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

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/Helpers/CiscoHealthTransactionFixture.php';
foreach (['cisco_health_graph_kinds', 'cisco_health_graph_plan', 'cisco_health_graph_save', 'cisco_health_graph_build'] as $function) {
	cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/cisco_health_graphs.php', $function, 'CactiTests\\CiscoHealthTransaction; use InvalidArgumentException');
}

test('combined validation preserves the original error and releases its lock', function () {
	$used = ['id'=>1, 'snmp_index'=>'7.1', 'data_source_name'=>'used', 'label'=>'Processor'];
	$free = ['id'=>2, 'snmp_index'=>'7.1', 'data_source_name'=>'free', 'label'=>'Processor'];
	foreach ([
		[[$used, $used], true, 'Multiple data sources', 0],
		[[$used], true, 'missing its used/free pair', 0],
		[[$used, $free], false, 'Cannot begin graph transaction', 0],
		[[$used, $free], true, 'Import the Cisco health graph templates first', 1],
	] as [$rows, $begin, $message, $rollbacks]) {
		\CactiTests\CiscoHealthTransaction\State::$rows = $rows;
		\CactiTests\CiscoHealthTransaction\State::$begin = $begin;
		\CactiTests\CiscoHealthTransaction\State::$rollbacks = 0;
		\CactiTests\CiscoHealthTransaction\State::$releases = 0;
		expect(fn () => \CactiTests\CiscoHealthTransaction\cisco_health_graph_build(1, 'memory', true))->toThrow(RuntimeException::class, $message);
		expect(\CactiTests\CiscoHealthTransaction\State::$rollbacks)->toBe($rollbacks);
		expect(\CactiTests\CiscoHealthTransaction\State::$releases)->toBe(1);
	}
});


test('combined creation preserves display settings and inserts four items per new sensor', function () {
	$state = \CactiTests\CiscoHealthTransaction\State::class;
	$state::$normal = true;
	$state::$identity = true;
	$state::$begin = true;
	$state::$items = [];
	$state::$rows = [];
	$state::$writes = 0;
	try {
		for ($sensor = 1; $sensor <= 100; $sensor++) {
			$state::$rows[] = ['id'=>$sensor, 'snmp_index'=>(string)$sensor, 'data_source_name'=>'temperature', 'label'=>'Sensor ' . $sensor];
			\CactiTests\CiscoHealthTransaction\cisco_health_graph_build(1, 'temperature', true);
		}
		expect($state::$writes)->toBe(400);
		expect($state::$base['image_format_id'])->toBe(1);
		expect($state::$base['width'])->toBe(500);
		expect($state::$base['height'])->toBe(150);
		$state::$identity = false;
		expect(fn () => \CactiTests\CiscoHealthTransaction\cisco_health_graph_build(1, 'temperature', true))->toThrow(RuntimeException::class, 'refusing to overwrite');
		expect($state::$writes)->toBe(400);
	} finally {
		$state::$normal = false;
		$state::$identity = true;
	}
});

cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/cisco_health_graphs.php', 'cisco_health_graph_percentage', 'CactiTests\\CiscoHealthTransaction');

test('memory percentages follow their own sources after earlier pools are deleted', function () {
	$used = ['local_data_id'=>20, 'data_template_rrd_id'=>30, 'data_source_name'=>'used'];
	$free = ['local_data_id'=>20, 'data_template_rrd_id'=>31, 'data_source_name'=>'free'];
	$other = ['local_data_id'=>10, 'data_template_rrd_id'=>10, 'data_source_name'=>'used'];
	$before = \CactiTests\CiscoHealthTransaction\cisco_health_graph_percentage($used, [$other, $used, $free], [10=>[1=>0], 30=>[1=>2], 31=>[1=>3]], 1);
	$after = \CactiTests\CiscoHealthTransaction\cisco_health_graph_percentage($used, [$used, $free], [30=>[1=>0], 31=>[1=>1]], 1);
	expect($before)->toBe('c,d,+,0,GT,c,c,d,+,/,100,*,UNKN,IF');
	expect($after)->toBe('a,b,+,0,GT,a,a,b,+,/,100,*,UNKN,IF');
	expect(\CactiTests\CiscoHealthTransaction\cisco_health_graph_percentage($used, [$other, $used], [10=>[1=>0], 30=>[1=>1]], 1))->toBe('b,UNKN,*');
});


test('percentage lookup reuses a render-local pool index', function () {
	$used = ['local_data_id'=>3, 'data_template_rrd_id'=>30, 'data_source_name'=>'used'];
	$free = ['local_data_id'=>3, 'data_template_rrd_id'=>31, 'data_source_name'=>'free'];
	$index = null;
	$defs = [30=>[1=>0], 31=>[1=>1]];
	$first = \CactiTests\CiscoHealthTransaction\cisco_health_graph_percentage($used, [$used, $free], $defs, 1, $index);
	// Once built, subsequent CDEFs resolve without rescanning the supplied list.
	expect(\CactiTests\CiscoHealthTransaction\cisco_health_graph_percentage($used, [], $defs, 1, $index))->toBe($first);
	expect($index[3])->toHaveCount(2);
});


