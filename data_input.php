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
header("Cache-control: no-cache");

include_once ("include/functions.php");
include_once ("include/cdef_functions.php");
include_once ("include/config_arrays.php");
include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'item_movedown':
		item_movedown();
		
		header ("Location: cdef.php?action=edit&id=$args[cdef_id]");
		break;
	case 'item_moveup':
		item_moveup();
		
		header ("Location: cdef.php?action=edit&id=$args[cdef_id]");
		break;
	case 'item_remove':
		item_remove();
	    
		header ("Location: cdef.php?action=edit&id=$args[cdef_id]");
		break;
	case 'item_edit':
		include_once ("include/top_header.php");
		
		item_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'remove':
		data_remove();
		
		header ("Location: cdef.php");
		break;
	case 'edit':
		include_once ("include/top_header.php");
		
		data_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		data();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_cdef_preview($cdef_id) {
	global $colors; ?>
	<tr bgcolor="#<?print $colors[panel];?>">
		<td>
			<pre><?print get_cdef($cdef_id);?></pre>
		</td>
	</tr>	
<?}


/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $form;
	
	if (isset($form[save_component_data_input])) {
		data_save();
		return "data_input.php";
	}elseif (isset($form[save_component_item])) {
		item_save();
		return "data_input.php?action=edit&id=$form[data_input_id]";
	}
}

/* --------------------------
    CDEF Item Functions
   -------------------------- */

function item_remove() {
	global $args;
	
	db_execute("delete from cdef_items where id=$args[id]");	
}

function item_save() {
	global $form;
	
	if ($form[value_function] != "0") { $current_type = 1; $current_value = $form[value_function]; }
	if ($form[value_operator] != "0") { $current_type = 2; $current_value = $form[value_operator]; }
	if ($form[value_data_source] != "0") { $current_type = 3; $current_value = $form[value_data_source]; }
	if ($form[value_special_data_source] != "0") { $current_type = 4; $current_value = $form[value_special_data_source]; }
	if ($form[value_cdef] != "0") { $current_type = 5; $current_value = $form[value_cdef]; }
	if ($form[value_custom] != "") { $current_type = 6; $current_value = $form[value_custom]; }
	
	if (!(isset($current_type))) {
		/* YOU MUST SELECT SOMETHING */
		header ("Location: cdef.php?action=edit&id=$form[cdef_id]"); exit;
	}
	
	$sequence = get_sequence($form[id], "sequence", "cdef_items", "cdef_id=$form[cdef_id]");
	
 	$save["id"] = $form["id"];
	$save["cdef_id"] = $form["cdef_id"];
	$save["sequence"] = $sequence;
	$save["type"] = $current_type;
	$save["value"] = $current_value;
	
	sql_save($save, "cdef_items");	
}

