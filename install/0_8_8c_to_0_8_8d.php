<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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

function upgrade_to_0_8_8d() {
	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `user_auth_group` (
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
		ENGINE=MyISAM
		COMMENT='Table that Contains User Groups';");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `user_auth_group_perms` (
		`group_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
		`item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
		`type` tinyint(2) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`group_id`,`item_id`,`type`),
		KEY `group_id` (`group_id`,`type`))
		ENGINE=MyISAM
		COMMENT='Table that Contains User Group Permissions';");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `user_auth_group_realm` (
		`group_id` int(10) unsigned NOT NULL,
		`realm_id` int(10) unsigned NOT NULL,
		PRIMARY KEY  (`group_id`, `realm_id`),
		KEY `group_id` (`group_id`),
		KEY `realm_id` (`realm_id`))
		ENGINE=MyISAM
		COMMENT='Table that Contains User Group Realm Permissions';");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `user_auth_group_members` (
		`group_id` int(10) unsigned NOT NULL,
		`user_id` int(10) unsigned NOT NULL,
		PRIMARY KEY  (`group_id`, `user_id`),
		KEY `group_id` (`group_id`),
		KEY `realm_id` (`user_id`))
		ENGINE=MyISAM
		COMMENT='Table that Contains User Group Members';");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `settings_graphs_group` (
		`group_id` smallint(8) unsigned NOT NULL DEFAULT '0',
		`name` varchar(50) NOT NULL DEFAULT '',
		`value` varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (`group_id`,`name`))
		ENGINE=MyISAM
		COMMENT='Stores the Default User Group Graph Settings';");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `data_source_stats_daily` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`average` DOUBLE DEFAULT NULL,
		`peak` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=MyISAM;");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `data_source_stats_hourly` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`average` DOUBLE DEFAULT NULL,
		`peak` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=MyISAM;");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `data_source_stats_hourly_cache` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`time` timestamp NOT NULL default '0000-00-00 00:00:00',
		`value` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`time`,`rrd_name`),
		KEY `time` USING BTREE (`time`)
		) ENGINE=MEMORY;");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `data_source_stats_hourly_last` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`value` DOUBLE DEFAULT NULL,
		`calculated` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=MEMORY;");

	if (!sizeof(db_fetch_row("SHOW COLUMNS from data_source_stats_hourly_last where Field='calculated'"))) {
		db_install_execute('0.8.8d', "ALTER TABLE data_source_stats_hourly_last ADD calculated DOUBLE DEFAULT NULL AFTER `value`");
	};

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `data_source_stats_monthly` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`average` DOUBLE DEFAULT NULL,
		`peak` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=MyISAM;"
	);

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `data_source_stats_weekly` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`average` DOUBLE DEFAULT NULL,
		`peak` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=MyISAM;"
	);

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `data_source_stats_yearly` (
		`local_data_id` mediumint(8) unsigned NOT NULL,
		`rrd_name` varchar(19) NOT NULL,
		`average` DOUBLE DEFAULT NULL,
		`peak` DOUBLE DEFAULT NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`)
		) ENGINE=MyISAM;"
	);

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `poller_output_boost` (
		`local_data_id` mediumint(8) unsigned NOT NULL default '0',
		`rrd_name` varchar(19) NOT NULL default '',
		`time` datetime NOT NULL default '0000-00-00 00:00:00',
		`output` varchar(512) NOT NULL,
		PRIMARY KEY USING BTREE (`local_data_id`,`time`,`rrd_name`))
		ENGINE=MEMORY;");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `poller_output_boost_processes` (
		`sock_int_value` bigint(20) unsigned NOT NULL auto_increment,
		`status` varchar(255) default NULL,
		PRIMARY KEY (`sock_int_value`))
		ENGINE=MEMORY;");

	if (db_table_exists('plugin_domains')) {
		db_install_execute('0.8.8d', 'RENAME TABLE plugin_domains TO user_domains');
	}

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `user_domains` (
		`domain_id` int(10) unsigned NOT NULL auto_increment,
		`domain_name` varchar(20) NOT NULL,
		`type` int(10) UNSIGNED NOT NULL DEFAULT '0',
		`enabled` char(2) NOT NULL DEFAULT 'on',
		`defdomain` tinyint(3) NOT NULL DEFAULT '0',
		`user_id` int(10) unsigned NOT NULL default '0',
		PRIMARY KEY  (`domain_id`))
		ENGINE=MyISAM
		COMMENT='Table to Hold Login Domains';");

	if (db_table_exists('plugin_domains_ldsp')) {
		db_install_execute('0.8.8d', 'RENAME TABLE plugin_domains_ldsp TO user_domains_ldap');
	}

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `user_domains_ldap` (
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
		ENGINE=MyISAM
		COMMENT='Table to Hold Login Domains for LDAP';");
		
	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `data_source_purge_temp` (
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
		ENGINE=MyISAM 
		COMMENT='RRD Cleaner File Repository';");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `data_source_purge_action` (
		`id` integer UNSIGNED auto_increment,
		`name` varchar(128) NOT NULL default '',
		`local_data_id` mediumint(8) unsigned NOT NULL default '0',
		`action` tinyint(2) NOT NULL default 0,
		PRIMARY KEY (`id`),
		UNIQUE KEY name (`name`))
		ENGINE=MyISAM 
		COMMENT='RRD Cleaner File Actions';");

	db_install_execute('0.8.8d', "ALTER TABLE graph_tree 
		ADD COLUMN enabled char(2) DEFAULT 'on' AFTER id,
		ADD COLUMN locked TINYINT default '0' AFTER enabled, 
		ADD COLUMN locked_date TIMESTAMP default '0000-00-00' AFTER locked, 
		ADD COLUMN last_modified TIMESTAMP default '0000-00-00' AFTER name, 
		ADD COLUMN user_id INT UNSIGNED default '1' AFTER name, 
		ADD COLUMN modified_by INT UNSIGNED default '1'");

	db_install_execute('0.8.8d', "ALTER TABLE graph_tree_items 
		MODIFY COLUMN id BIGINT UNSIGNED NOT NULL auto_increment, 
		ADD COLUMN parent BIGINT UNSIGNED default NULL AFTER id, 
		ADD COLUMN position int UNSIGNED default NULL AFTER parent,
		ADD INDEX parent (parent)");

	db_install_execute('0.8.8d', "CREATE TABLE IF NOT EXISTS `user_auth_cache` (
		`user_id` int(10) unsigned NOT NULL DEFAULT '0',
		`hostname` varchar(64) NOT NULL DEFAULT '',
		`last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`token` varchar(1024) NOT NULL DEFAULT '') 
		ENGINE=MyISAM 
		COMMENT='Caches Remember Me Details'");


	// Convert all trees to new format
	include_once('./include/global.php');

	define('CHARS_PER_TIER', 3);

	$trees      = db_fetch_assoc("SELECT id FROM graph_tree ORDER BY id");

	if (sizeof($trees)) {
	foreach($trees as $t) {
		$tree_items = db_fetch_assoc("SELECT * 
			FROM graph_tree_items 
			WHERE graph_tree_id=" . $t['id'] . " 
			AND order_key NOT LIKE '___000%' 
			ORDER BY order_key");

		/* reset the position variable in case we run more than once */
		db_execute("UPDATE graph_tree_items SET position=0 WHERE graph_tree_id=" . $t['id']);

		$prev_parent = 0;
		$prev_id     = 0;
		$position    = 0;

		if (sizeof($tree_items)) {
			foreach($tree_items AS $item) {
				$translated_key = rtrim($item["order_key"], "0\r\n");
				$missing_len    = strlen($translated_key) % CHARS_PER_TIER;
				if ($missing_len > 0) {
					$translated_key .= substr("000", 0, $missing_len);
				}
				$parent_key_len = strlen($translated_key) - CHARS_PER_TIER;
				$parent_key     = substr($translated_key, 0, $parent_key_len);
				$parent_id      = db_fetch_cell("SELECT id FROM graph_tree_items WHERE graph_tree_id=" . $item["graph_tree_id"] . " AND order_key LIKE '" . $parent_key . "000%'");

				if (!empty($parent_id)) {
					/* get order */
					if ($parent_id != $prev_parent) {
						$position = 0;
					}

					$position = db_fetch_cell("SELECT MAX(position) 
						FROM graph_tree_items 
						WHERE graph_tree_id=" . $item['graph_tree_id'] . " 
						AND parent=" . $parent_id) + 1;

					db_execute("UPDATE graph_tree_items SET parent=$parent_id, position=$position WHERE id=" . $item["id"]);
				}else{
					db_execute("UPDATE graph_tree_items SET parent=0, position=$position WHERE id=" . $item["id"]);
				}

				$prev_parent = $parent_id;
			}
		}

		/* get base tree items and set position */
		$tree_items = db_fetch_assoc("SELECT * 
			FROM graph_tree_items
			WHERE graph_tree_id=" . $t['id'] . " 
			AND order_key LIKE '___000%' 
			ORDER BY order_key");

		$position = 0;
		if (sizeof($tree_items)) {
			foreach($tree_items as $item) {
				db_execute("UPDATE graph_tree_items SET parent=0, position=$position WHERE id=" . $item['id']);
				$position++;
			}
		}
	}
	}

	db_install_execute('0.8.8d', "ALTER TABLE graph_tree_items DROP COLUMN order_key");
}
