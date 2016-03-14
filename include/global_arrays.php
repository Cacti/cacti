<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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
		'message' => 'Save Successful.',
		'type' => 'info'),
	2  => array(
		'message' => 'Save Failed.',
		'type' => 'error'),
	3  => array(
		'message' => 'Save Failed: Field Input Error (Check Red Fields).',
		'type' => 'error'),
	4  => array(
		'message' => 'Passwords do not match, please retype.',
		'type' => 'error'),
	5  => array(
		'message' => 'You must select at least one field.',
		'type' => 'error'),
	6  => array(
		'message' => 'You must have built in user authentication turned on to use this feature.',
		'type' => 'error'),
	7  => array(
		'message' => 'XML parse error.',
		'type' => 'error'),
	12 => array(
		'message' => 'Username already in use.',
		'type' => 'error'),
	15 => array(
		'message' => 'XML: Cacti version does not exist.',
		'type' => 'error'),
	16 => array(
		'message' => 'XML: Hash version does not exist.',
		'type' => 'error'),
	17 => array(
		'message' => 'XML: Generated with a newer version of Cacti.',
		'type' => 'error'),
	18 => array(
		'message' => 'XML: Cannot locate type code.',
		'type' => 'error'),
	19 => array(
		'message' => 'Username already exists.',
		'type' => 'error'),
	20 => array(
		'message' => 'Username change not permitted for designated template or guest user.',
		'type' => 'error'),
	21 => array(
		'message' => 'User delete not permitted for designated template or guest user.',
		'type' => 'error'),
	22 => array(
		'message' => 'User delete not permitted for designated graph export user.',
		'type' => 'error'),
	23 => array(
		'message' => 'Data Template Includes Deleted Data Source Profile.  Please resave the Data Template with an existing Data Source Profile.',
		'type' => 'error'),
	24 => array(
		'message' => 'Graph Template Includes Deleted GPrint Prefix.  Please run Database Repair Script to Identify and/or Correct.',
		'type' => 'error'),
	25 => array(
		'message' => 'Graph Template Includes Deleted CDEFs.  Please run Database Repair Script to Identify and/or Correct.',
		'type' => 'error'),
	26 => array(
		'message' => 'Graph Template Includes Deleted Data Input Method.  Please run Database Repair Script to Identify.',
		'type' => 'error'),
	27 => array(
		'message' => 'Data Template Not Found during Export.  Please run Database Repair Script to Identify.',
		'type' => 'error'),
	28 => array(
		'message' => 'Device Template Not Found during Export.  Please run Database Repair Script to Identify.',
		'type' => 'error'),
	29 => array(
		'message' => 'Data Query Not Found during Export.  Please run Database Repair Script to Identify.',
		'type' => 'error'),
	30 => array(
		'message' => 'Graph Template Not Found during Export.  Please run Database Repair Script to Identify.',
		'type' => 'error'),
	'clog_purged' => array(
		'message' => 'Cacti Log Purged Sucessfully', 
		'type' => 'info'),
	'nopassword' => array(
		'message' => 'Error: You are not allowed to change your password.', 
		'type' => 'error'),
	'nodomainpassword' => array(
		'message' => 'Error: LDAP/AD based password change not supported.', 
		'type' => 'error'),
	'clog_permissions' => array(
		'message' => 'Error: Unable to clear log, no write permissions', 
		'type' => 'error'),
	'clog_missing' => array(
		'message' => 'Error: Unable to clear log, file does not exist', 
		'type' => 'error'),
	'mg_mailtime_invalid' => array(
		'message' => 'Invalid Timestamp. Select timestamp in the future.',
		'type'    => 'error'),
	'reports_save' => array(
		'message' => '<i>Report Saved</i>', 
		'type' => 'info'),
	'reports_save_failed' => array(
		'message' => '<font style="color:red;"><i>Report Save Failed</i></font>', 
		'type' => 'info'),
	'reports_item_save' => array(
		'message' => '<i>Report Item Saved</i>', 
		'type' => 'info'),
	'reports_item_save_failed' => array(
		'message' => '<font style="color:red;"><i>Report Item Save Failed</i></font>', 
		'type' => 'info')
);

if (isset($_SESSION['automation_message']) && $_SESSION['automation_message'] != '') {
	$messages['automation_message'] = array(
		'message' => $_SESSION['automation_message'], 
		'type' => 'info'
	);
}

if (isset($_SESSION['clog_message']) && $_SESSION['clog_message'] != '') {
	$messages['clog_message'] = array(
		'message' => $_SESSION['clog_message'], 
		'type' => 'info'
	);
}

if (isset($_SESSION['clog_error']) && $_SESSION['clog_error'] != '') {
	$messages['clog_error'] = array(
		'message' => $_SESSION['clog_error'], 
		'type' => 'error'
	);
}

if (isset($_SESSION['reports_message']) && $_SESSION['reports_message'] != '') {
	$messages['reports_message'] = array(
		'message' => '<i>' . $_SESSION['reports_message'] . '</i>', 
		'type' => 'info'
	);
}

if (isset($_SESSION['reports_error']) && $_SESSION['reports_error'] != '') {
	$messages['reports_error'] = array(
		'message' => "<span style='color:red;'><i>" . $_SESSION['reports_error'] . "</i></span>", 
		'type' => 'info'
	);
}

$cdef_operators = array(1 =>
	'+',
	'-',
	'*',
	'/',
	'%'
);

$cdef_functions = array(1 =>
	'SIN',
	'COS',
	'LOG',
	'EXP',
	'FLOOR',
	'CEIL',
	'LT',
	'LE',
	'GT',
	'GE',
	'EQ',
	'IF',
	'MIN',
	'MAX',
	'LIMIT',
	'DUP',
	'EXC',
	'POP',
	'UN',
	'UNKN',
	'PREV',
	'INF',
	'NEGINF',
	'NOW',
	'TIME',
	'LTIME'
);

$vdef_functions = array(1 =>
	'MAXIMUM',
	'MINIMUM',
	'AVERAGE',
	'STDEV',
	'LAST',
	'FIRST',
	'TOTAL',
	'PERCENT',
	'PERCENTNAN',
	'LSLSLOPE',
	'LSLINT',
	'LSLCORREL'
);

