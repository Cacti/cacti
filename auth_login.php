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

switch ($_REQUEST["action"]) {
case 'login':
	$user = db_fetch_row("select * from auth_users where Username='" . $_POST["username"] . "' and Password = PASSWORD('" . $_POST["password"] . "')");
	
	if (sizeof($user)) {
		/* --- GOOD username/password --- */
		
		$denied_ips = db_fetch_assoc("select Hostname,Type from auth_hosts where userid=" . $user["ID"] . " where Type='1'");
		$allowed_ips = db_fetch_assoc("select Hostname,Type from auth_hosts where userid=" . $user["ID"] . " where Type='2'");
		
		if (sizeof($denied_ips) > 0) {
			/* if our ip is in this list, it means that we're denied */
			if (in_array($_SERVER["REMOTE_ADDR"],array_rekey($denied_ips, "Hostname", "Hostname"))) {
				$deny_ip = true;
			}
		}
		
		if (sizeof($allowed_ips) > 0) {
			/* if this list contains items, but our ip is not in this list, the we're denied */
			if (!in_array($_SERVER["REMOTE_ADDR"],array_rekey($allowed_ips, "Hostname", "Hostname"))) {
				$deny_ip = true;
			}
		}
		
		if ($deny_ip == true) {
			db_execute("insert into auth_log (username,success,ip) values('$username',2,'" . $_SERVER["REMOTE_ADDR"] . "')");
			include ("noauth.php");
			exit;
		}
		
		/* --- start ldap section --- */
		if (read_config_option("ldap_enabled") == "on"){
			$ldap_conn = ldap_connect(read_config_option("ldap_server")); 
			
			if ($ldap_conn) {
				$ldap_dn = str_replace("<username>",$username,read_config_option("ldap_dn"));
				$ldap_response = @ldap_bind($ldap_conn,$ldap_dn,$password);
				
				if ($ldap_response) {
					if (sizeof(db_fetch_assoc("select * from auth_users where username='$username' and FullName='ldap user'")) == 0) {
						/* get information about the template user */
						$template_user = db_fetch_assoc("SELECT '$username' as Username, 'ldap user' as FullName, '' as MustChangePassword, Password , ShowTree, ShowList, ShowPreview, GraphSettings, LoginOpts, GraphPolicy, ID FROM auth_users WHERE Username = " . read_config_option("ldap_template"));
						
						/* write out that information to the new ldap user */
						db_execute("INSERT INTO auth_users (Username, Password, FullName, MustChangePassword, ShowTree, ShowList, ShowPreview, GraphSettings, LoginOpts, GraphPolicy) VALUES ('" . $template_user["Username"] . "' , '" . $template_user["Password"] . "' , '" . $template_user["FullName"] . "' , '" . $template_user["MustChangePassword"] . "' , '" . $template_user["ShowTree"] . "' , '" . $template_user["ShowList"] . "' , '" . $template_user["ShowPreview"] . "' , '" . $template_user["GraphSettings"] . "' , '" . $template_user["LoginOpts"] . "' , '" . $template_user["GraphPolicy"] . "')");
						$ldap_new = true;
						
						/* get the newly created user_id */
						$user_id = db_fetch_cell("select LAST_INSERT_ID()");
						
						if ($ldap_new == true) {
							/* acl */
							$auth_acl = db_fetch_assoc("SELECT SectionID FROM `auth_acl` WHERE UserID = $user_id");
							
							foreach ($auth_acl as $item) {
								db_execute("INSERT INTO auth_acl (SectionID, UserID) VALUES (" . $item["SectionID"] . ", $user_id)");
							}
							
							/* graph */
							$auth_graph = db_fetch_assoc("SELECT GraphID FROM `auth_graph` WHERE UserID = $user_id");
							
							foreach ($auth_graph as $item) {
								db_execute("INSERT INTO auth_graph (GraphID, UserID) VALUES (" . $item["GraphID"] . ", $user_id)");
							}
							
							/* hierarchy */
							$auth_graph_hierarchy = db_fetch_assoc("SELECT HierarchyID FROM `auth_graph_hierarchy` WHERE UserID = $user_id");
							
							foreach ($auth_graph_hierarchy as $item) {
								db_execute("INSERT INTO auth_graph_hierarchy (HierarchyID, UserID) VALUES (" . $item["HierarchyID"] . ", $user_id)");
							}
							
							/* hosts */
							$auth_hosts = db_fetch_assoc("SELECT ID, Hostname, UserID, Type FROM `auth_hosts` WHERE UserID = $user_id");
							
							foreach ($auth_hosts as $item) {
								db_execute("INSERT INTO auth_hosts (Hostname, UserID, Type) VALUES ('" . $item["Hostname"] . "', $user_id, " . $item["Type"] . ")");
							}
						}
					}
				}
			}
		}
		/* --- end ldap section --- */
		
		/* make entry in the transactions log */
		db_execute("insert into auth_log (username,success,ip) values('$username',1,'" . $_SERVER["REMOTE_ADDR"] . "')");
		
		/* set the php session */
		$_SESSION["sess_user_id"] = $user["ID"];
		
		/* handle "force change password" */
		if ($user["MustChangePassword"] == "on") {
			$_SESSION["sess_change_password"] = "1";
		}
		
		/* ok, at the point the user has been sucessfully authenticated; so we must
		decide what to do next */
		switch ($user[LoginOpts]) {
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
		db_execute("insert into auth_log (username,success,ip) values('$username',0,'" . $_SERVER["REMOTE_ADDR"] . "')");
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
<form name="login" method="post" action="<?print $_SERVER["SCRIPT_NAME"];?>">

<table align="center">
	<tr>
		<td colspan="2"><img src="images/auth_login.gif" border="0" alt=""></td>
	</tr>
	<?
	if ($_POST["action"] == "login") {?>
	<tr height="10"><td></td></tr>
	<tr>
		<td colspan="2"><font color="#FF0000"><strong>Invalid User Name/Password Please Retype:</strong></font></td>
	</tr>
	<?}?>
	<tr height="10"><td></td></tr>
	<tr>
		<td colspan="2">Please enter your cacti user name and password below:</td>
	</tr>
	<tr height="10"><td></td></tr>
	<tr>
		<td>User Name:</td>
		<td><input type="text" name="username" size="40"></td>
	</tr>
	<tr>
		<td>Password:</td>
		<td><input type="password" name="password" size="40"></td>
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
