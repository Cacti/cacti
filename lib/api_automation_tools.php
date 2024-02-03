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

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $template) {
			$host_templates[$template['id']] = $template['name'];
		}
	}

	return $host_templates;
}

function getHostsByDescription($hostTemplateIds = false) {
	$hosts = array();

	if ($hostTemplateIds !== false) {
		if (!is_array($hostTemplateIds)) {
			$hostTemplateIds = array($hostTemplateIds);
		}
	}

	if ($hostTemplateIds !== false && cacti_sizeof($hostTemplateIds)) {
		foreach ($hostTemplateIds as $id) {
			if (!is_numeric($id)) {
				return false;
			}
		}

		$sql_where = 'WHERE ht.id IN (' . implode(',', $hostTemplateIds) . ')';
	} else {
		$sql_where = '';
	}

	$tmpArray = db_fetch_assoc("SELECT h.id, h.description
		FROM host AS h
		INNER JOIN host_template AS ht
		ON h.host_template_id = ht.id
		$sql_where
		ORDER BY h.description");

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $tmp) {
			$hosts[$tmp['description']] = $tmp['id'];
		}
	}

	return $hosts;
}

function getSites() {
	$sites    = array();
	$tmpArray = db_fetch_assoc('SELECT * FROM sites ORDER BY id');

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $site) {
			$sites[$site['id']] = $site;
		}
	}

	return $sites;
}

function getHosts($hostTemplateIds = false) {
	$hosts = array();

	if ($hostTemplateIds !== false) {
		if (!is_array($hostTemplateIds)) {
			$hostTemplateIds = array($hostTemplateIds);
		}
	}

	if ($hostTemplateIds !== false && cacti_sizeof($hostTemplateIds)) {
		foreach ($hostTemplateIds as $id) {
			if (!is_numeric($id)) {
				return false;
			}
		}

		$sql_where = 'WHERE ht.id IN (' . implode(',', $hostTemplateIds) . ')';
	} else {
		$sql_where = '';
	}

	$tmpArray = db_fetch_assoc("SELECT h.id, h.hostname, h.description, h.host_template_id
		FROM host AS h
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id
		$sql_where
		ORDER BY h.id");

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $host) {
			$hosts[$host['id']] = $host;
		}
	}

	return $hosts;
}

function getInputFields($templateId) {
	$fields = array();

	$tmpArray = db_fetch_assoc_prepared("SELECT DISTINCT dif.data_name AS `name`, dif.name AS `description`,
		did.value AS `default`, dtd.data_template_id, dif.id AS `data_input_field_id`
		FROM data_input_fields AS dif
		INNER JOIN (
			SELECT data_input_field_id, data_template_data_id, value
			FROM data_input_data
			WHERE t_value = 'on'
		) AS did
		ON did.data_input_field_id = dif.id
		INNER JOIN (
			SELECT id, data_input_id, data_template_id
			FROM data_template_data FORCE INDEX (local_data_id)
			WHERE local_data_id = 0
		) AS dtd
		ON did.data_template_data_id = dtd.id
		AND dtd.data_input_id = dif.data_input_id
		INNER JOIN (
			SELECT data_template_id, id
			FROM data_template_rrd
			WHERE local_data_id = 0 AND hash != ''
		) AS dtr
		ON dtr.data_template_id = dtd.data_template_id
		INNER JOIN graph_templates_item AS gti
		ON dtr.id = gti.task_item_id
		INNER JOIN graph_templates AS gt
		ON gt.id = gti.graph_template_id
		WHERE gt.id = ?
		AND dif.input_output IN ('in', 'inout')",
		array($templateId));

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $row) {
			$fields[$row['data_template_id'] . ':' . $row['name']] = $row;
		}
	}

	return $fields;
}

function getAddresses() {
	$addresses = array();
	$tmpArray  = db_fetch_assoc('SELECT id, hostname FROM host ORDER BY hostname');

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
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

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
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

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $v) {
			$values[$v['field_value']] = 1;
		}
	}

	return $values;
}

function getSNMPQueries() {
	$queries  = array();
	$tmpArray = db_fetch_assoc('SELECT id, name FROM snmp_query ORDER by id');

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $q) {
			$queries[$q['id']] = $q['name'];
		}
	}

	return $queries;
}

function getSNMPQueryTypes($snmpQueryId) {
	$types    = array();

	$tmpArray = db_fetch_assoc_prepared('SELECT id, name
		FROM snmp_query_graph
		WHERE snmp_query_id = ?
		ORDER BY id',
		array($snmpQueryId));

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $type) {
			$types[$type['id']] = $type['name'];
		}
	}

	return $types;
}

function getGraphTemplates() {
	$graph_templates = array();

	$tmpArray = db_fetch_assoc('SELECT id, name FROM graph_templates ORDER BY id');

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $t) {
			$graph_templates[$t['id']] = $t['name'];
		}
	}

	return $graph_templates;
}