$vdef_item_types = array(
	CVDEF_ITEM_TYPE_FUNCTION        => 'Function',
	CVDEF_ITEM_TYPE_SPEC_DS         => 'Special Data Source',
	CVDEF_ITEM_TYPE_STRING          => 'Custom String',
);

$custom_vdef_data_source_types = array( // this may change as soon as RRDTool supports math in VDEF, until then only reference to CDEF may help
	'CURRENT_DATA_SOURCE' => 'Current Graph Item Data Source',
);

$input_types = array(
	DATA_INPUT_TYPE_SNMP                => 'SNMP', // Action 0:
	DATA_INPUT_TYPE_SNMP_QUERY          => 'SNMP Query',
	DATA_INPUT_TYPE_SCRIPT              => 'Script/Command',  // Action 1:
	DATA_INPUT_TYPE_SCRIPT_QUERY        => 'Script Query', // Action 1:
	DATA_INPUT_TYPE_PHP_SCRIPT_SERVER   => 'Script - Script Server (PHP)',
	DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER => 'Script Query - Script Server'
);

$reindex_types = array(
	DATA_QUERY_AUTOINDEX_NONE               => 'None',
	DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME   => 'Uptime Goes Backwards',
	DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE   => 'Index Count Changed',
	DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION => 'Verify All Fields'
);

$snmp_query_field_actions = array(1 =>
	'SNMP Field Name (Dropdown)',
	'SNMP Field Value (From User)',
	'SNMP Output Type (Dropdown)'
);

$consolidation_functions = array(1 =>
	'AVERAGE',
	'MIN',
	'MAX',
	'LAST'
);

$data_source_types = array(1 =>
	'GAUGE',
	'COUNTER',
	'DERIVE',
	'ABSOLUTE'
);

$rrd_font_render_modes = array(
	RRD_FONT_RENDER_NORMAL  => 'Normal',
	RRD_FONT_RENDER_LIGHT   => 'Light',
	RRD_FONT_RENDER_MONO    => 'Mono',
);

$rrd_graph_render_modes = array(
	RRD_GRAPH_RENDER_NORMAL => 'Normal',
	RRD_GRAPH_RENDER_MONO   => 'Mono',
);

$rrd_legend_position = array(
	RRD_LEGEND_POS_NORTH    => 'North',
	RRD_LEGEND_POS_SOUTH    => 'South',
	RRD_LEGEND_POS_WEST     => 'West',
	RRD_LEGEND_POS_EAST     => 'East',
);

$rrd_textalign = array(
	RRD_ALIGN_LEFT          => 'Left',
	RRD_ALIGN_RIGHT         => 'Right',
	RRD_ALIGN_JUSTIFIED     => 'Justified',
	RRD_ALIGN_CENTER        => 'Center',
);

$rrd_legend_direction = array(
	RRD_LEGEND_DIR_TOPDOWN  => 'Top -> Down',
	RRD_LEGEND_DIR_BOTTOMUP => 'Bottom -> Up',
);

$rrd_axis_formatters = array(
	'numeric'   => 'Numeric',
	'timestamp' => 'Timestamp',
	'duration'  => 'Duration'
);

$graph_item_types = array(
	GRAPH_ITEM_TYPE_COMMENT         => 'COMMENT',
	GRAPH_ITEM_TYPE_HRULE           => 'HRULE',
	GRAPH_ITEM_TYPE_VRULE           => 'VRULE',
	GRAPH_ITEM_TYPE_LINE1           => 'LINE1',
	GRAPH_ITEM_TYPE_LINE2           => 'LINE2',
	GRAPH_ITEM_TYPE_LINE3           => 'LINE3',
	GRAPH_ITEM_TYPE_AREA            => 'AREA',
	GRAPH_ITEM_TYPE_STACK           => 'AREA:STACK',
	GRAPH_ITEM_TYPE_GPRINT          => 'GPRINT',
	GRAPH_ITEM_TYPE_GPRINT_AVERAGE  => 'GPRINT:AVERAGE',
	GRAPH_ITEM_TYPE_GPRINT_LAST     => 'GPRINT:LAST',
	GRAPH_ITEM_TYPE_GPRINT_MAX      => 'GPRINT:MAX',
	GRAPH_ITEM_TYPE_GPRINT_MIN      => 'GPRINT:MIN',
	GRAPH_ITEM_TYPE_LEGEND          => 'LEGEND',
	GRAPH_ITEM_TYPE_LINESTACK       => 'LINE:STACK',
	GRAPH_ITEM_TYPE_TIC             => 'TICK',
	GRAPH_ITEM_TYPE_TEXTALIGN       => 'TEXTALIGN',
);

$image_types = array(
	1 => 'PNG', 
	3 => 'SVG'
);

$snmp_versions = array(0 =>
	'Not In Use',
	'Version 1',
	'Version 2',
	'Version 3'
);

$snmp_auth_protocols = array(
	'MD5' => 'MD5 (default)',
	'SHA' => 'SHA'
);

$snmp_priv_protocols = array(
	'[None]' => '[None]',
	'DES'    => 'DES (default)',
	'AES128' => 'AES'
);

$banned_snmp_strings = array(
	'End of MIB',
	'No Such'
);

$logfile_options = array(1 =>
	'Logfile Only',
	'Logfile and Syslog/Eventlog',
	'Syslog/Eventlog Only'
);

$availability_options = array(
	AVAIL_NONE             => 'None',
	AVAIL_SNMP_AND_PING    => 'Ping and SNMP Uptime',
	AVAIL_SNMP_OR_PING     => 'Ping or SNMP Uptime',
	AVAIL_SNMP             => 'SNMP Uptime',
	AVAIL_SNMP_GET_SYSDESC => 'SNMP Desc',
	AVAIL_SNMP_GET_NEXT    => 'SNMP getNext',
	AVAIL_PING             => 'Ping'
);

$ping_methods = array(
	PING_ICMP => 'ICMP Ping',
	PING_TCP  => 'TCP Ping',
	PING_UDP  => 'UDP Ping'
);

