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
require_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
require_once(CACTI_PATH_LIBRARY . '/api_device.php');
require_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
require_once(CACTI_PATH_LIBRARY . '/api_graph.php');
require_once(CACTI_PATH_LIBRARY . '/api_tree.php');
require_once(CACTI_PATH_LIBRARY . '/data_query.php');
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/snmp.php');
require_once(CACTI_PATH_LIBRARY . '/template.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

if (! cacti_sizeof($parms)) {
	display_help();

	exit(0);
}

// setup defaults
$device_id   = 0;

$displayHostTemplates = false;
$displayCommunities   = false;
$quietMode            = false;

$overrides = [];

foreach ($parms as $parameter) {
	if (str_contains($parameter, '=')) {
		[$arg, $value] = explode('=', $parameter, 2);
	} else {
		$arg   = $parameter;
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
			$device_id = intval($value);

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
				$overrides['disabled'] = $value == 'on' ? 'on' : '';
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
		case '--retries':
			$overrides['snmp_retries'] = $value;

			break;
		case '--options':
			$overrides['snmp_options'] = $value;

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
					$overrides['availability_method'] = '0'; // tried to use AVAIL_NONE, but then preg_match fails on validation, sigh

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
					print "ERROR: Invalid Availability Parameter: ($value)" . PHP_EOL . PHP_EOL;
					display_help();

					exit(1);
			}

			break;
		case '--ping_method':
			switch(cacti_strtolower($value)) {
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
					print "ERROR: Invalid Ping Method: ($value)" . PHP_EOL . PHP_EOL;
					display_help();

					exit(1);
			}

			break;
		case '--ping_port':
			if (is_numeric($value) && ($value > 0)) {
				$overrides['ping_port'] = $value;
			} else {
				print "ERROR: Invalid Ping Port: ($value)" . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
			}

			break;
		case '--ping_retries':
			if (is_numeric($value) && ($value > 0)) {
				$overrides['ping_retries'] = $value;
			} else {
				print "ERROR: Invalid Ping Retries: ($value)" . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
			}

			break;
		case '--max_oids':
			if (is_numeric($value) && ($value > 0)) {
				$overrides['max_oids'] = $value;
			} else {
				print "ERROR: Invalid Max OIDS: ($value)" . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
			}

			break;
		case '--bulk_walk':
			if (is_numeric($value) && $value >= -1 && $value != 0) {
				$overrides['bulk_walk_size'] = $value;
			} else {
				print "ERROR: Invalid Bulk Walk Size: ($value)" . PHP_EOL . PHP_EOL;
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
			print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
			display_help();

			exit(1);
	}
}

if ($device_id <= 0) {
	print 'ERROR: --id is mandatory parameter.' . PHP_EOL;
	display_help();

	exit(1);
}

$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$device_id]);

if (!cacti_sizeof($host)) {
	print "ERROR: device-id $device_id not found." . PHP_EOL;

	exit(1);
}

// merge overridden parameters onto host
$host = array_merge($host, $overrides);

// exception for IP
if (isset($overrides['ip'])) {
	$host['hostname'] = $overrides['ip'];
}

// process the various lists into validation arrays
$host_templates = getHostTemplates();
$hosts          = getHostsByDescription();
$addresses      = getAddresses();

// process templates
if (!isset($host_templates[$host['host_template_id']])) {
	print 'ERROR: Unknown template id (' . $host['host_template_id'] . ')' . PHP_EOL;

	exit(1);
}

if ($host['description'] == '') {
	print 'ERROR: You must supply a description for all hosts!' . PHP_EOL;

	exit(1);
}

if ($host['hostname'] == '') {
	print 'ERROR: You must supply an IP address for all hosts!' . PHP_EOL;

	exit(1);
}

if ($host['snmp_version'] > 3 || $host['snmp_version'] < 0 || !is_numeric($host['snmp_version'])) {
	print 'ERROR: The snmp version must be between 0 and 3.  If you did not specify one, goto Configuration > Settings > Device Defaults and resave your defaults.' . PHP_EOL;

	exit(1);
}

if (!is_numeric($host['site_id']) || $host['site_id'] < 0) {
	print 'ERROR: You have specified an invalid site id!' . PHP_EOL;

	exit(1);
}

if (!is_numeric($host['poller_id']) || $host['poller_id'] < 0) {
	print 'ERROR: You have specified an invalid poller id!' . PHP_EOL;

	exit(1);
}

// process snmp information
if ($host['snmp_version'] < 0 || $host['snmp_version'] > 3) {
	print "ERROR: Invalid snmp version ({$host['snmp_version']})" . PHP_EOL;

	exit(1);
}

if ($host['snmp_version'] > 0) {
	if ($host['snmp_port'] <= 1 || $host['snmp_port'] > 65534) {
		print 'ERROR: Invalid port.  Valid values are from 1-65534' . PHP_EOL;

		exit(1);
	}

	if ($host['snmp_timeout'] <= 0 || $host['snmp_timeout'] > 20000) {
		print 'ERROR: Invalid timeout.  Valid values are from 1 to 20000' . PHP_EOL;

		exit(1);
	}
}

// community/user/password verification
if ($host['snmp_version'] < 3) {
	// snmp community can be blank
} else {
	if ($host['snmp_username'] == '' || $host['snmp_password'] == '') {
		print 'ERROR: When using snmpv3 you must supply an username and password' . PHP_EOL;

		exit(1);
	}
}

