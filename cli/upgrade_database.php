#!/usr/bin/env php
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

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');
require_once(CACTI_PATH_INSTALL . '/functions.php');

ini_set('max_execution_time', '0');

/* make sure installer knows we are installing */
define('IN_CACTI_INSTALL', 1);

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug, $cli_upgrade, $database_upgrade_status, $cacti_upgrade_version;

$debug       = false;
$cli_upgrade = true;
$local       = false;
$session     = array();
$forcever    = '';

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--local':
				$local = true;

				break;
			case '--forcever':
				$forcever = $value;

				break;
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();

				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();

				exit(0);

			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
		}
	}
}

if (!$local && $config['poller_id'] > 1) {
	db_switch_remote_to_main();

	print 'NOTE: Repairing Tables for Main Database' . PHP_EOL;
} else {
	print 'NOTE: Repairing Tables for Local Database' . PHP_EOL;
}

/* we need to rerun the upgrade, force the current version */
if ($forcever == '') {
	$old_cacti_version = format_cacti_version(get_cacti_db_version(),3);
} else {
	$old_cacti_version = format_cacti_version($forcever, 3);
}

/* try to find current (old) version in the array */
$old_version_index = (array_key_exists($old_cacti_version, $cacti_version_codes) ? $old_cacti_version : '');

/* do a version check */
if (cacti_version_compare($old_cacti_version,CACTI_VERSION,'=')) {
	exit_version_error($old_cacti_version, 'Your Cacti is already up to date');
} elseif (cacti_version_compare($old_cacti_version,'0.7','<')) {
	exit_error('You are attempting to install cacti ' . CACTI_VERSION . ' onto a 0.6.x database.' . PHP_EOL . "To continue, you must create a new database, import 'cacti.sql' into it," . PHP_EOL . "and\tupdate 'include/config.php' to point to the new database.");
} elseif (empty($old_cacti_version)) {
	exit_error('You have created a new database, but have not yet imported the \'cacti.sql\' file.');

	exit;
} elseif ($old_version_index == '') {
	exit_version_error($old_cacti_version, 'Unable to identify version, cannot upgrade.');
}

print 'Upgrading from v' . get_cacti_version_text(false, $old_cacti_version) . PHP_EOL;

$prev_cacti_version = $old_cacti_version;
$orig_cacti_version = get_cacti_db_version();

// loop through versions from old version to the current, performing updates for each version in the chain
foreach ($cacti_version_codes as $cacti_upgrade_version => $hash_code) {
	// skip versions old than the database version
	if (cacti_version_compare($old_cacti_version, $cacti_upgrade_version, '>=')) {
		continue;
	}

	// construct version upgrade include path
	$upgrade_file     = CACTI_PATH_INSTALL . '/upgrades/' . str_replace('.', '_', $cacti_upgrade_version) . '.php';
	$upgrade_function = 'upgrade_to_' . str_replace('.', '_', $cacti_upgrade_version);

	// check for upgrade version file, then include, check for function and execute
	if (file_exists($upgrade_file)) {
		print 'Performing Database Upgrade' . PHP_EOL;
		print '  - from v' . $prev_cacti_version .' (DB ' . $orig_cacti_version . ')' . PHP_EOL;
		print '      to v' . $cacti_upgrade_version . PHP_EOL;
		include($upgrade_file);

		if (function_exists($upgrade_function)) {
			call_user_func($upgrade_function);
			$status = db_install_errors($cacti_upgrade_version);
		} else {
			$status = DB_STATUS_ERROR;
			print 'Error: upgrade function (' . $upgrade_function . ') not found' . PHP_EOL;
		}

		if ($status == DB_STATUS_ERROR) {
			break;
		}

		if (cacti_version_compare($orig_cacti_version, $cacti_upgrade_version, '<')) {
			db_execute("UPDATE version SET cacti = '" . $cacti_upgrade_version . "'");
			$orig_cacti_version = $cacti_upgrade_version;
		}
		$prev_cacti_version = $cacti_upgrade_version;
	}

	db_execute("UPDATE version SET cacti = '" . $cacti_upgrade_version . "'");

	if (cacti_version_compare(CACTI_VERSION, $cacti_upgrade_version, '=')) {
		db_execute("UPDATE version SET cacti = '" . CACTI_VERSION_FULL . "'");

		break;
	}
}

function exit_error($text) {
	print "ERROR: $text" . PHP_EOL;

	exit;
}

function exit_version_error($old_cacti_version, $text) {
	exit_error($text . PHP_EOL . '  - from: v' . get_cacti_version_text(false, $old_cacti_version) . PHP_EOL . '      to: v' . CACTI_VERSION_BRIEF_FULL);
}

function db_install_errors($cacti_version) {
	global $database_upgrade_status, $debug, $database_statuses;

	$error_status = DB_STATUS_SKIPPED;

	if (!isset($database_upgrade_status)) {
		$database_upgrade_status = array();
	}

	if (cacti_sizeof($database_upgrade_status)) {
		if (isset($database_upgrade_status[$cacti_version])) {
			foreach ($database_upgrade_status[$cacti_version] as $cache_item) {
				$status = $cache_item['status'];
				$error  = empty($cache_item['error']) ? '<no error>' : $cache_item['error'];
				$sql    = $cache_item['sql'];

				if ($error_status > $status) {
					$error_status = $status;
				}

				if ($debug || $status < DB_STATUS_SUCCESS) {
					$db_status = '[Unknown]';

					if (isset($database_statuses[$status])) {
						$db_status = $database_statuses[$status];
					}

					$sep1 = '################################';
					$sep2 = '+------------------------------+';
					printf('%s%s%s%-10s   -   %s%s%s%s%s%s%s%s', PHP_EOL, $sep1, PHP_EOL, $db_status, $error, PHP_EOL, $sep2, PHP_EOL, clean_up_lines($sql), PHP_EOL, $sep1, PHP_EOL);
				}
			}
		}
	}

	return $error_status;
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Database Upgrade Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*  display_help - displays the usage of the function */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: upgrade_database.php [--debug] [--forcever=VERSION]' . PHP_EOL . PHP_EOL;
	print 'A command line version of the Cacti database upgrade tool.  You must execute' . PHP_EOL;
	print 'this command as a super user, or someone who can write a PHP session file.' . PHP_EOL;
	print 'Typically, this user account will be apache, www-run, or root.' . PHP_EOL . PHP_EOL;
	print 'If you are running a beta or alpha version of Cacti and need to rerun' . PHP_EOL;
	print 'the upgrade script, simply set the forcever to the previous release.' . PHP_EOL . PHP_EOL;
	print '--forcever - Force the starting version, say ' . CACTI_VERSION . PHP_EOL;
	print '--local    - Perform the action on the Remote Data Collector if run from there' . PHP_EOL;
	print '--debug    - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}