$logfile_verbosity = array(
	POLLER_VERBOSITY_NONE   => 'NONE - Syslog Only if Selected',
	POLLER_VERBOSITY_LOW    => 'LOW - Statistics and Errors',
	POLLER_VERBOSITY_MEDIUM => 'MEDIUM - Statistics, Errors and Results',
	POLLER_VERBOSITY_HIGH   => 'HIGH - Statistics, Errors, Results and Major I/O Events',
	POLLER_VERBOSITY_DEBUG  => 'DEBUG - Statistics, Errors, Results, I/O and Program Flow',
	POLLER_VERBOSITY_DEVDBG => 'DEVEL - Developer DEBUG Level'
);

$poller_options = array(1 =>
	'cmd.php',
	'spine'
);

$aggregation_levels = array(
	1       => 'Selected Poller Interval',
	900     => '15 Minutes',
	1800    => '30 Minutes',
	3600    => '1 Hour',
	7200    => '2 Hours',
	10800   => '3 Hours',
	14400   => '4 Hours',
	21600   => '6 Hours',
	43200   => '12 Hours',
	86400   => 'Daily'
);
	
$sampling_intervals = array(
	10    => 'Every 10 Seconds',
	15    => 'Every 15 Seconds',
	20    => 'Every 20 Seconds',
	30    => 'Every 30 Seconds',
	60    => 'Every Minute',
	300   => 'Every 5 Minutes',
	600   => 'Every 10 Minutes',
	1200  => 'Every 20 Minutes',
	1800  => 'Every 30 Minutes',
	3600  => 'Every Hour',
	7200  => 'Every 2 Hours',
	14400 => 'Every 4 Hours'
);

$heartbeats = array(
	20    => '20 Seconds',
	30    => '30 Seconds',
	40    => '40 Seconds',
	60    => '1 Minute',
	120   => '2 Minutes',
	300   => '5 Minutes',
	600   => '10 Minutes',
	1200  => '20 Minutes',
	1800  => '30 Minutes',
	2400  => '40 Minutes',
	3600  => '1 Hour',
	7200  => '2 Hours',
	14400 => '4 Hours',
	28800 => '8 Hours',
	57600 => '16 Hours',
	86400 => '1 Day'
);

$poller_intervals = array(
	10  => 'Every 10 Seconds',
	15  => 'Every 15 Seconds',
	20  => 'Every 20 Seconds',
	30  => 'Every 30 Seconds',
	60  => 'Every Minute',
	300 => 'Every 5 Minutes'
);

$device_threads = array(
	1 => '1 Thread (default)',
	2 => '2 Threads',
	3 => '3 Threads',
	4 => '4 Threads',
	5 => '5 Threads',
	6 => '6 Threads'
);

$cron_intervals = array(
	60  => 'Every Minute',
	300 => 'Every 5 Minutes'
);

$registered_cacti_names = array(
	'path_cacti'
);

$graph_views = array(1 =>
	'Tree View',
	'List View',
	'Preview View'
);

$auth_methods = array(
	0 => 'None',
	1 => 'Builtin Authentication',
	2 => 'Web Basic Authentication'
);

if (function_exists('ldap_connect')) {
	$auth_methods[3] = 'LDAP Authentication';
	$auth_methods[4] = 'Multiple LDAP/AD Domains';
}

$domain_types = array(
	'1' => 'LDAP', 
	'2' => 'Active Directory'
);

$auth_realms = get_auth_realms();

$ldap_versions = array(
	2 => 'Version 2',
	3 => 'Version 3'
);

$ldap_encryption = array(
	0 => 'None',
	1 => 'SSL',
	2 => 'TLS'
);

$ldap_modes = array(
	0 => 'No Searching',
	1 => 'Anonymous Searching',
	2 => 'Specific Searching'
);

$rrdtool_versions = array(
	'rrd-1.2.x' => 'RRDTool 1.2.x',
	'rrd-1.3.x' => 'RRDTool 1.3.x',
	'rrd-1.4.x' => 'RRDTool 1.4.x',
	'rrd-1.5.x' => 'RRDTool 1.5.x'
);

$cdef_item_types = array(
	CVDEF_ITEM_TYPE_FUNCTION => 'Function',
	CVDEF_ITEM_TYPE_OPERATOR => 'Operator',
	CVDEF_ITEM_TYPE_SPEC_DS  => 'Special Data Source',
	CVDEF_ITEM_TYPE_CDEF     => 'Another CDEF',
	CVDEF_ITEM_TYPE_STRING   => 'Custom String'
);

$graph_color_alpha = array(
	'00' => '  0%',
	'19' => ' 10%',
	'33' => ' 20%',
	'4C' => ' 30%',
	'66' => ' 40%',
	'7F' => ' 50%',
	'99' => ' 60%',
	'B2' => ' 70%',
	'CC' => ' 80%',
	'E5' => ' 90%',
	'FF' => '100%'
);

$tree_sort_types = array(
	TREE_ORDERING_INHERIT    => 'Inherit Parent Sorting',
	TREE_ORDERING_NONE       => 'Manual Ordering (No Sorting)',
	TREE_ORDERING_ALPHABETIC => 'Alphabetic Ordering',
	TREE_ORDERING_NATURAL    => 'Natural Ordering',
	TREE_ORDERING_NUMERIC    => 'Numeric Ordering'
);

$tree_item_types = array(
	TREE_ITEM_TYPE_HEADER => 'Header',
	TREE_ITEM_TYPE_GRAPH  => 'Graph',
	TREE_ITEM_TYPE_HOST   => 'Device'
);

$host_group_types = array(
	HOST_GROUPING_GRAPH_TEMPLATE   => 'Graph Template',
	HOST_GROUPING_DATA_QUERY_INDEX => 'Data Query Index'
);

