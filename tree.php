<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
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

include('./include/auth.php');
include_once('./lib/api_tree.php');
include_once('./lib/html_tree.php');
include_once('./lib/data_query.php');

define('MAX_DISPLAY_PAGES', 21);

$tree_actions = array(
	1 => 'Delete',
	2 => 'Publish',
	3 => 'Un Publish'
);

input_validate_input_number(get_request_var_request('tree_id'));
input_validate_input_number(get_request_var_request('leaf_id'));
input_validate_input_number(get_request_var_post('graph_tree_id'));
input_validate_input_number(get_request_var_post('parent_item_id'));

/* clean up id string */
if (isset($_REQUEST['id']) && $_REQUEST['id'] != '#') {
	$_REQUEST['id'] = sanitize_search_string(get_request_var_request('id'));
}

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		form_save();
		break;
	case 'actions':
        form_actions();
        break;
	case 'edit':
		top_header();
		tree_edit();
		bottom_footer();
		break;
	case 'hosts':
		display_hosts();
		break;
	case 'graphs':
		display_graphs();
		break;
	case 'lock':
		api_tree_lock($_REQUEST['id'], $_SESSION['sess_user_id']);
		break;
	case 'unlock':
		api_tree_unlock($_REQUEST['id'], $_SESSION['sess_user_id']);
		break;
	case 'copy_node':
		api_tree_copy_node($_REQUEST['tree_id'], $_REQUEST['id'], $_REQUEST['parent'], $_REQUEST['position']);
		break;
	case 'create_node':
		api_tree_create_node($_REQUEST['tree_id'], $_REQUEST['id'], $_REQUEST['position'], $_REQUEST['text']);
		break;
	case 'delete_node':
		api_tree_delete_node($_REQUEST['tree_id'], $_REQUEST['id']);
		break;
	case 'move_node':
		api_tree_move_node($_REQUEST['tree_id'], $_REQUEST['id'], $_REQUEST['parent'], $_REQUEST['position']);
		break;
	case 'rename_node':
		api_tree_rename_node($_REQUEST['tree_id'], $_REQUEST['id'], $_REQUEST['text']);
		break;
	case 'get_node':
		api_tree_get_node($_REQUEST['tree_id'], $_REQUEST['id']);
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

function get_host_sort_type() {
	if (isset($_REQUEST['nodeid'])) {
		$ndata = explode('_', $_REQUEST['nodeid']);
		if (sizeof($ndata)) {
			foreach($ndata as $n) {
				$parts = explode(':', $n);

				if (isset($parts[0]) && $parts[0] == 'tbranch') {
					$branch = $parts[1];
					input_validate_input_number($branch);
					$sort_type = db_fetch_cell_prepared('SELECT host_grouping_type FROM graph_tree_items WHERE id = ?', array($branch));
					if ($sort_type == HOST_GROUPING_GRAPH_TEMPLATE) {
						print 'hsgt';
					}else{
						print 'hsdq';
					}
				}
			}
		}
	}else{
		return '';
	}
}

function set_host_sort_type() {
	$type   = '';
	$branch = '';

	/* clean up type string */
	if (isset($_REQUEST['type'])) {
		$_REQUEST['type'] = sanitize_search_string(get_request_var_request('type'));
	}

	if (isset($_REQUEST['nodeid'])) {
		$ndata = explode('_', $_REQUEST['nodeid']);
		if (sizeof($ndata)) {
			foreach($ndata as $n) {
				$parts = explode(':', $n);

				if (isset($parts[0]) && $parts[0] == 'tbranch') {
					$branch = $parts[1];
					input_validate_input_number($branch);

					if ($_REQUEST['type'] == 'hsgt') {
						$type = HOST_GROUPING_GRAPH_TEMPLATE;
					}else{
						$type = HOST_GROUPING_DATA_QUERY_INDEX;
					}

					db_execute_prepared('UPDATE graph_tree_items SET host_grouping_type=$type WHERE id = ?', array($branch));
					break;
				}
			}
		}
	}

	return;
}

function get_branch_sort_type() {
	if (isset($_REQUEST['nodeid'])) {
		$ndata = explode('_', $_REQUEST['nodeid']);
		if (sizeof($ndata)) {
		foreach($ndata as $n) {
			$parts = explode(':', $n);

			if (isset($parts[0]) && $parts[0] == 'tbranch') {
				$branch = $parts[1];
				input_validate_input_number($branch);
				$sort_type = db_fetch_cell_prepared('SELECT sort_children_type FROM graph_tree_items WHERE id = ?', array($branch));
				switch($sort_type) {
				case TREE_ORDERING_INHERIT:
					print 'inherit';
					break;
				case TREE_ORDERING_NONE:
					print 'manual';
					break;
				case TREE_ORDERING_ALPHABETIC:
					print 'alpha';
					break;
				case TREE_ORDERING_NATURAL:
					print 'natural';
					break;
				case TREE_ORDERING_NUMERIC:
					print 'numeric';
					break;
				default:
					print '';
					break;
				}
				break;
			}
		}
		}
	}else{
		print '';
	}
}

