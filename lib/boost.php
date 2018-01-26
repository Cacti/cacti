<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

function boost_file_size_display($file_size, $digits = 2) {
	if ($file_size > 1024) {
		$file_size = $file_size / 1024;

		if ($file_size > 1024) {
			$file_size = $file_size / 1024;

			if ($file_size > 1024) {
				$file_size = $file_size / 1024;
				$file_suffix = ' GBytes';
			} else {
				$file_suffix = ' MBytes';
			}
		} else {
			$file_suffix = ' KBytes';
		}
	} else {
		$file_suffix = ' Bytes';
	}

	$file_size = number_format_i18n($file_size, $digits) . $file_suffix;

	return $file_size;
}

function boost_get_total_rows() {
	return db_fetch_cell("SELECT SUM(TABLE_ROWS) FROM INFORMATION_SCHEMA.TABLES
		WHERE table_schema=SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')
		GROUP BY table_schema");
}

function boost_error_handler($errno, $errmsg, $filename, $linenum, $vars) {
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
	global $config;

	if (read_config_option('boost_rrd_update_enable') == 'on') {
		/* suppress warnings */
		if (defined('E_DEPRECATED')) {
			error_reporting(E_ALL ^ E_DEPRECATED);
		} else {
			error_reporting(E_ALL);
		}

		/* install the boost error handler */
		set_error_handler('boost_error_handler');

		$outbuf      = '';
		$sql_prefix  = 'INSERT INTO poller_output_boost (local_data_id, rrd_name, time, output) VALUES ';
		$sql_suffix  = ' ON DUPLICATE KEY UPDATE output=VALUES(output)';
		$overhead    = strlen($sql_prefix) + strlen($sql_suffix);

		if (boost_check_correct_enabled()) {
			/* if boost redirect is on, rows are being inserted directly */
			if (read_config_option('boost_redirect') == 'on') {
				restore_error_handler();
				return false;
			}

			$max_allowed_packet = db_fetch_row("SHOW VARIABLES LIKE 'max_allowed_packet'");
			$max_allowed_packet = $max_allowed_packet['Value'];

			if (sizeof($results)) {
				$i          = 1;
				$out_length = 0;

				foreach($results as $result) {
					if ($i == 1) {
						$delim = '';
					} else {
						$delim = ',';
					}

					$outbuf2 =
						$delim . "('" .
						$result['local_data_id'] . "','" .
						$result['rrd_name'] . "','" .
						$result['time'] . "','" .
						$result['output'] .	"')";

					$out_length += strlen($outbuf2);

					if (($out_length + $overhead) > $max_allowed_packet) {
						db_execute($sql_prefix . $outbuf . $sql_suffix);
						$outbuf     = $outbuf2;
						$out_length = strlen($outbuf2);
						$i          = 1;
					} else {
						$outbuf .= $outbuf2;
						$i++;
					}
				}

				if ($outbuf != '') {
					db_execute($sql_prefix . $outbuf . $sql_suffix);
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

function boost_fetch_cache_check($local_data_id) {
	global $config, $rrdtool_pipe;

	if (read_config_option('boost_rrd_update_enable') == 'on') {
		/* include poller processing routinges */
		include_once($config['library_path'] . '/poller.php');

		/* check to see if boost can do it's job */
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
		$rrdtool_pipe = rrd_init();

		/* get the information to populate into the rrd files */
		if (boost_check_correct_enabled()) {
			boost_process_poller_output($local_data_id);
		}

		/* restore original error handler */
		restore_error_handler();

		/* close rrdtool */
		rrd_close($rrdtool_pipe);
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

	/* include poller processing routinges */
	include_once($config['library_path'] . '/poller.php');

	/* suppressnwarnings */
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler('boost_error_handler');

	/* check to see if boost can do it's job */
	if (!boost_poller_id_check()) {
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
		if (sizeof($local_data_ids)) {
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
		 * exist, it is the old fasioned MRTG type graph
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
	 * cacti's graphing fucntions.
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

/* boost_get_arch_table_name - returns current archive boost table or false if no arch table is present currently */
function boost_get_arch_table_name() {
	global $database_default;

	$tables = db_fetch_assoc("SELECT table_name AS name
		FROM information_schema.tables
		WHERE table_schema = '$database_default'
		AND table_name LIKE 'poller_output_boost_arch_%'");

	foreach($tables as $table) {
		$rows = db_fetch_cell('SELECT count(*) FROM '.$table['name']);
		if (is_numeric($rows) && intval($rows) > 0) {
			return $table['name'];
		}
	}

	return false;
}

/* boost_process_poller_output - grabs data from the 'poller_output' table and feeds the *completed*
     results to RRDtool for processing
   @arg $local_data_id - if you are using boost, you need to update only an rra id at a time */
function boost_process_poller_output($local_data_id = '', $rrdtool_pipe = '') {
	global $config, $database_default, $boost_sock, $boost_timeout, $debug, $get_memory, $memory_used;

	static $warning_issued;

	include_once($config['library_path'] . '/rrd.php');

	/* suppress warnings */
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler('boost_error_handler');

	/* tiny SQL where addendum for boost */
	if ($local_data_id != '') {
		/* we can simplify the delete process if only one local_data_id */
		$single_local_data_id = true;
		$orig_local_data_id   = $local_data_id;

		/* aquire lock in order to prevent race conditions */
		while (!db_fetch_cell("SELECT GET_LOCK('boost.single_ds.$orig_local_data_id', 1)")) {
			usleep(50000);
		}
	} else {
		$single_local_data_id = false;

		$archive_table = boost_get_arch_table_name();
		if ($archive_table === false) {
			if ($warning_issued != true) {
				cacti_log('NOTE: Failed to determine archive table', false, 'BOOST', POLLER_VERBOSITY_MEDIUM);
			}

			$warning_issued = true;

			return 0;
		}
	}

	$data_ids_to_get = read_config_option('boost_rrd_update_max_records_per_select');

	/* get the records */
	if ($single_local_data_id) {
		$query_string = '';
		$arch_tables  = db_fetch_assoc("SELECT table_name AS name
			FROM information_schema.tables
			WHERE table_schema = '$database_default'
			AND table_name LIKE 'poller_output_boost_arch_%'");

		if (count($arch_tables)) {
			foreach($arch_tables as $table) {
				$rows = db_fetch_cell('SELECT COUNT(*) FROM ' . $table['name']);
				if (db_table_exists($table['name']) && is_numeric($rows) && intval($rows) > 0) {
					if ($query_string != '') {
						$query_string .= ' UNION ';
					}

					$query_string .= ' (SELECT local_data_id, UNIX_TIMESTAMP(time) AS timestamp, rrd_name, output
						FROM ' . $table['name'] . "
						WHERE local_data_id = $local_data_id) ";
				}
			}
		}

		if ($query_string != '') {
			$query_string .= ' UNION ';
		}

		$timestamp = time();

		$query_string .= " (SELECT local_data_id, UNIX_TIMESTAMP(time) AS timestamp, rrd_name, output
			FROM poller_output_boost
			WHERE local_data_id = $orig_local_data_id
			AND time < FROM_UNIXTIME($timestamp))";

		$query_string .= ' ORDER BY timestamp ASC, rrd_name ASC ';
	} else {
		$query_string = '';
		$arch_tables  = db_fetch_assoc("SELECT table_name AS name
			FROM information_schema.tables
			WHERE table_schema = '$database_default'
			AND table_name LIKE 'poller_output_boost_arch_%'");

		if (count($arch_tables)) {
			foreach($arch_tables as $table) {
				$rows = db_fetch_cell('SELECT COUNT(*) FROM ' . $table['name']);
				if (db_table_exists($table['name']) && is_numeric($rows) && intval($rows) > 0) {
					if ($query_string != '') {
						$query_string .= ' UNION ';
					}

					$query_string .= ' (SELECT local_data_id, UNIX_TIMESTAMP(time) AS timestamp, rrd_name, output
						FROM ' . $table['name'] . ') ';
				}
			}
		}

		$query_string .= "ORDER BY local_data_id ASC, time ASC, rrd_name ASC
			LIMIT $data_ids_to_get ";
	}

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

	if ($single_local_data_id) {
		$log_verbosity = POLLER_VERBOSITY_DEBUG;

		if ($debug) {
			$log_verbosity = POLLER_VERBOSITY_LOW;
		}

		cacti_log("NOTE: Updating Local Data ID:'$local_data_id', Total of '" . sizeof($results) . "' Updates to Process", false, 'BOOST', $log_verbosity);
	}

	if (sizeof($results)) {
		$rrdp_auto_close = false;

		if (!$rrdtool_pipe) {
			$rrdtool_pipe    = rrd_init();
			$rrdp_auto_close = true;
		}

		/* create an array keyed off of each .rrd file */
		$local_data_id = -1;
		$time          = -1;
		$outbuf        = '';
		$last_update   = -1;
		$last_item     = array(
			'local_data_id' => -1,
			'timestamp'     => -1,
			'rrd_name'      => ''
		);

		/* we are going to blow away all record if ok */
		$vals_in_buffer = 0;

		/* cut last DS (likely to be incomplete due to LIMIT)*/
		if (!$single_local_data_id) {
			reset($results);

			$first_ds = $results[key($results)];
			$first_ds = intval($first_ds['local_data_id']);

			end($results);

			$last_ds = $results[key($results)];
			$last_ds = intval($last_ds['local_data_id']);

			reset($results);

			if ($first_ds == $last_ds) {
				if (sizeof($results) == $data_ids_to_get) {
					cacti_log("FALURE: Current LIMIT ($data_ids_to_get) is too low to run multiple DS RRD writes, consider raising it", false, 'BOOST');
				}

				restore_error_handler();

				return boost_process_poller_output($first_ds, $rrdtool_pipe);
			}
		}

		$upd_string_len = read_config_option('boost_rrd_update_string_length');

		/* initialize some variables */
		$rrd_tmpl     = '';
		$rrd_path     = '';
		$initial_time = '';
		$outlen       = 0;

		boost_timer('results_cycle', BOOST_TIMER_START);

		/* go through each poller_output_boost entries and process */
		foreach ($results as $item) {
			$item['timestamp'] = trim($item['timestamp']);

			if ($single_local_data_id && /* duplicate entry, possible when fetching data while rotating poller_output_boost */
				$last_item['local_data_id'] == $item['local_data_id'] &&
				$last_item['timestamp'] == $item['timestamp'] &&
				strcmp($last_item['rrd_name'], $item['rrd_name']) == 0) {

				continue;
			}

			if (!$single_local_data_id &&
				$first_ds != $last_ds &&
				$last_ds == $item['local_data_id']) {
				/* we faced last and possibly incomplete DS, bail out */
				break;
			}

			/* if the local_data_id changes, we need to flush the buffer */
			if ($local_data_id != $item['local_data_id']) {
				/* update the rrd for the previous local_data_id */
				if ($vals_in_buffer) {
					cacti_log("NOTE: Updating Local Data Id:'$local_data_id', Template:" . $rrd_tmpl . ', Output:' . $outbuf, false, 'BOOST', POLLER_VERBOSITY_MEDIUM);

					boost_timer('rrdupdate', BOOST_TIMER_START);
					$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $initial_time, $outbuf, $rrdtool_pipe);
					boost_timer('rrdupdate', BOOST_TIMER_END);

					$vals_in_buffer = 0;

					/* check return status for delete operation */
					if (strpos(trim($return_value), 'OK') === false) {
						cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", false, 'BOOST');
					}
				}

				/* reset the rrd file path and templates, assume non multi output */
				boost_timer('rrd_filename_and_template', BOOST_TIMER_START);
				$rrd_data    = boost_get_rrd_filename_and_template($item['local_data_id']);
				$rrd_tmpl    = $rrd_data['rrd_template'];
				$rrd_path    = $rrd_data['rrd_path'];
				boost_timer('rrd_filename_and_template', BOOST_TIMER_END);

				boost_timer('rrd_lastupdate', BOOST_TIMER_START);
				$last_update = boost_rrdtool_get_last_update_time($rrd_path, $rrdtool_pipe);
				boost_timer('rrd_lastupdate', BOOST_TIMER_END);

				$local_data_id  = $item['local_data_id'];
				$time           = $item['timestamp'];
				$initial_time   = $time;
				$output         = ' ' . $time;
				$outbuf         = $output;
				$outlen         = strlen($output);
				$multi_vals_set = false;
			}

			/* don't generate error messages if the RRD has already been updated */
			if ($time <= $last_update){
				cacti_log("WARNING: Stale Poller Data Found! Item Time:'" . $time . "', RRD Time:'" . $last_update . "' Ignoring Value!", false, 'BOOST');
				$value = 'DNP';
			} else {
				$value = trim($item['output']);
			}

			if ($time != $item['timestamp']) {
				if ($outlen > $upd_string_len) {
					cacti_log("NOTE: Updating Local Data Id:'$local_data_id', Template:" . $rrd_tmpl . ', Output:' . $outbuf, false, 'BOOST', POLLER_VERBOSITY_MEDIUM);

					boost_timer('rrdupdate', BOOST_TIMER_START);
					$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $initial_time, $outbuf, $rrdtool_pipe);
					boost_timer('rrdupdate', BOOST_TIMER_END);

					$outbuf         = '';
					$outlen         = 0;
					$vals_in_buffer = 0;

					/* check return status for delete operation */
					if (strpos(trim($return_value), 'OK') === false) {
						cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", false, 'BOOST');
					}
				}

				$output  = ' ' . $item['timestamp'];
				$outbuf .= $output;
				$outlen += strlen($output);
				$time    = $item['timestamp'];
			}

			/* single one value output */
			if (strcmp($value, 'DNP') == 0) {
				/* continue, bad time */
			} elseif ((is_numeric($value)) || (strcmp($value, 'U') == 0)) {
				$output  = ':' . $value;
				$outbuf .= $output;
				$outlen += strlen($output);
				$vals_in_buffer++;
			} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($value))) {
				$output  = ':' . hexdec($value);
				$outbuf .= $output;
				$outlen += strlen($output);
				$vals_in_buffer++;
			} elseif ($value != '') {
				/* break out multiple value output to an array */
				$values = explode(' ', $value);

				if (!$multi_vals_set) {
					$rrd_field_names = array_rekey(db_fetch_assoc_prepared('SELECT
						data_template_rrd.data_source_name,
						data_input_fields.data_name
						FROM (data_template_rrd, data_input_fields)
						WHERE data_template_rrd.data_input_field_id = data_input_fields.id
						AND data_template_rrd.local_data_id = ?', array($item['local_data_id'])), 'data_name', 'data_source_name');

					$rrd_tmpl = '';
				}

				$first_tmpl = true;
				$multi_ok   = false;

				for ($i=0; $i<count($values); $i++) {
					if (preg_match("/^([a-zA-Z0-9_\.-]+):([eE0-9\+\.-]+)$/", $values[$i], $matches)) {
						if (isset($rrd_field_names[$matches[1]])) {
							$multi_ok = true;

							cacti_log("Parsed MULTI output field '" . $matches[0] . "' [map " . $matches[1] . '->' . $rrd_field_names[$matches[1]] . ']' , false, 'BOOST', POLLER_VERBOSITY_DEBUG);

							if (!$multi_vals_set) {
								if (!$first_tmpl) {
									$rrd_tmpl .= ':';
								}

								$rrd_tmpl .= $rrd_field_names[$matches[1]];
								$first_tmpl = false;
							}

							if (is_numeric($matches[2]) || ($matches[2] == 'U')) {
								$output  = ':' . $matches[2];
								$outbuf .= $output;
								$outlen += strlen($output);
							} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($matches[2]))) {
								$output  = ':' . hexdec($matches[2]);
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
				cacti_log('WARNING: Local Data Id [' . $item['local_data_id'] . '] Contains an empty value', false, 'BOOST');
			}
		}

		/* process the last rrdupdate if applicable */
		if ($vals_in_buffer) {
			cacti_log("NOTE: Updating Local Data Id:'$local_data_id', Template:" . $rrd_tmpl . ', Output:' . $outbuf, false, 'BOOST', POLLER_VERBOSITY_MEDIUM);

			boost_timer('rrdupdate', BOOST_TIMER_START);
			$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $initial_time, $outbuf, $rrdtool_pipe);
			boost_timer('rrdupdate', BOOST_TIMER_END);

			/* check return status for delete operation */
			if (strpos(trim($return_value), 'OK') === false) {
				cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", false, 'BOOST');
			}
		}

		boost_timer('results_cycle', BOOST_TIMER_END);

		/* remove the entries from the table */
		boost_timer('delete', BOOST_TIMER_START);
		if ($single_local_data_id) {
			if (count($arch_tables)) {
				foreach($arch_tables as $table) {
					db_execute_prepared('DELETE FROM ' . $table['name'] . '
						WHERE local_data_id = ?',
						array($local_data_id));
				}
			}

			db_execute_prepared('DELETE FROM poller_output_boost
				WHERE local_data_id = ?
				AND time < FROM_UNIXTIME(?)',
				array($local_data_id, $timestamp));
		} else {
			if (count($arch_tables)) {
				foreach($arch_tables as $table) {
					db_execute_prepared('DELETE FROM ' . $table['name'] . '
						WHERE local_data_id BETWEEN ? AND ?',
						array($first_ds, $last_ds));
				}
			}
		}

		boost_timer('delete', BOOST_TIMER_END);

		if ($rrdp_auto_close) {
			rrd_close($rrdtool_pipe);
		}
	}

	if ($single_local_data_id) {
		db_execute("SELECT RELEASE_LOCK('boost.single_ds.$orig_local_data_id')");
	}

	/* restore original error handler */
	restore_error_handler();

	return sizeof($results);
}

function boost_rrdtool_get_last_update_time($rrd_path, &$rrdtool_pipe) {
	$return_value = 0;

	if (read_config_option('storage_location')) {
		$file_exists = rrdtool_execute("file_exists $rrd_path" , true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST');
	}else {
		$file_exists = file_exists($rrd_path);
	}

	if ($file_exists == true) {
		$return_value = rrdtool_execute("last $rrd_path", true, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'BOOST');
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
      update decisions for multi-output RRD's
   2) rrd filename
   @arg $local_data_id - the data source to obtain information from */
function boost_get_rrd_filename_and_template($local_data_id) {
	$rrd_path     = '';
	$all_nulls    = true;
	$ds_null      = array();
	$ds_nnull     = array();

	$ds_names = db_fetch_assoc_prepared("SELECT data_source_name, rrd_name, rrd_path
		FROM data_template_rrd AS dtr
		INNER JOIN poller_item AS pi
		ON (pi.local_data_id = dtr.local_data_id
		AND (pi.rrd_name = dtr.data_source_name OR pi.rrd_name = ''))
		WHERE dtr.local_data_id = ?
		ORDER BY data_source_name ASC", array($local_data_id));

	if (sizeof($ds_names)) {
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

function boost_rrdtool_function_create($local_data_id, $initial_time, $show_source, &$rrdtool_pipe) {
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
		}else {
			$file_exists = file_exists($data_source_path);
		}

		if ($file_exists == true) {
			return -1;
		}
	}

	/* the first thing we must do is make sure there is at least one
	rra associated with this data source... *
	UPDATE: As of version 0.6.6, we are splitting this up into two
	SQL strings because of the multiple DS per RRD support. This is
	not a big deal however since this function gets called once per
	data source */

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
	if (sizeof($rras) <= 0) {
		return false;
	}

	/* back off the initial time to allow updates */
	$initial_time -= 300;

	/* create the "--step" line */
	$create_ds = RRD_NL . '--start ' . $initial_time . ' --step '. $rras[0]['rrd_step'] . ' ' . RRD_NL;

	/* query the data sources to be used in this .rrd file */
	$data_sources = db_fetch_assoc_prepared('SELECT
		data_template_rrd.id,
		data_template_rrd.rrd_heartbeat,
		data_template_rrd.rrd_minimum,
		data_template_rrd.rrd_maximum,
		data_template_rrd.data_source_type_id
		FROM data_template_rrd
		WHERE data_template_rrd.local_data_id = ?
		ORDER BY local_data_template_rrd_id', array($local_data_id));

	/* ONLY make a new DS entry if:
	- There is multiple data sources and this item is not the main one.
	- There is only one data source (then use it) */

	if (sizeof($data_sources)) {
		foreach ($data_sources as $data_source) {
			/* use the cacti ds name by default or the user defined one, if entered */
			$data_source_name = get_data_source_item_name($data_source['id']);

			if (empty($data_source['rrd_maximum'])) {
				/* in case no maximum is given, use "Undef" value */
				$data_source['rrd_maximum'] = 'U';
			} elseif (strpos($data_source['rrd_maximum'], '|query_') !== false) {
				$data_local = db_fetch_row_prepared('SELECT * FROM data_local WHERE id = ?', array($local_data_id));
				if ($data_source['rrd_maximum'] == '|query_ifSpeed|' || $data_source['rrd_maximum'] == '|query_ifHighSpeed|') {
					$highSpeed = db_fetch_cell_prepared("SELECT field_value
						FROM host_snmp_cache
						WHERE host_id = ?
						AND snmp_query_id = ?
						AND snmp_index = ?
						AND field_name = 'ifHighSpeed'", array($data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']));

					if (!empty($highSpeed)) {
						$data_source['rrd_maximum'] = $highSpeed * 1000000;
					} else {
						$data_source['rrd_maximum'] = substitute_snmp_query_data('|query_ifSpeed|',$data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
					}
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

	/* check for structured path configuration, if in place verify directory
	   exists and if not create it.
	 */
	if (read_config_option('extended_paths') == 'on') {
		if (read_config_option('storage_location')) {
			if (false === rrdtool_execute('is_dir ' . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST') ) {
				if (false === rrdtool_execute('mkdir ' . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST') ) {
					cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", false);
				}
			}
		} elseif (!is_dir(dirname($data_source_path))) {
			if ($config['is_web'] == false) {
				if (mkdir(dirname($data_source_path), 0775)) {
					if ($config['cacti_server_os'] != 'win32') {
						$owner_id      = fileowner($config['rra_path']);
						$group_id      = filegroup($config['rra_path']);

						if ((chown(dirname($data_source_path), $owner_id)) &&
								(chgrp(dirname($data_source_path), $group_id))) {
							/* permissions set ok */
						} else {
							cacti_log("ERROR: Unable to set directory permissions for '" . dirname($data_source_path) . "'", false);
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
		return rrdtool_execute("create $data_source_path $create_ds$create_rra", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'BOOST');
	}
}

/* boost_rrdtool_function_update - a re-write of the Cacti rrdtool update command
   specifically designed for bulk updates.
   @arg $local_data_id - the data source to obtain information from
   @arg $rrd_path      - the path to the RRD file
   @arg $rrd_update_template  - the order in which values need to be added
   @arg $rrd_update_values    - values to include in the database */
function boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_update_template, $initial_time, &$rrd_update_values, &$rrdtool_pipe) {

	/* lets count the number of rrd files processed */
	$rrds_processed = 0;

	/* let's check for deleted Data Sources */
	$valid_entry = true;

	/* create the rrd if one does not already exist */
	if (read_config_option('storage_location')) {
		$file_exists = rrdtool_execute("file_exists $rrd_path" , true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST');
	} else {
		$file_exists = file_exists($rrd_path);
	}

	if ($file_exists == false) {
		$valid_entry = boost_rrdtool_function_create($local_data_id, $initial_time, false, $rrdtool_pipe);
	}

	if ($valid_entry) {
		rrdtool_execute("update $rrd_path --template $rrd_update_template $rrd_update_values", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'BOOST');
		return 'OK';
	}
}

function boost_poller_command_args ($args) {
	boost_memory_limit();

	return $args;
}

function boost_memory_limit() {
	ini_set('memory_limit', read_config_option('boost_poller_mem_limit') . 'M');
}

function boost_poller_bottom () {
	global $config;

	if (read_config_option('boost_rrd_update_enable') == 'on') {
		include_once($config['library_path'] . '/poller.php');

		chdir($config['base_path']);

		boost_update_snmp_statistics();

		$command_string = read_config_option('path_php_binary');
		if (read_config_option('path_boost_log') != '') {
			if ($config['cacti_server_os'] == 'unix') {
				$extra_args = '-q ' . $config['base_path'] . '/poller_boost.php >> ' . read_config_option('path_boost_log') . ' 2>&1';
			} else {
				$extra_args = '-q ' . $config['base_path'] . '/poller_boost.php >> ' . read_config_option('path_boost_log');
			}
		} else {
			$extra_args = '-q ' . $config['base_path'] . '/poller_boost.php';
		}

		exec_background($command_string, $extra_args);
	}
}

function boost_update_snmp_statistics () {
	global $config;
	$mc = new MibCache('CACTI-BOOST-MIB');

	/* get the boost table status */
	$boost_table_status = db_fetch_assoc("SELECT *
		FROM INFORMATION_SCHEMA.TABLES
		WHERE table_schema=SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')");

	$total_data_sources = db_fetch_cell('SELECT COUNT(*) FROM poller_item');

	$pending_records = 0;
	$arch_records = 0;
	$data_length = 0;
	$engine = '';
	$max_data_length = 0;

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
