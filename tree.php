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

include('./include/auth.php');
include_once('./lib/api_tree.php');
include_once('./lib/tree.php');
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
		lock_tree();
		break;
	case 'unlock':
		unlock_tree();
		break;
	default:
		top_header();
		tree();
		bottom_footer();
		break;
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
		$save['last_modified'] = date("Y-m-d H:i:s", time());
		$save['modified_by']   = $_SESSION['sess_user_id'];
		if (empty($save['id'])) {
			$save['user_id'] = $_SESSION['sess_user_id'];
		}

		if (!is_error_message()) {
			$tree_id = sql_save($save, 'graph_tree');

			if ($tree_id) {
				raise_message(1);

				/* sort the tree using the algorithm chosen by the user */
				sort_tree(SORT_TYPE_TREE, $tree_id, $_POST['sort_type']);
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
function lock_tree() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	db_execute("UPDATE graph_tree 
		SET locked=1, 
		locked_date=NOW(), 
		last_modified=NOW(), 
		modified_by=" . $_SESSION['sess_user_id'] . " 
		WHERE id=" . $_REQUEST['id']);

	header('Location: tree.php?action=edit&header=false&id=' . $_REQUEST['id']);
}

function unlock_tree() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	db_execute("UPDATE graph_tree 
		SET locked=0, 
		last_modified=NOW(), 
		modified_by=" . $_SESSION['sess_user_id'] . " 
		WHERE id=" . $_REQUEST['id']);

	header('Location: tree.php?action=edit&header=false&id=' . $_REQUEST['id']);
}

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
				modified_by=" . $_SESSION['sess_user_id'] . "
				WHERE " . array_to_sql_or($selected_items, 'id'));
		}elseif ($_POST['drp_action'] == '3') { /* un-publish */
			db_execute("UPDATE graph_tree 
				SET enabled='',
				last_modified=NOW(),
				modified_by=" . $_SESSION['sess_user_id'] . "
				WHERE " . array_to_sql_or($selected_items, 'id'));
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

			$tree_list .= '<li>' . htmlspecialchars(db_fetch_cell('SELECT name FROM graph_tree WHERE id=' . $matches[1])) . '</li>';
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

		form_confirm('Are You Sure?', "Are you sure you want to delete the tree <strong>'" . htmlspecialchars(db_fetch_cell('select name from graph_tree where id=' . $_REQUEST['id']), ENT_QUOTES) . "'</strong>?", htmlspecialchars('tree.php'), htmlspecialchars('tree.php?action=remove&id=' . $_REQUEST['id']));

		bottom_footer();
		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset($_REQUEST['confirm']))) {
		db_execute('delete from graph_tree where id=' . $_REQUEST['id']);
		db_execute('delete from graph_tree_items where graph_tree_id=' . $_REQUEST['id']);
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
		$tree = db_fetch_row('select * from graph_tree where id=' . $_REQUEST['id']);
		$header_label = '[edit: ' . htmlspecialchars($tree['name']) . ']';
	}else{
		$header_label = '[new]';
	}

	html_start_box('<strong>Graph Trees</strong> ' . $header_label, '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array(),
		'fields' => inject_form_variables($fields_tree_edit, (isset($tree) ? $tree : array()))
		));

	html_end_box();

	form_save_button('tree.php', 'return');

	if (isset($tree['locked']) && $tree['locked'] == 0) {
		print "<div style='padding:3px;'><input id='lock' type='button' value='Lock Tree'></div>\n";
	}elseif (isset($tree['locked']) && $tree['locked'] == 1) {
		print "<div style='padding:3px;'><input id='unlock' type='button' value='Unlock Tree'>&nbsp;Locked on '" . $tree['locked_date'] . "'</div>\n";
	}else{
		$tree['id'] = 0;
	}

	print "<table class='treeTable' cellpadding='0' cellspacing='0' width='100%' border='0' valign='top'><tr valign='top'><td class='treeArea'>\n";

	if (!empty($_REQUEST['id'])) {
		html_start_box('<strong>Tree Items</strong>', '100%', '', '3', 'center', '');

		$dhtml_tree = create_dhtml_tree_edit($_REQUEST['id']);

		$total_tree_items = sizeof($dhtml_tree) - 1;

		echo "<tr><td style='padding:7px;'>\n";
		for ($i = 2; $i <= $total_tree_items; $i++) {
			print $dhtml_tree[$i];
		}

		echo "</td></tr>\n";

		html_end_box();

		print "</td><td></td><td class='treeItemsArea'>\n";

		html_start_box('<strong>Available Hosts</strong>', '100%', '', '3', 'center', '');
		?>
		<tr id='treeFilter' class='even noprint'>
			<td>
			<form id='form_tree' action='tree.php'>
				<table cellpadding='2' cellspacing='0'>
					<tr>
						<td>
							Search:
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
							Search:
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
		if ((!isset($_SESSION['sess_node_id']) && !isset($_REQUEST['tree_id'])) || isset($_REQUEST['select_first'])) {
			print "var reset=true;\n";
		}elseif (isset($_REQUEST['tree_id'])) {
			print "var reset=false;\n";
		}elseif (isset($_SESSION['sess_node_id'])) {
			print "var reset=false;\n";
		}else{
			print "var reset=true;\n";
		}
		?>

		$(function() {
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
				'core' : {
					'animation' : 0,
					'check_callback' : true
				},
				'themes' : {
					'name' : 'default',
					'responsive' : true,
					'url' : true,
					'dots' : false
				},
				'plugins' : [ 'state', 'wholerow', 'contextmenu', 'dnd', 'types' ]
			})
			.on('ready.jstree', function(e, data) {
				if (reset == true) {
					$('#jstree').jstree('clear_state');
				}
			})
			.on('delete_node.jstree', function (e, data) {
				$.get('?operation=delete_node', { 'id' : data.node.id })
					.fail(function () {
						data.instance.refresh();
					});
				})
			.on('create_node.jstree', function (e, data) {
				$.get('?operation=create_node', { 'id' : data.node.parent, 'position' : data.position, 'text' : data.node.text })
					.done(function (d) {
						data.instance.set_id(data.node, d.id);
					})
					.fail(function () {
						data.instance.refresh();
					});
			})
			.on('rename_node.jstree', function (e, data) {
				$.get('?operation=rename_node', { 'id' : data.node.id, 'text' : data.text })
					.fail(function () {
						data.instance.refresh();
					});
			})
			.on('move_node.jstree', function (e, data) {
				$.get('?operation=move_node', { 'id' : data.node.id, 'parent' : data.parent, 'position' : data.position })
					.fail(function () {
						data.instance.refresh();
					});
			})
			.on('copy_node.jstree', function (e, data) {
				$.get('?operation=copy_node', { 'id' : data.original.id, 'parent' : data.parent, 'position' : data.position })
					.always(function () {
						data.instance.refresh();
					});
			})
			.on('changed.jstree', function (e, data) {
				if(data && data.selected && data.selected.length) {
					$.get('?operation=get_content&id=' + data.selected.join(':'), function (d) {
						$('#data .default').html(d.content).show();
					});
				} else {
					$('#data .content').hide();
					$('#data .default').html('Select a file from the tree.').show();
				}
			});

			dragable('#graphs');
			dragable('#hosts');
		});

		function dragable(element) {
			$(element)
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
					'core' : {
						'animation' : 0,
						'check_callback' : true
					},
					'dnd' : {
						'always_copy' : true
					},
					'themes' : { 'stripes' : true },
					'plugins' : [ 'wholerow', 'state', 'dnd', 'types' ]
				})
				.on('ready.jstree', function(e, data) {
					if (reset == true) {
						$('#jstree').jstree('clear_state');
					}
				})
				.on('delete_node.jstree', function (e, data) {
					$.get('?operation=delete_node', { 'id' : data.node.id })
						.fail(function () {
							data.instance.refresh();
						});
					})
				.on('create_node.jstree', function (e, data) {
					$.get('?operation=create_node', { 'id' : data.node.parent, 'position' : data.position, 'text' : data.node.text })
						.done(function (d) {
							data.instance.set_id(data.node, d.id);
						})
						.fail(function () {
							data.instance.refresh();
						});
				})
				.on('rename_node.jstree', function (e, data) {
					$.get('?operation=rename_node', { 'id' : data.node.id, 'text' : data.text })
						.fail(function () {
							data.instance.refresh();
						});
				})
				.on('move_node.jstree', function (e, data) {
					$.get('?operation=move_node', { 'id' : data.node.id, 'parent' : data.parent, 'position' : data.position })
						.fail(function () {
							data.instance.refresh();
						});
				})
				.on('copy_node.jstree', function (e, data) {
					$.get('?operation=copy_node', { 'id' : data.original.id, 'parent' : data.parent, 'position' : data.position })
						.always(function () {
							data.instance.refresh();
						});
				})
				.on('changed.jstree', function (e, data) {
					if(data && data.selected && data.selected.length) {
						$.get('?operation=get_content&id=' + data.selected.join(':'), function (d) {
							$('#data .default').html(d.content).show();
						});
					} else {
						$('#data .content').hide();
						$('#data .default').html('Select a file from the tree.').show();
					}
				});
				$(element).find('.jstree-ocl').hide();
				$(element).children().bind('contextmenu', function(event) {
					return false;
				});
		}

		var graphMeTimer;
		var hostMeTimer;

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
		$sql_where = "";
	}

	$hosts = db_fetch_assoc("SELECT h.id, CONCAT_WS('', 
		h.description, ' (', h.hostname, ')') AS host, 
		ht.name AS template_name 
		FROM host AS h 
		LEFT JOIN host_template AS ht 
		ON ht.id=h.host_template_id 
		$sql_where 
		ORDER BY description 
		LIMIT 10");

	if (sizeof($hosts)) {
		foreach($hosts as $h) {
			echo "<ul><li id='host_" . $h['id'] . "' data-jstree='{ \"type\" : \"device\"}'>" . $h['host'] . "</li></ul>\n";
		}
	}
}

