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

namespace BoostMariaDbFixture;

use PDO;
use PDOException;

$root = dirname(__DIR__, 2);
require_once $root . '/tests/Helpers/SourceSlice.php';

function boostMariaDbReset() {
	$host     = getenv('BOOST_DB_HOST') ?: '127.0.0.1';
	$port     = getenv('BOOST_DB_PORT') ?: '3306';
	$database = getenv('BOOST_DB_NAME') ?: 'cacti_boost_contract';
	$user     = getenv('BOOST_DB_USER') ?: 'root';
	$password = getenv('BOOST_DB_PASSWORD') ?: '';
	$socket   = getenv('BOOST_DB_SOCKET');
	$dsn      = $socket ? "mysql:unix_socket=$socket;dbname=$database;charset=utf8mb4" :
		"mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";

	$GLOBALS['boost_mariadb_pdo'] = new PDO(
		$dsn,
		$user,
		$password,
		array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
	);
	$GLOBALS['boost_mariadb_cache'] = array('tables' => array(), 'columns' => array());
	$GLOBALS['boost_mariadb_logs']  = array();
}

function boostMariaDbFetchCellPrepared($sql, $params = array()) {
	$statement = $GLOBALS['boost_mariadb_pdo']->prepare($sql);
	$statement->execute($params);

	return $statement->fetchColumn();
}

