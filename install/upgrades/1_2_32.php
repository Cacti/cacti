<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

/**
 * Handles the upgrade to 1.2.32. Used as part of Cacti's install functionality.
 *
 * @return void No value is returned.
 */
function upgrade_to_1_2_32() {
	/* #7728 landed these columns in cacti.sql and in upgrade_to_1_2_31() after
	 * 1.2.31 had already shipped. Installs sitting on 1.2.31 never re-run that
	 * file. db_install_add_column() is a no-op when the column exists, so
	 * 1.2.30 -> 1.2.32 is safe. */
	if (!db_table_exists('poller_output_boost_processes')) {
		db_install_execute("CREATE TABLE IF NOT EXISTS `poller_output_boost_processes` (
			`sock_int_value` bigint(20) unsigned NOT NULL auto_increment,
			`status` varchar(255) default NULL,
			PRIMARY KEY (`sock_int_value`))
			ENGINE=MEMORY");
	} else {
		db_install_execute('TRUNCATE TABLE poller_output_boost_processes');
	}

	db_install_add_column('poller_output_boost_processes', array('name' => 'run_id', 'type' => 'char(32)', 'NULL' => false, 'default' => '', 'after' => 'sock_int_value'));
	db_install_add_column('poller_output_boost_processes', array('name' => 'child_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'run_id'));
	db_install_add_key('poller_output_boost_processes', 'UNIQUE', 'run_child', array('run_id', 'child_id'));

	/* the database connection of each registered process, so a process killed for running too
	 * long does not leave its statement running on the server */
	db_install_add_column('processes', array('name' => 'connection_id', 'type' => 'bigint(20)', 'unsigned' => true, 'NULL' => false, 'default' => '0', 'after' => 'timeout'));

	upgrade_1_2_32_boost_tables();
}

/**
 * Keep the migration and the repair of an already-stamped release on the same
 * cacti.sql definitions. Silent repair avoids writing an unrelated upgrade log.
 *
 * @param bool $log Record statements in the current upgrade log.
 * @return bool True when both tables are available.
 */
function upgrade_1_2_32_boost_tables($log = true) {
	$local_ids = db_install_execute("CREATE TABLE IF NOT EXISTS `poller_output_boost_local_data_ids` (
		`run_id` char(32) NOT NULL default '',
		`local_data_id` int(10) unsigned NOT NULL default '0',
		`process_handler` int(10) unsigned NOT NULL default '0',
		`range_handler` bigint(20) unsigned NOT NULL default '0',
		`archive_rows` bigint(20) unsigned NOT NULL default '0',
		PRIMARY KEY (`run_id`, `local_data_id`),
		KEY `process_handler` (`run_id`, `process_handler`, `range_handler`, `local_data_id`))
		ENGINE=InnoDB ROW_FORMAT=Dynamic", array(), $log);

	$failures = db_install_execute("CREATE TABLE IF NOT EXISTS `poller_output_boost_failures` (
		`local_data_id` int(10) unsigned NOT NULL,
		`generation` char(32) NOT NULL default '',
		`rrd_path` varchar(255) NOT NULL default '',
		`rrd_template` text NOT NULL,
		`update_values` mediumtext NOT NULL,
		`latest_sample` bigint(20) unsigned NOT NULL default '0',
		`sample_count` int(10) unsigned NOT NULL default '0',
		`retry_count` smallint(5) unsigned NOT NULL default '0',
		`first_failed` timestamp NOT NULL default CURRENT_TIMESTAMP,
		`last_failed` timestamp NOT NULL default CURRENT_TIMESTAMP,
		`next_retry` timestamp NOT NULL default CURRENT_TIMESTAMP,
		`last_error` varchar(512) NOT NULL default '',
		PRIMARY KEY (`local_data_id`),
		KEY `next_retry` (`next_retry`))
		ENGINE=InnoDB ROW_FORMAT=Dynamic", array(), $log);

	return $local_ids != DB_STATUS_ERROR && $failures != DB_STATUS_ERROR;
}
