<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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

define("REGEXP_SNMP_TRIM", "/(hex|counter(32|64)|gauge|gauge(32|64)|float|ipaddress|string|integer):/i");

define("SNMP_METHOD_PHP", 1);
define("SNMP_METHOD_BINARY", 2);

function cacti_snmp_get($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $context, $port = 161, $timeout = 500, $retries = 0, $environ = SNMP_POLLER) {
	global $config;

	/* determine default retries */
	if (($retries == 0) || (!is_numeric($retries))) {
		$retries = read_config_option("snmp_retries");
		if ($retries == "") $retries = 3;
	}

	/* do not attempt to poll invalid combinations */
	if (($version == 0) || (!is_numeric($version)) ||
		(!is_numeric($port)) ||
		(!is_numeric($retries)) ||
		(!is_numeric($timeout)) ||
		(($community == "") && ($version != 3))
		) {
		return "U";
	}

	if ((snmp_get_method($version) == SNMP_METHOD_PHP) &&
		(!strlen($context) || ($version != 3))) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		if ($version == "1") {
			$snmp_value = @snmpget("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}elseif ($version == "2") {
			$snmp_value = @snmp2_get("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}else{
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			$snmp_value = @snmp3_get("$hostname:$port", "$username", $proto, $auth_proto, "$password", $priv_proto, "$priv_pass", "$oid", ($timeout * 1000), $retries);
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP Get Timeout for Host:'$hostname', and OID:'$oid'", false);
		}
	}else {
		$snmp_value = '';
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == "1") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? escapeshellarg($community): "-c " . escapeshellarg($community); /* v1/v2 - community string */
		}elseif ($version == "2") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? escapeshellarg($community) : "-c " . escapeshellarg($community); /* v1/v2 - community string */
			$version = "2c"; /* ucd/net snmp prefers this over '2' */
		}elseif ($version == "3") {
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			if (strlen($priv_pass)) {
				$priv_pass = "-X " . escapeshellarg($priv_pass) . " -x " . escapeshellarg($priv_proto);
			}else{
				$priv_pass = "";
			}

			if (strlen($context)) {
				$context = "-n " . escapeshellarg($context);
			}else{
				$context = "";
			}

			$snmp_auth = trim("-u " . escapeshellarg($username) .
				" -l " . escapeshellarg($proto) .
				" -a " . escapeshellarg($auth_proto) .
				" -A " . escapeshellarg($password) .
				" "    . $priv_pass .
				" "    . $context); /* v3 - username/password */
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) { return; }

		if (read_config_option("snmp_version") == "ucd-snmp") {
			/* escape the command to be executed and vulnerable parameters
			 * numeric parameters are not subject to command injection
			 * snmp_auth is treated seperately, see above */
			exec(cacti_escapeshellcmd(read_config_option("path_snmpget")) . " -O vt -v$version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port $snmp_auth " . cacti_escapeshellarg($oid), $snmp_value);
		}else {
			exec(cacti_escapeshellcmd(read_config_option("path_snmpget")) . " -O fntev " . $snmp_auth . " -v $version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port " . cacti_escapeshellarg($oid), $snmp_value);
		}

		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(" ", $snmp_value);
		}
	}

	/* fix for multi-line snmp output */
	if (isset($snmp_value)) {
		if (is_array($snmp_value)) {
			$snmp_value = implode(" ", $snmp_value);
		}
	}

	if (substr_count($snmp_value, "Timeout:")) {
		cacti_log("WARNING: SNMP Get Timeout for Host:'$hostname', and OID:'$oid'", false);
	}

	/* strip out non-snmp data */
	$snmp_value = format_snmp_string($snmp_value, false);

	return $snmp_value;
}

