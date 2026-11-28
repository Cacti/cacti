<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * The automation and data source profile import pages print each result line
 * as markup, so values copied from the imported JSON into those lines must be
 * encoded when the line is built.
 */

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/api_automation.php';

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->session = "$database_hostname:$database_port:$database_default";
	$this->prior   = $database_sessions[$this->session] ?? null;
	$this->conn    = new FakeMySQLPDO();

	foreach (['snmp_query', 'snmp_query_graph', 'host_template', 'automation_networks'] as $table) {
		$this->conn->exec("CREATE TABLE $table (id INTEGER PRIMARY KEY AUTOINCREMENT, hash TEXT, name TEXT)");
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

test('graph rule import encodes the unknown data query hashes it reports', function () {
	$result = automation_graph_rule_import(['graph_rules' => [[
		'hash'          => 'rule',
		'snmp_query_id' => '<i>query</i>',
		'graph_type_id' => '"graph"&',
	]]]);

	expect($result['errors'])->toHaveCount(2)
		->and($result['errors'][0])->toContain('&lt;i&gt;query&lt;/i&gt;')->not->toContain('<i>')
		->and($result['errors'][1])->toContain('&quot;graph&quot;&amp;');
});

test('device rule import encodes the unknown template and query hashes it reports', function () {
	$result = automation_template_import(['device' => [[
		'hash'          => 'device',
		'host_template' => '<b>template</b>',
		'graph_rules'   => [[
			'hash'          => 'rule',
			'snmp_query_id' => '<i>query</i>',
			'graph_type_id' => '<u>graph</u>',
		]],
	]]]);

	expect($result['errors'])->toHaveCount(3)
		->and(implode("\n", $result['errors']))->toContain('&lt;b&gt;template')
		->toContain('&lt;i&gt;query')->toContain('&lt;u&gt;graph')->not->toContain('<');
});

test('the import column check encodes the column name it rejects', function () {
	$data  = ['name' => 'network', '<s>column</s>' => 1];
	$debug = [];

	expect(automation_validate_import_columns('automation_networks', $data, $debug))->toBeFalse()
		->and($debug['errors'][0])->toContain('&lt;s&gt;column&lt;/s&gt;')->not->toContain('<s>');
});
