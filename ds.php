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
<? 	$section = "Add/Edit Data Sources"; 
include ('include/auth.php');
include_once ('include/functions.php');
include_once ('include/form.php');
include_once('include/tree_functions.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

$user_id = GetCurrentUserID($HTTP_SESSION_VARS['user_id'], $config["guest_user"]["value"]);

if (isset($args[hide]) == true) {
    /* find out if the current user has rights here */
    $graph_settings = db_fetch_cell("select GraphSettings from auth_users where id=$user_id");
    
    /* only update expand/contract info is this user has writes to keep their own settings */
    if ($graph_settings == "on") {
	db_execute("delete from settings_ds_tree where treeitemid=$args[branch_id] and userid=$user_id");
	db_execute("insert into settings_ds_tree (treeitemid,userid,status) values ($args[branch_id],$user_id,$args[hide])");
    }
}


switch ($args[action]) {
 case 'move_up': 
    $order_key = db_fetch_cell("SELECT order_key FROM polling_tree WHERE ptree_id=$args[branch_id]");
    if ($order_key > 0) { branch_up($order_key, 'polling_tree', 'order_key', '', 'ptree_id'); }    
    header ("Location: $PHP_SELF");
    break;
 case 'move_down':
    $order_key = db_fetch_cell("SELECT order_key FROM polling_tree WHERE ptree_id=$args[branch_id]");
    if ($order_key > 0) { branch_down($order_key, 'polling_tree', 'order_key', '', 'ptree_id'); }
    header ("Location: $PHP_SELF");
    break;
 case 'duplicate':
    include_once ('include/utility_functions.php');
    
    $id = DuplicateDataSource($id);
    
    header ("Location: $PHP_SELF?action=edit&id=$id");
    break;
 case 'save':
    /* do some error checking here */
    if ($dsname != "") {
		if (CleanUpName($dsname) != $dsname) {
		    header ("Location: $PHP_SELF?action=edit&id=$id&error=1");
		    exit;
		}
    }
    
    /* take care of renaming ds in the .rrd if the user wants it */
    if ($update_rrd == "on") {
		include_once ('include/rrd_functions.php');
		
		$rrd_tune_array["data_source_id"] = $id;
		$rrd_tune_array["heartbeat"] = $form[Heartbeat];
		$rrd_tune_array["minimum"] = $form[MinValue];
		$rrd_tune_array["maximum"] = $form[MaxValue];
		$rrd_tune_array["data-source-type"] = $form[DataSourceTypeID];
		$rrd_tune_array["data-source-rename"] = $form[DSName];
		
		rrdtool_function_tune($rrd_tune_array);
    }
    
    $sql_id = db_execute("replace into rrd_ds (id,name,datasourcetypeid,heartbeat,
		minvalue,maxvalue,srcid,active,dsname,dspath,step,subdsid,subfieldid,isparent) 
      	values ($id,\"$form[Name]\",$form[DataSourceTypeID],$form[Heartbeat],$form[MinValue],$form[MaxValue],$form[SrcID],
		\"$form[Active]\",\"$form[DSName]\",\"$form[DSPath]\",$form[Step],$form[SubDSID_Old],$form[SubFieldID],$formIsParent])");
    
    if ($id == 0) {
		/* get dsid if this is a new save */
		$id = db_fetch_cell("select LAST_INSERT_ID()");
		
		if ($id == 0) {
		    unset($id);
		}
    }
    
    /* update table with rra info */
    $sql_id = db_execute("delete from lnk_ds_rra where dsid=$id"); 
    
    for ($i = 0; $i < count($rraid); $i++) {
		db_execute("insert into lnk_ds_rra (dsid,rraid) values ($id,$rraid[$i])");
    }
    
    /* NEW: If this data source uses a data input source that has multiple outputs
	we must create the appropriate cacti data sources here! The following settings will
	be copied from the settings just entered to each data source we have to create:
	
     - Data Source Type
     - Heartbeat
     - Minimum Value
     - Maximum Value
	
	Additionally, to get the 'Name' field, we will append the data input source field
	name to the current 'Name' field. The 'Internal Data Source Name' will simply by
	default be the data input source field name.
	
	*/
    
    if ($SrcID != $SrcID_Old) {
		/* ------------------ CLEANUP ------------------ */
		/* if the data input source changed, make sure to delete any children this
		ds has */
		
		if ($ID != "0") {
			db_execute("delete from rrd_ds where subdsid=$id");
			
			/* ... same goes for the items in src_data */
			db_execute("delete from src_data where dsid=$id");
			
			/* we have to make sure to kill the children's DS's in src_data */
			$id_list = db_fetch_assoc("select ID from rrd_ds where subdsid=$id");
			
			if (sizeof($id_list) > 0) {
			    foreach ($id_list as $myid) {
				db_execute("delete from src_data where dsid=$myid[ID]");
			    }
			}
		}
		
		/* ------------------ CLEANUP ------------------ */
		
		/* Test if the current data input source has more than one output */
		$fields = db_fetch_assoc("select ID,DataName 
			from src_fields 
			where srcid=$form[SrcID] 
			and inputoutput=\"out\"
			and updaterra=\"on\"
			order by id");
		if (sizeof($fields) > 0) {
		    
		    /* Yes, it has more than one output.... */
		    $data_source_is_parent = "1";
		    
		    /* Loop through each output and create a sub-data source for the data 
		     source that has just been created */
		    foreach ($fields as $field) {
				db_execute("replace into rrd_ds 
					(id,subdsid,subfieldid,name,datasourcetypeid,heartbeat,minvalue,maxvalue,srcid,active,
					dsname,dspath,step
					) values (0,$id,$field[ID],\"$name" . "_" . "$field[DataName]\",
					$form[DataSourceTypeID],$form[Heartbeat],$form[MinValue],$form[MaxValue],0,\"on\",\"" . 
					CheckDataSourceName($field[DataName]) . "\",\"\",0)");
	    	}
		}else{
			$data_source_is_parent = "0";
		}
		
		db_execute("update rrd_ds set isparent=$data_source_is_parent where id=$id");
    }
    
    /* this will do any of the cleanup that is required on the data source name to make
     sure rrdtool is ok with it. */
    SyncDataSourceName($id, $form[DSName], $form[DSPath]);
    
    header ("Location: $PHP_SELF");
    break;
 case 'remove':
	if (($config["remove_verification"]["value"] == "on") && ($confirm != "yes")) {
		include_once ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this data source?", $current_script_name, "?action=remove&id=$id");
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($confirm == "yes")) {
		if ($id != "0") {
			db_execute("delete from rrd_ds where id=$id");
		    db_execute("delete from rrd_ds where subdsid=$id");
		    db_execute("delete from lnk_ds_rra where dsid=$id");
		    db_execute("delete from src_data where dsid=$id");
		    
		    /* we have to make sure to kill the children's DS's in src_data */
		    $id_list = db_fetch_assoc("select ID from rrd_ds where subdsid=$id");
		    
		    if (sizeof($id_list) > 0) {
				foreach ($id_list as $id) {
			    	db_execute("delete from src_data where dsid=$id[ID]");
				}
		    }
		}
	}
    
    header ("Location: $PHP_SELF");
    break;
 case 'edit':
    include_once ('include/rrd_functions.php');
    include_once ('include/top_header.php');
    
    if ($id != "") {
	$ds = db_fetch_row("select * from rrd_ds where id=$id");
	
	$srcid_old = $ds[SrcID];
	$sub_data_source_id = $ds[SubDSID];
    }
    
    DrawFormHeader("rrdtool Data Source Configuration","",false);
    
    if ($id != "") {
	switch ($error) {
	 case '1':
	    $dsname_error .= "<font color=\"red\">YOU HAVE ENTERED AN INVALID NAME!</font>\n\n";
	    break;
	}
	
	/* check to see if the current data source exists */
	$data_source_path = GetDataSourcePath($id, true);
	
	if (file_exists($data_source_path) == false) {
	    $warning_text .= "<font color=\"red\">CANNOT FIND DATA SOURCE: $data_source_path</font>\n\n";
	}
	
	/* see if the data source has any rra's */
	$rras = db_fetch_assoc("select * from lnk_ds_rra where dsid=$id");
	
	if (! sizeof($rras) >  0) {
	    $warning_text .= "<font color=\"red\">THIS DATA SOURCE NEEDS TO HAVE AT LEAST ONE RRA ASSOCIATED WITH IT!</font>\n\n";
	}
	
	if ($sub_data_source_id == 0) {
	    DrawFormPreformatedText($colors[panel], $warning_text . rrdtool_function_create($id, true));
	}
    }
    
    DrawFormItem("Name","The name used for RRA files, among other things; no spaces or strange characters.");
    DrawFormItemTextBox("Name",$ds[Name],"","255");
    
    DrawFormItem("$dsname_error (Optional) Internal Data Source Name","The name of the data source used by rrdtool, by default this name is the same as the Data Source Name. However it may need to be changed for compatability with other RRA's.");
    DrawFormItemTextBox("DSName",$ds[DSName],"","19");
    
    if ($sub_data_source_id == 0) {
	DrawFormItem("(Optional) Data Source Path","Used only if the data source lies in a different directory; you must include_once the full path to the RRA file.");
	DrawFormItemTextBox("DSPath",$ds[DSPath],"","");
    } else {
	DrawFormItemHiddenTextBox("DSPath","","");
    }
    
    if ($sub_data_source_id == 0) {
	DrawFormItemHiddenTextBox("SubFieldID","0","0");
    } else {
	DrawFormItem("Output Field","Which output value is associated with this data 
				      source.");
	
	$mysrcid = db_fetch_cell("select SrcID from rrd_ds where id=$sub_data_source_id");
	DrawFormItemDropdownFromSQL("SubFieldID",db_fetch_assoc("select id,concat(name,' (',dataname,')') as Name from src_fields where
								  srcid=$mysrcid and inputoutput=\"out\""),"Name","ID",$ds[SubFieldID],"","");
    }
    
    DrawFormItem("Data Source Type","The type of data that is being represented.");
    DrawFormItemDropdownFromSQL("DatasourceTypeID",db_fetch_assoc("select * from def_ds order by name"),"Name","ID",$ds[DataSourceTypeID],"","");
    
    if ($sub_data_source_id == 0) {
	DrawFormItem("Data Input Type","What is used to gather the data for use in RRA files and on graphs.");
	DrawFormItemDropdownFromSQL("SrcID",db_fetch_assoc("select * from src order by name"),"Name","ID",$ds[SrcID],"None","");
    }else{
	DrawFormItemHiddenTextBox("SrcID","0","0");
    }
    
    if ($sub_data_source_id == 0) {
	DrawFormItem("Associated RRA's","Which RRA's to use when entering data. (It is recommended that you select all of these values)");
	DrawFormItemMultipleList("RRAID","select Name,ID from rrd_rra","Name","ID",
				 "select * from lnk_ds_rra where dsid=$id","RRAID");
    }else{
	/* nothing */
    }
    
    DrawFormItem("Heartbeat","The maximum amount of time that can pass before data is entered as \"unknown\". (Usually 600)");
    DrawFormItemTextBox("Heartbeat",$ds[Hearbeat],"600","");
    
    if ($sub_data_source_id == 0) {
	DrawFormItem("Step","Specifies the base interval in seconds with which data will be fed into the .rrd file. (Usually 300)");
	DrawFormItemTextBox("Step",$ds[Step],"300","");
    }else{
	DrawFormItemHiddenTextBox("step","0","0");
    }
    
    DrawFormItem("Minimum Value","The minimum value of data that is allowed to be collected.");
    DrawFormItemTextBox("MinValue",$ds[MinValue],"0","");
    
    DrawFormItem("Maximum Value","The maximum value of data that is allowed to be collected (number must be smaller than max value).");
    DrawFormItemTextBox("MaxValue",$ds[MaxValue],"1","");
    
    if ($sub_data_source_id == 0) {
	DrawFormItem("Update .rrd File","Update the .rrd file in addition the cacti's database when saving these changes.");
	DrawFormItemCheckBox("update_rrd","","Update Changes in .rrd File","");
    }else{
	DrawFormItemHiddenTextBox("update_rrd","","");
    }
    
    if ($sub_data_source_id == 0) {
	DrawFormItem("Active","Whether data is currently be collected for this data source.");
	DrawFormItemCheckBox("Active",$ds[Active],"Data Source Currently Active","on");
    }else{
	DrawFormItemHiddenTextBox("active","on","on");
    }
    
    DrawFormSaveButton();
    DrawFormItemHiddenTextBox("SrcID_Old",$srcid_old,"0");
    DrawFormItemHiddenTextBox("SubDSID_Old",$sub_data_source_id,"0");
    DrawFormItemHiddenIDField("IsParent",$ds[IsParent]);
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    
    break;
 default:
    include_once ('include/top_header.php');

include_once ('include/form.php');
    include_once ('include/tree_view_functions.php');
    
    grow_polling_tree($start_branch, $user_id, $tree_parameters);
    
    include_once ("include/bottom_footer.php");
    
    break;
} ?>
