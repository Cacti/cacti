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
<?	header ("Cache-Control: no-cache, must-revalidate");
	header ("Pragma: no-cache");
	
	session_start();
	
	include_once ("include/rrd_functions.php");
	include_once ("include/functions.php");
	include ("include/top_graph_header.php");
	
$user_id = GetCurrentUserID($HTTP_SESSION_VARS['user_id'], $config["guest_user"]["value"]);

if (isset($hide) == true) {
	/* find out if the current user has rights here */
	$graph_settings = db_fetch_cell("select GraphSettings from auth_users where id=$user_id");
	
	/* only update expand/contract info is this user has writes to keep their own settings */
	if ($graph_settings == "on") {
		db_execute("delete from settings_tree where treeitemid=$branch_id and userid=$user_id");
		db_execute("insert into settings_tree (treeitemid,userid,status) values ($branch_id,$user_id,$hide)");
	}
}

/* if auth is enabled, get some basic info about this user so we know what they have
rights to */
if ($config["global_auth"]["value"] == "on") {
	$user = db_fetch_row("select GraphPolicy,ShowTree from auth_users where id=$user_id");
	
	if (sizeof($user) == 0) {
		print "<strong><font size=\"+1\" color=\"FF0000\">CANNOT FIND USER!</font></strong>"; exit;
	}else{
		$show_tree_view = $user[ShowTree];
	}
}else{
	/* if auth is turned off; obviously they have rights */
	$show_tree_view = "on";
}

if (! isset($action)) { $action = 'tree'; }

switch ($action) {
	case 'tree':
		if ($show_tree_view != "on") {
			print "<strong><font size=\"+1\" color=\"FF0000\">YOU DO NOT HAVE RIGHTS FOR TREE VIEW</font></strong>"; exit;
		}
		
		include_once ('include/form.php');
		include_once ('include/tree_view_functions.php');
		
		DrawMatrixTableBegin("97%");
		
		if (!(isset($tree_id))) { $tree_id = 0; }
		
		/* if cacti's builtin authentication is turned on then make sure to take 
		graph permissions into account here. if a user does not have rights to a 
		particular graph; do not show it. they will get an access denied message
		if they try and view the graph directly. */
		if ($config["global_auth"]["value"] == "on") {
			/* take tree permissions into account here, if the user does not have permission
			give an "access denied" message */
			$user = db_fetch_row("select UserID from auth_graph_hierarchy where hierarchyid=$tree_id and userid=" . GetCurrentUserID($HTTP_SESSION_VARS['user_id'],$config["guest_user"]["value"]));
			
			if ($config["graph_policy"]["auth"] == "1") {
				if (sizeof($user) > 0) { $access_denied = true; }
			}elseif ($config["graph_policy"]["auth"] == "2") {
				if (sizeof($user) == 0) { $access_denied = true; }
			}
			
			if ($access_denied == true) {
				print "<strong><font size=\"+1\" color=\"FF0000\">ACCESS DENIED</font></strong>"; exit;
			}
		}
		
		print '<tr><td colspan="2">';
		print "<strong><a href='graph_view.php?action=tree&tree_id=$tree_id'>[root]</a> - $tree_name</strong><br><br>";
		print '</td></tr>';
		
		grow_graph_tree_2($tree_id, $start_branch, $user_id, $tree_parameters);
		
		print '</td></tr></table>';
		
		break;
}

include_once ("include/bottom_footer.php");
