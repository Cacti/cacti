<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

function upgrade_to_0_8_7h() {
	global $config;

	require_once($config["base_path"] . "/lib/poller.php");

	/* speed up the reindexing */
	if (!db_column_exists('host_snmp_cache', 'present', false)) {
		db_install_add_column ('host_snmp_cache', array('name' => 'present', 'type' => 'tinyint', 'NULL' => false, 'default' => '1', 'after' => 'oid'));
		db_install_execute("ALTER TABLE host_snmp_cache ADD INDEX present (present)");
	}
	if (!db_column_exists('poller_item', 'present', false)) {
		db_install_add_column ('poller_item', array('name' => 'present', 'type' => 'tinyint', 'NULL' => false, 'default' => '1', 'after' => 'action'));
		db_install_execute("ALTER TABLE poller_item ADD INDEX present (present)");
	}
	if (!db_column_exists('poller_reindex', 'present', false)) {
		db_install_add_column ('poller_reindex', array('name' => 'present', 'type' => 'tinyint', 'NULL' => false, 'default' => '1', 'after' => 'action'));
		db_install_execute("ALTER TABLE poller_reindex ADD INDEX present (present)");
	}

	if (!db_column_exists('host', 'device_threads', false)) {
		db_install_add_column ('host', array('name' => 'device_threads', 'type' => 'tinyint(2) unsigned', 'NULL' => false, 'default' => '1', 'after' => 'max_oids'));
	}

	$_keys = array_rekey(db_fetch_assoc("SHOW KEYS FROM data_template_rrd"), "Key_name", "Key_name");
	if (!in_array("duplicate_dsname_contraint", $_keys)) {
		db_install_execute("ALTER TABLE `data_template_rrd` ADD UNIQUE INDEX `duplicate_dsname_contraint` (`local_data_id`, `data_source_name`, `data_template_id`)");
	}

	/* update the reindex cache, as we now introduced more options for "index count changed" */
	$command_string = read_config_option("path_php_binary");
	$extra_args = "-q \"" . $config["base_path"] . "/cli/poller_reindex_hosts.php\" --id=all";
	exec_background($command_string, "$extra_args");
	cacti_log(__FUNCTION__ . " running $command_string $extra_args", false, "UPGRADE");
}
