<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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

function api_tree_copy_node() {
	input_validate_input_number(get_request_var_request('tree_id'));
	input_validate_input_number(get_request_var_request('position'));

	$tree_id  = $_REQUEST['tree_id'];
	$position = $_REQUEST['position'];

	$data  = api_tree_parse_node_data($_REQUEST['id']);
	$pdata = api_tree_parse_node_data($_REQUEST['parent']);

	if (!isset($data['host']) && !isset($data['graph'])) {
		cacti_log("ERROR: Copy node requires either a host or a graph, Function copy_node", false);
		return;
	}

	if (isset($data['host']) && ($data['host'] < 0 || !is_numeric($data['host']))) {
		cacti_log("ERROR: Copy node host data invalid, Function copy_node", false);
		return;
	}elseif(isset($data['graph']) && ($data['graph'] < 0 || !is_numeric($data['graph']))) {
		cacti_log("ERROR: Copy node graph data invalid, Function copy_node", false);
		return;
	}

	if (!isset($pdata['branch']) || $pdata['branch'] < 0 || !is_numeric($pdata['branch'])) {
		cacti_log("ERROR: Copy node parent data invalid, Function copy_node", false);
		return;
	}

	// Check to see if the node already exists
	$title = '';
	if ($data['host'] > 0) {
		$exists = db_fetch_cell("SELECT id FROM graph_tree_items WHERE parent=" . $pdata['branch'] . " AND host_id=" . $data['host']);

		if ($exists) {
			print 'tbranch:' . $exists;
			return;
		}
	}elseif ($data['graph'] > 0) {
		$exists = db_fetch_cell("SELECT id FROM graph_tree_items WHERE parent=" . $pdata['branch'] . " AND local_graph_id=" . $data['graph']);

		if ($exists) {
			print 'tbranch:' . $exists;
			return;
		}
	}else{
		$title = db_fetch_cell("SELECT title FROM graph_tree_items WHERE id=" . $data['branch']);
	}

	$save = array();
	$save['parent']             = $pdata['branch'];
	$save['position']           = $position;
	$save['graph_tree_id']      = $tree_id;
	$save['local_graph_id']     = $data['graph'];
	$save['host_id']            = $data['host'];
	$save['host_grouping_type'] = 1;
	$save['sort_children_type'] = 1;
	$save['title']              = $title;

	$id = sql_save($save, 'graph_tree_items');

	api_tree_reorder_branch($tree_id, $pdata['branch'], $id, $position);

	header('Content-Type: application/json; charset=utf-8');
	print json_encode(array('id' => 'tbranch:' . $id));
}

function api_tree_get_lock($lockname, $timeout = 10) {
	while (true) {
		$locked = db_fetch_cell("SELECT GET_LOCK('$lockname', $timeout)");

		if ($locked) {
			return true;
		}else{
			sleep(1);
		}
	}
}

function api_tree_release_lock($lockname) {
	$unlocked = db_fetch_cell("SELECT RELEASE_LOCK('$lockname')");
}

