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

function api_tree_item_save($id, $tree_id, $type, $parent_tree_item_id,
	$title, $local_graph_id, $rra_id, $host_id, $host_grouping_type,
	$sort_children_type, $propagate_changes) {

	global $config;

	include_once($config["library_path"] . "/tree.php");

	$parent_order_key = db_fetch_cell("select order_key from graph_tree_items where id=$parent_tree_item_id");

	/* fetch some cache variables */
	if (empty($id)) {
		/* new/save - generate new order key */
		$order_key = get_next_tree_id($parent_order_key, "graph_tree_items", "order_key", "graph_tree_id=$tree_id");
	}else{
		/* edit/save - use old order_key */
		$order_key = db_fetch_cell("select order_key from graph_tree_items where id=$id");
	}

	/* duplicate graph check */
	$search_key = substr($parent_order_key, 0, (tree_tier($parent_order_key) * CHARS_PER_TIER));
	if (($type == TREE_ITEM_TYPE_GRAPH) && (sizeof(db_fetch_assoc("select id from graph_tree_items where local_graph_id='$local_graph_id' and graph_tree_id='$tree_id' and order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'")) > 0)) {
		return db_fetch_cell("select id from graph_tree_items where local_graph_id='$local_graph_id' and graph_tree_id='$tree_id' and order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'");
	}

	/* Duplicate header check */
	if (($type == TREE_ITEM_TYPE_HEADER)) {
		if ((sizeof(db_fetch_assoc("select id from graph_tree_items where title='$title' and graph_tree_id='$tree_id' and order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'")) > 0)) {
			return db_fetch_cell("select id from graph_tree_items where title='$title' and graph_tree_id='$tree_id' and order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'");
		}
	}

	$save["id"]                 = $id;
	$save["graph_tree_id"]      = $tree_id;
	$save["title"]              = form_input_validate($title, "title", "", ($type == TREE_ITEM_TYPE_HEADER ? false : true), 3);
	$save["order_key"]          = $order_key;
	$save["local_graph_id"]     = form_input_validate($local_graph_id, "local_graph_id", "", true, 3);
	$save["rra_id"]	            = form_input_validate($rra_id, "rra_id", "", true, 3);
	$save["host_id"]            = form_input_validate($host_id, "host_id", "", true, 3);
	$save["host_grouping_type"] = form_input_validate($host_grouping_type, "host_grouping_type", "", true, 3);
	$save["sort_children_type"] = form_input_validate($sort_children_type, "sort_children_type", "", true, 3);

	$tree_item_id = 0;

	if (!is_error_message()) {
		$tree_item_id = sql_save($save, "graph_tree_items");

		if ($tree_item_id) {
			raise_message(1);

			/* re-parent the branch if the parent item has changed */
			if ($parent_tree_item_id != $tree_item_id) {
				reparent_branch($parent_tree_item_id, $tree_item_id);
			}

			$tree_sort_type = db_fetch_cell("select sort_type from graph_tree where id='$tree_id'");

			/* tree item ordering */
			if ($tree_sort_type == TREE_ORDERING_NONE) {
				/* resort our parent */
				$parent_sorting_type = db_fetch_cell("select sort_children_type from graph_tree_items where id=$parent_tree_item_id");

				if ((!empty($parent_tree_item_id)) && ($parent_sorting_type != TREE_ORDERING_NONE)) {
					sort_tree(SORT_TYPE_TREE_ITEM, $parent_tree_item_id, $parent_sorting_type);
				}

				/* if this is a header, sort direct children */
				if (($type == TREE_ITEM_TYPE_HEADER) && ($sort_children_type != TREE_ORDERING_NONE)) {
					sort_tree(SORT_TYPE_TREE_ITEM, $tree_item_id, $sort_children_type);
				}
				/* tree ordering */
			}else{
				/* potential speed savings for large trees */
				if (tree_tier($save["order_key"]) == 1) {
					sort_tree(SORT_TYPE_TREE, $tree_id, $tree_sort_type);
				}else{
					sort_tree(SORT_TYPE_TREE_ITEM, $parent_tree_item_id, $tree_sort_type);
				}
			}

			/* if the user checked the 'Propagate Changes' box */
			if (($type == TREE_ITEM_TYPE_HEADER) && ($propagate_changes == true)) {
				$search_key = preg_replace("/0+$/", "", $order_key);

				$tree_items = db_fetch_assoc("select
					graph_tree_items.id
					from graph_tree_items
					where graph_tree_items.host_id = 0
					and graph_tree_items.local_graph_id = 0
					and graph_tree_items.title != ''
					and graph_tree_items.order_key like '$search_key%%'
					and graph_tree_items.graph_tree_id='$tree_id'");

				if (sizeof($tree_items) > 0) {
					foreach ($tree_items as $item) {
						db_execute("update graph_tree_items set sort_children_type = '$sort_children_type' where id = '" . $item["id"] . "'");

						if ($sort_children_type != TREE_ORDERING_NONE) {
							sort_tree(SORT_TYPE_TREE_ITEM, $item["id"], $sort_children_type);
						}
					}
				}
			}
		}else{
			raise_message(2);
		}
	}

	return $tree_item_id;
}

function getHostTemplates() {
	$tmpArray = db_fetch_assoc("select id, name from host_template order by id");

	$host_templates[0] = "None";

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $template) {
			$host_templates[$template["id"]] = $template["name"];
		}
	}

	return $host_templates;
}

