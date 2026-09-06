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

namespace PercentileResolutionBehaviorTest;

$savedGlobals = [];

function cacti_sizeof(array $value) : int {
	return count($value);
}

function is_graphable_item(string $type) : bool {
	return $type == 'LINE1';
}

function nth_percentile(mixed $ids, int $start, int $end, int $percentile, int $resolution, bool $peak = false) : array {
	$GLOBALS['percentile_fetch_requests'][] = [$ids, $start, $end, $percentile, $resolution, $peak];

	return [
		'traffic'                        => 10,
		'nth_percentile_maximum'         => 20,
		'nth_percentile_sum'             => 30,
		'nth_percentile_aggregate_total' => 40,
	];
}

$source = file_get_contents(CACTI_PATH_LIBRARY . '/graph_variables.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/graph_variables.php for percentile tests.');
}

if (preg_match('/function variable_nth_percentile\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract variable_nth_percentile() for tests.');
}

eval('namespace PercentileResolutionBehaviorTest;' . $matches[0]); // nosemgrep: php.lang.security.eval-use.eval-use

beforeEach(function () use (&$savedGlobals) {
	foreach (['graph_item_types', 'percentile_fetch_requests'] as $name) {
		$savedGlobals[$name] = [
			'exists' => array_key_exists($name, $GLOBALS),
			'value'  => $GLOBALS[$name] ?? null,
		];
	}

	$GLOBALS['graph_item_types']          = [1 => 'LINE1'];
	$GLOBALS['percentile_fetch_requests'] = [];
});

afterEach(function () use (&$savedGlobals) {
	foreach ($savedGlobals as $name => $saved) {
		if ($saved['exists']) {
			$GLOBALS[$name] = $saved['value'];
		} else {
			unset($GLOBALS[$name]);
		}
	}

	$savedGlobals = [];
});

test('every percentile mode forwards the graph RRA resolution', function (string $type, bool $peak) {
	$match = ['token', 95, 'bytes', 0, $type, 2];
	$graph = ['base_value' => 1000];
	$item  = [
		'local_data_id'        => 7,
		'data_source_name'     => 'traffic',
		'data_template_rrd_id' => 11,
		'graph_type_id'        => 1,
	];
	$items = [$item];

	$result = variable_nth_percentile($match, $graph, $item, $items, 100, 1000, 1800);

	expect($result)->toBeString()
		->and($GLOBALS['percentile_fetch_requests'])->toHaveCount(1)
		->and($GLOBALS['percentile_fetch_requests'][0][1])->toBe(100)
		->and($GLOBALS['percentile_fetch_requests'][0][2])->toBe(1000)
		->and($GLOBALS['percentile_fetch_requests'][0][3])->toBe(95)
		->and($GLOBALS['percentile_fetch_requests'][0][4])->toBe(1800)
		->and($GLOBALS['percentile_fetch_requests'][0][5])->toBe($peak);
})->with([
	['current', false],
	['max', true],
	['total', false],
	['all_max_current', false],
	['total_peak', true],
	['all_max_peak', true],
	['aggregate', false],
	['aggregate_sum', false],
	['aggregate_peak', true],
	['aggregate_max', true],
	['aggregate_sum_peak', true],
	['aggregate_current', false],
	['aggregate_current_peak', true],
]);

test('invalid and empty percentile tokens fail without fetching', function () {
	$graph = ['base_value' => 1000];
	$item  = [];
	$items = [];
	$empty = [];
	$bad   = ['token', 0, 'bytes', 0, 'current', 2];

	expect(variable_nth_percentile($empty, $graph, $item, $items, 100, 1000, 300))->toBe('0')
		->and(variable_nth_percentile($bad, $graph, $item, $items, 100, 1000, 300))->toBe('-1')
		->and($GLOBALS['percentile_fetch_requests'])->toBe([]);
});
