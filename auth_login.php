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
<?	session_start();
	
	include ("include/config.php");
	include_once ("include/functions.php");
	
 	if ($action=="login"){
		include_once ("include/database.php");
		
		$user = db_fetch_row("select * from auth_users where Username=\"$username\" and Password = PASSWORD(\"$password\")");
		$ip = trim(getenv("REMOTE_ADDR"));
		
		if (sizeof($user) == 0) {
			$badpassword = true;
		}
		
		if ($badpassword != true) {
			/* do hostnmame matching */
			$res_id_host = db_fetch_assoc("select Hostname,Type from auth_hosts where userid=$user[ID] order by type");
			
			if (sizeof($res_id_host) > 0) {
			    foreach ($res_id_host as $host) {
				switch ($host[Type]) {
					case "1":
						if ($done != true) {
							if ($host[Hostname] == $ip) {
								$deny = true; $done = true;
							}
						}
						
						break;
					case "2":
						if ($done != true) {
							if ($host[Hostname] == $ip) {
								$deny = false; $done = true;
							}else{
								$deny = true;
							}
						}
						
						break;
				}
				}
			}
			
			/* if the user is denied because of a hostname; log it and exit */
			if ($deny==true) {
				$res_id = db_execute("insert into auth_log (username,success,ip) values(\"$username\",2,\"$ip\")");
				include_once ("$current_path/noauth.php");
				exit;
			}
		}
		
		if ($badpassword != true) {
			$ref = getenv("HTTP_REFERER");
			db_execute("insert into auth_log (username,success,ip) values(\"$username\",1,\"$ip\")");
			
			$user_id = $user[ID];
			$user_hash = $user[Password];
			
			session_register("user_id");
			session_register("user_hash");
			
			if ($user[MustChangePassword] == "on") {
				/* set this cookie to force a password change */
				//header ("Set-Cookie: changepassword=1; path=/;");
				$change_password = 1;
				session_register("change_password");
			}
			
			/* ok, at the point the user has been sucessfully authenticated; so we must
			decide what to do next */
			switch ($user[LoginOpts]) {
				case '1': /* referer */
					header("Location: $ref"); break;
				case '2': /* default console page */
					header("Location: index.php"); break;
				case '3': /* default graph page */
					header("Location: graph_view.php?action=tree"); break;
			}
			
			exit;
		}
	} ?>
<html>
<head>
	<title>Login to cacti</title>
	<STYLE TYPE="text/css">
	<!--	
		BODY, TABLE, TR, TD {font-family: "Verdana, Arial, Helvetica, sans-serif"; font-size: 12px;}
		A {text-decoration: none;}
		A:active { text-decoration: none;}
		A:hover {text-decoration: underline; color: #333333;}
		A:visited {color: Blue;}
	-->
	</style>
</head>
<body>
<? /* apparently IIS 5/4 have a bug (Q176113) where setting a cookie and calling the header via
'Location' does not work. This seems to fix the bug for me at least... */ ?>
<form method="post" action="<?print $HTTP_SERVER_VARS["SCRIPT_NAME"];?>">
<table align="center">
	<tr>
		<td colspan="2"><img src="images/auth_login.gif" border="0" alt=""></td>
	</tr>
	<?if ($badpassword==true) {
	db_execute("insert into auth_log (username,success,ip) values(\"$username\",0,\"$ip\")");?>
	<tr height="10"></tr>
	<tr>
		<td colspan="2"><font color="#FF0000"><strong>Invalid User Name/Password Please Retype:</strong></font></td>
	</tr><?}?>
	<tr height="10"></tr>
	<tr>
		<td colspan="2">Please enter your cacti user name and password below:</td>
	</tr>
	<tr height="10"></tr>
	<tr>
		<td>User Name:</td>
		<td>
		<?if ($conf_drop_down_user_list==true) {?>
		<select name="username">
		<? CreateList(db_fetch_assoc("select username from auth_users","username","username"), ""); ?>
		</select>
		<?}else{?>
		<input type="text" name="username" size="40"><?}?></td>
	</tr>
	<tr>
		<td>Password:</td>
		<td><input type="password" name="password" size="40"></td>
	</tr>
	<tr height="10"></tr>
	<tr>
		<td><input type="submit" value="Login"></td>
	</tr>
</table>
<input type="hidden" name="action" value="login">
</form>
</body>
</html>
