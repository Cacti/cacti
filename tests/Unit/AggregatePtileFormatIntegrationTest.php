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
 * Integration tests for aggregate 95th percentile format string replacement.
 *
 * These tests simulate the full COMMENT and HRULE processing pipeline in
 * aggregate_handle_ptile_type(): explode the pipe-delimited string, replace
 * pparts[3], and rejoin. This verifies that the fix produces correct
 * rrdtool variable references for SIMILAR vs ALL total types.
 *
 * The bug: SIMILAR was grouped with ALL, causing aggregate graphs to use
 * aggregate_sum (sum of in+out) instead of aggregate_current (per-source)
 * for 95th percentile, roughly doubling the displayed value.
 *
 * See: https://github.com/Cacti/cacti/issues/6835
 *
 * SYNC WARNING: process_ptile_pparts() and process_text_format() below
 * replicate logic from lib/api_aggregate.php (lines ~412-427, ~1430-1439,
 * ~1512-1521). Production functions require DB/session state that prevents
 * direct invocation in unit tests. If the production replacement logic
 * changes, these helpers must be updated to match.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

/**
 * Simulates the full COMMENT/HRULE pparts processing pipeline from
 * aggregate_handle_ptile_type(). Takes a pipe-delimited text_format or
 * value string, applies the total_type replacement on pparts[3], and
 * returns the reassembled string.
 */
function process_ptile_pparts(string $value, int $total_type): string {
	$parts = explode('|', $value);

	if (!isset($parts[1])) {
		return $value;
	}

	$pparts = explode(':', $parts[1]);

	if (!isset($pparts[3])) {
		return $value;
	}

	if ($total_type == AGGREGATE_TOTAL_TYPE_ALL) {
		$pparts[3] = str_replace('current', 'aggregate_sum', $pparts[3]);
		$pparts[3] = str_replace('max',     'aggregate_sum_peak', $pparts[3]);
	} elseif ($total_type == AGGREGATE_TOTAL_TYPE_SIMILAR) {
		$pparts[3] = str_replace('current', 'aggregate_current', $pparts[3]);
		$pparts[3] = str_replace('max',     'aggregate_current_peak', $pparts[3]);
	}

	$parts[1] = implode(':', $pparts);

	return implode('|', $parts);
}

/**
 * Simulates the full text_format replacement pipeline from
 * aggregate_graphs_insert_graph_items(). Handles :current: and :max:
 * substitutions based on total_type.
 */
