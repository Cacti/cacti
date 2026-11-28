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
 * Every action color.php dispatches calls a function that exists. The
 * dispatcher runs at include time behind the login check, so its cases are
 * read from the source and each called name is looked up among the
 * functions color.php and the libraries it loads define.
 */

function colorPageDispatchCalls(): array {
	$source = file_get_contents(dirname(__DIR__, 4) . '/color.php');
	$start  = strpos($source, "switch (get_request_var('action')) {");
	$end    = strpos($source, "\n}\n", $start);

	preg_match_all('/^\t\t([a-z_]+)\(/m', substr($source, $start, $end - $start), $calls);

	return array_unique($calls[1]);
}

function colorPageDefinedFunctions(): array {
	$root    = dirname(__DIR__, 4);
	$defined = array();

	foreach (array_merge(array($root . '/color.php'), glob($root . '/lib/*.php'), glob($root . '/include/*.php')) as $file) {
		preg_match_all('/^function ([a-z_0-9]+)\(/mi', file_get_contents($file), $functions);
		$defined = array_merge($defined, $functions[1]);
	}

	return $defined;
}

test('each color.php action calls a defined function', function () {
	$calls = colorPageDispatchCalls();

	expect($calls)->toContain('form_save', 'form_actions', 'color_edit', 'color_import', 'color_export', 'color')
		->and(array_values(array_diff($calls, colorPageDefinedFunctions())))->toBe(array());
});
