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
	global $colors, $current_user, $config;
	
	include_once($config["include_path"] . "/form.php");
	include_once($config["include_path"] . "/tree_functions.php");
	
	$options["use_expand_contract"] = true;
	
	$search_key = "";
	$already_open = false;
	$hide_until_tier = false;
	$graph_ct = 0;
	$sql_where = "";
	$sql_join = "";
	
	/* get the "starting leaf" if the user clicked on a specific branch */
	if (($start_branch != "") && ($start_branch != "0")) {
		$search_key = preg_replace("/0+$/","",db_fetch_cell("select order_key from graph_tree_items where id=$start_branch"));
	}
	
	/* graph permissions */
	if (read_config_option("global_auth") == "on") {
		if ($current_user["graph_policy"] == "1") {
			$sql_where = "and (user_auth_graph.user_id is null OR graph_tree_items.local_graph_id=0)";
		}elseif ($current_user["graph_policy"] == "2") {
			$sql_where = "and (user_auth_graph.user_id is not null OR graph_tree_items.local_graph_id=0)";
		}
		
		$sql_join = "left join user_auth_graph on (graph_templates_graph.local_graph_id=user_auth_graph.local_graph_id and user_auth_graph.user_id=" . $_SESSION["sess_user_id"] . ")";
	}
	
	$heirarchy = db_fetch_assoc("select
		graph_tree_items.id,
		graph_tree_items.title,
		graph_tree_items.local_graph_id,
		graph_tree_items.rra_id,
		graph_tree_items.order_key,
		graph_templates_graph.title_cache as graph_title,
		settings_tree.status
		from graph_tree_items
		left join graph_templates_graph on (graph_tree_items.local_graph_id=graph_templates_graph.local_graph_id and graph_tree_items.local_graph_id>0)
		left join settings_tree on (graph_tree_items.id=settings_tree.graph_tree_item_id and settings_tree.user_id=$user_id)
		$sql_join
		where graph_tree_items.graph_tree_id=$tree_id
		and graph_tree_items.order_key like '$search_key%'
		$sql_where
		order by graph_tree_items.order_key");
    	
	print "<!-- <P>Building Heirarchy w/ " . sizeof($heirarchy) . " leaves</P>  -->\n";
	print "<table width='98%' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center' cellpadding='0' cellspacing='2'>";
	print "<tr bgcolor='#" . $colors["header_panel"] . "'><td colspan='30'><table cellspacing='0' cellpadding='3' width='100%'><tr><td class='textHeaderDark'><strong><a class='linkOverDark' href='graph_view.php?action=tree&tree_id=" . $_SESSION["sess_view_tree_id"] . "'>[root]</a> - " . db_fetch_cell("select name from graph_tree where id=" . $_SESSION["sess_view_tree_id"]) . "</strong></td></tr></table></td></tr>";
	
	$i = 0;
	
	/* loop through each tree item */
	if (sizeof($heirarchy) > 0) {
	foreach ($heirarchy as $leaf) {
		/* find out how 'deep' this item is */
		$tier = tree_tier($leaf["order_key"], 2);
		
		/* find the type of the current and next branch */
		$current_leaf_type = $leaf["title"] ? "heading" : "graph";
		$next_leaf_type = (isset($heirarchy{$i+1})) ? ($heirarchy{$i+1}["title"] ? "heading" : "graph") : "";
		
		if (($current_leaf_type == 'heading') && (($tier <= $hide_until_tier) || ($hide_until_tier == false))) {
			/* start the nested table for the heading */
			print "<tr><td colspan='2'><table width='100%' cellpadding='2' cellspacing='1'><tr>\n";
			
			/* draw one vbar for each tier */
			for ($j=0;($j<($tier-1));$j++) {
				print "<td width='10' bgcolor='#" . $colors["panel"] . "'></td>\n";
			}
			
			/* draw the '+' or '-' icons if configured to do so */
			if (($options["use_expand_contract"]) && (!empty($leaf["title"]))) {
				if ($leaf["status"] == "1") {
					$other_status = '0';
					$ec_icon = 'show';
				}else{
					$other_status = '1';
					$ec_icon =  'hide';
				}
				
				print "<td bgcolor='" . $colors["panel"] . "' align='center' width='1%'><a
					href='graph_view.php?action=tree&tree_id=$tree_id&start_branch=$start_branch&hide=$other_status&branch_id=" . $leaf["id"] . "'>
					<img src='images/$ec_icon.gif' border='0'></a></td>\n";
			}elseif (!($options["use_expand_contract"]) && (!empty($leaf["title"]))) {
				print "<td bgcolor='" . $colors["panel"] . "' width='1'>$indent</td>\n";
			}
			
			/* draw the actual cell containing the header */
			if (!empty($leaf["title"])) {
				print "<td bgcolor='" . $colors["panel"] . "' NOWRAP><strong>
					<a href='graph_view.php?action=tree&tree_id=$tree_id&start_branch=" . $leaf["id"] . "'>" . $leaf["title"] . "</a>&nbsp;</strong></td>\n";
			}
			
			/* end the nested table for the heading */
			print "</tr></table></td></tr>\n";
			
			$graph_ct = 0;
		}elseif (($current_leaf_type == 'graph') && (($tier <= $hide_until_tier) || ($hide_until_tier == false))) {
			$graph_ct++;
			
			/* start the nested table for the graph group */
			if ($already_open == false) {
				print "<tr><td><table width='100%' cellpadding='2' cellspacing='1'><tr>\n";
				
				/* draw one vbar for each tier */
				for ($j=0;($j<($tier-1));$j++) {
					print "<td width='10' bgcolor='#" . $colors["panel"] . "'></td>\n";
				}
				
				print "<td><table width='100%' cellspacing='0' cellpadding='2'><tr>\n";
				
				$already_open = true;
			}
			
			/* print out the actual graph html */
			print "<td><a href='graph.php?local_graph_id=" . $leaf["local_graph_id"] . "&rra_id=all'><img align='middle' alt='" . $leaf["graph_title"] . "'
				src='graph_image.php?local_graph_id=" . $leaf["local_graph_id"] . "&rra_id=" . $leaf["rra_id"] . "&graph_start=" . get_rra_timespan($leaf["rra_id"]) . '&graph_height=' .
				read_graph_config_option("default_height") . '&graph_width=' . read_graph_config_option("default_width") . "&graph_nolegend=true' border='0' alt='" . $leaf["title"] . "'></a></td>\n";
			
			/* if we are at the end of a row, start a new one */
			if ($graph_ct % read_graph_config_option("num_columns") == 0) {
				print "</tr><tr>\n";
			}
			
			/* if we are at the end of the graph group, end the nested table */
			if ($next_leaf_type != "graph") {
				print "</tr></table></td>";
				print "</tr></table></td></tr>\n";
				
				$already_open = false;
			}
		}
		
		/* if we have come back to the tier that was origionally flagged, then take away the flag */
		if (($tier <= $hide_until_tier) && ($hide_until_tier != false)) {
			$hide_until_tier = false;
		}
		
		/* if we are supposed to hide this branch, flag it */
		if (($leaf["status"] == "1") && ($hide_until_tier == false)) {
			$hide_until_tier = $tier;
		}
		
		$i++;
	}
	}
	
	print "</tr></table></td></tr></table>";
}

function grow_edit_graph_tree($tree_id, $user_id, $options) {
	global $config, $colors;
	
	include_once($config["include_path"] . "/form.php");
	include_once($config["include_path"] . "/tree_functions.php");
	
	$tree = db_fetch_assoc("select
		graph_tree_items.id,
		graph_tree_items.title,
		graph_tree_items.local_graph_id,
		graph_tree_items.order_key,
		graph_templates_graph.title_cache as graph_title
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
			print "<td bgcolor='#$row_color' bgcolor='#" . $colors["panel"] . "'>$transparent_indent<a href='tree.php?action=item_edit&tree_id=" . $_GET["id"] . "&tree_item_id=" . $leaf["id"] . "'>" . $leaf["graph_title"] . "</a></td>\n";
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
	global $colors, $config;
	
	include_once ($config["include_path"] . "/tree_functions.php");
	
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
