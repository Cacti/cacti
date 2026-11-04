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

$debug         = false;
$forcerun      = false;
$verbose       = false;
$child         = false;
$run_id        = '';
$archive_table = '';
$range_start   = 0;
$range_end     = 0;
$archive_lifecycle_lock = false;

/* for releasing lock on SIGNAL */
$current_lock = false;

global $child, $run_id, $next_run_time, $archive_table, $range_start, $range_end, $current_lock, $archive_lifecycle_lock;

/** @var array<string, int> $boost_worker_accounting Per-child write outcome counters. */
$boost_worker_accounting = array(
	'written_samples'   => 0,
	'failed_samples'    => 0,
	'queued_samples'    => 0,
	'discarded_samples' => 0,
);

/** @var array<string, int|bool> $boost_backend_circuit Per-child backend failure window. */
$boost_backend_circuit = array(
	'successes'            => 0,
	'failures'             => 0,
	'isolated_failures'    => 0,
	'consecutive_failures' => 0,
	'open'                 => false,
);

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--child':
				if (!preg_match('/^[1-9]\d*$/D', $value)) {
					print "ERROR: Child identifier must be a positive integer.\n\n";
					display_help();
					exit(1);
				}

				$child = (int) $value;
				break;
			case '--archive-table':
				if (boost_is_valid_archive_table($value)) {
					$archive_table = $value;
				}
				break;
			case '--range-start':
				if (preg_match('/^\d+$/D', $value)) {
					$range_start = (int) $value;
				}
				break;
			case '--range-end':
				if (preg_match('/^\d+$/D', $value)) {
					$range_end = (int) $value;
				}
				break;
			case '--run-id':
				if (preg_match('/^[a-f0-9]{32}$/D', $value)) {
					$run_id = $value;
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

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* take time and log performance data */
$start = microtime(true);
$start_time = time();
$rrd_updates = -1;
$exit_code   = 0;

/* let's give this script lot of time to run for ever */
ini_set('max_execution_time', '0');
boost_memory_limit();

if ($child == false) {
	$run_id        = bin2hex(random_bytes(16));
	$current_time  = time();

	/* find out if it's time to collect device information
	 * support both old and new formats.
	 */
	$boost_last_run_time = read_config_option('boost_last_run_time');
	if (!empty($boost_last_run_time) && !is_numeric($boost_last_run_time)) {
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

	$interval_minutes = (int) read_config_option('boost_rrd_update_interval');
	$seconds_offset   = ($interval_minutes > 0 ? $interval_minutes : 120) * 60;

	$run_now = boost_time_to_run($forcerun, $current_time, $last_run_time, $next_run_time);

	if ($run_now) {
		/**
		 * Check to see if the boost log is enabled and the file exists and
		 * is writable.  If it does not exist, create an empty file.
		 */
		$debug_log = read_config_option('path_boost_log');
		if ($debug_log != '') {
			if (!file_exists($debug_log)) {
				if (is_writable(dirname($debug_log))) {
					touch($debug_log);
				} else {
					cacti_log(sprintf('WARNING: Boost Debug Log %s is not writable.  Change the path to a writable location', $debug_log), true, 'BOOST');
				}
			}
		}

		/**
		 * Check to see if there are any poller items to process and if not
		 * exit cleanly
		 */
		$poller_items      = db_fetch_row('SELECT 1 FROM poller_output_boost LIMIT 1');
		$retained_archives = boost_get_arch_table_names();
		$queued_failures   = boost_failure_table_exists_uncached() ?
			(int) db_fetch_cell('SELECT COUNT(*) FROM poller_output_boost_failures') : 0;

		if (!cacti_sizeof($poller_items) && !cacti_sizeof($retained_archives) && $queued_failures === 0) {
			cacti_log('INFO: Boost has no live or archived items to process during this cycle.', true, 'BOOST');
			exit(0);
		}

		/* we will warn if the process is taking extra long */
		if (!register_process_start('boost', 'master', $config['poller_id'], read_config_option('boost_rrd_update_max_runtime') * 3)) {
			exit(0);
		}

		boost_debug('Time to Run Boost, Force Run is ' . ($forcerun ? 'true!':'false.'));

		/* Check if processes are running and kill them */
		if (!boost_kill_running_processes()) {
			cacti_log('ERROR: Boost could not confirm that the previous workers stopped; shared work tables were not modified.', true, 'BOOST');
			unregister_process('boost', 'master', $config['poller_id'], getmypid());
			exit(1);
		}

		if (boost_ensure_process_table(true) === false) {
			cacti_log('ERROR: Boost process table is not ready', true, 'BOOST');
			unregister_process('boost', 'master', $config['poller_id'], getmypid());
			exit(1);
		}

		if (boost_ensure_failure_queue() === false) {
			cacti_log('ERROR: Boost failure queue is not ready', true, 'BOOST');
			unregister_process('boost', 'master', $config['poller_id'], getmypid());
			exit(1);
		}

		if (db_execute('TRUNCATE TABLE poller_output_boost_processes') === false) {
			cacti_log('ERROR: Boost could not reset child completion state.', true, 'BOOST');
			unregister_process('boost', 'master', $config['poller_id'], getmypid());
			exit(1);
		}

		boost_begin_master_run();
		boost_prune_memstats();
		db_execute('DELETE FROM settings WHERE name LIKE \'stats_detail_boost_%\'');

		$archive_lifecycle_lock = boost_acquire_archive_lifecycle_lock(30);

		if ($archive_lifecycle_lock === false) {
			cacti_log('ERROR: Boost could not acquire exclusive archive lifecycle ownership; no table was rotated or deleted.', true, 'BOOST');
			set_config_option('boost_poller_status', 'failed - end time:' . date('Y-m-d H:i:s'));
			set_config_option('boost_poller_started', 0);
			unregister_process('boost', 'master', $config['poller_id'], getmypid());
			exit(1);
		}

		boost_refresh_rrdtool_version();
		$retry_pipe = rrd_init(true, true);

		if (is_resource($retry_pipe) || is_array($retry_pipe)) {
			$retry_stats = boost_retry_rrd_failures($retry_pipe);
			rrd_close($retry_pipe);
			set_config_option('boost_failure_last_retry', json_encode($retry_stats));
		} elseif ($queued_failures > 0) {
			cacti_log('WARNING: Boost could not start the RRDtool worker for queued failure recovery.', true, 'BOOST');
		}

		$rotated_table = boost_rotate_output_table();

		if ($rotated_table === false) {
			$run_complete = false;
			$exit_code    = 1;
		} else {
			$arch_tables  = boost_get_arch_table_names($rotated_table);
			$run_complete = true;
			$rrd_updates  = 0;

			if (cacti_sizeof($arch_tables)) {
				foreach($arch_tables as $table) {
					$result       = boost_drain_archive($table);
					$rrd_updates += $result['updates'];

					if (!$result['complete']) {
						$run_complete = false;
						$exit_code    = 1;
						break;
					}
				}
			}
		}

		boost_log_statistics(max(0, (int) $rrd_updates));
		set_config_option('boost_poller_started', 0);
		boost_release_archive_lifecycle_lock($archive_lifecycle_lock);
		$archive_lifecycle_lock = false;

		if ($run_complete) {
			$failure_depth = (int) read_config_option('boost_failure_queue_depth');
			$status = 'complete - end time:' . date('Y-m-d H:i:s');

			if ($failure_depth > 0) {
				$status .= ' - failed RRD retries pending:' . $failure_depth;
			}

			set_config_option('boost_poller_status', $status);
			set_config_option('boost_last_run_time', $current_time);
			$next_run_time = $current_time + $seconds_offset;

			if ($rrd_updates > 0) {
				dsstats_boost_bottom();
				rrdcheck_boost_bottom();
				api_plugin_hook('boost_poller_bottom');
			}
		} else {
			set_config_option('boost_poller_status', 'failed - end time:' . date('Y-m-d H:i:s'));
			set_config_option('boost_last_run_time', $last_run_time);
			cacti_log('WARNING: Boost stopped at the oldest incomplete archive; that table and all newer archives were retained.', true, 'BOOST');
		}

		cacti_log('INFO: Boost unregistering master process', true, 'BOOST');

		unregister_process('boost', 'master', $config['poller_id'], getmypid());

		/* log the end time of the process */
		set_config_option('boost_last_end_time', time());
	} else {
		set_config_option('boost_poller_status', 'complete');
		set_config_option('boost_poller_started', 0);
	}

	/* store the next run time so that people understand */
	if ($rrd_updates > 0 || $rrd_updates == -1) {
		if (empty($next_run_time)) {
			$next_run_time = time() + $seconds_offset;
		}

		set_config_option('boost_next_run_time', $next_run_time);
	}

	boost_purge_cached_png_files($forcerun);

	exit($exit_code);
} else {
	if (!preg_match('/^[a-f0-9]{32}$/D', $run_id)) {
		cacti_log('ERROR: Boost child refused to run without a valid parent run identifier.', true, 'BOOST');
		exit(1);
	}

	if (!boost_is_valid_archive_table($archive_table)) {
		cacti_log('ERROR: Boost child refused to run without a valid parent archive.', true, 'BOOST');
		exit(1);
	}

	if ($range_end > 0 && ($range_start <= 0 || $range_end < $range_start)) {
		cacti_log('ERROR: Boost child refused an invalid data-source range.', true, 'BOOST');
		exit(1);
	}

	cacti_log('INFO: Boost register child process ' . $child, true, 'BOOST');

	/* we will warn if the process is taking extra long */
	if (!register_process_start('boost', 'child', $child, read_config_option('boost_rrd_update_max_runtime') * 3)) {
		exit(0);
	}

	/* output all the rrd data to the rrd files */
	$rrd_updates = boost_output_rrd_data($child);

	$completion_recorded = db_execute_prepared('INSERT INTO poller_output_boost_processes
		(run_id, child_id, status) VALUES (?, ?, ?)
		ON DUPLICATE KEY UPDATE status = VALUES(status)',
		array($run_id, $child, $rrd_updates));

	if ($completion_recorded === false) {
		cacti_log('ERROR: Boost child could not record its completion status.', true, 'BOOST');
	}

	boost_log_child_statistics($rrd_updates, $child);

	unregister_process('boost', 'child', $child);

	exit($completion_recorded === false || $rrd_updates < 0 ? 1 : 0);
}

/**
 * Provides a generic means to catch exceptions to the Cacti log. Used as part of Cacti's poller
 * boost functionality.
 *
 * @param int $signo The signal that was thrown by the interface.
 *
 * @return void No value is returned.
 */
function sig_handler($signo) {
	global $child, $config, $current_lock, $archive_lifecycle_lock;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Boost Poller terminated by user', true, 'BOOST');

			if ($child) {
				unregister_process('boost', 'child', $child, getmypid());
			} else {
				/* only the master owns the global Boost lifecycle status */
				set_config_option('boost_poller_status', 'terminated - end time:' . date('Y-m-d H:i:s'));
				boost_kill_running_processes(30);
				set_config_option('boost_poller_started', 0);
				boost_release_archive_lifecycle_lock($archive_lifecycle_lock);
				$archive_lifecycle_lock = false;
				unregister_process('boost', 'master', $config['poller_id'], getmypid());
			}

			if (cacti_version_compare(get_rrdtool_version(), '1.5', '<')) {
				if ($current_lock !== false && $child) {
					db_execute("SELECT RELEASE_LOCK('boost.single_ds.$current_lock')");
				} elseif (!$child) {
					db_execute('SELECT RELEASE_ALL_LOCKS()');
				}
			}

			exit(1);
		default:
			/* ignore all other signals */
	}
}

/**
 * Handles the boost kill running processes. Used as part of Cacti's poller boost functionality.
 *
 * @param int $wait_seconds The wait seconds.
 *
 * @return void No value is returned.
 */
function boost_kill_running_processes($wait_seconds = 10) {
	$processes = db_fetch_assoc_prepared('SELECT *
		FROM processes
		WHERE tasktype = \'boost\'
		AND pid != ?',
		array(getmypid()));

	if ($processes === false) {
		cacti_log('ERROR: Boost could not inspect registered processes before takeover.', true, 'BOOST');

		return false;
	}

	if (!cacti_sizeof($processes)) {
		return true;
	}

	$waiting = array();

	foreach($processes as $p) {
		$pid = (int) $p['pid'];

		if (is_system_pid($pid)) {
			cacti_log(sprintf('ERROR: Refusing to signal reserved Boost PID %d.', $pid), true, 'BOOST');

			return false;
		}

		if (!boost_child_process_still_running($pid)) {
			unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $pid);
			continue;
		}

		cacti_log(sprintf('WARNING: Stopping Boost %s PID %d due to another boost process starting.', ucfirst($p['taskname']), $pid), true, 'BOOST');

		if (!posix_kill($pid, SIGTERM)) {
			cacti_log(sprintf('ERROR: Unable to signal Boost PID %d.', $pid), true, 'BOOST');

			return false;
		}

		$waiting[] = $p;
	}

	$deadline = microtime(true) + max(1, (int) $wait_seconds);

	do {
		foreach($waiting as $index => $p) {
			if (!boost_child_process_still_running((int) $p['pid'])) {
				unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], (int) $p['pid']);
				unset($waiting[$index]);
			}
		}

		if (!cacti_sizeof($waiting)) {
			return true;
		}

		usleep(100000);
	} while (microtime(true) < $deadline);

	foreach($waiting as $p) {
		cacti_log(sprintf('ERROR: Boost PID %d did not terminate before the takeover deadline.', $p['pid']), true, 'BOOST');
	}

	return false;
}

