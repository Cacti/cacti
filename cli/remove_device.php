#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
	$id_ids        = false;

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
		case '--id':
			$id = trim($value);

			if (strpos($id, ',') !== false) {
				$ids_id = explode(',', $id);
			} else {
				$ids_id = array($id);
			}

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
			print "ERROR: Invalid Argument: ($arg)" . PHP_EOL;
			display_help();
			exit(1);
		}
	}

	/* process the various lists into validation arrays */
	$hosts     = getHostsByDescription();
	$addresses = getAddresses();
	$ids_host  = array();
	$ids_ip    = array();

	/* process host description */
	if ($description != '') {
		if ($debug) {
			print "Searching hosts by description..." . PHP_EOL;
		}

		$ids_host = preg_array_key_match("/$description/", $hosts);
		if (cacti_sizeof($ids_host) == 0) {
			print "ERROR: Unable to find host in the database matching description ($description)" . PHP_EOL;
			exit(1);
		}
	}

	if ($ip != '') {
		if ($debug) {
			print "Searching hosts by IP..." . PHP_EOL;
		}

		$ids_ip = preg_array_key_match("/$ip/", $addresses);
		if (cacti_sizeof($ids_ip) == 0) {
			print "ERROR: Unable to find host in the database matching IP ($ip)" . PHP_EOL;
			exit(1);
		}
	}

	if (cacti_sizeof($ids_host) == 0 && cacti_sizeof($ids_ip) == 0 && cacti_sizeof($ids_id) == 0) {
		print "ERROR: No matches found, was IP or Description set properly?" . PHP_EOL;
		exit(1);
	}

	$ids = array_merge($ids_host, $ids_ip);
	$ids = array_unique($ids, SORT_NUMERIC);

	if ($ids_id !== false) {
		$ids = array_merge($ids, $ids_id);
		$ids = array_unique($ids, SORT_NUMERIC);
	}

	$ids_sql = implode(',', $ids);
	if ($debug) {
		print "Finding devices with ids $ids_sql" . PHP_EOL;
	}

	$hosts = db_fetch_assoc("SELECT id, hostname, description
		FROM host
		WHERE id IN ($ids_sql)
		ORDER BY description");

	$ids_found = array();
	if (!$quiet) {
		printf('%8.s | %30.s | %30.s' . PHP_EOL, 'id', 'host', 'description');

		foreach ($hosts as $host) {
			printf('%8.d | %30.s | %30.s' . PHP_EOL,$host['id'],$host['hostname'],$host['description']);
			$ids_found[] = $host['id'];
		}



		print PHP_EOL;
	}

	if ($confirm) {
		$ids_confirm = implode(', ', $ids_found);
		if (!$quiet) {
			print "Removing devices with ids: $ids_confirm" . PHP_EOL;
		}

		$host_id = api_device_remove_multi($ids);

		if (is_error_message()) {
			print "ERROR: Failed to remove devices" . PHP_EOL;
			exit(1);
		} else {
			print "Success - removed device-ids: $ids_confirm" . PHP_EOL;
			foreach ($hosts as $host) {
				cacti_log("Device Removed via remove_device.php - Device ID: " . $host['id'] . ", Hostname: " . $host['hostname'] . ", Description: " . $host['description'],FALSE,'INFO');
			}

			exit(0);
		}
	} else {
		print "Please use --confirm to remove these devices" . PHP_EOL;
	}
} else {
	display_help();
	exit(0);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Remove Device Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL;
	print 'usage: remove_device.php --description=\'S\' | --ip=\'S\' | --id=N,N,N,...' . PHP_EOL;
	print '    [--confirm] [--quiet]' . PHP_EOL . PHP_EOL;

	print 'Required: (on or more)' . PHP_EOL;
	print "    --description='S' A substring or regular expression of the hostname or description." . PHP_EOL;
	print "    --ip='S'          A IP or hostname (can also be a FQDN)." . PHP_EOL;
	print '    --id=N,N,...      A column delimited list of device ids.' . PHP_EOL . PHP_EOL;

	print '   (both --description and --ip can be a regex)' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '    --confirm           confirms that you wish to remove matches' . PHP_EOL . PHP_EOL;

	print 'List Options:' . PHP_EOL;
	print '    --quiet             batch mode value return' . PHP_EOL . PHP_EOL;
}

function preg_array_key_match($needle, $haystack) {
	global $debug;

	$matches = array();

	if (isset($haystack)) {
		if (!is_array($haystack)) {
			$haystack = array($haystack);
		}
	} else {
		$haystack = array();
	}

	if ($debug) {
		print "Attempting to match against '$needle' against " . cacti_sizeof($haystack) . " entries" . PHP_EOL;
	}

	foreach ($haystack as $str => $value) {
		if ($debug) {
			print " - Key $str => Value $value" . PHP_EOL;
		}

		if (preg_match ($needle, $str, $m)) {
			if ($debug) {
				print "   + $str: $value" . PHP_EOL;
			}

			$matches[] = $value;
		}
	}

	return $matches;
}

