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

function grow_graph_tree($tree_id, $start_branch, $user_id, $options) {
	include_once ('include/form.php');
	include_once ('include/tree_functions.php');
	
	global $colors;
	
	$options["num_columns"] = 2;
	$options["use_expand_contract"] = true;
	
	$vbar_width = 20;
	$search_key = "";
	$max_tier = 0;
	
	$num_graphs = array();
	$hide = array();
	$skip = array();
	$rowspans = array();
	
	/* get the "starting leaf" if the user clicked on a specific branch */
	if (($start_branch != "") && ($start_branch != "0")) {
		$search_key = preg_replace("/0+$/","",db_fetch_cell("select order_key from graph_tree_items where id=$start_branch"));
	}
	
	$heirarchy = db_fetch_assoc("select
		graph_tree_items.id,
		graph_tree_items.title,
		graph_tree_items.local_graph_id,
		graph_tree_items.rra_id,
		graph_tree_items.order_key,
		graph_templates_graph.title as graph_title,
		settings_tree.status
		from graph_tree_items
		left join graph_templates_graph on (graph_tree_items.local_graph_id=graph_templates_graph.local_graph_id and graph_tree_items.local_graph_id>0)
		left join settings_tree on (graph_tree_items.id=settings_tree.graph_tree_item_id and settings_tree.user_id=$user_id)
		where graph_tree_items.graph_tree_id=$tree_id
		and graph_tree_items.order_key like '$search_key%'
		order by graph_tree_items.order_key");
	
	$search_key = preg_replace("/0+$/","",$start_branch);
	
	##  First off, we walk the tree from the top to the root.  We do it in that order so that we 
	for ($i = (sizeof($heirarchy) - 1); $i >= 0; --$i) {
		$leaf = $heirarchy[$i];
		
		## While we're walking the tree, let's go ahead and set 'hide' flags for any branches that should be hidden (status in settings_viewing_tree == 1)
		if ($leaf["status"] == "1") {
			$hide[$leaf["order_key"]] = "1";
		}
		
		$tier = tree_tier($leaf["order_key"], 2);
		
		##  If there's a local_graph_id, the leaf is a graph, not a heading, so we increment the parent's num_graphs
		if ($leaf["local_graph_id"]) {
			$parent_key = str_pad(substr($leaf["order_key"],0,(($tier - 1) * 2) ),60,'0',STR_PAD_RIGHT);
			
			if (isset($num_graphs[$parent_key])) {
				$num_graphs[$parent_key]++;
			}else{
				$num_graphs[$parent_key] = 1;
			}
		}
		
		##  We also need the max_tier to do colspans so we do this to get it:
		if ($tier > $max_tier) { $max_tier = $tier; }
	}
	
	##  Now that we know how many graphs each heading has and whether it's supposed to be hidden, we walk the tree again from top to root to figure 
	##  out how many rows each vertical bar should span.
	for ($i = (sizeof($heirarchy) - 1); $i >= 0; --$i) {
		$leaf = $heirarchy[$i];
		$tier = tree_tier($leaf["order_key"], 2);
		
		##  Step through the hidden headings to see which entries to skip.
		for ($j = 1; $j < $tier; ++$j) {
			$parent_key = str_pad(substr($leaf["order_key"],0,($j * 2) ),60,'0',STR_PAD_RIGHT);
			
			if (!empty($hide[$parent_key])) { 
				$skip{$leaf["order_key"]} = "1";
			}
		}
				
		if (empty($skip{$leaf["order_key"]})) {
			if (empty($leaf["local_graph_id"])) {
				if (empty($hide{$leaf["order_key"]})) {
					if (!empty($num_graphs{$leaf["order_key"]})) {
						if (isset($rowspans{$leaf["order_key"]})) {
							$rowspans{$leaf["order_key"]}++;
						}else{
							$rowspans{$leaf["order_key"]} = "1";
						}
					}
				}
				
				$j = $tier - 1;
				$parent_key = str_pad(substr($leaf["order_key"],0,$j * 2 ),60,'0',STR_PAD_RIGHT);
				
				if (!isset($rowspans[$parent_key])) {
					$rowspans[$parent_key] = "0";
				}
				
				if (!isset($rowspans{$leaf["order_key"]})) {
					$rowspans{$leaf["order_key"]} = "0";
				}
				
				$rowspans[$parent_key] += ($rowspans{$leaf["order_key"]} + 1);
			}
		}
	}
    	
	print "<!-- <P>Building Heirarchy w/ " . sizeof($heirarchy) . " leaves</P>  -->\n";
	print "<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>";
	print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='30'><table cellspacing='0' cellpadding='3' width='100%'><tr><td class='textHeaderDark'><strong><a class='linkOverDark' href='graph_view.php?action=tree&tree_id=" . $_SESSION["sess_view_tree_id"] . "'>[root]</a> - " . db_fetch_cell("select name from graph_tree where id=" . $_SESSION["sess_view_tree_id"]) . "</strong></td></tr></table></td></tr>";
	
	$already_open = false;
	$heading_ct = 0;
	$graph_ct = 0;
	
	##  Here we go.  Starting the main tree drawing loop.
	if (sizeof($heirarchy) > 0) {
	foreach ($heirarchy as $leaf) {
		if (!empty($skip{$leaf["order_key"]})) { continue; }
		
		$tier = tree_tier($leaf["order_key"], 2);
		$current_leaf_type = $leaf["local_graph_id"] ? "graph" : "heading";
		
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
			$rowspan = (isset($rowspans{$leaf["order_key"]}) ? $rowspans{$leaf["order_key"]} : "1");
			
			if (! $already_open) { 
				print "<tr>\n";
				$already_open = true;
			}
			
			if ($options["use_expand_contract"]) {
				if (!empty($hide{$leaf["order_key"]})) {
					$other_status = '0';
					$ec_icon = 'show';
				}else{
					$other_status = '1';
					$ec_icon =  'hide';
					++$heading_ct;
				}
				
				print "<td bgcolor='" . $colors["panel"] . "' align='center' width='1%'><a
					href='graph_view.php?action=tree&tree_id=$tree_id&start_branch=$start_branch&hide=$other_status&branch_id=" . $leaf["id"] . "'>
					<img src='images/$ec_icon.gif' border='0'></a></td>\n";
			}else{
				print "<td bgcolor='" . $colors["panel"] . "' width='1'>$indent</td>\n";
			}
			
			print "<td bgcolor='" . $colors["panel"] . "' colspan=$colspan NOWRAP><strong>
				<a href='graph_view.php?action=tree&tree_id=$tree_id&start_branch=" . $leaf["id"] . "'>" . $leaf["title"] . "</a></strong></td>\n</tr>";
				$already_open = false;
			
			##  If a heading isn't hidden and has graphs, start the vertical bar.
			if ((empty($hide{$leaf["order_key"]})) && ($rowspan > 0)) {
				print "<tr><td bgcolor='" . $colors["panel"] . "' width='1%' rowspan=$rowspan>&nbsp;</td>\n";
				$already_open = true;
			}
			
			##  If this heading has graphs and we're supposed to show graphs, start that table.
			if ((!empty($num_graphs{$leaf["order_key"]})) && (empty($hide{$leaf["order_key"]}))) { 
				$need_table_close = true;
				print "<td colspan=$colspan><table border='0'><tr>\n";
			}else{
				$need_table_close = false;
			}
			
			$graph_ct = 0;
		}else{
			++$graph_ct;
			
			print "<td><a href='graph.php?local_graph_id=" . $leaf["local_graph_id"] . "&rra_id=all'><img align='middle' alt='" . get_graph_title($leaf["local_graph_id"]) . "'
				src='graph_image.php?local_graph_id=" . $leaf["local_graph_id"] . "&rra_id=" . read_graph_config_option("default_rra_id") . "&graph_start=-" . read_graph_config_option("timespan") . '&graph_height=' .
				read_graph_config_option("default_height") . '&graph_width=' . read_graph_config_option("default_width") . "&graph_nolegend=true' border='0' alt='" . $leaf["title"] . "'></a></td>\n";
			
			if ($graph_ct % 2 == 0) { print "</tr><tr>\n"; }
		}
	}
	}
	
	print "</tr></table></td></tr></table>";
}

