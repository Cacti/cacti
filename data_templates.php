<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

$section = "Add/Edit Graphs"; include ('include/auth.php');

include_once ('include/form.php');
include_once ("include/config_arrays.php");

switch ($_REQUEST["action"]) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'template_remove':
		template_remove();
		
		header ("Location: data_templates.php");
		break;
	case 'template_edit':
		include_once ("include/top_header.php");
		
		template_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		template();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $config;
	
	if (isset($_POST["save_component_template"])) {
		return template_save();
	}
}
   
/* ----------------------------
    template - Graph Templates 
   ---------------------------- */

function template_remove() {
	global $config;
	
	if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		form_confirm("Are You Sure?", "Are you sure you want to delete the data template <strong>'" . db_fetch_cell("select name from data_template where id=" . $_GET["data_template_id"]) . "'</strong>? This is generally not a good idea if you have data sources attached to this template even though it should not affect any data sources.", getenv("HTTP_REFERER"), "data_templates.php?action=template_remove&data_template_id=" . $_GET["data_template_id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || ($_GET["confirm"] == "yes")) {
		db_execute("delete from data_template_data_rra where data_template_data_id=" . db_fetch_cell("select id from data_template_data where data_template_id=" . $_GET["data_template_id"]));
		db_execute("delete from data_template_data where data_template_id=" . $_GET["data_template_id"] . " and local_graph_id=0");
		db_execute("delete from data_template_rrd where data_template_id=" . $_GET["data_template_id"] . " and local_graph_id=0");
		db_execute("delete from data_template where id=" . $_GET["data_template_id"]);
		
		/* "undo" any graph that is currently using this template */
		db_execute("update data_template_data set local_data_template_data_id=0,data_template_id=0 where data_template_id=" . $_GET["data_template_id"]);
		db_execute("update data_template_rrd set local_data_template_rrd_id=0,data_template_id=0 where data_template_id=" . $_GET["data_template_id"]);
	}	
}

function template_save() {
	include_once ("include/utility_functions.php");
	
	if ($_POST["rrd_maximum"] == "") { $_POST["rrd_maximum"] = 0; }
	if ($_POST["rrd_minimum"] == "") { $_POST["rrd_minimum"] = 0; }
	
	/* save: data_template */
	$save["id"] = $_POST["data_template_id"];
	$save["name"] = $_POST["template_name"];
	$save["graph_template_id"] = 0;
	
	$data_template_id = sql_save($save, "data_template");
	unset ($save);
	
	/* save: data_template_data */
	$save["id"] = $_POST["data_template_data_id"];
	$save["local_data_template_data_id"] = 0;
	$save["local_data_id"] = 0;
	$save["data_template_id"] = $data_template_id;
	$save["data_input_id"] = $_POST["data_input_id"];
	$save["t_name"] = $_POST["t_name"];
	$save["name"] = $_POST["name"];
	$save["t_active"] = $_POST["t_active"];
	$save["active"] = $_POST["active"];
	$save["t_rrd_step"] = $_POST["t_rrd_step"];
	$save["rrd_step"] = $_POST["rrd_step"];
	$save["t_rra_id"] = $_POST["t_rra_id"];
	
	$data_template_data_id = sql_save($save, "data_template_data");
	unset ($save);
	
	/* save: data_template_rrd */
	$save["id"] = $_POST["data_template_rrd_id"];
	$save["local_data_template_rrd_id"] = 0;
	$save["local_data_id"] = 0;
	$save["data_template_id"] = $data_template_id;
	$save["t_rrd_maximum"] = $_POST["t_rrd_maximum"];
	$save["rrd_maximum"] = $_POST["rrd_maximum"];
	$save["t_rrd_minimum"] = $_POST["t_rrd_minimum"];
	$save["rrd_minimum"] = $_POST["rrd_minimum"];
	$save["t_rrd_heartbeat"] = $_POST["t_rrd_heartbeat"];
	$save["rrd_heartbeat"] = $_POST["rrd_heartbeat"];
	$save["t_data_source_type_id"] = $_POST["t_data_source_type_id"];
	$save["data_source_type_id"] = $_POST["data_source_type_id"];
	$save["t_data_source_name"] = $_POST["t_data_source_name"];
	$save["data_source_name"] = $_POST["data_source_name"];
	$save["data_input_field_id"] = $_POST["data_input_field_id"];
	
	$data_template_rrd_id = sql_save($save, "data_template_rrd");
	
	/* save entried in 'selected rras' field */
	db_execute("delete from data_template_data_rra where data_template_data_id=$data_template_data_id"); 
	
	for ($i=0; ($i < count($_POST["rra_id"])); $i++) {
		db_execute("insert into data_template_data_rra (rra_id,data_template_data_id) 
			values (" . $_POST["rra_id"][$i] . ",$data_template_data_id)");
	}
	
	/* push out all data source settings to child data source using this template */
	push_out_data_source($data_template_data_id);
	push_out_data_source_item($data_template_rrd_id);
	
	/* ok, first pull out all 'input' values so we know how much to save */
	$input_fields = db_fetch_assoc("select
		id,
		input_output,
		data_name 
		from data_input_fields
		where data_input_id=" . $_POST["data_input_id"] . "
		and input_output='in'");
	
	db_execute("delete from data_input_data where data_template_data_id=$data_template_data_id");
	
	if (sizeof($input_fields) > 0) {
	foreach ($input_fields as $input_field) {
		/* save the data into the 'host_template_data' table */
		$form_value = "value_" . $input_field["data_name"];
		$form_value = $_POST[$form_value];
		
		$form_is_templated_value = "t_value_" . $input_field["data_name"];
		$form_is_templated_value = $_POST[$form_is_templated_value];
		
		if ((!empty($form_value)) || (!empty($form_is_templated_value))) {
			db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,t_value,value)
				values (" . $input_field["id"] . ",$data_template_data_id,'$form_is_templated_value','$form_value')");
		}
	}
	}
	
	/* push out all "custom data" for this data source template */
	push_out_data_template($data_template_id);
	
	if (empty($_POST["data_template_id"])) {
		return "data_templates.php?action=template_edit&data_template_id=" . $data_template_id . "&view_rrd=" . ($_POST["current_rrd"] ? $_POST["current_rrd"] : $data_template_rrd_id);
	}else{
		return "data_templates.php";
	}
}

function template_edit() {
	global $colors, $struct_data_source, $struct_data_source_item, $data_source_types;
	
	if (isset($_GET["data_template_id"])) {
		$template_data = db_fetch_row("select * from data_template_data where data_template_id=" . $_GET["data_template_id"] . " and local_data_id=0");
		$template = db_fetch_row("select * from data_template where id=" . $_GET["data_template_id"]);
	}else{
		unset($template_data);
		unset($template);
	}
	
	start_box("Template Configuration", "98%", $colors["header"], "3", "center", "");
	?>
	
	<form method="post" action="data_templates.php">
		
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			The name given to this data template.
		</td>
		<?php form_text_box("template_name",$template["name"],"","150", "40");?>
	</tr>
	
	<?php
	end_box();
	
	start_box("Data Template Configuration", "98%", $colors["header"], "3", "center", "");
	
	/* make sure 'data source path' doesn't show up for a template... we should NEVER template this field */
	unset($struct_data_source["data_source_path"]);
	
	while (list($field_name, $field_array) = each($struct_data_source)) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		
		print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
		form_base_checkbox("t_" . $field_name,$template_data{"t_" . $field_name},"Use Per-Graph Value (Ignore this Value)","",false);
		print "</td>\n";
		
		if ($field_array["type"] == "custom") {
			$array_rra = array_rekey(db_fetch_assoc("select id,name from rra order by name"), "id", "name");
			form_multi_dropdown("rra_id",$array_rra,db_fetch_assoc("select * from data_template_data_rra where data_template_data_id=" . $template_data["id"]), "rra_id");
		}else{
			draw_nontemplated_item($field_array, $field_name, $template_data[$field_name]);
		}
		
		print "</tr>\n";
	}
	
	end_box();
	
	/* fetch ALL rrd's for this data source */
	if (isset($_GET["data_template_id"])) {
		$template_data_rrds = db_fetch_assoc("select id,data_source_name from data_template_rrd where data_template_id=" . $_GET["data_template_id"] . " and local_data_id=0 order by data_source_name");
	}
	
	/* select the first "rrd" of this data source by default */
	if (empty($_GET["view_rrd"])) {
		$_GET["view_rrd"] = $template_data_rrds[0]["id"];
	}
	
	/* get more information about the rrd we chose */
	if (!empty($_GET["view_rrd"])) {
		$template_rrd = db_fetch_row("select * from data_template_rrd where id=" . $_GET["view_rrd"]);
	}
	
	start_box("Data Source Configuration [" . $template_rrd["data_source_name"] . "]", "98%", $colors["header"], "3", "center", "");
	
	$i = 0;
	if (sizeof($template_data_rrds) > 1) {
		?>
		<tr height="33">
			<td valign="bottom" colspan="3" background="images/tab_back_light.gif">
				<table border="0" cellspacing="0" cellpadding="0">
					<tr>
						<?php
						foreach ($template_data_rrds as $template_data_rrd) {
						$i++;
						?>
						<td nowrap class="textTab" align="center" background="images/tab_middle.gif">
							<img src="images/tab_left.gif" border="0" align="absmiddle"><a class="linkTabs" href="data_templates.php?action=template_edit&data_template_id=<?php print $_GET["data_template_id"];?>&view_rrd=<?php print $template_data_rrd["id"];?>"><?php print "$i: " . $template_data_rrd["data_source_name"];?></a><img src="images/tab_right.gif" border="0" align="absmiddle">
						</td>
						<?php
						}
						?>
					</tr>
				</table>
			</td>
		</tr>
		<?php
	}elseif (sizeof($template_data_rrds) == 1) {
		$_GET["view_rrd"] = $template_data_rrds[0]["id"];
	}
	
	while (list($field_name, $field_array) = each($struct_data_source_item)) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		
		print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
		form_base_checkbox("t_" . $field_name,$template_rrd{"t_" . $field_name},"Use Per-Graph Value (Ignore this Value)","",false);
		print "</td>\n";
		
		draw_nontemplated_item($field_array, $field_name, $template_rrd[$field_name]);
		
		print "</tr>\n";
	}
	
	end_box();
	
	$i = 0;
	if (!empty($_GET["data_template_id"])) {
	/* get each INPUT field for this data input source */
	$fields = db_fetch_assoc("select * from data_input_fields where data_input_id=" . $template_data["data_input_id"] . " and input_output='in' order by name");
		start_box("Custom Data", "98%", $colors["header"], "3", "center", "");
		
		/* loop through each field found */
		if (sizeof($fields) > 0) {
		foreach ($fields as $field) {
			$data_input_data = db_fetch_row("select t_value,value from data_input_data where data_template_data_id=" . $template_data["id"] . " and data_input_field_id=" . $field["id"]);
			
			if (sizeof($data_input_data) > 0) {
				$old_value = $data_input_data["value"];
			}else{
				$old_value = "";
			}
			
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); ?>
				<td width="50%">
					<strong><?php print $field["name"];?></strong><br>
					<?php form_base_checkbox("t_value_" . $field["data_name"],$data_input_data["t_value"],"Use Per-Data Source Value (Ignore this Value)","",false);?>
				</td>
				<?php form_text_box("value_" . $field["data_name"],$old_value,"","");?>
			</tr>
			<?php
			
			$i++;
		}
		}else{
			print "<tr><td><em>No Input Fields for the Selected Data Input Source</em></td></tr>";
		}
		
		end_box();
	}
	
	form_hidden_id("data_template_id",$_GET["data_template_id"]);
	form_hidden_id("host_id",$_GET["host_id"]);
	form_hidden_id("data_template_data_id",$template_data["id"]);
	form_hidden_id("data_template_rrd_id",$template_rrd["id"]);
	form_hidden_id("current_rrd",$_GET["view_rrd"]);
	form_hidden_box("save_component_template","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?php form_save_button("save", "data_templates.php");?>
		</td>
	</tr>
	</form>
	<?php
	end_box();
}

function template() {
	global $colors;
	
	start_box("<strong>Data Template Management</strong>", "98%", $colors["header"], "3", "center", "data_templates.php?action=template_edit");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Template Title",$colors["header_text"],2);
	print "</tr>";
	
	$template_list = db_fetch_assoc("select 
		data_template.id,
		data_template.name
		from data_template
		order by data_template.name");
       
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="data_templates.php?action=template_edit&data_template_id=<?php print $template["id"];?>"><?php print $template["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="data_templates.php?action=template_remove&data_template_id=<?php print $template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
		<?php
		$i++;
	}
	}
	end_box();
}

?>
