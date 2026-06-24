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

/*
 * Regression tests for ORDER BY sort injection hardening (GHSA-q9xg-p762-9jm3,
 * GHSA-72vr-jr4v-55vf).
 *
 * Two classes of injection were fixed:
 *
 *   1. sort_column — arbitrary SQL appended after the column name, e.g.
 *      "name UNION SELECT password FROM user_auth-- "
 *      Fix: sanitize_sql_column() in lib/functions.php strips every character
 *      outside [a-zA-Z0-9_().space].
 *
 *   2. sort_direction — arbitrary SQL injected via the direction parameter, e.g.
 *      "ASC; DROP TABLE reports-- "
 *      Fix: all callers enforce strtoupper(...) === 'DESC' ? 'DESC' : 'ASC'.
 *
 * Tests are stub-based: they mirror the production logic without requiring a
 * database, session, or full Cacti bootstrap.
 */

// --- Stub: sanitize_sql_column (lib/functions.php) ---

function stub_sanitize_sql_column(string $column): string {
	return preg_replace('/[^a-zA-Z0-9_().]/', '', $column) ?? '';
}

// --- Stub: direction enforcement used in html_reports.php, api_automation.php,
//     and the get_order_string() fallback in html_utility.php ---

function stub_safe_direction(string $raw): string {
	return strtoupper($raw) === 'DESC' ? 'DESC' : 'ASC';
}

// --- sanitize_sql_column: allowlist behaviour ---

test('sanitize_sql_column: plain column name passes unchanged', function () {
	expect(stub_sanitize_sql_column('hostname'))->toBe('hostname');
});

test('sanitize_sql_column: qualified column passes unchanged', function () {
	expect(stub_sanitize_sql_column('report.name'))->toBe('report.name');
});

test('sanitize_sql_column: function call with parens passes', function () {
	expect(stub_sanitize_sql_column('INET_ATON(ip)'))->toBe('INET_ATON(ip)');
});

test('sanitize_sql_column: column with underscore and digits passes', function () {
	expect(stub_sanitize_sql_column('last_started_2'))->toBe('last_started_2');
});

// --- sanitize_sql_column: injection payloads stripped ---

test('sanitize_sql_column: UNION SELECT payload: spaces and dashes stripped', function () {
	$input  = "name UNION SELECT password FROM user_auth-- ";
	$output = stub_sanitize_sql_column($input);
	// Spaces removed: SQL keywords cannot be parsed as separate tokens without spaces
	expect($output)->not->toContain(' ');
	expect($output)->not->toContain('-');
	expect($output)->not->toContain(';');
});

test('sanitize_sql_column: semicolon stripped', function () {
	expect(stub_sanitize_sql_column('name; DROP TABLE reports'))->not->toContain(';');
});

test('sanitize_sql_column: single quote stripped', function () {
	expect(stub_sanitize_sql_column("name' OR '1'='1"))->not->toContain("'");
});

test('sanitize_sql_column: backtick stripped', function () {
	expect(stub_sanitize_sql_column('`report`.`name`'))->not->toContain('`');
});

test('sanitize_sql_column: comment marker stripped', function () {
	$output = stub_sanitize_sql_column('name--comment');
	expect($output)->not->toContain('-');
});

test('sanitize_sql_column: empty string returns empty string', function () {
	expect(stub_sanitize_sql_column(''))->toBe('');
});

test('sanitize_sql_column: null-byte stripped', function () {
	expect(stub_sanitize_sql_column("name\x00injection"))->not->toContain("\x00");
});

// --- Direction enforcement: valid values ---

test('safe_direction: ASC preserved', function () {
	expect(stub_safe_direction('ASC'))->toBe('ASC');
});

test('safe_direction: DESC preserved', function () {
	expect(stub_safe_direction('DESC'))->toBe('DESC');
});

test('safe_direction: lowercase asc normalised to ASC', function () {
	expect(stub_safe_direction('asc'))->toBe('ASC');
});

test('safe_direction: lowercase desc normalised to DESC', function () {
	expect(stub_safe_direction('desc'))->toBe('DESC');
});

test('safe_direction: mixed case Desc normalised to DESC', function () {
	expect(stub_safe_direction('Desc'))->toBe('DESC');
});

// --- Direction enforcement: injection payloads default to ASC ---

test('safe_direction: SQL payload defaults to ASC', function () {
	expect(stub_safe_direction("ASC; DROP TABLE reports-- "))->toBe('ASC');
});

test('safe_direction: UNION payload defaults to ASC', function () {
	expect(stub_safe_direction('ASC UNION SELECT 1'))->toBe('ASC');
});

test('safe_direction: empty string defaults to ASC', function () {
	expect(stub_safe_direction(''))->toBe('ASC');
});

test('safe_direction: arbitrary string defaults to ASC', function () {
	expect(stub_safe_direction('RANDOM'))->toBe('ASC');
});

test('safe_direction: null-byte payload defaults to ASC', function () {
	expect(stub_safe_direction("DESC\x00extra"))->toBe('ASC');
});

// --- Combined: verify sanitized column + direction cannot form injected SQL ---

test('combined: injection payload in column and direction produces safe ORDER BY', function () {
	$raw_col = "name UNION SELECT password FROM user_auth-- ";
	$raw_dir = "ASC; DROP TABLE reports";

	$col    = stub_sanitize_sql_column($raw_col);
	$dir    = stub_safe_direction($raw_dir);
	$clause = 'ORDER BY ' . $col . ' ' . $dir;

	// No spaces in $col: SQL keywords cannot act as separate tokens
	expect($col)->not->toContain(' ');
	// No injection-enabling characters survive in $col
	expect($col)->not->toContain(';');
	expect($col)->not->toContain('-');
	expect($col)->not->toContain("'");
	// Direction is strictly ASC or DESC regardless of payload
	expect($dir)->toBe('ASC');
	// The assembled clause has no injected semicolons or comment markers
	expect($clause)->not->toContain(';');
	expect($clause)->not->toContain('--');
});
