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

/**
 * boost_array_orderby - performs a multicolumn sort of an
 *   array
 */
function boost_array_orderby() {
	$args = func_get_args();
	$data = array_shift($args);

	foreach ($args as $n => $field) {
		if (is_string($field)) {
			$tmp = array();

			foreach ($data as $key => $row) {
                $tmp[$key] = $row[$field];
			}

			$args[$n] = $tmp;
		}
	}

	$args[] = &$data;

	call_user_func_array('array_multisort', $args);

	return array_pop($args);
}

function boost_file_size_display($file_size, $digits = 2) {
	if ($file_size > 1024) {
		$file_size = $file_size / 1024;

		if ($file_size > 1024) {
			$file_size = $file_size / 1024;

			if ($file_size > 1024) {
				$file_size = $file_size / 1024;
				return __('%s GBytes', number_format_i18n($file_size, $digits));
			} else {
				return __('%s MBytes', number_format_i18n($file_size, $digits));
			}
		} else {
			return __('%s KBytes', number_format_i18n($file_size, $digits));
		}
	} else {
		return __('%s Bytes', number_format_i18n($file_size, $digits));
	}
}

function boost_get_total_rows() {
	return db_fetch_cell("SELECT SUM(TABLE_ROWS)
		FROM information_schema.tables
		WHERE table_schema = SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%'
		OR table_name LIKE 'poller_output_boost')");
}

function boost_error_handler($errno, $errmsg, $filename, $linenum, $vars = []) {
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

		if (defined('E_DEPRECATED')) {
			$errortype[E_DEPRECATED] = 'Deprecated Warning';
		}

		/* create an error string for the log */
		$err = "ERRNO:'"  . $errno   . "' TYPE:'"    . $errortype[$errno] .
			"' MESSAGE:'" . $errmsg  . "' IN FILE:'" . $filename .
			"' LINE NO:'" . $linenum . "'";

		/* let's ignore some lesser issues */
		if (substr_count($errmsg, 'date_default_timezone')) return;
		if (substr_count($errmsg, 'Only variables')) return;

		/* log the error to the Cacti log */
		cacti_log('PROGERR: ' . $err, false, 'BOOST');
	}

	return;
}

