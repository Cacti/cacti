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

function upgrade_to_0_8_6e() {
	/* changes for logarithmic rrd files */
	db_install_execute("ALTER TABLE `data_template_rrd` CHANGE `rrd_minimum` `rrd_minimum` VARCHAR( 20 ) NOT NULL;");
	db_install_execute("ALTER TABLE `data_template_rrd` CHANGE `rrd_maximum` `rrd_maximum` VARCHAR( 20 ) NOT NULL;");
	db_install_execute("ALTER TABLE `graph_templates_graph` CHANGE `upper_limit` `upper_limit` VARCHAR( 20 ) NOT NULL;");
	db_install_execute("ALTER TABLE `graph_templates_graph` CHANGE `lower_limit` `lower_limit` VARCHAR( 20 ) NOT NULL;");

	db_install_add_column('poller_item', array('name' => 'rrd_step', 'type' => 'mediumint(8) unsigned', 'after' => 'rrd_num'));
	db_install_add_column('poller_item', array('name' => 'rrd_next_step', 'type' => 'mediumint(8)', 'after' => 'rrd_step'));

	/* increase size of ping status field to handle more extensive messages */
	db_install_execute("ALTER TABLE `host` CHANGE `status_last_error` `status_last_error` VARCHAR( 100 );");

	/* missing key's to improve Treeview performance */
	db_install_add_key('graph_local', 'key', 'host_id', array('host_id'));
	db_install_add_key('graph_local', 'key', 'graph_template_id', array('graph_template_id'));
	db_install_add_key('graph_local', 'key', 'snmp_query_id', array('snmp_query_id'));
	db_install_add_key('graph_local', 'key', 'snmp_index', array('snmp_index'));

	db_install_add_key('graph_templates_graph', 'key', 'title_cache', array('title_cache'));
	db_install_add_key('graph_tree_items', 'key', 'host_id', array('host_id'));
	db_install_add_key('graph_tree_items', 'key', 'local_graph_id', array('local_graph_id'));
	if (db_column_exists('graph_tree_items','order_key')) {
		db_install_add_key('graph_tree_items', 'key', 'order_key', array('order_key'));
	}
	db_install_add_key('graph_templates', 'key', 'name', array('name'));
	db_install_add_key('snmp_query', 'key', 'name', array('name'));
	db_install_add_key('host_snmp_cache', 'key', 'snmp_query_id', array('snmp_query_id'));

	/* missing key's to improve Clear Poller Cache performance */
	db_install_add_key('snmp_query_graph_rrd', 'key', 'data_template_rrd_id', array('data_template_rrd_id'));
	db_install_add_key('snmp_query_graph_rrd', 'key', 'data_template_id', array('data_template_id'));
	db_install_add_key('data_template_rrd', 'key', 'local_data_template_rrd_id', array('local_data_template_rrd_id'));
	db_install_add_key('data_input_fields', 'key', 'type_code', array('type_code'));

	/* remove NVA indexes from database */
	db_install_drop_key('cdef', 'index', 'ID');
	db_install_drop_key('cdef', 'index', 'id_2');
	db_install_drop_key('cdef_items', 'index', 'ID');
	db_install_drop_key('colors', 'index', 'ID');
	db_install_drop_key('colors', 'index', 'id_2');
	db_install_drop_key('data_input', 'index', 'ID');
	db_install_drop_key('data_input', 'index', 'id_2');
	db_install_drop_key('data_input_data', 'index', 'data_input_field_id');
	db_install_drop_key('data_input_fields', 'index', 'ID');
	db_install_drop_key('data_input_fields', 'index', 'id_2');
	db_install_drop_key('data_local', 'index', 'id');
	db_install_drop_key('data_local', 'index', 'id_2');
	db_install_drop_key('data_template', 'index', 'id');
	db_install_drop_key('data_template', 'index', 'id_2');
	db_install_drop_key('data_template_data', 'index', 'id');
	db_install_drop_key('data_template_data', 'index', 'id_2');
	db_install_drop_key('data_template_rrd', 'index', 'id');
	db_install_drop_key('data_template_rrd', 'index', 'id_2');
	db_install_drop_key('graph_local', 'index', 'id');
	db_install_drop_key('graph_local', 'index', 'id_2');
	db_install_drop_key('graph_template_input', 'index', 'id');
	db_install_drop_key('graph_template_input', 'index', 'id_2');
	db_install_drop_key('graph_template_input', 'index', 'id_3');
	db_install_drop_key('graph_templates', 'index', 'id');
	db_install_drop_key('graph_templates', 'index', 'id_2');
	db_install_drop_key('graph_templates_gprint', 'index', 'id');
	db_install_drop_key('graph_templates_gprint', 'index', 'id_2');
	db_install_drop_key('graph_templates_graph', 'index', 'id');
	db_install_drop_key('graph_templates_graph', 'index', 'id_2');
	db_install_drop_key('graph_templates_item', 'index', 'id');
	db_install_drop_key('graph_templates_item', 'index', 'id_2');
	db_install_drop_key('graph_tree', 'index', 'id');
	db_install_drop_key('graph_tree', 'index', 'id_2;');
	db_install_drop_key('graph_tree_items', 'index', 'ID');
	db_install_drop_key('graph_tree_items', 'index', 'id_2');
	db_install_drop_key('host', 'index', 'id');
	db_install_drop_key('host', 'index', 'id_2');
	db_install_drop_key('host_template', 'index', 'id');
	db_install_drop_key('host_template', 'index', 'id_2');
	db_install_drop_key('rra', 'index', 'id');
	db_install_drop_key('rra', 'index', 'id_2');
	db_install_drop_key('settings', 'index', 'Name');
	db_install_drop_key('settings', 'index', 'name_2');
	db_install_drop_key('settings_graphs', 'index', 'user_id');
	db_install_drop_key('snmp_query', 'index', 'id');
	db_install_drop_key('snmp_query', 'index', 'id_2');
	db_install_drop_key('snmp_query_graph', 'index', 'id');
	db_install_drop_key('snmp_query_graph', 'index', 'id_2');
	db_install_drop_key('snmp_query_graph_rrd_sv', 'index', 'id');
	db_install_drop_key('snmp_query_graph_rrd_sv', 'index', 'id_2');
	db_install_drop_key('snmp_query_graph_sv', 'index', 'id');
	db_install_drop_key('snmp_query_graph_sv', 'index', 'id_2');
	db_install_drop_key('user_auth', 'index', 'ID');
	db_install_drop_key('user_auth', 'index', 'id_2');
}
