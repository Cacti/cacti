<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2011 The Cacti Group                                 |
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
include_once($config["base_path"] . "/lib/utility.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$debug   = FALSE;
$host_id = "";

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
	case "-d":
	case "--debug":
		$debug = TRUE;
		break;
	case "--host-id":
		$host_id = $value;
		break;
	case "-h":
	case "-v":
	case "--version":
	case "--help":
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}

/* obtain timeout settings */
$max_execution = ini_get("max_execution_time");
$max_memory    = ini_get("memory_limit");

/* set new timeout and memory settings */
ini_set("max_execution_time", "0");
ini_set("memory_limit", "512M");

/* get the data_local Id's for the poller cache */
$hosts = db_fetch_assoc("SELECT id FROM host WHERE disabled=''" . ($host_id != '' ? " AND id=$host_id":""));

/* initialize some variables */
$current_host = 1;
$total_hosts  = sizeof($hosts);

/* issue warnings and start message if applicable */
print "WARNING: Do not interrupt this script.  Rebuilding the Poller Cache can take quite some time\n";
debug("There are '" . $total_hosts . "' hosts to push out.");

/* start rebuilding the poller cache */
if (sizeof($hosts) > 0) {
	foreach ($hosts as $host) {
		if (!$debug) print ".";
		push_out_host($host["id"]);
		debug("Host ID '" . $host["id"] . "' or '$current_host' of '$total_hosts' updated");
		$current_host++;
	}
}
if (!$debug) print "\n";

/*	display_help - displays the usage of the function */
function display_help () {
	print "Cacti Push Out Host Poller Cache Script 1.0, Copyright 2004-2011 - The Cacti Group\n\n";
	print "usage: push_out_hosts.php [--host-id=N] [-d] [-h] [--help] [-v] [--version]\n\n";
	print "--host-id=N   - push out only the specific host-id\n";
	print "-d | --debug  - Display verbose output during execution\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - Display this help message\n";
}

function debug($message) {
	global $debug;

	if ($debug) {
		print("DEBUG: " . $message . "\n");
	}
}

?>
