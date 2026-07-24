#!/usr/bin/env php
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

require(__DIR__ . '/../include/cli_check.php');
require_once($config['base_path'] . '/lib/api_automation_tools.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/api_tree.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/template.php');
require_once($config['base_path'] . '/lib/utility.php');

/* administrative changes must always be made against the main database */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (! cacti_sizeof($parms)) {
	display_help();
	exit(0);
}

/* setup defaults */
$device_id   = '';
$device_ids  = array();
$file        = '';
$force       = false;

$quietMode = false;

$overrides = array();
foreach($parms as $parameter) {
	if (strpos($parameter, '=')) {
		list($arg, $value) = explode('=', $parameter, 2);
	} else {
		$arg = $parameter;
		$value = '';
	}

	switch ($arg) {
		case '-d':
			$debug = true;
			break;

		case '--quiet':
			$quietMode = true;
			break;

		case '--id':
			$device_id = trim($value);

			if (strpos($device_id, ',') !== false) {
				$device_ids = explode(',', $device_id);
			} else {
				$device_ids = array($device_id);
			}

			foreach ($device_ids as $id) {
				$id = trim($id);

				if (!is_valid_device_id($id)) {
					print "ERROR: Invalid device id: ($id)\n\n";
					display_help();
					exit(1);
				}
			}
			break;

		case '--file':
			$file = trim($value);
			break;

		case '--force':
			$force = true;
			break;

		case '--description':
			$overrides['description'] = trim($value);
			break;

		case '--ip':
			$overrides['ip'] = trim($value);
			break;

		case '--template':
			$converted = convert_override_value('template', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['host_template_id'] = $converted;
			break;

		case '--community':
			$overrides['snmp_community'] = trim($value);
			break;

		case '--version':
			if (cacti_sizeof($parms) == 1) {
				display_version();
				exit(0);
			} else {
				$converted = convert_override_value('version', trim($value));

				if ($converted === false) {
					display_help();
					exit(1);
				}

				$overrides['snmp_version'] = $converted;
			}
			break;

		case '--notes':
			$overrides['notes'] = trim($value);
			break;

		case '--location':
			$overrides['location'] = trim($value);
			break;

		case '--site':
			$converted = convert_override_value('site', trim($value));

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['site_id'] = $converted;
			break;

		case '--poller':
			$converted = convert_override_value('poller', trim($value));

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['poller_id'] = $converted;
			break;

		case '--disable':
			$converted = convert_override_value('disable', trim($value));

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['disabled'] = $converted;
			break;

		case '--external-id':
			$overrides['external_id']  = $value;
			break;

		case '--username':
			$overrides['snmp_username'] = trim($value);
			break;

		case '--password':
			$overrides['snmp_password'] = trim($value);
			break;

		case '--authproto':
			$overrides['snmp_auth_protocol'] = trim($value);
			break;

		case '--privproto':
			$overrides['snmp_priv_protocol'] = trim($value);
			break;

		case '--privpass':
			$overrides['snmp_priv_passphrase'] = trim($value);
			break;

		case '--context':
			$overrides['snmp_context'] = trim($value);
			break;

		case '--engineid':
			$overrides['snmp_engine_id'] = trim($value);
			break;

		case '--port':
			$converted = convert_override_value('port', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['snmp_port'] = $converted;
			break;

		case '--timeout':
			$converted = convert_override_value('timeout', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['snmp_timeout'] = $converted;
			break;

		case '--ping_timeout':
			$converted = convert_override_value('ping_timeout', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['ping_timeout'] = $converted;
			break;

		case '--threads':
			$converted = convert_override_value('threads', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['device_threads'] = $converted;
			break;

		case '--avail':
			$converted = convert_override_value('avail', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['availability_method'] = $converted;
			break;

		case '--ping_method':
			$converted = convert_override_value('ping_method', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['ping_method'] = $converted;
			break;

		case '--ping_port':
			$converted = convert_override_value('ping_port', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['ping_port'] = $converted;
			break;

		case '--ping_retries':
			$converted = convert_override_value('ping_retries', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['ping_retries'] = $converted;
			break;

		case '--max_oids':
			$converted = convert_override_value('max_oids', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['max_oids'] = $converted;
			break;

		case '--bulk_walk':
			$converted = convert_override_value('bulk_walk', $value);

			if ($converted === false) {
				display_help();
				exit(1);
			}

			$overrides['bulk_walk_size'] = $converted;
			break;

		case '-V':
		case '-v':
			display_version();
			exit(0);

		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit(0);

		default:
			print "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
	}
}

/* validate that either --id or --file was given */
if (empty($device_id) && $file == '') {
	print "ERROR: either --id or --file is a mandatory parameter.\n";
	display_help();
	exit(1);
}

if ($device_id != '' && $file != '') {
	print "ERROR: --id and --file are mutually exclusive.\n";
	display_help();
	exit(1);
}

/* load host templates once for validation */
$host_templates = getHostTemplates();

/* build the list of devices to process */
$device_list       = array();
$validation_failed = 0;

if ($file != '') {
	$device_list = parse_csv_file($file, $validation_failed);

	if ($device_list === false) {
		exit(1);
	}

	if (!cacti_sizeof($device_list)) {
		print "ERROR: no devices found in file ($file)\n";
		exit(1);
	}
} else {
	$seen_ids = array();

	foreach ($device_ids as $id) {
		$id = trim($id);

		if (isset($seen_ids[$id])) {
			print "ERROR: duplicate device id ($id).\n";
			exit(1);
		}

		$seen_ids[$id] = true;
		$device_list[] = array($id, array());
	}
}

/* preview changes and confirm before processing */
$valid_list        = array();
$no_change_devices = array();

foreach ($device_list as $entry) {
	$device_id     = $entry[0];
	$row_overrides = $entry[1];

	/* per-row overrides win over global CLI overrides */
	$merged = array_merge($overrides, $row_overrides);

	$original = array();
	$host     = load_and_validate_device($device_id, $merged, $host_templates, false, $original);

	if ($host === false) {
		$validation_failed++;
		continue;
	}

	if (!device_has_requested_changes($original, $merged)) {
		$no_change_devices[] = $device_id;
		continue;
	}

	$valid_list[] = array($device_id, $merged, $host, $original);
}

if (!$quietMode && cacti_sizeof($no_change_devices)) {
	foreach ($no_change_devices as $no_change_id) {
		print "Device ID $no_change_id already has the requested settings; no changes are needed.\n";
	}
}

if (!cacti_sizeof($valid_list)) {
	if ($validation_failed) {
		print "ERROR: no valid devices to process.\n";
		exit(1);
	}

	if (!$quietMode) {
		print "No devices require changes.\n";
	}

	exit(0);
}

/* show preview of what will change */
if (!$quietMode) {
	print "\nThe following " . cacti_count($valid_list) . " device(s) will be changed:\n\n";

	foreach ($valid_list as $entry) {
		$device_id = $entry[0];
		$host      = $entry[2];
		$original  = $entry[3];

		preview_device_changes($device_id, $original, $host);
	}

	/* only CSV bulk changes require confirmation */
	if ($file != '' && !$force) {
		print "\nDo you want to proceed? (y/N): ";

		$input  = fgets(STDIN);
		$answer = $input === false ? '' : trim($input);

		if (strtolower($answer) != 'y' && strtolower($answer) != 'yes') {
			print "Aborted.\n";
			exit(0);
		}
	}
}

/* process each device */
$success     = 0;
$save_failed = 0;

foreach ($valid_list as $entry) {
	$device_id = $entry[0];
	$merged    = $entry[1];
	$original  = $entry[3];
	$current   = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($device_id));

	if (!cacti_sizeof($current) || device_editable_state_changed($original, $current)) {
		print "ERROR: device-id $device_id changed after preview; it was not saved.\n";
		$save_failed++;
		continue;
	}

	$host = load_and_validate_device($device_id, $merged, $host_templates, $current);

	if ($host === false) {
		$save_failed++;
		continue;
	}

	if (save_device($device_id, $host, $quietMode, $host_templates)) {
		$success++;
	} else {
		$save_failed++;
	}
}

$failed    = $validation_failed + $save_failed;
$processed = $success + $failed;

if (!$quietMode) {
	print "\nProcessed $processed device(s): $success succeeded, $failed failed\n";

	if (cacti_sizeof($no_change_devices)) {
		print 'Skipped ' . cacti_count($no_change_devices) . " device(s) that already had the requested settings.\n";
	}
}

exit($failed > 0 ? 1 : 0);


/* load_and_validate_device - fetch a device, apply overrides, and validate */
function load_and_validate_device($device_id, $overrides, $host_templates, $source_host = false, &$original = null) {
	if ($source_host === false) {
		$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($device_id));
	} else {
		$host = $source_host;
	}

	if (!cacti_sizeof($host)) {
		print "ERROR: device-id $device_id not found.\n";
		return false;
	}

	$original = $host;

	/* merge overridden parameters onto host */
	$host = array_merge($host, $overrides);

	/* exception for IP */
	if (isset($overrides['ip'])) {
		$host['hostname'] = $overrides['ip'];
	}

	/* process templates */
	if (!is_unsigned_integer($host['host_template_id']) || !isset($host_templates[$host['host_template_id']])) {
		print "ERROR: Unknown template id (" . $host['host_template_id'] . ")\n";
		return false;
	}

	if ($host['description'] == '') {
		print "ERROR: You must supply a description for all hosts!\n";
		return false;
	}

	if ($host['hostname'] == '') {
		print "ERROR: You must supply an IP address for all hosts!\n";
		return false;
	}

	if (!is_unsigned_integer($host['snmp_version']) || $host['snmp_version'] > 3) {
		print "ERROR: The snmp version must be between 0 and 3.  If you did not specify one, goto Configuration > Settings > Device Defaults and resave your defaults.\n";
		return false;
	}

	/* api_device_save clears SNMPv3-only fields for lower SNMP versions */
	if (cacti_sizeof($overrides) && $host['snmp_version'] < 3) {
		$snmpv3_fields = array(
			'snmp_username',
			'snmp_password',
			'snmp_auth_protocol',
			'snmp_priv_passphrase',
			'snmp_priv_protocol',
			'snmp_context',
			'snmp_engine_id',
		);

		foreach ($snmpv3_fields as $field) {
			$host[$field] = '';
		}
	}

	if (!is_unsigned_integer($host['site_id']) || ($host['site_id'] > 0 && !reference_id_exists('sites', $host['site_id']))) {
		print "ERROR: You have specified an invalid site id!\n";
		return false;
	}

	if (!is_unsigned_integer($host['poller_id']) || ($host['poller_id'] > 0 && !reference_id_exists('poller', $host['poller_id']))) {
		print "ERROR: You have specified an invalid poller id!\n";
		return false;
	}

	/* process snmp information */
	if ($host['snmp_version'] > 0) {
		if (!is_unsigned_integer($host['snmp_port']) || $host['snmp_port'] < 1 || $host['snmp_port'] > 65534) {
			print "ERROR: Invalid port.  Valid values are from 1-65534\n";
			return false;
		}

		if (!is_unsigned_integer($host['snmp_timeout']) || $host['snmp_timeout'] < 1 || $host['snmp_timeout'] > 20000) {
			print "ERROR: Invalid timeout.  Valid values are from 1 to 20000\n";
			return false;
		}
	}

	/* community/user/password verification */
	if ($host['snmp_version'] < 3) {
		/* snmp community can be blank */
	} else {
		if ($host['snmp_username'] == "" || $host['snmp_password'] == "") {
			print "ERROR: When using snmpv3 you must supply an username and password\n";
			return false;
		}
	}

	if (!validate_device_field_values($host)) {
		return false;
	}

	return $host;
}

/* is_unsigned_integer - validates a non-negative base-10 integer */
function is_unsigned_integer($value) {
	return ctype_digit((string) $value);
}

/* is_valid_device_id - validates a positive device id */
function is_valid_device_id($value) {
	return is_unsigned_integer($value) && intval($value) > 0;
}

/* reference_id_exists - validates fixed device relation tables */
function reference_id_exists($table, $id) {
	static $cache = array();

	if ($table != 'sites' && $table != 'poller') {
		return false;
	}

	$key = $table . ':' . $id;

	if (isset($cache[$key])) {
		return $cache[$key];
	}

	$found = db_fetch_cell_prepared("SELECT id FROM $table WHERE id = ?", array($id));

	$cache[$key] = (string) $found === (string) $id;

	return $cache[$key];
}

/* validate_device_field_values - validates values not fully constrained by api_device_save */
function validate_device_field_values($host) {
	$lengths = array(
		'description'          => 150,
		'hostname'             => 100,
		'location'             => 40,
		'external_id'          => 40,
		'snmp_community'       => 100,
		'snmp_username'        => 50,
		'snmp_password'        => 50,
		'snmp_auth_protocol'   => 6,
		'snmp_priv_passphrase' => 200,
		'snmp_priv_protocol'   => 7,
		'snmp_context'         => 64,
		'snmp_engine_id'       => 64,
	);

	foreach ($lengths as $field => $max_length) {
		if (isset($host[$field]) && strlen($host[$field]) > $max_length) {
			print "ERROR: $field exceeds its maximum length of $max_length characters.\n";
			return false;
		}
	}

	if ($host['snmp_version'] == 3) {
		$auth_protocols = array('[None]', 'MD5', 'SHA', 'SHA224', 'SHA256', 'SHA384', 'SHA512');
		$priv_protocols = array('[None]', 'DES', 'AES', 'AES128', 'AES192', 'AES192C', 'AES256', 'AES256C');

		if (!in_array($host['snmp_auth_protocol'], $auth_protocols, true)) {
			print "ERROR: Invalid SNMP authentication protocol ({$host['snmp_auth_protocol']}).\n";
			return false;
		}

		if (!in_array($host['snmp_priv_protocol'], $priv_protocols, true)) {
			print "ERROR: Invalid SNMP privacy protocol ({$host['snmp_priv_protocol']}).\n";
			return false;
		}
	}

	if (!is_unsigned_integer($host['device_threads']) || $host['device_threads'] < 1 || $host['device_threads'] > 10) {
		print "ERROR: Device threads must be between 1 and 10.\n";
		return false;
	}

	return true;
}

/* device_editable_fields - fields persisted by save_device */
function device_editable_fields() {
	return array(
		'description'          => 'Description',
		'hostname'             => 'IP/Hostname',
		'host_template_id'     => 'Template',
		'snmp_version'         => 'SNMP Version',
		'snmp_community'       => 'SNMP Community',
		'snmp_port'            => 'SNMP Port',
		'snmp_timeout'         => 'SNMP Timeout',
		'snmp_username'        => 'SNMP Username',
		'snmp_password'        => 'SNMP Password',
		'snmp_auth_protocol'   => 'SNMP Auth Protocol',
		'snmp_priv_protocol'   => 'SNMP Priv Protocol',
		'snmp_priv_passphrase' => 'SNMP Priv Passphrase',
		'snmp_context'         => 'SNMP Context',
		'snmp_engine_id'       => 'SNMP Engine ID',
		'availability_method'  => 'Availability Method',
		'ping_method'          => 'Ping Method',
		'ping_port'            => 'Ping Port',
		'ping_timeout'         => 'Ping Timeout',
		'ping_retries'         => 'Ping Retries',
		'max_oids'             => 'Max OIDs',
		'device_threads'       => 'Threads',
		'poller_id'            => 'Poller ID',
		'site_id'              => 'Site ID',
		'external_id'          => 'External ID',
		'location'             => 'Location',
		'notes'                => 'Notes',
		'disabled'             => 'Disabled',
		'bulk_walk_size'       => 'Bulk Walk Size',
	);
}

/* device_has_changes - checks whether the proposed editable fields differ */
function device_has_changes($original, $proposed) {
	foreach (device_editable_fields() as $field => $label) {
		$old_value = isset($original[$field]) ? $original[$field] : '';
		$new_value = isset($proposed[$field]) ? $proposed[$field] : '';

		if ((string) $old_value !== (string) $new_value) {
			return true;
		}
	}

	return false;
}

/* device_has_requested_changes - compares only explicitly requested overrides */
function device_has_requested_changes($original, $overrides) {
	$editable_fields = device_editable_fields();

	foreach ($overrides as $field => $new_value) {
		if ($field == 'ip') {
			$field = 'hostname';
		}

		if (!isset($editable_fields[$field])) {
			continue;
		}

		$old_value = isset($original[$field]) ? $original[$field] : '';

		if ((string) $old_value !== (string) $new_value) {
			return true;
		}
	}

	return false;
}

/* device_editable_state_changed - detects concurrent edits after preview */
function device_editable_state_changed($original, $current) {
	return device_has_changes($original, $current);
}

/* preview_device_changes - show what fields will change for a device */
function preview_device_changes($device_id, $original, $host) {
	global $host_templates;

	/* fields to compare, in display order */
	$fields    = device_editable_fields();
	$sensitive = array('snmp_community', 'snmp_password', 'snmp_priv_passphrase');

	$changed = false;

	print "Device ID $device_id (" . $original['description'] . "):\n";

	foreach ($fields as $field => $label) {
		$old_val = isset($original[$field]) ? $original[$field] : '';
		$new_val = isset($host[$field]) ? $host[$field] : '';

		/* special case: show template name instead of id */
		if ($field == 'host_template_id') {
			$old_val = isset($host_templates[$old_val]) ? $host_templates[$old_val] : $old_val;
			$new_val = isset($host_templates[$new_val]) ? $host_templates[$new_val] : $new_val;
		}

		/* special case: show disabled as on/off */
		if ($field == 'disabled') {
			$old_val = ($old_val == 'on') ? 'on' : 'off';
			$new_val = ($new_val == 'on') ? 'on' : 'off';
		}

		if ($old_val != $new_val) {
			if (in_array($field, $sensitive, true)) {
				print "  $label: [redacted] -> [redacted]\n";
			} else {
				print "  $label: '$old_val' -> '$new_val'\n";
			}

			$changed = true;
		}
	}

	if (!$changed) {
		print "  (no changes)\n";
	}

	print "\n";
}

/* save_device - save a validated device and report the result */
function save_device($device_id, $host, $quietMode, $host_templates) {
	if (!$quietMode) {
		print "Changing device-id: $device_id to {$host['description']} ({$host['hostname']}) as \"{$host_templates[$host['host_template_id']]}\" using SNMP v{$host['snmp_version']}\n";
	}

	clear_cli_messages();

	$host_id = api_device_save($device_id, $host['host_template_id'], $host['description'], $host['hostname'],
		$host['snmp_community'], $host['snmp_version'], $host['snmp_username'], $host['snmp_password'],
		$host['snmp_port'], $host['snmp_timeout'], $host['disabled'], $host['availability_method'], $host['ping_method'],
		$host['ping_port'], $host['ping_timeout'], $host['ping_retries'], $host['notes'],
		$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'],
		$host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_engine_id'], $host['max_oids'], $host['device_threads'],
		$host['poller_id'], $host['site_id'], $host['external_id'], $host['location'], $host['bulk_walk_size']);

	$has_error    = is_error_message();
	$error_fields = isset($_SESSION['sess_error_fields']) ? array_keys($_SESSION['sess_error_fields']) : array();

	clear_cli_messages();

	if ($has_error || $host_id != $device_id) {
		$details = cacti_sizeof($error_fields) ? ' Invalid field(s): ' . implode(', ', $error_fields) . '.' : '';
		print "ERROR: Failed to change this device ($device_id-$host_id).$details\n";
		return false;
	} else {
		if (!$quietMode) {
			print "Success\n";
		}
		return true;
	}
}

/* clear_cli_messages - isolate API validation state without starting a web session */
function clear_cli_messages() {
	kill_session_var('sess_error_fields');
	kill_session_var('sess_messages');
}

/* device_override_definitions - defines CSV aliases and shared numeric validation */
function device_override_definitions() {
	return array(
		'id'           => array('key' => 'id'),
		'description'  => array('key' => 'description'),
		'ip'           => array('key' => 'ip'),
		'template'     => array('key' => 'host_template_id', 'min' => 0),
		'community'    => array('key' => 'snmp_community'),
		'version'      => array('key' => 'snmp_version', 'min' => 0, 'max' => 3),
		'notes'        => array('key' => 'notes'),
		'location'     => array('key' => 'location'),
		'site'         => array('key' => 'site_id', 'min' => 0),
		'poller'       => array('key' => 'poller_id', 'min' => 0),
		'disable'      => array('key' => 'disabled'),
		'external-id'  => array('key' => 'external_id'),
		'username'     => array('key' => 'snmp_username'),
		'password'     => array('key' => 'snmp_password'),
		'authproto'    => array('key' => 'snmp_auth_protocol'),
		'privproto'    => array('key' => 'snmp_priv_protocol'),
		'privpass'     => array('key' => 'snmp_priv_passphrase'),
		'context'      => array('key' => 'snmp_context'),
		'engineid'     => array('key' => 'snmp_engine_id'),
		'port'         => array('key' => 'snmp_port', 'min' => 1, 'max' => 65534),
		'timeout'      => array('key' => 'snmp_timeout', 'min' => 1, 'max' => 20000),
		'ping_timeout' => array('key' => 'ping_timeout', 'min' => 1),
		'threads'      => array('key' => 'device_threads', 'min' => 1, 'max' => 10),
		'avail'        => array('key' => 'availability_method'),
		'ping_method'  => array('key' => 'ping_method'),
		'ping_port'    => array('key' => 'ping_port', 'min' => 1, 'max' => 65534),
		'ping_retries' => array('key' => 'ping_retries', 'min' => 0),
		'max_oids'     => array('key' => 'max_oids', 'min' => 1, 'max' => 60),
		'bulk_walk'    => array('key' => 'bulk_walk_size', 'min' => 1, 'max' => 60, 'allow' => array(-1)),
	);
}

/* csv_column_map - maps CSV header names to override keys */
function csv_column_map() {
	$map = array();

	foreach (device_override_definitions() as $column => $definition) {
		$map[$column] = $definition['key'];
	}

	return $map;
}

/* convert_override_value - converts CSV string values to the expected override format */
function convert_override_value($column, $value) {
	$definitions = device_override_definitions();

	if (isset($definitions[$column]['min'])) {
		$definition = $definitions[$column];
		$allowed    = isset($definition['allow']) ? $definition['allow'] : array();
		$valid      = ctype_digit($value);

		if ($valid) {
			$number = intval($value);
			$valid  = $number >= $definition['min'];

			if (isset($definition['max'])) {
				$valid = $valid && $number <= $definition['max'];
			}
		} elseif (in_array(intval($value), $allowed, true) && (string) intval($value) === $value) {
			$valid = true;
		}

		if (!$valid) {
			$range = isset($definition['max']) ? $definition['min'] . '-' . $definition['max'] : $definition['min'] . ' or greater';

			if (cacti_sizeof($allowed)) {
				$range .= ', or ' . implode(', ', $allowed);
			}

			print "ERROR: Invalid $column value ($value). Valid values are $range.\n";
			return false;
		}

		return $value;
	}

	switch ($column) {
		case 'avail':
			switch ($value) {
				case 'none':
					return '0';
				case 'ping':
					return AVAIL_PING;
				case 'snmp':
					return AVAIL_SNMP;
				case 'pingsnmp':
					return AVAIL_SNMP_AND_PING;
				case 'pingorsnmp':
					return AVAIL_SNMP_OR_PING;
				default:
					print "ERROR: Invalid Availability Parameter: ($value)\n";
					return false;
			}
			break;

		case 'ping_method':
			switch (strtolower($value)) {
				case 'icmp':
					return PING_ICMP;
				case 'tcp':
					return PING_TCP;
				case 'udp':
					return PING_UDP;
				default:
					print "ERROR: Invalid Ping Method: ($value)\n";
					return false;
			}
			break;

		case 'disable':
			switch (strtolower($value)) {
				case '1':
				case 'on':
					return 'on';
				case '0':
				case 'off':
					return '';
				default:
					print "ERROR: Invalid disable value ($value). Valid values are 0, 1, off, or on.\n";
					return false;
			}

		default:
			return $value;
	}
}

/* parse_csv_file - reads a CSV file and returns an array of [device_id, overrides] pairs */
function parse_csv_file($file, &$failed) {
	if (!is_readable($file)) {
		print "ERROR: file '$file' is not readable or does not exist.\n";
		return false;
	}

	$fh = fopen($file, 'r');
	if ($fh === false) {
		print "ERROR: unable to open file '$file'.\n";
		return false;
	}

	$map = csv_column_map();

	/* read header row */
	$header = read_csv_row($fh);
	if ($header === false || !cacti_sizeof($header)) {
		print "ERROR: file '$file' is empty or has no header row.\n";
		fclose($fh);
		return false;
	}

	/* trim header names */
	$header = array_map('trim', $header);

	/* strip an optional UTF-8 byte order mark */
	if (isset($header[0]) && substr($header[0], 0, 3) === "\xEF\xBB\xBF") {
		$header[0] = substr($header[0], 3);
	}

	/* validate that the id column is first */
	if ($header[0] !== 'id') {
		print "ERROR: the first CSV column must be 'id'.\n";
		fclose($fh);
		return false;
	}

	if (cacti_count(array_unique($header)) != cacti_count($header)) {
		print "ERROR: CSV column names must be unique.\n";
		fclose($fh);
		return false;
	}

	foreach ($header as $column) {
		if (!isset($map[$column])) {
			print "ERROR: Unknown CSV column '$column'.\n";
			fclose($fh);
			return false;
		}
	}

	$device_list = array();
	$seen_ids    = array();
	$line        = 1;

	while (($row = read_csv_row($fh)) !== false) {
		$line++;

		/* skip empty rows */
		if (cacti_count($row) == 1 && ($row[0] === null || trim($row[0]) === '')) {
			continue;
		}

		if (cacti_count($row) != cacti_count($header)) {
			print "ERROR: CSV line $line has " . cacti_count($row) . ' field(s); expected ' . cacti_count($header) . ".\n";
			fclose($fh);
			return false;
		}

		$overrides = array();
		$device_id = '';
		$skip_row  = false;

		foreach ($header as $index => $column) {
			$value = trim($row[$index]);

			if ($column == 'id') {
				if (!is_valid_device_id($value)) {
					print "ERROR: Invalid device id on line $line: ($value)\n";
					fclose($fh);
					return false;
				}

				if (isset($seen_ids[$value])) {
					print "ERROR: Duplicate device id on line $line: ($value)\n";
					fclose($fh);
					return false;
				}

				$seen_ids[$value] = true;
				$device_id = $value;
				continue;
			}

			/* skip empty cells - preserve existing value */
			if ($value === '') {
				continue;
			}

			$override_key = $map[$column];
			$converted    = convert_override_value($column, $value);

			if ($converted === false) {
				print "WARNING: skipping line $line due to invalid value for column '$column'.\n";
				$failed++;
				$skip_row = true;
				break;
			}

			$overrides[$override_key] = $converted;
		}

		if ($skip_row) {
			continue;
		}

		if ($device_id == '') {
			print "WARNING: skipping line $line - missing device id.\n";
			$failed++;
			continue;
		}

		$device_list[] = array($device_id, $overrides);
	}

	fclose($fh);

	return $device_list;
}

/* read_csv_row - reads standards-compliant CSV across supported PHP versions */
function read_csv_row($fh) {
	if (PHP_VERSION_ID >= 70400) {
		return fgetcsv($fh, 0, ',', '"', '');
	}

	return fgetcsv($fh, 0, ',', '"', '\\');
}


/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Change Device Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: change_device.php --id=<device-id> [--description=[description]] [--ip=[IP]] [--template=[ID]] [--notes=\"[]\"] [--disable=[0|1|off|on]]\n";
	print "    [--poller=[id]] [--site=[id]] [--external-id=[S]] [--threads=[1]]\n";
	print "    [--avail=[ping]] [--ping_method=[icmp]] [--ping_port=[N/A, 1-65534]] [--ping_timeout=[N]] [--ping_retries=[2]]\n";
	print "    [--version=[0|1|2|3]] [--community=] [--port=161] [--timeout=500]\n";
	print "    [--username= --password=] [--authproto=] [--privpass= --privproto=] [--context=] [--engineid=]\n";
	print "    [--file=<path>] [--force] [--quiet]\n\n";
	print "Required (one of):\n";
	print "    --id           the id for a device, that is field id in table host\n";
	print "                   accepts a comma-separated list (e.g. --id=1,2,3) to change multiple devices\n";
	print "                   any optional device attribute parameter given, will replace the existing parameter\n";
	print "    --file         path to a CSV file with a header row for per-device overrides\n";
	print "                   the first column must be 'id'; remaining columns are override names\n";
	print "                   --id and --file are mutually exclusive\n";
	print "Optional:\n";
	print "    --description  the name that will be displayed by Cacti in the graphs\n";
	print "    --ip           self explanatory (can also be a FQDN)\n\n";
	print "    --template     0, is a number (read below to get a list of templates)\n";
	print "    --location     '', The physical location of the Device.\n";
	print "    --notes        '', General information about this host.  Must be enclosed using double quotes.\n";
	print "    --external-id  '', An external ID to align Cacti devices with devices from other systems.\n";
	print "    --disable      1 or on to disable checks; 0 or off to enable checks\n";
	print "    --poller       0, numeric poller id that will perform data collection for the device.\n";
	print "    --site         0, numeric site id that will be associated with the device.\n";
	print "    --threads      1, numeric number of threads to poll device with.\n";
	print "    --avail        pingsnmp, [ping][none, snmp, pingsnmp, pingorsnmp]\n";
	print "    --ping_method  tcp, icmp|tcp|udp\n";
	print "    --ping_port    '', 1-65534\n";
	print "    --ping_retries 2, the number of time to attempt to communicate with a host\n";
	print "    --ping_timeout N, the ping timeout in milliseconds.  Defaults to database setting.\n";
	print "    --version      1, 0|1|2|3, snmp version.  0 for no snmp\n";
	print "    --community    '', snmp community string for snmpv1 and snmpv2.  Leave blank for no community\n";
	print "    --port         161\n";
	print "    --timeout      500\n";
	print "    --username     '', snmp username for snmpv3\n";
	print "    --password     '', snmp password for snmpv3\n";
	print "    --authproto    '', snmp authentication protocol for snmpv3\n";
	print "    --privpass     '', snmp privacy passphrase for snmpv3\n";
	print "    --privproto    '', snmp privacy protocol for snmpv3\n";
	print "    --context      '', snmp context for snmpv3\n";
	print "    --engineid     '', snmp engineid for snmpv3\n";
	print "    --max_oids     10, 1-60, the number of OIDs that can be obtained in a single SNMP Get request\n\n";
	print "    --bulk_walk    -1, 1-60, the bulk walk chunk size that will be used for bulk walks.  Use -1 for auto-tune.\n\n";
	print "    --force        skip CSV confirmation (for automated use)\n";
	print "    --quiet        suppress normal output and skip CSV confirmation\n\n";
	print "CSV File Format (--file):\n";
	print "    The file must be a CSV with a header row.  The first column must be 'id'.\n";
	print "    Column names must be unique and every row must contain the same number of fields.\n";
	print "    Supported columns: " . implode(', ', array_keys(device_override_definitions())) . ".\n";
	print "    Only include columns you want to override; missing columns preserve existing values.\n";
	print "    Empty cells are treated as 'no override' and cannot be used to clear a field.\n";
	print "    Values containing commas must be enclosed in double quotes per CSV standard.\n\n";
	print "    Rows are saved individually.  Successful changes remain applied if a later row fails.\n";
	print "    Duplicate device ids are rejected and rows with no changes are skipped.\n\n";
	print "    Example CSV:\n";
	print "        id,description,ip,community\n";
	print "        1,Core Router,10.0.0.1,private\n";
	print "        2,Edge Switch,10.0.0.2,public\n\n";
}
