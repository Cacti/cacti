<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

define('CACTI_PHP_VERSION_MINIMUM', '5.4.0');

define('CACTI_ESCAPE_CHARACTER', '"');
define('COPYRIGHT_YEARS', 'Copyright (C) 2004-' . date('Y') . ' The Cacti Group');
define('COPYRIGHT_YEARS_SHORT', '(c) 2004-' . date('Y') . ' - The Cacti Group');

define('HOST_GROUPING_GRAPH_TEMPLATE', 1);
define('HOST_GROUPING_DATA_QUERY_INDEX', 2);

define('TREE_ORDERING_INHERIT', 0);
define('TREE_ORDERING_NONE', 1);
define('TREE_ORDERING_ALPHABETIC', 2);
define('TREE_ORDERING_NUMERIC', 3);
define('TREE_ORDERING_NATURAL', 4);

define('TREE_ITEM_TYPE_HEADER', 1);
define('TREE_ITEM_TYPE_GRAPH', 2);
define('TREE_ITEM_TYPE_HOST', 3);

define('RRDTOOL_OUTPUT_NULL', 0);
define('RRDTOOL_OUTPUT_STDOUT', 1);
define('RRDTOOL_OUTPUT_STDERR', 2);
define('RRDTOOL_OUTPUT_GRAPH_DATA', 3);
define('RRDTOOL_OUTPUT_BOOLEAN', 4);
define('RRDTOOL_OUTPUT_RETURN_STDERR', 5);

define('RRD_FONT_RENDER_NORMAL',  'normal');
define('RRD_FONT_RENDER_LIGHT',   'light');
define('RRD_FONT_RENDER_MONO',    'mono');

define('RRD_GRAPH_RENDER_NORMAL', 'normal');
define('RRD_GRAPH_RENDER_MONO',   'mono');

define('RRD_LEGEND_POS_NORTH',    'north');
define('RRD_LEGEND_POS_SOUTH',    'south');
define('RRD_LEGEND_POS_WEST',     'west');
define('RRD_LEGEND_POS_EAST',     'east');

define('RRD_ALIGN_NONE',          '');
define('RRD_ALIGN_LEFT',          'left');
define('RRD_ALIGN_RIGHT',         'right');
define('RRD_ALIGN_JUSTIFIED',     'justified');
define('RRD_ALIGN_CENTER',        'center');

define('RRD_LEGEND_DIR_TOPDOWN',  'topdown');
define('RRD_LEGEND_DIR_BOTTOMUP', 'bottomup');

define('RRD_FILE_VERSION1',       '0001');
define('RRD_FILE_VERSION3',       '0003');

define('DATA_QUERY_AUTOINDEX_NONE', 0);
define('DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME', 1);
define('DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE', 2);
define('DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION', 3);

define('DATA_INPUT_TYPE_SCRIPT', 1);
define('DATA_INPUT_TYPE_SNMP', 2);
define('DATA_INPUT_TYPE_SNMP_QUERY', 3);
define('DATA_INPUT_TYPE_SCRIPT_QUERY', 4);
define('DATA_INPUT_TYPE_PHP_SCRIPT_SERVER', 5);
define('DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER', 6);

define('GRAPH_ITEM_TYPE_COMMENT',            1);
define('GRAPH_ITEM_TYPE_HRULE',              2);
define('GRAPH_ITEM_TYPE_VRULE',              3);
define('GRAPH_ITEM_TYPE_LINE1',              4);
define('GRAPH_ITEM_TYPE_LINE2',              5);
define('GRAPH_ITEM_TYPE_LINE3',              6);
define('GRAPH_ITEM_TYPE_AREA',               7);
define('GRAPH_ITEM_TYPE_STACK',              8);
define('GRAPH_ITEM_TYPE_GPRINT',             9);
define('GRAPH_ITEM_TYPE_LEGEND',            10);
define('GRAPH_ITEM_TYPE_GPRINT_LAST',       11);
define('GRAPH_ITEM_TYPE_GPRINT_MAX',        12);
define('GRAPH_ITEM_TYPE_GPRINT_MIN',        13);
define('GRAPH_ITEM_TYPE_GPRINT_AVERAGE',    14);
define('GRAPH_ITEM_TYPE_LEGEND_CAMM',       15);
define('GRAPH_ITEM_TYPE_LINESTACK',         20);
define('GRAPH_ITEM_TYPE_TIC',               30);
define('GRAPH_ITEM_TYPE_TEXTALIGN',         40);

