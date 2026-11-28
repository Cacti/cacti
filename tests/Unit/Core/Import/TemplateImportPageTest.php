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
 * The probe runs the unchanged form_save() and prepare_template_display() of
 * templates_import.php with import_xml_data() answered by the scenario.
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';

function template_import_save($debug_data, string $preview): array {
	$file = tempnam(sys_get_temp_dir(), 'cacti-template-');

	file_put_contents($file, '<cacti/>');

	try {
		return cacti_import_export_probe(array(
			'slice'   => array(array('templates_import.php', 'form_save')),
			'request' => array('save_component_import' => '1', 'import_data_source_profile' => '1', 'preview_only' => $preview),
			'files'   => array('import_file' => array('tmp_name' => $file, 'name' => 'template.xml')),
			'returns' => array('import_xml_data' => $debug_data),
			'call'    => 'form_save',
		));
	} finally {
		unlink($file);
	}
}

test('a template import that fails is reported as failed', function () {
	$verdict = template_import_save(false, 'false');

	expect($verdict['error'])->toBeNull()
		->and(array_column($verdict['raised'], 0))->toBe(array('import_fail'))
		->and($verdict['raised'][0][2])->toBe(3) // MESSAGE_LEVEL_ERROR
		->and($verdict['headers'])->toBe(array('Location: templates_import.php'));
});

test('a template import that succeeds is reported as succeeded', function () {
	$verdict = template_import_save(array('graph_template' => array()), 'false');

	expect($verdict['error'])->toBeNull()
		->and(array_column($verdict['raised'], 0))->toBe(array('import_success'))
		->and($verdict['headers'])->toBe(array('Location: templates_import.php'));
});

test('the preview keeps the dependencies of each template for the Dependencies column', function () {
	$dep = 'hash_050103' . str_repeat('d', 32);

	$verdict = cacti_import_export_probe(array(
		'slice'     => array(array('templates_import.php', 'prepare_template_display')),
		'globals'   => array('hash_type_names' => array('graph_template' => 'Graph Template')),
		'call'      => 'prepare_template_display',
		'arguments' => array(array('graph_template' => array(array('hash' => str_repeat('a', 32), 'type' => 'new', 'title' => 'Traffic', 'result' => 'preview', 'dep' => array($dep => 'unmet'))))),
	));

	expect($verdict['error'])->toBeNull()
		->and($verdict['result'][str_repeat('a', 32)]['deps'])->toBe(array($dep => 'unmet'));
});
