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

$guest_account = true;
include("./include/auth.php");
include("./include/config_settings.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	default:
		include_once("./include/top_graph_header.php");
		
		settings();
		
		include_once("./include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $settings_graphs;
	
	while (list($tab_short_name, $tab_fields) = each($settings_graphs)) {
		while (list($field_name, $field_array) = each($tab_fields)) {
			db_execute("replace into settings_graphs (user_id,name,value) values (" . $_SESSION["sess_user_id"] . ",'$field_name', '" . (isset($_POST[$field_name]) ? $_POST[$field_name] : "") . "')");
		}
	}
	
	/* reset local settings cache so the user sees the new settings */
	kill_session_var("sess_graph_config_array");
	
	header("Location: " . $_POST["referer"]);
}

/* --------------------------
    Graph Settings Functions
   -------------------------- */

function settings() {
	global $colors, $tabs_graphs, $settings_graphs, $current_user, $graph_views, $current_user, $graph_tree_views;
	
	/* you cannot have per-user graph settings if cacti's user management is not turned on */
	if (read_config_option("global_auth") == "") {
		raise_message(6);
		display_output_messages();
		return;
	}
	
	/* Find out whether this user has right here */
	if($current_user["graph_settings"] == "") {
		print "<strong><font size='+1' color='#FF0000'>YOU DO NOT HAVE RIGHTS TO CHANGE GRAPH SETTINGS</font></strong>";
		include_once("./include/bottom_footer.php");
		exit;
	} 
	
	if (read_config_option("global_auth") == "on") {
		if ($current_user["policy_graphs"] == "1") {
			$sql_where = "where user_auth_tree.user_id is null";
		}elseif ($current_user["policy_graphs"] == "2") {
			$sql_where = "where user_auth_tree.user_id is not null";
		}
		
		$settings_graphs["tree"]["default_tree_id"]["sql"] = get_graph_tree_array(true);
	}
	
	print "<br><form method='post' action='graph_settings.php'>\n";
	
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
			
			$form_array[$field_name]["value"] =  db_fetch_cell("select value from settings_graphs where name='$field_name' and user_id=" . $_SESSION["sess_user_id"]);
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
	
	form_hidden_box("referer",(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : ""),"");
	form_hidden_box("save_component_graph_config","1","");
	form_save_button("graph_settings.php", "save");
}

?>
