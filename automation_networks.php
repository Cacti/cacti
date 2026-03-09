<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

// include cacti base functions
require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/api_scheduler.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');

$actions = [
	1 => __('Delete'),
	2 => __('Disable'),
	8 => __('Change Network Settings'),
	7 => __('Duplicate'),
	3 => __('Enable'),
	6 => __('Export'),
	4 => __('Discover Now'),
	5 => __('Cancel Discovery')
];

// set default action
set_default_action();

switch (grv('action')) {
	case 'save':
		if (isrv('save_component_import')) {
			automation_import_process();
		} else {
			form_save();
		}

		break;
	case 'actions':
		form_actions();

		break;
	case 'import':
		top_header();
		automation_import();
		bottom_footer();

		break;
	case 'export':
		automation_export();

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

function automation_export() : void {
	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		$snmp_option_ids = [];

		if ($selected_items != false) {
			if (cacti_sizeof($selected_items) == 1) {
				$export_data = automation_network_export($selected_items[0]);
			} else {
				foreach ($selected_items as $id) {
					$snmp_option_ids[] = $id;
				}

				$export_data = automation_network_export($snmp_option_ids);
			}

			if (cacti_sizeof($export_data)) {
				$export_file_name = $export_data['export_name'];

				header('Content-type: application/json');
				header('Content-Disposition: attachment; filename=' . $export_file_name);

				$output = json_encode($export_data, JSON_PRETTY_PRINT);

				print $output;
			}
		}
	}
}

function automation_import() : void {
	$form_data = [
		'import_file' => [
			'friendly_name' => __('Import Network Discovery Rule from Local File'),
			'description'   => __('If the JSON file containing the Network Discovery Rule data is located on your local machine, select it here.'),
			'method'        => 'file',
			'accept'        => '.json'
		],
		'import_text' => [
			'method'        => 'textarea',
			'friendly_name' => __('Import Network Discovery Rule from Text'),
			'description'   => __('If you have the JSON file containing the Network Discovery Rule data as text, you can paste it into this box to import it.'),
			'value'         => '',
			'default'       => '',
			'textarea_rows' => '10',
			'textarea_cols' => '80',
			'class'         => 'textAreaNotes'
		]
	];

	form_start('automation_networks.php', 'chk', true);

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results'), '60%', false, 3, 'center', '');

		print '<tr class="tableHeader"><th>' . __('Cacti has imported the following items:') . '</th></tr>';

		foreach ($_SESSION['import_debug_info'] as $line) {
			print '<tr><td>' . $line . '</td></tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import Network Discovery Rule'), '60%', false, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => $form_data
		]
	);

	form_hidden_box('save_component_import', '1', '');

	print "	<tr><td><hr/></td></tr><tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='save'>
			<button type='submit' value='import' title='" . __esc('Import Network Discovery Rule') . "' class='ui-button ui-corner-all ui-widget ui-state-active'>" . __esc('Import') . "</button>
		</td>
		<script type='text/javascript'>
		$(function() {
			Pace.stop();
			clearAllTimeouts();
		});
		</script>
	</tr>";

	html_end_box();
}

function automation_import_process() : void {
	$json_data = json_decode(gnrv('import_text'), true);

	$debug_data = [];

	// If we have text, then we were trying to import text, otherwise we are uploading a file for import
	if (empty($json_data)) {
		$json_data = automation_validate_upload();
	}

	$return_data = automation_network_import($json_data);

	if (sizeof($return_data) && isset($return_data['success'])) {
		foreach ($return_data['success'] as $message) {
			$debug_data[] = '<span class="deviceUp">' . __('NOTE:') . '</span> ' . $message;
			cacti_log('NOTE: Automation Network Rule Import Succeeded!.  Message: ' . $message, false, 'AUTOM8');
		}
	}

	if (isset($return_data['errors'])) {
		foreach ($return_data['errors'] as $error) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $error;
			cacti_log('NOTE: Automation Network Rule Import Error!.  Message: ' . $error, false, 'AUTOM8');
		}
	}

	if (isset($return_data['failure'])) {
		foreach ($return_data['failure'] as $message) {
			$debug_data[] = '<span class="deviceDown">' . __('ERROR:') . '</span> ' . $message;
			cacti_log('NOTE: Automation Network Rule Import Failed!.  Message: ' . $message, false, 'AUTOM8');
		}
	}

	if (cacti_sizeof($debug_data)) {
		$_SESSION['import_debug_info'] = $debug_data;
	}

	header('Location: automation_networks.php?action=import');

	exit();
}

