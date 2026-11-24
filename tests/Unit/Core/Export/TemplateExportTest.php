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

/*
 * The probe runs the unchanged template export functions of lib/export.php
 * and form_save() of templates_export.php against SQLite.
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';

test('a Data Query field name is written as XML text that the import can read', function () {
	$verdict = cacti_import_export_probe(array(
		'slice'     => array(array('lib/export.php', 'data_query_to_xml'), array('lib/export.php', 'xml_character_encode')),
		'schema'    => array(
			"CREATE TABLE snmp_query (id INTEGER PRIMARY KEY, name TEXT DEFAULT '')",
			"CREATE TABLE snmp_query_graph (id INTEGER PRIMARY KEY, snmp_query_id INTEGER DEFAULT 0, name TEXT DEFAULT '')",
			'CREATE TABLE snmp_query_graph_rrd (snmp_query_graph_id INTEGER, data_template_id INTEGER, data_template_rrd_id INTEGER, snmp_field_name TEXT)',
			"CREATE TABLE snmp_query_graph_sv (id INTEGER PRIMARY KEY, snmp_query_graph_id INTEGER DEFAULT 0, sequence INTEGER DEFAULT 0, field_name TEXT DEFAULT '', text TEXT DEFAULT '')",
			"CREATE TABLE snmp_query_graph_rrd_sv (id INTEGER PRIMARY KEY, snmp_query_graph_id INTEGER DEFAULT 0, data_template_id INTEGER DEFAULT 0, sequence INTEGER DEFAULT 0, field_name TEXT DEFAULT '', text TEXT DEFAULT '')",
		),
		'db'        => array(
			'snmp_query'           => array(array('id' => 1, 'name' => 'Interfaces')),
			'snmp_query_graph'     => array(array('id' => 2, 'snmp_query_id' => 1, 'name' => 'Traffic')),
			'snmp_query_graph_rrd' => array(array('snmp_query_graph_id' => 2, 'data_template_id' => 3, 'data_template_rrd_id' => 4, 'snmp_field_name' => 'ifIn<Octets>&x')),
		),
		'globals'   => array('fields_data_query_edit' => array('name' => array('method' => 'textbox')), 'fields_data_query_item_edit' => array('name' => array('method' => 'textbox')), 'export_errors' => 0),
		'call'      => 'data_query_to_xml',
		'arguments' => array(1),
	));

	$xml = simplexml_load_string('<cacti>' . $verdict['result'] . '</cacti>');

	expect($verdict['error'])->toBeNull()
		->and($xml)->not->toBeFalse()
		->and((string) $xml->xpath('//snmp_field_name')[0])->toBe('ifIn<Octets>&x');
});

test('a missing VDEF counts as an export error and writes nothing', function () {
	$verdict = cacti_import_export_probe(array(
		'slice'        => array(array('lib/export.php', 'vdef_to_xml')),
		'schema'       => array("CREATE TABLE vdef (id INTEGER PRIMARY KEY, name TEXT DEFAULT '')", 'CREATE TABLE vdef_items (id INTEGER PRIMARY KEY, vdef_id INTEGER, sequence INTEGER)'),
		'globals'      => array('export_errors' => 0),
		'call'         => 'vdef_to_xml',
		'arguments'    => array(5),
		'read_globals' => array('export_errors'),
	));

	expect($verdict['error'])->toBeNull()
		->and((string) $verdict['result'])->toBe('')
		->and($verdict['globals']['export_errors'])->toBe(1);
});

test('a missing Graph Template is logged by its id', function () {
	$verdict = cacti_import_export_probe(array(
		'slice'     => array(array('lib/export.php', 'graph_template_to_xml')),
		'schema'    => array(
			"CREATE TABLE graph_templates (id INTEGER PRIMARY KEY, name TEXT DEFAULT '')",
			'CREATE TABLE graph_templates_graph (id INTEGER PRIMARY KEY, graph_template_id INTEGER, local_graph_id INTEGER)',
			"CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, graph_template_id INTEGER, local_graph_id INTEGER, hash TEXT DEFAULT '', sequence INTEGER)",
			'CREATE TABLE graph_template_input (id INTEGER PRIMARY KEY, graph_template_id INTEGER)',
		),
		'globals'   => array('export_errors' => 0),
		'call'      => 'graph_template_to_xml',
		'arguments' => array(8),
	));

	expect($verdict['error'])->toBeNull()
		->and($verdict['log'])->toBe(array('ERROR: Invalid Graph Template found in Database for Template id 8.  Please run database repair script to identify and/or correct.'));
});

function template_export_save(string $type): array {
	return cacti_import_export_probe(array(
		'slice'   => array(array('templates_export.php', 'form_save')),
		'request' => array('save_component_export' => '1', 'export_type' => $type, 'export_item_id' => '1', 'output_format' => '3'),
		'globals' => array('export_types' => array('host_template' => array('name' => 'Device Template', 'title_sql' => 'SELECT name FROM host_template WHERE id=|id|'))),
		'returns' => array('get_item_xml' => '<cacti></cacti>', 'clean_up_file_name' => 'router'),
		'schema'  => array("CREATE TABLE host_template (id INTEGER PRIMARY KEY, name TEXT DEFAULT '')"),
		'db'      => array('host_template' => array(array('id' => 1, 'name' => 'Router'))),
		'call'    => 'form_save',
	));
}

test('an export of an unknown type is refused before any XML is built', function () {
	$verdict = template_export_save('nonexistent');

	expect($verdict['error'])->toBeNull()
		->and(cacti_import_export_called($verdict))->not->toContain('get_item_xml')
		->and(array_column($verdict['raised'], 0))->toBe(array('export_type'))
		->and($verdict['headers'])->toBe(array('Location: templates_export.php'));
});

test('an export of a known type is written as a file', function () {
	$verdict = template_export_save('host_template');

	expect($verdict['error'])->toBeNull()
		->and(cacti_import_export_called($verdict))->toContain('get_item_xml')
		->and($verdict['headers'])->toContain('Content-Disposition: attachment; filename=cacti_host_template_router.xml')
		->and($verdict['output'])->toBe('<cacti></cacti>');
});
