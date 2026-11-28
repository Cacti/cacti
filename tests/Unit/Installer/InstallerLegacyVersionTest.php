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

function installer_legacy_check(string $version) : bool {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(Installer::class, 'old_cacti_version'))->setValue($installer, $version);

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
