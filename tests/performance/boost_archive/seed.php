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
	'archives::',
	'rows-per-archive::',
	'sources::',
	'distribution::',
	'drop-existing',
));
$archive_count = max(1, (int) ($options['archives'] ?? 40));
$rows_per_archive = max(1, (int) ($options['rows-per-archive'] ?? 8000000));
$source_count = max(1, (int) ($options['sources'] ?? 60000));
$distribution = $options['distribution'] ?? 'uniform';
$drop_existing = array_key_exists('drop-existing', $options);

if (!in_array($distribution, array('uniform', 'skewed', 'single-hot'), true)) {
	fwrite(STDERR, "distribution must be uniform, skewed, or single-hot\n");
	exit(64);
}

if ($distribution === 'single-hot' && $source_count < 2) {
	fwrite(STDERR, "single-hot distribution requires at least two sources\n");
	exit(64);
}

$pdo = boostBenchPdo(true);

try {
	$pdo->exec('SET SESSION sql_log_bin = 0');
} catch (PDOException $e) {
	/* A disposable non-replicating database account does not need BINLOG ADMIN. */
}

$pdo->exec('SET SESSION unique_checks = 0');
$pdo->exec('SET SESSION foreign_key_checks = 0');

if ($drop_existing) {
	foreach(boostBenchArchives($pdo) as $table) {
		$pdo->exec("DROP TABLE `$table`");
	}

	$pdo->exec('DROP TABLE IF EXISTS poller_output_boost_local_data_ids');
	$pdo->exec('DROP TABLE IF EXISTS poller_output_boost');
	$pdo->exec('DROP TABLE IF EXISTS data_local');
	$pdo->exec('DROP TABLE IF EXISTS boost_bench_numbers');
	$pdo->exec('DROP TABLE IF EXISTS boost_bench_digits');
}

