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
 case 'remove':
    db_execute("delete from src_fields where id=$id");
    db_execute("delete from src_data where fieldid=$id");
    
    header ("Location: data.php?action=edit&id=$fid");
    break;
 case 'save':
    $sql_id = db_execute("replace into src_fields (id,srcid,name,dataname,inputoutput,
						    updaterra) values ($id,$form[FID],\"$form[Name]\",\"$form[DataName]\",\"$form[InputOutput]\",
								       \"$form[UpdateRRA]\")");
    
    if ($id == 0) {
	$id = db_fetch_cell("select LAST_INSERT_ID()");
	
	/* let me explain, if the user adds a new field and this data input source is
	 in use by any number of ds's, data collection will fail because of the missing
	 data corresponding to the newly added field...this is not good, so lets fix it */
	$ds_list = db_fetch_assoc("select * from rrd_ds where srcid=$form[FID]");
	if (sizeof($ds_list) > 0) {
	    foreach ($ds_list as $ds) {
		/* create a null entry in every ds for this field */
		db_execute("replace into src_data (id,fieldid,dsid,value) values (0,$id,$ds[ID],\"\")");
	    }
	}
    }
    
    header ("Location: data.php?action=edit&id=$fid");
    break;
 case 'edit':
    include_once ('include/top_header.php');
    
    if ($id != "") {
	$field = db_fetch_row("select * from src_fields where id=$id");
    }
    
    DrawFormHeader("Data Input Source Configuration","",false);
    
    DrawFormItem("Name","The name of this data source; only used by the front end.");
    DrawFormItemTextBox("Name",$field[Name],"","");
    
    DrawFormItem("Data Name","The name of the data source field used by the frontend to identify each field.");
    DrawFormItemTextBox("DataName",$field[DataName],"","");
    
    DrawFormItem("Input/Output Field","Whether this field is for output; or requires input to be sent to the program.");
    DrawFormItemDropDownCustomHeader("InputOutput");
    DrawFormItemDropDownCustomItem("InputOutput","in","Input",$field[InputOutput]);
    DrawFormItemDropDownCustomItem("InputOutput","out","Output",$field[InputOutput]);
    DrawFormItemDropDownCustomItem("InputOutput","hcin","HC Input",$field[InputOutput]);
    DrawFormItemDropDownCustomItem("InputOutput","hcout","HC Output",$field[InputOutput]);
    DrawFormItemDropDownCustomFooter();
    
    DrawFormItem("Use for RRA","Whether this data source field should be used to update the RRA with; there can only be one of these fields per data input source and it must be an output field.");
    DrawFormItemCheckBox("UpdateRRA",$field[UpdateRRA],"Field Used to Update RRA","");
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormItemHiddenIDField("FID",$fid);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    break;
} ?>
