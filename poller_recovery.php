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

/* tick use required as of PHP 4.3.0 to accomodate signal handling */
declare(ticks = 1);

/* we are not talking to the browser */
$no_http_headers = true;

/*  display_version - displays version information */
function display_version() {
    $version = db_fetch_cell('SELECT cacti FROM version');
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
			cacti_log('WARNING: Boost Poller terminated by user', FALSE, 'BOOST');

			/* tell the main poller that we are done */
			db_execute("REPLACE INTO settings (name, value) VALUES ('boost_poller_status', 'terminated - end time:" . date('Y-m-d G:i:s') ."')");

			exit;
			break;
		default:
			/* ignore all other signals */
	}

}

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'boost') !== FALSE) {
	chdir('../../');
}

/* include important functions */
include_once('./include/global.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/boost.php');
include_once($config['base_path'] . '/lib/dsstats.php');

global $local_db_cnn_id, $remote_db_cnn_id;

$recovery_pid = read_config_option('recovery_pid');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug          = FALSE;
$forcerun       = FALSE;
$verbose        = FALSE;
$poller_id      = $config['poller_id'];

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
			case '-f':
			case '--force':
				$forcerun = TRUE;
				break;
			case '--verbose':
				$verbose = TRUE;
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

if (posix_getpgid($recovery_pid) === false) {
	db_execute("DELETE FROM settings WHERE name='recovery_pid'");

	$end_count = 0;

	while (true) {
		$time_records  = db_fetch_assoc('SELECT time, count(*) AS entries 
			FROM poller_output_boost 
			GROUP BY time');

		$total_records = db_affected_rows();
		$found         = 0;

		if (!sizeof($time_records)) {
			// This should happen, but only after purging at least some records
			break;
		} else {
			$i          = 0;
			$purge_time = 0;

			/* traverse through the records getting the totals
			 * continue doing this till you get to the end
			 * or hit the record limit */
			foreach ($time_records as $record) {
				$time   = $records['time'];
				$found += $record['entries'];
				$i++;

				if ($found > $record_limit) {
					if ($i == $total_records) {
						if ($end_count > 1) {
							$operator = '<=';
						}else{
							$operator = '<';
						}

						$end_count++;
						$sleep_time = 3;
					}else{
						$operator = '<=';
						$sleep_time = 0;
					}

					$purge_time = $time;

					break;
				}elseif ($i == $total_records) {
					if ($end_count > 1) {
						$operator = '<=';
					}else{
						$operator = '<';
					}

					$end_count++;
					$purge_time = $time;
				}
			}

			$rows = db_fetch_assoc("SELECT * 
				FROM poller_output_boost 
				WHERE time $operator '$purge_time' 
				ORDER BY time ASC");

			if (sizeof($rows)) {
				$count     = 0;
				$sql_array = array();

				foreach($rows as $row) {
					$sql_array[] .= "(" . $r['local_data_id'] . "," . db_qstr($r['rrd_name']) . "," . db_qstr($r['time']) . "," . db_qstr($r['output']) . ")";
					$count++;

					if ($count > 1000) {
						db_execute("INSERT IGNORE INTO poller_output_boost 
							(local_data_id, rrd_name, time, output) 
							VALUES " . implode(',', $sql_array), $remote_db_cnn_id);

						$sql_array = array();
						$inserted += $count;
						$count = 0;
					}
				}

				if ($count > 0) {
					db_execute("INSERT IGNORE INTO poller_output_boost 
						(local_data_id, rrd_name, time, output) 
						VALUES " . implode(',', $sql_array), true, $remote_db_cnn_id);
					$inserted += $count;
				}
			}

			sleep($sleep_time);
		}
	}
}else{
	cacti_log("Recovery process still running for Poller " . $poller_id . ".  PID is $recovery_pid");
	exit(1);
}

$end = microtime(true);

cacti_log('RECOVERY STATS: Time:' . round($end - $start, 2) . ' Records:' . $inserted, false, 'SYSTEM');

exit(0);
