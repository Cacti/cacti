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
 * Tests for the $rra_id normalization in rrdtool_function_graph().
 *
 * Several callers (lib/reports.php, graph_realtime.php) pass '' when no
 * RRA is selected.  boost_graph_set_file() declares int|null for $rra_id,
 * so the string reached it unconverted and threw a TypeError, which broke
 * report sending (#7214).  Only whole numbers are archive ids; any other
 * input falls back to best-fit RRA selection.
 *
 * SYNC WARNING: normalize_rra_id() mirrors the normalization in
 * lib/rrd.php.  The production function requires DB state, so the logic
 * is replicated here for behavioral coverage.
 */

function normalize_rra_id(mixed $rra_id): ?int {
	return (is_int($rra_id) || ctype_digit((string)$rra_id)) ? (int)$rra_id : null;
}

test('rrdtool_function_graph validates rra_id before the first boost call', function () {
	$src = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');
	expect($src)->not->toBeFalse('Failed to read lib/rrd.php');

	$fnPos = strpos($src, 'function rrdtool_function_graph(');
	expect($fnPos)->not->toBeFalse('rrdtool_function_graph must exist in lib/rrd.php');

	// Anchor on the integer-validation call by shape so a switch between
	// ctype_digit() and filter_var(FILTER_VALIDATE_INT) keeps passing.
	$matched = preg_match('/ctype_digit\s*\(|filter_var\s*\([^)]*FILTER_VALIDATE_INT/', $src, $m, PREG_OFFSET_CAPTURE, $fnPos);
	expect($matched)->toBe(1, 'integer validation of rra_id must exist inside rrdtool_function_graph');
	$normPos = $m[0][1];

	$boostPos = strpos($src, 'boost_graph_cache_check(', $fnPos);
	expect($boostPos)->not->toBeFalse('boost_graph_cache_check call must exist');
	expect($normPos)->toBeLessThan($boostPos, 'validation must precede the first boost call');
});

test('normalization maps caller inputs to int or null', function () {
	// reports.php and graph_realtime.php pass ''
	expect(normalize_rra_id(''))->toBeNull();
	// graph_image.php passes a validated numeric string
	expect(normalize_rra_id('3'))->toBe(3);
	// graphs.php debug passes int directly
	expect(normalize_rra_id(1))->toBe(1);
	expect(normalize_rra_id(null))->toBeNull();
	expect(normalize_rra_id('all'))->toBeNull();
});

test('non-integer numerics fall back to best-fit instead of truncating', function () {
	expect(normalize_rra_id('1.5'))->toBeNull();
	expect(normalize_rra_id('1e3'))->toBeNull();
	expect(normalize_rra_id('-2'))->toBeNull();
	expect(normalize_rra_id(' 3'))->toBeNull();
});
