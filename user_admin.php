<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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

include("./include/auth.php");
include("./include/config_settings.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	case 'perm_remove':
		perm_remove();
    
		break;
	case 'user_remove':
		user_remove();
    
    		header("Location: user_admin.php");
		break;
	case 'user_realms_edit':
		include_once("include/top_header.php");
		
		user_edit();
	
		include_once("include/bottom_footer.php");
		break;
	case 'graph_settings_edit':
		include_once("include/top_header.php");
		
		user_edit();
	
		include_once("include/bottom_footer.php");
		break;
	case 'graph_perms_edit':
		include_once("include/top_header.php");
	
		user_edit();
	
		include_once("include/bottom_footer.php");
		break;
	case 'user_edit':
		include_once("include/top_header.php");
		
		user_edit();
		
		include_once("include/bottom_footer.php");
		break;
	default:
		include_once("include/top_header.php");
		
		user();
	
		include_once("include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $settings_graphs;
	
	/* graph permissions */
	if ((isset($_POST["save_component_graph_perms"])) && (!is_error_message())) {
		$add_button_clicked = false;
		
		if (isset($_POST["add_graph_y"])) {
			db_execute("replace into user_auth_perms (user_id,item_id,type) values (" . $_POST["id"] . "," . $_POST["perm_graphs"] . ",1)");
			$add_button_clicked = true;
		}elseif (isset($_POST["add_tree_y"])) {
			db_execute("replace into user_auth_perms (user_id,item_id,type) values (" . $_POST["id"] . "," . $_POST["perm_trees"] . ",2)");
			$add_button_clicked = true;
		}elseif (isset($_POST["add_host_y"])) {
			db_execute("replace into user_auth_perms (user_id,item_id,type) values (" . $_POST["id"] . "," . $_POST["perm_hosts"] . ",3)");
			$add_button_clicked = true;
		}elseif (isset($_POST["add_graph_template_y"])) {
			db_execute("replace into user_auth_perms (user_id,item_id,type) values (" . $_POST["id"] . "," . $_POST["perm_graph_templates"] . ",4)");
			$add_button_clicked = true;
		}
		
		if ($add_button_clicked == true) {
			header("Location: user_admin.php?action=graph_perms_edit&id=" . $_POST["id"]);
			exit;
		}
	}
	
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
		
		form_input_validate($_POST["password"], "password", "" . preg_quote($_POST["password_confirm"]) . "", true, 4);
		form_input_validate($_POST["password_confirm"], "password_confirm", "" . preg_quote($_POST["password"]) . "", true, 4);
		
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
		$save["policy_graphs"] = form_input_validate((isset($_POST["policy_graphs"]) ? $_POST["policy_graphs"] : $_POST["_policy_graphs"]), "policy_graphs", "", true, 3);
		$save["policy_trees"] = form_input_validate((isset($_POST["policy_trees"]) ? $_POST["policy_trees"] : $_POST["_policy_trees"]), "policy_trees", "", true, 3);
		$save["policy_hosts"] = form_input_validate((isset($_POST["policy_hosts"]) ? $_POST["policy_hosts"] : $_POST["_policy_hosts"]), "policy_hosts", "", true, 3);
		$save["policy_graph_templates"] = form_input_validate((isset($_POST["policy_graph_templates"]) ? $_POST["policy_graph_templates"] : $_POST["_policy_graph_templates"]), "policy_graph_templates", "", true, 3);
		
		if (!is_error_message()) {
			$user_id = sql_save($save, "user_auth");
			
			if ($user_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
			
			if (isset($_POST["save_component_realm_perms"])) {
				db_execute("delete from user_auth_realm where user_id=$user_id");
				
				while (list($var, $val) = each($_POST)) {
					if (eregi("^[section]", $var)) {
						if (substr($var, 0, 7) == "section") {
						    db_execute("replace into user_auth_realm (user_id,realm_id) values($user_id," . substr($var, 7) . ")");
						}
					}
				}
			}elseif (isset($_POST["save_component_graph_settings"])) {
				while (list($tab_short_name, $tab_fields) = each($settings_graphs)) {
					while (list($field_name, $field_array) = each($tab_fields)) {
						db_execute("replace into settings_graphs (user_id,name,value) values (" . (!empty($user_id) ? $user_id : $_POST["id"]) . ",'$field_name', '" . (isset($_POST[$field_name]) ? $_POST[$field_name] : "") . "')");
					}
				}
				
				/* reset local settings cache so the user sees the new settings */
				kill_session_var("sess_graph_config_array");
			}elseif (isset($_POST["save_component_graph_perms"])) {
				db_execute("update user_auth set 
					policy_graphs='" . $_POST["policy_graphs"] . "',
					policy_trees='" . $_POST["policy_trees"] . "',
					policy_hosts='" . $_POST["policy_hosts"] . "',
					policy_graph_templates='" . $_POST["policy_graph_templates"] . "'
					where id=" . $_POST["id"]);
			}
		}
	}
	
	/* redirect to the appropriate page */
	if (is_error_message()) {
		header("Location: user_admin.php?action=user_edit&id=" . (empty($user_id) ? $_POST["id"] : $user_id));
	}else{
		header("Location: user_admin.php");
	}
}

/* --------------------------
    Graph Permissions
   -------------------------- */

function perm_remove() {
	if ($_GET["type"] == "graph") {
		db_execute("delete from user_auth_perms where type=1 and user_id=" . $_GET["user_id"] . " and item_id=" . $_GET["id"]);
	}elseif ($_GET["type"] == "tree") {
		db_execute("delete from user_auth_perms where type=2 and user_id=" . $_GET["user_id"] . " and item_id=" . $_GET["id"]);
	}elseif ($_GET["type"] == "host") {
		db_execute("delete from user_auth_perms where type=3 and user_id=" . $_GET["user_id"] . " and item_id=" . $_GET["id"]);
	}elseif ($_GET["type"] == "graph_template") {
		db_execute("delete from user_auth_perms where type=4 and user_id=" . $_GET["user_id"] . " and item_id=" . $_GET["id"]);
	}
	
	header("Location: user_admin.php?action=graph_perms_edit&id=" . $_GET["user_id"]);
}

function graph_perms_edit() {
	global $colors;
	
	$graph_policy_array = array(
		1 => "Allow",
		2 => "Deny");
	
	if (!empty($_GET["id"])) {
		$policy = db_fetch_row("select policy_graphs,policy_trees,policy_hosts,policy_graph_templates from user_auth where id=" . $_GET["id"]);
		
		$header_label = "[edit: " . db_fetch_cell("select username from user_auth where id=" . $_GET["id"]) . "]";
	}
	
	?>
	<table width='98%' align='center' cellpadding="5">
		<tr>
			<td>
				<span style='font-size: 12px; font-weight: bold;'>Graph policies will be evaluated in the order shown until a match is found.</span>
			</td>
		</tr>
	</table>
	<?php
	
	/* box: graph permissions */
	start_box("<strong>Graph Permissions (By Graph)</strong>", "98%", $colors["header"], "3", "center", "");
	
	$graphs = db_fetch_assoc("select 
		graph_templates_graph.local_graph_id,
		graph_templates_graph.title_cache
		from graph_templates_graph
		left join user_auth_perms on (graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1)
		where graph_templates_graph.local_graph_id > 0
		and user_auth_perms.user_id=" . (empty($_GET["id"]) ? "0" : $_GET["id"]) . "
		order by graph_templates_graph.title_cache");
	
	?>
	<form method="post" action="user_admin.php">
	
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td width="50%">
			<font class="textEditTitle">Default Policy</font><br>
			The default allow/deny graph policy for this user.
		</td>
		<td align="right">
			<?php form_dropdown("policy_graphs",$graph_policy_array,"","",$policy["policy_graphs"],"",""); ?>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<table width="100%" cellpadding="1">
				<?php
				$i = 0;
				if (sizeof($graphs) > 0) {
				foreach ($graphs as $item) {
					$i++;
					print "	<tr>
							<td><span style='font-weight: bold; color: " . (($policy["policy_graphs"] == "1") ? "red" : "blue") . ";'>$i)</span> " . $item["title_cache"] . "</td>
							<td width='1%' align='right'><a href='user_admin.php?action=perm_remove&type=graph&id=" . $item["local_graph_id"] . "&user_id=" . $_GET["id"] . "'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;</td>
						</tr>\n";
				}
				}else{ print "<tr><td><em>No Graphs</em></td></tr>";
				}
				?>
			</table>
		</td>
	</tr>
	<?php
	
	end_box(false);
	
	?>
	<table align='center' width='98%'>
		<tr>
			<td nowrap>Add Graph:&nbsp;
				<?php form_dropdown("perm_graphs",db_fetch_assoc("select local_graph_id,title_cache from graph_templates_graph where local_graph_id>0 order by title_cache"),"title_cache","local_graph_id","","","");?>
			</td>
			<td align="right">
				&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add_graph" align="absmiddle">
			</td>
		</tr>
	</table>
	<br>
	<?php
	
	/* box: host permissions */
	start_box("<strong>Graph Permissions (By Host)</strong>", "98%", $colors["header"], "3", "center", "");
	
	$hosts = db_fetch_assoc("select 
		host.id,
		CONCAT_WS('',host.description,' (',host.hostname,')') as name
		from host
		left join user_auth_perms on (host.id=user_auth_perms.item_id and user_auth_perms.type=3)
		where user_auth_perms.user_id=" . (empty($_GET["id"]) ? "0" : $_GET["id"]) . "
		order by host.description,host.hostname");
	
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td width="50%">
			<font class="textEditTitle">Default Policy</font><br>
			The default allow/deny graph policy for this user.
		</td>
		<td align="right">
			<?php form_dropdown("policy_hosts",$graph_policy_array,"","",$policy["policy_hosts"],"",""); ?>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<table width="100%" cellpadding="1">
				<?php
				$i = 0;
				if (sizeof($hosts) > 0) {
				foreach ($hosts as $item) {
					$i++;
					print "	<tr>
							<td><span style='font-weight: bold; color: " . (($policy["policy_hosts"] == "1") ? "red" : "blue") . ";'>$i)</span> " . $item["name"] . "</td>
							<td width='1%' align='right'><a href='user_admin.php?action=perm_remove&type=host&id=" . $item["id"] . "&user_id=" . $_GET["id"] . "'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;</td>
						</tr>\n";
				}
				}else{ print "<tr><td><em>No Hosts</em></td></tr>";
				}
				?>
			</table>
		</td>
	</tr>
	
    	<?php
	
	end_box(false);
	
	?>
	<table align='center' width='98%'>
		<tr>
			<td nowrap>Add Host:&nbsp;
				<?php form_dropdown("perm_hosts",db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname"),"name","id","","","");?>
			</td>
			<td align="right">
				&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add_host" align="absmiddle">
			</td>
		</tr>
	</table>
	<br>
	<?php
	
	/* box: graph template permissions */
	start_box("<strong>Graph Permissions (By Graph Template)</strong>", "98%", $colors["header"], "3", "center", "");
	
	$graph_templates = db_fetch_assoc("select 
		graph_templates.id,
		graph_templates.name
		from graph_templates
		left join user_auth_perms on (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4)
		where user_auth_perms.user_id=" . (empty($_GET["id"]) ? "0" : $_GET["id"]) . "
		order by graph_templates.name");
	
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td width="50%">
			<font class="textEditTitle">Default Policy</font><br>
			The default allow/deny graph policy for this user.
		</td>
		<td align="right">
			<?php form_dropdown("policy_graph_templates",$graph_policy_array,"","",$policy["policy_graph_templates"],"",""); ?>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<table width="100%" cellpadding="1">
				<?php
				$i = 0;
				if (sizeof($graph_templates) > 0) {
				foreach ($graph_templates as $item) {
					$i++;
					print "	<tr>
							<td><span style='font-weight: bold; color: " . (($policy["policy_graph_templates"] == "1") ? "red" : "blue") . ";'>$i)</span> " . $item["name"] . "</td>
							<td width='1%' align='right'><a href='user_admin.php?action=perm_remove&type=graph_template&id=" . $item["id"] . "&user_id=" . $_GET["id"] . "'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;</td>
						</tr>\n";
				}
				}else{ print "<tr><td><em>No Graph Templates</em></td></tr>";
				}
				?>
			</table>
		</td>
	</tr>
	
    	<?php
	
	end_box(false);
	
	?>
	<table align='center' width='98%'>
		<tr>
			<td nowrap>Add Graph Template:&nbsp;
				<?php form_dropdown("perm_graph_templates",db_fetch_assoc("select id,name from graph_templates order by name"),"name","id","","","");?>
			</td>
			<td align="right">
				&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add_graph_template" align="absmiddle">
			</td>
		</tr>
	</table>
	<br>
	<?php
	
	/* box: tree permissions */
	start_box("<strong>Tree Permissions</strong>", "98%", $colors["header"], "3", "center", "");
	
	$trees = db_fetch_assoc("select 
		graph_tree.id,
		graph_tree.name
		from graph_tree
		left join user_auth_perms on (graph_tree.id=user_auth_perms.item_id and user_auth_perms.type=2)
		where user_auth_perms.user_id=" . (empty($_GET["id"]) ? "0" : $_GET["id"]) . "
		order by graph_tree.name");
	
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td width="50%">
			<font class="textEditTitle">Default Policy</font><br>
			The default allow/deny graph policy for this user.
		</td>
		<td align="right">
			<?php form_dropdown("policy_trees",$graph_policy_array,"","",$policy["policy_trees"],"",""); ?>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<table width="100%" cellpadding="1">
				<?php
				$i = 0;
				if (sizeof($trees) > 0) {
				foreach ($trees as $item) {
					$i++;
					print "	<tr>
							<td><span style='font-weight: bold; color: " . (($policy["policy_trees"] == "1") ? "red" : "blue") . ";'>$i)</span> " . $item["name"] . "</td>
							<td width='1%' align='right'><a href='user_admin.php?action=perm_remove&type=tree&id=" . $item["id"] . "&user_id=" . $_GET["id"] . "'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;</td>
						</tr>\n";
				}
				}else{ print "<tr><td><em>No Trees</em></td></tr>";
				}
				?>
			</table>
		</td>
	</tr>
	
    	<?php
	
	end_box(false);
	
	?>
	<table align='center' width='98%'>
		<tr>
			<td nowrap>Add Tree:&nbsp;
				<?php form_dropdown("perm_trees",db_fetch_assoc("select id,name from graph_tree order by name"),"name","id","","","");?>
			</td>
			<td align="right">
				&nbsp;<input type="image" src="images/button_add.gif" alt="Add" name="add_tree" align="absmiddle">
			</td>
		</tr>
	</table>
	<br>
	
	<?php
	form_hidden_box("save_component_graph_perms","1","");
}

function user_realms_edit() {
	global $colors, $user_auth_realms;
	
	?>
	<table width='98%' align='center' cellpadding="5">
		<tr>
			<td>
				<span style='font-size: 12px; font-weight: bold;'>Realm permissions control which sections of Cacti this user will have access to.</span>
			</td>
		</tr>
	</table>
	<?php
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	print "	<tr bgcolor='#" . $colors["header"] . "'>
			<td class='textHeaderDark'><strong>Realm Permissions</strong></td>
			<td width='1%' align='center' bgcolor='#819bc0' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='SelectAll(\"section\",this.checked)'></td>\n
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
						while (list($realm_id, $realm_name) = each($user_auth_realms)) {
							if (sizeof(db_fetch_assoc("select realm_id from user_auth_realm where user_id=" . (empty($_GET["id"]) ? "0" : $_GET["id"]) . " and realm_id=$realm_id")) > 0) {
								$old_value = "on";
							}else{
								$old_value = "";
							}
							
							$column1 = floor((sizeof($user_auth_realms) / 2) + (sizeof($user_auth_realms) % 2));
							
							if ($i == $column1) {
								print "</td><td valign='top' width='50%'>";
							}
							
							form_checkbox("section" . $realm_id, $old_value, $realm_name, "", (!empty($_GET["id"]) ? 1 : 0)); print "<br>";
							
							$i++;
						}
						?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	
	<?php
	end_box();
	
	form_hidden_box("save_component_realm_perms","1","");
}

function graph_settings_edit() {
	global $settings_graphs, $tabs_graphs, $colors, $graph_views, $graph_tree_views;
	
	?>
	<table width='98%' align='center' cellpadding="5">
		<tr>
			<td>
				<span style='font-size: 12px; font-weight: bold;'>Graph settings control how graphs are displayed for this user.</span>
			</td>
		</tr>
	</table>
	<?php
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	while (list($tab_short_name, $tab_fields) = each($settings_graphs)) {
		?>
		<tr>
			<td colspan="2" bgcolor="#<?php print $colors["header"];?>">
				<span class="textHeaderDark"><strong>Graph Settings</strong> [<?php print $tabs_graphs[$tab_short_name];?>]</span>
			</td>
		</tr>
		<?php
		
		$form_array = array();
		
		while (list($field_name, $field_array) = each($tab_fields)) {
			$form_array += array($field_name => $tab_fields[$field_name]);
			
			$form_array[$field_name]["value"] =  db_fetch_cell("select value from settings_graphs where name='$field_name' and user_id=" . (isset($_GET["id"]) ? $_GET["id"] : "0"));
		}
		
		draw_edit_form(
			array(
				"config" => array(
					"no_form_tag" => true
					),
				"fields" => $form_array
				)
			);
	}
	
	end_box();
	
	form_hidden_box("save_component_graph_settings","1","");
}

/* --------------------------
    User Administration
   -------------------------- */

function user_remove() {
	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the user <strong>'" . db_fetch_cell("select username from user_auth where id=" . $_GET["id"]) . "'</strong>?", "user_admin.php", "user_admin.php?action=user_remove&id=" . $_GET["id"]);
		include("./include/bottom_footer.php");
		exit;
	}
	
	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("delete from user_auth where id=" . $_GET["id"]);
		db_execute("delete from user_auth_realm where user_id=" . $_GET["id"]);
		db_execute("delete from user_auth_perms where user_id=" . $_GET["id"]);
		db_execute("delete from settings_graphs where user_id=" . $_GET["id"]);
	}	
}

function user_edit() {
	global $colors, $fields_user_user_edit_host;
	
	if (!empty($_GET["id"])) {
		$user = db_fetch_row("select * from user_auth where id=" . $_GET["id"]);
		$header_label = "[edit: " . $user["username"] . "]";
	}else{
		$header_label = "[new]";
	}
	
	start_box("<strong>User Management</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	
	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($fields_user_user_edit_host, (isset($user) ? $user : array()))
		));
	
	end_box();
	
	if (!empty($_GET["id"])) {
		/* draw user admin nav tabs */
		?>
		<table class='tabs' width='98%' cellspacing='0' cellpadding='3' align='center'>
			<tr>
				<td width='1'></td>
				<td <?php print ((($_GET["action"] == "user_realms_edit") || ($_GET["action"] == "user_edit")) ? "bgcolor='silver'" : "bgcolor='#DFDFDF'");?> nowrap='nowrap' width='150' align='center' class='tab'>
					<span class='textHeader'><a href='user_admin.php?action=user_realms_edit&id=<?php print $_GET["id"];?>'>Realm Permissions</a></span>
				</td>
				<td width='1'></td>
				<td <?php print (($_GET["action"] == "graph_perms_edit") ? "bgcolor='silver'" : "bgcolor='#DFDFDF'");?> nowrap='nowrap' width='150' align='center' class='tab'>
					<span class='textHeader'><a href='user_admin.php?action=graph_perms_edit&id=<?php print $_GET["id"];?>'>Graph Permissions</a></span>
				</td>
				<td width='1'></td>
				<td <?php print (($_GET["action"] == "graph_settings_edit") ? "bgcolor='silver'" : "bgcolor='#DFDFDF'");?> nowrap='nowrap' width='130' align='center' class='tab'>
					<span class='textHeader'><a href='user_admin.php?action=graph_settings_edit&id=<?php print $_GET["id"];?>'>Graph Settings</a></span>
				</td>
				<td></td>
			</tr>
		</table>
		<?php
	}
	
	if ($_GET["action"] == "graph_settings_edit") {
		graph_settings_edit();
	}elseif ($_GET["action"] == "user_realms_edit") {
		user_realms_edit();
	}elseif ($_GET["action"] == "graph_perms_edit") {
		graph_perms_edit();
	}else{
		user_realms_edit();
	}
	
	form_save_button("user_admin.php");
}

function user() {
	global $colors, $auth_realms;

	start_box("<strong>User Management</strong>", "98%", $colors["header"], "3", "center", "user_admin.php?action=user_edit");
	
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("User Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("Full Name",$colors["header_text"],1);
                DrawMatrixHeaderItem("Realm",$colors["header_text"],1);
		DrawMatrixHeaderItem("Default Graph Policy",$colors["header_text"],1);
                DrawMatrixHeaderItem("Last Login",$colors["header_text"],2);
	print "</tr>";
	
	$user_list = db_fetch_assoc("select id, user_auth.username, full_name, realm, policy_graphs, DATE_FORMAT(max(time),'%M %e %Y %H:%i:%s') as time from user_auth left join user_log on user_auth.id = user_log.user_id group by id");
	
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
				<?php print $auth_realms[$user["realm"]];?>
			</td>
			<td>
				<?php if ($user["policy_graphs"] == "1") { print "ALLOW"; }else{ print "DENY"; }?>
			</td>
			<td>
				<?php print $user["time"];?>
			</td>
			<td align="right">
				<a href="user_admin.php?action=user_remove&id=<?php print $user["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>
			</td>
		</tr>
	<?php
	$i++;
	}
	}
	end_box();	
}
?>
