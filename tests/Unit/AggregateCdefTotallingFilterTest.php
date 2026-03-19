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
 * Tests for the graph_type_id filter in aggregate_cdef_totalling().
 *
 * aggregate_cdef_totalling() queries graph_templates_item and replaces
 * CURRENT_DATA_SOURCE with SIMILAR_DATA_SOURCES_NODUPS (or ALL_...).
 * GPRINT and LEGEND items carry cdef_id but are not renderable data
 * items: the magic-variable expansion loop in rrd.php only walks
 * AREA/STACK/LINE items, so aggregate CDEFs on GPRINTs produce empty
 * RPN strings ("CDEF:cdefX=") which rrdtool rejects.
 *
 * The fix adds "AND graph_type_id NOT IN (9,10,11,12,13,14,15)" to
 * exclude GPRINT (9), LEGEND (10), GPRINT_LAST (11), GPRINT_MAX (12),
 * GPRINT_MIN (13), GPRINT_AVERAGE (14), and LEGEND_CAMM (15).
 *
 * SYNC WARNING: filter_totalling_items() below replicates the NOT IN
 * filter from lib/aggregate.php aggregate_cdef_totalling(). Production
 * functions require DB state that prevents direct invocation in unit
 * tests. If the production filter changes, this helper must be updated.
 */

require_once __DIR__ . '/../../include/global_constants.php';

/**
 * Mirrors the NOT IN filter from aggregate_cdef_totalling().
 * Returns only items whose graph_type_id is eligible for totalling.
 */
function filter_totalling_items(array $items): array {
	$excluded = [
		GRAPH_ITEM_TYPE_GPRINT,          // 9
		GRAPH_ITEM_TYPE_LEGEND,          // 10
		GRAPH_ITEM_TYPE_GPRINT_LAST,     // 11
		GRAPH_ITEM_TYPE_GPRINT_MAX,      // 12
		GRAPH_ITEM_TYPE_GPRINT_MIN,      // 13
		GRAPH_ITEM_TYPE_GPRINT_AVERAGE,  // 14
		GRAPH_ITEM_TYPE_LEGEND_CAMM,     // 15
	];

	return array_values(array_filter($items, function ($item) use ($excluded) {
		return !in_array($item['graph_type_id'], $excluded, true);
	}));
}

// --- Drift detection: excluded set must match global_constants.php ---

test('excluded set contains exactly the 7 GPRINT/LEGEND constants', function () {
	$excluded = [
		GRAPH_ITEM_TYPE_GPRINT,
		GRAPH_ITEM_TYPE_LEGEND,
		GRAPH_ITEM_TYPE_GPRINT_LAST,
		GRAPH_ITEM_TYPE_GPRINT_MAX,
		GRAPH_ITEM_TYPE_GPRINT_MIN,
		GRAPH_ITEM_TYPE_GPRINT_AVERAGE,
		GRAPH_ITEM_TYPE_LEGEND_CAMM,
	];

	expect($excluded)->toHaveCount(7)
		->and($excluded)->toBe([9, 10, 11, 12, 13, 14, 15]);
});

// --- Renderable types pass through ---

test('LINE1 items pass the totalling filter', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_LINE1]];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

test('LINE2 items pass the totalling filter', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_LINE2]];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

test('LINE3 items pass the totalling filter', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_LINE3]];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

test('AREA items pass the totalling filter', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_AREA]];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

test('STACK items pass the totalling filter', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_STACK]];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

test('LINESTACK items pass the totalling filter', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_LINESTACK]];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

test('TIC items pass the totalling filter', function () {
	$items = [['id' => 1, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_TIC]];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

test('TEXTALIGN items pass the totalling filter', function () {
	$items = [['id' => 1, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_TEXTALIGN]];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

// --- GPRINT and LEGEND types are excluded ---

test('GPRINT items are excluded from totalling', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT]];

	expect(filter_totalling_items($items))->toHaveCount(0);
});

test('LEGEND items are excluded from totalling', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_LEGEND]];

	expect(filter_totalling_items($items))->toHaveCount(0);
});

test('GPRINT_LAST items are excluded from totalling', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT_LAST]];

	expect(filter_totalling_items($items))->toHaveCount(0);
});

test('GPRINT_MAX items are excluded from totalling', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT_MAX]];

	expect(filter_totalling_items($items))->toHaveCount(0);
});

test('GPRINT_MIN items are excluded from totalling', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT_MIN]];

	expect(filter_totalling_items($items))->toHaveCount(0);
});

