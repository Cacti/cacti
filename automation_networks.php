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

/* include cacti base functions */
include('./include/auth.php');
include_once('./lib/snmp.php');
include_once('./lib/poller.php');

define('MAX_DISPLAY_PAGES', 21);

$network_actions = array(
	1 => 'Delete',
	2 => 'Disable',
	3 => 'Enable',
	4 => 'Discover Now'
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
case 'edit':
	top_header();
	network_edit();
	bottom_footer();

	break;
default:
	top_header();
	networks();
	bottom_footer();

	break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST['save_component_network'])) {
		$network_id = api_networks_save($_POST);

		header('Location: automation_networks.php?action=edit&id=' . (empty($network_id) ? $_POST['id'] : $network_id));
	}
}

function api_networks_remove($network_id){
	db_execute('DELETE FROM automation_networks WHERE id=' . $network_id);
	db_execute('DELETE FROM automation_devices WHERE network_id=' . $network_id);
}

function api_networks_discover($network_id) {
	$enabled = db_fetch_cell_prepared('SELECT enabled FROM automation_networks WHERE id = ?', array($network_id));
	$running = db_fetch_cell_prepared('SELECT count(*) FROM automation_processes WHERE network_id = ?', array($network_id));
	$name    = db_fetch_cell_prepared('SELECT name FROM automation_networks WHERE id = ?', array($network_id));

	if ($enabled == 'on') {
		if (!$running) {
			exec_background(read_config_option('path_php_binary'), '-q ' . read_config_option('path_webroot') . "/poller_automation.php --network=$network_id --force");
		}else{
			$_SESSION['automation_message'] = "Can Not Restart Discovery for Discovery in Progress for Network '$name'";
			raise_message('automation_message');
		}
	}else{
		$_SESSION['automation_message'] = "Can Not Perform Discovery for Disabled Network '$name'";
		raise_message('automation_message');
	}
}

