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
 * cannot take down init/systemd. The DB and posix_kill() are the only external
 * boundaries; both are stubbed here so every branch of the guarded code runs
 * without a live database and without signalling this process.
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

require_once __DIR__ . '/../../lib/poller.php';

beforeEach(function () {
	$GLOBALS['__poller_log']          = array();
	$GLOBALS['__poller_writes']       = array();
	$GLOBALS['__poller_table_exists'] = true;
	$GLOBALS['__poller_row']          = array();
	$GLOBALS['__poller_procs']        = array();
});

// A pid beyond any platform's pid_max, so posix_kill() fails with ESRCH and
// can never signal a real process (999999 is reachable where pid_max is high).
const POLLER_DEAD_PID = PHP_INT_MAX;

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

test('register_process_start signals a normal timed-out pid', function () {
	$GLOBALS['__poller_row'] = array(
		'pid'               => POLLER_DEAD_PID,
		'timeout_exceeded'  => 1720000000,
		'timeout'           => 300,
		'current_timestamp' => 1720000600,
	);

	$result = register_process_start('poller', 'test', 0, 300);

	expect($result)->toBeTrue();
	$joined = implode("\n", $GLOBALS['__poller_log']);
	expect($joined)->toContain('being killed due to timeout');
});

test('register_process_start treats a zero pid as a reserved system pid', function () {
	// is_system_pid(0) is true, so a zero pid takes the guarded branch rather
	// than reaching posix_kill().
	$GLOBALS['__poller_row'] = array(
		'pid'               => 0,
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
		array('pid' => 1, 'tasktype' => 'poller', 'taskname' => 'test', 'taskid' => 0),
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
