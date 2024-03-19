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

include('./include/auth.php');

$actions = array(
	1 => __('Delete'),
	2 => __('Disable'),
	3 => __('Enable'),
);

$mactions = array(
	1 => __('Disable'),
	2 => __('Enable')
);

$tabs_manager_edit = array(
	'general'       => __('General'),
	'notifications' => __('Notifications'),
	'logs'          => __('Logs'),
);

/* set default action */
set_default_action();

get_filter_request_var('tab', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		top_header();
		manager_edit();
		bottom_footer();

		break;

	default:
		top_header();
		manager();
		bottom_footer();

		break;
}

function manager() {
	global $config, $actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'hostname',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_snmp_mgr');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL  = 'managers.php';
		strURL += '?filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = 'managers.php?clear=1';
		loadUrl({url:strURL})
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpagent_managers').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('SNMP Notification Receivers'), '100%', '', '3', 'center', 'managers.php?action=edit');

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_managers' action='managers.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search'); ?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>' onChange='applyFilter()'>
						</td>
						<td>
							<?php print __('Receivers'); ?>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
								<?php
								if (cacti_sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'";

										if (get_request_var('rows') == $key) {
											print ' selected';
										} print '>' . html_escape($value) . '</option>';
									}
								}
	?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php
	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = 'WHERE (
		sm.hostname LIKE '	   . db_qstr('%' . get_request_var('filter') . '%') . '
		OR sm.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';

	$total_rows = db_fetch_cell("SELECT
		COUNT(sm.id)
		FROM snmpagent_managers AS sm
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$managers = db_fetch_assoc("SELECT sm.id, sm.description,
		sm.hostname, sm.disabled, smn.count_notify, snl.count_log
		FROM snmpagent_managers AS sm
		LEFT JOIN (
			SELECT COUNT(*) as count_notify, manager_id
			FROM snmpagent_managers_notifications
			GROUP BY manager_id
		) AS smn
		ON smn.manager_id = sm.id
		LEFT JOIN (
			SELECT COUNT(*) as count_log, manager_id
			FROM snmpagent_notifications_log
			GROUP BY manager_id
		) AS snl
		ON snl.manager_id = sm.id
		$sql_where
		$sql_order
		$sql_limit");

	$display_text = array(
		'description'  => array( __('Description'), 'ASC'),
		'id'           => array( __('Id'), 'ASC'),
		'disabled'     => array( __('Status'), 'ASC'),
		'hostname'     => array( __('Hostname'), 'ASC'),
		'count_notify' => array( __('Notifications'), 'ASC'),
		'count_log'    => array( __('Logs'), 'ASC')
	);

	/* generate page list */
	$nav = html_nav_bar('managers.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Receivers'), 'page', 'main');

	form_start('managers.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($managers)) {
		foreach ($managers as $item) {
			$description = filter_value($item['description'], get_request_var('filter'));
			$hostname    = filter_value($item['hostname'], get_request_var('filter'));
			form_alternate_row('line' . $item['id'], false);
			form_selectable_cell('<a class="linkEditMain" href="' . html_escape(CACTI_PATH_URL . 'managers.php?action=edit&id=' . $item['id']) . '">' . html_escape($description) . '</a>', $item['id']);
			form_selectable_cell($item['id'], $item['id']);
			form_selectable_cell($item['disabled'] ? '<span class="deviceDown">' . __('Disabled') . '</span>' : '<span class="deviceUp">' . __('Enabled') . '</span>', $item['id']);
			form_selectable_ecell($hostname, $item['id']);
			form_selectable_cell('<a class="linkEditMain" href="' . html_escape(CACTI_PATH_URL . 'managers.php?action=edit&tab=notifications&id=' . $item['id']) . '">' . ($item['count_notify'] ? $item['count_notify'] : 0) . '</a>' , $item['id']);
			form_selectable_cell('<a class="linkEditMain" href="' . html_escape(CACTI_PATH_URL . 'managers.php?action=edit&tab=logs&id=' . $item['id']) . '">' . ($item['count_log'] ? $item['count_log'] : 0) . '</a>', $item['id']);
			form_checkbox_cell($item['description'], $item['id']);
			form_end_row();
		}
	} else {
		print '<tr><td><em>' . __('No SNMP Notification Receivers') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($managers)) {
		print $nav;
	}

	form_hidden_box('action_receivers', '1', '');

	draw_actions_dropdown($actions);

	form_end();
}

