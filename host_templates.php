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
include_once('./lib/utility.php');

define('MAX_DISPLAY_PAGES', 21);

$host_actions = array(
	1 => 'Delete',
	2 => 'Duplicate'
	);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'item_remove_gt':
		template_item_remove_gt();

		header('Location: host_templates.php?action=edit&id=' . $_GET['host_template_id']);
		break;
	case 'item_remove_dq':
		template_item_remove_dq();

		header('Location: host_templates.php?action=edit&id=' . $_GET['host_template_id']);
		break;
	case 'edit':
		top_header();

		template_edit();

		bottom_footer();
		break;
	default:
		top_header();

		template();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('host_template_id'));
	input_validate_input_number(get_request_var_post('snmp_query_id'));
	input_validate_input_number(get_request_var_post('graph_template_id'));
	/* ==================================================== */

	if (isset($_POST['save_component_template'])) {
		$redirect_back = false;

		$save['id'] = $_POST['id'];
		$save['hash'] = get_hash_host_template($_POST['id']);
		$save['name'] = form_input_validate($_POST['name'], 'name', '', false, 3);

		if (!is_error_message()) {
			$host_template_id = sql_save($save, 'host_template');

			if ($host_template_id) {
				raise_message(1);

				if (isset($_POST['add_gt_x'])) {
					db_execute_prepared('REPLACE INTO host_template_graph (host_template_id, graph_template_id) VALUES (?, ?)', array($host_template_id, $_POST['graph_template_id']));
					$redirect_back = true;
				}elseif (isset($_POST['add_dq_x'])) {
					db_execute_prepared('REPLACE INTO host_template_snmp_query (host_template_id, snmp_query_id) VALUES (?, ?)', array($host_template_id, $_POST['snmp_query_id']));
					$redirect_back = true;
				}
			}else{
				raise_message(2);
			}
		}

		header('Location: host_templates.php?action=edit&id=' . (empty($host_template_id) ? $_POST['id'] : $host_template_id));
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $host_actions;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), '^([a-zA-Z0-9_]+)$');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == '1') { /* delete */
			db_execute('DELETE FROM host_template WHERE ' . array_to_sql_or($selected_items, 'id'));
			db_execute('DELETE FROM host_template_snmp_query WHERE ' . array_to_sql_or($selected_items, 'host_template_id'));
			db_execute('DELETE FROM host_template_graph WHERE ' . array_to_sql_or($selected_items, 'host_template_id'));

			/* "undo" any device that is currently using this template */
			db_execute('UPDATE host SET host_template_id=0 WHERE ' . array_to_sql_or($selected_items, 'host_template_id'));
		}elseif ($_POST['drp_action'] == '2') { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				duplicate_host_template($selected_items[$i], $_POST['title_format']);
			}
		}

		header('Location: host_templates.php');
		exit;
	}

	/* setup some variables */
	$host_list = ''; $i = 0;

	/* loop through each of the host templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$host_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM host_template WHERE id = ?'), array($matches[1])) . '<br>';
			$host_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	html_start_box('<strong>' . $host_actions{$_POST['drp_action']} . '</strong>', '60%', '', '3', 'center', '');

	print "<form action='host_templates.php' autocomplete='off' method='post'>\n";

	if (isset($host_array) && sizeof($host_array)) {
		if ($_POST['drp_action'] == '1') { /* delete */
			print "	<tr>
					<td class='textArea'>
						<p>Are you sure you want to delete the following Host Template(s)? All Devices currently associated
						with these Host Template(s) will lose that assocation.</p>
						<p><ul>$host_list</ul></p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Host Template(s)'>";
		}elseif ($_POST['drp_action'] == '2') { /* duplicate */
			print "	<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following Host Template(s) will be duplicated. You can
						optionally change the title format for the new Host Template(s).</p>
						<p><ul>$host_list</ul></p>
						<p><strong>Title Format:</strong><br>"; form_text_box('title_format', '<template_title> (1)', '', '255', '30', 'text'); print "</p>
					</td>
				</tr>\n
				";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Host Template(s)'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one host template.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($host_array) ? serialize($host_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	bottom_footer();
}

/* ---------------------
    Template Functions
   --------------------- */

function template_item_remove_gt() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('host_template_id'));
	/* ==================================================== */

	db_execute_prepared('DELETE FROM host_template_graph WHERE graph_template_id = ? AND host_template_id = ?', array(get_request_var('id'), get_request_var('host_template_id')));
}

function template_item_remove_dq() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('host_template_id'));
	/* ==================================================== */

	db_execute_prepared('DELETE FROM host_template_snmp_query WHERE snmp_query_id = ? AND host_template_id = ?', array(get_request_var('id'), get_request_var('host_template_id')));
}

