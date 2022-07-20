<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

function upgrade_to_1_2_20() {
	global $config;

	include_once($config['base_path'] . '/lib/data_query.php');

	// Correct bad hostnames and host_id's in the data_input_data table
	$entries = db_fetch_assoc("SELECT did.*, dif.type_code
		FROM data_input_data AS did
		INNER JOIN data_input_fields AS dif
		ON did.data_input_field_id = dif.id
		WHERE data_input_field_id in (
			SELECT id
			FROM data_input_fields
			WHERE type_code != ''
		)
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE local_data_id > 0
			AND data_template_id > 0
		)
		AND type_code in ('host_id', 'hostname')
		AND value = ''");

	if (cacti_sizeof($entries)) {
		foreach($entries as $e) {
			$data_template_data = db_fetch_row_prepared('SELECT *
				FROM data_template_data
				WHERE id = ?',
				array($e['data_template_data_id']));

			if (cacti_sizeof($data_template_data)) {
				$local_data = db_fetch_row_prepared('SELECT *
					FROM data_local
					WHERE id = ?',
					array($data_template_data['local_data_id']));

				if (cacti_sizeof($local_data)) {
					switch($e['type_code']) {
						case 'hostname':
							$hostname = db_fetch_cell_prepared('SELECT hostname
								FROM host
								WHERE id = ?',
								array($local_data['host_id']));

							db_execute_prepared('UPDATE data_input_data
								SET value = ?
								WHERE data_input_field_id = ?
								AND data_template_data_id = ?',
								array($hostname, $e['data_input_field_id'], $e['data_template_data_id']));

							break;
						case 'host_id':
							db_execute_prepared('UPDATE data_input_data
								SET value = ?
								WHERE data_input_field_id = ?
								AND data_template_data_id = ?',
								array($local_data['host_id'], $e['data_input_field_id'], $e['data_template_data_id']));

							break;
					}
				}
			}
		}
	}

	// Correct issues with Cacti Data Template input's
	db_execute("UPDATE data_input_data
		SET t_value = 'on'
		WHERE data_input_field_id IN (
			SELECT id
			FROM data_input_fields
			WHERE type_code IN ('output_type', 'index_type', 'index_value')
		)
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE data_template_id > 0
		)");

	// Host ID should not be checked, but should not be 'on' either
	db_execute("UPDATE data_input_data
		SET t_value = ''
		WHERE data_input_field_id IN (
			SELECT id
			FROM data_input_fields
			WHERE type_code IN ('host_id')
		)
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE data_template_id > 0
		)");

	$broken_data_sources = db_fetch_assoc("SELECT did.*
		FROM data_input_data AS did
		WHERE data_input_field_id IN (
			SELECT id
			FROM data_input_fields
			WHERE type_code in ('index_type', 'index_value', 'output_type_id')
		)
		AND data_template_data_id IN (
			SELECT id
			FROM data_template_data
			WHERE data_template_id > 0
			AND local_data_id > 0
		) AND value = ''");

	if (cacti_sizeof($broken_data_sources)) {
		foreach($broken_data_sources as $ds) {
			$data_template_data = db_fetch_row_prepared('SELECT *
				FROM data_template_data
				WHERE id = ?',
				array($ds['data_template_data_id']));

			$field_data = db_fetch_row_prepared('SELECT *
				FROM data_input_fields
				WHERE id = ?',
				array($ds['data_input_field_id']));

			if (cacti_sizeof($data_template_data)) {
				$local_data_id = $data_template_data['local_data_id'];

				$local_data = db_fetch_row_prepared('SELECT *
					FROM data_local
					WHERE id = ?',
					array($local_data_id));

				if (cacti_sizeof($local_data)) {
					$local_graph_ids = db_fetch_assoc_prepared('SELECT DISTINCT local_graph_id
						FROM data_template_rrd AS dtr
						INNER JOIN graph_templates_item AS gti
						ON dtr.id = gti.task_item_id
						WHERE dtr.local_data_id = ?',
						array($local_data_id));

					if (cacti_sizeof($local_graph_ids)) {
						foreach($local_graph_ids as $id) {
							$local_graph = db_fetch_row_prepared('SELECT *
								FROM graph_local
								WHERE id = ?',
								array($id['local_graph_id']));

							switch($field_data['type_code']) {
								case 'index_type':
									$index_type = get_best_data_query_index_type($local_graph['host_id'], $local_graph['snmp_query_id']);

									db_execute_prepared('UPDATE data_input_data
										SET value = ?
										WHERE data_input_field_id = ?
										AND data_template_data_id = ?',
										array($index_type, $ds['data_input_field_id'], $ds['data_template_data_id']));

									break;
								case 'index_value':
									db_execute_prepared('UPDATE data_input_data
										SET value = ?
										WHERE data_input_field_id = ?
										AND data_template_data_id = ?',
										array($local_graph['snmp_index'], $ds['data_input_field_id'], $ds['data_template_data_id']));

									break;
								case 'output_type_id':
									if ($local_graph['snmp_query_graph_id'] == 0) {
										$local_graph['snmp_query_graph_id'] = db_fetch_cell_prepared('SELECT id
											FROM snmp_query_graph
											WHERE graph_template_id = ?
											AND snmp_query_id = ?',
											array($local_graph['graph_template_id'], $local_graph['snmp_query_id']));
									}

									db_execute_prepared('UPDATE data_input_data
										SET value = ?
										WHERE data_input_field_id = ?
										AND data_template_data_id = ?',
										array($local_graph['snmp_query_graph_id'], $ds['data_input_field_id'], $ds['data_template_data_id']));
									break;
							}
						}
					}
				}
			}
		}
	}
}

