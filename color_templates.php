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

include_once('./include/auth.php');

define('MAX_DISPLAY_PAGES', 21);

$aggregate_actions = array(
	1 => 'Delete',
	2 => 'Duplicate'
	);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		aggregate_color_form_save();

		break;
	case 'actions':
		aggregate_color_form_actions();

		break;
	case 'template_edit':
		top_header();
		aggregate_color_template_edit();
		bottom_footer();

		break;
	default:
		top_header();
		aggregate_color_template();
		bottom_footer();

		break;
}

/** draw_color_template_items_list 	- draws a nicely formatted list of color items for display
 *   								  on an edit form
 * @param array $item_list 			- an array representing the list of color items. this array should
 *   								  come directly from the output of db_fetch_assoc()
 * @param string $filename 			- the filename to use when referencing any external url
 * @param string $url_data 			- any extra GET url information to pass on when referencing any
 *   								  external url
 * @param bool $disable_controls 	- whether to hide all edit/delete functionality on this form
 */
function draw_color_template_items_list($item_list, $filename, $url_data, $disable_controls) {
	global $config;
	global $struct_color_template_item;

	print "<tr class='tableHeader'>\n";
		DrawMatrixHeaderItem('Color Item', '' , 1);
		DrawMatrixHeaderItem('Seq', '', 1);
		DrawMatrixHeaderItem('Item Color', '', 2);
		DrawMatrixHeaderItem('Actions', '', 2);
	print '</tr>';

	$group_counter = 0; $_graph_type_name = ''; $i = 0;

	if (sizeof($item_list) > 0) {
	foreach ($item_list as $item) {
		/* color grouping display logic */
		$this_row_style = ''; $use_custom_row_color = false; $hard_return = '';

		/* alternating row color */
		form_alternate_row();

		# print item no.
		print '<td>';
		if ($disable_controls == false) { print "<a href='" . htmlspecialchars($filename . '?action=item_edit&color_template_item_id=' . $item['color_template_item_id'] . "&$url_data") . "'>"; }
		print '<strong>Item # ' . ($i+1) . '</strong>';
		if ($disable_controls == false) { print '</a>'; }
		print "</td>\n";

		# print function

		print "<td style='$this_row_style'>" . $item['sequence'] . "</td>\n";
		print '<td' . ((isset($item['hex'])) ? " bgcolor='#" . $item['hex'] . "'" : '') . " width='5%'>&nbsp;</td>\n";
		print "<td style='$this_row_style'>" . $item['hex'] . "</td>\n";

		if ($disable_controls == false) {
			print "<td><a href='" . htmlspecialchars($filename . "?action=item_movedown&color_template_item_id=" . $item['color_template_item_id'] . "&$url_data") . "'><img src='images/move_down.gif' border='0' alt='Move Down'></a>
					<a href='" . htmlspecialchars($filename . "?action=item_moveup&color_template_item_id=" . $item['color_template_item_id'] . "&$url_data") . "'><img src='images/move_up.gif' border='0' alt='Move Up'></a></td>\n";
			print "<td align='right'><a href='" . htmlspecialchars($filename . "?action=item_remove&color_template_item_id=" . $item['color_template_item_id'] . "&$url_data") . "'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a></td>\n";
		}

		print '</tr>';

		$i++;
	}
	}else{
		print "<tr><td colspan='7'><em>No Items</em></td></tr>";
	}
}

/* --------------------------
    The Save Function
   -------------------------- */
/**
 * aggregate_color_form_save	the save function
 */
