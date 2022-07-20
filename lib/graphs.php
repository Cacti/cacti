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

function get_graph_template_details($local_graph_id) {
	global $config;

	$graph_local = db_fetch_row_prepared('SELECT gl.*, gt.name AS template_name, sqg.name AS query_name
		FROM graph_local AS gl
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		LEFT JOIN snmp_query_graph AS sqg
		ON sqg.id = gl.snmp_query_graph_id
		WHERE gl.id = ?',
		array($local_graph_id));

	$aggregate = db_fetch_row_prepared('SELECT agt.id, agt.name
		FROM aggregate_graphs AS ag
		LEFT JOIN aggregate_graph_templates AS agt
		ON ag.aggregate_template_id=agt.id
		WHERE local_graph_id = ?',
		array($local_graph_id));

	if (!empty($aggregate)) {
		$url = $config['url_path'] . 'aggregate_graphs.php?action=edit&id=';

		if (!empty($aggregate['id'])) {
			return array(
				'id'                => $local_graph_id,
				'name'              => $aggregate['name'],
				'graph_description' => __('Aggregated Device'),
				'url'               => $url . $local_graph_id,
				'source'            => GRAPH_SOURCE_AGGREGATE,
			);
		} else {
			return array(
				'id'                => $local_graph_id,
				'name'              => __('Not Templated'),
				'graph_description' => __('Not Applicable'),
				'url'               => $url . $local_graph_id,
				'source'            => GRAPH_SOURCE_AGGREGATE,
			);
		}
	} elseif ($graph_local['graph_template_id'] == 0) {
		return array(
			'id'     => $local_graph_id,
			'name'   => __('Not Templated'),
			'url'    => '',
			'source' => GRAPH_SOURCE_PLAIN,
		);
	} elseif ($graph_local['snmp_query_id'] > 0 && $graph_local['snmp_query_graph_id'] > 0) {
		$url = $config['url_path'] . 'data_queries.php' .
			'?action=item_edit' .
			'&id=' . $graph_local['snmp_query_graph_id'] .
			'&snmp_query_id=' . $graph_local['snmp_query_id'];

		return array(
			'id'     => $graph_local['snmp_query_graph_id'],
			'name'   => (!empty($graph_local['query_name']) ? $graph_local['query_name'] : __('Not Found')),
			'url'    => $url,
			'source' => GRAPH_SOURCE_DATA_QUERY
		);
	} elseif ($graph_local['snmp_query_id'] > 0 && $graph_local['snmp_query_graph_id'] == 0) {
		return array(
			'id'     => 0,
			'name'   => __('Damaged Graph'),
			'url'    => '',
			'source' => GRAPH_SOURCE_DATA_QUERY
		);
	} else {
		if (!empty($graph_local['template_name'])) {
			$url = $config['url_path'] . 'graph_templates.php?action=template_edit&id=' . $graph_local['graph_template_id'];

			return array(
				'id'     => $graph_local['graph_template_id'],
				'name'   => $graph_local['template_name'],
				'url'    => $url,
				'source' => GRAPH_SOURCE_TEMPLATE,
			);
		} else {
			return array(
				'id'     => 0,
				'name'   => __('Not Found'),
				'url'    => '',
				'source' => GRAPH_SOURCE_TEMPLATE,
			);
		}
	}
}

