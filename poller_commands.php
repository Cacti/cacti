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

/* we are not talking to the browser */
$no_http_headers = true;

define('MAX_RECACHE_RUNTIME', 296);

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* Start Initialization Section */
include(dirname(__FILE__) . '/include/global.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/lib/rrd.php');

$poller_id = $config['poller_id'];

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
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
				exit;
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
				echo "ERROR: Invalid Argument: ($arg)\n\n";
				display_help();
				exit(1);
		}
	}
}

/* Record Start Time */
$start = microtime(true);

$poller_commands = db_fetch_assoc_prepared('SELECT action, command 
	FROM poller_command 
	WHERE poller_id = ?', array($poller_id));

$last_host_id   = 0;
$first_host     = true;
$recached_hosts = 0;

if (sizeof($poller_commands) > 0) {
	foreach ($poller_commands as $command) {
		switch ($command['action']) {
		case POLLER_COMMAND_REINDEX:
			list($host_id, $data_query_id) = explode(':', $command['command']);
				if ($last_host_id != $host_id) {
				$last_host_id = $host_id;
				$first_host = true;
				$recached_hosts++;
			} else {
				$first_host = false;
			}

			if ($first_host) {
				cacti_log("Device[$host_id] WARNING: Recache Event Detected for Device", true, 'PCOMMAND');
			}

			cacti_log("Device[$host_id] RECACHE: Recache for Device, data query #$data_query_id", true, 'PCOMMAND', POLLER_VERBOSITY_DEBUG);

			run_data_query($host_id, $data_query_id);

			cacti_log("Device[$host_id] RECACHE: Recache successful.", true, 'PCOMMAND', POLLER_VERBOSITY_DEBUG);
			break;
		default:
			cacti_log('ERROR: Unknown poller command issued', true, 'PCOMMAND');
		}

		/* record current_time */
		$current = microtime(true);

		/* end if runtime has been exceeded */
		if (($current-$start) > MAX_RECACHE_RUNTIME) {
			cacti_log("ERROR: Poller Command processing timed out after processing '" . $command . "'",true,'PCOMMAND');
			break;
		}
	}

	db_execute_prepared('DELETE FROM poller_command WHERE poller_id = ?', array($poller_id));
}

/* take time to log performance data */
$recache = microtime(true);

$recache_stats = sprintf('Poller:%i RecacheTime:%01.4f DevicesRecached:%s',	$poller_id, round($recache - $start, 4), $recached_hosts);

if ($recached_hosts > 0) {
	cacti_log('STATS: ' . $recache_stats, true, 'RECACHE');
}

/* insert poller stats into the settings table */
db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)',
	array('stats_recache_' . $poller_id, $recache_stats));

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	print "Cacti Poller Commands Poller, Version $version " . COPYRIGHT_YEARS . "\n";
}

function display_help () {
	display_version();

	echo "\nusage: poller_commands.php [--poller=ID] [--debug]\n\n";
	echo "Cacti's commands poller.  This poller can receive specifically crafted commands from\n";
	echo "either the Cacti UI, or from the main poller, and then run them in the background.\n\n";
	echo "Optional:\n";
	echo "    --poller=ID - The poller to run as.  Defaults to the system poller\n";
	echo "    --debug     - Display verbose output during execution\n\n";
}
