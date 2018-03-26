<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

$guest_account = true;

include('./include/auth.php');
include_once('./lib/html_tree.php');
include_once('./lib/html_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/timespan_settings.php');

/* set the default graph action */
set_default_graph_action();

/* perform spikekill action */
html_spikekill_actions();

/* process tree settings */
process_tree_settings();

/* setup realtime defaults if they are not set */
initialize_realtime_step_and_window();

function get_matching_nodes() {
	$my_matches = array();
	$match = array();

	$filter = '%' . get_nfilter_request_var('str') . '%';

	if (get_nfilter_request_var('str') != '') {
		$matching = db_fetch_assoc_prepared("SELECT gti.parent, gti.graph_tree_id
			FROM graph_tree_items AS gti
			LEFT JOIN host AS h
			ON h.id=gti.host_id
			LEFT JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id=gti.local_graph_id AND gtg.local_graph_id>0
			WHERE gtg.title_cache LIKE ?
			OR h.description LIKE ?
			OR h.hostname LIKE ?
			OR gti.title LIKE ?",
			array($filter, $filter, $filter, $filter));
	} else {
		$matching = db_fetch_assoc("SELECT parent, graph_tree_id FROM graph_tree_items");
	}

	if (sizeof($matching)) {
		foreach($matching as $row) {
			while ($row['parent'] != '0') {
				$match[] = 'tbranch-' . $row['parent'];

				$row = db_fetch_row_prepared('SELECT parent, graph_tree_id
					FROM graph_tree_items
					WHERE id = ?',
					array($row['parent']));

				if (!sizeof($row)) {
					break;
				}
			}

			if (sizeof($row)) {
				$match[]      = 'tree_anchor-' . $row['graph_tree_id'];
				$my_matches[] = array_reverse($match);
				$match        = array();
			}
		}

		// Now flatten the list of nodes
		$final_array = array();
		$level = 0;
		while (true) {
			$found = 0;
			foreach($my_matches as $match) {
				if (isset($match[$level])) {
					if ($level == 0) {
						$final_array[$match[$level]][$match[$level]] = 1;
					} else {
						$final_array[$match[0]][$match[$level]] = 1;
					}
					$found++;
				}
			}
			$level++;

			if ($found == 0) {
				break;
			}
		}

		if (sizeof($final_array)) {
			$fa = array();

			foreach($final_array as $key => $matches) {
				foreach($matches as $branch => $dnc) {
					$fa[] = $branch;
				}
			}
		}

		header('Content-Type: application/json; charset=utf-8');

		print json_encode($fa);
	}
}

switch (get_nfilter_request_var('action')) {
case 'ajax_hosts':
	get_allowed_ajax_hosts();

	break;
case 'ajax_search':
	get_matching_nodes();
	exit;

	break;
case 'update_timespan':
	// we really don't need to do anything.  The session variables have already been updated

	break;
case 'save':
	if (is_view_allowed('graph_settings')) {
		get_filter_request_var('columns');
		get_filter_request_var('predefined_timespan');
		get_filter_request_var('predefined_timeshift');
		get_filter_request_var('graphs');
		get_filter_request_var('graph_template_id', FILTER_VALIDATE_IS_NUMERIC_LIST);
		get_filter_request_var('thumbnails', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '(true|false)')));

		if (isset_request_var('predefined_timespan')) {
			set_graph_config_option('default_timespan', get_request_var('predefined_timespan'));
		}

		if (isset_request_var('predefined_timeshift')) {
			set_graph_config_option('default_timeshift', get_request_var('predefined_timeshift'));
		}

		if (isset_request_var('graph_template_id')) {
			set_graph_config_option('graph_template_id', get_request_var('graph_template_id'));
		}

		if (isset_request_var('section') && get_nfilter_request_var('section') == 'preview') {
			if (isset_request_var('columns')) {
				set_graph_config_option('num_columns', get_request_var('columns'));
			}
			if (isset_request_var('graphs')) {
				set_graph_config_option('preview_graphs_per_page', get_request_var('graphs'));
			}
			if (isset_request_var('thumbnails')) {
				set_graph_config_option('thumbnail_section_preview', get_nfilter_request_var('thumbnails') == 'true' ? 'on':'');
			}
		} else {
			if (isset_request_var('columns')) {
				set_graph_config_option('num_columns_tree', get_request_var('columns'));
			}
			if (isset_request_var('graphs')) {
				set_graph_config_option('treeview_graphs_per_page', get_request_var('graphs'));
			}
			if (isset_request_var('thumbnails')) {
				set_graph_config_option('thumbnail_section_tree_2', get_request_var('thumbnails') == 'true' ? 'on':'');
			}
		}
	}

	break;
