#!/usr/bin/php -q
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
}

/* used for includes */
include_once(dirname(__FILE__) . "/include/config.php");

/* PHP Bug.  Not yet logged */
if ($config["cacti_server_os"] == "win32") {
	$guess = substr(__FILE__,0,2);
	if ($guess == strtoupper($guess)) {
		$response = "ERROR: The PHP Script Server MUST be started using the full path to the file and in lower case.  This is a PHP Bug!!!";
		print "\n";
		cacti_log($response, true, "PHPSVR");
		exit(-1);
	}
}

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
	cacti_log("SERVER is->" . $environ, false, "PHPSVR");
	cacti_log("GETCWD is->" . strtolower(strtr(getcwd(),"\\","/")), false, "PHPSVR");
	cacti_log("DIRNAM is->" . strtolower(strtr(dirname(__FILE__),"\\","/")), false, "PHPSVR");
	cacti_log("FILENM is->" . __FILE__, false, "PHPSVR");
}

// send status back to the server
if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_HIGH) {
	cacti_log("PHP Script Server has Started - Parent is " . $environ, false, "PHPSVR");
}

fputs(STDOUT, "PHP Script Server has Started - Parent is " . $environ . "\n");
fflush(STDOUT);

// process waits for input and then calls functions as required
while (1) {
	$result = "";
	$in_string = fgets(STDIN,255);
	$in_string = rtrim(strtr(strtr($in_string,'\r',''),'\n',''));
	if (strlen($in_string)>0) {
		if (($in_string != "quit") && ($in_string != "")) {
			// get file to be included
			$inc = substr($in_string,0,strpos($in_string," "));
			$remainder = substr($in_string,strpos($in_string," ")+1);

			// parse function from command
			$cmd = substr($remainder,0,strpos($remainder," "));

			// parse parameters from remainder of command
			$preparm = substr($remainder,strpos($remainder," ")+1);
			$parm = explode(" ",$preparm);

			if (read_config_option("log_verbosity") == POLLER_VERBOSITY_DEBUG) {
				cacti_log("DEBUG: Include->".$inc."<-", false, "PHPSVR");
				cacti_log("DEBUG: Command->".$cmd."<-", false, "PHPSVR");
				cacti_log("DEBUG: Arguments->".$preparm."<-", false, "PHPSVR");
				cacti_log("DEBUG: ArgV->".$parm[0]."-".$parm[1]."-".$parm[2]."-".$parm[3]."<-", false, "PHPSVR");
			}

			// check for existance of function.  If exists call it
			if ($cmd != "") {
				if (!function_exists($cmd)) {
					if (file_exists($inc)) {
						/* quirk in php R5.0RC3, believe it or not.... */
						/* path must be lower case */
						$inc = strtolower($inc);
						include_once($inc);
					} else {
						cacti_log("WARNING: PHP Script File to be included, does not exist", false, "PHPSVR");
					}
				}
			} else {
				cacti_log("WARNING: PHP Script Server encountered errors parsing the command", false, "PHPSVR");
			}

			if (function_exists($cmd)) {
				$result = call_user_func_array($cmd, $parm);
				if (!is_numeric($result)) {
					$result = "U";
					cacti_log("WARNING: Result from PHP Script Server was Invalid", false, "PHPSVR");
				}
				if (strpos($result,"\n") != 0) {
					fputs(STDOUT, $result);
					fflush(STDOUT);
				} else {
					fputs(STDOUT, $result . "\n");
					fflush(STDOUT);
				}
				if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
					cacti_log("CMD: " . $in_string . " output " . $result, false, "PHPSVR");
				}
			} else {
				cacti_log("WARNING: Function does not exist\n", false, "PHPSVR");
				fputs(STDOUT, "WARNING: Function does not exist\n");
			}
		}elseif ($in_string == "quit") {
			fputs(STDOUT, "PHP Script Server Shutdown request received, exiting\n");
			if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_HIGH) {
				cacti_log("PHP Script Server Shutdown request received, exiting", false, "PHPSVR");
			}
			break;
		}else {
			cacti_log("WARNING: Problems with input", false, "PHPSVR");
			fputs(STDOUT, "ERROR: Problems with input\n");
		}
	}else {
		cacti_log("WARNING: Input Expected", false, "PHPSVR");
		fputs(STDOUT, "ERROR: Input expected\n");
	}
}
?>
