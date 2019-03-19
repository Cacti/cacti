<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2019 The Cacti Group                                 |
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

function get_graph_template_details($local_graph_id) {
	global $config;
	$graph_local = db_fetch_row_prepared('SELECT id, graph_template_id, snmp_query_id
		FROM graph_local
		WHERE id = ?',
		array($local_graph_id));

	$aggregate = db_fetch_row_prepared('SELECT agt.id, agt.name
				FROM aggregate_graphs AS ag
				LEFT JOIN aggregate_graph_templates AS agt
				ON ag.aggregate_template_id=agt.id
				WHERE local_graph_id = ?',
				array($local_graph_id));

	if (!empty($aggregate)) {
		$url = $config['url_path'] . 'aggregate_graphs.php?action=edit&id=';

		$hasDetail = !empty($aggregate['id']);
		return array(
			'id'                => $local_graph_id,
			'name'              => $hasDetail ? $aggregate['name'] : __('Not Templated'),
			'graph_description' => $hasDetail ? __('Aggregated Device') : __('Not Applicable'),
			'url'               => $url . $local_graph_id,
			'source'            => GRAPH_SOURCE_AGGREGATE,
		);
	} elseif (!cacti_sizeof($graph_local) || $graph_local['graph_template_id'] == 0) {
		return array(
			'id'     => 0,
			'name'   => __('Not Templated'),
			'url'    => '',
			'source' => GRAPH_SOURCE_PLAIN,
		);
	} elseif ($graph_local['snmp_query_id'] > 0) {
		$url = $config['url_path'] . 'data_queries.php?action=item_edit&id=';

		$detail = db_fetch_row_prepared('SELECT sqg.id, sqg.name
			FROM snmp_query_graph AS sqg
			INNER JOIN graph_local AS gl
			ON gl.snmp_query_graph_id=sqg.id
			AND gl.snmp_query_id=sqg.snmp_query_id
			WHERE gl.id = ?',
			array($local_graph_id));

		$hasDetail = (cacti_sizeof($detail));
		return array(
			'id'     => $hasDetail ? $detail['id'] : 0,
			'name'   => $hasDetail ? $detail['name'] : __('Not Found'),
			'url'    => $hasDetail ? ($url . $detail['id']) : '',
			'source' => GRAPH_SOURCE_DATA_QUERY
		);
	} else {
		$url = $config['url_path'] . 'graph_templates.php?action=template_edit&id=';

		$detail = db_fetch_row_prepared('SELECT gt.id, gt.name
			FROM graph_templates AS gt
			INNER JOIN graph_local AS gl
			ON gl.graph_template_id=gt.id
			WHERE gl.id = ?',
			array($local_graph_id));

		$hasDetail = (cacti_sizeof($detail));
		return array(
			'id'   => $hasDetail ? $detail['id'] :0,
			'name' => $hasDetail ? $detail['name'] : __('Not Found'),
			'url'  => $hasDetail ? ($url . $detail['id']) : '',
			'source' => GRAPH_SOURCE_TEMPLATE,
		);
	}
}
