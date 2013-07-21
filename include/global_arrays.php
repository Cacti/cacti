<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

global $menu;

$messages = array(
	1  => array(
		"message" => 'Save Successful.',
		"type" => "info"),
	2  => array(
		"message" => 'Save Failed.',
		"type" => "error"),
	3  => array(
		"message" => 'Save Failed: Field Input Error (Check Red Fields).',
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
		"type" => "error"),
	12 => array(
		"message" => 'Username already in use.',
		"type" => "error"),
	15 => array(
		"message" => 'XML: Cacti version does not exist.',
		"type" => "error"),
	16 => array(
		"message" => 'XML: Hash version does not exist.',
		"type" => "error"),
	17 => array(
		"message" => 'XML: Generated with a newer version of Cacti.',
		"type" => "error"),
	18 => array(
		"message" => 'XML: Cannot locate type code.',
		"type" => "error"),
	19 => array(
		"message" => 'Username already exists.',
		"type" => "error"),
	20 => array(
		"message" => 'Username change not permitted for designated template or guest user.',
		"type" => "error"),
	21 => array(
		"message" => 'User delete not permitted for designated template or guest user.',
		"type" => "error"),
	22 => array(
		"message" => 'User delete not permitted for designated graph export user.',
		"type" => "error"),
	23 => array(
		"message" => 'Data Template Includes Deleted Round Robin Archive.  Please run Database Repair Script to Identify and/or Correct.',
		"type" => "error"),
	24 => array(
		"message" => 'Graph Template Includes Deleted GPrint Prefix.  Please run Database Repair Script to Identify and/or Correct.',
		"type" => "error"),
	25 => array(
		"message" => 'Graph Template Includes Deleted CDEFs.  Please run Database Repair Script to Identify and/or Correct.',
		"type" => "error"),
	26 => array(
		"message" => 'Graph Template Includes Deleted Data Input Method.  Please run Database Repair Script to Identify.',
		"type" => "error"),
	27 => array(
		"message" => 'Data Template Not Found during Export.  Please run Database Repair Script to Identify.',
		"type" => "error"),
	28 => array(
		"message" => 'Host Template Not Found during Export.  Please run Database Repair Script to Identify.',
		"type" => "error"),
	29 => array(
		"message" => 'Data Query Not Found during Export.  Please run Database Repair Script to Identify.',
		"type" => "error"),
	30 => array(
		"message" => 'Graph Template Not Found during Export.  Please run Database Repair Script to Identify.',
		"type" => "error"),
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

$input_types = array(
	DATA_INPUT_TYPE_SNMP => "SNMP", // Action 0:
	DATA_INPUT_TYPE_SNMP_QUERY => "SNMP Query",
	DATA_INPUT_TYPE_SCRIPT => "Script/Command",  // Action 1:
	DATA_INPUT_TYPE_SCRIPT_QUERY => "Script Query", // Action 1:
	DATA_INPUT_TYPE_PHP_SCRIPT_SERVER => "Script - Script Server (PHP)",
	DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER => "Script Query - Script Server"
	);

$reindex_types = array(
	DATA_QUERY_AUTOINDEX_NONE => "None",
	DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME => "Uptime Goes Backwards",
	DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE => "Index Count Changed",
	DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION => "Verify All Fields"
	);

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

$graph_item_types = array(
	GRAPH_ITEM_TYPE_COMMENT => "COMMENT",
	GRAPH_ITEM_TYPE_HRULE   => "HRULE",
	GRAPH_ITEM_TYPE_VRULE   => "VRULE",
	GRAPH_ITEM_TYPE_LINE1   => "LINE1",
	GRAPH_ITEM_TYPE_LINE2   => "LINE2",
	GRAPH_ITEM_TYPE_LINE3   => "LINE3",
	GRAPH_ITEM_TYPE_AREA    => "AREA",
	GRAPH_ITEM_TYPE_STACK   => "STACK",
	GRAPH_ITEM_TYPE_GPRINT  => "GPRINT",
	GRAPH_ITEM_TYPE_LEGEND  => "LEGEND"
	);

$image_types = array(1 =>
	"PNG",
	"GIF",
	"SVG");

$snmp_versions = array(0 =>
	"Not In Use",
	"Version 1",
	"Version 2",
	"Version 3");

$snmp_auth_protocols = array(
	"MD5" => "MD5 (default)",
	"SHA" => "SHA");

$snmp_priv_protocols = array(
	"[None]" => "[None]",
	"DES" => "DES (default)",
	"AES128" => "AES");

$banned_snmp_strings = array(
	"End of MIB",
	"No Such");

$logfile_options = array(1 =>
	"Logfile Only",
	"Logfile and Syslog/Eventlog",
	"Syslog/Eventlog Only");

$availability_options = array(
	AVAIL_NONE => "None",
	AVAIL_SNMP_AND_PING => "Ping and SNMP Uptime",
	AVAIL_SNMP_OR_PING => "Ping or SNMP Uptime",
	AVAIL_SNMP => "SNMP Uptime",
	AVAIL_SNMP_GET_SYSDESC => "SNMP Desc",
	AVAIL_SNMP_GET_NEXT => "SNMP getNext",
	AVAIL_PING => "Ping");

$ping_methods = array(
	PING_ICMP => "ICMP Ping",
	PING_TCP => "TCP Ping",
	PING_UDP => "UDP Ping");

$logfile_verbosity = array(
	POLLER_VERBOSITY_NONE => "NONE - Syslog Only if Selected",
	POLLER_VERBOSITY_LOW => "LOW - Statistics and Errors",
	POLLER_VERBOSITY_MEDIUM => "MEDIUM - Statistics, Errors and Results",
	POLLER_VERBOSITY_HIGH => "HIGH - Statistics, Errors, Results and Major I/O Events",
	POLLER_VERBOSITY_DEBUG => "DEBUG - Statistics, Errors, Results, I/O and Program Flow",
	POLLER_VERBOSITY_DEVDBG => "DEVEL - Developer DEBUG Level");

$poller_options = array(1 =>
	"cmd.php",
	"spine");

$poller_intervals = array(
	10 => "Every 10 Seconds",
	15 => "Every 15 Seconds",
	20 => "Every 20 Seconds",
	30 => "Every 30 Seconds",
	60 => "Every Minute",
	300 => "Every 5 Minutes");

$device_threads = array(
	1 => "1 Thread (default)",
	2 => "2 Threads",
	3 => "3 Threads",
	4 => "4 Threads",
	5 => "5 Threads",
	6 => "6 Threads"
	);

$cron_intervals = array(
	60 => "Every Minute",
	300 => "Every 5 Minutes");

$registered_cacti_names = array(
	"path_cacti");

$graph_views = array(1 =>
	"Tree View",
	"List View",
	"Preview View");

$graph_tree_views = array(1 =>
	"Single Pane",
	"Dual Pane");

$auth_methods = array(
	0 => "None",
	1 => "Builtin Authentication",
	2 => "Web Basic Authentication");
if (function_exists("ldap_connect")) {
	$auth_methods[3] = "LDAP Authentication";
}

$auth_realms = array(0 =>
	"Local",
	"LDAP",
	"Web Basic");

$ldap_versions = array(
	2 => "Version 2",
	3 => "Version 3"
	);

$ldap_encryption = array(
	0 => "None",
	1 => "SSL",
	2 => "TLS");

$ldap_modes = array(
	0 => "No Searching",
	1 => "Anonymous Searching",
	2 => "Specific Searching");

$snmp_implimentations = array(
	"ucd-snmp" => "UCD-SNMP 4.x",
	"net-snmp" => "NET-SNMP 5.x");

$rrdtool_versions = array(
	"rrd-1.0.x" => "RRDTool 1.0.x",
	"rrd-1.2.x" => "RRDTool 1.2.x",
	"rrd-1.3.x" => "RRDTool 1.3.x",
	"rrd-1.4.x" => "RRDTool 1.4.x");

$cdef_item_types = array(
	1 => "Function",
	2 => "Operator",
	4 => "Special Data Source",
	5 => "Another CDEF",
	6 => "Custom String");

$graph_color_alpha = array(
		"00" => "  0%",
		"19" => " 10%",
		"33" => " 20%",
		"4C" => " 30%",
		"66" => " 40%",
		"7F" => " 50%",
		"99" => " 60%",
		"B2" => " 70%",
		"CC" => " 80%",
		"E5" => " 90%",
		"FF" => "100%"
		);

$tree_sort_types = array(
	TREE_ORDERING_NONE => "Manual Ordering (No Sorting)",
	TREE_ORDERING_ALPHABETIC => "Alphabetic Ordering",
	TREE_ORDERING_NATURAL => "Natural Ordering",
	TREE_ORDERING_NUMERIC => "Numeric Ordering"
	);

$tree_item_types = array(
	TREE_ITEM_TYPE_HEADER => "Header",
	TREE_ITEM_TYPE_GRAPH => "Graph",
	TREE_ITEM_TYPE_HOST => "Host"
	);

$host_group_types = array(
	HOST_GROUPING_GRAPH_TEMPLATE => "Graph Template",
	HOST_GROUPING_DATA_QUERY_INDEX => "Data Query Index"
	);

$custom_data_source_types = array(
	"CURRENT_DATA_SOURCE" => "Current Graph Item Data Source",
	"ALL_DATA_SOURCES_NODUPS" => "All Data Sources (Don't Include Duplicates)",
	"ALL_DATA_SOURCES_DUPS"	=> "All Data Sources (Include Duplicates)",
	"SIMILAR_DATA_SOURCES_NODUPS" => "All Similar Data Sources (Don't Include Duplicates)",
	"SIMILAR_DATA_SOURCES_DUPS" => "All Similar Data Sources (Include Duplicates)",
	"CURRENT_DS_MINIMUM_VALUE" => "Current Data Source Item: Minimum Value",
	"CURRENT_DS_MAXIMUM_VALUE" => "Current Data Source Item: Maximum Value",
	"CURRENT_GRAPH_MINIMUM_VALUE" => "Graph: Lower Limit",
	"CURRENT_GRAPH_MAXIMUM_VALUE" => "Graph: Upper Limit",
	"COUNT_ALL_DS_NODUPS" => "Count of All Data Sources (Don't Include Duplicates)",
	"COUNT_ALL_DS_DUPS" => "Count of All Data Sources (Include Duplicates)",
	"COUNT_SIMILAR_DS_NODUPS" => "Count of All Similar Data Sources (Don't Include Duplicates)",
	"COUNT_SIMILAR_DS_DUPS"	=> "Count of All Similar Data Sources (Include Duplicates)");

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
		"host.php" => 'Devices'
		),
	"Collection Methods" => array(
		"data_queries.php" => "Data Queries",
		"data_input.php" => "Data Input Methods"
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
		"settings.php" => "Settings"
		),
	"Utilities" => array(
		"utilities.php" => "System Utilities",
		"user_admin.php" => "User Management",
		"logout.php" => "Logout User"
	));

