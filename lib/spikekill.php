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

// setup constants
define('SPIKE_METHOD_STDDEV',   1);
define('SPIKE_METHOD_FLOAT',    2);
define('SPIKE_METHOD_FILL',     3);
define('SPIKE_METHOD_ABSOLUTE', 4);

class spikekill {
	// setup defaults
	private mixed  $std_kills = false;
	private mixed  $var_kills = false;
	private mixed  $out_kills = false;
	private string $username  = '';
	private string $user      = '';
	private array  $user_info = [];

	// Required variables
	public string $rrdfile    = '';
	public mixed  $method     = '';
	public mixed  $avgnan     = '';
	public mixed  $stddev     = '';
	public mixed  $out_start  = 0;
	public mixed  $out_end    = 0;
	public mixed  $numspike   = '';
	public mixed  $dsfilter   = '';
	public mixed  $absmax     = '';

	// Overridable
	public bool $html        = true;
	public bool $backup      = false;
	public bool $debug       = false;
	public bool $dryrun      = false;

	// Defaults from cacti settings
	private int $dmethod      = 1;
	private int $dnumspike    = 10;
	private int $dstddev      = 10;
	private float $dabsmax    = 1E9;
	private string $davgnan   = 'last';
	private string $ddsfilter = '';

	// Internal globals
	private string $tempdir  = '';
	private mixed $seed      = '';
	private string $strout   = '';
	private array $ds_min    = [];
	private array $ds_max    = [];
	private int $total_kills = 0;
	private array $rra_cf    = [];
	private array $ds_name   = [];
	private array $rra_pdp   = [];
	private int $step        = 0;
	private array $errors    = [];

