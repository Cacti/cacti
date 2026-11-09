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

namespace CactiTreeBranchEmptyFixture;

use TreeSqliteFixture;

/*
 * is_tree_branch_empty() decides whether a tree branch is drawn for the
 * current user. The unchanged lib/auth.php code runs against SQLite with the
 * permission helpers replaced by fakes that answer from fixed allow lists, so
 * these tests pin both the visibility rules and the number of queries.
 */

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

TreeSqliteFixture::install(__NAMESPACE__);

foreach (['is_tree_branch_empty', 'get_tree_visible_branches'] as $function) {
	cacti_fixture_source_function(dirname(__DIR__, 2) . '/lib/auth.php', $function, __NAMESPACE__);
}

$GLOBALS['tbe'] = [];

function read_config_option(string $name, bool $force = false) : mixed {
	return $name == 'time_last_change_branch' ? $GLOBALS['tbe']['changed'] : '';
}

function get_simple_graph_perms(int $user_id) : bool {
	return $GLOBALS['tbe']['simple'];
}

function ids_in(string $sql_where) : array {
	preg_match('/IN\s*\(([0-9, ]*)\)/', $sql_where, $match);

	return array_map('intval', array_filter(explode(',', $match[1] ?? ''), 'strlen'));
}

function get_allowed_graphs(string $sql_where = '', mixed $sql_order = 'gtg.title_cache', string $sql_limit = '', int &$total_rows = 0, int $user_id = 0, int $graph_id = 0) : array {
	$GLOBALS['tbe']['perm_calls']++;

	$rows = [];

	foreach (array_intersect(ids_in($sql_where), $GLOBALS['tbe']['graphs']) as $id) {
		$rows[] = ['local_graph_id' => $id];
	}

	return $rows;
}

function get_allowed_devices(string $sql_where = '', string $sql_order = 'description', string $sql_limit = '', int &$total_rows = 0, int $user_id = 0, int $device_id = 0) : array {
	$GLOBALS['tbe']['perm_calls']++;

	$rows = [];

	foreach (array_intersect(ids_in($sql_where), $GLOBALS['tbe']['hosts']) as $id) {
		$rows[] = ['id' => $id];
	}

	return $rows;
}

function allow(array $graphs = [], array $hosts = [], bool $simple = false) : void {
	$GLOBALS['tbe']['graphs'] = $graphs;
	$GLOBALS['tbe']['hosts']  = $hosts;
	$GLOBALS['tbe']['simple'] = $simple;
	$GLOBALS['tbe']['changed']++;
}

beforeEach(function () {
	$_SESSION = [SESS_USER_ID => 3];

	$GLOBALS['tbe'] = ['graphs' => [], 'hosts' => [], 'simple' => false, 'perm_calls' => 0, 'changed' => ($GLOBALS['tbe']['changed'] ?? 0) + 1];

	TreeSqliteFixture::reset(20000);
	TreeSqliteFixture::$pdo->exec('INSERT INTO host (id, site_id) VALUES (100, 7), (101, 7), (200, 0), (300, 8)');

	// 1 > 2 > 3 (graph 50), 1 > 4 (site 7), 1 > 5 (host 200), 6 (empty branch), 9 (host 300) > 10 (graph 60)
	TreeSqliteFixture::items([
		['id' => 1, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'top'],
		['id' => 2, 'parent' => 1, 'graph_tree_id' => 1, 'title' => 'mid'],
		['id' => 3, 'parent' => 2, 'graph_tree_id' => 1, 'local_graph_id' => 50],
		['id' => 4, 'parent' => 1, 'graph_tree_id' => 1, 'site_id' => 7],
		['id' => 5, 'parent' => 1, 'graph_tree_id' => 1, 'host_id' => 200],
		['id' => 6, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'empty'],
		['id' => 9, 'parent' => 0, 'graph_tree_id' => 1, 'host_id' => 300],
		['id' => 10, 'parent' => 9, 'graph_tree_id' => 1, 'local_graph_id' => 60],
	]);
});

