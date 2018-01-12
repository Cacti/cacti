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

function getHostTemplates() {
	$tmpArray = db_fetch_assoc('SELECT id, name FROM host_template ORDER BY id');

	$host_templates[0] = 'None';

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $template) {
			$host_templates[$template['id']] = $template['name'];
		}
	}

	return $host_templates;
}

function getHostsByDescription() {
	$hosts = array();
	$tmpArray = db_fetch_assoc('SELECT id, description FROM host ORDER BY description');

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $tmp) {
			$hosts[$tmp['description']] = $tmp['id'];
		}
	}

	return $hosts;
}

function getHosts() {
	$hosts    = array();
	$tmpArray = db_fetch_assoc('SELECT * FROM host ORDER BY id');

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $host) {
			$hosts[$host['id']] = $host;
		}
	}

	return $hosts;
}

function getInputFields($templateId) {
	$fields    = array();

	$tmpArray = db_fetch_assoc_prepared("SELECT DISTINCT dif.data_name AS `name`, dif.name AS `description`,
		did.value AS `default`, dtd.data_template_id, dif.id AS `data_input_field_id`
		FROM data_input_fields AS dif
		INNER JOIN data_input_data AS did
		ON did.data_input_field_id = dif.id
		INNER JOIN data_template_data AS dtd
		ON did.data_template_data_id = dtd.id
		AND dtd.data_input_id = dif.data_input_id
		INNER JOIN data_template_rrd AS dtr
		ON dtr.data_template_id = dtd.data_template_id
		INNER JOIN graph_templates_item AS gti
		ON dtr.id = gti.task_item_id
		INNER JOIN graph_templates AS gt
		ON gt.id = gti.graph_template_id
		WHERE gt.id = ?
		AND dtr.local_data_id = 0
		AND dtd.local_data_id = 0
		AND did.t_value = 'on'
		AND dif.input_output IN ('in', 'inout')",
		array($templateId));

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $row) {
			$fields[$row['data_template_id'] . ':' . $row['name']] = $row;
		}
	}

	return $fields;
}

function getAddresses() {
	$addresses = array();
	$tmpArray  = db_fetch_assoc('SELECT id, hostname FROM host ORDER BY hostname');

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $tmp) {
			$addresses[$tmp['hostname']] = $tmp['id'];
		}
	}

	return $addresses;
}

function getSNMPFields($hostId, $snmp_query_id = '') {
	$fieldNames = array();

	if ($snmp_query_id != '') {
		$sql_where = " AND snmp_query_id=$snmp_query_id";
	} else {
		$sql_where = '';
	}

	$tmpArray   = db_fetch_assoc('SELECT DISTINCT field_name
		FROM host_snmp_cache
		WHERE host_id = ' . $hostId . "
		$sql_where
		ORDER BY field_name");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $f) {
			$fieldNames[$f['field_name']] = 1;
		}
	}

	return $fieldNames;
}

function getSNMPValues($hostId, $field, $snmp_query_id = '') {
	$values   = array();

	if ($snmp_query_id != '') {
		$sql_where = " AND snmp_query_id=$snmp_query_id";
	} else {
		$sql_where = '';
	}

	$tmpArray = db_fetch_assoc('SELECT field_value
		FROM host_snmp_cache
		WHERE host_id=' . $hostId . "
		AND field_name='" . $field . "'
		$sql_where
		ORDER BY field_value");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $v) {
			$values[$v['field_value']] = 1;
		}
	}

	return $values;
}

function getSNMPQueries() {
	$queries  = array();
	$tmpArray = db_fetch_assoc('SELECT id, name FROM snmp_query ORDER by id');

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $q) {
			$queries[$q['id']] = $q['name'];
		}
	}

	return $queries;
}

function getSNMPQueryTypes($snmpQueryId) {
	$types    = array();
	$tmpArray = db_fetch_assoc_prepared('SELECT id, name FROM snmp_query_graph WHERE snmp_query_id = ? ORDER BY id', array($snmpQueryId));

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $type) {
			$types[$type['id']] = $type['name'];
		}
	}

	return $types;
}

function getGraphTemplates() {
	$graph_templates = array();
	$tmpArray        = db_fetch_assoc('SELECT id, name FROM graph_templates ORDER BY id');

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $t) {
			$graph_templates[$t['id']] = $t['name'];
		}
	}

	return $graph_templates;
}

