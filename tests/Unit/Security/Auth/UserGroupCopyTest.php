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

namespace CactiUserGroupCopyFixture;

use PDO;
use PDOException;

/*
 * Runs the unchanged user_group_copy() from user_group_admin.php against an
 * in-memory SQLite copy of the group tables. The copy must carry the source
 * group's permission and realm rows, and a failed write must leave no new
 * group behind, because under an allow policy a group without its permission
 * rows grants more than its source.
 */

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$GLOBALS['group_copy_pdo']  = null;
$GLOBALS['group_copy_fail'] = '';
$GLOBALS['group_copy_affected'] = 0;

function db_qstr($value) {
	return $GLOBALS['group_copy_pdo']->quote($value);
}

function db_execute_prepared($sql, $params = array()) {
	if ($GLOBALS['group_copy_fail'] !== '' && str_contains($sql, $GLOBALS['group_copy_fail'])) {
		return false;
	}

	$GLOBALS['group_copy_affected'] = 0;

	try {
		$statement = $GLOBALS['group_copy_pdo']->prepare($sql);
		$result    = $statement->execute($params);

		$GLOBALS['group_copy_affected'] = $statement->rowCount();

		return $result;
	} catch (PDOException $e) {
		return false;
	}
}

function db_affected_rows() {
	return $GLOBALS['group_copy_affected'];
}

function db_fetch_insert_id() {
	return $GLOBALS['group_copy_pdo']->lastInsertId();
}

function db_begin_transaction() {
	return $GLOBALS['group_copy_pdo']->beginTransaction();
}

function db_commit_transaction() {
	return $GLOBALS['group_copy_pdo']->commit();
}

function db_rollback_transaction() {
	return $GLOBALS['group_copy_pdo']->rollBack();
}

function group_copy_rows($sql, $params = array()) {
	$statement = $GLOBALS['group_copy_pdo']->prepare($sql);
	$statement->execute($params);

	return $statement->fetchAll(PDO::FETCH_ASSOC);
}

cacti_fixture_source_function(dirname(__DIR__, 4) . '/user_group_admin.php', 'user_group_copy', __NAMESPACE__);

beforeEach(function () {
	$pdo = new PDO('sqlite::memory:');
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$pdo->exec("CREATE TABLE user_auth_group (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		name TEXT NOT NULL,
		description TEXT NOT NULL DEFAULT '',
		graph_settings TEXT DEFAULT NULL,
		login_opts INTEGER NOT NULL DEFAULT 1,
		show_tree TEXT DEFAULT 'on',
		show_list TEXT DEFAULT 'on',
		show_preview TEXT NOT NULL DEFAULT 'on',
		policy_graphs INTEGER NOT NULL DEFAULT 1,
		policy_trees INTEGER NOT NULL DEFAULT 1,
		policy_hosts INTEGER NOT NULL DEFAULT 1,
		policy_graph_templates INTEGER NOT NULL DEFAULT 1,
		enabled TEXT NOT NULL DEFAULT 'on')");
	$pdo->exec('CREATE TABLE user_auth_group_perms (group_id INTEGER NOT NULL, item_id INTEGER NOT NULL, type INTEGER NOT NULL, PRIMARY KEY (group_id, item_id, type))');
	$pdo->exec('CREATE TABLE user_auth_group_realm (group_id INTEGER NOT NULL, realm_id INTEGER NOT NULL, PRIMARY KEY (group_id, realm_id))');
	$pdo->exec('CREATE TABLE user_auth_group_members (group_id INTEGER NOT NULL, user_id INTEGER NOT NULL, PRIMARY KEY (group_id, user_id))');

	/* group 4 sits beside the source so a copy that reads the wrong id is visible */
	$pdo->exec("INSERT INTO user_auth_group (id, name, policy_graphs, policy_hosts) VALUES (3, 'Operators', 1, 2), (4, 'Other', 1, 1)");
	$pdo->exec('INSERT INTO user_auth_group_perms VALUES (3, 10, 1), (3, 11, 1), (3, 20, 3), (4, 99, 1)');
	$pdo->exec('INSERT INTO user_auth_group_realm VALUES (3, 7), (3, 8), (4, 1)');
	$pdo->exec('INSERT INTO user_auth_group_members VALUES (3, 42)');

	$GLOBALS['group_copy_pdo']  = $pdo;
	$GLOBALS['group_copy_fail'] = '';
});

test('copying a group carries its permissions and realms to the new group', function () {
	expect(user_group_copy(3, 'Copy'))->toBeTrue();

	$new = group_copy_rows("SELECT * FROM user_auth_group WHERE name LIKE 'Copy %'");

	expect($new)->toHaveCount(1)
		->and((int) $new[0]['policy_graphs'])->toBe(1)
		->and((int) $new[0]['policy_hosts'])->toBe(2)
		->and(group_copy_rows('SELECT item_id, type FROM user_auth_group_perms WHERE group_id = ? ORDER BY item_id', array($new[0]['id'])))
		->toBe(group_copy_rows('SELECT item_id, type FROM user_auth_group_perms WHERE group_id = 3 ORDER BY item_id'))
		->and(group_copy_rows('SELECT realm_id FROM user_auth_group_realm WHERE group_id = ? ORDER BY realm_id', array($new[0]['id'])))
		->toBe(array(array('realm_id' => 7), array('realm_id' => 8)))
		->and(group_copy_rows('SELECT * FROM user_auth_group_members WHERE group_id = ?', array($new[0]['id'])))->toBe(array());
});

test('a failed permission or realm copy leaves no new group behind', function (string $table) {
	$GLOBALS['group_copy_fail'] = 'INSERT INTO ' . $table;

	expect(user_group_copy(3, 'Broken'))->toBeFalse()
		->and(group_copy_rows('SELECT id FROM user_auth_group ORDER BY id'))->toBe(array(array('id' => 3), array('id' => 4)))
		->and(group_copy_rows('SELECT COUNT(*) AS total FROM user_auth_group_perms'))->toBe(array(array('total' => 4)))
		->and(group_copy_rows('SELECT COUNT(*) AS total FROM user_auth_group_realm'))->toBe(array(array('total' => 3)))
		->and($GLOBALS['group_copy_pdo']->inTransaction())->toBeFalse();
})->with(array('user_auth_group_perms', 'user_auth_group_realm'));

test('copying a missing group reports failure without creating one', function () {
	expect(user_group_copy(55, 'Ghost'))->toBeFalse()
		->and(group_copy_rows('SELECT id FROM user_auth_group ORDER BY id'))->toBe(array(array('id' => 3), array('id' => 4)));
});

test('the copy action reports a failed copy', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/user_group_admin.php');

	expect($source)->toContain("if (!user_group_copy(\$selected_items[\$i], get_nfilter_request_var('group_prefix'))) {\n\t\t\t\t\t\traise_message(2);");
});
