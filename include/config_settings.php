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

include($config["include_path"] . "/config_arrays.php");

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
	"path" => array(
		"path_snmpwalk" => array(
			"friendly_name" => "snmpwalk Binary Path",
			"description" => "The path to your snmpwalk binary.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"path_snmpget" => array(
			"friendly_name" => "snmpget Binary Path",
			"description" => "The path to your snmpget binary.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"path_rrdtool" => array(
			"friendly_name" => "RRDTool Binary Path",
			"description" => "Path to the rrdtool binary.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"path_php_binary" => array(
			"friendly_name" => "PHP Binary Path",
			"description" => "The path to your PHP binary file (may require a php recompile to get this file).",
			"method" => "textbox",
			"max_length" => "255"
			),
		"path_html_export" => array(
			"friendly_name" => "HTML Export Path",
			"description" => "If you want cacti to write static png's and html files a directory when data is gathered, specify the location here. This feature is similar to MRTG, graphs do not have to be generated on the fly this way. Leave this field blank to disable this feature.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"path_html_export_skip" => array(
			"friendly_name" => "Export Every x Times",
			"description" => "If you don't want cacti to export static images every 5 minutes, put another number here. For instance, 3 would equal every 15 minutes.",
			"method" => "textbox",
			"tab" => "path",
			"max_length" => "10"
			)
		),
	"general" => array(
		"log" => array(
			"friendly_name" => "Log File",
			"description" => "What cacti should put in its log.",
			"method" => "checkbox_group",
			"tab" => "general",
			"items" => array(
				"log_graph" => array(
					"friendly_name" => "Graph",
					"default" => "on"
					),
				"log_create" => array(
					"friendly_name" => "Create",
					"default" => "on"
					),
				"log_update" => array(
					"friendly_name" => "Update",
					"default" => "on"
					),
				"log_snmp" => array(
					"friendly_name" => "SNMP",
					"default" => "on"
					)
				),
			),
		"smnp_version" => array(
			"friendly_name" => "SNMP Version",
			"description" => "The type of SNMP you have installed.",
			"method" => "drop_array",
			"array" => $snmp_implimentations,
			),
		"guest_user" => array(
			"friendly_name" => "Guest User",
			"description" => "The name of the guest user for viewing graphs; is \"guest\" by default.",
			"method" => "textbox",
			"default" => "guest",
			"max_length" => "100"
			),
		"remove_verification" => array(
			"friendly_name" => "Remove Verification",
			"description" => "Confirm Before the User Removes an Item.",
			"method" => "checkbox"
			)
		),
	"visual" => array(
		"num_rows_graph" => array(
			"friendly_name" => "Graph Management - Rows Per Page",
			"description" => "The number of rows to display on a single page for graph management.",
			"method" => "textbox",
			"default" => "30",
			"max_length" => "10"
			),
		"max_title_graph" => array(
			"friendly_name" => "Graph Management - Maximum Title Length",
			"description" => "The maximum number of characters to display for a graph title.",
			"method" => "textbox",
			"default" => "80",
			"max_length" => "10"
			),
		"max_data_query_field_length" => array(
			"friendly_name" => "Data Queries - Maximum Field Length",
			"description" => "The maximum number of characters to display for a data query field.",
			"method" => "textbox",
			"default" => "15",
			"max_length" => "10"
			),
		"max_data_query_javascript_rows" => array(
			"friendly_name" => "Data Queries - Maximum JavaScript Rows",
			"description" => "The maximum number of data query rows to display with JavaScript on the 'New Graphs' page.",
			"method" => "textbox",
			"default" => "96",
			"max_length" => "10"
			),
		"num_rows_data_source" => array(
			"friendly_name" => "Data Sources - Rows Per Page",
			"description" => "The number of rows to display on a single page for data sources.",
			"method" => "textbox",
			"default" => "30",
			"max_length" => "10"
			),
		"max_title_data_source" => array(
			"friendly_name" => "Data Sources - Maximum Title Length",
			"description" => "The maximum number of characters to display for a data source title.",
			"method" => "textbox",
			"default" => "45",
			"max_length" => "10"
			)
		),
	"authentication" => array(
		"global_auth" => array(
			"friendly_name" => "Use Cacti's Builtin Authentication",
			"description" => "By default cacti handles user authentication, which allows you to create users and give them rights to different areas within cacti. You can optionally turn this off if you are other other means of authentication.",
			"method" => "checkbox",
			"tab" => "authentication"
			),
		"ldap_enabled" => array(
			"friendly_name" => "Use LDAP Authentication",
			"description" => "This will alow users to use their LDAP credentials with cacti.",
			"method" => "checkbox",
			"tab" => "authentication"
			),
		"ldap_server" => array(
			"friendly_name" => "LDAP Server",
			"description" => "The dns hostname or ip address of the server you wish to tie authentication from.",
			"method" => "textbox",
			"max_length" => "100"
			),
		"ldap_dn" => array(
			"friendly_name" => "LDAP DN",
			"description" => "This is the Distinguished Name syntax, such as &lt;username&gt;@win2kdomain.lcl.",
			"method" => "textbox",
			"max_length" => "100"
			),
		"ldap_template" => array(
			"friendly_name" => "LDAP Cacti Template User",
			"description" => "This is the user that cacti will use as a template for new LDAP users.",
			"method" => "textbox",
			"max_length" => "100"
			)
		)
	);

