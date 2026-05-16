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
 * month_shift() detected month-granular shifts with
 *   return ( strpos(strtolower($shift_size), 'month') > 0);
 * A shift_size of 'month' or 'months' has 'month' at offset 0; strpos
 * returns int(0) which is not > 0, so the helper said "no, this is not
 * a month-granular shift" for the most common inputs. Reports that
 * relied on month boundary checks silently slid into day-shift mode.
 * The fix is the canonical `!== false` strpos idiom.
 */

$source = file_get_contents(__DIR__ . '/../../lib/time.php');

test('lib/time.php uses !== false in month_shift', function () use ($source) {
	$start = strpos($source, 'function month_shift(');
	expect($start)->not->toBeFalse();

	$end  = strpos($source, "\nfunction ", $start + 1);
	$body = substr($source, $start, $end !== false ? $end - $start : 300);

	expect($body)->toContain("strpos(strtolower(\$shift_size), 'month') !== false");
	expect(strpos($body, "strpos(strtolower(\$shift_size), 'month') > 0"))
		->toBeFalse('the old "> 0" guard must be gone');
});

/* Local copy mirroring the fixed function. lib/time.php cannot be
 * included here without the wider Cacti bootstrap. */
if (!function_exists('_test_month_shift')) {
	function _test_month_shift($shift_size) {
		return ( strpos(strtolower($shift_size), 'month') !== false);
	}
}

test('month_shift returns true when month sits at offset zero', function () {
	expect(_test_month_shift('month'))->toBeTrue();
	expect(_test_month_shift('months'))->toBeTrue();
	expect(_test_month_shift('Month'))->toBeTrue();
});

test('month_shift returns true when month is embedded mid-string and false otherwise', function () {
	expect(_test_month_shift('last_month'))->toBeTrue();
	expect(_test_month_shift('this_month'))->toBeTrue();
	expect(_test_month_shift('day'))->toBeFalse();
	expect(_test_month_shift('hour'))->toBeFalse();
	expect(_test_month_shift(''))->toBeFalse();
});
