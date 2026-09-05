<?php

namespace RrdFetchWindowTest;

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/rrd.php');

if ($source === false || preg_match('/function rrdtool_parse_fetch_output\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract rrdtool_parse_fetch_output() for tests.');
}

eval('namespace RrdFetchWindowTest;' . $matches[0]);

test('fetch output is bounded by the requested end and reports its observed step', function () {
	$output = " traffic_in traffic_out\n" .
		"1700000400: 1.0 2.0\n" .
		"1700000700: 3.0 4.0\n" .
		"1700001000: 5.0 6.0\n" .
		"1700001300: 7.0 8.0\n";

	$fetch = rrdtool_parse_fetch_output($output, 1700001000);

	expect($fetch['data_source_names'])->toBe(array('traffic_in', 'traffic_out'))
		->and($fetch['timestamp'])->toBe(array(
			'start_time' => 1700000400,
			'end_time'   => 1700001000,
			'step'       => 300,
		))
		->and(array_keys($fetch['values'][0]))->toBe(array(1700000400, 1700000700, 1700001000))
		->and($fetch['values'][0])->not->toHaveKey(1700001300);
});

test('fetch parser covers unknown malformed empty and single-row output', function () {
	$output = "value\nnot-a-time: 9\n1700000400: NaN\nmalformed\n1700001000: -NaN\n";

	$with_unknown = rrdtool_parse_fetch_output($output, 1700001000, true);
	$without      = rrdtool_parse_fetch_output($output, 1700001000);
	$single       = rrdtool_parse_fetch_output("value\n1700000400: 1\n1700000700: 2\n", 1700000400);

	expect($with_unknown['values'][0])->toBe(array(1700000400 => 'U', 1700001000 => 'U'))
		->and($with_unknown['timestamp']['step'])->toBe(600)
		->and($without)->not->toHaveKey('values')
		->and($single['timestamp']['step'])->toBe(0)
		->and(rrdtool_parse_fetch_output("\n\n", 1700001000))->toBe(array());
});
