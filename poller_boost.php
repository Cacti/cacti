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
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/boost.php');
require_once(CACTI_PATH_LIBRARY . '/dsstats.php');
require_once(CACTI_PATH_LIBRARY . '/rrdcheck.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');

// get the boost polling cycle
$max_run_duration = read_config_option('boost_rrd_update_max_runtime');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = false;
$forcerun = false;
$verbose  = false;
$child    = 0;

// for releasing lock on SIGNAL
$current_lock = false;

global $child, $next_run_time, $archive_table, $current_lock;
global $boost_debug, $boost_log, $cacti_log;

// Archive tables that boost_prepare_process_table() assigned to this run. Only
// these may be dropped at the end; tables from a later rotation or an older
// crashed run can still hold unprocessed rows.
global $boost_run_arch_tables;

/** @var array<int, string> $boost_run_arch_tables Populated by boost_prepare_process_table() through the global. */
$boost_run_arch_tables = [];

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--child':
				$child = intval($value);

				break;
			case '--archive-table':
				if (preg_match('/^poller_output_boost_arch_\d+$/', $value)) {
					$archive_table = $value;
				}

				break;
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '-f':
			case '--force':
				$forcerun = true;
				cacti_log('WARNING: Boost Poller forced by command line.', true, 'BOOST');

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

// install signal handlers for UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

// take time and log performance data
$start       = microtime(true);
$start_time  = time();
$rrd_updates = -1;

// let's give this script lot of time to run for ever
ini_set('max_execution_time', '0');
boost_memory_limit();

$boost_debug = read_config_option('boost_debug_enabled') == 'on' ? true : false;
$boost_log   = read_config_option('path_boost_log');
$cacti_log   = read_config_option('path_cactilog');

if ($child == false) {
	$current_time  = time();

	/* find out if it's time to collect device information
	 * support both old and new formats.
	 */
	$boost_last_run_time = read_config_option('boost_last_run_time') ?? ($current_time - 3600);

	if (!is_numeric($boost_last_run_time)) {
		$last_run_time = strtotime($boost_last_run_time);
	} elseif (empty($boost_last_run_time)) {
		$last_run_time = time() - 3600;
	} else {
		$last_run_time = $boost_last_run_time;
	}

	$boost_next_run_time = read_config_option('boost_next_run_time');

	if (!empty($boost_next_run_time) && !is_numeric($boost_next_run_time)) {
		$next_run_time = strtotime($boost_next_run_time);
	} elseif (empty($boost_next_run_time)) {
		$next_run_time = time() + 3600;
	} else {
		$next_run_time = $boost_next_run_time;
	}

	$seconds_offset = read_config_option('boost_rrd_update_interval') * 60;

	$run_now = boost_time_to_run($forcerun, $current_time, $last_run_time, $next_run_time);

	if ($run_now) {
		/**
		 * Check to see if the boost log is enabled and the file exists and
		 * is writable.  If it does not exist, create an empty file.
		 */
		if ($boost_debug && $boost_log != '') {
			if (dirname($cacti_log) != dirname($boost_log)) {
				cacti_log(sprintf('WARNING: Boost Debug Log location:%s must be in the same directory as the Cacti Log location:%s.  Change the path to a correct location', $boost_log, $cacti_log), true, 'BOOST');
			} elseif (!file_exists($boost_log)) {
				if (is_writable(dirname($boost_log))) {
					touch($boost_log);
				} else {
					cacti_log(sprintf('WARNING: Boost Debug Log %s is not writable.  Change the path to a writable location', $boost_log), true, 'BOOST');
				}
			}
		}

		/**
		 * Check to see if there are any poller items to process and if not
		 * exit cleanly
		 */
		$poller_items = db_fetch_row('SELECT * FROM poller_output_boost LIMIT 1');

		if (!cacti_sizeof($poller_items)) {
			cacti_log('INFO: Boost has no items in poller_output_boost to process during this cycle.', true, 'BOOST');

			exit(0);
		}

		// we will warn if the process is taking extra long
		if (!register_process_start('boost', 'master', POLLER_ID, read_config_option('boost_rrd_update_max_runtime') * 3)) {
			exit(0);
		}

		boost_debug('Time to Run Boost, Force Run is ' . ($forcerun ? 'true!' : 'false.'));

		// Check if processes are running and kill them
		boost_kill_running_processes();

		// Truncate the rrd_update_counter table
		db_execute('TRUNCATE TABLE poller_output_boost_processes');

		// Prepare the boost distribution
		cacti_log('INFO: Boost preparing tables ...', true, 'BOOST');
		$time_start = time();
		$continue   = boost_prepare_process_table();
		$time_end   = time();
		cacti_log('INFO: Boost prepare tables took ' . ($time_end - $time_start) . ' seconds.', true, 'BOOST');

		// prune old memory stats
		boost_prune_memstats();

		// Launch the boost children
		if ($continue) {
			cacti_log('INFO: Boost spawning child processes ...', true, 'BOOST');
			$expected_children = boost_launch_children();

			// exec_background() is non-blocking; children register and finish
			// independently. Wait until all launched children are accounted for
			// (running or already recorded a completion row) before draining.
			// Releasing on the first registration lets a fast child finish, drop
			// the running count to 0, and trip the drain exit while siblings are
			// still booting -- the parent then drops the archive tables out from
			// under them.
			$startup_deadline = time() + 30;

			while (!boost_all_children_registered($expected_children, boost_processes_running(), boost_completed_children()) && time() < $startup_deadline) {
				sleep(1);
			}

			if (!boost_all_children_registered($expected_children, boost_processes_running(), boost_completed_children())) {
				cacti_log(sprintf('WARNING: Boost startup barrier timed out; %d of %d children registered before draining.', boost_processes_running() + boost_completed_children(), $expected_children), true, 'BOOST');
			}

			// Drain until no child is running and every launched child has
			// recorded a completion row, not merely when none are running -- a
			// sibling may not have started yet.
			while (boost_processes_running() > 0 || boost_completed_children() < $expected_children) {
				boost_debug(sprintf('%d Processes Running, %d of %d Completed, Sleeping for 2 seconds.', boost_processes_running(), boost_completed_children(), $expected_children));
				sleep(2);

				if (boost_processes_running() === 0 && boost_completed_children() < $expected_children) {
					// All registered children exited but fewer completion rows than
					// expected: a child crashed before recording status. Stop waiting
					// so the parent does not spin forever.
					cacti_log(sprintf('WARNING: Boost drained with %d of %d completion rows; a child may have crashed.', boost_completed_children(), $expected_children), true, 'BOOST');

					break;
				}
			}

			cacti_log('INFO: Boost last child processes ended.', true, 'BOOST');

			// tell the main poller that we are done
			set_config_option('boost_poller_status', 'complete - end time:' . date('Y-m-d H:i:s'));

			// Finish processing post
			set_config_option('boost_last_run_time', $current_time);

			// output all the rrd data to the rrd files
			$rrd_updates = db_fetch_cell('SELECT SUM(status) FROM poller_output_boost_processes');

			if ($rrd_updates > 0) {
				boost_log_statistics($rrd_updates);
				$next_run_time = $current_time + $seconds_offset;
			} elseif ($rrd_updates == -1) {
				boost_log_statistics(0);
				$next_run_time = $current_time + $seconds_offset;
			} else { // rollback last run time
				set_config_option('boost_last_run_time', $last_run_time);
			}

			if ($rrd_updates > 0) {
				cacti_log('INFO: Boost removing archive tables ...', true, 'BOOST');

				// Drop only the tables this run owned. A table created by a later
				// rotation, or left by an earlier crashed run, may still hold rows
				// that have not been processed; matching on the LIKE pattern would
				// destroy those too.
				if (cacti_sizeof($boost_run_arch_tables)) {
					foreach ($boost_run_arch_tables as $table) {
						if (!boost_is_valid_archive_table($table)) {
							continue;
						}

						cacti_log('INFO: Boost removing archive table: ' . $table, true, 'BOOST');

						db_execute("DROP TABLE IF EXISTS `$table`");
					}
				}

				dsstats_boost_bottom();
				rrdcheck_boost_bottom();

				api_plugin_hook('boost_poller_bottom');
			}
		} else {
			// boost_prepare_process_table() set status to 'running' before returning
			// false; clear it now so the next run does not trigger a false Overrun warning.
			set_config_option('boost_poller_status', 'complete - end time:' . date('Y-m-d H:i:s'));
		}

		cacti_log('INFO: Boost unregistering master process', true, 'BOOST');

		unregister_process('boost', 'master', POLLER_ID, getmypid());

		// log the end time of the process
		set_config_option('boost_last_end_time', time());
	} else {
		set_config_option('boost_poller_status', 'complete');
	}

	// store the next run time so that people understand
	if ($rrd_updates > 0 || $rrd_updates == -1) {
		if (empty($next_run_time)) {
			$next_run_time = time() + $seconds_offset;
		}

		set_config_option('boost_next_run_time', $next_run_time);
	}

	boost_purge_cached_png_files($forcerun);

	exit(0);
} else {
	cacti_log('INFO: Boost register child process ' . $child, true, 'BOOST');

	// we will warn if the process is taking extra long
	if (!register_process_start('boost', 'child', $child, read_config_option('boost_rrd_update_max_runtime') * 3)) {
		exit(0);
	}

	// output all the rrd data to the rrd files
	$rrd_updates = boost_output_rrd_data($child);

	db_execute_prepared('INSERT INTO poller_output_boost_processes
		(status) VALUES (?)',
		[$rrd_updates]);

	boost_log_child_statistics($rrd_updates, $child);

	unregister_process('boost', 'child', $child);

	exit(0);
}

