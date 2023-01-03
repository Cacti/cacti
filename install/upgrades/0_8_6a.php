<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
	$results = db_install_fetch_assoc('SELECT id FROM data_template');
	$item    = $results['data'];

	if (cacti_sizeof($item)) {
		foreach($item as $i) {
			db_install_execute('UPDATE data_template
				SET hash = ?
				WHERE id = ?',
				array(get_hash_data_template($i['id']), $i['id']));

			$results = db_install_fetch_assoc('SELECT id
				FROM data_template_rrd
				WHERE data_template_id = ?
				AND local_data_id = 0',
				array($i['id']));

			$item2 = $results['data'];

			if (cacti_sizeof($item2)) {
				foreach($item2 as $j) {
					db_install_execute('UPDATE data_template_rrd
						SET hash = ?
						WHERE id = ?',
						array(get_hash_data_template($j['id'], 'data_template_item'), $j['id']));
				}
			}
		}
	}

	$results = db_install_fetch_assoc('SELECT id FROM graph_templates');
	$item    = $results['data'];

	if (cacti_sizeof($item)) {
		foreach($item as $i) {
			db_install_execute('UPDATE graph_templates
				SET hash = ?
				WHERE id = ?',
				array(get_hash_graph_template($i['id']), $i['id']));

			$results = db_install_fetch_assoc('SELECT id
				FROM graph_templates_item
				WHERE graph_template_id = ?
				AND local_graph_id = 0',
				array($i['id']));

			$item2 = $results['data'];

			if (cacti_sizeof($item2)) {
				foreach($item2 as $j) {
					db_install_execute('UPDATE graph_templates_item
						SET hash = ?
						WHERE id = ?',
						array(get_hash_graph_template($j['id'], 'graph_template_item'), $j['id']));
				}
			}

			$results = db_install_fetch_assoc('SELECT id
				FROM graph_template_input
				WHERE graph_template_id = ?',
				array($i['id']));

			$item2 = $results['data'];

			if (cacti_sizeof($item2)) {
				foreach($item2 as $j) {
					db_install_execute('UPDATE graph_template_input
						SET hash = ?
						WHERE id = ?',
						array(get_hash_graph_template($j['id'], 'graph_template_input'), $j['id']));
				}
			}
		}
	}

	/* clean up data template item orphans left behind by the graph->graph template bug */
	$results         = db_install_fetch_assoc('SELECT id FROM graph_templates');
	$graph_templates = $results['data'];

	if (cacti_sizeof($graph_templates)) {
		foreach ($graph_templates as $graph_template) {
			/* find non-templated graph template items */
			$results = db_install_fetch_assoc('SELECT
				graph_template_input_defs.graph_template_item_id
				FROM (graph_template_input,graph_template_input_defs)
				WHERE graph_template_input_defs.graph_template_input_id=graph_template_input.id
				AND graph_template_input.column_name = "task_item_id"
				AND graph_template_input.graph_template_id = ?',
				array($graph_template['id']));

			$non_templated_items = array_rekey($results['data'], 'graph_template_item_id', 'graph_template_item_id');

			/* find all graph items */
			$results = db_install_fetch_assoc('SELECT
				graph_templates_item.id,
				graph_templates_item.task_item_id
				FROM graph_templates_item
				WHERE graph_templates_item.graph_template_id = ?
				AND graph_templates_item.local_graph_id = 0',
				array($graph_template['id']));

			$graph_template_items = $results['data'];

			if (cacti_sizeof($graph_template_items) > 0) {
				foreach ($graph_template_items as $graph_template_item) {
					if (!isset($non_templated_items[$graph_template_item['id']])) {
						if ($graph_template_item['task_item_id'] > 0) {
							$results = db_install_fetch_row('SELECT local_data_id
								FROM data_template_rrd WHERE id = ?',
								array($graph_template_item['task_item_id']));

							$dest_dti = $results['data'];

							/* it's an orphan! */
							if ((!isset($dest_dti['local_data_id'])) || ($dest_dti['local_data_id'] > 0)) {
								/* clean graph template */
								db_install_execute('UPDATE graph_templates_item
									SET task_item_id = 0
									WHERE id = ?
									AND local_graph_id = 0
									AND graph_template_id = ?',
									array($graph_template_item['id'],  $graph_template['id']));

								/* clean attached graphs */
								db_install_execute('UPDATE graph_templates_item
									SET task_item_id = 0
									WHERE local_graph_template_item_id = ?
									AND local_graph_id > 0
									AND graph_template_id = ?',
									array($graph_template_item['id'], $graph_template['id']));
							}
						}
					}
				}
			}
		}
	}

	/* make sure the 'host_graph' table is populated (problem FROM 0.8.4) */
	$results = db_install_fetch_assoc('SELECT id,host_template_id FROM host WHERE host_template_id > 0');
	$hosts   = $results['data'];

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			$results = db_install_fetch_assoc('SELECT graph_template_id
				FROM host_template_graph
				WHERE host_template_id = ?',
				array($host['host_template_id']));

			$graph_templates = $results['data'];

			if (cacti_sizeof($graph_templates)) {
				foreach ($graph_templates as $graph_template) {
					db_install_execute('REPLACE INTO host_graph
						(host_id,graph_template_id)
						VALUES (?,?)',
						array($host['id'], $graph_template['graph_template_id']));
				}
			}
		}
	}
}