function cacti_snmp_getnext($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $context, $port = 161, $timeout = 500, $retries = 0, $environ = SNMP_POLLER) {
	global $config;

	/* determine default retries */
	if (($retries == 0) || (!is_numeric($retries))) {
		$retries = read_config_option("snmp_retries");
		if ($retries == "") $retries = 3;
	}

	/* do not attempt to poll invalid combinations */
	if (($version == 0) || (!is_numeric($version)) ||
		(!is_numeric($port)) ||
		(!is_numeric($retries)) ||
		(!is_numeric($timeout)) ||
		(($community == "") && ($version != 3))
		) {
		return "U";
	}

	if ((snmp_get_method($version) == SNMP_METHOD_PHP) &&
		(!strlen($context) || ($version != 3))) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */
		snmp_set_quick_print(0);

		if ($version == "1") {
			$snmp_value = @snmpgetnext("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}elseif ($version == "2") {
			$snmp_value = @snmp2_getnext("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}else{
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			$snmp_value = @snmp3_getnext("$hostname:$port", "$username", $proto, $auth_proto, "$password", $priv_proto, "$priv_pass", "$oid", ($timeout * 1000), $retries);
		}

		if ($snmp_value === false) {
			cacti_log("WARNING: SNMP GetNext Timeout for Host:'$hostname', and OID:'$oid'", false);
		}
	}else {
		$snmp_value = '';
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == "1") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? escapeshellarg($community): "-c " . escapeshellarg($community); /* v1/v2 - community string */
		}elseif ($version == "2") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? escapeshellarg($community): "-c " . escapeshellarg($community); /* v1/v2 - community string */
			$version = "2c"; /* ucd/net snmp prefers this over '2' */
		}elseif ($version == "3") {
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			if (strlen($priv_pass)) {
				$priv_pass = "-X " . escapeshellarg($priv_pass) . " -x " . escapeshellarg($priv_proto);
			}else{
				$priv_pass = "";
			}

			if (strlen($context)) {
				$context = "-n " . escapeshellarg($context);
			}else{
				$context = "";
			}

			$snmp_auth = trim("-u " . escapeshellarg($username) .
				" -l " . escapeshellarg($proto) .
				" -a " . escapeshellarg($auth_proto) .
				" -A " . escapeshellarg($password) .
				" "    . $priv_pass .
				" "    . $context); /* v3 - username/password */
		}

		/* no valid snmp version has been set, get out */
		if (empty($snmp_auth)) { return; }

		if (read_config_option("snmp_version") == "ucd-snmp") {
			/* escape the command to be executed and vulnerable parameters
			 * numeric parameters are not subject to command injection
			 * snmp_auth is treated seperately, see above */
			exec(cacti_escapeshellcmd(read_config_option("path_snmpgetnext")) . " -O vt -v$version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port $snmp_auth " . cacti_escapeshellarg($oid), $snmp_value);
		}else {
			exec(cacti_escapeshellcmd(read_config_option("path_snmpgetnext")) . " -O fntev $snmp_auth -v $version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port " . cacti_escapeshellarg($oid), $snmp_value);
		}
	}

	if (isset($snmp_value)) {
		/* fix for multi-line snmp output */
		if (is_array($snmp_value)) {
			$snmp_value = implode(" ", $snmp_value);
		}
	}

	if (substr_count($snmp_value, "Timeout:")) {
		cacti_log("WARNING: SNMP GetNext Timeout for Host:'$hostname', and OID:'$oid'", false);
	}

	/* strip out non-snmp data */
	$snmp_value = format_snmp_string($snmp_value, false);

	return $snmp_value;
}

