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
<? 	$section = "Add/Edit Graphs"; include ('include/auth.php');
header("Cache-control: no-cache");
#	include ('include/database.php');
#	include ('include/config.php');
include_once ('include/form.php');

switch ($action) {
 case 'save':
    include_once ('include/functions.php');
    
    if ($graphid != "0") { $type = "Graph"; }
    
    $max_sequence = GetSequence($id, "sequence", "graph_hierarchy_items", "parent", "$parent and treeid=$gid");
    
    $sql_id = db_execute("replace into graph_hierarchy_items (id,treeid,graphid,rraid,title,type,parent,sequence) 
      values ($id,$gid,$graphid,$rraid,\"$title\",\"$type\",$parent,$max_sequence)");
    
    header ("Location: tree_items.php?id=$gid");
    break;
 case 'movedown':
    include_once ('include/functions.php');
    
    $next_item = GetNextItem($cnn_id,"graph_hierarchy_items","sequence",$id,"parent","$gid and treeid=$tid");
    
    $item_id  = db_fetch_cell("select ID from graph_hierarchy_items where sequence=$next_item and parent=$gid","ID");
    $item_seq = db_fetch_cell("select Sequence from graph_hierarchy_items where id=$id","Sequence");
    $sql_id = db_execute("update graph_hierarchy_items set sequence=$next_item where id=$id");
    $sql_id = db_execute("update graph_hierarchy_items set sequence=$item_seq where id=$item_id");
    
    header ("Location: tree_items.php?id=$tid");
    break;
 case 'moveup':
    include_once ('include/functions.php');
    
    $last_item = GetLastItem($cnn_id,"graph_hierarchy_items","sequence",$id,"parent","$gid and treeid=$tid");
    
    $item_id  = db_fetch_cell("select id from graph_hierarchy_items where sequence=$last_item and parent=$gid","ID");
    $item_seq = db_fetch_cell("select sequence from graph_hierarchy_items where id=$id","Sequence");
    $sql_id = db_execute("update graph_hierarchy_items set sequence=$last_item where id=$id");
    $sql_id = db_execute("update graph_hierarchy_items set sequence=$item_seq where id=$item_id");
    
    header ("Location: tree_items.php?id=$tid");
    break;
 case 'remove':
    include_once ('include/tree_functions.php');
    
    $array_tree["options"]["sql_string"] = "select h.id,h.type,h.title,h.parent,g.title as 
					gtitle,r.name as rname from graph_hierarchy_items h left join rrd_graph g on h.graphid=g.id 
					left join rrd_rra r on h.rraid=r.id where h.parent=$branch and h.treeid=$gid  
					order by h.sequence";
    $array_tree["options"]["sql_connection_id"] = $cnn_id;
    $array_tree["options"]["sql_delete_table_name"] = "graph_hierarchy_items";
    $array_tree["options"]["tree_id"] = $gid;
    $array_tree["remove"]["remove_branch"] = true;
    $array_tree["remove"]["branch_to_remove"] = $id;
    
    GrowTree($array_tree);
    
    header ("Location: tree_items.php?id=$gid");
    break;
 case 'edit':
    include_once ("include/top_header.php");
    
    /* get current tree name for the header text */
    $tree_title = db_fetch_cell("select Name from graph_hierarchy where id=$gid","Name");
    
    if ($id != "") {
	$item = db_fetch_row("select * from graph_hierarchy_items where id=$id");
    }
    
    DrawFormHeader("Graph Hierarchy Item Configuration: $tree_title",true,"");
    
    DrawFormItem("Heading","");
    DrawFormItemRadioButton("type", $sql_id, "Heading", "This Item is a Heading.","Heading");
    DrawFormItemTextBox("title",$sql_id,"","");
    
    DrawFormItem("Graph","");
    DrawFormItemRadioButton("type", $sql_id, "Graph", "This Item is a Graph.","Heading");
    DrawFormItemDropdownFromSQL("GraphID",db_fetch_row("select * from rrd_graph order by title"),"Title","ID","None","");
    DrawFormItemDropdownFromSQL("rraid",db_fetch_row("select * from rrd_rra order by name","Name"),"ID","","");
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormItemHiddenTextBox("Parent",$sql_id[Parent],$parent);
    DrawFormItemHiddenIDField("GID",$gid);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    break;
 default:
    include_once ('include/tree_functions.php');
    include_once ('include/top_header.php');
    
    /* get current tree name for the header text */
    $tree_title = db_fetch_cell("select Title from viewing_trees where ID=$id");
    
    ########################################################################
    ##  
    ##  BROKEN!!!!
    ##
    ##  I have no idea what the $sql_id refers to in this section.
    ##  The line below makes an empty array called $item which would 
    ##  normally be populated by the data to be used in the following code.
    ##
    ########################################################################
    $item = array();
    
    DrawMatrixTableBegin("97%");
    DrawMatrixRowBegin();
    DrawMatrixHeaderTop("Graph Hierarchy: $tree_title",$colors[dark_bar],"","2");
    DrawMatrixHeaderAdd($colors[dark_bar],"","tree_items.php?action=edit&parent=0&gid=$id");
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("Tree Item",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Sequence",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
    DrawMatrixRowEnd();
    
    $array_tree["options"]["sql_type_column"] = "type";
    $array_tree["options"]["sql_string"] = "select h.id,h.type,h.title,h.parent,g.title as 
					gtitle,r.name as rname from graph_hierarchy_items h left join rrd_graph g on h.graphid=g.id 
					left join rrd_rra r on h.rraid=r.id where h.parent=$branch and h.treeid=$id 
					order by h.sequence";
    $array_tree["options"]["sql_connection_id"] = $cnn_id;
    $array_tree["options"]["indent"] = "&nbsp;";
    $array_tree["options"]["alternating_row_colors"] = true;
    $array_tree["options"]["tree_id"] = $id;
    $array_tree["options"]["remove_action"] = "tree_items.php?action=remove&gid=$id&id=$item[ID]'";
    $array_tree["options"]["moveup_action"] = "tree_items.php?action=moveup&tid=$id&id=$item[ID]&gid=$item[Parent]'";
    $array_tree["options"]["movedown_action"] = "tree_items.php?action=movedown&tid=$id&id=$item[ID]&gid=$item[Parent]'";
    $array_tree["item_action"]["Heading"] = "<strong><a href=\"tree_items.php?action=edit&gid=$id&id=$item[ID]"
      . "\">$item[title]</a> [<a href=\"tree_items.php?action=edit&gid=$id&parent=$item[ID]\">add</a>]</strong>";
      
    $array_tree["item_action"]["Graph"] = "<a href=\"tree_items.php?action=edit&gid=$id&id=$item[ID]\">GRAPH: $item[Title] - $item[RName]</a>";
    
    GrowTree($id,'');
    
    print "</td></tr>";
    DrawMatrixTableEnd();
    
    include_once ("include/bottom_footer.php");
    break;
} ?>
															  
