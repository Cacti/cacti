<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

define('CACTI_LOCALE', 'en-US');
define('POLLER_VERBOSITY_DEBUG', 5);
define('RRDTOOL_OUTPUT_STDOUT', 0);
define('RRDTOOL_OUTPUT_GRAPH_DATA', 1);
define('RRDTOOL_OUTPUT_NULL', 2);
define('RRDTOOL_OUTPUT_BOOLEAN', 3);
define('RRDTOOL_OUTPUT_STDERR', 4);
define('RRDTOOL_OUTPUT_RETURN_STDERR', 5);

$boost_rrd_bench_binary = getenv('BOOST_BENCH_RRDTOOL') ?: '/usr/bin/rrdtool';
$config = array('cacti_server_os' => 'unix', 'is_web' => false);

/**
 * Return the one configuration value required by the production RRDtool protocol helper.
 *
 * @param string $name Cacti setting name.
 *
 * @return string Configured value.
 */
function read_config_option($name) {
	global $boost_rrd_bench_binary;

	return $name === 'path_rrdtool' ? $boost_rrd_bench_binary : '';
}

/**
 * Discard benchmark debug logging while retaining the production function signature.
 *
 * @return void No value is returned.
 */
function cacti_log() {
}

/**
 * Benchmark processes do not own a Cacti session.
 *
 * @return void No value is returned.
 */
function cacti_session_close() {
}

/**
 * Quote one RRDtool argument using the same contract as Cacti.
 *
 * @param string $value Argument value.
 *
 * @return string Shell-quoted argument.
 */
function cacti_escapeshellarg($value) {
	return escapeshellarg($value);
}

require dirname(__DIR__, 3) . '/lib/rrd.php';

/**
 * Execute one discrete RRDtool command for fixture setup or verification.
 *
 * @param array<int, string> $arguments RRDtool arguments excluding the binary.
 *
 * @return string Standard output.
 */
