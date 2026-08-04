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
 * calculateNextStart() built monthly candidate dates with the current year
 * only and returned false once every selected date had passed. The caller
 * then wrote date('Y-m-d H:i', false) = '1970-01-01', which the run check
 * read as long overdue and fired discovery every poller cycle. Two smaller
 * defects fed the same failure: the fourth-week label was misspelled 'forth'
 * (strtotime() returns false), and the false return was stored verbatim.
 */

$source = file_get_contents(__DIR__ . '/../../lib/api_automation.php');

test('lib/api_automation.php spells the fourth week correctly', function () use ($source) {
	expect($source)->toContain("\$sweek = 'fourth';");
	expect(strpos($source, "\$sweek = 'forth';"))
		->toBeFalse('the misspelled "forth" label must be gone');
});

test('monthly candidates are built for an explicit year', function () use ($source) {
	expect($source)->toContain('strtotime("$smonth $day $year")');
	expect($source)->toContain('strtotime("last day of $smonth $year")');
	expect($source)->toContain('strtotime("$sweek $sday of $smonth $year")');
});

test('calculateNextStart guards against a false next_start', function () use ($source) {
	expect($source)->toContain('function calculateNextStartForYear($net, $year, $now)');
	expect($source)->toContain('if (empty($next)) {');
});

/* Local mirror of the fixed scan. calculateNextStart() cannot be included
 * here without the full Cacti bootstrap, so this reproduces the year-rollover
 * logic to prove behavior. */
if (!function_exists('_test_next_start')) {
	function _test_next_start($month_name, $day, $start_at, $now) {
		foreach (array((int) date('Y', $now), (int) date('Y', $now) + 1) as $year) {
			$dates = array(strtotime("$month_name $day $year"));

			asort($dates);

			foreach ($dates as $date) {
				if ($date === false) {
					continue;
				}

				$ntime = strtotime(date('Y-m-d', $date) . ' ' . date('H:i:s', strtotime($start_at)));

				if ($ntime > $now) {
					return $ntime;
				}
			}
		}

		return false;
	}
}

test('a monthly schedule whose date already passed rolls into next year', function () {
	$now = mktime(12, 0, 0, 7, 27, 2026);

	$next = _test_next_start('January', 15, '2026-01-15 09:00:00', $now);

	expect($next)->not->toBeFalse();
	expect(date('Y', $next))->toBe('2027');
});

test('strtotime resolves the fourth weekday only with the corrected spelling', function () {
	expect(strtotime('fourth Sunday of January 2027'))->not->toBeFalse();
	expect((int) date('N', strtotime('fourth Sunday of January 2027')))->toBe(7);
	expect(strtotime('forth Sunday of January 2027'))->toBeFalse();
});
