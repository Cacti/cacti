<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2008 The Cacti Group                                 |
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

/*
LDAP functions
*/

/* cacti_ldap_auth
  @arg $username - username of the user
  @arg $password - password of the user
  @arg $ldap_dn - LDAP DN for binding
  @arg $ldap_host - Hostname or IP of LDAP server, Default = Configured settings value
  @arg $ldap_port - Port of the LDAP server uses, Default = Configured settings value
  @arg $ldap_port_ssl - Port of the LDAP server uses for SSL, Default = Configured settings value
  @arg $ldap_version - '2' or '3', LDAP protocol version, Default = Configured settings value
  @arg $ldap_encryption - '0' None, '1' SSL, '2' TLS, Default = Configured settings value
  @arg $ldap_referrals - '0' Referrals from server are ignored, '1' Referrals from server are processed, Default = Configured setting value
  @arg $ldap_group_require - '0' Group membership is not required, '1' Group membership is required
  @arg $ldap_group_dn - LDAP Group DN
  @arg $ldap_group_attrib - Name of the LDAP Attrib that contains members
  @arg $ldap_group_require - '1' DN or '2' Username, user group member ship type

  @return - array of values
    "error_num" = error number returned
    "error_text" = error text

Error codes:

#	Text
==============================================================
0	Authentication Success
1	Authentication Failure
2	No username defined
3	Protocol error, unable to set version
4	Unable to set referrals option
5	Protocol error, unable to start TLS communications
6	Unable to create LDAP object
7	Protocol error
8	Insuffient access
9	Unable to connect to server
10	Timeout
11	General bind error
12	Group DN not found
99	PHP LDAP not enabled

*/
function cacti_ldap_auth($username,$password = "",$ldap_dn = "",$ldap_host = "",$ldap_port = "",$ldap_port_ssl = "",$ldap_version = "",$ldap_encryption = "",$ldap_referrals = "",$ldap_group_require = "",$ldap_group_dn = "",$ldap_group_attrib = "",$ldap_group_member_type = "") {

	$output = array();

	/* function check */
	if (! function_exists("ldap_connect")) {
		$output["error_num"] = 99;
		$output["error_text"] = "PHP LDAP not enabled";
		return $output;
	}

	/* validation */
	if (empty($username)) {
		$output["error_num"] = "2";
		$output["error_text"] = "No username defined";
		return $output;
	}

	/* Fix encoding of username and password */
	$username = utf8_encode($username);
	$password = utf8_encode($password);

	/* get LDAP parameters */
	if (empty($ldap_dn)) {
		$ldap_dn = read_config_option("ldap_dn");
	}
	$ldap_dn = str_replace("<username>",$username,$ldap_dn);
	if (empty($ldap_host)) {
		$ldap_host = read_config_option("ldap_server");
	}
	if (empty($ldap_port)) {
		$ldap_port = read_config_option("ldap_port");
	}
	if (empty($ldap_port_ssl)) {
		$ldap_port_ssl = read_config_option("ldap_port_ssl");
	}
	if (empty($ldap_version)) {
		$ldap_version = read_config_option("ldap_version");
	}
	if (empty($ldap_encryption)) {
		$ldap_encryption = read_config_option("ldap_encryption");
	}
	if (empty($ldap_referrals)) {
		$ldap_referrals = read_config_option("ldap_referrals");
	}
	if (empty($ldap_group_require)) {
		if (read_config_option("ldap_group_require") == "on") {
			$ldap_group_require = 1;
		}else{
			$ldap_group_require = 0;
		}
	}
	if (empty($ldap_group_dn)) {
		$ldap_group_dn = read_config_option("ldap_group_dn");
	}
	if (empty($ldap_group_attrib)) {
		$ldap_group_attrib = read_config_option("ldap_group_attrib");
	}
	if (empty($ldap_group_member_type)) {
		$ldap_group_member_type = read_config_option("ldap_group_member_type");
	}

	/* Determine connection method and create LDAP Object */
	if ($ldap_encryption == "1") {
		/* This only works with OpenLDAP, I'm pretty sure this will not work with Solaris, Tony */
		$ldap_conn = @ldap_connect("ldaps://" . $ldap_host . ":" . $ldap_port_ssl);
	}else{
		$ldap_conn = @ldap_connect($ldap_host,$ldap_port);
	}

	if ($ldap_conn) {
		/* Set protocol version */
		cacti_log("LDAP: Setting protocol version to " . $ldap_version, false, "AUTH");
		if (!@ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, $ldap_version)) {
			$output["error_num"] = "3";
			$output["error_text"] = "Protocol Error, Unable to set version";
			cacti_log("LDAP: " . $output["error_text"], false, "AUTH");
			@ldap_close($ldap_conn);
			return $output;
		}
		/* set referrals */
		if ($ldap_referrals == "0") {
			if (!@ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0)) {
				$output["error_num"] = "4";
				$output["error_text"] = "Unable to set referrals option";
				cacti_log("LDAP: " . $output["error_text"], false, "AUTH");
				@ldap_close($ldap_conn);
				return $output;
			}
		}
		/* start TLS if requested */
		if ($ldap_encryption == "2") {
			if (!@ldap_start_tls($ldap_conn)) {
				$output["error_num"] = "5";
				$output["error_text"] = "Protocol error, unable to start TLS communications";
				cacti_log("LDAP: " . $output["error_text"], false, "AUTH");
				@ldap_close($ldap_conn);
				return $output;
			}
		}
		/* Bind to the LDAP directory */
		$ldap_response = @ldap_bind($ldap_conn,$ldap_dn,$password);
		if ($ldap_response) {
			if ($ldap_group_require == 1) {
				/* Process group membership if required */
				if ($ldap_group_member_type == 1) {
					$ldap_group_response = @ldap_compare($ldap_conn,$ldap_group_dn,$ldap_group_attrib,$ldap_dn);
				} else {
					$ldap_group_response = @ldap_compare($ldap_conn,$ldap_group_dn,$ldap_group_attrib,$username);
				}
			        if ($ldap_group_response === true) {
					/* Auth ok */
					$output["error_num"] = "0";
					$output["error_text"] = "Authentication Success";
			        } else if ($ldap_group_response === false) {
					$output["error_num"] = "8";
					$output["error_text"] = "Insuffient access";
					cacti_log("LDAP: " . $output["error_text"], false, "AUTH");
					@ldap_close($ldap_conn);
					return $output;
			        } else {
					$output["error_num"] = "12";
					$output["error_text"] = "Group DN could not be found to compare";
					cacti_log("LDAP: " . $output["error_text"], false, "AUTH");
					@ldap_close($ldap_conn);
					return $output;
				}
			}else{
				/* Auth ok - No group membership required */
				$output["error_num"] = "0";
				$output["error_text"] = "Authentication Success";
			}
		}else{
			/* unable to bind */
			$ldap_error = ldap_errno($ldap_conn);
			if ($ldap_error == 0x03) {
				/* protocol error */
				$output["error_num"] = "7";
				$output["error_text"] = "Protocol error";
			}elseif ($ldap_error == 0x31) {
				/* invalid credentials */
				$output["error_num"] = "1";
				$output["error_text"] = "Authentication Failure";
			}elseif ($ldap_error == 0x32) {
				/* insuffient access */
				$output["error_num"] = "8";
				$output["error_text"] = "Insuffient access";
			}elseif ($ldap_error == 0x51) {
				/* unable to connect to server */
				$output["error_num"] = "9";
				$output["error_text"] = "Unable to connect to server";
			}elseif ($ldap_error == 0x55) {
				/* timeout */
				$output["error_num"] = "10";
				$output["error_text"] = "Connection Timeout";
			}else{
				/* general bind error */
				$output["error_num"] = "11";
				$output["error_text"] = "General bind error, LDAP result: " . ldap_error($ldap_conn);
			}
		}
	}else{
		/* Error intializing LDAP */
		$output["error_num"] = "6";
		$output["error_text"] = "Unable to create LDAP object";
	}

	/* Close LDAP connection */
	@ldap_close($ldap_conn);

	if ($output["error_num"] > 0) {
		cacti_log("LDAP: " . $output["error_text"], false, "AUTH");
	}

	return $output;


}