function manager_edit() {
	global $config, $snmp_auth_protocols, $snmp_priv_protocols, $snmp_versions,
	$tabs_manager_edit, $fields_manager_edit, $mactions;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isset_request_var('tab')) {
		set_request_var('tab', 'general');
	}
	$id	= (isset_request_var('id') ? get_request_var('id') : '0');

	if ($id) {
		$manager      = db_fetch_row_prepared('SELECT * FROM snmpagent_managers WHERE id = ?', array(get_request_var('id')));
		$header_label = __esc('SNMP Notification Receiver [edit: %s]', $manager['description']);
	} else {
		$header_label = __('SNMP Notification Receiver [new]');
	}

	if (cacti_sizeof($tabs_manager_edit) && isset_request_var('id')) {
		$i = 0;

		/* draw the tabs */
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach (array_keys($tabs_manager_edit) as $tab_short_name) {
			if (($id == 0 && $tab_short_name != 'general')) {
				print "<li class='subTab'><a href='#' " . (($tab_short_name == get_request_var('tab')) ? "class='selected'" : '') . "'>" . $tabs_manager_edit[$tab_short_name] . '</a></li>';
			} else {
				print "<li class='subTab'><a " . (($tab_short_name == get_request_var('tab')) ? "class='selected'" : '') .
					" href='" . html_escape(CACTI_PATH_URL .
					'managers.php?action=edit&id=' . get_request_var('id') .
					'&tab=' . $tab_short_name) .
					"'>" . $tabs_manager_edit[$tab_short_name] . '</a></li>';
			}

			$i++;
		}

		print '</ul></nav></div>';

		if (read_config_option('legacy_menu_nav') != 'on') { ?>
		<script type='text/javascript'>

		$(function() {
			$('.subTab').find('a').click(function(event) {
				event.preventDefault();

				strURL  = $(this).attr('href');
				strURL += (strURL.indexOf('?') > 0 ? '&':'?');
				loadUrl({url:strURL})
			});
		});
		</script>
		<?php }
		}

	switch(get_request_var('tab')) {
		case 'notifications':
			manager_notifications($id, $header_label);

			break;
		case 'logs':
			manager_logs($id, $header_label);

			break;

		default:
			form_start('managers.php');

			html_start_box($header_label, '100%', true, '3', 'center', '');

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => inject_form_variables($fields_manager_edit, (isset($manager) ? $manager : array()))
				)
			);

			html_end_box(true, true);

			form_save_button('managers.php', 'return');

			?>
			<script type='text/javascript'>

			// Need to set this for global snmpv3 functions to remain sane between edits
			snmp_security_initialized = false;

			$(function() {
				setSNMP();
			});
			</script>
			<?php
	}

	?>
	<script language='javascript' type='text/javascript' >
		$('.tooltip').tooltip({
			track: true,
			position: { collision: 'flipfit' },
			content: function() { return DOMPurify.sanitize($(this).attr('title')); }
		});
	</script>
	<?php
}