$log_tail_lines = array(
	-1 => "All Lines",
	10 => "10 Lines",
	15 => "15 Lines",
	20 => "20 Lines",
	50 => "50 Lines",
	100 => "100 Lines",
	200 => "200 Lines",
	500 => "500 Lines",
	1000 => "1000 Lines",
	2000 => "2000 Lines",
	3000 => "3000 Lines",
	5000 => "5000 Lines",
	10000 => "10000 Lines"
	);

$item_rows = array(
	10 => "10",
	15 => "15",
	20 => "20",
	25 => "25",
	30 => "30",
	40 => "40",
	50 => "50",
	100 => "100",
	250 => "250",
	500 => "500",
	1000 => "1000",
	2000 => "2000",
	5000 => "5000"
	);

$graphs_per_page = array(
	4 => "4",
	6 => "6",
	8 => "8",
	10 => "10",
	14 => "14",
	20 => "20",
	24 => "24",
	30 => "30",
	40 => "40",
	50 => "50",
	100 => "100"
	);

$page_refresh_interval = array(
	5 => "5 Seconds",
	10 => "10 Seconds",
	20 => "20 Seconds",
	30 => "30 Seconds",
	60 => "1 Minute",
	300 => "5 Minutes",
	600 => "10 Minutes",
	9999999 => "Never");

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
	"graph_xport.php" => 7,
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
	"data_queries.php" => 13,
	"templates_export.php" => 16,
	"templates_import.php" => 17,
	"tree.php" => 4,
	"user_admin.php" => 1,
	"utilities.php" => 15,
	"smtp_servers.php" => 8,
	"email_templates.php" => 8,
	"event_queue.php" => 8,
	"smtp_queue.php" => 8,
	"logout.php" => -1
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
	"0.8.4"  => "0000",
	"0.8.5"  => "0001",
	"0.8.5a" => "0002",
	"0.8.6"  => "0003",
	"0.8.6a" => "0004",
	"0.8.6b" => "0005",
	"0.8.6c" => "0006",
	"0.8.6d" => "0007",
	"0.8.6e" => "0008",
	"0.8.6f" => "0009",
	"0.8.6g" => "0010",
	"0.8.6h" => "0011",
	"0.8.6i" => "0012",
	"0.8.6j" => "0013",
	"0.8.7"  => "0014",
	"0.8.7a" => "0015",
	"0.8.7b" => "0016",
	"0.8.7c" => "0017",
	"0.8.7d" => "0018",
	"0.8.7e" => "0019",
	"0.8.7f" => "0020",
	"0.8.7g" => "0021",
	"0.8.7h" => "0022",
	"0.8.7i" => "0023",
	"0.8.8"  => "0024",
	"0.8.8a" => "0024",
	"0.8.8b" => "0024"
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

