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
require_once(CACTI_PATH_LIBRARY . '/poller.php');

// performing a full sync can take a lot of memory and time
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '900');

global $logfile_verbosity, $poller_sync_intervals;
global $database_default, $database_username, $database_password, $database_port;
global $database_retries, $database_ssl, $database_ssl_key, $database_ssl_cert;
global $database_ssl_ca, $database_ssl_capath, $database_ssl_verify_server_cert;

$actions = [
	POLLER_DELETE      => __('Delete'),
	POLLER_DISABLE     => __('Disable'),
	POLLER_ENABLE      => __('Enable'),
	POLLER_CLEAR_STATS => __('Clear Statistics'),
];

if (POLLER_ID == 1) {
	$actions += [POLLER_RESYNC   => __('Full Sync')];
	$actions += [POLLER_AUTHSYNC => __('Auth Sync')];
}

$poller_status = [
	POLLER_STATUS_NEW        => '<div class="deviceUnknown">' . __('New/Idle') . '</div>',
	POLLER_STATUS_RUNNING    => '<div class="deviceUp">' . __('Running') . '</div>',
	POLLER_STATUS_IDLE       => '<div class="deviceRecovering">' . __('Idle') . '</div>',
	POLLER_STATUS_DOWN       => '<div class="deviceDown">' . __('Down') . '</div>',
	POLLER_STATUS_DISABLED   => '<div class="deviceDisabled">' . __('Disabled') . '</div>',
	POLLER_STATUS_RECOVERING => '<div class="deviceDown">' . __('Recovering') . '</div>',
	POLLER_STATUS_HEARTBEAT  => '<div class="deviceDown">' . __('Heartbeat') . '</div>',
];

$logfile_verbosity = array_merge([-1 => __('Use Cacti Log Level')], $logfile_verbosity);

