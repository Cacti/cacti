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
 * Locks a tree for editing * @arg $tree_id - the tree id * @arg $user_id - the user id * @arg
 * $web - is this a web operation * @returns - null unless in $web, in which case it redirects to
 * the page. Used as part of Cacti's lib functionality.
 *
 * @param int $tree_id The ID of the tree to lock.
 * @param int $user_id The ID of the user performing the lock operation. Default is 0.
 * @param bool $web Indicates if the operation is performed via the web interface. Default is true.
 *
 * @return void No value is returned.
 */
function api_tree_lock($tree_id, $user_id = 0, $web = true) {
	/* ================= input validation ================= */
	input_validate_input_number($tree_id);
	input_validate_input_number($user_id);
	/* ==================================================== */

	db_execute_prepared('UPDATE graph_tree
		SET `locked` = 1, locked_date = NOW(), last_modified = NOW(), modified_by = ?
		WHERE id = ?',
		array($user_id, $tree_id));
}

/**
 * Unlocks a locked tree that has been locked for editing * @arg $tree_id - the tree id * @arg
 * $user_id - the user id * @arg $web - is this a web operation * @returns - null unless in $web,
 * in which case it redirects to the page. Used as part of Cacti's lib functionality.
 *
 * @param int $tree_id The ID of the tree to unlock.
 * @param int $user_id The ID of the user performing the unlock action. Default is 0.
 * @param bool $web Indicates if the action is performed via the web interface. Default is true.
 *
 * @return void No value is returned.
 */
function api_tree_unlock($tree_id, $user_id = 0, $web = true) {
	/* ================= input validation ================= */
	input_validate_input_number($tree_id);
	input_validate_input_number($user_id);
	/* ==================================================== */

	db_execute_prepared('UPDATE graph_tree
		SET `locked` = 0, last_modified = NOW(), modified_by = ?
		WHERE id = ?',
		array($user_id, $tree_id));
}

/**
 * Given a tree id, a new node location, * it's original paren, make a copy of the prior node. *
 * @arg $tree_id - The name of the lock to be created * @arg $node_id - The node to be copied *
 * @arg $new_parent - The new parent of the copied node * @arg $new_position - The manual position
 * of the copied node * @returns - json encoded new location information. Used as part of Cacti's
 * lib functionality.
 *
 * @param int $tree_id The ID of the tree.
 * @param int|string $node_id The ID of the node to copy.
 * @param int|string $new_parent The ID of the new parent node.
 * @param int $new_position The position under the new parent node.
 *
 * @return void No value is returned.
 */
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

	/**
	 * Save the last time a tree branch was created/updated
	 * for Caching.
	 */
	set_config_option('time_last_change_branch', time());

	header('Content-Type: application/json; charset=utf-8');
	print json_encode(array('id' => 'tbranch:' . $id));
}

/**
 * Given a lock name, placed a timed lock on the database. * This function allows simulating
 * transactions in an MyISAM database. * @arg $lockname - The name of the lock to be created *
 * @returns - true depending on outcome. Used as part of Cacti's lib functionality.
 *
 * @param string $lockname The name of the lock to acquire.
 * @param int $timeout The timeout period in seconds for acquiring the lock. Default is 10 seconds.
 *
 * @return bool Returns true if the lock is acquired, false otherwise.
 */
function api_tree_get_lock($lockname, $timeout = 10) {
	input_validate_input_number($timeout);

	$lockname = sanitize_search_string($lockname);

	$count = 0;

	while ($count < 5) {
		if (register_process_start('tree_lock', $lockname, 0, $timeout)) {
			return true;
		} else {
			$count++;
			sleep(1);
		}
	}

	return false;
}

/**
 * Given a lock name, release that lock. * This function allows simulating transactions in an
 * MyISAM database. * @arg $lockname - The name of the lock to be released * @returns - true or
 * false depending on outcome. Used as part of Cacti's lib functionality.
 *
 * @param string $lockname The name of the lock to be released.
 *
 * @return void No value is returned.
 */
function api_tree_release_lock($lockname) {
	unregister_process('tree_lock', $lockname, 0);
}

