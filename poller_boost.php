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
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/boost.php');
require_once($config['base_path'] . '/lib/dsstats.php');
require_once($config['base_path'] . '/lib/rrdcheck.php');
require_once($config['base_path'] . '/lib/rrd.php');

/* get the boost polling cycle */
$max_run_duration = read_config_option('boost_rrd_update_max_runtime');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = false;
$forcerun = false;
$verbose  = false;
$child    = false;

/* for releasing lock on SIGNAL */
$current_lock = false;

global $child, $next_run_time, $archive_table, $current_lock;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--child':
				$child = $value;
				break;
			case '-d':
			case '--debug':
				$debug = true;
				break;
			case '-f':
			case '--force':
				$forcerun = true;
				cacti_log('WARNING: Boost Poller forced by command line.', false, 'BOOST');
				break;
			case '--verbose':
				$verbose = true;
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
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
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
$start_time = time();
$rrd_updates = -1;

/* let's give this script lot of time to run for ever */
ini_set('max_execution_time', '0');
boost_memory_limit();

if ($child == false) {
	$current_time  = time();

	/* find out if it's time to collect device information
	 * support both old and new formats.
	 */
	$boost_last_run_time = read_config_option('boost_last_run_time');
	if (!empty($boost_last_run_time) && !is_numeric($boost_last_run_time)) {
		$last_run_time = strtotime($boost_last_run_time);
	} else {
		$last_run_time = $boost_last_run_time;
	}

	$boost_next_run_time = read_config_option('boost_next_run_time');
	if (!empty($boost_next_run_time) && !is_numeric($boost_next_run_time)) {
		$next_run_time = strtotime($boost_next_run_time);
	} else {
		$next_run_time = $boost_next_run_time;
	}

	$run_now = boost_time_to_run($forcerun, $current_time, $last_run_time, $next_run_time);

	if ($run_now) {
		/**
		 * Check to see if there are any poller items to process and if not
		 * exit cleanly
		 */
		$poller_items = db_fetch_row('SELECT * FROM poller_output_boost LIMIT 1');
		if (!cacti_sizeof($poller_items)) {
			exit(0);
		}

		/* we will warn if the process is taking extra long */
		if (!register_process_start('boost', 'master', $config['poller_id'], read_config_option('boost_rrd_update_max_runtime') * 3)) {
			exit(0);
		}

		boost_debug('Time to Run Boost, Force Run is ' . ($forcerun ? 'true!':'false.'));

		/* Check if processes are running and kill them */
		boost_kill_running_processes();

		/* Truncate the rrd_update_counter table */
		db_execute('TRUNCATE TABLE poller_output_boost_processes');

		/* Prepare the boost distribution */
		$continue = boost_prepare_process_table();

		/* Launch the boost children */
		if ($continue) {
			/* Allow mysql to flush the rename transaction */
			sleep(7);

			boost_launch_children();

			/* Wait for all processes to continue */
			while ($running = boost_processes_running()) {
				boost_debug(sprintf('%s Processes Running, Sleeping for 2 seconds.', $running));
				sleep(2);
			}

			/* tell the main poller that we are done */
			set_config_option('boost_poller_status', 'complete - end time:' . date('Y-m-d H:i:s'));

			/* Finish processing post */
			set_config_option('boost_last_run_time', $current_time);

			/* output all the rrd data to the rrd files */
			$rrd_updates = db_fetch_cell('SELECT SUM(status) FROM poller_output_boost_processes');

			$seconds_offset = read_config_option('boost_rrd_update_interval') * 60;

			if ($rrd_updates > 0) {
				boost_log_statistics($rrd_updates);
				$next_run_time = $current_time + $seconds_offset;
			} elseif ($rrd_updates == -1) {
				boost_log_statistics(0);
				$next_run_time = $current_time + $seconds_offset;
			} else { /* rollback last run time */
				set_config_option('boost_last_run_time', $last_run_time);
			}

			if ($rrd_updates > 0) {
				/* cleanup - remove empty arch tables*/
				$tables = db_fetch_assoc("SELECT table_name AS name
					FROM information_schema.tables
					WHERE TABLE_SCHEMA = SCHEMA()
					AND TABLE_NAME LIKE 'poller_output_boost_arch_%'");

				if (cacti_sizeof($tables)) {
					foreach($tables as $table) {
						db_execute('DROP TABLE IF EXISTS ' . $table['name']);
					}
				}

				dsstats_boost_bottom();
				rrdcheck_boost_bottom();

				api_plugin_hook('boost_poller_bottom');
			}
		}

		unregister_process('boost', 'master', $config['poller_id'], getmypid());
	}

	/* store the next run time so that people understand */
	if ($rrd_updates > 0 || $rrd_updates == -1) {
		set_config_option('boost_next_run_time', $next_run_time);
	}

	boost_purge_cached_png_files($forcerun);

	exit(0);
} else {
	/* we will warn if the process is taking extra long */
	if (!register_process_start('boost', 'child', $child, read_config_option('boost_rrd_update_max_runtime') * 3)) {
		exit(0);
	}

	/* output all the rrd data to the rrd files */
	$rrd_updates = boost_output_rrd_data($child);

	db_execute_prepared('INSERT INTO poller_output_boost_processes
		(status) VALUES (?)',
		array($rrd_updates));

	boost_log_child_statistics($rrd_updates, $child);

	unregister_process('boost', 'child', $child);

	exit(0);
}

function sig_handler($signo) {
	global $child, $config, $current_lock;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Boost Poller terminated by user', false, 'BOOST');

			/* tell the main poller that we are done */
			set_config_option('boost_poller_status', 'terminated - end time:' . date('Y-m-d H:i:s'));

			if ($child) {
				unregister_process('boost', 'child', $child, getmypid());
			} else {
				unregister_process('boost', 'master', $config['poller_id'], getmypid());
			}

			exit;
			break;
		default:
			/* ignore all other signals */
	}

	if ($current_lock !== false && $child) {
		db_execute("SELECT RELEASE_LOCK('boost.single_ds.$current_lock')");
	} elseif (!$child) {
		db_execute("SELECT RELEASE_ALL_LOCKS()");
	}
}

