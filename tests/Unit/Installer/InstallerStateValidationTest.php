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
*/

require_once CACTI_PATH_INCLUDE . '/global.php';
require_once CACTI_PATH_BASE . '/install/functions.php';
require_once CACTI_PATH_LIBRARY . '/installer.php';

test('browser wizard steps are retired once no install is needed', function () {
	// A wizard step left behind by an upgrade finished outside the browser.
	expect(Installer::isStaleWizardStep(Installer::STEP_INSTALL_TYPE, false, 'Web'))->toBeTrue()
		->and(Installer::isStaleWizardStep(Installer::STEP_PROFILE_AND_AUTOMATION, false, 'Json'))->toBeTrue()
		// An install or upgrade that is still pending keeps the wizard.
		->and(Installer::isStaleWizardStep(Installer::STEP_INSTALL_TYPE, true, 'Web'))->toBeFalse()
		// The background run and finished states are handled separately.
		->and(Installer::isStaleWizardStep(Installer::STEP_INSTALL, false, 'Web'))->toBeFalse()
		->and(Installer::isStaleWizardStep(Installer::STEP_COMPLETE, false, 'Web'))->toBeFalse()
		// The command line installer keeps its own --force semantics.
		->and(Installer::isStaleWizardStep(Installer::STEP_INSTALL_TYPE, false, 'Cli'))->toBeFalse();
});

test('installer accepts only installed theme directory names', function () {
	expect(Installer::isInstalledThemeName('modern'))->toBeTrue()
		->and(Installer::isInstalledThemeName('../themes/modern'))->toBeFalse()
		->and(Installer::isInstalledThemeName('modern/../modern'))->toBeFalse()
		->and(Installer::isInstalledThemeName('./modern'))->toBeFalse()
		->and(Installer::isInstalledThemeName(''))->toBeFalse()
		->and(Installer::isInstalledThemeName('not-a-shipped-theme'))->toBeFalse();
});

test('missing schema guidance does not render the database password', function () {
	global $database_username, $database_default, $database_password;

	$saved = [$database_username, $database_default, $database_password];

	$database_username = 'cactiuser';
	$database_default  = 'cacti';
	$database_password = 'Fixture-Db-Secret-7f3a';

	try {
		$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
		$method    = new ReflectionMethod(Installer::class, 'exitSqlNeeded');
		$output    = $method->invoke($installer);
	} finally {
		[$database_username, $database_default, $database_password] = $saved;
	}

	expect($output)->not->toContain('Fixture-Db-Secret-7f3a')
		->and($output)->toContain('GRANT SELECT ON mysql.time_zone_name')
		->and($output)->toContain('cactiuser');
});
