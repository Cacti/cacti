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

$last_time = time()-30;

$path_mibcache = $config['base_path'] . '/cache/mibcache/mibcache.tmp';
$path_mibcache_lock = $config['base_path'] . '/cache/mibcache/mibcache.lock';

/* check mib cache table status */
$mibcache_changed = db_fetch_cell_prepared("SHOW TABLE STATUS WHERE `Name` LIKE 'snmpagent_cache' AND (UNIX_TIMESTAMP(`Update_time`)) >= ?", array($last_time));

if($mibcache_changed !== NULL || file_exists($path_mibcache) === false ) {
	$objects = db_fetch_assoc("SELECT `oid`, LOWER(type) as type, `otype`, `max-access`, `value` FROM snmpagent_cache");

	if($objects && sizeof($objects)>0) {
		$oids = array();
		foreach($objects as &$object) {
			$oids[] = $object['oid'];
			$object = ($object['otype'] == 'DATA' && $object['max-access'] != 'not-accessible') ? array('type' => $object['type'], 'value' => $object['value']) : false;
		}
		/* natural sorting with MySQL is not available - especially not for OIDs */
		natsort($oids);

		$last_accessible_object = false;
		$next_accessible_object_required = array();

		foreach($oids as $key => $oid) {
			if($objects[$key]) {
				if($last_accessible_object) {
					$cache[$last_accessible_object]['next'] = $oid;
				}
				if(sizeof($next_accessible_object_required)>0) {
					foreach($next_accessible_object_required as $next_accessible_object_required_oid) {
						$cache[$next_accessible_object_required_oid]['next'] = $oid;
					}
					$next_accessible_object_required = array();
				}
				$last_accessible_object = $oid;
			}else {
				$next_accessible_object_required[] = $oid;
			}
			$cache[$oid] = $objects[$key];
		}
	}
	/* create lock file */
	$lock = fopen($path_mibcache_lock, 'w');

	/* Note: If SNMPAgent plugin has been disabled the cache will be truncated automatically */
	file_put_contents($path_mibcache, '<?php $cache = ' . var_export($cache, true) . ';', LOCK_EX);

	/* destroy lock file */
	fclose($lock);
	unlink($path_mibcache_lock);
}
return;

?>
