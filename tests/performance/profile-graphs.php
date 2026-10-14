<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require __DIR__ . '/profile-bootstrap.php';

$scenario = $argv[1] ?? '';
profile_check(in_array($scenario, ['render', 'boost'], true), 'Use render or boost.');
$fixture = json_decode(file_get_contents('/profiles/fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$samples = [];

function profile_io(): ?array {
	$path = '/sys/fs/cgroup/io.stat';

	if (!is_readable($path)) {
		return null;
	}
	$devices = [];

	foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		$fields = explode(' ', $line);
		$device = array_shift($fields);

		foreach ($fields as $field) {
			[$name, $value]          = explode('=', $field, 2);
			$devices[$device][$name] = (int) $value;
		}
	}

	return $devices;
}

if ($scenario === 'boost') {
	set_config_option('boost_rrd_update_enable', 'on');
	set_config_option('boost_rrd_update_system_enable', 'on');
}

// Separate process per scenario. The first pass has cold application caches;
// later passes reuse them. Neither pass flushes the OS or database page cache.
for ($pass = 0; $pass < 4; $pass++) {
	if ($scenario === 'boost') {
		for ($id = 1; $id <= 50; $id++) {
			db_execute_prepared('INSERT INTO poller_output_boost (local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)',
				[$id, 'value', $fixture['end'] + ($pass + 1) * 300, (string) (30 + $id)]);
		}
	}
	$usage       = getrusage();
	$child_usage = getrusage(1);
	$io          = profile_io();
	$start       = hrtime(true);
	$bytes       = 0;

	for ($id = 1; $id <= 50; $id++) {
		$options = ['graph_start' => $fixture['start'], 'graph_end' => $fixture['end'], 'image_format' => 'png'];
		$meta    = [];
		$output  = rrdtool_function_graph($id, 0, $options, null, $meta, 1);
		profile_check(is_string($output) && str_starts_with($output, "\x89PNG\r\n\x1a\n"), 'Graph ' . $id . ' did not render a PNG.');
		$bytes += strlen($output);
	}
	$after       = getrusage();
	$child_after = getrusage(1);
	$io_after    = profile_io();
	$io_delta    = null;

	if ($io !== null && $io_after !== null) {
		$io_delta = [];

		foreach ($io_after as $device => $fields) {
			foreach ($fields as $name => $value) {
				$io_delta[$device][$name] = $value - ($io[$device][$name] ?? 0);
			}
		}
	}
	$cpu       = static fn (array $value, string $type): float => $value['ru_' . $type . '.tv_sec'] + $value['ru_' . $type . '.tv_usec'] / 1000000;
	$samples[] = [
		'pass'                         => $pass, 'application_cache' => $pass === 0 ? 'cold' : 'warm',
		'elapsed_ms'                   => (hrtime(true) - $start) / 1000000,
		'php_cpu_ms'                   => (($cpu($after, 'utime') + $cpu($after, 'stime')) - ($cpu($usage, 'utime') + $cpu($usage, 'stime'))) * 1000,
		'child_cpu_ms'                 => (($cpu($child_after, 'utime') + $cpu($child_after, 'stime')) - ($cpu($child_usage, 'utime') + $cpu($child_usage, 'stime'))) * 1000,
		'process_max_rss_kb'           => $after['ru_maxrss'],
		'container_io_delta_by_device' => $io_delta,
		'peak_memory_bytes'            => memory_get_peak_usage(true), 'image_bytes' => $bytes,
	];

	if ($scenario === 'boost') {
		profile_check((int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost') === 0, 'Boost left fixture samples unprocessed.');

		for ($id = 1; $id <= 50; $id++) {
			$last = rrdtool_execute(['last', CACTI_PATH_BASE . '/rra/profile_' . $id . '.rrd'], true);
			profile_check((int) trim((string) $last) === $fixture['end'] + ($pass + 1) * 300, 'Boost did not update RRD ' . $id . '.');
		}
	}
}

// Browser workloads use the same non-Boost configuration after server profiling.
if ($scenario === 'boost') {
	set_config_option('boost_rrd_update_enable', '');
	set_config_option('boost_rrd_update_system_enable', '');
}

print json_encode([
	'scenario' => $scenario, 'kind' => 'disposable SQL/RRD fixture; no live SNMP', 'graphs_per_pass' => 50,
	'php'      => PHP_VERSION, 'xdebug_profile' => function_exists('xdebug_get_profiler_filename') ? basename((string) xdebug_get_profiler_filename()) : null,
	'samples'  => $samples,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
