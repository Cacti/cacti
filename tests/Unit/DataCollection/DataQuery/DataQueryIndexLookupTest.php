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

namespace Cacti\Test\DataQueryIndexLookup;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 4);
foreach (array('data_query_index_lookup', 'data_query_index_key', 'data_query_index_exists') as $name) {
	\cacti_fixture_source_function($root . '/lib/data_query.php', $name, __NAMESPACE__);
}

test('index lookups preserve loose numeric equality without rounding away distinct integers', function () {
	$values = array('0', '-0', '00', '0e20', '1', '01', '1.0', '1e0', ' 1 ', '+1', '-1',
		'1.2', '1.20', '1.2.3', '01.2.3', 'eth0', 'Eth0', '', ' ', '0x10',
		'9007199254740992', '9007199254740993', '9223372036854775807', '9223372036854775808',
		'1e999', '2e999', '-1e999', 'INF', 'NAN', '1e-999', '0.00000000000001');
	foreach ($values as $value) {
		$lookup = data_query_index_lookup(array($value));
		foreach ($values as $needle) {
			expect(data_query_index_exists($needle, $lookup))->toBe(in_array($needle, array($value)));
		}
	}
});

test('duplicate buckets and mixed index sets match the previous membership contract', function () {
	$values = array('9007199254740992', '9007199254740993', '0001', '1', '1.2.3', 'eth0', 'eth0');
	$lookup = data_query_index_lookup($values);
	foreach (array_merge($values, array('9007199254740994', '1e0', '01.2.3', 'ETH0', '')) as $needle) {
		expect(data_query_index_exists($needle, $lookup))->toBe(in_array($needle, $values));
	}
});

test('large ordinary index tables retain exact present and absent results', function () {
	$values = array_map('strval', range(1, 50000));
	$lookup = data_query_index_lookup($values);
	foreach (array('0', '1', '01', '100', '25000', '50000', '50001', '1.2.3') as $needle) {
		expect(data_query_index_exists($needle, $lookup))->toBe(in_array($needle, $values));
	}
});
