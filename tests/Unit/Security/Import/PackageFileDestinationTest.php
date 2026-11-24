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
 * A Package carries script and resource files that the import writes below
 * the Cacti directory. The test builds a Package signed with a key of its
 * own, which the probe accepts as the trusted key, and runs the unchanged
 * import_package() and Package readers of lib/import.php.
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';
require_once dirname(__DIR__, 3) . '/Helpers/SignedPackage.php';

function package_file_import(array $names): array {
	$base = sys_get_temp_dir() . '/cacti-package-base-' . bin2hex(random_bytes(4));

	foreach (array('scripts', 'resource/snmp_queries', 'plugins/demo/scripts', 'lib/myresource') as $directory) {
		mkdir($base . '/' . $directory, 0777, true);
	}

	$files = array();

	foreach ($names as $name) {
		$files[$name] = 'content of ' . $name;
	}

	$package = cacti_test_signed_package($files);

	try {
		$verdict = cacti_import_export_probe(array(
			'slice'      => cacti_test_package_reader_slices(),
			'base_path'  => $base,
			'public_key' => $package['public_key'],
			'returns'    => array('import_xml_data' => array()),
			'call'       => 'import_package',
			'arguments'  => array($package['file'], 1, false, false, false, false, false),
		));

		$written = array();

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $path) {
			$written[] = substr($path->getPathname(), strlen($base) + 1);
		}

		sort($written);

		return $verdict + array('written' => $written);
	} finally {
		unlink($package['file']);

		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path) {
			$path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
		}

		rmdir($base);
	}
}

test('script and resource files are written below the scripts and resource directories', function () {
	$verdict = package_file_import(array('scripts/ss_demo.php', 'resource/snmp_queries/demo.xml'));

	expect($verdict['error'])->toBeNull()
		->and($verdict['written'])->toBe(array('resource/snmp_queries/demo.xml', 'scripts/ss_demo.php'))
		->and($verdict['result'][1])->toHaveCount(2);
});

test('a file whose name has scripts or resource deeper in its path is not written', function (string $name) {
	$verdict = package_file_import(array($name, 'scripts/ss_demo.php'));

	expect($verdict['error'])->toBeNull()
		->and($verdict['written'])->toBe(array('scripts/ss_demo.php'))
		->and(cacti_import_export_called($verdict))->not->toContain('import_xml_data')
		->and(implode("\n", $verdict['log']))->toContain('outside the scripts and resource directories: ' . $name);
})->with(array('plugins/demo/scripts/evil.php', 'lib/myresource/evil.php'));
