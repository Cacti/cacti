#!/usr/bin/php -q
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

// do NOT run this script through a web browser
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
   die('<br><strong>This script is only meant to run at the command line.</strong>');
}

// We are not talking to the browser
$no_http_headers = true;

// allow the upgrade script to run for as long as it needs to
ini_set('max_execution_time', '0');

include(dirname(__FILE__) . '/../include/global.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/lib/utility.php');
include_once($config['base_path'] . '/install/functions.php');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug, $cli_upgrade, $session;

$debug = FALSE;
$cli_upgrade = TRUE;
$session = array();
$force_version = NULL;

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--force_version':
				$force_version = $value;
				break;
			case '-d':
			case '--debug':
				$debug = TRUE;
				break;
			case '--database_version':
				display_database_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print "ERROR: Invalid Parameter " . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit;
		}
	}
}

// we need to rerun the upgrade, force the current version
if (empty($force_version)) {
	$old_cacti_version = db_fetch_cell('SELECT cacti FROM version');
} else {
	$old_cacti_version = $force_version;
}
$old_cacti_version = trim($old_cacti_version);

// do a version check
if ($old_cacti_version == CACTI_VERSION) {
	print "Your Cacti is already up to date." . PHP_EOL;
	exit;
} 
if (version_compare($old_cacti_version, '0.7', '<')) {
	print 'You are attempting to install cacti ' . CACTI_VERSION . ' onto a 0.6.x database.' . PHP_EOL . "To continue, you must create a new database, import 'cacti.sql' into it," . PHP_EOL . "and\tupdate 'include/config.php' to point to the new database." . PHP_EOL;
	exit;
} 
if (empty($old_cacti_version)) {
	print "You have created a new database, but have not yet imported the 'cacti.sql' file." . PHP_EOL;
	exit;
} 
if ($old_cacti_version == 'new_install') {
	print "You can not upgrade a new installation" . PHP_EOL;
	exit;
}
if (! array_key_exists($old_cacti_version, $cacti_version_codes)) {
	print "Invalid Cacti version $old_cacti_version, cannot upgrade to " . CACTI_VERSION . PHP_EOL;
	exit;
}

// loop through versions from old version to the current, performing updates for each version in the chain
foreach ($cacti_version_codes as $cacti_upgrade_version => $hash_code)  {

	// skip versions old than the database version
	if (version_compare($old_cacti_version, $cacti_upgrade_version, '>=')) {
		continue;
	}

	// construct version upgrade include path
	$upgrade_file = dirname(__FILE__) . '/../install/upgrades/' . str_replace('.', '_', $cacti_upgrade_version) . '.php';
	$upgrade_function = 'upgrade_to_' . str_replace('.', '_', $cacti_upgrade_version);

	// check for upgrade version file, then include, check for function and execute
	print 'Upgrading to ' . $cacti_upgrade_version . ' ';
	if (file_exists($upgrade_file)) {
		include($upgrade_file);
		print PHP_EOL;
		if (function_exists($upgrade_function)) {
			call_user_func($upgrade_function);
			print_upgrade_results($cacti_upgrade_version);
		} else {
			print 'Error: upgrade function (' . $upgrade_function . ') not found' . PHP_EOL;;
		}
	} else {
		print "no actions" . PHP_EOL;
	}

	if (CACTI_VERSION == $cacti_upgrade_version) {
		break;
	}
}

db_execute("UPDATE version SET cacti = '" . CACTI_VERSION . "'");

/*
 * Print upgrade results for requested version
 */
function print_upgrade_results($cacti_version) {
	global $session, $debug;

	// if sessions are working for cli, use it
	if (isset($_SESSION)) {
		$session = $_SESSION;
	}

	if (array_key_exists($cacti_version, $session['cacti_db_install_cache'])) {
		foreach ($session['cacti_db_install_cache'][$cacti_version] as $action) {
			if ($action['status'] == 0) {
				print "    DB Error: " . $action['sql'] . PHP_EOL;
			} elseif ($debug) {
				print "    DB Success: " . $action['sql'] . PHP_EOL;
			}
		}
	}
}

/* 
 * Display database version information
 */
function display_database_version() {
	$version = db_fetch_cell('SELECT cacti FROM version');
	print 'Database Version: ' . trim($version) . PHP_EOL;
}

/* 
 * Display cli help
 */
function display_help () {
	print 'Cacti Database Upgrade Utility, Version ' . CACTI_VERSION . ', ' . COPYRIGHT_YEARS . PHP_EOL;
	print PHP_EOL;
	print "usage: upgrade_database.php [--debug] [--force_version=VERSION] [--database_version]" . PHP_EOL;
	print PHP_EOL;
	print "--force_version    - Force the starting version, say " . CACTI_VERSION . PHP_EOL;
	print "--database_version - Display database version and exit" . PHP_EOL;
	print "--debug            - Display verbose output during execution" . PHP_EOL;
	print PHP_EOL;
	print PHP_EOL;
	print "Command line Cacti database upgrade tool.  You must execute" . PHP_EOL;
	print "this command as a super user, or someone who can write a PHP session file." . PHP_EOL;
	print "Typically, this user account will be apache, www-run, or root." . PHP_EOL;
	print PHP_EOL;
	print "If you are running a beta or alpha version of Cacti and need to rerun" . PHP_EOL;
	print "the upgrade script, simply set the force_version to the previous version." . PHP_EOL;
	print PHP_EOL;
}