/**
 * Return whether a registered Boost child can still access archive state.
 *
 * A killed child can remain as a zombie when the Boost master is PID 1 in a container.
 * posix_kill(pid, 0) reports that zombie as present even though it cannot execute or unregister.
 * Linux procfs exposes the terminal Z/X state; other platforms retain Cacti's existing liveness
 * and process-identity behavior.
 *
 * @param int $pid Registered child PID.
 *
 * @return bool True when the child can still run.
 */
function boost_child_process_still_running($pid) {
	if (!cacti_process_still_running($pid)) {
		return false;
	}

	$stat_file = '/proc/' . (int) $pid . '/stat';

	if (is_readable($stat_file)) {
		$stat = @file_get_contents($stat_file);
		$command_end = is_string($stat) ? strrpos($stat, ') ') : false;

		if ($command_end !== false) {
			$state = substr($stat, $command_end + 2, 1);

			if ($state === 'Z' || $state === 'X') {
				return false;
			}
		}
	}

	return true;
}

/**
 * Handles the boost processes running. Used as part of Cacti's poller boost functionality.
 *
 * @return int|false Active child count, or false when process state cannot be read.
 */
function boost_processes_running() {
	$processes = db_fetch_assoc('SELECT tasktype, taskname, taskid, pid
		FROM processes
		WHERE tasktype = \'boost\'
		AND taskname = \'child\'');
	$running = 0;

	if ($processes === false) {
		cacti_log('ERROR: Boost could not inspect child process registrations.', true, 'BOOST');

		return false;
	}

	foreach((array) $processes as $process) {
		if (boost_child_process_still_running($process['pid'])) {
			$running++;
			continue;
		}

		cacti_log(sprintf('WARNING: Boost removed stale child registration for PID %d after an unexpected exit.',
			$process['pid']), true, 'BOOST');
		unregister_process($process['tasktype'], $process['taskname'], $process['taskid'], $process['pid']);
	}

	return $running;
}

/**
 * Handles the boost completed children. Used as part of Cacti's poller boost functionality.
 *
 * @param string $run_id The run ID.
 *
 * @return int|false Completed child count, or false on a database error.
 */
function boost_completed_children($run_id) {
	$count = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM poller_output_boost_processes
		WHERE run_id = ?', array($run_id));

	return $count === false ? false : (int) $count;
}

/**
 * Handles the boost failed children. Used as part of Cacti's poller boost functionality.
 *
 * @param string $run_id The run ID.
 *
 * @return int|false Failed child count, or false on a database error.
 */
function boost_failed_children($run_id) {
	$count = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM poller_output_boost_processes
		WHERE run_id = ?
		AND CAST(status AS SIGNED) < 0', array($run_id));

	return $count === false ? false : (int) $count;
}

/**
 * Mark a scheduled Boost master as running and report a stale prior run.
 *
 * @return void No value is returned.
 */
