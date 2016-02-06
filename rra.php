<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

$rra_actions = array(1 => 'Delete');

/* set default action */
set_default_action();

switch ($_REQUEST['action']) {
	case 'save':
		form_save();

		break;
    case 'actions':
        form_actions();

        break;
	case 'edit':
		top_header();

		rra_edit();

		bottom_footer();
		break;
	default:
		top_header();

		rra();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST['save_component_rra'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		/* ==================================================== */

		$save['id'] = get_request_var_post('id');
		$save['hash'] = get_hash_round_robin_archive(get_request_var_post('id'));
		$save['name'] = form_input_validate(get_request_var_post('name'), 'name', '', false, 3);
		$dummy = form_input_validate(count(get_request_var_post('consolidation_function_id')), 'consolidation_function_id', '^[0-9]*$', false, 3);
		$save['x_files_factor'] = form_input_validate(get_request_var_post('x_files_factor'), 'x_files_factor', "^[01]?(\.[0-9]+)?$", false, 3);
		$save['steps'] = form_input_validate(get_request_var_post('steps'), 'steps', '^[0-9]*$', false, 3);
		$save['rows'] = form_input_validate(get_request_var_post('rows'), 'rows', '^[0-9]*$', false, 3);
		$save['timespan'] = form_input_validate(get_request_var_post('timespan'), 'timespan', '^[0-9]*$', false, 3);

		if (!is_error_message()) {
			$rra_id = sql_save($save, 'rra');

			if ($rra_id) {
				raise_message(1);

				db_execute_prepared('DELETE FROM rra_cf WHERE rra_id = ?', array($rra_id));

				if (isset(get_request_var_post('consolidation_function_id'))) {
					for ($i = 0; ($i < count(get_request_var_post('consolidation_function_id'))); $i++) {
						/* ================= input validation ================= */
						input_validate_input_number(get_request_var_post('consolidation_function_id')[$i]);
						/* ==================================================== */

						db_execute_prepared('INSERT INTO rra_cf (rra_id, consolidation_function_id) VALUES (?, ?)', array($rra_id, get_request_var_post('consolidation_function_id')[$i]));
					}
				}else{
					raise_message(2);
				}
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: rra.php?action=edit&header=false&id=' . (empty($rra_id) ? get_request_var_post('id') : $rra_id));
		}else{
			header('Location: rra.php?header=false');
		}
		exit;
	}
}

/* -------------------
    RRA Functions
   ------------------- */

function form_actions() {
	global $rra_actions;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), '^([a-zA-Z0-9_]+)$');
	/* ==================================================== */
	
	/* if we are to save this form, instead of display it */
	if (isset(get_request_var_post('selected_items'))) {
		$selected_items = sanitize_unserialize_selected_items(get_request_var_post('selected_items'));

		if ($selected_items != false) {
			if (get_request_var_post('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM rra WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM rra_cf WHERE ' . array_to_sql_or($selected_items, 'rra_id'));
			}
		}

		header('Location: rra.php?header=false');
		exit;
	}

	/* setup some variables */
	$rra_list = ''; $i = 0;

	/* loop through each of the rra selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$rra_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM rra WHERE id = ?', array($matches[1]))) . '</li>';
			$rra_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('rra.php');

	html_start_box($rra_actions{get_request_var_post('drp_action')}, '60%', '', '3', 'center', '');

	if (isset($rra_array) && sizeof($rra_array)) {
		if (get_request_var_post('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>Click 'Continue' to delete the folling Round Robin Archive definition(s).</p>
					<ul>$rra_list</ul>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Round Robin Archive definition(s)'>";
		}
	}else{
		print "<tr><td class='odd'><span class='textError'>You must select at least one Round Robin Archive definition.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($rra_array) ? serialize($rra_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var_post('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function rra_edit() {
	global $fields_rra_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if (!empty($_REQUEST['id'])) {
		$rra = db_fetch_row_prepared('SELECT * FROM rra WHERE id = ?', array(get_request_var('id')));
		$header_label = '[edit: ' . htmlspecialchars($rra['name']) . ']';
	}else{
		$header_label = '[new]';
	}

	form_start('rra.php', 'rra');

	html_start_box("Round Robin Archives $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_rra_edit, (isset($rra) ? $rra : array()))
		)
	);

	html_end_box();

	form_save_button('rra.php');

	?>
	<script type='text/javascript'>
	$(function() {
		$('#consolidation_function_id').multiselect({
			selectedList: 1,
			noneSelectedText: 'Select Consolidation Function(s)',
			header: false,
			multipleRow: true,
			multipleRowWidth: 90,
			height: 28,
			minWidth: 400
		});
	});
	</script>
	<?php
}

function rra() {
	global $rra_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('page'));
	input_validate_input_number(get_request_var('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up has_data string */
	if (isset($_REQUEST['has_data'])) {
		$_REQUEST['has_data'] = sanitize_search_string(get_request_var('has_data'));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_rra_current_page');
		kill_session_var('sess_rra_filter');
		kill_session_var('sess_rra_has_data');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_rra_sort_column');
		kill_session_var('sess_rra_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['has_data']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('rows',       'sess_default_rows');
		$changed += check_changed('has_graphs', 'sess_rra_has_data');
		$changed += check_changed('filter',     'sess_rra_filter');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

    /* remember these search fields in session vars so we don't have to keep passing them around */
    load_current_session_value('page', 'sess_rra_current_page', '1');
    load_current_session_value('filter', 'sess_rra_filter', '');
    load_current_session_value('has_data', 'sess_rra_has_data', 'true');
    load_current_session_value('sort_column', 'sess_rra_sort_column', 'steps');
    load_current_session_value('sort_direction', 'sess_rra_sort_direction', 'ASC');
    load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	html_start_box('Round Robin Archives', '100%', '', '3', 'center', 'rra.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_rra' action='rra.php'>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						RRAs
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type="checkbox" id='has_data' <?php print ($_REQUEST['has_data'] == 'true' ? 'checked':'');?>>
					</td>
					<td>
						<label for='has_data'>Has Data Sources</label>
					</td>
					<td>
						<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' id='clear' value='Clear' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
			<script type='text/javascript'>
			function applyFilter() {
				strURL = 'rra.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&has_data='+$('#has_data').is(':checked')+'&header=false';
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'rra.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#has_data').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_rra').submit(function(event) {
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
		$sql_where = "WHERE (rs.name LIKE '%" . get_request_var('filter') . "%')";
	}else{
		$sql_where = '';
	}

	if ($_REQUEST['has_data'] == 'true') {
		$sql_having = 'HAVING data_sources>0';
	}else{
		$sql_having = '';
	}

	form_start('rra.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(rows)
		FROM (
			SELECT
			rs.id AS rows,
	        SUM(CASE WHEN local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
			FROM rra AS rs
			LEFT JOIN data_template_data_rra AS dtdr
			ON rs.id=dtdr.rra_id
			LEFT JOIN data_template_data AS dtd
			ON dtdr.data_template_data_id=dtd.id
			$sql_where
			GROUP BY rs.id
			$sql_having
		) AS rs");

	$rras = db_fetch_assoc("SELECT rs.*,
        SUM(CASE WHEN local_data_id=0 THEN 1 ELSE 0 END) AS templates,
        SUM(CASE WHEN local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
        FROM (
			SELECT rra.*, local_data_id
			FROM rra
			LEFT JOIN data_template_data_rra AS dtdr
			ON rra.id=dtdr.rra_id
			LEFT JOIN data_template_data AS dtd
			ON dtdr.data_template_data_id=dtd.id
			GROUP BY rra.id, dtd.data_template_id, dtd.local_data_id
		) AS rs
		$sql_where
		GROUP BY rs.id
		$sql_having
		ORDER BY " . $_REQUEST['sort_column'] . ' ' . $_REQUEST['sort_direction']);

    $nav = html_nav_bar('rra.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 8, 'RRAs', 'page', 'main');

    print $nav;

	$display_text = array(
		'name' => array('display' => 'Round Robin Archive Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name of this Round Robin Archive definition.'),
		"nosort" => array('display' => 'Deletable', 'align' => 'right', 'sort' => '', 'tip' => 'RRAs in use can not be Deleted.  In use is defined as being referenced either by a Data Source or a Data Template.'),
		'steps' => array('display' => 'Steps', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Rows inserted into the RRDfile over the polling interval before aggregation occurs.  A value of 1 means that this will be the first RRA that will be inserted into.'),
		'rows' => array('display' => 'Rows', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Rows in the RRDfile.  The more rows in the RRDfile, the longer data will be retained for.'),
		'timespan' => array('display' => 'Timespan', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'An arbitrary number as a way for Cacti to determine which RRA to use for Graphing operations.  However, RRDtool can override this value.'),
		'data_sources' => array('display' => 'Data Sources Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Data Sources using this RRA definition'),
		'templates' => array('display' => 'Templates Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Data Templates using this RRA definition')
	);

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	$i = 0;
	if (sizeof($rras) > 0) {
		foreach ($rras as $rra) {
			if ($rra['data_sources'] == 0 && $rra['templates'] == 0) {
				$disabled = false;
			}else{
				$disabled = true;
			}

			form_alternate_row('line' . $rra['id'], false, $disabled);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('rra.php?action=edit&id=' . $rra['id']) . "'>" . (strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($rra['name'])) : htmlspecialchars($rra['name'])) . '</a>', $rra['id']);
			form_selectable_cell($disabled ? 'No':'Yes', $rra['id'], '', 'text-align:right');
			form_selectable_cell(number_format($rra['steps']), $rra['id'], '', 'text-align:right');
			form_selectable_cell(number_format($rra['rows']), $rra['id'], '', 'text-align:right');
			form_selectable_cell(number_format($rra['timespan']), $rra['id'], '', 'text-align:right');
			form_selectable_cell(number_format($rra['data_sources']), $rra['id'], '', 'text-align:right');
			form_selectable_cell(number_format($rra['templates']), $rra['id'], '', 'text-align:right');
			form_checkbox_cell($rra['name'], $rra['id'], $disabled);
			form_end_row();
		}

		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='7'><em>No Round Robin Archives Found</em></td></tr>\n";
	}
	html_end_box();

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($rra_actions);

	form_end();
}
