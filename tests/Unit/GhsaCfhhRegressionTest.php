<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$aggregateGraphsSource = file_get_contents(__DIR__ . '/../../aggregate_graphs.php');

test('GHSA-cfhh-pwvx-gp5g: aggregate rfilter text input is html_escape_request_var encoded', function () use ($aggregateGraphsSource) {
	// The aggregate graph filter form round-trips the submitted value
	// back into the input element. Raw output here yields reflected XSS.
	expect($aggregateGraphsSource)->toContain("html_escape_request_var('rfilter')");
});

test('GHSA-cfhh-pwvx-gp5g: the encoded rfilter is emitted as the input value attribute', function () use ($aggregateGraphsSource) {
	// Pin the exact render site so a reviewer who refactors the filter
	// box cannot silently drop the escape helper.
	expect($aggregateGraphsSource)->toContain("value='<?php print html_escape_request_var('rfilter');?>'");
});

test('GHSA-cfhh-pwvx-gp5g: rfilter SQL side still runs through db_qstr_rlike', function () use ($aggregateGraphsSource) {
	// XSS fix must not regress the parallel SQL injection guard that
	// covers the same request variable.
	expect($aggregateGraphsSource)->toContain("db_qstr_rlike(get_request_var('rfilter'))");
});
