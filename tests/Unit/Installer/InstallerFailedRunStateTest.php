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

function installer_polled($runtime, $step, $params, $error) {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
	$property  = new ReflectionProperty(Installer::class, 'runtime');
	$property->setAccessible(true);
	$property->setValue($installer, $runtime);

	$method = new ReflectionMethod(Installer::class, 'isFailedInstallBeingPolled');
	$method->setAccessible(true);

	return $method->invoke($installer, $step, $params, $error);
}

test('the install page keeps the report of a failed run while it polls', function () {
	expect(installer_polled('Web', Installer::STEP_ERROR, array('Step' => Installer::STEP_INSTALL), 'schema incomplete'))->toBeTrue()
		->and(installer_polled('Json', Installer::STEP_ERROR, array('Step' => (string) Installer::STEP_INSTALL), 'schema incomplete'))->toBeTrue();
});

test('a fresh page load, the command line or a clean run still restart the wizard', function () {
	expect(installer_polled('Web', Installer::STEP_ERROR, array(), 'schema incomplete'))->toBeFalse()
		->and(installer_polled('Web', Installer::STEP_ERROR, array('Step' => Installer::STEP_WELCOME), 'schema incomplete'))->toBeFalse()
		->and(installer_polled('Cli', Installer::STEP_ERROR, array('Step' => Installer::STEP_INSTALL), 'schema incomplete'))->toBeFalse()
		->and(installer_polled('Web', Installer::STEP_ERROR, array('Step' => Installer::STEP_INSTALL), ''))->toBeFalse()
		->and(installer_polled('Web', Installer::STEP_COMPLETE, array('Step' => Installer::STEP_INSTALL), 'x'))->toBeFalse();
});

test('a command line run after a failed run starts over with its own options', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/installer.php');

	// the reset must hand the CLI step back to the first step, not pin it there
	expect($source)->toMatch("/runtime == 'Cli'\\)\\s*\\{[^}]*\\\$step = Installer::STEP_WELCOME;/s");
});
