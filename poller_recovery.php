#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

/* tick use required as of PHP 4.3.0 to accomodate signal handling */
declare(ticks = 1);

require(__DIR__ . '/include/cli_check.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/boost.php');
require_once($config['base_path'] . '/lib/dsstats.php');

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
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
			cacti_log('WARNING: Boost Poller terminated by user', false, 'BOOST');

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

$recovery_pid = db_fetch_cell("SELECT value FROM settings WHERE name='recovery_pid'", true, $local_db_cnn_id);
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

$record_limit = 10000;
$inserted     = 0;
$sleep_time   = 3;

debug('About to start recovery processing');

if (!empty($recovery_pid)) {
	$pid = posix_kill($recovery_pid, 0);
	if ($pid === false) {
		$run = true;
	} else {
		$run = false;
	}
} else {
	$run = true;
}

if ($run) {
	debug('No pid exists, starting recovery process!');

	db_execute("DELETE FROM settings WHERE name='recovery_pid'", true, $local_db_cnn_id);

	$end_count = 0;

	/* let the console know you are in recovery mode */
	db_execute_prepared('UPDATE poller
		SET status="5"
		WHERE id= ?', array($poller_id), true, $remote_db_cnn_id);

	poller_push_reindex_data_to_main();

	while (true) {
		$time_records  = db_fetch_assoc('SELECT time, count(*) AS entries
			FROM poller_output_boost
			GROUP BY time', true, $local_db_cnn_id);

		debug('There are ' . cacti_sizeof($time_records) . ' in the recovery database');

		$total_records = db_affected_rows();
		$found         = 0;

		if (!cacti_sizeof($time_records)) {
			// This should happen, but only after purging at least some records
			break;
		} else {
			$i          = 0;
			$purge_time = 0;

			/* traverse through the records getting the totals
			 * continue doing this till you get to the end
			 * or hit the record limit */
			foreach ($time_records as $record) {
				$time   = $record['time'];
				$found += $record['entries'];
				$i++;

				if ($found > $record_limit) {
					if ($i == $total_records) {
						if ($end_count > 1) {
							$operator = '<=';
						} else {
							$operator = '<';
						}

						$end_count++;
						$sleep_time = 3;
					} else {
						$operator = '<=';
						$sleep_time = 0;
					}

					$purge_time = $time;

					break;
				} elseif ($i == $total_records) {
					if ($end_count > 1) {
						$operator = '<=';
					} else {
						$operator = '<';
					}

					$end_count++;
					$purge_time = $time;
				}
			}

			if ($purge_time == 0) {
				$rows = db_fetch_assoc("SELECT *
					FROM poller_output_boost
					ORDER BY time ASC", true, $local_db_cnn_id);
			} else {
				$rows = db_fetch_assoc("SELECT *
					FROM poller_output_boost
					WHERE time $operator '$purge_time'
					ORDER BY time ASC", true, $local_db_cnn_id);
			}

			if (cacti_sizeof($rows)) {
				$count     = 0;
				$sql_array = array();

				foreach($rows as $r) {
					$sql = '(' . $r['local_data_id'] . ',' . db_qstr($r['rrd_name']) . ',' . db_qstr($r['time']) . ',' . db_qstr($r['output']) . ')';
					$count += strlen($sql);

					if ($count >= $max_allowed_packet) {
						db_execute('INSERT IGNORE INTO poller_output_boost
							(local_data_id, rrd_name, time, output)
							VALUES ' . implode(',', $sql_array), true, $remote_db_cnn_id);

						$inserted += cacti_sizeof($sql_array);
						$sql_array = array();
						$count = 0;
					}

					$sql_array[] = $sql;
				}

				if ($count > 0) {
					db_execute("INSERT IGNORE INTO poller_output_boost
						(local_data_id, rrd_name, time, output)
						VALUES " . implode(',', $sql_array), true, $remote_db_cnn_id);
					$inserted += $count;
				}

				/* remove the recovery records */
				if (is_object($local_db_cnn_id)) {
					// Only go through this if the local database is reachable
					if ($purge_time == 0) {
						db_execute("DELETE FROM poller_output_boost", true, $local_db_cnn_id);
					} else {
						db_execute("DELETE FROM poller_output_boost WHERE time $operator '$purge_time'", true, $local_db_cnn_id);
					}
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
	cacti_log('Recovery process still running for Poller ' . $poller_id . '.  PID is ' . $recovery_pid);
	exit(1);
}

$end = microtime(true);

cacti_log('RECOVERY STATS: Time:' . round($end - $start, 2) . ' Records:' . $inserted, false, 'SYSTEM');

exit(0);