function boostRrdBenchCommand(array $arguments): string {
	global $boost_rrd_bench_binary;

	$process = proc_open(array_merge(array($boost_rrd_bench_binary), $arguments), array(
		0 => array('file', '/dev/null', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	), $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start RRDtool.');
	}

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	if ($status !== 0) {
		throw new RuntimeException(trim($stderr) ?: 'RRDtool command failed.');
	}

	return trim($stdout);
}

/**
 * Return one source's sharded RRD path.
 *
 * @param string $root Benchmark root.
 * @param int $source Source ID.
 *
 * @return string RRD path.
 */
function boostRrdBenchPath($root, $source): string {
	return sprintf('%s/%02x/source_%06d.rrd', $root, $source % 256, $source);
}

/**
 * Prepare independently writable real RRD files from one valid template.
 *
 * Copying is setup only; every measured operation is an acknowledged RRDtool update.
 *
 * @param string $root Fresh benchmark root.
 * @param int $sources Destination count.
 * @param int $base_time First update timestamp.
 *
 * @return array<string, mixed> Setup measurements.
 */
function boostRrdBenchPrepare($root, $sources, $base_time): array {
	if (file_exists($root)) {
		throw new RuntimeException('--prepare requires a path that does not already exist.');
	}

	$started = microtime(true);

	if (!mkdir($root, 0770, true)) {
		throw new RuntimeException('Unable to create benchmark root.');
	}

	for ($shard = 0; $shard < 256; $shard++) {
		if (!mkdir(sprintf('%s/%02x', $root, $shard), 0770)) {
			throw new RuntimeException('Unable to create benchmark shard.');
		}
	}

	$template = $root . '/template.rrd';
	boostRrdBenchCommand(array(
		'create',
		$template,
		'--start',
		(string) ($base_time - 60),
		'--step',
		'60',
		'DS:value:GAUGE:600:U:U',
		'RRA:AVERAGE:0.5:1:1000',
	));

	for ($source = 1; $source <= $sources; $source++) {
		if (!copy($template, boostRrdBenchPath($root, $source))) {
			throw new RuntimeException('Unable to copy RRD fixture for source ' . $source . '.');
		}
	}

	$marker_written = file_put_contents($root . '/.cacti-boost-rrd-benchmark', json_encode(array(
		'sources'   => $sources,
		'base_time' => $base_time,
	), JSON_THROW_ON_ERROR));

	if ($marker_written === false) {
		throw new RuntimeException('Unable to write the benchmark ownership marker.');
	}

	return array(
		'files'   => $sources,
		'seconds' => microtime(true) - $started,
		'bytes'   => filesize($template) * $sources,
	);
}

/**
 * Update a non-overlapping set of RRD destinations through Cacti's acknowledged worker.
 *
 * @param string $root Benchmark root.
 * @param array<int, int> $sources Randomized source IDs owned by this worker.
 * @param int $samples Timestamp updates per destination.
 * @param int $samples_per_command Maximum timestamp updates in one RRDtool command.
 * @param int $base_time First timestamp.
 * @param float $benchmark_started Parent benchmark start time.
 *
 * @return array<string, mixed> Worker measurements.
 */
function boostRrdBenchWorker($root, array $sources, $samples, $samples_per_command, $base_time, $benchmark_started): array {
	$pipe = rrd_init(true, true);

	if (!is_resource($pipe)) {
		throw new RuntimeException('Unable to start acknowledged RRDtool worker.');
	}

	$started = microtime(true);
	$first_ack = null;
	$commands = 0;
	$successful_samples = 0;
	$failed_commands = 0;
	$failures = array();

	foreach ($sources as $source) {
		$path = boostRrdBenchPath($root, $source);

		for ($offset = 0; $offset < $samples; $offset += $samples_per_command) {
			$command_samples = min($samples_per_command, $samples - $offset);
			$values = array();

			for ($sample = $offset; $sample < $offset + $command_samples; $sample++) {
				$values[] = ($base_time + ($sample * 60)) . ':' . (($source * 17 + $sample) % 100000);
			}

			$response = rrdtool_execute('update ' . escapeshellarg($path) .
				' --skip-past-updates --template value ' . implode(' ', $values),
				false, RRDTOOL_OUTPUT_RETURN_STDERR, $pipe, 'BOOST');

			if (!is_string($response) || preg_match('/(?:^|\n)OK(?: |\r?\n|$)/', $response) !== 1) {
				$failed_commands++;

				if (count($failures) < 10) {
					$failures[] = array(
						'source'   => $source,
						'offset'   => $offset,
						'response' => substr((string) $response, 0, 512),
					);
				}
			} else {
				$commands++;
				$successful_samples += $command_samples;

				if ($first_ack === null) {
					$first_ack = microtime(true) - $benchmark_started;
				}
			}
		}
	}

	rrd_close($pipe);

	return array(
		'commands'                  => $commands,
		'failed_commands'           => $failed_commands,
		'samples'                   => $successful_samples,
		'seconds'                   => microtime(true) - $started,
		'time_to_first_ack_seconds' => $first_ack,
		'peak_php_bytes'            => memory_get_peak_usage(true),
		'failures'                  => $failures,
	);
}

$options = getopt('', array(
	'rrd-dir:',
	'sources::',
	'samples::',
	'samples-per-command::',
	'workers::',
	'base-time::',
	'prepare',
));
$root = rtrim($options['rrd-dir'] ?? '', '/');
$sources = max(1, (int) ($options['sources'] ?? 60000));
$samples = max(1, min(10000, (int) ($options['samples'] ?? 100)));
$samples_per_command = max(1, min($samples, (int) ($options['samples-per-command'] ?? $samples)));
$workers = max(1, min(16, (int) ($options['workers'] ?? 1)));
$base_time = max(1700000000, (int) ($options['base-time'] ?? time() - 86400));
$prepare = array_key_exists('prepare', $options);

if ($root === '' || $root === '/' || dirname($root) === $root) {
	fwrite(STDERR, "--rrd-dir must be a specific disposable subdirectory\n");
	exit(64);
}

$setup = null;

if ($prepare) {
	$setup = boostRrdBenchPrepare($root, $sources, $base_time);
}

$marker = $root . '/.cacti-boost-rrd-benchmark';

if (!is_file($marker)) {
	fwrite(STDERR, "benchmark marker is missing; use --prepare with a fresh path\n");
	exit(65);
}

$metadata = json_decode((string) file_get_contents($marker), true);

if (!is_array($metadata) || (int) ($metadata['sources'] ?? 0) !== $sources) {
	fwrite(STDERR, "prepared destination count does not match --sources\n");
	exit(66);
}

$order = range(1, $sources);
mt_srand(1701);
shuffle($order);
$chunks = array_chunk($order, (int) ceil($sources / $workers));
$active = array();
$started = microtime(true);

foreach ($chunks as $chunk) {
	$sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

	if ($sockets === false) {
		throw new RuntimeException('Unable to create RRD benchmark worker socket.');
	}

	$pid = pcntl_fork();

	if ($pid === -1) {
		throw new RuntimeException('Unable to start RRD benchmark worker.');
	}

	if ($pid === 0) {
		fclose($sockets[0]);

		try {
			$result = boostRrdBenchWorker($root, $chunk, $samples, $samples_per_command, $base_time, $started);
			fwrite($sockets[1], json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
			fclose($sockets[1]);
			exit(0);
		} catch (Throwable $e) {
			fwrite($sockets[1], json_encode(array('error' => $e->getMessage())));
			fclose($sockets[1]);
			exit(1);
		}
	}

	fclose($sockets[1]);
	$active[$pid] = $sockets[0];
}

$results = array();

while (count($active)) {
	$pid = pcntl_wait($status);
	$payload = stream_get_contents($active[$pid]);
	fclose($active[$pid]);
	unset($active[$pid]);
	$result = json_decode($payload, true);

	if (!is_array($result) || isset($result['error']) || !pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
		throw new RuntimeException(is_array($result) ? ($result['error'] ?? 'RRD worker failed') : 'Invalid RRD worker result');
	}

	$results[] = $result;
}

$elapsed = microtime(true) - $started;
$successful_commands = array_sum(array_column($results, 'commands'));
$successful_samples = array_sum(array_column($results, 'samples'));
$failed_commands = array_sum(array_column($results, 'failed_commands'));
$first_acks = array_filter(array_column($results, 'time_to_first_ack_seconds'), static fn($value) => $value !== null);
$first_ack = count($first_acks) ? min($first_acks) : null;
$expected_last = $base_time + (($samples - 1) * 60);
$verification = array();

foreach (array(1, max(1, intdiv($sources, 2)), $sources) as $source) {
	$verification[$source] = (int) boostRrdBenchCommand(array('last', boostRrdBenchPath($root, $source)));
}

print json_encode(array(
	'rrdtool'                    => $boost_rrd_bench_binary,
	'destinations'               => $sources,
	'samples_per_destination'    => $samples,
	'samples_per_command'        => $samples_per_command,
	'commands_per_destination'   => (int) ceil($samples / $samples_per_command),
	'workers'                    => count($chunks),
	'randomization'              => 'deterministic Mersenne Twister shuffle, seed 1701',
	'setup'                      => $setup,
	'elapsed_seconds'            => $elapsed,
	'time_to_first_ack_seconds'  => $first_ack,
	'successful_commands'        => $successful_commands,
	'failed_commands'            => $failed_commands,
	'successful_samples'         => $successful_samples,
	'commands_per_second'        => $successful_commands / max(0.000001, $elapsed),
	'samples_per_second'         => $successful_samples / max(0.000001, $elapsed),
	'expected_last'              => $expected_last,
	'verified_last'              => $verification,
	'peak_php_bytes_per_process' => max(array_column($results, 'peak_php_bytes')),
	'workers_detail'             => $results,
	'claim_scope'                => 'real acknowledged RRDtool write-stage benchmark; no database read or Cacti metadata preparation',
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
