<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// the stand-in for a registered Cacti process that is still running when the sweep finds it
if (($argv[1] ?? '') === 'sleep') {
	sleep(30);
	exit;
}

require dirname(__DIR__, 3) . '/Helpers/FixtureCoverage.php';
$root = dirname(__DIR__, 4);
require $root . '/include/global_constants.php';
require $root . '/lib/poller.php';

// a pid that no process can have, so every registered process here is gone
const GONE_PID = 999999999;

/** Record each statement; the fixture tables answer the reads. */
function record($sql, $params = []) {
	$GLOBALS['sql'][] = [preg_replace('/\s+/', ' ', trim($sql)), $params];
}

function db_table_exists($table, $log = true, $db_conn = false) {
	return true;
}

function db_column_exists($table, $column, $log = true, $db_conn = false) {
	return $GLOBALS['has_column'];
}

function db_execute($sql, $log = true, $db_conn = false) {
	record($sql);

	return true;
}

function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
	record($sql, $params);

	return true;
}

function db_fetch_row_prepared($sql, $params = [], $log = true, $db_conn = false) {
	record($sql, $params);

	// the connection is listed only if the server has been up since the row's start
	if (strpos($sql, 'information_schema.PROCESSLIST') !== false) {
		return time() - $params[1] <= strtotime($params[2]) ? $GLOBALS['threads'][$params[0]] ?? [] : [];
	}

	return $GLOBALS['row'];
}

function db_fetch_row($sql, $log = true, $db_conn = false) {
	record($sql);

	return $GLOBALS['uptime'] === null ? [] : ['Variable_name' => 'Uptime', 'Value' => $GLOBALS['uptime']];
}

function db_fetch_assoc_prepared($sql, $params = [], $log = true, $db_conn = false) {
	record($sql, $params);

	return $GLOBALS['rows'];
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['log'][] = $message;
}

function cacti_sizeof($value) {
	return is_array($value) || $value instanceof Countable ? count($value) : 0;
}

/** The statements that ended a connection. */
function kills() {
	return array_values(array_map(fn ($s) => $s[0], array_filter($GLOBALS['sql'], fn ($s) => strpos($s[0], 'KILL') === 0)));
}

function reset_state($row = [], $rows = [], $threads = []) {
	$GLOBALS['sql']     = [];
	$GLOBALS['log']     = [];
	$GLOBALS['row']     = $row;
	$GLOBALS['rows']    = $rows;
	$GLOBALS['threads'] = $threads;
}

$results = [];

// registering records the connection when the column exists, and still works before the upgrade
$GLOBALS['has_column'] = true;
$GLOBALS['uptime']     = 86400;
reset_state();
register_process('boost', 'master', 1, 4242, 300);
$results['register'] = $GLOBALS['sql'][0];

$GLOBALS['has_column'] = false;
reset_state();
register_process('boost', 'master', 1, 4242, 300);
$results['register_old'] = $GLOBALS['sql'][0];
$GLOBALS['has_column']   = true;

// the helper only ends a live connection of this user that is not the caller's own
reset_state();
$results['kill_none'] = [cacti_process_kill_db_connection(['connection_id' => 0, 'started' => '2026-01-01 00:00:00']), $GLOBALS['sql']];

$started = date('Y-m-d H:i:s', time() - 600);

reset_state();
$results['kill_gone'] = [cacti_process_kill_db_connection(['connection_id' => 77, 'started' => $started]), kills(), $GLOBALS['sql'][1][0]];

reset_state([], [], [77 => ['ID' => 77, 'TIME' => 4000]]);
$results['kill_live'] = [cacti_process_kill_db_connection(['connection_id' => 77, 'started' => $started]), kills(), $GLOBALS['log']];

// after a restart the id may name another connection, so it is left alone
$GLOBALS['uptime'] = 60;
reset_state([], [], [77 => ['ID' => 77, 'TIME' => 30]]);
$results['kill_restarted'] = [cacti_process_kill_db_connection(['connection_id' => 77, 'started' => $started]), kills()];

// without the server's uptime there is no telling, so it is left alone too
$GLOBALS['uptime'] = null;
reset_state([], [], [77 => ['ID' => 77, 'TIME' => 30]]);
$results['kill_no_uptime'] = [cacti_process_kill_db_connection(['connection_id' => 77, 'started' => $started]), kills()];
$GLOBALS['uptime']         = 86400;

// a task that timed out after its process died has its connection ended
$stale = ['pid'  => GONE_PID, 'tasktype' => 'boost', 'taskname' => 'master', 'taskid' => 1, 'timeout' => 300,
	'connection_id' => 88, 'started' => $started, 'timeout_exceeded' => 1700000000, 'current_timestamp' => 1700000400];
reset_state($stale, [], [88 => ['ID' => 88, 'TIME' => 400]]);
$results['start_timed_out'] = [register_process_start_locked('boost', 'master', 1, 300), kills()];

// so does a process that exited without unregistering, before its timeout
reset_state(array_merge($stale, ['timeout_exceeded' => 0]), [], [88 => ['ID' => 88, 'TIME' => 30]]);
$results['start_exited'] = [register_process_start_locked('boost', 'master', 1, 300), kills()];

// a row from before the upgrade has no connection to end
$old = $stale;
unset($old['connection_id']);
reset_state($old);
$results['start_old_row'] = [register_process_start_locked('boost', 'master', 1, 300), kills()];

// the timeout sweep ends the connection of a process that is gone
reset_state([], [$stale], [88 => ['ID' => 88, 'TIME' => 400]]);
timeout_kill_registered_processes('boost');
$results['sweep'] = kills();

// and signals a process that is still running before ending its connection
$child = proc_open([PHP_BINARY, __FILE__, 'sleep'], [], $pipes);
$pid   = proc_get_status($child)['pid'];
usleep(200000);
reset_state([], [array_merge($stale, ['pid' => $pid])], [88 => ['ID' => 88, 'TIME' => 400]]);
$started = microtime(true);
timeout_kill_registered_processes('boost');
proc_close($child);
// the child sleeps for 30 seconds unless the sweep signaled it
$results['sweep_running'] = [kills(), $GLOBALS['log'][0] ?? '', microtime(true) - $started < 10];

print json_encode($results) . PHP_EOL;