function form_save() : void {
	if (isrv('save_component_network')) {
		$network_id = api_networks_save($_POST);

		header('Location: automation_networks.php?action=edit&id=' . (empty($network_id) ? gnrv('id') : $network_id));
	}
}

function api_networks_remove(int $network_id) : void {
	db_execute_prepared('DELETE FROM automation_networks
		WHERE id = ?',
		[$network_id]
	);

	db_execute_prepared('DELETE FROM automation_devices
		WHERE network_id = ?',
		[$network_id]
	);
}

function api_networks_enable(int $network_id) : void {
	db_execute_prepared('UPDATE automation_networks
		SET enabled="on"
		WHERE id = ?',
		[$network_id]
	);
}

function api_networks_disable(int $network_id) : void {
	db_execute_prepared('UPDATE automation_networks
		SET enabled=""
		WHERE id = ?',
		[$network_id]
	);
}

function api_networks_cancel(int $network_id) : void {
	db_execute_prepared('UPDATE IGNORE automation_processes
		SET command="cancel"
		WHERE task="tmaster"
		AND network_id = ?',
		[$network_id]
	);
}

function api_networks_duplicate(int $network_id) : void {
	$save = db_fetch_row_prepared('SELECT *
		FROM automation_networks
		WHERE id = ?',
		[$network_id]);

	if (cacti_sizeof($save)) {
		$save['id']           = 0;
		$save['name']         = $save['name'] . ' (Duplicate)';
		$save['enabled']      = '';
		$save['up_hosts']     = 0;
		$save['snmp_hosts']   = 0;
		$save['next_start']   = '0000-00-00';
		$save['last_runtime'] = 0;
		$save['last_started'] = '0000-00-00';
		$save['last_status']  = '';

		$network_id = sql_save($save, 'automation_networks');
	}
}

/**
 * api_networks_change_options - Given a network_id and the post
 *   variable, update a series of Network settings
 *
 * @param mixed $network_ids A network id or an array of network ids
 * @param array $post        An array of post variables
 *
 * @return void
 */
function api_networks_change_options(mixed $network_ids, array $post) : void {
	if (!is_array($network_ids)) {
		$network_ids = [$network_ids];
	}

	$fields = network_get_field_array();

	foreach ($network_ids as $network_id) {
		foreach ($fields as $field_name => $field_array) {
			if (isset($post["t_$field_name"])) {
				db_execute_prepared("UPDATE automation_networks
					SET $field_name = ?
					WHERE id = ?",
					[gnrv($field_name), $network_id]);
			}
		}
	}
}

function api_networks_discover(int $network_id, bool $discover_debug, bool $discover_dryrun) : void {
	$enabled   = db_fetch_cell_prepared('SELECT enabled
		FROM automation_networks
		WHERE id = ?',
		[$network_id]
	);

	$running = db_fetch_cell_prepared('SELECT count(*)
		FROM automation_processes
		WHERE network_id = ?',
		[$network_id]
	);

	$name = db_fetch_cell_prepared('SELECT name
		FROM automation_networks
		WHERE id = ?',
		[$network_id]
	);

	$poller_id = db_fetch_cell_prepared('SELECT poller_id
		FROM automation_networks
		WHERE id = ?',
		[$network_id]
	);

	if ($enabled == 'on') {
		if (!$running) {
			if ($poller_id == POLLER_ID) {
				$args_debug  = ($discover_debug) ? ' --debug' : '';
				$args_debug .= ($discover_dryrun) ? ' --dryrun' : '';
				exec_background(read_config_option('path_php_binary'), '-q ' . read_config_option('path_webroot') . "/poller_automation.php --network=$network_id --force" . $args_debug);
			} else {
				$args_debug = ($discover_debug) ? '&debug=true' : '';

				$url = CACTI_PATH_URL . 'remote_agent.php?action=discover&network=' . $network_id . $args_debug;

				$response = call_remote_data_collector($poller_id, $url, 'AUTOM8');
			}
		} else {
			raise_message('automation_message', __esc('Can Not Restart Discovery for Discovery in Progress for Network \'%s\'', $name), MESSAGE_LEVEL_ERROR);
		}
	} else {
		raise_message('automation_message', __esc('Can Not Perform Discovery for Disabled Network \'%s\'', $name), MESSAGE_LEVEL_ERROR);
	}

	force_session_data();
}

