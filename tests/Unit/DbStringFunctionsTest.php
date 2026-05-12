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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

it('builds an IN clause from a list of values', function () {
	expect(array_to_sql_or([1, 2, 3], 'col'))
		->toBe("(col IN('1','2','3'))");
});

it('pops a trailing null before building the IN clause', function () {
	expect(array_to_sql_or([1, null], 'col'))
		->toBe("(col IN('1'))");
});

it('returns empty string for an empty input array', function () {
	expect(array_to_sql_or([], 'col'))->toBe('');
});

it('returns empty string when the only element is null', function () {
	expect(array_to_sql_or([null], 'col'))->toBe('');
});

it('escapes embedded quotes and semicolons via db_qstr', function () {
	// No live db_conn, so db_qstr falls back to its manual escape branch:
	// single quote -> backslash + quote.
	expect(array_to_sql_or(["O'Brien", 'a;b'], 'name'))
		->toBe("(name IN('O\\'Brien','a;b'))");
});

it('returns NULL literal for a null argument', function () {
	expect(db_qstr(null))->toBe('NULL');
});

it('quotes a plain string with single quotes', function () {
	expect(db_qstr('plain'))->toBe("'plain'");
});

it('escapes a single quote with a backslash (no live conn)', function () {
	// Byte-exact: 27 69 74 5C 27 73 27 = ' i t \ ' s '
	$expected = chr(0x27) . 'it' . chr(0x5C) . chr(0x27) . 's' . chr(0x27);

	expect(db_qstr("it's"))->toBe($expected);
});

it('doubles a backslash (no live conn)', function () {
	// Byte-exact: 27 61 5C 5C 62 27 = ' a \ \ b '
	$expected = chr(0x27) . 'a' . chr(0x5C) . chr(0x5C) . 'b' . chr(0x27);

	expect(db_qstr("a\\b"))->toBe($expected);
});

it('escapes the NUL byte with a backslash (no live conn)', function () {
	// Byte-exact: 27 61 5C 00 62 27 = ' a \ NUL b '
	$expected = chr(0x27) . 'a' . chr(0x5C) . chr(0x00) . 'b' . chr(0x27);

	expect(db_qstr("a\0b"))->toBe($expected);
});

it('delegates to PDO->quote when given a connection', function () {
	$conn = new PDO('sqlite::memory:');
	// SQLite quoting doubles single quotes rather than backslash-escaping.
	expect(db_qstr("it's", $conn))->toBe("'it''s'");
});

it('strips a trailing run of semicolons', function () {
	// db_strip_control_chars() = trim(clean_up_lines($sql), ';').
	// Two clean_up_lines() implementations may be in scope depending on
	// which sibling tests have already loaded lib/functions.php:
	//   * the unit stub (trim whitespace),
	//   * the production version (collapse CR/LF runs to a single space).
	// Inputs without surrounding whitespace and without embedded newlines
	// produce the same result under both, so we exercise that contract.
	expect(db_strip_control_chars('SELECT 1;;;'))->toBe('SELECT 1');
});

it('wraps a bare column name in backticks', function () {
	expect(db_format_index_create('col'))->toBe('`col`');
});

it('keeps an index expression that already ends in ) verbatim', function () {
	expect(db_format_index_create('col(10)'))->toBe('col(10)');
});

it('formats an array of mixed bare and length-prefixed columns', function () {
	expect(db_format_index_create(['a', 'b(5)', 'c']))->toBe('`a`,b(5),`c`');
});

it('trims whitespace around array entries before backtick-wrapping', function () {
	expect(db_format_index_create(['  a  ']))->toBe('`a`');
});

it('strips surrounding whitespace and stray backticks from a string index', function () {
	expect(db_format_index_create('  ` col `  '))->toBe('`col`');
});
