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

// UnitStubs MUST be loaded before lib/time.php so srv() and other helpers are
// resolvable when shift_time() is exercised.
require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/time.php';

it('calculates the last hour correctly', function () {
	$now = strtotime('2023-01-01 12:00:00');
	$span = [];
	get_timespan($span, $now, GT_LAST_HOUR, 1);

	expect($span['begin_now'])->toBe(strtotime('2023-01-01 11:00:00'));
	expect($span['end_now'])->toBe($now);
});

it('calculates today correctly', function () {
	$now = strtotime('2023-01-01 12:34:56');
	$span = [];
	get_timespan($span, $now, GT_THIS_DAY, 1);

	expect($span['begin_now'])->toBe(strtotime('2023-01-01 00:00:00'));
	expect($span['end_now'])->toBe(strtotime('2023-01-01 23:59:59'));
});

it('calculates this week correctly (Monday start)', function () {
	// 2023-01-04 is a Wednesday
	$now = strtotime('2023-01-04 12:00:00');
	$span = [];
	get_timespan($span, $now, GT_THIS_WEEK, 1); // 1 = Monday

	expect($span['begin_now'])->toBe(strtotime('2023-01-02 00:00:00')); // Previous Monday
	expect($span['end_now'])->toBe(strtotime('2023-01-08 23:59:59')); // Next Sunday
});

it('detects month boundaries correctly', function () {
	$span = [
		'begin_now' => strtotime('2023-01-01 00:00:00'),
		'end_now'   => strtotime('2023-01-31 23:59:59')
	];

	expect(check_month_boundaries($span))->toBeTrue();
});

it('detects non-month boundaries correctly', function () {
	$span = [
		'begin_now' => strtotime('2023-01-02 00:00:00'),
		'end_now'   => strtotime('2023-01-31 23:59:59')
	];

	expect(check_month_boundaries($span))->toBeFalse();
});

it('shifts time correctly', function () {
	$span = [
		'begin_now' => strtotime('2023-01-01 12:00:00'),
		'end_now'   => strtotime('2023-01-01 13:00:00'),
		'current_value_date1' => '2023-01-01 12:00',
		'current_value_date2' => '2023-01-01 13:00'
	];

	// Stub global and session for shift_time
	global $config;
	$_SESSION = [];

	shift_time($span, '-', '1 hour');

	expect($span['begin_now'])->toBe(strtotime('2023-01-01 11:00:00'));
	expect($span['end_now'])->toBe(strtotime('2023-01-01 12:00:00'));
});
