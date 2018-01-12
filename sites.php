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

include('./include/auth.php');

$site_actions = array(
	1 => __('Delete')
);

/* file: sites.php, action: edit */
$fields_site_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('Site Information'),
		'collapsible' => 'true'
	),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('The primary name for the Site.'),
		'value' => '|arg1:name|',
		'size' => '50',
		'default' => __('New Site'),
		'max_length' => '100'
	),
	'address1' => array(
		'method' => 'textbox',
		'friendly_name' => __('Address1'),
		'description' => __('The primary address for the Site.'),
		'value' => '|arg1:address1|',
		'placeholder' => __('Enter the Site Address'),
		'size' => '70',
		'max_length' => '100'
	),
	'address2' => array(
		'method' => 'textbox',
		'friendly_name' => __('Address2'),
		'description' => __('Additional address information for the Site.'),
		'value' => '|arg1:address2|',
		'placeholder' => __('Additional Site Address information'),
		'size' => '70',
		'max_length' => '100'
	),
	'city' => array(
		'method' => 'textbox',
		'friendly_name' => __('City'),
		'description' => __('The city or locality for the Site.'),
		'value' => '|arg1:city|',
		'placeholder' => __('Enter the City or Locality'),
		'size' => '30',
		'max_length' => '30'
	),
	'state' => array(
		'method' => 'textbox',
		'friendly_name' => __('State'),
		'description' => __('The state for the Site.'),
		'value' => '|arg1:state|',
		'placeholder' => __('Enter the state'),
		'size' => '15',
		'max_length' => '20'
	),
	'postal_code' => array(
		'method' => 'textbox',
		'friendly_name' => __('Postal/Zip Code'),
		'description' => __('The postal or zip code for the Site.'),
		'value' => '|arg1:postal_code|',
		'placeholder' => __('Enter the postal code'),
		'size' => '20',
		'max_length' => '20'
	),
	'country' => array(
		'method' => 'textbox',
		'friendly_name' => __('Country'),
		'description' => __('The country for the Site.'),
		'value' => '|arg1:country|',
		'placeholder' => __('Enter the country'),
		'size' => '20',
		'max_length' => '30'
	),
	'timezone' => array(
		'method' => 'drop_callback',
		'friendly_name' => __('TimeZone'),
		'description' => __('The TimeZone for the Site.'),
		'sql' => 'SELECT Name AS id, Name AS name FROM mysql.time_zone_name ORDER BY name',
		'action' => 'ajax_tz',
		'id' => '|arg1:timezone|',
		'value' => '|arg1:timezone|'
		),
	'latitude' => array(
		'method' => 'textbox',
		'friendly_name' => __('Latitude'),
		'description' => __('The Latitude for this Site.'),
		'value' => '|arg1:latitude|',
		'placeholder' => __('example 38.889488'),
		'size' => '20',
		'max_length' => '30'
	),
	'longitude' => array(
		'method' => 'textbox',
		'friendly_name' => __('Longitude'),
		'description' => __('The Longitude for this Site.'),
		'value' => '|arg1:longitude|',
		'placeholder' => __('example -77.0374678'),
		'size' => '20',
		'max_length' => '30'
	),
	'notes' => array(
		'method' => 'textarea',
		'friendly_name' => __('Site Notes'),
		'textarea_rows' => '3',
		'textarea_cols' => '70',
		'description' => __('Additional area use for random notes related to this Site.'),
		'value' => '|arg1:notes|',
		'max_length' => '255',
		'placeholder' => __('Enter some useful information about the Site.'),
		'class' => 'textAreaNotes'
	),
	'alternate_id' => array(
		'method' => 'textbox',
		'friendly_name' => __('Alternate Name'),
		'description' => __('Used for cases where a Site has an alternate named used to describe it'),
		'value' => '|arg1:alternate_id|',
		'placeholder' => __('If the Site is known by another name enter it here.'),
		'size' => '50',
		'max_length' => '30'
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
	'save_component_site' => array(
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
	case 'ajax_tz':
		print json_encode(db_fetch_assoc_prepared('SELECT Name AS label, Name AS `value`
			FROM mysql.time_zone_name
			WHERE Name LIKE ?
			ORDER BY Name
			LIMIT ' . read_config_option('autocomplete_rows'),
			array('%' . get_nfilter_request_var('term') . '%')));

		break;
	case 'edit':
		top_header();

		site_edit();

		bottom_footer();
		break;
	default:
		top_header();

		sites();

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
	if (isset_request_var('save_component_site')) {
		$save['id']           = get_filter_request_var('id');
		$save['name']         = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['address1']     = form_input_validate(get_nfilter_request_var('address1'), 'address1', '', true, 3);
		$save['address2']     = form_input_validate(get_nfilter_request_var('address2'), 'address2', '', true, 3);
		$save['city']         = form_input_validate(get_nfilter_request_var('city'), 'city', '', true, 3);
		$save['state']        = form_input_validate(get_nfilter_request_var('state'), 'state', '', true, 3);
		$save['postal_code']  = form_input_validate(get_nfilter_request_var('postal_code'), 'postal_code', '', true, 3);
		$save['country']      = form_input_validate(get_nfilter_request_var('country'), 'country', '', true, 3);
		$save['timezone']     = form_input_validate(get_nfilter_request_var('timezone'), 'timezone', '', true, 3);
		$save['latitude']     = form_input_validate(get_nfilter_request_var('latitude'), 'latitude', '^-?[0-9]\d*(\.\d+)?$', true, 3);
		$save['longitude']    = form_input_validate(get_nfilter_request_var('longitude'), 'longitude', '^-?[0-9]\d*(\.\d+)?$', true, 3);
		$save['alternate_id'] = form_input_validate(get_nfilter_request_var('alternate_id'), 'alternate_id', '', true, 3);
		$save['notes']        = form_input_validate(get_nfilter_request_var('notes'), 'notes', '', true, 3);

		if (!is_error_message()) {
			$site_id = sql_save($save, 'sites');

			if ($site_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: sites.php?header=false&action=edit&id=' . (empty($site_id) ? get_nfilter_request_var('id') : $site_id));
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $site_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				db_execute('DELETE FROM sites WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('UPDATE host SET site_id=0 WHERE ' . array_to_sql_or($selected_items, 'site_id'));
			}
		}

		header('Location: sites.php?header=false');
		exit;
	}

	/* setup some variables */
	$site_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$site_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM sites WHERE id = ?', array($matches[1]))) . '</li>';
			$site_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('sites.php');

	html_start_box($site_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($site_array) && sizeof($site_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea' class='odd'>
					<p>" . __n('Click \'Continue\' to delete the following Site.  Note, all devices will be disassociated from this site.', 'Click \'Continue\' to delete all following Sites.  Note, all devices will be disassociated from this site.', sizeof($site_array)) . "</p>
					<div class='itemlist'><ul>$site_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __n('Delete Site', 'Delete Sites', sizeof($site_array)) . "'>";
		}
	} else {
		print "<tr><td class='odd'><span class='textError'>" . __('You must select at least one Site.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($site_array) ? serialize($site_array) : '') . "'>
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
		$site = db_fetch_row_prepared('SELECT * FROM sites WHERE id = ?', array(get_request_var('id')));
		$header_label = __('Site [edit: %s]', htmlspecialchars($site['name']));
	} else {
		$header_label = __('Site [new]');
	}

	form_start('sites.php', 'site');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_site_edit, (isset($site) ? $site : array()))
		)
	);

	html_end_box(true, true);

	form_save_button('sites.php', 'return');
}