$custom_data_source_types = array(
	'CURRENT_DATA_SOURCE'         => 'Current Graph Item Data Source',
	'ALL_DATA_SOURCES_NODUPS'     => 'All Data Sources (Dont Include Duplicates)',
	'ALL_DATA_SOURCES_DUPS'       => 'All Data Sources (Include Duplicates)',
	'SIMILAR_DATA_SOURCES_NODUPS' => 'All Similar Data Sources (Dont Include Duplicates)',
	'SIMILAR_DATA_SOURCES_DUPS'   => 'All Similar Data Sources (Include Duplicates)',
	'CURRENT_DS_MINIMUM_VALUE'    => 'Current Data Source Item: Minimum Value',
	'CURRENT_DS_MAXIMUM_VALUE'    => 'Current Data Source Item: Maximum Value',
	'CURRENT_GRAPH_MINIMUM_VALUE' => 'Graph: Lower Limit',
	'CURRENT_GRAPH_MAXIMUM_VALUE' => 'Graph: Upper Limit',
	'COUNT_ALL_DS_NODUPS'         => 'Count of All Data Sources (Dont Include Duplicates)',
	'COUNT_ALL_DS_DUPS'           => 'Count of All Data Sources (Include Duplicates)',
	'COUNT_SIMILAR_DS_NODUPS'     => 'Count of All Similar Data Sources (Dont Include Duplicates)',
	'COUNT_SIMILAR_DS_DUPS'	      => 'Count of All Similar Data Sources (Include Duplicates)'
);

$menu = array(
	'Create' => array(
		'graphs_new.php' => 'New Graphs'
		),
	'Management' => array(
		'graphs.php'           => 'Graphs',
		'tree.php'             => 'Trees',
		'data_sources.php'     => 'Data Sources',
		'host.php'             => 'Devices',
		'aggregate_graphs.php' => 'Aggregates',
		),
	'Collection Methods' => array(
		'data_queries.php' => 'Data Queries',
		'data_input.php'   => 'Data Input Methods'
		),
	'Templates' => array(
		'graph_templates.php'     => 'Graph',
		'host_templates.php'      => 'Device',
		'data_templates.php'      => 'Data Source',
		'aggregate_templates.php' => 'Aggregate',
		'color_templates.php'     => 'Color'
		),
	'Automation' => array(
		'automation_networks.php'    => 'Networks',
		'automation_devices.php'     => 'Discovered Devices',
		'automation_templates.php'   => 'Device Rules',
		'automation_graph_rules.php' => 'Graph Rules',
		'automation_tree_rules.php'  => 'Tree Rules',
		'automation_snmp.php'        => 'SNMP Options',
		),
	'Presets' => array(
		'data_source_profiles.php' => 'Data Profiles',
		'cdef.php'                 => 'CDEFs',
		'vdef.php'                 => 'VDEFs',
		'color.php'                => 'Colors',
		'gprint_presets.php'       => 'GPRINTs'
		),
	'Import/Export' => array(
		'templates_import.php' => 'Import Templates',
		'templates_export.php' => 'Export Templates'
		),
	'Configuration'  => array(
		'settings.php' => 'Settings'
		),
	'Utilities' => array(
		'utilities.php'        => 'System Utilities',
		'user_admin.php'       => 'Users',
		'user_group_admin.php' => 'User Groups',
		'user_domains.php'     => 'User Domains'
		)
);

$log_tail_lines = array(
	-1    => 'All Lines',
	10    => '10 Lines',
	15    => '15 Lines',
	20    => '20 Lines',
	50    => '50 Lines',
	100   => '100 Lines',
	200   => '200 Lines',
	500   => '500 Lines',
	1000  => '1000 Lines',
	2000  => '2000 Lines',
	3000  => '3000 Lines',
	5000  => '5000 Lines',
	10000 => '10000 Lines'
);

$item_rows = array(
	10   => '10',
	15   => '15',
	20   => '20',
	25   => '25',
	30   => '30',
	40   => '40',
	50   => '50',
	100  => '100',
	250  => '250',
	500  => '500',
	1000 => '1000',
	2000 => '2000',
	5000 => '5000'
);

$graphs_per_page = array(
	4   => '4',
	6   => '6',
	8   => '8',
	9   => '9',
	10  => '10',
	12  => '12',
	14  => '14',
	15  => '15',
	16  => '16',
	18  => '18',
	20  => '20',
	24  => '24',
	25  => '25',
	27  => '27',
	28  => '28',
	30  => '30',
	32  => '32',
	35  => '35',
	40  => '40',
	50  => '50',
	100 => '100'
);

$page_refresh_interval = array(
	5       => '5 Seconds',
	10      => '10 Seconds',
	20      => '20 Seconds',
	30      => '30 Seconds',
	60      => '1 Minute',
	300     => '5 Minutes',
	600     => '10 Minutes',
	9999999 => 'Never'
);

$user_auth_realms = array(
	8  => 'Console Access',
	7  => 'View Graphs',
	20 => 'Update Profile',

	1  => 'User Management',
	15 => 'Settings and Utilities',
	23 => 'Automation Settings',

	2  => 'Data Input Methods',
	13 => 'Data Queries',

	3  => 'Devices/Data Sources',
	5  => 'Graphs',
	4  => 'Graph Trees',

	9  => 'Data Source Profiles',
	14 => 'Colors/GPrints/CDEFs/VDEFs',

	10 => 'Graph Templates',
	11 => 'Data Templates',
	12 => 'Device Templates',

	16 => 'Export Data',
	17 => 'Import Data',

	18 => 'Log Management',
	19 => 'Log Viewing',

	21 => 'Reports Management',
	22 => 'Reports Creation'
);

$user_auth_roles = array(
	'Normal User'            => array(7, 19, 20, 22),
	'Template Editor'        => array(8, 2, 9, 10, 11, 12, 13, 14, 16, 17),
	'General Administration' => array(8, 3, 4, 5, 23),
	'System Administration'  => array(8, 15, 1, 18, 21, 101)
);