function aggregate_color_form_save() {
	if (isset($_POST['save_component_color'])) {
		if (isset($_POST['color_template_id'])) {
			$save1['color_template_id'] = $_POST['color_template_id'];
		} else {
			$save1['color_template_id'] = 0;
		}
		$save1['name'] = form_input_validate(htmlspecialchars($_POST['name']), 'name', '', false, 3);
		if (read_config_option('log_verbosity', TRUE) == POLLER_VERBOSITY_DEBUG) {
			aggregate_log('AGGREGATE   Saved ID: ' . $save1['color_template_id'] . ' Name: ' . $save1['name'], FALSE);
		}

		if (!is_error_message()) {
			$color_template_id = sql_save($save1, 'color_templates', 'color_template_id');
			if (read_config_option('log_verbosity', TRUE) == POLLER_VERBOSITY_DEBUG) {
				aggregate_log('AGGREGATE   Saved ID: ' . $color_template_id, FALSE);
			}

			if ($color_template_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
	}

	if ((is_error_message()) || (empty($_POST['color_template_id']))) {
		header('Location: color_templates.php?action=template_edit&color_template_id=' . (empty($color_template_id) ? $_POST['color_template_id'] : $color_template_id));
	}else{
		header('Location: color_templates.php');
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */
/**
 * aggregate_color_form_actions		the action function
 */
function aggregate_color_form_actions() {
	global $aggregate_actions, $config;
	include_once($config['base_path'] . '/lib/api_aggregate.php');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == '1') { /* delete */
			db_execute('DELETE FROM color_templates WHERE ' . array_to_sql_or($selected_items, 'color_template_id'));
			db_execute('DELETE FROM color_template_items WHERE ' . array_to_sql_or($selected_items, 'color_template_id'));
		}elseif ($_POST['drp_action'] == '2') { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				duplicate_color_template($selected_items[$i], $_POST['title_format']);
			}
		}

		header('Location: color_templates.php');
		exit;
	}

	/* setup some variables */
	$color_list = ''; $i = 0;

	/* loop through each of the color templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$color_list .= '<li>' . db_fetch_cell('SELECT name FROM color_templates WHERE color_template_id=' . $matches[1]) . '</li>';
			$color_array[] = $matches[1];
		}
	}

	top_header();

	print "<form action='color_templates.php' method='post'>\n";
	html_start_box('<strong>' . $aggregate_actions{$_POST['drp_action']} . '</strong>', '60%', '', '3', 'center', '');

	if (isset($color_array) && sizeof($color_array)) {
	if ($_POST['drp_action'] == '1') { /* delete */
		print "	<tr>
				<td class='textArea'>
					<p>Are you sure you want to delete the following color templates?</p>
					<p><ul>$color_list</ul></p>
				</td>
			</tr>\n";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete Color Template(s)'>";
	}elseif ($_POST['drp_action'] == '2') { /* duplicate */
		print "	<tr>
				<td class='textArea'>
					<p>When you click save, the following color templates will be duplicated. You can
					optionally change the title format for the new color templates.</p>
					<p><ul>$color_list</ul></p>
					<p><strong>Title Format:</strong><br>"; form_text_box('title_format', '<template_title> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Color Template(s)'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one Color Template.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($color_array) ? serialize($color_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
				$save_html
			</td>
		</tr>\n";

	html_end_box();

	print "</form>\n";

	bottom_footer();
}

/**
 * aggregate_color_item		show all color template items
 */
function aggregate_color_item() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('color_template_id'));
	/* ==================================================== */

	if (empty($_GET['color_template_id'])) {
		$template_item_list = array();

		$header_label = '[new]';
	}else{
		$template_item_list = db_fetch_assoc('SELECT
			cti.color_template_item_id, cti.sequence, colors.hex
			FROM color_template_items AS cti
			LEFT JOIN colors 
			ON cti.color_id=colors.id
			WHERE cti.color_template_id=' . $_GET['color_template_id'] . '
			ORDER BY cti.sequence ASC');

		$header_label = '[edit: ' . db_fetch_cell('SELECT name FROM color_templates WHERE color_template_id=' . $_GET['color_template_id']) . ']';
	}

	html_start_box("<strong>Color Template Items</strong> $header_label", '100%', '', '3', 'center', 'color_templates_items.php?action=item_edit&color_template_id=' . htmlspecialchars($_GET['color_template_id']));

	draw_color_template_items_list($template_item_list, 'color_templates_items.php', 'color_template_id=' . htmlspecialchars($_GET['color_template_id']), false);

	html_end_box();
}