// file: pollers.php, action: edit
$fields_poller_edit = [
	'spacer0' => [
		'method'        => 'spacer',
		'friendly_name' => __('Data Collector Information'),
	],
	'name' => [
		'method'        => 'textbox',
		'friendly_name' => __('Name'),
		'description'   => __('The primary name for this Data Collector.'),
		'value'         => '|arg1:name|',
		'size'          => '50',
		'default'       => __('New Data Collector'),
		'max_length'    => '100'
	],
	'hostname' => [
		'method'        => 'textbox',
		'friendly_name' => __('Data Collector Hostname'),
		'description'   => __('The hostname for Data Collector.  It may have to be a Fully Qualified Domain name for the remote Pollers to contact it for activities such as re-indexing, Real-time graphing, etc.'),
		'value'         => '|arg1:hostname|',
		'size'          => '50',
		'default'       => '',
		'max_length'    => '100'
	],
	'log_level' => [
		'method'        => 'drop_array',
		'friendly_name' => __('Custom Log Level'),
		'description'   => __('In Cases where you need to perform debugging for a single Data Collector Only, you can change it\'s log level here.'),
		'value'         => '|arg1:log_level|',
		'default'       => '-1',
		'array'         => $logfile_verbosity,
	],
	'timezone' => [
		'method'        => 'drop_callback',
		'friendly_name' => __('TimeZone'),
		'description'   => __('The TimeZone for the Data Collector.'),
		'sql'           => 'SELECT Name AS id, Name AS name FROM mysql.time_zone_name ORDER BY name',
		'action'        => 'ajax_tz',
		'id'            => '|arg1:timezone|',
		'value'         => '|arg1:timezone|'
	],
	'notes' => [
		'method'        => 'textarea',
		'friendly_name' => __('Notes'),
		'description'   => __('Notes for this Data Collectors Database.'),
		'value'         => '|arg1:notes|',
		'textarea_rows' => 4,
		'textarea_cols' => 50
	],
	'spacer_collection' => [
		'method'        => 'spacer',
		'friendly_name' => __('Collection Settings'),
	],
	'processes' => [
		'method'        => 'textbox',
		'friendly_name' => __('Processes'),
		'description'   => __('The number of Data Collector processes to use to spawn.'),
		'value'         => '|arg1:processes|',
		'size'          => '10',
		'default'       => read_config_option('concurrent_processes'),
		'max_length'    => '4'
	],
	'threads' => [
		'method'        => 'textbox',
		'friendly_name' => __('Threads'),
		'description'   => __('The number of Spine Threads to use per Data Collector process.'),
		'value'         => '|arg1:threads|',
		'size'          => '10',
		'default'       => read_config_option('max_threads'),
		'max_length'    => '4'
	],
	'sync_interval' => [
		'method'        => 'drop_array',
		'friendly_name' => __('Sync Interval'),
		'description'   => __('The polling sync interval in use.  This setting will affect how often this poller is checked and updated.'),
		'value'         => '|arg1:sync_interval|',
		'default'       => read_config_option('poller_sync_interval'),
		'array'         => $poller_sync_intervals,
	],
	'spacer_remotedb' => [
		'method'        => 'spacer',
		'friendly_name' => __('Remote Database Connection'),
	],
	'dbhost' => [
		'method'        => 'textbox',
		'friendly_name' => __('Hostname'),
		'description'   => __('The hostname for the remote database server.'),
		'value'         => '|arg1:dbhost|',
		'size'          => '50',
		'default'       => '',
		'max_length'    => '100'
	],
	'dbdefault' => [
		'method'        => 'textbox',
		'friendly_name' => __('Remote Database Name'),
		'description'   => __('The name of the remote database.'),
		'value'         => '|arg1:dbdefault|',
		'size'          => '20',
		'default'       => $database_default,
		'max_length'    => '20'
	],
	'dbuser' => [
		'method'        => 'textbox',
		'friendly_name' => __('Remote Database User'),
		'description'   => __('The user name to use to connect to the remote database.'),
		'value'         => '|arg1:dbuser|',
		'size'          => '20',
		'default'       => $database_username,
		'max_length'    => '20'
	],
	'dbpass' => [
		'method'        => 'textbox_password',
		'friendly_name' => __('Remote Database Password'),
		'description'   => __('The user password to use to connect to the remote database.'),
		'value'         => '|arg1:dbpass|',
		'size'          => '40',
		'default'       => $database_password,
		'max_length'    => '64'
	],
	'dbport' => [
		'method'        => 'textbox',
		'friendly_name' => __('Remote Database Port'),
		'description'   => __('The TCP port to use to connect to the remote database.'),
		'value'         => '|arg1:dbport|',
		'size'          => '5',
		'default'       => $database_port,
		'max_length'    => '5'
	],
	'dbretries' => [
		'method'        => 'textbox',
		'friendly_name' => __('Remote Database Retries'),
		'description'   => __('The number of times to attempt to retry to connect to the remote database.'),
		'value'         => '|arg1:dbretries|',
		'size'          => '5',
		'default'       => $database_retries,
		'max_length'    => '5'
	],
	'spacerssl' => [
		'method'        => 'spacer',
		'friendly_name' => __('Remote Database SSL Information'),
		'description'   => __('Starting in MariaDB 11.4 and above, SSL is autonegotiated between the client and server.  So, this option may not be required')
	],
	'dbssl' => [
		'method'        => 'checkbox',
		'friendly_name' => __('Remote Database SSL'),
		'description'   => __('If the remote database uses SSL to connect, and it\'s prior to MariaDB 11.4, check the checkbox below to enter the details.'),
		'value'         => '|arg1:dbssl|',
		'default'       => $database_ssl ? 'on' : ''
	],
	'dbsslkey' => [
		'method'        => 'textbox',
		'friendly_name' => __('Remote Database SSL Key'),
		'description'   => __('The file holding the SSL Key to use to connect to the remote database.'),
		'value'         => '|arg1:dbsslkey|',
		'size'          => '50',
		'default'       => $database_ssl_key,
		'max_length'    => '255'
	],
	'dbsslcert' => [
		'method'        => 'textbox',
		'friendly_name' => __('Remote Database SSL Certificate'),
		'description'   => __('The file holding the SSL Certificate to use to connect to the remote database.'),
		'value'         => '|arg1:dbsslcert|',
		'size'          => '50',
		'default'       => $database_ssl_cert,
		'max_length'    => '255'
	],
	'dbsslca' => [
		'method'        => 'textbox',
		'friendly_name' => __('Remote Database SSL Authority'),
		'description'   => __('The file holding the SSL Certificate Authority to use to connect to the remote database.  This is an optional parameter that can be required by the database provider if they have started SSL using the --ssl-mode=VERIFY_CA option.'),
		'value'         => '|arg1:dbsslca|',
		'size'          => '50',
		'default'       => $database_ssl_ca,
		'max_length'    => '255'
	],
	'dbsslcapath' => [
		'method'        => 'textbox',
		'friendly_name' => __('Remote Database SSL Authorities directory'),
		'description'   => __('The file path to the directory that contains the trusted SSL Certificate Authority certificates. This is an optional parameter that can used instead of giving the path to an individual Certificate Authority file. This parameter can be required by the database provider if they have started SSL using the --ssl-mode=VERIFY_CA option.'),
		'value'         => '|arg1:dbsslcapath|',
		'size'          => '50',
		'default'       => $database_ssl_capath,
		'max_length'    => '255'
	],
	'dbsslverifyservercert' => [
		'method'        => 'checkbox',
		'friendly_name' => __('Remote Database SSL'),
		'description'   => __("Provides a way to disable verification of the server's SSL certificate Common Name against the server's hostname when connecting. This verification is enabled by default."),
		'value'         => '|arg1:dbsslverifyservercert|',
		'default'       => $database_ssl_verify_server_cert ? 'on' : ''
	],
	'spacertest' => [
		'method'        => 'spacer',
		'friendly_name' => __('Test Connection'),
	],
	'id' => [
		'method' => 'hidden',
		'value'  => '|arg1:id|',
	],
	'save_component_poller' => [
		'method' => 'hidden',
		'value'  => '1'
	]
];

