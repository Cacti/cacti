<?php
declare(strict_types=1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for CF fallback overwrite bug in rrdtool_function_graph().
 *
 * The bug was at lib/rrd.php:2060 where $cf_id was unconditionally
 * overwritten with $graph_item['cf_reference'] after the fallback
 * chain at lines 2041-2056 had selected an available CF.
 *
 * @group regression
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

/**
 * Simulate the CF fallback selection logic (FIXED version).
 *
 * When the preferred CF has no DEF, falls back to AVERAGE > MAX > MIN > LAST.
 *
 * @param array $cf_ds_cache Available CF/DS combinations (keyed as "rrd_id:cf_id")
 * @param int   $data_template_rrd_id The data template RRD ID
 * @param int   $preferred_cf The preferred consolidation function ID
 * @param int   $cf_reference The graph item's cf_reference (was used to overwrite)
 *
 * @return int The selected CF ID
 */
function cf_fallback_fixed(array $cf_ds_cache, int $data_template_rrd_id, int $preferred_cf, int $cf_reference): int {
	$cf_ds_key = "$data_template_rrd_id:$preferred_cf";

	if (isset($cf_ds_cache[$cf_ds_key])) {
		return $preferred_cf;
	}

	// Fallback: AVERAGE(1) > MAX(3) > MIN(2) > LAST(4)
	if (isset($cf_ds_cache["$data_template_rrd_id:1"])) return 1;
	if (isset($cf_ds_cache["$data_template_rrd_id:3"])) return 3;
	if (isset($cf_ds_cache["$data_template_rrd_id:2"])) return 2;
	if (isset($cf_ds_cache["$data_template_rrd_id:4"])) return 4;

	return 1; // default AVERAGE
}

/**
 * Simulate the BUGGY version that overwrites fallback with cf_reference.
 *
 * @return int Always returns cf_reference (bug)
 */
function cf_fallback_buggy(array $cf_ds_cache, int $data_template_rrd_id, int $preferred_cf, int $cf_reference): int {
	$cf_ds_key = "$data_template_rrd_id:$preferred_cf";

	if (isset($cf_ds_cache[$cf_ds_key])) {
		$cf_id = $preferred_cf;
	} else {
		if (isset($cf_ds_cache["$data_template_rrd_id:1"])) { $cf_id = 1; }
		elseif (isset($cf_ds_cache["$data_template_rrd_id:3"])) { $cf_id = 3; }
		elseif (isset($cf_ds_cache["$data_template_rrd_id:2"])) { $cf_id = 2; }
		elseif (isset($cf_ds_cache["$data_template_rrd_id:4"])) { $cf_id = 4; }
		else { $cf_id = 1; }
	}

	// BUG: overwrite with cf_reference (was line 2060)
	$cf_id = $cf_reference;

	return $cf_id;
}

// ===========================================================================
// Fixed behavior tests
// ===========================================================================

describe('CF fallback selection (fixed)', function () {
	test('preferred CF available: uses it directly', function () {
		$cache = ['10:3' => true, '10:1' => true];
		expect(cf_fallback_fixed($cache, 10, 3, 3))->toBe(3);
	});

	test('preferred CF missing: falls back to AVERAGE', function () {
		$cache = ['10:1' => true]; // only AVERAGE available
		expect(cf_fallback_fixed($cache, 10, 3, 3))->toBe(1);
	});

	test('preferred CF missing, only MAX available: falls back to MAX', function () {
		$cache = ['10:3' => true];
		expect(cf_fallback_fixed($cache, 10, 1, 1))->toBe(3);
	});

	test('preferred CF missing, only MIN available: falls back to MIN', function () {
		$cache = ['10:2' => true];
		expect(cf_fallback_fixed($cache, 10, 3, 3))->toBe(2);
	});

	test('preferred CF missing, only LAST available: falls back to LAST', function () {
		$cache = ['10:4' => true];
		expect(cf_fallback_fixed($cache, 10, 1, 1))->toBe(4);
	});

	test('no CFs available: defaults to AVERAGE (1)', function () {
		expect(cf_fallback_fixed([], 10, 3, 3))->toBe(1);
	});

	test('fallback priority: AVERAGE > MAX > MIN > LAST', function () {
		// All available: AVERAGE wins
		$all = ['10:1' => true, '10:2' => true, '10:3' => true, '10:4' => true];
		expect(cf_fallback_fixed($all, 10, 99, 99))->toBe(1);

		// No AVERAGE: MAX wins
		$no_avg = ['10:2' => true, '10:3' => true, '10:4' => true];
		expect(cf_fallback_fixed($no_avg, 10, 99, 99))->toBe(3);

		// No AVERAGE or MAX: MIN wins
		$no_avg_max = ['10:2' => true, '10:4' => true];
		expect(cf_fallback_fixed($no_avg_max, 10, 99, 99))->toBe(2);

		// Only LAST: LAST wins
		$only_last = ['10:4' => true];
		expect(cf_fallback_fixed($only_last, 10, 99, 99))->toBe(4);
	});
});

// ===========================================================================
// Bug demonstration
// ===========================================================================

describe('Bug demonstration: buggy vs fixed', function () {
	test('buggy version ignores fallback, always returns cf_reference', function () {
		$cache = ['10:1' => true]; // AVERAGE available
		// Preferred CF 3 (MAX) is not available, fallback should give 1 (AVERAGE)
		$buggy = cf_fallback_buggy($cache, 10, 3, 3);
		expect($buggy)->toBe(3); // BUG: returns cf_reference instead of fallback
	});

	test('fixed version correctly falls back', function () {
		$cache = ['10:1' => true];
		$fixed = cf_fallback_fixed($cache, 10, 3, 3);
		expect($fixed)->toBe(1); // Correctly returns AVERAGE
	});

	test('buggy and fixed produce different results when fallback needed', function () {
		$cache = ['10:1' => true];
		$buggy = cf_fallback_buggy($cache, 10, 3, 3);
		$fixed = cf_fallback_fixed($cache, 10, 3, 3);
		expect($buggy)->not->toBe($fixed);
	});

	test('both agree when preferred CF is available', function () {
		$cache = ['10:3' => true];
		$buggy = cf_fallback_buggy($cache, 10, 3, 3);
		$fixed = cf_fallback_fixed($cache, 10, 3, 3);
		expect($buggy)->toBe($fixed)->toBe(3);
	});
});

// ===========================================================================
// Mutation killers
// ===========================================================================

describe('CF fallback mutation killers', function () {
	test('fallback returns different CF than preferred when preferred missing', function () {
		$cache = ['10:1' => true];
		$r = cf_fallback_fixed($cache, 10, 3, 3);
		expect($r)->toBe(1);
		expect($r)->not->toBe(3);
	});

	test('AVERAGE(1) and MAX(3) are distinct fallback results', function () {
		$avg_only = ['10:1' => true];
		$max_only = ['10:3' => true];
		expect(cf_fallback_fixed($avg_only, 10, 99, 99))->toBe(1);
		expect(cf_fallback_fixed($max_only, 10, 99, 99))->toBe(3);
	});

	test('deterministic for same inputs', function () {
		$cache = ['10:1' => true, '10:3' => true];
		$first  = cf_fallback_fixed($cache, 10, 99, 99);
		$second = cf_fallback_fixed($cache, 10, 99, 99);
		expect($second)->toBe($first);
	});

	test('different rrd_ids are independent', function () {
		$cache = ['10:1' => true, '20:3' => true];
		expect(cf_fallback_fixed($cache, 10, 99, 99))->toBe(1);
		expect(cf_fallback_fixed($cache, 20, 99, 99))->toBe(3);
	});
});

// ===========================================================================
// Source verification: bug is fixed
// ===========================================================================

describe('Source verification: CF overwrite removed', function () {
	test('no unconditional cf_reference overwrite after fallback chain', function () {
		$c = file_get_contents(__DIR__ . '/../../lib/rrd.php');
		$p = strpos($c, 'first we need to check if there is a DEF');
		$s = substr($c, $p, 1000);

		// The fallback chain should exist
		expect($s)->toContain('CF: AVERAGE');
		expect($s)->toContain('CF: MAX');

		// But the overwrite line should NOT exist
		expect($s)->not->toContain('$cf_id = $graph_item[\'cf_reference\']');
	});
});
