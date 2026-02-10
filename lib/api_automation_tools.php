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
 * Retrieves a list of host templates from the database.
 *
 * @return array An associative array of host templates with template IDs as keys and template names as values.
 */
function getHostTemplates() : array {
	$tmpArray = db_fetch_assoc('SELECT id, name FROM host_template ORDER BY id');

	$host_templates[0] = 'None';

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $template) {
			$host_templates[$template['id']] = $template['name'];
		}
	}

	return $host_templates;
}

/**
 * Retrieves hosts based on their description.
 *
 * @param mixed $hostTemplateIds An array of host template IDs to filter the hosts by, or false to retrieve all hosts.
 *
 * @return mixed - Returns an array of hosts that match the given description, or false on failure.
 */
function getHostsByDescription(mixed $hostTemplateIds = false) : mixed {
	$hosts = [];

	if ($hostTemplateIds !== false) {
		if (!is_array($hostTemplateIds)) {
			$hostTemplateIds = [$hostTemplateIds];
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

/**
 * Retrieves a list of sites.
 *
 * @return array An array containing the list of sites.
 */
function getSites() : array {
	$sites    = [];
	$tmpArray = db_fetch_assoc('SELECT * FROM sites ORDER BY id');

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $site) {
			$sites[$site['id']] = $site;
		}
	}

	return $sites;
}

/**
 * Retrieves a list of hosts.
 *
 * @param mixed $hostTemplateIds An array of host template IDs to filter the hosts by.
 *                               If false, all hosts will be retrieved.
 *
 * @return mixed - Returns an array of hosts if successful, or false on failure.
 */
function getHosts(mixed $hostTemplateIds = false) : mixed {
	$hosts = [];

	if ($hostTemplateIds !== false) {
		if (!is_array($hostTemplateIds)) {
			$hostTemplateIds = [$hostTemplateIds];
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

/**
 * Retrieves the input fields for a given template ID.
 *
 * @param  int   $templateId The ID of the template to retrieve input fields for.
 * @return array An array of input fields associated with the specified template ID.
 */
function getInputFields(int $templateId) : array {
	$fields = [];

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
		[$templateId]);

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $row) {
			$fields[$row['data_template_id'] . ':' . $row['name']] = $row;
		}
	}

	return $fields;
}

/**
 * Retrieves a list of addresses.
 *
 * @return array An array of addresses.
 */
function getAddresses() : array {
	$addresses = [];
	$tmpArray  = db_fetch_assoc('SELECT id, hostname FROM host ORDER BY hostname');

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $tmp) {
			$addresses[$tmp['hostname']] = $tmp['id'];
		}
	}

	return $addresses;
}

/**
 * Retrieves SNMP fields for a given host.
 *
 * @param int $hostId        The ID of the host for which to retrieve SNMP fields.
 * @param int $snmp_query_id The ID of the SNMP query. Default is an empty string.
 *
 * @return array - An array of SNMP fields for the specified host.
 */
function getSNMPFields(int $hostId, int $snmp_query_id = 0) : array {
	$fieldNames = [];
	$params     = [];
	$params[]   = $hostId;

	if ($snmp_query_id > 0) {
		$sql_where = ' AND snmp_query_id = ?';
		$params[]  = $snmp_query_id;
	} else {
		$sql_where = '';
	}

	$tmpArray   = db_fetch_assoc_prepared('SELECT DISTINCT field_name
		FROM host_snmp_cache
		WHERE host_id = ?
		$sql_where
		ORDER BY field_name', $params);

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $f) {
			$fieldNames[$f['field_name']] = 1;
		}
	}

	return $fieldNames;
}

/**
 * Retrieves SNMP values for a given host and field.
 *
 * @param int    $hostId        The ID of the host to query.
 * @param string $field         The specific field to retrieve values for.
 * @param int    $snmp_query_id The ID of the SNMP query to use. Default is an empty string.
 *
 * @return array An array of SNMP values.
 */
function getSNMPValues(int $hostId, string $field, int $snmp_query_id = 0) : array {
	$values   = [];
	$params   = [];
	$params[] = $hostId;
	$params[] = $field;

	if ($snmp_query_id > 0) {
		$sql_where = ' AND snmp_query_id = ?';
		$params[]  = $snmp_query_id;
	} else {
		$sql_where = '';
	}

	$tmpArray = db_fetch_assoc_prepared('SELECT field_value
		FROM host_snmp_cache
		WHERE host_id = ?
		AND field_name = ?
		$sql_where
		ORDER BY field_value', $params);

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $v) {
			$values[$v['field_value']] = 1;
		}
	}

	return $values;
}

