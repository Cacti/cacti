#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
require_once($config['base_path'] . '/lib/rrd.php');
require_once($config['base_path'] . '/lib/reports.php');

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	print "Cacti Reporting Poller, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/** display_help - generic help screen for utilities
 * @return		 - null */
function display_help () {
	display_version();

	print "\nusage: poller_reports.php [--force] [--debug]\n\n";
	print "Cacti's graphical reporting poller.  This poller will create and distribute\n";
	print "email reports to recipients based upon the schedules for those reports.\n\n";
	print "Optional:\n";
	print "    --force     - Force all Reports to be sent\n";
	print "    --debug     - Display verbose output during execution\n\n";
}

/** sig_handler - provides a generic means to catch exceptions to the Cacti log.
 * @arg $signo 	- (int) the signal that was thrown by the interface.
 * @return 		- null */
function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			reports_log('WARNING: Reports Poller terminated by user', false, 'REPORTS TRACE', POLLER_VERBOSITY_LOW);

			exit(1);
			break;
		default:
			/* ignore all other signals */
	}
}

global $current_user;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;
$force = false;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-f':
			case '--force':
				$force = true;
				break;
			case '-d':
			case '--debug':
				$debug = true;
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
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit(1);
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

$t = time();
$number_sent = 0;

if (!$force) {
	/* silently end if the registered process is still running, or process table missing */
	if (!register_process_start('reports', 'master', 0, read_config_option('reports_timeout'))) {
		exit(0);
	}
}

/* cacti upgrading */
if (!db_table_exists('reports')) {
	exit(0);
}

/* fetch all enabled reports that have a start time in the past */
if (!$force) {
	$reports = db_fetch_assoc_prepared('SELECT * FROM reports WHERE mailtime < ? AND enabled="on"', array($t));
} else {
	$reports = db_fetch_assoc("SELECT * FROM reports WHERE enabled='on'");
}
reports_log('Cacti Reports reports found: ' . cacti_sizeof($reports), true, 'REPORTS', POLLER_VERBOSITY_MEDIUM);

/* execute each of those reports */
if (cacti_sizeof($reports)) {
	foreach ($reports as $report) {
		reports_log('Reports processing report: ' . $report['name'], true, 'REPORTS', POLLER_VERBOSITY_MEDIUM);
		$current_user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array($report['user_id']));
		if (isset($report['email'])) {
			generate_report($report, false, 'poller');
			$number_sent++;
		}
	}

	/* record the end time */
	$end = microtime(true);

	/* log statistics */
	$reports_stats = sprintf('Time:%01.4f Reports:%s', $end - $start, $number_sent);
	reports_log('REPORTS STATS: ' . $reports_stats, true, 'REPORTS', POLLER_VERBOSITY_LOW);
	db_execute_prepared('REPLACE INTO settings (name, value) VALUES ("stats_reports", ?)', array($reports_stats));
}

if (!$force) {
	unregister_process('reports', 'master', 0);
}

exit(0);