/* used both for polling and reindexing */
define('POLLER_ACTION_SNMP', 0);
define('POLLER_ACTION_SCRIPT', 1);
define('POLLER_ACTION_SCRIPT_PHP', 2);

/* used for reindexing only:
 * in case we do not have OID_NUM_INDEXES|ARG_NUM_INDEXES
 * we simply use the OID_INDEX|ARG_INDEX and count number of indexes found
 * so this is more of a REINDEX_ACTION_... thingy
 */
define('POLLER_ACTION_SNMP_COUNT', 10);
define('POLLER_ACTION_SCRIPT_COUNT', 11);
define('POLLER_ACTION_SCRIPT_PHP_COUNT', 12);

define('POLLER_COMMAND_REINDEX', 1);
define('POLLER_COMMAND_RRDPURGE', 2);
define('POLLER_COMMAND_PURGE', 3);

define('POLLER_VERBOSITY_NONE', 1);
define('POLLER_VERBOSITY_LOW', 2);
define('POLLER_VERBOSITY_MEDIUM', 3);
define('POLLER_VERBOSITY_HIGH', 4);
define('POLLER_VERBOSITY_DEBUG', 5);
define('POLLER_VERBOSITY_DEVDBG', 6);

define('POLLER_STATUS_NEW', 0);
define('POLLER_STATUS_RUNNING', 1);
define('POLLER_STATUS_IDLE', 2);
define('POLLER_STATUS_DOWN', 3);
define('POLLER_STATUS_DISABLED', 4);
define('POLLER_STATUS_RECOVERING', 5);
define('POLLER_STATUS_HEARTBEAT', 6);

define('AVAIL_NONE', 0);
define('AVAIL_SNMP_AND_PING', 1);
define('AVAIL_SNMP', 2);
define('AVAIL_PING', 3);
define('AVAIL_SNMP_OR_PING', 4);
define('AVAIL_SNMP_GET_SYSDESC', 5);
define('AVAIL_SNMP_GET_NEXT', 6);

define('PING_ICMP', 1);
define('PING_UDP', 2);
define('PING_TCP', 3);
define('PING_SNMP', 4);

define('HOST_UNKNOWN', 0);
define('HOST_DOWN', 1);
define('HOST_RECOVERING', 2);
define('HOST_UP', 3);
define('HOST_ERROR', 4);

define('GT_CUSTOM', 0);
define('GT_LAST_HALF_HOUR', 1);
define('GT_LAST_HOUR', 2);
define('GT_LAST_2_HOURS', 3);
define('GT_LAST_4_HOURS', 4);
define('GT_LAST_6_HOURS', 5);
define('GT_LAST_12_HOURS', 6);
define('GT_LAST_DAY', 7);
define('GT_LAST_2_DAYS', 8);
define('GT_LAST_3_DAYS', 9);
define('GT_LAST_4_DAYS', 10);
define('GT_LAST_WEEK', 11);
define('GT_LAST_2_WEEKS', 12);
define('GT_LAST_MONTH', 13);
define('GT_LAST_2_MONTHS', 14);
define('GT_LAST_3_MONTHS', 15);
define('GT_LAST_4_MONTHS', 16);
define('GT_LAST_6_MONTHS', 17);
define('GT_LAST_YEAR', 18);
define('GT_LAST_2_YEARS', 19);
define('GT_DAY_SHIFT', 20);
define('GT_THIS_DAY', 21);
define('GT_THIS_WEEK', 22);
define('GT_THIS_MONTH', 23);
define('GT_THIS_YEAR', 24);
define('GT_PREV_DAY', 25);
define('GT_PREV_WEEK', 26);
define('GT_PREV_MONTH', 27);
define('GT_PREV_YEAR', 28);

define('DEFAULT_TIMESPAN', 86400);

# graph timeshifts
define('GTS_CUSTOM', 0);
define('GTS_HALF_HOUR', 1);
define('GTS_1_HOUR', 2);
define('GTS_2_HOURS', 3);
define('GTS_4_HOURS', 4);
define('GTS_6_HOURS', 5);
define('GTS_12_HOURS', 6);
define('GTS_1_DAY', 7);
define('GTS_2_DAYS', 8);
define('GTS_3_DAYS', 9);
define('GTS_4_DAYS', 10);
define('GTS_1_WEEK', 11);
define('GTS_2_WEEKS', 12);
define('GTS_1_MONTH', 13);
define('GTS_2_MONTHS', 14);
define('GTS_3_MONTHS', 15);
define('GTS_4_MONTHS', 16);
define('GTS_6_MONTHS', 17);
define('GTS_1_YEAR', 18);
define('GTS_2_YEARS', 19);

