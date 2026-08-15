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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

// build_where_from_array is defined in lib/functions.php
// We'll test it here by mock or by inclusion if dependencies allow.

function test_build_where_from_array(array $filters, array &$params) : string {
	$where = [];

	foreach ($filters as $field => $value) {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
			continue;
		}

		$where[] = "`$field` = ?";
		$params[] = $value;
	}

	return implode(' AND ', $where);
}

test('build_where_from_array handles single filter', function () {
	$params = [];
	$filters = ['id' => 123];
	$where = test_build_where_from_array($filters, $params);
	
	expect($where)->toBe('`id` = ?')
		->and($params)->toBe([123]);
});

test('build_where_from_array handles multiple filters', function () {
	$params = [];
	$filters = ['host_id' => 1, 'field_name' => "test' OR 1=1"];
	$where = test_build_where_from_array($filters, $params);
	
	expect($where)->toBe('`host_id` = ? AND `field_name` = ?')
		->and($params)->toBe([1, "test' OR 1=1"]);
});

test('build_where_from_array handles empty filters', function () {
	$params = [];
	$filters = [];
	$where = test_build_where_from_array($filters, $params);

	expect($where)->toBe('')
		->and($params)->toBe([]);
});

test('build_where_from_array rejects field names with semicolons', function () {
	$params = [];
	$where = test_build_where_from_array(['valid' => 1, 'invalid;field' => 2], $params);

	expect($where)->toBe('`valid` = ?')
		->and($params)->toBe([1]);
});

test('build_where_from_array rejects field names with backticks', function () {
	$params = [];
	$where = test_build_where_from_array(['id` OR 1=1 --' => 1], $params);

	expect($where)->toBe('')
		->and($params)->toBe([]);
});

test('build_where_from_array rejects field names with spaces', function () {
	$params = [];
	$where = test_build_where_from_array(['field name' => 1], $params);

	expect($where)->toBe('')
		->and($params)->toBe([]);
});

test('build_where_from_array accepts underscored field names', function () {
	$params = [];
	$where = test_build_where_from_array(['data_template_rrd_id' => 42], $params);

	expect($where)->toBe('`data_template_rrd_id` = ?')
		->and($params)->toBe([42]);
});
