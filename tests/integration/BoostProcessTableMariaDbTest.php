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
});

afterEach(function () {
	$GLOBALS['boost_mariadb_pdo']->exec('DROP TABLE IF EXISTS poller_output_boost_processes');
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
