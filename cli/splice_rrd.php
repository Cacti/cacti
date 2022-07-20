#!/usr/bin/env php
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

/* work with both Cacti 1.x and Cacti 0.8.x */
if (file_exists(__DIR__ . '/../include/cli_check.php')) {
	require(__DIR__ . '/../include/cli_check.php');
} elseif (file_exists(__DIR__ . '/../include/global.php')) {
	/* do NOT run this script through a web browser */
	if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
		die('<br>This script is only meant to run at the command line.');
	}

	$no_http_headers = true;

	require(__DIR__ . '/../include/global.php');
} else {
	print "FATAL: Can not initialize the Cacti API" . PHP_EOL;
	exit(1);
}

// For legacy Cacti behavior
if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($object) {
		return cacti_sizeof($object);
	}
}

if (!function_exists('get_cacti_cli_version')) {
	function get_cacti_cli_version() {
		return db_fetch_cell('SELECT cacti FROM version');
	}
}

if (!function_exists('is_resource_writable')) {
	function is_resource_writable($path) {
		if ($path[strlen($path)-1]=='/') {
			return is_resource_writable($path.uniqid(mt_rand()).'.tmp');
		}

		if (file_exists($path)) {
			if (($f = @fopen($path, 'a'))) {
				fclose($f);
				return true;
			}

			return false;
		}

		if (($f = @fopen($path, 'w'))) {
			fclose($f);
			unlink($path);
			return true;
		}

		return false;
	}
}

ini_set('max_execution_time', '0');
ini_set('display_errors', 'On');

/* setup defaults */
$debug     = false;
$dryrun    = false;
$backup    = false;
$overwrite = false;
$user      = get_current_user();
$owner     = 'apache';
$ownerset  = false;
$oldrrd    = '';
$newrrd    = '';
$finrrd    = '';
$time      = microtime(true);

global $debug;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--oldrrd':
				$oldrrd = $value;

				if (!file_exists($oldrrd)) {
					print 'FATAL: File \'' . $oldrrd . '\' does not exist.' . PHP_EOL;
					exit(-9);
				}

				if (!is_resource_writable($oldrrd)) {
					print 'FATAL: File \'' . $oldrrd . '\' is not writable by this account.' . PHP_EOL;
					exit(-8);
				}

				break;
			case '--newrrd':
				$newrrd = $value;

				if (!file_exists($newrrd)) {
					print 'FATAL: File \'' . $newrrd . '\' does not exist.' . PHP_EOL;
					exit(-9);
				}

				if (!is_resource_writable($newrrd)) {
					print 'FATAL: File \'' . $newrrd . '\' is not writable by this account.' . PHP_EOL;
					exit(-8);
				}

				break;
			case '--finrrd':
				$finrrd = $value;

				if (!is_resource_writable(dirname($finrrd) . '/') || (file_exists($finrrd) && !is_resource_writable($finrrd))) {
					print 'FATAL: File \'' . $finrrd . '\' is not writable by this account.' . PHP_EOL;
					exit(-8);
				}

				break;
			case '-R':
			case '--owner':
				$owner = $value;
				$ownerset = true;

				break;
			case '-B':
			case '--backup':
				$backup = true;

				break;
			case '-O':
			case '--overwrite':
				$overwrite = true;

				break;
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '-D':
			case '--dryrun':
				$dryrun = true;

				break;
			case '-V':
			case '--version':
				display_version();
				exit(0);
			case '-H':
			case '--help':
				display_help();
				exit(0);
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit(-3);
		}
	}
}

/* additional error check */
if ($oldrrd == '') {
	print 'FATAL: You must specify a old RRDfile!' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-2);
}

if ($newrrd == '') {
	print 'FATAL: You must specify a New RRDfile!' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-2);
}

if ($overwrite && $finrrd == '') {
	$finrrd = $newrrd;
}

if ($finrrd == '') {
	print 'FATAL: You must specify a New RRDfile or use the overwrite option!' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-2);
}

debug('Entering Mainline');

/* let's see if we can find rrdtool */
global $rrdtool, $use_db, $db;

