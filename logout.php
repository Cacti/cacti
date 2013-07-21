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

include("./include/auth.php");

api_plugin_hook('logout_pre_session_destroy');

/* Clear session */
setcookie(session_name(),"",time() - 3600,"/");
session_destroy();

api_plugin_hook('logout_post_session_destroy');

/* Check to see if we are using Web Basic Auth */
if (read_config_option("auth_method") == "2") {
	if (api_plugin_hook_function('custom_logout_message', OPER_MODE_NATIVE) == OPER_MODE_RESKIN) {
		exit;
	}

	?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Logout of Cacti</title>
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
<body>
<table align="center">
	<tr>
		<td><img src="images/auth_logout.gif" border="0" alt=""></td>
	</tr><tr>
		<td><br>To end your Cacti session please close your web browser.<br><br><a href="index.php">Return to Cacti</a></td>
	</tr>
</table>
</body>
</html>
		<?php

}else{
	/* Default action */
	header("Location: index.php");
	exit;
}

?>