function getHostsByDescription() {
	$hosts = array();
	$tmpArray = db_fetch_assoc("select id, description from host order by description");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $tmp) {
			$hosts[$tmp["description"]] = $tmp["id"];
		}
	}

	return $hosts;
}

function getHosts() {
	$hosts    = array();
	$tmpArray = db_fetch_assoc("select * from host order by id");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $host) {
			$hosts[$host["id"]] = $host;
		}
	}

	return $hosts;
}

function getInputFields($templateId) {
	$fields    = array();

	$tmpArray = db_fetch_assoc("SELECT DISTINCT
		data_input_fields.data_name AS `name`,
		data_input_fields.name AS `description`,
		data_input_data.value AS `default`,
		data_template_data.data_template_id,
		data_input_fields.id AS `data_input_field_id`
		FROM data_input_data
		INNER JOIN (((data_template_rrd
		INNER JOIN (graph_templates
		INNER JOIN graph_templates_item
		ON graph_templates.id=graph_templates_item.graph_template_id)
		ON data_template_rrd.id=graph_templates_item.task_item_id)
		INNER JOIN data_template_data
		ON data_template_rrd.data_template_id=data_template_data.data_template_id)
		INNER JOIN data_input_fields
		ON data_template_data.data_input_id=data_input_fields.data_input_id)
		ON (data_input_data.data_template_data_id=data_template_data.id)
		AND (data_input_data.data_input_field_id=data_input_fields.id)
		WHERE (((graph_templates.id)=$templateId)
		AND (data_template_rrd.local_data_id=0)
		AND (data_template_data.local_data_id=0)
		AND ((data_input_data.t_value)='on')
		AND ((data_input_fields.input_output)='in'))");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $row) {
			$fields[$row["data_template_id"] . ":" . $row["name"]] = $row;
		}
	}

	return $fields;
}

function getAddresses() {
	$addresses = array();
	$tmpArray  = db_fetch_assoc("SELECT id, hostname FROM host ORDER BY hostname");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $tmp) {
			$addresses[$tmp["hostname"]] = $tmp["id"];
		}
	}

	return $addresses;
}

function getSNMPFields($hostId, $snmp_query_id = "") {
	$fieldNames = array();

	if ($snmp_query_id != "") {
		$sql_where = " AND snmp_query_id=$snmp_query_id";
	}else{
		$sql_where = "";
	}

	$tmpArray   = db_fetch_assoc("SELECT DISTINCT field_name
		FROM host_snmp_cache
		WHERE host_id = " . $hostId . "
		$sql_where
		ORDER BY field_name");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $f) {
			$fieldNames[$f["field_name"]] = 1;
		}
	}

	return $fieldNames;
}

function getSNMPValues($hostId, $field, $snmp_query_id = "") {
	$values   = array();

	if ($snmp_query_id != "") {
		$sql_where = " AND snmp_query_id=$snmp_query_id";
	}else{
		$sql_where = "";
	}

	$tmpArray = db_fetch_assoc("SELECT field_value
		FROM host_snmp_cache
		WHERE host_id=" . $hostId . "
		AND field_name='" . $field . "'
		$sql_where
		ORDER BY field_value");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $v) {
			$values[$v["field_value"]] = 1;
		}
	}

	return $values;
}

function getSNMPQueries() {
	$queries  = array();
	$tmpArray = db_fetch_assoc("SELECT id, name
		FROM snmp_query
		ORDER by id");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $q) {
			$queries[$q["id"]] = $q["name"];
		}
	}

	return $queries;
}

function getSNMPQueryTypes($snmpQueryId) {
	$types    = array();
	$tmpArray = db_fetch_assoc("SELECT id, name
		FROM snmp_query_graph
		WHERE snmp_query_id = " . $snmpQueryId . "
		ORDER BY id");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $type) {
			$types[$type["id"]] = $type["name"];
		}
	}

	return $types;
}

function getGraphTemplates() {
	$graph_templates = array();
	$tmpArray        = db_fetch_assoc("SELECT id, name
		FROM graph_templates
		ORDER BY id");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $t) {
			$graph_templates[$t["id"]] = $t["name"];
		}
	}

	return $graph_templates;
}

