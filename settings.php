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
include_once ("include/config_settings.php");
include_once ('include/form.php');

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
 case 'save':
	if (sizeof($settings) > 0) {
	foreach (array_keys($settings) as $setting) {
		if ($settings[$setting]["tab"] == $_POST["tab"]) {
			if ($settings[$setting]["method"] == "group") {
				if (sizeof($settings[$setting]["items"]) > 0) {
				foreach (array_keys($settings[$setting]["items"]) as $item) {
					db_execute("replace into settings (name,value) values ('$item', '" . (isset($_POST[$item]) ? $_POST[$item] : "") . "')");
				}
				}
			}else{
				db_execute("replace into settings (name,value) values ('$setting', '" . (isset($_POST[$setting]) ? $_POST[$setting] : "") . "')");
			}
		}
	}
	}
	
	raise_message(1);
	
	/* reset local settings cache so the user sees the new settings */
	session_unregister("sess_config_array");

	header ("Location: settings.php?tab=" . $_POST["tab"]);
	break;
 default:
    	include_once ('include/top_header.php');
	
	/* set the default settings category */
	if (!isset($_GET["tab"])) {
		/* there is no selected tab; select the first one */
		$current_tab = array_keys($tabs);
		$current_tab = $current_tab[0];
	}else{
		$current_tab = $_GET["tab"];
	}
	
	/* draw the categories tabs on the top of the page */
	print "	<table class='tabs' width='98%' cellspacing='0' cellpadding='3' align='center'>
			<tr>\n";
			
			if (sizeof($tabs) > 0) {
			foreach (array_keys($tabs) as $tab_short_name) {
				print "	<td " . (($tab_short_name == $current_tab) ? "bgcolor='silver'" : "bgcolor='#DFDFDF'") . " nowrap='nowrap' width='" . (strlen($tabs[$tab_short_name]) * 9) . "' align='center' class='tab'>
						<span class='textHeader'><a href='settings.php?tab=$tab_short_name'>$tabs[$tab_short_name]</a></span>
					</td>\n
					<td width='1'></td>\n";
			}
			}
			
			print "
			<td></td>\n
			</tr>
		</table>\n";
	
	start_box("<strong>cacti Settings (" . $tabs[$current_tab] . ")</strong>", "98%", $colors["header"], "3", "center", "");
	
    	print "<form method='post' action='settings.php?action=save'>\n";
	
	$i = 0;
	if (sizeof($settings) > 0) {
	foreach (array_keys($settings) as $setting) {
	    	/* make sure to skip group members here; only parents are allowed */
		if (($settings[$setting]["method"] != "internal") && ($settings[$setting]["tab"] == $current_tab)) {
			++$i;
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			
			/* draw the acual header and textbox on the form */
			form_item_label($settings[$setting]["friendly_name"],$settings[$setting]["description"]);
			
			$current_value = db_fetch_cell("select value from settings where name='$setting'");
			
			/* choose what kind of item this is */
			switch ($settings[$setting]["method"]) {
				case 'textbox':
					form_text_box($setting,$current_value,"","");
					print "</tr>\n";
					break;
				case 'checkbox':
					form_checkbox($setting,$current_value,$settings[$setting]["friendly_name"],"");
					print "</tr>\n";
					break;
				case 'dropdown':
                                        /* loop through the resultset and draw each item in the group */
                                        if (sizeof($settings[$setting]["items"]) > 0) {
                                        	form_dropdown($setting,$settings[$setting]["items"],"","",$current_value,"","net-snmp");
					}

					break;
				case 'group':
					print "<td>\n";
					
		    			/* loop through the resultset and draw each item in the group */
					if (sizeof($settings[$setting]["items"]) > 0) {
					foreach (array_keys($settings[$setting]["items"]) as $item) {
						$current_value = db_fetch_cell("select value from settings where name='$item'");
						
			    			switch ($settings[$setting]["items"][$item]["method"]) {
							case 'textbox':
								//DrawtrippedFormItemTextBox($item,$current_value,"","");
								break;
							case 'checkbox':
								form_base_checkbox($item,$current_value,$settings[$setting]["items"][$item]["description"],"",0,true);
								break;
						}
			    
					}
					}
					
					print "</td></tr>\n";
					break;
			}
		}
	
	}
	}
	
	end_box();
	
	form_hidden_id("tab",$current_tab);
	
	form_save_button("settings.php?tab=$current_tab");
	
	include_once ("include/bottom_footer.php");
	
	break;
} ?>	
			
