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

test('get_order_string rebuilds ORDER BY from validated session sort_data', function () use ($htmlUtilitySource) {
	$start = strpos($htmlUtilitySource, 'function get_order_string()');
	expect($start)->not->toBeFalse();

	$body = substr($htmlUtilitySource, $start, 1800);
	expect($body)->toContain("if (isset(\$_SESSION['sort_data'][\$page]) && is_array(\$_SESSION['sort_data'][\$page]))");
	expect($body)->toContain("\$_SESSION['sort_string'][\$page] = 'ORDER BY ' . implode(', ', \$order_parts);");
});
