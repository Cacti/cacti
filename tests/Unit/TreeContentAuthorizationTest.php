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

namespace CactiTreeContentAuthorizationFixture;

use TreeSqliteFixture;

/*
 * graph_view.php?action=get_node renders a branch through
 * get_allowed_tree_content(). The function runs against SQLite with the real
 * tree permission checks and must return nothing for a tree outside the
 * user's allowed trees, including when the user may view no tree at all.
 */

require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

const USER = 6;

/*
 * The user may view only the trees in $allowed: trees are denied by default
 * and each allowed tree is a tree exception. Graphs are allowed by default,
 * so every branch below holds a visible graph and is not empty.
 */
function titles(int $tree_id, array $allowed) : array {
	$pdo = TreeSqliteFixture::$pdo;
	$pdo->exec('DELETE FROM user_auth_perms');

	foreach ($allowed as $id) {
		$pdo->exec('INSERT INTO user_auth_perms (user_id, item_id, type) VALUES (' . USER . ", $id, 2)");
	}

	$_SESSION = [SESS_USER_ID => USER];

	return array_column(get_allowed_tree_content($tree_id, 0), 'title');
}

beforeEach(function () {
	TreeSqliteFixture::reset();
	TreeSqliteFixture::user(USER, ['policy_trees' => 2]);
	TreeSqliteFixture::$pdo->exec("INSERT INTO graph_tree (id, name) VALUES (1, 'one'), (2, 'two')");
	TreeSqliteFixture::insert('graph_local', [['id' => 31], ['id' => 32]]);
	TreeSqliteFixture::insert('graph_templates_graph', [['local_graph_id' => 31], ['local_graph_id' => 32]]);
	TreeSqliteFixture::items([
		['id' => 1, 'parent' => 0, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'one branch'],
		['id' => 2, 'parent' => 0, 'position' => 1, 'graph_tree_id' => 2, 'title' => 'two branch'],
		['id' => 3, 'parent' => 1, 'position' => 1, 'graph_tree_id' => 1, 'local_graph_id' => 31],
		['id' => 4, 'parent' => 2, 'position' => 1, 'graph_tree_id' => 2, 'local_graph_id' => 32],
	]);
});

afterEach(function () {
	TreeSqliteFixture::restore();
});

test('an allowed tree returns its branches', function () {
	expect(titles(1, [1]))->toBe(['one branch']);
});

test('a tree outside the allowed trees returns nothing', function () {
	expect(titles(2, [1]))->toBe([]);
});

test('a user with no allowed tree gets no branch content', function () {
	expect(titles(1, []))->toBe([]);
	expect(titles(2, []))->toBe([]);
});
