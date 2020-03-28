#!/usr/bin/php -q
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

/* tick use required as of PHP 4.3.0 to accomodate signal handling */
declare(ticks = 1);

require(__DIR__ . '/include/cli_check.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/boost.php');
require_once($config['base_path'] . '/lib/dsstats.php');
require_once($config['base_path'] . '/lib/rrd.php');

/* get the boost polling cycle */
$max_run_duration = read_config_option('boost_rrd_update_max_runtime');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = false;
$forcerun = false;
$verbose  = false;

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
$rrd_updates = -1;

/* let's give this script lot of time to run for ever */
ini_set('max_execution_time', '0');
boost_memory_limit();

if ((read_config_option('boost_rrd_update_enable') == 'on') || $forcerun) {
	/* turn on the system level updates as that is what dictates "on/off" */
	if ((!$forcerun) && (read_config_option('boost_rrd_update_system_enable') != 'on')) {
		set_config_option('boost_rrd_update_system_enable','on');
	}

	$seconds_offset = read_config_option('boost_rrd_update_interval') * 60;

	/* find out if it's time to collect device information */
	$last_run_time = strtotime(read_config_option('boost_last_run_time'));
	$next_run_time = strtotime(read_config_option('boost_next_run_time'));

	/* determine the next start time */
	$current_time = time();
	if (empty($last_run_time)) {
		/* since the poller has never run before, let's fake it out */
		$next_run_time = $current_time + $seconds_offset;

		set_config_option('boost_last_run_time', date('Y-m-d G:i:s', $current_time));

		$last_run_time = $current_time;
	} else {
		$next_run_time = $last_run_time + $seconds_offset;
	}
	$time_till_next_run = $next_run_time - $current_time;

	/* determine if you must output boost table now */
	$max_records     = read_config_option('boost_rrd_update_max_records');
	$current_records = boost_get_total_rows();

	if (($time_till_next_run <= 0) ||
		($forcerun) ||
		($current_records >= $max_records) ||
		($next_run_time <= $current_time)) {
		set_config_option('boost_last_run_time', date('Y-m-d G:i:s', $current_time));

		/* output all the rrd data to the rrd files */
		$rrd_updates = output_rrd_data($current_time, $forcerun);

		if ($rrd_updates > 0) {
			log_boost_statistics($rrd_updates);
			$next_run_time = $current_time + $seconds_offset;
		} else { /* rollback last run time */
			set_config_option('boost_last_run_time', date('Y-m-d G:i:s', $last_run_time));
		}

		dsstats_boost_bottom();

		api_plugin_hook('boost_poller_bottom');
	}

	/* store the next run time so that people understand */
	if ($rrd_updates > 0) {
		set_config_option('boost_next_run_time', date('Y-m-d G:i:s', $next_run_time));
	}
} else {
	/* turn off the system level updates */
	if (read_config_option('boost_rrd_update_system_enable') == 'on') {
		set_config_option('boost_rrd_update_system_enable','');
	}

	$rows =  boost_get_total_rows();

	if ($rows > 0) {
		/* determine the time to clear the table */
		$current_time = time();

		/* output all the rrd data to the rrd files */
		$rrd_updates = output_rrd_data($current_time, $forcerun);

		if ($rrd_updates > 0) {
			log_boost_statistics($rrd_updates);
		}
	}
}

purge_cached_png_files($forcerun);

exit(0);

function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Boost Poller terminated by user', false, 'BOOST');

			/* tell the main poller that we are done */
			set_config_option('boost_poller_status', 'terminated - end time:' . date('Y-m-d G:i:s'));

			exit;
			break;
		default:
			/* ignore all other signals */
	}

}

