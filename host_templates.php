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
include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'remove':
		template_remove();
		
		header ("Location: host_templates.php");
		break;
	case 'edit':
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
	global $form;
	
	if ((isset($form[save_component_template])) && (isset($form[save_component_data]))) {
		template_save();
		data_save();
		
		return "host_templates.php";
	}
}


/* ----------------------------
    data - Custom Data
   ---------------------------- */

function data_save() {
	global $form;
	
	/* get data_input_id */
	$data_input_id = db_fetch_cell("select
		data_input_id
		from data_template_data
		where id=$form[data_template_id]");
	
	/* ok, first pull out all 'input' values so we know how much to save */
	$input_fields = db_fetch_assoc("select
		id,
		input_output,
		data_name 
		from data_input_fields
		where data_input_id=$data_input_id
		and input_output='in'");
	
	db_execute("delete from host_template_data where data_template_id=$form[data_template_id] and host_template_id=$form[id]");
	
	if (sizeof($input_fields) > 0) {
	foreach ($input_fields as $input_field) {
		/* save the data into the 'host_template_data' table */
		$form_value = "value_" . $input_field[data_name];
		$form_value = $form[$form_value];
		
		$form_is_templated_value = "t_value_" . $input_field[data_name];
		$form_is_templated_value = $form[$form_is_templated_value];
		
		if ((!empty($form_value)) || (!empty($form_is_templated_value))) {
			db_execute("insert into host_template_data (data_input_field_id,data_template_id,host_template_id,t_value,value)
				values ($input_field[id],$form[data_template_id],$form[id],'$form_is_templated_value','$form_value')");
		}
	}
	}
}

/* ---------------------
    Template Functions
   --------------------- */

function template_remove() {
	global $args, $config;
	
	if (($config["remove_verification"]["value"] == "on") && ($args[confirm] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the host template <strong>'" . db_fetch_cell("select name from host_template where id=$args[id]") . "'</strong>?", getenv("HTTP_REFERER"), "host_templates.php?action=remove&id=$args[id]");
		include ('include/bottom_footer.php');
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($args[confirm] == "yes")) {
		db_execute("delete from host_template where id=$args[id]");
	}
}

function template_save() {
	global $form;
	
	$save["id"] = $form["id"];
	$save["name"] = $form["name"];
	
	$host_template_id = sql_save($save, "host_template");
	
	if ($host_template_id) {
		raise_message(1);
	}else{
		raise_message(2);
		header("Location: " . $_SERVER["HTTP_REFERER"]);
		exit;
	}
	
	db_execute ("delete from host_template_data_template where host_template_id=$host_template_id");
	
	while (list($var, $val) = each($form)) {
		if (eregi("^[dt_]", $var)) {
			db_execute ("replace into host_template_data_template (host_template_id,data_template_id) values($host_template_id," . substr($var, 3) . ")");
		}
	}
}

function template_edit() {
	global $args, $colors;
	
	display_output_messages();
	
	start_box("<strong>Host Templates [edit]</strong>", "", "");
	
	if (isset($args[id])) {
		$host_template = db_fetch_row("select * from host_template where id=$args[id]");
	}else{
		unset($host_template);
	}
	
	?>
	<form method="post" action="host_templates.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			A useful name for this host template.
		</td>
		<?DrawFormItemTextBox("name",$host_template[name],"","255", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Selected Data Templates</font><br>
			Select one or more data templates to associate with this host template.
		</td>
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td align="top" width="50%">
						<?
						$data_templates = db_fetch_assoc("select 
							host_template_data_template.host_template_id,
							data_template.id,
							data_template.name
							from data_template left join host_template_data_template
							on (data_template.id=host_template_data_template.data_template_id and host_template_data_template.host_template_id=2) 
							order by data_template.name");
						
						if (sizeof($data_templates) > 0) {
						foreach($data_templates as $data_template) {
							$column1 = floor((sizeof($data_templates) / 2) + (sizeof($data_templates) % 2));
							
							if (empty($data_template[host_template_id])) {
								$old_value = "";
							}else{
								$old_value = "on";
							}
							
							if ($i == $column1) {
								print "</td><td valign='top' width='50%'>";
							}
							DrawStrippedFormItemCheckBox("dt_".$data_template[id], $old_value, $data_template[name], "",true);
							$i++;
						}
						}
						?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	<?
	DrawFormItemHiddenIDField("id",$args[id]);
	DrawFormItemHiddenTextBox("save_component_template","1","");
	end_box();
	
	/* fetch ALL data templates for this data host template */
	if (isset($args[id])) {
		$data_templates = db_fetch_assoc("select
			data_template.id,
			data_template.name
			from data_template, host_template_data_template
			where host_template_data_template.data_template_id=data_template.id
			and host_template_data_template.host_template_id=$args[id]
			order by data_template.name");
	}
	
	/* select the "first" data template of this host template by default */
	if (empty($args[view_data_template])) {
		$args[view_data_template] = $data_templates[0][id];
	}
	
	/* get more information about the data template we chose */
	if (!empty($args[view_data_template])) {
		$data_template = db_fetch_row("select * from data_template where id=$args[view_data_template]");
	}
	
	/* find out what type of input it is using */
	$template_data = db_fetch_row("select id,data_input_id from data_template_data where data_template_id=$args[view_data_template] and local_data_id=0");
	
	$i = 0;
	
	/* if it is not using any input; skip this step: no custom data */
	if (!empty($template_data[data_input_id])) {
		start_box("Custom Data for Host Template [" . $data_template[name] . ": " . db_fetch_cell("select name from data_input where id=$template_data[data_input_id]") . "]", "", "");
		
		/* loop through each data template in use and draw tabs if there is more than one */
		if (sizeof($data_templates) > 1) {
			?>
			<tr height="33">
				<td valign="bottom" colspan="3" background="images/tab_back.gif">
					<table border="0" cellspacing="0" cellpadding="0">
						<tr>
							<?
							$i=0;
							foreach ($data_templates as $data_template) {
							$i++;
							?>
							<td nowrap class="textTab" align="center" background="images/tab_middle.gif">
								<img src="images/tab_left.gif" border="0" align="absmiddle"><a class="linkTabs" href="host_templates.php?action=edit&id=<?print $args[id];?>&view_data_template=<?print $data_template[id];?>"><?print "$i: $data_template[name]";?></a><img src="images/tab_right.gif" border="0" align="absmiddle">
							</td>
							<?
							}
							?>
						</tr>
					</table>
				</td>
			</tr>
			<?
		}elseif (sizeof($data_templates) == 1) {
			$args[view_data_template] = $data_templates[0][id];
		}
		
		/* get each INPUT field for this data input source */
		$fields = db_fetch_assoc("select * from data_input_fields where data_input_id=$template_data[data_input_id] and input_output='in' order by name");
		
		/* loop through each field found */
		if (sizeof($fields) > 0) {
		foreach ($fields as $field) {
			$data_input_data = db_fetch_row("select t_value,value from host_template_data where data_template_id=$args[view_data_template] and host_template_id=$args[id] and data_input_field_id=$field[id]");
			
			if (sizeof($data_input_data) > 0) {
				$old_value = $data_input_data[value];
			}else{
				$old_value = "";
			}
			
			DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i); ?>
				<td width="50%">
					<strong><?print $field[name];?></strong><br>
					<?DrawStrippedFormItemCheckBox("t_value_" . $field[data_name],$data_input_data[t_value],"Use Per-Data Source Value (Ignore this Value)","",false);?>
				</td>
				<?DrawFormItemTextBox("value_" . $field[data_name],$old_value,"","");?>
			</tr>
			<?
			
			$i++;
		}
		}else{
			print "<tr><td><em>No Input Fields for the Selected Data Input Source</em></td></tr>";
		}
		
		end_box();
	}
	
	DrawFormItemHiddenTextBox("save_component_data","1","");
	DrawFormItemHiddenIDField("data_template_id",$args[view_data_template]);
	
	start_box("", "", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "host_templates.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();	
}

function template() {
	global $colors;
	
	display_output_messages();
	
	start_box("<strong>Host Templates</strong>", "", "host_templates.php?action=edit");
	                         
	print "<tr bgcolor='#$colors[header_panel]'>";
		DrawMatrixHeaderItem("Name",$colors[header_text],1);
		DrawMatrixHeaderItem("&nbsp;",$colors[header_text],1);
	print "</tr>";
    
	$host_templates = db_fetch_assoc("select * from host_template order by name");
	
	if (sizeof($host_templates) > 0) {
	foreach ($host_templates as $host_template) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="host_templates.php?action=edit&id=<?print $host_template[id];?>"><?print $host_template[name];?></a>
			</td>
			<td width="1%" align="right">
				<a href="host_templates.php?action=remove&id=<?print $host_template[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	}
	}
	end_box();	
}
?>
