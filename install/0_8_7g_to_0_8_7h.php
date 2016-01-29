<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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
	$_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM host_snmp_cache"), "Field", "Field");
	if (!in_array("present", $_columns)) {
		db_install_execute("0.8.7h", "ALTER TABLE host_snmp_cache ADD COLUMN present tinyint NOT NULL DEFAULT '1' AFTER `oid`");
		db_install_execute("0.8.7h", "ALTER TABLE host_snmp_cache ADD INDEX present (present)");
		cacti_log(__FUNCTION__ . " upgrade table host_snmp_cache", false, "UPGRADE");
	}
	$_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM poller_item"), "Field", "Field");
	if (!in_array("present", $_columns)) {
		db_install_execute("0.8.7h", "ALTER TABLE poller_item ADD COLUMN present tinyint NOT NULL DEFAULT '1' AFTER `action`");
		db_install_execute("0.8.7h", "ALTER TABLE poller_item ADD INDEX present (present)");
		cacti_log(__FUNCTION__ . " upgrade table poller_item", false, "UPGRADE");
	}
	$_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM poller_reindex"), "Field", "Field");
	if (!in_array("present", $_columns)) {
		db_install_execute("0.8.7h", "ALTER TABLE poller_reindex ADD COLUMN present tinyint NOT NULL DEFAULT '1' AFTER `action`");
		db_install_execute("0.8.7h", "ALTER TABLE poller_reindex ADD INDEX present (present)");
		cacti_log(__FUNCTION__ . " upgrade table poller_reindex", false, "UPGRADE");
	}

	$_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM host"), "Field", "Field");
	if (!in_array("device_threads", $_columns)) {
		db_install_execute("0.8.7h", "ALTER TABLE host ADD COLUMN device_threads tinyint(2) unsigned NOT NULL DEFAULT '1' AFTER max_oids;");
		cacti_log(__FUNCTION__ . " upgrade table host", false, "UPGRADE");
	}

	$_keys = array_rekey(db_fetch_assoc("SHOW KEYS FROM data_template_rrd"), "Key_name", "Key_name");
	if (!in_array("duplicate_dsname_contraint", $_keys)) {
		db_install_execute("0.8.7h", "ALTER TABLE `data_template_rrd` ADD UNIQUE INDEX `duplicate_dsname_contraint` (`local_data_id`, `data_source_name`, `data_template_id`)");
		cacti_log(__FUNCTION__ . " upgrade table data_template_rrd", false, "UPGRADE");
	}

	/* update the reindex cache, as we now introduced more options for "index count changed" */
	$command_string = read_config_option("path_php_binary");
	$extra_args = "-q \"" . $config["base_path"] . "/cli/poller_reindex_hosts.php\" --id=all";
	exec_background($command_string, "$extra_args");
	cacti_log(__FUNCTION__ . " running $command_string $extra_args", false, "UPGRADE");

}
?>
