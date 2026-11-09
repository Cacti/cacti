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

namespace CactiTreeSortFixture;

use TreeSqliteFixture;

/*
 * api_tree_sort_branch() orders one parent's children (branches, then sites,
 * devices and graphs) by the inherited sort type. The unchanged lib/api_tree.php
 * code runs against SQLite: inserting one item must only re-sort its own
 * siblings and write the positions that change, while a full sort from the
 * tree root must order the root level and every inheriting branch below it.
 */

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

TreeSqliteFixture::install(__NAMESPACE__);

foreach (['api_tree_item_save', 'api_tree_sort_branch', 'api_tree_sort_children', 'api_tree_get_branch_ordering', 'api_tree_parse_node_data'] as $function) {
	cacti_fixture_source_function(dirname(__DIR__, 2) . '/lib/api_tree.php', $function, __NAMESPACE__);
}

function input_validate_input_number(mixed $value, string $name = '') : void {
}

function form_input_validate(mixed $value, string $field, string $regex, bool $allow_nulls, int $custom_message = 3) : mixed {
	return $value;
}

function is_error_message() : bool {
	return false;
}

function raise_message(mixed $message_id, string $message = '', int $message_level = 0) : void {
}

function cacti_log(mixed $message, bool $output = false, string $environ = 'CMDPHP', mixed $level = '') : bool {
	return true;
}

function set_config_option(string $name, mixed $value, bool $remote = false) : void {
}

function sql_save(array $save, string $table) : int|false {
	unset($save['id']);

	TreeSqliteFixture::run('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($save)) . ')
		VALUES (' . implode(', ', array_fill(0, count($save), '?')) . ')', $save);

	return (int) TreeSqliteFixture::$pdo->lastInsertId();
}

function updates() : int {
	return count(array_filter(TreeSqliteFixture::$log, fn ($sql) => preg_match('/^\s*UPDATE/i', $sql)));
}

function positions() : array {
	return TreeSqliteFixture::$pdo->query('SELECT id, position FROM graph_tree_items ORDER BY id')->fetchAll(\PDO::FETCH_KEY_PAIR);
}

function tree(int $sort_type) : void {
	TreeSqliteFixture::reset(200000);
	TreeSqliteFixture::$pdo->exec("INSERT INTO graph_tree (id, name, sort_type) VALUES (1, 'tree', $sort_type)");
}

