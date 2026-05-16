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
 * tree.php's get_host_sort_type() and get_branch_sort_type() compared
 * the result of db_fetch_cell_prepared() against integer constants and
 * fed it to a switch. When the graph_tree_items row is missing the
 * helper returns false; PHP's loose `==` and PHP's switch then match
 * false to 0, so the AJAX endpoint emitted hsgt / inherit for branches
 * that no longer exist. Both call sites now bail with an empty string
 * (the no-op response the front-end expects) when the lookup returns
 * false.
 */

$source = file_get_contents(__DIR__ . '/../../tree.php');

function _tree_function_body(string $source, string $needle): string {
	$start = strpos($source, $needle);
	expect($start)->not->toBeFalse();

	$end = strpos($source, "\nfunction ", $start + strlen($needle));
	return substr($source, $start, $end !== false ? $end - $start : 4000);
}

test('get_host_sort_type guards against a missing row before the constant comparison', function () use ($source) {
	$body = _tree_function_body($source, 'function get_host_sort_type() {');

	$guardPos = strpos($body, '$sort_type === false');
	expect($guardPos)->not->toBeFalse('=== false guard must be present');

	$cmpPos = strpos($body, 'HOST_GROUPING_GRAPH_TEMPLATE');
	expect($cmpPos)->not->toBeFalse();
	expect($guardPos < $cmpPos)->toBeTrue('guard must run before the loose-equal comparison');

	/* The guard returns rather than printing garbage. */
	$guardRegion = substr($body, $guardPos, 80);
	expect($guardRegion)->toContain('return');
});

test('get_branch_sort_type guards against a missing row before the switch', function () use ($source) {
	$body = _tree_function_body($source, 'function get_branch_sort_type() {');

	$guardPos  = strpos($body, '$sort_type === false');
	$switchPos = strpos($body, 'switch($sort_type)');

	expect($guardPos)->not->toBeFalse('=== false guard must be present');
	expect($switchPos)->not->toBeFalse();
	expect($guardPos < $switchPos)->toBeTrue('guard must run before the switch');

	/* The guard prints empty and breaks out of the parent foreach so the
	 * caller does not accidentally fall through into TREE_ORDERING_INHERIT. */
	$guardRegion = substr($body, $guardPos, 120);
	expect($guardRegion)->toContain("print ''");
	expect($guardRegion)->toContain('break;');
});
