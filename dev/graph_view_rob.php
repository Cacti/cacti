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
$section = "View Graphs"; 
#$guest_account = true; 
include ('include/auth.php');
include_once ("include/rrd_functions.php");
include_once ("include/functions.php");
include ("include/top_graph_header.php");

$user_id = GetCurrentUserID($HTTP_COOKIE_VARS["cactilogin"], $config["guest_user"]["value"]);

if (isset($hide) == true) {
    /* find out if the current user has rights here */
    $gs = db_fetch_cell("select GraphSettings from auth_users where id=$user_id");
    
    /* only update expand/contract info is this user has writes to keep their own settings */
    if ($gs >  0) {
	if ($gs == "on") {
	    db_execute("delete from settings_tree where treeitemid=$branch_id and userid=$user_id");
	    db_execute("insert into settings_tree (treeitemid,userid,status) values ($branch_id,$user_id,$hide)");
	}
    }
}

/* if auth is enabled, get some basic info about this user so we know what they have
 rights to */
if ($config["global_auth"]["value"] == "on") {
    $user = db_fetch_row("select GraphPolicy,ShowTree,ShowList,ShowPreview from auth_users where id=$user_id");
    
    if (sizeof($user) <= 0) {
	print "<strong><font size='+1' color='FF0000'>CANNOT FIND USER!</font></strong>"; exit;
    } else {
	$show_tree_view = $user[ShowTree];
	$show_list_view = $user[ShowList];
	$show_preview_view = $user[ShowPreview];
    }
}else{
    /* if auth is turned off; obviously they have rights */
    $show_tree_view = "on";
    $show_list_view = "on";
    $show_preview_view = "on";
}

