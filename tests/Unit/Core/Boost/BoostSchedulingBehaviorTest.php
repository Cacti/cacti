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

function boostScheduleTestReset(array $overrides = array()) {
	$GLOBALS['boost_schedule_test_state'] = array_merge(array(
		'config' => array(
			'boost_rrd_update_enable'        => 'on',
			'boost_rrd_update_system_enable' => 'on',
			'boost_rrd_update_interval'      => 60,
			'boost_rrd_update_max_records'   => 1000,
		),
		'rows'    => 0,
		'pollers' => 1,
		'writes'  => array(),
	), $overrides);
}

function boostScheduleTestDebug($message) {
}

function boostScheduleTestRead($name) {
	return $GLOBALS['boost_schedule_test_state']['config'][$name] ?? '';
}

function boostScheduleTestSet($name, $value) {
	$GLOBALS['boost_schedule_test_state']['config'][$name] = $value;
	$GLOBALS['boost_schedule_test_state']['writes'][] = array($name, $value);
}

function boostScheduleTestRows() {
	return $GLOBALS['boost_schedule_test_state']['rows'];
}

function boostScheduleTestFetchCell($sql) {
	return $GLOBALS['boost_schedule_test_state']['pollers'];
}

function boostScheduleTestLoadFunction($root) {
	if (function_exists('boostScheduleTestTimeToRun')) {
		return;
	}

	$source = file_get_contents($root . '/poller_boost.php');
	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function boost_time_to_run(');
	$end   = strpos($source, "\nfunction ", $start + 1);
	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);
	$function = str_replace(array(
		'boost_time_to_run',
		'boost_debug',
		'read_config_option',
		'set_config_option',
		'boost_get_total_rows',
		'db_fetch_cell',
	), array(
		'boostScheduleTestTimeToRun',
		'boostScheduleTestDebug',
		'boostScheduleTestRead',
		'boostScheduleTestSet',
		'boostScheduleTestRows',
		'boostScheduleTestFetchCell',
	), $function);

	eval($function);
}

beforeEach(function () use ($root) {
	boostScheduleTestLoadFunction($root);
	boostScheduleTestReset();
});

test('an enabled Boost run waits until its configured interval is due', function () {
	$current = 200000;
	$last    = $current - 3599;

	expect(boostScheduleTestTimeToRun(false, $current, $last, 0))->toBeFalse();
});

test('an enabled Boost run starts exactly when its configured interval is due', function () {
	$current = 200000;
	$last    = $current - 3600;

	expect(boostScheduleTestTimeToRun(false, $current, $last, 0))->toBeTrue()
		->and($GLOBALS['boost_schedule_test_state']['writes'])->toContain(array('boost_next_run_time', $current));
});

test('the record threshold starts Boost even before the time interval', function () {
	boostScheduleTestReset(array(
		'config' => array(
			'boost_rrd_update_enable'        => 'on',
			'boost_rrd_update_system_enable' => 'on',
			'boost_rrd_update_interval'      => 60,
			'boost_rrd_update_max_records'   => 1000,
		),
		'rows'    => 1001,
		'pollers' => 1,
		'writes'  => array(),
	));

	expect(boostScheduleTestTimeToRun(false, 200000, 199999, 0))->toBeTrue();
});

test('force starts an enabled Boost run regardless of time and row count', function () {
	expect(boostScheduleTestTimeToRun(true, 200000, 199999, 0))->toBeTrue();
});

test('a first enabled run initializes timestamps without immediately running', function () {
	expect(boostScheduleTestTimeToRun(false, 200000, 0, 0))->toBeFalse()
		->and($GLOBALS['boost_schedule_test_state']['writes'])->toContain(array('boost_last_run_time', 200000))
		->and($GLOBALS['boost_schedule_test_state']['writes'])->toContain(array('boost_next_run_time', 203600));
});

test('a missing interval consistently defaults to two hours', function () {
	boostScheduleTestReset(array(
		'config' => array(
			'boost_rrd_update_enable'        => 'on',
			'boost_rrd_update_system_enable' => 'on',
			'boost_rrd_update_interval'      => '',
			'boost_rrd_update_max_records'   => 1000,
		),
		'rows'    => 0,
		'pollers' => 1,
		'writes'  => array(),
	));

	expect(boostScheduleTestTimeToRun(false, 200000, 0, 0))->toBeFalse()
		->and($GLOBALS['boost_schedule_test_state']['writes'])->toContain(array('boost_rrd_update_interval', 120))
		->and($GLOBALS['boost_schedule_test_state']['writes'])->toContain(array('boost_next_run_time', 207200));
});

test('a non-numeric interval consistently defaults to two hours', function () {
	boostScheduleTestReset(array(
		'config' => array(
			'boost_rrd_update_enable'        => 'on',
			'boost_rrd_update_system_enable' => 'on',
			'boost_rrd_update_interval'      => 'invalid',
			'boost_rrd_update_max_records'   => 1000,
		),
		'rows'    => 0,
		'pollers' => 1,
		'writes'  => array(),
	));

	expect(boostScheduleTestTimeToRun(false, 200000, 0, 0))->toBeFalse()
		->and($GLOBALS['boost_schedule_test_state']['writes'])->toContain(array('boost_rrd_update_interval', 120))
		->and($GLOBALS['boost_schedule_test_state']['writes'])->toContain(array('boost_next_run_time', 207200));
});

test('disabled Boost stays idle with an empty queue and disables its system flag', function () {
	boostScheduleTestReset(array(
		'config' => array(
			'boost_rrd_update_enable'        => '',
			'boost_rrd_update_system_enable' => 'on',
			'boost_rrd_update_interval'      => 60,
			'boost_rrd_update_max_records'   => 1000,
		),
		'rows'    => 0,
		'pollers' => 1,
		'writes'  => array(),
	));

	expect(boostScheduleTestTimeToRun(false, 200000, 190000, 0))->toBeFalse()
		->and($GLOBALS['boost_schedule_test_state']['writes'])->toContain(array('boost_rrd_update_system_enable', ''));
});

test('disabled Boost still drains queued rows', function () {
	boostScheduleTestReset(array(
		'config' => array(
			'boost_rrd_update_enable'        => '',
			'boost_rrd_update_system_enable' => '',
			'boost_rrd_update_interval'      => 60,
			'boost_rrd_update_max_records'   => 1000,
		),
		'rows'    => 1,
		'pollers' => 1,
		'writes'  => array(),
	));

	expect(boostScheduleTestTimeToRun(false, 200000, 190000, 0))->toBeTrue();
});

test('multiple collectors keep the Boost system flag enabled', function () {
	boostScheduleTestReset(array(
		'config' => array(
			'boost_rrd_update_enable'        => '',
			'boost_rrd_update_system_enable' => '',
			'boost_rrd_update_interval'      => 60,
			'boost_rrd_update_max_records'   => 1000,
		),
		'rows'    => 0,
		'pollers' => 2,
		'writes'  => array(),
	));

	expect(boostScheduleTestTimeToRun(false, 200000, 190000, 0))->toBeFalse()
		->and($GLOBALS['boost_schedule_test_state']['writes'])->toContain(array('boost_rrd_update_system_enable', 'on'));
});
