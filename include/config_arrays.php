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

$messages = array(
	1  => array(
		"message" => 'Save Successful.',
		"type" => "info"),
	2  => array(
		"message" => 'Save Failed',
		"type" => "error"),
	3  => array(
		"message" => 'Save Failed: Field Input Error (Check Red Fields)',
		"type" => "error"),
	4  => array(
		"message" => 'Passwords do not match, please retype.',
		"type" => "error"),
	5  => array(
		"message" => 'You must select at least one field.',
		"type" => "error"),
	6  => array(
		"message" => 'You must have built in user authentication turned on to use this feature.',
		"type" => "error"),
	7  => array(
		"message" => 'XML parse error.',
		"type" => "error")
		);

$cdef_operators = array(1 =>
	"+",
	"-",
	"*",
	"/",
	"%");

$cdef_functions = array(1 =>
	"SIN",
	"COS",
	"LOG",
	"EXP",
	"FLOOR",
	"CEIL",
	"LT",
	"LE",
	"GT",
	"GE",
	"EQ",
	"IF",
	"MIN",
	"MAX",
	"LIMIT",
	"DUP",
	"EXC",
	"POP",
	"UN",
	"UNKN",
	"PREV",
	"INF",
	"NEGINF",
	"NOW",
	"TIME",
	"LTIME");

$input_types = array(1 =>
	"Script/Command",
	"SNMP",
	"SNMP Query",
	"Script Query");

$snmp_query_field_actions = array(1 =>
	"SNMP Field Name (Dropdown)",
	"SNMP Field Value (From User)",
	"SNMP Output Type (Dropdown)");

$consolidation_functions = array(1 =>
	"AVERAGE",
	"MIN",
	"MAX",
	"LAST");
					
$data_source_types = array(1 =>
	"GAUGE",
	"COUNTER",
	"DERIVE",
	"ABSOLUTE");
				
$graph_item_types = array(1 =>
	"COMMENT",
	"HRULE",
	"VRULE",
	"LINE1",
	"LINE2",
	"LINE3",
	"AREA",
	"STACK",
	"GPRINT",
	"LEGEND");

$image_types = array(1 =>
	"PNG",
	"GIF");

$snmp_versions = array(1 =>
	"Version 1",
	"Version 2",
	"Version 3");

$registered_cacti_names = array(
	"path_cacti");

$graph_views = array(1 =>
	"Tree View",
	"List View",
	"Preview View");

$graph_tree_views = array(1 =>
	"Single Pane (0.6.x - 0.8.2a Default)",
	"Dual Pane");

$auth_realms = array(0 =>
	"Local",
	"LDAP");

$snmp_implimentations = array(
	"ucd-snmp" => "UCD-SNMP 4.x",
	"net-snmp" => "NET-SNMP 5.x");

$cdef_item_types = array(
	1 => "Function",
	2 => "Operator",
	4 => "Special Data Source",
	5 => "Another CDEF",
	6 => "Custom String");
			      
$custom_data_source_types = array(
	"CURRENT_DATA_SOURCE" => "Current Graph Item Data Source",
	"ALL_DATA_SOURCES_NODUPS" => "All Data Sources (Don't Include Duplicates)",
	"ALL_DATA_SOURCES_DUPS" => "All Data Sources (Include Duplicates)",
	"CURRENT_MINIMUM_VALUE" => "Current Item: Minimum Value",
	"CURRENT_MAXIMUM_VALUE" => "Current Item: Maximum Value");

$menu = array(
	"Create" => array(
		"graphs_new.php" => "New Graphs"
		),
	"Management" => array(
		"graphs.php" => array(
			"graphs.php" => "Graph Management",
			"cdef.php" => "CDEFs",
			"color.php" => "Colors",
			"gprint_presets.php" => "GPRINT Presets"
			),
		"tree.php" => "Graph Trees",
		"data_sources.php" => array(
			"data_sources.php" => "Data Sources",
			"rra.php" => "RRAs"
			),
		"host.php" => 'Devices',
		"data_input.php" => "Data Input Methods",
		"snmp.php" => "Data Queries"
		),
	"Templates" => array(
		"graph_templates.php" => "Graph Templates",
		"host_templates.php" => "Host Templates",
		"data_templates.php" => "Data Templates"
		),
	"Import/Export" => array(
		"templates_import.php" => "Import Templates",
		"templates_export.php" => "Export Templates"
		),
	"Configuration"  => array(
		"utilities.php" => "Utilities",
		"settings.php" => "Cacti Settings"
		),
	"Utilities" => array(
		"user_admin.php" => "User Management",
		"logout.php" => "Logout User"
	));

$user_auth_realms = array(
	1 => "User Administration",
	2 => "Data Input",
	3 => "Update Data Sources",
	4 => "Update Graph Trees",
	5 => "Update Graphs",
	7 => "View Graphs",
	8 => "Console Access",
	9 => "Update Round Robin Archives",
	10 => "Update Graph Templates",
	11 => "Update Data Templates",
	12 => "Update Host Templates",
	13 => "Data Queries",
	14 => "Update CDEF's",
	15 => "Global Settings",
	16 => "Export Data",
	17 => "Import Data"
	);

$user_auth_realm_filenames = array(
	"about.php" => 8,
	"cdef.php" => 14,
	"color.php" => 5,
	"data_input.php" => 2,
	"data_sources.php" => 3,
	"data_templates.php" => 11,
	"gprint_presets.php" => 5,
	"graph.php" => 7,
	"graph_image.php" => 7,
	"graph_settings.php" => 7,
	"graph_templates.php" => 10,
	"graph_templates_inputs.php" => 10,
	"graph_templates_items.php" => 10,
	"graph_view.php" => 7,
	"graphs.php" => 5,
	"graphs_items.php" => 5,
	"graphs_new.php" => 5,
	"host.php" => 3,
	"host_templates.php" => 12,
	"index.php" => 8,
	"rra.php" => 9,
	"settings.php" => 15,
	"snmp.php" => 13,
	"templates_export.php" => 16,
	"templates_import.php" => 17,
	"tree.php" => 4,
	"user_admin.php" => 1,
	"utilities.php" => 15
	);

$hash_type_codes = array(
	"round_robin_archive" => "15",
	"cdef" => "05",
	"cdef_item" => "14",
	"gprint_preset" => "06",
	"data_input_method" => "03",
	"data_input_field" => "07",
	"data_template" => "01",
	"data_template_item" => "08",
	"graph_template" => "00",
	"graph_template_item" => "10",
	"graph_template_input" => "09",
	"data_query" => "04",
	"data_query_graph" => "11",
	"data_query_sv_graph" => "12",
	"data_query_sv_data_source" => "13",
	"host_template" => "02"
	);

$hash_version_codes = array(
	"0.8.4" => "0000",
	"0.8.5" => "0001"
	);

$hash_type_names = array(
	"cdef" => "CDEF",
	"cdef_item" => "CDEF Item",
	"gprint_preset" => "GPRINT Preset",
	"data_input_method" => "Data Input Method",
	"data_input_field" => "Data Input Field",
	"data_template" => "Data Template",
	"data_template_item" => "Data Template Item",
	"graph_template" => "Graph Template",
	"graph_template_item" => "Graph Template Item",
	"graph_template_input" => "Graph Template Input",
	"data_query" => "Data Query",
	"host_template" => "Host Template",
	"round_robin_archive" => "Round Robin Archive"
	);
?>