/**
 * Given a tree, a destination leaf_id, order position, and title, create a branch/leaf. * @arg
 * $tree_id - The tree to remove from * @arg $node_id - The branch/leaf to place the new
 * branch/leaf * @arg $title - The new branch/leaf title * @returns - json encoded new leaf
 * information. Used as part of Cacti's lib functionality.
 *
 * @param int $tree_id The ID of the tree where the node will be created.
 * @param mixed $node_id The ID of the node to be created.
 * @param int $position The position of the node within the tree.
 * @param string $title The title of the node. Defaults to 'New Branch' if not provided.
 *
 * @return bool Bool.
 */
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

	/**
	 * Save the last time a tree branch was created/updated
	 * for Caching.
	 */
	set_config_option('time_last_change_branch', time());

	header('Content-Type: application/json; charset=utf-8');
	print json_encode(array('id' => 'tbranch:' . $id, 'text' => $title));
}

/**
 * Given a tree, parent branch, and a title, will check for a branch * @arg $tree_id - The tree_id
 * to search * @arg $parent - The parent leaf_id to search * @arg $title - The branch name to
 * search for * @returns - the id of the branch if it exists. Used as part of Cacti's lib
 * functionality.
 *
 * @param int $tree_id The ID of the tree to check.
 * @param int $parent The parent ID of the branch to check.
 * @param string $title The title of the branch to check.
 *
 * @return mixed The ID of the branch if it exists, or false if it does not.
 */
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

/**
 * Given a tree, parent branch, and a host_id, will check host on that branch * @arg $tree_id -
 * The tree_id to search * @arg $parent - The parent leaf_id to search * @arg $site_id - The
 * host_id to search for * @returns - the id of the leaf if it exists. Used as part of Cacti's lib
 * functionality.
 *
 * @param int $tree_id The ID of the tree.
 * @param int $parent The ID of the parent item.
 * @param int $site_id The ID of the site.
 *
 * @return int|false The ID of the site if it exists, or false if it does not exist.
 */
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

/**
 * Given a tree, parent branch, and a host_id, will check host on that branch * @arg $tree_id -
 * The tree_id to search * @arg $parent - The parent leaf_id to search * @arg $host_id - The
 * host_id to search for * @returns - the id of the leaf if it exists. Used as part of Cacti's lib
 * functionality.
 *
 * @param int $tree_id The ID of the tree to check.
 * @param int $parent The ID of the parent under which to check for the host.
 * @param int $host_id The ID of the host to check for.
 *
 * @return int|false The ID of the host if it exists, or false if it does not.
 */
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

/**
 * Given a tree, parent branch, and a local_graph_id, will check graph on that branch * @arg
 * $tree_id - The tree_id to search * @arg $parent - The parent leaf_id to search * @arg
 * $local_graph_id - The local_graph_id to search for * @returns - the id of the leaf if it
 * exists. Used as part of Cacti's lib functionality.
 *
 * @param int $tree_id The ID of the tree to check.
 * @param int $parent The parent ID within the tree.
 * @param int $local_graph_id The local graph ID to check for.
 *
 * @return int|false The ID of the graph if it exists, or false if it does not.
 */
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

/**
 * Given a tree and a branch/leaf, delete the node and it's content * @arg $tree_id - The tree to
 * remove from * @arg $leaf_id - The branch to remove * @returns - null. Used as part of Cacti's
 * lib functionality.
 *
 * @param int $tree_id The ID of the tree from which the node will be deleted.
 * @param int|string $node_id The ID of the node to be deleted.
 *
 * @return void No value is returned.
 */
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

		/**
	 	 * Save the last time a tree branch was created/updated
		 * for Caching.
		 */
		set_config_option('time_last_change_branch', time());
	}
}

/**
 * Given a tree and a branch/leaf, recursively remove all elements * @arg $tree_id - The tree to
 * remove from * @arg $leaf_id - The branch to remove * @returns - null. Used as part of Cacti's
 * lib functionality.
 *
 * @param int $tree_id The ID of the tree from which the node content is to be deleted.
 * @param int $leaf_id The ID of the node whose content is to be deleted.
 *
 * @return void No value is returned.
 */
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

			/**
	 	 	 * Save the last time a tree branch was created/updated
			 * for Caching.
			 */
			set_config_option('time_last_change_branch', time());
		}
	}
}

