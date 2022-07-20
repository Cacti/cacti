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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_1_0_0() {
	global $config, $plugins_integrated;

	$default_engine_result = db_install_fetch_row("SHOW GLOBAL VARIABLES LIKE 'default_storage_engine'");
	$default_engine = $default_engine_result['data'];

	if (!cacti_sizeof($default_engine)) {
		$default_engine_result = db_install_fetch_row("SHOW GLOBAL VARIABLES LIKE 'storage_engine'");
		$default_engine = $default_engine_result['data'];
	}

	if (cacti_sizeof($default_engine)) {
		$engine = $default_engine['Value'];
	} else {
		$engine = 'MyISAM';
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `user_auth_group` (
		`id` int(10) unsigned NOT NULL auto_increment,
		`name` varchar(20) NOT NULL,
		`description` varchar(255) NOT NULL default '',
		`graph_settings` varchar(2) DEFAULT NULL,
		`login_opts` tinyint(1) NOT NULL DEFAULT '1',
		`show_tree` varchar(2) DEFAULT 'on',
		`show_list` varchar(2) DEFAULT 'on',
		`show_preview` varchar(2) NOT NULL DEFAULT 'on',
		`policy_graphs` tinyint(1) unsigned NOT NULL DEFAULT '1',
		`policy_trees` tinyint(1) unsigned NOT NULL DEFAULT '1',
		`policy_hosts` tinyint(1) unsigned NOT NULL DEFAULT '1',
		`policy_graph_templates` tinyint(1) unsigned NOT NULL DEFAULT '1',
		`enabled` char(2) NOT NULL DEFAULT 'on',
		PRIMARY KEY (`id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Table that Contains User Groups';");

	db_install_execute("CREATE TABLE IF NOT EXISTS `user_auth_group_perms` (
		`group_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
		`item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
		`type` tinyint(2) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`group_id`,`item_id`,`type`),
		KEY `group_id` (`group_id`,`type`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Table that Contains User Group Permissions';");

	db_install_execute("CREATE TABLE IF NOT EXISTS `user_auth_group_realm` (
		`group_id` int(10) unsigned NOT NULL,
		`realm_id` int(10) unsigned NOT NULL,
		PRIMARY KEY  (`group_id`, `realm_id`),
		KEY `group_id` (`group_id`),
		KEY `realm_id` (`realm_id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Table that Contains User Group Realm Permissions';");

	db_install_execute("CREATE TABLE IF NOT EXISTS `user_auth_group_members` (
		`group_id` int(10) unsigned NOT NULL,
		`user_id` int(10) unsigned NOT NULL,
		PRIMARY KEY  (`group_id`, `user_id`),
		KEY `group_id` (`group_id`),
		KEY `realm_id` (`user_id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Table that Contains User Group Members';");

	db_install_execute("CREATE TABLE IF NOT EXISTS `settings_user_group` (
		`group_id` smallint(8) unsigned NOT NULL DEFAULT '0',
		`name` varchar(50) NOT NULL DEFAULT '',
		`value` varchar(2048) NOT NULL DEFAULT '',
		PRIMARY KEY (`group_id`,`name`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Stores the Default User Group Graph Settings';");

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_stats_daily` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`average` DOUBLE DEFAULT NULL,
		`peak` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=$engine
		ROW_FORMAT=Dynamic;");

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_stats_hourly` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`average` DOUBLE DEFAULT NULL,
		`peak` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=$engine
		ROW_FORMAT=Dynamic;");

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_stats_hourly_cache` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`time` timestamp NOT NULL default '0000-00-00 00:00:00',
		`value` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`time`,`rrd_name`),
		KEY `time` USING BTREE (`time`)
		) ENGINE=MEMORY;");

	db_install_execute('CREATE TABLE IF NOT EXISTS `data_source_stats_hourly_last` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`value` DOUBLE DEFAULT NULL,
		`calculated` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=MEMORY;');

	db_install_add_column('data_source_stats_hourly_last', array('name' => 'calculated', 'type' => 'DOUBLE', 'NULL' => true, 'default' => 'NULL', 'after' => 'value'));

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_stats_monthly` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`average` DOUBLE DEFAULT NULL,
		`peak` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=$engine
		ROW_FORMAT=Dynamic;");

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_stats_weekly` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`average` DOUBLE DEFAULT NULL,
		`peak` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=$engine
		ROW_FORMAT=Dynamic;");

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_stats_yearly` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`average` DOUBLE DEFAULT NULL,
		`peak` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=$engine
		ROW_FORMAT=Dynamic;");

	db_install_execute("CREATE TABLE IF NOT EXISTS `poller_output_boost` (
		`local_data_id` mediumint(8) unsigned NOT NULL default '0',
		`rrd_name` varchar(19) NOT NULL default '',
		`time` timestamp NOT NULL default '0000-00-00 00:00:00',
		`output` varchar(512) NOT NULL,
		PRIMARY KEY USING BTREE (`local_data_id`,`rrd_name`,`time`)
		) ENGINE=$engine
		ROW_FORMAT=Dynamic;");

	db_install_execute("CREATE TABLE IF NOT EXISTS `poller_output_boost_processes` (
		`sock_int_value` bigint(20) unsigned NOT NULL auto_increment,
		`status` varchar(255) default NULL,
		PRIMARY KEY (`sock_int_value`))
		ENGINE=MEMORY;");

	if (db_table_exists('plugin_domains', false)) {
		db_install_rename_table('plugin_domains', 'user_domains');
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `user_domains` (
		`domain_id` int(10) unsigned NOT NULL auto_increment,
		`domain_name` varchar(20) NOT NULL,
		`type` int(10) UNSIGNED NOT NULL DEFAULT '0',
		`enabled` char(2) NOT NULL DEFAULT 'on',
		`defdomain` tinyint(3) NOT NULL DEFAULT '0',
		`user_id` int(10) unsigned NOT NULL default '0',
		PRIMARY KEY  (`domain_id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Table to Hold Login Domains';");

	if (db_table_exists('plugin_domains_ldap', false)) {
		db_install_rename_table('plugin_domains_ldap', 'user_domains_ldap');
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `user_domains_ldap` (
		`domain_id` int(10) unsigned NOT NULL,
		`server` varchar(128) NOT NULL,
		`port` int(10) unsigned NOT NULL,
		`port_ssl` int(10) unsigned NOT NULL,
		`proto_version` tinyint(3) unsigned NOT NULL,
		`encryption` tinyint(3) unsigned NOT NULL,
		`referrals` tinyint(3) unsigned NOT NULL,
		`mode` tinyint(3) unsigned NOT NULL,
		`dn` varchar(128) NOT NULL,
		`group_require` char(2) NOT NULL,
		`group_dn` varchar(128) NOT NULL,
		`group_attrib` varchar(128) NOT NULL,
		`group_member_type` tinyint(3) unsigned NOT NULL,
		`search_base` varchar(128) NOT NULL,
		`search_filter` varchar(128) NOT NULL,
		`specific_dn` varchar(128) NOT NULL,
		`specific_password` varchar(128) NOT NULL,
		PRIMARY KEY  (`domain_id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Table to Hold Login Domains for LDAP';");

	if (db_table_exists('plugin_snmpagent_cache', false)) {
		db_install_rename_table('plugin_snmpagent_cache', 'snmpagent_cache');
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `snmpagent_cache` (
		`oid` varchar(191) NOT NULL,
		`name` varchar(191) NOT NULL,
		`mib` varchar(191) NOT NULL,
		`type` varchar(255) NOT NULL DEFAULT '',
		`otype` varchar(255) NOT NULL DEFAULT '',
		`kind` varchar(255) NOT NULL DEFAULT '',
		`max-access` varchar(255) NOT NULL DEFAULT 'not-accessible',
		`value` varchar(255) NOT NULL DEFAULT '',
		`description` varchar(5000) NOT NULL DEFAULT '',
		PRIMARY KEY (`oid`),
		KEY `name` (`name`),
		KEY `mib` (`mib`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='SNMP MIB CACHE';");

	if (db_table_exists('plugin_snmpagent_mibs', false)) {
		db_install_rename_table('plugin_snmpagent_mibs', 'snmpagent_mibs');
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `snmpagent_mibs` (
		`id` int(8) NOT NULL AUTO_INCREMENT,
		`name` varchar(32) NOT NULL DEFAULT '',
		`file` varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (`id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Registered MIB files';");

	if (db_table_exists('plugin_snmpagent_cache_notifications', false)) {
		db_install_rename_table('plugin_snmpagent_cache_notifications', 'snmpagent_cache_notifications');
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `snmpagent_cache_notifications` (
		`name` varchar(191) NOT NULL,
		`mib` varchar(255) NOT NULL,
		`attribute` varchar(255) NOT NULL,
		`sequence_id` smallint(6) NOT NULL,
		KEY `name` (`name`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Notifcations and related attributes';");

	if (db_table_exists('plugin_snmpagent_cache_textual_conventions', false)) {
		db_install_rename_table('plugin_snmpagent_cache_textual_conventions', 'snmpagent_cache_textual_conventions');
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `snmpagent_cache_textual_conventions` (
		`name` varchar(191) NOT NULL,
		`mib` varchar(191) NOT NULL,
		`type` varchar(255) NOT NULL DEFAULT '',
		`description` varchar(5000) NOT NULL DEFAULT '',
		KEY `name` (`name`),
		KEY `mib` (`mib`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Textual conventions';");

	if (db_table_exists('plugin_snmpagent_managers', false)) {
		db_install_rename_table('plugin_snmpagent_managers', 'snmpagent_managers');
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `snmpagent_managers` (
		`id` int(8) NOT NULL AUTO_INCREMENT,
		`hostname` varchar(100) NOT NULL,
		`description` varchar(255) NOT NULL,
		`disabled` char(2) DEFAULT NULL,
		`max_log_size` tinyint(1) NOT NULL,
		`snmp_version` varchar(255) NOT NULL,
		`snmp_community` varchar(255) NOT NULL,
		`snmp_username` varchar(255) NOT NULL,
		`snmp_auth_password` varchar(255) NOT NULL,
		`snmp_auth_protocol` varchar(255) NOT NULL,
		`snmp_priv_password` varchar(255) NOT NULL,
		`snmp_priv_protocol` varchar(255) NOT NULL,
		`snmp_engine_id` varchar(64) NOT NULL DEFAULT '',
		`snmp_port` varchar(255) NOT NULL,
		`snmp_message_type` tinyint(1) NOT NULL,
		`notes` text,
		PRIMARY KEY (`id`),
		KEY `hostname` (`hostname`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='snmp notification receivers';");

	if (!db_column_exists('snmpagent_managers', 'snmp_engine_id', false)) {
		db_install_execute('ALTER TABLE snmpagent_managers
			ADD COLUMN `snmp_engine_id` varchar(64) NOT NULL DEFAULT "" AFTER snmp_priv_protocol');
	}

	if (db_table_exists('plugin_snmpagent_managers_notifications', false)) {
		db_install_rename_table('plugin_snmpagent_managers_notifications', 'snmpagent_managers_notifications');
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `snmpagent_managers_notifications` (
		`manager_id` int(8) NOT NULL,
		`notification` varchar(190) NOT NULL,
		`mib` varchar(191) NOT NULL,
		KEY `mib` (`mib`),
		KEY `manager_id` (`manager_id`),
		KEY `manager_id2` (`manager_id`,`notification`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='snmp notifications to receivers';");

	if (db_table_exists('plugin_snmpagent_notifications_log', false)) {
		db_install_rename_table('plugin_snmpagent_notifications_log', 'snmpagent_notifications_log');
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `snmpagent_notifications_log` (
		`id` int(12) NOT NULL AUTO_INCREMENT,
		`time` int(24) NOT NULL,
		`severity` tinyint(1) NOT NULL,
		`manager_id` int(8) NOT NULL,
		`notification` varchar(190) NOT NULL,
		`mib` varchar(191) NOT NULL,
		`varbinds` varchar(5000) NOT NULL,
		PRIMARY KEY (`id`),
		KEY `time` (`time`),
		KEY `severity` (`severity`),
		KEY `manager_id` (`manager_id`),
		KEY `manager_id2` (`manager_id`,`notification`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='logs snmp notifications to receivers';");

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_purge_temp` (
		`id` integer UNSIGNED auto_increment,
		`name_cache` varchar(255) NOT NULL default '',
		`local_data_id` mediumint(8) unsigned NOT NULL default '0',
		`name` varchar(128) NOT NULL default '',
		`size` integer UNSIGNED NOT NULL default '0',
		`last_mod` TIMESTAMP NOT NULL default '0000-00-00 00:00:00',
		`in_cacti` tinyint NOT NULL default '0',
		`data_template_id` mediumint(8) unsigned NOT NULL default '0',
		PRIMARY KEY (`id`),
		UNIQUE KEY name (`name`),
		KEY local_data_id (`local_data_id`),
		KEY in_cacti (`in_cacti`),
		KEY data_template_id (`data_template_id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='RRD Cleaner File Repository';");

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_purge_action` (
		`id` integer UNSIGNED auto_increment,
		`name` varchar(128) NOT NULL default '',
		`local_data_id` mediumint(8) unsigned NOT NULL default '0',
		`action` tinyint(2) NOT NULL default 0,
		PRIMARY KEY (`id`),
		UNIQUE KEY name (`name`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='RRD Cleaner File Actions';");

	db_install_add_column('graph_tree', array('name' => 'enabled', 'type' => 'char(2)', 'default' => 'on', 'after' => 'id'));
	db_install_add_column('graph_tree', array('name' => 'locked', 'type' => 'TINYINT', 'default' => 0, 'after' => 'enabled'));
	db_install_add_column('graph_tree', array('name' => 'locked_date', 'type' => 'TIMESTAMP', 'default' => '0000-00-00', 'after' => 'locked'));
	db_install_add_column('graph_tree', array('name' => 'last_modified', 'type' => 'TIMESTAMP', 'default' => '0000-00-00', 'after' => 'name'));
	db_install_add_column('graph_tree', array('name' => 'user_id', 'type' => 'INT UNSIGNED', 'default' => 1, 'after' => 'name'));
	db_install_add_column('graph_tree', array('name' => 'modified_by', 'type' => 'INT UNSIGNED', 'default' => 1));

	db_install_add_column('graph_tree_items', array('name' => 'parent', 'type' => 'BIGINT UNSIGNED', 'NULL' => true, 'after' => 'id'));
	db_install_add_column('graph_tree_items', array('name' => 'position', 'type' => 'int UNSIGNED', 'NULL' => true, 'after' => 'parent'));

	db_install_execute("ALTER TABLE graph_tree_items MODIFY COLUMN id BIGINT UNSIGNED NOT NULL auto_increment");

	db_install_add_key('graph_tree_items', 'INDEX', 'parent', array('parent'));

	db_install_drop_table('user_auth_cache');
	db_install_execute("CREATE TABLE `user_auth_cache` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`user_id` int(10) unsigned NOT NULL DEFAULT '0',
		`hostname` varchar(64) NOT NULL DEFAULT '',
		`last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`token` varchar(191) NOT NULL DEFAULT '',
		PRIMARY KEY (`id`),
		UNIQUE KEY `tokenkey` (`token`),
		KEY `hostname` (`hostname`),
		KEY `user_id` (`user_id`))
		ENGINE=InnoDB
		ROW_FORMAT=Dynamic
		COMMENT='Caches Remember Me Details'");

	db_install_execute("ALTER TABLE host
		MODIFY COLUMN status_fail_date timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		MODIFY COLUMN status_rec_date timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'");

	db_install_execute("ALTER TABLE poller
		MODIFY COLUMN last_update timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'");

	db_install_execute("ALTER TABLE poller_command
		MODIFY COLUMN time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'");

	db_install_execute("ALTER TABLE poller_output
		MODIFY COLUMN time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'");

	db_install_execute("ALTER TABLE poller_time
		MODIFY COLUMN start_time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		MODIFY COLUMN end_time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'");

	db_install_execute("ALTER TABLE user_log
		MODIFY COLUMN time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'");

	// Add secpass fields
	db_install_add_column('user_auth', array('name' => 'lastchange', 'type' => 'int(12)', 'NULL' => false, 'default' => '-1'));
	db_install_add_column('user_auth', array('name' => 'lastlogin', 'type' => 'int(12)', 'NULL' => false, 'default' => '-1'));
	db_install_add_column('user_auth', array('name' => 'password_history', 'type' => 'varchar(4096)', 'NULL' => false, 'default' => '-1'));
	db_install_add_column('user_auth', array('name' => 'locked', 'type' => 'varchar(3)', 'NULL' => false, 'default' => ''));
	db_install_add_column('user_auth', array('name' => 'failed_attempts', 'type' => 'int(5)', 'NULL' => false, 'default' => '0'));
	db_install_add_column('user_auth', array('name' => 'lastfail', 'type' => 'int(12)', 'NULL' => false, 'default' => '0'));

	$pos_array = array();

	// Convert all trees to new format, but never run more than once
	if (db_column_exists('graph_tree_items', 'order_key', false)) {
		$trees_result = db_install_fetch_assoc('SELECT id FROM graph_tree ORDER BY id');
		$trees = $trees_result['data'];

		if (cacti_sizeof($trees)) {
			foreach($trees as $t) {
				$tree_items_result = db_install_fetch_assoc("SELECT *
					FROM graph_tree_items
					WHERE graph_tree_id = ?
					AND order_key NOT LIKE '___000%'
					ORDER BY order_key", array($t['id']), false);
				$tree_items = $tree_items_result['data'];

				/* reset the position variable in case we run more than once */
				db_install_execute('UPDATE graph_tree_items
					SET position=0
					WHERE graph_tree_id = ?',
					array($t['id']), false);

				$prev_parent = 0;
				$prev_id     = 0;
				$position    = 0;

				if (cacti_sizeof($tree_items)) {
					foreach($tree_items AS $item) {
						$translated_key = rtrim($item['order_key'], "0\r\n");
						$missing_len    = strlen($translated_key) % CHARS_PER_TIER;

						if ($missing_len > 0) {
							$translated_key .= substr('000', 0, $missing_len);
						}

						$parent_key_len   = strlen($translated_key) - CHARS_PER_TIER;
						$parent_key       = substr($translated_key, 0, $parent_key_len);

						$parent_id_result = db_install_fetch_cell('SELECT id
							FROM graph_tree_items
							WHERE graph_tree_id = ?
							AND order_key LIKE ?',
							array($item['graph_tree_id'],'\'' . $parent_key . '\'000%'), false);

						$parent_id = $parent_id_result['data'];

						if (empty($parent_id)) {
							$parent_id = 0;
						}

						/* get order */
						if ($parent_id != $prev_parent) {
							$position = 0;
						}

						if (!isset($pos_array[$parent_id])) {
							$position_result = db_install_fetch_cell('SELECT MAX(position)
								FROM graph_tree_items
								WHERE graph_tree_id = ?
								AND parent = ?',
								array($item['graph_tree_id'], $parent_id), false);

							$position = $position_result['data'] + 1;
						} else {
							$position = $pos_array[$parent_id] + 1;
						}

						$pos_array[$parent_id] = $position;

						$postion = $position_result['data'] + 1;

						db_install_execute('UPDATE graph_tree_items
							SET parent = ?, position = ?
							WHERE id = ?',
							array($parent_id, $position,  $item['id']), false);

						$prev_parent = $parent_id;
					}
				}

				/* get base tree items and set position */
				$tree_items_result = db_install_fetch_assoc('SELECT *
					FROM graph_tree_items
					WHERE graph_tree_id = ?
					AND order_key LIKE "___000%"
					ORDER BY order_key',
					array($t['id']), false);
				$tree_items = $tree_items_result['data'];

				$position  = 0;
				$parent_id = 0;
				if (cacti_sizeof($tree_items)) {
					foreach($tree_items as $item) {
						db_install_execute('UPDATE graph_tree_items
							SET parent = ?, position = ?
							WHERE id = ?',
							array($parent_id, $position,  $item['id']), false);

						$position++;
					}
				}
			}
		}

		db_install_drop_column('graph_tree_items', 'order_key');
	}

	/* handle all merged realms, drop plugin contents separately */
	upgrade_realms();

	snmpagent_cache_install();

	// Adding email column for future user
	db_install_add_column('user_auth', array('name' => 'email_address', 'type' => 'varchar(128)', 'NULL' => true, 'after' => 'full_name'));
	db_install_add_column('user_auth', array('name' => 'password_change', 'type' => 'char(2)', 'NULL' => true, 'default' => 'on', 'after' => 'must_change_password'));

	db_install_drop_table('poller_output_realtime');
	db_install_execute("CREATE TABLE poller_output_realtime (
		local_data_id mediumint(8) unsigned NOT NULL default '0',
		rrd_name varchar(19) NOT NULL default '',
		time timestamp NOT NULL default '0000-00-00 00:00:00',
		output text NOT NULL,
		poller_id varchar(256) NOT NULL default '',
		PRIMARY KEY  (local_data_id,rrd_name,`time`),
		KEY poller_id(poller_id(191)))
		ENGINE=$engine
		ROW_FORMAT=Dynamic");

	db_install_drop_table('poller_output_rt');

	// If we have never install Nectar before, we can simply install
	$nectar_tables_result = db_install_fetch_row("SHOW TABLES LIKE '%plugin_nectar%'");
	$nectar_tables        = $nectar_tables_result['data'];
	if (!cacti_sizeof($nectar_tables)) {
		db_install_execute("CREATE TABLE IF NOT EXISTS `reports` (
			`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			`user_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
			`name` varchar(100) NOT NULL DEFAULT '',
			`cformat` char(2) NOT NULL DEFAULT '',
			`format_file` varchar(255) NOT NULL DEFAULT '',
			`font_size` smallint(2) unsigned NOT NULL DEFAULT '0',
			`alignment` smallint(2) unsigned NOT NULL DEFAULT '0',
			`graph_linked` char(2) NOT NULL DEFAULT '',
			`intrvl` smallint(2) unsigned NOT NULL DEFAULT '0',
			`count` smallint(2) unsigned NOT NULL DEFAULT '0',
			`offset` int(12) unsigned NOT NULL DEFAULT '0',
			`mailtime` bigint(20) unsigned NOT NULL DEFAULT '0',
			`subject` varchar(64) NOT NULL DEFAULT '',
			`from_name` varchar(40) NOT NULL,
			`from_email` text NOT NULL,
			`email` text NOT NULL,
			`bcc` text NOT NULL,
			`attachment_type` smallint(2) unsigned NOT NULL DEFAULT '1',
			`graph_height` smallint(2) unsigned NOT NULL DEFAULT '0',
			`graph_width` smallint(2) unsigned NOT NULL DEFAULT '0',
			`graph_columns` smallint(2) unsigned NOT NULL DEFAULT '0',
			`thumbnails` char(2) NOT NULL DEFAULT '',
			`lastsent` bigint(20) unsigned NOT NULL DEFAULT '0',
			`enabled` char(2) DEFAULT '',
			PRIMARY KEY (`id`),
			KEY `mailtime` (`mailtime`))
			ENGINE=$engine
			ROW_FORMAT=Dynamic
			COMMENT='Cacri Reporting Reports'");

		db_install_execute("CREATE TABLE IF NOT EXISTS `reports_items` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`report_id` int(10) unsigned NOT NULL DEFAULT '0',
			`item_type` tinyint(1) unsigned NOT NULL DEFAULT '1',
			`tree_id` int(10) unsigned NOT NULL DEFAULT '0',
			`branch_id` int(10) unsigned NOT NULL DEFAULT '0',
			`tree_cascade` char(2) NOT NULL DEFAULT '',
			`graph_name_regexp` varchar(128) NOT NULL DEFAULT '',
			`host_template_id` int(10) unsigned NOT NULL DEFAULT '0',
			`host_id` int(10) unsigned NOT NULL DEFAULT '0',
			`graph_template_id` int(10) unsigned NOT NULL DEFAULT '0',
			`local_graph_id` int(10) unsigned NOT NULL DEFAULT '0',
			`timespan` int(10) unsigned NOT NULL DEFAULT '0',
			`align` tinyint(1) unsigned NOT NULL DEFAULT '1',
			`item_text` text NOT NULL,
			`font_size` smallint(2) unsigned NOT NULL DEFAULT '10',
			`sequence` smallint(5) unsigned NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`),
			KEY `report_id` (`report_id`))
			ENGINE=$engine
			ROW_FORMAT=Dynamic
			COMMENT='Cacti Reporting Items'");
	} else {
		db_install_rename_table('plugin_nectar', 'reports');
		db_install_rename_table('plugin_nectar_items', 'reports_items');
		db_install_execute("UPDATE IGNORE settings SET name=REPLACE(name, 'nectar','reports') WHERE name LIKE '%nectar%'");

		db_install_add_column('reports', array('name' => 'bcc',           'type' => 'TEXT', 'after' => 'email'));
		db_install_add_column('reports', array('name' => 'from_name',     'type' => 'VARCHAR(40)',  'NULL' => false, 'default' => '', 'after' => 'mailtime'));
		db_install_add_column('reports', array('name' => 'user_id',       'type' => 'mediumint(8)', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'id'));
		db_install_add_column('reports', array('name' => 'graph_width',   'type' => 'smallint(2)',  'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'attachment_type'));
		db_install_add_column('reports', array('name' => 'graph_height',  'type' => 'smallint(2)',  'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'graph_width'));
		db_install_add_column('reports', array('name' => 'graph_columns', 'type' => 'smallint(2)',  'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'graph_height'));
		db_install_add_column('reports', array('name' => 'thumbnails',    'type' => 'char(2)',      'NULL' => false, 'default' => '', 'after' => 'graph_columns'));
		db_install_add_column('reports', array('name' => 'font_size',     'type' => 'smallint(2)',  'NULL' => false, 'default' => '16', 'after' => 'name'));
		db_install_add_column('reports', array('name' => 'alignment',     'type' => 'smallint(2)',  'NULL' => false, 'default' => '0', 'after' => 'font_size'));
		db_install_add_column('reports', array('name' => 'cformat',       'type' => 'char(2)',      'NULL' => false, 'default' => '', 'after' => 'name'));
		db_install_add_column('reports', array('name' => 'format_file',   'type' => 'varchar(255)', 'NULL' => false, 'default' => '', 'after' => 'cformat'));
		db_install_add_column('reports', array('name' => 'graph_linked',  'type' => 'char(2)',      'NULL' => false, 'default' => '', 'after' => 'alignment'));
		db_install_add_column('reports', array('name' => 'subject',       'type' => 'varchar(64)',  'NULL' => false, 'default' => '', 'after' => 'mailtime'));

		/* plugin_reports_items upgrade */
		db_install_add_column('reports_items', array('name' => 'host_template_id',  'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'item_type'));
		db_install_add_column('reports_items', array('name' => 'graph_template_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'host_id'));
		db_install_add_column('reports_items', array('name' => 'tree_id',           'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'item_type'));
		db_install_add_column('reports_items', array('name' => 'branch_id',         'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'tree_id'));
		db_install_add_column('reports_items', array('name' => 'tree_cascade',      'type' => 'char(2)', 'NULL' => false, 'default' => '', 'after' => 'branch_id'));
		db_install_add_column('reports_items', array('name' => 'graph_name_regexp', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '', 'after' => 'tree_cascade'));


		/* fix host templates and graph template ids */
		$items_result = db_install_fetch_assoc("SELECT * FROM reports_items WHERE item_type=1");
		$items        = $items_result['data'];
		if (cacti_sizeof($items)) {
			foreach ($items as $row) {
				$host_results = db_install_fetch_row('SELECT host.*
					FROM graph_local
					LEFT JOIN host
					ON (graph_local.host_id=host.id)
					WHERE graph_local.id = ?',
					array($row['local_graph_id']), false);
				$host = $host_results['data'];

				if (cacti_sizeof($host)) {
					$graph_template = db_install_fetch_cell('SELECT graph_template_id
						FROM graph_local
						WHERE id = ?',
						array($row['local_graph_id']), false);

					db_install_execute('UPDATE reports_items SET
						host_id = ?,
						host_template_id = ?,
						graph_template_id = ?
						WHERE id = ?',
						array($host['id'],$host['host_template_id'],$graph_template,$row['id']), false);
				}
			}
		}
	}

	db_install_add_column('host', array('name' => 'snmp_sysDescr',          'type' => 'varchar(300)', 'NULL' => false, 'default' => '',  'after' => 'snmp_timeout'));
	db_install_add_column('host', array('name' => 'snmp_sysObjectID',       'type' => 'varchar(64)',  'NULL' => false, 'default' => '',  'after' => 'snmp_sysDescr'));
	db_install_add_column('host', array('name' => 'snmp_sysUpTimeInstance', 'type' => 'int',          'NULL' => false, 'default' => '0', 'after' => 'snmp_sysObjectID', 'unsigned' => true));
	db_install_add_column('host', array('name' => 'snmp_sysContact',        'type' => 'varchar(300)', 'NULL' => false, 'default' => '',  'after' => 'snmp_sysUpTimeInstance'));
	db_install_add_column('host', array('name' => 'snmp_sysName',           'type' => 'varchar(300)', 'NULL' => false, 'default' => '',  'after' => 'snmp_sysContact'));
	db_install_add_column('host', array('name' => 'snmp_sysLocation',       'type' => 'varchar(300)', 'NULL' => false, 'default' => '',  'after' => 'snmp_sysName'));
	db_install_add_column('host', array('name' => 'polling_time',           'type' => 'DOUBLE',                        'default' => '0', 'after' => 'avg_time'));

	if (!db_table_exists('aggregate_graph_templates')) {
		/* Aggregate Merge Changes */

		/* V064 -> V065 tables were renamed */
		if (db_table_exists('plugin_color_templates', false)) {
			db_install_rename_table('plugin_color_templates', 'plugin_aggregate_color_templates');
		}

		if (db_table_exists('plugin_color_templates_item', false)) {
			db_install_rename_table('plugin_color_templates_item', 'plugin_aggregate_color_template_items');
		}

		$data = array();
		$data['columns'][] = array('name' => 'color_template_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'name', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
		$data['primary']   = 'color_template_id';
		$data['keys'][]    = ''; # lib/plugins.php _requires_ keys!
		$data['type']      = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment']   = 'Color Templates';
		db_table_create('plugin_aggregate_color_templates', $data);

		$sql[] = "INSERT IGNORE INTO `plugin_aggregate_color_templates` " .
			"(`color_template_id`, `name`) " .
			"VALUES " .
			"(1, 'Yellow: light -> dark, 4 colors'), " .
			"(2, 'Red: light yellow > dark red, 8 colors'), " .
			"(3, 'Red: light -> dark, 16 colors'), " .
			"(4, 'Green: dark -> light, 16 colors');";

		$data = array();
		$data['columns'][] = array('name' => 'color_template_item_id', 'type' => 'int(12)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'color_template_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'color_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'sequence', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['primary']   = 'color_template_item_id';
		$data['keys'][]    = ''; # lib/plugins.php _requires_ keys!
		$data['type']      = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment']   = 'Color Items for Color Templates';
		db_table_create ('plugin_aggregate_color_template_items', $data);

		$sql[] = 'INSERT IGNORE INTO `plugin_aggregate_color_template_items` ' .
			'(`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) VALUES ' .
			'(1, 1, 4, 1), (2, 1, 24, 2), (3, 1, 98, 3), (4, 1, 25, 4), ' .
			'(5, 2, 25, 1), (6, 2, 29, 2), (7, 2, 30, 3), (8, 2, 31, 4), (9, 2, 33, 5), (10, 2, 35, 6), (11, 2, 41, 7), (12, 2, 9, 8), ' .
			'(13, 3, 15, 1), (14, 3, 31, 2), (15, 3, 28, 3), (16, 3, 8, 4), (17, 3, 34, 5), (18, 3, 33, 6), (19, 3, 35, 7), (20, 3, 41, 8), ' .
			'(21, 3, 36, 9), (22, 3, 42, 10), (23, 3, 44, 11), (24, 3, 48, 12), (25, 3, 9, 13), (26, 3, 49, 14), (27, 3, 51, 15), (28, 3, 52, 16), ' .
			'(29, 4, 76, 1), (30, 4, 84, 2), (31, 4, 89, 3), (32, 4, 17, 4), (33, 4, 86, 5), (34, 4, 88, 6), (35, 4, 90, 7), (36, 4, 94, 8), ' .
			'(37, 4, 96, 9), (38, 4, 93, 10), (39, 4, 91, 11), (40, 4, 22, 12), (41, 4, 12, 13), (42, 4, 95, 14), (43, 4, 6, 15), (44, 4, 92, 16);';

		# now run all SQL commands
		if (cacti_sizeof($sql)) {
			foreach ($sql as $query) {
				$result = db_install_execute($query);
			}
			$sql = array();
		}

		// Autom8 Upgrade
		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'name', 'type' => 'VARCHAR(64)', 'NULL' => false);
		$data['columns'][] = array('name' => 'graph_template_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'gprint_prefix', 'type' => 'VARCHAR(64)', 'NULL' => false);
		$data['columns'][] = array('name' => 'graph_type', 'type' => 'INTEGER', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'total', 'type' => 'INTEGER', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'total_type', 'type' => 'INTEGER', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'total_prefix', 'type' => 'VARCHAR(64)', 'NULL' => false);
		$data['columns'][] = array('name' => 'order_type', 'type' => 'INTEGER', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'created', 'type' => 'TIMESTAMP', 'NULL' => false);
		$data['columns'][] = array('name' => 'user_id', 'type' => 'INTEGER', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['primary']   = 'id';
		$data['keys'][]    = array('name' => 'graph_template_id' , 'columns' => 'graph_template_id');
		$data['keys'][]    = array('name' => 'user_id' , 'columns' => 'user_id');
		$data['type']      = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment']   = 'Template Definitions for Aggregate Graphs';
		db_table_create ('plugin_aggregate_graph_templates', $data);

		$data = array();
		$data['columns'][] = array('name' => 'aggregate_template_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'graph_templates_item_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'sequence', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'color_template', 'type' => 'int(11)', 'NULL' => false);
		$data['columns'][] = array('name' => 'item_skip', 'type' => 'CHAR(2)', 'NULL' => false);
		$data['columns'][] = array('name' => 'item_total', 'type' => 'CHAR(2)', 'NULL' => false);
		$data['primary']   = 'aggregate_template_id`,`graph_templates_item_id';
		$data['keys'][]    = '';
		$data['type']      = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment']   = 'Aggregate Template Graph Items';
		db_table_create ('plugin_aggregate_graph_templates_item', $data);

		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'aggregate_template_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'template_propogation', 'type' => 'CHAR(2)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'local_graph_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'title_format', 'type' => 'VARCHAR(128)', 'NULL' => false);
		$data['columns'][] = array('name' => 'graph_template_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'gprint_prefix', 'type' => 'VARCHAR(64)', 'NULL' => false);
		$data['columns'][] = array('name' => 'graph_type', 'type' => 'INTEGER', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'total', 'type' => 'INTEGER', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'total_type', 'type' => 'INTEGER', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'total_prefix', 'type' => 'VARCHAR(64)', 'NULL' => false);
		$data['columns'][] = array('name' => 'order_type', 'type' => 'INTEGER', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'created', 'type' => 'TIMESTAMP', 'NULL' => false);
		$data['columns'][] = array('name' => 'user_id', 'type' => 'INTEGER', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['primary']   = 'id';
		$data['keys'][]    = array('name' => 'aggregate_template_id', 'columns' => 'aggregate_template_id');
		$data['keys'][]    = array('name' => 'local_graph_id', 'columns' => 'local_graph_id');
		$data['keys'][]    = array('name' => 'title_format', 'columns' => 'title_format');
		$data['keys'][]    = array('name' => 'user_id', 'columns' => 'user_id');
		$data['type']      = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment']   = 'Aggregate Graph Definitions';
		db_table_create ('plugin_aggregate_graphs', $data);

		$data = array();
		$data['columns'][] = array('name' => 'aggregate_graph_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'local_graph_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'sequence', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['primary']   = 'aggregate_graph_id`,`local_graph_id';
		$data['keys'][]    = '';
		$data['type']      = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment']   = 'Aggregate Graph Items';
		db_table_create ('plugin_aggregate_graphs_items', $data);

		$data = array();
		$data['columns'][] = array('name' => 'aggregate_graph_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'graph_templates_item_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'sequence', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'color_template', 'type' => 'int(11)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 'item_skip', 'type' => 'CHAR(2)', 'NULL' => false);
		$data['columns'][] = array('name' => 'item_total', 'type' => 'CHAR(2)', 'NULL' => false);
		$data['primary']   = 'aggregate_graph_id`,`graph_templates_item_id';
		$data['keys'][]    = '';
		$data['type']      = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment']   = 'Aggregate Graph Graph Items';
		db_table_create ('plugin_aggregate_graphs_graph_item', $data);

		/* TODO should this go in a seperate upgrade function? */
		/* Create table holding aggregate template graph params */
		$data = array();
		$data['columns'][] = array('name' => 'aggregate_template_id', 'type' => 'int(10)', 'unsigned' => 'unsigned', 'NULL' => false);
		$data['columns'][] = array('name' => 't_image_format_id', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'image_format_id', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 't_height', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'height', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 't_width', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'width', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 't_upper_limit', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'upper_limit', 'type' => 'varchar(20)', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 't_lower_limit', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'lower_limit', 'type' => 'varchar(20)', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 't_vertical_label', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'vertical_label', 'type' => 'varchar(200)', 'default' => '');
		$data['columns'][] = array('name' => 't_slope_mode', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'slope_mode', 'type' => 'char(2)', 'default' => 'on');
		$data['columns'][] = array('name' => 't_auto_scale', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'auto_scale', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 't_auto_scale_opts', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'auto_scale_opts', 'type' => 'tinyint(1)', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 't_auto_scale_log', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'auto_scale_log', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 't_scale_log_units', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'scale_log_units', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 't_auto_scale_rigid', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'auto_scale_rigid', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 't_auto_padding', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'auto_padding', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 't_base_value', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'base_value', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 't_grouping', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'grouping', 'type' => 'char(2)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 't_unit_value', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'unit_value', 'type' => 'varchar(20)', 'default' => '');
		$data['columns'][] = array('name' => 't_unit_exponent_value', 'type' => 'char(2)', 'default' => '');
		$data['columns'][] = array('name' => 'unit_exponent_value', 'type' => 'varchar(5)', 'NULL' => false, 'default' => '');
		$data['primary']   = 'aggregate_template_id';
		$data['keys'][]    = '';
		$data['type']      = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment']   = 'Aggregate Template Graph Data';
		db_table_create ('plugin_aggregate_graph_templates_graph', $data);

		/* TODO should this go in a seperate upgrade function? */
		/* Add cfed and graph_type override columns to aggregate tables */
		$columns = array();
		$columns[] = array('name' => 't_graph_type_id', 'type' => 'char(2)', 'default' => '', 'after' => 'color_template');
		$columns[] = array('name' => 'graph_type_id', 'type' => 'tinyint(3)', 'NULL' => false, 'default' => 0, 'after' => 't_graph_type_id');
		$columns[] = array('name' => 't_cdef_id', 'type' => 'char(2)', 'default' => '', 'after' => 'graph_type_id');
		$columns[] = array('name' => 'cdef_id', 'type' => 'mediumint(8)',  'unsigned' => true, 'NULL' => true, 'after' => 't_cdef_id');
		foreach(array('plugin_aggregate_graphs_graph_item', 'plugin_aggregate_graph_templates_item') as $table) {
			foreach($columns as $column) {
				db_install_add_column($table, $column);
			}
		}

		// Merging aggregate into mainline
		db_install_rename_table('plugin_aggregate_color_template_items', 'color_template_items');
		db_install_rename_table('plugin_aggregate_color_templates', 'color_templates');
		db_install_rename_table('plugin_aggregate_graph_templates', 'aggregate_graph_templates');
		db_install_rename_table('plugin_aggregate_graph_templates_graph', 'aggregate_graph_templates_graph');
		db_install_rename_table('plugin_aggregate_graph_templates_item', 'aggregate_graph_templates_item');
		db_install_rename_table('plugin_aggregate_graphs', 'aggregate_graphs');
		db_install_rename_table('plugin_aggregate_graphs_graph_item', 'aggregate_graphs_graph_item');
		db_install_rename_table('plugin_aggregate_graphs_items', 'aggregate_graphs_items');
	}

	/* automation rules */
	if (!db_table_exists('plugin_autom8_match_rule_items', false)) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'rule_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'rule_type', 'type' => 'smallint(3)',  'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'sequence', 'type' => 'smallint(3)',  'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'operation', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'field', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'operator', 'type' => 'smallint(3)',  'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'pattern', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
		$data['primary'] = 'id';
		$data['keys'][] = '';
		$data['type'] = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment'] = 'Automation Match Rule Items';
		db_table_create ('plugin_autom8_match_rule_items', $data);

		$sql[] = "INSERT IGNORE INTO `plugin_autom8_match_rule_items`
			(`id`, `rule_id`, `rule_type`, `sequence`, `operation`, `field`, `operator`, `pattern`)
			VALUES
			(1, 1, 1, 1, 0, 'h.description', 14, ''),
			(2, 1, 1, 2, 1, 'h.snmp_version', 12, '2'),
			(3, 1, 3, 1, 0, 'ht.name', 1, 'Linux'),
			(4, 2, 1, 1, 0, 'ht.name', 1, 'Linux'),
			(5, 2, 1, 2, 1, 'h.snmp_version', 12, '2'),
			(6, 2, 3, 1, 0, 'ht.name', 1, 'SNMP'),
			(7, 2, 3, 2, 1, 'gt.name', 1, 'Traffic')";
	} else {
		if (db_table_exists('plugin_autom8_match_rules', false)) {
			$sql[] = "UPDATE plugin_autom8_match_rules SET field=REPLACE(field, 'host_template.', 'ht.')";
			$sql[] = "UPDATE plugin_autom8_match_rules SET field=REPLACE(field, 'host.', 'h.')";
			$sql[] = "UPDATE plugin_autom8_match_rules SET field=REPLACE(field, 'graph_templates.', 'gt.')";
			$sql[] = "UPDATE plugin_autom8_match_rules SET field=REPLACE(field, 'graph_templates_graph.', 'gtg.')";
		}
	}

	if (!db_table_exists('plugin_autom8_graph_rules', false)) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'name', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'snmp_query_id', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'graph_type_id', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'enabled', 'type' => 'char(2)', 'NULL' => true,  'default' => '');
		$data['primary'] = 'id';
		$data['keys'][] = '';
		$data['type'] = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment'] = 'Automation Graph Rules';
		db_table_create ('plugin_autom8_graph_rules', $data);

		$sql[] = "INSERT IGNORE INTO `plugin_autom8_graph_rules`
			(`id`, `name`, `snmp_query_id`, `graph_type_id`, `enabled`)
			VALUES
			(1, 'Traffic 64 bit Server', 1, 14, ''),
			(2, 'Traffic 64 bit Server Linux', 1, 14, ''),
			(3, 'Disk Space', 8, 18, '')";
	}

	if (!db_table_exists('plugin_autom8_graph_rule_items', false)) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'rule_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'sequence', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'operation', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'field', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'operator', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'pattern', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
		$data['primary'] = 'id';
		$data['keys'][] = '';
		$data['type'] = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment'] = 'Automation Graph Rule Items';
		db_table_create ('plugin_autom8_graph_rule_items', $data);

		$sql[] = "INSERT IGNORE INTO `plugin_autom8_graph_rule_items`
			(`id`, `rule_id`, `sequence`, `operation`, `field`, `operator`, `pattern`)
			VALUES
			(1, 1, 1, 0, 'ifOperStatus', 7, 'Up'),
			(2, 1, 2, 1, 'ifIP', 16, ''),
			(3, 1, 3, 1, 'ifHwAddr', 16, ''),
			(4, 2, 1, 0, 'ifOperStatus', 7, 'Up'),
			(5, 2, 2, 1, 'ifIP', 16, ''),
			(6, 2, 3, 1, 'ifHwAddr', 16, '')";
	}

	if (!db_table_exists('plugin_autom8_tree_rules', false)) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'name', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'tree_id', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'tree_item_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'leaf_type', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'host_grouping_type', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'rra_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'enabled', 'type' => 'char(2)', 'NULL' => true,  'default' => '');
		$data['primary'] = 'id';
		$data['keys'][] = '';
		$data['type'] = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment'] = 'Automation Tree Rules';
		db_table_create ('plugin_autom8_tree_rules', $data);

		$sql[] = "INSERT IGNORE INTO `plugin_autom8_tree_rules`
			(`id`, `name`, `tree_id`, `tree_item_id`, `leaf_type`, `host_grouping_type`, `rra_id`, `enabled`)
			VALUES
			(1, 'New Device', 1, 0, 3, 1, 0, ''),
			(2, 'New Graph',  1, 0, 2, 0, 1, '')";
	}

	if (!db_table_exists('plugin_autom8_tree_rule_items', false)) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'rule_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'sequence', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'field', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'rra_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'sort_type', 'type' => 'smallint(3)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
		$data['columns'][] = array('name' => 'propagate_changes', 'type' => 'char(2)', 'NULL' => true, 'default' => '');
		$data['columns'][] = array('name' => 'search_pattern', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'replace_pattern',	'type' => 'varchar(255)',                       	    'NULL' => false, 	'default' => '');
		$data['primary'] = 'id';
		$data['keys'][] = '';
		$data['type'] = $engine;
		$data['row_format'] = 'Dynamic';
		$data['comment'] = 'Automation Tree Rule Items';
		db_table_create ('plugin_autom8_tree_rule_items', $data);

		$sql[] = "INSERT INTO `plugin_autom8_tree_rule_items`
			(`id`, `rule_id`, `sequence`, `field`, `rra_id`, `sort_type`, `propagate_changes`, `search_pattern`, `replace_pattern`)
			VALUES
			(1, 1, 1, 'ht.name', 0, 1, '', '^(.*)\\\\s*Linux\\\\s*(.*)$', '$\{1\}\\\\n$\{2\}'),
			(2, 1, 2, 'h.hostname', 0, 1, '', '^(\\\\w*)\\\\s*(\\\\w*)\\\\s*(\\\\w*).*$', '$\{1\}\\\\n$\{2\}\\\\n$\{3\}'),
			(3, 2, 1, '0', 0, 2, 'on', 'Traffic', ''),
			(4, 2, 2, 'gtg.title_cache', 0, 1, '', '^(.*)\\\\s*-\\\\s*Traffic -\\\\s*(.*)$', '$\{1\}\\\\n$\{2\}');";
	} else {
		$sql[] = "UPDATE plugin_autom8_tree_rule_items SET field = REPLACE(field, 'host_template.', 'ht.')";
		$sql[] = "UPDATE plugin_autom8_tree_rule_items SET field = REPLACE(field, 'host.', 'h.')";
		$sql[] = "UPDATE plugin_autom8_tree_rule_items SET field = REPLACE(field, 'graph_templates.', 'gt.')";
		$sql[] = "UPDATE plugin_autom8_tree_rule_items SET field = REPLACE(field, 'graph_templates_graph.', 'gtg.')";
	}

	# now run all SQL commands
	if (cacti_sizeof($sql)) {
		foreach($sql as $query) {
			$result = db_install_execute($query);
		}
		$sql = array();
	}

	/* autom8 table renames */
	$autom8_tables = array(
		'plugin_autom8_graph_rules',
		'plugin_autom8_graph_rule_items',
		'plugin_autom8_match_rule_items',
		'plugin_autom8_tree_rules',
		'plugin_autom8_tree_rule_items'
	);

	foreach($autom8_tables as $table) {
		$new_table = str_replace('plugin_autom8', 'automation', $table);

		if (db_table_exists($table)) {
			db_install_execute("DROP TABLE IF EXISTS $new_table");
			db_install_rename_table($table, str_replace('plugin_autom8', 'automation', $table));
		}
	}

	db_install_execute("UPDATE IGNORE settings SET name = REPLACE(name, 'autom8', 'automation') WHERE name LIKE 'autom8%'");

	// migrate discovery to Core if exists
	if (db_table_exists('plugin_discover_hosts', false)) {
		db_install_rename_table('plugin_discover_hosts', 'automation_devices');
		db_install_drop_column('automation_devices', 'hash');

		db_install_execute("ALTER TABLE automation_devices
			ADD COLUMN id BIGINT unsigned auto_increment FIRST,
			ADD COLUMN network_id INT unsigned NOT NULL default '0' AFTER id,
			ADD COLUMN snmp_port int(10) unsigned NOT NULL DEFAULT '161' AFTER snmp_version,
			ADD COLUMN snmp_engine_id varchar(30) DEFAULT '' AFTER snmp_context,
			DROP PRIMARY KEY,
			ADD PRIMARY KEY(id),
			ADD UNIQUE INDEX ip(ip);
			ADD INDEX network_id(network_id),
			COMMENT='Table of Discovered Devices'");

		if (db_table_exists('plugin_discover_processes', false)) {
			db_install_drop_table('plugin_discover_processes');
		}

		if (db_table_exists('plugin_discover_template', false)) {
			db_install_rename_table('plugin_discover_template', 'automation_templates');
			db_install_execute("ALTER TABLE automation_templates
				CHANGE COLUMN sysdescr sysDescr VARCHAR(255) DEFAULT ''");
			db_install_add_column('automation_templates', array('name' => 'availability_method', 'type' => 'int(10)', 'default' => 2, 'after' => 'host_template'));
			db_install_add_column('automation_templates', array('name' => 'sysName', 'type' => 'VARCHAR(255)', 'default' => '', 'after' => 'sysdescr'));
			db_install_add_column('automation_templates', array('name' => 'sysOid', 'type' => 'VARCHAR(60)', 'default' => '', 'after' => 'sysname'));
			db_install_add_column('automation_templates', array('name' => 'sequence', 'type' => 'INT UNSIGNED', 'default' => 0, 'after' => 'sysoid'));
			db_install_drop_column('automation_templates', 'tree');
			db_install_drop_column('automation_templates', 'snmp_version');
			db_install_execute("UPDATE automation_templates SET sequence=id");
		}
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `automation_devices` (
		`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		`network_id` int(10) unsigned NOT NULL DEFAULT '0',
		`hostname` varchar(100) NOT NULL DEFAULT '',
		`ip` varchar(17) NOT NULL DEFAULT '',
		`community` varchar(100) NOT NULL DEFAULT '',
		`snmp_version` tinyint(1) unsigned NOT NULL DEFAULT '1',
		`snmp_port` int(10) unsigned NOT NULL DEFAULT '161',
		`snmp_username` varchar(50) DEFAULT NULL,
		`snmp_password` varchar(50) DEFAULT NULL,
		`snmp_auth_protocol` char(5) DEFAULT '',
		`snmp_priv_passphrase` varchar(200) DEFAULT '',
		`snmp_priv_protocol` char(6) DEFAULT '',
		`snmp_context` varchar(64) DEFAULT '',
		`snmp_engine_id` varchar(30) DEFAULT '',
		`sysName` varchar(100) NOT NULL DEFAULT '',
		`sysLocation` varchar(255) NOT NULL DEFAULT '',
		`sysContact` varchar(255) NOT NULL DEFAULT '',
		`sysDescr` varchar(255) NOT NULL DEFAULT '',
		`sysUptime` int(32) NOT NULL DEFAULT '0',
		`os` varchar(64) NOT NULL DEFAULT '',
		`snmp` tinyint(4) NOT NULL DEFAULT '0',
		`known` tinyint(4) NOT NULL DEFAULT '0',
		`up` tinyint(4) NOT NULL DEFAULT '0',
		`time` int(11) NOT NULL DEFAULT '0',
		PRIMARY KEY (`id`),
		UNIQUE KEY `ip` (`ip`),
		KEY `hostname` (`hostname`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Table of Discovered Devices'");

	db_install_execute("CREATE TABLE IF NOT EXISTS `automation_ips` (
		`ip_address` varchar(20) NOT NULL DEFAULT '',
		`hostname` varchar(250) DEFAULT NULL,
		`network_id` int(10) unsigned DEFAULT NULL,
		`pid` int(10) unsigned DEFAULT NULL,
		`status` int(10) unsigned DEFAULT NULL,
		`thread` int(10) unsigned DEFAULT NULL,
		PRIMARY KEY (`ip_address`),
		KEY `pid` (`pid`))
		ENGINE=MEMORY
		COMMENT='List of discoverable ip addresses used for scanning'");

	db_install_execute("CREATE TABLE IF NOT EXISTS `automation_networks` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`poller_id` int(10) unsigned DEFAULT '0',
		`name` varchar(128) NOT NULL DEFAULT '' COMMENT 'The name for this network',
		`subnet_range` varchar(255) NOT NULL DEFAULT '' COMMENT 'Defined subnet ranges for discovery',
		`dns_servers` varchar(128) NOT NULL DEFAULT '' COMMENT 'DNS Servers to use for name resolution',
		`enabled` char(2) DEFAULT '',
		`snmp_id` int(10) unsigned DEFAULT NULL,
		`enable_netbios` char(2) DEFAULT '',
		`add_to_cacti` char(2) DEFAULT '',
		`total_ips` int(10) unsigned DEFAULT '0',
		`up_hosts` int(10) unsigned NOT NULL DEFAULT '0',
		`snmp_hosts` int(10) unsigned NOT NULL DEFAULT '0',
		`ping_method` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'The ping method (ICMP:TCP:UDP)',
		`ping_port` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'For TCP:UDP the port to ping',
		`ping_timeout` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'The ping timeout in seconds',
		`ping_retries` int(10) unsigned DEFAULT '0',
		`sched_type` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Schedule type: manual or automatic',
		`threads` int(10) unsigned DEFAULT '1',
		`run_limit` int(10) unsigned NULL DEFAULT '0' COMMENT 'The maximum runtime for the discovery',
		`start_at` varchar(20) DEFAULT NULL,
		`next_start` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		`recur_every` int(10) unsigned DEFAULT '1',
		`day_of_week` varchar(45) DEFAULT NULL COMMENT 'The days of week to run in crontab format',
		`month` varchar(45) DEFAULT NULL COMMENT 'The months to run in crontab format',
		`day_of_month` varchar(45) DEFAULT NULL COMMENT 'The days of month to run in crontab format',
		`monthly_week` varchar(45) DEFAULT NULL,
		`monthly_day` varchar(45) DEFAULT NULL,
		`last_runtime` double NOT NULL DEFAULT '0' COMMENT 'The last runtime for discovery',
		`last_started` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'The time the discovery last started',
		`last_status` varchar(128) NOT NULL DEFAULT '' COMMENT 'The last exit message if any',
		`rerun_data_queries` char(2) DEFAULT NULL COMMENT 'Rerun data queries or not for existing hosts',
		PRIMARY KEY (`id`),
		KEY `poller_id` (`poller_id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Stores scanning subnet definitions'");

	db_install_execute("CREATE TABLE IF NOT EXISTS `automation_processes` (
		`pid` int(8) unsigned NOT NULL,
		`poller_id` int(10) unsigned DEFAULT '0',
		`network_id` int(10) unsigned NOT NULL DEFAULT '0',
		`task` varchar(20) NULL DEFAULT '',
		`status` varchar(20) DEFAULT NULL,
		`command` varchar(20) DEFAULT NULL,
		`up_hosts` int(10) unsigned DEFAULT '0',
		`snmp_hosts` int(10) unsigned DEFAULT '0',
		`heartbeat` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY (`pid`, `network_id`))
		ENGINE=MEMORY
		COMMENT='Table tracking active poller processes'");

	db_install_execute("CREATE TABLE IF NOT EXISTS `automation_snmp` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`name` varchar(100) NOT NULL DEFAULT '',
		PRIMARY KEY (`id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Group of SNMP Option Sets'");

	db_install_execute("CREATE TABLE IF NOT EXISTS `automation_snmp_items` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`snmp_id` int(10) unsigned NOT NULL DEFAULT '0',
		`sequence` int(10) unsigned NOT NULL DEFAULT '0',
		`snmp_version` varchar(100) NOT NULL DEFAULT '',
		`snmp_readstring` varchar(100) NOT NULL,
		`snmp_port` int(10) NOT NULL DEFAULT '161',
		`snmp_timeout` int(10) unsigned NOT NULL DEFAULT '500',
		`snmp_retries` tinyint(11) unsigned NOT NULL DEFAULT '3',
		`max_oids` int(12) unsigned DEFAULT '10',
		`snmp_username` varchar(50) DEFAULT NULL,
		`snmp_password` varchar(50) DEFAULT NULL,
		`snmp_auth_protocol` char(5) DEFAULT '',
		`snmp_priv_passphrase` varchar(200) DEFAULT '',
		`snmp_priv_protocol` char(6) DEFAULT '',
		`snmp_context` varchar(64) DEFAULT '',
		PRIMARY KEY (`id`,`snmp_id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Set of SNMP Options'");

	$snmp_count = db_fetch_cell('SELECT COUNT(*) FROM automation_snmp');

	if (empty($snmp_count)) {
		db_install_execute("INSERT IGNORE INTO `automation_snmp`
			VALUES (1,'Default Option Set')");

		db_install_execute("INSERT IGNORE INTO `automation_snmp_items`
			VALUES (1,1,1,2,'public',161,1000,3,10,'','','MD5','','DES','')");

		db_install_execute("INSERT IGNORE INTO `automation_snmp_items`
			VALUES (2,1,2,2,'private',161,1000,3,10,'','','MD5','','DES','');");
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `automation_templates` (
		`id` int(8) NOT NULL AUTO_INCREMENT,
		`host_template` int(8) NOT NULL DEFAULT '0',
		`availability_method` int(10) unsigned DEFAULT '2',
		`sysDescr` varchar(255) NULL DEFAULT '',
		`sysName` varchar(255) NULL DEFAULT '',
		`sysOid` varchar(60) NULL DEFAULT '',
		`sequence` int(10) unsigned DEFAULT '0',
		PRIMARY KEY (`id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Templates of SNMP Sys variables used for automation'");

	db_install_execute("UPDATE automation_match_rule_items SET field = REPLACE(field, 'host_template.', 'ht.')");
	db_install_execute("UPDATE automation_match_rule_items SET field = REPLACE(field, 'host.', 'h.')");
	db_install_execute("UPDATE automation_match_rule_items SET field = REPLACE(field, 'graph_templates.', 'gt.')");
	db_install_execute("UPDATE automation_match_rule_items SET field = REPLACE(field, 'graph_templates_graph.', 'gtg.')");

	/* stamp out duplicate colors */
	$duplicates_results = db_install_fetch_assoc('SELECT hex, COUNT(*) AS totals
		FROM colors
		GROUP BY hex
		HAVING totals > 1');
	$duplicates = $duplicates_results['data'];

	if (cacti_sizeof($duplicates)) {
		foreach($duplicates as $duplicate) {
			$hexes_results = db_install_fetch_assoc('SELECT id, hex
				FROM colors
				WHERE hex = ?
				ORDER BY id ASC',
				array($duplicate['hex']), true);
			$hexes = $hexes_results['data'];

			$first = true;

			foreach($hexes as $hex) {
				if ($first) {
					$keephex = $hex['id'];
					$first   = false;
				} else {
					db_install_execute('UPDATE graph_templates_item
						SET color_id = ?
						WHERE color_id = ?',
						array($keephex, $hex['id']));

					if (db_table_exists('color_template_items')) {
						db_install_execute('UPDATE color_template_items
							SET color_id = ?
							WHERE color_id = ?',
							array($keephex, $hex['id']));
					}

					db_install_execute('DELETE FROM colors WHERE id = ?', array($hex['id']));
				}
			}
		}
	}

	db_install_add_key('colors', 'UNIQUE INDEX', 'hex', array('hex'));
	db_install_add_column('colors', array('name' => 'name', 'type' => 'varchar(40)', 'default' => '', 'after' => 'id'));
	db_install_add_column('colors', array('name' => 'read_only', 'type' => 'char(2)', 'default' => '', 'after' => 'hex'));

	// import remaining colors into database
	import_colors();

	db_install_execute("ALTER TABLE settings MODIFY COLUMN value varchar(2048) NOT NULL default ''");
	if (db_table_exists('settings_graphs', false)) {
		db_install_execute("ALTER TABLE settings_graphs MODIFY COLUMN value varchar(2048) NOT NULL default ''");
	}
	db_install_execute("ALTER TABLE user_auth MODIFY COLUMN password varchar(2048) NOT NULL default ''");

	db_install_rename_table('settings_graphs', 'settings_user');

	db_install_add_column('user_auth', array('name' => 'reset_perms', 'type' => 'INT(12) unsigned', 'default' => '0', 'after' => 'lastfail'));

	rsa_check_keypair();

	db_install_add_column('graph_templates_item', array('name' => 'vdef_id', 'type' => 'mediumint(8) unsigned', 'NULL' => false, 'default' => 0, 'after' => 'cdef_id'));
	db_install_add_column('graph_templates_item', array('name' => 'line_width', 'type' => 'DECIMAL(4,2)', 'default' => 0, 'after' => 'graph_type_id'));
	db_install_add_column('graph_templates_item', array('name' => 'dashes', 'type' => 'varchar(20)', 'NULL' => true, 'after' => 'line_width'));
	db_install_add_column('graph_templates_item', array('name' => 'dash_offset', 'type' => 'mediumint(4)', 'NULL' => true, 'after' => 'dashes'));
	db_install_add_column('graph_templates_item', array('name' => 'shift', 'type' => 'char(2)', 'NULL' => true, 'after' => 'vdef_id'));
	db_install_add_column('graph_templates_item', array('name' => 'textalign', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 'consolidation_function_id'));

	db_install_add_column('graph_templates_graph', array('name' => 't_alt_y_grid', 'type' => 'char(2)',  'default' => '', 'after' => 'unit_exponent_value'));
	db_install_add_column('graph_templates_graph', array('name' => 'alt_y_grid', 'type' => 'char(2)', 'NULL' => true, 'after' => 't_alt_y_grid'));
	db_install_add_column('graph_templates_graph', array('name' => 't_right_axis', 'type' => 'char(2)',  'default' => '', 'after' => 'alt_y_grid'));
	db_install_add_column('graph_templates_graph', array('name' => 'right_axis', 'type' => 'varchar(20)', 'NULL' => true, 'after' => 't_right_axis'));
	db_install_add_column('graph_templates_graph', array('name' => 't_right_axis_label', 'type' => 'char(2)',  'default' => '', 'after' => 'right_axis'));
	db_install_add_column('graph_templates_graph', array('name' => 'right_axis_label', 'type' => 'varchar(200)', 'NULL' => true, 'after' => 't_right_axis_label'));
	db_install_add_column('graph_templates_graph', array('name' => 't_right_axis_format', 'type' => 'char(2)',  'default' => '', 'after' => 'right_axis_label'));
	db_install_add_column('graph_templates_graph', array('name' => 'right_axis_format', 'type' => 'mediumint(8)', 'NULL' => true, 'after' => 't_right_axis_format'));
	db_install_add_column('graph_templates_graph', array('name' => 't_right_axis_formatter', 'type' => 'char(2)',  'default' => '', 'after' => 'right_axis_format'));
	db_install_add_column('graph_templates_graph', array('name' => 'right_axis_formatter', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 't_right_axis_formatter'));
	db_install_add_column('graph_templates_graph', array('name' => 't_left_axis_formatter', 'type' => 'char(2)',  'default' => '', 'after' => 'right_axis_formatter'));
	db_install_add_column('graph_templates_graph', array('name' => 'left_axis_formatter', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 't_left_axis_formatter'));
	db_install_add_column('graph_templates_graph', array('name' => 't_no_gridfit', 'type' => 'char(2)',  'default' => '', 'after' => 'left_axis_formatter'));
	db_install_add_column('graph_templates_graph', array('name' => 'no_gridfit', 'type' => 'char(2)', 'NULL' => true, 'after' => 't_no_gridfit'));
	db_install_add_column('graph_templates_graph', array('name' => 't_unit_length', 'type' => 'char(2)',  'default' => '', 'after' => 'no_gridfit'));
	db_install_add_column('graph_templates_graph', array('name' => 'unit_length', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 't_unit_length'));
	db_install_add_column('graph_templates_graph', array('name' => 't_tab_width', 'type' => 'char(2)',  'default' => '', 'after' => 'unit_length'));
	db_install_add_column('graph_templates_graph', array('name' => 'tab_width', 'type' => 'varchar(20)', 'default' => '30', 'NULL' => true, 'after' => 't_tab_width'));
	db_install_add_column('graph_templates_graph', array('name' => 't_dynamic_labels', 'type' => 'char(2)',  'default' => '', 'after' => 'tab_width'));
	db_install_add_column('graph_templates_graph', array('name' => 'dynamic_labels', 'type' => 'char(2)', 'NULL' => true, 'after' => 't_dynamic_labels'));
	db_install_add_column('graph_templates_graph', array('name' => 't_force_rules_legend', 'type' => 'char(2)',  'default' => '', 'after' => 'dynamic_labels'));
	db_install_add_column('graph_templates_graph', array('name' => 'force_rules_legend', 'type' => 'char(2)', 'NULL' => true, 'after' => 't_force_rules_legend'));
	db_install_add_column('graph_templates_graph', array('name' => 't_legend_position', 'type' => 'char(2)',  'default' => '', 'after' => 'force_rules_legend'));
	db_install_add_column('graph_templates_graph', array('name' => 'legend_position', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 't_legend_position'));
	db_install_add_column('graph_templates_graph', array('name' => 't_legend_direction', 'type' => 'char(2)',  'default' => '', 'after' => 'legend_position'));
	db_install_add_column('graph_templates_graph', array('name' => 'legend_direction', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 't_legend_direction'));

	/* create new table sessions */
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'varchar(32)', 'NULL' => false );
	$data['columns'][] = array('name' => 'remote_addr', 'type' => 'varchar(25)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'access', 'type' => 'INT(10)', 'unsigned' => 'unsigned', 'NULL' => true);
	$data['columns'][] = array('name' => 'data', 'type' => 'text',  'NULL' => true);
	$data['primary']   = 'id';
	$data['comment']   = 'Used for Database based Session Storage';
	$data['type'] = $engine;
	$data['row_format'] = 'Dynamic';
	db_table_create('sessions', $data);

	/* create new table VDEF */
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)',    'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'hash', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['primary']   = 'id';
	$data['comment']   = 'vdef';
	$data['type'] = $engine;
	$data['row_format'] = 'Dynamic';
	db_table_create('vdef', $data);

	/* create new table VDEF_ITEMS */
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'mediumint(8)',    'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'hash', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vdef_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'sequence', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'type', 'type' => 'tinyint(2)', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'value', 'type' => 'varchar(150)', 'NULL' => false, 'default' => '');
	$data['primary']   = 'id';
	$data['keys'][] = array('name' => 'vdef_id', 'columns' => 'vdef_id');
	$data['comment']   = 'vdef items';
	$data['type'] = $engine;
	$data['row_format'] = 'Dynamic';
	db_table_create('vdef_items', $data);

	/* fill table VDEF */
	db_install_execute("REPLACE INTO `vdef` VALUES (1, 'e06ed529238448773038601afb3cf278', 'Maximum');");
	db_install_execute("REPLACE INTO `vdef` VALUES (2, 'e4872dda82092393d6459c831a50dc3b', 'Minimum');");
	db_install_execute("REPLACE INTO `vdef` VALUES (3, '5ce1061a46bb62f36840c80412d2e629', 'Average');");
	db_install_execute("REPLACE INTO `vdef` VALUES (4, '06bd3cbe802da6a0745ea5ba93af554a', 'Last (Current)');");
	db_install_execute("REPLACE INTO `vdef` VALUES (5, '631c1b9086f3979d6dcf5c7a6946f104', 'First');");
	db_install_execute("REPLACE INTO `vdef` VALUES (6, '6b5335843630b66f858ce6b7c61fc493', 'Total: Current Data Source');");
	db_install_execute("REPLACE INTO `vdef` VALUES (7, 'c80d12b0f030af3574da68b28826cd39', '95th Percentage: Current Data Source');");

	/* fill table VDEF */
	db_install_execute("REPLACE INTO `vdef_items` VALUES (1, '88d33bf9271ac2bdf490cf1784a342c1', 1, 1, 4, 'CURRENT_DATA_SOURCE');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (2, 'a307afab0c9b1779580039e3f7c4f6e5', 1, 2, 1, '1');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (3, '0945a96068bb57c80bfbd726cf1afa02', 2, 1, 4, 'CURRENT_DATA_SOURCE');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (4, '95a8df2eac60a89e8a8ca3ea3d019c44', 2, 2, 1, '2');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (5, 'cc2e1c47ec0b4f02eb13708cf6dac585', 3, 1, 4, 'CURRENT_DATA_SOURCE');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (6, 'a2fd796335b87d9ba54af6a855689507', 3, 2, 1, '3');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (7, 'a1d7974ee6018083a2053e0d0f7cb901', 4, 1, 4, 'CURRENT_DATA_SOURCE');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (8, '26fccba1c215439616bc1b83637ae7f3', 4, 2, 1, '5');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (9, 'a8993b265f4c5398f4a47c44b5b37a07', 5, 1, 4, 'CURRENT_DATA_SOURCE');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (10, '5a380d469d611719057c3695ce1e4eee', 5, 2, 1, '6');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (11, '65cfe546b17175fad41fcca98c057feb', 6, 1, 4, 'CURRENT_DATA_SOURCE');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (12, 'f330b5633c3517d7c62762cef091cc9e', 6, 2, 1, '7');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (13, 'f1bf2ecf54ca0565cf39c9c3f7e5394b', 7, 1, 4, 'CURRENT_DATA_SOURCE');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (14, '11a26f18feba3919be3af426670cba95', 7, 2, 6, '95');");
	db_install_execute("REPLACE INTO `vdef_items` VALUES (15, 'e7ae90275bc1efada07c19ca3472d9db', 7, 3, 1, '8');");

	db_install_add_column('data_template_data', array('name' => 't_data_source_profile_id', 'type' => 'CHAR(2)',  'default' => ''));
	db_install_add_column('data_template_data', array('name' => 'data_source_profile_id', 'type' => 'mediumint(8) unsigned', 'NULL' => false, 'default' => '0'));

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_profiles` (
		`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
		`hash` varchar(32) NOT NULL DEFAULT '',
		`name` varchar(255) NOT NULL DEFAULT '',
		`step` int(10) unsigned NOT NULL DEFAULT '300',
		`heartbeat` int(10) unsigned NOT NULL DEFAULT '600',
		`x_files_factor` double DEFAULT '0.5',
		`default` char(2) DEFAULT '',
		PRIMARY KEY (`id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Stores Data Source Profiles'");


	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_profiles_cf` (
		`data_source_profile_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
		`consolidation_function_id` smallint(5) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`data_source_profile_id`,`consolidation_function_id`),
		KEY `data_source_profile_id` (`data_source_profile_id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Maps the Data Source Profile Consolidation Functions'");

	db_install_execute("CREATE TABLE IF NOT EXISTS `data_source_profiles_rra` (
		`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
		`data_source_profile_id` mediumint(8) unsigned not null default '0',
		`name` varchar(255) NOT NULL DEFAULT '',
		`steps` int(10) unsigned DEFAULT '1',
		`rows` int(10) unsigned NOT NULL DEFAULT '700',
		PRIMARY KEY (`id`),
		KEY `data_source_profile_id` (`data_source_profile_id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Stores RRA Definitions for Data Source Profiles'");

	/* get the current data source profiles */
	if (db_table_exists('data_template_data_rra', false)) {
		$profiles_results = db_install_fetch_assoc("SELECT pattern, rrd_step, rrd_heartbeat, x_files_factor
			FROM (
				SELECT data_template_data_id, GROUP_CONCAT(rra_id) AS pattern
				FROM data_template_data_rra
				GROUP BY data_template_data_id
			) AS dtdr
			INNER JOIN data_template_data AS dtd
			ON dtd.id=dtdr.data_template_data_id
			INNER JOIN data_template_rrd AS dtr
			ON dtd.id=dtr.local_data_template_rrd_id
			INNER JOIN rra AS r
			ON r.id IN(pattern)
			GROUP BY pattern, rrd_step, rrd_heartbeat, x_files_factor");
		$profiles = $profiles_results['data'];

		$i = 1;
		if (cacti_sizeof($profiles)) {
			foreach($profiles as $profile) {
				$pattern = $profile['pattern'];

				$save = array();
				$save['id'] = 0;
				$save['name']           = 'Upgrade Profile ' . $i;
				$save['hash']           = get_hash_data_source_profile($save['name']);
				$save['step']           = $profile['rrd_step'];
				$save['heartbeat']      = $profile['rrd_heartbeat'];
				$save['x_files_factor'] = $profile['x_files_factor'];

				$id = sql_save($save, 'data_source_profiles');

				$rras = explode(',', $pattern);

				foreach($rras as $r) {
					db_install_execute("INSERT INTO data_source_profiles_rra
						(`data_source_profile_id`, `name`, `steps`, `rows`)
						SELECT '$id' AS `data_source_profile_id`, `name`, `steps`, `rows` FROM `rra` WHERE `id`=" . $r);

					db_install_execute("REPLACE INTO data_source_profiles_cf
						(data_source_profile_id, consolidation_function_id)
						SELECT '$id' AS data_source_profile_id, consolidation_function_id FROM rra_cf WHERE rra_id=" . $r);
				}

				db_install_execute("UPDATE data_template_data
					SET data_source_profile_id=$id
					WHERE data_template_data.id IN(
						SELECT data_template_data_id
						FROM (
							SELECT data_template_data_id, GROUP_CONCAT(rra_id) AS pattern
							FROM data_template_data_rra
							GROUP BY data_template_data_id
							HAVING pattern='" . $pattern . "'
						) AS rs);");
			}

			$i++;
		}
	}

	db_install_drop_table('rra');
	db_install_drop_table('rra_cf');
	db_install_drop_table('data_template_data_rra');
	db_install_drop_column('data_template_data', 't_rra_id');
	db_install_drop_column('automation_tree_rule_items', 'rra_id');
	db_install_drop_column('automation_tree_rules', 'rra_id');
	db_install_drop_column('graph_tree_items', 'rra_id');

	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_alt_y_grid', 'type' => 'char(2)',  'default' => '0', 'after' => 'unit_exponent_value'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'alt_y_grid', 'type' => 'char(2)', 'NULL' => true, 'after' => 't_alt_y_grid'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_right_axis', 'type' => 'char(2)',  'default' => '0', 'after' => 'alt_y_grid'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'right_axis', 'type' => 'varchar(20)', 'NULL' => true, 'after' => 't_right_axis'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_right_axis_label', 'type' => 'char(2)',  'default' => '0', 'after' => 'right_axis'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'right_axis_label', 'type' => 'varchar(200)', 'NULL' => true, 'after' => 't_right_axis_label'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_right_axis_format', 'type' => 'char(2)',  'default' => '0', 'after' => 'right_axis_label'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'right_axis_format', 'type' => 'mediumint(8)', 'NULL' => true, 'after' => 't_right_axis_format'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_right_axis_formatter', 'type' => 'char(2)',  'default' => '0', 'after' => 'right_axis_format'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'right_axis_formatter', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 't_right_axis_formatter'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_left_axis_formatter', 'type' => 'char(2)',  'default' => '0', 'after' => 'right_axis_formatter'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'left_axis_formatter', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 't_left_axis_formatter'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_no_gridfit', 'type' => 'char(2)',  'default' => '0', 'after' => 'left_axis_formatter'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'no_gridfit', 'type' => 'char(2)', 'NULL' => true, 'after' => 't_no_gridfit'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_unit_length', 'type' => 'char(2)',  'default' => '0', 'after' => 'no_gridfit'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'unit_length', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 't_unit_length'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_tab_width', 'type' => 'char(2)',  'default' => '30', 'after' => 'unit_length'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'tab_width', 'type' => 'varchar(20)', 'NULL' => true, 'after' => 't_tab_width'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_dynamic_labels', 'type' => 'char(2)',  'default' => '0', 'after' => 'tab_width'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'dynamic_labels', 'type' => 'char(2)', 'NULL' => true, 'after' => 't_dynamic_labels'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_force_rules_legend', 'type' => 'char(2)',  'default' => '0', 'after' => 'dynamic_labels'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'force_rules_legend', 'type' => 'char(2)', 'NULL' => true, 'after' => 't_force_rules_legend'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_legend_position', 'type' => 'char(2)',  'default' => '0', 'after' => 'force_rules_legend'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'legend_position', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 't_legend_position'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 't_legend_direction', 'type' => 'char(2)',  'default' => '0', 'after' => 'legend_position'));
	db_install_add_column('aggregate_graph_templates_graph', array('name' => 'legend_direction', 'type' => 'varchar(10)', 'NULL' => true, 'after' => 't_legend_direction'));

	// Update Aggregate CDEFs to become system level
	db_install_add_column('cdef', array('name' => 'system', 'type' => 'mediumint(8) unsigned', 'NULL' => false, 'default' => '0', 'after' => 'hash'));
	db_install_execute("UPDATE cdef SET `system` = 1 WHERE name LIKE '\_%'");

	// Add some important missing indexes
	db_install_add_key('data_local', 'INDEX', 'data_template_id', array('data_template_id'));
	db_install_add_key('data_local', 'INDEX', 'snmp_query_id', array('snmp_query_id'));

	db_install_execute("ALTER TABLE poller
		MODIFY COLUMN last_update TIMESTAMP NOT NULL default '0000-00-00'");

	db_install_execute("CREATE TABLE IF NOT EXISTS poller_resource_cache (
		id int(10) unsigned NOT NULL AUTO_INCREMENT,
		resource_type varchar(20) DEFAULT NULL,
		md5sum varchar(32) DEFAULT NULL,
		path varchar(191) DEFAULT NULL,
		update_time timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		contents longblob,
		PRIMARY KEY (id),
		UNIQUE KEY path (path))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Caches all scripts, resources files, and plugins'");

	db_install_add_column('host', array('name' => 'poller_id', 'type' => 'INT UNSIGNED', 'default' => '0', 'after' => 'id'));
	db_install_add_key('host', 'INDEX', 'poller_id', array('poller_id'));

	if (db_table_exists('plugin_spikekill_templates', false)) {
		$templates_results = db_install_fetch_assoc('SELECT graph_template_id FROM plugin_spikekill_templates',array(), false);
		$templates = implode(',', array_rekey($templates_results, 'graph_template_id', 'graph_template_id'));
		db_install_execute("REPLACE INTO settings (name, value) VALUES ('spikekill_templates','$templates')");
	}

	// Migrate superlinks pages to new external links table
	if (db_table_exists('superlinks_auth', false)) {
		db_install_rename_table('superlinks_pages', 'external_links');

		db_install_drop_column('external_links', 'imagecache');

		db_install_add_column('external_links', array('name' => 'enabled', 'type' => 'CHAR(2)', 'default' => 'on', 'after' => 'disabled'));
		if (db_column_exists('external_links', 'disabled')) {
			db_install_execute('UPDATE external_links SET enabled="on" WHERE disabled=""');
			db_install_execute('UPDATE external_links SET enabled="" WHERE disabled="on"');
			db_install_drop_column('external_links', 'disabled');
		}

		db_install_execute('DELETE FROM external_links WHERE style NOT IN ("TAB", "CONSOLE", "FRONT", "FRONTTOP")');

		db_install_execute('ALTER TABLE external_links
			MODIFY COLUMN contentfile VARCHAR(255) NOT NULL default "",
			MODIFY COLUMN title VARCHAR(20) NOT NULL default "",
			MODIFY COLUMN style VARCHAR(10) NOT NULL default ""');

		db_install_drop_table('superlinks_auth');
	} else {
		db_install_execute("CREATE TABLE IF NOT EXISTS `external_links` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`sortorder` int(11) NOT NULL DEFAULT '0',
			`enabled` char(2) DEFAULT 'on',
			`contentfile` varchar(255) NOT NULL DEFAULT '',
			`title` varchar(20) NOT NULL DEFAULT '',
			`style` varchar(10) NOT NULL DEFAULT '',
			`extendedstyle` varchar(50) NOT NULL DEFAULT '',
			PRIMARY KEY (`id`))
			ENGINE=$engine
			ROW_FORMAT=Dynamic
			COMMENT='Stores External Link Information'");
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `sites` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`name` varchar(100) NOT NULL DEFAULT '',
		`address1` varchar(100) DEFAULT '',
		`address2` varchar(100) DEFAULT '',
		`city` varchar(50) DEFAULT '',
		`state` varchar(20) DEFAULT NULL,
		`postal_code` varchar(20) DEFAULT '',
		`country` varchar(30) DEFAULT '',
		`timezone` varchar(40) DEFAULT '',
		`latitude` decimal(13,10) NOT NULL DEFAULT '0.0000000000',
		`longitude` decimal(13,10) NOT NULL DEFAULT '0.0000000000',
		`alternate_id` varchar(30) DEFAULT '',
		`notes` varchar(1024),
		PRIMARY KEY (`id`),
		KEY `name` (`name`),
		KEY `city` (`city`),
		KEY `state` (`state`),
		KEY `postal_code` (`postal_code`),
		KEY `country` (`country`),
		KEY `alternate_id` (`alternate_id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Contains information about customer sites';");

	db_install_add_column('host', array('name' => 'site_id', 'type' => 'INT UNSIGNED', 'NULL' => false, 'default' => '0', 'after' => 'poller_id'));
	db_install_execute("ALTER TABLE host MODIFY COLUMN poller_id mediumint(8) unsigned default '1'");
	db_install_add_key('host', 'INDEX', 'site_id', array('site_id'));

	db_install_drop_table('poller');
	db_install_execute("CREATE TABLE `poller` (
		`id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
		`disabled` char(2) DEFAULT '',
		`name` varchar(30) DEFAULT NULL,
		`notes` varchar(1024) DEFAULT '',
		`status` int(10) unsigned NOT NULL DEFAULT '0',
		`hostname` varchar(250) NOT NULL DEFAULT '',
		`dbdefault` varchar(20) NOT NULL DEFAULT 'cacti',
		`dbhost` varchar(64) NOT NULL DEFAULT '',
		`dbuser` varchar(20) NOT NULL DEFAULT '',
		`dbpass` varchar(64) NOT NULL DEFAULT '',
		`dbport` int(10) unsigned DEFAULT '3306',
		`dbssl` char(3) DEFAULT '',
		`total_time` double DEFAULT '0',
		`snmp` mediumint(8) unsigned DEFAULT '0',
		`script` mediumint(8) unsigned DEFAULT '0',
		`server` mediumint(8) unsigned DEFAULT '0',
		`last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		`last_status` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY (`id`))
		ENGINE=$engine
		ROW_FORMAT=Dynamic
		COMMENT='Pollers for Cacti'");

	db_install_execute('INSERT INTO poller (id, name, hostname) VALUES (1, "Main Poller", "' . php_uname('n') . '")');
	db_install_execute('UPDATE automation_networks SET poller_id=1 WHERE poller_id=0');
	db_install_execute('UPDATE automation_processes SET poller_id=1 WHERE poller_id=0');
	db_install_execute('UPDATE host SET poller_id=1 WHERE poller_id=0');
	db_install_execute('UPDATE poller_command SET poller_id=1 WHERE poller_id=0');
	db_install_execute('UPDATE poller_item SET poller_id=1 WHERE poller_id=0');
	db_install_execute('UPDATE poller_output_realtime SET poller_id=1 WHERE poller_id=0');
	db_install_execute('UPDATE poller_time SET poller_id=1 WHERE poller_id=0');

	db_install_execute('ALTER TABLE sessions MODIFY COLUMN data MEDIUMBLOB');

	db_install_add_column('host', array('name' => 'snmp_engine_id', 'type' => 'varchar(30)', 'default' => '', 'after' => 'snmp_context'));
	db_install_add_column('poller_item', array('name' => 'snmp_engine_id', 'type' => 'varchar(30)', 'default' => '', 'after' => 'snmp_context'));
	db_install_add_column('automation_snmp_items', array('name' => 'snmp_engine_id', 'type' => 'varchar(30)', 'default' => '', 'after' => 'snmp_context'));

	db_install_execute('ALTER TABLE host MODIFY COLUMN poller_id int(10) unsigned DEFAULT "1"');
	db_install_execute('ALTER TABLE host MODIFY COLUMN site_id int(10) unsigned DEFAULT "1"');

	/* adding columns for remote poller sync */
	db_install_add_column('host', array('name' => 'last_updated', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP', 'after' => 'availability'));
	db_install_add_key('host', 'INDEX', 'last_updated', array('last_updated'));

	db_install_add_column('host_snmp_cache', array('name' => 'last_updated', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP', 'after' => 'present'));
	db_install_add_key('host_snmp_cache', 'INDEX', 'last_updated', array('last_updated'));

	db_install_add_column('poller_item', array('name' => 'last_updated', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP', 'after' => 'present'));
	db_install_add_key('poller_item', 'INDEX', 'last_updated', array('last_updated'));

	db_install_add_column('poller_command', array('name' => 'last_updated', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP', 'after' => 'command'));
	db_install_add_key('poller_command', 'INDEX', 'last_updated', array('last_updated'));

	db_install_execute('ALTER TABLE automation_networks MODIFY COLUMN poller_id int(10) unsigned DEFAULT "1"');
	db_install_execute('ALTER TABLE automation_processes MODIFY COLUMN poller_id int(10) unsigned DEFAULT "1"');
	db_install_execute('ALTER TABLE poller_command MODIFY COLUMN poller_id int(10) unsigned DEFAULT "1"');
	db_install_execute('ALTER TABLE poller_item MODIFY COLUMN poller_id int(10) unsigned DEFAULT "1"');
	db_install_execute('ALTER TABLE poller_time MODIFY COLUMN poller_id int(10) unsigned DEFAULT "1"');

	/* add new column to make data query graphs easier to manage */
	db_install_add_column('graph_local', array('name' => 'snmp_query_graph_id', 'type' => 'INT UNSIGNED', 'NULL' => false, 'default' => '0', 'after' => 'snmp_query_id'));
	db_install_add_key('graph_local', 'INDEX', 'snmp_query_graph_id', array('snmp_query_graph_id'));

	/* add the snmp query graph id to graph local */
	db_install_execute("UPDATE graph_local AS gl
		INNER JOIN (
			SELECT DISTINCT local_graph_id, task_item_id
			FROM graph_templates_item
		) AS gti
		ON gl.id=gti.local_graph_id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id=dtr.id
		INNER JOIN data_template_data AS dtd
		ON dtr.local_data_id=dtd.local_data_id
		INNER JOIN data_input_fields AS dif
		ON dif.data_input_id=dtd.data_input_id
		INNER JOIN data_input_data AS did
		ON did.data_template_data_id=dtd.id
		AND did.data_input_field_id=dif.id
		INNER JOIN snmp_query_graph_rrd AS sqgr
		ON sqgr.snmp_query_graph_id=did.value
		SET gl.snmp_query_graph_id=did.value
		WHERE input_output='in'
		AND type_code='output_type'
		AND gl.snmp_query_id>0");

	if (!db_column_exists('graph_tree', 'sequence', false)) {
		/* allow sorting of trees */
		db_install_add_column('graph_tree', array('name' => 'sequence', 'type' => 'int(10) unsigned', 'NULL' => false, 'default' => '1', 'after' => 'name'));
		$trees_results = db_install_fetch_assoc('SELECT id FROM graph_tree ORDER BY name');
		$trees         = $trees_results['data'];
		if (cacti_sizeof($trees)) {
			foreach($trees as $sequence => $tree) {
				db_install_execute('UPDATE graph_tree SET sequence = ? WHERE id = ?', array($sequence+1, $tree['id']));
			}
		}
	}

	/* removing obsolete column */
	if (!db_column_exists('graph_templates_graph', 'export', false)) {
		db_install_drop_column('graph_templates_graph', 'export');
		db_install_drop_column('graph_templates_graph', 't_export');
		db_install_drop_column('aggregate_graph_templates_graph', 'export');
		db_install_drop_column('aggregate_graph_templates_graph', 't_export');
	}

	db_install_execute("CREATE TABLE IF NOT EXISTS `poller_data_template_field_mappings` (
		`data_template_id` int(10) unsigned NOT NULL DEFAULT '0',
		`data_name` varchar(40) NOT NULL DEFAULT '',
		`data_source_names` varchar(125) NOT NULL DEFAULT '',
		`last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`data_template_id`,`data_name`,`data_source_names`))
		ENGINE=InnoDB
		ROW_FORMAT=Dynamic
		COMMENT='Tracks mapping of Data Templates to their Data Source Names'");

	db_install_execute("INSERT IGNORE INTO poller_data_template_field_mappings
		SELECT dtr.data_template_id, dif.data_name, GROUP_CONCAT(dtr.data_source_name ORDER BY dtr.data_source_name) AS data_source_names, NOW()
		FROM data_template_rrd AS dtr
		INNER JOIN data_input_fields AS dif
		ON dtr.data_input_field_id = dif.id
		WHERE dtr.local_data_id=0
		GROUP BY dtr.data_template_id, dif.data_name");

	db_install_execute("ALTER TABLE host_snmp_cache MODIFY COLUMN field_value varchar(512) DEFAULT NULL");

	/* repair system data input methods */
	repair_system_data_input_methods('upgrade');

	$fields = "t_auto_scale_opts t_auto_scale_log t_scale_log_units t_auto_scale_rigid t_auto_padding t_base_value t_grouping t_unit_value t_unit_exponent_value t_alt_y_grid t_right_axis t_right_axis_label t_right_axis_format t_right_axis_formatter t_left_axis_formatter t_no_gridfit t_unit_length t_tab_width t_dynamic_labels t_force_rules_legend t_legend_position t_legend_direction t_image_format_id t_title t_height t_width t_upper_limit t_lower_limit t_vertical_label t_slope_mode t_auto_scale";

	/* repair install issues */
	$fields = explode(' ', $fields);
	foreach($fields as $field) {
		db_install_execute("UPDATE graph_templates_graph SET $field='' WHERE $field IS NULL");
		db_install_execute("UPDATE graph_templates_graph SET $field='' WHERE $field='0'");
	}
	db_install_execute("UPDATE graph_templates_graph SET unit_value='' WHERE unit_value='on'");

	db_install_add_key('data_local', 'INDEX', 'snmp_index', array('snmp_index(191)'));
	db_install_add_key('graph_local', 'INDEX', 'snmp_index', array('snmp_index(191)'));

	$wathermark_results = db_install_fetch_cell('SELECT name FROM settings WHERE name = "graph_wathermark"', 'name');
	$wathermark         = $wathermark_results['data'];

	if ($wathermark == 'graph_wathermark') {
		$wathermark_results = db_install_fetch_cell('SELECT COUNT(*) FROM settings WHERE name = "graph_wathermark"');
		$wathermark         = $wathermark_results['data'];
		if ($wathermark == 0) {
			db_install_execute('UPDATE settings SET name = "graph_watermark" WHERE name = "graph_wathermark"');
		} else {
			db_install_execute('DELETE FROM settings WHERE name = "graph_wathermark"');
		}
	}
}

function upgrade_realms() {
	$upgrade_realms = array(
		array('new_realm' => 101, 'file_pattern' => 'plugins.php'),
		array('new_realm' => 23,  'file_pattern' => 'discovery.php'),
		array('new_realm' => 25,  'file_pattern' => 'graph_image_rt.php'),
		array('new_realm' => 21,  'file_pattern' => 'nectar.php'),
		array('new_realm' => 22,  'file_pattern' => 'nectar_user.php'),
		array('new_realm' => 24,  'file_pattern' => 'superlinks.php'),
		array('new_realm' => 18,  'file_pattern' => 'clog.php'),
		array('new_realm' => 19,  'file_pattern' => 'clog_user.php')
	);

	$set_drop_realms = array(
		array('new_realm' => 5,   'file_pattern' => 'aggregate'),
		array('new_realm' => 15,  'file_pattern' => 'managers.php'),
		array('new_realm' => 15,  'file_pattern' => 'rrdcleaner.php'),
		array('new_realm' => 15,  'file_pattern' => 'settings.php'),
		array('new_realm' => 15,  'file_pattern' => 'superlinks-mgmt.php'),
		array('new_realm' => 1,   'file_pattern' => 'domains.php'),
		array('new_realm' => 1,   'file_pattern' => 'ugroup.php'),
	);

	$drop_realms = array('settings.php');

	$remove_plugins = array(
		'aggregate',
		'autom8',
		'clog',
		'discovery',
		'domains',
		'dsstats',
		'nectar',
		'realtime',
		'rrdclean',
		'settings',
		'snmpagent',
		'spikekill',
		'superlinks',
		'ugroup'
	);

	// Check for the installation of the plugin architecture
	if (db_table_exists('plugin_realms')) {
		// There can be only one of these, so just update if exist
		foreach($upgrade_realms as $r) {
			$exists_results = db_install_fetch_row('SELECT *
				FROM plugin_realms
				WHERE file LIKE ?', array('%' . $r['file_pattern'] . '%'), false);

			$exists = $exists_results['data'];

			if (cacti_sizeof($exists)) {
				$old_realm = $exists['id'] + 100;

				db_execute_prepared('UPDATE IGNORE user_auth_realm
					SET realm_id = ?
					WHERE realm_id = ?',
					array($r['new_realm'], $old_realm));
			}
		}

		// There are more than one of these so update and drop
		foreach($set_drop_realms as $r) {
			$exists_results = db_install_fetch_row('SELECT *
				FROM plugin_realms
				WHERE file LIKE ?',
				array('%' . $r['file_pattern'] . '%'), false);

			$exists = $exists_results['data'];

			if (cacti_sizeof($exists)) {
				$old_realm = $exists['id'] + 100;

				db_execute_prepared('REPLACE INTO user_auth_realm (user_id, realm_id)
					SELECT user_id, "' . $r['new_realm'] . '" AS realm_id
					FROM user_auth_realm
					WHERE realm_id = ?',
					array($old_realm));

				db_execute_prepared('DELETE FROM user_auth_realm
					WHERE realm_id = ?',
					array($old_realm));
			}
		}

		// Drop realms that have been deprecated
		foreach($drop_realms as $r) {
			$exists_results = db_install_fetch_row('SELECT *
				FROM plugin_realms
				WHERE file LIKE ?',
				array('%' . $r . '%'), false);

			$exists = $exists_results['data'];

			if ($exists) {
				$old_realm = $exists['id'] + 100;

				db_execute_prepared('DELETE FROM user_auth_realm WHERE realm_id = ?', array($old_realm));
			}
		}
	} else {
		db_install_execute("CREATE TABLE IF NOT EXISTS `plugin_config` (
			`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			`directory` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`status` tinyint(2) NOT NULL DEFAULT 0,
			`author` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`webpage` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`version` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			PRIMARY KEY (`id`),
			KEY `status` (`status`),
			KEY `directory` (`directory`))
			ENGINE=InnoDB
			ROW_FORMAT=DYNAMIC");

		db_install_execute("CREATE TABLE IF NOT EXISTS `plugin_db_changes` (
			`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			`plugin` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`table` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`column` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
			`method` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			PRIMARY KEY (`id`),
			KEY `plugin` (`plugin`),
			KEY `method` (`method`))
			ENGINE=InnoDB
			ROW_FORMAT=DYNAMIC");

		db_install_execute("CREATE TABLE IF NOT EXISTS `plugin_hooks` (
			`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			`name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`hook` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`function` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`status` int(8) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `hook` (`hook`),
			KEY `status` (`status`))
			ENGINE=InnoDB
			ROW_FORMAT=DYNAMIC");

		db_install_execute("CREATE TABLE IF NOT EXISTS `plugin_realms` (
			`id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
			`plugin` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			`file` text COLLATE utf8mb4_unicode_ci NOT NULL,
			`display` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
			PRIMARY KEY (`id`),
			KEY `plugin` (`plugin`))
			ENGINE=InnoDB
			ROW_FORMAT=DYNAMIC");

		db_install_execute("REPLACE INTO plugin_hooks (name, hook, file, function, status) VALUES
			('internal', 'config_arrays', '', 'plugin_config_arrays', 1),
			('internal', 'draw_navigation_text', '', 'plugin_draw_navigation_text', 1)");
	}

	// Add realms to the admin user if it exists
	$user_auth = db_install_fetch_row('SELECT * FROM user_auth WHERE id=1');
	if (cacti_sizeof($user_auth['data'])) {
		db_install_execute('INSERT IGNORE INTO user_auth_realm VALUES (18,1)');
		db_install_execute('INSERT IGNORE INTO user_auth_realm VALUES (20,1)');
		db_install_execute('INSERT IGNORE INTO user_auth_realm VALUES (21,1)');
		db_install_execute('INSERT IGNORE INTO user_auth_realm VALUES (23,1)');
	}

	/* add admin permissions */
	$userid= db_install_fetch_cell("SELECT * FROM user_auth WHERE id='1' AND username='admin'");
	if (!empty($userid['data'])) {
		db_install_execute("REPLACE INTO `user_auth_realm` VALUES (19,1);");
		db_install_execute("REPLACE INTO `user_auth_realm` VALUES (22,1);");
	}

	if (db_table_exists('superlinks_auth', false)) {
		/* drop no longer present superlink pages */
		db_install_execute('DELETE FROM superlinks_auth
			WHERE pageid NOT IN (SELECT id FROM superlinks_pages)');

		/* create authorization records for existing pages */
		db_install_execute('REPLACE INTO user_auth_realm
			(user_id, realm_id)
			SELECT userid, pageid+10000
			FROM superlinks_auth');

		/* create authorization records for viewing the external links tab */
		db_install_execute('REPLACE INTO user_auth_realm
			(user_id, realm_id)
			SELECT userid, "24" AS realm_id
			FROM superlinks_auth');

		/* Handle special userid=0 case in Superlinks */
		db_install_execute('REPLACE INTO user_auth_realm (user_id, realm_id)
			SELECT user_id, pageid
			FROM (
				SELECT ua.id AS user_id, sa.pageid+10000 AS pageid
				FROM user_auth AS ua
				JOIN superlinks_auth AS sa
				WHERE sa.userid=0
			) AS rs');
	}

	foreach($remove_plugins as $p) {
		/* remove plugin */
		db_install_execute("DELETE FROM plugin_config WHERE directory = ?", array($p));
		db_install_execute("DELETE FROM plugin_realms WHERE plugin = ?", array($p));
		db_install_execute("DELETE FROM plugin_db_changes WHERE plugin = ?", array($p));
		db_install_execute("DELETE FROM plugin_hooks WHERE name = ?", array($p));
	}
}

