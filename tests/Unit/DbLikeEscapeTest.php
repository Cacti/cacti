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
 * db_like_escape() neutralises the LIKE metacharacters so a value matches
 * literally under a bound LIKE ? clause. The MySQL semantics (that '\%' and '\_'
 * become the literal characters) are verified separately against a live MariaDB;
 * these tests pin the escaping the helper produces.
 */

require_once CACTI_PATH_TESTS . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/database.php';

test('escapes percent, underscore and backslash for LIKE', function () {
	expect(db_like_escape('100%'))->toBe('100\\%');
	expect(db_like_escape('a_b'))->toBe('a\\_b');
	expect(db_like_escape('c\\d'))->toBe('c\\\\d');
});

test('escapes the backslash before the wildcards so nothing is left unescaped', function () {
	// a raw backslash must not become a LIKE escape for a following char
	expect(db_like_escape('\\%'))->toBe('\\\\\\%');
});

test('leaves ordinary text untouched', function () {
	expect(db_like_escape('router-01.example.net'))->toBe('router-01.example.net');
	expect(db_like_escape(''))->toBe('');
});

test('neutralises a wildcard-injection payload', function () {
	// the reported filter/PTR payloads relied on an unescaped % matching every row
	expect(db_like_escape('%'))->toBe('\\%');
	expect(db_like_escape('foo%" OR "1"="1'))->toContain('\\%');
	expect(db_like_escape('_'))->toBe('\\_');
});

test('returns a string for non-string input', function () {
	expect(db_like_escape(12345))->toBe('12345');
	expect(db_like_escape(null))->toBe('');
});

test('poller_automation binds the PTR hostname with db_like_escape', function () {
	$src = file_get_contents(CACTI_PATH_BASE . '/poller_automation.php');

	// the reverse-DNS hostname must reach LIKE via a bound parameter, not concat
	expect($src)->toContain('hostname = ? OR hostname LIKE ?');
	expect($src)->toContain('db_like_escape($hostname) . \'%\'');
	expect($src)->not->toContain('hostname LIKE "\' . $hostname . \'%"');
});
