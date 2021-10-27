<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

/* setup constants */
define('SPIKE_METHOD_STDDEV',   1);
define('SPIKE_METHOD_VARIANCE', 2);
define('SPIKE_METHOD_FILL',     4);
define('SPIKE_METHOD_FLOAT',    3);
define('SPIKE_METHOD_ABSOLUTE', 5);

class spikekill {
	/* setup defaults */
	private $std_kills = false;
	private $var_kills = false;
	private $out_kills = false;
	private $abs_kills = false;
	private $out_set   = false;
	private $username  = '';
	private $user      = '';
	private $user_info = array();

	// Required variables
	var $rrdfile   = '';
	var $method    = ''; /* starts as string, changed into int */
	var $avgnan    = '';
	var $stddev    = '';
	var $out_start = '';
	var $out_end   = '';
	var $outliers  = '';
	var $percent   = '';
	var $numspike  = '';
	var $dsfilter  = ''; /* starts as string, changed into array */
	var $absmax    = '';

	// Overrideable
	var $html      = true;
	var $backup    = false;
	var $debug     = false;
	var $dryrun    = false;

	// Defaults from cacti settings
	private $dmethod   = 1;
	private $dnumspike = 10;
	private $dstddev   = 10;
	private $dpercent  = 500;
	private $doutliers = 5;
	private $davgnan   = 'last';
	private $ddsfilter = '';
	private $dabsmax   = 1.25e10; // 100 gigabit represented in bytes

	// Internal globals
	private $tempdir        = '';
	private $seed           = '';
	private $strout         = '';
	private $ds_min         = '';
	private $ds_max         = '';
	private $total_kills    = 0;
	private $list_of_spikes = Array(); /* assemble a list of all kills found that
			will then be sorted and trimmed, thereby allowing us to kill
			the most egregious spikes first */

	private $rra_cf      = array();
	private $ds_name     = array();
	private $rra_pdp     = array();
	private $step        = 0;

	// For error handling
	private $errors = array();

	public function __construct($rrdfile = '', $method = '', $avgnan = '', $stddev = '',
		$out_start = '', $out_end = '', $outliers = '', $percent = '', $numspike = '',
		$dsfilter = '', $absmax = '') {

		$this->username  = 'OsUser:' . get_current_user();
		$this->user_info = array();

		if (isset($_SESSION['sess_user_id'])) {
			$this->user = $_SESSION['sess_user_id'];

			/* confirm the user id is accurate */
			$this->user_info = db_fetch_row_prepared('SELECT id, username
				FROM user_auth
				WHERE id = ?',
				array($this->user));

			if (cacti_sizeof($this->user_info)) {
				$this->username = 'CactiUser:' . $this->user_info['username'];
			}
		}

		/* get variables from construction and populate them into the class */
		if ($rrdfile != '') {
			$this->rrdfile = $rrdfile;
		}

		if ($method != '') {
			$this->method = $method;
		}

		if ($avgnan != '') {
			$this->avgnan = $avgnan;
		}

		if ($stddev != '') {
			$this->stddev = $stddev;
		}

		if ($out_start != '') {
			$this->out_start = $out_start;
		}

		if ($out_end != '') {
			$this->out_end = $out_end;
		}

		if ($outliers != '') {
			$this->outliers = $outliers;
		}

		if ($percent != '') {
			$this->percent = $percent;
		}

		if ($numspike != '') {
			$this->numspike = $numspike;
		}

		if ($dsfilter != '') {
			$this->dsfilter = $dsfilter;
		}

		if ($absmax != '') {
			$this->absmax = $absmax;
		}

		/* if there are global options in the database, then override the static
		     defaults stored in the class.  The precedence order goes something like
		     this: static default in the class -> overridden by the global default
		     in the database, if exists -> overridden by the user's setting, if
		     exists -> overridden by an argument provided during class creation */
		$dmethod = read_config_option('spikekill_method', true);
		if (isset($dmethod)) {
			$this->dmethod = $dmethod;
		}
		$dnumspike = read_config_option('spikekill_number', true);
		if (isset($dnumspike)) {
			$this->dnumspike = $dnumspike;
		}
		$dstddev = read_config_option('spikekill_deviations', true);
		if (isset($dstddev)) {
			$this->dstddev = $dstddev;
		}
		$dpercent = read_config_option('spikekill_percent', true);
		if (isset($dpercent)) {
			$this->dpercent = $dpercent;
		}
		$doutliers = read_config_option('spikekill_outliers', true);
		if (isset($doutliers)) {
			$this->doutliers = $doutliers;
		}
		$davgnan = read_config_option('spikekill_avgnan', true);
		if (isset($davgnan)) {
			$this->davgnan = $davgnan;
		}
		$ddsfilter = read_config_option('spikekill_dsfilter',true);
		if (isset($ddsfilter)) {
			$this->ddsfilter = $ddsfilter;
		}
		$dabsmax = read_config_option('spikekill_absmax',true);
		if (isset($dabsmax)) {
			$this->dabsmax = $dabsmax;
		}

		return true;
	}

	public function __destruct() {
		return true;
	}

	private function set_error($string) {
		$this->errors[] = $string;
	}

	private function is_error_set() {
		return cacti_sizeof($this->errors);
	}

	public function get_errors() {
		$output = '';

		if (cacti_sizeof($this->errors)) {
			foreach($this->errors as $error) {
				$output .= ($output != '' ? ($this->html ? '<br>':"\n"):'') . $error;
			}
		}

		return $output;
	}

	public function get_output($html = true) {
		return $this->strout;
	}