case 'tree':
	if (isset_request_var('tree_id')) {
		$_SESSION['sess_tree_id'] = get_filter_request_var('tree_id');
	}

	top_graph_header();

	bottom_footer();

	break;
case 'get_node':
	$parent  = -1;
	$tree_id = 0;

	if (isset_request_var('tree_id')) {
		if (get_nfilter_request_var('tree_id') == 0 && strstr(get_nfilter_request_var('id'), 'tbranch-') !== false) {
			$tree_id = db_fetch_cell_prepared('SELECT graph_tree_id
				FROM graph_tree_items
				WHERE id = ?',
				array(str_replace('tbranch-', '', get_nfilter_request_var('id'))));
		}else if (get_nfilter_request_var('tree_id') == 'default' ||
			get_nfilter_request_var('tree_id') == 'undefined' ||
			get_nfilter_request_var('tree_id') == '') {

			$tree_id = read_user_setting('default_tree_id');
		} elseif (get_nfilter_request_var('tree_id') == 0 &&
			substr_count(get_nfilter_request_var('id'), 'tree_anchor') > 0) {

			$ndata = explode('-', get_nfilter_request_var('id'));
			$tree_id = $ndata[1];
			input_validate_input_number($tree_id);
		}
	} else {
		$tree_id = read_user_setting('default_tree_id');
	}

	if (isset_request_var('id') && get_nfilter_request_var('id') != '#') {
		if (substr_count(get_nfilter_request_var('id'), 'tree_anchor')) {
			$parent = -1;
		} else {
			$ndata = explode('_', get_nfilter_request_var('id'));

			foreach($ndata as $node) {
				$pnode = explode('-', $node);

				if ($pnode[0] == 'tbranch') {
					$parent = $pnode[1];
					input_validate_input_number($parent);

					$tree_id = db_fetch_cell_prepared('SELECT graph_tree_id
						FROM graph_tree_items
						WHERE id = ?',
						array($parent));

					break;
				}
			}
		}
	}

	api_tree_get_main($tree_id, $parent);

	break;
case 'tree_content':
	html_validate_tree_vars();

	if (!is_view_allowed('show_tree')) {
		print "<font class='txtErrorTextBox'>" . __('YOU DO NOT HAVE RIGHTS FOR TREE VIEW') . '</font>';
		exit;
	}

	if (!isempty_request_var('node')) {
		$_SESSION['sess_graph_node'] = get_request_var('node');

		if (!isempty_request_var('hgd')) {
			$_SESSION['sess_graph_hgd'] = get_request_var('hgd');
		} else {
			$_SESSION['sess_graph_hgd'] = '';
		}
	} elseif (isset($_SESSION['sess_graph_node'])) {
		set_request_var('node', $_SESSION['sess_graph_node']);
		set_request_var('hgd', $_SESSION['sess_graph_hgd']);
	}

	?>
	<script type='text/javascript'>
	var refreshIsLogout=false;
	var refreshPage='<?php print str_replace('tree_content', 'tree', sanitize_uri($_SERVER['REQUEST_URI']));?>';
	var refreshMSeconds=<?php print read_user_setting('page_refresh')*1000;?>;
	var graph_start=<?php print get_current_graph_start();?>;
	var graph_end=<?php print get_current_graph_end();?>;
	var timeOffset=<?php print date('Z');?>

	// Adjust the height of the tree
	$(function() {
		myGraphLocation='tree';
		navHeight    = $('.cactiTreeNavigationArea').height();
		windowHeight = $(window).height();
		navOffset    = $('.cactiTreeNavigationArea').offset();
		if (navHeight + navOffset.top < windowHeight) {
			$('.cactiTreeNavigationArea').height(windowHeight - navOffset.top);
		}
	});
	</script>
	<?php

	$access_denied = false;
	$tree_parameters = array();
	$tree_id = 0;
	$node_id = 0;
	$hgdata  = 0;

	if (isset_request_var('node')) {
		$parts = explode('-', get_request_var('node'));

		// Check for tree anchoe
		if (strpos(get_request_var('node'), 'tree_anchor') !== false) {
			$tree_id = $parts[1];
			$node_id = 0;
		} elseif (strpos(get_request_var('node'), 'tbranch') !== false) {
			// Check for branch
			$node_id = $parts[1];
			$tree_id = db_fetch_cell_prepared('SELECT graph_tree_id
				FROM graph_tree_items
				WHERE id = ?',
				array($node_id));
		}
	}

	if (isset_request_var('hgd')) {
		$hgdata = get_request_var('hgd');
	}

	if ($tree_id > 0) {
		if (!is_tree_allowed($tree_id)) {
			header('Location: permission_denied.php');
			exit;
		}

		grow_right_pane_tree($tree_id, $node_id, $hgdata);
	}

	bottom_footer();

	break;