switch ($action) {
 case 'tree':
    if ($show_tree_view != "on") {
	print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR TREE VIEW</font></strong>"; exit;
    }
    
    include_once ('include/form.php');
    include_once ('include/tree_functions.php');
    
    DrawMatrixTableBegin("97%");
    
    $array_tree["options"]["sql_type_column"] = "type";
    
    if (!(isset($tree_id))) { $tree_id = 0; }
    
    /* if cacti's builtin authentication is turned on then make sure to take 
     graph permissions into account here. if a user does not have rights to a 
     particular graph; do not show it. they will get an access denied message
     if they try and view the graph directly. */
    if ($config["global_auth"]["value"] == "on") {
	/* take tree permissions into account here, if the user does not have permission
	 give an "access denied" message */
	$id = db_fetch_cell("select UserID from auth_graph_hierarchy where hierarchyid=$tree_id and userid=" . GetCurrentUserID($HTTP_COOKIE_VARS["cactilogin"],$config["guest_user"]["value"]));
	
	if ($config["graph_policy"]["auth"] == "1") {
	    if ($id > 0)  { $access_denied = true; }
	} elseif ($config["graph_policy"]["auth"] == "2") {
	    if ($id == 0) { $access_denied = true; }
	}
	
	if ($access_denied == true) {
	    print "<strong><font size='+1' color='FF0000'>ACCESS DENIED</font></strong>"; exit;
	}
	
	/* user is allowed to view this graph hierarchy; so lets move and create
	 the SQL string that will show only the graphs that the user has rights to view
	 in this tree */
	if ($config["graph_policy"]["auth"] == "1") {
	    $sql_where = "where ag.userid is null";
	}elseif ($config["graph_policy"]["auth"] == "2") {
	    $sql_where = "where (ag.userid is not null or h.type=\\\"Heading\\\")";
	}
	
	$array_tree["options"]["sql_string"] = "select 
						 h.ID,h.GraphID,h.RRAID,h.Type,h.Title,h.Parent,
						 g.title as GTitle,
						 st.Status,
						 ag.GraphID as AGGraphID, ag.userid as AGUserID
						 from graph_hierarchy_items h 
						 left join rrd_graph g on h.graphid=g.id 
						 left join settings_tree st on (h.id=st.treeitemid and st.userid=$user_id)
						   left join auth_graph ag on (g.id=ag.graphid and ag.userid=$user_id) 
						     $sql_where
						 and h.parent=$branch
						 and h.treeid=$tree_id
						 order by h.sequence";
    } else {
	$array_tree["options"]["sql_string"] = "select 
						 h.ID,h.GraphID,h.RRAID,h.Type,h.Title,h.Parent,
						 g.title as GTitle,
						 r.name as RName,
						 st.Status 
						 from graph_hierarchy_items h 
						 left join rrd_graph g on h.graphid=g.id 
						 left join rrd_rra r on h.rraid=r.id 
						 left join settings_tree st on (h.id=st.treeitemid and st.userid=$user_id)
						   where h.parent=$branch
						 and h.treeid=$tree_id 
						 order by h.sequence";
    }
		
    /* this SQL string is used in case a user clicks on a branch in the tree; this way
     cacti will show the actual branch they clicked on in addition to its children */
    if ($start_branch != "") {
	$array_tree["options"]["sql_header_string"] = "select 
							h.ID,h.GraphID,h.RRAID,h.Type,h.Title,h.Parent,
							g.title as GTitle,
							r.name as RName,
							st.Status 
							from graph_hierarchy_items h 
							left join rrd_graph g on h.graphid=g.id 
							left join rrd_rra r on h.rraid=r.id 
							left join settings_tree st on (h.id=st.treeitemid and st.userid=$user_id)
							  where h.id=$branch 
							and h.treeid=$tree_id 
							order by h.sequence";
    }
		
    $array_tree["options"]["indent"] = "- ";
    $array_tree["options"]["start_branch"] = $start_branch;
    $array_tree["options"]["alternating_row_colors"] = false;
    $array_tree["options"]["tree_id"] = $tree_id;
    $array_tree["options"]["remove_action"] = '';
    $array_tree["options"]["create_margin"] = true;
    $array_tree["options"]["use_expand_contract"] = true;
    
    $array_tree["options"]["show_item"] = "<a href='graph_view.php?action=tree&tree_id=$tree_id&start_branch=$start_branch&hide=0&branch_id=$branch'><img src='images/show.gif' border='0'></a>";
    $array_tree["options"]["hide_item"] = "<a href='graph_view.php?action=tree&tree_id=$tree_id&start_branch=$start_branch&hide=1&branch_id=$branch'><img src='images/hide.gif' border='0'></a>";
				    
    $array_tree["item_can_have_children"]["Heading"] = true;
    $array_tree["item_td_code"]["Heading"] = "bgcolor='#$colors[panel]' colspan='" . $array_settings[column_number] . "'";
#    $array_tree["item_action"]["Heading"] = "<strong><a href='graph_view.php?action=tree&tree_id=$tree_id&start_branch=$branch'>" . mysql_result($sql_id, $i, "title") . "</a></strong>";
    
    $array_tree["item_can_have_children"]["Graph"] = false;
    
    switch ($array_settings[view_type]) {
     case "1":
#	$array_tree["item_action"]["Graph"] = "<a href='graph.php?graphid=" . mysql_result($sql_id, $i, "graphid") . 
#	  "&rraid=all'><img align='middle' src='graph_image.php?graphid=" . mysql_result($sql_id, $i, "graphid") . 
#	  "&rraid=" . mysql_result($sql_id, $i, "rraid") . "&graph_start=-" . 
#	  $array_settings["preview"]["timespan"] . "&graph_height=" . 
#	  $array_settings["preview"]["height"] . "&graph_width=" . $array_settings["preview"]["width"] . 
#	  "&graph_nolegend='true' border='0' alt='" . mysql_result($sql_id, $i, "gtitle") . "'></a>";
	$array_tree["item_columns"]["Graph"] = $array_settings[column_number];
	
	break;
     case "2":
	$array_tree["item_action"]["Graph"] = "<a href='graph.php?graphid=" . mysql_result($sql_id, $i, "graphid") . 
	  "&rraid=all'>" . mysql_result($sql_id, $i, "gtitle") . "</a>";
								  
	break;
    }
    
    DrawMatrixRowBegin();
    DrawMatrixCustom("<td colspan='2'>");
    DrawMatrixCustom("<strong><a href='graph_view.php?action=tree&tree_id=$tree_id'>[root]</a> - $tree_name</strong><br><br>");
    DrawMatrixCustom("</td>");
    DrawMatrixRowEnd();
    
    print "<UL>\n";
    foreach (array_keys($array_tree) as $key) {
	if (is_array($array_tree[$key])) {
	    print "<UL>\n";
	    foreach (array_keys($array_tree[$key]) as $key2) {
		print "<LI>[$key][$key2] = '".$array_tree[$key][$key2]."'<BR>\n";
	    }
	    print "</UL>\n";
	} else {
	    print "<LI>[$key] = '$array_tree[$key]'<BR>\n";
	}
    }
    print "</UL>\n";
    
   GrowTree($array_tree);
    
    print '</td></tr>';
    DrawMatrixTableEnd();
    
    break;
 case 'preview':
    if ($show_preview_view != "on") {
	print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR PREVIEW VIEW</font></strong>"; exit;
    }
    
    /* graph permissions */
    if ($config["global_auth"]["value"] == "on") {
	if ($config["graph_policy"]["auth"] == "1") {
	    $sql_where = "where ag.userid is null";
	}elseif ($config["graph_policy"]["auth"] == "2") {
	    $sql_where = "where ag.userid is not null";
	}
	
	$graphs = db_fetch_assoc("select 
				   g.ID,g.Title 
				   from rrd_graph g 
				   left join auth_graph ag on (g.id=ag.graphid and ag.userid=$user_id) 
				     $sql_where
				   order by g.title");
    }else{
	$graphs = db_fetch_assoc("select 
				   ID,Title 
				   from rrd_graph 
				   order by title");
    }
    
    
    print "<tr><td bgcolor='$colors[light]' width='1%' rowspan='99999'></td>\n";
    if (sizeof($graphs) > 0) {
	foreach ($graphs as $graph) {
	    print "<td width='25%'><a href='graph.php?rraid=all&graphid=$graph[ID]'><img src='graph_image.php?graphid=$graph[ID]&rraid=".$array_settings[rra];?>&graph_start=-<?print $array_settings["preview"]["timespan"];?>&graph_height=<?print $array_settings["preview"]["height"]."&graph_width=".$array_settings["preview"]["width"]."&graph_nolegend=true' border='0' alt='$graph[Title]'></a></td>";
	    $k++;
	    if ($k == $array_settings[column_number]) {
		$k = 0;
		print "</tr><tr height='10'><td>&nbsp;</td></tr><tr>\n";
	    }
	}	
    }
    print "</tr>\n";
    
    break;
 case 'list':
    if ($show_list_view != "on") {
	print "<strong><font size='+1' color='FF0000'>YOU DO NOT HAVE RIGHTS FOR LIST VIEW</font></strong>"; exit;
    }
    
    include_once ("include/form.php");
    
    /* graph permissions */
    if ($config["global_auth"]["value"] == "on") {
	if ($config["graph_policy"]["auth"] == "1") {
	    $sql_where = "where ag.userid is null";
	} elseif ($config["graph_policy"]["auth"] == "2") {
	    $sql_where = "where ag.userid is not null";
	}
	
	$graphs = db_fetch_assoc("select 
				   g.ID,g.Title 
				   from rrd_graph g 
				   left join auth_graph ag on (g.id=ag.graphid and ag.userid=$user_id) 
				     $sql_where
				   order by g.title");
    } else {
	$graphs = db_fetch_assoc("select 
				   ID,Title 
				   from rrd_graph 
				   order by title");
    }
    
    
    $rra_list = db_fetch_assoc("select id,name from rrd_rra order by id");
    
    print "<table width='97%'>";
    print "<tr>";
    if (sizeof($graphs) > 0) {		
	foreach ($graphs as $graph) {
	    switch ($array_settings[list_view_type]) {
	     case "1":
		print "<td width='25%'><strong>$graph[Title] [<a href='graph.php?graphid=$graph[ID]&rraid=all'>all</a>]</strong><br>\n";
		if (sizeof($rra_list) > 0) {
		    foreach ($rra_list as $rra) {
			print "<a href='graph.php?graphid=$graph[ID]&rraid=$rra[ID]'><?print $rra[Name];?></a><br>\n";
		    }
		}
		print "</td>\n";
		$k++;
		
		if ($k==4) {
		    $k = 0;
		    print "</tr><tr height='10'><td>&nbsp;</td></tr><tr>\n";
		}
		
		break;
	     case "2"	:
		print "<td><strong><a href='graph.php?graphid=$graph[ID]&rraid=all'>$graph[Title]</a></strong></td></tr><tr>\n";
		break;
	    }
	}	
	print "</tr></table>\n";
	
	break;
    }
}
include_once ("include/bottom_footer.php");
