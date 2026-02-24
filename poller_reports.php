#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
require_once(CACTI_PATH_LIBRARY . '/api_scheduler.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');
require_once(CACTI_PATH_LIBRARY . '/reports.php');

global $current_user;

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$debug     = false;
$force     = false;
$report_id = false;
$queue_id  = false;

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--report-id':
				$report_id = intval($value);

				break;
			case '--queue-id':
				$queue_id = intval($value);

				break;
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

// install signal handlers for UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

// take time and log performance data
$start = microtime(true);

// let's give this script lot of time to run for ever
ini_set('max_execution_time', '0');

// cacti upgrading
if (!db_table_exists('reports')) {
	exit(0);
}

if ($report_id === false) {
	$number_sent = 0;

	if (!$force) {
		$timeout = intval(read_config_option('scheduler_timeout'));

		if (empty($timeout)) {
			$timeout = 300;
		}

		// silently end if the registered process is still running, or process table missing
		if (!register_process_start('reports', 'master', 0, $timeout)) {
			exit(0);
		}
	}

	// fetch all enabled reports that have a start time in the past
	$reports = db_fetch_assoc("SELECT * FROM reports WHERE enabled='on'");

	reports_log('Cacti Reports reports found: ' . cacti_sizeof($reports), true, 'REPORTS', POLLER_VERBOSITY_MEDIUM);

	$queued = [];

	$command  = read_config_option('path_php_binary');
	$command .= ' ' . CACTI_PATH_BASE . '/poller_reports.php';

	// execute each of those reports
	if (cacti_sizeof($reports)) {
		foreach ($reports as $report) {
			if (api_scheduler_is_time_to_start($report, 'reports') || $force) {
				reports_log('Reports scheduling report: ' . $report['name'], true, 'REPORTS', POLLER_VERBOSITY_MEDIUM);

				$id     = $report['id'];
				$name   = $report['name'];
				$notify = $report['notify_list'];
				$from   = [];

				if (isset($report['from_email']) && $report['from_email'] != '') {
					$from_email = $report['from_email'];
				} else {
					$from_email = read_config_option('settings_from_email');
				}

				if (isset($report['from_name']) && $report['from_name'] != '') {
					$from_name = $report['from_name'];
				} else {
					$from_name = read_config_option('settings_from_name');
				}

				if ($from_email != '' && $from_name != '') {
					$from['email'] = $from_email;
					$from['name']  = $from_name;
				}

				$to_emails  = array_map('trim', explode(',', $report['email']));
				$bcc_emails = array_map('trim', explode(',', $report['bcc']));

				if (isset($report['reply_to'])) {
					$reply_to = $report['reply_to'];
				} else {
					$reply_to = '';
				}

				$notification = [];

				if (cacti_sizeof($to_emails) || cacti_sizeof($bcc_emails)) {
					$notification['email']['to_email']  = $to_emails;
					$notification['email']['bcc_email'] = $bcc_emails;
					$notification['email']['reply_to']  = $reply_to;
					$notification['email']['from']      = $from;
				}

				if ($notify > 0) {
					$notification['notification_list']['id']       = $notify;
					$notification['notification_list']['reply_to'] = $reply_to;
					$notification['notification_list']['from']     = $from;
				}

				$queued[] = reports_queue($name, 1, 'reports', $id, $command, $notification);
			}
		}

		$number_sent = cacti_sizeof($queued);

		if (cacti_sizeof($queued)) {
			foreach ($queued as $qid) {
				reports_run($qid);
			}
		}

		// record the end time
		$end = microtime(true);

		// log statistics
		$reports_stats = sprintf('Time:%01.4f Reports:%s', $end - $start, $number_sent);
		reports_log('REPORTS STATS: ' . $reports_stats, true, 'REPORTS', POLLER_VERBOSITY_LOW);
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES ("stats_reports", ?)', [$reports_stats]);
	}

	if (!$force) {
		unregister_process('reports', 'master', 0);
	}
} else {
	$timeout = intval(read_config_option('scheduler_timeout'));

	if (empty($timeout)) {
		$timeout = 300;
	}

	if (!register_process_start('reports', 'child', $report_id, $timeout)) {
		exit(0);
	}

	$report = db_fetch_row_prepared('SELECT *
		FROM reports
		WHERE id = ?',
		[$report_id]);

	if (cacti_sizeof($report)) {
		reports_log('Reports processing report: ' . $report['name'], true, 'REPORTS');

		$current_user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', [$report['user_id']]);

		if (isset($report['email'])) {
			db_execute_prepared('UPDATE reports
				SET last_started = ?
				WHERE id = ?',
				[date('Y-m-d H:i:s'), $report['id']]);

			generate_report($queue_id, $report, false);
		}
	}

	unregister_process('reports', 'child', $report_id);
}

exit(0);

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param int $signo the signal that was thrown by the interface.
 *
 * @return void
 */
function sig_handler(int $signo) : void {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			reports_log('WARNING: Reports Poller terminated by user', false, 'REPORTS TRACE', POLLER_VERBOSITY_LOW);

			exit(1);
		default:
			// ignore all other signals
	}
}

/**
 * display_version - displays version information
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Reporting Poller, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/**
 * display_help - generic help screen for utilities
 */
function display_help() : void {
	display_version();

	print "\nusage: poller_reports.php [--force] [--debug]\n\n";
	print "Cacti's graphical reporting poller.  This poller will create and distribute\n";
	print "email reports to recipients based upon the schedules for those reports.\n\n";
	print "Optional:\n";
	print "    --force     - Force all Reports to be sent\n";
	print "    --debug     - Display verbose output during execution\n\n";
}
