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
 * Tests for the invalid-local_graph_id short-circuit in graph.php.
 *
 * When graph.php is loaded without a valid local_graph_id, the
 * db_fetch_row_prepared call returns false. Before the fix, line 114
 * dereferenced $graph['graph_template_id'] on a non-array, producing
 * PHP 8.x "Undefined array key" warnings. The fix adds a
 * cacti_sizeof($graph) guard that redirects with raise_message().
 *
 * These tests verify the guard exists and that the code does not
 * dereference $graph before validating it.
 */

$graphPath = __DIR__ . '/../../graph.php';

test('graph.php checks cacti_sizeof($graph) before dereferencing', function () use ($graphPath) {
	$contents = file_get_contents($graphPath);

	expect($contents)->toContain("cacti_sizeof(\$graph)");
});

test('graph.php calls raise_message when graph row is empty', function () use ($graphPath) {
	$contents = file_get_contents($graphPath);

	expect($contents)->toContain("raise_message('graph_not_found'");
});

test('graph.php redirects via validate_redirect_url on invalid graph', function () use ($graphPath) {
	$contents = file_get_contents($graphPath);

	// The raise_message block must use validate_redirect_url with HTTP_REFERER
	// and fall back to graph_view.php
	expect($contents)->toContain("validate_redirect_url(isset(\$_SERVER['HTTP_REFERER'])");
	expect($contents)->toContain("'graph_view.php'");
});

test('graph.php does not dereference $graph before the size check', function () use ($graphPath) {
	$contents = file_get_contents($graphPath);

	// Find the db_fetch_row_prepared that populates $graph
	$fetchPos = strpos($contents, "db_fetch_row_prepared('SELECT gtg.local_graph_id, width, height, title_cache");
	expect($fetchPos)->not->toBeFalse();

	// Find the cacti_sizeof guard
	$guardPos = strpos($contents, 'cacti_sizeof($graph)', $fetchPos);
	expect($guardPos)->not->toBeFalse();

	// Find the first $graph['...'] dereference after the fetch
	$derefPos = false;
	if (preg_match('/\$graph\[\s*[\'"]/', $contents, $m, PREG_OFFSET_CAPTURE, $fetchPos)) {
		$derefPos = $m[0][1];
	}
	expect($derefPos)->not->toBeFalse();

	// The guard must come BEFORE the first dereference
	expect($guardPos)->toBeLessThan($derefPos);
});

test('graph.php exits after raise_message redirect', function () use ($graphPath) {
	$contents = file_get_contents($graphPath);

	// After the redirect header there must be an exit before any $graph usage
	expect($contents)->toMatch("/raise_message\('graph_not_found'.*?exit;/s");
});
