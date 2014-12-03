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
	db_execute("CREATE TABLE IF NOT EXISTS `user_auth_group` (
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

	db_execute("CREATE TABLE IF NOT EXISTS `user_auth_group_perms` (
		`group_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
		`item_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
		`type` tinyint(2) unsigned NOT NULL DEFAULT '0',
		PRIMARY KEY (`group_id`,`item_id`,`type`),
		KEY `group_id` (`group_id`,`type`))
		ENGINE=MyISAM
		COMMENT='Table that Contains User Group Permissions';");

	db_execute("CREATE TABLE IF NOT EXISTS `user_auth_group_realm` (
		`group_id` int(10) unsigned NOT NULL,
		`realm_id` int(10) unsigned NOT NULL,
		PRIMARY KEY  (`group_id`, `realm_id`),
		KEY `group_id` (`group_id`),
		KEY `realm_id` (`realm_id`))
		ENGINE=MyISAM
		COMMENT='Table that Contains User Group Realm Permissions';");

	db_execute("CREATE TABLE IF NOT EXISTS `user_auth_group_members` (
		`group_id` int(10) unsigned NOT NULL,
		`user_id` int(10) unsigned NOT NULL,
		PRIMARY KEY  (`group_id`, `user_id`),
		KEY `group_id` (`group_id`),
		KEY `realm_id` (`user_id`))
		ENGINE=MyISAM
		COMMENT='Table that Contains User Group Members';");

	db_execute("CREATE TABLE IF NOT EXISTS `settings_graphs_group` (
		`group_id` smallint(8) unsigned NOT NULL DEFAULT '0',
		`name` varchar(50) NOT NULL DEFAULT '',
		`value` varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (`group_id`,`name`))
		ENGINE=MyISAM
		COMMENT='Stores the Default User Group Graph Settings';");
}
?>