function boost_begin_master_run() {
	global $start_time, $max_run_duration;

	$boost_poller_status  = read_config_option('boost_poller_status');
	$previous_start_time = (int) read_config_option('boost_poller_started');

	if (!$boost_poller_status) {
		$boost_poller_status = 'not started';
	}

	if (!$previous_start_time && substr_count($boost_poller_status, 'running')) {
		$status_array = explode(':', $boost_poller_status);

		if (!empty($status_array[1])) {
			$previous_start_time = strtotime($status_array[1]);
		}
	}

	if ($previous_start_time && $previous_start_time + $max_run_duration < $start_time) {
		$message = __('WARNING: Detected Poller Boost Overrun, Possible Boost Poller Crash');

		cacti_log($message, true, 'BOOST SVR');
		admin_email(__('Cacti System Warning'), $message);
	}

	set_config_option('boost_poller_status', 'running - start time:' . date('Y-m-d H:i:s'));
	set_config_option('boost_poller_started', $start_time);
}

/**
 * Atomically rotate a populated live Boost queue into a uniquely named sealed archive.
 *
 * Collection resumes against the replacement table as soon as RENAME TABLE commits. An empty
 * live queue is not rotated, which lets a later run recover retained archives without creating
 * another empty partition.
 *
 * @return string|false Archive name, an empty string when the live table is empty, or false on
 *   rotation failure.
 */
function boost_rotate_output_table() {
	$live_row = db_fetch_row('SELECT 1 FROM poller_output_boost LIMIT 1');

	if ($live_row === false) {
		cacti_log('ERROR: Boost could not inspect the live output table before rotation.', true, 'BOOST');

		return false;
	}

	if (!cacti_sizeof($live_row)) {
		return '';
	}

	$base_sequence = time();

	for ($attempt = 0; $attempt < 100; $attempt++) {
		$sequence      = $base_sequence + $attempt;
		$archive_table = 'poller_output_boost_arch_' . $sequence;
		$interim_table = 'poller_output_boost_' . $sequence;

		if (boost_dynamic_table_exists_uncached($archive_table) || boost_dynamic_table_exists_uncached($interim_table)) {
			continue;
		}

		cacti_log('INFO: Boost rotating poller_output_boost into archive table: ' . $archive_table, true, 'BOOST');

		if (db_execute("CREATE TABLE `$interim_table` LIKE poller_output_boost") === false) {
			cacti_log('ERROR: Boost failed to create its interim output table.', true, 'BOOST');

			return false;
		}

		if (db_execute("RENAME TABLE poller_output_boost TO `$archive_table`, `$interim_table` TO poller_output_boost") === false) {
			db_execute("DROP TABLE IF EXISTS `$interim_table`");
			cacti_log('ERROR: Boost failed to rotate its output table.', true, 'BOOST');

			return false;
		}

		cacti_log('INFO: Boost completed the atomic output-table rotation.', true, 'BOOST');

		return $archive_table;
	}

	cacti_log('ERROR: Boost could not allocate a unique archive table name.', true, 'BOOST');

	return false;
}

/**
 * Stage one sealed archive into balanced, contiguous data-source ranges.
 *
 * The archive primary key supplies local_data_id order. Windowed cumulative row counts balance
 * ranges by rows instead of merely by source count, while never splitting one data source across
 * workers. Only this archive is scanned; retained newer archives are untouched until its barrier
 * completes.
 *
 * @param string $table Validated archive table owned by the current master.
 *
 * @return bool True when the distribution is ready, false on a database or invariant failure.
 */