/* ----------------------------
    template - Color Templates
   ---------------------------- */
/**
 * aggregate_color_template_edit	edit the color template
 */
function aggregate_color_template_edit() {
	global $config, $image_types, $fields_color_template_template_edit, $struct_aggregate;
	include_once($config['base_path'] . '/lib/api_aggregate.php');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('color_template_id'));
	/* ==================================================== */
	if (!empty($_GET['color_template_id'])) {
		$template = db_fetch_row('SELECT * FROM color_templates WHERE color_template_id=' . $_GET['color_template_id']);
		$header_label = '[edit: ' . $template['name'] . ']';
	}else{
		$header_label = '[new]';
	}

	print ('<form name="color_template_edit" action="color_templates.php" method="POST">');
	html_start_box("<strong>Color Template</strong> $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_color_template_template_edit, (isset($template) ? $template : array()))
		));

	html_end_box();
	form_hidden_box('color_template_id', (isset($template['color_template_id']) ? $template['color_template_id'] : '0'), '');
	form_hidden_box('save_component_color', '1', '');

	/* color item list goes here */
	if (!empty($_GET['color_template_id'])) {
		aggregate_color_item();
	}

	form_save_button('color_templates.php', 'return', 'color_template_id');
}


/**
 * aggregate_color_template		maintain color templates
 */