/* see if sqlLite is available */
if (class_exists('SQLite3')) {
	print 'NOTE: Using SQLite Database for performance.' . PHP_EOL;
	$use_db = true;
} else {
	print 'NOTE: Using Native Arrays due to lack of SQLite.' . PHP_EOL;
	$use_db = false;
}

/* verify the location of rrdtool */
$rrdtool = read_config_option('path_rrdtool');
if (!file_exists($rrdtool)) {
	if (substr_count(PHP_OS, 'WIN')) {
		$rrdtool = 'rrdtool.exe';
	} else {
		$rrdtool = 'rrdtool';
	}
}

$response = shell_exec($rrdtool);

if (strlen($response)) {
	$response_array = explode(' ', $response);
	print 'NOTE: Using ' . $response_array[0] . ' Version ' . $response_array[1] . PHP_EOL;
} else {
	print 'FATAL: RRDTool not found in configuation or path.' . PHP_EOL . 'Please insure RRDTool can be found using one of these methods!' . PHP_EOL;
	exit(-1);
}

/* determine the temporary file name */
$seed = mt_rand();
if (substr_count(PHP_OS, 'WIN')) {
	$tempdir  = getenv('TEMP');
	$oldxmlfile = $tempdir . '/' . str_replace('.rrd', '', basename($oldrrd)) . '.dump.' . $seed;
	$seed++;
	$newxmlfile = $tempdir . '/' . str_replace('.rrd', '', basename($newrrd)) . '.dump.' . $seed;
} else {
	$tempdir = '/tmp';
	$oldxmlfile = '/tmp/' . str_replace('.rrd', '', basename($oldrrd)) . '.dump.' . $seed;
	$seed++;
	$newxmlfile = '/tmp/' . str_replace('.rrd', '', basename($newrrd)) . '.dump.' . $seed;
}

if ($finrrd == '') {
	$finrrd = dirname($newrrd) . '/' . basename($newrrd) . '.new';
}

/* execute the dump commands */
debug("Creating XML file '$oldxmlfile' from '$oldrrd'");
shell_exec("$rrdtool dump $oldrrd > $oldxmlfile");

debug("Creating XML file '$newxmlfile' from '$newrrd'");
shell_exec("$rrdtool dump $newrrd > $newxmlfile");

/* read the xml files into arrays */
if (file_exists($oldxmlfile)) {
	$old_output = file($oldxmlfile);

	/* remove the temp file */
	unlink($oldxmlfile);
} else {
	print 'FATAL: RRDtool Command Failed on \'' . $oldrrd . '\'.  Please insure your RRDtool install is valid!' . PHP_EOL;
	exit(-12);
}

if (file_exists($newxmlfile)) {
	$new_output = file($newxmlfile);

	/* remove the temp file */
	unlink($newxmlfile);
} else {
	print 'FATAL: RRDtool Command Failed on \'' . $newrrd . '\'.  Please insure your RRDtool install is valid!' . PHP_EOL;
	exit(-12);
}

print 'NOTE: RRDfile will be written to \'' . $finrrd . '\'' . PHP_EOL;

// Read the old RRDfile into an array, flatten and remove gaps
debug("Reading XML From '$oldrrd'");

$old_output = preProcessXML($old_output);
$old_rrd    = processXML($old_output);
$old_flat   = flattenXML($old_rrd);

if ($use_db) {
	$db = createTable();

	loadTable($db, $old_flat);
}

// Read the new RRDfile into an array
debug("Reading XML From '$newrrd'");

$new_output = preProcessXML($new_output);
$new_rrd    = processXML($new_output);

// Splice new RRDfiles array with the flattened data
debug('Splicing RRDfiles');

spliceRRDs($new_rrd, $old_flat, $old_rrd['dsnames'], $db);

debug('Re-Creating XML File');
$new_xml = recreateXML($new_rrd);

debug('Writing XML File to Disk');
file_put_contents($newxmlfile, $new_xml);