	private function initialize_spikekill() {
		/* additional error check */
		if ($this->rrdfile == '') {
			$this->set_error("FATAL: You must specify an RRDfile!");
		}

		if (!file_exists($this->rrdfile)) {
			$this->set_error("FATAL: File '$this->rrdfile' does not exist.");
		} elseif (!is_writable($this->rrdfile)) {
			$this->set_error("FATAL: File '$this->rrdfile' is not writeable by '" . get_execution_user() . "'.");
		}

		/* check if the user has options in the database */
		$umethod   = read_user_setting('spikekill_method', $this->dmethod);
		$unumspike = read_user_setting('spikekill_number', $this->dnumspike);
		$ustddev   = read_user_setting('spikekill_deviations', $this->dstddev);
		$upercent  = read_user_setting('spikekill_percent', $this->dpercent);
		$uoutliers = read_user_setting('spikekill_outliers', $this->doutliers);
		$uavgnan   = read_user_setting('spikekill_avgnan', $this->davgnan);
		$udsfilter = read_user_setting('spikekill_dsfilter', $this->ddsfilter);
		$uabsmax   = read_user_setting('spikekill_absmax', $this->dabsmax);
		$udsfilter = read_user_setting('spikekill_dsfilter', $this->ddsfilter);

		/* if values were not specified when the class was created, then pull the
		   correct values from the default (which came from the user's settings,
		   or the global settings, or statically defined by the class */
		if ($this->avgnan == '') {
			$this->avgnan = $uavgnan;
		}
		if ($this->method == '') {
			$this->method = $umethod;
		}
		if ($this->numspike == '') {
			$this->numspike = $unumspike;
		}
		if ($this->stddev == '') {
			$this->stddev = $ustddev;
		}
		if ($this->percent == '') {
			$this->percent = $upercent;
		}
		if ($this->outliers == '') {
			$this->outliers = $uoutliers;
		}
		if ($this->dsfilter == '') {
			$this->dsfilter = $udsfilter;
		}
		if ($this->absmax == '') {
			$this->absmax = $uabsmax;
		}

		if (!is_numeric($this->stddev) || ($this->stddev < 1)) {
			$this->set_error("FATAL: Standard Deviation must be a positive integer.");
		}

		if ($this->method == 'float' || $this->method == 'fill') {
			if (!is_numeric($this->out_start)) {
				$this->out_start = strtotime($this->out_start);
			}

			if (!is_numeric($this->out_end)) {
				$this->out_end = strtotime($this->out_end);
			}

			if ($this->out_start === false || $this->out_end === false) {
				$this->set_error("FATAL: The outlier-start and outlier-end arguments must be in the format of YYYY-MM-DD HH:MM.");
			}

			if (!is_numeric($this->outliers) || ($this->outliers < 1)) {
				$this->set_error("FATAL: The number of outliers to exlude must be a positive integer.");
			}

			$this->out_set = true;
		}

		if ($this->method == 'fill' && $this->avgnan == 'nan') {
			$this->set_error("FATAL: Filling NaN gaps with NaN is not useful. Cowardly refusing to proceed.");
		}

		if ($this->percent != '') {
			if (is_numeric($this->percent) && $this->percent > 0) {
				$this->percent = $this->percent/100;
			} else {
				$this->set_error("FATAL: Percent deviation must be a positive floating point number.");
			}
		}

		if (!$this->numspike != '') {
			if (!is_numeric($this->numspike) || ($this->numspike < 1)) {
				$this->set_error("FATAL: Number of spikes to remove must be a positive integer");
			}
		}

		if ((!empty($this->out_start) && empty($this->out_end)) || (!empty($this->out_end) && empty($this->out_start))) {
			$this->set_error("FATAL: Outlier time range requires outlier-start and outlier-end to be specified.");
		}

		if (!empty($this->out_start)) {
			if ($this->out_start >= $this->out_end) {
				$this->set_error("FATAL: Outlier time range requires outlier-start to be less than outlier-end.");
			}
		}

		switch($this->method) {
			/* the order of the following case statements reflects the order in the spikekill menu in the GUI. */
			case 'stddev':
				$this->method = SPIKE_METHOD_STDDEV;
				break;
			case 'variance':
				$this->method = SPIKE_METHOD_VARIANCE;
				break;
			case 'fill':
				$this->method = SPIKE_METHOD_FILL;
				break;
			case 'float':
				$this->method = SPIKE_METHOD_FLOAT;
				break;
			case 'absolute':
				$this->method = SPIKE_METHOD_ABSOLUTE;
				break;
			default:
				$this->set_error("FATAL: You must specify either 'stddev', 'variance', 'float', 'fill', or 'absolute' as methods.");
		}

		if ($this->method == SPIKE_METHOD_FLOAT && empty($this->out_start)) {
			$this->set_error("FATAL: The 'float' removal method requires the specification of a start and end date.");
		}

		if ($this->method == SPIKE_METHOD_FILL && empty($this->out_start)) {
			$this->set_error("FATAL: The 'gapfill' removal method requires the specification of a start and end date.");
		}

		switch($this->avgnan) {
			case 'avg':
			case 'last':
			case 'nan':
				break;
			default:
				$this->set_error("FATAL: You must specify either 'last', 'avg' or 'nan' as a replacement method.");
		}

		/* convert dsfilter from string to array */
		$this->dsfilter = $this->parse_ds($this->dsfilter);
		if ($this->dsfilter === false) {
			cacti_log("FATAL: Can not parse dsfilter.", false, 'SPIKEKILL');
			$this->set_error("FATAL: Can not parse dsfilter.");
		}

		return false;
	}

