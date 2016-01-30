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

$color_actions = array('1' => 'Delete');

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		form_save();

		break;
	case 'remove':
		color_remove();

		header ('Location: color.php');
		break;
	case 'edit':
		top_header();

		color_edit();

		bottom_footer();
		break;
	case 'export':
		color_export();

		break;
	case 'import':
		top_header();

		color_import();

		bottom_footer();
		break;
	default:
		top_header();

		color();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST['save_component_color'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		/* ==================================================== */

		$save['id']        = $_POST['id'];

		if (!isset($_POST['read_only'])) {
			$save['name']      = $_POST['name'];
			$save['hex']       = form_input_validate($_POST['hex'],  'hex',  '^[a-fA-F0-9]+$' , false, 3);
		}else{
			$save['name']      = $_POST['hidden_name'];
			$save['read_only'] = 'on';
		}

		if (!is_error_message()) {
			$color_id = sql_save($save, 'colors');

			if ($color_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: color.php?header=false&action=edit&id=' . (empty($color_id) ? $_POST['id'] : $color_id));
		}else{
			header('Location: color.php?header=false');
		}
	}elseif (isset($_POST['save_component_import'])) {
		if (($_FILES['import_file']['tmp_name'] != 'none') && ($_FILES['import_file']['tmp_name'] != '')) {
            $csv_data = file($_FILES['import_file']['tmp_name']);
			$debug_data = color_import_processor($csv_data);

			if (sizeof($debug_data)) {
				$_SESSION['import_debug_info'] = $debug_data;
			}

			header('Location: color.php?action=import');
		}
	}
}

/* -----------------------
    Color Functions
   ----------------------- */

function color_import_processor(&$colors) {
	$i      = 0;
	$hexcol = 0;
	$return_array = array();

	if (sizeof($colors)) {
	foreach($colors as $color_line) {
		/* parse line */
		$line_array = explode(',', $color_line);

		/* header row */
		if ($i == 0) {
			$save_order = '(';
			$j = 0;
			$first_column = TRUE;
			$required = 0;
			$update_suffix = '';

			if (sizeof($line_array)) {
			foreach($line_array as $line_item) {
				$line_item = trim(str_replace("'", '', $line_item));
				$line_item = trim(str_replace('"', '', $line_item));

				switch ($line_item) {
					case 'hex':
						$hexcol = $j;
					case 'name':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$save_order .= $line_item;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						$required++;

						break;
					default:
						/* ignore unknown columns */
				}

				$j++;
			}
			}

			$save_order .= ')';

			if ($required >= 2) {
				array_push($return_array, '<b>HEADER LINE PROCESSED OK</b>:  <br>Columns found where: ' . $save_order . '<br>');
			}else{
				array_push($return_array, '<b>HEADER LINE PROCESSING ERROR</b>: Missing required field <br>Columns found where:' . $save_order . '<br>');
				break;
			}
		}else{
			$save_value = '(';
			$j = 0;
			$first_column = TRUE;
			$sql_where = '';

			if (sizeof($line_array)) {
			foreach($line_array as $line_item) {
				if (in_array($j, $insert_columns)) {
					$line_item = trim(str_replace("'", '', $line_item));
					$line_item = trim(str_replace('"', '', $line_item));

					if (!$first_column) {
						$save_value .= ',';
					}else{
						$first_column = FALSE;
					}

					$save_value .= "'" . $line_item . "'";

					if ($j == $hexcol) {
						$sql_where = "WHERE hex='$line_item'";
					}
				}

				$j++;
			}
			}

			$save_value .= ')';

			if ($j > 0) {
				if (isset($_POST['allow_update'])) {
					$sql_execute = 'INSERT INTO colors ' . $save_order .
						' VALUES ' . $save_value . $update_suffix;

					if (db_execute($sql_execute)) {
						array_push($return_array,"INSERT SUCCEEDED: $save_value");
					}else{
						array_push($return_array,"INSERT FAILED: $save_value");
					}
				}else{
					/* perform check to see if the row exists */
					$existing_row = db_fetch_row("SELECT * FROM colors $sql_where");

					if (sizeof($existing_row)) {
						array_push($return_array,"<strong>INSERT SKIPPED, EXISTING:</strong> $save_value");
					}else{
						$sql_execute = 'INSERT INTO colors ' . $save_order .
							' VALUES ' . $save_value;

						if (db_execute($sql_execute)) {
							array_push($return_array,"INSERT SUCCEEDED: $save_value");
						}else{
							array_push($return_array,"INSERT FAILED: $save_value");
						}
					}
				}
			}
		}

		$i++;
	}
	}

	return $return_array;
}

