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
$section = "Add/Edit Data Sources"; 
include ('include/auth.php');
include_once ("include/functions.php");
include_once ('include/form.php');

if ($form[action]) { $action = $form[action]; } else { $action = $args[action]; }
if ($form[id]) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
 case 'save':
    /* ok, first pull out all 'input' values so we know how much to save */
    $ds_list = db_fetch_assoc("select d.SrcID, f.ID, f.InputOutput, f.DataName from rrd_ds d left 
				join src_fields f on d.srcid=f.srcid where d.id=$id and f.inputoutput=\"in\"");
    if (sizeof($ds_list) > 0) {
	foreach ($ds_list as $ds) {
	    
	    /* then, check and see if this value already exists */
	    $myid = db_fetch_cell("select id from src_data where fieldid=$ds[ID] and dsid=$id");
	    
	    /* use id 0 if it doesn't; previd if it does */
	    if ($myid <= 0) {
		$new_id = 0;
	    }else{
		$new_id = $myid;
	    }
	    
	    /* save the data into the src_data table */
	    $sql_id_save = db_execute("replace into src_data (id,fieldid,dsid,value) values
					($new_id,$myid,$id,\"$ds[ID]" .	${$ds[DataName]} . "\")");
	}
    }
    header ("Location: ds.php");
    break;
 default:
    include_once ('include/rrd_functions.php');
    include_once ("include/top_header.php");
    
    $fields = db_fetch_assoc("select * from src_fields where srcid=$did and inputoutput=\"in\" order by name");
    
    DrawFormHeader("rrdtool Data Source Configuration",false,false);
    DrawFormPreformatedText($colors[panel], GetCronPath($id) . "\n" . rrdtool_function_update($id, "", true));
    if (sizeof($fields) > 0) {
	foreach ($fields as $field) {
	    $data = db_fetch_row("select * from src_data where DSID=$id and FieldID=$field[ID]");
	    
	    DrawMatrixRowBegin();
	    DrawMatrixCustom("<td bgcolor=\"#$colors[panel]\"><strong>$ds[Name]</strong></td>");
	    DrawMatrixRowEnd();
	    
	    if (sizeof($data) > 0) {
		$old_value = $data[Value];
	    }else{
		$old_value = "";
	    }
	    
	    DrawFormItemTextBox("Value",$old_value,"","");
	    
	    $i++;
	}
    }
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("id",$id);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    break;
} 

?>
