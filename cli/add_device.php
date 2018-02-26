#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* We are not talking to the browser */
$no_http_headers = true;

include(dirname(__FILE__).'/../include/global.php');
include_once($config['base_path'].'/lib/api_automation_tools.php');
include_once($config['base_path'].'/lib/utility.php');
include_once($config['base_path'].'/lib/api_data_source.php');
include_once($config['base_path'].'/lib/api_graph.php');
include_once($config['base_path'].'/lib/snmp.php');
include_once($config['base_path'].'/lib/data_query.php');
include_once($config['base_path'].'/lib/api_device.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	/* setup defaults */
	$description   = '';
	$ip            = '';
	$poller_id     = $config['poller_id'];
	$site_id       = read_config_option('default_site');
	$template_id   = read_config_option('default_template');
	$community     = read_config_option('snmp_community');
	$snmp_ver      = read_config_option('snmp_version');
	$disable       = 0;

	$notes         = '';
	$location      = '';
	$external_id   = '';

	$snmp_username        = read_config_option('snmp_username');
	$snmp_password        = read_config_option('snmp_password');
	$snmp_auth_protocol   = read_config_option('snmp_auth_protocol');
	$snmp_priv_passphrase = read_config_option('snmp_priv_passphrase');
	$snmp_priv_protocol   = read_config_option('snmp_priv_protocol');
	$snmp_context         = '';
	$snmp_engine_id       = '';
	$snmp_port            = read_config_option('snmp_port');
	$snmp_timeout         = read_config_option('snmp_timeout');

	$avail          = 1;
	$ping_method    = read_config_option('ping_method');
	$ping_port      = read_config_option('ping_port');
	$ping_timeout   = read_config_option('ping_timeout');
	$ping_retries   = read_config_option('ping_retries');
	$max_oids       = read_config_option('max_get_size');
	$proxy          = false;
	$device_threads = read_config_option('device_threads');;

	$displayHostTemplates = false;
	$displayCommunities   = false;
	$quietMode            = false;

	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
		case '-d':
			$debug = true;

			break;
		case '--description':
			$description = trim($value);

			break;
		case '--ip':
			$ip = trim($value);

			break;
		case '--template':
			$template_id = $value;

			break;
		case '--community':
			$community = trim($value);

			break;
		case '--version':
			if (sizeof($parms) == 1) {
				display_version();
				exit;
			} else {
				$snmp_ver = trim($value);
			}

			break;
		case '--notes':
			$notes = trim($value);

			break;
		case '--location':
			$location = trim($value);

			break;
		case '--site':
			$site_id = trim($value);

			break;
		case '--poller':
			$poller_id = trim($value);

			break;
		case '--disable':
			$disable  = $value;

			break;
		case '--external-id':
			$external_id  = $value;

			break;
		case '--username':
			$snmp_username = trim($value);

			break;
		case '--password':
			$snmp_password = trim($value);

			break;
		case '--authproto':
			$snmp_auth_protocol = trim($value);

			break;
		case '--privproto':
			$snmp_priv_protocol = trim($value);

			break;
		case '--privpass':
			$snmp_priv_passphrase = trim($value);

			break;
		case '--context':
			$snmp_context = trim($value);

			break;
		case '--engineid':
			$snmp_engine_id = trim($value);

			break;
		case '--port':
			$snmp_port = $value;

			break;
		case '--proxy':
			$proxy = true;

			break;
		case '--timeout':
			$snmp_timeout = $value;

			break;
		case '--ping_timeout':
			$ping_timeout = $value;

			break;
		case '--threads':
			$device_threads = $value;

			break;
		case '--avail':
			switch($value) {
			case 'none':
				$avail = '0'; /* tried to use AVAIL_NONE, but then preg_match failes on validation, sigh */

				break;
			case 'ping':
				$avail = AVAIL_PING;

				break;
			case 'snmp':
				$avail = AVAIL_SNMP;

				break;
			case 'pingsnmp':
				$avail = AVAIL_SNMP_AND_PING;

				break;
			default:
				echo "ERROR: Invalid Availability Parameter: ($value)\n\n";
				display_help();
				exit(1);
			}

			break;
		case '--ping_method':
			switch(strtolower($value)) {
			case 'icmp':
				$ping_method = PING_ICMP;

				break;
			case 'tcp':
				$ping_method = PING_TCP;

				break;
			case 'udp':
				$ping_method = PING_UDP;

				break;
			default:
				echo "ERROR: Invalid Ping Method: ($value)\n\n";
				display_help();
				exit(1);
			}

			break;
		case '--ping_port':
			if (is_numeric($value) && ($value > 0)) {
				$ping_port = $value;
			} else {
				echo "ERROR: Invalid Ping Port: ($value)\n\n";
				display_help();
				exit(1);
			}

			break;
		case '--ping_retries':
			if (is_numeric($value) && ($value > 0)) {
				$ping_retries = $value;
			} else {
				echo "ERROR: Invalid Ping Retries: ($value)\n\n";
				display_help();
				exit(1);
			}

			break;
		case '--max_oids':
			if (is_numeric($value) && ($value > 0)) {
				$max_oids = $value;
			} else {
				echo "ERROR: Invalid Max OIDS: ($value)\n\n";
				display_help();
				exit(1);
			}

			break;
		case '--version':
		case '-V':
		case '-v':
			display_version();
			exit;
		case '--help':
		case '-H':
		case '-h':
			display_help();
			exit;
		case '--list-communities':
			$displayCommunities = true;

			break;
		case '--list-host-templates':
			$displayHostTemplates = true;

			break;
		case '--quiet':
			$quietMode = true;

			break;
		default:
			echo "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
		}
	}

	if ($displayCommunities) {
		displayCommunities($quietMode);
		exit(0);
	}

	if ($displayHostTemplates) {
		displayHostTemplates(getHostTemplates(), $quietMode);
		exit(0);
	}

	/* process the various lists into validation arrays */
	$host_templates = getHostTemplates();
	$hosts          = getHostsByDescription();
	$addresses      = getAddresses();

	/* process templates */
	if (!isset($host_templates[$template_id])) {
		echo "ERROR: Unknown template id ($template_id)\n";
		exit(1);
	}

	/* process host description */
	if (isset($hosts[$description])) {
		db_execute("UPDATE host SET hostname='$ip' WHERE id=" . $hosts[$description]);
		echo "This host already exists in the database ($description) device-id: (" . $hosts[$description] . ")\n";
		exit(1);
	}

	if ($description == "") {
		echo "ERROR: You must supply a description for all hosts!\n";
		exit(1);
	}

	if ($ip == "") {
		echo "ERROR: You must supply an IP address for all hosts!\n";
		exit(1);
	}

	/* process ip */
	if (isset($addresses[$ip])) {
		$id    = $addresses[$ip];
		$phost = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($id));
		$fail  = false;

		if ($phost['snmp_version'] < '3' && $snmp_ver < '3') {
			if ($snmp_ver == 0 && $proxy) {
				// proxy but for no snmp
			} elseif ($phost['snmp_community'] != $community) {
				if ($proxy) {
					// assuming an snmp-proxy
				} else {
					echo "ERROR: This IP ($id) already exists in the database and --proxy was not specified.\n";
					exit(1);
				}
			} else {
				$fail = true;
			}
		} elseif ($phost['snmp_version'] != $snmp_ver) {
			// assumeing a proxy
		} elseif ($phost['snmp_version'] == '3' && $snmp_ver == '3') {
			$changed = 0;
			$changed += ($phost['snmp_username'] != $username ? 1:0);
			$changed += ($phost['snmp_context'] != $snmp_context ? 1:0);
			$changed += ($phost['snmp_engine_id'] != $snmp_engine_id ? 1:0);
			$changed += ($phost['snmp_auth_protocol'] != $snmp_auth_protocol ? 1:0);
			$changed += ($phost['snmp_priv_protocol'] != $snmp_priv_protocol ? 1:0);

			if ($changed > 0) {
				if ($proxy) {
					// assuming a proxy
				} else {
					echo "ERROR: This IP ($id) already exists in the database and --proxy was not specified.\n";
					exit(1);
				}
			} else {
				$fail = true;
			}
		} else {
			$fail = true;
		}

		if ($fail) {
			db_execute("UPDATE host SET description = '$description' WHERE id = " . $addresses[$ip]);
			echo "ERROR: This IP already exists in the database ($ip) device-id: (" . $addresses[$ip] . ")\n";
			exit(1);
		}
	}

	if (!is_numeric($site_id) || $site_id < 0) {
		echo "ERROR: You have specified an invalid site id!\n";
		exit(1);
	}

	if (!is_numeric($poller_id) || $poller_id < 0) {
		echo "ERROR: You have specified an invalid poller id!\n";
		exit(1);
	}

	/* process snmp information */
	if ($snmp_ver < 0 || $snmp_ver > 3) {
		echo "ERROR: Invalid snmp version ($snmp_ver)\n";
 		exit(1);
	} elseif ($snmp_ver > 0) {
		if ($snmp_port <= 1 || $snmp_port > 65534) {
			echo "ERROR: Invalid port.  Valid values are from 1-65534\n";
			exit(1);
		}

		if ($snmp_timeout <= 0 || $snmp_timeout > 20000) {
			echo "ERROR: Invalid timeout.  Valid values are from 1 to 20000\n";
			exit(1);
		}
	}

	/* community/user/password verification */
	if ($snmp_ver < 3) {
		/* snmp community can be blank */
	} else {
		if ($snmp_username == "" || $snmp_password == "") {
			echo "ERROR: When using snmpv3 you must supply an username and password\n";
			exit(1);
		}
	}

	/* validate the disable state */
	if ($disable != 1 && $disable != 0) {
		echo "ERROR: Invalid disable flag ($disable)\n";
		exit(1);
	}

	if ($disable == 0) {
		$disable = "";
	} else {
		$disable = "on";
	}

	echo "Adding $description ($ip) as \"" . $host_templates[$template_id] . "\" using SNMP v$snmp_ver with community \"$community\"\n";

	$host_id = api_device_save('0', $template_id, $description, $ip,
		$community, $snmp_ver, $snmp_username, $snmp_password,
		$snmp_port, $snmp_timeout, $disable, $avail, $ping_method,
		$ping_port, $ping_timeout, $ping_retries, $notes,
		$snmp_auth_protocol, $snmp_priv_passphrase,
		$snmp_priv_protocol, $snmp_context, $snmp_engine_id, $max_oids, $device_threads,
		$poller_id, $site_id, $external_id, $location);

	if (is_error_message()) {
		echo "ERROR: Failed to add this device\n";
		exit(1);
	} else {
		echo "Success - new device-id: ($host_id)\n";
		exit(0);
	}
} else {
	display_help();
	exit(0);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_version();
	echo "Cacti Add Device Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	echo "\nusage: add_device.php --description=[description] --ip=[IP] --template=[ID] [--notes=\"[]\"] [--disable]\n";
	echo "    [--poller=[id]] [--site=[id] [--external-id=[S]] [--proxy] [--threads=[1]\n";
	echo "    [--avail=[ping]] --ping_method=[icmp] --ping_port=[N/A, 1-65534] --ping_timeout=[N] --ping_retries=[2]\n";
	echo "    [--version=[0|1|2|3]] [--community=] [--port=161] [--timeout=500]\n";
	echo "    [--username= --password=] [--authproto=] [--privpass= --privproto=] [--context=] [--engineid=]\n";
	echo "    [--quiet]\n\n";
	echo "Required:\n";
	echo "    --description  the name that will be displayed by Cacti in the graphs\n";
	echo "    --ip           self explanatory (can also be a FQDN)\n\n";
	echo "Optional:\n";
	echo "    --proxy        if specified, allows adding a second host with same ip address\n";
	echo "    --template     0, is a number (read below to get a list of templates)\n";
	echo "    --location     '', The physical location of the Device.\n";
	echo "    --notes        '', General information about this host.  Must be enclosed using double quotes.\n";
	echo "    --external-id  '', An external ID to align Cacti devices with devices from other systems.\n";
	echo "    --disable      0, 1 to add this host but to disable checks and 0 to enable it\n";
	echo "    --poller       0, numeric poller id that will perform data collection for the device.\n";
	echo "    --site         0, numeric site id that will be associated with the device.\n";
	echo "    --threads      1, numeric number of threads to poll device with.\n";
	echo "    --avail        pingsnmp, [ping][none, snmp, pingsnmp]\n";
	echo "    --ping_method  tcp, icmp|tcp|udp\n";
	echo "    --ping_port    '', 1-65534\n";
	echo "    --ping_retries 2, the number of time to attempt to communicate with a host\n";
	echo "    --ping_timeout N, the ping timeout in milliseconds.  Defaults to database setting.\n";
	echo "    --version      1, 0|1|2|3, snmp version.  0 for no snmp\n";
	echo "    --community    '', snmp community string for snmpv1 and snmpv2.  Leave blank for no community\n";
	echo "    --port         161\n";
	echo "    --timeout      500\n";
	echo "    --username     '', snmp username for snmpv3\n";
	echo "    --password     '', snmp password for snmpv3\n";
	echo "    --authproto    '', snmp authentication protocol for snmpv3\n";
	echo "    --privpass     '', snmp privacy passphrase for snmpv3\n";
	echo "    --privproto    '', snmp privacy protocol for snmpv3\n";
	echo "    --context      '', snmp context for snmpv3\n";
	echo "    --engineid     '', snmp engineid for snmpv3\n";
	echo "    --max_oids     10, 1-60, the number of OID's that can be obtained in a single SNMP Get request\n\n";
	echo "List Options:\n";
	echo "    --list-host-templates\n";
	echo "    --list-communities\n";
	echo "    --quiet - batch mode value return\n\n";
}
