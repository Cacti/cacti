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

/* setup constants */
define('SPIKE_METHOD_STDDEV',   1);
define('SPIKE_METHOD_VARIANCE', 2);
define('SPIKE_METHOD_FILL',     4);
define('SPIKE_METHOD_FLOAT',    3);

class spikekill {
	/* setup defaults */
	private $std_kills = false;
	private $var_kills = false;
	private $out_kills = false;
	private $out_set   = false;
	private $username  = '';
	private $user      = '';
	private $user_info = array();

	// Required variables
	var $rrdfile   = '';

	var $method    = '';
	var $avgnan    = '';
	var $stddev    = '';

	var $out_start = '';
	var $out_end   = '';
	var $outliers  = '';
	var $percent   = '';
	var $numspike  = '';

	// Overridable
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

	// Internal globals
	private $tempdir     = '';
	private $seed        = '';
	private $strout      = '';
	private $ds_min      = '';
	private $ds_max      = '';
	private $total_kills = 0;

	private $rra_cf      = array();
	private $ds_name     = array();
	private $rra_pdp     = array();
	private $step        = 0;

	// For error handling
	private $errors = array();

	public function __construct($rrdfile = '', $method = '', $avgnan = '', $stddev = '',
		$out_start = '', $out_end = '', $outliers = '', $percent = '', $numspike = '') {

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

		$this->dmethod   = read_config_option('spikekill_method', true);
		$this->dnumspike = read_config_option('spikekill_number', true);
		$this->dstddev   = read_config_option('spikekill_deviations', true);
		$this->dpercent  = read_config_option('spikekill_percent', true);
		$this->doutliers = read_config_option('spikekill_outliers', true);
		$this->davgnan   = read_config_option('spikekill_avgnan', true);

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

		$umethod   = read_user_setting('spikekill_method', $this->dmethod, true);
		$unumspike = read_user_setting('spikekill_number', $this->dnumspike, true);
		$ustddev   = read_user_setting('spikekill_deviations', $this->dstddev, true);
		$upercent  = read_user_setting('spikekill_percent', $this->dpercent, true);
		$uoutliers = read_user_setting('spikekill_outliers', $this->doutliers, true);
		$uavgnan   = read_user_setting('spikekill_avgnan', $this->davgnan, true);

		/* set the correct value */
		if ($this->avgnan == '') {
			if (!isset($uavgnan)) {
				$this->avgnan = $davgnan;
			} else {
				$this->avgnan = $uavgnan;
			}
		}

		if ($this->method == '') {
			if (!isset($umethod)) {
				$this->method = $dmethod;
			} else {
				$this->method = $umethod;
			}
		}

		if ($this->numspike == '') {
			if (!isset($unumspike)) {
				$this->numspike = $dnumspike;
			} else {
				$this->numspike = $unumspike;
			}
		}

		if ($this->stddev == '') {
			if (!isset($ustddev)) {
				$this->stddev = $dstddev;
			} else {
				$this->stddev = $ustddev;
			}
		}

		if ($this->percent == '') {
			if (!isset($upercent)) {
				$this->percent = $dpercent;
			} else {
				$this->percent = $upercent;
			}
		}

		if ($this->outliers == '') {
			if (!isset($uoutliers)) {
				$this->outliers = $doutliers;
			} else {
				$this->outliers = $uoutliers;
			}
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
				$this->set_error("FATAL: The number of outliers to exclude must be a positive integer.");
			}

			$this->out_set = true;
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
			default:
				$this->set_error("FATAL: You must specify either 'stddev', 'variance', 'float', or 'fill' as methods.");
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

		return false;
	}

	public function remove_spikes() {
		global $config;

		$this->initialize_spikekill();

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
			$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') . "NOTE: Removing Outliers in Range and Replacing with Last" . ($this->html ? "</p>\n":"\n");
		}

		/* execute the dump command */
		$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') . "NOTE: Creating XML file '$xmlfile' from '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");

