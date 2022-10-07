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

function get_rrdfiles($thread_id = 1, $max_threads = 1) {
	static $newrows = array();

	if ($max_threads == 1) {
		return db_fetch_assoc('SELECT dtd.local_data_id AS local_data_id, rrd_name, data_source_path, dtd.rrd_step AS rrdstep
			FROM data_template_data AS dtd
			JOIN poller_item AS pi
			ON pi.local_data_id = dtd.local_data_id
			WHERE pi.local_data_id IS NOT NULL
			AND data_source_path != ""
			AND dtd.local_data_id != 0');
	} elseif (sizeof($newrows)) {
		return $newrows[$thread_id];
	} else {
		$rows = db_fetch_assoc('SELECT dtd.local_data_id AS local_data_id, rrd_name, data_source_path, dtd.rrd_step AS rrdstep
			FROM data_template_data AS dtd
			JOIN poller_item AS pi
			ON pi.local_data_id = dtd.local_data_id
			WHERE pi.local_data_id IS NOT NULL
			AND data_source_path != ""
			AND dtd.local_data_id != 0');

		$split_size = ceil(cacti_sizeof($rows) / $max_threads);

		$thread  = 1;
		$rras = 0;
		$newrows = array();

		foreach($rows as $row) {
			$rras++;

			if ($rras > $split_size && $thread < $max_threads) {
				$thread++;
				$rras = 0;
			}

			$newrows[$thread][] = $row;
		}

		if (isset($newrows[$thread_id])) {
			return $newrows[$thread_id];
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
		$end = time() - $poller_interval;
		$start = $end - 86400;

		$done = array();

		foreach ($rrdfiles as $rrdfile) {
			if (in_array ($rrdfile['local_data_id'], $done)) {
				continue;
			}

			$file = str_replace('<path_rra>', $config['rra_path'], $rrdfile['data_source_path']);

			if ($use_proxy) {
				$file_exists = rrdtool_execute("file_exists $file", true, RRDTOOL_OUTPUT_BOOLEAN, false, 'rrdcheck');
			} else {
				clearstatcache();
				$file_exists = file_exists($file);
			}

			// don't attempt to get information if the file does not exist
			if ($file_exists) {
				if (time() > (filemtime($file)+3600)) {
					db_execute_prepared ('INSERT INTO rrdcheck (local_data_id,test_date,message) VALUES
					(?,NOW(),?)', array($rrdfile['local_data_id'], "RRD file modify time older than hour - $file"));
					$done[] = $rrdfile['local_data_id'];
				} else {
					$pstart = $start - $rrdfile['rrdstep'];
					$pend = $end - $rrdfile['rrdstep'];
					$one_hour_limit = 3600/$rrdfile['rrdstep'] * 23;

					if ($use_proxy) {
						$info = rrdtool_execute("fetch $file LAST -s $pstart -e $pend ", false, RRDTOOL_OUTPUT_STDOUT, false, 'rrdcheck');
					} else {
						$info = rrdcheck_rrdtool_execute("fetch $file LAST -s $pstart -e $pend", $pipes);
					}

					/* don't do anything if RRDfile did not return data */
					$info_array = explode("\n", $info);

					if (cacti_sizeof($info_array)) {
						$first = true;
						$lines_24 = 0;
						$lines_1 = 0;
						$nan_24 = array();
						$nan_1 = array();

						foreach($info_array as $line) {
							$line = trim($line);

							if ($first) {
								/* get the data source names */
								$data_source_names = preg_split('/\s+/', $line);

								foreach ($data_source_names as $index => $name)  {
                							$nan_24[$index] = 0;
									$nan_1[$index] = 0;
								}

								$dsses+= cacti_sizeof($data_source_names);

								$first = false;

							} elseif ($line != '') {
								// remove line - OK u:0.03 s:0.12 r:0.33
								if (substr($line, 0, 2) == 'OK') {
									continue;
								}

								$parts = explode(':', $line);
								$data  = explode(' ', trim($parts[1]));

								foreach($data as $index=>$number) {
									if ($index == 0) {	// only onetime for each row
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
								cacti_log("WARNING: RRDcheck - something is wrong lines24=$lines_24, DS " . $data_source_names[$index] . ", file " .  $file  , false, 'rrdcheck');
							}

							if ($ratio == 1) {
									db_execute_prepared ('INSERT INTO rrdcheck (local_data_id,test_date,message) VALUES
										(?,NOW(),?)', array($rrdfile['local_data_id'], "Stale values for last 24 hours, DS $data_source_names[$index], file $file"));
									$notified = true;
							} elseif ($ratio > 0.5) {
								db_execute_prepared ('INSERT INTO rrdcheck (local_data_id,test_date,message) VALUES
										(?,NOW(),?)', array($rrdfile['local_data_id'], "More than 50% ($count/$lines_24) values are NaN in last 24 hours, DS $data_source_names[$index], file $file"));
							}
						}

						// 1 hour statistics
						foreach	($nan_1 as $index=>$count) {
							if ($notified) {	// 24hour notified, skipping
								continue;
							}

							if ($lines_1 > 0) {
								$ratio = $count/$lines_1;
							} else {
								$ratio = 0;
								cacti_log("WARNING: RRDcheck - something is wrong lines1=$lines_1, DS " . $data_source_names[$index] . ", file " .  $file  , false, 'rrdcheck');
							}

							if ($ratio == 1) {
								db_execute_prepared ('INSERT INTO rrdcheck (local_data_id,test_date,message) VALUES
										(?,NOW(),?)', array($rrdfile['local_data_id'], "Stale values for last hour, DS $data_source_names[$index], file $file"));
							} elseif ($ratio > 0.5) {
								db_execute_prepared ('INSERT INTO rrdcheck (local_data_id,test_date,message) VALUES
									(?,NOW(),?)', array($rrdfile['local_data_id'], "More than 50% ($count/$lines_1) values are NaN in last hour, DS $data_source_names[$index], file $file"));
							}
						}

						$done[] = $rrdfile['local_data_id'];
					} else {
						cacti_log("WARNING: RRDcheck - no rrd data returned - " .  $file  , false, 'rrdcheck');
						$done[] = $rrdfile['local_data_id'];
					}
				}	// end of process the rrd file
			} else {	// rrdfile does not exist
				db_execute_prepared ('INSERT INTO rrdcheck (local_data_id,test_date,message) VALUES
					(?,NOW(),?)', array($rrdfile['local_data_id'], "RRD file does not exist - $file"));
				$done[] = $rrdfile['local_data_id'];
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
	}
	else {
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
	cacti_log('rrdcheck STATS: ' . $cacti_stats , true, 'SYSTEM');
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

	cacti_log('rrdcheck CHILD STATS: ' . $cacti_stats, true, 'SYSTEM');
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
		cacti_log('PROGERR: ' . $err, false, 'rrdcheck');
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

	if (strlen($stdout)) return $stdout;
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

