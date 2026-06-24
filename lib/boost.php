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

/**
 * Sorts a multi-dimensional array by one or more fields.
 *
 * This function takes a multi-dimensional array and sorts it based on the fields
 * specified in the arguments. It uses `array_multisort` internally to perform the sorting.
 *
 * @return array The sorted array.
 *
 */
function boost_array_orderby() : array {
	$args = func_get_args();
	$data = array_shift($args);

	foreach ($args as $n => $field) {
		if (is_string($field)) {
			$tmp = [];

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

/**
 * Converts a file size in bytes to a human-readable format.
 *
 * This function takes a file size in bytes and converts it to a more
 * readable format, such as Bytes, KBytes, MBytes, or GBytes, depending
 * on the size. The output is localized using the `__` and `number_format_i18n`
 * functions for internationalization support.
 *
 * @param float|int $file_size The file size in bytes.
 * @param int       $digits    The number of decimal places to include in the formatted output.
 *
 * @return string A human-readable string representing the file size in the appropriate unit.
 */
function boost_file_size_display(float|int $file_size, int $digits = 2) : string {
	if ($file_size > 1024) {
		$file_size /= 1024;

		if ($file_size > 1024) {
			$file_size /= 1024;

			if ($file_size > 1024) {
				$file_size /= 1024;

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

/**
 * Retrieves the total number of rows from the database tables
 * that match specific naming patterns.
 *
 * This function calculates the sum of rows from tables in the
 * current database schema where the table names match either
 * 'poller_output_boost_arch_%' or 'poller_output_boost'.
 *
 * @return int The total number of rows from the matching tables.
 */
function boost_get_total_rows() : int {
	return (int) db_fetch_cell("SELECT SUM(TABLE_ROWS)
		FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = SCHEMA()
		AND (TABLE_NAME LIKE 'poller_output_boost_arch_%'
		OR TABLE_NAME = 'poller_output_boost')");
}

/**
 * Custom error handler for the application.
 *
 * This function handles errors based on the application's logging verbosity
 * level. It logs detailed error information to the Cacti log if the verbosity
 * level is set to debug. Certain non-critical errors are ignored to reduce
 * noise in the logs.
 *
 * @param int    $errno    The level of the error raised.
 * @param string $errmsg   The error message.
 * @param string $filename The filename where the error was raised.
 * @param int    $linenum  The line number where the error was raised.
 * @param array  $vars     An array of variables that existed in the
 *                         scope the error was triggered in.
 *
 * @return bool
 */
function boost_error_handler(int $errno, string $errmsg, string $filename, int $linenum, array $vars = []) : bool {
	if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG) {
		// define all error types
		$errortype = [
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
		];

		if (defined('E_RECOVERABLE_ERROR')) {
			$errortype[E_RECOVERABLE_ERROR] = 'Catchable Fatal Error';
		}

		if (defined('E_DEPRECATED')) {
			$errortype[E_DEPRECATED] = 'Deprecated Warning';
		}

		// create an error string for the log
		$err = "ERRNO:'" . $errno . "' TYPE:'" . $errortype[$errno] .
			"' MESSAGE:'" . $errmsg . "' IN FILE:'" . $filename .
			"' LINE NO:'" . $linenum . "'";

		// let's ignore some lesser issues
		if (substr_count($errmsg, 'date_default_timezone')) {
			return true;
		}

		if (substr_count($errmsg, 'Only variables')) {
			return true;
		}

		// log the error to the Cacti log
		cacti_log('PROGERR: ' . $err, false, 'BOOST');
	}

	return true;
}

/**
 * Checks and ensures that the Boost RRD update system is correctly enabled.
 *
 * This function verifies if either the `boost_rrd_update_enable` or
 * `boost_rrd_update_system_enable` configuration options are set to 'on'.
 * If `boost_rrd_update_enable` is enabled but `boost_rrd_update_system_enable`
 * is not, it updates the database to enable the system-level updates.
 *
 * If neither of the options is enabled, the function restores the default
 * error handler and returns false.
 *
 * @return bool Returns true if the Boost RRD update system is correctly enabled,
 *              otherwise returns false.
 */
function boost_check_correct_enabled() : bool {
	if ((read_config_option('boost_rrd_update_enable') == 'on') ||
		(read_config_option('boost_rrd_update_system_enable') == 'on')) {
		// turn on the system level updates as that is what dictates "off"
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

/**
 * Handles the on-demand poller boost functionality for Cacti.
 *
 * This function processes the results of a poller run and inserts the data
 * into the `poller_output_boost` table. It ensures that the data is inserted
 * in a way that avoids exceeding the maximum allowed packet size for SQL queries.
 * Additionally, it manages error handling and configuration options related to
 * the boost functionality.
 *
 * @param array $results An array of poller results, where each result contains:
 *                       - 'local_data_id': The ID of the local data source.
 *                       - 'rrd_name': The name of the RRD file.
 *                       - 'time': The timestamp of the data.
 *                       - 'output': The output value to be stored.
 *
 * @return bool Returns `false` if the boost functionality is enabled and processed,
 *              or `true` if the boost functionality is disabled or bypassed.
 */
function boost_poller_on_demand(array &$results) : bool {
	global $remote_db_cnn_id;

	if (POLLER_ID > 1 && CACTI_CONNECTION == 'online') { // @phpstan-ignore-line
		$conn = $remote_db_cnn_id;
	} else {
		$conn = false;
	}

	if (read_config_option('boost_rrd_update_enable') == 'on' || POLLER_ID > 1) {
		set_config_option('boost_rrd_update_enable', 'on');

		// suppress warnings
		if (defined('E_DEPRECATED')) {
			error_reporting(E_ALL ^ E_DEPRECATED);
		} else {
			error_reporting(E_ALL);
		}

		// install the boost error handler
		set_error_handler('boost_error_handler');

		$out_buffer  = '';
		$sql_prefix  = 'INSERT INTO poller_output_boost (local_data_id, rrd_name, time, output) VALUES ';
		$sql_suffix  = ' ON DUPLICATE KEY UPDATE output=VALUES(output)';

		// Add 1 here for potential delimiter
		$overhead    = strlen($sql_prefix) + strlen($sql_suffix) + 1;

		if (boost_check_correct_enabled()) {
			// if boost redirect is on, rows are being inserted directly
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

				foreach ($results as $result) {
					$tmp_buffer =
						'(' .
						(int) $result['local_data_id'] . ',' .
						db_qstr($result['rrd_name'], $conn) . ',' .
						db_qstr($result['time'], $conn) . ',' .
						db_qstr($result['output'], $conn) .
						')';

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

		// restore original error handler
		restore_error_handler();

		return $return_value;
	} else {
		return true;
	}
}

/**
 * Checks the validity of the poller ID based on the storage location
 * and connection type.
 *
 * This function ensures that the poller ID is valid, particularly when
 * running from a remote poller. If the storage location is not set to
 * RRDproxy and the connection type is 'online', the function will return
 * false for remote pollers (poller ID > 1).
 *
 * @return bool Returns true if the poller ID is valid, otherwise false.
 */
function boost_poller_id_check() : bool {
	$storage_location = read_config_option('storage_location');

	/* error out if running from a remote poller and the storage
	 * location is not the RRDproxy */
	if (POLLER_ID > 1) {
		if (CACTI_CONNECTION == 'online') {
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

/**
 * Fetches and processes cache data for a given local data ID using the Boost plugin.
 *
 * This function checks if the Boost plugin is enabled and properly configured. If so, it
 * processes the poller output for the specified local data ID and updates the RRD files.
 * It also handles error reporting and manages the RRDTool pipe resource.
 *
 * @param int   $local_data_id The ID of the local data to process.
 * @param mixed $rrdtool_pipe  An existing RRDTool pipe resource. If not provided,
 *                             a new pipe will be initialized and closed within the function.
 *
 * @return bool Returns false if Boost is not enabled or not properly configured.
 */
function boost_fetch_cache_check(int $local_data_id, mixed $rrdtool_pipe = null) : bool {
	if (read_config_option('boost_rrd_update_enable') == 'on') {
		// include poller processing routines
		include_once(CACTI_PATH_LIBRARY . '/poller.php');

		// check to see if boost can do its job
		if (!boost_poller_id_check()) {
			return false;
		}

		// suppress warnings
		if (defined('E_DEPRECATED')) {
			error_reporting(E_ALL ^ E_DEPRECATED);
		} else {
			error_reporting(E_ALL);
		}

		// install the boost error handler
		set_error_handler('boost_error_handler');

		// process input parameters
		if (!is_resource($rrdtool_pipe)) {
			$rrdtool_pipe = rrd_init();
			$close_pipe   = true;
		} else {
			$close_pipe  = false;
		}

		// get the information to populate into the rrd files
		if (boost_check_correct_enabled()) {
			boost_process_poller_output($local_data_id, $rrdtool_pipe);
		}

		// restore original error handler
		restore_error_handler();

		// close rrdtool
		if ($close_pipe) {
			rrd_close($rrdtool_pipe);
		}

		return true;
	}

	return false;
}

/**
 * Determines whether a cached image should be returned based on the provided graph data array
 * and system configuration options.
 *
 * This function evaluates several conditions to decide if caching is enabled and applicable:
 * - If the graph data array contains 'export_csv' or 'export_realtime', caching is disabled.
 * - If the graph data array explicitly sets 'disable_cache' to true, caching is disabled.
 * - If the system configuration option 'boost_png_cache_enable' is set to 'on' and the
 *   caching state is determined to be valid, caching is enabled.
 *
 * @param array $graph_data_array Reference to the graph data array containing parameters
 *                                that influence caching behavior.
 *
 * @return bool Returns true if a cached image should be returned, false otherwise.
 */
function boost_return_cached_image(&$graph_data_array) : bool {
	if (isset($graph_data_array['export_csv'])) {
		return false;
	}

	if (isset($graph_data_array['export_realtime'])) {
		return false;
	}

	if (isset($graph_data_array['disable_cache']) && $graph_data_array['disable_cache'] == true) {
		return false;
	}

	if (read_config_option('boost_png_cache_enable') == 'on' && boost_determine_caching_state()) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks the graph cache for a given graph and returns the cached image if valid.
 * If the cache is invalid or unavailable, it falls back to Cacti's graphing functions.
 *
 * @param int   $local_graph_id   The ID of the local graph to check.
 * @param mixed $rra_id           The RRA ID associated with the graph.
 * @param mixed $rrdtool_pipe     Optional RRDTool pipe for processing (default: null).
 * @param array $graph_data_array Reference to an array containing graph data (default: empty array).
 * @param bool  $return           Whether to return the result (default: true).
 *
 * @return string|false Returns the cached image data if available and valid, or false otherwise.
 *
 * @throws Exception If there are issues with the cache directory or file operations.
 *
 */
function boost_graph_cache_check(int $local_graph_id, mixed $rra_id, mixed $rrdtool_pipe = null, array &$graph_data_array = [], bool $return = true) : string|false {
	// include poller processing routines
	include_once(CACTI_PATH_LIBRARY . '/poller.php');

	// suppressnwarnings
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	// install the boost error handler
	set_error_handler('boost_error_handler');

	// check to see if boost can do its job
	if (!boost_poller_id_check()) {
		return false;
	}

	// This is a realtime graph
	if (isset($graph_data_array['export_realtime'])) {
		// restore original error handler
		restore_error_handler();

		return false;
	}

	// if we are just printing the rrd command return
	if (isset($graph_data_array['print_source'])) {
		// restore original error handler
		restore_error_handler();

		return false;
	}

	// if we want to view the error message, then don't show the cache
	if ((isset($graph_data_array['output_flag'])) &&
		($graph_data_array['output_flag'] == RRDTOOL_OUTPUT_STDERR)) {
		// restore original error handler
		restore_error_handler();

		return false;
	}

	// get the information to populate into the rrd files
	if (boost_check_correct_enabled()) {
		// before we make a graph, we need to check for rrd updates and perform them.
		$local_data_ids = db_fetch_assoc_prepared('SELECT DISTINCT data_template_rrd.local_data_id
			FROM graph_templates_item
			INNER JOIN data_template_rrd
			ON (graph_templates_item.task_item_id = data_template_rrd.id)
			WHERE graph_templates_item.local_graph_id = ?
			AND data_template_rrd.local_data_id > 0', [$local_graph_id]);

		// first update the RRD files
		if (cacti_sizeof($local_data_ids)) {
			$updates = 0;

			foreach ($local_data_ids as $local_data_id) {
				$updates += boost_process_poller_output($local_data_id['local_data_id'], $rrdtool_pipe);
			}

			if ($updates) {
				// restore original error handler
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

		if (read_config_option('business_hours_enable') == 'on') {
			$bh_index = gnrv('business_hours') == 'true' ? '_bh_' : '';
		} else {
			$bh_index = '';
		}

		if ($cache_directory != '') {
			if (is_dir($cache_directory)) {
				if (is_writable($cache_directory)) {
					if ($rra_id > 0) {
						$cache_file = $cache_directory . '/' . get_selected_theme() . '_lgi_' . $local_graph_id . '_rrai_' . $rra_id . $bh_index;
					} else {
						$cache_file = $cache_directory . '/' . get_selected_theme() . '_lgi_' . $local_graph_id . '_rrai_' . $rra_id . $bh_index . '_tsi_' . $timespan;
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
						$mod_time        = filemtime($cache_file);
						$poller_interval = read_config_option('poller_interval');

						if (!isset($poller_interval)) {
							$poller_interval = '300';
						}

						if (($mod_time + $poller_interval) > time()) {
							if ($fileptr = fopen($cache_file, 'rb')) {
								$output = fread($fileptr, filesize($cache_file));
								fclose($fileptr);

								// restore original error handler
								restore_error_handler();

								// get access to the SNMP Cache of BOOST
								$mc = new MibCache('CACTI-BOOST-MIB');
								$mc->object('boostStatsTotalsImagesCacheReads')->count();
								$mc->object('boostStatsLastUpdate')->set(time());

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

	// restore original error handler
	restore_error_handler();

	return false;
}

/**
 * Prepares the graph data array for processing by configuring error handling
 * and determining the caching state.
 *
 * This function temporarily suppresses warnings and installs a custom error
 * handler to manage errors during the preparation process. It also determines
 * the caching state and sets the default output flag for the graph data array
 * if not already defined.
 *
 * @param array $graph_data_array The graph data array to be prepared.
 *
 * @return array The prepared graph data array with any necessary modifications.
 */
function boost_prep_graph_array(array $graph_data_array) : array {
	// suppress warnings
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	// install the boost error handler
	set_error_handler('boost_error_handler');

	if (boost_determine_caching_state()) {
		if (!isset($graph_data_array['output_flag'])) {
			if (!isset($graph_data_array['print_source'])) {
				$graph_data_array['output_flag'] = RRDTOOL_OUTPUT_STDOUT;
			}
		}
	}

	// restore original error handler
	restore_error_handler();

	return $graph_data_array;
}

/**
 * Handles the caching of graph images for the Cacti Boost plugin.
 *
 * This function checks if caching is enabled and determines the caching state.
 * If caching is valid, it generates a cache file path based on various parameters
 * such as graph ID, RRA ID, theme, timespan, and graph dimensions. It then writes
 * the graph image data to the cache file if the cache directory is writable.
 *
 * @param string|null $output         The graph image data to be cached.
 * @param int         $local_graph_id The ID of the local graph.
 * @param int         $rra_id         The RRA (Round Robin Archive) ID.
 *
 * @throws Exception If the cache directory is not writable, does not exist, or is not set.
 *
 * @return void
 */
function boost_graph_set_file(string|null &$output, int $local_graph_id, int|null $rra_id) : void {
	global $graph_data_array;

	// get access to the SNMP Cache of BOOST
	$mc = new MibCache('CACTI-BOOST-MIB');

	// suppress warnings
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	// install the boost error handler
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

		if (read_config_option('business_hours_enable') == 'on') {
			$bh_index = gnrv('business_hours') == 'true' ? '_bh_' : '';
		} else {
			$bh_index = '';
		}

		if ($cache_directory != '') {
			if (is_dir($cache_directory)) {
				if ($rra_id > 0) {
					$cache_file = $cache_directory . '/' . get_selected_theme() . '_lgi_' . $local_graph_id . '_rrai_' . $rra_id . $bh_index;
				} else {
					$cache_file = $cache_directory . '/' . get_selected_theme() . '_lgi_' . $local_graph_id . '_rrai_' . $rra_id . $bh_index . '_tsi_' . $timespan;
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
					// if the cache file was created in a prior step, save it
					if (strlen($output) > 10) {
						if ($fileptr = fopen($cache_file, 'w')) {
							fwrite($fileptr, $output, strlen($output));
							fclose($fileptr);
							chmod($cache_file, 0644);

							// count the number of images that had to be cached
							$mc->object('boostStatsTotalsImagesCacheWrites')->count();
							$mc->object('boostStatsLastUpdate')->set(time());
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

	// restore original error handler
	restore_error_handler();
}

/**
 * Tracks and logs execution time for specific areas of the application.
 *
 * This function is used to measure the time taken for a specific area of code
 * by marking the start and end times. It calculates the total elapsed time
 * and the number of cycles for the given area.
 *
 * @param string $area The name of the area being timed.
 * @param int    $type The type of timer action, either BOOST_TIMER_START or BOOST_TIMER_END.
 */
function boost_timer(string $area, int $type) : void {
	global $boost_stats_log;

	// get the time
	$btime = microtime(true);

	if ($type == BOOST_TIMER_START) {
		$boost_stats_log[$area][BOOST_TIMER_START] = $btime;
	} elseif ($type == BOOST_TIMER_END) {
		if (isset($boost_stats_log[$area][BOOST_TIMER_START])) {
			if (!isset($boost_stats_log[$area][BOOST_TIMER_TOTAL])) {
				$boost_stats_log[$area][BOOST_TIMER_TOTAL]  = 0;
				$boost_stats_log[$area][BOOST_TIMER_CYCLES] = 0;
			}
			$boost_stats_log[$area][BOOST_TIMER_TOTAL] += $btime - $boost_stats_log[$area][BOOST_TIMER_START];
			$boost_stats_log[$area][BOOST_TIMER_CYCLES]++;
			unset($boost_stats_log[$area][BOOST_TIMER_START]);
		}
	}
}

/**
 * Measures the overhead introduced by the `boost_timer` function.
 *
 * This function calculates the time taken to execute a series of
 * `boost_timer` start and end calls for a specified number of iterations,
 * defined by the `BOOST_TIMER_OVERHEAD_MULTIPLIER` constant. The measured
 * overhead is then returned as a floating-point value representing the
 * elapsed time in seconds.
 *
 * @return float The calculated overhead time in seconds.
 */
function boost_timer_get_overhead() : float {
	global $boost_stats_log;

	$start = microtime(true);
	$area  = 'calibrate';

	for ($i = 0; $i < BOOST_TIMER_OVERHEAD_MULTIPLIER; $i++) {
		boost_timer($area, BOOST_TIMER_START);
		boost_timer($area, BOOST_TIMER_END);
	}
	unset($boost_stats_log[$area]);

	return (microtime(true) - $start);
}

/**
 * Clamps the configured boost_parallel value to a sane process count.
 *
 * read_config_option() may return '', null, a negative value, or a non-numeric
 * string. All of those mean "run a single child"; anything else is the integer
 * process count. Both call sites in poller_boost.php must agree on this so the
 * parent spawns exactly as many children as it later waits for.
 *
 * @param mixed $value Raw boost_parallel option value.
 *
 * @return int Process count >= 1.
 */
function boost_clamp_parallel(mixed $value) : int {
	$processes = intval($value);

	return $processes > 0 ? $processes : 1;
}

/**
 * Validates an archive table name against the expected boost pattern.
 *
 * The name is interpolated into DDL/DML without parameter binding, so it must
 * match poller_output_boost_arch_<digits> exactly before any use.
 *
 * @param mixed $table Candidate table name.
 *
 * @return bool True when the name is a well-formed boost archive table.
 */
function boost_is_valid_archive_table(mixed $table) : bool {
	return is_string($table) && preg_match('/^poller_output_boost_arch_\d+$/', $table) === 1;
}

/**
 * Decides whether a boost log path is safe to splice into a shell redirect.
 *
 * redirect_args bypasses per-argument escaping, so the path must contain no
 * shell metacharacters. A plain character allow-list is too strict on Windows,
 * where legitimate paths carry a drive colon, backslashes, and spaces, so those
 * are permitted on win32 only.
 *
 * @param mixed $path Candidate log path.
 *
 * @return bool True when the path is safe to use unescaped in a redirect.
 */
function boost_log_path_is_safe(mixed $path) : bool {
	if (!is_string($path) || $path === '') {
		return false;
	}

	if (defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows') {
		// Allow drive colon, backslash, and space; still reject shell metacharacters.
		return preg_match('/[^A-Za-z0-9_.\/\\\\: -]/', $path) === 0;
	}

	return preg_match('/[^A-Za-z0-9_.\/\-]/', $path) === 0;
}

/**
 * Reports whether every launched boost child has registered.
 *
 * exec_background() is non-blocking, so the parent must not enter its drain loop
 * (and the unconditional archive-table DROP that follows) until all $expected
 * children are accounted for. A child counts as accounted for once it is either
 * still running or has already recorded a completion row, which closes the race
 * where a fast child registers and exits before its siblings boot.
 *
 * @param int $expected  Number of children launched.
 * @param int $running   Children currently in the processes table.
 * @param int $completed Completion rows in poller_output_boost_processes.
 *
 * @return bool True once running + completed covers every launched child.
 */
function boost_all_children_registered(int $expected, int $running, int $completed) : bool {
	return ($running + $completed) >= $expected;
}

/**
 * Retrieves the names of the archive tables related to poller output boost.
 *
 * @param mixed $latest_table - Optional. The name of the latest table to check
 *                            if no other tables are found.
 *
 * @return mixed - Returns an associative array of table names if found,
 *               where the keys and values are the table names.
 *               Returns false if no tables are found and the latest
 *               table is not provided or does not exist.
 */
function boost_get_arch_table_names(mixed $latest_table = '') : mixed {
	$tableData  = db_fetch_assoc("SHOW tables LIKE 'poller_output_boost_arch%'");
	$tableNames = [];

	if (cacti_sizeof($tableData)) {
		foreach ($tableData as $table) {
			$table                 = array_values($table);
			$tableNames[$table[0]] = $table[0];
		}
	}

	if (!cacti_sizeof($tableNames)) {
		$tableNames = array_rekey(
			db_fetch_assoc("SELECT TABLE_NAME AS name
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = SCHEMA()
				AND TABLE_NAME LIKE 'poller_output_boost_arch_%'"),
			'name', 'name'
		);
	}

	if (!cacti_sizeof($tableNames)) {
		// Both lookups above read metadata (SHOW TABLES / information_schema),
		// which can lag the data on a replica long enough to miss a table the
		// parent just created. Fall back to the validated name the parent passed
		// and confirm it with a data-plane read, which replicates with the rows.
		if (boost_is_valid_archive_table($latest_table) && boost_archive_table_readable($latest_table)) {
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
 * Confirms an archive table exists by reading from it, not from metadata.
 *
 * SHOW TABLES and information_schema are metadata-plane lookups that can lag on
 * a replica; a SELECT against the table itself replicates with the row data, so
 * it reflects the table the parent just created. The name must already be a
 * validated boost archive table before it reaches the interpolated query.
 *
 * @param string $table A name that passed boost_is_valid_archive_table().
 *
 * @return bool True when the table can be read.
 */
function boost_archive_table_readable(string $table) : bool {
	if (!boost_is_valid_archive_table($table)) {
		return false;
	}

	// COUNT(*) returns a numeric row even for an empty table, so a non-null,
	// non-false result means the table exists; a query error (missing table)
	// yields false. $log = false: a fallback miss is expected, not an error.
	$result = db_fetch_cell("SELECT COUNT(*) FROM `$table`", '', false);

	return $result !== false && $result !== null;
}

/**
 * Grabs data from the 'poller_output' and 'poller_output_boost*'
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
 * @param int   $local_data_id - The local data id to update.
 * @param mixed $rrdtool_pipe  - An array for the RRDtool process object
 *
 * @return int
 */
function boost_process_poller_output(int $local_data_id, mixed $rrdtool_pipe = []) : int {
	global $database_default, $boost_sock, $boost_timeout, $get_memory, $memory_used, $archive_table;

	static $warning_issued;
	static $rrdtool_version = null;

	cacti_system_zone_set();

	include_once(CACTI_PATH_LIBRARY . '/rrd.php');

	// suppress warnings
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	// install the boost error handler
	set_error_handler('boost_error_handler');

	if (cacti_version_compare(get_rrdtool_version(), '1.5', '<')) {
		while (!db_fetch_cell("SELECT GET_LOCK('boost.single_ds.$local_data_id', 1)")) {
			usleep(50000);
		}
	}

	$data_ids_to_get = read_config_option('boost_rrd_update_max_records_per_select');

	$archive_tables = boost_get_arch_table_names($archive_table);

	$results = [];

	// avoid getting rows in the middle of poller run
	$timestamp = db_fetch_cell('SELECT MIN(UNIX_TIMESTAMP(start_time))
		FROM poller_time
		WHERE end_time="0000-00-00"');

	if (empty($timestamp)) {
		$timestamp = time() - 10;
	}

	$query_string        = '';
	$sql_params          = [];
	$locks               = false;
	$temp_table          = false;

	if (cacti_count($archive_tables)) {
		$temp_table = 'poller_output_boost_temp_' . $local_data_id . '_' . random_int(0, PHP_INT_MAX);

		db_execute("CREATE TEMPORARY TABLE `{$temp_table}` LIKE poller_output_boost");

		foreach ($archive_tables as $table) {
			db_execute_prepared("INSERT INTO `{$temp_table}`
				SELECT *
				FROM `{$table}`
				WHERE local_data_id = ?",
				[$local_data_id], false);
		}
	}

	if ($temp_table !== false) {
		db_execute_prepared("INSERT INTO `{$temp_table}`
			SELECT *
			FROM poller_output_boost
			WHERE local_data_id = ?
			AND time < FROM_UNIXTIME(?)",
			[$local_data_id, $timestamp], false);

		$query_string = "SELECT po.local_data_id, dl.data_template_id,
			UNIX_TIMESTAMP(po.time) AS timestamp, po.rrd_name, po.output
			FROM `{$temp_table}` AS po
			INNER JOIN data_local AS dl
			ON po.local_data_id = dl.id
			WHERE po.local_data_id = ?
			AND po.time < FROM_UNIXTIME(?)
			ORDER BY time ASC, rrd_name ASC";
	} else {
		$query_string = 'SELECT po.local_data_id, dl.data_template_id,
			UNIX_TIMESTAMP(po.time) AS timestamp, po.rrd_name, po.output
			FROM poller_output_boost AS po
			INNER JOIN data_local AS dl
			ON po.local_data_id = dl.id
			WHERE po.local_data_id = ?
			AND po.time < FROM_UNIXTIME(?)
			ORDER BY time ASC, rrd_name ASC';
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

	// remove the entries from the table
	boost_timer('delete', BOOST_TIMER_START);

	if (cacti_count($archive_tables)) {
		foreach ($archive_tables as $table) {
			db_execute_prepared("DELETE IGNORE
				FROM $table
				WHERE local_data_id = ?",
				[$local_data_id], false);
		}
	}

	if (cacti_sizeof($results)) {
		db_execute_prepared('DELETE FROM poller_output_boost
			WHERE local_data_id = ?
			AND time < FROM_UNIXTIME(?)',
			[$local_data_id, $timestamp], false);
	}

	boost_timer('delete', BOOST_TIMER_END);

	if (cacti_version_compare(get_rrdtool_version(), '1.5', '<')) {
		db_execute("SELECT RELEASE_LOCK('boost.single_ds.$local_data_id')");
	}

	// log memory
	if ($get_memory) {
		$cur_memory = memory_get_usage();

		if ($cur_memory > $memory_used) {
			$memory_used = $cur_memory;
		}
	}

	if (cacti_sizeof($results)) {
		$local_init = false;

		if (!$rrdtool_pipe) {
			$rrdtool_pipe = rrd_init();
			$local_init   = true;
		}

		// create an array keyed off of each .rrd file
		$time           = -1;
		$outbuf         = '';
		$last_update    = -1;
		$multi_vals_set = false;

		$last_item = [
			'local_data_id' => -1,
			'timestamp'     => -1,
			'rrd_name'      => ''
		];

		// we are going to blow away all record if ok
		$vals_in_buffer = 0;
		$reset_template = false;

		$upd_string_len = read_config_option('boost_rrd_update_string_length');

		// initialize some variables
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
				[$local_data_id]);
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
				[$local_data_id]),
			'data_source_name', 'data_source_name'
		);

		boost_timer('results_cycle', BOOST_TIMER_START);

		// go through each poller_output_boost entries and process
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

			// don't generate error messages if the RRD has already been updated
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

					// check return status for delete operation
					if (!str_contains(trim($return_value), 'OK') && $return_value != '') {
						cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", false, 'BOOST');
					}
				}

				if (!str_contains($value, 'DNP')) {
					$output  = ' ' . $item['timestamp'];
					$outbuf .= $output;
					$outlen += strlen($output);
				}

				$time = $item['timestamp'];
			}

			// single one value output
			if (str_contains($value, 'DNP')) {
				// continue, bad time
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
			} elseif (str_contains($value, ':')) {
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
								[$item['local_data_id']]),
							'data_name', 'data_source_name'
						);

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
						$rrd_field_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
								FROM data_template_rrd AS dtr
								INNER JOIN data_input_fields AS dif
								ON dtr.data_input_field_id = dif.id
								WHERE dtr.local_data_id = ?',
								[$item['local_data_id']]),
							'data_name', 'data_source_name'
						);

						$unused_data_source_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
								FROM data_template_rrd AS dtr
								LEFT JOIN graph_templates_item AS gti
								ON dtr.id = gti.task_item_id
								WHERE dtr.local_data_id = ? AND gti.task_item_id IS NULL',
								[$item['local_data_id']]),
							'data_source_name', 'data_source_name'
						);
					}

					$rrd_tmpl = '';
				}

				$first_tmpl = true;
				$multi_ok   = false;

				if (cacti_sizeof($values)) {
					foreach ($values as $value) {
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

				// we only want to process the template and gather the fields once
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
								[$item['local_data_id']]),
							'data_name', 'data_source_name'
						);

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
						$rrd_field_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dif.data_name
								FROM data_template_rrd AS dtr
								INNER JOIN data_input_fields AS dif
								ON dtr.data_input_field_id = dif.id
								WHERE dtr.local_data_id = ?',
								[$item['local_data_id']]),
							'data_name', 'data_source_name'
						);

						$unused_data_source_names = array_rekey(
							db_fetch_assoc_prepared('SELECT DISTINCT dtr.data_source_name, dtr.data_source_name
								FROM data_template_rrd AS dtr
								LEFT JOIN graph_templates_item AS gti
								ON dtr.id = gti.task_item_id
								WHERE dtr.local_data_id = ? AND gti.task_item_id IS NULL',
								[$item['local_data_id']]),
							'data_source_name', 'data_source_name'
						);
					}

					$rrd_tmpl = '';
				}

				$expected = '';

				// TODO: This is legacy code that does not get used. Find out why its still here.
				$nt_rrd_field_names = [];

				if (cacti_sizeof($nt_rrd_field_names) > 0) {
					foreach ($nt_rrd_field_names as $field) { // @phpstan-ignore-line
						if (cacti_sizeof($unused_data_source_names) && isset($unused_data_source_names[$field])) {
							continue;
						}

						$expected .= ($expected != '' ? ' ' : '') . "$field:value";

						if ($reset_template) {
							$rrd_tmpl .= ($rrd_tmpl != '' ? ':' : '') . $field;
						}

						$tv_tmpl[$field] = 'U';
					}
				}

				cacti_log(sprintf('WARNING: Invalid output! MULTI DS[%d] Encountered [%s] Expected [%s]', $item['local_data_id'], $value, $expected), false, 'POLLER');

				$vals_in_buffer++;
				$multi_vals_set = true;
			}
		}

		// process the last rrdupdate if applicable
		if ($vals_in_buffer) {
			boost_timer('rrdupdate', BOOST_TIMER_START);
			$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $outbuf, $rrdtool_pipe);
			boost_timer('rrdupdate', BOOST_TIMER_END);

			// check return status for delete operation
			if (!str_contains(trim($return_value), 'OK') && $return_value != '') {
				cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", false, 'BOOST');
			}
		}

		boost_timer('results_cycle', BOOST_TIMER_END);

		if ($local_init) {
			rrd_close($rrdtool_pipe);
		}
	}

	// restore original error handler
	restore_error_handler();

	return cacti_sizeof($results);
}

/**
 * Retrieves the last update time of an RRD file using rrdtool.
 *
 * This function checks if the provided RRD file path is valid and exists.
 * If the file exists, it uses rrdtool to fetch the last update time.
 * If the file path is empty, it returns the current system time.
 *
 * @param string $rrd_path     The path to the RRD file.
 * @param mixed  $rrdtool_pipe The rrdtool pipe resource for executing commands.
 *
 * @return int|string The last update time of the RRD file as a timestamp, or the current time if the path is empty.
 */
function boost_rrdtool_get_last_update_time(string $rrd_path, mixed $rrdtool_pipe) : int|string {
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

/**
 * Determines the caching state for the application based on various conditions.
 *
 * This function evaluates several factors to decide whether caching should be enabled
 * or disabled. It considers session variables, request parameters, and custom settings.
 *
 * @return bool Returns `true` if caching is enabled, `false` otherwise.
 */
function boost_determine_caching_state() : bool {
	set_default_action();

	// turn off image caching if viewing thold vrules
	if (isset($_SESSION[OPTIONS_WEB]['thold_draw_vrules']) && $_SESSION[OPTIONS_WEB]['thold_draw_vrules'] == 'on') {
		return false;
	}

	$action = grv('action');

	// turn off image caching for the following actions
	if ($action == 'properties' ||
		$action == 'zoom' ||
		$action == 'edit' ||
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

/**
 * Retrieves the RRD filename and template for a given local data ID.
 *
 * This function queries the database to fetch the RRD path and template information
 * associated with the specified local data ID. It determines whether the data sources
 * have associated RRD names and constructs the RRD template accordingly.
 *
 * @param int $local_data_id The ID of the local data for which the RRD filename and template are to be retrieved.
 *
 * @return array An associative array containing:
 *               - 'rrd_path' (string): The path to the RRD file.
 *               - 'rrd_template' (string): The RRD template constructed from the data source names.
 */
function boost_get_rrd_filename_and_template(int $local_data_id) : array {
	$rrd_path     = '';
	$all_nulls    = true;
	$ds_null      = [];
	$ds_nnull     = [];

	$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
		FROM data_local
		WHERE id = ?',
		[$local_data_id]);

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
			[$local_data_id]);
	} else {
		$ds_names = db_fetch_assoc_prepared("SELECT DISTINCT data_source_name, rrd_name, rrd_path
			FROM data_template_rrd AS dtr
			INNER JOIN poller_item AS pi
			ON pi.local_data_id = dtr.local_data_id
			AND (pi.rrd_name = dtr.data_source_name OR pi.rrd_name = '')
			WHERE dtr.local_data_id = ?
			ORDER BY data_source_name ASC",
			[$local_data_id]);
	}

	if (cacti_sizeof($ds_names)) {
		foreach ($ds_names as $ds_name) {
			if ($rrd_path == '') {
				$rrd_path = $ds_name['rrd_path'];
			}

			if ($ds_name['rrd_name'] == '') {
				$ds_null[] = $ds_name['data_source_name'];
			} elseif ($ds_name['rrd_name'] == $ds_name['data_source_name']) {
				$ds_nnull[] = $ds_name['data_source_name'];
				$all_nulls  = false;
			}
		}
	}

	if ($all_nulls) {
		$rrd_template = implode(':', $ds_null);
	} else {
		$rrd_template = implode(':', $ds_nnull);
	}

	return ['rrd_path' => $rrd_path, 'rrd_template' => trim($rrd_template)];
}

/**
 * Creates an RRDTool data source file for a given local data ID.
 *
 * This function generates the necessary RRDTool commands to create a data source file
 * based on the provided local data ID. It ensures that the file does not already exist,
 * validates the associated RRA (Round Robin Archives), and constructs the data source
 * and RRA definitions. It also handles directory creation and permission settings for
 * structured paths.
 *
 * @param int   $local_data_id - The ID of the local data source to create.
 * @param bool  $show_source   - If true, returns the RRDTool command instead of executing it.
 * @param mixed $rrdtool_pipe  - The RRDTool pipe resource for executing commands.
 *
 * @return mixed - Returns the RRDTool command string if $show_source is true,
 *               -1 if the file already exists,
 *               false if no RRA is associated with the data source,
 *               or the result of the RRDTool execution.
 */
function boost_rrdtool_function_create(int $local_data_id, bool $show_source, mixed $rrdtool_pipe) : mixed {
	global $consolidation_functions, $data_source_types;

	include(CACTI_PATH_INCLUDE . '/global_arrays.php');

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
		ORDER BY dspc.consolidation_function_id, rra_order', [$local_data_id]);

	/* if we find that this DS has no RRA associated; get out.  This would
	 * indicate that a data sources has been deleted
	 */
	if (cacti_sizeof($rras) <= 0) {
		return false;
	}

	// create the "--step" line
	$create_ds = RRD_NL . '--start 0 --step ' . $rras[0]['rrd_step'] . ' ' . RRD_NL;

	/**
	 * We have to check for Non-Templated Data Source first as they may not include
	 * a graph.  So, for that case, we need the RRDfile to include all data sources
	 */
	$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
		FROM data_local
		WHERE id = ?',
		[$local_data_id]);

	if ($data_template_id > 0) {
		$data_sources = db_fetch_assoc_prepared('SELECT DISTINCT dtr.id, dtr.data_source_name, dtr.rrd_heartbeat,
			dtr.rrd_minimum, dtr.rrd_maximum, dtr.data_source_type_id
			FROM data_template_rrd AS dtr
			INNER JOIN graph_templates_item AS gti
			ON dtr.id = gti.task_item_id
			WHERE dtr.local_data_id = ?
			ORDER BY local_data_template_rrd_id',
			[$local_data_id]);
	} else {
		$data_sources = db_fetch_assoc_prepared('SELECT DISTINCT dtr.id, dtr.data_source_name, dtr.rrd_heartbeat,
			dtr.rrd_minimum, dtr.rrd_maximum, dtr.data_source_type_id
			FROM data_template_rrd AS dtr
			WHERE dtr.local_data_id = ?
			ORDER BY local_data_template_rrd_id',
			[$local_data_id]);
	}

	/**
	 * ONLY make a new DS entry if:
	 *
	 * - There are multiple data sources and this item is not the main one.
	 * - There are only one data source (then use it)
	 */
	if (cacti_sizeof($data_sources)) {
		foreach ($data_sources as $data_source) {
			// use the cacti ds name by default or the user defined one, if entered
			$data_source_name = get_data_source_item_name($data_source['id']);

			if (empty($data_source['rrd_maximum'])) {
				// in case no maximum is given, use "Undef" value
				$data_source['rrd_maximum'] = 'U';
			} elseif (str_contains($data_source['rrd_maximum'], '|query_')) {
				$data_local = db_fetch_row_prepared('SELECT * FROM data_local WHERE id = ?', [$local_data_id]);

				$speed = rrdtool_function_interface_speed($data_local);

				if ($data_source['rrd_maximum'] == '|query_ifSpeed|' || $data_source['rrd_maximum'] == '|query_ifHighSpeed|') {
					$data_source['rrd_maximum'] = $speed;
				} else {
					$data_source['rrd_maximum'] = substitute_snmp_query_data($data_source['rrd_maximum'],$data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
				}
			} elseif (($data_source['rrd_maximum'] != 'U') && (int)$data_source['rrd_maximum'] <= (int)$data_source['rrd_minimum']) {
				// max > min required, but take care of an "Undef" value
				$data_source['rrd_maximum'] = (int)$data_source['rrd_minimum'] + 1;
			}

			// min==max==0 won't work with rrdtool
			if ($data_source['rrd_minimum'] == 0 && $data_source['rrd_maximum'] == 0) {
				$data_source['rrd_maximum'] = 'U';
			}

			$create_ds .= "DS:$data_source_name:" . $data_source_types[$data_source['data_source_type_id']] . ':' . $data_source['rrd_heartbeat'] . ':' . $data_source['rrd_minimum'] . ':' . $data_source['rrd_maximum'] . RRD_NL;
		}
	}

	$create_rra = '';

	// loop through each available RRA for this DS
	foreach ($rras as $rra) {
		$create_rra .= 'RRA:' . $consolidation_functions[$rra['consolidation_function_id']] . ':' . $rra['x_files_factor'] . ':' . $rra['steps'] . ':' . $rra['rows'] . RRD_NL;
	}

	if (CACTI_SERVER_OS != 'win32') {
		$owner_id = fileowner(CACTI_PATH_RRA);
		$group_id = filegroup(CACTI_PATH_RRA);
	}

	/**
	 * check for structured path configuration, if in place verify directory
	 * exists and if not create it.
	 */
	if (read_config_option('extended_paths') == 'on') {
		if (read_config_option('storage_location') > 0) {
			if (rrdtool_execute('is_dir ' . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST') === false) {
				if (rrdtool_execute('mkdir ' . dirname($data_source_path), true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST') === false) {
					cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", false);
				}
			}
		} elseif (!is_dir(dirname($data_source_path))) {
			if (CACTI_WEB == false || is_writable(CACTI_PATH_RRA)) {
				if (mkdir(dirname($data_source_path), 0775, true)) {
					if (CACTI_SERVER_OS != 'win32' && posix_getuid() == 0) {
						$success  = true;
						$paths    = explode('/', str_replace(CACTI_PATH_RRA, '/', dirname($data_source_path)));
						$spath    = '';

						foreach ($paths as $path) {
							if ($path == '') {
								continue;
							}

							$spath .= '/' . $path;

							$powner_id = fileowner(CACTI_PATH_RRA . $spath);
							$pgroup_id = fileowner(CACTI_PATH_RRA . $spath);

							if ($powner_id != $owner_id) {
								$success = chown(CACTI_PATH_RRA . $spath, $owner_id);
							}

							if ($pgroup_id != $group_id && $success) {
								$success = chgrp(CACTI_PATH_RRA . $spath, $group_id);
							}

							if (!$success) {
								cacti_log("ERROR: Unable to set directory permissions for '" . CACTI_PATH_RRA . $spath . "'", false);

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

		if (CACTI_SERVER_OS != 'win32' && posix_getuid() == 0) {
			chown($data_source_path, (int) $owner_id);
			chgrp($data_source_path, (int) $group_id);
		}

		return $success;
	}
}

/**
 * A re-write of the Cacti rrdtool update command specifically designed for bulk updates
 *
 * @param int    $local_data_id       The ID of the local data source.
 * @param string $rrd_path            The file path to the RRD file.
 * @param string $rrd_update_template The template string for the RRD update.
 * @param string &$rrd_update_values  The values to update the RRD file with (passed by reference).
 * @param mixed  $rrdtool_pipe        Optional. The RRDTool pipe resource for communication.
 *
 * @return string Returns 'OK' on successful update or if the RRD file is invalid or missing.
 */
function boost_rrdtool_function_update(int $local_data_id, string $rrd_path, string $rrd_update_template, string &$rrd_update_values, mixed $rrdtool_pipe = null) : string {
	// lets count the number of rrd files processed
	$rrds_processed = 0;

	// let's check for deleted Data Sources
	$valid_entry = true;

	/* check for an empty rrd_path
	 * this can happen when you've removed a data source
	 * while boost is running
	 */
	if ($rrd_path == '') {
		return 'OK';
	}

	// create the rrd if one does not already exist
	if (read_config_option('storage_location')) {
		$file_exists = rrdtool_execute("file_exists $rrd_path" , true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'BOOST');
	} else {
		$file_exists = file_exists($rrd_path);
	}

	if ($file_exists == false) {
		$ds_exists = db_fetch_cell_prepared('SELECT id FROM data_local WHERE id = ?', [$local_data_id]);

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
			boost_debug("update $rrd_path $update_options --template $rrd_update_template $rrd_update_values");

			rrdtool_execute("update $rrd_path $update_options --template $rrd_update_template $rrd_update_values", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'BOOST');
		} else {
			boost_debug("update $rrd_path $update_options $rrd_update_values");

			rrdtool_execute("update $rrd_path $update_options $rrd_update_values", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'BOOST');
		}

		return 'OK';
	}

	return 'OK';
}

/**
 * Adjusts the PHP memory limit based on the configuration option 'boost_poller_mem_limit'.
 *
 * @return void
 */
function boost_memory_limit() : void {
	$memory_limit = read_config_option('boost_poller_mem_limit');

	if ($memory_limit != '-1') {
		ini_set('memory_limit', $memory_limit . 'M');
	} else {
		ini_set('memory_limit', -1);
	}
}

/**
 * Executes the Boost poller bottom process.
 *
 * This function is responsible for initiating the Boost poller process if the
 * Boost RRD update feature is enabled in the configuration. It performs the
 * following tasks:
 * - Reads configuration options to determine if Boost is enabled and to fetch
 *   necessary paths and settings.
 * - Updates SNMP statistics using the `boost_update_snmp_statistics` function.
 * - Validates the Boost log file and directory for writability if debugging is enabled.
 * - Constructs the command string to execute the Boost poller script (`poller_boost.php`),
 *   including debug options and log redirection if applicable.
 * - Executes the Boost poller script in the background.
 *
 * Configuration options used:
 * - `boost_rrd_update_enable`: Determines if Boost RRD updates are enabled.
 * - `path_boost_log`: Path to the Boost log file.
 * - `boost_debug_enabled`: Enables or disables Boost debugging.
 * - `path_php_binary`: Path to the PHP binary.
 *
 * @return void
 */
function boost_poller_bottom() : void {
	if (read_config_option('boost_rrd_update_enable') == 'on') {
		include_once(CACTI_PATH_LIBRARY . '/poller.php');

		chdir(CACTI_PATH_BASE);

		$redirect_args = '';

		boost_update_snmp_statistics();

		$boost_log     = read_config_option('path_boost_log');
		$boost_debug   = read_config_option('boost_debug_enabled') == 'on' ? true : false;
		$boost_logdir  = dirname($boost_log);

		if ($boost_debug && $boost_log != '') {
			if (!is_writable($boost_log) || !is_dir($boost_logdir) || !is_writable($boost_logdir)) {
				boost_debug("WARNING: Boost log '$boost_log' does not exist or is not writable!");

				cacti_log("WARNING: Boost log '$boost_log' is not writable!", false, 'BOOST');

				$boost_log = '';
			}
		}

		$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));

		if ($boost_debug && $boost_log != '') {
			if (CACTI_SERVER_OS == 'unix') {
				$extra_args    = '-q ' . CACTI_PATH_BASE . '/poller_boost.php --debug';
				$redirect_args =  '>> ' . $boost_log . ' 2>&1';
			} else {
				$extra_args    = '-q ' . CACTI_PATH_BASE . '/poller_boost.php --debug';
				$redirect_args = '>> ' . $boost_log;
			}
		} else {
			$extra_args = '-q ' . CACTI_PATH_BASE . '/poller_boost.php';
		}

		exec_background($command_string, $extra_args, $redirect_args);
	}
}

/**
 * Updates SNMP statistics for the Cacti Boost system.
 *
 * This function gathers information about the Boost table status, including
 * the number of pending and archived records, table size, and storage engine.
 * It calculates various statistics such as the total number of records,
 * average row length, and maximum record length. The gathered data is then
 * stored in the MIB cache for SNMP monitoring.
 *
 * @return void
 */
function boost_update_snmp_statistics() : void {
	$mc = new MibCache('CACTI-BOOST-MIB');

	// get the boost table status
	$boost_table_status = db_fetch_assoc("SELECT *
		FROM information_schema.tables
		WHERE table_schema = SCHEMA()
		AND (table_name LIKE 'poller_output_boost_arch_%'
		OR table_name LIKE 'poller_output_boost')");

	$total_data_sources = db_fetch_cell('SELECT COUNT(*) FROM poller_item');

	$pending_records = 0;
	$arch_records    = 0;
	$data_length     = 0;
	$engine          = '';
	$max_data_length = 0;

	if (cacti_sizeof($boost_table_status)) {
		foreach ($boost_table_status as $table) {
			if ($table['TABLE_NAME'] == 'poller_output_boost') {
				$pending_records += $table['TABLE_ROWS'];
			} else {
				$arch_records += $table['TABLE_ROWS'];
			}
			$data_length += $table['DATA_LENGTH'];
			$data_length -= $table['DATA_FREE'];
			$engine          = $table['ENGINE'];
			$max_data_length = $table['MAX_DATA_LENGTH'];
		}
	}

	$total_records  = $pending_records + $arch_records;
	$avg_row_length = ($total_records ? intval($data_length / $total_records) : 0);

	if (strcmp($engine, 'MEMORY') == 0) {
		$max_length        = db_fetch_cell('SELECT MAX(LENGTH(output)) FROM poller_output_boost');
		$max_table_allowed = $max_data_length;
		$max_table_records = ($avg_row_length ? round($max_data_length / $avg_row_length, 0) : 0);
	} else {
		$max_length        = '0';
		$max_table_allowed = '-1';
		$max_table_records = '0';
	}

	$mc->object('boostApplStorageDatabaseEngine')->set($engine);
	$mc->object('boostApplStorageMaxTableSize')->set($max_table_allowed);
	$mc->object('boostApplStorageMaxRecords')->set($max_table_records);
	$mc->object('boostApplLastUpdate')->set(time());

	$mc->object('boostStatsTotalsRecords')->set($total_records);
	$mc->object('boostStatsTotalsRecordsPending')->set($pending_records);
	$mc->object('boostStatsTotalsRecordsArchived')->set($arch_records);
	$mc->object('boostStatsStorageTableSize')->set($data_length);
	$mc->object('boostStatsStorageAverageRecordSize')->set($avg_row_length);
	$mc->object('boostStatsStorageMaxRecordLength')->set($max_length);
	$mc->object('boostStatsTotalsDataSources')->set($total_data_sources);

	$mc->object('boostStatsLastUpdate')->set(time());
}

/**
 * Logs debug messages to the output or a log file based on the debug settings.
 *
 * @param string $string The message to be logged as a debug message.
 *
 * @return void
 */
function boost_debug(string $string) : void {
	global $debug, $boost_log, $boost_debug, $child;

	$string = 'DEBUG: ' . trim($string, " \n");

	if ($debug || ($boost_log != '' && $boost_debug)) {
		print $string . PHP_EOL;
	}
}
