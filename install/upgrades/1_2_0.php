<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
	db_install_add_column('poller', array('name' => 'sync_interval', 'type' => 'int', 'after' => 'threads', 'default' => '7200'));
	db_install_add_column('poller', array('name' => 'timezone', 'type' => 'varchar(40)', 'default' => '', 'after' => 'status'));
	db_install_add_column('poller', array('name' => 'dbsslkey', 'type' => 'varchar(255)', 'after' => 'dbssl'));
	db_install_add_column('poller', array('name' => 'dbsslcert', 'type' => 'varchar(255)', 'after' => 'dbssl'));
	db_install_add_column('poller', array('name' => 'dbsslca', 'type' => 'varchar(255)', 'after' => 'dbssl'));
	db_install_add_column('poller', array('name' => 'dbsslcapath', 'type' => 'varchar(255)', 'after' => 'dbssl'));
	db_install_add_column('poller', array('name' => 'dbsslverifyservercert', 'type' => 'char(3)', 'after' => 'dbssl', 'default' => 'on'));
	if (!$poller_exists) {
		// Take the value from the settings table and translate to
		// the new Data Collector table settings

		// Ensure value falls in line with what we expect for processes
		$max_processes = read_config_option('concurrent_processes');

		if ($max_processes < 1) {
			$max_processes = 1;
		}

		if ($max_processes > 10) {
			$max_processes = 10;
		}

		// Ensure value falls in line with what we expect for threads
		$max_threads = read_config_option('max_threads');

		if ($max_threads < 1) {
			$max_threads = 1;
		}

		if ($max_threads > 100) {
			$max_threads = 100;
		}

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

	$log_validation_results = db_install_fetch_cell('SELECT value FROM settings WHERE name=\'log_validation\'');
	$log_validation         = $log_validation_results['data'];

	$log_developer_results  = db_install_fetch_cell('SELECT value FROM settings WHERE name=\'developer_mode\'');
	$log_developer          = $log_developer_results['data'];

	if ($log_developer !== false && $log_validation === false) {
		db_install_execute('UPDATE settings
			SET name="log_validation" WHERE name="developer_mode"');
	}

	db_install_add_column('automation_networks', array('name' => 'notification_enabled', 'type' => 'char(2)', 'default' => '', 'after' => 'enabled'));
	db_install_add_column('automation_networks', array('name' => 'notification_email', 'type' => 'varchar(255)', 'default' => '', 'after' => 'notification_enabled'));
	db_install_add_column('automation_networks', array('name' => 'notification_fromname', 'type' => 'varchar(32)', 'default' => '', 'after' => 'notification_email'));
	db_install_add_column('automation_networks', array('name' => 'notification_fromemail', 'type' => 'varchar(128)', 'default' => '', 'after' => 'notification_fromname'));

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
			`info` text NOT NULL,
			`issue` text NOT NULL,
			PRIMARY KEY (`id`),
			KEY `user` (`user`),
			KEY `done` (`done`),
			KEY `datasource` (`datasource`),
			KEY `started` (`started`))
			ROW_FORMAT=Dynamic
			ENGINE=InnoDB
			COMMENT = 'Datasource Debugger Information';");
	}

	// Upgrade debug plugin to core access by removing custom realm
	$debug_id_reports = db_install_fetch_cell('SELECT id FROM plugin_config WHERE name = \'Debug\'');
	$debug_id         = $debug_id_reports['data'];

	if ($debug_id !== false && $debug_id > 0) {
		// Plugin realms are plugin_id + 100
		$debug_id += 100;
		db_execute_prepared('DELETE FROM user_auth_realm WHERE realm_id = ?', array($debug_id));
		db_execute_prepared('DELETE FROM user_auth_group_realm WHERE realm_id = ?', array($debug_id));
	}

	// Fix data source stats column type
	$value_parms = db_get_column_attributes('data_source_stats_hourly_last', 'value');

	if (cacti_sizeof($value_parms)) {
		if ($value_parms[0]['COLUMN_TYPE'] != 'double') {
			db_install_execute('ALTER TABLE data_source_stats_hourly_last MODIFY COLUMN `value` DOUBLE DEFAULT NULL');
		}
	}

	// Resolve issues with bogus templates issue #1761
	$snmp_queries_results = db_install_fetch_assoc('SELECT id, name
		FROM snmp_query
		ORDER BY id');
	$snmp_queries = $snmp_queries_results['data'];

	if (cacti_sizeof($snmp_queries)) {
		foreach ($snmp_queries as $query) {
			db_execute_prepared("UPDATE graph_local AS gl
				INNER JOIN (
					SELECT graph_template_id
					FROM graph_local AS gl
					WHERE snmp_query_id = ?
					HAVING graph_template_id NOT IN (
						SELECT graph_template_id
						FROM snmp_query_graph
						WHERE snmp_query_id = ?)
				) AS rs
				ON gl.graph_template_id=rs.graph_template_id
				SET snmp_query_id=0, snmp_query_graph_id=0, snmp_index=''",
				array($query['id'], $query['id']));
		}
	}

	$ids_results = db_install_fetch_assoc('SELECT *
		FROM graph_local
		WHERE snmp_query_id > 0
		AND snmp_query_graph_id = 0');
	$ids = $ids_results['data'];

	if (cacti_sizeof($ids)) {
		foreach ($ids as $id) {
			$query_graph_id_results = db_install_fetch_cell('SELECT id
				FROM snmp_query_graph
				WHERE snmp_query_id = ?
				AND graph_template_id = ?',
				array($id['snmp_query_id'], $id['graph_template_id']));
			$query_graph_id = $query_graph_id_results['data'];

			if (empty($query_graph_id)) {
				db_execute_prepared('UPDATE graph_local
					SET snmp_query_id=0, snmp_query_graph_id=0, snmp_index=""
					WHERE id = ?',
					array($id['id']));
			} else {
				db_execute_prepared('UPDATE graph_local
					SET snmp_query_graph_id=?
					WHERE id = ?',
					array($query_graph_id, $id['id']));
			}
		}
	}

	db_install_execute('UPDATE graph_tree_items
		SET host_grouping_type = 1
		WHERE host_id > 0
		AND host_grouping_type = 0');

	db_install_execute('UPDATE automation_tree_rules
		SET host_grouping_type = 1
		WHERE host_grouping_type = 0');

	db_install_execute("UPDATE settings
		SET value = IF(value = '1', 'on', '')
		WHERE name = 'hide_console' and value != 'on'");

	db_install_add_column('sites', array('name' => 'zoom', 'type' => 'tinyint', 'unsigned' => true, 'NULL' => true));

	db_install_drop_key('poller_reindex', 'key', 'PRIMARY');

	db_install_add_key('poller_reindex', 'key', 'PRIMARY', array('host_id', 'data_query_id', 'arg1(187)'));

	db_install_add_column('poller', array('name' => 'last_sync', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'));
	db_install_add_column('poller', array('name' => 'requires_sync', 'type' => 'char(3)', 'NULL' => false, 'default' => ''));

	db_install_execute('UPDATE poller SET requires_sync = "on" WHERE id != 1');

	db_install_execute('UPDATE host SET status = 0 WHERE disabled = "on"');

	db_install_add_column('host', array('name' => 'deleted', 'type' => 'char(2)', 'default' => '', 'NULL' => true, 'after' => 'device_threads'));
}
