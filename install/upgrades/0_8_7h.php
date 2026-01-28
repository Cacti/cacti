<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

function upgrade_to_0_8_7h() : void {
	global $config;

	require_once(CACTI_PATH_LIBRARY . '/poller.php');

	// speed up the reindexing
	db_install_add_column('host_snmp_cache', ['name' => 'present', 'type' => 'tinyint', 'NULL' => false, 'default' => '1', 'after' => 'oid']);
	db_install_add_key('host_snmp_cache', 'index', 'present', ['present']);

	db_install_add_column('poller_item', ['name' => 'present', 'type' => 'tinyint', 'NULL' => false, 'default' => '1', 'after' => 'action']);
	db_install_add_key('poller_item', 'index', 'present', ['present']);

	db_install_add_column('poller_reindex', ['name' => 'present', 'type' => 'tinyint', 'NULL' => false, 'default' => '1', 'after' => 'action']);
	db_install_add_key('poller_reindex', 'index', 'present', ['present']);

	db_install_add_column('host', ['name' => 'device_threads', 'type' => 'tinyint(2) unsigned', 'NULL' => false, 'default' => '1', 'after' => 'max_oids']);

	db_install_add_key('data_template_rrd', 'unique index',  'duplicate_dsname_contraint', ['local_data_id', 'data_source_name', 'data_template_id']);
}
