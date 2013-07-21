<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
   die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* allow the upgrade script to run for as long as it needs to */
ini_set('max_execution_time', '0');

include(dirname(__FILE__)."/../include/global.php");
include_once($config["base_path"]."/lib/data_query.php");
include_once($config["base_path"]."/lib/utility.php");

/* UPDATE THIS FOR NEW VERSIONS!! */
$includes = array(
	'0.8'    => '',
	'0.8.1'  => '0_8_to_0_8_1.php',
	'0.8.2'  => '0_8_1_to_0_8_2.php',
	'0.8.2a' => '0_8_2_to_0_8_2a.php',
	'0.8.3'  => '0_8_2a_to_0_8_3.php',
	'0.8.3a' => '',
	'0.8.4'  => '0_8_3_to_0_8_4.php',
	'0.8.5'  => '0_8_4_to_0_8_5.php',
	'0.8.5a' => '',
	'0.8.6'  => '0_8_5a_to_0_8_6.php',
	'0.8.6a' => '0_8_6_to_0_8_6a.php',
	'0.8.6b' => '',
	'0.8.6c' => '',
	'0.8.6d' => '0_8_6c_to_0_8_6d.php',
	'0.8.6e' => '0_8_6d_to_0_8_6e.php',
	'0.8.6f' => '',
	'0.8.6g' => '0_8_6f_to_0_8_6g.php',
	'0.8.6h' => '0_8_6g_to_0_8_6h.php',
	'0.8.6i' => '0_8_6h_to_0_8_6i.php',
	'0.8.6j' => '',
	'0.8.6k' => '',
	'0.8.7'  => '0_8_6j_to_0_8_7.php',
	'0.8.7a' => '0_8_7_to_0_8_7a.php',
	'0.8.7b' => '0_8_7a_to_0_8_7b.php',
	'0.8.7c' => '0_8_7b_to_0_8_7c.php',
	'0.8.7d' => '0_8_7c_to_0_8_7d.php',
	'0.8.7e' => '0_8_7d_to_0_8_7e.php',
	'0.8.7f' => '0_8_7e_to_0_8_7f.php',
	'0.8.7g' => '0_8_7f_to_0_8_7g.php',
	'0.8.7h' => '0_8_7g_to_0_8_7h.php',
	'0.8.7i' => '0_8_7h_to_0_8_7i.php',
	'0.8.8'  => '0_8_7i_to_0_8_8.php',
);

$old_cacti_version = db_fetch_cell('select cacti from version');

/* try to find current (old) version in the array */
$old_version_index = (isset($includes[$old_cacti_version]) ? $old_cacti_version : '');

/* do a version check */
if ($old_cacti_version == $config['cacti_version']) {
	print "Your Cacti is already up to date.\n";
	exit;
} else if ($old_cacti_version < 0.7) {
	print 'You are attempting to install cacti ' . $config['cacti_version'] . " onto a 0.6.x database.\nTo continue, you must create a new database, import 'cacti.sql' into it,\nand 	update 'include/config.php' to point to the new database.\n";
	exit;
} else if (empty($old_cacti_version)) {
	print "You have created a new database, but have not yet imported the 'cacti.sql' file.\n";
	exit;
} else if ($old_version_index == '') {
	print "Invalid Cacti version $old_cacti_version, cannot upgrade to " . $config['cacti_version'] . "\n";
	exit;
}

/* loop from the old version to the current, performing updates for each version in between */
$start = FALSE;
foreach ($includes as $v => $file) {
	if ($file != '' && $start) {
		print "Upgrading to " . $v . "\n";
		include($config["base_path"] . '/install/' . $file);
		$func = "upgrade_to_" . str_replace('.', '_', $v);
		$func();
		db_install_errors ($v);
	}
	if ($v == $config['cacti_version']) {
		break;
	}
	if ($old_cacti_version == $v) {
		$start = TRUE;
	}
}

/* it's always a good idea to re-populate the poller cache to make sure everything is refreshed and up-to-date */
repopulate_poller_cache();

db_execute('delete from version');
db_execute("INSERT INTO version (cacti) values ('" . $config['cacti_version'] . "')");





function db_install_errors ($cacti_version) {
	if (isset($_SESSION["sess_sql_install_cache"])) {
		foreach ($_SESSION["sess_sql_install_cache"] as $sc) {
			if (isset($sc[$cacti_version])) {
				foreach ($sc[$cacti_version] as $value => $sql) {
					if ($value == 0) {
						print "    DB Error: $sql\n";
					}
				}
			}
		}
	}
}

function db_install_execute($cacti_version, $sql) {
	$sql_install_cache = (isset($_SESSION["sess_sql_install_cache"]) ? $_SESSION["sess_sql_install_cache"] : array());

	if (db_execute($sql)) {
		$sql_install_cache{sizeof($sql_install_cache)}[$cacti_version][1] = $sql;
	}else{
		$sql_install_cache{sizeof($sql_install_cache)}[$cacti_version][0] = $sql;
	}

	$_SESSION["sess_sql_install_cache"] = $sql_install_cache;
}



