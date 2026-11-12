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

test('the command line treats a stored install step as abandoned', function () {
	/*
	 * cli/install_cacti.php holds the install process lock before it builds the
	 * installer, so a stored STEP_INSTALL is left over from a run that died. It
	 * used to discard the options and report an install in progress forever.
	 */
	expect(Installer::isAbandonedInstallStep(Installer::STEP_INSTALL, 'Cli'))->toBeTrue();
});

test('the web keeps telling a live install from a dead one by its timestamps', function () {
	expect(Installer::isAbandonedInstallStep(Installer::STEP_INSTALL, 'Web'))->toBeFalse()
		->and(Installer::isAbandonedInstallStep(Installer::STEP_INSTALL, 'Json'))->toBeFalse()
		->and(Installer::isAbandonedInstallStep(Installer::STEP_CHECK_TABLES, 'Cli'))->toBeFalse()
		->and(Installer::isAbandonedInstallStep(Installer::STEP_ERROR, 'Cli'))->toBeFalse();
});

test('the installer takes the process lock before it reasons about a stored install step', function () {
	$cli = file_get_contents(CACTI_PATH_BASE . '/cli/install_cacti.php');

	expect(strpos($cli, "register_process_start('install', 'master'"))->toBeLessThan(strpos($cli, 'new Installer('));
});
