<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
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
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
case 'login':
	/* --- start ldap section --- */
	$ldap_auth = false;
	if ((read_config_option("ldap_enabled") == "on") && ($_POST["realm"] == "ldap") && (strlen($_POST["login_password"])) && (strlen($_POST["login_username"]))){
		$ldap_conn = ldap_connect(read_config_option("ldap_server"));

		if ($ldap_conn) {
			$ldap_dn = str_replace("<username>",$_POST["login_username"],read_config_option("ldap_dn"));
			$ldap_response = @ldap_bind($ldap_conn,$ldap_dn,stripslashes($_POST["login_password"]));

			if ($ldap_response) {
				$ldap_auth = true;
				if (sizeof(db_fetch_assoc("select * from user_auth where username='" . $_POST["login_username"] . "' and realm = 1")) == 0) {
					/* copy template user's settings */
					user_copy(read_config_option("ldap_template"), $_POST["login_username"], 1);
				}
			}
		}
	}
	/* --- end ldap section --- */

	if ($ldap_auth) {
		$user = db_fetch_row("select * from user_auth where username='" . $_POST["login_username"] . "' and realm = 1");
	} else {
		$user = db_fetch_row("select * from user_auth where username='" . $_POST["login_username"] . "' and password = '" . md5($_POST["login_password"]) . "' and realm = 0");
	}

	if (sizeof($user)) {
		/* make entry in the transactions log */
		db_execute("insert into user_log (username,user_id,result,ip,time) values('" . $_POST["login_username"] ."'," . $user["id"] . ",1,'" . $_SERVER["REMOTE_ADDR"] . "',NOW())");

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
				if (sizeof(db_fetch_assoc("select realm_id from user_auth_realm where realm_id=8 and user_id=" . $_SESSION["sess_user_id"])) == 0) {
					header("Location: graph_view.php");
				}else{
					header("Location: " . (isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : "index.php"));
				}
				break;
			case '2': /* default console page */
				header("Location: index.php"); break;
			case '3': /* default graph page */
				header("Location: graph_view.php"); break;
		}

		exit;
	}else{
		/* --- BAD username/password --- */
		db_execute("insert into user_log (username,user_id,result,ip,time) values('" . $_POST["login_username"] . "',0,0,'" . $_SERVER["REMOTE_ADDR"] . "',NOW())");
	}
}

?>
<html>
<head>
	<title>Login to Cacti</title>
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

<!-- apparently IIS 5/4 have a bug (Q176113) where setting a cookie and calling the header via
'Location' does not work. This seems to fix the bug for me at least... -->
<form name="login" method="post" action="<?php print basename($_SERVER["PHP_SELF"]);?>">

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
		<td colspan="2">Please enter your Cacti user name and password below:</td>
	</tr>
	<tr height="10"><td></td></tr>
	<tr>
		<td>User Name:</td>
		<td><input type="text" name="login_username" size="40" style="width: 295px;"></td>
	</tr>
	<tr>
		<td>Password:</td>
		<td><input type="password" name="login_password" size="40" style="width: 295px;"></td>
	</tr>
	<?php
	if (read_config_option("ldap_enabled") == "on") {?>
        <tr>
                <td>Realm:</td>
                <td>
			<select name="realm" style="width: 295px;">
				<option value="local">Local</option>
				<option value="ldap" selected>LDAP</option>
			</select>
		</td>
        </tr>
	<?php }?>
	<tr height="10"><td></td></tr>
	<tr>
		<td><input type="submit" value="Login"></td>
	</tr>
</table>

<input type="hidden" name="action" value="login">

</form>

</body>
</html>
