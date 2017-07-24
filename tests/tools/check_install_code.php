<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* This is a simple test script to make sure that there are no problems
 * with the installation files
 */

// report all the errors
error_reporting(E_ALL);

// setup global expection tracking array
$global_exception = array();

// allow infinite execute
ini_set('max_execution_time', '0');

// define base path of Cacti
define('CACTI_PATH', str_replace('/tests/tools', '', dirname(__FILE__)));

/*
 * TEST: include all installation upgrade files
 * This will find function name collisions
 */

print "TEST: Including all Install upgrade files" . PHP_EOL;
$install_upgrades_dir = CACTI_PATH . '/install/upgrades/';
$dh = opendir($install_upgrades_dir);
if ($dh === false) {
	throw new Exception('Failed to open directory: ' . $install_upgrades_dir);
}
while (($file = readdir($dh)) !== false)
{
	// only include .php files
	if (substr($file, -4) == '.php') {
		print '  Include File: ' . $install_upgrades_dir . '/' . $file . PHP_EOL;
		require_once($install_upgrades_dir . '/' . $file);
	}
}
closedir($dh);

exit(0);
