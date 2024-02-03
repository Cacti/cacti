#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

/* allow the script to hang around waiting for connections. */
set_time_limit(0);
chdir(dirname(__FILE__));

/* translate well-known textual conventions and SNMP base types to net-snmp */
$smi_base_datatypes = array(
	'integer' 			=> 'INTEGER',
	'integer32'			=> 'Integer32',
	'unsigned32' 		=> 'Unsigned32',
	'gauge' 			=> 'Gauge',
	'gauge32' 			=> 'Gauge32',
	'counter' 			=> 'Counter',
	'counter32' 		=> 'Counter32',
	'counter64' 		=> 'Counter64',
	'timeticks' 		=> 'TimeTicks',
	'octect string' 	=> 'OCTET STRING',
	'opaque'			=> 'Opaque',
	'object identifier' => 'OBJECT IDENTIFIER',
	'ipaddress' 		=> 'IpAddress',
	'networkaddress' 	=> 'IpAddress',
	'bits' 				=> 'OCTET STRING',
	'displaystring' 	=> 'STRING',
	'physaddress' 		=> 'OCTET STRING',
	'macaddress' 		=> 'OCTET STRING',
	'truthvalue' 		=> 'INTEGER',
	'testandincr' 		=> 'Integer32',
	'autonomoustype' 	=> 'OBJECT IDENTIFIER',
	'variablepointer' 	=> 'OBJECT IDENTIFIER',
	'rowpointer' 		=> 'OBJECT IDENTIFIER',
	'rowstatus' 		=> 'INTEGER',
	'timestamp' 		=> 'TimeTicks',
	'timeinterval' 		=> 'Integer32',
	'dateandtime' 		=> 'STRING',
	'storagetype' 		=> 'INTEGER',
	'tdomain' 			=> 'OBJECT IDENTIFIER',
	'taddress' 			=> 'OCTET STRING'
);

$data				= false;
$eol				= "\n";
$cache  			= array();
$cache_last_refresh = false;



/* start background caching process if not running */
$php = cacti_escapeshellcmd(read_config_option('path_php_binary'));
$extra_args     = '-q ' . cacti_escapeshellarg('./snmpagent_mibcache.php');

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	/* windows part missing */
	pclose(popen('start "CactiSNMPCache" /I /B ' . $php . ' ' . $extra_args, 'r'));
} else {
	exec('ps -ef | grep -v grep | grep -v "sh -c" | grep snmpagent_mibcache.php', $output);
	if(!cacti_sizeof($output)) {
		exec($php . ' ' . $extra_args . ' > /dev/null &');
	}
}


/* activate circular reference collector */
gc_enable();


while(1) {

	$input = trim(fgets(STDIN));
	switch($input) {
		case '':
			exit(0);
		case 'PING':
			fwrite(STDOUT, 'PONG' . $eol);
			cache_refresh();
			break;
		case 'get':
			$oid = trim(fgets(STDIN));
			if($data = cache_read($oid)) {
				fwrite(STDOUT, $oid . $eol . (isset($smi_base_datatypes[$data['type']]) ? $smi_base_datatypes[$data['type']] : 'INTEGER') . $eol . $data['value'] . $eol);
			}else {
				fwrite(STDOUT, 'NONE' . $eol);
			}
			break;
		case 'getnext':
			$oid = trim(fgets(STDIN));
			if( $next_oid = cache_get_next($oid)) {
				if($data = cache_read($next_oid)) {
					fwrite(STDOUT, $next_oid . $eol . (isset($smi_base_datatypes[$data['type']]) ? $smi_base_datatypes[$data['type']] : 'INTEGER') . $eol . $data['value'] . $eol);
			}else {
					 fwrite(STDOUT, 'NONE' . $eol);
				}
			}else {
				fwrite(STDOUT, 'NONE' . $eol);

			}
			break;
		case 'debug':
			fwrite(STDOUT, print_r($cache, true));
			break;
		case 'shutdown':
			fwrite(STDOUT, 'BYE' . $eol);
			exit(0);
	}
}

function cache_read($oid) {
	global $cache;
	return (isset($cache[$oid]) && $cache[$oid]) ? $cache[$oid] : false;
}

function cache_get_next($oid) {
	global $cache;
	return (isset($cache[$oid]['next'])) ? $cache[$oid]['next'] : false;
}

function cache_refresh() {
	global $config, $cache, $cache_last_refresh;

	$path_mibcache = $config['base_path'] . '/cache/mibcache/mibcache.tmp';
	$path_mibcache_lock = $config['base_path'] . '/cache/mibcache/mibcache.lock';

	/* check temporary cache file */
	clearstatcache();
	$cache_refresh_time = @filemtime( $path_mibcache );

	if($cache_refresh_time !== false) {
		/* initial phase */
		if( $cache_last_refresh === false || $cache_refresh_time > $cache_last_refresh ) {
			while( is_file( $path_mibcache_lock ) !== false ) {
				sleep(1);
				clearstatcache();
			}
			$cache = NULL;
			gc_collect_cycles();
			$cache_last_refresh = $cache_refresh_time;
			include( $path_mibcache );
		}
	}
	return;
}