function boost_kill_running_processes() {
	$processes = db_fetch_assoc_prepared('SELECT *
		FROM processes
		WHERE tasktype = "boost"
		AND pid != ?',
		array(getmypid()));

	if (cacti_sizeof($processes)) {
		foreach($processes as $p) {
			cacti_log(sprintf('WARNING: Killing Boost %s PID %d due to another boost process starting.', ucfirst($p['taskname']), $p['pid']), false, 'BOOST');
			posix_kill($p['pid'], SIGTERM);

			unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $p['pid']);
		}
	}
}

function boost_processes_running() {
	$running = db_fetch_cell('SELECT COUNT(*)
		FROM processes
		WHERE tasktype = "boost"
		AND taskname = "child"');

	return $running;
}

function boost_prepare_process_table() {
	global $start_time, $archive_table, $max_run_duration, $config, $database_default, $debug, $get_memory, $memory_used;

	boost_debug('Parallel Process Setup Begins.');

	$boost_poller_status = read_config_option('boost_poller_status');
	if (!$boost_poller_status) {
		$boost_poller_status = 'not started';
	]

	/* detect a process that has overrun it's warning time */
	if (substr_count($boost_poller_status, 'running')) {
		$status_array = explode(':', $boost_poller_status);

		if (!empty($status_array[1])) {
			$previous_start_time = strtotime($status_array[1]);

			/* if the runtime was exceeded, allow the next process to run */
			if ($previous_start_time + $max_run_duration < $start_time) {
				cacti_log('WARNING: Detected Poller Boost Overrun, Possible Boost Poller Crash', false, 'BOOST SVR');
				admin_email(__('Cacti System Warning'), __('WARNING: Detected Poller Boost Overrun, Possible Boost Poller Crash', 'BOOST SVR'));
			}
		}
	}

	/* if the poller is not running, or has never run, start */
	/* mark the boost server as running */
	set_config_option('boost_poller_status', 'running - start time:' . date('Y-m-d H:i:s'));

	$delayed_inserts = db_fetch_row("SHOW STATUS LIKE 'Not_flushed_delayed_rows'");

	while (cacti_sizeof($delayed_inserts) && $delayed_inserts['Value']) {
		cacti_log('BOOST WAIT: Waiting 1s for delayed inserts are made' , true, 'SYSTEM');
		usleep(1000000);
		$delayed_inserts = db_fetch_row("SHOW STATUS LIKE 'Not_flushed_delayed_rows'");
	}

	$time = time();

	/* split poller_output_boost */
	$archive_table = 'poller_output_boost_arch_' . $time;
	$interim_table = 'poller_output_boost_' . $time;

	db_execute("CREATE TABLE $interim_table LIKE poller_output_boost");
	db_execute("RENAME TABLE poller_output_boost TO $archive_table, $interim_table TO poller_output_boost");
	db_execute("ANALYZE TABLE $archive_table");

	$arch_tables = boost_get_arch_table_names($archive_table);

	if (!cacti_sizeof($arch_tables)) {
		cacti_log('ERROR: Failed to retrieve archive table name - check poller', false, 'BOOST');

		return false;
	}

	$total_rows = 0;

	foreach($arch_tables as $table) {
		$total_rows += db_fetch_cell_prepared('SELECT TABLE_ROWS
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = SCHEMA()
			AND TABLE_NAME = ?',
			array($table));
	}

	if ($total_rows == 0) {
		boost_debug('ERROR: Failed to retrieve any rows from archive tables');

		cacti_log('ERROR: Failed to retrieve any rows from archive tables', false, 'BOOST');

		return false;
	}

	db_execute('CREATE TABLE IF NOT EXISTS poller_output_boost_local_data_ids (
		local_data_id int unsigned default "0",
		process_handler int unsigned default "0",
		PRIMARY KEY (local_data_id),
		INDEX process_handler(process_handler))
		ENGINE=InnoDB');

	db_execute('TRUNCATE poller_output_boost_local_data_ids');

	foreach($arch_tables as $table) {
		db_execute("INSERT IGNORE INTO poller_output_boost_local_data_ids
			(local_data_id)
			SELECT DISTINCT local_data_id
			FROM $table");
	}

	$data_ids = db_fetch_cell("SELECT
		COUNT(local_data_id)
		FROM poller_output_boost_local_data_ids");

	$processes = read_config_option('boost_parallel');

	boost_debug("Data Sources:$data_ids, Concurrent Processes:$processes");

	$data_ids_per_process = ceil($data_ids / $processes);

	$count = 1;

	while ($count <= $processes) {
		db_execute_prepared('UPDATE poller_output_boost_local_data_ids
			SET process_handler = ?
			WHERE process_handler = 0
			LIMIT ' . $data_ids_per_process,
			array($count));

		$count++;
	}

	boost_debug('Parallel Process Setup Complete.  Ready to spawn children.');

	return true;
}

function boost_launch_children() {
	global $config, $debug;

	$processes = read_config_option('boost_parallel');

	if (empty($processes)) {
		$processes = 1;
	}

	$php_binary    = read_config_option('path_php_binary');
	$boost_log     = read_config_option('path_boost_log');
	$redirect_args = '';

	if ($boost_log != '') {
		if (!is_writable($boost_log)) {
			boost_debug("WARNING: Boost log '$boost_log' is not writable!");

			cacti_log("WARNING: Boost log '$boost_log' is not writable!", false, 'BOOST');
		} else {
			$redirect_args = '>> ' . $boost_log;
		}
	}

	boost_debug("About to launch $processes processes.");

	for($i = 1; $i <= $processes; $i++) {
		boost_debug('Launching Boost Process Number ' . $i);

		cacti_log('NOTE: Launching Boost Process Number ' . $i, false, 'BOOST', POLLER_VERBOSITY_MEDIUM);

		exec_background($php_binary, $config['base_path'] . '/poller_boost.php --child=' . $i . ($debug ? ' --debug':''), $redirect_args);
	}

	sleep(2);
}

function boost_time_to_run($forcerun, $current_time, $last_run_time, $next_run_time) {
	$run_now = false;

	boost_debug("Checking if Boost is ready to run.");

	if ((read_config_option('boost_rrd_update_enable') == 'on') || $forcerun) {
		/* turn on the system level updates as that is what dictates "on/off" */
		if (!$forcerun && read_config_option('boost_rrd_update_system_enable') != 'on') {
			set_config_option('boost_rrd_update_system_enable', 'on');
		}

		$seconds_offset = read_config_option('boost_rrd_update_interval') * 60;

		/* Initialize seconds offset, if not set to 2 hours */
		if (empty($seconds_offset)) {
			$seconds_offset = 120;
			set_config_option('boost_rrd_update_interval', 120);
		}

		boost_debug("Last Runtime was " . date('Y-m-d H:i:s', $last_run_time) . " ($last_run_time).");
		boost_debug("Next Runtime is "  . date('Y-m-d H:i:s', $next_run_time) . " ($next_run_time).");

		/* determine the next start time */
		if (empty($last_run_time)) {
			/* since the poller has never run before, let's fake it out */
			$next_run_time = $current_time + $seconds_offset;

			set_config_option('boost_last_run_time', $current_time);
			set_config_option('boost_next_run_time', $next_run_time);

			$run_now = false;
		} else {
			$next_run_time = $last_run_time + $seconds_offset;
			if ($current_time >= $next_run_time) {
				$run_now = true;
				set_config_option('boost_next_run_time', $next_run_time);
			}
		}

		/* determine if you must output boost table now */
		$current_records = boost_get_total_rows();
		$max_records     = read_config_option('boost_rrd_update_max_records');

		boost_debug('Records Found:' . $current_records . ', Max Threshold:' . $max_records . '.');

		if ($current_records > $max_records) {
			$run_now = true;
			set_config_option('boost_next_run_time', $next_run_time);
		}

		if ($forcerun) {
			$run_now = true;
			set_config_option('boost_next_run_time', $next_run_time);
		}
	} else {
		$pollers = db_fetch_cell('SELECT COUNT(*) FROM pollers WHERE disabled = ""');

		if ($pollers > 1) {
			boost_debug('Someone attempted to disable boost through there are multiple Data Collectors Defined!');

			set_config_option('boost_rrd_update_system_enable', 'on');
		} elseif (read_config_option('boost_rrd_update_system_enable') == 'on') {
			/* turn off the system level updates, we want to disable */
			set_config_option('boost_rrd_update_system_enable', '');
		}

		/* we are force to run until boost is finished */
		$rows = boost_get_total_rows();

		if ($rows > 0) {
			$run_now = true;
		}
	}

	return $run_now;
}

function boost_output_rrd_data($child) {
	global $start, $archive_table, $max_run_duration, $config, $database_default, $debug, $get_memory, $memory_used;

	$rrd_updates       = 0;
	$rrdtool_pipe      = rrd_init();
	$runtime_exceeded  = false;

	/* let's set and track memory usage will we */
	if (!function_exists('memory_get_peak_usage')) {
		$get_memory   = true;
		$memory_used  = memory_get_usage();
	} else {
		$get_memory   = false;
	}

	boost_debug("Processing RRRtool Output for Boost Process $child");

	$arch_tables = boost_get_arch_table_names($archive_table);

	if (!cacti_sizeof($arch_tables)) {
		cacti_log('ERROR: Failed to retrieve archive table name', false, 'BOOST');

		return false;
	}

	$total_rows = 0;

	foreach($arch_tables as $table) {
		$total_rows += db_fetch_cell_prepared("SELECT COUNT(at.local_data_id)
			FROM $table AS at
			INNER JOIN poller_output_boost_local_data_ids AS bpt
			ON at.local_data_id = bpt.local_data_id
			AND bpt.process_handler = ?",
			array($child));
	}

	if ($total_rows == 0) {
		return false;
	}

	boost_debug("Processes:$child, TotalRows:$total_rows");

	$max_per_select = read_config_option('boost_rrd_update_max_records_per_select');

	$data_ids = db_fetch_cell_prepared("SELECT
		COUNT(local_data_id)
		FROM poller_output_boost_local_data_ids
		WHERE process_handler = ?",
		array($child));

	$passes       = ceil($total_rows / $max_per_select);
	$ids_per_pass = ceil($data_ids / $passes);
	$curpass      = 1;

	while ($data_ids > 0) {
		boost_debug("Processing $curpass of $passes for Boost Process $child");

		$last_id = db_fetch_cell_prepared("SELECT MAX(local_data_id)
			FROM (
				SELECT local_data_id
				FROM poller_output_boost_local_data_ids
				WHERE process_handler = ?
				ORDER BY local_data_id ASC
				LIMIT $ids_per_pass
			) AS result",
			array($child));

		if (empty($last_id)) {
			break;
		}

		boost_process_local_data_ids($last_id, $child, $rrdtool_pipe);

		$curpass++;

		$data_ids = db_fetch_cell_prepared('SELECT *
			FROM poller_output_boost_local_data_ids
			WHERE process_handler = ?',
			array($child));

		if (((time()-$start) > $max_run_duration) && (!$runtime_exceeded)) {
			cacti_log('WARNING: RRD On Demand Updater Exceeded Runtime Limits. Continuing to Process!!!', false, 'BOOST');
			$runtime_exceeded = true;
		}
	}

	boost_debug("Processing Complete for Boost Process $child.  It took $curpass passed to complete.");

	/* log memory usage */
	if (function_exists('memory_get_peak_usage')) {
		set_config_option('boost_peak_memory_' . $child, memory_get_peak_usage());
	} else {
		set_config_option('boost_peak_memory_' . $child, $memory_used);
	}

	rrd_close($rrdtool_pipe);

	return $total_rows;
}

/* boost_process_local_data_ids - grabs data from the 'poller_output' table and feeds the *completed*
     results to RRDTool for processing
   @arg $last_id - the last id to process
   @arg $child - the current process
   @arg $rrdtool_pipe - the socket that has been opened for the RRDtool operation */
function boost_process_local_data_ids($last_id, $child, $rrdtool_pipe) {
	global $config, $archive_table, $boost_sock, $boost_timeout, $debug, $get_memory, $memory_used, $current_lock;

	/* cache this call as it takes time */
	static $archive_tables  = false;
	static $rrdtool_version = '';

	include_once($config['library_path'] . '/rrd.php');

	/* suppress warnings */
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	/* gather, repair if required and cache the rrdtool version */
	if ($rrdtool_version == '') {
		$rrdtool_ins_version = get_installed_rrdtool_version();
		$rrdtool_version = get_rrdtool_version();
		if ($rrdtool_ins_version != $rrdtool_version) {
			boost_debug('NOTE: Updating Stored RRDtool version to installed version ' . $rrdtool_ins_version);

			cacti_log('NOTE: Updating Stored RRDtool version to installed version ' . $rrdtool_ins_version, false, 'BOOST');

			set_config_option('rrdtool_version', $rrdtool_ins_version);
			$rrdtool_version = $rrdtool_ins_version;
		}
	}

	/* install the boost error handler */
	set_error_handler('boost_error_handler');

	/* load system variables needed */
	$upd_string_len		 = read_config_option('boost_rrd_update_string_length');
	$rrd_update_interval = read_config_option('boost_rrd_update_interval');
	$data_ids_to_get     = read_config_option('boost_rrd_update_max_records_per_select');

	if ($archive_tables === false) {
		$archive_tables = boost_get_arch_table_names($archive_table);
	}

	if ($archive_tables === false) {
		boost_debug('Failed to determine archive tables');
		cacti_log('Failed to determine archive tables', false, 'BOOST');
		return 0;
	}

	$query_string = 'SELECT * FROM (';
	$query_string_suffix = 'ORDER BY local_data_id ASC, timestamp ASC, rrd_name ASC';

	$sub_query_string = '';

	foreach ($archive_tables as $table) {
		$sub_query_string .= ($sub_query_string != '' ? ' UNION ALL ':'') .
			" SELECT $table.local_data_id, UNIX_TIMESTAMP(time) AS timestamp, rrd_name, output
			FROM $table
			INNER JOIN poller_output_boost_local_data_ids AS bpt
			ON $table.local_data_id = bpt.local_data_id
			WHERE bpt.local_data_id <= $last_id
			AND bpt.process_handler = $child";
	}

	$query_string = $query_string . $sub_query_string . ') t ' . $query_string_suffix;

	boost_timer('get_records', BOOST_TIMER_START);
	$results = db_fetch_assoc($query_string);
	boost_timer('get_records', BOOST_TIMER_END);

	/* log memory */
	if ($get_memory) {
		$cur_memory = memory_get_usage();

		if ($cur_memory > $memory_used) {
			$memory_used = $cur_memory;
		}
	}

	if (cacti_sizeof($results)) {
		/* create an array keyed off of each .rrd file */
		$local_data_id  = -1;
		$time           = -1;
		$buflen         = 0;
		$outarray       = array();
		$locked         = false;
		$last_update    = -1;
		$last_item	= array('local_data_id' => -1, 'timestamp' => -1, 'rrd_name' => '');

		/* we are going to blow away all record if ok */
		$vals_in_buffer = 0;

		boost_timer('results_cycle', BOOST_TIMER_START);

		/* go through each poller_output_boost entries and process */
		foreach ($results as $item) {
			$item['timestamp'] = trim($item['timestamp']);

			if (!$locked) {
				/* acquire lock in order to prevent race conditions, only a problem pre-rrdtool 1.5 */
				while (!db_fetch_cell("SELECT GET_LOCK('boost.single_ds." . $item['local_data_id'] . "', 1)")) {
					usleep(50000);
				}

				$current_lock = $item['local_data_id'];

				$locked = true;
			}

			/* if the local_data_id changes, we need to flush the buffer
			 * and discover the template for the next RRDfile.
			 */
			if ($local_data_id != $item['local_data_id']) {
				/* release the previous lock */
				db_execute("SELECT RELEASE_LOCK('boost.single_ds.$local_data_id')");

				$current_lock = false;

				/* acquire lock in order to prevent race conditions, only a problem pre-rrdtool 1.5 */
				while (!db_fetch_cell("SELECT GET_LOCK('boost.single_ds." . $item['local_data_id'] . "', 1)")) {
					usleep(50000);
				}

				$current_lock = $item['local_data_id'];

				/* update the rrd for the previous local_data_id */
				if ($vals_in_buffer) {
					/* place the latest update at the end of the output array */
					$outarray[] = $tv_tmpl;

					/* new process output function */
					boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe);

					$buflen = 0;
					$vals_in_buffer = 0;
					$outarray = array();
				}

				/* reset the rrd file path and templates, assume non multi output */
				boost_timer('rrd_filename_and_template', BOOST_TIMER_START);
				$rrd_data     = boost_get_rrd_filename_and_template($item['local_data_id']);
				$rrd_tmpl     = $rrd_data['rrd_template'];
				$template_len = strlen($rrd_tmpl);

				/* take the template and turn into an associative array of
				 * data source names with a default of 'U' for each value
				 * and creating the first value to include the timestamp.
				 * We will use this for missing data detection.
				 */
				$rrd_tmplp   = array_fill_keys(array_values(explode(':', $rrd_tmpl)), 'U');
				$rrd_tmplpts = array('timestamp' => '') + $rrd_tmplp;

				$rrd_path    = $rrd_data['rrd_path'];
				boost_timer('rrd_filename_and_template', BOOST_TIMER_END);

				if (cacti_version_compare($rrdtool_version, '1.5', '<')) {
					boost_timer('rrd_lastupdate', BOOST_TIMER_START);
					$last_update = boost_rrdtool_get_last_update_time($rrd_path, $rrdtool_pipe);
					boost_timer('rrd_lastupdate', BOOST_TIMER_END);
				} else {
					boost_timer('rrd_lastupdate', BOOST_TIMER_START);
					$last_update = 0;
					boost_timer('rrd_lastupdate', BOOST_TIMER_END);
				}

				$local_data_id = $item['local_data_id'];
				$time          = $item['timestamp'];

				if ($time > $last_update || cacti_version_compare($rrdtool_version, '1.5', '>=')) {
					$buflen += strlen(' ' . $time);
				}

				$multi_vals_set = false;
				$tv_tmpl = $rrd_tmplpts;
			}

			/* don't generate error messages if the RRD has already been updated */
			if ($time < $last_update && cacti_version_compare($rrdtool_version, '1.5', '<')) {
				cacti_log("WARNING: Stale Poller Data Found! Item Time:'" . $time . "', RRD Time:'" . $last_update . "' Ignoring Value!", false, 'BOOST', POLLER_VERBOSITY_HIGH);
				$value = 'DNP';
			} else {
				$value = trim($item['output']);
			}

			if ($time != $item['timestamp']) {
				if ($vals_in_buffer > 0) {
					/* place the latest update at the end of the output array */
					$outarray[] = $tv_tmpl;
				}

				if ($buflen > $upd_string_len) {
					/* new process output function */
					boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe);

					$buflen         = 0;
					$vals_in_buffer = 0;
					$outarray       = array();
				}

				$time = $item['timestamp'];
				$tv_tmpl = $rrd_tmplpts;
			}

			if (empty($tv_tmpl['timestamp']) && $value != 'DNP') {
				$tv_tmpl['timestamp'] = $item['timestamp'];
				$buflen += strlen($item['timestamp']) + 1;
			}

			/* single one value output */
			if (strpos($value, 'DNP') !== false) {
				/* continue, bad time */
			} elseif ((is_numeric($value)) || ($value == 'U')) {
				$tv_tmpl[$item['rrd_name']] = $value;
				$buflen += strlen(':' . $value);
				$vals_in_buffer++;
			} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($value))) {
				$tval = hexdec($value);
				$tv_tmpl[$item['rrd_name']] = $tval;
				$buflen += strlen(':' . $tval);
				$vals_in_buffer++;
			} elseif (strlen($value)) {
				/* break out multiple value output to an array */
				$values = preg_split('/\s+/', $value);

				if (!$multi_vals_set) {
					$rrd_field_names = array_rekey(
						db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
							FROM graph_templates_item AS gti
							INNER JOIN data_template_rrd AS dtr
							ON gti.task_item_id = dtr.id
							INNER JOIN data_input_fields AS dif
							ON dtr.data_input_field_id = dif.id
							AND dtr.local_data_id = ?',
							array($item['local_data_id'])),
						'data_name', 'data_source_name'
					);

					$rrd_tmpl = '';
				}

				$first_tmpl = true;
				$multi_ok   = false;

				if (cacti_sizeof($values)) {
					foreach($values as $value) {
						$matches = explode(':', $value);

						if (isset($rrd_field_names[$matches[0]])) {
							$multi_ok = true;

							if (trim(read_config_option('path_boost_log')) != '') {
								print "DEBUG: Parsed MULTI output field '" . $matches[0] . "' [map " . $matches[1] . '->' . $rrd_field_names[$matches[1]] . ']' . PHP_EOL;
							}

							if (!$multi_vals_set) {
								if (!$first_tmpl) {
									$rrd_tmpl .= ':';
								}

								$rrd_tmpl  .= $rrd_field_names[$matches[0]];
								$first_tmpl = false;
							}

							if (is_numeric($matches[1]) || ($matches[1] == 'U')) {
								$tv_tmpl[$rrd_field_names[$matches[0]]] = $matches[1];
								$buflen += strlen(':' . $matches[1]);
							} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($matches[1]))) {
								$tval = hexdec($matches[1]);
								$tv_tmpl[$rrd_field_names[$matches[0]]] = $tval;
								$buflen += strlen(':' . $tval);
							} else {
								$tv_tmpl[$rrd_field_names[$matches[0]]] = 'U';
								$buflen += 2;
							}
						}
					}
				}

				/* we only want to process the template and gather the fields once */
				$multi_vals_set = true;

				if ($multi_ok) {
					$vals_in_buffer++;
				}
			} else {
				cacti_log('WARNING: Local Data Id [' . $item['local_data_id'] . '] Contains an empty value', false, 'BOOST');
			}
		}

		/* process the last rrdupdate if applicable */
		if ($vals_in_buffer) {
			/* place the latest update at the end of the output array */
			$outarray[] = $tv_tmpl;

			boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe);
		}

		/* release the last lock */
		db_execute("SELECT RELEASE_LOCK('boost.single_ds." . $item['local_data_id'] . "')");

		$current_lock = false;

		boost_timer('results_cycle', BOOST_TIMER_END);
	}

	/* remove the entries from the table */
	boost_timer('delete', BOOST_TIMER_START);

	db_execute_prepared("DELETE FROM poller_output_boost_local_data_ids
		WHERE local_data_id <= ?
		AND process_handler = ?",
		array($last_id, $child));

	boost_timer('delete', BOOST_TIMER_END);

	/* restore original error handler */
	restore_error_handler();

	return cacti_sizeof($results);
}

function boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe) {
	$outbuf = '';
	if (cacti_sizeof($outarray)) {
		foreach($outarray as $tsdata) {
			$outbuf .= ($outbuf != '' ? ' ':'') . implode(':', $tsdata);
		}
	}

	$rrd_tmpl = implode(':', array_keys($rrd_tmplp));

	if (trim(read_config_option('path_boost_log')) != '') {
		print "DEBUG: Updating Local Data Id:'$local_data_id', Template:" . $rrd_tmpl . ', Output:' . $outbuf . PHP_EOL;
	}


	boost_timer('rrdupdate', BOOST_TIMER_START);
	$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $outbuf, $rrdtool_pipe);
	boost_timer('rrdupdate', BOOST_TIMER_END);

	/* check return status for delete operation */
	if (trim($return_value) != 'OK' && $return_value != '') {
		cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", false, 'BOOST');
	}
}

function boost_log_statistics($rrd_updates) {
	global $start, $boost_stats_log, $verbose;

	/* take time and log performance data */
	$end = microtime(true);

	$cacti_stats = sprintf(
		'Time:%01.2f ' .
		'RRDUpdates:%s',
		round($end-$start, 2),
		$rrd_updates);

	/* log to the database */
	set_config_option('stats_boost', $cacti_stats);

	/* log to the logfile */
	cacti_log('BOOST STATS: ' . $cacti_stats , true, 'SYSTEM');

	$output = array();
	$order  = array(
		'RRDUpdates',
		'TotalTime',
		'get_records',
		'results_cycle',
		'rrd_filename_and_template',
		'rrd_lastupdate',
		'rrdupdate',
		'delete'
	);

	$processes = read_config_option('boost_parallel');
	if (empty($processes)) {
		$processes = 1;
	}

	$stats = db_fetch_assoc('SELECT value
		FROM settings
		WHERE name LIKE "stats_detail_boost_%"');

	if (cacti_sizeof($stats)) {
		foreach($stats as $stat) {
			$stat = json_decode($stat['value']);

			foreach($stat as $key => $value) {
				if (isset($output[$key])) {
					$output[$key] += $value;
				} else {
					$output[$key]  = $value;
				}
			}
		}

		$outstr = '';

		foreach($order as $key) {
			if ($key == 'TotalTime') {
				$outstr .= ($outstr != '' ? ', ':'') . "$key:" . round($end-$start, 2);
			} elseif ($key == 'RRDUpdates') {
				$outstr .= ($outstr != '' ? ', ':'') . "$key:" . round($output[$key], 0);
			} else {
				$outstr .= ($outstr != '' ? ', ':'') . "$key:" . round($output[$key]/$processes, 0);
			}
		}

		/* log to the database */
		set_config_option('stats_detail_boost', str_replace(',', '', $outstr));

		/* log to the logfile */
		if ($verbose) {
			cacti_log('BOOST DETAIL STATS: ' . $outstr, true, 'SYSTEM');
		}
	}

	/* prune old process statistics if the number has changed */
	$processes = read_config_option('boost_parallel');
	$stats     = db_fetch_assoc('SELECT * FROM settings WHERE name LIKE "stats_boost_%"');
	if (cacti_sizeof($stats)) {
		foreach($stats as $stat) {
			$process = str_replace('stats_boost_', '', $stat['name']);
			if ($process > $processes) {
				db_execute_prepared('DELETE FROM settings WHERE name = ?', array('stats_boost_' . $process));
			}
		}
	}

	/* prune all detailed stats */
	db_execute('DELETE FROM settings WHERE name LIKE "stats_detail_boost_%"');
}

function boost_log_child_statistics($rrd_updates, $child) {
	global $start, $boost_stats_log, $verbose;

	/* take time and log performance data */
	$end = microtime(true);

	$cacti_stats = sprintf(
		'Time:%01.2f ' .
		'ProcessNumber:%s ' .
		'RRDUpdates:%s',
		round($end-$start, 2),
		$child,
		$rrd_updates);

	/* log to the database */
	set_config_option('stats_boost_' . $child, $cacti_stats);

	/* log to the logfile */
	cacti_log('BOOST STATS: ' . $cacti_stats , true, 'SYSTEM');

	if (isset($boost_stats_log)) {
		$overhead     = boost_timer_get_overhead();
		$output       = array();
		$timer_cycles = 0;

		foreach($boost_stats_log as $area => $entry) {
			if (isset($entry[BOOST_TIMER_TOTAL])) {
				$output[$area] = round($entry[BOOST_TIMER_TOTAL] - (($overhead * $entry[BOOST_TIMER_CYCLES])/BOOST_TIMER_OVERHEAD_MULTIPLIER), 2);
			}

			$timer_cycles += $entry[BOOST_TIMER_CYCLES];
		}

		if (cacti_sizeof($output)) {
			$output['RRDUpdates'] = $rrd_updates;
			$output['Process']    = $child;
			$output['TotalTime']  = round($end - $start, 0);

			$timer_overhead = round((($overhead * $timer_cycles)/BOOST_TIMER_OVERHEAD_MULTIPLIER), 0);

			if ($timer_overhead > 0) {
				$output['timer_overhead'] = $timer_overhead;
			}

			$output = json_encode($output);

			/* log to the database */
			set_config_option('stats_detail_boost_' . $child, $output);

			/* log to the logfile */
			if ($verbose) {
				cacti_log('BOOST DETAIL STATS: ' . $output, true, 'SYSTEM');
			}
		}
	}
}

function boost_purge_cached_png_files($forcerun) {
	/* remove stale png's from the cache.  I consider png's stale afer 1 hour */
	if ((read_config_option('boost_png_cache_enable') == 'on') || $forcerun) {
		$cache_directory = read_config_option('boost_png_cache_directory');
		$remove_time = time() - 3600;

		$directory_contents = array();

		if (is_dir($cache_directory)) {
			if ($handle = opendir($cache_directory)) {
				/* This is the correct way to loop over the directory. */
				while (false !== ($file = readdir($handle))) {
					$directory_contents[] = $file;
				}

				closedir($handle);
			}

			/* remove age old files */
			if (cacti_sizeof($directory_contents)) {
				/* goto the cache directory */
				chdir($cache_directory);

				/* check and fry as applicable */
				foreach($directory_contents as $file) {
					if (is_writable($file)) {
						$modify_time = filemtime($file);
						if ($modify_time < $remove_time) {
							/* only remove jpeg's and png's */
							if ((substr_count(strtolower($file), '.png')) ||
								(substr_count(strtolower($file), '.jpg'))) {
								unlink($file);
							}
						}
					}
				}
			}
		}
	}
}

/* do NOT run this script through a web browser */
/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	print "Cacti Boost RRD Update Poller, Version $version " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: poller_boost.php [--verbose] [--force] [--debug]\n\n";
	print "Cacti's performance boosting poller.  This poller will purge the boost cache periodically.  You may\n";
	print "force the processing of the boost cache by using the --force option.\n\n";
	print "Optional:\n";
	print "    --verbose - Show details logs at the command line\n";
	print "    --force   - Force the execution of a update process\n";
	print "    --debug   - Display verbose output during execution\n\n";
}

