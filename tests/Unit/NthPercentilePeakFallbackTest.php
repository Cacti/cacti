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
 * Tests for the nth_percentile() peak fallback.
 *
 * rrdtool_function_stats() always emits a 'peak' key, empty when the RRD
 * file has no MAX consolidation function.  nth_percentile() keyed its
 * fallback on array_key_exists(), so the empty array was returned as-is
 * and any peak-type percentile legend variable printed 0 instead of
 * falling back to the AVERAGE based statistics.
 *
 * nth_percentile() is extracted from lib/graph_variables.php source with
 * rrdtool_function_stats() redirected to a stub, so the fallback decision
 * is exercised without RRDtool or a database.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';

if (!function_exists('nth_percentile_stats_stub')) {
	function nth_percentile_stats_stub() : string {
		return $GLOBALS['nth_percentile_stub_json'];
	}
}

if (!function_exists('nth_percentile_under_test')) {
	$src = file_get_contents(__DIR__ . '/../../lib/graph_variables.php');

	$start = strpos($src, 'function nth_percentile(');
	$end   = strpos($src, "\n}", $start);

	$fn = substr($src, $start, $end - $start + 2);
	$fn = str_replace('function nth_percentile(', 'function nth_percentile_under_test(', $fn);
	$fn = str_replace('rrdtool_function_stats(', 'nth_percentile_stats_stub(', $fn);

	// test-only eval of source read from this repository, not external input
	eval($fn);
}

function nth_percentile_with_stats(array $stats, bool $peak) : mixed {
	$GLOBALS['nth_percentile_stub_json'] = json_encode($stats);

	return nth_percentile_under_test([1 => ['traffic_in']], 0, 300, 95, 0, $peak);
}

test('peak request returns the MAX based statistics when present', function () {
	$result = nth_percentile_with_stats([
		'avg'  => ['traffic_in' => 100],
		'peak' => ['traffic_in' => 250],
	], true);

	expect($result)->toBe(['traffic_in' => 250]);
});

test('peak request falls back to the AVERAGE statistics when the RRD has no MAX RRA', function () {
	// no MAX consolidation function: rrdtool_function_stats() emits 'peak' => []
	$result = nth_percentile_with_stats([
		'avg'  => ['traffic_in' => 100],
		'peak' => [],
	], true);

	expect($result)->toBe(['traffic_in' => 100]);
});

test('peak request without any usable data returns an empty result', function () {
	expect(nth_percentile_with_stats([], true))->toBe([]);
});

test('non-peak request still returns the AVERAGE statistics', function () {
	$result = nth_percentile_with_stats([
		'avg'  => ['traffic_in' => 100],
		'peak' => [],
	], false);

	expect($result)->toBe(['traffic_in' => 100]);
});
