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
        while (list($field_name, $field_array) = each($settings{$_POST["tab"]})) {
                if (($field_array["method"] == "header") || ($field_array["method"] == "spacer" )){
                        /* do nothing */
                }elseif ($field_array["method"] == "checkbox_group") {
                        while (list($sub_field_name, $sub_field_array) = each($field_array["items"])) {
                                db_execute("replace into settings (name,value) values ('$sub_field_name', '" . (isset($_POST[$sub_field_name]) ? $_POST[$sub_field_name] : "") . "')");
                        }
                }else{
                        db_execute("replace into settings (name,value) values ('$field_name', '" . (isset($_POST[$field_name]) ? $_POST[$field_name] : "") . "')");
                }
        }

        raise_message(1);

        /* reset local settings cache so the user sees the new settings */
        kill_session_var("sess_config_array");

        header("Location: settings.php?tab=" . $_POST["tab"]);
        break;
default:
        include("./include/top_header.php");

        /* set the default settings category */
        if (!isset($_GET["tab"])) {
                /* there is no selected tab; select the first one */
                $current_tab = array_keys($tabs);
                $current_tab = $current_tab[0];
        }else{
                $current_tab = $_GET["tab"];
        }

        /* draw the categories tabs on the top of the page */
        print "        <table class='tabs' width='98%' cellspacing='0' cellpadding='3' align='center'>
                        <tr>\n";

                        if (sizeof($tabs) > 0) {
                        foreach (array_keys($tabs) as $tab_short_name) {
                                print "        <td " . (($tab_short_name == $current_tab) ? "bgcolor='silver'" : "bgcolor='#DFDFDF'") . " nowrap='nowrap' width='" . (strlen($tabs[$tab_short_name]) * 9) . "' align='center' class='tab'>
                                                <span class='textHeader'><a href='settings.php?tab=$tab_short_name'>$tabs[$tab_short_name]</a></span>
                                        </td>\n
                                        <td width='1'></td>\n";
                        }
                        }

                        print "
                        <td></td>\n
                        </tr>
                </table>\n";

        start_box("<strong>Cacti Settings (" . $tabs[$current_tab] . ")</strong>", "98%", $colors["header"], "3", "center", "");


        $form_array = array();

        while (list($field_name, $field_array) = each($settings[$current_tab])) {
                $form_array += array($field_name => $field_array);

                if ($field_array["method"] == "checkbox_group") {
                        while (list($sub_field_name, $sub_field_array) = each($field_array["items"])) {
                                $current_value = db_fetch_cell("select value from settings where name='$sub_field_name'");

                                $form_array[$field_name]["items"][$sub_field_name]["value"] = $current_value;
                        }
                }else{
                        $current_value = db_fetch_cell("select value from settings where name='$field_name'");

                        /* if there is no value and there is a default value, use that instead */
                        if ((empty($current_value)) && (isset($field_array["default"]))) {
                                $current_value = $field_array["default"];
                        }

                        $form_array[$field_name]["value"] = $current_value;
                }

                $form_array[$field_name]["form_id"] = 1;
        }

        draw_edit_form(
                array(
                        "config" => array(
                                ),
                        "fields" => $form_array
                        )
                );

        end_box();

        form_hidden_id("tab",$current_tab);

        form_save_button("settings.php?tab=$current_tab", "save");

        include("./include/bottom_footer.php");

        break;
} ?>