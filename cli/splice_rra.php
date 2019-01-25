#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

require(__DIR__ . '/../include/cli_check.php');

ini_set('max_execution_time', '0');
ini_set('display_errors', 'On');

/* setup defaults */
$debug     = FALSE;
$dryrun    = FALSE;
$backup    = FALSE;
$overwrite = FALSE;
$oldrrd    = '';
$newrrd    = '';
$finrrd    = '';
$time      = time();

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
					echo 'FATAL: File \'' . $oldrrd . '\' does not exist.' . PHP_EOL;
					exit(-9);
				}

				if (!is_resource_writable($oldrrd)) {
					echo 'FATAL: File \'' . $oldrrd . '\' is not writable by this account.' . PHP_EOL;
					exit(-8);
				}

				break;
			case '--newrrd':
				$newrrd = $value;

				if (!file_exists($newrrd)) {
					echo 'FATAL: File \'' . $newrrd . '\' does not exist.' . PHP_EOL;
					exit(-9);
				}

				if (!is_resource_writable($newrrd)) {
					echo 'FATAL: File \'' . $newrrd . '\' is not writable by this account.' . PHP_EOL;
					exit(-8);
				}

				break;
			case '--finrrd':
				$finrrd = $value;

				if (!is_resource_writable(dirname($finrrd) . '/') || (file_exists($finrrd) && !is_resource_writable($finrrd))) {
					echo 'FATAL: File \'' . $finrrd . '\' is not writable by this account.' . PHP_EOL;
					exit(-8);
				}

				break;
			case '-B':
			case '--backup':
				$backup = TRUE;

				break;
			case '-O':
			case '--overwrite':
				$overwrite = TRUE;

				break;
			case '-d':
			case '--debug':
				$debug = TRUE;

				break;
			case '-D':
			case '--dryrun':
				$dryrun = TRUE;

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
	echo 'FATAL: You must specify a old RRDfile!' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-2);
}

if ($newrrd == '') {
	echo 'FATAL: You must specify a New RRDfile!' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-2);
}

if ($overwrite && $finrrd == '') {
	$finrrd = $newrrd;
}

if ($finrrd == '') {
	echo 'FATAL: You must specify a New RRDfile or use the overwrite option!' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-2);
}

/* let's see if we can find rrdtool */
global $rrdtool;

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
	echo 'NOTE: Using ' . $response_array[0] . ' Version ' . $response_array[1] . PHP_EOL;
}else{
	echo 'FATAL: RRDTool not found in configuation or path.' . PHP_EOL . 'Please insure RRDTool can be found using one of these methods!' . PHP_EOL;
	exit(-1);
}

/* determine the temporary file name */
$seed = mt_rand();
if (substr_count(PHP_OS, 'WIN')) {
	$tempdir  = getenv('TEMP');
	$oldxmlfile = $tempdir . '/' . str_replace('.rrd', '', basename($oldrrd)) . '.dump.' . $seed;
	$seed++;
	$newxmlfile = $tempdir . '/' . str_replace('.rrd', '', basename($newrrd)) . '.dump.' . $seed;
}else{
	$tempdir = '/tmp';
	$oldxmlfile = '/tmp/' . str_replace('.rrd', '', basename($oldrrd)) . '.dump.' . $seed;
	$seed++;
	$newxmlfile = '/tmp/' . str_replace('.rrd', '', basename($newrrd)) . '.dump.' . $seed;
}

if ($finrrd == '') {
	$finrrd = dirname($newrrd) . '/' . basename($newrrd) . '.new';
}

/* execute the dump command */
debug("Creating XML file '$oldxmlfile' from '$oldrrd'");
shell_exec("$rrdtool dump $oldrrd > $oldxmlfile");
debug("Creating XML file '$newxmlfile' from '$newrrd'");
shell_exec("$rrdtool dump $newrrd > $newxmlfile");

/* read the xml files into arrays */
if (file_exists($oldxmlfile)) {
	$old_output = file($oldxmlfile);

	/* remove the temp file */
	unlink($oldxmlfile);
}else{
	echo 'FATAL: RRDtool Command Failed on \'' . $oldrrd . '\'.  Please insure your RRDtool install is valid!' . PHP_EOL;
	exit(-12);
}

if (file_exists($newxmlfile)) {
	$new_output = file($newxmlfile);

	/* remove the temp file */
	unlink($newxmlfile);
}else{
	echo 'FATAL: RRDtool Command Failed on \'' . $newrrd . '\'.  Please insure your RRDtool install is valid!' . PHP_EOL;
	exit(-12);
}

echo 'NOTE: RRDfile will be written to \'' . $finrrd . '\'' . PHP_EOL;

/* process the xml file and remove all comments */
debug("Reading XML From '$oldrrd'");
$old_output = preProcessXML($old_output);
$old_rrd    = processXML($old_output);

debug("Reading XML From '$newrrd'");
$new_output = preProcessXML($new_output);
$new_rrd    = processXML($new_output);

#print_r($old_rrd);
#print_r($new_rrd);

debug('Splicing RRDfiles');
spliceRRDs($new_rrd, $old_rrd);

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
chown($finrrd, 'apache');

