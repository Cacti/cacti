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
$section = "View Graphs"; $guest_account = true; include ('include/auth.php');
include_once ("include/rrd_functions.php");
include_once ("include/functions.php");
include ("include/top_graph_header.php");

switch ($_GET["rraid"]) {
 case 'all':
    $sql_where = " where id is not null";
    break;
 default:
    $sql_where = " where id=" . $_GET["rraid"];
    break;
}

/* take graph permissions into account here, if the user does not have permission
 give an "access denied" message */
if (read_config_option("global_auth") == "on") {
    $user = db_fetch_row("select user_id from user_auth_graph where local_graph_id=$graphid and user_id=" . GetCurrentUserID($HTTP_COOKIE_VARS["cactilogin"],read_config_option("guest_user")));
    
    if ($config["graph_policy"]["auth"] == "1") {
	if (sizeof($user) > 0) { $access_denied = true; }
    } elseif ($config["graph_policy"]["auth"] == "2") {
	if (! sizeof($user) > 0) { $access_denied = true; }
    }
    
    if ($access_denied == true) {
	print "<strong><font size=\"+1\" color=\"FF0000\">ACCESS DENIED</font></strong>"; exit;
    }
}

/* make sure the graph requested exists (sanity) */
//$gid = db_fetch_cell("select ID from rrd_graph where id=$graphid");

//if (! $gid > 0) {
 //   print "<strong><font size=\"+1\" color=\"FF0000\">GRAPH DOES NOT EXIST</font></strong>"; exit;
//}

$rra_list = db_fetch_assoc("select id,name from rra $sql_where order by steps");

if (sizeof($rra_list) > 0) {
    foreach ($rra_list as $rra) {
	?>
	<div align="center"><img src="graph_image.php?graphid=<?print $graphid;?>&rraid=<?print $rra["ID"];?>" border="0" alt="cacti/rrdtool graph"></div>
	<div align="center"><strong><?print $rra["Name"];?></strong> [<a href="graph.php?graphid=<?print $graphid;?>&rraid=<?print $rra["ID"];?>&showinfo=true">source</a>]</div><br>
	<?
    }
}

include_once ("include/bottom_footer.php");

?>
