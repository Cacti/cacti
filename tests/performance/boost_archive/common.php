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

/**
 * Open an unbuffered benchmark connection from BOOST_BENCH_DB_* variables.
 *
 * @param bool $buffered Whether mysqlnd may buffer complete result sets.
 * @param bool $native_prepares Whether PDO should use the server prepared-statement protocol.
 *
 * @return PDO Benchmark database connection.
 */
function boostBenchPdo($buffered = false, $native_prepares = true): PDO {
	$host = getenv('BOOST_BENCH_DB_HOST') ?: '127.0.0.1';
	$port = getenv('BOOST_BENCH_DB_PORT') ?: '3306';
	$socket = getenv('BOOST_BENCH_DB_SOCKET') ?: '';
	$name = getenv('BOOST_BENCH_DB_NAME') ?: 'boostbench';
	$user = getenv('BOOST_BENCH_DB_USER') ?: 'root';
	$pass = getenv('BOOST_BENCH_DB_PASSWORD') ?: '';
	$dsn  = $socket !== ''
		? "mysql:unix_socket=$socket;dbname=$name;charset=utf8mb4"
		: "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

	$options = array(
		PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_EMULATE_PREPARES         => !$native_prepares,
	);
	$buffered_attribute = defined('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY') ?
		constant('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY') :
		constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY');
	$options[$buffered_attribute] = $buffered;

	return new PDO($dsn, $user, $pass, $options);
}

/**
 * Return validated Boost archives in numeric suffix order.
 *
 * @param PDO $pdo Benchmark database connection.
 *
 * @return array<int, string> Archive table names.
 */