	public function __construct(string $rrdfile = '', string $method = '', string $avgnan = '', string $stddev = '',
		string $out_start = '', string $out_end = '', string $numspike = '', string $dsfilter = '', string $absmax = '') {
		$this->username  = 'OsUser:' . get_current_user();
		$this->user_info = [];

		if (isset($_SESSION[SESS_USER_ID])) {
			$this->user = $_SESSION[SESS_USER_ID];

			// confirm the user id is accurate
			$this->user_info = db_fetch_row_prepared('SELECT id, username
				FROM user_auth
				WHERE id = ?',
				[$this->user]);

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
			if (!is_numeric($out_start)) {
				$this->out_start = strtotime($out_start);
			} else {
				$this->out_start = $out_start;
			}
		}

		if ($out_end != '') {
			if (!is_numeric($out_end)) {
				$this->out_end = strtotime($out_end);
			} else {
				$this->out_end = $out_end;
			}
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

		$this->dmethod   = read_config_option('spikekill_method', true);
		$this->davgnan   = read_config_option('spikekill_avgnan', true);
		$this->ddsfilter = read_config_option('spikekill_dsfilter', true);
		$this->dnumspike = intval(read_config_option('spikekill_number', true));
		$this->dstddev   = intval(read_config_option('spikekill_deviations', true));
		$this->dabsmax   = intval(read_config_option('spikekill_absmax', true));
	}

	public function __destruct() {
		// Empty destructor
	}

	private function set_error(string $string) : void {
		$this->errors[] = $string;
	}

	private function is_error_set() : mixed {
		return cacti_sizeof($this->errors);
	}

	public function get_errors() : string {
		$output = '';

		if (cacti_sizeof($this->errors)) {
			foreach ($this->errors as $error) {
				$output .= ($output != '' ? ($this->html ? '<br>' : "\n") : '') . $error;
			}
		}

		return $output;
	}

	public function get_output(bool $html = true) : string {
		return $this->strout;
	}

	private function initializeSpikekill() : void {
		// additional error check
		if ($this->rrdfile == '') {
			$this->set_error('FATAL: You must specify an RRDfile!');
		}

		if (!file_exists($this->rrdfile)) {
			$this->set_error(__esc("FATAL: File '%s' does not exist.", $this->rrdfile));
		} elseif (!is_writable($this->rrdfile)) {
			$this->set_error(__esc("FATAL: File '%s' is not writable by '%s'.", $this->rrdfile, get_execution_user()));
		}

		$umethod   = read_user_setting('spikekill_method', $this->dmethod, true);
		$uavgnan   = read_user_setting('spikekill_avgnan', $this->davgnan, true);
		$udsfilter = read_user_setting('spikekill_dsfilter', $this->ddsfilter, true);
		$unumspike = read_user_setting('spikekill_number', $this->dnumspike, true);
		$ustddev   = read_user_setting('spikekill_deviations', $this->dstddev, true);
		$uabsmax   = read_user_setting('spikekill_absmax', $this->dabsmax, true);

		// set the correct value: prefer user setting, fall back to default
		if ($this->avgnan == '') {
			if (!empty($uavgnan)) {
				$this->avgnan = $uavgnan;
			} else {
				$this->avgnan = $this->davgnan;
			}
		}

		if ($this->method == '') {
			if (!empty($umethod)) {
				$this->method = $umethod;
			} else {
				$this->method = $this->dmethod;
			}
		}

		if ($this->numspike == '') {
			if (!empty($unumspike)) {
				$this->numspike = $unumspike;
			} else {
				$this->numspike = $this->dnumspike;
			}
		}

		if ($this->stddev == '') {
			if (!empty($ustddev)) {
				$this->stddev = $ustddev;
			} else {
				$this->stddev = $this->dstddev;
			}
		}

		if ($this->dsfilter == '') {
			if (!empty($udsfilter)) {
				$this->dsfilter = $udsfilter;
			} else {
				$this->dsfilter = $this->ddsfilter;
			}
		}

		if ($this->absmax == '') {
			if (!empty($uabsmax)) {
				$this->absmax = $uabsmax;
			} else {
				$this->absmax = $this->dabsmax;
			}
		}

		// the order of the following case statements reflects the order in the spikekill menu in the GUI.
		$dispmethod = '';

		switch($this->method) {
			case 'stddev':
				$this->method = SPIKE_METHOD_STDDEV;
				$dispmethod   = __('StdDev');

				break;
			case 'fill':
				$this->method = SPIKE_METHOD_FILL;
				$dispmethod   = __('Gap Fill');

				break;
			case 'float':
				$this->method = SPIKE_METHOD_FLOAT;
				$dispmethod   = __('Float Range');

				break;
			case 'absolute':
				$this->method = SPIKE_METHOD_ABSOLUTE;
				$dispmethod   = __('Absolute Max');

				break;
			default:
				$this->set_error(__("FATAL: You must specify either 'stddev', 'float', or 'fill' as methods."));
		}

		if (!is_numeric($this->stddev) || ($this->stddev < 1)) {
			$this->set_error(__('FATAL: Standard Deviation must be a positive integer.'));
		}

		if (!is_numeric($this->out_start)) {
			$this->set_error(__('FATAL: The Spike Kill Window Start must be a date or timestamp.'));
		}

		if (!is_numeric($this->out_end)) {
			$this->set_error(__('FATAL: The Spike Kill Window End must be a date or timestamp.'));
		}

		/**
		 * The fill, float, and absolute require a time range.  It's optional for stddev.
		 * Convert these to timestamps if they are not already so.
		 */
		if ($this->method == SPIKE_METHOD_FLOAT || $this->method == SPIKE_METHOD_FILL || $this->method == SPIKE_METHOD_ABSOLUTE) {
			if (!is_numeric($this->out_start)) {
				$this->out_start = strtotime($this->out_start);
			}

			if (!is_numeric($this->out_end)) {
				$this->out_end = strtotime($this->out_end);
			}

			if ($this->out_start === false || $this->out_end === false) {
				$this->set_error(__('FATAL: The outlier-start and outlier-end arguments must be in the format of YYYY-MM-DD HH:MM or a UNIX timestamp.'));
			}
		}

		if ($this->numspike != '') {
			if (!is_numeric($this->numspike) || ($this->numspike < 1)) {
				$this->set_error(__('FATAL: Number of spikes to remove must be a positive integer'));
			}
		}

		if ($this->absmax != '') {
			if (!is_numeric($this->absmax) || ($this->absmax < 1)) {
				$this->set_error(__('FATAL: Number value for absolute maximum value positive integer'));
			}
		}

		// Make sure both ends of the time range are set
		if ((!empty($this->out_start) && empty($this->out_end)) || (!empty($this->out_end) && empty($this->out_start))) {
			$this->set_error(__('FATAL: Outlier time range requires outlier-start and outlier-end to be specified.'));
		}

		// Check a bad range of the window start and end
		if (!empty($this->out_start)) {
			if ($this->out_start >= $this->out_end) {
				$this->set_error(__('FATAL: Outlier time range requires outlier-start to be less than outlier-end.'));
			}
		}

		if ($this->method == SPIKE_METHOD_FLOAT && $this->out_start == 0) {
			$this->set_error(__("FATAL: The 'float' removal method requires the specification of a start and end date or timestamp."));
		}

		if ($this->method == SPIKE_METHOD_FILL && $this->out_start == 0) {
			$this->set_error(__("FATAL: The 'fill' removal method requires the specification of a start and end date or timestamp."));
		}

		if ($this->method == SPIKE_METHOD_ABSOLUTE && $this->out_start == 0) {
			$this->set_error(__("FATAL: The 'absolute' removal method requires the specification of a start and end date or timestamp."));
		}

		// Verify the replacement methods
		switch($this->avgnan) {
			case 'avg':
			case 'last':
			case 'nan':
				break;
			default:
				$this->set_error(__("FATAL: You must specify either 'last', 'avg' or 'nan' as a replacement method."));
		}

		$this->strout .= ($this->html ? "<h3 class='spikekillNote'>" : '') . __('Spike Kill Settings Used for Analysis/Correction') . ($this->html ? '</h3><hr>' : PHP_EOL);

		if (!$this->html) {
			$this->strout .= '------------------------------------------------' . PHP_EOL;
		}

		$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __esc('Method:        %s', $dispmethod) . ($this->html ? '</p>' : PHP_EOL);
		$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __esc('RRDfile:       %s', $this->rrdfile) . ($this->html ? '</p>' : PHP_EOL);
		$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __esc('Repair Type:   %s', ucfirst($this->avgnan)) . ($this->html ? '</p>' : PHP_EOL);

		if ($this->method == SPIKE_METHOD_STDDEV) {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __esc('Max Kills:     %s', $this->numspike) . ($this->html ? '</p>' : PHP_EOL);
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __esc('Standard Devs: %s', $this->stddev) . ($this->html ? '</p>' : PHP_EOL);
		} else {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __('Max Kills:     Unlimited') . ($this->html ? '</p>' : PHP_EOL);
		}

		if ($this->out_start > 0) {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __esc('Window Start:  %s (%s)' . PHP_EOL, $this->out_start, date('Y-m-d H:i', $this->out_start)) . ($this->html ? '</p>' : PHP_EOL);
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __esc('Window End:    %s (%s)', $this->out_end, date('Y-m-d H:i', $this->out_end)) . ($this->html ? '</p>' : PHP_EOL . PHP_EOL);
		} else {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __('Window Range:  All') . ($this->html ? '</p>' : PHP_EOL . PHP_EOL);
		}

		if ($this->html) {
			$this->strout .= '<hr>';
		}
	}

	public function remove_spikes() : bool {
		$this->strout = '';

		$this->initializeSpikekill();

		$start = microtime(true);

		if ($this->is_error_set()) {
			return false;
		}

		// determine the temporary file name; a non-predictable component keeps
		// concurrent spikekill runs from colliding on the same temp file
		try {
			$this->seed = bin2hex(random_bytes(8));
		} catch (Exception $e) {
			$this->set_error(__('FATAL: Unable to generate a random temporary file name.  Check the system entropy source!'));

			return false;
		}

		if (CACTI_SERVER_OS == 'win32') {
			$this->tempdir  = read_config_option('spikekill_backupdir');
			$xmlfile        = $this->tempdir . '/' . str_replace('.rrd', '', basename($this->rrdfile)) . '.dump.' . $this->seed;
			$bakfile        = $this->tempdir . '/' . str_replace('.rrd', '', basename($this->rrdfile)) . '.backup.' . $this->seed . '.rrd';
		} else {
			$this->tempdir = read_config_option('spikekill_backupdir');
			$xmlfile       = $this->tempdir . '/' . str_replace('.rrd', '', basename($this->rrdfile)) . '.dump.' . $this->seed;
			$bakfile       = $this->tempdir . '/' . str_replace('.rrd', '', basename($this->rrdfile)) . '.backup.' . $this->seed . '.rrd';
		}

		if (!empty($this->out_start) && !$this->dryrun) {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __('NOTE: Removing Outliers in Range and Replacing with Last') . ($this->html ? "</p>\n" : "\n");
		}

		// execute the dump command
		$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __esc("NOTE: Creating XML file '%s' from '%s'", $xmlfile, $this->rrdfile) . ($this->html ? "</p>\n" : "\n");

		if (!$this->dryrun) {
			switch ($this->method) {
				case SPIKE_METHOD_STDDEV:
					$mm  = 'StdDev';
					$mes = "$this->username, File:" . basename($this->rrdfile) . ", Method:$mm, StdDevs:$this->stddev, AvgNan:$this->avgnan, Kills:$this->numspike";

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
					$mm  = 'AbsMax';
					$mes = "$this->username, File:" . basename($this->rrdfile) . ", Method:$mm, OutStart:$this->out_start, OutEnd:$this->out_end, AvgNan:$this->avgnan";

					break;
				default:
					$mm  = 'Undefined';
					$mes = "$this->username, File:" . basename($this->rrdfile) . ", Method:$mm";
			}

			cacti_log($mes, false, 'SPIKEKILL');
		}

		// '2>&1' before the file redirection leaves stderr on the captured stream
		// while stdout still goes to the xml file
		$dump_output = shell_exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')) . ' dump ' . cacti_escapeshellarg($this->rrdfile) . ' 2>&1 > ' . cacti_escapeshellarg($xmlfile));

		// read the xml file into an array
		if (file_exists($xmlfile)) {
			$output = file($xmlfile);

			// remove the temp file
			unlink($xmlfile);
		} else {
			// the dump produced no file; capture any diagnostic output for the log
			cacti_log(sprintf("ERROR: RRDtool dump of '%s' produced no output file. Output:'%s'", $this->rrdfile, trim((string) $dump_output)), false, 'SPIKEKILL');

			$this->set_error(__('FATAL: RRDtool Command Failed.  Please verify that the RRDtool path is valid in Settings->Paths!'));

			return false;
		}

		// backup the rrdfile if requested
		if ($this->backup && !$this->dryrun) {
			if (copy($this->rrdfile, $bakfile)) {
				$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . __esc("NOTE: RRDfile '%s' backed up to '%s'", $this->rrdfile, $bakfile) . ($this->html ? "</p>\n" : "\n");
			} else {
				$this->set_error(__esc("FATAL: RRDfile Backup of '%s' to '%s' FAILED!", $this->rrdfile, $bakfile));

				return false;
			}
		}

		if ($this->is_error_set()) {
			return false;
		}

		// process the xml file and remove all comments
		$output = $this->removeComments($output);

		/**
		 * Read all the rra's ds values and obtain the following pieces of information from each
		 *  rra archive.
		 *
		 *  - numsamples   - The number of 'valid' non-nan samples
		 *  - sumofsamples - The sum of all 'valid' samples.
		 *  - average      - The average of all samples
		 *  - stddev       - The standard deviation of all samples
		 *  - max_value    - The maximum value of all samples
		 *  - min_value    - The minimum value of all samples
		 *  - max_cutoff   - Any value above this value will be set to the average.
		 *  - min_cutoff   - Any value lower than this value will be set to the average.
		 *
		 * This will end up being a n-dimensional array as follows:
		 *
		 * rra[x][ds#]['totalsamples'];
		 * rra[x][ds#]['numsamples'];
		 * rra[x][ds#]['sumofsamples'];
		 * rra[x][ds#]['average'];
		 * rra[x][ds#]['stddev'];
		 * rra[x][ds#]['max_value'];
		 * rra[x][ds#]['min_value'];
		 * rra[x][ds#]['max_cutoff'];
		 * rra[x][ds#]['min_cutoff'];
		 *
		 * There will also be a secondary array created with the actual samples.  This
		 * array will be used to calculate the standard deviation of the sample set.
		 * samples[rra_num][ds_num][timestamp];
		 *
		 * Also track the min and max value for each ds and store it into the two
		 * arrays: ds_min[ds#], ds_max[ds#].
		 *
		 * Then we don't need to know the type of rra, only its number for this analysis
		 * the same applies for the ds' as well.
		 */
		$rra           = [];
		$this->rra_cf  = [];
		$this->rra_pdp = [];

		$rra_num = 0;
		$ds_num  = 0;
		$samples = [];

		$this->total_kills = 0;

		$in_rra  = false;
		$in_db   = false;

		$this->ds_min  = [];
		$this->ds_max  = [];

		$this->ds_name = [];

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
			foreach ($output as $line) {
				if (substr_count($line, '<v>')) {
					$linearray = explode('<v>', $line);

					// get the timestamp
					$timestamp_part = $linearray[0];

					if (str_contains($timestamp_part, '<timestamp>')) {
						$timestamp_part = str_replace('<row><timestamp>', '', $timestamp_part);
						$timestamp_part = str_replace('</timestamp>', '', $timestamp_part);
						$timestamp      = intval(trim($timestamp_part));
					} else {
						$timestamp = 0;
					}

					// discard the first piece of the exploded line
					array_shift($linearray);
					$ds_num = 0;

					foreach ($linearray as $dsvalue) {
						// peel off garbage
						$dsvalue = trim(str_replace('</row>', '', str_replace('</v>', '', $dsvalue)));

						// check for outlier territory
						if ($timestamp > 0) {
							if ($this->method == SPIKE_METHOD_FILL || $this->method == SPIKE_METHOD_FLOAT || $this->method == SPIKE_METHOD_ABSOLUTE) {
								if ($timestamp < $this->out_start) {
									if (is_numeric($dsvalue)) {
										$rra[$rra_num][$ds_num]['last'] = $dsvalue;
									}

									$process = true;
								} elseif ($timestamp >= $this->out_start && $timestamp <= $this->out_end) {
									if ($this->method == SPIKE_METHOD_FILL) {
										if (!is_numeric($dsvalue)) {
											$this->debug(sprintf('Fill Found, RRA:%s, DSNum:%s, Date:%s, CurVal:%.2e', $rra_num, $ds_num, date('Y-m-d H:i:s', $timestamp), $dsvalue));
										}
									} elseif ($this->method == SPIKE_METHOD_FLOAT) {
										$this->debug(sprintf('Float Found, RRA:%s, DSNum:%s, Date:%s, CurVal:%.2e', $rra_num, $ds_num, date('Y-m-d H:i:s', $timestamp), $dsvalue));
									} else {
										if ($dsvalue >= $this->absmax) {
											$this->debug(sprintf('AbsMax Found, RRA:%s, DSNum:%s, Date:%s, CurVal:%.2e', $rra_num, $ds_num, date('Y-m-d H:i:s', $timestamp), $dsvalue));
										}
									}

									$process = false;
								} else {
									$process = true;
								}
							} else {
								$process = true;
							}
						} else {
							$this->debug('WARNING: Illegal Timestamp Found');

							$process = true;
						}

						if (is_numeric($dsvalue) && $process) {
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

						// store the sample for standard deviation calculation
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
					$this->step = intval(trim(str_replace('<step>', '', str_replace('</step>', '', trim($line)))));
				}
			}
		}

		$parse_end  = microtime(true);
		$parse_time = $parse_end - $start;

		cacti_log("DEBUG: number of RRAs: {$rra_num}", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
		cacti_log("DEBUG: number of DSes: {$ds_num}", false, 'SPIKE', POLLER_VERBOSITY_DEBUG);

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
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
				__esc("NOTE: Searching for Spikes in XML file '%s'", $xmlfile) . ($this->html ? "</p>\n" : "\n");
		} else {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
				__esc('NOTE: Limited to Time Window: %s through %s', date('M j, Y H:i:s', $this->out_start), date('M j, Y H:i:s',$this->out_end)) . ($this->html ? "</p><br>\n" : "\n");

			cacti_log('DEBUG: Limited to Time Window: ' . date('M j, Y H:i:s',$this->out_start) . ' thru ' . date('M j, Y H:i:s',$this->out_end), false, 'SPIKE', POLLER_VERBOSITY_DEBUG);
		}

		$this->calculateOverallStatistics($rra, $samples);

		$stats_end  =  microtime(true);
		$stats_time = $stats_end - $parse_end;

		// debugging and/or status report
		if ($this->debug || $this->dryrun) {
			if ($this->html) {
				$this->strout .= "<div style='overflow-x:auto;'><table style='width:100%' class='spikekillData' id='spikekillData'>";
			}

			$this->outputStatistics($rra);

			if ($this->html) {
				$this->strout .= '</table></div><br>';
			}
		}

		$new_output = '';
		$continue   = false;

		// create an output array
		if ($this->std_kills || $this->out_kills || $this->var_kills) {
			$this->debug('Either std_kills or out_kills found');

			if (!$this->dryrun) {
				$new_output = $this->updateXML($output, $rra);
				$output     = true;
				$continue   = true;
			} else {
				$new_output = $this->updateXML($output, $rra);
				$output     = false;
				$continue   = false;
			}
		} elseif ($this->out_start > 0) {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
				__esc("NOTE: No Window Spikes found in '%s'", $this->rrdfile) . ($this->html ? "</p>\n" : "\n");
		} else {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
				__esc("NOTE: No Spikes found in '%s'", $this->rrdfile) . ($this->html ? "</p>\n" : "\n");
		}

		$update_end  = microtime(true);
		$update_time = $update_end - $stats_end;

		// finally update the file XML file and Reprocess the RRDfile
		$end   = microtime(true);
		$total = round($end - $start, 2);

		// Need to find where this thing is slow
		$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
			__esc('NOTE: Parse:%.2f, Stats:%.2f, Update:%.2f, Kills:%d',
				$parse_time, $stats_time, $update_time, $this->total_kills) . ($this->html ? "</p>\n" : "\n");

		if (!$this->dryrun) {
			if ($continue) {
				if ($output == true && $new_output != '') {
					if ($this->writeXMLFile($new_output, $xmlfile)) {
						if ($this->backupRRDFile($this->rrdfile)) {
							$this->createRRDFileFromXML($xmlfile, $this->rrdfile);

							$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
								__esc('NOTE: Time:%s, Spikes Found and Remediated.  Total Spikes %s', $total, $this->total_kills) . ($this->html ? "</p>\n" : "\n");
						} else {
							$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
								__esc("FATAL: Time:%s, Unable to backup '%s'", $total, $this->rrdfile) . ($this->html ? "</p>\n" : "\n");
						}
					} else {
						$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
							__esc("FATAL: Time:%s, Unable to write XML file '%s'", $total, $xmlfile) . ($this->html ? "</p>\n" : "\n");
					}
				} else {
					$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
						__esc('NOTE: Time:%s, No Spikes Found.', $total) . ($this->html ? "</p>\n" : "\n");
				}
			}
		} else {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
				__esc('NOTE: Time:%s, Dryrun requested.  No updates performed', $total) . ($this->html ? "</p>\n" : "\n");
		}

		$this->strout .= ($this->html ? '</table>' : '');

		if ($this->total_kills > 0) {
			cacti_log(sprintf("WARNING: Time:%.2f, Removed '%s' Spikes from '%s', Method:'%s'", $total, $this->total_kills, $this->rrdfile, $this->method), false, 'WEBUI');
		} elseif ($this->debug) {
			cacti_log(sprintf("NOTE: Time:%.2f, Removed '%s' Spikes from '%s', Method:'%s'", $total, $this->total_kills, $this->rrdfile, $this->method), false, 'WEBUI');
		}

		if (file_exists($xmlfile)) {
			unlink($xmlfile);
		}

		if (file_exists($bakfile)) {
			unlink($bakfile);
		}

		return true;
	}

	// All Functions
	private function createRRDFileFromXML(string $xmlfile, string $rrdfile) : void {
		// execute the dump command
		$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
			"NOTE: Re-Importing '$xmlfile' to '$rrdfile'" . ($this->html ? "</p>\n" : "\n");

		$response = shell_exec(cacti_escapeshellcmd(read_config_option('path_rrdtool')) . ' restore -f -r ' . cacti_escapeshellarg($xmlfile) . ' ' . cacti_escapeshellarg($rrdfile));

		if ($response != '') {
			$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') . $response . ($this->html ? "</p>\n" : "\n");
		}
	}

	private function writeXMLFile(array $output, string $xmlfile) : mixed {
		return file_put_contents($xmlfile, $output);
	}

	private function backupRRDFile(string $rrdfile) : bool {
		$backupdir = read_config_option('spikekill_backupdir');

		if ($backupdir == '') {
			$backupdir = $this->tempdir;
		}

		if (file_exists($backupdir . '/' . basename($rrdfile))) {
			$newfile = basename($rrdfile) . '.' . $this->seed;
		} else {
			$newfile = basename($rrdfile);
		}

		$this->strout .= ($this->html ? "<p class='spikekillNote'>" : '') .
			"NOTE: Backing Up '$rrdfile' to '" . $backupdir . '/' . $newfile . "'" . ($this->html ? "</p>\n" : "\n");

		return copy($rrdfile, $backupdir . '/' . $newfile);
	}

	private function calculateOverallStatistics(array &$rra, array &$samples) : void {
		$rra_num = 0;

		if (cacti_sizeof($rra)) {
			foreach ($rra as $dses) {
				$ds_num = 0;

				if (cacti_sizeof($dses)) {
					foreach ($dses as $ds) {
						if (isset($samples[$rra_num][$ds_num])) {
							$rra[$rra_num][$ds_num]['stddev'] = $this->processStandardDeviationCalculation($samples[$rra_num][$ds_num]);

							if ($rra[$rra_num][$ds_num]['stddev'] == 'NAN') {
								$rra[$rra_num][$ds_num]['stddev'] = 0;
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

							$rra[$rra_num][$ds_num]['min_cutoff'] = $rra[$rra_num][$ds_num]['average'] - ($this->stddev * $rra[$rra_num][$ds_num]['stddev']);

							if ($rra[$rra_num][$ds_num]['min_cutoff'] < $this->ds_min[$ds_num]) {
								$rra[$rra_num][$ds_num]['min_cutoff'] = $this->ds_min[$ds_num];
							}

							$rra[$rra_num][$ds_num]['max_cutoff'] = $rra[$rra_num][$ds_num]['average'] + ($this->stddev * $rra[$rra_num][$ds_num]['stddev']);

							if ($rra[$rra_num][$ds_num]['max_cutoff'] > $this->ds_max[$ds_num]) {
								$rra[$rra_num][$ds_num]['max_cutoff'] = $this->ds_max[$ds_num];
							}

							$rra[$rra_num][$ds_num]['numnksamples'] = 0;
							$rra[$rra_num][$ds_num]['sumnksamples'] = 0;
							$rra[$rra_num][$ds_num]['avgnksamples'] = 0;

							// go through values and find cutoffs
							$rra[$rra_num][$ds_num]['stddev_killed']   = 0;
							$rra[$rra_num][$ds_num]['outwind_samples'] = 0;
							$rra[$rra_num][$ds_num]['outwind_killed']  = 0;

							// count the number of kills required
							if (cacti_sizeof($samples[$rra_num][$ds_num])) {
								foreach ($samples[$rra_num][$ds_num] as $timestamp => $sample) {
									if ($this->method == SPIKE_METHOD_FLOAT || $this->method == SPIKE_METHOD_FILL) {
										if ($timestamp >= $this->out_start && $timestamp <= $this->out_end) {
											$rra[$rra_num][$ds_num]['outwind_samples']++;

											if ($this->method == SPIKE_METHOD_FLOAT) {
												$this->debug(sprintf('Window Float Found, Date:%s, Value:%s', date('Y-m-d H:i', $timestamp), $sample));

												$rra[$rra_num][$ds_num]['outwind_killed']++;
												$this->out_kills = true;
											} elseif ($this->method == SPIKE_METHOD_FILL) {
												if (!is_numeric($sample)) { // Gap Fill Means 'NaN's only
													$this->debug(sprintf('Window GapFill Found, Date:%s, Value:%s', date('Y-m-d H:i', $timestamp), $sample));

													$rra[$rra_num][$ds_num]['outwind_killed']++;
													$this->out_kills = true;
												}
											} elseif (is_numeric($sample)) {
												$rra[$rra_num][$ds_num]['numnksamples']++;
												$rra[$rra_num][$ds_num]['sumnksamples'] += $sample;
											}
										} elseif (is_numeric($sample)) {
											$rra[$rra_num][$ds_num]['numnksamples']++;
											$rra[$rra_num][$ds_num]['sumnksamples'] += $sample;
										}
									} elseif ($this->method == SPIKE_METHOD_ABSOLUTE) {
										if ($timestamp >= $this->out_start && $timestamp <= $this->out_end) {
											if ($this->out_start > 0) {
												$rra[$rra_num][$ds_num]['outwind_samples']++;
											}

											if ($sample > $this->absmax) {
												$this->debug(sprintf('Window AbsMax Found, Date:%s, Value:%s', date('Y-m-d H:i', $timestamp), $sample));

												if ($this->out_start > 0) {
													$rra[$rra_num][$ds_num]['outwind_killed']++;
												}

												$this->out_kills = true;
											} elseif (is_numeric($sample)) {
												$rra[$rra_num][$ds_num]['numnksamples']++;
												$rra[$rra_num][$ds_num]['sumnksamples'] += $sample;
											}
										} elseif (is_numeric($sample)) {
											$rra[$rra_num][$ds_num]['numnksamples']++;
											$rra[$rra_num][$ds_num]['sumnksamples'] += $sample;
										}
									} elseif ($this->method == SPIKE_METHOD_STDDEV) {
										if ($this->out_start == 0 || ($timestamp >= $this->out_start && $timestamp <= $this->out_end)) {
											if ($this->out_start > 0) {
												$rra[$rra_num][$ds_num]['outwind_samples']++;
											}

											if ($sample > $rra[$rra_num][$ds_num]['max_cutoff'] || $sample < $rra[$rra_num][$ds_num]['min_cutoff']) {
												$this->debug(sprintf('StdDev Found, Date:%s, Value:%.2e, StandardDev:%.2e, StdDevLimit:%.2e', date('Y-m-d H:i', $timestamp), $sample, $rra[$rra_num][$ds_num]['stddev'], $rra[$rra_num][$ds_num]['max_cutoff']));

												$rra[$rra_num][$ds_num]['stddev_killed']++;

												if ($this->out_start > 0) {
													$rra[$rra_num][$ds_num]['outwind_killed']++;
												}

												$this->std_kills = true;
											} elseif (is_numeric($sample)) {
												$rra[$rra_num][$ds_num]['numnksamples']++;
												$rra[$rra_num][$ds_num]['sumnksamples'] += $sample;
											}
										} elseif (is_numeric($sample)) {
											$rra[$rra_num][$ds_num]['numnksamples']++;
											$rra[$rra_num][$ds_num]['sumnksamples'] += $sample;
										}
									} elseif (is_numeric($sample)) {
										$rra[$rra_num][$ds_num]['numnksamples']++;
										$rra[$rra_num][$ds_num]['sumnksamples'] += $sample;
									}
								}
							}

							if ($rra[$rra_num][$ds_num]['numnksamples'] > 0) {
								$rra[$rra_num][$ds_num]['avgnksamples'] = $rra[$rra_num][$ds_num]['sumnksamples'] / $rra[$rra_num][$ds_num]['numnksamples'];
							}
						} else {
							$rra[$rra_num][$ds_num]['stddev']          = 'N/A';
							$rra[$rra_num][$ds_num]['average']         = 'N/A';
							$rra[$rra_num][$ds_num]['min_value']       = 'N/A';
							$rra[$rra_num][$ds_num]['max_value']       = 'N/A';
							$rra[$rra_num][$ds_num]['min_cutoff']      = 'N/A';
							$rra[$rra_num][$ds_num]['max_cutoff']      = 'N/A';
							$rra[$rra_num][$ds_num]['numnksamples']    = 'N/A';
							$rra[$rra_num][$ds_num]['sumnksamples']    = 'N/A';
							$rra[$rra_num][$ds_num]['avgnksamples']    = 'N/A';
							$rra[$rra_num][$ds_num]['stddev_killed']   = 'N/A';
							$rra[$rra_num][$ds_num]['outwind_samples'] = 'N/A';
							$rra[$rra_num][$ds_num]['outwind_killed']  = 'N/A';
						}

						$ds_num++;
					}
				}

				$rra_num++;
			}
		}
	}

	private function outputStatistics(array $rra) : void {
		if (cacti_sizeof($rra)) {
			if (!$this->html) {
				$this->strout .= "\n";

				$this->strout .= sprintf("%10s %16s %10s %7s %7s %10s %10s %10s %10s %10s %10s %10s %10s %12s %10s\n",
					'Size', 'DS', 'CF', 'Samples', 'NonNan', 'Avg', 'StdDev', 'Variance',
					'MaxValue', 'MinValue', 'MaxStdDev', 'MinStdDev', 'StdKilled', 'WindSamples', 'WindKilled');

				$this->strout .= sprintf("%10s %16s %10s %7s %7s %10s %10s %10s %10s %10s %10s %10s %10s %12s %10s\n",
					'----------', '---------------', '----------', '-------', '-------', '----------', '----------', '----------',
					'----------', '----------', '----------', '----------', '----------', '------------', '----------');

				foreach ($rra as $rra_key => $dses) {
					if (cacti_sizeof($dses)) {
						foreach ($dses as $dskey => $ds) {
							$this->strout .= sprintf('%10s %16s %10s %7s %7s ' .
								($ds['average'] < 1E6 ? '%10s ' : '%10.2e ') .
								($ds['stddev'] < 1E6 ? '%10s ' : '%10.2e ') .
								($ds['max_value'] < 1E6 ? '%10s ' : '%10.2e ') .
								($ds['min_value'] < 1E6 ? '%10s ' : '%10.2e ') .
								($ds['max_cutoff'] < 1E6 ? '%10s ' : '%10.2e ') .
								($ds['min_cutoff'] < 1E6 ? '%10s ' : '%10.2e ') .
								'%10s %10s %12s %10s' . PHP_EOL,
								$this->displayTime($this->rra_pdp[$rra_key]),
								$this->ds_name[$dskey],
								$this->rra_cf[$rra_key],
								number_format_i18n($ds['totalsamples'] ?? 0),
								number_format_i18n($ds['numsamples'] ?? 0),
								($ds['average']         != 'N/A' ? round($ds['average'], 2) : 'N/A'),
								($ds['stddev']          != 'N/A' ? round($ds['stddev'], 2) : 'N/A'),
								($ds['variance']        != 'N/A' ? round($ds['stddev'] ^ 2, 2) : 'N/A'),
								($ds['max_value']       != 'N/A' ? round($ds['max_value'], 2) : 'N/A'),
								($ds['min_value']       != 'N/A' ? round($ds['min_value'], 2) : 'N/A'),
								($ds['max_cutoff']      != 'N/A' ? round($ds['max_cutoff'], 2) : 'N/A'),
								($ds['min_cutoff']      != 'N/A' ? round($ds['min_cutoff'], 2) : 'N/A'),
								($ds['stddev_killed']   != 'N/A' ? number_format_i18n($ds['stddev_killed']) : 'N/A'),
								number_format_i18n($ds['outwind_samples']),
								number_format_i18n($ds['outwind_killed']));
						}
					}
				}

				$this->strout .= "\n";
			} else {
				$this->strout .= sprintf("<tr class='tableHeader'><th class='nowrap' style='width:10%%'>%s</th><th>%s</th><th>%s</th><th class='right'>%s</th><th class='right'>%s</th><th class='right'>%s</th><th class='right'>%s</th><th class='right'>%s</th><th class='right'>%s</th><th class='right'>%s</th><th class='right'>%s</th><th class='right'>%s</th><th class='right'>%s</th><th class='right'>%s</th></tr>\n",
					__('Size'), __('DataSource'), __('CF'), __('Samples'), __('NonNan'), __('Avg'), __('StdDev'),
					__('MaxValue'), __('MinValue'), __('MaxStdDev'), __('MinStdDev'), __('StdKilled'), __('WindSamples'), __('WindKilled'));

				foreach ($rra as $rra_key => $dses) {
					if (cacti_sizeof($dses)) {
						foreach ($dses as $dskey => $ds) {
							$this->strout .= sprintf('<tr>' .
								'<td class="nowrap">%s</td>' .
								'<td>%s</td>' .
								'<td>%s</td>' .
								'<td class="right">%s</td>' .
								'<td class="right">%s</td>' .
								($ds['average'] < 1E6 ? '<td class="right">%s</td>' : '<td class="right">%10.2e</td>') .
								($ds['stddev'] < 1E6 ? '<td class="right">%s</td>' : '<td class="right">%10.2e</td>') .
								($ds['max_value'] < 1E6 ? '<td class="right">%s</td>' : '<td class="right">%10.2e</td>') .
								($ds['min_value'] < 1E6 ? '<td class="right">%s</td>' : '<td class="right">%10.2e</td>') .
								($ds['max_cutoff'] < 1E6 ? '<td class="right">%s</td>' : '<td class="right">%10.2e</td>') .
								($ds['min_cutoff'] < 1E6 ? '<td class="right">%s</td>' : '<td class="right">%10.2e</td>') .
								'<td class="right">%s</td>' .
								'<td class="right">%s</td>' .
								'<td class="right">%s</td>' .
								"</tr>\n\n",
								$this->displayTime($this->rra_pdp[$rra_key]),
								$this->ds_name[$dskey],
								$this->rra_cf[$rra_key],
								number_format_i18n($ds['totalsamples'] ?? 0),
								number_format_i18n($ds['numsamples'] ?? 0),
								($ds['average']         != 'N/A' ? round($ds['average'], 2) : __('N/A')),
								($ds['stddev']          != 'N/A' ? round($ds['stddev'], 2) : __('N/A')),
								($ds['max_value']       != 'N/A' ? round($ds['max_value'], 2) : __('N/A')),
								($ds['min_value']       != 'N/A' ? round($ds['min_value'], 2) : __('N/A')),
								($ds['max_cutoff']      != 'N/A' ? round($ds['max_cutoff'], 2) : __('N/A')),
								($ds['min_cutoff']      != 'N/A' ? round($ds['min_cutoff'], 2) : __('N/A')),
								($ds['stddev_killed']   != 'N/A' ? number_format_i18n($ds['stddev_killed']) : __('N/A')),
								($ds['outwind_samples'] != 'N/A' ? number_format_i18n($ds['outwind_samples']) : __('N/A')),
								($ds['outwind_killed']  != 'N/A' ? number_format_i18n($ds['outwind_killed']) : __('N/A')));
						}
					}
				}
			}
		}
	}

	private function updateXML(array &$output, array &$rra) : array {
		$rra_num   = 0;
		$ds_num    = 0;
		$last_num  = [];
		$new_array = [];

		if (cacti_sizeof($output)) {
			foreach ($output as $line) {
				if (substr_count($line, '<v>')) {
					$linearray = explode('<v>', $line);

					// get the timestamp
					$timestamp_part = $linearray[0];

					if (str_contains($timestamp_part, '<timestamp>')) {
						$timestamp_part = str_replace('<row><timestamp>', '', $timestamp_part);
						$timestamp_part = str_replace('</timestamp>', '', $timestamp_part);
						$timestamp      = trim($timestamp_part);
					} else {
						$timestamp = 0;
					}

					// discard the first piece of the exploded line
					array_shift($linearray);

					// initialize variables
					$ds_num  = 0;
					$out_row = '<row>';
					$kills   = 0;

					foreach ($linearray as $dsvalue) {
						// peel off garbage
						$dsvalue = trim(str_replace('</row>', '', str_replace('</v>', '', $dsvalue)));

						switch($this->method) {
							case SPIKE_METHOD_FLOAT:
								if ($timestamp >= $this->out_start && $timestamp <= $this->out_end) {
									if ($this->avgnan == 'avg') {
										$message = sprintf('Replacing dsvalue %s with average %s', $dsvalue, $rra[$rra_num][$ds_num]['average']);

										if ($this->debug) {
											cacti_log("DEBUG: $message", false, 'SPIKEKILL');
										}

										$this->debug($message);

										$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['average']);
										$kills++;
										$this->total_kills++;
									} elseif ($this->avgnan == 'nan') {
										// Not supported for fill and float modes
									} elseif ($this->avgnan == 'last' && isset($rra[$rra_num][$ds_num]['last'])) {
										$message = sprintf('Replacing dsvalue %s with last value %s', $dsvalue, $rra[$rra_num][$ds_num]['last']);

										if ($this->debug) {
											cacti_log("DEBUG: $message", false, 'SPIKEKILL');
										}

										$this->debug($message);

										$dsvalue = $rra[$rra_num][$ds_num]['last'];
										$kills++;
										$this->total_kills++;
									}
								} elseif ($this->debug) {
									cacti_log("DEBUG: ignoring dsvalue {$dsvalue} as we are outside of the time range!", false, 'SPIKEKILL');
								}

								break;
							case SPIKE_METHOD_FILL:
								if ($timestamp >= $this->out_start && $timestamp <= $this->out_end) {
									if ($this->avgnan == 'avg') {
										if (!is_numeric($dsvalue) || $dsvalue == 0) {
											$message = sprintf('Replacing dsvalue %s with average %s', $dsvalue, $rra[$rra_num][$ds_num]['average']);

											if ($this->debug) {
												cacti_log("DEBUG: $message", false, 'SPIKEKILL');
											}

											$this->debug($message);

											$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['average']);
											$kills++;
											$this->total_kills++;
										}
									} elseif ($this->avgnan == 'nan') {
										// Not supported for fill and float modes
									} elseif ($this->avgnan == 'last' && isset($rra[$rra_num][$ds_num]['last'])) {
										if (!is_numeric($dsvalue) || $dsvalue == 0) {
											$message = sprintf('Replacing dsvalue %s with last value %s', $dsvalue, $rra[$rra_num][$ds_num]['last']);

											if ($this->debug) {
												cacti_log("DEBUG: $message", false, 'SPIKEKILL');
											}

											$this->debug($message);

											$dsvalue = $rra[$rra_num][$ds_num]['last'];
											$kills++;
											$this->total_kills++;
										}
									}
								} elseif ($this->debug) {
									cacti_log("DEBUG: ignoring dsvalue {$dsvalue} as we are outside of the time range!", false, 'SPIKEKILL');
								}

								break;
							case SPIKE_METHOD_STDDEV:
								if (($dsvalue > $rra[$rra_num][$ds_num]['max_cutoff']) ||
									($dsvalue < $rra[$rra_num][$ds_num]['min_cutoff'])) {
									if (empty($this->out_start) || ($timestamp >= $this->out_start && $timestamp <= $this->out_end)) {
										if ($kills < $this->numspike) {
											if ($this->avgnan == 'avg') {
												$message = sprintf('Replacing dsvalue %s with average %s', $dsvalue, $rra[$rra_num][$ds_num]['average']);

												if ($this->debug) {
													cacti_log("DEBUG: $message", false, 'SPIKEKILL');
												}

												$this->debug($message);

												$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['average']);
												$this->total_kills++;
												$kills++;
											} elseif ($this->avgnan == 'nan') {
												$message = sprintf('Replacing dsvalue %s with NaN', $dsvalue);

												if ($this->debug) {
													cacti_log("DEBUG: $message", false, 'SPIKEKILL');
												}

												$this->debug($message);

												$dsvalue = 'NaN';
												$this->total_kills++;
												$kills++;
											} elseif ($this->avgnan == 'last' && isset($last_num[$ds_num])) {
												$message = sprintf('Replacing dsvalue %s with last value %s', $dsvalue, $last_num[$ds_num]);

												if ($this->debug) {
													cacti_log("DEBUG: $message", false, 'SPIKEKILL');
												}

												$this->debug($message);

												$dsvalue = $last_num[$ds_num];
												$this->total_kills++;
												$kills++;
											}
										}
									}
								} elseif (is_numeric($dsvalue) && $dsvalue != 0) {
									$last_num[$ds_num] = $dsvalue;
								}

								break;
							case SPIKE_METHOD_ABSOLUTE:
								if ($timestamp >= $this->out_start && $timestamp <= $this->out_end) {
									if ($dsvalue >= $this->absmax) {
										if ($this->avgnan == 'avg') {
											$message = sprintf('Replacing dsvalue %s with average %s', $dsvalue, $rra[$rra_num][$ds_num]['average']);

											if ($this->debug) {
												cacti_log("DEBUG: $message", false, 'SPIKEKILL');
											}

											$this->debug($message);

											$dsvalue = sprintf('%1.10e', $rra[$rra_num][$ds_num]['average']);
											$kills++;
											$this->total_kills++;
										} elseif ($this->avgnan == 'nan') {
											$message = sprintf('Replacing dsvalue %s with NaN', $dsvalue);

											if ($this->debug) {
												cacti_log("DEBUG: $message", false, 'SPIKEKILL');
											}

											$this->debug($message);

											$dsvalue = 'NaN';
											$kills++;
											$this->total_kills++;
										} elseif ($this->avgnan == 'last' && isset($last_num[$ds_num])) {
											$message = sprintf('Replacing dsvalue %s with last value %s', $dsvalue, $last_num[$ds_num]);

											if ($this->debug) {
												cacti_log("DEBUG: $message", false, 'SPIKEKILL');
											}

											$this->debug($message);

											$dsvalue = $last_num[$ds_num];
											$this->total_kills++;
											$kills++;
										}
									}
								} elseif ($this->debug) {
									cacti_log("DEBUG: ignoring dsvalue {$dsvalue} as we are outside of the time range!", false, 'SPIKEKILL');
								}

								break;
						}

						$out_row .= '<v> ' . $dsvalue . '</v>';
						$ds_num++;
					}

					$out_row .= "</row>\n";

					$new_array[] = $out_row;
				} else {
					if (substr_count($line, '</rra>')) {
						$ds_minmax = [];
						$rra_num++;

						$kills    = 0;
						$last_num = [];
					} elseif (substr_count($line, '</database>')) {
						$ds_num++;

						$kills    = 0;
						$last_num = [];
					}

					$new_array[] = $line;
				}
			}
		}

		return $new_array;
	}

