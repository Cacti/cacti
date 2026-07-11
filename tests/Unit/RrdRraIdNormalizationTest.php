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

/*
 * Source-scan tests for the $rra_id normalization in rrdtool_function_graph().
 *
 * Several callers (lib/reports.php, graph_realtime.php) pass '' when no
 * RRA is selected.  boost_graph_set_file() declares int|null for $rra_id,
 * so the string reached it unconverted and threw a TypeError, which broke
 * report sending (#7214).
 */

function getRrdSourceForRraId(): string {
	$path = __DIR__ . '/../../lib/rrd.php';
	$src  = file_get_contents($path);
	expect($src)->not->toBeFalse('Failed to read lib/rrd.php');

	return $src;
}

test('rrdtool_function_graph normalizes rra_id before first boost call', function () {
	$src = getRrdSourceForRraId();

	$fnPos = strpos($src, 'function rrdtool_function_graph(');
	expect($fnPos)->not->toBeFalse('rrdtool_function_graph must exist in lib/rrd.php');

	$normPos = strpos($src, "\$rra_id = is_numeric(\$rra_id) ? (int)\$rra_id : null;", $fnPos);
	expect($normPos)->not->toBeFalse('rra_id normalization must exist inside rrdtool_function_graph');

	$boostPos = strpos($src, 'boost_graph_cache_check(', $fnPos);
	expect($boostPos)->not->toBeFalse('boost_graph_cache_check call must exist');
	expect($normPos)->toBeLessThan($boostPos, 'normalization must precede the first boost call');
});

test('rra_id normalization maps caller inputs to int or null', function () {
	$normalize = function ($rra_id) {
		return is_numeric($rra_id) ? (int)$rra_id : null;
	};

	// reports.php and graph_realtime.php pass ''
	expect($normalize(''))->toBeNull();
	// graph_image.php passes a validated numeric string
	expect($normalize('3'))->toBe(3);
	// graphs.php debug passes int directly
	expect($normalize(1))->toBe(1);
	expect($normalize(null))->toBeNull();
	expect($normalize('all'))->toBeNull();
});
