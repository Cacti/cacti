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
 * Issue #7027 across a real process boundary. timeout_kill_registered_processes()
 * signals a genuinely running pid, so this forks a real sleeper child and checks
 * two outcomes: a normal child pid is delivered SIGTERM, and a reserved system
 * pid (init, pid 1) is never signalled. The DB is stubbed because the guard,
 * not the query, is under test.
 */

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($a) {
		return is_array($a) ? count($a) : 0;
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $stdout = false, $environ = 'CMDPHP', $level = 0) {
		$GLOBALS['__poller_log'][] = $message;
		return true;
	}
}

if (!function_exists('db_table_exists')) {
	function db_table_exists($table, $log = true, $force = false) {
		return true;
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = array(), $log = true) {
		return true;
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = array(), $log = true) {
		return $GLOBALS['__poller_procs'] ?? array();
	}
}

require_once __DIR__ . '/../../lib/poller.php';

beforeEach(function () {
	if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
		$this->markTestSkipped('pcntl/posix extensions required for the process boundary test');
	}

	$GLOBALS['__poller_log']   = array();
	$GLOBALS['__poller_procs'] = array();
});

test('timeout_kill_registered_processes delivers SIGTERM to a live child', function () {
	$pid = pcntl_fork();
	expect($pid)->not->toBe(-1);

	if ($pid === 0) {
		// Child: block until the parent signals us. Short enough that a kill
		// regression fails fast instead of stalling the suite for 30s.
		sleep(5);
		exit(0);
	}

	// Give the child a moment to enter its sleep before we signal it.
	usleep(200000);

	$GLOBALS['__poller_procs'] = array(
		array('pid' => $pid, 'tasktype' => 'poller', 'taskname' => 'integration', 'taskid' => 0),
	);

	timeout_kill_registered_processes();

	$status = 0;
	$waited = pcntl_waitpid($pid, $status);
	expect($waited)->toBe($pid);
	expect(pcntl_wifsignaled($status))->toBeTrue();
	expect(pcntl_wtermsig($status))->toBe(SIGTERM);

	$joined = implode("\n", $GLOBALS['__poller_log']);
	expect($joined)->toContain('killed due to timeout');
});

test('timeout_kill_registered_processes never signals init when the pid column is tampered', function () {
	// pid 1 is always running; without the guard posix_kill(1, SIGTERM) would fire.
	$GLOBALS['__poller_procs'] = array(
		array('pid' => 1, 'tasktype' => 'poller', 'taskname' => 'integration', 'taskid' => 0),
	);

	timeout_kill_registered_processes();

	$joined = implode("\n", $GLOBALS['__poller_log']);
	expect($joined)->toContain('reserved system PID');
	expect($joined)->not->toContain('killed due to timeout');
});