function getGraphTemplatesByHostTemplate($host_template_ids = false) {
	$graph_templates = array();

	if ($host_template_ids !== false) {
		if (!is_array($host_template_ids)) {
			$host_template_ids = array($host_template_ids);
		}
	}

	if ($host_template_ids !== false && cacti_sizeof($host_template_ids)) {
		foreach ($host_template_ids as $id) {
			if (!is_numeric($id)) {
				return false;
			}
		}

		$sql_where = 'WHERE htg.host_template_id IN (' . implode(',', $host_template_ids) . ')';
	} else {
		$sql_where = '';
	}

	$tmpArray = db_fetch_assoc_prepared("SELECT htg.graph_template_id AS id, gt.name AS name
		FROM host_template_graph AS htg
		LEFT JOIN graph_templates AS gt
		ON htg.graph_template_id = gt.id
		$sql_where
		ORDER by gt.name ASC");

	if (cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $t) {
			$graph_templates[$t['id']] = $t['name'];
		}
	}

	return $graph_templates;
}

function displayQueryTypes($types, $quietMode = false) {
	if (!$quietMode) {
		print 'Known SNMP Query Types: (id, name)' . PHP_EOL;
	}

	if ($types !== false && cacti_sizeof($types)) {
		foreach ($types as $id => $name) {
			print $id . "\t" . $name . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displayHostTemplates($host_templates, $quietMode = false) {
	if (!$quietMode) {
		print 'Valid Device Templates: (id, name)' . PHP_EOL;
	}

	if ($host_templates !== false && cacti_sizeof($host_templates)) {
		foreach ($host_templates as $id => $name) {
			print "$id\t$name" . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displayCommunities($quietMode = false) {
	if (!$quietMode) {
		print 'Known SNMP Communities: (community)' . PHP_EOL;
	}

	$communities = db_fetch_assoc('SELECT DISTINCT snmp_community
		FROM host
		ORDER BY snmp_community');

	if ($communities !== false && cacti_sizeof($communities)) {
		foreach ($communities as $community) {
			print $community['snmp_community'] . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displaySNMPFields($fields, $hostId, $quietMode = false) {
	if (!$quietMode) {
		print 'Known SNMP Fields for host-id ' . $hostId . ': (name)' . PHP_EOL;
	}

	if ($fields !== false && cacti_sizeof($fields)) {
		foreach ($fields as $field => $values) {
			print $field . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displaySNMPValues($values, $hostId, $field, $quietMode = false) {
	if (!$quietMode) {
		print 'Known SNMP Values for Field ' . $field . ' and host-id ' . $hostId . ': (name)' . PHP_EOL;
	}

	if ($values !== false && cacti_sizeof($values)) {
		foreach ($values as $value => $foo) {
			print $value . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displaySNMPQueries($queries, $quietMode = false) {
	if (!$quietMode) {
		print 'Known SNMP Queries: (id, name)' . PHP_EOL;
	}

	if ($queries !== false && cacti_sizeof($queries)) {
		foreach ($queries as $id => $name) {
			print $id . "\t" . $name . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displayInputFields($input_fields, $quietMode = false) {
	if (!$quietMode) {
		print 'Known Input Fields: (name, default, description)' . PHP_EOL;
	}

	if ($input_fields !== false && cacti_sizeof($input_fields)) {
		foreach ($input_fields as $row) {
			print $row['data_template_id'] . ':' . $row['name'] . "\t" . $row['default'] . "\t" . $row['description'] . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displayGraphTemplates($templates, $quietMode = false) {
	if (!$quietMode) {
		print 'Known Graph Templates: (id, name)' . PHP_EOL;
	}

	if ($templates !== false && cacti_sizeof($templates)) {
		foreach ($templates as $id => $name) {
			print $id . "\t" . $name . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displayHosts($hosts, $quietMode = false) {
	if (!$quietMode) {
		print 'Known Devices: (id, hostname, template, description)' . PHP_EOL;
	}

	if ($hosts !== false && cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			print $host['id'] . "\t" . $host['hostname'] . "\t" . $host['host_template_id'] . "\t" . $host['description'] . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displaySites($sites, $quietMode = false) {
	if (!$quietMode) {
		print 'Known Sites: (id, name)' . PHP_EOL;
	}

	if ($sites !== false && cacti_sizeof($sites)) {
		foreach ($sites as $site) {
			print $site['id'] . "\t" . $site['name'] . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displayTrees($quietMode = false) {
	global $tree_sort_types;

	if (!$quietMode) {
		print 'Known Trees: (id, sort method, name)' . PHP_EOL;
	}

	$trees = db_fetch_assoc('SELECT id, sort_type, name
		FROM graph_tree
		ORDER BY id');

	if (cacti_sizeof($trees)) {
		foreach ($trees as $tree) {
			print $tree['id'] . "\t";
			print $tree_sort_types[$tree['sort_type']] . "\t";
			print $tree['name'] . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displayTreeNodes($tree_id, $nodeType = '', $parentNode = 0, $quietMode = false) {
	global $tree_sort_types, $tree_item_types, $host_group_types;

	if ($parentNode == 0) {
		if (!$quietMode) {
			print 'Known Tree Nodes: (type, id, parentid, title, attribs)' . PHP_EOL;
		}
	}

	$parentID = 0;

	$nodes = db_fetch_assoc_prepared('SELECT id, local_graph_id, title,
		host_id, host_grouping_type, sort_children_type
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		ORDER BY position', array($tree_id, $parentNode));

	if (cacti_sizeof($nodes)) {
		foreach ($nodes as $node) {
			/* taken from tree.php, function item_edit() */
			$current_type = TREE_ITEM_TYPE_HEADER;

			if ($node['local_graph_id'] > 0) {
				$current_type = TREE_ITEM_TYPE_GRAPH;
			}

			if ($node['host_id'] > 0) {
				$current_type = TREE_ITEM_TYPE_HOST;
			}

			switch ($current_type) {
				case TREE_ITEM_TYPE_HEADER:
					if ($nodeType == '' || $nodeType == 'header') {
						print $tree_item_types[$current_type]."\t";
						print $node['id']."\t";

						if ($parentNode == 0) {
							print "N/A\t";
						} else {
							print $parentNode . "\t";
						}

						print $node['title'] . "\t";
						print $tree_sort_types[$node['sort_children_type']] . "\t";
						print PHP_EOL;
					}

					displayTreeNodes($tree_id, $nodeType, $node['id'], $quietMode);

					break;
				case TREE_ITEM_TYPE_GRAPH:
					if ($nodeType == '' || $nodeType == 'graph') {
						print $tree_item_types[$current_type] . "\t";
						print $node['id'] . "\t";

						if ($parentNode == 0) {
							print "N/A\t";
						} else {
							print $parentNode . "\t";
						}

						/* fetch the title for that graph */
						$graph_title = db_fetch_cell_prepared('SELECT gtg.title_cache AS name
							FROM graph_templates_graph AS gtg
							WHERE gtg.local_graph_id = ?', array($node['local_graph_id']));

						print $graph_title . "\t";
						print PHP_EOL;
					}

					break;
				case TREE_ITEM_TYPE_HOST:
					if ($nodeType == '' || $nodeType == 'host') {
						print $tree_item_types[$current_type] . "\t";
						print $node['id'] . "\t";

						if ($parentNode == 0) {
							print "N/A\t";
						} else {
							print $parentNode . "\t";
						}

						$name = db_fetch_cell_prepared('SELECT hostname FROM host WHERE id = ?', array($node['host_id']));

						print $name . "\t";
						print $host_group_types[$node['host_grouping_type']] . "\t";
						print PHP_EOL;
					}

					break;
			}
		}
	}

	if ($parentNode == 0) {
		if (!$quietMode) {
			print PHP_EOL;
		}
	}
}

function displayRRAs($quietMode = false) {
	if (!$quietMode) {
		print 'Known RRAs: (id, steps, rows, name)' . PHP_EOL;
	}

	$rras = db_fetch_assoc('SELECT id, name, steps, `rows` FROM data_source_profiles_rra ORDER BY id');

	if (cacti_sizeof($rras)) {
		foreach ($rras as $rra) {
			print $rra['id']    . "\t";
			print $rra['steps'] . "\t";
			print $rra['rows']  . "\t";
			print $rra['name']  . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displayHostGraphs($host_id, $quietMode = false) {
	if (!$quietMode) {
		print 'Known Device Graphs: (id, name, template)' . PHP_EOL;
	}

	$graphs = db_fetch_assoc_prepared('SELECT
		graph_templates_graph.local_graph_id AS id,
		graph_templates_graph.title_cache AS name,
		graph_templates.name AS template_name
		FROM (graph_local, graph_templates_graph)
		LEFT JOIN graph_templates ON (graph_local.graph_template_id = graph_templates.id)
		WHERE graph_local.id = graph_templates_graph.local_graph_id
		AND graph_local.host_id = ?
		ORDER BY graph_templates_graph.local_graph_id',
		array($host_id));

	if (cacti_sizeof($graphs)) {
		foreach ($graphs as $graph) {
			print $graph['id'] . "\t";
			print $graph['name'] . "\t";
			print $graph['template_name'] . "\t";
			print PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

function displayUsers($quietMode = false) {
	if (!$quietMode) {
		print 'Known Users: (id, username, full_name)'. PHP_EOL;
	}

	$groups = db_fetch_assoc('SELECT id, username, full_name
		FROM user_auth
		ORDER BY id');

	if (cacti_sizeof($groups)) {
		foreach ($groups as $group) {
			print $group['id']        ."\t";
			print $group['username']  ."\t";
			print $group['full_name'] . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}
