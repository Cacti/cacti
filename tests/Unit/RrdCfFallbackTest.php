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
 * Tests for the CF (consolidation function) fallback selection fix in lib/rrd.php.
 *
 * PR #6982: in rrdtool_function_graph(), after the AVERAGE/MAX/MIN/LAST
 * fallback chain that selects $cf_id when no DEF exists for the requested
 * data source/cf combination, there was an unconditional line:
 *
 *   $cf_id = $graph_item['cf_reference'];
 *
 * This overwrote the fallback-selected $cf_id with cf_reference, which
 * may point to a CF that has no DEF. The result was broken graph rendering
 * when the requested CF was unavailable in the RRA.
 *
 * The fix removes that unconditional assignment.
 *
 * These tests verify the fix by scanning the source of lib/rrd.php.
 */

// --- source scanning helper ---

function getRrdCfFallbackSource(): string {
	$rrdPhp = file_get_contents(__DIR__ . '/../../lib/rrd.php');
	expect($rrdPhp)->not->toBeFalse('Failed to read lib/rrd.php');

	return $rrdPhp;
}

/**
 * Extract the GRAPH ITEMS: CDEF section from rrdtool_function_graph.
 * This is the section that contains the CF fallback chain.
 */
function getCfFallbackRegion(): string {
	$source = getRrdCfFallbackSource();

	// Find the CDEF section marker
	$marker = 'GRAPH ITEMS: CDEF +++';
	$start = strpos($source, $marker);
	expect($start)->not->toBeFalse('GRAPH ITEMS: CDEF section must exist in lib/rrd.php');

	// Grab a region large enough to cover the fallback chain and what follows
	$region = substr($source, $start, 3000);

	return $region;
}

// --- the unconditional cf_reference overwrite must not exist ---

test('no unconditional cf_reference assignment after fallback chain', function () {
	$region = getCfFallbackRegion();

	/*
	 * The bug was this line appearing after the fallback if/elseif chain:
	 *   $cf_id = $graph_item['cf_reference'];
	 *
	 * This pattern should NOT exist between the fallback chain closing
	 * brace and the CDEF START marker.
	 */
	$fallbackEnd = strpos($region, "\$cf_id = 1; /* CF: AVERAGE */");
	expect($fallbackEnd)->not->toBeFalse('CF fallback chain must exist');

	$cdefStart = strpos($region, 'GRAPH ITEMS: CDEF START');
	expect($cdefStart)->not->toBeFalse('CDEF START marker must exist');

	// Extract the gap between the fallback chain end and CDEF START
	$gap = substr($region, $fallbackEnd, $cdefStart - $fallbackEnd);

	// The buggy line assigned cf_reference unconditionally
	expect(str_contains($gap, "\$cf_id = \$graph_item['cf_reference']"))->toBeFalse(
		'$cf_id must not be unconditionally overwritten by cf_reference after the fallback chain'
	);
});

// --- the fallback chain structure is intact ---

test('CF fallback chain tests AVERAGE, MAX, MIN, LAST in order', function () {
	$region = getCfFallbackRegion();

	// The fallback chain checks cf_ds_cache entries for CFs 1, 3, 2, 4
	$avgPos  = strpos($region, '$cf_id = 1; /* CF: AVERAGE */');
	$maxPos  = strpos($region, '$cf_id = 3; /* CF: MAX */');
	$minPos  = strpos($region, '$cf_id = 2; /* CF: MIN */');
	$lastPos = strpos($region, '$cf_id = 4; /* CF: LAST */');

	expect($avgPos)->not->toBeFalse('AVERAGE fallback must exist');
	expect($maxPos)->not->toBeFalse('MAX fallback must exist');
	expect($minPos)->not->toBeFalse('MIN fallback must exist');
	expect($lastPos)->not->toBeFalse('LAST fallback must exist');

	// Verify ordering: AVERAGE < MAX < MIN < LAST
	expect($avgPos)->toBeLessThan($maxPos, 'AVERAGE must come before MAX');
	expect($maxPos)->toBeLessThan($minPos, 'MAX must come before MIN');
	expect($minPos)->toBeLessThan($lastPos, 'MIN must come before LAST');
});

// --- the primary cf_ds_cache check still exists ---

test('primary cf_ds_cache check assigns consolidation_function_id', function () {
	$region = getCfFallbackRegion();

	// The primary path: when cf_ds_cache has the requested CF
	$pattern = '/if\s*\(isset\(\$cf_ds_cache\[\$graph_item\[.data_template_rrd_id.\]\]\[\$graph_cf\]\)\)/';

	expect(preg_match($pattern, $region))->toBe(1,
		'Primary cf_ds_cache check must exist before fallback chain'
	);

	expect(str_contains($region, "\$cf_id = \$graph_item['consolidation_function_id']"))->toBeTrue(
		'Primary path must assign consolidation_function_id to cf_id'
	);
});

// --- cf_reference is used legitimately elsewhere (in the column-building loop) ---

test('cf_reference is still assigned in the graph_items column-building loop', function () {
	$source = getRrdCfFallbackSource();

	// cf_reference is set during the earlier loop that builds graph_items columns
	expect(str_contains($source, "\$graph_items[\$key]['cf_reference'] = \$graph_cf"))->toBeTrue(
		'cf_reference must still be assigned in the column-building loop'
	);
});

// --- broader negative: cf_reference never overwrites cf_id in the CDEF section ---

test('cf_reference does not appear as cf_id assignment in CDEF section', function () {
	$region = getCfFallbackRegion();

	// No line like: $cf_id = $graph_item['cf_reference'];
	// should exist anywhere in the CDEF processing section
	$pattern = '/\$cf_id\s*=\s*\$graph_item\[.cf_reference.\]/';

	expect(preg_match($pattern, $region))->toBe(0,
		'cf_reference must never be assigned to cf_id in the CDEF section'
	);
});
