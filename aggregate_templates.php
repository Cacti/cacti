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

include_once('./include/auth.php');
include_once('./lib/api_aggregate.php');

define('MAX_DISPLAY_PAGES', 21);

$aggregate_actions = array(
	1 => 'Delete'
);

/* set default action */
if (!isset($_REQUEST['action'])) $_REQUEST['action'] = '';

if ($_REQUEST['action'] == 'save' && $_REQUEST['id'] == 0 && isset($_REQUEST['_graph_template_id']) && $_REQUEST['_graph_template_id'] == 0) {
	$_REQUEST['action'] = 'edit';
}

switch ($_REQUEST['action']) {
	case 'save':
		aggregate_form_save();
		break;
	case 'actions':
		aggregate_form_actions();
		break;
	case 'edit':
		top_header();
		aggregate_template_edit();
		bottom_footer();
		break;
	default:
		top_header();
		aggregate_template();
		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */
/**
 * aggregate_form_save	the save function
 */
function aggregate_form_save() {
	/* make sure we are saving aggregate template */
	if (!isset($_POST['save_component_template'])) {
		header('Location: aggregate_templates.php?header=false&action=edit&id=' . get_request_var_post('id'));
		return null;
	}

	$save1 = array();

	/* updating existing template or creating a new one? */
	if (isset(get_request_var_post('id')) && $_POST['id'] > 0) {
		$is_new = false;
		$save1['id'] = get_request_var_post('id');
	} else {
		$is_new = true;
		$save1['id'] = 0;
	}

	/* set some defaults for possibly disabled values */
	if (!isset(get_request_var_post('total')))        $_POST['total']        = 0;
	if (!isset(get_request_var_post('total_type')))   $_POST['total_type']   = 0;
	if (!isset(get_request_var_post('order_type')))   $_POST['order_type']   = 0;
	if (!isset(get_request_var_post('total_prefix'))) $_POST['total_prefix'] = '';

	/* populate aggregate template save array and validate posted values*/
	$save1['name']              = form_input_validate(get_request_var_post('name'), 'name', '', false, 3);
	$save1['graph_template_id'] = $_POST['_graph_template_id'];
	$save1['gprint_prefix']     = form_input_validate(get_request_var_post('gprint_prefix'), 'gprint_prefix', '', true, 3);
	$save1['graph_type']        = form_input_validate(get_request_var_post('graph_type'), 'graph_type', '', false, 3);
	$save1['total']             = form_input_validate(get_request_var_post('total'), 'total', '', false, 3);
	$save1['total_type']        = form_input_validate(get_request_var_post('total_type'), 'total_type', '', false, 3);
	$save1['total_prefix']      = form_input_validate(get_request_var_post('total_prefix'), 'total_prefix', '', true, 3);
	$save1['order_type']        = form_input_validate(get_request_var_post('order_type'), 'order_type', '', false, 3);
	$save1['user_id']           = $_SESSION['sess_user_id'];

	/* form validation failed */
	if (is_error_message()) {
		header('Location: aggregate_templates.php?header=false&action=edit&id=' . get_request_var_post('id'));
		return null;
	}

	cacti_log('AGGREGATE GRAPH TEMPLATE Saved ID: ' . $save1['id'] . ' Name: ' . $save1['name'], FALSE, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	/* do a quick comparison to see if anything changed */
	if ($is_new == false) {
		$old = db_fetch_row('SELECT * FROM aggregate_graph_templates WHERE id=' . $save1['id']);
		$save_me = 0;

		$save_me += ($old['gprint_prefix'] != $save1['gprint_prefix']);
		$save_me += ($old['graph_type']    != $save1['graph_type']);
		$save_me += ($old['total']         != $save1['total']);
		$save_me += ($old['total_prefix']  != $save1['total_prefix']);
		$save_me += ($old['order_type']    != $save1['order_type']);
	}else{
		$save_me = 1;
	}

	if ($save_me) {
		$id = sql_save($save1, 'aggregate_graph_templates', 'id');

		/* update children of the template */
		db_execute("UPDATE aggregate_graphs SET
			gprint_prefix='" . $save1['gprint_prefix'] . "',
			graph_type="     . $save1['graph_type'] . ",
			total="          . $save1['total'] . ",
			total_prefix='"  . $save1['total_prefix'] . "',
			order_type="     . $save1['order_type'] . "
			WHERE aggregate_template_id=$id
			AND template_propogation='on'");

		cacti_log('AGGREGATE GRAPH TEMPLATE Saved ID: ' . $id, FALSE, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);
	}else{
		$id = $save1['id'];
	}

	if (!$id) {
		raise_message(2);
		header('Location: aggregate_templates.php?header=false&action=edit&id=' . get_request_var_post('id'));
		return null;
	}

	/* save extra graph parameters */
	/* validate posted graph params */
	$params_new = aggregate_validate_graph_params($_POST, true);
	$params_new['aggregate_template_id'] = $id;
	/* compare new graph param values with existing in DB.
	 * We need to know if there were changes so we only
	 * rebuild existing graphs if needed. */
	$params_changed = false;
	$params_old = db_fetch_row('SELECT * FROM aggregate_graph_templates_graph WHERE aggregate_template_id='.$id);
	if (!empty($params_old)) {
		foreach ($params_old as $field => $value_old) {
			if (isset($params_new[$field]) && $params_new[$field] != $value_old) {
				$params_changed = true;
				break;
			}
		}
	} else {
		$params_changed = true;
	}


	/* save only if all posted form fields passed validation */
	if (!is_error_message())
		sql_save($params_new, 'aggregate_graph_templates_graph', 'aggregate_template_id', false);

	/* save the template items now */
	/* get existing item ids and sequences from graph template */
	$graph_templates_items = array_rekey(
		db_fetch_assoc('SELECT id, sequence FROM graph_templates_item WHERE local_graph_id=0 AND graph_template_id=' . $save1['graph_template_id']),
		'id', array('sequence')
	);
	/* get existing aggregate template items */
	$aggregate_template_items_old = array_rekey(
		db_fetch_assoc('SELECT * FROM aggregate_graph_templates_item WHERE aggregate_template_id='.$id),
		'graph_templates_item_id', array('sequence', 'color_template', 't_graph_type_id', 'graph_type_id', 't_cdef_id', 'cdef_id', 'item_skip', 'item_total')
	);

	/* update graph template item values with posted values */
	aggregate_validate_graph_items($_POST, $graph_templates_items);

	$items_changed = false;
	$items_to_save = array();
	foreach($graph_templates_items as $item_id => $data) {
		$item_new = array();
		$item_new['aggregate_template_id'] = $id;
		$item_new['graph_templates_item_id'] = $item_id;

		$item_new['color_template'] = isset($data['color_template']) ? $data['color_template']:-1;
		$item_new['item_skip']      = isset($data['item_skip']) ? 'on':'';
		$item_new['item_total']     = isset($data['item_total']) ? 'on':'';
		$item_new['sequence']       = isset($data['sequence']) ? $data['sequence']:-1;

		/* compare with old item to see if we need to push out. */
		if (!isset($aggregate_template_items_old[$item_id])) {
			/* this item does not yet exist */
			$items_changed = true;
		} else {
			// fill in missing fields with db values
			$item_new = array_merge($aggregate_template_items_old[$item_id], $item_new);
			/* compare data from user to data from DB */
			foreach ($data as $field => $new_value) {
				if ($aggregate_template_items_old[$item_id][$field] != $new_value)
					$items_changed = true;
			}
		}
		$items_to_save[] = $item_new;
	}

	if ($items_changed) {
		aggregate_graph_items_save($items_to_save, 'aggregate_graph_templates_item');
	}

	if ($save_me || $params_changed || $items_changed) {
		push_out_aggregates($id);
	}

	raise_message(1);

	header('Location: aggregate_templates.php?header=false&action=edit&id=' . (empty($id) ? get_request_var_post('id') : $id));
}


function aggregate_get_graph_items($table, $id) {

}


/* ------------------------
    The 'actions' function
   ------------------------ */
/**
 * aggregate_form_actions		the action function
 */
function aggregate_form_actions() {
	global $aggregate_actions, $config;
	include_once($config['base_path'] . '/api_aggregate.php');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset(get_request_var_post('selected_items'))) {
		$selected_items = sanitize_unserialize_selected_items(get_request_var_post('selected_items'));

		if ($selected_items != false) {
			if (get_request_var_post('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM aggregate_graph_templates WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM aggregate_graph_templates_item WHERE ' . array_to_sql_or($selected_items, 'aggregate_template_id'));
				db_execute('DELETE FROM aggregate_graph_templates_graph WHERE ' . array_to_sql_or($selected_items, 'aggregate_template_id'));
				db_execute("UPDATE aggregate_graphs SET aggregate_template_id=0, template_propogation='' WHERE " . array_to_sql_or($selected_items, 'aggregate_template_id'));
			}
		}else{
		}

		header('Location: aggregate_templates.php?header=false');
		exit;
	}

	/* setup some variables */
	$aggregate_list = ''; $i = 0;

	/* loop through each of the color templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$aggregate_list .= '<li>' . db_fetch_cell('SELECT name FROM aggregate_graph_templates WHERE id=' . $matches[1]) . '</li>';
			$aggregate_array[] = $matches[1];
		}
	}

	top_header();

	form_start('aggregate_templates.php');

	html_start_box($aggregate_actions{get_request_var_post('drp_action')}, '60%', '', '3', 'center', '');

	if (isset($aggregate_array) && sizeof($aggregate_array)) {
		if (get_request_var_post('drp_action') == '1') { /* delete */
			print "<tr>
					<td class='textArea'>
						<p>Are you sure you want to Delete the following Aggregate Graph Template(s)?</p>
						<p><ul>$aggregate_list</ul></p>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Color Template(s)'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one Aggregate Graph Template.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($aggregate_array) ? serialize($aggregate_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var_post('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/**
 * aggregate_template_edit	edit the color template
 */
function aggregate_template_edit() {
	global $image_types, $struct_aggregate_template;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if (!empty($_GET['id'])) {
		$template = db_fetch_row('SELECT * FROM aggregate_graph_templates WHERE id=' . $_GET['id']);
		$header_label = '[edit: ' . $template['name'] . ']';
	}else{
		$header_label = '[new]';
	}

	/* populate the graph template id if it's set */
	if (isset(get_request_var_post('graph_template_id')) && !isset($template)) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var('graph_template_id'));
		/* ==================================================== */
		$template['graph_template_id'] = get_request_var_post('graph_template_id');
		$template['id']                = 0;
	}

	form_start('aggregate_templates.php', 'template_edit');

	html_start_box("Aggregate Template $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($struct_aggregate_template, (isset($template) ? $template : array()))
	));

	html_end_box();

	if (isset($template)) {
		draw_aggregate_graph_items_list(0, $template['graph_template_id'], $template);

		/* Draw Graph Configuration form, so user can override some parameters from graph template */
		draw_aggregate_template_graph_config($template['id'], $template['graph_template_id']);
	}


	form_hidden_box('id', (isset($template['id']) ? $template['id'] : '0'), '0');
	form_hidden_box('save_component_template', '1', '');
	form_save_button('aggregate_templates.php', 'return', 'id');

	?>
	<script type='text/javascript'>
	$().ready(function() {
		if ($('#id').val() == 0) {
			$('[id^="agg_total_"]').prop('checked', true);
		}

		if ($('#graph_template_id').val() == 0) {
			$('#row_name').hide();
			$('#row_spacer1').hide();
			$('#row_gprint_prefix').hide();
			$('#row_graph_type').hide();
			$('#row_total').hide();
			$('#row_total_type').hide();
			$('#row_total_prefix').hide();
			$('#row_order_type').hide();

			$('#graph_template_id').change(function() {
				document.template_edit.submit();
			});

			$('#save_component_template').parent().next('table').css('display', 'none');
		}else{
			$('#graph_template_id').prop('disabled', true);
		}

		$('#total').change(function() {
			changeTotals();
		});

		$('#total_type').change(function() {
			changeTotalsType();
		});

		$('[id^="agg_skip"]').change(function() {
			pieces=$(this).attr('id').split('_');
			total_id='agg_total_'+pieces[2];

			if ($(this).is(':checked')) {
				$('#'+total_id).removeAttr('checked');
			}
		});

		$('[id^="agg_total"]').change(function() {
			pieces=$(this).attr('id').split('_');
			skip_id='agg_skip_'+pieces[2];

			if ($(this).is(':checked')) {
				$('#'+skip_id).removeAttr('checked');
			}

		});

		changeTotals();
	});

	function changeTotals() {
		switch ($('#total').val()) {
			case '<?php print AGGREGATE_TOTAL_NONE;?>':
				$('#total_type').prop('disabled', true);
				$('#total_prefix').prop('disabled', true);
				$('#order_type').prop('disabled', false);
				break;
			case '<?php print AGGREGATE_TOTAL_ALL;?>':
				$('#total_type').prop('disabled', false);
				$('#total_prefix').prop('disabled', false);
				$('#order_type').prop('disabled', false);
				changeTotalsType();
				break;
			case '<?php print AGGREGATE_TOTAL_ONLY;?>':
				$('#total_type').prop('disabled', false);
				$('#total_prefix').prop('disabled', false);
				//$('#order_type').prop('disabled', true);
				changeTotalsType();
				break;
		}
	}

	function changeTotalsType() {
		if ($('#total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_SIMILAR;?>) {
			if ($('#total_prefix').val() == '') {
				$('#total_prefix').attr('value', 'Total');
			}
		} else if ($('#total_type').val() == <?php print AGGREGATE_TOTAL_TYPE_ALL;?>) {
			if ($('#total_prefix').val() == '') {
				$('#total_prefix').attr('value', 'All Items');
			}
		}
	}
	-->
	</script>
	<?php
}

/**
 * aggregate_template
 */
function aggregate_template() {
	global $aggregate_actions, $item_rows, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('page'));
	input_validate_input_number(get_request_var('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
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
		kill_session_var('sess_agg_tmp_current_page');
		kill_session_var('sess_agg_tmp_filter');
		kill_session_var('sess_agg_tmp_has_graphs');
		kill_session_var('sess_agg_tmp_sort_column');
		kill_session_var('sess_agg_tmp_sort_direction');
		kill_session_var('sess_default_rows');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['has_graphs']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['rows']);
	}else{
		$changed = 0;
		$changed += check_changed('has_graphs', 'sess_agg_tmp_has_graphs');
		$changed += check_changed('rows',       'sess_default_rows');
		$changed += check_changed('filter',     'sess_agg_tmp_filter');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page',           'sess_agg_tmp_current_page', '1');
	load_current_session_value('filter',         'sess_agg_tmp_filter', '');
	load_current_session_value('has_graphs',     'sess_agg_tmp_graphs', 'true');
	load_current_session_value('sort_column',    'sess_agg_tmp_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_agg_tmp_sort_direction', 'ASC');
	load_current_session_value('rows',           'sess_default_rows', read_config_option('num_rows_table'));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}

	form_start('aggregate_templates.php', 'template');

	html_start_box('Aggregate Templates', '100%', '', '3', 'center', 'aggregate_templates.php?action=edit');

	$filter_html = '<tr class="even">
					<td>
					<table class="filterTable">
						<tr>
							<td>
								Search
							</td>
							<td>
								<input type="text" id="filter" size="25" value="' . get_request_var("filter") . '">
							</td>
							<td>
								Templates
							</td>
							<td>
								<select id="rows" onChange="applyFilter()">
								<option value="-1"';
	if (get_request_var("rows") == "-1") {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Default</option>';
	if (sizeof($item_rows) > 0) {
		foreach ($item_rows as $key => $value) {
			$filter_html .= "<option value='" . $key . "'";
			if (get_request_var("rows") == $key) {
				$filter_html .= " selected";
			}
			$filter_html .= ">" . $value . "</option>\n";
		}
	}
	$filter_html .= '					</select>
							</td>
							<td>
								<input type="checkbox" id="has_graphs" ' . ($_REQUEST['has_graphs'] == 'true' ? 'checked':'') . ' onChange="applyFilter()">
							</td>
							<td>
								<label for="has_graphs">Has Graphs</label>
							</td>
							<td>
								<input type="button" value="Go" id="refresh">
							</td>
							<td>
								<input type="button" value="Clear" id="clear">
							</td>
						</tr>
					</table>
					</td>
					<td><input type="hidden" id="page" value="' . $_REQUEST['page'] . '"></td>
				</tr>';

	print $filter_html;

	html_end_box();

	form_end();

	/* form the 'where' clause for our main sql query */
	$sql_where = '';
	if (strlen($_REQUEST['filter'])) {
		$sql_where = "WHERE (pgt.name LIKE '%%" . $_REQUEST['filter'] . "%%' OR gt.name LIKE '%%" . $_REQUEST['filter'] . "%%')";
	}

	if ($_REQUEST['has_graphs'] == 'true') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . 'graphs.graphs>0';
	}

	form_start('aggregate_templates.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(pgt.id)
		FROM aggregate_graph_templates AS pgt
		LEFT JOIN (
			SELECT aggregate_template_id, COUNT(*) AS graphs 
			FROM aggregate_graphs 
			GROUP BY aggregate_template_id
		) AS graphs
		ON pgt.id=graphs.aggregate_template_id
		LEFT JOIN graph_templates AS gt
		ON gt.id=pgt.graph_template_id
		$sql_where");

	$template_list = db_fetch_assoc("SELECT pgt.*, graphs.graphs, gt.name AS graph_template_name
		FROM aggregate_graph_templates AS pgt
		LEFT JOIN (
			SELECT aggregate_template_id, COUNT(*) AS graphs 
			FROM aggregate_graphs 
			GROUP BY aggregate_template_id
		) AS graphs
		ON pgt.id=graphs.aggregate_template_id
		LEFT JOIN graph_templates AS gt
		ON gt.id=pgt.graph_template_id
		$sql_where
		ORDER BY " . $_REQUEST['sort_column'] . ' ' . $_REQUEST['sort_direction'] .
		' LIMIT ' . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows'));

	$nav = html_nav_bar('aggregate_templates.php', MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 5, 'Aggregate Templates', 'page', 'main');

	print $nav;

	$display_text = array(
		'pgt.name' => array('display' => 'Template Title', 'align' => 'left', 'sort' => 'ASC'),
		'nosort' => array('display' => 'Deletable', 'align' => 'right', 'tip' => 'Aggregate Templates that are in use can not be Deleted.  In use is defined as being referenced by an Aggregate.'),
		'graphs.graphs' => array('display' => 'Graphs', 'align' => 'right', 'sort' => 'DESC'),
		'graph_template_name' => array('display' => 'Graph Template', 'align' => 'left', 'sort' => 'ASC'));

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	if (sizeof($template_list)) {
		foreach ($template_list as $template) {
			if ($template['graphs'] > 0) {
				$disabled = true;
			}else{
				$disabled = false;
			}

			form_alternate_row('line' . $template['id'], true, $disabled);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('aggregate_templates.php?action=edit&id=' . $template['id'] . '&page=1'). "'>" . ((get_request_var('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($template['name'])) : htmlspecialchars($template['name'])) . '</a>', $template['id']);
			form_selectable_cell($disabled ? 'No':'Yes', $template['id'], '', 'text-align:right');
			form_selectable_cell(number_format($template['graphs']), $template['id'], '', 'text-align:right;');
			form_selectable_cell(((get_request_var('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($template['graph_template_name'])) : htmlspecialchars($template['graph_template_name'])), $template['id']);
			form_checkbox_cell($template['graph_template_name'], $template['id'], $disabled);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Aggregate Templates</em></td></tr>\n";
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($aggregate_actions);

	form_end();

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'aggregate_templates.php';
		strURL += '?rows=' + $('#rows').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&has_graphs=' + $('#has_graphs').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'aggregate_templates.php?clear=1&header=false';
		loadPageNoHeader(strURL);
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

		$('#template').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php
}

?>
