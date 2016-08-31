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

$poller_actions = array(
	1 => __('Delete'),
	2 => __('Disable'),
	3 => __('Enable'),
);

$poller_status = array(
	0 => __('<div class="deviceUnknown">New/Idle</div>'),
	1 => __('<div class="deviceUp">Running</div>'),
	2 => __('<div class="deviceRecovering">Idle</div>'),
	3 => __('<div class="deviceDown">Unknown/Down</div>'),
	4 => __('<div class="deviceDisabled">Disabled</div>')
);

/* file: pollers.php, action: edit */
$fields_site_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('Data Collector Information'),
	),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('The primary name for this Data Collector.'),
		'value' => '|arg1:name|',
		'size' => '50',
		'default' => __('New Data Collector'),
		'max_length' => '100'
	),
	'notes' => array(
		'method' => 'textarea',
		'friendly_name' => __('Notes'),
		'description' => __('Notes for this Data Collector.'),
		'value' => '|arg1:notes|',
		'textarea_rows' => 4,
		'textarea_cols' => 50
	),
	'id' => array(
		'method' => 'hidden',
		'value' => '|arg1:id|',
	),
	'save_component_poller' => array(
		'method' => 'hidden',
		'value' => '1'
	)
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		top_header();

		site_edit();

		bottom_footer();
		break;
	default:
		top_header();

		pollers();

		bottom_footer();
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset_request_var('save_component_poller')) {
		$save['id']           = get_filter_request_var('id');
		$save['name']         = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['notes']        = form_input_validate(get_nfilter_request_var('notes'), 'notes', '', true, 3);

		if (!is_error_message()) {
			$poller_id = sql_save($save, 'poller');

			if ($poller_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		header('Location: pollers.php?header=false&action=edit&id=' . (empty($poller_id) ? get_nfilter_request_var('id') : $poller_id));
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $poller_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */
	
	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM poller WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('UPDATE host SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE automation_networks SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE automation_processes SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE poller_command SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE poller_item SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE poller_output_realtime SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE poller_time SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));

				cacti_log('NOTE: The poller(s) with the id(s): ' . implode(',', $selected_items) . ' deleted by user ' . $_SESSION['sess_user_id'], false, 'WEBUI');
			}elseif (get_request_var('drp_action') == '2') { /* disable */
				db_execute('UPDATE poller SET disabled="on" WHERE ' . array_to_sql_or($selected_items, 'id'));

				cacti_log('NOTE: The poller(s) with the id(s): ' . implode(',', $selected_items) . ' disabled by user ' . $_SESSION['sess_user_id'], false, 'WEBUI');
			}elseif (get_request_var('drp_action') == '3') { /* enable */
				db_execute('UPDATE poller SET disabled="" WHERE ' . array_to_sql_or($selected_items, 'id'));

				cacti_log('NOTE: The poller(s) with the id(s): ' . implode(',', $selected_items) . ' enabled by user ' . $_SESSION['sess_user_id'], false, 'WEBUI');
			}
		}

		header('Location: pollers.php?header=false');
		exit;
	}

	/* setup some variables */
	$pollers = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$pollers .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM sites WHERE id = ?', array($matches[1]))) . '</li>';
			$poller_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('pollers.php');

	html_start_box($poller_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');

	if (isset($poller_array) && sizeof($poller_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to delete the following Data Collector.  Note, all devices will be disassociated from this Data Collector and mapped back to the Main Cacti Data Collector.', 'Click \'Continue\' to delete all following Data Collectors.  Note, all devices will be disassociated from these Data Collectors and mapped back to the Main Cacti Data Collector.', sizeof($poller_array)) . "</p>
					<div class='itemlist'><ul>$pollers</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='" . __n('Delete Data Collector', 'Delete Data Collectors', sizeof($poller_array)) . "'>";
		}elseif (get_request_var('drp_action') == '2') { /* disable */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to disable the following Data Collector.', 'Click \'Continue\' to disable the following Data Collectors.', sizeof($poller_array)) . "</p>
					<div class='itemlist'><ul>$pollers</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='" . __n('Disable Data Collector', 'Disable Data Collectors', sizeof($poller_array)) . "'>";
		}elseif (get_request_var('drp_action') == '3') { /* enable */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to enable the following Data Collector.', 'Click \'Continue\' to enable the following Data Collectors.', sizeof($poller_array)) . "</p>
					<div class='itemlist'><ul>$pollers</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __('Continue') . "' title='" . __n('Enable Data Collector', 'Enable Data Collectors', sizeof($poller_array)) . "'>";
		}
	}else{
		print "<tr><td class='odd'><span class='textError'>" . __('You must select at least one Site.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __('Return') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($poller_array) ? serialize($poller_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ---------------------
    Site Functions
   --------------------- */

function site_edit() {
	global $fields_site_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$poller = db_fetch_row_prepared('SELECT * FROM poller WHERE id = ?', array(get_request_var('id')));
		$header_label = __('Site [edit: %s]', htmlspecialchars($poller['name']));
	}else{
		$header_label = __('Site [new]');
	}

	form_start('pollers.php', 'site');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_site_edit, (isset($poller) ? $poller : array()))
		)
	);

	html_end_box();

	form_save_button('pollers.php', 'return');
}

