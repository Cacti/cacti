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
include_once ("include/rrd_functions.php");
include_once ("include/functions.php");
include ("include/top_graph_header.php");

if ($_GET["rra_id"] == "all") {
	$sql_where = " where id is not null";
}else{
	$sql_where = " where id=" . $_GET["rra_id"];
}

/* take graph permissions into account here, if the user does not have permission
give an "access denied" message */
if (read_config_option("global_auth") == "on") {
	$user_auth = db_fetch_row("select user_id from user_auth_graph where local_graph_id=" . $_GET["local_graph_id"] . " and user_id=" . $_SESSION["sess_user_id"]);
	
		if ($current_user["graph_policy"] == "1") {
			if (sizeof($user_auth) > 0) { $access_denied = true; }
		}elseif ($current_user["graph_policy"] == "2") {
			if (sizeof($user_auth) == 0) { $access_denied = true; }
		}
	
	if ($access_denied == true) {
		print "<strong><font size='+1' color='FF0000'>ACCESS DENIED</font></strong>"; exit;
	}
}

/* make sure the graph requested exists (sanity) */
if (!(db_fetch_cell("select local_graph_id from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]))) {
	print "<strong><font size='+1' color='FF0000'>GRAPH DOES NOT EXIST</font></strong>"; exit;
}

$rras = db_fetch_assoc("select id,name from rra $sql_where order by steps");
$graph_title = db_fetch_cell("select title from graph_templates_graph where local_graph_id=" . $_GET["local_graph_id"]);

if (sizeof($rras) > 0) {
foreach ($rras as $rra) {
	print "	<div align='center'><img src='graph_image.php?local_graph_id=" . $_GET["local_graph_id"] . "&rra_id=" . $rra["id"] . "' border='0' alt='$graph_title'></div>\n
		<div align='center'><strong>" . $rra["name"] . "</strong> [<a href='graph.php?local_graph_id=" . $_GET["local_graph_id"] . "&rra_id=" . $rra["id"] . "&show_source=true'>source</a>]</div><br>\n";
}
}

include_once ("include/bottom_footer.php");

?>
