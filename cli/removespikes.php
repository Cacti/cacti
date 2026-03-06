#!/usr/bin/env php
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

$dir = __DIR__;
chdir($dir);

// Start Initialization Section
require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/spikekill.php');

if (POLLER_ID > 1) {
	print 'FATAL: This utility is designed for the main Data Collector only' . PHP_EOL;

	exit(1);
}

// allow more memory
ini_set('memory_limit', '-1');

// setup defaults
$debug     = false;
$dryrun    = false;
$out_start = '';
$out_end   = '';
$rrdfile   = '';
$std_kills = false;
$html      = false;
$backup    = false;
$user      = get_current_user();

$method   = read_config_option('spikekill_method', true);
$numspike = read_config_option('spikekill_number', true);
$stddev   = read_config_option('spikekill_deviations', true);
$avgnan   = read_config_option('spikekill_avgnan', true);
$absmax   = read_config_option('spikekill_absmax', true);
$dsfilter = read_config_option('spikekill_dsfilter', true);

switch($method) {
	case '1':
		$method = 'stddev';

		break;
	case '2':
		$method = 'float';

		break;
	case '3':
		$method = 'fill';

		break;
	case '4':
		$method = 'absolute';

		break;
	default:
		$method = 'stddev';
}

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (str_contains($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--user':
			case '-U':
				print 'WARNING: The user --user and -U are deprecated' . PHP_EOL;

				break;
			case '--method':
			case '-M':
				$method = $value;

				break;
			case '--avgnan':
			case '-A':
				$avgnan = cacti_strtolower($value);

				break;
			case '--rrdfile':
			case '-R':
				$rrdfile = $value;

				break;
			case '--stddev':
			case '-S':
				$stddev = $value;

				break;
			case '--outlier-start':
				$out_start = $value;

				break;
			case '--outlier-end':
				$out_end   = $value;

				break;
			case '--html':
				$html = true;

				break;
			case '--backup':
				$backup = true;

				break;
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '-D':
			case '--dryrun':
				$dryrun = true;

				break;
			case '--number':
			case '-n':
				$numspike = $value;

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();

				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();

				exit(0);
			case '--absmax':
				$absmax = $value;

				break;
			case '--dsfilter':
				$dsfilter = $value;

				break;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();

				exit(-3);
		}
	}
} else {
	display_help();

	exit(0);
}

if ($out_start != '' && !is_numeric($out_start)) {
	$orig_out_start = $out_start;
	$out_start      = strtotime($out_start);

	if ($out_start == false) {
		print "ERROR: The outlier start value '$orig_out_start' is invalid.  Use either a timestamp or a datetime format." . PHP_EOL;
		exit(1);
	}
}

if ($out_end != '' && !is_numeric($out_end)) {
	$orig_out_end = $out_end;
	$out_end      = strtotime($out_end);

	if ($out_end == false) {
		print "ERROR: The outlier end value '$orig_out_end' is invalid.  Use either a timestamp or a datetime format." . PHP_EOL;
		exit(1);
	}
}

$spiker = new spikekill($rrdfile, $method, $avgnan, $stddev, $out_start, $out_end, $numspike, $dsfilter, $absmax);

if ($debug) {
	$spiker->debug = true;
}

if ($html) {
	$spiker->html = true;
} else {
	$spiker->html = false;
}

if ($dryrun) {
	$spiker->dryrun = true;
} else {
	$spiker->dryrun = false;
}

$result = $spiker->remove_spikes();

if (!$result) {
	print 'ERROR: Remove Spikes experienced errors' . PHP_EOL;
	print $spiker->get_errors();

	exit(-1);
} else {
	print $spiker->get_output();

	exit(0);
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Spike Remover Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: removespikes.php -R|--rrdfile=rrdfile [-M|--method=stddev] [-A|--avgnan] [-S|--stddev=N]' . PHP_EOL;
	print '    [-O|--outliers=N | --outlier-start=YYYY-MM-DD HH:MM --outlier-end=YYYY-MM-DD HH:MM]' . PHP_EOL;
	print '    [-P|--percent=N] [-N|--number=N] [--absmax=<value>] [-D|--dryrun] [-d|--debug]' . PHP_EOL;
	print '    [--html] [--dsfilter=<filter>]' . PHP_EOL . PHP_EOL;

	print 'A utility to programmatically remove spikes from Cacti graphs. If no optional input parameters' . PHP_EOL;
	print 'are specified the defaults are taken from the Cacti database.' . PHP_EOL . PHP_EOL;

	print 'Required:' . PHP_EOL;
	print '    --rrdfile=F   - The path to the RRDfile that will be de-spiked.' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '    --method        - The spike removal method to use.  Options are stddev|variance|fill|float|absolute' . PHP_EOL;
	print '    --avgnan        - The spike replacement method to use.  Options are last|avg|nan' . PHP_EOL;
	print '    --stddev        - The number of standard deviations +/- allowed' . PHP_EOL;
	print '    --percent       - The sample to sample percentage variation allowed' . PHP_EOL;
	print '    --number        - The maximum number of spikes to remove from the RRDfile' . PHP_EOL;
	print '    --absmax        - The absolute maximum value of a data point to remove from the RRDfile' . PHP_EOL;
	print '    --dsfilter      - Specifies the DSes inside an RRD upon which Spikekill will operate' . PHP_EOL;
	print '    --outlier-start - A start date or timestamp of an incident where all data should be considered' . PHP_EOL;
	print '                      invalid data and should be excluded from average calculations.' . PHP_EOL;
	print '    --outlier-end   - An end date or timestamp of an incident where all data should be considered' . PHP_EOL;
	print '                      invalid data and should be excluded from average calculations.' . PHP_EOL;
	print '    --outliers      - The number of outliers to ignore when calculating average.' . PHP_EOL;
	print '    --dryrun        - If specified, the RRDfile will not be changed.  Instead a summary of' . PHP_EOL;
	print '                      changes that would have been performed will be issued.' . PHP_EOL;
	print '    --backup        - Backup the original RRDfile to preserve prior values.' . PHP_EOL . PHP_EOL;

	print 'The remainder of arguments are informational' . PHP_EOL;
	print '    --html          - Format the output for a web browser' . PHP_EOL;
	print '    --debug         - Display verbose output during execution' . PHP_EOL;
}
