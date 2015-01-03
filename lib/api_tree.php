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

function api_tree_lock($tree_id, $user_id = 0, $web = true) {
	/* ================= input validation ================= */
	input_validate_input_number($tree_id);
	input_validate_input_number($user_id);
	/* ==================================================== */

	db_execute_prepared('UPDATE graph_tree 
		SET locked = 1, 
		locked_date = NOW(), 
		last_modified = NOW(), 
		modified_by = ? 
		WHERE id = ?', array($user_id, $tree_id));

	if ($web) {
		header('Location: tree.php?action=edit&header=false&id=' . $tree_id);
	}
}

function api_tree_unlock($tree_id, $user_id = 0, $web = true) {
	/* ================= input validation ================= */
	input_validate_input_number($tree_id);
	input_validate_input_number($user_id);
	/* ==================================================== */

	db_execute_prepared('UPDATE graph_tree 
		SET locked = 0, 
		last_modified = NOW(), 
		modified_by = ?
		WHERE id = ?', array($user_id, $tree_id));

	if ($web) {
		header('Location: tree.php?action=edit&header=false&id=' . $tree_id);
	}
}

/* api_tree_copy_node - given a tree id, a new node location, 
 * it's original paren, make a copy of the prior node.
 * @arg $tree_id - The name of the lock to be created
 * @arg $node_id - The node to be copied
 * @arg $new_parent - The new parent of the copied node
 * @arg $new_position - The manual position of the copied node
 * @returns - json encoded new location information */
function api_tree_copy_node($tree_id, $node_id, $new_parent, $new_position) {
	input_validate_input_number($tree_id);
	input_validate_input_number($new_position);

	$data  = api_tree_parse_node_data($node_id);
	$pdata = api_tree_parse_node_data($new_parent);

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
		$exists = db_fetch_cell_prepared("SELECT id 
			FROM graph_tree_items 
			WHERE parent = ? 
			AND host_id = ?", 
			array($pdata['branch'], $data['host']));

		if ($exists) {
			print 'tbranch:' . $exists;
			return;
		}
	}elseif ($data['graph'] > 0) {
		$exists = db_fetch_cell_prepared("SELECT id 
			FROM graph_tree_items 
			WHERE parent = ?
			AND local_graph_id = ?",
			array($pdata['branch'], $data['graph']));

		if ($exists) {
			print 'tbranch:' . $exists;
			return;
		}
	}else{
		$title = db_fetch_cell_prepared("SELECT title 
			FROM graph_tree_items 
			WHERE id = ?", array($data['branch']));
	}

	$save = array();
	$save['parent']             = $pdata['branch'];
	$save['position']           = $new_position;
	$save['graph_tree_id']      = $tree_id;
	$save['local_graph_id']     = $data['graph'];
	$save['host_id']            = $data['host'];
	$save['host_grouping_type'] = 1;
	$save['sort_children_type'] = 1;
	$save['title']              = $title;

	$id = sql_save($save, 'graph_tree_items');

	api_tree_reorder_branch($tree_id, $pdata['branch'], $id, $new_position);

	header('Content-Type: application/json; charset=utf-8');
	print json_encode(array('id' => 'tbranch:' . $id));
}

/* api_tree_get_lock - given a lock name, placed a timeed lock on the database.
 * This function allows simulating transactions in an MyISAM database.
 * @arg $lockname - The name of the lock to be created
 * @returns - true depending on outcome */
function api_tree_get_lock($lockname, $timeout = 10) {
	input_validate_input_number($timeout);
	$lockname = sanitize_search_string($lockname);

	while (true) {
		$locked = db_fetch_cell("SELECT GET_LOCK('$lockname', $timeout)");

		if ($locked) {
			return true;
		}else{
			sleep(1);
		}
	}
}

/* api_tree_release_lock - given a lock name, release that lock.
 * This function allows simulating transactions in an MyISAM database.
 * @arg $lockname - The name of the lock to be released
 * @returns - true or false depending on outcome */
function api_tree_release_lock($lockname) {
	$lockname = sanitize_search_string($lockname);
	$unlocked = db_fetch_cell("SELECT RELEASE_LOCK('$lockname')");
}

/* api_tree_reorder_branch - given a tree, a parent branch, the branch just moved, and it's postion, resort the tree branch
 * @arg $tree_id - The tree to remove from
 * @arg $parent_id - The tree branch to resort
 * @arg $moved_branch - The branch that was juste moved into the current brnach
 * @arg $position - The current branch/leaf position
 * @arg $title - The new brnach/leaf title
 * @returns - null */
function api_tree_reorder_branch($tree_id, $parent_id, $moved_branch = 0, $position = 0) {
	// Lock the table
	api_tree_get_lock('tree-lock', 10);
	
	$items = db_fetch_assoc_prepared("SELECT id, position 
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		AND id != ?
		ORDER BY position", 
		array($tree_id, $parent_id, $moved_branch));

	$i = 0;
	if (sizeof($items)) {
		foreach($items as $item) {
			if ($i == $position) {
				$i++;
			}

			db_execute_prepared("UPDATE graph_tree_items 
				SET position = ?
				WHERE id = ?
				AND graph_tree_id = ?", 
				array($i, $item['id'], $tree_id));

			$i++;
		}
	}

	// Un-Lock the table
	api_tree_release_lock('tree-lock');
}

/* api_tree_create_node - given a tree, a desintation branch, order position, and title, create a branch/leaf.
 * @arg $tree_id - The tree to remove from
 * @arg $node_id - The branch/leaf to place the new branch/leaf
 * @arg $title - The new brnach/leaf title
 * @returns - json encoded new leaf information */
function api_tree_create_node($tree_id, $node_id, $position, $title = 'New Branch') {
	input_validate_input_number($tree_id);
	input_validate_input_number($position);

	/* clean up text string */
	if (isset($title)) {
		$title = sanitize_search_string($title);
	}
	
	$data  = api_tree_parse_node_data($node_id);

	if ($data['branch'] < 0) {
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

/* api_tree_delete - given a tree and a branch/leaf, delete the node and it's content
 * @arg $tree_id - The tree to remove from
 * @arg $branch_id - The branch to remove
 * @returns - null */
function api_tree_delete_node($tree_id, $node_id) {
	input_validate_input_number($tree_id);

	// Basic Error Checking
	if (empty($tree_id) || $tree_id < 0) {
		cacti_log("ERROR: Invalid TreeID: '$tree_id', Function delete_node", false);
		return;
	}

	if (empty($node_id)) {
		cacti_log("ERROR: Invalid NodeID: '$node_id', Function delete_node", false);
		return;
	}

	$data  = api_tree_parse_node_data($node_id);

	if (isset($data['branch']) && $data['branch'] > 0) {
		if ($data['host'] == 0 && $data['graph'] == 0) {
			api_tree_delete_node_content($tree_id, $data['branch']);
		}

		db_execute_prepared("DELETE FROM graph_tree_items WHERE graph_tree_id = ? AND id = ?", array($tree_id, $data['branch']));
	}
}

/* api_tree_delete_content - given a tree and a branch/leaf, recursively remove all elements
 * @arg $tree_id - The tree to remove from
 * @arg $branch_id - The branch to remove
 * @returns - null */
function api_tree_delete_node_content($tree_id, $branch_id) {
	$children = db_fetch_assoc_prepared("SELECT * 
		FROM graph_tree_items 
		WHERE graph_tree_id = ? AND parent = ?", array($tree_id, $branch_id));

	if (sizeof($children)) {
	foreach($children as $child) {
		if ($child['host_id'] == 0 && $child['graph_id'] == 0) {
			api_tree_delete_node_content($tree_id, $child['id']);
		}

		db_execute_prepared("DELETE 
			FROM graph_tree_items 
			WHERE graph_tree_id = ?
			AND id = ?", array($tree_id, $child['id']));
	}
	}
}

/* api_tree_move_node - given the current node information and it's new branch, move it.
 * @arg $variable - The request variable to parse
 * @returns - array of information about the variable */
function api_tree_move_node($tree_id, $node_id, $new_parent, $new_position) {
	input_validate_input_number($tree_id);
	input_validate_input_number($new_position);

	// Basic Error Checking
	if (empty($tree_id) || $tree_id < 0) {
		cacti_log("ERROR: Invalid TreeID: '$tree_id', Function delete_node", false);
		return;
	}

	if (empty($node_id)) {
		cacti_log("ERROR: Invalid NodeID: '$node_id', Function move_node", false);
		return;
	}

	if (empty($new_parent)) {
		cacti_log("ERROR: Invalid Parent Node '$new_parent' for NodeID: '$node_id', Function move_node", false);
		return;
	}elseif ($new_parent == '#') {
		$pdata['branch'] = 0;
	}else{
		$pdata = api_tree_parse_node_data($new_parent);
	}

	$data  = api_tree_parse_node_data($node_id);

	if ($data['parent'] != $pdata['branch']) {
		db_execute_prepared("UPDATE graph_tree_items 
			SET parent = ?, position = ? 
			WHERE id = ?
			AND graph_tree_id = ?", 
			array($pdata['branch'], $new_position, $data['branch'], $tree_id));

		api_tree_reorder_branch($tree_id, $pdata['branch'], $data['branch'], $new_position);
	}elseif (isset($data['branch']) && $data['branch'] > 0 && isset($pdata['branch']) && $pdata['branch'] >= 0) {
		db_execute_prepared("UPDATE graph_tree_items
			SET position = ? 
			WHERE graph_tree_id = ?
			AND id = ?", 
			array($new_position, $tree_id, $data['branch']));

		api_tree_reorder_branch($tree_id, $pdata['branch'], $data['branch'], $new_position);
	}else{
		cacti_log("Invalid Source Destination Branches, Function move_node", false);
		return;
	}
}

/* api_tree_parse_node_data - given the node information parse into a branch, parent, host, graph array
 * @arg $variable - The request variable to parse
 * @returns - array of information about the variable */
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
		$parent = db_fetch_cell_prepared("SELECT parent FROM graph_tree_items WHERE id = ?", array($branch_id));
	}else{
		$parent = '0';
	}

	return array('branch' => $branch_id, 'graph' => $graph_id, 'host' => $host_id, 'parent' => $parent);
}

/* api_tree_rename_node - given the tree and the node information rename the tree branch/leaf.
 * This function is used for editing.
 * @arg $tree_id - The id of the tree you are parsing
 * @arg $node_id - The branch/leaf id of the node to be renamed
 * @arg $text - The new branch/leaf title
 * @returns - string of the tree items in html format */
function api_tree_rename_node($tree_id, $node_id = '', $text = '') {
	input_validate_input_number($tree_id);
	
	/* clean up text string */
	$text = sanitize_search_string($text);
	
	// Basic Error Checking
	if ($tree_id <= 0) {
		cacti_log("ERROR: Invalid TreeID: '" . $tree_id . "', Function rename_node", false);
		return;
	}

	if (empty($node_id)) {
		cacti_log("ERROR: Invalid NodeID: '" . $node_id . "', Function rename_node", false);
		return;
	}

	// Initialize some variables
	$branch_id = 0;
	$graph_id  = 0;
	$host_id   = 0;

	// Process the 'id' variable
	$ndata   = explode('_', $node_id);
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
			db_execute_prepared("UPDATE graph_tree_items 
				SET title = ? 
				WHERE graph_tree_id = ? 
				AND id = ?", array($text, $tree_id, $branch_id));
		}
	}
}

/* api_tree_get_main - given the tree and the parent node information return tree elements.
 * This function is used for graphing.
 * @arg $tree_id - The id of the tree you are parsing
 * @arg $parent - The parent id of the branch/leaf
 * @returns - string of the tree items in html format */
function api_tree_get_main($tree_id, $parent = 0) {
	$is_root = false;
	if ($parent == -1) {
		$parent  = 0;
		$is_root = true;

		if ($tree_id > 0) {
			$name     = db_fetch_cell("SELECT name FROM graph_tree WHERE id=$tree_id");

			print "<ul><li class='jstree-closed' id='tree_anchor-$tree_id'><a href='" . htmlspecialchars('graph_view.php?action=tree&tree_id=' . $tree_id . '&leaf_id=&host_group_data='). "'>" . htmlspecialchars($name) . "</a>\n";

			$heirarchy = draw_dhtml_tree_level_graphing($tree_id, $parent);

			if (sizeof($heirarchy)) {
			foreach($heirarchy as $h) {
				print $h;
			}
			}
		}else{
			$heirarchy = draw_dhtml_tree_level_graphing($tree_id, $parent);

			if (sizeof($heirarchy)) {
			foreach($heirarchy as $h) {
				print $h;
			}
			}
		}
	}else{
		$heirarchy = draw_dhtml_tree_level_graphing($tree_id, $parent);

		if (sizeof($heirarchy)) {
		foreach($heirarchy as $h) {
			print $h;
		}
		}
	}

	if ($is_root) {
		print "</li></ul>\n";
	}
}

/* api_tree_get_node - given the tree and the node information return tree elements
 * @arg $tree_id - The id of the tree you are parsing
 * @arg $node_id - The encoded node id of the branch/leaf
 * @returns - string of the tree items in html format */
function api_tree_get_node($tree_id, $node_id) {
	if ($node_id == '#') {
		$heirarchy = draw_dhtml_tree_level($tree_id, 0);
	}else{
		$dnode = explode(':', $node_id);
		$id = $dnode[1];
		input_validate_input_number($id);
		$heirarchy = draw_dhtml_tree_level($tree_id, $id);
	}

	if (sizeof($heirarchy)) {
	foreach($heirarchy as $h) {
		print $h;
	}
	}
}

/** api_tree_item_save - saves the tree object and then resorts the tree
 * @arg $id - the branch id for the object
 * @arg $tree_id - the tree id for the object
 * @arg $type - the item type graph, host, leaf
 * @arg $parent_tree_item_id - The parent leaf for the object
 * @arg $title - The leaf title in the caseo a leaf
 * @arg $local_graph_id - The graph id in the case of a graph
 * @arg $rra_id - The default timespan in the case of a graph
 * @arg $host_id - The host id in the case of a graph
 * @arg $host_grouping_type - The sort order for the host under expanded hosts
 * @arg $sort_children - The sort type in the case of a leaf
 * @arg $propagate_changes - Wether the changes should be cascaded through all children
 * @returns - boolean true or false depending on the outcome of the operation */
function api_tree_item_save($id, $tree_id, $type, $parent_tree_item_id, $title, $local_graph_id, $rra_id,
	$host_id, $host_grouping_type, $sort_children_type, $propagate_changes) {
	global $config;

	input_validate_input_number($tree_id);
	input_validate_input_number($parent_tree_item_id);

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
					api_tree_sort_tree(SORT_TYPE_TREE_ITEM, $parent_tree_item_id, $parent_sorting_type);
				}

				/* if this is a header, sort direct children */
				if (($type == TREE_ITEM_TYPE_HEADER) && ($sort_children_type != TREE_ORDERING_NONE)) {
					api_tree_sort_tree(SORT_TYPE_TREE_ITEM, $tree_item_id, $sort_children_type);
				}
			}else{
				if ($parent_tree_item_id == 0) {
					api_tree_sort_tree(SORT_TYPE_TREE, $tree_id, $tree_sort_type);
				}else{
					api_tree_sort_tree(SORT_TYPE_TREE_ITEM, $parent_tree_item_id, $tree_sort_type);
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
							api_tree_sort_tree(SORT_TYPE_TREE_ITEM, $item["id"], $sort_children_type);
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

/* api_tree_get_item_type - gets the type of tree item
   @arg $tree_item_id - the id of the tree item to fetch the type for
   @returns - a string reprenting the type of the tree item. valid return
     values are 'header', 'graph', and 'host' */
function api_tree_get_item_type($tree_item_id) {
	$tree_item = db_fetch_row("SELECT title,local_graph_id,host_id FROM graph_tree_items WHERE id=$tree_item_id");

	if (!sizeof($tree_item)) {
		return "";
	}elseif ($tree_item["local_graph_id"] > 0) {
		return "graph";
	}elseif ($tree_item["title"] != "") {
		return "header";
	}elseif ($tree_item["host_id"] > 0) {
		return "host";
	}

	return "";
}

/* sort_branch - sorts the child items a branch using a specified sorting algorithm
   @arg $sort_type - the type of sorting to perform. available options are:
     SORT_TYPE_TREE (1) - sort the entire tree
     SORT_TYPE_TREE_ITEM (2) - sort a single tree branch
   @arg $item_id - the id tree or tree item to sort
   @arg $sort_style - the type of sorting to perform. available options are:
     TREE_ORDERING_NONE (1) - no sorting
     TREE_ORDERING_ALPHABETIC (2) - alphabetic sorting
     TREE_ORDERING_NUMERIC (3) - numeric sorting */
function api_tree_sort_tree($sort_type, $branch_id, $sort_style) {
	global $config;

	include_once($config["library_path"] . "/sort.php");

	if (empty($branch_id)) { 
		return 0; 
	}

	if ($sort_style == TREE_ORDERING_NONE) { 
		return 0; 
	}

	if ($sort_type == SORT_TYPE_TREE_ITEM) {
		$tree_id = db_fetch_cell("SELECT graph_tree_id FROM graph_tree_items WHERE id=$branch_id");

		$sql_where = "WHERE gti.graph_tree_id=$tree_id AND parent=$branch_id";
	}else if ($sort_type == SORT_TYPE_TREE) {
		$sql_where = "WHERE gti.graph_tree_id='$branch_id'";

		$tree_id = $branch_id;
	}else{
		return 0;
	}

	$hier_sql = "SELECT gti.id, gti.title, gti.local_graph_id, gti.host_id, 
		gtg.title_cache AS graph_title, CONCAT_WS('',description,' (',hostname,')') AS hostname
		FROM graph_tree_items AS gti
		LEFT JOIN graph_templates_graph AS gtg
		ON gti.local_graph_id=gtg.local_graph_id AND gtg.local_graph_id>0
		LEFT JOIN host AS h
		ON h.id=gti.host_id
		$sql_where
		ORDER BY gti.position";

	$hierarchy = db_fetch_assoc($hier_sql);

	$sort_array = array();
	if (sizeof($hierarchy)) {
	foreach ($hierarchy as $leaf) {
		if ($leaf["local_graph_id"] > 0) {
			$sort_array[$leaf["id"]] = $leaf["graph_title"];
		}elseif ($leaf["title"] != "") {
			$sort_array[$leaf["id"]] = $leaf["title"];
		}elseif ($leaf["host_id"] > 0) {
			$sort_array[$leaf["id"]] = $leaf["hostname"];
		}
	}
	}

	/* do the actual sort */
	if ($sort_style == TREE_ORDERING_NUMERIC) {
		uasort($sort_array, "usort_numeric");
	}elseif ($sort_style == TREE_ORDERING_ALPHABETIC) {
		uasort($sort_array, "usort_alphabetic");
	}elseif ($sort_style == TREE_ORDERING_NATURAL) {
		uasort($sort_array, "usort_natural");
	}

	$position = 0;

	/* prepend all order keys will 'x' so they don't collide during the REPLACE process */
	foreach($sort_array as $id => $item) {
		db_execute("UPDATE graph_tree_items 
			SET position=$position 
			WHERE id=$id AND graph_tree_id=$tree_id");

		$position++;
	}
}

