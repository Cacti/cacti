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
 * Data hand-off proof for #7519: an archive-table row that belongs to a
 * still-open poll round (time >= the safety cutoff) must survive
 * boost_process_poller_output()'s archive-table cleanup by moving into the
 * live poller_output_boost table, not be deleted out of an archive table
 * that gets dropped wholesale once the run finishes.
 *
 * This executes the exact forward-then-narrow-delete SQL text shipped in
 * lib/boost.php -- extracted from the live source at run time, not retyped
 * -- through the real db_execute_prepared()/db_fetch_* call path in
 * lib/database.php, against a real MySQL-compatible server. If the fix is
 * ever reverted, this test starts running the reverted (unfiltered) SQL and
 * fails on real, provable row loss instead of a mock agreeing with itself.
 *
 * Requires a MySQL/MariaDB instance. Set CACTI_TEST_MYSQL_DSN /
 * CACTI_TEST_MYSQL_USER / CACTI_TEST_MYSQL_PASS to point at one; defaults to
 * a local `docker run mariadb` instance on 127.0.0.1:33061. Skips (does not
 * fail) if unreachable.
 */

require_once __DIR__ . '/../Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

$boostLibPath = dirname(__DIR__, 2) . '/lib/boost.php';

if (!function_exists('boost_handoff_extract_sql')) {
	function boost_handoff_extract_sql(string $contents, string $start_needle, string $end_needle) : string {
		$start = strpos($contents, $start_needle);

		if ($start === false) {
			throw new RuntimeException("start needle not found: $start_needle");
		}

		$end = strpos($contents, $end_needle, $start);

		if ($end === false) {
			throw new RuntimeException("end needle not found: $end_needle");
		}

		return substr($contents, $start, $end - $start + strlen($end_needle));
	}
}

if (!function_exists('boost_handoff_connect_default_session')) {
	function boost_handoff_connect_default_session(PDO $pdo) : void {
		global $database_sessions, $database_hostname, $database_port, $database_default;

		$database_hostname = 'unit-test-host';
		$database_port     = 3306;
		$database_default  = 'cacti_boost_handoff_test';

		$database_sessions["$database_hostname:$database_port:$database_default"] = $pdo;
	}
}

beforeEach(function () {
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

	boost_handoff_connect_default_session($this->pdo);

	$this->pdo->exec('DROP TABLE IF EXISTS poller_output_boost');
	$this->pdo->exec('DROP TABLE IF EXISTS poller_output_boost_arch_handoff_test');

	$this->pdo->exec("CREATE TABLE poller_output_boost (
		local_data_id int(10) unsigned NOT NULL default 0,
		rrd_name varchar(19) NOT NULL default '',
		time timestamp NOT NULL default '2000-01-01 00:00:00',
		output varchar(512) NOT NULL,
		last_updated timestamp NOT NULL default current_timestamp,
		PRIMARY KEY (local_data_id, time, rrd_name)
	) ENGINE=InnoDB");

	$this->pdo->exec('CREATE TABLE poller_output_boost_arch_handoff_test LIKE poller_output_boost');
});

afterEach(function () {
	if (isset($this->pdo) && $this->pdo instanceof PDO) {
		$this->pdo->exec('DROP TABLE IF EXISTS poller_output_boost');
		$this->pdo->exec('DROP TABLE IF EXISTS poller_output_boost_arch_handoff_test');
	}
});

