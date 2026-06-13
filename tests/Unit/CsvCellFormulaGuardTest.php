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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';

// Negative numbers are data, not formulas, and must not gain a leading quote.

test('cacti_csv_cell leaves a negative number unquoted', function () {
	expect(cacti_csv_cell('-123.4'))->toBe('"-123.4"');
});

test('cacti_csv_cell leaves a leading-plus number unquoted', function () {
	expect(cacti_csv_cell('+1'))->toBe('"+1"');
});

test('cacti_csv_cell leaves a plain decimal unquoted', function () {
	expect(cacti_csv_cell('1.5'))->toBe('"1.5"');
});

// Formula text still gets the single-quote guard.

test('cacti_csv_cell quotes an equals formula', function () {
	expect(cacti_csv_cell('=cmd'))->toBe('"\'=cmd"');
});

test('cacti_csv_cell quotes an at-function formula', function () {
	expect(cacti_csv_cell('@SUM(1)'))->toBe('"\'@SUM(1)"');
});

// Leading whitespace must not hide a formula trigger.

test('cacti_csv_cell quotes a formula behind leading spaces', function () {
	expect(cacti_csv_cell('  =1+1'))->toBe('"\'  =1+1"');
});

// A lone sign is not a number, so it stays guarded.

test('cacti_csv_cell quotes a bare minus sign', function () {
	expect(cacti_csv_cell('-'))->toBe('"\'-"');
});

// Plain text without a trigger is left alone.

test('cacti_csv_cell leaves ordinary text unquoted', function () {
	expect(cacti_csv_cell('hostname'))->toBe('"hostname"');
});
