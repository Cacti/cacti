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
$section = "User Administration"; include ('include/auth.php');
include_once ("include/form.php");

switch ($_REQUEST["action"]) {
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
	global $colors; ?>
	<tr bgcolor="#<?print $colors["panel"];?>">
		</form>
		<form name="form_user">
		<td>
			<select name="cbo_user" onChange="window.location=document.form_user.cbo_user.options[document.form_user.cbo_user.selectedIndex].value">
				<option value="user_admin.php?action=user_edit&id=<?print $_GET["id"];?>"<?if ($_GET["action"] == "user_edit") {?> selected<?}?>>User Configuration</option>
				<option value="user_admin.php?action=graph_perms_edit&id=<?print $_GET["id"];?>"<?if ($_GET["action"] == "graph_perms_edit") {?> selected<?}?>>Individual Graph Permissions</option>
				<option value="user_admin.php?action=graph_config_edit&id=<?print $_GET["id"];?>"<?if ($_GET["action"] == "graph_config_edit") {?> selected<?}?>>User Graph Settings</option>
			</select>
		</td>
		</form>
	</tr>
<?}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $config;
	
	if ((isset($_POST["save_component_user"])) && (isset($_POST["save_component_graph_perms"])) && (isset($_POST["save_component_graph_config"]))) {
		user_save();
		graph_perms_save();
		/* graph_config_save(); */
		return "user_admin.php?action=user_edit&id=" . $_POST["user_id"];
	}elseif (isset($_POST["save_component_user"])) {
		user_save();
		return "user_admin.php";
	}elseif (isset($_POST["save_component_graph_perms"])) {
		graph_perms_save();
		return "user_admin.php";
	}elseif (isset($_POST["save_component_graph_config"])) {
		/* graph_config_save(); */
		return "user_admin.php";
	}
}

/* --------------------------
    Graph Permissions
   -------------------------- */

function graph_perms_save() {
	db_execute ("delete from user_auth_graph where user_id=" . $_POST["user_id"]);
    	db_execute ("delete from user_auth_tree where user_id=" . $_POST["user_id"]);
	
	reset($_POST);
	
	while (list($var, $val) = each($_POST)) {
		if (eregi("^[graph|tree]", $var)) {
			if (substr($var, 0, 5) == "graph") {
			    db_execute ("replace into user_auth_graph (user_id,local_graph_id) values(" . $_POST["user_id"] . "," . substr($var, 5) . ")");
			}elseif (substr($var, 0, 4) == "tree") {
			    db_execute ("replace into user_auth_tree (user_id,tree_id) values(" . $_POST["user_id"] . "," . substr($var, 4) . ")");
			}
		}
	}
}

