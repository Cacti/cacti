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

include ("include/auth.php");
include ("include/config_settings.php");
include ("include/config_arrays.php");
include_once ("include/form.php");
include_once ("include/functions.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();
		
		break;
	default:
		include_once ("include/top_graph_header.php");
		
		settings();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $settings_graphs;
	
	if (sizeof($settings_graphs) > 0) {
	foreach (array_keys($settings_graphs) as $setting) {
		db_execute("replace into settings_graphs (user_id,name,value) values (" . $_SESSION["sess_user_id"]. ",'$setting', '" . $_POST[$setting] . "')");
	}
	}
	
	/* reset local settings cache so the user sees the new settings */
	kill_session_var("sess_graph_config_array");
	
	header ("Location: " . $_POST["referer"]);
}

/* --------------------------
    Graph Settings Functions
   -------------------------- */

function settings() {
	global $colors, $tabs_graphs, $settings_graphs, $current_user, $graph_views;
	
	/* you cannot have per-user graph settings if cacti's user management is not turned on */
	if (read_config_option("global_auth") == "") {
		raise_message(6);
		display_output_messages();
		return;
	}
	
	print "<form method='post' action='graph_settings.php'>\n";
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	
	if (sizeof($tabs_graphs) > 0) {
	foreach (array_keys($tabs_graphs) as $tab_short_name) {
		?>
		<tr>
			<td colspan="2" bgcolor="#<?php print $colors["header"];?>">
				<span class="textHeaderDark"><strong><?php print $tabs_graphs[$tab_short_name];?></strong> [user: <?php print $current_user["username"];?>]</span>
			</td>
		</tr>
		<?php
		
		reset($settings_graphs);
		
		$i = 0;
		if (sizeof($settings_graphs) > 0) {
		foreach (array_keys($settings_graphs) as $setting) {
			/* make sure to skip group members here; only parents are allowed */
			if (($settings_graphs[$setting]["method"] != "internal") && ($settings_graphs[$setting]["tab"] == $tab_short_name)) {
				++$i;
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
				
				/* draw the acual header and textbox on the form */
				form_item_label($settings_graphs[$setting]["friendly_name"],$settings_graphs[$setting]["description"]);
				
				$current_value = db_fetch_cell("select value from settings_graphs where name='$setting' and user_id=" . $_SESSION["sess_user_id"]);
				
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
	
	form_hidden_box("referer",$_SERVER["HTTP_REFERER"],"");
	form_hidden_box("save_component_graph_config","1","");
	form_save_button("graph_settings.php");
}

?>