function sig_handler(int $signo) : void {
	global $child, $current_lock;

	$rrdtool_version = read_config_option('rrdtool_version');

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Boost Poller terminated by user', true, 'BOOST');

			// only the parent tracks overall poller status
			if (!$child) {
				set_config_option('boost_poller_status', 'terminated - end time:' . date('Y-m-d H:i:s'));
			}

			// release any held GET_LOCK() before exiting; rrdtool >= 1.5 does
			// not use these locks, so skip on modern installs
			if (cacti_version_compare(get_rrdtool_version(), '1.5', '<')) {
				if ($current_lock !== false && $child) {
					db_execute_prepared('SELECT RELEASE_LOCK(?)', ["boost.single_ds.$current_lock"]);
				} elseif (!$child) {
					db_execute_prepared('SELECT RELEASE_ALL_LOCKS()', []);
				}
			}

			if ($child) {
				unregister_process('boost', 'child', $child, getmypid());
			} else {
				unregister_process('boost', 'master', POLLER_ID, getmypid());
			}

			exit;
		default:
			// ignore all other signals
	}
}

function boost_kill_running_processes() : void {
	$processes = db_fetch_assoc_prepared('SELECT *
		FROM processes
		WHERE tasktype = "boost"
		AND pid != ?',
		[getmypid()]);

	if (cacti_sizeof($processes)) {
		foreach ($processes as $p) {
			cacti_log(sprintf('WARNING: Killing Boost %s PID %d due to another boost process starting.', ucfirst($p['taskname']), $p['pid']), true, 'BOOST');

			posix_kill($p['pid'], SIGTERM);

			unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $p['pid']);
		}
	}
}