function color_import() {
	print "<form method='post' action='color.php?action=import' enctype='multipart/form-data'>\n";

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box('Import Results', '100%', '', '3', 'center', '');

		print "<tr class='even'><td>
			<p class='textArea'>Cacti has imported the following items:</p>
		</td></tr>\n";

		if (sizeof($_SESSION['import_debug_info'])) {
			foreach($_SESSION['import_debug_info'] as $import_result) {
				print "<tr class='even'><td>" . $import_result . "</td></tr>\n";
			}
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box('Import Colors', '100%', '', '3', 'center', '');

	form_alternate_row();?>
		<td width='50%'><font class='textEditTitle'>Import Colors from Local File</font><br>
			Please specify the location of the CSV file containing your Color information.
		</td>
		<td align='left'>
			<div>
				<label class='import_label' for='import_file'>Select a File</label>
				<input class='import_button' type='file' id='import_file'>
				<span class='import_text'></span>
			</div>
		</td>
	</tr><?php
	form_alternate_row();?>
		<td width='50%'><font class='textEditTitle'>Overwrite Existing Data?</font><br>
			Should the import process be allowed to overwrite existing data?  Please note, this does not mean delete old rows, only update duplicate rows.
		</td>
		<td align='left'>
			<input type='checkbox' name='allow_update' id='allow_update'>Allow Existing Rows to be Updated?
		</td><?php

	html_end_box(FALSE);

	html_start_box('Required File Format Notes', '100%', '', '3', 'center', '');

	form_alternate_row();?>
		<td><strong>The file must contain a header row with the following column headings.</strong>
			<br><br>
			<strong>name</strong> - The Color Name<br>
			<strong>hex</strong> - The Hex Value<br>
			<br>
		</td>
	</tr><?php

	form_hidden_box('save_component_import','1','');

	html_end_box();

	?>
	<table style='width:100%'><tr><td class='saveRow'>
		<input type='hidden' name='action' value='save'>
		<input id='import' type='submit' value='Import'>
		</td></tr></table>
	</form>
	<?php
}

function color_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	db_execute_prepared('DELETE FROM colors WHERE id = ?', array(get_request_var('id')));
}

function color_edit() {
	global $fields_color_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if (!empty($_REQUEST['id'])) {
		$color = db_fetch_row_prepared('SELECT * FROM colors WHERE id = ?', array(get_request_var('id')));
		$header_label = '[edit: ' . $color['hex'] . ']';
	}else{
		$header_label = '[new]';
	}

	form_start('color.php', 'color');

	html_start_box("Colors $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_color_edit, (isset($color) ? $color : array()))
		)
	);

	html_end_box();

	form_save_button('color.php');

	?>
	<script type='text/javascript'>
	$(function() {
		checkReadonly();

		$('#hex').colorpicker().css({'width':'60px'});
		$('#read_only').click(function() {
			checkReadonly();
		});

		$('#name').keyup(function() {
			$('#hidden_name').val($(this).val());
		});

		function checkReadonly() {
			if ($('#read_only').is(':checked') || $('#read_only').val() == 'on') {
				$('#name').prop('disabled', true);
				$('#hex').prop('disabled', true);
			}else{
				$('#name').prop('disabled', false);
				$('#hex').prop('disabled', false);
			}
		}
	});
	</script>
	<?php
}