/* cacti_ldap_search_dn
  @arg $username - username to search for in the LDAP directory
  @arg $ldap_dn - configured LDAP DN for binding, "<username>" will be replaced with $username
  @arg $ldap_host - Hostname or IP of LDAP server, Default = Configured settings value
  @arg $ldap_port - Port of the LDAP server uses, Default = Configured settings value
  @arg $ldap_port_ssl - Port of the LDAP server uses for SSL, Default = Configured settings value
  @arg $ldap_version - '2' or '3', LDAP protocol version, Default = Configured settings value
  @arg $ldap_encryption - '0' None, '1' SSL, '2' TLS, Default = Configured settings value
  @arg $ldap_referrals - '0' Referrals from server are ignored, '1' Referrals from server are processed, Default = Configured setting value
  @arg $ldap_mode - '0' No Searching, '1' Anonymous Searching, '2' Specfic Searching, Default = Configured settings value
  @arg $ldap_search_base - Search base DN, Default = Configured settings value
  @arg $ldap_search_filter - Filter to find the user, Default = Configured settings value
  @arg $ldap_specific_dn - DN for binding to perform user search, Default = Configured settings value
  @arg $ldap_specific_password - Password for binding to perform user search, Default - Configured settings value

  @return - array of values
    "error_num" = error number returned
    "error_text" = error text
    "dn" = found dn of user

Error codes:

#	Text
==============================================================
0	User found
1	No username defined
2	Unable to create LDAP connection object
3	Unable to find users DN
4	Protocol error, unable to set version
5	Protocol error, unable to start TLS communications
6	Protocol error
7	Invalid credential
8	Insuffient access
9	Unable to connect to server
10	Timeout
11	General bind error
12	Unable to set referrals option
13	More than one matching user found
99	PHP LDAP not enabled

*/
function cacti_ldap_search_dn($username,$ldap_dn = "",$ldap_host = "",$ldap_port = "",$ldap_port_ssl = "",$ldap_version = "",$ldap_encryption = "",$ldap_referrals = "", $ldap_mode = "",$ldap_search_base = "", $ldap_search_filter = "",$ldap_specific_dn = "",$ldap_specific_password = "") {

	$output = array();

	/* function check */
	if (! function_exists("ldap_connect")) {
		$output["error_num"] = 99;
		$output["error_text"] = "PHP LDAP not enabled";
		return $output;
	}

	/* validation */
	if (empty($username)) {
		$output["dn"] = "";
		$output["error_num"] = "1";
		$output["error_text"] = _("No username defined");
		cacti_log("LDAP_SEARCH: No username defined", false, "AUTH");
		return $output;
	}

	/* Encode username */
	$username = utf8_encode($username);

	/* strip bad chars from username - prevent altering filter from username */
	$username = str_replace("&", "", $username);
	$username = str_replace("|", "", $username);
	$username = str_replace("(", "", $username);
	$username = str_replace(")", "", $username);
	$username = str_replace("*", "", $username);
	$username = str_replace(">", "", $username);
	$username = str_replace("<", "", $username);
	$username = str_replace("!", "", $username);
	$username = str_replace("=", "", $username);

	/* get LDAP parameters */
	if (empty($ldap_dn)) {
		$ldap_dn = read_config_option("ldap_dn");
	}
	$ldap_dn = str_replace("<username>",$username,$ldap_dn);
	if (empty($ldap_host)) {
		$ldap_host = read_config_option("ldap_server");
	}
	if (empty($ldap_port)) {
		$ldap_port = read_config_option("ldap_port");
	}
	if (empty($ldap_port_ssl)) {
		$ldap_port_ssl = read_config_option("ldap_port_ssl");
	}
	if (empty($ldap_version)) {
		$ldap_version = read_config_option("ldap_version");
	}
	if (empty($ldap_encryption)) {
		$ldap_encryption = read_config_option("ldap_encryption");
	}
	if (empty($ldap_referrals)) {
		$ldap_referrals = read_config_option("ldap_referrals");
	}
	if (empty($ldap_mode)) {
		$ldap_mode = read_config_option("ldap_mode");
	}

	if ($ldap_encryption == "1") {
		$ldap_host = "ldaps://" . $ldap_host;
		$ldap_port = $ldap_port_ssl;
	}else{
		$ldap_host = "ldap://" . $ldap_host;
	}

	if ($ldap_mode == "0") {
		/* Just bind mode, make dn and return */
		$output["dn"] = $ldap_dn;
		$output["error_num"] = "0";
		$output["error_text"] = "User found";
		return $output;
	}elseif ($ldap_mode == "2") {
		/* specific */
		if (empty($ldap_specific_dn)) {
			$ldap_specific_dn = read_config_option("ldap_specific_dn");
		}
		if (empty($ldap_specific_password)) {
			$ldap_specific_password = read_config_option("ldap_specific_password");
		}
	}elseif ($ldap_mode == "1"){
		/* assume anonymous */
		$ldap_specific_dn = "";
		$ldap_specific_password = "";
	}

	if (empty($ldap_search_base)) {
		$ldap_search_base = read_config_option("ldap_search_base");
	}
	if (empty($ldap_search_filter)) {
		$ldap_search_filter = read_config_option("ldap_search_filter");
	}
	$ldap_search_filter = str_replace("<username>",$username,$ldap_search_filter);

	/* Fix encoding on ldap specific search DN and password */
	$ldap_specific_password = utf8_encode($ldap_specific_password);
	$ldap_specific_dn = utf8_encode($ldap_specific_dn);

	/* Searching mode */
        /* Setup connection to LDAP server */
        $ldap_conn = @ldap_connect($ldap_host,$ldap_port);

	if ($ldap_conn) {
		/* Set protocol version */
		if (!@ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, $ldap_version)) {
			/* protocol error */
			$output["dn"] = "";
			$output["error_num"] = "4";
			$output["error_text"] = "Protocol error, unable to set version";
			cacti_log("LDAP_SEARCH: " . $output["error_text"], false, "AUTH");
			@ldap_close($ldap_conn);
			return $output;
		}
		/* set referrals */
		if ($ldap_referrals == "0") {
			if (!@ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0)) {
				/* referrals set error */
				$output["dn"] = "";
				$output["error_num"] = "13";
				$output["error_text"] = "Unable to set referrals option";
				cacti_log("LDAP_SEARCH: " . $output["error_text"], false, "AUTH");
				@ldap_close($ldap_conn);
				return $output;
			}
		}
		/* start TLS if requested */
		if ($ldap_encryption == "2") {
			if (!@ldap_start_tls($ldap_conn)) {
				/* TLS startup error */
				$output["dn"] = "";
				$output["error_num"] = "5";
				$output["error_text"] = "Protocol error, unable to start TLS communications";
				cacti_log("LDAP_SEARCH: " . $output["error_text"], false, "AUTH");
				@ldap_close($ldap_conn);
				return $output;
			}
		}

		/* bind to the directory */
		if (@ldap_bind($ldap_conn,$ldap_specific_dn,$ldap_specific_password)) {
			/* Search */

			$ldap_results = ldap_search($ldap_conn, $ldap_search_base, $ldap_search_filter, array("dn"));
			if ($ldap_results) {
				$ldap_entries =  ldap_get_entries($ldap_conn, $ldap_results);

				if ($ldap_entries["count"] == "1") {
					/* single response return user dn */
					$output["dn"] = $ldap_entries["0"]["dn"];
					$output["error_num"] = "0";
					$output["error_text"] = "User found";
					cacti_log("LDAP_SEARCH: User found, DN '%s'" . $output["dn"], false, "AUTH");
				}elseif ($ldap_entries["count"] > 1) {
					/* more than 1 result */
					$output["dn"] = "";
					$output["error_num"] = "13";
					$output["error_text"] = "More than one matching user found";
				}else{
					/* no search results */
					$output["dn"] = "";
					$output["error_num"] = "3";
					$output["error_text"] = "Unable to find users DN";
				}
			}else{
				/* no search results, user not found*/
				$output["dn"] = "";
				$output["error_num"] = "3";
				$output["error_text"] = "Unable to find users DN";
			}
		}else{
			/* unable to bind */
			$ldap_error = ldap_errno($ldap_conn);
			if ($ldap_error == 0x03) {
				/* protocol error */
				$output["dn"] = "";
				$output["error_num"] = "6";
				$output["error_text"] = "Protocol error";
			}elseif ($ldap_error == 0x31) {
				/* invalid credentials */
				$output["dn"] = "";
				$output["error_num"] = "7";
				$output["error_text"] = "Invalid credentials";
			}elseif ($ldap_error == 0x32) {
				/* insuffient access */
				$output["dn"] = "";
				$output["error_num"] = "8";
				$output["error_text"] = "Insuffient access";
			}elseif ($ldap_error == 0x51) {
				/* unable to connect to server */
				$output["dn"] = "";
				$output["error_num"] = "9";
				$output["error_text"] = "Unable to connect to server";
			}elseif ($ldap_error == 0x55) {
				/* timeout */
				$output["dn"] = "";
				$output["error_num"] = "10";
				$output["error_text"] = "Connection Timeout";
			}else{
				/* general bind error */
				$output["dn"] = "";
				$output["error_num"] = "11";
				$output["error_text"] = "General bind error, LDAP result: " . ldap_error($ldap_conn);
			}
		}
	}else{
		/* unable to setup connection */
		$output["dn"] = "";
		$output["error_num"] = "2";
		$output["error_text"] = "Unable to create LDAP connection object";
	}

	@ldap_close($ldap_conn);

	if ($output["error_num"] > 0) {
		cacti_log("LDAP_SEARCH: " . $output["error_text"], false, "AUTH");
	}

	return $output;

}

?>

