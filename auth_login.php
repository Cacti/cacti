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

include ('include/config.php');

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
case 'login':
	/* --- UPDATE old password with new md5 password value */
	db_execute("update user_auth set password = '" . md5($_POST["password"]) . "' where username='" . $_POST["username"] . "' and password = PASSWORD('" . $_POST["password"] . "')");

	/* --- start ldap section --- */
	$ldap_auth = false;
	if ((read_config_option("ldap_enabled") == "on") && (strlen($_POST["password"]))){
		$ldap_conn = ldap_connect(read_config_option("ldap_server"));

		if ($ldap_conn) {
			$ldap_dn = str_replace("<username>",$_POST["username"],read_config_option("ldap_dn"));
			$ldap_response = @ldap_bind($ldap_conn,$ldap_dn,$_POST["password"]);

			if ($ldap_response) {
				$ldap_auth = true;
				if (sizeof(db_fetch_assoc("select * from user_auth where username='" . $_POST["username"] . "' and full_name='ldap user'")) == 0) {
					/* get information about the template user */
					$template_user = db_fetch_row("SELECT '" . $_POST["username"] . "' as username, 'ldap user' as full_name, '' as must_change_password, '' as password , show_tree, show_list, show_preview, graph_settings, login_opts, graph_policy, id FROM user_auth WHERE username = '" . read_config_option("ldap_template") . "'");

					/* write out that information to the new ldap user */
					db_execute("INSERT INTO user_auth (username, password, full_name, must_change_password, show_tree, show_list, show_preview, graph_settings, login_opts, graph_policy) VALUES ('" . $template_user["username"] . "' , '" . $template_user["password"] . "' , '" . $template_user["full_name"] . "' , '" . $template_user["must_change_password"] . "' , '" . $template_user["show_tree"] . "' , '" . $template_user["show_list"] . "' , '" . $template_user["show_preview"] . "' , '" . $template_user["graph_settings"] . "' , '" . $template_user["login_opts"] . "' , '" . $template_user["graph_policy"] . "')");
					$ldap_new = true;

					/* get the newly created user_id */
					$user_id = db_fetch_insert_id();

					if ($ldap_new == true) {
						/* acl */
						$user_auth_realm = db_fetch_assoc("SELECT realm_id FROM `user_auth_realm` WHERE user_id = $user_id");

						foreach ($user_auth_realm as $item) {
							db_execute("INSERT INTO user_auth_realm (realm_id, user_id) VALUES (" . $item["realm_id"] . ", $user_id)");
						}

						/* graph */
						$user_auth_graph = db_fetch_assoc("SELECT local_graph_id FROM `user_auth_graph` WHERE user_id = $user_id");

						foreach ($user_auth_graph as $item) {
							db_execute("INSERT INTO user_auth_graph (local_graph_id, user_id) VALUES (" . $item["local_graph_id"] . ", $user_id)");
						}

						/* hierarchy */
						$user_auth_tree = db_fetch_assoc("SELECT tree_id FROM `user_auth_tree` WHERE UserID = $user_id");

						foreach ($user_auth_tree as $item) {
							db_execute("INSERT INTO user_auth_tree (tree_id, user_id) VALUES (" . $item["tree_id"] . ", $user_id)");
						}

						/* hosts */
						$user_auth_hosts = db_fetch_assoc("SELECT hostname, user_id, policy FROM `user_auth_hosts` WHERE user_id = $user_id");

						foreach ($user_auth_hosts as $item) {
							db_execute("INSERT INTO user_auth_hosts (hostname, user_id, policy) VALUES ('" . $item["hostname"] . "', $user_id, " . $item["policy"] . ")");
						}
					}
				}
			}
		}
	}
	/* --- end ldap section --- */

	if ($ldap_auth) {
                $user = db_fetch_row("select * from user_auth where username='" . $_POST["username"] . "' and full_name = 'ldap user'");
	} else {
		$user = db_fetch_row("select * from user_auth where username='" . $_POST["username"] . "' and password = '" . md5($_POST["password"]) . "' and full_name!='ldap user'");
	}
	
	if (sizeof($user)) {
		/* --- GOOD username/password --- */
		
		$denied_ips = db_fetch_assoc("select hostname,policy from user_auth_hosts where user_id=" . $user["id"] . " and policy='1'");
		$allowed_ips = db_fetch_assoc("select hostname,policy from user_auth_hosts where user_id=" . $user["id"] . " and policy='2'");
		
		$deny_ip = false; /* do not deny by ip by default */
		
		if (sizeof($denied_ips) > 0) {
			/* if our ip is in this list, it means that we're denied */
			if (in_array($_SERVER["REMOTE_ADDR"],array_rekey($denied_ips, "hostname", "hostname"))) {
				$deny_ip = true;
			}
		}
		
		if (sizeof($allowed_ips) > 0) {
			/* if this list contains items, but our ip is not in this list, the we're denied */
			if (!in_array($_SERVER["REMOTE_ADDR"],array_rekey($allowed_ips, "hostname", "hostname"))) {
				$deny_ip = true;
			}
		}
		
		if ($deny_ip == true) {
			db_execute("insert into user_log (username,result,ip) values('" . $_POST["username"]. "',2,'" . $_SERVER["REMOTE_ADDR"] . "')");
			include ("noauth.php");
			exit;
		}
		
		/* make entry in the transactions log */
		db_execute("insert into user_log (username,result,ip) values('" . $_POST["username"] . "',1,'" . $_SERVER["REMOTE_ADDR"] . "')");
		
		/* set the php session */
		$_SESSION["sess_user_id"] = $user["id"];
		
		/* handle "force change password" */
		if ($user["must_change_password"] == "on") {
			$_SESSION["sess_change_password"] = true;
		}
		
		/* ok, at the point the user has been sucessfully authenticated; so we must
		decide what to do next */
		switch ($user["login_opts"]) {
			case '1': /* referer */
				header("Location: " . $_SERVER["HTTP_REFERER"]); break;
			case '2': /* default console page */
				header("Location: index.php"); break;
			case '3': /* default graph page */
				header("Location: graph_view.php?action=tree"); break;
		}
		
		exit;
	}else{
		/* --- BAD username/password --- */
		db_execute("insert into user_log (username,result,ip) values('" . $_POST["username"] . "',0,'" . $_SERVER["REMOTE_ADDR"] . "')");
	}
}