function boost_check_correct_enabled() {
	if ((read_config_option('boost_rrd_update_enable') == 'on') ||
		(read_config_option('boost_rrd_update_system_enable') == 'on')) {
		/* turn on the system level updates as that is what dictates "off" */
		if (read_config_option('boost_rrd_update_system_enable') != 'on') {
			db_execute("REPLACE INTO settings (name,value)
				VALUES ('boost_rrd_update_system_enable','on')");
		}
	} else {
		restore_error_handler();
		return false;
	}

	return true;
}

function boost_poller_on_demand(&$results) {
	global $config, $remote_db_cnn_id;

	if ($config['poller_id'] > 1 && $config['connection'] == 'online') {
		$conn = $remote_db_cnn_id;
	} else {
		$conn = false;
	}

	if (read_config_option('boost_rrd_update_enable') == 'on' || $config['poller_id'] > 1) {
		set_config_option('boost_rrd_update_enable', 'on');

		/* suppress warnings */
		if (defined('E_DEPRECATED')) {
			error_reporting(E_ALL ^ E_DEPRECATED);
		} else {
			error_reporting(E_ALL);
		}

		/* install the boost error handler */
		set_error_handler('boost_error_handler');

		$out_buffer  = '';
		$sql_prefix  = 'INSERT INTO poller_output_boost (local_data_id, rrd_name, time, output) VALUES ';
		$sql_suffix  = ' ON DUPLICATE KEY UPDATE output=VALUES(output)';

		// Add 1 here for potential delimiter
		$overhead    = strlen($sql_prefix) + strlen($sql_suffix) + 1;

		if (boost_check_correct_enabled()) {
			/* if boost redirect is on, rows are being inserted directly */
			if (read_config_option('boost_redirect') == 'on') {
				restore_error_handler();
				return false;
			}

			$max_allowed_packet = db_fetch_row("SHOW VARIABLES LIKE 'max_allowed_packet'");
			$max_allowed_packet = $max_allowed_packet['Value'];

			if (cacti_sizeof($results)) {
				$delim      = '';
				$delim_len  = 0;
				$out_length = 0;

				foreach($results as $result) {
					$tmp_buffer =
						"('" .
						$result['local_data_id'] . "','" .
						$result['rrd_name'] . "','" .
						$result['time'] . "','" .
						$result['output'] .	"')";

					$tmp_length = strlen($tmp_buffer);

					// Calculate length of output buffer, plus overhead, plus the temp buffer
					// is it greater than what SQL allows?
					if (($out_length + $overhead + $tmp_length) > $max_allowed_packet) {

						// Overall length was greater, but do we actually have anything
						// already buffered? Or was it just the temp buffer that overflowed
						// things?
						if ($out_length > 0) {
							db_execute($sql_prefix . $out_buffer . $sql_suffix, true, $conn);
						}

						// Make the temp buffer the starting point for the output buffer, but
						// we don't need a delimiter at this point, so don't include it
						$out_buffer = $tmp_buffer;
						$out_length = $tmp_length;
					} else {
						// We didn't overflow so lets add the temp buffer to the output buffer
						// and include the delimiter string/length.  This will be a blank
						// delimiter on the first iteration as the output buffer will always
						// be blank.
						$out_buffer .= $delim . $tmp_buffer;
						$out_length += $delim_len + $tmp_length;
					}

					// Only on the first iteration do we need to set the delimiter as
					// after that, we will always need it when we are not overflowing
					if ($delim_len == 0) {
						$delim     = ',';
						$delim_len = strlen($delim);
					}
				}

				// output buffer had something left, lets flush it
				if ($out_buffer != '') {
					db_execute($sql_prefix . $out_buffer . $sql_suffix, true, $conn);
				}
			}

			$return_value = false;
		} else {
			$return_value = true;
		}

		/* restore original error handler */
		restore_error_handler();

		return $return_value;
	} else {
		return true;
	}
}

function boost_poller_id_check() {
	global $config;

	$storage_location = read_config_option('storage_location');

	/* error out if running from a remote poller and the storage
	 * location is not the RRDproxy */
	if ($config['poller_id'] > 1) {
		if ($config['connection'] == 'online') {
			if ($storage_location == 0) {
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	return true;
}

function boost_fetch_cache_check($local_data_id, $rrdtool_pipe = false) {
	global $config;

	if (read_config_option('boost_rrd_update_enable') == 'on') {
		/* include poller processing routines */
		include_once($config['library_path'] . '/poller.php');

		/* check to see if boost can do its job */
		if (!boost_poller_id_check()) {
			return false;
		}

		/* suppress warnings */
		if (defined('E_DEPRECATED')) {
			error_reporting(E_ALL ^ E_DEPRECATED);
		} else {
			error_reporting(E_ALL);
		}

		/* install the boost error handler */
		set_error_handler('boost_error_handler');

		/* process input parameters */
		if (!is_resource($rrdtool_pipe)) {
			$rrdtool_pipe = rrd_init();
			$close_pipe = true;
		} else {
			$close_pipe = false;
		}

		/* get the information to populate into the rrd files */
		if (boost_check_correct_enabled()) {
			boost_process_poller_output($local_data_id, $rrdtool_pipe);
		}

		/* restore original error handler */
		restore_error_handler();

		/* close rrdtool */
		if ($close_pipe) {
			rrd_close($rrdtool_pipe);
		}
	}
}

function boost_return_cached_image(&$graph_data_array) {
	if (isset($graph_data_array['export_csv'])) {
		return false;
	} elseif (isset($graph_data_array['export_realtime'])) {
		return false;
	} elseif (isset($graph_data_array['disable_cache']) && $graph_data_array['disable_cache'] == true) {
		return false;
	} elseif (read_config_option('boost_png_cache_enable') == 'on' && boost_determine_caching_state()) {
		return true;
	} else {
		return false;
	}
}

function boost_graph_cache_check($local_graph_id, $rra_id, $rrdtool_pipe, &$graph_data_array, $return = true) {
	global $config;

	/* include poller processing routines */
	include_once($config['library_path'] . '/poller.php');

	/* suppressnwarnings */
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler('boost_error_handler');

	/* check to see if boost can do its job */
	if (!boost_poller_id_check()) {
		return false;
	}

	/* This is a realtime graph */
	if (isset($graph_data_array['export_realtime'])) {
		/* restore original error handler */
		restore_error_handler();

		return false;
	}

	/* if we are just printing the rrd command return */
	if (isset($graph_data_array['print_source'])) {
		/* restore original error handler */
		restore_error_handler();

		return false;
	}

	/* if we want to view the error message, then don't show the cache */
	if ((isset($graph_data_array['output_flag'])) &&
		($graph_data_array['output_flag'] == RRDTOOL_OUTPUT_STDERR)) {
		/* restore original error handler */
		restore_error_handler();

		return false;
	}

	/* get the information to populate into the rrd files */
	if (boost_check_correct_enabled()) {
		/* before we make a graph, we need to check for rrd updates and perform them. */
		$local_data_ids = db_fetch_assoc_prepared('SELECT DISTINCT
			data_template_rrd.local_data_id
			FROM graph_templates_item
			INNER JOIN data_template_rrd
			ON (graph_templates_item.task_item_id = data_template_rrd.id)
			WHERE graph_templates_item.local_graph_id = ?
			AND data_template_rrd.local_data_id > 0', array($local_graph_id));

		/* first update the RRD files */
		if (cacti_sizeof($local_data_ids)) {
			$updates = 0;
			foreach($local_data_ids as $local_data_id) {
				$updates += boost_process_poller_output($local_data_id['local_data_id'], $rrdtool_pipe);
			}

			if ($updates) {
				/* restore original error handler */
				restore_error_handler();

				return false;
			}
		}
	}

	if (isset($_SESSION['sess_current_timespan'])) {
		$timespan = $_SESSION['sess_current_timespan'];
	} else {
		$timespan = 0;
	}

	/* check the graph cache and use it if it is valid, otherwise turn over to
	 * cacti's graphing functions.
	 */
	if (boost_return_cached_image($graph_data_array)) {
		/* if timespan is greater than 1, it is a predefined, if it does not
		 * exist, it is the old fashioned MRTG type graph
		 */
		$cache_directory = read_config_option('boost_png_cache_directory');

		if ($cache_directory != '') {
			if (is_dir($cache_directory)) {
				if (is_writable($cache_directory)) {
					if ($rra_id > 0) {
						$cache_file = $cache_directory . '/' . get_selected_theme() . '_lgi_' . $local_graph_id . '_rrai_' . $rra_id;
					} else {
						$cache_file = $cache_directory . '/' . get_selected_theme() . '_lgi_' . $local_graph_id . '_rrai_' . $rra_id . '_tsi_' . $timespan;
					}

					if (isset($graph_data_array['graph_height'])) {
						$cache_file .= '_height_' . $graph_data_array['graph_height'];
					}
					if (isset($graph_data_array['graph_width'])) {
						$cache_file .= '_width_' . $graph_data_array['graph_width'];
					}

					if (isset($graph_data_array['graph_nolegend'])) {
						$cache_file .= '_thumb.png';
					} else {
						$cache_file .= '.png';
					}

					if (file_exists($cache_file)) {
						$mod_time = filemtime($cache_file);
						$poller_interval = read_config_option('poller_interval');

						if (!isset($poller_interval)) {
							$poller_interval = '300';
						}

						if (($mod_time + $poller_interval) > time()) {
							if ($fileptr = fopen($cache_file, 'rb')) {
								$output = fread($fileptr, filesize($cache_file));
								fclose($fileptr);

								/* restore original error handler */
								restore_error_handler();

								/* get access to the SNMP Cache of BOOST*/
								$mc = new MibCache('CACTI-BOOST-MIB');
								$mc->object('boostStatsTotalsImagesCacheReads')->count();
								$mc->object('boostStatsLastUpdate')->set( time() );

								return $output;
							} else {
								cacti_log("Attempting to open cache file '$cache_file' failed", false, 'BOOST', POLLER_VERBOSITY_DEBUG);
							}
						} else {
							cacti_log("Boost Cache PNG Expired.  Image '$cache_file' will be recreated", false, 'BOOST', POLLER_VERBOSITY_DEBUG);
						}
					}
				} else {
					cacti_log('ERROR: Boost Cache Directory is not writable!  Can not cache images', false, 'BOOST');
				}
			} else {
				cacti_log('ERROR: Boost Cache Directory does not exist! Can not cache images', false, 'BOOST');
			}
		} else {
			cacti_log('ERROR: Boost Cache Directory variable is not set! Can not cache images', false, 'BOOST');
		}
	}

	/* restore original error handler */
	restore_error_handler();

	return false;
}

function boost_prep_graph_array($graph_data_array) {
	/* suppress warnings */
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler('boost_error_handler');

	if (boost_determine_caching_state()) {
		if (!isset($graph_data_array['output_flag'])) {
			if (!isset($graph_data_array['print_source'])) {
				$graph_data_array['output_flag'] = RRDTOOL_OUTPUT_STDOUT;
			}
		}
	}

	/* restore original error handler */
	restore_error_handler();

	return $graph_data_array;
}

function boost_graph_set_file(&$output, $local_graph_id, $rra_id) {
	global $config, $boost_sock, $graph_data_array;

	/* get access to the SNMP Cache of BOOST*/
	$mc = new MibCache('CACTI-BOOST-MIB');

	/* suppress warnings */
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler('boost_error_handler');

	if (isset($_SESSION['sess_current_timespan'])) {
		$timespan = $_SESSION['sess_current_timespan'];
	} else {
		$timespan = 0;
	}

	/* check the graph cache and use it if it is valid, otherwise turn over to
	 * cacti's graphing functions.
	 */
	if ((read_config_option('boost_png_cache_enable')) && (boost_determine_caching_state())) {
		$cache_directory = read_config_option('boost_png_cache_directory');

		if ($cache_directory != '') {
			if (is_dir($cache_directory)) {
				if ($rra_id > 0) {
					$cache_file = $cache_directory . '/' . get_selected_theme() . '_lgi_' . $local_graph_id . '_rrai_' . $rra_id;
				} else {
					$cache_file = $cache_directory . '/' . get_selected_theme() . '_lgi_' . $local_graph_id . '_rrai_' . $rra_id . '_tsi_' . $timespan;
				}

				if (isset($graph_data_array['graph_height'])) {
					$cache_file .= '_height_' . $graph_data_array['graph_height'];
				}

				if (isset($graph_data_array['graph_width'])) {
					$cache_file .= '_width_' . $graph_data_array['graph_width'];
				}

				if (isset($graph_data_array['graph_nolegend'])) {
					$cache_file .= '_thumb.png';
				} else {
					$cache_file .= '.png';
				}

				if (is_writable($cache_directory)) {
					/* if the cache file was created in a prior step, save it */
					if (strlen($output) > 10) {
						if ($fileptr = fopen($cache_file, 'w')) {
							fwrite($fileptr, $output, strlen($output));
							fclose($fileptr);
							chmod($cache_file, 0666);

							/* count the number of images that had to be cached */
							$mc->object('boostStatsTotalsImagesCacheWrites')->count();
							$mc->object('boostStatsLastUpdate')->set( time() );
						}
					}
				} else {
					cacti_log('ERROR: Boost Cache Directory is not writable!  Can not cache images', false, 'BOOST');
				}
			} else {
				cacti_log('ERROR: Boost Cache Directory does not exist! Can not cache images', false, 'BOOST');
			}
		} else {
			cacti_log('ERROR: Boost Cache Directory variable is not set! Can not cache images', false, 'BOOST');
		}
	}

	/* restore original error handler */
	restore_error_handler();
}

/* boost_timer - allows you to time events in boost and provide stats
   @arg $area - a text string that determines what area is being measured
   @arg $type - either 'start' or 'end' to start or end the timing */
function boost_timer($area, $type) {
	global $boost_stats_log;

	/* get the time */
	$btime = microtime(true);

	if ($type == BOOST_TIMER_START) {
		$boost_stats_log[$area][BOOST_TIMER_START] = $btime;
	} elseif ($type == BOOST_TIMER_END) {
		if (isset($boost_stats_log[$area][BOOST_TIMER_START])) {
			if (!isset($boost_stats_log[$area][BOOST_TIMER_TOTAL])) {
				$boost_stats_log[$area][BOOST_TIMER_TOTAL] = 0;
				$boost_stats_log[$area][BOOST_TIMER_CYCLES] = 0;
			}
			$boost_stats_log[$area][BOOST_TIMER_TOTAL] += $btime - $boost_stats_log[$area][BOOST_TIMER_START];
			$boost_stats_log[$area][BOOST_TIMER_CYCLES]++;
			unset($boost_stats_log[$area][BOOST_TIMER_START]);
		}
	}
}

function boost_timer_get_overhead() {
	global $boost_stats_log;

	$start = microtime(true);
	$area = 'calibrate';
	for ($i = 0; $i < BOOST_TIMER_OVERHEAD_MULTIPLIER; $i++) {
		boost_timer($area, BOOST_TIMER_START);
		boost_timer($area, BOOST_TIMER_END);
	}
	unset($boost_stats_log[$area]);
	return (microtime(true) - $start);
}

/* boost_get_arch_table_names - returns current archive boost tables or false if no arch table is present currently */
function boost_get_arch_table_names($latest_table = '') {
	$tableData = db_fetch_assoc("SHOW tables LIKE 'poller_output_boost_arch%'");
	$tableNames = array();
	if (cacti_sizeof($tableData)) {
		foreach($tableData as $table) {
			$table = array_values($table);
			$tableNames[$table[0]] = $table[0];
		}
	}

	if (!cacti_sizeof($tableNames)) {
		$tableNames = array_rekey(
			db_fetch_assoc("SELECT TABLE_NAME AS name
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = SCHEMA()
				AND TABLE_NAME LIKE 'poller_output_boost_arch_%'
				AND TABLE_ROWS > 0"),
			'name', 'name'
		);
	}

	if (!cacti_sizeof($tableNames)) {
		if ($latest_table != '' && db_table_exists($latest_table)) {
			$tableNames[$latest_table] = $latest_table;

			return $tableNames;
		} else {
			return false;
		}
	} else {
		return $tableNames;
	}
}

/**
 * boost_process_poller_output - grabs data from the 'poller_output' and 'poller_output_boost*'
 *   table and feeds to RRDtool for processing.  This function has been repurposed for a
 *   single local_data_id.  In the past, it was designed to handle one to many local_data_ids.
 *
 * The process works as follows:
 *
 * 1) Gather all the rows for the local_data_id from the archive tables on archive table
 *    at a time.
 * 2) Gather all the rows from the main boost table
 * 3) Delete those entries from all the aforementioned tables
 * 4) Merge the results together
 * 5) Process the entire result set
 *
 * @param  (int)      local_data_id - the local data id to update
 * @param  (resourse) rrdtool_pipe - a pointer to the rrdtool process
 *
 * @return (void)
 */
function boost_process_poller_output($local_data_id, $rrdtool_pipe = '') {
	global $config, $database_default, $boost_sock, $boost_timeout, $debug, $get_memory, $memory_used;

	static $archive_table = false;
	static $warning_issued;

	cacti_system_zone_set();

	include_once($config['library_path'] . '/rrd.php');

	/* suppress warnings */
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler('boost_error_handler');

	if (cacti_version_compare(get_rrdtool_version(), '1.5', '<')) {
		while (!db_fetch_cell("SELECT GET_LOCK('boost.single_ds.$local_data_id', 1)")) {
			usleep(50000);
		}
	}

	$data_ids_to_get = read_config_option('boost_rrd_update_max_records_per_select');

	$archive_tables = boost_get_arch_table_names($archive_table);

	$results = array();

	/* avoid getting rows in the middle of poller run */
	$timestamp = db_fetch_cell('SELECT MIN(UNIX_TIMESTAMP(start_time))
		FROM poller_time
		WHERE end_time="0000-00-00"');

	if (empty($timestamp)) {
		$timestamp = time() - 10;
	}

	$query_string        = '';
	$sql_params          = array();
	$locks               = false;
	$temp_table          = false;

	if (cacti_count($archive_tables)) {
		$temp_table = 'poller_output_boost_temp_' . $local_data_id . '_' . mt_rand();

		db_execute("CREATE TEMPORARY TABLE $temp_table LIKE poller_output_boost");

		foreach($archive_tables as $index => $table) {
			db_execute_prepared("INSERT INTO $temp_table
				SELECT *
				FROM $table
				WHERE local_data_id = ?",
				array($local_data_id), false);
		}
	}

	if ($temp_table !== false) {
		db_execute_prepared("INSERT INTO $temp_table
			SELECT *
			FROM poller_output_boost
			WHERE local_data_id = ?
			AND time < FROM_UNIXTIME(?)",
			array($local_data_id, $timestamp), false);

		$query_string = "SELECT po.local_data_id, dl.data_template_id,
			UNIX_TIMESTAMP(po.time) AS timestamp, po.rrd_name, po.output
			FROM $temp_table AS po
			INNER JOIN data_local AS dl
			ON po.local_data_id = dl.id
			WHERE po.local_data_id = ?
			AND po.time < FROM_UNIXTIME(?)
			ORDER BY time ASC, rrd_name ASC";
	} else {
		$query_string = "SELECT po.local_data_id, dl.data_template_id,
			UNIX_TIMESTAMP(po.time) AS timestamp, po.rrd_name, po.output
			FROM poller_output_boost AS po
			INNER JOIN data_local AS dl
			ON po.local_data_id = dl.id
			WHERE po.local_data_id = ?
			AND po.time < FROM_UNIXTIME(?)
			ORDER BY time ASC, rrd_name ASC";
	}

	$sql_params[] = $local_data_id;
	$sql_params[] = $timestamp;

	boost_timer('get_records', BOOST_TIMER_START);
	$results = db_fetch_assoc_prepared($query_string, $sql_params);
	boost_timer('get_records', BOOST_TIMER_END);

	$boost_results = cacti_sizeof($results);

	if ($temp_table !== false) {
		db_execute("DROP TEMPORARY TABLE $temp_table");
	}

	cacti_log('Local Data ID: ' . $local_data_id . ', Boost Results: ' . $boost_results, false, 'BOOST', POLLER_VERBOSITY_MEDIUM);

	/* remove the entries from the table */
	boost_timer('delete', BOOST_TIMER_START);

	if (cacti_count($archive_tables)) {
		foreach($archive_tables as $table) {
			db_execute_prepared("DELETE IGNORE
				FROM $table
				WHERE local_data_id = ?",
				array($local_data_id), false);
		}
	}

	if (cacti_sizeof($results)) {
		db_execute_prepared('DELETE FROM poller_output_boost
			WHERE local_data_id = ?
			AND time < FROM_UNIXTIME(?)',
			array($local_data_id, $timestamp), false);
	}

	boost_timer('delete', BOOST_TIMER_END);

	if (cacti_version_compare(get_rrdtool_version(), '1.5', '<')) {
		db_execute("SELECT RELEASE_LOCK('boost.single_ds.$local_data_id')");
	}

	/* log memory */
	if ($get_memory) {
		$cur_memory = memory_get_usage();

		if ($cur_memory > $memory_used) {
			$memory_used = $cur_memory;
		}
	}

	if (cacti_sizeof($results)) {
		$rrdp_auto_close = false;

		if (!$rrdtool_pipe) {
			$rrdtool_pipe    = rrd_init();
			$rrdp_auto_close = true;
		}

		/* create an array keyed off of each .rrd file */
		$time           = -1;
		$outbuf         = '';
		$last_update    = -1;
		$multi_vals_set = false;

		$last_item = array(
			'local_data_id' => -1,
			'timestamp'     => -1,
			'rrd_name'      => ''
		);

		/* we are going to blow away all record if ok */
		$vals_in_buffer = 0;

		$upd_string_len = read_config_option('boost_rrd_update_string_length');

		/* initialize some variables */
		$rrd_tmpl     = '';
		$rrd_path     = '';
		$outlen       = 0;

		$path_template = boost_get_rrd_filename_and_template($local_data_id);

		if (cacti_sizeof($path_template)) {
			$rrd_path = $path_template['rrd_path'];
			$rrd_tmpl = $path_template['rrd_template'];
		} else {
			$rrd_path = db_fetch_cell_prepared('SELECT rrd_path
				FROM poller_item
				WHERE local_data_id = ?',
				array($local_data_id));
		}

		cacti_log('The RRDpath is ' . $rrd_path, false, 'BOOST', POLLER_VERBOSITY_MEDIUM);
		cacti_log('The RRDpath template is ' . $rrd_tmpl, false, 'BOOST', POLLER_VERBOSITY_MEDIUM);

		$unused_data_source_names = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
				FROM data_template_rrd AS dtr
				LEFT JOIN graph_templates_item AS gti
				ON dtr.id = gti.task_item_id
				WHERE dtr.local_data_id = ?
				AND gti.task_item_id IS NULL',
				array($local_data_id)),
			'data_source_name', 'data_source_name'
		);

		boost_timer('results_cycle', BOOST_TIMER_START);

		/* go through each poller_output_boost entries and process */
		foreach ($results as $item) {
			if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$item['rrd_name']])) {
				continue;
			}

			/**
			 * detect duplicate records, this should not happen,
			 * but adding just in case.
			 */
			if ($last_item['timestamp'] == $item['timestamp'] && $last_item['rrd_name'] == $item['rrd_name']) {
				cacti_log(sprintf('WARNING: Skipping %s:%s due to duplicate record...', $item['local_data_id'], $item['rrd_name']), false, 'BOOST');

				continue;
			}

			/* don't generate error messages if the RRD has already been updated */
			if ($time < $last_update && cacti_version_compare(get_rrdtool_version(), '1.5', '<')) {
				cacti_log("WARNING: Stale Poller Data Found! Item Time:'" . $time . "', RRD Time:'" . $last_update . "' Ignoring Value!", false, 'BOOST', POLLER_VERBOSITY_HIGH);
				$value = 'DNP';
			} else {
				$value = trim($item['output']);
			}

			if ($time != $item['timestamp']) {
				if ($outlen > $upd_string_len) {
					boost_timer('rrdupdate', BOOST_TIMER_START);
					$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $outbuf, $rrdtool_pipe);
					boost_timer('rrdupdate', BOOST_TIMER_END);

					$outbuf         = '';
					$outlen         = 0;
					$vals_in_buffer = 0;

					/* check return status for delete operation */
					if (strpos(trim($return_value), 'OK') === false && $return_value != '') {
						cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", false, 'BOOST');
					}
				}

				if (strpos($value, 'DNP') === false) {
					$output  = ' ' . $item['timestamp'];
					$outbuf .= $output;
					$outlen += strlen($output);
				}

				$time = $item['timestamp'];
			}

			/* single one value output */
			if (strpos($value, 'DNP') !== false) {
				/* continue, bad time */
			} elseif ((is_numeric($value)) || ($value == 'U' && $item['rrd_name'] != '')) {
				$output  = ':' . $value;
				$outbuf .= $output;
				$outlen += strlen($output);
				$vals_in_buffer++;
			} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($value))) {
				$output  = ':' . hexdec($value);
				$outbuf .= $output;
				$outlen += strlen($output);
				$vals_in_buffer++;
			} elseif (strpos($value, ':') !== false) {
				$values = preg_split('/\s+/', $value);

				if (!$multi_vals_set) {
					if ($item['data_template_id'] > 0) {
						$rrd_field_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
								FROM graph_templates_item AS gti
								INNER JOIN data_template_rrd AS dtr
								ON gti.task_item_id = dtr.id
								INNER JOIN data_input_fields AS dif
								ON dtr.data_input_field_id = dif.id
								WHERE dtr.local_data_id = ?',
								array($item['local_data_id'])),
							'data_name', 'data_source_name'
						);

						$unused_data_source_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
								FROM data_template_rrd AS dtr
								LEFT JOIN graph_templates_item AS gti
								ON dtr.id = gti.task_item_id
								WHERE dtr.local_data_id = ?
								AND gti.task_item_id IS NULL',
								array($item['local_data_id'])),
							'data_source_name', 'data_source_name'
						);
					} else {
						$rrd_field_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
								FROM data_template_rrd AS dtr
								INNER JOIN data_input_fields AS dif
								ON dtr.data_input_field_id = dif.id
								WHERE dtr.local_data_id = ?',
								array($item['local_data_id'])),
							'data_name', 'data_source_name'
						);

						$unused_data_source_names = array();
					}

					$rrd_tmpl = '';
				}

				$first_tmpl = true;
				$multi_ok   = false;

				if (cacti_sizeof($values)) {
					foreach($values as $value) {
						$matches = explode(':', $value);

						if (isset($rrd_field_names[$matches[0]])) {
							$field = $rrd_field_names[$matches[0]];

							if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
								continue;
							}

							$multi_ok = true;

							if (!$multi_vals_set) {
								if (!$first_tmpl) {
									$rrd_tmpl .= ':';
								}

								$rrd_tmpl .= $rrd_field_names[$matches[0]];
								$first_tmpl = false;
							}

							if (is_numeric($matches[1]) || ($matches[1] == 'U')) {
								$output  = ':' . $matches[1];
								$outbuf .= $output;
								$outlen += strlen($output);
							} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($matches[1]))) {
								$output  = ':' . hexdec($matches[1]);
								$outbuf .= $output;
								$outlen += strlen($output);
							} else {
								$output  = ':U';
								$outbuf .= $output;
								$outlen += strlen($output);
							}

							$vals_in_buffer++;
						}
					}
				}

				/* we only want to process the template and gather the fields once */
				$multi_vals_set = true;

				if ($multi_ok) {
					$vals_in_buffer++;
				}
			} else {
				if (!$multi_vals_set) {
					if ($item['data_template_id'] > 0) {
						$rrd_field_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
								FROM graph_templates_item AS gti
								INNER JOIN data_template_rrd AS dtr
								ON gti.task_item_id = dtr.id
								INNER JOIN data_input_fields AS dif
								ON dtr.data_input_field_id = dif.id
								WHERE dtr.local_data_id = ?',
								array($item['local_data_id'])),
							'data_name', 'data_source_name'
						);

						$unused_data_source_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
								FROM data_template_rrd AS dtr
								LEFT JOIN graph_templates_item AS gti
								ON dtr.id = gti.task_item_id
								WHERE dtr.local_data_id = ?
								AND gti.task_item_id IS NULL',
								array($item['local_data_id'])),
							'data_source_name', 'data_source_name'
						);
					} else {
						$rrd_field_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
								FROM data_template_rrd AS dtr
								INNER JOIN data_input_fields AS dif
								ON dtr.data_input_field_id = dif.id
								WHERE dtr.local_data_id = ?',
								array($item['local_data_id'])),
							'data_name', 'data_source_name'
						);

						$unused_data_source_names = array();
					}

					$rrd_tmpl = '';
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

				cacti_log(sprintf('WARNING: Invalid output! MULTI DS[%d] Encountered [%s] Expected [%s]', $item['local_data_id'], $value, $expected), false, 'POLLER');

				$vals_in_buffer++;
				$multi_vals_set = true;
			}
		}

		/* process the last rrdupdate if applicable */
		if ($vals_in_buffer) {
			boost_timer('rrdupdate', BOOST_TIMER_START);
			$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $outbuf, $rrdtool_pipe);
			boost_timer('rrdupdate', BOOST_TIMER_END);

			/* check return status for delete operation */
			if (strpos(trim($return_value), 'OK') === false && $return_value != '') {
				cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", false, 'BOOST');
			}
		}

		boost_timer('results_cycle', BOOST_TIMER_END);

		if ($rrdp_auto_close) {
			rrd_close($rrdtool_pipe);
		}
	}

	/* restore original error handler */
	restore_error_handler();

	return cacti_sizeof($results);
}

