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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* api_tree_create - create or update a Cacti tree
 * @arg $tree_id - the tree id of an existing tree of 0 for new tree
 * @arg $name - the name of the tree
 * @arg $sort_type - the sort_type of the tree
 * @arg $enabled - either true or false
 * @arg $user_id - the user id
 * @returns - the tree id if successful otherwise false */
function api_tree_create($tree_id, $name, $sort_type, $enabled, $user_id = 0) {
	/* ================= input validation ================= */
	input_validate_input_number($tree_id);
	/* ==================================================== */

	if ($tree_id > 0) {
		$prev_order = db_fetch_cell_prepared('SELECT sort_type
			FROM graph_tree
			WHERE id = ?',
			array($tree_id));
	} else {
		$prev_order = -1;
	}

	$save['id']            = $tree_id;
	$save['name']          = $name;
	$save['sort_type']     = $sort_type;
	$save['last_modified'] = date('Y-m-d H:i:s', time());
	$save['enabled']       = $enabled;
	$save['modified_by']   = $_SESSION['sess_user_id'];

	if (empty($save['id'])) {
		$save['sequence'] = api_tree_get_max_sequence() + 1;
	}

	if (empty($save['id'])) {
		$save['user_id'] = $user_id;
	}

	$tree_id = sql_save($save, 'graph_tree');

	if ($tree_id) {
		/* sort the tree using the algorithm chosen by the user */
		if ($save['sort_type'] != $prev_order) {
			if ($save['sort_type'] != TREE_ORDERING_NONE) {
				api_tree_sort_recursive(0, $tree_id);
			}
		}

		return $tree_id;
	} else {
		return false;
	}
}

/* api_tree_delete - deletes a tree or trees
 * @arg $tree_ids - the tree id or ids
 * @returns - true or false */
function api_tree_delete($tree_ids) {
	if (!is_array($tree_ids)) {
		$tree_ids = array($tree_ids);
	}

	if (cacti_sizeof($tree_ids)) {
		foreach($tree_ids as $id) {
			input_validate_input_number($id);
		}

		db_execute('DELETE FROM graph_tree WHERE ' . array_to_sql_or($tree_ids, 'id'));
		db_execute('DELETE FROM graph_tree_items WHERE ' . array_to_sql_or($tree_ids, 'graph_tree_id'));

		return true;
	} else {
		return false;
	}
}

/* api_tree_lock - locks a tree or trees for editing
 * @arg $tree_ids - the tree id or ids
 * @arg $user_id - the user id
 * @returns - true or false */
function api_tree_lock($tree_ids, $user_id = 0) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id);
	/* ==================================================== */

	if (!is_array($tree_ids)) {
		$tree_ids = array($tree_ids);
	}

	if (cacti_sizeof($tree_ids)) {
		foreach($tree_ids as $id) {
			input_validate_input_number($id);
		}

		db_execute_prepared('UPDATE graph_tree
			SET `locked` = 1, locked_date = NOW(), last_modified = NOW(), modified_by = ?
			WHERE ' . array_to_sql_or($tree_ids, 'id'),
			array($user_id));

		return true;
	} else {
		return false;
	}
}

/* api_tree_unlock - unlockes a locked tree or trees that have been locked for editing
 * @arg $tree_ids - the tree id or ids
 * @arg $user_id - the user id
 * @returns - true or false */
function api_tree_unlock($tree_ids, $user_id = 0) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id);
	/* ==================================================== */

	if (!is_array($tree_ids)) {
		$tree_ids = array($tree_ids);
	}

	if (cacti_sizeof($tree_ids)) {
		foreach($tree_ids as $id) {
			input_validate_input_number($id);
		}

		db_execute_prepared('UPDATE graph_tree
			SET `locked` = 0, last_modified = NOW(), modified_by = ?
			WHERE ' . array_to_sql_or($tree_ids, 'id'),
			array($user_id));

		return true;
	} else {
		return false;
	}
}

/* api_tree_publish - publishes a tree or trees for viewing
 * @arg $tree_ids - an array of tree ids or a tree id
 * @arg $user_id - the user id
 * @returns - true if the tree was published */
function api_tree_publish($tree_ids, $user_id = 0) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id);
	/* ==================================================== */

	if (!is_array($tree_ids)) {
		$tree_ids = array($tree_ids);
	}

	if (cacti_sizeof($tree_ids)) {
		foreach($tree_ids as $id) {
			input_validate_input_number($id);
		}

		db_execute_prepared('UPDATE graph_tree
			SET enabled="on",
			last_modified=NOW(),
			modified_by = ?
			WHERE ' . array_to_sql_or($tree_ids, 'id'),
			array($user_id));

		return true;
	} else {
		return false;
	}
}

/* api_tree_unpublish - un-publishes a tree or trees for viewing
 * @arg $tree_ids - an array of tree ids or a tree id
 * @arg $user_id - the user id
 * @returns - true if the tree was un-published */
