<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * File-contract tests for the CactiRequest migration in global.php and
 * html_utility.php.  These validate that direct superglobal reads have been
 * replaced without requiring a full bootstrap.
 */

test('global.php ajax detection no longer reads $_SERVER[HTTP_X_REQUESTED_WITH] directly', function () {
	$contents = file_get_contents(__DIR__ . '/../../include/global.php');
	expect($contents)->not->toContain("\$_SERVER['HTTP_X_REQUESTED_WITH']");
	expect($contents)->toContain('CactiRequest::current()->headers->get(');
});

test('global.php no longer reads $_COOKIE[CactiTab] directly', function () {
	$contents = file_get_contents(__DIR__ . '/../../include/global.php');
	expect($contents)->not->toContain("\$_COOKIE['CactiTab']");
	expect($contents)->toContain('cookies->has(');
});

test('global.php uses CactiRequest::has for display_db_errors check', function () {
	$contents = file_get_contents(__DIR__ . '/../../include/global.php');
	expect($contents)->toContain("CactiRequest::has('display_db_errors')");
});

test('html_utility isset_request_var uses CactiRequest::has not $_REQUEST', function () {
	$contents = file_get_contents(__DIR__ . '/../../lib/html_utility.php');
	expect($contents)->not->toContain('return isset($_REQUEST[$variable])');
	expect($contents)->toContain('CactiRequest::has($variable)');
});

test('html_utility get_request_var uses CactiRequest::get not $_REQUEST', function () {
	$contents = file_get_contents(__DIR__ . '/../../lib/html_utility.php');
	expect($contents)->toContain('CactiRequest::get($variable)');
});

test('html_utility set_request_var uses CactiRequest::set not direct superglobal assign', function () {
	$contents = file_get_contents(__DIR__ . '/../../lib/html_utility.php');
	expect($contents)->not->toContain('$_REQUEST[$variable]       = $value');
	expect($contents)->toContain('CactiRequest::set($variable, $value)');
});

test('layout.js adds CSRF token to AJAX requests via ajaxSetup', function () {
	$contents = file_get_contents(__DIR__ . '/../../include/layout.js');
	expect($contents)->toContain('csrfMagicToken');
	expect($contents)->toContain('X-CSRF-Token');
	expect($contents)->toContain('ajaxSetup');
});
