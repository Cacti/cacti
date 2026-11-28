<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Removing orphaned Graph Template items during a template import must stay
 * within the Template being imported.  Graph items carry an empty hash, so a
 * removal keyed on the hash alone could reach the items of every Graph.
 */

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/import.php';

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->session = "$database_hostname:$database_port:$database_default";
	$this->prior   = $database_sessions[$this->session] ?? null;
	$this->conn    = new FakeMySQLPDO();

	$this->conn->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, hash TEXT, graph_template_id INTEGER, local_graph_id INTEGER, local_graph_template_item_id INTEGER)');

	$rows = [
		// Template 1: the orphan has an empty hash, and one item stays.
		[1, '', 1, 0, 0],
		[2, 'kept', 1, 0, 0],
		// Graphs made from Template 1.
		[10, '', 1, 5, 1],
		[11, '', 1, 5, 2],
		// Another Template and its Graph, sharing the hash value.
		[20, '', 2, 0, 0],
		[21, '', 2, 6, 20],
	];

	$insert = $this->conn->prepare('INSERT INTO graph_templates_item VALUES (?, ?, ?, ?, ?)');

	foreach ($rows as $row) {
		$insert->execute($row);
	}

	$database_sessions[$this->session] = $this->conn;
});

afterEach(function () {
	global $database_sessions;

	if ($this->prior === null) {
		unset($database_sessions[$this->session]);
	} else {
		$database_sessions[$this->session] = $this->prior;
	}
});

test('an orphaned template item is removed with its graph items only', function () {
	import_remove_orphan_graph_item(1, ['id' => 1, 'hash' => '']);

	$remaining = $this->conn->query('SELECT id FROM graph_templates_item ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

	expect(array_map('intval', $remaining))->toBe([2, 11, 20, 21]);
});
