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
<? 	$section = "Add/Edit Round Robin Archives"; include ('include/auth.php');
header("Cache-control: no-cache");
include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
 case 'save':
    $sql_id = db_execute("replace into rrd_rra (id,name,xfilesfactor,steps,rows) 
      values ($id,\"$form[Name]\",$form[XFilesFactor],$form[Steps],$form[RRA_Rows])");
    
    if ($id == 0) {
	/* get rraid if this is a new save */
	$id = db_fetch_cell("select LAST_INSERT_ID()");
    }
    
    $sql_id = db_execute("delete from lnk_rra_cf where rraid=$id"); 
    $i = 0;
    while ($i < count($form[ConsolidationFunctionID])) {
	db_execute("insert into lnk_rra_cf (rraid,consolidationfunctionid) 
	  values ($id,".$form[ConsolidationFunctionID][$i].")");
	$i++;
    }
    
    header ("Location: rra.php");
    break;
 case 'remove':
	if (($config["remove_verification"]["value"] == "on") && ($confirm != "yes")) {
		include_once ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this round robin archive?", $current_script_name, "?action=remove&id=$id");
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($confirm == "yes")) {
		db_execute("delete from rrd_rra where id=$id");
		db_execute("delete from lnk_ds_rra where rraid=$id");
    }
	
    header ("Location: rra.php");
    break;
 case 'edit':
    include_once ('include/top_header.php');
    
    if ($id != "") {
	$rra = db_fetch_row("select * from rrd_rra where id=$id");
    }
    
    DrawFormHeader("rrdtool Round Robin Archive (RRA) Configuration","",false);
    
    DrawFormItem("Name","The name of the RRA, this name is not used by rrdtool.");
    DrawFormItemTextBox("Name",$rra[Name],"","");
    
    DrawFormItem("Consolidation Functions","How data is to be entered in RRA's.");
    DrawFormItemMultipleList("ConsolidationFunctionID","select * from def_cf","Name","ID",
			     "select * from lnk_rra_cf where rraid=$id","ConsolidationFunctionID");
    
    DrawFormItem("X-Files Factor","The amount of unknown data that can still be regarded as known.");
    DrawFormItemTextBox("XFilesFactor",$rra[XFilesFactor],"0.5","");
    
    DrawFormItem("Steps","How many data points are needed to put data into the RRA.");
    DrawFormItemTextBox("Steps",$rra[Steps],"","");
    
    DrawFormItem("Rows","How many generations data is kept in the RRA.");
    DrawFormItemTextBox("rra_rows",$rra[Rows],"","");
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("id",$id);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    
    break;
 default:
    include_once ('include/top_header.php');
    
    DrawMatrixTableBegin("97%");
    DrawMatrixRowBegin();
    DrawMatrixHeaderTop("Current Round Robin Archives (RRA's)",$colors[dark_bar],"","3");
    DrawMatrixHeaderAdd($colors[dark_bar],"","rra.php?action=edit");
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("Name",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Steps",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Rows",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
    DrawMatrixRowEnd();
    
    $rra_list = db_fetch_assoc("select ID,Name,Rows,Steps from rrd_rra order by steps");
    $i = 0;
    if (sizeof($rra_list) > 0) {
	foreach ($rra_list as $rra) {
	    DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	    DrawMatrixLoopItem($rra[Name],html_boolean($config["vis_main_column_bold"]["value"]),"rra.php?action=edit&id=$rra[ID]");
	    DrawMatrixLoopItem($rra[Steps],false,"");
	    DrawMatrixLoopItem($rra[Rows],false,"");
	    DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,"rra.php?action=remove&id=$rra[ID]");
	    DrawMatrixRowEnd();
	    $i++;
	}
    }
    DrawMatrixTableEnd();
    include_once ("include/bottom_footer.php");
    
    break;
} ?>
