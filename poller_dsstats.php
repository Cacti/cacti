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
require_once($config['base_path'] . '/lib/rrd.php');
require_once($config['base_path'] . '/lib/dsstats.php');

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	print "Cacti Data Source Staitistcs Poller, Version $version " . COPYRIGHT_YEARS . "\n";
}

/* display_help - generic help screen for utilities
   @returns - null */
function display_help () {
	display_version();

	print "\nusage: poller_dsstats.php [--force] [--debug]\n\n";
	print "Cacti's Data Source Statics poller.  This poller will periodically\n";
	print "calculate Data Source statistics for Cacti and works in conjunction\n";
	print "with Cacti's performance boosting popller as required.\n\n";
	print "Optional:\n";
	print "    --force     - Force the execution of a update process\n";
	print "    --debug     - Display verbose output during execution\n\n";
}

/* sig_handler - provides a generic means to catch exceptions to the Cacti log.
   @arg $signo - (int) the signal that was thrown by the interface.
   @returns - null */
function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: DSStats Poller terminated by user', false, 'dsstats');

			/* tell the main poller that we are done */
			set_config_option('dsstats_poller_status', 'terminated - end time:' . date('Y-m-d G:i:s'));

			exit;
			break;
		default:
			/* ignore all other signals */
	}
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug          = false;
$forcerun       = false;
$forcerun_maint = false;

global $total_system_time, $total_user_time, $total_real_time;

$total_system_time = 0;
$total_user_time   = 0;
$total_real_time   = 0;

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
		case '--version':
		case '-v':
		case '-V':
			display_version();
			exit;
		case '--help':
		case '-h':
		case '-H':
			display_help();
			exit;
		default:
			print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
			display_help();
			exit;
		}
	}
}

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

/* only run if enabled, or forced */
if (read_config_option('dsstats_enable') == 'on' || $forcerun) {
	/* read some important settings relative to timing from the database */
	$major_time     = date('H:i:s', strtotime(read_config_option('dsstats_major_update_time')));
	$daily_interval = read_config_option('dsstats_daily_interval');
	$hourly_window  = date('Y-m-d H:i:s', time() - (read_config_option('dsstats_hourly_duration') * 60));

	/* check to see when the daily averages were updated last */
	$last_run_daily = read_config_option('dsstats_last_daily_run_time');
	$last_run_major = read_config_option('dsstats_last_major_run_time');

	/* remove old records from the cache first */
	if (db_fetch_cell_prepared('SELECT count(*) FROM data_source_stats_hourly_cache WHERE time < ?', array($hourly_window))) {
		db_execute_prepared('DELETE FROM data_source_stats_hourly_cache WHERE time < ?', array($hourly_window));
	}

	/* store the current averages into the hourly table */
	db_execute("INSERT INTO data_source_stats_hourly
		(local_data_id, rrd_name, average, peak)
		(SELECT local_data_id, rrd_name, AVG(`value`), MAX(`value`)
		 FROM data_source_stats_hourly_cache 
		 WHERE `value` IS NOT NULL
		 GROUP BY local_data_id, rrd_name
		)
		ON DUPLICATE KEY UPDATE average=VALUES(average), peak=VALUES(peak)");

	log_dsstats_statistics('HOURLY');

	/* see if boost is active or not */
	$boost_active = read_config_option('boost_rrd_update_enable');

	/* next let's see if it's time to update the daily interval */
	$current_time = time();
	if ($boost_active == 'on') {
		/* boost will spawn the collector */
	} else {
		if ($daily_interval == 'boost' || $boost_active == 'on') {
			cacti_log("WARNING: Daily update interval set to 'boost' and Boost Plugin Not Enabled, reseting to default of 1 hour", false, 'DSSTATS');
			set_config_option('dsstats_daily_interval', 60);
			$daily_interval = 60;
		}

		/* determine if it's time to determine hourly averages */
		if ($last_run_daily == '') {
			/* since the poller has never run before, let's fake it out */
			set_config_option('dsstats_last_daily_run_time', date('Y-m-d G:i:s', $current_time));
		}

		/* if it's time to update daily statistics, do so now */
		if (($last_run_daily != '' && ((strtotime($last_run_daily) + ($daily_interval * 60)) < $current_time)) || $forcerun) {
			/* run the daily stats */
			dsstats_get_and_store_ds_avgpeak_values('daily');
			log_dsstats_statistics('DAILY');
			set_config_option('dsstats_last_daily_run_time', date('Y-m-d G:i:s', $current_time));
		}
	}

	/* lastly, let's see if it's time to run the major stats */
	if ($last_run_major == '') {
		/* since the poller has never run before, let's fake it out */
		set_config_option('dsstats_last_major_run_time', date('Y-m-d G:i:s', $current_time));
	} else {
		$last_major_day = date('Y-m-d', strtotime($last_run_major));
		$next_major_day = strtotime($last_major_day . ' ' . $major_time) + 86400;
	}

	/* if its time to run major statistics, do so now */
	if (($last_run_major != '' && ($next_major_day < $current_time)) || $forcerun) {
		/* run the major stats, log first to keep other processes from running */
		set_config_option('dsstats_last_major_run_time', date('Y-m-d G:i:s', $current_time));
		dsstats_get_and_store_ds_avgpeak_values('weekly');
		dsstats_get_and_store_ds_avgpeak_values('monthly');
		dsstats_get_and_store_ds_avgpeak_values('yearly');
		log_dsstats_statistics('MAJOR');
	}
}

dsstats_debug('Polling Ending');
