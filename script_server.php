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

// process waits for input and then calls functions as required
while (1) {
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

			// check for existance of function.  If exists call it
			if (!function_exists($cmd)) {
				eval("include_once(\"" . $inc . "\");");
			}

			if (function_exists($cmd)) {
				$result = call_user_func_array($cmd, $parm);
				if (strpos($result,"\n") != 0) {
					fputs(STDOUT, $result);
				} else {
					fputs(STDOUT, $result . "\n");
				}
			} else {
				fputs(STDOUT, "ERROR: Function does not exist\n");
			}
		}elseif ($in_string == "quit") {
			break;
		}else {
			fputs(STDOUT, "ERROR: Problems with input\n");
		}
	}else {
		fputs(STDOUT, "ERROR: Input expected\n");
	}
}
?>