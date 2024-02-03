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

function upgrade_to_1_2_2() {
	db_install_execute('ALTER TABLE poller_time MODIFY COLUMN id bigint(20) unsigned auto_increment');

	// Find aggregates with orphaned items
	$aggregates_results = db_install_fetch_assoc('SELECT local_graph_id FROM aggregate_graphs');
	$aggregates         = array_rekey($aggregates_results['data'], 'local_graph_id', 'local_graph_id');

	if (cacti_sizeof($aggregates)) {
		foreach ($aggregates as $a) {
			$orphans_results = db_fetch_assoc_prepared('SELECT local_data_id, COUNT(DISTINCT local_graph_id) AS graphs
					FROM graph_templates_item AS gti
					INNER JOIN data_template_rrd AS dtr
					ON gti.task_item_id=dtr.id
					WHERE dtr.local_data_id IN (
						SELECT DISTINCT local_data_id
						FROM graph_templates_item AS gti
						INNER JOIN data_template_rrd AS dtr
						ON gti.task_item_id=dtr.id
						WHERE local_data_id > 0
						AND gti.local_graph_id = ?
					)
					GROUP BY dtr.local_data_id
					HAVING graphs = 1',
				array($a));

			$orphans = array_rekey($orphans_results, 'local_data_id', 'local_data_id');

			if (cacti_sizeof($orphans)) {
				cacti_log('Found ' . cacti_sizeof($orphans) . ' orphaned Data Source(s) in Aggregate Graph ' . $a . ' with Local Data IDs of ' . implode(', ', $orphans), false, 'UPGRADE');

				db_execute_prepared('DELETE
					FROM graph_templates_item
					WHERE local_graph_id = ?
					AND task_item_id IN(
						SELECT dtr.id
						FROM data_template_rrd AS dtr
						WHERE local_data_id IN (' . implode(', ', $orphans) . ')
					)',
					array($a));
			}
		}
	}
}
