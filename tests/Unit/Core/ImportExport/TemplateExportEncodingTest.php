<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * The data query editor stores the output field name of a data source mapping
 * as free text, so the template export has to encode it like the fields
 * around it for the document to stay well formed.
 */

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/export.php';
require_once dirname(__DIR__, 4) . '/lib/xml.php';

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->session = "$database_hostname:$database_port:$database_default";
	$this->prior   = $database_sessions[$this->session] ?? null;
	$this->fields  = [$GLOBALS['fields_data_query_edit'] ?? null, $GLOBALS['fields_data_query_item_edit'] ?? null];

	$conn = new FakeMySQLPDO();
	$conn->exec('CREATE TABLE snmp_query (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE snmp_query_graph (id INTEGER PRIMARY KEY, snmp_query_id INTEGER, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE snmp_query_graph_rrd (snmp_query_graph_id INTEGER, data_template_id INTEGER, data_template_rrd_id INTEGER, snmp_field_name TEXT)');
	$conn->exec('CREATE TABLE snmp_query_graph_rrd_sv (id INTEGER PRIMARY KEY, snmp_query_graph_id INTEGER, sequence INTEGER)');
	$conn->exec('CREATE TABLE snmp_query_graph_sv (id INTEGER PRIMARY KEY, snmp_query_graph_id INTEGER, sequence INTEGER)');
	$conn->exec('CREATE TABLE data_template (id INTEGER PRIMARY KEY, hash TEXT)');
	$conn->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY, hash TEXT)');
	$conn->exec("INSERT INTO snmp_query VALUES (1, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Query')");
	$conn->exec("INSERT INTO snmp_query_graph VALUES (2, 1, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'Graph')");
	$conn->exec("INSERT INTO snmp_query_graph_rrd VALUES (2, 3, 4, 'ifIn</snmp_field_name><extra>1</extra><x a=\"&\">')");
	$conn->exec("INSERT INTO data_template VALUES (3, 'cccccccccccccccccccccccccccccccc')");
	$conn->exec("INSERT INTO data_template_rrd VALUES (4, 'dddddddddddddddddddddddddddddddd')");

	$database_sessions[$this->session]         = $conn;
	$GLOBALS['fields_data_query_edit']         = ['name' => ['method' => 'textbox']];
	$GLOBALS['fields_data_query_item_edit']    = ['name' => ['method' => 'textbox']];
});

afterEach(function () {
	global $database_sessions;

	if ($this->prior === null) {
		unset($database_sessions[$this->session]);
	} else {
		$database_sessions[$this->session] = $this->prior;
	}

	[$GLOBALS['fields_data_query_edit'], $GLOBALS['fields_data_query_item_edit']] = $this->fields;
});

test('the data source field name of a data query mapping is encoded in the export', function () {
	$xml = data_query_to_xml(1);

	expect($xml)->toContain('<snmp_field_name>ifIn&lt;/snmp_field_name&gt;&lt;extra&gt;1&lt;/extra&gt;')
		->not->toContain('<extra>');

	$graphs = xml2array($xml)['graphs'];
	$item   = reset($graphs)['rrd']['item_000'];

	expect(array_keys($item))->toBe(['snmp_field_name', 'data_template_id', 'data_template_rrd_id'])
		->and($item['snmp_field_name'])->toBe('ifIn</snmp_field_name><extra>1</extra><x a="&">');
});
