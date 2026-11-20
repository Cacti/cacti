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
require_once CACTI_PATH_INCLUDE . '/global.php';
require_once CACTI_PATH_BASE . '/install/functions.php';
require_once CACTI_PATH_LIBRARY . '/installer.php';

/*
 * Issue #8771: Installer::beginInstall() builds the Installer with no Runtime
 * when it runs from install/background.php. Reading the runtime before
 * setRuntime() accepted a value used to throw "must not be accessed before
 * initialization" and abort the install.
 */
test('installer runtime is readable before a runtime is applied (#8771)', function () {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
	$runtime   = new ReflectionProperty(Installer::class, 'runtime');

	expect($runtime->getValue($installer))->toBe('unknown');
});

test('an unsupported runtime leaves the default in place (#8771)', function () {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
	(new ReflectionProperty(Installer::class, 'errors'))->setValue($installer, []);

	$installer->setRuntime('unknown');

	expect((new ReflectionProperty(Installer::class, 'runtime'))->getValue($installer))->toBe('unknown');
});
