<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2004-2018 The Cacti Group                                 |
   |                                                                         |
   | This program is free software; you can redistribute it and/or           |
   | modify it under the terms of the GNU General Public License             |
   | as published by the Free Software Foundation; either version 2          |
   | of the License, or (at your option) any later version.                  |
   |                                                                         |
   | This program is snmpagent in the hope that it will be useful,           |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

/* we are not talking to the browser */
$no_http_headers = true;

/* let's report all errors */
error_reporting(E_ALL);

/* allow the script to hang around. */
set_time_limit(0);

chdir(dirname(__FILE__));
include_once('./include/global.php');

$path_mibcache = $config['base_path'] . '/cache/mibcache/mibcache.tmp';
$path_mibcache_lock = $config['base_path'] . '/cache/mibcache/mibcache.lock';

/* remove temporary cache */
@unlink($path_mibcache);

/* remove lock file */
@unlink($path_mibcache_lock);

/* start background caching process if not running */
$php = read_config_option("path_php_binary");
$extra_args     = " \"./snmpagent_mibcachechild.php\"";

while(1) {
	if(strstr(PHP_OS, "WIN")) {
		popen("start \"CactiSNMPCacheChild\" /I \"" . $php . "\" " . $extra_args, "r");
	} else {
		exec($php . " " . $extra_args . " > /dev/null &");
	}
	sleep(30 - time() % 30);
}
?>
