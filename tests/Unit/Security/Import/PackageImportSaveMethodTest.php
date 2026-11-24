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
 * The Package import page keeps the previewed Package in the session, and
 * its save action imports that Package when no file is uploaded. The save
 * must come from the form, as a POST that csrf-magic checks. The probe runs
 * the unchanged form_save() of package_import.php and csrf_require_post().
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';

function package_import_save(string $method): array {
	$file = tempnam(sys_get_temp_dir(), 'cacti-package-');

	try {
		return cacti_import_export_probe(array(
			'slice'   => array(array('package_import.php', 'form_save'), array('include/csrf.php', 'csrf_require_post')),
			'server'  => array('REQUEST_METHOD' => $method),
			'session' => array('sess_import_package' => 'package'),
			'request' => array('action' => 'save', 'save_component_import' => '1', 'data_source_profile' => '1', 'preview_only' => '', 'remove_orphans' => 'on'),
			'returns' => array('package_import_write_session_file' => $file, 'package_validate_signature' => true, 'import_package_get_name' => 'ACME', 'import_package_get_details' => array(), 'import_package' => array(array(), array())),
			'call'    => 'form_save',
		));
	} finally {
		/* a completed import removes the file itself */
		if (is_file($file)) {
			unlink($file);
		}
	}
}

test('the Package import save refuses a request that is not a POST', function (string $method) {
	$verdict = package_import_save($method);

	expect($verdict['status'])->toBe(405)
		->and($verdict['headers'])->toBe(array('Allow: POST'))
		->and(cacti_import_export_called($verdict))->not->toContain('import_package');
})->with(array('GET', 'HEAD'));

test('the Package import save still imports the Package on a POST', function () {
	$verdict = package_import_save('POST');

	expect($verdict['status'])->toBeNull()
		->and(cacti_import_export_called($verdict))->toContain('import_package')
		->and(array_column($verdict['raised'], 0))->toBe(array('import_success'));
});
