<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_0_8_6() {
	include("../lib/data_query.php");
	include("../lib/tree.php");

	db_install_execute("0.8.6", "DROP TABLE `snmp_query_field`;");
	db_install_execute("0.8.6", "DROP TABLE `data_input_data_cache`;");
	db_install_execute("0.8.6", "DROP TABLE `data_input_data_fcache`;");

	/*distributed poller support*/
	db_install_execute("0.8.6", "CREATE TABLE `poller` (`id` smallint(5) unsigned NOT NULL auto_increment, `hostname` varchar(250) NOT NULL default '', `ip_address` int(11) unsigned NOT NULL default '0', `last_update` datetime NOT NULL default '0000-00-00 00:00:00', PRIMARY KEY  (`id`)) TYPE=MyISAM;");
	db_install_execute("0.8.6", "CREATE TABLE `poller_command` (`poller_id` smallint(5) unsigned NOT NULL default '0', `time` datetime NOT NULL default '0000-00-00 00:00:00', `action` tinyint(3) unsigned NOT NULL default '0', `command` varchar(200) NOT NULL default '', PRIMARY KEY  (`poller_id`,`action`,`command`)) TYPE=MyISAM;");
	db_install_execute("0.8.6", "CREATE TABLE `poller_field` (`local_data_id` mediumint(8) unsigned NOT NULL default '0', `data_input_field_name` varchar(100) NOT NULL default '', `rrd_data_source_name` varchar(19) NOT NULL default '', `output` text NOT NULL, PRIMARY KEY  (`local_data_id`,`rrd_data_source_name`)) TYPE=MyISAM;");
	db_install_execute("0.8.6", "CREATE TABLE `poller_item` (`local_data_id` mediumint(8) unsigned NOT NULL default '0', `poller_id` smallint(5) unsigned NOT NULL default '0', `host_id` mediumint(8) NOT NULL default '0', `action` tinyint(2) unsigned NOT NULL default '1', `hostname` varchar(250) NOT NULL default '', `snmp_community` varchar(100) NOT NULL default '', `snmp_version` tinyint(1) unsigned NOT NULL default '0', `snmp_username` varchar(50) NOT NULL default '', `snmp_password` varchar(50) NOT NULL default '', `snmp_port` mediumint(5) unsigned NOT NULL default '161', `snmp_timeout` mediumint(8) unsigned NOT NULL default '0', `rrd_name` varchar(19) NOT NULL default '', `rrd_path` varchar(255) NOT NULL default '', `rrd_num` tinyint(2) unsigned NOT NULL default '0', `arg1` varchar(255) default NULL, `arg2` varchar(255) default NULL, `arg3` varchar(255) default NULL, PRIMARY KEY  (`local_data_id`,`rrd_name`), KEY `local_data_id` (`local_data_id`)) TYPE=MyISAM;");
	db_install_execute("0.8.6", "CREATE TABLE `poller_output` (`local_data_id` mediumint(8) unsigned NOT NULL default '0', `rrd_name` varchar(19) NOT NULL default '', `time` datetime NOT NULL default '0000-00-00 00:00:00', `output` text NOT NULL, PRIMARY KEY  (`local_data_id`,`rrd_name`,`time`)) TYPE=MyISAM;");
	db_install_execute("0.8.6", "CREATE TABLE `poller_reindex` (`host_id` mediumint(8) unsigned NOT NULL default '0', `data_query_id` mediumint(8) unsigned NOT NULL default '0', `action` tinyint(3) unsigned NOT NULL default '0', `op` char(1) NOT NULL default '', `assert_value` varchar(100) NOT NULL default '', `arg1` varchar(100) NOT NULL default '', PRIMARY KEY  (`host_id`,`data_query_id`,`arg1`)) TYPE=MyISAM;");
	db_install_execute("0.8.6", "CREATE TABLE `poller_time` (`id` mediumint(8) unsigned NOT NULL auto_increment, `poller_id` smallint(5) unsigned NOT NULL default '0', `start_time` datetime NOT NULL default '0000-00-00 00:00:00', `end_time` datetime NOT NULL default '0000-00-00 00:00:00', PRIMARY KEY  (`id`)) TYPE=MyISAM;");

	/*smtp mail server support*/
//	db_install_execute("0.8.6", "CREATE TABLE `smtp_server` (`id` mediumint(8) unsigned NOT NULL auto_increment, `server_name` varchar(50) NOT NULL default '', `server_hostname` varchar(100), `server_timeout` mediumint(8) unsigned NOT NULL default '5', `server_port` mediumint(8) unsigned NOT NULL default '25', `server_enabled` tinyint(2) unsigned default '1', `server_uid` varchar(50), `server_password` varchar(50), `server_type` varchar(10), PRIMARY_KEY (`id`)) TYPE=MyISAM;");
//	db_install_execute("0.8.6", "CREATE TABLE `smtp_queue` (`id` mediumint(11) unsigned NOT NULL auto_increment, `email_template_id` mediumint(8) unsigned NOT NULL, `queue_date` datetime NOT NULL default '0000-00-00 00:00:00', `queue_type` varchar(25), `mail_to` varchar(250), `mail_subject` varchar(100), `mail_body` varchar(500), `mail_from` varchar(100), PRIMARY KEY  (`id`)) TYPE=MyISAM;");
//	db_install_execute("0.8.6", "CREATE TABLE `smtp_email_template` (`id` mediumint(8) unsigned NOT NULL auto_increment, `smtp_server_id` mediumint(8) unsigned NOT NULL, `template_name` varchar(50), `email_from` varchar(100), `email_to` varchar(250), `email_subject` varchar(100), `email_recovery` varchar(500), `email_failure` varchar(500), `max_message_size` mediumint(5) unsigned, `break_message` tinyint(2), PRIMARY KEY  (`id`)) TYPE=MyISAM;");

	/*host status and event tables*/
//	db_install_execute("0.8.6", "CREATE TABLE `host_status` (`id` mediumint(8) unsigned NOT NULL, `host_icon` mediumint(5) unsigned, `host_icon_flag` mediumint(5) unsigned, `host_success` int(11) unsigned, `host_failure` mediumint(8) unsigned, `min_time` mediumint(8) unsigned, `max_time` mediumint(8) unsigned, `cur_time` mediumint(8) unsigned, `avg_time` mediumint(8) unsigned, `host_status` varchar(50), `last_error` varchar(100), `host_stat_count` mediumint(5) unsigned, `host_rec_date` datetime NOT NULL default '0000-00-00 00:00:00', `host_fail_date` datetime NOT NULL default '0000-00-00 00:00:00', PRIMARY_KEY (`id`)) TYPE=MyISAM;");
//	db_install_execute("0.8.6", "CREATE TABLE `host_email_templates` (`host_id` mediumint(8) unsigned NOT NULL, `email_template_id` mediumint(8) unsigned, `enabled` tinyint(2), PRIMARY_KEY (`host_id`, `email_template_id`)) TYPE=MyISAM;");
//	db_install_execute("0.8.6", "CREATE TABLE `host_event` (`id` mediumint(8) unsigned NOT NULL auto_increment, `host_id` mediumint(8) unsigned NOT NULL, `event_icon` mediumint(5) unsigned, `event_date` datetime NOT NULL default '0000-00-00 00:00:00', `event_type` varchar(50), `event_description` varchar(250), PRIMARY KEY  (`id`)) TYPE=MyISAM;");

	db_install_execute("0.8.6", "ALTER TABLE `graph_tree_items` ADD `host_grouping_type` TINYINT( 3 ) UNSIGNED DEFAULT '1' NOT NULL, ADD `sort_children_type` TINYINT( 3 ) UNSIGNED DEFAULT '1' NOT NULL;");
	db_install_execute("0.8.6", "ALTER TABLE `host_snmp_query` ADD `sort_field` VARCHAR( 50 ) NOT NULL, ADD `title_format` VARCHAR( 50 ) NOT NULL, ADD `reindex_method` TINYINT( 3 ) UNSIGNED NOT NULL;");
	db_install_execute("0.8.6", "ALTER TABLE `graph_tree_items` CHANGE `order_key` `order_key` VARCHAR( 100 ) DEFAULT '0' NOT NULL;");
	db_install_execute("0.8.6", "ALTER TABLE `host` ADD `status_event_count` mediumint(8) unsigned NOT NULL default '0', ADD `status_fail_date` datetime NOT NULL default '0000-00-00 00:00:00', ADD `status_rec_date` datetime NOT NULL default '0000-00-00 00:00:00', ADD `status_last_error` varchar(50) default '', ADD `min_time` decimal(7,5) default '0.00000', ADD `max_time` decimal(7,5) default '0.00000', ADD `cur_time` decimal(7,5) default '0.00000', ADD `avg_time` decimal(7,5) default '0.00000', ADD `total_polls` int(12) unsigned default '0', ADD `failed_polls` int(12) unsigned default '0', ADD `availability` decimal(7,5) default '100.000' NOT NULL;");
	db_install_execute("0.8.6", "ALTER TABLE `host` ADD `failed_polls` int(12) unsigned default '0', ADD `availability` decimal(7,5) default '100.000' NOT NULL;");
	db_install_execute("0.8.6", "UPDATE snmp_query_graph_rrd_sv set text = REPLACE(text,' (In)','') where snmp_query_graph_id = 2;");

	/* update the sort cache */
	$host_snmp_query = db_fetch_assoc("select host_id,snmp_query_id from host_snmp_query");

	if (sizeof($host_snmp_query) > 0) {
		foreach ($host_snmp_query as $item) {
			update_data_query_sort_cache($item["host_id"], $item["snmp_query_id"]);
		}
	}

	/* update trees to three characters per tier */
	$trees = db_fetch_assoc("select id from graph_tree");

	if (sizeof($trees) > 0) {
		foreach ($trees as $tree) {
			$tree_items = db_fetch_assoc("select
				graph_tree_items.id,
				graph_tree_items.order_key
				from graph_tree_items
				where graph_tree_items.graph_tree_id='" . $tree["id"] . "'
				order by graph_tree_items.order_key");

			if (sizeof($tree_items) > 0) {
				$_tier = 0;

				foreach ($tree_items as $tree_item) {
					$tier = tree_tier($tree_item["order_key"], 2);

					/* back off */
					if ($tier < $_tier) {
						for ($i=$_tier; $i>$tier; $i--) {
							print "reset ctr = $i\n";
							$tier_counter[$i] = 0;
						}
					}

					/* we key tier==0 off of '1' and tier>0 off of '0' */
					if (!isset($tier_counter[$tier])) {
						$tier_counter[$tier] = 1;
					}else{
						$tier_counter[$tier]++;
					}

					$search_key = preg_replace("/0+$/", "", $tree_item["order_key"]);
					if (strlen($search_key) % 2 != 0) { $search_key .= "0"; }

					$new_search_key = "";
					for ($i=1; $i<$tier; $i++) {
						$new_search_key .= str_pad(strval($tier_counter[$i]),3,'0',STR_PAD_LEFT);
					}

					/* build the new order key string */
					$key = str_pad($new_search_key . str_pad(strval($tier_counter[$tier]),3,'0',STR_PAD_LEFT), 90, '0', STR_PAD_RIGHT);

					db_execute("update graph_tree_items set order_key='$key' where id=" . $tree_item["id"]);

					$_tier = $tier;
				}
			}
		}
	}
}

?>