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
 * Re-importing a Graph Template with "Remove Orphaned Graph Items" removes
 * the template items the file no longer has, and the graph items made from
 * them. The probe runs the unchanged xml_to_graph_template() against SQLite.
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';

function graph_template_orphan_import(bool $preview = false): array {
	$kept = str_repeat('1', 32);

	return cacti_import_export_probe(array(
		'slice'     => array(
			array('lib/import.php', 'xml_to_graph_template'),
			array('lib/import.php', 'parse_xml_hash'),
			array('lib/import.php', 'check_hash_type'),
			array('lib/import.php', 'check_hash_version'),
			array('lib/import.php', 'get_version_index'),
			array('lib/import.php', 'xml_character_decode'),
		),
		'schema'    => array(
			"CREATE TABLE graph_templates (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', name TEXT DEFAULT '', multiple TEXT DEFAULT '', test_source TEXT DEFAULT '')",
			'CREATE TABLE graph_templates_graph (id INTEGER PRIMARY KEY, graph_template_id INTEGER DEFAULT 0, local_graph_id INTEGER DEFAULT 0)',
			"CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', graph_template_id INTEGER DEFAULT 0, local_graph_id INTEGER DEFAULT 0, local_graph_template_item_id INTEGER DEFAULT 0, graph_type_id INTEGER DEFAULT 0, text_format TEXT DEFAULT '', value TEXT DEFAULT '')",
			"CREATE TABLE graph_template_input (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', graph_template_id INTEGER DEFAULT 0)",
		),
		'db'        => array(
			'graph_templates'       => array(array('id' => 1, 'hash' => str_repeat('a', 32), 'name' => 'Traffic'), array('id' => 2, 'hash' => str_repeat('b', 32), 'name' => 'Copy')),
			'graph_templates_graph' => array(array('id' => 1, 'graph_template_id' => 1)),
			'graph_templates_item'  => array(
				/* template 1: an item the file has, a hashless item, and an item the file dropped */
				array('id' => 1, 'hash' => $kept, 'graph_template_id' => 1, 'graph_type_id' => 4, 'text_format' => 'kept'),
				array('id' => 2, 'hash' => '', 'graph_template_id' => 1, 'graph_type_id' => 1, 'text_format' => 'hashless'),
				array('id' => 3, 'hash' => str_repeat('2', 32), 'graph_template_id' => 1, 'graph_type_id' => 4, 'text_format' => 'dropped'),
				/* graph items, which carry no hash, made from items 1 and 3 */
				array('id' => 10, 'graph_template_id' => 1, 'local_graph_id' => 5, 'local_graph_template_item_id' => 1),
				array('id' => 13, 'graph_template_id' => 1, 'local_graph_id' => 5, 'local_graph_template_item_id' => 3),
				/* a graph of another template, and another template with the dropped hash */
				array('id' => 11, 'graph_template_id' => 2, 'local_graph_id' => 6, 'local_graph_template_item_id' => 99),
				array('id' => 12, 'hash' => str_repeat('2', 32), 'graph_template_id' => 2, 'graph_type_id' => 4, 'text_format' => 'copy'),
			),
		),
		'globals'   => array(
			'preview_only'                     => $preview,
			'struct_graph'                     => array(),
			'struct_graph_item'                => array('graph_type_id' => array(), 'text_format' => array()),
			'fields_graph_template_input_edit' => array(),
			'graph_item_types'                 => array(1 => 'COMMENT', 4 => 'LINE1'),
		),
		'call'      => 'xml_to_graph_template',
		'arguments' => array(
			str_repeat('a', 32),
			array('name' => 'Traffic', 'graph' => array(), 'items' => array('hash_100103' . $kept => array('graph_type_id' => '4', 'text_format' => 'kept')), 'inputs' => array()),
			array(),
			'1.2.32',
			true,
		),
		'dump'         => array('graph_templates_item'),
		'read_globals' => array('import_debug_info'),
	));
}

test('removing orphans deletes only the dropped item of this template and its graph items', function () {
	$verdict = graph_template_orphan_import();

	expect($verdict['error'])->toBeNull()
		->and(array_column($verdict['tables']['graph_templates_item'], 'id'))->toBe(array('1', '2', '10', '11', '12'))
		->and($verdict['result']['graph_template_item'])->toBe(array(str_repeat('1', 32) => '1'));
});

test('a hashless template item is never reported as an orphan', function () {
	$verdict = graph_template_orphan_import(true);

	expect($verdict['error'])->toBeNull()
		->and(count($verdict['tables']['graph_templates_item']))->toBe(7)
		->and($verdict['globals']['import_debug_info']['orphans'])->toBe(array('Found Orphaned Graph Items, Type: LINE1, Text Format: dropped, Value: '));
});
