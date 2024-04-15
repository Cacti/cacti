#!/usr/bin/env php
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
			$overrides['snmp_port'] = $value;
			break;

		case '--proxy':
			$proxy = true;
			break;

		case '--timeout':
			$overrides['snmp_timeout'] = $value;
			break;

		case '--ping_timeout':
			$overrides['ping_timeout'] = $value;
			break;

		case '--threads':
			$overrides['device_threads'] = $value;
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
			if (is_numeric($value) && ($value > 0)) {
				$overrides['ping_port'] = $value;
			} else {
				print "ERROR: Invalid Ping Port: ($value)\n\n";
				display_help();
				exit(1);
			}
			break;

		case '--ping_retries':
			if (is_numeric($value) && ($value > 0)) {
				$overrides['ping_retries'] = $value;
			} else {
				print "ERROR: Invalid Ping Retries: ($value)\n\n";
				display_help();
				exit(1);
			}
			break;

		case '--max_oids':
			if (is_numeric($value) && ($value > 0)) {
				$overrides['max_oids'] = $value;
			} else {
				print "ERROR: Invalid Max OIDS: ($value)\n\n";
				display_help();
				exit(1);
			}
			break;

		case '--bulk_walk':
			if (is_numeric($value) && $value >= -1 && $value != 0) {
				$overrides['bulk_walk_size'] = $value;
			} else {
				print "ERROR: Invalid Bulk Walk Size: ($value)\n\n";
				display_help();
				exit(1);
			}

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

if (empty($device_id)) {
	print "ERROR: --id is mandatory parameter.\n";
	display_help();
	exit(1);
}

$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($device_id));
if (!cacti_sizeof($host)) {
	print "ERROR: device-id $device_id not found.\n";
	exit(1);
}

/* merge overriden parameters onto host */
$host    = array_merge($host, $overrides);

/* exception for IP */
if (isset($overrides['ip'])) {
	$host['hostname'] = $overrides['ip'];
}

/* process the various lists into validation arrays */
$host_templates = getHostTemplates();
$hosts          = getHostsByDescription();
$addresses      = getAddresses();

/* process templates */
if (!isset($host_templates[$host['host_template_id']])) {
	print "ERROR: Unknown template id (" . $host['host_template_id'] . ")\n";
	exit(1);
}

if ($host['description'] == '') {
	print "ERROR: You must supply a description for all hosts!\n";
	exit(1);
}

if ($host['hostname'] == '') {
	print "ERROR: You must supply an IP address for all hosts!\n";
	exit(1);
}

if ($host['snmp_version'] > 3 || $host['snmp_version'] < 0 || !is_numeric($host['snmp_version'])) {
	print "ERROR: The snmp version must be between 0 and 3.  If you did not specify one, goto Configuration > Settings > Device Defaults and resave your defaults.\n";
	exit(1);
}

if (!is_numeric($host['site_id']) || $host['site_id'] < 0) {
	print "ERROR: You have specified an invalid site id!\n";
	exit(1);
}

if (!is_numeric($host['poller_id']) || $host['poller_id'] < 0) {
	print "ERROR: You have specified an invalid poller id!\n";
	exit(1);
}

/* process snmp information */
if ($host['snmp_version'] < 0 || $host['snmp_version'] > 3) {
	print "ERROR: Invalid snmp version ({$host['snmp_version']})\n";
	exit(1);
} elseif ($host['snmp_version'] > 0) {
	if ($host['snmp_port'] <= 1 || $host['snmp_port'] > 65534) {
		print "ERROR: Invalid port.  Valid values are from 1-65534\n";
		exit(1);
	}

	if ($host['snmp_timeout'] <= 0 || $host['snmp_timeout'] > 20000) {
		print "ERROR: Invalid timeout.  Valid values are from 1 to 20000\n";
		exit(1);
	}
}

/* community/user/password verification */
if ($host['snmp_version'] < 3) {
	/* snmp community can be blank */
} else {
	if ($host['snmp_username'] == "" || $host['snmp_password'] == "") {
		print "ERROR: When using snmpv3 you must supply an username and password\n";
		exit(1);
	}
}

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
	exit(1);
} else {
	if (!$quietMode) {
		print "Success\n";
	}
	exit(0);
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
	print "    [--quiet]\n\n";
	print "Required:\n";
	print "    --id           the id for a device, that is field id in table host\n";
	print "                   any optional device attribute parameter given, will replace the existing parameter\n";
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
	print "    --quiet - batch mode value return\n\n";
}
