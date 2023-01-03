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
require_once(CACTI_PATH_LIBRARY . '/poller.php');

if ($config['poller_id'] > 1) {
	print 'FATAL: This utility is designed for the main Data Collector only' . PHP_EOL;

	exit(1);
}

ini_set('memory_limit', '-1');
ini_set('max_execution_time', '0');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug, $child, $type;

$debug      = false;
$force      = false;
$host_ids   = false;
$threads    = 5;
$child      = 0;
$type       = 'master';
$method     = 'fill';
$avgnan     = 'last';
$start_time = false;
$end_time   = false;
$php_bin    = read_config_option('path_php_binary');

/* install signal handlers for UNIX types only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* take the start time to log performance data */
$start = microtime(true);

foreach ($parms as $parameter) {
	if (strpos($parameter, '=')) {
		list($arg, $value) = explode('=', $parameter);
	} else {
		$arg   = $parameter;
		$value = '';
	}

	switch ($arg) {
		case '--start':
			$start_time = strtotime($value);

			break;
		case '--end':
			$end_time = strtotime($value);

			break;
		case '--threads':
			$threads = $value;

			break;
		case '--method':
			$method = $value;

			break;
		case '--avgnan':
			$avgnan = $value;

			break;
		case '--host-ids':
			$host_ids = $value;

			break;
		case '--child':
			$child = $value;

			break;
		case '-f':
		case '--force':
			$force = true;

			break;
		case '-d':
		case '--debug':
			$debug = true;

			break;
		case '-v':
		case '-V':
		case '--version':
			display_version();

			exit;
		case '-h':
		case '-H':
		case '--help':
			display_help();

			exit;

		default:
			print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
			display_help();

			exit;
	}
}

// General time checks
if ($end_time === false || $start_time === false) {
	print 'FATAL: You must provide both --start and an --end dates using \'YYYY-MM-DD HH:MM:SS\' format!' . PHP_EOL;

	exit(1);
}

// Secondary time checks
if ($end_time < $start_time) {
	print 'FATAL: End Time is less than start time!' . PHP_EOL;

	exit(1);
}

// Tertiary and subsequent time checks
if ($end_time < 0) {
	print 'FATAL: End Time is less than 0!' . PHP_EOL;

	exit(1);
}

// Tertiary and subsequent time checks
if ($start_time < 0) {
	print 'FATAL: Start Time is less than 0!' . PHP_EOL;

	exit(1);
}

if ($start_time < strtotime('2019-01-01')) {
	print 'FATAL: Start Time is less than 2019-01-01!.' . PHP_EOL;

	exit(1);
}

if ($method != 'fill' && $method != 'float') {
	print 'FATAL: Invalid --method value.  Options are \'fill\' and \'float\'.' . PHP_EOL;

	exit(1);
}

if ($avgnan != 'last' && $method != 'avg') {
	print 'FATAL: Invalid --avgnan value.  Options are \'last\' and \'avg\'.' . PHP_EOL;

	exit(1);
}

if ($threads <= 0 || $threads > 40) {
	print 'FATAL: Invalid --threads value.  Threads can be from 1 to 40 inclusive.' . PHP_EOL;

	exit(1);
}

if ($host_ids !== false) {
	$host_ids = explode(',', $host_ids);

	foreach ($host_ids as $id) {
		if (!is_numeric($id)) {
			print 'FATAL: The list of --host-ids must be a comma delimited list of numeric Cacti host_ids!' . PHP_EOL;

			exit(1);
		}
	}

	$sql_where = 'WHERE dtd.data_source_path IS NOT NULL AND gl.host_id IN(' . implode(',', $host_ids) . ')';
} else {
	$sql_where = 'WHERE dtd.data_source_path IS NOT NULL';
}

// Command takes a date
$start_date = date('Y-m-d H:i:s', $start_time);
$end_date   = date('Y-m-d H:i:s', $end_time);

