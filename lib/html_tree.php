<?

function grow_graph_tree($tree_id, $starting_branch, $user_id, $options) {
	include_once ('include/form.php');
	include_once ('include/tree_functions.php');
	global $colors, $config, $array_settings;
	
	$options[num_columns] = 2;
	$options[use_expand_contract] = true;
	
	/* get the "starting leaf" if the user clicked on a specific branch */
	if (($tree_parameters["options"]["start_branch"] != "") && ($tree_parameters["options"]["start_branch"] != "0")) {
		$search_key = preg_replace("/0+$/","",db_fetch_cell("select OrderKey from graph_hierarchy_items where id=" . $tree_parameters["options"]["start_branch"]));
	}
	
	/* find out what type of policy is in effect for this user: ALLOW or DENY, and
	set up the appropriate SQL WHERE clause appropriatly */
	if ($config["graph_policy"]["auth"] == "1") {
		$sql_where = "and ag.userid is null";
	}elseif ($config["graph_policy"]["auth"] == "2") {
		$sql_where = "and (ag.userid is not null or h.type=\\\"Heading\\\")";
	}
	
	if ($config["global_auth"]["value"] == "on") {
		$hierarchy = db_fetch_assoc('select 
			h.ID,h.GraphID,h.RRAID,h.Type,h.Title,h.OrderKey,
			g.Title as gtitle,
			st.Status,
			ag.graphid as aggraphid, ag.userid as aguserid 
			from graph_hierarchy_items h 
			left join rrd_graph g on h.graphid=g.id 
			left join settings_tree st on (h.id=st.treeitemid and st.userid=' . $user_id . ') 
			left join auth_graph ag on (g.id=ag.graphid and ag.userid=' . $user_id . ') 
			where h.treeid=' . $tree_id . '
			and OrderKey like "' . $search_key . '%"
			' . $sql_where . '
			order by h.OrderKey');
	}else{
		$hierarchy = db_fetch_assoc('select 
			h.ID,h.GraphID,h.RRAID,h.Type,h.Title,h.OrderKey,
			g.title as gtitle,
			r.name as rname,
			st.Status 
			from graph_hierarchy_items h 
			left join rrd_graph g on h.graphid=g.id 
			left join rrd_rra r on h.rraid=r.id 
			left join settings_tree st on (h.id=st.treeitemid and st.userid=' . $user_id . ') 
			where h.treeid=' . $tree_id . ' 
			and OrderKey like "' . $search_key . '%"
			order by h.OrderKey');
	}
	
	$rows = sizeof($hierarchy); $i = 0;
	
	/* make sure this tree has at least one leaf */
	if (sizeof($hierarchy) == 0) { return 0; }
	
	/* loop through all items */
	foreach ($hierarchy as $leaf) {
		$tier = tree_tier($leaf[OrderKey], 2);
		
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
		$current_column_type = $leaf[Type];
		
		/* get the next type (if we're not the last item) to see when to end a row on an
		'off' column */
		if (($i+2) <= $rows) {
			$next_column_type = $hierarchy[$i+1][Type];
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
			$html_item = $indent . '<a href="graph.php?graphid=' . $leaf[GraphID] . '
				&rraid=all"><img align="middle" src="graph_image.php?graphid=' . $leaf[GraphID] . '
				&rraid=' . $leaf[RRAID] . '&graph_start=-' . 
				$array_settings[preview][timespan] . '&graph_height=' . 
				$array_settings[preview][height] . '&graph_width=' . $array_settings[preview][width] . '
				&graph_nolegend=true" border="0" alt="' . $leaf[gtitle] . '"></a>';
			$html_td_item = 'bgcolor="#' . $colors[light] . '"';
		}elseif ($current_column_type == "Heading") {
			/* What happens when the user clicks the header? */
			$html_item = '<strong><a href="graph_view.php?action=tree&tree_id=' . $tree_id . '&start_branch="' . $leaf[ID] . '">' . $leaf[Title] . '</a></strong>';
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


?>
