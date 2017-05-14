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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$no_http_headers = true;

include(dirname(__FILE__) . '/../include/global.php');
include_once($config['base_path'] . '/lib/utility.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = FALSE;
$host_id = 0;

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
			case '--host-id':
				$host_id = trim($value);

				if (!is_numeric($host_id)) {
					echo "ERROR: You must supply a valid device id to run this script!\n";
					exit(1);
				}

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
			default:
				echo 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

/* obtain timeout settings */
$max_execution = ini_get('max_execution_time');

/* set new timeout */
ini_set('max_execution_time', '0');

/* get the data_local Id's for the poller cache */
if ($host_id > 0) {
	$poller_data  = db_fetch_assoc('SELECT * FROM data_local WHERE host_id=' . $host_id);
} else {
	$poller_data  = db_fetch_assoc('SELECT * FROM data_local');
}

/* initialize some variables */
$current_ds = 1;
$total_ds = sizeof($poller_data);

/* setting local_data_ids to an empty array saves time during updates */
$local_data_ids = array();
$poller_items   = array();

/* issue warnings and start message if applicable */
echo "WARNING: Do not interrupt this script.  Rebuilding the Poller Cache can take quite some time\n";
debug("There are '" . sizeof($poller_data) . "' data source elements to update.");

/* start rebuilding the poller cache */
if (sizeof($poller_data)) {
	foreach ($poller_data as $data) {
		if (!$debug) echo '.';
		$local_data_ids[] = $data['id'];
		$poller_items = array_merge($poller_items, update_poller_cache($data));

		debug("Data Source Item '$current_ds' of '$total_ds' updated");
		$current_ds++;
	}

	if (sizeof($local_data_ids)) {
		poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
	}
}
if (!$debug) echo "\n";

/* poller cache rebuilt, restore runtime parameters */
ini_set('max_execution_time', $max_execution);

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
    echo "Cacti Rebuild Poller Cache Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	echo "\nusage: rebuild_poller_cache.php [--host-id=ID] [--debug]\n\n";
	echo "A utility to repopulate Cacti's poller cache for a host or a system.  Note: That when performing\n";
	echo "for an entire Cacti system, expecially a large one, this may take some time.\n\n";
	echo "Optional:\n";
	echo "    --host-id=ID - Limit the repopulation to a single Cacti Device\n";
	echo "    --debug      - Display verbose output during execution\n\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		echo 'DEBUG: ' . trim($message) . "\n";
	}
}
