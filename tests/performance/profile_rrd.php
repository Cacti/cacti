<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software; you can redistribute it and/or modify it |
 | under the GNU General Public License, version 2 or later.                |
 | Cacti: The Complete RRDtool-based Graphing Solution                      |
 +-------------------------------------------------------------------------+
*/

// Offline diagnostic with test-bootstrap configuration and disposable RRDs.
// Usage: php profile_rrd.php <checkout> <mode> <iterations> [rrdtool binary]
// Modes: resolve, graph-options, command, persistent-update, persistent-batch,
// local-update. Timings exclude bootstrap and fixture creation.
if (PHP_SAPI !== 'cli') {
	exit(1);
}

$root       = realpath($argv[1] ?? '');
$mode       = $argv[2] ?? '';
$binary     = $argv[4] ?? '/usr/bin/rrdtool';
$modes      = ['resolve', 'graph-options', 'command', 'persistent-update', 'persistent-batch', 'local-update'];
$iterations = (int) ($argv[3] ?? 10000);

if ($root === false || $iterations < 1 || $iterations > 2500000 || !in_array($mode, $modes, true)) {
	throw new RuntimeException('Invalid arguments');
}
require $root . '/tests/bootstrap-unit.php';
require $root . '/lib/rrd.php';
$config[OPTIONS_CLI] = array_replace($config[OPTIONS_CLI] ?? [], [
	'storage_location'          => '', 'path_rrdtool' => $binary,
	'path_rrdtool_default_font' => '',
	'rrdtool_watermark'         => '', 'font_method' => 0,
	'default_date_format'       => GD_Y_MO_D, 'default_datechar' => 1,
]);
$_SESSION[OPTIONS_USER] = ['default_date_format' => GD_Y_MO_D, 'default_datechar' => 1, 'custom_fonts' => ''];
$options                = ['graph_start' => '1700000000', 'graph_end' => '1700003600', 'graph_height' => '120', 'graph_width' => '500', 'image_format' => 'png', 'graph_theme' => 'modern', 'plugin_option' => ['preserved' => true]];
$graph                  = [ 'auto_scale' => 'on', 'auto_scale_opts' => '1', 'lower_limit' => '0', 'upper_limit' => '100', 'auto_scale_log' => '', 'scale_log_units' => '', 'auto_scale_rigid' => '', 'unit_value' => '', 'unit_exponent_value' => '', 'height' => '120', 'width' => '500', 'image_format_id' => 3, 'title_cache' => 'Owned performance fixture', 'vertical_label' => 'Values', 'legend_position' => 'south', 'legend_direction' => 'topdown', 'left_axis_formatter' => 'numeric', 'right_axis_formatter' => 'numeric'];
$typed                  = class_exists(\Cacti\Rrd\Graph\GraphOptions::class);
$pipe                   = null;
$directory              = null;
$path                   = '/tmp/owned-perf.rrd';
$successful             = 0;
$last                   = '';