// Parent Process, prep table insert records
if ($child == 0) {
	$type = 'master';

	if ($force) {
		printf('NOTE: Looking for and killing running processes.' . PHP_EOL);

		$running = db_fetch_assoc('SELECT *
			FROM processes
			WHERE tasktype = "batchgapfix"');

		if (cacti_sizeof($running)) {
			printf('NOTE: Found %s running processes found.' . PHP_EOL);

			foreach ($running as $r) {
				$running = posix_kill($r['pid'], 0);

				if (posix_get_last_error() == 1) {
					printf('NOTE: Process with PID: %s being killed.' . PHP_EOL, $r['pid']);

					posix_kill($r['pid'], SIGTERM);
				} else {
					printf('NOTE: Process with PID: %s, not found likely crashed.' . PHP_EOL, $r['pid']);
				}
			}

			db_execute('DELETE FROM processes WHERE tasktype = "batchgapfix"');
		} else {
			printf('NOTE: No running processes found.' . PHP_EOL);
		}
	}

	if (!register_process_start('batchgapfix', 'master', 0, 250000)) {
		print 'FATAL: Detected an already running process.  Use --force to override' . PHP_EOL;

		exit(1);
	}

	if (db_table_exists('graph_local_spikekill')) {
		$running = db_fetch_cell('SELECT COUNT(*) FROM graph_local_spikekill WHERE ended = "0000-00-00"');

		if ($running > 0 && !$force) {
			print 'FATAL: You have requested a start run, and a run appears to be already running' . PHP_EOL;
			print 'FATAL: Check that no processes are running and use the --force option to override.' . PHP_EOL;

			exit(1);
		} else {
			db_execute('TRUNCATE TABLE graph_local_spikekill');
		}
	} else {
		db_execute('CREATE TABLE graph_local_spikekill (
			id INT(10) unsigned NOT NULL auto_increment,
			local_graph_id INT(10) unsigned NOT NULL default "0",
			data_source_path varchar(255) NOT NULL default "0",
			child INT(10) unsigned NOT NULL default "0",
			exit_code INT(10) unsigned NOT NULL default "0",
			started TIMESTAMP NOT NULL default "0000-00-00",
			ended TIMESTAMP NOT NULL default "0000-00-00",
			PRIMARY KEY (id),
			KEY child (child),
			KEY ended (ended))
			ENGINE=MEMORY
			COMMENT="Holds batch spike fill database"');
	}

	// Insert the local graph ids into the spikekill database
	db_execute("INSERT INTO graph_local_spikekill
		(local_graph_id, data_source_path)
		SELECT DISTINCT gl.id, dtd.data_source_path
		FROM graph_local AS gl
		INNER JOIN graph_templates_item AS gti
		ON gl.id = gti.local_graph_id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		INNER JOIN data_template_data AS dtd
		ON dtd.id = dtr.local_data_id
		$sql_where");

	$rrdfiles = db_affected_rows();

	db_execute_prepared('UPDATE graph_local_spikekill
		SET data_source_path = REPLACE(data_source_path, "<path_rra>", ?)',
		array(CACTI_PATH_RRA));

	print "NOTE: There are $rrdfiles RRDfiles that will be checked for gaps and fixed" . PHP_EOL;

	$rrds_per_thread = ceil($rrdfiles / $threads);

	// Distributing RRDfiles into tasks
	for ($i = 1; $i <= $threads; $i++) {
		db_execute_prepared("UPDATE graph_local_spikekill
			SET child = ?
			WHERE child = 0
			LIMIT $rrds_per_thread",
			array($i));
	}

	$now = date('H:i:s');

	printf('NOTE: %s, Database primed for batch gap fill.' . PHP_EOL, $now);

	// Fork Child Binaries
	for ($i = 1; $i <= $threads; $i++) {
		$command = sprintf("%s/cli/batchgapfix.php --start='%s' --end='%s' --method=%s --avgnan=%s --child=%s" . ($force ? ' --force':'') . ($debug ? ' --debug':''),
			CACTI_PATH_BASE,
			$start_date,
			$end_date,
			$method,
			$avgnan,
			$i
		);

		$now = date('H:i:s');

		printf('NOTE: %s, Exec in Background: %s %s' . PHP_EOL, $now, $php_bin, $command);

		exec_background($php_bin, $command);
	}

	$start = microtime(true);

	while (true) {
		sleep(1);

		$not_finished = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM graph_local_spikekill
			WHERE ended = "0000-00-00"');

		$end = microtime(true);

		$rate = ($rrdfiles - $not_finished) / ($end - $start);

		if ($rate > 0) {
			$estimate = round($rrdfiles / $rate, 0);
			$complate = ($end - $start) - $estimate;
		} else {
			$estimate = 'unknown';
		}

		$now = date('H:i:s');

		if ($not_finished > 0) {
			printf('NOTE: %s, Status %s of %s RRDfiles processed. Total Time is %.0f.' . PHP_EOL, $now, number_format($rrdfiles - $not_finished), number_format($rrdfiles), $end - $start);
			printf('NOTE: %s, Processing Rate: %s RRDfiles per/second, Estimated Complete in: %s seconds, Sleeping 1 seconds.' . PHP_EOL, $now, round($rate, 2), $estimate);
		} else {
			printf('NOTE: All RRDfiles processed.  Total Time was %.2f seconds.' . PHP_EOL, $end - $start);

			break;
		}
	}

	$succeeded = db_fetch_cell('SELECT COUNT(*) FROM graph_local_spikekill WHERE exit_code = 0');
	$failed    = db_fetch_cell('SELECT COUNT(*) FROM graph_local_spikekill WHERE exit_code != 0');

	cacti_log(sprintf('BATCHFIX STATS: Time:%s, RRDfiles:%s, Threads:%s, Rate:%s, Succeeded:%s, Failed:%s', round($end - $start, 2), $rrdfiles, $threads, round($rate,2), $succeeded, $failed), false, 'SYSTEM');

	unregister_process('batchgapfix', $type, $child);

	db_execute('TRUNCATE TABLE graph_local_spikekill');

	exit(0);
} else {
	$type = 'child';

	if (!register_process_start('batchgapfix', 'child', $child, 250000)) {
		print 'FATAL: Detected an already running process.  Use --force to override' . PHP_EOL;

		exit(1);
	}
	// Child Process, remediate spikes

	$rrdfiles = db_fetch_assoc_prepared('SELECT *
		FROM graph_local_spikekill
		WHERE child = ?',
		array($child));

	$start = microtime(true);

	$succeeded = 0;
	$failed    = 0;

	foreach ($rrdfiles as $rrdfile) {
		$output     = array();
		$return_var = 0;

		// Format the command
		$command = sprintf("%s -q %s/cli/removespikes.php --rrdfile='%s' --outlier-start='%s' --outlier-end='%s' --method=%s --avgnan=%s",
			$php_bin,
			CACTI_PATH_BASE,
			$rrdfile['data_source_path'],
			$start_date,
			$end_date,
			$method,
			$avgnan
		);

		db_execute_prepared('UPDATE graph_local_spikekill
			SET started = NOW()
			WHERE id = ?',
			array($rrdfile['id']));

		// Run the command
		debug("NOTE: Running command: $command");

		exec($command, $output, $return_var);

		db_execute_prepared('UPDATE graph_local_spikekill
			SET ended = NOW(), exit_code = ?
			WHERE id = ?',
			array($return_var, $rrdfile['id']));

		if ($return_var == 0) {
			printf('SUCCESS: Gap Fills for RRDfile:%s' . PHP_EOL, $rrdfile['data_source_path']);
			$succeeded++;
		} else {
			printf('FAILED:  Gap Fills failed for RRDfile:%s' . PHP_EOL, $graph['data_source_path']);
			$failed++;
		}
	}

	$end = microtime(true);

	printf('NOTE: Batch Fill Process Ended in %s seconds.  Succeeded:%s, Failed:%s' . PHP_EOL, round($end - $start, 2), $succeeded, $failed);

	cacti_log(sprintf('BATCHFIX CHILD STATS: Time:%s, Thread:%s, RRDfiles:%s, Succeeded:%s, Failed:%s', round($end - $start, 2), $child, cacti_sizeof($rrdfiles), $succeeded, $failed), false, 'SYSTEM');

	unregister_process('batchgapfix', $type, $child);
}

exit(0);

/** sig_handler - provides a generic means to catch exceptions to the Cacti log.
 * @arg $signo  - (int) the signal that was thrown by the interface.
 * @param mixed $signo
 * @return      - null */
function sig_handler($signo) {
	global $child, $type;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			unregister_process('batchgapfix', $type, $child);

			if ($child == 0) {
				db_execute('TRUNCATE TABLE graph_local_spikekill');
			}

			exit(1);

			break;

		default:
			/* ignore all other signals */
	}
}

function debug($string) {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . trim($string) . PHP_EOL;
	}
}