test('failed updates returning an existing ID roll back combined graph creation', function () {
	$state = \CactiTests\CiscoHealthTransaction\State::class;
	$state::$normal = true;
	$state::$identity = true;
	$state::$begin = true;
	$state::$failSave = true;
	$state::$rollbacks = 0;
	$state::$rows = [['id'=>1, 'snmp_index'=>'1', 'data_source_name'=>'temperature', 'label'=>'CPU']];
	try {
		expect(fn () => \CactiTests\CiscoHealthTransaction\cisco_health_graph_build(1, 'temperature', true))->toThrow(RuntimeException::class, 'Could not save');
		expect($state::$rollbacks)->toBe(1);
	} finally {
		$state::$failSave = false;
		$state::$normal = false;
		$GLOBALS['database_last_error'] = '';
	}
});


test('incremental creation loads only the arriving sensor and preserves existing item sequences', function () {
	$state = \CactiTests\CiscoHealthTransaction\State::class;
	$state::$normal = true;
	$state::$identity = true;
	$state::$begin = true;
	$state::$rows = [];
	$state::$items = [];
	$state::$writes = 0;
	$state::$loaded = 0;
	try {
		for ($sensor = 1; $sensor <= 100; $sensor++) {
			$state::$rows[] = ['id'=>$sensor, 'snmp_index'=>(string)$sensor, 'data_source_name'=>'temperature', 'label'=>'Sensor ' . $sensor];
			\CactiTests\CiscoHealthTransaction\cisco_health_graph_build(1, 'temperature', true, $sensor);
		}
		expect($state::$writes)->toBe(400);
		expect($state::$loaded)->toBe(100);
		expect(array_column($state::$items, 'sequence'))->toBe(range(1, 400));
		\CactiTests\CiscoHealthTransaction\cisco_health_graph_build(1, 'temperature', true, 100);
		expect($state::$writes)->toBe(400);
		expect($state::$loaded)->toBe(105);
	} finally { $state::$normal = false; }
});


test('nested CDEFs expand every complete memory token without changing surrounding RPN', function () {
 $token='CISCO_HEALTH_USED_PERCENT';
 $rpn='a,a,b,+,/,100,*';
 expect(cisco_health_graph_expand_percentage($token.',100,*',$rpn))->toBe($rpn.',100,*');
 expect(cisco_health_graph_expand_percentage($token.','.$token.',+',$rpn))->toBe($rpn.','.$rpn.',+');
 expect(cisco_health_graph_expand_percentage('OTHER_'.$token.',100,*',$rpn))->toBe('OTHER_'.$token.',100,*');
 expect(cisco_health_graph_expand_percentage($token.',100,*','a,UNKN,*'))->toBe('a,UNKN,*,100,*');
});


require_once dirname(__DIR__, 3) . '/Helpers/CiscoHealthRetryFixture.php';
cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/cisco_health_graphs.php', 'cisco_health_graph_retry_existing', 'CactiTests\\CiscoHealthRetry');

test('automation retries a failed combined view on a later pass without deleting individual graphs', function () {
	$state = \CactiTests\CiscoHealthRetry\State::class;
	$state::$exists = false;
	$state::$fail = true;
	$state::$builds = [];
	$attempted = false;
	for ($i=0; $i<100; $i++) {
		\CactiTests\CiscoHealthRetry\cisco_health_graph_retry_existing(1,7,[],$attempted);
	}
	expect($state::$builds)->toBe([[1,'memory',true]]);
	$state::$fail = false;
	$attempted = false;
	\CactiTests\CiscoHealthRetry\cisco_health_graph_retry_existing(1,7,[],$attempted);
	expect($state::$exists)->toBeTrue();
	expect($state::$builds)->toHaveCount(2);
	$attempted = false;
	\CactiTests\CiscoHealthRetry\cisco_health_graph_retry_existing(1,7,[],$attempted);
	\CactiTests\CiscoHealthRetry\cisco_health_graph_retry_existing(1,900,[],$attempted);
	expect($state::$builds)->toHaveCount(2);
});

test('memory percentage uses the selected DEF consolidation rather than a GPRINT requested function', function () {
	$used = ['local_data_id'=>20,'data_template_rrd_id'=>30,'data_source_name'=>'used','consolidation_function_id'=>3];
	$free = ['local_data_id'=>20,'data_template_rrd_id'=>31,'data_source_name'=>'free'];
	foreach (['30:1','30:2'] as $selected) {
		$cf = (int)explode(':',$selected)[1];
		$defs = [30=>[$cf=>0],31=>[$cf=>1]];
		expect(cisco_health_graph_percentage($used,[$used,$free],$defs,$cf))->toBe('a,b,+,0,GT,a,a,b,+,/,100,*,UNKN,IF');
	}
	$source = file_get_contents(dirname(__DIR__,4) . '/lib/rrd.php');
	expect($source)->toContain('(int)$cf_id, $cisco_health_pool_items');
});
