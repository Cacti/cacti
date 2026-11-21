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

test('a request cannot reach the install without passing the confirm step', function () {
	foreach ([Installer::STEP_WELCOME, Installer::STEP_INSTALL_TYPE, Installer::STEP_TEMPLATE_INSTALL, Installer::STEP_CHECK_TABLES] as $stored) {
		expect(Installer::isReachableStep($stored, Installer::STEP_INSTALL))->toBeFalse();
	}

	expect(Installer::isReachableStep(Installer::STEP_INSTALL_CONFIRM, Installer::STEP_INSTALL))->toBeTrue()
		->and(Installer::isReachableStep(Installer::STEP_INSTALL_OLDVERSION, Installer::STEP_INSTALL))->toBeTrue();
});

test('the wizard moves to the next step and to the steps it skips to', function () {
	expect(Installer::isReachableStep(Installer::STEP_WELCOME, Installer::STEP_CHECK_DEPENDENCIES))->toBeTrue()
		->and(Installer::isReachableStep(Installer::STEP_PERMISSION_CHECK, Installer::STEP_TEMPLATE_INSTALL))->toBeTrue()
		->and(Installer::isReachableStep(Installer::STEP_BINARY_LOCATIONS, Installer::STEP_CHECK_TABLES))->toBeTrue()
		->and(Installer::isReachableStep(Installer::STEP_CHECK_TABLES, Installer::STEP_INSTALL_CONFIRM))->toBeTrue()
		->and(Installer::isReachableStep(Installer::STEP_WELCOME, Installer::STEP_PERMISSION_CHECK))->toBeFalse();
});

test('a step already shown can be shown again', function () {
	// Previous, a browser refresh and the install page polling itself
	expect(Installer::isReachableStep(Installer::STEP_BINARY_LOCATIONS, Installer::STEP_WELCOME))->toBeTrue()
		->and(Installer::isReachableStep(Installer::STEP_BINARY_LOCATIONS, Installer::STEP_BINARY_LOCATIONS))->toBeTrue()
		->and(Installer::isReachableStep(Installer::STEP_INSTALL, Installer::STEP_INSTALL))->toBeTrue()
		->and(Installer::isReachableStep(Installer::STEP_ERROR, Installer::STEP_INSTALL))->toBeTrue()
		->and(Installer::isReachableStep(Installer::STEP_WELCOME, Installer::STEP_NONE))->toBeTrue();
});

test('steps outside the wizard are refused', function () {
	expect(Installer::isReachableStep(Installer::STEP_ERROR, Installer::STEP_GO_SITE))->toBeFalse()
		->and(Installer::isReachableStep(Installer::STEP_ERROR, 100))->toBeFalse();
});

test('the constructor drops an unreachable step before applying the request', function () {
	$source = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');

	expect($source)->toMatch('/runtime != \'Cli\' && isset\(\$install_params\[\'Step\'\]\) && !Installer::isReachableStep\(/')
		->and($source)->toContain("unset(\$install_params['Step']);");
});
