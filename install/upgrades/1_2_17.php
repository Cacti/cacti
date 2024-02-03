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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function upgrade_to_1_2_17() {
	// Correct max values in templates and data sources: GAUGE/ABSOLUTE (1,4)
	db_install_execute("ALTER TABLE graph_templates_graph ROW_FORMAT=Dynamic, DROP INDEX title_cache, ADD INDEX title_cache(title_cache)");
	db_install_execute("ALTER TABLE data_template_data ROW_FORMAT=Dynamic, DROP INDEX name_cache, ADD INDEX name_cache(name_cache)");

	// LDAP Search filter widening
	db_install_execute('ALTER TABLE user_domains_ldap MODIFY COLUMN search_filter VARCHAR(512) NOT NULL default ""');

	// Track users and transactions in database session
	if (!db_column_exists('sessions', 'user_id')) {
		db_install_execute('ALTER TABLE sessions
			ADD COLUMN user_id int unsigned NOT NULL default "0",
			ADD COLUMN user_agent varchar(128) NOT NULL default "",
			ADD COLUMN start_time timestamp NOT NULL default current_timestamp,
			ADD COLUMN transactions int unsigned NOT NULL default "1"');
	}

	if (!db_column_exists('host', 'bulk_walk_size')) {
		db_install_execute('ALTER TABLE host
			ADD COLUMN bulk_walk_size INT(11) DEFAULT "-1"
			AFTER max_oids');
	}

	if (!db_column_exists('automation_snmp_items', 'bulk_walk_size')) {
		db_install_execute('ALTER TABLE automation_snmp_items
			ADD COLUMN bulk_walk_size INT(11) DEFAULT "-1"
			AFTER max_oids');
	}

	database_fix_mediumint_columns();

    // Fix any 'Damaged Graph' instances
    db_install_execute("UPDATE graph_local AS gl
        INNER JOIN (
            SELECT DISTINCT local_graph_id, task_item_id
            FROM graph_templates_item
        ) AS gti
        ON gl.id = gti.local_graph_id
        INNER JOIN data_template_rrd AS dtr
        ON gti.task_item_id = dtr.id
        INNER JOIN data_template_data AS dtd
        ON dtr.local_data_id = dtd.local_data_id
        INNER JOIN data_input_fields AS dif
        ON dif.data_input_id = dtd.data_input_id
        INNER JOIN (
			SELECT *
			FROM data_input_data
			WHERE value RLIKE '^([0-9]+)$'
		) AS did
        ON did.data_template_data_id = dtd.id
        AND did.data_input_field_id = dif.id
        INNER JOIN snmp_query_graph_rrd AS sqgr
        ON sqgr.snmp_query_graph_id = did.value
        SET gl.snmp_query_graph_id = did.value
        WHERE input_output = 'in'
        AND type_code = 'output_type'
        AND gl.snmp_query_id > 0
		AND gl.snmp_query_graph_id = 0");
}

function database_fix_mediumint_columns() {
	global $database_default;

	$total = 0;

	// Known Tables
	$tables = array(
		'data_input_data' => 'data_template_data_id',

		'data_template_data' => 'id, local_data_template_data_id, local_data_id',
		'data_template_rrd'  => 'id, local_data_template_rrd_id, local_data_id',

		'graph_local' => 'id',
		'data_local'  => 'id',

		'data_source_purge_action'       => 'local_data_id',
		'data_source_purge_temp'         => 'local_data_id',
		'data_source_stats_daily'        => 'local_data_id',
		'data_source_stats_hourly'       => 'local_data_id',
		'data_source_stats_hourly_cache' => 'local_data_id',
		'data_source_stats_hourly_last'  => 'local_data_id',
		'data_source_stats_monthly'      => 'local_data_id',
		'data_source_stats_weekly'       => 'local_data_id',
		'data_source_stats_yearly'       => 'local_data_id',

		'graph_templates_graph'     => 'id, local_graph_id, local_graph_template_graph_id',
		'graph_template_input_defs' => 'graph_template_item_id',
		'graph_templates_item'      => 'id, local_graph_template_item_id, local_graph_id, task_item_id',
		'graph_tree_items'          => 'local_graph_id',

		'poller_item'            => 'local_data_id',
		'poller_output'          => 'local_data_id',
		'poller_output_boost'    => 'local_data_id',
		'poller_output_realtime' => 'local_data_id',

		'settings_tree'        => 'graph_tree_item_id',
		'snmp_query_graph_rrd' => 'data_template_rrd_id'
	);

	$known_columns['graph_id'] = 'graph_id';
	$known_columns['data_id']  = 'data_id';

	foreach($tables as $table => $columns) {
		$columns = explode(',', $columns);

		$sql = 'ALTER TABLE ' . $table;
		$i = 0;
		foreach($columns as $c) {
			$c = trim($c);

			$attribs = database_get_column_attribs($table, $c);

			if (cacti_sizeof($attribs)) {
				if (strpos($attribs['Type'], 'mediumint') === false) {
					if (strpos($attribs['Type'], 'int(10) unsigned') !== false) {
						continue;
					}
				}

				if (strtolower($attribs['Extra']) == 'auto_increment') {
					$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $c . ' int(10) unsigned NOT NULL AUTO_INCREMENT';
				} else {
					if ($c != 'id') {
						$known_columns[$c] = $c;
					}

					if ($attribs['Default'] != '') {
						$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $c . ' int(10) unsigned NOT NULL default "' . $attribs['Default'] . '"';
					} elseif ($attribs['Null'] == 'NO') {
						$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $c . ' int(10) unsigned NOT NULL';
					} else {
						$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $c . ' int(10) unsigned DEFAULT NULL';
					}
				}

				$i++;
			}
		}

		if ($i > 0) {
			db_install_execute($sql);
			$total++;
		}
	}

	$other_tables = db_fetch_assoc('SHOW TABLES');

	foreach($other_tables as $t) {
		$table   = $t['Tables_in_' . $database_default];
		$columns = array();

		if (!array_key_exists($table, $tables)) {
			$i   = 0;
			$sql = 'ALTER TABLE ' . $table;

			$columns = array_rekey(
				db_fetch_assoc("SHOW COLUMNS FROM " . $table),
					'Field', array('Type', 'Null', 'Key', 'Default', 'Extra')
			);

			foreach($columns as $field => $attribs) {
				if (array_key_exists($field, $known_columns)) {
					if (strpos($attribs['Type'], 'mediumint') === false) {
						if (strpos($attribs['Type'], 'int(10) unsigned') !== false) {
							continue;
						}
					}

					if (strtolower($attribs['Extra']) == 'auto_increment') {
						$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $field . ' int(10) unsigned NOT NULL AUTO_INCREMENT';
					} else {
						if ($attribs['Default'] != '') {
							$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $field . ' int(10) unsigned NOT NULL default "' . $attribs['Default'] . '"';
						} elseif ($attribs['Null'] == 'NO') {
							$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $field . ' int(10) unsigned NOT NULL';
						} else {
							$sql .= ($i == 0 ? '':', ') . ' MODIFY COLUMN ' . $field . ' int(10) unsigned DEFAULT NULL';
						}
					}

					$i++;
				}
			}

			if ($i > 0) {
				db_install_execute($sql);
				$total++;
			}
		}
	}

	return $total;
}

function database_get_column_attribs($table, $column) {
	return db_fetch_row("SHOW COLUMNS FROM $table LIKE '$column'");
}
