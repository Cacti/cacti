<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
include_once('./lib/api_aggregate.php');
include_once('./lib/api_automation.php');
include_once('./lib/api_data_source.php');
include_once('./lib/api_device.php');
include_once('./lib/api_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/html_form_template.php');
include_once('./lib/data_query.php');
include_once('./lib/html_graph.php');
include_once('./lib/html_tree.php');
include_once('./lib/ping.php');
include_once('./lib/poller.php');
include_once('./lib/reports.php');
include_once('./lib/rrd.php');
include_once('./lib/snmp.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

$device_actions = array(
	1 => __('Add Device'),
	2 => __('Delete Device')
);

$os_arr = array_rekey(db_fetch_assoc('SELECT DISTINCT os
	FROM automation_devices
	WHERE os IS NOT NULL AND os!=""'), 'os', 'os');

$status_arr = array(
	__('Down'),
	__('Up')
);

$networks = array_rekey(db_fetch_assoc('SELECT an.id, an.name
	FROM automation_networks AS an
	INNER JOIN automation_devices AS ad
	ON an.id=ad.network_id
	ORDER BY name'), 'id', 'name');

set_default_action();

process_request_vars();

switch(get_request_var('action')) {
	case 'purge':
		purge_discovery_results();

		break;
	case 'actions':
		form_actions();

		break;
	case 'export':
		export_discovery_results();

		break;

	default:
		display_discovery_page();

		break;
}

function form_actions() {
	global $device_actions, $availability_options;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* add to cacti */
				$i = 0;

				foreach ($selected_items as $id) {
					$d                        = db_fetch_row_prepared('SELECT * FROM automation_devices WHERE id = ?', array($id));
					$d['poller_id']           = get_filter_request_var('poller_id');
					$d['host_template']       = get_filter_request_var('host_template');
					$d['availability_method'] = get_filter_request_var('availability_method');
					$d['notes']               = __('Added manually through device automation interface.');
					$d['snmp_sysName']        = $d['sysName'];

					// pull ping options from network_id
					$n = db_fetch_row_prepared('SELECT * FROM automation_networks WHERE id = ?', array($d['network_id']));

					if (cacti_sizeof($n)) {
						$d['ping_method']  = $n['ping_method'];
						$d['ping_port']    = $n['ping_port'];
						$d['ping_timeout'] = $n['ping_timeout'];
						$d['ping_retries'] = $n['ping_retries'];
					}

					$host_id     = automation_add_device($d, true);
					$description = (trim($d['hostname']) != '' ? $d['hostname'] : $d['ip']);

					if ($host_id) {
						raise_message('automation_msg_' . $i, __esc('Device %s Added to Cacti', $description), MESSAGE_LEVEL_INFO);
					} else {
						raise_message('automation_msg_' . $i, __esc('Device %s Not Added to Cacti', $description), MESSAGE_LEVEL_ERROR);
					}

					$i++;
				}
			} elseif (get_nfilter_request_var('drp_action') == 2) { /* remove device */
				foreach ($selected_items as $id) {
					db_execute_prepared('DELETE FROM automation_devices WHERE id = ?', array($id));
				}

				raise_message('automation_remove', __('Devices Removed from Cacti Automation database'), MESSAGE_LEVEL_INFO);
			}
		}

		header('Location: automation_devices.php');

		exit;
	}

	/* setup some variables */
	$device_list  = '';
	$device_array = array();
	$i            = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1], 'chk[1]');
			/* ==================================================== */

			$device_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT CONCAT(IF(hostname!="", hostname, "unknown"), " (", ip, ")") FROM automation_devices WHERE id = ?', array($matches[1]))) . '</li>';
			$device_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('automation_devices.php', 'chk');

	html_start_box($device_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	$available_host_templates = db_fetch_assoc_prepared('SELECT id, name FROM host_template ORDER BY name');

	if (isset($device_array) && cacti_sizeof($device_array)) {
		if (get_request_var('drp_action') == '1') { /* add */
			$pollers = db_fetch_assoc_prepared('SELECT id, name FROM poller ORDER BY name');

			$availability_method = 0;
			$host_template       = 0;
			$devices             = db_fetch_assoc('SELECT id, sysName, sysDescr FROM automation_devices WHERE id IN (' . implode(',', $device_array) . ')');

			foreach ($devices as $device) {
				$os = automation_find_os($device['sysDescr'], '', $device['sysName']);

				if (isset($os['host_template']) && $os['host_template'] > 0) {
					if ($host_template == 0) {
						$host_template       = $os['host_template'];
						$availability_method = $os['availability_method'];
					} elseif ($host_template != $os['host_template']) {
						// End up here if we have 2 devices with different Host Template matches
						$host_template       = 0;
						$availability_method = 0;

						break;
					}
				} else {
					// Couldn't determine the Host Template for a device, so abort and don't set a default
					$host_template       = 0;
					$availability_method = 0;

					break;
				}
			}
			print "<tr>
				<td class='textArea odd'>
					<p>" . __('Click \'Continue\' to add the following Discovered device(s).') . "</p>
					<div class='itemlist'><ul>$device_list</ul></div>
				</td>
			</tr>
			<tr>
				<td class='textArea odd'>
					<table><tr><td>" . __('Pollers') . '</td><td>';

			form_dropdown('poller_id', $pollers, 'name', 'id', '', '', '');

			print '</td></tr><tr><td>' . __('Select Template') . '</td><td>';

			form_dropdown('host_template', $available_host_templates, 'name', 'id', '', '', $host_template);

			print '</td></tr>';

			print '<tr><td>' . __('Availability Method') . '</td><td>';

			form_dropdown('availability_method', $availability_options, '', '', '', '', $availability_method);

			print '</td></tr></table></td></tr>';

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Add Device(s)') . "'>";
		} elseif (get_request_var('drp_action') == '2') { /* remove */
			print "<tr>
				<td class='textArea odd'>
					<p>" . __('Click \'Continue\' to Delete the following Discovered device(s).') . "</p>
					<div class='itemlist'><ul>$device_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Delete Device(s)') . "'>";
		}
	} else {
		raise_message(40);
		header('Location: automation_devices.php');

		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($device_array) ? serialize($device_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function display_discovery_page() {
	global $item_rows, $os_arr, $status_arr, $networks, $device_actions;

	top_header();

	draw_filter();

	$total_rows = 0;

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$results = get_discovery_results($total_rows, $rows);

	/* generate page list */
	$nav = html_nav_bar('automation_devices.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 12, __('Devices'), 'page', 'main');

	form_start('automation_devices.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'hostname'    => array('display' => __('Device Name'), 'align' => 'left', 'sort' => 'ASC'),
		'ip'          => array('display' => __('IP'),          'align' => 'left', 'sort' => 'ASC'),
		'sysName'     => array('display' => __('SNMP Name'),   'align' => 'left', 'sort' => 'ASC'),
		'sysLocation' => array('display' => __('Location'),    'align' => 'left', 'sort' => 'ASC'),
		'sysContact'  => array('display' => __('Contact'),     'align' => 'left', 'sort' => 'ASC'),
		'sysDescr'    => array('display' => __('Description'), 'align' => 'left', 'sort' => 'ASC'),
		'os'          => array('display' => __('OS'),          'align' => 'left', 'sort' => 'ASC'),
		'time'        => array('display' => __('Uptime'),      'align' => 'right', 'sort' => 'DESC'),
		'snmp'        => array('display' => __('SNMP'),        'align' => 'right', 'sort' => 'DESC'),
		'up'          => array('display' => __('Status'),      'align' => 'right', 'sort' => 'ASC'),
		'mytime'      => array('display' => __('Last Check'),  'align' => 'right', 'sort' => 'DESC'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$snmp_version        = read_config_option('snmp_version');
	$snmp_port           = read_config_option('snmp_port');
	$snmp_timeout        = read_config_option('snmp_timeout');
	$snmp_username       = read_config_option('snmp_username');
	$snmp_password       = read_config_option('snmp_password');
	$max_oids            = read_config_option('max_get_size');
	$ping_method         = read_config_option('ping_method');
	$availability_method = read_config_option('availability_method');

	$status = array("<span class='deviceDown'>" . __('Down') . '</span>',"<span class='deviceUp'>" . __('Up') . '</span>');

	if (cacti_sizeof($results)) {
		foreach ($results as $host) {
			form_alternate_row('line' . base64_encode($host['ip']), true);

			if ($host['hostname'] == '') {
				$host['hostname'] = __('Not Detected');
			}

			form_selectable_cell(filter_value($host['hostname'], get_request_var('filter')), $host['id']);
			form_selectable_cell(filter_value($host['ip'], get_request_var('filter')), $host['id']);
			form_selectable_cell(filter_value(snmp_data($host['sysName']), get_request_var('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(filter_value(snmp_data($host['sysLocation']), get_request_var('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(filter_value(snmp_data($host['sysContact']), get_request_var('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(filter_value(snmp_data($host['sysDescr']), get_request_var('filter')), $host['id'], '', 'text-align:left;white-space:normal;');
			form_selectable_cell(filter_value(snmp_data($host['os']), get_request_var('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(snmp_data(get_uptime($host)), $host['id'], '', 'text-align:right');
			form_selectable_cell($status[$host['snmp']], $host['id'], '', 'text-align:right');
			form_selectable_cell($status[$host['up']], $host['id'], '', 'text-align:right');
			form_selectable_cell(substr($host['mytime'],0,16), $host['id'], '', 'text-align:right');
			form_checkbox_cell($host['ip'], $host['id']);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Devices Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($results)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($device_actions);

	form_end();

	bottom_footer();
}

function process_request_vars() {
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
			),
		'status' => array(
			'filter'  => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'network' => array(
			'filter'  => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'snmp' => array(
			'filter'  => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'os' => array(
			'filter'  => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_autom');
	/* ================= input validation ================= */
}

function get_discovery_results(&$total_rows = 0, $rows = 0, $export = false) {
	global $os_arr, $status_arr, $networks, $device_actions;

	$sql_where  = '';
	$status     = get_request_var('status');
	$network    = get_request_var('network');
	$snmp       = get_request_var('snmp');
	$os         = get_request_var('os');
	$filter     = get_request_var('filter');

	if ($status == __('Down')) {
		$sql_where .= 'WHERE up=0';
	} elseif ($status == __('Up')) {
		$sql_where .= 'WHERE up=1';
	}

	if ($network > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'network_id=' . $network;
	}

	if ($snmp == __('Down')) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'snmp=0';
	} elseif ($snmp == __('Up')) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'snmp=1';
	}

	if ($os != '-1' && in_array($os, $os_arr, true)) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . "os='$os'";
	}

	if ($filter != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(hostname LIKE ' . db_qstr('%' . $filter . '%') . '
			OR ip LIKE ' . db_qstr('%' . $filter . '%') . '
			OR sysName LIKE ' . db_qstr('%' . $filter . '%') . '
			OR sysDescr LIKE ' . db_qstr('%' . $filter . '%') . '
			OR sysLocation LIKE ' . db_qstr('%' . $filter . '%') . '
			OR sysContact LIKE ' . db_qstr('%' . $filter . '%') . '
			)';
	}

	if ($export) {
		return db_fetch_assoc("SELECT * FROM automation_devices $sql_where ORDER BY INET_ATON(ip)");
	} else {
		$total_rows = db_fetch_cell("SELECT
			COUNT(*)
			FROM automation_devices
			$sql_where");

		$page = get_request_var('page');

		$sql_order = get_order_string();
		$sql_limit = ' LIMIT ' . ($rows * ($page - 1)) . ',' . $rows;

		$sql_query = "SELECT *,sysUptime snmp_sysUpTimeInstance, FROM_UNIXTIME(time) AS mytime
			FROM automation_devices
			$sql_where
			$sql_order
			$sql_limit";

		return db_fetch_assoc($sql_query);
	}
}

function draw_filter() {
	global $item_rows, $os_arr, $status_arr, $networks, $device_actions;

	html_start_box(__('Discovery Filters'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td class='noprint'>
		<form id='form_devices' method='get' action='automation_devices.php'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Network');?>
					</td>
					<td>
						<select id='network' onChange='applyFilter()'>
							<option value='-1' <?php if (get_request_var('network') == -1) {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
							if (cacti_sizeof($networks)) {
								foreach ($networks as $key => $name) {
									print "<option value='" . html_escape($key) . "'";

									if (get_request_var('network') == $key) {
										print ' selected';
									} print '>' . html_escape($name) . '</option>';
								}
							}
	?>
						</select>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Reset fields to defaults');?>'>
						</span>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='export' value='<?php print __esc('Export');?>' title='<?php print __esc('Export to a file');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc('Purge');?>' title='<?php print __esc('Purge Discovered Devices');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1' <?php if (get_request_var('status') == '') {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
	if (cacti_sizeof($status_arr)) {
		foreach ($status_arr as $st) {
			print "<option value='" . html_escape($st) . "'";

			if (get_request_var('status') == $st) {
				print ' selected';
			} print '>' . html_escape($st) . '</option>';
		}
	}
	?>
						</select>
					</td>
					<td>
						<?php print __('OS');?>
					</td>
					<td>
						<select id='os' onChange='applyFilter()'>
							<option value='-1' <?php if (get_request_var('os') == '') {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
	if (cacti_sizeof($os_arr)) {
		foreach ($os_arr as $st) {
			print "<option value='" . html_escape($st) . "'";

			if (get_request_var('os') == $st) {
				print ' selected';
			} print '>' . html_escape($st) . '</option>';
		}
	}
	?>
						</select>
					</td>
					<td>
						<?php print __('SNMP');?>
					</td>
					<td>
						<select id='snmp' onChange='applyFilter()'>
							<option value='-1' <?php if (get_request_var('snmp') == '') {?> selected<?php }?>><?php print __('Any');?></option>
							<?php
	if (cacti_sizeof($status_arr)) {
		foreach ($status_arr as $st) {
			print "<option value='" . html_escape($st) . "'";

			if (get_request_var('snmp') == $st) {
				print ' selected';
			} print '>' . html_escape($st) . '</option>';
		}
	}
	?>
						</select>
					</td>
					<td>
						<?php print __('Devices');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
	if (cacti_sizeof($item_rows) > 0) {
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
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_devices').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});

			$('#purge').click(function() {
				loadUrl({url:'automation_devices.php?action=purge&network_id='+$('#network').val()})
			});

			$('#export').click(function() {
				document.location = 'automation_devices.php?action=export';
				Pace.stop();
			});
		});

		function clearFilter() {
			loadUrl({url:'automation_devices.php?clear=1'})
		}

		function applyFilter() {
			strURL  = 'automation_devices.php';
			strURL += '?status=' + $('#status').val();
			strURL += '&network=' + $('#network').val();
			strURL += '&snmp=' + $('#snmp').val();
			strURL += '&os=' + $('#os').val();
			strURL += '&filter=' + $('#filter').val();
			strURL += '&rows=' + $('#rows').val();

			loadUrl({url:strURL})
		}

		</script>
		</td>
	</tr>
	<?php
	html_end_box();
}

function export_discovery_results() {
	$results = get_discovery_results($total_rows, 0, true);

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=discovery_results.csv');
	print "Host,IP,System Name,System Location,System Contact,System Description,OS,Uptime,SNMP,Status\n";

	if (cacti_sizeof($results)) {
		foreach ($results as $host) {
			if ($host['sysUptime'] != 0) {
				$days   = intval($host['sysUptime'] / 8640000);
				$hours  = intval(($host['sysUptime'] - ($days * 8640000)) / 360000);
				$uptime = $days . ' days ' . $hours . ' hours';
			} else {
				$uptime = '';
			}

			foreach ($host as $h=>$r) {
				$host['$h'] = str_replace(',','',$r);
			}
			print($host['hostname'] == '' ? __('Not Detected'):$host['hostname']) . ',';
			print $host['ip'] . ',';
			print export_data($host['sysName']) . ',';
			print export_data($host['sysLocation']) . ',';
			print export_data($host['sysContact']) . ',';
			print export_data($host['sysDescr']) . ',';
			print export_data($host['os']) . ',';
			print export_data($uptime) . ',';
			print($host['snmp'] == 1 ? __('Up'):__('Down')) . ',';
			print($host['up'] == 1 ? __('Up'):__('Down')) . "\n";
		}
	}
}

function purge_discovery_results() {
	get_filter_request_var('network');

	if (get_request_var('network') > 0) {
		db_execute_prepared('DELETE FROM automation_devices WHERE network_id = ?', array(get_request_var('network')));
	} else {
		db_execute('TRUNCATE TABLE automation_devices');
	}

	header('Location: automation_devices.php');

	exit;
}

function snmp_data($item) {
	if ($item == '') {
		return __('N/A');
	} else {
		return html_escape(str_replace(':',' ', $item));
	}
}

function export_data($item) {
	if ($item == '') {
		return 'N/A';
	} else {
		return $item;
	}
}
