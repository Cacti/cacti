#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
   die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* We are not talking to the browser */
$no_http_headers = true;

/* allow the upgrade script to run for as long as it needs to */
ini_set('max_execution_time', '0');

include(dirname(__FILE__) . '/../include/global.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/lib/utility.php');
include_once($config['base_path'] . '/install/functions.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug, $cli_upgrade, $session;

$debug       = true;
$cli_upgrade = true;
$session     = array();

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = TRUE;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				echo "ERROR: Invalid Parameter " . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

/* UPDATE THIS FOR NEW VERSIONS!! */
$includes = array(
	'0.8'    => '',
	'0.8.1'  => '0_8_to_0_8_1.php',
	'0.8.2'  => '0_8_1_to_0_8_2.php',
	'0.8.2a' => '0_8_2_to_0_8_2a.php',
	'0.8.3'  => '0_8_2a_to_0_8_3.php',
	'0.8.3a' => '',
	'0.8.4'  => '0_8_3_to_0_8_4.php',
	'0.8.5'  => '0_8_4_to_0_8_5.php',
	'0.8.5a' => '',
	'0.8.6'  => '0_8_5a_to_0_8_6.php',
	'0.8.6a' => '0_8_6_to_0_8_6a.php',
	'0.8.6b' => '',
	'0.8.6c' => '',
	'0.8.6d' => '0_8_6c_to_0_8_6d.php',
	'0.8.6e' => '0_8_6d_to_0_8_6e.php',
	'0.8.6f' => '',
	'0.8.6g' => '0_8_6f_to_0_8_6g.php',
	'0.8.6h' => '0_8_6g_to_0_8_6h.php',
	'0.8.6i' => '0_8_6h_to_0_8_6i.php',
	'0.8.6j' => '',
	'0.8.6k' => '',
	'0.8.7'  => '0_8_6j_to_0_8_7.php',
	'0.8.7a' => '0_8_7_to_0_8_7a.php',
	'0.8.7b' => '0_8_7a_to_0_8_7b.php',
	'0.8.7c' => '0_8_7b_to_0_8_7c.php',
	'0.8.7d' => '0_8_7c_to_0_8_7d.php',
	'0.8.7e' => '0_8_7d_to_0_8_7e.php',
	'0.8.7f' => '0_8_7e_to_0_8_7f.php',
	'0.8.7g' => '0_8_7f_to_0_8_7g.php',
	'0.8.7h' => '0_8_7g_to_0_8_7h.php',
	'0.8.7i' => '0_8_7h_to_0_8_7i.php',
	'0.8.8'  => '0_8_7i_to_0_8_8.php',
	'0.8.8a' => '0_8_8_to_0_8_8a.php',
	'0.8.8b' => '0_8_8a_to_0_8_8b.php',
	'0.8.8c' => '0_8_8b_to_0_8_8c.php',
	'0.8.8d' => '0_8_8c_to_0_8_8d.php',
	'0.8.8e' => '0_8_8d_to_0_8_8e.php',
	'0.8.8f' => '0_8_8e_to_0_8_8f.php',
	'0.8.8g' => '0_8_8f_to_0_8_8g.php',
	'0.8.8h' => '0_8_8g_to_0_8_8h.php',
	'1.0.0'  => '0_8_8h_to_1_0_0.php',
);

$old_cacti_version = db_fetch_cell('SELECT cacti FROM version');

/* try to find current (old) version in the array */
$old_version_index = (isset($includes[$old_cacti_version]) ? $old_cacti_version : '');

/* do a version check */
if ($old_cacti_version == $config['cacti_version']) {
	echo "Your Cacti is already up to date.\n";
	exit;
} else if ($old_cacti_version < 0.7) {
	echo 'You are attempting to install cacti ' . $config['cacti_version'] . " onto a 0.6.x database.\nTo continue, you must create a new database, import 'cacti.sql' into it,\nand 	update 'include/config.php' to point to the new database.\n";
	exit;
} else if (empty($old_cacti_version)) {
	echo "You have created a new database, but have not yet imported the 'cacti.sql' file.\n";
	exit;
} else if ($old_version_index == '') {
	echo "Invalid Cacti version $old_cacti_version, cannot upgrade to " . $config['cacti_version'] . "\n";
	exit;
}

/* loop from the old version to the current, performing updates for each version in between */
$start = FALSE;
foreach ($includes as $v => $file) {
	if ($file != '' && $start) {
		echo 'Upgrading to ' . $v . "\n";
		include($config['base_path'] . '/install/' . $file);
		$func = 'upgrade_to_' . str_replace('.', '_', $v);
		if (function_exists($func)) {
			$func();
		}else{
			echo "ERROR: Function does not exist\n";
		}
		db_install_errors($v);
	}

	if ($v == $config['cacti_version']) {
		break;
	}

	if ($old_cacti_version == $v) {
		$start = TRUE;
	}
}

db_execute("UPDATE version SET cacti = '" . $config['cacti_version'] . "'");

function db_install_errors($cacti_version) {
	global $session;

	if (sizeof($session)) {	
		foreach ($session as $sc) {
			if (isset($sc[$cacti_version])) {
				foreach ($sc[$cacti_version] as $value => $sql) {
					if ($value == 0) {
						echo "    DB Error: $sql\n";
					}
				}
			}
		}
	}
}

/*  display_version - displays version information */
function display_version() {
    $version = db_fetch_cell('SELECT cacti FROM version');
    echo "Cacti Database Upgrade Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*  display_help - displays the usage of the function */
function display_help () {
    display_version();

    echo "\nusage: upgrade_database.php [-d|--debug]\n\n";
	echo "A command line version of the Cacti database upgrade tool.  You must execute\n";
	echo "this command as a super user, or someone who can write a PHP session file.\n";
	echo "Typically, this user account will be apache, www-run, or root.\n\n";
	echo "Optional:\n";
    echo "-d | --debug     - Display verbose output during execution\n\n";
}
