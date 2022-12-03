<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

/* let's report all errors */
error_reporting(E_ALL);

require(__DIR__ . '/include/cli_check.php');

/* allow the script to hang around. */
set_time_limit(0);

chdir(dirname(__FILE__));

$path_mibcache = $config['base_path'] . '/cache/mibcache/mibcache.tmp';
$path_mibcache_lock = $config['base_path'] . '/cache/mibcache/mibcache.lock';

/* remove temporary cache */
if (file_exists($path_mibcache) && is_writable($path_mibcache)) {
	unlink($path_mibcache);
}

/* remove lock file */
if (file_exists($path_mibcache_lock) && is_writable($path_mibcache_lock)) {
	unlink($path_mibcache_lock);
}

/* start background caching process if not running */
$php        = cacti_escapeshellcmd(read_config_option('path_php_binary'));
$extra_args = ' ' . cacti_escapeshellarg('./snmpagent_mibcachechild.php');

while(true) {
	if (strstr(PHP_OS, 'WIN')) {
		popen('start "CactiSNMPCacheChild" /I ' . $php . ' ' . $extra_args, 'r');
	} else {
		exec($php . ' ' . $extra_args . ' > /dev/null &');
	}

	sleep(30 - time() % 30);
}

