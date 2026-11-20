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

function installer_legacy_check($version) {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
	$property  = new ReflectionProperty(Installer::class, 'old_cacti_version');
	$property->setAccessible(true);
	$property->setValue($installer, $version);

	return $installer->isPre_v0_8_UpgradeNeeded();
}

test('only a database from 0.8.5a or earlier needs the legacy upgrade step', function () {
	expect(installer_legacy_check('0.8.5a'))->toBeTrue()
		->and(installer_legacy_check('0.8.4'))->toBeTrue()
		->and(installer_legacy_check('1.2.31'))->toBeFalse();
});

test('a new install is not mistaken for a legacy database', function () {
	// version_compare() orders the text new_install below 0.8.5a
	expect(installer_legacy_check('new_install'))->toBeFalse();
});
