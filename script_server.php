<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/
$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
	exit(-1);
}

/* define STDOUT/STDIN file descriptors if not running under CLI */
if (php_sapi_name() != "cli") {
	define("STDIN", fopen('php://stdin', 'r'));
	define("STDOUT", fopen('php://stdout', 'w'));

	ini_set("max_execution_time", "296");
}

/* used for includes */
error_reporting(0);
include_once(dirname(__FILE__) . "/include/config.php");

/* Record the calling environment */
if ($_SERVER["argc"] >= 2) {
	if ($_SERVER["argv"][1] == "cactid")
		$environ = "cactid";
	else
		if (($_SERVER["argv"][1] == "cmd.php") || ($_SERVER["argv"][1] == "cmd"))
			$environ = "cmd";
		else
			$environ = "other";

	if ($_SERVER["argc"] == 3)
		$poller_id = $_SERVER["argv"][2];
	else
		$poller_id = 0;
} else {
	$environ = "cmd";
	$poller_id = 0;
}

if(read_config_option("log_verbosity") == POLLER_VERBOSITY_DEBUG) {
	cacti_log("DEBUG: SERVER: " . $environ, false, "PHPSVR");
	cacti_log("DEBUG: GETCWD: " . strtolower(strtr(getcwd(),"\\","/")), false, "PHPSVR");
	cacti_log("DEBUG: DIRNAM: " . strtolower(strtr(dirname(__FILE__),"\\","/")), false, "PHPSVR");
	cacti_log("DEBUG: FILENM: " . __FILE__, false, "PHPSVR");
}

/* send status back to the server */
if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_HIGH) {
	cacti_log("PHP Script Server has Started - Parent is " . $environ, false, "PHPSVR");
}

fputs(STDOUT, "PHP Script Server has Started - Parent is " . $environ . "\n");
fflush(STDOUT);

/* process waits for input and then calls functions as required */
while (1) {
	$result = "";

	$command_array = explode(" ", fgets(STDIN,1024), 3);

	/* this will happen when the parent crashes, so log the event */
	if (sizeof($command_array) == 0) {
		cacti_log("ERROR: Input Expected, Script Server Terminating", false, "PHPSVR");
		fputs(STDOUT, "ERROR: Input Expected, Script Server Terminating\n");

		/* parent abended, let's show the parent as done  */
		db_execute("insert into poller_time (poller_id, start_time, end_time) values (0, NOW(), NOW())");
		exit (-1);
	}

	/* user has requested to quit */
	if (substr_count($command_array[0], "quit")) {
		fputs(STDOUT, "PHP Script Server Shutdown request received, exiting\n");
		if (read_config_option("log_verbosity") == POLLER_VERBOSITY_DEBUG) {
			cacti_log("DEBUG: PHP Script Server Shutdown request received, exiting", false, "PHPSVR");
		}
		exit(1);
	}

	/* valid command entered to system, parse and execute */
	if (isset($command_array[0])) {
		$include_file = trim($command_array[0]);
	}else{
		$include_file = "";
	}

	if (isset($command_array[1])) {
		$function = trim($command_array[1]);
	}else{
		$function = "";
	}

	if (isset($command_array[2])) {
		$parameters = trim($command_array[2]);
		$parameter_array = explode(" ", trim($command_array[2]));
	}else{
		$parameters = "";
		$parameters_array = array();
	}

	if (read_config_option("log_verbosity") == POLLER_VERBOSITY_DEBUG) {
		cacti_log("DEBUG: INC: '". $include_file . "' FUNC: '" .$function . "' PARMS: '" . $parameters . "'", false, "PHPSVR");
	}

	/* validate the existance of the function, and include if applicable */
	if (!function_exists($function)) {
		if (file_exists($include_file)) {
			/* quirk in php R5.0RC3, believe it or not.... */
			/* path must be lower case */
			$include_file = strtolower($include_file);

			/* set this variable so the calling script can determine if it was called
			 * by the script server or stand-alone */
			$called_by_script_server = true;
			include_once($include_file);
		} else {
			cacti_log("WARNING: PHP Script File to be included, does not exist", false, "PHPSVR");
		}
	}

	if (function_exists($function)) {
		if ($parameters == "") {
			$result = call_user_func($function);
		} else {
			$result = call_user_func_array($function, $parameter_array);
		}

		if (!validate_result($result)) {
			$result = "U";
		}

		if (strpos($result,"\n") != 0) {
			fputs(STDOUT, $result);
			fflush(STDOUT);
		} else {
			fputs(STDOUT, $result . "\n");
			fflush(STDOUT);
		}
	} else {
		cacti_log("WARNING: Function does not exist\n", false, "PHPSVR");
		fputs(STDOUT, "WARNING: Function does not exist\n");
	}
}
?>
