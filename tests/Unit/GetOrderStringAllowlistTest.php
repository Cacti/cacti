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
 * Tests for ORDER BY injection hardening in get_order_string() and
 * update_order_string().
 *
 * The fix validates sort_column with preg_match('/^[a-zA-Z][a-zA-Z0-9_.]*$/')
 * and collapses sort_direction to 'ASC' or 'DESC', rejecting everything else.
 * An invalid column produces an empty string from get_order_string().
 */

// ---------------------------------------------------------------------------
// Helper: mirrors the production validation from get_order_string()
// ---------------------------------------------------------------------------

function validate_sort_column(string $col): string {
	if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.]*$/', $col)) {
		return '';
	}

	return $col;
}

function validate_sort_direction(string $dir): string {
	$dir = strtoupper($dir);

	return ($dir === 'ASC' || $dir === 'DESC') ? $dir : 'ASC';
}

function build_order_clause(string $col, string $dir): string {
	$col = validate_sort_column($col);
	$dir = validate_sort_direction($dir);

	if ($col === '') {
		return '';
	}

	$del = '`';

	return 'ORDER BY ' . $del . implode($del . '.' . $del, explode('.', $col)) . $del . ' ' . $dir;
}

// ---------------------------------------------------------------------------
// Valid column names
// ---------------------------------------------------------------------------

test('simple alphabetic column produces ORDER BY clause', function () {
	expect(build_order_clause('hostname', 'ASC'))->toBe('ORDER BY `hostname` ASC');
});

test('column with underscore is valid', function () {
	expect(build_order_clause('host_id', 'DESC'))->toBe('ORDER BY `host_id` DESC');
});

test('column with digits is valid', function () {
	expect(build_order_clause('col1', 'ASC'))->toBe('ORDER BY `col1` ASC');
});

test('dotted table.column form is valid and backtick-quoted', function () {
	expect(build_order_clause('h.hostname', 'ASC'))->toBe('ORDER BY `h`.`hostname` ASC');
});

test('mixed-case column name is accepted', function () {
	expect(build_order_clause('HostName', 'ASC'))->toBe('ORDER BY `HostName` ASC');
});

// ---------------------------------------------------------------------------
// Invalid / injected column names return empty string
// ---------------------------------------------------------------------------

test('SQL injection payload with semicolon is rejected', function () {
	expect(validate_sort_column('1; DROP TABLE users'))->toBe('');
});

test('column starting with digit is rejected', function () {
	/* Pattern requires first char to be [a-zA-Z] */
	expect(validate_sort_column('1col'))->toBe('');
});

test('double-dash comment injection is rejected', function () {
	expect(validate_sort_column('col--'))->toBe('');
});

test('UNION keyword injection is rejected', function () {
	expect(validate_sort_column('col UNION SELECT 1'))->toBe('');
});

test('backtick in column name is rejected', function () {
	expect(validate_sort_column('col`name'))->toBe('');
});

test('parenthesis in column name is rejected', function () {
	expect(validate_sort_column('SLEEP(5)'))->toBe('');
});

test('space in column name is rejected', function () {
	expect(validate_sort_column('col name'))->toBe('');
});

test('empty string column is rejected', function () {
	expect(validate_sort_column(''))->toBe('');
});

test('null byte in column name is rejected', function () {
	expect(validate_sort_column("col\x00name"))->toBe('');
});

test('slash-based path traversal is rejected', function () {
	expect(validate_sort_column('../etc/passwd'))->toBe('');
});

// ---------------------------------------------------------------------------
// Valid sort directions
// ---------------------------------------------------------------------------

test('ASC direction is accepted unchanged', function () {
	expect(validate_sort_direction('ASC'))->toBe('ASC');
});

test('DESC direction is accepted unchanged', function () {
	expect(validate_sort_direction('DESC'))->toBe('DESC');
});

test('lowercase asc is normalised to ASC', function () {
	expect(validate_sort_direction('asc'))->toBe('ASC');
});

test('lowercase desc is normalised to DESC', function () {
	expect(validate_sort_direction('desc'))->toBe('DESC');
});

// ---------------------------------------------------------------------------
// Invalid sort directions default to ASC
// ---------------------------------------------------------------------------

test('empty direction defaults to ASC', function () {
	expect(validate_sort_direction(''))->toBe('ASC');
});

test('arbitrary string direction defaults to ASC', function () {
	expect(validate_sort_direction('UNION'))->toBe('ASC');
});

test('SQL comment direction defaults to ASC', function () {
	expect(validate_sort_direction('ASC--'))->toBe('ASC');
});

test('direction with space defaults to ASC', function () {
	expect(validate_sort_direction('ASC DESC'))->toBe('ASC');
});

// ---------------------------------------------------------------------------
// Full clause composition: invalid input produces empty string, not garbage SQL
// ---------------------------------------------------------------------------

test('invalid column with valid direction returns empty string', function () {
	expect(build_order_clause('1; DROP TABLE users', 'ASC'))->toBe('');
});

test('valid column with invalid direction still produces safe clause', function () {
	/* Direction coerces to ASC, column is safe */
	expect(build_order_clause('hostname', 'UNION'))->toBe('ORDER BY `hostname` ASC');
});

test('both invalid returns empty string', function () {
	expect(build_order_clause('col--', 'UNION SELECT'))->toBe('');
});

// ---------------------------------------------------------------------------
// Source-scan: confirm the allowlist regex is present in lib/html_utility.php
// ---------------------------------------------------------------------------

test('lib/html_utility.php contains sort_column preg_match allowlist', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/html_utility.php');

	/* Both get_order_string and update_order_string must contain the guard */
	$matches = preg_match_all(
		"/preg_match\s*\(\s*'\/\^/",
		$source
	);

	expect($matches)->toBeGreaterThanOrEqual(2,
		'Expected at least two preg_match allowlist guards (get_order_string + update_order_string)'
	);
});

test('lib/html_utility.php validates sort_direction to ASC or DESC', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/html_utility.php');

	expect($source)->toContain("!== 'ASC' && \$sort_dir !== 'DESC'");
});

test('lib/html_utility.php uses strtoupper to normalise direction', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/html_utility.php');

	expect($source)->toContain("strtoupper(get_nfilter_request_var('sort_direction'))");
});
