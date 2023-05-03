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

require(__DIR__ . '/../include/cli_check.php');

require_once(CACTI_PATH_LIBRARY . '/utility.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');


/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
        db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

/* system controlled parameters */
$type              = 'rmaster';
$thread_id         = 0;

/* mandatory parameters */
$start_time        = false;
$end_time          = false;

/* optional parameters for host selection */
$debug            = false;
$host_id          = false;
$host_template_id = false;
$data_template_id = false;

/* optional for threading and verbose display */
$threads           = 5;

/* optional for force handing and resume */
$forcerun          = false;

foreach ($parms as $parameter) {
	if (strpos($parameter, '=')) {
		list($arg, $value) = explode('=', $parameter);
	} else {
		$arg   = $parameter;
		$value = '';
	}

	switch ($arg) {
		case '--host-id':
			$host_id = trim($value);

			if (!is_numeric($host_id)) {
				print 'ERROR: You must supply a valid Device Id to run this script!' . PHP_EOL;

				exit(1);
			}
			break;

		case '--host-template-id':
			$host_template_id = trim($value);

			if (!is_numeric($host_template_id)) {
				print 'ERROR: You must supply a valid Device Template Id to run this script!' . PHP_EOL;

				exit(1);
			}
			break;

		case '--data-template-id':
			$data_template_id = trim($value);

			if (!is_numeric($data_template_id)) {
				print 'ERROR: You must supply a valid Data Template Id to run this script!' . PHP_EOL;

				exit(1);
			}
			break;

		case '--type':
			$type = $value;
			break;

		case '--threads':
			if (!is_numeric(trim($value))) {
				print 'ERROR: You must supply a valid Number of Treads or skip this parametr for default value (' . $threads . ')' . PHP_EOL;

				exit(1);
			}
			$threads = $value;
			break;

		case '--child':
			$thread_id = $value;
			break;

		case '--force':
			$forcerun = true;
			break;

		case '-d':
		case '--debug':
			$debug = true;
			break;

		case '-h':
		case '-H':
		case '--help':
			display_help();

			exit;
		case '-v':
		case '-V':
		case '--version':
			display_version();

			exit;
		default:
			print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;

			display_help();

			exit;
	}
}

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* take time and log performance data */
$start = microtime(true);

/* set new timeout and memory settings */
ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

$sql_where = '';
$params    = array();

if ($host_id > 0) {
        $sql_where = ' AND h.id = ?';
        $params[]  = $host_id;
}

if ($host_template_id > 0) {
        $sql_where .= ' AND h.host_template_id = ?';
        $params[] = $host_template_id;
}

/* issue warnings and start message if applicable */
print 'WARNING: Do not interrupt this script.  Rebuilding the Push out hosts/Poller Cache can take quite some time' . PHP_EOL;

/* send a gentle message to the log and stdout */
pushout_debug('Push out hosts starting');

/* silently end if the registered process is still running  */
if (!$forcerun) {
	if (!register_process_start('pushout', $type, $thread_id, 86400)) {
		exit(0);
	}
}

