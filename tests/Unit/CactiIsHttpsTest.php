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
 * Tests for cacti_is_https() in lib/functions.php.
 *
 * cacti_is_https() centralises the $_SERVER['HTTPS'] / HTTP_X_FORWARDED_PROTO
 * check that was previously duplicated across global.php, auth_login.php, and
 * graph_xport.php.  All callers now use this single helper.
 */

require_once __DIR__ . '/../../lib/functions.php';

test('returns true when HTTPS is "on"', function () {
	$_SERVER['HTTPS'] = 'on';
	expect(cacti_is_https())->toBeTrue();
});

test('returns true when HTTPS is "ON" (case-insensitive)', function () {
	$_SERVER['HTTPS'] = 'ON';
	expect(cacti_is_https())->toBeTrue();
});

test('returns true when HTTPS is "1"', function () {
	$_SERVER['HTTPS'] = '1';
	expect(cacti_is_https())->toBeTrue();
});

test('returns true when HTTPS is any non-off truthy string', function () {
	$_SERVER['HTTPS'] = 'anything';
	expect(cacti_is_https())->toBeTrue();
});

test('returns false when HTTPS is "off"', function () {
	$_SERVER['HTTPS'] = 'off';
	expect(cacti_is_https())->toBeFalse();
});

test('returns false when HTTPS is "OFF" (case-insensitive)', function () {
	$_SERVER['HTTPS'] = 'OFF';
	expect(cacti_is_https())->toBeFalse();
});

test('returns false when HTTPS is empty string', function () {
	$_SERVER['HTTPS'] = '';
	expect(cacti_is_https())->toBeFalse();
});

test('returns false when HTTPS is "0"', function () {
	$_SERVER['HTTPS'] = '0';
	expect(cacti_is_https())->toBeFalse();
});

test('returns false when HTTPS is not set', function () {
	unset($_SERVER['HTTPS']);
	expect(cacti_is_https())->toBeFalse();
});
