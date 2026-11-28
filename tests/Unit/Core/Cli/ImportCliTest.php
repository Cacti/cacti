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
 * The probe runs the unchanged cli/import_template.php and
 * cli/import_package.php, without their bootstrap, with the import functions
 * recorded and the Data Source Profiles in SQLite.
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';

function import_cli(string $script, array $argv, array $returns = array()): array {
	$file = tempnam(sys_get_temp_dir(), 'cacti-import-cli-');

	file_put_contents($file, '<cacti/>');

	try {
		return cacti_import_export_probe(array(
			'cli'     => $script,
			'argv'    => array_map(fn ($argument) => str_replace('{file}', $file, $argument), $argv),
			'schema'  => array("CREATE TABLE data_source_profiles (id INTEGER PRIMARY KEY, name TEXT DEFAULT '', `default` TEXT DEFAULT '')"),
			'db'      => array('data_source_profiles' => array(array('id' => 1, 'name' => 'System Default', 'default' => 'on'), array('id' => 2, 'name' => 'High'))),
			'returns' => $returns + array('import_xml_data' => array('graph_template' => array()), 'import_package' => array(array(), array())),
		));
	} finally {
		unlink($file);
	}
}

function import_cli_call(array $verdict, string $name): ?array {
	foreach ($verdict['calls'] as $call) {
		if ($call[0] == $name) {
			return $call[1];
		}
	}

	return null;
}

test('a template preview from the command line is shown as a text preview', function () {
	$verdict = import_cli('cli/import_template.php', array('--filename={file}', '--preview'));

	expect($verdict['error'])->toBeNull()
		->and(array_slice(import_cli_call($verdict, 'import_display_results'), 2))->toBe(array(false, 1));
});

test('a template import uses the profile id given with --with-profile', function () {
	$verdict = import_cli('cli/import_template.php', array('--filename={file}', '--with-profile', '--profile-id=2'));

	expect($verdict['output'])->toContain("Ignoring '--with-profile'")
		->and($verdict['output'])->not->toContain('FATAL')
		->and(import_cli_call($verdict, 'import_xml_data')[2])->toBe('2');
});

test('the template importer names an invalid argument', function () {
	expect(import_cli('cli/import_template.php', array('--bogus'))['output'])->toContain('ERROR: Invalid Argument: (--bogus)');
});

test('every line of the template importer help ends with a line break', function () {
	expect(import_cli('cli/import_template.php', array('--help'))['output'])->toContain('in the new version of the template.' . PHP_EOL . '    --replace-svalues');
});

test('a Package import from the command line gives Device Templates the class of the Package', function () {
	$verdict = import_cli('cli/import_package.php', array('--filename={file}'), array('import_package_get_details' => array('name' => 'ACME', 'class' => 'linux')));

	expect($verdict['error'])->toBeNull()
		->and($verdict['output'])->toContain('Read 8 bytes of Package data')
		->and(import_cli_call($verdict, 'import_package')[9])->toBe('linux');
});