function item_edit() {
	global $args, $colors, $cdef_functions, $cdef_operators, $custom_data_source_types;
	
	if (isset($args[id])) {
		$cdef = db_fetch_row("select * from cdef_items where id=$args[id]");
		$current_type = $cdef[type];
		$values[$current_type] = $cdef[value];
	}else{
		unset($cdef);
	}
	
	start_box("", "", "");
	draw_cdef_preview($args[cdef_id]);
	end_box();
	
	start_box("<strong>CDEF Items [edit]</strong> (" . db_fetch_cell("select name from cdef where id=$cdef_id") . ")", "", "");
	
	?>
	<form method="post" action="cdef.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td colspan="2">
			<font class="textHeader">Choose any one of these items:</font>
		</td>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Function</font>
		</td>
		<?DrawFormItemDropdownFromSQL("value_function",$cdef_functions,"","",$values[1],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Operator</font>
		</td>
		<?DrawFormItemDropdownFromSQL("value_operator",$cdef_operators,"","",$values[2],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Data Source</font>
		</td>
		<?DrawFormItemDropdownFromSQL("value_data_source",db_fetch_assoc("select descrip,item_id from polling_items"),"descrip","item_id",$values[3],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Special Data Source</font>
		</td>
		<?DrawFormItemDropdownFromSQL("value_special_data_source",$custom_data_source_types,"","",$values[4],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Another CDEF</font>
		</td>
		<?DrawFormItemDropdownFromSQL("value_cdef",db_fetch_assoc("select name,id from cdef"),"name","id",$values[5],"None","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Custom String</font>
		</td>
		<?DrawFormItemTextBox("value_custom",$values[6],"","150", "40");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("id",$args[id]);
	DrawFormItemHiddenIDField("cdef_id",$args[cdef_id]);
	DrawFormItemHiddenTextBox("save_component_item","1","");
	end_box();
	
	start_box("", "", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "cdef.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();	
}
   
/* ---------------------
    CDEF Functions
   --------------------- */

function data_remove() {
	global $args, $config;
	
	if (($config["remove_verification"]["value"] == "on") && ($args[confirm] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the data input method <strong>'" . db_fetch_cell("select name from data_input where id=$args[id]") . "'</strong>?", getenv("HTTP_REFERER"), "data_input.php?action=remove&id=$args[id]");
		include ('include/bottom_footer.php');
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($args[confirm] == "yes")) {
		db_execute("delete from data_input where id=$args[id]");
		db_execute("delete from data_input_fields where data_input_id=$args[id]");
		db_execute("delete from data_input_data where data_input_id=$args[id]");
	}
}

function data_save() {
	include("include/config_arrays.php");
	
	global $form;
	
	/* first, find out if any fields have been added -- get a list from POST and compare it to
	what we've got from the database
	
	-- new in 0.8: field names must match [_a-zA-Z], so users *could* include gt's and lt's
	in their input/output strings by escaping them (ie. \< \>) */
	
	if (preg_match_all("/<([_a-zA-Z0-9]+)>/", $form["input_string"] . $form["output_string"], $matches)) {
		for ($i=0; ($i < count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names) == false) {
				if (!db_fetch_cell("select id from data_input_fields where data_name='" . $matches[1][$i] . "' and data_input_id=$form[id]")) {
					/* new field -- not found in db */
					if (!$drew_field) {
						include_once ("include/top_header.php");
						print "<form method='post' action='data_input.php'>";
						
						start_pagebox("", "98%", "00438C", "3", "center", "");
						print "<tr><td bgcolor='#00438C' style='color: white;'><font style='color: white;' class='textInfo'>New Fields Detected</font><br>\n";
						print "Cacti has found additional fields for this data input method. Before cacti
						can use use fields you must fill in additional information about them below.\n";
						print "</td></tr>\n";
						end_box();
						
						$drew_field = true;
					}
					
					$j = 0;
					
					start_pagebox("", "98%", "aaaaaa", "3", "center", "");
					DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$j); $j++;
						print "<td width='50%'><font class='textEditTitle'>Field Name</font><br></td>\n";
						print "<td>" . $matches[1][$i] . "</td>\n";
					print "</tr>\n";
					
					DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$j); $j++;
						print "<td width='50%'><font class='textEditTitle'>Field Type</font><br></td>\n";
						if (strstr($form["input_string"], "<" . $matches[1][$i] . ">") != false) {  print "<td>Input</td>\n"; }else{ print "<td>Output</td>\n"; }
					print "</tr>\n";
					
					if (strstr($form["input_string"], "<" . $matches[1][$i] . ">") == false) {
						DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$j); $j++;
							print "<td width='50%'>\n";
								print "<font class='textEditTitle'>Update RRD File</font><br>\n";
								print "Whether data from this output field is to be entered into the rrd file.";
							print "</td>\n";
							DrawFormItemCheckBox("active",$data[active],"Update RRD File","on",$args[local_data_id]);
						print "</tr>\n";
					}
					
					DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$j); $j++;
						print "<td width='50%'>\n";
							print "<font class='textEditTitle'>Name</font><br>\n";
							print "Enter a meaningful name for this data input method.";
						print "</td>\n";
						DrawFormItemTextBox("name",$data_input[name],"","200", "30");
					print "</tr>\n";
					end_box();
				}
			}
		}
	}
	
	if ($drew_field == true) {
		/* if we started a page; finish it */
		start_box("", "", "");
		?>
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right">
				<?DrawFormSaveButton("save", "data_input.php");?>
			</td>
		</tr>
		</form>
		<?
		end_box();
		
		include_once("include/bottom_footer.php");
	}
	
	$save["id"] = $form["id"];
	$save["name"] = $form["name"];
	$save["input_string"] = $form["input_string"];
	$save["output_string"] = $form["output_string"];
	
	//sql_save($save, "data_input");
}

function data_edit() {
	global $args, $colors, $cdef_item_types;
	
	start_box("<strong>Data Input Methods</strong> [edit]", "", "");
	
	if (isset($args[id])) {
		$data_input = db_fetch_row("select * from data_input where id=$args[id]");
	}else{
		unset($data_input);
	}
	
	?>
	<form method="post" action="data_input.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			Enter a meaningful name for this data input method.
		</td>
		<?DrawFormItemTextBox("name",$data_input[name],"","255", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Input String</font><br>
			The data that in sent to the script, which includes the complete path to the script and input sources in &lt;&gt; brackets.
		</td>
		<?DrawFormItemTextBox("input_string",$data_input[input_string],"","255", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Output String</font><br>
			The data that is expected back from the input script; defined as &lt;&gt; brackets.
		</td>
		<?DrawFormItemTextBox("output_string",$data_input[output_string],"","255", "40");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("id",$args[id]);
	end_box();
	
	start_box("Input Fields", "", "");
	print "<tr bgcolor='#$colors[header_panel]'>";
		DrawMatrixHeaderItem("Name",$colors[header_text],1);
		DrawMatrixHeaderItem("Friendly Name",$colors[header_text],2);
	print "</tr>";
    
	$fields = db_fetch_assoc("select id,data_name,name from data_input_fields where data_input_id=$args[id] and input_output='in' order by data_name");
	
	if (sizeof($fields) > 0) {
	foreach ($fields as $field) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="data_input.php?action=field_edit&id=<?print $field[id];?>&data_input_id=<?print $args[id];?>"><?print $field[data_name];?></a>
			</td>
			<td>
				<?print $field[name];?>
			</td>
                        <td width="1%" align="right">
                                <a href="data_input.php?action=field_remove&id=<?print $field[id];?>&data_input_id=<?print $args[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
                        </td>
		</tr>
	<?
	}
	}
	end_box();
	
        start_box("Output Fields", "", "");
        print "<tr bgcolor='#$colors[header_panel]'>";
                DrawMatrixHeaderItem("Name",$colors[header_text],1);
		DrawMatrixHeaderItem("Friendly Name",$colors[header_text],1);
                DrawMatrixHeaderItem("Update RRA",$colors[header_text],2);
        print "</tr>";

        $fields = db_fetch_assoc("select id,name,data_name,update_rra from data_input_fields where data_input_id=$args[id] and input_output='out' order by data_name");
        
	if (sizeof($fields) > 0) {
        foreach ($fields as $field) {
                DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i); $i++;
                        ?>
                        <td>
                                <a class="linkEditMain" href="data_input.php?action=field_edit&id=<?print $field[id];?>&data_i
nput_id=<?print $args[id];?>"><?print $field[data_name];?></a>
                        </td>
                        <td>
                                <?print $field[name];?>
                        </td>
			<td>
                                <?print html_boolean_friendly($field[update_rra]);?>
                        </td>
                        <td width="1%" align="right">
                                <a href="data_input.php?action=field_remove&id=<?print $field[id];?>&data_input_id=<?print $args[id];?>"><img src="images/delete_icon.g
if" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
                        </td>
                </tr>
        <?
        }
        }
        end_box();
	
	DrawFormItemHiddenTextBox("save_component_data_input","1","");
	
	start_box("", "", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "data_input.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();	
}

function data() {
	global $colors;
	
	start_box("<strong>Data Input Methods</strong>", "", "data_input.php?action=edit");
	                         
	print "<tr bgcolor='#$colors[header_panel]'>";
		DrawMatrixHeaderItem("Name",$colors[header_text],1);
		DrawMatrixHeaderItem("&nbsp;",$colors[header_text],1);
	print "</tr>";
    
	$data_inputs = db_fetch_assoc("select * from data_input order by name");
	
	if (sizeof($data_inputs) > 0) {
	foreach ($data_inputs as $data_input) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="data_input.php?action=edit&id=<?print $data_input[id];?>"><?print $data_input[name];?></a>
			</td>
			<td width="1%" align="right">
				<a href="data_input.php?action=remove&id=<?print $data_input[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	}
	}
	end_box();	
}
?>
