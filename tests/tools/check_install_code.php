<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
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

// set error handler
set_error_handler('error_handler');

// allow infinite execute
ini_set('max_execution_time', '0');

// define base path of Cacti
define('CACTI_PATH', str_replace('/tests/tools', '', dirname(__FILE__)));

global $config;
$config = array('base_path' => CACTI_PATH);

// pre-flush output buffer to avoid header warning
flush();

/*
 * TEST: include all installation upgrade files
 * This will find function name collisions
 */

print "TEST: Including all Install upgrade files" . PHP_EOL;
$install_upgrades_dir = CACTI_PATH . '/install/upgrades';
$dh = opendir($install_upgrades_dir);
if ($dh === false) {
	throw new Exception('Failed to open directory: ' . $install_upgrades_dir);
}
while (($file = readdir($dh)) !== false)
{
	// only include .php files, skip index.php
	if (substr($file, -4) == '.php' && $file != 'index.php') {
		print '  Include File: ' . $install_upgrades_dir . '/' . $file . PHP_EOL;
		require_once($install_upgrades_dir . '/' . $file);
		// confirm upgrade function exists
		$function_name = 'upgrade_to_' . substr($file, 0, -4);
		if (! function_exists($function_name)) {
			throw new Exception('Install upgrade function ' . $function_name . ' not found');
		}
	}
}
closedir($dh);

exit(0);

/*
 * Error handler function to cause exception on error and warnings
 */
function error_handler($err_number, $err_string, $err_file, $err_line) {
	$msg = $err_string . " in " . $err_file . " on line " . $err_line;

	if ($err_number == E_NOTICE || $err_number == E_WARNING) {
		throw new ErrorException($msg, $err_number);
	} else {
		echo $msg;
	}
}