function output_rrd_data($start_time, $force = false) {
	global $start, $max_run_duration, $config, $database_default, $debug, $get_memory, $memory_used;

	$boost_poller_status = read_config_option('boost_poller_status');
	$rrd_updates = 0;

	/* implement process lock control for boost */
	if (!db_fetch_cell("SELECT GET_LOCK('poller_boost', 1)")) {
		if ($debug) {
			cacti_log('DEBUG: Found lock, so another boost process is running');
		}

		return -1;
	}

	/* detect a process that has overrun it's warning time */
	if (substr_count($boost_poller_status, 'running')) {
		$status_array = explode(':', $boost_poller_status);

		if (!empty($status_array[1])) {
			$previous_start_time = strtotime($status_array[1]);

			/* if the runtime was exceeded, allow the next process to run */
			if ($previous_start_time + $max_run_duration < $start_time) {
				cacti_log('WARNING: Detected Poller Boost Overrun, Possible Boost Poller Crash', false, 'BOOST SVR');
			}
		}
	}

	/* if the poller is not running, or has never run, start */
	/* mark the boost server as running */
	set_config_option('boost_poller_status', 'running - start time:' . date('Y-m-d G:i:s'));

	$current_time      = date('Y-m-d G:i:s', $start_time);
	$rrdtool_pipe      = rrd_init();
	$runtime_exceeded  = false;

	/* let's set and track memory usage will we */
	if (!function_exists('memory_get_peak_usage')) {
		$get_memory   = true;
		$memory_used  = memory_get_usage();
	} else {
		$get_memory   = false;
	}

	$delayed_inserts = db_fetch_row("SHOW STATUS LIKE 'Not_flushed_delayed_rows'");
	while($delayed_inserts['Value']) {
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

	$more_arch_tables = db_fetch_assoc_prepared("SELECT table_name AS name
		FROM information_schema.tables
		WHERE table_schema = SCHEMA()
		AND table_name LIKE 'poller_output_boost_arch_%'
		AND table_name != ?
		AND table_rows > 0", array($archive_table));

	if (cacti_count($more_arch_tables)) {
		foreach($more_arch_tables as $table) {
			$table_name = $table['name'];
			$rows = db_fetch_cell("SELECT count(local_data_id) FROM $table_name");
			if (is_numeric($rows) && intval($rows) > 0) {
				db_execute("INSERT INTO $archive_table SELECT * FROM $table_name");
				db_execute("TRUNCATE TABLE $table_name");
			}
		}
	}

	if ($archive_table == '') {
		cacti_log('ERROR: Failed to retrieve archive table name');
		return -1;
	}

	$max_per_select = read_config_option('boost_rrd_update_max_records_per_select');

	db_execute('CREATE TEMPORARY TABLE boost_local_data_ids (
		local_data_id int unsigned default "0",
		PRIMARY KEY (local_data_id))
		ENGINE=MEMORY');

	db_execute("INSERT INTO boost_local_data_ids
		SELECT DISTINCT local_data_id
		FROM $archive_table");

	$total_rows = db_fetch_cell("SELECT COUNT(local_data_id) FROM $archive_table");
	$data_ids   = db_fetch_cell("SELECT COUNT(DISTINCT local_data_id) FROM $archive_table");

	if (!empty($total_rows)) {
		$passes  = ceil($total_rows / $max_per_select);
		$ids_per_pass = ceil($data_ids / $passes);
		$curpass = 0;
		while ($curpass <= $passes) {
			$last_id = db_fetch_cell("SELECT MAX(local_data_id)
				FROM (
					SELECT local_data_id
					FROM boost_local_data_ids
					ORDER BY local_data_id
					LIMIT " . (($curpass * $ids_per_pass) + 1) . ", $ids_per_pass
				) AS result");

			if (empty($last_id)) {
				break;
			}

			boost_process_local_data_ids($last_id, $rrdtool_pipe);

			$curpass++;

			if (((time()-$start) > $max_run_duration) && (!$runtime_exceeded)) {
				cacti_log('WARNING: RRD On Demand Updater Exceeded Runtime Limits. Continuing to Process!!!');
				$runtime_exceeded = true;
			}
		}
	}

	/* remove temporary count table */
	db_execute('DROP TEMPORARY TABLE boost_local_data_ids');

	/* tell the main poller that we are done */
	set_config_option('boost_poller_status', 'complete - end time:' . date('Y-m-d G:i:s'));

	/* log memory usage */
	if (function_exists('memory_get_peak_usage')) {
		set_config_option('boost_peak_memory', memory_get_peak_usage());
	} else {
		set_config_option('boost_peak_memory', $memory_used);
	}

	rrd_close($rrdtool_pipe);

	/* cleanup  - remove empty arch tables */
	$tables = db_fetch_assoc("SELECT table_name AS name
		FROM information_schema.tables
		WHERE table_schema = SCHEMA()
		AND table_name LIKE 'poller_output_boost_arch_%'");

	if (cacti_count($tables)) {
		foreach($tables as $table) {
			$rows = db_fetch_cell('SELECT count(local_data_id) FROM ' . $table['name']);
			if (is_numeric($rows) && intval($rows) == 0) {
				db_execute('DROP TABLE IF EXISTS ' . $table['name']);
			}
		}
	}

	db_execute("SELECT RELEASE_LOCK('poller_boost');");

	return $total_rows;
}

/* boost_process_local_data_ids - grabs data from the 'poller_output' table and feeds the *completed*
     results to RRDTool for processing
   @arg $last_id - the last id to process
   @arg $rrdtool_pipe - the socket that has been opened for the RRDtool operation */
function boost_process_local_data_ids($last_id, $rrdtool_pipe) {
	global $config, $boost_sock, $boost_timeout, $debug, $get_memory, $memory_used;

	/* cache this call as it takes time */
	static $archive_table   = false;
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

	if ($archive_table === false) {
		$archive_table = boost_get_arch_table_name();
	}

	if ($archive_table === false) {
		cacti_log('Failed to determine archive table', false, 'BOOST');
		return 0;
	}

	$query_string = "SELECT local_data_id, UNIX_TIMESTAMP(time) AS timestamp,
		rrd_name, output
		FROM $archive_table
		WHERE local_data_id <= $last_id
		ORDER BY local_data_id ASC, time ASC, rrd_name ASC";

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
		$last_update    = -1;
		$last_item	= array('local_data_id' => -1, 'timestamp' => -1, 'rrd_name' => '');

		/* we are going to blow away all record if ok */
		$vals_in_buffer = 0;

		boost_timer('results_cycle', BOOST_TIMER_START);

		/* go through each poller_output_boost entries and process */
		foreach ($results as $item) {
			$item['timestamp'] = trim($item['timestamp']);

			/* if the local_data_id changes, we need to flush the buffer
			 * and discover the template for the next RRDfile.
			 */
			if ($local_data_id != $item['local_data_id']) {
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
				$initial_time  = $time;

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
				$values = explode(' ', $value);

				if (!$multi_vals_set) {
					$rrd_field_names = array_rekey(db_fetch_assoc('SELECT
						data_template_rrd.data_source_name,
						data_input_fields.data_name
						FROM (data_template_rrd,data_input_fields)
						WHERE data_template_rrd.data_input_field_id=data_input_fields.id
						AND data_template_rrd.local_data_id=' . $item['local_data_id']), 'data_name', 'data_source_name');

					$rrd_tmpl = '';
				}

				$first_tmpl = true;
				$multi_ok   = false;
				for ($i=0; $i<count($values); $i++) {
					if (preg_match('/^([a-zA-Z0-9_\.-]+):([eE0-9Uu\+\.-]+)$/', $values[$i], $matches)) {
						if (isset($rrd_field_names{$matches[1]})) {
							$multi_ok = true;

							if (trim(read_config_option('path_boost_log')) != '') {
								print "DEBUG: Parsed MULTI output field '" . $matches[0] . "' [map " . $matches[1] . '->' . $rrd_field_names{$matches[1]} . ']' . PHP_EOL;
							}

							if (!$multi_vals_set) {
								if (!$first_tmpl) {
									$rrd_tmpl .= ':';
								}

								$rrd_tmpl  .= $rrd_field_names{$matches[1]};
								$first_tmpl = false;
							}

							if (is_numeric($matches[2]) || ($matches[2] == 'U')) {
								$tv_tmpl[$rrd_field_names[$matches[1]]] = $matches[2];
								$buflen += strlen(':' . $matches[2]);
							} elseif ((function_exists('is_hexadecimal')) && (is_hexadecimal($matches[2]))) {
								$tval = hexdec($matches[2]);
								$tv_tmpl[$rrd_field_names[$matches[1]]] = $tval;
								$buflen += strlen(':' . $tval);
							} else {
								$tv_tmpl[$rrd_field_names[$matches[1]]] = 'U';
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
		boost_timer('results_cycle', BOOST_TIMER_END);

		/* remove the entries from the table */
		boost_timer('delete', BOOST_TIMER_START);
		db_execute("DELETE FROM $archive_table
			WHERE local_data_id <= $last_id");
		boost_timer('delete', BOOST_TIMER_END);
	}

	/* restore original error handler */
	restore_error_handler();

	return sizeof($results);
}

function boost_process_output($local_data_id, $outarray, $rrd_path, $rrd_tmplp, $rrdtool_pipe) {
	$outbuf = '';
	$initial_time = 0;
	if (sizeof($outarray)) {
		foreach($outarray as $tsdata) {
			$outbuf .= ($outbuf != '' ? ' ':'') . implode(':', $tsdata);
		}
	}

	$rrd_tmpl = implode(':', array_keys($rrd_tmplp));

	if (trim(read_config_option('path_boost_log')) != '') {
		print "DEBUG: Updating Local Data Id:'$local_data_id', Template:" . $rrd_tmpl . ', Output:' . $outbuf . PHP_EOL;
	}

	boost_timer('rrdupdate', BOOST_TIMER_START);
	$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $initial_time, $outbuf, $rrdtool_pipe);
	boost_timer('rrdupdate', BOOST_TIMER_END);

	/* check return status for delete operation */
	if (trim($return_value) != 'OK') {
		cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", false, 'BOOST');
	}
}

function log_boost_statistics($rrd_updates) {
	global $start, $boost_stats_log, $verbose;

	/* take time and log performance data */
	$end = microtime(true);

	$cacti_stats = sprintf(
		'Time:%01.4f ' .
		'RRDUpdates:%s',
		round($end-$start,2),
		$rrd_updates);

	/* log to the database */
	set_config_option('stats_boost', $cacti_stats);

	/* log to the logfile */
	cacti_log('BOOST STATS: ' . $cacti_stats , true, 'SYSTEM');

	if (isset($boost_stats_log)) {
		$overhead = boost_timer_get_overhead();
		$outstr = '';
		$timer_cycles = 0;
		foreach($boost_stats_log as $area => $entry) {
			if (isset($entry[BOOST_TIMER_TOTAL])) {
				$outstr .= ($outstr != '' ? ', ' : '') . $area . ':' . round($entry[BOOST_TIMER_TOTAL] - (($overhead * $entry[BOOST_TIMER_CYCLES])/BOOST_TIMER_OVERHEAD_MULTIPLIER), 2);
			}
			$timer_cycles += $entry[BOOST_TIMER_CYCLES];
		}

		if ($outstr != '') {
			$outstr = "RRDUpdates:$rrd_updates, TotalTime:" . round($end - $start, 0) . ', ' . $outstr;
			$timer_overhead = round((($overhead * $timer_cycles)/BOOST_TIMER_OVERHEAD_MULTIPLIER), 0);
			if ($timer_overhead > 0) {
				$outstr .= ", timer_overhead:~$timer_overhead";
			}

			/* log to the database */
			set_config_option('stats_detail_boost', str_replace(',', '', $outstr));

			/* log to the logfile */
			if ($verbose) {
				cacti_log('BOOST DETAIL STATS: ' . $outstr, true, 'SYSTEM');
			}
		}
	}
}

function purge_cached_png_files($forcerun) {
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
	$version = CACTI_VERSION_BRIEF_TEXT;
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

