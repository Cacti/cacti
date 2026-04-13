<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

global $menu, $menu_glyphs, $graph_sources;

/* Array of Cacti versions and template hash codes
   Remember to add every version here. */
$cacti_version_codes = [
	'0.8'     => 'NaN',
	'0.8.1'   => 'NaN',
	'0.8.2'   => 'NaN',
	'0.8.2a'  => 'NaN',
	'0.8.3'   => 'NaN',
	'0.8.3a'  => 'NaN',
	'0.8.4'   => '0000',
	'0.8.5'   => '0001',
	'0.8.5a'  => '0002',
	'0.8.6'   => '0003',
	'0.8.6a'  => '0004',
	'0.8.6b'  => '0005',
	'0.8.6c'  => '0006',
	'0.8.6d'  => '0007',
	'0.8.6e'  => '0008',
	'0.8.6f'  => '0009',
	'0.8.6g'  => '0010',
	'0.8.6h'  => '0011',
	'0.8.6i'  => '0012',
	'0.8.6j'  => '0013',
	'0.8.7'   => '0014',
	'0.8.7a'  => '0015',
	'0.8.7b'  => '0016',
	'0.8.7c'  => '0017',
	'0.8.7d'  => '0018',
	'0.8.7e'  => '0019',
	'0.8.7f'  => '0020',
	'0.8.7g'  => '0021',
	'0.8.7h'  => '0022',
	'0.8.7i'  => '0023',
	'0.8.8'   => '0024',
	'0.8.8a'  => '0024',
	'0.8.8b'  => '0024',
	'0.8.8c'  => '0025',
	'0.8.8d'  => '0025',
	'0.8.8e'  => '0025',
	'0.8.8f'  => '0025',
	'0.8.8g'  => '0025',
	'0.8.8h'  => '0025',
	'1.0.0'   => '0100',
	'1.0.1'   => '0100',
	'1.0.2'   => '0100',
	'1.0.3'   => '0100',
	'1.0.4'   => '0100',
	'1.0.5'   => '0100',
	'1.0.6'   => '0100',
	'1.1.0'   => '0100',
	'1.1.1'   => '0100',
	'1.1.2'   => '0100',
	'1.1.3'   => '0100',
	'1.1.4'   => '0100',
	'1.1.5'   => '0100',
	'1.1.6'   => '0101',
	'1.1.7'   => '0101',
	'1.1.8'   => '0101',
	'1.1.9'   => '0101',
	'1.1.10'  => '0101',
	'1.1.11'  => '0101',
	'1.1.12'  => '0101',
	'1.1.13'  => '0101',
	'1.1.14'  => '0101',
	'1.1.15'  => '0101',
	'1.1.16'  => '0101',
	'1.1.17'  => '0101',
	'1.1.18'  => '0101',
	'1.1.19'  => '0101',
	'1.1.20'  => '0101',
	'1.1.21'  => '0101',
	'1.1.22'  => '0101',
	'1.1.23'  => '0101',
	'1.1.24'  => '0101',
	'1.1.25'  => '0101',
	'1.1.26'  => '0101',
	'1.1.27'  => '0101',
	'1.1.28'  => '0101',
	'1.1.29'  => '0101',
	'1.1.30'  => '0101',
	'1.1.31'  => '0101',
	'1.1.32'  => '0101',
	'1.1.33'  => '0101',
	'1.1.34'  => '0101',
	'1.1.35'  => '0101',
	'1.1.36'  => '0101',
	'1.1.37'  => '0101',
	'1.1.38'  => '0101',
	'1.2.0'   => '0101',
	'1.2.1'   => '0101',
	'1.2.2'   => '0101',
	'1.2.3'   => '0102',
	'1.2.4'   => '0102',
	'1.2.5'   => '0102',
	'1.2.6'   => '0102',
	'1.2.7'   => '0102',
	'1.2.8'   => '0102',
	'1.2.9'   => '0102',
	'1.2.10'  => '0102',
	'1.2.11'  => '0102',
	'1.2.12'  => '0102',
	'1.2.13'  => '0102',
	'1.2.14'  => '0102',
	'1.2.15'  => '0102',
	'1.2.16'  => '0102',
	'1.2.17'  => '0102',
	'1.2.18'  => '0102',
	'1.2.19'  => '0103',
	'1.2.20'  => '0103',
	'1.2.21'  => '0103',
	'1.2.22'  => '0103',
	'1.2.23'  => '0103',
	'1.2.24'  => '0103',
	'1.2.25'  => '0103',
	'1.2.26'  => '0103',
	'1.2.27'  => '0103',
	'1.2.28'  => '0103',
	'1.2.29'  => '0103',
	'1.2.30'  => '0103',
	'1.2.31'  => '0103',
	'1.3.0'   => '0104',
];

