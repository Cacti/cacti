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

/**
 * get_rrdfile_names - this routine returns all of the RRDfiles know to Cacti
 *   so as to be processed when performing the Daily, Weekly, Monthly and Yearly
 *   average and peak calculations.
 *
 * @param $thread_id   - (int) The thread to process
 * @param $max_threads - (int) The maximum number of threads
 *
 * @return - (mixed) The RRDfile names
 */
function get_rrdfile_names($thread_id = 1, $max_threads = 1) {
	static $dsrows = array();

	if ($max_threads == 1) {
		return db_fetch_assoc('SELECT dtd.local_data_id, data_source_path, rrd_num AS dsses, GROUP_CONCAT(DISTINCT data_source_name) AS dsnames
			FROM data_template_data AS dtd
			INNER JOIN data_template_rrd AS dtr
			ON dtd.local_data_id = dtr.local_data_id
			LEFT JOIN poller_item AS pi
			ON pi.local_data_id = dtd.local_data_id
			WHERE pi.local_data_id IS NOT NULL
			AND data_source_path != ""
			AND dtd.local_data_id != 0
			GROUP BY dtd.local_data_id');
	} elseif (cacti_sizeof($dsrows)) {
		return $dsrows;
	} else {
		$dsses_total = db_fetch_cell('SELECT COUNT(DISTINCT dtd.local_data_id)
			FROM data_template_data AS dtd
			LEFT JOIN poller_item AS pi
			ON pi.local_data_id = dtd.local_data_id
			WHERE pi.local_data_id IS NOT NULL
			AND data_source_path != ""
			AND dtd.local_data_id != 0');

		$split_size = ceil($dsses_total / $max_threads);
		$start      = (($thread_id - 1) * $split_size) + 1;

		$rows = db_fetch_assoc("SELECT dtd.local_data_id, data_source_path, rrd_num AS dsses, GROUP_CONCAT(DISTINCT data_source_name) AS dsnames
			FROM data_template_data AS dtd
			INNER JOIN data_template_rrd AS dtr
			ON dtd.local_data_id = dtr.local_data_id
			LEFT JOIN poller_item AS pi
			ON pi.local_data_id = dtd.local_data_id
			WHERE pi.local_data_id IS NOT NULL
			AND data_source_path != ''
			AND dtd.local_data_id != 0
			GROUP BY dtd.local_data_id
			LIMIT $start, $split_size");

		$dsrows = $rows;

		if (isset($rows)) {
			return $rows;
		} else {
			return array();
		}
	}
}

/**
 * dsstats_debug - this simple routine prints a standard message to the console
 *   when running in debug mode.
 *
 * @param $message - (string) The message to display
 *
 * @return - NULL
 */
function dsstats_debug($message) {
	global $debug;

	if ($debug) {
		print 'DSSTATS: ' . $message . PHP_EOL;
	}
}

/**
 * dsstats_get_and_store_ds_avgpeak_values - this routine is a generic routine that takes an time interval as an
 *   input parameter and then, though additional function calls, reads the RRDfiles for the correct information
 *   and stores that information into the various database tables.
 *
 * @param $interval  - (string) either 'daily', 'weekly', 'monthly', or 'yearly'
 * @param $thread_id - (int) the dsstats parallel thread id
 *
 * @return - NULL
 */
function dsstats_get_and_store_ds_avgpeak_values($interval, $thread_id = 1) {
	global $config, $type;

	global $total_user, $total_system, $total_real, $total_dsses;
	global $user_time, $system_time, $real_time, $rrd_files;

	$user_time   = 0;
	$system_time = 0;
	$real_time   = 0;
	$dsses       = 0;

	dsstats_debug(sprintf('Processing %s for Thread %s', $interval, $thread_id));

	$max_threads = read_config_option('dsstats_parallel');
	$mode        = read_config_option('dsstats_mode');
	$peak        = read_config_option('dsstats_peak') == 'on' ? true: false;

	if (empty($max_threads)) {
		$max_threads = 1;
		set_config_option('dsstats_parallel', '1');
	}

	$rrdfiles   = get_rrdfile_names($thread_id, $max_threads);
	$stats      = array();
	$rrd_files += cacti_sizeof($rrdfiles);

	$use_proxy  = (read_config_option('storage_location') > 0 ? true : false);

	/* open a pipe to rrdtool for writing and reading */
	if ($use_proxy) {
		$rrd_process = rrd_init(false);
	} else {
		$rrd_process = dsstats_rrdtool_init();
	}

	if (cacti_sizeof($rrdfiles)) {
		foreach ($rrdfiles as $file) {
			$dsses += $file['dsses'];

			if ($file['data_source_path'] != '') {
				$rrdfile       = str_replace('<path_rra>', CACTI_PATH_RRA, $file['data_source_path']);
				$local_data_id = $file['local_data_id'];
				$dsnames       = explode(',', $file['dsnames']);

				$stats[$local_data_id] = dsstats_obtain_data_source_avgpeak_values($local_data_id, $rrdfile, $interval, $mode, $peak, $rrd_process);
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
		rrd_close($rrd_process);
	} else {
		dsstats_rrdtool_close($rrd_process);
	}

	dsstats_write_buffer($stats, $interval, $mode);

	if (!empty($type)) {
		$total_user   += $user_time;
		$total_system += $system_time;
		$total_real   += $real_time;
		$total_dsses  += $dsses;

		set_config_option('dsstats_rrd_system_'  . $type . '_' . $thread_id, $total_system);
		set_config_option('dsstats_rrd_user_'    . $type . '_' . $thread_id, $total_user);
		set_config_option('dsstats_rrd_real_'    . $type . '_' . $thread_id, $total_real);
		set_config_option('dsstats_total_rrds_'  . $type . '_' . $thread_id, $rrd_files);
		set_config_option('dsstats_total_dsses_' . $type . '_' . $thread_id, $total_dsses);
	}
}

/**
 * dsstats_write_buffer - this routine provide bulk database insert services to the various tables that store
 *   the average and peak information for Data Sources.
 *
 * @param array  $stats_array - A multi dimensional array keyed by the local_data_id that contains both
 *   the average and max values for each internal RRDfile Data Source.
 * @param string $interval    - 'daily', 'weekly', 'monthly', and 'yearly'.  Used for determining the table to
 *   update during the dumping of the buffer.
 * @param int    $mode        - The mode of collection legacy '0' or advanced '1'
 *
 * @return - NULL
 */
function dsstats_write_buffer(&$stats_array, $interval, $mode) {
	$outbuf     = '';
	$out_length = 0;
	$i          = 1;
	$max_packet = 256000;

	// Format $stats[ldi][avg|max][rrd_name][metric] = $value
	if ($mode == 1) {
		$sql_prefix = "INSERT INTO data_source_stats_$interval (local_data_id, rrd_name, cf, average, peak, p95n, p90n, p75n, p50n, p25n, sum, stddev, lslslope, lslint, lslcorrel) VALUES";

		$sql_suffix = ' ON DUPLICATE KEY UPDATE
			average=VALUES(average),
			peak=VALUES(peak),
			p95n=VALUES(p95n),
			p90n=VALUES(p90n),
			p75n=VALUES(p75n),
			p50n=VALUES(p50n),
			p25n=VALUES(p25n),
			sum=VALUES(sum),
			stddev=VALUES(stddev),
			lslslope=VALUES(lslslope),
			lslint=VALUES(lslint),
			lslcorrel=VALUES(lslcorrel)';
	} else {
		$sql_prefix = "INSERT INTO data_source_stats_$interval (local_data_id, rrd_name, cf, average, peak) VALUES";

		$sql_suffix = ' ON DUPLICATE KEY UPDATE
			average=VALUES(average),
			peak=VALUES(peak)';
	}

	$overhead = strlen($sql_prefix) + strlen($sql_suffix);

	/* don't attempt to process an empty array */
	/* Format $stats[ldi][avg|max][rrd_name][metric] = $value */
	if (cacti_sizeof($stats_array)) {
		foreach ($stats_array as $local_data_id => $ldi_stats) {
			foreach ($ldi_stats as $cf => $cf_stats) {
				if ($cf == 'avg') {
					$mycf = '0';
				} else {
					$mycf = '1';
				}

				foreach($cf_stats as $rrd_name => $stats) {
					if ($mode == 0) {
						$outbuf .= ($i == 1 ? ' ':', ') . "('" .
							$local_data_id      . "','" .
							$rrd_name           . "','" .
							$mycf               . "','" .
							$stats['avg']       . "','" .
							$stats['peak']      . "')";
					} else {
						$outbuf .= ($i == 1 ? ' ':', ') . "('" .
							$local_data_id      . "','" .
							$rrd_name           . "','" .
							$mycf               . "','" .
							$stats['avg']       . "','" .
							$stats['peak']      . "','" .
							$stats['p95n']      . "','" .
							$stats['p90n']      . "','" .
							$stats['p75n']      . "','" .
							$stats['p50n']      . "','" .
							$stats['p25n']      . "','" .
							$stats['sum']       . "','" .
							$stats['stddev']    . "','" .
							$stats['lslslope']  . "','" .
							$stats['lslint']    . "','" .
							$stats['lslcorrel'] . "')";
					}

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

		/* flush the buffer if it still has elements in it */
		if ($out_length > 0) {
			db_execute($sql_prefix . $outbuf . $sql_suffix);
		}
	}
}

/**
 * dsstats_obtain_data_source_avgpeak_values - this routine, given the rrdfile name, interval and RRDtool process
 *   pipes, will obtain the average a peak values from the RRDfile.  It does this in two steps:
 *
 *   1) It first reads the RRDfile's information header to obtain all of the internal data source names,
 *   poller interval and consolidation functions.
 *   2) Based upon the available consolidation functions, it then grabs either AVERAGE, and MAX, or just AVERAGE
 *      in the case where the MAX consolidation function is not included in the RRDfile, and then proceeds to
 *      gather data from the RRDfile for the time period in question.  It allows RRDtool to select the RRA to
 *      use by simply limiting the number of rows to be returned to the default.
 *
 *   Once it has all of the information from the RRDfile.  It then decomposes the resulting XML file to its
 *   components and then calculates the AVERAGE and MAX values from that data and returns an array to the calling
 *   function for storage into the respective database table.
 *
 * @param $local_data_id - (int) The Cacti Local Data Id
 * @param $rrdfile       - (string) The rrdfile to process
 * @param $interval      - (string) The interval type to process
 * @param $mode          - (int) If we should be collecting legacy stats or not
 * @param $peak          - (bool) If the peak should be take from the max cf
 * @param $rrd_process   - (resource) Pipes to the background RRDtool process
 *
 * @return - (mixed) An array of AVERAGE, and MAX values in an RRDfile by Data Source name
 */
function dsstats_obtain_data_source_avgpeak_values($local_data_id, $rrdfile, $interval, $mode, $peak, $rrd_process) {
	global $config, $user_time, $system_time, $real_time;

	$use_proxy = (read_config_option('storage_location') ? true : false);

	if ($use_proxy) {
		$file_exists = rrdtool_execute("file_exists $rrdfile", true, RRDTOOL_OUTPUT_BOOLEAN, false, 'DSSTATS');
	} else {
		clearstatcache();
		$file_exists = file_exists($rrdfile);
	}

	/* don't attempt to get information if the file does not exist */
	if ($file_exists) {
		/* high speed or snail speed */
		if ($use_proxy) {
			$info = rrdtool_execute("info $rrdfile", false, RRDTOOL_OUTPUT_STDOUT, false, 'DSSTATS');
		} else {
			$info = dsstats_rrdtool_execute("info $rrdfile", $rrd_process);
		}

		/* don't do anything if RRDfile did not return data */
		if ($info != '') {
			$info_array = explode("\n", $info);

			$average = false;
			$max     = false;
			$dsnames = array();

			/* figure out what is in this RRDfile.  Assume CF Uniformity as Cacti does not allow async rrdfiles.
			 * also verify the consolidation functions in the RRDfile for average and max calculations.
			 */
			if (cacti_sizeof($info_array)) {
				foreach ($info_array as $line) {
					if (substr_count($line, 'ds[')) {
						$parts  = explode(']', $line);
						$parts2 = explode('[', $parts[0]);

						$dsnames[trim($parts2[1])] = 1;
					} elseif (substr_count($line, '.cf')) {
						$parts = explode('=', $line);

						if (substr_count($parts[1], 'AVERAGE')) {
							$average = true;
						} elseif (substr_count($parts[1], 'MAX')) {
							$max = true;
						}
					} elseif (substr_count($line, 'step')) {
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
			$command  = '';
			$dsvalues = array();

			/* escape the file name if on Windows */
			if ($config['cacti_server_os'] != 'unix') {
				$rrdfile = str_replace(':', '\\:', $rrdfile);
			}

			/* setup the graph command by parsing through the internal data source names */
			if (cacti_sizeof($dsnames)) {
				foreach ($dsnames as $dsname => $present) {
					$mydata_avg = $defs[$j] . $defs[$i] . '_a';
					$mydata_max = $defs[$j] . $defs[$i] . '_m';

					if ($average) {
						$def .= 'DEF:' . $mydata_avg . '="' . $rrdfile . '":' . $dsname . ':AVERAGE ';
						$command .= " VDEF:{$mydata_avg}_aa=$mydata_avg,AVERAGE PRINT:{$mydata_avg}_aa:{$dsname}-avg_avg=%lf";
						$command .= " VDEF:{$mydata_avg}_am=$mydata_avg,MAXIMUM PRINT:{$mydata_avg}_am:{$dsname}-peak_avg=%lf";
						$i++;
					}

					if ($max && $peak) {
						$def .= 'DEF:' . $mydata_max . '="' . $rrdfile . '":' . $dsname . ':MAX ';
						$command .= " VDEF:{$mydata_max}_ma=$mydata_max,AVERAGE PRINT:{$mydata_max}_ma:{$dsname}-avg_max=%lf";
						$command .= " VDEF:{$mydata_max}_mm=$mydata_max,MAXIMUM PRINT:{$mydata_max}_mm:{$dsname}-peak_max=%lf";
						$i++;
					}

					if ($mode == 1) {
						$pt = array('95', '90', '75', '50', '25');

						if ($average) {
							foreach($pt as $s) {
								$command .= " VDEF:{$mydata_avg}_p{$s}n={$mydata_avg},$s,PERCENTNAN PRINT:{$mydata_avg}_p{$s}n:{$dsname}-p{$s}n_avg=%lf";
							}

							// TOTAL
							$command .= " VDEF:{$mydata_avg}_sum=$mydata_avg,TOTAL PRINT:{$mydata_avg}_sum:{$dsname}-sum_avg=%lf";

							// STDDEV
							$command .= " VDEF:{$mydata_avg}_stddev=$mydata_avg,STDEV PRINT:{$mydata_avg}_stddev:{$dsname}-stddev_avg=%lf";

							// LSLSLOPE
							$command .= " VDEF:{$mydata_avg}_lslslope=$mydata_avg,LSLSLOPE PRINT:{$mydata_avg}_lslslope:{$dsname}-lslslope_avg=%lf";

							// LSLINT
							$command .= " VDEF:{$mydata_avg}_lslint=$mydata_avg,LSLINT PRINT:{$mydata_avg}_lslint:{$dsname}-lslint_avg=%lf";

							// LSLCORREL
							$command .= " VDEF:{$mydata_avg}_lslcorrel=$mydata_avg,LSLCORREL PRINT:{$mydata_avg}_lslcorrel:{$dsname}-lslcorrel_avg=%lf";
						}

						if ($max && $peak) {
							foreach($pt as $s) {
								$command .= " VDEF:{$mydata_max}_p{$s}n={$mydata_max},$s,PERCENTNAN PRINT:{$mydata_max}_p{$s}n:{$dsname}-p{$s}n_max=%lf";
							}

							// TOTAL
							$command .= " VDEF:{$mydata_max}_sum=$mydata_max,TOTAL PRINT:{$mydata_max}_sum:{$dsname}-sum_max=%lf";

							// STDDEV
							$command .= " VDEF:{$mydata_max}_stddev=$mydata_max,STDEV PRINT:{$mydata_max}_stddev:{$dsname}-stddev_max=%lf";

							// LSLSLOPE
							$command .= " VDEF:{$mydata_max}_lslslope=$mydata_max,LSLSLOPE PRINT:{$mydata_max}_lslslope:{$dsname}-lslslope_max=%lf";

							// LSLINT
							$command .= " VDEF:{$mydata_max}_lslint=$mydata_max,LSLINT PRINT:{$mydata_max}_lslint:{$dsname}-lslint_max=%lf";

							// LSLCORREL
							$command .= " VDEF:{$mydata_max}_lslcorrel=$mydata_max,LSLCORREL PRINT:{$mydata_max}_lslcorrel:{$dsname}-lslcorrel_max=%lf";
						}
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

			/* now execute the graph command */
			$stats_cmd = 'graph x --start now-1' . $interval . ' --end now ' . trim($def) . ' ' . trim($command);

			//print $stats_cmd . PHP_EOL . PHP_EOL;

			if ($use_proxy) {
				$xport_data = rrdtool_execute($stats_cmd, false, RRDTOOL_OUTPUT_STDOUT, false, 'DSSTATS');
			} else {
				$xport_data = dsstats_rrdtool_execute($stats_cmd, $rrd_process);
			}

			/* process the xport array and return average and peak values */
			if ($xport_data != '') {
				$xport_array = explode("\n", $xport_data);

				if (cacti_sizeof($xport_array)) {
					foreach ($xport_array as $index => $line) {
						$line = trim($line);

						if ($line == '' || $line == '0x0') {
							continue;
						}

						if ($index > 0) {
							// Catch the last line
							if (substr($line, 0, 2) == 'OK') {
								$line  = trim($line, ' OK');
								$parts = explode(' ', $line);
								//print $line . PHP_EOL;

								foreach ($parts as $line) {
									$sparts = explode(':', $line);

									switch($sparts[0]) {
										case 'u':
											$user_time = $sparts[1];

											break;
										case 's':
											$system_time = $sparts[1];

											break;
										case 'r':
											$real_time = $sparts[1];

											break;
									}
								}

								break;
							} else {
								// Get the rrd_name and the remainder
								$parts    = explode('-', $line);
								$rrd_name = $parts[0];
								$namvalpt = $parts[1];

								// Get the combined variable and value
								$parts    = explode('=', $namvalpt);
								$variable = $parts[0];
								$value    = $parts[1];

								// Get the metric and the mode
								$parts    = explode('_', $variable);
								$metric   = $parts[0];
								$amode    = $parts[1];

								if (stripos($value, 'nan') === false) {
									$dsvalues[$amode][$rrd_name][$metric] = $value;
								} else {
									$dsvalues[$amode][$rrd_name][$metric] = 'NULL';
								}
							}
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

/**
 * dsstats_log_statistics - provides generic timing message to both the Cacti log and the settings
 *   table so that the statistics can be graphed as well.
 *
 * @param $type - (string) the type of statistics to log, either 'HOURLY', 'DAILY', 'BOOST' or 'MAJOR'.
 *
 * @return - NULL
 */
function dsstats_log_statistics($type) {
	global $start;

	dsstats_debug($type);

	if ($type == 'HOURLY') {
		$sub_type = '';
	} elseif ($type == 'MAJOR') {
		$sub_type = 'dchild';
	} elseif ($type == 'DAILY') {
		$sub_type = 'child';
	} elseif ($type == 'BOOST') {
		$sub_type = 'bchild';
	}

	/* take time and log performance data */
	$end = microtime(true);

	if ($sub_type != '') {
		$rrd_user = db_fetch_cell_prepared('SELECT SUM(value)
			FROM settings
			WHERE name LIKE ?',
			array('dsstats_rrd_user_%' . $sub_type . '%'));

		$rrd_system = db_fetch_cell_prepared('SELECT SUM(value)
			FROM settings
			WHERE name LIKE ?',
			array('dsstats_rrd_system_%' . $sub_type . '%'));

		$rrd_real = db_fetch_cell_prepared('SELECT SUM(value)
			FROM settings
			WHERE name LIKE ?',
			array('dsstats_rrd_real_%' . $sub_type . '%'));

		$rrd_files = db_fetch_cell_prepared('SELECT SUM(value)
			FROM settings
			WHERE name LIKE ?',
			array('dsstats_total_rrds_%' . $sub_type . '%'));

		$dsses = db_fetch_cell_prepared('SELECT SUM(value)
			FROM settings
			WHERE name LIKE ?',
			array('dsstats_total_dsses_%' . $sub_type . '%'));

		$processes  = read_config_option('dsstats_parallel');

		$cacti_stats = sprintf('Time:%01.2f Type:%s Threads:%s RRDfiles:%s DSSes:%s RRDUser:%01.2f RRDSystem:%01.2f RRDReal:%01.2f', $end - $start, $type, $processes, $rrd_files, $dsses, $rrd_user, $rrd_system, $rrd_real);

		db_execute("DELETE FROM settings
			WHERE name LIKE 'dsstats_rrd_%$sub_type%'
			OR name LIKE 'dsstats_total_rrds_%$sub_type%'
			OR name LIKE 'dsstats_total_dsses_%$sub_type%'");
	} else {
		$cacti_stats = sprintf('Time:%01.2f Type:%s', $end - $start, $type);
	}

	/* take time and log performance data */
	$start = microtime(true);

	/* log to the database */
	set_config_option('stats_dsstats_' . $type, $cacti_stats);

	/* log to the logfile */
	cacti_log('DSSTATS STATS: ' . $cacti_stats , true, 'SYSTEM');
}

/**
 * dsstats_log_child_stats - logs dsstats child process information
 *
 * @param $type        - (string) The type of child, MAJOR, DAILY, BOOST
 * @param $thread_id   - (int) The parallel thread id
 * @param $total_time  - (int) The total time to collect date
 *
 * @return - NULL
 */
function dsstats_log_child_stats($type, $thread_id, $total_time) {
	$rrd_user = db_fetch_cell_prepared('SELECT SUM(value)
		FROM settings
		WHERE name LIKE ?',
		array('dsstats_rrd_user_%' . $type . '_' . $thread_id . '%'));

	$rrd_system = db_fetch_cell_prepared('SELECT SUM(value)
		FROM settings
		WHERE name LIKE ?',
		array('dsstats_rrd_system_%' . $type . '_' . $thread_id . '%'));

	$rrd_real = db_fetch_cell_prepared('SELECT SUM(value)
		FROM settings
		WHERE name LIKE ?',
		array('dsstats_rrd_real_%' . $type . '_' . $thread_id . '%'));

	$rrd_files = db_fetch_cell_prepared('SELECT SUM(value)
		FROM settings
		WHERE name LIKE ?',
		array('dsstats_total_rrds_%' . $type . '_' . $thread_id . '%'));

	$dsses = db_fetch_cell_prepared('SELECT SUM(value)
		FROM settings
		WHERE name LIKE ?',
		array('dsstats_total_dsses_%' . $type . '_' . $thread_id . '%'));

	$cacti_stats = sprintf('Time:%01.2f Type:%s ProcessNumber:%s RRDfiles:%s DSSes:%s RRDUser:%01.2f RRDSystem:%01.2f RRDReal:%01.2f', $total_time, strtoupper($type), $thread_id, $rrd_files, $dsses, $rrd_user, $rrd_system, $rrd_real);

	cacti_log('DSSTATS CHILD STATS: ' . $cacti_stats, true, 'SYSTEM');
}

/**
 * dsstats_error_handler - this routine logs all PHP error transactions
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
function dsstats_error_handler($errno, $errmsg, $filename, $linenum, $vars = array()) {
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
		cacti_log('PROGERR: ' . $err, false, 'DSSTATS');
	}

	return;
}

/**
 * dsstats_poller_output - this routine runs in parallel with the cacti poller and
 *   populates the last and cache tables.  On larger systems, it should be noted that
 *   the memory overhead for the global arrays, $ds_types, $ds_last, $ds_steps, $ds_multi
 *   could be serval hundred megabytes.  So, this should be kept in mind when running the
 *   sizing your system.
 *
 *   The routine basically loads those 4 structures into memory, and then uses them to
 *   determine what should be stored in both the Cache and the Last tables.  The 4 structures
 *   contain the following information:
 *
 *   $ds_types - The type of data source, keyed by the local_data_id and the rrd_name stored inside
 *               of the RRDfile.
 *   $ds_last  - For the COUNTER, and DERIVE DS types, the last measured and stored value.
 *   $ds_steps - Records the poller interval for every Data Source so that rates can be stored.
 *   $ds_multi - For Multi Part responses, stores the mapping of the Data Input Fields to the
 *               Internal RRDfile DS names.
 *
 *   The routine loops through all poller output items and makes decisions relative to the output
 *   that should be stored into the two tables, and then bulk inserts that information once
 *   all poller items have been processed.
 *
 *   The purpose for loading then entire structures into memory at one time is to reduce the latency
 *   related to multiple database calls.  The author believed that PHP's array hashing algorithms
 *   would be as fast, if not faster, than MySQL, when considering the transaction overhead and therefore
 *   chose this method.
 *
 * @param $rrd_update_array - (mixed) The output from the poller output table to be processed by dsstats
 *
 * @return - NULL
 */
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

	/* do not make any calculations unless enabled */
	if (read_config_option('dsstats_enable') == 'on') {
		if (cacti_sizeof($rrd_update_array) > 0) {
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

			/* determine the keyvalue pairs to decide on how to store data */
			$ds_types = array_rekey(
				db_fetch_assoc('SELECT DISTINCT data_source_name, data_source_type_id, rrd_step, rrd_maximum
					FROM data_template_rrd AS dtr
					INNER JOIN data_template_data AS dtd
					ON dtd.local_data_id = dtr.local_data_id
					WHERE dtd.local_data_id > 0'),
				'data_source_name', array('data_source_type_id', 'rrd_step', 'rrd_maximum')
			);

			/* make the association between the multi-part name value pairs and the RRDfile internal
			 * data source names.
			 */
			$ds_multi = array_rekey(
				db_fetch_assoc('SELECT DISTINCT data_name, data_source_name
					FROM graph_templates_item AS gti
					INNER JOIN data_template_rrd AS dtr
					ON gti.task_item_id = dtr.id
					INNER JOIN data_input_fields AS dif
					ON dif.id = dtr.data_input_field_id
					WHERE dtr.data_input_field_id != 0'),
				'data_name', 'data_source_name'
			);

			/* required for updating tables */
			$cache_i      = 1;
			$last_i       = 1;
			$out_length   = 0;
			$last_length  = 0;
			$lastbuf      = '';
			$cachebuf     = '';

			/* process each array */
			$n = 1;

			foreach ($rrd_update_array as $data_source) {
				if (isset($data_source['times'])) {
					foreach ($data_source['times'] as $time => $sample) {
						foreach ($sample as $ds => $value) {
							$result['local_data_id'] = $data_source['local_data_id'];
							$result['rrd_name']      = $ds;
							$result['time']          = date('Y-m-d H:i:s', $time);

							if (is_numeric($value)) {
								$result['output'] = $value;
							} elseif ($value == 'U' || strtolower($value) == 'nan') {
								$result['output'] = 'NULL';
							} else {
								$result['output'] = 'NULL';

								cacti_log('ERROR: Output from local_data_id ' .
									$data_source['local_data_id'] .
									", for RRDfile DS Name '$ds', is invalid.  " .
									"It outputs was : '" . $value . "'. " .
									'Please check your script or data input method for errors.');
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
								case 6:	// DCOUNTER
									/* get the last values from the database for COUNTER and DERIVE data sources */
									$ds_last = db_fetch_cell_prepared('SELECT SQL_NO_CACHE `value`
									FROM data_source_stats_hourly_last
									WHERE local_data_id = ?
									AND rrd_name = ?',
										array($result['local_data_id'], $result['rrd_name']));

									if ($ds_last == '' || $ds_last == 'NULL') {
										$currentval = 'NULL';
									} elseif ($result['output'] == 'NULL') {
										$currentval = 'NULL';
									} elseif ($result['output'] >= $ds_last) {
										/* everything is normal */
										$currentval = $result['output'] - $ds_last;
									} else {
										$max_value = $ds_types[$result['rrd_name']]['rrd_maximum'];

										/* possible overflow, see if its 32bit or 64bit */
										if ($ds_last > 4294967295) {
											$currentval = (18446744073709551615 - $ds_last) + $result['output'];
										} else {
											$currentval = (4294967295 - $ds_last) + $result['output'];
										}

										if ($max_value != 'U' && $currentval > $max_value) {
											$currentval = 'NULL';
										}
									}

									if ($currentval != 'NULL') {
										$currentval = $currentval / $polling_interval;

										if ($ds_type == 6) {
											$currentval = round($currentval, 0);
										}
									}

									$lastval = $result['output'];

									if ($ds_type == 6) {
										$lastval = round($lastval, 0);
									}

									break;
								case 3:	// DERIVE
								case 7:	// DDERIVE
									/* get the last values from the database for COUNTER and DERIVE data sources */
									$ds_last = db_fetch_cell_prepared('SELECT SQL_NO_CACHE `value`
									FROM data_source_stats_hourly_last
									WHERE local_data_id = ?
									AND rrd_name = ?', array($result['local_data_id'], $result['rrd_name']));

									if ($ds_last == '') {
										$currentval = 'NULL';
									} elseif ($result['output'] != 'NULL') {
										$currentval = ($result['output'] - $ds_last) / $polling_interval;

										if ($ds_type == 7) {
											$currentval = round($currentval, 0);
										}
									} else {
										$currentval = 'NULL';
									}

									$lastval = $result['output'];

									if ($ds_type == 7) {
										$lastval = round($lastval, 0);
									}

									break;
								case 4:	// ABSOLUTE
									if ($result['output']          != 'NULL' &&
										$result['output']             != 'U' &&
										strtolower($result['output']) != 'nan') {
										$currentval = abs($result['output']);
										$lastval    = $currentval;
									} else {
										$currentval = 'NULL';
										$lastval    = $currentval;
									}

									break;
								case 1:	// GAUGE
									if ($result['output']          != 'NULL' &&
										$result['output']             != 'U' &&
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

							/* setup the output buffer for the cache first */
							$cachebuf .=
								$cache_delim . '(' .
								$result['local_data_id'] . ", '" .
								$result['rrd_name'] . "', '" .
								$result['time'] . "', " .
								$currentval . ')';

							$out_length += strlen($cachebuf);

							/* now do the last value, if applicable */
							if ($lastval != '') {
								$lastbuf .=
									$last_delim . '(' .
									$result['local_data_id'] . ", '" .
									$result['rrd_name'] . "', " .
									$lastval . ', ' .
									$currentval . ')';

								$last_i++;
								$last_length += strlen($lastbuf);
							}

							/* if we exceed our output buffer, it's time to write */
							if ((($out_length + $overhead) > $max_packet) ||
								(($last_length + $overhead_last) > $max_packet)) {
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

							if (($n % 1000) == 0) {
								print '.';
							}
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

/**
 * dsstats_boost_bottom - this routine accommodates mass updates after the boost process
 *   has completed.  The use of boost will require boost version 2.5 or above.  The idea
 *   if that daily averages will be updated on the boost cycle.
 *
 * @return - NULL
 */
function dsstats_boost_bottom() {
	global $config;

	global $total_user, $total_system, $total_real, $total_dsses;

	$total_user   = 0;
	$total_system = 0;
	$total_real   = 0;
	$total_dsses  = 0;

	if (read_config_option('dsstats_enable') == 'on') {
		include_once(CACTI_PATH_LIBRARY . '/rrd.php');

		/* run the daily stats. log to database to prevent secondary runs */
		set_config_option('dsstats_last_daily_run_time', date('Y-m-d G:i:s', time()));

		/* run the daily stats */
		dsstats_launch_children('bmaster');

		/* Wait for all processes to continue */
		while ($running = dsstats_processes_running('bmaster')) {
			dsstats_debug(sprintf('%s Processes Running, Sleeping for 2 seconds.', $running));
			sleep(2);
		}

		dsstats_get_and_store_ds_avgpeak_values('daily');

		dsstats_log_statistics('DAILY');
	}
}

/**
 * dsstats_memory_limit - this routine increases/decreases the memory available for the script
 *   It is divided into two functions as the main dsstats poller calls this function directly
 *   as opposed to the call during the processing of poller output in the main cacti poller.
 *
 * @return - NULL
 */
function dsstats_memory_limit() {
	ini_set('memory_limit', read_config_option('dsstats_poller_mem_limit') . 'M');
}

/**
 * dsstats_poller_bottom - this routine launches the main dsstats poller so that it might
 *   calculate the Hourly, Daily, Weekly, Monthly, and Yearly averages.  It is forked independently
 *   to the Cacti poller after all polling has finished.
 *
 * @return - NULL
 */
function dsstats_poller_bottom() {
	global $config;

	if (read_config_option('dsstats_enable') == 'on') {
		include_once(CACTI_PATH_LIBRARY . '/poller.php');

		chdir(CACTI_PATH_BASE);

		$command_string = read_config_option('path_php_binary');

		if (read_config_option('path_dsstats_log') != '') {
			if ($config['cacti_server_os'] == 'unix') {
				$extra_args = '-q ' . CACTI_PATH_BASE . '/poller_dsstats.php >> ' . read_config_option('path_dsstats_log') . ' 2>&1';
			} else {
				$extra_args = '-q ' . CACTI_PATH_BASE . '/poller_dsstats.php >> ' . read_config_option('path_dsstats_log');
			}
		} else {
			$extra_args = '-q ' . CACTI_PATH_BASE . '/poller_dsstats.php';
		}

		exec_background($command_string, $extra_args);
	}
}

/**
 * dsstats_rrdtool_init - this routine provides a bi-directional socket based connection to RRDtool.
 *   it provides a high speed connection to rrdfile in the case where the traditional Cacti call does
 *   not when performing fetch type calls.
 *
 * @return - (mixed) An array that includes both the process resource and the pipes to communicate
 *   with RRDtool.
 */
function dsstats_rrdtool_init() {
	global $config;

	if ($config['cacti_server_os'] == 'unix') {
		$fds = array(
			0 => array('pipe', 'r'), // stdin
			1 => array('pipe', 'w'), // stdout
			2 => array('file', '/dev/null', 'a') // stderr
		);
	} else {
		$fds = array(
			0 => array('pipe', 'r'), // stdin
			1 => array('pipe', 'w'), // stdout
			2 => array('file', 'nul', 'a') // stderr
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
 * dsstats_rrdtool_execute - this routine passes commands to RRDtool and returns the information
 *   back to DSStats.  It is important to note here that RRDtool needs to provide an either 'OK'
 *   or 'ERROR' response across the pipe as it does not provide EOF characters to key upon.
 *   This may not be the best method and may be changed after I have a conversation with a few
 *   developers.
 *
 * @param $command      - (string) The rrdtool command to execute
 * @param $rrd_process  - (array) An array of stdin and stdout pipes to read and write data from
 *
 * @returns - (string) The output from RRDtool
 */
function dsstats_rrdtool_execute($command, $rrd_process) {
	static $broken = false;

	$use_proxy  = (read_config_option('storage_location') > 0 ? true : false);

	if ($use_proxy) {
		$output = rrdtool_function_execute($command, false, RRDTOOL_OUTPUT_STDOUT, false, 'DSSTATS');
	} else {
		$stdout = '';

		if ($command == '') {
			return;
		}

		$pipes = $rrd_process[1];

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
			cacti_log('ERROR: RRDtool was unable to fork.  Likely RRDtool can not be found or system out of resources.  Blocking subsequent messages.', false, 'POLLER');
			$broken = true;
		}
	}

	if (strlen($stdout)) {
		return $stdout;
	}
}

/**
 * dsstats_rrdtool_close - this routine closes the RRDtool process thus also
 *   closing the pipes.
 *
 * @param  mixed $process
 *
 * @return - null
 */
function dsstats_rrdtool_close($rrd_process) {
	if (is_array($rrd_process)) {
		proc_terminate($rrd_process[0]);
		proc_close($rrd_process[0]);
	}
}

/**
 * dsstats_launch_children - this function will launch collector children based upon
 *   the maximum number of threads and the process type
 *
 * @param  $type - (string) The process type
 *
 * @return - null
 */
function dsstats_launch_children($type) {
	global $config, $debug;

	$processes = read_config_option('dsstats_parallel');

	if (empty($processes)) {
		$processes = 1;
	}

	$php_binary = read_config_option('path_php_binary');

	dsstats_debug("About to launch $processes processes.");

	$sub_type = dsstats_get_subtype($type);

	for ($i = 1; $i <= $processes; $i++) {
		dsstats_debug(sprintf('Launching DSStats Process Number %s for Type %s', $i, $type));

		cacti_log(sprintf('NOTE: Launching DSStats Process Number %s for Type %s', $i, $type), false, 'BOOST', POLLER_VERBOSITY_MEDIUM);

		exec_background($php_binary, CACTI_PATH_BASE . "/poller_dsstats.php --type=$sub_type --child=$i" . ($debug ? ' --debug':''));
	}

	sleep(2);
}

/**
 * dsstats_get_subtype - this function determine the applicable
 *   sub-type (child name) and return if based upon a type
 *
 * @param $type - (string) The process type
 *
 * @return - (string) The sub type
 */
function dsstats_get_subtype($type) {
	switch($type) {
		case 'master':
			return 'child';

			break;
		case 'bmaster':
			return 'bchild';

			break;
		case 'dmaster':
			return 'dchild';

			break;
	}
}

/**
 * dsstats_kill_running_processes - this function is part of an interrupt
 *   handler to kill children processes when the parent is killed
 *
 * @return - NULL
 */
function dsstats_kill_running_processes() {
	global $type;

	if ($type == 'bmaster') {
		$processes = db_fetch_assoc_prepared('SELECT *
			FROM processes
			WHERE tasktype = "dsstats"
			AND taskname = "bchild"
			AND pid != ?',
			array(getmypid()));
	} else {
		$processes = db_fetch_assoc_prepared('SELECT *
			FROM processes
			WHERE tasktype = "dsstats"
			AND taskname IN ("child", "dchild")
			AND pid != ?',
			array(getmypid()));
	}

	if (cacti_sizeof($processes)) {
		foreach ($processes as $p) {
			cacti_log(sprintf('WARNING: Killing DSStats %s PID %d due to another due to signal or overrun.', ucfirst($p['taskname']), $p['pid']), false, 'BOOST');
			posix_kill($p['pid'], SIGTERM);

			unregister_process($p['tasktype'], $p['taskname'], $p['taskid'], $p['pid']);
		}
	}
}

/**
 * dsstats_processes_running - given a type, determine the number
 *   of sub-type or children that are currently running
 *
 * @param $type - (string) The process type
 *
 * @return - (int) The number of running processes
 */
function dsstats_processes_running($type) {
	$sub_type = dsstats_get_subtype($type);

	$running = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM processes
		WHERE tasktype = "dsstats"
		AND taskname = ?',
		array($sub_type));

	if ($running == 0) {
		return 0;
	}

	return $running;
}
