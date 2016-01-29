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

function upgrade_to_0_8_3() {
	include("../lib/template.php");

	db_install_execute("0.8.3", "ALTER TABLE `user_auth` CHANGE `graph_policy` `policy_graphs` TINYINT( 1 ) UNSIGNED DEFAULT '1' NOT NULL;");
	db_install_execute("0.8.3", "ALTER TABLE `user_auth` ADD `policy_trees` TINYINT( 1 ) UNSIGNED DEFAULT '1' NOT NULL, ADD `policy_hosts` TINYINT( 1 ) UNSIGNED DEFAULT '1' NOT NULL, ADD `policy_graph_templates` TINYINT( 1 ) UNSIGNED DEFAULT '1' NOT NULL;");
	db_install_execute("0.8.3", "ALTER TABLE `graph_tree_items` ADD `host_id` MEDIUMINT( 8 ) UNSIGNED NOT NULL AFTER `title`;");
	db_install_execute("0.8.3", "ALTER TABLE `rra` ADD `timespan` INT( 12 ) UNSIGNED NOT NULL;");
	db_install_execute("0.8.3", "UPDATE rra set timespan=(rows*steps*144);");
	db_install_execute("0.8.3", "CREATE TABLE `user_auth_perms` (
		`user_id` mediumint(8) unsigned NOT NULL default '0',
		`item_id` mediumint(8) unsigned NOT NULL default '0',
		`type` tinyint(2) unsigned NOT NULL default '0',
		PRIMARY KEY  (`user_id`,`item_id`,`type`),
		KEY `user_id` (`user_id`,`type`)
		) TYPE=MyISAM;");

	$auth_graph = db_fetch_assoc("select user_id,local_graph_id from user_auth_graph");

	/* update to new 'user_auth_perms' table */
	if (sizeof($auth_graph) > 0) {
	foreach ($auth_graph as $item) {
		db_install_execute("0.8.3", "replace into user_auth_perms (user_id,item_id,type) values (" . $item["user_id"] . "," . $item["local_graph_id"] . ",1);");
	}
	}

	$auth_tree = db_fetch_assoc("select user_id,tree_id from user_auth_tree");

	/* update to new 'user_auth_perms' table */
	if (sizeof($auth_tree) > 0) {
	foreach ($auth_tree as $item) {
		db_install_execute("0.8.3", "replace into user_auth_perms (user_id,item_id,type) values (" . $item["user_id"] . "," . $item["tree_id"] . ",2);");
	}
	}

	$users = db_fetch_assoc("select id from user_auth");

	/* default all current users to tree view mode 1 (single pane) */
	if (sizeof($users) > 0) {
	foreach ($users as $item) {
		db_install_execute("0.8.3", "replace into settings_graphs (user_id,name,value) values (" . $item["id"] . ",'default_tree_view_mode',1);");
	}
	}

	/* drop unused tables */
	db_install_execute("0.8.3", "DROP TABLE `user_auth_graph`;");
	db_install_execute("0.8.3", "DROP TABLE `user_auth_tree`;");
	db_install_execute("0.8.3", "DROP TABLE `user_auth_hosts`;");

	/* bug#72 */
	db_install_execute("0.8.3", "UPDATE graph_templates_item set cdef_id=15 where id=25;");
	db_install_execute("0.8.3", "UPDATE graph_templates_item set cdef_id=15 where id=26;");
	db_install_execute("0.8.3", "UPDATE graph_templates_item set cdef_id=15 where id=27;");
	db_install_execute("0.8.3", "UPDATE graph_templates_item set cdef_id=15 where id=28;");

	push_out_graph_item(25);
	push_out_graph_item(26);
	push_out_graph_item(27);
	push_out_graph_item(28);

	/* too many people had problems with the poller cache in 0.8.2a... */
	db_install_execute("0.8.3", "DROP TABLE `data_input_data_cache`");
	db_install_execute("0.8.3", "CREATE TABLE `data_input_data_cache` (
		`local_data_id` mediumint(8) unsigned NOT NULL default '0',
		`host_id` mediumint(8) NOT NULL default '0',
		`data_input_id` mediumint(8) unsigned NOT NULL default '0',
		`action` tinyint(2) NOT NULL default '1',
		`command` varchar(255) NOT NULL default '',
		`management_ip` varchar(15) NOT NULL default '',
		`snmp_community` varchar(100) NOT NULL default '',
		`snmp_version` tinyint(1) NOT NULL default '0',
		`snmp_username` varchar(50) NOT NULL default '',
		`snmp_password` varchar(50) NOT NULL default '',
		`rrd_name` varchar(19) NOT NULL default '',
		`rrd_path` varchar(255) NOT NULL default '',
		`rrd_num` tinyint(2) unsigned NOT NULL default '0',
		`arg1` varchar(255) default NULL,
		`arg2` varchar(255) default NULL,
		`arg3` varchar(255) default NULL,
		PRIMARY KEY  (`local_data_id`,`rrd_name`),
		KEY `local_data_id` (`local_data_id`)
		) TYPE=MyISAM;");
}

?>
