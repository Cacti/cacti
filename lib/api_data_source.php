<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
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

function api_data_source_remove($local_data_id) {
	global $config;

	if (empty($local_data_id)) {
		return;
	}

	$autoclean = read_config_option('rrd_autoclean');
	$acmethod  = read_config_option('rrd_autoclean_method');

	if ($autoclean == 'on') {
		$dsinfo = db_fetch_row_prepared('SELECT local_data_id, data_source_path FROM data_template_data WHERE local_data_id = ?', array($local_data_id));

		if (sizeof($dsinfo)) {
			$filename = str_replace('<path_cacti>/', '', $dsinfo['data_source_path']);
			db_execute_prepared('INSERT INTO data_source_purge_action (local_data_id, name, action) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE action=VALUES(action)', array($local_data_id, $filename, $acmethod));
		}
	}

	$data_template_data_id = db_fetch_cell_prepared('SELECT id FROM data_template_data WHERE local_data_id = ?', array($local_data_id));

	if (!empty($data_template_data_id)) {
		db_execute_prepared('DELETE FROM data_template_data_rra WHERE data_template_data_id = ?', array($data_template_data_id));
		db_execute_prepared('DELETE FROM data_input_data WHERE data_template_data_id = ?', array($data_template_data_id));
	}

	db_execute_prepared('DELETE FROM data_template_data WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM data_template_rrd WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM poller_item WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM data_local WHERE id = ?', array($local_data_id));

	/* dsstats */
	db_execute_prepared('DELETE FROM data_source_stats_daily WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM data_source_stats_hourly WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM data_source_stats_cache WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM data_source_stats_last WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM data_source_stats_monthly WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM data_source_stats_weekly WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM data_source_stats_yearly WHERE local_data_id = ?', array($local_data_id));

	/* boost */
	db_execute_prepared('DELETE FROM poller_output WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM poller_output_boost WHERE local_data_id = ?', array($local_data_id));
}