function api_tree_unpublish($tree_ids, $user_id = 0) {
	/* ================= input validation ================= */
	input_validate_input_number($user_id);
	/* ==================================================== */

	if (!is_array($tree_ids)) {
		$tree_ids = array($tree_ids);
	}

	if (cacti_sizeof($tree_ids)) {
		foreach($tree_ids as $id) {
			input_validate_input_number($id);
		}

		db_execute_prepared('UPDATE graph_tree
			SET enabled="",
			last_modified=NOW(),
			modified_by = ?
			WHERE ' . array_to_sql_or($tree_ids, 'id'),
			array($user_id));

		return true;
	} else {
		return false;
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
		cacti_log('ERROR: Copy node requires either a host or a graph, Function copy_node', false);
		return;
	}

	if (isset($data['host']) && ($data['host'] < 0 || !is_numeric($data['host']))) {
		cacti_log('ERROR: Copy node host data invalid, Function copy_node', false);
		return;
	} elseif(isset($data['site']) && ($data['site'] < 0 || !is_numeric($data['site']))) {
		cacti_log('ERROR: Copy node site data invalid, Function copy_node', false);
		return;
	} elseif(isset($data['graph']) && ($data['graph'] < 0 || !is_numeric($data['graph']))) {
		cacti_log('ERROR: Copy node graph data invalid, Function copy_node', false);
		return;
	}

	if (!isset($pdata['leaf_id']) || $pdata['leaf_id'] < 0 || !is_numeric($pdata['leaf_id'])) {
		cacti_log('ERROR: Copy node parent data invalid, Function copy_node', false);
		return;
	}

	// Check to see if the node already exists
	$title = '';
	if ($data['host'] > 0) {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE parent = ?
			AND graph_tree_id = ?
			AND host_id = ?',
			array($pdata['leaf_id'], $tree_id, $data['host']));

		if ($exists) {
			print 'tbranch:' . $exists;
			return;
		}
	} elseif ($data['graph'] > 0) {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE parent = ?
			AND graph_tree_id = ?
			AND local_graph_id = ?',
			array($pdata['leaf_id'], $tree_id, $data['graph']));

		if ($exists) {
			print 'tbranch:' . $exists;
			return;
		}
	} elseif ($data['site'] > 0) {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE parent = ?
			AND graph_tree_id = ?
			AND site_id = ?',
			array($pdata['leaf_id'], $tree_id, $data['site']));

		if ($exists) {
			print 'tbranch:' . $exists;
			return;
		}
	} else {
		$title = db_fetch_cell_prepared('SELECT title
			FROM graph_tree_items
			WHERE id = ?', array($data['leaf_id']));
	}

	$save = array();
	$save['parent']             = $pdata['leaf_id'];
	$save['position']           = $new_position;
	$save['graph_tree_id']      = $tree_id;
	$save['local_graph_id']     = $data['graph'];
	$save['host_id']            = $data['host'];
	$save['site_id']            = $data['site'];
	$save['host_grouping_type'] = 1;
	$save['sort_children_type'] = TREE_ORDERING_INHERIT;
	$save['title']              = $title;

	$id = sql_save($save, 'graph_tree_items');

	api_tree_sort_branch($id, $tree_id);

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
		} else {
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

/* api_tree_create_node - given a tree, a desintation leaf_id, order position, and title, create a branch/leaf.
 * @arg $tree_id - The tree to remove from
 * @arg $node_id - The branch/leaf to place the new branch/leaf
 * @arg $title - The new brnach/leaf title
 * @returns - json encoded new leaf information */
function api_tree_create_node($tree_id, $node_id, $position, $title = '') {
	input_validate_input_number($tree_id);
	input_validate_input_number($position);

	if ($title == '') {
		$title = __('New Branch');
	}

	$data  = api_tree_parse_node_data($node_id);

	if ($data['leaf_id'] < 0) {
		cacti_log("ERROR: Invalid BranchID: '" . (isset($data['leaf_id']) ? $data['leaf_id']:'-') . "', Function create_node", false);
		return;
	}

	$i     = 0;
	$found = false;
	$orig  = $title;

	while(true) {
		$title = $orig . ($found ? ' (' . $i . ')':'');
		$exists_id = api_tree_branch_exists($tree_id, $data['leaf_id'], $title);

		if ($exists_id == false) {
			break;
		} else {
			$found = true;
			$i++;
		}
	}

	/* watch out for monkey business */
	input_validate_input_number($data['leaf_id']);

	$save = array();
	$save['parent']             = $data['leaf_id'];
	$save['position']           = $position;
	$save['graph_tree_id']      = $tree_id;
	$save['local_graph_id']     = 0;
	$save['host_id']            = 0;
	$save['site_id']            = 0;
	$save['host_grouping_type'] = 1;
	$save['sort_children_type'] = TREE_ORDERING_INHERIT;
	$save['title']              = $title;

	$id = sql_save($save, 'graph_tree_items');

	api_tree_sort_branch($id, $tree_id);

	header('Content-Type: application/json; charset=utf-8');
	print json_encode(array('id' => 'tbranch:' . $id, 'text' => $title));
}

/* api_tree_branch_exists - given a tree, parent branch, and a title, will check for a branch
 * @arg $tree_id - The tree_id to search
 * @arg $parent - The parent leaf_id to search
 * @arg $title - The branch name to search for
 * @returns - the id of the branch if it exists */
function api_tree_branch_exists($tree_id, $parent, $title) {
	$id = db_fetch_cell_prepared('SELECT id
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		AND title = ?',
		array($tree_id, $parent, $title));

	if ($id > 0) {
		return $id;
	} else {
		return false;
	}
}

/* api_tree_site_exists - given a tree, parent branch, and a host_id, will check host on that branch
 * @arg $tree_id - The tree_id to search
 * @arg $parent - The parent leaf_id to search
 * @arg $site_id - The host_id to search for
 * @returns - the id of the leaf if it exists */
function api_tree_site_exists($tree_id, $parent, $site_id) {
	$id = db_fetch_cell_prepared('SELECT id
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		AND site_id = ?',
		array($tree_id, $parent, $site_id));

	if ($id > 0) {
		return $id;
	} else {
		return false;
	}
}

/* api_tree_host_exists - given a tree, parent branch, and a host_id, will check host on that branch
 * @arg $tree_id - The tree_id to search
 * @arg $parent - The parent leaf_id to search
 * @arg $host_id - The host_id to search for
 * @returns - the id of the leaf if it exists */
function api_tree_host_exists($tree_id, $parent, $host_id) {
	$id = db_fetch_cell_prepared('SELECT id
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		AND host_id = ?',
		array($tree_id, $parent, $host_id));

	if ($id > 0) {
		return $id;
	} else {
		return false;
	}
}

/* api_tree_graph_exists - given a tree, parent branch, and a local_graph_id, will check graph on that branch
 * @arg $tree_id - The tree_id to search
 * @arg $parent - The parent leaf_id to search
 * @arg $local_graph_id - The local_graph_id to search for
 * @returns - the id of the leaf if it exists */
function api_tree_graph_exists($tree_id, $parent, $local_graph_id) {
	$id = db_fetch_cell_prepared('SELECT id
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		AND local_graph_id = ?',
		array($tree_id, $parent, $local_graph_id));

	if ($id > 0) {
		return $id;
	} else {
		return false;
	}
}

/* api_tree_delete - given a tree and a branch/leaf, delete the node and it's content
 * @arg $tree_id - The tree to remove from
 * @arg $leaf_id - The branch to remove
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

	if (isset($data['leaf_id']) && $data['leaf_id'] > 0) {
		if ($data['host'] == 0 && $data['graph'] == 0 && $data['site'] == 0) {
			api_tree_delete_node_content($tree_id, $data['leaf_id']);
		}

		db_execute_prepared('DELETE FROM graph_tree_items
			WHERE graph_tree_id = ?
			AND id = ?',
			array($tree_id, $data['leaf_id']));
	}
}

/* api_tree_delete_content - given a tree and a branch/leaf, recursively remove all elements
 * @arg $tree_id - The tree to remove from
 * @arg $leaf_id - The branch to remove
 * @returns - null */
function api_tree_delete_node_content($tree_id, $leaf_id) {
	$children = db_fetch_assoc_prepared('SELECT *
		FROM graph_tree_items
		WHERE graph_tree_id = ? AND parent = ?', array($tree_id, $leaf_id));

	if (cacti_sizeof($children)) {
		foreach($children as $child) {
			if ($child['host_id'] == 0 && $child['local_graph_id'] == 0) {
				api_tree_delete_node_content($tree_id, $child['id']);
			}

			db_execute_prepared('DELETE
				FROM graph_tree_items
				WHERE graph_tree_id = ?
				AND id = ?', array($tree_id, $child['id']));
		}
	}
}

/* api_tree_move_node - given the current node information and it's new branch, move it.
 * @arg $variable - The request variable to parse
 * @returns - array of information about the variable */
function api_tree_move_node($tree_id, $node_id, $new_parent, $new_position) {
	input_validate_input_number($tree_id);
	input_validate_input_number($new_position);

	$new_position++;

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
	} elseif ($new_parent == '#') {
		$pdata['leaf_id'] = 0;
	} else {
		$pdata = api_tree_parse_node_data($new_parent);
	}

	$data  = api_tree_parse_node_data($node_id);

	if ($data['parent'] != $pdata['leaf_id']) {
		db_execute_prepared('UPDATE graph_tree_items
			SET parent = ?, position = ?
			WHERE id = ?
			AND graph_tree_id = ?',
			array($pdata['leaf_id'], $new_position, $data['leaf_id'], $tree_id));

		$others = db_fetch_assoc_prepared('SELECT id
			FROM graph_tree_items
			WHERE parent = ?
			AND id != ?
			AND position >= ?', array($pdata['leaf_id'], $data['leaf_id'], $new_position));

		$position = $new_position + 1;
		if (cacti_sizeof($others)) {
			foreach($others as $other) {
				db_execute_prepared('UPDATE graph_tree_items SET position = ? WHERE id = ?', array($position, $other['id']));
				$position++;
			}
		}

		api_tree_sort_branch($data['leaf_id'], $tree_id);
	} elseif (isset($data['leaf_id']) && $data['leaf_id'] > 0 && isset($pdata['leaf_id']) && $pdata['leaf_id'] >= 0) {
		db_execute_prepared('UPDATE graph_tree_items
			SET position = ?
			WHERE graph_tree_id = ?
			AND id = ?',
			array($new_position, $tree_id, $data['leaf_id']));

		$others = db_fetch_assoc_prepared('SELECT id
			FROM graph_tree_items
			WHERE parent = ?
			AND id != ? AND
			position >= ?', array($pdata['leaf_id'], $data['leaf_id'], $new_position));

		$position = $new_position + 1;
		if (cacti_sizeof($others)) {
			foreach($others as $other) {
				db_execute_prepared('UPDATE graph_tree_items SET position = ? WHERE id = ?', array($position, $other['id']));
				$position++;
			}
		}

		api_tree_sort_branch($data['leaf_id'], $tree_id);
	} else {
		cacti_log('Invalid Source Destination Branches, Function move_node', false);
	}

	return;
}

/* api_tree_parse_node_data - given the node information parse into a branch, parent, host, graph array
 * @arg $variable - The request variable to parse
 * @returns - array of information about the variable */
function api_tree_parse_node_data($variable) {
	// Initialize some variables
	$leaf_id   = 0;
	$graph_id  = 0;
	$host_id   = 0;
	$site_id   = 0;

	if ($variable != '#') {
		// Process the 'id' variable
		$ndata   = explode('_', $variable);
		if (cacti_sizeof($ndata)) {
			foreach($ndata as $data) {
				list($type, $tid) = explode(':', $data);

				/* watch out for monkey business */
				input_validate_input_number($tid);

				switch ($type) {
					case 'tbranch':
						$leaf_id  = $tid;
						break;
					case 'tgraph':
						$graph_id = $tid;
						break;
					case 'thost':
						$host_id  = $tid;
						break;
					case 'tsite':
						$site_id  = $tid;
						break;
				}
			}
		}
	}

	if ($leaf_id > 0) {
		$parent = db_fetch_cell_prepared('SELECT parent
			FROM graph_tree_items
			WHERE id = ?',
			array($leaf_id));
	} else {
		$parent = '0';
	}

	return array('leaf_id' => $leaf_id, 'graph' => $graph_id, 'host' => $host_id, 'site' => $site_id, 'parent' => $parent);
}

/* api_tree_rename_node - given the tree and the node information rename the tree branch/leaf.
 * This function is used for editing.
 * @arg $tree_id - The id of the tree you are parsing
 * @arg $node_id - The branch/leaf id of the node to be renamed
 * @arg $title - The new branch/leaf title
 * @returns - string of the tree items in html format */
function api_tree_rename_node($tree_id, $node_id = '', $title = '') {
	input_validate_input_number($tree_id);

	// Basic Error Checking
	if ($tree_id <= 0) {
		cacti_log("ERROR: Invalid TreeID: '" . $tree_id . "', Function rename_node", false);

		header('Content-Type: application/json; charset=utf-8');
		print json_encode(array('id' => $node_id, 'result' => false));

		return;
	}

	if (empty($node_id)) {
		cacti_log("ERROR: Invalid NodeID: '" . $node_id . "', Function rename_node", false);

		header('Content-Type: application/json; charset=utf-8');
		print json_encode(array('id' => $node_id, 'result' => 'false'));

		return;
	}

	// Initialize some variables
	$leaf_id  = 0;
	$graph_id = 0;
	$host_id  = 0;
	$site_id  = 0;

	// Process the 'id' variable
	$ndata = explode('_', $node_id);
	if (cacti_sizeof($ndata)) {
		foreach($ndata as $data) {
			if (strpos($data, ':') === false) {
				cacti_log("ERROR: Invalid NodeID: '" . $node_id . "', Function rename_node", false);

				header('Content-Type: application/json; charset=utf-8');
				print json_encode(array('id' => $node_id, 'result' => 'false'));

				return;
			}

			list($type, $tid) = explode(':', $data);

			/* watch out for monkey business */
			input_validate_input_number($tid);

			switch ($type) {
				case 'tbranch':
					$leaf_id  = $tid;
					break;
				case 'tsite':
					$site_id = $tid;
					break;
				case 'tgraph':
					$graph_id = $tid;
					break;
				case 'thost':
					$host_id  = $tid;
					break;
			}
		}
	}

	if (isset($leaf_id) && $leaf_id > 0) {
		if ($host_id > 0 || $graph_id > 0 || $site_id > 0) {
			// Ignore.  Need to customize context
		} else {
			db_execute_prepared('UPDATE graph_tree_items
				SET title = ?
				WHERE graph_tree_id = ?
				AND id = ?', array($title, $tree_id, $leaf_id));
		}
	}

	header('Content-Type: application/json; charset=utf-8');
	print json_encode(array('id' => $node_id, 'result' => 'true'));
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
			$name = db_fetch_cell_prepared('SELECT name
				FROM graph_tree
				WHERE id = ?',
				array($tree_id));

			print "<ul><li class='jstree-closed' id='tree_anchor-$tree_id' data-jstree='{ \"type\" : \"tree\" }'><a href='" . html_escape('graph_view.php?action=tree&node=tree_anchor-' . $tree_id). "'>" . html_escape($name) . "</a>\n";

			$heirarchy = draw_dhtml_tree_level_graphing($tree_id, $parent);

			if (cacti_sizeof($heirarchy)) {
				foreach($heirarchy as $h) {
					print $h;
				}
			}
		} else {
			$heirarchy = draw_dhtml_tree_level_graphing($tree_id, $parent);

			if (cacti_sizeof($heirarchy)) {
				foreach($heirarchy as $h) {
					print $h;
				}
			}
		}
	} else {
		$heirarchy = draw_dhtml_tree_level_graphing($tree_id, $parent);

		if (cacti_sizeof($heirarchy)) {
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
 * @arg $editing - The determine if we are building a user tree or a tree for editing
 * @returns - string of the tree items in html format */
function api_tree_get_node($tree_id, $node_id, $editing = false) {
	if ($node_id == '#') {
		$heirarchy = draw_dhtml_tree_level($tree_id, 0, $editing);
	} else {
		$data  = api_tree_parse_node_data($node_id);
		$id    = $data['leaf_id'];

		input_validate_input_number($id);
		$heirarchy = draw_dhtml_tree_level($tree_id, $id, $editing);
	}

	if (cacti_sizeof($heirarchy)) {
		$hh = '';
		foreach($heirarchy as $h) {
			$hh .= $h;
		}

		return $hh;
	}
}

/** api_tree_item_save - saves the tree object and then resorts the tree
 * @arg $id - the leaf_id for the object
 * @arg $tree_id - the tree id for the object
 * @arg $type - the item type graph, host, leaf
 * @arg $parent_tree_item_id - The parent leaf for the object
 * @arg $title - The leaf title in the caseo a leaf
 * @arg $local_graph_id - The graph id in the case of a graph
 * @arg $host_id - The host id in the case of a graph
 * @arg $site_id - The site id in the case of a graph
 * @arg $host_grouping_type - The sort order for the host under expanded hosts
 * @arg $sort_children_type - The sort type in the case of a leaf
 * @arg $propagate_changes - Wether the changes should be cascaded through all children
 * @returns - boolean true or false depending on the outcome of the operation */
function api_tree_item_save($id, $tree_id, $type, $parent_tree_item_id, $title, $local_graph_id,
	$host_id, $site_id, $host_grouping_type, $sort_children_type, $propagate_changes) {
	global $config;

	input_validate_input_number($tree_id);
	input_validate_input_number($parent_tree_item_id);

	//api_tree_get_lock('tree-lock', 10);

	if ($local_graph_id > 0) {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE local_graph_id = ?
			AND parent = ?
			AND graph_tree_id = ?',
			array($local_graph_id, $parent_tree_item_id, $tree_id));

		if ($exists) {
			return false;
		}
	} elseif ($site_id > 0) {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE site_id = ?
			AND parent = ?
			AND graph_tree_id = ?',
			array($site_id, $parent_tree_item_id, $tree_id));

		if ($exists) {
			return false;
		}
	} elseif ($host_id > 0) {
		$exists = db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE host_id = ?
			AND parent = ?
			AND graph_tree_id = ?',
			array($host_id, $parent_tree_item_id, $tree_id));

		if ($exists) {
			return false;
		}
	}

	$save['id']                 = $id;
	$save['graph_tree_id']      = $tree_id;
	$save['title']              = form_input_validate($title, 'title', '', ($type == TREE_ITEM_TYPE_HEADER ? false : true), 3);
	$save['parent']             = $parent_tree_item_id;
	$save['local_graph_id']     = form_input_validate($local_graph_id, 'local_graph_id', '', true, 3);
	$save['host_id']            = form_input_validate($host_id, 'host_id', '', true, 3);
	$save['host_grouping_type'] = form_input_validate($host_grouping_type, 'host_grouping_type', '', true, 3);
	$save['sort_children_type'] = form_input_validate($sort_children_type, 'sort_children_type', '', true, 3);

	$tree_item_id = 0;

	if (!is_error_message()) {
		$tree_item_id = sql_save($save, 'graph_tree_items');

		if ($tree_item_id) {
			raise_message(1);

			api_tree_sort_branch($tree_item_id, $tree_id);
		} else {
			raise_message(2);
		}
	}

	//api_tree_release_lock('tree-lock');

	return $tree_item_id;
}

/* api_tree_get_item_type - gets the type of tree item
   @arg $tree_item_id - the id of the tree item to fetch the type for
   @returns - a string reprenting the type of the tree item. valid return
     values are 'header', 'graph', and 'host' */
function api_tree_get_item_type($tree_item_id) {
	$tree_item = db_fetch_row_prepared('SELECT title, local_graph_id, site_id, host_id
		FROM graph_tree_items
		WHERE id = ?',
		array($tree_item_id));

	if (!cacti_sizeof($tree_item)) {
		return '';
	} elseif ($tree_item['local_graph_id'] > 0) {
		return 'graph';
	} elseif ($tree_item['site_id'] > 0) {
		return 'site';
	} elseif ($tree_item['host_id'] > 0) {
		return 'host';
	} elseif ($tree_item['title'] != '') {
		return 'header';
	}

	return '';
}

/* naturally_sort_graphs - deprecated - callback to naturally sort an array
 * This function is used to sort graphs and trees
 * @arg $a - first graph array to compare
 * @arg $b - second graph array to compare
 * @returns - the re-ordered arrays */
function naturally_sort_graphs($a, $b) {
	return strnatcasecmp($a['title_cache'], $b['title_cache']);
}

/* api_tree_get_branch_ordering - determine the ordering of any elements owning leaf
 * This function is to assist with ordering tree items
 * @arg $leaf_id - the leaf_id of the element
 * @returns - the ordering of the parent leaf/branch */
function api_tree_get_branch_ordering($leaf_id) {
	$leaf = db_fetch_row_prepared('SELECT sort_children_type, parent, graph_tree_id FROM graph_tree_items WHERE id = ?', array($leaf_id));

	if (cacti_sizeof($leaf)) {
		if ($leaf['sort_children_type'] == 0) {
			$parent = $leaf['parent'];

			if ($parent > 0) {
				return api_tree_get_branch_ordering($parent);
			} else {
				return db_fetch_cell_prepared('SELECT sort_type FROM graph_tree WHERE id = ?', array($leaf['graph_tree_id']));
			}
		} else {
			return $leaf['sort_children_type'];
		}
	} else {
		return 1;
	}
}

/* api_tree_get_branch_name - determine the name of a branch leaf
 * This function is to assist with editing trees
 * @arg $tree_id - the tree id
 * @arg $leaf_id - the leaf id
 * @returns - the name of the leaf */
function api_tree_get_branch_name($tree_id, $leaf_id) {
	return db_fetch_cell_prepared('SELECT title FROM graph_tree_items WHERE graph_tree_id = ? AND id = ?', array($tree_id, $leaf_id));
}

/* api_tree_get_branch_id - given a tree, parent, and title return the leaf_id
 * @arg $tree_id - the tree id
 * @arg $parent - the parent leaf id
 * @arg $title - the branch/leaf title
 * @returns - the name of the leaf */
function api_tree_get_branch_id($tree_id, $parent, $title) {
	return db_fetch_cell_prepared('SELECT id FROM graph_tree_items WHERE graph_tree_id = ? AND parent = ? AND title = ?', array($tree_id, $parent, $title));
}

/* api_tree_sort_branch - sorts a branch based upon sorting rules.
 * Trees always go first, then hosts, and finally, graphs.
 * @arg $leaf_id - the leaf id
 * @arg $tree_id - the tree id
 * @returns - the name of the leaf */
function api_tree_sort_branch($leaf_id, $tree_id = 0, $lock = true) {
	static $level = 1;

	if ($lock) {
		//api_tree_get_lock('tree-lock', 10);
	}

	// Sorting will go in this order for anyone sorting:
	// Tree Branches go first, then Devices, then Graphs
	$sequence = 1;

	if (!is_numeric($leaf_id)) {
		$data  = api_tree_parse_node_data($leaf_id);
		$leaf_id  = $data['leaf_id'];
	}

	if ($leaf_id > 0) {
		$pdata   = db_fetch_row_prepared('SELECT parent, graph_tree_id
			FROM graph_tree_items
			WHERE id = ?',
			array($leaf_id));

		$parent  = $pdata['parent'];
		$tree_id = $pdata['graph_tree_id'];
	} elseif ($tree_id > 0) {
		$parent        = 0;
	} else {
		cacti_log('Error Sorting Tree');
		return;
	}

	if ($parent > 0) {
		$sort_style = api_tree_get_branch_ordering($parent);
	} else {
		$sort_style = db_fetch_cell_prepared('SELECT sort_type
			FROM graph_tree
			WHERE id = ?',
			array($tree_id));
	}

	if ($sort_style == TREE_ORDERING_ALPHABETIC) {
		$order_by = 'ORDER BY title';
	} else {
		$order_by = 'ORDER BY position';
	}

	$sort_array = array_rekey(
		db_fetch_assoc_prepared('SELECT id, title
			FROM graph_tree_items AS gti
			WHERE parent = ?
			AND graph_tree_id = ?
			AND local_graph_id = 0
			AND host_id = 0
			AND site_id = 0 ' . $order_by,
			array($parent, $tree_id)),
		'id', 'title'
	);

	if (cacti_sizeof($sort_array)) {
		if ($sort_style == TREE_ORDERING_NUMERIC) {
			asort($sort_array, SORT_NUMERIC);
		} elseif ($sort_style == TREE_ORDERING_ALPHABETIC) {
			// Let's let the database do it!
		} elseif ($sort_style == TREE_ORDERING_NATURAL) {
			if (defined('SORT_FLAG_CASE')) {
				asort($sort_array, SORT_NATURAL | SORT_FLAG_CASE);
			} else {
				natcasesort($sort_array);
			}
		}

		foreach($sort_array as $id => $element) {
			$sort = db_fetch_cell_prepared('SELECT sort_children_type
				FROM graph_tree_items
				WHERE id = ?',
				array($id));

			if ($sort == TREE_ORDERING_INHERIT) {
				$first_child = db_fetch_cell_prepared('SELECT id
					FROM graph_tree_items
					WHERE parent = ?
					ORDER BY position
					LIMIT 1', array($id));

				if (!empty($first_child)) {
					$level++;
					api_tree_sort_branch($first_child, $tree_id, false);
				}
			}

			db_execute_prepared('UPDATE graph_tree_items
				SET position = ?
				WHERE id = ?',
				array($sequence, $id));

			$sequence++;
		}
	}

	if ($sort_style == TREE_ORDERING_ALPHABETIC) {
		$order_by = 'ORDER BY s.name';
	} else {
		$order_by = 'ORDER BY position';
	}

	$sort_array = array_rekey(
		db_fetch_assoc_prepared('SELECT s.name, gti.id
			FROM graph_tree_items AS gti
			INNER JOIN sites AS s
			ON s.id = gti.site_id
			WHERE parent = ?
			AND graph_tree_id = ?
			AND local_graph_id = 0
			AND site_id > 0 ' . $order_by,
			array($parent, $tree_id)),
		'id', 'name'
	);

	if (cacti_sizeof($sort_array)) {
		if ($sort_style == TREE_ORDERING_NUMERIC) {
			asort($sort_array, SORT_NUMERIC);
		} elseif ($sort_style == TREE_ORDERING_ALPHABETIC) {
			// Let's let the database do it!
		} elseif ($sort_style == TREE_ORDERING_NATURAL) {
			if (defined('SORT_FLAG_CASE')) {
				asort($sort_array, SORT_NATURAL | SORT_FLAG_CASE);
			} else {
				natcasesort($sort_array);
			}
		}

		foreach($sort_array as $id => $element) {
			$sort = db_fetch_cell_prepared('SELECT sort_children_type
				FROM graph_tree_items
				WHERE id = ?',
				array($id));

			if ($sort == TREE_ORDERING_INHERIT) {
				$first_child = db_fetch_cell_prepared('SELECT id
					FROM graph_tree_items
					WHERE parent = ?
					ORDER BY position
					LIMIT 1',
					array($id));

				if (!empty($first_child)) {
					$level++;
					api_tree_sort_branch($first_child, $tree_id, false);
				}
			}

			db_execute_prepared('UPDATE graph_tree_items
				SET position = ?
				WHERE id = ?',
				array($sequence, $id));

			$sequence++;
		}
	}

	if ($sort_style == TREE_ORDERING_ALPHABETIC) {
		$order_by = 'ORDER BY h.description';
	} else {
		$order_by = 'ORDER BY position';
	}

	$sort_array = array_rekey(
		db_fetch_assoc_prepared('SELECT h.description, gti.id
			FROM graph_tree_items AS gti
			INNER JOIN host AS h
			ON h.id=gti.host_id
			WHERE parent = ?
			AND graph_tree_id = ?
			AND host_id > 0 ' . $order_by,
			array($parent, $tree_id)),
		'id', 'description'
	);

	if (cacti_sizeof($sort_array)) {
		if ($sort_style == TREE_ORDERING_NUMERIC) {
			asort($sort_array, SORT_NUMERIC);
		} elseif ($sort_style == TREE_ORDERING_ALPHABETIC) {
			// Let's let the database do it!
		} elseif ($sort_style == TREE_ORDERING_NATURAL) {
			if (defined('SORT_FLAG_CASE')) {
				asort($sort_array, SORT_NATURAL | SORT_FLAG_CASE);
			} else {
				natcasesort($sort_array);
			}
		}

		foreach($sort_array as $id => $element) {
			db_execute_prepared('UPDATE graph_tree_items
				SET position = ?
				WHERE id = ?',
				array($sequence, $id));

			$sequence++;
		}
	}

	if ($sort_style == TREE_ORDERING_ALPHABETIC) {
		$order_by = 'ORDER BY gtg.title_cache';
	} else {
		$order_by = 'ORDER BY position';
	}

	$sort_array = array_rekey(
		db_fetch_assoc_prepared('SELECT gtg.title_cache, gti.id
			FROM graph_tree_items AS gti
			INNER JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id=gti.local_graph_id
			WHERE parent = ?
			AND graph_tree_id = ?
			AND gti.local_graph_id > 0 ' . $order_by,
			array($parent, $tree_id)),
		'id', 'title_cache'
	);

	if (cacti_sizeof($sort_array)) {
		if ($sort_style == TREE_ORDERING_NUMERIC) {
			asort($sort_array, SORT_NUMERIC);
		} elseif ($sort_style == TREE_ORDERING_ALPHABETIC) {
			// Let's let the database do it!
		} elseif ($sort_style == TREE_ORDERING_NATURAL) {
			if (defined('SORT_FLAG_CASE')) {
				asort($sort_array, SORT_NATURAL | SORT_FLAG_CASE);
			} else {
				natcasesort($sort_array);
			}
		}

		foreach($sort_array as $id => $element) {
			db_execute_prepared('UPDATE graph_tree_items
				SET position = ?
				WHERE id = ?',
				array($sequence, $id));

			$sequence++;
		}
	}

	if ($lock) {
		//api_tree_release_lock('tree-lock');
	}
}

/* api_tree_get_max_sequence - obtain the maximum sequence of all Cacti trees.
 * This function queries the Cacti database and obtains the maximum defined sequence for trees.
 * @returns - the maximum sequence */
function api_tree_get_max_sequence() {
	$max_seq = db_fetch_cell('SELECT MAX(sequence) FROM graph_tree');

	if ($max_seq == NULL) {
		return 0;
	}

	return $max_seq;
}

/* api_tree_check_sequence - verify all tree sequences are correct
 * This function queries the Cacti database and verifies and/or corrects sequences of trees.
 * @returns - null */
function api_tree_check_sequences() {
	$bad_seq = db_fetch_cell('SELECT COUNT(sequence)
		FROM graph_tree
		WHERE sequence <= 0');

	$dup_seq = db_fetch_cell('SELECT SUM(count)
		FROM (
			SELECT sequence, COUNT(sequence) AS count
			FROM graph_tree
			GROUP BY sequence
		) AS t
		WHERE t.count > 1');

	// report any bad or duplicate sequencs to the log for reporting purposes
	if ($bad_seq > 0) {
		cacti_log('WARN: Found ' . $bad_seq . ' Sequences in graph_tree Table', false, 'TREE', POLLER_VERBOSITY_HIGH);
	}

	if ($dup_seq > 0) {
		cacti_log('WARN: Found ' . $dup_seq . ' Sequences in graph_tree Table', false, 'TREE', POLLER_VERBOSITY_HIGH);
	}

	if ($bad_seq > 0 || $dup_seq > 0) {
		// resequence the list so it has no gaps, and 0 values will appear at the top
		// since thats where they would have been displayed
		db_execute('SET @seq = 0; UPDATE graph_tree SET sequence = (@seq:=@seq+1) ORDER BY sequence, id;');
	}
}

/* api_tree_sort_name_asc - sort trees by name ascending
 * This function queries the Cacti database sorts all trees by name ascending.
 * @returns - null */
function api_tree_sort_name_asc() {
	// resequence the list so it has no gaps, alphabetically ascending
	db_execute('SET @seq = 0; UPDATE graph_tree SET sequence = (@seq:=@seq+1) ORDER BY name;');
}

/* api_tree_sort_name_desc - sort trees by name descending
 * This function queries the Cacti database sorts all trees by name descending.
 * @returns - null */
function api_tree_sort_name_desc() {
	// resequence the list so it has no gaps, alphabetically ascending
	db_execute('SET @seq = 0; UPDATE graph_tree SET sequence = (@seq:=@seq+1) ORDER BY name DESC;');
}

/* api_tree_down - moves a tree down one sequence
 * This function queries the Cacti database and move a tree down one sequence.
 * @returns - null */
function tree_down($tree_id) {
	/* ================= input validation ================= */
	input_validate_input_number($tree_id);
	/* ==================================================== */

	api_tree_check_sequences();

	$seq = db_fetch_cell_prepared('SELECT sequence
		FROM graph_tree
		WHERE id = ?',
		array($tree_id));

	$new_seq = $seq + 1;

	/* update the old tree first */
	db_execute_prepared('UPDATE graph_tree
		SET sequence = ?
		WHERE sequence = ?',
		array($seq, $new_seq));

	/* update the tree in question */
	db_execute_prepared('UPDATE graph_tree
		SET sequence = ?
		WHERE id = ?',
		array($new_seq, $tree_id));
}

/* api_tree_up - moves a tree up one sequence
 * This function queries the Cacti database and move a tree up one sequence.
 * @returns - null */
function tree_up($tree_id) {
	/* ================= input validation ================= */
	input_validate_input_number($tree_id);
	/* ==================================================== */

	api_tree_check_sequences();

	$seq = db_fetch_cell_prepared('SELECT sequence
		FROM graph_tree
		WHERE id = ?',
		array($tree_id));

	$new_seq = $seq - 1;

	/* update the old tree first */
	db_execute_prepared('UPDATE graph_tree
		SET sequence = ?
		WHERE sequence = ?',
		array($seq, $new_seq));

	/* update the tree in question */
	db_execute_prepared('UPDATE graph_tree
		SET sequence = ?
		WHERE id = ?',
		array($new_seq, $tree_id));
}

/* api_tree_dnd - sort Cacti trees by id
 * This function given an array of Cacti tree ids, will sort the trees by that array.
 * @returns - null */
function api_tree_dnd($tree_ids) {
	$tids     = $tree_ids;
	$sequence = 1;

	foreach($tids as $id) {
		$id = str_replace('line', '', $id);
		input_validate_input_number($id);

		db_execute_prepared('UPDATE graph_tree
			SET sequence = ?
			WHERE id = ?',
			array($sequence, $id));

		$sequence++;
	}
}

/* api_tree_get_host_sort_type - return the sort type of a host in a tree
 * This function given a Cacti tree's nodeid, will return the sort type.
 * @returns - the sort type */
function api_tree_get_host_sort_type($nodeid) {
	if (!empty($nodeid)) {
		$ndata = explode('_', $nodeid);

		if (cacti_sizeof($ndata)) {
			foreach($ndata as $n) {
				$parts = explode(':', $n);

				if (isset($parts[0]) && $parts[0] == 'tbranch') {
					$branch = $parts[1];
					input_validate_input_number($branch);

					$sort_type = db_fetch_cell_prepared('SELECT host_grouping_type
						FROM graph_tree_items
						WHERE id = ?',
						array($branch));

					if ($sort_type == HOST_GROUPING_GRAPH_TEMPLATE) {
						return 'hsgt';
					} else {
						return 'hsdq';
					}
				}
			}
		}
	} else {
		return '';
	}
}

/* api_tree_set_host_sort_type - set the sort type of a host in a tree
 * This function given a Cacti tree's nodeid, will return the sort type.
 * @returns - null */
function api_tree_set_host_sort_type($nodetype, $nodeid) {
	$type   = '';
	$branch = '';
	$ndata  = explode('_', $nodeid);

	if (cacti_sizeof($ndata)) {
		foreach($ndata as $n) {
			$parts = explode(':', $n);

			if (isset($parts[0]) && $parts[0] == 'tbranch') {
				$branch = $parts[1];
				input_validate_input_number($branch);

				if ($nodetype == 'hsgt') {
					$type = HOST_GROUPING_GRAPH_TEMPLATE;
				} else {
					$type = HOST_GROUPING_DATA_QUERY_INDEX;
				}

				db_execute_prepared('UPDATE graph_tree_items
					SET host_grouping_type = ?
					WHERE id = ?',
					array($type, $branch));

				break;
			}
		}
	}

	return;
}

/* api_tree_get_branch_sort_type - return the sort type of a branch in a tree
 * This function given a Cacti tree's nodeid, will return the sort type.
 * @returns - the sort type */
function api_tree_get_branch_sort_type($nodeid) {
	$ndata = explode('_', $nodeid);

	if ($nodeid != '') {
		if (cacti_sizeof($ndata)) {
			foreach($ndata as $n) {
				$parts = explode(':', $n);

				if (isset($parts[0]) && $parts[0] == 'tbranch') {
					$branch = $parts[1];

					input_validate_input_number($branch);

					$sort_type = db_fetch_cell_prepared('SELECT sort_children_type
						FROM graph_tree_items
						WHERE id = ?',
						array($branch));

					switch($sort_type) {
					case TREE_ORDERING_INHERIT:
						return __x('ordering of tree items', 'inherit');
						break;
					case TREE_ORDERING_NONE:
						return __x('ordering of tree items', 'manual');
						break;
					case TREE_ORDERING_ALPHABETIC:
						return __x('ordering of tree items', 'alpha');
						break;
					case TREE_ORDERING_NATURAL:
						return __x('ordering of tree items', 'natural');
						break;
					case TREE_ORDERING_NUMERIC:
						return __x('ordering of tree items', 'numeric');
						break;
					default:
						return '';
						break;
					}
					break;
				}
			}
		}
	} else {
		return '';
	}
}

/* api_tree_set_branch_sort_type - set the sort type of a branch in a tree
 * This function given a Cacti tree's nodeid, will set the sort type.
 * @returns - null */
function api_tree_set_branch_sort_type($nodetype, $nodeid) {
	$type   = '';
	$branch = '';

	if ($nodeid != '') {
		$ndata = explode('_', $nodeid);

		if (cacti_sizeof($ndata)) {
			foreach($ndata as $n) {
				$parts = explode(':', $n);

				if (isset($parts[0]) && $parts[0] == 'tbranch') {
					$branch = $parts[1];
					input_validate_input_number($branch);

					switch($nodetype) {
					case 'inherit':
						$type = TREE_ORDERING_INHERIT;
						break;
					case 'manual':
						$type = TREE_ORDERING_NONE;
						break;
					case 'alpha':
						$type = TREE_ORDERING_ALPHABETIC;
						break;
					case 'natural':
						$type = TREE_ORDERING_NATURAL;
						break;
					case 'numeric':
						$type = TREE_ORDERING_NUMERIC;
						break;
					default:
						break;
					}

					if (is_numeric($type) && is_numeric($branch)) {
						db_execute_prepared('UPDATE graph_tree_items
							SET sort_children_type = ?
							WHERE id = ?',
							array($type, $branch));
					}

					$first_child = db_fetch_row_prepared('SELECT id, graph_tree_id
						FROM graph_tree_items
						WHERE parent = ?
						ORDER BY position
						LIMIT 1',
						array($branch));

					if (!empty($first_child)) {
						api_tree_sort_branch($first_child['id'], $first_child['graph_tree_id']);
					}

					break;
				}
			}
		}
	}
}

/* api_tree_sort_recursive - sort a tree branch and its siblines recursively
 * This function given a branch id within a tree and a tree id, will sort it recursively
 * @returns - null */
function api_tree_sort_recursive($branch, $tree_id) {
	/* ================= input validation ================= */
	input_validate_input_number($branch);
	input_validate_input_number($tree_id);
	/* ==================================================== */

	$leaves = db_fetch_assoc_prepared('SELECT *
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		AND local_graph_id = 0
		AND host_id = 0',
		array($tree_id, $branch));

	if (cacti_sizeof($leaves)) {
		foreach($leaves as $leaf) {
			if ($leaf['sort_children_type'] == TREE_ORDERING_INHERIT) {
				$first_child = db_fetch_cell_prepared('SELECT id
					FROM graph_tree_items
					WHERE parent = ?',
					array($leaf['id']));

				if (!empty($first_child)) {
					api_tree_sort_branch($first_child, $tree_id);

					if (api_tree_leaves_exist($leaf['id'], $tree_id)) {
						api_tree_sort_recursive($first_child, $tree_id);
					}
				}
			}
		}
	}
}

/* api_tree_leaves_exist - return whether a parent branch has children
 * This function given a parent branch id and tree, return if a parent has children
 * @returns - true or false */
function api_tree_leaves_exist($parent, $tree_id) {
	/* ================= input validation ================= */
	input_validate_input_number($parent);
	input_validate_input_number($tree_id);
	/* ==================================================== */

	return db_fetch_assoc_prepared('SELECT COUNT(*)
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		AND local_graph_id = 0
		AND host_id = 0',
		array($tree_id, $parent));
}

