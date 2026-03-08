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
	expect($source)->toContain("require_once(CACTI_PATH_LIBRARY . '/database.php')");
});

test('lib/plugins.php uses require_once for plugin setup.php in install path', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	/* The install path now has an explicit file_exists guard followed by
	 * require_once — verify no bare include_once remains for setup.php. */
	expect(preg_match('/include_once\s*\(\s*CACTI_PATH_PLUGINS/', $source))->toBe(0);
	expect(preg_match('/require_once\s*\(\s*CACTI_PATH_PLUGINS/', $source))->toBe(1);
});

test('lib/plugins.php install path hard-fails with sanitized log when setup.php missing', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	/* Verify the file_exists guard exists before require_once in the install path. */
	expect($source)->toContain("file_exists(CACTI_PATH_PLUGINS . \"/\$plugin/setup.php\")");

	/* The log message must use preg_replace to strip non-safe chars from $plugin
	 * before interpolation, preventing pipe/newline injection into structured logs. */
	expect($source)->toContain("preg_replace('/[^a-zA-Z0-9_\\-]/', '', \$plugin)");

	/* raise_message must accompany the log call so the UI reflects the failure. */
	expect($source)->toContain("raise_message('plugin_missing'");
});

test('lib/plugins.php uninstall and check_config silently skip missing setup.php (by design)', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	/* Install hard-fails (file_exists guard + exit) while uninstall/check_config
	 * use a silent-skip pattern (require_once only inside if (file_exists(...))).
	 * This asymmetry is intentional: install must be explicit; cleanup is best-effort. */
	$uninstallGuards  = preg_match_all('/file_exists\(CACTI_PATH_PLUGINS.*setup\.php.*\)/', $source);
	expect($uninstallGuards)->toBeGreaterThanOrEqual(3);
});

test('cli/audit_database.php uses require_once inside file_exists guard', function () {
	$source = file_get_contents(__DIR__ . '/../../cli/audit_database.php');

	expect($source)->not->toContain('include_once(');
	expect($source)->toContain("require_once(\$plugin . '/setup.php')");
});

test('cli/audit_database.php inner includes/database.php load is guarded by file_exists', function () {
	$source = file_get_contents(__DIR__ . '/../../cli/audit_database.php');

	/* Confirm includes/database.php is loaded with require_once and only when present. */
	expect($source)->toContain("require_once(\$plugin . '/includes/database.php')");
	expect($source)->toContain("file_exists(\$plugin . '/includes/database.php')");
});