$host_struc = array(
	"host_template_id",
	"description",
	"hostname",
	"notes",
	"snmp_community",
	"snmp_version",
	"snmp_username",
	"snmp_password",
	"snmp_auth_protocol",
	"snmp_priv_passphrase",
	"snmp_priv_protocol",
	"snmp_context",
	"snmp_port",
	"snmp_timeout",
	"max_oids",
	"device_threads",
	"availability_method",
	"ping_method",
	"ping_port",
	"ping_timeout",
	"ping_retries",
	"disabled",
	"status",
	"status_event_count",
	"status_fail_date",
	"status_rec_date",
	"status_last_error",
	"min_time",
	"max_time",
	"cur_time",
	"avg_time",
	"total_polls",
	"failed_polls",
	"availability"
	);

$graph_timespans = array(
	GT_LAST_HALF_HOUR => "Last Half Hour",
	GT_LAST_HOUR => "Last Hour",
	GT_LAST_2_HOURS => "Last 2 Hours",
	GT_LAST_4_HOURS => "Last 4 Hours",
	GT_LAST_6_HOURS =>"Last 6 Hours",
	GT_LAST_12_HOURS =>"Last 12 Hours",
	GT_LAST_DAY =>"Last Day",
	GT_LAST_2_DAYS =>"Last 2 Days",
	GT_LAST_3_DAYS =>"Last 3 Days",
	GT_LAST_4_DAYS =>"Last 4 Days",
	GT_LAST_WEEK =>"Last Week",
	GT_LAST_2_WEEKS =>"Last 2 Weeks",
	GT_LAST_MONTH =>"Last Month",
	GT_LAST_2_MONTHS =>"Last 2 Months",
	GT_LAST_3_MONTHS =>"Last 3 Months",
	GT_LAST_4_MONTHS =>"Last 4 Months",
	GT_LAST_6_MONTHS =>"Last 6 Months",
	GT_LAST_YEAR =>"Last Year",
	GT_LAST_2_YEARS =>"Last 2 Years",
	GT_DAY_SHIFT => "Day Shift",
	GT_THIS_DAY => "This Day",
	GT_THIS_WEEK => "This Week",
	GT_THIS_MONTH => "This Month",
	GT_THIS_YEAR => "This Year",
	GT_PREV_DAY => "Previous Day",
	GT_PREV_WEEK => "Previous Week",
	GT_PREV_MONTH => "Previous Month",
	GT_PREV_YEAR => "Previous Year"
	);

