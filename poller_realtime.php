#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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

require(__DIR__ . '/include/cli_check.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/rrd.php');

/* force Cacti to store realtime data locally */
$config['force_storage_location_local'] = true;

/* initialize some additional variables */
$force     = false;
$debug     = false;
$graph_id  = false;
$interval  = false;
$poller_id = '';

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
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '--force':
				$force = true;
				break;
			case '--graph':
				$graph_id = (int)$value;
				break;
			case '--interval':
				$interval = (int)$value;
				break;
			case '--poller_id':
				$poller_id = $value;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print "ERROR: Invalid Argument: ($arg)\n\n";
				display_help();
				exit(1);
		}
	}
}

if ($graph_id === false || $graph_id < 0) {
	print "ERROR: No --graph=ID specified\n\n";
	display_help();
	exit(1);
}

if ($interval === false || $interval < 0) {
	print "ERROR: No --interval=SEC specified\n\n";
	display_help();
	exit(1);
}

/* record the start time */
$poller_start         = microtime(true);

/* get number of polling items from the database */
$poller_interval = 1;

/* retreive the last time the poller ran */
$poller_lastrun = read_config_option('poller_lastrun');

/* get the current cron interval from the database */
$cron_interval = read_config_option('cron_interval');

if ($cron_interval != 60) {
	$cron_interval = 300;
}

/* assume a scheduled task of either 60 or 300 seconds */
define('MAX_POLLER_RUNTIME', 298);

/* let PHP only run 1 second longer than the max runtime, plus the poller needs lots of memory */
ini_set('max_execution_time', MAX_POLLER_RUNTIME + 1);

/* initialize file creation flags */
$change_files = false;

/* obtain some defaults from the database */
$max_threads = read_config_option('max_threads');

/* Determine Command Name */
$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));
$extra_args     = '-q ' . cacti_escapeshellarg($config['base_path'] . '/cmd_realtime.php') . " $poller_id $graph_id $interval";

/* Determine if Realtime will work or not */
$cache_dir = read_config_option('realtime_cache_path');
if (!is_dir($cache_dir)) {
	cacti_log("FATAL: Realtime Cache Directory '$cache_dir' Does Not Exist!");
	return -1;
} elseif (!is_writable($cache_dir)) {
	cacti_log("FATAL: Realtime Cache Directory '$cache_dir' is Not Writable!");
	return -2;
}

shell_exec("$command_string $extra_args");

/* open a pipe to rrdtool for writing */
$rrdtool_pipe = rrd_init();

/* process poller output */
process_poller_output_rt($rrdtool_pipe, $poller_id, $interval);

/* close rrd */
rrd_close($rrdtool_pipe);

/* close db */
db_close();

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	print "Cacti Realtime Poller, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: poller_realtime.php --graph=ID [--interval=SEC] [--force] [--debug]\n\n";
	print "Cacti's Realtime graphing poller.  This poller behavies very similary\n";
	print "to Cacti's main poller with the exception that it only polls data source\n";
	print "that are specific to the graph being rendered in the Cacti UI.\n\n";
	print "Required:\n";
	print "    --graph=ID     Specify the graph id to convert (realtime)\n\n";
	print "Optional:\n";
	print "    --interval=SEC Specify the graph interval (realtime)\n";
	print "    --force        Override poller overrun detection and force a poller run\n";
	print "    --debug|-d     Output debug information.  Similar to cacti's DEBUG logging level.\n\n";
}

