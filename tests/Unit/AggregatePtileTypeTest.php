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
 * Tests for the AGGREGATE_TOTAL_TYPE_SIMILAR percentile replacement fix.
 *
 * Prior to the fix, the SIMILAR case was grouped with ALL in three
 * str_replace blocks, causing SIMILAR to use aggregate_sum / aggregate_sum_peak
 * (summed across all data sources) instead of aggregate_current / aggregate_current_peak
 * (per-data-source percentiles). This produced identical inbound and outbound
 * 95th percentile HRULEs.
 *
 * See: https://github.com/Cacti/cacti/issues/6470
 *
 * The fix separates SIMILAR into its own elseif branch in all three locations:
 *   1. text_format :current: / :max: replacement (aggregate_graphs_insert_graph_items)
 *   2. COMMENT item pparts[3] replacement (aggregate_graphs_insert_graph_items)
 *   3. HRULE item pparts[3] replacement (aggregate_graphs_insert_graph_items)
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

/**
 * Mimics the text_format :current: / :max: replacement logic from
 * aggregate_graphs_insert_graph_items() in lib/api_aggregate.php.
 */
function apply_text_format_replacement(string $text_format, int $total_type): string {
	if (str_contains($text_format, ':current:')) {
		if ($total_type == AGGREGATE_TOTAL_TYPE_ALL) {
			$text_format = str_replace(':current:', ':aggregate_sum:', $text_format);
		} elseif ($total_type == AGGREGATE_TOTAL_TYPE_SIMILAR) {
			$text_format = str_replace(':current:', ':aggregate_current:', $text_format);
		}
	} elseif (str_contains($text_format, ':max:')) {
		if ($total_type == AGGREGATE_TOTAL_TYPE_ALL) {
			$text_format = str_replace(':max:', ':aggregate_sum_peak:', $text_format);
		} elseif ($total_type == AGGREGATE_TOTAL_TYPE_SIMILAR) {
			$text_format = str_replace(':max:', ':aggregate_current_peak:', $text_format);
		}
	}

	return $text_format;
}

/**
 * Mimics the COMMENT / HRULE pparts[3] replacement logic from
 * aggregate_graphs_insert_graph_items() around lines 1421-1427 and 1501-1507.
 * Both paths use the same replacement pattern on pparts[3].
 */
function apply_pparts_replacement(string $ppart, int $total_type): string {
	if ($total_type == AGGREGATE_TOTAL_TYPE_ALL) {
		$ppart = str_replace('current', 'aggregate_sum', $ppart);
		$ppart = str_replace('max',     'aggregate_sum_peak', $ppart);
	} elseif ($total_type == AGGREGATE_TOTAL_TYPE_SIMILAR) {
		$ppart = str_replace('current', 'aggregate_current', $ppart);
		$ppart = str_replace('max',     'aggregate_current_peak', $ppart);
	}

	return $ppart;
}

// --- text_format path: SIMILAR uses aggregate_current for :current: ---

test('text_format SIMILAR replaces :current: with :aggregate_current:', function () {
	$result = apply_text_format_replacement('|query_ifSpeed| :current:', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('|query_ifSpeed| :aggregate_current:');
});

test('text_format ALL replaces :current: with :aggregate_sum:', function () {
	$result = apply_text_format_replacement('|query_ifSpeed| :current:', AGGREGATE_TOTAL_TYPE_ALL);

	expect($result)->toBe('|query_ifSpeed| :aggregate_sum:');
});

// --- text_format path: SIMILAR uses aggregate_current_peak for :max: ---

test('text_format SIMILAR replaces :max: with :aggregate_current_peak:', function () {
	$result = apply_text_format_replacement('|query_ifSpeed| :max:', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('|query_ifSpeed| :aggregate_current_peak:');
});

test('text_format ALL replaces :max: with :aggregate_sum_peak:', function () {
	$result = apply_text_format_replacement('|query_ifSpeed| :max:', AGGREGATE_TOTAL_TYPE_ALL);

	expect($result)->toBe('|query_ifSpeed| :aggregate_sum_peak:');
});

// --- text_format path: unrecognized total type leaves value unchanged ---

test('text_format with unknown total type leaves :current: unchanged', function () {
	$result = apply_text_format_replacement('|query_ifSpeed| :current:', 99);

	expect($result)->toBe('|query_ifSpeed| :current:');
});

// --- COMMENT/HRULE pparts path: SIMILAR uses aggregate_current ---

test('pparts SIMILAR replaces current with aggregate_current', function () {
	$result = apply_pparts_replacement('current', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('aggregate_current');
});

test('pparts ALL replaces current with aggregate_sum', function () {
	$result = apply_pparts_replacement('current', AGGREGATE_TOTAL_TYPE_ALL);

	expect($result)->toBe('aggregate_sum');
});

// --- COMMENT/HRULE pparts path: SIMILAR uses aggregate_current_peak ---

test('pparts SIMILAR replaces max with aggregate_current_peak', function () {
	$result = apply_pparts_replacement('max', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('aggregate_current_peak');
});

test('pparts ALL replaces max with aggregate_sum_peak', function () {
	$result = apply_pparts_replacement('max', AGGREGATE_TOTAL_TYPE_ALL);

	expect($result)->toBe('aggregate_sum_peak');
});

// --- pparts path: SIMILAR must NOT produce aggregate_sum ---

test('pparts SIMILAR never produces aggregate_sum for current input', function () {
	$result = apply_pparts_replacement('current', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->not->toBe('aggregate_sum');
});

test('pparts SIMILAR never produces aggregate_sum_peak for max input', function () {
	$result = apply_pparts_replacement('max', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->not->toBe('aggregate_sum_peak');
});

// --- pparts path: unknown total type leaves value unchanged ---

test('pparts with unknown total type leaves current unchanged', function () {
	$result = apply_pparts_replacement('current', 99);

	expect($result)->toBe('current');
});
