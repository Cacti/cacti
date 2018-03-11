s<?php
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

/* get_rrdfile_names - this routine returns all of the RRDfiles know to Cacti
     so as to be processed when performin the Daily, Weekly, Monthly and Yearly
     average and peak calculations.
   @returns - (mixed) The RRDfile names */
function get_rrdfile_names() {
	return db_fetch_assoc('SELECT local_data_id, data_source_path FROM data_template_data WHERE local_data_id != 0');
}

/* dsstats_debug - this simple routine print's a standard message to the console
     when running in debug mode.
   @returns - NULL */
function dsstats_debug($message) {
	global $debug;

	if ($debug) {
		print 'DSSTATS: ' . $message . "\n";
	}
}

/* dsstats_get_and_store_ds_avgpeak_values - this routine is a generic routine that takes an time interval as an
     input parameter and then, though additional function calls, reads the RRDfiles for the correct information
     and stores that information into the various database tables.
   @arg $interval - (string) either 'daily', 'weekly', 'monthly', or 'yearly'
   @returns - NULL */
function dsstats_get_and_store_ds_avgpeak_values($interval) {
	global $config;

	$rrdfiles = get_rrdfile_names();
	$stats    = array();

	$use_proxy = (read_config_option('storage_location') ? true:false);

	/* open a pipe to rrdtool for writing and reading */
	if ($use_proxy) {
		$rrdtool_pipe = rrd_init(false);
	}else {
		$process_pipes = dsstats_rrdtool_init();
		$process       = $process_pipes[0];
		$rrdtool_pipe  = $process_pipes[1];
	}

	if (sizeof($rrdfiles)) {
		foreach ($rrdfiles as $file) {
			if ($file['data_source_path'] != '') {
				$rrdfile = str_replace('<path_rra>', $config['rra_path'], $file['data_source_path']);

				$stats[$file['local_data_id']] = dsstats_obtain_data_source_avgpeak_values($rrdfile, $interval, $rrdtool_pipe);
			} else {
				$data_source_name = db_fetch_cell_prepared('SELECT name_cache 
					FROM data_template_data 
					WHERE local_data_id = ?', 
					array($file['local_data_id']));

				cacti_log("WARNING: Data Source '$data_source_name' is damaged and contains no path.  Please delete and re-create both the Graph and Data Source.", false, 'DSSTATS');
			}
		}
	}

	if ($use_proxy) {
		rrd_close($rrdtool_pipe);
	} else {
		dsstats_rrdtool_close($process);
	}

	dsstats_write_buffer($stats, $interval);
}

/* dsstats_write_buffer - this routine provide bulk database insert services to the various tables that store
     the average and peak information for Data Sources.
   @arg $stats_array - (mixed) A multi dimensional array keyed by the local_data_id that contains both
     the average and max values for each internal RRDfile Data Source.
   @arg $interval - (string) 'daily', 'weekly', 'monthly', and 'yearly'.  Used for determining the table to
     update during the dumping of the buffer.
   @returns - NULL */
function dsstats_write_buffer(&$stats_array, $interval) {
	/* initialize some variables */
	$sql_prefix = "INSERT INTO data_source_stats_$interval (local_data_id, rrd_name, average, peak) VALUES";
	$sql_suffix = " ON DUPLICATE KEY UPDATE average=VALUES(average), peak=VALUES(peak)";
	$overhead   = strlen($sql_prefix) + strlen($sql_suffix);
	$outbuf     = '';
	$out_length = 0;
	$i          = 1;
	$max_packet = '264000';

	/* don't attempt to process an empty array */
	if (sizeof($stats_array)) {
		foreach($stats_array as $local_data_id => $stats) {
			/* some additional sanity checking */
			if (is_array($stats) && sizeof($stats)) {
				foreach($stats as $rrd_name => $avgpeak_stats) {
					$outbuf .= ($i == 1 ? ' ':', ') . "('" . $local_data_id . "','" .
						$rrd_name . "','" .
						$avgpeak_stats['AVG'] . "','" .
						$avgpeak_stats['MAX'] . "')";

					$out_length += strlen($outbuf);

					if (($out_length + $overhead) > $max_packet) {
						db_execute($sql_prefix . $outbuf . $sql_suffix);

						$outbuf     = '';
						$out_length = 0;
						$i          = 1;
					} else {
						$i++;
					}
				}
			}
		}
	}

	/* flush the buffer if it still has elements in it */
	if ($out_length > 0) {
		db_execute($sql_prefix . $outbuf . $sql_suffix);
	}
}

