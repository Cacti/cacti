<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * upgrade_boost_process_table() has to add the run_child UNIQUE key to
 * poller_output_boost_processes, and nothing stops cron from starting
 * poller_boost.php while the installer is working.  Rows written before this
 * upgrade carry no run identifier, so the ALTERs default every one of them to
 * ('', 0) and the key cannot build over them; rows written by a child of an
 * in-flight run carry a real run_id and are what the parent counts when it
 * decides whether every child finished.
 *
 * These cases run against a real server because the discriminating behaviour
 * is the engine's: whether the UNIQUE key builds, and which rows survive.
 *
 * Requires a MySQL/MariaDB instance; see BoostArchiveHandoffIntegrationTest.php
 * for the same env vars.  Skips (does not fail) if unreachable.
 */

require_once __DIR__ . '/../Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/install/functions.php';
require_once dirname(__DIR__, 2) . '/install/upgrades/1_3_0.php';

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$dsn  = getenv('CACTI_TEST_MYSQL_DSN')  ?: 'mysql:host=127.0.0.1;port=33061;dbname=cacti_test';
	$user = getenv('CACTI_TEST_MYSQL_USER') ?: 'root';
	$pass = getenv('CACTI_TEST_MYSQL_PASS') ?: 'root';

	try {
		$this->pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_TIMEOUT => 3]);
	} catch (\PDOException $e) {
		$this->pdo = null;
		test()->markTestSkipped('No MySQL/MariaDB test instance reachable at ' . $dsn . ': ' . $e->getMessage());

		return;
	}

	$database_hostname = 'unit-test-host';
	$database_port     = 3306;
	$database_default  = 'cacti_boost_upgrade_test';

	$database_sessions["$database_hostname:$database_port:$database_default"] = $this->pdo;

	// db_install_add_cache() stamps install_updated on every statement.
	$this->pdo->exec('CREATE TABLE IF NOT EXISTS settings (
		name varchar(50) NOT NULL default "",
		value varchar(2048) NOT NULL default "",
		PRIMARY KEY (name))');

	$this->pdo->exec('DROP TABLE IF EXISTS poller_output_boost_processes');
});

afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	if (isset($this->pdo) && $this->pdo instanceof PDO) {
		$this->pdo->exec('DROP TABLE IF EXISTS poller_output_boost_processes');
	}

	// Put the default db_* connection back so the test handle does not answer
	// every later read_config_option() in the run.
	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

function boostProcessIndexColumns(PDO $pdo) : array {
	$rows = $pdo->query("SHOW INDEX FROM poller_output_boost_processes WHERE Key_name = 'run_child'");

	if ($rows === false) {
		return [];
	}

	$columns = [];

	foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$columns[(int) $row['Seq_in_index']] = $row['Column_name'] . ':' . $row['Non_unique'];
	}

	ksort($columns);

	return array_values($columns);
}

test('the 1.2.x table gains the run_child key even though its rows cannot satisfy it', function () {
	if (!isset($this->pdo) || !$this->pdo instanceof PDO) {
		return;
	}

	$this->pdo->exec('CREATE TABLE poller_output_boost_processes (
		sock_int_value bigint(20) unsigned NOT NULL auto_increment,
		status varchar(255) default NULL,
		PRIMARY KEY (sock_int_value)) ENGINE=MEMORY');

	$this->pdo->exec("INSERT INTO poller_output_boost_processes (status) VALUES ('118'), ('92'), ('0')");

	upgrade_boost_process_table();

	expect(boostProcessIndexColumns($this->pdo))->toBe(['run_id:0', 'child_id:0'])
		->and((int) $this->pdo->query('SELECT COUNT(*) FROM poller_output_boost_processes')->fetchColumn())->toBe(0);
});

test('an in-flight run keeps its completion rows while the legacy rows are dropped', function () {
	if (!isset($this->pdo) || !$this->pdo instanceof PDO) {
		return;
	}

	// A collector already carrying the 1.3.0 columns but not yet the key, which
	// is what a develop-to-develop upgrade finds.
	$this->pdo->exec("CREATE TABLE poller_output_boost_processes (
		sock_int_value bigint(20) unsigned NOT NULL auto_increment,
		run_id char(32) NOT NULL default '',
		child_id int(10) unsigned NOT NULL default 0,
		status varchar(255) default NULL,
		PRIMARY KEY (sock_int_value)) ENGINE=MEMORY");

	$run_id = '3f2a91c4d80b47e6a15c9f0e7b32d5a8';

	$this->pdo->exec("INSERT INTO poller_output_boost_processes (run_id, child_id, status) VALUES
		('$run_id', 1, '4210'),
		('$run_id', 2, '3987'),
		('', 0, '77')");

	upgrade_boost_process_table();

	$survivors = $this->pdo->query('SELECT run_id, child_id, status
		FROM poller_output_boost_processes
		ORDER BY child_id')->fetchAll(PDO::FETCH_ASSOC);

	expect($survivors)->toBe([
		['run_id' => $run_id, 'child_id' => 1, 'status' => '4210'],
		['run_id' => $run_id, 'child_id' => 2, 'status' => '3987'],
	]);

	// The parent drains on COUNT(*) for its own run_id, so those two rows are
	// the difference between finishing the run and warning about a crashed
	// child while holding the archive tables.
	$completed = $this->pdo->prepare('SELECT COUNT(*) FROM poller_output_boost_processes WHERE run_id = ?');
	$completed->execute([$run_id]);

	expect((int) $completed->fetchColumn())->toBe(2)
		->and(boostProcessIndexColumns($this->pdo))->toBe(['run_id:0', 'child_id:0']);

	// What the upgrade used to do, on the same rows.
	$this->pdo->exec('TRUNCATE TABLE poller_output_boost_processes');
	$completed->execute([$run_id]);

	expect((int) $completed->fetchColumn())->toBe(0);
});
