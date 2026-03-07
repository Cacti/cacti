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

/**
 * Test for issue #6788: include_once replaced with require_once for core
 * library files and plugin setup loaders so missing files produce immediate
 * fatal errors rather than silent undefined-function failures downstream.
 */

test('include/global.php uses require_once for all core library includes', function () {
	$source = file_get_contents(__DIR__ . '/../../include/global.php');

	expect($source)->not->toContain('include_once(');
});

test('lib/plugins.php uses require_once for plugin setup.php in install path', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	/* The install path now has an explicit file_exists guard followed by
	 * require_once — verify no bare include_once remains for setup.php. */
	expect(preg_match('/include_once\s*\(\s*CACTI_PATH_PLUGINS/', $source))->toBe(0);
});

test('cli/audit_database.php uses require_once inside file_exists guard', function () {
	$source = file_get_contents(__DIR__ . '/../../cli/audit_database.php');

	expect($source)->not->toContain('include_once(');
});