		if (!$this->dryrun) {
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
			default:
				$mm  = 'Undefined';
				$mes = "$this->username, File:" . basename($this->rrdfile) . ", Method:$mm";
			}

			cacti_log($mes, false, 'SPIKEKILL');
		}

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
		cacti_log("DEBUG: number of RRAs: {$rra_num}", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
		cacti_log("DEBUG: number of DSes: {$ds_num}", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);

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
				"NOTE: Limited to window: " . date('M j, Y H:i:s',$this->out_start) . " thru " . date('M j, Y H:i:s',$this->out_end) . ($this->html ? "</p>\n":"\n");
			cacti_log("DEBUG: Limited to window: " . date('M j, Y H:i:s',$this->out_start) . " thru " . date('M j, Y H:i:s',$this->out_end), false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
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

		$new_output = '';

		/* create an output array */
		if ($this->method == SPIKE_METHOD_STDDEV) {
			/* standard deviation subroutine */
			if ($this->std_kills || $this->out_kills) {
				$this->debug('Either std_kills or out_kills found');

				if (!$this->dryrun) {
					$new_output = $this->updateXML($output, $rra);
					$output = true;
				}
			} elseif (!empty($this->out_start)) {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
					"NOTE: No Window Spikes found in '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");
			} else {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
					"NOTE: No Standard Deviation Spikes found in '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");
			}
		} else {
			/* variance subroutine */
			if ($this->var_kills || $this->out_kills) {
				$this->debug('Either variance or out_kills found');

				if (!$this->dryrun) {
					$new_output = $this->updateXML($output, $rra);
					$output = true;
				}
			} elseif (!empty($this->out_start)) {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
					"NOTE: No Window Fills found in '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");
			} else {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>":'') .
					"NOTE: No Variance Spikes found in '$this->rrdfile'" . ($this->html ? "</p>\n":"\n");
			}
		}

		/* finally update the file XML file and Reprocess the RRDfile */
		if (!$this->dryrun) {
			if ($output == true && $new_output != '') {
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
			cacti_log("WARNING: Removed '$this->total_kills' Spikes from '$this->rrdfile', Method:'$this->method'", false, 'WEBUI');
		} elseif($this->debug) {
			cacti_log("NOTE: Removed '$this->total_kills' Spikes from '$this->rrdfile', Method:'$this->method'", false, 'WEBUI');
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

							$rra[$rra_num][$ds_num]['min_cutoff'] = $rra[$rra_num][$ds_num]['average'] - ($this->stddev * $rra[$rra_num][$ds_num]['standard_deviation']);
							if ($rra[$rra_num][$ds_num]['min_cutoff'] < $this->ds_min[$ds_num]) {
								$rra[$rra_num][$ds_num]['min_cutoff'] = $this->ds_min[$ds_num];
							}

							$rra[$rra_num][$ds_num]['max_cutoff'] = $rra[$rra_num][$ds_num]['average'] + ($this->stddev * $rra[$rra_num][$ds_num]['standard_deviation']);
							if ($rra[$rra_num][$ds_num]['max_cutoff'] > $this->ds_max[$ds_num]) {
								$rra[$rra_num][$ds_num]['max_cutoff'] = $this->ds_max[$ds_num];
							}

							$rra[$rra_num][$ds_num]['numnksamples'] = 0;
							$rra[$rra_num][$ds_num]['sumnksamples'] = 0;
							$rra[$rra_num][$ds_num]['avgnksamples'] = 0;

							/* go through values and find cutoffs */
							$rra[$rra_num][$ds_num]['stddev_killed']   = 0;
							$rra[$rra_num][$ds_num]['variance_killed'] = 0;
							$rra[$rra_num][$ds_num]['outwind_killed']  = 0;

							/* count the number of kills required */
							if (cacti_sizeof($samples[$rra_num][$ds_num])) {
								foreach($samples[$rra_num][$ds_num] as $timestamp => $sample) {
									if (!empty($this->out_start) && $timestamp >= $this->out_start && $timestamp <= $this->out_end) {
										if ($this->method == SPIKE_METHOD_FLOAT) {
											$this->debug(sprintf("Window Float Kill: Value '%.4e', Time '%s'", $sample, date('Y-m-d H:i', $timestamp)));

											$rra[$rra_num][$ds_num]['outwind_killed']++;
											$this->out_kills = true;
										} elseif ($this->method == SPIKE_METHOD_FILL) {
											if ($sample > (1+$this->percent)*$rra[$rra_num][$ds_num]['variance_avg'] || strtolower($sample) == 'nan') {
												$this->debug(sprintf("Window GapFill Kill: Value '%.4e', Time '%s'", $sample, date('Y-m-d H:i', $timestamp)));

												$rra[$rra_num][$ds_num]['outwind_killed']++;
												$this->out_kills = true;
											}
										}
									} elseif (($sample > $rra[$rra_num][$ds_num]['max_cutoff']) ||
										($sample < $rra[$rra_num][$ds_num]['min_cutoff'])) {
										if ($this->method == SPIKE_METHOD_STDDEV) {
											$this->debug(sprintf("StdDev Kill: Value '%.4e', StandardDev '%.4e', StdDevLimit '%.4e'", $sample, $rra[$rra_num][$ds_num]['standard_deviation'], ($rra[$rra_num][$ds_num]['max_cutoff'] * (1+$this->percent))));

											$rra[$rra_num][$ds_num]['stddev_killed']++;
											$this->std_kills = true;
										}
									} elseif (is_numeric($sample)) {
										$rra[$rra_num][$ds_num]['numnksamples']++;
										$rra[$rra_num][$ds_num]['sumnksamples'] += $sample;
									}

									if (!empty($this->out_start) && $timestamp >= $this->out_start && $timestamp <= $this->out_end) {
										/* Already calculated */
									} elseif ($rra[$rra_num][$ds_num]['variance_avg'] == 'NAN') {
										/* not enough samples to calculate */
									} elseif ($sample > ($rra[$rra_num][$ds_num]['variance_avg'] * (1+$this->percent))) {
										if ($this->method == SPIKE_METHOD_VARIANCE) {
											/* kill based upon variance */
											$this->debug(sprintf("Var Kill: Value '%.4e', VarianceDev '%.4e', VarianceLimit '%.4e'", $sample, $rra[$rra_num][$ds_num]['variance_avg'], ($rra[$rra_num][$ds_num]['variance_avg'] * (1+$this->percent))));

											$rra[$rra_num][$ds_num]['variance_killed']++;
											$this->var_kills = true;
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
			if (!$this->html) {
				$this->strout .= "\n";
				$this->strout .= sprintf("%10s %16s %10s %7s %7s %10s %10s %10s %10s %10s %10s %10s %10s %10s %10s %10s\n",
					'Size', 'DataSource', 'CF', 'Samples', 'NonNan', 'Avg', 'StdDev',
					'MaxValue', 'MinValue', 'MaxStdDev', 'MinStdDev', 'StdKilled', 'VarKilled', 'WindFilled', 'StdDevAvg', 'VarAvg');
				$this->strout .= sprintf("%10s %16s %10s %7s %7s %10s %10s %10s %10s %10s %10s %10s %10s %10s %10s %10s\n",
					'----------', '---------------', '----------', '-------', '-------', '----------', '----------',
					'----------', '----------', '----------', '----------', '----------', '----------', '----------',
					'----------', '----------', '----------');

				foreach($rra as $rra_key => $dses) {
					if (cacti_sizeof($dses)) {
						foreach($dses as $dskey => $ds) {
							$this->strout .= sprintf('%10s %16s %10s %7s %7s ' .
								($ds['average'] < 1E6 ? '%10s ':'%10.4e ') .
								($ds['standard_deviation'] < 1E6 ? '%10s ':'%10.4e ') .
								(isset($ds['max_value'])  ? ($ds['max_value']  < 1E6 ? '%10s ':'%10.4e ') : '%10s ') .
								(isset($ds['min_value'])  ? ($ds['min_value']  < 1E6 ? '%10s ':'%10.4e ') : '%10s ') .
								(isset($ds['max_cutoff']) ? ($ds['max_cutoff'] < 1E6 ? '%10s ':'%10.4e ') : '%10s ') .
								(isset($ds['min_cutoff']) ? ($ds['min_cutoff'] < 1E6 ? '%10s ':'%10.4e ') : '%10s ') .
								'%10s %10s %10s ' .
								(isset($ds['avgnksamples']) ? ($ds['avgnksamples'] < 1E6 ? '%10s ':'%10.4e ') : '%10.4E ') .
								(isset($ds['variance_avg']) ? ($ds['variance_avg'] < 1E6 ? '%10s ':'%10.4e ') : '%10.4E ') . "\n",
								$this->displayTime($this->rra_pdp[$rra_key]),
								$this->ds_name[$dskey],
								$this->rra_cf[$rra_key],
								$ds['totalsamples'],
								(isset($ds['numsamples']) ? $ds['numsamples'] : '0'),
								($ds['average'] != 'N/A' ? round($ds['average'],2) : $ds['average']),
								($ds['standard_deviation'] != 'N/A' ? round($ds['standard_deviation'],2) : $ds['standard_deviation']),
								(isset($ds['max_value']) ? round($ds['max_value'],2) : 'N/A'),
								(isset($ds['min_value']) ? round($ds['min_value'],2) : 'N/A'),
								($ds['max_cutoff'] != 'N/A' ? round($ds['max_cutoff'],2) : $ds['max_cutoff']),
								($ds['min_cutoff'] != 'N/A' ? round($ds['min_cutoff'],2) : $ds['min_cutoff']),
								$ds['stddev_killed'],
								$ds['variance_killed'],
								$ds['outwind_killed'],
								($ds['avgnksamples'] != 'N/A' ? round($ds['avgnksamples'],2) : $ds['avgnksamples']),
								(isset($ds['variance_avg']) ? round($ds['variance_avg'],2) : 'N/A'));
						}
					}
				}

				$this->strout .= "\n";
			} else {
				$this->strout .= sprintf("<tr class='tableHeader'><th style='width:10%%;'>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr>\n",
					'Size', 'DataSource', 'CF', 'Samples', 'NonNan', 'Avg', 'StdDev',
					'MaxValue', 'MinValue', 'MaxStdDev', 'MinStdDev', 'StdKilled', 'VarKilled', 'WindFilled', 'StdDevAvg', 'VarAvg');
				foreach($rra as $rra_key => $dses) {
					if (cacti_sizeof($dses)) {
						foreach($dses as $dskey => $ds) {
							$this->strout .= sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>' .
								($ds['average'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') .
								($ds['standard_deviation'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') .
								(isset($ds['max_value']) ? ($ds['max_value'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								(isset($ds['min_value']) ? ($ds['min_value'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								(isset($ds['max_cutoff']) ? ($ds['max_cutoff'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								(isset($ds['min_cutoff']) ? ($ds['min_cutoff'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								'<td>%s</td><td>%s</td><td>%s</td>' .
								(isset($ds['avgnksampled']) ? ($ds['avgnksamples'] < 1E6 ? '<td>%s</td>':'<td>%.4e</td>') : '<td>%s</td>') .
								(isset($ds['variance_avg']) ? ($ds['variance_avg'] < 1E6 ? "<td>%s</td>":"<td>%.4e</td>") : "<td>%s</td>") .
								"</tr>\n\n",
								$this->displayTime($this->rra_pdp[$rra_key]),
								$this->ds_name[$dskey],
								$this->rra_cf[$rra_key],
								$ds['totalsamples'],
								(isset($ds['numsamples']) ? $ds['numsamples'] : '0'),
								($ds['average'] != 'N/A' ? round($ds['average'],2) : $ds['average']),
									($ds['standard_deviation'] != 'N/A' ? round($ds['standard_deviation'],2) : $ds['standard_deviation']),
								(isset($ds['max_value']) ? round($ds['max_value'],2) : 'N/A'),
								(isset($ds['min_value']) ? round($ds['min_value'],2) : 'N/A'),
								($ds['max_cutoff'] != 'N/A' ? round($ds['max_cutoff'],2) : $ds['max_cutoff']),
								($ds['min_cutoff'] != 'N/A' ? round($ds['min_cutoff'],2) : $ds['min_cutoff']),
								$ds['stddev_killed'],
								$ds['variance_killed'],
								$ds['outwind_killed'],
								($ds['avgnksamples'] != 'N/A' ? round($ds['avgnksamples'],2) : $ds['avgnksamples']),
								(isset($ds['variance_avg']) ? round($ds['variance_avg'],2) : 'N/A'));
						}
					}
				}
			}
		}
	}

	private function updateXML(&$output, &$rra) {
		/* variance subroutine */
		$rra_num   = 0;
		$ds_num    = 0;
		$last_num  = array();
		$new_array = array();

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

						if (strtolower($dsvalue) == 'nan' && !isset($last_num[$ds_num])) {
							/* do nothing, it's a NaN, and the first one */
						} elseif (!empty($this->out_start) && $timestamp > $this->out_start && $timestamp < $this->out_end) {
							/* a window is specified, and the timestamp is inside the window */
							if ($this->method == SPIKE_METHOD_FLOAT) {
								if ($this->avgnan == 'avg') {
									cacti_log("DEBUG: replacing dsvalue {$dsvalue} with variance_avg {$rra[$rra_num][$ds_num]['variance_avg']}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
									$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['variance_avg']);
									$kills++;
									$this->total_kills++;
								} elseif ($this->avgnan == 'last' && isset($last_num[$ds_num])) {
									cacti_log("DEBUG: replacing dsvalue {$dsvalue} with last value {$last_num[$ds_num]}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
									$dsvalue = $last_num[$ds_num];
									$kills++;
									$this->total_kills++;
								}
							} elseif ($this->method == SPIKE_METHOD_FILL) {
								if ($dsvalue > (1+$this->percent)*$rra[$rra_num][$ds_num]['variance_avg'] || strtolower($dsvalue) == 'nan') {
									if ($this->avgnan == 'avg') {
										cacti_log("DEBUG: replacing dsvalue {$dsvalue} with variance_avg {$rra[$rra_num][$ds_num]['variance_avg']}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
										$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['variance_avg']);
										$kills++;
										$this->total_kills++;
									} elseif ($this->avgnan == 'last' && isset($last_num[$ds_num])) {
										cacti_log("DEBUG: replacing dsvalue {$dsvalue} with last value {$last_num[$ds_num]}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
										$dsvalue = $last_num[$ds_num];
										$kills++;
										$this->total_kills++;
									}
								}
							} elseif ($this->method == SPIKE_METHOD_VARIANCE) {
								if ($kills < $this->numspike) {
									if ($this->avgnan == 'avg') {
										cacti_log("DEBUG: replacing dsvalue {$dsvalue} with variance_avg {$rra[$rra_num][$ds_num]['variance_avg']}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
										$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['variance_avg']);
										$this->total_kills++;
										$kills++;
									} elseif ($this->avgnan == 'last' && isset($last_num[$ds_num])) {
										cacti_log("DEBUG: replacing dsvalue {$dsvalue} with last value {$last_num[$ds_num]}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
										$dsvalue = $last_num[$ds_num];
										$this->total_kills++;
										$kills++;
									} else {
										cacti_log("DEBUG: replacing dsvalue {$dsvalue} with NaN", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
										$dsvalue = 'NaN';
									}
								}
							} elseif ($this->method == SPIKE_METHOD_STDDEV) {
								if ($kills < $this->numspike) {
									if ($this->avgnan == 'avg') {
										cacti_log("DEBUG: replacing dsvalue {$dsvalue} with average {$rra[$rra_num][$ds_num]['average']}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
										$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['average']);
										$this->total_kills++;
										$kills++;
									} elseif ($this->avgnan == 'last' && isset($last_num[$ds_num])) {
										cacti_log("DEBUG: replacing dsvalue {$dsvalue} with last value {$last_num[$ds_num]}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
										$dsvalue = $last_num[$ds_num];
										$this->total_kills++;
										$kills++;
									} else {
										cacti_log("DEBUG: replacing dsvalue {$dsvalue} with NaN", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
										$dsvalue = 'NaN';
									}
								}
							}
						} elseif(strtolower($dsvalue) == 'nan' && isset($last_num[$ds_num])) {
							/**
							 * We need to ignore this case as it's only a gap file when
							 * There is a time range
							 */
							if ($this->method == SPIKE_METHOD_VARIANCE) {
								cacti_log("DEBUG: ignoring dsvalue {$dsvalue} as NaN values are left along when using the Variance Method!", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
							} elseif ($this->method == SPIKE_METHOD_STDDEV) {
								cacti_log("DEBUG: ignoring dsvalue {$dsvalue} as NaN values are left alone when using the Standard Deviation Method!", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
							} else {
								cacti_log("DEBUG: ignoring dsvalue {$dsvalue} as we are outside of the time range!", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
							}
						} else {
							if ($this->method == SPIKE_METHOD_VARIANCE) {
								if ($dsvalue > (1+$this->percent)*$rra[$rra_num][$ds_num]['variance_avg']) {
									if ($kills < $this->numspike) {
										if ($this->avgnan == 'avg') {
											cacti_log("DEBUG: replacing dsvalue {$dsvalue} with variance_avg {$rra[$rra_num][$ds_num]['variance_avg']}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
											$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['variance_avg']);
											$kills++;
											$this->total_kills++;
										} elseif ($this->avgnan == 'last' && isset($last_num[$ds_num])) {
											cacti_log("DEBUG: replacing dsvalue {$dsvalue} with last value {$last_num[$ds_num]}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
											$dsvalue = $last_num[$ds_num];
											$kills++;
											$this->total_kills++;
										} else {
											cacti_log("DEBUG: replacing dsvalue {$dsvalue} with NaN", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
											$dsvalue = 'NaN';
										}
									}
								} else {
									$last_num[$ds_num] = $dsvalue;
								}
							} else {
								if (($dsvalue > $rra[$rra_num][$ds_num]['max_cutoff']) ||
									($dsvalue < $rra[$rra_num][$ds_num]['min_cutoff'])) {
									if ($kills < $this->numspike) {
										if ($this->avgnan == 'avg') {
											cacti_log("DEBUG: replacing dsvalue {$dsvalue} with average {$rra[$rra_num][$ds_num]['average']}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
											$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['average']);
											$kills++;
											$this->total_kills++;
										} elseif ($this->avgnan == 'last' && isset($last_num[$ds_num])) {
											cacti_log("DEBUG: replacing dsvalue {$dsvalue} with last value {$last_num[$ds_num]}", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
											$dsvalue = $last_num[$ds_num];
											$kills++;
											$this->total_kills++;
										} else {
											cacti_log("DEBUG: replacing dsvalue {$dsvalue} with NaN", false, 'SPIKEKILL', POLLER_VERBOSITY_DEBUG);
											$dsvalue = 'NaN';
										}
									}
								} else {
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
					if (substr_count($line, '</rra>')) {
						$ds_minmax = array();
						$rra_num++;

						$kills    = 0;
						$last_num = array();
					} elseif (substr_count($line, '</database>')) {
						$ds_num++;

						$kills    = 0;
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
}