// set default action
set_default_action();

switch (grv('action')) {
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
			['%' . gnrv('term') . '%']
		));

		break;
	case 'ping':
		test_database_connection();

		break;
	case 'edit':
		top_header();

		poller_edit();

		bottom_footer();

		break;
	default:
		top_header();

		pollers();

		bottom_footer();

		break;
}

function form_save() : void {
	if (isrv('save_component_poller')) {
		// Common data
		$save['id']        = gfrv('id');
		$save['name']      = form_input_validate(gnrv('name'), 'name', '', false, 3);
		$save['hostname']  = form_input_validate(gnrv('hostname'), 'hostname', '', false, 3);
		$save['log_level'] = form_input_validate(gnrv('log_level'), 'log_level', '', false, 3);
		$save['timezone']  = form_input_validate(gnrv('timezone'), 'timezone', '', false, 3);
		$save['notes']     = form_input_validate(gnrv('notes'), 'notes', '', true, 3);

		// Process settings
		$save['processes'] = form_input_validate(gnrv('processes'), 'processes', '^[0-9]+$', false, 3);
		$save['threads']   = form_input_validate(gnrv('threads'), 'threads', '^[0-9]+$', false, 3);

		if ($save['id'] != 1) {
			$save['sync_interval'] = form_input_validate(gnrv('sync_interval'), 'sync_interval', '^[0-9]+$', false, 3);

			// Database settings
			$save['dbdefault']             = form_input_validate(gnrv('dbdefault'), 'dbdefault', '', true, 3);
			$save['dbhost']                = form_input_validate(gnrv('dbhost'),    'dbhost',    '', true, 3);
			$save['dbuser']                = form_input_validate(gnrv('dbuser'),    'dbuser',    '', true, 3);
			$save['dbpass']                = form_input_validate(gnrv('dbpass'),    'dbpass',    '', true, 3);
			$save['dbport']                = form_input_validate(gnrv('dbport'),    'dbport',    '^[0-9]+$', true, 3);
			$save['dbretries']             = form_input_validate(gnrv('dbretries'), 'dbretries', '^[0-9]+$', true, 3);
			$save['dbssl']                 = isrv('dbssl') ? 'on' : '';
			$save['dbsslkey']              = form_input_validate(gnrv('dbsslkey'),  'dbsslkey',  '', true, 3);
			$save['dbsslcert']             = form_input_validate(gnrv('dbsslcert'), 'dbsslcert', '', true, 3);
			$save['dbsslca']               = form_input_validate(gnrv('dbsslca'),   'dbsslca',   '', true, 3);
			$save['dbsslcapath']           = form_input_validate(gnrv('dbsslcapath'), 'dbsslcapath',   '', true, 3);
			$save['dbsslverifyservercert'] = isrv('dbsslverifyservercert') ? 'on' : '';
		}

		// Check for duplicate hostname
		$error = false;

		if (poller_check_duplicate_poller_id($save['id'], $save['hostname'], 'hostname')) {
			raise_message('dupe_hostname', __esc('You have already used this hostname \'%s\'.  Please enter a non-duplicate hostname.', $save['hostname']), MESSAGE_LEVEL_ERROR);
			$error = true;
		}

		if (isset($save['dbhost'])) {
			if (poller_check_duplicate_poller_id($save['id'], $save['dbhost'], 'dbhost')) {
				raise_message('dupe_dbhost', __esc('You have already used this database hostname \'%s\'.  Please enter a non-duplicate database hostname.', $save['hostname']), MESSAGE_LEVEL_ERROR);
				$error = true;
			}
		}

		if (isset($save['dbhost']) && $save['dbhost'] == 'localhost' && $save['id'] > 1) {
			raise_message('poller_dbhost');
		} elseif ($save['id'] > 1 && poller_host_duplicate($save['id'], $save['dbhost'])) {
			raise_message('poller_nodupe');
		} elseif (!is_error_message() && $error == false) {
			$poller_id = sql_save($save, 'poller');

			if ($poller_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: pollers.php?action=edit&id=' . (empty($poller_id) ? gnrv('id') : $poller_id));
	}
}

function poller_check_duplicate_poller_id(int $poller_id, string $hostname, string $column) : bool {
	$ip_addresses  = [];
	$ip_hostnames  = [];

	if (is_ipaddress($hostname)) {
		$address = @gethostbyaddr($hostname);

		if ($address != $hostname) {
			$ip_hostnames[$address] = $address;
		} else {
			$ip_addresses[$address] = $address;
		}

		$ip_addresses[$hostname] = $hostname;
	} elseif (str_contains($hostname, '.')) {
		$addresses = @dns_get_record($hostname);
		$ip        = @gethostbyname($hostname);

		if ($ip != $hostname) {
			$ip_addresses[$ip] = $ip;
		}

		$ip_hostnames[$hostname] = $hostname;

		if (cacti_sizeof($addresses)) {
			foreach ($addresses as $address) {
				if (isset($address['target'])) {
					$ip_hostnames[$address['host']] = $address['host'];
				}

				if (isset($address['host'])) {
					$ip_hostnames[$address['host']] = $address['host'];
				}

				if (isset($address['ip'])) {
					$ip_addresses[$address['ip']] = $address['ip'];
				}
			}
		}
	} else {
		$ip_hostname[$hostname] = $hostname;

		$address = @gethostbyname($hostname);

		if ($address != $hostname) {
			$ip_addresses[$address] = $address;
		}
	}

	$sql_where1 = '';

	if (cacti_sizeof($ip_addresses)) {
		$sql_where1 = "$column IN ('" . implode("','", $ip_addresses) . "')";
	}

	$sql_where2 = '';

	if (cacti_sizeof($ip_hostnames)) {
		foreach ($ip_hostnames as $host) {
			$parts = explode('.', $host);
			$sql_where2 .= ($sql_where2 != '' ? ' OR ' : ' (') .
				"($column = " . db_qstr($parts[0]) .
				" OR $column = " . db_qstr($host) . ')';
		}
		$sql_where2 .= ')';
	}

	if ($sql_where1 != '' || $sql_where2 != '') {
		$sql_where = ' AND (' . $sql_where1 . ($sql_where1 != '' && $sql_where2 != '' ? ' OR ' : '') . $sql_where2 . ')';
	} else {
		$sql_where = '';
	}

	$duplicate = db_fetch_cell_prepared("SELECT id
		FROM poller
		WHERE id != ?
		$sql_where",
		[$poller_id]
	);

	if (empty($duplicate)) {
		return false;
	} else {
		return true;
	}
}

function poller_host_duplicate(int $poller_id, string $host) : mixed {
	if ($host == 'localhost') {
		return true;
	} else {
		return db_fetch_cell_prepared('SELECT COUNT(*)
			FROM poller
			WHERE dbhost LIKE ?
			AND id != ?',
			[$host . '%', $poller_id]);
	}
}

function form_actions() : void {
	global $actions;

	// ================= input validation =================
	gfrv('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	// ====================================================

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == POLLER_DELETE) { // delete
				db_execute('DELETE FROM poller WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('UPDATE host SET poller_id=1 WHERE deleted="" AND ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE automation_networks SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE automation_processes SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE poller_command SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE poller_item SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE poller_output_realtime SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));
				db_execute('UPDATE poller_time SET poller_id=1 WHERE ' . array_to_sql_or($selected_items, 'poller_id'));

				cacti_log('NOTE: The poller(s) with the id(s): ' . implode(',', $selected_items) . ' deleted by user ' . $_SESSION[SESS_USER_ID], false, 'WEBUI');
			} elseif (grv('drp_action') == POLLER_DISABLE) { // disable
				db_execute('UPDATE poller SET disabled="on" WHERE ' . array_to_sql_or($selected_items, 'id'));

				cacti_log('NOTE: The poller(s) with the id(s): ' . implode(',', $selected_items) . ' disabled by user ' . $_SESSION[SESS_USER_ID], false, 'WEBUI');
			} elseif (grv('drp_action') == POLLER_ENABLE) { // enable
				db_execute('UPDATE poller SET disabled="" WHERE ' . array_to_sql_or($selected_items, 'id'));

				cacti_log('NOTE: The poller(s) with the id(s): ' . implode(',', $selected_items) . ' enabled by user ' . $_SESSION[SESS_USER_ID], false, 'WEBUI');
			} elseif (grv('drp_action') == POLLER_RESYNC || grv('drp_action') == POLLER_AUTHSYNC) { // full or auth sync
				cacti_session_close();

				if (grv('drp_action') == POLLER_RESYNC) {
					$class = 'all';
				} else {
					$class = 'auth';
				}

				$success = [];
				$failed  = [];
				$ids     = [];

				foreach ($selected_items as $item) {
					// Operation not allowed on the main poller
					if ($item == 1) {
						continue;
					}

					$ids[]   = $item;

					$poller = db_fetch_row_prepared('SELECT *
						FROM poller
						WHERE id = ?',
						[$item]
					);

					if ($poller['dbhost'] == 'localhost') {
						raise_message('poller_dbhost');

						continue;
					}

					if ($item == 1) {
						raise_message('poller_nomain');

						continue;
					} else {
						if (replicate_out($item, $class)) {
							$success[] = $item;

							if ($class == 'all') {
								db_execute_prepared('UPDATE poller
									SET last_sync = NOW()
									WHERE id = ?',
									[$item]
								);
							}
						} else {
							$failed[] = $item;
						}
					}
				}

				cacti_session_start();

				if (cacti_sizeof($failed)) {
					cacti_log('WARNING: Some Selected Remote Data Collectors in [' . implode(', ', $ids) . '] failed synchronization by user ' . get_username($_SESSION[SESS_USER_ID]) . ', Successful/Failed[' . cacti_sizeof($success) . '/' . cacti_sizeof($failed) . '].  See log for details.', false, 'WEBUI');
				} else {
					cacti_log('NOTE: All Selected Remote Data Collectors in [' . implode(', ', $ids) . '] synchronized correctly by user ' . get_username($_SESSION[SESS_USER_ID]), false, 'WEBUI');
				}
			} elseif (grv('drp_action') == '5') { // clear statistics
				foreach ($selected_items as $item) {
					db_execute_prepared('UPDATE poller
						SET total_time = 0, max_time = 0, min_time = 9999999, avg_time = 0, total_polls = 0
						WHERE id = ?',
						[$item]
					);
				}

				raise_message('poller_clear', __('Data Collector Statistics cleared.'), MESSAGE_LEVEL_INFO);
			}
		}

		header('Location: pollers.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// loop through each of the graphs selected on the previous page and get more info about them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM poller WHERE id = ?', [$matches[1]])) . '</li>';

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'pollers.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				POLLER_DELETE => [
					'smessage' => __('Click \'Continue\' to Delete the following Data Collector.'),
					'pmessage' => __('Click \'Continue\' to Delete the following Data Collectors.'),
					'scont'    => __('Delete Data Collector'),
					'pcont'    => __('Delete Data Collectors')
				],
				POLLER_DISABLE => [
					'smessage' => __('Click \'Continue\' to Disable the following Data Collector.'),
					'pmessage' => __('Click \'Continue\' to Disable the following Data Collectors.'),
					'scont'    => __('Disable Data Collector'),
					'pcont'    => __('Disable Data Collectors')
				],
				POLLER_ENABLE => [
					'smessage' => __('Click \'Continue\' to Enable the following Data Collector.'),
					'pmessage' => __('Click \'Continue\' to Enable the following Data Collectors.'),
					'scont'    => __('Enable Data Collector'),
					'pcont'    => __('Enable Data Collectors')
				],
				POLLER_RESYNC => [
					'smessage' => __('Click \'Continue\' to Synchronize the Remote Data Collector for Offline Operation.'),
					'pmessage' => __('Click \'Continue\' to Synchronize the Remote Data Collectors for Offline Operation.'),
					'scont'    => __('Resync Data Collector'),
					'pcont'    => __('Resync Data Collectors')
				],
				POLLER_AUTHSYNC => [
					'smessage' => __('Click \'Continue\' to Synchronize the Authentication Data to Remote Data Collector.'),
					'pmessage' => __('Click \'Continue\' to Synchronize the Authentication Data to Remote Data Collectors.'),
					'scont'    => __('Resync Auth Data to Collector'),
					'pcont'    => __('Resync Auth Data to Collectors')
				],
				POLLER_CLEAR_STATS => [
					'smessage' => __('Click \'Continue\' to Clear Statistics for the following Data Collector.'),
					'pmessage' => __('Click \'Continue\' to Clear Statistics for following Data Collectors.'),
					'scont'    => __('Resync Data Collector'),
					'pcont'    => __('Resync Data Collectors')
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function poller_edit() : void {
	global $fields_poller_edit;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	if (!ierv('id')) {
		$poller = db_fetch_row_prepared('SELECT *
			FROM poller
			WHERE id = ?',
			[grv('id')]
		);

		$header_label = __esc('Site [edit: %s]', $poller['name']);
	} else {
		$poller = [];

		$header_label = __('Site [new]');
	}

	form_start('pollers.php', 'poller');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	if (cacti_sizeof($poller)) {
		if ($poller['id'] == 1) {
			unset($fields_poller_edit['sync_interval']);
			unset($fields_poller_edit['spacer_remotedb']);
			unset($fields_poller_edit['dbdefault']);
			unset($fields_poller_edit['dbhost']);
			unset($fields_poller_edit['dbuser']);
			unset($fields_poller_edit['dbpass']);
			unset($fields_poller_edit['dbport']);
			unset($fields_poller_edit['dbretries']);
			unset($fields_poller_edit['spacerssl']);
			unset($fields_poller_edit['dbssl']);
			unset($fields_poller_edit['dbsslkey']);
			unset($fields_poller_edit['dbsslcert']);
			unset($fields_poller_edit['dbsslca']);
			unset($fields_poller_edit['dbsslcapath']);
			unset($fields_poller_edit['dbsslverifyservercert']);

			$fields_poller_edit['log_level']['method'] = 'hidden';
		}

		if ($poller['timezone'] == '') {
			$poller['timezone'] = ini_get('date.timezone');
		}
	}

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($fields_poller_edit, $poller)
		]
	);

	$tip_text = __('Remote Data Collectors must be able to communicate to the Main Data Collector, and vice versa.  Use this button to verify that the Main Data Collector can communicate to this Remote Data Collector.');

	if (read_config_option('hide_form_description') == 'on') {
		$tooltip = '<br><span class="formFieldDescription">' . $tip_text . '</span>';
	} else {
		$tooltip = '<div class="formTooltip">' . str_replace("\n", '', display_tooltip($tip_text)) . '</div>';
	}

	$row_html = '<div class="formRow odd"><div class="formColumnLeft"><div class="formFieldName">' . __('Test Database Connection') . $tooltip . '</div></div><div class="formColumnRight"><button type="button" class="ui-button ui-corner-all ui-widget" id="dbtest">' . __esc('Test Connection') . '</button><span id="results"></span></div></div>';

	$pt = read_config_option('poller_type');

	if (cacti_sizeof($poller)) {
		if ($poller['id'] > 1) {
			?>
			<script type='text/javascript'>
				pt = <?php print $pt; ?>;

				function showHideRemoteDB() {
					var hasSSL = $('#dbssl').is(':checked');
					toggleFields({
						dbsslkey: hasSSL,
						dbsslcert: hasSSL,
						dbsslca: hasSSL,
						dbsslcapath: hasSSL,
						dbsslverifyservercert: hasSSL,
					});
				}

				$(function() {
					$('#row_spacertest').after('<?php print $row_html; ?>');
					$('#dbssl').click(function() {
						showHideRemoteDB();
					});

					$('#dbtest').click(function(e) {
						e.preventDefault();
						ping_database();
					});

					showHideRemoteDB();

					if (pt == 1) {
						$('#row_threads').hide();
					}
				});

				function ping_database() {
					dbssl = $('#dbssl').is(':checked') ? 'on' : '';
					dbsslverifyservercert = $('#dbsslverifyservercert').is(':checked') ? 'on' : '';

					var options = {
						url: 'pollers.php',
						funcEnd: 'pingDatabaseFinalize',
						handle: false
					};

					var data = {
						__csrf_magic: csrfMagicToken,
						action: 'ping',
						dbdefault: $('#dbdefault').val(),
						dbhost: $('#dbhost').val(),
						dbuser: $('#dbuser').val(),
						dbpass: $('#dbpass').val(),
						dbport: $('#dbport').val(),
						dbretries: $('#dbretries').val(),
						dbssl: dbssl,
						dbsslkey: $('#dbsslkey').val(),
						dbsslcert: $('#dbsslcert').val(),
						dbsslca: $('#dbsslca').val(),
						dbsslcapath: $('#dbsslcapath').val(),
						dbsslverifyservercert: dbsslverifyservercert
					};

					postUrl(options, data);
				}

				function pingDatabaseFinalize(options, data) {
					$('#results').empty().show().html(data).fadeOut(5000);
				}
			</script>
			<?php
		} else {
			?>
			<script type='text/javascript'>
				pt = <?php print $pt; ?>;

				$(function() {
					if (pt == 1) {
						$('#row_threads').hide();
					}
				});
			</script>
			<?php
		}
	}

	html_end_box(true, true);

	$form_buttons = [];

	if ($poller['id'] > 1) {
		$form_buttons[] = [
			'id'     => 'delete',
			'value'  => __esc('Delete'),
			'method' => 'post',
			'url'    => 'pollers.php',
			'data'   => json_encode(
				[
					'action'               => 'actions',
					'drp_action'           => POLLER_DELETE,
					'chk_' . $poller['id'] => 'on',
					'__csrf_magic'         => csrf_get_tokens(),
				]
			),
		];
	}

	$form_buttons = array_merge($form_buttons, [
		[
			'id'     => 'return',
			'value'  => __esc('Return'),
			'url'    => 'pollers.php',
			'method' => 'return',
		],
		[
			'id'     => 'save',
			'value'  => __esc('Save'),
			'type'   => 'submit'
		]
	]);

	form_save_buttons($form_buttons);
}

