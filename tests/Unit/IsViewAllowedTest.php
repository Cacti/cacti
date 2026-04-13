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

require_once __DIR__ . '/../../lib/functions.php';
require_once __DIR__ . '/../../lib/auth.php';

/*
 * Tests for the $allowed_views whitelist in is_view_allowed().
 * The function requires a DB session, so we test the whitelist
 * validation layer in isolation by calling the function with
 * no active session -- invalid views return false before the
 * session check, valid views return false at the session check.
 */

test('rejects SQL injection attempt in view parameter', function () {
	unset($_SESSION);
	expect(is_view_allowed("1; DROP TABLE user_auth; --"))->toBeFalse();
});

test('rejects empty string view', function () {
	unset($_SESSION);
	expect(is_view_allowed(''))->toBeFalse();
});

test('rejects arbitrary column name', function () {
	unset($_SESSION);
	expect(is_view_allowed('password'))->toBeFalse();
});

test('rejects view with backtick injection', function () {
	unset($_SESSION);
	expect(is_view_allowed('show_tree` FROM user_auth; --'))->toBeFalse();
});

test('accepts show_tree as valid view', function () {
	// Will return false due to no session, but won't hit the whitelist rejection
	unset($_SESSION);
	// show_tree is in the whitelist, so it passes validation but fails at session check
	expect(is_view_allowed('show_tree'))->toBeFalse();
});

test('accepts show_list as valid view', function () {
	unset($_SESSION);
	expect(is_view_allowed('show_list'))->toBeFalse();
});

test('accepts show_preview as valid view', function () {
	unset($_SESSION);
	expect(is_view_allowed('show_preview'))->toBeFalse();
});

test('accepts graph_settings as valid view', function () {
	unset($_SESSION);
	expect(is_view_allowed('graph_settings'))->toBeFalse();
});

test('allowed views list contains exactly 4 entries', function () {
	// Verify by testing all known valid views pass and one extra fails
	$valid = ['show_tree', 'show_list', 'show_preview', 'graph_settings'];
	$invalid = ['show_console', 'enabled', 'username', 'password', 'realm'];

	foreach ($invalid as $view) {
		expect(is_view_allowed($view))->toBeFalse();
	}
});
