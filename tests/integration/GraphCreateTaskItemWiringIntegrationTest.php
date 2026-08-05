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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/template.php';

/*
 * #7380: tests/Unit/Issue7380GraphCreateWiringTest.php proves equivalence between
 * the old per-item loop and the new bulk pre-fetch by eval'ing an extracted copy
 * of graph_template_connect_task_items() with a small, key-unique fixture. That
 * leaves two things unverified:
 *
 *  1. It never calls the function actually shipped in lib/template.php, only a
 *     string-extracted stand-in, so a future edit could drift the test out of
 *     sync with production without failing.
 *  2. Its fixture has no duplicate local_graph_template_item_id / rrd keys, so
 *     it never exercises the "ORDER BY id" added to both bulk pre-fetch queries
 *     (Copilot review feedback) -- the clause that makes the "keep first match
 *     per key" dedupe deterministic instead of depending on incidental SQLite/
 *     MySQL storage order.
 *
 * These integration tests call graph_template_connect_task_items() straight out
 * of lib/template.php against a real (sqlite-backed) PDO connection, and build
 * fixtures with duplicate keys inserted out of id order specifically to prove
 * the ORDER BY clause -- not insertion order -- decides which row wins.
 */

function issue7380_integration_seed(PDO $conn): void {
	$conn->exec('DROP TABLE IF EXISTS graph_templates_item');
	$conn->exec('DROP TABLE IF EXISTS data_template_rrd');

	// "rowseq" is the sqlite rowid alias (an AUTOINCREMENT surrogate key), kept
	// separate from "id" on purpose: it advances in insertion order, while "id"
	// is just an ordinary column whose values we assign out of insertion order
	// below. That decoupling is what lets these tests tell a query that relies
	// on incidental storage order apart from one that explicitly ORDER BYs id.
	$conn->exec('CREATE TABLE graph_templates_item (rowseq INTEGER PRIMARY KEY AUTOINCREMENT, id INTEGER, graph_template_id INTEGER, local_graph_id INTEGER, local_graph_template_item_id INTEGER, task_item_id INTEGER)');
	$conn->exec('CREATE TABLE data_template_rrd (rowseq INTEGER PRIMARY KEY AUTOINCREMENT, id INTEGER, data_template_id INTEGER, local_data_id INTEGER, local_data_template_rrd_id INTEGER)');
}

function issue7380_insert_graph_item(PDO $conn, int $id, int $graph_template_id, int $local_graph_id, int $local_graph_template_item_id, int $task_item_id): void {
	$stmt = $conn->prepare('INSERT INTO graph_templates_item (id, graph_template_id, local_graph_id, local_graph_template_item_id, task_item_id) VALUES (?, ?, ?, ?, ?)');
	$stmt->execute([$id, $graph_template_id, $local_graph_id, $local_graph_template_item_id, $task_item_id]);
}

function issue7380_insert_rrd_item(PDO $conn, int $id, int $data_template_id, int $local_data_id, int $local_data_template_rrd_id): void {
	$stmt = $conn->prepare('INSERT INTO data_template_rrd (id, data_template_id, local_data_id, local_data_template_rrd_id) VALUES (?, ?, ?, ?)');
	$stmt->execute([$id, $data_template_id, $local_data_id, $local_data_template_rrd_id]);
}

function issue7380_integration_wiring(PDO $conn, int $local_graph_id): array {
	$rows = $conn->query('SELECT id, task_item_id FROM graph_templates_item WHERE local_graph_id = ' . (int) $local_graph_id . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
	$out  = [];

	foreach ($rows as $r) {
		$out[(int) $r['id']] = (int) $r['task_item_id'];
	}

	return $out;
}

test('production graph_template_connect_task_items() wires a multi-item batch correctly', function () {
	$conn = new FakeMySQLPDO();
	issue7380_integration_seed($conn);

	// template side: five graph items (10..14), each wired to its own template rrd (20..24)
	for ($i = 0; $i < 5; $i++) {
		issue7380_insert_graph_item($conn, 10 + $i, 5, 0, 0, 20 + $i);
		issue7380_insert_rrd_item($conn, 20 + $i, 100 + $i, 0, 0);
	}

	// created side: graph local_graph_id=200, one data source per template item
	$local_data_id = [];

	for ($i = 0; $i < 5; $i++) {
		issue7380_insert_graph_item($conn, 110 + $i, 5, 200, 10 + $i, 0);
		issue7380_insert_rrd_item($conn, 210 + $i, 100 + $i, 300 + $i, 20 + $i);
		$local_data_id[100 + $i] = 300 + $i;
	}

	$cache = ['local_graph_id' => 200, 'local_data_id' => $local_data_id];

	graph_template_connect_task_items(5, $cache, $conn);

	$expected = [];
	for ($i = 0; $i < 5; $i++) {
		$expected[110 + $i] = 210 + $i;
	}

	expect(issue7380_integration_wiring($conn, 200))->toBe($expected);
});

test('ORDER BY id makes the graph-item dedupe pick the lowest id, not insertion order', function () {
	$conn = new FakeMySQLPDO();
	issue7380_integration_seed($conn);

	issue7380_insert_graph_item($conn, 10, 5, 0, 0, 20);
	issue7380_insert_rrd_item($conn, 20, 7, 0, 0);

	// Two created graph_templates_item rows share local_graph_template_item_id=10.
	// The higher id is inserted FIRST (lower rowseq), so a scan without an
	// explicit ORDER BY would hand it back before the lower id; without
	// "ORDER BY id" the dedupe would key new_graph_items[10] on whichever row
	// happens to come back first from storage, not the lowest id, and the
	// wiring would land on the wrong row.
	issue7380_insert_graph_item($conn, 999, 5, 200, 10, 0);
	issue7380_insert_graph_item($conn, 110, 5, 200, 10, 0);
	issue7380_insert_rrd_item($conn, 210, 7, 300, 20);

	$cache = ['local_graph_id' => 200, 'local_data_id' => [7 => 300]];

	graph_template_connect_task_items(5, $cache, $conn);

	$wiring = issue7380_integration_wiring($conn, 200);

	expect($wiring[110])->toBe(210);
	expect($wiring[999])->toBe(0);
});

test('ORDER BY id makes the rrd-item dedupe pick the lowest id, not insertion order', function () {
	$conn = new FakeMySQLPDO();
	issue7380_integration_seed($conn);

	issue7380_insert_graph_item($conn, 10, 5, 0, 0, 20);
	issue7380_insert_rrd_item($conn, 20, 7, 0, 0);
	issue7380_insert_graph_item($conn, 110, 5, 200, 10, 0);

	// Two created data_template_rrd rows share the "local_data_template_rrd_id:
	// local_data_id" key (20:300). The higher id is inserted first (lower
	// rowseq) for the same reason as above -- proving the rrd-side pre-fetch
	// also depends on ORDER BY id, not physical insertion order.
	issue7380_insert_rrd_item($conn, 999, 7, 300, 20);
	issue7380_insert_rrd_item($conn, 210, 7, 300, 20);

	$cache = ['local_graph_id' => 200, 'local_data_id' => [7 => 300]];

	graph_template_connect_task_items(5, $cache, $conn);

	$wiring = issue7380_integration_wiring($conn, 200);

	expect($wiring[110])->toBe(210);
});
