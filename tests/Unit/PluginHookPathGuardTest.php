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

/*
 * Tests for the plugin hook path-traversal guard in lib/plugins.php.
 *
 * api_plugin_hook() and api_plugin_hook_function() build the include path from
 * both the plugin_hooks 'name' and 'file' columns:
 *   CACTI_PATH_PLUGINS . '/' . $name . '/' . $file
 * The original guard rejected '..' in 'file' only, so a poisoned 'name' column
 * containing '..' escaped CACTI_PATH_PLUGINS. The guard now rejects '..' in
 * either component at both call sites.
 */

function getPluginsSource(): string {
	$src = file_get_contents(__DIR__ . '/../../lib/plugins.php');
	expect($src)->not->toBeFalse('Failed to read lib/plugins.php');

	return $src;
}

// --- source guard parity: both 'name' and 'file' are checked at both sites ---

test('api_plugin_hook guard rejects dotdot in both plugin name and file', function () {
	$source  = getPluginsSource();
	$pattern = '/str_contains\(\$plugin_file, \'\.\.\'\) \|\| str_contains\(\$plugin_name, \'\.\.\'\)/';
	expect(preg_match($pattern, $source))->toBe(1,
		'api_plugin_hook must reject ".." in both $plugin_file and $plugin_name'
	);
});

test('api_plugin_hook_function guard rejects dotdot in both name and file', function () {
	$source  = getPluginsSource();
	$pattern = '/str_contains\(\$hdata\[\'file\'\], \'\.\.\'\) \|\| str_contains\(\$hdata\[\'name\'\], \'\.\.\'\)/';
	expect(preg_match($pattern, $source))->toBe(1,
		'api_plugin_hook_function must reject ".." in both $hdata[\'file\'] and $hdata[\'name\']'
	);
});

test('plugins.php guards the name component at both hook call sites', function () {
	$source = getPluginsSource();
	// Both api_plugin_hook and api_plugin_hook_function guard the name field.
	expect(preg_match_all('/str_contains\([^)]*name[^)]*\'\.\.\'\)/', $source))->toBeGreaterThanOrEqual(2,
		'the name component must be guarded at both hook iteration sites'
	);
});

// --- pure predicate replicating the inline guard, exercised directly ---

/*
 * Mirrors the inline guard: a hook row is rejected when either the plugin
 * directory name or the hook file contains a parent-directory reference.
 */
function pluginHookRowRejected(string $name, string $file): bool {
	return str_contains($file, '..') || str_contains($name, '..');
}

test('hook guard rejects a row whose name contains dotdot', function () {
	expect(pluginHookRowRejected('../../../etc', 'setup.php'))->toBeTrue();
});

test('hook guard rejects a row whose file contains dotdot', function () {
	expect(pluginHookRowRejected('thold', '../../../etc/passwd'))->toBeTrue();
});

test('hook guard accepts a benign name and file', function () {
	expect(pluginHookRowRejected('thold', 'setup.php'))->toBeFalse();
});

test('hook guard rejects dotdot embedded mid-path in the name', function () {
	// A name like 'good/../evil' still escapes once concatenated.
	expect(pluginHookRowRejected('good/../evil', 'setup.php'))->toBeTrue();
});