function getGraphTemplatesByHostTemplate($host_template_id) {
	$graph_templates = array();
	$tmpArray 		 = db_fetch_assoc_prepared('SELECT host_template_graph.graph_template_id AS id, graph_templates.name AS name
						FROM host_template_graph
						LEFT JOIN graph_templates
						ON (host_template_graph.graph_template_id = graph_templates.id)
						WHERE host_template_id = ?', array($host_template_id));

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $t) {
			$graph_templates[$t['id']] = $t['name'];
		}
	}

	return $graph_templates;
}

function displayQueryTypes($types, $quietMode = false) {
	if (!$quietMode) {
		echo "Known SNMP Query Types: (id, name)\n";
	}

	foreach ($types as $id => $name) {
		echo $id . "\t" . $name . "\n";
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayHostTemplates($host_templates, $quietMode = false) {
	if (!$quietMode) {
		echo "Valid Device Templates: (id, name)\n";
	}

	if (sizeof($host_templates)) {
		foreach ($host_templates as $id => $name) {
			echo "$id\t$name\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayCommunities($quietMode = false) {
	if (!$quietMode) {
		echo "Known communities are: (community)\n";
	}

	$communities = db_fetch_assoc('SELECT DISTINCT snmp_community FROM host ORDER BY snmp_community');

	if (sizeof($communities)) {
		foreach ($communities as $community) {
			echo $community['snmp_community']."\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displaySNMPFields($fields, $hostId, $quietMode = false) {
	if (!$quietMode) {
		echo "Known SNMP Fields for host-id $hostId: (name)\n";
	}

	foreach ($fields as $field => $values) {
		echo $field . "\n";
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displaySNMPValues($values, $hostId, $field, $quietMode = false) {
	if (!$quietMode) {
		echo "Known values for $field for host $hostId: (name)\n";
	}

	foreach ($values as $value => $foo) {
		echo "$value\n";
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displaySNMPQueries($queries, $quietMode = false) {
	if (!$quietMode) {
		echo "Known SNMP Queries:(id, name)\n";
	}

	foreach ($queries as $id => $name) {
		echo $id . "\t" . $name . "\n";
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayInputFields($input_fields, $quietMode = false) {
	if (!$quietMode) {
		echo "Known Input Fields:(name, default, description)\n";
	}

	if (sizeof($input_fields)) {
	foreach ($input_fields as $row) {
		echo $row['data_template_id'] . ':' . $row['name'] . "\t" . $row['default'] . "\t" . $row['description'] . "\n";
	}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayGraphTemplates($templates, $quietMode = false) {
	if (!$quietMode) {
		echo "Known Graph Templates:(id, name)\n";
	}

	foreach ($templates as $id => $name) {
		echo $id . "\t" . $name . "\n";
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayHosts($hosts, $quietMode = false) {
	if (!$quietMode) {
		echo "Known Devices: (id, hostname, template, description)\n";
	}

	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			echo $host['id'] . "\t" . $host['hostname'] . "\t" . $host['host_template_id'] . "\t" . $host['description'] . "\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayTrees($quietMode = false) {
	global $tree_sort_types;

	if (!$quietMode) {
		echo "Known Trees:\nid\tsort method\t\t\tname\n";
	}

	$trees = db_fetch_assoc('SELECT id, sort_type, name FROM graph_tree ORDER BY id');

	if (sizeof($trees)) {
		foreach ($trees as $tree) {
			echo $tree['id']."\t";
			echo $tree_sort_types[$tree['sort_type']]."\t";
			echo $tree['name']."\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayTreeNodes($tree_id, $nodeType = '', $parentNode = 0, $quietMode = false) {
	global $tree_sort_types, $tree_item_types, $host_group_types;

	if ($parentNode == 0) {
		if (!$quietMode) {
			echo "Known Tree Nodes:\n";
			echo "type\tid\tparentid\ttitle\tattribs\n";
		}
	}

	$parentID = 0;

	$nodes = db_fetch_assoc_prepared('SELECT id, local_graph_id, title,
		host_id, host_grouping_type, sort_children_type
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		ORDER BY position', array($tree_id, $parentNode));

	if (sizeof($nodes)) {
		foreach ($nodes as $node) {
			/* taken from tree.php, funtion item_edit() */
			$current_type = TREE_ITEM_TYPE_HEADER;
			if ($node['local_graph_id'] > 0) { $current_type = TREE_ITEM_TYPE_GRAPH; }
			if ($node['host_id'] > 0) { $current_type = TREE_ITEM_TYPE_HOST; }

			switch ($current_type) {
				case TREE_ITEM_TYPE_HEADER:
					if ($nodeType == '' || $nodeType == 'header') {
						echo $tree_item_types[$current_type]."\t";
						echo $node['id']."\t";
						if ($parentNode == 0) {
							echo "N/A\t";
						} else {
							echo $parentNode . "\t";
						}

						echo $node['title'] . "\t";
						echo $tree_sort_types[$node['sort_children_type']] . "\t";
						echo "\n";
					}

					displayTreeNodes($tree_id, $nodeType, $node['id'], $quietMode);

					break;
				case TREE_ITEM_TYPE_GRAPH:
					if ($nodeType == '' || $nodeType == 'graph') {
						echo $tree_item_types[$current_type] . "\t";
						echo $node['id'] . "\t";
						if ($parentNode == 0) {
							echo "N/A\t";
						} else {
							echo $parentNode . "\t";
						}

						/* fetch the title for that graph */
						$graph_title = db_fetch_cell_prepared('SELECT gtg.title_cache AS name
							FROM graph_templates_graph AS gtg
							WHERE gtg.local_graph_id = ?', array($node['local_graph_id']));

						echo $graph_title . "\t";
						echo "\n";
					}

					break;
				case TREE_ITEM_TYPE_HOST:
					if ($nodeType == '' || $nodeType == 'host') {
						echo $tree_item_types[$current_type] . "\t";
						echo $node['id'] . "\t";
						if ($parentNode == 0) {
							echo "N/A\t";
						} else {
							echo $parentNode . "\t";
						}

						$name = db_fetch_cell_prepared('SELECT hostname FROM host WHERE id = ?', array($node['host_id']));

						echo $name . "\t";
						echo $host_group_types[$node['host_grouping_type']] . "\t";
						echo "\n";
					}
				break;
			}
		}
	}

	if ($parentNode == 0) {
		if (!$quietMode) {
			echo "\n";
		}
	}
}

function displayRRAs($quietMode = false) {
	if (!$quietMode) {
		echo "Known RRAs:\nid\tsteps\trows\tname\n";
	}

	$rras = db_fetch_assoc('SELECT id, name, steps, `rows` FROM data_source_profiles_rra ORDER BY id');

	if (sizeof($rras)) {
		foreach ($rras as $rra) {
			echo $rra['id']."\t";
			echo $rra['steps']."\t";
			echo $rra['rows']."\t";
			echo $rra['name']."\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayHostGraphs($host_id, $quietMode = false) {

	if (!$quietMode) {
		echo "Known Device Graphs: (id, name, template)\n";
	}

	$graphs = db_fetch_assoc_prepared('SELECT
			graph_templates_graph.local_graph_id AS id,
			graph_templates_graph.title_cache AS name,
			graph_templates.name AS template_name
			FROM (graph_local, graph_templates_graph)
			LEFT JOIN graph_templates ON (graph_local.graph_template_id = graph_templates.id)
			WHERE graph_local.id = graph_templates_graph.local_graph_id
			AND graph_local.host_id = ?
			ORDER BY graph_templates_graph.local_graph_id', array($host_id));

	if (sizeof($graphs)) {
		foreach ($graphs as $graph) {
			echo $graph['id'] . "\t";
			echo $graph['name'] . "\t";
			echo $graph['template_name'] . "\t";
			echo "\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayUsers($quietMode = false) {
	if (!$quietMode) {
		echo "Known Users:\nid\tusername\tfull_name\n";
	}

	$groups = db_fetch_assoc('SELECT id, username, full_name FROM user_auth ORDER BY id');

	if (sizeof($groups)) {
		foreach ($groups as $group) {
			echo $group['id']."\t";
			echo $group['username']."\t";
			echo $group['full_name']."\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