function boostMariaDbFetchAssocPrepared($sql, $params = array()) {
	$statement = $GLOBALS['boost_mariadb_pdo']->prepare($sql);
	$statement->execute($params);

	return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function boostMariaDbValidArchiveTable($table) {
	return is_string($table) && preg_match('/^poller_output_boost_arch_\d+$/D', $table) === 1;
}

function boostMariaDbSizeof($value) {
	return is_countable($value) ? count($value) : 0;
}

function boostMariaDbTableExists($table) {
	$cache =& $GLOBALS['boost_mariadb_cache']['tables'];

	if (!array_key_exists($table, $cache)) {
		$cache[$table] = (bool) boostMariaDbFetchCellPrepared('SELECT COUNT(*)
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = SCHEMA()
			AND TABLE_NAME = ?', array($table));
	}

	return $cache[$table];
}

function boostMariaDbColumnExists($table, $column) {
	$cache =& $GLOBALS['boost_mariadb_cache']['columns'];
	$key = $table . '.' . $column;

	if (!array_key_exists($key, $cache)) {
		$cache[$key] = (bool) boostMariaDbFetchCellPrepared('SELECT COUNT(*)
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = SCHEMA()
			AND TABLE_NAME = ?
			AND COLUMN_NAME = ?', array($table, $column));
	}

	return $cache[$key];
}

function boostMariaDbIndexExists($table, $index) {
	return (bool) boostMariaDbFetchCellPrepared('SELECT COUNT(*)
		FROM information_schema.STATISTICS
		WHERE TABLE_SCHEMA = SCHEMA()
		AND TABLE_NAME = ?
		AND INDEX_NAME = ?', array($table, $index));
}

function boostMariaDbExecute($sql) {
	try {
		$GLOBALS['boost_mariadb_pdo']->exec($sql);

		return true;
	} catch (PDOException $e) {
		return false;
	}
}

function boostMariaDbLog($message, $output = false, $facility = '') {
	$GLOBALS['boost_mariadb_logs'][] = $message;
}

function db_fetch_cell_prepared($sql, $params = array()) {
	return boostMariaDbFetchCellPrepared($sql, $params);
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	return boostMariaDbFetchAssocPrepared($sql, $params);
}

function boost_is_valid_archive_table($table) {
	return boostMariaDbValidArchiveTable($table);
}

function cacti_sizeof($value) {
	return boostMariaDbSizeof($value);
}

function db_table_exists($table) {
	return boostMariaDbTableExists($table);
}

function db_column_exists($table, $column) {
	return boostMariaDbColumnExists($table, $column);
}

function db_index_exists($table, $index) {
	return boostMariaDbIndexExists($table, $index);
}

function db_execute($sql) {
	return boostMariaDbExecute($sql);
}

function cacti_log($message, $output = false, $facility = '') {
	boostMariaDbLog($message, $output, $facility);
}

function boostMariaDbLoadProductionFunctions($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_ensure_process_table')) {
		return;
	}

	foreach(array(
		'boost_process_table_exists_uncached',
		'boost_process_column_exists_uncached',
		'boost_ensure_process_table',
		'boost_dynamic_table_exists_uncached',
		'boost_fetch_archive_range',
	) as $function) {
		$file = $function === 'boost_fetch_archive_range' ?
			$root . '/poller_boost.php' : $root . '/lib/boost.php';
		\cacti_fixture_source_function($file, $function, __NAMESPACE__);
	}
}

function boostMariaDbProcessColumnExistsUncached($column) {
	return boost_process_column_exists_uncached($column);
}

function boostMariaDbEnsureProcessTable($repair_key = false) {
	return boost_ensure_process_table($repair_key);
}

function boostMariaDbDynamicTableExistsUncached($table) {
	return boost_dynamic_table_exists_uncached($table);
}

function boostMariaDbFetchArchiveRange($table, $range_start, $range_end) {
	return boost_fetch_archive_range($table, $range_start, $range_end);
}

beforeEach(function () use ($root) {
	boostMariaDbLoadProductionFunctions($root);
	boostMariaDbReset();
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_processes');
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_local_data_ids');
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_failures');
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_arch_100');
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS data_local');
});

afterEach(function () {
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_processes');
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_local_data_ids');
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_failures');
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_arch_100');
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS data_local');
});

test('one archive range uses one ordered primary-key read without a limit', function () {
	$GLOBALS['boost_mariadb_pdo']->exec('CREATE TABLE poller_output_boost_arch_100 (
		local_data_id int unsigned NOT NULL,
		rrd_name varchar(19) NOT NULL,
		time timestamp NOT NULL,
		output varchar(512) NOT NULL,
		PRIMARY KEY (local_data_id, time, rrd_name)) ENGINE=InnoDB');
	$GLOBALS['boost_mariadb_pdo']->exec('CREATE TABLE data_local (
		id int unsigned NOT NULL PRIMARY KEY,
		data_template_id int unsigned NOT NULL) ENGINE=InnoDB');
	$GLOBALS['boost_mariadb_pdo']->exec('INSERT INTO data_local VALUES (42, 7), (43, 7)');
	$GLOBALS['boost_mariadb_pdo']->exec("INSERT INTO poller_output_boost_arch_100 VALUES
		(42, 'a', '2026-09-05 12:00:00', '1'),
		(42, 'b', '2026-09-05 12:00:00', '2'),
		(42, 'a', '2026-09-05 12:01:00', '3'),
		(43, 'a', '2026-09-05 12:00:00', '4'),
		(44, 'a', '2026-09-05 12:00:00', '5')");
	$range = boostMariaDbFetchArchiveRange(
		'poller_output_boost_arch_100',
		42,
		44
	);

	expect($range)->toHaveCount(5)
		->and(array_map('intval', array_column($range, 'local_data_id')))->toBe(array(42, 42, 42, 43, 44))
		->and(array_column($range, 'rrd_name'))->toBe(array('a', 'b', 'a', 'a', 'a'))
		->and($range[4]['data_template_id'])->toBeNull()
		->and(boostMariaDbFetchArchiveRange('invalid_archive', 42, 44))->toBeFalse();
});

test('a newly created archive bypasses a cached negative existence check', function () {
	expect(boostMariaDbTableExists('poller_output_boost_arch_100'))->toBeFalse();

	$GLOBALS['boost_mariadb_pdo']->exec('CREATE TABLE poller_output_boost_arch_100 (
		local_data_id int unsigned NOT NULL,
		rrd_name varchar(19) NOT NULL,
		time timestamp NOT NULL,
		output varchar(512) NOT NULL,
		PRIMARY KEY (local_data_id, time, rrd_name)) ENGINE=InnoDB');

	expect(boostMariaDbTableExists('poller_output_boost_arch_100'))->toBeFalse()
		->and(boostMariaDbDynamicTableExistsUncached('poller_output_boost_arch_100'))->toBeTrue()
		->and(boostMariaDbDynamicTableExistsUncached('poller_output_boost_100'))->toBeFalse()
		->and(boostMariaDbDynamicTableExistsUncached('poller_output_boost_arch_100;DROP TABLE settings'))->toBeFalse();
});

test('row-balanced staging assigns every source once within one sealed archive', function () {
	$GLOBALS['boost_mariadb_pdo']->exec('CREATE TABLE poller_output_boost_arch_100 (
		local_data_id int unsigned NOT NULL,
		rrd_name varchar(19) NOT NULL,
		time timestamp NOT NULL,
		output varchar(512) NOT NULL,
		PRIMARY KEY (local_data_id, time, rrd_name)) ENGINE=InnoDB');
	$GLOBALS['boost_mariadb_pdo']->exec("INSERT INTO poller_output_boost_arch_100 VALUES
		(1, 'a', '2026-09-05 12:00:00', '1'),
		(1, 'a', '2026-09-05 12:01:00', '2'),
		(1, 'a', '2026-09-05 12:02:00', '3'),
		(2, 'a', '2026-09-05 12:00:00', '4'),
		(3, 'a', '2026-09-05 12:00:00', '5'),
		(4, 'a', '2026-09-05 12:00:00', '6')");
	$GLOBALS['boost_mariadb_pdo']->exec("CREATE TABLE poller_output_boost_local_data_ids (
		run_id char(32) NOT NULL,
		local_data_id int unsigned NOT NULL,
		process_handler int unsigned NOT NULL,
		range_handler bigint unsigned NOT NULL,
		archive_rows bigint unsigned NOT NULL,
		PRIMARY KEY (run_id, local_data_id),
		INDEX process_handler(run_id, process_handler, range_handler, local_data_id)) ENGINE=InnoDB");
	$run_id = str_repeat('c', 32);
	$sql = 'INSERT INTO poller_output_boost_local_data_ids
		(run_id, local_data_id, process_handler, range_handler, archive_rows)
		SELECT ?, local_data_id,
			LEAST(?, FLOOR((cumulative_rows - source_rows) * ? / total_rows) + 1),
			FLOOR((cumulative_rows - source_rows) / ?) + 1, source_rows
		FROM (SELECT local_data_id, source_rows,
			SUM(source_rows) OVER (ORDER BY local_data_id ROWS UNBOUNDED PRECEDING) AS cumulative_rows,
			SUM(source_rows) OVER () AS total_rows
			FROM (SELECT local_data_id, COUNT(*) AS source_rows
				FROM poller_output_boost_arch_100 GROUP BY local_data_id) AS source_counts) AS assignments';
	$statement = $GLOBALS['boost_mariadb_pdo']->prepare($sql);
	$statement->execute(array($run_id, 2, 2, 3));
	$rows = $GLOBALS['boost_mariadb_pdo']->query('SELECT local_data_id, process_handler, range_handler, archive_rows
		FROM poller_output_boost_local_data_ids ORDER BY local_data_id')->fetchAll(PDO::FETCH_ASSOC);

	expect($rows)->toHaveCount(4)
		->and(array_sum(array_map(fn($row) => (int) $row['archive_rows'], $rows)))->toBe(6)
		->and(array_column($rows, 'process_handler'))->toBe(array(1, 2, 2, 2))
		->and(array_column($rows, 'range_handler'))->toBe(array(1, 2, 2, 2));
});

test('archive lifecycle locks exclude on-demand ownership and release cleanly', function () {
	$pdo1 = $GLOBALS['boost_mariadb_pdo'];
	$host = getenv('BOOST_DB_HOST') ?: '127.0.0.1';
	$port = getenv('BOOST_DB_PORT') ?: '3306';
	$name = getenv('BOOST_DB_NAME') ?: 'cacti_boost_contract';
	$user = getenv('BOOST_DB_USER') ?: 'root';
	$pass = getenv('BOOST_DB_PASSWORD') ?: '';
	$pdo2 = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass,
		array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

	expect((int) $pdo1->query("SELECT GET_LOCK('boost.archive.lifecycle', 0)")->fetchColumn())->toBe(1)
		->and((int) $pdo2->query("SELECT GET_LOCK('boost.archive.lifecycle', 0)")->fetchColumn())->toBe(0)
		->and((int) $pdo1->query("SELECT RELEASE_LOCK('boost.archive.lifecycle')")->fetchColumn())->toBe(1)
		->and((int) $pdo2->query("SELECT GET_LOCK('boost.archive.lifecycle', 0)")->fetchColumn())->toBe(1)
		->and((int) $pdo2->query("SELECT RELEASE_LOCK('boost.archive.lifecycle')")->fetchColumn())->toBe(1);
});

test('bounded failure queue DDL and parameterized retry backoff execute on MariaDB', function () {
	$GLOBALS['boost_mariadb_pdo']->exec("CREATE TABLE poller_output_boost_failures (
		local_data_id int unsigned NOT NULL,
		generation char(32) NOT NULL default '',
		rrd_path varchar(255) NOT NULL default '',
		rrd_template text NOT NULL,
		update_values mediumtext NOT NULL,
		latest_sample bigint unsigned NOT NULL default 0,
		sample_count int unsigned NOT NULL default 0,
		retry_count smallint unsigned NOT NULL default 0,
		first_failed timestamp NOT NULL default CURRENT_TIMESTAMP,
		last_failed timestamp NOT NULL default CURRENT_TIMESTAMP,
		next_retry timestamp NOT NULL default CURRENT_TIMESTAMP,
		last_error varchar(512) NOT NULL default '',
		PRIMARY KEY (local_data_id), KEY next_retry (next_retry)) ENGINE=InnoDB");
	$GLOBALS['boost_mariadb_pdo']->exec("INSERT INTO poller_output_boost_failures
		(local_data_id, generation, rrd_template, update_values)
		VALUES (7, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'value', '1:2')");
	$statement = $GLOBALS['boost_mariadb_pdo']->prepare('UPDATE poller_output_boost_failures
		SET retry_count = ?, next_retry = DATE_ADD(NOW(), INTERVAL ? SECOND), last_error = ?
		WHERE local_data_id = ? AND generation = ?');
	$statement->execute(array(1, 60, 'transient', 7, str_repeat('a', 32)));
	$row = $GLOBALS['boost_mariadb_pdo']->query('SELECT retry_count,
		TIMESTAMPDIFF(SECOND, NOW(), next_retry) AS delay_seconds
		FROM poller_output_boost_failures WHERE local_data_id = 7')->fetch(PDO::FETCH_ASSOC);

	expect((int) $row['retry_count'])->toBe(1)
		->and((int) $row['delay_seconds'])->toBeGreaterThanOrEqual(58)
		->and((int) $row['delay_seconds'])->toBeLessThanOrEqual(60);
});

test('runtime repair executes valid process-table DDL on MariaDB', function () {
	$GLOBALS['boost_mariadb_pdo']->exec('CREATE TABLE poller_output_boost_processes (
		sock_int_value bigint unsigned NOT NULL AUTO_INCREMENT,
		status varchar(255) DEFAULT NULL,
		PRIMARY KEY (sock_int_value)) ENGINE=MEMORY');

	expect(boostMariaDbEnsureProcessTable(true))->toBeTrue()
		->and(boostMariaDbProcessColumnExistsUncached('run_id'))->toBeTrue()
		->and(boostMariaDbProcessColumnExistsUncached('child_id'))->toBeTrue()
		->and(boostMariaDbIndexExists('poller_output_boost_processes', 'run_child'))->toBeTrue()
		->and($GLOBALS['boost_mariadb_logs'])->toBe(array());
});

test('uncached recheck accepts a concurrent column repair after cached absence', function () {
	$GLOBALS['boost_mariadb_pdo']->exec('CREATE TABLE poller_output_boost_processes (
		sock_int_value bigint unsigned NOT NULL AUTO_INCREMENT,
		status varchar(255) DEFAULT NULL,
		PRIMARY KEY (sock_int_value)) ENGINE=MEMORY');

	expect(boostMariaDbTableExists('poller_output_boost_processes'))->toBeTrue()
		->and(boostMariaDbColumnExists('poller_output_boost_processes', 'run_id'))->toBeFalse();

	$GLOBALS['boost_mariadb_pdo']->exec("ALTER TABLE poller_output_boost_processes
		ADD run_id char(32) NOT NULL DEFAULT '' AFTER sock_int_value");

	expect(boostMariaDbEnsureProcessTable(true))->toBeTrue()
		->and(boostMariaDbProcessColumnExistsUncached('run_id'))->toBeTrue()
		->and(boostMariaDbProcessColumnExistsUncached('child_id'))->toBeTrue()
		->and(boostMariaDbIndexExists('poller_output_boost_processes', 'run_child'))->toBeTrue()
		->and($GLOBALS['boost_mariadb_logs'])->toBe(array());
});

test('runtime repair clears duplicate legacy rows before adding the run-child key', function () {
	$GLOBALS['boost_mariadb_pdo']->exec('CREATE TABLE poller_output_boost_processes (
		sock_int_value bigint unsigned NOT NULL AUTO_INCREMENT,
		run_id char(32) NOT NULL DEFAULT \'\',
		child_id int unsigned NOT NULL DEFAULT 0,
		status varchar(255) DEFAULT NULL,
		PRIMARY KEY (sock_int_value)) ENGINE=MEMORY');
	$GLOBALS['boost_mariadb_pdo']->exec("INSERT INTO poller_output_boost_processes
		(run_id, child_id, status) VALUES ('', 0, '1'), ('', 0, '2')");

	expect(boostMariaDbEnsureProcessTable(true))->toBeTrue()
		->and(boostMariaDbIndexExists('poller_output_boost_processes', 'run_child'))->toBeTrue()
		->and((int) boostMariaDbFetchCellPrepared('SELECT COUNT(*) FROM poller_output_boost_processes'))->toBe(0)
		->and($GLOBALS['boost_mariadb_logs'])->toBe(array());
});
