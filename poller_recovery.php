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

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

ini_set('output_buffering', 'Off');

require(__DIR__ . '/include/cli_check.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/boost.php');
require_once($config['base_path'] . '/lib/dsstats.php');

/*  display_version - displays version information */
function display_version() {
	$version = CACTI_VERSION_TEXT_CLI;
	print "Cacti Boost RRD Update Poller, Version $version " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: poller_recovery.php [--verbose] [--force] [--debug]\n\n";
	print "Cacti's Remote Poller Recovery Script.  This poller will transfer all offline boost records\n";
	print "to the Main Cacti Pollers boost table\n";
	print "Optional:\n";
	print "    --verbose - Show details logs at the command line\n";
	print "    --force   - Force the execution of a update process\n";
	print "    --debug   - Display verbose output during execution\n\n";
}

function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('RECOVERY WARNING: Recovery Poller terminated by user', false, 'POLLER');

			/* tell the main poller that we are done */
			db_execute("REPLACE INTO settings (name, value) VALUES ('boost_poller_status', 'terminated - end time:" . date('Y-m-d G:i:s') ."')");

			exit;
			break;
		default:
			/* ignore all other signals */
	}

}

function debug($string) {
	global $debug;

	if ($debug) {
		print trim($string) . "\n";
	}
}

global $local_db_cnn_id, $remote_db_cnn_id;

$recovery_pid = db_fetch_cell("SELECT value FROM settings WHERE name='recovery_pid'", '', true, $local_db_cnn_id);
$packet_data  = db_fetch_row("SHOW GLOBAL VARIABLES LIKE 'max_allowed_packet'", true, $remote_db_cnn_id);

if (isset($packet_data['Value'])) {
	$max_allowed_packet = $packet_data['Value'];
} else {
	$max_allowed_packet = 1E6;
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug          = false;
$forcerun       = false;
$verbose        = false;
$poller_id      = $config['poller_id'];

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
			case '-f':
			case '--force':
				$forcerun = true;
				break;
			case '--verbose':
				$verbose = true;
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
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

/* check for an invalid run locaiton */
if ($poller_id == 1) {
	print "ERROR: This command is only to be run on remote Cacti Data Collectors\n";
	exit(1);
}

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* take time and log performance data */
$start = microtime(true);

/* configuration variables */
$record_limit = 150000;
$sleep_time   = 1;

/* global counter variables */
$records_inserted = 0;

debug('About to start recovery processing');

if (!empty($recovery_pid)) {
	$pid = posix_kill($recovery_pid, 0);
	if ($pid === false) {
		/* we found a stale PID, so we delete it from the table */
		db_execute("DELETE FROM settings WHERE name='recovery_pid'", true, $local_db_cnn_id);

		$run = true;
	} else {
		$run = false;
	}
} else {
	$run = true;
}

if ($run) {
	$my_pid = getmypid();

	cacti_log('RECOVERY: No pid exists, starting recovery process (PID=' . $my_pid . ')!', false, 'POLLER');

	db_execute_prepared('REPLACE INTO settings
		(name, value)
		VALUES ("recovery_pid", ?)',
		array($my_pid), true, $local_db_cnn_id);

	/* let the console know you are in recovery mode */
	db_execute_prepared('UPDATE poller
		SET status = "5"
		WHERE id = ?',
		array($poller_id), true, $remote_db_cnn_id);

	poller_push_reindex_data_to_poller(0, 0, true);

	while (true) {
		cacti_log('RECOVERY: Getting max_time for '. $record_limit . ' records.', false, 'POLLER');

		$max_time = db_fetch_cell("SELECT MAX(time)
			FROM (
				SELECT time
				FROM poller_output_boost
				ORDER BY time ASC
				LIMIT $record_limit
			) AS rs", '', true, $local_db_cnn_id);

		if (empty($max_time)) {
			db_execute("DELETE FROM settings WHERE name='recovery_pid'", true, $local_db_cnn_id);

			break;
		} else {
			cacti_log('RECOVERY: Fetching records till time: ' . $max_time . ' from poller DB', false, 'POLLER');

			$rows = db_fetch_assoc_prepared('SELECT *
				FROM poller_output_boost
				WHERE time <= ?
				ORDER BY time ASC, local_data_id ASC',
				array($max_time));

			if (cacti_sizeof($rows)) {
				$packet_size = 0;
				$sql_array   = array();

				foreach($rows as $r) {
					$sql = '(' . $r['local_data_id'] . ',' . db_qstr($r['rrd_name']) . ',' . db_qstr($r['time']) . ',' . db_qstr($r['output']) . ')';
					$sql_size = strlen($sql);

					/* if adding a new row would exceed max_allowed_packet, send the current frame to the main poller and start a new frame */
					if (($packet_size + $sql_size) >= $max_allowed_packet) {
						$record_count = cacti_sizeof($sql_array);

						cacti_log('RECOVERY: Writing ' . $record_count . ' records (' . $packet_size . ' bytes) to main (partial).', false, 'POLLER');

						db_execute('INSERT IGNORE INTO poller_output_boost
							(local_data_id, rrd_name, time, output)
							VALUES ' . implode(',', $sql_array), true, $remote_db_cnn_id);

						$records_inserted += $record_count;
						$sql_array = array();
						$packet_size = 0;
					}

					$sql_array[] = $sql;
					$packet_size += $sql_size;
				}

				/* if there is data in the last frame, send it to main poller as well and finalize */
				if ($packet_size > 0) {
					$record_count = cacti_sizeof($sql_array);

					cacti_log('RECOVERY: Writing ' . $record_count . ' records (' . $packet_size . ' bytes) to main (last slice).', false, 'POLLER');

					db_execute("INSERT IGNORE INTO poller_output_boost
						(local_data_id, rrd_name, time, output)
						VALUES " . implode(',', $sql_array), true, $remote_db_cnn_id);

					$records_inserted += $record_count;
				}

				/* remove the recovery records */
				if (is_object($local_db_cnn_id)) {
					db_execute_prepared('DELETE FROM poller_output_boost
						WHERE time <= ?',
						array($max_time), true, $local_db_cnn_id);
				}
			}

			sleep($sleep_time);
		}
	}

	/* let the console know you are in online mode */
	db_execute_prepared('UPDATE poller
		SET status="2"
		WHERE id= ?', array($poller_id), false, $remote_db_cnn_id);
} else {
	debug('Recovery process still running, exiting');
	cacti_log('RECOVERY: Recovery process still running for Poller ' . $poller_id . '.  PID is ' . $recovery_pid, false, 'POLLER');
	exit(1);
}

$end = microtime(true);

cacti_log('RECOVERY STATS: Time:' . round($end - $start, 2) . ' Records:' . $records_inserted, false, 'SYSTEM');

exit(0);