	private function removeComments(array &$output) : array {
		$new_array = [];

		if (cacti_sizeof($output)) {
			foreach ($output as $line) {
				$line = trim($line);

				if ($line == '') {
					continue;
				} else {
					// is there a comment, remove it
					$oline = $line;

					$comment_start = strpos($line, '<!--');

					if ($comment_start === false) {
						// do nothing no line
					} else {
						$comment_end = strpos($line, '-->');

						if ($comment_start == 0) {
							$line = trim(substr($line, $comment_end + 3));
						} else {
							$line = trim(substr($line,0,$comment_start - 1) . substr($line,$comment_end + 3));
						}

						if (str_contains($line, '<row>')) {
							// capture the timestamp
							$stamp     = trim(substr($oline, $comment_start + 4, $comment_end - 4));
							$stamp     = explode('/', $stamp);
							$timestamp = trim($stamp[1]);
							$line      = str_replace('<row><v>', "<row><timestamp> $timestamp </timestamp><v>", $line);
						}
					}

					if ($line != '') {
						$new_array[] = $line;
					}
				}
			}
		}

		return $new_array;
	}

	private function displayTime(mixed $pdp) : string {
		$total_time = $pdp * $this->step; // seconds

		if ($total_time < 60) {
			return $total_time . ' secs';
		} else {
			$total_time /= 60;

			if ($total_time < 60) {
				return $total_time . ' mins';
			} else {
				$total_time /= 60;

				if ($total_time < 24) {
					return $total_time . ' hours';
				} else {
					$total_time /= 24;

					return $total_time . ' days';
				}
			}
		}
	}

	private function debug(string $string) : void {
		if ($this->debug) {
			print 'DEBUG: ' . $string . "\n";
		}
	}

	private function processStandardDeviationCalculation(array $samples) : mixed {
		$my_samples = $samples;

		if ($this->out_start > 0) {
			$my_samples = [];

			foreach ($samples as $timestamp => $value) {
				if (($timestamp < $this->out_start || $timestamp > $this->out_end) && is_numeric($value)) {
					$my_samples[] = $value;
				}
			}
		}

		return $this->calculateStandardDeviation($my_samples);
	}

	private function calculateStandardDeviation(array $items) : mixed {
		$sum         = 0;
		$total_items = 0;

		// remove NaN entries from the data set
		if (cacti_sizeof($items)) {
			foreach ($items as $key => $value) {
				if (is_numeric($value)) {
					$total_items++;

					$sum += $value;
				} else {
					unset($items[$key]);
				}
			}
		}

		if ($total_items < 2) {
			return false;
		}

		$mean  = $sum / $total_items;
		$carry = 0.0;

		foreach ($items as $val) {
			$d = ((float) $val) - $mean;
			$carry += $d * $d;
		}

		return sqrt($carry / $total_items);
	}
}
