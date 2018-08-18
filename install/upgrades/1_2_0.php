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

function upgrade_to_1_2_0() {
	db_install_add_column('user_domains_ldap', array('name' => 'cn_full_name', 'type' => 'varchar(50)', 'NULL' => true, 'default' => ''));
	db_install_add_column('user_domains_ldap', array('name' => 'cn_email', 'type' => 'varchar(50)', 'NULL' => true, 'default' => ''));

	$poller_exists = db_column_exists('poller', 'processes');

	db_install_add_column('poller', array('name' => 'max_time', 'type' => 'double', 'after' => 'total_time'));
	db_install_add_column('poller', array('name' => 'min_time', 'type' => 'double', 'after' => 'max_time'));
	db_install_add_column('poller', array('name' => 'avg_time', 'type' => 'double', 'after' => 'min_time'));
	db_install_add_column('poller', array('name' => 'total_polls', 'type' => 'int', 'after' => 'avg_time', 'default' => '0'));
	db_install_add_column('poller', array('name' => 'processes', 'type' => 'int', 'after' => 'total_polls', 'default' => '1'));
	db_install_add_column('poller', array('name' => 'threads', 'type' => 'double', 'after' => 'processes', 'default' => '1'));
	db_install_add_column('poller', array('name' => 'timezone', 'type' => 'varchar(40)', 'default' => '', 'after' => 'status'));

	if ($poller_exists) {
		// Take the value from the settings table and translate to
		// the new Data Collector table settings

		// Ensure value falls in line with what we expect for processes
		$max_processes = read_config_option('concurrent_processes');
		if ($max_processes < 1) $max_processes = 1;
		if ($max_processes > 10) $max_processes = 10;

		// Ensure value falls in line with what we expect for threads
		$max_threads = read_config_option('max_threads');
		if ($max_threads < 1) $max_threads = 1;
		if ($max_threads > 100) $max_threads = 100;

		db_install_execute("UPDATE poller SET processes = $max_processes, threads = $max_threads");
	}

	db_install_add_column('host', array('name' => 'location', 'type' => 'varchar(40)', 'after' => 'hostname'));
	db_install_add_key('host', 'index', 'site_id_location', array('site_id', 'location'));

	db_install_add_column('poller_resource_cache', array('name' => 'attributes', 'type' => 'int unsigned', 'default' => '0'));
	db_install_add_column('external_links', array('name' => 'refresh', 'type' => 'int unsigned'));
	db_install_add_column('automation_networks', array('name' => 'same_sysname', 'type' => 'char(2)', 'default' => '', 'after' => 'add_to_cacti'));

	db_install_execute("ALTER TABLE user_auth
		MODIFY COLUMN password varchar(256) NOT NULL DEFAULT ''");

	db_install_execute("ALTER TABLE graph_tree_items
		MODIFY COLUMN sort_children_type tinyint(3) unsigned NOT NULL DEFAULT '0'");

	db_install_execute('UPDATE graph_templates_graph
		SET t_title="" WHERE t_title IS NULL or t_title="0"');

	$log_validation = db_fetch_cell('SELECT value FROM settings WHERE name=\'log_validation\'');
	$log_developer  = db_fetch_cell('SELECT value FROM settings WHERE name=\'developer_mode\'');
	if ($log_developer !== false && $log_validation === false) {
		db_install_execute('UPDATE settings
			SET name="log_validation" WHERE name="developer_mode"');
	}

	db_install_add_column('automation_networks', array('name' => 'notification_enabled', 'type' => 'char(2)', 'default' => '', 'after' => 'enabled'));
	db_install_add_column('automation_networks', array('name' => 'notification_email', 'type' => 'varchar(255)', 'default' => "", 'after' => 'notification_enabled'));
	db_install_add_column('automation_networks', array('name' => 'notification_fromname', 'type' => 'varchar(32)', 'default' => "", 'after' => 'notification_email'));
	db_install_add_column('automation_networks', array('name' => 'notification_fromemail', 'type' => 'varchar(128)', 'default' => "", 'after' => 'notification_fromname'));

	if (db_table_exists('dsdebug')) {
		db_install_rename_table('dsdebug','data_debug');
	}

	if (!db_table_exists('data_debug')) {
		db_install_execute("CREATE TABLE `data_debug` (
			`id` int(11) unsigned NOT NULL auto_increment,
			`started` int(11) NOT NULL DEFAULT '0',
			`done` int(11) NOT NULL DEFAULT '0',
			`user` int(11) NOT NULL DEFAULT '0',
			`datasource` int(11) NOT NULL DEFAULT '0',
			`info` text NOT NULL DEFAULT '',
			`issue` text NOT NULL NULL DEFAULT '',
			PRIMARY KEY (`id`),
			KEY `user` (`user`),
			KEY `done` (`done`),
			KEY `datasource` (`datasource`),
			KEY `started` (`started`))
			COMMENT = 'Datasource Debugger Information';");
	}

	// Upgrade debug plugin to core access by removing custom realm
	$debug_id = db_fetch_cell('SELECT id FROM plugin_config WHERE name = \'Debug\'');
	if ($debug_id !== false && $debug_id > 0) {
		// Plugin realms are plugin_id + 100
		$debug_id += 100;
		db_install_execute_prepared('DELETE FROM user_auth_realm WHERE id = ?', array($debug_id));
		db_install_execute_prepared('DELETE FROM user_auth_group_realm WHERE id = ?', array($debug_id));
	}

	// Fix data source stats column type
	$value_parms = db_get_column_attributes('data_source_stats_hourly_last', 'value');

	if (sizeof($value_parms)) {
		if ($value_parms[0]['COLUMN_TYPE'] != 'double') {
			db_install_execute('ALTER TABLE data_source_stats_hourly_last MODIFY COLUMN `value` DOUBLE DEFAULT NULL');
		}
	}
}