function boost_prepare_process_table($table) {
	global $run_id;

	if (!boost_is_valid_archive_table($table) || !boost_dynamic_table_exists_uncached($table)) {
		cacti_log('ERROR: Boost refused to stage an invalid or missing archive table.', true, 'BOOST');

		return false;
	}

	$processes = max(1, min(20, (int) read_config_option('boost_parallel')));

	if (db_execute('DROP TABLE IF EXISTS poller_output_boost_local_data_ids') === false ||
		db_execute('CREATE TABLE poller_output_boost_local_data_ids (
			run_id char(32) NOT NULL,
			local_data_id int unsigned NOT NULL default 0,
			process_handler int unsigned NOT NULL default 0,
			archive_rows bigint unsigned NOT NULL default 0,
			PRIMARY KEY (run_id, local_data_id),
			INDEX process_handler(run_id, process_handler, local_data_id))
			ENGINE=InnoDB ROW_FORMAT=Dynamic') === false) {
		cacti_log('ERROR: Boost failed to create its process-distribution table.', true, 'BOOST');

		return false;
	}

	$sql = "INSERT INTO poller_output_boost_local_data_ids
		(run_id, local_data_id, process_handler, archive_rows)
		SELECT ?, local_data_id,
			LEAST(?, FLOOR((cumulative_rows - source_rows) * ? / total_rows) + 1),
			source_rows
		FROM (
			SELECT local_data_id, source_rows,
				SUM(source_rows) OVER (ORDER BY local_data_id ROWS UNBOUNDED PRECEDING) AS cumulative_rows,
				SUM(source_rows) OVER () AS total_rows
			FROM (
				SELECT local_data_id, COUNT(*) AS source_rows
				FROM `$table`
				GROUP BY local_data_id
			) AS source_counts
		) AS assignments";

	if (db_execute_prepared($sql, array($run_id, $processes, $processes), false) === false) {
		cacti_log('ERROR: Boost failed to stage archive data-source ranges.', true, 'BOOST');

		return false;
	}

	$data_ids = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM poller_output_boost_local_data_ids
		WHERE run_id = ?', array($run_id));
	$total_rows = db_fetch_cell_prepared('SELECT COALESCE(SUM(archive_rows), 0)
		FROM poller_output_boost_local_data_ids
		WHERE run_id = ?', array($run_id));

	if ($data_ids === false || $total_rows === false) {
		cacti_log('ERROR: Boost could not verify its staged archive ranges.', true, 'BOOST');

		return false;
	}

	$data_ids  = (int) $data_ids;
	$total_rows = (int) $total_rows;

	if ($data_ids === 0 || $total_rows === 0) {
		cacti_log('WARNING: Boost staged an empty archive table.', true, 'BOOST');

		return true;
	}

	$shared_path = db_fetch_row_prepared('SELECT pi.rrd_path, COUNT(DISTINCT pi.local_data_id) AS source_count
		FROM poller_item AS pi
		INNER JOIN poller_output_boost_local_data_ids AS bpt
		ON bpt.local_data_id = pi.local_data_id
		AND bpt.run_id = ?
		WHERE pi.rrd_path != \'\'
		GROUP BY pi.rrd_path
		HAVING COUNT(DISTINCT pi.local_data_id) > 1
		LIMIT 1', array($run_id));

	if ($shared_path === false) {
		cacti_log('ERROR: Boost could not verify exclusive RRD path ownership; the archive was retained.', true, 'BOOST');

		return false;
	}

	if (cacti_sizeof($shared_path)) {
		$message = sprintf('ERROR: Boost found %d local data IDs targeting the same RRD path %s; the archive was retained to prevent competing or out-of-order writers.',
			$shared_path['source_count'],
			$shared_path['rrd_path']);

		cacti_log($message, true, 'BOOST');
		admin_email(__('Cacti System Warning'), $message);

		return false;
	}

	cacti_log(sprintf('INFO: Boost staged archive %s with %d rows across %d data sources and %d configured workers.',
		$table,
		$total_rows,
		$data_ids,
		$processes), true, 'BOOST');

	return true;
}

/**
 * Handles the boost prune memstats. Used as part of Cacti's poller boost functionality.
 *
 * @return void No value is returned.
 */
function boost_prune_memstats() {
	$processes = read_config_option('boost_parallel');

	db_execute_prepared('DELETE FROM settings
		WHERE name LIKE \'boost_peak_memory%\'
		AND REPLACE(name, \'boost_peak_memory_\', \'\') > ?',
		array($processes));
}

/**
 * Handles the boost launch children. Used as part of Cacti's poller boost functionality.
 *
 * @return int The resulting integer value.
 */
function boost_launch_children() {
	global $config, $debug, $run_id, $archive_table;

	if (!boost_is_valid_archive_table($archive_table)) {
		cacti_log('ERROR: Boost refused to launch children without a valid archive owner.', true, 'BOOST');

		return 0;
	}

	$php_binary    = read_config_option('path_php_binary');
	$boost_log     = read_config_option('path_boost_log');
	$redirect_args = '';

	if ($boost_log != '') {
		if (!is_writable($boost_log)) {
			boost_debug("WARNING: Boost log '$boost_log' is not writable!");

			cacti_log("WARNING: Boost log '$boost_log' is not writable!", true, 'BOOST');
		} else {
			$redirect_args = '>> ' . cacti_escapeshellarg($boost_log);
		}
	}

	$ranges = db_fetch_assoc_prepared('SELECT process_handler,
			MIN(local_data_id) AS range_start, MAX(local_data_id) AS range_end
			FROM poller_output_boost_local_data_ids
			WHERE run_id = ?
			GROUP BY process_handler
			ORDER BY process_handler', array($run_id));

	if (!is_array($ranges) || !cacti_sizeof($ranges)) {
		cacti_log('ERROR: Boost found no non-empty worker ranges to launch.', true, 'BOOST');

		return 0;
	}

	boost_debug('About to launch ' . cacti_sizeof($ranges) . ' non-empty processes.');
	$launched = 0;

	foreach($ranges as $range) {
		$i            = (int) $range['process_handler'];
		$worker_start = (int) $range['range_start'];
		$worker_end   = (int) $range['range_end'];

		if ($i < 1 || $worker_start < 1 || $worker_end < $worker_start) {
			cacti_log('ERROR: Boost refused an invalid staged worker range.', true, 'BOOST');

			return 0;
		}

		boost_debug('Launching Boost Process Number ' . $i);

		cacti_log('NOTE: Launching Boost Process Number ' . $i, true, 'BOOST', POLLER_VERBOSITY_MEDIUM);

		$arguments = $config['base_path'] . '/poller_boost.php' .
			' --child=' . $i .
			' --run-id=' . $run_id .
			' --archive-table=' . $archive_table .
			' --range-start=' . $worker_start .
			' --range-end=' . $worker_end .
			($debug ? ' --debug':'');

		exec_background($php_binary, $arguments, $redirect_args);
		$launched++;
	}

	sleep(2);

	return $launched;
}

/**
 * Drain one parent-owned archive and enforce its worker barrier.
 *
 * Handled per-RRD failures remain nonfatal because the child accounts for them separately.
 * A missing completion, database failure, or opened backend circuit is fatal: the oldest table
 * remains in place and newer tables are not started. A completed table is dropped only after no
 * child process can still hold a metadata lock or read cursor on it.
 *
 * @param string $table Validated oldest archive table.
 *
 * @return array{complete: bool, updates: int, failed_children: int} Archive result.
 */
function boost_drain_archive($table) {
	global $archive_table, $run_id;

	$archive_table = $table;
	$run_id        = bin2hex(random_bytes(16));

	if (!boost_prepare_process_table($table)) {
		return array('complete' => false, 'updates' => 0, 'failed_children' => 0);
	}

	$data_ids = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM poller_output_boost_local_data_ids
		WHERE run_id = ?', array($run_id));

	if ($data_ids === false) {
		cacti_log('ERROR: Boost could not verify whether the staged archive was empty.', true, 'BOOST');

		return array('complete' => false, 'updates' => 0, 'failed_children' => 0);
	}

	$data_ids = (int) $data_ids;

	if ($data_ids === 0) {
		cacti_log('INFO: Boost dropping empty archive table: ' . $table, true, 'BOOST');

		return array(
			'complete'        => db_execute("DROP TABLE IF EXISTS `$table`") !== false,
			'updates'         => 0,
			'failed_children' => 0,
		);
	}

	cacti_log('INFO: Boost spawning child processes for oldest archive ' . $table . '.', true, 'BOOST');
	$expected_children = boost_launch_children();

	if ($expected_children < 1) {
		return array('complete' => false, 'updates' => 0, 'failed_children' => 0);
	}

	while (true) {
		$running_children   = boost_processes_running();
		$completed_children = boost_completed_children($run_id);

		if ($running_children === false || $completed_children === false) {
			cacti_log('ERROR: Boost could not verify its child barrier; retaining archive table ' . $table . '.', true, 'BOOST');

			return array('complete' => false, 'updates' => 0, 'failed_children' => 0);
		}

		if ($running_children === 0) {
			if ($completed_children >= $expected_children) {
				break;
			}

			cacti_log('WARNING: Boost child exited without recording completion; retaining archive table ' . $table . '.', true, 'BOOST');
			break;
		}

		boost_debug(sprintf('%s Processes Running, %s of %s Completed, Sleeping for 2 seconds.',
			$running_children,
			$completed_children,
			$expected_children));
		sleep(2);
	}

	$completed_children = boost_completed_children($run_id);
	$failed_children    = boost_failed_children($run_id);
	$updates            = db_fetch_cell_prepared('SELECT COALESCE(SUM(CAST(status AS SIGNED)), 0)
		FROM poller_output_boost_processes
		WHERE run_id = ?
		AND CAST(status AS SIGNED) >= 0', array($run_id));

	if ($completed_children === false || $failed_children === false || $updates === false) {
		cacti_log('ERROR: Boost could not verify child completion accounting; retaining archive table ' . $table . '.', true, 'BOOST');

		return array('complete' => false, 'updates' => 0, 'failed_children' => 0);
	}

	$updates  = (int) $updates;
	$complete = $completed_children >= $expected_children && $failed_children === 0;

	if (!$complete) {
		cacti_log(sprintf('WARNING: Boost retained oldest archive %s after %d of %d completions and %d fatal child failures.',
			$table,
			$completed_children,
			$expected_children,
			$failed_children), true, 'BOOST');

		return array('complete' => false, 'updates' => $updates, 'failed_children' => $failed_children);
	}

	$running_children = boost_processes_running();

	if ($running_children === false || $running_children > 0) {
		cacti_log('ERROR: Boost archive barrier could not prove that every child stopped.', true, 'BOOST');

		return array('complete' => false, 'updates' => $updates, 'failed_children' => $failed_children);
	}

	cacti_log('INFO: Boost dropping completed archive table: ' . $table, true, 'BOOST');

	if (db_execute("DROP TABLE IF EXISTS `$table`") === false) {
		cacti_log('ERROR: Boost could not drop completed archive table: ' . $table, true, 'BOOST');

		return array('complete' => false, 'updates' => $updates, 'failed_children' => $failed_children);
	}

	return array('complete' => true, 'updates' => $updates, 'failed_children' => $failed_children);
}

/**
 * Handles the boost time to run. Used as part of Cacti's poller boost functionality.
 *
 * @param bool $forcerun The forcerun.
 * @param int $current_time The current time.
 * @param int $last_run_time The last run time.
 * @param int $next_run_time The next run time.
 *
 * @return bool True on success, false otherwise.
 */
function boost_time_to_run($forcerun, $current_time, $last_run_time, $next_run_time) {
	$run_now = false;

	boost_debug("Checking if Boost is ready to run.");

	if ((read_config_option('boost_rrd_update_enable') == 'on') || $forcerun) {
		/* turn on the system level updates as that is what dictates "on/off" */
		if (!$forcerun && read_config_option('boost_rrd_update_system_enable') != 'on') {
			set_config_option('boost_rrd_update_system_enable', 'on');
		}

		$seconds_offset = (int) read_config_option('boost_rrd_update_interval') * 60;

		/* Initialize seconds offset, if not set to 2 hours */
		if (empty($seconds_offset)) {
			$seconds_offset = 120 * 60;
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
		$max_records = (int) read_config_option('boost_rrd_update_max_records');

		/* an empty/zero setting must not be treated as "flush on every row" */
		if ($max_records <= 0) {
			$max_records = 1000000;
			set_config_option('boost_rrd_update_max_records', '1000000');
		}

		$retained_archives = boost_get_arch_table_names();
		$due_failures = boost_failure_table_exists_uncached() ?
			(bool) db_fetch_cell('SELECT 1 FROM poller_output_boost_failures WHERE next_retry <= NOW() LIMIT 1') : false;

		if (cacti_sizeof($retained_archives) || $due_failures) {
			$run_now = true;
			set_config_option('boost_next_run_time', $next_run_time);
			boost_debug('Retained Boost work found; scheduling bounded automatic recovery.');
		} else {
			$current_records = boost_get_total_rows($max_records);

			boost_debug('Records Found:' . $current_records . ', Max Threshold:' . $max_records . '.');

			if ($current_records > $max_records) {
				$run_now = true;
				set_config_option('boost_next_run_time', $next_run_time);
			}
		}

		if ($forcerun) {
			$run_now = true;
			set_config_option('boost_next_run_time', $next_run_time);
		}
	} else {
		$pollers = db_fetch_cell('SELECT COUNT(*) FROM pollers WHERE disabled = \'\'');

		if ($pollers > 1) {
			boost_debug('Someone attempted to disable boost through there are multiple Data Collectors Defined!');

			set_config_option('boost_rrd_update_system_enable', 'on');
		} elseif (read_config_option('boost_rrd_update_system_enable') == 'on') {
			/* turn off the system level updates, we want to disable */
			set_config_option('boost_rrd_update_system_enable', '');
		}

		/* we are force to run until boost is finished */
		$rows         = boost_get_total_rows(1);
		$due_failures = boost_failure_table_exists_uncached() ?
			(bool) db_fetch_cell('SELECT 1 FROM poller_output_boost_failures WHERE next_retry <= NOW() LIMIT 1') : false;

		if ($rows > 0 || $due_failures) {
			$run_now = true;
		}
	}

	return $run_now;
}

/**
 * Handles the boost output RRD data. Used as part of Cacti's poller boost functionality.
 *
 * @param int $child The child.
 *
 * @return mixed The result of the operation, or false on failure.
 */
function boost_output_rrd_data($child) {
	global $start, $archive_table, $range_start, $range_end, $max_run_duration, $config, $database_default, $debug, $get_memory, $memory_used, $run_id;

	$rrd_updates       = 0;
	$processed_rows    = 0;
	$rrdtool_pipe      = rrd_init(true, true);
	$runtime_exceeded  = false;

	if (!boost_is_valid_archive_table($archive_table) || !boost_dynamic_table_exists_uncached($archive_table)) {
		cacti_log('ERROR: Boost child has no valid parent-owned archive table.', true, 'BOOST');

		return -1;
	}

	if ($range_start <= 0 || $range_end < $range_start) {
		if (is_resource($rrdtool_pipe) || is_array($rrdtool_pipe)) {
			rrd_close($rrdtool_pipe);
		}

		return 0;
	}

	if (!is_resource($rrdtool_pipe) && !is_array($rrdtool_pipe)) {
		cacti_log('ERROR: Boost could not initialize an acknowledged RRDtool worker.', true, 'BOOST');

		return -1;
	}

	/* let's set and track memory usage will we */
	if (!function_exists('memory_get_peak_usage')) {
		$get_memory   = true;
		$memory_used  = memory_get_usage();
	} else {
		$get_memory   = false;
	}

	boost_debug("Processing RRRtool Output for Boost Process $child");

	boost_debug("Process:$child, Archive:$archive_table, Range:$range_start-$range_end");

	$max_per_select = read_config_option('boost_rrd_update_max_records_per_select');

	if (empty($max_per_select) || $max_per_select < 1) {
		$max_per_select = 50000;
	}

	$curpass = 1;

	while (true) {
		boost_debug("Processing bounded page $curpass for Boost Process $child");

		$pass_rows = boost_process_local_data_ids($child, $rrdtool_pipe, $max_per_select);

		if ($pass_rows < 0) {
			rrd_close($rrdtool_pipe);

			return -1;
		}

		if ($pass_rows === 0) {
			break;
		}

		$processed_rows += $pass_rows;

		$curpass++;

		if (((time()-$start) > $max_run_duration) && (!$runtime_exceeded)) {
			cacti_log('WARNING: RRD On Demand Updater Exceeded Runtime Limits. Continuing to Process!!!', true, 'BOOST');

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

	return $processed_rows;
}

/**
 * Fetch one bounded archive page without an OR across different primary-key prefixes.
 *
 * MariaDB can filesort and repeatedly rescan a large single-source range when a full keyset
 * predicate combines `local_data_id > cursor` with the current source's time predicate. Read the
 * remainder of the current source first, then fill the page from later source IDs. The two
 * results are naturally adjacent in primary-key order and together never exceed max_rows + 1.
 *
 * @param string $table Validated archive table.
 * @param int $range_start First local data ID owned by this worker.
 * @param int $range_end Last local data ID owned by this worker.
 * @param int $cursor_id Last local data ID consumed by this worker.
 * @param string $cursor_time Last timestamp consumed for cursor_id.
 * @param string $cursor_rrd_name Last RRD field name consumed at cursor_time.
 * @param int $max_rows Maximum complete page size, excluding the look-ahead row.
 *
 * @return array<int, array<string, mixed>>|false Ordered rows, or false on validation/database
 *   failure.
 */
function boost_fetch_archive_page($table, $range_start, $range_end, $cursor_id, $cursor_time, $cursor_rrd_name, $max_rows) {
	if (!boost_is_valid_archive_table($table) || $range_start < 1 || $range_end < $range_start || $max_rows < 1) {
		return false;
	}

	$limit   = (int) $max_rows + 1;
	$results = array();
	$columns = 'SELECT STRAIGHT_JOIN archive.local_data_id, dl.data_template_id,
		UNIX_TIMESTAMP(archive.time) AS timestamp, archive.time AS sample_time,
		archive.rrd_name, archive.output
		FROM `' . $table . '` AS archive FORCE INDEX (PRIMARY)
		LEFT JOIN data_local AS dl
		ON archive.local_data_id = dl.id ';

	if ($cursor_id >= $range_start && $cursor_id <= $range_end) {
		$current = db_fetch_assoc_prepared($columns . '
			WHERE archive.local_data_id = ?
			AND (archive.time > ? OR (archive.time = ? AND archive.rrd_name > ?))
			ORDER BY archive.time ASC, archive.rrd_name ASC
			LIMIT ' . $limit, array(
				$cursor_id,
				$cursor_time,
				$cursor_time,
				$cursor_rrd_name,
			));

		if ($current === false) {
			return false;
		}

		$results = $current;
	}

	$remaining = $limit - cacti_sizeof($results);
	$next_id   = max($range_start, $cursor_id + 1);

	if ($remaining > 0 && $next_id <= $range_end) {
		if ($next_id === $range_end) {
			$later = db_fetch_assoc_prepared($columns . '
				WHERE archive.local_data_id = ?
				ORDER BY archive.time ASC, archive.rrd_name ASC
				LIMIT ' . $remaining, array($next_id));
		} else {
			$later = db_fetch_assoc_prepared($columns . '
				WHERE archive.local_data_id BETWEEN ? AND ?
				ORDER BY archive.local_data_id ASC, archive.time ASC, archive.rrd_name ASC
				LIMIT ' . $remaining, array($next_id, $range_end));
		}

		if ($later === false) {
			return false;
		}

		$results = array_merge($results, $later);
	}

	return $results;
}

/**
 * Grabs data from the 'poller_output' table and feeds the *completed* results to RRDTool for
 * processing. Used as part of Cacti's poller boost functionality.
 *
 * @param int $child The current process.
 * @param mixed $rrdtool_pipe The socket that has been opened for the RRDtool operation.
 * @param mixed $max_rows Maximum rows to materialize in one page.
 *
 * @return int The number of processed local_data_ids.
 */
function boost_process_local_data_ids($child, $rrdtool_pipe, $max_rows) {
	global $config, $archive_table, $range_start, $range_end, $boost_sock, $boost_timeout, $debug, $get_memory, $memory_used, $current_lock;
	global $run_id, $max_run_duration;

	/* cache this call as it takes time */
	static $rrdtool_version = null;
	static $rrd_field_names = array();
	static $cursor_id       = null;
	static $cursor_time     = '1970-01-02 00:00:00';
	static $cursor_rrd_name = '';

	include_once($config['library_path'] . '/rrd.php');
	$previous_error_reporting = error_reporting();

	/* suppress warnings */
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	/* gather, repair if required and cache the rrdtool version */
	if ($rrdtool_version === null) {
		$rrdtool_version = boost_refresh_rrdtool_version();
	}

	/* install the boost error handler */
	set_error_handler('boost_error_handler');

	/* load system variables needed */
	$upd_string_len		 = read_config_option('boost_rrd_update_string_length');
	$rrd_update_interval = read_config_option('boost_rrd_update_interval');
	$max_rows = max(1, (int) $max_rows);

	if (!boost_is_valid_archive_table($archive_table) || $range_start <= 0 || $range_end < $range_start) {
		cacti_log('ERROR: Boost child lost its archive ownership or data-source range.', true, 'BOOST');
		restore_error_handler();
		error_reporting($previous_error_reporting);

		return -1;
	}

	if ($cursor_id === null) {
		$cursor_id = $range_start - 1;
	}

	if (!cacti_sizeof($rrd_field_names)) {
		$rrd_field_names = array_rekey(
			db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . '
					CONCAT(data_template_id, \'_\', data_name) AS keyname, data_source_names AS data_source_name
				FROM poller_data_template_field_mappings'),
			'keyname', array('data_source_name'));
	}

	boost_timer('get_records', BOOST_TIMER_START);
	$results = boost_fetch_archive_page(
		$archive_table,
		$range_start,
		$range_end,
		$cursor_id,
		$cursor_time,
		$cursor_rrd_name,
		$max_rows
	);
	boost_timer('get_records', BOOST_TIMER_END);

	if ($results === false) {
		restore_error_handler();
		error_reporting($previous_error_reporting);

		return -1;
	}

	if (!cacti_sizeof($results)) {
		db_execute_prepared('DELETE FROM poller_output_boost_local_data_ids
			WHERE run_id = ?
			AND process_handler = ?', array($run_id, $child));
		restore_error_handler();
		error_reporting($previous_error_reporting);

		return 0;
	}

	$bounded_results = boost_limit_complete_timestamp_page($results, $max_rows);

	if ($bounded_results === false) {
		global $boost_worker_accounting;

		/* The fetched look-ahead page already contains the authoritative first pending key. */
		$first_result = reset($results);

		if (!is_array($first_result) || !cacti_sizeof($first_result)) {
			restore_error_handler();
			error_reporting($previous_error_reporting);

			return -1;
		}

		$oversized = db_fetch_row_prepared("SELECT COUNT(*) AS sample_rows, MAX(rrd_name) AS last_rrd_name
			FROM `$archive_table`
			WHERE local_data_id = ? AND time = ?", array($first_result['local_data_id'], $first_result['sample_time']));

		if (!cacti_sizeof($oversized) || (int) $oversized['sample_rows'] < 1) {
			restore_error_handler();
			error_reporting($previous_error_reporting);

			return -1;
		}

		$cursor_id       = (int) $first_result['local_data_id'];
		$cursor_time     = $first_result['sample_time'];
		$cursor_rrd_name = $oversized['last_rrd_name'];
		$discarded       = (int) $oversized['sample_rows'];
		$boost_worker_accounting['discarded_samples'] += $discarded;
		$message = sprintf('WARNING: Boost explicitly discarded an oversized %d-row timestamp group for Local Data ID %d at %s because the configured page limit is %d.',
			$discarded,
			$cursor_id,
			$cursor_time,
			$max_rows);
		cacti_log($message, true, 'BOOST');
		boost_alert_failure_queue($message);
		restore_error_handler();
		error_reporting($previous_error_reporting);

		return $discarded;
	}

	$results = $bounded_results;

	boost_reset_rrd_metadata_page_cache();

	if (!boost_prime_rrd_metadata_cache(array_column($results, 'local_data_id'))) {
		cacti_log('ERROR: Boost could not load bounded RRD metadata for the current page; the archive was retained.', true, 'BOOST');
		restore_error_handler();
		error_reporting($previous_error_reporting);

		return -1;
	}

	/* log memory */
	if ($get_memory) {
		$cur_memory = memory_get_usage();

		if ($cur_memory > $memory_used) {
			$memory_used = $cur_memory;
		}
	}

	if (cacti_sizeof($results)) {
		$updates_ok = true;
		/* create an array keyed off of each .rrd file */
		$local_data_id  = -1;
		$time           = -1;
		$buflen         = 0;
		$outarray       = array();
		$last_update    = -1;
		$reset_template = true;

		$unused_data_source_names = array();

		/* we are going to blow away all record if ok */
		$vals_in_buffer = 0;

		boost_timer('results_cycle', BOOST_TIMER_START);

		/* go through each poller_output_boost entries and process */
		foreach ($results as $item) {
			$skip_item = false;

			if ($local_data_id == $item['local_data_id'] && cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$item['rrd_name']])) {
				continue;
			}

			$item['timestamp'] = trim($item['timestamp']);

			/**
			 * if the local_data_id changes, we need to flush the buffer
			 * and discover the template for the next RRDfile.
			 */
			if ($local_data_id != $item['local_data_id']) {
				/* Flush the previous RRD while its legacy named lock is still held. */
				if ($vals_in_buffer) {
					$outarray[] = $tv_tmpl;
					$flush_ok   = boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe);

					$buflen         = 0;
					$vals_in_buffer = 0;
					$outarray       = array();

					if (!$flush_ok && boost_backend_circuit_open()) {
						$updates_ok = false;
						break;
					}
				}

				/* release the previous lock */
				if (cacti_version_compare($rrdtool_version, '1.5', '<') && $current_lock !== false) {
					db_execute("SELECT RELEASE_LOCK('boost.single_ds.$current_lock')");
				}

				$current_lock = false;

				/* acquire lock in order to prevent race conditions, only a problem pre-rrdtool 1.5 */
				if (cacti_version_compare($rrdtool_version, '1.5', '<')) {
					$lock_deadline = microtime(true) + max(1, min(30, (int) $max_run_duration));

					while (!db_fetch_cell("SELECT GET_LOCK('boost.single_ds." . $item['local_data_id'] . "', 1)")) {
						if (microtime(true) >= $lock_deadline) {
							cacti_log('ERROR: Boost timed out acquiring the RRD lock for Local Data ID ' . $item['local_data_id'] . '; queued rows were retained.', true, 'BOOST');
							restore_error_handler();
							error_reporting($previous_error_reporting);

							return -1;
						}

						usleep(50000);
					}
				}

				$current_lock = $item['local_data_id'];

				$unused_data_source_names = boost_get_unused_data_source_names($item['local_data_id']);
				if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$item['rrd_name']])) {
					$skip_item = true;
				}

				$reset_template = true;

				if (isset($nt_rrd_field_names)) {
					unset($nt_rrd_field_names);
				}

				/* reset the rrd file path and templates, assume non multi output */
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
				$rrd_tmplp   = array_fill_keys(array_values(explode(':', $rrd_tmpl)), 'U');
				$rrd_tmplpts = array('timestamp' => '') + $rrd_tmplp;

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

				if ($skip_item) {
					continue;
				}
			} else {
				$reset_template = false;
			}

			/* don't generate error messages if the RRD has already been updated */
			if ($item['timestamp'] <= $last_update && cacti_version_compare(get_rrdtool_version(), '1.5', '<')) {
				cacti_log("WARNING: Stale Poller Data Found! Item Time:'" . $item['timestamp'] . "', RRD Time:'" . $last_update . "' Ignoring Value!", true, 'BOOST', POLLER_VERBOSITY_HIGH);

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
					$flush_ok = boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe);

					$buflen         = 0;
					$vals_in_buffer = 0;
					$outarray       = array();

					if (!$flush_ok && boost_backend_circuit_open()) {
						$updates_ok = false;
						break;
					}
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
			} elseif ((is_numeric($value)) || ($value == 'U' && $item['rrd_name'] !== '')) {
				$tv_tmpl[$item['rrd_name']] = $value;
				$buflen += strlen(':' . $value);
				$vals_in_buffer++;
			} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($value))) {
				$tval = hexdec($value);
				$tv_tmpl[$item['rrd_name']] = $tval;
				$buflen += strlen(':' . $tval);
				$vals_in_buffer++;
			} elseif (strpos($value, ':') !== false) {
				/* break out multiple value output to an array */
				$values = preg_split('/\s+/', $value);

				if (!$reset_template) {
					$rrd_tmpl = '';
				} else {
					if ($item['data_template_id'] > 0) {
						$unused_data_source_names = boost_get_unused_data_source_names($item['local_data_id']);
					} else {
						$unused_data_source_names = array();
					}
				}

				foreach($values as $value) {
					$matches = explode(':', $value);

					if (cacti_sizeof($matches) == 2) {
						if (isset($rrd_field_names[$item['data_template_id'] . '_' . $matches[0]])) {
							$field = $rrd_field_names[$item['data_template_id'] . '_' . $matches[0]]['data_source_name'];

							if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
								continue;
							}

							if ($reset_template) {
								cacti_log("Parsed MULTI output field '" . $matches[0] . ':' . $field . "' [map " . $matches[0] . '->' . $field . ']', true, 'BOOST', ($debug ? POLLER_VERBOSITY_NONE:POLLER_VERBOSITY_HIGH));

								if (trim(read_config_option('path_boost_log')) != '') {
									print "DEBUG: Parsed MULTI output field in path 1 '" . $matches[0] . "' [map " . $field . '->' . $field . ']' . PHP_EOL;
								}

								$rrd_tmpl .= ($rrd_tmpl != '' ? ':':'') . $field;
							}

							if (is_numeric($matches[1]) || ($matches[1] == 'U')) {
								$tv_tmpl[$field] = $matches[1];
								$buflen += strlen(':' . $matches[1]);
							} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($matches[1]))) {
								$tval = hexdec($matches[1]);
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
								$nt_rrd_field_names = boost_get_input_field_names($item['local_data_id'], true);
							} else {
								$nt_rrd_field_names = boost_get_input_field_names($item['local_data_id'], false);
							}

							if (cacti_sizeof($nt_rrd_field_names)) {
								if (isset($nt_rrd_field_names[$matches[0]])) {
									$field = $nt_rrd_field_names[$matches[0]];

									if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
										continue;
									}

									if ($reset_template) {
										cacti_log("Parsed MULTI output field in path 2 '" . $matches[0] . ':' . $matches[1] . "' [map " . $matches[0] . '->' . $field . ']', true, 'BOOST', ($debug ? POLLER_VERBOSITY_NONE:POLLER_VERBOSITY_HIGH));

										if (trim(read_config_option('path_boost_log')) != '') {
											print "DEBUG: Parsed MULTI output field '" . $matches[0] . "' [map " . $matches[1] . '->' . $field . ']' . PHP_EOL;
										}

										$rrd_tmpl .= ($rrd_tmpl != '' ? ':':'') . $field;
									}

									if (is_numeric($matches[1]) || ($matches[1] == 'U')) {
										$tv_tmpl[$field] = $matches[1];
										$buflen += strlen(':' . $matches[1]);
									} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($matches[1]))) {
										$tval = hexdec($matches[1]);
										$tv_tmpl[$field] = $tval;
										$buflen += strlen(':' . $tval);
									} else {
										$tv_tmpl[$field] = 'U';
										$buflen += 2;
									}

									if (trim(read_config_option('path_boost_log')) != '') {
										print "DEBUG: Parsed MULTI output field '" . $matches[0] . "' [map " . $matches[1] . '->' . $nt_rrd_field_names[$matches[1]] . ']' . PHP_EOL;
									}
								}

								$vals_in_buffer++;
							}
						}
					}
				}
			} else {
				if ($reset_template) {
					if ($item['data_template_id'] > 0) {
						$unused_data_source_names = boost_get_unused_data_source_names($item['local_data_id']);
						$nt_rrd_field_names       = boost_get_input_field_names($item['local_data_id'], true);
					} else {
						$unused_data_source_names = array();
						$nt_rrd_field_names       = boost_get_input_field_names($item['local_data_id'], false);
					}
				}

				$expected = '';

				if (cacti_sizeof($nt_rrd_field_names)) {
					foreach($nt_rrd_field_names as $field) {
						if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
							continue;
						}

						$expected .= ($expected != '' ? ' ':'') . "$field:value";

						if ($reset_template) {
							$rrd_tmpl .= ($rrd_tmpl != '' ? ':':'') . $field;
						}

						$tv_tmpl[$field] = 'U';
						$buflen += 2;
					}
				}

				cacti_log(sprintf('WARNING: Invalid output! MULTI DS[%d] Encountered [%s] Expected[%s]', $item['local_data_id'], $value, $expected), true, 'POLLER');
			}
		}

		/* process the last rrdupdate if applicable */
		if ($vals_in_buffer) {
			/* place the latest update at the end of the output array */
			$outarray[] = $tv_tmpl;

			if (!boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe) &&
				boost_backend_circuit_open()) {
				$updates_ok = false;
			}
		}

		/* release the last lock */
		if (cacti_version_compare(get_rrdtool_version(), '1.5', '<') && $current_lock !== false) {
			db_execute("SELECT RELEASE_LOCK('boost.single_ds.$current_lock')");
		}

		$current_lock = false;

		boost_timer('results_cycle', BOOST_TIMER_END);
	}

	/* Advance one in-memory full-primary-key cursor after acknowledged page handling. */
	boost_timer('delete', BOOST_TIMER_START);

	if (!isset($updates_ok)) {
		$updates_ok = $results !== false;
	}

	if ($updates_ok && $results !== false) {
		$last_result    = end($results);
		$cursor_id      = (int) $last_result['local_data_id'];
		$cursor_time    = $last_result['sample_time'];
		$cursor_rrd_name = $last_result['rrd_name'];
	} else {
		cacti_log("WARNING: Boost stopped archive $archive_table at the current page because one or more RRD updates failed systemically.", true, 'BOOST');
	}

	boost_timer('delete', BOOST_TIMER_END);

	/* restore original error handler */
	restore_error_handler();
	error_reporting($previous_error_reporting);

	return $updates_ok && $results !== false ? cacti_sizeof($results) : -1;
}

