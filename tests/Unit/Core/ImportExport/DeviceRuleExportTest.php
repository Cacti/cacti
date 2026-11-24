<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * A Device Rule export replaces local ids with hashes so the rules can be
 * imported on another system.  Tree rule items name their Tree Rule by hash,
 * and Threshold Templates carry the Data Source hash rather than its id.
 */

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/api_automation.php';

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->session = "$database_hostname:$database_port:$database_default";
	$this->prior   = $database_sessions[$this->session] ?? null;

	$conn = new FakeMySQLPDO();

	$tables = [
		'automation_templates'        => 'id INTEGER PRIMARY KEY, hash TEXT, host_template INTEGER',
		'host_template'               => 'id INTEGER PRIMARY KEY, hash TEXT, name TEXT',
		'automation_templates_rules'  => 'id INTEGER PRIMARY KEY, template_id INTEGER, rule_type INTEGER, rule_id INTEGER',
		'automation_graph_rules'      => 'id INTEGER PRIMARY KEY, hash TEXT',
		'automation_tree_rules'       => 'id INTEGER PRIMARY KEY, hash TEXT, tree_id INTEGER, tree_item_id INTEGER',
		'automation_tree_rule_items'  => 'id INTEGER PRIMARY KEY, rule_id INTEGER, field TEXT',
		'automation_match_rule_items' => 'id INTEGER PRIMARY KEY, rule_id INTEGER, rule_type INTEGER',
		'graph_tree'                  => 'id INTEGER PRIMARY KEY, name TEXT, sort_type INTEGER',
		'plugin_thold_host_template'  => 'host_template_id INTEGER, thold_template_id INTEGER',
		'thold_template'              => 'id INTEGER PRIMARY KEY, hash TEXT, data_template_id INTEGER, data_source_id INTEGER',
		'data_template_rrd'           => 'id INTEGER PRIMARY KEY, hash TEXT',
	];

	foreach ($tables as $table => $columns) {
		$conn->exec("CREATE TABLE $table ($columns)");
	}

	$conn->exec("INSERT INTO automation_templates VALUES (1, 'template', 4)");
	$conn->exec("INSERT INTO host_template VALUES (4, 'device_template', 'Device')");
	$conn->exec('INSERT INTO automation_templates_rules VALUES (1, 1, 2, 3)');
	$conn->exec("INSERT INTO automation_tree_rules VALUES (3, 'tree_rule', 1, 0)");
	// The item id matches no Tree Rule, so a lookup by the item id finds nothing.
	$conn->exec("INSERT INTO automation_tree_rule_items VALUES (9, 3, 'hostname')");
	$conn->exec("INSERT INTO graph_tree VALUES (1, 'Default', 1)");
	$conn->exec('INSERT INTO plugin_thold_host_template VALUES (4, 6)');
	$conn->exec("INSERT INTO thold_template VALUES (6, 'thold_template', 2, 8)");
	$conn->exec("INSERT INTO data_template_rrd VALUES (8, 'data_source')");

	$database_sessions[$this->session] = $conn;
});

afterEach(function () {
	global $database_sessions;

	if ($this->prior === null) {
		unset($database_sessions[$this->session]);
	} else {
		$database_sessions[$this->session] = $this->prior;
	}
});

test('a device rule export names tree rules and data sources by hash', function () {
	$export = automation_device_rule_export([1]);
	$device = $export['device'][0];

	expect($device['tree_rules'][0]['tree_rule_items'][0]['rule_id'])->toBe('tree_rule')
		->and($device['thold_rules'][0]['thold_template']['data_source_hash'])->toBe('data_source')
		->and($device['thold_rules'][0]['thold_template'])->not->toHaveKey('data_source_id');
});
