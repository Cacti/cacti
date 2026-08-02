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
 * Data hand-off proof for #7526: dsstats_wait_for_children()/
 * rrdcheck_wait_for_children() must not treat "no child has registered yet"
 * as "all children are done", the same race Boost's
 * boost_all_children_registered() barrier closes.
 *
 * This test forks a real child process that registers into a shared
 * `processes` table only after a short delay -- reproducing
 * exec_background()'s non-blocking fork -- and asserts the parent's barrier
 * actually observes that registration instead of racing ahead of it. Under
 * the pre-fix bare `while ($running = ...processes_running($type))` pattern
 * this would return in well under the child's registration delay, since
 * $running reads 0 at t=0 and the loop body never executes.
 *
 * Uses a file-backed SQLite database so the forked child and the parent
 * observe the same table; ext-pcntl is required and the test skips (does
 * not fail) if it is unavailable.
 */

require_once __DIR__ . '/../Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/dsstats.php';
require_once dirname(__DIR__, 2) . '/lib/rrdcheck.php';

if (!function_exists('barrier_handoff_connect_default_session')) {
	function barrier_handoff_connect_default_session(PDO $pdo) : void {
		global $database_sessions, $database_hostname, $database_port, $database_default;

		$database_hostname = 'unit-test-host';
		$database_port     = 3306;
		$database_default  = 'cacti_barrier_handoff_test';

		$database_sessions["$database_hostname:$database_port:$database_default"] = $pdo;
	}
}

/*
 * Forks a child that sleeps $register_after seconds, inserts a `processes`
 * row for ($tasktype, $taskname), holds it for $hold_for seconds, then
 * deletes it and exits. Returns the child PID.
 */
if (!function_exists('barrier_handoff_spawn_late_child')) {
	function barrier_handoff_spawn_late_child(string $db_file, string $tasktype, string $taskname, float $register_after, float $hold_for) : int {
		$pid = pcntl_fork();

		if ($pid === -1) {
			throw new RuntimeException('pcntl_fork failed');
		}

		if ($pid === 0) {
			usleep((int) ($register_after * 1_000_000));

			$conn = new PDO('sqlite:' . $db_file);
			$conn->exec('PRAGMA busy_timeout = 5000');
			$conn->exec("INSERT INTO processes (pid, tasktype, taskname, taskid) VALUES (999, '$tasktype', '$taskname', 1)");
			$conn = null;

			usleep((int) ($hold_for * 1_000_000));

			$conn = new PDO('sqlite:' . $db_file);
			$conn->exec('PRAGMA busy_timeout = 5000');
			$conn->exec("DELETE FROM processes WHERE tasktype = '$tasktype' AND taskname = '$taskname' AND taskid = 1");
			$conn = null;

			// A plain exit() here would run every shutdown function and
			// destructor this process inherited from the parent at fork
			// time, including Pest/PHPUnit's own teardown -- which then
			// races the real parent's teardown over shared fds/temp files
			// and surfaces as spurious warnings in the parent's test
			// results. SIGKILL terminates immediately with none of that.
			posix_kill(posix_getpid(), SIGKILL);
			exit(0);
		}

		return $pid;
	}
}

beforeEach(function () {
	if (!function_exists('pcntl_fork')) {
		test()->markTestSkipped('ext-pcntl not available; cannot fork a real late-registering child.');

		return;
	}

	// tempnam() creates an empty base file as a side effect; the real
	// database lives at $this->dbFile . '.sqlite', created fresh by PDO
	// below. Track the base path separately so afterEach can remove it
	// without ever calling unlink() on a path that may not exist --
	// PHPUnit 10+ surfaces E_WARNING from a failed unlink() as a test
	// warning even under the "@" operator.
	$this->dbFileBase = tempnam(sys_get_temp_dir(), 'cacti_barrier_handoff_');
	$this->dbFile      = $this->dbFileBase . '.sqlite';

	$setup = new PDO('sqlite:' . $this->dbFile);
	$setup->exec('PRAGMA busy_timeout = 5000');
	$setup->exec('CREATE TABLE processes (pid INTEGER, tasktype TEXT, taskname TEXT, taskid INTEGER)');
	$setup = null;
});

afterEach(function () {
	if (isset($this->dbFile) && is_file($this->dbFile)) {
		unlink($this->dbFile);
	}

	if (isset($this->dbFileBase) && is_file($this->dbFileBase)) {
		unlink($this->dbFileBase);
	}
});

test('dsstats_wait_for_children waits for a late-registering child instead of returning at time zero', function () {
	if (!isset($this->dbFile)) {
		return;
	}

	$childPid = barrier_handoff_spawn_late_child($this->dbFile, 'dsstats', 'bchild', 0.6, 0.6);

	$pdo = new PDO('sqlite:' . $this->dbFile);
	$pdo->exec('PRAGMA busy_timeout = 5000');
	barrier_handoff_connect_default_session($pdo);

	$start = microtime(true);
	dsstats_wait_for_children('bmaster', 1);
	$elapsed = microtime(true) - $start;

	pcntl_waitpid($childPid, $status);

	// The pre-fix pattern reads $running = 0 immediately (the child hasn't
	// forked/booted/registered yet) and returns in well under 100ms. The
	// barrier must instead still be waiting when the child registers at
	// ~0.6s, and must not resolve before the child clears its row at ~1.2s.
	expect($elapsed)->toBeGreaterThan(1.0);
	expect($elapsed)->toBeLessThan(20.0);

	// No processes row should remain -- the child cleaned up and the parent
	// did not return before that happened.
	expect((int) dsstats_processes_running('bmaster'))->toBe(0);
});

test('rrdcheck_wait_for_children waits for a late-registering child instead of returning at time zero', function () {
	if (!isset($this->dbFile)) {
		return;
	}

	$childPid = barrier_handoff_spawn_late_child($this->dbFile, 'rrdcheck', 'bchild', 0.6, 0.6);

	$pdo = new PDO('sqlite:' . $this->dbFile);
	$pdo->exec('PRAGMA busy_timeout = 5000');
	barrier_handoff_connect_default_session($pdo);

	$start = microtime(true);
	rrdcheck_wait_for_children('bmaster', 1);
	$elapsed = microtime(true) - $start;

	pcntl_waitpid($childPid, $status);

	expect($elapsed)->toBeGreaterThan(1.0);
	expect($elapsed)->toBeLessThan(20.0);

	expect((int) rrdcheck_processes_running('bmaster'))->toBe(0);
});