function api_networks_save($post) {
	if (empty($post['network_id'])) {
		$save['id']            = form_input_validate($post['id'], 'id', '^[0-9]+$', false, 3);

		/* general information */
		$save['name']          = form_input_validate($post['name'], 'name', '', false, 3);
		$save['subnet_range']  = form_input_validate($post['subnet_range'], 'subnet_range', '', false, 3);
		$save['dns_servers']   = form_input_validate($post['dns_servers'], 'dns_servers', '', true, 3);

		$save['threads']       = form_input_validate($post['threads'], 'threads', '^[0-9]+$', false, 3);
		$save['run_limit']     = form_input_validate($post['run_limit'], 'run_limit', '^[0-9]+$', false, 3);

		$save['enabled']            = (isset($post['enabled']) ? 'on':'');
		$save['rerun_data_queries'] = (isset($post['rerun_data_queries']) ? 'on':'');

		/* discovery connectivity settings */
		$save['snmp_id']       = form_input_validate($post['snmp_id'], 'snmp_id', '^[0-9]+$', false, 3);
		$save['ping_method']   = form_input_validate($post['ping_method'], 'ping_method', '^[0-9]+$', false, 3);
		$save['ping_port']     = form_input_validate($post['ping_port'], 'ping_port', '^[0-9]+$', false, 3);
		$save['ping_timeout']  = form_input_validate($post['ping_timeout'], 'ping_timeout', '^[0-9]+$', false, 3);
		$save['ping_retries']  = form_input_validate($post['ping_retries'], 'ping_retries', '^[0-9]+$', false, 3);

		/* discovery schedule settings */
		$save['sched_type']    = form_input_validate($post['sched_type'], 'sched_type', '^[0-9]+$', false, 3);
		$save['start_at']      = form_input_validate($post['start_at'], 'start_at', '', false, 3);;

		// accomodate a schedule start change
		if ($post['orig_start_at'] != $post['start_at']) {
			$save['next_start'] = '';
		}

		if ($post['orig_sched_type'] != $post['sched_type']) {
			$save['next_start'] = '';
		}

		$save['recur_every']   = form_input_validate($post['recur_every'], 'recur_every', '', true, 3);

		$save['day_of_week']   = form_input_validate(isset($post['day_of_week']) ? implode(',', $post['day_of_week']):'', 'day_of_week', '', true, 3);
		$save['month']         = form_input_validate(isset($post['month']) ? implode(',', $post['month']):'', 'month', '', true, 3);
		$save['day_of_month']  = form_input_validate(isset($post['day_of_month']) ? implode(',', $post['day_of_month']):'', 'day_of_month', '', true, 3);
		$save['monthly_week']  = form_input_validate(isset($post['monthly_week']) ? implode(',', $post['monthly_week']):'', 'monthly_week', '', true, 3);
		$save['monthly_day']   = form_input_validate(isset($post['monthly_day']) ? implode(',', $post['monthly_day']):'', 'monthly_day', '', true, 3);

		/* validate the network definitions and rais error if failed */
		$continue  = true;
		$total_ips = 0;
		$networks  = explode(',', $save['subnet_range']);

		$i = 0;
		if (sizeof($networks)) {
		foreach($networks as $net) {
			$ips = automation_calculate_total_ips($networks, $i);
			if ($ips !== false) {
				$total_ips += $ips;
			}else{
				$continue = false;
				$_SESSION['automation_message'] = "ERROR: Network '$net' is Invalid.  Please correct.";
				raise_message('automation_message');
				break;
			}
			$i++;
		}
		}

		if ($continue) {
			$save['total_ips'] = $total_ips;

			$network_id = 0;
			if (!is_error_message()) {
				$network_id = sql_save($save, 'automation_networks');
	
				if ($network_id) {
					raise_message(1);
				}else{
					raise_message(2);
				}
			}
		}
	}

	return $network_id;
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $colors, $config, $network_actions, $fields_networkss_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == '1') { /* delete */
			foreach($selected_items as $item) {
				/* ================= input validation ================= */
				input_validate_input_number($item);
				/* ==================================================== */

				api_networks_remove($item);
			}
		}elseif ($_POST['drp_action'] == '2') { /* enable */
			foreach($selected_items as $item) {
				/* ================= input validation ================= */
				input_validate_input_number($item);
				/* ==================================================== */

				api_networks_enable($item);
			}
		}elseif ($_POST['drp_action'] == '3') { /* disable */
			foreach($selected_items as $item) {
				/* ================= input validation ================= */
				input_validate_input_number($item);
				/* ==================================================== */

				api_networks_disable($item);
			}
		}elseif ($_POST['drp_action'] == '4') { /* run now */
			foreach($selected_items as $item) {
				/* ================= input validation ================= */
				input_validate_input_number($item);
				/* ==================================================== */

				api_networks_discover($item);
			}
		}

		header('Location: automation_networks.php');
		exit;
	}

	/* setup some variables */
	$networks_list = ''; $i = 0;

	/* loop through each of the device types selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg('^chk_([0-9]+)$', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$networks_info = db_fetch_row('SELECT name FROM automation_networks WHERE id=' . $matches[1]);
			$networks_list .= '<li>' . $networks_info['name'] . '</li>';
			$networks_array[$i] = $matches[1];
		}

		$i++;
	}

	top_header();

	html_start_box('<strong>' . $network_actions{$_POST['drp_action']} . '</strong>', '60%', $colors['header_panel'], '3', 'center', '');

	print "<form action='automation_networks.php' method='post'>\n";

	if ($_POST['drp_action'] == '1') { /* delete */
		print "	<tr>
				<td class='textArea'>
					<p>Are you sure you want to delete the following Network(s)?</p>
					<p><ul>$networks_list</ul></p>
				</td>
			</tr>\n";
	}elseif ($_POST['drp_action'] == '2') { /* enable */
		print "	<tr>
				<td class='textArea'>
					<p>When you click save, the following Network(s) will be enabled.
					<p><ul>$networks_list</ul></p>
				</td>
			</tr>\n";
	}elseif ($_POST['drp_action'] == '3') { /* disable */
		print "	<tr>
				<td class='textArea'>
					<p>When you click save, the following Network(s) will be disabled.
					<p><ul>$networks_list</ul></p>
				</td>
			</tr>\n";
	}elseif ($_POST['drp_action'] == '4') { /* discover now */
		print "	<tr>
				<td class='textArea'>
					<p>When you click save, the following Network(s) will be discovered.
					<p><ul>$networks_list</ul></p>
				</td>
			</tr>\n";
	}

	if (!isset($networks_array)) {
		print "<tr><td class='even'><span class='textError'>You must select at least one Network.</span></td></tr>\n";
		$save_html = '';
	}else{
		$save_html = "<input type='submit' value='Yes' name='save'>";
	}

	print "	<tr>
			<td colspan='2' align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($networks_array) ? serialize($networks_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>" . (strlen($save_html) ? "
				<input type='submit' name='cancel' value='No'>
				$save_html" : "<input type='submit' name='cancel' value='Return'>") . "
			</td>
		</tr>\n";

	html_end_box();

	bottom_footer();
}