function api_networks_save(array $post) : mixed {
	if (empty($post['network_id'])) {
		$save['id']            = form_input_validate($post['id'], 'id', '^[0-9]+$', false, 3);
		$save['hash']          = get_hash_automation($post['id'], 'automation_networks');

		// general information
		$save['name']          = form_input_validate($post['name'], 'name', '', false, 3);
		$save['poller_id']     = form_input_validate($post['poller_id'], 'poller_id', '^[0-9]+$', false, 3);
		$save['site_id']       = form_input_validate($post['site_id'], 'site_id', '^[0-9]+$', false, 3);
		$save['subnet_range']  = form_input_validate($post['subnet_range'], 'subnet_range', '', false, 3);
		$save['ignore_ips']    = form_input_validate($post['ignore_ips'], 'ignore_ips', '', true, 3);
		$save['dns_servers']   = form_input_validate($post['dns_servers'], 'dns_servers', '', true, 3);

		$save['threads']       = form_input_validate($post['threads'], 'threads', '^[0-9]+$', false, 3);
		$save['run_limit']     = form_input_validate($post['run_limit'], 'run_limit', '^[0-9]+$', false, 3);

		$save['enabled']              = (isset($post['enabled']) ? 'on' : '');

		// notification settings
		$save['notification_enabled'] = (isset($post['notification_enabled']) ? 'on' : '');
		$save['notification_email']   = form_input_validate($post['notification_email'], 'notification_email', '', true, 3);

		$save['notification_fromname']  = form_input_validate($post['notification_fromname'], 'notification_fromname', '', true, 3);
		$save['notification_fromemail'] = form_input_validate($post['notification_fromemail'], 'notification_fromemail', '', true, 3);

		$save['enable_netbios']       = (isset($post['enable_netbios']) ? 'on' : '');
		$save['add_to_cacti']         = (isset($post['add_to_cacti']) ? 'on' : '');
		$save['same_sysname']         = (isset($post['same_sysname']) ? 'on' : '');
		$save['rerun_data_queries']   = (isset($post['rerun_data_queries']) ? 'on' : '');

		// discovery connectivity settings
		$save['snmp_id']       = form_input_validate($post['snmp_id'], 'snmp_id', '^[0-9]+$', false, 3);
		$save['ping_method']   = form_input_validate($post['ping_method'], 'ping_method', '^[0-9]+$', false, 3);
		$save['ping_port']     = form_input_validate($post['ping_port'], 'ping_port', '^[0-9]+$', false, 3);
		$save['ping_timeout']  = form_input_validate($post['ping_timeout'], 'ping_timeout', '^[0-9]+$', false, 3);
		$save['ping_retries']  = form_input_validate($post['ping_retries'], 'ping_retries', '^[0-9]+$', false, 3);

		$save = api_scheduler_augment_save($save, $post);

		// validate the network definitions and rais error if failed
		$continue  = true;
		$total_ips = 0;
		$networks  = explode(',', $save['subnet_range']);

		if (cacti_sizeof($networks)) {
			foreach ($networks as $net) {
				$ips = automation_calculate_total_ips($net);

				if ($ips !== false) {
					$total_ips += $ips;
				} else {
					$continue = false;
					raise_message('automation_message', __esc('ERROR: Network \'%s\' is Invalid.', $net), MESSAGE_LEVEL_ERROR);

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

	return false;
}

function form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action');
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				foreach ($selected_items as $item) {
					api_networks_remove($item);
				}
			} elseif (gnrv('drp_action') == '3') { // enable
				foreach ($selected_items as $item) {
					api_networks_enable($item);
				}
			} elseif (gnrv('drp_action') == '2') { // disable
				foreach ($selected_items as $item) {
					api_networks_disable($item);
				}
			} elseif (gnrv('drp_action') == '4') { // run now
				$discover_debug  = isrv('discover_debug');
				$discover_dryrun = isrv('discover_dryrun');

				foreach ($selected_items as $item) {
					api_networks_discover($item, $discover_debug, $discover_dryrun);
				}

				sleep(2);
			} elseif (gnrv('drp_action') == '5') { // cancel
				foreach ($selected_items as $item) {
					api_networks_cancel($item);
				}
			} elseif (gnrv('drp_action') == '6') { // export
				top_header();

				print '<script text="text/javascript">
					function DownloadStart(url) {
						document.getElementById("download_iframe").src = url;
						setTimeout(function() {
							loadUrl({ url: "automation_networks.php" });
							Pace.stop();
						}, 500);
					}

					$(function() {
						//debugger;
						DownloadStart(\'automation_networks.php?action=export&selected_items=' . gnrv('selected_items') . '\');
					});
				</script>
				<iframe id="download_iframe" style="display:none;"></iframe>';

				bottom_footer();

				exit;
			} elseif (gnrv('drp_action') == '7') { // dupliciate
				foreach ($selected_items as $item) {
					api_networks_duplicate($item);
				}
			} elseif (gnrv('drp_action') == '8') { // change options
				foreach ($selected_items as $item) {
					api_networks_change_options($item, $_POST);
				}
			}
		}

		header('Location: automation_networks.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// defaults
		$header_array = [];

		// loop through each of the device types selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$networks_info = db_fetch_row_prepared('SELECT name FROM automation_networks WHERE id = ?', [$matches[1]]);

				$ilist .= '<li>' . htmle($networks_info['name']) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		if (cacti_sizeof($iarray) && grv('drp_action') == 8) {
			$form_array = [];

			$fields = network_get_field_array();

			foreach ($fields as $field_name => $field_array) {
				if ((preg_match('/^notification_/', $field_name)) ||
					(preg_match('/^ping_/', $field_name)) ||
					($field_name == 'poller_id') ||
					($field_name == 'site_id') ||
					($field_name == 'dns_servers') ||
					($field_name == 'enabled') ||
					($field_name == 'snmp_id') ||
					($field_name == 'enable_netbios') ||
					($field_name == 'add_to_cacti') ||
					($field_name == 'same_sysname') ||
					($field_name == 'sched_type') ||
					($field_name == 'threads') ||
					($field_name == 'run_limit') ||
					($field_name == 'recur_every') ||
					($field_name == 'day_of_week') ||
					($field_name == 'month') ||
					($field_name == 'day_of_month') ||
					($field_name == 'monthly_week') ||
					($field_name == 'monthly_day')
				) {
					$form_array += [$field_name => $fields[$field_name]];

					$form_array[$field_name]['value'] = '';

					if (read_config_option('hide_form_description') == 'on') {
						$form_array[$field_name]['description'] = '';
					}

					$form_array[$field_name]['form_id']      = 0;
					$form_array[$field_name]['sub_checkbox'] = [
						'name'          => 't_' . $field_name,
						'friendly_name' => __('Update this Field'),
						'class'         => 'ui-state-disabled',
						'value'         => ''
					];
				}
			}

			ob_start();

			draw_edit_form(
				[
					'config' => ['no_form_tag' => true],
					'fields' => $form_array
				]
			);

			network_edit_javascript();

			$header_array = ob_get_flush();
		}

		$form_data = [
			'general' => [
				'page'       => 'automation_networks.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following Network.'),
					'pmessage' => __('Click \'Continue\' to Delete the following Networks.'),
					'scont'    => __('Delete Network'),
					'pcont'    => __('Delete Networks')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Disable the following Network.'),
					'pmessage' => __('Click \'Continue\' to Disable the following Networks.'),
					'scont'    => __('Disable Network'),
					'pcont'    => __('Disable Networks')
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Enable the following Network.'),
					'pmessage' => __('Click \'Continue\' to Enable the following Networks.'),
					'scont'    => __('Enable Network'),
					'pcont'    => __('Enable Networks')
				],
				4 => [
					'smessage' => __('Click \'Continue\' to Discover the following Network.'),
					'pmessage' => __('Click \'Continue\' to Discover the following Networks.'),
					'scont'    => __('Discover Network'),
					'pcont'    => __('Discover Networks'),
					'extra'    => [
						'discover_dryrun' => [
							'method'  => 'checkbox',
							'title'   => __('Perform a Dry Run.  Do not add Devices'),
							'default' => ''
						],
						'discover_debug' => [
							'method'  => 'checkbox',
							'title'   => __('Enable Debug Logging'),
							'default' => ''
						]
					]
				],
				5 => [
					'message'  => __('Click \'Continue\' to cancel on going Network Discovery(s).'),
					'cont'     => __('Cancel Network Discovery'),
				],
				6 => [
					'smessage' => __('Click \'Continue\' to Export the following Network.'),
					'pmessage' => __('Click \'Continue\' to Export the following Networks.'),
					'scont'    => __('Export Network'),
					'pcont'    => __('Export Networks')
				],
				7 => [
					'smessage' => __('Click \'Continue\' to Duplicate the following Network.'),
					'pmessage' => __('Click \'Continue\' to Duplicate the following Networks.'),
					'scont'    => __('Duplicate Network'),
					'pcont'    => __('Duplicate Networks')
				],
				8 => [
					'smessage' => __('Click \'Continue\' to Change Network options for the following Network.  Check the checkboxes to indicate that this setting should be changed.'),
					'pmessage' => __('Click \'Continue\' to Change Network options for the following Networks.  Check the checkboxes to indicate that this setting should be changed.'),
					'scont'    => __('Change Network'),
					'pcont'    => __('Change Networks'),
					'header'   => $header_array
				],
			]
		];

		form_continue_confirmation($form_data);
	}
}