function aggregate_color_template() {
	global $aggregate_actions, $item_rows, $config;
	include_once($config['base_path'] . '/lib/api_aggregate.php');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up search string */
	if (isset($_REQUEST['has_graphs'])) {
		$_REQUEST['has_graphs'] = sanitize_search_string(get_request_var('has_graphs'));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	} else {
		$_REQUEST['sort_column'] = 'name';
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	} else {
		$_REQUEST['sort_direction'] = 'ASC';
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_color_template_current_page');
		kill_session_var('sess_color_template_filter');
		kill_session_var('sess_color_template_has_graphs');
		kill_session_var('sess_color_template_sort_column');
		kill_session_var('sess_color_template_sort_direction');
		kill_session_var('sess_color_template_rows');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['has_graphs']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['sess_color_template_rows']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_color_template_current_page', '1');
	load_current_session_value('filter', 'sess_color_template_filter', '');
	load_current_session_value('has_graphs', 'sess_color_template_has_graphs', '');
	load_current_session_value('sort_column', 'sess_color_template_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_color_template_sort_direction', 'ASC');
	load_current_session_value('rows', 'sess_color_template_rows', read_config_option('num_rows_table'));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}

	print ('<form id="form_template" action="color_templates.php" method="get">');

	html_start_box('<strong>Color Templates</strong>', '100%', '', '3', 'center', 'color_templates.php?action=template_edit');

	$filter_html = '<tr class="even">
					<td>
					<table cellpadding="2" cellspacing="0">
						<tr>
							<td width="55">
								Search
							</td>
							<td>
								<input type="text" id="filter" size="25" value="' . get_request_var_request('filter') . '">
							</td>
							<td>
								Color Templates
							</td>
							<td width="1">
								<select id="rows" onChange="applyFilter()">
								<option value="-1"';
	if (get_request_var_request('rows') == '-1') {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Default</option>';
	if (sizeof($item_rows) > 0) {
		foreach ($item_rows as $key => $value) {
			$filter_html .= "<option value='" . $key . "'";
			if (get_request_var_request('rows') == $key) {
				$filter_html .= ' selected';
			}
			$filter_html .= '>' . $value . "</option>\n";
		}
	}
	$filter_html .= '					</select>
							</td>
							<td>
								<input type="checkbox" id="has_graphs" ' . ($_REQUEST['has_graphs'] == 'true' ? 'checked':'') . ' onChange="applyFilter()">
							</td>
							<td>
								<label for="has_graphs" style="white-space:nowrap;">Has Graphs</label>
							</td>
							<td>
								<input type="button" id="refresh" value="Go">
							</td>
							<td>
								<input type="button" id="clear" value="Clear">
							</td>
						</tr>
					</table>
					</td>
					<td><input type="hidden" id="page" value="' . $_REQUEST['page'] . '"></td>
				</tr>';

	print $filter_html;

	html_end_box();

	print "</form>\n";

	/* form the 'where' clause for our main sql query */
	$sql_where = '';
	if ($_REQUEST['filter'] != '') {
		$sql_where = "WHERE (ct.name LIKE '%%" . $_REQUEST['filter'] . "%%')";
	}

	if ($_REQUEST['has_graphs'] == 'true') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' (templates>0 OR graphs>0)';
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='color_templates.php'>\n";
	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(ct.color_template_id)
		FROM color_templates AS ct
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS templates 
			FROM aggregate_graph_templates_item 
			GROUP BY color_template
		) AS templates
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS graphs
			FROM aggregate_graphs_graph_item
			GROUP BY color_template
		) AS graphs
		ON ct.color_template_id=graphs.color_template
		ON ct.color_template_id=templates.color_template
		$sql_where");

	$template_list = db_fetch_assoc("SELECT
		ct.color_template_id, ct.name, templates.templates, graphs.graphs
		FROM color_templates AS ct
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS templates 
			FROM aggregate_graph_templates_item 
			GROUP BY color_template
		) AS templates
		ON ct.color_template_id=templates.color_template
		LEFT JOIN (
			SELECT color_template, COUNT(*) AS graphs
			FROM aggregate_graphs_graph_item
			GROUP BY color_template
		) AS graphs
		ON ct.color_template_id=graphs.color_template
		$sql_where
		ORDER BY " . $_REQUEST['sort_column'] . ' ' . $_REQUEST['sort_direction'] .
		' LIMIT ' . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows'));

	$nav = html_nav_bar('color_templates.php', MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 5, 'Color Templates', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('Template Title', 'ASC'),
		'nosort' => array('display' => 'Deletable', 'align' => 'right', 'tip' => 'Color Templates that are in use can not be Deleted.  In use is defined as being referenced by an Aggregate Template.'),
		'graphs' => array('display' => 'Graphs', 'align' => 'right', 'sort' => 'DESC'),
		'templates' => array('display' => 'Templates', 'align' => 'right', 'sort' => 'DESC')
	);

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	if (sizeof($template_list) > 0) {
		foreach ($template_list as $template) {
			if ($template['templates'] > 0) {
				$disabled = true;
			}else{
				$disabled = false;
			}

			form_alternate_row('line' . $template['color_template_id'], true);

			form_selectable_cell("<a style='white-space:nowrap;' class='linkEditMain' href='" . htmlspecialchars('color_templates.php?action=template_edit&color_template_id=' . $template['color_template_id'] . '&page=1') . "'>" . (get_request_var_request('filter') != '' ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($template['name'])) : htmlspecialchars($template['name'])) . '</a>', $template['color_template_id']);
			form_selectable_cell($disabled ? 'No':'Yes', $template['color_template_id'], '', 'text-align:right');
            form_selectable_cell(number_format($template['graphs']), $template['color_template_id'], '', 'text-align:right;');
            form_selectable_cell(number_format($template['templates']), $template['color_template_id'], '', 'text-align:right;');
			form_checkbox_cell($template['name'], $template['color_template_id'], $disabled);
			form_end_row();
		}
		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Color Templates</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($aggregate_actions);

	print "</form>\n";

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL = 'color_templates.php';
		strURL = strURL + '?rows=' + $('#rows').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&has_graphs=' + $('#has_graphs').is(':checked');
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'color_templates.php?clear=1&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#filter').change(function() {
			applyFilter();
		});

		$('#form_template').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php
}

