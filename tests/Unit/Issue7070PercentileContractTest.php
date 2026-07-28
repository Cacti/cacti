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

$graphVariables = file_get_contents(__DIR__ . '/../../lib/graph_variables.php');
$graphXport     = file_get_contents(__DIR__ . '/../../graph_xport.php');

test('percentile index is based on observed samples', function () use ($graphVariables) {
	preg_match('/^function cacti_percentile_index\([^)]*\).*?\{.*?^\}/sm', $graphVariables, $matches);
	expect($matches)->not->toBeEmpty();

	$source = preg_replace('/^function cacti_percentile_index\(/m', 'function issue7070_percentile_index(', $matches[0]);
	eval($source);

	// Verified against rrdtool VDEF PERCENT for 28/29/30/31-day months
	// (N = 8064/8352/8640/8928): the index is N - round(0.95 * N).
	expect(issue7070_percentile_index(8064, 95))->toBe(403);
	expect(issue7070_percentile_index(8352, 95))->toBe(418);
	expect(issue7070_percentile_index(8640, 95))->toBe(432);
	expect(issue7070_percentile_index(8928, 95))->toBe(446);
	// A single sample returns the only value, not a zero; the empty set stays zero.
	expect(issue7070_percentile_index(1, 95))->toBe(0);
	expect(issue7070_percentile_index(0, 95))->toBe(0);
});

test('CSV export exposes sparse-period coverage', function () use ($graphXport) {
	expect($graphXport)->toContain("__('Expected Rows')");
	expect($graphXport)->toContain("__('Missing Rows')");
	expect($graphXport)->toContain("['meta']['missing_rows']");
});
