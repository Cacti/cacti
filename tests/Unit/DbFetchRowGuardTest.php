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

$basePath = dirname(__DIR__, 2);

$files = array(
	'auth_login.php',
	'graph_view.php',
	'graph.php',
	'sites.php',
	'automation_devices.php',
	'automation_networks.php',
	'gprint_presets.php',
	'color_templates.php',
	'color_templates_items.php',
	'graph_templates.php',
	'data_input.php',
	'vdef.php',
	'aggregate_templates.php',
	'rrdcleaner.php',
	'user_admin.php',
	'poller_automation.php',
);

foreach ($files as $file) {
	$path = $basePath . '/' . $file;

	test("$file guards db_fetch_row results before dereference", function () use ($path, $file) {
		if (!file_exists($path)) {
			$this->markTestSkipped("$file not found");
		}

		$contents = file_get_contents($path);

		// Find each db_fetch_row assignment
		preg_match_all('/(\$\w+)\s*=\s*db_fetch_row/', $contents, $matches, PREG_OFFSET_CAPTURE);

		foreach ($matches[1] as $match) {
			$var = $match[0];
			$pos = $match[1];

			// Look at next 500 chars
			$chunk = substr($contents, $pos, 500);

			// Find first dereference
			$derefPattern = '/' . preg_quote($var, '/') . '\[\s*[\'\"]/';
			if (!preg_match($derefPattern, $chunk, $dm, PREG_OFFSET_CAPTURE)) {
				continue;
			}

			$beforeDeref = substr($chunk, 0, $dm[0][1]);

			// Must have a guard
			$guardPattern = '/cacti_sizeof|isset|empty|!\s*' . preg_quote($var, '/') . '\b/';
			expect(preg_match($guardPattern, $beforeDeref))
				->toBeGreaterThan(0, "$file: $var dereferenced without cacti_sizeof/isset/empty guard");
		}
	});
}
