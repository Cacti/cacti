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
$section = "Data Input"; 
include ('include/auth.php');
header("Cache-control: no-cache");
include_once ('include/form.php'); 

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
 case 'save':
    $sql_id = db_execute("replace into src (id,name,formatstrin,formatstrout,type) values 
			   ($id,\"$form[Name]\",\"$form[FormatStriIn]\",\"$form[FormatStrOut]\",\"$form[Type]\")");
    
    /* if new save, then return to edit page so user can add data fields... */
    if ($id == 0) {
		header("Location: data.php?action=edit&id=" .db_fetch_cell("select LAST_INSERT_ID()"));
		exit;
    }else{
		header("Location: data.php"); exit;
    }
    
    break;
 case 'remove':
	if (($config["remove_verification"]["value"] == "on") && ($confirm != "yes")) {
		include_once ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this data input source?", $current_script_name, "?action=remove&id=$id");
		exit;
	}
    
	if (($config["remove_verification"]["value"] == "") || ($confirm == "yes")) {
		$id_list = db_fetch_assoc("select d.ID from src s left join src_fields f on s.id=f.srcid left join src_data d on f.id=d.fieldid where s.id=$id");
	    
		if (sizeof($id_list) > 0) {
			foreach ($id_list as $myid) {
			    db_execute("delete from src_data where id=$myid");
			}
	    }
	    
	    db_execute("delete from src_fields where srcid=$id");
	    db_execute("delete from src where id=$id");
    }
	
    header ("Location: data.php");
    break;
 case 'edit':
    include_once ('include/top_header.php');
    
    if ($id != "") {
	$src = db_fetch_row("select * from src where id=$id");
    }
    
    DrawFormHeader("Data Input Source Configuration","",false);
    
    DrawFormItem("Name","The name of this data source; only used by the front end.");
    DrawFormItemTextBox("Name",$src[Name],"","");
    
    DrawFormItem("Input String","The data that in sent; which includes the filename and input sources in <> brackets.");
    DrawFormItemTextBox("FormatStrIn",$src[FormatStrIn],"","");
    
    DrawFormItem("Output String","The data that is returned from the input program; defined as <> brackets.");
    DrawFormItemTextBox("FormatStrOut",$src[FormatStrOut],"","");
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormItemHiddenTextBox("action","save","");
    DrawFormItemHiddenTextBox("Type",$src[Type],"");
    DrawMatrixCustom("</form>");
    
    /* fields */
    if ($id != "") {
	DrawMatrixTableBegin("97%");
	DrawMatrixRowBegin();
	DrawMatrixHeaderTop("Current Data Input Source Fields",$colors[dark_bar],"","4");
	DrawMatrixHeaderAdd($colors[dark_bar],"","data_fields.php?action=edit&fid=$id");
	DrawMatrixRowEnd();
	
	DrawMatrixRowBegin();
	DrawMatrixHeaderItem("Name",$colors[panel],$colors[panel_text]);
	DrawMatrixHeaderItem("Data Name",$colors[panel],$colors[panel_text]);
	DrawMatrixHeaderItem("Input/Output",$colors[panel],$colors[panel_text]);
	DrawMatrixHeaderItem("Update RRA",$colors[panel],$colors[panel_text]);
	DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
	DrawMatrixRowEnd();
	
	$fields = db_fetch_assoc("select * from src_fields where srcid=$id order by name");
	if (sizeof($fields) > 0) {
	    foreach ($fields as $field) {
		
		DrawMatrixRowBegin();
		switch ($field[InputOutput]) {
		 case 'in':
		    $inputoutput = "Input";
		    break;
		 case 'out':
		    $inputoutput = "Output";
		    break;
		}
		
		switch ($field[UpdateRRA]) {
		 case '':
		    $updaterra = "No";
		    break;
		 case 'on':
		    $updaterra = "<font color=\"#FF0000\">Yes</font>";
		    break;
		}
	    
		DrawMatrixLoopItem($field[Name],html_boolean($config["vis_main_column_bold"]["value"]),"data_fields.php?action=edit&id=$field[ID]&fid=$id");
		DrawMatrixLoopItem($field[DataName],false,"");
		DrawMatrixLoopItem($inputoutput,false,"");
		DrawMatrixLoopItem($updaterra,false,"");
		
		DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,"data_fields.php?action=remove&id=$field[ID]&fid=$id");
		DrawMatrixRowEnd();
	    }
	}
	DrawMatrixTableEnd();
    }
	
    include_once ("include/bottom_footer.php");
    
    break;
 default:
    include_once ('include/top_header.php');
    
    DrawMatrixTableBegin("97%");
    DrawMatrixRowBegin();
    DrawMatrixHeaderTop("Current Data Input Sources",$colors[dark_bar],"","1");
    DrawMatrixHeaderAdd($colors[dark_bar],"","data.php?action=edit");
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("Name",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
    DrawMatrixRowEnd();
    
    $src_list = db_fetch_assoc("select * from src order by name");
    $rows = sizeof($src_list);
    $i = 0;
    if (sizeof($src_list) > 0) {
	foreach ($src_list as $src) {
	    
	    DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	    DrawMatrixLoopItem($src[Name],html_boolean($config["vis_main_column_bold"]["value"]),"data.php?action=edit&id=$src[ID]");
	    
	    if ($src[Type] == "") {
		$matrix_remove = "data.php?action=remove&id=$src[ID]";
	    } else {
		$matrix_remove = "";
	    }
	
	    DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,$matrix_remove);
	    DrawMatrixRowEnd();
	    $i++;
	}
    }
    
    DrawMatrixTableEnd();
    include_once ("include/bottom_footer.php");
    
    break;
} 
?>
