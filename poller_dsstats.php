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

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

ini_set('output_buffering', 'Off');

require(__DIR__ . '/include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');
require_once(CACTI_PATH_LIBRARY . '/graph_variables.php');
require_once(CACTI_PATH_LIBRARY . '/dsstats.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug          = false;
$forcerun       = false;
$type           = 'pmaster';
$thread_id      = 0;

global $rrd_files, $total_system, $total_user, $total_real, $total_dsses;
global $user_time, $system_time, $real_time;

(double) $total_system = 0;
(double) $total_user   = 0;
(double) $total_real   = 0;
$total_dsses  = 0;

$system_time  = 0;
$user_time    = 0;
$real_time    = 0;

$rrd_files    = 0;

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg   = $parameter;
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
			case '--type':
				$type = $value;

				break;
			case '--child':
				$thread_id = $value;

				break;
			case '--version':
			case '-v':
			case '-V':
				display_version();

				exit(0);
			case '--help':
			case '-h':
			case '-H':
				display_help();

				exit(0);

			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
		}
	}
}

/**
 * Types include
 *
 * pmaster  - the main process launched from the Cacti main poller and will launch child processes
 * pchild   - a child of the master process from the 'master'
 *
 * bmaster  - a boost master process, will perform launch bchild processes
 * bchild   - a child of the boost master process, will launch boost collection
 *
 * dmaster - a daily master process, will perform launch bchild processes
 * dchild  - a child of the daily master process, will launch boost collection
 *
 */

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* take time and log performance data */
$start = microtime(true);

/* let's give this script lot of time to run for ever */
ini_set('max_execution_time', '0');
dsstats_memory_limit();

/* send a gentle message to the log and stdout */
dsstats_debug('Polling Starting');

/* silently end if the registered process is still running */
if (!$forcerun) {
	if (!register_process_start('dsstats', $type, $thread_id, read_config_option('dsstats_timeout'))) {
		exit(0);
	}
}

// Collect data as determined by the type
switch ($type) {
	case 'pmaster':
		if (read_config_option('dsstats_enable') == 'on' || $forcerun) {
			dsstats_master_handler($forcerun);
		}

		break;
	case 'bmaster': // Launched at the end of boost
	case 'dmaster': // Launched inside this script for daily processing
		/* run the daily stats */
		dsstats_launch_children($type);

		/* Wait for all processes to continue */
		while ($running = dsstats_processes_running($type)) {
			dsstats_debug(sprintf('%s Processes Running, Sleeping for 2 seconds.', $running));
			sleep(2);
		}

		break;
	case 'child':  // Launched by the master process
	case 'bchild': // Launched by the boost process
		$child_start = microtime(true);

		dsstats_get_and_store_ds_avgpeak_values('daily', $thread_id);

		$total_time = microtime(true) - $child_start;

		dsstats_log_child_stats($type, $thread_id, $total_time);

		break;
	case 'dchild': // Launched by the daily master process
		$child_start = microtime(true);

		dsstats_debug(sprintf('Daily Stats Master Child %s Executing', $thread_id));
		dsstats_get_and_store_ds_avgpeak_values('weekly', $thread_id);
		dsstats_get_and_store_ds_avgpeak_values('monthly', $thread_id);
		dsstats_get_and_store_ds_avgpeak_values('yearly', $thread_id);

		$total_time = microtime(true) - $child_start;

		dsstats_log_child_stats($type, $thread_id, $total_time);

		break;
}

dsstats_debug('Polling Ending');

if (!$forcerun) {
	unregister_process('dsstats', $type, $thread_id);
}

exit(0);

function dsstats_purge_hourly_cache() {
	$hourly_window  = date('Y-m-d H:i:s', time() - (read_config_option('dsstats_hourly_duration') * 60));

	/* remove old records from the cache first */
	if (db_fetch_cell_prepared('SELECT COUNT(*) FROM data_source_stats_hourly_cache WHERE time < ?', array($hourly_window))) {
		db_execute_prepared('DELETE FROM data_source_stats_hourly_cache WHERE time < ?', array($hourly_window));
	}
}

