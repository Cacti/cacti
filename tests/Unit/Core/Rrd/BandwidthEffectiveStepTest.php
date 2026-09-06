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

namespace BandwidthEffectiveStepTest;

$GLOBALS['bandwidth_fetch_result'] = [];

function rrdtool_function_fetch(...$arguments) : array {
	return $GLOBALS['bandwidth_fetch_result'];
}

function cacti_count(array $value) : int {
	return count($value);
}

$source = file_get_contents(CACTI_PATH_LIBRARY . '/graph_variables.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/graph_variables.php for bandwidth tests.');
}

if (preg_match('/function bandwidth_summation\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract bandwidth_summation() for tests.');
}

eval('namespace BandwidthEffectiveStepTest;' . $matches[0]); // nosemgrep: php.lang.security.eval-use.eval-use

beforeEach(function () {
	$GLOBALS['bandwidth_fetch_result'] = [
		'data_source_names' => ['traffic'],
		'timestamp'         => ['step' => 1800],
		'values'            => [[100 => 2.0, 1900 => 3.0]],
	];
});

test('bandwidth summation uses the interval observed in fetched rows', function () {
	expect(bandwidth_summation(1, 0, 3600, 300, 1))->toBe(['traffic' => 9000.0]);
});

test('bandwidth summation falls back to the requested interval when one row cannot reveal a step', function () {
	$GLOBALS['bandwidth_fetch_result']['timestamp']['step'] = 0;

	expect(bandwidth_summation(1, 0, 3600, 300, 2))->toBe(['traffic' => 3000.0]);
});

test('bandwidth summation handles empty names values and samples', function () {
	$GLOBALS['bandwidth_fetch_result'] = [];
	expect(bandwidth_summation(1, 0, 3600, 300, 1))->toBe([]);

	$GLOBALS['bandwidth_fetch_result'] = [
		'data_source_names' => ['missing', 'empty'],
		'timestamp'         => ['step' => 300],
		'values'            => [1 => []],
	];

	expect(bandwidth_summation(1, 0, 3600, 300, 1))->toBe(['empty' => 0]);
});
