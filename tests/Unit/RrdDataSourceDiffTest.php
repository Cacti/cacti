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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

test('rrdtool_tune_ds_add_spec builds a DS spec with numeric limits', function () {
	expect(rrdtool_tune_ds_add_spec('traffic_in', 'COUNTER', 600, 0, 100))
		->toBe('DS:traffic_in:COUNTER:600:0:100');

	expect(rrdtool_tune_ds_add_spec('load', 'DERIVE', 300, -5, 5))
		->toBe('DS:load:DERIVE:300:-5:5');
});

test('rrdtool_tune_ds_add_spec falls back to U for non numeric limits', function () {
	expect(rrdtool_tune_ds_add_spec('x', 'GAUGE', 300, 'U', 'NaN'))->toBe('DS:x:GAUGE:300:U:U');
	expect(rrdtool_tune_ds_add_spec('x', 'GAUGE', 300, '', ''))->toBe('DS:x:GAUGE:300:U:U');
});

test('rrdtool_tune_ds_add_spec normalizes a 0/0 range to 0:U', function () {
	expect(rrdtool_tune_ds_add_spec('x', 'GAUGE', 300, 0, 0))->toBe('DS:x:GAUGE:300:0:U');
	expect(rrdtool_tune_ds_add_spec('x', 'COUNTER', 600, '0', '0'))->toBe('DS:x:COUNTER:600:0:U');
});

test('rrdtool_cacti_compare_ds reports a missing data source and an add tune', function () {
	$diff  = [];
	$cacti = [
		'in'  => ['type' => 'COUNTER', 'minimal_heartbeat' => 600, 'min' => 0, 'max' => 'U'],
		'out' => ['type' => 'COUNTER', 'minimal_heartbeat' => 600, 'min' => 0, 'max' => 'U'],
	];
	$file  = ['in' => ['type' => 'COUNTER']];

	rrdtool_cacti_compare_ds($diff, '/rra/x.rrd', $cacti, $file);

	expect($diff['ds_count'])->toContain('requires 2');
	expect($diff['ds_count'])->toContain('will be added');
	expect($diff['ds']['out']['error'])->toContain('missing from the RRDfile');
	expect($diff['tune'])->toHaveCount(1);
	expect($diff['tune'][0])->toContain('DS:out:COUNTER:600:0:U');
});

test('rrdtool_cacti_compare_ds reports an extra data source and a DEL tune', function () {
	$diff  = [];
	$cacti = ['in' => ['type' => 'COUNTER', 'minimal_heartbeat' => 600, 'min' => 0, 'max' => 'U']];
	$file  = ['in' => ['type' => 'COUNTER'], 'legacy' => ['type' => 'GAUGE']];

	rrdtool_cacti_compare_ds($diff, '/rra/x.rrd', $cacti, $file);

	expect($diff['ds_count'])->toContain('will be removed');
	expect($diff['ds']['legacy']['error'])->toContain('not required by the Graph');
	expect($diff['tune'])->toHaveCount(1);
	expect($diff['tune'][0])->toContain('DEL:legacy');
});

test('rrdtool_cacti_compare_ds is silent when the data sources match', function () {
	$diff  = [];
	$cacti = ['in' => ['type' => 'COUNTER', 'minimal_heartbeat' => 600, 'min' => 0, 'max' => 'U']];
	$file  = ['in' => ['type' => 'COUNTER']];

	rrdtool_cacti_compare_ds($diff, '/rra/x.rrd', $cacti, $file);

	expect($diff)->toBe([]);
});
