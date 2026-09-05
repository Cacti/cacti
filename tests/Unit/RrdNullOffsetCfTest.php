<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * generate_graph_best_cf() kept an uninitialized static. The first call with
 * local_data_id 0 returned null, which PHP 8.5 then used as a cache-array
 * offset (issue #7810). Graph items without a data source also pass null
 * names and rrd ids into those arrays.
 */

$root = dirname(__DIR__, 2);

test('generate_graph_best_cf initializes the static CF before first use', function () use ($root) {
	$src = file_get_contents($root . '/lib/functions.php');

	expect($src)->toContain('static $best_cf = 1;')
		->and($src)->not->toMatch('/static \$best_cf;\s*$/m');
});

test('graph CF caches do not use raw nullable item fields as offsets', function () use ($root) {
	$src = file_get_contents($root . '/lib/rrd.php');

	expect($src)->toContain("\$graph_item['data_source_name'] ?? ''")
		->and($src)->toContain("\$graph_item['local_data_template_rrd_id'] ?? ''")
		->and($src)->toContain("\$graph_item['data_template_rrd_id'] ?? ''")
		->and($src)->toContain('$graph_item[\'cf_reference\'] ?? 1');
});
