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

define('MAX_DISPLAY_PAGES', 21);

$gprint_actions = array(1 => 'Delete');

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

		gprint_presets_edit();

		bottom_footer();
		break;
	default:
		top_header();

		gprint_presets();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST['save_component_gprint_presets'])) {
		$save['id'] = $_POST['id'];
		$save['hash'] = get_hash_gprint($_POST['id']);
		$save['name'] = form_input_validate($_POST['name'], 'name', '', false, 3);
		$save['gprint_text'] = form_input_validate($_POST['gprint_text'], 'gprint_text', '', false, 3);

		if (!is_error_message()) {
			$gprint_preset_id = sql_save($save, 'graph_templates_gprint');

			if ($gprint_preset_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: gprint_presets.php?action=edit&id=' . (empty($gprint_preset_id) ? $_POST['id'] : $gprint_preset_id));
			exit;
		}else{
			header('Location: gprint_presets.php');
			exit;
		}
	}
}

/* -----------------------------------
    gprint_presets - GPRINT Presets
   ----------------------------------- */

function form_actions() {
	global $gprint_actions;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), '^([a-zA-Z0-9_]+)$');
	/* ==================================================== */
	
	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == '1') { /* delete */
			db_execute('DELETE FROM graph_templates_gprint WHERE ' . array_to_sql_or($selected_items, 'id'));
		}

		header('Location: gprint_presets.php');
		exit;
	}

	/* setup some variables */
	$gprint_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$gprint_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM graph_templates_gprint WHERE id = ?', array($matches[1]))) . '</li>';
			$gprint_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	print "<form action='gprint_presets.php' method='post'>\n";

	html_start_box('<strong>' . $gprint_actions{$_POST['drp_action']} . '</strong>', '60%', '', '3', 'center', '');

	if (isset($gprint_array) && sizeof($gprint_array)) {
		if ($_POST['drp_action'] == '1') { /* delete */
			print "	<tr>
					<td class='textArea' class='odd'>
						<p>When you click \"Continue\", the folling GPRINT Preset(s) will be deleted.</p>
						<ul>$gprint_list</ul>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete GPRINT Preset(s)'>";
		}
	}else{
		print "<tr><td class='odd'><span class='textError'>You must select at least one GPRINT Preset.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($gprint_array) ? serialize($gprint_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
				$save_html
			</td>
		</tr>\n";

	html_end_box();

	bottom_footer();
}

function gprint_presets_edit() {
	global $fields_grprint_presets_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if (!empty($_GET['id'])) {
		$gprint_preset = db_fetch_row_prepared('SELECT * FROM graph_templates_gprint WHERE id = ?', array(get_request_var('id')));
		$header_label = '[edit: ' . htmlspecialchars($gprint_preset['name']) . ']';
	}else{
		$header_label = '[new]';
	}

	html_start_box("<strong>GPRINT Presets</strong> $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array(),
		'fields' => inject_form_variables($fields_grprint_presets_edit, (isset($gprint_preset) ? $gprint_preset : array()))
		));

	html_end_box();

	form_save_button('gprint_presets.php');
}

