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

require_once __DIR__ . '/../../../../lib/poller.php';

/**
 * Exercise database-backed branches in an isolated PHP process. Keeping the
 * doubles there prevents them from shadowing real Cacti functions when Pest
 * collects the complete unit suite in one process.
 *
 * @param array  $row    Row returned to register_process_start().
 * @param array  $procs  Rows returned to timeout_kill_registered_processes().
 * @param string $action Function branch to invoke.
 *
 * @return array{result: mixed, log: array<int, string>, writes: array}
 */
function poller_pid_branch_scenario($row, $procs, $action) {
	$library = dirname(__DIR__, 4) . '/lib/poller.php';
	$invoke  = $action === 'register'
		? '$result = register_process_start("poller", "test", 0, 300);'
		: '$result = timeout_kill_registered_processes();';
	$code    = 'define("POLLER_VERBOSITY_MEDIUM", 2);'
		. '$row = ' . var_export($row, true) . '; $procs = ' . var_export($procs, true) . '; $log = array(); $writes = array();'
		. 'function cacti_sizeof($value) { return is_array($value) ? count($value) : 0; }'
		. 'function cacti_log($message) { global $log; $log[] = $message; return true; }'
		. 'function db_table_exists($table) { return true; }'
		. 'function db_execute_prepared($sql, $params = array()) { global $writes; $writes[] = array($sql, $params); return true; }'
		. 'function db_fetch_row_prepared($sql, $params = array()) { global $row; return $row; }'
		. 'function db_fetch_assoc_prepared($sql, $params = array()) { global $procs; return $procs; }'
		. 'require ' . var_export($library, true) . ';' . $invoke
		. 'echo json_encode(array("result" => $result, "log" => $log, "writes" => $writes));';
	$pipes   = array();
	$process = proc_open(array(PHP_BINARY, '-r', $code), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

	expect($process)->not->toBeFalse();

	$output = stream_get_contents($pipes[1]);
	$error  = stream_get_contents($pipes[2]);

	fclose($pipes[1]);
	fclose($pipes[2]);

	expect(proc_close($process))->toBe(0, $error);

	return json_decode($output, true);
}

// Above every platform's pid_max but still inside pid_t, so posix_kill() fails
// with ESRCH and can never signal a real process (999999 is reachable where
// pid_max is high).
const POLLER_DEAD_PID = 999999999;

// Wider than signed pid_t on both 32-bit and 64-bit PHP. Keep it as a string
// so a 32-bit runtime does not narrow it before the guard sees it.
const POLLER_WIDE_PID = '4294967295';

test('register_process_start refuses to kill a reserved system pid on timeout', function () {
	$row = array(
		'pid'               => 1,
		'timeout_exceeded'  => 1720000000,
		'timeout'           => 300,
		'current_timestamp' => 1720000600,
	);

	$out = poller_pid_branch_scenario($row, array(), 'register');

	expect($out['result'])->toBeTrue();
	$joined = implode("\n", $out['log']);
	expect($joined)->toContain('reserved system PID');
	expect(count($out['writes']))->toBeGreaterThanOrEqual(2);
});

test('register_process_start clears a timed-out pid that is already gone', function () {
	$row = array(
		'pid'               => POLLER_DEAD_PID,
		'timeout_exceeded'  => 1720000000,
		'timeout'           => 300,
		'current_timestamp' => 1720000600,
	);

	$out = poller_pid_branch_scenario($row, array(), 'register');

	expect($out['result'])->toBeTrue();
	$joined = implode("\n", $out['log']);
	// An ordinary pid, so the guard must not claim it, and it is not running,
	// so nothing may be signalled. The row is still cleared and re-registered.
	expect($joined)->not->toContain('reserved system PID');
	expect($joined)->not->toContain('being killed due to timeout');
	expect(count($out['writes']))->toBeGreaterThanOrEqual(2);
});

test('register_process_start clears a pid value that cannot name a process', function () {
	// is_system_pid(0) is true, so a zero pid takes the guarded branch rather
	// than reaching posix_kill().
	$row = array(
		'pid'               => '0',
		'timeout_exceeded'  => 1720000000,
		'timeout'           => 300,
		'current_timestamp' => 1720000600,
	);

	$out = poller_pid_branch_scenario($row, array(), 'register');

	expect($out['result'])->toBeTrue();
	$joined = implode("\n", $out['log']);
	expect($joined)->toContain('reserved system PID');
	expect(count($out['writes']))->toBeGreaterThanOrEqual(2);
});

test('timeout_kill_registered_processes skips a reserved system pid', function () {
	$procs = array(
		array('pid' => '1', 'tasktype' => 'poller', 'taskname' => 'test', 'taskid' => 0),
	);

	$out = poller_pid_branch_scenario(array(), $procs, 'timeout');

	$joined = implode("\n", $out['log']);
	expect($joined)->toContain('reserved system PID');
	expect(count($out['writes']))->toBe(1);
});

test('timeout_kill_registered_processes reports a stale gone pid', function () {
	$procs = array(
		array('pid' => POLLER_DEAD_PID, 'tasktype' => 'poller', 'taskname' => 'test', 'taskid' => 0),
	);

	$out = poller_pid_branch_scenario(array(), $procs, 'timeout');

	$joined = implode("\n", $out['log']);
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
	$row = array(
		'pid'               => POLLER_WIDE_PID,
		'timeout_exceeded'  => 1720000000,
		'timeout'           => 300,
		'current_timestamp' => 1720000600,
	);

	$out = poller_pid_branch_scenario($row, array(), 'register');

	expect($out['result'])->toBeTrue();
	$joined = implode("\n", $out['log']);
	expect($joined)->toContain('reserved system PID');
	expect($joined)->not->toContain('being killed due to timeout');
	expect(count($out['writes']))->toBeGreaterThanOrEqual(2);
});

test('timeout_kill_registered_processes refuses a pid wider than pid_t', function () {
	$procs = array(
		array('pid' => POLLER_WIDE_PID, 'tasktype' => 'poller', 'taskname' => 'test', 'taskid' => 0),
	);

	$out = poller_pid_branch_scenario(array(), $procs, 'timeout');

	$joined = implode("\n", $out['log']);
	expect($joined)->toContain('reserved system PID');
});
