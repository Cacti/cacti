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
 * Regression tests for #7526: dsstats_launch_children()/rrdcheck_launch_children()
 * reimplemented Boost's pre-fix "fork N workers, wait for them" shape:
 *
 *     while ($running = dsstats_processes_running('bmaster')) { sleep(2); }
 *
 * exec_background() is non-blocking. If the parent evaluates this before any
 * child has registered in the `processes` table, $running is 0, the loop body
 * never runs, and the caller treats "not started yet" as "already done" --
 * exactly the race boost_all_children_registered() was added to close in
 * poller_boost.php (see BoostPollerBarrierTest.php).
 *
 * The fix applies the same shape: dsstats_launch_children()/
 * rrdcheck_launch_children() now return the launched count, and a new
 * dsstats_wait_for_children()/rrdcheck_wait_for_children() barrier waits for
 * the running count to reach that expected count (bounded by a deadline)
 * before trusting the drain loop's running-count poll.
 */

require_once __DIR__ . '/../Helpers/UnitStubs.php';
require_once __DIR__ . '/../../lib/dsstats.php';
require_once __DIR__ . '/../../lib/rrdcheck.php';

$dsstatsLibPath  = __DIR__ . '/../../lib/dsstats.php';
$rrdcheckLibPath = __DIR__ . '/../../lib/rrdcheck.php';

if (!function_exists('boost_handoff_extract_function')) {
	function boost_handoff_extract_function(string $contents, string $signature) : string {
		$func_pos = strpos($contents, $signature);

		if ($func_pos === false) {
			return '';
		}

		$func_end = strpos($contents, "\nfunction ", $func_pos + 1);

		if ($func_end === false) {
			$func_end = strlen($contents);
		}

		return substr($contents, $func_pos, $func_end - $func_pos);
	}
}

// ---------------------------------------------------------------------
// Source-inspection: the launch/drain shape matches Boost's barrier fix
// ---------------------------------------------------------------------