function network_get_field_array(array $network = []) : array {
	global $ping_methods, $sched_types;

	$ping_methods[PING_SNMP] = __('SNMP Get');

	// file: mactrack_device_types.php, action: edit
	$fields = [
		'spacer0' => [
			'method'        => 'spacer',
			'friendly_name' => __('General Settings'),
			'collapsible'   => 'true'
		],
		'name' => [
			'method'        => 'textbox',
			'friendly_name' => __('Name'),
			'description'   => __('Give this Network a meaningful name.'),
			'value'         => '|arg1:name|',
			'max_length'    => '250',
			'placeholder'   => __('New Network Discovery Range')
		],
		'poller_id' => [
			'method'        => 'drop_sql',
			'friendly_name' => __('Data Collector'),
			'description'   => __('Choose the Cacti Data Collector/Poller to be used to gather data from this Device.'),
			'value'         => '|arg1:poller_id|',
			'default'       => read_config_option('default_poller'),
			'sql'           => 'SELECT id, name FROM poller ORDER BY name',
		],
		'site_id' => [
			'method'        => 'drop_sql',
			'friendly_name' => __('Associated Site'),
			'description'   => __('Choose the Cacti Site that you wish to associate discovered Devices with.'),
			'value'         => '|arg1:site_id|',
			'default'       => read_config_option('default_site'),
			'sql'           => 'SELECT id, name FROM sites ORDER BY name',
			'none_value'    => __('None')
		],
		'subnet_range' => [
			'method'        => 'textarea',
			'friendly_name' => __('Subnet Range'),
			'description'   => __('Enter valid Network Ranges separated by commas.  You may use an IP address, a Network range such as 192.168.1.0/24 or 192.168.1.0/255.255.255.0, or using wildcards such as 192.168.*.*'),
			'value'         => '|arg1:subnet_range|',
			'textarea_rows' => '4',
			'textarea_cols' => '80',
			'max_length'    => '1024',
			'placeholder'   => '192.168.1.0/24'
		],
		'ignore_ips' => [
			'method'        => 'textarea',
			'friendly_name' => __('IP Addresses to Ignore'),
			'description'   => __('Enter valid comma separated list command of IP Addresses from this range to ignore.'),
			'value'         => '|arg1:ignore_ips|',
			'textarea_rows' => '2',
			'textarea_cols' => '80',
			'max_length'    => '1024',
			'placeholder'   => __('Comma delimited list of IP Addresses to not scan')
		],
		'total_ips' => [
			'method'        => 'other',
			'friendly_name' => __('Total IP Addresses'),
			'description'   => __('Total addressable IP Addresses in this Network Range.'),
			'value'         => (isset($network['total_ips']) ? number_format_i18n($network['total_ips']) : 0)
		],
		'dns_servers' => [
			'method'        => 'textbox',
			'friendly_name' => __('Alternate DNS Servers'),
			'description'   => __('A space delimited list of alternate DNS Servers to use for DNS resolution. If blank, the poller OS will be used to resolve DNS names.'),
			'value'         => '|arg1:dns_servers|',
			'max_length'    => '250',
			'placeholder'   => __('Enter IPs or FQDNs of DNS Servers')
		],
		'threads' => [
			'method'        => 'drop_array',
			'friendly_name' => __('Discovery Threads'),
			'description'   => __('Define the number of threads to use for discovering this Network Range.'),
			'value'         => '|arg1:threads|',
			'array'         => [
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
			],
			'default' => 1
		],
		'run_limit' => [
			'method'        => 'drop_array',
			'friendly_name' => __('Run Limit'),
			'description'   => __('After the selected Run Limit, the discovery process will be terminated.'),
			'value'         => '|arg1:run_limit|',
			'array'         => [
				'60'    => __('%d Minute', 1),
				'300'   => __('%d Minutes', 5),
				'600'   => __('%d Minutes', 10),
				'1200'  => __('%d Minutes', 20),
				'1800'  => __('%d Minutes', 30),
				'3600'  => __('%d Hour', 1),
				'7200'  => __('%d Hours', 2),
				'14400' => __('%d Hours', 4),
				'28800' => __('%d Hours', 8),
			],
			'default' => 1200
		],
		'enabled' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Enabled'),
			'description'   => __('Enable this Network Range.'),
			'value'         => '|arg1:enabled|'
		],
		'enable_netbios' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Enable NetBIOS'),
			'description'   => __('Use NetBIOS to attempt to resolve the hostname of up hosts.'),
			'value'         => '|arg1:enable_netbios|',
			'default'       => ''
		],
		'add_to_cacti' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Automatically Add to Cacti'),
			'description'   => __('For any newly discovered Devices that are reachable using SNMP and who match a Device Rule, add them to Cacti.'),
			'value'         => '|arg1:add_to_cacti|'
		],
		'same_sysname' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Allow same sysName on different hosts'),
			'description'   => __('When discovering devices, allow duplicate sysnames to be added on different hosts'),
			'value'         => '|arg1:same_sysname|'
		],
		'rerun_data_queries' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Rerun Data Queries'),
			'description'   => __('If a device previously added to Cacti is found, rerun its data queries.'),
			'value'         => '|arg1:rerun_data_queries|'
		],
	];

	$fields += api_scheduler_form();

	$fields += [
		'spacern' => [
			'method'        => 'spacer',
			'friendly_name' => __('Notification Settings'),
			'collapsible'   => 'true'
		],
		'notification_enabled' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Notification Enabled'),
			'description'   => __('If checked, when the Automation Network is scanned, a report will be sent to the Notification Email account..'),
			'value'         => '|arg1:notification_enabled|',
			'default'       => ''
		],
		'notification_email' => [
			'method'        => 'textbox',
			'friendly_name' => __('Notification Email'),
			'description'   => __('The Email account to be used to send the Notification Email to.'),
			'value'         => '|arg1:notification_email|',
			'max_length'    => '250',
			'default'       => ''
		],
		'notification_fromname' => [
			'method'        => 'textbox',
			'friendly_name' => __('Notification From Name'),
			'description'   => __('The Email account name to be used as the sender\'s name for the Notification Email.  If left blank, Cacti will use the default Automation Notification Name if specified, otherwise, it will use the Cacti system default Email name'),
			'value'         => '|arg1:notification_fromname|',
			'max_length'    => '32',
			'size'          => '30',
			'default'       => ''
		],
		'notification_fromemail' => [
			'method'        => 'textbox',
			'friendly_name' => __('Notification From Email Address'),
			'description'   => __('The Email Address to be used as the sender\'s Email for the Notification Email.  If left blank, Cacti will use the default Automation Notification Email Address if specified, otherwise, it will use the Cacti system default Email Address'),
			'value'         => '|arg1:notification_fromemail|',
			'max_length'    => '128',
			'default'       => ''
		],
		'spacer1' => [
			'method'        => 'spacer',
			'friendly_name' => __('Reachability Settings'),
			'collapsible'   => 'true'
		],
		'snmp_id' => [
			'method'        => 'drop_sql',
			'friendly_name' => __('SNMP Options'),
			'description'   => __('Select the SNMP Options to use for discovery of this Network Range.'),
			'value'         => '|arg1:snmp_id|',
			'sql'           => 'SELECT id, name FROM automation_snmp ORDER BY name'
		],
		'ping_method' => [
			'friendly_name' => __('Ping Method'),
			'description'   => __('The type of ping packet to send.'),
			'value'         => '|arg1:ping_method|',
			'method'        => 'drop_array',
			'default'       => read_config_option('ping_method'),
			'array'         => $ping_methods
		],
		'ping_port' => [
			'method'        => 'textbox',
			'friendly_name' => __('Ping Port'),
			'value'         => '|arg1:ping_port|',
			'description'   => __('TCP or UDP port to attempt connection.'),
			'default'       => read_config_option('ping_port'),
			'max_length'    => 5,
			'size'          => 5
		],
		'ping_timeout' => [
			'friendly_name' => __('Ping Timeout Value'),
			'description'   => __('The timeout value to use for host ICMP and UDP pinging.  This host SNMP timeout value applies for SNMP pings.'),
			'method'        => 'textbox',
			'value'         => '|arg1:ping_timeout|',
			'default'       => read_config_option('ping_timeout'),
			'max_length'    => 5,
			'size'          => 5
		],
		'ping_retries' => [
			'friendly_name' => __('Ping Retry Count'),
			'description'   => __('After an initial failure, the number of ping retries Cacti will attempt before failing.'),
			'method'        => 'textbox',
			'value'         => '|arg1:ping_retries|',
			'default'       => read_config_option('ping_retries'),
			'max_length'    => 5,
			'size'          => 5
		],
		'orig_start_at' => [
			'method' => 'hidden',
			'value'  => '|arg1:start_at|',
		],
		'orig_sched_type' => [
			'method' => 'hidden',
			'value'  => '|arg1:sched_type|',
		]
	];

	return $fields;
}