/**
 * Given the current node information and it's new branch, move it. * @arg $variable - The request
 * variable to parse * @returns - array of information about the variable. Used as part of Cacti's
 * lib functionality.
 *
 * @param int $tree_id The ID of the tree.
 * @param int|string $node_id The ID of the node to move.
 * @param int|string $new_parent The ID of the new parent node or '#' for the root.
 * @param int $new_position The new position of the node within the new parent.
 *
 * @return void No value is returned.
 */
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

		/**
 	 	 * Save the last time a tree branch was created/updated
		 * for Caching.
		 */
		set_config_option('time_last_change_branch', time());

		api_tree_sort_branch($data['leaf_id'], $tree_id);
	} else {
		cacti_log('Invalid Source Destination Branches, Function move_node', false);
	}

	return;
}

/**
 * Given the node information parse into a branch, parent, host, graph array * @arg $variable -
 * The request variable to parse * @returns - array of information about the variable. Used as
 * part of Cacti's lib functionality.
 *
 * @param string $variable The variable containing the node data to be parsed. If the variable is
 *   '#', it is treated as a special case.
 *
 * @return array An associative array containing the parsed node data.
 */
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

/**
 * Given the tree and the node information rename the tree branch/leaf. * This function is used
 * for editing. * @arg $tree_id - The id of the tree you are parsing * @arg $node_id - The
 * branch/leaf id of the node to be renamed * @arg $title - The new branch/leaf title * @returns -
 * string of the tree items in html format.
 *
 * @param int $tree_id The ID of the tree.
 * @param string|null $node_id The ID of the node to rename. Defaults to an empty string.
 * @param string $title The new title for the node. Defaults to an empty string.
 *
 * @return void No value is returned.
 */
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

/**
 * Given the tree and the parent node information return tree elements. * This function is used
 * for graphing. * @arg $tree_id - The id of the tree you are parsing * @arg $parent - The parent
 * id of the branch/leaf * @returns - string of the tree items in html format.
 *
 * @param mixed $tree_id The ID of the tree to retrieve. Can be null.
 * @param int $parent The parent node ID. Defaults to 0. If -1, it indicates the root node.
 *
 * @return void No value is returned.
 */
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

			$hierarchy = draw_dhtml_tree_level_graphing($tree_id, $parent);

			if (cacti_sizeof($hierarchy)) {
				foreach($hierarchy as $h) {
					print $h;
				}
			}
		} else {
			$hierarchy = draw_dhtml_tree_level_graphing($tree_id, $parent);

			if (cacti_sizeof($hierarchy)) {
				foreach($hierarchy as $h) {
					print $h;
				}
			}
		}
	} else {
		$hierarchy = draw_dhtml_tree_level_graphing($tree_id, $parent);

		if (cacti_sizeof($hierarchy)) {
			foreach($hierarchy as $h) {
				print $h;
			}
		}
	}

	if ($is_root) {
		print "</li></ul>\n";
	}
}

/**
 * Given the tree and the node information return tree elements * @arg $tree_id - The id of the
 * tree you are parsing * @arg $node_id - The encoded node id of the branch/leaf * @arg $editing -
 * The determine if we are building a user tree or a tree for editing * @returns - string of the
 * tree items in html format. Used as part of Cacti's lib functionality.
 *
 * @param int $tree_id The ID of the tree.
 * @param string $node_id The ID of the node. If the node ID is '#', the top-level hierarchy is
 *   fetched.
 * @param bool $editing Whether the tree is being edited. Default is false.
 *
 * @return void No value is returned.
 */
function api_tree_get_node($tree_id, $node_id, $editing = false) {
	if ($node_id == '#') {
		$hierarchy = draw_dhtml_tree_level($tree_id, 0, $editing);
	} else {
		$data  = api_tree_parse_node_data($node_id);
		$id    = $data['leaf_id'];

		input_validate_input_number($id);
		$hierarchy = draw_dhtml_tree_level($tree_id, $id, $editing);
	}

	if (cacti_sizeof($hierarchy)) {
		foreach($hierarchy as $h) {
			print $h;
		}
	}
}

