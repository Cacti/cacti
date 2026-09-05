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

test('graph export and percentile paths share the selected RRA resolution', function () {
	$rrd       = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');
	$variables = file_get_contents(CACTI_PATH_LIBRARY . '/graph_variables.php');

	expect($rrd)->not->toBeFalse()
		->and($variables)->not->toBeFalse()
		->and($rrd)->toContain("':step=' . max(1, \$rra_seconds)")
		->and($rrd)->toContain('variable_nth_percentile($match, $graph, $graph_item, $graph_items, $graph_start, $graph_end, $rra_seconds)')
		->and($rrd)->toContain('ceil(abs($graph_end - $graph_start) / $export_step)')
		->and($variables)->toContain('int $graph_start, int $graph_end, int $resolution = 0')
		->and(preg_match_all('/\$nth_cache\s*=\s*nth_percentile\([^;]+\$resolution/', $variables))->toBe(8)
		->and($variables)->not->toContain('$percentile, 0, true');
});

test('CSV export no longer shifts the requested start boundary', function () {
	$rrd = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');

	expect($rrd)->not->toBeFalse()
		->and($rrd)->toContain("'--start=' . \$graph_start")
		->and($rrd)->not->toMatch('/--start=.*graph_start\s*-\s*1/');
});
