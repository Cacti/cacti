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

function upgrade_to_0_8_6i() {
	/* once again, larger fields for OIDs and the like */
	db_install_execute("ALTER TABLE `poller_item` MODIFY COLUMN `arg1` TEXT;");
	db_install_execute("ALTER TABLE `poller_reindex` MODIFY COLUMN `arg1` VARCHAR(255) NOT NULL;");
	db_install_execute("ALTER TABLE `host_snmp_cache` MODIFY COLUMN `oid` TEXT NOT NULL;");

	/* let's add more graph tree items for those larger installations */
	db_install_execute("ALTER TABLE `graph_tree_items` MODIFY COLUMN `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;");

	/* let's keep track of an important statistical value */
	db_install_add_column('poller_time', array('name' => 'pid', 'type' => 'INTEGER UNSIGNED', 'NULL' => false, 'default' => '0', 'after' => 'id'));

	/* add some missing information from default/system data input methods */
	/* first we must see if the user was smart enough to add it themselves */
	$snmp_get_results = db_install_fetch_cell("SELECT id FROM data_input_fields WHERE data_name='snmp_port' AND data_input_id='1'");
	$snmp_get         = $snmp_get_results['data'];

	$snmp_index_results = db_install_fetch_cell("SELECT id FROM data_input_fields WHERE data_name='snmp_port' AND data_input_id='2'");
	$snmp_index         = $snmp_index_results['data'];

	if ($snmp_index > 0) {
		db_install_execute("REPLACE INTO `data_input_fields` VALUES (?, 'c1f36ee60c3dc98945556d57f26e475b',2,'SNMP Port','snmp_port','in','',0,'snmp_port','','');", array($snmp_index));
	} else {
		db_install_execute("REPLACE INTO `data_input_fields` VALUES (0, 'c1f36ee60c3dc98945556d57f26e475b',2,'SNMP Port','snmp_port','in','',0,'snmp_port','','');");
	}

	if ($snmp_get > 0) {
		db_install_execute("REPLACE INTO `data_input_fields` VALUES ('fc64b99742ec417cc424dbf8c7692d36',1,'SNMP Port','snmp_port','in','',0,'snmp_port','','');", array($snmp_get));
	} else {
		db_install_execute("REPLACE INTO `data_input_fields` VALUES (0, 'fc64b99742ec417cc424dbf8c7692d36',1,'SNMP Port','snmp_port','in','',0,'snmp_port','','');");
	}
}
