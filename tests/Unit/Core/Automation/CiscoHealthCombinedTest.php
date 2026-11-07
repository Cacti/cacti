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

require_once dirname(__DIR__, 4) . '/lib/cisco_health_graphs.php';

test('combined memory keeps separate named pools in natural index order', function () {
	$rows = [
	 ['id'=>4, 'snmp_index'=>'10', 'data_source_name'=>'free', 'label'=>'I/O'],
	 ['id'=>2, 'snmp_index'=>'2', 'data_source_name'=>'free', 'label'=>'Processor'],
	 ['id'=>3, 'snmp_index'=>'10', 'data_source_name'=>'used', 'label'=>'I/O'],
	 ['id'=>1, 'snmp_index'=>'2', 'data_source_name'=>'used', 'label'=>'Processor'],
	];
	$pools = cisco_health_graph_plan($rows,false);
	expect(array_keys($pools))->toBe([2, 10]);
	expect($pools[2]['name'])->toBe('Processor');
	expect($pools[2]['sources'])->toBe(['free'=>2, 'used'=>1]);
	expect($pools[10]['sources'])->toBe(['free'=>4, 'used'=>3]);
});

test('combined memory refuses ambiguous duplicate sources', function () {
	$row = ['id'=>1, 'snmp_index'=>'7.1', 'data_source_name'=>'used', 'label'=>'Processor'];
	expect(fn () =>cisco_health_graph_plan([$row, $row],false))->toThrow(RuntimeException::class,'Multiple data sources');
});

test('combined memory refuses incomplete used and free pairs', function () {
	expect(fn () =>cisco_health_graph_plan([['id'=>1, 'snmp_index'=>'7.1', 'data_source_name'=>'used', 'label'=>'Processor']],false))->toThrow(RuntimeException::class,'missing its used/free pair');
});

test('combined temperature handles missing names and ignores unrelated metrics', function () {
	$pools = cisco_health_graph_plan([
	 ['id'=>7, 'snmp_index'=>'5', 'data_source_name'=>'temperature', 'label'=>''],
	 ['id'=>8, 'snmp_index'=>'6', 'data_source_name'=>'voltage', 'label'=>'Voltage'],
	],true);
	expect($pools)->toBe([5=>['name'=>'Index 5', 'sources'=>['temperature'=>7]]]);
	expect(cisco_health_graph_plan([],false))->toBe([]);
});
