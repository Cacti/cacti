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
include ("include/config_settings.php");
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
	<tr bgcolor="#<?php print $colors["panel"];?>">
		</form>
		<form name="form_user">
		<td>
			<select name="cbo_user" onChange="window.location=document.form_user.cbo_user.options[document.form_user.cbo_user.selectedIndex].value">
				<option value="user_admin.php?action=user_edit&id=<?php print $_GET["id"];?>"<?php if ($_GET["action"] == "user_edit") {?> selected<?php }?>>User Configuration</option>
				<option value="user_admin.php?action=graph_perms_edit&id=<?php print $_GET["id"];?>"<?php if ($_GET["action"] == "graph_perms_edit") {?> selected<?php }?>>Individual Graph Permissions</option>
				<option value="user_admin.php?action=graph_config_edit&id=<?php print $_GET["id"];?>"<?php if ($_GET["action"] == "graph_config_edit") {?> selected<?php }?>>User Graph Settings</option>
			</select>
		</td>
		</form>
	</tr>
<?php }

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
		<?php
		
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
					
			    form_base_checkbox("graph" . $graph["id"], $old_value, $graph["title"], "", $_GET["id"], true);
			    $i++;
			}
		}
		?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	
    	<?php
	end_box();
	
	form_hidden_id("user_id",$_GET["id"]);
	form_hidden_box("save_component_graph_perms","1","");
	
	if (read_config_option("full_view_user_admin") == "") {
		start_box("", "98%", $colors["header"], "3", "center", "");
		?>
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right">
				<?php form_save_button("save", "user_admin.php");?>
			</td>
		</tr>
		</form>
		<?php
		end_box();
	}
}

/* --------------------------
    Per-User Graph Config
   -------------------------- */

