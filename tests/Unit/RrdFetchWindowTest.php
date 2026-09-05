<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once CACTI_PATH_LIBRARY . '/rrd.php';

test('fetch output is bounded by the requested end and reports its observed step', function () {
	$output = " traffic_in traffic_out\n" .
		"1700000400: 1.0 2.0\n" .
		"1700000700: 3.0 4.0\n" .
		"1700001000: 5.0 6.0\n" .
		"1700001300: 7.0 8.0\n";

	$fetch = rrdtool_parse_fetch_output($output, 1700001000);

	expect($fetch['data_source_names'])->toBe(['traffic_in', 'traffic_out'])
		->and($fetch['timestamp'])->toBe([
			'start_time' => 1700000400,
			'end_time'   => 1700001000,
			'step'       => 300,
		])
		->and(array_keys($fetch['values'][0]))->toBe([1700000400, 1700000700, 1700001000])
		->and($fetch['values'][1][1700001000])->toBe('6.0')
		->and($fetch['values'][0])->not->toHaveKey(1700001300);
});

test('fetch output handles unknown, malformed, sparse, and empty rows safely', function () {
	$output = "value\n" .
		"not-a-time: 9\n" .
		"1700000400: NaN\n" .
		"malformed row\n" .
		"1700001000: -NaN\n";

	$with_unknown = rrdtool_parse_fetch_output($output, 1700001000, true);
	$without      = rrdtool_parse_fetch_output($output, 1700001000);
	$empty        = rrdtool_parse_fetch_output("\n\n", 1700001000);

	expect($with_unknown['values'][0])->toBe([
		1700000400 => 'U',
		1700001000 => 'U',
	])
		->and($with_unknown['timestamp']['step'])->toBe(600)
		->and($without)->not->toHaveKey('values')
		->and($empty)->toBe([]);
});

test('a single in-window row has an unknown observed step', function () {
	$fetch = rrdtool_parse_fetch_output("value\n1700000400: 1\n1700000700: 2\n", 1700000400);

	expect($fetch['timestamp'])->toBe([
		'start_time' => 1700000400,
		'end_time'   => 1700000400,
		'step'       => 0,
	]);
});