test('placing many graphs on a branch writes about one position per graph', function () {
	tree(TREE_ORDERING_NATURAL);
	TreeSqliteFixture::items([['id' => 1, 'parent' => 0, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'hdr']]);

	$k      = 300;
	$insert = TreeSqliteFixture::$pdo->prepare('INSERT INTO graph_templates_graph (local_graph_id, title_cache) VALUES (?, ?)');

	for ($g = 1; $g <= $k; $g++) {
		$insert->execute([$g, "graph $g"]);
	}

	TreeSqliteFixture::resetCounters();

	for ($g = 1; $g <= $k; $g++) {
		api_tree_item_save(0, 1, TREE_ITEM_TYPE_GRAPH, 1, '', $g, 0, 0, 0, 0, false);
	}

	// each graph sorts last, so only the new row needs a position
	expect(TreeSqliteFixture::$writes)->toBeLessThanOrEqual(3 * $k)
		->and(TreeSqliteFixture::$queries)->toBeLessThanOrEqual(12 * $k);

	$titles = TreeSqliteFixture::$pdo->query('SELECT gtg.title_cache FROM graph_tree_items AS gti
		INNER JOIN graph_templates_graph AS gtg ON gtg.local_graph_id = gti.local_graph_id
		WHERE gti.parent = 1 ORDER BY gti.position')->fetchAll(\PDO::FETCH_COLUMN);

	expect($titles)->toBe(array_map(fn ($g) => "graph $g", range(1, $k)));
});

test('an insert at the root leaves every nested branch untouched', function () {
	tree(TREE_ORDERING_ALPHABETIC);

	$id    = 0;
	$rows  = [];
	$build = function (int $parent, int $depth) use (&$build, &$id, &$rows) : void {
		for ($i = 5; $i >= 1; $i--) {
			$rows[] = ['id' => ++$id, 'parent' => $parent, 'position' => 6 - $i, 'graph_tree_id' => 1, 'title' => "b$depth-$i"];

			if ($depth < 3) {
				$build($id, $depth + 1);
			}
		}
	};

	$build(0, 1);
	TreeSqliteFixture::items($rows);
	TreeSqliteFixture::$pdo->exec("INSERT INTO host (id, description) VALUES (1, 'a device')");

	$before = positions();
	TreeSqliteFixture::resetCounters();

	$new = api_tree_item_save(0, 1, TREE_ITEM_TYPE_HOST, 0, '', 0, 1, 0, 1, 1, false);

	$after = positions();
	unset($after[$new]);

	foreach ($before as $item => $position) {
		if ($rows[$item - 1]['parent'] != 0) {
			expect($after[$item])->toBe($position);
		}
	}

	expect(TreeSqliteFixture::$writes)->toBeLessThanOrEqual(3);
});

test('a sorted branch is not rewritten', function () {
	tree(TREE_ORDERING_ALPHABETIC);
	TreeSqliteFixture::items([
		['parent' => 0, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'Alpha'],
		['parent' => 0, 'position' => 2, 'graph_tree_id' => 1, 'title' => 'Mike'],
		['parent' => 0, 'position' => 3, 'graph_tree_id' => 1, 'title' => 'Zulu'],
	]);

	TreeSqliteFixture::resetCounters();
	api_tree_sort_branch(0, 1);

	expect(updates())->toBe(0);
});

test('a full sort orders the root level and every inheriting branch', function () {
	tree(TREE_ORDERING_ALPHABETIC);
	TreeSqliteFixture::items([
		['id' => 1, 'parent' => 0, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'Zulu'],
		['id' => 2, 'parent' => 0, 'position' => 2, 'graph_tree_id' => 1, 'title' => 'Alpha'],
		['id' => 3, 'parent' => 0, 'position' => 3, 'graph_tree_id' => 1, 'title' => 'Mike', 'sort_children_type' => TREE_ORDERING_NONE],
		['id' => 4, 'parent' => 1, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'z2'],
		['id' => 5, 'parent' => 1, 'position' => 2, 'graph_tree_id' => 1, 'title' => 'z1'],
		['id' => 6, 'parent' => 5, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'deep b'],
		['id' => 7, 'parent' => 5, 'position' => 2, 'graph_tree_id' => 1, 'title' => 'deep a'],
		['id' => 8, 'parent' => 3, 'position' => 1, 'graph_tree_id' => 1, 'title' => 'm2'],
		['id' => 9, 'parent' => 3, 'position' => 2, 'graph_tree_id' => 1, 'title' => 'm1'],
	]);

	api_tree_sort_branch(0, 1);

	expect(TreeSqliteFixture::childTitles(0))->toBe(['Alpha', 'Mike', 'Zulu'])
		->and(TreeSqliteFixture::childTitles(1))->toBe(['z1', 'z2'])
		->and(TreeSqliteFixture::childTitles(5))->toBe(['deep a', 'deep b'])
		->and(TreeSqliteFixture::childTitles(3))->toBe(['m2', 'm1']);
});

test('branches sort ahead of sites, devices and graphs', function () {
	tree(TREE_ORDERING_ALPHABETIC);
	TreeSqliteFixture::$pdo->exec("INSERT INTO sites (id, name) VALUES (1, 'a site')");
	TreeSqliteFixture::$pdo->exec("INSERT INTO host (id, description) VALUES (1, 'a device')");
	TreeSqliteFixture::$pdo->exec("INSERT INTO graph_templates_graph (local_graph_id, title_cache) VALUES (1, 'a graph')");
	TreeSqliteFixture::items([
		['id' => 1, 'parent' => 0, 'position' => 1, 'graph_tree_id' => 1, 'local_graph_id' => 1],
		['id' => 2, 'parent' => 0, 'position' => 2, 'graph_tree_id' => 1, 'host_id' => 1],
		['id' => 3, 'parent' => 0, 'position' => 3, 'graph_tree_id' => 1, 'site_id' => 1],
		['id' => 4, 'parent' => 0, 'position' => 4, 'graph_tree_id' => 1, 'title' => 'z branch'],
	]);

	api_tree_sort_branch(0, 1);

	expect(TreeSqliteFixture::$pdo->query('SELECT id FROM graph_tree_items ORDER BY position')->fetchAll(\PDO::FETCH_COLUMN))
		->toBe([4, 3, 2, 1]);
});

test('each sort type orders branch titles as before', function (int $sort_type, array $titles, array $expected) {
	tree($sort_type);

	$rows = [];

	foreach ($titles as $i => $title) {
		$rows[] = ['parent' => 0, 'position' => $i + 1, 'graph_tree_id' => 1, 'title' => $title];
	}

	TreeSqliteFixture::items($rows);

	api_tree_sort_branch(0, 1);

	expect(TreeSqliteFixture::childTitles(0))->toBe($expected);
})->with([
	'natural'    => [TREE_ORDERING_NATURAL, ['b10', 'B9', 'b1'], ['b1', 'B9', 'b10']],
	'numeric'    => [TREE_ORDERING_NUMERIC, ['100', '9', '10'], ['9', '10', '100']],
	'alphabetic' => [TREE_ORDERING_ALPHABETIC, ['b', 'c', 'a'], ['a', 'b', 'c']],
	'none'       => [TREE_ORDERING_NONE, ['b', 'c', 'a'], ['b', 'c', 'a']],
]);

test('tree.php sorts the whole tree through api_tree_sort_branch when the sort type changes', function () {
	$source = (string) file_get_contents(dirname(__DIR__, 2) . '/tree.php');

	expect($source)->toContain('api_tree_sort_branch(0, $tree_id);')
		->not->toContain('function sort_recursive(');
});