function graph_config_edit() {
	include_once ("include/config_arrays.php");
	include_once ("include/functions.php");
	
	global $colors, $tabs_graphs, $settings_graphs;
	
	if (read_config_option("full_view_user_admin") == "") {
		start_box("<strong>User Management [edit]</strong>", "98%", $colors["header"], "3", "center", "");
		draw_user_form_select();
		end_box();
	}
	
	print "<form method='post' action='user_admin.php'>\n";
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	if (sizeof($tabs_graphs) > 0) {
	foreach (array_keys($tabs_graphs) as $tab_short_name) {
		?>
		<tr>
			<td colspan="2" bgcolor="#<?php print $colors["header"];?>">
				<span class="textHeaderDark"><?php print $tabs_graphs[$tab_short_name];?></span>
			</td>
		</tr>
		<?php
		
		reset($settings_graphs);
		
		if (sizeof($settings_graphs) > 0) {
		foreach (array_keys($settings_graphs) as $setting) {
			/* make sure to skip group members here; only parents are allowed */
			if (($settings_graphs[$setting]["method"] != "internal") && ($settings_graphs[$setting]["tab"] == $tab_short_name)) {
				++$i;
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
				
				/* draw the acual header and textbox on the form */
				form_item_label($settings_graphs[$setting]["friendly_name"],$settings_graphs[$setting]["description"]);
				
				$current_value = db_fetch_cell("select value from settings_graphs where name='$setting' and user_id=" . $_GET["id"]);
				
				/* choose what kind of item this is */
				switch ($settings_graphs[$setting]["method"]) {
					case 'textbox':
						form_text_box($setting,$current_value,$settings_graphs[$setting]["default"],"");
						break;
					case 'drop_sql':
						form_dropdown($setting,db_fetch_assoc($settings_graphs[$setting]["sql"]),"name","id",$current_value,"",$settings_graphs[$setting]["default"]);
						break;
					case 'drop_array':
						form_dropdown($setting,${$settings_graphs[$setting]["array_name"]},"","",$current_value,"",$settings_graphs[$setting]["default"]);
						break;
				}
				
				print "</tr>\n";
			}
		
		}
		}
	
	}
	}
	
	end_box();
	
	form_hidden_id("user_id",$_GET["id"]);
	form_hidden_box("save_component_graph_config","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?php form_save_button("save", "user_admin.php");?>
		</td>
	</tr>
	</form>
	<?php
	end_box();
}

/* --------------------------
    User Administration
   -------------------------- */

function user_save() {
	/* only change password when user types one */
	if ($_POST["password"] != $_POST["confirm"]) {
		$passwords_do_not_match = true;
	}elseif (($_POST["password"] == "") && ($_POST["confirm"] == "")) {
		$password = db_fetch_cell("select password from user where id=" . $_POST["id"]);
	}else{
		$password = "PASSWORD('" . $_POST["password"] . "')";
	}
	
	if ($passwords_do_not_match != true) {
		$save["id"] = $_POST["id"];
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
		
		$user_id = sql_save($save, "user");
		
		db_execute("delete from user_auth_realm where user_id=$user_id");
		
		while (list($var, $val) = each($_POST)) {
			if (eregi("^[section]", $var)) {
				if (substr($var, 0, 7) == "section") {
				    db_execute ("replace into user_auth_realm (user_id,realm_id) values($user_id," . substr($var, 7) . ")");
				}
			}
		}
	}	
}

function user_remove() {
	global $config;
	
	if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
		include ('include/top_header.php');
		form_confirm("Are You Sure?", "Are you sure you want to delete the user <strong>'" . db_fetch_cell("select username from user where id=" . $_GET["id"]) . "'</strong>?", getenv("HTTP_REFERER"), "user_admin.php?action=user_remove&id=" . $_GET["id"]);
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
	<?php
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">User Name</font><br>
			
		</td>
		<?php form_text_box('username',$user["username"],"","");?>
	</tr>
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Full Name</font><br>
			
		</td>
		<?php form_text_box('full_name',$user["full_name"],"","");?>
	</tr>
    
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Password</font><br>
			
		</td>
		<td>
			<?php form_base_password_box("password","","","","40");?><br>
			<?php form_base_password_box("confirm","","","","40");?>
		</td>
	</tr>
    
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Account Options</font><br>
			
		</td>
		<td>
		<?php
			form_base_checkbox("must_change_password", $user["must_change_password"], "User Must Change Password at Next Login", "", $_GET["id"], true);
			form_base_checkbox("graph_settings", $user["graph_settings"], "Allow this User to Keep Custom Graph Settings", "on", $_GET["id"], true);
		?>
		</td>
	</tr>
    
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Graph Options</font><br>
			
		</td>
		<td>
		<?php
			form_base_checkbox("show_tree", $user["show_tree"], "User Has Rights to View Tree Mode", "on", $_GET["id"], true);
			form_base_checkbox("show_list", $user["show_list"], "User Has Rights to View List Mode", "on", $_GET["id"], true);
			form_base_checkbox("show_preview", $user["show_preview"], "User Has Rights to View Tree Mode", "on", $_GET["id"], true);
		?>
		</td>
	</tr>
    
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Default Policy</font><br>
			The default allow/deny graph policy for this user.
		</td>
		<?php
		$graph_policy = array(
			1 => "Allow",
			2 => "Deny");
		
		form_dropdown("graph_policy",$graph_policy,"","",$user["graph_policy"],"","");
		?>
	</tr>
    
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Login Options</font><br>
			What to do when this user logs in.
		</td>
		<td>
		<?php
			form_base_radio_button("login_opts", $user["login_opts"], "1", "Show the page that user pointed their browser to.","1",true);
			form_base_radio_button("login_opts", $user["login_opts"], "2", "Show the default console screen.","1",true);
			form_base_radio_button("login_opts", $user["login_opts"], "3", "Show the default graph screen.","1",true);
		?>
		</td>
	</tr>
	
	<?php
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
		<?php
		
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
			
			form_base_checkbox("section" . $realm["id"], $old_value, $realm["name"], "", $_GET["id"], true);
			
			$i++;
		}
		}
		?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	
	<?php
	end_box();
	
	form_hidden_id("id",$_GET["id"]);
	form_hidden_box("save_component_user","1","");
	
	if (read_config_option("full_view_user_admin") == "") {
		start_box("", "98%", $colors["header"], "3", "center", "");
		?>
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right">
				<?php form_save_button("save", "user_admin.php");?>
			</td>
		</tr>
		</form>
		<?php
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
		form_alternate_row_color($colors["alternate"],$colors["light"],$i);
			?>
			<td>
				<a class="linkEditMain" href="user_admin.php?action=user_edit&id=<?php print $user["id"];?>"><?php print $user["username"];?></a>
			</td>
			<td>
				<?php print $user["full_name"];?>
			</td>
			<td>
				<?php if ($user["graph_policy"] == "1") { print "ALLOW"; }else{ print "DENY"; }?>
			</td>
			<td width="1%" align="right">
				<a href="user_admin.php?action=user_remove&id=<?php print $user["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?php
	$i++;
	}
	}
	end_box();	
}
?>
