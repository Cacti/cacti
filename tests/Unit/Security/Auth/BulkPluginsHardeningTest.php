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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$root = dirname(__DIR__, 4);

test('plugin hook callback validation, realm loading, and raw INFO parsing enforce security invariants', function () {
	$res = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/BulkPluginsHardeningProbe.php');

	expect($res['cb']['internal_config'])->toBeTrue()
		->and($res['cb']['internal_nav'])->toBeTrue()
		->and($res['cb']['internal_phpinfo'])->toBeFalse()
		->and($res['cb']['internal_system'])->toBeFalse()
		->and($res['cb']['empty_fn'])->toBeFalse()
		->and($res['cb']['missing_fn'])->toBeFalse()
		->and($res['cb']['bad_plugin_path'])->toBeFalse()
		->and($res['cb']['php_internal'])->toBeFalse()
		->and($res['cb']['outside_user_fn'])->toBeFalse()
		->and($res['cb']['valid_plugin_fn'])->toBeTrue()
		->and($res['deps_traversal'])->toBeFalse()
		->and($res['deps_raw']['${PATH}'])->toBeTrue()
		->and($res['deps_raw']['thold'])->toBe('1.0')
		->and($res['caps'])->toBe('online_view:1')
		->and($res['compat']['compat'])->toBeTrue()
		->and($res['compat_bad']['compat'])->toBeFalse()
		->and($res['reg_plugins_php'])->toBeFalse()
		->and($res['reg_core_host'])->toBeFalse()
		->and($res['realm_files']['host.php'])->toBe(3)
		->and($res['realm_files']['plugins.php'])->toBe(101)
		->and(isset($res['realm_files']['disabled_page.php']))->toBeFalse()
		->and($res['realm_files']['enabled_a.php'])->toBe(111)
		->and($res['realm_files']['enabled_b.php'])->toBe(112)
		->and($res['realms'][110])->toBe('Disabled Plugin')
		->and($res['logs_second'])->toBe($res['logs_first'] * 2);
});

test('lib/plugins.php fails closed on hook file validation and uses INI_SCANNER_RAW everywhere', function () use ($root) {
	$source = file_get_contents($root . '/lib/plugins.php');

	expect($source)->toContain("if (\$plugin_include === false || !is_file(\$plugin_include))")
		->and($source)->toContain("if (!api_plugin_callback_is_allowed(\$hdata['name'], \$function))")
		->and($source)->toContain("if (!api_plugin_callback_is_allowed(\$plugin_name, \$plugin_func))")
		->and(substr_count($source, 'parse_ini_file($file, true, INI_SCANNER_RAW)'))->toBe(3)
		->and($source)->not->toContain('parse_ini_file($file, true)');
});
