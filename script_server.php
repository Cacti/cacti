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
// print "Cacti PHP Script Handler Service Starting\n";

// load script information to handle script request
include(dirname(__FILE__) . "/scripts/script_functions.php" );

// process waits for input and then calls functions as required
while (1) {
	$in_string = fgets(STDIN,255);
	if (strlen($in_string)>0) {
		if (($in_string != "quit\r\n") && ($in_string != "\r\n")) {
			// parse function from command
			$in_string = strtr($in_string,"\r","\0");
			$in_string = strtr($in_string,"\n","\0");

			$cmd = substr($in_string,0,strpos($in_string," "));

			// parse parameters from remainder of command
			$preparm = substr($in_string,strpos($in_string," ")+1);
//			$preparm = substr($preparm,0,strlen($preparm)-2);
			$parm = explode(" ",$preparm);

			// check for existance of function.  If exists call it
			if (function_exists($cmd)) {
				// print output of function arguments

				$result = call_user_func_array($cmd, $parm);
				if (strpos($result,"\n") != 0) {
					fputs(STDOUT, $result);
				} else {
					fputs(STDOUT, $result . "\n");
				}
			} else {
				fputs(STDOUT, "ERROR: Function does not exist\n");
			}
		}elseif ($in_string == "quit\r\n") {
			break;
		}else {
			fputs(STDOUT, "ERROR: Problems with input\n");
			break;
		}
	}
}
?>