function boost_rrdtool_get_last_update_time($rrd_path, &$rrdtool_pipe) {
	$return_value = 0;

	/* check if the rrd_path is empty
	 * It can become empty if someone has removed
	 * a Data Source while boost is running, or a Re-Index
	 * found the Data Source invalid, so it was removed
	 * from the poller_item table
	 */
	if ($rrd_path == '') {
		return time();
	}

	if (read_config_option('storage_location')) {
		$file_exists = rrdtool_execute("file_exists $rrd_path" , true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST');
	} else {
		$file_exists = file_exists($rrd_path);
	}

	if ($file_exists == true) {
		$return_value = rrdtool_execute("last $rrd_path", true, RRDTOOL_OUTPUT_STDOUT, false, 'BOOST');
	}

	return trim($return_value);
}

function boost_determine_caching_state() {
	set_default_action();

	/* turn off image caching if viewing thold vrules */
	if (isset($_SESSION['sess_config_array']['thold_draw_vrules']) && $_SESSION['sess_config_array']['thold_draw_vrules'] == 'on') {
		return false;
	}

	$action = get_request_var('action');

	/* turn off image caching for the following actions */
	if ($action == 'properties' ||
		$action == 'zoom'       ||
		$action == 'edit'       ||
		$action == 'graph_edit') {
		$cache = false;
	} else {
		$cache = true;
	}

	if (!isset($_SESSION['custom'])) {
		$custom = false;
	} else {
		$custom = $_SESSION['custom'];
	}

	if ($cache && !$custom) {
		return true;
	} else {
		return false;
	}
}

/* boost_get_rrd_filename_and_template - pulls
   1) the rrd_update template from the database in form of
      update decisions for multi-output RRDs
   2) rrd filename
   @arg $local_data_id - the data source to obtain information from */
function boost_get_rrd_filename_and_template($local_data_id) {
	$rrd_path     = '';
	$all_nulls    = true;
	$ds_null      = array();
	$ds_nnull     = array();

	$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
		FROM data_local
		WHERE id = ?',
		array($local_data_id));

	if ($data_template_id > 0) {
		$ds_names = db_fetch_assoc_prepared("SELECT DISTINCT data_source_name, rrd_name, rrd_path
			FROM data_template_rrd AS dtr
			INNER JOIN graph_templates_item AS gti
			ON gti.task_item_id = dtr.id
			INNER JOIN poller_item AS pi
			ON pi.local_data_id = dtr.local_data_id
			AND (pi.rrd_name = dtr.data_source_name OR pi.rrd_name = '')
			WHERE dtr.local_data_id = ?
			ORDER BY data_source_name ASC",
			array($local_data_id));
	} else {
		$ds_names = db_fetch_assoc_prepared("SELECT DISTINCT data_source_name, rrd_name, rrd_path
			FROM data_template_rrd AS dtr
			INNER JOIN poller_item AS pi
			ON pi.local_data_id = dtr.local_data_id
			AND (pi.rrd_name = dtr.data_source_name OR pi.rrd_name = '')
			WHERE dtr.local_data_id = ?
			ORDER BY data_source_name ASC",
			array($local_data_id));
	}

	if (cacti_sizeof($ds_names)) {
		foreach($ds_names as $ds_name) {
			if ($rrd_path == '') {
				$rrd_path = $ds_name['rrd_path'];
			}

			if ($ds_name['rrd_name'] == '') {
				$ds_null[] = $ds_name['data_source_name'];
			} elseif ($ds_name['rrd_name'] == $ds_name['data_source_name']) {
				$ds_nnull[] = $ds_name['data_source_name'];
				$all_nulls = false;
			}
		}
	}

	if ($all_nulls) {
		$rrd_template = implode(':', $ds_null);
	} else {
		$rrd_template = implode(':', $ds_nnull);
	}

	return array('rrd_path' => $rrd_path, 'rrd_template' => trim($rrd_template));
}

