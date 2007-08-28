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

define("REGEXP_SNMP_TRIM", "(hex|counter(32|64)|gauge|gauge(32|64)|float|ipaddress|string|integer):");

define("SNMP_METHOD_PHP", 1);
define("SNMP_METHOD_BINARY", 2);

/* we must use an apostrophe to escape community names under Unix in case the user uses
characters that the shell might interpret. the ucd-snmp binaries on Windows flip out when
you do this, but are perfectly happy with a quotation mark. */
if ($config["cacti_server_os"] == "unix") {
	define("SNMP_ESCAPE_CHARACTER", "'");
}else{
	define("SNMP_ESCAPE_CHARACTER", "\"");
}

function cacti_snmp_get($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $port = 161, $timeout = 500, $retries = 0, $environ = SNMP_POLLER) {
	global $config;

	/* determine default retries */
	if (($retries == 0) || (!is_numeric($retries))) {
		$retries = read_config_option("snmp_retries");
		if ($retries == "") $retries = 3;
	}

	/* do not attempt to poll invalid combinations */
	if (($version == 0) || (($community == "") && ($version != 3))) {
		return "U";
	}

	if (snmp_get_method($version) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		if ($version == "1") {
			$snmp_value = @snmpget("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}elseif ($version == "2") {
			$snmp_value = @snmp2_get("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}else{
			if ($auth_proto == "[None]") {
				$proto = "authNoPriv";
			}else{
				$proto = "authPriv";
			}

			$snmp_value = @snmp3_get("$hostname:$port", $username, $proto, $auth_proto, "$password", "$priv_pass", $priv_proto, "$oid", ($timeout * 1000), $retries);
		}
	}else {
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == "1") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER : "-c " . SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER; /* v1/v2 - community string */
		}elseif ($version == "2") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER : "-c " . SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER; /* v1/v2 - community string */
			$version = "2c"; /* ucd/net snmp prefers this over '2' */
		}elseif ($version == "3") {
			if ($auth_proto == "[None]") {
				$proto = "authNoPriv";
			}else{
				$proto = "authPriv";
			}

			if (strlen($priv_pass)) {
				$priv_pass = "-X \"$priv_pass\" -x $priv_proto";
			}else{
				$priv_pass = "";
			}

			$snmp_auth = "-u $username -l $proto -a $auth_proto -A $password $priv_pass"; /* v3 - username/password */
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) { return; }

		if (read_config_option("snmp_version") == "ucd-snmp") {
			exec(read_config_option("path_snmpget") . " -O vt -v$version -t $timeout -r $retries $hostname:$port $snmp_auth $oid", $snmp_value);
		}else {
			exec(read_config_option("path_snmpget") . " -O fntev $snmp_auth -v $version -t $timeout -r $retries $hostname:$port $oid", $snmp_value);
		}
	}

	if (isset($snmp_value)) {
		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(" ", $snmp_value);
		}
	}

	/* strip out non-snmp data */
	$snmp_value = format_snmp_string($snmp_value);

	return $snmp_value;
}

function cacti_snmp_getnext($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $port = 161, $timeout = 500, $retries = 0, $environ = SNMP_POLLER) {
	global $config;

	/* determine default retries */
	if (($retries == 0) || (!is_numeric($retries))) {
		$retries = read_config_option("snmp_retries");
		if ($retries == "") $retries = 3;
	}

	/* do not attempt to poll invalid combinations */
	if (($version == 0) || (($community == "") && ($version != 3))) {
		return "U";
	}

	if (snmp_get_method($version) == SNMP_METHOD_PHP) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		if ($version == "1") {
			$snmp_value = @snmpgetnext("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}elseif ($version == "2") {
			$snmp_value = @snmp2_getnext("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}else{
			if ($auth_proto == "[None]") {
				$proto = "authNoPriv";
			}else{
				$proto = "authPriv";
			}

			$snmp_value = @snmp3_getnext("$hostname:$port", $username, $proto, $auth_proto, "$password", "$priv_pass", $priv_proto, "$oid", ($timeout * 1000), $retries);
		}
	}else {
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == "1") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER : "-c " . SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER; /* v1/v2 - community string */
		}elseif ($version == "2") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER : "-c " . SNMP_ESCAPE_CHARACTER . $community . SNMP_ESCAPE_CHARACTER; /* v1/v2 - community string */
			$version = "2c"; /* ucd/net snmp prefers this over '2' */
		}elseif ($version == "3") {
			if ($auth_proto == "[None]") {
				$proto = "authNoPriv";
			}else{
				$proto = "authPriv";
			}

			if (strlen($priv_pass)) {
				$priv_pass = "-X \"$priv_pass\" -x $priv_proto";
			}else{
				$priv_pass = "";
			}

			$snmp_auth = "-u $username -l $proto -a $auth_proto -A $password $priv_pass"; /* v3 - username/password */
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) { return; }

		if (read_config_option("snmp_version") == "ucd-snmp") {
			exec(read_config_option("path_snmpgetnext") . " -O vt -v$version -t $timeout -r $retries $hostname:$port $snmp_auth $oid", $snmp_value);
		}else {
			exec(read_config_option("path_snmpgetnext") . " -O fntev $snmp_auth -v $version -t $timeout -r $retries $hostname:$port $oid", $snmp_value);
		}
	}

	if (isset($snmp_value)) {
		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(" ", $snmp_value);
		}
	}

	/* strip out non-snmp data */
	$snmp_value = format_snmp_string($snmp_value);

	return $snmp_value;
}

