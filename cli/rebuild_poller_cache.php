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
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

if ($config['poller_id'] > 1) {
	print 'FATAL: This utility is designed for the main Data Collector only' . PHP_EOL;
	exit(1);
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;
$host_id = 0;
$host_template_id = 0;

if (cacti_sizeof($parms)) {
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
				$debug = true;
				break;
			case '--host-id':
				$host_id = trim($value);

				if (!is_numeric($host_id)) {
					print 'ERROR: You must supply a valid device id to run this script!' . PHP_EOL;
					exit(1);
				}

				break;
			case '--host-template-id':
				$host_template_id = trim($value);

				if (!is_numeric($host_id)) {
					print 'ERROR: You must supply a valid device template id to run this script!' . PHP_EOL;
					exit(1);
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
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}
}

/* obtain timeout settings */
$max_execution = ini_get('max_execution_time');

/* set new timeout */
ini_set('max_execution_time', '0');

$sql_where = '';
$params    = array();

if ($host_id > 0) {
	$sql_where = 'WHERE dl.host_id = ?';
	$params[] = $host_id;
}

if ($host_template_id > 0) {
	$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' h.host_template_id = ?';
	$params[] = $host_template_id;
}

$pollers = array_rekey(
	db_fetch_assoc('SELECT DISTINCT poller_id
		FROM host
		WHERE disabled = ""'),
	'poller_id', 'poller_id'
);

if (cacti_sizeof($pollers)) {
	print 'NOTE:  Do not interrupt this script.  Rebuilding the Poller Cache can take quite some time' . PHP_EOL;

	foreach($pollers as $poller_id) {
		/* get the hosts for this poller_id */
		$hosts  = array_rekey(
			db_fetch_assoc_prepared("SELECT DISTINCT dl.host_id
				FROM data_local AS dl
				INNER JOIN host AS h
				ON dl.host_id = h.id
				$sql_where
				AND poller_id = $poller_id
				ORDER BY dl.host_id",
				$params),
			'host_id', 'host_id'
		);

		/* add special host 0 */
		if ($poller_id == 1 && $host_template_id == 0 && $host_id == 0) {
			$hosts[0] = 0;
		}

		/* issue warnings and start message if applicable */
		debug("There are '" . cacti_sizeof($hosts) . "' Devices to rebuild poller items for on poller $poller_id.");

		/* start rebuilding the poller cache */
		if (cacti_sizeof($hosts)) {
			foreach ($hosts as $host_id) {
				if ($host_id > 0) {
					$description = db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($host_id));
				} else {
					$description = 'Special Host Zero';
				}

				$data_sources = db_fetch_cell_prepared('SELECT COUNT(*) FROM data_local WHERE host_id = ?', array($host_id));

				if ($data_sources > 0) {
					print "NOTE:  Processing Device:'$description' on Poller:'$poller_id' having $data_sources Data Sources." . PHP_EOL;
					push_out_host($host_id);
				} else {
					print "NOTE:  Removing Poller Cache Items for Device:'$description' on Poller:'$poller_id' as it has no Data Sources." . PHP_EOL;
					db_execute_prepared('DELETE FROM poller_item WHERE host_id = ?', array($host_id));
				}
			}
		}
	}
}

if (!$debug) {
	print PHP_EOL;
}

/* poller cache rebuilt, restore runtime parameters */
ini_set('max_execution_time', $max_execution);

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Rebuild Poller Cache Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: rebuild_poller_cache.php [--host-id=ID] [--debug]' . PHP_EOL . PHP_EOL;

	print 'A utility to repopulate Cacti\'s poller cache for a host or a system.  Note: That when performing' . PHP_EOL;
	print 'for an entire Cacti system, especially a large one, this may take some time.' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '    --host-id=ID          - Limit the repopulation to a single Device' . PHP_EOL;
	print '    --host-template-id=ID - Limit the repopulation to a single Device Template' . PHP_EOL;
	print '    --debug               - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}

function debug($message) {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . trim($message) . "\n";
	}
}
