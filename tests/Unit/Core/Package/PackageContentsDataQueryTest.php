<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * The packager lists what a package will hold before it is built.  For a
 * Data Query export the listed query and its graph templates must be the ones
 * of that Data Query, not those of a Device Template with the same id.
 */

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/export.php';
require_once dirname(__DIR__, 4) . '/lib/package.php';

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->session = "$database_hostname:$database_port:$database_default";
	$this->prior   = $database_sessions[$this->session] ?? null;
	$this->fields  = [$GLOBALS['fields_data_query_edit'] ?? null, $GLOBALS['fields_data_query_item_edit'] ?? null];

	$conn = new FakeMySQLPDO();
	$conn->exec("CREATE TABLE snmp_query (id INTEGER PRIMARY KEY, hash TEXT, name TEXT, xml_path TEXT DEFAULT '')");
	$conn->exec('CREATE TABLE snmp_query_graph (id INTEGER PRIMARY KEY, snmp_query_id INTEGER, hash TEXT, name TEXT, graph_template_id INTEGER)');
	$conn->exec('CREATE TABLE host_template_snmp_query (host_template_id INTEGER, snmp_query_id INTEGER)');

	foreach (['snmp_query_graph_rrd', 'snmp_query_graph_rrd_sv', 'snmp_query_graph_sv'] as $table) {
		$conn->exec("CREATE TABLE $table (id INTEGER PRIMARY KEY, snmp_query_graph_id INTEGER, sequence INTEGER, data_template_id INTEGER, data_template_rrd_id INTEGER)");
	}

	$conn->exec("INSERT INTO snmp_query (id, hash, name) VALUES (1, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Interface Statistics'), (2, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'Disk Partitions')");
	$conn->exec("INSERT INTO snmp_query_graph VALUES (5, 1, 'cccccccccccccccccccccccccccccccc', 'In/Out Bits', 7), (6, 2, 'dddddddddddddddddddddddddddddddd', 'Disk Space', 8)");
	// Device Template 1 uses only the second Data Query.
	$conn->exec('INSERT INTO host_template_snmp_query VALUES (1, 2)');

	$database_sessions[$this->session]      = $conn;
	$GLOBALS['fields_data_query_edit']      = ['name' => ['method' => 'textbox']];
	$GLOBALS['fields_data_query_item_edit'] = ['name' => ['method' => 'textbox']];
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

test('a data query package lists that data query and its graph templates', function () {
	$output = get_package_contents('data_query', 1, false);

	expect($output)->toContain('Data Query Graph Templates')
		->toContain('In/Out Bits')
		->not->toContain('Disk Partitions')
		->not->toContain('Disk Space');
});