/* dsstats_obtain_data_source_avgpeak_values - this routine, given the rrdfile name, interval and RRDtool process
     pipes, will obtain the average a peak values from the RRDfile.  It does this in two steps:

     1) It first reads the RRDfile's information header to obtain all of the internal data source names,
     poller interval and consolidation functions.
     2) Based upon the available consolidation functions, it then grabs either AVERAGE, and MAX, or just AVERAGE
        in the case where the MAX consolidation function is not included in the RRDfile, and then proceeds to
        gather data from the RRDfile for the time period in question.  It allows RRDtool to select the RRA to
        use by simply limiting the number of rows to be returned to the default.

     Once it has all of the information from the RRDfile.  It then decomposes the resulting XML file to it's
     components and then calculates the AVERAGE and MAX values from that data and returns an array to the calling
     function for storage into the respective database table.
   @returns - (mixed) An array of AVERAGE, and MAX values in an RRDfile by Data Source name */
function dsstats_obtain_data_source_avgpeak_values($rrdfile, $interval, $rrdtool_pipe) {
	global $config;

	$use_proxy = (read_config_option('storage_location') ? true:false);

	if ($use_proxy) {
		$file_exists = rrdtool_execute("file_exists $rrdfile", true, RRDTOOL_OUTPUT_BOOLEAN, $rrdtool_pipe, 'DSSTATS');
	}else {
		$file_exists = file_exists($rrdfile);
	}

	/* don't attempt to get information if the file does not exist */
	if ($file_exists) {
		/* high speed or snail speed */
		if ($use_proxy) {
			$info = rrdtool_execute("info $rrdfile", false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'DSSTATS');
		} else {
			$info = dsstats_rrdtool_execute("info $rrdfile", $rrdtool_pipe);
		}

		/* don't do anything if RRDfile did not return data */
		if ($info != '') {
			$info_array = explode("\n", $info);

			$average = false;
			$max     = false;
			$dsnames = array();

			/* figure out whatis in this RRDfile.  Assume CF Uniformity as Cacti does not allow async rrdfiles.
			 * also verify the consolidation functions in the RRDfile for average and max calculations.
			 */
			if (sizeof($info_array)) {
				foreach ($info_array as $line) {
					if (substr_count($line, 'ds[')) {
						$parts = explode(']', $line);
						$parts2 = explode('[', $parts[0]);
						$dsnames[trim($parts2[1])] = 1;
					} else if (substr_count($line, '.cf')) {
						$parts = explode('=', $line);
						if (substr_count($parts[1], 'AVERAGE')) {
							$average = true;
						} elseif (substr_count($parts[1], 'MAX')) {
							$max = true;
						}
					} else if (substr_count($line, 'step')) {
						$parts = explode('=', $line);
						$poller_interval = trim($parts[1]);
					}
				}
			}

			/* create the command syntax to get data */
			/* assume that an RRDfile has not more than 62 data sources */
			$defs     = 'abcdefghijklmnopqrstuvwxyz012345789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$i        = 0;
			$j        = 0;
			$def      = '';
			$xport    = '';
			$dsvalues = array();


			/* escape the file name if on Windows */
			if ($config['cacti_server_os'] != 'unix') {
				$rrdfile = str_replace(':', "\\:", $rrdfile);
			}

			/* setup the export command by parsing throught the internal data source names */
			if (sizeof($dsnames)) {
				foreach ($dsnames as $dsname => $present) {
					if ($average) {
						$def .= 'DEF:' . $defs[$j] . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ':AVERAGE ';
						$xport .= ' XPORT:' . $defs[$j] . $defs[$i];
						$i++;
					}

					if ($max) {
						$def .= 'DEF:' . $defs[$j] . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ':MAX ';
						$xport .= ' XPORT:' . $defs[$j] . $defs[$i];
						$i++;
					}

					if ($i > 50) {
						$j++;
						$i = 0;
					}
				}
			}

			/* change the interval to something RRDtool understands */
			switch($interval) {
				case 'daily':
					$interval = 'day';
					break;
				case 'weekly':
					$interval = 'week';
					break;
				case 'monthly':
					$interval = 'month';
					break;
				case 'yearly':
					$interval = 'year';
					break;
			}

			/* now execute the xport command */
			$xport_cmd = 'xport --start now-1' . $interval . ' --end now ' . trim($def) . ' ' . trim($xport) . ' --maxrows 10';

			if ($use_proxy) {
				$xport_data = rrdtool_execute($xport_cmd, false, RRDTOOL_OUTPUT_STDOUT, $rrdtool_pipe, 'DSSTATS');
			} else {
				$xport_data = dsstats_rrdtool_execute($xport_cmd, $rrdtool_pipe);
			}

			/* initialize the array of return values */
			foreach($dsnames as $dsname => $present) {
				$dsvalues[$dsname]['AVG']    = 0;
				$dsvalues[$dsname]['AVGCNT'] = 0;
				$dsvalues[$dsname]['MAX']    = 0;
			}

			/* process the xport array and return average and peak values */
			if ($xport_data != '') {
				$xport_array = explode("\n", $xport_data);

				if (sizeof($xport_array)) {
					foreach($xport_array as $line) {
						/* we've found an output value, let's cut it to pieces */
						if (substr_count($line, '<v>')) {
							$line = str_replace('<row><t>', '', $line);
							$line = str_replace('</t>',     '', $line);
							$line = str_replace('</v>',     '', $line);
							$line = str_replace('</row>',   '', $line);

							$values = explode('<v>', $line);
							array_shift($values);

							$i = 0;
							/* sum and/or store values for later processing */
							foreach($dsnames as $dsname => $present) {
								if ($average) {
									/* ignore 'NaN' values */
									if (strtolower($values[$i]) != 'nan') {
										$dsvalues[$dsname]['AVG'] += $values[$i];
										$dsvalues[$dsname]['AVGCNT'] += 1;

										if (!$max) {
											if ($values[$i] > $dsvalues[$dsname]['MAX']) {
												$dsvalues[$dsname]['MAX'] = $values[$i];
											}
										}
										$i++;
									}
								}

								if ($max) {
									/* ignore 'NaN' values */
									if (strtolower($values[$i]) != 'nan') {
										if ($values[$i] > $dsvalues[$dsname]['MAX']) {
											$dsvalues[$dsname]['MAX'] = $values[$i];
										}
										$i++;
									}
								}
							}
						}
					}

					/* calculate the average */
					foreach($dsnames as $dsname => $present) {
						if ($dsvalues[$dsname]['AVGCNT'] > 0) {
							$dsvalues[$dsname]['AVG'] = $dsvalues[$dsname]['AVG'] / $dsvalues[$dsname]['AVGCNT'];
						}
					}

					return $dsvalues;
				}
			}
		}
	} else {
		/* only alarm if performing the 'daily' averages */
		if (($interval == 'daily') || ($interval == 'day')) {
			cacti_log("WARNING: File '" . $rrdfile . "' Does not exist", false, 'DSSTATS');
		}
	}
}