/**
 * Count timestamp updates and return the newest timestamp in an RRDtool values string.
 *
 * @param string $outbuf Space-separated RRDtool update values.
 *
 * @return array{count: int, latest: int} Command sample accounting.
 */
function boost_measure_update_values($outbuf) {
	$matches = array();
	$count   = preg_match_all('/(?:^|\s)(\d+):/', $outbuf, $matches);
	$latest  = 0;

	if ($count && isset($matches[1])) {
		$latest = max(array_map('intval', $matches[1]));
	}

	return array('count' => max(1, (int) $count), 'latest' => $latest);
}

/**
 * Update the per-child RRDtool backend circuit breaker.
 *
 * Eight failures before any success, or 32 consecutive failures after progress, indicate a
 * backend-wide outage rather than isolated bad files. Opening the circuit makes the child fatal
 * so the current archive remains available for a later automatic run.
 *
 * @param bool $success Whether RRDtool acknowledged the command.
 * @param string $error RRDtool diagnostic when the command failed.
 *
 * @return bool True when the backend circuit is open.
 */
function boost_record_backend_result($success, $error = '') {
	global $boost_backend_circuit;

	if ($success) {
		$boost_backend_circuit['successes']++;
		$boost_backend_circuit['consecutive_failures'] = 0;
	} elseif (boost_is_isolated_rrd_failure($error)) {
		$boost_backend_circuit['isolated_failures']++;
		$boost_backend_circuit['consecutive_failures'] = 0;
	} else {
		$boost_backend_circuit['failures']++;
		$boost_backend_circuit['consecutive_failures']++;
	}

	if (boost_backend_failure_window_open(
		$boost_backend_circuit['successes'],
		$boost_backend_circuit['failures'],
		$boost_backend_circuit['consecutive_failures']
	)) {
		$boost_backend_circuit['open'] = true;
	}

	return $boost_backend_circuit['open'];
}