$graph_timeshifts = array(
	GTS_HALF_HOUR => "30 Min",
	GTS_1_HOUR => "1 Hour",
	GTS_2_HOURS => "2 Hours",
	GTS_4_HOURS => "4 Hours",
	GTS_6_HOURS => "6 Hours",
	GTS_12_HOURS => "12 Hours",
	GTS_1_DAY => "1 Day",
	GTS_2_DAYS => "2 Days",
	GTS_3_DAYS => "3 Days",
	GTS_4_DAYS => "4 Days",
	GTS_1_WEEK => "1 Week",
	GTS_2_WEEKS => "2 Weeks",
	GTS_1_MONTH => "1 Month",
	GTS_2_MONTHS => "2 Months",
	GTS_3_MONTHS => "3 Months",
	GTS_4_MONTHS => "4 Months",
	GTS_6_MONTHS => "6 Months",
	GTS_1_YEAR => "1 Year",
	GTS_2_YEARS => "2 Years"
	);

$graph_weekdays = array(
	WD_SUNDAY => date("l", strtotime("Sunday")),
	WD_MONDAY => date("l", strtotime("Monday")),
	WD_TUESDAY => date("l", strtotime("Tuesday")),
	WD_WEDNESDAY => date("l", strtotime("Wednesday")),
	WD_THURSDAY => date("l", strtotime("Thursday")),
	WD_FRIDAY => date("l", strtotime("Friday")),
	WD_SATURDAY => date("l", strtotime("Saturday"))
	);

$graph_dateformats = array(
	GD_MO_D_Y => "Month Number, Day, Year",
	GD_MN_D_Y => "Month Name, Day, Year",
	GD_D_MO_Y => "Day, Month Number, Year",
	GD_D_MN_Y => "Day, Month Name, Year",
	GD_Y_MO_D => "Year, Month Number, Day",
	GD_Y_MN_D => "Year, Month Name, Day"
	);

$graph_datechar = array(
	GDC_HYPHEN => "-",
	GDC_SLASH => "/"
	);

$plugin_architecture = array(
	'version' => '3.1'
	);

api_plugin_hook('config_arrays');

?>
