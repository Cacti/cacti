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
<? 	$section = "Add/Edit Graphs"; 
include ('include/auth.php');
header("Cache-control: no-cache");
include_once ('include/utility_functions.php');
include_once ('include/form.php');

if ($form[action]) { $action = $form[action]; } else { $action = $args[action]; }
if ($form[ID]) { $id = $form[ID]; } else { $id = $args[id]; }
if ($form[GID]) { $gid = $form[GID]; } else { $gid = $args[gid]; }
if ($form[TID]) { $tid = $form[TID]; } else { $tid = $args[tid]; }

/* if the user wants to change the grouping status of this
 graph, do it here */
if (isset($HTTP_GET_VARS["graph_grouping"]) == true) {
    $grouping = db_fetch_row("select Grouping from rrd_graph where id=$args[id]");
    $current_graph_grouping = $grouping[Grouping];
    
    switch ($graph_grouping) {
		case 'on':
			/* make sure graph grouping is already OFF */
			if ($current_graph_grouping == "") {
			    group_graph_items($args[id]);
			    db_execute("update rrd_graph set grouping=\"on\" where id=$args[id]");
			}
			break;
     	case 'off':
			/* make sure graph grouping is already ON */
			if ($current_graph_grouping == "on") {
			    ungroup_graph_items($id);
			    db_execute("update rrd_graph set grouping=\"\" where id=$id");
			}
			
			break;
    }
    
    header ("Location: graphs_items.php?id=$id"); exit;
}

if (isset($HTTP_GET_VARS["graph_preview"]) == true) {
	switch ($HTTP_GET_VARS["graph_preview"]) {
		case 'on':
			header ("Set-Cookie: graph_preview=on; path=/;");
			break;
		case 'off':
			header ("Set-Cookie: graph_preview=off; path=/;");
			break;
    }
    
    header ("Location: graphs_items.php?id=$id"); exit;
}

if (isset($HTTP_GET_VARS["graph_output_preview"]) == true) {
	switch ($HTTP_GET_VARS["graph_output_preview"]) {
		case 'on':
			header ("Set-Cookie: graph_output_preview=on; path=/;");
			break;
     	case 'off':
			header ("Set-Cookie: graph_output_preview=off; path=/;");
			break;
    }
    
    header ("Location: graphs_items.php?id=$id"); exit;
}

$graph_preview = $HTTP_COOKIE_VARS["graph_preview"];
$graph_output_preview = $HTTP_COOKIE_VARS["graph_output_preview"];

