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

require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/type_secure.php';

test('db_qstr_rlike strips dangerous metacharacters (Mutation Protection)', function () {
	// If a developer "mutates" the code and removes the str_replace for '|', this test fails.
	$input = "abc|def";
	$output = db_qstr_rlike($input);
	expect($output)->not->toContain('|');
	expect($output)->toContain('abcdef');
});

test('db_qstr_rlike caps length at 255 (Mutation Protection)', function () {
	$long_input = str_repeat('a', 500);
	$output = db_qstr_rlike($long_input);
	// Result is 'RLIKE ' + ' (1) + 255 + ' (1) = 263
	expect(strlen($output))->toBeLessThanOrEqual(263);
});

test('db_qstr_rlike handles NUL bytes', function () {
	$input = "abc\0def";
	$output = db_qstr_rlike($input);
	expect($output)->not->toContain("\0");
});