function set_branch_sort_type() {
	$type   = '';
	$branch = '';

	/* clean up type string */
	if (isset($_REQUEST['type'])) {
		$_REQUEST['type'] = sanitize_search_string(get_request_var_request('type'));
	}

	if (isset($_REQUEST['nodeid'])) {
		$ndata = explode('_', $_REQUEST['nodeid']);
		if (sizeof($ndata)) {
		foreach($ndata as $n) {
			$parts = explode(':', $n);

			if (isset($parts[0]) && $parts[0] == 'tbranch') {
				$branch = $parts[1];
				input_validate_input_number($branch);

				switch($_REQUEST['type']) {
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

				if ($type != '' && $branch != '') {
					db_execute_prepared('UPDATE graph_tree_items SET sort_children_type=$type WHERE id = ?', array($branch));
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

	if (isset($_POST['save_component_tree'])) {
		$save['id']            = $_POST['id'];
		$save['name']          = form_input_validate($_POST['name'], 'name', '', false, 3);
		$save['sort_type']     = form_input_validate($_POST['sort_type'], 'sort_type', '', true, 3);
		$save['last_modified'] = date('Y-m-d H:i:s', time());
		$save['modified_by']   = $_SESSION['sess_user_id'];
		if (empty($save['id'])) {
			$save['user_id'] = $_SESSION['sess_user_id'];
		}

		if (!is_error_message()) {
			$tree_id = sql_save($save, 'graph_tree');

			if ($tree_id) {
				raise_message(1);

				/* sort the tree using the algorithm chosen by the user */
				api_tree_sort_tree(SORT_TYPE_TREE, $tree_id, $_POST['sort_type']);
			}else{
				raise_message(2);
			}
		}

		header("Location: tree.php?action=edit&header=false&id=$tree_id");
	}elseif (isset($_POST['save_component_tree_item'])) {
		$tree_item_id = api_tree_item_save($_POST['id'], $_POST['graph_tree_id'], $_POST['type'], $_POST['parent_item_id'],
			(isset($_POST['title']) ? $_POST['title'] : ''), (isset($_POST['local_graph_id']) ? $_POST['local_graph_id'] : '0'),
			(isset($_POST['rra_id']) ? $_POST['rra_id'] : '0'), (isset($_POST['host_id']) ? $_POST['host_id'] : '0'),
			(isset($_POST['host_grouping_type']) ? $_POST['host_grouping_type'] : '1'), (isset($_POST['sort_children_type']) ? $_POST['sort_children_type'] : '1'),
			(isset($_POST['propagate_changes']) ? true : false));
	}
}

/* -----------------------
    Tree Item Functions
   ----------------------- */
function form_actions() {
	global $tree_actions;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), '^([a-zA-Z0-9_]+)$');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));
		for ($i=0;($i<count($selected_items));$i++) {
			/* ================= input validation ================= */
			input_validate_input_number($selected_items[$i]);
			/* ==================================================== */
		}

		if ($_POST['drp_action'] == '1') { /* delete */
			db_execute('DELETE FROM graph_tree WHERE ' . array_to_sql_or($selected_items, 'id'));
			db_execute('DELETE FROM graph_tree_items WHERE ' . array_to_sql_or($selected_items, 'graph_tree_id'));

		}elseif ($_POST['drp_action'] == '2') { /* publish */
			db_execute("UPDATE graph_tree 
				SET enabled='on',
				last_modified=NOW(),
				modified_by=" . $_SESSION['sess_user_id'] . '
				WHERE ' . array_to_sql_or($selected_items, 'id'));
		}elseif ($_POST['drp_action'] == '3') { /* un-publish */
			db_execute("UPDATE graph_tree 
				SET enabled='',
				last_modified=NOW(),
				modified_by=" . $_SESSION['sess_user_id'] . '
				WHERE ' . array_to_sql_or($selected_items, 'id'));
		}

		header('Location: tree.php');
		exit;
	}

	/* setup some variables */
	$tree_list = ''; $i = 0;

	/* loop through each of the selected items */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$tree_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', array($matches[1]))) . '</li>';
			$tree_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	print "<form action='tree.php' method='post'>\n";

	html_start_box('<strong>' . $tree_actions{$_POST['drp_action']} . '</strong>', '60%', '', '3', 'center', '');

	if (isset($tree_array) && sizeof($tree_array)) {
		if ($_POST['drp_action'] == '1') { /* delete */
			print "	<tr>
					<td class='textArea' class='odd'>
						<p>When you click \"Continue\", the folling Tree(s) will be deleted.</p>
						<ul>$tree_list</ul>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Tree(s)'>";
		}elseif ($_POST['drp_action'] == '2') { /* publish */
			print "	<tr>
					<td class='textArea' class='odd'>
						<p>When you click \"Continue\", the following Tree(s) will be Published.</p>
						<ul>$tree_list</ul>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Publish Tree(s)'>";
		}elseif ($_POST['drp_action'] == '3') { /* un-publish */
			print "	<tr>
					<td class='textArea' class='odd'>
						<p>When you click \"Continue\", the following Tree(s) will be Un-Published.</p>
						<ul>$tree_list</ul>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Un-Publish Tree(s)'>";
		}
	}else{
		print "<tr><td class='odd'><span class='textError'>You must select at least one Tree.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($tree_array) ? serialize($tree_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
				$save_html
			</td>
		</tr>\n";

	html_end_box();

	bottom_footer();
}

/* ---------------------
    Tree Functions
   --------------------- */

function tree_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	if ((read_config_option('deletion_verification') == 'on') && (!isset($_REQUEST['confirm']))) {
		top_header();

		form_confirm('Are You Sure?', "Are you sure you want to delete the tree <strong>'" . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', array($_REQUEST['id'])), ENT_QUOTES) . "'</strong>?", htmlspecialchars('tree.php'), htmlspecialchars('tree.php?action=remove&id=' . $_REQUEST['id']));

		bottom_footer();
		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset($_REQUEST['confirm']))) {
		db_execute_prepared('DELETE FROM graph_tree WHERE id = ?', array($_REQUEST['id']));
		db_execute_prepared('DELETE FROM graph_tree_items WHERE graph_tree_id = ?', array($_REQUEST['id']));
	}

	/* clear graph tree cache on save - affects current user only, other users should see changes in <5 minutes */
	if (isset($_SESSION['dhtml_tree'])) {
		unset($_SESSION['dhtml_tree']);
	}

}

function tree_edit() {
	global $fields_tree_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	input_validate_input_number(get_request_var_request('type'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	load_current_session_value('filter', 'sess_tree_edit_filter', '');
	load_current_session_value('type', 'sess_tree_edit_type', '0');

	if (!empty($_REQUEST['id'])) {
		$tree = db_fetch_row_prepared('SELECT * FROM graph_tree WHERE id = ?', array($_REQUEST['id']));
		$header_label = '[edit: ' . htmlspecialchars($tree['name']) . ']';
	}else{
		$header_label = '[new]';
	}

	// Reset the cookie state if tree id has changed
	if (isset($_SESSION['sess_tree_id']) && $_SESSION['sess_tree_id'] != $_REQUEST['id']) {
		$select_first = true;
	}else{
		$select_first = false;
	}
	$_SESSION['sess_tree_id'] = $_REQUEST['id'];

	html_start_box('<strong>Graph Trees</strong> ' . $header_label, '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array(),
		'fields' => inject_form_variables($fields_tree_edit, (isset($tree) ? $tree : array()))
		));

	html_end_box();

	$lockdiv = '';

	if (isset($tree['locked']) && $tree['locked'] == 0) {
		$lockdiv = "<div style='padding:3px;'><table><tr><td><input id='lock' type='button' value='Lock Tree'></td><td style='font-weight:bold;'>To edit this tree, you must first lock it.</td></tr></table></div>\n";
		$editable = false;
	}elseif (isset($tree['locked']) && $tree['locked'] == 1) {
		$lockdiv = "<div style='padding:3px;'><table><tr><td><input id='unlock' type='button' value='Unlock Tree'></td><td><input id='addbranch' type='button' value='Add Root Branch' onClick='createNode()'></td><td style='font-weight:bold;'>The tree was locked on '" . $tree['locked_date'] . "' by '" . get_username($tree['modified_by']) . "'";
		if ($tree['modified_by'] == $_SESSION['sess_user_id']) {
			$editable = true;
			$lockdiv .= '</td></tr></table></div>';
		}else{
			$editable = false;
			$lockdiv .= '. To edit the tree, you must first unlock it and then lock it as yourself</td></tr></table></div>';
		}
	}else{
		$tree['id'] = 0;
		$editable = true;
	}

	if ($editable) {
		form_save_button('tree.php', 'return');
	}
		
	print $lockdiv;

	print "<table class='treeTable' cellpadding='0' cellspacing='0' width='100%' border='0' valign='top'><tr valign='top'><td class='treeArea'>\n";

	if (!empty($_REQUEST['id'])) {
		html_start_box('<strong>Tree Items</strong>', '100%', '', '3', 'center', '');

		echo "<tr><td style='padding:7px;'><div id='jstree'></div></td></tr>\n";

		html_end_box();

		print "</td><td></td><td class='treeItemsArea'>\n";

		html_start_box('<strong>Available Devices</strong>', '100%', '', '3', 'center', '');
		?>
		<tr id='treeFilter' class='even noprint'>
			<td>
			<form id='form_tree' action='tree.php'>
				<table cellpadding='2' cellspacing='0'>
					<tr>
						<td width='50'>
							Search
						</td>
						<td>
							<input id='hfilter' type='text' name='hfilter' size='25' value='<?php print htmlspecialchars(get_request_var_request('hfilter'));?>'>
						</td>
					</tr>
				</table>
			</form>
			</td>
		</tr>
		<?php	

		html_end_box(false);

		$display_text = array('Description');

		html_start_box('', '100%', '', '3', 'center', '');
		html_header($display_text);

		echo "<tr><td style='padding:7px;'><div id='hosts'>\n";
		display_hosts();
		echo "</div></td></tr>\n";

		html_end_box();

		print "</td><td></td><td class='treeItemsArea'>\n";

		html_start_box('<strong>Available Graphs</strong>', '100%', '', '3', 'center', '');
		?>
		<tr id='treeFilter' class='even noprint'>
			<td>
			<form id='form_tree' action='tree.php'>
				<table cellpadding='2' cellspacing='0'>
					<tr>
						<td>
							Search
						</td>
						<td>
							<input id='grfilter' type='text' name='grfilter' size='25' value='<?php print htmlspecialchars(get_request_var_request('grfilter'));?>'>
						</td>
					</tr>
				</table>
			</form>
			</td>
		</tr>
		<?php	
		html_end_box(false);

		$display_text = array('Graph Name');

		html_start_box('', '100%', '', '3', 'center', '');
		html_header($display_text);

		echo "<tr><td style='padding:7px;'><div id='graphs'>\n";
		display_graphs();
		echo "</div></td></tr>\n";

		html_end_box();

		print "</td></tr></table>\n";

		?>
		<script type='text/javascript'>
		<?php
		if ($select_first) {
			print "var reset=true;\n";
		}else{
			print "var reset=false;\n";
		}
		?>

		var graphMeTimer;
		var hostMeTimer;
		var hostSortInfo   = {};
		var branchSortInfo = {};

		function createNode() {
			var ref = $('#jstree').jstree(true),
			sel = ref.create_node('#', 'New Node', '0');
			if (sel) {
				ref.edit(sel);
			}
		};

		function getGraphData() {
			$.get('tree.php?action=graphs&filter='+$('#grfilter').val(), function(data) {
				$('#graphs').jstree('destroy');
				$('#graphs').html(data);
				dragable('#graphs');
			});
		}

		function getHostData() {
			$.get('tree.php?action=hosts&filter='+$('#hfilter').val(), function(data) {
				$('#hosts').jstree('destroy');
				$('#hosts').html(data);
				dragable('#hosts');
			});
		}

		function setHostSortIcon(nodeid) {
			if (hostSortInfo[nodeid]) {
				// Already set
			}else{
				$.get('tree.php?action=get_host_sort&nodeid='+nodeid, function(data) {
					hostSortInfo[nodeid] = data;
				});
			}
		}

		function setBranchSortIcon(nodeid) {
			if (branchSortInfo[nodeid]) {
				// Already set
			}else{
				$.get('tree.php?action=get_branch_sort&nodeid='+nodeid, function(data) {
					branchSortInfo[nodeid] = data;
				});
			}
		}

		function getHostSortIcon(type, nodeid) {
			if (hostSortInfo[nodeid] == type) {
				return 'fa fa-check';
			}else{
				return 'false';
			}
		}

		function getBranchSortIcon(type, nodeid) {
			if (branchSortInfo[nodeid] == type) {
				return 'fa fa-check';
			}else{
				return 'false';
			}
		}

		function setBranchSortOrder(type, nodeid) {
			$.get('tree.php?action=set_branch_sort&type='+type+'&nodeid='+nodeid, function(data) {
				branchSortInfo[nodeid] = type;
			});
		}

		function setHostSortOrder(type, nodeid) {
			$.get('tree.php?action=set_host_sort&type='+type+'&nodeid='+nodeid, function(data) {
				hostSortInfo[nodeid] = type;
			});
		}

		$(function() {
			<?php if ($editable == false) {?>
			$('select, input').not('#lock').prop('disabled', true);
			<?php }else{?>
			$('select, input').prop('disabled', false);
			<?php }?>

			$('input[value="Save"]').click(function(event) {
				event.preventDefault();
				$.post('tree.php', { action: 'save', name: $('#name').val(), sort_type: $('#sort_type').val(), enabled: $('#enabled').is(':checked'), id: $('#id').val(), save_component_tree: 1 } ).done(function(data) {
					$('#main').html(data);
					applySkin();
				});
			});

			$('#lock').click(function() {
				$.get('tree.php?action=lock&id=<?php print $tree['id'];?>&header=false', function(data) {
					$('#main').html(data);
					applySkin();
				});
			});

			$('#unlock').click(function() {
				$.get('tree.php?action=unlock&id=<?php print $tree['id'];?>&header=false', function(data) {
					$('#main').html(data);
					applySkin();
				});
			});

			var height      = parseInt($(window).height()-$('#jstree').offset().top-10)+'px';
			var hheight     = parseInt($(window).height()-$('#hosts').offset().top-10)+'px';
			var gheight     = parseInt($(window).height()-$('#graphs').offset().top-10)+'px';

			$(window).resize(function() {
				height      = parseInt($(window).height()-$('#jstree').offset().top-10)+'px';
				hheight     = parseInt($(window).height()-$('#hosts').offset().top-10)+'px';
				gheight     = parseInt($(window).height()-$('#graphs').offset().top-10)+'px';
				$('#jstree').css('height', height).css('overflow','auto');;
				$('#hosts').css('height', hheight).css('overflow','auto');;
				$('#graphs').css('height', gheight).css('overflow','auto');;
			});

			$("#jstree")
			.jstree({
				'types' : {
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
						}else {
							var dataType = 'branch';
						}
						if (dataType == 'graph') {
							return graphContext(node.id);
						}else if (dataType == 'host') {
							return hostContext(node.id);
						}else{
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
					'check_callback' : true
				},
				'themes' : {
					'name' : 'default',
					'responsive' : true,
					'url' : true,
					'dots' : false
				},
				'state': { 'key': 'tree_<?php print $_REQUEST['id'];?>' },
				'plugins' : [ 'state', 'wholerow', <?php if ($editable) {?>'contextmenu', 'dnd', <?php }?>'types' ]
			})
			.on('ready.jstree', function(e, data) {
				if (reset == true) {
					$('#jstree').jstree('clear_state');
				}
			})<?php if ($editable) {?>.on('delete_node.jstree', function (e, data) {
				$.get('?action=delete_node', { 'id' : data.node.id, 'tree_id' : $('#id').val() })
					.fail(function () {
						data.instance.refresh();
					});
				})
			.on('hover_node.jstree', function (e, data) {
				if (data.node.id.search('thost') >= 0) {
					setHostSortIcon(data.node.id);
				}else if (data.node.id.search('thost') < 0 && data.node.id.search('tgraph') < 0) {
					setBranchSortIcon(data.node.id);
				}
			})
			.on('create_node.jstree', function (e, data) {
				$.get('?action=create_node', { 'id' : data.node.parent, 'tree_id' : $('#id').val(), 'position' : data.position, 'text' : data.node.text })
					.done(function (d) {
						data.instance.set_id(data.node, d.id);
					})
					.fail(function () {
						data.instance.refresh();
					});
			})
			.on('rename_node.jstree', function (e, data) {
				$.get('?action=rename_node', { 'id' : data.node.id, 'tree_id' : $('#id').val(), 'text' : data.text })
					.fail(function () {
						data.instance.refresh();
					});
			})
			.on('move_node.jstree', function (e, data) {
				$.get('?action=move_node', { 'id' : data.node.id, 'tree_id' : $('#id').val(), 'parent' : data.parent, 'position' : data.position })
					.always(function () {
						data.instance.refresh();
					});
			})
			.on('copy_node.jstree', function (e, data) {
				$.get('?action=copy_node', { 'id' : data.original.id, 'tree_id' : $('#id').val(), 'parent' : data.parent, 'position' : data.position })
					.always(function () {
						data.instance.refresh();
					});
			})<?php }else{?>.children().bind('contextmenu', function(event) {
				return false;
			})<?php }?>;

			$('#jstree').css('height', height).css('overflow','auto');;

			dragable('#graphs');
			dragable('#hosts');
		});

		function dragable(element) {
			$(element)
				.jstree({
					'types' : {
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
						'check_callback' : true
					},
					'dnd' : {
						'always_copy' : true
					},
					'themes' : { 'stripes' : true },
					'plugins' : [ 'wholerow', 'state', <?php if ($editable) {?>'dnd', <?php }?>'types' ]
				})
				.on('ready.jstree', function(e, data) {
					if (reset == true) {
						$('#jstree').jstree('clear_state');
					}
				})<?php if ($editable) {?>
				.on('copy_node.jstree', function (e, data) {
					$.get('?action=copy_node', { 'id' : data.original.id, 'parent' : data.parent, 'position' : data.position })
						.always(function () {
							data.instance.refresh();
						});
				})<?php }?>;
				$(element).find('.jstree-ocl').hide();
				$(element).children().bind('contextmenu', function(event) {
					return false;
				});
		}

		function branchContext(nodeid) {
			return {
				'create' : {
					'separator_before'	: false,
					'separator_after'	: true,
					'icon'				: 'fa fa-folder',
					'_disabled'			: false,
					'label'				: 'Create',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference),
							obj = inst.get_node(data.reference);
						inst.create_node(obj, {}, 'last', function (new_node) {
							setTimeout(function () { inst.edit(new_node); },0);
						});
					}
				},
				'rename' : {
					'separator_before'	: false,
					'separator_after'	: false,
					'icon'				: 'fa fa-pencil',
					'_disabled'			: false,
					'label'				: 'Rename',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference),
							obj = inst.get_node(data.reference);
						inst.edit(obj);
					}
				},
				'remove' : {
					'separator_before'	: false,
					'icon'				: 'fa fa-remove',
					'separator_after'	: false,
					'_disabled'			: false,
					'label'				: 'Delete',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference),
							obj = inst.get_node(data.reference);
						if(inst.is_selected(obj)) {
							inst.delete_node(inst.get_selected());
						}
						else {
							inst.delete_node(obj);
						}
					}
				},
				'bst' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-sort',
					'separator_after'	: false,
					'label'				: 'Branch Sorting',
					'action'			: false,
					'submenu' : {
						'inherit' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: getBranchSortIcon('inherit', nodeid),
							'label'				: 'Inherit',
							'action'			: function (data) {
								setBranchSortOrder('inherit', nodeid);
							}
						},
						'manual' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: getBranchSortIcon('manual', nodeid),
							'label'				: 'Manual',
							'action'			: function (data) {
								setBranchSortOrder('manual', nodeid);
							}
						},
						'alpha' : {
							'separator_before'	: false,
							'icon'				: getBranchSortIcon('alpha', nodeid),
							'separator_after'	: false,
							'label'				: 'Alphabetic',
							'action'			: function (data) {
								setBranchSortOrder('alpha', nodeid);
							}
						},
						'natural' : {
							'separator_before'	: false,
							'icon'				: getBranchSortIcon('natural', nodeid),
							'separator_after'	: false,
							'label'				: 'Natural',
							'action'			: function (data) {
								setBranchSortOrder('natural', nodeid);
							}
						},
						'numeric' : {
							'separator_before'	: false,
							'icon'				: getBranchSortIcon('numeric', nodeid),
							'separator_after'	: false,
							'label'				: 'Numeric',
							'action'			: function (data) {
								setBranchSortOrder('numeric', nodeid);
							}
						}
					}
				},
				'ccp' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-edit',
					'separator_after'	: false,
					'label'				: 'Edit',
					'action'			: false,
					'submenu' : {
						'cut' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: 'fa fa-cut',
							'label'				: 'Cut',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.cut(inst.get_selected());
								}
								else {
									inst.cut(obj);
								}
							}
						},
						'copy' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-copy',
							'separator_after'	: false,
							'label'				: 'Copy',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.copy(inst.get_selected());
								}
								else {
									inst.copy(obj);
								}
							}
						},
						'paste' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-paste',
							'_disabled'			: function (data) {
								return !$.jstree.reference(data.reference).can_paste();
							},
							'separator_after'	: false,
							'label'				: 'Paste',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
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
					'icon'				: 'fa fa-remove',
					'separator_after'	: false,
					'_disabled'			: false, //(this.check('delete_node', data.reference, this.get_parent(data.reference), '')),
					'label'				: 'Delete',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference),
							obj = inst.get_node(data.reference);
						if(inst.is_selected(obj)) {
							inst.delete_node(inst.get_selected());
						}
						else {
							inst.delete_node(obj);
						}
					}
				},
				'ccp' : {
					'separator_before'	: true,
					'icon'				: 'fa fa-edit',
					'separator_after'	: false,
					'label'				: 'Edit',
					'action'			: false,
					'submenu' : {
						'cut' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: 'fa fa-cut',
							'label'				: 'Cut',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.cut(inst.get_selected());
								}
								else {
									inst.cut(obj);
								}
							}
						},
						'copy' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-copy',
							'separator_after'	: false,
							'label'				: 'Copy',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.copy(inst.get_selected());
								}
								else {
									inst.copy(obj);
								}
							}
						}
					}
				}
			};
		}

		function hostContext(nodeid) {
			return {
				'remove' : {
					'separator_before'	: false,
					'icon'				: 'fa fa-remove',
					'separator_after'	: false,
					'_disabled'			: false,
					'label'				: 'Delete',
					'action'			: function (data) {
						var inst = $.jstree.reference(data.reference),
							obj = inst.get_node(data.reference);
						if(inst.is_selected(obj)) {
							inst.delete_node(inst.get_selected());
						}
						else {
							inst.delete_node(obj);
						}
					}
				},
				'hso' : {
					'separator_before'	: true,
					'separator_after'	: false,
					'icon'				: 'fa fa-sort',
					'label'				: 'Sorting Type',
					'action'			: false,
					'submenu' : {
						'hsgt' : {
							'separator_before'	: false,
							'icon'				: getHostSortIcon('hsgt', nodeid),
							'separator_after'	: false,
							'label'				: 'Graph Template',
							'action'			: function (data) {
								setHostSortOrder('hsgt', nodeid);
							}
						},
						'hsdq' : {
							'separator_before'	: false,
							'icon'				: getHostSortIcon('hsdq', nodeid),
							'separator_after'	: false,
							'label'				: 'Data Query Index',
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
					'label'				: 'Edit',
					'action'			: false,
					'submenu' : {
						'cut' : {
							'separator_before'	: false,
							'separator_after'	: false,
							'icon'				: 'fa fa-cut',
							'label'				: 'Cut',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.cut(inst.get_selected());
								}
								else {
									inst.cut(obj);
								}
							}
						},
						'copy' : {
							'separator_before'	: false,
							'icon'				: 'fa fa-copy',
							'separator_after'	: false,
							'label'				: 'Copy',
							'action'			: function (data) {
								var inst = $.jstree.reference(data.reference),
									obj = inst.get_node(data.reference);
								if(inst.is_selected(obj)) {
									inst.copy(inst.get_selected());
								}
								else {
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
		</script>
		<?php
	}
}

function display_hosts() {
	if ($_REQUEST['filter'] != '') {
		$sql_where = "WHERE hostname LIKE '%" . $_REQUEST['filter'] . "%' OR description LIKE '%" . $_REQUEST['filter'] . "%'";
	}else{
		$sql_where = '';
	}

	$hosts = db_fetch_assoc("SELECT h.id, CONCAT_WS('', 
		h.description, ' (', h.hostname, ')') AS host, 
		ht.name AS template_name 
		FROM host AS h 
		LEFT JOIN host_template AS ht 
		ON ht.id=h.host_template_id 
		$sql_where 
		ORDER BY description 
		LIMIT 20");

	if (sizeof($hosts)) {
		foreach($hosts as $h) {
			if (is_device_allowed($h['id'])) {
				echo "<ul><li id='thost:" . $h['id'] . "' data-jstree='{ \"type\" : \"device\"}'>" . $h['host'] . "</li></ul>\n";
			}
		}
	}
}

function display_graphs() {
	if ($_REQUEST['filter'] != '') {
		$sql_where = "WHERE (title_cache LIKE '%" . $_REQUEST['filter'] . "%' OR gt.name LIKE '%" . $_REQUEST['filter'] . "%') AND local_graph_id>0";
	}else{
		$sql_where = 'WHERE local_graph_id>0';
	}

	$graphs = db_fetch_assoc("SELECT 
		gtg.local_graph_id AS id, 
		gtg.title_cache AS title,
		gt.name AS template_name
		FROM graph_templates_graph AS gtg
		LEFT JOIN graph_templates AS gt
		ON gt.id=gtg.graph_template_id
		$sql_where 
		ORDER BY title_cache 
		LIMIT 20");

	if (sizeof($graphs)) {
		foreach($graphs as $g) {
			if (is_graph_allowed($g['id'])) {
				echo "<ul><li id='tgraph:" . $g['id'] . "' data-jstree='{ \"type\": \"graph\" }'>" . $g['title'] . '</li></ul>';	
			}
		}
	}
}

function tree() {
	global $tree_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up search string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_tree_current_page');
		kill_session_var('sess_tree_filter');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_tree_sort_column');
		kill_session_var('sess_tree_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_tree_current_page', '1');
	load_current_session_value('filter', 'sess_tree_filter', '');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('sort_column', 'sess_tree_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_tree_sort_direction', 'ASC');

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL = 'tree.php?rows=' + $('#rows').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'tree.php?clear_x=1&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	$(function(data) {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_tree').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>

	<?php

	html_start_box('<strong>Graph Trees</strong>', '100%', '', '3', 'center', 'tree.php?action=edit');

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_tree' action='tree.php'>
			<table cellpadding='2' cellspacing='0' border='0'>
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var_request('filter'));?>'>
					</td>
					<td>
						Trees
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' id='clear' name='clear_x' value='Clear' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		</td>
	</tr>
	<?php	

	html_end_box();

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='tree.php'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request('filter'))) {
		$sql_where = "WHERE (t.name LIKE '%" . get_request_var_request('filter') . "%' OR ti.title LIKE '%" . get_request_var_request('filter') . "%')";
	}else{
		$sql_where = '';
	}

	$trees = db_fetch_assoc("SELECT t.*,
		SUM(CASE WHEN ti.host_id>0 THEN 1 ELSE 0 END) AS hosts,
		SUM(CASE WHEN ti.local_graph_id>0 THEN 1 ELSE 0 END) AS graphs,
		SUM(CASE WHEN ti.local_graph_id=0 AND host_id=0 THEN 1 ELSE 0 END) AS branches
		FROM graph_tree AS t
		LEFT JOIN graph_tree_items AS ti
		ON t.id=ti.graph_tree_id
		$sql_where
		GROUP BY t.id
		ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') . '
		LIMIT ' . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows'));

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM graph_tree AS t
		LEFT JOIN graph_tree_items AS ti
		ON t.id=ti.graph_tree_id
		$sql_where
		GROUP BY t.id");

	$nav = html_nav_bar('tree.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 11, 'Trees', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('display' => 'Tree Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name by which this Tree will be referred to as.'),
		'id' => array('display' => 'ID', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The internal database ID for this Tree.  Usefull when performing automation or debugging.'),
		'enabled' => array('display' => 'Published', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'Unpublished Trees can not be viewed from the Graph tab'),
		'locked' => array('display' => 'Locked', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'A Tree must be locked in order to be edited.'),
		'user_id' => array('display' => 'Owner', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The original author of this Tree.'),
		'last_modified' => array('display' => 'Last Edited', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The date that this Tree was last edited.'),
		'modified_by' => array('display' => 'Edited By', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The last user to have modified this Tree.'),
		'branches' => array('display' => 'Branches', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The total number of Branches in this Tree.'),
		'hosts' => array('display' => 'Devices', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The total number of individual Devices in this Tree.'),
		'graphs' => array('display' => 'Graphs', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The total number of individual Graphs in this Tree.'));

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	$i = 0;
	if (sizeof($trees) > 0) {
		foreach ($trees as $tree) {
			form_alternate_row('line' . $tree['id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('tree.php?action=edit&id=' . $tree['id']) . "'>" .
				(strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($tree['name'])) : htmlspecialchars($tree['name'])) . '</a>', $tree['id']);
			form_selectable_cell($tree['id'], $tree['id'], '', 'text-align:right');
			form_selectable_cell($tree['enabled'] == 'on' ? 'Yes':'No', $tree['id']);
			form_selectable_cell($tree['locked'] == '1' ? 'Yes':'No', $tree['id']);
			form_selectable_cell(get_username($tree['user_id']), $tree['id']);
			form_selectable_cell(substr($tree['last_modified'],0,16), $tree['id'], '', 'text-align:right');
			form_selectable_cell(get_username($tree['modified_by']), $tree['id'], '', 'text-align:right');
			form_selectable_cell($tree['branches'] > 0 ? number_format($tree['branches']):'-', $tree['id'], '', 'text-align:right');
			form_selectable_cell($tree['hosts'] > 0 ? number_format($tree['hosts']):'-', $tree['id'], '', 'text-align:right');
			form_selectable_cell($tree['graphs'] > 0 ? number_format($tree['graphs']):'-', $tree['id'], '', 'text-align:right');
			form_checkbox_cell($tree['name'], $tree['id']);
			form_end_row();
		}

		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='11'><em>No Trees Found</em></td></tr>";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($tree_actions);

	print "</form>\n";
}

