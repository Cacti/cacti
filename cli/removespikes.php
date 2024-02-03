#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

$dir = dirname(__FILE__);
chdir($dir);

/* Start Initialization Section */
require(__DIR__ . '/../include/cli_check.php');
require_once($config['base_path'] . '/lib/spikekill.php');

if ($config['poller_id'] > 1) {
	print "FATAL: This utility is designed for the main Data Collector only" . PHP_EOL;
	exit(1);
}

/* allow more memory */
ini_set('memory_limit', '-1');

/* setup defaults */
$debug     = false;
$dryrun    = false;
$out_start = '';
$out_end   = '';
$rrdfile   = '';
$std_kills = false;
$var_kills = false;
$html      = false;
$backup    = false;
$out_set   = false;
$user      = get_current_user();

$method   = read_config_option('spikekill_method', 1);
$numspike = read_config_option('spikekill_number', 10);
$stddev   = read_config_option('spikekill_deviations', 10);
$percent  = read_config_option('spikekill_percent', 500);
$outliers = read_config_option('spikekill_outliers', 5);
$avgnan   = read_config_option('spikekill_avgnan', 'last');

switch($method) {
	case '1':
		$method = 'stddev';
		break;
	case '2':
		$method = 'variance';
		break;
	case '3':
		$method = 'float';
		break;
	case '4':
		$method = 'fill';
		break;
	default:
		$method = 'variance';
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--user':
			case '-U':
				print "WARNING: The user --user and -U are deprecated\n";

				break;
			case '--method':
			case '-M':
				$method = $value;

				break;
			case '--avgnan':
			case '-A':
				$avgnan = strtolower($value);

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
			case '--outliers':
			case '-O':
				$outliers = $value;

				break;
			case '--percent':
			case '-P':
				$percent = $value;

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
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit(-3);
		}
	}
} else {
	display_help();
	exit(0);
}

$spiker = new spikekill($rrdfile, $method, $avgnan, $stddev, $out_start, $out_end, $outliers, $percent, $numspike);

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
	print "ERROR: Remove Spikes experienced errors\n";
	print $spiker->get_errors();
	exit(-1);
} else {
	print $spiker->get_output();
	exit(0);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Spike Remover Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/* display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: removespikes.php -R|--rrdfile=rrdfile [-M|--method=stddev] [-A|--avgnan] [-S|--stddev=N]\n";
	print "    [-O|--outliers=N | --outlier-start=YYYY-MM-DD HH:MM --outlier-end=YYYY-MM-DD HH:MM]\n";
	print "    [-P|--percent=N] [-N|--number=N] [-D|--dryrun] [-d|--debug]\n";
	print "    [--html]\n\n";

	print "A utility to programmatically remove spikes from Cacti graphs. If no optional input parameters\n";
	print "are specified the defaults are taken from the Cacti database.\n\n";

	print "Required:\n";
	print "    --rrdfile=F   - The path to the RRDfile that will be de-spiked.\n\n";

	print "Optional:\n";
	print "    --method        - The spike removal method to use.  Options are stddev|variance|fill|float\n";
	print "    --avgnan        - The spike replacement method to use.  Options are last|avg|nan\n";
	print "    --stddev        - The number of standard deviations +/- allowed\n";
	print "    --percent       - The sample to sample percentage variation allowed\n";
	print "    --number        - The maximum number of spikes to remove from the RRDfile\n";
	print "    --outlier-start - A start date of an incident where all data should be considered\n";
	print "                      invalid data and should be excluded from average calculations.\n";
	print "    --outlier-end   - An end date of an incident where all data should be considered\n";
	print "                      invalid data and should be excluded from average calculations.\n";
	print "    --outliers      - The number of outliers to ignore when calculating average.\n";
	print "    --dryrun        - If specified, the RRDfile will not be changed.  Instead a summary of\n";
	print "                      changes that would have been performed will be issued.\n";
	print "    --backup        - Backup the original RRDfile to preserve prior values.\n\n";

	print "The remainder of arguments are informational\n";
	print "    --html          - Format the output for a web browser\n";
	print "    --debug         - Display verbose output during execution\n";
}
