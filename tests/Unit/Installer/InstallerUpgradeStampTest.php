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

test('upgrade steps below the running release are recorded so a failed upgrade resumes', function () {
	expect(Installer::shouldStampUpgradeVersion('1.2.30', '1.2.31'))->toBeTrue()
		->and(Installer::shouldStampUpgradeVersion('1.2.31', '1.2.31'))->toBeFalse();
});

test('the running release is never recorded before the install has finished', function () {
	/*
	 * A database that reads as CACTI_VERSION makes getMode() return MODE_NONE,
	 * so an upgrade that failed in templates or in the final schema check could
	 * not be run again.
	 */
	expect(Installer::shouldStampUpgradeVersion('1.2.31', CACTI_VERSION))->toBeFalse()
		->and(Installer::shouldStampUpgradeVersion('1.2.31', '9.9.9'))->toBeFalse();
});

test('upgradeDatabase leaves the final version to install()', function () {
	$source = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');
	$start  = strpos($source, 'private function upgradeDatabase()');
	$end    = strpos($source, 'private function checkDatabaseUpgrade');
	$body   = substr($source, $start, $end - $start);

	expect($body)->not->toContain('CACTI_VERSION_FULL')
		->and($body)->toContain('Installer::shouldStampUpgradeVersion(');
});
