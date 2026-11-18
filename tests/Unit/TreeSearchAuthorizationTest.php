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
 * may view, may only open a branch for a matched graph the user may view,
 * needs at least three characters like the client, and must stop on a
 * parent cycle.
 */

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

TreeSqliteFixture::install(__NAMESPACE__);

cacti_fixture_source_function(dirname(__DIR__, 2) . '/graph_view.php', 'get_matching_nodes', __NAMESPACE__);

$GLOBALS['tree_search_request'] = [];
$GLOBALS['tree_search_allowed'] = [];

function get_nfilter_request_var(string $name, mixed $default = '') : mixed {
	return $GLOBALS['tree_search_request'][$name] ?? $default;
}

function is_tree_allowed(int $tree_id, int $user_id = 0) : bool {
	return in_array($tree_id, $GLOBALS['tree_search_allowed'], true);
}

function header(string $header) : void {
}

function get_simple_graph_perms(int $user_id) : bool {
	return $GLOBALS['tree_search_simple'];
}

/*
 * Stands in for the graph permission query: is_graph_allowed() runs
 * get_allowed_graphs() for one graph, the search runs it for an id list.
 * A null list permits every graph.
 */
function get_allowed_graphs(string $sql_where = '', mixed $sql_order = 'gtg.title_cache', string $sql_limit = '', int &$total_rows = 0, int $user_id = 0, int $graph_id = 0) : array {
	$GLOBALS['tree_search_graph_calls'][] = $sql_where;

	expect(preg_match('/^gl\.id IN\(([0-9,]+)\)$/', $sql_where, $ids))->toBe(1);

	$rows = [];

	foreach (array_map('intval', explode(',', $ids[1])) as $id) {
		if ($GLOBALS['tree_search_graphs'] === null || in_array($id, $GLOBALS['tree_search_graphs'], true)) {
			$rows[] = ['local_graph_id' => $id];
		}
	}

	return $rows;
}

function search(string $str, array $allowed = [1], bool $sort = true) : array {
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

	if ($sort) {
		sort($nodes);
	}

	return $nodes;
}

beforeEach(function () {
	$GLOBALS['tree_search_simple']      = false;
	$GLOBALS['tree_search_graphs']      = null;
	$GLOBALS['tree_search_graph_calls'] = [];

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

	// every branch above the 1,296 matches, plus the tree anchor; the two
	// statements are the match search and one read of the matched tree
	expect($nodes)->toHaveCount(6 + 36 + 216 + 1)
		->and(TreeSqliteFixture::$queries)->toBeLessThanOrEqual(2);
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
		->and(TreeSqliteFixture::$queries)->toBeLessThanOrEqual(2)
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

function graph_matches() : void {
	TreeSqliteFixture::$pdo->exec("INSERT INTO graph_templates_graph (local_graph_id, title_cache)
		VALUES (100, 'Uplink traffic'), (101, 'Uplink errors'), (102, 'Uplink drops')");
	TreeSqliteFixture::items([
		['id' => 50, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'Rack'],
		['id' => 51, 'parent' => 50, 'graph_tree_id' => 1, 'local_graph_id' => 100],
		['id' => 52, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'Closet'],
		['id' => 53, 'parent' => 52, 'graph_tree_id' => 1, 'local_graph_id' => 101],
	]);
}

test('a matched graph opens its branch only when the user may view the graph', function () {
	graph_matches();

	$GLOBALS['tree_search_graphs'] = [100];
	expect(search('Uplink'))->toBe(['tbranch-50', 'tree_anchor-1']);

	$GLOBALS['tree_search_graphs'] = [100, 101];
	expect(search('Uplink'))->toBe(['tbranch-50', 'tbranch-52', 'tree_anchor-1']);

	$GLOBALS['tree_search_graphs'] = [];
	expect(search('Uplink'))->toBe([]);
});

test('a hidden graph does not hide a viewable match in the same branch', function () {
	graph_matches();
	TreeSqliteFixture::items([
		['id' => 54, 'parent' => 52, 'graph_tree_id' => 1, 'local_graph_id' => 102],
	]);

	$GLOBALS['tree_search_graphs'] = [102];

	expect(search('Uplink'))->toBe(['tbranch-52', 'tree_anchor-1']);
});

test('matched graphs are checked in one permission query, and only for viewable trees', function () {
	$insert_graph = TreeSqliteFixture::$pdo->prepare('INSERT INTO graph_templates_graph (local_graph_id, title_cache) VALUES (?, ?)');
	$insert_item  = TreeSqliteFixture::$pdo->prepare('INSERT INTO graph_tree_items (parent, graph_tree_id, local_graph_id) VALUES (?, ?, ?)');

	for ($id = 1000; $id < 1200; $id++) {
		$insert_graph->execute([$id, "Port $id traffic"]);
		$insert_item->execute([$id < 1100 ? 1 : 3, $id < 1100 ? 1 : 2, $id]);
	}

	$GLOBALS['tree_search_graphs'] = [1000];

	expect(search('traffic', [1]))->toBe(['tbranch-1', 'tree_anchor-1'])
		->and($GLOBALS['tree_search_graph_calls'])->toHaveCount(1)
		->and(substr_count($GLOBALS['tree_search_graph_calls'][0], ','))->toBe(99);
});

test('a search without graph matches runs no graph permission query', function () {
	search('Traffic', [1, 2]);

	expect($GLOBALS['tree_search_graph_calls'])->toBe([]);
});

test('simple graph permissions open every matched graph without a permission query', function () {
	graph_matches();

	$GLOBALS['tree_search_simple'] = true;
	$GLOBALS['tree_search_graphs'] = [];

	expect(search('Uplink'))->toBe(['tbranch-50', 'tbranch-52', 'tree_anchor-1'])
		->and($GLOBALS['tree_search_graph_calls'])->toBe([]);
});
