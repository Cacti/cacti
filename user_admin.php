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

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'user_remove':
		user_remove();
    
    		header ("Location: user_admin.php");
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
	?>
	<table class='tabs' width='98%' cellspacing='0' cellpadding='3' align='center'>
		<tr>
			<td <?php print (($_GET["action"] == "user_edit") ? "bgcolor='silver'" : "bgcolor='#DFDFDF'");?> nowrap='nowrap' width='150' align='center' class='tab'>
				<span class='textHeader'><a href='user_admin.php?action=user_edit&id=<?php print $_GET["id"];?>'>User Configuration</a></span>
			</td>
			<td width='1'></td>
			<td <?php print (($_GET["action"] == "graph_perms_edit") ? "bgcolor='silver'" : "bgcolor='#DFDFDF'");?> nowrap='nowrap' width='230' align='center' class='tab'>
				<span class='textHeader'><a href='user_admin.php?action=graph_perms_edit&id=<?php print $_GET["id"];?>'>Individual Graph Permissions</a></span>
			</td>
			<td width='1'></td>
			<td <?php print (($_GET["action"] == "graph_config_edit") ? "bgcolor='silver'" : "bgcolor='#DFDFDF'");?> nowrap='nowrap' width='150' align='center' class='tab'>
				<span class='textHeader'><a href='user_admin.php?action=graph_config_edit&id=<?php print $_GET["id"];?>'>User Graph Settings</a></span>
			</td>
			<td width='1'></td>
			<td></td>
		</tr>
	</table>
<?php }

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $settings_graphs;
	
	/* user management save */
	if (isset($_POST["save_component_user"])) {
		if (($_POST["password"] == "") && ($_POST["password_confirm"] == "")) {
			$password = db_fetch_cell("select password from user_auth where id=" . $_POST["id"]);
		}else{
			$password = md5($_POST["password"]);
		}
		
		/* check to make sure the passwords match; if not error */
		if ($_POST["password"] != $_POST["password_confirm"]) {
			raise_message(4);
		}
		
		form_input_validate($_POST["password"], "password", "" . $_POST["password_confirm"] . "", true, 4);
		form_input_validate($_POST["password_confirm"], "password_confirm", "" . $_POST["password"] . "", true, 4);
		
		$save["id"] = $_POST["id"];
		$save["username"] = form_input_validate($_POST["username"], "username", "^[A-Za-z_0-9]+$", false, 3);
		$save["full_name"] = form_input_validate($_POST["full_name"], "full_name", "", true, 3);
		$save["password"] = $password;
		$save["must_change_password"] = form_input_validate((isset($_POST["must_change_password"]) ? $_POST["must_change_password"] : ""), "must_change_password", "", true, 3);
		$save["show_tree"] = form_input_validate((isset($_POST["show_tree"]) ? $_POST["show_tree"] : ""), "show_tree", "", true, 3);
		$save["show_list"] = form_input_validate((isset($_POST["show_list"]) ? $_POST["show_list"] : ""), "show_list", "", true, 3);
		$save["show_preview"] = form_input_validate((isset($_POST["show_preview"]) ? $_POST["show_preview"] : ""), "show_preview", "", true, 3);
		$save["graph_settings"] = form_input_validate((isset($_POST["graph_settings"]) ? $_POST["graph_settings"] : ""), "graph_settings", "", true, 3);
		$save["login_opts"] = form_input_validate($_POST["login_opts"], "login_opts", "", true, 3);
		$save["graph_policy"] = form_input_validate((isset($_POST["graph_policy"]) ? $_POST["graph_policy"] : $_POST["_graph_policy"]), "graph_policy", "", true, 3);
		
		if (!is_error_message()) {
			$user_id = sql_save($save, "user_auth");
			
			if ($user_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
			
			db_execute("delete from user_auth_realm where user_id=$user_id");
			
			while (list($var, $val) = each($_POST)) {
				if (eregi("^[section]", $var)) {
					if (substr($var, 0, 7) == "section") {
					    db_execute("replace into user_auth_realm (user_id,realm_id) values($user_id," . substr($var, 7) . ")");
					}
				}
			}
		}
	}
	
	/* graph permissions */
	if ((isset($_POST["save_component_graph_perms"])) && (!is_error_message())) {
		db_execute("delete from user_auth_graph where user_id=" . (!empty($user_id) ? $user_id : $_POST["user_id"]));
		db_execute("delete from user_auth_tree where user_id=" . (!empty($user_id) ? $user_id : $_POST["user_id"]));
		db_execute("update user_auth set graph_policy='" . $_POST["graph_policy"] . "' where id=" . (!empty($user_id) ? $user_id : $_POST["user_id"]));
		
		reset($_POST);
		
		while (list($var, $val) = each($_POST)) {
			if (eregi("^[graph|tree]", $var)) {
				if (substr($var, 0, 5) == "graph") {
				    db_execute("replace into user_auth_graph (user_id,local_graph_id) values(" . (!empty($user_id) ? $user_id : $_POST["user_id"]) . "," . substr($var, 5) . ")");
				}elseif (substr($var, 0, 4) == "tree") {
				    db_execute("replace into user_auth_tree (user_id,tree_id) values(" . (!empty($user_id) ? $user_id : $_POST["user_id"]). "," . substr($var, 4) . ")");
				}
			}
		}
	}
	
	/* per-user graph permissions */
	if ((isset($_POST["save_component_graph_config"])) && (!is_error_message())) {
		if (sizeof($settings_graphs) > 0) {
		foreach (array_keys($settings_graphs) as $setting) {
			if (isset($_POST[$setting])) {
				db_execute("replace into settings_graphs (user_id,name,value) values (" . (!empty($user_id) ? $user_id : $_POST["user_id"]) . ",'$setting', '" . $_POST[$setting] . "')");
			}
		}
		}
		
		/* reset local settings cache so the user sees the new settings */
		kill_session_var("sess_graph_config_array");
	}
	
	/* redirect to the appropriate page */
	if (is_error_message()) {
		header ("Location: user_admin.php?action=user_edit&id=" . (empty($user_id) ? $_POST["id"] : $user_id));
	}else{
		header ("Location: user_admin.php");
	}
}