?>
<html>
<head>
	<title>Login to cacti</title>
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

<body onload="document.login.username.focus()">

<!-- apparently IIS 5/4 have a bug (Q176113) where setting a cookie and calling the header via
'Location' does not work. This seems to fix the bug for me at least... -->
<form name="login" method="post" action="<?php print $_SERVER["PHP_SELF"];?>">

<table align="center">
	<tr>
		<td colspan="2"><img src="images/auth_login.gif" border="0" alt=""></td>
	</tr>
	<?php
	if ($_REQUEST["action"] == "login") {?>
	<tr height="10"><td></td></tr>
	<tr>
		<td colspan="2"><font color="#FF0000"><strong>Invalid User Name/Password Please Retype:</strong></font></td>
	</tr>
	<?php }?>
	<tr height="10"><td></td></tr>
	<tr>
		<td colspan="2">Please enter your cacti user name and password below:</td>
	</tr>
	<tr height="10"><td></td></tr>
	<tr>
		<td>User Name:</td>
		<td><input type="text" name="username" size="40" style="width: 295px;"></td>
	</tr>
	<tr>
		<td>Password:</td>
		<td><input type="password" name="password" size="40" style="width: 295px;"></td>
	</tr>
	<tr height="10"><td></td></tr>
	<tr>
		<td><input type="submit" value="Login"></td>
	</tr>
</table>

<input type="hidden" name="action" value="login">

</form>

</body>
</html>
