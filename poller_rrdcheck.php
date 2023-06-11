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
require_once(CACTI_PATH_LIBRARY . '/rrdcheck.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug          = false;
$forcerun       = false;
$type           = 'pmaster';
$thread_id      = 0;

global $rrd_files, $total_system, $total_user, $total_real, $total_dsses;
global $user_time, $system_time, $real_time;

$total_system = 0;
$total_user   = 0;
$total_real   = 0;
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

/* send a gentle message to the log and stdout */
rrdcheck_debug('Polling Starting');

/* silently end if the registered process is still running */
if (!$forcerun) {
	if (!register_process_start('rrdcheck', $type, $thread_id, read_config_option('rrdcheck_timeout'))) {
		exit(0);
	}
}

// Collect data as determined by the type
switch ($type) {
	case 'pmaster':
		if (read_config_option('rrdcheck_enable') == 'on' || $forcerun) {
			rrdcheck_master_handler($forcerun);
		}

		break;
	case 'bmaster': // Launched at the end of boost
		rrdcheck_launch_children($type);

		/* Wait for all processes to continue */
		while ($running = rrdcheck_processes_running($type)) {
			rrdcheck_debug(sprintf('%s Processes Running, Sleeping for 2 seconds.', $running));
			sleep(2);
		}

		break;
	case 'child':  // Launched by the master process
	case 'bchild': // Launched by the boost process
		$child_start = microtime(true);

		do_rrdcheck($thread_id);

		$total_time = microtime(true) - $child_start;

		rrdcheck_log_child_stats($type, $thread_id, $total_time);

		break;
}

rrdcheck_debug('Polling Ending');

if (!$forcerun) {
	unregister_process('rrdcheck', $type, $thread_id);
}

exit(0);

function rrdcheck_master_handler($forcerun) {
	global $type;

	/* read some important settings relative to timing from the database */
	$run_interval = read_config_option('rrdcheck_interval');

	$last_run = read_config_option('rrdcheck_last_run_time');

	/* see if boost is active or not */
	$boost_active = read_config_option('boost_rrd_update_enable');

	/* next let's see if it's time to update the interval */
	$current_time = time();

	if ($boost_active == 'on') {
		// boost will spawn the collector
		rrdcheck_debug('Skipping Periodic Rollup - Boost will handle the Periodic Roll-up Cycle');
	} else {
		if ($run_interval == 'boost') {
			cacti_log("WARNING: RRDcheck interval set to 'boost' and boost not enabled, reseting to default of 4 hours", false, 'RRDCHECK');

			set_config_option('rrdcheck_interval', 240);

			$run_interval = 240;
		}

		// determine if it's time to determine hourly averages
		if (empty($last_run)) {
			// since the poller has never run before, let's fake it out
			set_config_option('rrdcheck_last_run_time', date('Y-m-d G:i:s', $current_time));
		}

		// if it's time to check, do so now
		if ((!empty($last_run) && ((strtotime($last_run) + ($run_interval * 60)) < $current_time)) || $forcerun) {
			set_config_option('rrdcheck_last_run_time', date('Y-m-d G:i:s', $current_time));

			rrdcheck_launch_children($type);

			// Wait for all processes to continue
			while ($running = rrdcheck_processes_running($type)) {
				rrdcheck_debug(sprintf('%s Processes Running, Sleeping for 2 seconds.', $running));
				sleep(2);
			}

			// delete old data
			db_execute('DELETE FROM rrdcheck WHERE test_date < DATE_SUB(NOW(), INTERVAL 7 DAY)');

			rrdcheck_log_statistics('HOURLY');
		}
	}
}

/**
 * display_version - displays version information
 */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti RRD Check Poller, Version $version " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - generic help screen for utilities
 */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: poller_rrdcheck.php [--force] [--debug]' . PHP_EOL . PHP_EOL;

	print 'Cacti\'s RRD check poller.  This poller will periodically' . PHP_EOL;
	print 'check rrdfiles and saved datasources and works in conjunction' . PHP_EOL;
	print 'with Cacti\'s performance boosting poller as required.' . PHP_EOL . PHP_EOL;

	print 'System Controlled:' . PHP_EOL;
	print '    --type      - The type and subtype of the rrdcheck process' . PHP_EOL;
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
			cacti_log('WARNING: rrdcheck Poller terminated by user', false, 'RRDCHECK');

			/* tell the main poller that we are done */
			if ($type == 'master') {
				set_config_option('rrdcheck_poller_status', 'terminated - end time:' . date('Y-m-d G:i:s'));
			}

			if (strpos($type, 'master') !== false) {
				rrdcheck_kill_running_processes();
			}

			unregister_process('rrdcheck', $type, $thread_id, getmypid());

			exit(1);

			break;

		default:
			/* ignore all other signals */
	}
}
