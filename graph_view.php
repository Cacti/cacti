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
	//if (mysql_num_rows($sql_id) != 0) {
		if ($graph_settings == "on") {
			db_execute("delete from settings_tree where treeitemid=$branch_id and userid=$user_id");
			db_execute("insert into settings_tree (treeitemid,userid,status) values ($branch_id,$user_id,$hide)");
		}
	//}
}

/* if auth is enabled, get some basic info about this user so we know what they have
rights to */
if ($config["global_auth"]["value"] == "on") {
	$user = db_fetch_row("select GraphPolicy,ShowTree from auth_users where id=$user_id");
	
	if (sizeof($user) == 0) {
		print "<strong><font size=\"+1\" color=\"FF0000\">CANNOT FIND USER!</font></strong>"; exit;
	}else{
		$show_tree_view = $user[ShowTree];
		//$show_list_view = mysql_result($sql_id_user, 0, "showlist");
		//$show_preview_view = mysql_result($sql_id_user, 0, "showpreview");
	}
}else{
	/* if auth is turned off; obviously they have rights */
	$show_tree_view = "on";
	//$show_list_view = "on";
	//$show_preview_view = "on";
}

switch ($action) {
	case 'tree':
		if ($show_tree_view != "on") {
			print "<strong><font size=\"+1\" color=\"FF0000\">YOU DO NOT HAVE RIGHTS FOR TREE VIEW</font></strong>"; exit;
		}
		
		include_once ('include/form.php');
		include_once ('include/tree_functions.php');
		
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
			
			/* user is allowed to view this graph hierarchy; so lets move and create
			the SQL string that will show only the graphs that the user has rights to view
			in this tree */
			
			if ($config["graph_policy"]["auth"] == "1") {
				$sql_where = "and ag.userid is null";
			}elseif ($config["graph_policy"]["auth"] == "2") {
				$sql_where = "and (ag.userid is not null or h.type=\\\"Heading\\\")";
			}
			
			$array_tree["options"]["sql_string"] = 'select 
				h.ID,h.GraphID,h.RRAID,h.Type,h.Title,h.OrderKey,
				g.Title as gtitle,
				st.Status,
				ag.graphid as aggraphid, ag.userid as aguserid 
				from graph_hierarchy_items h 
				left join rrd_graph g on h.graphid=g.id 
				left join settings_tree st on (h.id=st.treeitemid and st.userid=' . $user_id . ') 
				left join auth_graph ag on (g.id=ag.graphid and ag.userid=' . $user_id . ') 
				where h.treeid=' . $tree_id . '
				and OrderKey like \"$search_key%\"
				' . $sql_where . '
				order by h.OrderKey';
		}else{
			$array_tree["options"]["sql_string"] = 'select 
				h.ID,h.GraphID,h.RRAID,h.Type,h.Title,h.OrderKey,
				g.title as gtitle,
				r.name as rname,
				st.Status 
				from graph_hierarchy_items h 
				left join rrd_graph g on h.graphid=g.id 
				left join rrd_rra r on h.rraid=r.id 
				left join settings_tree st on (h.id=st.treeitemid and st.userid=' . $user_id . ') 
				where h.treeid=' . $tree_id . ' 
				and OrderKey like \"$search_key%\"
				order by h.OrderKey';
		}
		
		$array_tree["options"]["indent"] = "- ";
		$array_tree["options"]["start_branch"] = $start_branch;
		$array_tree["options"]["alternating_row_colors"] = false;
		$array_tree["options"]["tree_id"] = $tree_id;
		$array_tree["options"]["remove_action"] = '';
		$array_tree["options"]["create_margin"] = true;
		$array_tree["options"]["use_expand_contract"] = true;
		$array_tree["options"]["sql_type_column"] = "Type";
		
		$array_tree["options"]["show_item"] = '<a href=\"graph_view.php?action=tree&tree_id=' .
			$tree_id . '&start_branch=' . $start_branch . '&hide=0&branch_id=" . 
			$leaf[ID] . "\"><img src=\"images/show.gif\" border=\"0\"></a>';
		$array_tree["options"]["hide_item"] = '<a href=\"graph_view.php?action=tree&tree_id=' .
			$tree_id . '&start_branch=' . $start_branch . '&hide=1&branch_id=" . 
			$leaf[ID] . "\"><img src=\"images/hide.gif\" border=\"0\"></a>';
		
		$array_tree["item_can_have_children"]["Heading"] = true;
		$array_tree["item_td_code"]["Heading"] = 'bgcolor=\"#$colors[panel]\" colspan=\"' . $array_settings["preview"]["columnnumber"] . '\"';
		$array_tree["item_action"]["Heading"] = '<strong><a href=\"graph_view.php?action=tree&tree_id=' . $tree_id . '&start_branch=" . $leaf[ID] . "\">" . $leaf[Title] . "</a></strong>';
		
		$array_tree["item_can_have_children"]["Graph"] = false;
		
		switch ($array_settings["hierarchical"]["viewtype"]) {
			case "1":
				$array_tree["item_action"]["Graph"] = '<a href=\"graph.php?graphid=" . $leaf[GraphID] . 
					"&rraid=all\"><img align=\"middle\" src=\"graph_image.php?graphid=" . $leaf[GraphID] . 
					"&rraid=" . $leaf[RRAID] . "&graph_start=-' . 
					$array_settings["preview"]["timespan"] . '&graph_height=' . 
					$array_settings["preview"]["height"] . '&graph_width=' . $array_settings["preview"]["width"] . 
					'&graph_nolegend=true\" border=\"0\" alt=\"" . $leaf[gtitle] . "\"></a>';
				$array_tree["item_columns"]["Graph"] = $array_settings["preview"]["columnnumber"];
				
				break;
			case "2":
				$array_tree["item_action"]["Graph"] = '<a href=\"graph.php?graphid=" . $leaf[GraphID] . 
					"&rraid=all\">" . $leaf[gtitle] . "</a>';
				
				break;
		}
		
		DrawMatrixRowBegin();
			DrawMatrixCustom("<td colspan=\"2\">");
				DrawMatrixCustom("<strong><a href=\"graph_view.php?action=tree&tree_id=$tree_id\">[root]</a> - $tree_name</strong><br><br>");
			DrawMatrixCustom("</td>");
		DrawMatrixRowEnd();
		
		GrowTree($array_tree);
		
		print '</td></tr>';
		DrawMatrixTableEnd();
		
		break;
}

include_once ("include/bottom_footer.php");