<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

include('./include/auth.php');
include_once('./lib/api_tree.php');
include_once('./lib/html_tree.php');
include_once('./lib/data_query.php');

$tree_actions = array(
	1 => __x('dropdown action', 'Delete'),
	2 => __x('dropdown action', 'Publish'),
	3 => __x('dropdown action', 'Un-Publish'),
	4 => __x('dropdown action', 'Un-Lock')
);

/* set default action */
set_default_action();

if (get_request_var('action') != '') {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'tree_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => ''
			),
		'leaf_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => ''
			),
		'graph_tree_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => ''
			),
		'parent_item_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => ''
			),
		'parent' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '/([_\-a-z:0-9#]+)/')),
			'pageset' => true,
			'default' => ''
			),
		'position' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => ''
			),
		'nodeid' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '/([_\-a-z:0-9#]+)/')),
			'pageset' => true,
			'default' => ''
			),
		'id' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '/([_\-a-z:0-9#]+)/')),
			'pageset' => true,
			'default' => ''
			)
	);

	validate_store_request_vars($filters);
	/* ================= input validation ================= */
}

switch (get_request_var('action')) {
	case 'save':
		form_save();
		break;
	case 'actions':
        form_actions();
        break;
	case 'sortasc':
		tree_sort_name_asc();
		header('Location: tree.php?header=false');
		break;
	case 'sortdesc':
		tree_sort_name_desc();
		header('Location: tree.php?header=false');
		break;
	case 'edit':
		top_header();
		tree_edit();
		bottom_footer();
		break;
	case 'sites':
		display_sites();
		break;
	case 'hosts':
		display_hosts();
		break;
	case 'graphs':
		display_graphs();
		break;
	case 'tree_up':
		tree_up();
		break;
	case 'tree_down':
		tree_down();
		break;
	case 'ajax_dnd':
		tree_dnd();
		break;
	case 'lock':
		api_tree_lock(get_request_var('id'), $_SESSION['sess_user_id']);
		tree_edit(true);
		break;
	case 'unlock':
		api_tree_unlock(get_request_var('id'), $_SESSION['sess_user_id']);
		tree_edit(true);
		break;
	case 'copy_node':
		api_tree_copy_node(get_request_var('tree_id'), get_request_var('id'), get_request_var('parent'), get_request_var('position'));
		break;
	case 'create_node':
		api_tree_create_node(get_request_var('tree_id'), get_request_var('id'), get_request_var('position'), get_nfilter_request_var('text'));
		break;
	case 'delete_node':
		api_tree_delete_node(get_request_var('tree_id'), get_request_var('id'));
		break;
	case 'move_node':
		api_tree_move_node(get_request_var('tree_id'), get_request_var('id'), get_request_var('parent'), get_request_var('position'));
		break;
	case 'rename_node':
		api_tree_rename_node(get_request_var('tree_id'), get_request_var('id'), get_nfilter_request_var('text'));
		break;
	case 'get_node':
		api_tree_get_node(get_request_var('tree_id'), get_request_var('id'));
		break;
	case 'get_host_sort':
		get_host_sort_type();
		break;
	case 'set_host_sort':
		set_host_sort_type();
		break;
	case 'get_branch_sort':
		get_branch_sort_type();
		break;
	case 'set_branch_sort':
		set_branch_sort_type();
		break;
	default:
		top_header();
		tree();
		bottom_footer();
		break;
}

function tree_get_max_sequence() {
	$max_seq = db_fetch_cell('SELECT MAX(sequence) FROM graph_tree');

	if ($max_seq == NULL) {
		return 0;
	}

	return $max_seq;
}

function tree_check_sequences() {
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

	// report any bad or duplicate sequences to the log for reporting purposes
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

function tree_sort_name_asc() {
	// resequence the list so it has no gaps, alphabetically ascending
	db_execute('SET @seq = 0; UPDATE graph_tree SET sequence = (@seq:=@seq+1) ORDER BY name;');
}

function tree_sort_name_desc() {
	// resequence the list so it has no gaps, alphabetically ascending
	db_execute('SET @seq = 0; UPDATE graph_tree SET sequence = (@seq:=@seq+1) ORDER BY name DESC;');
}

function tree_down() {
	tree_check_sequences();

	$tree_id = get_filter_request_var('id');

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

	header('Location: tree.php?header=false');
	exit;
}

function tree_up() {
	tree_check_sequences();

	$tree_id = get_filter_request_var('id');

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

	header('Location: tree.php?header=false');
	exit;
}

function tree_dnd() {
	if (isset_request_var('tree_ids') && is_array(get_nfilter_request_var('tree_ids'))) {
		$tids     = get_nfilter_request_var('tree_ids');
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

		/**
	 	 * Save the last time a tree branch was created/updated
		 * for Caching.
		 */
		set_config_option('time_last_change_branch', time());
	}

	header('Location: tree.php?header=false');
	exit;
}

function get_host_sort_type() {
	if (isset_request_var('nodeid')) {
		$ndata = explode('_', get_request_var('nodeid'));
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
						print 'hsgt';
					} else {
						print 'hsdq';
					}
				}
			}
		}
	} else {
		return '';
	}
}

function set_host_sort_type() {
	$type   = '';
	$branch = '';

	/* clean up type string */
	if (isset_request_var('type')) {
		set_request_var('type', sanitize_search_string(get_request_var('type')));
	}

	if (isset_request_var('nodeid')) {
		$ndata = explode('_', get_request_var('nodeid'));
		if (cacti_sizeof($ndata)) {
			foreach($ndata as $n) {
				$parts = explode(':', $n);

				if (isset($parts[0]) && $parts[0] == 'tbranch') {
					$branch = $parts[1];
					input_validate_input_number($branch);

					if (get_request_var('type') == 'hsgt') {
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
	}

	return;
}

function get_branch_sort_type() {
	if (isset_request_var('nodeid')) {
		$ndata = explode('_', get_request_var('nodeid'));
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
					print __x('ordering of tree items', 'inherit');
					break;
				case TREE_ORDERING_NONE:
					print __x('ordering of tree items', 'manual');
					break;
				case TREE_ORDERING_ALPHABETIC:
					print __x('ordering of tree items', 'alpha');
					break;
				case TREE_ORDERING_NATURAL:
					print __x('ordering of tree items', 'natural');
					break;
				case TREE_ORDERING_NUMERIC:
					print __x('ordering of tree items', 'numeric');
					break;
				default:
					print '';
					break;
				}
				break;
			}
		}
		}
	} else {
		print '';
	}
}

