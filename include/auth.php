<?/* 
+-------------------------------------------------------------------------+
| Copyright (C) 2002 Ian Berry                                            |
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
| cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
+-------------------------------------------------------------------------+
| This code is currently maintained and debugged by Ian Berry, any        |
| questions or comments regarding this code should be directed to:        |
| - iberry@raxnet.net                                                     |
+-------------------------------------------------------------------------+
| - raXnet - http://www.raxnet.net/                                       |
+-------------------------------------------------------------------------+
*/?>
<?
/* initilize php session */
session_start();

/* we don't want these pages cached */
header ("Cache-Control: no-cache, must-revalidate");
header ("Pragma: no-cache");

include("config.php");
include("functions.php");

/* check to see if this is a new installation */
include_once ("include/version_functions.php");

if (GetCurrentVersion() != $config[cacti_version]) {
	header ("Location: install.php");
	exit;
}

if (read_config_option("global_auth") == "on") {
	/* handle change password dialog */
	if ($_SESSION['sess_change_password'] == "1") {
		header ("Location: auth_changepassword.php?ref=" . $_SERVER["HTTP_REFERER"]);
		exit;
	}
	
	/* don't even bother with the guest code if we're already logged in */
	if (($guest_account == true) && (empty($_SESSION["sess_user_id"]))) {
		$guest_user_id = db_fetch_cell("select ID from auth_users where username='" . read_config_option("guest_user") . "'");
		
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
		if (!db_fetch_assoc("select a.SectionID, a.UserID, s.ID, s.Section  from
			auth_acl a left join auth_sections s on a.sectionid=s.id where s.section='$section'
			and a.userid='" . $_SESSION["sess_user_id"] . "'")) {
			
			include ("auth_noauth.php");
			exit;
		}
	}
}

?>