afterEach(function () {
	$_SESSION = [];
});

test('nothing visible leaves every branch empty', function () {
	allow();

	expect(is_tree_branch_empty(1, 0))->toBeTrue()
		->and(is_tree_branch_empty(1, 1))->toBeTrue()
		->and(is_tree_branch_empty(1, 2))->toBeTrue();
});

test('an allowed graph shows its branch and every ancestor', function () {
	allow([50]);

	expect(is_tree_branch_empty(1, 2))->toBeFalse()
		->and(is_tree_branch_empty(1, 1))->toBeFalse()
		->and(is_tree_branch_empty(1, 0))->toBeFalse()
		->and(is_tree_branch_empty(1, 6))->toBeTrue();
});

test('simple graph permissions show any graph leaf', function () {
	allow([], [], true);

	expect(is_tree_branch_empty(1, 2))->toBeFalse();
});

test('an allowed direct device shows its branch', function () {
	allow([], [200]);

	expect(is_tree_branch_empty(1, 1))->toBeFalse()
		->and(is_tree_branch_empty(1, 2))->toBeTrue();
});

test('an allowed device of a site shows the branch holding the site', function () {
	allow([], [101]);

	expect(is_tree_branch_empty(1, 1))->toBeFalse();
});

test('a site leaf does not hide an allowed direct device on the same branch', function () {
	allow([], [200]);

	// branch 1 holds site 7 (no allowed device) and device 200 (allowed)
	expect(is_tree_branch_empty(1, 1))->toBeFalse();
});

test('items below a device leaf do not count toward the branch above it', function () {
	allow([60]);

	expect(is_tree_branch_empty(1, 0))->toBeTrue();
});

test('one check of a large tree with nothing visible needs a bounded number of queries', function () {
	TreeSqliteFixture::$pdo->beginTransaction();

	$insert = TreeSqliteFixture::$pdo->prepare('INSERT INTO graph_tree_items (parent, graph_tree_id, local_graph_id, host_id) VALUES (?, 2, ?, ?)');
	$graph  = 1000;
	$host   = 1000;

	$build = function (int $parent, int $depth) use (&$build, $insert, &$graph, &$host) : void {
		for ($i = 0; $i < 10; $i++) {
			$insert->execute([$parent, 0, 0]);
			$id = (int) TreeSqliteFixture::$pdo->lastInsertId();

			if ($depth < 3) {
				$build($id, $depth + 1);
			} else {
				$insert->execute([$id, ++$graph, 0]);
				$insert->execute([$id, 0, ++$host]);
			}
		}
	};

	$build(0, 1);
	TreeSqliteFixture::$pdo->commit();

	allow();
	TreeSqliteFixture::resetCounters();

	expect(is_tree_branch_empty(2, 0))->toBeTrue();

	// 1110 branches, 1000 graphs and 1000 devices
	expect(TreeSqliteFixture::$queries)->toBeLessThanOrEqual(3)
		->and($GLOBALS['tbe']['perm_calls'])->toBeLessThanOrEqual(2);

	// the rest of the tree is answered from the same read
	TreeSqliteFixture::resetCounters();

	foreach (TreeSqliteFixture::$pdo->query('SELECT id FROM graph_tree_items WHERE graph_tree_id = 2 AND parent = 0') as $row) {
		expect(is_tree_branch_empty(2, (int) $row['id']))->toBeTrue();
	}

	expect(TreeSqliteFixture::$queries)->toBe(0);
});

test('a tree change in the same request is not answered from the cache', function () {
	allow();
	expect(is_tree_branch_empty(1, 6))->toBeTrue();

	TreeSqliteFixture::items([['id' => 7, 'parent' => 6, 'graph_tree_id' => 1, 'local_graph_id' => 70]]);
	allow([70]);

	expect(is_tree_branch_empty(1, 6))->toBeFalse();
});
