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

require dirname(__DIR__, 3) . '/include/cli_check.php';

/**
 * Execute RRDtool with discrete arguments and fail on any diagnostic output.
 *
 * @param array<int, string> $arguments RRDtool arguments excluding the binary.
 *
 * @return string Captured standard output.
 */
function boostE2eRrdtool(array $arguments) {
	$command = array_merge(array('/usr/bin/rrdtool'), $arguments);
	$descriptors = array(
		0 => array('file', '/dev/null', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);
	$process = proc_open($command, $descriptors, $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start RRDtool.');
	}

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	if ($status !== 0) {
		throw new RuntimeException(trim($stderr) ?: 'RRDtool exited unsuccessfully.');
	}

	return trim($stdout);
}

/**
 * Create a two-data-source RRD used by the Boost scenario.
 *
 * @param string $path Destination path.
 * @param int $start RRD start timestamp.
 *
 * @return void No value is returned.
 */
function boostE2eCreateRrd($path, $start) {
	if (file_exists($path) && !unlink($path)) {
		throw new RuntimeException("Unable to replace $path.");
	}

	boostE2eRrdtool(array(
		'create',
		$path,
		'--start',
		(string) $start,
		'--step',
		'60',
		'DS:value_a:GAUGE:600:U:U',
		'DS:value_b:GAUGE:600:U:U',
		'RRA:AVERAGE:0.5:1:10000',
	));
}

/**
 * Store one Cacti setting without retaining stale rows from earlier scenarios.
 *
 * @param string $name Setting name.
 * @param string|int $value Setting value.
 *
 * @return void No value is returned.
 */
function boostE2eSetting($name, $value) {
	db_execute_prepared('INSERT INTO settings (name, value) VALUES (?, ?)
		ON DUPLICATE KEY UPDATE value = VALUES(value)', array($name, (string) $value), false);
}

/**
 * Return validated archive names in chronological suffix order.
 *
 * @return array<int, string> Archive table names.
 */
function boostE2eArchives() {
	$rows = db_fetch_assoc("SHOW TABLES LIKE 'poller_output_boost_arch_%'");
	$tables = array();

	foreach((array) $rows as $row) {
		$table = array_values($row)[0];

		if (preg_match('/^poller_output_boost_arch_\d+$/D', $table)) {
			$tables[] = $table;
		}
	}

	usort($tables, static function($left, $right) {
		$left = substr($left, strlen('poller_output_boost_arch_'));
		$right = substr($right, strlen('poller_output_boost_arch_'));

		return strlen($left) === strlen($right) ? strcmp($left, $right) : strlen($left) <=> strlen($right);
	});

	return $tables;
}

/**
 * Insert deterministically permuted rows so physical insert order cannot satisfy replay order by
 * accident.
 *
 * @param string $table Validated Boost queue table.
 * @param int $sources Source count.
 * @param int $samples Samples per source.
 * @param int $base_time First timestamp.
 * @param int $seed Permutation seed.
 *
 * @return int Inserted rows.
 */
function boostE2eInsertRows($table, $sources, $samples, $base_time, $seed) {
	if (!preg_match('/^poller_output_boost(?:_arch_\d+)?$/D', $table)) {
		throw new InvalidArgumentException('Invalid Boost output table.');
	}

	$rows = array();

	for ($source = 1; $source <= $sources; $source++) {
		for ($sample = 0; $sample < $samples; $sample++) {
			$timestamp = $base_time + ($sample * 60);
			$rows[] = array($source, 'value_a', $timestamp, ($source * 100) + $sample);
			$rows[] = array($source, 'value_b', $timestamp, ($source * 1000) + $sample);
		}
	}

	usort($rows, static function($left, $right) use ($seed) {
		$left_key = (($left[0] * 48271) + ($left[2] * 17) + crc32($left[1]) + $seed) % 2147483647;
		$right_key = (($right[0] * 48271) + ($right[2] * 17) + crc32($right[1]) + $seed) % 2147483647;

		return $left_key <=> $right_key;
	});
	/* db_execute_prepared does not expose a reusable statement, so insert bounded fixture rows. */
	foreach($rows as $row) {
		if (db_execute_prepared("INSERT INTO `$table`
			(local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)", $row, false) === false) {
			throw new RuntimeException("Unable to seed $table.");
		}
	}

	return count($rows);
}

/**
 * Read the last update timestamp from one RRD.
 *
 * @param string $path RRD path.
 *
 * @return int Last update timestamp.
 */
function boostE2eLastUpdate($path) {
	return (int) boostE2eRrdtool(array('last', $path));
}

/**
 * Capture the complete observable state used by end-to-end assertions.
 *
 * @param int $sources Source count.
 * @param int $expected_last Expected final RRD timestamp.
 *
 * @return array<string, mixed> Current archive, queue, worker, and RRD state.
 */
function boostE2eState($sources, $expected_last) {
	$rrds = array();

	for ($source = 1; $source <= $sources; $source++) {
		$path = (string) db_fetch_cell_prepared('SELECT rrd_path FROM poller_item
			WHERE local_data_id = ? ORDER BY rrd_name LIMIT 1', array($source));
		$last_update = null;
		$error = null;

		if ($path !== '' && is_file($path)) {
			try {
				$last_update = boostE2eLastUpdate($path);
			} catch (Throwable $e) {
				$error = $e->getMessage();
			}
		}

		$rrds[$source] = array('path' => $path, 'last_update' => $last_update, 'error' => $error);
	}

	return array(
		'archives'           => boostE2eArchives(),
		'live_rows'          => (int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost'),
		'failure_queue'      => db_fetch_assoc('SELECT local_data_id, latest_sample, sample_count,
			retry_count, last_error FROM poller_output_boost_failures ORDER BY local_data_id'),
		'process_completions' => db_fetch_assoc('SELECT run_id, child_id, status
			FROM poller_output_boost_processes ORDER BY sock_int_value'),
		'active_processes'   => (int) db_fetch_cell("SELECT COUNT(*) FROM processes WHERE tasktype = 'boost'"),
		'boost_status'       => read_config_option('boost_poller_status'),
		'failure_depth'      => (int) read_config_option('boost_failure_queue_depth'),
		'expected_last'      => $expected_last,
		'rrds'               => $rrds,
	);
}

/**
 * Fail one end-to-end assertion with an actionable diagnostic.
 *
 * @param bool $condition Assertion result.
 * @param string $message Failure detail.
 *
 * @return void No value is returned.
 */
function boostE2eAssert($condition, $message) {
	if (!$condition) {
		throw new RuntimeException('Boost end-to-end assertion failed: ' . $message);
	}
}

/**
 * Return whether every recorded child completed with positive progress.
 *
 * @param array<int, array<string, mixed>> $completions Child completion rows.
 *
 * @return bool True when at least one child completed successfully.
 */
function boostE2eCompletionsSucceeded(array $completions) {
	if (!count($completions)) {
		return false;
	}

	foreach($completions as $completion) {
		if (!is_numeric($completion['status']) || (int) $completion['status'] <= 0) {
			return false;
		}
	}

	return true;
}

/**
 * Assert that selected RRD destinations reached one exact timestamp.
 *
 * @param array<string, mixed> $state Captured end-to-end state.
 * @param int $expected Expected RRD timestamp.
 * @param array<int, int> $excluded_sources Sources intentionally excluded from this assertion.
 *
 * @return void No value is returned.
 */
function boostE2eAssertRrdTimestamps(array $state, $expected, array $excluded_sources = array()) {
	foreach($state['rrds'] as $source => $rrd) {
		if (in_array((int) $source, $excluded_sources, true)) {
			continue;
		}

		boostE2eAssert($rrd['error'] === null, "source $source has an RRD error");
		boostE2eAssert($rrd['last_update'] === $expected,
			"source $source last update {$rrd['last_update']} does not equal $expected");
	}
}

/**
 * Validate one named recovery checkpoint before the next fixture resets its state.
 *
 * @param string $checkpoint Checkpoint name.
 * @param array<string, mixed> $state Captured end-to-end state.
 * @param int $base_time Original fixture base timestamp.
 * @param int $expected_last Scenario's expected final timestamp.
 * @param int $broken_source Source intentionally assigned a corrupt RRD.
 * @param int $sources Source count.
 *
 * @return void No value is returned.
 */
function boostE2eAssertState($checkpoint, array $state, $base_time, $expected_last, $broken_source, $sources) {
	boostE2eAssert($state['active_processes'] === 0, "$checkpoint left registered Boost processes");

	if ($checkpoint === 'isolated-failure') {
		boostE2eAssert(count($state['archives']) === 0, 'isolated failure retained an archive');
		boostE2eAssert($state['live_rows'] === 0, 'isolated failure left live rows');
		boostE2eAssert(count($state['failure_queue']) === 1, 'isolated failure did not queue one destination');
		boostE2eAssert((int) $state['failure_queue'][0]['local_data_id'] === $broken_source,
			'isolated failure queued the wrong destination');
		boostE2eAssert($state['failure_depth'] === 1, 'isolated failure depth is not one');
		boostE2eAssert(boostE2eCompletionsSucceeded($state['process_completions']),
			'healthy isolated-failure work did not complete');
		boostE2eAssertRrdTimestamps($state, $expected_last, array($broken_source));
		boostE2eAssert($state['rrds'][$broken_source]['error'] !== null,
			'corrupt isolated destination unexpectedly became readable');
	} elseif ($checkpoint === 'isolated-recovered') {
		boostE2eAssert(count($state['archives']) === 0 && $state['live_rows'] === 0,
			'isolated recovery left queued archive rows');
		boostE2eAssert(count($state['failure_queue']) === 0 && $state['failure_depth'] === 0,
			'isolated recovery left a retry queued');
		boostE2eAssertRrdTimestamps($state, $expected_last);
	} elseif ($checkpoint === 'permanent-failures') {
		boostE2eAssert(count($state['archives']) === 0 && $state['live_rows'] === 0,
			'permanent destination failures retained the archive');
		boostE2eAssert(count($state['failure_queue']) === $sources,
			'permanent destination queue depth does not match the source count');
		boostE2eAssert($state['failure_depth'] === $sources,
			'persisted permanent-failure depth does not match the source count');
		boostE2eAssert(boostE2eCompletionsSucceeded($state['process_completions']),
			'permanent destination isolation did not complete the archive');

		$queue_ids = array_map('intval', array_column($state['failure_queue'], 'local_data_id'));
		sort($queue_ids, SORT_NUMERIC);
		boostE2eAssert($queue_ids === range(1, $sources), 'permanent failure queue omitted a destination');

		foreach($state['rrds'] as $source => $rrd) {
			boostE2eAssert($rrd['error'] !== null, "permanent destination $source unexpectedly became readable");
		}
	} elseif ($checkpoint === 'backend-outage') {
		boostE2eAssert(count($state['archives']) === 1 && $state['live_rows'] === 0,
			'backend outage did not retain exactly one sealed archive');
		boostE2eAssert(count($state['failure_queue']) === 8 && $state['failure_depth'] === 8,
			'backend circuit did not stop after eight queued probes');

		$failed_completion = false;

		foreach($state['process_completions'] as $completion) {
			$failed_completion = $failed_completion || (int) $completion['status'] < 0;
		}

		boostE2eAssert($failed_completion, 'backend outage did not record a fatal child completion');
		boostE2eAssertRrdTimestamps($state, $base_time - 60);
	} elseif ($checkpoint === 'backend-recovered' || $checkpoint === 'final') {
		boostE2eAssert(count($state['archives']) === 0 && $state['live_rows'] === 0,
			"$checkpoint left archive rows");
		boostE2eAssert(count($state['failure_queue']) === 0 && $state['failure_depth'] === 0,
			"$checkpoint left failed destinations queued");
		boostE2eAssert(boostE2eCompletionsSucceeded($state['process_completions']),
			"$checkpoint did not record successful child completion");
		boostE2eAssertRrdTimestamps($state, $expected_last);
	} elseif ($checkpoint === 'shared-path') {
		boostE2eAssert(count($state['archives']) === 1 && $state['live_rows'] === 0,
			'shared-path refusal did not retain exactly one sealed archive');
		boostE2eAssert(count($state['failure_queue']) === 0 && $state['failure_depth'] === 0,
			'shared-path refusal unexpectedly queued a destination failure');
		boostE2eAssert(count($state['process_completions']) === 0,
			'shared-path refusal launched a child');

		foreach($state['rrds'] as $source => $rrd) {
			boostE2eAssert($rrd['last_update'] !== $expected_last,
				"shared-path refusal wrote source $source");
		}
	} elseif ($checkpoint === 'worker-crash') {
		boostE2eAssert(count($state['archives']) === 1 && $state['live_rows'] === 0,
			'worker crash did not retain exactly one sealed archive');
		boostE2eAssert(count($state['failure_queue']) === 0 && $state['failure_depth'] === 0,
			'worker crash unexpectedly queued a destination failure');
		boostE2eAssert(count($state['process_completions']) === 0,
			'killed worker unexpectedly recorded completion');
	} else {
		throw new InvalidArgumentException("Unknown Boost end-to-end checkpoint: $checkpoint");
	}
}

/**
 * Reset every Boost queue, archive, process, and data-source table before a scenario.
 *
 * @return void No value is returned.
 */
function boostE2eResetScenarioTables() {
	foreach(boostE2eArchives() as $table) {
		db_execute("DROP TABLE `$table`");
	}

	db_execute('TRUNCATE TABLE poller_output_boost');
	db_execute('TRUNCATE TABLE poller_output_boost_failures');
	db_execute('TRUNCATE TABLE poller_output_boost_processes');
	db_execute('TRUNCATE TABLE poller_output_boost_local_data_ids');
	db_execute("DELETE FROM processes WHERE tasktype = 'boost'");
	db_execute('DELETE FROM data_template_rrd');
	db_execute('DELETE FROM graph_templates_item');
	db_execute('DELETE FROM poller_item');
	db_execute('DELETE FROM data_local');
}

/**
 * Provision one two-data-source Boost source with its RRD and poller items.
 *
 * @param int $source Local data ID.
 * @param string $path RRD destination path.
 * @param int $start RRD start timestamp.
 *
 * @return void No value is returned.
 */
function boostE2eProvisionSource($source, $path, $start) {
	db_execute_prepared('INSERT INTO data_local
		(id, data_template_id, host_id, snmp_query_id, snmp_index, orphan)
		VALUES (?, 0, 0, 0, ?, 0)', array($source, (string) $source), false);

	boostE2eCreateRrd($path, $start);

	foreach(array('value_a', 'value_b') as $rrd_name) {
		db_execute_prepared('INSERT INTO data_template_rrd
			(local_data_id, data_template_id, rrd_maximum, rrd_minimum, rrd_heartbeat,
			data_source_type_id, data_source_name, data_input_field_id)
			VALUES (?, 0, ?, ?, 600, 1, ?, 34)', array($source, 'U', 'U', $rrd_name), false);
		$data_template_rrd_id = db_fetch_insert_id();
		db_execute_prepared('INSERT INTO graph_templates_item (task_item_id)
			VALUES (?)', array($data_template_rrd_id), false);
		db_execute_prepared('INSERT INTO poller_item
			(local_data_id, poller_id, host_id, action, present, rrd_name, rrd_path, rrd_num, rrd_step)
			VALUES (?, 1, 0, 1, 1, ?, ?, 2, 60)', array($source, $rrd_name, $path), false);
	}
}

$options = getopt('', array(
	'action:',
	'rra-dir:',
	'sources::',
	'samples::',
	'broken-source::',
	'php-binary::',
));
$action = $options['action'] ?? '';
$rra_dir = rtrim($options['rra-dir'] ?? '', '/');
$sources = max(8, (int) ($options['sources'] ?? 12));
$samples = max(1, (int) ($options['samples'] ?? 4));
$broken_source = max(1, min($sources, (int) ($options['broken-source'] ?? $sources)));
$php_binary = $options['php-binary'] ?? PHP_BINARY;

if ($rra_dir === '' || !is_dir($rra_dir) || !is_writable($rra_dir)) {
	fwrite(STDERR, "--rra-dir must be an existing writable disposable directory\n");
	exit(64);
}

$stored_base_time = (int) read_config_option('boost_e2e_base_time');
$base_time = $stored_base_time > 0 ? $stored_base_time : time() - 86400;
$default_expected_last = $base_time + 7200 + (($samples - 1) * 60);
$stored_expected_last = (int) read_config_option('boost_e2e_expected_last');
$expected_last = $stored_expected_last > 0 ? $stored_expected_last : $default_expected_last;

if ($action === 'setup-isolated-failure') {
	boostE2eResetScenarioTables();

	boostE2eSetting('path_rrdtool', '/usr/bin/rrdtool');
	boostE2eSetting('path_php_binary', $php_binary);
	boostE2eSetting('path_cactilog', '/tmp/cacti-boost-e2e.log');
	boostE2eSetting('path_boost_log', '');
	boostE2eSetting('rrdtool_version', '1.7.0');
	boostE2eSetting('boost_rrd_update_enable', 'on');
	boostE2eSetting('boost_parallel', 2);
	boostE2eSetting('boost_rrd_update_max_runtime', 300);
	boostE2eSetting('boost_rrd_update_max_records_per_select', 7);
	boostE2eSetting('boost_rrd_update_string_length', 2048);
	boostE2eSetting('boost_rrd_update_interval', 120);
	boostE2eSetting('boost_poller_mem_limit', 256);
	boostE2eSetting('boost_failure_queue_max_records', 100);
	boostE2eSetting('boost_failure_retry_budget', 100);
	boostE2eSetting('boost_failure_max_retries', 8);
	boostE2eSetting('boost_failure_max_age_days', 7);
	boostE2eSetting('boost_poller_started', 0);
	boostE2eSetting('boost_poller_status', 'not started');
	boostE2eSetting('boost_e2e_base_time', $base_time);
	boostE2eSetting('boost_e2e_expected_last', $default_expected_last);
	boostE2eSetting('log_verbosity', 5);

	for ($source = 1; $source <= $sources; $source++) {
		boostE2eProvisionSource($source, $rra_dir . '/source_' . $source . '.rrd', $base_time - 60);
	}

	$broken_path = $rra_dir . '/broken.rrd';
	file_put_contents($broken_path, "not an RRD\n");
	db_execute_prepared('UPDATE poller_item SET rrd_path = ? WHERE local_data_id = ?',
		array($broken_path, $broken_source), false);

	foreach(array(1001, 1002) as $sequence) {
		$table = 'poller_output_boost_arch_' . $sequence;
		db_execute("CREATE TABLE `$table` LIKE poller_output_boost");
	}

	$rows = 0;
	$rows += boostE2eInsertRows('poller_output_boost_arch_1001', $sources, $samples, $base_time, 17);
	$rows += boostE2eInsertRows('poller_output_boost_arch_1002', $sources, $samples, $base_time + 3600, 29);
	$rows += boostE2eInsertRows('poller_output_boost', $sources, $samples, $base_time + 7200, 43);

	print json_encode(array(
		'action'              => $action,
		'sources'             => $sources,
		'data_sources_per_rrd' => 2,
		'rows'                => $rows,
		'archives'            => boostE2eArchives(),
		'live_rows'           => (int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost'),
		'broken_source'       => $broken_source,
		'broken_path'         => $broken_path,
		'expected_last'       => $default_expected_last,
		'randomization'       => 'deterministic per-archive permutation',
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($action === 'repair-isolated-failure') {
	$broken_path = $rra_dir . '/broken.rrd';
	boostE2eCreateRrd($broken_path, $base_time - 60);
	$retry_ready = db_execute_prepared('UPDATE poller_output_boost_failures
		SET next_retry = NOW() WHERE local_data_id = ?', array($broken_source), false);

	if ($retry_ready === false || db_affected_rows() !== 1) {
		throw new RuntimeException('Unable to make the isolated failure immediately retryable.');
	}

	print json_encode(array('action' => $action, 'path' => $broken_path), JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($action === 'setup-permanent-failures') {
	foreach(boostE2eArchives() as $table) {
		db_execute("DROP TABLE `$table`");
	}

	db_execute('TRUNCATE TABLE poller_output_boost');
	db_execute('TRUNCATE TABLE poller_output_boost_failures');
	db_execute('TRUNCATE TABLE poller_output_boost_processes');
	db_execute("DELETE FROM processes WHERE tasktype = 'boost'");
	boostE2eSetting('boost_parallel', 1);
	boostE2eSetting('path_rrdtool', '/usr/bin/rrdtool');
	boostE2eSetting('boost_poller_started', 0);
	boostE2eSetting('boost_poller_status', 'not started');
	$failure_time = $base_time + 9000;
	boostE2eSetting('boost_e2e_expected_last', $failure_time);

	for ($source = 1; $source <= $sources; $source++) {
		$path = $rra_dir . '/permanent_' . $source . '.rrd';
		file_put_contents($path, "not an RRD\n");
		db_execute_prepared('UPDATE poller_item SET rrd_path = ? WHERE local_data_id = ?',
			array($path, $source), false);
	}

	$rows = boostE2eInsertRows('poller_output_boost', $sources, 1, $failure_time, 53);

	print json_encode(array(
		'action'         => $action,
		'sources'        => $sources,
		'rows'           => $rows,
		'expected_last'  => $failure_time,
		'failure_policy' => 'each permanent RRD failure is queued without retaining its archive',
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($action === 'setup-backend-outage') {
	foreach(boostE2eArchives() as $table) {
		db_execute("DROP TABLE `$table`");
	}

	db_execute('TRUNCATE TABLE poller_output_boost');
	db_execute('TRUNCATE TABLE poller_output_boost_failures');
	db_execute('TRUNCATE TABLE poller_output_boost_processes');
	db_execute("DELETE FROM processes WHERE tasktype = 'boost'");
	boostE2eSetting('boost_parallel', 1);
	boostE2eSetting('path_rrdtool', '/bin/false');
	boostE2eSetting('rrdtool_version', '1.7.2');
	boostE2eSetting('boost_poller_started', 0);
	boostE2eSetting('boost_poller_status', 'not started');
	$outage_time = $base_time + 10800;
	boostE2eSetting('boost_e2e_expected_last', $outage_time);

	for ($source = 1; $source <= $sources; $source++) {
		$path = $rra_dir . '/outage_' . $source . '.rrd';
		boostE2eCreateRrd($path, $base_time - 60);
		db_execute_prepared('UPDATE poller_item SET rrd_path = ? WHERE local_data_id = ?',
			array($path, $source), false);
	}

	$rows = boostE2eInsertRows('poller_output_boost', $sources, 1, $outage_time, 61);

	print json_encode(array(
		'action'         => $action,
		'sources'        => $sources,
		'rows'           => $rows,
		'expected_last'  => $outage_time,
		'parallelism'    => 1,
		'rrdtool_binary' => '/bin/false',
		'failure_policy' => 'circuit opens after eight failures before any success',
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($action === 'repair-backend-outage') {
	boostE2eSetting('path_rrdtool', '/usr/bin/rrdtool');

	print json_encode(array('action' => $action, 'rrdtool_binary' => '/usr/bin/rrdtool'), JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($action === 'retry-backend-outage' || $action === 'retry-failures') {
	require_once($config['base_path'] . '/lib/boost.php');
	require_once($config['base_path'] . '/lib/rrd.php');

	$rrdtool_pipe = rrd_init(true, true);

	if (!is_resource($rrdtool_pipe) && !is_array($rrdtool_pipe)) {
		throw new RuntimeException('Unable to start the configured RRDtool worker.');
	}

	$stats = boost_retry_rrd_failures($rrdtool_pipe);
	rrd_close($rrdtool_pipe);

	print json_encode(array(
		'action' => $action,
		'stats'  => $stats,
		'queue'  => db_fetch_assoc('SELECT local_data_id, retry_count, next_retry, last_error
			FROM poller_output_boost_failures ORDER BY local_data_id'),
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($action === 'setup-shared-path') {
	foreach(boostE2eArchives() as $table) {
		db_execute("DROP TABLE `$table`");
	}

	db_execute('TRUNCATE TABLE poller_output_boost');
	db_execute('TRUNCATE TABLE poller_output_boost_failures');
	db_execute('TRUNCATE TABLE poller_output_boost_processes');
	db_execute("DELETE FROM processes WHERE tasktype = 'boost'");
	boostE2eSetting('boost_parallel', 2);
	boostE2eSetting('path_rrdtool', '/usr/bin/rrdtool');
	boostE2eSetting('boost_poller_started', 0);
	boostE2eSetting('boost_poller_status', 'not started');
	$shared_time = max($base_time + 12600, $expected_last + 3600);
	$shared_path = $rra_dir . '/shared-local-data-ids.rrd';
	boostE2eCreateRrd($shared_path, $base_time - 60);
	boostE2eSetting('boost_e2e_expected_last', $shared_time);
	db_execute_prepared('UPDATE poller_item SET rrd_path = ? WHERE local_data_id IN (?, ?)',
		array($shared_path, 1, 2), false);
	$rows = boostE2eInsertRows('poller_output_boost', $sources, 1, $shared_time, 71);

	print json_encode(array(
		'action'        => $action,
		'rows'          => $rows,
		'expected_last' => $shared_time,
		'shared_path'   => $shared_path,
		'shared_ids'    => array(1, 2),
		'policy'        => 'retain before workers because local_data_id ranges cannot order one shared file',
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($action === 'setup-worker-crash') {
	foreach(boostE2eArchives() as $table) {
		db_execute("DROP TABLE `$table`");
	}

	db_execute('TRUNCATE TABLE poller_output_boost');
	db_execute('TRUNCATE TABLE poller_output_boost_failures');
	db_execute('TRUNCATE TABLE poller_output_boost_processes');
	db_execute("DELETE FROM processes WHERE tasktype = 'boost'");
	boostE2eSetting('boost_parallel', 1);
	boostE2eSetting('path_rrdtool', $config['base_path'] . '/tests/fixtures/Core/BoostSlowRrdtool.php');
	boostE2eSetting('rrdtool_version', '1.7.2');
	boostE2eSetting('boost_poller_started', 0);
	boostE2eSetting('boost_poller_status', 'not started');
	$crash_time = max($base_time + 14400, $expected_last + 3600);
	boostE2eSetting('boost_e2e_expected_last', $crash_time);

	for ($source = 1; $source <= $sources; $source++) {
		$path = $rra_dir . '/crash_' . $source . '.rrd';
		boostE2eCreateRrd($path, $base_time - 60);
		db_execute_prepared('UPDATE poller_item SET rrd_path = ? WHERE local_data_id = ?',
			array($path, $source), false);
	}

	$rows = boostE2eInsertRows('poller_output_boost', $sources, 1, $crash_time, 79);

	print json_encode(array(
		'action'         => $action,
		'sources'        => $sources,
		'rows'           => $rows,
		'expected_last'  => $crash_time,
		'rrdtool_proxy'  => $config['base_path'] . '/tests/fixtures/Core/BoostSlowRrdtool.php',
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($action === 'kill-worker') {
	$deadline = microtime(true) + 15;
	$pid      = 0;
	$signal   = defined('SIGKILL') ? constant('SIGKILL') : 9;

	do {
		$pid = (int) db_fetch_cell("SELECT pid FROM processes
			WHERE tasktype = 'boost' AND taskname = 'child'
			ORDER BY pid LIMIT 1");

		if ($pid > 1) {
			break;
		}

		usleep(100000);
	} while (microtime(true) < $deadline);

	if ($pid <= 1 || !function_exists('posix_kill') || !posix_kill($pid, $signal)) {
		throw new RuntimeException('Unable to locate and kill the disposable Boost worker.');
	}

	print json_encode(array('action' => $action, 'killed_pid' => $pid), JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($action === 'on-demand-flush') {
	/**
	 * Exercise the real on-demand (WebUI) path: boost_process_poller_output() must always process
	 * its data immediately without any archive lifecycle or per-RRD lock, and tolerate a missing
	 * archive table silently. This provisions one healthy source, stages archive and live rows, and
	 * flushes them straight into the RRD.
	 */
	include_once($config['library_path'] . '/boost.php');

	boostE2eResetScenarioTables();
	db_execute('DELETE FROM poller_time');

	boostE2eSetting('path_rrdtool', '/usr/bin/rrdtool');
	boostE2eSetting('path_php_binary', $php_binary);
	boostE2eSetting('path_cactilog', '/tmp/cacti-boost-e2e.log');
	boostE2eSetting('path_boost_log', '');
	boostE2eSetting('rrdtool_version', '1.7.0');
	boostE2eSetting('boost_rrd_update_enable', 'on');
	boostE2eSetting('boost_rrd_update_max_records_per_select', 50000);
	boostE2eSetting('boost_rrd_update_string_length', 2048);
	boostE2eSetting('log_verbosity', 5);

	$source = 1;
	$path   = $rra_dir . '/on_demand_' . $source . '.rrd';
	boostE2eProvisionSource($source, $path, $base_time - 60);

	$archive = 'poller_output_boost_arch_2001';
	db_execute("CREATE TABLE `$archive` LIKE poller_output_boost");

	$rows  = boostE2eInsertRows($archive, 1, $samples, $base_time, 11);
	$rows += boostE2eInsertRows('poller_output_boost', 1, $samples, $base_time + 3600, 23);

	$before  = boostE2eLastUpdate($path);
	$handled = boost_process_poller_output($source);
	$after   = boostE2eLastUpdate($path);

	boostE2eAssert($handled > 0, 'on-demand flush handled no rows');
	boostE2eAssert($after > $before, 'on-demand flush did not advance the RRD');
	boostE2eAssert((int) db_fetch_cell("SELECT COUNT(*) FROM `$archive`") === 0,
		'on-demand flush left archive rows');
	boostE2eAssert((int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost') === 0,
		'on-demand flush left live rows');

	print json_encode(array(
		'action'      => $action,
		'handled'     => (int) $handled,
		'rows'        => $rows,
		'last_before' => $before,
		'last_after'  => $after,
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} elseif ($action === 'status' || strpos($action, 'assert-') === 0) {
	$state = boostE2eState($sources, $expected_last);
	$state['action'] = $action;

	if ($action !== 'status') {
		boostE2eAssertState(substr($action, strlen('assert-')), $state, $base_time,
			$expected_last, $broken_source, $sources);
		$state['assertion'] = 'passed';
	}

	print json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
	fwrite(STDERR, "--action must be a setup, repair, retry, status, or documented assert action\n");
	exit(64);
}
