<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
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

function api_graph_remove($local_graph_id) {
	if (empty($local_graph_id)) {
		return;
	}

	db_execute("delete from graph_templates_graph where local_graph_id=$local_graph_id");
	db_execute("delete from graph_templates_item where local_graph_id=$local_graph_id");
	db_execute("delete from graph_tree_items where local_graph_id=$local_graph_id");
	db_execute("delete from graph_local where id=$local_graph_id");
}

function api_graph_remove_multi($local_graph_ids) {
	/* initialize variables */
	$ids_to_delete = "";
	$i = 0;

	/* build the array */
	if (sizeof($local_graph_ids)) {
		foreach($local_graph_ids as $local_graph_id) {
			if ($i == 0) {
				$ids_to_delete .= $local_graph_id;
			}else{
				$ids_to_delete .= ", " . $local_graph_id;
			}

			$i++;

			if (($i % 1000) == 0) {
				db_execute("DELETE FROM graph_templates_graph WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM graph_templates_item WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM graph_tree_items WHERE local_graph_id IN ($ids_to_delete)");
				db_execute("DELETE FROM graph_local WHERE id IN ($ids_to_delete)");

				$i = 0;
				$ids_to_delete = "";
			}
		}

		if ($i > 0) {
			db_execute("DELETE FROM graph_templates_graph WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM graph_templates_item WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM graph_tree_items WHERE local_graph_id IN ($ids_to_delete)");
			db_execute("DELETE FROM graph_local WHERE id IN ($ids_to_delete)");
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
	db_execute("UPDATE graph_templates_graph SET width=" . $graph_width . ", height=" . $graph_height . " WHERE local_graph_id=" . $local_graph_id);
}

/* api_reapply_suggested_graph_title - reapplies the suggested name to a graph title
   @arg $graph_templates_graph_id - the id of the graph to reapply the name to
*/
function api_reapply_suggested_graph_title($local_graph_id) {
	global $config;

	/* get graphs template id */
	$graph_template_id = db_fetch_cell("select graph_template_id from graph_templates_graph where local_graph_id=" . $local_graph_id);

	/* if a non-template graph, simply return */
	if ($graph_template_id == 0) {
		return;
	}

	/* get the host associated with this graph */
	$graph_local = db_fetch_row("select host_id, graph_template_id, snmp_query_id, snmp_index from graph_local where id=" . $local_graph_id);
	$snmp_query_graph_id = db_fetch_cell("select id from snmp_query_graph where graph_template_id=" . $graph_local["graph_template_id"] .
										" and snmp_query_id=" . $graph_local["snmp_query_id"]);

	/* get the suggested values from the suggested values cache */
	$suggested_values = db_fetch_assoc("select text,field_name from snmp_query_graph_sv where snmp_query_graph_id=" . $snmp_query_graph_id . " order by sequence");

	if (sizeof($suggested_values) > 0) {
	foreach ($suggested_values as $suggested_value) {
		/* once we find a match; don't try to find more */
		if (!isset($suggested_values_graph[$graph_template_id]{$suggested_value["field_name"]})) {
			$subs_string = substitute_snmp_query_data($suggested_value["text"], $graph_local["host_id"], $graph_local["snmp_query_id"], $graph_local["snmp_index"], read_config_option("max_data_query_field_length"));
			/* if there are no '|' characters, all of the substitutions were successful */
			if ((!substr_count($subs_string, "|query")) && ($suggested_value["field_name"] == "title")) {
				db_execute("update graph_templates_graph set " . $suggested_value["field_name"] . "='" . $suggested_value["text"] . "' where local_graph_id=" . $local_graph_id);
				/* once we find a working value, stop */
				$suggested_values_graph[$graph_template_id]{$suggested_value["field_name"]} = true;
			}
		}
	}
	}
	/* suggested values: graph */
	if (isset($suggested_values_array[$graph_template_id]["graph_template"])) {
		while (list($field_name, $field_value) = each($suggested_values_array[$graph_template_id]["graph_template"])) {
			db_execute("update graph_templates_graph set $field_name='$field_value' where local_graph_id=" . $local_graph_id);
		}
	}

	/* suggested values: graph item */
	if (isset($suggested_values_array[$graph_template_id]["graph_template_item"])) {
		while (list($graph_template_item_id, $field_array) = each($suggested_values_array[$graph_template_id]["graph_template_item"])) {
			while (list($field_name, $field_value) = each($field_array)) {
				$graph_item_id = db_fetch_cell("select id from graph_templates_item where local_graph_template_item_id=$graph_template_item_id and local_graph_id=" . $local_graph_id);
				db_execute("update graph_templates_item set $field_name='$field_value' where id=$graph_item_id");
			}
		}
	}
}

/* api_get_graphs_from_datasource - get's all graphs related to a data source
   @arg $local_data_id - the id of the data source
   @returns - array($id => $name_cache) returns the graph id's and names of the graphs
  */
function api_get_graphs_from_datasource($local_data_id) {
	return array_rekey(db_fetch_assoc("SELECT DISTINCT graph_templates_graph.local_graph_id AS id,
		graph_templates_graph.title_cache AS name
		FROM (graph_templates_graph
		INNER JOIN graph_templates_item
		ON graph_templates_graph.local_graph_id=graph_templates_item.local_graph_id)
		INNER JOIN data_template_rrd
		ON graph_templates_item.task_item_id=data_template_rrd.id
		WHERE graph_templates_graph.local_graph_id>0
		AND data_template_rrd.local_data_id=$local_data_id"), "id", "name");
}

?>
