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

namespace PermissionAssociationMutationTest;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/auth.php', 'auth_update_permission_associations', __NAMESPACE__);

function gnrv($key, $default = '') {
	return $GLOBALS['permission_request'][$key] ?? $default;
}
function gfrv($key, $filter = FILTER_VALIDATE_INT, $options = []) {
	return filter_var(gnrv($key), $filter, $options);
}
function input_validate_input_number($value, $variable = '') : void {
	if (!ctype_digit((string) $value)) {
		throw new \RuntimeException('Invalid checkbox identity');
	}
}
function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = [], $timeout = 0) {
	$GLOBALS['permission_events'][] = ['write', (int) $params[1]];

	return $GLOBALS['permission_database']->prepare($sql)->execute($params);
}
function reset_user_perms($id) : void {
	$GLOBALS['permission_events'][] = ['user', $id];
}
function reset_group_perms($id) : void {
	$GLOBALS['permission_events'][] = ['group', $id];
}

beforeEach(function () {
	$GLOBALS['permission_database'] = new \PDO('sqlite::memory:');
	$GLOBALS['permission_database']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

	foreach (['user_auth_perms' => 'user_id', 'user_auth_group_perms' => 'group_id'] as $table => $owner) {
		$GLOBALS['permission_database']->exec("CREATE TABLE $table ($owner INTEGER, item_id INTEGER, type INTEGER, PRIMARY KEY ($owner, item_id, type))");

		foreach ([7, 8] as $id) {
			foreach ([9, 10] as $item) {
				foreach ([1, 2, 3, 4] as $type) {
					$GLOBALS['permission_database']->prepare("INSERT INTO $table VALUES (?, ?, ?)")->execute([$id, $item, $type]);
				}
			}
		}
	}
	$GLOBALS['permission_database']->exec('CREATE TABLE user_auth_group_members (group_id INTEGER, user_id INTEGER, PRIMARY KEY (group_id, user_id))');

	foreach ([7, 8] as $owner) {
		foreach ([9, 10] as $item) {
			$GLOBALS['permission_database']->prepare('INSERT INTO user_auth_group_members VALUES (?, ?)')->execute([$owner, $item]);
			$GLOBALS['permission_database']->prepare('INSERT INTO user_auth_group_members VALUES (?, ?)')->execute([$item, $owner]);
		}
	}
	$GLOBALS['permission_request']       = ['id' => '7'];
	$GLOBALS['permission_events']        = [];
	$GLOBALS['permission_original_post'] = $_POST;
});

afterEach(function () {
	$_POST = $GLOBALS['permission_original_post'];
	unset($GLOBALS['permission_database'], $GLOBALS['permission_request'], $GLOBALS['permission_events'], $GLOBALS['permission_original_post']);
});

dataset('permission association changes', function () {
	foreach ([false, true] as $group) {
		foreach ([0, 1, 2, 3, 4] as $type) {
			foreach ([false, true] as $grant) {
				yield ($group ? 'group' : 'user') . ":$type:" . ($grant ? 'grant' : 'remove') => [$group, $type, $grant];
			}
		}
	}
});

test('association updates preserve other owners and types and invalidate after writes', function (bool $group, int $type, bool $grant) {
	$GLOBALS['permission_request']['drp_action'] = $grant ? '1' : '2';
	$_POST                                       = ['chk_9' => 'on', 'chk_11' => 'on', 'chk_9_invalid' => 'on', 'unrelated' => 'on'];
	$table                                       = $type === 0 ? 'user_auth_group_members' : ($group ? 'user_auth_group_perms' : 'user_auth_perms');
	$owner                                       = $group ? 'group_id' : 'user_id';
	$item                                        = $type === 0 ? ($group ? 'user_id' : 'group_id') : 'item_id';
	$condition                                   = $type === 0 ? '' : ' AND type = ' . $type;
	$count                                       = function (int $owner_id, int $item_id) use ($table, $owner, $item, $condition) : int {
		return (int) $GLOBALS['permission_database']->query("SELECT COUNT(*) FROM $table WHERE $owner = $owner_id AND $item = $item_id $condition")->fetchColumn();
	};
	auth_update_permission_associations($type, $group);
	expect($count(7, 9))->toBe($grant ? 1 : 0);
	expect($count(7, 11))->toBe($grant ? 1 : 0);
	expect($count(7, 10))->toBe(1);
	expect($count(8, 9))->toBe(1);

	if ($type !== 0) {
		$other_type = $type === 1 ? 2 : 1;
		expect((int) $GLOBALS['permission_database']->query("SELECT COUNT(*) FROM $table WHERE $owner = 7 AND item_id = 9 AND type = $other_type")->fetchColumn())->toBe(1);
	}
	$expected = $group && $type === 0
		? [['write', 9], ['user', 9], ['write', 11], ['user', 11]]
		: [['write', 9], ['write', 11], [$group ? 'group' : 'user', 7]];
	expect($GLOBALS['permission_events'])->toBe($expected);
})->with('permission association changes');

test('an empty selection preserves rows and the existing reset behavior', function (bool $group, int $type, bool $grant) {
	$GLOBALS['permission_request']['drp_action'] = $grant ? '1' : '2';
	$_POST                                       = ['chk_invalid' => 'on'];
	auth_update_permission_associations($type, $group);
	expect($GLOBALS['permission_events'])->toBe($group && $type === 0 ? [] : [[$group ? 'group' : 'user', 7]]);
	expect((int) $GLOBALS['permission_database']->query('SELECT COUNT(*) FROM user_auth_perms')->fetchColumn())->toBe(16);
	expect((int) $GLOBALS['permission_database']->query('SELECT COUNT(*) FROM user_auth_group_perms')->fetchColumn())->toBe(16);
	expect((int) $GLOBALS['permission_database']->query('SELECT COUNT(*) FROM user_auth_group_members')->fetchColumn())->toBe(8);
})->with('permission association changes');