function api_data_source_remove_multi($local_data_ids) {
	$ids_to_delete     = '';
	$dtd_ids_to_delete = '';
	$i = 0;
	$j = 0;

	$autoclean = read_config_option('rrd_autoclean');
	$acmethod  = read_config_option('rrd_autoclean_method');

	if (sizeof($local_data_ids)) {
		foreach($local_data_ids as $local_data_id) {
			if ($i == 0) {
				$ids_to_delete .= $local_data_id;
			}else{
				$ids_to_delete .= ', ' . $local_data_id;
			}

			$i++;

			if (($i % 1000) == 0) {
				$data_template_data_ids = db_fetch_assoc("SELECT id
					FROM data_template_data
					WHERE local_data_id IN ($ids_to_delete)");

				if ($autoclean == 'on') {
					db_execute("INSERT INTO data_source_purge_action (local_data_id, name, action) 
						SELECT local_data_id, REPLACE(data_source_name, '<path_cacti>/', ''), '$acmethod' 
						FROM data_template_data 
						WHERE local_data_id IN ($ids_to_delete) 
						ON DUPLICATE KEY UPDATE action=VALUES(action)");
				}

				if (sizeof($data_template_data_ids)) {
					foreach($data_template_data_ids as $data_template_data_id) {
						if ($j == 0) {
							$dtd_ids_to_delete .= $data_template_data_id['id'];
						}else{
							$dtd_ids_to_delete .= ', ' . $data_template_data_id['id'];
						}

						$j++;

						if (($j % 1000) == 0) {
							db_execute("DELETE FROM data_template_data_rra WHERE data_template_data_id IN ($dtd_ids_to_delete)");
							db_execute("DELETE FROM data_input_data WHERE data_template_data_id IN ($dtd_ids_to_delete)");

							$dtd_ids_to_delete = '';
							$j = 0;
						}
					}

					if ($j > 0) {
						db_execute("DELETE FROM data_template_data_rra WHERE data_template_data_id IN ($dtd_ids_to_delete)");
						db_execute("DELETE FROM data_input_data WHERE data_template_data_id IN ($dtd_ids_to_delete)");
					}
				}

				db_execute("DELETE FROM data_template_data WHERE local_data_id IN ($ids_to_delete)");
				db_execute("DELETE FROM data_template_rrd WHERE local_data_id IN ($ids_to_delete)");
				db_execute("DELETE FROM poller_item WHERE local_data_id IN ($ids_to_delete)");
				db_execute("DELETE FROM data_local WHERE id IN ($ids_to_delete)");

				/* dsstats */
				db_execute("DELETE FROM data_source_stats_daily WHERE local_data_id IN($ids_to_delete)");
				db_execute("DELETE FROM data_source_stats_hourly WHERE local_data_id IN($ids_to_delete)");
				db_execute("DELETE FROM data_source_stats_cache WHERE local_data_id IN($ids_to_delete)");
				db_execute("DELETE FROM data_source_stats_last WHERE local_data_id IN($ids_to_delete)");
				db_execute("DELETE FROM data_source_stats_monthly WHERE local_data_id IN($ids_to_delete)");
				db_execute("DELETE FROM data_source_stats_weekly WHERE local_data_id IN($ids_to_delete)");
				db_execute("DELETE FROM data_source_stats_yearly WHERE local_data_id IN($ids_to_delete)");

				/* boost */
				db_execute("DELETE FROM poller_output WHERE local_data_id IN ($ids_to_delete)");
				db_execute("DELETE FROM poller_output_boost WHERE local_data_id IN ($ids_to_delete)");

				$i = 0;
				$ids_to_delete = '';
			}
		}
	}

	if ($i > 0) {
		if ($autoclean == 'on') {
			db_execute("INSERT INTO data_source_purge_action (local_data_id, name, action) 
				SELECT local_data_id, REPLACE(data_source_name, '<path_cacti>/', ''), '$acmethod' 
				FROM data_template_data 
				WHERE local_data_id IN ($ids_to_delete) 
				ON DUPLICATE KEY UPDATE action=VALUES(action)");
		}

		db_execute("DELETE FROM data_template_data WHERE local_data_id IN ($ids_to_delete)");
		db_execute("DELETE FROM data_template_rrd WHERE local_data_id IN ($ids_to_delete)");
		db_execute("DELETE FROM poller_item WHERE local_data_id IN ($ids_to_delete)");
		db_execute("DELETE FROM data_local WHERE id IN ($ids_to_delete)");

		/* dsstats */
		db_execute("DELETE FROM data_source_stats_daily WHERE local_data_id IN($ids_to_delete)");
		db_execute("DELETE FROM data_source_stats_hourly WHERE local_data_id IN($ids_to_delete)");
		db_execute("DELETE FROM data_source_stats_cache WHERE local_data_id IN($ids_to_delete)");
		db_execute("DELETE FROM data_source_stats_last WHERE local_data_id IN($ids_to_delete)");
		db_execute("DELETE FROM data_source_stats_monthly WHERE local_data_id IN($ids_to_delete)");
		db_execute("DELETE FROM data_source_stats_weekly WHERE local_data_id IN($ids_to_delete)");
		db_execute("DELETE FROM data_source_stats_yearly WHERE local_data_id IN($ids_to_delete)");

		/* boost */
		db_execute("DELETE FROM poller_output WHERE local_data_id IN ($ids_to_delete)");
		db_execute("DELETE FROM poller_output_boost WHERE local_data_id IN ($ids_to_delete)");
	}
}

function api_data_source_enable($local_data_id) {
	db_execute_prepared("UPDATE data_template_data SET active = 'on' WHERE local_data_id = ?", array($local_data_id));
	update_poller_cache($local_data_id, true);
 }

function api_data_source_disable($local_data_id) {
	db_execute_prepared('DELETE FROM poller_item WHERE local_data_id = ?', array($local_data_id));
	db_execute_prepared("UPDATE data_template_data SET active='' WHERE local_data_id = ?", array($local_data_id));
}

function api_data_source_disable_multi($local_data_ids) {
	/* initialize variables */
	$ids_to_disable = '';
	$i = 0;

	/* build the array */
	if (sizeof($local_data_ids)) {
		foreach($local_data_ids as $local_data_id) {
			if ($i == 0) {
				$ids_to_disable .= $local_data_id;
			}else{
				$ids_to_disable .= ', ' . $local_data_id;
			}

			$i++;

			if (!($i % 1000)) {
				db_execute("DELETE FROM poller_item WHERE local_data_id IN ($ids_to_disable)");
				db_execute("UPDATE data_template_data SET active='' WHERE local_data_id IN ($ids_to_disable)");

				$i = 0;
				$ids_to_disable = '';
			}
		}

		if ($i > 0) {
			db_execute("DELETE FROM poller_item WHERE local_data_id IN ($ids_to_disable)");
			db_execute("UPDATE data_template_data SET active='' WHERE local_data_id IN ($ids_to_disable)");
		}
	}
}

function api_reapply_suggested_data_source_title($local_data_id) {
	global $config;

	$data_template_data_id = db_fetch_cell_prepared('SELECT id FROM data_template_data WHERE local_data_id = ?', array($local_data_id));
	if (empty($data_template_data_id)) {
		return;
	}

	/* require query type data sources only (snmp_query_id > 0) */
	$data_local = db_fetch_row_prepared('SELECT id, host_id, data_template_id, snmp_query_id, snmp_index FROM data_local WHERE snmp_query_id > 0 AND id = ?', array($local_data_id));
	/* if this is not a data query graph, simply return */
	if (!isset($data_local['host_id'])) {
		return;
	}

	$snmp_query_graph_id = db_fetch_cell_prepared("SELECT 
		data_input_data.value FROM data_input_data 
		JOIN data_input_fields ON (data_input_data.data_input_field_id = data_input_fields.id) 
		JOIN data_template_data ON (data_template_data.id = data_input_data.data_template_data_id) 
		WHERE data_input_fields.type_code = 'output_type' 
		AND data_template_data.local_data_id = ?", array($data_local['id']));

	/* no snmp query graph id found */
	if ($snmp_query_graph_id == 0) {
		return;
	}

	$suggested_values = db_fetch_assoc_prepared("SELECT 
		text, 
		field_name 
		FROM snmp_query_graph_rrd_sv 
		WHERE snmp_query_graph_id = ?
		AND data_template_id = ?
		AND field_name = 'name'
		ORDER BY sequence", array($snmp_query_graph_id, $data_local['data_template_id']));

	$suggested_values_data = array();
	if (sizeof($suggested_values) > 0) {
		foreach ($suggested_values as $suggested_value) {
			if(!isset($suggested_values_data{$suggested_value['field_name']})) {
 				$subs_string = substitute_snmp_query_data($suggested_value['text'],$data_local['host_id'],
								$data_local['snmp_query_id'], $data_local['snmp_index'],
								read_config_option('max_data_query_field_length'));
				/* if there are no '|query' characters, all of the substitutions were successful */
				if (!substr_count($subs_string, '|query')) {
					db_execute_prepared('UPDATE data_template_data SET ' . $suggested_value['field_name'] . ' = ? WHERE local_data_id = ?', array($suggested_value['text'], $local_data_id));
					/* once we find a working value for that very field, stop */
					$suggested_values_data{$suggested_value['field_name']} = true;
				}
			}
		}
	}
}
