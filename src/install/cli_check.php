#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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

ini_set('memory_limit','-1');

include(dirname(__FILE__) . '/../include/cli_check.php');
include(dirname(__FILE__) . '/../lib/utility.php');

if ($argv !== false && $argc != false && $argc > 1) {
	$value = strtolower($argv[1]);

	if ($value == 'extensions') {
		$ext = false;
		utility_php_verify_extensions($ext,'cli');
		print json_encode($ext);
	} else if ($value == 'recommends') {
		$rec = false;
		utility_php_verify_recommends($rec, 'cli');
		print json_encode($rec);
	} else if ($value == 'optionals') {
		$opt = false;
		utility_php_verify_optionals($opt, 'cli');
		print json_encode($opt);
	}
}
