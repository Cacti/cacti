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

namespace CactiTreeRenderingQueryFixture;

use TreeSqliteFixture;

/*
 * The graph tree draws a "Non Query Based" node under a device when the device
 * has any permitted non-query graph or aggregate, and marks a branch closed
 * when it has any child. The lib/html_tree.php functions run against SQLite
 * with the real permission helpers: the markup must not change, and each
 * check only needs to know whether one row exists.
 */

require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

const USER = 8;

/**
 * Draws device 12's data query branches with $graphs plain graphs and
 * $aggregates aggregate graphs on it. An aggregate graph is a graph of no
 * device that sums member graphs; the member graph here has no graph of its
 * own, so only the aggregate lookup can find it.
 */
function device_branch(int $graphs, int $aggregates) : string {
	for ($i = 1; $i <= $graphs; $i++) {
		TreeSqliteFixture::insert('graph_local', [['id' => 100 + $i, 'host_id' => 12]]);
		TreeSqliteFixture::insert('graph_templates_graph', [['local_graph_id' => 100 + $i, 'title_cache' => "graph $i"]]);
	}

	for ($i = 1; $i <= $aggregates; $i++) {
		TreeSqliteFixture::insert('graph_local', [['id' => 200 + $i, 'host_id' => 12], ['id' => 300 + $i, 'host_id' => 0]]);
		TreeSqliteFixture::insert('graph_templates_graph', [['local_graph_id' => 300 + $i, 'title_cache' => "aggregate $i"]]);
		TreeSqliteFixture::insert('aggregate_graphs', [['id' => $i, 'local_graph_id' => 300 + $i]]);
		TreeSqliteFixture::insert('aggregate_graphs_items', [['aggregate_graph_id' => $i, 'local_graph_id' => 200 + $i]]);
	}

	TreeSqliteFixture::resetCounters();

	return implode('', create_data_query_branch(['id' => 4, 'host_id' => 12]));
}

/**
 * The statements that fetch graphs or aggregates, in the order they ran. The
 * permission helpers also count the rows they could return; that count is not
 * a fetch and is left out.
 */
function lookups() : array {
	return array_values(array_filter(TreeSqliteFixture::$log, fn ($sql) => str_contains($sql, 'graph_templates_graph') && !str_contains($sql, 'COUNT(')));
}

beforeEach(function () {
	$GLOBALS['settings_user'] = [];
	$_SESSION                 = [SESS_USER_ID => USER];

	TreeSqliteFixture::reset();
	TreeSqliteFixture::user(USER);
	TreeSqliteFixture::insert('host', [['id' => 12, 'description' => 'twelve']]);
});

afterEach(function () {
	TreeSqliteFixture::restore();
});

test('a device with non-query graphs draws the node from a single row lookup', function () {
	expect(device_branch(50, 0))->toContain('Non Query Based')
		->and(lookups())->toHaveCount(1)
		->and(lookups()[0])->toContain('LIMIT 1');
});

test('a device with only aggregates still draws the node', function () {
	expect(device_branch(0, 3))->toContain('Non Query Based');

	// the plain graph lookup found nothing, so the aggregate lookup ran too
	expect(lookups())->toHaveCount(2)
		->and(lookups()[0])->toContain('LIMIT 1')
		->and(lookups()[1])->toContain('LIMIT 1')->toContain('aggregate_graphs');
});

test('a device with neither draws no node', function () {
	expect(device_branch(0, 0))->not->toContain('Non Query Based');
});

test('a branch is drawn closed only when it has a child, from a one row lookup', function () {
	TreeSqliteFixture::items([
		['id' => 1, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'parent'],
		['id' => 2, 'parent' => 1, 'graph_tree_id' => 1, 'title' => 'child'],
	]);

	expect(implode('', create_branch(['id' => 1, 'title' => 'parent'])))->toContain("class='jstree-closed'")
		->and(implode('', create_branch(['id' => 2, 'title' => 'child'])))->not->toContain('jstree-closed');

	foreach (TreeSqliteFixture::$log as $sql) {
		expect($sql)->toContain('LIMIT 1')->not->toContain('COUNT(');
	}
});
