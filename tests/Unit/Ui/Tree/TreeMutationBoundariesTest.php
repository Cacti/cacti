<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiTreeMutationBoundariesFixture;

use TreeSqliteFixture;

require_once dirname(__DIR__, 3) . '/Helpers/TreeSqliteFixture.php';

beforeEach(function () {
	$_SESSION = [SESS_USER_ID => 1];
	TreeSqliteFixture::reset();
	TreeSqliteFixture::user(1, ['policy_graphs' => 2, 'policy_hosts' => 2, 'policy_graph_templates' => 2]);
	TreeSqliteFixture::$pdo->exec("INSERT INTO graph_tree (id, name, sort_type) VALUES (1, 'one', 1), (2, 'two', 1)");
	TreeSqliteFixture::items([
		['id' => 1, 'parent' => 0, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'A'],
		['id' => 2, 'parent' => 1, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'B'],
		['id' => 3, 'parent' => 0, 'position' => 2, 'graph_tree_id' => 1, 'title' => 'C'],
		['id' => 4, 'parent' => 0, 'position' => 1, 'graph_tree_id' => 2, 'title' => 'D'],
	]);
});

afterEach(function () {
	TreeSqliteFixture::restore();
});

test('moving within a parent preserves descendants and other trees', function () {
	$parents = TreeSqliteFixture::parents();
	api_tree_move_node(1, 'tbranch:3', '#', 0);
	expect(TreeSqliteFixture::childTitles(0))->toBe(['C', 'A'])
		->and(TreeSqliteFixture::parents())->toBe($parents)
		->and(TreeSqliteFixture::childTitles(0, 2))->toBe(['D']);
});

test('invalid move identities do not query or mutate the tree', function (int $tree, int $leaf, int $parent) {
	TreeSqliteFixture::resetCounters();
	expect(api_tree_move_allowed($tree, $leaf, $parent))->toBeFalse()
		->and(TreeSqliteFixture::$queries)->toBe(0);
})->with([[0, 1, 0], [1, 0, 0], [1, 1, -1]]);

test('branch ordering honors an explicit override and defaults for a missing item', function () {
	TreeSqliteFixture::$pdo->exec('UPDATE graph_tree_items SET sort_children_type = 2 WHERE id = 2');
	expect(api_tree_get_branch_ordering(2))->toBe(2)
		->and(api_tree_get_branch_ordering(999))->toBe(1);
});

test('creating and copying a branch sort only the destination siblings', function () {
	TreeSqliteFixture::$pdo->exec('UPDATE graph_tree SET sort_type = 2 WHERE id = 1');
	ob_start();

	try {
		$created  = api_tree_create_node(1, '#', 0, 'B');
		$response = json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
	} finally {
		ob_end_clean();
	}
	expect($created)->toBeTrue()
		->and($response['text'])->toBe('B')
		->and(TreeSqliteFixture::childTitles(0))->toBe(['A', 'B', 'C']);
	ob_start();

	try {
		api_tree_copy_node(1, 'tbranch:2', '#', 0);
		$copy = json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
	} finally {
		ob_end_clean();
	}
	expect($copy['id'])->toMatch('/^tbranch:\d+$/')
		->and(TreeSqliteFixture::childTitles(0))->toBe(['A', 'B', 'B', 'C'])
		->and(TreeSqliteFixture::childTitles(1))->toBe(['B'])
		->and(TreeSqliteFixture::childTitles(0, 2))->toBe(['D']);
});

test('a failed later delete chunk rolls back the earlier chunk', function () {
	$insert = TreeSqliteFixture::$pdo->prepare('INSERT INTO graph_tree_items (id, parent, graph_tree_id, title) VALUES (?, 1, 1, ?)');

	for ($id = 10; $id < 610; $id++) {
		$insert->execute([$id, 'child ' . $id]);
	}
	$before = TreeSqliteFixture::parents();
	TreeSqliteFixture::$pdo->exec("CREATE TRIGGER reject_tree_delete BEFORE DELETE ON graph_tree_items WHEN OLD.id = 609 BEGIN SELECT RAISE(ABORT, 'fixture delete failure'); END");
	TreeSqliteFixture::resetCounters();
	api_tree_delete_node_content(1, 1);
	expect(TreeSqliteFixture::parents())->toBe($before)
		->and(TreeSqliteFixture::$pdo->inTransaction())->toBeFalse()
		->and(array_filter(TreeSqliteFixture::$config_log, fn ($sql) => preg_match('/^\s*INSERT\s+INTO\s+`?settings`?/i', $sql)))->toBeEmpty();
});

test('a hostless leaf can render permitted non-query graphs', function () {
	$GLOBALS['settings_user'] = [];
	TreeSqliteFixture::$pdo->exec('UPDATE user_auth SET policy_graphs = 1, policy_hosts = 1, policy_graph_templates = 1 WHERE id = 1');
	TreeSqliteFixture::insert('host', [['id' => 12, 'description' => 'twelve']]);
	TreeSqliteFixture::insert('graph_local', [['id' => 100, 'host_id' => 12]]);
	TreeSqliteFixture::insert('graph_templates_graph', [['local_graph_id' => 100, 'title_cache' => 'hostless']]);
	expect(implode('', create_data_query_branch(['id' => 1, 'host_id' => 0])))->toContain('Non Query Based');
});
