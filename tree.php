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
$section = "Add/Edit Graphs"; 
include ('include/auth.php');

include_once ('include/form.php');

if ($form[action]) { $action = $form[action]; } else { $action = $args[action]; }
if ($form[ID]) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
 case 'save':
    $sql = "replace into viewing_trees (id,title,owner) values ($id,\"$form[Title]\",\"$form[Owner]\")";
    db_execute($sql);
    
    header ("Location: tree.php");
    break;
 case 'remove':
	if (($config["remove_verification"]["value"] == "on") && ($confirm != "yes")) {
		include_once ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this graph hierarchy?", $current_script_name, "?action=remove&id=$id");
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($confirm == "yes")) {
	    db_execute("delete from viewing_trees where id=$id");
	    db_execute("delete from graph_viewing where TreeID=$id");
	    db_execute("delete from auth_graph_hierarchy where hierarchyid=$id");
    }
	
    header ("Location: tree.php");
    break;
 case 'edit':
    include_once ('include/top_header.php');
    
    if ($id != "") {
	$tree = db_fetch_row("select * from viewing_trees where id=$id");
    }
    
    DrawFormHeader("Graph Tree Configuration","",false);
    
    DrawFormItem("Name","The name of this tree; use any name you want to describe what kind of
			  graphs this tree will contain.");
    DrawFormItemTextBox("Title",$tree[Title],"","");
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("Owner",$tree[Owner]);
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    
    break;
 default:
    include_once ('include/top_header.php');
    
    DrawMatrixTableBegin("97%");
    DrawMatrixRowBegin();
    DrawMatrixHeaderTop("Current Graph Trees",$colors[dark_bar],"","2");
    DrawMatrixHeaderAdd($colors[dark_bar],"","tree.php?action=edit");
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("Name",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Edit Graph Tree",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
    DrawMatrixRowEnd();
    
    $gh = db_fetch_assoc("select * from viewing_trees");
    if (sizeof($gh) > 0) {
	foreach ($gh as $item) {
	    DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	    DrawMatrixLoopItem($item[Title],html_boolean($config["vis_main_column_bold"]["value"]),"tree.php?action=edit&id=" . $item[ID]);
	    DrawMatrixLoopItem("Edit Current Graph Tree",false,"tree_items.php?id=" . $item[ID]);
	    DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,"tree.php?action=remove&id=" . $item[ID]);
	    DrawMatrixRowEnd();
	}
    }
    
    DrawMatrixTableEnd();
    include_once ("include/bottom_footer.php");
    
    break;
} ?>
