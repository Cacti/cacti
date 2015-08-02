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

$guest_account = true;
include('./include/auth.php');
include_once('./lib/html_tree.php');
include_once('./lib/html_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/timespan_settings.php');

define('MAX_DISPLAY_PAGES', 21);

/* ================= input validation ================= */
if (isset($_REQUEST['id']) && $_REQUEST['id'] == '#') {
	$_REQUEST['id'] = '0';
}

input_validate_input_number(get_request_var_request('branch_id'));
input_validate_input_number(get_request_var_request('tree_id'));
input_validate_input_number(get_request_var_request('graphs'));
input_validate_input_number(get_request_var_request('rows'));
input_validate_input_number(get_request_var_request('hide'));
input_validate_input_number(get_request_var_request('leaf_id'));
input_validate_input_number(get_request_var_request('rra_id'));
input_validate_input_number(get_request_var_request('columns'));
input_validate_input_number(get_request_var_request('predefined_timespan'));
input_validate_input_number(get_request_var_request('predefined_timeshift'));
input_validate_input_regex(get_request_var_request('graph_list'), '^([\,0-9]+)$');
input_validate_input_regex(get_request_var_request('graph_add'), '^([\,0-9]+)$');
input_validate_input_regex(get_request_var_request('graph_remove'), '^([\,0-9]+)$');
input_validate_input_regex(get_request_var_request('nodeid'), '^([_a-zA-Z0-9]+)$');
/* ==================================================== */

/* clean up action string */
if (isset($_REQUEST['action'])) {
	$_REQUEST['action'] = sanitize_search_string(get_request_var_request('action'));
}

if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'save') {
	if (is_view_allowed('graph_settings')) {
		if (isset($_REQUEST['predefined_timespan'])) {
			set_graph_config_option('default_timespan', $_REQUEST['predefined_timespan']);
		}
		if (isset($_REQUEST['predefined_timeshift'])) {
			set_graph_config_option('default_timeshift', $_REQUEST['predefined_timeshift']);
		}
		if (isset($_REQUEST['section']) && $_REQUEST['section'] == 'preview') {
			if (isset($_REQUEST['columns'])) {
				set_graph_config_option('num_columns', get_request_var_request('columns'));
			}
			if (isset($_REQUEST['graphs'])) {
				set_graph_config_option('preview_graphs_per_page', get_request_var_request('graphs'));
			}
			if (isset($_REQUEST['thumbnails'])) {
				set_graph_config_option('thumbnail_section_preview', $_REQUEST['thumbnails'] == 'true' ? 'on':'');
			}
		}else{
			if (isset($_REQUEST['columns'])) {
				set_graph_config_option('num_columns_tree', get_request_var_request('columns'));
			}
			if (isset($_REQUEST['graphs'])) {
				set_graph_config_option('treeview_graphs_per_page', get_request_var_request('graphs'));
			}
			if (isset($_REQUEST['thumbnails'])) {
				set_graph_config_option('thumbnail_section_tree_2', $_REQUEST['thumbnails'] == 'true' ? 'on':'');
			}
		}
	}

	exit;
}

/* setup tree selection defaults if the user has not been here before */
if (!isset($_REQUEST['action'])) {
	if (isset($_REQUEST['header'])) {
		$_REQUEST['action'] = 'tree_content';
	}elseif (!isset($_SESSION['sess_graph_view_action'])) {
		if (read_graph_config_option('default_view_mode') == '1') {
			$_REQUEST['action'] = 'tree';
		}elseif (read_graph_config_option('default_view_mode') == '2') {
			$_REQUEST['action'] = 'list';
		}elseif (read_graph_config_option('default_view_mode') == '3') {
			$_REQUEST['action'] = 'preview';
		}
	}else{
		$_REQUEST['action'] = $_SESSION['sess_graph_view_action'];
	}
}

if ($_REQUEST['action'] != 'get_node' && $_REQUEST['action'] != 'tree_content') {
	$_SESSION['sess_graph_view_action'] = $_REQUEST['action'];
}

if (read_config_option('auth_method') != 0) {
	$current_user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array($_SESSION['sess_user_id']));
}

if (isset($_REQUEST['hide'])) {
	if (($_REQUEST['hide'] == '0') || ($_REQUEST['hide'] == '1')) {
		/* only update expand/contract info is this user has rights to keep their own settings */
		if ((isset($current_user)) && ($current_user['graph_settings'] == 'on')) {
			db_execute_prepared('DELETE FROM settings_tree WHERE graph_tree_item_id = ? AND user_id = ?', array($_REQUEST['branch_id'], $_SESSION['sess_user_id']));
			db_execute_prepared('INSERT INTO settings_tree (graph_tree_item_id, user_id,status) values (?, ?, ?)', array($_REQUEST['branch_id'], $_SESSION['sess_user_id'], $_REQUEST['hide']));
		}
	}
}

