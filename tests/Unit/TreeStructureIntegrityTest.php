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

namespace CactiTreeStructureIntegrityFixture;

use TreeSqliteFixture;

/*
 * graph_tree_items is an adjacency list. api_tree_move_node() must only
 * accept a new parent in the same tree that is neither the node nor one of
 * its descendants, and the functions that walk parent links must terminate
 * on a parent cycle that is already stored. The lib functions run against
 * SQLite; the fixture's query budget turns a walk that never ends into a
 * failure.
 */

require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

beforeEach(function () {
	$_SESSION = [SESS_USER_ID => 1];

	TreeSqliteFixture::reset(5000);
	TreeSqliteFixture::user(1, ['policy_graphs' => 2, 'policy_hosts' => 2, 'policy_graph_templates' => 2]);
	TreeSqliteFixture::$pdo->exec("INSERT INTO graph_tree (id, name, sort_type) VALUES (1, 'one', 1), (2, 'two', 1)");

	// tree 1: A(1) > B(2) > C(3), D(4) at the root; tree 2: E(10)
	TreeSqliteFixture::items([
		['id' => 1, 'parent' => 0, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'A'],
		['id' => 2, 'parent' => 1, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'B'],
		['id' => 3, 'parent' => 2, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'C'],
		['id' => 4, 'parent' => 0, 'position' => 2, 'graph_tree_id' => 1, 'title' => 'D'],
		['id' => 10, 'parent' => 0, 'position' => 1, 'graph_tree_id' => 2, 'title' => 'E'],
	]);
});

afterEach(function () {
	TreeSqliteFixture::restore();
});

function cycle() : void {
	// X(20) <-> Y(21), Z(22) hangs off Y; none of them reaches the root
	TreeSqliteFixture::items([
		['id' => 20, 'parent' => 21, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'X'],
		['id' => 21, 'parent' => 20, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'Y'],
		['id' => 22, 'parent' => 21, 'position' => 2, 'graph_tree_id' => 1, 'title' => 'Z'],
	]);
}

test('moving a branch under its own descendant is refused before any write', function () {
	$before = TreeSqliteFixture::parents();

	api_tree_move_node(1, 'tbranch:1', 'tbranch:3', 0);

	expect(TreeSqliteFixture::parents())->toBe($before);
});

test('moving a branch under itself is refused', function () {
	$before = TreeSqliteFixture::parents();

	api_tree_move_node(1, 'tbranch:2', 'tbranch:2', 0);

	expect(TreeSqliteFixture::parents())->toBe($before);
});

test('moving a branch under an item of another tree is refused', function () {
	$before = TreeSqliteFixture::parents();

	api_tree_move_node(1, 'tbranch:2', 'tbranch:10', 0);

	expect(TreeSqliteFixture::parents())->toBe($before);
});

test('moving an item of another tree is refused', function () {
	$before = TreeSqliteFixture::parents();

	api_tree_move_node(1, 'tbranch:10', 'tbranch:4', 0);

	expect(TreeSqliteFixture::parents())->toBe($before);
});

test('a valid move still reparents the branch', function () {
	api_tree_move_node(1, 'tbranch:2', 'tbranch:4', 0);

	expect(TreeSqliteFixture::parents()[2])->toBe(4);

	api_tree_move_node(1, 'tbranch:2', '#', 0);

	expect(TreeSqliteFixture::parents()[2])->toBe(0);
});

test('branch ordering lookup stops on a parent cycle', function () {
	cycle();

	expect(api_tree_get_branch_ordering(22))->toBe(1);
});

test('sorting a branch stops on a parent cycle', function () {
	cycle();

	api_tree_sort_branch(22, 1);

	expect(TreeSqliteFixture::$queries)->toBeLessThan(5000);
});

test('deleting branch content stops on a parent cycle', function () {
	cycle();

	api_tree_delete_node_content(1, 20);

	expect(TreeSqliteFixture::parents())->not->toHaveKeys([21, 22]);
});

test('the empty branch check stops on a parent cycle', function () {
	cycle();

	expect(is_tree_branch_empty(1, 20))->toBeTrue();
});

test('the tree path stops on a parent cycle', function () {
	cycle();

	$_REQUEST = ['node' => 'tbranch-22'];

	expect(get_tree_path())->toContain('tbranch-21');
});

test('the branch dropdown stops on a parent cycle', function () {
	cycle();

	ob_start();
	grow_dropdown_tree(1, 20, 'parent');
	$html = ob_get_clean();

	expect($html)->toContain('Y');
});

test('a move renumbers only the destination siblings, in position order', function () {
	// tree 1 root by position: A(1), D(2), Y(3), X(9); X has the lower id
	TreeSqliteFixture::items([
		['id' => 29, 'parent' => 0, 'position' => 9, 'graph_tree_id' => 1, 'title' => 'X'],
		['id' => 30, 'parent' => 0, 'position' => 3, 'graph_tree_id' => 1, 'title' => 'Y'],
		['id' => 31, 'parent' => 0, 'position' => 5, 'graph_tree_id' => 2, 'title' => 'F'],
		['id' => 32, 'parent' => 0, 'position' => 7, 'graph_tree_id' => 2, 'title' => 'G'],
	]);

	$other_tree = fn () => TreeSqliteFixture::$pdo->query('SELECT id, position FROM graph_tree_items WHERE graph_tree_id = 2 ORDER BY id')->fetchAll(\PDO::FETCH_KEY_PAIR);
	$before     = $other_tree();

	api_tree_move_node(1, 'tbranch:3', '#', 0);

	expect($other_tree())->toBe($before)
		->and(TreeSqliteFixture::childTitles(0))->toBe(['C', 'A', 'D', 'Y', 'X']);
});
