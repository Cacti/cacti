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

include_once ("include/rrd_functions.php");
include_once ("include/functions.php");
include ("include/top_graph_header.php");

//if (isset($args[hide]) == true) {
	/* find out if the current user has rights here */
//	$graph_settings = db_fetch_cell("select graph_settings from user where id=$user_id");
	
	/* only update expand/contract info is this user has rights to keep their own settings */
//	if ($graph_settings == "on") {
//		db_execute("delete from settings_viewing_tree where treeitemid=$args[branch_id] and userid=$user_id");
//		db_execute("insert into settings_viewing_tree (treeitemid,userid,status) values ($args[branch_id],$user_id,$args[hide])");
//	}
//}

switch ($_REQUEST["action"]) {
case 'tree':
	include_once ('include/tree_view_functions.php');
	
	if ($current_user["show_tree"] == "") {
		print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR TREE VIEW</font></strong>"; exit;
	}
	
	/* if cacti's builtin authentication is turned on then make sure to take 
	graph permissions into account here. if a user does not have rights to a 
	particular graph; do not show it. they will get an access denied message
	if they try and view the graph directly. */
	
	if (read_config_option("global_auth") == "on") {
		/* take tree permissions into account here, if the user does not have permission
		give an "access denied" message */
		$user_auth = db_fetch_row("select user_id from user_auth_tree where tree_id=" . $_SESSION["sess_view_tree_id"] . " and user_id=" . $_SESSION["sess_user_id"]);
		
		if ($current_user["graph_policy"] == "1") {
			if (sizeof($user_auth) > 0) { $access_denied = true; }
		}elseif ($current_user["graph_policy"] == "2") {
			if (sizeof($user_auth) == 0) { $access_denied = true; }
		}
		
		if ($access_denied == true) {
			print "<strong><font size='+1' color='FF0000'>ACCESS DENIED</font></strong>"; exit;
		}
	}
	
	print "	<table width='98%' align='center'>
			<tr>
				<td>
					<strong><a href='graph_view.php?action=tree&tree_id=" . $_SESSION["sess_view_tree_id"] . "'>[root]</a> - " . db_fetch_cell("select name from graph_tree where id=" . $_SESSION["sess_view_tree_id"]) . "</strong>
				</td>
			</tr>
		</table>
		<br>\n";
	
	grow_graph_tree($_SESSION["sess_view_tree_id"], $start_branch, $user_id, $tree_parameters);
	
	break;
case 'preview':
	if ($current_user["show_preview"] == "") {
		print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW</font></strong>"; exit;
	}
	
	/* graph permissions */
	if (read_config_option("global_auth") == "on") {
		if ($current_user["graph_policy"] == "1") {
			$sql_where = "where user_auth_graph.user_id is null";
		}elseif ($current_user["graph_policy"] == "2") {
			$sql_where = "where user_auth_graph.user_id is not null";
		}
		
		$graphs = db_fetch_assoc("select 
			graph_templates_graph.local_graph_id,
			graph_templates_graph.title
			from graph_templates_graph 
			left join user_auth_graph on (graph_templates_graph.local_graph_id=user_auth_graph.local_graph_id and user_auth_graph.user_id=" . $_SESSION["sess_user_id"] . ") 
			$sql_where
			and graph_templates_graph.local_graph_id > 0
			order by graph_templates_graph.title");
	}else{
		$graphs = db_fetch_assoc("select 
			local_graph_id,title 
			from graph_templates_graph 
			where local_graph_id > 0
			order by title");
	}
	
	print "<table width='98%' align='center'><tr>";
	
	if (sizeof($graphs) > 0) {
	foreach ($graphs as $graph) {
		print "<td><a href='graph.php?rra_id=all&local_graph_id=" . $graph["local_graph_id"] . "'><img src='graph_image.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=" . read_graph_config_option("default_rra_id") . "&graph_start=-" . read_graph_config_option("timespan") . "&graph_height=" . read_graph_config_option("default_height") . "&graph_width=" . read_graph_config_option("default_width") . "&graph_nolegend=true' border='0' alt='" . $graph["title"] . "'></a></td>\n";
		
		$i++;
		$k++;
		
		if (($i == read_graph_config_option("num_columns")) && ($k < sizeof($graphs))) {
			$i = 0;
			print "</tr><tr height='10'><td>&nbsp;</td></tr>\n<tr>";
		}
	}
	}
	
	print "</tr></table>";
	
	break;
case 'list':
	if ($current_user["show_list"] == "") {
		print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR LIST VIEW</font></strong>"; exit;
	}
	
	/* graph permissions */
	if (read_config_option("global_auth") == "on") {
		if ($current_user["graph_policy"] == "1") {
			$sql_where = "where user_auth_graph.user_id is null";
		}elseif ($current_user["graph_policy"] == "2") {
			$sql_where = "where user_auth_graph.user_id is not null";
		}
		
		$graphs = db_fetch_assoc("select 
			graph_templates_graph.local_graph_id,
			graph_templates_graph.title
			from graph_templates_graph 
			left join user_auth_graph on (graph_templates_graph.local_graph_id=user_auth_graph.local_graph_id and user_auth_graph.user_id=" . $_SESSION["sess_user_id"] . ") 
			$sql_where
			and graph_templates_graph.local_graph_id > 0
			order by graph_templates_graph.title");
	}else{
		$graphs = db_fetch_assoc("select 
			local_graph_id,title 
			from graph_templates_graph 
			where local_graph_id > 0
			order by title");
	}
	
	print "<table width='98%' align='center'>";
	
	if (sizeof($graphs) > 0) {
	foreach ($graphs as $graph) {
		print "<tr><td><strong><a href='graph.php?local_graph_id=" . $graph["local_graph_id"] . "&rra_id=all'>" . $graph["title"] . "</a></strong></td></tr>\n";
	}
	}
	
	print "</table>";
	
	break;
}

print "<pre>";print $_SESSION["sess_debug_buffer"];print "</pre>";session_unregister("sess_debug_buffer");

include_once ("include/bottom_footer.php");
