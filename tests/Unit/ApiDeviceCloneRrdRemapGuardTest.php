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
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

// api_clone_device_template() remaps each cloned Data Template's RRD items
// onto the cloned Graph Template's task_item_id. If the cloned Data Template
// has no matching data_template_rrd row for a given data_source_name,
// db_fetch_cell_prepared() returns false; the loop must skip that row rather
// than UPDATE task_item_id to a non-numeric/false value. The whole function
// is too heavy to drive end to end here (host_template rows, XML/script
// duplication, generate_hash(), etc.), so this pulls the exact remap block
// out of lib/api_device.php by brace-balancing from its unique anchor and
// evals it unmodified, the same technique Issue7380GraphCreateWiringTest.php
// uses for graph_template_connect_task_items().

$src = file_get_contents(dirname(__DIR__, 2) . '/lib/api_device.php');

$anchor = 'foreach ($old_rrds as $old_rrd) {';
$start  = strpos($src, $anchor);

if ($start === false) {
	throw new RuntimeException('could not locate the rrd remap loop in lib/api_device.php');
}

$brace_open  = strpos($src, '{', $start);
$depth       = 0;
$cursor      = $brace_open;
$len         = strlen($src);

do {
	$ch = $src[$cursor];

	if ($ch === '{') {
		$depth++;
	} elseif ($ch === '}') {
		$depth--;
	}

	$cursor++;
} while ($depth > 0 && $cursor < $len);

if ($depth !== 0) {
	throw new RuntimeException('unbalanced braces while extracting the rrd remap loop');
}

$block = substr($src, $start, $cursor - $start);

// Test-only: wraps a block read from this repo's own lib/api_device.php (not
// external/untrusted input) so the test exercises the production source
// unmodified, matching Issue7380GraphCreateWiringTest.php's technique.
eval('function apidevice_remap_rrds(array $old_rrds, $new_dt, array $duped_graph_templates) { ' . $block . ' }');

beforeEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$this->db_globals = [$database_sessions, $database_hostname, $database_port, $database_default];

	$database_hostname = 'unit-local';
	$database_port     = 0;
	$database_default  = 'unit-local';

	$this->conn = new PDO('sqlite::memory:');
	$this->conn->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY, data_template_id INTEGER, data_source_name TEXT, local_data_id INTEGER)');
	$this->conn->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, graph_template_id INTEGER, task_item_id INTEGER)');

	$database_sessions = ["$database_hostname:$database_port:$database_default" => $this->conn];
});

// Put the real db_* globals back so later tests in the run don't hit this sqlite handle.
afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->db_globals;
});

function seedOldRrd(PDO $conn, int $id, int $old_dt, string $name): void {
	$conn->exec("INSERT INTO data_template_rrd (id, data_template_id, data_source_name, local_data_id) VALUES ($id, $old_dt, '$name', 0)");
}

function seedNewRrd(PDO $conn, int $id, int $new_dt, string $name): void {
	$conn->exec("INSERT INTO data_template_rrd (id, data_template_id, data_source_name, local_data_id) VALUES ($id, $new_dt, '$name', 0)");
}

function seedGraphItem(PDO $conn, int $id, int $graph_template_id, int $task_item_id): void {
	$conn->exec("INSERT INTO graph_templates_item (id, graph_template_id, task_item_id) VALUES ($id, $graph_template_id, $task_item_id)");
}

test('a matched data_template_rrd row wires task_item_id to the new rrd id', function () {
	seedNewRrd($this->conn, 20, 8, 'ds');
	seedGraphItem($this->conn, 100, 5, 10);

	apidevice_remap_rrds(
		[['id' => 10, 'data_source_name' => 'ds']],
		8,
		[5]
	);

	$task_item_id = $this->conn->query('SELECT task_item_id FROM graph_templates_item WHERE id = 100')->fetchColumn();

	expect((int) $task_item_id)->toBe(20);
});

test('a missing data_template_rrd row leaves task_item_id untouched instead of corrupting it', function () {
	// No row in data_template_rrd for data_template_id = 8, data_source_name = 'ds':
	// the cloned Data Template has no matching RRD item.
	seedGraphItem($this->conn, 100, 5, 10);

	apidevice_remap_rrds(
		[['id' => 10, 'data_source_name' => 'ds']],
		8,
		[5]
	);

	$task_item_id = $this->conn->query('SELECT task_item_id FROM graph_templates_item WHERE id = 100')->fetchColumn();

	// Pre-fix, db_fetch_cell_prepared() returns false here and the UPDATE
	// still runs, binding false and corrupting task_item_id to 0.
	expect((int) $task_item_id)->toBe(10);
});
