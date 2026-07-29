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
 * 1.3 raises the minimum PHP version to 8.4. utility_php_verify_recommends()
 * and phpversion_check() used to hardcode their own '7.4'/'7.4.0' floor
 * independently of CACTI_PHP_VERSION_MINIMUM; they now derive it from the
 * constant, falling back to an 8.4-based default if it isn't defined.
 *
 * The fallback branches can't be exercised directly: CACTI_PHP_VERSION_MINIMUM
 * is already defined process-wide by the time these functions run, so
 * defined('CACTI_PHP_VERSION_MINIMUM') is always true in a real request. The
 * fallback literals are instead verified by inspecting the source, matching
 * the source-scan pattern used elsewhere in this suite (e.g.
 * DatasourceScriptErrorReturnTest, GraphRealtimeShellTest).
 */

require_once __DIR__ . '/../../include/global_constants.php';

test('CACTI_PHP_VERSION_MINIMUM is 8.4.0', function () {
	expect(defined('CACTI_PHP_VERSION_MINIMUM'))->toBeTrue()
		->and(CACTI_PHP_VERSION_MINIMUM)->toBe('8.4.0');
});

test('utility_php_verify_recommends() derives its recommended version from CACTI_PHP_VERSION_MINIMUM', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/utility.php');

	expect($src)->toContain(
		"\$rec_version    = defined('CACTI_PHP_VERSION_MINIMUM') ? CACTI_PHP_VERSION_MINIMUM : '8.4.0';"
	);
	expect($src)->not->toContain("\$rec_version    = '7.4.0';");
});

test('poller_maintenance.php phpversion_check() derives its minimum version from CACTI_PHP_VERSION_MINIMUM', function () {
	$src = file_get_contents(__DIR__ . '/../../poller_maintenance.php');

	expect($src)->toContain(
		"\$rec_version = defined('CACTI_PHP_VERSION_MINIMUM') ? CACTI_PHP_VERSION_MINIMUM : '8.4';"
	);
	expect($src)->toContain("version_compare(PHP_VERSION, \$rec_version, '<')");
	expect($src)->not->toContain("version_compare(PHP_VERSION,'7.4','<')");
});

test('utility.php and poller_maintenance.php fallbacks agree with CACTI_PHP_VERSION_MINIMUM', function () {
	// utility.php's fallback is a full x.y.z version string (matches the
	// define() literal); poller_maintenance.php's fallback is truncated to
	// x.y since version_compare() only needs major.minor for its check.
	expect(CACTI_PHP_VERSION_MINIMUM)->toBe('8.4.0');
	expect(substr(CACTI_PHP_VERSION_MINIMUM, 0, 3))->toBe('8.4');
});
