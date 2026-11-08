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

// Exercise the actual token conversion shared by percentile comments and HRULEs.
require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 4) . '/include/global_constants.php';
cacti_fixture_source_function(
	dirname(__DIR__, 4) . '/lib/api_aggregate.php',
	'aggregate_percentile_mode',
	'CactiAggregatePercentileFixture'
);

$percentile_cases = [
	'current' => ['aggregate_current', 'aggregate_sum'],
	'max' => ['aggregate_current_peak', 'aggregate_sum_peak'],
	'total' => ['aggregate_sum', 'aggregate_sum'],
	'total_peak' => ['total_peak', 'total_peak'],
	'all_max_current' => ['aggregate_peak', 'aggregate_peak'],
	'all_max_peak' => ['aggregate_peak', 'aggregate_peak'],
	'aggregate_max' => ['aggregate_peak', 'aggregate_peak'],
	'aggregate_sum' => ['aggregate_sum', 'aggregate_sum'],
	'aggregate_sum_peak' => ['aggregate_sum_peak', 'aggregate_sum_peak'],
	'aggregate_current' => ['aggregate_current', 'aggregate_current'],
	'aggregate_current_peak' => ['aggregate_current_peak', 'aggregate_current_peak'],
	'aggregate' => ['aggregate', 'aggregate'],
	'aggregate_peak' => ['aggregate_peak', 'aggregate_peak'],
];
$percentile_dataset = [];
foreach ($percentile_cases as $input => $expected) {
	$percentile_dataset[$input . ' similar'] = [$input, AGGREGATE_TOTAL_TYPE_SIMILAR, $expected[0]];
	$percentile_dataset[$input . ' all'] = [$input, AGGREGATE_TOTAL_TYPE_ALL, $expected[1]];
}
$percentile_dataset['unknown type'] = ['current', 99, 'current'];
$percentile_dataset['unknown mode'] = ['custom', AGGREGATE_TOTAL_TYPE_ALL, 'custom'];

test('percentile conversion preserves complete mode tokens', function ($input, $type, $expected) {
	expect(\CactiAggregatePercentileFixture\aggregate_percentile_mode($input, $type))->toBe($expected);
})->with($percentile_dataset);
