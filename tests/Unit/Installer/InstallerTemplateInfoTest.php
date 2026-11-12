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

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 3) . '/install/functions.php';

test('the template package list is read once per request', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/install/functions.php');
	$start  = strpos($source, 'function install_setup_get_templates()');
	$body   = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	/*
	 * Every call opened and parsed all of the compressed packages, and the
	 * installer asks for them from getTemplates(), setTemplates() and the
	 * template step on each request.
	 */
	expect($body)->toContain('static $info = null;')
		->and($body)->toContain('if ($info !== null) {');
});

test('repeated calls return the same list', function () {
	$first = install_setup_get_templates();

	expect($first)->not->toBeEmpty()
		->and(install_setup_get_templates())->toBe($first);
});
