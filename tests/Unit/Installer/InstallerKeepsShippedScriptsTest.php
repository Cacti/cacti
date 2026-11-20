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
 * The device template packages in install/templates embed their own copies of
 * shared scripts and resource files, and those copies can be older than the
 * files shipped next to them. The installer imports the packages on install
 * and upgrade, so it must not replace the shipped files with the embedded
 * copies. Package imports from the console or the CLI keep their behaviour.
 */

$root      = dirname(__DIR__, 3);
$import    = file_get_contents($root . '/lib/import.php');
$installer = file_get_contents($root . '/lib/installer.php');

test('import_package accepts a keep_existing_files flag that defaults to false', function () use ($import) {
	expect($import)->toContain("\$class = '',\n\t\$keep_existing_files = false) {");
});

test('existing files are kept before the write branch when the flag is set', function () use ($import) {
	$keep  = strpos($import, 'if ($keep_existing_files && file_exists($filename)) {');
	$write = strpos($import, "cacti_log('Writing file: ' . \$filename");

	expect($keep)->not->toBeFalse();
	expect($write)->not->toBeFalse();
	expect($keep)->toBeLessThan($write);
});

test('the installer imports packages without replacing shipped files', function () use ($installer) {
	expect($installer)->toContain("import_package(\$path . \$package, \$this->profile, false, false, false, false, true, array(), array(), \$info['class'], true);");
});

test('console and CLI imports still replace files', function () use ($root) {
	foreach (array('package_import.php', 'cli/import_package.php') as $file) {
		$src = file_get_contents($root . '/' . $file);

		preg_match_all('/import_package\(([^;]*)\);/', $src, $calls);

		expect($calls[1])->not->toBeEmpty();

		foreach ($calls[1] as $args) {
			expect(substr_count($args, ','))->toBeLessThan(10);
		}
	}
});

test('the shipped MikroTik scripts bind query index values instead of interpolating them', function () use ($root) {
	foreach (glob($root . '/scripts/ss_mikrotik_*.php') as $script) {
		$src = file_get_contents($script);

		expect(preg_match('/IN\s*\(\s*\'\$index/', $src))->toBe(0, basename($script));
		expect(preg_match('/=\s*\'\$index\'/', $src))->toBe(0, basename($script));
	}
});
