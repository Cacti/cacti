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

/* this file stores additional information about each configurable setting
   and how that setting is to be rendered on a configuration page. */

/* tab information */
$tabs = array(
	"general" => "General",
	"path" => "Path",
	"visual" => "Visual",
	"authentication" => "Authentication");

$tabs_graphs = array(
	"general" => "General Graph Settings",
	"tree" => "Graph Tree Settings");

/* setting information */
$settings = array(
	"path_webcacti" => array(
		"friendly_name" => "cacti Web Root",
		"description" => "The path under your webroot where cacti lies, would be '/cacti'  in most cases if you are accessing cacti by: http://yourhost.com/cacti/.",
		"method" => "textbox",
		"tab" => "path"),
	"path_webroot" => array("friendly_name" => "Web Server Document Root",
		"description" => "Your webserver document root, is '/var/www/html' or '/home/httpd/html' in most cases.",
		"method" => "textbox",
		"tab" => "path"),
	"path_snmpwalk" => array(
		"friendly_name" => "snmpwalk Binary Path",
		"description" => "The path to your snmpwalk binary.",
		"method" => "textbox",
		"tab" => "path"),
	"path_snmpget" => array(
		"friendly_name" => "snmpget Binary Path",
		"description" => "The path to your snmpget binary.",
		"method" => "textbox",
		"tab" => "path"),
	"path_rrdtool" => array(
		"friendly_name" => "rrdtool Binary Path",
		"description" => "Path to the rrdtool binary.",
		"method" => "textbox",
		"tab" => "path"),
	"path_php_binary" => array(
		"friendly_name" => "PHP Binary Path",
		"description" => "The path to your PHP binary file (may require a php recompile to get this file).",
		"method" => "textbox",
		"tab" => "path"),
	"path_html_export" => array(
		"friendly_name" => "HTML Export Path",
		"description" => "If you want cacti to write static png's and html files a directory when data is gathered, specify the location here. This feature is similar to MRTG, graphs do not have to be generated on the fly this way. Leave this field blank to disable this feature.",
		"method" => "textbox",
		"tab" => "path"),
	"log" => array(
		"friendly_name" => "Log File",
		"description" => "What cacti should put in its log.",
		"method" => "group",
		"tab" => "general",
		"items" => array(
			"log_graph" => array(
				"friendly_name" => "",
				"description" => "Graph",
				"method" => "checkbox",
				"tab" => "general"),
			"log_create" => array(
				"friendly_name" => "",
				"description" => "Create",
				"method" => "checkbox",
				"tab" => "general"),
			"log_update" => array(
				"friendly_name" => "",
				"description" => "Update",
				"method" => "checkbox",
				"tab" => "general"),
			"log_snmp" => array(
				"friendly_name" => "",
				"description" => "SNMP",
				"method" => "checkbox",
				"tab" => "general")
				)
			),
	"guest_user" => array(
		"friendly_name" => "Guest User",
		"description" => "The name of the guest user for viewing graphs; is \"guest\" by default.",
		"method" => "textbox",
		"tab" => "general"),
	"path_html_export_skip" => array(
		"friendly_name" => "Export Every x Times",
		"description" => "If you don't want cacti to export static images every 5 minutes, put another number here. For instance, 3 would equal every 15 minutes.",
		"method" => "textbox",
		"tab" => "path"),
	"remove_verification" => array(
		"friendly_name" => "Remove Verification",
		"description" => "Confirm Before the User Removes an Item.",
		"method" => "checkbox",
		"tab" => "general"),
	"use_polling_zones" => array(
		"friendly_name" => "Use Polling Zones",
		"description" => "If you want to do distributed polling you can set up 'polling zones' which correspond to each of your polling machines.  Polling Hosts are then associated with a particular polling zone.",
		"method" => "checkbox",
		"tab" => "general"),
	"full_view" => array(
		"friendly_name" => "Use Full View",
		"description" => "Select the areas in cacti where you would rather see everything on one edit screen rather than having to choose each area from a drop down box.",
		"method" => "group",
		"tab" => "visual",
		"items" => array(
			"full_view_graph_template" => array(
				"friendly_name" => "",
				"description" => "Graph Templates",
				"method" => "checkbox",
				"tab" => "general"),
			"full_view_graph" => array(
				"friendly_name" => "",
				"description" => "Graph Management",
				"method" => "checkbox",
				"tab" => "general"),
			"full_view_data_source" => array(
				"friendly_name" => "",
				"description" => "Data Sources",
				"method" => "checkbox",
				"tab" => "general"),		
			"full_view_user_admin" => array("friendly_name" => "",
				"description" => "User Management",
				"method" => "checkbox",
				"tab" => "general"),
				)
			),
	"global_auth" => array(
		"friendly_name" => "Use cacti's Builtin Authentication",
		"description" => "By default cacti handles user authentication, which allows you to create users and give them rights to different areas within cacti. You can optionally turn this off if you are other other means of authentication.",
		"method" => "checkbox",
		"tab" => "authentication"),
	"ldap_enabled" => array(
		"friendly_name" => "Use LDAP Authentication",
		"description" => "This will alow users to use their LDAP credentials with cacti.",
		"method" => "checkbox",
		"tab" => "authentication"),
	"ldap_server" => array(
		"friendly_name" => "LDAP Server",
		"description" => "The dns hostname or ip address of the server you wish to tie authentication from.",
		"method" => "textbox",
		"tab" => "authentication"),
	"ldap_dn" => array(
		"friendly_name" => "LDAP DN",
		"description" => "This is the Distinguished Name syntax, such as NTDomain\\\\&lt;username&gt;.",
		"method" => "textbox",
		"tab" => "authentication"),
	"ldap_template" => array(
		"friendly_name" => "LDAP Cacti Template User",
		"description" => "This is the user that cacti will use as a template for new LDAP users.",
		"method" => "textbox",
		"tab" => "authentication"));

$settings_graphs = array(
	"default_rra_id" => array(
		"friendly_name" => "Default RRA",
		"description" => "The default RRA to use when displaying graphs in preview mode.",
		"method" => "textbox",
		"default" => "1",
		"tab" => "general"),
	"default_tree_id" => array(
		"friendly_name" => "Default Graph Hierarchy",
		"description" => "The default graph hierarchy to use when displaying graphs in tree mode.",
		"method" => "textbox",
		"default" => "",
		"tab" => "tree"),
	"default_height" => array(
		"friendly_name" => "Height",
		"description" => "The height of graphs created in preview mode.",
		"method" => "textbox",
		"default" => "100",
		"tab" => "general"),
	"default_width" => array(
		"friendly_name" => "Width",
		"description" => "The width of graphs created in preview mode.",
		"method" => "textbox",
		"default" => "300",
		"tab" => "general"),
	"default_view_mode" => array(
		"friendly_name" => "Default View Mode",
		"description" => "What mode you wanted displayed when you visit 'graph_view.php'",
		"method" => "textbox",
		"default" => "1",
		"tab" => "general"),
	"timespan" => array(
		"friendly_name" => "Timespan",
		"description" => "The amount of time to represent on a graph created in preview mode (0 uses auto).",
		"method" => "textbox",
		"default" => "60000",
		"tab" => "general"),
	"num_columns" => array(
		"friendly_name" => "Columns",
		"description" => "The number of columns to display graphs in using preview mode.",
		"method" => "textbox",
		"default" => "2",
		"tab" => "general"),
	"page_refresh" => array(
		"friendly_name" => "Page Refresh",
		"description" => "The number of seconds between automatic page refreshes.",
		"method" => "textbox",
		"default" => "300",
		"tab" => "general"));
?>
