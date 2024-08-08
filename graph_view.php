<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
include_once('./lib/graphs.php');
include_once('./lib/reports.php');
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
			ON h.id = gti.host_id
			LEFT JOIN (
				SELECT DISTINCT site_id
				FROM host
				WHERE description LIKE ?
				OR hostname LIKE ?
			) AS h2
			ON h2.site_id = gti.site_id
			LEFT JOIN (
				SELECT local_graph_id
				FROM graph_templates_graph
				WHERE local_graph_id > 0
				AND title_cache LIKE ?
			) AS gtg
			ON gtg.local_graph_id = gti.local_graph_id
			LEFT JOIN (
				SELECT id
				FROM sites
				WHERE name LIKE ?
			) AS site
			ON site.id = gti.site_id
			WHERE (gti.title LIKE ?)
			OR (h.description LIKE ? AND (gti.host_id > 0 OR gti.site_id > 0))
			OR (h.hostname LIKE ? AND (gti.host_id > 0 OR gti.site_id > 0))
			OR (h2.site_id > 0)
			OR (gtg.local_graph_id > 0)
			OR (site.id > 0)",
			array($filter, $filter, $filter, $filter, $filter, $filter, $filter));
	} else {
		$matching = db_fetch_assoc("SELECT parent, graph_tree_id FROM graph_tree_items");
	}

	if (cacti_sizeof($matching)) {
		foreach($matching as $row) {
			while ($row['parent'] != '0') {
				$match[] = 'tbranch-' . $row['parent'];

				$row = db_fetch_row_prepared('SELECT parent, graph_tree_id
					FROM graph_tree_items
					WHERE id = ?',
					array($row['parent']));

				if (!cacti_sizeof($row)) {
				    break;
				}
			}

			if (cacti_sizeof($row)) {
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

		$fa = array();

		if (cacti_sizeof($final_array)) {
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
case 'ajax_reports':
	// Add to a report
	get_filter_request_var('report_id');
	get_filter_request_var('timespan');
	get_filter_request_var('align');

	if (isset_request_var('graph_list')) {
		$items = explode(',', get_request_var('graph_list'));

		if (cacti_sizeof($items)) {
			$good = true;

			foreach($items as $item) {
				if (!reports_add_graphs(get_filter_request_var('report_id'), $item, get_request_var('timespan'), get_request_var('align'))) {
					raise_message('reports_add_error');
					$good = false;
					break;
				}
			}

			if ($good) {
				raise_message('reports_graphs_added');
			}
		}
	} else {
		raise_message('reports_no_graph');
	}

	header('Location: graph_view.php?action=list&header=false');

	break;
case 'update_timespan':
	$_SESSION['sess_current_date1'] = get_request_var('date1');
	$_SESSION['sess_current_date2'] = get_request_var('date2');

	break;
case 'save':
	if (is_view_allowed('graph_settings')) {
		get_filter_request_var('columns');
		get_filter_request_var('predefined_timespan');
		get_filter_request_var('predefined_timeshift');
		get_filter_request_var('graphs');
		get_filter_request_var('thumbnails', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '(true|false)')));

		if (isset_request_var('predefined_timespan')) {
			set_graph_config_option('default_timespan', get_request_var('predefined_timespan'));
		}

		if (isset_request_var('predefined_timeshift')) {
			set_graph_config_option('default_timeshift', get_request_var('predefined_timeshift'));
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
	html_validate_tree_vars();

	if (isset_request_var('tree_id')) {
		$_SESSION['sess_tree_id'] = get_filter_request_var('tree_id');
	}

	top_graph_header();

	?>
	<script type='text/javascript'>
	minTreeWidth = <?php print read_user_setting('min_tree_width');?>;
	maxTreeWidth = <?php print read_user_setting('max_tree_width');?>;
	</script>
	<?php

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

	top_graph_header();

	if (!is_view_allowed('show_tree')) {
		print "<font class='txtErrorTextBox'>" . __('YOU DO NOT HAVE RIGHTS FOR TREE VIEW') . '</font>';
		exit;
	}

	if (!isempty_request_var('node')) {
		$_SESSION['sess_graph_node'] = sanitize_search_string(get_nfilter_request_var('node'));

		if (!isempty_request_var('hgd')) {
			$_SESSION['sess_graph_hgd'] = sanitize_search_string(get_nfilter_request_var('hgd'));
		} else {
			$_SESSION['sess_graph_hgd'] = '';
		}
	} elseif (isset($_SESSION['sess_graph_node'])) {
		set_request_var('node', $_SESSION['sess_graph_node']);
		set_request_var('hgd', $_SESSION['sess_graph_hgd']);
	}

	?>
	<script type='text/javascript'>
	var refreshIsLogout = false;
	var refreshPage     = '<?php print str_replace('tree_content', 'tree', sanitize_uri($_SERVER['REQUEST_URI']));?>';
	var refreshMSeconds = <?php print read_user_setting('page_refresh')*1000;?>;
	var graph_start     = <?php print get_current_graph_start();?>;
	var graph_end       = <?php print get_current_graph_end();?>;
	var timeOffset      = <?php print date('Z');?>

	// Adjust the height of the tree
	$(function() {
		pageAction   = 'tree';
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
		$parts = explode('-', sanitize_search_string(get_request_var('node')));

		// Check for tree anchor
		if (strpos(get_nfilter_request_var('node'), 'tree_anchor') !== false) {
			$tree_id = $parts[1];
			$node_id = 0;
		} elseif (strpos(get_nfilter_request_var('node'), 'tbranch') !== false) {
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
	if (!is_view_allowed('show_preview')) {
		print "<font class='txtErrorTextBox'>" . __('YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW') . "</font>";
		bottom_footer();
		exit;
	}

	if (isset_request_var('external_id')) {
		$host_id = db_fetch_cell_prepared('SELECT id FROM host WHERE external_id = ?', array(get_nfilter_request_var('external_id')));
		if (!empty($host_id)) {
			set_request_var('host_id', $host_id);
			set_request_var('reset',true);
		}
	}

	html_graph_validate_preview_request_vars();

	top_graph_header();

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

			if ((isset($graph_array)) && (cacti_sizeof($graph_array) > 0)) {
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

	if (!isempty_request_var('graph_template_id') && get_request_var('graph_template_id') != '-1' && get_request_var('graph_template_id') != '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . get_request_var('graph_template_id') . '))';
	} elseif (get_request_var('graph_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . get_request_var('graph_template_id') . '))';
	}

	$limit      = (get_request_var('graphs')*(get_request_var('page')-1)) . ',' . get_request_var('graphs');
	$order      = 'gtg.title_cache';

	$graphs     = get_allowed_graphs($sql_where, $order, $limit, $total_graphs);

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
	global $graph_timespans, $alignment, $graph_sources;

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
			'default' => '-1'
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
	if (cacti_sizeof($graph_list)) {
		set_request_var('graph_list', implode(',', array_keys($graph_list)));
	}
	load_current_session_value('graph_list', 'sess_gl_graph_list', '');

	$reports = db_fetch_assoc_prepared('SELECT *
		FROM reports
		WHERE user_id = ?',
		array($_SESSION['sess_user_id']));

	top_graph_header();

	form_start('graph_view.php', 'chk');

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
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='55' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
					<?php html_host_filter(get_request_var('host_id'));?>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>' onClick='clearFilter()'>
							<input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('View');?>' title='<?php print __esc('View Graphs');?>' onClick='viewGraphs()'>
							<?php if (cacti_sizeof($reports)) {?>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='addreport' value='<?php print __esc('Report');?>' title='<?php print __esc('Add to a Report');?>'>
							<?php } ?>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='graph_template_id' multiple style='opacity:0.1;overflow:hide;height:0px;'>
							<option value='-1'<?php if (get_request_var('graph_template_id') == '-1') {?> selected<?php }?>><?php print __('All Graphs & Templates');?></option>
							<option value='0'<?php if (get_request_var('graph_template_id') == '0') {?> selected<?php }?>><?php print __('Not Templated');?></option>
							<?php

							// suppress total rows collection
							$total_rows = -1;

							$graph_templates = get_allowed_graph_templates('', 'name', '', $total_rows);
							if (cacti_sizeof($graph_templates)) {
								$selected    = explode(',', get_request_var('graph_template_id'));
								foreach ($graph_templates as $gt) {
									if ($gt['id'] != 0) {
										$found = db_fetch_cell_prepared('SELECT id
											FROM graph_local
											WHERE graph_template_id = ? LIMIT 1',
											array($gt['id']));

										if ($found) {
											print "<option value='" . $gt['id'] . "'";
											if (cacti_sizeof($selected)) {
												if (in_array($gt['id'], $selected)) {
													print ' selected';
												}
											}
											print '>';
											print html_escape($gt['name']) . "</option>\n";
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
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
							}
							?>
						</select>
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

	if (!isempty_request_var('graph_template_id') && get_request_var('graph_template_id') != '-1' && get_request_var('graph_template_id') != '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . get_request_var('graph_template_id') . '))';
	} elseif (get_request_var('graph_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . ' (gl.graph_template_id IN (' . get_request_var('graph_template_id') . '))';
	}

	$total_rows = 0;
	$limit      = ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$graphs = get_allowed_graphs($sql_where, 'gtg.title_cache', $limit, $total_rows);

	$nav = html_nav_bar('graph_view.php?action=list', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Graphs'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	if (is_realm_allowed(10)) {
		$display_text = array(
			'title_cache' => array(
				'display' => __('Graph Name'),
				'align'   => 'left',
				'tip'     => __('The Title of this Graph.  Generally programmatically generated from the Graph Template definition or Suggested Naming rules.  The max length of the Title is controlled under Settings->Visual.')
			),
			'local_graph_id' => array(
				'display' => __('Device'),
				'align'   => 'left',
				'tip'     => __('The device for this Graph.')
			),
			'source' => array(
				'display' => __('Source Type'),
				'align'   => 'right',
				'tip'     => __('The underlying source that this Graph was based upon.')
			),
			'name' => array(
				'display' => __('Source Name'),
				'align'   => 'left',
				'tip'     => __('The Graph Template or Data Query that this Graph was based upon.')
			),
			'height' => array(
				'display' => __('Size'),
				'align'   => 'left',
				'tip'     => __('The size of this Graph when not in Preview mode.')
			)
		);
	} else {
		$display_text = array(
			'title_cache' => array(
				'display' => __('Graph Name'),
				'align'   => 'left',
				'tip'     => __('The Title of this Graph.  Generally programmatically generated from the Graph Template definition or Suggested Naming rules.  The max length of the Title is controlled under Settings->Visual.')
			),
			'height' => array(
				'display' => __('Size'),
				'align'   => 'left',
				'tip'     => __('The size of this Graph when not in Preview mode.')
			)
		);
	}

	html_header_checkbox($display_text, false);

	$i = 0;
	if (cacti_sizeof($graphs)) {
		foreach ($graphs as $graph) {
			/* we're escaping strings here, so no need to escape them on form_selectable_cell */
			$template_details = get_graph_template_details($graph['local_graph_id']);

			if($graph['graph_source'] == '0') { //Not Templated, customize graph source and template details.
				$template_details = api_plugin_hook_function('customize_template_details', $template_details);
				$graph = api_plugin_hook_function('customize_graph', $graph);
			}

			if (isset($template_details['graph_name'])) {
				$graph['name'] = $template_details['graph_name'];
			}

			if (isset($template_details['graph_description'])) {
				$graph['description'] = $template_details['graph_description'];
			}

			form_alternate_row('line' . $graph['local_graph_id'], true);
			form_selectable_cell(filter_value($graph['title_cache'], get_request_var('rfilter'), 'graph.php?local_graph_id=' . $graph['local_graph_id'] . '&rra_id=0'), $graph['local_graph_id']);
			if (is_realm_allowed(10)) {
				form_selectable_ecell($graph['description'], $graph['local_graph_id']);
				form_selectable_cell(filter_value($graph_sources[$template_details['source']], get_request_var('rfilter')), $graph['local_graph_id'], '', 'right');
				form_selectable_cell(filter_value($template_details['name'], get_request_var('rfilter'), $template_details['url']), $graph['local_graph_id'], '', 'left');
			}
			form_selectable_ecell($graph['height'] . 'x' . $graph['width'], $graph['local_graph_id']);
			form_checkbox_cell($graph['title_cache'], $graph['local_graph_id']);
			form_end_row();
		}
	}

	html_end_box(false);

	if (cacti_sizeof($graphs)) {
		print $nav;
	}

	form_end();

	$report_text = '';

	if (cacti_sizeof($reports)) {
		$report_text = '<div id="addGraphs" style="display:none;">
			<p>' . __('Select the Report to add the selected Graphs to.') . '</p>
			<table class="cactiTable">';

		$report_text .= '<tr><td>' . __('Report Name') . '</td>';
		$report_text .= '<td><select id="report_id">';
		foreach($reports as $report) {
			$report_text .= '<option value="' . $report['id'] . '">' . html_escape($report['name']) . '</option>';
		}
		$report_text .= '</select></td></tr>';

		$report_text .= '<tr><td>' . __('Timespan') . '</td>';
		$report_text .= '<td><select id="timespan">';
		foreach($graph_timespans as $key => $value) {
			$report_text .= '<option value="' . $key . '"' . ($key == read_user_setting('default_timespan') ? ' selected':'') . '>' . $value . '</option>';
		}
		$report_text .= '</select></td></tr>';

		$report_text .= '<tr><td>' . __('Align') . '</td>';
		$report_text .= '<td><select id="align">';
		foreach($alignment as $key => $value) {
			$report_text .= '<option value="' . $key . '"' . ($key == REPORTS_ALIGN_CENTER ? ' selected':'') . '>' . $value . '</option>';
		}
		$report_text .= '</select></td></tr>';

		$report_text .= '</table></div>';
	}

	?>
	<div class='break'></div>
	<div class='cactiTable'>
		<div style='float:left'><img src='images/arrow.gif' alt=''>&nbsp;</div>
		<div style='float:right'><input type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('View');?>' title='<?php print __esc('View Graphs');?>' onClick='viewGraphs()'></div>
	</div>
	<?php print $report_text;?>
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
		$('#chk').find('select, input').each(function() {
			switch($(this).attr('id')) {
				case 'rfilter':
					strURL += '&' + $(this).attr('id') + '=' + base64_encode($(this).val());
					break;
				case 'graph_template_id':
				case 'host_id':
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

	function addReport() {
		$('#addGraphs').dialog({
			title: '<?php print __('Add Selected Graphs to Report');?>',
			minHeight: 80,
			minWidth: 400,
			modal: true,
			resizable: false,
			draggable: false,
			buttons: [
				{
					text: '<?php print __('Cancel');?>',
					click: function() {
						$(this).dialog('close');
					}
				},
				{
					text: '<?php print __('Ok');?>',
					click: function() {
						graphList = $('#graph_list').val();
						$('input[id^=chk_]').each(function(data) {
							graphID = $(this).attr('id').replace('chk_','');
							if ($(this).is(':checked')) {
								graphList += (graphList.length > 0 ? ',':'') + graphID;
							}
						});
						$('#graph_list').val(graphList);

						$(this).dialog('close');

						strURL = 'graph_view.php?action=ajax_reports' +
							'&header=false' +
							'&report_id='   + $('#report_id').val()   +
							'&timespan='    + $('#timespan').val()    +
							'&align='       + $('#align').val()       +
							'&graph_list='  + $('#graph_list').val();

						loadPageUsingPost(strURL);
					}
				}
			],
			open: function() {
				$('.ui-dialog').css('z-index', 99);
				$('.ui-widget-overlay').css('z-index', 98);
			},
			close: function() {
				$('[title]').each(function() {
					if ($(this).tooltip('instance')) {
						$(this).tooltip('close');
					}
				});
			}
		});
	}

	$(function() {
		pageAction = 'list';

		initializeChecks();

		$('#addreport').click(function() {
			addReport();
		});

		<?php html_graph_template_multiselect('list');?>

		$('#chk').unbind().on('submit', function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	bottom_footer();

	break;
}

