<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

/* dsstats_debug - this simple routine print's a standard message to the console
	 when running in debug mode.
   @returns - NULL */
function dsdebug_debug($message) {
	global $debug;

	if ($debug) {
		print 'DSDEBUG: ' . $message . "\n";
	}
}

/* log_dsstats_statistics - provides generic timing message to both the Cacti log and the settings
	 table so that the statistics can be graphed as well.
   @arg $type - (string) the type of statistics to log, either 'HOURLY', 'DAILY' or 'MAJOR'.
   @returns - null */
function log_dsdebug_statistics($type, $checks, $issues) {
	global $start;

	/* take time and log performance data */
	$end = microtime(true);

	$cacti_stats = sprintf('ChecksPerformed:%d, TotalIssues:%d, Time:%01.4f ', $checks, $issues, round($end - $start,4));

	/* log to the database */
	set_config_option('stats_dsdebug_' . $type, $cacti_stats);

	/* log to the logfile */
	cacti_log('DSDEBUG STATS: Type:' . $type . ', ' . $cacti_stats , true, 'SYSTEM');
}

/* dsstats_error_handler - this routine logs all PHP error transactions
	 to make sure they are properly logged.
   @arg $errno - (int) The errornum reported by the system
   @arg $errmsg - (string) The error message provides by the error
   @arg $filename - (string) The filename that encountered the error
   @arg $linenum - (int) The line number where the error occurred
   @arg $vars - (mixed) The current state of PHP variables.
   @returns - (bool) always returns true for some reason */
function dsdebug_error_handler($errno, $errmsg, $filename, $linenum, $vars = array()) {
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
		if (substr_count($errmsg, 'date_default_timezone')) {
			return;
		}

		if (substr_count($errmsg, 'Only variables')) {
			return;
		}

		/* log the error to the Cacti log */
		cacti_log('PROGERR: ' . $err, false, 'DSDEBUG');
	}

	return;
}

function dsdebug_poller_output(&$rrd_update_array) {
	global $config, $ds_types, $ds_last, $ds_steps, $ds_multi;

	/* suppress warnings */
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	/* install the dsstats error handler */
	set_error_handler('dsdebug_error_handler');

	/* do not make any calculations unless enabled */
	$checks = db_fetch_assoc('SELECT * FROM data_debug WHERE `done` = 0');

	if (cacti_sizeof($checks)) {
		if (cacti_sizeof($rrd_update_array)) {
			foreach ($checks as $c) {
				foreach ($rrd_update_array as $item) {
					if ($c['datasource'] == $item['local_data_id']) {
						if (isset($item['times'][key($item['times'])])) {
							$c['info'] = cacti_unserialize($c['info']);

							$c['info']['last_result'] = $item['times'][key($item['times'])];
							$c['info']                = serialize($c['info']);
							db_execute_prepared('UPDATE data_debug SET `info` = ? WHERE `id` = ?', array($c['info'], $c['id']));
						}
					}
				}
			}
		}
	}

	/* restore original error handler */
	restore_error_handler();
}

