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

$GLOBALS['graph_item_types']          = array(1 => 'LINE1');
$GLOBALS['percentile_fetch_requests'] = array();

function cacti_sizeof($value) {
	return count($value);
}

function is_graphable_item($type) {
	return $type == 'LINE1';
}

function nth_percentile($ids, $start, $end, $percentile, $resolution, $peak = false) {
	$GLOBALS['percentile_fetch_requests'][] = array($ids, $start, $end, $percentile, $resolution, $peak);

	return array(
		'traffic'                        => 10,
		'nth_percentile_maximum'         => 20,
		'nth_percentile_sum'             => 30,
		'nth_percentile_aggregate_total' => 40,
	);
}

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/graph_variables.php');

if ($source === false || preg_match('/function variable_nth_percentile\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract variable_nth_percentile() for tests.');
}

eval('namespace PercentileResolutionBehaviorTest;' . $matches[0]); // nosemgrep: php.lang.security.eval-use.eval-use

beforeEach(function () {
	$GLOBALS['percentile_fetch_requests'] = array();
});

test('every percentile mode forwards the graph RRA resolution', function ($type, $peak) {
	$match = array('token', 95, 'bytes', 0, $type, 2);
	$graph = array('base_value' => 1000);
	$item  = array(
		'local_data_id'        => 7,
		'data_source_name'     => 'traffic',
		'data_template_rrd_id' => 11,
		'graph_type_id'        => 1,
	);
	$items = array($item);

	variable_nth_percentile($match, $graph, $item, $items, 100, 1000, 1800);

	expect($GLOBALS['percentile_fetch_requests'])->toHaveCount(1)
		->and($GLOBALS['percentile_fetch_requests'][0][1])->toBe(100)
		->and($GLOBALS['percentile_fetch_requests'][0][2])->toBe(1000)
		->and($GLOBALS['percentile_fetch_requests'][0][3])->toBe(95)
		->and($GLOBALS['percentile_fetch_requests'][0][4])->toBe(1800)
		->and($GLOBALS['percentile_fetch_requests'][0][5])->toBe($peak);
})->with(array(
	array('current', false),
	array('max', true),
	array('total', false),
	array('all_max_current', false),
	array('total_peak', true),
	array('all_max_peak', true),
	array('aggregate', false),
	array('aggregate_sum', false),
	array('aggregate_peak', true),
	array('aggregate_max', true),
	array('aggregate_sum_peak', true),
	array('aggregate_current', false),
	array('aggregate_current_peak', true),
));