function boost_processes_running() : int {
	$running = db_fetch_cell('SELECT COUNT(*)
		FROM processes
		WHERE tasktype = "boost"
		AND taskname = "child"');

	return (int) $running;
}

function boost_completed_children() : int {
	// Each child inserts one status row when it finishes, so the row count is
	// the number of children that have completed this run.
	return (int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost_processes');
}

function boost_prepare_process_table() : bool {
	global $start_time, $archive_table, $max_run_duration, $database_default, $debug, $get_memory, $memory_used;
	global $boost_run_arch_tables;

	boost_debug('Parallel Process Setup Begins.');

	$boost_poller_status = read_config_option('boost_poller_status');

	if (!$boost_poller_status) {
		$boost_poller_status = 'not started';
	}

	// detect a process that has overrun it's warning time
	if (substr_count($boost_poller_status, 'running')) {
		$status_array = explode(':', $boost_poller_status);

		if (!empty($status_array[1])) {
			$previous_start_time = strtotime($status_array[1]);

			// if the runtime was exceeded, allow the next process to run
			if ($previous_start_time + $max_run_duration < $start_time) {
				cacti_log('WARNING: Detected Poller Boost Overrun, Possible Boost Poller Crash', true, 'BOOST SVR');

				admin_email(__('Cacti System Warning'), __('WARNING: Detected Poller Boost Overrun, Possible Boost Poller Crash', 'BOOST SVR'));
			}
		}
	}

	// if the poller is not running, or has never run, start
	// mark the boost server as running
	set_config_option('boost_poller_status', 'running - start time:' . date('Y-m-d H:i:s'));

	$delayed_inserts = db_fetch_row("SHOW STATUS LIKE 'Not_flushed_delayed_rows'");

	while (cacti_sizeof($delayed_inserts) && $delayed_inserts['Value']) {
		cacti_log('BOOST WAIT: Waiting 1s for delayed inserts are made' , true, 'SYSTEM');
		usleep(1000000);
		$delayed_inserts = db_fetch_row("SHOW STATUS LIKE 'Not_flushed_delayed_rows'");
	}

	$time = time();

	// split poller_output_boost
	$archive_table = 'poller_output_boost_arch_' . $time;
	$interim_table = 'poller_output_boost_' . $time;

	cacti_log('INFO: Boost rotating poller_output_boost into archive table: ' . $archive_table, true, 'BOOST');
	db_execute("CREATE TABLE `{$interim_table}` LIKE poller_output_boost");
	db_execute("RENAME TABLE `poller_output_boost` TO `{$archive_table}`, `{$interim_table}` TO `poller_output_boost`");
	db_execute("ANALYZE TABLE `{$archive_table}`");
	cacti_log('INFO: Boost done rotating poller_output_boost', true, 'BOOST');

	$arch_tables = boost_get_arch_table_names($archive_table);

	if (!cacti_sizeof($arch_tables)) {
		cacti_log('ERROR: Failed to retrieve archive table name - check poller', true, 'BOOST');

		return false;
	}

	// Record the tables this run owns so the end-of-run cleanup drops only
	// these, never a table created by a later rotation or left by a prior run.
	$boost_run_arch_tables = array_values($arch_tables);

	$total_rows     = 0;
	$per_table_rows = [];

	cacti_log('INFO: Boost counting entries in archive tables ...', true, 'BOOST');

	foreach ($arch_tables as $table) {
		$table_rows = (int) db_fetch_cell("SELECT COUNT(*) FROM `{$table}`");

		$total_rows += $table_rows;
		$per_table_rows[$table] = $table_rows;

		cacti_log('INFO: Boost archive table ' . $table . ' has ' . $table_rows . ' entries.', true, 'BOOST');
	}

	if ($total_rows == 0) {
		boost_debug('ERROR: Failed to retrieve any rows from archive tables');

		cacti_log('ERROR: Failed to retrieve any rows from archive tables', true, 'BOOST');

		// Drop only confirmed-empty arch tables; skip any whose COUNT(*) was
		// non-zero to avoid data loss if boost_get_arch_table_names returned
		// a table from a prior run that still holds unprocessed rows.
		foreach ($per_table_rows as $table => $rows) {
			if ($rows === 0) {
				db_execute("DROP TABLE IF EXISTS `{$table}`");
			}
		}

		return false;
	} else {
		cacti_log('INFO: Boost processing a total of ' . $total_rows . ' entries.', true, 'BOOST');
	}

	db_execute('CREATE TABLE IF NOT EXISTS poller_output_boost_local_data_ids (
		local_data_id int unsigned default "0",
		process_handler int unsigned default "0",
		PRIMARY KEY (local_data_id),
		INDEX process_handler(process_handler))
		ENGINE=InnoDB');

	db_execute('TRUNCATE poller_output_boost_local_data_ids');

	foreach ($arch_tables as $table) {
		db_execute("INSERT IGNORE INTO poller_output_boost_local_data_ids
			(local_data_id)
			SELECT DISTINCT local_data_id
			FROM $table");
	}

	$data_ids = db_fetch_cell('SELECT
		COUNT(local_data_id)
		FROM poller_output_boost_local_data_ids');

	$processes = boost_clamp_parallel(read_config_option('boost_parallel'));

	boost_debug("Data Sources:$data_ids, Concurrent Processes:$processes");

	$data_ids_per_process = ceil($data_ids / $processes);

	$count = 1;

	while ($count <= $processes) {
		db_execute_prepared('UPDATE poller_output_boost_local_data_ids
			SET process_handler = ?
			WHERE process_handler = 0
			LIMIT ' . $data_ids_per_process,
			[$count]);

		$count++;
	}

	boost_debug('Parallel Process Setup Complete.  Ready to spawn children.');

	return true;
}

function boost_prune_memstats() : void {
	$processes = read_config_option('boost_parallel');

	db_execute_prepared('DELETE FROM settings
		WHERE name LIKE "boost_peak_memory%"
		AND REPLACE(name, "boost_peak_memory_", "") > ?',
		[$processes]);
}

function boost_launch_children() : int {
	global $debug, $archive_table, $boost_log, $boost_debug, $cacti_log;

	if (!boost_is_valid_archive_table($archive_table)) {
		cacti_log('ERROR: Boost refusing to launch children: archive table not set or invalid', true, 'BOOST');

		return 0;
	}

	$processes = boost_clamp_parallel(read_config_option('boost_parallel'));

	$php_binary    = read_config_option('path_php_binary');
	$redirect_args = '';

	if ($boost_debug && $boost_log != '') {
		// redirect_args bypasses per-argument escaping, so reject paths with
		// shell metacharacters. boost_log_path_is_safe() permits Windows drive
		// colons, backslashes, and spaces on win32 without weakening the check.
		if (!boost_log_path_is_safe($boost_log)) {
			cacti_log('WARNING: Boost log path contains unsafe characters; redirect disabled.', true, 'BOOST');
		} elseif (!is_writable($boost_log)) {
			boost_debug("WARNING: Boost log '$boost_log' is not writable!");

			cacti_log("WARNING: Boost log '$boost_log' is not writable!", true, 'BOOST');
		} else {
			$redirect_args = '>> ' . $boost_log;
		}
	}

	boost_debug("About to launch $processes processes.");

	for ($i = 1; $i <= $processes; $i++) {
		boost_debug('Launching Boost Process Number ' . $i);

		cacti_log('NOTE: Launching Boost Process Number ' . $i, true, 'BOOST', POLLER_VERBOSITY_MEDIUM);

		$child_args = [
			CACTI_PATH_BASE . '/poller_boost.php',
			'--child=' . $i,
			'--archive-table=' . $archive_table,
		];

		if ($debug) {
			$child_args[] = '--debug';
		}

		exec_background($php_binary, $child_args, $redirect_args);
	}

	sleep(2);

	return $processes;
}

function boost_time_to_run(bool $forcerun, int $current_time, int $last_run_time, int $next_run_time) : bool {
	$run_now = false;

	boost_debug('Checking if Boost is ready to run.');

	if ((read_config_option('boost_rrd_update_enable') == 'on') || $forcerun) {
		// turn on the system level updates as that is what dictates "on/off"
		if (!$forcerun && read_config_option('boost_rrd_update_system_enable') != 'on') {
			set_config_option('boost_rrd_update_system_enable', 'on');
		}

		$seconds_offset = read_config_option('boost_rrd_update_interval') * 60;

		// Initialize seconds offset, if not set to 2 hours.
		// boost_rrd_update_interval is stored in minutes; multiply to get seconds.
		if (empty($seconds_offset)) {
			set_config_option('boost_rrd_update_interval', 120);
			$seconds_offset = 120 * 60;
		}

		boost_debug('Last Runtime was ' . date('Y-m-d H:i:s', $last_run_time) . " ($last_run_time).");
		boost_debug('Next Runtime is ' . date('Y-m-d H:i:s', $next_run_time) . " ($next_run_time).");

		// determine the next start time
		if (empty($last_run_time)) {
			// since the poller has never run before, let's fake it out
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

		// determine if you must output boost table now
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
			// turn off the system level updates, we want to disable
			set_config_option('boost_rrd_update_system_enable', '');
		}

		// we are force to run until boost is finished
		$rows = boost_get_total_rows();

		if ($rows > 0) {
			$run_now = true;
		}
	}

	return $run_now;
}

function boost_output_rrd_data(int $child) : mixed {
	global $start, $archive_table, $max_run_duration, $database_default, $debug, $get_memory, $memory_used;

	$rrd_updates      = 0;
	$rrdtool_pipe     = rrd_init();
	$runtime_exceeded = false;

	// let's set and track memory usage will we
	if (!function_exists('memory_get_peak_usage')) {
		$get_memory   = true;
		$memory_used  = memory_get_usage();
	} else {
		$get_memory   = false;
	}

	boost_debug("Processing RRRtool Output for Boost Process $child");

	$arch_tables = boost_get_arch_table_names($archive_table);

	if (!cacti_sizeof($arch_tables)) {
		cacti_log('ERROR: Failed to retrieve archive table name', true, 'BOOST');

		return 0;
	}

	$total_rows = 0;

	foreach ($arch_tables as $table) {
		$total_rows += db_fetch_cell_prepared("SELECT COUNT(at.local_data_id)
			FROM $table AS at
			INNER JOIN poller_output_boost_local_data_ids AS bpt
			ON at.local_data_id = bpt.local_data_id
			AND bpt.process_handler = ?",
			[$child]);
	}

	if ($total_rows == 0) {
		return 0;
	}

	boost_debug("Processes:$child, TotalRows:$total_rows");

	$max_per_select = intval(read_config_option('boost_rrd_update_max_records_per_select'));

	if ($max_per_select <= 0) {
		$max_per_select = 50000;
	}

	$data_ids = db_fetch_cell_prepared('SELECT
		COUNT(local_data_id)
		FROM poller_output_boost_local_data_ids
		WHERE process_handler = ?',
		[$child]);

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
			[$child]);

		if (empty($last_id)) {
			break;
		}

		boost_process_local_data_ids($last_id, $child, $rrdtool_pipe);

		$curpass++;

		$data_ids = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM poller_output_boost_local_data_ids
			WHERE process_handler = ?',
			[$child]);

		if (((time() - $start) > $max_run_duration) && (!$runtime_exceeded)) {
			cacti_log('WARNING: RRD On Demand Updater Exceeded Runtime Limits. Continuing to Process!!!', true, 'BOOST');

			$runtime_exceeded = true;
		}
	}

	boost_debug("Processing Complete for Boost Process $child.  It took $curpass passed to complete.");

	// log memory usage
	if (function_exists('memory_get_peak_usage')) {
		set_config_option('boost_peak_memory_' . $child, memory_get_peak_usage());
	} else {
		set_config_option('boost_peak_memory_' . $child, $memory_used);
	}

	rrd_close($rrdtool_pipe);

	return $total_rows;
}

/**
 * boost_process_local_data_ids - grabs data from the 'poller_output' table and feeds the *completed*
 * results to RRDTool for processing
 *
 * @param int   $last_id      The last id to process
 * @param int   $child        The current process
 * @param mixed $rrdtool_pipe The socket that has been opened for the RRDtool operation
 *
 * @return int The number of processed local_data_ids
 */
function boost_process_local_data_ids(int $last_id, int $child, mixed $rrdtool_pipe) : int {
	global $archive_table, $boost_sock, $boost_timeout, $debug, $get_memory, $memory_used, $current_lock;
	global $boost_debug, $boost_log;

	// cache this call as it takes time
	static $archive_tables  = false;
	static $rrdtool_version = null;

	require_once(CACTI_PATH_LIBRARY . '/rrd.php');

	// suppress warnings
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	// gather, repair if required and cache the rrdtool version
	if ($rrdtool_version === null) {
		$rrdtool_ins_version = get_installed_rrdtool_version();
		$rrdtool_version     = get_rrdtool_version();

		if ($rrdtool_ins_version != $rrdtool_version) {
			boost_debug('Updating Stored RRDtool version to installed version ' . $rrdtool_ins_version);

			cacti_log('NOTE: Updating Stored RRDtool version to installed version ' . $rrdtool_ins_version, true, 'BOOST');

			set_config_option('rrdtool_version', $rrdtool_ins_version);
			$rrdtool_version = $rrdtool_ins_version;
		}
	}

	// install the boost error handler
	set_error_handler('boost_error_handler');

	// load system variables needed
	$upd_string_len      = intval(read_config_option('boost_rrd_update_string_length'));
	$rrd_update_interval = intval(read_config_option('boost_rrd_update_interval'));
	$data_ids_to_get     = intval(read_config_option('boost_rrd_update_max_records_per_select'));
	$rrd_field_names     = [];

	if ($data_ids_to_get <= 0) {
		$data_ids_to_get = 50000;
	}

	if ($archive_tables === false) {
		$archive_tables = boost_get_arch_table_names($archive_table);
	}

	if ($archive_tables === false) {
		boost_debug('Failed to determine archive tables');

		cacti_log('Failed to determine archive tables', true, 'BOOST');

		return 0;
	}

	if (!cacti_sizeof($rrd_field_names)) {
		$rrd_field_names = array_rekey(
			db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . '
				CONCAT(data_template_id, "_", data_name) AS keyname, data_source_names AS data_source_name
				FROM poller_data_template_field_mappings'),
			'keyname', ['data_source_name']);
	}

	$query_string        = 'SELECT * FROM (';
	$query_string_suffix = 'ORDER BY local_data_id ASC, timestamp ASC, rrd_name ASC';

	$sub_query_string = '';

	foreach ($archive_tables as $table) {
		$sub_query_string .= ($sub_query_string != '' ? ' UNION ALL ' : '') .
			" SELECT $table.local_data_id, dl.data_template_id, UNIX_TIMESTAMP(time) AS timestamp, rrd_name, output
			FROM $table
			INNER JOIN poller_output_boost_local_data_ids AS bpt
			ON $table.local_data_id = bpt.local_data_id
			INNER JOIN data_local AS dl
			ON $table.local_data_id = dl.id
			WHERE bpt.local_data_id <= $last_id
			AND bpt.process_handler = $child";
	}

	$query_string = $query_string . $sub_query_string . ') t ' . $query_string_suffix;

	boost_timer('get_records', BOOST_TIMER_START);
	$results = db_fetch_assoc($query_string);
	boost_timer('get_records', BOOST_TIMER_END);

	// log memory
	if ($get_memory) {
		$cur_memory = memory_get_usage();

		if ($cur_memory > $memory_used) {
			$memory_used = $cur_memory;
		}
	}

	if (cacti_sizeof($results)) {
		// create an array keyed off of each .rrd file
		$local_data_id  = -1;
		$time           = -1;
		$buflen         = 0;
		$outarray       = [];
		$locked         = false;
		$last_update    = -1;
		$reset_template = true;

		$unused_data_source_names = [];

		// we are going to blow away all record if ok
		$vals_in_buffer = 0;

		// initialize some variables
		$rrd_tmpl           = '';
		$rrd_tmplp          = [];
		$rrd_tmplpts        = 0;
		$rrd_path           = '';
		$nt_rrd_field_names = [];
		$tv_tmpl            = [];

		boost_timer('results_cycle', BOOST_TIMER_START);

		// go through each poller_output_boost entries and process
		foreach ($results as $item) {
			if ($local_data_id == $item['local_data_id'] && cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$item['rrd_name']])) {
				continue;
			}

			$item['timestamp'] = trim($item['timestamp']);

			if (!$locked) {
				// acquire lock in order to prevent race conditions, only a problem pre-rrdtool 1.5
				if (cacti_version_compare($rrdtool_version, '1.5', '<')) {
					while (!db_fetch_cell_prepared('SELECT GET_LOCK(?, 1)', ['boost.single_ds.' . $item['local_data_id']])) {
						usleep(50000);
					}
				}

				$current_lock = $item['local_data_id'];

				$locked = true;
			}

			/**
			 * if the local_data_id changes, we need to flush the buffer
			 * and discover the template for the next RRDfile.
			 */
			if ($local_data_id != $item['local_data_id']) {
				$unused_data_source_names = array_rekey(
					db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
						FROM data_template_rrd AS dtr
						LEFT JOIN graph_templates_item AS gti
						ON dtr.id = gti.task_item_id
						WHERE dtr.local_data_id = ?
						AND gti.task_item_id IS NULL',
						[$item['local_data_id']]),
					'data_source_name', 'data_source_name'
				);

				if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$item['rrd_name']])) {
					continue;
				}

				$reset_template = true;

				$nt_rrd_field_names = [];

				// release the previous lock
				if (cacti_version_compare($rrdtool_version, '1.5', '<')) {
					db_execute_prepared('SELECT RELEASE_LOCK(?)', ["boost.single_ds.$local_data_id"]);
				}

				$current_lock = false;

				// acquire lock in order to prevent race conditions, only a problem pre-rrdtool 1.5
				if (cacti_version_compare($rrdtool_version, '1.5', '<')) {
					while (!db_fetch_cell_prepared('SELECT GET_LOCK(?, 1)', ['boost.single_ds.' . $item['local_data_id']])) {
						usleep(50000);
					}
				}

				$current_lock = $item['local_data_id'];

				// update the rrd for the previous local_data_id
				if ($vals_in_buffer) {
					// place the latest update at the end of the output array
					$outarray[] = $tv_tmpl;

					// new process output function
					boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe);

					$buflen         = 0;
					$vals_in_buffer = 0;
					$outarray       = [];
				}

				// reset the rrd file path and templates, assume non multi output
				boost_timer('rrd_filename_and_template', BOOST_TIMER_START);
				$rrd_data     = boost_get_rrd_filename_and_template($item['local_data_id']);
				$rrd_tmpl     = $rrd_data['rrd_template'];
				$template_len = strlen($rrd_tmpl);

				/**
				 * take the template and turn into an associative array of
				 * data source names with a default of 'U' for each value
				 * and creating the first value to include the timestamp.
				 * We will use this for missing data detection.
				 */
				$rrd_tmplp   = array_fill_keys(explode(':', $rrd_tmpl), 'U');
				$rrd_tmplpts = ['timestamp' => ''] + $rrd_tmplp;

				$rrd_path    = $rrd_data['rrd_path'];
				boost_timer('rrd_filename_and_template', BOOST_TIMER_END);

				if (cacti_version_compare(get_rrdtool_version(), '1.5', '<')) {
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

				if ($time > $last_update || cacti_version_compare(get_rrdtool_version(), '1.5', '>=')) {
					$buflen += strlen(' ' . $time);
				}

				$tv_tmpl = $rrd_tmplpts;
			} else {
				$reset_template = false;
			}

			// don't generate error messages if the RRD has already been updated
			if ($time < $last_update && cacti_version_compare(get_rrdtool_version(), '1.5', '<')) {
				cacti_log("WARNING: Stale Poller Data Found! Item Time:'" . $time . "', RRD Time:'" . $last_update . "' Ignoring Value!", true, 'BOOST', POLLER_VERBOSITY_HIGH);

				$value = 'DNP';
			} else {
				$value = trim($item['output']);
			}

			if ($time != $item['timestamp']) {
				if ($vals_in_buffer > 0) {
					// place the latest update at the end of the output array
					$outarray[] = $tv_tmpl;
				}

				if ($buflen > $upd_string_len) {
					// new process output function
					boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe);

					$buflen         = 0;
					$vals_in_buffer = 0;
					$outarray       = [];
				}

				$time    = $item['timestamp'];
				$tv_tmpl = $rrd_tmplpts;
			}

			if (empty($tv_tmpl['timestamp']) && $value != 'DNP') {
				$tv_tmpl['timestamp'] = $item['timestamp'];
				$buflen += strlen($item['timestamp']) + 1;
			}

			// single one value output
			if (str_contains($value, 'DNP')) {
				// continue, bad time
			} elseif ((is_numeric($value)) || ($value == 'U' && $item['rrd_name'] !== '')) {
				$tv_tmpl[$item['rrd_name']] = $value;
				$buflen += strlen(':' . $value);
				$vals_in_buffer++;
			} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($value))) {
				$tval                       = hexdec($value);
				$tv_tmpl[$item['rrd_name']] = $tval;
				$buflen += strlen(':' . $tval);
				$vals_in_buffer++;
			} elseif (str_contains($value, ':')) {
				// break out multiple value output to an array
				$values = preg_split('/\s+/', $value);

				if (!$reset_template) {
					$rrd_tmpl = '';
				} else {
					if ($item['data_template_id'] > 0) {
						$unused_data_source_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
								FROM data_template_rrd AS dtr
								LEFT JOIN graph_templates_item AS gti
								ON dtr.id = gti.task_item_id
								WHERE dtr.local_data_id = ? AND gti.task_item_id IS NULL',
								[$item['local_data_id']]),
							'data_source_name', 'data_source_name'
						);
					} else {
						$unused_data_source_names = [];
					}
				}

				foreach ($values as $value) {
					$matches = explode(':', $value);

					if (cacti_sizeof($matches) == 2) {
						if (isset($rrd_field_names[$item['data_template_id'] . '_' . $matches[0]])) {
							$field = $rrd_field_names[$item['data_template_id'] . '_' . $matches[0]]['data_source_name'];

							if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
								continue;
							}

							if ($reset_template) {
								boost_debug("Parsed MULTI output field in path 1 '" . $matches[0] . "' [map " . $field . '->' . $field . ']');

								$rrd_tmpl .= ($rrd_tmpl != '' ? ':' : '') . $field;
							}

							if (is_numeric($matches[1]) || ($matches[1] == 'U')) {
								$tv_tmpl[$field] = $matches[1];
								$buflen += strlen(':' . $matches[1]);
							} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($matches[1]))) {
								$tval            = hexdec($matches[1]);
								$tv_tmpl[$field] = $tval;
								$buflen += strlen(':' . $tval);
							} else {
								$tv_tmpl[$field] = 'U';
								$buflen += 2;
							}

							$vals_in_buffer++;
						} else {
							/**
							 * We have to check for Non-Templated Data Source first as they may not include
							 * a graph.  So, for that case, we need the RRDfile to include all data sources
							 */
							if ($item['data_template_id'] > 0) {
								$nt_rrd_field_names = array_rekey(
									db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
										FROM graph_templates_item AS gti
										INNER JOIN data_template_rrd AS dtr
										ON gti.task_item_id = dtr.id
										INNER JOIN data_input_fields AS dif
										ON dtr.data_input_field_id=dif.id
										WHERE dtr.local_data_id = ?',
										[$item['local_data_id']]),
									'data_name', 'data_source_name'
								);
							} else {
								$nt_rrd_field_names = array_rekey(
									db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
										FROM data_template_rrd AS dtr
										INNER JOIN data_input_fields AS dif
										ON dtr.data_input_field_id=dif.id
										WHERE dtr.local_data_id = ?',
										[$item['local_data_id']]),
									'data_name', 'data_source_name'
								);
							}

							if (cacti_sizeof($nt_rrd_field_names)) {
								if (isset($nt_rrd_field_names[$matches[0]])) {
									$field = $nt_rrd_field_names[$matches[0]];

									if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
										continue;
									}

									if ($reset_template) {
										boost_debug("Parsed MULTI output field '" . $matches[0] . "' [map " . $matches[1] . '->' . $field . ']');

										$rrd_tmpl .= ($rrd_tmpl != '' ? ':' : '') . $field;
									}

									if (is_numeric($matches[1]) || ($matches[1] == 'U')) {
										$tv_tmpl[$field] = $matches[1];
										$buflen += strlen(':' . $matches[1]);
									} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($matches[1]))) {
										$tval            = hexdec($matches[1]);
										$tv_tmpl[$field] = $tval;
										$buflen += strlen(':' . $tval);
									} else {
										$tv_tmpl[$field] = 'U';
										$buflen += 2;
									}

									boost_debug("Parsed MULTI output field '" . $matches[0] . "' [map " . $matches[1] . '->' . $nt_rrd_field_names[$matches[1]] . ']');
								}

								$vals_in_buffer++;
							}
						}
					}
				}
			} else {
				if ($reset_template) {
					if ($item['data_template_id'] > 0) {
						$unused_data_source_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
								FROM data_template_rrd AS dtr
								LEFT JOIN graph_templates_item AS gti
								ON dtr.id = gti.task_item_id
								WHERE dtr.local_data_id = ? AND gti.task_item_id IS NULL',
								[$item['local_data_id']]),
							'data_source_name', 'data_source_name'
						);

						$nt_rrd_field_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
								FROM graph_templates_item AS gti
								INNER JOIN data_template_rrd AS dtr
								ON gti.task_item_id = dtr.id
								INNER JOIN data_input_fields AS dif
								ON dtr.data_input_field_id=dif.id
								WHERE dtr.local_data_id = ?',
								[$item['local_data_id']]),
							'data_name', 'data_source_name'
						);
					} else {
						$unused_data_source_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
								FROM data_template_rrd AS dtr
								LEFT JOIN graph_templates_item AS gti
								ON dtr.id = gti.task_item_id
								WHERE dtr.local_data_id = ? AND gti.task_item_id IS NULL',
								[$item['local_data_id']]),
							'data_source_name', 'data_source_name'
						);

						$nt_rrd_field_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
								FROM data_template_rrd AS dtr
								INNER JOIN data_input_fields AS dif
								ON dtr.data_input_field_id=dif.id
								WHERE dtr.local_data_id = ?',
								[$item['local_data_id']]),
							'data_name', 'data_source_name'
						);
					}
				}

				$expected = '';

				if (cacti_sizeof($nt_rrd_field_names)) {
					foreach ($nt_rrd_field_names as $field) {
						if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
							continue;
						}

						$expected .= ($expected != '' ? ' ' : '') . "$field:value";

						if ($reset_template) {
							$rrd_tmpl .= ($rrd_tmpl != '' ? ':' : '') . $field;
						}

						$tv_tmpl[$field] = 'U';
						$buflen += 2;
					}
				}

				cacti_log(sprintf('WARNING: Invalid output! MULTI DS[%d] Encountered [%s] Expected[%s]', $item['local_data_id'], $value, $expected), true, 'POLLER');
			}
		}

		// process the last rrdupdate if applicable
		if ($vals_in_buffer && $rrd_path != '' && cacti_sizeof($tv_tmpl)) {
			// place the latest update at the end of the output array
			$outarray[] = $tv_tmpl;

			boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe);
		}

		// release the last lock
		if (cacti_version_compare(get_rrdtool_version(), '1.5', '<') && isset($item['local_data_id'])) {
			db_execute_prepared('SELECT RELEASE_LOCK(?)', ['boost.single_ds.' . $item['local_data_id']]);
		}

		$current_lock = false;

		boost_timer('results_cycle', BOOST_TIMER_END);
	}

	// remove the entries from the table
	boost_timer('delete', BOOST_TIMER_START);

	db_execute_prepared('DELETE FROM poller_output_boost_local_data_ids
		WHERE local_data_id <= ?
		AND process_handler = ?',
		[$last_id, $child]);

	boost_timer('delete', BOOST_TIMER_END);

	// restore original error handler
	restore_error_handler();

	return cacti_sizeof($results);
}

