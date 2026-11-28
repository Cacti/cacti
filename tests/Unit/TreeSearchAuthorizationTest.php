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

namespace CactiTreeSearchAuthorizationFixture;

use TreeSqliteFixture;

/*
 * graph_view.php?action=ajax_search runs get_matching_nodes(). The function
 * runs against SQLite with the real tree and graph permission checks: it may
 * only return nodes of trees the user may view, may only open a branch for a
 * matched graph the user may view, needs at least three characters like the
 * client, and must stop on a parent cycle.
 */

require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

const USER = 4;

/*
 * The user may view only the trees in $allowed: trees are denied by default
 * and each allowed tree is a tree exception.
 */
function search(string $str, array $allowed = [1], bool $sort = true) : array {
	$pdo = TreeSqliteFixture::$pdo;
	$pdo->exec('DELETE FROM user_auth_perms WHERE type = 2');

	foreach ($allowed as $tree_id) {
		$pdo->exec('INSERT INTO user_auth_perms (user_id, item_id, type) VALUES (' . USER . ", $tree_id, 2)");
	}

	$_SESSION = [SESS_USER_ID => USER];
	$_REQUEST = ['str' => $str];

	ob_start();

	try {
		get_matching_nodes();
	} finally {
		$out = ob_get_clean();
	}

	$nodes = json_decode($out === '' ? '[]' : $out, true);

	expect($nodes)->toBeArray();

	if ($sort) {
		sort($nodes);
	}

	return $nodes;
}

beforeEach(function () {
	$GLOBALS['settings_user'] = [];

	TreeSqliteFixture::reset(2000);
	TreeSqliteFixture::user(USER, ['policy_trees' => 2]);
	TreeSqliteFixture::$pdo->exec("INSERT INTO graph_tree (id, name) VALUES (1, 'one'), (2, 'two')");
	TreeSqliteFixture::items([
		['id' => 1, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'Core'],
		['id' => 2, 'parent' => 1, 'graph_tree_id' => 1, 'title' => 'Traffic one'],
		['id' => 3, 'parent' => 0, 'graph_tree_id' => 2, 'title' => 'Edge'],
		['id' => 4, 'parent' => 3, 'graph_tree_id' => 2, 'title' => 'Traffic two'],
	]);
});

afterEach(function () {
	TreeSqliteFixture::restore();
});

test('search returns only nodes of trees the user may view', function () {
	expect(search('Traffic', [1]))->toBe(['tbranch-1', 'tree_anchor-1']);
	expect(search('Traffic', [1, 2]))->toBe(['tbranch-1', 'tbranch-3', 'tree_anchor-1', 'tree_anchor-2']);
	expect(search('Traffic', []))->toBe([]);
});

test('search needs at least three characters', function () {
	expect(search('', [1, 2]))->toBe([]);
	expect(search('Tr', [1, 2]))->toBe([]);
	expect(search('Tra', [1, 2]))->not->toBe([]);
});

test('search stops on a parent cycle', function () {
	TreeSqliteFixture::items([
		['id' => 10, 'parent' => 11, 'graph_tree_id' => 1, 'title' => 'loop a'],
		['id' => 11, 'parent' => 10, 'graph_tree_id' => 1, 'title' => 'loop b'],
		['id' => 12, 'parent' => 11, 'graph_tree_id' => 1, 'title' => 'Looped'],
	]);

	expect(search('Looped', [1]))->toBe([]);
});

test('search reads each matched tree once instead of once per ancestor', function () {
	$insert = TreeSqliteFixture::$pdo->prepare('INSERT INTO graph_tree_items (parent, graph_tree_id, title) VALUES (?, 1, ?)');
	$build  = function (int $parent, int $depth) use (&$build, $insert) : void {
		for ($i = 0; $i < 6; $i++) {
			$insert->execute([$parent, $depth < 4 ? "level $depth" : 'Leaf match']);
			$id = (int) TreeSqliteFixture::$pdo->lastInsertId();

			if ($depth < 4) {
				$build($id, $depth + 1);
			}
		}
	};

	$build(0, 1);
	TreeSqliteFixture::resetCounters();

	$nodes = search('Leaf match', [1]);

	// every branch above the 1,296 matches, plus the tree anchor; the four
	// statements are the match search, the user policy, the tree exception and
	// one read of the matched tree
	expect($nodes)->toHaveCount(6 + 36 + 216 + 1)
		->and(TreeSqliteFixture::$queries)->toBeLessThanOrEqual(4);
});

test('search handles a deep branch without retaining every ancestor prefix', function () {
	$insert = TreeSqliteFixture::$pdo->prepare('INSERT INTO graph_tree_items (id, parent, graph_tree_id, title) VALUES (?, ?, 1, ?)');

	for ($id = 100; $id <= 4100; $id++) {
		$insert->execute([$id, $id === 100 ? 0 : $id - 1, $id === 4100 ? 'Deep leaf' : 'branch']);
	}

	TreeSqliteFixture::resetCounters();
	memory_reset_peak_usage();
	$before = memory_get_usage();
	$nodes  = search('Deep leaf');

	expect($nodes)->toHaveCount(4001)
		->and(TreeSqliteFixture::$queries)->toBeLessThanOrEqual(4)
		->and(memory_get_peak_usage() - $before)->toBeLessThan(32 * 1024 * 1024);
});

