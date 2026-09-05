<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$GLOBALS['cdef_test_items']   = [];
$GLOBALS['cdef_test_lists']   = [];
$GLOBALS['cdef_test_names']   = [];
$GLOBALS['cdef_test_queries'] = [];
$GLOBALS['cdef_functions']    = [];
$GLOBALS['cdef_operators']    = [];

function db_fetch_row_prepared(string $sql, array $params = []) : array|false {
	$GLOBALS['cdef_test_queries'][] = [$sql, $params];

	return $GLOBALS['cdef_test_items'][(int) ($params[0] ?? 0)] ?? false;
}

function db_fetch_assoc_prepared(string $sql, array $params = []) : array|false {
	$GLOBALS['cdef_test_queries'][] = [$sql, $params];

	return $GLOBALS['cdef_test_lists'][(int) ($params[0] ?? 0)] ?? [];
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	$GLOBALS['cdef_test_queries'][] = [$sql, $params];

	return $GLOBALS['cdef_test_names'][(int) ($params[0] ?? 0)] ?? false;
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

require_once dirname(__DIR__, 2) . '/lib/cdef.php';

beforeEach(function () : void {
	$GLOBALS['cdef_test_items']   = [];
	$GLOBALS['cdef_test_lists']   = [];
	$GLOBALS['cdef_test_names']   = [];
	$GLOBALS['cdef_test_queries'] = [];
	$GLOBALS['cdef_functions']    = [7 => 'Maximum'];
	$GLOBALS['cdef_operators']    = [3 => '*'];
});

test('CDEF item names cover every supported item type and the unknown fallback', function () : void {
	$GLOBALS['cdef_test_items'] = [
		1 => ['type' => '1', 'value' => 7],
		2 => ['type' => '2', 'value' => 3],
		3 => ['type' => '4', 'value' => 'CURRENT_DATA_SOURCE'],
		4 => ['type' => '5', 'value' => 42],
		5 => ['type' => '6', 'value' => '8'],
		6 => ['type' => '99', 'value' => 'ignored'],
	];
	$GLOBALS['cdef_test_names'][42] = 'Nested CDEF';

	expect(get_cdef_item_name(1))->toBe('Maximum')
		->and(get_cdef_item_name(2))->toBe('*')
		->and(get_cdef_item_name(3))->toBe('CURRENT_DATA_SOURCE')
		->and(get_cdef_item_name(4))->toBe('Nested CDEF')
		->and(get_cdef_item_name(5))->toBe('8')
		->and(get_cdef_item_name(6))->toBeNull();

	expect($GLOBALS['cdef_test_queries'][0][1])->toBe([1])
		->and($GLOBALS['cdef_test_queries'][4][1])->toBe([42]);
});

test('CDEF resolution handles empty, ordered, and recursively nested definitions', function () : void {
	$GLOBALS['cdef_test_items'] = [
		10 => ['type' => '4', 'value' => 'CURRENT_DATA_SOURCE'],
		11 => ['type' => '6', 'value' => '8'],
		12 => ['type' => '2', 'value' => 3],
		20 => ['type' => '6', 'value' => '2'],
	];
	$GLOBALS['cdef_test_lists'] = [
		1 => [],
		2 => [
			['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'],
			['id' => 11, 'type' => '6', 'value' => '8'],
			['id' => 12, 'type' => '2', 'value' => '3'],
		],
		3 => [
			['id' => 0, 'type' => '5', 'value' => '2'],
			['id' => 20, 'type' => '6', 'value' => '2'],
		],
	];

	expect(get_cdef(1))->toBe('')
		->and(get_cdef(2))->toBe('CURRENT_DATA_SOURCE,8,*')
		->and(get_cdef(3))->toBe('CURRENT_DATA_SOURCE,8,*,2');

	expect($GLOBALS['cdef_test_queries'][0][1])->toBe([1]);
});