/**
 * Retrieves a list of SNMP queries.
 *
 * @return array An array containing SNMP queries.
 */
function getSNMPQueries() : array {
	$queries  = [];
	$tmpArray = db_fetch_assoc('SELECT id, name FROM snmp_query ORDER by id');

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $q) {
			$queries[$q['id']] = $q['name'];
		}
	}

	return $queries;
}

/**
 * Retrieves the SNMP query types for a given SNMP query ID.
 *
 * @param int $snmpQueryId The ID of the SNMP query.
 *
 * @return array An array of SNMP query types.
 */
function getSNMPQueryTypes(int $snmpQueryId) : array {
	$types    = [];

	$tmpArray = db_fetch_assoc_prepared('SELECT id, name
		FROM snmp_query_graph
		WHERE snmp_query_id = ?
		ORDER BY id',
		[$snmpQueryId]);

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $type) {
			$types[$type['id']] = $type['name'];
		}
	}

	return $types;
}

/**
 * Retrieves a list of graph templates.
 *
 * @return array An array of graph templates.
 */
function getGraphTemplates() : array {
	$graph_templates = [];

	$tmpArray = db_fetch_assoc('SELECT id, name FROM graph_templates ORDER BY id');

	if ($tmpArray !== false && cacti_sizeof($tmpArray)) {
		foreach ($tmpArray as $t) {
			$graph_templates[$t['id']] = $t['name'];
		}
	}

	return $graph_templates;
}

/**
 * Retrieves graph templates associated with the given host template IDs.
 *
 * @param mixed $host_template_ids An array of host template IDs to filter the graph templates by.
 *                                 If false, all graph templates will be retrieved.
 *
 * @return mixed - Returns an array of graph templates if found, or false on failure.
 */
