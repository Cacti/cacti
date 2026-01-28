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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Deletes graphs based on the provided local graph IDs and delete type.
 *
 * @param array $local_graph_ids An array of local graph IDs to be deleted.
 * @param int   $delete_type     The type of deletion to perform:
 *                               - 1: Remove graphs only.
 *                               - 2: Remove graphs and all associated data sources.
 * @param bool  $update_totals   Optional. Whether to update object cache totals. Default is true.
 *
 * @return void
 */
function api_delete_graphs(array &$local_graph_ids, int $delete_type, bool $update_totals = true) : void {
	// check for a bad local_graph_id = 0, and remove graphs
	api_graph_remove_bad_graphs($local_graph_ids);

	if (!cacti_sizeof($local_graph_ids)) {
		return;
	}

	if ($update_totals) {
		if ($delete_type == 2) {
			object_cache_get_totals('graph_delete', $local_graph_ids);
		} else {
			object_cache_get_totals('graph_leave', $local_graph_ids);
		}
	}

	api_graph_remove_aggregate_items($local_graph_ids);

	switch ($delete_type) {
		case '2': // delete all data sources referenced by this graph
			$all_data_sources = array_rekey(
				db_fetch_assoc('SELECT DISTINCT dtd.local_data_id
					FROM data_template_data AS dtd
					INNER JOIN data_template_rrd AS dtr
					ON dtd.local_data_id=dtr.local_data_id
					INNER JOIN graph_templates_item AS gti
					ON dtr.id=gti.task_item_id
					WHERE ' . array_to_sql_or($local_graph_ids, 'gti.local_graph_id') . '
					AND gti.local_graph_id NOT IN(SELECT local_graph_id FROM aggregate_graphs)
					AND dtd.local_data_id > 0'),
				'local_data_id', 'local_data_id'
			);

			if (cacti_sizeof($all_data_sources)) {
				$data_sources = array_rekey(
					db_fetch_assoc('SELECT dtd.local_data_id,
						COUNT(DISTINCT gti.local_graph_id) AS graphs
						FROM data_template_data AS dtd
						INNER JOIN data_template_rrd AS dtr
						ON dtd.local_data_id=dtr.local_data_id
						INNER JOIN graph_templates_item AS gti
						ON dtr.id=gti.task_item_id
						WHERE dtd.local_data_id > 0
						AND gti.local_graph_id NOT IN(SELECT local_graph_id FROM aggregate_graphs)
						GROUP BY dtd.local_data_id
						HAVING graphs = 1
						AND ' . array_to_sql_or($all_data_sources, 'local_data_id')),
					'local_data_id', 'local_data_id'
				);

				if (cacti_sizeof($data_sources)) {
					api_data_source_remove_multi($data_sources, false);
				}

				api_graph_remove_multi($local_graph_ids);

				// Remove orphaned data sources
				$data_sources = array_rekey(
					db_fetch_assoc('SELECT DISTINCT dtd.local_data_id
						FROM data_template_data AS dtd
						INNER JOIN data_template_rrd AS dtr
						ON dtd.local_data_id=dtr.local_data_id
						LEFT JOIN graph_templates_item AS gti
						ON dtr.id=gti.task_item_id
						WHERE ' . array_to_sql_or($all_data_sources, 'dtd.local_data_id') . '
						AND gti.local_graph_id IS NULL
						AND gti.local_graph_id NOT IN(SELECT local_graph_id FROM aggregate_graphs)
						AND dtd.local_data_id > 0'),
					'local_data_id', 'local_data_id'
				);

				if (cacti_sizeof($data_sources)) {
					api_data_source_remove_multi($data_sources, false);
				}
			} else {
				api_graph_remove_multi($local_graph_ids);
			}

			break;
		case '1':
			api_graph_remove_multi($local_graph_ids);

			break;
	}

	if ($update_totals) {
		object_cache_update_totals('delete');
	}

	/**
	 * Save the last time a graph was created/updated
	 * for Caching.
	 */
	set_config_option('time_last_change_graph', time());
}

/**
 * Removes a graph and its associated items from the database.
 *
 * @param int $local_graph_id The ID of the local graph to be removed.
 *
 * @return void
 */
function api_graph_remove(int $local_graph_id) : void {
	if (empty($local_graph_id)) {
		$local_graph_ids = [$local_graph_id];
		api_graph_remove_bad_graphs($local_graph_ids);

		return;
	}

	object_cache_get_totals('graph_leave', $local_graph_id);

	api_plugin_hook_function('graphs_remove', [$local_graph_id]);

	api_graph_remove_aggregate_items($local_graph_id);

	db_execute_prepared('DELETE FROM graph_templates_graph WHERE local_graph_id = ?', [$local_graph_id]);
	db_execute_prepared('DELETE FROM graph_templates_item WHERE local_graph_id = ?', [$local_graph_id]);
	db_execute_prepared('DELETE FROM graph_tree_items WHERE local_graph_id = ?', [$local_graph_id]);
	db_execute_prepared('DELETE FROM reports_items WHERE local_graph_id = ?', [$local_graph_id]);
	db_execute_prepared('DELETE FROM graph_local WHERE id = ?', [$local_graph_id]);

	object_cache_update_totals('delete');

	/**
	 * Save the last time a graph was created/updated
	 * for Caching.
	 */
	set_config_option('time_last_change_graph', time());
}

/**
 * Removes bad graphs from the provided list of local graph IDs and deletes
 * corresponding entries from the database.
 *
 * @param array $local_graph_ids An array of local graph IDs to be checked and cleaned.
 *
 * @return void
 */
function api_graph_remove_bad_graphs(array &$local_graph_ids = []) : void {
	if (cacti_sizeof($local_graph_ids)) {
		$bad_graph = array_search(0, $local_graph_ids, true);

		if ($bad_graph !== false) {
			unset($local_graph_ids[$bad_graph]);

			db_execute('DELETE FROM graph_local
				WHERE id = 0');

			db_execute('DELETE FROM graph_templates_graph
				WHERE local_graph_id = 0
				AND graph_template_id = 0');

			db_execute('DELETE FROM graph_templates_item
				WHERE hash = ""
				AND local_graph_id = 0');
		}
	}

	/**
	 * Save the last time a graph was created/updated
	 * for Caching.
	 */
	set_config_option('time_last_change_graph', time());
}

/**
 * Removes aggregate items associated with the given local graph IDs.
 *
 * @param mixed $local_graph_ids An array of local graph IDs or a comma-separated string of local graph IDs.
 *
 * @return void
 */
function api_graph_remove_aggregate_items(mixed $local_graph_ids) : void {
	if (!is_array($local_graph_ids)) {
		$local_graph_ids = explode(',', $local_graph_ids);
	}

	foreach ($local_graph_ids as $lgid) {
		$aggregate_graphs = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT aggregate_graph_id
				FROM aggregate_graphs_items
				WHERE local_graph_id = ?',
				[$lgid]),
			'aggregate_graph_id', 'aggregate_graph_id'
		);

		if (cacti_sizeof($aggregate_graphs)) {
			foreach ($aggregate_graphs as $ag) {
				$lgids = [$lgid];

				api_aggregate_disassociate($ag, $lgids);
			}
		}
	}
}

/**
 * Removes multiple graphs based on their local graph IDs.
 *
 * @param array $local_graph_ids An array of local graph IDs to be removed.
 *
 * @return void
 */
function api_graph_remove_multi(array $local_graph_ids) : void {
	// check for a bad local_graph_id = 0, and remove graphs
	api_graph_remove_bad_graphs($local_graph_ids);

	if (!cacti_sizeof($local_graph_ids)) {
		return;
	}

	// initialize variables
	$ids_to_delete = '';
	$i             = 0;

	// build the array
	if (cacti_sizeof($local_graph_ids)) {
		api_plugin_hook_function('graphs_remove', $local_graph_ids);

		foreach ($local_graph_ids as $local_graph_id) {
			if ($i == 0) {
				$ids_to_delete .= $local_graph_id;
			} else {
				$ids_to_delete .= ', ' . $local_graph_id;
			}

			$i++;

			if (($i % 1000) == 0) {
				api_graph_remove_aggregate_items($ids_to_delete);

				db_execute("DELETE FROM graph_templates_graph WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM graph_templates_item WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM graph_tree_items WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM reports_items WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM graph_local WHERE id IN ($ids_to_delete)");

				$i             = 0;
				$ids_to_delete = '';
			}
		}

		if ($i > 0) {
			api_graph_remove_aggregate_items($ids_to_delete);

			db_execute("DELETE FROM graph_templates_graph WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM graph_templates_item WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM graph_tree_items WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM reports_items WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM graph_local WHERE id IN ($ids_to_delete)");
		}

		/**
		 * Save the last time a graph was created/updated
		 * for Caching.
		 */
		set_config_option('time_last_change_graph', time());
	}
}

/**
 * Resizes the selected graph, overriding the template value
 *
 * @param int $local_graph_id The ID of the local graph to resize.
 * @param int $graph_width    The new width of the graph.
 * @param int $graph_height   The new height of the graph.
 *
 * @return void
 */
function api_resize_graphs(int $local_graph_id, int $graph_width, int $graph_height) : void {
	// get graphs template id
	db_execute_prepared('UPDATE graph_templates_graph
		SET width = ?, height = ?
		WHERE local_graph_id = ?',
		[$graph_width, $graph_height, $local_graph_id]);
}

/**
 * Reapplies the suggested graph title for a given local graph ID.
 *
 * @param int $local_graph_id The ID of the local graph to update.
 *
 * @return bool True if the title was successfully updated, false otherwise.
 */
function api_reapply_suggested_graph_title(int $local_graph_id) : bool {
	// get graphs template id
	$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		[$local_graph_id]);

	// if a non-template graph, simply return
	if ($graph_template_id == 0) {
		return false;
	}

	/* get the host associated with this graph for data queries only
	 * there's no "reapply suggested title" for "simple" graph templates */
	$graph_local = db_fetch_row_prepared('SELECT *
		FROM graph_local
		WHERE id = ?',
		[$local_graph_id]);

	// if this is not a data query graph, simply return
	if (!isset($graph_local['host_id'])) {
		return false;
	}

	// no snmp query graph id found
	if ($graph_local['snmp_query_graph_id'] == 0) {
		return false;
	}

	// get the suggested values from the suggested values cache
	$suggested_values = db_fetch_assoc_prepared("SELECT text, field_name
		FROM snmp_query_graph_sv
		WHERE snmp_query_graph_id = ?
		AND field_name = 'title'
		ORDER BY sequence",
		[$graph_local['snmp_query_graph_id']]);

	$matches = [];

	$suggested_values_graph = [];

	if (cacti_sizeof($suggested_values)) {
		foreach ($suggested_values as $suggested_value) {
			// once we find a match; don't try to find more
			if (!isset($suggested_values_graph[$suggested_value['field_name']])) {
				$max_chars = intval(read_config_option('max_data_query_field_length'));

				if (empty($max_chars)) {
					$max_chars = 40;
				}

				$subs_string = substitute_snmp_query_data($suggested_value['text'], $graph_local['host_id'], $graph_local['snmp_query_id'], $graph_local['snmp_index'], $max_chars);

				// if there are no '|' characters, all of the substitutions were successful
				if (!substr_count($subs_string, '|query')) {
					if (in_array($suggested_value['field_name'], $matches, true)) {
						continue;
					}

					$matches[] = $suggested_value['field_name'];

					db_execute_prepared('UPDATE graph_templates_graph
						SET ' . $suggested_value['field_name'] . ' = ?
						WHERE local_graph_id = ?',
						[$suggested_value['text'], $local_graph_id]);

					// once we find a working value for this very field, stop
					$suggested_values_graph[$suggested_value['field_name']] = true;
				}
			}
		}

		if (cacti_sizeof($matches)) {
			return true;
		}
	}

	return false;
}

/**
 * Retrieves a list of graphs associated with a specific data source.
 *
 * @param int $local_data_id The local data ID of the data source.
 *
 * @return array An associative array of graphs with graph IDs as keys and graph names as values.
 */
function api_get_graphs_from_datasource(int $local_data_id) : array {
	return array_rekey(db_fetch_assoc_prepared('SELECT DISTINCT graph_templates_graph.local_graph_id AS id,
		graph_templates_graph.title_cache AS name
		FROM (graph_templates_graph
		INNER JOIN graph_templates_item
		ON graph_templates_graph.local_graph_id = graph_templates_item.local_graph_id)
		INNER JOIN data_template_rrd
		ON graph_templates_item.task_item_id = data_template_rrd.id
		WHERE graph_templates_graph.local_graph_id > 0
		AND data_template_rrd.local_data_id = ?', [$local_data_id]), 'id', 'name');
}

/**
 * Duplicates a graph or graph template.
 *
 * @param int    $_local_graph_id    The ID of the local graph to duplicate. If provided, the function will duplicate this graph.
 * @param int    $_graph_template_id The ID of the graph template to duplicate. If provided, the function will duplicate this template.
 * @param string $graph_title        The title for the new graph or template. Placeholders in the title will be replaced accordingly.
 * @param bool   $map_to_data_query  Whether to map the duplicated graph template to a data query. Default is true.
 *
 * @return int|false The ID of the newly created local graph or graph template, or false on failure.
 */
function api_duplicate_graph(int $_local_graph_id, int $_graph_template_id, string $graph_title, bool $map_to_data_query = true) : int|false {
	global $struct_graph, $struct_graph_item;

	$local_graph_id        = 0;
	$graph_template_id     = 0;
	$graph_template_inputs = [];
	$graph_item_mappings   = [];
	$graph_template_items  = [];
	$graph_template_graph  = [];

	if (!empty($_local_graph_id)) {
		$graph_local = db_fetch_row_prepared('SELECT *
			FROM graph_local
			WHERE id = ?',
			[$_local_graph_id]);

		if (!cacti_sizeof($graph_local)) {
			return false;
		}

		$graph_template_graph = db_fetch_row_prepared('SELECT *
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
			[$_local_graph_id]);

		$graph_template_items = db_fetch_assoc_prepared('SELECT *
			FROM graph_templates_item
			WHERE local_graph_id = ?',
			[$_local_graph_id]);

		// create new entry: graph_local
		$save       = [];
		$save['id'] = 0;

		$save['graph_template_id'] = $graph_local['graph_template_id'];
		$save['host_id']           = $graph_local['host_id'];
		$save['snmp_query_id']     = $graph_local['snmp_query_id'];
		$save['snmp_index']        = $graph_local['snmp_index'];

		$local_graph_id = sql_save($save, 'graph_local');

		$graph_template_graph['title'] = str_replace('<graph_title>', $graph_template_graph['title'], $graph_title);
	} elseif (!empty($_graph_template_id)) {
		$graph_template        = db_fetch_row_prepared('SELECT *
			FROM graph_templates
			WHERE id = ?',
			[$_graph_template_id]);

		if (!cacti_sizeof($graph_template)) {
			return false;
		}

		$graph_template_graph  = db_fetch_row_prepared('SELECT *
			FROM graph_templates_graph
			WHERE graph_template_id = ?
			AND local_graph_id=0',
			[$_graph_template_id]);

		$graph_template_items  = db_fetch_assoc_prepared('SELECT *
			FROM graph_templates_item
			WHERE graph_template_id = ?
			AND local_graph_id=0',
			[$_graph_template_id]);

		$graph_template_inputs = db_fetch_assoc_prepared('SELECT *
			FROM graph_template_input
			WHERE graph_template_id = ?',
			[$_graph_template_id]);

		// create new entry: graph_templates
		$save       = [];
		$save['id'] = 0;

		$save['hash']     = get_hash_graph_template(0);
		$save['name']     = str_replace('<template_title>', $graph_template['name'], $graph_title);
		$save['multiple'] = $graph_template['multiple'];

		$graph_template_id = sql_save($save, 'graph_templates');
	}

	$save       = [];
	$save['id'] = 0;

	// create new entry: graph_templates_graph
	$save['local_graph_id']                = ($local_graph_id ?? 0);
	$save['local_graph_template_graph_id'] = ($graph_template_graph['local_graph_template_graph_id'] ?? 0);
	$save['graph_template_id']             = (!empty($_local_graph_id) ? $graph_template_graph['graph_template_id'] : $graph_template_id);
	$save['title_cache']                   = $graph_template_graph['title_cache'];

	foreach ($struct_graph as $field => $array) {
		if ($array['method'] == 'spacer') {
			continue;
		}
		$save[$field]        = $graph_template_graph[$field];
		$save['t_' . $field] = $graph_template_graph['t_' . $field];
	}

	$graph_templates_graph_id = sql_save($save, 'graph_templates_graph');

	// create new entry(s): graph_templates_item
	if (cacti_sizeof($graph_template_items)) {
		foreach ($graph_template_items as $graph_template_item) {
			$save       = [];
			$save['id'] = 0;

			// save a hash only for graph_template copy operations
			$save['hash']                         = (!empty($_graph_template_id) ? get_hash_graph_template(0, 'graph_template_item') : 0);
			$save['local_graph_id']               = ($local_graph_id ?? 0);
			$save['graph_template_id']            = (!empty($_local_graph_id) ? $graph_template_item['graph_template_id'] : $graph_template_id);
			$save['local_graph_template_item_id'] = ($graph_template_item['local_graph_template_item_id'] ?? 0);

			foreach ($struct_graph_item as $field => $array) {
				$save[$field] = $graph_template_item[$field];
			}

			$graph_item_mappings[$graph_template_item['id']] = sql_save($save, 'graph_templates_item');
		}
	}

	if (!empty($_graph_template_id)) {
		// create new entry(s): graph_template_input (graph template only)
		if (cacti_sizeof($graph_template_inputs)) {
			foreach ($graph_template_inputs as $graph_template_input) {
				$save       = [];
				$save['id'] = 0;

				$save['graph_template_id'] = $graph_template_id;
				$save['name']              = $graph_template_input['name'];
				$save['description']       = $graph_template_input['description'];
				$save['column_name']       = $graph_template_input['column_name'];
				$save['hash']              = get_hash_graph_template(0, 'graph_template_input');

				$graph_template_input_id   = sql_save($save, 'graph_template_input');

				$graph_template_input_defs = db_fetch_assoc_prepared('SELECT *
					FROM graph_template_input_defs
					WHERE graph_template_input_id = ?',
					[$graph_template_input['id']]);

				// create new entry(s): graph_template_input_defs (graph template only)
				if (cacti_sizeof($graph_template_input_defs)) {
					foreach ($graph_template_input_defs as $graph_template_input_def) {
						db_execute_prepared('INSERT INTO graph_template_input_defs
							(graph_template_input_id, graph_template_item_id)
							VALUES (?, ?)',
							[
								$graph_template_input_id,
								$graph_item_mappings[$graph_template_input_def['graph_template_item_id']]
							]
						);
					}
				}
			}
		}
	}

	if (!empty($_local_graph_id)) {
		update_graph_title_cache($local_graph_id);
	} elseif ($map_to_data_query) {
		// Graph Template, Check for Data Query Associated Graph Template
		$data_query_graphs = db_fetch_assoc_prepared('SELECT *
			FROM snmp_query_graph
			WHERE graph_template_id = ?',
			[$_graph_template_id]);

		if (cacti_sizeof($data_query_graphs)) {
			$name = db_fetch_cell_prepared('SELECT name
				FROM graph_templates
				WHERE id = ?',
				[$graph_template_id]);

			foreach ($data_query_graphs as $dqg) {
				$save       = [];
				$save['id'] = 0;

				$save['hash']              = get_hash_data_query(0, 'data_query_graph');
				$save['snmp_query_id']     = $dqg['snmp_query_id'];
				$save['name']              = $name;
				$save['graph_template_id'] = $graph_template_id;

				$snmp_query_graph_id = sql_save($save, 'snmp_query_graph');

				// map the snmp_query_graph_rrds
				if (!empty($snmp_query_graph_id)) {
					$snmp_query_graph_rrds = db_fetch_assoc_prepared('SELECT *
						FROM snmp_query_graph_rrd
						WHERE snmp_query_graph_id = ?',
						[$dqg['id']]);

					if (cacti_sizeof($snmp_query_graph_rrds)) {
						foreach ($snmp_query_graph_rrds as $sqgr) {
							db_execute_prepared('INSERT INTO snmp_query_graph_rrd
								(snmp_query_graph_id, data_template_id, data_template_rrd_id, snmp_field_name)
								VALUES (?, ?, ?, ?)',
								[
									$snmp_query_graph_id,
									$sqgr['data_template_id'],
									$sqgr['data_template_rrd_id'],
									$sqgr['snmp_field_name']
								]
							);
						}
					}
				}

				// map data source suggested values
				$snames = db_fetch_assoc_prepared('SELECT *
					FROM snmp_query_graph_rrd_sv
					WHERE snmp_query_graph_id = ?',
					[$dqg['id']]);

				if (cacti_sizeof($snames)) {
					foreach ($snames as $sn) {
						$save       = [];
						$save['id'] = 0;

						$save['hash']                = get_hash_data_query(0, 'data_query_sv_data_source');
						$save['snmp_query_graph_id'] = $snmp_query_graph_id;
						$save['data_template_id']    = $sn['data_template_id'];
						$save['sequence']            = $sn['sequence'];
						$save['field_name']          = $sn['field_name'];
						$save['text']                = $sn['text'];

						sql_save($save, 'snmp_query_graph_rrd_sv');
					}
				}

				// map graph suggested values
				$snames = db_fetch_assoc_prepared('SELECT *
					FROM snmp_query_graph_sv
					WHERE snmp_query_graph_id = ?',
					[$dqg['id']]);

				if (cacti_sizeof($snames)) {
					foreach ($snames as $sn) {
						$save       = [];
						$save['id'] = 0;

						$save['hash']                = get_hash_data_query(0, 'data_query_sv_graph');
						$save['snmp_query_graph_id'] = $snmp_query_graph_id;
						$save['sequence']            = $sn['sequence'];
						$save['field_name']          = $sn['field_name'];
						$save['text']                = $sn['text'];

						sql_save($save, 'snmp_query_graph_sv');
					}
				}
			}
		}
	}

	/**
	 * Save the last time a graph was created/updated
	 * for Caching.
	 */
	set_config_option('time_last_change_graph', time());

	if ($_local_graph_id > 0) {
		return $local_graph_id;
	}

	if ($_graph_template_id > 0) {
		return $graph_template_id;
	} else {
		return false;
	}
}

/**
 * Changes the device associated with a given graph.
 *
 * @param int $local_graph_id The ID of the local graph to update.
 * @param int $host_id        The ID of the new host to associate with the graph.
 *
 * @return bool Returns true if the device was successfully changed, false otherwise.
 */
function api_graph_change_device(int $local_graph_id, int $host_id) : bool {
	$dqgraph = db_fetch_cell_prepared('SELECT snmp_query_id
		FROM graph_local
		WHERE id = ?',
		[$local_graph_id]);

	if (empty($dqgraph)) {
		db_execute_prepared('UPDATE graph_local
			SET host_id = ?
			WHERE id = ?',
			[$host_id, $local_graph_id]);

		update_graph_title_cache($local_graph_id);

		// update the data sources as well
		$data_ids = db_fetch_assoc_prepared('SELECT DISTINCT dtr.local_data_id
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id=dtr.id
			WHERE gti.local_graph_id = ?',
			[$local_graph_id]);

		if (cacti_sizeof($data_ids)) {
			foreach ($data_ids as $data_id) {
				db_execute_prepared('UPDATE data_local
					SET host_id = ?
					WHERE id = ?',
					[$host_id, $data_id['local_data_id']]);

				db_execute_prepared('UPDATE poller_item
					SET host_id = ?
					WHERE local_data_id = ?',
					[$host_id, $data_id['local_data_id']]);
			}
		}

		return true;
	}

	return false;
}
