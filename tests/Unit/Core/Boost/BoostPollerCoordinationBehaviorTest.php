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

namespace BoostPollerCoordinationFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function db_fetch_assoc_prepared($sql, $params = array()) {
	$GLOBALS['boost_poller_coordination']['queries'][] = array($sql, $params);

	return array_shift($GLOBALS['boost_poller_coordination']['assoc_prepared']);
}

function db_fetch_assoc($sql) {
	$GLOBALS['boost_poller_coordination']['queries'][] = $sql;

	return array_shift($GLOBALS['boost_poller_coordination']['assoc']);
}

function db_fetch_cell_prepared($sql, $params = array()) {
	$GLOBALS['boost_poller_coordination']['queries'][] = array($sql, $params);

	return array_shift($GLOBALS['boost_poller_coordination']['cells']);
}

function db_fetch_row($sql) {
	$GLOBALS['boost_poller_coordination']['queries'][] = $sql;

	return array_shift($GLOBALS['boost_poller_coordination']['rows']);
}

function db_fetch_row_prepared($sql, $params = array()) {
	$GLOBALS['boost_poller_coordination']['queries'][] = array($sql, $params);

	return array_shift($GLOBALS['boost_poller_coordination']['prepared_rows']);
}

function db_execute($sql) {
	$GLOBALS['boost_poller_coordination']['queries'][] = $sql;

	return array_shift($GLOBALS['boost_poller_coordination']['execute']);
}

function db_execute_prepared($sql, $params = array(), $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = array()) {
	$GLOBALS['boost_poller_coordination']['queries'][] = array($sql, $params, $log);

	return array_shift($GLOBALS['boost_poller_coordination']['prepared_execute']);
}

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['boost_poller_coordination']['logs'][] = $message;
}

function unregister_process($tasktype, $taskname, $taskid, $pid) {
	$GLOBALS['boost_poller_coordination']['unregistered'][] = array($tasktype, $taskname, $taskid, $pid);
}

function is_system_pid($pid) {
	return in_array($pid, $GLOBALS['boost_poller_coordination']['system_pids'], true);
}

function boost_child_process_still_running($pid) {
	if (isset($GLOBALS['boost_poller_coordination']['running'][$pid]) &&
		is_array($GLOBALS['boost_poller_coordination']['running'][$pid])) {
		return array_shift($GLOBALS['boost_poller_coordination']['running'][$pid]);
	}

	return $GLOBALS['boost_poller_coordination']['running'][$pid] ?? false;
}

function posix_kill($pid, $signal) {
	return $GLOBALS['boost_poller_coordination']['signals'][$pid] ?? true;
}

function usleep($microseconds) {
}

function read_config_option($name) {
	return $GLOBALS['boost_poller_coordination']['settings'][$name] ?? '';
}

function set_config_option($name, $value) {
	$GLOBALS['boost_poller_coordination']['settings'][$name] = $value;
}

function admin_email($subject, $message) {
	$GLOBALS['boost_poller_coordination']['emails'][] = array($subject, $message);
}

function __($message) {
	return $message;
}

function time() {
	return 2000000000;
}

function boost_dynamic_table_exists_uncached($table) {
	return $GLOBALS['boost_poller_coordination']['dynamic_tables'][$table] ?? false;
}

function boost_is_valid_archive_table($table) {
	return is_string($table) && preg_match('/^poller_output_boost_arch_\d+$/D', $table) === 1;
}

function boost_debug($message) {
	$GLOBALS['boost_poller_coordination']['debug'][] = $message;
}

function boostPollerCoordinationReset() {
	$GLOBALS['boost_poller_coordination'] = array(
		'assoc_prepared'  => array(),
		'assoc'           => array(),
		'cells'           => array(),
		'rows'            => array(),
		'prepared_rows'   => array(),
		'execute'         => array(),
		'prepared_execute'=> array(),
		'queries'         => array(),
		'logs'            => array(),
		'unregistered'    => array(),
		'system_pids'     => array(),
		'running'         => array(),
		'signals'         => array(),
		'settings'        => array(),
		'emails'          => array(),
		'dynamic_tables'  => array(),
		'debug'           => array(),
	);
}

function boostPollerCoordinationLoadFunctions($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_prepare_process_table')) {
		return;
	}

	foreach(array(
		'boost_kill_running_processes',
		'boost_processes_running',
		'boost_completed_children',
		'boost_failed_children',
		'boost_begin_master_run',
		'boost_rotate_output_table',
		'boost_prepare_process_table',
		'boost_launch_children',
	) as $function) {
		\cacti_fixture_source_function($root . '/poller_boost.php', $function, __NAMESPACE__);
	}
}

