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

include ('include/auth.php');
include_once ('include/form.php');
include_once ("include/config_arrays.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'rrd_add':
		template_rrd_add();
		
		break;
	case 'rrd_remove':
		template_rrd_remove();
		
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
	include_once ("include/utility_functions.php");
	
	if (isset($_POST["save_component_template"])) {
		/* save: data_template */
		$save1["id"] = $_POST["data_template_id"];
		$save1["name"] = form_input_validate($_POST["template_name"], "template_name", "", false, 3);
		$save1["graph_template_id"] = 0;
		
		/* save: data_template_data */
		$save2["id"] = $_POST["data_template_data_id"];
		$save2["local_data_template_data_id"] = 0;
		$save2["local_data_id"] = 0;
		
		$save2["data_input_id"] = form_input_validate($_POST["data_input_id"], "data_input_id", "", true, 3);
		$save2["t_name"] = form_input_validate((isset($_POST["t_name"]) ? $_POST["t_name"] : ""), "t_name", "", true, 3);
		$save2["name"] = form_input_validate($_POST["name"], "name", "", false, 3);
		$save2["t_active"] = form_input_validate((isset($_POST["t_active"]) ? $_POST["t_active"] : ""), "t_active", "", true, 3);
		$save2["active"] = form_input_validate((isset($_POST["active"]) ? $_POST["active"] : ""), "active", "", true, 3);
		$save2["t_rrd_step"] = form_input_validate((isset($_POST["t_rrd_step"]) ? $_POST["t_rrd_step"] : ""), "t_rrd_step", "", true, 3);
		$save2["rrd_step"] = form_input_validate($_POST["rrd_step"], "rrd_step", "^[0-9]+$", false, 3);
		$save2["t_rra_id"] = form_input_validate((isset($_POST["t_rra_id"]) ? $_POST["t_rra_id"] : ""), "t_rra_id", "", true, 3);
		
		/* save: data_template_rrd */
		$save3["id"] = $_POST["data_template_rrd_id"];
		$save3["local_data_template_rrd_id"] = 0;
		$save3["local_data_id"] = 0;
		
		$save3["t_rrd_maximum"] = form_input_validate((isset($_POST["t_rrd_maximum"]) ? $_POST["t_rrd_maximum"] : ""), "t_rrd_maximum", "", true, 3);
		$save3["rrd_maximum"] = form_input_validate($_POST["rrd_maximum"], "rrd_maximum", "^[0-9]+$", false, 3);
		$save3["t_rrd_minimum"] = form_input_validate((isset($_POST["t_rrd_minimum"]) ? $_POST["t_rrd_minimum"] : ""), "t_rrd_minimum", "", true, 3);
		$save3["rrd_minimum"] = form_input_validate($_POST["rrd_minimum"], "rrd_minimum", "^[0-9]+$", false, 3);
		$save3["t_rrd_heartbeat"] = form_input_validate((isset($_POST["t_rrd_heartbeat"]) ? $_POST["t_rrd_heartbeat"] : ""), "t_rrd_heartbeat", "", true, 3);
		$save3["rrd_heartbeat"] = form_input_validate($_POST["rrd_heartbeat"], "rrd_heartbeat", "^[0-9]+$", false, 3);
		$save3["t_data_source_type_id"] = form_input_validate((isset($_POST["t_data_source_type_id"]) ? $_POST["t_data_source_type_id"] : ""), "t_data_source_type_id", "", true, 3);
		$save3["data_source_type_id"] = form_input_validate($_POST["data_source_type_id"], "data_source_type_id", "", true, 3);
		$save3["t_data_source_name"] = form_input_validate((isset($_POST["t_data_source_name"]) ? $_POST["t_data_source_name"] : ""), "t_data_source_name", "", true, 3);
		$save3["data_source_name"] = form_input_validate($_POST["data_source_name"], "data_source_name", "^[a-zA-Z0-9_]{1,19}$", false, 3);
		$save3["t_data_input_field_id"] = form_input_validate((isset($_POST["t_data_input_field_id"]) ? $_POST["t_data_input_field_id"] : ""), "t_data_input_field_id", "", true, 3);
		$save3["data_input_field_id"] = form_input_validate((isset($_POST["data_input_field_id"]) ? $_POST["data_input_field_id"] : "0"), "data_input_field_id", "", true, 3);
		
		if (!is_error_message()) {
			$data_template_id = sql_save($save1, "data_template");
			
			if ($data_template_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
		
		if (!is_error_message()) {
			$save2["data_template_id"] = $data_template_id;
			$data_template_data_id = sql_save($save2, "data_template_data");
			
			if ($data_template_data_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
		
		if (!is_error_message()) {
			$save3["data_template_id"] = $data_template_id;
			$data_template_rrd_id = sql_save($save3, "data_template_rrd");
			
			if ($data_template_rrd_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
		
		if (!is_error_message()) {
			/* save entried in 'selected rras' field */
			db_execute("delete from data_template_data_rra where data_template_data_id=$data_template_data_id"); 
			
			for ($i=0; ($i < count($_POST["rra_id"])); $i++) {
				db_execute("insert into data_template_data_rra (rra_id,data_template_data_id) 
					values (" . $_POST["rra_id"][$i] . ",$data_template_data_id)");
			}
			
			if (!empty($_POST["data_template_id"])) {
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
					
					if (isset($_POST[$form_is_templated_value])) {
						if ((!empty($form_value)) || (!empty($_POST[$form_is_templated_value]))) {
							db_execute("insert into data_input_data (data_input_field_id,data_template_data_id,t_value,value)
								values (" . $input_field["id"] . ",$data_template_data_id,'" . $_POST[$form_is_templated_value] . "','$form_value')");
						}
					}
				}
				}
				
				/* push out all "custom data" for this data source template */
				push_out_data_template($data_template_id);
			}
		}
		
		if ((is_error_message()) || (empty($_POST["data_template_id"]))) {
			header ("Location: data_templates.php?action=template_edit&id=" . (empty($data_template_id) ? $_POST["data_template_id"] : $data_template_id)) . "&view_rrd=" . ($_POST["current_rrd"] ? $_POST["current_rrd"] : $data_template_rrd_id);
		}else{
			header ("Location: data_templates.php");
		}
	}
}
   
/* ----------------------------
    template - Graph Templates 
   ---------------------------- */

function template_remove() {
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include ('include/top_header.php');
		form_confirm("Are You Sure?", "Are you sure you want to delete the data template <strong>'" . db_fetch_cell("select name from data_template where id=" . $_GET["id"]) . "'</strong>? This is generally not a good idea if you have data sources attached to this template even though it should not affect any data sources.", getenv("HTTP_REFERER"), "data_templates.php?action=template_remove&id=" . $_GET["id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from data_template_data_rra where data_template_data_id=" . db_fetch_cell("select id from data_template_data where data_template_id=" . $_GET["id"]));
		db_execute("delete from data_template_data where data_template_id=" . $_GET["id"] . " and local_graph_id=0");
		db_execute("delete from data_template_rrd where data_template_id=" . $_GET["id"] . " and local_graph_id=0");
		db_execute("delete from data_template where id=" . $_GET["id"]);
		
		/* "undo" any graph that is currently using this template */
		db_execute("update data_template_data set local_data_template_data_id=0,data_template_id=0 where data_template_id=" . $_GET["id"]);
		db_execute("update data_template_rrd set local_data_template_rrd_id=0,data_template_id=0 where data_template_id=" . $_GET["id"]);
	}	
}

function template_rrd_remove() {
	db_execute("delete from data_template_rrd where id=" . $_GET["id"]);
	
	header ("Location: data_templates.php?action=template_edit&id=" . $_GET["data_template_id"]);
}

function template_rrd_add() {
	db_execute("insert into data_template_rrd (data_template_id,rrd_maximum,rrd_minimum,rrd_heartbeat,data_source_type_id,
		data_source_name) values (" . $_GET["id"] . ",100,0,600,1,'ds')");
	$data_template_rrd_id = db_fetch_cell("select LAST_INSERT_ID()");
	
	header ("Location: data_templates.php?action=template_edit&id=" . $_GET["id"] . "&view_rrd=$data_template_rrd_id");
}

function template_edit() {
	global $colors, $struct_data_source, $struct_data_source_item, $data_source_types;
	
	if (!empty($_GET["id"])) {
		$template_data = db_fetch_row("select * from data_template_data where data_template_id=" . $_GET["id"] . " and local_data_id=0");
		$template = db_fetch_row("select * from data_template where id=" . $_GET["id"]);
		
		$header_label = "[edit: " . $template["name"] . "]";
	}else{
		$header_label = "[new]";
	}
	
	start_box("<strong>Data Template</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	?>
	
	<form method="post" action="data_templates.php">
		
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			The name given to this data template.
		</td>
		<?php form_text_box("template_name",(isset($template) ? $template["name"] : ""),"","150", "40");?>
	</tr>
	
	<?php
	end_box();
	
	start_box("<strong>Data Source</strong>", "98%", $colors["header"], "3", "center", "");
	
	/* make sure 'data source path' doesn't show up for a template... we should NEVER template this field */
	unset($struct_data_source["data_source_path"]);
	
	$i = 0;
	while (list($field_name, $field_array) = each($struct_data_source)) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		
		print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
		form_base_checkbox("t_" . $field_name,(isset($template_data{"t_" . $field_name}) ? $template_data{"t_" . $field_name} : ""),"Use Per-Graph Value (Ignore this Value)","",(isset($template_data) ? $template_data["data_template_id"] : "0"),false);
		print "</td>\n";
		
		if ($field_array["type"] == "custom") {
			$array_rra = array_rekey(db_fetch_assoc("select id,name from rra order by name"), "id", "name");
			form_multi_dropdown("rra_id",$array_rra,db_fetch_assoc("select * from data_template_data_rra where data_template_data_id=" . (isset($template_data) ? $template_data["id"] : "0")), "rra_id");
		}else{
			draw_nontemplated_item($field_array, $field_name, (isset($template_data) ? $template_data[$field_name] : ""));
		}
		
		print "</tr>\n";
	}
	
	end_box();
	
	/* fetch ALL rrd's for this data source */
	if (!empty($_GET["id"])) {
		$template_data_rrds = db_fetch_assoc("select id,data_source_name from data_template_rrd where data_template_id=" . $_GET["id"] . " and local_data_id=0 order by data_source_name");
	}
	
	/* select the first "rrd" of this data source by default */
	if (empty($_GET["view_rrd"])) {
		$_GET["view_rrd"] = (isset($template_data_rrds[0]["id"]) ? $template_data_rrds[0]["id"] : "0");
	}
	
	/* get more information about the rrd we chose */
	if (!empty($_GET["view_rrd"])) {
		$template_rrd = db_fetch_row("select * from data_template_rrd where id=" . $_GET["view_rrd"]);
	}
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	print "	<tr>
			<td bgcolor='#" . $colors["header"] . "' class='textHeaderDark'>
				<strong>Data Source Item</strong> [" . (isset($template_rrd) ? $template_rrd["data_source_name"] : "") . "]
			</td>
			<td class='textHeaderDark' align='right' bgcolor='" . $colors["header"] . "'>
				" . (!empty($_GET["id"]) ? "<strong><a class='linkOverDark' href='data_templates.php?action=rrd_add&id=" . $_GET["id"] . "'>New</a>&nbsp;</strong>" : "") . "
			</td>
		</tr>\n";
	
	$i = 0;
	if (isset($template_data_rrds)) {
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
								<img src="images/tab_left.gif" border="0" align="absmiddle"><a class="linkTabs" href="data_templates.php?action=template_edit&id=<?php print $_GET["id"];?>&view_rrd=<?php print $template_data_rrd["id"];?>"><?php print "$i: " . $template_data_rrd["data_source_name"];?></a>&nbsp;<a href="data_templates.php?action=rrd_remove&id=<?php print $template_data_rrd["id"];?>&data_template_id=<?php print $_GET["id"];?>"><img src="images/delete_icon_dark_back.gif" width="10" height="12" border="0" alt="Delete" align="middle"></a><img src="images/tab_right.gif" border="0" align="absmiddle">
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
	}
	
	/* data input fields list */
	if ((empty($template_data["data_input_id"])) || (db_fetch_cell("select type_id from data_input where id=" . $template_data["data_input_id"]) > "1")) {
		unset($struct_data_source_item["data_input_field_id"]);
	}else{
		$struct_data_source_item["data_input_field_id"]["sql"] = "select id,CONCAT(data_name,' - ',name) as name from data_input_fields where data_input_id=" . $template_data["data_input_id"] . " and input_output='out' and update_rra='on' order by data_name,name";
	}
	
	$i = 1;
	while (list($field_name, $field_array) = each($struct_data_source_item)) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		
		print "<td width='50%'><font class='textEditTitle'>" . $field_array["title"] . "</font><br>\n";
		form_base_checkbox("t_" . $field_name,(isset($template_rrd) ? $template_rrd{"t_" . $field_name} : ""),"Use Per-Graph Value (Ignore this Value)","",(isset($template_data) ? $template_data["data_template_id"] : "0"),false);
		print "</td>\n";
		
		draw_nontemplated_item($field_array, $field_name, (isset($template_rrd) ? $template_rrd[$field_name] : ""));
		
		print "</tr>\n";
	}
	
	end_box();
	
	$i = 0;
	if (!empty($_GET["id"])) {
		/* get each INPUT field for this data input source */
		$fields = db_fetch_assoc("select * from data_input_fields where data_input_id=" . $template_data["data_input_id"] . " and input_output='in' order by name");
		
		start_box("<strong>Custom Data</strong> [data input: " . db_fetch_cell("select name from data_input where id=" . $template_data["data_input_id"]) . "]", "98%", $colors["header"], "3", "center", "");
		
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
					<?php form_base_checkbox("t_value_" . $field["data_name"],$data_input_data["t_value"],"Use Per-Data Source Value (Ignore this Value)","",$_GET["id"],false);?>
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
	
	form_hidden_id("data_template_id",(isset($template_data) ? $template_data["data_template_id"] : "0"));
	form_hidden_id("data_template_data_id",(isset($template_data) ? $template_data["id"] : "0"));
	form_hidden_id("data_template_rrd_id",(isset($template_rrd) ? $template_rrd["id"] : "0"));
	form_hidden_id("current_rrd",(isset($_GET["view_rrd"]) ? $_GET["view_rrd"] : "0"));
	form_hidden_box("save_component_template","1","");
	
	form_save_button("data_templates.php");
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
	
	$i = 0;
	if (sizeof($template_list) > 0) {
	foreach ($template_list as $template) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="data_templates.php?action=template_edit&id=<?php print $template["id"];?>"><?php print $template["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="data_templates.php?action=template_remove&id=<?php print $template["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
		<?php
		$i++;
	}
	}
	end_box();
}

?>