function set_branch_sort_type() {
	$type   = '';
	$branch = '';

	/* clean up type string */
	if (isset_request_var('type')) {
		set_request_var('type', sanitize_search_string(get_request_var('type')));
	}

	if (isset_request_var('nodeid')) {
		$ndata = explode('_', get_request_var('nodeid'));
		if (cacti_sizeof($ndata)) {
			foreach($ndata as $n) {
				$parts = explode(':', $n);

				if (isset($parts[0]) && $parts[0] == 'tbranch') {
					$branch = $parts[1];
					input_validate_input_number($branch);

					switch(get_request_var('type')) {
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

/* --------------------------
    The Save Function
   -------------------------- */
function form_save() {
	/* clear graph tree cache on save - affects current user only, other users should see changes in <5 minutes */
	if (isset($_SESSION['dhtml_tree'])) {
		unset($_SESSION['dhtml_tree']);
	}

	if (isset_request_var('save_component_tree')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('sequence');
		/* ==================================================== */

		if (get_filter_request_var('id') > 0) {
			$prev_order = db_fetch_cell_prepared('SELECT sort_type
				FROM graph_tree
				WHERE id = ?',
				array(get_request_var('id')));
		} else {
			$prev_order = 1;
		}

		$save['id']            = get_request_var('id');
		$save['name']          = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['sort_type']     = form_input_validate(get_nfilter_request_var('sort_type'), 'sort_type', '', true, 3);
		$save['last_modified'] = date('Y-m-d H:i:s', time());
		$save['enabled']       = get_nfilter_request_var('enabled') == 'true' ? 'on':'-';
		$save['modified_by']   = $_SESSION['sess_user_id'];

		if (isempty_request_var('sequence')) {
			$save['sequence'] = tree_get_max_sequence() + 1;
		} else {
			$save['sequence'] = get_request_var('sequence');
		}

		if (empty($save['id'])) {
			$save['user_id'] = $_SESSION['sess_user_id'];
		}

		if (!is_error_message()) {
			$tree_id = sql_save($save, 'graph_tree');

			if ($tree_id) {
				raise_message(1);

				/* sort the tree using the algorithm chosen by the user */
				if ($save['sort_type'] != $prev_order) {
					if ($save['sort_type'] != TREE_ORDERING_NONE) {
						sort_recursive(0, $tree_id);
					}
				}

				if (empty($save['id'])) {
					/**
				 	 * Save the last time a tree was created/updated
					 * for Caching.
					 */
					set_config_option('time_last_change_tree', time());
				}
			} else {
				raise_message(2);
			}
		}

		header("Location: tree.php?header=false&action=edit&id=$tree_id");
		exit;
	}
}

function sort_recursive($branch, $tree_id) {
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

					if (leaves_exist($leaf['id'], $tree_id)) {
						sort_recursive($first_child, $tree_id);
					}
				}
			}
		}
	}
}

function leaves_exist($parent, $tree_id) {
	return db_fetch_assoc_prepared('SELECT COUNT(*)
		FROM graph_tree_items
		WHERE graph_tree_id = ?
		AND parent = ?
		AND local_graph_id = 0
		AND host_id = 0',
		array($tree_id, $parent));
}

/* -----------------------
    Tree Item Functions
   ----------------------- */
function form_actions() {
	global $tree_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { // delete
				db_execute('DELETE FROM graph_tree WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM graph_tree_items WHERE ' . array_to_sql_or($selected_items, 'graph_tree_id'));

				/**
			 	 * Save the last time a tree or branch was created/updated
				 * for Caching.
				 */
				set_config_option('time_last_change_tree', time());
				set_config_option('time_last_change_branch', time());
			} elseif (get_nfilter_request_var('drp_action') == '2') { // publish
				db_execute("UPDATE graph_tree
					SET enabled='on',
					last_modified=NOW(),
					modified_by=" . $_SESSION['sess_user_id'] . '
					WHERE ' . array_to_sql_or($selected_items, 'id'));

				/**
			 	 * Save the last time a tree or branch was created/updated
				 * for Caching.
				 */
				set_config_option('time_last_change_tree', time());
				set_config_option('time_last_change_branch', time());
			} elseif (get_nfilter_request_var('drp_action') == '3') { // un-publish
				db_execute("UPDATE graph_tree
					SET enabled='',
					last_modified=NOW(),
					modified_by=" . $_SESSION['sess_user_id'] . '
					WHERE ' . array_to_sql_or($selected_items, 'id'));

				/**
			 	 * Save the last time a tree or branch was created/updated
				 * for Caching.
				 */
				set_config_option('time_last_change_tree', time());
				set_config_option('time_last_change_branch', time());
			} elseif (get_nfilter_request_var('drp_action') == '4') { // un-lock
				db_execute("UPDATE graph_tree
					SET locked=0,
					last_modified=NOW(),
					modified_by=" . $_SESSION['sess_user_id'] . '
					WHERE ' . array_to_sql_or($selected_items, 'id'));
			}
		}

		header('Location: tree.php?header=false');
		exit;
	}

	/* setup some variables */
	$tree_list = ''; $i = 0;

	/* loop through each of the selected items */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$tree_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', array($matches[1]))) . '</li>';
			$tree_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('tree.php');

	html_start_box($tree_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($tree_array) && cacti_sizeof($tree_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to delete the following Tree.', 'Click \'Continue\' to delete following Trees.', cacti_sizeof($tree_array)) . "</p>
					<div class='itemlist'><ul>$tree_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete Tree', 'Delete Trees', cacti_sizeof($tree_array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '2') { // publish
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to publish the following Tree.', 'Click \'Continue\' to publish following Trees.', cacti_sizeof($tree_array)) . "</p>
					<div class='itemlist'><ul>$tree_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Publish Tree', 'Publish Trees', cacti_sizeof($tree_array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '3') { // un-publish
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to un-publish the following Tree.', 'Click \'Continue\' to un-publish following Trees.', cacti_sizeof($tree_array)) . "</p>
					<div class='itemlist'><ul>$tree_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Un-publish Tree', 'Un-publish Trees', cacti_sizeof($tree_array)) . "'>";
		} elseif (get_nfilter_request_var('drp_action') == '4') { // un-lock
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to un-lock the following Tree.', 'Click \'Continue\' to un-lock following Trees.', cacti_sizeof($tree_array)) . "</p>
					<div class='itemlist'><ul>$tree_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Un-lock Tree', 'Un-lock Trees', cacti_sizeof($tree_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: tree.php?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($tree_array) ? serialize($tree_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ---------------------
    Tree Functions
   --------------------- */

function tree_edit($partial = false) {
	global $fields_tree_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('type');
	/* ==================================================== */

	load_current_session_value('type', 'sess_tree_edit_type', '0');

	if (!isempty_request_var('id')) {
		$tree = db_fetch_row_prepared('SELECT *
			FROM graph_tree
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __esc('Trees [edit: %s]', $tree['name']);

		// Reset the cookie state if tree id has changed
		if (isset($_SESSION['sess_tree_id']) && $_SESSION['sess_tree_id'] != get_request_var('id')) {
			$select_first = true;
		} else {
			$select_first = false;
		}

		$_SESSION['sess_tree_id'] = get_request_var('id');
	} else {
		$tree = array();

		$header_label = __('Trees [new]');
	}

	print '<div id="tree_edit_container">';

	form_start('tree.php', 'tree_edit');

	// Remove inherit from the main tree option
	unset($fields_tree_edit['sort_type']['array'][0]);

	html_start_box($header_label, '100%', true, '3', 'center', '');

	if (!cacti_sizeof($tree)) {
		unset($fields_tree_edit['enabled']);
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_tree_edit, (isset($tree) ? $tree : array()))
		)
	);

	html_end_box(true, true);

	$lockdiv = '';

	if (isset($tree['locked']) && $tree['locked'] == 0) {
		$lockdiv = "<div style='padding:5px 5px 5px 0px'><table><tr><td><input type='button' class='ui-button ui-corner-all ui-widget' id='lock' value='" . __esc('Edit Tree') . "'></td><td style='font-weight:bold;'>" . __('To Edit this tree, you must first lock it by pressing the Edit Tree button.') . "</td></tr></table></div>\n";
		$editable = false;
	} elseif (isset($tree['locked']) && $tree['locked'] == 1) {
		$lockdiv = "<div style='padding:5px 5px 5px 0px'><table><tr><td><input type='button' class='ui-button ui-corner-all ui-widget' id='unlock' value='" . __esc('Finish Editing Tree') . "'></td><td><input type='button' class='ui-button ui-corner-all ui-widget' id='addbranch' value='" . __esc('Add Root Branch') . "' onClick='createNode()'></td><td style='font-weight:bold;'>" . __('This tree has been locked for Editing on %1$s by %2$s.', $tree['locked_date'], get_username($tree['modified_by']));
		if ($tree['modified_by'] == $_SESSION['sess_user_id']) {
			$editable = true;
			$lockdiv .= '</td></tr></table></div>';
		} else {
			$editable = false;
			$lockdiv .= __('To edit the tree, you must first unlock it and then lock it as yourself') . '</td></tr></table></div>';
		}
	} else {
		$tree['id'] = 0;
		$editable = true;
	}

	if ($editable) {
		form_save_button('tree.php', 'return');
	}

	if (!isempty_request_var('id')) {
		print $lockdiv;
	}

	print '</div>';

	if ($partial) {
		return;
	}

	if (!isempty_request_var('id')) {
		print "<table class='treeTable' style='width:100%;'>\n";

		print "<tr class='even' id='tree_filter'>\n";
		print "<td colspan='4'>";
		print "<table><tr><td>" . __('Display') . "</td>";
		print "<td>\n";
		print "<select id='element'>\n";
		print "<option id='0'>" . __('All') . "</option>";
		print "<option id='1'>" . __('Sites') . "</option>";
		print "<option id='2'>" . __('Devices') . "</option>";
		print "<option id='3'>" . __('Graphs') . "</option>";
		print "</select></td></tr></table></td></tr>";

		print "<tr class='tableRow'><td class='treeArea'>\n";

		html_start_box(__('Tree Items'), '100%', '', '3', 'center', '');

		print "<tr class='tableRow'><td style='padding:7px;'><div id='ctree'></div></td></tr>\n";

		html_end_box();

		print "</td><td class='treeItemsArea treeItemsAreaSite'>\n";

		html_start_box(__('Available Sites'), '100%', '', '3', 'center', '');
		?>
		<tr class='even noprint'>
			<td>
			<form id='form_tree_sites' action='tree.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search'); ?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='sfilter' name='sfilter' size='25' value='<?php print html_escape_request_var('sfilter');?>'>
						</td>
					</tr>
				</table>
			</form>
			</td>
		</tr>
		<?php

		html_end_box(false);

		$display_text = array(__('Site Name'));

		html_start_box('', '100%', '', '3', 'center', '');
		html_header($display_text);

		print "<tr class='tableRow'><td style='padding:7px;'><div id='sites' style='display:none;'>\n";
		display_sites();
		print "</div></td></tr>\n";

		html_end_box();

		print "</td><td class='treeItemsArea treeItemsAreaDevice'>\n";

		html_start_box(__('Available Devices'), '100%', '', '3', 'center', '');
		?>
		<tr class='even noprint'>
			<td>
			<form id='form_tree_devices' action='tree.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search'); ?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='hfilter' name='hfilter' size='25' value='<?php print html_escape_request_var('hfilter');?>'>
						</td>
					</tr>
				</table>
			</form>
			</td>
		</tr>
		<?php

		html_end_box(false);

		$display_text = array(__('Device Description'));

		html_start_box('', '100%', '', '3', 'center', '');
		html_header($display_text);

		print "<tr class='tableRow'><td style='padding:7px;'><div id='hosts' style='display:none;'>\n";
		display_hosts();
		print "</div></td></tr>\n";

		html_end_box();

		print "</td><td class='treeItemsArea treeItemsAreaGraph'>\n";

		html_start_box(__('Available Graphs'), '100%', '', '3', 'center', '');
		?>
		<tr class='even noprint'>
			<td>
			<form id='form_tree_graphs' action='tree.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search'); ?>
						</td>
						<td>
							<input type='text'  class='ui-state-default ui-corner-all' id='grfilter' name='grfilter' size='25' value='<?php print html_escape_request_var('grfilter');?>'>
						</td>
					</tr>
				</table>
			</form>
			</td>
		</tr>
		<?php
		html_end_box(false);

		$display_text = array(__('Graph Name'));

		html_start_box('', '100%', '', '3', 'center', '');
		html_header($display_text);

		print "<tr class='tableRow'><td style='padding:7px;'><div id='graphs' style='display:none;'>\n";
		display_graphs();
		print "</div></td></tr>\n";

		html_end_box();

		print "</td></tr></table>\n";

		?>
		<script type='text/javascript'>
		<?php
		if ($select_first) {
			print "var reset=true;\n";
		} else {
			print "var reset=false;\n";
		}
		?>

		var graphMeTimer;
		var hostMeTimer;
		var siteMeTimer;
		var hostSortInfo   = {};
		var branchSortInfo = {};
		var selectedItem   = {};

		function createNode() {
			var ref = $('#ctree').jstree(true);
			sel = ref.create_node('#', '<?php print __esc('New Node');?>', '0');
			if (sel) {
				ref.edit(sel);
			}
		};

		function disableTree() {
			$('.treeTable').each(function() {
				$(this).mousedown(function(event) {
					event.preventDefault();
				});
			});
		}

		function loadTreeEdit(url) {
			var myMagic = csrfMagicToken;

			$.post(url, { __csrf_magic: csrfMagicToken })
			.done(function(data) {
				$('#tree_edit_container').replaceWith(data);
				$('#tree_edit').append('<input type="hidden" name="__csrf_magic" value="'+myMagic+'">');
				initializeTreeEdit();
				applySkin();
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});
		}

		function getGraphData() {
			$.get('tree.php?action=graphs&filter='+$('#grfilter').val()
				+ '&site_id=' + (selectedItem.site_id ? selectedItem.site_id:'')
				+ '&host_id=' + (selectedItem.host_id ? selectedItem.host_id:''))
				.done(function(data) {
					$('#graphs').jstree('destroy');
					$('#graphs').html(data);
					dragable('#graphs');
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}

		function getHostData() {
			$.get('tree.php?action=hosts&filter='+$('#hfilter').val()
				+ '&site_id=' + (selectedItem.site_id ? selectedItem.site_id:''))
				.done(function(data) {
					$('#hosts').jstree('destroy');
					$('#hosts').html(data);
					dragable('#hosts');
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}

		function getSiteData() {
			$.get('tree.php?action=sites&filter='+$('#sfilter').val(), function(data) {
				$('#sites').jstree('destroy');
				$('#sites').html(data);
				dragable('#sites');
			});
		}

		function setHostSortIcon(nodeid) {
			if (hostSortInfo[nodeid]) {
				// Already set
			} else {
				$.get('tree.php?action=get_host_sort&nodeid='+nodeid)
					.done(function(data) {
						hostSortInfo[nodeid] = data;
					})
					.fail(function(data) {
						getPresentHTTPError(data);
					});
			}
		}

		function setBranchSortIcon(nodeid) {
			if (branchSortInfo[nodeid]) {
				// Already set
			} else {
				$.get('tree.php?action=get_branch_sort&nodeid='+nodeid)
					.done(function(data) {
						branchSortInfo[nodeid] = data;
					})
					.fail(function(data) {
						getPresentHTTPError(data);
					});
			}
		}

		function getHostSortIcon(type, nodeid) {
			if (hostSortInfo[nodeid] == type) {
				return 'fa fa-check';
			} else {
				return 'false';
			}
		}

		function getBranchSortIcon(type, nodeid) {
			if (branchSortInfo[nodeid] == type) {
				return 'fa fa-check';
			} else {
				return 'false';
			}
		}

		function setBranchSortOrder(type, nodeid) {
			$.get('tree.php?action=set_branch_sort&type='+type+'&nodeid='+nodeid)
				.done(function(data) {
					branchSortInfo[nodeid] = type;
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}

		function setHostSortOrder(type, nodeid) {
			$.get('tree.php?action=set_host_sort&type='+type+'&nodeid='+nodeid)
				.done(function(data) {
					hostSortInfo[nodeid] = type;
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				});
		}

		function initializeTreeEdit() {
			$('#lock').click(function() {
				strURL = 'tree.php?action=lock&id=<?php print $tree['id'];?>';
				loadTreeEdit(strURL);
			});

			$('#unlock').click(function() {
				strURL = 'tree.php?action=unlock&id=<?php print $tree['id'];?>';
				loadTreeEdit(strURL);
			});
		}

		graphsDropSet = '';
		hostsDropSet  = '';
		sitesDropSet  = '';

		$(function() {
			<?php if ($editable == false) {?>
			$('select, input').not('#lock, #element').each(function() {
				$(this).prop('disabled', true);
				$(this).addClass('ui-state-disabled');
				if ($(this).selectmenu('instance') !== undefined) {
					$(this).selectmenu('disable');
				}
			});
			disableTree();
			<?php } else {?>
			$('select, input').each(function() {
				$(this).prop('disabled', false);
			});
			<?php }?>

			$('form').unbind().submit(function(event) {
				event.preventDefault();

				if ($(this).attr('id') == 'tree_edit') {
					$.post('tree.php', { action: 'save', name: $('#name').val(), sort_type: $('#sort_type').val(), enabled: $('#enabled').is(':checked'), id: $('#id').val(), save_component_tree: 1, sequence: $('#sequence').val(), __csrf_magic: csrfMagicToken } ).done(function(data) {
						$('#main').html(data);
						applySkin();
					});
				}
			});

			initializeTreeEdit();

			var height  = parseInt($(window).height()-$('#ctree').offset().top-10)+'px';
			var sheight = parseInt($(window).height()-$('#sites').offset().top-10)+'px';
			var hheight = parseInt($(window).height()-$('#hosts').offset().top-10)+'px';
			var gheight = parseInt($(window).height()-$('#graphs').offset().top-10)+'px';

			$('#element').change(function() {
				resizer();
			});

			$(window).resize(function() {
				resizer();
			});

			function resizer() {
				if ($('#ctree').length) {
					var wheight = $(window).height();
					var cTop    = $('#ctree').parent().offset().top;
					var sTop    = $('#sites').parent().offset().top;
					var height  = wheight - cTop - 10;
					var sheight = wheight - sTop - 10;

					$('#ctree').css('height', height).css('overflow','auto');
					$('#hosts').css('height', sheight).css('overflow','auto');
					$('#sites').css('height', sheight).css('overflow','auto');
					$('#graphs').css('height', sheight).css('overflow','auto');

					switchDisplay();
				}
			}

			function switchDisplay() {
				var selected = $('#element').prop('selectedIndex');
				var windowWidth = parseInt($(window).outerWidth());
				var clientWidth = parseInt($(document).width());

				if (selected == 0) {
					if (clientWidth > windowWidth) {
						$('#element').prop('selectedIndex', 1);
						if ($('#element').selectmenu('instance')) {
							$('#element').selectmenu('refresh');
						}
						selected = $('#element').prop('selectedIndex');
					}
				}

				switch(selected) {
					case 0:
						$('.treeItemsAreaSite').show();
						$('.treeItemsAreaDevice').show();
						$('.treeItemsAreaGraph').show();
						break;
					case 1:
						$('.treeItemsAreaSite').show();
						$('.treeItemsAreaDevice').hide();
						$('.treeItemsAreaGraph').hide();
						break;
					case 2:
						$('.treeItemsAreaSite').hide();
						$('.treeItemsAreaDevice').show();
						$('.treeItemsAreaGraph').hide();
						break;
					case 3:
						$('.treeItemsAreaSite').hide();
						$('.treeItemsAreaDevice').hide();
						$('.treeItemsAreaGraph').show();
						break;
				}
			}

			$("#ctree")
			.jstree({
				'types' : {
					'site' : {
						icon : 'images/site.png',
						max_children : 0
					},
					'device' : {
						icon : 'images/server.png',
						max_children : 0
					},
					'graph' : {
						icon : 'images/server_chart_curve.png',
						max_children : 0
					}
				},
				'contextmenu' : {
					'items': function(node) {
						if (node.id.search('tgraph') > 0) {
							var dataType = 'graph';
						}else if (node.id.search('thost') > 0) {
							var dataType = 'host';
						}else if (node.id.search('tsite') > 0) {
							var dataType = 'site';
						}else {
							var dataType = 'branch';
						}

						if (dataType == 'graph') {
							return graphContext(node.id);
						}else if (dataType == 'host') {
							return hostContext(node.id);
						}else if (dataType == 'site') {
							return siteContext(node.id);
						} else {
							return branchContext(node.id);
						}
					}
				},
				'core' : {
					'data' : {
						'url' : 'tree.php?action=get_node&tree_id='+$('#id').val(),
						'data' : function(node) {
							return { 'id' : node.id }
						}
					},
					'animation' : 0,
					'check_callback' : true,
					'force_text' : true
				},
				'themes' : {
					'name' : 'default',
					'responsive' : true,
					'url' : true,
					'dots' : false
				},
				'state': { 'key': 'tree_<?php print get_request_var('id');?>' },
				'plugins' : [ 'state', 'wholerow', <?php if ($editable) {?>'contextmenu', 'dnd', <?php }?>'types' ]
			})
			.on('ready.jstree', function(e, data) {
				if (reset == true) {
					$('#ctree').jstree('clear_state');
				}
			})<?php if ($editable) {?>.on('delete_node.jstree', function (e, data) {
				$.get('?action=delete_node', { 'id' : data.node.id, 'tree_id' : $('#id').val() })
					.always(function() {
						var st = data.instance.get_state();
						data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
					});
				})
			.on('hover_node.jstree', function (e, data) {
				if (data.node.id.search('thost') >= 0) {
					setHostSortIcon(data.node.id);
				}else if (data.node.id.search('thost') < 0 && data.node.id.search('tgraph') < 0 && data.node.id.search('tsite')) {
					setBranchSortIcon(data.node.id);
				}
			})
			.on('create_node.jstree', function (e, data) {
				$.get('?action=create_node', { 'id' : data.node.parent, 'tree_id' : $('#id').val(), 'position' : data.position, 'text' : data.node.text })
					.done(function (d) {
						data.instance.set_id(data.node, d.id);
						data.instance.set_text(data.node, d.text);
						data.instance.edit(data.node);

						if (d.text != '<?php print __esc('New Node');?>') {
							$('.jstree').jstree(true).refresh();
						}
					})
					.fail(function () {
						var st = data.instance.get_state();
						data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
					});
			})
			.on('rename_node.jstree', function (e, data) {
				$.get('?action=rename_node', { 'id' : data.node.id, 'tree_id' : $('#id').val(), 'text' : data.text })
					.done(function (d) {
						if (d.result == 'false') {
							data.instance.set_text(data.node, d.text);
							data.instance.edit(data.node);
						} else {
							var st = data.instance.get_state();
							data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
						}
					})
					.fail(function(data) {
						getPresentHTTPError(data);
					});
			})
			.on('move_node.jstree', function (e, data) {
				$.get('?action=move_node', { 'id' : data.node.id, 'tree_id' : $('#id').val(), 'parent' : data.parent, 'position' : data.position })
					.always(function () {
						var st = data.instance.get_state();
						data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
					});
			})
			.on('copy_node.jstree', function (e, data) {
				oid = data.original.id;

				if (oid.search('thost') >= 0) {
					$('#hosts').jstree().deselect_all();
				} else if (oid.search('tsite') >= 0) {
					$('#sites').jstree().deselect_all();
				} else {
					$('#graphs').jstree().deselect_all();
				}

				$.get('?action=copy_node', { 'id' : data.original.id, 'tree_id' : $('#id').val(), 'parent' : data.parent, 'position' : data.position })
				.always(function () {
					var st = data.instance.get_state();
					data.instance.load_node(data.instance.get_parent(data.node.id), function () { this.set_state(st); });
				});
			})<?php } else {?>.children().bind('contextmenu', function(event) {
				return false;
			})<?php }?>;

			$('#ctree').css('height', height).css('overflow','auto');;

			dragable('#graphs', 'graphs');
			dragable('#sites',  'sites');
			dragable('#hosts',  'hosts');
		});

		function dragable(element, type) {
			$(element)
				.jstree({
					'types' : {
						'site' : {
							icon : 'images/site.png',
							valid_children: 'none',
							max_children : 0
						},
						'device' : {
							icon : 'images/server.png',
							valid_children: 'none',
							max_children : 0
						},
						'graph' : {
							icon : 'images/server_chart_curve.png',
							valid_children: 'none',
							max_children : 0
						}
					},
					'core' : {
						'animation' : 0,
						'check_callback' : function(operation, node, node_parent, node_position, more) {
							return false;  // not dragging onto self
						}
					},
					'dnd' : {
						'always_copy' : true,
						'check_while_dragging': true
					},
					'themes' : { 'stripes' : true },
					'plugins' : [ 'wholerow', <?php if ($editable) {?>'dnd', <?php }?>'types' ]
				})
				.on('ready.jstree', function(e, data) {
					if (reset == true) {
						$('#ctree').jstree('clear_state');
					}
				})<?php if ($editable) {?>
				.on('select_node.jstree', function(e, data) {
					if (type == 'graphs') {
						graphsDropSet = data;
					} else {
						hostsDropSet  = data;
					}
				})
				.on('activate_node.jstree', function(e, data) {
					if (type == 'sites') {
						selectedItem.site_id = (data.node.id).split(':')[1];
						selectedItem.host_id = '';
						getHostData();
						getGraphData();
					}else if(type == 'hosts'){
						selectedItem.host_id = (data.node.id).split(':')[1];
						getGraphData();
					}
				})
				.on('deselect_node.jstree', function(e, data) {
					if (type == 'graphs') {
						graphsDropSet = data;
					} else {
						hostsDropSet  = data;
					}
				})<?php }?>;
				$(element).find('.jstree-ocl').hide();
				$(element).children().bind('contextmenu', function(event) {
					return false;
				});
				$(element).show();
		}

		function branchContext(nodeid) {
			return {
				'create' : {
					'separator_before'	: false,
					'separator_after'	: true,
					'icon'				: 'fa fa-folder',
					'_disabled'			: false,
					'label'				: '<?php print __esc('Create');?>',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						inst.create_node(obj, {}, 'last', function (new_node) {
							setTimeout(function () { inst.edit(new_node); },0);
						});
					}
				},
				'rename' : {
					'separator_before'	: false,
					'separator_after'	: false,
					'icon'				: 'fa fa-pencil-alt',
					'_disabled'			: false,
					'label'				: '<?php print __esc('Rename');?>',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						inst.edit(obj);
					}
				},
				'remove' : {
					'separator_before'	: false,
					'icon'				: 'fa fa-times',
					'separator_after'	: false,
					'_disabled'			: false,
					'label'				: '<?php print __esc('Delete');?>',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						if(inst.is_selected(obj)) {
							inst.delete_node(inst.get_selected());
						} else {
							inst.delete_node(obj);
						}
					}
				},
				'bst' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-sort',
					'separator_after'	: false,
					'label'				: '<?php print __esc('Branch Sorting');?>',
					'action'			: false,
					'submenu' : {
						'inherit' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: getBranchSortIcon('inherit', nodeid),
							'label'				: '<?php print __esc('Inherit');?>',
							'action'			: function (data) {
								setBranchSortOrder('inherit', nodeid);
								var inst = $.jstree.reference(data.reference);
								var st = inst.get_state();
								var obj = inst.get_node();
								inst.refresh(obj);
								inst.load_node(nodeid, function() { this.set_state(st); });
							}
						},
						'manual' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: getBranchSortIcon('manual', nodeid),
							'label'				: '<?php print __esc('Manual');?>',
							'action'			: function (data) {
								setBranchSortOrder('manual', nodeid);
								var inst = $.jstree.reference(data.reference);
								var st = inst.get_state();
								var obj = inst.get_node();
								inst.refresh(obj);
								inst.load_node(nodeid, function() { this.set_state(st); });
							}
						},
						'alpha' : {
							'separator_before'	: false,
							'icon'				: getBranchSortIcon('alpha', nodeid),
							'separator_after'	: false,
							'label'				: '<?php print __esc('Alphabetic');?>',
							'action'			: function (data) {
								setBranchSortOrder('alpha', nodeid);
								var inst = $.jstree.reference(data.reference);
								var st = inst.get_state();
								var obj = inst.get_node();
								inst.refresh(obj);
								inst.load_node(nodeid, function() { this.set_state(st); });
							}
						},
						'natural' : {
							'separator_before'	: false,
							'icon'				: getBranchSortIcon('natural', nodeid),
							'separator_after'	: false,
							'label'				: '<?php print __esc('Natural');?>',
							'action'			: function (data) {
								setBranchSortOrder('natural', nodeid);
								var inst = $.jstree.reference(data.reference);
								var st = inst.get_state();
								var obj = inst.get_node();
								inst.refresh(obj);
								inst.load_node(nodeid, function () { this.set_state(st); });
							}
						},
						'numeric' : {
							'separator_before'	: false,
							'icon'				: getBranchSortIcon('numeric', nodeid),
							'separator_after'	: false,
							'label'				: '<?php print __esc('Numeric');?>',
							'action'			: function (data) {
								setBranchSortOrder('numeric', nodeid);
								var inst = $.jstree.reference(data.reference);
								var st = inst.get_state();
								var obj = inst.get_node();
								inst.refresh(obj);
								inst.load_node(nodeid, function () { this.set_state(st); });
							}
						}
					}
				},
				'ccp' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-edit',
					'separator_after'	: false,
					'label'				: '<?php print __esc('Edit');?>',
					'action'			: false,
					'submenu' : {
						'cut' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: 'fa fa-cut',
							'label'				: '<?php print __esc('Cut');?>',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference);
								var obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.cut(inst.get_selected());
								} else {
									inst.cut(obj);
								}
							}
						},
						'copy' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-copy',
							'separator_after'	: false,
							'label'				: '<?php print __esc('Copy');?>',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference);
								var obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.copy(inst.get_selected());
								} else {
									inst.copy(obj);
								}
							}
						},
						'paste' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-clipboard',
							'_disabled'			: function (data) {
								return !$.jstree.reference(data.reference).can_paste();
							},
							'separator_after'	: false,
							'label'				: '<?php print __esc('Paste');?>',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference);
								var obj = inst.get_node(data.reference);
								inst.paste(obj);
							}
						}
					}
				}
			};
		}

		function graphContext(nodeid) {
			return {
				'remove' : {
					'separator_before'	: false,
					'icon'				: 'fa fa-times',
					'separator_after'	: false,
					'_disabled'			: false, //(this.check('delete_node', data.reference, this.get_parent(data.reference), '')),
					'label'				: '<?php print __esc('Delete');?>',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						if(inst.is_selected(obj)) {
							inst.delete_node(inst.get_selected());
						} else {
							inst.delete_node(obj);
						}
					}
				},
				'ccp' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-edit',
					'separator_after'	: false,
					'label'				: '<?php print __esc('Edit');?>',
					'action'			: false,
					'submenu' : {
						'cut' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: 'fa fa-cut',
							'label'				: '<?php print __esc('Cut');?>',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference);
								var obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.cut(inst.get_selected());
								} else {
									inst.cut(obj);
								}
							}
						},
						'copy' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-copy',
							'separator_after'	: false,
							'label'				: '<?php print __esc('Copy');?>',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference);
								var obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.copy(inst.get_selected());
								} else {
									inst.copy(obj);
								}
							}
						}
					}
				}
			};
		}

		function siteContext(nodeid) {
			return {
				'remove' : {
					'separator_before'	: false,
					'icon'				: 'fa fa-times',
					'separator_after'	: false,
					'_disabled'			: false,
					'label'				: '<?php print __esc('Delete');?>',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						if(inst.is_selected(obj)) {
							inst.delete_node(inst.get_selected());
						} else {
							inst.delete_node(obj);
						}
					}
				}
			};
		}

		function hostContext(nodeid) {
			return {
				'remove' : {
					'separator_before'	: false,
					'icon'				: 'fa fa-times',
					'separator_after'	: false,
					'_disabled'			: false,
					'label'				: '<?php print __esc('Delete');?>',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference);
						var obj = inst.get_node(data.reference);
						if(inst.is_selected(obj)) {
							inst.delete_node(inst.get_selected());
						} else {
							inst.delete_node(obj);
						}
					}
				},
				'hso' : {
					'separator_before'	: true,
					'separator_after'	: false,
					'icon'				: 'fa fa-sort',
					'label'				: '<?php print __esc('Sorting Type');?>',
					'action'			: false,
					'submenu' : {
						'hsgt' : {
							'separator_before'	: false,
							'icon'				: getHostSortIcon('hsgt', nodeid),
							'separator_after'	: false,
							'label'				: '<?php print __esc('Graph Template');?>',
							'action'			: function (data) {
								setHostSortOrder('hsgt', nodeid);
							}
						},
						'hsdq' : {
							'separator_before'	: false,
							'icon'				: getHostSortIcon('hsdq', nodeid),
							'separator_after'	: false,
							'label'				: '<?php print __esc('Data Query Index');?>',
							'action'			: function (data) {
								setHostSortOrder('hsdq', nodeid);
							}
						}
					}
				},
				'ccp' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-edit',
					'separator_after'	: false,
					'label'				: '<?php print __esc('Edit');?>',
					'action'			: false,
					'submenu' : {
						'cut' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: 'fa fa-cut',
							'label'				: '<?php print __esc('Cut');?>',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.cut(inst.get_selected());
								} else {
									inst.cut(obj);
								}
							}
						},
						'copy' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-copy',
							'separator_after'	: false,
							'label'				: '<?php print __esc('Copy');?>',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.copy(inst.get_selected());
								} else {
									inst.copy(obj);
								}
							}
						}
					}
				}
			};
		}

		$('#grfilter').keyup(function(data) {
			graphMeTimer && clearTimeout(graphMeTimer);
			graphMeTimer = setTimeout(getGraphData, 300);
		});

		$('#hfilter').keyup(function(data) {
			hostMeTimer && clearTimeout(hostMeTimer);
			hostMeTimer = setTimeout(getHostData, 300);
		});

		$('#sfilter').keyup(function(data) {
			siteMeTimer && clearTimeout(siteMeTimer);
			siteMeTimer = setTimeout(getSiteData, 300);
		});
		</script>
		<?php
	}
}

