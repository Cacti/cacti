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
    
    	if ($config["global_auth"]["value"] == "on") {
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

#    print "$sql<BR>\n";
    $heirarchy = db_fetch_assoc($sql);


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
    
    DrawMatrixTableBegin();
    
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

function grow_polling_tree($start_branch, $user_id, $options) {
    global $config,$colors,$array_settings,$PHP_SELF,$args;
    include_once ('include/form.php');
    include_once ('include/tree_functions.php');
    
    $options[use_expand_contract] = true;
    $vbar_width = 20;
    
    /* get the "starting leaf" if the user clicked on a specific branch */
    if (($start_branch != "") && ($start_branch != "0")) {
	$search_key = preg_replace("/0+$/","",db_fetch_cell("select order_key from graph_tree_view_items where id=$start_branch"));
    }
    
    
    $sql = "select * from polling_tree
	     left join polling_hosts on polling_tree.host_id = polling_hosts.host_id
	     left join settings_ds_tree on ( polling_tree.ptree_id = settings_ds_tree.TreeItemID AND settings_ds_tree.userid = $user_id)
	     where polling_tree.order_key like '$search_key%'
	     and (polling_hosts.profile_id = 0 OR polling_hosts.profile_id IS NULL)
	     order by polling_tree.order_key";
    
    
#        print "$sql<BR>\n";
    $heirarchy = db_fetch_assoc($sql);
    
    
    ##  First off, we walk the tree from the top to the root.  We do it in that order so that we 
    for ($i = (sizeof($heirarchy) - 1); $i >= 0; --$i) {
	$leaf = $heirarchy[$i];
	
	## While we're walking the tree, let's go ahead and set 'hide' flags for any branches that should be hidden (status in settings_viewing_tree == 1)
	if ($leaf[Status] == 1) {
	    $hide[$leaf[order_key]] = 1; 
	}
	
	$tier = tree_tier($leaf[order_key], 2);
	$parent_key = str_pad(substr($leaf[order_key],0,(($tier - 1) * 2) ),60,'0',STR_PAD_RIGHT);
	
	##  If there's a host_id, the leaf is a host, not a heading, so we increment the parent's num_hosts
	if ($leaf[host_id]) {
	    ++$num_hosts[$parent_key];
	    #	    print "host_id = '$leaf[host_id]'.  Incrementing parent '$parent_key'.<BR>\n";
	}
	
	##  We also need the max_tier to do colspans so we do this to get it:
	if ($tier > $max_tier) { $max_tier = $tier; }
    }
    
    
    ##  Now that we know how many hosts each heading has and whether it's supposed to be hidden, we walk the tree again from top to root to figure 
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
	    if (!$leaf[host_id]) {
#	    print "Checking header $leaf[ptree_id], $leaf[order_key], num_hosts = '".$num_hosts[$leaf[order_key]]."'<BR>\n";
		if (! $hide[$leaf[order_key]]) {
		    if ($num_hosts[$leaf[order_key]] > 0) {
			++$rowspans[$leaf[order_key]]; 
#		    print "num_hosts[$leaf[order_key]] > 0 - incrementing rowspans[$leaf[order_key]]<BR>\n";
		    }
		}
		$j = $tier - 1;
		$parent_key = str_pad(substr($leaf[order_key],0,$j * 2 ),60,'0',STR_PAD_RIGHT);
		$rowspans[$parent_key] += ($rowspans[$leaf[order_key]] + 1);
#	    print "Adding ".($rowspans[$leaf[order_key]] + 1)." to rowspans[$parent_key] for $leaf[ptree_id]<BR>\n";
	    }
	}
    }
    
    $indent = "<img src=\"images/gray_line.gif\" width=\"".($level * $vbar_width)."\" height=\"1\" align=\"middle\">&nbsp;";
    
    print "<!-- <P>Building Heirarchy w/ ".sizeof($heirarchy)." leaves</P>  -->\n";
    
    DrawMatrixTableBegin();
    
    $already_open = false;
    
    ##  Here we go.  Starting the main tree drawing loop.
    if (sizeof($heirarchy) > 0) {
	foreach ($heirarchy as $leaf) {
	    
	    if ($skip[$leaf[order_key]]) { continue; }
	    
	    $tier = tree_tier($leaf[order_key], 2);
	    $current_leaf_type = $leaf[host_id] ? "host" : "heading";
	    
	    $colspan = (($max_tier - $tier) + 1);
	    $rowspan = $rowspans[$leaf[order_key]];
	    #		print "Order key = '$leaf[order_key]', rs = '".$rowspans[$leaf[order_key]]."', rowspan = '$rowspan'<BR>\n";
	    
	    if (! $already_open) { 
		print "<tr>\n";
		$already_open = true;
	    }
	    
	    if ($hide[$leaf[order_key]] == 1) {
		$other_status = '0';
		$ec_icon = 'show';
	    } else {
		$other_status = '1';
		$ec_icon =  'hide';
		++$heading_ct;
	    }
	    print "<td bgcolor=\"$colors[panel]\" align=\"center\" width=\"1\"><a
		    href='$PHP_SELF?action=tree&start_branch=$start_branch&hide=$other_status&branch_id=$leaf[ptree_id]'><img
		    src='images/$ec_icon.gif' border='0'></a></td>\n";
	    if ($current_leaf_type == 'heading') {
		print "<td bgcolor='$colors[panel]' colspan=$colspan NOWRAP><strong>$leaf[title]</strong></td>\n";
	    } else {
		print "<td bgcolor='$colors[panel]'><a href=''>$leaf[hostname].$leaf[domain]</a> - $leaf[descrip]</td>\n";
	    }
	    print "	<td bgcolor='$colors[panel]'>
		    [ <a href='?action=move_up&branch_id=$leaf[ptree_id]'>Up</a> ]
		    [ <a href='?action=move_down&branch_id=$leaf[ptree_id]'>Down</a> ]
		    </td></tr>";
	    $already_open = false;
	    
	    ##  If a heading isn't hidden and has hosts, start the vertical bar.
	    if (! $hide[$leaf[order_key]] && $rowspan > 0) {
		print "<tr><td bgcolor=\"$colors[panel]\" width=\"1%\" rowspan=$rowspan>&nbsp;</td>\n";
		$already_open = true;
	    }
	    
	}
    }
    DrawMatrixTableEnd();
}
    
    ?>
