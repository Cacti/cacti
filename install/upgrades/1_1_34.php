<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

function upgrade_to_1_1_34() {
	if (!db_column_exists('automation_snmp_items', 'snmp_community')) {
		db_install_execute('ALTER TABLE `automation_snmp_items`
			CHANGE COLUMN `snmp_readstring` `snmp_community` varchar(50) NOT NULL DEFAULT ""');
	}

	db_install_add_key('data_input_fields', 'index', 'input_output', array('input_output'));

	db_install_drop_column('graph_templates_graph', 'export');
	db_install_drop_column('graph_templates_graph', 't_export');

	db_install_add_key('host', 'index', 'hostname', array('hostname'));
	db_install_drop_key('host', 'index', 'last_updated');
	db_install_add_key('host', 'index', 'poller_id_last_updated', array('poller_id', 'last_updated'));

	db_install_add_key('poller_command', 'index', 'poller_id_last_updated', array('poller_id', 'last_updated'));
	db_install_drop_key('poller_command', 'index', 'last_updated');

	db_install_execute('ALTER TABLE `poller_item`
		MODIFY COLUMN `snmp_auth_protocol` char(6) NOT NULL DEFAULT "",
		MODIFY COLUMN `snmp_priv_protocol` char(6) NOT NULL DEFAULT ""');

	db_install_drop_key('poller_item', 'index', 'last_updated');
	db_install_add_key('poller_item', 'index', 'poller_id_last_updated', array('poller_id', 'last_updated'));

	// Results of upgrade audit
	db_install_execute('ALTER TABLE `aggregate_graph_templates`
		MODIFY COLUMN `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP');

	db_install_execute('ALTER TABLE `aggregate_graph_templates_graph`
		MODIFY COLUMN `t_alt_y_grid` char(2) DEFAULT "",
		MODIFY COLUMN `t_right_axis` char(2) DEFAULT "",
		MODIFY COLUMN `t_right_axis_label` char(2) DEFAULT "",
		MODIFY COLUMN `t_right_axis_format` char(2) DEFAULT "",
		MODIFY COLUMN `t_right_axis_formatter` char(2) DEFAULT "",
		MODIFY COLUMN `t_left_axis_formatter` char(2) DEFAULT "",
		MODIFY COLUMN `t_no_gridfit` char(2) DEFAULT "",
		MODIFY COLUMN `t_unit_length` char(2) DEFAULT "",
		MODIFY COLUMN `t_tab_width` char(2) DEFAULT "",
		MODIFY COLUMN `tab_width` varchar(20) DEFAULT "30",
		MODIFY COLUMN `t_dynamic_labels` char(2) DEFAULT "",
		MODIFY COLUMN `t_force_rules_legend` char(2) DEFAULT "",
		MODIFY COLUMN `t_legend_position` char(2) DEFAULT "",
		MODIFY COLUMN `t_legend_direction` char(2) DEFAULT ""');

	db_install_execute('ALTER TABLE `automation_devices`
		MODIFY COLUMN `snmp_port` mediumint(5) unsigned NOT NULL DEFAULT "161",
		MODIFY COLUMN `snmp_auth_protocol` char(6) DEFAULT ""');

	if (db_column_exists('automation_devices', 'community')) {
		db_install_execute('ALTER TABLE `automation_devices`
			CHANGE COLUMN `community` `snmp_community` varchar(100) NOT NULL DEFAULT ""');
	}

	db_install_execute('ALTER TABLE `automation_ips`
		MODIFY COLUMN `hostname` varchar(100) DEFAULT ""');

	db_install_execute('ALTER TABLE `automation_snmp_items`
		MODIFY COLUMN `snmp_version` tinyint(1) unsigned NOT NULL DEFAULT "1",
		MODIFY COLUMN `snmp_port` mediumint(5) unsigned NOT NULL DEFAULT "161",
		MODIFY COLUMN `snmp_community` varchar(100) NOT NULL DEFAULT "",
		MODIFY COLUMN `snmp_auth_protocol` char(6) DEFAULT ""');

	db_install_execute('ALTER TABLE `data_input_data`
		MODIFY COLUMN `t_value` char(2) DEFAULT ""');

	db_install_add_key('data_input_data', 'index', 't_value', array('t_value'), 'BTREE');

	db_install_execute('ALTER TABLE `data_input_fields`
		MODIFY COLUMN `input_output` char(3) NOT NULL DEFAULT "",
		MODIFY COLUMN `update_rra` char(2) DEFAULT "0",
		MODIFY COLUMN `type_code` varchar(40) DEFAULT "",
		MODIFY COLUMN `allow_nulls` char(2) DEFAULT ""');

	db_install_add_key('data_input_fields', 'index', 'type_code_data_input_id', array('type_code','data_input_id'), 'BTREE');

	db_install_execute('ALTER TABLE `data_template_data`
		MODIFY COLUMN `t_name` char(2) DEFAULT "",
		MODIFY COLUMN `t_active` char(2) DEFAULT "",
		MODIFY COLUMN `active` char(2) DEFAULT "",
		MODIFY COLUMN `t_rrd_step` char(2) DEFAULT "",
		MODIFY COLUMN `data_source_profile_id` mediumint(8) unsigned NOT NULL DEFAULT "1"');

	db_install_execute('ALTER TABLE `data_template_rrd`
		MODIFY COLUMN `local_data_template_rrd_id` mediumint(8) unsigned NOT NULL DEFAULT "0",
		MODIFY COLUMN `t_rrd_maximum` char(2) DEFAULT "",
		MODIFY COLUMN `t_rrd_minimum` char(2) DEFAULT "",
		MODIFY COLUMN `t_rrd_heartbeat` char(2) DEFAULT "",
		MODIFY COLUMN `t_data_source_type_id` char(2) DEFAULT "",
		MODIFY COLUMN `t_data_source_name` char(2) DEFAULT "",
		MODIFY COLUMN `t_data_input_field_id` char(2) DEFAULT ""');

	db_install_add_key('data_template_rrd', 'index', 'local_data_template_rrd_id', array('local_data_template_rrd_id'), 'BTREE');

	db_install_execute('ALTER TABLE `graph_local`
		MODIFY COLUMN `snmp_query_id` mediumint(8) NOT NULL DEFAULT "0",
		MODIFY COLUMN `snmp_query_graph_id` mediumint(8) NOT NULL DEFAULT "0"');

	db_install_add_key('graph_local', 'index', 'snmp_query_id', array('snmp_query_id'), 'BTREE');

	db_install_execute('ALTER TABLE `graph_templates_graph`
		MODIFY COLUMN `t_image_format_id` char(2) DEFAULT "",
		MODIFY COLUMN `t_title` char(2) DEFAULT "",
		MODIFY COLUMN `t_height` char(2) DEFAULT "",
		MODIFY COLUMN `t_width` char(2) DEFAULT "",
		MODIFY COLUMN `t_upper_limit` char(2) DEFAULT "",
		MODIFY COLUMN `t_lower_limit` char(2) DEFAULT "",
		MODIFY COLUMN `t_vertical_label` char(2) DEFAULT "",
		MODIFY COLUMN `t_slope_mode` char(2) DEFAULT "",
		MODIFY COLUMN `slope_mode` char(2) DEFAULT "on",
		MODIFY COLUMN `t_auto_scale` char(2) DEFAULT "",
		MODIFY COLUMN `auto_scale` char(2) DEFAULT "",
		MODIFY COLUMN `t_auto_scale_opts` char(2) DEFAULT "",
		MODIFY COLUMN `t_auto_scale_log` char(2) DEFAULT "",
		MODIFY COLUMN `auto_scale_log` char(2) DEFAULT "",
		MODIFY COLUMN `t_scale_log_units` char(2) DEFAULT "",
		MODIFY COLUMN `t_auto_scale_rigid` char(2) DEFAULT "",
		MODIFY COLUMN `auto_scale_rigid` char(2) DEFAULT "",
		MODIFY COLUMN `t_auto_padding` char(2) DEFAULT "",
		MODIFY COLUMN `auto_padding` char(2) DEFAULT "",
		MODIFY COLUMN `t_base_value` char(2) DEFAULT "",
		MODIFY COLUMN `t_grouping` char(2) DEFAULT "",
		MODIFY COLUMN `grouping` char(2) NOT NULL DEFAULT "",
		MODIFY COLUMN `t_unit_value` char(2) DEFAULT "",
		MODIFY COLUMN `t_unit_exponent_value` char(2) DEFAULT ""');

	db_install_execute('ALTER TABLE `graph_templates_item`
		MODIFY COLUMN `task_item_id` mediumint(8) unsigned NOT NULL DEFAULT "0",
		MODIFY COLUMN `alpha` char(2) DEFAULT "FF",
		MODIFY COLUMN `hard_return` char(2) DEFAULT ""');

	db_install_add_key('graph_templates_item', 'index', 'task_item_id', array('task_item_id'), 'BTREE');

	db_install_execute('ALTER TABLE `graph_tree`
		MODIFY COLUMN `sequence` int(10) unsigned DEFAULT "1"');

	db_install_execute('ALTER TABLE `host`
		MODIFY COLUMN `poller_id` int(10) unsigned NOT NULL DEFAULT "1",
		MODIFY COLUMN `site_id` int(10) unsigned NOT NULL DEFAULT "1",
		MODIFY COLUMN `hostname` varchar(100) DEFAULT "",
		MODIFY COLUMN `snmp_auth_protocol` char(6) DEFAULT "",
		MODIFY COLUMN `snmp_priv_protocol` char(6) DEFAULT "",
		MODIFY COLUMN `disabled` char(2) DEFAULT "",
		MODIFY COLUMN `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP');

	db_install_execute('ALTER TABLE `host_snmp_cache`
		MODIFY COLUMN `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP');

	db_install_add_key('host_snmp_cache', 'index', 'snmp_query_id', array('snmp_query_id'), 'BTREE');

	db_install_execute('ALTER TABLE `plugin_config`
		MODIFY COLUMN `id` mediumint(8) unsigned NOT NULL auto_increment');

	db_install_execute('ALTER TABLE `plugin_db_changes`
		MODIFY COLUMN `id` mediumint(8) unsigned NOT NULL auto_increment');

	db_install_execute('ALTER TABLE `plugin_hooks`
		MODIFY COLUMN `id` mediumint(8) unsigned NOT NULL auto_increment');

	db_install_execute('ALTER TABLE `plugin_realms`
		MODIFY COLUMN `id` mediumint(8) unsigned NOT NULL auto_increment');

	db_install_execute('ALTER TABLE `poller`
		MODIFY COLUMN `hostname` varchar(100) NOT NULL DEFAULT "",
		MODIFY COLUMN `dbhost` varchar(64) NOT NULL DEFAULT "cacti"');

	db_install_execute('ALTER TABLE `poller_command`
		MODIFY COLUMN `poller_id` smallint(5) unsigned NOT NULL DEFAULT "1",
		MODIFY COLUMN `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP');

	db_install_execute('ALTER TABLE `poller_item`
		MODIFY COLUMN `poller_id` int(10) unsigned NOT NULL DEFAULT "1",
		MODIFY COLUMN `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		MODIFY COLUMN `hostname` varchar(100) NOT NULL DEFAULT "",
		MODIFY COLUMN `snmp_auth_protocol` char(6) NOT NULL DEFAULT ""');

	db_install_execute('ALTER TABLE `poller_output_realtime`
		MODIFY COLUMN `poller_id` varchar(256) NOT NULL DEFAULT "1"');

	db_install_execute('ALTER TABLE `poller_time`
		MODIFY COLUMN `poller_id` int(10) unsigned NOT NULL DEFAULT "1"');

	db_install_add_key('snmp_query_graph_rrd', 'index', 'data_template_rrd_id', array('data_template_rrd_id'), 'BTREE');

	db_install_execute('ALTER TABLE `snmp_query_graph_rrd_sv`
		MODIFY COLUMN `data_template_id` mediumint(8) unsigned NOT NULL DEFAULT "0"');

	db_install_add_key('snmp_query_graph_rrd_sv', 'index', 'data_template_id', array('data_template_id'), 'BTREE');

	db_install_execute('ALTER TABLE `snmpagent_cache`
		MODIFY COLUMN `max-access` varchar(50) NOT NULL DEFAULT "not-accessible"');

	db_install_execute('ALTER TABLE `snmpagent_managers`
		MODIFY COLUMN `snmp_version` tinyint(1) unsigned NOT NULL DEFAULT "1",
		MODIFY COLUMN `snmp_community` varchar(100) NOT NULL DEFAULT "",
		MODIFY COLUMN `snmp_auth_protocol` char(6) NOT NULL DEFAULT "",
		MODIFY COLUMN `snmp_priv_protocol` char(6) NOT NULL DEFAULT "",
		MODIFY COLUMN `snmp_port` mediumint(5) unsigned NOT NULL DEFAULT "161"');

	db_install_execute('ALTER TABLE `snmpagent_notifications_log`
		MODIFY COLUMN `notification` varchar(190) NOT NULL DEFAULT ""');

	db_install_execute('ALTER TABLE `snmpagent_notifications_log`
		MODIFY COLUMN `mib` varchar(50) NOT NULL DEFAULT ""');

	db_install_execute('ALTER TABLE `user_auth`
		MODIFY COLUMN `realm` mediumint(8) NOT NULL DEFAULT "0",
		MODIFY COLUMN `must_change_password` char(2) DEFAULT "",
		MODIFY COLUMN `show_tree` char(2) DEFAULT "on",
		MODIFY COLUMN `show_list` char(2) DEFAULT "on",
		MODIFY COLUMN `show_preview` char(2) NOT NULL DEFAULT "on",
		MODIFY COLUMN `graph_settings` char(2) DEFAULT "",
		MODIFY COLUMN `reset_perms` int(12) unsigned NOT NULL DEFAULT "0"');

	db_install_add_key('user_auth', 'index', 'realm', array('realm'), 'BTREE');

	db_install_execute('ALTER TABLE `user_auth_cache`
		MODIFY COLUMN `hostname` varchar(100) NOT NULL DEFAULT ""');

	db_install_execute('ALTER TABLE `version`
		MODIFY COLUMN `cacti` char(20) NOT NULL DEFAULT ""');

	db_install_drop_key('version', 'key', 'PRIMARY');

	db_install_add_key('version', 'key', 'PRIMARY', array('cacti'), 'BTREE');
}