test('dsstats_launch_children returns the launched child count', function () use ($dsstatsLibPath) {
	$contents  = file_get_contents($dsstatsLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function dsstats_launch_children(string $type) : int');

	expect($func_body)->not->toBe('');
	expect($func_body)->toContain('return (int) $processes;');
});

test('rrdcheck_launch_children returns the launched child count', function () use ($rrdcheckLibPath) {
	$contents  = file_get_contents($rrdcheckLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function rrdcheck_launch_children(string $type) : int');

	expect($func_body)->not->toBe('');
	expect($func_body)->toContain('return (int) $processes;');
});

test('dsstats_boost_bottom waits on the barrier helper, not a bare running-count poll', function () use ($dsstatsLibPath) {
	$contents  = file_get_contents($dsstatsLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function dsstats_boost_bottom()');

	expect($func_body)->toContain('$expected_children = dsstats_launch_children(\'bmaster\');');
	expect($func_body)->toContain('dsstats_wait_for_children(\'bmaster\', $expected_children);');
	expect($func_body)->not->toMatch('/while\s*\(\s*\$running\s*=\s*dsstats_processes_running\(/');
});

test('rrdcheck_boost_bottom waits on the barrier helper, not a bare running-count poll', function () use ($rrdcheckLibPath) {
	$contents  = file_get_contents($rrdcheckLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function rrdcheck_boost_bottom()');

	expect($func_body)->toContain('$expected_children = rrdcheck_launch_children(\'bmaster\');');
	expect($func_body)->toContain('rrdcheck_wait_for_children(\'bmaster\', $expected_children);');
	expect($func_body)->not->toMatch('/while\s*\(\s*\$running\s*=\s*rrdcheck_processes_running\(/');
});

test('dsstats_wait_for_children implements a startup barrier before the drain loop', function () use ($dsstatsLibPath) {
	$contents  = file_get_contents($dsstatsLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function dsstats_wait_for_children(string $type, int $expected_children) : void');

	expect($func_body)->not->toBe('');

	$barrier_pos = strpos($func_body, '$startup_deadline');
	$drain_pos   = strpos($func_body, 'while ($running = dsstats_processes_running($type))');

	expect($barrier_pos)->not->toBeFalse();
	expect($drain_pos)->not->toBeFalse();
	expect($barrier_pos)->toBeLessThan($drain_pos);

	expect($func_body)->toContain('dsstats_processes_running($type) < $expected_children');
});

test('rrdcheck_wait_for_children implements a startup barrier before the drain loop', function () use ($rrdcheckLibPath) {
	$contents  = file_get_contents($rrdcheckLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function rrdcheck_wait_for_children(string $type, int $expected_children) : void');

	expect($func_body)->not->toBe('');

	$barrier_pos = strpos($func_body, '$startup_deadline');
	$drain_pos   = strpos($func_body, 'while ($running = rrdcheck_processes_running($type))');

	expect($barrier_pos)->not->toBeFalse();
	expect($drain_pos)->not->toBeFalse();
	expect($barrier_pos)->toBeLessThan($drain_pos);

	expect($func_body)->toContain('rrdcheck_processes_running($type) < $expected_children');
});

// ---------------------------------------------------------------------
// Functional: exercise the real functions against a live `processes` table
// ---------------------------------------------------------------------

if (!function_exists('boost_handoff_connect_processes_table')) {
	function boost_handoff_connect_processes_table(PDO $pdo) : void {
		global $database_sessions, $database_hostname, $database_port, $database_default;

		$database_hostname = 'unit-test-host';
		$database_port     = 3306;
		$database_default  = 'cacti_unit_test';

		$database_sessions["$database_hostname:$database_port:$database_default"] = $pdo;
	}
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	require_once __DIR__ . '/../../lib/database.php';

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$this->pdo = new PDO('sqlite::memory:');
	$this->pdo->exec('CREATE TABLE processes (pid INTEGER, tasktype TEXT, taskname TEXT, taskid INTEGER)');

	boost_handoff_connect_processes_table($this->pdo);
});

// Put the default db_* connection back. Left in place, the sqlite handle
// answers every later read_config_option() in the run and throws on Cacti's
// MySQL SQL, aborting the suite.
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

test('dsstats_processes_running counts only matching tasktype/taskname rows', function () {
	$this->pdo->exec("INSERT INTO processes (pid, tasktype, taskname, taskid) VALUES (1, 'dsstats', 'bchild', 1)");
	$this->pdo->exec("INSERT INTO processes (pid, tasktype, taskname, taskid) VALUES (2, 'dsstats', 'bchild', 2)");
	$this->pdo->exec("INSERT INTO processes (pid, tasktype, taskname, taskid) VALUES (3, 'dsstats', 'dchild', 3)");
	$this->pdo->exec("INSERT INTO processes (pid, tasktype, taskname, taskid) VALUES (4, 'boost', 'child', 4)");

	expect(dsstats_processes_running('bmaster'))->toBe(2);
});

test('rrdcheck_processes_running counts only matching tasktype/taskname rows', function () {
	$this->pdo->exec("INSERT INTO processes (pid, tasktype, taskname, taskid) VALUES (1, 'rrdcheck', 'bchild', 1)");
	$this->pdo->exec("INSERT INTO processes (pid, tasktype, taskname, taskid) VALUES (2, 'rrdcheck', 'bchild', 2)");
	$this->pdo->exec("INSERT INTO processes (pid, tasktype, taskname, taskid) VALUES (3, 'rrdcheck', 'child', 3)");

	expect(rrdcheck_processes_running('bmaster'))->toBe(2);
});

test('dsstats_wait_for_children returns immediately with zero expected children', function () {
	$start = microtime(true);

	dsstats_wait_for_children('bmaster', 0);

	expect(microtime(true) - $start)->toBeLessThan(1.0);
});

test('rrdcheck_wait_for_children returns immediately with zero expected children', function () {
	$start = microtime(true);

	rrdcheck_wait_for_children('bmaster', 0);

	expect(microtime(true) - $start)->toBeLessThan(1.0);
});
