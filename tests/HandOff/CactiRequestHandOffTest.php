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
 * HandOff tests confirm the migration surface: any caller of set_request_var,
 * isset_request_var, or get_request_var must now go through CactiRequest.
 * These tests catch regressions if the superglobal access patterns are
 * re-introduced during merges.
 */

test('HandOff: html_utility uses CactiRequest::has not isset($_REQUEST[', function () {
	$contents = file_get_contents(__DIR__ . '/../../lib/html_utility.php');
	expect($contents)->toContain('CactiRequest::has(');
	expect($contents)->not->toContain('isset($_REQUEST[');
});

test('HandOff: set_request_var uses CactiRequest::set not direct $_REQUEST assign', function () {
	$contents = file_get_contents(__DIR__ . '/../../lib/html_utility.php');
	expect($contents)->toContain('CactiRequest::set(');
	expect($contents)->not->toContain('$_REQUEST[$variable]       = $value');
});

test('HandOff: global.php ajax detection uses CactiRequest headers', function () {
	$contents = file_get_contents(__DIR__ . '/../../include/global.php');
	expect($contents)->toContain('CactiRequest::current()->headers->get(');
});

test('HandOff: global.php cookie access uses CactiRequest cookies bag', function () {
	$contents = file_get_contents(__DIR__ . '/../../include/global.php');
	expect($contents)->toContain('CactiRequest::current()->cookies->');
	expect($contents)->not->toContain("\$_COOKIE['CactiTab']");
});