/* Collect data as determined by the type */
switch ($type) {
	case 'rmaster':

		pushout_master_handler($forcerun, $host_id, $host_template_id, $data_template_id, $threads);

		unregister_process('pushout', 'rmaster', 0);

		break;
	case 'child':  /* Launched by the rmaster process */

		$child_start = microtime(true);

		$sql_where  = '';
		$sql_params = array();

		if ($host_id !== false) {
			$sql_where .= 'AND id = ?';
			$sql_params[] = $host_id;
		}

		if ($host_template_id !== false) {
			$sql_where .= 'AND host_template_id = ?';
			$sql_params[] = $host_template_id;
		}

		$rows = db_fetch_cell_prepared("SELECT count(id) FROM host WHERE disabled='' " . $sql_where, $sql_params);

		$hosts_per_process = ceil($rows/$threads);

		$sql_where .= ' GROUP BY h.id ORDER BY h.id LIMIT ' . (($thread_id-1)*$hosts_per_process) . ',' . $hosts_per_process;

		$rows = db_fetch_assoc_prepared("SELECT h.id AS id, COUNT(dl.id) AS dl_count FROM host AS h
			LEFT JOIN data_local AS dl ON h.id=dl.host_id WHERE h.disabled='' " . $sql_where, $sql_params);

		cacti_log(sprintf('Child Started Process %s with %d hosts, from: %d', $thread_id, $hosts_per_process, ($thread_id-1)*$hosts_per_process), true, 'PUSHOUT');

		foreach ($rows as $row) {

			if (!$debug) {
				print '.';
			}

			if ($row['dl_count'] > 0) {
				push_out_host($row['id'], 0, $data_template_id);
			} else {
				db_execute_prepared('DELETE FROM poller_item WHERE host_id = ?', array($row['id']));
			}
		}

		$total_time = microtime(true) - $child_start;

		unregister_process('pushout', 'child', $thread_id);

		break;
}

pushout_debug('Polling Ending');

exit(0);


function pushout_master_handler($forcerun, $host_id, $host_template_id, $data_template_id, $threads) {
	global $type;

	$sql_where  = "";
	$sql_params = array();

	if ($host_id !== false) {
		$sql_where .= 'AND id = ?';
		$sql_params[] = $host_id;
	}

	if ($host_template_id !== false) {
		$sql_where .= 'AND host_template_id = ?';
		$sql_params[] = $host_template_id;
	}

	$rows = db_fetch_cell_prepared("SELECT count(id) FROM host WHERE disabled = '' " . $sql_where, $sql_params);

	if ($rows == 0) {
		print 'WARNING: There are no hosts to process' . PHP_EOL;;

		return false;
	}

	$hosts_per_process = ceil($rows/$threads);

	print "There are $rows hosts, $threads threads and $hosts_per_process hosts to process per thread" . PHP_EOL;

	$h_done = 0;

	for ($thread_id = 1; $h_done < $rows; $thread_id++) {

		pushout_debug("Launching Process ID $thread_id");

		pushout_launch_child($thread_id, $threads);
		$h_done += $hosts_per_process;
	}

	$starting = true;

	while (true) {
		if ($starting) {

			sleep(5);
			$starting = false;
		}

		$running = pushout_processes_running();

		if ($running > 0) {
			pushout_debug(sprintf('%s Processes Running, keeping for 2 seconds.', $running));
			sleep(2);
		} else {
			break;
		}
	}

	return true;
}

/**
 * pushout_launch_child - this function will launch collector children based upon
 *   the maximum number of threads and the process type
 *
 * @param $thread_id  (int)    The Thread id to launch
 *
 * @return - NULL
 */
function pushout_launch_child($thread_id, $threads) {
	global $config, $debug, $host_template_id, $data_template_id;

	$php_binary = read_config_option('path_php_binary');

	pushout_debug(sprintf('Launching Push out hosts Process Number %s for Type %s', $thread_id, 'child'));

	cacti_log(sprintf('NOTE: Launching Push out hosts Number %s for Type %s', $thread_id, 'child'), true, 'PUSHOUT', POLLER_VERBOSITY_MEDIUM);

	exec_background($php_binary, CACTI_PATH_CLI . "/push_out_hosts.php --type=child --threads=$threads --child=$thread_id " . ($debug ? " --debug":"") . ($host_template_id ? " --host-template-id=$host_template_id":"") . ($data_template_id ? " --data-template-id=$data_template_id":""));

}

/**
 * pushout_processes_running - given a type, determine the number
 *   of sub-type or children that are currently running
 *
 * @return - (int) The number of running processes
 */
function pushout_processes_running() {
	$running = db_fetch_cell('SELECT COUNT(*)
		FROM processes
		WHERE tasktype = "pushout"
		AND taskname = "child"');

	if ($running == 0) {
		return 0;
	}

	return $running;
}

/**
 * pushout_debug - this simple routine prints a standard message to the console
 *   when running in debug mode.
 *
 * @param $message - (string) The message to display
 *
 * @return - NULL
 */
function pushout_debug($message) {
	global $debug;

	if ($debug) {
		print 'PUSHOUT: ' . $message . PHP_EOL;
	}
}

/**
 * display_version - displays version information
 */
function display_version() {
	print 'Cacti Push out hosts/repopulate poller cache Tool, Version ' . CACTI_VERSION . ' ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - generic help screen for utilities
 */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: push_out_hosts.php [--host-id=N] [--host-template-id=N] [--data-template-id=N] [--debug]' . PHP_EOL . PHP_EOL;

	print 'Cacti\'s repopulate poller cache tool.  This CLI script will ' . PHP_EOL;
	print 'repopulate poller cache for all or specified hosts.' . PHP_EOL . PHP_EOL;
	print 'This utility will run in parallel with the given number of threads,' . PHP_EOL;

        print 'Optional:' . PHP_EOL;
	print ' --threads=N           - The number of threads to use to repopulate, default = 5' . PHP_EOL;
        print ' --host-id=N           - Run for a specific Device' . PHP_EOL;
        print ' --host-template-id=N  - Run for a specific Device Template' . PHP_EOL;
        print ' --data-template-id=N  - Run for a specific Data Template' . PHP_EOL;
        print ' --debug               - Display verbose output during execution' . PHP_EOL . PHP_EOL;

	print 'System Controlled:' . PHP_EOL;
	print '    --type      - The type and subtype of the push out hosts process' . PHP_EOL;
	print '    --child     - The thread id of the child process' . PHP_EOL . PHP_EOL;
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
			cacti_log('WARNING: Push out hosts terminated by user', false, 'PUSHOUT');

			if (strpos($type, 'rmaster') !== false) {
				pushout_kill_running_processes();
			}

			unregister_process('pushout', 'rmaster', $thread_id, getmypid());

			exit(1);

			break;

		default:
			/* ignore all other signals */
	}
}

/**
 * pushout_kill_running_processes - this function is part of an interrupt
 *   handler to kill children processes when the parent is killed
 *
 * @return - NULL
 */
function pushout_kill_running_processes() {
	global $type;

	$processes = db_fetch_assoc_prepared('SELECT *
		FROM processes
		WHERE tasktype = "pushout"
		AND taskname IN ("child")
		AND pid != ?',
		array(getmypid()));

	if (cacti_sizeof($processes)) {
		foreach ($processes as $p) {
			cacti_log(sprintf('WARNING: Killing Cleanup %s PID %d due to another due to signal or overrun.', ucfirst($p['taskname']), $p['pid']), false, 'PUSHOUT');
			posix_kill($p['pid'], SIGTERM);

			unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $p['pid']);
		}
	}
}
