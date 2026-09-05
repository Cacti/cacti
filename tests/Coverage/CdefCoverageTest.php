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
		1  => ['type' => '1', 'value' => 7],
		2  => ['type' => '2', 'value' => 3],
		3  => ['type' => '4', 'value' => 'CURRENT_DATA_SOURCE'],
		4  => ['type' => '5', 'value' => 42],
		5  => ['type' => '6', 'value' => '8'],
		6  => ['type' => '99', 'value' => 'ignored'],
		7  => ['type' => '1', 'value' => 999],
		8  => ['type' => '2', 'value' => 999],
		9  => ['type' => '1'],
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
	$GLOBALS['cdef_test_names'][1] = 1;
	$GLOBALS['cdef_test_lists']    = [
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
		->and(get_cdef(4))->toBeNull()
		->and(get_cdef(5))->toBeNull()
		->and(get_cdef(6))->toBeNull()
		->and(get_cdef(8))->toBeNull()
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

	expect(get_cdef(1))->toBeNull()
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

test('CDEF resolution rejects an excessive expanded output size', function () : void {
	$GLOBALS['cdef_test_items'][10] = ['type' => '4', 'value' => 'CURRENT_DATA_SOURCE'];

	for ($id = 100; $id < 117; $id++) {
		$GLOBALS['cdef_test_lists'][$id] = [
			['id' => 0, 'type' => '5', 'value' => $id + 1],
			['id' => 0, 'type' => '5', 'value' => $id + 1],
		];
	}

	$GLOBALS['cdef_test_lists'][117] = [['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE']];

	expect(get_cdef(100))->toBeNull()
		->and($GLOBALS['cdef_test_logs'])->not->toBeEmpty()
		->and($GLOBALS['cdef_test_logs'][count($GLOBALS['cdef_test_logs']) - 1][0])->toContain('output budget');
});

test('CDEF resolution rejects too many unique definitions', function () : void {
	$GLOBALS['cdef_test_items'][10]   = ['type' => '4', 'value' => 'CURRENT_DATA_SOURCE'];
	$GLOBALS['cdef_test_lists'][2000] = [];

	for ($id = 3000; $id < 7097; $id++) {
		$GLOBALS['cdef_test_lists'][2000][] = ['id' => 0, 'type' => '5', 'value' => $id];
		$GLOBALS['cdef_test_lists'][$id]    = [['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE']];
	}

	expect(get_cdef(2000))->toBeNull()
		->and($GLOBALS['cdef_test_logs'][0][0])->toContain('expansion budget');
});

test('CDEF resolution bounds a single stored item value', function () : void {
	$GLOBALS['cdef_test_items'][40] = ['type' => '6', 'value' => str_repeat('x', 1048577)];
	$GLOBALS['cdef_test_lists'][40] = [['id' => 40, 'type' => '6', 'value' => str_repeat('x', 1048577)]];

	expect(get_cdef(40))->toBeNull()
		->and($GLOBALS['cdef_test_logs'][0][0])->toContain('output budget');
});

test('CDEF resolution bounds cumulative cache memory', function () : void {
	$GLOBALS['cdef_test_items'][50] = ['type' => '6', 'value' => str_repeat('x', 200000)];

	for ($id = 5000; $id < 5063; $id++) {
		$GLOBALS['cdef_test_lists'][$id] = [['id' => 0, 'type' => '5', 'value' => $id + 1]];
	}

	$GLOBALS['cdef_test_lists'][5063] = [['id' => 50, 'type' => '6', 'value' => str_repeat('x', 200000)]];

	expect(get_cdef(5000))->toBeNull()
		->and($GLOBALS['cdef_test_logs'][0][0])->toContain('cache budget');
});

test('empty nested definitions do not introduce empty separators', function () : void {
	$GLOBALS['cdef_test_items'] = [
		10 => ['type' => '4', 'value' => 'CURRENT_DATA_SOURCE'],
		11 => ['type' => '6', 'value' => '8'],
		30 => ['type' => '6', 'value' => ''],
		31 => ['type' => '4', 'value' => ''],
	];
	$GLOBALS['cdef_test_names'][20] = 20;
	$GLOBALS['cdef_test_lists']     = [
		20 => [],
		21 => [['id' => 0, 'type' => '5', 'value' => '20'], ['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE']],
		22 => [['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'], ['id' => 0, 'type' => '5', 'value' => '20'], ['id' => 11, 'type' => '6', 'value' => '8']],
		23 => [['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'], ['id' => 0, 'type' => '5', 'value' => '20']],
		24 => [['id' => 10, 'type' => '4', 'value' => 'CURRENT_DATA_SOURCE'], ['id' => 30, 'type' => '6', 'value' => ''], ['id' => 31, 'type' => '4', 'value' => ''], ['id' => 11, 'type' => '6', 'value' => '8']],
	];

	expect(get_cdef(21))->toBe('CURRENT_DATA_SOURCE')
		->and(get_cdef(22))->toBe('CURRENT_DATA_SOURCE,8')
		->and(get_cdef(23))->toBe('CURRENT_DATA_SOURCE')
		->and(get_cdef(24))->toBe('CURRENT_DATA_SOURCE,8');
});
