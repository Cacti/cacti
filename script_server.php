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

/* display No errors */
error_reporting(E_ERROR);

/* used for includes */
include_once(dirname(__FILE__) . "/include/config.php");

// don't ask me why, ask the developers of php...
if ($_SERVER["argc"] == 2)
	if ($_SERVER["argv"][1] == "cactid")
		$environ = "cactid";
	else
		$environ = "other";
else
	$environ = "cmd";

// determine logging level
$verbosity = read_config_option("log_verbosity");
if ($verbosity == "") $verbosity = 1;

// send status back to the server
if ($verbosity == HIGH) {
	log_data("PHPSERVER: Script Server has Started - Parent is " . $environ . "\n");
}
fputs(STDOUT, "PHPSERVER: PHP Script Server has Started - Parent is " . $environ . "\n");

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

			if ($verbosity == DEBUG) {
				log_data("Include->".$inc."<-\n");
				log_data("Command->".$cmd."<-\n");
				log_data("Arguments->".$preparm."<-\n");
				log_data("ArgV->".$parm[0]."-".$parm[1]."-".$parm[2]."-".$parm[3]."<-\n");
			}

			// check for existance of function.  If exists call it
			if ($cmd != "") {
				if (!function_exists($cmd)) {
					if (file_exists($inc)) {
						// quirk in php R5.0RC3, believe it or not....
//						if (($environ == "cactid") || (getcwd() == dirname(__FILE__))) {
							$inc = strtolower($inc);
//						}
						include_once($inc);
					} else {
						log_data("ERROR: PHP Script File to be included, does not exist\n");
					}
				}
			} else {
				log_data("ERROR: PHP Script Server encountered errors parsing the command\n");
			}

			if (function_exists($cmd)) {
				$result = call_user_func_array($cmd, $parm);
				if (!is_numeric($result)) {
					$result = "U";
					log_data("ERROR: Result from PHP Script Server was Invalid\n");
				}
				if (strpos($result,"\n") != 0) {
					fputs(STDOUT, $result);
				} else {
					fputs(STDOUT, $result . "\n");
				}
				if ($verbosity == HIGH) {
					log_data("PHPSERVER: CMD:" . $in_string . " output " . $result . "\n");
				}
			} else {
				log_data("ERROR: Function does not exist\n");
				fputs(STDOUT, "ERROR: Function does not exist\n");
			}
		}elseif ($in_string == "quit") {
			fputs(STDOUT, "PHPSERVER: PHP Script Server Shutdown request received, exiting\n");
			if ($verbosity == HIGH) {
				log_data("PHPSERVER: PHP Script Server Shutdown request received, exiting\n");
			}
			break;
		}else {
			log_data("ERROR: Problems with input\n");
			fputs(STDOUT, "ERROR: Problems with input\n");
		}
	}else {
		log_data("ERROR: Input Expected\n");
		fputs(STDOUT, "ERROR: Input expected\n");
	}
}
?>