switch ($action) {
 case 'save':
    include_once ('include/functions.php');
    if ($id == '') { $id = '0'; }
    
    $sql_id = db_execute("replace into rrd_graph_item (id,dsid,colorid,textformat,
		sequence,graphid,graphtypeid,consolidationfunction,hardreturn,cdefid,value,sequenceparent,
		parent) values ($id,$form[DSID],$form[ColorID],\"$form[TextFormat]\",$form[Sequence],$gid,$form[GraphTypeID],
		$form[ConsolidationFunction],\"$form[HardReturn]\",$form[CDEFID],\"$form[Value]\",$form[SequenceParent],$form[Parent])");
	
    if ($id == 0) {
		/* get graphid if this is a new save */
		$tmp = db_fetch_row("select LAST_INSERT_ID() as ID");
		
		if ($tmp[ID] != 0) {
		    /* we are using new_id so only the next two queries so the 
		     sequence code does not know about it...therefore forcing a new
		     sequence number if this is a new save */
		    $new_id = $tmp[ID];
		}
    }else{
		$new_id = $id;
    }
    
	$grouping = db_fetch_cell("select grouping from rrd_graph where id=$gid");
	$item = db_fetch_row("select t.Name from rrd_graph_item i left join def_graph_type t
		on i.graphtypeid=t.id where i.id=$new_id");
    
	if ($grouping == "on") {
		if ($old_graphtypeid != "0") {
			/* get the name of the LAST graph item type */
			$old_graphtype = db_fetch_cell("select name from def_graph_type where id=$old_graphtypeid");
		}
		
		if ($item[Name] != "GPRINT") {
			/* graph type changed; force a new sequence id */
			if ($old_graphtype == "GPRINT") { $id = 0; }
			
			$max_sequence = GetSequence($id, "sequenceparent", "rrd_graph_item", "graphid", $gid);
			
			db_execute("update rrd_graph_item set sequenceparent=$max_sequence where id=$new_id");
			db_execute("update rrd_graph_item set parent=$new_id where id=$new_id");
			
			/* this is a save on a parent item: if this parent has children with all of the same
			data source, then update the data source change in the parent to the children as 
			well. this saves the user some clicks */
			$sql_id = db_fetch_assoc("select dsid from rrd_graph_item where parent=$new_id and parent!=id group by dsid");
			
			if (sizeof($sql_id) == "1") {
				/* there is only one data source in use for this group */
				mysql_query("update rrd_graph_item set dsid=$dsid where parent=$new_id");
			}
		}else{
			/* if the group changes, force a new sequence */
			if ($old_parent != $parent) { $id = 0; }
			
			if ($old_graphtype != "GPRINT") {
				/* graph type changed; force a new sequence id */
				$id = 0;
				
				/* if the old item was a parent and now it is a child (GPRINT), we must find
				a new parent for this child. cacti says... go with the last available parent... */
				$parents = db_fetch_row("select SequenceParent,Parent from rrd_graph_item i left join def_graph_type t 
					on i.graphtypeid=t.id where i.graphid=$gid and (t.name = 'AREA' or t.name = 
					'STACK' or t.name = 'LINE1' or t.name = 'LINE2' or t.name = 'LINE3')
					order by i.sequenceparent desc");
				if (sizeof($parents) != "0") { $parent = $parents[Parent]; }
				
				/* also... what if that old parent had children... orfans need homes as well */
				$items_list = db_fetch_assoc("select ID from rrd_graph_item where parent=$new_id");
				
				foreach ($items_list as $items) {
					$sequenceparent = $parents[SequenceParent];
					$max_sequence = GetSequence(0, "sequence", "rrd_graph_item", "graphid", "$gid and parent=$parent");
					
					db_execute("update rrd_graph_item set sequence=$max_sequence,
						sequenceparent=$sequenceparent,
						parent=$parent
						where id=" . $items[ID]);
				}
			}
			
			$max_sequence = GetSequence($id, "sequence", "rrd_graph_item", "graphid", "$gid and parent=$parent");
			
			if ($parent != 0) {
				$sequenceparent = db_fetch_cell("select sequenceparent from rrd_graph_item where id=$parent");
			}
			
			db_execute("update rrd_graph_item set sequence=$max_sequence where id=$new_id", $cnn_id);
			db_execute("update rrd_graph_item set sequenceparent=$sequenceparent where id=$new_id", $cnn_id);
		}
	}else{
		$max_sequence = GetSequence($id, "sequence", "rrd_graph_item", "graphid", $gid);
		
		db_execute("update rrd_graph_item set sequence=$max_sequence where id=$new_id", $cnn_id);
	}
    
    header ("Location: graphs_items.php?id=$gid");
    break;
 case 'remove':
    $grouping = db_fetch_cell("select Grouping from rrd_graph where id=$gid");
    $item = db_fetch_row("select t.Name from rrd_graph_item i left join def_graph_type t
			   on i.graphtypeid=t.id where i.id=$id");
    
    if ($grouping == "on") {
		if ($item[Name] != "GPRINT") {
	    	db_execute("delete from rrd_graph_item where parent=$tid");
		}else{
	    	db_execute("delete from rrd_graph_item where id=$id");
		}
    }else{
		db_execute("delete from rrd_graph_item where id=$id");
    }
    
    header ("Location: graphs_items.php?id=$gid&graph_preview=$graph_preview&graph_output_preview=$graph_output_preview");
    break;
 case 'movedown':
    include_once ('include/functions.php');
    
    $grouping = db_fetch_row("select Grouping from rrd_graph where id=$gid");
    $item = db_fetch_row("select t.Name from rrd_graph_item i left join def_graph_type t
			   on i.graphtypeid=t.id where i.id=$id");
    
    if ($grouping[Grouping] == "on") {
	if ($item[Name] != "GPRINT") {
	    $sequence_column = "sequenceparent";
	    $sequence_query = "$gid";
	} else {
	    $sequence_column = "sequence";
	    $sequence_query = "$gid and parent=$tid";
	}
    }else{
	$sequence_column = "sequence";
	$sequence_query = "$gid";
    }
    
    $next_item = GetNextItem("rrd_graph_item",$sequence_column,$id,"graphid",$sequence_query);
    
    if ($sequence_column == "sequenceparent") { /* group order */
#	print "here1<BR>\n";
	$sql = "select Parent from rrd_graph_item where $sequence_column=$next_item and graphid=$gid";
#	print "$sql<BR>\n";
	$parent = db_fetch_row($sql);
	$seq    = db_fetch_row("select $sequence_column from rrd_graph_item where id=$id");
	$sql = "update rrd_graph_item set $sequence_column=$next_item where parent=$id";
#	print "$sql<BR>\n";
	db_execute($sql);
	$sql = "update rrd_graph_item set $sequence_column=$seq[$sequence_column] where parent=$parent[Parent]";
#	print "$sql<BR>\n";
	db_execute($sql);
    } elseif ($sequence_column == "sequence") { /* item order */
	$id  = db_fetch_cell("select ID from rrd_graph_item where $sequence_column=$next_item and graphid=$gid and parent=$tid");
	$seq = db_fetch_cell("select $sequence_column from rrd_graph_item where id=$id");
	db_execute("update rrd_graph_item set $sequence_column=$next_item where id=$id");
	db_execute("update rrd_graph_item set $sequence_column=$seq[$sequence_column] where id=$id[ID]");
    }
    
    header ("Location: graphs_items.php?id=$gid&graph_preview=$graph_preview&graph_output_preview=$graph_output_preview"); exit;
    break;
 case 'moveup':
    include_once ('include/functions.php');
    
    $grouping = db_fetch_row("select Grouping from rrd_graph where id=$gid");
    $item = db_fetch_row("select t.Name from rrd_graph_item i left join def_graph_type t
			   on i.graphtypeid=t.id where i.id=$id");
    
    if ($grouping[Grouping] == "on") {
	if ($item[Name] != "GPRINT") {
	    $sequence_column = "sequenceparent";
	    $sequence_query = "$gid";
	}else{
	    $sequence_column = "sequence";
	    $sequence_query = "$gid and parent=$tid";
	}
    }else{
	$sequence_column = "sequence";
	$sequence_query = "$gid";
    }
    
    $last_item = GetLastItem($cnn_id,"rrd_graph_item",$sequence_column,$id,"graphid",$sequence_query);
    
    if ($sequence_column == "sequenceparent") { /* group order */
	$parent = db_fetch_row("select Parent from rrd_graph_item where $sequence_column=$last_item and graphid=$gid");
	$seq = db_fetch_row("select $sequence_column from rrd_graph_item where id=$id");
	db_execute("update rrd_graph_item set $sequence_column=$last_item where parent=$id");
	db_execute("update rrd_graph_item set $sequence_column=$seq[$sequence_column] where parent=$parent[Parent]");
    }elseif ($sequence_column == "sequence") { /* item order */
	$id = db_fetch_row("select ID from rrd_graph_item where $sequence_column=$last_item and graphid=$gid and parent=$tid");
	$seq = db_fetch_row("select $sequence_column from rrd_graph_item where id=$id");
	db_execute("update rrd_graph_item set $sequence_column=$last_item where id=$id");
	db_execute("update rrd_graph_item set $sequence_column=$seq[$sequence_column] where id=$id[ID]");
    }
    
    header ("Location: graphs_items.php?id=$gid&graph_preview=$graph_preview&graph_output_preview=$graph_output_preview"); exit;
    break;
 case 'edit':
    include_once ('include/top_header.php');
    
    /* get current graph name for the header text */
    $title = db_fetch_row("select Title,Grouping from rrd_graph where id=$gid");
    $graph_title = $title[Title];
    
    if ($id != "") {
	$item = db_fetch_row("select * from rrd_graph_item where id=$id");
	$old_parent = $item[Parent];
    }
    
    DrawFormHeader("Graph Item Configuration for: $graph_title","",false);
    
    /* by default, select the LAST DS chosen to make everyone's lives easier */
    $default = db_fetch_row("select * from rrd_graph_item where graphid=$gid order by sequenceparent DESC,sequence DESC");
    
    if (sizeof($default) > 0) {
	$default_item = $default[SDID];
    } else {
	$default_item = 0;
    }
    
    DrawFormItem("Data Source","The data source to use for this graph item; not used for COMMENT fields.");
    DrawFormItemDropdownFromSQL("DSID",db_fetch_assoc("select ID,Name from rrd_ds where isparent=0 order by name"),
				"Name","ID","","None",$default_item);
    
    if ($title[Grouping] == "on") {
	/* default item (last item) */
	$types = db_fetch_assoc("select CONCAT_WS('',t.name,': ',d.name) as Name,
				  i.ID from rrd_graph_item i left join def_graph_type t on i.graphtypeid=t.id left join
				  rrd_ds d on i.dsid=d.id where graphid=$gid and (t.name = 'AREA' or t.name = 'STACK' or t.name = 'LINE1'
										  or t.name = 'LINE2' or t.name = 'LINE3') order by i.sequenceparent DESC");
	
	if (sizeof($types) == 0) {
	    DrawFormItemHiddenIDField("Parent","0");
	} else {
	    $default_item = $types[0][ID];
	    
	    DrawFormItem("Item Group","Choose which graph item this GPRINT is associated with. NOTE: This field
					will be ignored if it is not a GPRINT.");
	    DrawFormItemDropdownFromSQL("Parent",$types,"Name","ID","","",$default_item);
	}
    } else {
	DrawFormItemHiddenIDField("Parent","0");
    }
    
    DrawFormItem("Color","The color that is used for this item; not used for COMMENT fields.");
    DrawFormItemColorSelect("ColorID",$item[ColorID],"None","0");
    
    DrawFormItem("Graph Item Type","How data for this item is displayed.");
    DrawFormItemDropdownFromSQL("GraphTypeID",db_fetch_assoc("select ID,Name from def_graph_type order by name"),"Name","ID","","","");
    
    DrawFormItem("Consolidation Function","How data is to be represented on the graph.");
    DrawFormItemDropdownFromSQL("ConsolidationFunction",db_fetch_assoc("select * from def_cf order by name"),"Name","ID","","","");
    
    DrawFormItem("CDEF Function","A CDEF Function to apply to this item on the graph.");
    DrawFormItemDropdownFromSQL("CDEFID",db_fetch_assoc("select * from rrd_ds_cdef order by name"),"Name","ID","None","","");
    
    DrawFormItem("Value","For use with VRULE and HRULE, <i>numbers only</i>, and<br> staggered CDEF Calculations, format start=<<i>Data Source Position</i>>&skip<<i>Skip Count</i>>.");
    DrawFormItemTextBox("Value",$item[Value],"","");
    
    DrawFormItem("Text Format","The text of the comment or legend, input and output keywords are allowed.");
    DrawFormItemTextBox("TextFormat",$item[TextFormat],"","");
    DrawFormItemCheckBox("HardReturn",$item[HardReturn],"Insert Hard Return","");
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("GID",$gid);
    DrawFormItemHiddenIDField("SequenceParent",$item[SequenceParent]);
    DrawFormItemHiddenIDField("Sequence",$item[Sequence]);
    DrawFormItemHiddenIDField("old_parent",$old_parent);
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    
    break;
 default:
    include_once ('include/top_header.php');
    include_once ('include/rrd_functions.php');
    
    /* get current graph name for the header text */
    $graph = db_fetch_row("select Title,Grouping from rrd_graph where id=$id");
    $graph_title = $graph[Title];
    $allow_grouping = $grouping[Grouping];
    
    DrawMatrixTableBegin("97%");
    DrawMatrixRowBegin();
    DrawMatrixHeaderTop("Graph Items for: $graph_title",$colors[dark_bar],"","6");
    DrawMatrixHeaderAdd($colors[dark_bar],"","graphs_items.php?action=edit&gid=$id");
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    ?>
			<td bgcolor="#<?print $colors[panel];?>" colspan="7">
				<table width="100%">
					<tr>
						<td rowspan="2" valign="top">
							<a href="graphs_items.php?id=<?print $id;?>&graph_preview=<?if ($graph_preview == "on") { print "off"; }else{ print "on"; }?>">Turn Graph Preview <?if ($graph_preview == "on") { print "<strong>Off</strong>"; }else{ print "<strong>On</strong>"; }?></a><br>
							<a href="graphs_items.php?id=<?print $id;?>&graph_output_preview=<?if ($graph_output_preview == "on") { print "off"; }else{ print "on"; }?>">Turn Graph Output Preview <?if ($graph_output_preview == "on") { print "<strong>Off</strong>"; }else{ print "<strong>On</strong>"; }?></a><br>
							<a href="graphs_items.php?id=<?print $id;?>&graph_grouping=<?if ($allow_grouping == "on") { print "off"; }else{ print "on"; }?>">Turn Graph Grouping <?if ($allow_grouping == "on") { print "<strong>Off</strong>"; }else{ print "<strong>On</strong>"; }?></a><br>
						</td>
						<td align="right">
							<?if ($graph_preview == "on") {?>
							<img src="graph_image.php?graphid=<?print $id;?>&rraid=1&graph_start=-86400&graph_height=100&graph_width=350">
							<?}?>
						</td>
					</tr>
					<tr>
						<td align="right">
							<?if ($graph_output_preview == "on") {
    $graph_data_array["output_flag"] = 2;
							    ?><PRE><?print rrdtool_function_graph($id, 1, $graph_data_array);?></PRE><?
							}?>
						</td>
					</tr>
				</table>
			</td>
			<?
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("Graph Item",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Data Source Name",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Graph Item Type",$colors[panel],$colors[panel_text]);
    DrawMatrixCustom("<td bgcolor=\"$colors[panel]\" colspan=\"2\">Item Color</td>");
    DrawMatrixHeaderItem("Sequence",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderAdd($colors[panel],"","graphs_items.php?action=edit&gid=$id");
    DrawMatrixRowEnd();
    
    $items = db_fetch_assoc("select 
			      d.id as DID, d.name as DName, 
			      t.id as TID, t.Name, 
			      c.id as CID,c.Hex, 
			      cf.name as CFName,
			      g.*,
			      cd.name as CDEFName
			      from rrd_graph_item g 
			      left join def_graph_type t on g.graphtypeid=t.id
			      left join def_colors c on g.colorid=c.id 
			      left join def_cf cf on g.consolidationfunction=cf.id
			      left join rrd_ds d on g.dsid=d.id
			      left join rrd_ds_cdef cd on g.cdefid=cd.id
			      where g.graphid=$id order by g.sequenceparent,g.sequence");
    $rows = sizeof($items);
    $i = 0;
    
    while ($i < $rows) {
	$item = $items[$i];
	$current_color = DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	/* grouping options */
	if ($allow_grouping == "on") { /* GROUPING ON, PARENT */
	    print "<P>Name = '$item[Name]'</P>\n";
	    if ($item[Name] != "GPRINT") {
		$items_bold = true;
		$title_bold = true;
		$items_indent = "";
	    } else { /* GROUPING ON, CHILD */
		$items_bold = false;
		$title_bold = false;
		$items_indent = "<img src=\"images/gray_line.gif\" width=\"7\" height=\"1\" border=\"0\">";
	    }
	} else { /* GROUPING OFF */
	    $items_bold = false;
	    $title_bold = html_boolean($config["vis_main_column_bold"]["value"]);
	    $items_indent = "";
	}
	
	DrawMatrixLoopItem("$items_indent Item #" . ($i+1),$title_bold,"graphs_items.php?action=edit&id=$item[ID]&gid=$id");
	
	switch ($item[Name]) {
	 case 'COMMENT':
	    $matrix_title = "COMMENT: $item[TextFormat]";
	    break;
	 case 'GPRINT':
	    $matrix_title = $item[DName] . ": " . $item[TextFormat];
	    break;
	 case 'HRULE':
	    $matrix_title = "HRULE: $item[Value]";
	    break;
	 case 'VRULE':
	    $matrix_title = "VRULE: $item[Value]";
	    break;
	 default:
	    $matrix_title = $item[DName];
	    break;
	}
	
	/* for display only */
	$matrix_title = htmlspecialchars($matrix_title);
	
	/* use the cdef name (if in use) if all else fails */
	if ($matrix_title == "") {
	    if ($item[CDEFName] != "") {
		$matrix_title .= "CDEF: $item[CDEFName]";
	    }
	}
	
	if ($item[HardReturn] == "on") {
	    $matrix_title .= "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
	}
	
	if ($item[Name] != "GPRINT") {
	    if ($item[CFName] != "AVERAGE") {
		$matrix_title .= " (" . $item[CFName] . ")";
	    }
	}
	
	DrawMatrixLoopItem($matrix_title,$items_bold,"");
	DrawMatrixLoopItem($item[Name],$items_bold,"");
	
	if ($item[Hex] != "") {
	    $matrix_bgcolor = $item[Hex];
	} else {
	    $matrix_bgcolor = $current_color;
	}
	
	DrawMatrixLoopItemAction("&nbsp;",$matrix_bgcolor,"",$items_bold,""); /* color preview */
	DrawMatrixLoopItem($item[Hex],$items_bold,""); /* hex value */
	DrawMatrixLoopItem("[<a href=\"graphs_items.php?action=movedown&id=$item[ID]&gid=$id&tid=$item[Parent]\">Down</a>], [<a 
																   href=\"graphs_items.php?action=moveup&id=$item[ID]&gid=$id&tid=$item[Parent]\">Up</a>]",$items_bold,"");
	DrawMatrixLoopItemAction("Remove",$colors[panel],"",$items_bold,"graphs_items.php?action=remove&id=$item[ID]&gid=$id&tid=$item[Parent]");
	
	DrawMatrixRowEnd();
	$i++;
    }
    
    DrawMatrixTableEnd();
    include_once ("include/bottom_footer.php");
    
    break;
} ?>