function template_edit() {
	global $fields_host_template_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if (!empty($_GET['id'])) {
		$host_template = db_fetch_row_prepared('SELECT * FROM host_template WHERE id = ?', array(get_request_var('id')));
		$header_label = '[edit: ' . $host_template['name'] . ']';
	}else{
		$header_label = '[new]';
		$_GET['id'] = 0;
	}

	html_start_box('<strong>Host Templates</strong> ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('form_name' => 'chk'),
		'fields' => inject_form_variables($fields_host_template_edit, (isset($host_template) ? $host_template : array()))
	));

	/* we have to hide this button to make a form change in the main form trigger the correct
	 * submit action */
	echo "<div style='display:none;'><input type='submit' value='Default Submit Button'></div>";

	html_end_box();

	if (!empty($_GET['id'])) {
		html_start_box('<strong>Associated Graph Templates</strong>', '100%', '', '3', 'center', '');

		$selected_graph_templates = db_fetch_assoc_prepared('SELECT
			graph_templates.id,
			graph_templates.name
			FROM (graph_templates,host_template_graph)
			WHERE graph_templates.id = host_template_graph.graph_template_id
			AND host_template_graph.host_template_id = ?
			ORDER BY graph_templates.name', array(get_request_var('id')));

		$i = 0;
		if (sizeof($selected_graph_templates) > 0) {
			foreach ($selected_graph_templates as $item) {
				form_alternate_row('', true);
				?>
					<td style="padding: 4px;">
						<strong><?php print $i;?>)</strong> <?php print htmlspecialchars($item['name']);?>
					</td>
					<td align="right">
						<a href='<?php print htmlspecialchars('host_templates.php?action=item_remove_gt&id=' . $item['id'] . '&host_template_id=' . $_GET['id']);?>'><img src='images/delete_icon.gif' style="height:10px;width:10px;" border='0' alt='Delete'></a>
					</td>
				<?php
				form_end_row();

				$i++;
			}
		}else{ 
			print '<tr><td><em>No associated graph templates.</em></td></tr>'; 
		}

		?>
		<tr class='odd'>
			<td colspan="2">
				<table cellspacing="0" cellpadding="1" width="100%">
					<td nowrap>Add Graph Template:&nbsp;
						<?php form_dropdown('graph_template_id',db_fetch_assoc_prepared('SELECT
							graph_templates.id,
							graph_templates.name
							FROM graph_templates LEFT JOIN host_template_graph
							ON (graph_templates.id = host_template_graph.graph_template_id AND host_template_graph.host_template_id = ?)
							WHERE host_template_graph.host_template_id is null
							ORDER BY graph_templates.name', array(get_request_var('id'))),'name','id','','','');?>
					</td>
					<td align="right">
						&nbsp;<input type="submit" value="Add" name="add_gt_x" title="Add Graph Template to Host Template">
					</td>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();

		html_start_box('<strong>Associated Data Queries</strong>', '100%', '', '3', 'center', '');

		$selected_data_queries = db_fetch_assoc_prepared('SELECT
			snmp_query.id,
			snmp_query.name
			FROM (snmp_query, host_template_snmp_query)
			WHERE snmp_query.id = host_template_snmp_query.snmp_query_id
			AND host_template_snmp_query.host_template_id = ?
			ORDER BY snmp_query.name', array(get_request_var('id')));

		$i = 0;
		if (sizeof($selected_data_queries) > 0) {
			foreach ($selected_data_queries as $item) {
				form_alternate_row('', true);
				?>
					<td style="padding: 4px;">
						<strong><?php print $i;?>)</strong> <?php print htmlspecialchars($item['name']);?>
					</td>
					<td align='right'>
						<a href='<?php print htmlspecialchars('host_templates.php?action=item_remove_dq&id=' . $item['id'] . '&host_template_id=' . $_GET['id']);?>'><img src='images/delete_icon.gif' style="height:10px;width:10px;" border='0' alt='Delete'></a>
					</td>
				<?php
				form_end_row();

				$i++;
			}
		}else{ 
			print '<tr><td><em>No associated data queries.</em></td></tr>'; 
		}

		?>
		<tr class='odd'>
			<td colspan="2">
				<table cellspacing="0" cellpadding="1" width="100%">
					<td nowrap>Add Data Query:&nbsp;
						<?php form_dropdown('snmp_query_id',db_fetch_assoc_prepared('SELECT
							snmp_query.id,
							snmp_query.name
							FROM snmp_query LEFT JOIN host_template_snmp_query
							ON (snmp_query.id = host_template_snmp_query.snmp_query_id AND host_template_snmp_query.host_template_id = ?)
							WHERE host_template_snmp_query.host_template_id is null
							ORDER BY snmp_query.name', array(get_request_var('id'))),'name','id','','','');?>
					</td>
					<td align="right">
						&nbsp;<input type="submit" value="Add" name="add_dq_x" title="Add Data Query to Host Template">
					</td>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();
	}

	form_save_button('host_templates.php', 'return');
}

function template() {
	global $host_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up has_hosts string */
	if (isset($_REQUEST['has_hosts'])) {
		$_REQUEST['has_hosts'] = sanitize_search_string(get_request_var('has_hosts'));
	}

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_host_template_current_page');
		kill_session_var('sess_host_template_hosts');
		kill_session_var('sess_host_template_filter');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_host_template_sort_column');
		kill_session_var('sess_host_template_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['has_hosts']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('has_hosts', 'sess_host_template_has_hosts');
		$changed += check_changed('rows', 'sess_default_rows');
		$changed += check_changed('filter', 'sess_host_template_filter');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_host_template_current_page', '1');
	load_current_session_value('has_hosts', 'sess_host_template_has_hosts', 'true');
	load_current_session_value('filter', 'sess_host_template_filter', '');
	load_current_session_value('sort_column', 'sess_host_template_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_host_template_sort_direction', 'ASC');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	display_output_messages();

	html_start_box('<strong>Host Templates</strong>', '100%', '', '3', 'center', 'host_templates.php?action=edit');

	?>
	<tr class='even noprint'>
		<td>
		<form id="form_host_template" action="host_templates.php">
			<table cellpadding="2" cellspacing="0" border="0">
				<tr>
					<td width="50">
						Search:
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>">
					</td>
					<td style='white-space:nowrap;'>
						Host Templates:
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
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
						<input type="checkbox" id='has_hosts' <?php print ($_REQUEST['has_hosts'] == 'true' ? 'checked':'');?>>
					</td>
					<td>
						<label for='has_hosts' style='white-space:nowrap;'>Has Hosts</label>
					</td>
					<td>
						<input type="button" id='refresh' value="Go" title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clear_x" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
		<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		</td>
		<script type='text/javascript'>
		function applyFilter() {
			strURL = 'host_templates.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&has_hosts='+$('#has_hosts').is(':checked')+'&header=false';
			$.get(strURL, function(data) {
				$('#main').html(data);
				applySkin();
			});
		}

		function clearFilter() {
			strURL = 'host_templates.php?clear_x=1&header=false';
			$.get(strURL, function(data) {
				$('#main').html(data);
				applySkin();
			});
		}

		$(function() {
			$('#refresh, #has_hosts').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});
	
			$('#form_host_template').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
	</tr>
	<?php


	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST['filter'])) {
		$sql_where = "WHERE (host_template.name LIKE '%%" . get_request_var_request('filter') . "%%')";
	}else{
		$sql_where = '';
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='host_templates.php'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	if ($_REQUEST['has_hosts'] == 'true') {
		$sql_having = 'HAVING hosts>0';
	}else{
		$sql_having = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(rows)
		FROM (
			SELECT
			COUNT(host_template.id) AS rows, COUNT(DISTINCT host.id) AS hosts
			FROM host_template
			LEFT JOIN host ON host.host_template_id=host_template.id
			$sql_where
			GROUP BY host_template.id
			$sql_having
		) AS rs");

	$template_list = db_fetch_assoc("SELECT
		host_template.id,host_template.name, COUNT(DISTINCT host.id) AS hosts
		FROM host_template
		LEFT JOIN host ON host.host_template_id=host_template.id
		$sql_where
		GROUP BY host_template.id
		$sql_having
		ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') .
		' LIMIT ' . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows'));

	$nav = html_nav_bar('host_templates.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 4, 'Host Templates', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('Template Title', 'ASC'),
		'hosts' => array('display' => 'Hosts', 'align' => 'right', 'sort' => 'DESC'),
		'host_template.id' => array('display' => 'ID', 'align' => 'right', 'sort' => 'ASC')
	);

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	$i = 0;
	if (sizeof($template_list) > 0) {
		foreach ($template_list as $template) {
			if ($template['hosts'] > 0) {
				$disable = true;
			}else{
				$disable = false;
			}

			form_alternate_row('line' . $template['id'], true, $disable);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('host_templates.php?action=edit&id=' . $template['id']) . "'>" . (strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($template['name'])) : htmlspecialchars($template['name'])) . '</a>', $template['id']);
			form_selectable_cell(number_format($template['hosts']), $template['id'], '', 'text-align:right');
			form_selectable_cell($template['id'], $template['id'], '', 'text-align:right');
			form_checkbox_cell($template['name'], $template['id'], $disable);
			form_end_row();
		}
		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='4'><em>No Host Templates</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($host_actions);

	print "</form>\n";
}


