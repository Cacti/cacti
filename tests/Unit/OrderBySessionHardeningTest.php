<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$htmlUtilitySource = file_get_contents(__DIR__ . '/../../lib/html_utility.php');

test('sort order uses normalized column helper before session sql generation', function () use ($htmlUtilitySource) {
	expect($htmlUtilitySource)->toContain('function cacti_normalize_sort_column($column)');
	expect($htmlUtilitySource)->toContain('cacti_build_sort_fragment($column, $direction)');
});

test('get_order_string normalizes, validates and builds the sort fragment', function () use ($htmlUtilitySource) {
	$start = strpos($htmlUtilitySource, 'function get_order_string()');
	expect($start)->not->toBeFalse();

	$next = strpos($htmlUtilitySource, "\nfunction ", $start + 1);
	$body = $next === false ? substr($htmlUtilitySource, $start) : substr($htmlUtilitySource, $start, $next - $start);
	expect($body)->toContain("cacti_normalize_sort_column(get_nfilter_request_var('sort_column'))");
	expect($body)->toContain('cacti_build_sort_fragment($sort_column, $sort_dir)');
	expect($body)->toContain('validate_sort_column($request_column, $page)');
});