/* log_dsstats_statistics - provides generic timing message to both the Cacti log and the settings
     table so that the statistcs can be graphed as well.
   @arg $type - (string) the type of statistics to log, either 'HOURLY', 'DAILY' or 'MAJOR'.
   @returns - null */
function log_dsstats_statistics($type) {
	global $start;

	/* take time and log performance data */
	$end = microtime(true);

	$cacti_stats = sprintf('Time:%01.4f ', round($end-$start,4));

	/* take time and log performance data */
	$start = microtime(true);

	/* log to the database */
	set_config_option('stats_dsstats_' . $type, $cacti_stats);

	/* log to the logfile */
	cacti_log('DSSTATS STATS: Type:' . $type . ', ' . $cacti_stats , true, 'SYSTEM');
}

/* dsstats_error_handler - this routine logs all PHP error transactions
     to make sure they are properly logged.
   @arg $errno - (int) The errornum reported by the system
   @arg $errmsg - (string) The error message provides by the error
   @arg $filename - (string) The filename that encountered the error
   @arg $linenum - (int) The line number where the error occurred
   @arg $vars - (mixed) The current state of PHP variables.
   @returns - (bool) always returns true for some reason */
function dsstats_error_handler($errno, $errmsg, $filename, $linenum, $vars) {
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
		cacti_log('PROGERR: ' . $err, false, 'DSSTATS');
	}

	return;
}