function gprint_presets() {
	global $gprint_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up search string */
	if (isset($_REQUEST['has_graphs'])) {
		$_REQUEST['has_graphs'] = sanitize_search_string(get_request_var_request('has_graphs'));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_gprint_current_page');
		kill_session_var('sess_gprint_filter');
		kill_session_var('sess_gprint_has_graphs');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_gprint_sort_column');
		kill_session_var('sess_gprint_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['has_graphs']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}

    /* remember these search fields in session vars so we don't have to keep passing them around */
    load_current_session_value('page', 'sess_gprint_current_page', '1');
    load_current_session_value('filter', 'sess_gprint_filter', '');
    load_current_session_value('has_graphs', 'sess_gprint_has_graphs', 'true');
    load_current_session_value('sort_column', 'sess_gprint_sort_column', 'name');
    load_current_session_value('sort_direction', 'sess_gprint_sort_direction', 'ASC');
    load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	html_start_box('<strong>GPRINT Presets</strong>', '100%', '', '3', 'center', 'gprint_presets.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_gprint' action='gprint_presets.php'>
			<table cellpadding='2' cellspacing='0' border='0'>
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var_request('filter'));?>'>
					</td>
					<td>
						GPRINTs
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
						<input type="checkbox" id='has_graphs' <?php print ($_REQUEST['has_graphs'] == 'true' ? 'checked':'');?>>
					</td>
					<td>
						<label for='has_graphs' style='white-space:nowrap;'>Has Graphs</label>
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
			<script type='text/javascript'>
			function applyFilter() {
				strURL = 'gprint_presets.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&has_graphs='+$('#has_graphs').is(':checked')+'&header=false';
				$.get(strURL, function(data) {
					$('#main').html(data);
					applySkin();
				});
			}

			function clearFilter() {
				strURL = 'gprint_presets.php?clear_x=1&header=false';
				$.get(strURL, function(data) {
					$('#main').html(data);
					applySkin();
				});
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#has_graphs').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_gprint').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});
			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if ($_REQUEST['filter'] != '') {
		$sql_where = "WHERE (name LIKE '%" . get_request_var_request('filter') . "%')";
	}else{
		$sql_where = '';
	}

	if ($_REQUEST['has_graphs'] == 'true') {
		$sql_having = 'HAVING graphs>0';
	}else{
		$sql_having = '';
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='gprint_presets.php'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(rows)
		FROM (
			SELECT gp.id AS rows,
			SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs
			FROM graph_templates_gprint AS gp
			LEFT JOIN graph_templates_item AS gti
			ON gti.gprint_id=gp.id
			$sql_where
			GROUP BY gp.id
			$sql_having
		) AS rs");
			
	$gprint_list = db_fetch_assoc("SELECT rs.*,
		SUM(CASE WHEN local_graph_id=0 THEN 1 ELSE 0 END) AS templates,
		SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs
		FROM (
			SELECT gp.*, gti.local_graph_id
			FROM graph_templates_gprint AS gp
			LEFT JOIN graph_templates_item AS gti
			ON gti.gprint_id=gp.id
			GROUP BY gp.id, gti.graph_template_id, gti.local_graph_id
		) AS rs
		$sql_where
		GROUP BY rs.id
		$sql_having
		ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') .
		' LIMIT ' . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows'));

	$nav = html_nav_bar('gprint_presets.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 5, 'GPRINTs', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('GPRINT Preset Title', 'ASC'),
		'nosort' => array('display' => 'Deletable', 'align' => 'right', 'tip' => 'GPRINTs that are in use can not be Deleted'), 
		'graphs' => array('display' => 'Graphs Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Graphs using this GPRINT'),
		'templates' => array('display' => 'Templates Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Graphs Templates using this GPRINT')
	);

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	$i = 0;
	if (sizeof($gprint_list) > 0) {
		foreach ($gprint_list as $gp) {
			if ($gp['graphs'] == 0 && $gp['templates'] == 0) {
				$disabled = false;
			}else{
				$disabled = true;
			}

            form_alternate_row('line' . $gp['id'], false, $disabled);
            form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('gprint_presets.php?action=edit&id=' . $gp['id']) . "'>" . (strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($gp['name'])) : htmlspecialchars($gp['name'])) . '</a>', $gp['id']);
            form_selectable_cell($disabled ? 'No':'Yes', $gp['id'], '', 'text-align:right');
            form_selectable_cell($gp['graphs'], $gp['id'], '', 'text-align:right');
            form_selectable_cell($gp['templates'], $gp['id'], '', 'text-align:right');
            form_checkbox_cell($gp['name'], $gp['id'], $disabled);
            form_end_row();
		}
		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='4'><em>No GPRINT Presets</em></td></tr>\n";
	}

	html_end_box();

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($gprint_actions);

	print "</form>\n";
}
