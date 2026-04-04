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
 * Tests for GHSA-j696-m433-87qq: server-executable file types must be
 * rejected during plugin archive extraction so a crafted archive cannot
 * plant a webshell in the web-accessible plugin directory.
 *
 * The guard uses pathinfo(PATHINFO_EXTENSION) on the lowercased path, so
 * only the final extension segment is evaluated.
 */

// ---------------------------------------------------------------------------
// Helper: mirrors the production check in api_plugin_archive_restore()
// ---------------------------------------------------------------------------

function is_plugin_file_blocked(string $filename): bool {
	$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

	return in_array($ext, ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar'], true);
}

// ---------------------------------------------------------------------------
// Blocked extensions
// ---------------------------------------------------------------------------

test('plain .php file is blocked', function () {
	expect(is_plugin_file_blocked('shell.php'))->toBeTrue();
});

test('.phtml file is blocked', function () {
	expect(is_plugin_file_blocked('template.phtml'))->toBeTrue();
});

test('.phar file is blocked', function () {
	expect(is_plugin_file_blocked('package.phar'))->toBeTrue();
});

test('.php7 file is blocked', function () {
	expect(is_plugin_file_blocked('legacy.php7'))->toBeTrue();
});

test('.php3 file is blocked', function () {
	expect(is_plugin_file_blocked('old.php3'))->toBeTrue();
});

test('.php4 file is blocked', function () {
	expect(is_plugin_file_blocked('older.php4'))->toBeTrue();
});

test('.php5 file is blocked', function () {
	expect(is_plugin_file_blocked('compat.php5'))->toBeTrue();
});

test('.php8 file is blocked', function () {
	expect(is_plugin_file_blocked('modern.php8'))->toBeTrue();
});

test('uppercase .PHP extension is blocked (case-insensitive)', function () {
	/* strtolower() normalises before the in_array check */
	expect(is_plugin_file_blocked('SHELL.PHP'))->toBeTrue();
});

test('mixed-case .Php extension is blocked', function () {
	expect(is_plugin_file_blocked('shell.Php'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Allowed extensions
// ---------------------------------------------------------------------------

test('.xml file is allowed', function () {
	expect(is_plugin_file_blocked('data_query.xml'))->toBeFalse();
});

test('.json file is allowed', function () {
	expect(is_plugin_file_blocked('config.json'))->toBeFalse();
});

test('.js file is allowed', function () {
	expect(is_plugin_file_blocked('app.js'))->toBeFalse();
});

test('.css file is allowed', function () {
	expect(is_plugin_file_blocked('style.css'))->toBeFalse();
});

test('.html file is allowed', function () {
	expect(is_plugin_file_blocked('index.html'))->toBeFalse();
});

test('.png file is allowed', function () {
	expect(is_plugin_file_blocked('logo.png'))->toBeFalse();
});

test('.sql file is allowed', function () {
	expect(is_plugin_file_blocked('install.sql'))->toBeFalse();
});

test('.txt file is allowed', function () {
	expect(is_plugin_file_blocked('README.txt'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Double-extension behaviour
// pathinfo() returns only the final segment, so .php.bak → 'bak' (allowed)
// while .jpg.php → 'php' (blocked).
// ---------------------------------------------------------------------------

test('.php.bak double extension is allowed (final ext is bak)', function () {
	/* Backup file: extension is 'bak', not 'php' */
	expect(is_plugin_file_blocked('shell.php.bak'))->toBeFalse();
});

test('.jpg.php double extension is blocked (final ext is php)', function () {
	/* Classic disguise: extension is 'php' */
	expect(is_plugin_file_blocked('image.jpg.php'))->toBeTrue();
});

test('.phtml.bak double extension is allowed (final ext is bak)', function () {
	expect(is_plugin_file_blocked('template.phtml.bak'))->toBeFalse();
});

test('.tar.php double extension is blocked (final ext is php)', function () {
	expect(is_plugin_file_blocked('archive.tar.php'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Source-scan: confirm the blocking logic lives in lib/plugins.php
// ---------------------------------------------------------------------------

test('lib/plugins.php contains the executable-extension in_array guard', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	/* Both the type != archive and type == archive branches must contain the guard */
	$matches = preg_match_all(
		"/in_array\s*\(\s*\\\$ext\s*,\s*\['php'/",
		$source
	);

	expect($matches)->toBeGreaterThanOrEqual(2,
		'Expected at least two in_array extension guards (one per extraction branch)'
	);
});

test('lib/plugins.php guard covers phtml and phar', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	expect($source)->toContain("'phtml'");
	expect($source)->toContain("'phar'");
});

test('lib/plugins.php uses pathinfo PATHINFO_EXTENSION for extension extraction', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	expect($source)->toContain('pathinfo($tfile, PATHINFO_EXTENSION)');
});
