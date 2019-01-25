<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

function upgrade_to_0_8_6a() {
	/* fix import/export template bug */
	$item = db_fetch_assoc("select id from data_template");
	for ($i=0; $i<cacti_count($item); $i++) {
		db_install_execute("update data_template set hash='" . get_hash_data_template($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");
		$item2 = db_fetch_assoc("select id from data_template_rrd where data_template_id=" . $item[$i]["id"] . " and local_data_id=0");
		for ($j=0; $j<cacti_count($item2); $j++) {
			db_install_execute("update data_template_rrd set hash='" . get_hash_data_template($item2[$j]["id"], "data_template_item") . "' where id=" . $item2[$j]["id"] . ";");
		}
	}

	$item = db_fetch_assoc("select id from graph_templates");
	for ($i=0; $i<cacti_count($item); $i++) {
		db_install_execute("update graph_templates set hash='" . get_hash_graph_template($item[$i]["id"]) . "' where id=" . $item[$i]["id"] . ";");
		$item2 = db_fetch_assoc("select id from graph_templates_item where graph_template_id=" . $item[$i]["id"] . " and local_graph_id=0");
		for ($j=0; $j<cacti_count($item2); $j++) {
			db_install_execute("update graph_templates_item set hash='" . get_hash_graph_template($item2[$j]["id"], "graph_template_item") . "' where id=" . $item2[$j]["id"] . ";");
		}
		$item2 = db_fetch_assoc("select id from graph_template_input where graph_template_id=" . $item[$i]["id"]);
		for ($j=0; $j<cacti_count($item2); $j++) {
			db_install_execute("update graph_template_input set hash='" . get_hash_graph_template($item2[$j]["id"], "graph_template_input") . "' where id=" . $item2[$j]["id"] . ";");
		}
	}

	/* clean up data template item orphans left behind by the graph->graph template bug */
	$graph_templates = db_fetch_assoc("select id from graph_templates");

	if (cacti_sizeof($graph_templates) > 0) {
		foreach ($graph_templates as $graph_template) {
			/* find non-templated graph template items */
			$non_templated_items = array_rekey(db_fetch_assoc("select
				graph_template_input_defs.graph_template_item_id
				from (graph_template_input,graph_template_input_defs)
				where graph_template_input_defs.graph_template_input_id=graph_template_input.id
				and graph_template_input.column_name = 'task_item_id'
				and graph_template_input.graph_template_id = '" . $graph_template["id"] . "'"), "graph_template_item_id", "graph_template_item_id");

			/* find all graph items */
			$graph_template_items = db_fetch_assoc("select
				graph_templates_item.id,
				graph_templates_item.task_item_id
				from graph_templates_item
				where graph_templates_item.graph_template_id = '" . $graph_template["id"] . "'
				and graph_templates_item.local_graph_id = 0");

			if (cacti_sizeof($graph_template_items) > 0) {
				foreach ($graph_template_items as $graph_template_item) {
					if (!isset($non_templated_items[$graph_template_item["id"]])) {
						if ($graph_template_item["task_item_id"] > 0) {
							$dest_dti = db_fetch_row("select local_data_id from data_template_rrd where id = '" . $graph_template_item["task_item_id"] . "'");

							/* it's an orphan! */
							if ((!isset($dest_dti["local_data_id"])) || ($dest_dti["local_data_id"] > 0)) {
								/* clean graph template */
								db_install_execute("update graph_templates_item set task_item_id = 0 where id = '" . $graph_template_item["id"] . "' and local_graph_id = 0 and graph_template_id = '" . $graph_template["id"] . "'");
								/* clean attached graphs */
								db_install_execute("update graph_templates_item set task_item_id = 0 where local_graph_template_item_id = '" . $graph_template_item["id"] . "' and local_graph_id > 0 and graph_template_id = '" . $graph_template["id"] . "'");
							}
						}
					}
				}
			}
		}
	}

	/* make sure the 'host_graph' table is populated (problem from 0.8.4) */
	$hosts = db_fetch_assoc("select id,host_template_id from host where host_template_id > 0");

	if (cacti_sizeof($hosts) > 0) {
		foreach ($hosts as $host) {
			$graph_templates = db_fetch_assoc("select graph_template_id from host_template_graph where host_template_id=" . $host["host_template_id"]);

			if (cacti_sizeof($graph_templates) > 0) {
				foreach ($graph_templates as $graph_template) {
					db_install_execute("replace into host_graph (host_id,graph_template_id) values (" . $host["id"] . "," . $graph_template["graph_template_id"] . ")");
				}
			}
		}
	}
}