if (!isset($_SESSION['sess_realtime_dsstep'])) {
	$_SESSION['sess_realtime_dsstep'] = read_config_option('realtime_interval');
}
if (!isset($_SESSION['sess_realtime_window'])) {
	$_SESSION['sess_realtime_window'] = read_config_option('realtime_gwindow');
}

switch ($_REQUEST['action']) {
case 'tree':
	if (isset($_REQUEST['tree_id'])) {
		$_SESSION['sess_tree_id'] = $_REQUEST['tree_id'];
	}

	top_graph_header();

	bottom_footer();

	break;
case 'get_node':
	$parent  = -1;
	$tree_id = 0;
	if (isset($_REQUEST['tree_id'])) {
		if ($_REQUEST['tree_id'] == 'default' || $_REQUEST['tree_id'] == 'undefined') {
			$tree_id = read_graph_config_option('default_tree_id');
		}elseif ($_REQUEST['tree_id'] == 0 && substr_count($_REQUEST['id'], 'tree_anchor') > 0) {
			$ndata = explode('-', $_REQUEST['id']);
			$tree_id = $ndata[1];
			input_validate_input_number($tree_id);
		}
	}else{
		$tree_id = read_graph_config_option('default_tree_id');
	}

	if (isset($_REQUEST['id']) && $_REQUEST['id'] != '#') {
		if (substr_count($_REQUEST['id'], 'tree_anchor')) {
			$parent = -1;
		}else{
			$ndata = explode('_', $_REQUEST['id']);

			foreach($ndata as $node) {
				$pnode = explode('-', $node);
	
				if ($pnode[0] == 'tbranch') {
					$parent = $pnode[1];
					input_validate_input_number($parent);
					$tree_id = db_fetch_cell("SELECT graph_tree_id FROM graph_tree_items WHERE id=$parent");
					break;
				}
			}
		}
	}

	api_tree_get_main($tree_id, $parent); exit;
	
	break;
case 'tree_content':
	validate_tree_vars();

	if (!is_view_allowed('show_tree')) {
		print "<strong><font class='txtErrorTextBox'>YOU DO NOT HAVE RIGHTS FOR TREE VIEW</font></strong>"; return;
	}

	?>
	<script type='text/javascript'>
	var graph_start=<?php print get_current_graph_start();?>;
	var graph_end=<?php print get_current_graph_end();?>;
	var timeOffset=<?php print date('Z');?>
	</script>
	<?php

	$access_denied = false;
	$tree_parameters = array();

	if (isset($_REQUEST['nodeid'])) {
		$_SESSION['sess_node_id'] = 'tbranch-' . $_REQUEST['nodeid'];
	}

	if (isset($_REQUEST['tree_id'])) {
		if (!is_tree_allowed($_REQUEST['tree_id'])) {
			header('Location: permission_denied.php');
			exit;
		}

        $_SESSION['sess_tree_id'] = $_REQUEST['tree_id'];

		grow_right_pane_tree((isset($_REQUEST['tree_id']) ? $_REQUEST['tree_id'] : 0), (isset($_REQUEST['leaf_id']) ? $_REQUEST['leaf_id'] : 0), (isset($_REQUEST['host_group_data']) ? urldecode($_REQUEST['host_group_data']) : 0));
	}

	break;
case 'preview':
	top_graph_header();

	if (!is_view_allowed('show_preview')) {
		print "<strong><font class='txtErrorTextBox'>YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW</font></strong>"; return;
	}

	html_graph_validate_preview_request_vars();

	/* include graph view filter selector */
	html_start_box('<strong>Graph Preview Filters</strong>' . (isset($_REQUEST['style']) && strlen($_REQUEST['style']) ? ' [ Custom Graph List Applied - Filtering from List ]':''), '100%', '', '3', 'center', '');

	html_graph_preview_filter('graph_view.php', 'preview');

	html_end_box();

	/* the user select a bunch of graphs of the 'list' view and wants them displayed here */
	$sql_or = '';
	if (isset($_REQUEST['style'])) {
		if (get_request_var_request('style') == 'selective') {

			/* process selected graphs */
			if (!empty($_REQUEST['graph_list'])) {
				foreach (explode(',',$_REQUEST['graph_list']) as $item) {
					$graph_list[$item] = 1;
				}
			}else{
				$graph_list = array();
			}
			if (!empty($_REQUEST['graph_add'])) {
				foreach (explode(',',$_REQUEST['graph_add']) as $item) {
					$graph_list[$item] = 1;
				}
			}
			/* remove items */
			if (!empty($_REQUEST['graph_remove'])) {
				foreach (explode(',',$_REQUEST['graph_remove']) as $item) {
					unset($graph_list[$item]);
				}
			}

			$i = 0;
			foreach ($graph_list as $item => $value) {
				$graph_array[$i] = $item;
				$i++;
			}

			if ((isset($graph_array)) && (sizeof($graph_array) > 0)) {
				/* build sql string including each graph the user checked */
				$sql_or = array_to_sql_or($graph_array, 'gtg.local_graph_id');

				$set_rra_id = empty($rra_id) ? read_graph_config_option('default_rra_id') : get_request_var_request('rra_id');
			}
		}
	}

	$total_graphs = 0;

	$sql_where  = (strlen($_REQUEST['filter']) ? "gtg.title_cache LIKE '%%" . get_request_var_request('filter') . "%%'":'');
	$sql_where .= (strlen($sql_or) && strlen($sql_where) ? ' AND ':'') . $sql_or;
	$sql_where .= ($_REQUEST['host_id'] > 0 ? (strlen($sql_where) ? ' AND':'') . ' gl.host_id=' . $_REQUEST['host_id']:'');
	$sql_where .= ($_REQUEST['graph_template_id'] > 0 ? (strlen($sql_where) ? ' AND':'') . ' gl.graph_template_id=' . $_REQUEST['graph_template_id']:'');

	$limit      = ($_REQUEST['graphs']*($_REQUEST['page']-1)) . ',' . $_REQUEST['graphs'];
	$order      = 'gtg.title_cache';

	$graphs     = get_allowed_graphs($sql_where, $order, $limit, $total_graphs);	

	/* do some fancy navigation url construction so we don't have to try and rebuild the url string */
	if (preg_match('/page=[0-9]+/',basename($_SERVER['QUERY_STRING']))) {
		$nav_url = str_replace('&page=' . get_request_var_request('page'), '', get_browser_query_string());
	}else{
		$nav_url = get_browser_query_string() . '&host_id=' . get_request_var_request('host_id');
	}

	$nav_url = preg_replace('/((\?|&)host_id=[0-9]+|(\?|&)filter=[a-zA-Z0-9]*)/', '', $nav_url);

	html_start_box('', '100%', '', '3', 'center', '');

	$nav = html_nav_bar($nav_url, MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('graphs'), $total_graphs, get_request_var_request('columns'), 'Graphs', 'page', 'main');

	print $nav;

	if (get_request_var_request('thumbnails') == 'true') {
		html_graph_thumbnail_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var_request('columns'));
	}else{
		html_graph_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var_request('columns'));
	}

	if ($total_graphs > 0) {
		print $nav;
	}

	html_end_box();

	if (!isset($_REQUEST['header']) || $_REQUEST['header'] == false) {
		bottom_footer();
	}

	break;
