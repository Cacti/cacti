<?

function grow_graph_tree($tree_id, $start_branch, $user_id, $options) {
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
    
    ## This code makes sure that the tree you're trying to show is either a public tree or owned by you.
#    if ($treeinfo[Owner] == '' || ($treeinfo[Owner] != 0 && $treeinfo[Owner] != $user_id)) {
#	print "<P ALIGN=CENTER><strong><font size=\"+1\" color=\"FF0000\">GRAPH TREE IS NOT PUBLIC AND DOESN'T BELONG TO YOU.<BR>ACCESS DENIED!</font></strong></P>\n";
#	exit;
#   }

#    print "<P><strong><A HREF='graph_view.php?action=tree&tree_id=$tree_id'>$treeinfo[Title]</A></strong></P>\n";
    
    
    /*
    	if (read_config_option("global_auth") == "on") {
		$sql = "select 
			h.id,h.graph_id,h.rra_id,h.type,h.title,h.order_key,
			g.Title as gtitle,
			st.Status as status,
			ag.graphid as aggraphid, ag.userid as aguserid 
			from graph_tree_view_items h 
			left join rrd_graph g on h.graph_id=g.id 
			left join settings_viewing_tree st on (h.id=st.treeitemid and st.userid=$user_id)
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
			left join settings_viewing_tree st on (h.id=st.treeitemid and st.userid=$user_id) 
			where h.tree_id=$tree_id 
			and h.order_key like '$search_key%'
			order by h.order_key";
	}
*/
#    print "$sql<BR>\n";
    $heirarchy = db_fetch_assoc("select
    	graph_tree_view_items.title,
	graph_tree_view_items.graph_id,
	graph_tree_view_items.rra_id,
	graph_tree_view_items.order_key,
	graph_templates_graph.title as graph_title
	from graph_tree_view_items
	left join graph_templates_graph on graph_tree_view_items.graph_id=graph_templates_graph.id
	where graph_tree_view_items.tree_id=$tree_id
	and graph_tree_view_items.order_key like '$search_key%'
	order by graph_tree_view_items.order_key");


    $search_key = preg_replace("/0+$/","",$start_branch);
    
    ##  First off, we walk the tree from the top to the root.  We do it in that order so that we 
    for ($i = (sizeof($heirarchy) - 1); $i > 0; --$i) {
	$leaf = $heirarchy[$i];
	
	## While we're walking the tree, let's go ahead and set 'hide' flags for any branches that should be hidden (status in settings_viewing_tree == 1)
	if ($leaf[status] == 1) {
	    $hide[$leaf[order_key]] = 1; 
		print "Adding Hide for '$leaf[order_key]'<BR>\n";
	}
	
	$tier = tree_tier($leaf[order_key], 2);
	
	##  If there's a graph_id, the leaf is a graph, not a heading, so we increment the parent's num_graphs
	if ($leaf[graph_id]) {
	    $parent_key = str_pad(substr($leaf[order_key],0,(($tier - 1) * 2) ),60,'0',STR_PAD_RIGHT);
	    ++$num_graphs[$parent_key];
#	    print "graph_id = '$leaf[graph_id]'.  Incrementing parent '$parent_key'.<BR>\n";
	}
	
	##  We also need the max_tier to do colspans so we do this to get it:
	if ($tier > $max_tier) { $max_tier = $tier; }
    }


    ##  Now that we know how many graphs each heading has and whether it's supposed to be hidden, we walk the tree again from top to root to figure 
    ##  out how many rows each vertical bar should span.
    for ($i = (sizeof($heirarchy) - 1); $i >= 0; --$i) {
	$leaf = $heirarchy[$i];
	$tier = tree_tier($leaf[order_key], 2);
	
	##  Step through the hidden headings to see which entries to skip.
	if (! $hide[$leaf[order_key]]) {
	    for ($j = 1; $j < $tier; ++$j) {
		$parent_key = str_pad(substr($leaf[order_key],0,($j * 2) ),60,'0',STR_PAD_RIGHT);
		if ($hide[$parent_key]) { 
		    $skip[$leaf[order_key]] = 1;
		    break;
		}
	    }
	}
	
	if (! $skip[$leaf[order_key]]) {
	    if (!$leaf[graph_id]) {
#	        print "Checking header $leaf[id], $leaf[order_key], num_graphs = '".$num_graphs[$leaf[order_key]]."'<BR>\n";
		if (! $hide[$leaf[order_key]]) {
		    if ($num_graphs[$leaf[order_key]] > 0) {
			++$rowspans[$leaf[order_key]]; 
#		    print "num_graphs[$leaf[order_key]] > 0 - incrementing rowspans[$leaf[order_key]]<BR>\n";
		    }
		}
		$j = $tier - 1;
		$parent_key = str_pad(substr($leaf[order_key],0,$j * 2 ),60,'0',STR_PAD_RIGHT);
		$rowspans[$parent_key] += ($rowspans[$leaf[order_key]] + 1);
#	    print "Adding ".($rowspans[$leaf[order_key]] + 1)." to rowspans[$parent_key] for $leaf[id]<BR>\n";
	    }
	}
    }
    
    $indent = "<img src=\"images/gray_line.gif\" width=\"".($level * $vbar_width)."\" height=\"1\" align=\"middle\">&nbsp;";
    
    print "<!-- <P>Building Heirarchy w/ ".sizeof($heirarchy)." leaves</P>  -->\n";
    
    print "<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>";
    
    $already_open = false;
    
    ##  Here we go.  Starting the main tree drawing loop.
    if (sizeof($heirarchy) > 0) {
	foreach ($heirarchy as $leaf) {
	    
	    if ($skip[$leaf[order_key]]) { continue; }
	    
	    
	    $tier = tree_tier($leaf[order_key], 2);
	    $current_leaf_type = $leaf[graph_id] ? "graph" : "heading";
	    
	    if ($current_leaf_type == 'heading') {
		
		##  If this isn't the first heading, we may have to close tables/rows.
		if ($heading_ct > 0) {
		    if ($need_table_close) { 
			if ($graph_ct % 2 == 0) { print "</tr>\n"; }
			print "</table></td></tr>\n"; 
			$already_open = false;
		    }
		}
		$colspan = (($max_tier - $tier) * 2);
		$rowspan = $rowspans[$leaf[order_key]];
#		print "Order key = '$leaf[order_key]', rs = '".$rowspans[$leaf[order_key]]."', rowspan = '$rowspan'<BR>\n";
		
		if (! $already_open) { 
		    print "<tr>\n";
		    $already_open = true;
		}
		
		if ($options[use_expand_contract]) {
		    if ($hide[$leaf[order_key]] == 1) {
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
			href='?tree_id=$tree_id&start_branch=$leaf[id]'>$leaf[title]</a></strong></td>\n</tr>";
		$already_open = false;
		
		##  If a heading isn't hidden and has graphs, start the vertical bar.
		if (! $hide[$leaf[order_key]] && $rowspan > 0) {
		    print "<tr><td bgcolor=\"$colors[panel]\" width=\"1%\" rowspan=$rowspan>&nbsp;</td>\n";
		    $already_open = true;
		}
		
		##  If this heading has graphs and we're supposed to show graphs, start that table.
		if ($num_graphs[$leaf[order_key]] > 0 && ! $hide[$leaf[order_key]]) { 
		    $need_table_close = true;
		    print "<td colspan=$colspan><table border=0><tr>\n"; 
		} else {
		    $need_table_close = false;
		}
		$graph_ct = 0;
	    } else {
		++$graph_ct;
		switch ($array_settings[view_type]) {
		 case "1":
		    print "<td><a href='graph.php?graphid=$leaf[graph_id]&rraid=all'><img align='middle' 
			    src='graph_image.php?graphid=$leaf[graph_id]&rraid=$leaf[rra_id]&graph_start=-".$array_settings[time_span].'&graph_height='.
			    $array_settings[height].'&graph_width='.$array_settings[width] ."&graph_nolegend=true' border='0' alt='$leaf[gtitle]'></a><td>\n";
		    break;
		 case "2":
		    print "<td><a href='graph.php?graphid=$leaf[graph_id]&rraid=all'>$leaf[gtitle]</a></td>";
		    break;
		}
		if ($graph_ct % 2 == 0) { print "</tr><tr>\n"; }
	    }
	}
    }
    DrawMatrixTableEnd();

}

function grow_edit_graph_tree($tree_id, $user_id, $options) {
	global $config, $colors;
	
	include_once ('include/form.php');
	include_once ('include/tree_functions.php');
	
	$tree = db_fetch_assoc("select
		graph_tree_view_items.id,
		graph_tree_view_items.graph_id,
		graph_tree_view_items.title,
		graph_tree_view_items.order_key
		from graph_tree_view_items
		where graph_tree_view_items.tree_id=$tree_id
		order by graph_tree_view_items.order_key");
	
    	print "<!-- <P>Building Heirarchy w/ " . sizeof($tree) . " leaves</P>  -->\n";
    	
    	##  Here we go.  Starting the main tree drawing loop.
	if (sizeof($tree) > 0) {
	foreach ($tree as $leaf) {
	    	$tier = tree_tier($leaf[order_key], 2);
	    	$transparent_indent = "<img width='" . (($tier-1) * 20) . "' height='1' align='middle' alt=''>&nbsp;";
		
		if ($i % 2 == 0) { $row_color = $colors["form_alternate1"]; }else{ $row_color = $colors["form_alternate2"]; } $i++;
		
	    	if ($leaf["title"] == "") {
			print "<td bgcolor='#$row_color' bgcolor='#$colors[panel]'>$transparent_indent<a href='tree.php?action=item_edit&tree_id=" . $_GET["id"] . "&tree_item_id=" . $leaf["id"] . "'>graph_id: $leaf[graph_id]</a></td>\n";
			print "<td bgcolor='#$row_color' bgcolor='#$colors[panel]'>Graph</td>";
		}else{
			print "<td bgcolor='#$row_color' bgcolor='#$colors[panel]'>$transparent_indent<a href='tree.php?action=item_edit&tree_id=" . $_GET["id"] . "&tree_item_id=" . $leaf["id"] . "'><strong>$leaf[title]</strong></a></td>\n";
			print "<td bgcolor='#$row_color' bgcolor='#$colors[panel]'>Heading</td>";
		}
		
		print 	"<td bgcolor='#$row_color' width='80' align='center'>\n
			<a href='tree.php?action=item_movedown&tree_item_id=" . $leaf["id"] . "&tree_id=" . $_GET["id"] . "'><img src='images/move_down.gif' border='0' alt='Move Down'></a>\n
			<a href='tree.php?action=item_moveup&tree_item_id=" . $leaf["id"] . "&tree_id=" . $_GET["id"] . "'><img src='images/move_up.gif' border='0' alt='Move Up'></a>\n
			</td>\n";
		
		print 	"<td bgcolor='#$row_color' width='1%' align='right'>\n
			<a href='tree.php?action=item_remove&id=" . $leaf["id"] . "&tree_id=$tree_id'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;\n
			</td></tr>\n";
	}
	}
}
    
?>