function api_tree_reorder_branch($tree_id, $parent_id, $moved_branch, $position) {
	// Lock the table
	api_tree_get_lock('tree-lock', 10);
	
	$items = db_fetch_assoc("SELECT id, position 
		FROM graph_tree_items
		WHERE graph_tree_id=$tree_id 
		AND parent=$parent_id 
		AND id!=$moved_branch
		ORDER BY position");

	$i = 0;
	if (sizeof($items)) {
		foreach($items as $item) {
			if ($i == $position) {
				$i++;
			}
			db_execute("UPDATE graph_tree_items SET position=$i WHERE id=" . $item['id'] . " AND graph_tree_id=$tree_id");
			$i++;
		}
	}

	// Un-Lock the table
	api_tree_release_lock('tree-lock');
}

function api_tree_create_node() {
	input_validate_input_number(get_request_var_request('tree_id'));
	input_validate_input_number(get_request_var_request('position'));

	/* clean up text string */
	if (isset($_REQUEST['text'])) {
		$_REQUEST['text'] = sanitize_search_string(get_request_var_request('text'));
	}
	
	$tree_id  = $_REQUEST['tree_id'];
	$position = $_REQUEST['position'];
	$title    = $_REQUEST['text'];

	$data  = api_tree_parse_node_data($_REQUEST['id']);

	if (!isset($data['branch']) || $data['branch'] < 0) {
		cacti_log("ERROR: Invalid BranchID: '" . (isset($data['branch']) ? $data['branch']:'-') . "', Function create_node", false);
		return;
	}

	/* watch out for monkey business */
	input_validate_input_number($data['branch']);

	$save = array();
	$save['parent']             = $data['branch'];
	$save['position']           = $position;
	$save['graph_tree_id']      = $tree_id;
	$save['local_graph_id']     = 0;
	$save['host_id']            = 0;
	$save['host_grouping_type'] = 1;
	$save['sort_children_type'] = 1;
	$save['title']              = $title;

	$id = sql_save($save, 'graph_tree_items');

	api_tree_reorder_branch($tree_id, $data['branch'], $id, $position);

	header('Content-Type: application/json; charset=utf-8');
	print json_encode(array('id' => 'tbranch:' . $id));
}

function api_tree_delete_node() {
	input_validate_input_number(get_request_var_request('tree_id'));

	// Basic Error Checking
	if (!isset($_REQUEST['tree_id']) || empty($_REQUEST['tree_id']) || $_REQUEST['tree_id'] < 0) {
		cacti_log("ERROR: Invalid TreeID: '" . $_REQUEST['tree_id'] . ", Function delete_node", false);
		return;
	}

	if (!isset($_REQUEST['id']) || empty($_REQUEST['id'])) {
		cacti_log("ERROR: Invalid NodeID: '" . $_REQUEST['id'] . ", Function delete_node", false);
		return;
	}

	$tree_id = $_REQUEST['tree_id'];

	$data  = api_tree_parse_node_data($_REQUEST['id']);

	if (isset($data['branch']) && $data['branch'] > 0) {
		if ($data['host'] == 0 && $data['graph'] == 0) {
			api_tree_delete_node_content($tree_id, $data['branch']);
		}

		db_execute("DELETE FROM graph_tree_items WHERE graph_tree_id=$tree_id AND id=" . $data['branch']);
	}
}

function api_tree_delete_node_content($tree_id, $branch_id) {
	$children = db_fetch_assoc("SELECT * 
		FROM graph_tree_items 
		WHERE graph_tree_id=$tree_id AND parent=$branch_id");

	if (sizeof($children)) {
	foreach($children as $child) {
		if ($child['host_id'] == 0 && $child['graph_id'] == 0) {
			api_tree_delete_node_content($tree_id, $child['id']);
		}

		db_execute("DELETE FROM graph_tree_items 
			WHERE graph_tree_id=$tree_id 
			AND id=" . $child['id']);
	}
	}
}

function api_tree_move_node() {
	input_validate_input_number(get_request_var_request('tree_id'));
	input_validate_input_number(get_request_var_request('position'));

	// Basic Error Checking
	if (!isset($_REQUEST['tree_id']) || empty($_REQUEST['tree_id']) || $_REQUEST['tree_id'] < 0) {
		cacti_log("ERROR: Invalid TreeID: '" . $_REQUEST['tree_id'] . ", Function delete_node", false);
		return;
	}

	if (!isset($_REQUEST['id']) || empty($_REQUEST['id'])) {
		cacti_log("ERROR: Invalid NodeID: '" . $_REQUEST['id'] . ", Function move_node", false);
		return;
	}

	$position = $_REQUEST['position'];

	if (!isset($_REQUEST['parent']) || empty($_REQUEST['parent'])) {
		cacti_log("ERROR: Invalid NodeID: '" . $_REQUEST['id'] . ", Function move_node", false);
		return;
	}elseif ($_REQUEST['parent'] == '#') {
		$pdata['branch'] = 0;
	}else{
		$pdata = api_tree_parse_node_data($_REQUEST['parent']);
	}

	$tree_id = $_REQUEST['tree_id'];

	$data  = api_tree_parse_node_data($_REQUEST['id']);

	if ($data['parent'] != $pdata['branch']) {
		db_execute("UPDATE graph_tree_items 
			SET parent=" . $pdata['branch'] . ", position=$position 
			WHERE id=" . $data['branch'] . " 
			AND graph_tree_id=$tree_id");

		api_tree_reorder_branch($tree_id, $pdata['branch'], $data['branch'], $position);
	}elseif (isset($data['branch']) && $data['branch'] > 0 && isset($pdata['branch']) && $pdata['branch'] >= 0) {
		db_execute("UPDATE graph_tree_items
			SET position=$position
			WHERE graph_tree_id=$tree_id
			AND id=" . $data['branch']);

		api_tree_reorder_branch($tree_id, $pdata['branch'], $data['branch'], $position);
	}else{
		cacti_log("Invalid Source Destination Branches, Function move_node", false);
		return;
	}
}

function api_tree_parse_node_data($variable) {
	// Initialize some variables
	$branch_id = 0;
	$graph_id  = 0;
	$host_id   = 0;

	if ($variable != '#') {
		// Process the 'id' variable
		$ndata   = explode('_', $variable);
		if (sizeof($ndata)) {
		foreach($ndata as $data) {
			list($type, $tid) = explode(':', $data);

			/* watch out for monkey business */
			input_validate_input_number($tid);

			switch ($type) {
				case 'tbranch':
					$branch_id = $tid;
					break;
				case 'tgraph':
					$graph_id  = $tid;
					break;
				case 'thost':
					$host_id   = $tid;
					break;
			}
		}
		}
	}

	if ($branch_id > 0) {
		$parent = db_fetch_cell("SELECT parent FROM graph_tree_items WHERE id=$branch_id");
	}else{
		$parent = '0';
	}

	return array('branch' => $branch_id, 'graph' => $graph_id, 'host' => $host_id, 'parent' => $parent);
}

function api_tree_rename_node() {
	input_validate_input_number(get_request_var_request('tree_id'));
	
	/* clean up text string */
	if (isset($_REQUEST['text'])) {
		$_REQUEST['text'] = sanitize_search_string(get_request_var_request('text'));
	}
	
	// Basic Error Checking
	if (!isset($_REQUEST['tree_id']) || empty($_REQUEST['tree_id']) || $_REQUEST['tree_id'] < 0) {
		cacti_log("ERROR: Invalid TreeID: '" . $_REQUEST['tree_id'] . ", Function rename_node", false);
		return;
	}

	if (!isset($_REQUEST['id']) || empty($_REQUEST['id'])) {
		cacti_log("ERROR: Invalid NodeID: '" . $_REQUEST['id'] . ", Function rename_node", false);
		return;
	}

	$tree_id = $_REQUEST['tree_id'];

	// Initialize some variables
	$branch_id = 0;
	$graph_id  = 0;
	$host_id   = 0;

	// Process the 'id' variable
	$ndata   = explode('_', $_REQUEST['id']);
	if (sizeof($ndata)) {
	foreach($ndata as $data) {
		list($type, $tid) = explode(':', $data);

		/* watch out for monkey business */
		input_validate_input_number($tid);

		switch ($type) {
			case 'tbranch':
				$branch_id = $tid;
				break;
			case 'tgraph':
				$graph_id  = $tid;
				break;
			case 'thost':
				$host_id   = $tid;
				break;
		}
	}
	}

	if (isset($branch_id) && $branch_id > 0) {
		if ($host_id > 0 || $graph_id > 0) {
			// Ignore.  Need to customize context
		}else{
			db_execute("UPDATE graph_tree_items SET title='" . $_REQUEST['text'] . "' WHERE graph_tree_id=$tree_id AND id=$branch_id");
		}
	}
}

function api_tree_get_main($tree_id, $parent = 0) {
	//$heirarchy = draw_dhtml_tree_level($tree_id, $parent);

	$is_root = false;
	if ($parent == -1) {
		$parent  = 0;
		$is_root = true;
		$name = db_fetch_cell("SELECT name FROM graph_tree WHERE id=$tree_id");
		print "<ul><li class='jstree-closed' id='tree_anchor-$tree_id'><a href='" . htmlspecialchars('graph_view.php?action=tree&tree_id=' . $tree_id . '&leaf_id=&host_group_data='). "'>" . htmlspecialchars($name) . "</a>\n";
	}

	$heirarchy = draw_dhtml_tree_level_graphing($tree_id, $parent);

	if (sizeof($heirarchy)) {
	foreach($heirarchy as $h) {
		print $h;
	}
	}

	if ($is_root) {
		print "</li></ul>\n";
	}
}

function api_tree_get_node() {
	input_validate_input_number(get_request_var_request('tree_id'));

	if ($_REQUEST['id'] == '#') {
		$heirarchy = draw_dhtml_tree_level($_REQUEST['tree_id'], 0);
	}else{
		$dnode = explode(':', $_REQUEST['id']);
		$id = $dnode[1];
		input_validate_input_number($id);
		$heirarchy = draw_dhtml_tree_level($_REQUEST['tree_id'], $id);
	}

	if (sizeof($heirarchy)) {
	foreach($heirarchy as $h) {
		print $h;
	}
	}
}

function api_tree_item_save($id, $tree_id, $type, $parent_tree_item_id, $title, $local_graph_id, $rra_id,
	$host_id, $host_grouping_type, $sort_children_type, $propagate_changes) {
	global $config;

	input_validate_input_number($tree_id);
	input_validate_input_number($parent_tree_item_id);

	include_once($config["library_path"] . "/tree.php");

	api_tree_get_lock('tree-lock', 10);

	$position = db_fetch_cell("SELECT MAX(position)+1 FROM graph_tree_items WHERE parent=$parent_tree_item_id AND graph_tree_id=$tree_id");

	if ($local_graph_id > 0) {
		$exists = db_fetch_cell("SELECT id FROM graph_tree_items WHERE local_graph_id=$local_graph_id AND parent=$parent_tree_item_id AND graph_tree_id=$tree_id");
		if ($exists) {
			return false;
		}
	}elseif ($host_id > 0) {
		$exists = db_fetch_cell("SELECT id FROM graph_tree_items WHERE host_id=$host_id AND parent=$parent_tree_item_id AND graph_tree_id=$tree_id");
		if ($exists) {
			return false;
		}
	}

	$save["id"] = $id;
	$save["graph_tree_id"] = $tree_id;
	$save["title"] = form_input_validate($title, "title", "", ($type == TREE_ITEM_TYPE_HEADER ? false : true), 3);
	$save["parent"] = $parent_tree_item_id;
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

			$tree_sort_type = db_fetch_cell("SELECT sort_type FROM graph_tree WHERE id='$tree_id'");

			/* tree item ordering */
			if ($tree_sort_type == TREE_ORDERING_NONE) {
				/* resort our parent */
				$parent_sorting_type = db_fetch_cell("SELECT sort_children_type FROM graph_tree_items WHERE id=$parent_tree_item_id");
				if ((!empty($parent_tree_item_id)) && ($parent_sorting_type != TREE_ORDERING_NONE)) {
					sort_tree(SORT_TYPE_TREE_ITEM, $parent_tree_item_id, $parent_sorting_type);
				}

				/* if this is a header, sort direct children */
				if (($type == TREE_ITEM_TYPE_HEADER) && ($sort_children_type != TREE_ORDERING_NONE)) {
					sort_tree(SORT_TYPE_TREE_ITEM, $tree_item_id, $sort_children_type);
				}
			}else{
				if ($parent_tree_item_id == 0) {
					sort_tree(SORT_TYPE_TREE, $tree_id, $tree_sort_type);
				}else{
					sort_tree(SORT_TYPE_TREE_ITEM, $parent_tree_item_id, $tree_sort_type);
				}
			}

			/* if the user checked the 'Propagate Changes' box */
			if (($type == TREE_ITEM_TYPE_HEADER) && ($propagate_changes == true)) {
				$tree_items = db_fetch_assoc("SELECT gti.id
					FROM graph_tree_items AS gti
					WHERE gti.host_id=0
					AND gti.local_graph_id=0
					AND gti.parent=$parent_tree_item_id
					AND gti.graph_tree_id='$tree_id'");

				if (sizeof($tree_items) > 0) {
					foreach ($tree_items as $item) {
						db_execute("UPDATE graph_tree_items SET sort_children_type='$sort_children_type' WHERE id='" . $item["id"] . "'");

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

	api_tree_release_lock('tree-lock');

	return $tree_item_id;
}

