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
	case 'tree':
		include_once ("include/top_header.php");
		
		tree();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'tree_moveup': 
		tree_moveup();
		
		header ("Location: data_sources.php?action=tree");
		break;
	case 'tree_movedown':
		tree_movedown();
		
		header ("Location: data_sources.php?action=tree");
		break;
	case 'remove':
		ds_remove();
		
		header ("Location: data_sources.php");
		break;
	case 'edit':
		include_once ("include/top_header.php");
		
		ds_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		ds();
		
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


/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_tabs() {
?>
		<tr height="33">
			<td valign="bottom" colspan="30" background="images/tab_back.gif">
				<table border="0" cellspacing="0" cellpadding="0">
					<tr>
						<td nowrap class="textTab" align="center" background="images/tab_middle.gif">
							<img src="images/tab_left.gif" border="0" align="absmiddle"><a class="linkTabs" href="data_sources.php">Data Sources</a><img src="images/tab_right.gif" border="0" align="absmiddle">
						</td>
						<td nowrap class="textTab" align="center" background="images/tab_middle.gif">
							<img src="images/tab_left.gif" border="0" align="absmiddle"><a class="linkTabs" href="data_sources.php?action=tree">Data Source Tree</a><img src="images/tab_right.gif" border="0" align="absmiddle">
						</td>
					</tr>
				</table>
			</td>
		</tr>
<?	
}

/* ----------------------
    Tree Edit Functions
   ---------------------- */

function tree() {
	include_once ('include/tree_view_functions.php');
	
	start_box("<strong>Data Source Tree</strong>", "", "data_sources.php?action=edit");
	
	$tree_parameters[edit_mode] = true;
	
	draw_tabs();
    	grow_polling_tree($start_branch, 1, $tree_parameters);
	
	end_box();
}

function tree_moveup() {
	include_once("include/tree_functions.php");
	global $args;
	
	$order_key = db_fetch_cell("SELECT order_key FROM data_tree WHERE id=$args[branch_id]");
	if ($order_key > 0) { branch_up($order_key, 'data_tree', 'order_key', ''); }
}

function tree_movedown() {
	include_once("include/tree_functions.php");
	global $args;
	
	$order_key = db_fetch_cell("SELECT order_key FROM data_tree WHERE id=$args[branch_id]");
	if ($order_key > 0) { branch_down($order_key, 'data_tree', 'order_key', ''); }
}

/* ---------------------
    Template Functions
   --------------------- */


function ds_remove() {
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

function ds_save() {
	global $form;
	
	$save["id"] = $form["id"];
	$save["name"] = $form["name"];
	
	sql_save($save, "host_template");
}

function ds_edit() {
	global $args, $colors, $cdef_item_types;
	
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

function ds() {
	include_once ('include/tree_view_functions.php');
	
	start_box("<strong>Data Sources</strong>", "", "data_sources.php?action=edit");
	
	draw_tabs();
    	grow_polling_tree($start_branch, 1, $tree_parameters);
	
	end_box();
}
?>