function dsstats_insert_hourly_data_into_cache() {
	/* store the current averages into the hourly table */
	db_execute('INSERT INTO data_source_stats_hourly
		(local_data_id, rrd_name, average, peak)
		SELECT local_data_id, rrd_name, AVG(`value`), MAX(`value`)
		FROM data_source_stats_hourly_cache
		WHERE `value` IS NOT NULL
		GROUP BY local_data_id, rrd_name
		ON DUPLICATE KEY UPDATE average=VALUES(average), peak=VALUES(peak)');
}

function dsstats_master_handler($forcerun) {
	global $type;

	/* read some important settings relative to timing from the database */
	$major_time     = date('H:i:s', strtotime(read_config_option('dsstats_major_update_time')));
	$daily_interval = read_config_option('dsstats_daily_interval');

	/* check to see when the daily averages were updated last */
	$last_run_daily = read_config_option('dsstats_last_daily_run_time');
	$last_run_major = read_config_option('dsstats_last_major_run_time');

	// Purge the cache
	dsstats_purge_hourly_cache();

	// Insert new rows into cache
	dsstats_insert_hourly_data_into_cache();

	dsstats_log_statistics('HOURLY');

	/* see if boost is active or not */
	$boost_active = read_config_option('boost_rrd_update_enable');

	/* next let's see if it's time to update the daily interval */
	$current_time = time();

	if ($boost_active == 'on') {
		/* boost will spawn the collector */
		dsstats_debug('Skipping Periodic Rollup - Boost will handle the Periodic Roll-up Cycle');
	} else {
		if ($daily_interval == 'boost') {
			cacti_log("WARNING: Daily update interval set to 'boost' and boost not enabled, resetting to default of 1 hour", false, 'DSSTATS');

			set_config_option('dsstats_daily_interval', 60);

			$daily_interval = 60;
		}

		/* determine if it's time to determine hourly averages */
		if (empty($last_run_daily)) {
			/* since the poller has never run before, let's fake it out */
			set_config_option('dsstats_last_daily_run_time', date('Y-m-d G:i:s', $current_time));
		}

		/* if it's time to update daily statistics, do so now */
		if ((!empty($last_run_daily) && ((strtotime($last_run_daily) + ($daily_interval * 60)) < $current_time)) || $forcerun) {
			set_config_option('dsstats_last_daily_run_time', date('Y-m-d G:i:s', $current_time));

			/* run the daily stats */
			dsstats_launch_children($type);

			/* Wait for all processes to continue */
			while ($running = dsstats_processes_running($type)) {
				dsstats_debug(sprintf('%s Processes Running, Sleeping for 2 seconds.', $running));
				sleep(2);
			}

			dsstats_log_statistics('DAILY');
		}
	}

	/* lastly, let's see if it's time to run the major stats */
	if (empty($last_run_major)) {
		/* since the poller has never run before, let's fake it out */
		set_config_option('dsstats_last_major_run_time', date('Y-m-d G:i:s', $current_time));
	} else {
		$last_major_day = date('Y-m-d', strtotime($last_run_major));
		$next_major_day = strtotime($last_major_day . ' ' . $major_time) + 86400;
	}

	/* if its time to run major statistics, do so now */
	if ((!empty($last_run_major) && ($next_major_day < $current_time)) || $forcerun) {
		/* run the major stats, log first to keep other processes from running */
		set_config_option('dsstats_last_major_run_time', date('Y-m-d G:i:s', $current_time));

		/* run the daily stats */
		dsstats_launch_children('dmaster');

		/* Wait for all processes to continue */
		while ($running = dsstats_processes_running('dmaster')) {
			dsstats_debug(sprintf('%s Processes Running, Sleeping for 2 seconds.', $running));
			sleep(2);
		}

		dsstats_log_statistics('MAJOR');
	}
}

/**
 * display_version - displays version information
 */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Data Source Statistics Poller, Version $version " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - generic help screen for utilities
 */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: poller_dsstats.php [--force] [--debug]' . PHP_EOL . PHP_EOL;

	print 'Cacti\'s Data Source Statics poller.  This poller will periodically' . PHP_EOL;
	print 'to calculate Data Source statistics for Cacti and works in conjunction' . PHP_EOL;
	print 'with Cacti\'s performance boosting poller as required.' . PHP_EOL . PHP_EOL;

	print 'System Controlled:' . PHP_EOL;
	print '    --type      - The type and subtype of the dsstats process' . PHP_EOL;
	print '    --child     - The thread id of the child process' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '    --force     - Force the execution of a update process' . PHP_EOL;
	print '    --debug     - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param $signo - (int) the signal that was thrown by the interface.
 *
 * @return - null
 */
function sig_handler($signo) {
	global $type, $thread_id;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: DSStats Poller terminated by user', false, 'dsstats');

			/* tell the main poller that we are done */
			if ($type == 'master') {
				set_config_option('dsstats_poller_status', 'terminated - end time:' . date('Y-m-d G:i:s'));
			}

			if (strpos($type, 'master') !== false) {
				dsstats_kill_running_processes();
			}

			unregister_process('dsstats', $type, $thread_id, getmypid());

			exit(1);

			break;

		default:
			/* ignore all other signals */
	}
}
