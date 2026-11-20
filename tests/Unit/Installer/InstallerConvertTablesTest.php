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

test('convertDatabase clears the setting of every table it converted', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/installer.php');
	$start  = strpos($source, 'private function convertDatabase()');
	$body   = substr($source, $start, strpos($source, 'private function upgradeDatabase', $start) - $start);

	/*
	 * The query selected only the value, and the loop used the row number as the
	 * setting name: converted tables kept their install_table_ setting and every
	 * retry converted them all again, while settings called 0, 1, 2 were written.
	 */
	expect($body)->toContain('SELECT name, value FROM settings')
		->and($body)->toContain("set_install_config_option(\$table['name'], '');")
		->and($body)->not->toContain('foreach ($tables as $key => $table)');
});
