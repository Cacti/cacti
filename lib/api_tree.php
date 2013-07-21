<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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

	input_validate_input_number($tree_id);
	input_validate_input_number($parent_tree_item_id);

	include_once($config["library_path"] . "/tree.php");

	db_execute("LOCK TABLES graph_tree_items WRITE, graph_tree READ, graph_templates_graph READ, host READ");

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
	if ($id == 0 && ($type == TREE_ITEM_TYPE_GRAPH) && (sizeof(db_fetch_assoc("select id from graph_tree_items where local_graph_id='$local_graph_id' and graph_tree_id='$tree_id' and order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'")) > 0)) {
		$value = db_fetch_cell("select id from graph_tree_items where local_graph_id='$local_graph_id' and graph_tree_id='$tree_id' and order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'");
		db_execute("UNLOCK TABLES");
		return $value;
	}

	/* Duplicate header check */
	if ($id == 0 && ($type == TREE_ITEM_TYPE_HEADER)) {
		if ((sizeof(db_fetch_assoc("select id from graph_tree_items where title='$title' and graph_tree_id='$tree_id' and order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'")) > 0)) {
			$value = db_fetch_cell("select id from graph_tree_items where title='$title' and graph_tree_id='$tree_id' and order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'");
			db_execute("UNLOCK TABLES");
			return $value;
		}
	}

	/* Duplicate host check */
	if ($id == 0 && ($type == TREE_ITEM_TYPE_HOST) && (sizeof(db_fetch_assoc("select id from graph_tree_items where host_id='$host_id' and local_graph_id='$local_graph_id' and graph_tree_id='$tree_id' and order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'")) > 0)) {
		$value = db_fetch_cell("select id from graph_tree_items where host_id='$host_id' and local_graph_id='$local_graph_id' and graph_tree_id='$tree_id' and order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'");
		db_execute("UNLOCK TABLES");
		return $value;
	}

	$save["id"] = $id;
	$save["graph_tree_id"] = $tree_id;
	$save["title"] = form_input_validate($title, "title", "", ($type == TREE_ITEM_TYPE_HEADER ? false : true), 3);
	$save["order_key"] = $order_key;
	$save["local_graph_id"] = form_input_validate($local_graph_id, "local_graph_id", "", true, 3);
	$save["rra_id"]	= form_input_validate($rra_id, "rra_id", "", true, 3);
	$save["host_id"] = form_input_validate($host_id, "host_id", "", true, 3);
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

	db_execute("UNLOCK TABLES");

	return $tree_item_id;
}

?>
