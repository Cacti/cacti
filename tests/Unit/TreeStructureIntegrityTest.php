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
 * on a parent cycle that is already stored. The unchanged lib functions run
 * against SQLite; the fixture's query budget turns a walk that never ends
 * into a failure.
 */

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

TreeSqliteFixture::install(__NAMESPACE__);

$root = dirname(__DIR__, 2);

foreach (['api_tree_move_node', 'api_tree_move_allowed', 'api_tree_parse_node_data', 'api_tree_sort_branch', 'api_tree_get_branch_ordering', 'api_tree_delete_node_content'] as $function) {
	cacti_fixture_source_function($root . '/lib/api_tree.php', $function, __NAMESPACE__);
}

foreach (['get_tree_path', 'grow_dropdown_tree'] as $function) {
	cacti_fixture_source_function($root . '/lib/html_tree.php', $function, __NAMESPACE__);
}

cacti_fixture_source_function($root . '/lib/auth.php', 'is_tree_branch_empty', __NAMESPACE__);

$GLOBALS['tree_integrity_request'] = [];

function input_validate_input_number(mixed $value, string $name = '') : void {
	if (!is_numeric($value)) {
		throw new \RuntimeException("invalid $name");
	}
}

function cacti_log(mixed $message, bool $output = false, string $environ = 'CMDPHP', mixed $level = '') : bool {
	return true;
}

function set_config_option(string $name, mixed $value, bool $remote = false) : void {
}

function isset_request_var(string $name) : bool {
	return isset($GLOBALS['tree_integrity_request'][$name]);
}

function get_request_var(string $name, mixed $default = '') : mixed {
	return $GLOBALS['tree_integrity_request'][$name] ?? $default;
}

function get_simple_graph_perms(int $user_id) : bool {
	return false;
}

function get_allowed_graphs(string $sql_where = '', mixed $sql_order = '', string $sql_limit = '', int &$total_rows = 0, int $user_id = 0, int $graph_id = 0) : array {
	return [];
}

function get_allowed_devices(string $sql_where = '', string $sql_order = '', string $sql_limit = '', int &$total_rows = 0, int $user_id = 0, int $device_id = 0) : array {
	return [];
}

beforeEach(function () {
	$_SESSION = ['sess_user_id' => 1];

	TreeSqliteFixture::reset(5000);
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
	$_SESSION = [];
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

	$GLOBALS['tree_integrity_request'] = ['node' => 'tbranch-22'];

	expect(get_tree_path())->toContain('tbranch-21');
});

test('the branch dropdown stops on a parent cycle', function () {
	cycle();

	ob_start();
	grow_dropdown_tree(1, 20, 'parent');
	$html = ob_get_clean();

	expect($html)->toContain('Y');
});
