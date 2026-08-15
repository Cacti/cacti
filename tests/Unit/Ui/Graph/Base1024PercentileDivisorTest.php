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
 * Tests for the base-1024 percentile divisor fix (#7211).
 *
 * With base_value 1024 the legend variables divided by 10.24^power.
 * 10^6 equals 1000^2, but 10.24^6 does not equal 1024^2, so printed
 * percentiles were about 9% low at power 6.  The divisor is now
 * 2^(10*power/3), which is 1024^(power/3), the binary analogue of
 * 10^power.
 *
 * SYNC WARNING: divisor() mirrors the base/exp_scale selection in
 * lib/graph_variables.php.  The production functions require DB state,
 * so the arithmetic is replicated here for behavioral coverage.
 */

function divisor(int $base_value, float $power): float {
	if ($base_value == 1024) {
		$base      = 2;
		$exp_scale = 10 / 3;
	} else {
		$base      = 10;
		$exp_scale = 1;
	}

	return $base ** ($power * $exp_scale);
}

test('base 1000 behaviour is unchanged', function () {
	expect(divisor(1000, 6))->toBe(10.0 ** 6)
		->and(divisor(1000, 3))->toBe(10.0 ** 3)
		->and(divisor(1000, 0))->toBe(1.0);
});

test('base 1024 divides by true binary powers', function () {
	// the issue example: |95:bits:6:aggregate:2| on a base-1024 graph
	expect(divisor(1024, 6))->toBe(1024.0 ** 2)
		->and(divisor(1024, 3))->toBe(1024.0)
		->and(divisor(1024, 0))->toBe(1.0);
});

test('the old 10.24 divisor error is gone', function () {
	// 10.24^6 = 1,152,921.5 overstated the divisor by about 10%
	$old = 10.24 ** 6;
	$new = divisor(1024, 6);

	expect($new)->toBe(1048576.0)
		->and($old / $new)->toBeGreaterThan(1.09)
		->and($old / $new)->toBeLessThan(1.10);
});

test('no 10.24 divisor remains in graph_variables.php', function () {
	$src = file_get_contents(__DIR__ . '/../../../../lib/graph_variables.php');
	expect($src)->not->toBeFalse('Failed to read lib/graph_variables.php');

	expect(preg_match('/=\s*10\.24\s*;/', $src))->toBe(0, 'the 10.24 approximation must be gone from code');
});