function boost_rrdtool_function_create($local_data_id, $show_source, &$rrdtool_pipe) {
	global $config;

	/**
	 * @var array $data_source_types
	 * @var array $consolidation_functions
	 */
	include($config['include_path'] . '/global_arrays.php');

	$data_source_path = get_data_source_path($local_data_id, true);

	/* ok, if that passes lets check to make sure an rra does not already
	exist, the last thing we want to do is overwrite data! */
	if ($show_source != true) {
		if (read_config_option('storage_location')) {
			$file_exists = rrdtool_execute("file_exists $data_source_path" , true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'POLLER');
		} else {
			$file_exists = file_exists($data_source_path);
		}

		if ($file_exists == true) {
			return -1;
		}
	}

	/**
	 * the first thing we must do is make sure there is at least one
	 * rra associated with this data source... *
	 *
	 * UPDATE: As of version 0.6.6, we are splitting this up into two
	 * SQL strings because of the multiple DS per RRD support. This is
	 * not a big deal however since this function gets called once per
	 * data source
	 */
	$rras = db_fetch_assoc_prepared('SELECT
		dtd.rrd_step, dsp.x_files_factor, dspr.steps, dspr.rows,
		dspc.consolidation_function_id,
		(dspr.rows * dspr.steps) AS rra_order
		FROM data_template_data AS dtd
		LEFT JOIN data_source_profiles AS dsp
		ON dtd.data_source_profile_id=dsp.id
		LEFT JOIN data_source_profiles_rra AS dspr
		ON dtd.data_source_profile_id=dspr.data_source_profile_id
		LEFT JOIN data_source_profiles_cf AS dspc
		ON dtd.data_source_profile_id=dspc.data_source_profile_id
		WHERE dtd.local_data_id = ?
		AND (dspr.steps IS NOT NULL OR dspr.rows IS NOT NULL)
		ORDER BY dspc.consolidation_function_id, rra_order', array($local_data_id));

	/* if we find that this DS has no RRA associated; get out.  This would
	 * indicate that a data sources has been deleted
	 */
	if (cacti_sizeof($rras) <= 0) {
		return false;
	}

	/* create the "--step" line */
	$create_ds = RRD_NL . '--start 0 --step '. $rras[0]['rrd_step'] . ' ' . RRD_NL;

	/**
	 * We have to check for Non-Templated Data Source first as they may not include
	 * a graph.  So, for that case, we need the RRDfile to include all data sources
     */
	$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
		FROM data_local
		WHERE id = ?',
		array($local_data_id));

	if ($data_template_id > 0) {
		$data_sources = db_fetch_assoc_prepared('SELECT DISTINCT dtr.id, dtr.data_source_name, dtr.rrd_heartbeat,
			dtr.rrd_minimum, dtr.rrd_maximum, dtr.data_source_type_id
			FROM data_template_rrd AS dtr
			INNER JOIN graph_templates_item AS gti
			ON dtr.id = gti.task_item_id
			WHERE dtr.local_data_id = ?
			ORDER BY local_data_template_rrd_id',
			array($local_data_id));
	} else {
		$data_sources = db_fetch_assoc_prepared('SELECT DISTINCT dtr.id, dtr.data_source_name, dtr.rrd_heartbeat,
			dtr.rrd_minimum, dtr.rrd_maximum, dtr.data_source_type_id
			FROM data_template_rrd AS dtr
			WHERE dtr.local_data_id = ?
			ORDER BY local_data_template_rrd_id',
			array($local_data_id));
	}

	/**
	 * ONLY make a new DS entry if:
	 *
	 * - There are multiple data sources and this item is not the main one.
	 * - There are only one data source (then use it)
	 */
	if (cacti_sizeof($data_sources)) {
		foreach ($data_sources as $data_source) {
			/* use the cacti ds name by default or the user defined one, if entered */
			$data_source_name = get_data_source_item_name($data_source['id']);

			if (empty($data_source['rrd_maximum'])) {
				/* in case no maximum is given, use "Undef" value */
				$data_source['rrd_maximum'] = 'U';
			} elseif (strpos($data_source['rrd_maximum'], '|query_') !== false) {
				$data_local = db_fetch_row_prepared('SELECT * FROM data_local WHERE id = ?', array($local_data_id));

				$speed = rrdtool_function_interface_speed($data_local);

				if ($data_source['rrd_maximum'] == '|query_ifSpeed|' || $data_source['rrd_maximum'] == '|query_ifHighSpeed|') {
					$data_source['rrd_maximum'] = $speed;
				} else {
					$data_source['rrd_maximum'] = substitute_snmp_query_data($data_source['rrd_maximum'],$data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
				}
			} elseif (($data_source['rrd_maximum'] != 'U') && (int)$data_source['rrd_maximum']<=(int)$data_source['rrd_minimum']) {
				/* max > min required, but take care of an "Undef" value */
				$data_source['rrd_maximum'] = (int)$data_source['rrd_minimum']+1;
			}

			/* min==max==0 won't work with rrdtool */
			if ($data_source['rrd_minimum'] == 0 && $data_source['rrd_maximum'] == 0) {
				$data_source['rrd_maximum'] = 'U';
			}

			$create_ds .= "DS:$data_source_name:" . $data_source_types[$data_source['data_source_type_id']] . ':' . $data_source['rrd_heartbeat'] . ':' . $data_source['rrd_minimum'] . ':' . $data_source['rrd_maximum'] . RRD_NL;
		}
	}

	$create_rra = '';
	/* loop through each available RRA for this DS */
	foreach ($rras as $rra) {
		$create_rra .= 'RRA:' . $consolidation_functions[$rra['consolidation_function_id']] . ':' . $rra['x_files_factor'] . ':' . $rra['steps'] . ':' . $rra['rows'] . RRD_NL;
	}

	if ($config['cacti_server_os'] != 'win32') {
		$owner_id = fileowner($config['rra_path']);
		$group_id = filegroup($config['rra_path']);
	}

	/**
	 * check for structured path configuration, if in place verify directory
	 * exists and if not create it.
	 */
	if (read_config_option('extended_paths') == 'on') {
		if (read_config_option('storage_location') > 0) {
			if (false === rrdtool_execute('is_dir ' . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST') ) {
				if (false === rrdtool_execute('mkdir ' . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST') ) {
					cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", false);
				}
			}
		} elseif (!is_dir(dirname($data_source_path))) {
			if ($config['is_web'] == false || is_writable($config['rra_path'])) {
				if (mkdir(dirname($data_source_path), 0775, true)) {
					if ($config['cacti_server_os'] != 'win32' && posix_getuid() == 0) {
						$success  = true;
						$paths    = explode('/', str_replace($config['rra_path'], '/', dirname($data_source_path)));
						$spath    = '';

						foreach($paths as $path) {
							if ($path == '') {
								continue;
							}

							$spath .= '/' . $path;

							$powner_id = fileowner($config['rra_path'] . $spath);
							$pgroup_id = fileowner($config['rra_path'] . $spath);

							if ($powner_id != $owner_id) {
								$success = chown($config['rra_path'] . $spath, $owner_id);
							}

							if ($pgroup_id != $group_id && $success) {
								$success = chgrp($config['rra_path'] . $spath, $group_id);
							}

							if (!$success) {
								cacti_log("ERROR: Unable to set directory permissions for '" . $config['rra_path'] . $spath . "'", false);
								break;
							}
						}
					}
				} else {
					cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", false);
				}
			} else {
				cacti_log("WARNING: Poller has not created structured path '" . dirname($data_source_path) . "' yet.", false);
			}
		}
	}

	if ($show_source == true) {
		return read_config_option('path_rrdtool') . ' create' . RRD_NL . "$data_source_path$create_ds$create_rra";
	} else {
		$success = rrdtool_execute("create $data_source_path $create_ds$create_rra", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'BOOST');

		if ($config['cacti_server_os'] != 'win32' && posix_getuid() == 0) {
			shell_exec("chown $owner_id:$group_id $data_source_path");
		}

		return $success;
	}
}

/* boost_rrdtool_function_update - a re-write of the Cacti rrdtool update command
   specifically designed for bulk updates.
   @arg $local_data_id - the data source to obtain information from
   @arg $rrd_path      - the path to the RRD file
   @arg $rrd_update_template  - the order in which values need to be added
   @arg $rrd_update_values    - values to include in the database */
function boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_update_template, &$rrd_update_values, &$rrdtool_pipe) {
	global $debug;

	/* lets count the number of rrd files processed */
	$rrds_processed = 0;

	/* let's check for deleted Data Sources */
	$valid_entry = true;

	/* check for an empty rrd_path
	 * this can happen when you've removed a data source
	 * while boost is running
	 */
	if ($rrd_path == '') {
		return 'OK';
	}

	/* create the rrd if one does not already exist */
	if (read_config_option('storage_location')) {
		$file_exists = rrdtool_execute("file_exists $rrd_path" , true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST');
	} else {
		$file_exists = file_exists($rrd_path);
	}

	if ($file_exists == false) {
		$ds_exists = db_fetch_cell_prepared('SELECT id FROM data_local WHERE id = ?', array($local_data_id));

		// Check for a Data Source that has been removed
		if ($ds_exists) {
			$valid_entry = boost_rrdtool_function_create($local_data_id, false, $rrdtool_pipe);
		} else {
			return 'OK';
		}
	}

	if (cacti_version_compare(get_rrdtool_version(), '1.5', '>=')) {
		$update_options = '--skip-past-updates';
	} else {
		$update_options = '';
	}

	if ($valid_entry) {
		if ($rrd_update_template != '') {
			cacti_log("update $rrd_path $update_options --template $rrd_update_template $rrd_update_values", true, 'BOOST', ($debug ? POLLER_VERBOSITY_NONE:POLLER_VERBOSITY_HIGH));

			rrdtool_execute("update $rrd_path $update_options --template $rrd_update_template $rrd_update_values", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'BOOST');
		} else {
			cacti_log("update $rrd_path $update_options $rrd_update_values", true, 'BOOST', ($debug ? POLLER_VERBOSITY_NONE:POLLER_VERBOSITY_HIGH));

			rrdtool_execute("update $rrd_path $update_options $rrd_update_values", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'BOOST');
		}

		return 'OK';
	}
}

function boost_memory_limit() {
	$memory_limit = read_config_option('boost_poller_mem_limit');

	if ($memory_limit != '-1') {
		ini_set('memory_limit', $memory_limit . 'M');
	} else {
		ini_set('memory_limit', -1);
	}
}

function boost_poller_bottom() {
	global $config;

	if (read_config_option('boost_rrd_update_enable') == 'on') {
		include_once($config['library_path'] . '/poller.php');

		chdir($config['base_path']);

		$redirect_args = '';

		boost_update_snmp_statistics();

		$boost_log     = read_config_option('path_boost_log');
		$boost_logdir  = dirname($boost_log);

		if ($boost_log != '') {
			if (!is_writable($boost_log) || !is_dir($boost_logdir) || !is_writable($boost_logdir)) {
				boost_debug("WARNING: Boost log '$boost_log' does not exist or is not writable!");

				cacti_log("WARNING: Boost log '$boost_log' is not writable!", false, 'BOOST');

				$boost_log = '';
			}
		}

		$command_string = read_config_option('path_php_binary');
		if ($boost_log != '') {
			if ($config['cacti_server_os'] == 'unix') {
				$extra_args    = '-q '  . $config['base_path'] . '/poller_boost.php --debug';
				$redirect_args =  '>> ' . $boost_log . ' 2>&1';
			} else {
				$extra_args    = '-q ' . $config['base_path'] . '/poller_boost.php --debug';
				$redirect_args = '>> ' . $boost_log;
			}
		} else {
			$extra_args = '-q ' . $config['base_path'] . '/poller_boost.php';
		}

		exec_background($command_string, $extra_args, $redirect_args);
	}
}

function boost_update_snmp_statistics() {
	global $config;
	$mc = new MibCache('CACTI-BOOST-MIB');

	/* get the boost table status */
	$boost_table_status = db_fetch_assoc("SELECT *
		FROM information_schema.tables
		WHERE table_schema = SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%'
		OR table_name LIKE 'poller_output_boost')");

	$total_data_sources = db_fetch_cell('SELECT COUNT(*) FROM poller_item');

	$pending_records = 0;
	$arch_records = 0;
	$data_length = 0;
	$engine = '';
	$max_data_length = 0;

	if (cacti_sizeof($boost_table_status)) {
		foreach($boost_table_status as $table) {
			if ($table['TABLE_NAME'] == 'poller_output_boost') {
				$pending_records += $table['TABLE_ROWS'];
			} else {
				$arch_records += $table['TABLE_ROWS'];
			}
			$data_length += $table['DATA_LENGTH'];
			$data_length -= $table['DATA_FREE'];
			$engine = $table['ENGINE'];
			$max_data_length = $table['MAX_DATA_LENGTH'];
		}
	}

	$total_records = $pending_records + $arch_records;
	$avg_row_length = ($total_records ? intval($data_length / $total_records) : 0);

	if (strcmp($engine, 'MEMORY') == 0) {
		$max_length = db_fetch_cell('SELECT MAX(LENGTH(output)) FROM poller_output_boost');
		$max_table_allowed = $max_data_length;
		$max_table_records = ($avg_row_length ? round($max_data_length/$avg_row_length, 0) : 0);
	} else {
		$max_length = '0';
		$max_table_allowed = '-1';
		$max_table_records = '0';
	}

	$mc->object('boostApplStorageDatabaseEngine')->set( $engine );
	$mc->object('boostApplStorageMaxTableSize')->set( $max_table_allowed );
	$mc->object('boostApplStorageMaxRecords')->set( $max_table_records );
	$mc->object('boostApplLastUpdate')->set( time() );

	$mc->object('boostStatsTotalsRecords')->set( $total_records );
	$mc->object('boostStatsTotalsRecordsPending')->set( $pending_records );
	$mc->object('boostStatsTotalsRecordsArchived')->set( $arch_records );
	$mc->object('boostStatsStorageTableSize')->set( $data_length );
	$mc->object('boostStatsStorageAverageRecordSize')->set( $avg_row_length );
	$mc->object('boostStatsStorageMaxRecordLength')->set( $max_length );
	$mc->object('boostStatsTotalsDataSources')->set( $total_data_sources );

	$mc->object('boostStatsLastUpdate')->set( time() );
}

function boost_debug($string) {
	global $debug, $child;

	$string = 'DEBUG: ' . trim($string, " \n");

	if ($debug) {
		print $string . PHP_EOL;

		if ($child) {
			cacti_log($string, false, 'BOOST CHILD');
		}
	}
}

