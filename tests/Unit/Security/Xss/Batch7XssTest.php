<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Batch 7 develop-only XSS consolidation. The automation Resulting Branch cell
 * is the one live fix (GHSA-f7jw); the rest pin name sinks the broad develop
 * XSS hardening already closed: reports device-description status (GHSA-4gph),
 * graph export legend (GHSA-977w), Reports Data Query name (GHSA-mgcq),
 * die_html_input_error echo (GHSA-5p79), and the aggregate rfilter attribute
 * (GHSA-cfhh).
 */

$root = dirname(__DIR__, 4);

test('automation tree-rule preview escapes the Resulting Branch values (GHSA-f7jw)', function () use ($root) {
	$s = file_get_contents($root . '/lib/api_automation.php');
	expect($s)->toContain('htmle(array_shift($replacement))');
});

test('report-add device description status is escaped (GHSA-4gph)', function () use ($root) {
	$s = file_get_contents($root . '/lib/reports.php');
	expect($s)->not->toContain("__('Device \\'%s\\' successfully added to Report.'");
});

test('graph export legend columns are html-escaped (GHSA-977w)', function () use ($root) {
	$s = file_get_contents($root . '/graph_xport.php');
	expect($s)->toContain('htmle($xport_array');
});

test('Reports Data Query name is html-escaped (GHSA-mgcq)', function () use ($root) {
	$s = file_get_contents($root . '/lib/reports.php');
	expect($s)->toContain('htmle($data_query');
});

test('die_html_input_error escapes the echoed variable and value (GHSA-5p79)', function () use ($root) {
	$s = file_get_contents($root . '/lib/html_validate.php');
	expect($s)->toContain("\$func     = CACTI_CLI ? 'trim' : 'htmle';");
});

test('aggregate rfilter is not echoed into a raw attribute (GHSA-cfhh)', function () use ($root) {
	$s = file_get_contents($root . '/aggregate_graphs.php');
	expect($s)->not->toContain("value='<?php print grv('rfilter')");
});