function display_graphs() {
		if ($_REQUEST['filter'] != '') {
			$sql_where = "WHERE (title_cache LIKE '%" . $_REQUEST['filter'] . "%' OR gt.name LIKE '%" . $_REQUEST['filter'] . "%') AND local_graph_id>0";
		}else{
			$sql_where = "WHERE local_graph_id>0";
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
			LIMIT 10");

		$i = 0;
		if (sizeof($graphs)) {
			foreach($graphs as $g) {
				echo "<ul><li id='graph_" . $g['id'] . "' data-jstree='{ \"type\": \"graph\" }'>" . $g['title'] . "</li></ul>";	
				$i++;
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
			<table cellpadding='2' cellspacing='0'>
				<tr>
					<td width='50'>
						Search:
					</td>
					<td width='1'>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var_request('filter'));?>'>
					</td>
					<td style='white-space:nowrap;'>
						Graph Templates:
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
        ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') . "
        LIMIT " . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows'));

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM graph_tree AS t
		LEFT JOIN graph_tree_items AS ti
		ON t.id=ti.graph_tree_id
		$sql_where
		GROUP BY t.id");

	$nav = html_nav_bar('tree.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 11, 'Trees', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('Tree Name', 'ASC'),
		'id' => array('display' => 'ID', 'align' => 'right', 'sort' => 'ASC'),
		'enabled' => array('Published', 'ASC'),
		'locked' => array('Locked', 'ASC'),
		'user_id' => array('Owner', 'ASC'),
		'last_modified' => array('display' => 'Last Edited', 'align' => 'right', 'sort' => 'ASC'),
		'modified_by' => array('display' => 'Edited By', 'align' => 'right', 'sort' => 'ASC'),
		'branches' => array('display' => 'Branches', 'align' => 'right', 'sort' => 'DESC'),
		'hosts' => array('display' => 'Hosts', 'align' => 'right', 'sort' => 'DESC'),
		'graphs' => array('display' => 'Graphs', 'align' => 'right', 'sort' => 'DESC'));

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

?>
