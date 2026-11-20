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

namespace BoostPollerDrainFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function boost_prepare_process_table($table) {
	return $GLOBALS['boost_poller_drain']['prepare'];
}

function db_fetch_cell_prepared($sql, $params = array()) {
	$GLOBALS['boost_poller_drain']['queries'][] = array($sql, $params);

	return array_shift($GLOBALS['boost_poller_drain']['cells']);
}

function db_execute($sql) {
	$GLOBALS['boost_poller_drain']['queries'][] = $sql;

	return array_shift($GLOBALS['boost_poller_drain']['execute']);
}

function boost_launch_children() {
	return $GLOBALS['boost_poller_drain']['launched'];
}

function boost_processes_running() {
	return array_shift($GLOBALS['boost_poller_drain']['running']);
}

function boost_completed_children($run_id) {
	return array_shift($GLOBALS['boost_poller_drain']['completed']);
}

function boost_failed_children($run_id) {
	return array_shift($GLOBALS['boost_poller_drain']['failed']);
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['boost_poller_drain']['logs'][] = $message;
}

function boost_debug($message) {
	$GLOBALS['boost_poller_drain']['debug'][] = $message;
}

function sleep($seconds) {
}

function read_config_option($name) {
	return $GLOBALS['boost_poller_drain']['settings'][$name] ?? '';
}

function set_config_option($name, $value) {
	$GLOBALS['boost_poller_drain']['settings'][$name] = $value;
}

function boost_get_arch_table_names() {
	return $GLOBALS['boost_poller_drain']['archives'];
}

function boost_failure_table_exists_uncached() {
	return $GLOBALS['boost_poller_drain']['failure_table'];
}

function db_fetch_cell($sql) {
	$GLOBALS['boost_poller_drain']['queries'][] = $sql;

	return array_shift($GLOBALS['boost_poller_drain']['plain_cells']);
}

function boost_get_total_rows($stop_after = 0) {
	$GLOBALS['boost_poller_drain']['row_limits'][] = $stop_after;

	return $GLOBALS['boost_poller_drain']['total_rows'];
}

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function boostPollerDrainReset() {
	$GLOBALS['boost_poller_drain'] = array(
		'prepare'       => true,
		'cells'         => array(),
		'execute'       => array(),
		'launched'      => 1,
		'running'       => array(),
		'completed'     => array(),
		'failed'        => array(),
		'queries'       => array(),
		'logs'          => array(),
		'debug'         => array(),
		'settings'      => array(),
		'archives'      => array(),
		'failure_table' => false,
		'plain_cells'   => array(),
		'total_rows'    => 0,
		'row_limits'    => array(),
	);
}

function boostPollerDrainLoadFunctions($root) {
	if (function_exists(__NAMESPACE__ . '\\boost_drain_archive')) {
		return;
	}

	foreach(array('boost_drain_archive', 'boost_time_to_run') as $function) {
		\cacti_fixture_source_function($root . '/poller_boost.php', $function, __NAMESPACE__);
	}
}

beforeEach(function () use ($root) {
	boostPollerDrainLoadFunctions($root);
	boostPollerDrainReset();
});

test('archive drain retains work when preparation count or launch fails', function () {
	$GLOBALS['boost_poller_drain']['prepare'] = false;
	expect(boost_drain_archive('poller_output_boost_arch_1'))->toBe(array(
		'complete' => false, 'updates' => 0, 'failed_children' => 0,
	));

	boostPollerDrainReset();
	$GLOBALS['boost_poller_drain']['cells'] = array(false);
	expect(boost_drain_archive('poller_output_boost_arch_1')['complete'])->toBeFalse();

	boostPollerDrainReset();
	$GLOBALS['boost_poller_drain']['cells'] = array(1);
	$GLOBALS['boost_poller_drain']['launched'] = 0;
	expect(boost_drain_archive('poller_output_boost_arch_1')['complete'])->toBeFalse();
});

test('archive drain drops an empty sealed table without launching children', function () {
	$GLOBALS['boost_poller_drain']['cells'] = array(0);
	$GLOBALS['boost_poller_drain']['execute'] = array(true);

	expect(boost_drain_archive('poller_output_boost_arch_1'))->toBe(array(
		'complete' => true, 'updates' => 0, 'failed_children' => 0,
	));
});

