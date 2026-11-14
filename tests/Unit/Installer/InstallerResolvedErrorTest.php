<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 */


require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';
require_once dirname(__DIR__, 3) . '/install/functions.php';
require_once dirname(__DIR__, 3) . '/lib/installer.php';

function installer_with_errors($errors, $stepError) {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();

	foreach (array('errors' => $errors, 'stepError' => $stepError) as $name => $value) {
		$property = new ReflectionProperty(Installer::class, $name);
		$property->setAccessible(true);
		$property->setValue($installer, $value);
	}

	return $installer;
}

function installer_property($installer, $name) {
	$property = new ReflectionProperty(Installer::class, $name);
	$property->setAccessible(true);

	return $property->getValue($installer);
}

test('resolving the last path error leaves no error behind for the command line to trip over', function () {
	/*
	 * A default binary location that does not exist records an error before the
	 * command line options are applied. Applying a valid --path used to leave
	 * an empty Paths section, which cli/install_cacti.php still counted as an
	 * error ("0 issues in 1 section").
	 */
	$installer = installer_with_errors(array('Paths' => array('path_rrdtool' => 'File not found')), Installer::STEP_BINARY_LOCATIONS);

	$method = new ReflectionMethod(Installer::class, 'clearError');
	$method->setAccessible(true);
	$method->invoke($installer, 'Paths', 'path_rrdtool');

	expect($installer->getErrors())->toBe(array())
		->and(installer_property($installer, 'stepError'))->toBeFalse();
});

test('resolving one path error keeps the others and the step they recorded', function () {
	$installer = installer_with_errors(
		array('Paths' => array('path_rrdtool' => 'File not found', 'path_snmpget' => 'File not found')),
		Installer::STEP_BINARY_LOCATIONS
	);

	$method = new ReflectionMethod(Installer::class, 'clearError');
	$method->setAccessible(true);
	$method->invoke($installer, 'Paths', 'path_rrdtool');

	expect($installer->getErrors())->toBe(array('Paths' => array('path_snmpget' => 'File not found')))
		->and(installer_property($installer, 'stepError'))->toBe(Installer::STEP_BINARY_LOCATIONS);
});

test('setPaths clears resolved errors through clearError', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/installer.php');

	expect($source)->not->toContain("unset(\$this->errors['Paths'][\$name])")
		->and($source)->toContain("\$this->clearError('Paths', \$name);");
});
