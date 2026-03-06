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

require(__DIR__ . '/../include/cli_check.php');

require_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
require_once(CACTI_PATH_LIBRARY . '/api_automation.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/api_device.php');
require_once(CACTI_PATH_LIBRARY . '/api_tree.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');
require_once(CACTI_PATH_LIBRARY . '/sort.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

// switch to main database for cli's
if (POLLER_ID > 1) {
	db_switch_remote_to_main();
}

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

// system controlled parameters
$type        = 'rmaster';
$thread_id   = 0;

// mandatory parameters
$start_time  = false;
$end_time    = false;

// optional parameters for host selection
$debug       = false;
$host_id     = 0;
$query_id    = 0;
$host_descr  = '';

// optional for threading and verbose display
$threads     = detect_cpu_cores();

if ($threads == 0) {
	$threads = 2;
}

// optional for force handing and resume
$forcerun = false;

foreach ($parms as $parameter) {
	if (str_contains($parameter, '=')) {
		[$arg, $value] = explode('=', $parameter, 2);
	} else {
		$arg   = $parameter;
		$value = '';
	}

	switch ($arg) {
		case '-id':
		case '--id':
			if (cacti_strtolower($value) == 'all') {
				$host_id = -1;
			} elseif (is_numeric($value) && $value > 0) {
				$host_id = intval($value);
			} else {
				print 'ERROR: You must supply a valid Device ID to run this script!' . PHP_EOL;

				exit(1);
			}

			break;
		case '--qid':
			if (cacti_strtolower($value) == 'all') {
				$query_id = -1;
			} elseif (is_numeric($value) && $value > 0) {
				$query_id = intval($value);
			} else {
				print 'ERROR: You must supply a valid Query ID to run this script!' . PHP_EOL;

				exit(1);
			}

			break;
		case '-host-descr':
		case '--host-descr':
			$host_descr = $value;

			break;
		case '--type':
			$type = $value;

			break;
		case '--threads':
			if (!is_numeric(trim($value))) {
				print 'ERROR: You must supply a valid Number of Treads or skip this parameter for default value (' . $threads . ')' . PHP_EOL;

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

// install signal handlers for UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

// take time and log performance data
$start = microtime(true);

// set new timeout and memory settings
ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

$sql_where = 'WHERE IFNULL(TRIM(s.disabled),"") != "on" AND IFNULL(TRIM(h.disabled),"") != "on"';
$params    = [];

if ($host_id > 0) {
	$sql_where .= 'AND host_id = ? ';
	$params[]  = $host_id;
}

if ($query_id > 0) {
	$sql_where .= 'AND hsq.snmp_query_id = ?';
	$params[] = $query_id;
}

// allow for additional filtering on host description
if ($host_descr != '') {
	$sql_where .= 'AND h.description LIKE ?';
	$params[] = '%' . $host_descr . '%';
}

$rows = db_fetch_cell_prepared("SELECT COUNT(*)
	FROM host_snmp_query hsq
	INNER JOIN host h
	ON h.id = hsq.host_id
	LEFT JOIN sites s
	ON s.id = h.site_id
	$sql_where",
	$params);

// issue warnings and start message if applicable
print 'WARNING: Do not interrupt this script.  Reindexing can take quite some time' . PHP_EOL;

reindex_debug('There are ' . $rows . ' data queries to run');

if ($type == 'rmaster') {
	cacti_log('Poller reindex hosts process started', true, 'REINDEX');
}

// send a gentle message to the log and stdout
reindex_debug('Reindex hosts starting');

// silently end if the registered process is still running
if (!$forcerun) {
	if (!register_process_start('reindex', $type, $thread_id, 86400)) {
		exit(0);
	}
}

// Collect data as determined by the type
switch ($type) {
	case 'rmaster':
		reindex_master_handler($forcerun, $host_id, $query_id, $host_descr, $threads);

		unregister_process('reindex', 'rmaster', 0);

		break;
	case 'child':  // Launched by the rmaster process
		$child_start = microtime(true);

		$sql_where = 'WHERE IFNULL(TRIM(s.disabled),"") != "on" AND IFNULL(TRIM(h.disabled),"") != "on"';
		$params    = [];

		if ($host_id > 0) {
			$sql_where .= 'AND host_id = ? ';
			$params[]  = $host_id;
		}

		if ($query_id > 0) {
			$sql_where .= 'AND hsq.snmp_query_id = ?';
			$params[] = $query_id;
		}

		// allow for additional filtering on host description
		if ($host_descr != '') {
			$sql_where .= 'AND h.description LIKE ?';
			$params[] = '%' . $host_descr . '%';
		}

		$rows = db_fetch_cell_prepared("SELECT COUNT(*)
			FROM host_snmp_query hsq
			INNER JOIN host h
			ON h.id = hsq.host_id
			LEFT JOIN sites s
			ON s.id = h.site_id
			$sql_where",
			$params);

		$ds_per_process = ceil($rows / $threads);

		$sql_where .= ' ORDER BY h.id LIMIT ' . (($thread_id - 1) * $ds_per_process) . ',' . $ds_per_process;

		$data_queries = db_fetch_assoc_prepared("SELECT h.description, h.hostname, hsq.host_id, hsq.snmp_query_id
			FROM host_snmp_query hsq
			INNER JOIN host h
			ON h.id = hsq.host_id
			LEFT JOIN sites s
			ON s.id = h.site_id
			$sql_where",
			$params);

		cacti_log(sprintf('Child Started Process %s with %d hosts, from: %d', $thread_id, $ds_per_process, ($thread_id - 1) * $ds_per_process), true, 'REINDEX');

		foreach ($data_queries as $data_query) {
			run_data_query($data_query['host_id'], $data_query['snmp_query_id'], false, $forcerun);
		}

		$total_time = microtime(true) - $child_start;

		unregister_process('reindex', 'child', $thread_id);

		break;
}

reindex_debug('Polling Ending');

if ($type == 'rmaster') {
	cacti_log('Poller reindex hosts process finished', true, 'REINDEX');
}

exit(0);

function reindex_master_handler(bool $forcerun, int $host_id, int $query_id, string $host_descr, int $threads) : bool {
	global $type;

	$sql_where = 'WHERE IFNULL(TRIM(s.disabled),"") != "on" AND IFNULL(TRIM(h.disabled),"") != "on"';
	$params    = [];

	if ($host_id > 0) {
		$sql_where .= 'AND host_id = ? ';
		$params[]  = $host_id;
	}

	if ($query_id > 0) {
		$sql_where .= 'AND hsq.snmp_query_id = ?';
		$params[] = $query_id;
	}

	// allow for additional filtering on host description
	if ($host_descr != '') {
		$sql_where .= 'AND h.description LIKE ?';
		$params[] = '%' . $host_descr . '%';
	}

	$rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM host_snmp_query hsq
		INNER JOIN host h
		ON h.id = hsq.host_id
		LEFT JOIN sites s
		ON s.id = h.site_id
		$sql_where",
		$params);

	if ($rows == 0) {
		print 'WARNING: There are no data sources to process' . PHP_EOL;

		return false;
	}

	$ds_per_process = ceil($rows / $threads);

	print "There are $rows data queries, $threads threads and $ds_per_process data sources to process per thread" . PHP_EOL;

	$h_done = 0;

	for ($thread_id = 1; $h_done < $rows; $thread_id++) {
		reindex_debug("Launching Process ID $thread_id");

		reindex_launch_child($thread_id, $threads);
		$h_done += $ds_per_process;
	}

	$starting = true;

	while (true) {
		if ($starting) {
			sleep(5);
			$starting = false;
		}

		$running = reindex_processes_running();

		if ($running > 0) {
			reindex_debug(sprintf('%s Processes Running, keeping for 2 seconds.', $running));
			sleep(2);
		} else {
			break;
		}
	}

	return true;
}

/**
 * reindex_launch_child - this function will launch collector children based upon
 * the maximum number of threads and the process type
 *
 * @param int $thread_id The Thread id to launch
 *
 * @return void
 */
function reindex_launch_child(int $thread_id, int $threads) : void {
	global $debug, $host_id, $query_id, $host_descr, $forcerun;

	$php_binary = read_config_option('path_php_binary');

	reindex_debug(sprintf('Launching Reindex hosts Process Number %s for Type %s', $thread_id, 'child'));

	cacti_log(sprintf('NOTE: Launching Reindex hosts Number %s for Type %s', $thread_id, 'child'), true, 'REINDEX', POLLER_VERBOSITY_MEDIUM);

	exec_background($php_binary, CACTI_PATH_CLI . "/poller_reindex_hosts.php --type=child --threads=$threads --child=$thread_id " . ($debug ? ' --debug' : '') . ($host_id ? " --id=$host_id" : '') . ($query_id ? " --qid=$query_id" : '') . ($host_descr ? " --host-descr=$host_descr" : '') . ($forcerun ? ' --force' : ''));
}

/**
 * reindex_processes_running - given a type, determine the number
 * of sub-type or children that are currently running
 *
 * @return int - The number of running processes
 */
function reindex_processes_running() : int {
	$running = db_fetch_cell('SELECT COUNT(*)
		FROM processes
		WHERE tasktype = "reindex"
		AND taskname = "child"');

	return intval($running);
}

/**
 * reindex_debug - this simple routine prints a standard message to the console
 * when running in debug mode.
 *
 * @param string $message The message to display
 *
 * @return void
 */
function reindex_debug(string $message) : void {
	global $debug;

	if ($debug) {
		print 'REINDEX: ' . trim($message) . PHP_EOL;
	}
}

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param int $signo the signal that was thrown by the interface.
 *
 * @return void
 */
function sig_handler(int $signo) : void {
	global $type, $thread_id;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Poller reindex hosts terminated by user', false, 'REINDEX');

			if (str_contains($type, 'rmaster')) {
				reindex_kill_running_processes();
			}

			unregister_process('reindex', 'rmaster', $thread_id, getmypid());

			exit(1);
		default:
			// ignore all other signals
	}
}

/**
 * reindex_kill_running_processes - this function is part of an interrupt
 * handler to kill children processes when the parent is killed
 *
 * @return void
 */
function reindex_kill_running_processes() : void {
	global $type;

	$processes = db_fetch_assoc_prepared('SELECT *
		FROM processes
		WHERE tasktype = "reindex"
		AND taskname IN ("child")
		AND pid != ?',
		[getmypid()]);

	if (cacti_sizeof($processes)) {
		foreach ($processes as $p) {
			cacti_log(sprintf('WARNING: Killing Cleanup %s PID %d due to another due to signal or overrun.', ucfirst($p['taskname']), $p['pid']), false, 'REINDEX');
			posix_kill($p['pid'], SIGTERM);

			unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $p['pid']);
		}
	}
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	print 'Cacti Reindex hosts Tool, Version ' . CACTI_VERSION . ' ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - generic help screen for utilities
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print 'usage: poller_reindex_hosts.php --id=[host_id|all] [--qid=[ID|all]]' . PHP_EOL . PHP_EOL;

	print 'This utility will run in parallel with the given number of threads.' . PHP_EOL;
	print 'If threads argument is not specified, value is derived from the number of processor cores.' . PHP_EOL;
	print 'In case of a detection problem, 2 threads are used.' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '   [--host-descr=[description]] [--debug]' . PHP_EOL . PHP_EOL;
	print '--id=host_id             - The host_id to have data queries reindexed or \'all\' to reindex all hosts' . PHP_EOL;
	print '--qid=query_id           - Only index on a specific data query id; defaults to \'all\'' . PHP_EOL;
	print '--host-descr=description - The host description to filter by (SQL filters acknowledged)' . PHP_EOL;
	print '--threads=N              - The number of threads to use to repopulate' . PHP_EOL;
	print '--force                  - Force Graph and Data Source Suggested Name Re-mapping for all items' . PHP_EOL;
	print '--debug                  - Display verbose output during execution' . PHP_EOL;

	print 'System Controlled:' . PHP_EOL;
	print '    --type      - The type and subtype of the rebuild poller cache process' . PHP_EOL;
	print '    --child     - The thread id of the child process' . PHP_EOL . PHP_EOL;
}
