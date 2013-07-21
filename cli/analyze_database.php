#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

include(dirname(__FILE__) . "/../include/global.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

global $debug;

$debug = FALSE;
$form  = "";
$start = time();

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
	case "-d":
	case "--debug":
		$debug = TRUE;
		break;
	case "-v":
	case "-V":
	case "--version":
	case "--help":
	case "-h":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}
echo "Analyzing All Cacti Database Tables\n";

$tables = db_fetch_assoc("SHOW TABLES FROM " . $database_default);

if (sizeof($tables)) {
	foreach($tables AS $table) {
		echo "Analyzing Table -> '" . $table['Tables_in_' . $database_default] . "'";
		$status = db_execute("ANALYZE TABLE " . $table['Tables_in_' . $database_default] . $form);
		echo ($status == 0 ? " Failed" : " Successful") . "\n";
	}

	cacti_log("ANALYSIS STATS: Analyzing Cacti Tables Complete.  Total time " . (time() - $start) . " seconds.", false, "SYSTEM");
}

/*	display_help - displays the usage of the function */
function display_help () {
	print "Cacti Database Analyze Tool v1.0, Copyright 2004-2013 - The Cacti Group\n\n";
	print "usage: analyze_database.php [-d|--debug] [-h|--help|-v|-V|--version]\n\n";
	print "-d | --debug     - Display verbose output during execution\n";
	print "-v -V --version  - Display this help message\n";
	print "-h --help        - display this help message\n";
}
?>
