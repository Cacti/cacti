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
require_once __DIR__ . '/../../include/global.php';
require_once __DIR__ . '/../../lib/functions.php';

// --- get_current_script_name ---

test('get_current_script_name function exists', function () {
	expect(function_exists('get_current_script_name'))->toBeTrue();
});

test('get_current_script_name returns a string', function () {
	$_SERVER['SCRIPT_NAME'] = '/cacti/index.php';

	expect(get_current_script_name())->toBeString();
});

test('get_current_script_name returns basename from SCRIPT_NAME', function () {
	$_SERVER['SCRIPT_NAME'] = '/cacti/index.php';

	expect(get_current_script_name())->toBe('index.php');
});

test('get_current_script_name strips leading path segments', function () {
	$_SERVER['SCRIPT_NAME'] = '/var/www/html/cacti/graph_view.php';

	expect(get_current_script_name())->toBe('graph_view.php');
});

test('get_current_script_name returns empty string when no server vars set', function () {
	unset($_SERVER['SCRIPT_NAME'], $_SERVER['SCRIPT_FILENAME']);

	expect(get_current_script_name())->toBe('');
});
