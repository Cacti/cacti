<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2008 The Cacti Group                                 |
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

function upgrade_to_0_8_7c() {
	/* speed up the UI, missed in 0.8.7b upgrade */
	db_install_execute("0.8.7c", "ALTER TABLE `data_local` ADD INDEX `host_id`(`host_id`)");

	/* add a default for NOT NULL columns */
	db_install_execute("0.8.7c", "ALTER TABLE `data_local` MODIFY COLUMN `snmp_index` VARCHAR(255) NOT NULL DEFAULT '';");
	db_install_execute("0.8.7c", "ALTER TABLE `graph_local` MODIFY COLUMN `snmp_index` VARCHAR(255) NOT NULL DEFAULT '';");
	db_install_execute("0.8.7c", "ALTER TABLE `graph_templates_graph` MODIFY COLUMN `upper_limit` VARCHAR(20) NOT NULL DEFAULT '0';");
	db_install_execute("0.8.7c", "ALTER TABLE `graph_templates_graph` MODIFY COLUMN `lower_limit` VARCHAR(20) NOT NULL DEFAULT '0';");
	db_install_execute("0.8.7c", "ALTER TABLE `graph_templates_graph` MODIFY COLUMN `scale_log_units` CHAR(2) DEFAULT NULL;");
	db_install_execute("0.8.7c", "ALTER TABLE `host` MODIFY COLUMN `availability` DECIMAL(8,5) NOT NULL DEFAULT '100.00000';");
	db_install_execute("0.8.7c", "ALTER TABLE `host_snmp_cache` MODIFY COLUMN `snmp_index` VARCHAR(255) NOT NULL DEFAULT '';");
	db_install_execute("0.8.7c", "ALTER TABLE `host_snmp_cache` MODIFY COLUMN `oid` TEXT NOT NULL DEFAULT '';");
	db_install_execute("0.8.7c", "ALTER TABLE `poller_item` MODIFY COLUMN `snmp_auth_protocol` VARCHAR(5) NOT NULL DEFAULT '';");
	db_install_execute("0.8.7c", "ALTER TABLE `poller_item` MODIFY COLUMN `snmp_priv_passphrase` VARCHAR(200) NOT NULL DEFAULT '';");
	db_install_execute("0.8.7c", "ALTER TABLE `poller_item` MODIFY COLUMN `snmp_priv_protocol` VARCHAR(6) NOT NULL DEFAULT '';");
	db_install_execute("0.8.7c", "ALTER TABLE `poller_item` MODIFY COLUMN `rrd_next_step` MEDIUMINT(8) NOT NULL DEFAULT '0';");
	db_install_execute("0.8.7c", "ALTER TABLE `poller_item` MODIFY COLUMN `arg1` TEXT DEFAULT NULL;");
	db_install_execute("0.8.7c", "ALTER TABLE `poller_reindex` MODIFY COLUMN `arg1` VARCHAR(255) NOT NULL DEFAULT '';");
	db_install_execute("0.8.7c", "ALTER TABLE `user_auth` MODIFY COLUMN `enabled` CHAR(2) NOT NULL DEFAULT 'on';");
	db_install_execute("0.8.7c", "ALTER TABLE `user_log` MODIFY COLUMN `ip` VARCHAR(40) NOT NULL DEFAULT '';");
	/* change size of columns to match current cacti.sql file */
	db_install_execute("0.8.7c", "ALTER TABLE `poller_item` MODIFY COLUMN `rrd_step` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '300';");
	db_install_execute("0.8.7c", "ALTER TABLE `poller_time` MODIFY COLUMN `pid` INT(11) UNSIGNED NOT NULL DEFAULT '0';");
	
}
?>