	public function remove_spikes() {
		global $config;

		$this->initialize_spikekill();

		/* check to see if initialization caused any errors */
		if ($this->is_error_set()) {
			return false;
		}

		/* determine the temporary file name */
		$this->seed = mt_rand();

		if ($config['cacti_server_os'] == 'win32') {
			$this->tempdir  = read_config_option('spikekill_backupdir');
			$xmlfile = $this->tempdir . '/' . str_replace('.rrd', '', basename($this->rrdfile)) . '.dump.' . $this->seed;
			$bakfile = $this->tempdir . '/' . str_replace('.rrd', '', basename($this->rrdfile)) . '.backup.' . $this->seed . '.rrd';
		} else {
			$this->tempdir = read_config_option('spikekill_backupdir');
			$xmlfile = $this->tempdir . '/' . str_replace('.rrd', '', basename($this->rrdfile)) . '.dump.' . $this->seed;
			$bakfile = $this->tempdir . '/' . str_replace('.rrd', '', basename($this->rrdfile)) . '.backup.' . $this->seed . '.rrd';
		}

		$this->strout = '';

		if (!empty($this->out_start) && !$this->dryrun) {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') . "NOTE: Removing Outliers in Range and Replacing with {$this->avgnan}" . ($this->html ? "</p>\n":"\n");
		}

		$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') . "NOTE: Creating XML file '$xmlfile' from '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");

		switch ($this->method) {
			case SPIKE_METHOD_STDDEV:
				$mm  = 'StdDev';
				$mes = "$this->username, File:" . basename($this->rrdfile) . ", Method:$mm, StdDevs:$this->stddev, AvgNan:$this->avgnan, Kills:$this->numspike, Outliers:$this->outliers";
				break;
			case SPIKE_METHOD_VARIANCE:
				$mm  = 'Variance';
				$mes = "$this->username, File:" . basename($this->rrdfile) . ", Method:$mm, AvgNan:$this->avgnan, Kills:$this->numspike, Outliers:$this->outliers, Percent:" . round($this->percent*100,2) . "%";
				break;
			case SPIKE_METHOD_FLOAT:
				$mm  = 'RangeFloat';
				$mes = "$this->username, File:" . basename($this->rrdfile) . ", Method:$mm, OutStart:$this->out_start, OutEnd:$this->out_end, AvgNan:$this->avgnan";
				break;
			case SPIKE_METHOD_FILL:
				$mm  = 'GapFill';
				$mes = "$this->username, File:" . basename($this->rrdfile) . ", Method:$mm, OutStart:$this->out_start, OutEnd:$this->out_end, AvgNan:$this->avgnan";
				break;
			case SPIKE_METHOD_ABSOLUTE:
				$mm  = 'Absolute';
				$mes = "$this->username, File:" . basename($this->rrdfile) . ", Method:$mm, AvgNan:$this->avgnan, AbsMax:$this->absmax";
				break;
			default:
				$mm  = 'Undefined';
				$mes = "$this->username, File:" . basename($this->rrdfile) . ", Method:$mm";
		}

		cacti_log($mes, false, 'SPIKEKILL');

		/* execute the dump command */
		shell_exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')) . ' dump ' . cacti_escapeshellarg($this->rrdfile) . ' > ' . cacti_escapeshellarg($xmlfile));

		/* read the xml file into an array*/
		if (file_exists($xmlfile)) {
			$output = file($xmlfile);

			/* remove the temp file */
			unlink($xmlfile);
		} else {
			$this->set_error("FATAL: RRDtool Command Failed.  Please verify that the RRDtool path is valid in Settings->Paths!");
			return false;
		}

		/* backup the rrdfile if requested */
		if ($this->backup && !$this->dryrun) {
			if (copy($this->rrdfile, $bakfile)) {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') . "NOTE: RRDfile '$this->rrdfile' backed up to '$bakfile'" . ($this->html ? "</p>\n":"\n");
			} else {
				$this->set_error("FATAL: RRDfile Backup of '$this->rrdfile' to '$bakfile' FAILED!");
				return false;
			}
		}

		if ($this->is_error_set()) {
			return false;
		}

		/* process the xml file and remove all comments */
		$output = $this->removeComments($output);

		/* Read all the rra's ds values and obtain the following pieces of information from each
		   rra archive.

		   * numsamples - The number of 'valid' non-nan samples
		   * sumofsamples - The sum of all 'valid' samples.
		   * average - The average of all samples
		   * standard_deviation - The standard deviation of all samples
		   * max_value - The maximum value of all samples
		   * min_value - The minimum value of all samples
		   * max_cutoff - Any value above this value will be set to the average.
		   * min_cutoff - Any value lower than this value will be set to the average.

		   This will end up being a n-dimensional array as follows:
		   rra[x][ds#]['totalsamples'];
		   rra[x][ds#]['numsamples'];
		   rra[x][ds#]['sumofsamples'];
		   rra[x][ds#]['average'];
		   rra[x][ds#]['stddev'];
		   rra[x][ds#]['max_value'];
		   rra[x][ds#]['min_value'];
		   rra[x][ds#]['max_cutoff'];
		   rra[x][ds#]['min_cutoff'];

		   There will also be a secondary array created with the actual samples.  This
		   array will be used to calculate the standard deviation of the sample set.
		   samples[rra_num][ds_num][timestamp];

		   Also track the min and max value for each ds and store it into the two
		   arrays: ds_min[ds#], ds_max[ds#].

		   The we don't need to know the type of rra, only it's number for this analysis
		   the same applies for the ds' as well.
		*/
		$rra     = array();
		$this->rra_cf  = array();
		$this->rra_pdp = array();

		$rra_num = 0;
		$ds_num  = 0;

		$this->total_kills = 0;

		$in_rra  = false;
		$in_db   = false;

		$this->ds_min  = array();
		$this->ds_max  = array();

		$this->ds_name = array();

		/**
		 * perform a first pass on the array and do the following:
		 *
		 * 1) Get the number of good samples per ds
		 * 2) Get the sum of the samples per ds
		 * 3) Get the max and min values for all samples
		 * 4) Build both the rra and sample arrays
		 * 5) Get each ds' min and max values
		 *
		 */
		if (cacti_sizeof($output)) {
			foreach($output as $line) {
				if (substr_count($line, '<v>')) {
					$linearray = explode('<v>', $line);

					/* get the timestamp */
					$timestamp_part = $linearray[0];
					if (strpos($timestamp_part, '<timestamp>') !== false) {
						$timestamp_part = str_replace('<row><timestamp>', '', $timestamp_part);
						$timestamp_part = str_replace('</timestamp>', '', $timestamp_part);
						$timestamp = trim($timestamp_part);
					} else {
						$timestamp = 0;
					}

					/* discard the first piece of the exploded line */
					array_shift($linearray);
					$ds_num = 0;
					foreach($linearray as $dsvalue) {
						/* peel off garbage */
						$dsvalue = trim(str_replace('</row>', '', str_replace('</v>', '', $dsvalue)));

						/* check for outlier territory */
						/* the purpose of this check is to exempt the selected time
						   range (if there is one) while calculating statistics,
						   because obviously, the selected time range is highly
						   likely to have a spike in it that we're attempting
						   to overwrite, so we don't want the spike to throw
						   off the math. */
						if ($timestamp > 0) {
							if (!empty($this->out_start) && $timestamp < $this->out_start) {
								$process = true;
							} elseif (!empty($this->out_end) && $timestamp > $this->out_end) {
								$process = true;
							} elseif (empty($this->out_start)) {
								$process = true;
							} else {
								$process = false;
							}
						} else {
							$process = true;
						}

						if (strtolower($dsvalue) != 'nan' && $process) {
							if (!isset($rra[$rra_num][$ds_num]['numsamples'])) {
								$rra[$rra_num][$ds_num]['numsamples'] = 1;
							} else {
								$rra[$rra_num][$ds_num]['numsamples']++;
							}

							if (!isset($rra[$rra_num][$ds_num]['sumofsamples'])) {
								$rra[$rra_num][$ds_num]['sumofsamples'] = $dsvalue;
							} elseif (is_numeric($dsvalue)) {
								$rra[$rra_num][$ds_num]['sumofsamples'] += $dsvalue;
							}

							if (!isset($rra[$rra_num][$ds_num]['max_value'])) {
								$rra[$rra_num][$ds_num]['max_value'] = $dsvalue;
							} elseif ($dsvalue > $rra[$rra_num][$ds_num]['max_value']) {
								$rra[$rra_num][$ds_num]['max_value'] = $dsvalue;
							}

							if (!isset($rra[$rra_num][$ds_num]['min_value'])) {
								$rra[$rra_num][$ds_num]['min_value'] = $dsvalue;
							} elseif ($dsvalue < $rra[$rra_num][$ds_num]['min_value']) {
								$rra[$rra_num][$ds_num]['min_value'] = $dsvalue;
							}
						}

						/* store the sample for standard deviation calculation */
						if ($timestamp == 0) {
							$samples[$rra_num][$ds_num][] = $dsvalue;
						} else {
							$samples[$rra_num][$ds_num][$timestamp] = $dsvalue;
						}

						if (!isset($rra[$rra_num][$ds_num]['totalsamples'])) {
							$rra[$rra_num][$ds_num]['totalsamples'] = 1;
						} else {
							$rra[$rra_num][$ds_num]['totalsamples']++;
						}

						$ds_num++;
					}
				} elseif (substr_count($line, '<rra>')) {
					$in_rra = true;
				} elseif (substr_count($line, '<min>')) {
					$this->ds_min[] = trim(str_replace('<min>', '', str_replace('</min>', '', trim($line))));
				} elseif (substr_count($line, '<max>')) {
					$this->ds_max[] = trim(str_replace('<max>', '', str_replace('</max>', '', trim($line))));
				} elseif (substr_count($line, '<name>')) {
					$this->ds_name[] = trim(str_replace('<name>', '', str_replace('</name>', '', trim($line))));
				} elseif (substr_count($line, '<cf>')) {
					$this->rra_cf[] = trim(str_replace('<cf>', '', str_replace('</cf>', '', trim($line))));
				} elseif (substr_count($line, '<pdp_per_row>')) {
					$this->rra_pdp[] = trim(str_replace('<pdp_per_row>', '', str_replace('</pdp_per_row>', '', trim($line))));
				} elseif (substr_count($line, '</rra>')) {
					$in_rra = false;
					$rra_num++;
				} elseif (substr_count($line, '<step>')) {
					$this->step = trim(str_replace('<step>', '', str_replace('</step>', '', trim($line))));
				}
			}
		}
		cacti_log("DEBUG: number of RRAs: {$rra_num}", false, 'SPIKE', POLLER_VERBOSITY_HIGH);
		cacti_log("DEBUG: number of DSes: {$ds_num}", false, 'SPIKE', POLLER_VERBOSITY_HIGH);

		/* evaluate the dsfilter for matches in our specific RRD file */
		$this->dsfilter = $this->evaluateDsFilter($this->dsfilter);
		/* since `evaluateDsFilter` will return false if there are no matches, we
		   need to catch that situation and insert a fake DS to trigger filtering */
		if ($this->dsfilter === false) {
			$this->dsfilter = array('a' => '-');
		}
//		cacti_log("DEBUG: this->dsfilter: " . var_export($this->dsfilter,true), false, 'SPIKE', POLLER_VERBOSITY_DEBUG);

		/* For all the samples determine the average with the outliers removed */
		$this->calculateVarianceAverages($rra, $samples);

		/**
		 * Now scan the rra array and the samples array and calculate the following
		 *
		 * 1) The standard deviation of all samples
		 * 2) The average of all samples per ds
		 * 3) The max and min cutoffs of all samples
		 * 4) The number of kills in each ds based upon the thresholds
		 *
		 */
		if (empty($this->out_start)) {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
				"NOTE: Searching for Spikes in XML file '$xmlfile'" . ($this->html ? "</p>\n":"\n");
		} else {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
				"NOTE: Limited to window: " . date('Y-m-d H:i:s',$this->out_start) . " thru " . date('Y-m-d H:i:s',$this->out_end) . ($this->html ? "</p>\n":"\n");
			cacti_log("DEBUG: Limited to window: " . date('Y-m-d H:i:s',$this->out_start) . " thru " . date('Y-m-d H:i:s',$this->out_end), false, 'SPIKE', POLLER_VERBOSITY_HIGH);
		}

		$this->calculateOverallStatistics($rra, $samples);

		/* debugging and/or status report */
		if ($this->debug || $this->dryrun) {
			if ($this->html) {
				$this->strout .= "<table style='width:100%' class='spikekillData' id='spikekillData'>";
			}

			$this->outputStatistics($rra);

			if ($this->html) {
				$this->strout .= '</table>';
			}
		}

		/* if we're doing StdDev or Variance, we need to sort the array using the
		   3rd element of each child array (which represents the magnitude of the
		   spike), then trim it to only `numspike` values */
		if ($this->method == SPIKE_METHOD_STDDEV || $this->method == SPIKE_METHOD_VARIANCE) {
			foreach ($this->list_of_spikes as &$spikes_per_rra) {
				uasort($spikes_per_rra, array($this, 'sort_by_third_element_desc'));
				$spikes_per_rra = array_slice($spikes_per_rra, 0, $this->numspike);
			}
		}

		/* count the list_of_spikes array for logging purposes */
		$count = 0;
		foreach ($this->list_of_spikes as $this_rra) {
			$count += count($this_rra);
		}
		cacti_log("DEBUG: count of list_of_spikes is now {$count}", false, 'SPIKE', POLLER_VERBOSITY_HIGH);
		//if ($count <= 60) { cacti_log("DEBUG: dump of list_of_spikes: " . var_export($this->list_of_spikes,TRUE), false, 'SPIKE', POLLER_VERBOSITY_DEBUG); }

		/* create an output array */
		if ($this->method == SPIKE_METHOD_STDDEV) {
			if ($this->std_kills) {
				$this->debug('StdDev kills found');
				cacti_log("DEBUG: StdDev kills found", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
				if (!$this->dryrun) {
					$new_output = $this->updateXML($output, $rra);
					$output = true;
				}
			} else {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
					"NOTE: NO Standard Deviation spikes found in '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");
				cacti_log("DEBUG: No StdDev kills found", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
			}
		} elseif ($this->method == SPIKE_METHOD_VARIANCE) {
			if ($this->var_kills) {
				$this->debug('Variance kills found');
				cacti_log("DEBUG: Variance kills found", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
				if (!$this->dryrun) {
					$new_output = $this->updateXML($output, $rra);
					$output = true;
				}
			} else {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
					"NOTE: NO Variance spikes found in '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");
				cacti_log("DEBUG: No Variance kills found", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
			}
		} elseif ($this->method == SPIKE_METHOD_FILL) {
			if ($this->out_kills) {
				$this->debug('Fill kills found');
				if (!$this->dryrun) {
					$new_output = $this->updateXML($output, $rra);
					$output = true;
				}
			} else {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
					"NOTE: NO Gap Fills found in '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");
				cacti_log("DEBUG: No Gap Fills found", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
			}
		} elseif ($this->method == SPIKE_METHOD_FLOAT) {
			if ($this->out_kills) {
				$this->debug('Float kills found');
				if (!$this->dryrun) {
					$new_output = $this->updateXML($output, $rra);
					$output = true;
				}
			} else {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
					"NOTE: NO Float Fills found in '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");
				cacti_log("DEBUG: No Float Fills found", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
			}
		} elseif ($this->method == SPIKE_METHOD_ABSOLUTE) {
			if ($this->abs_kills) {
				$this->debug('Absolute Max kills found');
				if (!$this->dryrun) {
					$new_output = $this->updateXML($output, $rra);
					$output = true;
				}
			} else {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
					"NOTE: NO Absolute Max kills found in '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");
				cacti_log("DEBUG: No Absolute Max kills found", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
			}
		}

		/* finally update the file XML file and Reprocess the RRDfile */
		if (!$this->dryrun) {
			if ($output === true) {
				cacti_log("DEBUG: writing updates to XML {$xmlfile}", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
				if ($this->writeXMLFile($new_output, $xmlfile)) {
					if ($this->backupRRDFile($this->rrdfile)) {
						$this->createRRDFileFromXML($xmlfile, $this->rrdfile);
						$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
							"NOTE: Spikes Found and Remediated.  Total Spikes ($this->total_kills)" . ($this->html ? "</p>\n":"\n");
					} else {
						$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
							"FATAL: Unable to backup '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");
					}
				} else {
					$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
						"FATAL: Unable to write XML file '$xmlfile'" . ($this->html ? "</p>\n":"\n");
				}
			} else {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
					"NOTE: No Spikes Found." . ($this->html ? "</p>\n":"\n");
			}
		} else {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
				"NOTE: Dryrun requested.  No updates performed" . ($this->html ? "</p>\n":"\n");
		}

		$this->strout .= ($this->html ? "</table>":'');

		if ($this->total_kills > 0) {
			cacti_log("WARNING: Removed '$this->total_kills' Spikes from '$this->rrdfile', Method:'$this->method'", false, 'SPIKE');
		} else {
			cacti_log("NOTE: Removed '$this->total_kills' Spikes from '$this->rrdfile', Method:'$this->method'" . ($this->dryrun ? ' on dryrun' : ''), false, 'SPIKE');
		}

		if (file_exists($xmlfile)) {
			unlink($xmlfile);
		}

		if (file_exists($xmlfile)) {
			unlink($bakfile);
		}

		return true;
	}

	/* All Functions */
	private function createRRDFileFromXML($xmlfile, $rrdfile) {
		/* execute the dump command */
		$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
			"NOTE: Re-Importing '$xmlfile' to '$rrdfile'" . ($this->html ? "</p>\n":"\n");

		$response = shell_exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')) . ' restore -f -r ' . cacti_escapeshellarg($xmlfile) . ' ' . cacti_escapeshellarg($rrdfile));

		if ($response != '') {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') . $response . ($this->html ? "</p>\n":"\n");
		}
	}

	private function writeXMLFile($output, $xmlfile) {
		return file_put_contents($xmlfile, $output);
	}

	private function backupRRDFile($rrdfile) {
		$backupdir = read_config_option('spikekill_backupdir');

		if ($backupdir == '') {
			$backupdir = $this->tempdir;
		}

		if (file_exists($backupdir . '/' . basename($rrdfile))) {
			$newfile = basename($rrdfile) . '.' . $this->seed;
		} else {
			$newfile = basename($rrdfile);
		}

		$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
			"NOTE: Backing Up '$rrdfile' to '" . $backupdir . '/' .  $newfile . "'" . ($this->html ? "</p>\n":"\n");

		return copy($rrdfile, $backupdir . "/" . $newfile);
	}

	private function calculateVarianceAverages(&$rra, &$samples) {
		/* the `variance_avg` is defined as the average of the remaining samples
		   after the outliers are dropped, and NANs are ignored */
		if (cacti_sizeof($samples)) {
			foreach($samples as $rra_num => $dses) {
				if (cacti_sizeof($dses)) {
					foreach($dses as $ds_num => $ds) {
						if (empty($this->out_start)) {
							if (cacti_sizeof($ds) < $this->outliers * 3) {
								$rra[$rra_num][$ds_num]['variance_avg'] = 'NAN';
							} else {
								$myds = $ds;
								$myds = array_filter($myds, array($this, 'removeNanFromSamples'));

								/* remove high outliers */
								rsort($myds, SORT_NUMERIC);
								$myds = array_slice($myds, $this->outliers);

								/* remove low outliers */
								sort($myds, SORT_NUMERIC);
								$myds = array_slice($myds, $this->outliers);

								if (cacti_sizeof($myds)) {
									$rra[$rra_num][$ds_num]['variance_avg'] = array_sum($myds) / cacti_sizeof($myds);
								} else {
									$rra[$rra_num][$ds_num]['variance_avg'] = 'NAN';
								}
							}
						} else {
							if (isset($rra[$rra_num][$ds_num]['sumofsamples']) && isset($rra[$rra_num][$ds_num]['numsamples'])) {
								if ($rra[$rra_num][$ds_num]['numsamples'] > 0) {
									$rra[$rra_num][$ds_num]['variance_avg'] = $rra[$rra_num][$ds_num]['sumofsamples'] / $rra[$rra_num][$ds_num]['numsamples'];
								} else {
									$rra[$rra_num][$ds_num]['variance_avg'] = 0;
								}
							} else {
								$rra[$rra_num][$ds_num]['variance_avg'] = 0;
							}
						}

						cacti_log("DEBUG: variance average for rra {$rra_num}, ds {$ds_num} is {$rra[$rra_num][$ds_num]['variance_avg']}", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
					}
				}
			}
		}
	}

	private function removeNanFromSamples(&$string) {
		return stripos($string, 'nan') === false;
	}

	private function calculateOverallStatistics(&$rra, &$samples) {
		$rra_num = 0;

		if (cacti_sizeof($rra)) {
			foreach($rra as $dses) {
				$ds_num = 0;

				if (cacti_sizeof($dses)) {
					foreach($dses as $ds) {
						if (isset($samples[$rra_num][$ds_num])) {
							if (count($this->dsfilter)==0 || isset($this->dsfilter[$ds_num])) {
								$rra[$rra_num][$ds_num]['standard_deviation'] = $this->processStandardDeviationCalculation($samples[$rra_num][$ds_num]);
								if ($rra[$rra_num][$ds_num]['standard_deviation'] == 'NAN') {
									$rra[$rra_num][$ds_num]['standard_deviation'] = 0;
								}

								if (isset($rra[$rra_num][$ds_num]['sumofsamples']) && isset($rra[$rra_num][$ds_num]['numsamples'])) {
									if ($rra[$rra_num][$ds_num]['numsamples'] > 0) {
										$rra[$rra_num][$ds_num]['average'] = $rra[$rra_num][$ds_num]['sumofsamples'] / $rra[$rra_num][$ds_num]['numsamples'];
									} else {
										$rra[$rra_num][$ds_num]['average'] = 0;
									}
								} else {
									$rra[$rra_num][$ds_num]['average'] = 0;
								}

								if ($this->method == SPIKE_METHOD_STDDEV) {
									$rra[$rra_num][$ds_num]['swing'] = $this->stddev * $rra[$rra_num][$ds_num]['standard_deviation'];
									$rra[$rra_num][$ds_num]['min_cutoff'] = $rra[$rra_num][$ds_num]['average'] - $rra[$rra_num][$ds_num]['swing'];
									if ($rra[$rra_num][$ds_num]['min_cutoff'] < $this->ds_min[$ds_num]) {
										$rra[$rra_num][$ds_num]['min_cutoff'] = $this->ds_min[$ds_num];
									}
									$rra[$rra_num][$ds_num]['max_cutoff'] = $rra[$rra_num][$ds_num]['average'] + $rra[$rra_num][$ds_num]['swing'];
									if ($rra[$rra_num][$ds_num]['max_cutoff'] > $this->ds_max[$ds_num]) {
										$rra[$rra_num][$ds_num]['max_cutoff'] = $this->ds_max[$ds_num];
									}
								} elseif ($this->method == SPIKE_METHOD_VARIANCE || $this->method == SPIKE_METHOD_FLOAT || $this->method == SPIKE_METHOD_FILL) {
									$rra[$rra_num][$ds_num]['swing'] = $this->percent * $rra[$rra_num][$ds_num]['variance_avg'];
									$rra[$rra_num][$ds_num]['min_cutoff'] = $rra[$rra_num][$ds_num]['variance_avg'] - $rra[$rra_num][$ds_num]['swing'];
									if ($rra[$rra_num][$ds_num]['min_cutoff'] < $this->ds_min[$ds_num]) {
										$rra[$rra_num][$ds_num]['min_cutoff'] = $this->ds_min[$ds_num];
									}

									$rra[$rra_num][$ds_num]['max_cutoff'] = $rra[$rra_num][$ds_num]['variance_avg'] + $rra[$rra_num][$ds_num]['swing'];
									if ($rra[$rra_num][$ds_num]['max_cutoff'] > $this->ds_max[$ds_num]) {
										$rra[$rra_num][$ds_num]['max_cutoff'] = $this->ds_max[$ds_num];
									}
								} elseif ($this->method == SPIKE_METHOD_ABSOLUTE) {
									$rra[$rra_num][$ds_num]['min_cutoff'] = 0;
									$rra[$rra_num][$ds_num]['max_cutoff'] = $this->absmax;
								} else {
									$rra[$rra_num][$ds_num]['min_cutoff'] = 'N/A';
									$rra[$rra_num][$ds_num]['max_cutoff'] = 'N/A';
								}

								cacti_log("DEBUG: min_cutoff for rra {$rra_num}, ds {$ds_num} is {$rra[$rra_num][$ds_num]['min_cutoff']}", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
								cacti_log("DEBUG: max_cutoff for rra {$rra_num}, ds {$ds_num} is {$rra[$rra_num][$ds_num]['max_cutoff']}", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);

								$rra[$rra_num][$ds_num]['numnksamples'] = 0;
								$rra[$rra_num][$ds_num]['sumnksamples'] = 0;
								$rra[$rra_num][$ds_num]['avgnksamples'] = 0;

								/* go through values and find cutoffs */
								$rra[$rra_num][$ds_num]['stddev_killed']   = 0;
								$rra[$rra_num][$ds_num]['variance_killed'] = 0;
								$rra[$rra_num][$ds_num]['outwind_killed']  = 0;
								$rra[$rra_num][$ds_num]['absolute_killed']  = 0;

								/* count the number and record the exact kills required */
								if (cacti_sizeof($samples[$rra_num][$ds_num])) {
									if ($this->method == SPIKE_METHOD_STDDEV) {
										foreach($samples[$rra_num][$ds_num] as $timestamp => $sample) {
											if (is_numeric($sample)) {
												if ($sample > $rra[$rra_num][$ds_num]['max_cutoff']) {
													$this->debug(sprintf("StdDev Kill High: Value '%.4e', StandardDev '%.4e', StdDevLimit '%.4e', Time '%s'", $sample, $rra[$rra_num][$ds_num]['standard_deviation'], $rra[$rra_num][$ds_num]['max_cutoff'], date('Y-m-d H:i', $timestamp)));
													$rra[$rra_num][$ds_num]['stddev_killed']++;
													$this->std_kills = true;
													cacti_log("DEBUG: adding this spike to list of spikes: rra {$rra_num}, ds {$ds_num}, value {$sample}, time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
													$this->list_of_spikes[$rra_num][] = Array($ds_num, $timestamp, ($sample - $rra[$rra_num][$ds_num]['average']) / $rra[$rra_num][$ds_num]['swing']);
												} elseif ($sample < $rra[$rra_num][$ds_num]['min_cutoff']) {
													$this->debug(sprintf("StdDev Kill Low: Value '%.4e', StandardDev '%.4e', StdDevLimit '%.4e', Time '%s'", $sample, $rra[$rra_num][$ds_num]['standard_deviation'], $rra[$rra_num][$ds_num]['min_cutoff'], date('Y-m-d H:i', $timestamp)));
													$rra[$rra_num][$ds_num]['stddev_killed']++;
													$this->std_kills = true;
													cacti_log("DEBUG: adding this spike to list of spikes: rra {$rra_num}, ds {$ds_num}, value {$sample}, time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
													$this->list_of_spikes[$rra_num][] = Array($ds_num, $timestamp, ($rra[$rra_num][$ds_num]['average'] - $sample) / $rra[$rra_num][$ds_num]['swing']);
												}
											}
										}
									} elseif ($this->method == SPIKE_METHOD_VARIANCE) {
										foreach($samples[$rra_num][$ds_num] as $timestamp => $sample) {
											if (is_numeric($sample)) {
												if ($sample > $rra[$rra_num][$ds_num]['max_cutoff']) {
													$this->debug(sprintf("Variance Kill High: Value '%.4e', VarianceDev '%.4e', VarianceLimit '%.4e', Time '%s'", $sample, $rra[$rra_num][$ds_num]['variance_avg'], $rra[$rra_num][$ds_num]['max_cutoff'], date('Y-m-d H:i', $timestamp)));
													$rra[$rra_num][$ds_num]['variance_killed']++;
													$this->var_kills = true;
													cacti_log("DEBUG: adding this spike to list of spikes: rra {$rra_num}, ds {$ds_num}, value {$sample}, time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
													$this->list_of_spikes[$rra_num][] = Array($ds_num, $timestamp, ($sample - $rra[$rra_num][$ds_num]['variance_avg']) / $rra[$rra_num][$ds_num]['swing']);
												} elseif ($sample < $rra[$rra_num][$ds_num]['min_cutoff']) {
													$this->debug(sprintf("Variance Kill Low: Value '%.4e', VarianceDev '%.4e', VarianceLimit '%.4e', Time '%s'", $sample, $rra[$rra_num][$ds_num]['variance_avg'], $rra[$rra_num][$ds_num]['min_cutoff'], date('Y-m-d H:i', $timestamp)));
													$rra[$rra_num][$ds_num]['variance_killed']++;
													$this->var_kills = true;
													cacti_log("DEBUG: adding this spike to list of spikes: rra {$rra_num}, ds {$ds_num}, value {$sample}, time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
													$this->list_of_spikes[$rra_num][] = Array($ds_num, $timestamp, ($rra[$rra_num][$ds_num]['variance_avg'] - $sample) / $rra[$rra_num][$ds_num]['swing']);
												}
											}
										}
									} elseif ($this->method == SPIKE_METHOD_FILL) {
										foreach($samples[$rra_num][$ds_num] as $timestamp => $sample) {
											if ($timestamp >= $this->out_start && $timestamp <= $this->out_end) {
												if (strtolower($sample) == 'nan') {
													$this->debug(sprintf("Gap Fill: Value '%.4e', Time '%s'", $sample, date('Y-m-d H:i', $timestamp)));
													$rra[$rra_num][$ds_num]['outwind_killed']++;
													$this->out_kills = true;
													cacti_log("DEBUG: adding this gap to list of fills: rra {$rra_num}, ds {$ds_num}, value {$sample}, time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
													$this->list_of_spikes[$rra_num][] = Array($ds_num, $timestamp, 0);
												}
											}
										}
									} elseif ($this->method == SPIKE_METHOD_FLOAT) {
										foreach($samples[$rra_num][$ds_num] as $timestamp => $sample) {
											if ($timestamp >= $this->out_start && $timestamp <= $this->out_end) {
												$this->debug(sprintf("Float Kill: Value '%.4e', Time '%s'", $sample, date('Y-m-d H:i', $timestamp)));
												$rra[$rra_num][$ds_num]['outwind_killed']++;
												$this->out_kills = true;
												cacti_log("DEBUG: adding this value to list of floats: rra {$rra_num}, ds {$ds_num}, value {$sample}, time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
												$this->list_of_spikes[$rra_num][] = Array($ds_num, $timestamp, 0);
											}
										}
									} elseif ($this->method == SPIKE_METHOD_ABSOLUTE) {
										foreach($samples[$rra_num][$ds_num] as $timestamp => $sample) {
											if (is_numeric($sample)) {
												if ($sample > $rra[$rra_num][$ds_num]['max_cutoff']) {
													$this->debug(sprintf("Absolute Kill High: Value '%.4e', Max '%.4e', Time '%s'", $sample, $rra[$rra_num][$ds_num]['max_cutoff'], date('Y-m-d H:i', $timestamp)));
													$rra[$rra_num][$ds_num]['absolute_killed']++;
													$this->abs_kills = true;
													cacti_log("DEBUG: adding this spike to list of spikes: rra {$rra_num}, ds {$ds_num}, value {$sample}, time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
													$this->list_of_spikes[$rra_num][] = Array($ds_num, $timestamp, 0);
												} elseif ($sample < $rra[$rra_num][$ds_num]['min_cutoff']) {
													$this->debug(sprintf("Absolute Kill Low: Value '%.4e', Min '%.4e', Time '%s'", $sample, $rra[$rra_num][$ds_num]['min_cutoff'], date('Y-m-d H:i', $timestamp)));
													$rra[$rra_num][$ds_num]['absolute_killed']++;
													$this->abs_kills = true;
													cacti_log("DEBUG: adding this spike to list of spikes: rra {$rra_num}, ds {$ds_num}, value {$sample}, time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
													$this->list_of_spikes[$rra_num][] = Array($ds_num, $timestamp, 0);
												}
											}
										}
									}
								}

								if ($rra[$rra_num][$ds_num]['numnksamples'] > 0) {
									$rra[$rra_num][$ds_num]['avgnksamples'] = $rra[$rra_num][$ds_num]['sumnksamples'] / $rra[$rra_num][$ds_num]['numnksamples'];
								}
							} else {
								$rra[$rra_num][$ds_num]['standard_deviation'] = 'N/A';
								$rra[$rra_num][$ds_num]['average']            = 'N/A';
								$rra[$rra_num][$ds_num]['min_cutoff']         = 'N/A';
								$rra[$rra_num][$ds_num]['max_cutoff']         = 'N/A';
								$rra[$rra_num][$ds_num]['numnksamples']       = 'N/A';
								$rra[$rra_num][$ds_num]['sumnksamples']       = 'N/A';
								$rra[$rra_num][$ds_num]['avgnksamples']       = 'N/A';
								$rra[$rra_num][$ds_num]['stddev_killed']      = 'N/A';
								$rra[$rra_num][$ds_num]['variance_killed']    = 'N/A';
								$rra[$rra_num][$ds_num]['outwind_killed']     = 'N/A';
								$rra[$rra_num][$ds_num]['absolute_killed']    = 'N/A';
							}
						} else {
							$rra[$rra_num][$ds_num]['standard_deviation'] = 'N/A';
							$rra[$rra_num][$ds_num]['average']            = 'N/A';
							$rra[$rra_num][$ds_num]['min_cutoff']         = 'N/A';
							$rra[$rra_num][$ds_num]['max_cutoff']         = 'N/A';
							$rra[$rra_num][$ds_num]['numnksamples']       = 'N/A';
							$rra[$rra_num][$ds_num]['sumnksamples']       = 'N/A';
							$rra[$rra_num][$ds_num]['avgnksamples']       = 'N/A';
							$rra[$rra_num][$ds_num]['stddev_killed']      = 'N/A';
							$rra[$rra_num][$ds_num]['variance_killed']    = 'N/A';
							$rra[$rra_num][$ds_num]['outwind_killed']     = 'N/A';
							$rra[$rra_num][$ds_num]['absolute_killed']    = 'N/A';
						}

						$ds_num++;
					}
				}

				$rra_num++;
			}
		}
	}

	private function outputStatistics($rra) {
		if (cacti_sizeof($rra)) {
			if ($this->html) {
				$this->strout .= sprintf("<tr class='tableHeader'><th style='width:10%%;'>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr>\n",
					'Size', 'DataSource', 'CF', 'Samples', 'NonNan', 'Avg', 'VarAvg', 'StdDev',
					'MinValue', 'MaxValue', 'LowCutoff', 'HiCutoff', 'StdKilled', 'VarKilled', 'WindFilled', 'AbsKilled', 'StdDevAvg');
				foreach($rra as $rra_key => $dses) {
					if (cacti_sizeof($dses)) {
						foreach($dses as $dskey => $ds) {
							$this->strout .= sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>' .
								($ds['average'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') .
								(isset($ds['variance_avg']) ? ($ds['variance_avg'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								($ds['standard_deviation'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') .
								(isset($ds['min_value']) ? ($ds['min_value'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								(isset($ds['max_value']) ? ($ds['max_value'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								(isset($ds['min_cutoff']) ? (abs($ds['min_cutoff']) < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								(isset($ds['max_cutoff']) ? ($ds['max_cutoff'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								'<td>%s</td><td>%s</td><td>%s</td><td>%s</td>' .
								(isset($ds['avgnksampled']) ? ($ds['avgnksamples'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								"</tr>\n\n",
								$this->displayTime($this->rra_pdp[$rra_key]),
								$this->ds_name[$dskey],
								$this->rra_cf[$rra_key],
								$ds['totalsamples'],
								(isset($ds['numsamples']) ? $ds['numsamples'] : '0'),
								($ds['average'] != 'N/A' ? round($ds['average'],2) : $ds['average']),
								(isset($ds['variance_avg']) ? round($ds['variance_avg'],2) : 'N/A'),
								($ds['standard_deviation'] != 'N/A' ? round($ds['standard_deviation'],2) : $ds['standard_deviation']),
								(isset($ds['min_value']) ? round($ds['min_value'],2) : 'N/A'),
								(isset($ds['max_value']) ? round($ds['max_value'],2) : 'N/A'),
								($ds['min_cutoff'] != 'N/A' ? round($ds['min_cutoff'],2) : $ds['min_cutoff']),
								($ds['max_cutoff'] != 'N/A' ? round($ds['max_cutoff'],2) : $ds['max_cutoff']),
								$ds['stddev_killed'],
								$ds['variance_killed'],
								$ds['outwind_killed'],
								$ds['absolute_killed'],
								($ds['avgnksamples'] != 'N/A' ? round($ds['avgnksamples'],2) : $ds['avgnksamples'])
								);
						}
					}
				}
			} else {
				$this->strout .= "\n";
				$this->strout .= sprintf("%10s %16s %10s %7s %7s %10s %10s %10s %10s %10s %10s %10s %10s %10s %10s %10s %10s\n",
					'Size', 'DataSource', 'CF', 'Samples', 'NonNan', 'Avg', 'VarAvg', 'StdDev',
					'MinValue', 'MaxValue', 'LowCutoff', 'HiCutoff', 'StdKilled', 'VarKilled', 'WindFilled', 'AbsKilled', 'StdDevAvg');
				$this->strout .= sprintf("%10s %16s %10s %7s %7s %10s %10s %10s %10s %10s %10s %10s %10s %10s %10s %10s %10s\n",
					'----------', '---------------', '----------', '-------', '-------', '----------', '----------',
					'----------', '----------', '----------', '----------', '----------', '----------', '----------',
					'----------', '----------', '----------');

				foreach($rra as $rra_key => $dses) {
					if (cacti_sizeof($dses)) {
						foreach($dses as $dskey => $ds) {
							$this->strout .= sprintf('%10s %16s %10s %7s %7s ' .
								($ds['average'] < 1E6 ? '%10s ':'%10.4e ') .
								(isset($ds['variance_avg']) ? ($ds['variance_avg'] < 1E6 ? '%10s ':'%10.4e ') : '%10.4E ') .
								($ds['standard_deviation'] < 1E6 ? '%10s ':'%10.4e ') .
								(isset($ds['min_value'])  ? ($ds['min_value']  < 1E6 ? '%10s ':'%10.4e ') : '%10s ') .
								(isset($ds['max_value'])  ? ($ds['max_value']  < 1E6 ? '%10s ':'%10.4e ') : '%10s ') .
								(isset($ds['min_cutoff']) ? ($ds['min_cutoff'] < 1E6 ? '%10s ':'%10.4e ') : '%10s ') .
								(isset($ds['max_cutoff']) ? ($ds['max_cutoff'] < 1E6 ? '%10s ':'%10.4e ') : '%10s ') .
								'%10s %10s %10s %10s ' .
								(isset($ds['avgnksamples']) ? ($ds['avgnksamples'] < 1E6 ? '%10s ':'%10.4e ') : '%10.4E ') .
								"\n",
								$this->displayTime($this->rra_pdp[$rra_key]),
								$this->ds_name[$dskey],
								$this->rra_cf[$rra_key],
								$ds['totalsamples'],
								(isset($ds['numsamples']) ? $ds['numsamples'] : '0'),
								($ds['average'] != 'N/A' ? round($ds['average'],2) : $ds['average']),
								(isset($ds['variance_avg']) ? round($ds['variance_avg'],2) : 'N/A'),
								($ds['standard_deviation'] != 'N/A' ? round($ds['standard_deviation'],2) : $ds['standard_deviation']),
								(isset($ds['min_value']) ? round($ds['min_value'],2) : 'N/A'),
								(isset($ds['max_value']) ? round($ds['max_value'],2) : 'N/A'),
								($ds['min_cutoff'] != 'N/A' ? round($ds['min_cutoff'],2) : $ds['min_cutoff']),
								($ds['max_cutoff'] != 'N/A' ? round($ds['max_cutoff'],2) : $ds['max_cutoff']),
								$ds['stddev_killed'],
								$ds['variance_killed'],
								$ds['outwind_killed'],
								$ds['absolute_killed'],
								($ds['avgnksamples'] != 'N/A' ? round($ds['avgnksamples'],2) : $ds['avgnksamples'])
								);
						}
					}
				}

				$this->strout .= "\n";
			}
		}
	}

	private function updateXML(&$output, &$rra) {
		$rra_num   = 0;
		$ds_num    = 0;
		//$kills     = 0; // used to count the kills per RRA (to make sure we don't go over $this->numspike
		$last_num  = array();
		$new_array = array();

		/* since we previously sorted the array by the magnitude of the spike,
		   let's now drop the magnitude column so the array is easier to search */
		foreach ($this->list_of_spikes as &$spikes_per_rra) {
			foreach ($spikes_per_rra as &$spike) {
				$spike = array_slice($spike,0,2);
			}
		}

		if (cacti_sizeof($output)) {
			foreach($output as $line) {
				if (substr_count($line, '<v>')) {
					$linearray = explode('<v>', $line);

					/* get the timestamp */
					$timestamp_part = $linearray[0];
					if (strpos($timestamp_part, '<timestamp>') !== false) {
						$timestamp_part = str_replace('<row><timestamp>', '', $timestamp_part);
						$timestamp_part = str_replace('</timestamp>', '', $timestamp_part);
						$timestamp = trim($timestamp_part);
					} else {
						$timestamp = 0;
					}

					/* discard the first piece of the exploded line */
					array_shift($linearray);

					/* initialize variables */
					$ds_num         = 0;
					$out_row        = '<row>';
					$kills          = 0;

					foreach($linearray as $dsvalue) {
						/* peel off garbage */
						$dsvalue = trim(str_replace('</row>', '', str_replace('</v>', '', $dsvalue)));

						if (isset($this->list_of_spikes[$rra_num])) {
							if (in_array(array($ds_num,$timestamp),$this->list_of_spikes[$rra_num])) {
								/* this dsvalue is a spike, so we need to kill it */
								if ($this->avgnan == 'avg') {
									if ($this->method == SPIKE_METHOD_STDDEV) {
										cacti_log("DEBUG: replacing dsvalue {$dsvalue} with average " . sprintf('%1.10e', $rra[$rra_num][$ds_num]['average']) . " in rra {$rra_num} ds {$ds_num} at time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
										$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['average']);
									} else {
										cacti_log("DEBUG: replacing dsvalue {$dsvalue} with variance_avg " . sprintf('%1.10e', $rra[$rra_num][$ds_num]['variance_avg']) . " in rra {$rra_num} ds {$ds_num} at time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
										$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['variance_avg']);
									}
									//$kills++;
									$this->total_kills++;
								} elseif ($this->avgnan == 'nan') {
									cacti_log("DEBUG: replacing dsvalue {$dsvalue} with NaN in rra {$rra_num} ds {$ds_num} at time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
									$dsvalue = "NaN";
									// still counts as a kill, though
									//$kills++;
									$this->total_kills++;
								} elseif ($this->avgnan == 'last' && isset($last_num[$ds_num])) {
									cacti_log("DEBUG: replacing dsvalue {$dsvalue} with last value {$last_num[$ds_num]} in rra {$rra_num} ds {$ds_num} at time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . ")", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
									$dsvalue = $last_num[$ds_num];
									//$kills++;
									$this->total_kills++;
								} else {
									cacti_log("DEBUG: tried to replace dsvalue {$dsvalue} with last known value in rra {$rra_num} ds {$ds_num} at time {$timestamp} (" . date('Y-m-d H:i:s',$timestamp) . "), but no last value was known", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
									//$kills++;
									//$this->total_kills++;
								}
							} else {
								/* this dsvalue is not a spike */
								if (is_numeric($dsvalue)) {
									$last_num[$ds_num] = $dsvalue;
								}
							}
						}

						$out_row .= '<v> ' . $dsvalue . '</v>';
						$ds_num++;
					}

					$out_row .= "</row>\n";

					$new_array[] = $out_row;
				} else {
					if (substr_count($line, "</rra>")) {
						$rra_num++;
						//$kills = 0;
						$last_num = array();
					} elseif (substr_count($line, '</database>')) {
						$ds_num++;
						//$kills = 0;
						$last_num = array();
					}

					$new_array[] = $line;
				}
			}
		}

		return $new_array;
	}

	private function removeComments(&$output) {
		if (cacti_sizeof($output)) {
			foreach($output as $line) {
				$line = trim($line);
				if ($line == '') {
					continue;
				} else {
					/* is there a comment, remove it */
					$oline = $line;

					$comment_start = strpos($line, '<!--');
					if ($comment_start === false) {
						/* do nothing no line */
					} else {
						$comment_end = strpos($line, '-->');
						if ($comment_start == 0) {
							$line = trim(substr($line, $comment_end+3));
						} else {
							$line = trim(substr($line,0,$comment_start-1) . substr($line,$comment_end+3));
						}

						if (strpos($line, '<row>') !== false) {
							/* capture the timestamp */
							$stamp     = trim(substr($oline, $comment_start+4, $comment_end-4));
							$stamp     = explode('/', $stamp);
							$timestamp = trim($stamp[1]);
							$line = str_replace('<row><v>', "<row><timestamp> $timestamp </timestamp><v>", $line);
						}
					}

					if ($line != '') {
						$new_array[] = $line;
					}
				}
			}

			/* transfer the new array back to the original array */
			return $new_array;
		}
	}

	private function displayTime($pdp) {
		$total_time = $pdp * $this->step; // seconds

		if ($total_time < 60) {
			return $total_time . ' secs';
		} else {
			$total_time = $total_time / 60;

			if ($total_time < 60) {
				return $total_time . ' mins';
			} else {
				$total_time = $total_time / 60;

				if ($total_time < 24) {
					return $total_time . ' hours';
				} else {
					$total_time = $total_time / 24;

					return $total_time . ' days';
				}
			}
		}
	}

	private function debug($string) {
		if ($this->debug) {
			print 'DEBUG: ' . $string . "\n";
		}
	}

	private function processStandardDeviationCalculation($samples) {
		$my_samples = $samples;

		/* if there is a window specified, then only use the values outside the window */
		if (!empty($this->out_start)) {
			foreach($samples as $timestamp => $value) {
				if ($timestamp < $this->out_start || $timestamp > $this->out_end) {
					$my_samples[] = $value;
				}
			}
		}

		return $this->calculateStandardDeviation($my_samples);
	}

	private function calculateStandardDeviation($items) {
		if (!function_exists('stats_standard_deviation')) {
			function stats_standard_deviation($items, $sample = false) {
				$total_items = cacti_count($items);

				if ($total_items === 0) {
					return false;
				}

				if ($sample && $total_items === 1) {
					return false;
				}

				$mean  = array_sum($items) / $total_items;
				$carry = 0.0;

				foreach ($items as $val) {
					$d = ((double) $val) - $mean;
					$carry += $d * $d;
				}

				if ($sample) {
					--$total_items;
				}

				return sqrt($carry / $total_items);
			}
		}

		return stats_standard_deviation($items, false);
	}

	/* sort_by_third_element_desc - used as a sorting mechanism by comparing two
	     arrays and returning a value that represents which is greater than the other
	   @arg $a - an array containing a number at key '2'
	   @arg $b - an array containing a number at key '2'
	   @returns - an integer -1, 0, or 1 */
	private function sort_by_third_element_desc($a, $b) {
		/* sort order is descending (largest to smallest) */
		if ($a[2] == $b[2]) {
				return 0;
		}
		return ($a[2] > $b[2]) ? -1 : 1;
	}

	/* parse_ds - takes a string of text and attempts to convert it into an array
	     containing the complete set of all phrases included within $exp
	   @arg $exp - the string expression to evaluate
	   @returns - an array containing all values represented by the string, or
	     false if the string doesn't parse */
	private function parse_ds($exp) {
		$exp = trim($exp);
		$list = array();
		if (strlen($exp)>0) {
			$pieces = explode(",",$exp);
			foreach ($pieces as $this_piece) {
				$components = explode("-",$this_piece);
				if (count($components)==1) {
					if (is_numeric($components[0])) {
						$list[] = $components[0];
					} elseif (preg_match("/^[a-zA-Z0-9_\*]*$/",$components[0])) {
						/* check to see if there are 0 || 1 asterisks, or more */
						if (strpos($components[0],"*") == strrpos($components[0],"*")) {
							$list[] = $components[0];
						} else {
							/* more than 0 || 1 asterisks, so fail */
							return false;
						}
					} else {
						return false;
					}
				} elseif (count($components)==2) {
					if (is_numeric($components[0]) && is_numeric($components[1])) {
						if ($components[1] > $components[0] && $components[1] - $components[0] < 1000) {
							for ($i = $components[0]; $i <= $components[1]; $i++) {
								$list[] = $i;
							}
						} else {
							return false;
						}
					} else {
						return false;
					}
				} else {
					return false;
				}
			}
		}
		return $list;
	}

	/* evaluateDsFilter - compares the list of DS filters against the list of DSes
	     that exist inside the RRD.  This produces an array of relevant matches
	     for this particular spikekill execution
	   @arg $filters - an array containing individual filters
	   @returns - an array represeting the DSes in this RRD file, or false if
	     the filters had no matches */
	private function evaluateDsFilter($filters) {
		$ds_heap = array();
		if (count($filters)>0) {
			cacti_log("applying dsfilter: " . implode(",",$filters), false, 'SPIKEKILL', POLLER_VERBOSITY_HIGH);
			/* go through each filter and find matching DSes */
			foreach ($filters as $needle) {
				if (is_numeric($needle)) {
					if (isset($this->ds_name[$needle])) { $ds_heap[$needle] = $this->ds_name[$needle]; }
				} else {
					$needle = str_replace("*", ".*", $needle);
					foreach ($this->ds_name as $this_key => $this_name) {
						if (preg_match("/^" . $needle . "$/",$this_name)) { $ds_heap[$this_key] = $this_name; }
					}
				}
			}
			/* build a string that names each DS */
			if (count($ds_heap) > 0) {
				$mes = "";
				foreach ($ds_heap as $key => $value) {
					$mes .= $value . "(" . $key . "),";
				}
				$mes = substr($mes,0,-1);
				cacti_log("dsfilter will limit operation to: {$mes}", false, 'SPIKEKILL');
			} else {
				cacti_log("dsfilter will limit operation to: <no matches>", false, 'SPIKEKILL');
				return false;
			}
		}
		return $ds_heap;
	}

}
