<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
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

declare(ticks = 1);

/* need to capture signals from users */
function sig_handler($signo) {
	global $include_file, $function, $parameters;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
		case SIGABRT:
		case SIGQUIT:
		case SIGSEGV:
			cacti_log("WARNING: Script Server terminated with signal '$signo' in file:'" . basename($include_file) . "', function:'$function', params:'$parameters'", FALSE, "PHPSVR");

			exit;
			break;
		default:
			cacti_log("WARNING: Script Server recieved signal '$signo' in file:'$include_file', function:'$function', params:'$parameters'", FALSE, "PHPSVR");

			break;
	}
}

/* used for includes */
/* install signal handlers for UNIX only */
if (function_exists("pcntl_signal")) {
	pcntl_signal(SIGTERM, "sig_handler");
	pcntl_signal(SIGINT, "sig_handler");
	pcntl_signal(SIGABRT, "sig_handler");
	pcntl_signal(SIGQUIT, "sig_handler");
	pcntl_signal(SIGSEGV, "sig_handler");
}

error_reporting(0);
include_once(dirname(__FILE__) . "/include/global.php");

/* define STDOUT/STDIN file descriptors if not running under CLI */
if (php_sapi_name() != "cli") {
	define("STDIN", fopen('php://stdin', 'r'));
	define("STDOUT", fopen('php://stdout', 'w'));
}

/* record the script start time */
list($micro,$seconds) = split(" ", microtime());
$start = $seconds + $micro;

/* some debugging */
$pid = getmypid();
$ctr = 0;

/* if multiple polling intervals are defined, compensate for them */
$polling_interval = read_config_option("poller_interval");

if (!empty($polling_interval)) {
	$num_polling_items = db_fetch_cell("select count(*) from poller_item where rrd_next_step<=0");
	define("MAX_POLLER_RUNTIME", $polling_interval);
}else{
	$num_polling_items = db_fetch_cell("select count(*) from poller_item");
	define("MAX_POLLER_RUNTIME", 300);
}

/* Let PHP only run 1 second longer than the max runtime */
ini_set("max_execution_time", MAX_POLLER_RUNTIME + 1);

/* Record the calling environment */
if ($_SERVER["argc"] >= 2) {
	if ($_SERVER["argv"][1] == "spine")
		$environ = "spine";
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

if(read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
	cacti_log("DEBUG: SERVER: " . $environ, false, "PHPSVR");

	if ($config["cacti_server_os"] == "win32") {
		cacti_log("DEBUG: GETCWD: " . strtolower(strtr(getcwd(),"\\","/")), false, "PHPSVR");
		cacti_log("DEBUG: DIRNAM: " . strtolower(strtr(dirname(__FILE__),"\\","/")), false, "PHPSVR");
	}else{
		cacti_log("DEBUG: GETCWD: " . strtr(getcwd(),"\\","/"), false, "PHPSVR");
		cacti_log("DEBUG: DIRNAM: " . strtr(dirname(__FILE__),"\\","/"), false, "PHPSVR");
	}

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

	$input_string = fgets(STDIN, 1024);

	if ($input_string !== FALSE) {
		$input_string = trim($input_string);

		if (strlen($input_string)) {
			$command_array = explode(" ", $input_string, 3);

			if (sizeof($command_array)) {
				/* user has requested to quit */
				if (substr_count($command_array[0], "quit")) {
					fputs(STDOUT, "PHP Script Server Shutdown request received, exiting\n");
					if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
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

				if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
					cacti_log("DEBUG: PID[$pid] CTR[$ctr] INC: '". basename($include_file) . "' FUNC: '" .$function . "' PARMS: '" . $parameters . "'", false, "PHPSVR");
				}

				/* validate the existance of the function, and include if applicable */
				if (!function_exists($function)) {
					if (file_exists($include_file)) {
						/* quirk in php on Windows, believe it or not.... */
						/* path must be lower case */
						if ($config["cacti_server_os"] == "win32") {
							$include_file = strtolower($include_file);
						}

						/* set this variable so the calling script can determine if it was called
						 * by the script server or stand-alone */
						$called_by_script_server = true;

						/* turn on output buffering to avoid problems with nasty scripts */
						ob_start();
						include_once($include_file);
						ob_end_clean();
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

					fputs(STDOUT, trim($result) . "\n");
					fflush(STDOUT);

					if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
						cacti_log("DEBUG: PID[$pid] CTR[$ctr] RESPONSE:'$value'", false, "PHPSVR");
					}

					$ctr++;
				} else {
					cacti_log("WARNING: Function does not exist", false, "PHPSVR");
					fputs(STDOUT, "WARNING: Function does not exist\n");
				}
			}
		}
	}else{
		cacti_log("ERROR: Input Expected, Script Server Terminating", false, "PHPSVR");
		fputs(STDOUT, "ERROR: Input Expected, Script Server Terminating\n");
		exit (-1);
	}

	/* end the process if the runtime exceeds MAX_POLLER_RUNTIME */
	if (($start + MAX_POLLER_RUNTIME) < time()) {
		cacti_log("Maximum runtime of " . MAX_POLLER_RUNTIME . " seconds exceeded for the Script Server. Exiting.", true, "PHPSVR");
		exit (-1);
	}
}