/**
 * Saves the tree object and then resorts the tree. Used as part of Cacti's lib functionality.
 *
 * @param int $id The leaf_id for the object.
 * @param int $tree_id The tree id for the object.
 * @param int $type The item type graph, host, leaf.
 * @param int $parent_tree_item_id The parent leaf for the object.
 * @param string $title The leaf title in the case of a leaf.
 * @param int $local_graph_id The graph id in the case of a graph.
 * @param int $host_id The host id in the case of a graph.
 * @param int $site_id The site id in the case of a graph.
 * @param int $host_grouping_type The sort order for the host under expanded hosts.
 * @param int $sort_children_type The sort type in the case of a leaf.
 * @param bool $propagate_changes Whether the changes should be cascaded through all children.
 *
 * @return int|false True or false depending on the outcome of the operation.
 */
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

		if (empty($save['id'])) {
			/**
			 * Save the last time a tree branch was created/updated
			 * for Caching.
			 */
			set_config_option('time_last_change_branch', time());
		}
	}

	//api_tree_release_lock('tree-lock');

	return $tree_item_id;
}

/**
 * Gets the type of tree item. Used as part of Cacti's lib functionality.
 *
 * @param int $tree_item_id The id of the tree item to fetch the type for.
 *
 * @return string A string representing the type of the tree item. valid return values are
 *   'header', 'graph', and 'host'.
 */
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

/**
 * Deprecated - callback to naturally sort an array * This function is used to sort graphs and
 * trees * @arg $a - first graph array to compare * @arg $b - second graph array to compare *
 * @returns - the re-ordered arrays.
 *
 * @param array $a The first graph data array to compare.
 * @param array $b The second graph data array to compare.
 *
 * @return int Returns < 0 if $a is less than $b; > 0 if $a is greater than $b, and 0 if they are
 *   equal.
 *
 * @deprecated
 */
function naturally_sort_graphs($a, $b) {
	return strnatcasecmp($a['title_cache'], $b['title_cache']);
}

/**
 * Determine the ordering of any elements owning leaf * This function is to assist with ordering
 * tree items * @arg $leaf_id - the leaf_id of the element * @returns - the ordering of the parent
 * leaf/branch. Used as part of Cacti's lib functionality.
 *
 * @param int $leaf_id The ID of the leaf node for which to retrieve the branch ordering type.
 *
 * @return int The branch ordering type. Returns 1 if the leaf node is not found.
 */
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

/**
 * Determine the name of a branch leaf * This function is to assist with editing trees * @arg
 * $tree_id - the tree id * @arg $leaf_id - the leaf id * @returns - the name of the leaf. Used as
 * part of Cacti's lib functionality.
 *
 * @param int $tree_id The ID of the tree.
 * @param int $leaf_id The ID of the leaf within the tree.
 *
 * @return string The title of the branch.
 */
function api_tree_get_branch_name($tree_id, $leaf_id) {
	return db_fetch_cell_prepared('SELECT title FROM graph_tree_items WHERE graph_tree_id = ? AND id = ?', array($tree_id, $leaf_id));
}

/**
 * Given a tree, parent, and title return the leaf_id * @arg $tree_id - the tree id * @arg $parent
 * - the parent leaf id * @arg $title - the branch/leaf title * @returns - the name of the leaf.
 * Used as part of Cacti's lib functionality.
 *
 * @param int $tree_id The ID of the tree.
 * @param int $parent The parent ID within the tree.
 * @param string $title The title of the branch.
 *
 * @return int|null The ID of the branch if found, or null if not found.
 */
function api_tree_get_branch_id($tree_id, $parent, $title) {
	return db_fetch_cell_prepared('SELECT id FROM graph_tree_items WHERE graph_tree_id = ? AND parent = ? AND title = ?', array($tree_id, $parent, $title));
}

/**
 * Sorts a branch based upon sorting rules. * Trees always go first, then hosts, and finally,
 * graphs. * @arg $leaf_id - the leaf id * @arg $tree_id - the tree id * @returns - the name of
 * the leaf. Used as part of Cacti's lib functionality.
 *
 * @param int|string $leaf_id The ID of the leaf or a string representing the node data.
 * @param int $tree_id The ID of the tree. Default is 0.
 * @param bool $lock Whether to lock the tree during sorting. Default is true.
 *
 * @return void No value is returned.
 */
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