memoryUsage();

function spliceRRDs(&$new_rrd, &$old_rrd) {
	if (cacti_sizeof($new_rrd) && cacti_sizeof($old_rrd)) {
		if (isset($new_rrd['rra'])) {
			foreach($new_rrd['rra'] as $rra_num => $rra) {
				$cf  = $new_rrd['rra'][$rra_num]['cf'];
				$pdp = $new_rrd['rra'][$rra_num]['pdp_per_row'];
				if (isset($rra['database'])) {
					foreach($rra['database'] as $cdp_ds_num => $value) {
						$dsname = $new_rrd['ds'][$cdp_ds_num]['name'];
						foreach($value as $time => $v) {
							$old_value = getOldRRDValue($old_rrd, $dsname, $cf, $pdp, $time);

							if ($v == 'NaN' || $v == 0.0) {
								if ($old_value != 'NaN' && $old_value > 0.0) {
									$new_rrd['rra'][$rra_num]['database'][$cdp_ds_num][$time] = $old_value;
								}
							}
						}
					}
				}else{
					echo 'FATAL: RRA database is Invalid' . PHP_EOL;
				}
			}
		} else {
			echo 'FATAL: One of RRA\'s is Invalid' . PHP_EOL;
		}
	}else{
		echo 'FATAL: One of RRD\'s is Invalid' . PHP_EOL;
	}
}

function getOldRRDValue(&$old_rrd, $dsname, $cf, $pdp, $time) {
	$old_dsnum  = -1;
	$old_rranum = -1;
	/* find the old ds number */
	foreach($old_rrd['ds'] as $dsnum => $ds) {
		if ($dsname == $ds['name']) {
			$old_dsnum = $dsnum;
			break;
		}
	}

	/* find the old rra_number */
	foreach($old_rrd['rra'] as $rranum => $rra) {
		if ($rra['cf'] == $cf && $rra['pdp_per_row'] == $pdp) {
			$old_rranum = $rranum;
			break;
		}
	}

	$last_value = '';
	$last_time  = 0;
	if ($old_rranum >= 0 && $old_dsnum >= 0) {
		foreach($old_rrd['rra'][$old_rranum]['database'][$old_dsnum] as $oldtime => $v) {
			//echo $last_time . "---" . $time . "---" . $oldtime . "\n";
			if ($oldtime == $time) {
				return $v;
			}elseif ($oldtime >= $time && $oldtime < $last_time) {
				return $v;
			}
			$last_value = $v;
			$last_time  = $oldtime;
		}
	}

	return '';
}

