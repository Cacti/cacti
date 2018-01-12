<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

function api_graph_remove($local_graph_id) {
	if (empty($local_graph_id)) {
		return;
	}

	db_execute_prepared('DELETE FROM graph_templates_graph WHERE local_graph_id = ?', array($local_graph_id));
	db_execute_prepared('DELETE FROM graph_templates_item WHERE local_graph_id = ?', array($local_graph_id));
	db_execute_prepared('DELETE FROM graph_tree_items WHERE local_graph_id = ?', array($local_graph_id));
	db_execute_prepared('DELETE FROM reports_items WHERE local_graph_id = ?', array($local_graph_id));
	db_execute_prepared('DELETE FROM graph_local WHERE id = ?', array($local_graph_id));
	db_execute_prepared('DELETE FROM aggregate_graphs_items WHERE local_graph_id = ?', array($local_graph_id));
}

function api_graph_remove_multi($local_graph_ids) {
	/* initialize variables */
	$ids_to_delete = '';
	$i = 0;

	/* build the array */
	if (sizeof($local_graph_ids)) {
		foreach($local_graph_ids as $local_graph_id) {
			if ($i == 0) {
				$ids_to_delete .= $local_graph_id;
			} else {
				$ids_to_delete .= ', ' . $local_graph_id;
			}

			$i++;

			if (($i % 1000) == 0) {
				db_execute("DELETE FROM graph_templates_graph WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM graph_templates_item WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM graph_tree_items WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM reports_items WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM graph_local WHERE id IN ($ids_to_delete)");
				db_execute("DELETE FROM aggregate_graphs_items WHERE local_graph_id IN ($ids_to_delete)");

				$i = 0;
				$ids_to_delete = '';
			}
		}

		if ($i > 0) {
			db_execute("DELETE FROM graph_templates_graph WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM graph_templates_item WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM graph_tree_items WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM reports_items WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM graph_local WHERE id IN ($ids_to_delete)");
			db_execute("DELETE FROM aggregate_graphs_items WHERE local_graph_id IN ($ids_to_delete)");
		}
	}
}

/* api_resize_graphs - resizes the selected graph, overriding the template value
   @arg $graph_templates_graph_id - the id of the graph to resize
   @arg $graph_width - the width of the resized graph
   @arg $graph_height - the height of the resized graph
  */
function api_resize_graphs($local_graph_id, $graph_width, $graph_height) {
	global $config;

	/* get graphs template id */
	db_execute_prepared('UPDATE graph_templates_graph SET width = ?, height = ? WHERE local_graph_id = ?', array($graph_width, $graph_height, $local_graph_id));
}