if (!$quietMode) {
	print "Changing device-id: $device_id to {$host['description']} ({$host['hostname']}) as \"{$host_templates[$host['host_template_id']]}\" using SNMP v{$host['snmp_version']} with community \"{$host['snmp_community']}\"" . PHP_EOL;
}

$host_id = api_device_save($device_id, $host['host_template_id'], $host['description'], $host['hostname'],
	$host['snmp_community'], $host['snmp_version'], $host['snmp_username'], $host['snmp_password'],
	$host['snmp_port'], $host['snmp_timeout'], $host['disabled'], $host['availability_method'], $host['ping_method'],
	$host['ping_port'], $host['ping_timeout'], $host['ping_retries'], $host['notes'],
	$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'],
	$host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_engine_id'], $host['max_oids'], $host['device_threads'],
	$host['poller_id'], $host['site_id'], $host['external_id'], $host['location'], $host['bulk_walk_size'], $host['snmp_options'], $host['snmp_retries']);

if (is_error_message() || $host_id != $device_id) {
	print "ERROR: Failed to change this device ($device_id-$host_id)" . PHP_EOL;

	exit(1);
} else {
	if (!$quietMode) {
		print 'Success' . PHP_EOL;
	}

	exit(0);
}

/**
 * display_version - displays version information
 *
 * @return void
 */
function display_version() : void {
	$version = get_cacti_cli_version();
	print "Cacti Change Device Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays help information
 *
 * @return void
 */
function display_help() : void {
	display_version();

	print PHP_EOL;
	print 'usage: change_device.php --id=<device-id> [--description=[description]] [--ip=[IP]] [--template=[ID]] [--notes="[]"] [--disable]' . PHP_EOL;
	print '    [--poller=[id]] [--site=[id] [--external-id=[S]] [--proxy] [--threads=[1]' . PHP_EOL;
	print '    [--avail=[ping]] --ping_method=[icmp] --ping_port=[N/A, 1-65534] --ping_timeout=[N] --ping_retries=[2]' . PHP_EOL;
	print '    [--version=[0|1|2|3]] [--community=] [--port=161] [--timeout=500] [--retries=3] [--options=0]' . PHP_EOL;
	print '    [--username= --password=] [--authproto=] [--privpass= --privproto=] [--context=] [--engineid=]' . PHP_EOL;
	print '    [--quiet]' . PHP_EOL . PHP_EOL;

	print 'Required:' . PHP_EOL;
	print '    --id           the id for a device, that is field id in table host' . PHP_EOL;
	print '                   any optional device attribute parameter given, will replace the existing parameter' . PHP_EOL . PHP_EOL;

	print 'Optional:' . PHP_EOL;
	print '    --description  the name that will be displayed by Cacti in the graphs' . PHP_EOL;
	print '    --ip           self explanatory (can also be a FQDN)' . PHP_EOL;
	print '    --proxy        if specified, allows adding a second host with same ip address' . PHP_EOL;
	print '    --template     0, is a number (read below to get a list of templates)' . PHP_EOL;
	print "    --location     '', The physical location of the Device." . PHP_EOL;
	print "    --notes        '', General information about this host.  Must be enclosed using double quotes." . PHP_EOL;
	print "    --external-id  '', An external ID to align Cacti devices with devices from other systems." . PHP_EOL;
	print '    --disable      0, 1 to add this host but to disable checks and 0 to enable it' . PHP_EOL;
	print '    --poller       0, numeric poller id that will perform data collection for the device.' . PHP_EOL;
	print '    --site         0, numeric site id that will be associated with the device.' . PHP_EOL;
	print '    --threads      1, numeric number of threads to poll device with.' . PHP_EOL;
	print '    --avail        pingsnmp, [ping][none, snmp, pingsnmp, pingorsnmp]' . PHP_EOL;
	print '    --ping_method  tcp, icmp|tcp|udp' . PHP_EOL;
	print "    --ping_port    '', 1-65534" . PHP_EOL;
	print '    --ping_retries 2, the number of time to attempt to communicate with a host' . PHP_EOL;
	print '    --ping_timeout N, the ping timeout in milliseconds.  Defaults to database setting.' . PHP_EOL;
	print '    --version      1, 0|1|2|3, snmp version.  0 for no snmp' . PHP_EOL;
	print "    --community    '', snmp community string for snmpv1 and snmpv2.  Leave blank for no community" . PHP_EOL;
	print '    --port         161' . PHP_EOL;
	print '    --timeout      500, The default snmp timeout' . PHP_EOL;
	print '    --retries      3, The number of snmp retries' . PHP_EOL;
	print '    --options      0, The SNMP Recovery Template Options set to use' . PHP_EOL;
	print "    --username     '', snmp username for snmpv3" . PHP_EOL;
	print "    --password     '', snmp password for snmpv3" . PHP_EOL;
	print "    --authproto    '', snmp authentication protocol for snmpv3" . PHP_EOL;
	print "    --privpass     '', snmp privacy passphrase for snmpv3" . PHP_EOL;
	print "    --privproto    '', snmp privacy protocol for snmpv3" . PHP_EOL;
	print "    --context      '', snmp context for snmpv3" . PHP_EOL;
	print "    --engineid     '', snmp engineid for snmpv3" . PHP_EOL;
	print '    --max_oids     10, 1-60, the number of OIDs that can be obtained in a single SNMP Get request' . PHP_EOL;
	print '    --bulk_walk    -1, 1-60, the bulk walk chunk size that will be used for bulk walks.  Use -1 for auto-tune.' . PHP_EOL;
	print '    --quiet - batch mode value return' . PHP_EOL . PHP_EOL;
}
