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


function get_rrdfiles($thread_id = 1, $max_threads = 1) {
	global $config;

	static $newrows = array();

	if (cacti_sizeof($newrows)) {
		return $newrows[$thread_id - 1];
	}

	$rows = db_fetch_assoc_prepared('SELECT dtd.data_source_profile_id, dtd.local_data_id,
		REPLACE(dtd.data_source_path, "<path_rra>", ?) AS data_source_path,
		GROUP_CONCAT(DISTINCT dtr.data_source_name) AS data_source_names,
		GROUP_CONCAT(DISTINCT dtd.rrd_step) AS rrd_step,
		GROUP_CONCAT(DISTINCT dtr.rrd_heartbeat) AS rrd_heartbeat,
		dsp.heartbeat AS profile_heartbeat, dsp.step AS profile_step
		FROM data_template_data AS dtd
		INNER JOIN data_template_rrd AS dtr
		ON dtr.local_data_id = dtd.local_data_id
		INNER JOIN graph_templates_item AS gti
		ON dtr.id = gti.task_item_id
		INNER JOIN data_source_profiles AS dsp
		ON dtd.data_source_profile_id = dsp.id
		LEFT JOIN graph_local AS gl
		ON gl.id = gti.local_graph_id
		LEFT JOIN host as h
		ON h.id = gl.host_id
		WHERE dtd.local_data_id != 0 
		AND h.disabled != "on"
		GROUP BY dtd.local_data_id',
		array($config['rra_path']));

	if ($max_threads == 1) {
		$newrows[0] = $rows;

		return $rows;
	} else {
		$newrows = array_chunk($rows, ceil(cacti_sizeof($rows) / $max_threads));

		if (isset($newrows[$thread_id - 1])) {
			return $newrows[$thread_id - 1];
		} else {
			return array();
		}
	}
}

/**
 * rrdcheck_debug - this simple routine prints a standard message to the console
 *   when running in debug mode.
 *
 * @param $message - (string) The message to display
 *
 * @return - NULL
 */
function rrdcheck_debug($message) {
	global $debug;

	if ($debug) {
		print 'rrdcheck: ' . $message . PHP_EOL;
	}
}

/**
 * do_rrdcheck - this routine is a generic routine that
 *   check all RRDfiles (missing files, ...) and stored information (NaN)
 *
 * @param $thread_id - (int) the rrdcheck parallel thread id
 *
 * @return - NULL
 */
