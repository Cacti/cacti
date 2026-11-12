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
require_once dirname(__DIR__, 3) . '/include/global_constants.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';
require_once dirname(__DIR__, 3) . '/install/functions.php';
require_once dirname(__DIR__, 3) . '/lib/installer.php';

test('a setting that did not exist is looked up again instead of being cached as missing', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/functions.php');
	$start  = strpos($source, 'function config_value_exists(');
	$body   = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	/*
	 * isset() is true for a cached false, so the first miss was permanent. The
	 * command line installer reads the default paths before and after it stores
	 * the paths it was given, and the stale miss made it report the defaults it
	 * had just replaced as missing binaries.
	 */
	expect($body)->toContain('if (empty($config_values[$config_name])) {')
		->and($body)->not->toContain('if (!isset($config_values[$config_name])) {');
});
