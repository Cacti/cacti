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

require __DIR__ . '/common.php';

/**
 * Build contiguous, row-balanced local-data-ID ranges from one sealed archive.
 *
 * @param PDO $pdo Database connection.
 * @param string $table Validated archive table.
 * @param int $target_rows Approximate rows per range.
 *
 * @return array<int, array{start: int, end: int, expected_rows: int}> Ranges.
 */
function boostBenchRanges(PDO $pdo, $table, $target_rows): array {
	if (!preg_match('/^poller_output_boost_arch_\d+$/D', $table)) {
		throw new InvalidArgumentException('Invalid archive table name.');
	}

	$target_rows = max(1, (int) $target_rows);
	$sql = "WITH source_rows AS (
		SELECT local_data_id, COUNT(*) AS archive_rows
		FROM `$table` FORCE INDEX (PRIMARY)
		GROUP BY local_data_id
	), cumulative AS (
		SELECT local_data_id, archive_rows,
			SUM(archive_rows) OVER (ORDER BY local_data_id) AS running_rows
		FROM source_rows
	), assigned AS (
		SELECT local_data_id, archive_rows,
			FLOOR((running_rows - archive_rows) / $target_rows) + 1 AS range_handler
		FROM cumulative
	)
	SELECT MIN(local_data_id) AS range_start, MAX(local_data_id) AS range_end,
		SUM(archive_rows) AS expected_rows
	FROM assigned
	GROUP BY range_handler
	ORDER BY range_handler";
	$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
	$ranges = array();

	foreach($rows as $row) {
		$ranges[] = array(
			'start'         => (int) $row['range_start'],
			'end'           => (int) $row['range_end'],
			'expected_rows' => (int) $row['expected_rows'],
		);
	}

	return $ranges;
}

/**
 * Scan one non-overlapping range with one LIMIT-less clustered-primary-key query.
 *
 * This is a synthetic database-only consumer. It deliberately performs no RRD writes and does
 * not delete the archive.
 *
 * @param string $table Validated archive table.
 * @param array{start: int, end: int, expected_rows: int} $range Source range.
 * @return array<string, mixed> Worker measurements.
 */
function boostBenchDrainRange($table, array $range): array {
	$pdo = boostBenchPdo(false, false);
	$query_durations = array();
	$rows = 0;
	$first_row_seconds = null;
	$started = microtime(true);
	$query_started = microtime(true);
	$sql = "SELECT STRAIGHT_JOIN archive.local_data_id, dl.data_template_id,
		UNIX_TIMESTAMP(archive.time) AS timestamp, archive.time AS sample_time,
		archive.rrd_name, archive.output
		FROM `$table` AS archive FORCE INDEX (PRIMARY)
		LEFT JOIN data_local AS dl ON archive.local_data_id = dl.id
		WHERE archive.local_data_id BETWEEN ? AND ?
		ORDER BY archive.local_data_id, archive.time, archive.rrd_name";
	$params = array($range['start'], $range['end']);
	$statement = $pdo->query(boostBenchRenderSql($pdo, $sql, $params));

	while ($statement->fetch(PDO::FETCH_ASSOC) !== false) {
		if ($first_row_seconds === null) {
			$first_row_seconds = microtime(true) - $started;
		}

		$rows++;
	}

	$statement->closeCursor();
	$query_durations[] = microtime(true) - $query_started;

	return array(
		'pid'                       => getmypid(),
		'range_start'               => $range['start'],
		'range_end'                 => $range['end'],
		'expected_rows'             => $range['expected_rows'],
		'rows'                      => $rows,
		'pages'                     => 1,
		'seconds'                   => microtime(true) - $started,
		'time_to_first_row_seconds' => $first_row_seconds,
		'page_seconds'              => $query_durations,
		'peak_php_bytes'            => memory_get_peak_usage(true),
	);
}

/**
 * Start one range worker and return its process and socket handles.
 *
 * @param string $table Archive table.
 * @param array{start: int, end: int, expected_rows: int} $range Source range.
 * @return array{pid: int, socket: resource, range: array<string, int>} Worker state.
 */
