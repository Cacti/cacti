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
header("Cache-control: no-cache");
$section = "User Administration"; 
include ('include/auth.php');
include_once ("include/form.php");

if ($form[action]) { $action = $form[action]; } else { $action = $args[action]; }
if ($form[ID]) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'user_remove':
		user_remove();
    
    		header ("Location: user_auth.php");
		break;
	case 'graph_config_edit':
		include_once ("include/top_header.php");
		
		graph_config_edit();
	
		include_once ("include/bottom_footer.php");
		break;
	case 'graph_perms_edit':
		include_once ("include/top_header.php");
	
		graph_perms_edit();
	
		include_once ("include/bottom_footer.php");
		break;
	case 'user_edit':
		include_once ("include/top_header.php");
		
		user_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		user();
	
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function draw_user_form_select() { 
	global $colors, $args; ?>
	<tr bgcolor="#<?print $colors[panel];?>">
		</form>
		<form name="form_user">
		<td>
			<select name="cbo_user" onChange="window.location=document.form_user.cbo_user.options[document.form_user.cbo_user.selectedIndex].value">
				<option value="user_admin.php?action=user_edit&id=<?print $args[id];?>"<?if ($args[action] == "user_edit") {?> selected<?}?>>User Configuration</option>
				<option value="user_admin.php?action=graph_perms_edit&id=<?print $args[id];?>"<?if ($args[action] == "graph_perms_edit") {?> selected<?}?>>Individual Graph Permissions</option>
				<option value="user_admin.php?action=graph_config_edit&id=<?print $args[id];?>"<?if ($args[action] == "graph_config_edit") {?> selected<?}?>>User Graph Settings</option>
			</select>
		</td>
		</form>
	</tr>
<?}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $form, $config;
	
	if ((isset($form[save_component_user])) && (isset($form[save_component_graph_perms])) && (isset($form[save_component_graph_config]))) {
		user_save();
		graph_perms_save();
		/* graph_config_save(); */
		return "user_admin.php?action=user_edit&id=$form[user_id]";
	}elseif (isset($form[save_component_user])) {
		user_save();
		return "user_admin.php";
	}elseif (isset($form[save_component_graph_perms])) {
		graph_perms_save();
		return "user_admin.php";
	}elseif (isset($form[save_component_graph_config])) {
		/* graph_config_save(); */
		return "user_admin.php";
	}
}

/* --------------------------
    Graph Permissions
   -------------------------- */

function graph_perms_save() {
	global $form;
	
	db_execute ("delete from auth_graph where userid=$form[user_id]");
    	db_execute ("delete from auth_graph_hierarchy where userid=$form[user_id]");
	
	reset($form);
	
	while (list($var, $val) = each($form)) {
		if (eregi("^[graph|tree]", $var)) {
			if (substr($var, 0, 5) == "graph") {
			    db_execute ("replace into auth_graph (userid,graphid) values($form[user_id]," . substr($var, 5) . ")");
			}elseif (substr($var, 0, 4) == "tree") {
			    db_execute ("replace into auth_graph_hierarchy (userid,hierarchyid) values($form[user_id]," . substr($var, 4) . ")");
			}
		}
	}
}

