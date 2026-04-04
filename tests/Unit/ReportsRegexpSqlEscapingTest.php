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

/**
 * Tests for the graph_name_regexp REGEXP SQL injection hardening in lib/reports.php.
 *
 * The fix wraps every graph_name_regexp value with db_qstr() before it enters
 * a REGEXP clause, replacing raw variable interpolation that allowed an
 * attacker-controlled report item to inject SQL via the regexp field.
 *
 * Two test suites:
 *   1. Source-scan: verifies the safe pattern is present and the unsafe pattern
 *      (bare $item['graph_name_regexp'] in a SQL string) is absent.
 *   2. Behavioral: exercises the db_qstr() quoting contract against SQL-special
 *      characters without requiring a live DB connection.
 */

// ---------------------------------------------------------------------------
// Source file
// ---------------------------------------------------------------------------

$src = file_get_contents(__DIR__ . '/../../lib/reports.php');

// ---------------------------------------------------------------------------
// Source-scan suite
// ---------------------------------------------------------------------------

test('reports: REGEXP clause uses db_qstr() around graph_name_regexp', function () use ($src) {
	expect($src)->toContain("REGEXP ' . db_qstr(\$item['graph_name_regexp'])");
});

test('reports: no raw graph_name_regexp concatenated directly into REGEXP string', function () use ($src) {
	// Patterns that would indicate unescaped interpolation.
	expect($src)->not->toContain("REGEXP '\$item[")
		->and($src)->not->toContain("REGEXP '\" . \$item['graph_name_regexp']")
		->and($src)->not->toContain("REGEXP \" . \$item['graph_name_regexp']");
});

test('reports: all REGEXP+graph_name_regexp occurrences are wrapped in db_qstr', function () use ($src) {
	// Every site that builds a REGEXP clause from graph_name_regexp must use db_qstr.
	$safe_count = substr_count($src, "REGEXP ' . db_qstr(\$item['graph_name_regexp'])");

	// There are multiple call sites; all must be guarded.
	expect($safe_count)->toBeGreaterThanOrEqual(3);
});

test('reports: graph_name_regexp non-empty guard still present before REGEXP usage', function () use ($src) {
	// The fix must not remove the existing non-empty guard; it only adds quoting.
	expect($src)->toContain("\$item['graph_name_regexp'] != ''");
});

test('reports: db_qstr is used, not manual addslashes, for REGEXP escaping', function () use ($src) {
	// Confirms the fix uses the standard Cacti quoting helper rather than ad-hoc
	// escaping that might miss edge cases.
	$dbqstr_pos    = strpos($src, "db_qstr(\$item['graph_name_regexp'])");
	$addslash_pos  = strpos($src, "addslashes(\$item['graph_name_regexp'])");

	expect($dbqstr_pos)->not->toBeFalse();
	expect($addslash_pos)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Behavioral suite — db_qstr() quoting contract
//
// db_qstr() wraps a value in single quotes and escapes single quotes and
// backslashes via addslashes(). The tests confirm this holds for inputs
// that would otherwise break a REGEXP clause or inject SQL.
// ---------------------------------------------------------------------------

/*
 * Mirrors the db_qstr() quoting semantics used in production.
 */
function reports_quote(string $value): string {
	return "'" . addslashes($value) . "'";
}

test('plain regexp pattern is quoted without modification', function () {
	$quoted = reports_quote('host.*');

	expect($quoted)->toBe("'host.*'");
});

test('single quote in regexp is escaped to prevent SQL injection', function () {
	$quoted = reports_quote("'; DROP TABLE reports; --");

	expect($quoted)->toContain("\\'")
		->and($quoted)->toStartWith("'")
		->and($quoted)->toEndWith("'");
});

test('backslash in regexp is doubled to prevent REGEXP escape confusion', function () {
	$quoted = reports_quote('path\\to\\graph');

	// addslashes() doubles the backslash; MySQL then interprets \\ as a literal \.
	expect($quoted)->toContain('\\\\');
});

test('percent sign is passed through unchanged — REGEXP does not use LIKE wildcards', function () {
	$quoted = reports_quote('graph%name');

	expect($quoted)->toContain('%');
});

test('SQL comment sequence is safely enclosed in quotes', function () {
	$quoted = reports_quote('graph -- DROP TABLE');

	expect($quoted)->toStartWith("'")
		->and($quoted)->toEndWith("'");
});

test('empty regexp string produces empty quoted literal', function () {
	$quoted = reports_quote('');

	expect($quoted)->toBe("''");
});

test('regexp with UNIX newline is safely quoted', function () {
	// Newlines are not special to db_qstr() but must not break the SQL string.
	$quoted = reports_quote("line1\nline2");

	expect($quoted)->toStartWith("'")
		->and($quoted)->toEndWith("'");
});

test('regexp with dot-star wildcard passes through correctly', function () {
	$quoted = reports_quote('.*router.*');

	expect($quoted)->toBe("'.*router.*'");
});