/* dsstats_poller_output - this routine runs in parallel with the cacti poller and
     populates the last and cache tables.  On larger systems, it should be noted that
     the memory overhead for the global arrays, $ds_types, $ds_last, $ds_steps, $ds_multi
     could be serval hundred megabytes.  So, this should be kept in mind when running the
     sizing your system.

     The routine basically loads those 4 structures into memory, and then uses them to
     determine what should be stored in both the Cache and the Last tables.  The 4 structures
     contain the following information:

     $ds_types - The type of data source, keyed by the local_data_id and the rrd_name stored inside
                 of the RRDfile.
     $ds_last  - For the COUNTER, and DERIVE DS types, the last measured and stored value.
     $ds_steps - Records the poller interval for every Data Source so that rates can be stored.
     $ds_multi - For Multi Part responses, stores the mapping of the Data Input Fields to the
                 Internal RRDfile DS names.

     The routine loops through all poller output items and makes decisions relative to the output
     that should be stored into the two tables, and then bulk inserts that information once
     all poller items have been processed.

     The pupose for loading then entire structures into memory at one time is to reduce the latency
     related to multiple database calls.  The author believed that PHP's array hashing algorythms
     would be as fast, if not faster, than MySQL, when considering the transaction overhead and therefore
     chose this method.

   @arg $rrd_update_array - (mixed) The output from the poller output table to be processed by dsstats */
