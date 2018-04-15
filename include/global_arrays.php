<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

global $menu, $menu_glyphs;

/* Array of Cacti versions and template hash codes
   Remember to add every version here. */
$cacti_version_codes = array(
	'0.8'    => 'NaN',
	'0.8.1'  => 'NaN',
	'0.8.2'  => 'NaN',
	'0.8.2a' => 'NaN',
	'0.8.3'  => 'NaN',
	'0.8.3a' => 'NaN',
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
	'0.8.8h' => '0025',
	'1.0.0'  => '0100',
	'1.0.1'  => '0100',
	'1.0.2'  => '0100',
	'1.0.3'  => '0100',
	'1.0.4'  => '0100',
	'1.0.5'  => '0100',
	'1.0.6'  => '0100',
	'1.1.0'  => '0100',
	'1.1.1'  => '0100',
	'1.1.2'  => '0100',
	'1.1.3'  => '0100',
	'1.1.4'  => '0100',
	'1.1.5'  => '0100',
	'1.1.6'  => '0101',
	'1.1.7'  => '0101',
	'1.1.8'  => '0101',
	'1.1.9'  => '0101',
	'1.1.10' => '0101',
	'1.1.11' => '0101',
	'1.1.12' => '0101',
	'1.1.13' => '0101',
	'1.1.14' => '0101',
	'1.1.15' => '0101',
	'1.1.16' => '0101',
	'1.1.17' => '0101',
	'1.1.18' => '0101',
	'1.1.19' => '0101',
	'1.1.20' => '0101',
	'1.1.21' => '0101',
	'1.1.22' => '0101',
	'1.1.23' => '0101',
	'1.1.24' => '0101',
	'1.1.25' => '0101',
	'1.1.26' => '0101',
	'1.1.27' => '0101',
	'1.1.28' => '0101',
	'1.1.29' => '0101',
	'1.1.30' => '0101',
	'1.1.31' => '0101',
	'1.1.32' => '0101',
	'1.1.33' => '0101',
	'1.1.34' => '0101',
	'1.1.35' => '0101',
	'1.1.36' => '0101',
	'1.1.37' => '0101',
	'1.1.38' => '0101',
);

$messages = array(
	1  => array(
		'message' => __('Save Successful.'),
		'type' => 'info'),
	2  => array(
		'message' => __('Save Failed.'),
		'type' => 'error'),
	3  => array(
		'message' => __('Save Failed due to field input errors (Check red fields).'),
		'type' => 'error'),
	4  => array(
		'message' => __('Passwords do not match, please retype.'),
		'type' => 'error'),
	5  => array(
		'message' => __('You must select at least one field.'),
		'type' => 'error'),
	6  => array(
		'message' => __('You must have built in user authentication turned on to use this feature.'),
		'type' => 'error'),
	7  => array(
		'message' => __('XML parse error.'),
		'type' => 'error'),
	8  => array(
		'message' => __('The directory highlighted does not exist.  Please enter a valid directory.'),
		'type' => 'error'),
	9  => array(
		'message' => __('The Cacti log file must have the extension \'.log\''),
		'type' => 'error'),
	11  => array(
		'message' => __('Data Source does not exist.'),
		'type' => 'error'),
	12 => array(
		'message' => __('Username already in use.'),
		'type' => 'error'),
	13  => array(
		'message' => __('The SNMP v3 Privacy Passphrases do not match'),
		'type' => 'error'),
	14  => array(
		'message' => __('The SNMP v3 Authentication Passphrases do not match'),
		'type' => 'error'),
	15 => array(
		'message' => __('XML: Cacti version does not exist.'),
		'type' => 'error'),
	16 => array(
		'message' => __('XML: Hash version does not exist.'),
		'type' => 'error'),
	17 => array(
		'message' => __('XML: Generated with a newer version of Cacti.'),
		'type' => 'error'),
	18 => array(
		'message' => __('XML: Cannot locate type code.'),
		'type' => 'error'),
	19 => array(
		'message' => __('Username already exists.'),
		'type' => 'error'),
	20 => array(
		'message' => __('Username change not permitted for designated template or guest user.'),
		'type' => 'error'),
	21 => array(
		'message' => __('User delete not permitted for designated template or guest user.'),
		'type' => 'error'),
	22 => array(
		'message' => __('User delete not permitted for designated graph export user.'),
		'type' => 'error'),
	23 => array(
		'message' => __('Data Template includes deleted Data Source Profile.  Please resave the Data Template with an existing Data Source Profile.'),
		'type' => 'error'),
	24 => array(
		'message' => __('Graph Template includes deleted GPrint Prefix.  Please run database repair script to identify and/or correct.'),
		'type' => 'error'),
	25 => array(
		'message' => __('Graph Template includes deleted CDEFs.  Please run database repair script to identify and/or correct.'),
		'type' => 'error'),
	26 => array(
		'message' => __('Graph Template includes deleted Data Input Method.  Please run database repair script to identify.'),
		'type' => 'error'),
	27 => array(
		'message' => __('Data Template not found during Export.  Please run database repair script to identify.'),
		'type' => 'error'),
	28 => array(
		'message' => __('Device Template not found during Export.  Please run database repair script to identify.'),
		'type' => 'error'),
	29 => array(
		'message' => __('Data Query not found during Export.  Please run database repair script to identify.'),
		'type' => 'error'),
	30 => array(
		'message' => __('Graph Template not found during Export.  Please run database repair script to identify.'),
		'type' => 'error'),
	31 => array(
		'message' => __('Graph not found.  Either it has been deleted or your database needs repair.'),
		'type' => 'error'),
	32 => array(
		'message' => __('SNMPv3 Auth Passphrases must be 8 characters or greater.'),
		'type' => 'error'),
	33 => array(
		'message' => __('Some Graphs not updated. Unable to change device for Data Query based Graphs.'),
		'type' => 'error'),
	34 => array(
		'message' => __('Unable to change device for Data Query based Graphs.'),
		'type' => 'error'),
	35 => array(
		'message' => __('Some settings not saved. Check messages below.  Check red fields for errors.'),
		'type' => 'error'),
	36 => array(
		'message' => __('The file highlighted does not exist.  Please enter a valid file name.'),
		'type' => 'error'),
	37 => array(
		'message' => __('All User Settings have been returned to their default values.'),
		'type' => 'info'),
	38 => array(
		'message' => __('Suggested Field Name was not entered.  Please enter a field name and try again.'),
		'type' => 'error'),
	39 => array(
		'message' => __('Suggested Value was not entered.  Please enter a suggested value and try again.'),
		'type' => 'error'),
	'clog_invalid' => array(
		'message' => __('Log file specified is not a Cacti log or archive file.'),
		'type' => 'info'),
	'clog_remove' => array(
		'message' => __('Log file specified was Cacti archive file and was removed.'),
		'type' => 'info'),
	'clog_purged' => array(
		'message' => __('Cacti log purged successfully'),
		'type' => 'info'),
	'password_change' => array(
		'message' => __('If you force a password change, you must also allow the user to change their password.'),
		'type' => 'error'),
	'nopassword' => array(
		'message' => __('You are not allowed to change your password.'),
		'type' => 'error'),
	'nodomainpassword' => array(
		'message' => __('LDAP/AD based password change not supported.'),
		'type' => 'error'),
	'clog_permissions' => array(
		'message' => __('Unable to clear log, no write permissions'),
		'type' => 'error'),
	'clog_missing' => array(
		'message' => __('Unable to clear log, file does not exist'),
		'type' => 'error'),
	'csrf_timeout' => array(
		'message' => __('CSRF Timeout, refreshing page.'),
		'type' => 'error'),
	'mg_mailtime_invalid' => array(
		'message' => __('Invalid timestamp. Select timestamp in the future.'),
		'type'    => 'error'),
	'poller_sync' => array(
		'message' => __('Data Collector(s) synchronized for offline operation'),
		'type' => 'info'),
	'poller_notfound' => array(
		'message' => __('Data Collector(s) not found when attempting synchronization'),
		'type' => 'error'),
	'poller_noconnect' => array(
		'message' => __('Unable to establish MySQL connection with remote Data Collector.'),
		'type' => 'error'),
	'poller_nosync' => array(
		'message' => __('Data Collector synchronization must be initiated from the main Cacti server.'),
		'type' => 'error'),
	'reports_save' => array(
		'message' => __('Report Saved'),
		'type' => 'info'),
	'reports_save_failed' => array(
		'message' => __('Report Save Failed'),
		'type' => 'error'),
	'reports_item_save' => array(
		'message' => __('Report Item Saved'),
		'type' => 'info'),
	'reports_item_save_failed' => array(
		'message' => __('Report Item Save Failed'),
		'type' => 'error'),
	'resource_cache_rebuild' => array(
		'message' => __('Poller Resource Cache cleared.  Main Data Collector will rebuild at the next poller start, and Remote Data Collectors will sync afterwards.'),
		'type' => 'info'),
	'custom_error' => array(
		'message' => __('Unexpected error occurred'),
		'type' => 'error')
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
		'message' => $_SESSION['reports_message'],
		'type' => 'info'
	);
}