case 'list':
	top_graph_header();

	if (!is_view_allowed('show_list')) {
		print "<strong><font class='txtErrorTextBox'>YOU DO NOT HAVE RIGHTS FOR LIST VIEW</font></strong>"; return;
	}

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('host_id'));
	input_validate_input_number(get_request_var_request('graph_template_id'));
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* reset the graph list on a new viewing */
	if (!isset($_REQUEST['page'])) {
		$_REQUEST['graph_list'] = '';
		$_REQUEST['page'] = 1;
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_graph_view_list_current_page');
		kill_session_var('sess_graph_view_list_filter');
		kill_session_var('sess_graph_view_list_host');
		kill_session_var('sess_graph_view_list_graph_template');
		kill_session_var('sess_graph_view_list_rows');
		kill_session_var('sess_graph_view_list_graph_list');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['host_id']);
		unset($_REQUEST['graph_template_id']);
		unset($_REQUEST['graph_list']);
		unset($_REQUEST['graph_add']);
		unset($_REQUEST['graph_remove']);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = false;
		$changed += check_changed('host_id', 'sess_graph_view_list_host');
		$changed += check_changed('rows', 'sess_graph_view_list_rows');
		$changed += check_changed('graph_template_id', 'sess_graph_view_list_graph_template');
		$changed += check_changed('filter', 'sess_graph_view_list_filter');
		if ($changed) $_REQUEST['page'] = 1;
	}

	load_current_session_value('host_id', 'sess_graph_view_list_host', '0');
	load_current_session_value('graph_template_id', 'sess_graph_view_list_graph_template', '0');
	load_current_session_value('filter', 'sess_graph_view_list_filter', '');
	load_current_session_value('page', 'sess_graph_view_list_current_page', '1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('graph_list', 'sess_graph_view_list_graph_list', '');

	/* save selected graphs into url */
	if (!empty($_REQUEST['graph_list'])) {
		foreach (explode(',',$_REQUEST['graph_list']) as $item) {
			$graph_list[$item] = 1;
		}
	}else{
		$graph_list = array();
	}
	if (!empty($_REQUEST['graph_add'])) {
		foreach (explode(',',$_REQUEST['graph_add']) as $item) {
			$graph_list[$item] = 1;
		}
	}
	/* remove items */
	if (!empty($_REQUEST['graph_remove'])) {
		foreach (explode(',',$_REQUEST['graph_remove']) as $item) {
			unset($graph_list[$item]);
		}
	}

	/* update the revised graph list session variable */
	$_REQUEST['graph_list'] = implode(',', array_keys($graph_list));
	load_current_session_value('graph_list', 'sess_graph_view_list_graph_list', '');

	/* display graph view filter selector */
	html_start_box('<strong>Graph List View Filters</strong>' . (isset($_REQUEST['style']) && strlen($_REQUEST['style']) ? ' [ Custom Graph List Applied - Filter FROM List ]':''), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
		<form id='form_graph_list' name='form_graph_list' method='post' action='graph_view.php?action=list'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						Device
					</td>
					<td>
						<select id='host_id' name='host_id' onChange='applyFilter()'>
							<option value='0'<?php if (get_request_var_request('host_id') == '0') {?> selected<?php }?>>Any</option>
							<?php
							$hosts = get_allowed_devices();
							if (sizeof($hosts) > 0) {
								foreach ($hosts as $host) {
									print "<option value='" . $host['id'] . "'"; if (get_request_var_request('host_id') == $host['id']) { print ' selected'; } print '>' . htmlspecialchars($host['description']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						Template
					</td>
					<td>
						<select id='graph_template_id' name='graph_template_id' onChange='applyFilter()'>
							<option value='0'<?php print htmlspecialchars(get_request_var_request('filter'));?><?php if (get_request_var_request('host_id') == '0') {?> selected<?php }?>>Any</option>
							<?php

							$graph_templates = get_allowed_graph_templates();

							if (sizeof($graph_templates) > 0) {
								foreach ($graph_templates as $template) {
									print "<option value='" . $template['id'] . "'"; if (get_request_var_request('graph_template_id') == $template['id']) { print ' selected'; } print '>' . htmlspecialchars($template['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						Graphs
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var_request('filter'));?>'>
					</td>
					<td>
						<input type='button' id='refresh' value='Go' title='Set/Refresh Filters' onClick='applyFilter()'>
					</td>
					<td>
						<input type='button' id='clear' value='Clear' title='Clear Filters' onClick='clearFilter()'>
					</td>
					<td>
						<input type='button' value='View' title='View Graphs' onClick='viewGraphs()'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='graph_add' value=''>
			<input type='hidden' name='graph_remove' value=''>
			<input type='hidden' name='graph_list' value='<?php print $_REQUEST['graph_list'];?>'>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		</td>
	</tr>
	<?php
	html_end_box();

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_graph_config_option('num_rows_table');
	}

	/* create filter for sql */
	$sql_where  = '';
	$sql_where .= (empty($_REQUEST['filter']) ? '' : " gtg.title_cache LIKE '%" . get_request_var_request('filter') . "%'");
	$sql_where .= (empty($_REQUEST['host_id']) ? '' : (empty($sql_filter) ? '' : ' AND') . ' gl.host_id=' . get_request_var_request('host_id'));
	$sql_where .= (empty($_REQUEST['graph_template_id']) ? '' : (empty($sql_filter) ? '' : ' AND') . ' gl.graph_template_id=' . get_request_var_request('graph_template_id'));

	$total_rows = 0;
	$limit      = ($_REQUEST['rows']*($_REQUEST['page']-1)) . ',' . $_REQUEST['rows'];

	$graphs = get_allowed_graphs($sql_where, 'gtg.title_cache', $limit, $total_rows);

	form_start('graph_view.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$nav = html_nav_bar('graph_view.php?action=list', MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 5, 'Graphs', 'page', 'main');

	print $nav;

	html_header_checkbox(array('Graph Title', 'Device', 'Graph Template', 'Graph Size'), false);

	$i = 0;
	if (sizeof($graphs)) {
		foreach ($graphs as $graph) {
			form_alternate_row('line' . $graph['local_graph_id'], true);
			form_selectable_cell("<strong><a href='" . htmlspecialchars('graph.php?local_graph_id=' . $graph['local_graph_id'] . '&rra_id=0') . "'>" . htmlspecialchars($graph['title_cache']) . '</a></strong>', $graph['local_graph_id']);
			form_selectable_cell($graph['description'], $graph['local_graph_id']);
			form_selectable_cell($graph['template_name'], $graph['local_graph_id']);
			form_selectable_cell($graph['height'] . 'x' . $graph['width'], $graph['local_graph_id']);
			form_checkbox_cell($graph['title_cache'], $graph['local_graph_id']);
			form_end_row();
		}

		print $nav;
	}

	html_end_box();

	?>
	<table align='right'>
	<tr>
		<td align='right'><img src='images/arrow.gif' alt=''>&nbsp;</td>
		<td align='right'><input type='button' value='View' title='View Graphs' onClick='viewGraphs()'></td>
	</tr>
	</table>
	<input type='hidden' name='style' value='selective'>
	<input type='hidden' name='action' value='preview'>
	<input type='hidden' id='graph_list' name='graph_list' value='<?php print $_REQUEST['graph_list']; ?>'>
	<input type='hidden' id='graph_add' name='graph_add' value=''>
	<input type='hidden' id='graph_remove' name='graph_remove' value=''>
	</form>
	<script type='text/javascript'>
	var graph_list_array = new Array(<?php print $_REQUEST['graph_list'];?>);

	$(function() {
		initializeChecks();
	});

	function clearFilter() {
		strURL = 'graph_view.php?action=list&header=false&clear=1';
		loadPageNoHeader(strURL);
	}

	function applyFilter() {
		strURL = 'graph_view.php?action=list&header=false&page=1';
		strURL += '&host_id=' + $('#host_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&graph_template_id=' + $('#graph_template_id').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += url_graph('');
		loadPageNoHeader(strURL);
	}

	function initializeChecks() {
		for (var i = 0; i < graph_list_array.length; i++) {
			$('#line'+graph_list_array[i]).addClass('selected');
			$('#chk_'+graph_list_array[i]).prop('checked', true);
			$('#chk_'+graph_list_array[i]).parent().addClass('selected');
		}
	}

	function viewGraphs() {
		graphList = $('#graph_list').val();
		$('input[id^=chk_]').each(function(data) {
			graphID = $(this).attr('id').replace('chk_','');
			if ($(this).is(':checked')) {
				graphList += (graphList.length > 0 ? ',':'') + graphID;
			}
		});
		$('#graph_list').val(graphList);

		document.chk.submit();
	}

	function url_graph(strNavURL) {
		var strURL = '';
		var strAdd = '';
		var strDel = '';
		$('input[id^=chk_]').each(function(data) {
			graphID = $(this).attr('id').replace('chk_','');
			if ($(this).is(':checked')) {
				strAdd += (strAdd.length > 0 ? ',':'') + graphID;
			} else if (graphChecked(graphID)) {
				strDel += (strDel.length > 0 ? ',':'') + graphID;
			}
		});

		strURL = '&graph_add=' + strAdd + '&graph_remove=' + strDel;

		return strNavURL + strURL;
	}

	function graphChecked(graph_id) {
		for(var i = 0; i < graph_list_array.length; i++) {
			if (graph_list_array[i] == graph_id) {
				return true;
			}
		}

		return false;
	}

	function form_graph(objForm,objFormSubmit) {
		var strAdd = '';
		var strDel = '';
		$('input[id^=chk_]').each(function(data) {
			graphID = $(this).attr('id').replace('chk_','');
			if ($(this).is(':checked')) {
				strAdd += (strAdd.length > 0 ? ',':'') + graphID;
			} else if (graphChecked(graphID)) {
				strAdd += (strAdd.length > 0 ? ',':'') + graphID;
			}
		});
		objFormSubmit.graph_add.value = strAdd;
		objFormSubmit.graph_remove.value = strDel;
	}

	$(function() {
		$('#form_graph_list').on('submit', function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	bottom_footer();

	break;
}

