<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * api_scheduler_calculate_next_start() resolved every candidate date against
 * the current year only, so once the last selected date had passed it returned
 * false for the rest of the year. Its caller wrote date('Y-m-d H:i', false),
 * which stores 1970 and reads as overdue on every poller cycle after that.
 *
 * The dates were also built without a year at all -- "March 15" relative to
 * now -- and the fourth-week label was spelled 'forth', which strtotime does
 * not parse.
 *
 * These drive the real functions with an explicit $now so the assertions do
 * not depend on the day the suite runs.
 */

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/lib/api_scheduler.php';

if (!defined('CACTI_WEB')) {
	define('CACTI_WEB', false);
}

if (!defined('CACTI_PATH_LOG')) {
	define('CACTI_PATH_LOG', sys_get_temp_dir() . '/cacti-issue-7429-test.log');
}

/** A monthly-on-a-date schedule: 15 March, 09:30. */
function scheduler_monthly_schedule(array $overrides = []) : array {
	return array_merge([
		'sched_type'   => SCHEDULE_MONTHLY,
		'month'        => '3',
		'day_of_month' => '15',
		'start_at'     => '09:30',
	], $overrides);
}

/** A monthly-on-a-weekday schedule: fourth Wednesday of March, 09:30. */
function scheduler_monthly_on_day_schedule(array $overrides = []) : array {
	return array_merge([
		'sched_type'   => SCHEDULE_MONTHLY_ON_DAY,
		'month'        => '3',
		'monthly_week' => '4',
		'monthly_day'  => '4',
		'start_at'     => '09:30',
	], $overrides);
}

test('a date already past in the requested year resolves to false', function () {
	$now = strtotime('2026-08-05 12:00:00');

	expect(api_scheduler_calculate_next_start_for_year(scheduler_monthly_schedule(), 2026, $now))
		->toBeFalse();
});

test('the year rolls over instead of returning false once every date has passed', function () {
	// pre-fix this was the 1970 case: no candidate left, so false reached the
	// caller and was written as a next_start of 1970-01-01
	$next = api_scheduler_calculate_next_start(scheduler_monthly_schedule());

	expect($next)->not->toBeFalse()
		->and($next)->toBeGreaterThan(time());
});

test('a day-of-month schedule lands on the selected date at the start time', function () {
	$now  = strtotime('2026-08-05 12:00:00');
	$next = api_scheduler_calculate_next_start_for_year(scheduler_monthly_schedule(), 2027, $now);

	expect(date('Y-m-d H:i', $next))->toBe('2027-03-15 09:30');
});

test('day 32 means the last day of the selected month', function () {
	$now  = strtotime('2026-08-05 12:00:00');
	$next = api_scheduler_calculate_next_start_for_year(
		scheduler_monthly_schedule(['month' => '2', 'day_of_month' => '32']),
		2028,
		$now
	);

	// 2028 is a leap year, so this also pins the month-length handling
	expect(date('Y-m-d H:i', $next))->toBe('2028-02-29 09:30');
});

test('the fourth week of a month resolves instead of failing to parse', function () {
	$now  = strtotime('2026-08-05 12:00:00');
	$next = api_scheduler_calculate_next_start_for_year(scheduler_monthly_on_day_schedule(), 2027, $now);

	// pre-fix the label was 'forth', which strtotime rejects
	expect(date('Y-m-d H:i', $next))->toBe('2027-03-24 09:30');
});

test('each week label picks the matching Wednesday', function () {
	$now = strtotime('2026-08-05 12:00:00');

	$resolved = [];

	foreach (['1' => 'first', '2' => 'second', '3' => 'third', '4' => 'fourth', '32' => 'last'] as $week => $label) {
		$next = api_scheduler_calculate_next_start_for_year(
			scheduler_monthly_on_day_schedule(['monthly_week' => $week]),
			2027,
			$now
		);

		$resolved[$label] = date('Y-m-d', $next);
	}

	expect($resolved)->toBe([
		'first'  => '2027-03-03',
		'second' => '2027-03-10',
		'third'  => '2027-03-17',
		'fourth' => '2027-03-24',
		'last'   => '2027-03-31',
	]);
});

test('a day that no month has is skipped instead of poisoning the candidate list', function () {
	$now = strtotime('2026-08-05 12:00:00');

	// strtotime('March 40 2027') is false, and date() would render that as
	// 1970-01-01
	expect(strtotime('March 40 2027'))->toBeFalse();

	$next = api_scheduler_calculate_next_start_for_year(
		scheduler_monthly_schedule(['day_of_month' => '40,15']),
		2027,
		$now
	);

	expect(date('Y-m-d H:i', $next))->toBe('2027-03-15 09:30');
});

test('an out-of-range month falls back to January rather than the previous loop value', function () {
	$now = strtotime('2026-08-05 12:00:00');

	// '99' is not a month; before the fix the default branch assigned $Smonth,
	// so $smonth kept whatever the preceding iteration had set
	$next = api_scheduler_calculate_next_start_for_year(
		scheduler_monthly_on_day_schedule(['month' => '99']),
		2027,
		$now
	);

	expect(date('Y-m', $next))->toBe('2027-01');
});

test('a manual schedule has no next start', function () {
	$now = strtotime('2026-08-05 12:00:00');

	expect(api_scheduler_calculate_next_start_for_year(
		['sched_type' => SCHEDULE_MANUAL, 'start_at' => '09:30'],
		2027,
		$now
	))->toBeFalse();
});