function test_database_connection(array $poller = []) : bool {
	if (!cacti_sizeof($poller)) {
		$poller['dbtype'] = 'mysql';

		$fields = [
			'dbhost',
			'dbuser',
			'dbpass',
			'dbdefault',
			'dbport',
			'dbretries',
			'dbssl',
			'dbsslkey',
			'dbsslcert',
			'dbsslca',
			'dbsslcapath',
			'dbsslverifyservercert'
		];

		foreach ($fields as $field) {
			if ($field == 'dbssl') {
				if (isrv('dbssl') && gnrv('dbssl') == 'on') {
					$poller['dbssl'] = 'on';
				} else {
					$poller['dbssl'] = '';
				}
			} elseif ($field == 'dbsslverifyservercert') {
				if (isrv('dbsslverifyservercert') && gnrv('dbsslverifyservercert') == 'on') {
					$poller['dbsslverifyservercert'] = 'on';
				} else {
					$poller['dbsslverifyservercert'] = '';
				}
			} elseif (isrv($field)) {
				$poller[$field] = gnrv($field);
			} else {
				print 'ERROR: DB Connection Column ' . $field . ' Missing';

				return false;
			}
		}
	}

	$connection = db_connect_real(
		$poller['dbhost'],
		$poller['dbuser'],
		$poller['dbpass'],
		$poller['dbdefault'],
		$poller['dbtype'],
		$poller['dbport'],
		$poller['dbretries'],
		$poller['dbssl'],
		$poller['dbsslkey'],
		$poller['dbsslcert'],
		$poller['dbsslca'],
		$poller['dbsslcapath'],
		$poller['dbsslverifyservercert']
	);

	if (is_object($connection)) {
		db_close($connection);
		print '&nbsp;<i class="ti ti-check"></i>&nbsp;' . __('Connection Successful');
	} else {
		print '&nbsp;<i class="ti ti-x"></i>&nbsp;' . __('Connection Failed');
	}

	return true;
}

