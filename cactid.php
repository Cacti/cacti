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

/* sig_handler - provides a generic means to catch exceptions to the Cacti log.
   @arg $signo - (int) the signal that was thrown by the interface.
   @returns - null */
function sig_handler($signo) {
	global $config, $hostname;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Cacti Daemon PID[' . getmypid() . '] Terminated on Device[' . gethostname() . ']', true, 'CACTID');
			admin_email(__('Cacti System Warning'), __('WARNING: Cacti Daemon PID[' . getmypid() . '] Terminated on Device[' . gethostname() . ']', true, 'CACTID'));

			exit(1);

			break;

		default:
			/* ignore all other signals */
	}
}

/* let's report all errors */
error_reporting(E_ALL);

/* allow the script to hang around waiting for connections. */
set_time_limit(0);

/* we do not need so much memory */
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

// default values
$hostname   = gethostname();
$debug      = false;
$foreground = false;
$logrecon   = false;

chdir(__DIR__);
include_once('./include/cli_check.php');

/* install signal handlers for Linux/UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

global $config, $hostname, $debug;

// process calling arguments
$options = get_options();

if (isset($options['debug'])) {
	$debug = true;
}

if (isset($options['foreground'])) {
	$foreground = true;
}

// Set the frequency of data collection dynamically
// before loosing our database connection after fork.
// We would not have to do this is PDO supported
// connection cloning as does Perl.
$frequency = read_config_option('cron_interval', true);

// redirect standard error to dev/null
if (DIRECTORY_SEPARATOR != '\\') {
	fclose(STDERR);
	$STDERR = fopen('/dev/null', 'wb');

	// check if cactid daemon is already running
	exec('pgrep -a php | grep cactid.php', $output);

	if (sizeof($output) >= 2) {
		print 'The Cacti Daemon is still running' . PHP_EOL;

		return;
	}
} else {
	fclose(STDERR);
	$STDERR = fopen('null', 'wb');
}

print 'Starting Cacti Daemon ... ';

if (!$foreground) {
	if (function_exists('pcntl_fork')) {
		// Close the database connection
		db_close();

		// Fork the current process to daemonize
		$pid = pcntl_fork();

		if ($pid == -1) {
			// Something went wrong
			print '[FAILED]' . PHP_EOL;

			exit(1);
		}

		if ($pid == 0) {
			// We are the child
		} else {
			cacti_log('NOTE: Cacti Daemon PID[' . getmypid() . '] Started on Device[' . gethostname() . ']');
			admin_email(__('Cacti System Notice'), __('Notice: Cacti Daemon PID[' . getmypid() . '] Started on Device[' . gethostname() . ']', true, 'CACTID'));

			print '[OK]' . PHP_EOL;

			exit(0);
		}
	} else {
		// On Windows we run in foreground mode
		print '[OK]' . PHP_EOL . '[NOTE] This system does not support forking.' . PHP_EOL;
	}
} else {
	print '[OK]' . PHP_EOL . '[NOTE] The Cacti Daemon is running in foreground mode.' . PHP_EOL;
}

sleep(2);

while (true) {
	wait_for_start($frequency);

	db_check_reconnect(false, $logrecon);

	run_poller();

	// Force Cacti to check the service start frequency dynamically
	$frequency = -1;
	$logrecon  = true;
}

function wait_for_start($frequency = -1) {
	$prev_time = -1;
	$i         = 0;

	while (true) {
		if ($frequency <= 0) {
			$frequency = read_config_option('cron_interval', true);

			if (empty($frequency)) {
				$frequency = 300;
			}
		}

		$now    = time();
		$offset = $now % $frequency;

		if ($i % 5 == 0) {
			debug('PrevOS: ' . $prev_time . ', CurrOS: ' . $offset . ', Freq: ' . $frequency);
		}

		if ($prev_time > 0) {
			if ($offset < $prev_time) {
				debug('Time to Run Poller');

				break;
			}
		}

		$prev_time = $offset;
		$i++;

		sleep(1);
	}

	return $frequency;
}

function run_poller() {
	global $config, $debug;

	debug('Cacti Data Collector');

	$command = ' -q ' . CACTI_PATH_BASE . '/poller.php --force' . ($debug ? ' --debug':'');

	debug('Command Line is: ' . $command);

	exec_background(read_config_option('path_php_binary'), $command);
}

function get_options() {
	$parms = $_SERVER['argv'];
	array_shift($parms);

	$options = array();

	if (sizeof($parms)) {
		$shortopts = 'VvHh';

		$longopts = array(
			'foreground',
			'debug',
			'version',
			'help'
		);

		$options = getopt($shortopts, $longopts);

		foreach ($options as $arg => $value) {
			switch($arg) {
				case 'foreground':
				case 'debug':
					break;
				case 'version':
				case 'V':
				case 'v':
					display_version();

					exit(0);
				case 'help':
				case 'H':
				case 'h':
					display_help();

					exit(0);

				default:
					print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
					display_help();

					exit(1);
			}
		}
	}

	return $options;
}

function debug($string) {
	global $debug;

	if ($debug) {
		$output = 'DEBUG: ' . trim($string);

		print $output . PHP_EOL;
	}
}

function display_version() {
	global $config;

	$version = get_cacti_cli_version();
	print 'The Cacti Daemon (cactid), Version ' . $version . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/*	display_help - displays the usage of the function */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: cactid.php [ --foreground ] [ --debug ]' . PHP_EOL . PHP_EOL;
	print 'Daemon for Cacti data collection, otherwise known as cactid.' . PHP_EOL;
	print 'optional:' . PHP_EOL;
	print '  --foreground       Run cactid in foreground mode, otherwise this is a forking daemon.' . PHP_EOL;
	print '  --debug            Used for debugging in --foreground mode.' . PHP_EOL;
}