function grow_edit_graph_tree($tree_id, $user_id, $options) {
	global $config, $colors;
	
	include_once ('include/form.php');
	include_once ('include/tree_functions.php');
	
	$tree = db_fetch_assoc("select
		graph_tree_items.id,
		graph_tree_items.title,
		graph_tree_items.local_graph_id,
		graph_tree_items.order_key,
		graph_templates_graph.title as graph_title
		from graph_tree_items
		left join graph_templates_graph on (graph_tree_items.local_graph_id=graph_templates_graph.local_graph_id and graph_tree_items.local_graph_id>0)
		where graph_tree_items.graph_tree_id=$tree_id
		order by graph_tree_items.order_key");
	
    	print "<!-- <P>Building Heirarchy w/ " . sizeof($tree) . " leaves</P>  -->\n";
    	
    	##  Here we go.  Starting the main tree drawing loop.
	
	$i = 0;
	if (sizeof($tree) > 0) {
	foreach ($tree as $leaf) {
	    	$tier = tree_tier($leaf["order_key"], 2);
	    	$transparent_indent = "<img width='" . (($tier-1) * 20) . "' height='1' align='middle' alt=''>&nbsp;";
		
		if ($i % 2 == 0) { $row_color = $colors["form_alternate1"]; }else{ $row_color = $colors["form_alternate2"]; } $i++;
		
	    	if ($leaf["title"] == "") {
			print "<td bgcolor='#$row_color' bgcolor='#" . $colors["panel"] . "'>$transparent_indent<a href='tree.php?action=item_edit&tree_id=" . $_GET["id"] . "&tree_item_id=" . $leaf["id"] . "'>" . get_graph_title($leaf["local_graph_id"]) . "</a></td>\n";
			print "<td bgcolor='#$row_color' bgcolor='#" . $colors["panel"] . "'>Graph</td>";
		}else{
			print "<td bgcolor='#$row_color' bgcolor='#" . $colors["panel"] . "'>$transparent_indent<a href='tree.php?action=item_edit&tree_id=" . $_GET["id"] . "&tree_item_id=" . $leaf["id"] . "'><strong>" . $leaf["title"] . "</strong></a> (<a href='tree.php?action=item_edit&tree_id=" . $_GET["id"] . "&parent_id=" . $leaf["id"] . "'>Add</a>)</td>\n";
			print "<td bgcolor='#$row_color' bgcolor='#" . $colors["panel"] . "'>Heading</td>";
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

function grow_dropdown_tree($tree_id, $form_name, $selected_tree_item_id) {
	global $colors;
	
	include_once ('include/tree_functions.php');
	
	$tree = db_fetch_assoc("select
		graph_tree_items.id,
		graph_tree_items.title,
		graph_tree_items.order_key
		from graph_tree_items
		where graph_tree_items.graph_tree_id=$tree_id
		and graph_tree_items.title != ''
		order by graph_tree_items.order_key");
	
	print "<select name='$form_name'>\n";
	print "<option value='0'>[root]</option>\n";
	
	if (sizeof($tree) > 0) {
	foreach ($tree as $leaf) {
	    	$tier = tree_tier($leaf["order_key"], 2);
	    	$indent = str_repeat("---", ($tier));
		
		if ($selected_tree_item_id == $leaf["id"]) {
			$html_selected = " selected";
		}else{
			$html_selected = "";
		}
		
	    	print "<option value='" . $leaf["id"] . "'$html_selected>$indent " . $leaf["title"] . "</option>\n";
	}
	}
	
	print "</select>\n";
}
    
?>
