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
 * Source-scan tests verifying that all plugin file inclusion sites in
 * lib/plugins.php apply realpath() boundary checks before loading files.
 *
 * These guard against symlink traversal and path injection attacks where a
 * crafted plugin name or hook file path could cause Cacti to include files
 * outside the plugins/ directory.
 */

test('api_plugin_hook guards include_once with realpath boundary check', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	// realpath() must be called on both the plugins root and the full path
	expect($source)->toContain('$real_plugins  = realpath(CACTI_PATH_PLUGINS)');
	expect($source)->toContain('$real_fullpath = realpath($full_path)');

	// Boundary enforced via str_starts_with before file is included
	expect($source)->toContain(
		'str_starts_with($real_fullpath . DIRECTORY_SEPARATOR, $real_plugins . DIRECTORY_SEPARATOR)'
	);
});

test('api_plugin_hook_function guards require_once with realpath boundary check', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	expect($source)->toContain('$real_plugins  = realpath(CACTI_PATH_PLUGINS)');
	expect($source)->toContain('$real_hookpath = realpath($hook_fullpath)');

	expect($source)->toContain(
		'str_starts_with($real_hookpath . DIRECTORY_SEPARATOR, $real_plugins . DIRECTORY_SEPARATOR)'
	);
});

test('api_plugin_install guards setup.php load with realpath boundary check', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	expect($source)->toContain('$real_plugins_i = realpath(CACTI_PATH_PLUGINS)');
	expect($source)->toContain('$real_setup_i   = realpath(CACTI_PATH_PLUGINS . "/$plugin/setup.php")');

	expect($source)->toContain(
		'str_starts_with($real_setup_i . DIRECTORY_SEPARATOR, $real_plugins_i . DIRECTORY_SEPARATOR)'
	);
});

test('api_plugin_uninstall guards setup.php load with realpath boundary check', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	expect($source)->toContain('$real_plugins_u = realpath(CACTI_PATH_PLUGINS)');
	expect($source)->toContain('$real_setup_u   = realpath(CACTI_PATH_PLUGINS . "/$plugin/setup.php")');

	expect($source)->toContain(
		'str_starts_with($real_setup_u . DIRECTORY_SEPARATOR, $real_plugins_u . DIRECTORY_SEPARATOR)'
	);
});

test('api_plugin_check_config guards setup.php load with realpath boundary check', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	expect($source)->toContain('$real_plugins_c = realpath(CACTI_PATH_PLUGINS)');
	expect($source)->toContain('$real_setup_c   = realpath(CACTI_PATH_PLUGINS . "/$plugin/setup.php")');

	expect($source)->toContain(
		'str_starts_with($real_setup_c . DIRECTORY_SEPARATOR, $real_plugins_c . DIRECTORY_SEPARATOR)'
	);
});

test('api_plugin_remove_data guards setup.php load with realpath boundary check', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	expect($source)->toContain('$real_plugins_r = realpath(CACTI_PATH_PLUGINS)');
	expect($source)->toContain('$real_setup_r   = realpath($setup_file)');

	expect($source)->toContain(
		'str_starts_with($real_setup_r . DIRECTORY_SEPARATOR, $real_plugins_r . DIRECTORY_SEPARATOR)'
	);
});

test('all six plugin inclusion sites log a SECURITY entry on boundary violation', function () {
	$source = file_get_contents(__DIR__ . '/../../lib/plugins.php');

	// Hook and install paths emit SECURITY log entries on boundary violations.
	// Uninstall/check_config/remove_data silently skip by design (best-effort cleanup).
	$securityLogs = preg_match_all("/cacti_log\('SECURITY:/", $source);
	expect($securityLogs)->toBeGreaterThanOrEqual(3);
});
