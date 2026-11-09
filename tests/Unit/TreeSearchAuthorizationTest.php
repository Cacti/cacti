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
 * graph_view.php?action=ajax_search runs get_matching_nodes(). The unchanged
 * function runs against SQLite: it may only return nodes of trees the user
 * may view, needs at least three characters like the client, and must stop
 * on a parent cycle.
 */

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

TreeSqliteFixture::install(__NAMESPACE__);

cacti_fixture_source_function(dirname(__DIR__, 2) . '/lib/html_tree.php', 'get_matching_nodes', __NAMESPACE__);

$GLOBALS['tree_search_request'] = [];
$GLOBALS['tree_search_allowed'] = [];

function gnrv(string $name, mixed $default = '') : mixed {
	return $GLOBALS['tree_search_request'][$name] ?? $default;
}

function is_tree_allowed(int $tree_id, int $user_id = 0) : bool {
	return in_array($tree_id, $GLOBALS['tree_search_allowed'], true);
}

function header(string $header) : void {
}

function search(string $str, array $allowed = [1]) : array {
	$GLOBALS['tree_search_request'] = ['str' => $str];
	$GLOBALS['tree_search_allowed'] = $allowed;

	ob_start();

	try {
		get_matching_nodes();
	} finally {
		$out = ob_get_clean();
	}

	$nodes = json_decode($out === '' ? '[]' : $out, true);

	expect($nodes)->toBeArray();

	sort($nodes);

	return $nodes;
}

beforeEach(function () {
	TreeSqliteFixture::reset(2000);
	TreeSqliteFixture::$pdo->exec("INSERT INTO graph_tree (id, name) VALUES (1, 'one'), (2, 'two')");
	TreeSqliteFixture::items([
		['id' => 1, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'Core'],
		['id' => 2, 'parent' => 1, 'graph_tree_id' => 1, 'title' => 'Traffic one'],
		['id' => 3, 'parent' => 0, 'graph_tree_id' => 2, 'title' => 'Edge'],
		['id' => 4, 'parent' => 3, 'graph_tree_id' => 2, 'title' => 'Traffic two'],
	]);
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

	// every branch above the 1,296 matches, plus the tree anchor
	expect($nodes)->toHaveCount(6 + 36 + 216 + 1)
		->and(TreeSqliteFixture::$queries)->toBeLessThanOrEqual(2);
});
