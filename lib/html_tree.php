<?

function grow_graph_tree($tree_id, $starting_branch, $user_id, $options) {
	include_once ('include/form.php');
	include_once ('include/tree_functions.php');
	global $colors, $config, $array_settings;
	
	$options[num_columns] = 2;
	$options[use_expand_contract] = true;
	
	/* get the "starting leaf" if the user clicked on a specific branch */
	if (($starting_branch != "") && ($starting_branch != "0")) {
		$search_key = preg_replace("/0+$/","",db_fetch_cell("select order_key from graph_tree_view_items where id=$starting_branch"));
	}
	
	/* find out what type of policy is in effect for this user: ALLOW or DENY, and
	set up the appropriate SQL WHERE clause appropriatly */
	if ($config["graph_policy"]["auth"] == "1") {
		$sql_where = "and ag.userid is null";
	}elseif ($config["graph_policy"]["auth"] == "2") {
		$sql_where = "and (ag.userid is not null or h.type=\\\"Heading\\\")";
	}
	
	if ($config["global_auth"]["value"] == "on") {
		$hierarchy = db_fetch_assoc("select 
			h.id,h.graph_id,h.rra_id,h.type,h.title,h.order_key,
			g.Title as gtitle,
			st.Status,
			ag.graphid as aggraphid, ag.userid as aguserid 
			from graph_tree_view_items h 
			left join rrd_graph g on h.graph_id=g.id 
			left join settings_tree st on (h.id=st.treeitemid and st.userid=$user_id)
			left join auth_graph ag on (g.id=ag.graphid and ag.userid=$user_id) 
			where h.tree_id=$tree_id
			and h.order_key like '$search_key%'
			$sql_where
			order by h.order_key");
	}else{
		$hierarchy = db_fetch_assoc("select 
			h.id,h.graph_id,h.rra_id,h.type,h.title,h.order_key,
			g.title as gtitle,
			r.name as rname,
			st.Status 
			from graph_tree_view_items h 
			left join rrd_graph g on h.graph_id=g.id 
			left join rrd_rra r on h.rraid=r.id 
			left join settings_tree st on (h.id=st.treeitemid and st.userid=$user_id) 
			where h.tree_id=$tree_id 
			and h.order_key like '$search_key%'
			order by h.order_key");
	}
	
	$rows = sizeof($hierarchy); $i = 0;
	
	/* make sure this tree has at least one leaf */
	if (sizeof($hierarchy) == 0) { return 0; }
	
	/* loop through all items */
	foreach ($hierarchy as $leaf) {
		$tier = tree_tier($leaf[order_key], 2);
		
		/* check to see if we are currently "hiding" items. If so, check and see if we
		should stop */
		if (isset($array_values[hide][start_branch]) == true) {
			if ($array_values[hide][start_branch] >= $tier) {
				unset($array_values[hide][start_branch]);
			}
		}
		
		/* if we are currently hiding items; hide. */
		if (isset($array_values[hide][start_branch]) == true) {
			continue;
		}
		
		/* set up variables used in this section */
		unset($start_tr, $end_tr, $start_nested_table, $end_nested_table, $td_indent, $indent);
		
		/* put the current column type in a variable for easy access (c) :) */
		$current_column_type = $leaf[type];
		
		/* get the next type (if we're not the last item) to see when to end a row on an
		'off' column */
		if (($i+2) <= $rows) {
			$next_column_type = $hierarchy[$i+1][type];
		}
		
		/* figure out if the user wants a margin; if this item can have children,
		and if it can draw a '+' */
		if ($current_column_type == "Heading") {
			if ($options[use_expand_contract] == true) {
				if ($leaf[Status] == "1") {
					$show_hide_item = '<a href="graph_view.php?action=tree&tree_id=' .
						$tree_id . '&start_branch=' . $start_branch . '&hide=0&branch_id=" .
						' . $leaf[ID] . '"><img src="images/show.gif" border="0"></a>';
				}else{
					$show_hide_item = '<a href="graph_view.php?action=tree&tree_id=' .
						$tree_id . '&start_branch=' . $start_branch . '&hide=1&branch_id=" . 
						' . $leaf[ID] . '"><img src="images/hide.gif" border="0"></a>';
				}
				
				$html_margin = '<td bgcolor="#' . $colors[panel] . '" width="1%">' . $show_hide_item . '</td>';
			}
		}else{
			$html_margin = '<td bgcolor="' . $colors[panel] . '" align="center" width="1%"></td>';
		}
		
		/* if the 'current_column_type' has changed 1) set the column #1 to 1
		2) start a new row */
		if ($array_values[last_tree_type] != $current_column_type) {
			/* reset counter when type changes */
			$array_values[current_column] = 0;
		}
		
		$array_values[current_column]++;
		
		/* only display margin on column #1 */
		if ($array_values[current_column] == 1) {
			$td_indent = "&nbsp;";
			
			$start_tr =  "<tr>";
			
			if ($options[num_columns] > 1) {
				$start_nested_table = "<td><table><tr>";
			}
			
			/* create the HTML for the image indent */
			$indent = '<img src="images/gray_line.gif" width="' . ($tier * 20) . '" height="1" align="middle">&nbsp;';
		}else{
			$html_margin = "";
			$td_indent = "&nbsp;";
		}
		
		/* a lot of column logic... */
		if ($current_column_type == "Heading") {
			/* there is only ever one column in a header... */
			$start_nested_table = "";
			$start_tr =  "<tr>";
			$end_tr = "</tr>";
			$end_nested_table = "";
			$array_values[current_column] = 0;
		}elseif ($array_values[current_column] == $options[num_columns]) {
			/* this row is done; clean up and move on */
			$array_values[current_column] = 0;
			
			$end_tr = "</tr>";
			
			if ($options[num_columns] > 1) {
				$end_nested_table = "</tr></table></td>";
			}
		}elseif (($i+1) >= $rows) {
			/* if we are "out of graphs"; go on to the next row */
			$end_tr = "</tr>";
			$end_nested_table = "</tr></table></td>";
		}elseif ($next_column_type != $current_column_type) {
			/* if it is not the end of the row; but our forcasting powers tell us that
			it is the end of the row; end it */
			$end_tr = "</tr>";
			$end_nested_table = "</tr></table></td>";
		}
		
		if ($current_column_type == "Graph") {
			/* What happens when the user clicks the graph? */
			$html_item = $indent . '<a href="graph.php?graphid=' . $leaf[graph_id] . '&rraid=all"><img align="middle" src="graph_image.php?graphid=' . $leaf[graph_id] . '&rraid=' . $leaf[rra_id] . '&graph_start=-' . $array_settings[preview][timespan] . '&graph_height=' . $array_settings[preview][height] . '&graph_width=' . $array_settings[preview][width] . '&graph_nolegend=true" border="0" alt="' . $leaf[gtitle] . '"></a>';
			$html_td_item = 'bgcolor="#' . $colors[light] . '"';
		}elseif ($current_column_type == "Heading") {
			/* What happens when the user clicks the header? */
			$html_item = '<strong><a href="graph_view.php?action=tree&tree_id=' . $tree_id . '&start_branch=' . $leaf[id] . '">' . $leaf[title] . '</a></strong>';
			$html_td_item = 'bgcolor="#' . $colors[panel] . '" colspan="' . $options[num_columns] . '"';
		}
		
		/* draw the main item */
		print "$start_tr$html_margin$start_nested_table<td $html_td_item>$td_indent$html_item</td>";
		print "$end_nested_table$end_tr";
		
		$array_values[last_tree_type] = $current_column_type;
		
		/* if do the hide thing if config says we can */
		if ($options[use_expand_contract] == true) {
			if ($leaf[Status] == "1") { /* hide all chilren */
				/* initiate hide until we're done with this parent */
				$array_values[hide][start_branch] = $tier;
			}
		}
		
		$i++;
	}
}


function grow_graph_tree_2($tree_id, $start_branch, $user_id, $options) {
#  GrowTree($tree_id,$start_branch, $options = array()) {
    include_once ('include/form.php');
    include_once ('include/tree_functions.php');
    global $config,$colors,$array_settings,$PHP_SELF,$args;
	
    $options[num_columns] = 2;
    $options[use_expand_contract] = true;
	
    $vbar_width = 20;
    
    /* get the "starting leaf" if the user clicked on a specific branch */
    if (($start_branch != "") && ($start_branch != "0")) {
	$search_key = preg_replace("/0+$/","",db_fetch_cell("select order_key from graph_tree_view_items where id=$start_branch"));
    }
#    print "start_branch = '$start_branch', search_key = '$search_key'<BR>\n";
 
    $treeinfo = db_fetch_row("SELECT * FROM graph_tree_view WHERE id = $args[tree_id]");
#    if ($treeinfo[Owner] == '' || ($treeinfo[Owner] != 0 && $treeinfo[Owner] != $user_id)) {
#	print "<P ALIGN=CENTER><strong><font size=\"+1\" color=\"FF0000\">GRAPH TREE IS NOT PUBLIC AND DOESN'T BELONG TO YOU.<BR>ACCESS DENIED!</font></strong></P>\n";
#	exit;
#   }

#    print "<P><strong><A HREF='graph_view.php?action=tree&tree_id=$tree_id'>$treeinfo[Title]</A></strong></P>\n";
    
    	if ($config["global_auth"]["value"] == "on") {
		$sql = "select 
			h.id,h.graph_id,h.rra_id,h.type,h.title,h.order_key,
			g.Title as gtitle,
			st.Status as status,
			ag.graphid as aggraphid, ag.userid as aguserid 
			from graph_tree_view_items h 
			left join rrd_graph g on h.graph_id=g.id 
			left join settings_tree st on (h.id=st.treeitemid and st.userid=$user_id)
			left join auth_graph ag on (g.id=ag.graphid and ag.userid=$user_id) 
			where h.tree_id=$tree_id
			and h.order_key like '$search_key%'
			$sql_where
			order by h.order_key";
	}else{
		$sql = "select 
			h.id,h.graph_id,h.rra_id,h.type,h.title,h.order_key,
			g.title as gtitle,
			r.name as rname,
			st.Status as status
			from graph_tree_view_items h 
			left join rrd_graph g on h.graph_id=g.id 
			left join rrd_rra r on h.rraid=r.id 
			left join settings_tree st on (h.id=st.treeitemid and st.userid=$user_id) 
			where h.tree_id=$tree_id 
			and h.order_key like '$search_key%'
			order by h.order_key";
	}

#    print "$sql<BR>\n";
    $heirarchy = db_fetch_assoc($sql);
#    $tmp = db_fetch_assoc("SELECT * FROM settings_tree,graph_viewing 
#			    WHERE settings_tree.TreeItemID = graph_viewing.ID AND UserID = $user_id");
    if (sizeof($heirarchy) > 0) {
	foreach ($heirarchy as $set) {
	    $tree_settings[$set[TreeItemID]] = $set[status];
	    if ($set[status] == 1) {
		$hide[preg_replace("/0+$/","",$set[order_key])] = 1; 
#		print "Adding Hide for '".preg_replace("/0+$/","",$set[order_key])."'<BR>\n";
	    }
	}
   }
#    print "Total of ".sizeof($hide)." hides<BR>\n";

    $search_key = preg_replace("/0+$/","",$start_branch);

#    $sql = "SELECT *,graph_viewing.Title HTitle,rrd_graph.Title GTitle, graph_viewing.ID VID
#	     FROM graph_viewing 
#	     LEFT JOIN rrd_graph ON graph_viewing.GraphID = rrd_graph.ID 
#	     WHERE OrderKey LIKE '$search_key%' AND TreeID = $tree_id ORDER BY OrderKey";
#    print "$sql<BR>\n";
#    $heirarchy = db_fetch_assoc($sql);
    
    for ($i = (sizeof($heirarchy) - 1); $i > 0; --$i) {
	$leaf = $heirarchy[$i];
	$tier = tree_tier($leaf[order_key], 2);
	if ($tier > 1) {
	    $parent_key = str_pad(substr($leaf[order_key],0,(($tier - 1) * 2) ),60,'0',STR_PAD_RIGHT);
	    if (! isset($counted_graphs[$parent_key])) {
		++$num_children[$parent_key];
		++$counted_graphs[$parent_key];
#		print "graph_id = '$leaf[graph_id]'.  Incrementing Parent '$parent_key', counted_graphs = '$counted_graphs[$parent_key]'.<BR>\n";
	    }
	    if (!$leaf[graph_id]) {
#		print "graph_id = '$leaf[graph_id]'.  Incrementing parent '$parent_key'.<BR>\n";
		++$num_children[$parent_key];
		++$num_children[$parent_key];
	    }
	    
	}
	if ($tier > $max_tier) { $max_tier = $tier; }
    }

    $indent = "<img src=\"images/gray_line.gif\" width=\"".($level * $vbar_width)."\" height=\"1\" align=\"middle\">&nbsp;";
    
    print "<!-- <P>Building Heirarchy w/ ".sizeof($heirarchy)." leaves</P>  -->\n";
    
    DrawMatrixTableBegin();
    if (sizeof($heirarchy) > 0) {
	foreach ($heirarchy as $leaf) {
	    $no_children = 0;
	    if (sizeof($hide) > 0) {
		foreach (array_keys($hide) as $key_root) { 
		    if (preg_match("/^$key_root/",$leaf[order_key])) {
			if (preg_match("/^".$key_root."00/",$leaf[order_key])) {
			    $no_children = 1; 
			} else {
			    $skip_entry = 1;
			}
		    }
		}
	    }
	    if ($skip_entry) {
		$skip_entry = 0;
		continue;
	    }
	    $tier = tree_tier($leaf[order_key], 2);
#	    if ($tier == 1 && ) { continue; }
	    $current_leaf_type = $leaf[graph_id] ? "graph" : "heading";
	    
	    if ($current_leaf_type == 'heading') {
		
		if ($heading_ct > 0) {
		    if ($graph_ct % 2 != 0) { print "</tr>\n"; }
		    print "</table></td>\n";
		}
		$colspan = (($max_tier - $tier) * 2);
		$rowspan = $num_children[$leaf[order_key]];
		print "<tr>\n";
		if ($options[use_expand_contract]) {
		    if ($hide[preg_replace("/0+$/","",$leaf[order_key])] == 1) {
			$other_status = '0';
			$ec_icon = 'show';
		    } else {
			$other_status = '1';
			$ec_icon =  'hide';
			++$heading_ct;
		    }
		    print "<td bgcolor=\"$colors[panel]\" align=\"center\" width=\"1\"><a
			    href='$PHP_SELF?action=tree&tree_id=$tree_id&start_branch=$start_branch&hide=$other_status&branch_id=$leaf[id]'><img
			    src='images/$ec_icon.gif' border='0'></a></td>\n";
		    
		} else {
		    print "<td bgcolor=\"$colors[panel]\" width=\"1\">$indent</td>\n";
		}
		print "<td bgcolor=\"$colors[panel]\" colspan=$colspan NOWRAP><strong><a
			href='?tree_id=$tree_id&start_branch=$leaf[id]'>$leaf[title]</a></strong></td>\n";
		print "</tr>";
		if ($num_children[$leaf[order_key]] > 0 && ! $no_children) { 
		    print "<tr>\n<td bgcolor=\"$colors[panel]\" width=\"1%\" rowspan=$rowspan>&nbsp;</td>
			    <td colspan=$colspan><table border=0><tr>\n"; 
		}
		$graph_ct = 0;
	    } else {
		++$graph_ct;
		switch ($array_settings["hierarchical"]["viewtype"]) {
		 case "1":
		    print "<td><a href='graph.php?graphid=$leaf[graph_id]&rraid=all'><img align='middle' 
			    src='graph_image.php?graphid=$leaf[graph_id]&rraid=$leaf[rra_id]&graph_start=-".$array_settings["preview"]["timespan"].'&graph_height='.
		      $array_settings["preview"]["height"].'&graph_width='.$array_settings["preview"]["width"] ."&graph_nolegend=true' border='0' alt='$leaf[Title]'></a><td>\n";
		    break;
		 case "2":
		    print "<td><a href='graph.php?graphid=$leaf[graph_id]&rraid=all'>$leaf[title]</a></td>";
		    break;
		}
		if ($graph_ct % 2 == 0) { print "</tr><tr>\n"; }
	    }
	}
    }
    DrawMatrixTableEnd();

}

?>
