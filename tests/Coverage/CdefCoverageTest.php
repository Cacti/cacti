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

beforeEach(function () : void {
	$GLOBALS['cdef_test_items']   = [];
	$GLOBALS['cdef_test_lists']   = [];
	$GLOBALS['cdef_test_names']   = [];
	$GLOBALS['cdef_test_queries'] = [];
	$GLOBALS['cdef_test_logs']    = [];
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
		7 => ['type' => '1', 'value' => 999],
		8 => ['type' => '2', 'value' => 999],
		9 => ['type' => '1'],
		10 => ['type' => '5', 'value' => 999],
	];
	$GLOBALS['cdef_test_names'][42] = 'Nested CDEF';

	expect(get_cdef_item_name(1))->toBe('Maximum')
		->and(get_cdef_item_name(2))->toBe('*')
		->and(get_cdef_item_name(3))->toBe('CURRENT_DATA_SOURCE')
		->and(get_cdef_item_name(4))->toBe('Nested CDEF')
		->and(get_cdef_item_name(5))->toBe('8')
		->and(get_cdef_item_name(6))->toBeNull()
		->and(get_cdef_item_name(7))->toBeNull()
		->and(get_cdef_item_name(8))->toBeNull()
		->and(get_cdef_item_name(9))->toBeNull()
		->and(get_cdef_item_name(10))->toBeNull()
		->and(get_cdef_item_name(999))->toBeNull();

	$messages = implode("\n", array_column($GLOBALS['cdef_test_logs'], 0));
	expect($messages)->toContain('unknown type')
		->and($messages)->toContain('unknown function')
		->and($messages)->toContain('unknown operator')
		->and($messages)->toContain('missing definition')
		->and($messages)->toContain('missing or corrupt');

	expect($GLOBALS['cdef_test_queries'][0][1])->toBe([1])
		->and($GLOBALS['cdef_test_queries'][4][1])->toBe([42]);
});

test('CDEF resolution handles empty, ordered, and recursively nested definitions', function () : void {
	$GLOBALS['cdef_test_items'] = [
		10 => ['type' => '4', 'value' => 'CURRENT_DATA_SOURCE'],
		11 => ['type' => '6', 'value' => '8'],
		12 => ['type' => '2', 'value' => 3],
		20 => ['type' => '6', 'value' => '2'],
		7  => ['type' => '1', 'value' => 999],
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
		4 => [['id' => 0, 'type' => '5', 'value' => '999']],
		5 => [['id' => 0, 'type' => '5', 'value' => '5']],
		6 => [['id' => 0, 'type' => '5', 'value' => '7']],
		7 => [['id' => 0, 'type' => '5', 'value' => '6']],
		8 => [['id' => 7, 'type' => '1', 'value' => '999']],
		9 => [
			['id' => 0, 'type' => '5', 'value' => '2'],
			['id' => 0, 'type' => '5', 'value' => '2'],
		],
	];

	expect(get_cdef(1))->toBe('')
		->and(get_cdef(2))->toBe('CURRENT_DATA_SOURCE,8,*')
		->and(get_cdef(3))->toBe('CURRENT_DATA_SOURCE,8,*,2')
		->and(get_cdef(4))->toBe('')
		->and(get_cdef(5))->toBe('')
		->and(get_cdef(6))->toBe('')
		->and(get_cdef(8))->toBe('')
		->and(get_cdef(9))->toBe('CURRENT_DATA_SOURCE,8,*,CURRENT_DATA_SOURCE,8,*');

	expect($GLOBALS['cdef_test_queries'][0][1])->toBe([1])
		->and($GLOBALS['cdef_test_queries'][0][0])->toContain('ORDER BY sequence');

	$messages = implode("\n", array_column($GLOBALS['cdef_test_logs'], 0));
	expect($messages)->toContain('recursive cycle')
		->and($messages)->toContain('unknown function');
});

test('CDEF resolution rejects nesting deeper than the safety limit', function () : void {
	for ($id = 1; $id <= 65; $id++) {
		$GLOBALS['cdef_test_lists'][$id] = [['id' => 0, 'type' => '5', 'value' => $id + 1]];
	}

	expect(get_cdef(1))->toBe('')
		->and($GLOBALS['cdef_test_logs'][0][0])->toContain('nesting depth');
});

test('CDEF resolution accepts the maximum safe nesting depth', function () : void {
	$GLOBALS['cdef_test_items'][10] = ['type' => '4', 'value' => 'CURRENT_DATA_SOURCE'];

	for ($id = 1000; $id < 1063; $id++) {
		$GLOBALS['cdef_test_lists'][$id] = [['id' => 0, 'type' => '5', 'value' => $id + 1]];
	}

	$GLOBALS['cdef_test_lists'][1063] = [['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE']];

	expect(get_cdef(1000))->toBe('CURRENT_DATA_SOURCE');
});

test('CDEF resolution rejects an excessive total expansion count', function () : void {
	$GLOBALS['cdef_test_items'][10] = ['type' => '4', 'value' => 'CURRENT_DATA_SOURCE'];

	for ($id = 100; $id < 113; $id++) {
		$GLOBALS['cdef_test_lists'][$id] = [
			['id' => 0, 'type' => '5', 'value' => $id + 1],
			['id' => 0, 'type' => '5', 'value' => $id + 1],
		];
	}

	$GLOBALS['cdef_test_lists'][113] = [['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE']];

	expect(get_cdef(100))->toBe('')
		->and($GLOBALS['cdef_test_logs'])->not->toBeEmpty()
		->and($GLOBALS['cdef_test_logs'][count($GLOBALS['cdef_test_logs']) - 1][0])->toContain('expansion budget');
});
