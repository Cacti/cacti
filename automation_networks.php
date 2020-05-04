<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

/* include cacti base functions */
include('./include/auth.php');
include_once('./lib/snmp.php');
include_once('./lib/poller.php');

$network_actions = array(
	1 => __('Delete'),
	2 => __('Disable'),
	3 => __('Enable'),
	4 => __('Discover Now'),
	5 => __('Cancel Discovery')
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
	if (isset_request_var('save_component_network')) {
		$network_id = api_networks_save($_POST);

		header('Location: automation_networks.php?action=edit&id=' . (empty($network_id) ? get_nfilter_request_var('id') : $network_id));
	}
}

function api_networks_remove($network_id){
	db_execute_prepared('DELETE FROM automation_networks
		WHERE id = ?',
		array($network_id));

	db_execute_prepared('DELETE FROM automation_devices
		WHERE network_id = ?',
		array($network_id));
}

function api_networks_enable($network_id){
	db_execute_prepared('UPDATE automation_networks
		SET enabled="on"
		WHERE id = ?',
		array($network_id));
}

function api_networks_disable($network_id){
	db_execute_prepared('UPDATE automation_networks
		SET enabled=""
		WHERE id = ?',
		array($network_id));
}

function api_networks_cancel($network_id){
	db_execute_prepared('UPDATE IGNORE automation_processes
		SET command="cancel"
		WHERE task="tmaster"
		AND network_id = ?',
		array($network_id));
}

function api_networks_discover($network_id, $discover_debug) {
	global $config;

	$enabled   = db_fetch_cell_prepared('SELECT enabled
		FROM automation_networks
		WHERE id = ?',
		array($network_id));

	$running = db_fetch_cell_prepared('SELECT count(*)
		FROM automation_processes
		WHERE network_id = ?',
		array($network_id));

	$name = db_fetch_cell_prepared('SELECT name
		FROM automation_networks
		WHERE id = ?',
		array($network_id));

	$poller_id = db_fetch_cell_prepared('SELECT poller_id
		FROM automation_networks
		WHERE id = ?',
		array($network_id));

	if ($enabled == 'on') {
		if (!$running) {
			if ($config['poller_id'] == $poller_id) {
				$args_debug = ($discover_debug) ? ' --debug' : '';
				exec_background(read_config_option('path_php_binary'), '-q ' . read_config_option('path_webroot') . "/poller_automation.php --network=$network_id --force" . $args_debug);
			} else {
				$args_debug = ($discover_debug) ? '&debug=true' : '';
				$hostname = db_fetch_cell_prepared('SELECT hostname
					FROM poller
					WHERE id = ?',
					array($poller_id));

				$fgc_contextoption = get_default_contextoption();
				$fgc_context       = stream_context_create($fgc_contextoption);
				$response          = @file_get_contents(get_url_type() .'://' . $hostname . $config['url_path'] . 'remote_agent.php?action=discover&network=' . $network_id . $args_debug, false, $fgc_context);
			}
		} else {
			$_SESSION['automation_message'] = __esc('Can Not Restart Discovery for Discovery in Progress for Network \'%s\'', $name);
			raise_message('automation_message');
		}
	} else {
		$_SESSION['automation_message'] = __esc('Can Not Perform Discovery for Disabled Network \'%s\'', $name);
		raise_message('automation_message');
	}

	force_session_data();
}