/* finally update the file XML file and Reprocess the RRDfile */
if (!$dryrun) {
	debug('Creating New RRDfile');
	createRRDFileFromXML($newxmlfile, $finrrd);
}

/* remove the temp file */
unlink($newxmlfile);

/* change ownership */
if ($ownerset) {
	if ($user == 'root') {
		chown($finrrd, $owner);
	} else {
		print "ERROR: Unable to change owner.  You must run as root to change owner" . PHP_EOL;
	}
}

memoryUsage();

/** spliceRRDs - This function walks through the structure of the newrrd
 *  XML file array and for each value, if it's either '0' or 'NaN' the
 *  script will search the flattenedXML or SQLite table for the closest
 *  match and save that into the final array, that will then be written
 *  back out to an XML file and re-loaded into an RRDfile.
 */
function spliceRRDs(&$new_rrd, &$old_flat, &$old_dsnames) {
	if (cacti_sizeof($new_rrd) && cacti_sizeof($old_flat)) {
		if (isset($new_rrd['rra'])) {
			foreach($new_rrd['rra'] as $rra_num => $rra) {
				$cf  = $new_rrd['rra'][$rra_num]['cf'];
				$pdp = $new_rrd['rra'][$rra_num]['pdp_per_row'];
				if (isset($rra['database'])) {
					foreach($rra['database'] as $cdp_ds_num => $value) {
						$dsname    = $new_rrd['ds'][$cdp_ds_num]['name'];
						$olddsnum  = $old_dsnames[$dsname];
						$last_good = 'NaN';

						debug("Splicing DSName $dsname NewId $cdp_ds_num OldId $olddsnum");

						foreach($value as $time => $v) {
							if ($v == 'NaN' || $v == 0) {
								if ($time < $old_flat['mintime']) {
									continue;
								}

								$old_value = getOldRRDValue($old_flat, $olddsnum, $cf, $time);

								if ($old_value != 'NaN') {
									$last_good = $old_value;
									$new_rrd['rra'][$rra_num]['database'][$cdp_ds_num][$time] = $old_value;
								} elseif ($last_good != 'NaN') {
									$new_rrd['rra'][$rra_num]['database'][$cdp_ds_num][$time] = $last_good;
								}
							} else {
								$last_good = $v;
							}

							$new_value = $new_rrd['rra'][$rra_num]['database'][$cdp_ds_num][$time];

							//print "DSName: $dsname, NewVAlue: $new_value, OrigValue: $v, OldValue: $old_value\n";
						}
					}
				} else {
					print 'FATAL: RRA database is Invalid' . PHP_EOL;
				}
			}
		} else {
			print 'FATAL: One of RRA\'s is Invalid' . PHP_EOL;
		}
	} else {
		print 'FATAL: One of RRD\'s is Invalid' . PHP_EOL;
	}
}

/** getOldRRDValue - scan the flattened array for a good timestamp
 *  and return the nearest value for that timestamp.
 *
 *  The flattened array is sorted by timestamp in reverse order.
 *  If the SQLite table is available, this function will prefer
 *  that table over traversing the array.
 */