function boostBenchArchives(PDO $pdo): array {
	$tables = $pdo->query("SELECT TABLE_NAME
		FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME LIKE 'poller_output_boost_arch_%'")->fetchAll(PDO::FETCH_COLUMN);
	$tables = array_values(array_filter($tables, static fn($table) =>
		preg_match('/^poller_output_boost_arch_\d+$/D', $table) === 1));
	$prefix = 'poller_output_boost_arch_';

	usort($tables, static function($left, $right) use ($prefix) {
		$left  = substr($left, strlen($prefix));
		$right = substr($right, strlen($prefix));

		return strlen($left) === strlen($right) ? strcmp($left, $right) : strlen($left) <=> strlen($right);
	});

	return $tables;
}

/**
 * Read selected session counters as integers.
 *
 * @param PDO $pdo Benchmark database connection.
 *
 * @return array<string, int> Status counters.
 */
function boostBenchSessionStatus(PDO $pdo): array {
	$names = array(
		'Created_tmp_disk_tables',
		'Created_tmp_tables',
		'Handler_read_key',
		'Handler_read_next',
		'Handler_read_rnd_next',
		'Sort_merge_passes',
		'Sort_rows',
		'Sort_scan',
	);
	$quoted = implode(',', array_map(static fn($name) => $pdo->quote($name), $names));
	$rows   = $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN ($quoted)")->fetchAll(PDO::FETCH_KEY_PAIR);

	return array_map('intval', $rows);
}

/**
 * Subtract status snapshots.
 *
 * @param array<string, int> $before Earlier counters.
 * @param array<string, int> $after Later counters.
 *
 * @return array<string, int> Counter deltas.
 */
function boostBenchStatusDelta(array $before, array $after): array {
	$delta = array();

	foreach($after as $name => $value) {
		$delta[$name] = $value - ($before[$name] ?? 0);
	}

	return $delta;
}

/**
 * Read database-wide counters used to detect pressure during a disposable benchmark.
 *
 * The benchmark database must not share a server with production traffic because these counters
 * include every connection on the server.
 *
 * @param PDO $pdo Buffered observer connection.
 *
 * @return array<string, int> Global status counters.
 */
function boostBenchGlobalStatus(PDO $pdo): array {
	$names = array(
		'Created_tmp_disk_tables',
		'Created_tmp_tables',
		'Innodb_buffer_pool_reads',
		'Innodb_buffer_pool_wait_free',
		'Innodb_data_read',
		'Innodb_data_written',
		'Innodb_os_log_written',
		'Threads_running',
	);
	$quoted = implode(',', array_map(static fn($name) => $pdo->quote($name), $names));
	$rows   = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ($quoted)")->fetchAll(PDO::FETCH_KEY_PAIR);

	return array_map('intval', $rows);
}

/**
 * Return a nearest-rank percentile for positive benchmark durations.
 *
 * @param array<int, float> $values Measurements.
 * @param float $percentile Requested percentile in the inclusive range 0 through 1.
 *
 * @return float Percentile value, or zero when no measurements exist.
 */
function boostBenchPercentile(array $values, $percentile): float {
	if (!count($values)) {
		return 0.0;
	}

	sort($values, SORT_NUMERIC);
	$percentile = max(0.0, min(1.0, (float) $percentile));
	$index      = max(0, (int) ceil($percentile * count($values)) - 1);

	return (float) $values[$index];
}

/**
 * Return the most expensive Performance Schema statement digests.
 *
 * @param PDO $pdo Buffered observer connection.
 * @param int $limit Maximum digests.
 *
 * @return array<int, array<string, mixed>> Digest rows, or an empty array when unavailable.
 */
function boostBenchDigests(PDO $pdo, $limit = 10): array {
	try {
		$sql = 'SELECT DIGEST_TEXT, COUNT_STAR,
			ROUND(SUM_TIMER_WAIT / 1000000000000, 6) AS total_seconds,
			SUM_ROWS_EXAMINED, SUM_ROWS_SENT,
			SUM_CREATED_TMP_TABLES, SUM_CREATED_TMP_DISK_TABLES,
			SUM_SORT_ROWS
			FROM performance_schema.events_statements_summary_by_digest
			WHERE SCHEMA_NAME = DATABASE()
			ORDER BY SUM_TIMER_WAIT DESC
			LIMIT ' . max(1, min(50, (int) $limit));

		return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
	} catch (Throwable $e) {
		return array();
	}
}

/**
 * Return individual measured SELECTs from Performance Schema history.
 *
 * @param PDO $pdo Buffered observer connection.
 * @param int $limit Maximum statements.
 *
 * @return array<int, array<string, mixed>> Statement measurements.
 */
function boostBenchStatementHistory(PDO $pdo, $limit = 10): array {
	try {
		$sql = 'SELECT DIGEST, DIGEST_TEXT,
			ROUND(TIMER_WAIT / 1000000000000, 6) AS seconds,
			ROWS_EXAMINED, ROWS_SENT, CREATED_TMP_TABLES, CREATED_TMP_DISK_TABLES,
			SORT_ROWS, SORT_MERGE_PASSES
			FROM performance_schema.events_statements_history_long
			WHERE CURRENT_SCHEMA = DATABASE()
			AND SQL_TEXT IS NOT NULL
			AND (SQL_TEXT LIKE \'SELECT STRAIGHT_JOIN%\' OR SQL_TEXT LIKE \'SELECT * FROM (%\')
			ORDER BY TIMER_START DESC
			LIMIT ' . max(1, min(50, (int) $limit));

		return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
	} catch (Throwable $e) {
		return array();
	}
}

/**
 * Reduce a vendor-specific JSON plan to stable evidence useful in benchmark comparisons.
 *
 * @param array $plan Decoded EXPLAIN FORMAT=JSON output.
 *
 * @return array<string, mixed> Plan summary.
 */
function boostBenchPlanSummary(array $plan): array {
	$summary = array(
		'tables'         => array(),
		'access_types'   => array(),
		'keys'           => array(),
		'estimated_rows' => 0,
		'filesorts'      => 0,
		'union_branches' => 0,
	);
	$walk = function($value) use (&$walk, &$summary) {
		if (!is_array($value)) {
			return;
		}

		if (isset($value['table_name'])) {
			$summary['tables'][] = $value['table_name'];
		}

		if (isset($value['access_type'])) {
			$summary['access_types'][] = $value['access_type'];
		}

		if (isset($value['key'])) {
			$summary['keys'][] = $value['key'];
		}

		if (isset($value['rows']) && is_numeric($value['rows'])) {
			$summary['estimated_rows'] += (int) $value['rows'];
		}

		if (isset($value['filesort'])) {
			$summary['filesorts']++;
		}

		if (($value['operation'] ?? '') === 'UNION') {
			$summary['union_branches']++;
		}

		foreach($value as $child) {
			$walk($child);
		}
	};
	$walk($plan);
	$summary['tables'] = array_values(array_unique($summary['tables']));
	$summary['access_types'] = array_values(array_unique($summary['access_types']));
	$summary['keys'] = array_values(array_unique($summary['keys']));

	return $summary;
}

/**
 * Render trusted benchmark parameters as literals so Performance Schema records the SELECT
 * digest on MariaDB versions that omit server-side prepared executions from digest summaries.
 *
 * @param PDO $pdo Connection used for vendor-correct quoting.
 * @param string $sql SQL containing positional placeholders.
 * @param array $params Trusted scalar benchmark parameters.
 *
 * @return string Rendered SQL.
 */
function boostBenchRenderSql(PDO $pdo, $sql, array $params): string {
	$parts = explode('?', $sql);

	if (count($parts) !== count($params) + 1) {
		throw new RuntimeException('Benchmark placeholder count does not match its parameters.');
	}

	$rendered = array_shift($parts);

	foreach($params as $index => $value) {
		$rendered .= is_int($value) || is_float($value) ? (string) $value : $pdo->quote((string) $value);
		$rendered .= $parts[$index];
	}

	return $rendered;
}