if (in_array($mode, ['persistent-update', 'local-update', 'persistent-batch'], true)) {
	$directory = sys_get_temp_dir() . '/cacti-rrd-perf-' . bin2hex(random_bytes(8));

	if (!mkdir($directory, 0700)) {
		throw new RuntimeException('Cannot create fixture');
	}
	$path = $directory . '/owned.rrd';

	if (in_array($mode, ['persistent-update', 'persistent-batch'], true)) {
		$pipe = rrd_init(false);

		if (!rrdtool_is_process($pipe)) {
			throw new RuntimeException('Cannot launch persistent RRDtool');
		}
	}

	if (rrdtool_execute(['create', $path, '--start', '1700000000', '--step', '60', 'DS:value:GAUGE:600:U:U', 'RRA:AVERAGE:0.5:1:1024'], false, RRDTOOL_OUTPUT_BOOLEAN, $pipe) !== true) {
		throw new RuntimeException('Cannot create RRD');
	}
}
$run = static function (int $i) use ($mode, $typed, $options, $graph, $path, $pipe, &$successful): string {
	if ($mode === 'resolve') {
		return json_encode(\Cacti\Rrd\Graph\GraphOptions::resolve($options), JSON_THROW_ON_ERROR);
	}

	if ($mode === 'graph-options') {
		$parameters = $typed ? \Cacti\Rrd\Graph\GraphOptions::resolve($options) : $options;
		$metadata   = $graph;

		return rrd_function_process_graph_options(1700000000, 1700003600, $metadata, $parameters);
	}

	if ($mode === 'command') {
		if ($typed) {
			$command = (new \Cacti\Rrd\DataSource\UpdateCommandBuilder())->build($path, 'value', '1700000060:42', true);

			return rrdtool_build_command($command);
		}

		return 'update ' . cacti_escapeshellarg($path) . ' --skip-past-updates --template value 1700000060:42';
	}
	$updated = rrdtool_function_update([$path => ['local_data_id' => 0, 'times' => [1700000060 + $i * 60 => ['value' => 42]]]], $pipe);

	if ($updated !== 1) {
		throw new RuntimeException('Update failed');
	}
	$successful += $updated;

	return (string) $updated;
};

try {
	// Warm resolver/configuration; timestamps remain monotonic for real updates.
	$warmup = in_array($mode, ['persistent-update', 'local-update', 'persistent-batch'], true) ? 5 : 100;

	for ($i = 0; $i < $warmup; $i++) {
		$run($i);
	}
	$successful = 0;
	$batch      = [];

	if ($mode === 'persistent-batch') {
		for ($i = $warmup; $i < $iterations + $warmup; $i++) {
			$batch[1700000060 + $i * 60] = ['value' => 42];
		}
	}
	$startCpu = getrusage();
	$start    = hrtime(true);

	if ($mode === 'persistent-batch') {
		$successful = rrdtool_function_update([$path => ['local_data_id' => 0, 'times' => $batch]], $pipe);

		if ($successful !== $iterations) {
			throw new RuntimeException('Batch incomplete');
		}
		$last = (string) $successful;
	} else {
		for ($i = $warmup; $i < $iterations + $warmup; $i++) {
			$last = $run($i);
		}
	}
	$elapsed = hrtime(true) - $start;
	$endCpu  = getrusage();
	$cpuUs   = (($endCpu['ru_utime.tv_sec'] - $startCpu['ru_utime.tv_sec']) + ($endCpu['ru_stime.tv_sec'] - $startCpu['ru_stime.tv_sec'])) * 1000000 + $endCpu['ru_utime.tv_usec'] - $startCpu['ru_utime.tv_usec'] + $endCpu['ru_stime.tv_usec'] - $startCpu['ru_stime.tv_usec'];

	if ($directory !== null) {
		$info = rrdtool_execute(['last', $path], false, RRDTOOL_OUTPUT_STDOUT, $pipe);

		if ((int) trim($info) !== 1700000060 + ($iterations + $warmup - 1) * 60) {
			throw new RuntimeException('Last timestamp mismatch');
		}
		$values = rrdtool_execute(['lastupdate', $path], false, RRDTOOL_OUTPUT_STDOUT, $pipe);

		if (!str_contains($values, trim($info) . ': 42')) {
			throw new RuntimeException('Last value mismatch');
		}
	}
	print json_encode(['mode' => $mode, 'php' => PHP_VERSION, 'typed' => $typed, 'iterations' => $iterations, 'elapsed_ms' => $elapsed / 1000000, 'us_per_call' => $elapsed / 1000 / $iterations, 'cpu_us_per_call' => $cpuUs / $iterations, 'peak_bytes' => memory_get_peak_usage(true), 'successful_updates' => $successful, 'output_sha256' => hash('sha256', $last)], JSON_THROW_ON_ERROR) . "\n";
} finally {
	if ($pipe !== null) {
		rrd_close($pipe);
	}

	if ($directory !== null) {
		if (is_file($path)) {
			unlink($path);
		} rmdir($directory);
	}
}