test('search discards missing and cross-tree parent chains', function () {
	TreeSqliteFixture::items([
		['id' => 10, 'parent' => 999, 'graph_tree_id' => 1, 'title' => 'Broken leaf'],
		['id' => 11, 'parent' => 3, 'graph_tree_id' => 1, 'title' => 'Broken leaf'],
	]);

	expect(search('Broken leaf', [1, 2]))->toBe([]);
});

test('search emits shared ancestors once and opens parents before children', function () {
	TreeSqliteFixture::items([
		['id' => 10, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'root'],
		['id' => 11, 'parent' => 10, 'graph_tree_id' => 1, 'title' => 'branch'],
		['id' => 12, 'parent' => 11, 'graph_tree_id' => 1, 'title' => 'Leaf match'],
		['id' => 15, 'parent' => 10, 'graph_tree_id' => 1, 'title' => 'branch'],
		['id' => 16, 'parent' => 15, 'graph_tree_id' => 1, 'title' => 'Leaf match'],
		['id' => 20, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'root'],
		['id' => 21, 'parent' => 20, 'graph_tree_id' => 1, 'title' => 'branch'],
		['id' => 22, 'parent' => 21, 'graph_tree_id' => 1, 'title' => 'Leaf match'],
	]);

	$nodes = search('Leaf match', [1], false);
	$order = array_flip($nodes);

	expect($nodes)->toHaveCount(6)
		->and($order['tree_anchor-1'])->toBe(0)
		->and($order['tbranch-10'])->toBeLessThan($order['tbranch-11'])
		->and($order['tbranch-10'])->toBeLessThan($order['tbranch-15'])
		->and($order['tbranch-20'])->toBeLessThan($order['tbranch-21']);
});

/*
 * Rack(50) holds graph 100 and Closet(52) holds graph 101. With $allowed the
 * user's graphs, devices and templates are denied by default and each listed
 * graph is an exception; with null the user keeps simple graph permissions.
 */
function graph_matches(?array $allowed) : void {
	TreeSqliteFixture::insert('host', [['id' => 999]]);
	TreeSqliteFixture::insert('graph_local', [['id' => 100, 'host_id' => 999], ['id' => 101, 'host_id' => 999], ['id' => 102, 'host_id' => 999]]);
	TreeSqliteFixture::insert('graph_templates_graph', [
		['local_graph_id' => 100, 'title_cache' => 'Uplink traffic'],
		['local_graph_id' => 101, 'title_cache' => 'Uplink errors'],
		['local_graph_id' => 102, 'title_cache' => 'Uplink drops'],
	]);
	TreeSqliteFixture::items([
		['id' => 50, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'Rack'],
		['id' => 51, 'parent' => 50, 'graph_tree_id' => 1, 'local_graph_id' => 100],
		['id' => 52, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'Closet'],
		['id' => 53, 'parent' => 52, 'graph_tree_id' => 1, 'local_graph_id' => 101],
	]);

	$pdo = TreeSqliteFixture::$pdo;
	$pdo->exec('DELETE FROM user_auth_perms WHERE type = 1');

	if ($allowed !== null) {
		$pdo->exec('UPDATE user_auth SET policy_graphs = 2, policy_hosts = 2, policy_graph_templates = 2 WHERE id = ' . USER);

		foreach ($allowed as $graph_id) {
			TreeSqliteFixture::insert('user_auth_perms', [['user_id' => USER, 'item_id' => $graph_id, 'type' => 1]]);
		}
	}

	TreeSqliteFixture::touch();
}

test('a matched graph opens its branch only when the user may view the graph', function (array $allowed, array $nodes) {
	graph_matches($allowed);

	expect(search('Uplink'))->toBe($nodes);
})->with([
	'one graph'  => [[100], ['tbranch-50', 'tree_anchor-1']],
	'both'       => [[100, 101], ['tbranch-50', 'tbranch-52', 'tree_anchor-1']],
	'no graph'   => [[], []],
]);

test('a hidden graph does not hide a viewable match in the same branch', function () {
	graph_matches([102]);
	TreeSqliteFixture::items([['id' => 54, 'parent' => 52, 'graph_tree_id' => 1, 'local_graph_id' => 102]]);

	expect(search('Uplink'))->toBe(['tbranch-52', 'tree_anchor-1']);
});

test('simple graph permissions open every matched graph', function () {
	graph_matches(null);

	expect(search('Uplink'))->toBe(['tbranch-50', 'tbranch-52', 'tree_anchor-1']);
});

test('matched graphs are checked in one permission query, not one per graph', function () {
	graph_matches([100]);

	$graph = TreeSqliteFixture::$pdo->prepare("INSERT INTO graph_templates_graph (local_graph_id, title_cache) VALUES (?, 'Uplink port')");
	$local = TreeSqliteFixture::$pdo->prepare('INSERT INTO graph_local (id, host_id) VALUES (?, 999)');
	$item  = TreeSqliteFixture::$pdo->prepare('INSERT INTO graph_tree_items (parent, graph_tree_id, local_graph_id) VALUES (52, 1, ?)');

	for ($id = 1000; $id < 1200; $id++) {
		$graph->execute([$id]);
		$local->execute([$id]);
		$item->execute([$id]);
	}

	TreeSqliteFixture::resetCounters();

	expect(search('Uplink'))->toBe(['tbranch-50', 'tree_anchor-1'])
		->and(TreeSqliteFixture::$queries)->toBeLessThan(20);
});