$pdo->exec('CREATE TABLE IF NOT EXISTS boost_bench_digits (n tinyint unsigned NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('INSERT IGNORE INTO boost_bench_digits VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)');
$pdo->exec('CREATE TABLE IF NOT EXISTS boost_bench_numbers (n int unsigned NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$number_count = (int) $pdo->query('SELECT COUNT(*) FROM boost_bench_numbers')->fetchColumn();

if ($number_count < max($rows_per_archive, $source_count)) {
	$digits = strlen((string) (max($rows_per_archive, $source_count) - 1));
	$terms  = array();
	$joins  = array();

	for ($position = 0; $position < $digits; $position++) {
		$alias   = 'd' . $position;
		$terms[] = "$alias.n * " . (10 ** $position);
		$joins[] = 'CROSS JOIN boost_bench_digits AS ' . $alias;
	}

	$first = array_shift($joins);
	$sql = 'INSERT IGNORE INTO boost_bench_numbers (n)
		SELECT ' . implode(' + ', $terms) . " AS n
		FROM boost_bench_digits AS d0 " . implode(' ', array_slice($joins, 0)) . '
		WHERE (' . implode(' + ', $terms) . ') < ' . max($rows_per_archive, $source_count);

	/* The first alias is already present in FROM; discard its duplicate cross join. */
	$sql = str_replace('FROM boost_bench_digits AS d0 CROSS JOIN boost_bench_digits AS d0 ',
		'FROM boost_bench_digits AS d0 ', $sql);
	$started = microtime(true);
	$pdo->exec($sql);
	fwrite(STDERR, sprintf("numbers=%d seconds=%.3f\n",
		$pdo->query('SELECT COUNT(*) FROM boost_bench_numbers')->fetchColumn(),
		microtime(true) - $started));
}

$pdo->exec('CREATE TABLE IF NOT EXISTS data_local (
	id int unsigned NOT NULL,
	data_template_id int unsigned NOT NULL default 0,
	PRIMARY KEY (id)) ENGINE=InnoDB');
$pdo->exec('TRUNCATE TABLE data_local');
$pdo->exec("INSERT INTO data_local (id, data_template_id)
	SELECT n + 1, 0 FROM boost_bench_numbers WHERE n < $source_count");
$pdo->exec("CREATE TABLE IF NOT EXISTS poller_output_boost_local_data_ids (
	run_id char(32) NOT NULL,
	local_data_id int unsigned NOT NULL,
	process_handler int unsigned NOT NULL,
	archive_rows bigint unsigned NOT NULL default 0,
	cursor_time timestamp NULL,
	cursor_rrd_name varchar(19) NOT NULL default '',
	PRIMARY KEY (run_id, local_data_id),
	KEY process_handler (run_id, process_handler, local_data_id)) ENGINE=InnoDB");
$pdo->exec('TRUNCATE TABLE poller_output_boost_local_data_ids');
$run_id = str_repeat('b', 32);
$statement = $pdo->prepare('INSERT INTO poller_output_boost_local_data_ids
	(run_id, local_data_id, process_handler)
	SELECT ?, id, 1 FROM data_local');
$statement->execute(array($run_id));
$pdo->exec("CREATE TABLE IF NOT EXISTS poller_output_boost (
	local_data_id int unsigned NOT NULL,
	rrd_name varchar(19) NOT NULL,
	time timestamp NOT NULL,
	output varchar(512) NOT NULL,
	PRIMARY KEY (local_data_id, time, rrd_name)) ENGINE=InnoDB ROW_FORMAT=Dynamic");
$pdo->exec('TRUNCATE TABLE poller_output_boost');

$base_sequence = 1700000000;
$base_timestamp = 1700000000;
$archive_span = 500000;
$hot_sources = max(1, (int) floor($source_count * 0.01));
$cold_sources = max(1, $source_count - $hot_sources);

for ($archive = 1; $archive <= $archive_count; $archive++) {
	$table = 'poller_output_boost_arch_' . ($base_sequence + $archive);

	if (!$drop_existing && in_array($table, boostBenchArchives($pdo), true)) {
		fwrite(STDERR, "reuse=$table\n");
		continue;
	}

	$pdo->exec("DROP TABLE IF EXISTS `$table`");
	$pdo->exec("CREATE TABLE `$table` (
		local_data_id int unsigned NOT NULL,
		rrd_name varchar(19) NOT NULL,
		time timestamp NOT NULL,
		output varchar(512) NOT NULL) ENGINE=InnoDB");
	$archive_base = $base_timestamp + (($archive - 1) * $archive_span);

	if ($distribution === 'uniform') {
		$id_expression = "MOD(MOD(n, $source_count) * 48271 + $archive * 7919, $source_count) + 1";
		$time_expression = "$archive_base + FLOOR(n / $source_count) * 60";
	} elseif ($distribution === 'skewed') {
		$id_expression = "CASE WHEN MOD(n, 2) = 0
			THEN MOD(MOD(FLOOR(n / 2), $hot_sources) * 487 + $archive * 17, $hot_sources) + 1
			ELSE $hot_sources + MOD(MOD(FLOOR(n / 2), $cold_sources) * 48271 + $archive * 7919, $cold_sources) + 1 END";
		$time_expression = "CASE WHEN MOD(n, 2) = 0
			THEN $archive_base + FLOOR(FLOOR(n / 2) / $hot_sources) * 60
			ELSE $archive_base + FLOOR(FLOOR(n / 2) / $cold_sources) * 60 END";
	} else {
		$single_hot_cold_sources = $source_count - 1;
		$id_expression = "CASE WHEN MOD(n, 2) = 0 THEN 1
			ELSE 2 + MOD(MOD(FLOOR(n / 2), $single_hot_cold_sources) * 48271 + $archive * 7919, $single_hot_cold_sources) END";
		$time_expression = "CASE WHEN MOD(n, 2) = 0
			THEN $archive_base + FLOOR(n / 2)
			ELSE $archive_base + FLOOR(FLOOR(n / 2) / $single_hot_cold_sources) * 60 END";
	}

	$started = microtime(true);
	$pdo->exec("INSERT INTO `$table` (local_data_id, rrd_name, time, output)
		SELECT $id_expression, 'value', FROM_UNIXTIME($time_expression),
			CAST(MOD(n * 1103515245 + $archive * 12345, 1000000) / 100 AS CHAR)
		FROM boost_bench_numbers WHERE n < $rows_per_archive");
	$pdo->exec("ALTER TABLE `$table`
		ADD PRIMARY KEY (local_data_id, time, rrd_name), ROW_FORMAT=Dynamic");
	$seconds = microtime(true) - $started;
	fwrite(STDERR, sprintf("archive=%s rows=%d seconds=%.3f rows_per_second=%.1f\n",
		$table,
		$rows_per_archive,
		$seconds,
		$rows_per_archive / max(0.001, $seconds)));
}

$result = array(
	'engine'            => $pdo->query('SELECT VERSION()')->fetchColumn(),
	'archives'          => $archive_count,
	'rows_per_archive'  => $rows_per_archive,
	'total_rows'        => $archive_count * $rows_per_archive,
	'sources'           => $source_count,
	'distribution'      => $distribution,
	'randomization'     => $distribution === 'single-hot' ?
		'deterministic single-source hot set with coprime cold-source permutation per archive' :
		'deterministic coprime permutation per archive',
	'archive_tables'    => boostBenchArchives($pdo),
);

print json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