function network_edit_javascript() : void {
	api_scheduler_javascript();

	?>
	<script type='text/javascript'>
	$(function() {
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
		var showField = $('#notification_enabled').is(':checked');
		toggleFields({
			notification_email: showField,
			notification_fromname: showField,
			notification_fromemail: showField,
		});
	}

	function setPing() {
		var pingMethod = $('#ping_method').val();
		toggleFields({
			snmp_id: true,
			ping_method: true,
			ping_port: ping_method > 0,
			ping_timeout: ping_method > 1,
			ping_retries: ping_method > 1,
		});
	}
	</script>
	<?php
}

function network_edit() : void {
	global $ping_methods;

	$ping_methods[PING_SNMP] = __('SNMP Get');

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$network      = db_fetch_row_prepared('SELECT * FROM automation_networks WHERE id = ?', [grv('id')]);
		$header_label = __esc('Network Discovery Range [edit: %s]', $network['name']);
	} else {
		$network      = [];
		$header_label = __('Network Discovery Range [new]');
	}

	$fields = network_get_field_array($network);

	form_start('automation_networks.php', 'form_network');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => 'true'],
			'fields' => inject_form_variables($fields, $network)
		]
	);

	html_end_box(true, true);

	form_hidden_box('save_component_network', '1', '');
	form_hidden_box('id', !ierv('id') ? grv('id') : 0, 0);

	form_save_button('automation_networks.php', 'return');

	network_edit_javascript();
}