/* api_reapply_suggested_graph_title - reapplies the suggested name to a graph title
   @param int $graph_templates_graph_id - the id of the graph to reapply the name to
*/
function api_reapply_suggested_graph_title($local_graph_id) {
	global $config;

	/* get graphs template id */
	$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id FROM graph_templates_graph WHERE local_graph_id = ?', array($local_graph_id));

	/* if a non-template graph, simply return */
	if ($graph_template_id == 0) {
		return;
	}

	/* get the host associated with this graph for data queries only
	 * there's no "reapply suggested title" for "simple" graph templates */
	$graph_local = db_fetch_row_prepared('SELECT host_id, graph_template_id, snmp_query_id, snmp_index FROM graph_local WHERE snmp_query_id>0 AND id = ?', array($local_graph_id));
	/* if this is not a data query graph, simply return */
	if (!isset($graph_local['host_id'])) {
		return;
	}
	/* get data source associated with the graph */
	$data_local = db_fetch_cell_prepared('SELECT 
		data_template_data.local_data_id
		FROM (data_template_rrd,data_template_data,graph_templates_item)
		WHERE graph_templates_item.task_item_id=data_template_rrd.id
		AND data_template_rrd.local_data_id=data_template_data.local_data_id
		AND graph_templates_item.local_graph_id = ?
		GROUP BY data_template_data.local_data_id', array($local_graph_id));
	
	$snmp_query_graph_id = db_fetch_cell_prepared("SELECT 
		data_input_data.value from data_input_data 
		JOIN data_input_fields ON (data_input_data.data_input_field_id=data_input_fields.id) 
		JOIN data_template_data ON (data_template_data.id = data_input_data.data_template_data_id) 
		WHERE data_input_fields.type_code = 'output_type' 
		AND data_template_data.local_data_id = ?", array($data_local));

	/* no snmp query graph id found */
	if ($snmp_query_graph_id == 0) {
		return;
	}

	/* get the suggested values from the suggested values cache */
	$suggested_values = db_fetch_assoc_prepared("SELECT 
		text, 
		field_name 
		FROM snmp_query_graph_sv 
		WHERE snmp_query_graph_id = ?
		AND field_name = 'title'
		ORDER BY sequence", array($snmp_query_graph_id));

	$suggested_values_graph = array();
	if (sizeof($suggested_values) > 0) {
		foreach ($suggested_values as $suggested_value) {
			/* once we find a match; don't try to find more */
			if (!isset($suggested_values_graph[$suggested_value['field_name']])) {
				$subs_string = substitute_snmp_query_data($suggested_value['text'], $graph_local['host_id'], $graph_local['snmp_query_id'], $graph_local['snmp_index'], read_config_option('max_data_query_field_length'));
				/* if there are no '|' characters, all of the substitutions were successful */
				if ((!substr_count($subs_string, '|query'))) {
					db_execute_prepared('UPDATE graph_templates_graph SET ' . $suggested_value['field_name'] . ' = ? WHERE local_graph_id = ?', array($suggested_value['text'], $local_graph_id));
					/* once we find a working value for this very field, stop */
					$suggested_values_graph[$suggested_value['field_name']] = true;
				}
			}
		}
	}
}

/* api_get_graphs_from_datasource - get's all graphs related to a data source
   @arg $local_data_id - the id of the data source
   @returns - array($id => $name_cache) returns the graph id's and names of the graphs
  */
function api_get_graphs_from_datasource($local_data_id) {
	return array_rekey(db_fetch_assoc_prepared('SELECT DISTINCT graph_templates_graph.local_graph_id AS id,
		graph_templates_graph.title_cache AS name
		FROM (graph_templates_graph
		INNER JOIN graph_templates_item
		ON graph_templates_graph.local_graph_id = graph_templates_item.local_graph_id)
		INNER JOIN data_template_rrd
		ON graph_templates_item.task_item_id = data_template_rrd.id
		WHERE graph_templates_graph.local_graph_id > 0
		AND data_template_rrd.local_data_id = ?', array($local_data_id)), 'id', 'name');
}

function api_duplicate_graph($_local_graph_id, $_graph_template_id, $graph_title) {
	global $struct_graph, $struct_graph_item;

	if (!empty($_local_graph_id)) {
		$graph_local          = db_fetch_row_prepared('SELECT * FROM graph_local WHERE id = ?', array($_local_graph_id));
		$graph_template_graph = db_fetch_row_prepared('SELECT * FROM graph_templates_graph WHERE local_graph_id = ?', array($_local_graph_id));
		$graph_template_items = db_fetch_assoc_prepared('SELECT * FROM graph_templates_item WHERE local_graph_id = ?', array($_local_graph_id));

		/* create new entry: graph_local */
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
			array($_graph_template_id));

		$graph_template_graph  = db_fetch_row_prepared('SELECT * 
			FROM graph_templates_graph 
			WHERE graph_template_id = ? 
			AND local_graph_id=0', 
			array($_graph_template_id));

		$graph_template_items  = db_fetch_assoc_prepared('SELECT * 
			FROM graph_templates_item 
			WHERE graph_template_id = ? 
			AND local_graph_id=0', 
			array($_graph_template_id));

		$graph_template_inputs = db_fetch_assoc_prepared('SELECT * 
			FROM graph_template_input 
			WHERE graph_template_id = ?', 
			array($_graph_template_id));

		/* create new entry: graph_templates */
		$save['id']       = 0;
		$save['hash']     = get_hash_graph_template(0);
		$save['name']     = str_replace('<template_title>', $graph_template['name'], $graph_title);
		$save['multiple'] = $graph_template['multiple'];

		$graph_template_id = sql_save($save, 'graph_templates');
	}

	unset($save);

	/* create new entry: graph_templates_graph */
	$save['id']                            = 0;
	$save['local_graph_id']                = (isset($local_graph_id) ? $local_graph_id : 0);
	$save['local_graph_template_graph_id'] = (isset($graph_template_graph['local_graph_template_graph_id']) ? $graph_template_graph['local_graph_template_graph_id'] : 0);
	$save['graph_template_id']             = (!empty($_local_graph_id) ? $graph_template_graph['graph_template_id'] : $graph_template_id);
	$save['title_cache']                   = $graph_template_graph['title_cache'];

	foreach ($struct_graph as $field => $array) {
		if ($array['method'] == 'spacer') continue;
		$save[$field] = $graph_template_graph[$field];
		$save['t_' . $field] = $graph_template_graph['t_' . $field];
	}

	$graph_templates_graph_id = sql_save($save, 'graph_templates_graph');

	/* create new entry(s): graph_templates_item */
	if (sizeof($graph_template_items)) {
		foreach ($graph_template_items as $graph_template_item) {
			unset($save);

			$save['id']                           = 0;
			/* save a hash only for graph_template copy operations */
			$save['hash']                         = (!empty($_graph_template_id) ? get_hash_graph_template(0, 'graph_template_item') : 0);
			$save['local_graph_id']               = (isset($local_graph_id) ? $local_graph_id : 0);
			$save['graph_template_id']            = (!empty($_local_graph_id) ? $graph_template_item['graph_template_id'] : $graph_template_id);
			$save['local_graph_template_item_id'] = (isset($graph_template_item['local_graph_template_item_id']) ? $graph_template_item['local_graph_template_item_id'] : 0);

			foreach ($struct_graph_item as $field => $array) {
				$save[$field] = $graph_template_item[$field];
			}

			$graph_item_mappings[$graph_template_item['id']] = sql_save($save, 'graph_templates_item');
		}
	}

	if (!empty($_graph_template_id)) {
		/* create new entry(s): graph_template_input (graph template only) */
		if (sizeof($graph_template_inputs)) {
			foreach ($graph_template_inputs as $graph_template_input) {
				unset($save);

				$save['id']                = 0;
				$save['graph_template_id'] = $graph_template_id;
				$save['name']              = $graph_template_input['name'];
				$save['description']       = $graph_template_input['description'];
				$save['column_name']       = $graph_template_input['column_name'];
				$save['hash']              = get_hash_graph_template(0, 'graph_template_input');

				$graph_template_input_id   = sql_save($save, 'graph_template_input');

				$graph_template_input_defs = db_fetch_assoc_prepared('SELECT * FROM graph_template_input_defs WHERE graph_template_input_id = ?', array($graph_template_input['id']));

				/* create new entry(s): graph_template_input_defs (graph template only) */
				if (sizeof($graph_template_input_defs)) {
					foreach ($graph_template_input_defs as $graph_template_input_def) {
						db_execute_prepared('INSERT INTO graph_template_input_defs 
							(graph_template_input_id, graph_template_item_id)
							VALUES (?, ?)', 
							array($graph_template_input_id, $graph_item_mappings[$graph_template_input_def['graph_template_item_id']]));
					}
				}
			}
		}
	}

	if (!empty($_local_graph_id)) {
		update_graph_title_cache($local_graph_id);
	}
}

function api_graph_change_device($local_graph_id, $host_id) {
	$dqgraph = db_fetch_cell_prepared('SELECT snmp_query_id 
		FROM graph_local 
		WHERE id = ?', 
		array($local_graph_id));

	if (empty($dqgraph)) {
		db_execute_prepared('UPDATE graph_local
			SET host_id = ?
			WHERE id = ?',
			array($host_id, $local_graph_id));

		update_graph_title_cache($local_graph_id);

		/* update the data sources as well */
		$data_ids = db_fetch_assoc_prepared('SELECT DISTINCT dtr.local_data_id
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id=dtr.id
			WHERE gti.local_graph_id = ?',
			array($local_graph_id));

		if (sizeof($data_ids)) {
			foreach($data_ids as $data_id) {
				db_execute_prepared('UPDATE data_local 
					SET host_id = ? 
					WHERE id = ?', 
					array($host_id, $data_id['local_data_id']));

				db_execute_prepared('UPDATE poller_item 
					SET host_id = ? 
					WHERE local_data_id = ?', 
					array($host_id, $data_id['local_data_id']));
			}
		}

		return true;
	}

	return false;
}

