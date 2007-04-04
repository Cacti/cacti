#!/usr/bin/php -q
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

include(dirname(__FILE__)."/include/config.php");
include_once($config["base_path"]."/lib/api_automation_tools.php");
include_once($config["base_path"]."/lib/utility.php");
include_once($config["base_path"]."/lib/api_data_source.php");
include_once($config["base_path"]."/lib/api_graph.php");
include_once($config["base_path"]."/lib/snmp.php");
include_once($config["base_path"]."/lib/data_query.php");
include_once($config["base_path"]."/lib/api_device.php");

if ($_SERVER["argc"] == 1) {
	usage();
}elseif ($_SERVER["argc"] == 7) {
	$host_templates = get_host_templates();
	$hosts          = get_hosts();
	$addresses      = get_addresses();

	/* sanitize variables */
	input_validate_input_number($_SERVER["argv"][1]);
	input_validate_input_number($_SERVER["argv"][5]);
	input_validate_input_number($_SERVER["argv"][6]);

	/* clean up description */
	if (isset($_SERVER["argv"][2])) {
		$description = sanitize_search_string($_SERVER["argv"][2]);
	}

	/* clean up ipaddress */
	if (isset($_SERVER["argv"][4])) {
		$ip = sanitize_search_string($_SERVER["argv"][4]);
	}

	/* clean up snmp community */
	if (isset($_SERVER["argv"][3])) {
		$community = sanitize_search_string($_SERVER["argv"][3]);
	}

	$template_id   = $_SERVER["argv"][1];
	$snmp_ver      = $_SERVER["argv"][5];
	$disable       = $_SERVER["argv"][6];

	$snmp_username = "";
	$snmp_password = "";
	$snmp_port     = 161;
	$snmp_timeout  = 500;

	if (!isset($host_templates[$template_id])) {
		echo "Unknown template id ($template_id)\n";
		exit(1);
	}

	if (isset($hosts[$description])) {
		db_execute("update host set hostname = '$ip' where id = " . $hosts[$description]);
		echo "This host already exists in the database ($description) device-id: (" . $hosts[$description] . ")\n";
		exit(1);
	}

	if (isset($addresses[$ip])) {
		db_execute("update host set description = '$description' where id = " . $addresses[$ip]);
		echo "This IP already exists in the database ($ip) device-id: (" . $addresses[$ip] . ")\n";
		exit(1);
	}

	if ($snmp_ver != "1" && $snmp_ver != "2") {
		echo "Invalid snmp version ($snmp_ver)\n";
		exit(1);
	}

	if ($disable != "1" && $disable != "0") {
		echo "Invalid disable flag ($disable)\n";
		exit(1);
	}

	if ($disable == "0") {
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
	}
}else{
	for ($i = 1; $i < $_SERVER["argc"]; $i++) {
		switch ($_SERVER["argv"][$i]) {
		case "--communities":
			display_communities();
			break;
		case "-h":
		case "--h":
		case "--help":
			usage();
			break;
		case "--host-templates":
			display_host_templates(get_host_templates());
			break;
		default:
			break;
		}
	}
}

return 0;

function usage() {
	echo "Usage:\n";
	echo "add_device.php templateid description IP snmp_community snmp_version disable\n\n";
	echo "Where:\n";
	echo "    - templateid is a number (read below to get a list of templates)\n";
	echo "    - description: the name that will be displayed by Cacti in the graphs\n";
	echo "    - IP: self explanatory (can also be a FQDN)\n";
	echo "    - snmp_community: community string\n";
	echo "    - snmp_version: 1/2\n";
	echo "    - disable: 1 to add this host but to disable checks and 0 to enable it\n\n";
	echo "Alternative usages:\n";
	echo "add_device.php [--host-templates]\n\n";
	echo "Where:\n";
	echo "    --host-templates: returns the valid host templates\n";
	echo "    --communities: returns the known community strings\n";
}

?>