/**
 * Return whether the per-child RRDtool backend circuit is open.
 *
 * @return bool True when archive processing must stop without dropping the table.
 */
function boost_backend_circuit_open() {
	global $boost_backend_circuit;

	return !empty($boost_backend_circuit['open']);
}

/**
 * Write one prepared RRD command, isolating and durably recording a per-RRD failure.
 *
 * @param int $local_data_id The local data ID.
 * @param array $outarray The outarray.
 * @param string $rrd_path The RRD path.
 * @param array $rrd_tmplp The RRD tmplp.
 * @param mixed $rrdtool_pipe The rrdtool pipe.
 *
 * @return bool True on success, false otherwise.
 */
function boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe) {
	global $boost_worker_accounting, $boost_backend_circuit;

	$outbuf = '';
	if (cacti_sizeof($outarray)) {
		foreach($outarray as $tsdata) {
			$outbuf .= ($outbuf != '' ? ' ':'') . implode(':', $tsdata);
		}
	}

	$rrd_tmpl = implode(':', array_keys($rrd_tmplp));
	$measure  = boost_measure_update_values($outbuf);

	if ($rrd_path == '') {
		$boost_worker_accounting['discarded_samples'] += $measure['count'];
		$superseded = boost_clear_superseded_rrd_failure($local_data_id, $measure['latest']);

		if ($superseded > 0) {
			$boost_worker_accounting['discarded_samples'] += $superseded;
			cacti_log(sprintf('WARNING: Boost explicitly discarded %d queued failed sample update(s) after Local Data ID %d was removed.',
				$superseded,
				$local_data_id), true, 'BOOST');
		}

		return true;
	}

	if (trim(read_config_option('path_boost_log')) != '') {
		print "DEBUG: Updating Local Data Id:'$local_data_id', Template:" . $rrd_tmpl . ', Output:' . $outbuf . PHP_EOL;
	}


	boost_timer('rrdupdate', BOOST_TIMER_START);
	$lock_name = boost_acquire_rrd_path_lock($rrd_path);

	if ($lock_name === false) {
		$return_value = 'ERROR: Timed out acquiring the RRD path lock';
	} else {
		$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $outbuf, $rrdtool_pipe);
		boost_release_rrd_path_lock($lock_name);
	}
	boost_timer('rrdupdate', BOOST_TIMER_END);

	/* check return status for delete operation */
	if (trim((string) $return_value) !== 'OK') {
		cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", true, 'BOOST');
		$disposition = boost_queue_rrd_failure(
			$local_data_id,
			$rrd_path,
			$rrd_tmpl,
			$outbuf,
			$measure['latest'],
			$measure['count'],
			$return_value
		);
		$boost_worker_accounting['failed_samples'] += $measure['count'];
		$boost_worker_accounting['queued_samples'] += $disposition['queued_samples'];
		$boost_worker_accounting['discarded_samples'] += $disposition['discarded_samples'];

		if (!$disposition['handled']) {
			$boost_backend_circuit['open'] = true;
			cacti_log('ERROR: Boost could not durably isolate an RRD failure; the current archive will be retained.', true, 'BOOST');
		}

		if (boost_record_backend_result(false, $return_value)) {
			cacti_log('ERROR: Boost opened the RRDtool backend circuit after repeated failures; the current archive will be retained.', true, 'BOOST');
		}

		return false;
	}

	$boost_worker_accounting['written_samples'] += $measure['count'];
	$superseded = boost_clear_superseded_rrd_failure($local_data_id, $measure['latest']);

	if ($superseded > 0) {
		$boost_worker_accounting['discarded_samples'] += $superseded;
		cacti_log(sprintf('WARNING: Boost explicitly discarded %d queued failed sample update(s) for Local Data ID %d after a newer normal write succeeded.',
			$superseded,
			$local_data_id), true, 'BOOST');
	}

	boost_record_backend_result(true);

	return true;
}

