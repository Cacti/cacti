<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Cross-file contract test for the rra_id path from report generation
 * into boost (#7214).
 *
 * lib/reports.php passes '' for $rra_id.  rrdtool_function_graph() must
 * therefore normalize before handing the value to boost_graph_set_file(),
 * whose signature is int|null and rejects strings with a TypeError.
 */

$root = dirname(__DIR__, 2);

test('boost_graph_set_file still declares int|null for rra_id', function () use ($root) {
	$src = file_get_contents($root . '/lib/boost.php');
	expect($src)->not->toBeFalse('Failed to read lib/boost.php');

	$pattern = '/function\s+boost_graph_set_file\s*\([^)]*int\|null\s+\$rra_id/s';
	expect(preg_match($pattern, $src))->toBe(1,
		'boost_graph_set_file must declare int|null $rra_id; if this changed, revisit the normalization in lib/rrd.php');
});

test('reports.php still passes a non-int rra_id into rrdtool_function_graph', function () use ($root) {
	$src = file_get_contents($root . '/lib/reports.php');
	expect($src)->not->toBeFalse('Failed to read lib/reports.php');

	$count = preg_match_all("/rrdtool_function_graph\(\\\$local_graph_id,\s*''/", $src);
	expect($count)->toBeGreaterThan(0,
		"reports.php passes '' as rra_id; the normalization in rrdtool_function_graph must stay");
});

test('rrd.php normalization guards every boost consumer of rra_id', function () use ($root) {
	$src = file_get_contents($root . '/lib/rrd.php');
	expect($src)->not->toBeFalse('Failed to read lib/rrd.php');

	$normPos = strpos($src, "\$rra_id = is_numeric(\$rra_id) ? (int)\$rra_id : null;");
	expect($normPos)->not->toBeFalse('normalization missing from lib/rrd.php');

	foreach (['boost_graph_cache_check(', 'boost_graph_set_file('] as $consumer) {
		$pos = strpos($src, $consumer, $normPos);
		expect($pos)->not->toBeFalse("$consumer must appear after the normalization");
	}
});