$user_auth_realm_filenames = array(
	'about.php' => 8,
	'cdef.php' => 14,
	'clog.php' => 18,
	'clog_user.php' => 19,
	'color.php' => 5,
	'data_input.php' => 2,
	'data_sources.php' => 3,
	'data_source_profiles.php' => 9,
	'data_templates.php' => 11,
	'gprint_presets.php' => 5,
	'graph.php' => 7,
	'graph_image.php' => 7,
	'graph_json.php' => 7,
	'graph_xport.php' => 7,
	'graph_templates.php' => 10,
	'graph_templates_inputs.php' => 10,
	'graph_templates_items.php' => 10,
	'graph_view.php' => 7,
	'graph_realtime.php' => 7,
	'graphs.php' => 5,
	'graphs_items.php' => 5,
	'graphs_new.php' => 5,
	'host.php' => 3,
	'host_templates.php' => 12,
	'index.php' => 8,
	'managers.php' => 15,
	'rrdcleaner.php' => 15,
	'settings.php' => 15,
	'data_queries.php' => 13,
	'templates_export.php' => 16,
	'templates_import.php' => 17,
	'tree.php' => 4,
	'user_admin.php' => 1,
	'user_domains.php' => 1,
	'user_group_admin.php' => 1,
	'utilities.php' => 15,
	'vdef.php' => 14,
	'smtp_servers.php' => 8,
	'email_templates.php' => 8,
	'event_queue.php' => 8,
	'smtp_queue.php' => 8,
	'logout.php' => -1,
	'auth_profile.php' => 20,
	'auth_changepassword.php' => -1,
	'reports_user.php' => 21,
	'reports_admin.php' => 22,
	'automation_graph_rules.php' => 23,
	'automation_tree_rules.php' => 23,
	'automation_templates.php' => 23,
	'automation_networks.php' => 23,
	'automation_devices.php' => 23,
	'automation_snmp.php' => 23,
	'color_templates.php' => 5,
	'color_templates_items.php' => 5,
	'aggregate_templates.php' => 5,
	'aggregate_graphs.php' => 5,
	'aggregate_items.php' => 5,
	'permission_denied.php' => -1
);

$hash_type_codes = array(
	'round_robin_archive' => '15',
	'cdef' => '05',
	'cdef_item' => '14',
	'gprint_preset' => '06',
	'data_input_method' => '03',
	'data_input_field' => '07',
	'data_template' => '01',
	'data_template_item' => '08',
	'graph_template' => '00',
	'graph_template_item' => '10',
	'graph_template_input' => '09',
	'data_query' => '04',
	'data_query_graph' => '11',
	'data_query_sv_graph' => '12',
	'data_query_sv_data_source' => '13',
	'host_template' => '02',
	'vdef' => '18',
	'vdef_item' => '19',
	'data_source_profile' => '20'
);

$hash_version_codes = array(
	'0.8.4'  => '0000',
	'0.8.5'  => '0001',
	'0.8.5a' => '0002',
	'0.8.6'  => '0003',
	'0.8.6a' => '0004',
	'0.8.6b' => '0005',
	'0.8.6c' => '0006',
	'0.8.6d' => '0007',
	'0.8.6e' => '0008',
	'0.8.6f' => '0009',
	'0.8.6g' => '0010',
	'0.8.6h' => '0011',
	'0.8.6i' => '0012',
	'0.8.6j' => '0013',
	'0.8.7'  => '0014',
	'0.8.7a' => '0015',
	'0.8.7b' => '0016',
	'0.8.7c' => '0017',
	'0.8.7d' => '0018',
	'0.8.7e' => '0019',
	'0.8.7f' => '0020',
	'0.8.7g' => '0021',
	'0.8.7h' => '0022',
	'0.8.7i' => '0023',
	'0.8.8'  => '0024',
	'0.8.8a' => '0024',
	'0.8.8b' => '0024',
	'0.8.8c' => '0025',
	'0.8.8d' => '0025',
	'0.8.8e' => '0025',
	'0.8.8f' => '0025',
	'0.8.8g' => '0025',
	'1.0.0'  => '0027'
);

$hash_type_names = array(
	'cdef'                 => 'CDEF',
	'cdef_item'            => 'CDEF Item',
	'gprint_preset'        => 'GPRINT Preset',
	'data_template'        => 'Data Template',
	'data_input_method'    => 'Data Input Method',
	'data_input_field'     => 'Data Input Field',
	'data_source_profile'  => 'Data Source Profile',
	'data_template_item'   => 'Data Template Item',
	'graph_template'       => 'Graph Template',
	'graph_template_item'  => 'Graph Template Item',
	'graph_template_input' => 'Graph Template Input',
	'data_query'           => 'Data Query',
	'host_template'        => 'Device Template',
	'vdef'                 => 'VDEF',
	'vdef_item'            => 'VDEF Item'
);

$host_struc = array(
	'host_template_id',
	'description',
	'hostname',
	'notes',
	'snmp_community',
	'snmp_version',
	'snmp_username',
	'snmp_password',
	'snmp_auth_protocol',
	'snmp_priv_passphrase',
	'snmp_priv_protocol',
	'snmp_context',
	'snmp_port',
	'snmp_timeout',
	'max_oids',
	'device_threads',
	'availability_method',
	'ping_method',
	'ping_port',
	'ping_timeout',
	'ping_retries',
	'disabled',
	'status',
	'status_event_count',
	'status_fail_date',
	'status_rec_date',
	'status_last_error',
	'min_time',
	'max_time',
	'cur_time',
	'avg_time',
	'total_polls',
	'failed_polls',
	'availability'
);

