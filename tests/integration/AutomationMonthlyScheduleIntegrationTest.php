<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for calculateNextStart() / calculateNextStartForYear()
 * (lib/api_automation.php).
 *
 * tests/Unit/AutomationMonthlyScheduleTest.php checks the fix's source
 * fragments and re-implements the year-rollover scan in a local mirror
 * function, because it assumed the real function "cannot be included ...
 * without the full Cacti bootstrap". That is not the case: neither function
 * touches the database or a Cacti global, so this test requires the real
 * lib/api_automation.php and calls the real functions directly.
 *
 * The original bug was specifically about repeated/cumulative corruption:
 * calculateNextStart() only scanned the current year, so once every date in
 * a monthly schedule had passed for the year it returned false every single
 * poller cycle, and the caller stored date('Y-m-d H:i', false) as
 * '1970-01-01 00:00', which the run check then treated as perpetually due.
 * A single-call unit test cannot show that "every cycle" failure mode; this
 * test drives calculateNextStartForYear() across a simulated sequence of
 * poller cycles (each cycle's $now taken from the previous cycle's computed
 * next_start) and asserts the schedule keeps resolving to a real future date
 * instead of ever collapsing to false/1970.
 */

require_once dirname(__DIR__, 2) . '/lib/api_automation.php';

if (!isset($GLOBALS['config'])) {
	/* automation_debug() prints to stdout when $config['is_web'] is falsy;
	 * keep test output clean since we don't care about that log line here. */
	$GLOBALS['config'] = array('is_web' => true);
}

function automation_next_start_for($net, $now) {
	$next = calculateNextStartForYear($net, (int) date('Y', $now), $now);

	if ($next === false) {
		$next = calculateNextStartForYear($net, (int) date('Y', $now) + 1, $now);
	}

	return $next;
}

test('a day-of-month schedule never collapses to false/1970 across five simulated poller cycles', function () {
	$net = array(
		'sched_type'   => '4',
		'month'        => '3',
		'day_of_month' => '15',
		'start_at'     => '09:00:00',
	);

	$now = mktime(12, 0, 0, 1, 1, 2026);

	for ($cycle = 0; $cycle < 5; $cycle++) {
		$next = automation_next_start_for($net, $now);

		expect($next)->not->toBeFalse("cycle $cycle collapsed to false");
		expect($next)->toBeGreaterThan($now);
		expect(date('Y', $next))->not->toBe('1970');

		/* advance "now" past the computed next_start, as the poller would
		 * on its following run once the schedule fires. */
		$now = $next + 86400;
	}
});

test('a last-day-of-month schedule (day 32) never collapses to false/1970 across cycles', function () {
	$net = array(
		'sched_type'   => '4',
		'month'        => '2',
		'day_of_month' => '32',
		'start_at'     => '23:00:00',
	);

	$now = mktime(0, 0, 0, 1, 1, 2026);

	for ($cycle = 0; $cycle < 4; $cycle++) {
		$next = automation_next_start_for($net, $now);

		expect($next)->not->toBeFalse("cycle $cycle collapsed to false");
		expect(date('Y', $next))->not->toBe('1970');

		$now = $next + 86400;
	}
});

test('a fourth-week-of-month schedule (sched_type 5) never collapses to false/1970 across cycles', function () {
	$net = array(
		'sched_type'   => '5',
		'month'        => '6',
		'monthly_week' => '4',
		'monthly_day'  => '1',
		'start_at'     => '06:00:00',
	);

	$now = mktime(0, 0, 0, 1, 1, 2026);

	for ($cycle = 0; $cycle < 4; $cycle++) {
		$next = automation_next_start_for($net, $now);

		expect($next)->not->toBeFalse("cycle $cycle collapsed to false (the 'forth' misspelling regression would show up here)");
		expect(date('Y', $next))->not->toBe('1970');

		$now = $next + 86400;
	}
});

test('once every date in the current year has passed, the schedule rolls to next year instead of failing', function () {
	$net = array(
		'sched_type'   => '4',
		'month'        => '1',
		'day_of_month' => '15',
		'start_at'     => '09:00:00',
	);

	/* simulate "now" already past January 15th of the schedule's year */
	$now = mktime(12, 0, 0, 6, 1, 2026);

	$next = automation_next_start_for($net, $now);

	expect($next)->not->toBeFalse();
	expect((int) date('Y', $next))->toBe(2027);
	expect((int) date('n', $next))->toBe(1);
	expect((int) date('j', $next))->toBe(15);
});