function dsdebug_poller_bottom() {
	global $config, $start;

	/* install the dsstats error handler */
	set_error_handler('dsdebug_error_handler');

	/* take time and log performance data */
	$start = microtime(true);

	if (!db_table_exists('data_debug')) {
		return true;
	}

	$checks = db_fetch_assoc('SELECT *
		FROM data_debug
		WHERE `done` = 0');

	if (!empty($checks)) {
		clearstatcache();
		$total_issues = 0;

		foreach ($checks as $c) {
			$c['issue'] = array();
			$info = cacti_unserialize($c['info']);

			$dtd = db_fetch_row_prepared('SELECT *
				FROM data_template_data
				WHERE local_data_id = ?',
				array($c['datasource']));

			if (!isset($dtd['local_data_id'])) {
				$c['issue'][] = __('Data Source ID %s does not exist', $c['datasource']);
				$c['done']    = 1;

				$total_issues++;
			} else {
				if (read_config_option('boost_rrd_update_enable') == 'on') {
					boost_process_poller_output($c['datasource']);
				}

				$real_path = str_replace('<path_rra>', CACTI_PATH_RRA, $dtd['data_source_path']);

				// rrd_folder_writable
				$info['rrd_folder_writable'] = (is_resource_writable(dirname($real_path) . '/') ? 1 : 0);

				// rrd_exists
				$info['rrd_exists'] = (file_exists($real_path) ? 1 : 0);

				// rrd_writable
				$info['rrd_writable'] = (is_resource_writable($real_path) ? 1 : 0);

				// active
				$info['active'] = $dtd['active'];
				// owner
				if ($config['cacti_server_os'] == 'win32') {
					$info['owner'] = '<unable to determine on windows>';
				} else {
					$o             = posix_getpwuid(fileowner($real_path));
					$o             = $o['name'];
					$g             = posix_getgrgid(filegroup($real_path));
					$g             = $g['name'];
					$info['owner'] =  $o . ':' . $g;
				}

				// poller_runas
				$info['runas_poller'] = get_running_user();

				// convert_name
				$info['convert_name'] = (strpos('|', get_data_source_title($c['datasource'])) === false ? 1 : 0);

				// last_result  (processed by hook)
				if (is_array($info['last_result']) && !empty($info['last_result']) && $info['valid_data'] == '') {
					$info['valid_data'] = 1;

					foreach ($info['last_result'] as $k => $l) {
						if ($l == 'U') {
							cacti_log('Bad Data Found for Data Source ID ' . $c['datasource'], false, 'DSDEBUG');
							$info['valid_data'] = 0;

							$total_issues++;
						}
					}
				}

				$rrdinfo = rrdtool_function_info($c['datasource']);

				if (cacti_sizeof($rrdinfo)) {
					$comp                    = rrdtool_cacti_compare($c['datasource'], $rrdinfo);
					$info['rrd_match']       = (is_array($comp) && empty($comp) ? 1 : 0);
					$info['rrd_match_array'] = $comp;
					$info['rrd_info']        = $rrdinfo;

					// rra_timestamp
					if ($info['rra_timestamp'] != ''
						&& isset($rrdinfo['last_update'])
						&& $info['rra_timestamp'] != $rrdinfo['last_update']) {

						$info['rra_timestamp2'] = $rrdinfo['last_update'];
					}

					if (isset($rrdinfo['last_update']) && $info['rra_timestamp'] == '') {
						$info['rra_timestamp'] = $rrdinfo['last_update'];
					}

					$c['done'] = 1;
					$f = array();
					foreach ($info as $k => $v) {
						if ($v === '') {
							$c['done'] = 0;
							$f[] = $k;
						}
					}

					if ($c['started'] < time() - ($dtd['rrd_step'] * 5)) {
						$c['done'] = 1;
						$c['issue'][] = __('Debug not completed after 5 pollings');
						$c['issue'][] = __('Failed fields: ') . implode(', ', $f);

						$total_issues++;
					}

					// Try to determine issue
					// Not set as Active
					// Log permanent fails first
					if ($info['active'] != 'on') {
						$c['issue'][] = __('Data Source is not set as Active');
						$c['done'] = 1;

						$total_issues++;
					}

					// File Permissions
					if ((!$info['rrd_exists'] || !$info['rrd_writable']) && !$info['rrd_folder_writable']) {
						$c['issue'][] = __('RRDfile Folder (rra) is not writable by Poller.  Folder owner: %s.  Poller runs as: %s', $o, $info['runas_poller']);
						$c['done'] = 1;

						$total_issues++;
					} elseif (!$info['rrd_writable']) {
						$c['issue'][] = __('RRDfile is not writable by Poller.  RRDfile owner: %s.  Poller runs as %s', $o, $info['runas_poller']);
						$c['done'] = 1;

						$total_issues++;
					}

					// For errors that only appear after so many errors next
					if ($c['done'] == 1) {
						if ($info['rrd_match'] == 0) {
							$c['issue'][] = __('RRDfile does not match Data Source');
							$total_issues++;
						}

						if ($info['rra_timestamp2'] == '') {
							$c['issue'][] = __('RRDfile not updated after polling');
							$total_issues++;
						}

						if (is_array($info['last_result']) && !empty($info['last_result'])) {
							foreach ($info['last_result'] as $k => $l) {
								if ($l == 'U') {
									$c['issue'][] = __('Data Source returned Bad Results for ' . $k);
									$total_issues++;
								}
							}
						} elseif ($info['last_result'] == '') {
							$c['issue'][] = __('Data Source was not polled');
							$total_issues++;
						}

						if ($c['issue'] == '') {
							$c['issue'][] = __('No issues found');
						}
					}
				} else {
					$c['issue'][] = __('RRDfile not created yet');
					$total_issues++;
				}

				$info = serialize($info);

				db_execute_prepared('UPDATE data_debug
					SET `done` = ?, `info` = ?, `issue` = ?
					WHERE id = ?',
					array($c['done'], $info, trim(implode("\n", $c['issue'])), $c['id']));
			}
		}

		log_dsdebug_statistics('poller', cacti_sizeof($checks), $total_issues);
	}

	restore_error_handler();
}

function dsdebug_run_repair($id) {
	$check = db_fetch_row_prepared('SELECT *
		FROM data_debug
		WHERE datasource = ?',
		array($id));

	if (cacti_sizeof($check)) {
		$check['info'] = cacti_unserialize($check['info']);

		if (isset($check['info']['rrd_match_array']['tune'])) {
			$path = get_data_source_path($id, true);

			if (is_writeable($path)) {
				$rrdtool_path = read_config_option('path_rrdtool');
				$failures     = 0;
				$failure_data = '';

				foreach ($check['info']['rrd_match_array']['tune'] as $options) {
					$command    = $rrdtool_path . ' tune ' . $options;

					$output = rrdtool_execute('tune ' . $options, false, RRDTOOL_OUTPUT_RETURN_STDERR);

					if ($output == '') {
						cacti_log("RRDfile repair command succeeded for DS[$id] Command[$command]", false, 'DSDEBUG');
					} else {
						cacti_log("ERROR: RRDfile repair command failed for DS[$id] Command[$command] Output[$output]", false, 'DSDEBUG');
						$failures++;
					}
				}

				if ($failures == 0) {
					return true;
				}
			} else {
				cacti_log("ERROR: RRDfile Repair Command Failed for DS[$id] Output[Unable to write RRDfile]", false, 'DSDEBUG');
			}
		} else {
			cacti_log("ERROR: RRDfile Repair Command Could not be run for DS[$id] Output[No tune recommendation found]", false, 'DSDEBUG');
		}
	} else {
		cacti_log("ERROR: RRDfile Repair Command Could not be run for DS[$id] Output[No Data Source debug information found]", false, 'DSDEBUG');
	}

	return false;
}
