#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* We are not talking to the browser */
$no_http_headers = true;

include(dirname(__FILE__).'/../include/global.php');
include_once($config['base_path'].'/lib/api_automation_tools.php');
include_once($config['base_path'].'/lib/utility.php');
include_once($config['base_path'].'/lib/api_data_source.php');
include_once($config['base_path'].'/lib/api_graph.php');
include_once($config['base_path'].'/lib/snmp.php');
include_once($config['base_path'].'/lib/data_query.php');
include_once($config['base_path'].'/lib/api_device.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	/* setup defaults */
	$description   = '';
	$ip            = '';
	$host_id       = '';

	$quietMode     = false;
	$confirm       = false;
	$quiet         = false;

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
			display_version();
			$debug = true;

			break;
		case '--confirm':
			$confirm=true;

			break;
		case '--description':
			$description = trim($value);

			break;
		case '--ip':
			$ip = trim($value);

			break;
		case '--version':
			display_version();
			exit;
			break;
		case '--version':
		case '-V':
		case '-v':
			display_version();
			exit;
		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit;
		case '--quiet':
			$quietMode = true;

			break;
		default:
			echo "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
		}
	}

	/* process the various lists into validation arrays */
	$hosts		= getHostsByDescription();
	$addresses	= getAddresses();
	$ids_host	= array();
	$ids_ip		= array();

	/* process host description */
	if ($description > '') {
		if ($debug) {
			echo "Searching hosts by description...\n";
		}

		$ids_host = preg_array_key_match("/$description/", $hosts);
		if (sizeof($ids_host) == 0) {
			echo "ERROR: Unable to find host in the database matching desciption ($description)\n";
			exit(1);
		}
	}

	if ($ip > '') {
		if ($debug) {
			echo "Searching hosts by IP...\n";
		}

		$ids_ip = preg_array_key_match("/$ip/", $addresses);
		if (sizeof($ids_ip) == 0) {
			echo "ERROR: Unable to find host in the database matching IP ($ip)\n";
			exit(1);
		}
	}

	if (sizeof($ids_host) == 0 && sizeof($ids_ip) == 0) {
		echo "ERROR: No matches found, was IP or Description set properly?\n";
		exit(1);
	}

	$ids = array_merge($ids_host, $ids_ip);
	$ids = array_unique($ids, SORT_NUMERIC);

	$ids_sql = implode(',',$ids);
	if ($debug) {
		echo "Finding devices with ids $ids_sql\n\n";
	}

	$hosts = db_fetch_assoc("SELECT id, hostname, description FROM host WHERE id IN ($ids_sql) ORDER by description");
	$ids_found = array();
	if (!$quiet) {
		printf("%8.s | %30.s | %30.s\n",'id','host','description');
		foreach ($hosts as $host) {
			printf("%8.d | %30.s | %30.s\n",$host['id'],$host['hostname'],$host['description']);
			$ids_found[] = $host['id'];
		}
		echo "\n";
	}

	if ($confirm) {
		$ids_confirm = implode(', ',$ids_found);
		if (!$quiet) {
			echo "Removing devices with ids: $ids_confirm\n";
		}
		$host_id = api_device_remove_multi($ids);

		if (is_error_message()) {
			echo "ERROR: Failed to remove devices\n";
			exit(1);
		} else {
			echo "Success - removed device-ids: $ids_confirm\n";
			exit(0);
		}
	} else {
		echo "Please use --confirm to remove these devices\n";
	}
} else {
	display_help();
	exit(0);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Remove Device Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	echo "\nusage: remove_device.php --description=[description] --ip=[IP]\n";
	echo "    [--confirm] [--quiet]\n\n";
	echo "Required:\n";
	echo "    --description  the name that will be displayed by Cacti in the graphs\n";
	echo "    --ip           self explanatory (can also be a FQDN)\n";
	echo "   (either one or both fields can be used and may be regex)\n\n";
	echo "Optional:\n";
	echo "    -confirm       confirms that you wish to remove matches\n\n";
	echo "List Options:\n";
	echo "    --quiet - batch mode value return\n\n";
}

function preg_array_key_match($needle, $haystack) {
	global $debug;
	$matches = array ();

	if (isset($haystack)) {
		if (!is_array($haystack)) {
			$haystack = array($hackstack);
		}
	} else {
		$haystack = array();
	}

	if ($debug) {
		echo "Attempting to match against '$needle' against ".sizeof($haystack)." entries\n";
	}

	foreach ($haystack as $str => $value) {
		if ($debug) {
			echo " - Key $str => Value $value\n";
		}

		if (preg_match ($needle, $str, $m)) {
			if ($debug) {
				echo "   + $str: $value\n";
			}
			$matches[] = $value;
		}
	}

	return $matches;
}
