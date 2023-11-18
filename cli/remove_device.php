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
require_once($config['base_path'] . '/lib/api_automation_tools.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/utility.php');

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {

	/* setup defaults */
	$description   = '';
	$ip            = '';
	$host_id       = '';

	$quietMode     = false;
	$confirm       = false;
	$quiet         = false;
	$debug         = false;

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
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
		case '-V':
		case '-v':
			display_version();
			exit(0);
		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit(0);
		case '--quiet':
			$quietMode = true;

			break;
		default:
			print "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
		}
	}

	/* process the various lists into validation arrays */
	$hosts     = getHostsByDescription();
	$addresses = getAddresses();
	$ids_host	 = array();
	$ids_ip    = array();

	/* process host description */
	if ($description > '') {
		if ($debug) {
			print "Searching hosts by description...\n";
		}

		$ids_host = preg_array_key_match("/$description/", $hosts);
		if (cacti_sizeof($ids_host) == 0) {
			print "ERROR: Unable to find host in the database matching description ($description)\n";
			exit(1);
		}
	}

	if ($ip > '') {
		if ($debug) {
			print "Searching hosts by IP...\n";
		}

		$ids_ip = preg_array_key_match("/$ip/", $addresses);
		if (cacti_sizeof($ids_ip) == 0) {
			print "ERROR: Unable to find host in the database matching IP ($ip)\n";
			exit(1);
		}
	}

	if (cacti_sizeof($ids_host) == 0 && cacti_sizeof($ids_ip) == 0) {
		print "ERROR: No matches found, was IP or Description set properly?\n";
		exit(1);
	}

	$ids = array_merge($ids_host, $ids_ip);
	$ids = array_unique($ids, SORT_NUMERIC);

	$ids_sql = implode(',',$ids);
	if ($debug) {
		print "Finding devices with ids $ids_sql\n\n";
	}

	$hosts = db_fetch_assoc("SELECT id, hostname, description FROM host WHERE id IN ($ids_sql) ORDER by description");
	$ids_found = array();
	if (!$quiet) {
		printf("%8.s | %30.s | %30.s\n",'id','host','description');
		foreach ($hosts as $host) {
			printf("%8.d | %30.s | %30.s\n",$host['id'],$host['hostname'],$host['description']);
			$ids_found[] = $host['id'];
		}
		print "\n";
	}

	if ($confirm) {
		$ids_confirm = implode(', ',$ids_found);
		if (!$quiet) {
			print "Removing devices with ids: $ids_confirm\n";
		}
		$host_id = api_device_remove_multi($ids);

		if (is_error_message()) {
			print "ERROR: Failed to remove devices\n";
			exit(1);
		} else {
			print "Success - removed device-ids: $ids_confirm\n";
			exit(0);
		}
	} else {
		print "Please use --confirm to remove these devices\n";
	}
} else {
	display_help();
	exit(0);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Remove Device Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: remove_device.php --description=[description] --ip=[IP]\n";
	print "    [--confirm] [--quiet]\n\n";
	print "Required:\n";
	print "    --description  the name that will be displayed by Cacti in the graphs\n";
	print "    --ip           self explanatory (can also be a FQDN)\n";
	print "   (either one or both fields can be used and may be regex)\n\n";
	print "Optional:\n";
	print "    -confirm       confirms that you wish to remove matches\n\n";
	print "List Options:\n";
	print "    --quiet - batch mode value return\n\n";
}

function preg_array_key_match($needle, $haystack) {
	global $debug;
	$matches = array ();

	if (isset($haystack)) {
		if (!is_array($haystack)) {
			$haystack = array($haystack);
		}
	} else {
		$haystack = array();
	}

	if ($debug) {
		print "Attempting to match against '$needle' against ".cacti_sizeof($haystack)." entries\n";
	}

	foreach ($haystack as $str => $value) {
		if ($debug) {
			print " - Key $str => Value $value\n";
		}

		if (preg_match ($needle, $str, $m)) {
			if ($debug) {
				print "   + $str: $value\n";
			}
			$matches[] = $value;
		}
	}

	return $matches;
}
