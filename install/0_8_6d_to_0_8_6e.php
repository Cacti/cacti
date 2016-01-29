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

function upgrade_to_0_8_6e() {
	/* changes for logarithmic rrd files */
	db_install_execute("0.8.6e", "ALTER TABLE `data_template_rrd` CHANGE `rrd_minimum` `rrd_minimum` VARCHAR( 20 ) NOT NULL;");
	db_install_execute("0.8.6e", "ALTER TABLE `data_template_rrd` CHANGE `rrd_maximum` `rrd_maximum` VARCHAR( 20 ) NOT NULL;");
	db_install_execute("0.8.6e", "ALTER TABLE `graph_templates_graph` CHANGE `upper_limit` `upper_limit` VARCHAR( 20 ) NOT NULL;");
	db_install_execute("0.8.6e", "ALTER TABLE `graph_templates_graph` CHANGE `lower_limit` `lower_limit` VARCHAR( 20 ) NOT NULL;");
	db_install_execute("0.8.6e", "ALTER TABLE `poller_item` ADD `rrd_step` MEDIUMINT( 8 ) UNSIGNED AFTER `rrd_num`, ADD `rrd_next_step` MEDIUMINT( 8 ) AFTER `rrd_step`;");

	/* increase size of ping status field to handle more extensive messages */
	db_install_execute("0.8.6e", "ALTER TABLE `host` CHANGE `status_last_error` `status_last_error` VARCHAR( 100 );");

	/* missing key's to improve Treeview performance */
	db_install_execute("0.8.6e", "ALTER TABLE `graph_local` ADD KEY host_id (host_id), ADD KEY graph_template_id (graph_template_id), ADD KEY snmp_query_id (snmp_query_id), ADD KEY snmp_index (snmp_index);");
	db_install_execute("0.8.6e", "ALTER TABLE `graph_templates_graph` ADD KEY title_cache (title_cache);");
	db_install_execute("0.8.6e", "ALTER TABLE `graph_tree_items` ADD KEY host_id (host_id), ADD KEY local_graph_id (local_graph_id), ADD KEY order_key (order_key);");
	db_install_execute("0.8.6e", "ALTER TABLE `graph_templates` ADD KEY name (name);");
	db_install_execute("0.8.6e", "ALTER TABLE `snmp_query` ADD KEY name (name);");
	db_install_execute("0.8.6e", "ALTER TABLE `host_snmp_cache` ADD KEY snmp_query_id (snmp_query_id);");

	/* missing key's to improve Clear Poller Cache performance */
	db_install_execute("0.8.6e", "ALTER TABLE `snmp_query_graph_rrd` ADD KEY data_template_rrd_id (data_template_rrd_id);");
	db_install_execute("0.8.6e", "ALTER TABLE `snmp_query_graph_rrd` ADD KEY data_template_id (data_template_id);");
	db_install_execute("0.8.6e", "ALTER TABLE `data_template_rrd` ADD KEY local_data_template_rrd_id (local_data_template_rrd_id);");
	db_install_execute("0.8.6e", "ALTER TABLE `data_input_fields` ADD KEY type_code (type_code);");

    /* remove NVA indexes from database */
    db_install_execute("0.8.6e", "ALTER TABLE `cdef` DROP INDEX ID, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `cdef_items` DROP INDEX ID;");
    db_install_execute("0.8.6e", "ALTER TABLE `colors` DROP INDEX ID, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `data_input` DROP INDEX ID, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `data_input_data` DROP INDEX data_input_field_id");
    db_install_execute("0.8.6e", "ALTER TABLE `data_input_fields` DROP INDEX ID, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `data_local` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `data_template` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `data_template_data` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `data_template_rrd` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `graph_local` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `graph_template_input` DROP INDEX id, DROP INDEX id_2, DROP INDEX id_3;");
    db_install_execute("0.8.6e", "ALTER TABLE `graph_templates` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `graph_templates_gprint` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `graph_templates_graph` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `graph_templates_item` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `graph_tree` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `graph_tree_items` DROP INDEX ID, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `host` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `host_template` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `rra` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `settings` DROP INDEX Name, DROP INDEX name_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `settings_graphs` DROP INDEX user_id;");
    db_install_execute("0.8.6e", "ALTER TABLE `snmp_query` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `snmp_query_graph` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `snmp_query_graph_rrd_sv` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `snmp_query_graph_sv` DROP INDEX id, DROP INDEX id_2;");
    db_install_execute("0.8.6e", "ALTER TABLE `user_auth` DROP INDEX ID, DROP INDEX id_2;");
 }
?>
