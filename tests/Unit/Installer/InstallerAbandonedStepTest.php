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

test('the command line treats a stored install step as abandoned', function () {
	expect(Installer::isAbandonedInstallStep(Installer::STEP_INSTALL, 'Cli'))->toBeTrue();
});

test('the web keeps telling a live install from a dead one by its timestamps', function () {
	expect(Installer::isAbandonedInstallStep(Installer::STEP_INSTALL, 'Web'))->toBeFalse()
		->and(Installer::isAbandonedInstallStep(Installer::STEP_INSTALL, 'Json'))->toBeFalse()
		->and(Installer::isAbandonedInstallStep(Installer::STEP_CHECK_TABLES, 'Cli'))->toBeFalse()
		->and(Installer::isAbandonedInstallStep(Installer::STEP_ERROR, 'Cli'))->toBeFalse();
});

test('the command line takes the install process lock before it builds the installer', function () {
	/*
	 * Without the lock a stored STEP_INSTALL may belong to a live web install, so
	 * treating it as abandoned would be wrong.
	 */
	$cli = file_get_contents(dirname(__DIR__, 3) . '/cli/install_cacti.php');

	expect(strpos($cli, "register_process_start('install', 'master'"))->toBeLessThan(strpos($cli, 'new Installer('))
		->and($cli)->toContain("register_shutdown_function('unregister_process', 'install', 'master', 0);");
});
