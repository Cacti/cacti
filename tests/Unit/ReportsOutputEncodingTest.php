<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression test for GHSA-mgcq-r6vm-4xhw and GHSA-pc79-jxf4-wmqr.
 *
 * Report rendering and the package export form emitted database sourced names
 * straight into HTML.  reports_expand_device() already escaped its device
 * description, so the unescaped siblings in reports_expand_tree() were an
 * oversight rather than a deliberate exception; they are covered here too.
 *
 * @group regression
 */

$reportsPath = dirname(__DIR__, 2) . '/lib/reports.php';
$packagePath = dirname(__DIR__, 2) . '/package.php';

test('the data query name is escaped in both report rendering branches', function () use ($reportsPath) {
	$source = file_get_contents($reportsPath);

	expect($source)->not->toMatch("/' ' \. \\\$data_query\['name'\]/");
	expect(substr_count($source, "htmle(\$data_query['name'])"))->toBe(2);
});

test('every tree title component is escaped as it is built', function () use ($reportsPath) {
	$source = file_get_contents($reportsPath);

	foreach (['tree_name', 'leaf_name', 'host_name', 'graph_name'] as $part) {
		expect($source)->not->toMatch('/\$title_delimiter \. " \$' . $part . '"/');
		expect($source)->toContain("htmle(\$$part)");
	}
});

test('the package export form escapes the object name', function () use ($packagePath) {
	$source = file_get_contents($packagePath);

	expect($source)->not->toMatch("/value='<\?php print \\\$detail\['name'\]; \?>'/");
	expect($source)->toContain("print htmle(\$detail['name'])");
});

test('the escaping helper neutralises both quote styles and script tags', function () {
	require_once dirname(__DIR__, 2) . '/lib/html.php';

	$payload = '\'"><script>alert(1)</script>';
	$escaped = htmle($payload);

	expect($escaped)->not->toContain('<script');
	expect($escaped)->not->toContain('"');
	expect($escaped)->not->toContain("'");
});
