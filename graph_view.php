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

//if (isset($args[hide]) == true) {
	/* find out if the current user has rights here */
//	$graph_settings = db_fetch_cell("select graph_settings from user where id=$user_id");
	
	/* only update expand/contract info is this user has rights to keep their own settings */
//	if ($graph_settings == "on") {
//		db_execute("delete from settings_viewing_tree where treeitemid=$args[branch_id] and userid=$user_id");
//		db_execute("insert into settings_viewing_tree (treeitemid,userid,status) values ($args[branch_id],$user_id,$args[hide])");
//	}
//}

switch ($action) {
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
	
	print "<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>";
	print "<tr><td><strong><a href='graph_view.php?action=tree&tree_id=" . $_SESSION["sess_view_tree_id"] . "'>[root]</a> - $tree_name</strong></td></tr>";
	
	grow_graph_tree($_SESSION["sess_view_tree_id"], $start_branch, $user_id, $tree_parameters);
	
	print '</tr>';
	print "</table>";
	
	break;
case 'preview':
	if ($show_preview_view != "on") {
		print "<strong><font size=\"+1\" color=\"FF0000\">YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW</font></strong>"; exit;
	}
	
	/* graph permissions */
	if ($config["global_auth"]["value"] == "on") {
		if ($config["graph_policy"]["auth"] == "1") {
			$sql_where = "where ag.userid is null";
		}elseif ($config["graph_policy"]["auth"] == "2") {
			$sql_where = "where ag.userid is not null";
		}
		
		$sql_id = mysql_query("select 
			g.id,g.title 
			from rrd_graph g 
			left join auth_graph ag on (g.id=ag.graphid and ag.userid=$user_id) 
			$sql_where
			order by g.title",$cnn_id);
	}else{
		$sql_id = mysql_query("select 
			id,title 
			from rrd_graph 
			order by title",$cnn_id);
	}
	
	$rows = mysql_num_rows($sql_id); $i = 0;
	
	?><tr><td bgcolor="#<?print $color_light;?>" width="1%" rowspan="99999"></td><?
		while ($i < $rows) {
			?><td width="25%"><a href="graph.php?rraid=all&graphid=<?print mysql_result($sql_id, $i, "id");?>"><img src="graph_image.php?graphid=<?print mysql_result($sql_id, $i, "id");?>&rraid=<?print $array_settings["preview"]["rraid"];?>&graph_start=-<?print $array_settings["preview"]["timespan"];?>&graph_height=<?print $array_settings["preview"]["height"];?>&graph_width=<?print $array_settings["preview"]["width"];?>&graph_nolegend=true" border="0" alt="<?print mysql_result($sql_id, $i, "title");?>"></a><?
			?></td><?
			$i++;
			$k++;
			
			if ($k==$array_settings["preview"]["columnnumber"]) {
				$k = 0;
				?></tr><tr height="10"><td>&nbsp;</td></tr><tr><?
			}
			$i_rra = 0;
		}
	?></tr><?
	
	break;
case 'list':
	if ($show_list_view != "on") {
		print "<strong><font size=\"+1\" color=\"FF0000\">YOU DO NOT HAVE RIGHTS FOR LIST VIEW</font></strong>"; exit;
	}
	
	include_once ("include/form.php");
	
	/* graph permissions */
	if ($config["global_auth"]["value"] == "on") {
		if ($config["graph_policy"]["auth"] == "1") {
			$sql_where = "where ag.userid is null";
		}elseif ($config["graph_policy"]["auth"] == "2") {
			$sql_where = "where ag.userid is not null";
		}
		
		$sql_id = mysql_query("select 
			g.id,g.title 
			from rrd_graph g 
			left join auth_graph ag on (g.id=ag.graphid and ag.userid=$user_id) 
			$sql_where
			order by g.title",$cnn_id);
	}else{
		$sql_id = mysql_query("select 
			id,title 
			from rrd_graph 
			order by title",$cnn_id);
	}
	
	$rows = mysql_num_rows($sql_id); $i = 0;
	
	$sql_id_rra = mysql_query("select id,name from rrd_rra order by id",$cnn_id);
	$rows_rra = mysql_num_rows($sql_id_rra); $i_rra = 0;
	
	print "<table width=\"97%\">";
	print "<tr>";
	
	while ($i < $rows) {
		switch ($array_settings["list"]["listviewtype"]) {
			case "1":
				?><td width="25%"><strong><?print mysql_result($sql_id, $i, "title");?> [<a href="graph.php?graphid=<?print mysql_result($sql_id, $i, "id");?>&rraid=all">all</a>]</strong><br><?
				while ($i_rra < $rows_rra) {
					?><a href="graph.php?graphid=<?print mysql_result($sql_id, $i, "id");?>&rraid=<?print mysql_result($sql_id_rra, $i_rra, "id");?>"><?print mysql_result($sql_id_rra, $i_rra, "name");?></a><br><?
					$i_rra++;
				}
				?></td><?
				$i++;
				$k++;
				
				if ($k==4) {
					$k = 0;
					?></tr><tr height="10"><td>&nbsp;</td></tr><tr><?
				}
				$i_rra = 0;
				
				break;
			case "2":
				?><td><strong><a href="graph.php?graphid=<?print mysql_result($sql_id, $i, "id");?>&rraid=all"><?print mysql_result($sql_id, $i, "title");?></a></strong></td></tr><tr><?
				$i++;
				
				break;
		}
	}
		?></tr></table><?
	
	break;
}

print "<pre>";print $_SESSION["sess_debug_buffer"];print "</pre>";session_unregister("sess_debug_buffer");

include_once ("include/bottom_footer.php");
