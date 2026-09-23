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

$root = dirname(__DIR__, 2);

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

function boostMariaDbLoadProductionFunctions($root) {
	if (function_exists('boostMariaDbEnsureProcessTable')) {
		return;
	}

	$source = file_get_contents($root . '/lib/boost.php');
	$start  = strpos($source, 'function boost_process_table_exists_uncached(');
	$end    = strpos($source, "\n/**\n * boost_array_orderby", $start);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$functions = substr($source, $start, $end - $start);
	$functions = str_replace(array(
		'boost_process_table_exists_uncached',
		'boost_process_column_exists_uncached',
		'boost_ensure_process_table',
		'db_fetch_cell_prepared',
		'db_table_exists',
		'db_column_exists',
		'db_index_exists',
		'db_execute',
		'cacti_log',
	), array(
		'boostMariaDbProcessTableExistsUncached',
		'boostMariaDbProcessColumnExistsUncached',
		'boostMariaDbEnsureProcessTable',
		'boostMariaDbFetchCellPrepared',
		'boostMariaDbTableExists',
		'boostMariaDbColumnExists',
		'boostMariaDbIndexExists',
		'boostMariaDbExecute',
		'boostMariaDbLog',
	), $functions);

	eval($functions);
}

beforeEach(function () use ($root) {
	boostMariaDbLoadProductionFunctions($root);
	boostMariaDbReset();
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_processes');
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_local_data_ids');
});

afterEach(function () {
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_processes');
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_local_data_ids');
});

test('run-scoped Boost cursor table executes on MariaDB and advances monotonically', function () {
	$GLOBALS['boost_mariadb_pdo']->exec("CREATE TABLE poller_output_boost_local_data_ids (
		run_id char(32) NOT NULL,
		local_data_id int unsigned NOT NULL default 0,
		process_handler int unsigned NOT NULL default 0,
		cursor_time timestamp NULL default NULL,
		cursor_rrd_name varchar(19) NOT NULL default '',
		PRIMARY KEY (run_id, local_data_id),
		INDEX process_handler(run_id, process_handler)) ENGINE=MEMORY");
	$run_id = str_repeat('a', 32);
	$insert = $GLOBALS['boost_mariadb_pdo']->prepare('INSERT INTO poller_output_boost_local_data_ids
		(run_id, local_data_id, process_handler) VALUES (?, ?, ?)');
	$insert->execute(array($run_id, 42, 3));
	$update = $GLOBALS['boost_mariadb_pdo']->prepare('UPDATE poller_output_boost_local_data_ids
		SET cursor_time = ?, cursor_rrd_name = ?
		WHERE run_id = ? AND local_data_id = ? AND process_handler = ?');
	$update->execute(array('2026-09-05 12:00:00', 'traffic_out', $run_id, 42, 3));
	$row = $GLOBALS['boost_mariadb_pdo']->query('SELECT run_id, local_data_id, process_handler,
		cursor_time, cursor_rrd_name FROM poller_output_boost_local_data_ids')->fetch(PDO::FETCH_ASSOC);

	expect($row['run_id'])->toBe($run_id)
		->and((int) $row['local_data_id'])->toBe(42)
		->and((int) $row['process_handler'])->toBe(3)
		->and($row['cursor_time'])->toBe('2026-09-05 12:00:00')
		->and($row['cursor_rrd_name'])->toBe('traffic_out');
});

test('run-scoped cursor predicate pages archive rows in primary-key order', function () {
	$GLOBALS['boost_mariadb_pdo']->exec("CREATE TABLE poller_output_boost_local_data_ids (
		run_id char(32) NOT NULL,
		local_data_id int unsigned NOT NULL default 0,
		process_handler int unsigned NOT NULL default 0,
		cursor_time timestamp NULL default NULL,
		cursor_rrd_name varchar(19) NOT NULL default '',
		PRIMARY KEY (run_id, local_data_id),
		INDEX process_handler(run_id, process_handler)) ENGINE=MEMORY");
	$GLOBALS['boost_mariadb_pdo']->exec('CREATE TEMPORARY TABLE boost_archive (
		local_data_id int unsigned NOT NULL,
		rrd_name varchar(19) NOT NULL,
		time timestamp NOT NULL,
		output varchar(512) NOT NULL,
		PRIMARY KEY (local_data_id, time, rrd_name))');
	$GLOBALS['boost_mariadb_pdo']->exec('CREATE TEMPORARY TABLE data_local (
		id int unsigned NOT NULL PRIMARY KEY,
		data_template_id int unsigned NOT NULL)');
	$run_id = str_repeat('b', 32);
	$statement = $GLOBALS['boost_mariadb_pdo']->prepare('INSERT INTO poller_output_boost_local_data_ids
		(run_id, local_data_id, process_handler) VALUES (?, 42, 3)');
	$statement->execute(array($run_id));
	$GLOBALS['boost_mariadb_pdo']->exec('INSERT INTO data_local VALUES (42, 7)');
	$GLOBALS['boost_mariadb_pdo']->exec("INSERT INTO boost_archive VALUES
		(42, 'a', '2026-09-05 12:00:00', '1'),
		(42, 'b', '2026-09-05 12:00:00', '2'),
		(42, 'a', '2026-09-05 12:01:00', '3'),
		(42, 'b', '2026-09-05 12:01:00', '4')");
	$query = 'SELECT * FROM (
		SELECT boost_archive.local_data_id, dl.data_template_id,
			UNIX_TIMESTAMP(boost_archive.time) AS timestamp,
			boost_archive.time AS sample_time, boost_archive.rrd_name, boost_archive.output
		FROM boost_archive
		INNER JOIN poller_output_boost_local_data_ids AS bpt
		ON boost_archive.local_data_id = bpt.local_data_id
		INNER JOIN data_local AS dl ON boost_archive.local_data_id = dl.id
		WHERE bpt.run_id = ? AND bpt.process_handler = ?
		AND (bpt.cursor_time IS NULL OR boost_archive.time > bpt.cursor_time
			OR (boost_archive.time = bpt.cursor_time AND boost_archive.rrd_name > bpt.cursor_rrd_name))
		) AS page ORDER BY local_data_id, sample_time, rrd_name LIMIT 3';
	$statement = $GLOBALS['boost_mariadb_pdo']->prepare($query);
	$statement->execute(array($run_id, 3));
	$first_page = $statement->fetchAll(PDO::FETCH_ASSOC);

	expect($first_page)->toHaveCount(3)
		->and(array_column($first_page, 'rrd_name'))->toBe(array('a', 'b', 'a'));

	$statement = $GLOBALS['boost_mariadb_pdo']->prepare('UPDATE poller_output_boost_local_data_ids
		SET cursor_time = ?, cursor_rrd_name = ? WHERE run_id = ? AND local_data_id = 42');
	$statement->execute(array('2026-09-05 12:00:00', 'b', $run_id));
	$statement = $GLOBALS['boost_mariadb_pdo']->prepare($query);
	$statement->execute(array($run_id, 3));
	$second_page = $statement->fetchAll(PDO::FETCH_ASSOC);

	expect($second_page)->toHaveCount(2)
		->and(array_column($second_page, 'rrd_name'))->toBe(array('a', 'b'));
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
