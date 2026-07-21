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
 * therefore validate the value before handing it to boost_graph_set_file(),
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

test('rrd.php validation guards every boost consumer of rra_id', function () use ($root) {
	$src = file_get_contents($root . '/lib/rrd.php');
	expect($src)->not->toBeFalse('Failed to read lib/rrd.php');

	$fnPos = strpos($src, 'function rrdtool_function_graph(');
	expect($fnPos)->not->toBeFalse('rrdtool_function_graph must exist');

	// Anchor on the integer-validation call by shape, not an exact line, so
	// ctype_digit() or filter_var(FILTER_VALIDATE_INT) both satisfy it.
	$matched = preg_match('/ctype_digit\s*\(|filter_var\s*\([^)]*FILTER_VALIDATE_INT/', $src, $m, PREG_OFFSET_CAPTURE, $fnPos);
	expect($matched)->toBe(1, 'integer validation of rra_id missing from rrdtool_function_graph');
	$normPos = $m[0][1];

	foreach (['boost_graph_cache_check(', 'boost_graph_set_file('] as $consumer) {
		$pos = strpos($src, $consumer, $normPos);
		expect($pos)->not->toBeFalse("$consumer must appear after the validation");
	}
});
