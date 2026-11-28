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
 * The probe runs the unchanged xml_to_data_query() and hash_to_friendly_name()
 * against SQLite.
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';

function data_query_import_slices(): array {
	return array(
		array('lib/import.php', 'xml_to_data_query'),
		array('lib/import.php', 'resolve_hash_to_id'),
		array('lib/import.php', 'hash_to_friendly_name'),
		array('lib/import.php', 'parse_xml_hash'),
		array('lib/import.php', 'check_hash_type'),
		array('lib/import.php', 'check_hash_version'),
		array('lib/import.php', 'xml_character_decode'),
	);
}

function data_query_import(array $graph): array {
	return cacti_import_export_probe(array(
		'slice'     => data_query_import_slices(),
		'schema'    => array(
			"CREATE TABLE snmp_query (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', name TEXT DEFAULT '')",
			"CREATE TABLE snmp_query_graph (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', snmp_query_id INTEGER DEFAULT 0, name TEXT DEFAULT '')",
			"CREATE TABLE snmp_query_graph_rrd (snmp_query_graph_id INTEGER, data_template_id INTEGER, data_template_rrd_id INTEGER, snmp_field_name TEXT, PRIMARY KEY (snmp_query_graph_id, data_template_id, data_template_rrd_id))",
			"CREATE TABLE snmp_query_graph_sv (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', snmp_query_graph_id INTEGER DEFAULT 0, sequence INTEGER DEFAULT 0, field_name TEXT DEFAULT '', text TEXT DEFAULT '')",
			"CREATE TABLE snmp_query_graph_rrd_sv (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', snmp_query_graph_id INTEGER DEFAULT 0, data_template_id INTEGER DEFAULT 0, sequence INTEGER DEFAULT 0, field_name TEXT DEFAULT '', text TEXT DEFAULT '')",
		),
		'globals'   => array('preview_only' => false, 'fields_data_query_edit' => array('name' => array()), 'fields_data_query_item_edit' => array('name' => array())),
		'call'      => 'xml_to_data_query',
		'arguments' => array(
			str_repeat('f', 32),
			array('name' => 'Interfaces', 'graphs' => array('hash_110103' . str_repeat('1', 32) => $graph + array('name' => 'Traffic', 'rrd' => array()))),
			array('data_template' => array(str_repeat('7', 32) => 4)),
			array(),
			false,
		),
		'dump'      => array('snmp_query_graph_rrd_sv'),
	));
}

function data_query_sv_data_source(string $hash, string $data_template): array {
	return array('hash_130103' . $hash => array('data_template_id' => 'hash_010103' . $data_template, 'sequence' => '1', 'field_name' => 'name', 'text' => '|query_ifName|'));
}

test('a Data Source suggested value that is skipped adds no id to the hash cache', function () {
	$verdict = data_query_import(array(
		'sv_graph'       => array(),
		'sv_data_source' => data_query_sv_data_source(str_repeat('2', 32), str_repeat('7', 32)) + data_query_sv_data_source(str_repeat('3', 32), str_repeat('8', 32)),
	));

	expect($verdict['error'])->toBeNull()
		->and(array_column($verdict['tables']['snmp_query_graph_rrd_sv'], 'hash'))->toBe(array(str_repeat('2', 32)))
		->and($verdict['result']['data_query_sv_data_source'])->toBe(array(str_repeat('2', 32) => '1'));
});

test('an empty Data Source suggested value list is reported when Graph suggested values exist', function () {
	$verdict = data_query_import(array(
		'sv_graph'       => array('hash_120103' . str_repeat('4', 32) => array('sequence' => '1', 'field_name' => 'title', 'text' => '|host_description|')),
		'sv_data_source' => array(),
	));

	expect($verdict['error'])->toBeNull()
		->and($verdict['log'])->toContain('WARNING: Suggested Values Array for Data Template Empty');
});

test('a Data Source Profile hash is shown by its name', function () {
	$verdict = cacti_import_export_probe(array(
		'slice'     => data_query_import_slices(),
		'schema'    => array("CREATE TABLE data_source_profiles (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', name TEXT DEFAULT '')"),
		'db'        => array('data_source_profiles' => array(array('id' => 1, 'hash' => str_repeat('5', 32), 'name' => 'High <Collection>'))),
		'call'      => 'hash_to_friendly_name',
		'arguments' => array('hash_200103' . str_repeat('5', 32), false),
	));

	expect($verdict['error'])->toBeNull()
		->and($verdict['result'])->toBe('High &lt;Collection&gt;');
});
