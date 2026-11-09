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
 * when it has any child. The unchanged lib/html_tree.php functions run with
 * the permission helpers faked: the markup must not change, and each check
 * only needs to know whether one row exists.
 */

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

TreeSqliteFixture::install(__NAMESPACE__);

foreach (['create_data_query_branch', 'create_branch'] as $function) {
	cacti_fixture_source_function(dirname(__DIR__, 2) . '/lib/html_tree.php', $function, __NAMESPACE__);
}

$GLOBALS['trq'] = [];

function read_config_option(string $name, bool $force = false) : mixed {
	return '';
}

function read_user_setting(string $name, mixed $default = false, bool $force = false, int $user = 0) : mixed {
	return 'on';
}

function grv(string $name, mixed $default = '') : mixed {
	return '';
}

function get_formatted_data_query_indexes(int $host_id, int $data_query_id) : array {
	return [];
}

function rows(int $count, string $sql_limit) : array {
	$count = $sql_limit === '' ? $count : min($count, (int) $sql_limit);

	return $count > 0 ? array_fill(0, $count, ['local_graph_id' => 1]) : [];
}

function get_allowed_graphs(string $sql_where = '', mixed $sql_order = 'gtg.title_cache', string $sql_limit = '', int &$total_rows = 0, int $user_id = 0, int $graph_id = 0) : array {
	$GLOBALS['trq']['calls'][] = ['graphs', $sql_where, $sql_limit];

	return rows($GLOBALS['trq']['graphs'], $sql_limit);
}

function get_allowed_aggregate_graphs(string $sql_where = '', string $sql_order = 'gtg.title_cache', string $sql_limit = '', int &$total_rows = 0, int $user_id = 0, int $graph_id = 0) : array {
	$GLOBALS['trq']['calls'][] = ['aggregates', $sql_where, $sql_limit];

	return rows($GLOBALS['trq']['aggregates'], $sql_limit);
}

function device_branch(int $graphs, int $aggregates) : string {
	$GLOBALS['trq'] = ['graphs' => $graphs, 'aggregates' => $aggregates, 'calls' => []];

	return implode('', create_data_query_branch(['id' => 4, 'host_id' => 12]));
}

beforeEach(function () {
	TreeSqliteFixture::reset();
});

test('a device with non-query graphs draws the node from a single row lookup', function () {
	expect(device_branch(500, 0))->toContain('Non Query Based')
		->and($GLOBALS['trq']['calls'])->toBe([['graphs', 'gl.host_id=12 AND gl.snmp_query_id=0', '1']]);
});

test('a device with only aggregates still draws the node', function () {
	expect(device_branch(0, 3))->toContain('Non Query Based')
		->and($GLOBALS['trq']['calls'])->toBe([
			['graphs', 'gl.host_id=12 AND gl.snmp_query_id=0', '1'],
			['aggregates', 'gl.host_id=12 AND gl.snmp_query_id=0', '1'],
		]);
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
