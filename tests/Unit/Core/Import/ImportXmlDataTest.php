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
 * import_xml_data() walks the objects of a template file by type and hands
 * each one to its xml_to_*() importer. The probe runs the unchanged function
 * with the importers recorded, so the order, the handling of a refused object
 * and the repair step can be observed.
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';

function import_xml_data_scenario(array $objects, array $returns = array(), bool $preview = false): array {
	$tables = array('graph_templates', 'graph_templates_item', 'graph_template_input', 'data_template', 'data_template_rrd', 'host_template', 'data_input', 'data_input_fields', 'snmp_query', 'snmp_query_graph', 'snmp_query_graph_sv', 'snmp_query_graph_rrd_sv', 'graph_templates_gprint', 'cdef', 'cdef_items', 'vdef', 'vdef_items', 'data_source_profiles', 'data_source_profiles_rra');

	$codes = array('graph_template' => '00', 'data_template' => '01', 'data_input_method' => '03', 'vdef' => '18', 'cdef' => '05');
	$xml   = array();

	foreach ($objects as $index => $type) {
		$xml['hash_' . $codes[$type] . '0103' . str_pad((string) $index, 32, 'a', STR_PAD_LEFT)] = array('name' => $type . ' ' . $index);
	}

	return cacti_import_export_probe(array(
		'slice'     => array(
			array('lib/import.php', 'import_xml_data'),
			array('lib/import.php', 'import_hash_type_order'),
			array('lib/import.php', 'parse_xml_hash'),
			array('lib/import.php', 'check_hash_type'),
			array('lib/import.php', 'check_hash_version'),
		),
		'schema'    => array_map(fn ($table) => "CREATE TABLE $table (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', name TEXT DEFAULT '')", $tables),
		'globals'   => array('preview_only' => $preview),
		'returns'   => $returns + array('xml2array' => $xml, 'graph_template_input_xml_preflight' => true, 'db_begin_transaction' => true, 'xml_to_graph_template' => array(), 'xml_to_data_template' => array(), 'xml_to_data_input_method' => array(), 'xml_to_vdef' => array(), 'xml_to_cdef' => array()),
		'call'      => 'import_xml_data',
		'arguments' => array('<xml/>', false, 1),
	));
}

function import_xml_data_importers(array $verdict): array {
	return array_values(array_filter(cacti_import_export_called($verdict), fn ($name) => str_starts_with($name, 'xml_to_')));
}

test('VDEFs are imported before the Graph Templates whose items use them', function () {
	$verdict = import_xml_data_scenario(array('graph_template', 'vdef', 'cdef'));

	expect($verdict['error'])->toBeNull()
		->and(import_xml_data_importers($verdict))->toBe(array('xml_to_cdef', 'xml_to_vdef', 'xml_to_graph_template'));
});

test('an object its importer refuses stops the import with false instead of an error', function (string $refused) {
	$verdict = import_xml_data_scenario(array('data_input_method', 'data_template', 'graph_template', 'vdef'), array($refused => false));

	expect($verdict['error'])->toBeNull()
		->and($verdict['result'])->toBeFalse()
		->and(import_xml_data_importers($verdict))->not->toContain('xml_to_graph_template')
		->and(end($verdict['log']))->toContain('failed');
})->with(array('xml_to_data_input_method', 'xml_to_data_template', 'xml_to_vdef'));

test('the objects an importer accepts are still reported', function () {
	$verdict = import_xml_data_scenario(array('data_input_method', 'graph_template'), array('xml_to_data_input_method' => array('data_input_method' => array('x' => 3))));

	expect($verdict['error'])->toBeNull()
		->and($verdict['result'])->toBeArray()
		->and(import_xml_data_importers($verdict))->toBe(array('xml_to_data_input_method', 'xml_to_graph_template'));
});