beforeEach(function () use ($root) {
	boostPollerCoordinationLoadFunctions($root);
	boostPollerCoordinationReset();
	$GLOBALS['run_id'] = str_repeat('a', 32);
	$GLOBALS['start_time'] = 2000000000;
	$GLOBALS['max_run_duration'] = 1200;
	$GLOBALS['archive_table'] = 'poller_output_boost_arch_2000000000';
	$GLOBALS['config'] = array('base_path' => '/cacti');
	$GLOBALS['debug'] = false;
});

test('Boost takeover fails closed on database reserved PID and signal errors', function () {
	$GLOBALS['boost_poller_coordination']['assoc_prepared'] = array(false);
	expect(boost_kill_running_processes())->toBeFalse();

	boostPollerCoordinationReset();
	$GLOBALS['boost_poller_coordination']['assoc_prepared'] = array(array());
	expect(boost_kill_running_processes())->toBeTrue();

	boostPollerCoordinationReset();
	$GLOBALS['boost_poller_coordination']['assoc_prepared'] = array(array(
		array('tasktype' => 'boost', 'taskname' => 'child', 'taskid' => 1, 'pid' => 2),
	));
	$GLOBALS['boost_poller_coordination']['system_pids'] = array(2);
	expect(boost_kill_running_processes())->toBeFalse();

	boostPollerCoordinationReset();
	$GLOBALS['boost_poller_coordination']['assoc_prepared'] = array(array(
		array('tasktype' => 'boost', 'taskname' => 'child', 'taskid' => 1, 'pid' => 10),
	));
	$GLOBALS['boost_poller_coordination']['running'][10] = true;
	$GLOBALS['boost_poller_coordination']['signals'][10] = false;
	expect(boost_kill_running_processes())->toBeFalse();
});

test('Boost takeover removes stale registrations and waits for signalled children', function () {
	$GLOBALS['boost_poller_coordination']['assoc_prepared'] = array(array(
		array('tasktype' => 'boost', 'taskname' => 'child', 'taskid' => 1, 'pid' => 10),
		array('tasktype' => 'boost', 'taskname' => 'child', 'taskid' => 2, 'pid' => 11),
	));
	$GLOBALS['boost_poller_coordination']['running'][10] = false;
	$GLOBALS['boost_poller_coordination']['running'][11] = array(true, false);

	expect(boost_kill_running_processes())->toBeTrue()
		->and($GLOBALS['boost_poller_coordination']['unregistered'])->toHaveCount(2);
});

test('child registration accounting reports database errors and removes stale rows', function () {
	$GLOBALS['boost_poller_coordination']['assoc'] = array(false);
	expect(boost_processes_running())->toBeFalse();

	boostPollerCoordinationReset();
	$GLOBALS['boost_poller_coordination']['assoc'] = array(array(
		array('tasktype' => 'boost', 'taskname' => 'child', 'taskid' => 1, 'pid' => 10),
		array('tasktype' => 'boost', 'taskname' => 'child', 'taskid' => 2, 'pid' => 11),
	));
	$GLOBALS['boost_poller_coordination']['running'] = array(10 => true, 11 => false);

	expect(boost_processes_running())->toBe(1)
		->and($GLOBALS['boost_poller_coordination']['unregistered'])->toHaveCount(1);
});

test('run completion counters preserve database errors and numeric counts', function () {
	$GLOBALS['boost_poller_coordination']['cells'] = array(false, '3', false, '2');

	expect(boost_completed_children('run'))->toBeFalse()
		->and(boost_completed_children('run'))->toBe(3)
		->and(boost_failed_children('run'))->toBeFalse()
		->and(boost_failed_children('run'))->toBe(2);
});

test('master startup reports a stale run and records a numeric lease', function () {
	$GLOBALS['boost_poller_coordination']['settings'] = array(
		'boost_poller_status'  => 'running - start time:2020-01-01 00:00:00',
		'boost_poller_started' => 1,
	);

	boost_begin_master_run();

	expect($GLOBALS['boost_poller_coordination']['emails'])->toHaveCount(1)
		->and($GLOBALS['boost_poller_coordination']['settings']['boost_poller_started'])->toBe(2000000000)
		->and($GLOBALS['boost_poller_coordination']['settings']['boost_poller_status'])->toStartWith('running - start time:');
});

