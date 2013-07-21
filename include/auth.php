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

include("./include/global.php");

/* check to see if this is a new installation */
if (db_fetch_cell("select cacti from version") != $config["cacti_version"]) {
	header ("Location: " . $config['url_path'] . "install/");
	exit;
}

if (read_config_option("auth_method") != 0) {
	/* handle alternate authentication realms */
	api_plugin_hook_function('auth_alternate_realms');

	/* handle change password dialog */
	if ((isset($_SESSION['sess_change_password'])) && (read_config_option("webbasic_enabled") != "on")) {
		header ("Location: " . $config['url_path'] . "auth_changepassword.php?ref=" . (isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : "index.php"));
		exit;
	}

	/* don't even bother with the guest code if we're already logged in */
	if ((isset($guest_account)) && (empty($_SESSION["sess_user_id"]))) {
		$guest_user_id = db_fetch_cell("select id from user_auth where username='" . read_config_option("guest_user") . "' and realm = 0 and enabled = 'on'");

		/* cannot find guest user */
		if (!empty($guest_user_id)) {
			$_SESSION["sess_user_id"] = $guest_user_id;
		}
	}

	/* if we are a guest user in a non-guest area, wipe credentials */
	if (!empty($_SESSION["sess_user_id"])) {
		if ((!isset($guest_account)) && (db_fetch_cell("select id from user_auth where username='" . read_config_option("guest_user") . "'") == $_SESSION["sess_user_id"])) {
			kill_session_var("sess_user_id");
		}
	}

	if (empty($_SESSION["sess_user_id"])) {
		include("./auth_login.php");
		exit;
	}elseif (!empty($_SESSION["sess_user_id"])) {
		$realm_id = 0;

		if (isset($user_auth_realm_filenames{basename($_SERVER["PHP_SELF"])})) {
			$realm_id = $user_auth_realm_filenames{basename($_SERVER["PHP_SELF"])};
		}

		if ($realm_id != -1 && ((!db_fetch_assoc("select
			user_auth_realm.realm_id
			from
			user_auth_realm
			where user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "'
			and user_auth_realm.realm_id='$realm_id'")) || (empty($realm_id)))) {

			if (isset($_SERVER["HTTP_REFERER"])) {
				$goBack = "<td class='textArea' colspan='2' align='center'>( <a href='" . htmlspecialchars($_SERVER["HTTP_REFERER"]) . "'>Return</a> | <a href='" . $config['url_path'] . "logout.php'>Login Again</a> )</td>";
			}else{
				$goBack = "<td class='textArea' colspan='2' align='center'>( <a href='" . $config['url_path'] . "logout.php'>Login Again</a> )</td>";
			}

			?>
			<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
			<html>
			<head>
				<title>Cacti</title>
				<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
				<link href="<?php echo $config['url_path']; ?>include/main.css" type="text/css" rel="stylesheet">
			</head>
			<body>
			<br><br>

			<table width="450" align='center'>
				<tr>
					<td colspan='2'><img src='<?php echo $config['url_path']; ?>images/auth_deny.gif' border='0' alt='Access Denied'></td>
				</tr>
				<tr style='height:10px;'><td></td></tr>
				<tr>
					<td class='textArea' colspan='2'>You are not permitted to access this section of Cacti. If you feel that you
					need access to this particular section, please contact the Cacti administrator.</td>
				</tr>
				<tr>
					<?php print $goBack;?>
				</tr>
			</table>

			</body>
			</html>
			<?php
			exit;
		}
	}
}

?>
