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

$options = getopt('', array(
	'mode:',
	'page-size::',
	'range-start::',
	'range-end::',
	'cursor-id::',
	'cursor-epoch::',
	'cursor-name::',
	'run-id::',
	'process::',
	'full-plan',
));
$mode = $options['mode'] ?? '';
$page_size = max(1, (int) ($options['page-size'] ?? 50000));
$range_start = max(1, (int) ($options['range-start'] ?? 1));
$observer = boostBenchPdo(true);
$archives = boostBenchArchives($observer);

if (!count($archives)) {
	fwrite(STDERR, "no Boost archives found\n");
	exit(65);
}

$range_end = max($range_start, (int) ($options['range-end'] ??
	$observer->query('SELECT MAX(id) FROM data_local')->fetchColumn()));
$params = array();

if ($mode === 'baseline') {
	$assignment = $observer->query('SELECT run_id, process_handler
		FROM poller_output_boost_local_data_ids
		ORDER BY process_handler LIMIT 1')->fetch(PDO::FETCH_ASSOC);
	$bench_run_id = $options['run-id'] ?? ($assignment['run_id'] ?? str_repeat('b', 32));
	$bench_process = max(1, (int) ($options['process'] ?? ($assignment['process_handler'] ?? 1)));
	$branches = array();

	foreach($archives as $table) {
		$branches[] = "SELECT `$table`.local_data_id, dl.data_template_id,
			UNIX_TIMESTAMP(`$table`.time) AS timestamp, `$table`.time AS sample_time,
			`$table`.rrd_name, `$table`.output
			FROM `$table`
			INNER JOIN poller_output_boost_local_data_ids AS bpt
			ON `$table`.local_data_id = bpt.local_data_id
			INNER JOIN data_local AS dl ON `$table`.local_data_id = dl.id
			WHERE bpt.run_id = ? AND bpt.process_handler = ?
			AND (bpt.cursor_time IS NULL OR `$table`.time > bpt.cursor_time
				OR (`$table`.time = bpt.cursor_time AND `$table`.rrd_name > bpt.cursor_rrd_name))";
		$params[] = $bench_run_id;
		$params[] = $bench_process;
	}

	$sql = 'SELECT * FROM (' . implode(' UNION ALL ', $branches) .
		') AS archive_union ORDER BY local_data_id, sample_time, rrd_name LIMIT ' . ($page_size + 1);
} elseif ($mode === 'candidate') {
	$table = $archives[0];
	$cursor_id = max($range_start - 1, (int) ($options['cursor-id'] ?? ($range_start - 1)));
	$cursor_epoch = max(86400, (int) ($options['cursor-epoch'] ?? 86400));
	$cursor_time = gmdate('Y-m-d H:i:s', $cursor_epoch);
	$cursor_name = substr((string) ($options['cursor-name'] ?? ''), 0, 19);
	$columns = "SELECT STRAIGHT_JOIN archive.local_data_id, dl.data_template_id,
		UNIX_TIMESTAMP(archive.time) AS timestamp, archive.time AS sample_time,
		archive.rrd_name, archive.output
		FROM `$table` AS archive FORCE INDEX (PRIMARY)
		LEFT JOIN data_local AS dl ON archive.local_data_id = dl.id ";

	if ($cursor_id >= $range_start && $cursor_id <= $range_end) {
		$candidate_stage = 'current-source remainder';
		$sql = $columns . "WHERE archive.local_data_id = ?
			AND (archive.time > ? OR (archive.time = ? AND archive.rrd_name > ?))
			ORDER BY archive.time, archive.rrd_name
			LIMIT " . ($page_size + 1);
		$params = array($cursor_id, $cursor_time, $cursor_time, $cursor_name);
	} else {
		if ($range_start === $range_end) {
			$candidate_stage = 'single-source start';
			$sql = $columns . "WHERE archive.local_data_id = ?
				ORDER BY archive.time, archive.rrd_name
				LIMIT " . ($page_size + 1);
			$params = array($range_start);
		} else {
			$candidate_stage = 'later-source range';
			$sql = $columns . "WHERE archive.local_data_id BETWEEN ? AND ?
				ORDER BY archive.local_data_id, archive.time, archive.rrd_name
				LIMIT " . ($page_size + 1);
			$params = array($range_start, $range_end);
		}
	}
} else {
	fwrite(STDERR, "--mode must be baseline or candidate\n");
	exit(64);
}

$benchmark_sql = boostBenchRenderSql($observer, $sql, $params);
$plan = json_decode($observer->query('EXPLAIN FORMAT=JSON ' . $benchmark_sql)->fetchColumn(), true);

try {
	$observer->exec('TRUNCATE TABLE performance_schema.events_statements_summary_by_digest');
	$observer->exec("UPDATE performance_schema.setup_consumers
		SET ENABLED = 'YES'
		WHERE NAME IN ('events_statements_history', 'events_statements_history_long')");
	$observer->exec('TRUNCATE TABLE performance_schema.events_statements_history_long');
} catch (Throwable $e) {
	/* Performance Schema may be read-only or disabled; session counters remain available. */
}

$pdo = boostBenchPdo(false, false);
$before = boostBenchSessionStatus($pdo);
$started = microtime(true);
$statement = $pdo->query($benchmark_sql);
$executed = microtime(true);
$rows = 0;
$first_row_seconds = null;

while ($statement->fetch(PDO::FETCH_ASSOC) !== false) {
	$rows++;

	if ($first_row_seconds === null) {
		$first_row_seconds = microtime(true) - $started;
	}
}

$finished = microtime(true);
$statement->closeCursor();
$after = boostBenchSessionStatus($pdo);
unset($statement);
gc_collect_cycles();
$pdo = null;

$result = array(
	'mode'                    => $mode,
	'engine'                  => $observer->query('SELECT VERSION()')->fetchColumn(),
	'archive_count'           => count($archives),
	'oldest_archive'          => $archives[0],
	'page_size'               => $page_size,
	'cursor'                  => $mode === 'candidate' ? array(
		'local_data_id' => $cursor_id,
		'time'          => $cursor_time,
		'rrd_name'      => $cursor_name,
	) : null,
	'candidate_stage'         => $mode === 'candidate' ? $candidate_stage : null,
	'rows_returned'           => $rows,
	'time_to_execute_seconds' => $executed - $started,
	'time_to_first_row_seconds' => $first_row_seconds,
	'total_seconds'           => $finished - $started,
	'peak_php_bytes'          => memory_get_peak_usage(true),
	'session_status_delta'    => boostBenchStatusDelta($before, $after),
	'query_plan_summary'      => boostBenchPlanSummary($plan),
	'performance_schema'      => boostBenchDigests($observer),
	'statement_history'       => boostBenchStatementHistory($observer),
);

if (array_key_exists('full-plan', $options)) {
	$result['query_plan'] = $plan;
}

print json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
