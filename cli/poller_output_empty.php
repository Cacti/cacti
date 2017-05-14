#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* we are not talking to the browser */
$no_http_headers = true;

/* start initialization section */
include(dirname(__FILE__) . '/../include/global.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/data_query.php');
include_once($config['base_path'] . '/lib/dsstats.php');
include_once($config['base_path'] . '/lib/boost.php');
include_once($config['base_path'] . '/lib/rrd.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--version':
			case '-V':
			case '-v':
				display_help();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				echo 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

/* record the start time */
$start = microtime(true);

/* open a pipe to rrdtool for writing */
$rrdtool_pipe = rrd_init();

$rrds_processed = 0;

while (db_fetch_cell('SELECT count(*) FROM poller_output') > 0) {
	$rrds_processed = $rrds_processed + process_poller_output($rrdtool_pipe, FALSE);
}

echo "There were $rrds_processed, RRD updates made this pass\n";

rrd_close($rrdtool_pipe);

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
    echo "Cacti Process Poller Output Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	echo "\nusage: poller_output_empty.php\n\n";
	echo "A utility to process the poller output table.  This tool is deprecated but should work.\n\n";
}