define('DEFAULT_TIMESHIFT', 86400);

# weekdays according to date('w') builtin function
define('WD_SUNDAY', 	date('w',strtotime('sunday')));
define('WD_MONDAY', 	date('w',strtotime('monday')));
define('WD_TUESDAY', 	date('w',strtotime('tuesday')));
define('WD_WEDNESDAY', 	date('w',strtotime('wednesday')));
define('WD_THURSDAY', 	date('w',strtotime('thursday')));
define('WD_FRIDAY', 	date('w',strtotime('friday')));
define('WD_SATURDAY', 	date('w',strtotime('saturday')));

define('GD_MO_D_Y', 0);
define('GD_MN_D_Y', 1);
define('GD_D_MO_Y', 2);
define('GD_D_MN_Y', 3);
define('GD_Y_MO_D', 4);
define('GD_Y_MN_D', 5);

define('GDC_HYPHEN', 0);
define('GDC_SLASH', 1);
define('GDC_DOT', 2);

define('CVDEF_ITEM_TYPE_FUNCTION', 1);
define('CVDEF_ITEM_TYPE_OPERATOR', 2);
define('CVDEF_ITEM_TYPE_SPEC_DS', 4);
define('CVDEF_ITEM_TYPE_CDEF', 5);
define('CVDEF_ITEM_TYPE_STRING', 6);

define('SNMP_POLLER', 0);
define('SNMP_CMDPHP', 1);
define('SNMP_WEBUI', 2);

define('OPER_MODE_NATIVE', 0);
define('OPER_MODE_RESKIN', 1);
define('OPER_MODE_IFRAME_NONAV', 2);
define('OPER_MODE_NOTABS', 3);

define('BOOST_TIMER_START', 1);
define('BOOST_TIMER_END', 0);
define('BOOST_TIMER_STOP', BOOST_TIMER_END);
define('BOOST_TIMER_TOTAL', 2);
define('BOOST_TIMER_CYCLES', 3);
define('BOOST_TIMER_OVERHEAD_MULTIPLIER', 20000);

define('SNMPAGENT_EVENT_SEVERITY_LOW', 1);
define('SNMPAGENT_EVENT_SEVERITY_MEDIUM', 2);
define('SNMPAGENT_EVENT_SEVERITY_HIGH', 3);
define('SNMPAGENT_EVENT_SEVERITY_CRITICAL', 4);

define('CLOG_PERM_ADMIN', 0);
define('CLOG_PERM_USER',  1);
define('CLOG_PERM_NONE',  2);

define('CHARS_PER_TIER', 3);
define('MAX_TREE_DEPTH', 30);
define('SORT_TYPE_TREE', 1);
define('SORT_TYPE_TREE_ITEM', 2);

define('REPORTS_SEND_NOW', 1);
define('REPORTS_DUPLICATE', 2);
define('REPORTS_ENABLE', 3);
define('REPORTS_DISABLE', 4);
define('REPORTS_DELETE', 99);
define('REPORTS_OWN', 100);

define('REPORTS_TYPE_INLINE_PNG', 1);
define('REPORTS_TYPE_INLINE_JPG', 2);
define('REPORTS_TYPE_INLINE_GIF', 3);
define('REPORTS_TYPE_ATTACH_PNG', 11);
define('REPORTS_TYPE_ATTACH_JPG', 12);
define('REPORTS_TYPE_ATTACH_GIF', 13);
define('REPORTS_TYPE_INLINE_PNG_LN', 91);
define('REPORTS_TYPE_INLINE_JPG_LN', 92);
define('REPORTS_TYPE_INLINE_GIF_LN', 93);

define('REPORTS_ITEM_GRAPH', 1);
define('REPORTS_ITEM_TEXT',  2);
define('REPORTS_ITEM_TREE',  3);
define('REPORTS_ITEM_HR',    4);
define('REPORTS_ITEM_HOST',  5);

define('REPORTS_ALIGN_LEFT', 1);
define('REPORTS_ALIGN_CENTER', 2);
define('REPORTS_ALIGN_RIGHT', 3);
define('REPORTS_SCHED_INTVL_MINUTE', 10);
define('REPORTS_SCHED_INTVL_HOUR', 11);

