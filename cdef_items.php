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
<? 	$section = "Add/Edit Data Sources"; include ('include/auth.php');
header("Cache-control: no-cache");
include_once ('include/form.php');

if ($form[action]) { $action = $form[action]; } else { $action = $args[action]; }
if ($form[ID]) { $id = $form[ID]; } else { $id = $args[id]; }
if ($form[GID]) { $gid = $form[GID]; } else { $gid = $args[id]; }

switch ($action) {
 case 'save':
    include_once ('include/functions.php');
    
    $max_sequence = GetSequence($id, "sequence", "rrd_ds_cdef_item", "cdefid", $gid);

    $sql_id = db_execute("replace into rrd_ds_cdef_item (id,dsid,cdefid,custom,currentds,
							  cdeffunctionid,type,sequence) values ($id,$form[DSID],$gid,\"$form[Custom]\",\"$form[CurrentDS]\",
												$form[CDEFFunctionID],\"$form[Type]\",$max_sequence])");
    
    header ("Location: cdef_items.php?id=$gid");
    break;
 case 'remove':
    db_execute("delete from rrd_ds_cdef_item where id=$id");
    
    header ("Location: cdef_items.php?id=$gid");
    break;
 case 'movedown':
    include_once ('include/functions.php');
    
    $next_item = GetNextItem("rrd_ds_cdef_item","sequence",$id,"cdefid",$gid);
    
    $myid  = db_fetch_cell("select id from rrd_ds_cdef_item where sequence=$next_item and cdefid=$gid");
    $myseq = db_fetch_cell("select sequence from rrd_ds_cdef_item where id=$id");
    db_execute("update rrd_ds_cdef_item set sequence=$next_item where id=$id");
    db_execute("update rrd_ds_cdef_item set sequence=$myseq where id=$myid");
    
    header ("Location: cdef_items.php?id=$gid");
    break;
 case 'moveup':
    include_once ('include/functions.php');
    
    $last_item = GetLastItem($cnn_id,"rrd_ds_cdef_item","sequence",$id,"cdefid",$gid);
    
    $myid  = db_fetch_cell("select id from rrd_ds_cdef_item where sequence=$last_item and cdefid=$gid");
    $myseq = db_fetch_cell("select sequence from rrd_ds_cdef_item where id=$id");
    db_execute("update rrd_ds_cdef_item set sequence=$last_item where id=$id");
    db_execute("update rrd_ds_cdef_item set sequence=$myseq where id=$myid");
    
    header ("Location: cdef_items.php?id=$gid");
    break;
 case 'edit':
    include_once ('include/top_header.php');
    
    if ($id != "") {
	$tmp = db_fetch_assoc("select * from rrd_ds_cdef_item where id=$id");
	$ds = $tmp[0];
	unset($tmp);
    }
    
    DrawFormHeader("rrdtool CDEF Configuration","",false);
    
    DrawFormItem("Data Source","Add a data source to this CDEF.");
    DrawFormItemRadioButton("type",$ds[Type], "Data Source", "Add a Data Source.","Data Source");
    DrawFormItemDropdownFromSQL("DSID",db_fetch_assoc("select * from rrd_ds where isparent=0 order by name"),
				"Name","ID",$ds[DSID],"None","");
    DrawFormItemCheckBox("CurrentDS",$ds[CurrentDS],"Use the current data source being used on the
					       graph instead of a preset one.","");
    
    DrawFormItem("CDEF Function","Add a CDEF function to this CDEF.");
    DrawFormItemRadioButton("type", $sql_id, "CDEF Function", "Add a CDEF Function.","Data Source");
    DrawFormItemDropdownFromSQL("CDEFFunctionID",db_fetch_assoc("select * from def_cdef order by name"),
				"Name","ID",$ds[CDEFID],"","");
    
    DrawFormItem("Custom Entry","Add a custom entry to this CDEF.");
    DrawFormItemRadioButton("Type", $ds[Type], "Custom Entry", "Add a Custom Entry.","Data Source");
    DrawFormItemTextBox("Custom",$ds[Custom],"","");
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormItemHiddenIDField("GID",$gid);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    
    break;
 default:
    include_once ('include/top_header.php');
    
    DrawMatrixTableBegin("97%");
    DrawMatrixRowBegin();
    DrawMatrixHeaderTop("Current Data Sources",$colors[dark_bar],"","4");
    DrawMatrixHeaderAdd($colors[dark_bar],"","cdef_items.php?action=edit&gid=$id");
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("Item",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Type",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Item Value",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Sequence",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
    DrawMatrixRowEnd();
    
    $sql = "select ds.name as DSName, cf.name as CFName, c.ID, c.Type, c.Custom
	     from rrd_ds_cdef_item c left join def_cdef cf on cf.id=c.cdeffunctionid left 
	     join rrd_ds ds on ds.id=c.dsid where c.cdefid=$id order by c.sequence";
    $ds_list = db_fetch_assoc($sql);
    $numrows = sizeof($ds_list);
    
    if ($numrows > 0) {
	foreach ($ds_list as $ds) {
	    ++$i;
	    DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	    DrawMatrixLoopItem("Item #$i",html_boolean($config["vis_main_column_bold"]["value"]),"cdef_items.php?action=edit&gid=$id&id=$ds[ID]");
	    DrawMatrixLoopItem($ds[Type],false,"");
	    
	    switch ($ds[Type]) {
	     case 'Data Source':
		$matrix_title = $ds[DSName];
		break;
	     case 'CDEF Function':
		$matrix_title = $ds[CFName];
		break;
	     case 'Custom Entry':
		$matrix_title = $ds[Custom];
		break;
	    }
	    
	    DrawMatrixLoopItem($matrix_title,false,"");
	    DrawMatrixLoopItem("[<a href=\"cdef_items.php?action=movedown&id=$ds[ID]&gid=$id\">Down</a>], [<a href=\"cdef_items.php?action=moveup&id=$ds[ID]&gid=$id\">Up</a>]",false,"");
	    DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,"cdef_items.php?action=remove&gid=$id&id=$ds[ID]");
	    DrawMatrixRowEnd();
	}
    }
    DrawMatrixTableEnd();
    include_once ("include/bottom_footer.php");
    
    break;
} ?>
