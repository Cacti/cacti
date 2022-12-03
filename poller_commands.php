#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
ini_set('max_runtime', '-1');
ini_set('memory_limit', '-1');

/* we are not talking to the browser */
define('MAX_RECACHE_RUNTIME', 1800);

require(__DIR__ . '/include/cli_check.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/api_tree.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/html_form_template.php');
require_once($config['base_path'] . '/lib/ping.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/rrd.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/sort.php');
require_once($config['base_path'] . '/lib/template.php');
require_once($config['base_path'] . '/lib/utility.php');

$poller_id = $config['poller_id'];
$debug     = false;
$host_id   = false;
$forcerun  = false;
$type      = 'master';
$threads   = read_config_option('commands_processes');

global $poller_db_cnn_id, $remote_db_cnn_id, $type, $host_id, $poller_id;

if ($config['poller_id'] > 1 && $config['connection'] == 'online') {
	$poller_db_cnn_id = $remote_db_cnn_id;
} else {
	$poller_db_cnn_id = false;
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
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
				exit(0);
			case '-H':
			case '--help':
				display_help();
				exit(0);
			case '--poller':
			case '-p':
				$poller_id = $value;
				break;
			case '--child':
			case '-c':
				$host_id = $value;
				$type    = 'child';
				break;
			case '-t':
			case '--threads':
				$threads = $value;
				break;
			case '--debug':
			case '-d':
				$debug = true;
				break;
			default:
				print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}
}

if ($debug) {
	$verbosity = POLLER_VERBOSITY_LOW;
} else {
	$verbosity = POLLER_VERBOSITY_MEDIUM;
}

/**
 * Types include
 *
 * master  - the main process launched from the Cacti main poller and will launch child processes
 * child   - a child of the master process from the 'master'
 *
 */

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* Record Start Time */
$start = microtime(true);

/* send a gentle message to the log and stdout */
commands_debug('Polling Starting');

if ($host_id === false) {
	$hosts = array_rekey(
		db_fetch_assoc_prepared('SELECT DISTINCT SUBSTRING_INDEX(command, ":", 1) AS host_id
			FROM poller_command
			WHERE poller_id = ?',
			array($poller_id), true, $poller_db_cnn_id),
		'host_id', 'host_id'
	);

	if (cacti_sizeof($hosts)) {
		/**
		 * Register the master process
		 */
		if (!register_process_start('commands', 'master', $poller_id, read_config_option('commands_timeout'))) {
			exit(0);
		}

		// Master processing
		commands_master_handler($forcerun, $hosts, $threads);

		/* take time to log performance data */
		$recache = microtime(true);

		$recache_stats = sprintf('Poller:%s RecacheTime:%01.4f DevicesRecached:%s',	$poller_id, round($recache - $start, 4), cacti_sizeof($hosts));

		if (cacti_sizeof($hosts)) {
			cacti_log('STATS: ' . $recache_stats, true, 'RECACHE');
		}

		/* insert poller stats into the settings table */
		db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)',
			array('stats_recache_' . $poller_id, $recache_stats), true, $poller_db_cnn_id);

		unregister_process('commands', 'master', $poller_id);
	} else {
		cacti_log('NOTE: No Poller Commands found for processing', true, 'PCOMMAND', $verbosity);
	}
} else {
	/**
	 * Register the child process
	 */
	if (!register_process_start('commands', 'child', $host_id + 1000, read_config_option('commands_timeout'))) {
		exit(0);
	}

	$last_host_id   = 0;
	$first_host     = true;

	/**
	 * We will only remove records earlier than this date
	 */
	$max_updated = db_fetch_cell_prepared('SELECT MAX(UNIX_TIMESTAMP(last_updated))
		FROM poller_command
		WHERE poller_id = ?
		AND SUBSTRING_INDEX(command, ":", 1) = ?',
		array($poller_id, $host_id), '', true, $poller_db_cnn_id);

	/**
	 * Get the poller command records for the host
	 */
	$poller_commands = db_fetch_assoc_prepared('SELECT action, command,
		SUBSTRING_INDEX(command, ":", 1) AS host_id
		FROM poller_command
		WHERE poller_id = ?
		AND last_updated <= FROM_UNIXTIME(?)
		AND SUBSTRING_INDEX(command, ":", 1) = ?',
		array($poller_id, $max_updated, $host_id), true, $poller_db_cnn_id);

	if (cacti_sizeof($poller_commands)) {
		foreach ($poller_commands as $command) {
			switch ($command['action']) {
			case POLLER_COMMAND_REINDEX:
				list($device_id, $data_query_id) = explode(':', $command['command']);

				if ($last_host_id != $device_id) {
					$last_host_id = $device_id;
					$first_host = true;
				} else {
					$first_host = false;
				}

				if ($first_host) {
					cacti_log("Device[$device_id] NOTE: Recache Event Detected for Device", true, 'PCOMMAND');
				}

				cacti_log("Device[$device_id] DQ[$data_query_id] RECACHE: Recache for Device started.", true, 'PCOMMAND', $verbosity);
				run_data_query($device_id, $data_query_id);
				cacti_log("Device[$device_id] DQ[$data_query_id] RECACHE: Recached successfully.", true, 'PCOMMAND', $verbosity);

				break;
			case POLLER_COMMAND_PURGE:
				$device_id = $command['command'];

				api_device_purge_from_remote($device_id, $poller_id);
				cacti_log("Device[$device_id] PURGE: Purged successfully.", true, 'PCOMMAND', $verbosity);

				break;
			default:
				cacti_log('ERROR: Unknown poller command issued', true, 'PCOMMAND');
			}

			/* record current_time */
			$current = microtime(true);

			/* end if runtime has been exceeded */
			if (($current-$start) > MAX_RECACHE_RUNTIME) {
				cacti_log("ERROR: Poller Command processing timed out after processing '$command'", true, 'PCOMMAND');
				break;
			}
		}

		db_execute_prepared('DELETE FROM poller_command
			WHERE poller_id = ?
			AND SUBSTRING_INDEX(command, ":", 1) = ?
			AND last_updated <= FROM_UNIXTIME(?)',
			array($poller_id, $host_id, $max_updated), true, $poller_db_cnn_id);
	}

	unregister_process('commands', 'child', $host_id + 1000);
}

