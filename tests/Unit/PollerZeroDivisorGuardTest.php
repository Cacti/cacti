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
 * update_host_status() averaged ping times by dividing with
 * total_polls - failed_polls. Counters that agree cannot make that zero, but
 * stored counters that disagree can, and PHP 8 turned division by zero from a
 * warning returning INF into a fatal error that would end the poll for the
 * device. The reverse lookup helper had the matching problem in the other
 * direction: it used the return of a write as the offset it parses the reply
 * from, and a failed write reads as offset zero.
 */

$src = file_get_contents(CACTI_PATH_LIBRARY . '/functions.php');

test('PHP 8 makes a zero divisor fatal rather than infinite', function () {
	/* the reason the guard is needed at all, rather than the warning and INF
	   that the same expression produced on PHP 7 */
	expect(fn () => 1 / 0)->toThrow(DivisionByZeroError::class);
});

test('the average is guarded before it divides', function () use ($src) {
	expect($src)->not->toContain("/ (\$host['total_polls'] - \$host['failed_polls'])");

	expect($src)->toContain("\$successful_polls = \$host['total_polls'] - \$host['failed_polls'];")
		->toContain('if ($successful_polls > 0) {');
});

test('the guarded average still matches the original formula', function () {
	/* the rewrite has to be arithmetic-neutral for every input that already
	   worked, so compare it against the expression it replaced */
	$original = function (int $total, int $failed, float $avg, float $ping) : float {
		return (($total - 1 - $failed) * $avg + $ping) / ($total - $failed);
	};

	$guarded = function (int $total, int $failed, float $avg, float $ping) : float {
		$successful = $total - $failed;

		if ($successful > 0) {
			return (($successful - 1) * $avg + $ping) / $successful;
		}

		return $ping;
	};

	foreach ([[1, 0, 0.0, 12.5], [2, 0, 12.5, 7.5], [10, 3, 20.0, 5.0], [500, 499, 3.0, 9.0]] as $case) {
		[$total, $failed, $avg, $ping] = $case;

		expect($guarded($total, $failed, $avg, $ping))->toBe($original($total, $failed, $avg, $ping));
	}
});

test('inconsistent counters fall back to the sample instead of dividing', function () {
	$guarded = function (int $total, int $failed, float $avg, float $ping) : float {
		$successful = $total - $failed;

		if ($successful > 0) {
			return (($successful - 1) * $avg + $ping) / $successful;
		}

		return $ping;
	};

	// failed_polls ahead of total_polls is the shape that used to be fatal
	expect($guarded(1, 1, 4.0, 9.0))->toBe(9.0)
		->and($guarded(1, 5, 4.0, 9.0))->toBe(9.0);
});

test('a failed write cannot be used as a parse offset', function () use ($src) {
	$write  = strpos($src, '$requestsize = @fwrite($handle, $data);');
	$guard  = strpos($src, 'if ($requestsize === false) {');
	$parse  = strpos($src, "@unpack('s', substr(\$response, \$requestsize + 2))");

	expect($write)->not->toBeFalse()
		->and($guard)->not->toBeFalse()
		->and($parse)->not->toBeFalse();

	// the guard has to sit between the write and the offset arithmetic
	expect($guard)->toBeGreaterThan($write)
		->and($guard)->toBeLessThan($parse);
});