function do_rrdcheck($thread_id = 1) {
	global $config, $type;
	global $total_user, $total_system, $total_real, $total_dsses;
	global $user_time, $system_time, $real_time, $rrd_files;

	$user_time   = 0;
	$system_time = 0;
	$real_time   = 0;
	$dsses       = 0;

	$poller_interval = read_config_option('poller_interval');

	rrdcheck_debug(sprintf('Processing %s for Thread', $thread_id));

	$max_threads = read_config_option('rrdcheck_parallel');
	if (empty($max_threads)) {
		$max_threads = 1;
		set_config_option('rrdcheck_parallel', '1');
	}

	$profiles = array_rekey(
		db_fetch_assoc('SELECT step, data_source_profile_id AS id, MIN(steps) AS steps, `rows`
			FROM data_source_profiles AS dsp
			INNER JOIN data_source_profiles_rra AS dspr
			ON dsp.id = dspr.data_source_profile_id
			GROUP BY data_source_profile_id'),
		'id', array('step', 'steps', 'rows')
	);

	$rrdfiles   = get_rrdfiles($thread_id, $max_threads);
	$stats      = array();
	$rrd_files += cacti_sizeof($rrdfiles);

	$use_proxy  = (read_config_option('storage_location') ? true : false);

	if ($use_proxy) {
		$rrdtool_pipe = rrd_init(false);
	} else {
		$process_pipes = rrdcheck_rrdtool_init();
		$process = $process_pipes[0];
		$pipes   = $process_pipes[1];
	}

	if (cacti_sizeof($rrdfiles)) {
		$now  = time();

		foreach ($rrdfiles as $rrdval) {
			$local_data_id = $rrdval['local_data_id'];
			$data_sources  = array();

			// Rekey the Data Sources key
			$sources = explode(',', $rrdval['data_source_names']);
			foreach($sources as $s) {
				$data_sources[$s] = $s;
			}

			// The first RRA may have less than 24 hours of samples
			$duration = $profiles[$rrdval['data_source_profile_id']]['step'] * ($profiles[$rrdval['data_source_profile_id']]['rows']-1);
			$step     = $profiles[$rrdval['data_source_profile_id']]['step'];

			$end   = $now;
			$start = $end - $duration;

			$file = $rrdval['data_source_path'];

			if ($use_proxy) {
				$file_exists = rrdtool_execute("file_exists $file", true, RRDTOOL_OUTPUT_BOOLEAN, false, 'RRDCHECK');
			} else {
				clearstatcache();
				$file_exists = file_exists($file);
			}

			// don't attempt to get information if the file does not exist
			if ($file_exists) {
				if (!is_resource_writable($file)) {
					db_execute_prepared ('INSERT INTO rrdcheck
						(local_data_id, test_date, message)
						VALUES	(?,NOW(),?)',
						array(
							$local_data_id,
							"RRDfile is not writable - $file"
						)
					);
				}

				if (time() > (filemtime($file) + 3600)) {
					db_execute_prepared ('INSERT INTO rrdcheck
						(local_data_id, test_date, message)
						VALUES (?,NOW(),?)',
						array(
							$local_data_id,
							"RRDfile modify time older than hour - $file"
						)
					);
				}

				if ($rrdval['profile_step'] != $rrdval['rrd_step']) {
					// Fix the RRD Data Template Data Step
					db_execute_prepared('UPDATE data_template_data
						SET rrd_step = ?
						WHERE local_data_id = ?',
						array(
							$rrdval['profile_step'],
							$local_data_id
						)
					);
				}

				if ($rrdval['rrd_heartbeat'] != $rrdval['profile_heartbeat']) {
					// Fix the RRD Data Template Data Step
					db_execute_prepared('UPDATE data_template_rrd
						SET rrd_heartbeat = ?
						WHERE local_data_id = ?',
						array($rrdval['profile_heartbeat'], $rrdval['local_data_id']));
				}

				if ($use_proxy) {
					$output = rrdtool_execute("info $file", false, RRDTOOL_OUTPUT_STDOUT, false, 'RRDCHECK');
				} else {
					$output = rrdcheck_rrdtool_execute("info $file", $pipes);
				}

				$matches     = array();
				$rrd_info    = array();
				$output      = explode("\n", $output);
				$last_update = false;
				$rrd_step    = false;

				// Process the RRDfile information into an array
				foreach ($output as $line) {
					$line = trim($line);

					if ($line == '') {
						continue;
					}

					if (!$last_update) {
						if (preg_match('/^last_update = (\S+)$/', $line, $matches)) {
							$last_update = $matches[1];
						}
					}

					if (!$rrd_step) {
						if (preg_match('/^step = (\S+)$/', $line, $matches)) {
							$rrd_step = $matches[1];
						}
					}

					if (preg_match('/^ds\[(\S+)\]\.(\S+) = (\S+)$/', $line, $matches)) {
						$rrd_info[$matches[1]][$matches[2]] = trim($matches[3], '"');
					}
				}

				if ($rrd_step != $rrdval['profile_step']) {
					db_execute_prepared ('INSERT INTO rrdcheck
						(local_data_id, test_date, message)
						VALUES (?, NOW(), ?)',
						array(
							$local_data_id,
							"The RRDfile step of {$step} does not match the Data Source Profile step {$rrdval['profile_step']}.  File '$file'."
						)
					);
				}

				if ($last_update  < (time() - 3600)) {
					db_execute_prepared ('INSERT INTO rrdcheck
						(local_data_id, test_date, message)
						VALUES (?, NOW(), ?)',
						array(
							$local_data_id,
							"Last update value in RRDfile is older than 1 hour.  File '$file'."
						)
					);
				}

				// Not really an issue that we should be calling out till we can fix
				if (cacti_sizeof($data_sources) > cacti_sizeof($rrd_info)) {
					db_execute_prepared ('INSERT INTO rrdcheck
						(local_data_id, test_date, message)
						VALUES (?, NOW(), ?)',
						array(
							$local_data_id,
							"There are more Data Sources in the database than in the RRDfile, please investigate.  File '$file'."
						)
					);
				}

				if (cacti_sizeof($data_sources) < cacti_sizeof($rrd_info)) {
					db_execute_prepared ('INSERT INTO rrdcheck
						(local_data_id, test_date, message)
						VALUES (?, NOW(), ?)',
						array(
							$local_data_id,
							"There are less Data Sources in the database than in the RRDfile, please investigate.  File '$file'."
						)
					);
				}

				$output  = '';
				$matches = array();

				foreach ($rrd_info as $info_key => $info_array) {
					if (!isset($data_sources[$info_key])) {
						db_execute_prepared ('INSERT INTO rrdcheck
							(local_data_id, test_date, message)
							VALUES (?, NOW(), ?)',
							array(
								$local_data_id,
								"The Data Source '$info_key' exists in RRDfile, but not in database.  File '$file'."
							)
						);

						continue;
					}

					if ($info_array['minimal_heartbeat'] <= $poller_interval) {
						db_execute_prepared ('INSERT INTO rrdcheck
							(local_data_id, test_date, message)
							VALUES	(?, NOW(), ?)',
							array(
								$local_data_id,
								"The RRDfile Minimal Heart for Data Source '{$info_key}' is lower than polling interval.  This will causes gaps in Graphs.  File '$file.'"
							)
						);
					}

					if ($info_array['minimal_heartbeat'] < $rrdval['profile_heartbeat']) {
						db_execute_prepared ('INSERT INTO rrdcheck
							(local_data_id, test_date, message)
							VALUES (?, NOW(), ?)',
							array(
								$local_data_id,
								"The RRDfile minimal heartbeat for Data Source '{$info_key}' should be '{$rrdval['profile_heartbeat']}' and is currently '{$info_array['minimal_heartbeat']}'.  File '$file'."
							)
						);
					}
				}

				// test if ds in db == ds in rra
				foreach ($data_sources as $dsname) {
					if (!array_key_exists($dsname, $rrd_info)) {
						db_execute_prepared ('INSERT INTO rrdcheck
							(local_data_id, test_date, message)
							VALUES (?, NOW(), ?)',
							array(
								$local_data_id,
								"Data Source name '$dsname' exists in the Database, but not in RRDfile.  File '$file'."
							)
						);
					}

					// Should never happend
					if (empty($dsname)) {
						db_execute_prepared ('INSERT INTO rrdcheck
							(local_data_id, test_date, message)
							VALUES (?, NOW(), ?)',
							array(
								$local_data_id,
								"Database Data Source name is empty for local data id '$local_data_id'.   File '$file'."
							)
						);
					}
				}

				// test stale data

				$pstart = $start - $step;
				$pend = $end - $step;
				$one_hour_limit = ($duration - 3600) / $step;

				if ($use_proxy) {
					$info_array = rrdtool_execute("fetch $file LAST -s $pstart -e $pend ", false, RRDTOOL_OUTPUT_STDOUT, false, 'RRDCHECK');
				} else {
					$info_array = rrdcheck_rrdtool_execute("fetch $file LAST -s $pstart -e $pend", $pipes);
				}

				/* don't do anything if RRDfile did not return data */
				$info_array = explode("\n", $info_array);

				if (cacti_sizeof($info_array)) {
					$first    = true;
					$lines_24 = 0;
					$lines_1  = 0;
					$nan_24   = array();
					$nan_1    = array();

					foreach($info_array as $line) {
						$line = trim($line);

						// remove line - OK u:0.03 s:0.12 r:0.33
						if (substr($line, 0, 2) == 'OK') {
							continue;
						} elseif ($line == '') {
							continue;
						}

						if ($first) {
							/* get the data source names */
							$data_source_names = preg_split('/\s+/', $line);

							foreach ($data_source_names as $index => $name)  {
								$nan_24[$index] = 0;
								$nan_1[$index]  = 0;
							}

							$dsses += cacti_sizeof($data_source_names);
							$first  = false;
						} else {
							$parts = explode(':', $line);
							$data  = explode(' ', trim($parts[1]));

							foreach($data as $index=>$number) {
								if ($index == 0) {
									// only onetime for each row
									$lines_24++;

									if ($lines_24 > $one_hour_limit) { // last hour
										$lines_1++;
									}
								}

								if (strtolower($number) == 'nan' || strtolower($number) == '-nan') {
									$nan_24[$index]++;

									if ($lines_24 > $one_hour_limit) {
										$nan_1[$index]++;
									}
								}
							}
						}
					}

					$notified = false;

					// 24 hour statistics
					foreach	($nan_24 as $index=>$count) {
						if ($lines_24 > 0) {
							$ratio = $count/$lines_24;
						} else {
							$ratio = 0;

							db_execute_prepared ('INSERT INTO rrdcheck
								(local_data_id, test_date, message)
								VALUES (?,NOW(),?)',
								array(
									$local_data_id,
									"No data returned, maybe corrupted Data Source '{$data_source_names[$index]}'.  File '$file'."
								)
							);
						}

						if ($ratio == 1) {
							db_execute_prepared ('INSERT INTO rrdcheck
								(local_data_id, test_date, message)
								VALUES (?,NOW(),?)',
								array(
									$local_data_id,
									"Stale values for last 24 hours, Data Source '{$data_source_names[$index]}'.  File '$file'."
								)
							);

							$notified = true;
						} elseif ($ratio > 0.5) {
							db_execute_prepared ('INSERT INTO rrdcheck
								(local_data_id, test_date, message)
								VALUES (?,NOW(),?)',
								array(
									$local_data_id,
									"More than 50% ($count/$lines_24) values are NaN in last 24 hours, Data Source '{$data_source_names[$index]}'.  File '$file'."
								)
							);
						}
					}

					// 1 hour statistics

					foreach	($nan_1 as $index=>$count) {
						if ($notified) {
							// 24hour notified, skipping
							continue;
						}

						if ($lines_1 > 0) {
							$ratio = $count/$lines_1;
						} else {
							$ratio = 0;

							db_execute_prepared ('INSERT INTO rrdcheck
								(local_data_id, test_date, message)
								VALUES (?,NOW(),?)',
								array(
									$local_data_id,
									"No data returned, maybe corrupted Data Source '{$data_source_names[$index]}'.  File '$file'."
								)
							);
						}

						if ($ratio == 1) {
							db_execute_prepared ('INSERT INTO rrdcheck
								(local_data_id, test_date, message)
								VALUES (?,NOW(),?)',
								array(
									$local_data_id,
									"Stale values for last hour, Data Source '{$data_source_names[$index]}'.  File '$file'."
								)
							);
						} elseif ($ratio > 0.5) {
							db_execute_prepared ('INSERT INTO rrdcheck
								(local_data_id, test_date, message)
								VALUES (?,NOW(),?)',
								array(
									$local_data_id,
									"More than 50% ($count/$lines_1) values are NaN in last hour, Data Source '{$data_source_names[$index]}'.  File '$file'."
								)
							);
						}
					}
				} else {
					cacti_log("WARNING: RRDcheck - no rrd data returned - '$file'", false, 'RRDCHECK');
				}
			} else {	// rrdfile does not exist
				db_execute_prepared ('INSERT INTO rrdcheck
					(local_data_id, test_date, message)
					VALUES (?,NOW(),?)',
					array($local_data_id, "RRDfile does not exist - '$file'"));
			}
		}
	}

	if ($use_proxy) {
		rrd_close($rrdtool_pipe);
	} else {
		rrdcheck_rrdtool_close($process);
	}

	if (!empty($type)) {
		$total_user   += $user_time;
		$total_system += $system_time;
		$total_real   += $real_time;
		$total_dsses  += $dsses;

		set_config_option('rrdcheck_rrd_system_'  . $type . '_' . $thread_id, $total_system);
		set_config_option('rrdcheck_rrd_user_'    . $type . '_' . $thread_id, $total_user);
		set_config_option('rrdcheck_rrd_real_'    . $type . '_' . $thread_id, $total_real);
		set_config_option('rrdcheck_total_rrds_'  . $type . '_' . $thread_id, $rrd_files);
		set_config_option('rrdcheck_total_dsses_' . $type . '_' . $thread_id, $total_dsses);
	}
}

/**
 * rrdcheck_log_statistics - provides generic timing message to both the Cacti log and the settings
 *   table so that the statistcs can be graphed as well.
 *
 * @param $type - (string) the type of statistics to log, either 'HOURLY', 'BOOST'.
 *
 * @return - NULL
 */
function rrdcheck_log_statistics($type) {
	global $start;

	rrdcheck_debug($type);

	if ($type == 'BOOST') {
		$sub_type = 'bchild';
	} else {
		$sub_type = '';
	}

	/* take time and log performance data */
	$end = microtime(true);

	if ($sub_type != '') {
		$rrd_user = db_fetch_cell_prepared("SELECT SUM(value)
			FROM settings
			WHERE name LIKE ?",
			array('rrdcheck_rrd_user_%' . $sub_type . '%'));

		$rrd_system = db_fetch_cell_prepared("SELECT SUM(value)
			FROM settings
			WHERE name LIKE ?",
			array('rrdcheck_rrd_system_%' . $sub_type . '%'));

		$rrd_real = db_fetch_cell_prepared("SELECT SUM(value)
			FROM settings
			WHERE name LIKE ?",
			array('rrdcheck_rrd_real_%' . $sub_type . '%'));

		$rrd_files = db_fetch_cell_prepared("SELECT SUM(value)
			FROM settings
			WHERE name LIKE ?",
			array('rrdcheck_total_rrds_%' . $sub_type . '%'));

		$dsses = db_fetch_cell_prepared("SELECT SUM(value)
			FROM settings
			WHERE name LIKE ?",
			array('rrdcheck_total_dsses_%' . $sub_type . '%'));

		$processes  = read_config_option('rrdcheck_parallel');

		$cacti_stats = sprintf('Time:%01.2f Type:%s Threads:%s RRDfiles:%s DSSes:%s RRDUser:%01.2f RRDSystem:%01.2f RRDReal:%01.2f', $end - $start, $type, $processes, $rrd_files, $dsses, $rrd_user, $rrd_system, $rrd_real);

		db_execute("DELETE FROM settings
			WHERE name LIKE 'rrdcheck_rrd_%$sub_type%'
			OR name LIKE 'rrdcheck_total_rrds_%$sub_type%'
			OR name LIKE 'rrdcheck_total_dsses_%$sub_type%'");
	} else {
		$cacti_stats = sprintf('Time:%01.2f Type:%s', $end-$start, $type);
	}

	/* take time and log performance data */
	$start = microtime(true);

	/* log to the database */
	set_config_option('stats_rrdcheck_' . $type, $cacti_stats);

	/* log to the logfile */
	cacti_log('RRDCHECK STATS: ' . $cacti_stats , true, 'SYSTEM');
}

/**
 * rrdcheck_log_child_stats - logs rrdcheck child process information
 *
 * @param $type        - (string) The type of child, MAJOR, DAILY, BOOST
 * @param $thread_id   - (int) The parallel thread id
 * @param $total_time  - (int) The total time to collect date
 *
 * @return - NULL
 */
function rrdcheck_log_child_stats($type, $thread_id, $total_time) {
	$rrd_user = db_fetch_cell_prepared("SELECT SUM(value)
		FROM settings
		WHERE name LIKE ?",
		array('rrdcheck_rrd_user_%' . $type . '_' . $thread_id . '%'));

	$rrd_system = db_fetch_cell_prepared("SELECT SUM(value)
		FROM settings
		WHERE name LIKE ?",
		array('rrdcheck_rrd_system_%' . $type . '_' . $thread_id . '%'));

	$rrd_real = db_fetch_cell_prepared("SELECT SUM(value)
		FROM settings
		WHERE name LIKE ?",
		array('rrdcheck_rrd_real_%' . $type . '_' . $thread_id . '%'));

	$rrd_files = db_fetch_cell_prepared("SELECT SUM(value)
		FROM settings
		WHERE name LIKE ?",
		array('rrdcheck_total_rrds_%' . $type . '_' . $thread_id . '%'));

	$dsses = db_fetch_cell_prepared("SELECT SUM(value)
		FROM settings
		WHERE name LIKE ?",
		array('rrdcheck_total_dsses_%' . $type . '_' . $thread_id . '%'));

	$cacti_stats = sprintf('Time:%01.2f Type:%s ProcessNumber:%s RRDfiles:%s DSSes:%s RRDUser:%01.2f RRDSystem:%01.2f RRDReal:%01.2f', $total_time, strtoupper($type), $thread_id, $rrd_files, $dsses, $rrd_user, $rrd_system, $rrd_real);

	cacti_log('RRDCHECK CHILD STATS: ' . $cacti_stats, true, 'SYSTEM');
}

/**
 * rrdcheck_error_handler - this routine logs all PHP error transactions
 *   to make sure they are properly logged.
 *
 * @param $errno    - (int) The errornum reported by the system
 * @param $errmsg   - (string) The error message provides by the error
 * @param $filename - (string) The filename that encountered the error
 * @param $linenum  - (int) The line number where the error occurred
 * @param $vars     - (mixed) The current state of PHP variables.
 *
 * @returns - (bool) always returns true for some reason
 */
function rrdcheck_error_handler($errno, $errmsg, $filename, $linenum, $vars = []) {
	if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG) {
		/* define all error types */
		$errortype = array(
			E_ERROR             => 'Error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parsing Error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING      => 'User Warning',
			E_USER_NOTICE       => 'User Notice',
			E_STRICT            => 'Runtime Notice'
		);

		if (defined('E_RECOVERABLE_ERROR')) {
			$errortype[E_RECOVERABLE_ERROR] = 'Catchable Fatal Error';
		}

		/* create an error string for the log */
		$err = "ERRNO:'"  . $errno   . "' TYPE:'"    . $errortype[$errno] .
			"' MESSAGE:'" . $errmsg  . "' IN FILE:'" . $filename .
			"' LINE NO:'" . $linenum . "'";

		/* let's ignore some lesser issues */
		if (substr_count($errmsg, 'date_default_timezone')) return;
		if (substr_count($errmsg, 'Only variables')) return;

		/* log the error to the Cacti log */
		cacti_log('PROGERR: ' . $err, false, 'RRDCHECK');
	}

	return;
}

/**
 * rrdcheck_boost_bottom - this routine accomodates rrdcheck after the boost process
 *   has completed.  The use of boost will require boost version 2.5 or above.  The idea
 *   if that rrdcheck will be started on the boost cycle.
 *
 * @return - NULL
 */
function rrdcheck_boost_bottom() {
	global $config;

	if (read_config_option('rrdcheck_enable') == 'on') {
		include_once($config['base_path'] . '/lib/rrd.php');

		/* run the daily stats. log to database to prevent secondary runs */
		set_config_option('rrdcheck_last_run_time', date('Y-m-d G:i:s', time()));

		/* run the daily stats */
		rrdcheck_launch_children('bmaster');

		/* Wait for all processes to continue */
		while ($running = rrdcheck_processes_running('bmaster')) {
			rrdcheck_debug(sprintf('%s Processes Running, Sleeping for 2 seconds.', $running));

			sleep(2);
		}

		rrdcheck_log_statistics('BOOST');
	}
}

/**
 * rrdcheck_poller_bottom - this routine launches the main rrdcheck poller.
 *   It is forked independently
 *   to the Cacti poller after all polling has finished.
 *
 * @return - NULL
 */
function rrdcheck_poller_bottom () {
	global $config;

	if (read_config_option('rrdcheck_enable') == 'on') {
		include_once($config['library_path'] . '/poller.php');

		chdir($config['base_path']);

		$command_string = read_config_option('path_php_binary');

		if (read_config_option('path_rrdcheck_log') != '') {
			if ($config['cacti_server_os'] == 'unix') {
				$extra_args = '-q ' . $config['base_path'] . '/poller_rrdcheck.php >> ' . read_config_option('path_rrdcheck_log') . ' 2>&1';
			} else {
				$extra_args = '-q ' . $config['base_path'] . '/poller_rrdcheck.php >> ' . read_config_option('path_rrdcheck_log');
			}
		} else {
			$extra_args = '-q ' . $config['base_path'] . '/poller_rrdcheck.php';
		}

		exec_background($command_string, $extra_args);
	}
}

/**
 * rrdcheck_rrdtool_init - this routine provides a bi-directional socket based connection to RRDtool.
 *   it provides a high speed connection to rrdfile in the case where the traditional Cacti call does
 *   not when performing fetch type calls.
 *
 * @return - (mixed) An array that includes both the process resource and the pipes to communicate
 *   with RRDtool.
 */
function rrdcheck_rrdtool_init() {
	global $config;

	if ($config['cacti_server_os'] == 'unix') {
		$fds = array(
			0 => array('pipe', 'r'), // stdin
			1 => array('pipe', 'w'), // stdout
			2 => array('file', '/dev/null', 'a')  // stderr
		);
	} else {
		$fds = array(
			0 => array('pipe', 'r'), // stdin
			1 => array('pipe', 'w'), // stdout
			2 => array('file', 'nul', 'a')  // stderr
		);
	}

	/* set the rrdtool default font */
	if (read_config_option('path_rrdtool_default_font')) {
		putenv('RRD_DEFAULT_FONT=' . read_config_option('path_rrdtool_default_font'));
	}

	$command = read_config_option('path_rrdtool') . ' - ';

	$process = proc_open($command, $fds, $pipes);

	/* make stdin/stdout/stderr non-blocking */
	stream_set_blocking($pipes[0], 0);
	stream_set_blocking($pipes[1], 0);

	return array($process, $pipes);
}

/**
 * rrdcheck_rrdtool_execute - this routine passes commands to RRDtool and returns the information
 *   back to rrdcheck.  It is important to note here that RRDtool needs to provide an either 'OK'
 *   or 'ERROR' response accross the pipe as it does not provide EOF characters to key upon.
 *   This may not be the best method and may be changed after I have a conversation with a few
 *   developers.
 *
 * @param $command - (string) The rrdtool command to execute
 * @param $pipes   - (array) An array of stdin and stdout pipes to read and write data from
 *
 * @returns - (string) The output from RRDtool
 */
function rrdcheck_rrdtool_execute($command, &$pipes) {
	static $broken = false;

	$stdout = '';

	if ($command == '') return;

	$command .= "\r\n";
	$return_code = fwrite($pipes[0], $command);

	if (is_resource($pipes[1])) {
		while (!feof($pipes[1])) {
			$stdout .= fgets($pipes[1], 4096);

			if (substr_count($stdout, 'OK')) {
				break;
			}

			if (substr_count($stdout, 'ERROR')) {
				break;
			}
		}
	} elseif (!$broken) {
		cacti_log("ERROR: RRDtool was unable to fork.  Likely RRDtool can not be found or system out of resources.  Blocking subsequent messages.", false, 'POLLER');

		$broken = true;
	}

	if (strlen($stdout)) {
		return $stdout;
	}
}

/**
 * rrdcheck_rrdtool_close - this routine closes the RRDtool process thus also
 *   closing the pipes.
 *
 * @return - NULL
 */
function rrdcheck_rrdtool_close($process) {
	proc_close($process);
}

/**
 * rrdcheck_launch_children - this function will launch collector children based upon
 *   the maximum number of threads and the process type
 *
 * @param $type - (string) The process type
 *
 * @return - NULL
 */
function rrdcheck_launch_children($type) {
	global $config, $debug;

	$processes = read_config_option('rrdcheck_parallel');

	if (empty($processes)) {
		$processes = 1;
	}

	$php_binary = read_config_option('path_php_binary');

	rrdcheck_debug("About to launch $processes processes.");

	$sub_type = rrdcheck_get_subtype($type);

	for ($i = 1; $i <= $processes; $i++) {
		rrdcheck_debug(sprintf('Launching rrdcheck Process Number %s for Type %s', $i, $type));

		cacti_log(sprintf('NOTE: Launching rrdcheck Process Number %s for Type %s', $i, $type), false, 'BOOST', POLLER_VERBOSITY_MEDIUM);

		exec_background($php_binary, $config['base_path'] . "/poller_rrdcheck.php --type=$sub_type --child=$i" . ($debug ? ' --debug':''));
	}

	sleep(2);
}

/**
 * rrdcheck_get_subtype - this function determine the applicable
 *   sub-type (child name) and return if based upon a type
 *
 * @param $type - (string) The process type
 *
 * @return - (string) The sub type
 */
function rrdcheck_get_subtype($type) {
	switch($type) {
		case 'master':
		case 'pmaster':
			return 'child';

			break;
		case 'bmaster':
			return 'bchild';

			break;
	}
}

/**
 * rrdcheck_kill_running_processes - this function is part of an interrupt
 *   handler to kill children processes when the parent is killed
 *
 * @return - NULL
 */
function rrdcheck_kill_running_processes() {
	global $type;

	if ($type == 'bmaster') {
		$processes = db_fetch_assoc_prepared('SELECT *
			FROM processes
			WHERE tasktype = "rrdcheck"
			AND taskname = "bchild"
			AND pid != ?',
			array(getmypid()));
	} else {
		$processes = db_fetch_assoc_prepared('SELECT *
			FROM processes
			WHERE tasktype = "rrdcheck"
			AND taskname = "child"
			AND pid != ?',
			array(getmypid()));
	}

	if (cacti_sizeof($processes)) {
		foreach($processes as $p) {
			cacti_log(sprintf('WARNING: Killing rrdcheck %s PID %d due to another due to signal or overrun.', ucfirst($p['taskname']), $p['pid']), false, 'BOOST');

			posix_kill($p['pid'], SIGTERM);

			unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $p['pid']);
		}
	}
}

/**
 * rrdcheck_processes_running - given a type, determine the number
 *   of sub-type or children that are currently running
 *
 * @param $type - (string) The process type
 *
 * @return - (int) The number of running processes
 */
function rrdcheck_processes_running($type) {
	$sub_type = rrdcheck_get_subtype($type);

	$running = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM processes
		WHERE tasktype = "rrdcheck"
		AND taskname = ?',
		array($sub_type));

	if ($running == 0) {
		return 0;
	}

	return $running;
}