function api_networks_save($post) {
	if (empty($post['network_id'])) {
		$save['id']            = form_input_validate($post['id'], 'id', '^[0-9]+$', false, 3);

		/* general information */
		$save['name']          = form_input_validate($post['name'], 'name', '', false, 3);
		$save['poller_id']     = form_input_validate($post['poller_id'], 'poller_id', '^[0-9]+$', false, 3);
		$save['site_id']       = form_input_validate($post['site_id'], 'site_id', '^[0-9]+$', false, 3);
		$save['subnet_range']  = form_input_validate($post['subnet_range'], 'subnet_range', '', false, 3);
		$save['dns_servers']   = form_input_validate($post['dns_servers'], 'dns_servers', '', true, 3);

		$save['threads']       = form_input_validate($post['threads'], 'threads', '^[0-9]+$', false, 3);
		$save['run_limit']     = form_input_validate($post['run_limit'], 'run_limit', '^[0-9]+$', false, 3);

		$save['enabled']              = (isset($post['enabled']) ? 'on':'');

		/* notification settings */
		$save['notification_enabled'] = (isset($post['notification_enabled']) ? 'on':'');
		$save['notification_email']   = form_input_validate($post['notification_email'], 'notification_email', '', true, 3);

		$save['notification_fromname']  = form_input_validate($post['notification_fromname'], 'notification_fromname', '', true, 3);
		$save['notification_fromemail'] = form_input_validate($post['notification_fromemail'], 'notification_fromemail', '', true, 3);

		$save['enable_netbios']       = (isset($post['enable_netbios']) ? 'on':'');
		$save['add_to_cacti']         = (isset($post['add_to_cacti']) ? 'on':'');
		$save['same_sysname']         = (isset($post['same_sysname']) ? 'on':'');
		$save['rerun_data_queries']   = (isset($post['rerun_data_queries']) ? 'on':'');

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
			$save['next_start'] = '0000-00-00';
		}

		if ($post['orig_sched_type'] != $post['sched_type']) {
			$save['next_start'] = '0000-00-00';
		}

		$save['recur_every']   = form_input_validate($post['recur_every'], 'recur_every', '', true, 3);

		$save['day_of_week']   = form_input_validate(isset($post['day_of_week']) ? implode(',', $post['day_of_week']):'', 'day_of_week', '', true, 3);
		$save['month']         = form_input_validate(isset($post['month']) ? implode(',', $post['month']):'', 'month', '', true, 3);
		$save['day_of_month']  = form_input_validate(isset($post['day_of_month']) ? implode(',', $post['day_of_month']):'', 'day_of_month', '', true, 3);
		$save['monthly_week']  = form_input_validate(isset($post['monthly_week']) ? implode(',', $post['monthly_week']):'', 'monthly_week', '', true, 3);
		$save['monthly_day']   = form_input_validate(isset($post['monthly_day']) ? implode(',', $post['monthly_day']):'', 'monthly_day', '', true, 3);

		/* check for bad rules */
		if ($save['sched_type'] == '3') {
			if ($save['day_of_week'] == '') {
				$save['enabled'] = '';
				$_SESSION['automation_message'] = __esc('ERROR: You must specify the day of the week.  Disabling Network %s!.', $save['name']);
				raise_message('automation_message');
			}
		} elseif ($save['sched_type'] == '4') {
			if ($save['month'] == '' || $save['day_of_month'] == '') {
				$save['enabled'] = '';
				$_SESSION['automation_message'] = __esc('ERROR: You must specify both the Months and Days of Month.  Disabling Network %s!', $save['name']);
				raise_message('automation_message');
			}
		} elseif ($save['sched_type'] == '5') {
			if ($save['month'] == '' || $save['monthly_day'] == '' || $save['monthly_week'] == '') {
				$save['enabled'] = '';
				$_SESSION['automation_message'] = __esc('ERROR: You must specify the Months, Weeks of Months, and Days of Week.  Disabling Network %s!', $save['name']);
				raise_message('automation_message');
			}
		}

		/* validate the network definitions and rais error if failed */
		$continue  = true;
		$total_ips = 0;
		$networks  = explode(',', $save['subnet_range']);

		if (cacti_sizeof($networks)) {
			foreach($networks as $net) {
				$ips = automation_calculate_total_ips($net);
				if ($ips !== false) {
					$total_ips += $ips;
				} else {
					$continue = false;
					$_SESSION['automation_message'] = __esc('ERROR: Network \'%s\' is Invalid.', $net);
					raise_message('automation_message');
					break;
				}
			}
		}

		if ($continue) {
			$save['total_ips'] = $total_ips;

			$network_id = 0;
			if (!is_error_message()) {
				$network_id = sql_save($save, 'automation_networks');

				if ($network_id) {
					raise_message(1);
				} else {
					raise_message(2);
				}
			}

			return $network_id;
		}
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $config, $network_actions, $fields_networkss_edit;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));
		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				foreach($selected_items as $item) {
					api_networks_remove($item);
				}
			} elseif (get_nfilter_request_var('drp_action') == '3') { /* enable */
				foreach($selected_items as $item) {
					api_networks_enable($item);
				}
			} elseif (get_nfilter_request_var('drp_action') == '2') { /* disable */
				foreach($selected_items as $item) {
					api_networks_disable($item);
				}
			} elseif (get_nfilter_request_var('drp_action') == '4') { /* run now */
				$discover_debug = isset_request_var('discover_debug');

				foreach($selected_items as $item) {
					api_networks_discover($item, $discover_debug);
				}

				sleep(2);
			} elseif (get_nfilter_request_var('drp_action') == '5') { /* cancel */
				foreach($selected_items as $item) {
					api_networks_cancel($item);
				}
			}
		}

		header('Location: automation_networks.php');

		exit;
	}

	/* setup some variables */
	$networks_list = ''; $i = 0;

	/* loop through each of the device types selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$networks_info = db_fetch_row_prepared('SELECT name FROM automation_networks WHERE id = ?', array($matches[1]));
			$networks_list .= '<li>' . html_escape($networks_info['name']) . '</li>';
			$networks_array[$i] = $matches[1];
		}

		$i++;
	}

	top_header();

	form_start('automation_networks.php');

	html_start_box($network_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (get_nfilter_request_var('drp_action') == '1') { /* delete */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to delete the following Network(s).') . "</p>
				<div class='itemlist'><ul>$networks_list</ul></div>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == '3') { /* enable */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to enable the following Network(s).') . "</p>
				<div class='itemlist'><ul>$networks_list</ul></div>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == '2') { /* disable */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to disable the following Network(s).') . "</p>
				<div class='itemlist'><ul>$networks_list</ul></div>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == '4') { /* discover now */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to discover the following Network(s).') . "</p>
				<div class='itemlist'><ul>$networks_list</ul></div>
				<p><input type='checkbox' id='discover_debug' name='discover_debug' value='1'>
				<label id='discover_debug_label' for='discover_debug'>" . __('Run discover in debug mode') . "</label></p>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == '5') { /* cancel discovery now */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to cancel on going Network Discovery(s).') . "</p>
				<div class='itemlist'><ul>$networks_list</ul></div>
			</td>
		</tr>";
	}

	if (!isset($networks_array)) {
		raise_message(40);
		header('Location: automation_networks.php');
		exit;
	} else {
		$save_html = "<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' name='save'>";
	}

	print "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($networks_array) ? serialize($networks_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>" . ($save_html != '' ? "
			<input type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo()' name='cancel' value='" . __esc('Cancel') . "'>
			$save_html" : "<input type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo()' name='cancel' value='" . __esc('Return') . "'>") . "
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function network_edit() {
	global $config, $ping_methods;;

	$ping_methods[PING_SNMP] = __('SNMP Get');

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$sched_types = array(
		'1' => __('Manual'),
		'2' => __('Daily'),
		'3' => __('Weekly'),
		'4' => __('Monthly'),
		'5' => __('Monthly on Day'));

	if (!isempty_request_var('id')) {
		$network = db_fetch_row_prepared('SELECT * FROM automation_networks WHERE id = ?', array(get_request_var('id')));
		$header_label = __esc('Network Discovery Range [edit: %s]', $network['name']);
	} else {
		$header_label = __('Network Discovery Range [new]');
	}

	/* file: mactrack_device_types.php, action: edit */
	$fields = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Settings'),
		'collapsible' => 'true'
		),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('Give this Network a meaningful name.'),
		'value' => '|arg1:name|',
		'max_length' => '250',
		'placeholder' => __('New Network Discovery Range')
		),
	'poller_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Data Collector'),
		'description' => __('Choose the Cacti Data Collector/Poller to be used to gather data from this Device.'),
		'value' => '|arg1:poller_id|',
		'default' => read_config_option('default_poller'),
		'sql' => 'SELECT id, name FROM poller ORDER BY name',
		),
	'site_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Associated Site'),
		'description' => __('Choose the Cacti Site that you wish to associate discovered Devices with.'),
		'value' => '|arg1:site_id|',
		'default' => read_config_option('default_site'),
		'sql' => 'SELECT id, name FROM sites ORDER BY name',
		'none_value' => __('None')
		),
	'subnet_range' => array(
		'method' => 'textarea',
		'friendly_name' => __('Subnet Range'),
		'description' => __('Enter valid Network Ranges separated by commas.  You may use an IP address, a Network range such as 192.168.1.0/24 or 192.168.1.0/255.255.255.0, or using wildcards such as 192.168.*.*'),
		'value' => '|arg1:subnet_range|',
		'textarea_rows' => '4',
		'textarea_cols' => '80',
		'max_length' => '1024',
		'placeholder' => '192.168.1.0/24'
		),
	'total_ips' => array(
		'method' => 'other',
		'friendly_name' => __('Total IP Addresses'),
		'description' => __('Total addressable IP Addresses in this Network Range.'),
		'value' => (isset($network['total_ips']) ? number_format_i18n($network['total_ips']) : 0)
		),
	'dns_servers' => array(
		'method' => 'textbox',
		'friendly_name' => __('Alternate DNS Servers'),
		'description' => __('A space delimited list of alternate DNS Servers to use for DNS resolution. If blank, the poller OS will be used to resolve DNS names.'),
		'value' => '|arg1:dns_servers|',
		'max_length' => '250',
		'placeholder' => __('Enter IPs or FQDNs of DNS Servers')
		),
	'sched_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Schedule Type'),
		'description' => __('Define the collection frequency.'),
		'value' => '|arg1:sched_type|',
		'array' => $sched_types,
		'default' => 1
		),
	'threads' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Discovery Threads'),
		'description' => __('Define the number of threads to use for discovering this Network Range.'),
		'value' => '|arg1:threads|',
		'array' => array(
			'1'  => __('%d Thread', 1),
			'2'  => __('%d Threads', 2),
			'3'  => __('%d Threads', 3),
			'4'  => __('%d Threads', 4),
			'5'  => __('%d Threads', 5),
			'6'  => __('%d Threads', 6),
			'7'  => __('%d Threads', 7),
			'8'  => __('%d Threads', 8),
			'9'  => __('%d Threads', 9),
			'10' => __('%d Threads', 10),
			'20' => __('%d Threads', 20),
			'50' => __('%d Threads', 50)
			),
		'default' => 1
		),
	'run_limit' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Run Limit'),
		'description' => __('After the selected Run Limit, the discovery process will be terminated.'),
		'value' => '|arg1:run_limit|',
		'array' => array(
			'60'    => __('%d Minute', 1),
			'300'   => __('%d Minutes', 5),
			'600'   => __('%d Minutes', 10),
			'1200'  => __('%d Minutes', 20),
			'1800'  => __('%d Minutes', 30),
			'3600'  => __('%d Hour', 1),
			'7200'  => __('%d Hours', 2),
			'14400' => __('%d Hours', 4),
			'28800' => __('%d Hours', 8),
			),
		'default' => 1200
		),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enabled'),
		'description' => __('Enable this Network Range.'),
		'value' => '|arg1:enabled|'
		),
	'enable_netbios' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enable NetBIOS'),
		'description' => __('Use NetBIOS to attempt to resolve the hostname of up hosts.'),
		'value' => '|arg1:enable_netbios|',
		'default' => ''
		),
	'add_to_cacti' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Automatically Add to Cacti'),
		'description' => __('For any newly discovered Devices that are reachable using SNMP and who match a Device Rule, add them to Cacti.'),
		'value' => '|arg1:add_to_cacti|'
		),
	'same_sysname' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Allow same sysName on different hosts'),
		'description' => __('When discovering devices, allow duplicate sysnames to be added on different hosts'),
		'value' => '|arg1:same_sysname|'
		),
	'rerun_data_queries' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Rerun Data Queries'),
		'description' => __('If a device previously added to Cacti is found, rerun its data queries.'),
		'value' => '|arg1:rerun_data_queries|'
		),
	'spacern' => array(
		'method' => 'spacer',
		'friendly_name' => __('Notification Settings'),
		'collapsible' => 'true'
		),
	'notification_enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Notification Enabled'),
		'description' => __('If checked, when the Automation Network is scanned, a report will be sent to the Notification Email account..'),
		'value' => '|arg1:notification_enabled|',
		'default' => ''
		),
	'notification_email' => array(
		'method' => 'textbox',
		'friendly_name' => __('Notification Email'),
		'description' => __('The Email account to be used to send the Notification Email to.'),
		'value' => '|arg1:notification_email|',
		'max_length' => '250',
		'default' => ''
		),
	'notification_fromname' => array(
		'method' => 'textbox',
		'friendly_name' => __('Notification From Name'),
		'description' => __('The Email account name to be used as the senders name for the Notification Email.  If left blank, Cacti will use the default Automation Notification Name if specified, otherwise, it will use the Cacti system default Email name'),
		'value' => '|arg1:notification_fromname|',
		'max_length' => '32',
		'size' => '30',
		'default' => ''
		),
	'notification_fromemail' => array(
		'method' => 'textbox',
		'friendly_name' => __('Notification From Email Address'),
		'description' => __('The Email Address to be used as the senders Email for the Notification Email.  If left blank, Cacti will use the default Automation Notification Email Address if specified, otherwise, it will use the Cacti system default Email Address'),
		'value' => '|arg1:notification_fromemail|',
		'max_length' => '128',
		'default' => ''
		),
	'spacer2' => array(
		'method' => 'spacer',
		'friendly_name' => __('Discovery Timing'),
		'collapsible' => 'true'
		),
	'start_at' => array(
		'method' => 'textbox',
		'friendly_name' => __('Starting Date/Time'),
		'description' => __('What time will this Network discover item start?'),
		'value' => '|arg1:start_at|',
		'max_length' => '30',
		'default' => date('Y-m-d H:i:s'),
		'size' => 20
		),
	'recur_every' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Rerun Every'),
		'description' => __('Rerun discovery for this Network Range every X.'),
		'value' => '|arg1:recur_every|',
		'default' => '1',
		'array' => array(
			1 => '1',
			2 => '2',
			3 => '3',
			4 => '4',
			5 => '5',
			6 => '6',
			7 => '7'
			),
		),
	'day_of_week' => array(
		'method' => 'drop_multi',
		'friendly_name' => __('Days of Week'),
		'description' => __('What Day(s) of the week will this Network Range be discovered.'),
		'array' => array(
			1 => __('Sunday'),
			2 => __('Monday'),
			3 => __('Tuesday'),
			4 => __('Wednesday'),
			5 => __('Thursday'),
			6 => __('Friday'),
			7 => __('Saturday')
			),
		'value' => '|arg1:day_of_week|',
		'class' => 'day_of_week'
		),
	'month' => array(
		'method' => 'drop_multi',
		'friendly_name' => __('Months of Year'),
		'description' => __('What Months(s) of the Year will this Network Range be discovered.'),
		'array' => array(
			1 => __('January'),
			2 => __('February'),
			3 => __('March'),
			4 => __('April'),
			5 => __('May'),
			6 => __('June'),
			7 => __('July'),
			8 => __('August'),
			9 => __('September'),
			10 => __('October'),
			11 => __('November'),
			12 => __('December')
			),
		'value' => '|arg1:month|',
		'class' => 'month'
		),
	'day_of_month' => array(
		'method' => 'drop_multi',
		'friendly_name' => __('Days of Month'),
		'description' => __('What Day(s) of the Month will this Network Range be discovered.'),
		'array' => array(1 => '1', 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32 => __('Last')),
		'value' => '|arg1:day_of_month|',
		'class' => 'days_of_month'
		),
	'monthly_week' => array(
		'method' => 'drop_multi',
		'friendly_name' => __('Week(s) of Month'),
		'description' => __('What Week(s) of the Month will this Network Range be discovered.'),
		'array' => array(
			1 => __('First'),
			2 => __('Second'),
			3 => __('Third'),
			'32' => __('Last')
			),
		'value' => '|arg1:monthly_week|',
		'class' => 'monthly_week'
		),
	'monthly_day' => array(
		'method' => 'drop_multi',
		'friendly_name' => __('Day(s) of Week'),
		'description' => __('What Day(s) of the week will this Network Range be discovered.'),
		'array' => array(
			1 => __('Sunday'),
			2 => __('Monday'),
			3 => __('Tuesday'),
			4 => __('Wednesday'),
			5 => __('Thursday'),
			6 => __('Friday'),
			7 => __('Saturday')
			),
		'value' => '|arg1:monthly_day|',
		'class' => 'monthly_day'
		),
	'spacer1' => array(
		'method' => 'spacer',
		'friendly_name' => __('Reachability Settings'),
		'collapsible' => 'true'
		),
	'snmp_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('SNMP Options'),
		'description' => __('Select the SNMP Options to use for discovery of this Network Range.'),
		'value' => '|arg1:snmp_id|',
		'sql' => 'SELECT id, name FROM automation_snmp ORDER BY name'
		),
	'ping_method' => array(
		'friendly_name' => __('Ping Method'),
		'description' => __('The type of ping packet to send.'),
		'value' => '|arg1:ping_method|',
		'method' => 'drop_array',
		'default' => read_config_option('ping_method'),
		'array' => $ping_methods
		),
	'ping_port' => array(
		'method' => 'textbox',
		'friendly_name' => __('Ping Port'),
		'value' => '|arg1:ping_port|',
		'description' => __('TCP or UDP port to attempt connection.'),
		'default' => read_config_option('ping_port'),
		'max_length' => 5,
		'size' => 5
		),
	'ping_timeout' => array(
		'friendly_name' => __('Ping Timeout Value'),
		'description' => __('The timeout value to use for host ICMP and UDP pinging.  This host SNMP timeout value applies for SNMP pings.'),
		'method' => 'textbox',
		'value' => '|arg1:ping_timeout|',
		'default' => read_config_option('ping_timeout'),
		'max_length' => 5,
		'size' => 5
		),
	'ping_retries' => array(
		'friendly_name' => __('Ping Retry Count'),
		'description' => __('After an initial failure, the number of ping retries Cacti will attempt before failing.'),
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

	form_start('automation_networks.php', 'form_network');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => 'true'),
			'fields' => inject_form_variables($fields, (isset($network) ? $network : array()))
		)
	);

	html_end_box(true, true);

	form_hidden_box('save_component_network', '1', '');
	form_hidden_box('id', !isempty_request_var('id') ? get_request_var('id'):0, 0);

	form_save_button('automation_networks.php', 'return');

	?>
	<script type='text/javascript'>
	$(function() {
		$('#day_of_week').multiselect({
			selectedList: 7,
			noneSelectedText: '<?php print __('Select the days(s) of the week');?>',
			header: false,
			height: 54,
			groupColumns: true,
			groupColumnsWidth: 90,
			menuWidth: 385
		});

		$('#month').multiselect({
			selectedList: 7,
			noneSelectedText: '<?php print __('Select the month(s) of the year');?>',
			header: false,
			height: 82,
			groupColumns: true,
			groupColumnsWidth: 90,
			menuWidth: 380
		});

		$('#day_of_month').multiselect({
			selectedList: 15,
			noneSelectedText: '<?php print __('Select the day(s) of the month');?>',
			header: false,
			height: 162,
			groupColumns: true,
			groupColumnsWidth: 50,
			menuWidth: 275
		});

		$('#monthly_week').multiselect({
			selectedList: 4,
			noneSelectedText: '<?php print __('Select the week(s) of the month');?>',
			header: false,
			height: 28,
			groupColumns: true,
			groupColumnsWidth: 70,
			menuWidth: 300
		});

		$('#monthly_day').multiselect({
			selectedList: 7,
			noneSelectedText: '<?php print __('Select the day(s) of the week');?>',
			header: false,
			height: 54,
			groupColumns: true,
			groupColumnsWidth: 90,
			menuWidth: 385
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

		$('#notification_enabled').click(function() {
			setNotification();
		});

		setNotification();

		$('#ping_method').change(function() {
			setPing();
		});

		setPing();
	});

	function setNotification() {
		if ($('#notification_enabled').is(':checked')) {
			$('#row_notification_email').show();
			$('#row_notification_fromname').show();
			$('#row_notification_fromemail').show();
		} else {
			$('#row_notification_email').hide();
			$('#row_notification_fromname').hide();
			$('#row_notification_fromemail').hide();
		}
	}

	function setPing() {
		$('#row_snmp_id').show();
		$('#row_ping_method').show();

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
				html = html.replace('<?php print __('every X Weeks');?>', '<?php print __('every X Days');?>');
				html = html.replace('<?php print __('every X.');?>', '<?php print __('every X Days.');?>');
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
				html = html.replace('<?php print __('every X Days');?>', '<?php print __('every X Weeks');?>');
				html = html.replace('<?php print __('every X.');?>', '<?php print __('every X Weeks.');?>');
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

function get_networks(&$sql_where, $rows, $apply_limits = true) {
	if (get_request_var('filter') != '') {
		$sql_where = ' WHERE (automation_networks.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	$sql_order = get_order_string();

	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	} else {
		$sql_limit = '';
	}

	$query_string = "SELECT automation_networks.*, poller.name AS data_collector
		FROM automation_networks
		LEFT JOIN poller
		ON automation_networks.poller_id=poller.id
		$sql_where
		$sql_order
		$sql_limit";

	return db_fetch_assoc($query_string);
}

function networks() {
	global $network_actions, $networkss, $config, $item_rows;

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
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '20'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
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

	validate_store_request_vars($filters, 'sess_networks');
	/* ================= input validation ================= */

	$refresh['page']    = 'automation_networks.php';
	$refresh['seconds'] = get_request_var('refresh');
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} elseif (get_request_var('rows') == -2) {
		$rows = 99999999;
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Network Filters'), '100%', '', '3', 'center', 'automation_networks.php?action=edit');
	networks_filter();
	html_end_box();

	$sql_where = '';

	$networks = get_networks($sql_where, $rows);

	$total_rows = db_fetch_cell('SELECT COUNT(*) FROM automation_networks ' . $sql_where);

	$nav = html_nav_bar('automation_networks.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 14, __('Networks'), 'page', 'main');

	form_start('automation_networks.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$sched_types = array(
		'1' => __('Manual'),
		'2' => __('Daily'),
		'3' => __('Weekly'),
		'4' => __('Monthly'),
		'5' => __('Monthly on Day')
	);

	$display_text = array(
		'name'           => array('display' => __('Network Name'), 'align' => 'left', 'sort' => 'ASC'),
		'data_collector' => array('display' => __('Data Collector'), 'align' => 'left', 'sort' => 'DESC'),
		'sched_type'     => array('display' => __('Schedule'), 'align' => 'left', 'sort' => 'DESC'),
		'total_ips'      => array('display' => __('Total IPs'), 'align' => 'right', 'sort' => 'DESC'),
		'nosort1'        => array('display' => __('Status'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __('The Current Status of this Networks Discovery')),
		'nosort2'        => array('display' => __('Progress'), 'align' => 'right', 'sort' => 'DESC', 'tip' => __('Pending/Running/Done')),
		'nosort3'        => array('display' => __('Up/SNMP Hosts'), 'align' => 'right', 'sort' => 'DESC'),
		'threads'        => array('display' => __('Threads'), 'align' => 'right', 'sort' => 'DESC'),
		'last_runtime'   => array('display' => __('Last Runtime'), 'align' => 'right', 'sort' => 'ASC'),
		'nosort4'        => array('display' => __('Next Start'), 'align' => 'right', 'sort' => 'ASC'),
		'last_started'   => array('display' => __('Last Started'), 'align' => 'right', 'sort' => 'ASC'));

	$status = 'Idle';

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($networks)) {
		foreach ($networks as $network) {
			if ($network['enabled'] == '') {
				$mystat   = "<span class='disabled'>" . __('Disabled') . "</span>";
				$progress = "0/0/0";
				$status   = array();
				$updown['up'] = $updown['snmp'] = '0';
			} else {
				$running = db_fetch_cell_prepared('SELECT COUNT(*)
					FROM automation_processes
					WHERE network_id = ?
					AND status != "done"',
					array($network['id']));

				if ($running > 0) {
					$status = db_fetch_row_prepared('SELECT
						COUNT(*) AS total,
						SUM(CASE WHEN status=0 THEN 1 ELSE 0 END) AS pending,
						SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS running,
						SUM(CASE WHEN status=2 THEN 1 ELSE 0 END) AS done
						FROM automation_ips
						WHERE network_id = ?', array($network['id']));

					$mystat   = "<span class='running'>" . __('Running') . "</span>";

					if (empty($status['total'])) {
						$progress = "0/0/0";
					} else {
						$progress = $status['pending'] . '/' . $status['running'] . '/' . $status['done'];
					}

					$updown = db_fetch_row_prepared("SELECT SUM(up_hosts) AS up, SUM(snmp_hosts) AS snmp
						FROM automation_processes
						WHERE network_id = ?", array($network['id']));

					if (empty($updown['up'])) {
						$updown['up']   = 0;
						$updown['snmp'] = 0;
					}
				} else {
					db_execute_prepared('DELETE FROM automation_processes
						WHERE network_id = ?',
						array($network['id']));

					$updown['up']   = $network['up_hosts'];
					$updown['snmp'] = $network['snmp_hosts'];

					$mystat   = "<span class='idle'>" . __('Idle') . "</span>";
					$progress = "0/0/0";
				}
			}

			form_alternate_row('line' . $network['id'], true);
			form_selectable_cell('<a class="linkEditMain" href="' . html_escape('automation_networks.php?action=edit&id=' . $network['id']) . '">' . html_escape($network['name']) . '</a>', $network['id']);
			form_selectable_ecell($network['data_collector'], $network['id']);
			form_selectable_cell($sched_types[$network['sched_type']], $network['id']);
			form_selectable_cell(number_format_i18n($network['total_ips']), $network['id'], '', 'right');
			form_selectable_cell($mystat, $network['id'], '', 'right');
			form_selectable_cell($progress, $network['id'], '', 'right');
			form_selectable_cell(number_format_i18n($updown['up']) . '/' . number_format_i18n($updown['snmp']), $network['id'], '', 'right');
			form_selectable_cell(number_format_i18n($network['threads']), $network['id'], '', 'right');
			form_selectable_cell(round($network['last_runtime'],2), $network['id'], '', 'right');
			form_selectable_cell($network['enabled'] == '' || $network['sched_type'] == '1' ? __('N/A'):($network['next_start'] == '0000-00-00 00:00:00' ? substr($network['start_at'],0,16):substr($network['next_start'],0,16)), $network['id'], '', 'right');
			form_selectable_cell($network['last_started'] == '0000-00-00 00:00:00' ? __('Never'):substr($network['last_started'],0,16), $network['id'], '', 'right');
			form_checkbox_cell($network['name'], $network['id']);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No Networks Found') . "</em></td></tr>";
	}
	html_end_box(false);

	if (cacti_sizeof($networks)) {
		/* put the nav bar on the bottom as well */
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($network_actions);

	form_end();
}

function networks_filter() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
			<form id='networks' action='automation_networks.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Networks');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='refresh' onChange='applyFilter()'>
							<?php
							$frequency = array(
								10  => __('%d Seconds', 10),
								20  => __('%d Seconds', 20),
								30  => __('%d Seconds', 30),
								45  => __('%d Seconds', 45),
								60  => __('%d Minute', 1),
								120 => __('%d Minutes', 2),
								300 => __('%d Minutes', 5)
							);

							foreach ($frequency as $r => $row) {
								echo "<option value='" . $r . "'" . (isset_request_var('refresh') && $r == get_request_var('refresh') ? ' selected' : '') . '>' . $row . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='go' title='<?php print __esc('Search');?>' value='<?php print __esc('Go');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' title='<?php print __esc('Clear Filtered');?>' value='<?php print __esc('Clear');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>
			function applyFilter() {
				strURL  = '?rows=' + $('#rows').val();
				strURL += '&filter=' + $('#filter').val();
				strURL += '&refresh=' + $('#refresh').val();

				loadUrl({url:strURL})
			}

			function clearFilter() {
				strURL = '?clear=true';

				loadUrl({url:strURL})
			}

			$(function() {
				$('#go').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#networks').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});
			</script>
		</td>
	</tr>
	<?php
}