$graph_timespans = array(
	GT_LAST_HALF_HOUR => 'Last Half Hour',
	GT_LAST_HOUR      => 'Last Hour',
	GT_LAST_2_HOURS   => 'Last 2 Hours',
	GT_LAST_4_HOURS   => 'Last 4 Hours',
	GT_LAST_6_HOURS   => 'Last 6 Hours',
	GT_LAST_12_HOURS  => 'Last 12 Hours',
	GT_LAST_DAY       => 'Last Day',
	GT_LAST_2_DAYS    => 'Last 2 Days',
	GT_LAST_3_DAYS    => 'Last 3 Days',
	GT_LAST_4_DAYS    => 'Last 4 Days',
	GT_LAST_WEEK      => 'Last Week',
	GT_LAST_2_WEEKS   => 'Last 2 Weeks',
	GT_LAST_MONTH     => 'Last Month',
	GT_LAST_2_MONTHS  => 'Last 2 Months',
	GT_LAST_3_MONTHS  => 'Last 3 Months',
	GT_LAST_4_MONTHS  => 'Last 4 Months',
	GT_LAST_6_MONTHS  => 'Last 6 Months',
	GT_LAST_YEAR      => 'Last Year',
	GT_LAST_2_YEARS   => 'Last 2 Years',
	GT_DAY_SHIFT      => 'Day Shift',
	GT_THIS_DAY       => 'This Day',
	GT_THIS_WEEK      => 'This Week',
	GT_THIS_MONTH     => 'This Month',
	GT_THIS_YEAR      => 'This Year',
	GT_PREV_DAY       => 'Previous Day',
	GT_PREV_WEEK      => 'Previous Week',
	GT_PREV_MONTH     => 'Previous Month',
	GT_PREV_YEAR      => 'Previous Year'
);

$graph_timeshifts = array(
	GTS_HALF_HOUR => '30 Min',
	GTS_1_HOUR    => '1 Hour',
	GTS_2_HOURS   => '2 Hours',
	GTS_4_HOURS   => '4 Hours',
	GTS_6_HOURS   => '6 Hours',
	GTS_12_HOURS  => '12 Hours',
	GTS_1_DAY     => '1 Day',
	GTS_2_DAYS    => '2 Days',
	GTS_3_DAYS    => '3 Days',
	GTS_4_DAYS    => '4 Days',
	GTS_1_WEEK    => '1 Week',
	GTS_2_WEEKS   => '2 Weeks',
	GTS_1_MONTH   => '1 Month',
	GTS_2_MONTHS  => '2 Months',
	GTS_3_MONTHS  => '3 Months',
	GTS_4_MONTHS  => '4 Months',
	GTS_6_MONTHS  => '6 Months',
	GTS_1_YEAR    => '1 Year',
	GTS_2_YEARS   => '2 Years'
);

$graph_weekdays = array(
	WD_SUNDAY    => date('l', strtotime('Sunday')),
	WD_MONDAY    => date('l', strtotime('Monday')),
	WD_TUESDAY   => date('l', strtotime('Tuesday')),
	WD_WEDNESDAY => date('l', strtotime('Wednesday')),
	WD_THURSDAY  => date('l', strtotime('Thursday')),
	WD_FRIDAY    => date('l', strtotime('Friday')),
	WD_SATURDAY  => date('l', strtotime('Saturday'))
);

$graph_dateformats = array(
	GD_MO_D_Y => 'Month Number, Day, Year',
	GD_MN_D_Y => 'Month Name, Day, Year',
	GD_D_MO_Y => 'Day, Month Number, Year',
	GD_D_MN_Y => 'Day, Month Name, Year',
	GD_Y_MO_D => 'Year, Month Number, Day',
	GD_Y_MN_D => 'Year, Month Name, Day'
);

$graph_datechar = array(
	GDC_HYPHEN => '-',
	GDC_SLASH => '/'
);

$dsstats_refresh_interval = array(
	'boost' => 'After Boost',
	'60'    => '1 Hour',
	'120'   => '2 Hours',
	'180'   => '3 Hours',
	'240'   => '4 Hours',
	'300'   => '5 Hours',
	'360'   => '6 Hours'
);

$dsstats_max_memory = array(
	'32'   => '32 MBytes',
	'64'   => '64 MBytes',
	'128'  => '128 MBytes',
	'256'  => '256 MBytes',
	'512'  => '512 MBytes',
	'1024' => '1 GBytes',
	'1536' => '1.5 GBytes',
	'2048' => '2 GBytes',
	'3072' => '3 GBytes'
);

$dsstats_hourly_avg = array(
	'60'  => '1 Hour',
	'120' => '2 Hours',
	'180' => '3 Hours',
	'240' => '4 Hours',
	'300' => '5 Hours',
	'360' => '6 Hours'
);

$boost_max_rows_per_select = array(
	'2000'   => '2,000 Data Source Items',
	'5000'   => '5,000 Data Source Items',
	'10000'  => '10,000 Data Source Items',
	'15000'  => '15,000 Data Source Items',
	'25000'  => '25,000 Data Source Items',
	'50000'  => '50,000 Data Source Items (Default)',
	'100000' => '100,000 Data Source Items',
	'200000' => '200,000 Data Source Items',
	'400000' => '400,000 Data Source Items'
);

$boost_utilities_interval = array(
	'999999' => 'Disabled',
	'5'      => '5 Seconds',
	'10'     => '10 Seconds',
	'15'     => '15 Seconds',
	'20'     => '20 Seconds',
	'30'     => '30 Seconds',
	'60'     => '1 Minute',
	'300'    => '5 Minutes'
);

$boost_refresh_interval = array(
	'30'  => '30 Minutes',
	'60'  => '1 Hour',
	'120' => '2 Hours',
	'240' => '4 Hours',
	'360' => '6 Hours'
);

$boost_max_runtime = array(
	'1200' => '20 Minutes',
	'2400' => '40 Minutes',
	'3600' => '1 Hour',
	'4800' => '1.5 Hours'
);

$boost_max_memory = array(
	'32'   => '32 MBytes',
	'64'   => '64 MBytes',
	'128'  => '128 MBytes',
	'256'  => '256 MBytes',
	'512'  => '512 MBytes',
	'1024' => '1 GBytes',
	'1536' => '1.5 GBytes',
	'2048' => '2 GBytes',
	'3072' => '3 GBytes'
);

$realtime_window = array(
	30   => '30 Seconds',
	45   => '45 Seconds',
	60   => '1 Minute',
	90   => '1.5 Minutes',
	120  => '2 Minutes',
	300  => '5 Minutes',
	600  => '10 Minutes',
	1200 => '20 Minutes',
	1800 => '30 Minutes',
	3600 => '1 Hour'
);

$realtime_refresh = array(
	5   => '5 Seconds',
	10  => '10 Seconds',
	15  => '15 Seconds',
	20  => '20 Seconds',
	30  => '30 Seconds',
	60  => '1 Minute',
	120 => '2 Minutes'
);

