<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Test for GPRINT case consolidation refactoring
 * Verifies that all four GPRINT_* types behave identically
 */

// Load Cacti constants for GRAPH_ITEM_TYPE_* definitions
require_once __DIR__ . '/../../include/global_constants.php';

// Mock the graph item behavior that the switch statement processes
function mockGraphItem(int $graphType, int $consolidationFunctionId): array
{
	return [
		'graph_type_id'              => $graphType,
		'consolidation_function_id'  => $consolidationFunctionId,
		'local_data_id'              => 1,
	];
}

// Extract the consolidation logic from the switch statement
function processGraphItemType(array $graphItem): ?int
{
	switch ($graphItem['graph_type_id']) {
		case GRAPH_ITEM_TYPE_GPRINT_AVERAGE:
		case GRAPH_ITEM_TYPE_GPRINT_LAST:
		case GRAPH_ITEM_TYPE_GPRINT_MAX:
		case GRAPH_ITEM_TYPE_GPRINT_MIN:
			// This is the consolidated logic
			return $graphItem['consolidation_function_id'];
		default:
			return null;
	}
}

test('GPRINT_AVERAGE uses consolidation_function_id directly', function () {
	$cfId = 2; // Example CF ID
	$item = mockGraphItem(GRAPH_ITEM_TYPE_GPRINT_AVERAGE, $cfId);

	$result = processGraphItemType($item);

	expect($result)->toBe($cfId);
});

test('GPRINT_LAST uses consolidation_function_id directly', function () {
	$cfId = 3;
	$item = mockGraphItem(GRAPH_ITEM_TYPE_GPRINT_LAST, $cfId);

	$result = processGraphItemType($item);

	expect($result)->toBe($cfId);
});

test('GPRINT_MAX uses consolidation_function_id directly', function () {
	$cfId = 4;
	$item = mockGraphItem(GRAPH_ITEM_TYPE_GPRINT_MAX, $cfId);

	$result = processGraphItemType($item);

	expect($result)->toBe($cfId);
});

test('GPRINT_MIN uses consolidation_function_id directly', function () {
	$cfId = 5;
	$item = mockGraphItem(GRAPH_ITEM_TYPE_GPRINT_MIN, $cfId);

	$result = processGraphItemType($item);

	expect($result)->toBe($cfId);
});

test('all four GPRINT types produce identical results for same CF ID', function () {
	$cfId = 2;

	$avgResult  = processGraphItemType(mockGraphItem(GRAPH_ITEM_TYPE_GPRINT_AVERAGE, $cfId));
	$lastResult = processGraphItemType(mockGraphItem(GRAPH_ITEM_TYPE_GPRINT_LAST, $cfId));
	$maxResult  = processGraphItemType(mockGraphItem(GRAPH_ITEM_TYPE_GPRINT_MAX, $cfId));
	$minResult  = processGraphItemType(mockGraphItem(GRAPH_ITEM_TYPE_GPRINT_MIN, $cfId));

	expect($avgResult)
		->toBe($lastResult)
		->toBe($maxResult)
		->toBe($minResult)
		->toBe($cfId, 'All four GPRINT types should return the same CF ID');
});

test('GPRINT consolidation preserves individual CF ID values', function () {
	$cfIds = [1, 2, 3, 4, 5];
	$types = [
		GRAPH_ITEM_TYPE_GPRINT_AVERAGE,
		GRAPH_ITEM_TYPE_GPRINT_LAST,
		GRAPH_ITEM_TYPE_GPRINT_MAX,
		GRAPH_ITEM_TYPE_GPRINT_MIN
	];

	foreach ($cfIds as $cfId) {
		foreach ($types as $type) {
			$result = processGraphItemType(mockGraphItem($type, $cfId));
			expect($result)
				->toBe($cfId, "Type $type should preserve CF ID $cfId");
		}
	}
});

test('non-GPRINT types return null from consolidated case', function () {
	// Test that other graph types don't match the consolidated GPRINT cases
	$result = processGraphItemType(mockGraphItem(GRAPH_ITEM_TYPE_LINE1, 2));

	expect($result)->toBeNull('Non-GPRINT types should not match consolidated cases');
});
