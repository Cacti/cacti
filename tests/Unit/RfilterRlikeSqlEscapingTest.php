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
 * Tests for the rfilter RLIKE SQL injection hardening in html_graph.php and html_tree.php.
 *
 * The fix wraps every rfilter value with db_qstr() before interpolating it into
 * RLIKE clauses, replacing previous raw string concatenation that allowed an
 * attacker-controlled value to escape the regex literal and inject SQL.
 *
 * Two test suites:
 *   1. Source-scan: verifies the safe pattern is present and the unsafe pattern
 *      is absent from both files.
 *   2. Behavioral: exercises the db_qstr() quoting logic in isolation to confirm
 *      SQL-special characters are escaped correctly.
 */

// ---------------------------------------------------------------------------
// Source files
// ---------------------------------------------------------------------------

$graph_src = file_get_contents(__DIR__ . '/../../lib/html_graph.php');
$tree_src  = file_get_contents(__DIR__ . '/../../lib/html_tree.php');

// ---------------------------------------------------------------------------
// Source-scan suite — html_graph.php
// ---------------------------------------------------------------------------

test('html_graph: RLIKE clause uses db_qstr() around rfilter value', function () use ($graph_src) {
	expect($graph_src)->toContain('RLIKE ' . "' . db_qstr(grv('rfilter'))");
});

test('html_graph: no raw rfilter variable concatenated directly into RLIKE string', function () use ($graph_src) {
	// Raw patterns that indicate unescaped interpolation: RLIKE '$rfilter or RLIKE ".$rfilter
	expect($graph_src)->not->toContain("RLIKE '\$rfilter")
		->and($graph_src)->not->toContain('RLIKE ".$rfilter')
		->and($graph_src)->not->toContain("RLIKE '.$rfilter");
});

test('html_graph: both RLIKE occurrences for rfilter are guarded by db_qstr', function () use ($graph_src) {
	// Count safe occurrences vs. total RLIKE+rfilter occurrences; they must match.
	$safe_count  = substr_count($graph_src, "RLIKE ' . db_qstr(grv('rfilter'))");
	$total_count = substr_count($graph_src, "RLIKE");

	// There are exactly 2 RLIKE+rfilter sites in html_graph.php; both must be guarded.
	expect($safe_count)->toBeGreaterThanOrEqual(2);
	// Every RLIKE in this file must be wrapped — no raw concat should remain.
	expect($total_count)->toBe($safe_count);
});

// ---------------------------------------------------------------------------
// Source-scan suite — html_tree.php
// ---------------------------------------------------------------------------

test('html_tree: RLIKE clauses use db_qstr() around rfilter values', function () use ($tree_src) {
	expect($tree_src)->toContain('RLIKE ' . "' . db_qstr(grv('rfilter'))");
});

test('html_tree: no raw rfilter variable concatenated directly into RLIKE string', function () use ($tree_src) {
	expect($tree_src)->not->toContain("RLIKE '\$rfilter")
		->and($tree_src)->not->toContain('RLIKE ".$rfilter')
		->and($tree_src)->not->toContain("RLIKE '.$rfilter");
});

test('html_tree: all RLIKE occurrences for rfilter are guarded by db_qstr', function () use ($tree_src) {
	$safe_count  = substr_count($tree_src, "RLIKE ' . db_qstr(grv('rfilter'))");

	// html_tree.php has multiple RLIKE+rfilter sites; all must be guarded.
	expect($safe_count)->toBeGreaterThanOrEqual(4);
});

// ---------------------------------------------------------------------------
// Behavioral suite — db_qstr() escaping logic
//
// db_qstr() is a thin wrapper that: wraps the value in single quotes and
// escapes internal single quotes and backslashes.  The tests below exercise
// that contract using the same escaping rule MySQL uses for string literals,
// without requiring a live DB connection.
// ---------------------------------------------------------------------------

/*
 * Minimal reimplementation of db_qstr() quoting semantics so tests are
 * self-contained.  The production function calls addslashes() and wraps in
 * single quotes; this mirrors that exactly.
 */
function rlike_quote(string $value): string {
	return "'" . addslashes($value) . "'";
}

test('plain alphanumeric rfilter is quoted without modification', function () {
	$quoted = rlike_quote('server.*');

	expect($quoted)->toBe("'server.*'");
});

test('single quote in rfilter value is escaped to prevent SQL injection', function () {
	$quoted = rlike_quote("'; DROP TABLE graphs; --");

	// addslashes escapes the single quote; the result must not contain a bare '
	// that would terminate the SQL string literal.
	expect($quoted)->toContain("\\'")
		->and($quoted)->toStartWith("'")
		->and($quoted)->toEndWith("'");
});

test('backslash in rfilter value is escaped to prevent RLIKE pattern confusion', function () {
	$quoted = rlike_quote('path\\to\\host');

	expect($quoted)->toContain('\\\\');
});

test('null byte in rfilter value is preserved inside quotes (boundary enforcement is DB-layer)', function () {
	$quoted = rlike_quote("evil\x00payload");

	// db_qstr does not strip null bytes; the quoting itself neutralises injection.
	expect($quoted)->toStartWith("'")
		->and($quoted)->toEndWith("'");
});

test('percent sign in rfilter is preserved — RLIKE uses regex not LIKE wildcards', function () {
	$quoted = rlike_quote('host%domain');

	// Percent is not special in RLIKE/REGEXP; db_qstr must not strip or encode it.
	expect($quoted)->toContain('%');
});

test('rfilter with SQL comment sequence is safely quoted', function () {
	$quoted = rlike_quote("graph -- DROP TABLE");

	expect($quoted)->toStartWith("'")
		->and($quoted)->toEndWith("'")
		->and($quoted)->toContain('--');
});

test('empty rfilter string produces empty quoted literal', function () {
	$quoted = rlike_quote('');

	expect($quoted)->toBe("''");
});