function network_edit() {
	global $config, $ping_methods;;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	display_output_messages();

	$sched_types = array('1' => 'Manual', '2' => 'Daily', '3' => 'Weekly', '4' => 'Monthly', '5' => 'Monthly on Day');

	if (!empty($_GET['id'])) {
		$network = db_fetch_row('SELECT * FROM automation_networks WHERE id=' . $_GET['id']);
		$header_label = '[edit: ' . $network['name'] . ']';
	}else{
		$header_label = '[new]';
	}

	/* file: mactrack_device_types.php, action: edit */
	$fields = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => 'General Settings',
		),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => 'Name',
		'description' => 'Give this Network a meaningful name.',
		'value' => '|arg1:name|',
		'max_length' => '250',
		'placeholder' => 'New Network Discovery Range'
		),
	'subnet_range' => array(
		'method' => 'textarea',
		'friendly_name' => 'Subnet Range',
		'description' => 'Enter valid Network Ranges separated by commas.  You may use an IP address, a Network range such as 192.168.1.0/24 or 192.168.1.0/255.255.255.0, or using wildcards such as 192.168.*.*',
		'value' => '|arg1:subnet_range|',
		'textarea_rows' => '2',
		'textarea_cols' => '60',
		'max_length' => '255',
		'placeholder' => '192.168.1.0/24'
		),
	'total_ips' => array(
		'method' => 'other',
		'friendly_name' => 'Total IP Addresses',
		'description' => 'Total addressible IP Addresses in this Network Range.',
		'value' => number_format($network['total_ips'])
		),
	'dns_servers' => array(
		'method' => 'textbox',
		'friendly_name' => 'Alternate DNS Servers',
		'description' => 'A space delimited list of alternate DNS Servers to use for DNS resolution. If blank, the poller OS will be used to resolve DNS names.',
		'value' => '|arg1:dns_servers|',
		'max_length' => '250',
		'placeholder' => 'Enter IPs or FQDNs of DNS Servers'
		),
	'sched_type' => array(
		'method' => 'drop_array',
		'friendly_name' => 'Schedule Type',
		'description' => 'Define the collection frequency.',
		'value' => '|arg1:sched_type|',
		'array' => $sched_types,
		'default' => 1
		),
	'threads' => array(
		'method' => 'drop_array',
		'friendly_name' => 'Discovery Threads',
		'description' => 'Define the number of threads to use for discovering this Network Range.',
		'value' => '|arg1:threads|',
		'array' => array(
			'1' => '1 Thread', 
			'2' => '2 Threads', 
			'3' => '3 Threads', 
			'4' => '4 Threads', 
			'5' => '5 Threads', 
			'6' => '6 Threads', 
			'7' => '7 Threads', 
			'8' => '8 Threads', 
			'9' => '9 Threads', 
			'10' => '10 Threads'
			),
		'default' => 1
		),
	'run_limit' => array(
		'method' => 'drop_array',
		'friendly_name' => 'Run Limit',
		'description' => 'After the selected Run Limit, the discovery process will be terminated.',
		'value' => '|arg1:run_limit|',
		'array' => array(
			'60' => '1 Minute', 
			'300' => '5 Minutes', 
			'600' => '10 Minutes', 
			'1200' => '20 Minutes', 
			'1800' => '30 Minutes', 
			'3600' => '1 Hour', 
			'7200' => '2 Hours', 
			'14400' => '4 Hours', 
			'28800' => '8 Hours', 
			),
		'default' => 1200
		),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => 'Enabled',
		'description' => 'Enable this Network Range Enabled.',
		'value' => '|arg1:enabled|'
		),
	'rerun_data_queries' => array(
		'method' => 'checkbox',
		'friendly_name' => 'Rerun Data Queries',
		'description' => 'If checked, a previously discovered device will be re queried.',
		'value' => '|arg1:rerun_data_queries|'
		),
	'spacer2' => array(
		'method' => 'spacer',
		'friendly_name' => 'Discovery Timing',
		),
	'start_at' => array(
		'method' => 'textbox',
		'friendly_name' => 'Starting Date/Time',
		'description' => 'What time will this Network discover item start?',
		'value' => '|arg1:start_at|',
		'max_length' => '30',
		'default' => date('Y-m-d H:i:s'),
		'size' => 20
		),
	'recur_every' => array(
		'method' => 'drop_array',
		'friendly_name' => 'Rerun Every',
		'description' => 'Rerun discovery for this Network Range every X.',
		'value' => '|arg1:recur_every|',
		'default' => '1',
		'array' => array(1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7'),
		),
	'day_of_week' => array(
		'method' => 'drop_multi',
		'friendly_name' => 'Days of Week',
		'description' => 'What Day(s) of the week will this Network Range be discovered.',
		'array' => array(1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday'),
		'value' => '|arg1:day_of_week|',
		'class' => 'day_of_week'
		),
	'month' => array(
		'method' => 'drop_multi',
		'friendly_name' => 'Months of Year',
		'description' => 'What Months(s) of the Year will this Network Range be discovered.',
		'array' => array(1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'),
		'value' => '|arg1:month|',
		'class' => 'month'
		),
	'day_of_month' => array(
		'method' => 'drop_multi',
		'friendly_name' => 'Days of Month',
		'description' => 'What Day(s) of the Month will this Network Range be discovered.',
		'array' => array(1 => '1', 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32 => 'Last'),
		'value' => '|arg1:day_of_month|',
		'class' => 'days_of_month'
		),
	'monthly_week' => array(
		'method' => 'drop_multi',
		'friendly_name' => 'Week(s) of Month',
		'description' => 'What Week(s) of the Month will this Network Range be discovered.',
		'array' => array(1 => 'First', 2 => 'Second', 3 => 'Third', '32' => 'Last'),
		'value' => '|arg1:monthly_week|',
		'class' => 'monthly_week'
		),
	'monthly_day' => array(
		'method' => 'drop_multi',
		'friendly_name' => 'Day(s) of Week',
		'description' => 'What Day(s) of the week will this Network Range be discovered.',
		'array' => array(1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday'),
		'value' => '|arg1:monthly_day|',
		'class' => 'monthly_day'
		),
	'spacer1' => array(
		'method' => 'spacer',
		'friendly_name' => 'Connectivity Settings',
		),
	'snmp_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => 'SNMP Options',
		'description' => 'Select the SNMP Options to use for discovery of this Network Range.',
		'value' => '|arg1:snmp_id|',
		'none_value' => 'None',
		'sql' => 'SELECT id, name FROM automation_snmp ORDER BY name'
		),
	'ping_method' => array(
		'friendly_name' => 'Ping Method',
		'description' => 'The type of ping packet to sent.',
		'value' => '|arg1:ping_method|',
		'method' => 'drop_array',
		'default' => read_config_option('ping_method'),
		'array' => $ping_methods
		),
	'ping_port' => array(
		'method' => 'textbox',
		'friendly_name' => 'Ping Port',
		'value' => '|arg1:ping_port|',
		'description' => 'TCP or UDP port to attempt connection.',
		'default' => read_config_option('ping_port'),
		'max_length' => 5,
		'size' => 5
		),
	'ping_timeout' => array(
		'friendly_name' => 'Ping Timeout Value',
		'description' => 'The timeout value to use for host ICMP and UDP pinging.  This host SNMP timeout value applies for SNMP pings.',
		'method' => 'textbox',
		'value' => '|arg1:ping_timeout|',
		'default' => read_config_option('ping_timeout'),
		'max_length' => 5,
		'size' => 5
		),
	'ping_retries' => array(
		'friendly_name' => 'Ping Retry Count',
		'description' => 'After an initial failure, the number of ping retries Cacti will attempt before failing.',
		'method' => 'textbox',
		'value' => '|arg1:ping_retries|',
		'default' => read_config_option('ping_retries'),
		'max_length' => 5,
		'size' => 5
		),
	'orig_start_at' => array(
		'method' => 'hidden',
		'value' => '|arg1:start_at|',
		),
	'orig_sched_type' => array(
		'method' => 'hidden',
		'value' => '|arg1:sched_type|',
		)
	);

	print "<form id='form_network' action='automation_networks.php' method='post'>\n";

	html_start_box("<strong>Network Discovery Range</strong> $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => 'true'),
		'fields' => inject_form_variables($fields, (isset($network) ? $network : array()))
		));

	html_end_box();

	form_hidden_box('save_component_network', '1', '');
	form_hidden_box('id', !empty($_GET['id']) ? $_GET['id']:0, 0);

	form_save_button('automation_networks.php', 'return');

	?>
	<script type='text/javascript'>
	$(function() {
		$('#day_of_week').multiselect({ 
			selectedList: 7, 
			noneSelectedText: 'Select the days(s) of the week',
			header: false, 
			height: 54, 
			multipleRow: true, 
			multipleRowWidth: 90, 
			minWidth: 450 
		});

		$('#month').multiselect({ 
			selectedList: 7, 
			noneSelectedText: 'Select the month(s) of the year',
			header: false, 
			height: 82, 
			multipleRow: true, 
			multipleRowWidth: 90, 
			minWidth: 400 
		});

		$('#day_of_month').multiselect({ 
			selectedList: 15, 
			noneSelectedText: 'Select the day(s) of the month',
			header: false, 
			height: 162, 
			multipleRow: true, 
			multipleRowWidth: 55, 
			minWidth: 400 
		});

		$('#monthly_week').multiselect({ 
			selectedList: 4, 
			noneSelectedText: 'Select the week(s) of the month',
			header: false, 
			height: 28,
			multipleRow: true,
			multipleRowWidth: 70,
			minWidth: 300
		});

		$('#monthly_day').multiselect({ 
			selectedList: 7, 
			noneSelectedText: 'Select the day(s) of the week',
			header: false, 
			height: 54, 
			multipleRow: true, 
			multipleRowWidth: 90, 
			minWidth: 450 
		});

		$('#start_at').datetimepicker({
			minuteGrid: 10,
			stepMinute: 5,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			minDateTime: new Date(<?php print date("Y") . ', ' . (date("m")-1) . ', ' . date("d, H") . ', ' . date('i', ceil(time()/300)*300) . ', 0, 0';?>)
		});

		$('#sched_type').change(function() {
			setSchedule();
		});

		setSchedule();

		$('#ping_method').change(function() {
			setPing();
		});

		setPing();
	});

	function setPing() {
		switch($('#ping_method').val()) {
		case '0':
			$('#row_ping_port').hide();
			$('#row_ping_timeout').hide();
			$('#row_ping_retries').hide();
			break;
		case '1':
			$('#row_ping_port').hide();
			$('#row_ping_timeout').show();
			$('#row_ping_retries').show();
			break;
		case '2':
		case '3':
			$('#row_ping_port').show();
			$('#row_ping_timeout').show();
			$('#row_ping_retries').show();
			break;
		}
	}

	function setSchedule() {
		switch($('#sched_type').val()) {
		case '1': //Manual
			$('#row_start_at').hide();
			$('#row_spacer2').hide();
			$('#row_recur_every').hide();
			$('#row_day_of_week').hide();
			$('#row_month').hide();
			$('#row_day_of_month').hide();
			$('#row_monthly_week').hide();
			$('#row_monthly_day').hide();
			break;
		case '2': //Daily
			$('#row_start_at').show();
			$('#row_spacer2').show();
			$('#row_recur_every').show();
			$('#row_day_of_week').hide();
			$('#row_month').hide();
			$('#row_day_of_month').hide();
			$('#row_monthly_week').hide();
			$('#row_monthly_day').hide();
			$('#row_recur_every').find('td:first').each(function() {
				var html = $(this).html();
				html = html.replace('every X Weeks', 'every X Days');
				html = html.replace('every X.', 'every X Days.');
				$(this).html(html);
			});

			break;
		case '3': //Weekly
			$('#row_start_at').show();
			$('#row_spacer2').show();
			$('#row_recur_every').show();
			$('#row_day_of_week').show();
			$('#row_month').hide();
			$('#row_day_of_month').hide();
			$('#row_monthly_week').hide();
			$('#row_monthly_day').hide();
			$('#row_recur_every').find('td:first').each(function() {
				var html = $(this).html();
				html = html.replace('every X Days', 'every X Weeks');
				html = html.replace('every X.', 'every X Weeks.');
				$(this).html(html);
			});

			break;
		case '4': //Monthly
			$('#row_start_at').show();
			$('#row_spacer2').show();
			$('#row_recur_every').hide();
			$('#row_day_of_week').hide();
			$('#row_month').show();
			$('#row_day_of_month').show();
			$('#row_monthly_week').hide();
			$('#row_monthly_day').hide();
			break;
		case '5': //Monthly on Day
			$('#row_start_at').show();
			$('#row_spacer2').show();
			$('#row_recur_every').hide();
			$('#row_day_of_week').hide();
			$('#row_month').show();
			$('#row_day_of_month').hide();
			$('#row_monthly_week').show();
			$('#row_monthly_day').show();
			break;
		}
	}
	</script>
	<?php
}

