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
 
$section = "View Graphs";  include ('include/auth.php');

header("Cache-control: no-cache");
include_once ("include/functions.php");
include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

/* get current user */
$current_user_id = GetCurrentUserID($HTTP_COOKIE_VARS["cactilogin"], read_config_option("guest_user"));

switch ($action) {
 case 'save':
    $sql_id = db_execute("replace into settings_graphs (id,userid,rraid,treeid, height,width,timespan,columnnumber,viewtype,listviewtype,pagerefresh) 
      values ($id,$current_user_id,$form[RRAID],$form[TreeID],$form[Height],$form[Width],$form[TimeSpan],$form[ColumnNumber],$form[ViewType],$form[ListViewType],$form[PageRefresh])");
    
    header ("Location: $referer");
    break;
 default:
    include_once ('include/top_graph_header.php');
    
    /* find out if the current user has right s here */
    $gs = db_fetch_cell("select graph_settings from user where id=$current_user_id");
    
    if (! $gs) {
	print "<strong><font size=\"+1\" color=\"FF0000\">YOU DO NOT HAVE RIGHTS TO CHANGE GRAPH SETTINGS</font></strong>"; exit;
    }
    
    $settings = db_fetch_assoc("select * from settings_graphs where userid=$current_user_id");
    
    if (getenv("HTTP_REFERER") == "") {
	$referer = $HTTP_REFERER;
    }else{
	$referer = getenv("HTTP_REFERER");
    }
    
    DrawFormHeader("Graph Preview Settings","",false);
    
    form_item_label("Height","The height of graphs created in preview mode.");
    form_text_box("Height",$settings[Height],"100","");
    
    form_item_label("Width","The width of graphs created in preview mode.");
    form_text_box("Width",$settings[Width],"300","");
    
    form_item_label("Timespan","The amount of time to represent on a graph created in preview mode (0 uses auto).");
    form_text_box("TimeSpan",$settings[TimeSpan],"60000","");
    
    form_item_label("Default RRA","The default RRA to use when displaying graphs in preview mode.");
    form_dropdown("RRAID",db_fetch_assoc("select * from rrd_rra order by name"),"Name","ID",$settings[RRAID],"","");
    
    form_item_label("Columns","The number of columns to display graphs in using preview mode.");
    form_text_box("ColumnNumber",$settings[ColumnNumber],"2","");
    
    form_item_label("Page Refresh","The number of seconds between automatic page refreshes.");
    form_text_box("PageRefresh",$settings[PageRefresh],"300","");
    
    DrawPlainFormHeader("List Settings");
    
    form_item_label("View Settings","Options that govern how the graphs are displayed.");
    form_radio_button("ListViewType", $settings[ListViewType], "1", "Show a 4 column list of each graph and its RRA.", "1");
    form_radio_button("ListViewType", $settings[ListViewType], "2", "Show a 1 column list of each graph.", "1");
    
    DrawPlainFormHeader("Hierarchical Settings");
    
    form_item_label("Default Graph Hierarchy ","The default graph hierarchy to use when displaying graphs in tree mode.");
    form_dropdown("TreeID",db_fetch_assoc("select * from viewing_trees order by Title"),"Title","ID",$settings[TreeID],"","");
    
    form_item_label("View Settings","Options that govern how the graphs are displayed.");
    form_radio_button("ViewType", $settings[ViewType], "1", "Show a preview of the graph.", "1");
    form_radio_button("ViewType", $settings[ViewType], "2", "Show a text-based listing of the graph.", "1");
    
    form_save_button();
    form_hidden_id("ID",$settings[ID]);
    form_hidden_id("referer", $referer);
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    break;
} ?>
