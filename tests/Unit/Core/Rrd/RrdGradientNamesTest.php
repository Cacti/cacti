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
 * Each gradient AREA defines its own CDEFs. Two gradients in one graph must
 * not define the same name, whatever the random number generator returns.
 */

require_once dirname(__DIR__, 4) . '/lib/rrd.php';

function rrdGradientCdefNames($spline) {
	preg_match_all('/^CDEF:([-_A-Za-z0-9]+)=/m', str_replace(RRD_NL, "\n", $spline), $names);

	return $names[1];
}

test('two gradients define different CDEF names', function () {
	mt_srand(42);
	$first = rrdGradientCdefNames(gradient('a', "#FF0000'FF'", '#990000FF', 'In', 20));

	mt_srand(42);
	$second = rrdGradientCdefNames(gradient('b', "#00FF00'FF'", '#009900FF', 'Out', 20));

	expect($first)->toHaveCount(21)
		->and($second)->toHaveCount(21)
		->and(array_intersect($first, $second))->toBe(array());
});

test('the names of one gradient are distinct and valid RRDtool names', function () {
	$names = rrdGradientCdefNames(gradient('a', "#FF0000'FF'", '#990000FF', 'In', 20));

	expect(array_unique($names))->toBe($names);

	foreach ($names as $name) {
		expect(strlen($name))->toBeLessThanOrEqual(255);
	}
});

test('each shaded area draws a CDEF of the same gradient', function () {
	$spline = str_replace(RRD_NL, "\n", gradient('a', "#FF0000'FF'", '#990000FF', 'In', 20));
	$names  = rrdGradientCdefNames($spline);

	preg_match_all('/^AREA:([-_A-Za-z0-9]+)#/m', $spline, $areas);

	expect($areas[1])->toHaveCount(21)
		->and(array_values(array_diff($areas[1], $names)))->toBe(array());
});