function getOldRRDValue(&$old_flat, $dsnum, $cf, $time) {
	global $use_db, $db;

	if ($use_db) {
		$stmt = $db->prepare("SELECT *
			FROM dsData
			WHERE dsid = $dsnum
			AND cf = '$cf'
			AND timestamp <= $time
			ORDER BY timestamp DESC
			LIMIT 1");

		$result = $stmt->execute();

		while($row = $result->fetchArray()) {
			return $row['value'];
		}

		return 'NaN';
	} else {
		if (!isset($old_flat[$dsnum][$cf])) {
			debug("CF $cf Not found in flattened data.");

			return 'NaN';
		} else {
			$first = true;

			foreach($old_flat[$dsnum][$cf] as $timestamp => $data) {
				if ($first && $time > $timestamp) {
					// The time is before any good data in the RRDfile
					debug("No Good data found.  Timestamp $time newer than the newest timestamp $timestamp");

					return 'NaN';
				} elseif ($time >= $timestamp) {
					debug("Good for $time offset is " . number_format(abs($time - $timestamp), 0));

					return $data;
				}

				$first = false;
			}

			debug("No Good data found.  Timestamp $time");

			return 'NaN';
		}
	}
}

/** recreateXML - Take the data from the modified XML and re-create the XML file that
 *  will then be turned back into an RRDfile.
 *
 *  The array structure is documented below.
 *
 *  $rrd['version'];
 *  $rrd['step'];
 *  $rrd['lastupdate'];
 *  $rrd['ds'][$ds_num]['name'];
 *  $rrd['ds'][$ds_num]['type'];
 *  $rrd['ds'][$ds_num]['minimal_heartbeat'];
 *  $rrd['ds'][$ds_num]['min'];
 *  $rrd['ds'][$ds_num]['max'];
 *  $rrd['ds'][$ds_num]['last_ds'];
 *  $rrd['ds'][$ds_num]['value'];
 *  $rrd['ds'][$ds_num]['unknown_sec'];
 *  $rrd['rra'][$rra_num]['cf'];
 *  $rrd['rra'][$rra_num]['pdp_per_row'];
 *  $rrd['rra'][$rra_num]['params']['xff'];
 *  $rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['primary_value'];
 *  $rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['secondary_value'];
 *  $rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['value'];
 *  $rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['unknown_datapoints'];
 *  $rrd['rra'][$rra_num]['database'][$cdp_ds_num]['time'];
 */
function recreateXML($new_rrd) {
	$rrd = "<rrd>\n";
	$rrd .= "\t<version> "    . $new_rrd['version']    . " </version>\n";
	$rrd .= "\t<step> "       . $new_rrd['step']       . " </step>\n";
	$rrd .= "\t<lastupdate> " . $new_rrd['lastupdate'] . " </lastupdate>\n";

	foreach($new_rrd['ds'] as $dsnum => $ds) {
		$rrd .= "\t<ds>\n";
		$rrd .= "\t\t<name> "        . $ds['name']        . " </name>\n";;
		$rrd .= "\t\t<type> "        . $ds['type']        . " </type>\n";;
		$rrd .= "\t\t<minimal_heartbeat> " . $ds['minimal_heartbeat'] . " </minimal_heartbeat>\n";;
		$rrd .= "\t\t<min> "         . $ds['min']         . " </min>\n";;
		$rrd .= "\t\t<max> "         . $ds['max']         . " </max>\n";;
		$rrd .= "\t\t<last_ds> "     . $ds['last_ds']     . " </last_ds>\n";;
		$rrd .= "\t\t<value> "       . $ds['value']       . " </value>\n";;
		$rrd .= "\t\t<unknown_sec> " . $ds['unknown_sec'] . " </unknown_sec>\n";;
		$rrd .= "\t</ds>\n";
	}

	foreach($new_rrd['rra'] as $rra_num => $rra) {
		$rrd .= "\t<rra>\n";
		$rrd .= "\t\t<cf> " . $rra['cf'] . " </cf>\n";
		$rrd .= "\t\t<pdp_per_row> " . $rra['pdp_per_row'] . " </pdp_per_row>\n";
		$rrd .= "\t\t<params>\n";
		$rrd .= "\t\t\t<xff> " . $rra['params']['xff'] . " </xff>\n";
		$rrd .= "\t\t</params>\n";
		$rrd .= "\t\t<cdp_prep>\n";

		foreach($new_rrd['rra'][$rra_num]['cdp_prep'] as $cdp_ds_num => $pdp) {
			$rrd .= "\t\t\t<ds>\n";
			$rrd .= "\t\t\t\t<primary_value> " . $pdp['primary_value'] . " </primary_value>\n";
			$rrd .= "\t\t\t\t<secondary_value> " . $pdp['secondary_value'] . " </secondary_value>\n";
			$rrd .= "\t\t\t\t<value> " . $pdp['value'] . " </value>\n";
			$rrd .= "\t\t\t\t<unknown_datapoints> " . $pdp['unknown_datapoints'] . " </unknown_datapoints>\n";
			$rrd .= "\t\t\t</ds>\n";

			$output = array();
			foreach($new_rrd['rra'][$rra_num]['database'] as $dsnum => $v) {
				foreach($v as $time => $value) {
					$output[$time][$dsnum] = $value;
				}
			}
		}

		$rrd .= "\t\t</cdp_prep>\n";
		$rrd .= "\t\t<database>\n";

		foreach($output as $time => $v) {
			$rrd .= "\t\t\t<row>";
			foreach($v as $dsnum => $value) {
				$rrd .= "<v> " . $value . " </v>";
			}
			$rrd .= "</row>\n";
		}

		$rrd .= "\t\t</database>\n";
		$rrd .= "\t</rra>\n";
	}

	$rrd .= "</rrd>";

	return $rrd;
}

/* memoryUsage - Report the peak memory usage of the php script */
function memoryUsage() {
	global $time;

	$mem_usage = memory_get_usage(true);

	if ($mem_usage < 1024)
		$memstr = $mem_usage . ' B';
	elseif ($mem_usage < 1048576)
		$memstr = round($mem_usage/1024,2) . ' KB';
	else
		$memstr = round($mem_usage/1048576,2) . ' MB';

	print 'NOTE: Time:' . round(microtime(true)-$time, 2) . ', RUsage:' . $memstr . PHP_EOL;
}

/** flattenXML - Take all the data from the various data sources and
 *  by Consolidation Function, sort the values by timestamp so that
 *  the new RRDfile can pull values that make sense to fill in the
 *  time where there may be no data.
 *
 *  Additionally, remove any NaN values and replace with the last
 *  good known value to fill gaps in the graphs.
 *
 *  The form of the output array will be as follows:
 *
 *  $newxml[$datasourceid][$cf][$timestamp] = value
 *  $newxml['mintime'] = value
 *
 *  The data will only go back as far as the source RRDfile.
 *
 */
function flattenXML(&$xml) {
	global $debug;

	$newxml   = array();
	$maxarray = array();
	$mintime  = 'NaN';

	if (cacti_sizeof($xml['rra'])) {
		foreach($xml['rra'] as $rraid => $data) {
			$cf = $data['cf'];

			foreach($data['database'] as $dsid => $timedata) {
				$i = 0;

				// Don't load data from less granular RRA's
				if (isset($maxarray[$dsid][$cf])) {
					$maxtoload = $maxarray[$dsid][$cf];
				} else {
					$maxtoload = 9999999999999;
				}

				foreach($timedata as $timestamp => $value) {
					if ($i == 0 && $value != 'NaN') {
						$maxarray[$dsid][$cf] = $timestamp;
						$i++;
					}

					if ($timestamp <= $maxtoload) {
						$newxml[$dsid][$cf][$timestamp] = $value;

						if ($mintime == 'NaN') {
							$mintime = $timestamp;
						} elseif ($timestamp < $mintime) {
							$mintime = $timestamp;
						}
					}
				}
			}
		}

		foreach($newxml as $dsid => $data) {
			foreach($data as $cf => $timedata) {
				// Sort the data
				ksort($timedata);

				// Get rid of NaN
				$last_data = 0;
				foreach($timedata as $timestamp => $data) {
					if ($data == 'NaN') {
						$timedata[$timestamp] = $last_data;
					} else {
						$last_data = $data;
					}
				}

				krsort($timedata);

				$newxml[$dsid][$cf] = $timedata;

				if ($debug) {
					$stats = sprintf("DS:%2d, CF:%8s, Vals:%10s, Max:%10s, Avg:%10s",
						$dsid,
						$cf,
						number_format(cacti_sizeof($timedata)),
						number_format(getMaxValue($timedata), 4),
						number_format(getAvgValue($timedata), 4));

					debug($stats);
				}
			}
		}
	}

	$newxml['mintime'] = $mintime;

	return $newxml;
}

/** getMaxValue - Obtains tha max value from the timestamp array
 *  for use in debug output.
 */
function getMaxValue(&$data) {
	$max = 0;
	foreach($data as $timestamp => $value) {
		if ($value != 'NaN' && $value > $max) {
			$max = $value;
		}
	}

	return $max;
}

/** getAvgValue - Obtains tha average value from the timestamp array
 *  for use in debug output.
 */
function getAvgValue(&$data) {
	$entries = cacti_sizeof($data);
	$total   = array_sum($data);

	if ($entries) {
		return $total / $entries;
	} else {
		return 0;
	}
}

/** processXML - Read all the XML into an array. The format of the array
 *  will be as show below.  This way it can be processed reverted back
 *  to array format at the end of the merge process.
 *
 *  $rrd['version'];
 *  $rrd['step'];
 *  $rrd['lastupdate'];
 *  $rrd['ds'][$ds_num]['name'];
 *  $rrd['ds'][$ds_num]['type'];
 *  $rrd['ds'][$ds_num]['minimal_heartbeat'];
 *  $rrd['ds'][$ds_num]['min'];
 *  $rrd['ds'][$ds_num]['max'];
 *  $rrd['ds'][$ds_num]['last_ds'];
 *  $rrd['ds'][$ds_num]['value'];
 *  $rrd['ds'][$ds_num]['unknown_sec'];
 *  $rrd['rra'][$rra_num]['cf'];
 *  $rrd['rra'][$rra_num]['pdp_per_row'];
 *  $rrd['rra'][$rra_num]['params']['xff'];
 *  $rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['primary_value'];
 *  $rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['secondary_value'];
 *  $rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['value'];
 *  $rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['unknown_datapoints'];
 *  $rrd['rra'][$rra_num]['database'][$cdp_ds_num]['time'];
 */
function processXML(&$output) {
	$rrd        = array();
	$dsnames    = array();
	$rra_num    = 0;
	$ds_num     = 0;
	$cdp_ds_num = 0;
	$in_ds      = false;
	$in_rra     = false;
	$in_db      = false;
	$in_parm    = false;
	$in_cdp     = false;
	$in_cdp_ds  = false;

	if (cacti_sizeof($output)) {
		foreach($output as $line) {
			if (substr_count($line, '<row>')) {
				$line   = trim(str_replace('<row>', '', str_replace('</row>', '', $line)));
				$larray = explode('<v>', $line);
				$time   = trim(str_replace('<date>', '', str_replace('</date>', '', $larray[0])));

				array_shift($larray);
				$tdsno  = 0;
				foreach($larray as $l) {
					$value = trim(str_replace('</v>', '', $l));
					$rrd['rra'][$rra_num]['database'][$tdsno][$time] = $value;
					$tdsno++;
				}
			} elseif (substr_count($line, '<lastupdate>')) {
				$rrd['lastupdate'] = XMLrip('lastupdate', $line);
			} elseif (substr_count($line, '<version>')) {
				$line = trim(str_replace('<rrd>', '', $line));
				$rrd['version'] = XMLrip('version', $line);
			} elseif (substr_count($line, '<step>')) {
				$rrd['step'] = XMLrip('step', $line);
			} elseif (substr_count($line, '<rra>')) {
				$in_rra = true;
			} elseif (substr_count($line, '</rra>')) {
				$in_rra = false;
				$cdp_ds_num = 0;
				$rra_num++;
			} elseif (substr_count($line, '<ds>')) {
				if (!$in_cdp) {
					$in_ds = true;
				}
			} elseif (substr_count($line, '</ds>')) {
				if ($in_ds) {
					$in_ds = false;
					$ds_num++;
				} else {
					$in_cdp_ds = false;
					$cdp_ds_num++;
				}
			} elseif (substr_count($line, '<cdp_prep>')) {
				$in_cdp = true;
			} elseif (substr_count($line, '</cdp_prep>')) {
				$in_cdp = false;
			} elseif (substr_count($line, '<params>')) {
				$in_parm = true;
			} elseif (substr_count($line, '</params>')) {
				$in_parm = false;
			} elseif (substr_count($line, '<name>')) {
				$rrd['ds'][$ds_num]['name'] = XMLrip('name', $line);
				$dsnames[] = XMLrip('name', $line);
			} elseif (substr_count($line, '<type>')) {
				$rrd['ds'][$ds_num]['type'] = XMLrip('type', $line);
			} elseif (substr_count($line, '<minimal_heartbeat>')) {
				$rrd['ds'][$ds_num]['minimal_heartbeat'] = XMLrip('minimal_heartbeat', $line);
			} elseif (substr_count($line, '<max>')) {
				$rrd['ds'][$ds_num]['max']  = XMLrip('max', $line);
			} elseif (substr_count($line, '<min>')) {
				$rrd['ds'][$ds_num]['min']  = XMLrip('min', $line);
			} elseif (substr_count($line, '<last_ds>')) {
				$rrd['ds'][$ds_num]['last_ds'] = XMLrip('last_ds', $line);
			} elseif (substr_count($line, '<value>')) {
				if ($in_rra) {
					$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['value'] = XMLrip('value', $line);
				} else {
					$rrd['ds'][$ds_num]['value'] = XMLrip('value', $line);
				}
			} elseif (substr_count($line, '<unknown_sec>')) {
				$rrd['ds'][$ds_num]['unknown_sec'] = XMLrip('unknown_sec', $line);
			} elseif (substr_count($line, '<cf>')) {
				$rrd['rra'][$rra_num]['cf'] = XMLrip('cf', $line);
			} elseif (substr_count($line, '<pdp_per_row>')) {
				$rrd['rra'][$rra_num]['pdp_per_row'] = XMLrip('pdp_per_row', $line);
			} elseif (substr_count($line, '<xff>')) {
				$rrd['rra'][$rra_num]['params']['xff'] = XMLrip('xff', $line);
			} elseif (substr_count($line, '<primary_value>')) {
				$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['primary_value'] = XMLrip('primary_value', $line);
			} elseif (substr_count($line, '<secondary_value>')) {
				$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['secondary_value'] = XMLrip('secondary_value', $line);
			} elseif (substr_count($line, '<unknown_datapoints>')) {
				$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['unknown_datapoints'] = XMLrip('unknown_datapoints', $line);
			}
		}
	}

	if (cacti_sizeof($dsnames)) {
		foreach($dsnames as $index => $name) {
			$rrd['dsnames'][$name] = $index;
		}
	}

	return $rrd;
}

/* All Functions */
function createRRDFileFromXML($xmlfile, $rrdfile) {
	global $rrdtool;

	/* execute the dump command */
	print 'NOTE: Re-Importing \'' . $xmlfile . '\' to \'' . $rrdfile . '\'' . PHP_EOL;
	$response = shell_exec("$rrdtool restore -f -r $xmlfile $rrdfile");
	if (strlen($response)) print $response . PHP_EOL;
}

function XMLrip($tag, $line) {
	return trim(str_replace("<$tag>", '', str_replace("</$tag>", '', $line)));
}

function writeXMLFile($output, $xmlfile) {
	return file_put_contents($xmlfile, $output);
}

function backupRRDFile($rrdfile) {
	global $tempdir, $seed, $html;

	$backupdir = $tempdir;

	if (file_exists($backupdir . '/' . basename($rrdfile))) {
		$newfile = basename($rrdfile) . '.' . $seed;
	} else {
		$newfile = basename($rrdfile);
	}

	print 'NOTE: Backing Up \'' . $rrdfile . '\' to \'' . $backupdir . '/' .  $newfile . '\'' . PHP_EOL;

	return copy($rrdfile, $backupdir . '/' . $newfile);
}

/** preProcessXML - This function strips the timestamps off the XML dump
 *  and loads that data into an array along with the remainder of the
 *  XML data for future processing.
 */
function preProcessXML(&$output) {
	if (cacti_sizeof($output)) {
		foreach($output as $line) {
			$line = trim($line);
			$date = '';
			if ($line == '') {
				continue;
			} else {
				/* is there a comment, remove it */
				$comment_start = strpos($line, '<!--');
				if ($comment_start === false) {
					/* do nothing no line */
				} else {
					$comment_end = strpos($line, '-->');
					$row = strpos($line, '<row>');

					if ($row > 0) {
						$date = trim(substr($line,$comment_start+30,11));
					}

					if ($comment_start == 0) {
						$line = trim(substr($line, $comment_end+3));
					} else {
						$line = trim(substr($line,0,$comment_start-1) . substr($line,$comment_end+3));
					}

					if (!empty($date)) {
						$line = str_replace('<row>', "<row><date> $date </date>", $line);
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

function debug($string) {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . trim($string) . PHP_EOL;
	}
}

/** createTable - This function creates a SQLite memory table
 *  to hold the flattened XML file in for replay.
 */
function createTable() {
	/* table in memory */
	$db = new SQLite3(':memory:');

	/* create the table */
	$db->exec('CREATE TABLE dsData (
		dsid             int,
		cf               char(10) NOT NULL,
		timestamp        int,
		value            real NOT NULL,
		PRIMARY KEY (dsid, cf, timestamp))');

	$db->exec('CREATE INDEX dsid_cf_timestamp ON dsData (dsid, cf, timestamp)');
	$db->exec('CREATE INDEX timestamp ON dsData (timestamp)');

	return $db;
}

/** loadTable - This function loads the flattened XML file into
 *  the SQLite database for replaying the RRDfile dump data
 *  into the new XML file.
 */
function loadTable($db, &$records) {
	$db->exec("BEGIN TRANSACTION");

	$sql = '';
	foreach($records as $dsid => $cfdata) {
		if (is_numeric($dsid)) {
			foreach($cfdata as $cf => $timedata) {
				$i = 0;
				foreach($timedata as $timestamp => $value) {
					$sql .= ($sql != '' ? ', ':'') . '(' . $dsid . ',"' . $cf . '",' . $timestamp . ', ' . $value . ')';
					$i++;

					if ($i > 50) {
						if ($sql != '') {
							$db->exec("INSERT INTO dsData
								(dsid, cf, timestamp, value)
								VALUES $sql");
						}

						$sql = '';
						$i = 0;
					}
				}

				if ($sql != '') {
					$db->exec("INSERT INTO dsData
						(dsid, cf, timestamp, value)
						VALUES $sql");
				}

				$sql = '';
			}
		}
	}

	$db->exec("COMMIT TRANSACTION");
}


function display_version() {
	if (!defined('COPYRIGHT_YEARS')) {
		define('COPYRIGHT_YEARS', '2004-2021');
	}

	$version = get_cacti_cli_version();

	print 'Cacti RRDfile Splicer Utility, Version ' . $version . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/** display_help - Displays usage information about how to utilize
 *  this program.
 */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: splice_rrd.php --oldrrd=file --newrrd=file [--finrrd=file]' . PHP_EOL;
	print '       [--owner=apache] [--debug] [--dryrun]' . PHP_EOL . PHP_EOL;

	print 'The splice_rrd.php file is designed to allow two RRDfiles to be merged.' . PHP_EOL . PHP_EOL;

	print 'This utility can effectively change the resolution/step of an RRDfile' . PHP_EOL;
	print 'so long as the new RRDfile already has the correct step.' . PHP_EOL . PHP_EOL;

	print 'The Old and New input parameters are mandatory.  If the finrrd option is' . PHP_EOL;
	print 'not specified, it will be the newrrd plus a timestamp.' . PHP_EOL . PHP_EOL;

	print '--oldrrd=file    - The old RRDfile that contains old data.' . PHP_EOL;
	print '--newrrd=file    - The new RRDfile that contains more recent data.' . PHP_EOL;
	print '--finrrd=file    - The final RRDfile that contains data from both rrdfiles.' . PHP_EOL . PHP_EOL;
	print '--owner=apache   - Change the owner of the resulting file.  Note requires root.' . PHP_EOL;
	print '--dryrun         - Simply test the splicing of the RRDfiles, don\'t write.' . PHP_EOL . PHP_EOL;
}
