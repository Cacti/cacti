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

// reports_redirect_url() lives in lib/html_reports.php, which cannot be loaded
// in isolation because it runs reports_get_format_files() and builds field
// arrays at include time. As in ReportsBulkActionItemTest, the pure helper is
// reproduced here and exercised directly, with a source guard below asserting
// the real call site keeps the (int) cast that neutralises id-parameter
// injection into the Location header (Psalm taint finding, defence-in-depth).

$reportsPath = dirname(__DIR__, 4) . '/lib/html_reports.php';

/**
 * Mirror of reports_redirect_url() in lib/html_reports.php: the report id is
 * always an integer, so the value appended after id= is cast to int.
 */
function reports_redirect_url_mirror(string $base, mixed $id) : string {
	return $base . '?action=edit&id=' . (int) $id;
}

test('valid integer id is preserved', function () {
	expect(reports_redirect_url_mirror('reports.php', '5'))->toBe('reports.php?action=edit&id=5')
		->and(reports_redirect_url_mirror('reports.php', 5))->toBe('reports.php?action=edit&id=5');
});

test('a fresh insert id renders unchanged', function () {
	expect(reports_redirect_url_mirror('reports.php', 42))->toBe('reports.php?action=edit&id=42');
});

test('trailing parameter injection is stripped', function () {
	expect(reports_redirect_url_mirror('reports.php', '5&action=delete'))->toBe('reports.php?action=edit&id=5')
		->and(reports_redirect_url_mirror('reports.php', '5 OR 1=1'))->toBe('reports.php?action=edit&id=5');
});

test('non-numeric id collapses to zero', function () {
	expect(reports_redirect_url_mirror('reports.php', 'evil'))->toBe('reports.php?action=edit&id=0')
		->and(reports_redirect_url_mirror('reports.php', ''))->toBe('reports.php?action=edit&id=0');
});

// --- source guard: the real call site keeps the integer coercion ---

test('lib/html_reports.php defines reports_redirect_url', function () use ($reportsPath) {
	$contents = file_get_contents($reportsPath);

	expect($contents)->toContain('function reports_redirect_url(');
});

test('reports_redirect_url casts the id to int', function () use ($reportsPath) {
	$contents = file_get_contents($reportsPath);

	expect($contents)->toMatch('/\\$base\\s*\\.\\s*\'\\?action=edit&id=\'\\s*\\.\\s*\\(int\\)\\s*\\$id/');
});

test('the save redirect routes through reports_redirect_url', function () use ($reportsPath) {
	$contents = file_get_contents($reportsPath);

	expect($contents)->toContain('reports_redirect_url(get_reports_page(),');
});
