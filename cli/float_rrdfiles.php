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

require(__DIR__ . '/../include/cli_check.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/rrd.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

/* system controlled parameters */
$type              = 'rmaster';
$thread_id         = 0;

/* mandatory parameters */
$start_time        = false;
$end_time          = false;

/* optional parameters for RRDfile selection */
$host_id           = false;
$host_template_id  = false;
$graph_template_id = false;

/* optional for threading and verbose display */
$threads           = 20;
$seebug            = false;

/* optional for force handing and resume */
$resume            = false;
$forcerun          = false;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
		case '--force':
			$forcerun = true;

			break;
		case '--debug':
			$seebug = true;

			break;
		case '--resume':
			$resume = true;

			break;
		case '--start':
			$start_time = $value;

			break;
		case '--end':
			$end_time = $value;

			break;
		case '--type':
			$type = $value;

			break;
		case '--threads':
			$threads = $value;

			break;
		case '--child':
			$thread_id = $value;

			break;
		case '--host-id':
			$host_id = $value;

			break;
		case '--host-template_id':
			$host_template_id = $value;

			break;
		case '--graph-template_id':
			$graph_template_id = $value;

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
 * rmaster  - the main process launched from the Cacti main poller and will launch child processes
 * child    - a child of the master process from the 'rmaster'
 *
 */

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

if ($start_time == false || $end_time == false) {
	printf('ERROR: Both --start=TS and --end=TS are required and can be a timestamp or in date/time format' . PHP_EOL);
	exit(1);
}

/* validate the start time */
if (!is_numeric($start_time)) {
	$ost = $start_time;
	$start_time = strtotime($start_time);

	if ($start_time === false) {
		printf('ERROR: The Start Time \'%s\' is not a valid date/time' . PHP_EOL, $ost);
		exit(1);
	}
}

/* validate the end time */
if (!is_numeric($end_time)) {
	$oet = $end_time;

	$end_time = strtotime($end_time);

	if ($end_time === false) {
		printf('ERROR: The End Time \'%s\' is not a valid date/time' . PHP_EOL, $oet);
		exit(1);
	}
}

/* validate the start and end times are sane */
if ($start_time >= $end_time) {
	printf('ERROR: The Start Time \'%s\' is equal or grater to the End Time \'%s\' is not a valid date/time' . PHP_EOL, date('Y-m-d H:i:s', $start_time), date('Y-m-d H:i:s', $end_time));
	exit(1);
}

/* perform some validation for host-id */
if ($host_id !== false && ($host_id < 0 || !is_numeric($host_id))) {
	printf('ERROR: The value of %s for --host-id is invalid!' . PHP_EOL, $host_id);
	exit(1);
}

/* perform some validation for host-template-id */
if ($host_template_id !== false && ($host_template_id < 0 || !is_numeric($host_template_id))) {
	printf('ERROR: The value of %s for --host-template-id is invalid!' . PHP_EOL, $host_template_id);
	exit(1);
}

/* perform some validation for graph-template-id */
if ($graph_template_id !== false && ($graph_template_id < 0 || !is_numeric($graph_template_id))) {
	printf('ERROR: The value of %s for --graph-template-id is invalid!' . PHP_EOL, $graph_template_id);
	exit(1);
}

/* take time and log performance data */
$start = microtime(true);

/* let's give this script lot of time to run for ever */
ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

/* send a gentle message to the log and stdout */
float_debug('Polling Starting');

/* silently end if the registered process is still running */
if (!$forcerun) {
	if (!register_process_start('rfloat', $type, $thread_id, 86400)) {
		exit(0);
	}
}

/* Collect data as determined by the type */
switch ($type) {
	case 'rmaster':
		float_master_handler($forcerun, $resume, $host_id, $host_template_id, $graph_template_id, $threads, $start_time, $end_time);

		unregister_process('rfloat', 'rmaster', 0);

		break;
	case 'child':  /* Launched by the rmaster process */
		$rrdfiles = db_fetch_assoc_prepared('SELECT *
			FROM poller_float_rrdfiles_not_done
			WHERE process = ?',
			array($thread_id));

		$child_start = microtime(true);

		cacti_log(sprintf('Child Started Process %s with % RRDfiles', $thread_id, cacti_sizeof($rrdfiles)), true, 'RFLOAT');

		foreach($rrdfiles as $data) {
			print '.';

			/* Update the rrdfile to current */
			rrdtool_function_fetch($data['local_data_id'], time()-120, time());

			float_rrdfile($data['rrd_path'], $data['local_data_id'], $start_time, $end_time);

			db_execute_prepared('DELETE FROM poller_float_rrdfiles_not_done
				WHERE local_data_id = ?',
				array($data['local_data_id']));
		}

		$total_time = microtime(true) - $child_start;

		unregister_process('rfloat', 'child', $thread_id);

		break;
}

float_debug('Polling Ending');

exit(0);

/**
 * float_rrdfile - Takes the last known data for a data range
 *   and uses it to float a range.  It is sensitive to daily and other
 *   RRA's and will float around those ranges to ensure that there are
 *   no spikes.
 *
 * @param  (string) The RRDfile to update
 * @param  (int)    The local data id of the data source
 * @param  (int)    The float range start time as a unix timestamp
 * @param  (int)    The float range end time as a unix timestamp
 *
 * @return (bool)   True if successful otherwise false
 */
function float_rrdfile($rrd_path, $local_data_id, $start_time, $end_time) {
	global $sebug;

	static $rrdtool_bin = false;
	static $tmp_dir     = false;

	if ($rrdtool_bin === false) {
		$rrdtool_bin = read_config_option('path_rrdtool');
	}

	if ($tmp_dir === false) {
		$tmp_dir = sys_get_temp_dir();
	}

	$delta_time = $end_time - $start_time;
	$tmp_file   = $tmp_dir . '/' . $local_data_id . '.xml';

	$return     = 0;
	$output     = array();
	$command    = "$rrdtool_bin dump $rrd_path";
	$db_prefix  = '                       ';

	if (file_exists($rrd_path)) {
		if (is_writable($rrd_path)) {
			$response = exec($command, $output, $return);

			if ($return != 0) {
				cacti_log(sprintf('ERROR: Unable to dump file %s to XML', $rrd_path), false, 'RFLOAT');
				return false;
			}

			$fp = fopen($tmp_file, 'w');

			if ($sebug) {
				$lf = fopen('/tmp/clearer.log', 'a');
			}

			if (is_resource($fp)) {
				$in_database = false;
				$in_range    = false;
				$prev_data   = '';

				foreach($output as $line) {
					if (strpos($line, '<pdp_per_row>') !== false) {
						/* split the database record into pieces */
						$parts = preg_split('/[\s]+/', trim($line));

						/**
						 * We use this just in case we need to update
						 * only one line in the RRA.
						 */
						$granularity = $parts[2];
						$gdelta      = $granularity / 2;

						$line .= PHP_EOL;
					} elseif (strpos($line, '<database>') !== false) {
						$in_database = true;
						$line .= PHP_EOL;
					} elseif (strpos($line, '</database>') !== false) {
						$in_database = false;
						$line .= PHP_EOL;
					} elseif ($in_database) {
						/* split the database record into pieces */
						$parts  = preg_split('/[\s]+/', trim($line));

						$timestamp = $parts[5];

						if ($timestamp <= $start_time && ($timestamp + $gdelta) < $end_time) {
							$in_range = false;
							$line .= PHP_EOL;

							$prev_data = $parts[7];
							$prev_line = $line;
						} elseif ($timestamp >= $end_time && ($timestamp - $gdelta) > $start_time) {
							$in_range = false;
							$line .= PHP_EOL;
						} elseif ($prev_data != '') {
							$in_range = true;

							unset($parts[7]);

							$nline = $db_prefix . implode(' ', $parts) . ' ' .  $prev_data . PHP_EOL;

							if ($sebug) {
								fwrite($lf, sprintf("CurDate:%s, StartDate:%s, EndDate:%s, Granularity:%s, Delta:%s" . PHP_EOL, date('Y-m-d H:i:s', $timestamp), date('Y-m-d H:i:s', $start_time), date('Y-m-d H:i:s', $end_time), $granularity, $delta_time));
								fwrite($lf, sprintf("PreLine: %s\nOldLine: %s\nNewLine: %s\n\n", trim($prev_line), trim($line), trim($nline)));
							}

							$line = $nline;
						} else {
							$line .= PHP_EOL;
						}
					} else {
						$line .= PHP_EOL;
					}

					fwrite($fp, $line);
				}

				fclose($fp);

				/* restore the file */
				$return  = 0;
				$output  = array();
				$command = "$rrdtool_bin restore -f $tmp_file $rrd_path";

				$response = exec($command, $output, $return);

				if ($return == 0) {
					cacti_log(sprintf('NOTE: Range floated for RRDfile %s', $rrd_path), false, 'RFLOAT');
					return true;
				} else {
					cacti_log(sprintf('WARNING: Range float FAILED for RRDfile %s.  Message is %s', $rrd_path, $response), false, 'RFLOAT');
					return false;
				}

				if (!$sebug) {
					unlink($tmp_file);
				}

				if ($sebug) {
					fclose($lf);
				}
			} else {
				cacti_log(sprintf('WARNING: Unable to open file %s for writing', $tmp_file), false, 'RFLOAT');
				return false;
			}
		} else {
			cacti_log(sprintf('WARNING: Unable to write to RRDfile %s', $rrd_path), false, 'RFLOAT');
			return false;
		}
	} else {
		cacti_log(sprintf('WARNING: RRDfile does not exist %s', $rrd_path), false, 'RFLOAT');
		return false;
	}
}

function float_master_handler($forcerun, $resume, $host_id, $host_template_id, $graph_template_id, $threads, $start_time, $end_time) {
	global $type;

	/* Create table if first time use */
	if (!db_table_exists('poller_float_rrdfiles_not_done')) {
		db_execute("CREATE TABLE `poller_float_rrdfiles_not_done` (
			`process` int(10) unsigned NOT NULL DEFAULT 0,
			`local_data_id` int(10) unsigned NOT NULL DEFAULT 0,
			`rrd_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			PRIMARY KEY (`rrd_path`),
			KEY `local_data_id` (`local_data_id`),
			KEY `process` (`process`))
			ENGINE=InnoDB
			COMMENT='Temp Storage for processed RRDfiles'");
	}

	$sql_where  = '';
	$sql_params = array();

	if ($host_id !== false) {
		$sql_where  .= 'WHERE h.id = ?';
		$sql_params[] = $host_id;
	}

	if ($host_template_id !== false) {
		$sql_where  .= ($sql_where != '' ? 'AND ':'WHERE ') . 'h.host_template_id = ?';
		$sql_params[] = $host_template_id;
	}

	if ($graph_template_id !== false) {
		$sql_where  .= ($sql_where != '' ? 'AND ':'WHERE ') . 'gti.graph_template_id = ?';
		$sql_params[] = $graph_template_id;
	}

	/* Find out if there are unprocessed records */
	if ($resume) {
		$rows = db_fetch_cell('SELECT COUNT(*) FROM poller_float_rrdfiles_not_done');

		/* If there are no unprocessed records, prime the collector */
		if ($rows > 0) {
			db_execute('UPDATE poller_float_rrdfiles_not_done SET process = 0');
		} else {
			printf('ERROR: There are no outstanding RRDfiles to process!');
			return false;
		}
	} else {
		db_execute('TRUNCATE TABLE poller_float_rrdfiles_not_done');

		db_execute_prepared("INSERT INTO poller_float_rrdfiles_not_done
			(local_data_id, rrd_path)
			SELECT DISTINCT pi.local_data_id, pi.rrd_path
			FROM poller_item AS pi
			INNER JOIN data_local AS dl
			ON pi.local_data_id = dl.id
			INNER JOIN host AS h
			ON dl.host_id = h.id
			INNER JOIN data_template_rrd AS dtr
			ON dl.id = dtr.local_data_id
			INNER JOIN graph_templates_item AS gti
			ON gti.task_item_id = dtr.id
			$sql_where", $sql_params);

		$rows = db_fetch_cell('SELECT COUNT(*) FROM poller_float_rrdfiles_not_done');
	}

	if ($rows == 0) {
		print "WARNING: There are no RRDfiles to process";
		return false;
	}

	$rrdfiles_per_process = ceil(db_fetch_cell_prepared('SELECT COUNT(*)/? FROM poller_float_rrdfiles_not_done', array($threads)));

	print "There are $threads and $rrdfiles_per_process RRDfiles to process per thread" . PHP_EOL;

	for($thread_id = 1; $thread_id <= $threads; $thread_id++) {
		db_execute_prepared("UPDATE poller_float_rrdfiles_not_done
			SET process = ?
			WHERE process = 0
			LIMIT $rrdfiles_per_process",
			array($thread_id));

		float_debug("Launching Process ID $thread_id");

		float_launch_child($thread_id, $start_time, $end_time);
	}

	$starting = true;

	while (true) {
		if ($starting) {
			sleep(5);
			$starting = false;
		}

		$running = float_processes_running();

		$rrds = db_fetch_cell('SELECT COUNT(*) FROM poller_float_rrdfiles_not_done');

		if ($running > 0) {
			float_debug(sprintf('%s Processes Running, %s RRDfiles Remaining, Sleeping for 2 seconds.', $running, $rrds));
			sleep(2);
		} else {
			break;
		}
	}

	return true;
}

/**
 * flaot_launch_child - this function will launch collector children based upon
 *   the maximum number of threads and the process type
 *
 * @param $thread_id  (int)    The Thread id to launch
 * @param $start_time (int)    The float window start time as a timestamp
 * @param $end_time   (int)    The float window end time as a timestamp
 *
 * @return - NULL
 */
function float_launch_child($thread_id, $start_time, $end_time) {
	global $config, $seebug;

	$php_binary = read_config_option('path_php_binary');

	float_debug(sprintf('Launching Float Data Process Number %s for Type %s', $thread_id, 'child'));

	cacti_log(sprintf('NOTE: Launching Float Data Number %s for Type %s', $thread_id, 'child'), false, 'RFLOAT', POLLER_VERBOSITY_MEDIUM);

	exec_background($php_binary, $config['base_path'] . "/cli/float_rrdfiles.php --type=child --child=$thread_id --start=$start_time --end=$end_time" . ($seebug ? ' --debug':''));
}

/**
 * float_processes_running - given a type, determine the number
 *   of sub-type or children that are currently running
 *
 * @return - (int) The number of running processes
 */
function float_processes_running() {
	$running = db_fetch_cell('SELECT COUNT(*)
		FROM processes
		WHERE tasktype = "rfloat"
		AND taskname = "child"');

	if ($running == 0) {
		return 0;
	}

	return $running;
}

/**
 * float_debug - this simple routine prints a standard message to the console
 *   when running in debug mode.
 *
 * @param $message - (string) The message to display
 *
 * @return - NULL
 */
function float_debug($message) {
	global $seebug;

	if ($seebug) {
		print 'RFLOAT: ' . $message . PHP_EOL;
	}
}

/**
 * display_version - displays version information
 */
function display_version() {
	$version = get_cacti_version();
	print "Cacti RRDfile Data Float Tool, Version $version " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - generic help screen for utilities
 */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: float_rrdfiles.php --start=TS --end=TS [--threads=N --host-id=N --host-template-id=N --graph-template-id=N] [--debug]' . PHP_EOL . PHP_EOL;

	print 'Cacti\'s RRDfile Data Float Tool.  This CLI script will float a' . PHP_EOL;
	print 'range in select Cacti Graphs using the RRDtool dump/import utility.' . PHP_EOL . PHP_EOL;

	print 'Required:' . PHP_EOL;
	print '    --start=TS  - The float range start time timestamp or date.' . PHP_EOL;
	print '    --end=TS    - The float range end time timestamp or date.' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '    --threads           - 20, The number of threads to use to update RRDfiles' . PHP_EOL;
	print '    --resume            - False, Resume a canceled float process' . PHP_EOL;
	print '    --host-id           - N/A, Update a specific devices RRDfiles' . PHP_EOL;
	print '    --host-template-id  - N/A, Update a specific Device Templates RRDfiles' . PHP_EOL;
	print '    --graph-template-id - N/A, Update a specific Graph Template RRDfiles' . PHP_EOL;
	print '    --debug             - Display verbose output during execution' . PHP_EOL . PHP_EOL;

	print 'System Controlled:' . PHP_EOL;
	print '    --type      - The type and subtype of the float process' . PHP_EOL;
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
			cacti_log('WARNING: RRDfile Data Float Tool terminated by user', false, 'RFLOAT');

			if (strpos($type, 'rmaster') !== false) {
				float_kill_running_processes();
			}

			unregister_process('rfloat', 'rmaster', $thread_id, getmypid());

			exit(1);
			break;
		default:
			/* ignore all other signals */
	}
}

/**
 * float_kill_running_processes - this function is part of an interrupt
 *   handler to kill children processes when the parent is killed
 *
 * @return - NULL
 */
function float_kill_running_processes() {
	global $type;

	$processes = db_fetch_assoc_prepared('SELECT *
		FROM processes
		WHERE tasktype = "rfloat"
		AND taskname IN ("child")
		AND pid != ?',
		array(getmypid()));

	if (cacti_sizeof($processes)) {
		foreach($processes as $p) {
			cacti_log(sprintf('WARNING: Killing Cleanup %s PID %d due to another due to signal or overrun.', ucfirst($p['taskname']), $p['pid']), false, 'RFLOAT');
			posix_kill($p['pid'], SIGTERM);

			unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $p['pid']);
		}
	}
}