function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Batch Graph Gap Fill Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL . 'This utility will fill gaps in graphs based upon a time range.' . PHP_EOL;
	print 'It will perform this process in parallel to increase performance based upon the number of threads ' . PHP_EOL;
	print 'selected by the user.' . PHP_EOL . PHP_EOL;
	print 'usage: batchgapfix.php --start=\'YYYY-MM-DD HH:MM:SS\' --end=\'YYYY-MM-DD HH:MM:SS\' [--threads=N]' . PHP_EOL;
	print '       [--method=fill|float] [--avgnan=last|avg] [--host-ids=N,N,N,...]' . PHP_EOL;
	print '       [-f|--force] [-d|--debug]' . PHP_EOL . PHP_EOL;
	print 'Required:' . PHP_EOL;
	print '   --start=\'YYYY-MM-DD HH:MM:SS\' - The start date to check and remove gaps.' . PHP_EOL;
	print '   --end=\'YYYY-MM-DD HH:MM:SS\'   - The end date to check and remove gaps.' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '   --threads=N                     - Default is 5.  The number of parallel threads [1..40]' . PHP_EOL;
	print '   --method=fill|float             - Default is \'fill\'.  The method to fill gaps.' . PHP_EOL;
	print '   --avgnan=last|avg               - Default is \'last\'.  The number to use to fill gaps.' . PHP_EOL;
	print '   --host-ids=N,N,N,...            - A comma delimited list of Cacti Device ID\'s to process.' . PHP_EOL;
	print '   --force                         - Kill the current running batch gap fill and start over.' . PHP_EOL;
	print '   --debug                         - Higher tracing level for select utilities.' . PHP_EOL . PHP_EOL;
}