test('GPRINT_AVERAGE items are excluded from totalling', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT_AVERAGE]];

	expect(filter_totalling_items($items))->toHaveCount(0);
});

test('LEGEND_CAMM items are excluded from totalling', function () {
	$items = [['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_LEGEND_CAMM]];

	expect(filter_totalling_items($items))->toHaveCount(0);
});

// --- Mixed item sets: reproduces the issue scenario ---

test('mixed AREA and GPRINT items only keep AREA items', function () {
	$items = [
		['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_AREA],
		['id' => 2, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT_MAX],
		['id' => 3, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_AREA],
		['id' => 4, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT_AVERAGE],
		['id' => 5, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT_LAST],
	];

	$filtered = filter_totalling_items($items);

	expect($filtered)->toHaveCount(2)
		->and($filtered[0]['id'])->toBe(1)
		->and($filtered[1]['id'])->toBe(3);
});

test('aggregate template scenario: LINE+AREA+GPRINT items filter correctly', function () {
	/* Reproduces the issue: 2 data sources, each with MAX and AVERAGE DEFs,
	   each with LINE1 (MAX), AREA (AVERAGE), then GPRINT items for stats. */
	$items = [
		['id' => 1, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_LINE1],
		['id' => 2, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_AREA],
		['id' => 3, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_LINE1],
		['id' => 4, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_AREA],
		['id' => 5, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_LINE1],
		['id' => 6, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_AREA],
		['id' => 7, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT_LAST],
		['id' => 8, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT_AVERAGE],
		['id' => 9, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT_MAX],
	];

	$filtered = filter_totalling_items($items);

	expect($filtered)->toHaveCount(6);

	foreach ($filtered as $item) {
		expect(in_array($item['graph_type_id'], [
			GRAPH_ITEM_TYPE_GPRINT,
			GRAPH_ITEM_TYPE_LEGEND,
			GRAPH_ITEM_TYPE_GPRINT_LAST,
			GRAPH_ITEM_TYPE_GPRINT_MAX,
			GRAPH_ITEM_TYPE_GPRINT_MIN,
			GRAPH_ITEM_TYPE_GPRINT_AVERAGE,
			GRAPH_ITEM_TYPE_LEGEND_CAMM,
		], true))->toBeFalse();
	}
});

// --- Decorative types (COMMENT, HRULE, VRULE) are not excluded ---

test('COMMENT items pass the totalling filter', function () {
	$items = [['id' => 1, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_COMMENT]];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

test('HRULE items pass the totalling filter', function () {
	$items = [['id' => 1, 'cdef_id' => 0, 'graph_type_id' => GRAPH_ITEM_TYPE_HRULE]];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

// --- Edge cases ---

test('empty input array returns empty result', function () {
	expect(filter_totalling_items([]))->toHaveCount(0);
});

test('items with duplicate entries are all preserved when eligible', function () {
	$items = [
		['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_AREA],
		['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_AREA],
	];

	expect(filter_totalling_items($items))->toHaveCount(2);
});

test('mixed valid and excluded items filter correctly', function () {
	$items = [
		['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_LINE1],
		['id' => 2, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT],
		['id' => 3, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_STACK],
		['id' => 4, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_LEGEND_CAMM],
	];

	$filtered = filter_totalling_items($items);

	expect($filtered)->toHaveCount(2)
		->and($filtered[0]['id'])->toBe(1)
		->and($filtered[1]['id'])->toBe(3);
});

// --- Duplicate ineligible items ---

test('duplicate excluded items are all removed', function () {
	$items = [
		['id' => 1, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT],
		['id' => 2, 'cdef_id' => 5, 'graph_type_id' => GRAPH_ITEM_TYPE_GPRINT],
	];

	expect(filter_totalling_items($items))->toHaveCount(0);
});

// --- Unknown graph_type_id values pass through (not in exclusion list) ---

test('unknown graph_type_id values are not excluded', function () {
	$items = [
		['id' => 1, 'cdef_id' => 0, 'graph_type_id' => 999],
	];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

test('graph_type_id zero passes the filter', function () {
	$items = [
		['id' => 1, 'cdef_id' => 0, 'graph_type_id' => 0],
	];

	expect(filter_totalling_items($items))->toHaveCount(1);
});

test('negative graph_type_id passes the filter', function () {
	$items = [
		['id' => 1, 'cdef_id' => 0, 'graph_type_id' => -1],
	];

	expect(filter_totalling_items($items))->toHaveCount(1);
});
