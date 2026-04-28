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

// Extracted from the original cli/splice_rrd.php so SpliceRrdCommand can drive
// the same XML walk + RRDtool dance from a Symfony Console action without
// pulling in the entire procedural top-of-file argv parser.

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof(mixed $array) : int {
		return ($array === false || !is_array($array)) ? 0 : sizeof($array);
	}

	function cacti_count(mixed $array) : int {
		return ($array === false || !is_array($array)) ? 0 : count($array);
	}
}

if (!function_exists('is_resource_writable')) {
	function is_resource_writable(string $path) : bool {
		if ($path[strlen($path) - 1] == '/') {
			return is_resource_writable($path . uniqid('', true) . '.tmp');
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

/**
 * spliceRRDs - Walk the new RRD structure and, for each NaN/0 entry, look
 * up the closest known-good value in the old (flattened) RRD data and
 * splice it in.
 */
function spliceRRDs(array &$new_rrd, array &$old_flat, array &$old_dsnames) : void {
	if (!cacti_sizeof($new_rrd) || !cacti_sizeof($old_flat)) {
		print 'FATAL: One of RRD\'s is Invalid' . PHP_EOL;

		return;
	}

	if (!isset($new_rrd['rra'])) {
		print 'FATAL: One of RRA\'s is Invalid' . PHP_EOL;

		return;
	}

	foreach ($new_rrd['rra'] as $rra_num => $rra) {
		$cf  = $new_rrd['rra'][$rra_num]['cf'];
		$pdp = $new_rrd['rra'][$rra_num]['pdp_per_row'];

		if (!isset($rra['database'])) {
			print 'FATAL: RRA database is Invalid' . PHP_EOL;

			continue;
		}

		foreach ($rra['database'] as $cdp_ds_num => $value) {
			$dsname    = $new_rrd['ds'][$cdp_ds_num]['name'];
			$olddsnum  = $old_dsnames[$dsname];
			$last_good = 'NaN';

			debug("Splicing DSName $dsname NewId $cdp_ds_num OldId $olddsnum");

			foreach ($value as $time => $v) {
				if ($v == 'NaN' || $v == 0) {
					if ($time < $old_flat['mintime']) {
						continue;
					}

					$old_value = getOldRRDValue($old_flat, $olddsnum, $cf, $time);

					if ($old_value != 'NaN') {
						$last_good                                                = $old_value;
						$new_rrd['rra'][$rra_num]['database'][$cdp_ds_num][$time] = $old_value;
					} elseif ($last_good != 'NaN') {
						$new_rrd['rra'][$rra_num]['database'][$cdp_ds_num][$time] = $last_good;
					}
				} else {
					$last_good = $v;
				}
			}
		}
	}
}

function getOldRRDValue(array &$old_flat, int $dsnum, string $cf, int $time) : string {
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

		while ($row = $result->fetchArray()) {
			return $row['value'];
		}

		return 'NaN';
	}

	if (!isset($old_flat[$dsnum][$cf])) {
		debug("CF $cf Not found in flattened data.");

		return 'NaN';
	}

	$first = true;

	foreach ($old_flat[$dsnum][$cf] as $timestamp => $data) {
		if ($first && $time > $timestamp) {
			debug("No Good data found.  Timestamp $time newer than the newest timestamp $timestamp");

			return 'NaN';
		}

		if ($time >= $timestamp) {
			debug("Good for $time offset is " . number_format(abs($time - $timestamp), 0));

			return $data;
		}

		$first = false;
	}

	debug("No Good data found.  Timestamp $time");

	return 'NaN';
}

function recreateXML(array $new_rrd) : string {
	$rrd = "<rrd>\n";
	$rrd .= "\t<version> " . $new_rrd['version'] . " </version>\n";
	$rrd .= "\t<step> " . $new_rrd['step'] . " </step>\n";
	$rrd .= "\t<lastupdate> " . $new_rrd['lastupdate'] . " </lastupdate>\n";

	foreach ($new_rrd['ds'] as $ds) {
		$rrd .= "\t<ds>\n";
		$rrd .= "\t\t<name> " . $ds['name'] . " </name>\n";
		$rrd .= "\t\t<type> " . $ds['type'] . " </type>\n";
		$rrd .= "\t\t<minimal_heartbeat> " . $ds['minimal_heartbeat'] . " </minimal_heartbeat>\n";
		$rrd .= "\t\t<min> " . $ds['min'] . " </min>\n";
		$rrd .= "\t\t<max> " . $ds['max'] . " </max>\n";
		$rrd .= "\t\t<last_ds> " . $ds['last_ds'] . " </last_ds>\n";
		$rrd .= "\t\t<value> " . $ds['value'] . " </value>\n";
		$rrd .= "\t\t<unknown_sec> " . $ds['unknown_sec'] . " </unknown_sec>\n";
		$rrd .= "\t</ds>\n";
	}

	foreach ($new_rrd['rra'] as $rra_num => $rra) {
		$output = [];

		$rrd .= "\t<rra>\n";
		$rrd .= "\t\t<cf> " . $rra['cf'] . " </cf>\n";
		$rrd .= "\t\t<pdp_per_row> " . $rra['pdp_per_row'] . " </pdp_per_row>\n";
		$rrd .= "\t\t<params>\n";
		$rrd .= "\t\t\t<xff> " . $rra['params']['xff'] . " </xff>\n";
		$rrd .= "\t\t</params>\n";
		$rrd .= "\t\t<cdp_prep>\n";

		foreach ($new_rrd['rra'][$rra_num]['cdp_prep'] as $cdp_ds_num => $pdp) {
			$rrd .= "\t\t\t<ds>\n";
			$rrd .= "\t\t\t\t<primary_value> " . $pdp['primary_value'] . " </primary_value>\n";
			$rrd .= "\t\t\t\t<secondary_value> " . $pdp['secondary_value'] . " </secondary_value>\n";
			$rrd .= "\t\t\t\t<value> " . $pdp['value'] . " </value>\n";
			$rrd .= "\t\t\t\t<unknown_datapoints> " . $pdp['unknown_datapoints'] . " </unknown_datapoints>\n";
			$rrd .= "\t\t\t</ds>\n";

			foreach ($new_rrd['rra'][$rra_num]['database'] as $dsnum => $v) {
				foreach ($v as $time => $value) {
					$output[$time][$dsnum] = $value;
				}
			}
		}

		$rrd .= "\t\t</cdp_prep>\n";
		$rrd .= "\t\t<database>\n";

		foreach ($output as $time => $v) {
			$rrd .= "\t\t\t<row>";

			foreach ($v as $value) {
				$rrd .= '<v> ' . $value . ' </v>';
			}

			$rrd .= "</row>\n";
		}

		$rrd .= "\t\t</database>\n";
		$rrd .= "\t</rra>\n";
	}

	$rrd .= '</rrd>';

	return $rrd;
}

function memoryUsage() : void {
	global $time;

	$mem_usage = memory_get_usage(true);

	if ($mem_usage < 1024) {
		$memstr = $mem_usage . ' B';
	} elseif ($mem_usage < 1048576) {
		$memstr = round($mem_usage / 1024, 2) . ' KB';
	} else {
		$memstr = round($mem_usage / 1048576, 2) . ' MB';
	}

	print 'NOTE: Time:' . round(microtime(true) - $time, 2) . ', RUsage:' . $memstr . PHP_EOL;
}

function flattenXML(array &$xml) : array {
	global $debug;

	$newxml   = [];
	$maxarray = [];
	$mintime  = 'NaN';

	if (cacti_sizeof($xml['rra'])) {
		foreach ($xml['rra'] as $rraid => $data) {
			$cf = $data['cf'];

			foreach ($data['database'] as $dsid => $timedata) {
				$i = 0;

				if (isset($maxarray[$dsid][$cf])) {
					$maxtoload = $maxarray[$dsid][$cf];
				} else {
					$maxtoload = 9999999999999;
				}

				foreach ($timedata as $timestamp => $value) {
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

		foreach ($newxml as $dsid => $entries) {
			foreach ($entries as $cf => $timedata) {
				ksort($timedata);

				$last_data = 0;

				foreach ($timedata as $timestamp => $cell) {
					if ($cell == 'NaN') {
						$timedata[$timestamp] = $last_data;
					} else {
						$last_data = $cell;
					}
				}

				krsort($timedata);

				$newxml[$dsid][$cf] = $timedata;
			}
		}
	}

	$newxml['mintime'] = $mintime;

	return $newxml;
}

function getMaxValue(array &$data) : float {
	$max = 0;

	foreach ($data as $value) {
		if ($value != 'NaN' && $value > $max) {
			$max = $value;
		}
	}

	return $max;
}

function getAvgValue(mixed &$data) : float {
	$entries = cacti_sizeof($data);
	$total   = array_sum($data);

	if ($entries) {
		return $total / $entries;
	}

	return 0;
}

function processXML(array &$output) : array {
	$rrd        = [];
	$dsnames    = [];
	$rra_num    = 0;
	$ds_num     = 0;
	$cdp_ds_num = 0;
	$in_ds      = false;
	$in_rra     = false;
	$in_parm    = false;
	$in_cdp     = false;
	$in_cdp_ds  = false;

	if (cacti_sizeof($output)) {
		foreach ($output as $line) {
			if (substr_count($line, '<row>')) {
				$line   = trim(str_replace('<row>', '', str_replace('</row>', '', $line)));
				$larray = explode('<v>', $line);
				$time   = trim(str_replace('<date>', '', str_replace('</date>', '', $larray[0])));

				array_shift($larray);
				$tdsno  = 0;

				foreach ($larray as $l) {
					$value                                           = trim(str_replace('</v>', '', $l));
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
		foreach ($dsnames as $index => $name) {
			$rrd['dsnames'][$name] = $index;
		}
	}

	return $rrd;
}

function createRRDFileFromXML(string $xmlfile, string $rrdfile) : void {
	global $rrdtool;

	print 'NOTE: Re-Importing \'' . $xmlfile . '\' to \'' . $rrdfile . '\'' . PHP_EOL;

	$output  = [];
	$command = cacti_escapeshellcmd($rrdtool) . ' restore -f -r ' . cacti_escapeshellarg($xmlfile) . ' ' . cacti_escapeshellarg($rrdfile);

	exec($command, $output, $return_var);

	if ($return_var == 0) {
		print "NOTE: File $rrdfile Restored Correctly" . PHP_EOL;
	} else {
		print "WARNING: File $rrdfile Encountered Errors.  Errors below:" . PHP_EOL;

		foreach ($output as $l) {
			print "WARNING: $l" . PHP_EOL;
		}
	}
}

function XMLrip(string $tag, string $line) : string {
	return trim(str_replace("<$tag>", '', str_replace("</$tag>", '', $line)));
}

function preProcessXML(array &$output) : array {
	$new_array = [];

	if (cacti_sizeof($output)) {
		foreach ($output as $line) {
			$line = trim($line);
			$date = '';

			if ($line == '') {
				continue;
			}

			$comment_start = strpos($line, '<!--');

			if ($comment_start !== false) {
				$comment_end = strpos($line, '-->');

				$row = strpos($line, '<row>');

				if ($row > 0) {
					$date = trim(substr($line, strpos($line, '/') + 1, 11));
				}

				if ($comment_start == 0) {
					$line = trim(substr($line, $comment_end + 3));
				} else {
					$line = trim(substr($line, 0, $comment_start - 1) . substr($line, $comment_end + 3));
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

	return $new_array;
}

function debug(string $string) : void {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . trim($string) . PHP_EOL;
	}
}

function createTable() : object {
	$db = new SQLite3(':memory:');

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

function loadTable(object $db, array &$records) : void {
	$db->exec('BEGIN TRANSACTION');

	$sql = '';

	foreach ($records as $dsid => $cfdata) {
		if (is_numeric($dsid)) {
			foreach ($cfdata as $cf => $timedata) {
				$i = 0;

				foreach ($timedata as $timestamp => $value) {
					$sql .= ($sql != '' ? ', ' : '') . '(' . $dsid . ',"' . $cf . '",' . $timestamp . ', ' . $value . ')';
					$i++;

					if ($i > 50) {
						if ($sql != '') {
							$db->exec("INSERT INTO dsData
								(dsid, cf, timestamp, value)
								VALUES $sql");
						}

						$sql = '';
						$i   = 0;
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

	$db->exec('COMMIT TRANSACTION');
}

/**
 * splice_rrd_run - Drive the full splice flow once arguments have already been
 * validated and resolved. Returns 0 on success, non-zero on failure.
 */
function splice_rrd_run(string $oldrrd, string $newrrd, string $finrrd, string $owner, bool $overwrite, bool $dryrun, bool $debugFlag) : int {
	global $debug, $rrdtool, $use_db, $db, $time, $tempdir, $seed;

	$debug = $debugFlag;
	$time  = microtime(true);

	if (!file_exists($oldrrd)) {
		print 'FATAL: File \'' . $oldrrd . '\' does not exist.' . PHP_EOL;

		return 9;
	}

	if (!is_resource_writable($oldrrd)) {
		print 'FATAL: File \'' . $oldrrd . '\' is not writable by this account.' . PHP_EOL;

		return 8;
	}

	if (!file_exists($newrrd)) {
		print 'FATAL: File \'' . $newrrd . '\' does not exist.' . PHP_EOL;

		return 9;
	}

	if (!is_resource_writable($newrrd)) {
		print 'FATAL: File \'' . $newrrd . '\' is not writable by this account.' . PHP_EOL;

		return 8;
	}

	if ($overwrite && $finrrd === '') {
		$finrrd = $newrrd;
	}

	if ($finrrd === '') {
		$finrrd = dirname($newrrd) . '/' . basename($newrrd) . '.new';
	}

	if (!is_resource_writable(dirname($finrrd) . '/') || (file_exists($finrrd) && !is_resource_writable($finrrd))) {
		print 'FATAL: File \'' . $finrrd . '\' is not writable by this account.' . PHP_EOL;

		return 8;
	}

	$use_db = class_exists('SQLite3');

	if (function_exists('read_config_option')) {
		$rrdtool = read_config_option('path_rrdtool');
	}

	if (!isset($rrdtool) || !file_exists($rrdtool)) {
		$rrdtool = file_exists('/usr/bin/rrdtool') ? '/usr/bin/rrdtool' : '/usr/local/bin/rrdtool';
	}

	$response = shell_exec(cacti_escapeshellcmd($rrdtool));

	if (!strlen((string) $response)) {
		print 'FATAL: RRDTool not found in configuration or path.' . PHP_EOL;

		return 1;
	}

	$response_array = explode(' ', (string) $response);
	print 'NOTE: Using ' . $response_array[0] . ' Version ' . ($response_array[1] ?? '?') . PHP_EOL;

	$seed = mt_rand();

	$tempdir    = str_starts_with(PHP_OS, 'WIN') ? (string) getenv('TEMP') : '/tmp';
	$oldxmlfile = $tempdir . '/' . str_replace('.rrd', '', basename($oldrrd)) . '.dump.' . $seed;
	$seed++;
	$newxmlfile = $tempdir . '/' . str_replace('.rrd', '', basename($newrrd)) . '.dump.' . $seed;

	$rrdtool_q = cacti_escapeshellcmd($rrdtool);

	debug("Creating XML file '$oldxmlfile' from '$oldrrd'");
	shell_exec($rrdtool_q . ' dump ' . cacti_escapeshellarg($oldrrd) . ' > ' . cacti_escapeshellarg($oldxmlfile));

	debug("Creating XML file '$newxmlfile' from '$newrrd'");
	shell_exec($rrdtool_q . ' dump ' . cacti_escapeshellarg($newrrd) . ' > ' . cacti_escapeshellarg($newxmlfile));

	if (!file_exists($oldxmlfile)) {
		print 'FATAL: RRDtool Command Failed on \'' . $oldrrd . '\'.' . PHP_EOL;

		return 12;
	}

	$old_output = file($oldxmlfile);
	unlink($oldxmlfile);

	if (!file_exists($newxmlfile)) {
		print 'FATAL: RRDtool Command Failed on \'' . $newrrd . '\'.' . PHP_EOL;

		return 12;
	}

	$new_output = file($newxmlfile);
	unlink($newxmlfile);

	print 'NOTE: RRDfile will be written to \'' . $finrrd . '\'' . PHP_EOL;

	$old_output = preProcessXML($old_output);
	$old_rrd    = processXML($old_output);
	$old_flat   = flattenXML($old_rrd);

	if ($use_db) {
		$db = createTable();

		loadTable($db, $old_flat);
	}

	$new_output = preProcessXML($new_output);
	$new_rrd    = processXML($new_output);

	spliceRRDs($new_rrd, $old_flat, $old_rrd['dsnames']);

	$new_xml = recreateXML($new_rrd);

	file_put_contents($newxmlfile, $new_xml);

	if (!$dryrun) {
		createRRDFileFromXML($newxmlfile, $finrrd);
	}

	@unlink($newxmlfile);

	if ($owner !== '') {
		if (get_current_user() === 'root') {
			@chown($finrrd, $owner);
		} else {
			print 'ERROR: Unable to change owner.  You must run as root to change owner' . PHP_EOL;
		}
	}

	memoryUsage();

	return 0;
}