/**
 * Handles the boost log statistics. Used as part of Cacti's poller boost functionality.
 *
 * @param int $rrd_updates The RRD updates.
 *
 * @return void No value is returned.
 */
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
		'WrittenSamples',
		'FailedSamples',
		'QueuedSamples',
		'DiscardedSamples',
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
		WHERE name LIKE \'stats_detail_boost_%\'');

	if (cacti_sizeof($stats)) {
		foreach($stats as $stat) {
			$stat = json_decode($stat['value'], true);

			if (!is_array($stat)) {
				cacti_log('WARNING: Ignoring malformed Boost child statistics.', true, 'BOOST');

				continue;
			}

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
			} elseif (in_array($key, array('RRDUpdates', 'WrittenSamples', 'FailedSamples', 'QueuedSamples', 'DiscardedSamples'), true)) {
				$outstr .= ($outstr != '' ? ', ':'') . "$key:" . round(isset($output[$key]) ? $output[$key] : 0, 0);
			} elseif (isset($output[$key])) {
				$outstr .= ($outstr != '' ? ', ':'') . "$key:" . round($output[$key]/$processes, 0);
			} else {
				$outstr .= ($outstr != '' ? ', ':'') . "$key:0";
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
	$stats     = db_fetch_assoc('SELECT * FROM settings WHERE name LIKE \'stats_boost_%\'');
	if (cacti_sizeof($stats)) {
		foreach($stats as $stat) {
			$process = str_replace('stats_boost_', '', $stat['name']);
			if ($process > $processes) {
				db_execute_prepared('DELETE FROM settings WHERE name = ?', array('stats_boost_' . $process));
			}
		}
	}

	/* prune all detailed stats */
	db_execute('DELETE FROM settings WHERE name LIKE \'stats_detail_boost_%\'');
}

/**
 * Handles the boost log child statistics. Used as part of Cacti's poller boost functionality.
 *
 * @param int $rrd_updates The RRD updates.
 * @param int $child The child.
 *
 * @return void No value is returned.
 */
function boost_log_child_statistics($rrd_updates, $child) {
	global $start, $boost_stats_log, $verbose, $boost_worker_accounting;

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
			$output['WrittenSamples'] = $boost_worker_accounting['written_samples'];
			$output['FailedSamples'] = $boost_worker_accounting['failed_samples'];
			$output['QueuedSamples'] = $boost_worker_accounting['queued_samples'];
			$output['DiscardedSamples'] = $boost_worker_accounting['discarded_samples'];
			$output['Process']    = $child;
			$output['TotalTime']  = round($end - $start, 0);

			$timer_overhead = round((($overhead * $timer_cycles)/BOOST_TIMER_OVERHEAD_MULTIPLIER), 0);

			if ($timer_overhead > 0) {
				$output['timer_overhead'] = $timer_overhead;
			}

			$previous = json_decode((string) read_config_option('stats_detail_boost_' . $child), true);

			if (is_array($previous)) {
				foreach($previous as $key => $value) {
					if ($key !== 'Process' && is_numeric($value)) {
						$output[$key] = (isset($output[$key]) ? $output[$key] : 0) + $value;
					}
				}
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

/**
 * Handles the boost purge cached png files. Used as part of Cacti's poller boost functionality.
 *
 * @param bool $forcerun The forcerun.
 *
 * @return void No value is returned.
 */
function boost_purge_cached_png_files($forcerun) {
	global $config;

	/* remove stale png's from the cache.  I consider png's stale after 1 hour */
	if ((read_config_option('boost_png_cache_enable') == 'on') || $forcerun) {
		$cache_directory = read_config_option('boost_png_cache_directory');
		$remove_time = time() - 3600;

		$directory_contents = array();

		if (is_dir($cache_directory)) {
			$real_cache_directory = realpath($cache_directory);
			$real_base_path       = realpath($config['base_path']);
			$normalized_cache     = ($real_cache_directory === false ? false : str_replace('\\', '/', $real_cache_directory));
			$normalized_base      = ($real_base_path === false ? false : rtrim(str_replace('\\', '/', $real_base_path), '/'));

			if ($normalized_cache !== false) {
				$normalized_cache = rtrim($normalized_cache, '/');
			}

			if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
				if ($normalized_cache !== false) {
					$normalized_cache = strtolower($normalized_cache);
				}

				if ($normalized_base !== false) {
					$normalized_base = strtolower($normalized_base);
				}
			}

			// Reject if cache dir IS the base path or is outside it (must be a strict subdirectory)
			if ($normalized_cache === false || $normalized_base === false
				|| $normalized_cache === $normalized_base
				|| strpos($normalized_cache, $normalized_base . '/') !== 0) {
				cacti_log("ERROR: Boost PNG Cache Directory '$cache_directory' is outside of Cacti base path. Purge aborted.", true, 'BOOST');
				return;
			}

			if ($handle = opendir($cache_directory)) {				/* This is the correct way to loop over the directory. */
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
/**
 * Displays version information. Used as part of Cacti's poller boost functionality.
 *
 * @return void No value is returned.
 */
function display_version() {
	$version = get_cacti_version();
	print "Cacti Boost RRD Update Poller, Version $version " . COPYRIGHT_YEARS . "\n";
}

/**
 * Displays the usage of the function. Used as part of Cacti's poller boost functionality.
 *
 * @return void No value is returned.
 */
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
