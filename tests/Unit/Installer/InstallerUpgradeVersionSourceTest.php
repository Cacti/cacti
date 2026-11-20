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

test('upgradeDatabase compares the database version, not the CLI banner', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/installer.php');
	$start  = strpos($source, 'private function upgradeDatabase()');
	$body   = substr($source, $start, strpos($source, 'private function checkDatabaseUpgrade', $start) - $start);

	/*
	 * get_cacti_cli_version() returns display text such as '1.2.32 (DB: 1.2.30)'.
	 * Feeding it to cacti_version_compare() reaches hexdec() with characters it
	 * has to discard, which PHP reports as a deprecation on every upgrade.
	 */
	expect($body)->not->toContain('get_cacti_cli_version()')
		->and($body)->toContain('$orig_cacti_version = get_cacti_version();');
});