test('archive drain retains work when its child barrier cannot be verified', function () {
	$GLOBALS['boost_poller_drain']['cells'] = array(2);
	$GLOBALS['boost_poller_drain']['running'] = array(false);
	$GLOBALS['boost_poller_drain']['completed'] = array(0);

	expect(boost_drain_archive('poller_output_boost_arch_1')['complete'])->toBeFalse();
});

test('archive drain distinguishes incomplete failed active and drop-failure barriers', function () {
	$GLOBALS['boost_poller_drain']['cells'] = array(2, 5);
	$GLOBALS['boost_poller_drain']['running'] = array(0);
	$GLOBALS['boost_poller_drain']['completed'] = array(0, 0);
	$GLOBALS['boost_poller_drain']['failed'] = array(1);
	$result = boost_drain_archive('poller_output_boost_arch_1');
	expect($result)->toBe(array('complete' => false, 'updates' => 5, 'failed_children' => 1));

	boostPollerDrainReset();
	$GLOBALS['boost_poller_drain']['cells'] = array(2, 5);
	$GLOBALS['boost_poller_drain']['running'] = array(0, 1);
	$GLOBALS['boost_poller_drain']['completed'] = array(1, 1);
	$GLOBALS['boost_poller_drain']['failed'] = array(0);
	expect(boost_drain_archive('poller_output_boost_arch_1')['complete'])->toBeFalse();

	boostPollerDrainReset();
	$GLOBALS['boost_poller_drain']['cells'] = array(2, 5);
	$GLOBALS['boost_poller_drain']['running'] = array(0, 0);
	$GLOBALS['boost_poller_drain']['completed'] = array(1, 1);
	$GLOBALS['boost_poller_drain']['failed'] = array(0);
	$GLOBALS['boost_poller_drain']['execute'] = array(false);
	expect(boost_drain_archive('poller_output_boost_arch_1')['complete'])->toBeFalse();
});

test('archive drain drops only after successful completion and a stopped-worker barrier', function () {
	$GLOBALS['boost_poller_drain']['cells'] = array(2, 5);
	$GLOBALS['boost_poller_drain']['running'] = array(0, 0);
	$GLOBALS['boost_poller_drain']['completed'] = array(1, 1);
	$GLOBALS['boost_poller_drain']['failed'] = array(0);
	$GLOBALS['boost_poller_drain']['execute'] = array(true);

	expect(boost_drain_archive('poller_output_boost_arch_1'))->toBe(array(
		'complete' => true, 'updates' => 5, 'failed_children' => 0,
	));
});

test('scheduler recovers retained archives and corrects zero limits', function () {
	$GLOBALS['boost_poller_drain']['settings'] = array(
		'boost_rrd_update_enable'          => 'on',
		'boost_rrd_update_interval'        => 0,
		'boost_rrd_update_max_records'     => 0,
		'boost_rrd_update_system_enable'   => '',
	);
	$GLOBALS['boost_poller_drain']['archives'] = array('poller_output_boost_arch_1');

	expect(boost_time_to_run(false, 2000, 1000, 0))->toBeTrue()
		->and($GLOBALS['boost_poller_drain']['settings']['boost_rrd_update_interval'])->toBe(120)
		->and($GLOBALS['boost_poller_drain']['settings']['boost_rrd_update_max_records'])->toBe('1000000');
});

test('scheduler runs for row pressure and disabled-mode cleanup', function () {
	$GLOBALS['boost_poller_drain']['settings'] = array(
		'boost_rrd_update_enable'        => 'on',
		'boost_rrd_update_interval'      => 1,
		'boost_rrd_update_max_records'   => 10,
		'boost_rrd_update_system_enable' => 'on',
	);
	$GLOBALS['boost_poller_drain']['total_rows'] = 11;
	expect(boost_time_to_run(false, 2000, 1900, 0))->toBeTrue();

	boostPollerDrainReset();
	$GLOBALS['boost_poller_drain']['plain_cells'] = array(1);
	$GLOBALS['boost_poller_drain']['total_rows'] = 1;
	expect(boost_time_to_run(false, 2000, 1900, 0))->toBeTrue()
		->and($GLOBALS['boost_poller_drain']['row_limits'])->toBe(array(1));
});
