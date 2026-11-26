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

namespace CactiBulkPluginsHardeningTest;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 4);

$GLOBALS['bulk_plugin_realms_db'] = array();
$GLOBALS['bulk_plugin_logs']      = array();

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['bulk_plugin_logs'][] = $facility . ': ' . $message;
}

function cacti_plugin_path($plugin, $relative = '') {
	return \cacti_plugin_path($plugin, $relative);
}

function cacti_path_is_within($path, $base) {
	return \cacti_path_is_within($path, $base);
}

function db_fetch_assoc($sql) {
	return $GLOBALS['bulk_plugin_realms_db'];
}

if (!function_exists(__NAMESPACE__ . '\\api_plugin_callback_is_allowed')) {
	\cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_callback_is_allowed', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_load_realms', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_get_dependencies', __NAMESPACE__);
}

beforeEach(function () {
	$GLOBALS['bulk_plugin_realms_db'] = array();
	$GLOBALS['bulk_plugin_logs']      = array();
});

test('api_plugin_callback_is_allowed permits only allowlisted internal hooks and plugin-owned functions', function () {
	expect(api_plugin_callback_is_allowed('internal', 'plugin_config_arrays'))->toBeTrue()
		->and(api_plugin_callback_is_allowed('internal', 'plugin_draw_navigation_text'))->toBeTrue()
		->and(api_plugin_callback_is_allowed('internal', 'phpinfo'))->toBeFalse()
		->and(api_plugin_callback_is_allowed('internal', 'system'))->toBeFalse()
		->and(api_plugin_callback_is_allowed('demo', 'system'))->toBeFalse()
		->and(api_plugin_callback_is_allowed('../escape', 'strlen'))->toBeFalse();

	$tmpBase = sys_get_temp_dir() . '/cacti-plugin-cb-' . bin2hex(random_bytes(6));
	mkdir($tmpBase . '/plugins/sample', 0700, true);
	$pluginFile = $tmpBase . '/plugins/sample/setup.php';
	$funcName   = 'cacti_sample_plugin_hook_' . bin2hex(random_bytes(4));
	file_put_contents($pluginFile, "<?php\nfunction {$funcName}(\$x) { return \$x; }\n");
	require_once $pluginFile;

	$prevBase = isset($GLOBALS['config']['base_path']) ? $GLOBALS['config']['base_path'] : null;
	$GLOBALS['config']['base_path'] = $tmpBase;

	try {
		expect(api_plugin_callback_is_allowed('sample', $funcName))->toBeTrue()
			->and(api_plugin_callback_is_allowed('sample', 'cacti_sizeof'))->toBeFalse()
			->and(api_plugin_callback_is_allowed('sample', 'strlen'))->toBeFalse();
	} finally {
		if ($prevBase === null) {
			unset($GLOBALS['config']['base_path']);
		} else {
			$GLOBALS['config']['base_path'] = $prevBase;
		}
		@unlink($pluginFile);
		@rmdir($tmpBase . '/plugins/sample');
		@rmdir($tmpBase . '/plugins');
		@rmdir($tmpBase);
	}
});

test('api_plugin_load_realms ignores disabled plugins and blocks core or cross-plugin filename collisions', function () {
	$GLOBALS['user_auth_realms'] = array();
	$GLOBALS['user_auth_realm_filenames'] = array(
		'host.php'    => 3,
		'plugins.php' => 101,
	);
	$GLOBALS['bulk_plugin_realms_db'] = array(
		array('id' => 1, 'plugin' => 'internal', 'file' => 'plugins.php', 'display' => 'Plugin Management', 'plugin_status' => null),
		array('id' => 10, 'plugin' => 'disabled_plug', 'file' => 'disabled_page.php', 'display' => 'Disabled Plugin', 'plugin_status' => 0),
		array('id' => 11, 'plugin' => 'enabled_a', 'file' => 'enabled_a.php,host.php', 'display' => 'Enabled A', 'plugin_status' => 1),
		array('id' => 12, 'plugin' => 'enabled_b', 'file' => 'enabled_a.php,enabled_b.php', 'display' => 'Enabled B', 'plugin_status' => 1),
	);

	api_plugin_load_realms();

	expect($GLOBALS['user_auth_realm_filenames']['host.php'])->toBe(3)
		->and($GLOBALS['user_auth_realm_filenames']['plugins.php'])->toBe(101)
		->and(isset($GLOBALS['user_auth_realm_filenames']['disabled_page.php']))->toBeFalse()
		->and($GLOBALS['user_auth_realm_filenames']['enabled_a.php'])->toBe(111)
		->and($GLOBALS['user_auth_realm_filenames']['enabled_b.php'])->toBe(112)
		->and($GLOBALS['user_auth_realms'][110])->toBe('Disabled Plugin');
});

test('api_plugin_get_dependencies rejects traversal and parses INFO with INI_SCANNER_RAW', function () {
	$tmpBase = sys_get_temp_dir() . '/cacti-plugin-ini-' . bin2hex(random_bytes(6));
	mkdir($tmpBase . '/plugins/rawtest', 0700, true);
	$infoPath = $tmpBase . '/plugins/rawtest/INFO';
	file_put_contents($infoPath, "[info]\nrequires = \${PATH} thold:1.0\n");

	$prevBase = isset($GLOBALS['config']['base_path']) ? $GLOBALS['config']['base_path'] : null;
	$GLOBALS['config']['base_path'] = $tmpBase;

	try {
		expect(api_plugin_get_dependencies('../etc'))->toBeFalse();
		$deps = api_plugin_get_dependencies('rawtest');
		expect($deps)->toBeArray()
			->and(isset($deps['${PATH}']))->toBeTrue()
			->and($deps['thold'])->toBe('1.0');
	} finally {
		if ($prevBase === null) {
			unset($GLOBALS['config']['base_path']);
		} else {
			$GLOBALS['config']['base_path'] = $prevBase;
		}
		@unlink($infoPath);
		@rmdir($tmpBase . '/plugins/rawtest');
		@rmdir($tmpBase . '/plugins');
		@rmdir($tmpBase);
	}
});

test('lib/plugins.php fails closed on hook file validation and uses INI_SCANNER_RAW everywhere', function () use ($root) {
	$source = file_get_contents($root . '/lib/plugins.php');

	expect($source)->toContain("if (\$plugin_include === false || !is_file(\$plugin_include))")
		->and($source)->toContain("if (!api_plugin_callback_is_allowed(\$hdata['name'], \$function))")
		->and($source)->toContain("if (!api_plugin_callback_is_allowed(\$plugin_name, \$plugin_func))")
		->and(substr_count($source, 'parse_ini_file($file, true, INI_SCANNER_RAW)'))->toBe(3)
		->and($source)->not->toContain('parse_ini_file($file, true)');
});
