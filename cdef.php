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
   | - 2/8/2002 - Dave Neitz, Sprint E|Solutionns                            |
   |   Added 3 new CDEF special types to the CDEF drop down list box:        |
   |                                                                         |
   |   Type  Description                                                     |
   |   ----  -----------                                                     |
   |    3    Staggered Total of Data Sources on a Graph                      |
   |    4    Average of All Data Sources on a Graph                          |
   |    5    Staggered Average of Data Sources on a Graph                    |
   +-------------------------------------------------------------------------+
   */?>
<? 
$section = "Add/Edit Data Sources"; 
include ('include/auth.php');
header("Cache-control: no-cache");
include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
 case 'save':
    $sql_id = db_execute("replace into rrd_ds_cdef (id,name,type) values ($id,\"$form[Name]\",$form[Type])");
	
    header ("Location: cdef.php");
    break;
 case 'remove':
	if (($config["remove_verification"]["value"] == "on") && ($confirm != "yes")) {
		include_once ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this CDEF?", $current_script_name, "?action=remove&id=$id");
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($confirm == "yes")) {
	    db_execute("delete from rrd_ds_cdef where id=$id");
	    db_execute("delete from rrd_ds_cdef_item where cdefid=$id");
    }
	
    header ("Location: cdef.php");
    break;
 case 'edit':
    include_once ('include/top_header.php');
    
    if ($id != "") {
	$row = db_fetch_assoc("select * from rrd_ds_cdef where id=$id");
	$cdef = $row[0];
    }
    
    DrawFormHeader("rrdtool Data Source Configuration","",false);
    
    DrawFormItem("Name","The name of this CDEF function, only used for internal purposes.");
    DrawFormItemTextBox("Name",$cdef[Name],"","");
    
    DrawFormItem("CDEF Type","Always select \"Normal\", unless you want to create a special CDEF.");
    DrawFormItemDropDownCustomHeader("Type");
    DrawFormItemDropDownCustomItem("Type","1","Normal",$cdef[Type]);
    DrawFormItemDropDownCustomItem("Type","2","Total of All Data Sources on a Graph",$cdef[Type]);
    DrawFormItemDropDownCustomItem("Type","3","Staggered Total of Data Sources on a Graph",$cdef[Type]);
    DrawFormItemDropDownCustomItem("Type","4","Average of All Data Sources on a Graph",$cdef[Type]);
    DrawFormItemDropDownCustomItem("Type","5","Staggered Average of Data Sources on a Graph",$cdef[Type]);
    DrawFormItemDropDownCustomFooter();
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    
    break;
 default:
    include_once ('include/top_header.php');
    
    DrawMatrixTableBegin("97%");
    DrawMatrixRowBegin();
    DrawMatrixHeaderTop("Current CDEF Functions",$colors[dark_bar],"","2");
    DrawMatrixHeaderAdd($colors[dark_bar],"","cdef.php?action=edit");
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("Name",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Edit CDEF",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
    DrawMatrixRowEnd();
    
    $rows = db_fetch_assoc("select * from rrd_ds_cdef", $cnn_id);
    $numrows = sizeof($rows);
    
    foreach ($rows as $cdef) {
	++$i;
	DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	DrawMatrixLoopItem($cdef[Name],html_boolean($config["vis_main_column_bold"]["value"]),"cdef.php?action=edit&id=$cdef[ID]");
	
	if ($cdef[Type] == "1") { /* normal cdef */
	    $matrix_url = "cdef_items.php?id=$cdef[ID]";
	}else{
	    $matrix_url = "";
	}
	
	DrawMatrixLoopItem("Edit Current CDEF",false,$matrix_url);
	DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,"cdef.php?action=remove&id=$cdef[ID]");
	DrawMatrixRowEnd();
    }
    
    DrawMatrixTableEnd();
    include_once ("include/bottom_footer.php");
    
    break;
} ?>
	