test('atomic rotation distinguishes inspection allocation create rename and success outcomes', function () {
	$GLOBALS['boost_poller_coordination']['rows'] = array(false);
	expect(boost_rotate_output_table())->toBeFalse();

	boostPollerCoordinationReset();
	$GLOBALS['boost_poller_coordination']['rows'] = array(array());
	expect(boost_rotate_output_table())->toBe('');

	boostPollerCoordinationReset();
	$GLOBALS['boost_poller_coordination']['rows'] = array(array('present' => 1));
	for ($attempt = 0; $attempt < 100; $attempt++) {
		$GLOBALS['boost_poller_coordination']['dynamic_tables']['poller_output_boost_arch_' . (2000000000 + $attempt)] = true;
	}
	expect(boost_rotate_output_table())->toBeFalse();

	boostPollerCoordinationReset();
	$GLOBALS['boost_poller_coordination']['rows'] = array(array('present' => 1));
	$GLOBALS['boost_poller_coordination']['execute'] = array(false);
	expect(boost_rotate_output_table())->toBeFalse();

	boostPollerCoordinationReset();
	$GLOBALS['boost_poller_coordination']['rows'] = array(array('present' => 1));
	$GLOBALS['boost_poller_coordination']['execute'] = array(true, false, true);
	expect(boost_rotate_output_table())->toBeFalse();

	boostPollerCoordinationReset();
	$GLOBALS['boost_poller_coordination']['rows'] = array(array('present' => 1));
	$GLOBALS['boost_poller_coordination']['execute'] = array(true, true);
	expect(boost_rotate_output_table())->toBe('poller_output_boost_arch_2000000000');
});

test('range staging fails closed for invalid table schema insert and verification errors', function () {
	expect(boost_prepare_process_table('bad'))->toBeFalse();

	foreach(array(
		array('execute' => array(false)),
		array('execute' => array(true, false)),
		array('execute' => array(true, true), 'prepared_execute' => array(false)),
		array('execute' => array(true, true), 'prepared_execute' => array(true), 'cells' => array(false, 10)),
	) as $scenario) {
		boostPollerCoordinationReset();
		$GLOBALS['boost_poller_coordination'] = array_replace($GLOBALS['boost_poller_coordination'], $scenario);
		$GLOBALS['boost_poller_coordination']['dynamic_tables'][$GLOBALS['archive_table']] = true;

		expect(boost_prepare_process_table($GLOBALS['archive_table']))->toBeFalse();
	}
});

test('range staging accepts empty archives and rejects shared or unverifiable ownership', function () {
	$GLOBALS['boost_poller_coordination']['dynamic_tables'][$GLOBALS['archive_table']] = true;
	$GLOBALS['boost_poller_coordination']['execute'] = array(true, true);
	$GLOBALS['boost_poller_coordination']['prepared_execute'] = array(true);
	$GLOBALS['boost_poller_coordination']['cells'] = array(0, 0);
	expect(boost_prepare_process_table($GLOBALS['archive_table']))->toBeTrue();

	foreach(array(false, array('rrd_path' => '/rra/shared.rrd', 'source_count' => 2)) as $shared) {
		boostPollerCoordinationReset();
		$GLOBALS['boost_poller_coordination']['dynamic_tables'][$GLOBALS['archive_table']] = true;
		$GLOBALS['boost_poller_coordination']['execute'] = array(true, true);
		$GLOBALS['boost_poller_coordination']['prepared_execute'] = array(true);
		$GLOBALS['boost_poller_coordination']['cells'] = array(2, 20);
		$GLOBALS['boost_poller_coordination']['prepared_rows'] = array($shared);

		expect(boost_prepare_process_table($GLOBALS['archive_table']))->toBeFalse();
	}
});

test('range staging verifies range count before reporting success', function () {
	foreach(array(false, 2) as $range_count) {
		boostPollerCoordinationReset();
		$GLOBALS['boost_poller_coordination']['dynamic_tables'][$GLOBALS['archive_table']] = true;
		$GLOBALS['boost_poller_coordination']['execute'] = array(true, true);
		$GLOBALS['boost_poller_coordination']['prepared_execute'] = array(true);
		$GLOBALS['boost_poller_coordination']['cells'] = array(2, 20, $range_count);
		$GLOBALS['boost_poller_coordination']['prepared_rows'] = array(array());

		expect(boost_prepare_process_table($GLOBALS['archive_table']))->toBe($range_count !== false);
	}
});

test('child launch rejects missing ownership empty assignments and invalid ranges', function () {
	$GLOBALS['archive_table'] = 'bad';
	expect(boost_launch_children())->toBe(0);

	$GLOBALS['archive_table'] = 'poller_output_boost_arch_2000000000';
	$GLOBALS['boost_poller_coordination']['assoc_prepared'] = array(false);
	expect(boost_launch_children())->toBe(0);

	$GLOBALS['boost_poller_coordination']['assoc_prepared'] = array(array(
		array('process_handler' => 0, 'range_start' => 1, 'range_end' => 10),
	));
	expect(boost_launch_children())->toBe(0);
});