function recreateXML($new_rrd) {
/*
	Read all the XML into an array. The format of the array will be as show below.  This
	way it can be processed reverted back to array format at the end of the merge process.

	$rrd['version'];
	$rrd['step'];
	$rrd['lastupdate'];
	$rrd['ds'][$ds_num]['name'];
	$rrd['ds'][$ds_num]['type'];
	$rrd['ds'][$ds_num]['minimal_heartbeat'];
	$rrd['ds'][$ds_num]['min'];
	$rrd['ds'][$ds_num]['max'];
	$rrd['ds'][$ds_num]['last_ds'];
	$rrd['ds'][$ds_num]['value'];
	$rrd['ds'][$ds_num]['unknown_sec'];
	$rrd['rra'][$rra_num]['cf'];
	$rrd['rra'][$rra_num]['pdp_per_row'];
	$rrd['rra'][$rra_num]['params']['xff'];
	$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['primary_value'];
	$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['secondary_value'];
	$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['value'];
	$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['unknown_datapoints'];
	$rrd['rra'][$rra_num]['database'][$cdp_ds_num]['time'];

*/
	$rrd = "<rrd>\n";
	$rrd .= "\t<version> "    . $new_rrd['version']    . " </version>\n";
	$rrd .= "\t<step> "       . $new_rrd['step']       . " </step>\n";
	$rrd .= "\t<lastupdate> " . $new_rrd['lastupdate'] . " </lastupdate>\n";

	foreach($new_rrd['ds'] as $dsnum => $ds) {
		$rrd .= "\t<ds>\n";
		$rrd .= "\t\t<name> "  . $ds['name']  . " </name>\n";;
		$rrd .= "\t\t<type> "  . $ds['type']  . " </type>\n";;
		$rrd .= "\t\t<minimal_heartbeat> " . $ds['minimal_heartbeat'] . " </minimal_heartbeat>\n";;
		$rrd .= "\t\t<min> "   . $ds['min']   . " </min>\n";;
		$rrd .= "\t\t<max> "   . $ds['max']   . " </max>\n";;
		$rrd .= "\t\t<last_ds> " . $ds['last_ds'] . " </last_ds>\n";;
		$rrd .= "\t\t<value> " . $ds['value'] . " </value>\n";;
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

function memoryUsage() {
	global $time;

	$mem_usage = memory_get_usage(true);

	if ($mem_usage < 1024)
		$memstr = $mem_usage . ' B';
	elseif ($mem_usage < 1048576)
		$memstr = round($mem_usage/1024,2) . ' KB';
	else
		$memstr = round($mem_usage/1048576,2) . ' MB';

	echo 'NOTE: Time:' . (time()-$time) . ', RUsage:' . $memstr . PHP_EOL;
}

function processXML($output) {
/*
	Read all the XML into an array. The format of the array will be as show below.  This
	way it can be processed reverted back to array format at the end of the merge process.

	$rrd['version'];
	$rrd['step'];
	$rrd['lastupdate'];
	$rrd['ds'][$ds_num]['name'];
	$rrd['ds'][$ds_num]['type'];
	$rrd['ds'][$ds_num]['minimal_heartbeat'];
	$rrd['ds'][$ds_num]['min'];
	$rrd['ds'][$ds_num]['max'];
	$rrd['ds'][$ds_num]['last_ds'];
	$rrd['ds'][$ds_num]['value'];
	$rrd['ds'][$ds_num]['unknown_sec'];
	$rrd['rra'][$rra_num]['cf'];
	$rrd['rra'][$rra_num]['pdp_per_row'];
	$rrd['rra'][$rra_num]['params']['xff'];
	$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['primary_value'];
	$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['secondary_value'];
	$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['value'];
	$rrd['rra'][$rra_num]['cdp_prep'][$cdp_ds_num]['unknown_datapoints'];
	$rrd['rra'][$rra_num]['database'][$cdp_ds_num]['time'];

*/
	$rrd        = array();
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
			}else{
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
			}else{
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

	return $rrd;
}

/* All Functions */
function createRRDFileFromXML($xmlfile, $rrdfile) {
	global $rrdtool;

	/* execute the dump command */
	echo 'NOTE: Re-Importing \'' . $xmlfile . '\' to \'' . $rrdfile . '\'' . PHP_EOL;
	$response = shell_exec("$rrdtool restore -f -r $xmlfile $rrdfile");
	if (strlen($response)) echo $response . PHP_EOL;
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
	}else{
		$newfile = basename($rrdfile);
	}

	echo 'NOTE: Backing Up \'' . $rrdfile . '\' to \'' . $backupdir . '/' .  $newfile . '\'' . PHP_EOL;

	return copy($rrdfile, $backupdir . '/' . $newfile);
}

function preProcessXML(&$output) {
	if (cacti_sizeof($output)) {
		foreach($output as $line) {
			$line = trim($line);
			$date = '';
			if ($line == '') {
				continue;
			}else{
				/* is there a comment, remove it */
				$comment_start = strpos($line, '<!--');
				if ($comment_start === false) {
					/* do nothing no line */
				}else{
					$comment_end = strpos($line, '-->');
					$row = strpos($line, '<row>');

					if ($row > 0) {
						$date = trim(substr($line,$comment_start+30,11));
					}
					if ($comment_start == 0) {
						$line = trim(substr($line, $comment_end+3));
					}else{
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

function displayTime($pdp) {
	global $step;

	$total_time = $pdp * $step; // seconds

	if ($total_time < 60) {
		return $total_time . ' secs';
	}else{
		$total_time = $total_time / 60;

		if ($total_time < 60) {
			return $total_time . ' mins';
		}else{
			$total_time = $total_time / 60;

			if ($total_time < 24) {
				return $total_time . ' hours';
			}else{
				$total_time = $total_time / 24;

				return $total_time . ' days';
			}
		}
	}
}

function debug($string) {
	global $debug;

	if ($debug) {
		echo 'DEBUG: ' . $string . PHP_EOL;
	}
}

function standard_deviation($samples) {
	$sample_count = cacti_count($samples);

	for ($current_sample = 0; $sample_count > $current_sample; ++$current_sample) {
		$sample_square[$current_sample] = pow($samples[$current_sample], 2);
	}

	return sqrt(array_sum($sample_square) / $sample_count - pow((array_sum($samples) / $sample_count), 2));
}

function display_version() {
	$version = get_cacti_cli_version();
	echo 'Cacti RRDfile Splicer Utility, Version ' . $version . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}
/* display_help - displays the usage of the function */
function display_help () {
	display_version();

	echo PHP_EOL . 'usage: splice_rrd.php --oldrrd=file --newrrd=file [--finrrd=file]' . PHP_EOL;
	echo '               [--debug] [--dryrun] [-h|--help|-v|-V|--version]' . PHP_EOL . PHP_EOL;

	echo 'The Old and New input parameters are mandatory.  If the finrrd option is' . PHP_EOL;
	echo 'not specified, it will be the newrrd plus a timestamp' . PHP_EOL. PHP_EOL;

	echo '--oldrrd=file    - The old RRDfile that contains old data.' . PHP_EOL;
	echo '--newrrd=file    - The new RRDfile that contains more recent data.' . PHP_EOL;
	echo '--finrrd=file    - The final RRDfile that contains data from both rrdfiles.' . PHP_EOL. PHP_EOL;

	echo 'The remainder of arguments are informational' . PHP_EOL;
	echo '-d|--debug       - Display verbose output during execution' . PHP_EOL;
	echo '-v|--version     - Display this help message' . PHP_EOL;
	echo '-h|--help        - display this help message' . PHP_EOL;
}
