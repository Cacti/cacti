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

include_once($config['base_path'] . '/lib/template.php');
include_once($config['base_path'] . '/lib/utility.php');

function upgrade_to_0_8_3() {
	if (db_column_exists('user_auth', 'graph_policy')) {
		db_install_execute("ALTER TABLE `user_auth` CHANGE `graph_policy` `policy_graphs` TINYINT( 1 ) UNSIGNED DEFAULT '1' NOT NULL;");
	}

	db_install_add_column('user_auth', array('name' => 'policy_trees', 'type' => 'tinyint(1) unsigned', 'default' => '1', 'NULL' => false));
	db_install_add_column('user_auth', array('name' => 'policy_hosts', 'type' => 'tinyint(1) unsigned', 'default' => '1', 'NULL' => false));
	db_install_add_column('user_auth', array('name' => 'policy_graph_templates', 'type' => 'tinyint(1) unsigned', 'default' => '1', 'NULL' => false));
	db_install_add_column('graph_tree_items', array('name' => 'host_id', 'type' => 'mediumint(8) unsigned', 'default' => '0', 'NULL' => false, 'after' => 'title'));

	if (!db_column_exists('rra', 'timespan')) {
		db_install_add_column('rra', array('name' => 'timespan', 'type' => 'int(12) unsigned', 'NULL' => false));
		db_install_execute("UPDATE rra set timespan=(rows*steps*144);");
	}

	if (!db_table_exists('user_auth_perms')) {
		db_install_execute("CREATE TABLE `user_auth_perms` (
			`user_id` mediumint(8) unsigned NOT NULL default '0',
			`item_id` mediumint(8) unsigned NOT NULL default '0',
			`type` tinyint(2) unsigned NOT NULL default '0',
			PRIMARY KEY  (`user_id`,`item_id`,`type`),
			KEY `user_id` (`user_id`,`type`)
			)");

		$auth_graph_results = db_install_fetch_assoc("SELECT user_id,local_graph_id FROM user_auth_graph");
		$auth_graph         = $auth_graph_results['data'];

		/* update to new 'user_auth_perms' table */
		if (cacti_sizeof($auth_graph) > 0) {
			foreach ($auth_graph as $item) {
				db_install_execute("REPLACE INTO user_auth_perms (user_id,item_id,type) VALUES (?,?,1);",
					array($item["user_id"],$item["local_graph_id"]),false);
			}
		}

		$auth_tree_results = db_install_fetch_assoc("SELECT user_id,tree_id FROM user_auth_tree");
		$auth_tree         = $auth_tree_results['data'];

		/* update to new 'user_auth_perms' table */
		if (cacti_sizeof($auth_tree) > 0) {
			foreach ($auth_tree as $item) {
				db_install_execute("REPLACE INTO user_auth_perms (user_id,item_id,type) VALUES (?,?,2);",
					array($item["user_id"],$item["tree_id"]), false);
			}
		}

		$users_results = db_install_fetch_assoc("SELECT id FROM user_auth");
		$users         = $users_results['data'];

		/* default all current users to tree view mode 1 (single pane) */
		if (cacti_sizeof($users) > 0) {
			foreach ($users as $item) {
				db_install_execute("REPLACE INTO settings_graphs (user_id,name,value) VALUES (?,'default_tree_view_mode',1);",
					array($item["id"]), false);
			}
		}
	}

	/* drop unused tables */
	db_install_drop_table('user_auth_graph');
	db_install_drop_table('user_auth_tree');
	db_install_drop_table('user_auth_hosts');

	/* bug#72 */
	db_install_execute("UPDATE graph_templates_item set cdef_id=15 where id=25;");
	db_install_execute("UPDATE graph_templates_item set cdef_id=15 where id=26;");
	db_install_execute("UPDATE graph_templates_item set cdef_id=15 where id=27;");
	db_install_execute("UPDATE graph_templates_item set cdef_id=15 where id=28;");

	push_out_graph_item(25);
	push_out_graph_item(26);
	push_out_graph_item(27);
	push_out_graph_item(28);

	/* too many people had problems with the poller cache in 0.8.2a... */
	db_install_drop_table('data_input_data_cache');
	db_install_execute("CREATE TABLE `data_input_data_cache` (
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
		)");
}