case 'preview':
	top_graph_header();

	if (!is_view_allowed('show_preview')) {
		print "<font class='txtErrorTextBox'>" . __('YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW') . "</font>";
		bottom_footer();
		exit;
	}

	html_graph_validate_preview_request_vars();

	/* include graph view filter selector */
	html_start_box(__('Graph Preview Filters') . (isset_request_var('style') && get_request_var('style') != '' ? ' ' . __('[ Custom Graph List Applied - Filtering from List ]'):''), '100%', '', '3', 'center', '');

	html_graph_preview_filter('graph_view.php', 'preview');

	html_end_box();

	/* the user select a bunch of graphs of the 'list' view and wants them displayed here */
	$sql_or = '';
	if (isset_request_var('style')) {
		if (get_request_var('style') == 'selective') {
			$graph_list = array();

			/* process selected graphs */
			if (!isempty_request_var('graph_list')) {
				foreach (explode(',', get_request_var('graph_list')) as $item) {
					if (is_numeric($item)) {
						$graph_list[$item] = 1;
					}
				}
			}
			if (!isempty_request_var('graph_add')) {
				foreach (explode(',', get_request_var('graph_add')) as $item) {
					if (is_numeric($item)) {
						$graph_list[$item] = 1;
					}
				}
			}
			/* remove items */
			if (!isempty_request_var('graph_remove')) {
				foreach (explode(',', get_request_var('graph_remove')) as $item) {
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
			}
		}
	}

	$total_graphs = 0;

	/* create filter for sql */
	$sql_where  = '';
	if (!isempty_request_var('rfilter')) {
		$sql_where .= " gtg.title_cache RLIKE '" . get_request_var('rfilter') . "'";
	}

	$sql_where .= ($sql_or != '' && $sql_where != '' ? ' AND ':'') . $sql_or;

	if (!isempty_request_var('host_id') && get_request_var('host_id') > 0) {
		$sql_where .= (empty($sql_where) ? '' : ' AND') . ' gl.host_id=' . get_request_var('host_id');
	} elseif (isempty_request_var('host_id')) {
		$sql_where .= (empty($sql_where) ? '' : ' AND') . ' gl.host_id=0';
	}

	if (!isempty_request_var('graph_template_id')) {
		$sql_where .= (empty($sql_where) ? '' : ' AND') . ' gl.graph_template_id IN (' . get_request_var('graph_template_id') . ')';
	}

	$limit = (get_request_var('graphs') * (get_request_var('page') - 1)) . ',' . get_request_var('graphs');
	$order = 'gtg.title_cache';

	$graphs = get_allowed_graphs($sql_where, $order, $limit, $total_graphs);

	$nav = html_nav_bar('graph_view.php', MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('graphs'), $total_graphs, get_request_var('columns'), __('Graphs'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	if (get_request_var('thumbnails') == 'true') {
		html_graph_thumbnail_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	} else {
		html_graph_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var('columns'));
	}

	html_end_box();

	if ($total_graphs) {
		print $nav;
	}

	if (!isset_request_var('header') || get_nfilter_request_var('header') == 'false') {
		bottom_footer();
	}

	break;
case 'list':
	top_graph_header();

	if (!is_view_allowed('show_list')) {
		print "<font class='txtErrorTextBox'>" . __('YOU DO NOT HAVE RIGHTS FOR LIST VIEW') . '</font>';
		bottom_footer();
		exit;
	}

	/* reset the graph list on a new viewing */
	if (!isset_request_var('page')) {
		set_request_var('graph_list', '');
	}

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
		'rfilter' => array(
			'filter' => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => '',
			),
		'graph_template_id' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'pageset' => true,
			'default' => '0'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'graph_add' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
			),
		'graph_list' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
			),
		'graph_remove' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
			)
	);

	validate_store_request_vars($filters, 'sess_gl');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$graph_list = array();

	/* save selected graphs into url */
	if (!isempty_request_var('graph_list')) {
		foreach (explode(',', get_request_var('graph_list')) as $item) {
			if (is_numeric($item)) {
				$graph_list[$item] = 1;
			}
		}
	}

	if (!isempty_request_var('graph_add')) {
		foreach (explode(',', get_request_var('graph_add')) as $item) {
			if (is_numeric($item)) {
				$graph_list[$item] = 1;
			}
		}
	}

	/* remove items */
	if (!isempty_request_var('graph_remove')) {
		foreach (explode(',', get_request_var('graph_remove')) as $item) {
			unset($graph_list[$item]);
		}
	}

	/* update the revised graph list session variable */
	if (sizeof($graph_list)) {
		set_request_var('graph_list', implode(',', array_keys($graph_list)));
	}
	load_current_session_value('graph_list', 'sess_gl_graph_list', '');

	form_start('graph_view.php', 'form_graph_list');

	/* display graph view filter selector */
	html_start_box(__('Graph List View Filters') . (isset_request_var('style') && get_request_var('style') != '' ? ' ' . __('[ Custom Graph List Applied - Filter FROM List ]'):''), '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='rfilter' type='text' size='30' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
					<?php html_host_filter(get_request_var('host_id'));?>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='graph_template_id' multiple style='opacity:0.1;overflow:hide;height:0px;'>
							<option value='0'<?php if (get_request_var('graph_template_id') == '0') {?> selected<?php }?>><?php print __('All Graphs & Templates');?></option>
							<?php

							$graph_templates = get_allowed_graph_templates();
							if (sizeof($graph_templates)) {
								$selected    = explode(',', get_request_var('graph_template_id'));
								foreach ($graph_templates as $gt) {
									if ($gt['id'] != 0) {
										$found = db_fetch_cell_prepared('SELECT id
											FROM graph_local
											WHERE graph_template_id = ? LIMIT 1',
											array($gt['id']));

										if ($found) {
											print "<option value='" . $gt['id'] . "'";
											if (sizeof($selected)) {
												if (in_array($gt['id'], $selected)) {
													print ' selected';
												}
											}
											print '>';
											print $gt['name'] . "</option>\n";
										}
									}
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Graphs');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>' onClick='clearFilter()'>
							<input type='button' value='<?php print __esc('View');?>' title='<?php print __esc('View Graphs');?>' onClick='viewGraphs()'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' id='style' value='selective'>
			<input type='hidden' id='action' value='list'>
			<input type='hidden' id='graph_add' value=''>
			<input type='hidden' id='graph_remove' value=''>
			<input type='hidden' id='graph_list' value='<?php print get_request_var('graph_list');?>'>
		</td>
	</tr>
	<?php
	html_end_box();

	/* create filter for sql */
	$sql_where  = '';
	if (!isempty_request_var('rfilter')) {
		$sql_where .= " gtg.title_cache RLIKE '" . get_request_var('rfilter') . "'";
	}

	if (!isempty_request_var('host_id') && get_request_var('host_id') > 0) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' gl.host_id=' . get_request_var('host_id');
	} elseif (isempty_request_var('host_id')) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' gl.host_id=0';
	}

	if (!isempty_request_var('graph_template_id')) {
		$sql_where .= ($sql_where == '' ? '' : ' AND') . ' gl.graph_template_id IN (' . get_request_var('graph_template_id') . ')';
	}

	$total_rows = 0;
	$limit      = ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$graphs = get_allowed_graphs($sql_where, 'gtg.title_cache', $limit, $total_rows);

	$nav = html_nav_bar('graph_view.php?action=list', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Graphs'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_checkbox(array(__('Graph Title'), __('Device'), __('Graph Template'), __('Graph Size')), false);

	$i = 0;
	if (sizeof($graphs)) {
		foreach ($graphs as $graph) {
			if ($graph['description'] == '' && $graph['template_name'] == '') {
				$aggregate = db_fetch_cell_prepared('SELECT agt.name
					FROM aggregate_graphs AS ag
					INNER JOIN aggregate_graph_templates AS agt
					ON ag.aggregate_template_id=agt.id
					WHERE local_graph_id = ?',
					array($graph['local_graph_id']));

				if (!empty($aggregate)) {
					$graph['description']   = __('Aggregated Device');
					$graph['template_name'] = $aggregate;
				} else {
					$graph['description']   = __('Non-Device');
					$graph['template_name'] = __('Not Applicable');
				}
			}

			form_alternate_row('line' . $graph['local_graph_id'], true);
			form_selectable_cell(filter_value($graph['title_cache'], get_request_var('rfilter'), 'graph.php?local_graph_id=' . $graph['local_graph_id'] . '&rra_id=0'), $graph['local_graph_id']);
			form_selectable_cell($graph['description'], $graph['local_graph_id']);
			form_selectable_cell($graph['template_name'], $graph['local_graph_id']);
			form_selectable_cell($graph['height'] . 'x' . $graph['width'], $graph['local_graph_id']);
			form_checkbox_cell($graph['title_cache'], $graph['local_graph_id']);
			form_end_row();
		}
	}

	?>
	<tr>
		<td style='text-align:right' colspan='3'><img src='images/arrow.gif' alt=''>&nbsp;</td>
		<td style='text-align:right' colspan='2'><input type='button' value='<?php print __esc('View');?>' title='<?php print __esc('View Graphs');?>' onClick='viewGraphs()'></td>
	</tr>
	<?php

	html_end_box(false);

	if (sizeof($graphs)) {
		print $nav;
	}

	form_end();

	?>
	<script type='text/javascript'>
	var refreshMSeconds=999999999;
	var graph_list_array = new Array(<?php print get_request_var('graph_list');?>);

	function clearFilter() {
		strURL = 'graph_view.php?action=list&header=false&clear=1';
		loadPageNoHeader(strURL);
	}

	function applyFilter() {
		strURL = 'graph_view.php?action=list&header=false&page=1';
		strURL += '&host_id=' + $('#host_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&graph_template_id=' + $('#graph_template_id').val();
		strURL += '&rfilter=' + base64_encode($('#rfilter').val());
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

		strURL = urlPath+'graph_view.php?action=preview';
		$('#form_graph_list').find('select, input').each(function() {
			switch($(this).attr('id')) {
			case 'graph_template_id':
				strURL += '&' + $(this).attr('id') + '=' + $(this).val();
				break;
			case 'host_id':
			case 'rfilter':
			case 'graph_add':
			case 'graph_remove':
			case 'graph_list':
			case 'style':
			case 'csrf_magic':
				strURL += '&' + $(this).attr('id') + '=' + $(this).val();
				break;
			default:
				break;
			}
		});

		strURL += '&reset=true&header=false';

		loadPageNoHeader(strURL);

		$('#breadcrumbs').empty().html('<li><a href="graph_view.php?action=preview"><?php print __('Preview Mode');?></a></li>');
		$('#listview').removeClass('selected');
		$('#preview').addClass('selected');
	}

	function url_graph(strNavURL) {
		if ($('#action').val() == 'list') {
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

			strURL = '&demon=1&graph_list=<?php print get_request_var('graph_list');?>&graph_add=' + strAdd + '&graph_remove=' + strDel;

			return strNavURL + strURL;
		} else {
			return strNavURL;
		}
	}

	function graphChecked(graph_id) {
		for(var i = 0; i < graph_list_array.length; i++) {
			if (graph_list_array[i] == graph_id) {
				return true;
			}
		}

		return false;
	}

	$(function() {
		myGraphLocation='list';

		initializeChecks();

		var msWidth = 100;
		$('#graph_template_id option').each(function() {
			if ($(this).textWidth() > msWidth) {
				msWidth = $(this).textWidth();
			}
			$('#graph_template_id').css('width', msWidth+120+'px');
		});

		$('#graph_template_id').hide().multiselect({
			height: 300,
			noneSelectedText: '<?php print __('All Graphs & Templates');?>',
			selectedText: function(numChecked, numTotal, checkedItems) {
				myReturn = numChecked + ' <?php print __('Templates Selected');?>';
				$.each(checkedItems, function(index, value) {
					if (value.value == '0') {
						myReturn='<?php print __('All Graphs & Templates');?>';
						return false;
					}
				});
				return myReturn;
			},
			checkAllText: '<?php print __('All');?>',
			uncheckAllText: '<?php print __('None');?>',
			uncheckall: function() {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).prop('checked', true);
				});
			},
			open: function(event, ui) {
				$("input[type='search']:first").focus();
			},
			close: function(event, ui) {
				applyFilter();
			},
			click: function(event, ui) {
				checked=$(this).multiselect('widget').find('input:checked').length;

				if (ui.value == 0) {
					if (ui.checked == true) {
						$('#graph_template_id').multiselect('uncheckAll');
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).prop('checked', true);
						});
					}
				}else if (checked == 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
					});
				}else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
					if (checked > 0) {
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).click();
							$(this).prop('disable', true);
						});
					}
				}
			}
		}).multiselectfilter({
			label: '<?php print __('Search');?>',
			width: msWidth
		});

		$('#form_graph_list').unbind().on('submit', function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	bottom_footer();

	break;
}