function cacti_snmp_walk($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $port = 161, $timeout = 500, $retries = 0, $environ = SNMP_POLLER) {
	global $config;

	$snmp_array = array();
	$temp_array = array();

	/* determine default retries */
	if (($retries == 0) || (!is_numeric($retries))) {
		$retries = read_config_option("snmp_retries");
		if ($retries == "") $retries = 3;
	}

	$path_snmpbulkwalk = read_config_option("path_snmpbulkwalk");

	if ((snmp_get_method($version) == SNMP_METHOD_PHP) &&
		(($version == 1) ||
		(version_compare(phpversion(), "5.1") >= 0) ||
		(!file_exists($path_snmpbulkwalk)))) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */

		/* force php to return numeric oid's */
		if (function_exists("snmp_set_oid_numeric_print")) {
			snmp_set_oid_numeric_print(TRUE);
		}

		snmp_set_quick_print(0);

		if ($version == "1") {
			$temp_array = @snmprealwalk("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}elseif ($version == "2") {
			$temp_array = @snmp2_real_walk("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}else{
			if ($auth_proto == "[None]") {
				$proto = "authNoPriv";
			}else{
				$proto = "authPriv";
			}

			$temp_array = @snmp3_real_walk("$hostname:$port", $username, $proto, $auth_proto, "$password", "$priv_pass", $priv_proto, "$oid", ($timeout * 1000), $retries);
		}

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
			if ($auth_proto == "[None]") {
				$proto = "authNoPriv";
			}else{
				$proto = "authPriv";
			}

			if (strlen($priv_pass)) {
				$priv_pass = "-X \"$priv_pass\" -x $priv_proto";
			}else{
				$priv_pass = "";
			}

			$snmp_auth = "-u $username -l $proto -a $auth_proto -A $password $priv_pass"; /* v3 - username/password */
		}

		if (read_config_option("snmp_version") == "ucd-snmp") {
			$temp_array = exec_into_array(read_config_option("path_snmpwalk") . " -v$version -t $timeout -r $retries $hostname:$port $snmp_auth $oid");
		}else {
			if (file_exists($path_snmpbulkwalk) && ($version > 1)) {
				$temp_array = exec_into_array($path_snmpbulkwalk . " -O Qn $snmp_auth -v $version -t $timeout -r $retries -Cr50 $hostname:$port $oid");
			}else{
				$temp_array = exec_into_array(read_config_option("path_snmpwalk") . " -O Qn $snmp_auth -v $version -t $timeout -r $retries $hostname:$port $oid");
			}
		}

		if ((sizeof($temp_array) == 0) ||
			(substr_count($temp_array[0], "No Such Object")) ||
			(substr_count($temp_array[0], "No more variables")) ||
			(substr_count($temp_array[0], "End of MIB")) ||
			(substr_count($temp_array[0], "Wrong Type"))) {
			return array();
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
	$string = str_replace("\\", "", $string);

	/* Account for invalid type messages */
	if (substr_count($string, "Wrong Type")) {
		$string = strrev($string);
		if ($position = strpos($string, ":")) {
			$string = trim(strrev(substr($string, 0, $position)));
		}else{
			$string = trim(strrev($string));
		}
	}

	/* Remove invalid chars */
	$k = strlen($string);
	for ($i=0; $i < $k; $i++) {
		if ((ord($string[$i]) <= 31) || (ord($string[$i]) >= 127)) {
			$string[$i] = " ";
		}
	}
	$string = trim($string);

	if ((substr_count($string, "Hex-STRING:")) ||
		(substr_count($string, "Hex:"))) {
		/* strip of the 'Hex-STRING:' */
		$string = eregi_replace("Hex-STRING: ?", "", $string);
		$string = eregi_replace("Hex: ?", "", $string);

		$string_array = split(" ", $string);

		/* loop through each string character and make ascii */
		$string = "";
		$hexval = "";
		$ishex  = false;
		for ($i=0;($i<sizeof($string_array));$i++) {
			if (strlen($string_array[$i])) {
				$string .= chr(hexdec($string_array[$i]));

				$hexval .= str_pad($string_array[$i], 2, "0", STR_PAD_LEFT);

				if (($i+1) < count($string_array)) {
					$hexval .= ":";
				}

				if ((hexdec($string_array[$i]) <= 31) || (hexdec($string_array[$i]) >= 127)) {
					if ((($i+1) == sizeof($string_array)) && ($string_array[$i] == 0)) {
						/* do nothing */
					}else{
						$ishex = true;
					}
				}
			}
		}

		if ($ishex) $string = $hexval;
	}elseif (preg_match("/(hex:\?)?([a-fA-F0-9]{1,2}(:|\s)){5}/", $string)) {
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

function snmp_get_method($version = 1) {
	if ((function_exists("snmpget")) && ($version == 1)) {
		return SNMP_METHOD_PHP;
	}else if ((function_exists("snmp2_get")) && ($version == 2)) {
		return SNMP_METHOD_PHP;
	}else if ((function_exists("snmp3_get")) && ($version == 3)) {
		return SNMP_METHOD_PHP;
	}else if ((($version == 2) || ($version == 3)) && (file_exists(read_config_option("path_snmpget")))) {
		return SNMP_METHOD_BINARY;
	}else if (function_exists("snmpget")) {
		/* last resort (hopefully it isn't a 64-bit result) */
		return SNMP_METHOD_PHP;
	}else if (file_exists(read_config_option("path_snmpget"))) {
		return SNMP_METHOD_BINARY;
	}else{
		/* looks like snmp is broken */
		return SNMP_METHOD_BINARY;
	}
}

?>