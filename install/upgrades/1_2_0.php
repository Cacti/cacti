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
	if (!db_column_exists('user_domains_ldap', 'cn_full_name')) {
		db_install_execute("ALTER TABLE `user_domains_ldap`
			ADD `cn_full_name` VARCHAR(50) NULL DEFAULT '',
			ADD `cn_email` VARCHAR(50) NULL DEFAULT ''");
	}

	if (!db_column_exists('poller', 'max_time')) {
		db_install_execute("ALTER TABLE poller
			ADD COLUMN max_time DOUBLE DEFAULT NULL AFTER total_time,
			ADD COLUMN min_time DOUBLE DEFAULT NULL AFTER max_time,
			ADD COLUMN avg_time DOUBLE DEFAULT NULL AFTER min_time,
			ADD COLUMN total_polls INT unsigned DEFAULT '0' AFTER avg_time,
			ADD COLUMN processes INT unsigned DEFAULT '1' AFTER total_polls,
			ADD COLUMN threads INT unsigned DEFAULT '1' AFTER processes");

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

		db_install_execute("UPDATE TABLE poller SET processes = $max_processes, threads = $max_threads");
	}

	if (!db_column_exists('host', 'location')) {
		db_install_execute("ALTER TABLE host
			ADD COLUMN location VARCHAR(40) DEFAULT NULL AFTER hostname,
			ADD INDEX site_id_location(site_id, location)");
	}

	if (!db_column_exists('poller', 'timezone')) {
		db_install_execute("ALTER TABLE poller
			ADD COLUMN `timezone` varchar(40) DEFAULT '' AFTER `status`");
	}

	if (!db_column_exists('poller_resource_cache', 'attributes')) {
		db_install_execute("ALTER TABLE poller_resource_cache
			ADD COLUMN `attributes` INT unsigned DEFAULT '0'");
	}

	if (!db_column_exists('external_links', 'refresh')) {
		db_install_execute("ALTER TABLE external_links
			ADD COLUMN `refresh` int unsigned default NULL");
	}

	if (!db_column_exists('automation_networks', 'same_sysname')) {
		db_install_execute("ALTER TABLE automation_networks
			ADD COLUMN `same_sysname` char(2) DEFAULT '' AFTER `add_to_cacti`");
	}

  db_install_execute("ALTER TABLE graph_tree_items
		MODIFY COLUMN sort_children_type tinyint(3) unsigned NOT NULL DEFAULT '0'");

	db_install_execute('UPDATE graph_templates_graph
		SET t_title="" WHERE t_title IS NULL or t_title="0"');

	db_install_execute('UPDATE settings
		SET name="log_validation" WHERE name="developer_mode"');
}