$settings_graphs = array(
	"general" => array(
		"default_rra_id" => array(
			"friendly_name" => "Default RRA",
			"description" => "The default RRA to use when displaying graphs in preview mode.",
			"method" => "drop_sql",
			"sql" => "select id,name from rra order by name",
			"default" => "1"
			),
		"default_height" => array(
			"friendly_name" => "Height",
			"description" => "The height of graphs created in preview mode.",
			"method" => "textbox",
			"default" => "100",
			"max_length" => "10"
			),
		"default_width" => array(
			"friendly_name" => "Width",
			"description" => "The width of graphs created in preview mode.",
			"method" => "textbox",
			"default" => "300",
			"max_length" => "10"
			),
		"default_view_mode" => array(
			"friendly_name" => "Default View Mode",
			"description" => "What mode you wanted displayed when you visit 'graph_view.php'",
			"method" => "drop_array",
			"array" => $graph_views,
			"default" => "1"
			),
		"timespan" => array(
			"friendly_name" => "Timespan",
			"description" => "The amount of time to represent on a graph created in preview mode (0 uses auto).",
			"method" => "textbox",
			"default" => "60000",
			"max_length" => "12"
			),
		"num_columns" => array(
			"friendly_name" => "Columns",
			"description" => "The number of columns to display graphs in using preview mode.",
			"method" => "textbox",
			"default" => "2",
			"max_length" => "5"
			),
		"num_graphs_per_page" => array(
			"friendly_name" => "Graphs Per-Page",
			"description" => "The number of graphs to display graphs on one page using preview mode.",
			"method" => "textbox",
			"default" => "10",
			"max_length" => "10"
			),
		"page_refresh" => array(
			"friendly_name" => "Page Refresh",
			"description" => "The number of seconds between automatic page refreshes.",
			"method" => "textbox",
			"default" => "300",
			"max_length" => "10"
			)
		),
	"tree" => array(
		"default_tree_id" => array(
			"friendly_name" => "Default Graph Hierarchy",
			"description" => "The default graph hierarchy to use when displaying graphs in tree mode.",
			"method" => "drop_sql",
			"sql" => "select id,name from graph_tree where user_id=0 order by name",
			"default" => "0",
			"tab" => "tree"
			),
		"default_tree_view_mode" => array(
			"friendly_name" => "Default Tree View Mode",
			"description" => "The default mode that will be used when viewing tree mode.",
			"method" => "drop_array",
			"array" => $graph_tree_views,
			"default" => "2",
			"tab" => "tree"
			),
		"expand_hosts" => array(
			"friendly_name" => "Expand Hosts",
			"description" => "Choose whether to expand the graph templates used for a host on the dual pane tree.",
			"method" => "checkbox",
			"default" => "",
			"tab" => "tree"
			)
		)
	);
?>