function pollers() : void {
	global $actions, $poller_status, $item_rows;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Data Collectors'), 'pollers.php', 'form_poller', 'sess_pollers');

	$pageFilter->rows_label  = __('Collectors');
	$pageFilter->has_refresh = true;
	$pageFilter->def_refresh = 20;
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE name LIKE ' . db_qstr('%' . grv('filter') . '%');
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM poller $sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$pollers = db_fetch_assoc("SELECT poller.*, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(poller.last_status) as heartbeat, devices AS hosts
		FROM poller
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('pollers.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 5, __('Pollers'), 'page', 'main');

	form_start('pollers.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'name' => [
			'display' => __('Collector Name'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Name of this Data Collector.')
		],
		'id' => [
			'display' => __('ID'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The unique id associated with this Data Collector.')
		],
		'poller.hostname'    => [
			'display' => __('Hostname'),
			'align'   => 'right',
			'sort'    => 'ASC',
			'tip'     => __('The Hostname where the Data Collector is running.')
		],
		'status'      => [
			'display' => __('Status'),
			'align'   => 'center',
			'sort'    => 'DESC',
			'tip'     => __('The Status of this Data Collector.')
		],
		'nosort0'   => [
			'display' => __('Proc/Threads'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The Number of Poller Processes and Threads for this Data Collector.')
		],
		'total_time'  => [
			'display' => __('Polling Time'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The last data collection time for this Data Collector.')
		],
		'nosort1'     => [
			'display' => __('Avg/Max'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The Average and Maximum Collector timings for this Data Collector.')
		],
		'hosts'       => [
			'display' => __('Devices'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of Devices associated with this Data Collector.')
		],
		'snmp'        => [
			'display' => __('SNMP Gets'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of SNMP gets associated with this Collector.')
		],
		'script'      => [
			'display' => __('Scripts'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of script calls associated with this Data Collector.')
		],
		'server'      => [
			'display' => __('Servers'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The number of script server calls associated with this Data Collector.')
		],
		'last_update' => [
			'display' => __('Last Finished'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The last time this Data Collector completed.')
		],
		'last_status' => [
			'display' => __('Last Update'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The last time this Data Collector checked in with the main Cacti site.')
		],
		'last_sync' => [
			'display' => __('Last Sync'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The last time this Data Collector was full synced with main Cacti site.')
		]
	];

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($pollers)) {
		foreach ($pollers as $poller) {
			if ($poller['id'] == 1) {
				$disabled = true;
			} else {
				$disabled = false;
			}

			if ($poller['disabled'] == 'on') {
				$poller['status'] = 4;
			} elseif ($poller['heartbeat'] > 310) {
				$poller['status'] = 6;
			}

			$mma = round($poller['avg_time'] ?: 0, 2) . '/' . round(max($poller['max_time'] ?: 1, 1), 2);

			if (empty($poller['name'])) {
				$poller['name'] = '&lt;no name&gt;';
			}

			$pt = read_config_option('poller_type');

			form_alternate_row('line' . $poller['id'], true, $disabled);

			form_selectable_cell(filter_value($poller['name'], grv('filter'), 'pollers.php?action=edit&id=' . $poller['id']), $poller['id']);
			form_selectable_cell($poller['id'], $poller['id'], '', 'right');
			form_selectable_ecell($poller['hostname'], $poller['id'], '', 'right');
			form_selectable_cell($poller_status[$poller['status']], $poller['id'], '', 'center');
			form_selectable_cell($poller['processes'] . '/' . ($pt == 2 ? $poller['threads'] : '-'), $poller['id'], '', 'right');
			form_selectable_cell(number_format_i18n($poller['total_time'], 2), $poller['id'], '', 'right');
			form_selectable_cell($mma, $poller['id'], '', 'right');
			form_selectable_cell(number_format_i18n($poller['hosts'], -1), $poller['id'], '', 'right');
			form_selectable_cell(number_format_i18n($poller['snmp'], -1), $poller['id'], '', 'right');
			form_selectable_cell(number_format_i18n($poller['script'], -1), $poller['id'], '', 'right');
			form_selectable_cell(number_format_i18n($poller['server'], -1), $poller['id'], '', 'right');
			form_selectable_cell(substr($poller['last_update'], 5), $poller['id'], '', 'right');
			form_selectable_cell(substr($poller['last_status'], 5), $poller['id'], '', 'right');

			if ($poller['id'] == 1) {
				form_selectable_cell(__('N/A'), $poller['id'], '', 'right');
			} else {
				form_selectable_cell(substr($poller['last_sync'], 5), $poller['id'], '', 'right');
			}

			form_checkbox_cell($poller['name'], $poller['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow odd'><td colspan='" . (cacti_sizeof($display_text) + 1) . "'><em>" . __('No Data Collectors Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($pollers)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
