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
 | This program is distributed in the hope that it will be useful,
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

define("REGEXP_SNMP_TRIM", "(hex|counter(32|64)|gauge|gauge(32|64)|float|ipaddress|string|integer):");

/* we must use an apostrophe to escape community names under Unix in case the user uses
characters that the shell might interpret. the ucd-snmp binaries on Windows flip out when
you do this, but are perfectly happy with a quotation mark. */
if ($config["cacti_server_os"] == "unix") {
	define("SNMP_ESCAPE_CHARACTER", "'");
}else{
	define("SNMP_ESCAPE_CHARACTER", "\"");
}

function cacti_snmp_get($hostname, $community, $oid, $version, $username, $password, $port = 161, $timeout = 500, $environ = SNMP_POLLER) {
	global $config;

	$retries = read_config_option("snmp_retries");
	if ($retries == "") $retries = 3;
	if (($environ != SNMP_POLLER) && ($environ != SNMP_WEBUI) && ($environ != SNMP_CMDPHP)) {
		$environ = SNMP_POLLER;
	}

	if (($config["php_snmp_support"] == true) &&
		(($version == "1") || (($version == "2") && ($environ == SNMP_WEBUI)))) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		$snmp_value = @snmpget("$hostname:$port", $community, $oid, ($timeout * 1000), $retries);
	}else{
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == "1") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER : "-c " . SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER; /* v1/v2 - community string */
		}elseif ($version == "2") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER : "-c " . SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER; /* v1/v2 - community string */
			$version = "2c"; /* ucd/net snmp prefers this over '2' */
		}elseif ($version == "3") {
			$snmp_auth = "-u $username -X $password"; /* v3 - username/password */
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) { return; }

		if (read_config_option("snmp_version") == "ucd-snmp") {
			$snmp_value = exec(read_config_option("path_snmpget") . " -O vt -v$version -t $timeout -r $retries $hostname:$port $snmp_auth $oid");
		}elseif (read_config_option("snmp_version") == "net-snmp") {
			$snmp_value = exec(read_config_option("path_snmpget") . " -O vt $snmp_auth -v $version -t $timeout -r $retries $hostname:$port $oid");
		}
	}

	$snmp_value = format_snmp_string($snmp_value);

	return $snmp_value;
}

function cacti_snmp_walk($hostname, $community, $oid, $version, $username, $password, $port = 161, $timeout = 500, $environ = SNMP_POLLER) {
	global $config;

	$snmp_array = array();
	$temp_array = array();

	$retries = read_config_option("snmp_retries");
	if ($retries == "") $retries = 3;

	if (($environ != SNMP_POLLER) && ($environ != SNMP_WEBUI) && ($environ != SNMP_CMDPHP)) {
		$environ = SNMP_POLLER;
	}

	if (($config["php_snmp_support"] == true) &&
		(($version == "1") || (($version == "2") && ($environ == SNMP_WEBUI)))) {
		$temp_array = @snmpwalkoid("$hostname:$port", $community, $oid, ($timeout * 1000), $retries);

		$o = 0;
		for (@reset($temp_array); $i = @key($temp_array); next($temp_array)) {
			$snmp_array[$o]["oid"] = ereg_replace("^\.", "", $i);
			$snmp_array[$o]["value"] = format_snmp_string($temp_array[$i]);
			$o++;
		}
	}else{
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == "1") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER : "-c " . SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER; /* v1/v2 - community string */
		}elseif ($version == "2") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER : "-c " . SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER; /* v1/v2 - community string */
			$version = "2c"; /* ucd/net snmp prefers this over '2' */
		}elseif ($version == "3") {
			$snmp_auth = "-u $username -X $password"; /* v3 - username/password */
		}

		if (read_config_option("snmp_version") == "ucd-snmp") {
			$temp_array = exec_into_array(read_config_option("path_snmpwalk") . " -v$version -t $timeout -r $retries $hostname:$port $snmp_auth $oid");
		}elseif (read_config_option("snmp_version") == "net-snmp") {
			$temp_array = exec_into_array(read_config_option("path_snmpwalk") . " $snmp_auth -v $version -t $timeout -r $retries $hostname:$port $oid");
		}

		if (sizeof($temp_array) == 0) {
			return 0;
		}

		for ($i=0; $i < count($temp_array); $i++) {
			$snmp_array[$i]["oid"] = trim(ereg_replace("(.*) =.*", "\\1", $temp_array[$i]));
			$snmp_array[$i]["value"] = format_snmp_string($temp_array[$i]);
		}
	}

	return $snmp_array;
}

function format_snmp_string($string) {
	/* strip off all leading junk (the oid and stuff) */
	$string = trim(ereg_replace(".*= ?", "", $string));

	/* remove ALL quotes */
	$string = str_replace("\"", "", $string);
	$string = str_replace("'", "", $string);
	$string = str_replace(">", "", $string);
	$string = str_replace("<", "", $string);

	if (preg_match("/(hex:\?)?([a-fA-F0-9]{1,2}(:|\s)){5}/", $string)) {
		$octet = "";

		/* strip of the 'hex:' */
		$string = eregi_replace("hex: ?", "", $string);

		/* split the hex on the delimiter */
		$octets = preg_split("/\s|:/", $string);

		/* loop through each octet and format it accordingly */
		for ($i=0;($i<count($octets));$i++) {
			$octet .= str_pad($octets[$i], 2, "0", STR_PAD_LEFT);

			if (($i+1) < count($octets)) {
				$octet .= ":";
			}
		}

		/* copy the final result and make it upper case */
		$string = strtoupper($octet);
	}elseif (preg_match("/Timeticks:\s\((\d+)\)\s/", $string, $matches)) {
		$string = $matches[1];
	}

	$string = eregi_replace(REGEXP_SNMP_TRIM, "", $string);

	return trim($string);
}

?>