/* --------------------------
    Graph Permissions
   -------------------------- */

function graph_perms_edit() {
	global $colors;
	
	$graph_policy = "2";
	
	if (!empty($_GET["id"])) {
		$graph_policy = db_fetch_cell("select graph_policy from user_auth where id=" . $_GET["id"]);
		
		if ($graph_policy == "1") {
			$graph_policy_text = "DENIED";
		}elseif ($graph_policy == "2") {
			$graph_policy_text = "ALLOWED";
		}
		
		$header_label = "[edit: " . db_fetch_cell("select username from user_auth where id=" . $_GET["id"]) . "]";
	}
	
	if (read_config_option("full_view_user_admin") == "") {
		draw_user_form_select();
	}
	
	start_box("<strong>Graph Permissions</strong> [policy: " . (($graph_policy == "1") ? "ALLOW" : "DENY") . "]", "98%", $colors["header"], "3", "center", "");
	
	$graphs = db_fetch_assoc("select 
		user_auth_graph.user_id,
		graph_templates_graph.local_graph_id,
		graph_templates_graph.title_cache,
		graph_local.host_id
		from graph_templates_graph
		left join user_auth_graph on (graph_templates_graph.local_graph_id=user_auth_graph.local_graph_id and user_auth_graph.user_id=" . (empty($_GET["id"]) ? "0" : $_GET["id"]) . ") 
		left join graph_local on graph_templates_graph.local_graph_id=graph_local.id
		where graph_templates_graph.local_graph_id > 0
		order by graph_templates_graph.title_cache");
	$trees = db_fetch_assoc("select 
		user_auth_tree.user_id,
		graph_tree.id as tree_id,
		graph_tree.name
		from graph_tree
		left join user_auth_tree on (graph_tree.id=user_auth_tree.tree_id and user_auth_tree.user_id=" . (empty($_GET["id"]) ? "0" : $_GET["id"]) . ") 
		order by graph_tree.name");
	
	?>
	<form method="post" action="user_admin.php">
	
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td width="50%">
			<font class="textEditTitle">Default Policy</font><br>
			The default allow/deny graph policy for this user.
		</td>
		<?php
		$graph_policy_array = array(
			1 => "Allow",
			2 => "Deny");
		
		form_dropdown("graph_policy",$graph_policy_array,"","",$graph_policy,"","");
		?>
	</tr>
	
	<tr>
		<td colspan="2" width="100%">
			<table width="100%">
				<tr>
					<td align="top" width="50%">
						<?php
						$i = 0;
						if (sizeof($graphs) > 0) {
						foreach ($graphs as $graph) {
							if ($graph["user_id"] == "") {
								$old_value = "";
							}else{
								$old_value = "on";
							}
							
							$column1 = floor((sizeof($graphs) / 2) + (sizeof($graphs) % 2));
							
							if ($i == $column1) {
								print "</td><td valign='top' width='50%'>";
							}
							
							form_base_checkbox("graph" . $graph["local_graph_id"], $old_value, title_trim($graph["title_cache"], 60), "", (!empty($_GET["id"]) ? 1 : 0), true);
							$i++;
						}
						}
						?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["header"];?>">
		<td class="textHeaderDark" colspan="2">
			<strong>Tree Permissions</strong> [policy: <?php print (($graph_policy == "1") ? "ALLOW" : "DENY");?>]
		</td>
	</tr>
	<tr>
		<td colspan="2" width="100%">
			<table width="100%">
				<tr>
					<td align="top" width="50%">
						<?php
						$i = 0;
						if (sizeof($trees) > 0) {
						foreach ($trees as $tree) {
							if ($tree["user_id"] == "") {
								$old_value = "";
							}else{
								$old_value = "on";
							}
							
							$column1 = floor((sizeof($trees) / 2) + (sizeof($trees) % 2));
							
							if ($i == $column1) {
								print "</td><td valign='top' width='50%'>";
							}
							
							form_base_checkbox("tree" . $tree["tree_id"], $old_value, $tree["name"], "", (!empty($_GET["id"]) ? 1 : 0), true);
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
	
	form_hidden_id("user_id",(isset($_GET["id"]) ? $_GET["id"] : "0"));
	form_hidden_box("save_component_graph_perms","1","");
	
	if (read_config_option("full_view_user_admin") == "") {
		form_save_button("user_admin.php");
	}
}

/* --------------------------
    Per-User Graph Config
   -------------------------- */

function graph_config_edit() {
	include ("include/config_arrays.php");
	include_once ("include/functions.php");
	
	global $colors, $tabs_graphs, $settings_graphs;
	
	if (!empty($_GET["id"])) {
		$header_label = "[edit: " . db_fetch_cell("select username from user_auth where id=" . $_GET["id"]) . "]";
	}
	
	if (read_config_option("full_view_user_admin") == "") {
		draw_user_form_select();
	}
	
	print "<form method='post' action='user_admin.php'>\n";
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	if (sizeof($tabs_graphs) > 0) {
	foreach (array_keys($tabs_graphs) as $tab_short_name) {
		?>
		<tr>
			<td colspan="2" bgcolor="#<?php print $colors["header"];?>">
				<span class="textHeaderDark"><strong>Graph Settings</strong> [<?php print $tabs_graphs[$tab_short_name];?>]</span>
			</td>
		</tr>
		<?php
		
		reset($settings_graphs);
		
		$i = 0;
		if (sizeof($settings_graphs) > 0) {
		foreach (array_keys($settings_graphs) as $setting) {
			/* make sure to skip group members here; only parents are allowed */
			if (($settings_graphs[$setting]["method"] != "internal") && ($settings_graphs[$setting]["tab"] == $tab_short_name)) {
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
				
				/* draw the acual header and textbox on the form */
				form_item_label($settings_graphs[$setting]["friendly_name"],$settings_graphs[$setting]["description"]);
				
				$current_value = db_fetch_cell("select value from settings_graphs where name='$setting' and user_id=" . (isset($_GET["id"]) ? $_GET["id"] : "0"));
				
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
				
				$i++;
				
				print "</tr>\n";
			}
		
		}
		}
	
	}
	}
	
	end_box();
	
	form_hidden_id("user_id",(isset($_GET["id"]) ? $_GET["id"] : "0"));
	form_hidden_box("save_component_graph_config","1","");
	
	form_save_button("user_admin.php");
}

/* --------------------------
    User Administration
   -------------------------- */

function user_remove() {
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include ('include/top_header.php');
		form_confirm("Are You Sure?", "Are you sure you want to delete the user <strong>'" . db_fetch_cell("select username from user_auth where id=" . $_GET["id"]) . "'</strong>?", $_SERVER["HTTP_REFERER"], "user_admin.php?action=user_remove&id=" . $_GET["id"]);
		include ('include/bottom_footer.php');
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from user_auth where id=" . $_GET["id"]);
		db_execute("delete from user_auth_realm where user_id=" . $_GET["id"]);
		db_execute("delete from user_auth_hosts where user_id=" . $_GET["id"]);
		db_execute("delete from user_auth_graph where user_id=" . $_GET["id"]);
		db_execute("delete from user_auth_tree where user_id=" . $_GET["id"]);
		db_execute("delete from settings_graphs where user_id=" . $_GET["id"]);
	}	
}

function user_edit() {
	global $colors;
	
	if (!empty($_GET["id"])) {
		$user = db_fetch_row("select * from user_auth where id=" . $_GET["id"]);
		$header_label = "[edit: " . $user["username"] . "]";
	}else{
		$header_label = "[new]";
	}
	
	if (read_config_option("full_view_user_admin") == "") {
		draw_user_form_select();
	}
	
	start_box("<strong>User Management</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	?>
	<form method="post" name="chk" action="user_admin.php">
	<?php
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">User Name</font><br>
			The login name for this user.
		</td>
		<?php form_text_box('username',(isset($user) ? $user["username"] : ""),"","");?>
	</tr>
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Full Name</font><br>
			A more descriptive name for this user, that can include spaces or special characters.
		</td>
		<?php form_text_box('full_name',(isset($user) ? $user["full_name"] : ""),"","");?>
	</tr>
    
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Password</font><br>
			Enter the password for this user twice. Remember that passwords are case sensitive!
		</td>
		<td>
			<?php form_base_text_box("password","","","","40","password");?><br>
			<?php form_base_text_box("password_confirm","","","","40","password");?>
		</td>
	</tr>
    
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Account Options</font><br>
			Set any user account-specific options here.
		</td>
		<td>
		<?php
			form_base_checkbox("must_change_password", (isset($user) ? $user["must_change_password"] : ""), "User Must Change Password at Next Login", "", (!empty($_GET["id"]) ? 1 : 0), true);
			form_base_checkbox("graph_settings", (isset($user) ? $user["graph_settings"] : ""), "Allow this User to Keep Custom Graph Settings", "on", (!empty($_GET["id"]) ? 1 : 0), true);
		?>
		</td>
	</tr>
    
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Graph Options</font><br>
			Set any graph-specific options here.
		</td>
		<td>
		<?php
			form_base_checkbox("show_tree", (isset($user) ? $user["show_tree"] : ""), "User Has Rights to Tree View", "on", (!empty($_GET["id"]) ? 1 : 0), true);
			form_base_checkbox("show_list", (isset($user) ? $user["show_list"] : ""), "User Has Rights to List View", "on", (!empty($_GET["id"]) ? 1 : 0), true);
			form_base_checkbox("show_preview", (isset($user) ? $user["show_preview"] : ""), "User Has Rights to Preview View", "on", (!empty($_GET["id"]) ? 1 : 0), true);
		?>
		</td>
	</tr>
    	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Login Options</font><br>
			What to do when this user logs in.
		</td>
		<td>
		<?php
			form_base_radio_button("login_opts", (isset($user) ? $user["login_opts"] : ""), "1", "Show the page that user pointed their browser to.","1",true);
			form_base_radio_button("login_opts", (isset($user) ? $user["login_opts"] : ""), "2", "Show the default console screen.","1",true);
			form_base_radio_button("login_opts", (isset($user) ? $user["login_opts"] : ""), "3", "Show the default graph screen.","1",true);
		?>
		</td>
	</tr>
	
	<?php
	end_box();
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	print "	<tr bgcolor='#" . $colors["header"] . "'>
			<td class='textHeaderDark'><strong>Realm Permissions</strong></td>
			<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectForce(\"section\")'></td>\n
		</tr>\n";
	
	$realms = db_fetch_assoc("select 
		user_auth_realm.user_id,
		user_realm.id,
		user_realm.name
		from user_realm
		left join user_auth_realm on (user_realm.id=user_auth_realm.realm_id and user_auth_realm.user_id=" . (empty($_GET["id"]) ? "0" : $_GET["id"]) . ") 
		order by user_realm.name");
	
	?>
	
	<tr>
		<td colspan="2" width="100%">
			<table width="100%">
				<tr>
					<td align="top" width="50%">
						<?php
						$i = 0;
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
							
							form_base_checkbox("section" . $realm["id"], $old_value, $realm["name"], "", (!empty($_GET["id"]) ? 1 : 0), true);
							
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
	
	form_hidden_id("id",(isset($user) ? $user["id"] : "0"));
	form_hidden_id("_graph_policy",(isset($user) ? $user["graph_policy"] : "2"));
	form_hidden_box("save_component_user","1","");
	
	if (read_config_option("full_view_user_admin") == "") {
		form_save_button("user_admin.php");
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
		DrawMatrixHeaderItem("Default Graph Policy",$colors["header_text"],1);
                DrawMatrixHeaderItem("Last Login",$colors["header_text"],2);
	print "</tr>";
	
	$user_list = db_fetch_assoc("select id, user_auth.username, full_name, graph_policy, DATE_FORMAT(max(time),'%M %e %Y %H:%i:%s') as time from user_auth left join user_log on user_auth.username = user_log.username group by id");
	
	$i = 0;
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
			<td>
				<?php print $user["time"];?>
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