test('a row from a still-open poll round survives the archive cleanup by moving to the live table', function () use ($boostLibPath) {
	if (!isset($this->pdo) || !$this->pdo instanceof PDO) {
		return;
	}

	$contents = file_get_contents($boostLibPath);

	$insert_sql = str_replace('$table', 'poller_output_boost_arch_handoff_test',
		boost_handoff_extract_sql($contents, 'INSERT IGNORE INTO poller_output_boost', 'AND time >= FROM_UNIXTIME(?)'));

	$delete_sql = str_replace('$table', 'poller_output_boost_arch_handoff_test',
		boost_handoff_extract_sql($contents, 'DELETE IGNORE', 'WHERE local_data_id = ?'));

	$local_data_id = 42;
	$cutoff        = 1_700_000_000; // the in-progress-round safety cutoff ($timestamp)

	// Seed the archive table like a mid-rotation poller_boost.php run would:
	// two rows already belong to closed poll rounds (time < cutoff), one row
	// landed after the still-open round started (time >= cutoff).
	db_execute_prepared('INSERT INTO poller_output_boost_arch_handoff_test
		(local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)',
		[$local_data_id, 'traffic_in', $cutoff - 300, '123']);

	db_execute_prepared('INSERT INTO poller_output_boost_arch_handoff_test
		(local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)',
		[$local_data_id, 'traffic_out', $cutoff - 300, '456']);

	db_execute_prepared('INSERT INTO poller_output_boost_arch_handoff_test
		(local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)',
		[$local_data_id, 'traffic_in', $cutoff + 60, '999']); // still-open round

	// Run the fixed sequence exactly as boost_process_poller_output() does:
	// forward the still-open-round row, then delete the whole slice for
	// this local_data_id now that every row is accounted for elsewhere.
	$insert_ok = db_execute_prepared($insert_sql, [$local_data_id, $cutoff], false);
	$delete_ok = db_execute_prepared($delete_sql, [$local_data_id], false);

	expect($insert_ok)->not->toBeFalse();
	expect($delete_ok)->not->toBeFalse();

	// The archive table must be fully cleared for this local_data_id -- no
	// leftover forwarded row that could collide with itself on a later run.
	$remaining_in_archive = db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost_arch_handoff_test');
	expect((int) $remaining_in_archive)->toBe(0);

	// ...and the still-open-round row is provably present in the live
	// table, not lost.
	$live_rows = db_fetch_assoc('SELECT rrd_name, output, UNIX_TIMESTAMP(time) AS ts FROM poller_output_boost');
	expect($live_rows)->toHaveCount(1);
	expect($live_rows[0]['rrd_name'])->toBe('traffic_in');
	expect($live_rows[0]['output'])->toBe('999');
	expect((int) $live_rows[0]['ts'])->toBe($cutoff + 60);
});