function manager_notifications($id, $header_label) {
	global $item_rows, $mactions;

	$mibs            = db_fetch_assoc('SELECT DISTINCT mib FROM snmpagent_cache');
	$registered_mibs = array();

	if ($mibs && $mibs > 0) {
		foreach ($mibs as $mib) {
			$registered_mibs[] = $mib['mib'];
		}
	}

	/* ================= input validation ================= */
	if (!$id | !is_numeric($id)) {
		die_html_input_error('id');
	}

	if (!in_array(get_request_var('mib'), $registered_mibs, true) && get_request_var('mib') != '-1' && get_request_var('mib') != '') {
		die_html_input_error('mib');
	}
	/* ==================================================== */

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'mib' => array(
			'filter'  => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '-1',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_snmp_cache');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box($header_label, '100%', '', '3', 'center', '');

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'managers.php?action=edit&tab=notifications&id=<?php print $id; ?>';
		strURL += '&mib=' + $('#mib').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();

		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = 'managers.php?action=edit&tab=notifications&id=<?php print $id; ?>&clear=1';
		loadUrl({url:strURL})
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpagent_managers').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_managers' name='form_snmpagent_managers' action='managers.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('MIB');?>
						</td>
						<td>
							<select id='mib' name='mib' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('mib') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<?php
								if (cacti_sizeof($mibs)) {
									foreach ($mibs as $mib) {
										print "<option value='" . html_escape($mib['mib']) . "'";

										if (get_request_var('mib') == $mib['mib']) {
											print ' selected';
										} print '>' . html_escape($mib['mib']) . '</option>';
									}
								}
	?>
							</select>
						</td>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>' onChange='applyFilter()'>
						</td>
						<td>
							<?php print __('Receivers');?>
						</td>
						<td>
							<select id='rows' name='rows' onChange='applyFilter()'>
								<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
								<?php
	if (cacti_sizeof($item_rows)) {
		foreach ($item_rows as $key => $value) {
			print "<option value='" . $key . "'";

			if (get_request_var('rows') == $key) {
				print ' selected';
			} print '>' . html_escape($value) . '</option>';
		}
	}
	?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = " AND `kind`='Notification'";

	/* filter by host */
	if (get_request_var('mib') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('mib')) {
		$sql_where .= " AND snmpagent_cache.mib='" . get_request_var('mib') . "'";
	}
	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (
			`oid` LIKE '	 . db_qstr('%' . get_request_var('filter') . '%') . '
			OR `name` LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR `mib` LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}
	$sql_where .= ' ORDER by `oid`';

	form_start('managers.php', 'chk');

	/* FIXME: Change SQL Queries to not use WHERE 1 */
	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM snmpagent_cache WHERE 1 $sql_where");

	$snmp_cache_sql = "SELECT * FROM snmpagent_cache WHERE 1 $sql_where LIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;
	$snmp_cache     = db_fetch_assoc($snmp_cache_sql);

	$registered_notifications = db_fetch_assoc_prepared('SELECT notification, mib FROM snmpagent_managers_notifications WHERE manager_id = ?', array($id));
	$notifications            = array();

	if ($registered_notifications && cacti_sizeof($registered_notifications) > 0) {
		foreach ($registered_notifications as $registered_notification) {
			$notifications[$registered_notification['mib']][$registered_notification['notification']] = 1;
		}
	}

	$display_text = array(
		__('Name'),
		__('OID'),
		__('MIB'),
		__('Kind'),
		__('Max-Access'),
		__('Monitored')
	);

	/* generate page list */
	$nav = html_nav_bar('managers.php?action=edit&id=' . $id . '&tab=notifications&mib=' . get_request_var('mib') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Notifications'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_checkbox($display_text, true, 'managers.php?action=edit&tab=notifications&id=' . $id);

	if (cacti_sizeof($snmp_cache)) {
		foreach ($snmp_cache as $item) {
			$row_id = $item['mib'] . '__' . $item['name'];
			$oid    = filter_value($item['oid'], get_request_var('filter'));
			$name   = filter_value($item['name'], get_request_var('filter'));
			$mib    = filter_value($item['mib'], get_request_var('filter'));

			form_alternate_row('line' . $row_id, false);

			if ($item['description']) {
				print '<td><a href="#" title="<div class=\'header\'>' . $name . '</div><div class=\'content preformatted\'>' . $item['description']. '</div>" class="tooltip">' . $name . '</a></td>';
			} else {
				form_selectable_cell($name, $row_id);
			}

			form_selectable_cell($oid, $row_id);
			form_selectable_cell($mib, $row_id);
			form_selectable_cell($item['kind'], $row_id);
			form_selectable_cell($item['max-access'],$row_id);
			form_selectable_cell(((isset($notifications[$item['mib']]) && isset($notifications[$item['mib']][$item['name']])) ? '<span class="deviceUp">' . __('Enabled'):'<span class="deviceDown">' . __('Disabled')) . '</span>', $row_id);
			form_checkbox_cell($item['oid'], $row_id);
			form_end_row();
		}
	} else {
		print '<tr><td><em>' . __('No SNMP Notifications') . '</em></td></tr>';
	}

	?>
	<input type='hidden' name='id' value='<?php print get_request_var('id'); ?>'>
	<?php

	html_end_box(false);

	if (cacti_sizeof($snmp_cache)) {
		print $nav;
	}

	draw_actions_dropdown($mactions);

	form_end();
}

function manager_logs($id, $header_label) {
	$severity_levels = array(
		SNMPAGENT_EVENT_SEVERITY_LOW      => 'LOW',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM   => 'MEDIUM',
		SNMPAGENT_EVENT_SEVERITY_HIGH     => 'HIGH',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => 'CRITICAL'
	);

	$severity_colors = array(
		SNMPAGENT_EVENT_SEVERITY_LOW      => '#00FF00',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM   => '#FFFF00',
		SNMPAGENT_EVENT_SEVERITY_HIGH     => '#FF0000',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => '#FF00FF'
	);

	if (isset_request_var('purge')) {
		db_execute_prepared('DELETE FROM snmpagent_notifications_log WHERE manager_id = ?', array($id));
		set_request_var('clear', true);
	}

	/* ================= input validation ================= */
	if (!$id | !is_numeric($id)) {
		die_html_input_error('id');
	}

	if (!in_array(get_request_var('severity'), array_keys($severity_levels), true) && get_request_var('severity') != '-1' && get_request_var('severity') != '') {
		die_html_input_error('severity');
	}

	/* ==================================================== */

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'severity' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_snmp_logs');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box($header_label, '100%', '', '3', 'center', '');

	?>
	<script type='text/javascript'>

	function applyFilter(objForm) {
		strURL  = '?severity=' + $('#severity').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&action=edit&tab=logs&id=<?php print get_request_var('id'); ?>';
		loadUrl({url:strURL})
	}

	function showTooltip(e, div, title, desc) {
		div.style.display = 'inline';
		div.style.position = 'fixed';
		div.style.backgroundColor = '#EFFCF0';
		div.style.border = 'solid 1px grey';
		div.style.padding = '10px';
		div.innerHTML = '<b>' + title + '</b><div style="padding-left:10px; padding-right:5px;"><pre>' + desc + '</pre></div>';
		div.style.left = e.clientX + 15 + 'px';
		div.style.top = e.clientY + 15 + 'px';
	}

	function hideTooltip(div) {
		div.style.display = 'none';
	}

	function highlightStatus(selectID){
		if ($('#status_' + selectID).val() == 'ON') {
			$('#status_' + selectID).css('background-color', 'LawnGreen');
		}else {
			$('#status_' + selectID).css('background-color', 'OrangeRed');
		}
	}

	</script>
	<tr class='even'>
		<td>
			<form name='form_snmpagent_manager_logs' action='managers.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Severity');?>
						</td>
						<td>
							<select id='severity' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('severity') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<?php
								foreach ($severity_levels as $level => $name) {
									print "<option value='" . $level . "'";

									if (get_request_var('severity') == $level) {
										print ' selected';
									} print '>' . $name . '</option>';
								}
	?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc('Purge');?>' title='<?php print __esc('Purge Notification Log');?>'>
							</span>
						</td>
					</tr>
				</table>
				<input type='hidden' name='action' value='edit'>
				<input type='hidden' name='tab' value='logs'>
				<input type='hidden' id='id' value='<?php print get_request_var('id'); ?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = " snl.manager_id='" . $id . "'";

	/* filter by severity */
	if (get_request_var('severity') == '-1') {
		/* Show all items */
	} elseif (!isempty_request_var('severity')) {
		$sql_where .= " AND snl.severity='" . get_request_var('severity') . "'";
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (`varbinds` LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	$sql_where .= ' ORDER by `id` DESC';
	$sql_query = "SELECT snl.*, sc.description
		FROM snmpagent_notifications_log AS snl
		LEFT JOIN snmpagent_cache AS sc
		ON sc.name = snl.notification
		WHERE $sql_where
		LIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	form_start('managers.php', 'chk');

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM snmpagent_notifications_log AS snl
		WHERE $sql_where");

	$logs = db_fetch_assoc($sql_query);

	$display_text = array(
		'',
		__('Time'),
		__('Notification'),
		__('Varbinds')
	);

	$nav = html_nav_bar('managers.php?action=exit&id=' . $id . '&tab=logs&mib=' . get_request_var('mib') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text), __('Receivers'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header($display_text);

	if (cacti_sizeof($logs)) {
		foreach ($logs as $item) {
			$varbinds = filter_value($item['varbinds'], get_request_var('filter'));

			form_alternate_row('line' . $item['id'], true);

			print "<td title='" . __esc('Severity Level') . ': ' . $severity_levels[$item['severity']] . "' style='width:10px;background-color: " . $severity_colors[$item['severity']] . ";border-top:1px solid white;border-bottom:1px solid white;'></td>";
			print "<td class='nowrap'>" . date('Y/m/d H:i:s', $item['time']) . '</td>';

			if ($item['description']) {
				$description = '';
				$lines       = preg_split('/\r\n|\r|\n/', $item['description']);

				foreach ($lines as $line) {
					$description .= html_escape(trim($line)) . '<br>';
				}
				print '<td><a href="#" onMouseOut="hideTooltip(snmpagentTooltip)" onMouseMove="showTooltip(event, snmpagentTooltip, \'' . $item['notification'] . '\', \'' . $description . '\')">' . $item['notification'] . '</a></td>';
			} else {
				print "<td>{$item['notification']}</td>";
			}
			print "<td>$varbinds</td>";
			form_end_row();
		}
	} else {
		print '<tr><td><em>' . __('No SNMP Notification Log Entries') . '</em></td></tr>';
	}

	html_end_box();

	if (cacti_sizeof($logs)) {
		print $nav;
	}

	?>
	<input type='hidden' name='id' value='<?php print get_filter_request_var('id'); ?>'>
	<div style='display:none' id='snmpagentTooltip'></div>
	<?php
}

function form_save() {
	if (!isset_request_var('tab')) {
		set_request_var('tab', 'general');
	}

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('max_log_size');

	if (!in_array(get_nfilter_request_var('max_log_size'), range(1,31), true)) {
		die_html_input_error('max_log_size');
	}
	/* ================= input validation ================= */

	switch(get_nfilter_request_var('tab')) {
		case 'notifications':
			header('Location: managers.php?action=edit&tab=notifications&id=' . get_request_var('id'));

			break;

		default:
			$save['id']             = get_request_var('id');
			$save['description']    = form_input_validate(trim(get_nfilter_request_var('description')), 'description', '', false, 3);
			$save['hostname']       = form_input_validate(trim(get_nfilter_request_var('hostname')), 'hostname', '', false, 3);
			$save['disabled']       = form_input_validate(get_nfilter_request_var('disabled'), 'disabled', '^on$', true, 3);
			$save['max_log_size']   = get_nfilter_request_var('max_log_size');
			$save['snmp_version']   = form_input_validate(get_nfilter_request_var('snmp_version'), 'snmp_version', '^[1-3]$', false, 3);
			$save['snmp_community'] = form_input_validate(get_nfilter_request_var('snmp_community'), 'snmp_community', '', true, 3);

			if ($save['snmp_version'] == 3) {
				$save['snmp_username']        = form_input_validate(get_nfilter_request_var('snmp_username'), 'snmp_username', '', true, 3);
				$save['snmp_password']        = form_input_validate(get_nfilter_request_var('snmp_password'), 'snmp_password', '', true, 3);
				$save['snmp_auth_protocol']   = form_input_validate(get_nfilter_request_var('snmp_auth_protocol'), 'snmp_auth_protocol', "^\[None\]|MD5|SHA|SHA224|SHA256|SHA392|SHA512$", true, 3);
				$save['snmp_priv_passphrase'] = form_input_validate(get_nfilter_request_var('snmp_priv_passphrase'), 'snmp_priv_passphrase', '', true, 3);
				$save['snmp_priv_protocol']   = form_input_validate(get_nfilter_request_var('snmp_priv_protocol'), 'snmp_priv_protocol', "^\[None\]|DES|AES|AES128|AES192|AES192C|AES256|AES256C$", true, 3);
				$save['snmp_engine_id']       = form_input_validate(get_request_var_post('snmp_engine_id'), 'snmp_engine_id', '', false, 3);
			} else {
				$save['snmp_username']        = '';
				$save['snmp_password']        = '';
				$save['snmp_auth_protocol']   = '';
				$save['snmp_priv_passphrase'] = '';
				$save['snmp_priv_protocol']   = '';
				$save['snmp_engine_id']       = '';
			}

			$save['snmp_port']         = form_input_validate(get_nfilter_request_var('snmp_port'), 'snmp_port', '^[0-9]+$', false, 3);
			$save['snmp_message_type'] = form_input_validate(get_nfilter_request_var('snmp_message_type'), 'snmp_message_type', '^[1-2]$', false, 3);
			$save['notes']             = form_input_validate(get_nfilter_request_var('notes'), 'notes', '', true, 3);

			if ($save['snmp_version'] == 3 && ($save['snmp_password'] != get_nfilter_request_var('snmp_password_confirm'))) {
				raise_message(4);
			}

			if ($save['snmp_version'] == 3 && ($save['snmp_priv_passphrase'] != get_nfilter_request_var('snmp_priv_passphrase_confirm'))) {
				raise_message(4);
			}

			$manager_id = 0;

			if (!is_error_message()) {
				$manager_id = sql_save($save, 'snmpagent_managers');
				raise_message(($manager_id)? 1 : 2);
			}

			break;
	}

	header('Location: managers.php?action=edit&id=' . (empty($manager_id) ? get_nfilter_request_var('id') : $manager_id));
}

function form_actions() {
	global $actions, $mactions;

	if (isset_request_var('selected_items')) {
		if (isset_request_var('action_receivers')) {
			$selected_items = cacti_unserialize(stripslashes(get_nfilter_request_var('selected_graphs_array')));

			if ($selected_items != false) {
				if (get_nfilter_request_var('drp_action') == '1') { // delete
					db_execute('DELETE FROM snmpagent_managers WHERE id IN (' . implode(',' ,$selected_items) . ')');
					db_execute('DELETE FROM snmpagent_managers_notifications WHERE manager_id IN (' . implode(',' ,$selected_items) . ')');
					db_execute('DELETE FROM snmpagent_notifications_log WHERE manager_id IN (' . implode(',' ,$selected_items) . ')');
				} elseif (get_nfilter_request_var('drp_action') == '2') { // disable
					db_execute("UPDATE snmpagent_managers SET disabled = 'on' WHERE id IN (" . implode(',' ,$selected_items) . ')');
				} elseif (get_nfilter_request_var('drp_action') == '3') { // enable
					db_execute("UPDATE snmpagent_managers SET disabled = '' WHERE id IN (" . implode(',' ,$selected_items) . ')');
				}

				header('Location: managers.php');

				exit;
			}
		} elseif (isset_request_var('action_receiver_notifications')) {
			/* ================= input validation ================= */
			get_filter_request_var('id');
			/* ==================================================== */

			$selected_items = cacti_unserialize(stripslashes(get_nfilter_request_var('selected_items')));

			if ($selected_items !== false) {
				if (get_nfilter_request_var('drp_action') == '1') { // disable
					foreach ($selected_items as $mib => $notifications) {
						foreach ($notifications as $notification => $state) {
							db_execute_prepared('DELETE FROM snmpagent_managers_notifications
								WHERE `manager_id` = ?
								AND `mib` = ?
								AND `notification` = ?
								LIMIT 1',
								array(get_nfilter_request_var('id'), $mib, $notification));
						}
					}
				} elseif (get_nfilter_request_var('drp_action') == '2') { // enable
					foreach ($selected_items as $mib => $notifications) {
						foreach ($notifications as $notification => $state) {
							db_execute_prepared('INSERT IGNORE INTO snmpagent_managers_notifications
								(`manager_id`, `notification`, `mib`)
								VALUES (?, ?, ?)',
								array(get_nfilter_request_var('id'), $notification, $mib));
						}
					}
				}
			}

			header('Location: managers.php?action=edit&id=' . get_nfilter_request_var('id') . '&tab=notifications');

			exit;
		}
	} elseif (isset_request_var('action_receivers')) {
		$ilist  = '';
		$iarray = array();

		foreach ($_POST as $key => $value) {
			if (strstr($key, 'chk_')) {
				/* grep manager's id */
				$id = substr($key, 4);
				/* ================= input validation ================= */
				input_validate_input_number($id, 'id');
				/* ==================================================== */

				$ilist .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT description FROM snmpagent_managers WHERE id = ?', array($id))) . '</li>';

				$iarray[] = $id;
			}
		}

		$form_data = array(
			'general' => array(
				'page'       => 'managers.php',
				'actions'    => $actions,
				'eaction'    => 'action_receivers',
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			),
			'options' => array(
				1 => array(
					'smessage' => __('Click \'Continue\' to Delete the following Notification Receiver.'),
					'pmessage' => __('Click \'Continue\' to Delete following Notification Receivers.'),
					'scont'    => __('Delete Notification Receiver'),
					'pcont'    => __('Delete Notification Receivers')
				),
				2 => array(
					'smessage' => __('Click \'Continue\' to Disable the following Notification Receiver.'),
					'pmessage' => __('Click \'Continue\' to Disable following Notification Receivers.'),
					'scont'    => __('Disable Notification Receiver'),
					'pcont'    => __('Disable Notification Receivers')
				),
				3 => array(
					'smessage' => __('Click \'Continue\' to Enable the following Notification Receiver.'),
					'pmessage' => __('Click \'Continue\' to Enable following Notification Receivers.'),
					'scont'    => __('Enable Notification Receiver'),
					'pcont'    => __('Enable Notification Receivers'),
				)
			)
		);

		form_continue_confirmation($form_data);
	} else {
		$ilist  = '';
		$iarray = array();

		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */

		foreach ($_POST as $key => $value) {
			if (strstr($key, 'chk_')) {
				/* grep mib and notification name */
				$row_id = substr($key, 4);

				list($mib, $name) = explode('__', $row_id);

				$ilist .= '<li>' . html_escape($name) . ' (' . html_escape($mib) .')</li>';

				$iarray[$mib][$name] = 1;
			}
		}

		$form_data = array(
			'general' => array(
				'page'       => 'managers.php?action=edit&tab=notifications&id=' . get_request_var('id'),
				'actions'    => $mactions,
				'eaction'    => 'action_receiver_notifications',
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			),
			'options' => array(
				1 => array(
					'smessage' => __('Click \'Continue\' to Disable Forwarding the following Notification Object the following Notification Receiver.'),
					'pmessage' => __('Click \'Continue\' to Disable Forwarding the following Notification Objects to the following Notification Receiver.'),
					'scont'    => __('Disable Forwarding Object'),
					'pcont'    => __('Disable Forwarding Objects')
				),
				2 => array(
					'smessage' => __('Click \'Continue\' to Enable Forwarding the following Notification Object to this Notification Receiver.'),
					'pmessage' => __('Click \'Continue\' to Enable Forwarding the following Notification Objects Notification Receivers.'),
					'scont'    => __('Enable Forwarding Object'),
					'pcont'    => __('Enable Forwarding Objects')
				)
			)
		);

		form_continue_confirmation($form_data);
	}
}
