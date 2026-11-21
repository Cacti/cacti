<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * auth_user_has_access() decides at login whether an account has any access.
 * A group that is disabled must not count, matching is_realm_allowed().
 *
 * The function is taken from lib/auth.php and loaded into this namespace,
 * whose db_*() functions run its real SQL against an in-memory SQLite
 * database, so the queries are executed rather than pattern matched.
 */

namespace LoginAccessDisabledGroupTest;

function db() {
	static $db = null;

	if ($db === null) {
		$db = new \PDO('sqlite::memory:');
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$db->exec('CREATE TABLE user_auth_realm (realm_id INTEGER, user_id INTEGER)');
		$db->exec('CREATE TABLE user_auth_group (id INTEGER PRIMARY KEY, enabled TEXT)');
		$db->exec('CREATE TABLE user_auth_group_members (group_id INTEGER, user_id INTEGER)');
		$db->exec('CREATE TABLE user_auth_group_realm (group_id INTEGER, realm_id INTEGER)');
	}

	return $db;
}

function db_fetch_cell_prepared($sql, $params = array(), $col_name = '', $log = true, $db_conn = false) {
	$stmt = db()->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchColumn();
}

function db_fetch_assoc_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	$stmt = db()->prepare($sql);
	$stmt->execute($params);

	return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

function read_config_option($config_name, $force = false) {
	return $config_name === 'guest_user' ? 0 : '';
}

$authSource = file_get_contents(dirname(__DIR__, 4) . '/lib/auth.php');

if ($authSource === false || preg_match('/^function auth_user_has_access\(\$user\) \{.*?^}\R/ms', $authSource, $accessMatch) !== 1) {
	throw new \RuntimeException('Unable to extract auth_user_has_access()');
}

$temporary = tempnam(sys_get_temp_dir(), 'auth-access-');
file_put_contents($temporary, '<?php namespace LoginAccessDisabledGroupTest;' . $accessMatch[0]);

try {
	require $temporary;
} finally {
	unlink($temporary);
}

function login_access_disabled_group_seed(array $groups, array $members, array $group_realms, array $user_realms = array()) {
	$db = db();

	foreach (array('user_auth_realm', 'user_auth_group', 'user_auth_group_members', 'user_auth_group_realm') as $table) {
		$db->exec("DELETE FROM $table");
	}

	foreach ($groups as $id => $enabled) {
		$db->prepare('INSERT INTO user_auth_group (id, enabled) VALUES (?, ?)')->execute(array($id, $enabled));
	}

	foreach ($members as $member) {
		$db->prepare('INSERT INTO user_auth_group_members (group_id, user_id) VALUES (?, ?)')->execute($member);
	}

	foreach ($group_realms as $realm) {
		$db->prepare('INSERT INTO user_auth_group_realm (group_id, realm_id) VALUES (?, ?)')->execute($realm);
	}

	foreach ($user_realms as $realm) {
		$db->prepare('INSERT INTO user_auth_realm (realm_id, user_id) VALUES (?, ?)')->execute($realm);
	}
}

$loginUser = array('id' => 7, 'show_tree' => '', 'show_list' => '', 'show_preview' => '');

test('the realms of an enabled group count as access', function () use ($loginUser) {
	login_access_disabled_group_seed(array(1 => 'on'), array(array(1, 7)), array(array(1, 8)));

	expect(auth_user_has_access($loginUser))->toBeTrue();
})->skip(!extension_loaded('pdo_sqlite'), 'pdo_sqlite is not available');

test('the realms of a disabled group do not count as access', function () use ($loginUser) {
	login_access_disabled_group_seed(array(1 => ''), array(array(1, 7)), array(array(1, 8)));

	expect(auth_user_has_access($loginUser))->toBeFalse();
})->skip(!extension_loaded('pdo_sqlite'), 'pdo_sqlite is not available');

test('an enabled group still counts when another group of the user is disabled', function () use ($loginUser) {
	login_access_disabled_group_seed(array(1 => '', 2 => 'on'), array(array(1, 7), array(2, 7)), array(array(1, 8), array(2, 3)));

	expect(auth_user_has_access($loginUser))->toBeTrue();
})->skip(!extension_loaded('pdo_sqlite'), 'pdo_sqlite is not available');

test('another user\'s enabled group and a group without realms grant nothing', function () use ($loginUser) {
	login_access_disabled_group_seed(array(1 => 'on', 2 => 'on'), array(array(1, 9), array(2, 7)), array(array(1, 8)));

	expect(auth_user_has_access($loginUser))->toBeFalse();
})->skip(!extension_loaded('pdo_sqlite'), 'pdo_sqlite is not available');

test('a realm granted directly to the user is unaffected by a disabled group', function () use ($loginUser) {
	login_access_disabled_group_seed(array(1 => ''), array(array(1, 7)), array(array(1, 8)), array(array(8, 7)));

	expect(auth_user_has_access($loginUser))->toBeTrue();
})->skip(!extension_loaded('pdo_sqlite'), 'pdo_sqlite is not available');
