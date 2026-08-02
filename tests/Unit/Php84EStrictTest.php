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
 |
 | E_STRICT can no longer be raised as of PHP 8.0 and reading the constant
 | is deprecated as of PHP 8.4, so the error handlers must not name it.
 +-------------------------------------------------------------------------+
*/

$basePath = dirname(__DIR__, 2);

test('include/global_arrays.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/include/global_arrays.php'))->not->toContain('E_STRICT');
});

test('lib/functions.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/lib/functions.php'))->not->toContain('E_STRICT');
});

test('lib/aggregate.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/lib/aggregate.php'))->not->toContain('E_STRICT');
});

test('lib/rrdcheck.php names no E_STRICT constant', function () use ($basePath) {
	expect(file_get_contents($basePath . '/lib/rrdcheck.php'))->not->toContain('E_STRICT');
});
