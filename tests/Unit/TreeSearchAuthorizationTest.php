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
 * runs against SQLite with the real tree permission checks: it may only
 * return nodes of trees the user may view, needs at least three characters
 * like the client, and must stop on a parent cycle.
 */

require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

const USER = 4;

/*
 * The user may view only the trees in $allowed: trees are denied by default
 * and each allowed tree is a tree exception.
 */
function search(string $str, array $allowed = [1], bool $sort = true) : array {
	$pdo = TreeSqliteFixture::$pdo;
	$pdo->exec('DELETE FROM user_auth_perms');

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