function boost_process_output(int $local_data_id, array $outarray, string $rrd_path, array $rrd_tmplp, mixed $rrdtool_pipe) : void {
	$outbuf = '';

	if (cacti_sizeof($outarray)) {
		foreach ($outarray as $tsdata) {
			$outbuf .= ($outbuf != '' ? ' ' : '') . implode(':', $tsdata);
		}
	}

	$rrd_tmpl = implode(':', array_keys($rrd_tmplp));

	boost_debug("Updating Local Data Id:'$local_data_id', Template:" . $rrd_tmpl . ', Output:' . $outbuf);

	boost_timer('rrdupdate', BOOST_TIMER_START);
	$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $outbuf, $rrdtool_pipe);
	boost_timer('rrdupdate', BOOST_TIMER_END);

	// check return status for delete operation
	if (trim($return_value) != 'OK' && $return_value != '') {
		cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", true, 'BOOST');
	}
}

function boost_log_statistics(int $rrd_updates) : void {
	global $start, $boost_stats_log, $verbose;

	// take time and log performance data
	$end = microtime(true);

	$cacti_stats = sprintf(
		'Time:%01.2f ' .
		'RRDUpdates:%s',
		round($end - $start, 2),
		$rrd_updates);

	// log to the database
	set_config_option('stats_boost', $cacti_stats);

	// log to the logfile
	cacti_log('BOOST STATS: ' . $cacti_stats , true, 'SYSTEM');

	$output = [];
	$order  = [
		'RRDUpdates',
		'TotalTime',
		'get_records',
		'results_cycle',
		'rrd_filename_and_template',
		'rrd_lastupdate',
		'rrdupdate',
		'delete'
	];

	$processes = boost_clamp_parallel(read_config_option('boost_parallel'));

	$stats = db_fetch_assoc('SELECT value
		FROM settings
		WHERE name LIKE "stats_detail_boost_%"');

	if (cacti_sizeof($stats)) {
		foreach ($stats as $stat) {
			$stat = json_decode($stat['value']);

			foreach ($stat as $key => $value) {
				if (isset($output[$key])) {
					$output[$key] += $value;
				} else {
					$output[$key]  = $value;
				}
			}
		}

		$outstr = '';

		foreach ($order as $key) {
			if ($key == 'TotalTime') {
				$outstr .= ($outstr != '' ? ', ' : '') . "$key:" . round($end - $start, 2);
			} elseif ($key == 'RRDUpdates') {
				$outstr .= ($outstr != '' ? ', ' : '') . "$key:" . round($output[$key], 0);
			} elseif (isset($output[$key])) {
				$outstr .= ($outstr != '' ? ', ' : '') . "$key:" . round($output[$key] / $processes, 0);
			} else {
				$outstr .= ($outstr != '' ? ', ' : '') . "$key:0";
			}
		}

		// log to the database
		set_config_option('stats_detail_boost', str_replace(',', '', $outstr));

		// log to the logfile
		if ($verbose) {
			cacti_log('BOOST DETAIL STATS: ' . $outstr, true, 'SYSTEM');
		}
	}

	// prune old process statistics if the number has changed
	$processes = read_config_option('boost_parallel');
	$stats     = db_fetch_assoc('SELECT * FROM settings WHERE name LIKE "stats_boost_%"');

	if (cacti_sizeof($stats)) {
		foreach ($stats as $stat) {
			$process = str_replace('stats_boost_', '', $stat['name']);

			if ($process > $processes) {
				db_execute_prepared('DELETE FROM settings WHERE name = ?', ['stats_boost_' . $process]);
			}
		}
	}

	// prune all detailed stats
	db_execute('DELETE FROM settings WHERE name LIKE "stats_detail_boost_%"');
}

function boost_log_child_statistics(int $rrd_updates, int $child) : void {
	global $start, $boost_stats_log, $verbose;

	// take time and log performance data
	$end = microtime(true);

	$cacti_stats = sprintf(
		'Time:%01.2f ' .
		'ProcessNumber:%s ' .
		'RRDUpdates:%s',
		round($end - $start, 2),
		$child,
		$rrd_updates);

	// log to the database
	set_config_option('stats_boost_' . $child, $cacti_stats);

	// log to the logfile
	cacti_log('BOOST STATS: ' . $cacti_stats , true, 'SYSTEM');

	if (isset($boost_stats_log)) {
		$overhead     = boost_timer_get_overhead();
		$output       = [];
		$timer_cycles = 0;

		foreach ($boost_stats_log as $area => $entry) {
			if (isset($entry[BOOST_TIMER_TOTAL])) {
				$output[$area] = round($entry[BOOST_TIMER_TOTAL] - (($overhead * $entry[BOOST_TIMER_CYCLES]) / BOOST_TIMER_OVERHEAD_MULTIPLIER), 2);
			}

			$timer_cycles += $entry[BOOST_TIMER_CYCLES];
		}

		if (cacti_sizeof($output)) {
			$output['RRDUpdates'] = $rrd_updates;
			$output['Process']    = $child;
			$output['TotalTime']  = round($end - $start, 0);

			$timer_overhead = round((($overhead * $timer_cycles) / BOOST_TIMER_OVERHEAD_MULTIPLIER), 0);

			if ($timer_overhead > 0) {
				$output['timer_overhead'] = $timer_overhead;
			}

			$output = json_encode($output);

			// log to the database
			set_config_option('stats_detail_boost_' . $child, $output);

			// log to the logfile
			if ($verbose) {
				cacti_log('BOOST DETAIL STATS: ' . $output, true, 'SYSTEM');
			}
		}
	}
}

function boost_purge_cached_png_files(bool $forcerun) : void {
	// remove stale png's from the cache.  I consider png's stale after 1 hour
	if ((read_config_option('boost_png_cache_enable') == 'on') || $forcerun) {
		$cache_directory = read_config_option('boost_png_cache_directory');
		$remove_time     = time() - 3600;

		$directory_contents = [];

		if (is_dir($cache_directory)) {
			if ($handle = opendir($cache_directory)) {
				// This is the correct way to loop over the directory.
				while (false !== ($file = readdir($handle))) {
					$directory_contents[] = $file;
				}

				closedir($handle);
			}

			// remove age old files
			if (cacti_sizeof($directory_contents)) {
				// goto the cache directory
				chdir($cache_directory);

				// check and fry as applicable
				foreach ($directory_contents as $file) {
					if (is_writable($file)) {
						$modify_time = filemtime($file);

						if ($modify_time < $remove_time) {
							// only remove jpeg's and png's
							if ((substr_count(cacti_strtolower($file), '.png')) ||
								(substr_count(cacti_strtolower($file), '.jpg'))) {
								unlink($file);
							}
						}
					}
				}
			}
		}
	}
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();

	print "Cacti Boost RRD Update Poller, Version $version " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print "\nusage: poller_boost.php [--verbose] [--force] [--debug]\n\n";
	print "Cacti's performance boosting poller.  This poller will purge the boost cache periodically.  You may\n";
	print "force the processing of the boost cache by using the --force option.\n\n";
	print "Optional:\n";
	print "    --verbose          - Show details logs at the command line\n";
	print "    --force            - Force the execution of a update process\n";
	print "    --debug            - Display verbose output during execution\n\n";
	print "Child process (internal use only):\n";
	print "    --child=N          - Run as child worker process N\n";
	print "    --archive-table=T  - Name of the archive table created by the parent\n\n";
}
