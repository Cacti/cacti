#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

/* we are not talking to the browser */
define('MAX_RECACHE_RUNTIME', 1800);

ini_set('max_runtime', '-1');
ini_set('memory_limit', '-1');

require(__DIR__ . '/include/cli_check.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/api_tree.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/html_form_template.php');
require_once($config['base_path'] . '/lib/ping.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/rrd.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/sort.php');
require_once($config['base_path'] . '/lib/template.php');
require_once($config['base_path'] . '/lib/utility.php');

$poller_id = $config['poller_id'];

$debug = false;

global $poller_db_cnn_id, $remote_db_cnn_id;

if ($config['poller_id'] > 1 && $config['connection'] == 'online') {
	$poller_db_cnn_id = $remote_db_cnn_id;
} else {
	$poller_db_cnn_id = false;
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--version':
			case '-V':
				display_version();
				exit(0);
			case '-H':
			case '--help':
				display_help();
				exit(0);
			case '--poller':
			case '-p':
				$poller_id = $value;
				break;
			case '--debug':
			case '-d':
				$debug = true;
				break;
			default:
				print "ERROR: Invalid Argument: ($arg)\n\n";
				display_help();
				exit(1);
		}
	}
}

/* Record Start Time */
$start = microtime(true);

$max_updated = db_fetch_cell_prepared('SELECT MAX(UNIX_TIMESTAMP(last_updated))
	FROM poller_command
	WHERE poller_id = ?',
	array($poller_id), '', true, $poller_db_cnn_id);

$poller_commands = db_fetch_assoc_prepared('SELECT action, command
	FROM poller_command
	WHERE poller_id = ?',
	array($poller_id), true, $poller_db_cnn_id);

$last_host_id   = 0;
$first_host     = true;
$recached_hosts = 0;

if ($debug) {
	$verbosity = POLLER_VERBOSITY_LOW;
} else {
	$verbosity = POLLER_VERBOSITY_MEDIUM;
}

/* silently end if the registered process is still running, or process table missing */
if (!register_process_start('commands', 'master', $poller_id, read_config_option('commands_timeout'))) {
	exit(0);
}

if (cacti_sizeof($poller_commands)) {
	foreach ($poller_commands as $command) {
		switch ($command['action']) {
		case POLLER_COMMAND_REINDEX:
			list($device_id, $data_query_id) = explode(':', $command['command']);

			if ($last_host_id != $device_id) {
				$last_host_id = $device_id;
				$first_host = true;
				$recached_hosts++;
			} else {
				$first_host = false;
			}

			if ($first_host) {
				cacti_log("Device[$device_id] NOTE: Recache Event Detected for Device", true, 'PCOMMAND');
			}

			cacti_log("Device[$device_id] DQ[$data_query_id] RECACHE: Recache for Device started.", true, 'PCOMMAND', $verbosity);
			run_data_query($device_id, $data_query_id);
			cacti_log("Device[$device_id] DQ[$data_query_id] RECACHE: Recached successfully.", true, 'PCOMMAND', $verbosity);

			break;
		case POLLER_COMMAND_PURGE:
			$device_id = $command['command'];

			api_device_purge_from_remote($device_id, $poller_id);
			cacti_log("Device[$device_id] PURGE: Purged successfully.", true, 'PCOMMAND', $verbosity);

			break;
		default:
			cacti_log('ERROR: Unknown poller command issued', true, 'PCOMMAND');
		}

		/* record current_time */
		$current = microtime(true);

		/* end if runtime has been exceeded */
		if (($current-$start) > MAX_RECACHE_RUNTIME) {
			cacti_log("ERROR: Poller Command processing timed out after processing '$command'", true, 'PCOMMAND');
			break;
		}
	}

	db_execute_prepared('DELETE FROM poller_command
		WHERE poller_id = ?
		AND last_updated <= FROM_UNIXTIME(?)',
		array($poller_id, $max_updated), true, $poller_db_cnn_id);
} else {
	cacti_log('NOTE: No Poller Commands found for processing', true, 'PCOMMAND', $verbosity);
}

/* take time to log performance data */
$recache = microtime(true);

$recache_stats = sprintf('Poller:%s RecacheTime:%01.4f DevicesRecached:%s',	$poller_id, round($recache - $start, 4), $recached_hosts);

if ($recached_hosts > 0) {
	cacti_log('STATS: ' . $recache_stats, true, 'RECACHE');
}

/* insert poller stats into the settings table */
db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)',
	array('stats_recache_' . $poller_id, $recache_stats), true, $poller_db_cnn_id);

unregister_process('commands', 'master', $poller_id);

/*  display_version - displays version information */
function display_version() {
	$version = CACTI_VERSION_TEXT_CLI;
	print "Cacti Poller Commands Poller, Version $version " . COPYRIGHT_YEARS . "\n";
}

function display_help () {
	display_version();

	print "\nusage: poller_commands.php [--poller=ID] [--debug]\n\n";
	print "Cacti's commands poller.  This poller can receive specifically crafted commands from\n";
	print "either the Cacti UI, or from the main poller, and then run them in the background.\n\n";
	print "Optional:\n";
	print "    --poller=ID - The poller to run as.  Defaults to the system poller\n";
	print "    --debug     - Display verbose output during execution\n\n";
}
