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
	 * once and is then maintained by the data source API (api_data_source_cache_unused*),
	 * so the hot paths only read a tiny table instead of recomputing it per poll. The
	 * seed below must stay in sync with api_data_source_cache_unused() in lib/api_data_source.php. */
	db_install_execute("CREATE TABLE IF NOT EXISTS `poller_data_source_unused` (
		`local_data_id` int(10) unsigned NOT NULL DEFAULT '0',
		`data_source_name` varchar(19) NOT NULL DEFAULT '',
		PRIMARY KEY (`local_data_id`,`data_source_name`))
		ENGINE=InnoDB
		ROW_FORMAT=Dynamic
		COMMENT='Orphaned (not-on-graph) field names for standalone multi-output script data sources'");

	/* Only standalone (snmp_query_id = 0), multi-output Script/Script Server sources with
	 * not-on-graph fields can reach the orphan filter; a data query returns one DS per row. */
	$multi_script_input_ids = array();

	foreach (db_fetch_assoc('SELECT di.id
		FROM data_input AS di
		INNER JOIN data_input_fields AS dif
		ON dif.data_input_id = di.id
		WHERE di.type_id IN (' . DATA_INPUT_TYPE_SCRIPT . ', ' . DATA_INPUT_TYPE_PHP_SCRIPT_SERVER . ')
		AND dif.input_output = "out"
		AND dif.update_rra = "on"
		GROUP BY di.id
		HAVING COUNT(*) > 1') as $r) {
		$multi_script_input_ids[] = (int) $r['id'];
	}

	if (cacti_sizeof($multi_script_input_ids)) {
		db_install_execute('INSERT IGNORE INTO poller_data_source_unused (local_data_id, data_source_name)
			SELECT dtr.local_data_id, dtr.data_source_name
			FROM data_template_rrd AS dtr
			INNER JOIN data_local AS dl
			ON dl.id = dtr.local_data_id
			AND dl.snmp_query_id = 0
			INNER JOIN data_template_data AS dtd
			ON dtd.local_data_id = dtr.local_data_id
			AND dtd.data_input_id IN (' . implode(',', $multi_script_input_ids) . ')
			LEFT JOIN graph_templates_item AS gti
			ON gti.task_item_id = dtr.id
			WHERE dtr.data_template_id > 0
			AND dtr.local_data_id > 0
			AND gti.task_item_id IS NULL');
	}

	/* Index poller_output to match the ordered, keyset drain in process_poller_output()
	 * so the batched fetch scans in index order instead of filesorting each batch. */
	db_install_add_key('poller_output', 'INDEX', 'local_data_id_time', array('local_data_id', 'time', 'rrd_name'), 'BTREE');
}
