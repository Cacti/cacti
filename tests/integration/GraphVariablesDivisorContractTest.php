<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Cross-file contract test for the base-1024 divisor fix (#7211).
 *
 * Every legend division in variable_nth_percentile() and
 * variable_bandwidth_summation() must apply the exponent scale, and
 * both base selection blocks must define it.
 */

$root = dirname(__DIR__, 2);

test('every percentile division applies the exponent scale', function () use ($root) {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/graph_variables.php');
	expect($src)->not->toBeFalse('Failed to read lib/graph_variables.php');

	$scaled = preg_match_all('/\$nth\s*\/=\s*\$base\s*\*\*\s*\(\$power\s*\*\s*\$exp_scale\)/', $src);
	expect($scaled)->toBe(4, 'all four nth percentile divisions must use the scale');

	$unscaled = preg_match_all('/\$nth\s*\/=\s*\$base\s*\*\*\s*\$power\s*;/', $src);
	expect($unscaled)->toBe(0, 'no unscaled nth percentile division may remain');
});

test('the bandwidth summation division applies the exponent scale', function () use ($root) {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/graph_variables.php');

	$pattern = '/\$summation\s*\/=\s*\$base\s*\*\*\s*\(\$regexp_match_array\[1\]\s*\*\s*\$exp_scale\)/';
	expect(preg_match($pattern, $src))->toBe(1, 'summation must use the scale');
});

test('both base selection blocks define the exponent scale', function () use ($root) {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/graph_variables.php');

	$count = preg_match_all('/\$exp_scale\s*=\s*10\s*\/\s*3;/', $src);
	expect($count)->toBe(2, 'both base-1024 branches must set the scale')
		->and(preg_match_all('/\$exp_scale\s*=\s*1;/', $src))->toBe(2, 'both base-1000 branches must set the scale');
});
