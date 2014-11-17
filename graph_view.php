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

$guest_account = true;
include('./include/auth.php');
include_once('./lib/html_tree.php');
include_once('./lib/timespan_settings.php');

define("MAX_DISPLAY_PAGES", 21);

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request('branch_id'));
input_validate_input_number(get_request_var_request('hide'));
input_validate_input_number(get_request_var_request('tree_id'));
input_validate_input_number(get_request_var_request('leaf_id'));
input_validate_input_number(get_request_var_request('rra_id'));
input_validate_input_regex(get_request_var_request('graph_list'), '^([\,0-9]+)$');
input_validate_input_regex(get_request_var_request('graph_add'), '^([\,0-9]+)$');
input_validate_input_regex(get_request_var_request('graph_remove'), '^([\,0-9]+)$');
input_validate_input_regex(get_request_var_request('nodeid'), '^([_a-z0-9]+)$');
/* ==================================================== */

/* clean up action string */
if (isset($_REQUEST['action'])) {
	$_REQUEST['action'] = sanitize_search_string(get_request_var_request('action'));
}

/* setup tree selection defaults if the user has not been here before */
if (!isset($_REQUEST['action'])) {
	if (!isset($_SESSION['sess_graph_view_action'])) {
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

if ($_REQUEST['action'] != 'tree_content') {
	$_SESSION['sess_graph_view_action'] = $_REQUEST['action'];
}

if (read_config_option('auth_method') != 0) {
	$current_user = db_fetch_row('select * from user_auth where id=' . $_SESSION['sess_user_id']);
}

if (isset($_REQUEST['hide'])) {
	if (($_REQUEST['hide'] == '0') || ($_REQUEST['hide'] == '1')) {
		/* only update expand/contract info is this user has rights to keep their own settings */
		if ((isset($current_user)) && ($current_user['graph_settings'] == 'on')) {
			db_execute('delete from settings_tree where graph_tree_item_id=' . $_REQUEST['branch_id'] . ' and user_id=' . $_SESSION['sess_user_id']);
			db_execute('insert into settings_tree (graph_tree_item_id,user_id,status) values (' . $_REQUEST['branch_id'] . ',' . $_SESSION['sess_user_id'] . ',' . $_REQUEST['hide'] . ')');
		}
	}
}

switch ($_REQUEST['action']) {
case 'tree':
	top_graph_header();

	validate_tree_vars();

	if ((read_config_option('auth_method') != 0) && (empty($current_user['show_tree']))) {
		print "<strong><font class='txtErrorTextBox'>YOU DO NOT HAVE RIGHTS FOR TREE VIEW</font></strong>"; exit;
	}

	if ((!isset($_REQUEST['tree_id'])) && (isset($_SESSION['dhtml_tree']))) {
		unset($_SESSION['dhtml_tree']);
	}

	$tree_dropdown_html = draw_tree_dropdown((isset($_REQUEST['tree_id']) ? $_REQUEST['tree_id'] : '0'));

	/* don't even print the table if there is not >1 tree */
	if ((!empty($tree_dropdown_html)) && (read_graph_config_option('default_tree_view_mode') == '1')) {
		print "
		<table width='100%' class='tableConfirm' align='center' cellpadding='3'>
			<tr>
				$tree_dropdown_html
			</tr>
		</table>\n";
	}

	if (read_graph_config_option('default_tree_view_mode') == '1') {
		$tree_parameters = array();
		grow_graph_tree($_SESSION['sess_view_tree_id'], (!empty($start_branch) ? $start_branch : 0), isset($_SESSION['sess_user_id']) ? $_SESSION['sess_user_id'] : 0, $tree_parameters);
	}

	bottom_footer();

	break;
case 'tree_content':
	validate_tree_vars();

	if ((read_config_option('auth_method') != 0) && (empty($current_user['show_tree']))) {
		print "<strong><font class='txtErrorTextBox'>YOU DO NOT HAVE RIGHTS FOR TREE VIEW</font></strong>"; exit;
	}

	?>
		<script type="text/javascript" >
		$(document).ready(function() {
			$(".graphimage").zoom({inputfieldStartTime : 'date1', inputfieldEndTime : 'date2', serverTimeOffset : <?php print date('Z');?>});
		});
	</script>
	<?php

	$access_denied = false;
	$tree_parameters = array();
	$_SESSION['sess_node_id'] = $_REQUEST['nodeid'];

	/* if cacti's builtin authentication is turned on then make sure to take
	graph permissions into account here. if a user does not have rights to a
	particular graph; do not show it. they will get an access denied message
	if they try and view the graph directly. */

	if (isset($_REQUEST['tree_id'])) {
		if (read_config_option('auth_method') != 0) {
			/* take tree permissions into account here, if the user does not have permission
			give an "access denied" message */
			$access_denied = !(is_tree_allowed($_REQUEST['tree_id']));

			if ($access_denied == true) {
				print "<strong><font class='txtErrorTextBox'>ACCESS DENIED</font></strong>"; exit;
			}
		}

		grow_right_pane_tree((isset($_REQUEST['tree_id']) ? $_REQUEST['tree_id'] : 0), (isset($_REQUEST['leaf_id']) ? $_REQUEST['leaf_id'] : 0), (isset($_REQUEST['host_group_data']) ? urldecode($_REQUEST['host_group_data']) : 0));
	}

	break;
case 'preview':
	top_graph_header();

	if ((read_config_option('auth_method') != 0) && (empty($current_user['show_preview']))) {
		print "<strong><font class='txtErrorTextBox'>YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW</font></strong>"; exit;
	}

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('host_id'));
	input_validate_input_number(get_request_var_request('graph_template_id'));
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('columns'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up search string */
	if (isset($_REQUEST['thumbnails'])) {
		$_REQUEST['thumbnails'] = sanitize_search_string(get_request_var_request('thumbnails'));
	}

	/* reset the graph list on a new viewing */
	if (!isset($_REQUEST['page'])) {
		$_REQUEST['page'] = 1;
	}

	/* reset the page counter to '1' if a search in initiated */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['page'] = '1';
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x']) || isset($_REQUEST['style'])) {
		kill_session_var('sess_graph_view_current_page');
		kill_session_var('sess_graph_view_filter');
		kill_session_var('sess_graph_view_graph_template');
		kill_session_var('sess_graph_view_host');
		kill_session_var('sess_graph_view_rows');
		kill_session_var('sess_graph_view_columns');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['host_id']);
		unset($_REQUEST['graph_template_id']);

		if (isset($_REQUEST['clear_x'])) {
			unset($_REQUEST['filter']);
			unset($_REQUEST['graph_list']);
			unset($_REQUEST['graph_add']);
			unset($_REQUEST['graph_remove']);
			unset($_REQUEST['columns']);
			unset($_REQUEST['thumbnails']);
			unset($_REQUEST['style']);
			kill_session_var('sess_graph_view_graph_list');
			kill_session_var('sess_graph_view_graph_add');
			kill_session_var('sess_graph_view_graph_remove');
			kill_session_var('sess_graph_view_thumbnails');
			kill_session_var('sess_graph_view_style');
		}
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = false;
		$changed += check_changed('host_id', 'sess_graph_view_host');
		$changed += check_changed('rows', 'sess_graph_view_rows');
		$changed += check_changed('graph_template_id', 'sess_graph_view_graph_template');
		$changed += check_changed('filter', 'sess_graph_view_filter');
		$changed += check_changed('columns', 'sess_graph_view_columns');
		if ($changed) $_REQUEST['page'] = 1;
	}

	load_current_session_value('host_id', 'sess_graph_view_host', '0');
	load_current_session_value('graph_template_id', 'sess_graph_view_graph_template', '0');
	load_current_session_value('filter', 'sess_graph_view_filter', '');
	load_current_session_value('style', 'sess_graph_view_style', '');
	load_current_session_value('page', 'sess_graph_view_current_page', '1');
	load_current_session_value('rows', 'sess_graph_view_rows', '-1');
	load_current_session_value('columns', 'sess_graph_view_columns', '-1');
	load_current_session_value('thumbnails', 'sess_graph_view_thumbnails', read_graph_config_option('thumbnail_section_preview') == 'on' ? 'true':'false');
	load_current_session_value('graph_list', 'sess_graph_view_graph_list', '');
	load_current_session_value('graph_add', 'sess_graph_view_graph_add', '');
	load_current_session_value('graph_remove', 'sess_graph_view_graph_remove', '');

	/* include graph view filter selector */
	html_start_box('<strong>Graph Filters</strong>' . (isset($_REQUEST['style']) && strlen($_REQUEST['style']) ? ' [ Custom Graph List Applied - Filtering from List ]':''), '100%', "", '3', 'center', '');

	?>
	<script type="text/javascript" >
		$(document).ready(function() {
			$(".graphimage").zoom({inputfieldStartTime : 'date1', inputfieldEndTime : 'date2', serverTimeOffset : <?php print date('Z');?>});
		});
	</script>
	<tr class='even noprint'>
		<td class='noprint'>
		<form style='margin:0px;padding:0px;' name='form_graph_view' method='post' action='graph_view.php?action=preview' onSubmit='applyGraphPreviewFilterChange();return false;'>
			<table cellpadding='2' cellspacing='0'>
				<tr class='noprint'>
					<td width='55'>
						Host:
					</td>
					<td width='1'>
						<select id='host_id' name='host_id' onChange='applyGraphPreviewFilterChange();return false;'>
							<option value='0'<?php if (get_request_var_request('host_id') == '0') {?> selected<?php }?>>Any</option>

							<?php
							if (read_config_option('auth_method') != 0) {
								/* get policy information for the sql where clause */
								$sql_where = get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

								$hosts = db_fetch_assoc('SELECT DISTINCT host.id, host.description as name
									FROM host
									LEFT JOIN graph_local ON ( host.id = graph_local.host_id )
									LEFT JOIN graph_templates_graph ON ( graph_templates_graph.local_graph_id = graph_local.id )
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . '))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									' . (empty($sql_where) ? '' : "and $sql_where") . '
									ORDER BY name');
							}else{
								$hosts = db_fetch_assoc('SELECT DISTINCT host.id, host.description as name
									FROM host
									ORDER BY name');
							}

							if (sizeof($hosts) > 0) {
								foreach ($hosts as $host) {
									print "<option value='" . $host['id'] . "'"; if (get_request_var_request('host_id') == $host['id']) { print ' selected'; } print '>' . htmlspecialchars($host['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						Template:
					</td>
					<td width='1'>
						<select id='graph_template_id' name='graph_template_id' onChange='applyGraphPreviewFilterChange();return false;'>
							<option value='0'<?php if (get_request_var_request('graph_template_id') == '0') {?> selected<?php }?>>Any</option>

							<?php
							if (read_config_option('auth_method') != 0) {
								$graph_templates = db_fetch_assoc('SELECT DISTINCT graph_templates.*
									FROM host
									LEFT JOIN graph_local ON ( host.id = graph_local.host_id )
									LEFT JOIN graph_templates_graph ON ( graph_templates_graph.local_graph_id = graph_local.id )
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . '))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									' . (empty($sql_where) ? '' : "and $sql_where") . '
									ORDER BY name');
							}else{
								$graph_templates = db_fetch_assoc('SELECT DISTINCT graph_templates.*
									FROM graph_templates
									ORDER BY name');
							}

							if (sizeof($graph_templates) > 0) {
								foreach ($graph_templates as $template) {
									print "<option value='" . $template['id'] . "'"; if (get_request_var_request('graph_template_id') == $template['id']) { print ' selected'; } print '>' . htmlspecialchars($template['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						Graphs:
					</td>
					<td width='1'>
						<select id='rows' name='rows' onChange='applyGraphPreviewFilterChange();return false;'>
							<option value='-1'<?php if (get_request_var_request('rows') == '-1') {?> selected<?php }?>>Default</option>
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
						Columns:
					</td>
					<td width='1'>
						<select id='columns' name='columns' onChange='applyGraphPreviewFilterChange();return false;'>
							<option value='-1'<?php if (get_request_var_request('columns') == '-1') {?> selected<?php }?>>Default</option>
							<option value='1'<?php if (get_request_var_request('columns') == '1') {?> selected<?php }?>>1 Column</option>
							<option value='2'<?php if (get_request_var_request('columns') == '2') {?> selected<?php }?>>2 Columns</option>
							<option value='3'<?php if (get_request_var_request('columns') == '3') {?> selected<?php }?>>3 Columns</option>
							<option value='4'<?php if (get_request_var_request('columns') == '4') {?> selected<?php }?>>4 Columns</option>
							<option value='5'<?php if (get_request_var_request('columns') == '5') {?> selected<?php }?>>5 Columns</option>
						</select>
					</td>
					<td width='40'>
						<label for='thumbnails'>Thumbnails:</label>
					</td>
					<td>
						<input id='thumbnails' type='checkbox' name='thumbnails' onClick='applyGraphPreviewFilterChange();return false;' <?php print (($_REQUEST['thumbnails'] == 'true') ? 'checked':'');?>>
					</td>
					<td>
						Search:
					</td>
					<td width='1'>
						<input type='text' name='filter' size='40' value='<?php print htmlspecialchars(get_request_var_request('filter'));?>'>
					</td>
					<td>
						<input type='button' value='Go' title='Set/Refresh Filters' onClick='applyGraphPreviewFilterChange();return false;'>
					</td>
					<td>
						<input type='button' name='clear_x' value='Clear' title='Clear Filters' onClick='clearFilter()'>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<script type='text/javascript'>
	function clearFilter() {
		$.get('graph_view.php?action=preview&header=false&clear_x=1', function(data) {
			$('#main').html(data);
		});
	}

	function applyGraphPreviewFilterChange() {
		$.get('graph_view.php?action=preview&header=false'+
			'&flter='+$('#filter').val()+'&host_id='+$('#host_id').val()+'&columns='+$('#columns').val()+
			'&rows='+$('#rows').val()+'&graph_template_id='+$('#graph_template_id').val()+
			'&thumbnails='+$('#thumbnails').is(':checked'), function(data) {
			$('#main').html(data);
		});
	}

	function refreshTimespanFilter() {
		var json = { custom: 1, button_refresh_x: 1, date1: $('#date1').val(), date2: $('#date2').val(), predefined_timespan: $('#predefined_timespan').val(), predefined_timeshift: $('#predefined_timeshift').val() };
		var url  = 'graph_view.php?action=preview&header=false';
		$.post(url, json).done(function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function timeshiftFilterLeft() {
		var json = { move_left_x: 1, move_left_y: 1, date1: $('#date1').val(), date2: $('#date2').val(), predefined_timespan: $('#predefined_timespan').val(), predefined_timeshift: $('#predefined_timeshift').val() };
		var url  = 'graph_view.php?action=preview&header=false';
		$.post(url, json).done(function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function timeshiftFilterRight() {
		var json = { move_right_x: 1, move_right_y: 1, date1: $('#date1').val(), date2: $('#date2').val(), predefined_timespan: $('#predefined_timespan').val(), predefined_timeshift: $('#predefined_timeshift').val() };
		var url  = 'graph_view.php?action=preview&header=false';
		$.post(url, json).done(function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearTimespanFilter() {
		var json = { button_clear_x: 1, date1: $('#date1').val(), date2: $('#date2').val(), predefined_timespan: $('#predefined_timespan').val(), predefined_timeshift: $('#predefined_timeshift').val() };
		var url  = 'graph_view.php?action=preview&header=false';
		$.post(url, json).done(function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	</script>
	<?php

	/* include time span selector */
	if (read_graph_config_option('timespan_sel') == 'on') {
		?>
		<tr class='even noprint'>
			<td class='noprint'>
			<form style='margin:0px;padding:0px;' name='form_timespan_selector' action='graph_view.php?action=preview' method='post' action='graph_view.php'>
				<table cellpadding='1' cellspacing='0'>
					<tr>
						<td width='55'>
							Presets:
						</td>
						<td>
							<select id='predefined_timespan' name='predefined_timespan' onChange='applyTimespanFilterChange(document.form_timespan_selector)'>
								<?php
								if ($_SESSION['custom']) {
									$graph_timespans[GT_CUSTOM] = 'Custom';
									$start_val = 0;
									$end_val = sizeof($graph_timespans);
								} else {
									if (isset($graph_timespans[GT_CUSTOM])) {
										asort($graph_timespans);
										array_shift($graph_timespans);
									}
									$start_val = 1;
									$end_val = sizeof($graph_timespans)+1;
								}

								if (sizeof($graph_timespans) > 0) {
									for ($value=$start_val; $value < $end_val; $value++) {
										print "<option value='$value'"; if ($_SESSION['sess_current_timespan'] == $value) { print ' selected'; } print '>' . title_trim($graph_timespans[$value], 40) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							From:
						</td>
						<td>
							<input type='text' name='date1' id='date1' title='Graph Begin Timestamp' size='15' value='<?php print (isset($_SESSION['sess_current_date1']) ? $_SESSION['sess_current_date1'] : '');?>'>
						</td>
						<td>
							<input type='image' src='images/calendar.gif' align='middle' alt='Start date selector' title='Start date selector' onclick="return showCalendar('date1');">
						</td>
						<td>
							To:
						</td>
						<td>
							<input type='text' name='date2' id='date2' title='Graph End Timestamp' size='15' value='<?php print (isset($_SESSION['sess_current_date2']) ? $_SESSION['sess_current_date2'] : '');?>'>
						</td>
						<td>
							<input type='image' src='images/calendar.gif' align='middle' alt='End date selector' title='End date selector' onclick="return showCalendar('date2');">
						</td>
						<td>
							<img style='padding-bottom:0px;cursor:pointer;' border='0' src='images/move_left.gif' align='middle' alt='' title='Shift Left' onClick='timeshiftFilterLeft()'/>
						</td>
						<td>
							<select id='predefined_timeshift' name='predefined_timeshift' title='Define Shifting Interval'>
								<?php
								$start_val = 1;
								$end_val = sizeof($graph_timeshifts)+1;
								if (sizeof($graph_timeshifts) > 0) {
									for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
										print "<option value='$shift_value'"; if ($_SESSION['sess_current_timeshift'] == $shift_value) { print ' selected'; } print '>' . title_trim($graph_timeshifts[$shift_value], 40) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<img style='padding-bottom:0px;cursor:pointer;' name='move_right' src='images/move_right.gif' align='middle' alt='' title='Shift Right' onClick='timeshiftFilterRight()'/>
						</td>
						<td>
							<input type='button' value='Refresh' title='Refresh selected time span' onClick='refreshTimespanFilter()'>
						</td>
						<td>
							<input type='button' value='Clear' title='Return to the default time span' onClick='clearTimespanFilter()'>
						</td>
					</tr>
				</table>
			</form>
			</td>
		</tr>
		<?php
	}
	html_end_box();

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_graph_config_option('preview_graphs_per_page');
	}

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['columns'] == -1) {
		$_REQUEST['columns'] = read_graph_config_option('num_columns');
	}

	$sql_or = ''; $sql_where = ''; $sql_join = '';

	/* graph permissions */
	if (read_config_option('auth_method') != 0) {
		$sql_where = 'where ' . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

		$sql_join = 'left join host on (host.id=graph_local.host_id)
			left join graph_templates on (graph_templates.id=graph_local.graph_template_id)
			left join user_auth_perms on ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . '))';
	}else{
		$sql_where = '';
		$sql_join = '';
	}

	/* the user select a bunch of graphs of the 'list' view and wants them displayed here */
	if (isset($_REQUEST['style'])) {
		if (get_request_var_request('style') == 'selective') {

			/* process selected graphs */
			if (! empty($_REQUEST['graph_list'])) {
				foreach (explode(',',$_REQUEST['graph_list']) as $item) {
					$graph_list[$item] = 1;
				}
			}else{
				$graph_list = array();
			}
			if (! empty($_REQUEST['graph_add'])) {
				foreach (explode(',',$_REQUEST['graph_add']) as $item) {
					$graph_list[$item] = 1;
				}
			}
			/* remove items */
			if (! empty($_REQUEST['graph_remove'])) {
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
				$sql_or = 'AND ' . array_to_sql_or($graph_array, 'graph_templates_graph.local_graph_id');

				$set_rra_id = empty($rra_id) ? read_graph_config_option('default_rra_id') : get_request_var_request('rra_id');
			}
		}
	}

	$sql_base = "FROM (graph_templates_graph,graph_local)
		$sql_join
		$sql_where
		" . (empty($sql_where) ? 'WHERE' : 'AND') . "   graph_templates_graph.local_graph_id > 0
		AND graph_templates_graph.local_graph_id=graph_local.id
		AND graph_templates_graph.title_cache like '%%" . get_request_var_request('filter') . "%%'
		" . (empty($_REQUEST['host_id']) ? '' : ' and graph_local.host_id=' . get_request_var_request('host_id')) . "
		" . (empty($_REQUEST['graph_template_id']) ? '' : ' and graph_local.graph_template_id=' . get_request_var_request('graph_template_id')) . "
		$sql_or";

	$total_rows = count(db_fetch_assoc('SELECT ' .
		'graph_templates_graph.local_graph_id ' .
		$sql_base));

	$graphs = db_fetch_assoc('SELECT ' .
		'graph_templates_graph.local_graph_id, ' .
		'graph_templates_graph.height, ' .
		'graph_templates_graph.width, ' .
		'graph_templates_graph.title_cache ' .
		$sql_base . ' ' .
		'GROUP BY graph_templates_graph.local_graph_id ' .
		'ORDER BY graph_templates_graph.title_cache ' .
		'limit ' . ($_REQUEST['rows']*($_REQUEST['page']-1)) . ',' . $_REQUEST['rows']);

	/* do some fancy navigation url construction so we don't have to try and rebuild the url string */
	if (ereg('page=[0-9]+',basename($_SERVER['QUERY_STRING']))) {
		$nav_url = str_replace('&page=' . get_request_var_request('page'), '', get_browser_query_string());
	}else{
		$nav_url = get_browser_query_string() . '&host_id=' . get_request_var_request('host_id');
	}

	$nav_url = preg_replace('/((\?|&)host_id=[0-9]+|(\?|&)filter=[a-zA-Z0-9]*)/', '', $nav_url);

	html_start_box('', '100%', "", '3', 'center', '');

	$nav = html_nav_bar($nav_url, MAX_DISPLAY_PAGES, get_request_var_request("page"), get_request_var_request("rows"), $total_rows, get_request_var_request('columns'), "Graphs", 'page', 'main');

	print $nav;

	if (get_request_var_request('thumbnails') == 'true') {
		html_graph_thumbnail_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var_request('columns'));
	}else{
		html_graph_area($graphs, '', 'graph_start=' . get_current_graph_start() . '&graph_end=' . get_current_graph_end(), '', get_request_var_request('columns'));
	}

	if ($total_rows > 0) {
		print $nav;
	}

	html_end_box();

	if (!isset($_REQUEST['header']) || $_REQUEST['header'] == false) {
		bottom_footer();
	}

	break;
case 'list':
	top_graph_header();

	if ((read_config_option('auth_method') != 0) && (empty($current_user['show_list']))) {
		print "<strong><font class='txtErrorTextBox'>YOU DO NOT HAVE RIGHTS FOR LIST VIEW</font></strong>"; exit;
	}

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('host_id'));
	input_validate_input_number(get_request_var_request('graph_template_id'));
	input_validate_input_number(get_request_var_request('rows'));
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
	if (isset($_REQUEST['clear_x'])) {
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
	load_current_session_value('rows', 'sess_graph_view_list_rows', '-1');
	load_current_session_value('graph_list', 'sess_graph_view_list_graph_list', '');

	/* save selected graphs into url */
	if (!empty($_REQUEST['graph_list'])) {
		foreach (explode(',',$_REQUEST['graph_list']) as $item) {
			$graph_list[$item] = 1;
		}
	}else{
		$graph_list = array();
	}
	if (! empty($_REQUEST['graph_add'])) {
		foreach (explode(',',$_REQUEST['graph_add']) as $item) {
			$graph_list[$item] = 1;
		}
	}
	/* remove items */
	if (! empty($_REQUEST['graph_remove'])) {
		foreach (explode(',',$_REQUEST['graph_remove']) as $item) {
			unset($graph_list[$item]);
		}
	}

	/* update the revised graph list session variable */
	$_REQUEST['graph_list'] = implode(',', array_keys($graph_list));
	load_current_session_value('graph_list', 'sess_graph_view_list_graph_list', '');

	/* display graph view filter selector */
	html_start_box('<strong>Graph Filters</strong>' . (isset($_REQUEST['style']) && strlen($_REQUEST['style']) ? ' [ Custom Graph List Applied - Filter from List ]':''), '100%', '', '2', 'center', '');

	?>
	<tr class='even noprint'>
		<td>
		<form style='margin:0px;padding:0px;' name='form_graph_list' method='post' action='graph_view.php?action=list' onSubmit='form_graph(document.chk,document.form_graph_list)'>
			<table cellpadding='1' cellspacing='0'>
				<tr>
					<td nowrap style='white-space: nowrap;' width='55'>
						Host:
					</td>
					<td width='1'>
						<select id='host_id' name='host_id' onChange='applyGraphListFilterChange()'>
							<option value='0'<?php if (get_request_var_request('host_id') == '0') {?> selected<?php }?>>Any</option>
							<?php
							if (read_config_option('auth_method') != 0) {
								/* get policy information for the sql where clause */
								$sql_where = get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);

								$hosts = db_fetch_assoc('SELECT DISTINCT host.id, host.description as name
									FROM host
									LEFT JOIN graph_local ON ( host.id = graph_local.host_id )
									LEFT JOIN graph_templates_graph ON ( graph_templates_graph.local_graph_id = graph_local.id )
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . '))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									' . (empty($sql_where) ? '' : "and $sql_where") . '
									ORDER BY name');
							}else{
								$hosts = db_fetch_assoc('SELECT DISTINCT host.id, host.description as name
									FROM host
									ORDER BY name');
							}

							if (sizeof($hosts) > 0) {
								foreach ($hosts as $host) {
									print "<option value='" . $host['id'] . "'"; if (get_request_var_request('host_id') == $host['id']) { print ' selected'; } print '>' . htmlspecialchars($host['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td width='70'>
						Template:
					</td>
					<td width='1'>
						<select id='graph_template_id' name='graph_template_id' onChange='applyGraphListFilterChange()'>
							<option value='0'<?php print htmlspecialchars(get_request_var_request('filter'));?><?php if (get_request_var_request('host_id') == '0') {?> selected<?php }?>>Any</option>
							<?php
							if (read_config_option('auth_method') != 0) {
								$graph_templates = db_fetch_assoc('SELECT DISTINCT graph_templates.*
									FROM host
									LEFT JOIN graph_local ON ( host.id = graph_local.host_id )
									LEFT JOIN graph_templates_graph ON ( graph_templates_graph.local_graph_id = graph_local.id )
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . '))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									' . (empty($sql_where) ? '' : "and $sql_where") . '
									ORDER BY name');
							}else{
								$graph_templates = db_fetch_assoc('SELECT DISTINCT graph_templates.*
									FROM graph_templates
									ORDER BY name');
							}

							if (sizeof($graph_templates) > 0) {
								foreach ($graph_templates as $template) {
									print "<option value='" . $template['id'] . "'"; if (get_request_var_request('graph_template_id') == $template['id']) { print ' selected'; } print '>' . htmlspecialchars($template['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td style='white-space: nowrap;' width='50'>
						Graphs per Page:
					</td>
					<td width='1'>
						<select id='rows' name='rows' onChange='applyGraphListFilterChange()'>
							<option value='-1'<?php if (get_request_var_request('rows') == '-1') {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td style='white-space: nowrap;' width='50'>
						Search:
					</td>
					<td width='1'>
						<input id='filter' type='text' name='filter' size='40' value='<?php print htmlspecialchars(get_request_var_request('filter'));?>'>
					</td>
					<td>
						<input type='button' value='Go' title='Set/Refresh Filters' onClick='applyGraphListFilterChange()'>
					</td>
					<td>
						<input type='button' name='clear_x' value='Clear' title='Clear Filters' onClick='clearFilter()'>
					</td>
					<td>
						<input type='button' value='View' title='View Graphs' onClick='viewGraphs()'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='graph_add' value=''>
			<input type='hidden' name='graph_remove' value=''>
			<input type='hidden' name='graph_list' value='<?php print $_REQUEST['graph_list'];?>'>
		</form>
		</td>
	</tr>
	<?php
	html_end_box();

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_graph_config_option('list_graphs_per_page');
	}

	/* create filter for sql */
	$sql_filter = '';
	$sql_filter .= (empty($_REQUEST['filter']) ? '' : " graph_templates_graph.title_cache like '%" . get_request_var_request('filter') . "%'");
	$sql_filter .= (empty($_REQUEST['host_id']) ? '' : (empty($sql_filter) ? '' : ' and') . ' graph_local.host_id=' . get_request_var_request('host_id'));
	$sql_filter .= (empty($_REQUEST['graph_template_id']) ? '' : (empty($sql_filter) ? '' : ' and') . ' graph_local.graph_template_id=' . get_request_var_request('graph_template_id'));

	/* graph permissions */
	if (read_config_option('auth_method') != 0) {
		/* get policy information for the sql where clause */
		$sql_where = 'where ' . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);
		$sql_join = 'left join host on (host.id=graph_local.host_id)
			left join graph_templates on (graph_templates.id=graph_local.graph_template_id)
			left join user_auth_perms on ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . '))';

	}else{
		$sql_where = '';
		$sql_join = '';
	}

	$sql_base = "from (graph_templates_graph,graph_local)
		$sql_join
		$sql_where
		" . (empty($sql_where) ? 'where' : 'and') . " graph_templates_graph.local_graph_id > 0
		and graph_templates_graph.local_graph_id=graph_local.id
		and graph_templates_graph.title_cache like '%" . $_REQUEST['filter'] . "%'
		" . (empty($_REQUEST['host_id']) ? '' : ' and graph_local.host_id=' . $_REQUEST['host_id']) . '
		' . (empty($_REQUEST['graph_template_id']) ? '' : ' and graph_local.graph_template_id=' . $_REQUEST['graph_template_id']);

	$total_rows = count(db_fetch_assoc("select
		graph_templates_graph.local_graph_id
		$sql_base"));
	$graphs = db_fetch_assoc("select
		graph_templates_graph.local_graph_id,
		host.description,
		graph_templates.name AS template_name,
		graph_templates_graph.title_cache,
		graph_templates_graph.height,
		graph_templates_graph.width
		$sql_base
		group by graph_templates_graph.local_graph_id
		order by graph_templates_graph.title_cache
		limit " . ($_REQUEST['rows']*($_REQUEST['page']-1)) . ',' . $_REQUEST['rows']);
	?>

	<form name='chk' id='chk' action='graph_view.php' method='get' onSubmit='form_graph(document.chk,document.chk)'>

	<?php

	html_start_box('', '100%', '', '3', 'center', '');

	$nav = html_nav_bar("graph_view.php?action=list", MAX_DISPLAY_PAGES, get_request_var_request("page"), get_request_var_request("rows"), $total_rows, 5, "Graphs", 'page', 'main');

	print $nav;

	html_header_checkbox(array('Graph Title', 'Host', 'Graph Template', 'Graph Size'), false);

	$i = 0;
	if (sizeof($graphs)) {
		foreach ($graphs as $graph) {
			form_alternate_row('line' . $graph['local_graph_id'], true);
			form_selectable_cell("<strong><a href='" . htmlspecialchars('graph.php?local_graph_id=' . $graph['local_graph_id'] . '&rra_id=all') . "'>" . htmlspecialchars($graph['title_cache']) . '</a></strong>', $graph['local_graph_id'], '30%');
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
	<input type='hidden' name='style' value='selective'>
	<input type='hidden' name='action' value='preview'>
	<input type='hidden' id='graph_list' name='graph_list' value='<?php print $_REQUEST['graph_list']; ?>'>
	<input type='hidden' id='graph_add' name='graph_add' value=''>
	<input type='hidden' id='graph_remove' name='graph_remove' value=''>
	</form>
	<script type='text/javascript'>
	<!--
	var graph_list_array = new Array(<?php print $_REQUEST['graph_list'];?>);
	initializeChecks();

	$(function() {
		applySkin();
	});

	function clearFilter() {
		$.get('graph_view.php?action=list&header=false&clear_x=1', function(data) {
			$('#main').html(data);
		});
	}

	function applyGraphListFilterChange() {
		strURL = 'graph_view.php?action=list&header=false;&page=1';
		strURL += '&host_id=' + $('#host_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&graph_template_id=' + $('#graph_template_id').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += url_graph('');
		$.get(strURL, function(data) {
			$('#main').html(data);
		});
	}

	function initializeChecks() {
		for (var i = 0; i < graph_list_array.length; i++) {
			if (document.getElementById('chk_'+graph_list_array[i])) {
				select_line(graph_list_array[i]);
			}
		}
	}

	function viewGraphs() {
		graphList = $('#graph_list').val();
		for(var i = 0; i < document.chk.elements.length; i++) {
			if (document.chk.elements[i].name.substr(0,4) == 'chk_') {
				if (document.chk.elements[i].checked) {
					graphList += document.chk.elements[i].id.substr(4) + ',';
				}
			}
		}
		$('#graph_list').val(graphList);

		document.chk.submit();
	}

	function url_graph(strNavURL) {
		var strURL = '';
		var strAdd = '';
		var strDel = '';
		for(var i = 0; i < document.chk.elements.length; i++) {
			if (document.chk.elements[i].name.substr(0,4) == 'chk_') {
				if (document.chk.elements[i].checked) {
					strAdd = strAdd + document.chk.elements[i].id.substr(4) + ',';
				} else if (graphChecked(document.chk.elements[i].id.substr(4))) {
					strDel = strDel + document.chk.elements[i].id.substr(4) + ',';
				}
			}
		}
		strAdd = strAdd.substring(0,strAdd.length - 1);
		strDel = strDel.substring(0,strDel.length - 1);
		strURL = '&graph_add=' + strAdd + '&graph_remove=' + strDel;
		return strNavURL + strURL;
	}

	function url_go(strURL) {
		document.location = strURL;
		return false;
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
		for(var i = 0; i < objForm.elements.length; i++) {
			if (objForm.elements[i].id.substring(0,4) == 'chk_') {
				if (objForm.elements[i].checked) {
					strAdd = strAdd + objForm.elements[i].id.substr(4) + ',';
				} else if (graphChecked(document.chk.elements[i].id.substr(4))) {
					strDel = strDel + objForm.elements[i].id.substr(4) + ',';
				}
			}
		}
		strAdd = strAdd.substring(0,strAdd.length - 1);
		strDel = strDel.substring(0,strDel.length - 1);
		objFormSubmit.graph_add.value = strAdd;
		objFormSubmit.graph_remove.value = strDel;
	}
	-->
	</script>
	<?php

	bottom_footer();

	break;
}

?>