define('REPORTS_SCHED_INTVL_DAY', 1);
define('REPORTS_SCHED_INTVL_WEEK', 2);
define('REPORTS_SCHED_INTVL_MONTH_DAY', 3);
define('REPORTS_SCHED_INTVL_MONTH_WEEKDAY', 4);
define('REPORTS_SCHED_INTVL_YEAR', 5);

define('REPORTS_SCHED_COUNT', 1);
define('REPORTS_SCHED_OFFSET', 0);

define('REPORTS_GRAPH_LINK', 0);

define('REPORTS_FONT_SIZE', 10);
define('REPORTS_HOST_NONE', 0);
define('REPORTS_TREE_NONE', 0);
define('REPORTS_TIMESPAN_DEFAULT', GT_LAST_DAY);

define('REPORTS_EXTENSION_GD', 'gd');

define('REPORTS_OUTPUT_STDOUT', 1);
define('REPORTS_OUTPUT_EMAIL',  2);

define('REPORTS_DEFAULT_MAX_SIZE', 10485760);

# unless a hook for 'global_constants' is available, all DEFINEs go here
define('AGGREGATE_GRAPH_TYPE_KEEP',          0);
define('AGGREGATE_GRAPH_TYPE_KEEP_STACKED', 50);
define('AGGREGATE_GRAPH_TYPE_LINE1_STACK',  51);
define('AGGREGATE_GRAPH_TYPE_LINE2_STACK',  52);
define('AGGREGATE_GRAPH_TYPE_LINE3_STACK',  53);

define('AGGREGATE_TOTAL_NONE', 1);
define('AGGREGATE_TOTAL_ALL', 2);
define('AGGREGATE_TOTAL_ONLY', 3);

define('AGGREGATE_TOTAL_TYPE_SIMILAR', 1);
define('AGGREGATE_TOTAL_TYPE_ALL', 2);

define('AGGREGATE_ORDER_NONE', 1);
define('AGGREGATE_ORDER_DS_GRAPH', 2);
define('AGGREGATE_ORDER_GRAPH_DS', 3);
define('AGGREGATE_ORDER_BASE_GRAPH', 4);

define('AUTOMATION_OP_NONE', 0);
define('AUTOMATION_OP_CONTAINS', 1);
define('AUTOMATION_OP_CONTAINS_NOT', 2);
define('AUTOMATION_OP_BEGINS', 3);
define('AUTOMATION_OP_BEGINS_NOT', 4);
define('AUTOMATION_OP_ENDS', 5);
define('AUTOMATION_OP_ENDS_NOT', 6);
define('AUTOMATION_OP_MATCHES', 7);
define('AUTOMATION_OP_MATCHES_NOT', 8);
define('AUTOMATION_OP_LT', 9);
define('AUTOMATION_OP_LE', 10);
define('AUTOMATION_OP_GT', 11);
define('AUTOMATION_OP_GE', 12);
define('AUTOMATION_OP_UNKNOWN', 13);
define('AUTOMATION_OP_NOT_UNKNOWN', 14);
define('AUTOMATION_OP_EMPTY', 15);
define('AUTOMATION_OP_NOT_EMPTY', 16);
define('AUTOMATION_OP_REGEXP', 17);
define('AUTOMATION_OP_NOT_REGEXP', 18);

define('AUTOMATION_OPER_NULL', 0);
define('AUTOMATION_OPER_AND', 1);
define('AUTOMATION_OPER_OR', 2);
define('AUTOMATION_OPER_LEFT_BRACKET', 3);
define('AUTOMATION_OPER_RIGHT_BRACKET', 4);

define('AUTOMATION_TREE_ITEM_TYPE_STRING', '0');

define('AUTOMATION_RULE_TYPE_GRAPH_MATCH', 1);
define('AUTOMATION_RULE_TYPE_GRAPH_ACTION', 2);
define('AUTOMATION_RULE_TYPE_TREE_MATCH', 3);
define('AUTOMATION_RULE_TYPE_TREE_ACTION', 4);

# pseudo table name required as long as Data Query XML resides in files
define('AUTOMATION_RULE_TABLE_XML', 'XML');