function graph_perms_edit() {
	global $colors, $args, $config;
	
	if (isset($args[id])) {
		$graph_policy = db_fetch_cell("select GraphPolicy from auth_users where id=$args[id]");
		
		if ($graph_policy == "1") {
			$graph_policy_text = "DENIED";
		}elseif ($graph_policy == "2") {
			$graph_policy_text = "ALLOWED";
		}
	}else{
		unset($user);
	}
	
	if ($config[full_view_user_admin][value] == "") {
		start_box("<strong>User Management [edit]</strong>", "", "");
		draw_user_form_select();
		end_box();
	}
	
	if ($graph_policy == "1") {
		$graph_policy_text = "Select the graphs you want to <strong>DENY</strong> this user from.";
	} elseif ($graph_policy == "2") {
		$graph_policy_text = "Select the graphs you want <strong>ALLOW</strong> this user to view.";
	}
	
	start_box("$graph_policy_text", "", "");
	
	$graphs = db_fetch_assoc("select 
		ag.UserID,
		g.ID, g.Title 
		from rrd_graph g
		left join auth_graph ag on (g.id=ag.graphid and ag.userid=$args[id]) 
		order by g.title");
	$rows = sizeof($graphs);
	
	?>
	<form method="post" action="user_admin.php">
	
	<tr>
		<td colspan="2" width="100%">
			<table width="100%">
				<tr>
					<td align="top" width="50%">
		<?
		
		if (sizeof($graphs) > 0) {
			foreach ($graphs as $graph) {
			    if ($graph[UserID] == "") {
				$old_value = "";
			    }else{
				$old_value = "on";
			    }
			    
			    $column1 = floor(($rows / 2) + ($rows % 2));
			    
			    if ($i == $column1) {
				print "</td><td valign='top' width='50%'>";
			    }
					
			    DrawStrippedFormItemCheckBox("graph".$graph[ID], $old_value, $graph[Title],"",true);
			    
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
	end_box();
	
	DrawFormItemHiddenIDField("user_id",$args[id]);
	DrawFormItemHiddenTextBox("save_component_graph_perms","1","");
	
	if ($config[full_view_user_admin][value] == "") {
		start_box("", "", "");
		?>
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right">
				<?DrawFormSaveButton("save", "user_admin.php");?>
			</td>
		</tr>
		</form>
		<?
		end_box();
	}
}

/* --------------------------
    Per-User Graph Config
   -------------------------- */

function graph_config_edit() {
	include_once ("include/functions.php");
	global $colors, $args, $config, $HTTP_SESSION_VARS;
	
	if (isset($args[id])) {
		$graph_settings = LoadSettingsIntoArray($HTTP_SESSION_VARS["user_id"], $config["guest_user"]["value"]);
	}else{
		unset($user);
	}
	
	if ($config[full_view_user_admin][value] == "") {
		start_box("<strong>User Management [edit]</strong>", "", "");
		draw_user_form_select();
		end_box();
	}
	
	start_box("Graph Preview Settings", "", "");
	
	?>
	<form method="post" action="user_admin.php">
	<?
	
	DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Height</font><br>
			The height of graphs created in preview mode.
		</td>
		<?DrawFormItemTextBox("Height",$graph_settings[height],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Width</font><br>
			The width of graphs created in preview mode.
		</td>
		<?DrawFormItemTextBox("Width",$graph_settings[width],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Timespan</font><br>
			The amount of time to represent on a graph created in preview mode (0 uses auto).
		</td>
		<?DrawFormItemTextBox("TimeSpan",$graph_settings[time_span],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Default RRA</font><br>
			The default RRA to use when displaying graphs in preview mode.
		</td>
		<?DrawFormItemDropdownFromSQL("RRAID",db_fetch_assoc("select * from rrd_rra order by name"),"Name","ID",$graph_settings[rra_id],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Columns</font><br>
			The number of columns to display graphs in using preview mode.
		</td>
		<?DrawFormItemTextBox("ColumnNumber",$graph_settings[column_number],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Page Refresh</font><br>
			The number of seconds between automatic page refreshes.
		</td>
		<?DrawFormItemTextBox("PageRefresh",$graph_settings[page_refresh],"","50", "40");?>
	</tr>
	
	<tr>
		<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Hierarchical Settings</td>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Default Graph Hierarchy</font><br>
			The default graph hierarchy to use when displaying graphs in tree mode.
		</td>
		<?DrawFormItemDropdownFromSQL("TreeID",db_fetch_assoc("select * from viewing_trees order by Title"),"Title","ID",$graph_settings[tree_id],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">View Settings</font><br>
			Options that govern how the graphs are displayed.
		</td>
		<td>
		<?
			DrawStrippedFormItemRadioButton("ViewType", $graph_settings[view_type], "1", "Show a preview of the graph.", "1",true);
			DrawStrippedFormItemRadioButton("ViewType", $graph_settings[view_type], "2", "Show a text-based listing of the graph.", "1",true);
		?>
		</td>
	</tr>
	
	<?
	end_box();
	
	DrawFormItemHiddenIDField("user_id",$args[id]);
	DrawFormItemHiddenTextBox("save_component_graph_config","1","");
	
	start_box("", "", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "user_admin.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();
}

/* --------------------------
    User Administration
   -------------------------- */

function user_save() {
	global $form;
	
	/* only change password when user types one */
	if ($form[Password] != $form[Confirm]) {
		$passwords_do_not_match = true;
	}elseif (($form[Password] == "") && ($form[Confirm] == "")) {
		$password = $form[_password];
	}else{
		$password = "PASSWORD('$form[Password]')";
	}
	
	if ($passwords_do_not_match != true) {
		$save["ID"] = $form["user_id"];
		$save["Username"] = $form["Username"];
		$save["FullName"] = $form["FullName"];
		$save["Password"] = $password;
		$save["MustChangePassword"] = $form["MustChangePassword"];
		$save["ShowTree"] = $form["ShowTree"];
		$save["ShowList"] = $form["ShowList"];
		$save["ShowPreview"] = $form["ShowPreview"];
		$save["GraphSettings"] = $form["GraphSettings"];
		$save["LoginOpts"] = $form["LoginOpts"];
		$save["GraphPolicy"] = $form["GraphPolicy"];
		
		$id = sql_save($save, "auth_users");
		
		reset($form);
		
		db_execute("delete from auth_acl where userid=$id");
		
		while (list($var, $val) = each($form)) {
			if (eregi("^[section]", $var)) {
				if (substr($var, 0, 7) == "section") {
				    db_execute ("replace into auth_acl (userid,sectionid) values($id," . substr($var, 7) . ")");
				}
			}
		}
	}	
}

function user_remove() {
	global $config, $args;
	
	if (($config["remove_verification"]["value"] == "on") && ($args[confirm] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the user <strong>'" . db_fetch_cell("select Username from auth_users where id=$args[id]") . "'</strong>?", getenv("HTTP_REFERER"), "user_admin.php?action=user_remove&id=$args[id]");
		include ('include/bottom_footer.php');
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($args[confirm] == "yes")) {
	    db_execute("delete from auth_users where id=$args[id]");
	    db_execute("delete from auth_acl where userid=$args[id]");
	    db_execute("delete from auth_hosts where userid=$args[id]");
	    db_execute("delete from auth_graph where userid=$args[id]");
	    db_execute("delete from auth_graph_hierarchy where userid=$args[id]");
	    db_execute("delete from settings_graphs where userid=$args[id]");
	    db_execute("delete from settings_viewing_tree where userid=$args[id]");
	    db_execute("delete from settings_graph_tree where userid=$args[id]");
	    db_execute("delete from settings_ds_tree where userid=$args[id]");
	}	
}

function user_edit() {
	global $args, $colors, $config;
	
	if (isset($args[id])) {
		$user = db_fetch_row("select * from auth_users where id=$args[id]");
	}else{
		unset($user);
	}
	
	if ($config[full_view_user_admin][value] == "") {
		start_box("<strong>User Management [edit]</strong>", "", "");
		draw_user_form_select();
		end_box();
	}
	
	start_box("User Configuration", "", "");
	
	?>
	<form method="post" action="user_admin.php">
	<?
	
	DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">User Name</font><br>
			
		</td>
		<?DrawFormItemTextBox('Username',$user[Username],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Full Name</font><br>
			
		</td>
		<?DrawFormItemTextBox('FullName',$user[FullName],"","");?>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Password</font><br>
			
		</td>
		<td>
			<?DrawStrippedFormItemPasswordTextBox("Password","","","","40");?><br>
			<?DrawStrippedFormItemPasswordTextBox("Confirm","","","","40");?>
		</td>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Account Options</font><br>
			
		</td>
		<td>
		<?
			DrawStrippedFormItemCheckBox("MustChangePassword",$user[MustChangePassword],"User Must Change Password at Next Login","",true);
			DrawStrippedFormItemCheckBox("GraphSettings",$user[GraphSettings],"Allow this User to Keep Custom Graph Settings","on",true);
		?>
		</td>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Graph Options</font><br>
			
		</td>
		<td>
		<?
			DrawStrippedFormItemCheckBox("ShowTree",$user[ShowTree],"User Has Rights to View Tree Mode","on",true);
			DrawStrippedFormItemCheckBox("ShowList",$user[ShowList],"User Has Rights to View List Mode","on",true);
			DrawStrippedFormItemCheckBox("ShowPreview",$user[ShowPreview],"User Has Rights to View Preview Mode","on",true);
		?>
		</td>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Default Policy</font><br>
			The default allow/deny graph policy for this user.
		</td>
		<?
		DrawFormItemDropDownCustomHeader("GraphPolicy");
		DrawFormItemDropDownCustomItem("GraphPolicy","1","Allow",$user[GraphPolicy]);
		DrawFormItemDropDownCustomItem("GraphPolicy","2","Deny",$user[GraphPolicy]);
		DrawFormItemDropDownCustomFooter();
		?>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Login Options</font><br>
			What to do when this user logs in.
		</td>
		<td>
		<?
			DrawStrippedFormItemRadioButton("LoginOpts", $user[LoginOpts], "1", "Show the page that user pointed their browser to.","1",true);
			DrawStrippedFormItemRadioButton("LoginOpts", $user[LoginOpts], "2", "Show the default console screen.","1",true);
			DrawStrippedFormItemRadioButton("LoginOpts", $user[LoginOpts], "3", "Show the default graph screen.","1",true);
		?>
		</td>
	</tr>
	
	<?
	end_box();
	start_box("User Permissions", "", "");
	
	$sections = db_fetch_assoc("select 
		auth_acl.UserID,
		auth_sections.ID, auth_sections.Section
		from auth_sections
		left join auth_acl on (auth_sections.id=auth_acl.sectionid and auth_acl.userid=$args[id]) 
		order by auth_sections.Section");
	$rows = sizeof($sections);
	
	?>
	<tr>
		<td colspan="2" width="100%">
			<table width="100%">
				<tr>
					<td align="top" width="50%">
		<?
		
		if (sizeof($sections) > 0) {
			foreach ($sections as $section) {
			    if ($section[UserID] == "") {
				$old_value = "";
			    }else{
				$old_value = "on";
			    }
			    
			    $column1 = floor(($rows / 2) + ($rows % 2));
			    
			    if ($i == $column1) {
				print "</td><td valign='top' width='50%'>";
			    }
					
			    DrawStrippedFormItemCheckBox("section".$section[ID], $old_value, $section[Section],"",true);
			    
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
	end_box();
	
	DrawFormItemHiddenIDField("user_id",$args[id]);
	DrawFormItemHiddenTextBox("_password",$user[Password],"");
	DrawFormItemHiddenTextBox("save_component_user","1","");
	
	if ($config[full_view_user_admin][value] == "") {
		start_box("", "", "");
		?>
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right">
				<?DrawFormSaveButton("save", "user_admin.php");?>
			</td>
		</tr>
		</form>
		<?
		end_box();
	}
	
	if ($config[full_view_user_admin][value] == "on") {
		graph_perms_edit();
		graph_config_edit();
	}	
}

function user() {
	global $colors;
	
	start_box("<strong>User Management</strong>", "", "user_admin.php?action=user_edit");
	
	print "<tr bgcolor='#$colors[header_panel]'>";
		DrawMatrixHeaderItem("User Name",$colors[header_text],1);
		DrawMatrixHeaderItem("Full Name",$colors[header_text],1);
		DrawMatrixHeaderItem("Default Graph Policy",$colors[header_text],2);
	print "</tr>";
	
	$user_list = db_fetch_assoc("select ID,Username,FullName,GraphPolicy from auth_users order by Username");
	
	if (sizeof($user_list) > 0) {
	foreach ($user_list as $user) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
			?>
			<td>
				<a class="linkEditMain" href="user_admin.php?action=user_edit&id=<?print $user[ID];?>"><?print $user[Username];?></a>
			</td>
			<td>
				<?print $user[FullName];?>
			</td>
			<td>
				<?if ($user[GraphPolicy] == "1") { print "ALLOW"; }else{ print "DENY"; }?>
			</td>
			<td width="1%" align="right">
				<a href="user_admin.php?action=user_remove&id=<?print $user[ID];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	$i++;
	}
	}
	end_box();	
}
?>
