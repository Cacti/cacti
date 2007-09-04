
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2007 The Cacti Group                                 |
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

function api_tree_item_save($id, $tree_id, $type, $parent_tree_item_id, $title, $local_graph_id, $rra_id,
	$host_id, $host_grouping_type, $sort_children_type, $propagate_changes) {
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

function get_host_templates() {
	$tmparray = db_fetch_assoc("select id, name from host_template order by id");

	foreach ($tmparray as $template) {
		$host_templates[$template["id"]] = $template["name"];
	}

	return $host_templates;
}

function get_hosts_by_description() {
	$hosts = array();
	$tmparray = db_fetch_assoc("select id, description from host order by description");

	foreach ($tmparray as $tmp) {
		$hosts[$tmp["description"]] = $tmp["id"];
	}

	return $hosts;
}

function getHosts() {
	$hosts    = array();
	$tmpArray = db_fetch_assoc("select * from host order by id");

	foreach ($tmpArray as $host) {
		$hosts[$host["id"]] = $host;
	}

	return $hosts;
}

function get_addresses() {
	$addresses = array();
	$tmparray  = db_fetch_assoc("select id, hostname from host order by hostname");

	foreach ($tmparray as $tmp) {
		$addresses[$tmp["hostname"]] = $tmp["id"];
	}

	return $addresses;
}

function getSNMPFields($hostId) {
	$fieldNames = array();
	$tmpArray   = db_fetch_assoc("select distinct field_name from host_snmp_cache where host_id = " . $hostId . " order by field_name");

	foreach ($tmpArray as $f) {
		$fieldNames[$f["field_name"]] = 1;
	}

	return $fieldNames;
}

function getSNMPValues($hostId, $field) {
	$values   = array();
	$tmpArray = db_fetch_assoc("select field_value from host_snmp_cache where host_id = " . $hostId . " and field_name = '" . $field . "' order by field_value");

	foreach ($tmpArray as $v) {
		$values[$v["field_value"]] = 1;
	}

	return $values;
}

function getSNMPQueries() {
	$queries  = array();
	$tmpArray = db_fetch_assoc("select id, name from snmp_query order by id");

	foreach ($tmpArray as $q) {
		$queries[$q["id"]] = $q["name"];
	}

	return $queries;
}

function getSNMPQueryTypes($snmpQueryId) {
	$types    = array();
	$tmpArray = db_fetch_assoc("select id, name from snmp_query_graph where snmp_query_id = " . $snmpQueryId . " order by id");

	foreach ($tmpArray as $type) {
		$types[$type["id"]] = $type["name"];
	}

	return $types;
}

function displayQueryTypes($types) {
	echo "Known SNMP Query Types:(id, name)\n";

	while (list($id, $name) = each ($types)) {
		echo "\t" . $id . "\t" . $name . "\n";
	}
}

function getGraphTemplates() {
	$graph_templates = array();
	$tmpArray        = db_fetch_assoc("select id, name from graph_templates order by id");

	foreach ($tmpArray as $t) {
		$graph_templates[$t["id"]] = $t["name"];
	}

	return $graph_templates;
}

function display_host_templates($host_templates) {
	echo "Valid Host Templates:\n";

	foreach ($host_templates as $id => $name) {
		echo "\t$id => $name\n";
	}

	echo "\n";
}

function display_communities() {
	echo "Known communities are:\n";

	$communities = db_fetch_assoc("select snmp_community from host group by snmp_community");

	foreach ($communities as $community) {
		echo "\t".$community["snmp_community"]."\n";
	}

	echo "\n";
}

function displaySNMPFields($fields, $hostId) {
	echo "Known SNMP Fields for host-id $hostId\n";

	while (list($field, $values) = each ($fields)) {
		printf("%s\n", $field);
	}
}

function displaySNMPValues($values, $hostId, $field) {
	echo "Known values for $field (for host $hostId)\n";

	while (list($value, $foo) = each($values)) {
		echo "$value\n";
	}
}

function displaySNMPQueries($queries) {
	echo "Known SNMP Queries:(id, name)\n";

	while (list($id, $name) = each ($queries)) {
		echo "\t" . $id . "\t" . $name . "\n";
	}
}

function displayGraphTemplates($templates) {
	echo "Known Graph Templates:(id, name)\n";

	while (list($id, $name) = each ($templates)) {
		echo "\t" . $id . "\t" . $name . "\n";
	}
}

function displayHosts($hosts) {
	echo "Known Hosts: (id, hostname, template, description)\n";

	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			echo "\t" . $host["id"] . "\t" . $host["hostname"] . "\t" . $host["host_template_id"] . "\t" . $host["description"] . "\n";
		}
	}
}

?>