function getGraphTemplatesByHostTemplate(mixed $host_template_ids = false) : mixed {
	$graph_templates = [];

	if ($host_template_ids !== false) {
		if (!is_array($host_template_ids)) {
			$host_template_ids = [$host_template_ids];
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

/**
 * Displays the query types.
 *
 * @param mixed $types     An array of query types to display.
 * @param bool  $quietMode Optional. If set to true, suppresses output. Default is false.
 *
 * @return void
 */
function displayQueryTypes(mixed $types, bool $quietMode = false) : void {
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

/**
 * Displays the host templates.
 *
 * @param mixed $host_templates An array of host templates to display.
 * @param bool  $quietMode      Optional. If set to true, suppresses output. Default is false.
 *
 * @return void
 */
function displayHostTemplates(mixed $host_templates, bool $quietMode = false) : void {
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

/**
 * Displays the list of communities.
 *
 * @param bool $quietMode If set to true, suppresses output.
 *
 * @return void
 */
function displayCommunities(bool $quietMode = false) : void {
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

/**
 * Displays SNMP fields for a given host.
 *
 * @param mixed $fields    An array of SNMP fields to display.
 * @param int   $hostId    The ID of the host for which the SNMP fields are displayed.
 * @param bool  $quietMode If true, suppresses output. Default is false.
 *
 * @return void
 */
function displaySNMPFields(mixed $fields, int $hostId, bool $quietMode = false) : void {
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

/**
 * Displays SNMP values for a given host.
 *
 * @param mixed  $values    The SNMP values to display.
 * @param int    $hostId    The ID of the host.
 * @param string $field     The field to display.
 * @param bool   $quietMode If true, suppresses output. Default is false.
 *
 * @return void
 */
function displaySNMPValues(mixed $values, int $hostId, string $field, bool $quietMode = false) : void {
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

/**
 * Displays SNMP queries.
 *
 * @param mixed $queries   An array of SNMP queries to display.
 * @param bool  $quietMode If set to true, suppresses output. Default is false.
 *
 * @return void
 */
function displaySNMPQueries(mixed $queries, bool $quietMode = false) : void {
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

/**
 * Displays input fields.
 *
 * @param mixed $input_fields An array of input fields to be displayed.
 * @param bool  $quietMode    If set to true, suppresses output. Default is false.
 *
 * @return void
 */
function displayInputFields(mixed $input_fields, bool $quietMode = false) : void {
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

/**
 * Displays the provided graph templates.
 *
 * @param mixed $templates An array of graph templates to be displayed.
 * @param bool  $quietMode If set to true, suppresses output. Default is false.
 *
 * @return void
 */
function displayGraphTemplates(mixed $templates, bool $quietMode = false) : void {
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

/**
 * Displays a list of hosts.
 *
 * @param mixed $hosts     An array of host information to be displayed.
 * @param bool  $quietMode If set to true, suppresses output. Default is false.
 *
 * @return void
 */
function displayHosts(mixed $hosts, bool $quietMode = false) : void {
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

/**
 * Displays a list of sites.
 *
 * @param mixed $sites     An array of sites to display.
 * @param bool  $quietMode If true, suppresses output. Default is false.
 *
 * @return void
 */
function displaySites(mixed $sites, bool $quietMode = false) : void {
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

/**
 * Displays the trees.
 *
 * @param bool $quietMode If set to true, the function will operate in quiet mode.
 *
 * @return void
 */
function displayTrees(bool $quietMode = false) : void {
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

/**
 * Displays the tree nodes for a given tree.
 *
 * @param int    $tree_id    The ID of the tree to display nodes for.
 * @param string $nodeType   The type of nodes to display (optional).
 * @param int    $parentNode The ID of the parent node to start displaying from (optional).
 * @param bool   $quietMode  Whether to suppress output (optional).
 *
 * @return void
 */
function displayTreeNodes(int $tree_id, string $nodeType = '', int $parentNode = 0, bool $quietMode = false) : void {
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
		ORDER BY position', [$tree_id, $parentNode]);

	if (cacti_sizeof($nodes)) {
		foreach ($nodes as $node) {
			// taken from tree.php, function item_edit()
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
						print $tree_item_types[$current_type] . "\t";
						print $node['id'] . "\t";

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

						// fetch the title for that graph
						$graph_title = db_fetch_cell_prepared('SELECT gtg.title_cache AS name
							FROM graph_templates_graph AS gtg
							WHERE gtg.local_graph_id = ?', [$node['local_graph_id']]);

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

						$name = db_fetch_cell_prepared('SELECT hostname FROM host WHERE id = ?', [$node['host_id']]);

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

/**
 * Displays the Round-Robin Archives (RRAs).
 *
 * @param bool $quietMode If set to true, suppresses output.
 *
 * @return void
 */
function displayRRAs(bool $quietMode = false) : void {
	if (!$quietMode) {
		print 'Known RRAs: (id, steps, rows, name)' . PHP_EOL;
	}

	$rras = db_fetch_assoc('SELECT id, name, steps, `rows` FROM data_source_profiles_rra ORDER BY id');

	if (cacti_sizeof($rras)) {
		foreach ($rras as $rra) {
			print $rra['id'] . "\t";
			print $rra['steps'] . "\t";
			print $rra['rows'] . "\t";
			print $rra['name'] . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

/**
 * Displays the graphs for a given host.
 *
 * @param int  $host_id   The ID of the host whose graphs are to be displayed.
 * @param bool $quietMode If set to true, suppresses output. Default is false.
 *
 * @return void
 */
function displayHostGraphs(int $host_id, bool $quietMode = false) : void {
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
		[$host_id]);

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

/**
 * Displays the list of users.
 *
 * @param bool $quietMode If set to true, suppresses output.
 *
 * @return void
 */
function displayUsers(bool $quietMode = false) : void {
	if (!$quietMode) {
		print 'Known Users: (id, username, full_name)' . PHP_EOL;
	}

	$groups = db_fetch_assoc('SELECT id, username, full_name
		FROM user_auth
		ORDER BY id');

	if (cacti_sizeof($groups)) {
		foreach ($groups as $group) {
			print $group['id'] . "\t";
			print $group['username'] . "\t";
			print $group['full_name'] . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}
}

/**
 * Displays the list of groups.
 *
 * @param bool $quietMode If set to true, suppresses output.
 *
 * @return void
 */
function displayGroups(bool $quietMode = false) {
	if (!$quietMode) {
		print 'Known Groups: (id, name, description)' . PHP_EOL;
	}

	$groups = db_fetch_assoc('SELECT id, name, description
                FROM user_auth_group
                ORDER BY id');

	if (cacti_sizeof($groups)) {
		foreach ($groups as $group) {
			print $group['id'] . "\t";
			print $group['name'] . "\t";
			print $group['description'] . PHP_EOL;
		}
	}

	if (!$quietMode) {
		print PHP_EOL;
	}

	exit(1);
}