function getGraphTemplatesByHostTemplate($host_template_id) {
	$graph_templates = array();
	$tmpArray 		 = db_fetch_assoc("SELECT " .
										"host_template_graph.graph_template_id AS id, " .
										"graph_templates.name AS name " .
									"FROM host_template_graph " .
									"LEFT JOIN graph_templates " .
										"ON (host_template_graph.graph_template_id = graph_templates.id) " .
									"WHERE host_template_id = $host_template_id");

	if (sizeof($tmpArray)) {
		foreach ($tmpArray as $t) {
			$graph_templates[$t["id"]] = $t["name"];
		}
	}

	return $graph_templates;
}

function displayQueryTypes($types, $quietMode = FALSE) {
	if (!$quietMode) {
		echo "Known SNMP Query Types: (id, name)\n";
	}

	while (list($id, $name) = each ($types)) {
		echo $id . "\t" . $name . "\n";
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayHostTemplates($host_templates, $quietMode = FALSE) {
	if (!$quietMode) {
		echo "Valid Host Templates: (id, name)\n";
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

function displayCommunities($quietMode = FALSE) {
	if (!$quietMode) {
		echo "Known communities are: (community)\n";
	}

	$communities = db_fetch_assoc("SELECT DISTINCT
		snmp_community
		FROM host
		ORDER BY snmp_community");

	if (sizeof($communities)) {
		foreach ($communities as $community) {
			echo $community["snmp_community"]."\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displaySNMPFields($fields, $hostId, $quietMode = FALSE) {
	if (!$quietMode) {
		echo "Known SNMP Fields for host-id $hostId: (name)\n";
	}

	while (list($field, $values) = each ($fields)) {
		echo $field . "\n";
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displaySNMPValues($values, $hostId, $field, $quietMode = FALSE) {
	if (!$quietMode) {
		echo "Known values for $field for host $hostId: (name)\n";
	}

	while (list($value, $foo) = each($values)) {
		echo "$value\n";
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displaySNMPQueries($queries, $quietMode = FALSE) {
	if (!$quietMode) {
		echo "Known SNMP Queries:(id, name)\n";
	}

	while (list($id, $name) = each ($queries)) {
		echo $id . "\t" . $name . "\n";
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayInputFields($input_fields, $quietMode = FALSE) {
	if (!$quietMode) {
		echo "Known Input Fields:(name, default, description)\n";
	}

	if (sizeof($input_fields)) {
	foreach ($input_fields as $row) {
		echo $row["data_template_id"] . ":" . $row["name"] . "\t" . $row["default"] . "\t" . $row["description"] . "\n";
	}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayGraphTemplates($templates, $quietMode = FALSE) {
	if (!$quietMode) {
		echo "Known Graph Templates:(id, name)\n";
	}

	while (list($id, $name) = each ($templates)) {
		echo $id . "\t" . $name . "\n";
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayHosts($hosts, $quietMode = FALSE) {
	if (!$quietMode) {
		echo "Known Hosts: (id, hostname, template, description)\n";
	}

	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			echo $host["id"] . "\t" . $host["hostname"] . "\t" . $host["host_template_id"] . "\t" . $host["description"] . "\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayTrees($quietMode = FALSE) {
	global $tree_sort_types;

	if (!$quietMode) {
		echo "Known Trees:\nid\tsort method\t\t\tname\n";
	}

	$trees = db_fetch_assoc("SELECT
		id,
		sort_type,
		name
		FROM graph_tree
		ORDER BY id");

	if (sizeof($trees)) {
		foreach ($trees as $tree) {
			echo $tree["id"]."\t";
			echo $tree_sort_types[$tree["sort_type"]]."\t";
			echo $tree["name"]."\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayTreeNodes($tree_id, $nodeType = "", $parentNode = "", $quietMode = FALSE) {
	global $tree_sort_types, $tree_item_types, $host_group_types;

	if (!$quietMode) {
		echo "Known Tree Nodes:\n";
		echo "type\tid\tparentid\ttitle\tattribs\n";
	}

	$parentID = 0;

	$nodes = db_fetch_assoc("SELECT
		id,
		local_graph_id,
		rra_id,
		title,
		host_id,
		host_grouping_type,
		order_key,
		sort_children_type
		FROM graph_tree_items
		WHERE graph_tree_id=$tree_id
		ORDER BY order_key");

	if (sizeof($nodes)) {
		foreach ($nodes as $node) {
			/* taken from tree.php, funtion item_edit() */
			$current_type = TREE_ITEM_TYPE_HEADER;
			if ($node["local_graph_id"] > 0) { $current_type = TREE_ITEM_TYPE_GRAPH; }
			if ($node["title"] != "") { $current_type = TREE_ITEM_TYPE_HEADER; }
			if ($node["host_id"] > 0) { $current_type = TREE_ITEM_TYPE_HOST; }

			switch ($current_type) {
				case TREE_ITEM_TYPE_HEADER:
					$starting_tier = tree_tier($node["order_key"]);
					if ($starting_tier == 1) {
						$parentID = 0;
					}else{
						$parent_tier = substr($node["order_key"], 0, (($starting_tier - 1) * CHARS_PER_TIER));
						$parentID = db_fetch_cell("SELECT id FROM graph_tree_items WHERE order_key LIKE '$parent_tier%%' AND graph_tree_id=$tree_id ORDER BY order_key LIMIT 1");
					}

					if ($nodeType == '' || $nodeType == 'header') {
						if ($parentNode == '' || $parentNode == $parentID) {
							echo $tree_item_types[$current_type]."\t";
							echo $node["id"]."\t";
							if ($parentID == 0) {
								echo "N/A\t";
							}else{
								echo $parentID."\t";
							}

							echo $node["title"]."\t";
							echo $tree_sort_types[$node["sort_children_type"]]."\t";
							echo "\n";
						}
					}
					$parentID = $node["id"];

					break;

				case TREE_ITEM_TYPE_GRAPH:
					if ($nodeType == '' || $nodeType == 'graph') {
						if ($parentNode == '' || $parentNode == $parentID) {
							echo $tree_item_types[$current_type]."\t";
							echo $node["id"]."\t";
							if ($parentID == 0) {
								echo "N/A\t";
							}else{
								echo $parentID."\t";
							}

							/* fetch the title for that graph */
							$graph_title = db_fetch_cell("SELECT
							graph_templates_graph.title_cache as name
							FROM (
								graph_templates_graph,
								graph_local)
							WHERE
								graph_local.id=graph_templates_graph.local_graph_id and
								local_graph_id = " . $node["local_graph_id"]);

							$rra = db_fetch_cell("SELECT
								name
								FROM rra
								WHERE id =" . $node["rra_id"]);

							echo $graph_title ."\t";
							echo $rra . "\t";
							echo "\n";
						}
					}
					break;

				case TREE_ITEM_TYPE_HOST:
					if ($nodeType == '' || $nodeType == 'host') {
						if ($parentNode == '' || $parentNode == $parentID) {
							echo $tree_item_types[$current_type]."\t";
							echo $node["id"]."\t";
							if ($parentID == 0) {
								echo "N/A\t";
							}else{
								echo $parentID."\t";
							}

							$name = db_fetch_cell("SELECT
							hostname
							FROM host
							WHERE id = " . $node["host_id"]);
							echo $name . "\t";
							echo $host_group_types[$node["host_grouping_type"]]."\t";
							echo "\n";
						}
					}
				break;
			}
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayRRAs($quietMode = FALSE) {
	if (!$quietMode) {
		echo "Known RRAs:\nid\tsteps\trows\ttimespan\tname\n";
	}

	$rras = db_fetch_assoc("SELECT
		id,
		name,
		steps,
		rows,
		timespan
		FROM rra
		ORDER BY id");

	if (sizeof($rras)) {
		foreach ($rras as $rra) {
			echo $rra["id"]."\t";
			echo $rra["steps"]."\t";
			echo $rra["rows"]."\t";
			echo $rra["timespan"]."\t\t";
			echo $rra["name"]."\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayHostGraphs($host_id, $quietMode = FALSE) {

	if (!$quietMode) {
		echo "Known Host Graphs: (id, name, template)\n";
	}

	$graphs = db_fetch_assoc("SELECT
		graph_templates_graph.local_graph_id as id,
		graph_templates_graph.title_cache as name,
		graph_templates.name as template_name
		FROM (graph_local,graph_templates_graph)
		LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id)
		WHERE graph_local.id=graph_templates_graph.local_graph_id
		AND graph_local.host_id=" . $host_id . "
		ORDER BY graph_templates_graph.local_graph_id");

	if (sizeof($graphs)) {
		foreach ($graphs as $graph) {
			echo $graph["id"] . "\t";
			echo $graph["name"] . "\t";
			echo $graph["template_name"] . "\t";
			echo "\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

function displayUsers($quietMode = FALSE) {
	if (!$quietMode) {
		echo "Known Users:\nid\tusername\tfull_name\n";
	}

	$groups = db_fetch_assoc("SELECT
				id,
				username,
				full_name
				FROM user_auth
				ORDER BY id");

	if (sizeof($groups)) {
		foreach ($groups as $group) {
			echo $group["id"]."\t";
			echo $group["username"]."\t";
			echo $group["full_name"]."\n";
		}
	}

	if (!$quietMode) {
		echo "\n";
	}
}

?>