function graph_perms_edit() {
	global $colors, $config;
	
	if (isset($_GET["id"])) {
		$graph_policy = db_fetch_cell("select graph_policy from user where id=" . $_GET["id"]);
		
		if ($graph_policy == "1") {
			$graph_policy_text = "DENIED";
		}elseif ($graph_policy == "2") {
			$graph_policy_text = "ALLOWED";
		}
	}else{
		unset($user);
	}
	
	if (read_config_option("full_view_user_admin") == "") {
		start_box("<strong>User Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_user_form_select();
		end_box();
	}
	
	if ($graph_policy == "1") {
		$graph_policy_text = "Select the graphs you want to <strong>DENY</strong> this user from.";
	} elseif ($graph_policy == "2") {
		$graph_policy_text = "Select the graphs you want <strong>ALLOW</strong> this user to view.";
	}
	
	start_box($graph_policy_text, "98%", $colors["header"], "3", "center", "");
	
	$graphs = db_fetch_assoc("select 
		user_auth_graph.user_id,
		graph_templates_graph.local_graph_id,
		graph_templates_graph.title
		from graph_templates_graph
		left join user_auth_graph on (graph_templates_graph.local_graph_id=user_auth_graph.local_graph_id and user_auth_graph.user_id=" . $_GET["id"] . ") 
		where graph_templates_graph.local_graph_id > 0
		order by graph_templates_graph.title");
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
			    if ($graph["user_id"] == "") {
				$old_value = "";
			    }else{
				$old_value = "on";
			    }
			    
			    $column1 = floor(($rows / 2) + ($rows % 2));
			    
			    if ($i == $column1) {
				print "</td><td valign='top' width='50%'>";
			    }
					
			    DrawStrippedFormItemCheckBox("graph".$graph["id"], $old_value, $graph["title"],"",true);
			    
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
	
	DrawFormItemHiddenIDField("user_id",$_GET["id"]);
	DrawFormItemHiddenTextBox("save_component_graph_perms","1","");
	
	if (read_config_option("full_view_user_admin") == "") {
		start_box("", "98%", $colors["header"], "3", "center", "");
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
	global $colors, $config;
	
	if (isset($_GET["id"])) {
		$graph_settings = LoadSettingsIntoArray($_SESSION["user_id"], read_config_option("guest_user"));
	}else{
		unset($user);
	}
	
	if (read_config_option("full_view_user_admin") == "") {
		start_box("<strong>User Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_user_form_select();
		end_box();
	}
	
	start_box("Graph Preview Settings", "98%", $colors["header"], "3", "center", "");
	
	?>
	<form method="post" action="user_admin.php">
	<?
	
	DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Height</font><br>
			The height of graphs created in preview mode.
		</td>
		<?DrawFormItemTextBox("Height",$graph_settings["height"],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Width</font><br>
			The width of graphs created in preview mode.
		</td>
		<?DrawFormItemTextBox("Width",$graph_settings["width"],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Timespan</font><br>
			The amount of time to represent on a graph created in preview mode (0 uses auto).
		</td>
		<?DrawFormItemTextBox("TimeSpan",$graph_settings["time_span"],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Default RRA</font><br>
			The default RRA to use when displaying graphs in preview mode.
		</td>
		<?DrawFormItemDropdownFromSQL("RRAID",db_fetch_assoc("select * from rrd_rra order by name"),"Name","ID",$graph_settings["rra_id"],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Columns</font><br>
			The number of columns to display graphs in using preview mode.
		</td>
		<?DrawFormItemTextBox("ColumnNumber",$graph_settings["column_number"],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Page Refresh</font><br>
			The number of seconds between automatic page refreshes.
		</td>
		<?DrawFormItemTextBox("PageRefresh",$graph_settings["page_refresh"],"","50", "40");?>
	</tr>
	
	<tr>
		<td colspan="2" class="textSubHeaderDark" bgcolor="#00438C">Hierarchical Settings</td>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Default Graph Hierarchy</font><br>
			The default graph hierarchy to use when displaying graphs in tree mode.
		</td>
		<?DrawFormItemDropdownFromSQL("TreeID",db_fetch_assoc("select * from viewing_trees order by Title"),"Title","ID",$graph_settings["tree_id"],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">View Settings</font><br>
			Options that govern how the graphs are displayed.
		</td>
		<td>
		<?
			DrawStrippedFormItemRadioButton("ViewType", $graph_settings["view_type"], "1", "Show a preview of the graph.", "1",true);
			DrawStrippedFormItemRadioButton("ViewType", $graph_settings["view_type"], "2", "Show a text-based listing of the graph.", "1",true);
		?>
		</td>
	</tr>
	
	<?
	end_box();
	
	DrawFormItemHiddenIDField("user_id",$_GET["id"]);
	DrawFormItemHiddenTextBox("save_component_graph_config","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
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
	/* only change password when user types one */
	if ($_POST["password"] != $_POST["Confirm"]) {
		$passwords_do_not_match = true;
	}elseif (($_POST["password"] == "") && ($_POST["Confirm"] == "")) {
		$password = $_POST["_password"];
	}else{
		$password = "PASSWORD('" . $_POST["password"] . "')";
	}
	
	if ($passwords_do_not_match != true) {
		$save["id"] = $_POST["user_id"];
		$save["username"] = $_POST["username"];
		$save["full_name"] = $_POST["full_name"];
		$save["password"] = $password;
		$save["must_change_password"] = $_POST["must_change_password"];
		$save["show_tree"] = $_POST["show_tree"];
		$save["show_list"] = $_POST["show_list"];
		$save["show_preview"] = $_POST["show_preview"];
		$save["graph_settings"] = $_POST["graph_settings"];
		$save["login_opts"] = $_POST["login_opts"];
		$save["graph_policy"] = $_POST["graph_policy"];
		
		$id = sql_save($save, "user");
		
		reset($_POST);
		
		db_execute("delete from user_auth_realm where user_id=$id");
		
		while (list($var, $val) = each($_POST)) {
			if (eregi("^[section]", $var)) {
				if (substr($var, 0, 7) == "section") {
				    db_execute ("replace into user_auth_realm (user_id,realm_id) values($id," . substr($var, 7) . ")");
				}
			}
		}
	}	
}

function user_remove() {
	global $config;
	
	if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the user <strong>'" . db_fetch_cell("select username from user where id=" . $_GET["id"]) . "'</strong>?", getenv("HTTP_REFERER"), "user_admin.php?action=user_remove&id=" . $_GET["id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || ($_GET["confirm"] == "yes")) {
	    db_execute("delete from user where id=" . $_GET["id"]);
	    db_execute("delete from user_auth_realm where user_id=" . $_GET["id"]);
	    db_execute("delete from user_auth_hosts where user_id=" . $_GET["id"]);
	    db_execute("delete from user_auth_graph where user_id=" . $_GET["id"]);
	    db_execute("delete from user_auth_tree where user_id=" . $_GET["id"]);
	    db_execute("delete from settings_graphs where user_id=" . $_GET["id"]);
	}	
}

function user_edit() {
	global $colors, $config;
	
	if (isset($_GET["id"])) {
		$user = db_fetch_row("select * from user where id=" . $_GET["id"]);
	}else{
		unset($user);
	}
	
	if (read_config_option("full_view_user_admin") == "") {
		start_box("<strong>User Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_user_form_select();
		end_box();
	}
	
	start_box("User Configuration", "98%", $colors["header"], "3", "center", "");
	
	?>
	<form method="post" action="user_admin.php">
	<?
	
	DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">User Name</font><br>
			
		</td>
		<?DrawFormItemTextBox('username',$user["username"],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Full Name</font><br>
			
		</td>
		<?DrawFormItemTextBox('full_name',$user["full_name"],"","");?>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Password</font><br>
			
		</td>
		<td>
			<?DrawStrippedFormItemPasswordTextBox("password","","","","40");?><br>
			<?DrawStrippedFormItemPasswordTextBox("confirm","","","","40");?>
		</td>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Account Options</font><br>
			
		</td>
		<td>
		<?
			DrawStrippedFormItemCheckBox("must_change_password",$user["must_change_password"],"User Must Change Password at Next Login","",true);
			DrawStrippedFormItemCheckBox("graph_settings",$user["graph_settings"],"Allow this User to Keep Custom Graph Settings","on",true);
		?>
		</td>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Graph Options</font><br>
			
		</td>
		<td>
		<?
			DrawStrippedFormItemCheckBox("show_tree",$user["show_tree"],"User Has Rights to View Tree Mode","on",true);
			DrawStrippedFormItemCheckBox("show_list",$user["show_list"],"User Has Rights to View List Mode","on",true);
			DrawStrippedFormItemCheckBox("show_preview",$user["show_preview"],"User Has Rights to View Preview Mode","on",true);
		?>
		</td>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Default Policy</font><br>
			The default allow/deny graph policy for this user.
		</td>
		<?
		DrawFormItemDropDownCustomHeader("graph_policy");
		DrawFormItemDropDownCustomItem("graph_policy","1","Allow",$user["graph_policy"]);
		DrawFormItemDropDownCustomItem("graph_policy","2","Deny",$user["graph_policy"]);
		DrawFormItemDropDownCustomFooter();
		?>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Login Options</font><br>
			What to do when this user logs in.
		</td>
		<td>
		<?
			DrawStrippedFormItemRadioButton("login_opts", $user["login_opts"], "1", "Show the page that user pointed their browser to.","1",true);
			DrawStrippedFormItemRadioButton("login_opts", $user["login_opts"], "2", "Show the default console screen.","1",true);
			DrawStrippedFormItemRadioButton("login_opts", $user["login_opts"], "3", "Show the default graph screen.","1",true);
		?>
		</td>
	</tr>
	
	<?
	end_box();
	start_box("User Permissions", "98%", $colors["header"], "3", "center", "");
	
	$realms = db_fetch_assoc("select 
		user_auth_realm.user_id,
		user_realm.id,
		user_realm.name
		from user_realm
		left join user_auth_realm on (user_realm.id=user_auth_realm.realm_id and user_auth_realm.user_id=" . $_GET["id"] . ") 
		order by user_realm.name");
	
	?>
	
	<tr>
		<td colspan="2" width="100%">
			<table width="100%">
				<tr>
					<td align="top" width="50%">
		<?
		
		if (sizeof($realms) > 0) {
		foreach ($realms as $realm) {
			if ($realm["user_id"] == "") {
				$old_value = "";
			}else{
				$old_value = "on";
			}
			
			$column1 = floor((sizeof($realms) / 2) + (sizeof($realms) % 2));
			
			if ($i == $column1) {
				print "</td><td valign='top' width='50%'>";
			}
			
			DrawStrippedFormItemCheckBox("section".$realm["id"], $old_value, $realm["name"],"",true);
			
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
	
	DrawFormItemHiddenIDField("user_id",$_GET["id"]);
	DrawFormItemHiddenTextBox("_password",$user["Password"],"");
	DrawFormItemHiddenTextBox("save_component_user","1","");
	
	if (read_config_option("full_view_user_admin") == "") {
		start_box("", "98%", $colors["header"], "3", "center", "");
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
	
	if (read_config_option("full_view_user_admin") == "on") {
		graph_perms_edit();
		graph_config_edit();
	}	
}

function user() {
	global $colors;
	
	start_box("<strong>User Management</strong>", "98%", $colors["header"], "3", "center", "user_admin.php?action=user_edit");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("User Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("Full Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("Default Graph Policy",$colors["header_text"],2);
	print "</tr>";
	
	$user_list = db_fetch_assoc("select id,username,full_name,graph_policy from user order by username");
	
	if (sizeof($user_list) > 0) {
	foreach ($user_list as $user) {
		DrawMatrixRowAlternateColorBegin($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="user_admin.php?action=user_edit&id=<?print $user["id"];?>"><?print $user["username"];?></a>
			</td>
			<td>
				<?print $user["full_name"];?>
			</td>
			<td>
				<?if ($user["graph_policy"] == "1") { print "ALLOW"; }else{ print "DENY"; }?>
			</td>
			<td width="1%" align="right">
				<a href="user_admin.php?action=user_remove&id=<?print $user["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	$i++;
	}
	}
	end_box();	
}
?>
