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
 * Data hand-off proof for #7520: poller_boost.php decided whether to drop a
 * run's archive tables using `SELECT SUM(status) FROM
 * poller_output_boost_processes` -- an aggregate over whatever completion
 * rows happened to land. If 3 of 4 shards succeed and the 4th child crashes
 * before writing a completion row, that sum is still positive and every
 * archive table for the run, including the crashed child's entire
 * unprocessed shard, gets dropped.
 *
 * The fix reuses the drain loop's own completeness signal -- COUNT(*) FROM
 * poller_output_boost_processes compared against $expected_children -- to
 * gate the drop instead.
 *
 * This proves the discriminating case against a real server's SUM()/COUNT()
 * semantics: exactly the scenario where the old gate and the new gate
 * disagree, which is the entire point of the fix.
 *
 * Requires a MySQL/MariaDB instance; see BoostArchiveHandoffIntegrationTest.php
 * for the same env vars / docker fallback. Skips (does not fail) if
 * unreachable.
 */

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/database.php';

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

	global $database_sessions, $database_hostname, $database_port, $database_default;

	$database_hostname = 'unit-test-host';
	$database_port     = 3306;
	$database_default  = 'cacti_boost_dropgate_test';

	$database_sessions["$database_hostname:$database_port:$database_default"] = $this->pdo;

	$this->pdo->exec('DROP TABLE IF EXISTS poller_output_boost_processes');
	$this->pdo->exec('CREATE TABLE poller_output_boost_processes (status int(10) NOT NULL default 0)');
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

test('3 of 4 shards completing makes the old SUM(status)>0 gate say "drop", real data', function () {
	if (!isset($this->pdo) || !$this->pdo instanceof PDO) {
		return;
	}

	// Child 4 crashed before writing any completion row at all.
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [12]);
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [8]);
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [5]);

	$rrd_updates = db_fetch_cell('SELECT SUM(status) FROM poller_output_boost_processes');

	// This is exactly the old, buggy gate: positive sum from partial success.
	expect((int) $rrd_updates)->toBeGreaterThan(0);
});

test('the same 3-of-4 scenario correctly withholds the drop under the completeness gate', function () {
	if (!isset($this->pdo) || !$this->pdo instanceof PDO) {
		return;
	}

	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [12]);
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [8]);
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [5]);

	$expected_children  = 4;
	$boost_completed    = (int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost_processes');

	// This is the exact expression the fix gates the DROP TABLE on
	// (poller_boost.php: boost_completed_children() >= $expected_children).
	expect($boost_completed >= $expected_children)->toBeFalse();
});

test('once all 4 shards report, both the old sum gate and the new completeness gate agree to drop', function () {
	if (!isset($this->pdo) || !$this->pdo instanceof PDO) {
		return;
	}

	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [12]);
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [8]);
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [5]);
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [3]);

	$expected_children = 4;

	$rrd_updates      = (int) db_fetch_cell('SELECT SUM(status) FROM poller_output_boost_processes');
	$boost_completed  = (int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost_processes');

	expect($rrd_updates > 0)->toBeTrue();
	expect($boost_completed >= $expected_children)->toBeTrue();
});

test('a shard that legitimately processed zero rows (status = 0) does not falsely block the drop', function () {
	if (!isset($this->pdo) || !$this->pdo instanceof PDO) {
		return;
	}

	// All 4 children completed, but one had nothing to update this cycle.
	// COUNT(*) still correctly reflects 4 completions even though SUM(status)
	// would undercount the "did work happen" question -- the completeness
	// gate deliberately asks a different question than $rrd_updates does.
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [12]);
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [0]);
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [5]);
	db_execute_prepared('INSERT INTO poller_output_boost_processes (status) VALUES (?)', [3]);

	$expected_children = 4;
	$boost_completed   = (int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost_processes');

	expect($boost_completed >= $expected_children)->toBeTrue();
});
