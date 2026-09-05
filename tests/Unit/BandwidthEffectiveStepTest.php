<?php

namespace BandwidthEffectiveStepTest;

$GLOBALS['bandwidth_fetch_result'] = array();

function rrdtool_function_fetch() {
	return $GLOBALS['bandwidth_fetch_result'];
}

function cacti_count($value) {
	return count($value);
}

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/graph_variables.php');

if ($source === false || preg_match('/function bandwidth_summation\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract bandwidth_summation() for tests.');
}

eval('namespace BandwidthEffectiveStepTest;' . $matches[0]);

test('bandwidth uses the observed step and falls back when it cannot be inferred', function () {
	$GLOBALS['bandwidth_fetch_result'] = array(
		'data_source_names' => array('traffic'),
		'timestamp'         => array('step' => 1800),
		'values'            => array(array(100 => 2.0, 1900 => 3.0)),
	);

	expect(bandwidth_summation(1, 0, 3600, 300, 1))->toBe(array('traffic' => 9000.0));

	$GLOBALS['bandwidth_fetch_result']['timestamp']['step'] = 0;
	expect(bandwidth_summation(1, 0, 3600, 300, 2))->toBe(array('traffic' => 3000.0));
});

test('bandwidth handles missing and empty samples', function () {
	$GLOBALS['bandwidth_fetch_result'] = array();
	expect(bandwidth_summation(1, 0, 3600, 300, 1))->toBe(array());

	$GLOBALS['bandwidth_fetch_result'] = array(
		'data_source_names' => array('missing', 'empty'),
		'timestamp'         => array('step' => 300),
		'values'            => array(1 => array()),
	);

	expect(bandwidth_summation(1, 0, 3600, 300, 1))->toBe(array('empty' => 0));
});
