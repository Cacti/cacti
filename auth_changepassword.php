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
<?	include ("include/database.php");
	include ("include/config.php");
	
	/* SESSION DATA */
	session_start();
	
	$userid = $HTTP_SESSION_VARS["user_id"];
	$user = db_fetch_row("select * from auth_users where id=$userid",$cnn_id);
	
	$ip = trim(getenv("REMOTE_ADDR"));
	//$username = mysql_result($res_id_user, 0, "username");
	
	switch ($action) {
		case 'changepassword':
			if (($password == $confirm) && ($password != "")) {
				mysql_query("insert into auth_log (username,success,ip) values(\"$user[Username]\",3,\"$ip\")",$cnn_id);
				mysql_query("update auth_users set mustchangepassword=\"\",password=PASSWORD(\"$password\") where id=$userid",$cnn_id);
				
				$HTTP_SESSION_VARS['change_password'] = 0;
				
				header("Location: $ref");
				exit;
			}else{
				$badpassword = true;
			}
			
			break;
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
<form method="post" action="<?print $HTTP_SERVER_VARS["SCRIPT_NAME"];?>">
<table align="center">
	<tr>
		<td colspan="2"><img src="images/auth_login.gif" border="0" alt=""></td>
	</tr>
	<?if ($badpassword == true) {?>
	<tr height="10"></tr>
	<tr>
		<td colspan="2"><font color="#FF0000"><strong>Your passwords do not match, please retype:</strong></font></td>
	</tr><?}?>
	<tr height="10"></tr>
	<tr>
		<td colspan="2">
			<strong><font color="#FF0000">*** Forced Password Change ***</font></strong><br><br>
			Please enter a new password for cacti:
		</td>
	</tr>
	<tr height="10"></tr>
	<tr>
		<td>Password:</td>
		<td><input type="password" name="password" size="40"></td>
	</tr>
	<tr>
		<td>Confirm:</td>
		<td><input type="password" name="confirm" size="40"></td>
	</tr>
	<tr height="10"></tr>
	<tr>
		<td><input type="submit" value="Save"></td>
	</tr>
</table>
<input type="hidden" name="action" value="changepassword">
<input type="hidden" name="ref" value="<?print $ref;?>">
</form>
</body>
</html>