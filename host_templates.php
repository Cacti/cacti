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
	
	if (isset($form[save_component_template])) {
		template_save();
		return "host_templates.php";
	}
}

   
/* ---------------------
    CDEF Functions
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
	
	if (sql_save($save, "host_template")) {
		raise_message(1);
	}else{
		raise_message(2);
		header("Location: " . $_SERVER["HTTP_REFERER"]);
		exit;
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
	<tr>
		<td colspan="2" width="100%">
			<table width="100%">
				<tr>
					<td align="top" width="50%">
		<?
		$data_templates = db_fetch_assoc("select id, name from data_template");
		if (sizeof($data_templates) > 0) {
			foreach($data_templates as $data_template) {
				$column1 = floor(($rows / 2) + ($rows % 2));

				if ($i == $column1) {
					print "</td><td valign='top' width='50%'>";
				}
				DrawStrippedFormItemCheckBox("data_template".$data_template[id], $old_value, $data_template[name], "",true);
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
	end_box();
	
	DrawFormItemHiddenTextBox("save_component_template","1","");
	
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
