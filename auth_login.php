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

/* set default action */
if (isset($_REQUEST["action"])) {
	$action = $_REQUEST["action"];
}else{
	$action = "";
}

/* Get the username */
if (read_config_option("auth_method") == "2") {
	/* Get the Web Basic Auth username and set action so we login right away */
	$action = "login";
	if (isset($_SERVER["PHP_AUTH_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["PHP_AUTH_USER"]);
	}elseif (isset($_SERVER["REMOTE_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["REMOTE_USER"]);
	}elseif (isset($_SERVER["REDIRECT_REMOTE_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["REDIRECT_REMOTE_USER"]);
	}elseif (isset($_SERVER["HTTP_PHP_AUTH_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["HTTP_PHP_AUTH_USER"]);
	}elseif (isset($_SERVER["HTTP_REMOTE_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["HTTP_REMOTE_USER"]);
	}elseif (isset($_SERVER["HTTP_REDIRECT_REMOTE_USER"])) {
		$username = str_replace("\\", "\\\\", $_SERVER["HTTP_REDIRECT_REMOTE_USER"]);

	}else{
		/* No user - Bad juju! */
		$username = "";
		cacti_log("ERROR: No username passed with Web Basic Authentication enabled.", false, "AUTH");
		auth_display_custom_error_message("Web Basic Authentication configured, but no username was passed from the web server.  Please make sure you have authentication enabled on the web server.");
		exit;
	}
}else{
	if ($action == "login") {
		/* LDAP and Builtin get username from Form */
		$username = get_request_var_post("login_username");
	}else{
		$username = "";
	}
}

$username = sanitize_search_string($username);

/* process login */
$copy_user = false;
$user_auth = false;
$user_enabled = 1;
$ldap_error = false;
$ldap_error_message = "";
$realm = 0;
if ($action == 'login') {
	switch (read_config_option("auth_method")) {
	case "0":
		/* No auth, no action, also shouldn't get here */
		exit;

		break;
	case "2":
		/* Web Basic Auth */
		$copy_user = true;
		$user_auth = true;
		$realm = 2;
		/* Locate user in database */
		$user = db_fetch_row("SELECT * FROM user_auth WHERE username = " . $cnn_id->qstr($username) . " AND realm = 2");

		break;
	case "3":
		/* LDAP Auth */
 		if ((get_request_var_post("realm") == "ldap") && (strlen(get_request_var_post("login_password")) > 0)) {
			/* include LDAP lib */
			include_once("./lib/ldap.php");

			/* get user DN */
			$ldap_dn_search_response = cacti_ldap_search_dn($username);
			if ($ldap_dn_search_response["error_num"] == "0") {
				$ldap_dn = $ldap_dn_search_response["dn"];
			}else{
				/* Error searching */
				cacti_log("LOGIN: LDAP Error: " . $ldap_dn_search_response["error_text"], false, "AUTH");
				$ldap_error = true;
				$ldap_error_message = "LDAP Search Error: " . $ldap_dn_search_response["error_text"];
				$user_auth = false;
				$user = array();
			}

			if (!$ldap_error) {
				/* auth user with LDAP */
				$ldap_auth_response = cacti_ldap_auth($username,stripslashes(get_request_var_post("login_password")),$ldap_dn);

				if ($ldap_auth_response["error_num"] == "0") {
					/* User ok */
					$user_auth = true;
					$copy_user = true;
					$realm = 1;
					/* Locate user in database */
					cacti_log("LOGIN: LDAP User '" . $username . "' Authenticated", false, "AUTH");
					$user = db_fetch_row("SELECT * FROM user_auth WHERE username = " . $cnn_id->qstr($username) . " AND realm = 1");
				}else{
					/* error */
					cacti_log("LOGIN: LDAP Error: " . $ldap_auth_response["error_text"], false, "AUTH");
					$ldap_error = true;
					$ldap_error_message = "LDAP Error: " . $ldap_auth_response["error_text"];
					$user_auth = false;
					$user = array();
				}
			}

		}

	default:
		if (!api_plugin_hook_function('login_process', false)) {
			/* Builtin Auth */
			if ((!$user_auth) && (!$ldap_error)) {
				/* if auth has not occured process for builtin - AKA Ldap fall through */
				$user = db_fetch_row("SELECT * FROM user_auth WHERE username = " . $cnn_id->qstr($username) . " AND password = '" . md5(get_request_var_post("login_password")) . "' AND realm = 0");
			}
		}
	}
	/* end of switch */

	/* Create user from template if requested */
	if ((!sizeof($user)) && ($copy_user) && (read_config_option("user_template") != "0") && (strlen($username) > 0)) {
		cacti_log("WARN: User '" . $username . "' does not exist, copying template user", false, "AUTH");
		/* check that template user exists */
		if (db_fetch_row("SELECT id FROM user_auth WHERE username = '" . read_config_option("user_template") . "' AND realm = 0")) {
			/* template user found */
			user_copy(read_config_option("user_template"), $username, 0, $realm);
			/* requery newly created user */
			$user = db_fetch_row("SELECT * FROM user_auth WHERE username = " . $cnn_id->qstr($username) . " AND realm = " . $realm);
		}else{
			/* error */
			cacti_log("LOGIN: Template user '" . read_config_option("user_template") . "' does not exist.", false, "AUTH");
			auth_display_custom_error_message("Template user '" . read_config_option("user_template") . "' does not exist.");
			exit;
		}
	}

	/* Guest account checking - Not for builtin */
	$guest_user = false;
	if ((sizeof($user) < 1) && ($user_auth) && (read_config_option("guest_user") != "0")) {
		/* Locate guest user record */
		$user = db_fetch_row("SELECT * FROM user_auth WHERE username = '" . read_config_option("guest_user") . "'");
		if ($user) {
			cacti_log("LOGIN: Authenicated user '" . $username . "' using guest account '" . $user["username"] . "'", false, "AUTH");
			$guest_user = true;
		}else{
			/* error */
			auth_display_custom_error_message("Guest user \"" . read_config_option("guest_user") . "\" does not exist.");
			cacti_log("LOGIN: Unable to locate guest user '" . read_config_option("guest_user") . "'", false, "AUTH");
			exit;
		}
	}

	/* Process the user  */
	if (sizeof($user) > 0) {
		cacti_log("LOGIN: User '" . $user["username"] . "' Authenticated", false, "AUTH");
		db_execute("INSERT INTO user_log (username,user_id,result,ip,time) VALUES (" . $cnn_id->qstr($username) . "," . $user["id"] . ",1,'" . $_SERVER["REMOTE_ADDR"] . "',NOW())");
		/* is user enabled */
		$user_enabled = $user["enabled"];
		if ($user_enabled != "on") {
			/* Display error */
			auth_display_custom_error_message("Access Denied, user account disabled.");
			exit;
		}

		/* set the php session */
		$_SESSION["sess_user_id"] = $user["id"];

		/* handle "force change password" */
		if (($user["must_change_password"] == "on") && (read_config_option("auth_method") == 1)) {
			$_SESSION["sess_change_password"] = true;
		}

		/* ok, at the point the user has been sucessfully authenticated; so we must
		decide what to do next */
		switch ($user["login_opts"]) {
			case '1': /* referer */
				/* because we use plugins, we can't redirect back to graph_view.php if they don't
				 * have console access
				 */
				if (isset($_SERVER["HTTP_REFERER"])) {
					$referer = $_SERVER["HTTP_REFERER"];
					if (basename($referer) == "logout.php") {
						$referer = $config['url_path'] . "index.php";
					}
				} else if (isset($_SERVER["REQUEST_URI"])) {
					$referer = $_SERVER["REQUEST_URI"];
					if (basename($referer) == "logout.php") {
						$referer = $config['url_path'] . "index.php";
					}
				} else {
					$referer = $config['url_path'] . "index.php";
				}

				if (substr_count($referer, "plugins")) {
					header("Location: " . $referer);
				} elseif (sizeof(db_fetch_assoc("SELECT realm_id FROM user_auth_realm WHERE realm_id = 8 AND user_id = " . $_SESSION["sess_user_id"])) == 0) {
					header("Location: graph_view.php");
				} else {
					header("Location: $referer");
				}

				break;
			case '2': /* default console page */
				header("Location: " . $config['url_path'] . "index.php");

				break;
			case '3': /* default graph page */
				header("Location: " . $config['url_path'] . "graph_view.php");

				break;
			default:
				api_plugin_hook_function('login_options_navigate', $user['login_opts']);
		}
		exit;
	}else{
		if ((!$guest_user) && ($user_auth)) {
			/* No guest account defined */
			auth_display_custom_error_message("Access Denied, please contact you Cacti Administrator.");
			cacti_log("LOGIN: Access Denied, No guest enabled or template user to copy", false, "AUTH");
			exit;
		}else{
			/* BAD username/password builtin and LDAP */
			db_execute("INSERT INTO user_log (username,user_id,result,ip,time) VALUES (" . $cnn_id->qstr($username) . ",0,0,'" . $_SERVER["REMOTE_ADDR"] . "',NOW())");
		}
	}
}

/* auth_display_custom_error_message - displays a custom error message to the browser that looks like
     the pre-defined error messages
   @arg $message - the actual text of the error message to display */
function auth_display_custom_error_message($message) {
	/* kill the session */
	setcookie(session_name(),"",time() - 3600,"/");
	/* print error */
	print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">";
	print "<html>\n<head>\n";
	print "     <title>" . "Cacti" . "</title>\n";
	print "     <meta http-equiv='Content-Type' content='text/html;charset=utf-8'>";
	print "     <link href=\"include/main.css\" type=\"text/css\" rel=\"stylesheet\">";
	print "</head>\n";
	print "<body>\n<br><br>\n";
	display_custom_error_message($message);
	print "</body>\n</html>\n";
}

if (api_plugin_hook_function('custom_login', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
	return;
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title><?php print api_plugin_hook_function("login_title", "Login to Cacti");?></title>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8">
	<STYLE TYPE="text/css">
	<!--
		BODY, TABLE, TR, TD {font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 12px;}
		A {text-decoration: none;}
		A:active { text-decoration: none;}
		A:hover {text-decoration: underline; color: #333333;}
		A:visited {color: Blue;}
	-->
	</style>
</head>
<body bgcolor="#FFFFFF" onload="document.login.login_username.focus()">
	<form name="login" method="post" action="<?php print basename($_SERVER["PHP_SELF"]);?>">
	<input type="hidden" name="action" value="login">
<?php

api_plugin_hook_function("login_before", array('ldap_error' => $ldap_error, 'ldap_error_message' => $ldap_error_message, 'username' => $username, 'user_enabled' => $user_enabled, 'action' => $action));

$cacti_logo = $config['url_path'] . 'images/auth_login.gif';
$cacti_logo = api_plugin_hook_function('cacti_image', $cacti_logo);

?>
	<table id="login" align="center">
		<tr>
			<td colspan="2"><center><?php if ($cacti_logo != '') { ?><img src="<?php echo $cacti_logo; ?>" border="0" alt=""><?php } ?></center></td>
		</tr>
		<?php

		if ($ldap_error) {?>
		<tr style="height:10px;"><td></td></tr>
		<tr>
			<td id="error" colspan="2"><font color="#FF0000"><strong><?php print $ldap_error_message; ?></strong></font></td>
		</tr>
		<?php }else{
		if ($action == "login") {?>
		<tr style="height:10px;"><td></td></tr>
		<tr>
			<td id="error" colspan="2"><font color="#FF0000"><strong>Invalid User Name/Password Please Retype</strong></font></td>
		</tr>
		<?php }
		if ($user_enabled == "0") {?>
		<tr style="height:10px;"><td></td></tr>
		<tr>
			<td id="error" colspan="2"><font color="#FF0000"><strong>User Account Disabled</strong></font></td>
		</tr>
		<?php } } ?>

		<tr style="height:10px;"><td></td></tr>
		<tr id="login_row">
			<td colspan="2">Please enter your Cacti user name and password below:</td>
		</tr>
		<tr style="height:10px;"><td></td></tr>
		<tr id="user_row">
			<td>User Name:</td>
			<td><input type="text" name="login_username" size="40" style="width: 295px;" value="<?php print htmlspecialchars($username); ?>"></td>
		</tr>
		<tr id="password_row">
			<td>Password:</td>
			<td><input type="password" name="login_password" size="40" style="width: 295px;"></td>
		</tr>
		<?php
		if (read_config_option("auth_method") == "3" || api_plugin_hook_function('login_realms_exist')) {
			$realms = api_plugin_hook_function('login_realms', array("local" => array("name" => "Local", "selected" => false), "ldap" => array("name" => "LDAP", "selected" => true)));
			?>
		<tr id="realm_row">
			<td>Realm:</td>
			<td>
				<select name="realm" style="width: 295px;"><?php
				if (sizeof($realms)) {
				foreach($realms as $name => $realm) {
					print "\t\t\t\t\t<option value='" . $name . "'" . ($realm["selected"] ? " selected":"") . ">" . htmlspecialchars($realm["name"]) . "</option>\n";
				}
				}
				?>
				</select>
			</td>
		</tr>
		<?php }?>
		<tr style="height:10px;"><td></td></tr>
		<tr>
			<td><input type="submit" value="Login"></td>
		</tr>
	</table>
<?php api_plugin_hook('login_after'); ?>
	</form>
</body>
</html>