function get_networks(string &$sql_where, int $rows, bool $apply_limits = true) : mixed {
	if (grv('filter') != '') {
		$sql_where = ' WHERE (automation_networks.name LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	}

	$sql_order = get_order_string();

	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;
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

function networks() : void {
	global $actions, $networks, $item_rows, $sched_types;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Network Rules'), 'automation_networks.php', 'networks', 'sess_networks', 'automation_networks.php?action=edit');

	$pageFilter->rows_label  = __('Networks');
	$pageFilter->has_refresh = true;
	$pageFilter->def_refresh = 20;
	$pageFilter->render();

	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} elseif (grv('rows') == -2) {
		$rows = 99999999;
	} else {
		$rows = grv('rows');
	}

	$sql_where = '';

	$networks = get_networks($sql_where, $rows);

	$total_rows = db_fetch_cell('SELECT COUNT(*) FROM automation_networks ' . $sql_where);

	$nav = html_nav_bar('automation_networks.php', MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 14, __('Networks'), 'page', 'main');

	form_start('automation_networks.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name' => [
			'display' => __('Network Name'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'data_collector' => [
			'display' => __('Data Collector'),
			'align'   => 'left',
			'sort'    => 'DESC'
		],
		'sched_type' => [
			'display' => __('Schedule'),
			'align'   => 'left',
			'sort'    => 'DESC'
		],
		'total_ips' => [
			'display' => __('Total IPs'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'nosort1' => [
			'display' => __('Status'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The Current Status of this Networks Discovery')
		],
		'nosort2' => [
			'display' => __('Progress'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('Pending/Running/Done')
		],
		'nosort3' => [
			'display' => __('Up/SNMP Hosts'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'threads' => [
			'display' => __('Threads'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'last_runtime' => [
			'display' => __('Last Runtime'),
			'align'   => 'right',
			'sort'    => 'ASC'
		],
		'nosort4' => [
			'display' => __('Next Start'),
			'align'   => 'right',
			'sort'    => 'ASC'
		],
		'last_started' => [
			'display' => __('Last Started'),
			'align'   => 'right',
			'sort'    => 'ASC'
		]
	];

	$status = 'Idle';

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($networks)) {
		foreach ($networks as $network) {
			if ($network['enabled'] == '') {
				$mystat       = "<span class='disabled'>" . __('Disabled') . '</span>';
				$progress     = '0/0/0';
				$status       = [];
				$updown['up'] = $updown['snmp'] = '0';
			} else {
				$running = db_fetch_cell_prepared('SELECT COUNT(*)
					FROM automation_processes
					WHERE network_id = ?
					AND status != "done"',
					[$network['id']]
				);

				if ($running > 0) {
					$status = db_fetch_row_prepared('SELECT COUNT(*) AS total,
						SUM(CASE WHEN status=0 THEN 1 ELSE 0 END) AS pending,
						SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) AS running,
						SUM(CASE WHEN status=2 THEN 1 ELSE 0 END) AS done
						FROM automation_ips
						WHERE network_id = ?', [$network['id']]);

					$mystat   = "<span class='running'>" . __('Running') . '</span>';

					if (empty($status['total'])) {
						$progress = '0/0/0';
					} else {
						$progress = $status['pending'] . '/' . $status['running'] . '/' . $status['done'];
					}

					$updown = db_fetch_row_prepared('SELECT SUM(up_hosts) AS up, SUM(snmp_hosts) AS snmp
						FROM automation_processes
						WHERE network_id = ?', [$network['id']]);

					if (empty($updown['up'])) {
						$updown['up']   = 0;
						$updown['snmp'] = 0;
					}
				} else {
					db_execute_prepared('DELETE FROM automation_processes
						WHERE network_id = ?',
						[$network['id']]
					);

					$updown['up']   = $network['up_hosts'];
					$updown['snmp'] = $network['snmp_hosts'];

					$mystat   = "<span class='idle'>" . __('Idle') . '</span>';
					$progress = '0/0/0';
				}
			}

			form_alternate_row('line' . $network['id'], true);

			form_selectable_cell('<a class="linkEditMain" href="' . htmle('automation_networks.php?action=edit&id=' . $network['id']) . '">' . htmle($network['name']) . '</a>', $network['id']);
			form_selectable_ecell($network['data_collector'], $network['id']);
			form_selectable_cell($sched_types[$network['sched_type']], $network['id']);
			form_selectable_cell(number_format_i18n($network['total_ips']), $network['id'], '', 'right');
			form_selectable_cell($mystat, $network['id'], '', 'right');
			form_selectable_cell($progress, $network['id'], '', 'right');
			form_selectable_cell(number_format_i18n($updown['up']) . '/' . number_format_i18n($updown['snmp']), $network['id'], '', 'right');
			form_selectable_cell(number_format_i18n($network['threads']), $network['id'], '', 'right');
			form_selectable_cell(round($network['last_runtime'], 2), $network['id'], '', 'right');
			form_selectable_cell($network['enabled'] == '' || $network['sched_type'] == SCHEDULE_MANUAL ? __('N/A') : ($network['next_start'] == '0000-00-00 00:00:00' ? substr($network['start_at'], 0, 16) : substr($network['next_start'], 0, 16)), $network['id'], '', 'right');
			form_selectable_cell($network['last_started'] == '0000-00-00 00:00:00' ? __('Never') : substr($network['last_started'], 0, 16), $network['id'], '', 'right');
			form_checkbox_cell($network['name'], $network['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Networks Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($networks)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
