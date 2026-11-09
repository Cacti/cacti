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

namespace CactiTreeDeleteContentFixture;

use TreeSqliteFixture;

/*
 * api_tree_delete_node_content() removes everything below a branch. The
 * unchanged lib/api_tree.php code runs against SQLite: it must remove the
 * same items as before with a bounded number of statements and record the
 * tree change once.
 */

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

TreeSqliteFixture::install(__NAMESPACE__);

cacti_fixture_source_function(dirname(__DIR__, 2) . '/lib/api_tree.php', 'api_tree_delete_node_content', __NAMESPACE__);

$GLOBALS['tree_delete_config_writes'] = 0;

function set_config_option(string $name, mixed $value, bool $remote = false) : void {
	$GLOBALS['tree_delete_config_writes']++;
}

beforeEach(function () {
	$GLOBALS['tree_delete_config_writes'] = 0;

	TreeSqliteFixture::reset();

	// 1 > 2 > 3 (graph), 1 > 4 (device) > 5 (graph below a device), 6 at the root, 7 in tree 2
	TreeSqliteFixture::items([
		['id' => 1, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'doomed'],
		['id' => 2, 'parent' => 1, 'graph_tree_id' => 1, 'title' => 'child'],
		['id' => 3, 'parent' => 2, 'graph_tree_id' => 1, 'local_graph_id' => 9],
		['id' => 4, 'parent' => 1, 'graph_tree_id' => 1, 'host_id' => 9],
		['id' => 5, 'parent' => 4, 'graph_tree_id' => 1, 'local_graph_id' => 8],
		['id' => 6, 'parent' => 0, 'graph_tree_id' => 1, 'title' => 'kept'],
		['id' => 7, 'parent' => 1, 'graph_tree_id' => 2, 'title' => 'other tree'],
	]);
});

test('the branch content goes and everything else stays', function () {
	api_tree_delete_node_content(1, 1);

	// items below a device leaf were never walked, as before
	expect(array_keys(TreeSqliteFixture::parents()))->toBe([1, 5, 6, 7]);
});

test('a large branch is removed with a bounded number of statements', function () {
	$insert = TreeSqliteFixture::$pdo->prepare('INSERT INTO graph_tree_items (parent, graph_tree_id, local_graph_id) VALUES (?, 1, ?)');

	$build = function (int $parent, int $depth) use (&$build, $insert) : void {
		for ($i = 0; $i < 10; $i++) {
			$insert->execute([$parent, $depth < 3 ? 0 : $i + 1]);

			if ($depth < 3) {
				$build((int) TreeSqliteFixture::$pdo->lastInsertId(), $depth + 1);
			}
		}
	};

	$build(6, 1);
	TreeSqliteFixture::resetCounters();

	api_tree_delete_node_content(1, 6);

	// 1,110 items below branch 6
	expect(TreeSqliteFixture::$pdo->query('SELECT COUNT(*) FROM graph_tree_items')->fetchColumn())->toBe(7)
		->and(TreeSqliteFixture::$queries)->toBeLessThanOrEqual(4)
		->and($GLOBALS['tree_delete_config_writes'])->toBe(1);
});

test('an empty branch records no change', function () {
	api_tree_delete_node_content(1, 6);

	expect($GLOBALS['tree_delete_config_writes'])->toBe(0);
});