function get_networks(&$sql_where, $row_limit, $apply_limits = TRUE) {
	if ($_REQUEST['filter'] != '') {
		$sql_where = " WHERE (automation_networks.name LIKE '%%" . $_REQUEST['filter'] . "%%')";
	}

	$query_string = "SELECT *
		FROM automation_networks
		$sql_where
		ORDER BY " . $_REQUEST['sort_column'] . ' ' . $_REQUEST['sort_direction'];

	if ($apply_limits) {
		$query_string .= ' LIMIT ' . ($row_limit*($_REQUEST['page']-1)) . ',' . $row_limit;
	}

	return db_fetch_assoc($query_string);
}

function networks() {
	global $colors, $network_actions, $networkss, $config, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up search string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_networks_current_page');
		kill_session_var('sess_networks_filter');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_networks_sort_column');
		kill_session_var('sess_networks_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_networks_current_page', '1');
	load_current_session_value('filter', 'sess_networks_filter', '');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('sort_column', 'sess_networks_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_networks_sort_direction', 'ASC');

	if ($_REQUEST['rows'] == -1) {
		$row_limit = read_config_option('num_rows_table');
	}elseif ($_REQUEST['rows'] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST['rows'];
	}

	html_start_box('<strong>Network Filters</strong>', '100%', '', '3', 'center', 'automation_networks.php?action=edit');
	networks_filter();
	html_end_box();

	$sql_where = '';

	$networks = get_networks($sql_where, $row_limit);

	/* print checkbox form for validation */
    print "<form name='chk' method='post' action='automation_networks.php'>\n";

	html_start_box('', '100%', $colors['header'], '3', 'center', '');

	$total_rows = db_fetch_cell('SELECT COUNT(*) FROM automation_networks ' . $sql_where);

	$nav = html_nav_bar('automation_networks.php', MAX_DISPLAY_PAGES, get_request_var_request('page'), $row_limit, $total_rows, 14, 'Networks');

	print $nav;

	$sched_types = array('1' => 'Manual', '2' => 'Daily', '3' => 'Weekly', '4' => 'Monthly', '5' => 'Monthly on Day');

	$display_text = array(
		'name' => array('display' => 'Network Name', 'align' => 'left', 'sort' => 'ASC'),
		'sched_type'   => array('display' => 'Schedule', 'align' => 'left', 'sort' => 'DESC'),
		'total_ips'    => array('display' => 'Total IPs', 'align' => 'right', 'sort' => 'DESC'),
		'nosort1'      => array('display' => 'Status', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The Current Status of this Networks Discovery'),
		'nosort2'      => array('display' => 'Progress', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'Pending/Running/Dene'),
		'nosort3'      => array('display' => 'Up/SNMP Hosts', 'align' => 'right', 'sort' => 'DESC'),
		'threads'      => array('display' => 'Threads', 'align' => 'right', 'sort' => 'DESC'),
		'last_runtime' => array('display' => 'Last Runtime', 'align' => 'right', 'sort' => 'ASC'),
		'nosort4'      => array('display' => 'Next Start', 'align' => 'right', 'sort' => 'ASC'),
		'last_started' => array('display' => 'Last Started', 'align' => 'right', 'sort' => 'ASC'));

	$status = 'Idle';

	html_header_sort_checkbox($display_text, $_REQUEST['sort_column'], $_REQUEST['sort_direction'], false);

	if (sizeof($networks)) {
		foreach ($networks as $network) {
			if ($network['enabled'] == '') {
				$mystat   = "<span style='color:grey;'>Disabled</span>";
				$progress = "0/0/0";
				$status   = array();
			}else{
				$status = db_fetch_row_prepared('SELECT 
					COUNT(*) AS total,
					SUM(CASE WHEN status=0 THEN 1 ELSE 0 END) AS pending,
					SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS running,
					SUM(CASE WHEN status=2 THEN 1 ELSE 0 END) AS done
					FROM automation_ips
					WHERE network_id = ?', array($network['id']));

				if ($status['total'] > 0) {
					$mystat   = "<span style='color:red;'>Running</span>";
					$progress = $status['pending'] . '/' . $status['running'] . '/' . $status['done'];
				}else{
					$mystat   = "<span style='color:green;'>Idle</span>";
					$progress = "0/0/0";
				}

				$updown = db_fetch_row_prepared("SELECT SUM(up_hosts) AS up, SUM(snmp_hosts) AS snmp
					FROM automation_processes
					WHERE network_id = ?", array($network['id']));

				if (empty($updown['up'])) {
					$updown['up']   = $network['up_hosts'];
					$updown['snmp'] = $network['snmp_hosts'];
				}
			}

			form_alternate_row('line' . $network['id'], true);
			form_selectable_cell('<a class="linkEditMain" href="' . htmlspecialchars('automation_networks.php?action=edit&id=' . $network['id']) . '">' . $network['name'] . '</a>', $network['id']);
			form_selectable_cell($sched_types[$network['sched_type']], $network['id']);
			form_selectable_cell(number_format($network['total_ips']), $network['id'], '', 'text-align:right;');
			form_selectable_cell($mystat, $network['id'], '', 'text-align:right;');
			form_selectable_cell($progress, $network['id'], '', 'text-align:right;');
			form_selectable_cell(number_format($updown['up']) . '/' . number_format($updown['snmp']), $network['id'], '', 'text-align:right;');
			form_selectable_cell(number_format($network['threads']), $network['id'], '', 'text-align:right;');
			form_selectable_cell(round($network['last_runtime'],2), $network['id'], '', 'text-align:right;');
			form_selectable_cell($network['next_start'] == '0000-00-00 00:00:00' ? substr($network['start_at'],0,16):substr($network['next_start'],0,16), $network['id'], '', 'text-align:right;');
			form_selectable_cell($network['last_started'] == '0000-00-00 00:00:00' ? 'Never':substr($network['last_started'],0,16), $network['id'], '', 'text-align:right;');
			form_checkbox_cell($network['name'], $network['id']);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td colspan='10'><em>No Network Found</em></td></tr>";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($network_actions);

	print "</form>\n";
}

function networks_filter() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
			<form id='form_networks' action='automation_networks.php'>
			<table class='filterTable'>
				<tr>
					</td>
					<td>
						Search
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print $_REQUEST['filter'];?>'>
					</td>
					<td>
						Networks
					</td>
					<td>
						<select name='rows' onChange='applyFilterChange(document.form_networks)'>
							<option value='-1'<?php if (get_request_var_request('rows') == '-1') {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . $value . '</option>\n';
							}
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='go' title='Search' value='Go'>
					</td>
					<td>
						<input type='button' id='clear' title='Clear Filtered' value='Clear'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
			<script type='text/javascript'>
			function applyFilterChange(objForm) {
				strURL = '?rows=' + objForm.rows.value;
				strURL = strURL + '&filter=' + objForm.filter.value;
				document.location = strURL;
			}
			</script>
		</td>
	</tr>
	<?php
}

?>
