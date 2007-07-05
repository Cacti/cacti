#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2007 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

/* We are not talking to the browser */
$no_http_headers = true;

include_once("./include/config.php");
include_once($config["base_path"]."/lib/api_automation_tools.php");
include_once($config["base_path"]."/lib/utility.php");
include_once($config["base_path"]."/lib/api_data_source.php");
include_once($config["base_path"]."/lib/api_graph.php");
include_once($config["base_path"]."/lib/snmp.php");
include_once($config["base_path"]."/lib/data_query.php");
include_once($config["base_path"]."/lib/api_device.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

if (sizeof($parms)) {
	/* setup defaults */
	$description   = "";
	$ip            = "";
	$template_id   = -1;
	$community     = "";
	$snmp_ver      = 1;
	$disable       = 0;
	$snmp_username = "";
	$snmp_password = "";
	$snmp_port     = 161;
	$snmp_timeout  = 500;

	foreach($parms as $parameter) {
		@list($arg, $value) = @explode("=", $parameter);

		switch ($arg) {
		case "-d":
			$debug = TRUE;
			break;
		case "--description":
			$description = trim($value);
			break;
		case "--ip":
			$ip = trim($value);
			break;
		case "--template":
			$template_id = $value;
			break;
		case "--community":
			$community = trim($value);
			break;
		case "--version":
			$snmp_ver = trim($value);
			break;
		case "--disable":
			$disable  = $value;
			break;
		case "--username":
			$snmp_username = trim($value);
			break;
		case "--password":
			$snmp_password = trim($value);
			break;
		case "--port":
			$snmp_port     = $value;
			break;
		case "--timeout":
			$snmp_timeout  = $value;
			break;
		case "-h":
			display_help();
			return 0;
		case "-v":
		case "-V":
			display_help();
			return 0;
		case "--help":
			display_help();
			return 0;
		case "--list-communities":
			display_communities();
			return 0;
		case "-h":
		case "--h":
		case "--help":
			display_help();
			return 0;
		case "--list-host-templates":
			display_host_templates(get_host_templates());
			return 0;
		default:
			print "ERROR: Invalid Parameter " . $parameter . "\n\n";
			display_help();
			return 1;
		}
	}

	/* process the various lists into validation arrays */
	$host_templates = get_host_templates();
	$hosts          = get_hosts();
	$addresses      = get_addresses();

	/* process templates */
	if (!isset($host_templates[$template_id])) {
		echo "Unknown template id ($template_id)\n";

		return 1;
	}

	/* process host description */
	if (isset($hosts[$description])) {
		db_execute("update host set hostname = '$ip' where id = " . $hosts[$description]);
		echo "This host already exists in the database ($description) device-id: (" . $hosts[$description] . ")\n";
		return 1;
	}

	if ($description == "") {
		echo "You must supply a description for all hosts!\n";
		return 1;
	}

	/* process ip */
	if (isset($addresses[$ip])) {
		db_execute("update host set description = '$description' where id = " . $addresses[$ip]);
		echo "This IP already exists in the database ($ip) device-id: (" . $addresses[$ip] . ")\n";
		return 1;
	}

	if ($ip == "") {
		echo "You must supply an IP address for all hosts!\n";
		return 1;
	}

	/* process snmp information */
	if ($snmp_ver != "1" && $snmp_ver != "2" && $snmp_ver != "3") {
		echo "Invalid snmp version ($snmp_ver)\n";
		return 1;
	}else{
		if ($snmp_port <= 0 || $snmp_port > 65535) {
			echo "Invalid port.  Valid values are from 1-65535\n";
			return 1;
		}

		if ($snmp_timeout <= 0 || $snmp_timeout > 20000) {
			echo "Invalid timeout.  Valid values are from 1 to 20000\n";
			return 1;
		}
	}

	/* community/user/password verification */
	if ($snmp_ver == "1" || $snmp_ver == "2") {
		/* snmp community can be blank */
	}else{
		if ($snmp_username == "" || $snmp_password == "") {
			echo "When using snmpv3 you must supply an username and password\n";
			return 1;
		}
	}

	/* validate the disable state */
	if ($disable != 1 && $disable != 0) {
		echo "Invalid disable flag ($disable)\n";
		return 1;
	}

	if ($disable == 0) {
		$disable = "";
	}else{
		$disable = "on";
	}

	echo "Adding $description ($ip) as \"" . $host_templates[$template_id] . "\" using SNMP v$snmp_ver with community \"$community\"\n";

	$host_id = api_device_save(0, $template_id, $description, $ip,
				$community, $snmp_ver, $snmp_username, $snmp_password,
				$snmp_port, $snmp_timeout, $disable);

	if (is_error_message()) {
		echo "Failed to add this device\n";
		return 1;
	} else {
		echo "Success - new device-id: ($host_id)\n";
		return 0;
	}
}else{
	display_help();

	return 0;
}

function display_help() {
	echo "Usage:\n";
	echo "add_device.php --description=[description] --ip=[IP] --template=[ID] [--disable]\n";
	echo "   [--version=[1|2|3]] [--community=] [--username= --password=] [--port=161] [--timeout=500]\n\n";
	echo "Required:\n";
	echo "    - description: the name that will be displayed by Cacti in the graphs\n";
	echo "    - ip: self explanatory (can also be a FQDN)\n";
	echo "    - template is a number (read below to get a list of templates)\n\n";
	echo "Optional:\n";
	echo "    - disable: 0, 1 to add this host but to disable checks and 0 to enable it\n";
	echo "    - version: 1, 1|2|3, snmp version\n";
	echo "    - community: '', snmp community string for snmpv1 and snmpv2.  Leave blank for no community\n";
	echo "    - username: '', snmp username for snmpv3\n";
	echo "    - password: '', snmp password for snmpv3\n";
	echo "    - port: 161\n";
	echo "    - timeout: 500\n\n";
	echo "List Options:  --list-templates\n";
	echo "               --list-communities\n\n";
}

?>