/* process_poller_output REAL TIME MODIFIED */
function process_poller_output_rt($rrdtool_pipe, $poller_id, $interval) {
	global $config;

	include_once($config['library_path'] . '/rrd.php');

	/* let's count the number of rrd files we processed */
	$rrds_processed = 0;

	/* create/update the rrd files */
	$results = db_fetch_assoc_prepared('SELECT port.output, port.time, port.local_data_id,
		poller_item.rrd_path, poller_item.rrd_name, poller_item.rrd_num
		FROM (poller_output_realtime AS port, poller_item)
		WHERE (port.local_data_id=poller_item.local_data_id
		AND port.rrd_name=poller_item.rrd_name)
		AND port.poller_id = ?',
		array($poller_id));

	if (cacti_sizeof($results) > 0) {
		/* create an array keyed off of each .rrd file */
		foreach ($results as $item) {
			$rt_graph_path    = read_config_option('realtime_cache_path') . '/user_' . $poller_id . '_' . $item['local_data_id'] . '.rrd';
			$data_source_path = get_data_source_path($item['local_data_id'], true);

			/* create rt rrd */
			if (!file_exists($rt_graph_path)) {
				/* get the syntax */
				$command = @rrdtool_function_create($item['local_data_id'], true);

				/* replace path */
				$command = str_replace($data_source_path, $rt_graph_path, $command);

				/* replace step */
				$command = preg_replace('/--step\s(\d+)/', '--step 1', $command);

				/* WIN32: before sending this command off to rrdtool, get rid
				of all of the '\' characters. Unix does not care; win32 does.
				Also make sure to replace all of the fancy "\"s at the end of the line,
				but make sure not to get rid of the "\n"s that are supposed to be
				in there (text format) */
				$command = str_replace("\\\n", " ", $command);

				/* create the rrdfile */
				shell_exec($command);

				/* change permissions so that the poller can clear */
				@chmod($rt_graph_path, 0644);
			} else {
				/* change permissions so that the poller can clear */
				@chmod($rt_graph_path, 0644);
			}

			/* now, let's update the path to keep the RRDs updated */
			$item['rrd_path'] = $rt_graph_path;

			/* cleanup the value */
			$value            = trim($item['output']);
			$unix_time        = strtotime($item['time']);

			$rrd_update_array[$item['rrd_path']]['local_data_id'] = $item['local_data_id'];

			/* single one value output */
			if ((is_numeric($value)) || ($value == 'U')) {
				$rrd_update_array[$item['rrd_path']]['times'][$unix_time][$item['rrd_name']] = $value;
			/* multiple value output */
			} else {
				$values = explode(' ', $value);

				$rrd_field_names = array_rekey(db_fetch_assoc_prepared('SELECT
					data_template_rrd.data_source_name,
					data_input_fields.data_name
					FROM (data_template_rrd,data_input_fields)
					WHERE data_template_rrd.data_input_field_id=data_input_fields.id
					AND data_template_rrd.local_data_id = ?', array($item['local_data_id'])), 'data_name', 'data_source_name');

				for ($i=0; $i<cacti_count($values); $i++) {
					if (preg_match('/^([a-zA-Z0-9_\.-]+):([eE0-9\+\.-]+)$/', $values[$i], $matches)) {
						if (isset($rrd_field_names[$matches[1]])) {
							$rrd_update_array[$item['rrd_path']]['times'][$unix_time][$rrd_field_names[$matches[1]]] = $matches[2];
						}
					}
				}
			}

			/* fallback values */
			if ((!isset($rrd_update_array[$item['rrd_path']]['times'][$unix_time])) && ($item['rrd_name'] != '')) {
				$rrd_update_array[$item['rrd_path']]['times'][$unix_time][$item['rrd_name']] = 'U';
			}else if ((!isset($rrd_update_array[$item['rrd_path']]['times'][$unix_time])) && ($item['rrd_name'] == '')) {
				unset($rrd_update_array[$item['rrd_path']]);
			}
		}

		/* make sure each .rrd file has complete data */
		foreach ($results as $item) {
			db_execute_prepared('DELETE FROM poller_output_realtime
				WHERE local_data_id = ?
				AND rrd_name = ?
				AND time = ?
				AND poller_id = ?',
				array($item['local_data_id'], $item['rrd_name'], $item['time'], $poller_id));
		}

		$rrds_processed = rrdtool_function_update($rrd_update_array, $rrdtool_pipe);
	}

	return $rrds_processed;
}
