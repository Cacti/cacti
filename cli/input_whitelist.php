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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
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
include(dirname(__FILE__) . '/../lib/template.php');

$audit  = false;
$update = false;

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
			case '--audit':
			case '-A':
				$audit = true;
				break;
			case '--update':
			case '-U':
				$update = true;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
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

if (!isset($config['input_whitelist'])) {
	echo "NOTICE: The Cacti whitelist file is not setup in config.php.  Therefore, no action taken.\n";
	exit(0);
}

if ($audit) {
	if (isset($config['input_whitelist']) && !file_exists($config['input_whitelist'])) {
		echo "ERROR: The file '" . $config['input_whitelist'] . "' does not exist.  Please run with the '--update' option.\n";
		exit(1);
	}

	$totals = 0;

	$input = db_fetch_assoc('SELECT *
		FROM data_input
		WHERE input_string != ""');

	if (sizeof($input)) {
		echo "The results below show all non-blank Data Input Methods and their whitespace verification status.\n\n";
		echo "------------------------------------------------------------------------------------------------------------\n";

		foreach($input as $line) {
			$aud = verify_data_input($line);
			if ($aud['status'] == true) {
				echo "ID: " . $line['id'] . ", Name: " . $line['name'] . ", Status: " . 'Success' . "\n";
				echo "------------------------------------------------------------------------------------------------------------\n";
				echo "Command:   " . $line['input_string'] . "\nWhitelist: " . $aud['input'] . "\n";
			} else {
				echo "ID: " . $line['id'] . ", Name: " . $line['name'] . ", Status: " . 'Failed' . "\n";
				echo "------------------------------------------------------------------------------------------------------------\n";
				echo "Command:   " . $line['input_string'] . "\nWhitelist: " . $aud['input'] . "\n";

				$totals++;
			}
			echo "------------------------------------------------------------------------------------------------------------\n\n";
		}

		if ($totals) {
			echo "ERROR: $totals Audits failed out of a total of " . sizeof($input) . " Data Input Methods\n\n";
		} else {
			echo "NOTE: All Audits successfull from a total of " . sizeof($input) . " Data Input Methods\n\n";
		}
	}
} elseif ($update) {
	if (!is_writable(dirname($config['input_whitelist']))) {
		echo "ERROR: The file '" . $config['input_whitelist'] . "' is not writeable!  Please run as a user with write permissions.\n";
		exit(1);
	}

	$input = db_fetch_assoc('SELECT hash, input_string
		FROM data_input
		WHERE input_string != ""');

	if (sizeof($input)) {
		file_put_contents($config['input_whitelist'], json_encode($input));
		echo "NOTE: The file '" . $config['input_whitelist'] . "' has successfully been updated.\n";
	} else {
		echo "ERROR: The no Data Input records found in the Cacti Database.  Unable to write.\n";
		exit(1);
	}
} else {
	display_help();
}

exit(0);

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Input whitelist Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	echo "\nusage: input_whitelist.php [--audit | --update]\n\n";

	echo "A utility audit and update the Data Input whitelist status and\n";
	echo "Data Input protection file.\n\n";

	echo "Optional:\n";
	echo "    --audit       Audit but do not update the whitelist file.\n";
	echo "    --update      Update the whitelist file with latest information.\n\n";
}
