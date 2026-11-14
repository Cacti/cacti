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

test('the template package list is read once per request', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/install/functions.php');
	$start  = strpos($source, 'function install_setup_get_templates()');
	$body   = substr($source, $start, strpos($source, "\n}\n", $start) - $start);

	/*
	 * Every call opened and parsed all of the compressed packages, and the
	 * installer asks for them from getTemplates(), setTemplates() and the
	 * template step on each request.
	 */
	expect($body)->toContain('static $cached = null;')
		->and($body)->toContain('if ($cached !== null) {')
		->and($body)->toContain('$cached = $info;');
});
