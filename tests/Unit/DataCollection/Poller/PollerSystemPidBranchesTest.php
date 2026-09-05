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
 * Branch coverage for issue #7027. register_process_start() and
 * timeout_kill_registered_processes() decide whether to signal a pid read from
 * the processes table. is_system_pid() gates the kill so a tampered pid column
 * cannot take down init/systemd. The database is stubbed here, but posix_kill()
 * is not, so every pid these tests hand to the registry has to be one the
 * kernel will refuse. A pid wider than pid_t is not such a value: posix_kill()
 * narrows it to -1 and signals every process the test runner owns.
 */

if (!defined('POLLER_VERBOSITY_MEDIUM')) {
	define('POLLER_VERBOSITY_MEDIUM', 2);
}

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
		return $GLOBALS['__poller_table_exists'] ?? true;
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = array(), $log = true) {
		$GLOBALS['__poller_writes'][] = array($sql, $params);
		return true;
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = array(), $log = true) {
		return $GLOBALS['__poller_row'] ?? array();
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = array(), $log = true) {
		return $GLOBALS['__poller_procs'] ?? array();
	}
}

require_once __DIR__ . '/../../../../lib/poller.php';

beforeEach(function () {
	$GLOBALS['__poller_log']          = array();
	$GLOBALS['__poller_writes']       = array();
	$GLOBALS['__poller_table_exists'] = true;
	$GLOBALS['__poller_row']          = array();
	$GLOBALS['__poller_procs']        = array();
});

// Above every platform's pid_max but still inside pid_t, so posix_kill() fails
// with ESRCH and can never signal a real process (999999 is reachable where
// pid_max is high).
const POLLER_DEAD_PID = 999999999;

// Wider than signed pid_t on both 32-bit and 64-bit PHP. Keep it as a string
// so a 32-bit runtime does not narrow it before the guard sees it.
const POLLER_WIDE_PID = '4294967295';

test('register_process_start refuses to kill a reserved system pid on timeout', function () {
	$GLOBALS['__poller_row'] = array(
		'pid'               => 1,
		'timeout_exceeded'  => 1720000000,
		'timeout'           => 300,
		'current_timestamp' => 1720000600,
	);

	$result = register_process_start('poller', 'test', 0, 300);

	expect($result)->toBeTrue();
	$joined = implode("\n", $GLOBALS['__poller_log']);
	expect($joined)->toContain('reserved system PID');
	// Must have re-registered (unregister + register both write).
	expect(count($GLOBALS['__poller_writes']))->toBeGreaterThanOrEqual(2);
});

test('register_process_start clears a timed-out pid that is already gone', function () {
	$GLOBALS['__poller_row'] = array(
		'pid'               => POLLER_DEAD_PID,
		'timeout_exceeded'  => 1720000000,
		'timeout'           => 300,
		'current_timestamp' => 1720000600,
	);

	$result = register_process_start('poller', 'test', 0, 300);

	expect($result)->toBeTrue();
	$joined = implode("\n", $GLOBALS['__poller_log']);
	// An ordinary pid, so the guard must not claim it, and it is not running,
	// so nothing may be signalled. The row is still cleared and re-registered.
	expect($joined)->not->toContain('reserved system PID');
	expect($joined)->not->toContain('being killed due to timeout');
	expect(count($GLOBALS['__poller_writes']))->toBeGreaterThanOrEqual(2);
});

test('register_process_start treats a zero pid as a reserved system pid', function () {
	// is_system_pid(0) is true, so a zero pid takes the guarded branch rather
	// than reaching posix_kill().
	$GLOBALS['__poller_row'] = array(
		'pid'               => '0',
		'timeout_exceeded'  => 1720000000,
		'timeout'           => 300,
		'current_timestamp' => 1720000600,
	);

	$result = register_process_start('poller', 'test', 0, 300);

	expect($result)->toBeTrue();
	$joined = implode("\n", $GLOBALS['__poller_log']);
	expect($joined)->toContain('reserved system PID');
});

test('timeout_kill_registered_processes skips a reserved system pid', function () {
	$GLOBALS['__poller_procs'] = array(
		array('pid' => '1', 'tasktype' => 'poller', 'taskname' => 'test', 'taskid' => 0),
	);

	timeout_kill_registered_processes();

	$joined = implode("\n", $GLOBALS['__poller_log']);
	expect($joined)->toContain('reserved system PID');
});

test('timeout_kill_registered_processes reports a stale gone pid', function () {
	$GLOBALS['__poller_procs'] = array(
		array('pid' => POLLER_DEAD_PID, 'tasktype' => 'poller', 'taskname' => 'test', 'taskid' => 0),
	);

	timeout_kill_registered_processes();

	$joined = implode("\n", $GLOBALS['__poller_log']);
	expect($joined)->toContain('did not unregister first');
});

test('is_system_pid refuses a pid wider than pid_t', function () {
	expect(is_system_pid(POLLER_WIDE_PID))->toBeTrue();
	// The bound is pid_t, not an arbitrary ceiling on large pids.
	expect(is_system_pid(2147483647))->toBeFalse();
	expect(is_system_pid(POLLER_DEAD_PID))->toBeFalse();
});

test('cacti_process_still_running refuses a pid wider than pid_t', function () {
	// Without the bound this probes posix_kill(-1, 0), which succeeds and
	// reports a dead registry row as live.
	expect(cacti_process_still_running(POLLER_WIDE_PID))->toBeFalse();
});

test('register_process_start refuses to signal a pid wider than pid_t', function () {
	$GLOBALS['__poller_row'] = array(
		'pid'               => POLLER_WIDE_PID,
		'timeout_exceeded'  => 1720000000,
		'timeout'           => 300,
		'current_timestamp' => 1720000600,
	);

	$result = register_process_start('poller', 'test', 0, 300);

	expect($result)->toBeTrue();
	$joined = implode("\n", $GLOBALS['__poller_log']);
	expect($joined)->toContain('reserved system PID');
	expect($joined)->not->toContain('being killed due to timeout');
});

test('timeout_kill_registered_processes refuses a pid wider than pid_t', function () {
	$GLOBALS['__poller_procs'] = array(
		array('pid' => POLLER_WIDE_PID, 'tasktype' => 'poller', 'taskname' => 'test', 'taskid' => 0),
	);

	timeout_kill_registered_processes();

	$joined = implode("\n", $GLOBALS['__poller_log']);
	expect($joined)->toContain('reserved system PID');
});
