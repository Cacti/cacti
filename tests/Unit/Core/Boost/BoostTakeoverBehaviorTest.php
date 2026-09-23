<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 4);

function boostTakeoverReset(array $overrides = array()) {
	$GLOBALS['boost_takeover_state'] = array_merge(array(
		'processes'    => array(),
		'running'      => array(),
		'signals'      => array(),
		'unregistered' => array(),
		'logs'         => array(),
		'now'          => 1000.0,
	), $overrides);
}

function boostTakeoverFetch($sql, $params = array()) {
	return $GLOBALS['boost_takeover_state']['processes'];
}

function boostTakeoverPid() {
	return 9999;
}

function boostTakeoverSizeof($value) {
	return is_countable($value) ? count($value) : 0;
}

function boostTakeoverIsSystemPid($pid) {
	return (int) $pid <= 100;
}

function boostTakeoverStillRunning($pid) {
	$states =& $GLOBALS['boost_takeover_state']['running'][$pid];

	if (is_array($states)) {
		return count($states) ? array_shift($states) : false;
	}

	return (bool) $states;
}

function boostTakeoverUnregister($tasktype, $taskname, $taskid, $pid) {
	$GLOBALS['boost_takeover_state']['unregistered'][] = array($tasktype, $taskname, $taskid, $pid);
}

function boostTakeoverLog($message, $output = false, $facility = '') {
	$GLOBALS['boost_takeover_state']['logs'][] = $message;
}

function boostTakeoverKill($pid, $signal) {
	$GLOBALS['boost_takeover_state']['signals'][] = array($pid, $signal);

	return true;
}

function boostTakeoverTime($as_float = false) {
	$GLOBALS['boost_takeover_state']['now'] += 0.25;

	return $GLOBALS['boost_takeover_state']['now'];
}

function boostTakeoverSleep($microseconds) {
}

function boostTakeoverLoadFunction($root) {
	if (function_exists('boostTakeoverStopProcesses')) {
		return;
	}

	$source = file_get_contents($root . '/poller_boost.php');
	$start  = strpos($source, 'function boost_kill_running_processes(');
	$end    = strpos($source, "\nfunction ", $start + 1);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);
	$function = str_replace(array(
		'boost_kill_running_processes',
		'db_fetch_assoc_prepared',
		'getmypid',
		'cacti_sizeof',
		'is_system_pid',
		'cacti_process_still_running',
		'unregister_process',
		'cacti_log',
		'posix_kill',
		'microtime',
		'usleep',
	), array(
		'boostTakeoverStopProcesses',
		'boostTakeoverFetch',
		'boostTakeoverPid',
		'boostTakeoverSizeof',
		'boostTakeoverIsSystemPid',
		'boostTakeoverStillRunning',
		'boostTakeoverUnregister',
		'boostTakeoverLog',
		'boostTakeoverKill',
		'boostTakeoverTime',
		'boostTakeoverSleep',
	), $function);

	eval($function);
}

beforeEach(function () use ($root) {
	boostTakeoverLoadFunction($root);
	boostTakeoverReset();
});

test('takeover succeeds immediately when no old Boost process is registered', function () {
	expect(boostTakeoverStopProcesses())->toBeTrue()
		->and($GLOBALS['boost_takeover_state']['signals'])->toBe(array());
});

test('takeover removes a stale registration without signaling its recycled PID', function () {
	$process = array('tasktype' => 'boost', 'taskname' => 'child', 'taskid' => 1, 'pid' => 1200);
	boostTakeoverReset(array('processes' => array($process), 'running' => array(1200 => false)));

	expect(boostTakeoverStopProcesses())->toBeTrue()
		->and($GLOBALS['boost_takeover_state']['signals'])->toBe(array())
		->and($GLOBALS['boost_takeover_state']['unregistered'])->toBe(array(array('boost', 'child', 1, 1200)));
});

test('takeover waits for confirmed worker death before unregistering it', function () {
	$process = array('tasktype' => 'boost', 'taskname' => 'child', 'taskid' => 2, 'pid' => 1201);
	boostTakeoverReset(array('processes' => array($process), 'running' => array(1201 => array(true, true, false))));

	expect(boostTakeoverStopProcesses(2))->toBeTrue()
		->and($GLOBALS['boost_takeover_state']['signals'])->toBe(array(array(1201, SIGTERM)))
		->and($GLOBALS['boost_takeover_state']['unregistered'])->toBe(array(array('boost', 'child', 2, 1201)));
});

test('takeover fails closed while a signaled worker remains alive', function () {
	$process = array('tasktype' => 'boost', 'taskname' => 'child', 'taskid' => 3, 'pid' => 1202);
	boostTakeoverReset(array('processes' => array($process), 'running' => array(1202 => true)));

	expect(boostTakeoverStopProcesses(1))->toBeFalse()
		->and($GLOBALS['boost_takeover_state']['signals'])->toBe(array(array(1202, SIGTERM)))
		->and($GLOBALS['boost_takeover_state']['unregistered'])->toBe(array())
		->and(end($GLOBALS['boost_takeover_state']['logs']))->toContain('did not terminate');
});

test('takeover refuses to signal a reserved system PID', function () {
	$process = array('tasktype' => 'boost', 'taskname' => 'child', 'taskid' => 4, 'pid' => 42);
	boostTakeoverReset(array('processes' => array($process)));

	expect(boostTakeoverStopProcesses())->toBeFalse()
		->and($GLOBALS['boost_takeover_state']['signals'])->toBe(array())
		->and($GLOBALS['boost_takeover_state']['unregistered'])->toBe(array());
});
