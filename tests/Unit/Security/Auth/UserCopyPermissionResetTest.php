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
 * Batch copy overwrites an existing user's permissions, realms, policies and
 * group memberships with the template's.  The copied row carries the
 * template's reset_perms, which can equal the user's own, so the overwritten
 * user must be reset explicitly or a signed-in session keeps the old access.
 */

namespace UserCopyPermissionResetTest;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/auth.php', 'user_copy', __NAMESPACE__);

function input_validate_input_number($value, $variable = '') : void {
}

function cacti_sizeof($value) : int {
	return is_countable($value) ? count($value) : 0;
}

function raise_message($id) : void {
}

function db_fetch_row_prepared($sql, $params = []) {
	return $GLOBALS['user_copy_users'][$params[0]] ?? [];
}

function db_fetch_assoc_prepared($sql, $params = []) : array {
	return [];
}

function db_execute_prepared($sql, $params = []) : bool {
	return true;
}

function db_execute($sql) : bool {
	return true;
}

function sql_save($row, $table, $keys = 'id', $autoinc = true) {
	return $row['id'] ?: 99;
}

function api_plugin_hook_function($name, $data = []) {
	return $data;
}

function reset_user_perms($id) : void {
	$GLOBALS['user_copy_resets'][] = $id;
}

beforeEach(function () : void {
	// the template and the existing user share the default reset_perms of 0
	$GLOBALS['user_copy_users'] = [
		'template' => ['id' => 3, 'username' => 'template', 'realm' => 0, 'reset_perms' => 0],
		'existing' => [
			'id'                   => 7,
			'username'             => 'existing',
			'password'             => 'x',
			'realm'                => 0,
			'full_name'            => '',
			'email_address'        => '',
			'must_change_password' => '',
			'enabled'              => 'on',
			'reset_perms'          => 0,
		],
	];
	$GLOBALS['user_copy_resets'] = [];
});

test('a batch copy over an existing user resets that user', function () : void {
	expect(user_copy('template', 'existing', 0, 0, true))->toBe(7)
		->and($GLOBALS['user_copy_resets'])->toBe([7]);
});

test('a copy to a new user resets nobody', function () : void {
	expect(user_copy('template', 'fresh', 0, 0, false))->toBe(99)
		->and($GLOBALS['user_copy_resets'])->toBe([]);
});