define('AUTOMATION_ACTION_GRAPH_DUPLICATE', 1);
define('AUTOMATION_ACTION_GRAPH_ENABLE', 2);
define('AUTOMATION_ACTION_GRAPH_DISABLE', 3);
define('AUTOMATION_ACTION_GRAPH_DELETE', 99);
define('AUTOMATION_ACTION_TREE_DUPLICATE', 1);
define('AUTOMATION_ACTION_TREE_ENABLE', 2);
define('AUTOMATION_ACTION_TREE_DISABLE', 3);
define('AUTOMATION_ACTION_TREE_DELETE', 99);

if (isset($database_type) && $database_type == 'mysql') {
	define('SQL_NO_CACHE', 'SQL_NO_CACHE');
} else {
	define('SQL_NO_CACHE', '');
}

define('MAX_DISPLAY_PAGES', 5);
define('CHECKED', 'on');

define('FILTER_VALIDATE_MAX_DATE_AS_INT', 2088385563);
define('FILTER_VALIDATE_IS_REGEX',             99999);
define('FILTER_VALIDATE_IS_NUMERIC_ARRAY',    100000);
define('FILTER_VALIDATE_IS_NUMERIC_LIST',     100001);

/* socket errors */
define('ENOTSOCK',        88);
define('EDESTADDRREQ',    89);
define('EMSGSIZE',        90);
define('EPROTOTYPE',      91);
define('ENOPROTOOPT',     92);
define('EPROTONOSUPPORT', 93);
define('ESOCKTNOSUPPORT', 94);
define('EOPNOTSUPP',      95);
define('EPFNOSUPPORT',    96);
define('EAFNOSUPPORT',    97);
define('EADDRINUSE',      98);
define('EADDRNOTAVAIL',   99);
define('ENETDOWN',        100);
define('ENETUNREACH',     101);
define('ENETRESET',       102);
define('ECONNABORTED',    103);
define('ECONNRESET',      104);
define('ENOBUFS',         105);
define('EISCONN',         106);
define('ENOTCONN',        107);
define('ESHUTDOWN',       108);
define('ETOOMANYREFS',    109);
define('ETIMEDOUT',       110);
define('ECONNREFUSED',    111);
define('EHOSTDOWN',       112);
define('EHOSTUNREACH',    113);
define('EALREADY',        114);
define('EINPROGRESS',     115);
define('EREMOTEIO',       121);
define('ECANCELED',       125);

define('DB_STATUS_ERROR'  , 0);
define('DB_STATUS_WARNING', 1);
define('DB_STATUS_RESTART', 2);
define('DB_STATUS_SUCCESS', 3);
define('DB_STATUS_SKIPPED', 4);

define('MESSAGE_LEVEL_NONE',  0);
define('MESSAGE_LEVEL_INFO',  1);
define('MESSAGE_LEVEL_WARN',  2);
define('MESSAGE_LEVEL_ERROR', 3);
define('MESSAGE_LEVEL_CSRF',  4);
define('MESSAGE_LEVEL_MIXED', 5);

if (!defined('PASSWORD_DEFAULT')) {
	define('PASSWORD_DEFAULT', 1);
}

define('CACTI_MAIL_PHP', 0);
define('CACTI_MAIL_SENDMAIL', 1);
define('CACTI_MAIL_SMTP', 2);

define('DAYS_FORMAT_SHORT', 0);
define('DAYS_FORMAT_MEDIUM', 1);
define('DAYS_FORMAT_MEDIUM_LOG', 2);
define('DAYS_FORMAT_LONG', 3);
define('DAYS_FORMAT_LONG_LOG', 4);

define('GRAPH_SOURCE_PLAIN', 0);
define('GRAPH_SOURCE_DATA_QUERY', 1);
define('GRAPH_SOURCE_TEMPLATE', 2);
define('GRAPH_SOURCE_AGGREGATE', 3);

define('CACTI_LANGUAGE_HANDLER_DEFAULT', 0);
define('CACTI_LANGUAGE_HANDLER_PHPGETTEXT', 1);
define('CACTI_LANGUAGE_HANDLER_OSCAROTERO', 2);
define('CACTI_LANGUAGE_HANDLER_MOTRANSLATOR', 3);

if (!defined('LDAP_OPT_X_TLS_NEVER')) {
	define('LDAP_OPT_X_TLS_NEVER', 0);
	define('LDAP_OPT_X_TLS_HARD', 1);
	define('LDAP_OPT_X_TLS_DEMAND', 2);
	define('LDAP_OPT_X_TLS_ALLOW', 3);
	define('LDAP_OPT_X_TLS_TRY', 4);
}

