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
 * get_allowed_tree_content(). The unchanged function runs against SQLite and
 * must return nothing for a tree outside the user's allowed trees, including
 * when the user may view no tree at all.
 */

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

TreeSqliteFixture::install(__NAMESPACE__);

cacti_fixture_source_function(dirname(__DIR__, 2) . '/lib/auth.php', 'get_allowed_tree_content', __NAMESPACE__);

$GLOBALS['tree_content_allowed'] = [];

function get_allowed_trees(bool $edit = false, bool $return_sql = false, string $sql_where = '', string $sql_order = 'name', string $sql_limit = '', int &$total_rows = 0, int $user_id = 0) : array {
	$trees = [];

	foreach ($GLOBALS['tree_content_allowed'] as $id) {
		$trees[] = ['id' => $id, 'name' => "tree $id"];
	}

	return $trees;
}

function is_tree_allowed(int $tree_id, int $user_id = 0) : bool {
	return in_array($tree_id, $GLOBALS['tree_content_allowed'], true);
}

function is_tree_branch_empty(int $tree_id, int $parent = 0) : bool {
	return false;
}

function is_device_allowed(int $device_id, int $user_id = 0) : bool {
	return true;
}

function titles(int $tree_id, array $allowed) : array {
	$GLOBALS['tree_content_allowed'] = $allowed;

	return array_column(get_allowed_tree_content($tree_id, 0), 'title');
}

beforeEach(function () {
	TreeSqliteFixture::reset();
	TreeSqliteFixture::$pdo->exec("INSERT INTO graph_tree (id, name) VALUES (1, 'one'), (2, 'two')");
	TreeSqliteFixture::items([
		['parent' => 0, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'one branch'],
		['parent' => 0, 'position' => 1, 'graph_tree_id' => 2, 'title' => 'two branch'],
	]);
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