function boostBenchStartWorker($table, array $range): array {
	$sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

	if ($sockets === false) {
		throw new RuntimeException('Unable to create worker result socket.');
	}

	$pid = pcntl_fork();

	if ($pid === -1) {
		throw new RuntimeException('Unable to fork benchmark worker.');
	}

	if ($pid === 0) {
		fclose($sockets[0]);

		try {
			$result = boostBenchDrainRange($table, $range);
			fwrite($sockets[1], json_encode($result, JSON_UNESCAPED_SLASHES));
			fclose($sockets[1]);
			exit(0);
		} catch (Throwable $e) {
			fwrite($sockets[1], json_encode(array('error' => $e->getMessage())));
			fclose($sockets[1]);
			exit(1);
		}
	}

	fclose($sockets[1]);
	stream_set_blocking($sockets[0], true);

	return array('pid' => $pid, 'socket' => $sockets[0], 'range' => $range);
}

/**
 * Collect completed workers and remove them from the active set.
 *
 * @param array<int, array<string, mixed>> &$active Active workers keyed by PID.
 * @param bool $block Wait for one worker when true.
 *
 * @return array<int, array<string, mixed>> Completed results.
 */
function boostBenchCollectWorkers(array &$active, $block): array {
	$results = array();
	$options = $block ? 0 : WNOHANG;
	$wait_pid = pcntl_waitpid(-1, $status, $options);

	if ($wait_pid <= 0) {
		return $results;
	}

	$worker = $active[$wait_pid];
	$payload = stream_get_contents($worker['socket']);
	fclose($worker['socket']);
	unset($active[$wait_pid]);
	$result = json_decode($payload, true);

	if (!is_array($result) || isset($result['error']) || !pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
		$error = is_array($result) ? ($result['error'] ?? 'worker exited unsuccessfully') : 'invalid worker result';
		throw new RuntimeException($error);
	}

	$results[] = $result;

	while (($wait_pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
		$worker = $active[$wait_pid];
		$payload = stream_get_contents($worker['socket']);
		fclose($worker['socket']);
		unset($active[$wait_pid]);
		$result = json_decode($payload, true);

		if (!is_array($result) || isset($result['error']) || !pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
			$error = is_array($result) ? ($result['error'] ?? 'worker exited unsuccessfully') : 'invalid worker result';
			throw new RuntimeException($error);
		}

		$results[] = $result;
	}

	return $results;
}

/**
 * Insert deterministic live samples until the parent requests a stop.
 *
 * @param resource $socket Full-duplex parent control socket.
 * @param int $rate Target rows per second.
 * @param int $sources Number of source IDs.
 *
 * @return never
 */
function boostBenchRunProducer($socket, $rate, $sources): void {
	$pdo = boostBenchPdo(true, false);
	stream_set_blocking($socket, false);
	$rate = max(1, (int) $rate);
	$sources = max(1, (int) $sources);
	$batch_size = min(1000, $rate);
	$inserted = 0;
	$sequence = 0;
	$started = microtime(true);

	while (true) {
		if (stream_get_contents($socket) !== '') {
			break;
		}

		$values = array();

		for ($index = 0; $index < $batch_size; $index++) {
			$local_data_id = ($sequence % $sources) + 1;
			$sample_time = 1900000000 + intdiv($sequence, $sources);
			$output = ($sequence * 48271) % 1000000;
			$values[] = sprintf('(%d,\'value\',FROM_UNIXTIME(%d),\'%d\')',
				$local_data_id,
				$sample_time,
				$output);
			$sequence++;
		}

		$pdo->exec('INSERT IGNORE INTO poller_output_boost
			(local_data_id, rrd_name, time, output) VALUES ' . implode(',', $values));
		$inserted += $batch_size;
		$target_elapsed = $inserted / $rate;
		$sleep_seconds = $target_elapsed - (microtime(true) - $started);

		if ($sleep_seconds > 0) {
			usleep((int) min(500000, $sleep_seconds * 1000000));
		}
	}

	$result = array(
		'rows_attempted' => $inserted,
		'rows_persisted' => (int) $pdo->query('SELECT COUNT(*) FROM poller_output_boost')->fetchColumn(),
		'seconds'        => microtime(true) - $started,
	);
	stream_set_blocking($socket, true);
	fwrite($socket, json_encode($result, JSON_UNESCAPED_SLASHES));
	fclose($socket);
	exit(0);
}

$options = getopt('', array(
	'mode:',
	'target-rows::',
	'page-size::',
	'workers::',
	'max-workers::',
	'target-p95-ms::',
	'archives::',
	'incoming-rate::',
));
$mode = $options['mode'] ?? '';
$target_rows = max(1, (int) ($options['target-rows'] ?? ($options['page-size'] ?? 50000)));
$workers = max(1, min(32, (int) ($options['workers'] ?? 1)));
$max_workers = max(1, min(32, (int) ($options['max-workers'] ?? 8)));
$target_p95 = max(0.001, (float) ($options['target-p95-ms'] ?? 250) / 1000);
$archive_limit = max(1, (int) ($options['archives'] ?? 1));
$incoming_rate = max(0, (int) ($options['incoming-rate'] ?? 0));

if (!in_array($mode, array('serial', 'parallel', 'adaptive'), true)) {
	fwrite(STDERR, "--mode must be serial, parallel, or adaptive\n");
	exit(64);
}

$observer = boostBenchPdo(true, false);
$archives = array_slice(boostBenchArchives($observer), 0, $archive_limit);

if (!count($archives)) {
	fwrite(STDERR, "no Boost archives found\n");
	exit(65);
}

$source_count = (int) $observer->query('SELECT COUNT(*) FROM data_local')->fetchColumn();
$observer->exec('TRUNCATE TABLE poller_output_boost');
$engine = $observer->query('SELECT VERSION()')->fetchColumn();
$observer = null;
$producer = null;

if ($incoming_rate > 0) {
	$sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
	$producer_pid = pcntl_fork();

	if ($producer_pid === 0) {
		fclose($sockets[0]);
		boostBenchRunProducer($sockets[1], $incoming_rate, $source_count);
	}

	fclose($sockets[1]);
	$producer = array('pid' => $producer_pid, 'socket' => $sockets[0]);
}

$observer = boostBenchPdo(true, false);

try {
	$observer->exec('TRUNCATE TABLE performance_schema.events_statements_summary_by_digest');
} catch (Throwable $e) {
	/* Performance Schema may be unavailable; global counters remain available. */
}

$before = boostBenchGlobalStatus($observer);
$last_pressure = $before;
$observer = null;
$started = microtime(true);
$archive_results = array();
$all_page_seconds = array();
$time_to_first_row = null;
$total_rows = 0;
$total_pages = 0;
$total_worker_seconds = 0.0;
$peak_php_bytes = memory_get_peak_usage(true);
$observed_concurrency = 0;

foreach($archives as $table) {
	$observer = boostBenchPdo(true, false);
	$staging_started = microtime(true);
	$ranges = boostBenchRanges($observer, $table, $target_rows);
	$staging_seconds = microtime(true) - $staging_started;
	$observer = null;
	$queue = $ranges;
	$active = array();
	$results = array();
	$current_limit = $mode === 'serial' ? 1 : ($mode === 'parallel' ? $workers : 1);
	$archive_started = microtime(true);

	while (count($queue) || count($active)) {
		while (count($queue) && count($active) < $current_limit) {
			$range = array_shift($queue);
			$worker = boostBenchStartWorker($table, $range);
			$active[$worker['pid']] = $worker;
			$observed_concurrency = max($observed_concurrency, count($active));
		}

		foreach(boostBenchCollectWorkers($active, true) as $result) {
			$results[] = $result;

			if ($mode === 'adaptive') {
				$recent = array_slice(array_merge(...array_column(array_slice($results, -8), 'page_seconds')), -64);
				$p95 = boostBenchPercentile($recent, 0.95);
				$pressure_pdo = boostBenchPdo(true, false);
				$pressure = boostBenchGlobalStatus($pressure_pdo);
				$pressure_pdo = null;
				$waited = ($pressure['Innodb_buffer_pool_wait_free'] ?? 0) > ($last_pressure['Innodb_buffer_pool_wait_free'] ?? 0);
				$last_pressure = $pressure;

				if (($p95 > $target_p95 || $waited) && $current_limit > 1) {
					$current_limit--;
				} elseif ($p95 <= $target_p95 && !$waited && $current_limit < $max_workers) {
					$current_limit++;
				}
			}
		}
	}

	$archive_rows = array_sum(array_column($results, 'rows'));
	$expected_rows = array_sum(array_column($ranges, 'expected_rows'));

	if ($archive_rows !== $expected_rows) {
		throw new RuntimeException("Archive $table returned $archive_rows rows; expected $expected_rows.");
	}

	foreach($results as $result) {
		$total_rows += $result['rows'];
		$total_pages += $result['pages'];
		$total_worker_seconds += $result['seconds'];
		$all_page_seconds = array_merge($all_page_seconds, $result['page_seconds']);
		$peak_php_bytes = max($peak_php_bytes, $result['peak_php_bytes']);

		if ($result['time_to_first_row_seconds'] !== null) {
			$candidate = ($archive_started - $started) + $result['time_to_first_row_seconds'];
			$time_to_first_row = $time_to_first_row === null ? $candidate : min($time_to_first_row, $candidate);
		}
	}

	$archive_results[] = array(
		'table'                => $table,
		'rows'                 => $archive_rows,
		'ranges'               => count($ranges),
		'range_rows_min'       => count($results) ? min(array_column($results, 'rows')) : 0,
		'range_rows_max'       => count($results) ? max(array_column($results, 'rows')) : 0,
		'range_seconds_min'    => count($results) ? min(array_column($results, 'seconds')) : 0.0,
		'range_seconds_max'    => count($results) ? max(array_column($results, 'seconds')) : 0.0,
		'staging_seconds'      => $staging_seconds,
		'drain_seconds'        => microtime(true) - $archive_started,
		'final_worker_limit'   => $current_limit,
	);
}

$finished = microtime(true);
$producer_result = null;

if ($producer !== null) {
	fwrite($producer['socket'], "stop\n");
	stream_set_blocking($producer['socket'], true);
	$producer_result = json_decode(stream_get_contents($producer['socket']), true);
	fclose($producer['socket']);
	pcntl_waitpid($producer['pid'], $producer_status);
}

$observer = boostBenchPdo(true, false);
$after = boostBenchGlobalStatus($observer);
$elapsed = $finished - $started;
$result = array(
	'mode'                        => $mode,
	'engine'                      => $engine,
	'archives_processed'          => count($archives),
	'target_range_rows'           => $target_rows,
	'configured_workers'          => $mode === 'adaptive' ? $max_workers : ($mode === 'parallel' ? $workers : 1),
	'observed_max_concurrency'     => $observed_concurrency,
	'rows'                        => $total_rows,
	'pages'                       => $total_pages,
	'elapsed_seconds'             => $elapsed,
	'aggregate_worker_seconds'    => $total_worker_seconds,
	'time_to_first_row_seconds'   => $time_to_first_row,
	'rows_per_second'             => $total_rows / max(0.000001, $elapsed),
	'page_latency_seconds'        => array(
		'p50' => boostBenchPercentile($all_page_seconds, 0.50),
		'p95' => boostBenchPercentile($all_page_seconds, 0.95),
		'max' => count($all_page_seconds) ? max($all_page_seconds) : 0.0,
	),
	'peak_php_bytes_per_process'  => $peak_php_bytes,
	'global_status_delta'         => boostBenchStatusDelta($before, $after),
	'continued_collection'        => $producer_result,
	'archives'                    => $archive_results,
	'performance_schema'          => boostBenchDigests($observer, 20),
	'statement_history'           => boostBenchStatementHistory($observer, 20),
	'claim_scope'                 => 'synthetic database-only scan; no RRD writes or archive drops',
);

print json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