function process_text_format(string $text_format, int $total_type): string {
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

// --- COMMENT pipeline: SIMILAR produces per-source percentile ---

test('COMMENT pipeline: SIMILAR current produces aggregate_current', function () {
	$input  = '95th Percentile|nth_percentile:95:traffic_in:current';
	$result = process_ptile_pparts($input, AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('95th Percentile|nth_percentile:95:traffic_in:aggregate_current');
});

test('COMMENT pipeline: SIMILAR max produces aggregate_current_peak', function () {
	$input  = '95th Percentile|nth_percentile:95:traffic_in:max';
	$result = process_ptile_pparts($input, AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('95th Percentile|nth_percentile:95:traffic_in:aggregate_current_peak');
});

test('COMMENT pipeline: ALL current produces aggregate_sum', function () {
	$input  = '95th Percentile|nth_percentile:95:traffic_in:current';
	$result = process_ptile_pparts($input, AGGREGATE_TOTAL_TYPE_ALL);

	expect($result)->toBe('95th Percentile|nth_percentile:95:traffic_in:aggregate_sum');
});

test('COMMENT pipeline: ALL max produces aggregate_sum_peak', function () {
	$input  = '95th Percentile|nth_percentile:95:traffic_in:max';
	$result = process_ptile_pparts($input, AGGREGATE_TOTAL_TYPE_ALL);

	expect($result)->toBe('95th Percentile|nth_percentile:95:traffic_in:aggregate_sum_peak');
});

// --- HRULE pipeline: same replacement pattern ---

test('HRULE pipeline: SIMILAR current produces aggregate_current', function () {
	$input  = '#FF5722|nth_percentile:95:traffic_out:current';
	$result = process_ptile_pparts($input, AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('#FF5722|nth_percentile:95:traffic_out:aggregate_current');
});

test('HRULE pipeline: SIMILAR max produces aggregate_current_peak', function () {
	$input  = '#FF5722|nth_percentile:95:traffic_out:max';
	$result = process_ptile_pparts($input, AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('#FF5722|nth_percentile:95:traffic_out:aggregate_current_peak');
});

test('HRULE pipeline: ALL max produces aggregate_sum_peak', function () {
	$input  = '#FF5722|nth_percentile:95:traffic_out:max';
	$result = process_ptile_pparts($input, AGGREGATE_TOTAL_TYPE_ALL);

	expect($result)->toBe('#FF5722|nth_percentile:95:traffic_out:aggregate_sum_peak');
});

// --- SIMILAR must never produce summed values ---

test('SIMILAR COMMENT never produces aggregate_sum for current', function () {
	$input  = '95th Percentile|nth_percentile:95:traffic_in:current';
	$result = process_ptile_pparts($input, AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->not->toContain('aggregate_sum:')
		->and($result)->not->toContain(':aggregate_sum');
});

test('SIMILAR HRULE never produces aggregate_sum_peak for max', function () {
	$input  = '#FF5722|nth_percentile:95:traffic_in:max';
	$result = process_ptile_pparts($input, AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->not->toContain('aggregate_sum_peak');
});

// --- text_format pipeline: full string with ISP billing context ---

test('text_format: SIMILAR with :current: in bandwidth label', function () {
	$result = process_text_format('95th In :current: bps', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('95th In :aggregate_current: bps');
});

test('text_format: SIMILAR with :max: in bandwidth label', function () {
	$result = process_text_format('95th Peak :max: bps', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('95th Peak :aggregate_current_peak: bps');
});

test('text_format: ALL with :current: in bandwidth label', function () {
	$result = process_text_format('95th Total :current: bps', AGGREGATE_TOTAL_TYPE_ALL);

	expect($result)->toBe('95th Total :aggregate_sum: bps');
});

test('text_format: ALL with :max: produces aggregate_sum_peak not aggregate_sum', function () {
	// This is the secondary fix: ALL+max was using aggregate_sum instead of
	// aggregate_sum_peak, mismatching the COMMENT/HRULE paths
	$result = process_text_format('95th Peak :max: bps', AGGREGATE_TOTAL_TYPE_ALL);

	expect($result)->toBe('95th Peak :aggregate_sum_peak: bps')
		->and($result)->not->toContain(':aggregate_sum:');
});

// --- Edge cases ---

test('pparts with no pipe delimiter returns unchanged', function () {
	$result = process_ptile_pparts('no pipes here', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('no pipes here');
});

test('pparts with fewer than 4 colon parts returns unchanged', function () {
	$result = process_ptile_pparts('label|nth_percentile:95:traffic_in', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($result)->toBe('label|nth_percentile:95:traffic_in');
});

test('unknown total type leaves pparts unchanged', function () {
	$input  = '95th Percentile|nth_percentile:95:traffic_in:current';
	$result = process_ptile_pparts($input, 99);

	expect($result)->toBe($input);
});

test('unknown total type leaves text_format unchanged', function () {
	$result = process_text_format('95th :current: bps', 99);

	expect($result)->toBe('95th :current: bps');
});

// --- Inbound vs Outbound: verify both data sources handled independently ---

test('SIMILAR handles traffic_in and traffic_out independently', function () {
	$in_result  = process_ptile_pparts('In|nth_percentile:95:traffic_in:current', AGGREGATE_TOTAL_TYPE_SIMILAR);
	$out_result = process_ptile_pparts('Out|nth_percentile:95:traffic_out:current', AGGREGATE_TOTAL_TYPE_SIMILAR);

	expect($in_result)->toBe('In|nth_percentile:95:traffic_in:aggregate_current')
		->and($out_result)->toBe('Out|nth_percentile:95:traffic_out:aggregate_current')
		->and($in_result)->not->toBe($out_result);
});