function cacti_snmp_walk($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $context, $port = 161, $timeout = 500, $retries = 0, $max_oids = 10, $environ = SNMP_POLLER) {
	global $config, $banned_snmp_strings;

	$snmp_oid_included = true;
	$snmp_auth	       = '';
	$snmp_array        = array();
	$temp_array        = array();

	/* determine default retries */
	if (($retries == 0) || (!is_numeric($retries))) {
		$retries = read_config_option("snmp_retries");
		if ($retries == "") $retries = 3;
	}

	/* determine default max_oids */
	if (($max_oids == 0) || (!is_numeric($max_oids))) {
		$max_oids = read_config_option("max_get_size");

		if ($max_oids == "") $max_oids = 10;
	}

	/* do not attempt to poll invalid combinations */
	if (($version == 0) || (!is_numeric($version)) ||
		(!is_numeric($max_oids)) ||
		(!is_numeric($port)) ||
		(!is_numeric($retries)) ||
		(!is_numeric($timeout)) ||
		(($community == "") && ($version != 3))
		) {
		return array();
	}

	$path_snmpbulkwalk = read_config_option("path_snmpbulkwalk");

	if ((snmp_get_method($version) == SNMP_METHOD_PHP) &&
		(!strlen($context) || ($version != 3)) &&
		(($version == 1) ||
		(version_compare(phpversion(), "5.1") >= 0) ||
		(!file_exists($path_snmpbulkwalk)))) {
		/* make sure snmp* is verbose so we can see what types of data
		we are getting back */

		/* force php to return numeric oid's */
		if (function_exists("snmp_set_oid_numeric_print")) {
			snmp_set_oid_numeric_print(TRUE);
		}

		if (function_exists("snmprealwalk")) {
			$snmp_oid_included = false;
		}

		snmp_set_quick_print(0);

		if ($version == "1") {
			$temp_array = @snmprealwalk("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}elseif ($version == "2") {
			$temp_array = @snmp2_real_walk("$hostname:$port", "$community", "$oid", ($timeout * 1000), $retries);
		}else{
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			$temp_array = @snmp3_real_walk("$hostname:$port", "$username", $proto, $auth_proto, "$password", $priv_proto, "$priv_pass", "$oid", ($timeout * 1000), $retries);
		}

		if ($temp_array === false) {
			cacti_log("WARNING: SNMP Walk Timeout for Host:'$hostname', and OID:'$oid'", false);
		}

		/* check for bad entries */
		if (is_array($temp_array) && sizeof($temp_array)) {
		foreach($temp_array as $key => $value) {
			foreach($banned_snmp_strings as $item) {
				if(strstr($value, $item) != "") {
					unset($temp_array[$key]);
					continue 2;
				}
			}
		}
		}

		$o = 0;
		for (@reset($temp_array); $i = @key($temp_array); next($temp_array)) {
			if ($temp_array[$i] != "NULL") {
				$snmp_array[$o]["oid"] = preg_replace("/^\./", "", $i);
				$snmp_array[$o]["value"] = format_snmp_string($temp_array[$i], $snmp_oid_included);
			}
			$o++;
		}
	}else{
		/* ucd/net snmp want the timeout in seconds */
		$timeout = ceil($timeout / 1000);

		if ($version == "1") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? escapeshellarg($community): "-c " . escapeshellarg($community); /* v1/v2 - community string */
		}elseif ($version == "2") {
			$snmp_auth = (read_config_option("snmp_version") == "ucd-snmp") ? escapeshellarg($community): "-c " . escapeshellarg($community); /* v1/v2 - community string */
			$version = "2c"; /* ucd/net snmp prefers this over '2' */
		}elseif ($version == "3") {
			if ($priv_proto == "[None]" || $priv_pass == '') {
				$proto = "authNoPriv";
				$priv_proto = "";
			}else{
				$proto = "authPriv";
			}

			if (strlen($priv_pass)) {
				$priv_pass = "-X " . escapeshellarg($priv_pass) . " -x " . escapeshellarg($priv_proto);
			}else{
				$priv_pass = "";
			}

			if (strlen($context)) {
				$context = "-n " . escapeshellarg($context);
			}else{
				$context = "";
			}

			$snmp_auth = trim("-u " . escapeshellarg($username) .
				" -l " . escapeshellarg($proto) .
				" -a " . escapeshellarg($auth_proto) .
				" -A " . escapeshellarg($password) .
				" "    . $priv_pass .
				" "    . $context); /* v3 - username/password */
		}

		if (read_config_option("snmp_version") == "ucd-snmp") {
			/* escape the command to be executed and vulnerable parameters
			 * numeric parameters are not subject to command injection
			 * snmp_auth is treated seperately, see above */
			$temp_array = exec_into_array(cacti_escapeshellcmd(read_config_option("path_snmpwalk")) . " -v$version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port $snmp_auth " . cacti_escapeshellarg($oid));
		}else {
			if (file_exists($path_snmpbulkwalk) && ($version > 1) && ($max_oids > 1)) {
				$temp_array = exec_into_array(cacti_escapeshellcmd($path_snmpbulkwalk) . " -O Qn $snmp_auth -v $version -t $timeout -r $retries -Cr$max_oids " . cacti_escapeshellarg($hostname) . ":$port " . cacti_escapeshellarg($oid));
			}else{
				$temp_array = exec_into_array(cacti_escapeshellcmd(read_config_option("path_snmpwalk")) . " -O Qn $snmp_auth -v $version -t $timeout -r $retries " . cacti_escapeshellarg($hostname) . ":$port " . cacti_escapeshellarg($oid));
			}
		}

		if (substr_count(implode(" ", $temp_array), "Timeout:")) {
			cacti_log("WARNING: SNMP Walk Timeout for Host:'$hostname', and OID:'$oid'", false);
		}

		/* check for bad entries */
		if (is_array($temp_array) && sizeof($temp_array)) {
		foreach($temp_array as $key => $value) {
			foreach($banned_snmp_strings as $item) {
				if(strstr($value, $item) != "") {
					unset($temp_array[$key]);
					continue 2;
				}
			}
		}
		}

		for ($i=0; $i < count($temp_array); $i++) {
			if ($temp_array[$i] != "NULL") {
				$snmp_array[$i]["oid"]   = trim(preg_replace("/(.*) =.*/", "\\1", $temp_array[$i]));
				$snmp_array[$i]["value"] = format_snmp_string($temp_array[$i], true);
			}
		}
	}

	return $snmp_array;
}