function sites() {
	global $site_actions, $item_rows;

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
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box( __('Sites'), '100%', '', '3', 'center', 'sites.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_site' action='sites.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Sites');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'sites.php?header=false';
				strURL += '&filter='+$('#filter').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'sites.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_site').submit(function(event) {
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
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM sites $sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$site_list = db_fetch_assoc("SELECT sites.*, count(h.id) AS hosts
		FROM sites
		LEFT JOIN host AS h
		ON h.site_id=sites.id
		$sql_where
		GROUP BY sites.id
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('sites.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Sites'), 'page', 'main');

	form_start('sites.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'    => array('display' => __('Site Name'), 'align' => 'left',  'sort' => 'ASC', 'tip' => __('The name of this Site.')),
		'id'      => array('display' => __('ID'),        'align' => 'right', 'sort' => 'ASC', 'tip' => __('The unique id associated with this Site.')),
		'hosts'   => array('display' => __('Devices'),   'align' => 'right', 'sort' => 'DESC', 'tip' => __('The number of Devices associated with this Site.')),
		'city'    => array('display' => __('City'),      'align' => 'left',  'sort' => 'DESC', 'tip' => __('The City associated with this Site.')),
		'state'   => array('display' => __('State'),     'align' => 'left',  'sort' => 'DESC', 'tip' => __('The State associated with this Site.')),
		'country' => array('display' => __('Country'),   'align' => 'left',  'sort' => 'DESC', 'tip' => __('The Country associated with this Site.')));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($site_list)) {
		foreach ($site_list as $site) {
			form_alternate_row('line' . $site['id'], true);
			form_selectable_cell(filter_value($site['name'], get_request_var('filter'), 'sites.php?action=edit&id=' . $site['id']), $site['id']);
			form_selectable_cell($site['id'], $site['id'], '', 'right');
			form_selectable_cell(number_format_i18n($site['hosts'], '-1'), $site['id'], '', 'right');
			form_selectable_cell($site['city'], $site['id'], '', 'left');
			form_selectable_cell($site['state'], $site['id'], '', 'left');
			form_selectable_cell($site['country'], $site['id'], '', 'left');
			form_checkbox_cell($site['name'], $site['id']);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='4'><em>" . __('No Sites Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (sizeof($site_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($site_actions);

	form_end();
}

