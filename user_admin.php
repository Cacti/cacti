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

$current_script_name = basename($HTTP_SERVER_VARS["SCRIPT_NAME"]);

function draw_user_form_select() { 
	global $current_script_name, $colors, $args; ?>
	<tr bgcolor="#<?print $colors[panel];?>">
		</form>
		<form name="form_user">
		<td>
			<select name="cbo_user" onChange="window.location=document.form_user.cbo_user.options[document.form_user.cbo_user.selectedIndex].value">
				<option value="<?print $current_script_name;?>?action=edit&id=<?print $args[id];?>"<?if ($args[action] == "edit") {?> selected<?}?>>User Configuration</option>
				<option value="<?print $current_script_name;?>?action=edit_perms&id=<?print $args[id];?>"<?if ($args[action] == "edit_perms") {?> selected<?}?>>Individual Graph Permissions</option>
				<option value="<?print $current_script_name;?>?action=edit_graph_config&id=<?print $args[id];?>"<?if ($args[action] == "edit_graph_config") {?> selected<?}?>>User Graph Settings</option>
			</select>
		</td>
		</form>
	</tr>
<?}

switch ($action) {
 case 'save_perms':
    	db_execute ("delete from auth_graph where userid=$form[id]");
    	db_execute ("delete from auth_graph_hierarchy where userid=$form[id]");
	
	while (list($var, $val) = each($form)) {
		if (eregi("^[graph|tree|section]", $var)) {
			if (substr($var, 0, 5) == "graph") {
			    db_execute ("replace into auth_graph (userid,graphid) values($form[id]," . substr($var, 5) . ")");
			}elseif (substr($var, 0, 4) == "tree") {
			    db_execute ("replace into auth_graph_hierarchy (userid,hierarchyid) values($form[id]," . substr($var, 4) . ")");
			}
		}
	}
	
	header ("Location: $current_script_name");
 	break;
 case 'save':
    	/* only change password when user types on */
	if ($form[Password] != $form[Confirm]) {
		$passwords_do_not_match = true;
	}elseif (($form[Password] == "") && ($form[Confirm] == "")) {
		$password = $form[_password];
	}else{
		$password = "PASSWORD('$form[Password]')";
	}
	
	if ($passwords_do_not_match != true) {
		$save["ID"] = $form["ID"];
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
		
		db_execute("delete from auth_acl where userid=$id");
		while (list($var, $val) = each($form)) {
			if (eregi("^[section]", $var)) {
				if (substr($var, 0, 7) == "section") {
				    db_execute ("replace into auth_acl (userid,sectionid) values($id," . substr($var, 7) . ")");
				}
			}
		}
	}
    
    	header ("Location: $current_script_name");
	break;
 case 'remove':
	if (($config["remove_verification"]["value"] == "on") && ($args[confirm] != "yes")) {
		include_once ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this user?", getenv("HTTP_REFERER"), "user_admin.php?action=remove&id=$args[id]");
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
    
    header ("Location: $current_script_name");
    break;
 case 'edit_graph_config':
 	include_once ("include/functions.php");
	include_once ("include/top_header.php");
	$title_text = "User Management [edit]";
	include_once ("include/top_table_header.php");
	if (isset($args[id])) {
		$graph_settings = LoadSettingsIntoArray($HTTP_SESSION_VARS["user_id"], $config["guest_user"]["value"]);
	}else{
		unset($user);
	}
	
	draw_user_form_select();
 	new_table();
	
	?>
	<tr>
		<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Graph Preview Settings</td>
	</tr>
	
	<form method="post" action="user_admin.php">
	<?
	
	DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Height</font><br>
			The height of graphs created in preview mode.
		</td>
		<?DrawFormItemTextBox("Height",$graph_settings[Height],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Width</font><br>
			The width of graphs created in preview mode.
		</td>
		<?DrawFormItemTextBox("Width",$graph_settings[Width],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Timespan</font><br>
			The amount of time to represent on a graph created in preview mode (0 uses auto).
		</td>
		<?DrawFormItemTextBox("TimeSpan",$graph_settings[TimeSpan],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Default RRA</font><br>
			The default RRA to use when displaying graphs in preview mode.
		</td>
		<?DrawFormItemDropdownFromSQL("RRAID",db_fetch_assoc("select * from rrd_rra order by name"),"Name","ID",$graph_settings[RRAID],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Columns</font><br>
			The number of columns to display graphs in using preview mode.
		</td>
		<?DrawFormItemTextBox("ColumnNumber",$graph_settings[ColumnNumber],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Page Refresh</font><br>
			The number of seconds between automatic page refreshes.
		</td>
		<?DrawFormItemTextBox("PageRefresh",$graph_settings[PageRefresh],"","50", "40");?>
	</tr>
	
	<tr>
		<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Hierarchical Settings</td>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Default Graph Hierarchy</font><br>
			The default graph hierarchy to use when displaying graphs in tree mode.
		</td>
		<?DrawFormItemDropdownFromSQL("TreeID",db_fetch_assoc("select * from viewing_trees order by Title"),"Title","ID",$graph_settings[TreeID],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">View Settings</font><br>
			Options that govern how the graphs are displayed.
		</td>
		<td>
		<?
			DrawStrippedFormItemRadioButton("ViewType", $graph_settings[ViewType], "1", "Show a preview of the graph.", "1",true);
			DrawStrippedFormItemRadioButton("ViewType", $graph_settings[ViewType], "2", "Show a text-based listing of the graph.", "1",true);
		?>
		</td>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("ID",$args[id]);
	?>
	
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right" background="images/blue_line.gif">
			<?DrawFormSaveButton("save", "user_admin.php");?>
			</form>
		</td>
	</tr>
	
	<?
	
	include_once ("include/bottom_table_footer.php");
	include_once ("include/bottom_footer.php");
	
	break;
 case 'edit_perms':
	include_once ("include/top_header.php");
	$title_text = "User Management [edit]";
	include_once ("include/top_table_header.php");
	
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
	
	draw_user_form_select();
	new_table();
	
	?>
	<tr>
		<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C"><strong><?print db_fetch_cell("select username from auth_users where id=$args[id]");?></strong> is Currently is <strong><?print $graph_policy_text;?></strong> to View the Following Graphs:</td>
	</tr>
	
	<form method="post" action="user_admin.php">
	
	<?
	$perm_graphs = db_fetch_assoc("select rrd_graph.Title from
		auth_graph left join rrd_graph on auth_graph.graphid=rrd_graph.id
		where auth_graph.userid=$args[id]");
	
	DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%" colspan="2" class="textEditTitle">
			<?
			if (sizeof($perm_graphs) > 0) {
				foreach ($perm_graphs as $graph) {
					print "$graph[Title]<br>";
				}
			}else{
				print "<em>No Graphs</em>";
			}
			?>
		</td>
	</tr>
	<?
	if ($graph_policy == "1") {
		$graph_policy_text = "Select the graphs you want to <strong>DENY</strong> this user from.";
	} elseif ($graph_policy == "2") {
		$graph_policy_text = "Select the graphs you want <strong>ALLOW</strong> this user to view.";
	}
	
	$graphs = db_fetch_assoc("select 
		ag.UserID,
		g.ID, g.Title 
		from rrd_graph g
		left join auth_graph ag on (g.id=ag.graphid and ag.userid=$args[id]) 
		order by g.title");
	$rows = sizeof($graphs);
	
	?>
	<tr>
		<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C"><?print $graph_policy_text;?></td>
	</tr>
	
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
	DrawFormItemHiddenIDField("id",$args[id]);
	?>
    	
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right" background="images/blue_line.gif">
			<?DrawFormSaveButton("save_perms", "user_admin.php");?>
			</form>
		</td>
	</tr>
	
	<?
	include_once ("include/bottom_table_footer.php");
	include_once ("include/bottom_footer.php");
	
	break;
 case 'edit':
	include_once ("include/top_header.php");
	$title_text = "User Management [edit]";
	include_once ("include/top_table_header.php");
	
	if (isset($args[id])) {
		$user = db_fetch_row("select * from auth_users where id=$args[id]");
	}else{
		unset($user);
	}
	
	draw_user_form_select();
	new_table();
	
	?>
	<tr>
		<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">User Configuration</td>
	</tr>
	
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
	<?
   // if ($badpass == "true") {
//	DrawFormItem("Password","<font color=\"red\">Passwords do not match! Please retype.</font>");
    //} else{
//	DrawFormItem("Password","");
   // }
   ?>
    
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
	new_table();
	
	$sections = db_fetch_assoc("select 
		auth_acl.UserID,
		auth_sections.ID, auth_sections.Section
		from auth_sections
		left join auth_acl on (auth_sections.id=auth_acl.sectionid and auth_acl.userid=$args[id]) 
		order by auth_sections.Section");
	$rows = sizeof($sections);
	?>
	
	<tr>
		<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">User Permissions</td>
	</tr>
	
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
	DrawFormItemHiddenIDField("ID",$args[id]);
	DrawFormItemHiddenTextBox("_password",$user[Password],"");
	?>
	
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right" background="images/blue_line.gif">
			<?DrawFormSaveButton("save", "user_admin.php");?>
			</form>
		</td>
	</tr>
	
	<?
	include_once ("include/bottom_table_footer.php");
	include_once ("include/bottom_footer.php");
	
	break;
 default:
	include_once ("include/top_header.php");
	$title_text = "User Management"; $add_text = "$current_script_name?action=edit";
	include_once ("include/top_table_header.php");
    
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
				<a class="linkEditMain" href="<?print $current_script_name;?>?action=edit&id=<?print $user[ID];?>"><?print $user[Username];?></a>
			</td>
			<td>
				<?print $user[FullName];?>
			</td>
			<td>
				<?if ($user[GraphPolicy] == "1") { print "ALLOW"; }else{ print "DENY"; }?>
			</td>
			<td width="1%" align="right">
				<a href="<?print $current_script_name;?>?action=remove&id=<?print $user[ID];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	$i++;
	}
	}
    
	include_once ("include/bottom_footer.php");
	include_once ("include/bottom_table_footer.php");
	
	break;
} 
?>
