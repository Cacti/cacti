<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$reportsSource = file_get_contents(__DIR__ . '/../../lib/reports.php');

test('GHSA-6233-v5hc-6gvf: tree name is html_escape encoded when rendered', function () use ($reportsSource) {
	// Tree titles are arbitrary user strings; the report renderer used
	// to concatenate them into HTML directly.
	expect($reportsSource)->toContain("html_escape(\$tree_name)");
});

test('GHSA-6233-v5hc-6gvf: leaf and host names are html_escape encoded', function () use ($reportsSource) {
	expect($reportsSource)->toContain('html_escape($leaf_name)');
	expect($reportsSource)->toContain('html_escape($host_name)');
});

test('GHSA-6233-v5hc-6gvf: graph title and device description use html_escape', function () use ($reportsSource) {
	expect($reportsSource)->toContain('html_escape($graph_name)');
	expect($reportsSource)->toContain('html_escape($description)');
});

test('GHSA-6233-v5hc-6gvf: at least three html_escape call sites exist in the report title path', function () use ($reportsSource) {
	// Sanity floor so a partial revert cannot silently strip the
	// encoding from one of the four title sources.
	$count = substr_count($reportsSource, 'html_escape(');
	expect($count)->toBeGreaterThanOrEqual(3);
});