$attachment_sizes = array(
	1048576   => '1 Megabyte',
	2097152   => '2 Megabytes',
	4194304   => '4 Megabytes',
	10485760  => '10 Megabytes',
	20971520  => '20 Megabytes',
	52428800  => '50 Megabytes',
	104857600 => '100 Megabytes'
);

$reports_actions = array(
	REPORTS_SEND_NOW  => 'Send Now',
	REPORTS_DUPLICATE => 'Duplicate',
	REPORTS_ENABLE    => 'Enable',
	REPORTS_DISABLE   => 'Disable',
	REPORTS_DELETE    => 'Delete',
);

if (is_realm_allowed(22)) {
	$reports_actions[REPORTS_OWN] = 'Take Ownership';
}

$attach_types = array(
	REPORTS_TYPE_INLINE_PNG => 'Inline PNG Image',
	#REPORTS_TYPE_INLINE_JPG => 'Inline JPEG Image',
	#REPORTS_TYPE_ATTACH_PDF => 'PDF Attachment',
);

if (extension_loaded(REPORTS_EXTENSION_GD)) {
	$attach_types[REPORTS_TYPE_INLINE_JPG] = 'Inline JPEG Image';
	$attach_types[REPORTS_TYPE_INLINE_GIF] = 'Inline GIF Image';
}

$attach_types[REPORTS_TYPE_ATTACH_PNG] = 'Attached PNG Image';

if (extension_loaded(REPORTS_EXTENSION_GD)) {
	$attach_types[REPORTS_TYPE_ATTACH_JPG] = 'Attached JPEG Image';
	$attach_types[REPORTS_TYPE_ATTACH_GIF] = 'Attached GIF Image';
}

if (read_config_option('reports_allow_ln') != '') {
	$attach_types[REPORTS_TYPE_INLINE_PNG_LN] = 'Inline PNG Image, LN Style';
	if (extension_loaded(REPORTS_EXTENSION_GD)) {
		$attach_types[REPORTS_TYPE_INLINE_JPG_LN] = 'Inline JPEG Image, LN Style';
		$attach_types[REPORTS_TYPE_INLINE_GIF_LN] = 'Inline GIF Image, LN Style';
	}
}

$item_types = array(
	REPORTS_ITEM_TEXT  => 'Text',
	REPORTS_ITEM_TREE  => 'Tree',
	REPORTS_ITEM_GRAPH => 'Graph',
	REPORTS_ITEM_HR    => 'Horizontal Rule'
);

$alignment = array(
	REPORTS_ALIGN_LEFT   => 'left',
	REPORTS_ALIGN_CENTER => 'center',
	REPORTS_ALIGN_RIGHT  => 'right'
);

$reports_interval = array(
	REPORTS_SCHED_INTVL_MINUTE        => 'Minute(s)',
	REPORTS_SCHED_INTVL_HOUR          => 'Hour(s)',
	REPORTS_SCHED_INTVL_DAY           => 'Day(s)',
	REPORTS_SCHED_INTVL_WEEK          => 'Week(s)',
	REPORTS_SCHED_INTVL_MONTH_DAY     => 'Month(s), Day of Month',
	REPORTS_SCHED_INTVL_MONTH_WEEKDAY => 'Month(s), Day of Week',
	REPORTS_SCHED_INTVL_YEAR          => 'Year(s)',
);

$agg_graph_types = array(
	AGGREGATE_GRAPH_TYPE_KEEP         => 'Keep Graph Types',
	AGGREGATE_GRAPH_TYPE_KEEP_STACKED => 'Keep Type and STACK',
	GRAPH_ITEM_TYPE_STACK             => 'Convert to AREA/STACK Graph',
	GRAPH_ITEM_TYPE_LINE1             => 'Convert to LINE1 Graph',
	GRAPH_ITEM_TYPE_LINE2             => 'Convert to LINE2 Graph',
	GRAPH_ITEM_TYPE_LINE3             => 'Convert to LINE3 Graph',
);

$agg_totals = array(
	AGGREGATE_TOTAL_NONE => 'No Totals',
	AGGREGATE_TOTAL_ALL  => 'Print all Legend Items',
	AGGREGATE_TOTAL_ONLY => 'Print totaling Legend Items Only',
);

$agg_totals_type = array(
	AGGREGATE_TOTAL_TYPE_SIMILAR => 'Total Similar Data Sources',
	AGGREGATE_TOTAL_TYPE_ALL     => 'Total All Data Sources',
);

$agg_order_types = array(
	AGGREGATE_ORDER_NONE      => 'No Reordering',
	AGGREGATE_ORDER_DS_GRAPH  => 'Data Source, Graph',
	AGGREGATE_ORDER_GRAPH_DS  => 'Graph, Data Source',
);