function display_sites() {
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE
			name LIKE '       . db_qstr('%' . get_request_var('filter') . '%') . '
			OR city LIKE '    . db_qstr('%' . get_request_var('filter') . '%') . '
			OR state LIKE '   . db_qstr('%' . get_request_var('filter') . '%') . '
			OR country LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	} else {
		$sql_where = '';
	}

	$sites = db_fetch_assoc("SELECT * FROM sites $sql_where");

	if (cacti_sizeof($sites)) {
		foreach($sites as $s) {
			print "<ul><li id='tsite:" . $s['id'] . "' data-jstree='{ \"type\" : \"site\"}'>" . html_escape($s['name']) . "</li></ul>\n";
		}
	}
}

function display_hosts() {
	$sql_where = '';

	if (get_request_var('filter') != '') {
		$sql_where .= 'h.hostname LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR h.description LIKE '      . db_qstr('%' . get_request_var('filter') . '%');
	}

	if (get_filter_request_var('site_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . 'h.site_id = ' . get_filter_request_var('site_id');
	}

	$hosts = get_allowed_devices($sql_where, 'description', read_config_option('autocomplete_rows'));

	if (cacti_sizeof($hosts)) {
		foreach($hosts as $h) {
			print "<ul><li id='thost:" . $h['id'] . "' data-jstree='{ \"type\" : \"device\"}'>" . html_escape($h['description']) . ' (' . html_escape($h['hostname']) . ')' . "</li></ul>\n";
		}
	}
}

function display_graphs() {
	$sql_where = '';

	if (get_request_var('filter') != '') {
		$sql_where .= 'WHERE (
			title_cache LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR gt.name LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . ')
			AND local_graph_id > 0';
	} else {
		$sql_where .= 'WHERE local_graph_id > 0';
	}

	if (get_filter_request_var('site_id') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ': 'WHERE ') . 'h.site_id = ' . get_request_var('site_id');
	}

	if (get_filter_request_var('host_id') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gl.host_id = ' . get_request_var('host_id');
	}

	$graphs = db_fetch_assoc("SELECT
		gtg.local_graph_id AS id,
		gtg.title_cache AS title,
		gt.name AS template_name
		FROM graph_templates_graph AS gtg
		LEFT JOIN graph_templates AS gt
		ON gt.id=gtg.graph_template_id
		LEFT JOIN graph_local AS gl
		ON gtg.local_graph_id = gl.id
		LEFT JOIN host as h
		ON gl.host_id = h.id
		$sql_where
		ORDER BY title_cache
		LIMIT " . read_config_option('autocomplete_rows'));

	if (cacti_sizeof($graphs)) {
		foreach($graphs as $g) {
			if (is_graph_allowed($g['id'])) {
				print "<ul><li id='tgraph:" . $g['id'] . "' data-jstree='{ \"type\": \"graph\" }'>" . html_escape($g['title']) . '</li></ul>';
			}
		}
	}
}

function tree() {
	global $tree_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'sequence',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_tree');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'tree.php?rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'tree.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#sorta').click(function() {
			loadPageNoHeader('tree.php?action=sortasc');
		});

		$('#sortd').click(function() {
			loadPageNoHeader('tree.php?action=sortdesc');
		});

		$('#form_tree').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>

	<?php

	$buttons = array(
		array(
			'href'     => 'tree.php?action=edit',
			'callback' => true,
			'title'    => __esc('Add Tree'),
			'class'    => 'fa fa-plus'
		),
		array(
			'href'     => 'tree.php?action=sortasc',
			'callback' => true,
			'title'    => __esc('Sort Trees Ascending'),
			'class'    => 'fa fa-sort-alpha-down'
		),
		array(
			'href'     => 'tree.php?action=sortdesc',
			'callback' => true,
			'title'    => __esc('Sort Trees Descending'),
			'class'    => 'fa fa-sort-alpha-up'
		)
	);

	html_start_box(__('Trees'), '100%', '', '3', 'center', $buttons);

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_tree' action='tree.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search'); ?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Trees'); ?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<button type='button' class='ui-button ui-corner-all ui-widget' id='sorta' title='<?php print __esc('Sort Trees Ascending');?>'><i class='fa fa-sort-alpha-down'></i></button>
							<button type='button' class='ui-button ui-corner-all ui-widget' id='sortd' title='<?php print __esc('Sort Trees Descending');?>'><i class='fa fa-sort-alpha-up'></i></button>
						</span>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (
			t.name LIKE '      . db_qstr('%' . get_request_var('filter') . '%') . '
			OR ti.title LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$trees = db_fetch_assoc("SELECT t.*,
		SUM(CASE WHEN ti.host_id > 0 THEN 1 ELSE 0 END) AS hosts,
		SUM(CASE WHEN ti.local_graph_id > 0 THEN 1 ELSE 0 END) AS graphs,
		SUM(CASE WHEN ti.local_graph_id = 0 AND host_id = 0 AND site_id = 0 THEN 1 ELSE 0 END) AS branches,
		SUM(CASE WHEN ti.site_id > 0 THEN 1 ELSE 0 END) AS sites
		FROM graph_tree AS t
		LEFT JOIN graph_tree_items AS ti
		ON t.id=ti.graph_tree_id
		$sql_where
		GROUP BY t.id
		$sql_order
		$sql_limit");

	$sql = "SELECT COUNT(DISTINCT(t.id))
		FROM graph_tree AS t
		LEFT JOIN graph_tree_items AS ti
		ON t.id=ti.graph_tree_id
		$sql_where";

	$total_rows = get_total_row_data($_SESSION['sess_user_id'], $sql, array(), 'tree');

	$nav = html_nav_bar('tree.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Trees'), 'page', 'main');

	form_start('tree.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name' => array(
			'display' => __('Tree Name'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The name by which this Tree will be referred to as.')
		),
		'id' => array(
			'display' => __('ID'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The internal database ID for this Tree.  Useful when performing automation or debugging.')
		),
		'enabled' => array(
			'display' => __('Published'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('Unpublished Trees cannot be viewed from the Graph tab')
		),
		'locked' => array(
			'display' => __('Locked'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('A Tree must be locked in order to be edited.')
		),
		'user_id' => array(
			'display' => __('Owner'),
			'align' => 'left',
			'sort' => 'ASC',
			'tip' => __('The original author of this Tree.')
		),
		'sequence' => array(
			'display' => __('Order'),
			'align' => 'center',
			'sort' => 'ASC',
			'tip' => __('To change the order of the trees, first sort by this column, press the up or down arrows once they appear.')
		),
		'last_modified' => array(
			'display' => __('Last Edited'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The date that this Tree was last edited.')
		),
		'modified_by' => array(
			'display' => __('Edited By'),
			'align' => 'right',
			'sort' => 'ASC',
			'tip' => __('The last user to have modified this Tree.')
		),
		'sites' => array(
			'display' => __('Sites'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The total number of Site Branches in this Tree.')
		),
		'branches' => array(
			'display' => __('Branches'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The total number of Branches in this Tree.')
		),
		'hosts' => array(
			'display' => __('Devices'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The total number of individual Devices in this Tree.')
		),
		'graphs' => array(
			'display' => __('Graphs'),
			'align' => 'right',
			'sort' => 'DESC',
			'tip' => __('The total number of individual Graphs in this Tree.')
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 1;
	if (cacti_sizeof($trees)) {
		foreach ($trees as $tree) {
			$sequence = '';
			if (get_request_var('sort_column') == 'sequence' && get_request_var('sort_direction') == 'ASC') {
				if ($i == 1 && cacti_sizeof($trees) == 1) {
					$sequence .= '<span class="moveArrowNone"></span>';
					$sequence .= '<span class="moveArrowNone"></span>';
				} elseif ($i == 1) {
					$sequence .= '<a class="pic fa fa-caret-down moveArrow" href="' . htmlspecialchars('tree.php?action=tree_down&id=' . $tree['id']) . '" title="' . __esc('Move Down') . '"></a>';
					$sequence .= '<span class="moveArrowNone"></span>';
				} elseif ($i == cacti_sizeof($trees)) {
					$sequence .= '<span class="moveArrowNone"></span>';
					$sequence .= '<a class="pic fa fa-caret-up moveArrow" href="' . html_escape('tree.php?action=tree_up&id=' . $tree['id']) . '" title="' . __esc('Move Up') . '"></a>';

				} else {
					$sequence .= '<a class="pic fa fa-caret-down moveArrow" href="' . html_escape('tree.php?action=tree_down&id=' . $tree['id']) . '" title="' . __esc('Move Down') . '"></a>';
					$sequence .= '<a class="pic fa fa-caret-up moveArrow" href="' . html_escape('tree.php?action=tree_up&id=' . $tree['id']) . '" title="' . __esc('Move Up') . '"></a>';
				}
			}

			form_alternate_row('line' . $tree['id'], true);
			form_selectable_cell(filter_value($tree['name'], get_request_var('filter'), 'tree.php?action=edit&id=' . $tree['id']), $tree['id']);
			form_selectable_cell($tree['id'], $tree['id'], '', 'right');
			form_selectable_cell($tree['enabled'] == 'on' ? __('Yes'):__('No'), $tree['id']);
			form_selectable_cell($tree['locked'] == '1' ? __('Yes'):__('No'), $tree['id']);
			form_selectable_cell(get_username($tree['user_id']), $tree['id']);
			form_selectable_cell($sequence, $tree['id'], '', 'nowrap center');
			form_selectable_cell(substr($tree['last_modified'],0,16), $tree['id'], '', 'right');
			form_selectable_cell(get_username($tree['modified_by']), $tree['id'], '', 'right');
			form_selectable_cell($tree['sites'] > 0 ? number_format_i18n($tree['sites'], '-1'):'-', $tree['id'], '', 'right');
			form_selectable_cell($tree['branches'] > 0 ? number_format_i18n($tree['branches'], '-1'):'-', $tree['id'], '', 'right');
			form_selectable_cell($tree['hosts'] > 0 ? number_format_i18n($tree['hosts'], '-1'):'-', $tree['id'], '', 'right');
			form_selectable_cell($tree['graphs'] > 0 ? number_format_i18n($tree['graphs'], '-1'):'-', $tree['id'], '', 'right');
			form_checkbox_cell($tree['name'], $tree['id']);
			form_end_row();

			$i++;
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No Trees Found') . "</em></td></tr>";
	}
	html_end_box(false);

	if (cacti_sizeof($trees)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($tree_actions);

	form_end();

	if (get_request_var('sort_column') == 'sequence' && get_request_var('sort_direction') == 'ASC') {
		?>
		<script type='text/javascript'>
		$(function() {
			$('#tree2_child').attr('id', 'tree_ids');

			<?php if (read_config_option('drag_and_drop') == 'on') { ?>
			$('#tree_ids').find('tr:first').addClass('nodrag').addClass('nodrop');

			$('#tree_ids').tableDnD({
				onDrop: function(table, row) {
					loadPageNoHeader('tree.php?action=ajax_dnd&'+$.tableDnD.serialize());
				}
			});
			<?php } ?>
		});
		</script>
		<?php
	}
}

