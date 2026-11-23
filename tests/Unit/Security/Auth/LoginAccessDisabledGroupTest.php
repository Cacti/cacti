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
 * A group that is disabled must not count, matching is_realm_allowed(), and
 * the guest view flags are read from the group, where they are stored.
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
		$db->exec('CREATE TABLE user_auth_group (id INTEGER PRIMARY KEY, enabled TEXT, show_tree TEXT, show_list TEXT, show_preview TEXT)');
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
	return $config_name === 'guest_user' ? $GLOBALS['login_access_guest_user'] : '';
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

function login_access_disabled_group_seed(array $groups, array $members, array $group_realms, array $user_realms = array(), $guest_user = 0) {
	$db = db();

	$GLOBALS['login_access_guest_user'] = $guest_user;

	foreach (array('user_auth_realm', 'user_auth_group', 'user_auth_group_members', 'user_auth_group_realm') as $table) {
		$db->exec("DELETE FROM $table");
	}

	/* a group is either its enabled value or array(enabled, show_tree, show_list, show_preview) */
	foreach ($groups as $id => $group) {
		$group = array_merge(array($id), is_array($group) ? $group : array($group, '', '', ''));

		$db->prepare('INSERT INTO user_auth_group (id, enabled, show_tree, show_list, show_preview) VALUES (?, ?, ?, ?, ?)')->execute($group);
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

/* Runs the check with warnings and notices collected, so a read of a column the query did not return is seen. */
function login_access_with_messages(array $user) {
	$messages = array();

	set_error_handler(function ($level, $message) use (&$messages) {
		$messages[] = $message;

		return true;
	});

	try {
		$access = auth_user_has_access($user);
	} finally {
		restore_error_handler();
	}

	return array($access, $messages);
}

test('with a guest user configured, a view flag on an enabled group counts as access', function ($flags) use ($loginUser) {
	login_access_disabled_group_seed(array(1 => array_merge(array('on'), $flags)), array(array(1, 7)), array(), array(), 3);

	expect(login_access_with_messages($loginUser))->toBe(array(true, array()));
})->with(array(
	'tree'    => array(array('on', '', '')),
	'list'    => array(array('', 'on', '')),
	'preview' => array(array('', '', 'on')),
))->skip(!extension_loaded('pdo_sqlite'), 'pdo_sqlite is not available');

test('with a guest user configured, a group without view flags grants nothing and raises no warning', function () use ($loginUser) {
	login_access_disabled_group_seed(array(1 => 'on'), array(array(1, 7)), array(), array(), 3);

	expect(login_access_with_messages($loginUser))->toBe(array(false, array()));
})->skip(!extension_loaded('pdo_sqlite'), 'pdo_sqlite is not available');

test('the view flags of a disabled group do not count', function () use ($loginUser) {
	login_access_disabled_group_seed(array(1 => array('', 'on', 'on', 'on')), array(array(1, 7)), array(), array(), 3);

	expect(login_access_with_messages($loginUser))->toBe(array(false, array()));
})->skip(!extension_loaded('pdo_sqlite'), 'pdo_sqlite is not available');

test('without a guest user, the view flags of a group are not access', function () use ($loginUser) {
	login_access_disabled_group_seed(array(1 => array('on', 'on', 'on', 'on')), array(array(1, 7)), array());

	expect(login_access_with_messages($loginUser))->toBe(array(false, array()));
})->skip(!extension_loaded('pdo_sqlite'), 'pdo_sqlite is not available');
