<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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

/* initilize php session */
session_start();

/* we don't want these pages cached */
header ("Cache-Control: no-cache, must-revalidate");
header ("Pragma: no-cache");

include("config.php");

/* check to see if this is a new installation */
include_once ("include/version_functions.php");
include_once ("functions.php");

if (GetCurrentVersion() != $config["cacti_version"]) {
	header ("Location: install.php");
	exit;
}

if (read_config_option("global_auth") == "on") {
	/* handle change password dialog */
	if (isset($_SESSION['sess_change_password'])) {
		header ("Location: auth_changepassword.php?ref=" . $_SERVER["HTTP_REFERER"]);
		exit;
	}
	
	/* don't even bother with the guest code if we're already logged in */
	if ((isset($guest_account)) && (empty($_SESSION["sess_user_id"]))) {
		$guest_user_id = db_fetch_cell("select id from user where username='" . read_config_option("guest_user") . "'");
		
		/* cannot find guest user */
		if (empty($guest_user_id) == 0) {
			print "<strong><font size='+1' color='FF0000'>CANNOT FIND GUEST USER: " . read_config_option("guest_user") . "</font></strong>";
		}else{
			$_SESSION["sess_user_id"] = $guest_user_id;
		}
	}
	
	if (empty($_SESSION["sess_user_id"])) {
		include ("auth_login.php");
		exit;
	}else{
		$realm_id = db_fetch_cell("select realm_id from user_realm_filename where filename='" . basename($_SERVER["SCRIPT_NAME"]) . "'");
		
		if (!db_fetch_assoc("select
			user_auth_realm.realm_id
			from
			user_auth_realm
			where user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "'
			and user_auth_realm.realm_id='$realm_id'")) {
			
			include ("top_header.php");
			include ("auth_noauth.php");
			include ("bottom_footer.php");
			exit;
		}
	}
}

?>
