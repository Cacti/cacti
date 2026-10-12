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

	/* Seed the stateful poller_data_source_unused cache. The orphan/unused filter in
	 * the poller hot paths (process_poller_output() and poller_boost.php) previously
	 * ran a per-source graph_templates_item lookup every cycle; this table resolves it
	 * once and is then maintained incrementally by the data source API, so the hot
	 * paths only read a tiny table instead of recomputing it per poll. */
	db_install_execute("CREATE TABLE IF NOT EXISTS `poller_data_source_unused` (
		`local_data_id` int(10) unsigned NOT NULL DEFAULT '0',
		`data_source_name` varchar(19) NOT NULL DEFAULT '',
		PRIMARY KEY (`local_data_id`,`data_source_name`))
		ENGINE=InnoDB
		ROW_FORMAT=Dynamic
		COMMENT='Orphaned (not-on-graph) field names for standalone multi-output script data sources'");

	/* Populate through the data source API so the seed and the incremental
	 * maintenance always share one definition of the orphan/unused scope. */
	global $config;
	include_once($config['base_path'] . '/lib/api_data_source.php');

	if (function_exists('api_data_source_cache_unused')) {
		api_data_source_cache_unused();
	}

	/* Index poller_output to match the ordered, keyset drain in process_poller_output()
	 * so the batched fetch scans in index order instead of filesorting each batch. */
	db_install_add_key('poller_output', 'INDEX', 'local_data_id_time', array('local_data_id', 'time', 'rrd_name'), 'BTREE');
}
