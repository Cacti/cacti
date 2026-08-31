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

namespace RrdResolutionStepQueryTest;

$GLOBALS['resolution_step_queries'] = [];
$GLOBALS['resolution_step_rows']    = [];

function cacti_sizeof(array $value) : int {
	return count($value);
}

function db_fetch_assoc_prepared(string $sql, array $params = []) : array {
	$GLOBALS['resolution_step_queries'][] = [$sql, $params];

	return $GLOBALS['resolution_step_rows'];
}

$source = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/rrd.php for resolution step tests.');
}

if (preg_match('/function rrdtool_function_get_resstep\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract rrdtool_function_get_resstep() for query tests.');
}

$function = str_replace('function rrdtool_function_get_resstep(', 'function rrdtool_function_get_resstep_under_test(', $matches[0]);
eval('namespace RrdResolutionStepQueryTest;' . $function);

beforeEach(function () {
	$GLOBALS['resolution_step_queries'] = [];
	$GLOBALS['resolution_step_rows']    = [];
});

test('resolution profiles for multiple data sources are fetched once', function () {
	$GLOBALS['resolution_step_rows'] = [
		['local_data_id' => 20, 'step' => 60, 'steps' => 5, 'rows' => 1, 'timespan' => 300],
		['local_data_id' => 10, 'step' => 30, 'steps' => 2, 'rows' => 500000000, 'timespan' => 30000000000],
		['local_data_id' => 20, 'step' => 60, 'steps' => 1, 'rows' => 500000000, 'timespan' => 30000000000],
	];

	expect(rrdtool_function_get_resstep_under_test([10, 20, 10], 1, 2))->toBe(60)
		->and($GLOBALS['resolution_step_queries'])->toHaveCount(1)
		->and($GLOBALS['resolution_step_queries'][0][0])->toContain('dtd.local_data_id IN (?, ?)')
		->and($GLOBALS['resolution_step_queries'][0][1])->toBe([10, 20]);
});

test('step mode preserves caller data source order and skips expired archives', function () {
	$GLOBALS['resolution_step_rows'] = [
		['local_data_id' => 4, 'step' => 120, 'steps' => 1, 'rows' => 500000000, 'timespan' => 60000000000],
		['local_data_id' => 3, 'step' => 30, 'steps' => 1, 'rows' => 1, 'timespan' => 30],
	];

	expect(rrdtool_function_get_resstep_under_test([3, 4], 1, 2, 'step'))->toBe(120);
});

test('negative graph times are normalized before retention matching', function () {
	$GLOBALS['resolution_step_rows'] = [
		['local_data_id' => 7, 'step' => 60, 'steps' => 1, 'rows' => 1000, 'timespan' => 60000],
	];

	expect(rrdtool_function_get_resstep_under_test(7, -300, -1))->toBe(60);
});

test('empty and unmatched data source sets return zero', function () {
	expect(rrdtool_function_get_resstep_under_test([], 1, 2))->toBe(0)
		->and($GLOBALS['resolution_step_queries'])->toBe([]);

	$GLOBALS['resolution_step_rows'] = [
		['local_data_id' => 8, 'step' => 60, 'steps' => 1, 'rows' => 1, 'timespan' => 60],
	];

	expect(rrdtool_function_get_resstep_under_test(8, 1, 2))->toBe(0);
});
