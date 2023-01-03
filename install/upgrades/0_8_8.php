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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_0_8_8() {
	/* speed up the joins */
	if (db_column_exists('poller_item', 'host_id')) {
		db_install_execute("ALTER TABLE `poller_item` MODIFY COLUMN `host_id` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0'");
	}

	db_install_add_key('poller_output', 'key', 'PRIMARY', array('local_data_id', 'rrd_name', 'time'));

	/* speed up user management */
	db_install_add_key('user_log', 'key', 'user_id', array('user_id'));

	/* Plugin Architecture
	 * be prepared to find those data already present
	 * in case of upgrade of a cacti+PIA installation */
	$sql =     "CREATE TABLE IF NOT EXISTS `plugin_config` (
				`id` 		int(8) unsigned NOT NULL auto_increment,
				`directory` varchar(32) 	NOT NULL default '',
				`name` 		varchar(64) 	NOT NULL default '',
				`status`	tinyint(2) 		NOT NULL default 0,
				`author`	varchar(64) 	NOT NULL default '',
				`webpage`	varchar(255)  	NOT NULL default '',
				`version`	varchar(8) 		NOT NULL default '',
				PRIMARY KEY  (`id`),
				KEY `status` (`status`),
				KEY `directory` (`directory`)
				) ENGINE=MyISAM COMMENT='Plugin Configuration'";
	db_install_execute($sql);

	$sql =     "CREATE TABLE IF NOT EXISTS `plugin_db_changes` (
				`id` 		int(10) unsigned NOT NULL auto_increment,
				`plugin` 	varchar(16) 	NOT NULL default '',
				`table` 	varchar(64) 	NOT NULL default '',
				`column`	varchar(64) 	NOT NULL default '',
				`method` 	varchar(16) 	NOT NULL default '',
				PRIMARY KEY  (`id`),
				KEY `plugin` (`plugin`),
				KEY `method` (`method`)
				) ENGINE=MyISAM COMMENT='Plugin Database Changes'";
	db_install_execute($sql);

	$sql =     "CREATE TABLE IF NOT EXISTS `plugin_hooks` (
				`id` 		int(8) unsigned NOT NULL auto_increment,
				`name` 		varchar(32) 	NOT NULL default '',
				`hook` 		varchar(64) 	NOT NULL default '',
				`file`		varchar(255) 	NOT NULL default '',
				`function` 	varchar(128) 	NOT NULL default '',
				`status`	int(8) 			NOT NULL default 0,
				PRIMARY KEY  (`id`),
				KEY `hook` (`hook`),
				KEY `status` (`status`)
				) ENGINE=MyISAM COMMENT='Plugin Hooks'";
	db_install_execute($sql);

	$sql =     "CREATE TABLE IF NOT EXISTS `plugin_realms` (
				`id` 		int(8) unsigned NOT NULL auto_increment,
				`plugin` 	varchar(32) 	NOT NULL default '',
				`file`		text		 	NOT NULL,
				`display` 	varchar(64) 	NOT NULL default '',
				PRIMARY KEY  (`id`),
				KEY `plugin` (`plugin`)
				) ENGINE=MyISAM COMMENT='Plugin Realms'";
	db_install_execute($sql);

	/* fill initial data into plugin tables
	 * be prepared to find those data already present
	 * in case of upgrade of a cacti+PIA installation */
	db_install_execute("REPLACE INTO `plugin_realms` VALUES (1, 'internal', 'plugins.php', 'Plugin Management')");
	db_install_execute("REPLACE INTO `plugin_hooks` VALUES (1, 'internal', 'config_arrays', '', 'plugin_config_arrays', 1)");
	db_install_execute("REPLACE INTO `plugin_hooks` VALUES (2, 'internal', 'draw_navigation_text', '', 'plugin_draw_navigation_text', 1)");
	/* allow admin user to access Plugin Management */
	db_install_execute("REPLACE INTO user_auth_realm VALUES (101,1)");

	/* create index on data_template_data on data_input_id */
	db_install_add_key('data_template_data', 'key', 'data_input_id', array('data_input_id'));
}