function color() {
	global $color_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('page'));
	input_validate_input_number(get_request_var('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up named string */
	if (isset($_REQUEST['named'])) {
		$_REQUEST['named'] = sanitize_search_string(get_request_var('named'));
	}

	/* clean up has_graph string */
	if (isset($_REQUEST['has_graphs'])) {
		$_REQUEST['has_graphs'] = sanitize_search_string(get_request_var('has_graphs'));
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
		kill_session_var('sess_color_current_page');
		kill_session_var('sess_color_filter');
		kill_session_var('sess_color_has_graphs');
		kill_session_var('sess_color_named');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_color_sort_column');
		kill_session_var('sess_color_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['has_graphs']);
		unset($_REQUEST['named']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('rows',       'sess_default_rows');
		$changed += check_changed('has_graphs', 'sess_color_has_graphs');
		$changed += check_changed('named', 'sess_color_named');
		$changed += check_changed('filter',     'sess_color_filter');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

    /* remember these search fields in session vars so we don't have to keep passing them around */
    load_current_session_value('page', 'sess_color_current_page', '1');
    load_current_session_value('filter', 'sess_color_filter', '');
    load_current_session_value('has_graphs', 'sess_color_has_graphs', 'true');
    load_current_session_value('named', 'sess_color_named', '');
    load_current_session_value('sort_column', 'sess_color_sort_column', 'name');
    load_current_session_value('sort_direction', 'sess_color_sort_direction', 'ASC');
    load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	html_start_box('Colors', '100%', '', '3', 'center', 'color.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_color' action='color.php'>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						Colors
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
						<input type="checkbox" id='named' <?php print ($_REQUEST['named'] == 'true' ? 'checked':'');?>>
					</td>
					<td>
						<label for='named'>Named Colors</label>
					</td>
					<td>
						<input type="checkbox" id='has_graphs' <?php print ($_REQUEST['has_graphs'] == 'true' ? 'checked':'');?>>
					</td>
					<td>
						<label for='has_graphs'>Has Graphs</label>
					</td>
					<td>
						<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' id='clear' value='Clear' title='Clear Filters'>
					</td>
					<td>
						<input type='button' id='import' value='Import' title='Import Colors'>
					</td>
					<td>
						<input type='button' id='export' value='Export' title='Export Colors'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
			<script type='text/javascript'>
			function applyFilter() {
				strURL = 'color.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&has_graphs='+$('#has_graphs').is(':checked')+'&named='+$('#named').is(':checked')+'&header=false';
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'color.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#has_graphs').click(function() {
					applyFilter();
				});

				$('#named').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_color').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});

				$('#import').click(function(event) {
					strURL = 'color.php?action=import&header=false';
					loadPageNoHeader(strURL);
				});
			});
			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if ($_REQUEST['filter'] != '') {
		$sql_where = "WHERE (name LIKE '%" . get_request_var('filter') . "%' 
			OR hex LIKE '%" .  get_request_var('filter') . "%')";
	}else{
		$sql_where = '';
	}

	if ($_REQUEST['named'] == 'true') {
		$sql_where .= (strlen($sql_where) ? ' AND':'WHERE') . " read_only='on'";
	}

	if ($_REQUEST['has_graphs'] == 'true') {
		$sql_having = 'HAVING graphs>0 OR templates>0';
	}else{
		$sql_having = '';
	}

	form_start('color.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(color)
		FROM (
			SELECT
			c.id AS color,
			SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs,
			SUM(CASE WHEN local_graph_id=0 THEN 1 ELSE 0 END) AS templates
			FROM colors AS c
			LEFT JOIN (
				SELECT color_id, graph_template_id, local_graph_id 
				FROM graph_templates_item 
				WHERE color_id>0
			) AS gti
			ON gti.color_id=c.id
			$sql_where
			GROUP BY c.id
			$sql_having
		) AS rs");

	$colors = db_fetch_assoc("SELECT *,
        SUM(CASE WHEN local_graph_id>0 THEN 1 ELSE 0 END) AS graphs,
        SUM(CASE WHEN local_graph_id=0 THEN 1 ELSE 0 END) AS templates
        FROM (
			SELECT c.*, local_graph_id
			FROM colors AS c
			LEFT JOIN (
				SELECT color_id, graph_template_id, local_graph_id 
				FROM graph_templates_item 
				WHERE color_id>0
			) AS gti
			ON c.id=gti.color_id
		) AS rs
		$sql_where
		GROUP BY rs.id
		$sql_having
		ORDER BY " . $_REQUEST['sort_column'] . ' ' . $_REQUEST['sort_direction'] . "
		LIMIT " . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows'));

    $nav = html_nav_bar('color.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 8, 'Colors', 'page', 'main');

    print $nav;

	$display_text = array(
		'hex' => array('display' => 'Hex', 'align' => 'left', 'sort' => 'DESC', 'tip' => 'The Hex Value for this Color.'),
		'name' => array('display' => 'Color Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name of this Color definition.'),
		'read_only' => array('display' => 'Named Color', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'Is this color a named color which are read only.'),
		'nosort1' => array('display' => 'Color', 'align' => 'center', 'sort' => 'DESC', 'tip' => 'The Color as shown on the screen.'),
		'nosort' => array('display' => 'Deletable', 'align' => 'right', 'sort' => '', 'tip' => 'Colors in use can not be Deleted.  In use is defined as being referenced either by a Graph or a Graph Template.'),
		'graphs' => array('display' => 'Graphs', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Graph using this Color.'),
		'templates' => array('display' => 'Templates', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Graph Templates using this Color.')
	);

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	$i = 0;
	if (sizeof($colors) > 0) {
		foreach ($colors as $color) {
			if ($color['graphs'] == 0 && $color['templates'] == 0) {
				$disabled = false;
			}else{
				$disabled = true;
			}

			if ($color['name'] == '') {
				$color['name'] = 'Unnamed #'. $color['hex'];
			}

			form_alternate_row('line' . $color['id'], false, $disabled);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('color.php?action=edit&id=' . $color['id']) . "'>" . $color['hex'] . '</a>', $color['id']);
			form_selectable_cell(strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($color['name'])) : htmlspecialchars($color['name']), $color['id']);
			form_selectable_cell($color['read_only'] == 'on' ? 'Yes':'No', $color['id']);
			form_selectable_cell('', $color['id'], '', 'text-align:right;background-color:#' . $color['hex'] . ';min-width:30%');
			form_selectable_cell($disabled ? 'No':'Yes', $color['id'], '', 'text-align:right');
			form_selectable_cell(number_format($color['graphs']), $color['id'], '', 'text-align:right');
			form_selectable_cell(number_format($color['templates']), $color['id'], '', 'text-align:right');
			form_checkbox_cell($color['name'], $color['id'], $disabled);
			form_end_row();
		}

		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='7'><em>No Colors Found</em></td></tr>\n";
	}
	html_end_box();

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($color_actions, 1);

	form_end();
}