$messages = [
	1  => [
		'message' => __('Save Successful.'),
		'level'   => MESSAGE_LEVEL_INFO],
	2  => [
		'message' => __('Save Failed.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	3  => [
		'message' => __('Save Failed due to field input errors (Check red fields).'),
		'level'   => MESSAGE_LEVEL_ERROR],
	4  => [
		'message' => __('Passwords do not match, please retype.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	5  => [
		'message' => __('You must select at least one field.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	6  => [
		'message' => __('You must have built in user authentication turned on to use this feature.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	7  => [
		'message' => __('XML parse error.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	8  => [
		'message' => __('The directory highlighted does not exist.  Please enter a valid directory.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	9  => [
		'message' => __('The Cacti log file must have the extension \'.log\''),
		'level'   => MESSAGE_LEVEL_ERROR],
	10  => [
		'message' => __('Data Input for method does not appear to be whitelisted.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	11  => [
		'message' => __('Data Source does not exist.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	12 => [
		'message' => __('Username already in use.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	13  => [
		'message' => __('The SNMP v3 Privacy Passphrases do not match'),
		'level'   => MESSAGE_LEVEL_ERROR],
	14  => [
		'message' => __('The SNMP v3 Authentication Passphrases do not match'),
		'level'   => MESSAGE_LEVEL_ERROR],
	15 => [
		'message' => __('XML: Cacti version does not exist.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	16 => [
		'message' => __('XML: Hash version does not exist.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	17 => [
		'message' => __('XML: Generated with a newer version of Cacti.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	18 => [
		'message' => __('XML: Cannot locate type code.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	19 => [
		'message' => __('Username already exists.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	20 => [
		'message' => __('Username change not permitted for designated template or guest user.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	21 => [
		'message' => __('User delete not permitted for designated template or guest user.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	22 => [
		'message' => __('User delete not permitted for designated graph export user.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	23 => [
		'message' => __('Data Template includes deleted Data Source Profile.  Please resave the Data Template with an existing Data Source Profile.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	24 => [
		'message' => __('Graph Template includes deleted GPrint Prefix.  Please run database repair script to identify and/or correct.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	25 => [
		'message' => __('Graph Template includes deleted CDEFs.  Please run database repair script to identify and/or correct.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	26 => [
		'message' => __('Graph Template includes deleted Data Input Method.  Please run database repair script to identify.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	27 => [
		'message' => __('Data Template not found during Export.  Please run database repair script to identify.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	28 => [
		'message' => __('Device Template not found during Export.  Please run database repair script to identify.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	29 => [
		'message' => __('Data Query not found during Export.  Please run database repair script to identify.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	30 => [
		'message' => __('Graph Template not found during Export.  Please run database repair script to identify.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	31 => [
		'message' => __('Graph not found.  Either it has been deleted or your database needs repair.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	32 => [
		'message' => __('SNMPv3 Auth Passphrases must be 8 characters or greater.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	33 => [
		'message' => __('Some Graphs not updated. Unable to change device for Data Query based Graphs.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	34 => [
		'message' => __('Unable to change device for Data Query based Graphs.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	35 => [
		'message' => __('Some settings not saved. Check messages below.  Check red fields for errors.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	36 => [
		'message' => __('The file highlighted does not exist.  Please enter a valid file name.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	37 => [
		'message' => __('All User Settings have been returned to their default values.'),
		'level'   => MESSAGE_LEVEL_INFO],
	38 => [
		'message' => __('Suggested Field Name was not entered.  Please enter a field name and try again.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	39 => [
		'message' => __('Suggested Value was not entered.  Please enter a suggested value and try again.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	40 => [
		'message' => __('You must select at least one object from the list.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	41 => [
		'message' => __('Device Template updated.  Remember to Sync Devices to push all changes to Devices that use this Device Template.'),
		'level'   => MESSAGE_LEVEL_INFO],
	42 => [
		'message' => __('Save Successful. Settings replicated to Remote Data Collectors.'),
		'level'   => MESSAGE_LEVEL_INFO],
	43 => [
		'message' => __('Save Failed.  Minimum Values must be less than Maximum Value.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	44 => [
		'message' => __('Unable to change password.  User account not found.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'input_save_wo_ds' => [
		'message' => __('Data Input Saved.  You must update the Data Templates referencing this Data Input Method before creating Graphs or Data Sources.'),
		'level'   => MESSAGE_LEVEL_INFO],
	'input_save_w_ds' => [
		'message' => __('Data Input Saved.  You must update the Data Templates referencing this Data Input Method before the Data Collectors will start using any new or modified Data Input - Input Fields.'),
		'level'   => MESSAGE_LEVEL_INFO],
	'input_field_save_wo_ds' => [
		'message' => __('Data Input Field Saved.  You must update the Data Templates referencing this Data Input Method before creating Graphs or Data Sources.'),
		'level'   => MESSAGE_LEVEL_INFO],
	'input_field_save_w_ds' => [
		'message' => __('Data Input Field Saved.  You must update the Data Templates referencing this Data Input Method before the Data Collectors will start using any new or modified Data Input - Input Fields.'),
		'level'   => MESSAGE_LEVEL_INFO],
	'clog_invalid' => [
		'message' => __('Log file specified is not a Cacti log or archive file.'),
		'level'   => MESSAGE_LEVEL_INFO],
	'clog_remove' => [
		'message' => __('Log file specified was Cacti archive file and was removed.'),
		'level'   => MESSAGE_LEVEL_INFO],
	'clog_purged' => [
		'message' => __('Cacti log purged successfully'),
		'level'   => MESSAGE_LEVEL_INFO],
	'password_change' => [
		'message' => __('If you force a password change, you must also allow the user to change their password.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'nopassword' => [
		'message' => __('You are not allowed to change your password.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'nopasswordlen' => [
		'message' => __('Unable to determine size of password field, please check permissions of db user'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'nopasswordinc' => [
		'message' => __('Unable to increase size of password field, please check permission of db user'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'nodomainpassword' => [
		'message' => __('LDAP/AD based password change not supported.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'password_success' => [
		'message' => __('Password successfully changed.'),
		'level'   => MESSAGE_LEVEL_INFO],
	'clog_permissions' => [
		'message' => __('Unable to clear log, no write permissions'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'clog_missing' => [
		'message' => __('Unable to clear log, file does not exist'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'csrf_timeout' => [
		'message' => __('CSRF Timeout, refreshing page.'),
		'level'   => MESSAGE_LEVEL_CSRF],
	'csrf_ptimeout' => [
		'message' => __('CSRF Timeout occurred due to inactivity, page refreshed.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'mg_mailtime_invalid' => [
		'message' => __('Invalid timestamp. Select timestamp in the future.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'poller_sync' => [
		'message' => __('Data Collector(s) synchronized for offline operation'),
		'level'   => MESSAGE_LEVEL_INFO],
	'poller_notfound' => [
		'message' => __('Data Collector(s) not found when attempting synchronization'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'poller_noconnect' => [
		'message' => __('Unable to establish MySQL connection with Remote Data Collector.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'poller_nosync' => [
		'message' => __('Data Collector synchronization must be initiated from the main Cacti server.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'poller_nomain' => [
		'message' => __('Synchronization does not include the Central Cacti Database server.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'poller_nodupe' => [
		'message' => __('When saving a Remote Data Collector, the Database Hostname must be unique from all others.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'poller_dbhost' => [
		'message' => __('Your Remote Database Hostname must be something other than \'localhost\' for each Remote Data Collector.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'poller_paths' => [
		'message' => __('Path variables on this page were only saved locally.'),
		'level'   => MESSAGE_LEVEL_INFO],
	'reports_save' => [
		'message' => __('Report Saved'),
		'level'   => MESSAGE_LEVEL_INFO],
	'reports_save_failed' => [
		'message' => __('Report Save Failed'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'reports_item_save' => [
		'message' => __('Report Item Saved'),
		'level'   => MESSAGE_LEVEL_INFO],
	'reports_item_save_failed' => [
		'message' => __('Report Item Save Failed'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'reports_graph_not_found' => [
		'message' => __('Graph was not found attempting to Add to Report'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'reports_not_owner' => [
		'message' => __('Unable to Add Graphs.  Current user is not owner'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'reports_add_error' => [
		'message' => __('Unable to Add all Graphs.  See error message for details.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'reports_no_graph' => [
		'message' => __('You must select at least one Graph to add to a Report.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'reports_graphs_added' => [
		'message' => __('All Graphs have been added to the Report.  Duplicate Graphs with the same Timespan were skipped.'),
		'level'   => MESSAGE_LEVEL_INFO],
	'resource_cache_rebuild' => [
		'message' => __('Poller Resource Cache cleared.  Main Data Collector will rebuild at the next poller start, and Remote Data Collectors will sync afterwards.'),
		'level'   => MESSAGE_LEVEL_INFO],
	'permission_denied' => [
		'message' => __('Permission Denied.  You do not have permission to the requested action.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'page_not_defined' => [
		'message' => __('Page is not defined.  Therefore, it can not be displayed.'),
		'level'   => MESSAGE_LEVEL_ERROR],
	'custom_error' => [
		'message' => __('Unexpected error occurred'),
		'level'   => MESSAGE_LEVEL_ERROR]
];

$cdef_operators = [
	1 => '+',
	2 => '-',
	3 => '*',
	4 => '/',
	5 => '%'
];

$cdef_functions = [
	1  => 'SIN',
	2  => 'COS',
	3  => 'LOG',
	4  => 'EXP',
	5  => 'FLOOR',
	6  => 'CEIL',
	7  => 'LT',
	8  => 'LE',
	9  => 'GT',
	10 => 'GE',
	11 => 'EQ',
	12 => 'IF',
	13 => 'MIN',
	14 => 'MAX',
	15 => 'LIMIT',
	16 => 'DUP',
	17 => 'EXC',
	18 => 'POP',
	19 => 'UN',
	20 => 'UNKN',
	21 => 'PREV',
	22 => 'INF',
	23 => 'NEGINF',
	24 => 'NOW',
	25 => 'TIME',
	26 => 'LTIME',
	27 => 'ADDNAN',
	28 => 'TREND',
	29 => 'TRENDNAN',
	30 => 'PREDICT',
	31 => 'PREDICTSIGMA',
	32 => 'PREDICTPERC',
	33 => 'SQRT',
	34 => 'ATAN',
	35 => 'ATAN2',
	36 => 'POW',
	37 => 'ISINF',
	38 => 'MINNAN',
	39 => 'MAXNAN',
	40 => 'DEG2RAD',
	41 => 'RAD2DEG',
	42 => 'ABS',
	43 => 'REV',
	44 => 'SMIN',
	45 => 'SMAX',
	46 => 'MEDIAN',
	47 => 'STDEV',
	48 => 'PERCENT',
	49 => 'COUNT',
	50 => 'STEPWIDTH',
	51 => 'NEWDAY',
	52 => 'NEWWEEK',
	53 => 'NEWMONTH',
	54 => 'NEWYEAR',
	55 => 'DEPTH',
	56 => 'COPY',
	57 => 'INDEX',
	58 => 'ROLL'
];

$phperrors =  [
	E_ERROR              => 'ERROR',
	E_WARNING            => 'WARNING',
	E_PARSE              => 'PARSE',
	E_NOTICE             => 'NOTICE',
	E_CORE_ERROR         => 'CORE_ERROR',
	E_CORE_WARNING       => 'CORE_WARNING',
	E_COMPILE_ERROR      => 'COMPILE_ERROR',
	E_COMPILE_WARNING    => 'COMPILE_WARNING',
	E_USER_ERROR         => 'USER_ERROR',
	E_USER_WARNING       => 'USER_WARNING',
	E_USER_NOTICE        => 'USER_NOTICE',
	E_RECOVERABLE_ERROR  => 'RECOVERABLE_ERROR',
	E_DEPRECATED         => 'DEPRECATED',
	E_USER_DEPRECATED    => 'USER_DEPRECATED',
	E_ALL                => 'ALL'
];

if (version_compare(PHP_VERSION, '8.4', '<')) {
	$phperrors[E_STRICT] = 'STRICT';
}

if (cacti_version_compare(get_rrdtool_version(), '1.8.0', '>=')) {
	$cdef_functions[] = 'ROUND';
}

$vdef_functions = [
	1  => 'MAXIMUM',
	2  => 'MINIMUM',
	3  => 'AVERAGE',
	4  => 'STDEV',
	5  => 'LAST',
	6  => 'FIRST',
	7  => 'TOTAL',
	8  => 'PERCENT',
	9  => 'PERCENTNAN',
	10 => 'LSLSLOPE',
	11 => 'LSLINT',
	12 => 'LSLCORREL'
];

$vdef_item_types = [
	CVDEF_ITEM_TYPE_FUNCTION => __('Function'),
	CVDEF_ITEM_TYPE_SPEC_DS  => __('Special Data Source'),
	CVDEF_ITEM_TYPE_STRING   => __('Custom String'),
];

$custom_vdef_data_source_types = [ // this may change as soon as RRDtool supports math in VDEF, until then only reference to CDEF may help
	'CURRENT_DATA_SOURCE' => __('Current Graph Item Data Source'),
];

$input_types = [
	DATA_INPUT_TYPE_SNMP                => __('SNMP Get'),        // Action 0:
	DATA_INPUT_TYPE_SNMP_QUERY          => __('SNMP Query'),
	DATA_INPUT_TYPE_SCRIPT              => __('Script/Command'),  // Action 1:
	DATA_INPUT_TYPE_SCRIPT_QUERY        => __('Script Query'),    // Action 1:
	DATA_INPUT_TYPE_PHP_SCRIPT_SERVER   => __('Script Server'),
	DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER => __('Script Server Query'),
	DATA_INPUT_TYPE_STREAM              => __('Stream Data'),
	DATA_INPUT_TYPE_STREAM_QUERY        => __('Stream Data Query')
];

$input_types_script = [
	DATA_INPUT_TYPE_SCRIPT              => __('Script/Command'),  // Action 1:
	DATA_INPUT_TYPE_PHP_SCRIPT_SERVER   => __('Script Server'),
];

$reindex_types = [
	DATA_QUERY_AUTOINDEX_NONE               => __('None'),
	DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME   => __('Uptime'),
	DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE   => __('Index Count'),
	DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION => __('Verify All')
];

$reindex_types_tips = [
	DATA_QUERY_AUTOINDEX_NONE               => __('All Re-Indexing will be manual or managed through scripts or Device Automation.'),
	DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME   => __('When the Devices SNMP uptime goes backward, a Re-Index will be performed.'),
	DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE   => __('When the Data Query index count changes, a Re-Index will be performed.'),
	DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION => __('Every polling cycle, a Re-Index will be performed.  Very expensive.')
];

$snmp_query_field_actions = [
	1 => __('SNMP Field Name (Dropdown)'),
	2 => __('SNMP Field Value (From User)'),
	3 => __('SNMP Output Type (Dropdown)')
];

$consolidation_functions = [
	1 => 'AVERAGE',
	2 => 'MIN',
	3 => 'MAX',
	4 => 'LAST'
];

$data_source_types = [
	1 => 'GAUGE',
	2 => 'COUNTER',
	3 => 'DERIVE',
	4 => 'ABSOLUTE',
	5 => 'COMPUTE'
];

if (cacti_version_compare(get_rrdtool_version(), '1.5', '>=')) {
	$data_source_types[6] = 'DCOUNTER';
	$data_source_types[7] = 'DDERIVE';
}

$rrd_font_render_modes = [
	RRD_FONT_RENDER_NORMAL  => __('Normal'),
	RRD_FONT_RENDER_LIGHT   => __('Light'),
	RRD_FONT_RENDER_MONO    => __('Mono'),
];

$rrd_graph_render_modes = [
	RRD_GRAPH_RENDER_NORMAL => __('Normal'),
	RRD_GRAPH_RENDER_MONO   => __('Mono'),
];

$rrd_legend_position = [
	RRD_LEGEND_POS_NORTH    => __('North'),
	RRD_LEGEND_POS_SOUTH    => __('South'),
	RRD_LEGEND_POS_WEST     => __('West'),
	RRD_LEGEND_POS_EAST     => __('East'),
];

$rrd_textalign = [
	RRD_ALIGN_NONE          => __('None'),
	RRD_ALIGN_LEFT          => __('Left'),
	RRD_ALIGN_RIGHT         => __('Right'),
	RRD_ALIGN_JUSTIFIED     => __('Justified'),
	RRD_ALIGN_CENTER        => __('Center'),
];

$rrd_legend_direction = [
	RRD_LEGEND_DIR_TOPDOWN  => __('Top -> Down'),
	RRD_LEGEND_DIR_BOTTOMUP => __('Bottom -> Up'),
];

$rrd_axis_formatters = [
	'numeric'   => __('Numeric'),
	'timestamp' => __('Timestamp'),
	'duration'  => __('Duration')
];

$graph_item_types = [
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
];

asort($graph_item_types);

$automation_log_levels = [
	AUTOMATION_LOG_LOW    => __('Warnings, Errors, Device and Tree Creation'),
	AUTOMATION_LOG_MEDIUM => __('Low Level plus Graph Creation/Non-Creation Events'),
	AUTOMATION_LOG_HIGH   => __('All Events plus Global Log Level Specific Trace Events')
];

$image_types = [
	1 => 'PNG',
	3 => 'SVG'
];

$snmp_security_levels = [
	'noAuthNoPriv' => 'noAuthNoPriv',
	'authNoPriv'   => 'authNoPriv',
	'authPriv'     => 'authPriv'
];

$snmp_versions = [0 => __('Not In Use'),
	__('Version %d', 1),
	__('Version %d', 2),
	__('Version %d', 3)
];

$snmp_auth_protocols = [
	'[None]' => __('[None]'),
	'MD5'    => __('MD5'),
	'SHA'    => __('SHA'),
	'SHA224' => __('SHA-224'),
	'SHA256' => __('SHA-256'),
	'SHA384' => __('SHA-384'),
	'SHA512' => __('SHA-512'),
];

$snmp_priv_protocols = [
	'[None]'   => __('[None]'),
	'DES'      => __('DES'),
	'AES'      => __('AES'),
	'AES128'   => __('AES-128'),
	'AES192'   => __('AES-192'),
	'AES192C'  => __('AES-192-C'),
	'AES256'   => __('AES-256'),
	'AES256C'  => __('AES-256-C')
];

$banned_snmp_strings = [
	'End of MIB',
	'No Such'
];

$logfile_options = [
	1 => __('Logfile Only'),
	2 => __('Logfile and Syslog/Eventlog'),
	3 => __('Syslog/Eventlog Only')
];

$logfile_actions = [
	LOG_ACTION_PURGE        => __('Purge'),
	LOG_ACTION_ROTATE       => __('Rotate'),
	LOG_ACTION_PURGE_ROTATE => __('Purge and Rotate')
];

$availability_options = [
	AVAIL_NONE             => __('None'),
	AVAIL_SNMP_AND_PING    => __('Ping and SNMP Uptime'),
	AVAIL_SNMP_OR_PING     => __('Ping or SNMP Uptime'),
	AVAIL_SNMP             => __('SNMP Uptime'),
	AVAIL_SNMP_GET_SYSDESC => __('SNMP Desc'),
	AVAIL_SNMP_GET_NEXT    => __('SNMP getNext'),
	AVAIL_PING             => __('Ping'),
	AVAIL_STREAM           => __('Stream Device')
];

$ping_methods = [
	PING_ICMP        => __('ICMP Ping'),
	PING_TCP         => __('TCP Ping'),
	PING_TCP_CLOSED  => __('TCP Ping Closed'),
	PING_UDP         => __('UDP Ping')
];

$logfile_verbosity = [
	POLLER_VERBOSITY_NONE   => __('NONE - Syslog Only if Selected'),
	POLLER_VERBOSITY_LOW    => __('LOW - Statistics and Errors'),
	POLLER_VERBOSITY_MEDIUM => __('MEDIUM - Statistics, Errors and Results'),
	POLLER_VERBOSITY_HIGH   => __('HIGH - Statistics, Errors, Results and Major I/O Events'),
	POLLER_VERBOSITY_DEBUG  => __('DEBUG - Statistics, Errors, Results, I/O and Program Flow'),
	POLLER_VERBOSITY_DEVDBG => __('DEVEL - Developer DEBUG Level')
];

$logfile_expansion = [
	LOG_EXPAND_NONE    => ('NONE - No expansion'),
	LOG_EXPAND_DISPLAY => ('DISPLAY - Only when displaying'),
	LOG_EXPAND_FULL    => ('FULL - Display and search expansion'),
];

$logfile_validation = [
	INPUT_VALIDATION_NONE => ('NONE - No reporting'),
	INPUT_VALIDATION_LOG  => ('LOG - Only record to log'),
	INPUT_VALIDATION_FULL => ('FULL - Display and log'),
];

$poller_options = [
	1 => 'cmd.php',
	2 => 'spine'
];

if (read_config_option('path_spine') != '' && (!file_exists(read_config_option('path_spine')) || !is_executable(read_config_option('path_spine')))) {
	unset($poller_options[2]);
}

$aggregation_levels = [
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
	86400   => __('%d Day', 1),
	604800  => __('%d Week', 1)
];

$sampling_intervals = [
	10    => __('Every %d Seconds', 10),
	15    => __('Every %d Seconds', 15),
	20    => __('Every %d Seconds', 20),
	30    => __('Every %d Seconds', 30),
	60    => __('Every Minute'),
	300   => __('Every %d Minutes', 5),
	600   => __('Every %d Minutes', 10),
	900   => __('Every %d Minutes', 15),
	1200  => __('Every %d Minutes', 20),
	1800  => __('Every %d Minutes', 30),
	3600  => __('Every Hour'),
	7200  => __('Every %d Hours', 2),
	14400 => __('Every %d Hours', 4),
	28800 => __('Every %d Hours', 8),
	43200 => __('Every %d Hours', 12),
	86400 => __('Every %d Day', 1)
];

$heartbeats = [
	20     => __('%d Seconds', 20),
	30     => __('%d Seconds', 30),
	40     => __('%d Seconds', 40),
	45     => __('%d Seconds', 45),
	60     => __('%d Seconds', 60),
	90     => __('%d Seconds', 90),
	120    => __('%d Minutes', 2),
	300    => __('%d Minutes', 5),
	330    => __('%0.1f Minutes', 5.5),
	600    => __('%d Minutes', 10),
	660    => __('%d Minutes', 11),
	900    => __('%d Minutes', 15),
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
];

$timespans = [
	3600      => __('%d Hour', 1),
	7200      => __('%d Hours', 2),
	14400     => __('%d Hours', 4),
	21600     => __('%d Hours', 6),
	43200     => __('%d Hours', 12),
	86400     => __('%d Day', 1),
	172800    => __('%d Days', 2),
	345600    => __('%d Days', 4),
	604800    => __('%d Week', 1),
	1209600   => __('%d Weeks', 2),
	1814400   => __('%d Weeks', 3),
	2618784   => __('%d Month', 1),
	5237568   => __('%d Months', 2),
	7856352   => __('%d Months', 3),
	10475136  => __('%d Months', 4),
	15712704  => __('%d Months', 6),
	31536000  => __('%d Year', 1),
	63072000  => __('%d Years', 2),
	94608000  => __('%d Years', 3),
	126144000 => __('%d Years', 4),
	157680000 => __('%d Years', 5),
	189216000 => __('%d Years', 6),
	220752000 => __('%d Years', 7),
	252288000 => __('%d Years', 8),
	283824000 => __('%d Years', 9),
	315360000 => __('%d Years', 10)
];

$daily_timespans   = [
	7   => __('%d Week', 1),
	14  => __('%d Weeks', 2),
	21  => __('%d Weeks', 3),
	30  => __('%d Month', 1),
	60  => __('%d Months', 2),
	90  => __('%d Months', 3),
	182 => __('%d Months', 6),
	365 => __('%d Year', 1),
];

$weekly_timespans  = [
	1   => __('%d Week', 1),
	2   => __('%d Weeks', 2),
	3   => __('%d Weeks', 3),
	4   => __('%d Month', 1),
	8   => __('%d Months', 2),
	13  => __('%d Months', 3),
	17  => __('%d Months', 4),
	26  => __('%d Months', 6),
	52  => __('%d Year', 1),
	104 => __('%d Year', 2),
	156 => __('%d Year', 3),
	208 => __('%d Year', 4),
];

$monthly_timespans = [
	1  => __('%d Month', 1),
	2  => __('%d Months', 2),
	3  => __('%d Months', 3),
	6  => __('%d Months', 6),
	12 => __('%d Year', 1),
	24 => __('%d Year', 2),
	36 => __('%d Year', 3),
	48 => __('%d Year', 4),
];

$yearly_timespans  = [
	1  => __('%d Year', 1),
	2  => __('%d Years', 2),
	3  => __('%d Years', 3),
	4  => __('%d Years', 4),
	5  => __('%d Years', 5),
	6  => __('%d Years', 6),
	7  => __('%d Years', 7),
	8  => __('%d Years', 8),
	9  => __('%d Years', 9),
	10 => __('%d Years', 10),
];

$poller_intervals = [
	10  => __('Every %d Seconds', 10),
	15  => __('Every %d Seconds', 15),
	20  => __('Every %d Seconds', 20),
	30  => __('Every %d Seconds', 30),
	60  => __('Every Minute'),
	300 => __('Every %d Minutes', 5)
];

$poller_sync_intervals = [
	0     => __('Disabled/Manual'),
	1800  => __('Every %d Minutes', 30),
	3600  => __('Every Hour'),
	7200  => __('Every %d Hours', 2),
	14400 => __('Every %d Hours', 4),
	28800 => __('Every %d Hours', 8),
	57600 => __('Every %d Hours', 16),
	86400 => __('Every day'),
];

$device_threads = [
	1  => __('1 Thread'),
	2  => __('%d Threads', 2),
	3  => __('%d Threads', 3),
	4  => __('%d Threads', 4),
	5  => __('%d Threads', 5),
	6  => __('%d Threads', 6),
	7  => __('%d Threads', 7),
	8  => __('%d Threads', 8),
	9  => __('%d Threads', 9),
	10 => __('%d Threads', 10)
];

$device_change_fields = [
	'preg_field' => [
		'/^snmp_/',
		'/^ping_/'
	],
	'match_field' => [
		'poller_id',
		'site_id',
		'host_template_id',
		'availability_method',
		'bulk_walk_size',
		'device_threads',
		'location',
		'max_oids'
	]
];

$cron_intervals = [
	60  => __('Every Minute'),
	300 => __('Every %d Minutes', 5)
];

$registered_cacti_names = [
	'path_cacti'
];

$graph_views = [
	1 => __('Tree View'),
	2 => __('List View'),
	3 => __('Preview View')
];

$auth_methods = [
	AUTH_METHOD_CACTI => __('Cacti Builtin Authentication'),
	AUTH_METHOD_BASIC => __('Web Basic Authentication')
];

if (function_exists('ldap_connect')) {
	$auth_methods[AUTH_METHOD_DOMAIN] = __('LDAP/AD User Domains');
}

$domain_types = [
	DOMAIN_TYPE_LDAP => __('LDAP'),
	DOMAIN_TYPE_AD   => __('Active Directory')
];

$auth_realms = get_auth_realms();

$ldap_versions = [
	2 => __('Version %d', 2),
	3 => __('Version %d', 3)
];

$ldap_encryption = [
	0 => __('None'),
	1 => __('LDAPS'),
	2 => __('LDAP + TLS')
];

$ldap_tls_cert_req = [
	LDAP_OPT_X_TLS_NEVER  => __('Never'),
	LDAP_OPT_X_TLS_HARD   => __('Hard'),
	LDAP_OPT_X_TLS_DEMAND => __('Demand'),
	LDAP_OPT_X_TLS_ALLOW  => __('Allow'),
	LDAP_OPT_X_TLS_TRY    => __('Try')
];

$ldap_modes = [
	0 => __('No Searching'),
	1 => __('Anonymous Searching'),
	2 => __('Specific Searching')
];

$rrdtool_versions = [
	'1.5.0' => 'RRDtool 1.5+',
	'1.6.0' => 'RRDtool 1.6+',
	'1.7.0' => 'RRDtool 1.7+',
	'1.7.1' => 'RRDtool 1.7.1+',
	'1.7.2' => 'RRDtool 1.7.2+',
	'1.8.0' => 'RRDtool 1.8+',
	'1.9.0' => 'RRDtool 1.9+'
];

$i18n_modes = [
	0 => __('Disabled'),
	1 => __('Enabled'),
	2 => __('Enabled (strict mode)'),
];

$cdef_item_types = [
	CVDEF_ITEM_TYPE_FUNCTION => __('Function'),
	CVDEF_ITEM_TYPE_OPERATOR => __('Operator'),
	CVDEF_ITEM_TYPE_SPEC_DS  => __('Special Data Source'),
	CVDEF_ITEM_TYPE_CDEF     => __('Another CDEF'),
	CVDEF_ITEM_TYPE_STRING   => __('Custom String')
];

$graph_color_alpha = [
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
];

$tree_sort_types = [
	TREE_ORDERING_INHERIT    => __('Inherit Parent Sorting'),
	TREE_ORDERING_NONE       => __('Manual Ordering (No Sorting)'),
	TREE_ORDERING_ALPHABETIC => __('Alphabetic Ordering'),
	TREE_ORDERING_NATURAL    => __('Natural Ordering'),
	TREE_ORDERING_NUMERIC    => __('Numeric Ordering')
];

$tree_item_types = [
	TREE_ITEM_TYPE_HEADER => __('Header'),
	TREE_ITEM_TYPE_GRAPH  => __('Graph'),
	TREE_ITEM_TYPE_HOST   => __('Device')
];

$host_group_types = [
	HOST_GROUPING_GRAPH_TEMPLATE   => __('Graph Template'),
	HOST_GROUPING_DATA_QUERY_INDEX => __('Data Query Index')
];

$custom_data_source_types = [
	'CURRENT_DATA_SOURCE'            => __('Current Graph Item Data Source'),
	'CURRENT_DATA_SOURCE_PI'         => __('Current Graph Item Polling Interval'),
	'ALL_DATA_SOURCES_NODUPS'        => __('All Data Sources (Do not Include Duplicates)'),
	'ALL_DATA_SOURCES_DUPS'          => __('All Data Sources (Include Duplicates)'),
	'SIMILAR_DATA_SOURCES_NODUPS'    => __('All Similar Data Sources (Do not Include Duplicates)'),
	'SIMILAR_DATA_SOURCES_NODUPS_PI' => __('All Similar Data Sources (Do not Include Duplicates) Polling Interval'),
	'SIMILAR_DATA_SOURCES_DUPS'      => __('All Similar Data Sources (Include Duplicates)'),
	'CURRENT_DS_MINIMUM_VALUE'       => __('Current Data Source Item: Minimum Value'),
	'CURRENT_DS_MAXIMUM_VALUE'       => __('Current Data Source Item: Maximum Value'),
	'CURRENT_DS_LSLFUNCTION'         => __('Current Data Source Item: Least Squares Line Function'),
	'CURRENT_DS_LSLINT'              => __('Current Data Source Item: Least Squares Line Y-intercept'),
	'CURRENT_DS_LSLSLOPE'            => __('Current Data Source Item: Least Squares Line Slope'),
	'CURRENT_DS_LSLCORREL'           => __('Current Data Source Item: Least Squares Line Correlation Coefficient'),
	'CURRENT_GRAPH_MINIMUM_VALUE'    => __('Graph: Lower Limit'),
	'CURRENT_GRAPH_MAXIMUM_VALUE'    => __('Graph: Upper Limit'),
	'COUNT_ALL_DS_NODUPS'            => __('Count of All Data Sources (Do not Include Duplicates)'),
	'COUNT_ALL_DS_DUPS'              => __('Count of All Data Sources (Include Duplicates)'),
	'COUNT_SIMILAR_DS_NODUPS'        => __('Count of All Similar Data Sources (Do not Include Duplicates)'),
	'COUNT_SIMILAR_DS_DUPS'	         => __('Count of All Similar Data Sources (Include Duplicates)')
];

$copyrights = [
	'Apache License 2.0'                 => __('Apache License 2.0'),
	'Creative Commons'                   => __('Creative Commons'),
	'GNU General Public License'         => __('GNU General Public License'),
	'MIT License'                        => __('MIT License'),
	'Eclipse Public License version 2.0' => __('Eclipse Public License version 2.0'),
];

if (POLLER_ID || $config['connection'] == 'online') { // @phpstan-ignore-line
	$menu = [
		__('Main Console') => [
			'index.php' => __('Console Page')
		],
		__('Create') => [
			'graphs_new.php'                   => __('New Graphs'),
			'host.php?action=edit&create=true' => __('New Device')
		],
		__('Management') => [
			'host.php'             => __('Devices'),
			'sites.php'            => __('Sites'),
			'tree.php'             => __('Trees'),
			'graphs.php'           => __('Graphs'),
			'data_sources.php'     => __('Data Sources'),
			'aggregate_graphs.php' => __('Aggregates'),
		],
		__('Data Collection') => [
			'pollers.php'      => __('Data Collectors'),
			'data_queries.php' => __('Data Queries'),
			'data_input.php'   => __('Data Input Methods')
		],
		__('Templates') => [
			'host_templates.php'      => __('Device'),
			'graph_templates.php'     => __('Graph'),
			'data_templates.php'      => __('Data Source'),
			'aggregate_templates.php' => __('Aggregate'),
			'color_templates.php'     => __('Color')
		],
		__('Automation') => [
			'automation_networks.php'    => __('Networks'),
			'automation_devices.php'     => __('Discovered Devices'),
			'automation_templates.php'   => __('Device Rules'),
			'automation_graph_rules.php' => __('Graph Rules'),
			'automation_tree_rules.php'  => __('Tree Rules'),
		],
		__('Presets') => [
			'data_source_profiles.php' => __('Data Profiles'),
			'automation_snmp.php'      => __('SNMP'),
			'cdef.php'                 => __('CDEFs'),
			'vdef.php'                 => __('VDEFs'),
			'color.php'                => __('Colors'),
			'gprint_presets.php'       => __('GPRINTs'),
		],
		__('Import/Export') => [
			'package_import.php'   => __('Import Packages'),
			'templates_import.php' => __('Import Templates'),
			'templates_export.php' => __('Export Templates'),
			'package.php'          => __('Create Packages'),
			'package_repos.php'    => __('Repositories'),
			'package_keys.php'     => __('Public Keys'),
		],
		__('Configuration')  => [
			'settings.php'         => __('Settings'),
			'user_admin.php'       => __('Users'),
			'user_group_admin.php' => __('User Groups'),
			'user_domains.php'     => __('User Domains')
		],
		__('Utilities') => [
			'utilities.php'  => __('System Utilities'),
			'rrdcleaner.php' => __('RRDfile Cleaner'),
			'managers.php'   => __('SNMP Managers'),
			'links.php'      => __('External Links'),
			'user_log.php'   => __('User Log'),
		],
		__('Troubleshooting') => [
			'data_debug.php' => __('Data Sources'),
			'rrdcheck.php'   => __('RRDfile Checker'),
			'support.php'    => __('Technical Info'),
			'changelog.php'  => __('Change Log'),
		]
	];

	if (POLLER_ID > 1) {
		unset($menu[__('Utilities')]['rrdcleaner.php']);
		unset($menu[__('Troubleshooting')]['rrdcheck.php']);
	}
} else {
	$menu = [
		__('Management') => [
			'host.php' => __('Devices'),
		],
		__('Data Collection') => [
			'pollers.php' => __('Data Collectors'),
		],
		__('Configuration')  => [
			'settings.php' => __('Settings'),
		],
		__('Utilities') => [
			'utilities.php' => __('System Utilities'),
			'user_log.php'  => __('User Log'),
		]
	];
}

$menu_glyphs = [
	__('Main Console')    => 'ti ti-map',
	__('Create')          => 'ti ti-chart-area-line',
	__('Management')      => 'ti ti-devices',
	__('Data Collection') => 'ti ti-affiliate',
	__('Templates')       => 'ti ti-template',
	__('Automation')      => 'ti ti-automation',
	__('Presets')         => 'ti ti-archive',
	__('Import/Export')   => 'ti ti-transfer',
	__('Configuration')   => 'ti ti-adjustments-cog',
	__('Utilities')       => 'ti ti-tools',
	__('External Links')  => 'ti ti-external-link',
	__('Support')         => 'ti ti-help',
	__('Troubleshooting') => 'ti ti-bug'
];

$device_classes = [
	'wireless'     => __('Access Points, Controllers'),
	'application'  => __('Application Related'),
	'cacti'        => __('Cacti Related'),
	'database'     => __('Database Related'),
	'facilities'   => __('Facilities Related'),
	'general'      => __('Generic Device'),
	'hpc'          => __('HPC/Grid Computing'),
	'hypervisor'   => __('Hypervisor Related'),
	'remotemgmt'   => __('ILO, IPMI, iDrac, etc.'),
	'license'      => __('Licensing Related'),
	'linux'        => __('Linux Related'),
	'loadbalancer' => __('Load Balancer'),
	'switch'       => __('Network Switch'),
	'router'       => __('Network Router'),
	'nassan'       => __('NAS/SAN Related'),
	'firewall'     => __('Network Firewalls'),
	'power'        => __('Power Related PDUs/STS, etc.'),
	'printer'      => __('Printer Related'),
	'storage'      => __('Storage Related'),
	'telephony'    => __('Telco Related'),
	'webserver'    => __('Web Server Related'),
	'windows'      => __('Windows Related'),
	'ups'          => __('UPS Related'),
	'unassigned'   => __('Unassigned')
];

$graph_template_classes = [
	'amon'            => __('Amon Server Stats'),
	'amqp'            => __('AMQP Stats'),
	'cpu'             => __('CPU Stats'),
	'disk'            => __('Disk Stats'),
	'diskio'          => __('Disk I/O Stats'),
	'health'          => __('Health Stats'),
	'http'            => __('Webserver Stats'),
	'kafka'           => __('Kafka Stats'),
	'kernel'          => __('Kernel Stats'),
	'kernel_vmstat'   => __('Kernel Memory'),
	'linux_cpu'       => __('Linux CPU Stats'),
	'linux_sysctl_fs' => __('Linux Kernel Params'),
	'mongodb'         => __('MongoDB Stats'),
	'mysql'           => __('MariaDB/MySQL Stats'),
	'mem'             => __('Memory Stats'),
	'mqqt'            => __('Mosquitto Stats'),
	'net'             => __('Network Stats'),
	'process'         => __('Process Stats'),
	'qos'             => __('QOS Stats'),
	'swap'            => __('Swap Stats'),
	'syslog'          => __('Syslog Stats'),
	'system'          => __('System Stats'),
	'unassigned'      => __('Unassigned'),
	'unspecified'     => __('Unspecified'),
];

if ((isset($_SESSION[SESS_USER_ID]))) {
	if (db_table_exists('external_links')) {
		$consoles = db_fetch_assoc('SELECT id, title, extendedstyle
			FROM external_links
			WHERE style="CONSOLE"
			AND enabled="on"
			ORDER BY extendedstyle, sortorder, id');

		if (cacti_sizeof($consoles)) {
			foreach ($consoles as $page) {
				if (!CACTI_WEB || is_realm_allowed($page['id'] + 10000)) {
					$menuname = (isset($page['extendedstyle']) && $page['extendedstyle'] != '' ? htmle($page['extendedstyle']) : __('External Links'));

					$menu[$menuname]['link.php?id=' . $page['id']] = htmle($page['title']);
				}
			}
		}
	}
}

$log_tail_lines = [
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
];

$item_rows = [
	-1    => __('Default'),
	10    => '10',
	15    => '15',
	16    => '16',
	17    => '17',
	18    => '18',
	19    => '19',
	20    => '20',
	21    => '21',
	22    => '22',
	23    => '23',
	24    => '24',
	25    => '25',
	26    => '26',
	27    => '27',
	30    => '30',
	40    => '40',
	44    => '44',
	45    => '45',
	50    => '50',
	100   => '100',
	250   => '250',
	500   => '500',
	750   => '750',
	1000  => '1000',
	2000  => '2000',
	3000  => '3000',
	4000  => '4000',
	5000  => '5000',
];

// Adjust the number of items rows based upon max_input_vars
$max_size = ini_get('max_input_vars') - 20;

foreach ($item_rows as $index => $row) {
	if ($index > $max_size) {
		unset($item_rows[$index]);
	}
}

$graphs_per_page = [
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
];

$page_refresh_interval = [
	5       => __('%d Seconds', 5),
	10      => __('%d Seconds', 10),
	20      => __('%d Seconds', 20),
	30      => __('%d Seconds', 30),
	60      => __('1 Minute'),
	300     => __('%d Minutes', 5),
	600     => __('%d Minutes', 10),
	9999999 => __('Never')
];

$user_auth_realms = [
	8    => __('Console Access'),
	7    => __('View Graphs'),
	25   => __('Realtime Graphs'),
	20   => __('Update Profile'),
	24   => __('External Links'),

	1    => __('Users/Groups'),
	15   => __('Settings/Utilities'),
	23   => __('Automation'),
	26   => __('Installation/Upgrades'),

	2    => __('Data Input Methods'),
	13   => __('Data Queries'),

	3    => __('Sites/Devices/Data'),
	5    => __('Graphs'),
	4    => __('Trees'),
	1043 => __('Spike Handling'),

	9    => __('Data Source Profiles'),
	14   => __('Presets'),

	10   => __('Graph Templates'),
	11   => __('Data Templates'),
	12   => __('Device Templates'),

	16   => __('Export Templates'),
	17   => __('Import Templates'),
	29   => __('Package Management'),

	18   => __('Log Administration'),
	19   => __('Log Viewing'),

	21   => __('Reports Administration'),
	22   => __('Reports Creation'),
	27   => __('Show Graph Action Icons'),
	28   => __('Show User Help Links'),
	101  => __('Plugin Administration')
];

$user_auth_roles = [
	__('Normal User')            => [7, 19, 20, 22, 24, 25, 27, 28],
	__('Template Editor')        => [8, 2, 9, 10, 11, 12, 13, 14, 16, 17, 29],
	__('General Administration') => [8, 3, 4, 5, 23, 1043],
	__('System Administration')  => [8, 15, 26, 1, 18, 21, 101]
];

$user_auth_realm_filenames = [
	'about.php'                  => -1,
	'cdef.php'                   => 14,
	'clog.php'                   => 18,
	'clog_user.php'              => 19,
	'color.php'                  => 5,
	'data_debug.php'             => 15,
	'data_input.php'             => 2,
	'data_sources.php'           => 3,
	'data_source_profiles.php'   => 9,
	'data_templates.php'         => 11,
	'gprint_presets.php'         => 5,
	'graph.php'                  => 7,
	'graph_image.php'            => 7,
	'graph_json.php'             => 7,
	'graph_xport.php'            => 7,
	'graph_templates.php'        => 10,
	'graph_view.php'             => 7,
	'graph_realtime.php'         => 25,
	'graphs.php'                 => 5,
	'graphs_new.php'             => 5,
	'sites.php'                  => 3,
	'pollers.php'                => 3,
	'host.php'                   => 3,
	'host_templates.php'         => 12,
	'index.php'                  => 8,
	'install.php'                => 26,
	'step_json.php'              => 26,
	'managers.php'               => 15,
	'rrdcleaner.php'             => 15,
	'rrdcheck.php'               => 15,
	'settings.php'               => 15,
	'links.php'                  => 15,
	'data_queries.php'           => 13,
	'templates_export.php'       => 16,
	'templates_import.php'       => 17,
	'package_import.php'         => 17,
	'package.php'                => 29,
	'package_repos.php'          => 29,
	'package_keys.php'           => 29,
	'tree.php'                   => 4,
	'user_admin.php'             => 1,
	'user_domains.php'           => 1,
	'user_group_admin.php'       => 1,
	'utilities.php'              => 15,
	'user_log.php'               => 15,
	'vdef.php'                   => 14,
	'smtp_servers.php'           => 8,
	'email_templates.php'        => 8,
	'event_queue.php'            => 8,
	'smtp_queue.php'             => 8,
	'logout.php'                 => -1,
	'auth_profile.php'           => 20,
	'auth_changepassword.php'    => -1,
	'reports.php'                => 22,
	'automation_graph_rules.php' => 23,
	'automation_tree_rules.php'  => 23,
	'automation_templates.php'   => 23,
	'automation_networks.php'    => 23,
	'automation_devices.php'     => 23,
	'automation_snmp.php'        => 23,
	'color_templates.php'        => 5,
	'aggregate_templates.php'    => 5,
	'aggregate_graphs.php'       => 5,
	'spikekill.php'              => 1043,
	'support.php'                => 15,
	'changelog.php'              => 15,
	'permission_denied.php'      => -1,
	'help.php'                   => -1,
	'oauth2.php'                 => -1
];

$hash_type_codes = [
	'round_robin_archive'       => '15',
	'cdef'                      => '05',
	'cdef_item'                 => '14',
	'gprint_preset'             => '06',
	'data_input_method'         => '03',
	'data_input_field'          => '07',
	'data_template'             => '01',
	'data_template_item'        => '08',
	'graph_template'            => '00',
	'graph_template_item'       => '10',
	'graph_template_input'      => '09',
	'data_query'                => '04',
	'data_query_graph'          => '11',
	'data_query_sv_graph'       => '12',
	'data_query_sv_data_source' => '13',
	'host_template'             => '02',
	'vdef'                      => '18',
	'vdef_item'                 => '19',
	'data_source_profile'       => '20'
];

$hash_type_names = [
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
];

$hash_system_data_inputs = [
	'3eb92bb845b9660a7445cf9740726522', // Get SNMP Data
	'bf566c869ac6443b0c75d1c32b5a350e', // Get SNMP Data (Indexed)
	'80e9e4c4191a5da189ae26d0e237f015', // Get Script Data (Indexed)
	'332111d8b54ac8ce939af87a7eac0c06', // Get Script Server Data (Indexed)
	'7ed649bfa9cd627d7482b7700e88db53', // Get Stream Data
	'8489967af7ec4addacc6a67ca4e36c16'  // Get Stream Data (Indexed)
];

$host_struc = [
	'host_template_id',
	'description',
	'hostname',
	'site_id',
	'poller_id',
	'notes',
	'snmp_options',
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
	'snmp_retries',
	'max_oids',
	'bulk_walk_size',
	'device_threads',
	'availability_method',
	'location',
	'external_id',
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
];

$graph_timespans = [
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
];

// ***** MUST BE KEPT IN SYNC WITH graph_timeshifts_vals *********
$graph_timeshifts = [
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
];

// ***** MUST BE KEPT IN SYNC WITH graph_timeshifts *********
$graph_timeshifts_vals = [
	GTS_HALF_HOUR => sprintf('%d Min', 30),
	GTS_1_HOUR    => sprintf('%d Hour', 1),
	GTS_2_HOURS   => sprintf('%d Hours', 2),
	GTS_4_HOURS   => sprintf('%d Hours', 4),
	GTS_6_HOURS   => sprintf('%d Hours', 6),
	GTS_12_HOURS  => sprintf('%d Hours', 12),
	GTS_1_DAY     => sprintf('%d Day', 1),
	GTS_2_DAYS    => sprintf('%d Days', 2),
	GTS_3_DAYS    => sprintf('%d Days', 3),
	GTS_4_DAYS    => sprintf('%d Days', 4),
	GTS_1_WEEK    => sprintf('%d Week', 1),
	GTS_2_WEEKS   => sprintf('%d Weeks', 2),
	GTS_1_MONTH   => sprintf('%d Month', 1),
	GTS_2_MONTHS  => sprintf('%d Months', 2),
	GTS_3_MONTHS  => sprintf('%d Months', 3),
	GTS_4_MONTHS  => sprintf('%d Months', 4),
	GTS_6_MONTHS  => sprintf('%d Months', 6),
	GTS_1_YEAR    => sprintf('%d Year', 1),
	GTS_2_YEARS   => sprintf('%d Years', 2)
];

$graph_weekdays = [
	WD_SUNDAY    => date('l', strtotime('Sunday')),
	WD_MONDAY    => date('l', strtotime('Monday')),
	WD_TUESDAY   => date('l', strtotime('Tuesday')),
	WD_WEDNESDAY => date('l', strtotime('Wednesday')),
	WD_THURSDAY  => date('l', strtotime('Thursday')),
	WD_FRIDAY    => date('l', strtotime('Friday')),
	WD_SATURDAY  => date('l', strtotime('Saturday'))
];

$dateformats = [
	GD_MO_D_Y => __('Month Number, Day, Year'),
	GD_MN_D_Y => __('Month Name, Day, Year'),
	GD_D_MO_Y => __('Day, Month Number, Year'),
	GD_D_MN_Y => __('Day, Month Name, Year'),
	GD_Y_MO_D => __('Year, Month Number, Day'),
	GD_Y_MN_D => __('Year, Month Name, Day')
];

$datechar = [
	GDC_HYPHEN => '-',
	GDC_SLASH  => '/',
	GDC_DOT    => '.'
];

$dsstats_refresh_interval = [
	'boost' => __('After Boost'),
	'60'    => __('1 Hour'),
	'120'   => __('%d Hours', 2),
	'180'   => __('%d Hours', 3),
	'240'   => __('%d Hours', 4),
	'300'   => __('%d Hours', 5),
	'360'   => __('%d Hours', 6)
];

$dsstats_max_memory = [
	'32'   => __('%d MBytes', 32),
	'64'   => __('%d MBytes', 64),
	'128'  => __('%d MBytes', 128),
	'256'  => __('%d MBytes', 256),
	'512'  => __('%d MBytes', 512),
	'1024' => __('1 GByte'),
	'1536' => __('%s GBytes', '1.5'),
	'2048' => __('%d GBytes', 2),
	'3072' => __('%d GBytes', 3)
];

$dsstats_hourly_avg = [
	'60'  => __('1 Hour'),
	'120' => __('%d Hours', 2),
	'180' => __('%d Hours', 3),
	'240' => __('%d Hours', 4),
	'300' => __('%d Hours', 5),
	'360' => __('%d Hours', 6)
];

$rrdcheck_intervals = [
	'boost' => __('After Boost'),
	'60'    => __('1 Hour'),
	'240'   => __('%d Hours', 4),
	'1440'  => __('%d Hours', 24)
];

$boost_max_rows_per_select = [
	'2000'   => __('2,000 Data Source Items'),
	'5000'   => __('5,000 Data Source Items'),
	'10000'  => __('10,000 Data Source Items'),
	'15000'  => __('15,000 Data Source Items'),
	'25000'  => __('25,000 Data Source Items'),
	'50000'  => __('50,000 Data Source Items (Default)'),
	'100000' => __('100,000 Data Source Items'),
	'200000' => __('200,000 Data Source Items'),
	'400000' => __('400,000 Data Source Items')
];

$boost_utilities_interval = [
	'999999' => __('Disabled'),
	'5'      => __('%d Seconds', 5),
	'10'     => __('%d Seconds', 10),
	'15'     => __('%d Seconds', 15),
	'20'     => __('%d Seconds', 20),
	'30'     => __('%d Seconds', 30),
	'60'     => __('1 Minute'),
	'300'    => __('%d Minutes', 5)
];

$boost_refresh_interval = [
	'30'  => __('%d Minutes', 30),
	'60'  => __('1 Hour'),
	'120' => __('2 Hours', 2),
	'240' => __('4 Hours', 4),
	'360' => __('6 Hours', 6)
];

$boost_max_runtime = [
	'1200' => __('%d Minutes', 20),
	'2400' => __('%d Minutes', 40),
	'3600' => __('1 Hour'),
	'4800' => __('%s Hours', '1.5')
];

$boost_max_memory = [
	'256'  => __('%d MBytes', 256),
	'512'  => __('%d MBytes', 512),
	'1024' => __('%d GByte', 1),
	'1536' => __('%s GBytes', '1.5'),
	'2048' => __('%d GBytes', 2),
	'3072' => __('%d GBytes', 3),
	'4096' => __('%d GBytes', 4),
	'5120' => __('%d GBytes', 5),
	'6144' => __('%d GBytes', 6),
	'8192' => __('%d GBytes', 8),
	'-1'   => __('Unlimited')
];

$realtime_window = [
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
];

$realtime_refresh = [
	1   => __('%d Seconds', 1),
	2   => __('%d Seconds', 2),
	5   => __('%d Seconds', 5),
	10  => __('%d Seconds', 10),
	15  => __('%d Seconds', 15),
	20  => __('%d Seconds', 20),
	30  => __('%d Seconds', 30),
	60  => __('1 Minute'),
	120 => __('%d Minutes', 2)
];

$attachment_sizes = [
	1048576   => __('1 Megabyte'),
	2097152   => __('%d Megabytes', 2),
	4194304   => __('%d Megabytes', 4),
	10485760  => __('%d Megabytes', 10),
	20971520  => __('%d Megabytes', 20),
	52428800  => __('%d Megabytes', 50),
	104857600 => __('%d Megabytes', 100),
];

$reports_actions = [
	REPORTS_DELETE    => __('Delete'),
	REPORTS_DISABLE   => __('Disable'),
	REPORTS_DUPLICATE => __('Duplicate'),
	REPORTS_ENABLE    => __('Enable'),
	REPORTS_SEND_NOW  => __('Send Now'),
];

if (!CACTI_WEB || is_realm_allowed(21)) {
	$reports_actions[REPORTS_OWN] = __('Take Ownership');
}

$attach_types = [
	REPORTS_TYPE_INLINE_PNG => __('Inline PNG Image'),
];

if (extension_loaded(REPORTS_EXTENSION_GD)) {
	$attach_types[REPORTS_TYPE_INLINE_JPG] = __('Inline JPEG Image');
	$attach_types[REPORTS_TYPE_INLINE_GIF] = __('Inline GIF Image');
}

$attach_types[REPORTS_TYPE_ATTACH_PNG] = __('Attached PNG Image');

if (extension_loaded(REPORTS_EXTENSION_GD)) {
	$attach_types[REPORTS_TYPE_ATTACH_JPG] = __('Attached JPEG Image');
	$attach_types[REPORTS_TYPE_ATTACH_GIF] = __('Attached GIF Image');
}

$item_types = [
	REPORTS_ITEM_TEXT  => __('Text'),
	REPORTS_ITEM_TREE  => __('Tree'),
	REPORTS_ITEM_GRAPH => __('Graph'),
	REPORTS_ITEM_HOST  => __('Device'),
	REPORTS_ITEM_HR    => __('Horizontal Rule')
];

$alignment = [
	REPORTS_ALIGN_LEFT   => __('left'),
	REPORTS_ALIGN_CENTER => __('center'),
	REPORTS_ALIGN_RIGHT  => __('right')
];

$reports_interval = [
	REPORTS_SCHED_INTVL_MINUTE        => __('Minute(s)'),
	REPORTS_SCHED_INTVL_HOUR          => __('Hour(s)'),
	REPORTS_SCHED_INTVL_DAY           => __('Day(s)'),
	REPORTS_SCHED_INTVL_WEEK          => __('Week(s)'),
	REPORTS_SCHED_INTVL_MONTH_DAY     => __('Month(s), Day of Month'),
	REPORTS_SCHED_INTVL_MONTH_WEEKDAY => __('Month(s), Day of Week'),
	REPORTS_SCHED_INTVL_YEAR          => __('Year(s)'),
];

$agg_graph_types = [
	AGGREGATE_GRAPH_TYPE_KEEP         => __('Keep Graph Types'),
	AGGREGATE_GRAPH_TYPE_KEEP_STACKED => __('Keep Type and STACK'),
	GRAPH_ITEM_TYPE_STACK             => __('Convert to AREA/STACK Graph'),
	GRAPH_ITEM_TYPE_LINE1             => __('Convert to LINE1 Graph'),
	GRAPH_ITEM_TYPE_LINE2             => __('Convert to LINE2 Graph'),
	GRAPH_ITEM_TYPE_LINE3             => __('Convert to LINE3 Graph'),
	AGGREGATE_GRAPH_TYPE_LINE1_STACK  => __('Convert to LINE1/STACK Graph'),
	AGGREGATE_GRAPH_TYPE_LINE2_STACK  => __('Convert to LINE2/STACK Graph'),
	AGGREGATE_GRAPH_TYPE_LINE3_STACK  => __('Convert to LINE3/STACK Graph'),
];

$agg_totals = [
	AGGREGATE_TOTAL_NONE => __('No Totals'),
	AGGREGATE_TOTAL_ALL  => __('Print All Legend Items'),
	AGGREGATE_TOTAL_ONLY => __('Print Totaling Legend Items Only'),
];

$agg_totals_type = [
	AGGREGATE_TOTAL_TYPE_SIMILAR => __('Total Similar Data Sources'),
	AGGREGATE_TOTAL_TYPE_ALL     => __('Total All Data Sources'),
];

$agg_order_types = [
	AGGREGATE_ORDER_NONE       => __('No Reordering'),
	AGGREGATE_ORDER_DS_GRAPH   => __('Data Source, Graph'),
	AGGREGATE_ORDER_GRAPH_DS   => __('Graph, Data Source'),
	AGGREGATE_ORDER_BASE_GRAPH => __('Base Graph Order')
];

// operators for use with SQL/pattern matching
$automation_op_array = [
	'display' => [
		AUTOMATION_OP_NONE         => __('None'),
		AUTOMATION_OP_MATCHES      => __('Matches (=)'),
		AUTOMATION_OP_MATCHES_NOT  => __('Is Not Equal to (!=)'),
		AUTOMATION_OP_LT           => __('Is Less Than (<)'),
		AUTOMATION_OP_LE           => __('Is Less Than or Equal (<=)'),
		AUTOMATION_OP_GT           => __('Is Greater Than (>=)'),
		AUTOMATION_OP_GE           => __('Is Greater Than or Equal'),
		AUTOMATION_OP_UNKNOWN      => __('Is NULL'),
		AUTOMATION_OP_NOT_UNKNOWN  => __('Is Not NULL'),
		AUTOMATION_OP_EMPTY        => __('Is Empty'),
		AUTOMATION_OP_NOT_EMPTY    => __('Is Not Empty'),
		AUTOMATION_OP_CONTAINS     => __('Contains (LIKE %xxx%)'),
		AUTOMATION_OP_CONTAINS_NOT => __('Does Not Contain (NOT LIKE %xxx%)'),
		AUTOMATION_OP_BEGINS       => __('Begins with (LIKE xxx%)'),
		AUTOMATION_OP_BEGINS_NOT   => __('Does Not Begin with (NOT LIKE xxx%)'),
		AUTOMATION_OP_ENDS         => __('Ends with (LIKE %xxx)'),
		AUTOMATION_OP_ENDS_NOT     => __('Does Not End with (NOT LIKE %xxx)'),
		AUTOMATION_OP_REGEXP       => __('Matches Regular Expression'),
		AUTOMATION_OP_NOT_REGEXP   => __('Does Not Match Regular Expression'),
	],
	'op' => [
		AUTOMATION_OP_NONE          => '',
		AUTOMATION_OP_MATCHES       => '=',
		AUTOMATION_OP_MATCHES_NOT   => '!=',
		AUTOMATION_OP_LT            => '<',
		AUTOMATION_OP_LE            => '<=',
		AUTOMATION_OP_GT            => '>',
		AUTOMATION_OP_GE            => '>=',
		AUTOMATION_OP_UNKNOWN       => 'IS NULL',
		AUTOMATION_OP_NOT_UNKNOWN   => 'IS NOT NULL',
		AUTOMATION_OP_EMPTY         => "LIKE ''",
		AUTOMATION_OP_NOT_EMPTY     => "NOT LIKE ''",
		AUTOMATION_OP_CONTAINS      => 'LIKE',
		AUTOMATION_OP_CONTAINS_NOT  => 'NOT LIKE',
		AUTOMATION_OP_BEGINS        => 'LIKE',
		AUTOMATION_OP_BEGINS_NOT    => 'NOT LIKE',
		AUTOMATION_OP_ENDS          => 'LIKE',
		AUTOMATION_OP_ENDS_NOT      => 'NOT LIKE',
		AUTOMATION_OP_REGEXP        => 'REGEXP',
		AUTOMATION_OP_NOT_REGEXP    => 'NOT REGEXP',
	],
	'binary' => [
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
	],
	'pre' => [
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
	],
	'post' => [
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
	]
];

$automation_oper = [
	AUTOMATION_OPER_NULL            => '',
	AUTOMATION_OPER_AND             => 'AND',
	AUTOMATION_OPER_OR              => 'OR',
	AUTOMATION_OPER_LEFT_BRACKET    => '(',
	AUTOMATION_OPER_RIGHT_BRACKET   => ')',
];

$automation_tree_item_types  = [
	TREE_ITEM_TYPE_GRAPH => __('Graph'),
	TREE_ITEM_TYPE_HOST  => __('Device')
];

$automation_tree_header_types  = [
	AUTOMATION_TREE_ITEM_TYPE_STRING => __('Fixed String'),
];

$automation_frequencies = [
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
];

$logrotate_frequency = [
	'1'  => __('Daily'),
	'7'  => __('Weekly'),
	'30' => __('Monthly')
];

$no_session_write = [
	'graph_xport.php',
	'graph_image.php',
	'graph_json.php'
];

$i18n_months = [
	'January'	  => __('January'),
	'February'	 => __('February'),
	'March'		   => __('March'),
	'April'		   => __('April'),
	'May'		     => __('May'),
	'June'		    => __('June'),
	'July'		    => __('July'),
	'August'	   => __('August'),
	'September'	=> __('September'),
	'October'	  => __('October'),
	'November'	 => __('November'),
	'December'	 => __('December'),
];

$i18n_months_short = [
	'Jan' => __x('A short textual representation of a month, three letters', 'Jan'),
	'Feb' => __x('A short textual representation of a month, three letters', 'Feb'),
	'Mar' => __x('A short textual representation of a month, three letters', 'Mar'),
	'Arp' => __x('A short textual representation of a month, three letters', 'Apr'),
	'May' => __x('A short textual representation of a month, three letters', 'May'),
	'Jun' => __x('A short textual representation of a month, three letters', 'Jun'),
	'Jul' => __x('A short textual representation of a month, three letters', 'Jul'),
	'Aug' => __x('A short textual representation of a month, three letters', 'Aug'),
	'Sep' => __x('A short textual representation of a month, three letters', 'Sep'),
	'Oct' => __x('A short textual representation of a month, three letters', 'Oct'),
	'Nov' => __x('A short textual representation of a month, three letters', 'Nov'),
	'Dec' => __x('A short textual representation of a month, three letters', 'Dec'),
];

$i18n_supported_languages = [
	CACTI_LANGUAGE_HANDLER_DEFAULT => __('Use the First Handler Found'),
];

if (is_dir(CACTI_PATH_INCLUDE . '/vendor/phpgettext')) {
	$i18n_supported_languages[CACTI_LANGUAGE_HANDLER_PHPGETTEXT]  = __('Use the PHP GetText Handler');
}

if (is_dir(CACTI_PATH_INCLUDE . '/vendor/monotranslator')) {
	$i18n_supported_languages[CACTI_LANGUAGE_HANDLER_MOTRANSLATOR]  = __('Use the MonoTranslator GetText Handler');
}

$i18n_weekdays = [
	'Sunday'    => __('Sunday'),
	'Monday'    => __('Monday'),
	'Tuesday'   => __('Tuesday'),
	'Wednesday' => __('Wednesday'),
	'Thursday'  => __('Thursday'),
	'Friday'    => __('Friday'),
	'Saturday'  => __('Saturday')
];

$i18n_weekdays_short = [
	'Sun' => __x('A textual representation of a day, three letters', 'Sun'),
	'Mon' => __x('A textual representation of a day, three letters', 'Mon'),
	'Tue' => __x('A textual representation of a day, three letters', 'Tue'),
	'Wed' => __x('A textual representation of a day, three letters', 'Wed'),
	'Thu' => __x('A textual representation of a day, three letters', 'Thu'),
	'Fri' => __x('A textual representation of a day, three letters', 'Fri'),
	'Sat' => __x('A textual representation of a day, three letters', 'Sat')
];

$i18n_languages = [
	__('Arabic'),
	__('Bulgarian'),
	__('Chinese (China)'),
	__('Chinese (Taiwan)'),
	__('Dutch'),
	__('English'),
	__('French'),
	__('German'),
	__('Greek'),
	__('Hebrew'),
	__('Hindi'),
	__('Italian'),
	__('Japanese'),
	__('Korean'),
	__('Polish'),
	__('Portuguese'),
	__('Portuguese (Brazil)'),
	__('Russian'),
	__('Spanish'),
	__('Swedish'),
	__('Turkish'),
	__('Vietnamese')
];

$i18n_themes = [
	__('Classic'),
	__('Modern'),
	__('Dark'),
	__('Paper-plane'),
	__('Paw'),
	__('Sunrise'),
];

$database_statuses = [
	0 => __('[Fail]'),
	1 => __('[Warning]'),
	2 => __('[Restart]'),
	3 => __('[Success]'),
	4 => __('[Skipped]'),
];

$navigation = [
	'auth_profile.php:' => [
		'title'   => __('User Profile (Edit)'),
		'mapping' => '',
		'url'     => '',
		'level'   => '0'
	],
	'auth_profile.php:edit' => [
		'title'   => __('User Profile (Edit)'),
		'mapping' => '',
		'url'     => '',
		'level'   => '0'
	],
	'graph_view.php:' => [
		'title'   => __('Graphs'),
		'mapping' => '',
		'url'     => 'graph_view.php',
		'level'   => '0'
	],
	'graph_view.php:tree' => [
		'title'   => __('Tree Mode'),
		'mapping' => 'graph_view.php:',
		'url'     => 'graph_view.php?action=tree',
		'level'   => '0'
	],
	'graph_view.php:tree_content' => [
		'title'   => __('Tree Mode'),
		'mapping' => 'graph_view.php:',
		'url'     => 'graph_view.php?action=tree',
		'level'   => '0'
	],
	'graph_view.php:list' => [
		'title'   => __('List Mode'),
		'mapping' => '',
		'url'     => 'graph_view.php?action=list',
		'level'   => '0'
	],
	'graph_view.php:preview' => [
		'title'   => __('Preview Mode'),
		'mapping' => '',
		'url'     => 'graph_view.php?action=preview',
		'level'   => '0'
	],
	'graph_view.php:view-preview' => [
		'title'   => __('Single Graph View'),
		'mapping' => 'graph_view.php:preview',
		'url'     => 'graph_view.php?action=view',
		'level'   => '1'
	],
	'graph_view.php:view-tree' => [
		'title'   => __('Single Graph View'),
		'mapping' => 'graph_view.php:tree',
		'url'     => 'graph_view.php?action=view',
		'level'   => '1'
	],
	'graph_view.php:zoom-preview' => [
		'title'   => __('Graph Details View'),
		'mapping' => 'graph_view.php:preview',
		'url'     => 'graph_view.php?action=zoom',
		'level'   => '1'
	],
	'graph_view.php:zoom-tree' => [
		'title'   => __('Graph Details View'),
		'mapping' => 'graph_view.php:tree',
		'url'     => 'graph_view.php?action=zoom',
		'level'   => '1'
	],
	'graph_view.php:properties-preview' => [
		'title'   => __('Graph Properties'),
		'mapping' => 'graph_view.php:preview',
		'url'     => 'graph_view.php?action=properties',
		'level'   => '1'
	],
	'graph_view.php:properties-tree' => [
		'title'   => __('Graph Properties'),
		'mapping' => 'graph_view.php:tree',
		'url'     => 'graph_view.php?action=properties',
		'level'   => '1'
	],
	'graph.php:' => [
		'title'   => '|current_graph_title|',
		'mapping' => CACTI_PATH_URL . 'index.php:',
		'level'   => '1'
	],
	'graph.php:view' => [
		'title'   => '|current_graph_title|',
		'mapping' => CACTI_PATH_URL . 'index.php:',
		'level'   => '1'
	],
	'graph.php:zoom' => [
		'title'   => '|current_graph_title|',
		'mapping' => CACTI_PATH_URL . 'index.php:',
		'level'   => '1'
	],
	'graph.php:update_timespan' => [
		'title'   => '|current_graph_title|',
		'mapping' => CACTI_PATH_URL . 'index.php:',
		'level'   => '1'
	],
	'index.php:' => [
		'title'   => __('Console'),
		'mapping' => '',
		'url'     => CACTI_PATH_URL . 'index.php',
		'level'   => '0'
	],
	'index.php:login' => [
		'title'   => __('Console'),
		'mapping' => '',
		'url'     => CACTI_PATH_URL . 'index.php',
		'level'   => '0'
	],
	'graphs.php:' => [
		'title'   => __('Graph Management'),
		'mapping' => 'index.php:',
		'url'     => 'graphs.php',
		'level'   => '1'
	],
	'graphs.php:graph_edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,graphs.php:',
		'url'     => '',
		'level'   => '2'
	],
	'graphs.php:graph_diff' => [
		'title'   => __('Change Graph Template'),
		'mapping' => 'index.php:,graphs.php:,graphs.php:graph_edit',
		'url'     => '',
		'level'   => '3'
	],
	'graphs.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,graphs.php:',
		'url'     => '',
		'level'   => '2'
	],
	'graphs.php:item_edit' => [
		'title'   => __('Graph Items'),
		'mapping' => 'index.php:,graphs.php:,graphs.php:graph_edit',
		'url'     => '',
		'level'   => '3'
	],
	'graphs_new.php:' => [
		'title'   => __('Create New Graphs'),
		'mapping' => 'index.php:',
		'url'     => 'graphs_new.php',
		'level'   => '1'
	],
	'graphs_new.php:save' => [
		'title'   => __('Create Graphs from Data Query'),
		'mapping' => 'index.php:,graphs_new.php:',
		'url'     => '',
		'level'   => '2'
	],
	'gprint_presets.php:' => [
		'title'   => __('GPRINT Presets'),
		'mapping' => 'index.php:',
		'url'     => 'gprint_presets.php',
		'level'   => '1'
	],
	'gprint_presets.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,gprint_presets.php:',
		'url'     => '',
		'level'   => '2'
	],
	'gprint_presets.php:remove' => [
		'title'   => __('(Remove)'),
		'mapping' => 'index.php:,gprint_presets.php:',
		'url'     => '',
		'level'   => '2'
	],
	'cdef.php:' => [
		'title'   => __('CDEFs'),
		'mapping' => 'index.php:',
		'url'     => 'cdef.php',
		'level'   => '1'
	],
	'cdef.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,cdef.php:',
		'url'     => '',
		'level'   => '2'
	],
	'cdef.php:remove' => [
		'title'   => __('(Remove)'),
		'mapping' => 'index.php:,cdef.php:',
		'url'     => '',
		'level'   => '2'
	],
	'cdef.php:item_edit' => [
		'title'   => __('CDEF Items'),
		'mapping' => 'index.php:,cdef.php:,cdef.php:edit',
		'url'     => '',
		'level'   => '3'
	],
	'cdef.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,cdef.php:',
		'url'     => '',
		'level'   => '2'
	],
	'clog.php:' => [
		'title'   => __('View Log'),
		'mapping' => '',
		'url'     => 'clog.php',
		'level'   => '0'
	],
	'clog.php:preview' => [
		'title'   => __('View Log'),
		'mapping' => '',
		'url'     => 'clog.php',
		'level'   => '0'
	],
	'clog_user.php:' => [
		'title'   => __('View Log'),
		'mapping' => '',
		'url'     => 'clog_user.php',
		'level'   => '0'
	],
	'clog_user.php:preview' => [
		'title'   => __('View Log'),
		'mapping' => '',
		'url'     => 'clog_user.php',
		'level'   => '0'
	],
	'tree.php:' => [
		'title'   => __('Graph Trees'),
		'mapping' => 'index.php:',
		'url'     => 'tree.php',
		'level'   => '1'
	],
	'tree.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,tree.php:',
		'url'     => '',
		'level'   => '2'
	],
	'pollers.php:' => [
		'title'   => __('Data Collectors'),
		'mapping' => 'index.php:',
		'url'     => 'pollers.php',
		'level'   => '1'
	],
	'pollers.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,pollers.php:',
		'url'     => '',
		'level'   => '2'
	],
	'links.php:' => [
		'title'   => __('External Links'),
		'mapping' => 'index.php:',
		'url'     => 'links.php',
		'level'   => '1'
	],
	'links.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,links.php:',
		'url'     => '',
		'level'   => '2'
	],
	'color.php:' => [
		'title'   => __('Colors'),
		'mapping' => 'index.php:',
		'url'     => 'color.php',
		'level'   => '1'
	],
	'color.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,color.php:',
		'url'     => '',
		'level'   => '2'
	],
	'graph_templates.php:' => [
		'title'   => __('Graph Templates'),
		'mapping' => 'index.php:',
		'url'     => 'graph_templates.php',
		'level'   => '1'
	],
	'graph_templates.php:template_edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,graph_templates.php:',
		'url'     => '',
		'level'   => '2'
	],
	'graph_templates.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,graph_templates.php:',
		'url'     => '',
		'level'   => '2'
	],
	'graph_templates.php:item_edit' => [
		'title'   => __('Graph Template Items'),
		'mapping' => 'index.php:,graph_templates.php:,graph_templates.php:template_edit',
		'url'     => '',
		'level'   => '3'
	],
	'graph_templates.php:input_edit' => [
		'title'   => __('Graph Item Inputs'),
		'mapping' => 'index.php:,graph_templates.php:,graph_templates.php:template_edit',
		'url'     => '',
		'level'   => '3'
	],
	'graph_templates.php:input_remove' => [
		'title'   => __('(Remove)'),
		'mapping' => 'index.php:,graph_templates.php:,graph_templates.php:template_edit',
		'url'     => '',
		'level'   => '3'
	],
	'host_templates.php:' => [
		'title'   => __('Device Templates'),
		'mapping' => 'index.php:',
		'url'     => 'host_templates.php',
		'level'   => '1'
	],
	'host_templates.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,host_templates.php:',
		'url'     => '',
		'level'   => '2'
	],
	'host_templates.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,host_templates.php:',
		'url'     => '',
		'level'   => '2'
	],
	'data_templates.php:' => [
		'title'   => __('Data Templates'),
		'mapping' => 'index.php:',
		'url'     => 'data_templates.php',
		'level'   => '1'
	],
	'data_templates.php:template_edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,data_templates.php:',
		'url'     => '',
		'level'   => '2'
	],
	'data_templates.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,data_templates.php:',
		'url'     => '',
		'level'   => '2'
	],
	'data_sources.php:' => [
		'title'   => __('Data Sources'),
		'mapping' => 'index.php:',
		'url'     => 'data_sources.php',
		'level'   => '1'
	],
	'data_sources.php:ds_edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,data_sources.php:',
		'url'     => '',
		'level'   => '2'
	],
	'data_sources.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,data_sources.php:',
		'url'     => '',
		'level'   => '2'
	],
	'host.php:' => [
		'title'   => __('Devices'),
		'mapping' => 'index.php:',
		'url'     => 'host.php',
		'level'   => '1'
	],
	'host.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,host.php:',
		'url'     => '',
		'level'   => '2'
	],
	'host.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,host.php:',
		'url'     => '',
		'level'   => '2'
	],
	'rra.php:' => [
		'title'   => __('Round Robin Archives'),
		'mapping' => 'index.php:',
		'url'     => 'rra.php',
		'level'   => '1'
	],
	'rra.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,rra.php:',
		'url'     => '',
		'level'   => '2'
	],
	'rra.php:remove' => [
		'title'   => __('(Remove)'),
		'mapping' => 'index.php:,rra.php:',
		'url'     => '',
		'level'   => '2'
	],
	'data_input.php:' => [
		'title'   => __('Data Input Methods'),
		'mapping' => 'index.php:',
		'url'     => 'data_input.php',
		'level'   => '1'
	],
	'data_input.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,data_input.php:',
		'url'     => '',
		'level'   => '2'
	],
	'data_input.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,data_input.php:',
		'url'     => '',
		'level'   => '2'
	],
	'data_input.php:remove' => [
		'title'   => __('(Remove)'),
		'mapping' => 'index.php:,data_input.php:',
		'url'     => '',
		'level'   => '2'
	],
	'data_input.php:field_edit' => [
		'title'   => __('Data Input Fields'),
		'mapping' => 'index.php:,data_input.php:,data_input.php:edit',
		'url'     => '',
		'level'   => '3'
	],
	'data_input.php:field_remove' => [
		'title'   => __('(Remove Item)'),
		'mapping' => 'index.php:,data_input.php:,data_input.php:edit',
		'url'     => '',
		'level'   => '3'
	],
	'data_queries.php:' => [
		'title'   => __('Data Queries'),
		'mapping' => 'index.php:',
		'url'     => 'data_queries.php',
		'level'   => '1'
	],
	'data_queries.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,data_queries.php:',
		'url'     => '',
		'level'   => '2'
	],
	'data_queries.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,data_queries.php:',
		'url'     => '',
		'level'   => '2'
	],
	'data_queries.php:item_edit' => [
		'title'   => __('Associated Graph Templates'),
		'mapping' => 'index.php:,data_queries.php:,data_queries.php:edit',
		'url'     => '',
		'level'   => '3'
	],
	'data_queries.php:item_remove' => [
		'title'   => __('(Remove Item)'),
		'mapping' => 'index.php:,data_queries.php:,data_queries.php:edit',
		'url'     => '',
		'level'   => '3'
	],
	'rrdcleaner.php:' => [
		'title'   => __('RRD Cleaner'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'rrdcleaner.php',
		'level'   => '2'
	],
	'rrdcleaner.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,rrdcleaner.php:',
		'url'     => 'rrdcleaner.php?action=actions',
		'level'   => '2'
	],
	'rrdcleaner.php:restart' => [
		'title'   => __('List unused Files'),
		'mapping' => 'rrdcleaner.php:',
		'url'     => 'rrdcleaner.php?action=restart',
		'level'   => '2'
	],
	'rrdcheck.php:' => [
		'title'   => __('RRD Check'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'rrdcheck.php',
		'level'   => '2'
	],
	'support.php' => [
		'title'   => __('Technical Support'),
		'mapping' => 'index.php:,support.php:',
		'url'     => 'support.php',
		'level'   => '2'
	],
	'user_log.php:' => [
		'title'   => __('User Log'),
		'mapping' => 'index.php:',
		'url'     => 'user_log.php',
		'level'   => '1'
	],
	'user_log.php:view' => [
		'title'   => __('View User Log'),
		'mapping' => 'index.php:,user_log.php:',
		'url'     => 'user_log.php',
		'level'   => '2'
	],
	'user_log.php:clear' => [
		'title'   => __('Clear User Log'),
		'mapping' => 'index.php:,user_log.php:',
		'url'     => 'user_log.php',
		'level'   => '2'
	],
	'user_log.php:purge' => [
		'title'   => __('Purge User Log'),
		'mapping' => 'index.php:,user_log.php:',
		'url'     => 'user_log.php',
		'level'   => '2'
	],
	'utilities.php:' => [
		'title'   => __('Utilities'),
		'mapping' => 'index.php:',
		'url'     => 'utilities.php',
		'level'   => '1'
	],
	'utilities.php:view_poller_cache' => [
		'title'   => __('View Poller Cache'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'utilities.php',
		'level'   => '2'
	],
	'utilities.php:view_snmp_cache' => [
		'title'   => __('View Data Query Cache'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'utilities.php',
		'level'   => '2'
	],
	'utilities.php:clear_poller_cache' => [
		'title'   => __('View Poller Cache'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'utilities.php',
		'level'   => '2'
	],
	'utilities.php:view_logfile' => [
		'title'   => __('View Log'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'utilities.php',
		'level'   => '2'
	],
	'utilities.php:clear_logfile' => [
		'title'   => __('Clear Log'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'utilities.php',
		'level'   => '2'
	],
	'utilities.php:view_boost_status' => [
		'title'   => __('Boost Status'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'utilities.php',
		'level'   => '2'
	],
	'utilities.php:view_snmpagent_cache' => [
		'title'   => __('View SNMP Agent Cache'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'utilities.php',
		'level'   => '2'
	],
	'utilities.php:view_snmpagent_events' => [
		'title'   => __('View SNMP Agent Notification Log'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'utilities.php',
		'level'   => '2'
	],
	'vdef.php:' => [
		'title'   => __('VDEFs'),
		'mapping' => 'index.php:',
		'url'     => 'vdef.php',
		'level'   => '1'
	],
	'vdef.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,vdef.php:',
		'url'     => 'vdef.php',
		'level'   => '2'
	],
	'vdef.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,vdef.php:',
		'url'     => 'vdef.php',
		'level'   => '2'
	],
	'vdef.php:remove' => [
		'title'   => __('(Remove)'),
		'mapping' => 'index.php:,vdef.php:',
		'url'     => 'vdef.php',
		'level'   => '2'
	],
	'vdef.php:item_edit' => [
		'title'   => __('VDEF Items'),
		'mapping' => 'index.php:,vdef.php:,vdef.php:edit',
		'url'     => '',
		'level'   => '3'
	],
	'managers.php:' => [
		'title'   => __('View SNMP Notification Receivers'),
		'mapping' => 'index.php:,utilities.php:',
		'url'     => 'managers.php',
		'level'   => '2'
	],
	'managers.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,utilities.php:,managers.php:',
		'url'     => '',
		'level'   => '3'
	],
	'settings.php:' => [
		'title'   => __('Cacti Settings'),
		'mapping' => 'index.php:',
		'url'     => 'settings.php',
		'level'   => '1'
	],
	'link.php:' => [
		'title'   => __('External Link'),
		'mapping' => 'index.php:',
		'url'     => 'link.php',
		'level'   => '1'
	],
	'user_admin.php:' => [
		'title'   => __('Users'),
		'mapping' => 'index.php:',
		'url'     => 'user_admin.php',
		'level'   => '1'
	],
	'user_admin.php:user_edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,user_admin.php:',
		'url'     => '',
		'level'   => '2'
	],
	'user_admin.php:actions' => [
		'title'   => __('(Action)'),
		'mapping' => 'index.php:,user_admin.php:',
		'url'     => '',
		'level'   => '2'
	],
	'user_domains.php:' => [
		'title'   => __('User Domains'),
		'mapping' => 'index.php:',
		'url'     => 'user_domains.php',
		'level'   => '1'
	],
	'user_domains.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'user_domains.php:,index.php:',
		'url'     => 'user_domains.php',
		'level'   => '2'
	],
	'user_group_admin.php:' => [
		'title'   => __('User Groups'),
		'mapping' => 'index.php:',
		'url'     => 'user_group_admin.php',
		'level'   => '1'
	],
	'user_group_admin.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,user_group_admin.php:',
		'url'     => '',
		'level'   => '2'
	],
	'user_group_admin.php:actions' => [
		'title'   => __('(Action)'),
		'mapping' => 'index.php:,user_group_admin.php:',
		'url'     => '',
		'level'   => '2'
	],
	'about.php:' => [
		'title'   => __('About Cacti'),
		'mapping' => '',
		'url'     => 'about.php',
		'level'   => '1'
	],
	'help.php:' => [
		'title'   => __('Cacti Help'),
		'mapping' => '',
		'url'     => 'help.php',
		'level'   => '1'
	],
	'templates_export.php:' => [
		'title'   => __('Export Templates'),
		'mapping' => 'index.php:',
		'url'     => 'templates_export.php',
		'level'   => '1'
	],
	'templates_export.php:save' => [
		'title'   => __('Export Results'),
		'mapping' => 'index.php:,templates_export.php:',
		'url'     => 'templates_export.php',
		'level'   => '2'
	],
	'templates_import.php:' => [
		'title'   => __('Import Templates'),
		'mapping' => 'index.php:',
		'url'     => 'templates_import.php',
		'level'   => '1'
	],
	'package_import.php:' => [
		'title'   => __('Import Packages'),
		'mapping' => 'index.php:',
		'url'     => 'package_import.php',
		'level'   => '1'
	],
	'package.php:' => [
		'title'   => __('Package Cacti Template'),
		'mapping' => 'index.php:',
		'url'     => 'package.php',
		'level'   => '1'
	],
	'package_repos.php' => [
		'title'   => __('Package Repositories'),
		'mapping' => 'index.php',
		'url'     => 'package_repos.php',
		'level'   => '1'
	],
	'package_keys.php' => [
		'title'   => __('Package Public Keys'),
		'mapping' => 'index.php',
		'url'     => 'package_keys.php',
		'level'   => '1'
	],
	'reports.php:' => [
		'title'   => __('Reporting'),
		'mapping' => '',
		'url'     => 'reports.php',
		'level'   => '0'
	],
	'reports.php:actions' => [
		'title'   => __('Report Add'),
		'mapping' => 'reports.php:',
		'url'     => 'reports.php',
		'level'   => '1'
	],
	'reports.php:delete' => [
		'title'   => __('Report Delete'),
		'mapping' => 'reports.php:',
		'url'     => 'reports.php',
		'level'   => '1'
	],
	'reports.php:edit' => [
		'title'   => __('Report Edit'),
		'mapping' => 'reports.php:',
		'url'     => 'reports.php?action=edit',
		'level'   => '1'
	],
	'reports.php:item_edit' => [
		'title'   => __('Report Edit Item'),
		'mapping' => 'reports.php:,reports.php:edit',
		'url'     => '',
		'level'   => '2'
	],
	'color_templates.php:' => [
		'title'   => __('Color Templates'),
		'mapping' => 'index.php:',
		'url'     => 'color_templates.php',
		'level'   => '1'
	],
	'color_templates.php:template_edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,color_templates.php:',
		'url'     => '',
		'level'   => '2'
	],
	'color_templates.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,color_templates.php:',
		'url'     => '',
		'level'   => '2'
	],
	'color_templates.php:item_edit' => [
		'title'   => __('Color Template Items'),
		'mapping' => 'index.php:,color_templates.php:,color_templates.php:template_edit',
		'url'     => '',
		'level'   => '3'
	],
	'aggregate_templates.php:' => [
		'title'   => __('Aggregate Templates'),
		'mapping' => 'index.php:',
		'url'     => 'aggregate_templates.php',
		'level'   => '1'
	],
	'aggregate_templates.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,aggregate_templates.php:',
		'url'     => '',
		'level'   => '2'
	],
	'aggregate_templates.php:actions'=> [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,aggregate_templates.php:',
		'url'     => '',
		'level'   => '2'
	],
	'aggregate_graphs.php:' => [
		'title'   => __('Aggregate Graphs'),
		'mapping' => 'index.php:',
		'url'     => 'aggregate_graphs.php',
		'level'   => '1'
	],
	'aggregate_graphs.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,aggregate_graphs.php:',
		'url'     => '',
		'level'   => '2'
	],
	'aggregate_graphs.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,aggregate_graphs.php:',
		'url'     => '',
		'level'   => '2'
	],
	'aggregate_graphs.php:item_edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,aggregate_graphs.php:,aggregate_graphs.php:',
		'url'     => '',
		'level'   => '2'
	],
	'automation_graph_rules.php:' => [
		'title'   => __('Graph Rules'),
		'mapping' => 'index.php:',
		'url'     => 'automation_graph_rules.php',
		'level'   => '1'
	],
	'automation_graph_rules.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,automation_graph_rules.php:',
		'url'     => '',
		'level'   => '2'
	],
	'automation_graph_rules.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,automation_graph_rules.php:',
		'url'     => '',
		'level'   => '2'
	],
	'automation_graph_rules.php:item_edit' => [
		'title'   => __('Graph Rule Items'),
		'mapping' => 'index.php:,automation_graph_rules.php:,automation_graph_rules.php:edit',
		'url'     => '',
		'level'   => '3'
	],
	'automation_tree_rules.php:' => [
		'title'   => __('Tree Rules'),
		'mapping' => 'index.php:',
		'url'     => 'automation_tree_rules.php',
		'level'   => '1'
	],
	'automation_tree_rules.php:edit' => [
		'title'   => __('(Edit)'),
		'mapping' => 'index.php:,automation_tree_rules.php:',
		'url'     => '',
		'level'   => '2'
	],
	'automation_tree_rules.php:actions' => [
		'title'   => __('Actions'),
		'mapping' => 'index.php:,automation_tree_rules.php:',
		'url'     => '',
		'level'   => '2'
	],
	'automation_tree_rules.php:item_edit' => [
		'title'   => __('Tree Rule Items'),
		'mapping' => 'index.php:,automation_tree_rules.php:,automation_tree_rules.php:edit',
		'url'     => '',
		'level'   => '3'
	],
	'changelog.php' => [
		'title'   => __('Change Log'),
		'mapping' => 'index.php:,changelog.php:',
		'url'     => 'changelog.php',
		'level'   => '2'
	],
];

$snmpagent_event_severity = [
	SNMPAGENT_EVENT_SEVERITY_LOW      => 'low',
	SNMPAGENT_EVENT_SEVERITY_MEDIUM   => 'medium',
	SNMPAGENT_EVENT_SEVERITY_HIGH     => 'high',
	SNMPAGENT_EVENT_SEVERITY_CRITICAL => 'critical'
];

$days_from_time_settings = [
	'mods' => [
		'y' => 31536000,
		'd' => 86400,
		'h' => 3600,
		'm' => '60',
		's' => 0,
	],
	'text' => [
		DAYS_FORMAT_SHORT => [
			'y'      => 'y',
			'd'      => 'd',
			'h'      => 'h',
			'm'      => 'm',
			's'      => 's',
			'suffix' => ':',
			'prefix' => '',
		],
		DAYS_FORMAT_MEDIUM => [
			'y'      => __('years'),
			'd'      => __('days'),
			'h'      => __('hrs'),
			'm'      => __('mins'),
			's'      => __('secs'),
			'suffix' => ', ',
			'prefix' => ' ',
		],
		DAYS_FORMAT_MEDIUM_LOG => [
			'y'      => 'years',
			'd'      => 'days',
			'h'      => 'hrs',
			'm'      => 'mins',
			's'      => 'secs',
			'suffix' => ', ',
			'prefix' => ' ',
		],
		DAYS_FORMAT_LONG => [
			'y'      => __('years'),
			'd'      => __('days'),
			'h'      => __('hours'),
			'm'      => __('minutes'),
			's'      => __('seconds'),
			'suffix' => ', ',
			'prefix' => ' ',
		],
		DAYS_FORMAT_LONG_LOG => [
			'y'      => 'years',
			'd'      => 'days',
			'h'      => 'hours',
			'm'      => 'minutes',
			's'      => 'seconds',
			'suffix' => ', ',
			'prefix' => ' ',
		],
	],
];

$graph_sources = [
	0 => __('Not Templated'),
	1 => __('Data Query'),
	2 => __('Template'),
	3 => __('Aggregate'),
];

$sched_types = [
	SCHEDULE_MANUAL         => __('Manual'),
	SCHEDULE_HOURLY         => __('Hourly'),
	SCHEDULE_DAILY          => __('Daily'),
	SCHEDULE_WEEKLY         => __('Weekly'),
	SCHEDULE_MONTHLY        => __('Monthly'),
	SCHEDULE_MONTHLY_ON_DAY => __('Monthly on Day')
];

if (CACTI_SERVER_OS == 'unix') {
	$dejavu_paths = [
		'/usr/share/fonts/dejavu/', // RHEL/CentOS
		'/usr/share/fonts/truetype/', // SLES
		'/usr/share/fonts/truetype/dejavu/', // Ubuntu
		'/usr/local/share/fonts/dejavu/', // FreeBSD
		__DIR__ . '/fonts'  // Built-in
	];
} else {
	$dejavu_paths = [
		'C:/Windows/Fonts/' // Windows
	];
}

$allowed_proxy_headers = [
	'X-Forwarded-For',
	'X-Client-IP',
	'X-Real-IP',
	'X-ProxyUser-Ip',
	'CF-Connecting-IP',
	'True-Client-IP',
	'HTTP_X_FORWARDED',
	'HTTP_X_FORWARDED_FOR',
	'HTTP_X_CLUSTER_CLIENT_IP',
	'HTTP_FORWARDED_FOR',
	'HTTP_FORWARDED',
	'HTTP_CLIENT_IP',
	'REMOTE_ADDR',
];

api_plugin_hook('config_arrays');