function dsstats_poller_output(&$rrd_update_array) {
	global $config, $ds_types, $ds_last, $ds_steps, $ds_multi;

	/* suppress warnings */
	if (defined('E_DEPRECATED')) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	} else {
		error_reporting(E_ALL);
	}

	/* install the dsstats error handler */
	set_error_handler('dsstats_error_handler');

	/* do not make any calculations unlessed enabled */
	if (read_config_option('dsstats_enable') == 'on') {
		if (sizeof($rrd_update_array) > 0) {
			/* we will assume a smaller than the max packet size.  This would appear to be around the sweat spot. */
			$max_packet       = '264000';

			/* initialize some variables related to the DB inserts */
			$outbuf           = '';
			$sql_cache_prefix = 'INSERT INTO data_source_stats_hourly_cache (local_data_id, rrd_name, time, `value`) VALUES';
			$sql_last_prefix  = 'INSERT INTO data_source_stats_hourly_last (local_data_id, rrd_name, `value`, calculated) VALUES';
			$sql_suffix       = ' ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)';
			$sql_last_suffix  = ' ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `calculated`=VALUES(`calculated`)';
			$overhead         = strlen($sql_cache_prefix) + strlen($sql_suffix);
			$overhead_last    = strlen($sql_last_prefix) + strlen($sql_last_suffix);

			/* determine the keyvalue pair's to decide on how to store data */
			$ds_types = array_rekey(
				db_fetch_assoc('SELECT DISTINCT data_source_name, data_source_type_id, rrd_step
					FROM data_template_rrd
					INNER JOIN data_template_data
					ON data_template_data.local_data_id=data_template_rrd.local_data_id
					WHERE data_template_data.local_data_id>0'),
				'data_source_name', array('data_source_type_id', 'rrd_step')
			);

			/* make the association between the multi-part name value pairs and the RRDfile internal
			 * data source names.
			 */
			$ds_multi = array_rekey(db_fetch_assoc('SELECT DISTINCT data_name, data_source_name
				FROM data_template_rrd
				INNER JOIN data_input_fields
				ON data_input_fields.id=data_template_rrd.data_input_field_id
				WHERE data_template_rrd.data_input_field_id!=0'), 'data_name', 'data_source_name');

			/* required for updating tables */
			$cache_i      = 1;
			$last_i       = 1;
			$out_length   = 0;
			$last_length  = 0;
			$lastbuf      = '';
			$cachebuf     = '';

			/* process each array */
			$n = 1;
			foreach($rrd_update_array as $data_source) {
				if (isset($data_source['times'])) {
					foreach($data_source['times'] as $time => $sample) {
						foreach($sample as $ds => $value) {
							$result['local_data_id'] = $data_source['local_data_id'];
							$result['rrd_name']      = $ds;
							$result['time']          = date('Y-m-d H:i:s', $time);

							if (is_numeric($value)) {
								$result['output'] = $value;
							} elseif ($value == 'U' || strtolower($value) == 'nan') {
								$result['output'] = 'NULL';
							} else {
								$result['output'] = 'NULL';

								cacti_log("ERROR: Output from local_data_id " .
									$data_source['local_data_id'] .
									", for RRDfile DS Name '$ds', is invalid.  " .
									"It outputs was : '" . $value . "'. " .
									"Please check your script or data input method for errors.");
							}

							$lastval = '';

							if (!isset($ds_types[$result['rrd_name']]['data_source_type_id'])) {
								$polling_interval = db_fetch_cell_prepared('SELECT rrd_step
									FROM data_template_data
									WHERE local_data_id = ?',
									array($data_source['local_data_id']));

								$ds_type = db_fetch_cell_prepared('SELECT data_source_type_id
									FROM data_template_rrd
									WHERE local_data_id = ?',
									array($data_source['local_data_id']));
							} else {
								$polling_interval = $ds_types[$result['rrd_name']]['rrd_step'];
								$ds_type          = $ds_types[$result['rrd_name']]['data_source_type_id'];
							}

							switch ($ds_type) {
							case 2:	// COUNTER
								/* get the last values from the database for COUNTER and DERIVE data sources */
								$ds_last = db_fetch_cell_prepared('SELECT SQL_NO_CACHE `value`
									FROM data_source_stats_hourly_last
									WHERE local_data_id = ?
									AND rrd_name = ?', array($result['local_data_id'], $result['rrd_name']));

								if ($ds_last == '' || $ds_last == 'NULL') {
									$currentval = 'NULL';
								} elseif ($result['output'] == 'NULL') {
									$currentval = 'NULL';
								} elseif ($result['output'] >= $ds_last) {
									/* everything is normal */
									$currentval = $result['output'] - $ds_last;
								} else {
									/* possible overflow, see if its 32bit or 64bit */
									if ($ds_last > 4294967295) {
										$currentval = (18446744073709551615 - $ds_last) + $result['output'];
									} else {
										$currentval = (4294967295 - $ds_last) + $result['output'];
									}
								}

								if ($currentval != 'NULL') {
									$currentval = $currentval / $polling_interval;
								}

								$lastval = $result['output'];

								break;
							case 3:	// DERIVE
								/* get the last values from the database for COUNTER and DERIVE data sources */
								$ds_last = db_fetch_cell_prepared('SELECT SQL_NO_CACHE `value`
									FROM data_source_stats_hourly_last
									WHERE local_data_id = ?
									AND rrd_name = ?', array($result['local_data_id'], $result['rrd_name']));

								if ($ds_last == '') {
									$currentval = 'NULL';
								} elseif ($result['output'] != 'NULL') {
									$currentval = ($result['output'] - $ds_last) / $polling_interval;
								} else {
									$currentval = 'NULL';
								}

								$lastval = $result['output'];

								break;
							case 4:	// ABSOLUTE
								if ($result['output'] != 'NULL' &&
									$result['output'] != 'U' &&
									strtolower($result['output']) != 'nan') {

									$currentval = abs($result['output']);
									$lastval    = $currentval;
								} else {
									$currentval = 'NULL';
									$lastval    = $currentval;
								}

								break;
							case 1:	// GAUGE
								if ($result['output'] != 'NULL' &&
									$result['output'] != 'U' &&
									strtolower($result['output']) != 'nan') {

									$currentval = $result['output'];
									$lastval    = $result['output'];
								} else {
									$currentval = 'NULL';
									$lastval    = $currentval;
								}

								break;
							default:
								cacti_log("WARNING: Unknown RRDtool Data Type '" . $ds_types[$result['rrd_name']]['data_source_type_id'] . "', For '" . $result['rrd_name'] . "'", false, 'DSSTATS');

								break;
							}

							/* when doing bulk inserts, the second record is different */
							if ($cache_i == 1) {
								$cache_delim = ' ';
							} else {
								$cache_delim = ', ';
							}

							if ($last_i == 1) {
								$last_delim = ' ';
							} else {
								$last_delim = ', ';
							}

							if ($currentval == '' || $currentval == '-') {
								$currentval = 'NULL';
							}

							/* setupt the output buffer for the cache first */
							$cachebuf .=
								$cache_delim . '(' .
								$result['local_data_id'] . ", '" .
								$result['rrd_name'] . "', '" .
								$result['time'] . "', " .
								$currentval . ')';

							$out_length += strlen($cachebuf);

							/* now do the the last value, if applicable */
							if ($lastval != '') {
								$lastbuf .=
									$last_delim . '(' .
									$result['local_data_id'] . ", '" .
									$result['rrd_name'] . "', " .
									$lastval . ", " .
									$currentval . ')';

								$last_i++;
								$last_length += strlen($lastbuf);
							}

							/* if we exceed our output buffer, it's time to write */
							if ((($out_length + $overhead) > $max_packet) ||
								(($last_length + $overhead_last) > $max_packet )) {
								db_execute($sql_cache_prefix . $cachebuf . $sql_suffix);

								if ($last_i > 1) {
									db_execute($sql_last_prefix . $lastbuf . $sql_last_suffix);
								}

								$cachebuf     = '';
								$lastbuf      = '';
								$out_length   = 0;
								$last_length  = 0;
								$cache_i      = 1;
								$last_i       = 1;
							} else {
								$cache_i++;
							}

							$n++;

							if (($n % 1000) == 0) print '.';
						}
					}
				}
			}

			if ($cache_i > 1) {
				db_execute($sql_cache_prefix . $cachebuf . $sql_suffix);
			}

			if ($last_i > 1) {
				db_execute($sql_last_prefix . $lastbuf . $sql_last_suffix);
			}
		}
	}

	/* restore original error handler */
	restore_error_handler();
}

/* dsstats_boost_bottom - this routine accomodates mass updates after the boost process
     has completed.  The use of boost will require boost version 2.5 or above.  The idea
     if that daily averages will be updated on the boost cycle.
   @returns - NULL */
function dsstats_boost_bottom() {
	global $config;

	if (read_config_option('dsstats_enable') == 'on') {
		include_once($config['base_path'] . '/lib/rrd.php');

		/* run the daily stats. log to database to prevent secondary runs */
		set_config_option('dsstats_last_daily_run_time', date('Y-m-d G:i:s', time()));

		dsstats_get_and_store_ds_avgpeak_values('daily');

		log_dsstats_statistics('DAILY');
	}
}

/* dsstats_poller_command_args - this routine allows DSStats to increase the memory of the
     running script.  This is important for very large sites. */
function dsstats_poller_command_args () {
	dsstats_memory_limit();
}

/* dsstats_memory_limit - this routine increases/decreases the memory available for the script
     It is divided into two functions as the main dsstats poller calls this function directly
     as opposed to the call during the processing of poller output in the main cacti poller.
   @returns - NULL */
function dsstats_memory_limit() {
	ini_set('memory_limit', read_config_option('dsstats_poller_mem_limit') . 'M');
}

/* dsstats_poller_bottom - this routine launches the main dsstats poller so that it might
     calculate the Hourly, Daily, Weekly, Monthly, and Yearly averages.  It is forked independently
     to the Cacti poller after all polling has finished. */
function dsstats_poller_bottom () {
	global $config;

	if (read_config_option('dsstats_enable') == 'on') {
		include_once($config['library_path'] . '/poller.php');

		chdir($config['base_path']);

		$command_string = read_config_option('path_php_binary');
		if (read_config_option('path_dsstats_log') != '') {
			if ($config['cacti_server_os'] == 'unix') {
				$extra_args = '-q ' . $config['base_path'] . '/poller_dsstats.php >> ' . read_config_option('path_dsstats_log') . ' 2>&1';
			} else {
				$extra_args = '-q ' . $config['base_path'] . '/poller_dsstats.php >> ' . read_config_option('path_dsstats_log');
			}
		} else {
			$extra_args = '-q ' . $config['base_path'] . '/poller_dsstats.php';
		}

		exec_background($command_string, $extra_args);
	}
}

/* dsstats_rrdtool_init - this routine provides a bi-directional socket based connection to RRDtool.
     it provides a high speed connection to rrdfile in the case where the traditional Cacti call does
     not when performing fetch type calls.
   @returns - (mixed) An array that includes both the process resource and the pipes to communicate
     with RRDtool. */
function dsstats_rrdtool_init() {
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

/* dsstats_rrdtool_execute - this routine passes commands to RRDtool and returns the information
     back to DSStats.  It is important to note here that RRDtool needs to provide an either 'OK'
     or 'ERROR' response accross the pipe as it does not provide EOF characters to key upon.
     This may not be the best method and may be changed after I have a conversation with a few
     developers.
   @arg $command - (string) The rrdtool command to execute
   @arg $pipes - (array) An array of stdin and stdout pipes to read and write data from
   @returns - (string) The output from RRDtool */
function dsstats_rrdtool_execute($command, $pipes) {
	$stdout = '';

	if ($command == '') return;

	$command .= "\r\n";
	$return_code = fwrite($pipes[0], $command);

	while (!feof($pipes[1])) {
		$stdout .= fgets($pipes[1], 4096);

		if (substr_count($stdout, 'OK')) {
			break;
		}

		if (substr_count($stdout, 'ERROR')) {
			break;
		}
	}

	if (strlen($stdout)) return $stdout;
}

/* dsstats_rrdtool_close - this routine closes the RRDtool process thus also
     closing the pipes.
   @returns - NULL */
function dsstats_rrdtool_close($process) {
	proc_close($process);
}

