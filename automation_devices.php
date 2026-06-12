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

require('./include/auth.php');
require_once(CACTI_PATH_LIBRARY . '/api_aggregate.php');
require_once(CACTI_PATH_LIBRARY . '/api_automation.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_device.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/api_tree.php');
require_once(CACTI_PATH_LIBRARY . '/html_form_template.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/html_graph.php');
require_once(CACTI_PATH_LIBRARY . '/html_tree.php');
require_once(CACTI_PATH_LIBRARY . '/ping.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/reports.php');
require_once(CACTI_PATH_LIBRARY . '/rrd.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

$actions = [
	1 => __('Add Device'),
	2 => __('Delete Device')
];

$os_arr = array_rekey(db_fetch_assoc('SELECT DISTINCT os
	FROM automation_devices
	WHERE os IS NOT NULL AND os!=""'), 'os', 'os');

$status_arr = [
	__('Down'),
	__('Up')
];

$networks = array_rekey(db_fetch_assoc('SELECT an.id, an.name
	FROM automation_networks AS an
	INNER JOIN automation_devices AS ad
	ON an.id=ad.network_id
	ORDER BY name'), 'id', 'name');

set_default_action();

switch(grv('action')) {
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

function form_actions() : void {
	global $actions, $availability_options;

	// ================= input validation =================
	gfrv('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // add to cacti
				$i = 0;

				foreach ($selected_items as $id) {
					$d                        = db_fetch_row_prepared('SELECT * FROM automation_devices WHERE id = ?', [$id]);
					$d['poller_id']           = gfrv('poller_id');
					$d['host_template']       = gfrv('host_template');
					$d['availability_method'] = gfrv('availability_method');
					$d['notes']               = __('Added manually through device automation interface.');
					$d['snmp_sysName']        = $d['sysName'];

					// pull ping options from network_id
					$n = db_fetch_row_prepared('SELECT * FROM automation_networks WHERE id = ?', [$d['network_id']]);

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
			} elseif (gnrv('drp_action') == 2) { // remove device
				foreach ($selected_items as $id) {
					db_execute_prepared('DELETE FROM automation_devices WHERE id = ?', [$id]);
				}

				raise_message('automation_remove', __('Devices Removed from Cacti Automation database'), MESSAGE_LEVEL_INFO);
			}
		}

		header('Location: automation_devices.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// default variables
		$pollers        = [];
		$host_templates = [];
		$poller_id      = 0;

		$availability_method = 0;
		$host_template       = 0;

		// loop through each of the graphs selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT CONCAT(IF(hostname!="", hostname, "unknown"), " (", ip, ")") FROM automation_devices WHERE id = ?', [$matches[1]])) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		if (cacti_sizeof($iarray) && grv('drp_action') == '1') { // add
			$pollers = array_rekey(
				db_fetch_assoc_prepared('SELECT id, name
					FROM poller
					ORDER BY name'),
				'id', 'name'
			);

			$host_templates = array_rekey(
				db_fetch_assoc_prepared('SELECT id, name
					FROM host_template
					ORDER BY name'),
				'id', 'name'
			);

			$poller_id = db_fetch_cell_prepared('SELECT id FROM poller WHERE disabled = "" LIMIT 1');

			if (empty($poller_id)) {
				$poller_id = $pollers[0]['id'];
			}

			$devices = db_fetch_assoc('SELECT id, sysName, sysDescr
				FROM automation_devices
				WHERE id IN (' . implode(',', $iarray) . ')');

			foreach ($devices as $device) {
				$os = automation_find_os($device['sysDescr'], '', $device['sysName']);

				if (isset($os['host_template']) && $os['host_template'] > 0) {
					if ($host_template == 0) {
						$host_template       = $os['host_template'];
						$availability_method = $os['availability_method'];
					} elseif ($host_template != $os['host_template']) {
						$host_template       = 0;
						$availability_method = 0;

						break;
					}
				} else {
					$host_template       = 0;
					$availability_method = 0;

					break;
				}
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'automation_devices.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Add the following Discovered Device to Cacti.'),
					'pmessage' => __('Click \'Continue\' to Add the following Discovered Devices to Cacti.'),
					'scont'    => __('Add Discovered Device'),
					'pcont'    => __('Add Discovered Devices'),
					'extra'    => [
						'poller_id' => [
							'method'  => 'drop_array',
							'title'   => __('Poller'),
							'array'   => $pollers,
							'default' => $poller_id,
							'name'    => 'name',
							'id'      => 'id',
						],
						'host_template' => [
							'method'  => 'drop_array',
							'title'   => __('Device Template'),
							'array'   => $host_templates,
							'default' => $host_template,
							'name'    => 'name',
							'id'      => 'id',
						],
						'availability_method' => [
							'method'  => 'drop_array',
							'title'   => __('Availability Method'),
							'array'   => $availability_options,
							'default' => $availability_method,
							'name'    => 'name',
							'id'      => 'id',
						],
					]
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Remove the following Discovered Device.'),
					'pmessage' => __('Click \'Continue\' to Remove the following Discovered Devices.'),
					'scont'    => __('Remove Discovered Device'),
					'pcont'    => __('Remove Discovered Devices'),
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function display_discovery_page() : void {
	global $item_rows, $os_arr, $status_arr, $networks, $actions;

	top_header();

	draw_automation_devices_filter(true);

	$total_rows = 0;

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$results = get_discovery_results($total_rows, $rows);

	// generate page list
	$nav = html_nav_bar('automation_devices.php', MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 12, __('Devices'), 'page', 'main');

	form_start('automation_devices.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'host_id' => [
			'display' => __('Imported Device'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'hostname' => [
			'display' => __('Device Name'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'ip' => [
			'display' => __('IP'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'network_id' => [
			'display' => __('Network'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'sysName' => [
			'display' => __('SNMP Name'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'sysLocation' => [
			'display' => __('Location'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'sysContact' => [
			'display' => __('Contact'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'sysDescr' => [
			'display' => __('Description'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'os' => [
			'display' => __('OS'),
			'align'   => 'left',
			'sort'    => 'ASC'
		],
		'time' => [
			'display' => __('Uptime'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'snmp' => [
			'display' => __('SNMP'),
			'align'   => 'right',
			'sort'    => 'DESC'
		],
		'up' => [
			'display' => __('Status'),
			'align'   => 'right',
			'sort'    => 'ASC'
		],
		'mytime' => [
			'display' => __('Last Check'),
			'align'   => 'right',
			'sort'    => 'DESC'
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	$snmp_version        = read_config_option('snmp_version');
	$snmp_port           = read_config_option('snmp_port');
	$snmp_timeout        = read_config_option('snmp_timeout');
	$snmp_username       = read_config_option('snmp_username');
	$snmp_password       = read_config_option('snmp_password');
	$max_oids            = read_config_option('max_get_size');
	$ping_method         = read_config_option('ping_method');
	$availability_method = read_config_option('availability_method');

	$status = ["<span class='deviceDown'>" . __('Down') . '</span>', "<span class='deviceUp'>" . __('Up') . '</span>'];

	if (cacti_sizeof($results)) {
		foreach ($results as $host) {
			$description = get_device_description($host['host_id']);
			$network     = get_network_description($host['network_id']);

			form_alternate_row('line' . base64_encode($host['ip']), true);

			if ($host['hostname'] == '') {
				$host['hostname'] = __('Not Detected');
			}

			form_selectable_cell(filter_value($description, ''), $host['id']);
			form_selectable_cell(filter_value($host['hostname'], grv('filter')), $host['id']);
			form_selectable_cell(filter_value($host['ip'], grv('filter')), $host['id']);
			form_selectable_cell(filter_value($network, ''), $host['id']);
			form_selectable_cell(filter_value(snmp_data($host['sysName']), grv('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(filter_value(snmp_data($host['sysLocation']), grv('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(filter_value(snmp_data($host['sysContact']), grv('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(filter_value(snmp_data($host['sysDescr']), grv('filter')), $host['id'], '', 'text-align:left;white-space:normal;');
			form_selectable_cell(filter_value(snmp_data($host['os']), grv('filter')), $host['id'], '', 'text-align:left');
			form_selectable_cell(snmp_data(get_uptime($host)), $host['id'], '', 'text-align:right');
			form_selectable_cell($status[$host['snmp']], $host['id'], '', 'text-align:right');
			form_selectable_cell($status[$host['up']], $host['id'], '', 'text-align:right');
			form_selectable_cell(substr($host['mytime'],0,16), $host['id'], '', 'text-align:right');
			form_checkbox_cell($host['ip'], $host['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Devices Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($results)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();

	bottom_footer();
}

function get_device_description(int $id) : string {
	if ($id > 0) {
		$description = db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$id]);

		if (empty($description)) {
			return __('Removed from Cacti');
		} else {
			return $description;
		}
	} else {
		return __('Not In Cacti');
	}
}

function get_network_description(int $id) : string {
	if ($id > 0) {
		$description = db_fetch_cell_prepared('SELECT name FROM automation_networks WHERE id = ?', [$id]);

		if (empty($description)) {
			return __('Removed from Cacti');
		} else {
			return $description;
		}
	} else {
		return __('Invalid Network');
	}
}

function get_discovery_results(int &$total_rows = 0, int $rows = 0, bool $export = false) : array {
	global $os_arr, $status_arr, $networks, $actions;

	$sql_where  = '';
	$status     = grv('status');
	$network    = grv('network');
	$snmp       = grv('snmp');
	$os         = grv('os');
	$filter     = grv('filter');

	$sql_where  = '';
	$sql_params = [];

	if ($status != '-1') {
		$sql_where .= 'WHERE up = ?';
		$sql_params[] = $status;
	}

	if ($network > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'network_id = ?';
		$sql_params[] = $network;
	}

	if ($snmp != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'snmp = ?';
		$sql_params[] = $snmp;
	}

	if ($os != '-1' && in_array($os, $os_arr, true)) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'os = ?';
		$sql_params[] = $network;
	}

	if ($filter != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') .
			'(hostname LIKE ? OR ip LIKE ? OR sysName LIKE ? OR sysDescr LIKE ? OR sysLocation LIKE ? OR sysContact LIKE ?)';

		$sql_params[] = "%$filter%";
		$sql_params[] = "%$filter%";
		$sql_params[] = "%$filter%";
		$sql_params[] = "%$filter%";
		$sql_params[] = "%$filter%";
		$sql_params[] = "%$filter%";
	}

	if ($export) {
		return db_fetch_assoc_prepared("SELECT *
			FROM automation_devices
			$sql_where
			ORDER BY INET_ATON(ip)",
			$sql_params);
	} else {
		$total_rows = db_fetch_cell_prepared("SELECT
			COUNT(*)
			FROM automation_devices
			$sql_where",
			$sql_params);

		$page      = grv('page');
		$sql_order = get_order_string();
		$sql_limit = ' LIMIT ' . ($rows * ($page - 1)) . ',' . $rows;

		$sql_query = "SELECT *,sysUpTime snmp_sysUpTimeInstance, FROM_UNIXTIME(time) AS mytime
			FROM automation_devices
			$sql_where
			$sql_order
			$sql_limit";

		return db_fetch_assoc_prepared($sql_query, $sql_params);
	}
}

function create_automation_devices_filter() : array {
	global $item_rows, $os_arr, $status_arr, $networks;

	$any          = [-1 => __('Any')];
	$networks_arr = $any + $networks;
	$status_arr   = $any + $status_arr;
	$os_arr       = $any + $os_arr;

	return [
		'rows' => [
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'network' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Network'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $networks_arr,
					'value'         => '-1'
				]
			],
			[
				'status' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Status'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $status_arr,
					'value'         => '-1'
				],
				'os' => [
					'method'        => 'drop_array',
					'friendly_name' => __('OS'),
					'filter'        => FILTER_DEFAULT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $os_arr,
					'value'         => '-1'
				],
				'snmp' => [
					'method'        => 'drop_array',
					'friendly_name' => __('SNMP'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $status_arr,
					'value'         => '-1'
				],
				'rows' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Devices'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'   => 'submit',
				'display'  => __('Go'),
				'title'    => __('Apply filter to table'),
			],
			'clear' => [
				'method'   => 'button',
				'display'  => __('Clear'),
				'title'    => __('Reset filter to default values'),
			],
			'export' => [
				'method'   => 'button',
				'display'  => __('Export'),
				'action'   => 'default',
				'title'    => __('Export the Discovered Devices to CSV'),
				'callback' => 'document.location = \'automation_devices.php?action=export\''
			],
			'purge' => [
				'method'   => 'button',
				'display'  => __('Purge'),
				'action'   => 'default',
				'title'    => __('Purge the Discovered Devices from the Database'),
			]
		],
		'sort' => [
			'sort_column'    => 'hostname',
			'sort_direction' => 'ASC'
		]
	];
}

function draw_automation_devices_filter(bool $render = false) : void {
	global $item_rows, $filters, $os_arr, $status_arr, $networks, $actions;

	$filters = create_automation_devices_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Discovered Devices'), 'automation_devices.php', 'form_devices', 'sess_autom_device');

	$pageFilter->rows_label = __('Devices');
	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

function export_discovery_results() : void {
	draw_automation_devices_filter(false);

	$total_rows = 0;

	$results = get_discovery_results($total_rows, 0, true);

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=discovery_results.csv');
	print implode(',', [
		cacti_csv_cell('Host'),
		cacti_csv_cell('IP'),
		cacti_csv_cell('System Name'),
		cacti_csv_cell('System Location'),
		cacti_csv_cell('System Contact'),
		cacti_csv_cell('System Description'),
		cacti_csv_cell('OS'),
		cacti_csv_cell('Uptime'),
		cacti_csv_cell('SNMP'),
		cacti_csv_cell('Status')
	]) . "\n";

	if (cacti_sizeof($results)) {
		foreach ($results as $host) {
			if (isset($host['sysUpTime']) && $host['sysUpTime'] != 0) {
				$days   = intval($host['sysUpTime'] / 8640000);
				$hours  = intval(($host['sysUpTime'] - ($days * 8640000)) / 360000);
				$uptime = $days . ' days ' . $hours . ' hours';
			} else {
				$uptime = '';
			}

			print implode(',', [
				cacti_csv_cell($host['hostname'] == '' ? __('Not Detected') : $host['hostname']),
				cacti_csv_cell($host['ip']),
				cacti_csv_cell(export_data($host['sysName'])),
				cacti_csv_cell(export_data($host['sysLocation'])),
				cacti_csv_cell(export_data($host['sysContact'])),
				cacti_csv_cell(export_data($host['sysDescr'])),
				cacti_csv_cell(export_data($host['os'])),
				cacti_csv_cell(export_data($uptime)),
				cacti_csv_cell($host['snmp'] == 1 ? __('Up') : __('Down')),
				cacti_csv_cell($host['up'] == 1 ? __('Up') : __('Down'))
			]) . "\n";
		}
	}
}

function purge_discovery_results() : void {
	gfrv('network');

	if (grv('network') > 0) {
		db_execute_prepared('DELETE FROM automation_devices WHERE network_id = ?', [grv('network')]);
	} else {
		db_execute('TRUNCATE TABLE automation_devices');
	}

	header('Location: automation_devices.php');

	exit;
}

function snmp_data(string $item) : string {
	if ($item == '') {
		return __('N/A');
	} else {
		return htmle(str_replace(':',' ', $item));
	}
}

function export_data(string $item) : string {
	if ($item == '') {
		return 'N/A';
	} else {
		return $item;
	}
}
