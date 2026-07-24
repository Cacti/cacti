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

$displayHostTemplates = false;
$displayCommunities   = false;
$quietMode            = false;

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
				if (!is_numeric($id)) {
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
			$overrides['host_template_id'] = $value;
			break;

		case '--community':
			$overrides['snmp_community'] = trim($value);
			break;

		case '--version':
			if (cacti_sizeof($parms) == 1) {
				display_version();
				exit(0);
			} else {
				$overrides['snmp_version'] = trim($value);
			}
			break;

		case '--notes':
			$overrides['notes'] = trim($value);
			break;

		case '--location':
			$overrides['location'] = trim($value);
			break;

		case '--site':
			$overrides['site_id'] = trim($value);
			break;

		case '--poller':
			$overrides['poller_id'] = trim($value);
			break;

		case '--disable':
			$value = trim($value);
			if (is_numeric($value)) {
				$overrides['disabled'] = intval($value) == 0 ? 'on' : '';
			} else {
				$overrides['disabled'] = $value == 'on' ? 'on': '';
			}
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

		case '--proxy':
			$proxy = true;
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
			switch($value) {
				case 'none':
					$overrides['availability_method'] = '0'; /* tried to use AVAIL_NONE, but then preg_match failes on validation, sigh */
					break;
				case 'ping':
					$overrides['availability_method'] = AVAIL_PING;
					break;

				case 'snmp':
					$overrides['availability_method'] = AVAIL_SNMP;
					break;

				case 'pingsnmp':
					$overrides['availability_method'] = AVAIL_SNMP_AND_PING;
					break;

				case 'pingorsnmp':
					$overrides['availability_method'] = AVAIL_SNMP_OR_PING;
					break;

				default:
					print "ERROR: Invalid Availability Parameter: ($value)\n\n";
					display_help();
					exit(1);
			}
			break;

		case '--ping_method':
			switch(strtolower($value)) {
				case 'icmp':
					$overrides['ping_method'] = PING_ICMP;
					break;

				case 'tcp':
					$overrides['ping_method'] = PING_TCP;
					break;

				case 'udp':
					$overrides['ping_method'] = PING_UDP;
					break;

				default:
					print "ERROR: Invalid Ping Method: ($value)\n\n";
					display_help();
					exit(1);
			}
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

		case '--version':
		case '-V':
		case '-v':
			display_version();
			exit(0);

		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit(0);

		case '--quiet':
			$quietMode = true;
			break;

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
	foreach ($device_ids as $id) {
		$device_list[] = array(trim($id), array());
	}
}

/* preview changes and confirm before processing */
$valid_list = array();

foreach ($device_list as $entry) {
	$device_id     = $entry[0];
	$row_overrides = $entry[1];

	/* per-row overrides win over global CLI overrides */
	$merged = array_merge($overrides, $row_overrides);

	$host = load_and_validate_device($device_id, $merged, $host_templates);

	if ($host === false) {
		$validation_failed++;
		continue;
	}

	$valid_list[] = array($device_id, $merged, $host);
}

if (!cacti_sizeof($valid_list)) {
	print "ERROR: no valid devices to process.\n";
	exit(1);
}