function pollers() {
	global $poller_actions, $poller_status, $item_rows;

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
		'filter' => array(
			'filter' => FILTER_CALLBACK, 
			'pageset' => true,
			'default' => '', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'name', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'ASC', 
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_site');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box( __('Data Collectors'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form_poller' action='pollers.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td>
						<?php print __('Rows');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
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
						<input type='button' id='refresh' value='<?php print __('Go');?>' title='<?php print __('Set/Refresh Filters');?>'>
					</td>
					<td>
						<input type='button' id='clear' value='<?php print __('Clear');?>' title='<?php print __('Clear Filters');?>'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print get_request_var('page');?>'>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL = 'pollers.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&header=false';
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'pollers.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_poller').submit(function(event) {
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
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (name LIKE '%" . get_request_var('filter') . "%')";
	}else{
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM poller $sql_where");

	$pollers = db_fetch_assoc("SELECT poller.*, count(h.id) AS hosts
		FROM poller
		LEFT JOIN host AS h
		ON h.poller_id=poller.id
		$sql_where
		GROUP BY poller.id
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows);

	$nav = html_nav_bar('pollers.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Pollers'), 'page', 'main');

	form_start('pollers.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'        => array('display' => __('Collector Name'), 'align' => 'left',   'sort' => 'ASC',  'tip' => __('The Name of this Data Collector.')),
		'id'          => array('display' => __('ID'),             'align' => 'right',  'sort' => 'ASC',  'tip' => __('The unique id associated with this Data Collector.')),
		'status'      => array('display' => __('Status'),         'align' => 'center', 'sort' => 'DESC', 'tip' => __('The Status of this Data Collector.')),
		'total_time'  => array('display' => __('Polling Time'),   'align' => 'right',  'sort' => 'DESC', 'tip' => __('The last data collection time for this Data Collector.')),
		'hosts'       => array('display' => __('Devices'),        'align' => 'right',  'sort' => 'DESC', 'tip' => __('The number of Devices associated with this Data Collector.')),
		'snmp'        => array('display' => __('SNMP Gets'),      'align' => 'right',  'sort' => 'DESC', 'tip' => __('The number of SNMP gets associated with this Collector.')),
		'script'      => array('display' => __('Scripts'),        'align' => 'right',  'sort' => 'DESC', 'tip' => __('The number of script calls associated with this Data Collector.')),
		'server'      => array('display' => __('Servers'),        'align' => 'right',  'sort' => 'DESC', 'tip' => __('The number of script server calls associated with this Data Collector.')),
		'last_update' => array('display' => __('Last Finished'),      'align' => 'right',  'sort' => 'DESC', 'tip' => __('The last time this Data Collector completed.')),
		'last_status' => array('display' => __('Last Update'),    'align' => 'right',  'sort' => 'DESC', 'tip' => __('The last time this Data Collector checked in with the main Cacti site.')));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($pollers)) {
		foreach ($pollers as $poller) {
			if ($poller['id'] == 1) {
				$disabled = true;
			}else{
				$disabled = false;
			}

			if ($poller['disabled'] == 'on') {
				$poller['status'] = 4;
			}else if (time()-strtotime($poller['last_status']) > 310) {
				$poller['status'] = 3;
			}

			form_alternate_row('line' . $poller['id'], true, $disabled);
			form_selectable_cell(filter_value($poller['name'], get_request_var('filter'), 'pollers.php?action=edit&id=' . $poller['id']), $poller['id']);
			form_selectable_cell($poller['id'], $poller['id'], '', 'right');
			form_selectable_cell($poller_status[$poller['status']], $poller['id'], '', 'center');
			form_selectable_cell(number_format_i18n($poller['total_time'], 2), $poller['id'], '', 'right');
			form_selectable_cell(number_format_i18n($poller['hosts']), $poller['id'], '', 'right');
			form_selectable_cell(number_format_i18n($poller['snmp']), $poller['id'], '', 'right');
			form_selectable_cell(number_format_i18n($poller['script']), $poller['id'], '', 'right');
			form_selectable_cell(number_format_i18n($poller['server']), $poller['id'], '', 'right');
			form_selectable_cell($poller['last_update'], $poller['id'], '', 'right');
			form_selectable_cell($poller['last_status'], $poller['id'], '', 'right');
			form_checkbox_cell($poller['name'], $poller['id'], $disabled);
			form_end_row();
		}
	}else{
		print "<tr class='tableRow'><td colspan='4'><em>" . __('No Data Collectors Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (sizeof($pollers)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($poller_actions);

	form_end();
}

