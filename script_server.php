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
print "Cacti PHP Script Handler Service Starting\n";

// load script information to handle script request
include(dirname(__FILE__) . "/scripts/script_functions.php" );

// process waits for input and then calls functions as required
print "Ready - \"quit\" command exists.\n";
while (1) {
	fputs(STDOUT, ">");
	$in_string = fgets(STDIN,255);
	if (strlen($in_string)>0) {
		if (($in_string != "quit\r\n") && ($in_string != "\r\n")) {
			// parse function from command
			$cmd1 = substr($in_string,0,strpos($in_string," "));

			// parse parameters from remainder of command
			$preparm1 = substr($in_string,strpos($in_string," ")+1);
			$preparm1 = substr($preparm1,0,strlen($preparm1)-2);
			$parm1 = explode(" ",$preparm1);

			// check for existance of function.  If exists call it
			if (function_exists($cmd1)) {
				// print output of function arguments

				$result0 = call_user_func_array($cmd1, $parm1);
				fputs(STDOUT, $result0);
			} else {
				fputs(STDERR, "ERROR: Function does not exist\n");
			}
		}
		elseif ($in_string == "quit\r\n") {
			break;
		}
	}
}
print "Cactid PHP Script Handler Service Exiting";
?>