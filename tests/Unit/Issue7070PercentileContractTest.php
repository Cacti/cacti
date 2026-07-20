<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$graphVariables = file_get_contents(__DIR__ . '/../../lib/graph_variables.php');
$graphXport     = file_get_contents(__DIR__ . '/../../graph_xport.php');

test('percentile index is based on observed samples', function () use ($graphVariables) {
	preg_match('/^function cacti_percentile_index\([^)]*\).*?\{.*?^\}/sm', $graphVariables, $matches);
	expect($matches)->not->toBeEmpty();

	$source = preg_replace('/^function cacti_percentile_index\(/m', 'function issue7070_percentile_index(', $matches[0]);
	eval($source);

	// 8,640 observed samples discard 432 high values; index 431 is the 432nd.
	expect(issue7070_percentile_index(8640, 95))->toBe(431);
	expect(issue7070_percentile_index(8664, 95))->toBe(433);
	// A missing sample is visible in coverage, not converted to a zero value.
	expect(issue7070_percentile_index(0, 95))->toBe(0);
});

test('CSV export exposes sparse-period coverage', function () use ($graphXport) {
	expect($graphXport)->toContain("__('Expected Rows')");
	expect($graphXport)->toContain("__('Missing Rows')");
	expect($graphXport)->toContain("['meta']['missing_rows']");
});