function format_snmp_string($string, $snmp_oid_included) {
	global $banned_snmp_strings;

	$string = preg_replace(REGEXP_SNMP_TRIM, "", trim($string));

	if (substr($string, 0, 7) == "No Such") {
		return "";
	}

	if ($snmp_oid_included) {
		/* strip off all leading junk (the oid and stuff) */
		$string_array = explode("=", $string);
		if (sizeof($string_array) == 1) {
			/* trim excess first */
			$string = trim($string);
		}else if ((substr($string, 0, 1) == ".") || (strpos($string, "::") !== false)) {
			/* drop the OID from the array */
			array_shift($string_array);
			$string = trim(implode("=", $string_array));
		}else {
			$string = trim(implode("=", $string_array));
		}
	}

	/* return the easiest value */
	if ($string == "") {
		return $string;
	}

	/* now check for the second most obvious */
	if (is_numeric($string)) {
		return trim($string);
	}

	/* remove ALL quotes, and other special delimiters */
	$string = str_replace("\"", "", $string);
	$string = str_replace("'", "", $string);
	$string = str_replace(">", "", $string);
	$string = str_replace("<", "", $string);
	$string = str_replace("\\", "", $string);
	$string = str_replace("\n", " ", $string);
	$string = str_replace("\r", " ", $string);

	/* account for invalid MIB files */
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
		(substr_count($string, "Hex-")) ||
		(substr_count($string, "Hex:"))) {
		/* strip of the 'Hex-STRING:' */
		$string = preg_replace("/Hex-STRING: ?/i", "", $string);
		$string = preg_replace("/Hex: ?/i", "", $string);
		$string = preg_replace("/Hex- ?/i", "", $string);

		$string_array = explode(" ", $string);

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
	}elseif (preg_match("/(hex:\?)?([a-fA-F0-9]{1,2}(:|\s)){5}/i", $string)) {
		$octet = "";

		/* strip off the 'hex:' */
		$string = preg_replace("/hex: ?/i", "", $string);

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

	foreach($banned_snmp_strings as $item) {
		if(strstr($string, $item) != "") {
			$string = "";
			break;
		}
	}

	return $string;
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
