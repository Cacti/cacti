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

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 3) . '/lib/installer.php';

function installer_with_errors(array $errors, mixed $stepError) : Installer {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();

	foreach (['errors' => $errors, 'stepError' => $stepError] as $name => $value) {
		(new ReflectionProperty(Installer::class, $name))->setValue($installer, $value);
	}

	return $installer;
}

test('resolving the last path error leaves no error behind for the command line to trip over', function () {
	/*
	 * A default binary location that does not exist records an error before the
	 * command line options are applied. Applying a valid --path used to leave
	 * an empty Paths section, which cli/install_cacti.php still counted as an
	 * error ("0 issues in 1 section") and which kept the wizard on the binary
	 * locations step.
	 */
	$installer = installer_with_errors(['Paths' => ['path_rrdtool' => 'File not found']], Installer::STEP_BINARY_LOCATIONS);

	(new ReflectionMethod(Installer::class, 'clearError'))->invoke($installer, 'Paths', 'path_rrdtool');

	expect($installer->getErrors())->toBe([])
		->and((new ReflectionProperty(Installer::class, 'stepError'))->getValue($installer))->toBeFalse();
});

test('resolving one path error keeps the others and the step they recorded', function () {
	$installer = installer_with_errors(
		['Paths' => ['path_rrdtool' => 'File not found', 'path_fping' => 'File not found']],
		Installer::STEP_BINARY_LOCATIONS
	);

	(new ReflectionMethod(Installer::class, 'clearError'))->invoke($installer, 'Paths', 'path_rrdtool');

	expect($installer->getErrors())->toBe(['Paths' => ['path_fping' => 'File not found']])
		->and((new ReflectionProperty(Installer::class, 'stepError'))->getValue($installer))->toBe(Installer::STEP_BINARY_LOCATIONS);
});

test('setPaths clears resolved errors through clearError', function () {
	$source = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');

	expect($source)->not->toContain("unset(\$this->errors['Paths'][\$name])")
		->and($source)->toContain("\$this->clearError('Paths', \$name);");
});
