<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Consolidated XSS / output-escaping regression tests.
 *
 * Combines the previously separate per-advisory test files for:
 * GHSA-6233-v5hc-6gvf, GHSA-7gx8-f5q4-86mv, GHSA-977w-79m7-xjc4,
 * GHSA-m544-32jr-54xw, and the XSS assertions from GHSA-cfhh-pwvx-gp5g.
 *
 * Each test below keeps its original GHSA identifier in its description
 * so the advisory it guards against remains traceable.
 */

$reportsSource         = file_get_contents(__DIR__ . '/../../../../lib/reports.php');
$managersSource        = file_get_contents(__DIR__ . '/../../../../managers.php');
$graphXportSource      = file_get_contents(__DIR__ . '/../../../../graph_xport.php');
$authProfileSource     = file_get_contents(__DIR__ . '/../../../../auth_profile.php');
$aggregateGraphsSource = file_get_contents(__DIR__ . '/../../../../aggregate_graphs.php');

// GHSA-6233: tree/leaf/host/graph names were echoed unescaped by the report renderer.
test('GHSA-6233: tree name is html_escape encoded when rendered', function () use ($reportsSource) {
	// Tree titles are arbitrary user strings; the report renderer used
	// to concatenate them into HTML directly.
	expect($reportsSource)->toContain("html_escape(\$tree_name)");
});

test('GHSA-6233: leaf and host names are html_escape encoded', function () use ($reportsSource) {
	expect($reportsSource)->toContain('html_escape($leaf_name)');
	expect($reportsSource)->toContain('html_escape($host_name)');
});

test('GHSA-6233: graph title and device description use html_escape', function () use ($reportsSource) {
	expect($reportsSource)->toContain('html_escape($graph_name)');
	expect($reportsSource)->toContain('html_escape($description)');
});

test('GHSA-6233: at least three html_escape call sites exist in the report title path', function () use ($reportsSource) {
	// Sanity floor so a partial revert cannot silently strip the
	// encoding from one of the four title sources.
	$count = substr_count($reportsSource, 'html_escape(');
	expect($count)->toBeGreaterThanOrEqual(3);
});

// GHSA-7gx8: managers.php tooltip echoed name/description without escaping.
test('GHSA-7gx8: managers.php contains the 7gx8 fix', function () use ($managersSource) {
	expect($managersSource)->not->toBeFalse();
	expect($managersSource)->toContain("html_escape(\$item['name'])");
	expect($managersSource)->toContain("html_escape(\$item['description'])");
});

// GHSA-977w: graph_xport.php HTML preview + CSV export escaping.
test('GHSA-977w: HTML preview escapes the graph title', function () use ($graphXportSource) {
	// title_cache is attacker-controllable via the graph template and
	// was echoed raw into the preview table on the vulnerable branch.
	expect($graphXportSource)->toContain("html_escape(\$xport_array['meta']['title_cache'])");
});

test('GHSA-977w: HTML preview escapes each legend column', function () use ($graphXportSource) {
	// Per-column legends are joined into <th> cells; they need the same
	// escaping path as the title to stop reflected XSS.
	expect($graphXportSource)->toContain("html_escape(\$xport_array['meta']['legend']['col' . \$i])");
});

test('GHSA-977w: CSV legend export applies RFC 4180 quote doubling', function () use ($graphXportSource) {
	// CR/LF get flattened to spaces and embedded double-quotes are
	// doubled so the CSV cell cannot be terminated early.
	expect($graphXportSource)->toContain('str_replace(array("\r", "\n", \'"\'), array(\' \', \' \', \'""\'), $legend)');
});

test('GHSA-977w: CSV cells are wrapped in quotes after sanitisation', function () use ($graphXportSource) {
	// The defensive escape only works if the final field is actually
	// emitted as a quoted CSV cell.
	expect($graphXportSource)->toContain('$header .= \',"\' . str_replace(array("\r", "\n", \'"\'), array(\' \', \' \', \'""\'), $legend) . \'"\';');
});

// GHSA-m544: session referer echoed into a JS context without json_encode.
test('GHSA-m544: auth_profile.php contains the m544 fix', function () use ($authProfileSource) {
	expect($authProfileSource)->not->toBeFalse();
	expect($authProfileSource)->toContain('json_encode(');
});

// GHSA-cfhh: XSS half of the aggregate rfilter advisory (SQL-injection half lives
// in the SqlInjection regression group).
test('GHSA-cfhh: aggregate rfilter text input is html_escape_request_var encoded', function () use ($aggregateGraphsSource) {
	// The aggregate graph filter form round-trips the submitted value
	// back into the input element. Raw output here yields reflected XSS.
	expect($aggregateGraphsSource)->toContain("html_escape_request_var('rfilter')");
});

test('GHSA-cfhh: the encoded rfilter is emitted as the input value attribute', function () use ($aggregateGraphsSource) {
	// Pin the exact render site so a reviewer who refactors the filter
	// box cannot silently drop the escape helper.
	expect($aggregateGraphsSource)->toContain("value='<?php print html_escape_request_var('rfilter');?>'");
});