/* show preview of what will change */
if (!$quietMode) {
	print "\nThe following " . cacti_count($valid_list) . " device(s) will be changed:\n\n";

	foreach ($valid_list as $entry) {
		$device_id = $entry[0];
		$host      = $entry[2];

		preview_device_changes($device_id, $host);
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
	$host      = $entry[2];

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
}

exit($failed > 0 ? 1 : 0);


/* load_and_validate_device - fetch a device, apply overrides, and validate */
function load_and_validate_device($device_id, $overrides, $host_templates) {
	$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($device_id));

	if (!cacti_sizeof($host)) {
		print "ERROR: device-id $device_id not found.\n";
		return false;
	}

	/* merge overridden parameters onto host */
	$host = array_merge($host, $overrides);

	/* exception for IP */
	if (isset($overrides['ip'])) {
		$host['hostname'] = $overrides['ip'];
	}

	/* process templates */
	if (!isset($host_templates[$host['host_template_id']])) {
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

	if ($host['snmp_version'] > 3 || $host['snmp_version'] < 0 || !is_numeric($host['snmp_version'])) {
		print "ERROR: The snmp version must be between 0 and 3.  If you did not specify one, goto Configuration > Settings > Device Defaults and resave your defaults.\n";
		return false;
	}

	if (!is_numeric($host['site_id']) || $host['site_id'] < 0) {
		print "ERROR: You have specified an invalid site id!\n";
		return false;
	}

	if (!is_numeric($host['poller_id']) || $host['poller_id'] < 0) {
		print "ERROR: You have specified an invalid poller id!\n";
		return false;
	}

	/* process snmp information */
	if ($host['snmp_version'] < 0 || $host['snmp_version'] > 3) {
		print "ERROR: Invalid snmp version ({$host['snmp_version']})\n";
		return false;
	} elseif ($host['snmp_version'] > 0) {
		if ($host['snmp_port'] < 1 || $host['snmp_port'] > 65534) {
			print "ERROR: Invalid port.  Valid values are from 1-65534\n";
			return false;
		}

		if ($host['snmp_timeout'] <= 0 || $host['snmp_timeout'] > 20000) {
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

	return $host;
}

/* preview_device_changes - show what fields will change for a device */
function preview_device_changes($device_id, $host) {
	global $host_templates;

	$original = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($device_id));

	if (!cacti_sizeof($original)) {
		return;
	}

	/* fields to compare, in display order */
	$fields = array(
		'description'        => 'Description',
		'hostname'           => 'IP/Hostname',
		'host_template_id'  => 'Template',
		'snmp_version'      => 'SNMP Version',
		'snmp_community'    => 'SNMP Community',
		'snmp_port'         => 'SNMP Port',
		'snmp_timeout'      => 'SNMP Timeout',
		'snmp_username'     => 'SNMP Username',
		'snmp_auth_protocol' => 'SNMP Auth Protocol',
		'snmp_priv_protocol' => 'SNMP Priv Protocol',
		'snmp_priv_passphrase' => 'SNMP Priv Passphrase',
		'snmp_context'      => 'SNMP Context',
		'snmp_engine_id'    => 'SNMP Engine ID',
		'availability_method' => 'Availability Method',
		'ping_method'       => 'Ping Method',
		'ping_port'         => 'Ping Port',
		'ping_timeout'      => 'Ping Timeout',
		'ping_retries'     => 'Ping Retries',
		'max_oids'          => 'Max OIDs',
		'device_threads'    => 'Threads',
		'poller_id'         => 'Poller ID',
		'site_id'           => 'Site ID',
		'external_id'       => 'External ID',
		'location'          => 'Location',
		'notes'             => 'Notes',
		'disabled'          => 'Disabled',
		'bulk_walk_size'    => 'Bulk Walk Size',
	);

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
			print "  $label: '$old_val' -> '$new_val'\n";
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
		print "Changing device-id: $device_id to {$host['description']} ({$host['hostname']}) as \"{$host_templates[$host['host_template_id']]}\" using SNMP v{$host['snmp_version']} with community \"{$host['snmp_community']}\"\n";
	}

	$host_id = api_device_save($device_id, $host['host_template_id'], $host['description'], $host['hostname'],
		$host['snmp_community'], $host['snmp_version'], $host['snmp_username'], $host['snmp_password'],
		$host['snmp_port'], $host['snmp_timeout'], $host['disabled'], $host['availability_method'], $host['ping_method'],
		$host['ping_port'], $host['ping_timeout'], $host['ping_retries'], $host['notes'],
		$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'],
		$host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_engine_id'], $host['max_oids'], $host['device_threads'],
		$host['poller_id'], $host['site_id'], $host['external_id'], $host['location'], $host['bulk_walk_size']);

	if (is_error_message() || $host_id != $device_id) {
		print "ERROR: Failed to change this device ($device_id-$host_id)\n";
		return false;
	} else {
		if (!$quietMode) {
			print "Success\n";
		}
		return true;
	}
}

/* device_override_definitions - defines CSV aliases and shared numeric validation */
function device_override_definitions() {
	return array(
		'id'           => array('key' => 'id'),
		'description'  => array('key' => 'description'),
		'ip'           => array('key' => 'ip'),
		'template'     => array('key' => 'host_template_id'),
		'community'    => array('key' => 'snmp_community'),
		'version'      => array('key' => 'snmp_version'),
		'notes'        => array('key' => 'notes'),
		'location'     => array('key' => 'location'),
		'site'         => array('key' => 'site_id'),
		'poller'       => array('key' => 'poller_id'),
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
		'threads'      => array('key' => 'device_threads', 'min' => 1),
		'avail'        => array('key' => 'availability_method'),
		'ping_method'  => array('key' => 'ping_method'),
		'ping_port'    => array('key' => 'ping_port', 'min' => 1, 'max' => 65534),
		'ping_retries' => array('key' => 'ping_retries', 'min' => 1),
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
			if (is_numeric($value)) {
				return intval($value) == 0 ? 'on' : '';
			} else {
				return $value == 'on' ? 'on' : '';
			}
			break;

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
	$header = fgetcsv($fh);
	if ($header === false || !cacti_sizeof($header)) {
		print "ERROR: file '$file' is empty or has no header row.\n";
		fclose($fh);
		return false;
	}

	/* trim header names */
	$header = array_map('trim', $header);

	/* validate that the id column is first */
	if ($header[0] !== 'id') {
		print "ERROR: the first CSV column must be 'id'.\n";
		fclose($fh);
		return false;
	}

	$device_list = array();
	$line        = 1;

	while (($row = fgetcsv($fh)) !== false) {
		$line++;

		/* skip empty rows */
		if (cacti_count($row) == 1 && $row[0] === '') {
			continue;
		}

		$overrides = array();
		$device_id = '';
		$skip_row  = false;

		foreach ($header as $index => $column) {
			if (!isset($row[$index])) {
				continue;
			}

			$value = trim($row[$index]);

			if ($column == 'id') {
				if (!is_numeric($value)) {
					print "ERROR: Invalid device id on line $line: ($value)\n";
					fclose($fh);
					return false;
				}
				$device_id = $value;
				continue;
			}

			/* skip empty cells - preserve existing value */
			if ($value === '') {
				continue;
			}

			if (!isset($map[$column])) {
				print "ERROR: Unknown CSV column '$column' on line $line.\n";
				fclose($fh);
				return false;
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


/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Change Device Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: change_device.php --id=<device-id> [--description=[description]] [--ip=[IP]] [--template=[ID]] [--notes=\"[]\"] [--disable]\n";
	print "    [--poller=[id]] [--site=[id] [--external-id=[S]] [--proxy] [--threads=[1]\n";
	print "    [--avail=[ping]] --ping_method=[icmp] --ping_port=[N/A, 1-65534] --ping_timeout=[N] --ping_retries=[2]\n";
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
	print "    --proxy        if specified, allows adding a second host with same ip address\n";
	print "    --template     0, is a number (read below to get a list of templates)\n";
	print "    --location     '', The physical location of the Device.\n";
	print "    --notes        '', General information about this host.  Must be enclosed using double quotes.\n";
	print "    --external-id  '', An external ID to align Cacti devices with devices from other systems.\n";
	print "    --disable      0, 1 to add this host but to disable checks and 0 to enable it\n";
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
	print "    --force        skip the confirmation prompt (for automated use)\n";
	print "    --quiet - batch mode value return\n\n";
	print "CSV File Format (--file):\n";
	print "    The file must be a CSV with a header row.  The first column must be 'id'.\n";
	print "    Remaining columns use the same names as the CLI flags above (without the -- prefix).\n";
	print "    Only include columns you want to override; missing columns preserve existing values.\n";
	print "    Empty cells are treated as 'no override' for that row.\n";
	print "    Values containing commas must be enclosed in double quotes per CSV standard.\n\n";
	print "    Example CSV:\n";
	print "        id,description,ip,community\n";
	print "        1,Core Router,10.0.0.1,private\n";
	print "        2,Edge Switch,10.0.0.2,public\n\n";
}