function commands_master_handler($forcerun, &$hosts, $threads) {
	commands_debug("There are " . cacti_sizeof($hosts) . " to reindex");

	foreach($hosts as $id) {
		/* run the daily stats */
		commands_debug("Launching Host ID $id");
		commands_launch_child($id);

		/* Wait for if there are 50 processes running */
		while (true) {
			$running = commands_processes_running();

			if ($running >= $threads) {
				commands_debug(sprintf('%s Processes Running, Sleeping for 2 seconds.', $running));
				sleep(2);
			} else {
				commands_debug(sprintf('%s Processes Running, Launching more processes.', $running));
				usleep(500000);
				break;
			}
		}
	}

	$starting = true;

	while (true) {
		if ($starting) {
			sleep(5);
			$starting = false;
		}

		$running = commands_processes_running();

		if ($running > 0) {
			commands_debug(sprintf('%s Processes Running, Sleeping for 2 seconds.', $running));
			sleep(2);
		} else {
			break;
		}
	}
}

/**
 * commands_launch_child - this function will launch collector children based upon
 *   the maximum number of threads and the process type
 *
 * @param  (int)  $host_id - The Cacti host_id
 *
 * @return (void)
 */
function commands_launch_child($host_id) {
	global $config, $seebug;

	$php_binary = read_config_option('path_php_binary');

	commands_debug(sprintf('Launching Commands Process Number %s for Type %s', $host_id, 'child'));

	cacti_log(sprintf('NOTE: Launching Commands Process Number %s for Type %s', $host_id, 'child'), false, 'CLEANUP', POLLER_VERBOSITY_MEDIUM);

	exec_background($php_binary, $config['base_path'] . "/poller_commands.php --child=$host_id" . ($seebug ? ' --debug':''));
}

/**
 * commands_processes_running - given a type, determine the number
 *   of sub-type or children that are currently running
 *
 * @return (int) The number of running processes
 */
function commands_processes_running() {
	$running = db_fetch_cell('SELECT COUNT(*)
		FROM processes
		WHERE tasktype = "commands"
		AND taskname = "child"');

	if ($running == 0) {
		return 0;
	}

	return $running;
}

/**
 * commands_debug - this simple routine prints a standard message to the console
 *   when running in debug mode.
 *
 * @param  (string)  $message - The message to display
 *
 * @return (void)
 */
function commands_debug($message) {
	global $seebug;

	if ($seebug) {
		print 'COMMANDS: ' . $message . PHP_EOL;
	}
}

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param  (int) $signo - the signal that was thrown by the interface.
 *
 * @return (void)
 */
function sig_handler($signo) {
	global $type, $host_id, $poller_id;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: RRDfile Cleanup Poller terminated by user', false, 'CLEANUP');

			if (strpos($type, 'master') !== false) {
				commands_kill_running_processes();
			}

			if ($type == 'master') {
				unregister_process('commands', $type, $poller_id, getmypid());
			} else {
				unregister_process('commands', $type, $host_id + 1000, getmypid());
			}

			exit(1);
			break;
		default:
			/* ignore all other signals */
	}
}

/**
 * commands_kill_running_processes - this function is part of an interrupt
 *   handler to kill children processes when the parent is killed
 *
 * @return (void)
 */
function commands_kill_running_processes() {
    global $type;

    $processes = db_fetch_assoc_prepared('SELECT *
        FROM processes
        WHERE tasktype = "commands"
        AND taskname IN ("child")
        AND pid != ?',
        array(getmypid()));

    if (cacti_sizeof($processes)) {
        foreach($processes as $p) {
            cacti_log(sprintf('WARNING: Killing Commands %s PID %d due to another due to signal or overrun.', ucfirst($p['taskname']), $p['pid']), false, 'CLEANUP');
            posix_kill($p['pid'], SIGTERM);

            unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $p['pid']);
        }
    }
}

/**
 * display_version - displays version information
 *
 * @return (void)
 */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Poller Commands Poller, Version $version " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays help information
 *
 * @return (void)
 */
function display_help () {
	display_version();

	print PHP_EOL;
	print 'usage: poller_commands.php [--poller=ID] [--debug]' . PHP_EOL . PHP_EOL;
	print 'Cacti\'s Commands Poller.  This poller can receive specifically crafted commands from' . PHP_EOL;
	print 'either the Cacti UI, or from the main poller, and then run them in the background.' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '  --poller=ID - The poller to run as.  Defaults to the system poller' . PHP_EOL;
	print '  --threads=N - Override the System Processes setting and use N processes' . PHP_EOL;
	print '  --debug     - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}