# operators for use with SQL/pattern matching
$automation_op_array = array(
	'display' => array(
		AUTOMATION_OP_NONE         => 'None',
		AUTOMATION_OP_CONTAINS     => 'contains',
		AUTOMATION_OP_CONTAINS_NOT => 'does not contain',
		AUTOMATION_OP_BEGINS       => 'begins with',
		AUTOMATION_OP_BEGINS_NOT   => 'does not begin with',
		AUTOMATION_OP_ENDS         => 'ends with',
		AUTOMATION_OP_ENDS_NOT     => 'does not end with',
		AUTOMATION_OP_MATCHES      => 'matches',
		AUTOMATION_OP_MATCHES_NOT  => 'does not match with',
		AUTOMATION_OP_LT           => 'is less than',
		AUTOMATION_OP_LE           => 'is less than or equal',
		AUTOMATION_OP_GT           => 'is greater than',
		AUTOMATION_OP_GE           => 'is greater than or equal',
		AUTOMATION_OP_UNKNOWN      => 'is unknown',
		AUTOMATION_OP_NOT_UNKNOWN  => 'is not unknown',
		AUTOMATION_OP_EMPTY        => 'is empty',
		AUTOMATION_OP_NOT_EMPTY    => 'is not empty',
		AUTOMATION_OP_REGEXP       => 'matches regular expression',
		AUTOMATION_OP_NOT_REGEXP   => 'does not match regular expression',
	),
	'op' => array(
		AUTOMATION_OP_NONE          => '',
		AUTOMATION_OP_CONTAINS      => 'LIKE',
		AUTOMATION_OP_CONTAINS_NOT  => 'NOT LIKE',
		AUTOMATION_OP_BEGINS        => 'LIKE',
		AUTOMATION_OP_BEGINS_NOT    => 'NOT LIKE',
		AUTOMATION_OP_ENDS          => 'LIKE',
		AUTOMATION_OP_ENDS_NOT      => 'NOT LIKE',
		AUTOMATION_OP_MATCHES       => '<=>',
		AUTOMATION_OP_MATCHES_NOT   => '<>',
		AUTOMATION_OP_LT            => '<',
		AUTOMATION_OP_LE            => '<=',
		AUTOMATION_OP_GT            => '>',
		AUTOMATION_OP_GE            => '>=',
		AUTOMATION_OP_UNKNOWN       => 'IS NULL',
		AUTOMATION_OP_NOT_UNKNOWN   => 'IS NOT NULL',
		AUTOMATION_OP_EMPTY         => "LIKE ''",
		AUTOMATION_OP_NOT_EMPTY     => "NOT LIKE ''",
		AUTOMATION_OP_REGEXP        => 'REGEXP',
		AUTOMATION_OP_NOT_REGEXP    => 'NOT REGEXP',
	),
	'binary' => array(
		AUTOMATION_OP_NONE          => false,
		AUTOMATION_OP_CONTAINS      => true,
		AUTOMATION_OP_CONTAINS_NOT  => true,
		AUTOMATION_OP_BEGINS        => true,
		AUTOMATION_OP_BEGINS_NOT    => true,
		AUTOMATION_OP_ENDS          => true,
		AUTOMATION_OP_ENDS_NOT      => true,
		AUTOMATION_OP_MATCHES       => true,
		AUTOMATION_OP_MATCHES_NOT   => true,
		AUTOMATION_OP_LT            => true,
		AUTOMATION_OP_LE            => true,
		AUTOMATION_OP_GT            => true,
		AUTOMATION_OP_GE            => true,
		AUTOMATION_OP_UNKNOWN       => false,
		AUTOMATION_OP_NOT_UNKNOWN   => false,
		AUTOMATION_OP_EMPTY         => false,
		AUTOMATION_OP_NOT_EMPTY     => false,
		AUTOMATION_OP_REGEXP        => true,
		AUTOMATION_OP_NOT_REGEXP    => true,
	),
	'pre' => array(
		AUTOMATION_OP_NONE          => '',
		AUTOMATION_OP_CONTAINS      => '%',
		AUTOMATION_OP_CONTAINS_NOT  => '%',
		AUTOMATION_OP_BEGINS        => '',
		AUTOMATION_OP_BEGINS_NOT    => '',
		AUTOMATION_OP_ENDS          => '%',
		AUTOMATION_OP_ENDS_NOT      => '%',
		AUTOMATION_OP_MATCHES       => '',
		AUTOMATION_OP_MATCHES_NOT   => '',
		AUTOMATION_OP_LT            => '',
		AUTOMATION_OP_LE            => '',
		AUTOMATION_OP_GT            => '',
		AUTOMATION_OP_GE            => '',
		AUTOMATION_OP_UNKNOWN       => '',
		AUTOMATION_OP_NOT_UNKNOWN   => '',
		AUTOMATION_OP_EMPTY         => '',
		AUTOMATION_OP_NOT_EMPTY     => '',
		AUTOMATION_OP_REGEXP        => '',
		AUTOMATION_OP_NOT_REGEXP    => '',
	),
	'post' => array(
		AUTOMATION_OP_NONE          => '',
		AUTOMATION_OP_CONTAINS      => '%',
		AUTOMATION_OP_CONTAINS_NOT  => '%',
		AUTOMATION_OP_BEGINS        => '%',
		AUTOMATION_OP_BEGINS_NOT    => '%',
		AUTOMATION_OP_ENDS          => '',
		AUTOMATION_OP_ENDS_NOT      => '',
		AUTOMATION_OP_MATCHES       => '',
		AUTOMATION_OP_MATCHES_NOT   => '',
		AUTOMATION_OP_LT            => '',
		AUTOMATION_OP_LE            => '',
		AUTOMATION_OP_GT            => '',
		AUTOMATION_OP_GE            => '',
		AUTOMATION_OP_UNKNOWN       => '',
		AUTOMATION_OP_NOT_UNKNOWN   => '',
		AUTOMATION_OP_EMPTY         => '',
		AUTOMATION_OP_NOT_EMPTY     => '',
		AUTOMATION_OP_REGEXP        => '',
		AUTOMATION_OP_NOT_REGEXP    => '',
	)
);

$automation_oper = array(
	AUTOMATION_OPER_NULL            => '',
	AUTOMATION_OPER_AND             => 'AND',
	AUTOMATION_OPER_OR              => 'OR',
	AUTOMATION_OPER_LEFT_BRACKET    => '(',
	AUTOMATION_OPER_RIGHT_BRACKET   => ')',
);

$automation_tree_item_types  = array(
	TREE_ITEM_TYPE_GRAPH => 'Graph',
	TREE_ITEM_TYPE_HOST  => 'Host'
);

$automation_tree_header_types  = array(
	AUTOMATION_TREE_ITEM_TYPE_STRING => 'Fixed String',
);

$automation_frequencies = array(
	'disabled' => 'Disabled',
	'60'       => 'Every 1 Hour',
	'120'      => 'Every 2 Hours',
	'240'      => 'Every 4 Hours',
	'360'      => 'Every 6 Hours',
	'480'      => 'Every 8 Hours',
	'720'      => 'Every 12 Hours',
	'1440'     => 'Every Day',
	'10080'    => 'Every Week',
	'20160'    => 'Every 2 Weeks',
	'40320'    => 'Every 4 Weeks'
);

api_plugin_hook('config_arrays');

