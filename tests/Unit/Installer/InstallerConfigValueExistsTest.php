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

test('a setting that did not exist is looked up again instead of being cached as missing', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/functions.php');
	$start  = strpos($source, 'function config_value_exists(');
	$body   = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	/*
	 * isset() is true for a cached false, so the first miss was permanent. The
	 * command line installer reads install_file_paths() before and after it
	 * stores the paths it was given, and the stale miss made it report the
	 * defaults it had just replaced as missing binaries.
	 */
	expect($body)->toContain('if (empty($exists_config_value[$config_name])) {')
		->and($body)->not->toContain('if (!isset($exists_config_value[$config_name])) {');
});
