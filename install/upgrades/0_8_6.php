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

global $config;
include_once(CACTI_PATH_LIBRARY . '/data_query.php');
include_once(CACTI_PATH_LIBRARY . '/import.php');
include_once(CACTI_PATH_LIBRARY . '/poller.php');

function upgrade_to_0_8_6() {
	db_install_drop_table('snmp_query_field');
	db_install_drop_table('data_input_data_cache');
	db_install_drop_table('data_input_data_fcache');

	/* distributed poller support */
	if (!db_table_exists('poller')) {
		db_install_execute("CREATE TABLE `poller` (`id` smallint(5) unsigned NOT NULL auto_increment, `hostname` varchar(250) NOT NULL default '', `ip_address` int(11) unsigned NOT NULL default '0', `last_update` datetime NOT NULL default '0000-00-00 00:00:00', PRIMARY KEY  (`id`))");
	}

	if (!db_table_exists('poller_command')) {
		db_install_execute("CREATE TABLE `poller_command` (`poller_id` smallint(5) unsigned NOT NULL default '0', `time` datetime NOT NULL default '0000-00-00 00:00:00', `action` tinyint(3) unsigned NOT NULL default '0', `command` varchar(200) NOT NULL default '', PRIMARY KEY  (`poller_id`,`action`,`command`))");
	}

	if (!db_table_exists('poller_item')) {
		db_install_execute("CREATE TABLE `poller_item` (`local_data_id` mediumint(8) unsigned NOT NULL default '0', `poller_id` smallint(5) unsigned NOT NULL default '0', `host_id` mediumint(8) NOT NULL default '0', `action` tinyint(2) unsigned NOT NULL default '1', `hostname` varchar(250) NOT NULL default '', `snmp_community` varchar(100) NOT NULL default '', `snmp_version` tinyint(1) unsigned NOT NULL default '0', `snmp_username` varchar(50) NOT NULL default '', `snmp_password` varchar(50) NOT NULL default '', `snmp_port` mediumint(5) unsigned NOT NULL default '161', `snmp_timeout` mediumint(8) unsigned NOT NULL default '0', `rrd_name` varchar(19) NOT NULL default '', `rrd_path` varchar(255) NOT NULL default '', `rrd_num` tinyint(2) unsigned NOT NULL default '0', `arg1` varchar(255) default NULL, `arg2` varchar(255) default NULL, `arg3` varchar(255) default NULL, PRIMARY KEY  (`local_data_id`,`rrd_name`), KEY `local_data_id` (`local_data_id`), KEY `host_id` (`host_id`))");
	}

	if (!db_table_exists('poller_output')) {
		db_install_execute("CREATE TABLE `poller_output` (`local_data_id` mediumint(8) unsigned NOT NULL default '0', `rrd_name` varchar(19) NOT NULL default '', `time` datetime NOT NULL default '0000-00-00 00:00:00', `output` text NOT NULL, PRIMARY KEY  (`local_data_id`,`rrd_name`,`time`))");
	}

	if (!db_table_exists('poller_reindex')) {
		db_install_execute("CREATE TABLE `poller_reindex` (`host_id` mediumint(8) unsigned NOT NULL default '0', `data_query_id` mediumint(8) unsigned NOT NULL default '0', `action` tinyint(3) unsigned NOT NULL default '0', `op` char(1) NOT NULL default '', `assert_value` varchar(100) NOT NULL default '', `arg1` varchar(100) NOT NULL default '', PRIMARY KEY  (`host_id`,`data_query_id`,`arg1`))");
	}

	if (!db_table_exists('poller_time')) {
		db_install_execute("CREATE TABLE `poller_time` (`id` mediumint(8) unsigned NOT NULL auto_increment, `poller_id` smallint(5) unsigned NOT NULL default '0', `start_time` datetime NOT NULL default '0000-00-00 00:00:00', `end_time` datetime NOT NULL default '0000-00-00 00:00:00', PRIMARY KEY  (`id`))");
	}

	db_install_add_column('graph_tree_items', array('name' => 'host_grouping_type', 'type' => 'tinyint(3) unsigned', 'NULL' => false, 'default' => 1));
	db_install_add_column('graph_tree_items', array('name' => 'sort_children_type', 'type' => 'tinyint(3) unsigned', 'NULL' => false, 'default' => 1));
	db_install_add_column('host_snmp_query', array('name' => 'sort_field', 'type' => 'varchar(50)', 'NULL' => false));
	db_install_add_column('host_snmp_query', array('name' => 'title_format', 'type' => 'varchar(50)', 'NULL' => false));
	db_install_add_column('host_snmp_query', array('name' => 'reindex_method', 'type' => 'tinyint(3) unsigned', 'NULL' => false, 'default' => 1));

	if (db_column_exists('graph_tree', 'user_id') &&
	   !db_column_exists('graph_tree', 'sort_type')) {
		db_install_execute("ALTER TABLE `graph_tree` CHANGE `user_id` `sort_type` TINYINT( 3 ) UNSIGNED DEFAULT '1' NOT NULL;");
	}

	if (db_column_exists('graph_tree_items', 'order_key')) {
		db_install_execute("ALTER TABLE `graph_tree_items` CHANGE `order_key` `order_key` VARCHAR( 100 ) DEFAULT '0' NOT NULL;");
	}

	db_install_add_column('host', array('name' => 'status_event_count', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => '0'));
	db_install_add_column('host', array('name' => 'status_fail_date', 'type' => 'datetime', 'NULL' => false, 'default' => '0000-00-00 00:00:00'));
	db_install_add_column('host', array('name' => 'status_rec_date', 'type' => 'datetime', 'NULL' => false, 'default' => '0000-00-00 00:00:00'));
	db_install_add_column('host', array('name' => 'status_last_error', 'type' => 'varchar(50)', 'NULL' => false, 'default' => ''));
	db_install_add_column('host', array('name' => 'min_time', 'type' => 'decimal(7,5)', 'NULL' => false, 'default' => '9.99999'));
	db_install_add_column('host', array('name' => 'max_time', 'type' => 'decimal(7,5)', 'NULL' => false, 'default' => '0.00000'));
	db_install_add_column('host', array('name' => 'cur_time', 'type' => 'decimal(7,5)', 'NULL' => false, 'default' => '0.00000'));
	db_install_add_column('host', array('name' => 'avg_time', 'type' => 'decimal(7,5)', 'NULL' => false, 'default' => '0.00000'));
	db_install_add_column('host', array('name' => 'total_polls', 'type' => 'int(12) unsignd', 'NULL' => false, 'default' => '0'));
	db_install_add_column('host', array('name' => 'failed_polls', 'type' => 'int(12) unsignd', 'NULL' => false, 'default' => '0'));
	db_install_add_column('host', array('name' => 'availability', 'type' => 'decimal(7,5)', 'NULL' => false, 'default' => '100.000'));

	db_install_execute("UPDATE snmp_query_graph_rrd_sv set text = REPLACE(text,' (In)','') where snmp_query_graph_id = 2;");
	db_install_execute("UPDATE graph_tree set sort_type = '1';");

	/* update the sort cache */
	$host_snmp_query_results = db_install_fetch_assoc('select host_id,snmp_query_id from host_snmp_query');
	$host_snmp_query         = $host_snmp_query_results['data'];

	if (cacti_sizeof($host_snmp_query) > 0) {
		foreach ($host_snmp_query as $item) {
			update_data_query_sort_cache($item['host_id'], $item['snmp_query_id']);
			update_reindex_cache($item['host_id'], $item['snmp_query_id']);
		}
	}

	/* script query data input methods */
	$xml_data = '<cacti>
			<hash_030003332111d8b54ac8ce939af87a7eac0c06>
				<name>Get Script Server Data (Indexed)</name>
				<type_id>6</type_id>
				<input_string></input_string>
				<fields>
					<hash_070003172b4b0eacee4948c6479f587b62e512>
						<name>Index Type</name>
						<update_rra></update_rra>
						<regexp_match></regexp_match>
						<allow_nulls></allow_nulls>
						<type_code>index_type</type_code>
						<input_output>in</input_output>
						<data_name>index_type</data_name>
					</hash_070003172b4b0eacee4948c6479f587b62e512>
					<hash_07000330fb5d5bcf3d66bb5abe88596f357c26>
						<name>Index Value</name>
						<update_rra></update_rra>
						<regexp_match></regexp_match>
						<allow_nulls></allow_nulls>
						<type_code>index_value</type_code>
						<input_output>in</input_output>
						<data_name>index_value</data_name>
					</hash_07000330fb5d5bcf3d66bb5abe88596f357c26>
					<hash_07000331112c85ae4ff821d3b288336288818c>
						<name>Output Type ID</name>
						<update_rra></update_rra>
						<regexp_match></regexp_match>
						<allow_nulls></allow_nulls>
						<type_code>output_type</type_code>
						<input_output>in</input_output>
						<data_name>output_type</data_name>
					</hash_07000331112c85ae4ff821d3b288336288818c>
					<hash_0700035be8fa85472d89c621790b43510b5043>
						<name>Output Value</name>
						<update_rra>on</update_rra>
						<regexp_match></regexp_match>
						<allow_nulls></allow_nulls>
						<type_code></type_code>
						<input_output>out</input_output>
						<data_name>output</data_name>
					</hash_0700035be8fa85472d89c621790b43510b5043>
				</fields>
			</hash_030003332111d8b54ac8ce939af87a7eac0c06>
		</cacti>';

	//import_xml_data($xml_data);

	/* update trees to three characters per tier */
	$trees_results = db_install_fetch_assoc('select id from graph_tree');
	$trees         = $trees_results['data'];

	if (cacti_sizeof($trees) > 0) {
		foreach ($trees as $tree) {
			$tree_items_results = db_install_fetch_assoc('select
				graph_tree_items.id,
				graph_tree_items.order_key
				from graph_tree_items
				where graph_tree_items.graph_tree_id=?
				order by graph_tree_items.order_key',
				array($tree['id']));
			$tree_items = $tree_items_results['data'];

			if ($tree_items !== false && cacti_sizeof($tree_items) > 0) {
				$_tier = 0;

				/* only do the upgrade once */
				if ($tree_items[0]['order_key'] == '001000000000000000000000000000000000000000000000000000000000000000000000000000000000000000') {
					return;
				}

				foreach ($tree_items as $tree_item) {
					$tier = tree_tier($tree_item['order_key'], 2);

					/* back off */
					if ($tier < $_tier) {
						for ($i=$_tier; $i > $tier; $i--) {
							$tier_counter[$i] = 0;
						}
					}

					/* we key tier==0 off of '1' and tier>0 off of '0' */
					if (!isset($tier_counter[$tier])) {
						$tier_counter[$tier] = 1;
					} else {
						$tier_counter[$tier]++;
					}

					$search_key = preg_replace('/0+$/', '', $tree_item['order_key']);

					if (strlen($search_key) % 2 != 0) {
						$search_key .= '0';
					}

					$new_search_key = '';

					for ($i=1; $i < $tier; $i++) {
						$new_search_key .= str_pad(strval($tier_counter[$i]),3,'0',STR_PAD_LEFT);
					}

					/* build the new order key string */
					$key = str_pad($new_search_key . str_pad(strval($tier_counter[$tier]),3,'0',STR_PAD_LEFT), 90, '0', STR_PAD_RIGHT);

					db_install_execute('update graph_tree_items set order_key=? where id=?', array($key, $tree_item['id']));

					$_tier = $tier;
				}
			}
		}
	}
}
