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

function upgrade_to_1_1_4() {
	db_install_add_key('cdef', 'index', 'hash', array('hash'));
	db_install_drop_key('cdef_items', 'index', 'cdef_id');
	db_install_add_key('cdef_items', 'index', 'cdef_id_sequence', array('cdef_id', 'sequence'));

	db_install_drop_key('data_input_fields', 'index', 'type_code');
	db_install_add_key('data_input_fields', 'index', 'type_code_data_input_id', array('type_code', 'data_input_id'));

	db_install_drop_key('data_local', 'index', 'host_id');
	db_install_add_key('data_local', 'index', 'host_id_snmp_query_id', array('host_id', 'snmp_query_id'));

	db_install_drop_key('snmpagent_cache', 'index', 'mib');
	db_install_add_key('snmpagent_cache', 'index', 'mib_name', array('mib', 'name'));

	db_install_add_key('host', 'index', 'hostname', array('hostname'));

	db_install_drop_key('snmpagent_managers_notifications', 'index', 'manager_id');
	db_install_drop_key('snmpagent_managers_notifications', 'index', 'manager_id2');
	db_install_add_key('snmpagent_managers_notifications', 'index', 'manager_id_notification', array('manager_id', 'notification'));

	db_install_drop_key('snmpagent_notifications_log', 'index', 'manager_id');
	db_install_drop_key('snmpagent_notifications_log', 'index', 'manager_id2');
	db_install_add_key('snmpagent_notifications_log', 'index', 'manager_id_notification', array('manager_id', 'notification'));

	db_install_drop_key('user_auth_group_members', 'index', 'group_id');
	db_install_drop_key('user_auth_group_realm', 'index', 'group_id');
	db_install_drop_key('user_log', 'index', 'username');

	db_install_add_key('vdef', 'index', 'hash', array('hash'));

	db_install_drop_key('vdef_items', 'index', 'vdef_id');
	db_install_add_key('vdef_items', 'index', 'vdef_id_sequence', array('vdef_id', 'sequence'));

	db_install_add_key('graph_templates_item', 'index', 'lgi_gti', array('local_graph_id', 'graph_template_id'));
	db_install_add_key('poller_item', 'index', 'poller_id_host_id', array('poller_id', 'host_id'));

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
