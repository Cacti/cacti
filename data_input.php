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
$section = "Add/Edit Graphs"; include ('include/auth.php');
include_once ("include/functions.php");
include_once ("include/config_arrays.php");
include_once ('include/form.php');

switch ($_REQUEST["action"]) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'field_remove':
		field_remove();
	    
		header ("Location: data_input.php?action=edit&id=" . $_GET["data_input_id"]);
		break;
	case 'field_edit':
		include_once ("include/top_header.php");
		
		field_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'remove':
		data_remove();
		
		header ("Location: data_input.php");
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
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_data_input"])) {
		data_save();
		return "data_input.php";
	}elseif (isset($_POST["save_component_field"])) {
		field_save();
		return "data_input.php?action=edit&id=" . $_POST["data_input_id"];
	}
}

/* --------------------------
    CDEF Item Functions
   -------------------------- */

function field_remove() {
	global $config, $registered_cacti_names;
	
	if (($config["remove_verification"]["value"] == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the field <strong>'" . db_fetch_cell("select name from data_input_fields where id=" . $_GET["id"]) . "'</strong>?", getenv("HTTP_REFERER"), "data_input.php?action=field_remove&id=" . $_GET["id"] . "&data_input_id=" . $_GET["data_input_id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($_GET["confirm"] == "yes")) {
		/* get information about the field we're going to delete so we can re-order the seqs */
		$field = db_fetch_row("select input_output,data_input_id from data_input_fields where id=" . $_GET["id"]);
		
		db_execute("delete from data_input_fields where id=" . $_GET["id"]);
		db_execute("delete from data_input_data where data_input_field_id=" . $_GET["id"]);
		
		/* when a field is deleted; we need to re-order the field sequences */
		if (preg_match_all("/<([_a-zA-Z0-9]+)>/", db_fetch_cell("select " . $field["input_output"] . "put_string from data_input where id=" . $field["data_input_id"]), $matches)) {
			$j = 0;
			for ($i=0; ($i < count($matches[1])); $i++) {
				if (in_array($matches[1][$i], $registered_cacti_names) == false) {
					$j++; db_execute("update data_input_fields set sequence=$j where data_input_id=" . $field["data_input_id"] . " and input_output='" .  $field["input_output"]. "' and data_name='" . $matches[1][$i] . "'");
				}
			}
		}
	}
}

function field_save() {
	global $registered_cacti_names;
	
 	$save["id"] = $_POST["id"];
	$save["data_input_id"] = $_POST["data_input_id"];
	$save["name"] = $_POST["name"];
	$save["data_name"] = $_POST["data_name"];
	$save["input_output"] = $_POST["input_output"];
	$save["update_rra"] = $_POST["update_rra"];
	$save["sequence"] = $_POST["sequence"];
	$save["type_code"] = $_POST["type_code"];
	$save["regexp_match"] = $_POST["regexp_match"];
	$save["allow_nulls"] = $_POST["allow_nulls"];
	
	$data_input_field_id = sql_save($save, "data_input_fields");
	
	if (!empty($data_input_field_id)) {
		if (preg_match_all("/<([_a-zA-Z0-9]+)>/", db_fetch_cell("select " . $_POST["input_output"] . "put_string from data_input where id=" . $_POST["data_input_id"]), $matches)) {
			$j = 0;
			for ($i=0; ($i < count($matches[1])); $i++) {
				if (in_array($matches[1][$i], $registered_cacti_names) == false) {
					$j++;
					if ($matches[1][$i] == $_POST["data_name"]) {
						db_execute("update data_input_fields set sequence=$j where data_input_id=" . $_POST["data_input_id"] . " and input_output='" .  $_POST["input_output"]. "' and data_name='" . $matches[1][$i] . "'");
					}
				}
			}
		}
	}
}

function field_edit() {
	global $colors, $registered_cacti_names;
	
	if (isset($_GET["id"])) {
		$field = db_fetch_row("select * from data_input_fields where id=" . $_GET["id"]);
	}else{
		unset($field);
	}
	
	if (!empty($_GET["type"])) {
		$current_field_type = $_GET["type"];
	}else{
		$current_field_type = $field["input_output"];
	}
	
	if ($current_field_type == "out") {
		$header_name = "Output";
	}elseif ($current_field_type == "in") {
		$header_name = "Input";
	}
	
	/* obtain a list of available fields for this given field type (input/output) */
	if (preg_match_all("/<([_a-zA-Z0-9]+)>/", db_fetch_cell("select $current_field_type" . "put_string from data_input where id=" . ($_GET["data_input_id"] ? $_GET["data_input_id"] : $field["data_input_id"])), $matches)) {
		for ($i=0; ($i < count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names) == false) {
				$current_field_name = $matches[1][$i];
				$array_field_names[$current_field_name] = $current_field_name;
			}
		}
	}
	
	start_box("<strong>$header_name Fields</strong> [edit]", "", "");
	
	?>
	<form method="post" action="data_input.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Field [<?print $header_name;?>]</font><br>
			Choose the associated field from the <?print $header_name;?> field.
		</td>
		<?DrawFormItemDropdownFromSQL("data_name",$array_field_names,"","",$field["data_name"],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			Enter a meaningful name for this data input method.
		</td>
		<?DrawFormItemTextBox("name",$field["name"],"","200", "40");?>
	</tr>
	
	<?
	if ($current_field_type == "out") {
	DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Update RRD File</font><br>
			Whether data from this output field is to be entered into the rrd file.
		</td>
		<?DrawFormItemCheckBox("update_rra",$field["update_rra"],"Update RRD File","on",$_GET["local_data_id"]);?>
	</tr>
	<?
	}
	
	if ($current_field_type == "in") {
	DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Regular Expression Match</font><br>
			If you want to require a certain regular expression to be matched againt input data, enter it here (ereg format).
		</td>
		<?DrawFormItemTextBox("regexp_match",$field["regexp_match"],"","200", "40");?>
	</tr>
	<?
	}
	
	if ($current_field_type == "in") {
	DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Allow Empty Input</font><br>
			Check here if you want to allow NULL input in this field from the user.
		</td>
		<?DrawFormItemCheckBox("allow_nulls",$field["allow_nulls"],"Allow NULL's","",false);?>
	</tr>
	<?
	}
	
	if ($current_field_type == "in") {
	DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++; ?>
		<td width="50%">
			<font class="textEditTitle">Special Type Code</font><br>
			If this field should be treated specially by host templates, indicate so here. Valid keywords for this field are 'hostname', 'management_ip', 'snmp_community', 'snmp_username', and 'snmp_password'.
		</td>
		<?DrawFormItemTextBox("type_code",$field["type_code"],"","40", "40");?>
	</tr>
	<?
	}
	
	DrawFormItemHiddenIDField("id",$_GET["id"]);
	DrawFormItemHiddenTextBox("input_output",$current_field_type,"");
	DrawFormItemHiddenTextBox("sequence",$field["sequence"],"");
	DrawFormItemHiddenTextBox("data_input_id",$_GET["data_input_id"],$field["data_input_id"]);
	DrawFormItemHiddenTextBox("save_component_field","1","");
	end_box();
	
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
   
/* -----------------------
    Data Input Functions
   ----------------------- */

function data_remove() {
	global $config;
	
	if (($config["remove_verification"]["value"] == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the data input method <strong>'" . db_fetch_cell("select name from data_input where id=" . $_GET["id"]) . "'</strong>?", getenv("HTTP_REFERER"), "data_input.php?action=remove&id=" . $_GET["id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($_GET["confirm"] == "yes")) {
		db_execute("delete from data_input where id=" . $_GET["id"]);
		db_execute("delete from data_input_fields where data_input_id=" . $_GET["id"]);
		db_execute("delete from data_input_data where data_input_id=" . $_GET["id"]);
	}
}

function data_save() {
	global $registered_cacti_names;
	
	$save["id"] = $_POST["id"];
	$save["name"] = $_POST["name"];
	$save["input_string"] = $_POST["input_string"];
	$save["output_string"] = $_POST["output_string"];
	
	sql_save($save, "data_input");
	
	/* get a list of each field so we can note their sequence of occurance in the database */
	if (!empty($_POST["id"])) {
		db_execute("update data_input_fields set sequence=0 where data_input_id=" . $_POST["id"]);
		
		if (preg_match_all("/<([_a-zA-Z0-9]+)>/", $_POST["input_string"], $matches)) {
			$j = 0;
			for ($i=0; ($i < count($matches[1])); $i++) {
				if (in_array($matches[1][$i], $registered_cacti_names) == false) {
					$j++; db_execute("update data_input_fields set sequence=$j where data_input_id=" . $_POST["id"] . " and input_output='in' and data_name='" . $matches[1][$i] . "'");
				}
			}
		}
		
		if (preg_match_all("/<([_a-zA-Z0-9]+)>/", $_POST["output_string"], $matches)) {
			$j = 0;
			for ($i=0; ($i < count($matches[1])); $i++) {
				if (in_array($matches[1][$i], $registered_cacti_names) == false) {
					$j++; db_execute("update data_input_fields set sequence=$j where data_input_id=" . $_POST["id"] . " and input_output='out' and data_name='" . $matches[1][$i] . "'");
				}
			}
		}
	}
	
	
}

function data_edit() {
	global $colors;
	
	start_box("<strong>Data Input Methods</strong> [edit]", "", "");
	
	if (isset($_GET["id"])) {
		$data_input = db_fetch_row("select * from data_input where id=" . $_GET["id"]);
	}else{
		unset($data_input);
	}
	
	?>
	<form method="post" action="data_input.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			Enter a meaningful name for this data input method.
		</td>
		<?DrawFormItemTextBox("name",$data_input["name"],"","255", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Input String</font><br>
			The data that in sent to the script, which includes the complete path to the script and input sources in &lt;&gt; brackets.
		</td>
		<?DrawFormItemTextBox("input_string",$data_input["input_string"],"","255", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Output String</font><br>
			The data that is expected back from the input script; defined as &lt;&gt; brackets.
		</td>
		<?DrawFormItemTextBox("output_string",$data_input["output_string"],"","255", "40");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("id",$_GET["id"]);
	end_box();
	
	if (!empty($_GET["id"])) {
		start_box("Input Fields", "", "data_input.php?action=field_edit&type=in&data_input_id=" . $_GET["id"]);
		print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
			DrawMatrixHeaderItem("Name",$colors["header_text"],1);
			DrawMatrixHeaderItem("Field Order",$colors["header_text"],1);
			DrawMatrixHeaderItem("Friendly Name",$colors["header_text"],2);
		print "</tr>";
	    
		$fields = db_fetch_assoc("select id,data_name,name,sequence from data_input_fields where data_input_id=" . $_GET["id"] . " and input_output='in' order by sequence");
		
		if (sizeof($fields) > 0) {
		foreach ($fields as $field) {
			DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td>
					<a class="linkEditMain" href="data_input.php?action=field_edit&id=<?print $field["id"];?>&data_input_id=<?print $_GET["id"];?>"><?print $field["data_name"];?></a>
				</td>
				<td>
					<?print $field["sequence"]; if ($field["sequence"] == "0") { print " (Not In Use)"; }?>
				</td>
				<td>
					<?print $field["name"];?>
				</td>
				<td width="1%" align="right">
					<a href="data_input.php?action=field_remove&id=<?print $field["id"];?>&data_input_id=<?print $_GET["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
				</td>
			</tr>
		<?
		}
		}else{
			print "<tr><td><em>No Input Fields</em></td></tr>";
		}
		end_box();
		
		start_box("Output Fields", "", "data_input.php?action=field_edit&type=out&data_input_id=" . $_GET["id"]);
		print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
			DrawMatrixHeaderItem("Name",$colors["header_text"],1);
			DrawMatrixHeaderItem("Field Order",$colors["header_text"],1);
			DrawMatrixHeaderItem("Friendly Name",$colors["header_text"],1);
			DrawMatrixHeaderItem("Update RRA",$colors["header_text"],2);
		print "</tr>";
	
		$fields = db_fetch_assoc("select id,name,data_name,update_rra,sequence from data_input_fields where data_input_id=" . $_GET["id"] . " and input_output='out' order by sequence");
		
		if (sizeof($fields) > 0) {
		foreach ($fields as $field) {
			DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td>
					<a class="linkEditMain" href="data_input.php?action=field_edit&id=<?print $field["id"];?>&data_input_id=<?print $_GET["id"];?>"><?print $field["data_name"];?></a>
				</td>
				<td>
					<?print $field["sequence"]; if ($field["sequence"] == "0") { print " (Not In Use)"; }?>
				</td>
				<td>
					<?print $field["name"];?>
				</td>
				<td>
					<?print html_boolean_friendly($field["update_rra"]);?>
				</td>
				<td width="1%" align="right">
					<a href="data_input.php?action=field_remove&id=<?print $field["id"];?>&data_input_id=<?print $_GET["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
				</td>
			</tr>
		<?
		}
		}else{
			print "<tr><td><em>No Output Fields</em></td></tr>";
		}
		end_box();
	}
	
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
	                         
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
	print "</tr>";
    
	$data_inputs = db_fetch_assoc("select * from data_input order by name");
	
	if (sizeof($data_inputs) > 0) {
	foreach ($data_inputs as $data_input) {
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="data_input.php?action=edit&id=<?print $data_input["id"];?>"><?print $data_input["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="data_input.php?action=remove&id=<?print $data_input["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	}
	}
	end_box();	
}
?>
