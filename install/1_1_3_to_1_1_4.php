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

function upgrade_to_1_1_4() {
	if (!db_index_exists('cdef', 'hash')) {
		db_install_execute('ALTER TABLE `cdef` ADD INDEX (`hash`)');
	}

	if (db_index_exists('cdef_items', 'cdef_id')) {
		db_install_execute(
			'ALTER TABLE `cdef_items` 
				DROP INDEX `cdef_id`,
				ADD INDEX `cdef_id_sequence` (`cdef_id`, `sequence`)'
		);
	}

	if (db_index_exists('data_input_fields', 'type_code')) {
		db_install_execute(
			'ALTER TABLE `data_input_fields` 
				DROP INDEX `type_code`,
				ADD INDEX `type_code_data_input_id` (`type_code`, `data_input_id`)'
		);
	}

	if (db_index_exists('data_local', 'host_id')) {
		db_install_execute(
			'ALTER TABLE `data_local` 
				DROP INDEX `host_id`,
				ADD INDEX `host_id_snmp_query_id` (`host_id`, `snmp_query_id`)'
		);
	}

	if (db_index_exists('snmpagent_cache', 'mib')) {
		db_install_execute(
			'ALTER TABLE `snmpagent_cache` 
				DROP INDEX `mib`,
				ADD INDEX `mib_name` (`mib`, `name`)'
		);
	}

	if (!db_index_exists('host', 'hostname')) {
		db_install_execute('ALTER TABLE `host` ADD INDEX (`hostname`)');
	}

	if (db_index_exists('snmpagent_managers_notifications', 'manager_id')) {
		db_install_execute(
			'ALTER TABLE `snmpagent_managers_notifications` 
				DROP INDEX `manager_id`,
				DROP INDEX `manager_id2`,
				ADD INDEX `manager_id_notification` (`manager_id`,`notification`)'
		);
	}

	if (db_index_exists('snmpagent_notifications_log', 'manager_id')) {
		db_install_execute(
			'ALTER TABLE `snmpagent_notifications_log` 
				DROP INDEX `manager_id`,
				DROP INDEX `manager_id2`,
				ADD INDEX `manager_id_notification` (`manager_id`,`notification`)'
		);
	}

	if (db_index_exists('user_auth_group_members', 'group_id')) {
		db_install_execute('ALTER TABLE `user_auth_group_members` DROP INDEX `group_id`');
	}

	if (db_index_exists('user_auth_group_realm', 'group_id')) {
		db_install_execute('ALTER TABLE `user_auth_group_realm` DROP INDEX `group_id`');
	}

	if (db_index_exists('user_log', 'username')) {
		db_install_execute('ALTER TABLE `user_log` DROP INDEX `username`');
	}

	if (!db_index_exists('vdef', 'hash')) {
		db_install_execute('ALTER TABLE `vdef` ADD INDEX `hash` (`hash`)');
	}

	if (db_index_exists('vdef_items', 'vdef_id')) {
		db_install_execute(
			'ALTER TABLE `vdef_items` 
				DROP INDEX `vdef_id`, 
				ADD INDEX `vdef_id_sequence` (`vdef_id`, `sequence`)'
		);
	}

	if (!db_index_exists('graph_templates_item', 'lgi_gti')) {
		db_install_execute('ALTER TABLE `graph_templates_item` ADD INDEX `lgi_gti` (`local_graph_id`, `graph_template_id`)');
	}

	if (!db_index_exists('poller_item', 'poller_id_host_id')) {
		db_install_execute('ALTER TABLE `poller_item` ADD INDEX `poller_id_host_id` (`poller_id`, `host_id`)');
	}

	if (!db_column_exists('automation_networks', 'site_id')) {
		db_install_execute('ALTER TABLE `automation_networks` ADD COLUMN `site_id` INT UNSIGNED DEFAULT "1" AFTER `poller_id`');
	}

	if (!db_column_exists('graph_tree_items', 'site_id')) {
		db_install_execute(
			'ALTER TABLE `graph_tree_items` 
				ADD COLUMN `site_id` INT UNSIGNED DEFAULT "0" AFTER `host_id`,
				ADD INDEX `site_id` (`site_id`)'
		);
	}

	if (!db_column_exists('graph_tree_items', 'graph_regex')) {
		db_install_execute(
			'ALTER TABLE `graph_tree_items` 
				ADD COLUMN `graph_regex` VARCHAR(60) DEFAULT "" AFTER `sort_children_type`,
				ADD COLUMN `host_regex` VARCHAR(60) DEFAULT "" AFTER `graph_regex`'
		);
	}
}
