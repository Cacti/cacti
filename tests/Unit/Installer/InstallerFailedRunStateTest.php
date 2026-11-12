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

function installer_polled(string $runtime, int $step, array $params, mixed $error) : bool {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
	$property  = new ReflectionProperty(Installer::class, 'runtime');
	$property->setValue($installer, $runtime);

	return (new ReflectionMethod(Installer::class, 'isFailedInstallBeingPolled'))->invoke($installer, $step, $params, $error);
}

test('the install page keeps the report of a failed run while it polls', function () {
	expect(installer_polled('Web', Installer::STEP_ERROR, ['Step' => Installer::STEP_INSTALL], 'schema incomplete'))->toBeTrue()
		->and(installer_polled('Json', Installer::STEP_ERROR, ['Step' => (string) Installer::STEP_INSTALL], 'schema incomplete'))->toBeTrue();
});

test('a fresh page load, the command line or a clean run still restart the wizard', function () {
	expect(installer_polled('Web', Installer::STEP_ERROR, [], 'schema incomplete'))->toBeFalse()
		->and(installer_polled('Web', Installer::STEP_ERROR, ['Step' => Installer::STEP_WELCOME], 'schema incomplete'))->toBeFalse()
		->and(installer_polled('Cli', Installer::STEP_ERROR, ['Step' => Installer::STEP_INSTALL], 'schema incomplete'))->toBeFalse()
		->and(installer_polled('Web', Installer::STEP_ERROR, ['Step' => Installer::STEP_INSTALL], ''))->toBeFalse()
		->and(installer_polled('Web', Installer::STEP_COMPLETE, ['Step' => Installer::STEP_INSTALL], 'x'))->toBeFalse();
});

test('a command line run after a failed run starts over with its own options', function () {
	$source = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');

	// the reset must hand the CLI step back to the first step, not pin it there
	expect($source)->toMatch("/runtime == 'Cli'\\)\\s*\\{[^}]*\\\$step = Installer::STEP_WELCOME;/s")
		->and($source)->toContain("\$install_version = (string) read_config_option('install_version', true);");
});
