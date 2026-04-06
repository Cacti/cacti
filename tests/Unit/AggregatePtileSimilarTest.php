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
 * Source-scan tests for the aggregate 95th percentile SIMILAR fix.
 *
 * Before the fix, the SIMILAR total_type was lumped together with ALL
 * in the same if-branch, so both used aggregate_sum / aggregate_sum_peak.
 * SIMILAR should use aggregate_current / aggregate_current_peak instead,
 * because SIMILAR totals should reflect per-data-source consolidation
 * rather than the sum across all sources.
 *
 * The fix splits the condition into separate branches:
 *   ALL     -> aggregate_sum / aggregate_sum_peak
 *   SIMILAR -> aggregate_current / aggregate_current_peak
 */

// --- helper ---

function getApiAggregateSource(): string {
	$path = __DIR__ . '/../../lib/api_aggregate.php';
	$src  = file_get_contents($path);
	expect($src)->not->toBeFalse('Failed to read lib/api_aggregate.php');

	return $src;
}

// --- :current: replacement in graph item text_format (line ~405 area) ---

test('text_format :current: replacement uses aggregate_current for SIMILAR', function () {
	$src = getApiAggregateSource();

	// SIMILAR must map :current: to :aggregate_current:, not :aggregate_sum:
	$pattern = '/AGGREGATE_TOTAL_TYPE_SIMILAR\b.*?:aggregate_current:/s';
	expect(preg_match($pattern, $src))->toBe(1,
		'SIMILAR total_type must replace :current: with :aggregate_current:'
	);
});

test('text_format :current: replacement uses aggregate_sum only for ALL', function () {
	$src = getApiAggregateSource();

	// The ALL branch must use aggregate_sum for :current:
	// Find the block that checks for ':current:' and verify ALL maps to aggregate_sum
	$pattern = "/strpos.*':current:'.*?AGGREGATE_TOTAL_TYPE_ALL\b[^}]*?:aggregate_sum:/s";
	expect(preg_match($pattern, $src))->toBe(1,
		'ALL total_type must replace :current: with :aggregate_sum:'
	);
});

test('ALL and SIMILAR are separate branches for :current: replacement', function () {
	$src = getApiAggregateSource();

	// The old bug had: if ($_total_type == AGGREGATE_TOTAL_TYPE_ALL || $_total_type == AGGREGATE_TOTAL_TYPE_SIMILAR)
	// The fix separates them. Verify no combined condition exists for :current:
	$pattern = "/strpos.*':current:'.*?AGGREGATE_TOTAL_TYPE_ALL\s*\|\|\s*.*AGGREGATE_TOTAL_TYPE_SIMILAR/s";

	// Limit search to the text_format replacement section (first 500 lines)
	$first_half = implode("\n", array_slice(explode("\n", $src), 0, 500));
	expect(preg_match($pattern, $first_half))->toBe(0,
		'ALL and SIMILAR must not be combined in the same if-condition for :current: replacement'
	);
});

// --- :max: replacement in graph item text_format ---

test('text_format :max: replacement uses aggregate_current_peak for SIMILAR', function () {
	$src = getApiAggregateSource();

	$pattern = '/AGGREGATE_TOTAL_TYPE_SIMILAR\b.*?:aggregate_current_peak:/s';
	expect(preg_match($pattern, $src))->toBe(1,
		'SIMILAR total_type must replace :max: with :aggregate_current_peak:'
	);
});

test('text_format :max: replacement uses aggregate_sum_peak for ALL', function () {
	$src = getApiAggregateSource();

	$pattern = "/strpos.*':max:'.*?AGGREGATE_TOTAL_TYPE_ALL\b[^}]*?:aggregate_sum_peak:/s";
	expect(preg_match($pattern, $src))->toBe(1,
		'ALL total_type must replace :max: with :aggregate_sum_peak:'
	);
});

// --- ptile handler pparts[3] replacement (line ~1380 and ~1455 areas) ---

test('ptile handler separates ALL and SIMILAR for pparts replacement', function () {
	$src = getApiAggregateSource();

	// The aggregate_handle_ptile_type function must not combine ALL||SIMILAR
	$fnStart = strpos($src, 'function aggregate_handle_ptile_type(');
	expect($fnStart)->not->toBeFalse('aggregate_handle_ptile_type() must exist');

	$fnBody = substr($src, $fnStart, 8000);

	// Old pattern: AGGREGATE_TOTAL_TYPE_ALL || ... AGGREGATE_TOTAL_TYPE_SIMILAR
	$combined = preg_match_all(
		'/AGGREGATE_TOTAL_TYPE_ALL\s*\|\|\s*\$_total_type\s*==\s*AGGREGATE_TOTAL_TYPE_SIMILAR/',
		$fnBody
	);
	expect($combined)->toBe(0,
		'aggregate_handle_ptile_type must not combine ALL and SIMILAR in the same condition'
	);
});

test('ptile handler maps current to aggregate_current for SIMILAR', function () {
	$src = getApiAggregateSource();

	$fnStart = strpos($src, 'function aggregate_handle_ptile_type(');
	$fnBody  = substr($src, $fnStart, 8000);

	// SIMILAR branch must replace 'current' with 'aggregate_current'
	$pattern = "/AGGREGATE_TOTAL_TYPE_SIMILAR.*?str_replace\('current',\s*'aggregate_current'/s";
	expect(preg_match($pattern, $fnBody))->toBe(1,
		'ptile handler must replace current with aggregate_current for SIMILAR'
	);
});

test('ptile handler maps max to aggregate_current_peak for SIMILAR', function () {
	$src = getApiAggregateSource();

	$fnStart = strpos($src, 'function aggregate_handle_ptile_type(');
	$fnBody  = substr($src, $fnStart, 8000);

	$pattern = "/AGGREGATE_TOTAL_TYPE_SIMILAR.*?str_replace\('max',\s*'aggregate_current_peak'/s";
	expect(preg_match($pattern, $fnBody))->toBe(1,
		'ptile handler must replace max with aggregate_current_peak for SIMILAR'
	);
});

// --- the switch still handles all four aggregate CF names ---

test('ptile handler switch covers all four aggregate consolidation names', function () {
	$src = getApiAggregateSource();

	$fnStart = strpos($src, 'function aggregate_handle_ptile_type(');
	$fnBody  = substr($src, $fnStart, 8000);

	foreach (array('aggregate_sum', 'aggregate_sum_peak', 'aggregate_current', 'aggregate_current_peak') as $cf) {
		expect(str_contains($fnBody, "case '{$cf}'"))->toBeTrue(
			"ptile handler switch must contain case '{$cf}'"
		);
	}
});