if (isset($_SESSION['reports_error']) && $_SESSION['reports_error'] != '') {
	$messages['reports_error'] = array(
		'message' => $_SESSION['reports_error'],
		'type' => 'error'
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
	CVDEF_ITEM_TYPE_FUNCTION => __('Function'),
	CVDEF_ITEM_TYPE_SPEC_DS  => __('Special Data Source'),
	CVDEF_ITEM_TYPE_STRING   => __('Custom String'),
);

$custom_vdef_data_source_types = array( // this may change as soon as RRDtool supports math in VDEF, until then only reference to CDEF may help
	'CURRENT_DATA_SOURCE' => __('Current Graph Item Data Source'),
);

$input_types = array(
	DATA_INPUT_TYPE_SNMP                => __('SNMP Get'), // Action 0:
	DATA_INPUT_TYPE_SNMP_QUERY          => __('SNMP Query'),
	DATA_INPUT_TYPE_SCRIPT              => __('Script/Command'),  // Action 1:
	DATA_INPUT_TYPE_SCRIPT_QUERY        => __('Script Query'), // Action 1:
	DATA_INPUT_TYPE_PHP_SCRIPT_SERVER   => __('Script Server'),
	DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER => __('Script Query - Script Server')
);

$input_types_script = array(
	DATA_INPUT_TYPE_SCRIPT              => __('Script/Command'),  // Action 1:
	DATA_INPUT_TYPE_PHP_SCRIPT_SERVER   => __('Script Server'),
);

$reindex_types = array(
	DATA_QUERY_AUTOINDEX_NONE               => __('None'),
	DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME   => __('Uptime'),
	DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE   => __('Index Count'),
	DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION => __('Verify All')
);

$reindex_types_tips = array(
	DATA_QUERY_AUTOINDEX_NONE               => __('All Re-Indexing will be manual or managed through scripts or Device Automation.'),
	DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME   => __('When the Devices SNMP uptime goes backward, a Re-Index will be performed.'),
	DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE   => __('When the Data Query index count changes, a Re-Index will be performed.'),
	DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION => __('Every polling cycle, a Re-Index will be performed.  Very expensive.')
);

$snmp_query_field_actions = array(1 =>
	__('SNMP Field Name (Dropdown)'),
	__('SNMP Field Value (From User)'),
	__('SNMP Output Type (Dropdown)')
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
	'ABSOLUTE',
	'COMPUTE'
);

$rrd_font_render_modes = array(
	RRD_FONT_RENDER_NORMAL  => __('Normal'),
	RRD_FONT_RENDER_LIGHT   => __('Light'),
	RRD_FONT_RENDER_MONO    => __('Mono'),
);

$rrd_graph_render_modes = array(
	RRD_GRAPH_RENDER_NORMAL => __('Normal'),
	RRD_GRAPH_RENDER_MONO   => __('Mono'),
);

$rrd_legend_position = array(
	RRD_LEGEND_POS_NORTH    => __('North'),
	RRD_LEGEND_POS_SOUTH    => __('South'),
	RRD_LEGEND_POS_WEST     => __('West'),
	RRD_LEGEND_POS_EAST     => __('East'),
);

$rrd_textalign = array(
	RRD_ALIGN_NONE          => __('None'),
	RRD_ALIGN_LEFT          => __('Left'),
	RRD_ALIGN_RIGHT         => __('Right'),
	RRD_ALIGN_JUSTIFIED     => __('Justified'),
	RRD_ALIGN_CENTER        => __('Center'),
);

$rrd_legend_direction = array(
	RRD_LEGEND_DIR_TOPDOWN  => __('Top -> Down'),
	RRD_LEGEND_DIR_BOTTOMUP => __('Bottom -> Up'),
);

$rrd_axis_formatters = array(
	'numeric'   => __('Numeric'),
	'timestamp' => __('Timestamp'),
	'duration'  => __('Duration')
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
	GRAPH_ITEM_TYPE_LEGEND_CAMM     => 'LEGEND_CAMM',
	GRAPH_ITEM_TYPE_LINESTACK       => 'LINE:STACK',
	GRAPH_ITEM_TYPE_TIC             => 'TICK',
	GRAPH_ITEM_TYPE_TEXTALIGN       => 'TEXTALIGN',
);

asort($graph_item_types);

$image_types = array(
	1 => 'PNG',
	3 => 'SVG'
);

$snmp_security_levels = array(
	'noAuthNoPriv' => 'noAuthNoPriv',
	'authNoPriv' => 'authNoPriv',
	'authPriv' => 'authPriv'
);

$snmp_versions = array(0 =>
	__('Not In Use'),
	__('Version %d', 1),
	__('Version %d', 2),
	__('Version %d', 3)
);

$snmp_auth_protocols = array(
	'[None]' => __('[None]'),
	'MD5'    => __('MD5'),
	'SHA'    => __('SHA')
);

$snmp_priv_protocols = array(
	'[None]' => __('[None]'),
	'DES'    => __('DES'),
	'AES128' => __('AES')
);

$banned_snmp_strings = array(
	'End of MIB',
	'No Such'
);

$logfile_options = array(1 =>
	__('Logfile Only'),
	__('Logfile and Syslog/Eventlog'),
	__('Syslog/Eventlog Only')
);

$availability_options = array(
	AVAIL_NONE             => __('None'),
	AVAIL_SNMP_AND_PING    => __('Ping and SNMP Uptime'),
	AVAIL_SNMP_OR_PING     => __('Ping or SNMP Uptime'),
	AVAIL_SNMP             => __('SNMP Uptime'),
	AVAIL_SNMP_GET_SYSDESC => __('SNMP Desc'),
	AVAIL_SNMP_GET_NEXT    => __('SNMP getNext'),
	AVAIL_PING             => __('Ping')
);

$ping_methods = array(
	PING_ICMP => __('ICMP Ping'),
	PING_TCP  => __('TCP Ping'),
	PING_UDP  => __('UDP Ping')
);

$logfile_verbosity = array(
	POLLER_VERBOSITY_NONE   => __('NONE - Syslog Only if Selected'),
	POLLER_VERBOSITY_LOW    => __('LOW - Statistics and Errors'),
	POLLER_VERBOSITY_MEDIUM => __('MEDIUM - Statistics, Errors and Results'),
	POLLER_VERBOSITY_HIGH   => __('HIGH - Statistics, Errors, Results and Major I/O Events'),
	POLLER_VERBOSITY_DEBUG  => __('DEBUG - Statistics, Errors, Results, I/O and Program Flow'),
	POLLER_VERBOSITY_DEVDBG => __('DEVEL - Developer DEBUG Level')
);

$poller_options = array(
	1 => 'cmd.php',
	2 => 'spine'
);

if (!file_exists(read_config_option('path_spine')) || !is_executable(read_config_option('path_spine'))) {
	unset($poller_options[2]);
}

$aggregation_levels = array(
	1       => __('Selected Poller Interval'),
	30      => __('%d Seconds', 30),
	60      => __('1 Minute'),
	300     => __('%d Minutes', 5),
	600     => __('%d Minutes', 10),
	900     => __('%d Minutes', 15),
	1800    => __('%d Minutes', 30),
	3600    => __('1 Hour'),
	7200    => __('%d Hours', 2),
	10800   => __('%d Hours', 3),
	14400   => __('%d Hours', 4),
	21600   => __('%d Hours', 6),
	43200   => __('%d Hours', 12),
	86400   => __('Daily')
);

$sampling_intervals = array(
	10    => __('Every %d Seconds', 10),
	15    => __('Every %d Seconds', 15),
	20    => __('Every %d Seconds', 20),
	30    => __('Every %d Seconds', 30),
	60    => __('Every Minute'),
	300   => __('Every %d Minutes', 5),
	600   => __('Every %d Minutes', 10),
	1200  => __('Every %d Minutes', 20),
	1800  => __('Every %d Minutes', 30),
	3600  => __('Every Hour'),
	7200  => __('Every %d Hours', 2),
	14400 => __('Every %d Hours', 4),
	28800 => __('Every %d Hours', 8),
	43200 => __('Every %d Hours', 12),
	86400 => __('Every %1 Day', 1)
);

$heartbeats = array(
	20     => __('%d Seconds', 20),
	30     => __('%d Seconds', 30),
	40     => __('%d Seconds', 40),
	60     => __('%d Minute',  1),
	120    => __('%d Minutes', 2),
	300    => __('%d Minutes', 5),
	600    => __('%d Minutes', 10),
	1200   => __('%d Minutes', 20),
	1800   => __('%d Minutes', 30),
	2400   => __('%d Minutes', 40),
	3600   => __('%d Hour', 1),
	7200   => __('%d Hours', 2),
	14400  => __('%d Hours', 4),
	28800  => __('%d Hours', 8),
	57600  => __('%d Hours', 16),
	86400  => __('%d Day', 1),
	172800 => __('%d Days', 2)
);

$timespans = array(
	3600     => __('%d Hour', 1),
	7200     => __('%d Hours', 2),
	14400    => __('%d Hours', 4),
	21600    => __('%d Hours', 6),
	43200    => __('%d Hours', 12),
	86400    => __('%d Day', 1),
	172800   => __('%d Days', 2),
	345600   => __('%d Days', 4),
	604800   => __('%d Week', 1),
	1209600  => __('%d Weeks', 2),
	1814400  => __('%d Weeks', 3),
	2618784  => __('%d Month', 1),
	5237568  => __('%d Months', 2),
	7856352  => __('%d Months', 3),
	10475136 => __('%d Months', 4),
	15712704 => __('%d Months', 6),
	31536000 => __('%d Year', 1),
	63072000 => __('%d Years', 2)
);

$poller_intervals = array(
	10  => __('Every %d Seconds', 10),
	15  => __('Every %d Seconds', 15),
	20  => __('Every %d Seconds', 20),
	30  => __('Every %d Seconds', 30),
	60  => __('Every Minute'),
	300 => __('Every %d Minutes', 5)
);

$device_threads = array(
	1 => __('1 Thread (default)'),
	2 => __('%d Threads', 2),
	3 => __('%d Threads', 3),
	4 => __('%d Threads', 4),
	5 => __('%d Threads', 5),
	6 => __('%d Threads', 6)
);

$cron_intervals = array(
	60  => __('Every Minute'),
	300 => __('Every %d Minutes', 5)
);

$registered_cacti_names = array(
	'path_cacti'
);

$graph_views = array(1 =>
	__('Tree View'),
	__('List View'),
	__('Preview View')
);

$auth_methods = array(
	0 => __('None'),
	1 => __('Builtin Authentication'),
	2 => __('Web Basic Authentication')
);

if (function_exists('ldap_connect')) {
	$auth_methods[3] = __('LDAP Authentication');
	$auth_methods[4] = __('Multiple LDAP/AD Domains');
}

$domain_types = array(
	'1' => __('LDAP'),
	'2' => __('Active Directory')
);

$auth_realms = get_auth_realms();

$ldap_versions = array(
	2 => __('Version %d', 2),
	3 => __('Version %d', 3)
);

$ldap_encryption = array(
	0 => __('None'),
	1 => __('SSL'),
	2 => __('TLS')
);

$ldap_modes = array(
	0 => __('No Searching'),
	1 => __('Anonymous Searching'),
	2 => __('Specific Searching')
);

$rrdtool_versions = array(
	'rrd-1.3.x' => 'RRDtool 1.3.x',
	'rrd-1.4.x' => 'RRDtool 1.4.x',
	'rrd-1.5.x' => 'RRDtool 1.5.x',
	'rrd-1.6.x' => 'RRDtool 1.6.x',
	'rrd-1.7.x' => 'RRDtool 1.7.x'
);

$i18n_modes = array(
    0 => __('Disabled'),
    1 => __('Enabled'),
    2 => __('Enabled (strict mode)'),
);

$cdef_item_types = array(
	CVDEF_ITEM_TYPE_FUNCTION => __('Function'),
	CVDEF_ITEM_TYPE_OPERATOR => __('Operator'),
	CVDEF_ITEM_TYPE_SPEC_DS  => __('Special Data Source'),
	CVDEF_ITEM_TYPE_CDEF     => __('Another CDEF'),
	CVDEF_ITEM_TYPE_STRING   => __('Custom String')
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
	TREE_ORDERING_INHERIT    => __('Inherit Parent Sorting'),
	TREE_ORDERING_NONE       => __('Manual Ordering (No Sorting)'),
	TREE_ORDERING_ALPHABETIC => __('Alphabetic Ordering'),
	TREE_ORDERING_NATURAL    => __('Natural Ordering'),
	TREE_ORDERING_NUMERIC    => __('Numeric Ordering')
);

$tree_item_types = array(
	TREE_ITEM_TYPE_HEADER => __('Header'),
	TREE_ITEM_TYPE_GRAPH  => __('Graph'),
	TREE_ITEM_TYPE_HOST   => __('Device')
);

$host_group_types = array(
	HOST_GROUPING_GRAPH_TEMPLATE   => __('Graph Template'),
	HOST_GROUPING_DATA_QUERY_INDEX => __('Data Query Index')
);

$custom_data_source_types = array(
	'CURRENT_DATA_SOURCE'            => __('Current Graph Item Data Source'),
	'CURRENT_DATA_SOURCE_PI'         => __('Current Graph Item Polling Interval'),
	'ALL_DATA_SOURCES_NODUPS'        => __('All Data Sources (Do not Include Duplicates)'),
	'ALL_DATA_SOURCES_DUPS'          => __('All Data Sources (Include Duplicates)'),
	'SIMILAR_DATA_SOURCES_NODUPS'    => __('All Similar Data Sources (Do not Include Duplicates)'),
	'SIMILAR_DATA_SOURCES_NODUPS_PI' => __('All Similar Data Sources (Do not Include Duplicates) Polling Interval'),
	'SIMILAR_DATA_SOURCES_DUPS'      => __('All Similar Data Sources (Include Duplicates)'),
	'CURRENT_DS_MINIMUM_VALUE'       => __('Current Data Source Item: Minimum Value'),
	'CURRENT_DS_MAXIMUM_VALUE'       => __('Current Data Source Item: Maximum Value'),
	'CURRENT_GRAPH_MINIMUM_VALUE'    => __('Graph: Lower Limit'),
	'CURRENT_GRAPH_MAXIMUM_VALUE'    => __('Graph: Upper Limit'),
	'COUNT_ALL_DS_NODUPS'            => __('Count of All Data Sources (Do not Include Duplicates)'),
	'COUNT_ALL_DS_DUPS'              => __('Count of All Data Sources (Include Duplicates)'),
	'COUNT_SIMILAR_DS_NODUPS'        => __('Count of All Similar Data Sources (Do not Include Duplicates)'),
	'COUNT_SIMILAR_DS_DUPS'	         => __('Count of All Similar Data Sources (Include Duplicates)')
);

if ($config['poller_id'] == 1 || $config['connection'] == 'online') {
	$menu = array(
		__('Create') => array(
			'graphs_new.php' => __('New Graphs')
			),
		__('Management') => array(
			'host.php'             => __('Devices'),
			'sites.php'            => __('Sites'),
			'tree.php'             => __('Trees'),
			'graphs.php'           => __('Graphs'),
			'data_sources.php'     => __('Data Sources'),
			'aggregate_graphs.php' => __('Aggregates'),
			),
		__('Data Collection') => array(
			'pollers.php' => __('Data Collectors'),
			'data_queries.php' => __('Data Queries'),
			'data_input.php'   => __('Data Input Methods')
			),
		__('Templates') => array(
			'host_templates.php'      => __('Device'),
			'graph_templates.php'     => __('Graph'),
			'data_templates.php'      => __('Data Source'),
			'aggregate_templates.php' => __('Aggregate'),
			'color_templates.php'     => __('Color')
			),
		__('Automation') => array(
			'automation_networks.php'    => __('Networks'),
			'automation_devices.php'     => __('Discovered Devices'),
			'automation_templates.php'   => __('Device Rules'),
			'automation_graph_rules.php' => __('Graph Rules'),
			'automation_tree_rules.php'  => __('Tree Rules'),
			'automation_snmp.php'        => __('SNMP Options'),
			),
		__('Presets') => array(
			'data_source_profiles.php' => __('Data Profiles'),
			'cdef.php'                 => __('CDEFs'),
			'vdef.php'                 => __('VDEFs'),
			'color.php'                => __('Colors'),
			'gprint_presets.php'       => __('GPRINTs')
			),
		__('Import/Export') => array(
			'templates_import.php' => __('Import Templates'),
			'templates_export.php' => __('Export Templates')
			),
		__('Configuration')  => array(
			'settings.php'         => __('Settings'),
			'user_admin.php'       => __('Users'),
			'user_group_admin.php' => __('User Groups'),
			'user_domains.php'     => __('User Domains')
			),
		__('Utilities') => array(
			'utilities.php' => __('System Utilities'),
			'links.php'     => __('External Links'),
			)
	);
} else {
	$menu = array(
		__('Management') => array(
			'host.php'             => __('Devices')
			),
		__('Data Collection') => array(
			'pollers.php' => __('Data Collectors')
			),
		__('Configuration')  => array(
			'settings.php' => __('Settings')
			),
		__('Utilities') => array(
			'utilities.php'        => __('System Utilities')
			)
	);
}

$menu_glyphs = array(
	__('Create') => 'fa fa-area-chart',
	__('Management') => 'fa fa-home',
	__('Data Collection') => 'fa fa-database',
	__('Templates') => 'fa fa-clone',
	__('Automation') => 'fa fa-superpowers',
	__('Presets') => 'fa fa-archive',
	__('Import/Export') => 'fa fa-exchange',
	__('Configuration')  => 'fa fa-sliders',
	__('Utilities') => 'fa fa-cogs',
	__('External Links') => 'fa fa-external-link'
);

if ((isset($_SESSION['sess_user_id']))) {
	if (db_table_exists('external_links')) {
		$consoles = db_fetch_assoc('SELECT id, title, extendedstyle
			FROM external_links
			WHERE style="CONSOLE"
			AND enabled="on"
			ORDER BY extendedstyle, sortorder, id');

		if (sizeof($consoles)) {
			foreach ($consoles as $page) {
				if (is_realm_allowed($page['id']+10000)) {
					$menuname = (isset($page['extendedstyle']) && $page['extendedstyle'] != '' ? $page['extendedstyle'] : __('External Links'));
					$menu[$menuname]['link.php?id=' . $page['id']] = $page['title'];
				}
			}
		}
	}
}

$log_tail_lines = array(
	-1    => __('All Lines'),
	10    => __('%d Lines', 10),
	15    => __('%d Lines', 15),
	20    => __('%d Lines', 20),
	50    => __('%d Lines', 50),
	100   => __('%d Lines', 100),
	200   => __('%d Lines', 200),
	500   => __('%d Lines', 500),
	1000  => __('%d Lines', 1000),
	2000  => __('%d Lines', 2000),
	3000  => __('%d Lines', 3000),
	5000  => __('%d Lines', 5000),
	10000 => __('%d Lines', 10000)
);

$item_rows = array(
	10   => '10',
	15   => '15',
	20   => '20',
	25   => '25',
	26   => '26',
	27   => '27',
	30   => '30',
	40   => '40',
	44   => '44',
	45   => '45',
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
	5       => __('%d Seconds', 5),
	10      => __('%d Seconds', 10),
	20      => __('%d Seconds', 20),
	30      => __('%d Seconds', 30),
	60      => __('1 Minute'),
	300     => __('%d Minutes', 5),
	600     => __('%d Minutes', 10),
	9999999 => __('Never')
);

$user_auth_realms = array(
	8    => __('Console Access'),
	7    => __('View Graphs'),
	25   => __('Realtime Graphs'),
	20   => __('Update Profile'),
	24   => __('External Links'),

	1    => __('User Management'),
	15   => __('Settings and Utilities'),
	23   => __('Automation Settings'),

	2    => __('Data Input Methods'),
	13   => __('Data Queries'),

	3    => __('Sites/Devices/Data Sources/Data Collectors'),
	5    => __('Graphs'),
	4    => __('Trees'),
	1043 => __('Remove Spikes from Graphs'),

	9    => __('Data Source Profiles'),
	14   => __('Colors/GPrints/CDEFs/VDEFs'),

	10   => __('Graph Templates'),
	11   => __('Data Templates'),
	12   => __('Device Templates'),

	16   => __('Export Data'),
	17   => __('Import Data'),

	18   => __('Log Management'),
	19   => __('Log Viewing'),

	21   => __('Reports Management'),
	22   => __('Reports Creation')
);

$user_auth_roles = array(
	'Normal User'            => array(7, 19, 20, 22, 24, 25),
	'Template Editor'        => array(8, 2, 9, 10, 11, 12, 13, 14, 16, 17),
	'General Administration' => array(8, 3, 4, 5, 23, 1043),
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
	'graph_realtime.php' => 25,
	'graphs.php' => 5,
	'graphs_items.php' => 5,
	'graphs_new.php' => 5,
	'sites.php' => 3,
	'pollers.php' => 3,
	'host.php' => 3,
	'host_templates.php' => 12,
	'index.php' => 8,
	'managers.php' => 15,
	'rrdcleaner.php' => 15,
	'settings.php' => 15,
	'links.php' => 15,
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
	'spikekill.php' => 1043,
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

$hash_type_names = array(
	'cdef'                 => __('CDEF'),
	'cdef_item'            => __('CDEF Item'),
	'gprint_preset'        => __('GPRINT Preset'),
	'data_template'        => __('Data Template'),
	'data_input_method'    => __('Data Input Method'),
	'data_input_field'     => __('Data Input Field'),
	'data_source_profile'  => __('Data Source Profile'),
	'data_template_item'   => __('Data Template Item'),
	'graph_template'       => __('Graph Template'),
	'graph_template_item'  => __('Graph Template Item'),
	'graph_template_input' => __('Graph Template Input'),
	'data_query'           => __('Data Query'),
	'host_template'        => __('Device Template'),
	'vdef'                 => __('VDEF'),
	'vdef_item'            => __('VDEF Item')
);

$hash_system_data_inputs = array(
	'3eb92bb845b9660a7445cf9740726522', // Get SNMP Data
	'bf566c869ac6443b0c75d1c32b5a350e', // Get SNMP Data (Indexed)
	'80e9e4c4191a5da189ae26d0e237f015', // Get Script Data (Indexed)
	'332111d8b54ac8ce939af87a7eac0c06'  // Get Script Server Data (Indexed)
);


$host_struc = array(
	'host_template_id',
	'description',
	'hostname',
	'site_id',
	'poller_id',
	'notes',
	'snmp_community',
	'snmp_version',
	'snmp_username',
	'snmp_password',
	'snmp_auth_protocol',
	'snmp_priv_passphrase',
	'snmp_priv_protocol',
	'snmp_context',
	'snmp_engine_id',
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
	GT_LAST_HALF_HOUR => __('Last Half Hour'),
	GT_LAST_HOUR      => __('Last Hour'),
	GT_LAST_2_HOURS   => __('Last %d Hours', 2),
	GT_LAST_4_HOURS   => __('Last %d Hours', 4),
	GT_LAST_6_HOURS   => __('Last %d Hours', 6),
	GT_LAST_12_HOURS  => __('Last %d Hours', 12),
	GT_LAST_DAY       => __('Last Day'),
	GT_LAST_2_DAYS    => __('Last %d Days', 2),
	GT_LAST_3_DAYS    => __('Last %d Days', 3),
	GT_LAST_4_DAYS    => __('Last %d Days', 4),
	GT_LAST_WEEK      => __('Last Week'),
	GT_LAST_2_WEEKS   => __('Last %d Weeks', 2),
	GT_LAST_MONTH     => __('Last Month'),
	GT_LAST_2_MONTHS  => __('Last %d Months', 2),
	GT_LAST_3_MONTHS  => __('Last %d Months', 3),
	GT_LAST_4_MONTHS  => __('Last %d Months', 4),
	GT_LAST_6_MONTHS  => __('Last %d Months', 6),
	GT_LAST_YEAR      => __('Last Year'),
	GT_LAST_2_YEARS   => __('Last %d Years', 2),
	GT_DAY_SHIFT      => __('Day Shift'),
	GT_THIS_DAY       => __('This Day'),
	GT_THIS_WEEK      => __('This Week'),
	GT_THIS_MONTH     => __('This Month'),
	GT_THIS_YEAR      => __('This Year'),
	GT_PREV_DAY       => __('Previous Day'),
	GT_PREV_WEEK      => __('Previous Week'),
	GT_PREV_MONTH     => __('Previous Month'),
	GT_PREV_YEAR      => __('Previous Year')
);

$graph_timeshifts = array(
	GTS_HALF_HOUR => __('%d Min', 30),
	GTS_1_HOUR    => __('%d Hour', 1),
	GTS_2_HOURS   => __('%d Hours', 2),
	GTS_4_HOURS   => __('%d Hours', 4),
	GTS_6_HOURS   => __('%d Hours', 6),
	GTS_12_HOURS  => __('%d Hours', 12),
	GTS_1_DAY     => __('%d Day', 1),
	GTS_2_DAYS    => __('%d Days', 2),
	GTS_3_DAYS    => __('%d Days', 3),
	GTS_4_DAYS    => __('%d Days', 4),
	GTS_1_WEEK    => __('%d Week', 1),
	GTS_2_WEEKS   => __('%d Weeks', 2),
	GTS_1_MONTH   => __('%d Month', 1),
	GTS_2_MONTHS  => __('%d Months', 2),
	GTS_3_MONTHS  => __('%d Months', 3),
	GTS_4_MONTHS  => __('%d Months', 4),
	GTS_6_MONTHS  => __('%d Months', 6),
	GTS_1_YEAR    => __('%d Year', 1),
	GTS_2_YEARS   => __('%d Years', 2)
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

$dateformats = array(
	GD_MO_D_Y => __('Month Number, Day, Year'),
	GD_MN_D_Y => __('Month Name, Day, Year'),
	GD_D_MO_Y => __('Day, Month Number, Year'),
	GD_D_MN_Y => __('Day, Month Name, Year'),
	GD_Y_MO_D => __('Year, Month Number, Day'),
	GD_Y_MN_D => __('Year, Month Name, Day')
);

$datechar = array(
	GDC_HYPHEN => '-',
	GDC_SLASH  => '/',
	GDC_DOT    => '.'
);

$dsstats_refresh_interval = array(
	'boost' => __('After Boost'),
	'60'    => __('1 Hour'),
	'120'   => __('%d Hours', 2),
	'180'   => __('%d Hours', 3),
	'240'   => __('%d Hours', 4),
	'300'   => __('%d Hours', 5),
	'360'   => __('%d Hours', 6)
);

$dsstats_max_memory = array(
	'32'   => __('%d MBytes', 32),
	'64'   => __('%d MBytes', 64),
	'128'  => __('%d MBytes', 128),
	'256'  => __('%d MBytes', 256),
	'512'  => __('%d MBytes', 512),
	'1024' => __('1 GByte'),
	'1536' => __('%s GBytes', '1.5'),
	'2048' => __('%d GBytes', 2),
	'3072' => __('%d GBytes', 3)
);

$dsstats_hourly_avg = array(
	'60'  => __('1 Hour'),
	'120' => __('%d Hours', 2),
	'180' => __('%d Hours', 3),
	'240' => __('%d Hours', 4),
	'300' => __('%d Hours', 5),
	'360' => __('%d Hours', 6)
);

$boost_max_rows_per_select = array(
	'2000'   => __('2,000 Data Source Items'),
	'5000'   => __('5,000 Data Source Items'),
	'10000'  => __('10,000 Data Source Items'),
	'15000'  => __('15,000 Data Source Items'),
	'25000'  => __('25,000 Data Source Items'),
	'50000'  => __('50,000 Data Source Items (Default)'),
	'100000' => __('100,000 Data Source Items'),
	'200000' => __('200,000 Data Source Items'),
	'400000' => __('400,000 Data Source Items')
);

$boost_utilities_interval = array(
	'999999' => __('Disabled'),
	'5'      => __('%d Seconds', 5),
	'10'     => __('%d Seconds', 10),
	'15'     => __('%d Seconds', 15),
	'20'     => __('%d Seconds', 20),
	'30'     => __('%d Seconds', 30),
	'60'     => __('1 Minute'),
	'300'    => __('%d Minutes', 5)
);

$boost_refresh_interval = array(
	'30'  => __('%d Minutes', 30),
	'60'  => __('1 Hour'),
	'120' => __('2 Hours', 2),
	'240' => __('4 Hours', 4),
	'360' => __('6 Hours', 6)
);

$boost_max_runtime = array(
	'1200' => __('%d Minutes', 20),
	'2400' => __('%d Minutes', 40),
	'3600' => __('1 Hour'),
	'4800' => __('%s Hours', '1.5')
);

$boost_max_memory = array(
	'32'   => __('%d MBytes', 32),
	'64'   => __('%d MBytes', 64),
	'128'  => __('%d MBytes', 128),
	'256'  => __('%d MBytes', 256),
	'512'  => __('%d MBytes', 512),
	'1024' => __('1 GByte'),
	'1536' => __('%s GBytes', '1.5'),
	'2048' => __('%d GBytes', 2),
	'3072' => __('%d GBytes', 3)
);

$realtime_window = array(
	30   => __('%d Seconds', 30),
	45   => __('%d Seconds', 45),
	60   => __('1 Minute'),
	90   => __('%s Minutes', '1.5'),
	120  => __('%d Minutes', 2),
	300  => __('%d Minutes', 5),
	600  => __('%d Minutes', 10),
	1200 => __('%d Minutes', 20),
	1800 => __('%d Minutes', 30),
	3600 => __('1 Hour')
);

$realtime_refresh = array(
	5   => __('%d Seconds', 5),
	10  => __('%d Seconds', 10),
	15  => __('%d Seconds', 15),
	20  => __('%d Seconds', 20),
	30  => __('%d Seconds', 30),
	60  => __('1 Minute'),
	120 => __('%d Minutes', 2)
);

$attachment_sizes = array(
	1048576   => __('1 Megabyte'),
	2097152   => __('%d Megabytes', 2),
	4194304   => __('%d Megabytes', 4),
	10485760  => __('%d Megabytes', 10),
	20971520  => __('%d Megabytes', 20),
	52428800  => __('%d Megabytes', 50),
	104857600 => __('%d Megabytes', 100),
);

$reports_actions = array(
	REPORTS_SEND_NOW  => __('Send Now'),
	REPORTS_DUPLICATE => __('Duplicate'),
	REPORTS_ENABLE    => __('Enable'),
	REPORTS_DISABLE   => __('Disable'),
	REPORTS_DELETE    => __('Delete'),
);

if (is_realm_allowed(22)) {
	$reports_actions[REPORTS_OWN] = __('Take Ownership');
}

$attach_types = array(
	REPORTS_TYPE_INLINE_PNG => __('Inline PNG Image'),
	#REPORTS_TYPE_INLINE_JPG => 'Inline JPEG Image'
);

if (extension_loaded(REPORTS_EXTENSION_GD)) {
	$attach_types[REPORTS_TYPE_INLINE_JPG] = __('Inline JPEG Image');
	$attach_types[REPORTS_TYPE_INLINE_GIF] = __('Inline GIF Image');
}

$attach_types[REPORTS_TYPE_ATTACH_PNG] = __('Attached PNG Image');

if (extension_loaded(REPORTS_EXTENSION_GD)) {
	$attach_types[REPORTS_TYPE_ATTACH_JPG] = __('Attached JPEG Image');
	$attach_types[REPORTS_TYPE_ATTACH_GIF] = __('Attached GIF Image');
}

if (read_config_option('reports_allow_ln') != '') {
	$attach_types[REPORTS_TYPE_INLINE_PNG_LN] = __('Inline PNG Image, LN Style');
	if (extension_loaded(REPORTS_EXTENSION_GD)) {
		$attach_types[REPORTS_TYPE_INLINE_JPG_LN] = __('Inline JPEG Image, LN Style');
		$attach_types[REPORTS_TYPE_INLINE_GIF_LN] = __('Inline GIF Image, LN Style');
	}
}

$item_types = array(
	REPORTS_ITEM_TEXT  => __('Text'),
	REPORTS_ITEM_TREE  => __('Tree'),
	REPORTS_ITEM_GRAPH => __('Graph'),
	REPORTS_ITEM_HR    => __('Horizontal Rule')
);

$alignment = array(
	REPORTS_ALIGN_LEFT   => __('left'),
	REPORTS_ALIGN_CENTER => __('center'),
	REPORTS_ALIGN_RIGHT  => __('right')
);

$reports_interval = array(
	REPORTS_SCHED_INTVL_MINUTE        => __('Minute(s)'),
	REPORTS_SCHED_INTVL_HOUR          => __('Hour(s)'),
	REPORTS_SCHED_INTVL_DAY           => __('Day(s)'),
	REPORTS_SCHED_INTVL_WEEK          => __('Week(s)'),
	REPORTS_SCHED_INTVL_MONTH_DAY     => __('Month(s), Day of Month'),
	REPORTS_SCHED_INTVL_MONTH_WEEKDAY => __('Month(s), Day of Week'),
	REPORTS_SCHED_INTVL_YEAR          => __('Year(s)'),
);

$agg_graph_types = array(
	AGGREGATE_GRAPH_TYPE_KEEP         => __('Keep Graph Types'),
	AGGREGATE_GRAPH_TYPE_KEEP_STACKED => __('Keep Type and STACK'),
	GRAPH_ITEM_TYPE_STACK             => __('Convert to AREA/STACK Graph'),
	GRAPH_ITEM_TYPE_LINE1             => __('Convert to LINE1 Graph'),
	GRAPH_ITEM_TYPE_LINE2             => __('Convert to LINE2 Graph'),
	GRAPH_ITEM_TYPE_LINE3             => __('Convert to LINE3 Graph'),
);

$agg_totals = array(
	AGGREGATE_TOTAL_NONE => __('No Totals'),
	AGGREGATE_TOTAL_ALL  => __('Print all Legend Items'),
	AGGREGATE_TOTAL_ONLY => __('Print totaling Legend Items Only'),
);

$agg_totals_type = array(
	AGGREGATE_TOTAL_TYPE_SIMILAR => __('Total Similar Data Sources'),
	AGGREGATE_TOTAL_TYPE_ALL     => __('Total All Data Sources'),
);

$agg_order_types = array(
	AGGREGATE_ORDER_NONE      => __('No Reordering'),
	AGGREGATE_ORDER_DS_GRAPH  => __('Data Source, Graph'),
	AGGREGATE_ORDER_GRAPH_DS  => __('Graph, Data Source'),
);

# operators for use with SQL/pattern matching
$automation_op_array = array(
	'display' => array(
		AUTOMATION_OP_NONE         => __('None'),
		AUTOMATION_OP_CONTAINS     => __('contains'),
		AUTOMATION_OP_CONTAINS_NOT => __('does not contain'),
		AUTOMATION_OP_BEGINS       => __('begins with'),
		AUTOMATION_OP_BEGINS_NOT   => __('does not begin with'),
		AUTOMATION_OP_ENDS         => __('ends with'),
		AUTOMATION_OP_ENDS_NOT     => __('does not end with'),
		AUTOMATION_OP_MATCHES      => __('matches'),
		AUTOMATION_OP_MATCHES_NOT  => __('does not match with'),
		AUTOMATION_OP_LT           => __('is less than'),
		AUTOMATION_OP_LE           => __('is less than or equal'),
		AUTOMATION_OP_GT           => __('is greater than'),
		AUTOMATION_OP_GE           => __('is greater than or equal'),
		AUTOMATION_OP_UNKNOWN      => __('is unknown'),
		AUTOMATION_OP_NOT_UNKNOWN  => __('is not unknown'),
		AUTOMATION_OP_EMPTY        => __('is empty'),
		AUTOMATION_OP_NOT_EMPTY    => __('is not empty'),
		AUTOMATION_OP_REGEXP       => __('matches regular expression'),
		AUTOMATION_OP_NOT_REGEXP   => __('does not match regular expression'),
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
	TREE_ITEM_TYPE_GRAPH => __('Graph'),
	TREE_ITEM_TYPE_HOST  => __('Device')
);

$automation_tree_header_types  = array(
	AUTOMATION_TREE_ITEM_TYPE_STRING => __('Fixed String'),
);

$automation_frequencies = array(
	'disabled' => __('Disabled'),
	'60'       => __('Every 1 Hour'),
	'120'      => __('Every %d Hours', 2),
	'240'      => __('Every %d Hours', 4),
	'360'      => __('Every %d Hours', 6),
	'480'      => __('Every %d Hours', 8),
	'720'      => __('Every %d Hours', 12),
	'1440'     => __('Every Day'),
	'10080'    => __('Every Week'),
	'20160'    => __('Every %d Weeks', 2),
	'40320'    => __('Every %d Weeks', 4)
);

$logrotate_frequency = array(
	'1'  => __('Daily'),
	'7'  => __('Weekly'),
	'30' => __('Monthly')
);

$no_session_write = array(
	'graph_xport.php',
	'graph_image.php',
	'graph_json.php'
);

$i18n_months = array(
	'January'	=> __('January'),
	'February'	=> __('February'),
	'March'		=> __('March'),
	'Arpil'		=> __('April'),
	'May'		=> __('May'),
	'June'		=> __('June'),
	'July'		=> __('July'),
	'August'	=> __('August'),
	'September'	=> __('September'),
	'October'	=> __('October'),
	'November'	=> __('November'),
	'December'	=> __('December'),
);

$i18n_months_short = array(
	'Jan'	=> __x('A short textual representation of a month, three letters', 'Jan'),
	'Feb'	=> __x('A short textual representation of a month, three letters', 'Feb'),
	'Mar'	=> __x('A short textual representation of a month, three letters', 'Mar'),
	'Arp'	=> __x('A short textual representation of a month, three letters', 'Apr'),
	'May'	=> __x('A short textual representation of a month, three letters', 'May'),
	'Jun'	=> __x('A short textual representation of a month, three letters', 'Jun'),
	'Jul'	=> __x('A short textual representation of a month, three letters', 'Jul'),
	'Aug'	=> __x('A short textual representation of a month, three letters', 'Aug'),
	'Sep'	=> __x('A short textual representation of a month, three letters', 'Sep'),
	'Oct'	=> __x('A short textual representation of a month, three letters', 'Oct'),
	'Nov'	=> __x('A short textual representation of a month, three letters', 'Nov'),
	'Dec'	=> __x('A short textual representation of a month, three letters', 'Dec'),
);

$i18n_weekdays = array(
	'Sunday'	=> __('Sunday'),
	'Monday'	=> __('Monday'),
	'Tuesday'	=> __('Tuesday'),
	'Wednesday'	=> __('Wednesday'),
	'Thursday'	=> __('Thursday'),
	'Friday'	=> __('Friday'),
	'Saturday'	=> __('Saturday')
);

$i18n_weekdays_short = array(
	'Sun'	=> __x('A textual representation of a day, three letters', 'Sun'),
	'Mon'	=> __x('A textual representation of a day, three letters', 'Mon'),
	'Tue'	=> __x('A textual representation of a day, three letters', 'Tue'),
	'Wed'	=> __x('A textual representation of a day, three letters', 'Wed'),
	'Thu'	=> __x('A textual representation of a day, three letters', 'Thu'),
	'Fri'	=> __x('A textual representation of a day, three letters', 'Fri'),
	'Sat'	=> __x('A textual representation of a day, three letters', 'Sat')
);

$phperrors = array (
	E_ERROR => 'ERROR',
	E_WARNING => 'WARNING',
	E_PARSE => 'PARSE',
	E_NOTICE => 'NOTICE',
	E_CORE_ERROR => 'CORE_ERROR',
	E_CORE_WARNING => 'CORE_WARNING',
	E_COMPILE_ERROR  => 'COMPILE_ERROR',
	E_COMPILE_WARNING => 'COMPILE_WARNING',
	E_USER_ERROR => 'USER_ERROR',
	E_USER_WARNING => 'USER_WARNING',
	E_USER_NOTICE  => 'USER_NOTICE',
	E_STRICT => 'STRICT',
	E_RECOVERABLE_ERROR  => 'RECOVERABLE_ERROR',
	E_DEPRECATED => 'DEPRECATED',
	E_USER_DEPRECATED  => 'USER_DEPRECATED',
	E_ALL => 'ALL'
);

api_plugin_hook('config_arrays');