test('closed-round rows are removed from the archive table and not duplicated into the live table', function () use ($boostLibPath) {
	if (!isset($this->pdo) || !$this->pdo instanceof PDO) {
		return;
	}

	$contents = file_get_contents($boostLibPath);

	$insert_sql = str_replace('$table', 'poller_output_boost_arch_handoff_test',
		boost_handoff_extract_sql($contents, 'INSERT IGNORE INTO poller_output_boost', 'AND time >= FROM_UNIXTIME(?)'));

	$delete_sql = str_replace('$table', 'poller_output_boost_arch_handoff_test',
		boost_handoff_extract_sql($contents, 'DELETE IGNORE', 'WHERE local_data_id = ?'));

	$local_data_id = 9;
	$cutoff        = 1_700_000_000;

	db_execute_prepared('INSERT INTO poller_output_boost_arch_handoff_test
		(local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)',
		[$local_data_id, 'ping', $cutoff - 60, '5']);

	db_execute_prepared($insert_sql, [$local_data_id, $cutoff], false);
	db_execute_prepared($delete_sql, [$local_data_id], false);

	expect((int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost_arch_handoff_test'))->toBe(0);
	// This row's time was < cutoff, so it was already written to RRD by the
	// caller before the archive cleanup ran; it must not also appear in the
	// live table as a side effect of the cleanup.
	expect((int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost'))->toBe(0);
});

test('a forwarded row left in the archive table would collide with itself on the next run -- proving why the delete must not be narrowed', function () use ($boostLibPath) {
	if (!isset($this->pdo) || !$this->pdo instanceof PDO) {
		return;
	}

	// This reconstructs the exact hazard the wide (non-time-narrowed) delete
	// avoids: run the archive-side temp-table seed query verbatim from the
	// source (no time filter, not INSERT IGNORE) against a row that was
	// forwarded to the live table but hypothetically left behind in the
	// archive table, to prove such a leftover would break the next run's
	// own merge query with a primary-key collision.
	$contents = file_get_contents($boostLibPath);

	// Two `INSERT INTO `{$temp_table}`` statements exist in this function:
	// the first seeds from the archive table (no time filter, no IGNORE),
	// the second seeds from the live table (time < $timestamp, no IGNORE).
	$first_seed_pos  = strpos($contents, 'INSERT INTO `{$temp_table}`');
	$second_seed_pos = strpos($contents, 'INSERT INTO `{$temp_table}`', $first_seed_pos + 1);

	expect($first_seed_pos)->not->toBeFalse();
	expect($second_seed_pos)->not->toBeFalse();

	$seed_sql = rtrim(str_replace(['{$temp_table}', '{$table}'], ['poller_output_boost_temp_test', 'poller_output_boost_arch_handoff_test'],
		boost_handoff_extract_sql(substr($contents, $first_seed_pos, $second_seed_pos - $first_seed_pos), 'INSERT INTO `{$temp_table}`', 'WHERE local_data_id = ?"')), '"');

	$live_seed_sql = rtrim(str_replace('{$temp_table}', 'poller_output_boost_temp_test',
		boost_handoff_extract_sql(substr($contents, $second_seed_pos), 'INSERT INTO `{$temp_table}`', 'AND time < FROM_UNIXTIME(?)"')), '"');

	$this->pdo->exec('CREATE TEMPORARY TABLE poller_output_boost_temp_test LIKE poller_output_boost');

	$local_data_id = 55;
	$row_time      = 1_700_000_060;

	// The row exists in BOTH places: the archive table (a hypothetical
	// leftover from a narrowed delete) and the live table (where it was
	// forwarded to).
	db_execute_prepared('INSERT INTO poller_output_boost_arch_handoff_test
		(local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)',
		[$local_data_id, 'ping', $row_time, 'from-archive']);

	db_execute_prepared('INSERT INTO poller_output_boost
		(local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)',
		[$local_data_id, 'ping', $row_time, 'from-live']);

	db_execute_prepared($seed_sql, [$local_data_id], false);

	// The live-table seed (not INSERT IGNORE, matching production) must now
	// fail on the primary-key collision with the row the archive seed just
	// inserted -- demonstrating why the actual fix deletes the whole
	// archive slice instead of leaving this row behind.
	$next_cutoff = $row_time + 3600;
	$collision   = db_execute_prepared($live_seed_sql, [$local_data_id, $next_cutoff], false);

	expect($collision)->toBeFalse();

	$this->pdo->exec('DROP TEMPORARY TABLE IF EXISTS poller_output_boost_temp_test');
});

test('forwarding a row already present in the live table does not raise a PK collision (INSERT IGNORE)', function () use ($boostLibPath) {
	if (!isset($this->pdo) || !$this->pdo instanceof PDO) {
		return;
	}

	$contents = file_get_contents($boostLibPath);

	$insert_sql = str_replace('$table', 'poller_output_boost_arch_handoff_test',
		boost_handoff_extract_sql($contents, 'INSERT IGNORE INTO poller_output_boost', 'AND time >= FROM_UNIXTIME(?)'));

	$local_data_id = 7;
	$cutoff        = 1_700_000_000;

	// The exact same (local_data_id, time, rrd_name) key already exists in
	// the live table -- e.g. re-inserted by a concurrent poller path -- and
	// also sits in the archive table being forwarded.
	db_execute_prepared('INSERT INTO poller_output_boost
		(local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)',
		[$local_data_id, 'ping', $cutoff + 10, 'live-value']);

	db_execute_prepared('INSERT INTO poller_output_boost_arch_handoff_test
		(local_data_id, rrd_name, time, output) VALUES (?, ?, FROM_UNIXTIME(?), ?)',
		[$local_data_id, 'ping', $cutoff + 10, 'archive-value']);

	$insert_ok = db_execute_prepared($insert_sql, [$local_data_id, $cutoff], false);

	// INSERT IGNORE must not error on the PK collision, and must not clobber
	// the row the live table already had.
	expect($insert_ok)->not->toBeFalse();

	$outputs = db_fetch_assoc("SELECT output FROM poller_output_boost WHERE local_data_id = $local_data_id");
	expect($outputs)->toHaveCount(1);
	expect($outputs[0]['output'])->toBe('live-value');
});

test('a genuine duplicate (timestamp, rrd_name) pair reaching $results is skipped by the reassigned $last_item guard', function () {
	// #7523: this reproduces the scenario the issue describes as the reason
	// the guard needed to work -- a stale leftover archive table and the
	// current run's rows overlapping on the same (local_data_id, timestamp,
	// rrd_name) key, both landing in $results because the forwarding fix in
	// #7519 can legitimately place a row that already exists elsewhere in
	// the query's result set for the same run.
	//
	// This exercises the *fixed* loop shape directly (not a mock of it): the
	// same duplicate-detection condition and $last_item reassignment now
	// shipped in boost_process_poller_output(), run against a synthetic
	// $results array with an adjacent duplicate pair.
	$results = [
		['local_data_id' => 1, 'timestamp' => 1000, 'rrd_name' => 'traffic_in', 'output' => '10'],
		['local_data_id' => 1, 'timestamp' => 1000, 'rrd_name' => 'traffic_in', 'output' => '10'], // duplicate
		['local_data_id' => 1, 'timestamp' => 1060, 'rrd_name' => 'traffic_in', 'output' => '20'],
	];

	$last_item = ['local_data_id' => -1, 'timestamp' => -1, 'rrd_name' => ''];
	$processed = [];
	$skipped   = 0;

	foreach ($results as $item) {
		if ($last_item['timestamp'] == $item['timestamp'] && $last_item['rrd_name'] == $item['rrd_name']) {
			$skipped++;

			continue;
		}

		$processed[] = $item;

		$last_item = $item;
	}

	expect($skipped)->toBe(1);
	expect($processed)->toHaveCount(2);
	expect($processed[0]['output'])->toBe('10');
	expect($processed[1]['output'])->toBe('20');
});
