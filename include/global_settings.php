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

/* tab information */
$tabs = array(
	"general" => "General",
	"path" => "Paths",
	"poller" => "Poller",
	"export" => "Graph Export",
	"visual" => "Visual",
	"authentication" => "Authentication");

$tabs_graphs = array(
	"general" => "General",
	"thumbnail" => "Graph Thumbnails",
	"tree" => "Tree View Mode",
	"preview" => "Preview Mode",
	"list" => "List View Mode",
	"fonts" => "Graph Fonts (RRDtool 1.2.x and Above)");

/* setting information */
$settings = array(
	"path" => array(
		"dependent_header" => array(
			"friendly_name" => "Required Tool Paths",
			"method" => "spacer",
			),
		"path_snmpwalk" => array(
			"friendly_name" => "snmpwalk Binary Path",
			"description" => "The path to your snmpwalk binary.",
			"method" => "filepath",
			"max_length" => "255"
			),
		"path_snmpget" => array(
			"friendly_name" => "snmpget Binary Path",
			"description" => "The path to your snmpget binary.",
			"method" => "filepath",
			"max_length" => "255"
			),
		"path_snmpbulkwalk" => array(
			"friendly_name" => "snmpbulkwalk Binary Path",
			"description" => "The path to your snmpbulkwalk binary.",
			"method" => "filepath",
			"max_length" => "255"
			),
		"path_snmpgetnext" => array(
			"friendly_name" => "snmpgetnext Binary Path",
			"description" => "The path to your snmpgetnext binary.",
			"method" => "filepath",
			"max_length" => "255"
			),
		"path_rrdtool" => array(
			"friendly_name" => "RRDTool Binary Path",
			"description" => "The path to the rrdtool binary.",
			"method" => "filepath",
			"max_length" => "255"
			),
		"path_rrdtool_default_font" => array(
			"friendly_name" => "RRDTool Default Font",
			"description" => "For RRDtool 1.2, the path to the True Type Font File." . "<br/>" .
							"For RRDtool 1.3 and above, the font name conforming to the pango naming convention:" . "<br/>" .
							'You can to use the full Pango syntax when selecting your font: The font name has the form "[FAMILY-LIST] [STYLE-OPTIONS] [SIZE]", where FAMILY-LIST is a comma separated list of families optionally terminated by a comma, STYLE_OPTIONS is a whitespace separated list of words where each WORD describes one of style, variant, weight, stretch, or gravity, and SIZE is a decimal number (size in points) or optionally followed by the unit modifier "px" for absolute size. Any one of the options may be absent.',
			"method" => "font",
			"max_length" => "255"
			),
		"path_php_binary" => array(
			"friendly_name" => "PHP Binary Path",
			"description" => "The path to your PHP binary file (may require a php recompile to get this file).",
			"method" => "filepath",
			"max_length" => "255"
			),
		"logging_header" => array(
			"friendly_name" => "Logging",
			"method" => "spacer",
			),
		"path_cactilog" => array(
			"friendly_name" => "Cacti Log File Path",
			"description" => "The path to your Cacti log file (if blank, defaults to <path_cacti>/log/cacti.log)",
			"method" => "filepath",
			"default" => $config["base_path"] . "/log/cacti.log",
			"max_length" => "255"
			),
		"pollerpaths_header" => array(
			"friendly_name" => "Alternate Poller Path",
			"method" => "spacer",
			),
		"path_spine" => array(
			"friendly_name" => "Spine Poller File Path",
			"description" => "The path to Spine binary.",
			"method" => "filepath",
			"max_length" => "255"
			),
		"extendedpaths_header" => array(
			"friendly_name" => "Structured RRD Path",
			"method" => "spacer",
			),
		"extended_paths" => array(
			"friendly_name" => "Structured RRA Path (/host_id/local_data_id.rrd)",
			"description" => "Use a seperate subfolder for each hosts RRD files.",
			"method" => "checkbox"
 			)
		),
	"general" => array(
		"logging_header" => array(
			"friendly_name" => "Event Logging",
			"method" => "spacer",
			),
		"log_destination" => array(
			"friendly_name" => "Log File Destination",
			"description" => "How will Cacti handle event logging.",
			"method" => "drop_array",
			"default" => 1,
			"array" => $logfile_options,
			),
		"web_log" => array(
			"friendly_name" => "Web Events",
			"description" => "What Cacti website messages should be placed in the log.",
			"method" => "checkbox_group",
			"tab" => "general",
			"items" => array(
				"log_snmp" => array(
					"friendly_name" => "Web SNMP Messages",
					"default" => ""
					),
				"log_graph" => array(
					"friendly_name" => "Web RRD Graph Syntax",
					"default" => ""
					),
				"log_export" => array(
					"friendly_name" => "Graph Export Messages",
					"default" => ""
					)
				),
			),
		"poller_header" => array(
			"friendly_name" => "Poller Specific Logging",
			"method" => "spacer",
			),
		"log_verbosity" => array(
			"friendly_name" => "Poller Logging Level",
			"description" => "What level of detail do you want sent to the log file.  WARNING: Leaving in any other status than NONE or LOW can exaust your disk space rapidly.",
			"method" => "drop_array",
			"default" => POLLER_VERBOSITY_LOW,
			"array" => $logfile_verbosity,
			),
		"poller_log" => array(
			"friendly_name" => "Poller Syslog/Eventlog Selection",
			"description" => "If you are using the Syslog/Eventlog, What Cacti poller messages should be placed in the Syslog/Eventlog.",
			"method" => "checkbox_group",
			"tab" => "poller",
			"items" => array(
				"log_pstats" => array(
					"friendly_name" => "Poller Statistics",
					"default" => ""
					),
				"log_pwarn" => array(
					"friendly_name" => "Poller Warnings",
					"default" => ""
					),
				"log_perror" => array(
					"friendly_name" => "Poller Errors",
					"default" => "on"
					)
				),
			),
		"versions_header" => array(
			"friendly_name" => "Required Tool Versions",
			"method" => "spacer",
			),
		"snmp_version" => array(
			"friendly_name" => "SNMP Utility Version",
			"description" => "The type of SNMP you have installed.  Required if you are using SNMP v2c or don't have embedded SNMP support in PHP.",
			"method" => "drop_array",
			"default" => "net-snmp",
			"array" => $snmp_implimentations,
			),
		"rrdtool_version" => array(
			"friendly_name" => "RRDTool Utility Version",
			"description" => "The version of RRDTool that you have installed.",
			"method" => "drop_array",
			"default" => "rrd-1.2.x",
			"array" => $rrdtool_versions,
			),
		"snmp_header" => array(
			"friendly_name" => "SNMP Defaults",
			"method" => "spacer",
			),
		"snmp_ver" => array(
			"friendly_name" => "SNMP Version",
			"description" => "Default SNMP version for all new hosts.",
			"method" => "drop_array",
			"default" => "1",
			"array" => $snmp_versions,
			),
		"snmp_community" => array(
			"friendly_name" => "SNMP Community",
			"description" => "Default SNMP read community for all new hosts.",
			"method" => "textbox",
			"default" => "public",
			"max_length" => "100",
			),
		"snmp_username" => array(
			"friendly_name" => "SNMP Username (v3)",
			"description" => "The SNMP v3 Username for polling hosts.",
			"method" => "textbox",
			"default" => "",
			"max_length" => "100",
			),
		"snmp_password" => array(
			"friendly_name" => "SNMP Password (v3)",
			"description" => "The SNMP v3 Password for polling hosts.",
			"method" => "textbox_password",
			"default" => "",
			"max_length" => "100",
			),
		"snmp_auth_protocol" => array(
			"method" => "drop_array",
			"friendly_name" => "SNMP Auth Protocol (v3)",
			"description" => "Choose the SNMPv3 Authorization Protocol.",
			"default" => "MD5",
			"array" => $snmp_auth_protocols,
			),
		"snmp_priv_passphrase" => array(
			"method" => "textbox",
			"friendly_name" => "SNMP Privacy Passphrase (v3)",
			"description" => "Choose the SNMPv3 Privacy Passphrase.",
			"default" => "",
			"max_length" => "200"
			),
		"snmp_priv_protocol" => array(
			"method" => "drop_array",
			"friendly_name" => "SNMP Privacy Protocol (v3)",
			"description" => "Choose the SNMPv3 Privacy Protocol.",
			"default" => "DES",
			"array" => $snmp_priv_protocols,
			),
		"snmp_timeout" => array(
			"friendly_name" => "SNMP Timeout",
			"description" => "Default SNMP timeout in milli-seconds.",
			"method" => "textbox",
			"default" => "500",
			"max_length" => "10",
			"size" => "5"
			),
		"snmp_port" => array(
			"friendly_name" => "SNMP Port Number",
			"description" => "Default UDP port to be used for SNMP Calls.  Typically 161.",
			"method" => "textbox",
			"default" => "161",
			"max_length" => "10",
			"size" => "5"
			),
		"snmp_retries" => array(
			"friendly_name" => "SNMP Retries",
			"description" => "The number times the SNMP poller will attempt to reach the host before failing.",
			"method" => "textbox",
			"default" => "3",
			"max_length" => "10",
			"size" => "5"
			),
		"other_header" => array(
			"friendly_name" => "Other Defaults",
			"method" => "spacer",
			),
		"reindex_method" => array(
			"friendly_name" => "Reindex Method for Data Queries",
			"description" => "The default reindex method to use for all Data Queries.",
			"method" => "drop_array",
			"default" => "1",
			"array" => $reindex_types,
			),
		"deletion_verification" => array(
			"friendly_name" => "Deletion Verification",
			"description" => "Prompt user before item deletion.",
			"default" => "on",
			"method" => "checkbox"
			)
		),
	"export" => array(
		"export_hdr_general" => array(
			"friendly_name" => "General",
			"method" => "spacer",
			),
		"export_type" => array(
			"friendly_name" => "Export Method",
			"description" => "Choose which export method to use.",
			"method" => "drop_array",
			"default" => "disabled",
			"array" => array(
						"disabled" => "Disabled (no exporting)",
						"local" => "Classic (local path)",
						"ftp_php" => "FTP (remote) - use php functions",
						"ftp_ncftpput" => "FTP (remote) - use ncftpput",
						"sftp_php" => "SFTP (remote) - use ssh php functions"
						),
			),
		"export_presentation" => array(
			"friendly_name" => "Presentation Method",
			"description" => "Choose which presentation would you want for the html generated pages. If you choose classical presentation, the graphs will be in a only-one-html page. If you choose tree presentation, the graph tree architecture will be kept in the static html pages",
			"method" => "drop_array",
			"default" => "disabled",
			"array" => array(
						"classical" => "Classical Presentation",
						"tree" => "Tree Presentation",
						),
			),
		"export_tree_options" => array(
			"friendly_name" => "Tree Settings",
			"method" => "spacer",
			),
		"export_tree_isolation" => array(
			"friendly_name" => "Tree Isolation",
			"description" => "This setting determines if the entire tree is treated as a single hierarchy or as separate hierarchies.  If they are treated separately, graphs will be isolated from one another.",
			"method" => "drop_array",
			"default" => "off",
			"array" => array(
						"off" => "Single Tree Representation",
						"on" => "Multiple Tree Representation"
						),
			),
		"export_user_id" => array(
			"friendly_name" => "Effective User Name",
			"description" => "The user name to utilize for establishing export permissions.  This user name will be used to determine which graphs/tree's are exported.  This setting works in conjunction with the current on/off behavior available within the current templates.",
			"method" => "drop_sql",
			"sql" => "SELECT id, username AS name FROM user_auth ORDER BY name",
			"default" => "1"
			),
		"export_tree_expand_hosts" => array(
			"friendly_name" => "Expand Tree Hosts",
			"description" => "This settings determines if the tree hosts will be expanded or not.  If set to expanded, each host will have a sub-folder containing either data templates or data query items.",
			"method" => "drop_array",
			"default" => "off",
			"array" => array(
						"off" => "Off",
						"on" => "On"
						),
			),
		"export_thumb_options" => array(
			"friendly_name" => "Thumbnail Settings",
			"method" => "spacer",
			),
		"export_default_height" => array(
			"friendly_name" => "Thumbnail Height",
			"description" => "The height of thumbnail graphs in pixels.",
			"method" => "textbox",
			"default" => "100",
			"max_length" => "10",
			"size" => "5"
			),
		"export_default_width" => array(
			"friendly_name" => "Thumbnail Width",
			"description" => "The width of thumbnail graphs in pixels.",
			"method" => "textbox",
			"default" => "300",
			"max_length" => "10",
			"size" => "5"
			),
		"export_num_columns" => array(
			"friendly_name" => "Thumbnail Columns",
			"description" => "The number of columns to use when displaying thumbnail graphs.",
			"method" => "textbox",
			"default" => "2",
			"max_length" => "5",
			"size" => "5"
			),
		"export_hdr_paths" => array(
			"friendly_name" => "Paths",
			"method" => "spacer",
			),
		"path_html_export" => array(
			"friendly_name" => "Export Directory (both local and ftp)",
			"description" => "This is the directory, either on the local system or on the remote system, that will contain the exported data.",
			"method" => "dirpath",
			"max_length" => "255"
			),
		"export_temporary_directory" => array(
			"friendly_name" => "Local Scratch Directory (ftp only)",
			"description" => "This is the a directory that cacti will temporarily store output prior to sending to the remote site via ftp.  The contents of this directory will be deleted after the ftp is completed.",
			"method" => "dirpath",
			"max_length" => "255"
			),
		"export_hdr_timing" => array(
			"friendly_name" => "Timing",
			"method" => "spacer",
			),
		"export_timing" => array(
			"friendly_name" => "Export timing",
			"description" => "Choose when to export graphs.",
			"method" => "drop_array",
			"default" => "disabled",
			"array" => array(
						"disabled" => "Disabled",
						"classic" => "Classic (export every x times)",
						"export_hourly" => "Hourly at specified minutes",
						"export_daily" => "Daily at specified time"
						),
			),
		"path_html_export_skip" => array(
			"friendly_name" => "Export Every x Times",
			"description" => "If you don't want Cacti to export static images every 5 minutes, put another number here. For instance, 3 would equal every 15 minutes.",
			"method" => "textbox",
			"max_length" => "10",
			"size" => "5"
			),
		"export_hourly" => array(
			"friendly_name" => "Hourly at specified minutes",
			"description" => "If you want Cacti to export static images on an hourly basis, put the minutes of the hour when to do that. Cacti assumes that you run the data gathering script every 5 minutes, so it will round your value to the one closest to its runtime. For instance, 43 would equal 40 minutes past the hour.",
			"method" => "textbox",
			"max_length" => "10",
			"size" => "5"
			),
		"export_daily" => array(
			"friendly_name" => "Daily at specified time",
			"description" => "If you want Cacti to export static images on an daily basis, put here the time when to do that. Cacti assumes that you run the data gathering script every 5 minutes, so it will round your value to the one closest to its runtime. For instance, 21:23 would equal 20 minutes after 9 PM.",
			"method" => "textbox",
			"max_length" => "10",
			"size" => "5"
			),
		"export_hdr_ftp" => array(
			"friendly_name" => "FTP Options",
			"method" => "spacer",
			),
		"export_ftp_sanitize" => array(
			"friendly_name" => "Sanitize remote directory",
			"description" => "Check this if you want to delete any existing files in the FTP remote directory. This option is in use only when using the PHP built-in ftp functions.",
			"method" => "checkbox",
			"max_length" => "255"
			),
		"export_ftp_host" => array(
			"friendly_name" => "FTP Host",
			"description" => "Denotes the host to upload your graphs by ftp.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"export_ftp_port" => array(
			"friendly_name" => "FTP Port",
			"description" => "Communication port with the ftp server (leave empty for defaults). Default: 21.",
			"method" => "textbox",
			"max_length" => "10",
			"size" => "5"
			),
		"export_ftp_passive" => array(
			"friendly_name" => "Use passive mode",
			"description" => "Check this if you want to connect in passive mode to the FTP server.",
			"method" => "checkbox",
			"max_length" => "255"
			),
		"export_ftp_user" => array(
			"friendly_name" => "FTP User",
			"description" => "Account to logon on the remote server (leave empty for defaults). Default: Anonymous.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"export_ftp_password" => array(
			"friendly_name" => "FTP Password",
			"description" => "Password for the remote ftp account (leave empty for blank).",
			"method" => "textbox_password",
			"max_length" => "255"
			)
		),
	"visual" => array(
		"graphmgmt_header" => array(
			"friendly_name" => "Graph Management",
			"method" => "spacer",
			),
		"num_rows_graph" => array(
			"friendly_name" => "Rows Per Page",
			"description" => "The number of rows to display on a single page for graph management.",
			"method" => "drop_array",
			"default" => "30",
			"array" => $item_rows
			),
		"max_title_graph" => array(
			"friendly_name" => "Maximum Title Length",
			"description" => "The maximum number of characters to display for a graph title.",
			"method" => "textbox",
			"default" => "80",
			"max_length" => "10",
			"size" => "5"
			),
		"dataqueries_header" => array(
			"friendly_name" => "Data Queries",
			"method" => "spacer",
			),
		"max_data_query_field_length" => array(
			"friendly_name" => "Maximum Field Length",
			"description" => "The maximum number of characters to display for a data query field.",
			"method" => "textbox",
			"default" => "15",
			"max_length" => "10",
			"size" => "5"
			),
		"graphs_new_header" => array(
			"friendly_name" => "Graph Creation",
			"method" => "spacer",
			),
		"default_graphs_new_dropdown" => array(
			"friendly_name" => "Default Dropdown Selector",
			"description" => "When creating graphs, how would you like the page to appear by default",
			"method" => "drop_array",
			"default" => "-2",
			"array" => array("-2" => "All Types", "-1" => "By Template/Data Query"),
			),
		"num_rows_data_query" => array(
			"friendly_name" => "Data Query Graph Rows",
			"description" => "The maximum number Data Query rows to place on a page per Data Query.  This applies to the 'New Graphs' page.",
			"method" => "drop_array",
			"default" => "30",
			"array" => $item_rows
			),
		"datasources_header" => array(
			"friendly_name" => "Data Sources",
			"method" => "spacer",
			),
		"num_rows_data_source" => array(
			"friendly_name" => "Rows Per Page",
			"description" => "The number of rows to display on a single page for data sources.",
			"method" => "drop_array",
			"default" => "30",
			"array" => $item_rows
			),
		"max_title_data_source" => array(
			"friendly_name" => "Maximum Title Length",
			"description" => "The maximum number of characters to display for a data source title.",
			"method" => "textbox",
			"default" => "45",
			"max_length" => "10",
			"size" => "5"
			),
		"devices_header" => array(
			"friendly_name" => "Devices",
			"method" => "spacer",
			),
		"num_rows_device" => array(
			"friendly_name" => "Rows Per Page",
			"description" => "The number of rows to display on a single page for devices.",
			"method" => "drop_array",
			"default" => "30",
			"array" => $item_rows
			),
		"logmgmt_header" => array(
			"friendly_name" => "Log Management",
			"method" => "spacer",
			),
		"num_rows_log" => array(
			"friendly_name" => "Default Log File Tail Lines",
			"description" => "Default number of lines of the Cacti log file to tail.",
			"method" => "drop_array",
			"default" => 500,
			"array" => $log_tail_lines,
			),
		"log_refresh_interval" => array(
			"friendly_name" => "Log File Tail Refresh",
			"description" => "How often do you want the Cacti log display to update.",
			"method" => "drop_array",
			"default" => 60,
			"array" => $page_refresh_interval,
			),
		"fonts_header" => array(
			"friendly_name" => "Default RRDtool 1.2 Fonts",
			"method" => "spacer",
			),
		"title_size" => array(
			"friendly_name" => "Title Font Size",
			"description" => "The size of the font used for Graph Titles",
			"method" => "textbox",
			"default" => "10",
			"max_length" => "10",
			"size" => "5"
			),
		"title_font" => array(
			"friendly_name" => "Title Font File",
			"description" => "The font to use for Graph Titles" . "<br/>" .
							"For RRDtool 1.2, the path to the True Type Font File." . "<br/>" .
							"For RRDtool 1.3 and above, the font name conforming to the pango naming convention:" . "<br/>" .
							'You can to use the full Pango syntax when selecting your font: The font name has the form "[FAMILY-LIST] [STYLE-OPTIONS] [SIZE]", where FAMILY-LIST is a comma separated list of families optionally terminated by a comma, STYLE_OPTIONS is a whitespace separated list of words where each WORD describes one of style, variant, weight, stretch, or gravity, and SIZE is a decimal number (size in points) or optionally followed by the unit modifier "px" for absolute size. Any one of the options may be absent.',
			"method" => "font",
			"max_length" => "100"
			),
		"legend_size" => array(
			"friendly_name" => "Legend Font Size",
			"description" => "The size of the font used for Graph Legend items",
			"method" => "textbox",
			"default" => "8",
			"max_length" => "10",
			"size" => "5"
			),
		"legend_font" => array(
			"friendly_name" => "Legend Font File",
			"description" => "The font file to be used for Graph Legend items",
			"method" => "font",
			"max_length" => "100"
			),
		"axis_size" => array(
			"friendly_name" => "Axis Font Size",
			"description" => "The size of the font used for Graph Axis",
			"method" => "textbox",
			"default" => "7",
			"max_length" => "10",
			"size" => "5"
			),
		"axis_font" => array(
			"friendly_name" => "Axis Font File",
			"description" => "The font file to be used for Graph Axis items",
			"method" => "font",
			"max_length" => "100"
			),
		"unit_size" => array(
			"friendly_name" => "Unit Font Size",
			"description" => "The size of the font used for Graph Units",
			"method" => "textbox",
			"default" => "7",
			"max_length" => "10",
			"size" => "5"
			),
		"unit_font" => array(
			"friendly_name" => "Unit Font File",
			"description" => "The font file to be used for Graph Unit items",
			"method" => "font",
			"max_length" => "100"
			)
		),
	"poller" => array(
		"poller_header" => array(
			"friendly_name" => "General",
			"method" => "spacer",
			),
		"poller_enabled" => array(
			"friendly_name" => "Enabled",
			"description" => "If you wish to stop the polling process, uncheck this box.",
			"method" => "checkbox",
			"default" => "on",
			"tab" => "poller"
			),
		"poller_type" => array(
			"friendly_name" => "Poller Type",
			"description" => "The poller type to use.  This setting will take effect at next polling interval.",
			"method" => "drop_array",
			"default" => 1,
			"array" => $poller_options,
			),
		"poller_interval" => array(
			"friendly_name" => "Poller Interval",
			"description" => "The polling interval in use.  This setting will effect how often rrd's are checked and updated.
			<strong><u>NOTE: If you change this value, you must re-populate the poller cache.  Failure to do so, may result in lost data.</u></strong>",
			"method" => "drop_array",
			"default" => 300,
			"array" => $poller_intervals,
			),
		"cron_interval" => array(
			"friendly_name" => "Cron Interval",
			"description" => "The cron interval in use.  You need to set this setting to the interval that your cron or scheduled task is currently running.",
			"method" => "drop_array",
			"default" => 300,
			"array" => $cron_intervals,
			),
		"concurrent_processes" => array(
			"friendly_name" => "Maximum Concurrent Poller Processes",
			"description" => "The number of concurrent processes to execute.  Using a higher number when using cmd.php will improve performance.  Performance improvements in spine are best resolved with the threads parameter",
			"method" => "textbox",
			"default" => "1",
			"max_length" => "10",
			"size" => "5"
			),
		"process_leveling" => array(
			"friendly_name" => "Balance Process Load",
			"description" => "If you choose this option, Cacti will attempt to balance the load of each poller process by equally distributing poller items per process.",
			"method" => "checkbox",
			"default" => "on"
			),
		"spine_header" => array(
			"friendly_name" => "Spine Specific Execution Parameters",
			"method" => "spacer",
			),
		"max_threads" => array(
			"friendly_name" => "Maximum Threads per Process",
			"description" => "The maximum threads allowed per process.  Using a higher number when using Spine will improve performance.",
			"method" => "textbox",
			"default" => "1",
			"max_length" => "10",
			"size" => "5"
			),
		"php_servers" => array(
			"friendly_name" => "Number of PHP Script Servers",
			"description" => "The number of concurrent script server processes to run per Spine process.  Settings between 1 and 10 are accepted.  This parameter will help if you are running several threads and script server scripts.",
			"method" => "textbox",
			"default" => "1",
			"max_length" => "10",
			"size" => "5"
			),
		"script_timeout" => array(
			"friendly_name" => "Script and Script Server Timeout Value",
			"description" => "The maximum time that Cacti will wait on a script to complete.  This timeout value is in seconds",
			"method" => "textbox",
			"default" => "25",
			"max_length" => "10",
			"size" => "5"
			),
		"max_get_size" => array(
			"friendly_name" => "The Maximum SNMP OID's Per SNMP Get Request",
			"description" => "The maximum number of snmp get OID's to issue per snmpbulkwalk request.  Increasing this value speeds poller performance over slow links.  The maximum value is 100 OID's.  Decreasing this value to 0 or 1 will disable snmpbulkwalk",
			"method" => "textbox",
			"default" => "10",
			"max_length" => "10",
			"size" => "5"
			),
		"availability_header" => array(
			"friendly_name" => "Host Availability Settings",
			"method" => "spacer",
			),
		"availability_method" => array(
			"friendly_name" => "Downed Host Detection",
			"description" => "The method Cacti will use to determine if a host is available for polling.  <br><i>NOTE: It is recommended that, at a minimum, SNMP always be selected.</i>",
			"method" => "drop_array",
			"default" => AVAIL_SNMP,
			"array" => $availability_options,
			),
		"ping_method" => array(
			"friendly_name" => "Ping Type",
			"description" => "The type of ping packet to sent.  <br><i>NOTE: ICMP requires that the Cacti Service ID have root privilages in Unix.</i>",
			"method" => "drop_array",
			"default" => PING_UDP,
			"array" => $ping_methods,
			),
		"ping_port" => array(
			"friendly_name" => "Ping Port",
			"description" => "When choosing either TCP or UDP Ping, which port should be checked for availability of the host prior to polling.",
			"method" => "textbox",
			"default" => "23",
			"max_length" => "10",
			"size" => "5"
			),
		"ping_timeout" => array(
			"friendly_name" => "Ping Timeout Value",
			"description" => "The timeout value to use for host ICMP and UDP pinging.  This host SNMP timeout value applies for SNMP pings.",
			"method" => "textbox",
			"default" => "400",
			"max_length" => "10",
			"size" => "5"
			),
		"ping_retries" => array(
			"friendly_name" => "Ping Retry Count",
			"description" => "The number of times Cacti will attempt to ping a host before failing.",
			"method" => "textbox",
			"default" => "1",
			"max_length" => "10",
			"size" => "5"
			),
		"updown_header" => array(
			"friendly_name" => "Host Up/Down Settings",
			"method" => "spacer",
			),
		"ping_failure_count" => array(
			"friendly_name" => "Failure Count",
			"description" => "The number of polling intervals a host must be down before logging an error and reporting host as down.",
			"method" => "textbox",
			"default" => "2",
			"max_length" => "10",
			"size" => "5"
			),
		"ping_recovery_count" => array(
			"friendly_name" => "Recovery Count",
			"description" => "The number of polling intervals a host must remain up before returning host to an up status and issuing a notice.",
			"method" => "textbox",
			"default" => "3",
			"max_length" => "10",
			"size" => "5"
			)
		),
	"authentication" => array(
		"general_header" => array(
			"friendly_name" => "General",
			"method" => "spacer",
			),
		"auth_method" => array(
			"friendly_name" => "Authentication Method",
			"description" => "<blockquote><i>None</i> - No authentication will be used, all users will have full access.<br><br><i>Builtin Authentication</i> - Cacti handles user authentication, which allows you to create users and give them rights to different areas within Cacti.<br><br><i>Web Basic Authentication</i> - Authentication is handled by the web server. Users can be added or created automatically on first login if the Template User is defined, otherwise the defined guest permissions will be used.<br><br><i>LDAP Authentication</i> - Allows for authentication against a LDAP server. Users will be created automatically on first login if the Template User is defined, otherwise the defined guest permissions will be used.  If PHP's LDAP module is not enabled, LDAP Authentication will not appear as a selectable option.</blockquote>",
			"method" => "drop_array",
			"default" => 1,
			"array" => $auth_methods
			),
		"special_users_header" => array(
			"friendly_name" => "Special Users",
			"method" => "spacer",
			),
		"guest_user" => array(
			"friendly_name" => "Guest User",
			"description" => "The name of the guest user for viewing graphs; is \"No User\" by default.",
			"method" => "drop_sql",
			"none_value" => "No User",
			"sql" => "select username as id, username as name from user_auth where realm = 0 order by username",
			"default" => "0"
			),
		"user_template" => array(
			"friendly_name" => "User Template",
			"description" => "The name of the user that cacti will use as a template for new Web Basic and LDAP users; is \"guest\" by default.",
			"method" => "drop_sql",
			"none_value" => "No User",
			"sql" => "select username as id, username as name from user_auth where realm = 0 order by username",
			"default" => "0"
			),
		"ldap_general_header" => array(
			"friendly_name" => "LDAP General Settings",
			"method" => "spacer"
			),
		"ldap_server" => array(
			"friendly_name" => "Server",
			"description" => "The dns hostname or ip address of the server.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"ldap_port" => array(
			"friendly_name" => "Port Standard",
			"description" => "TCP/UDP port for Non SSL communications.",
			"method" => "textbox",
			"max_length" => "5",
			"default" => "389",
			"size" => "5"
			),
		"ldap_port_ssl" => array(
			"friendly_name" => "Port SSL",
			"description" => "TCP/UDP port for SSL communications.",
			"method" => "textbox",
			"max_length" => "5",
			"default" => "636",
			"size" => "5"
			),
		"ldap_version" => array(
			"friendly_name" => "Protocol Version",
			"description" => "Protocol Version that the server supports.",
			"method" => "drop_array",
			"default" => "3",
			"array" => $ldap_versions
			),
		"ldap_encryption" => array(
			"friendly_name" => "Encryption",
			"description" => "Encryption that the server supports. TLS is only supported by Protocol Version 3.",
			"method" => "drop_array",
			"default" => "0",
			"array" => $ldap_encryption
			),
		"ldap_referrals" => array(
			"friendly_name" => "Referrals",
			"description" => "Enable or Disable LDAP referrals.  If disabled, it may increase the speed of searches.",
			"method" => "drop_array",
			"default" => "0",
			"array" => array( "0" => "Disabled", "1" => "Enable")
			),
		"ldap_mode" => array(
			"friendly_name" => "Mode",
		"description" => "Mode which cacti will attempt to authenicate against the LDAP server.<blockquote><i>No Searching</i> - No Distinguished Name (DN) searching occurs, just attempt to bind with the provided Distinguished Name (DN) format.<br><br><i>Anonymous Searching</i> - Attempts to search for username against LDAP directory via anonymous binding to locate the users Distinguished Name (DN).<br><br><i>Specific Searching</i> - Attempts search for username against LDAP directory via Specific Distinguished Name (DN) and Specific Password for binding to locate the users Distinguished Name (DN).",
			"method" => "drop_array",
			"default" => "0",
			"array" => $ldap_modes
			),
		"ldap_dn" => array(
			"friendly_name" => "Distinguished Name (DN)",
			"description" => "Distinguished Name syntax, such as for windows: <i>\"&lt;username&gt;@win2kdomain.local\"</i> or for OpenLDAP: <i>\"uid=&lt;username&gt;,ou=people,dc=domain,dc=local\"</i>.   \"&lt;username&gt\" is replaced with the username that was supplied at the login prompt.  This is only used when in \"No Searching\" mode.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"ldap_group_require" => array(
			"friendly_name" => "Require Group Membership",
			"description" => "Require user to be member of group to authenicate. Group settings must be set for this to work, enabling without proper group settings will cause authenication failure.",
			"default" => "",
			"method" => "checkbox"
			),
		"ldap_group_header" => array(
			"friendly_name" => "LDAP Group Settings",
			"method" => "spacer"
			),
		"ldap_group_dn" => array(
			"friendly_name" => "Group Distingished Name (DN)",
			"description" => "Distingished Name of the group that user must have membership.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"ldap_group_attrib" => array(
			"friendly_name" => "Group Member Attribute",
			"description" => "Name of the attribute that contains the usernames of the members.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"ldap_group_member_type" => array(
			"friendly_name" => "Group Member Type",
			"description" => "Defines if users use full Distingished Name or just Username in the defined Group Member Attribute.",
			"method" => "drop_array",
			"default" => 1,
			"array" => array( 1 => "Distingished Name", 2 => "Username" )
			),
		"ldap_search_base_header" => array(
			"friendly_name" => "LDAP Specific Search Settings",
			"method" => "spacer"
			),
		"ldap_search_base" => array(
			"friendly_name" => "Search Base",
			"description" => "Search base for searching the LDAP directory, such as <i>\"dc=win2kdomain,dc=local\"</i> or <i>\"ou=people,dc=domain,dc=local\"</i>.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"ldap_search_filter" => array(
			"friendly_name" => "Search Filter",
			"description" => "Search filter to use to locate the user in the LDAP directory, such as for windows: <i>\"(&amp;(objectclass=user)(objectcategory=user)(userPrincipalName=&lt;username&gt;*))\"</i> or for OpenLDAP: <i>\"(&(objectClass=account)(uid=&lt;username&gt))\"</i>.  \"&lt;username&gt\" is replaced with the username that was supplied at the login prompt. ",
			"method" => "textbox",
			"max_length" => "255"
			),
		"ldap_specific_dn" => array(
			"friendly_name" => "Search Distingished Name (DN)",
			"description" => "Distinguished Name for Specific Searching binding to the LDAP directory.",
			"method" => "textbox",
			"max_length" => "255"
			),
		"ldap_specific_password" => array(
			"friendly_name" => "Search Password",
			"description" => "Password for Specific Searching binding to the LDAP directory.",
			"method" => "textbox_password",
			"max_length" => "255"
			)
		)
	);

$settings_graphs = array(
	"general" => array(
		"default_rra_id" => array(
			"friendly_name" => "Default RRA",
			"description" => "The default RRA to use when thumbnail graphs are not being displayed or when 'Thumbnail Timespan' is set to '0'.",
			"method" => "drop_sql",
			"sql" => "select id,name from rra order by timespan",
			"default" => "1"
			),
		"default_view_mode" => array(
			"friendly_name" => "Default View Mode",
			"description" => "Which mode you want displayed when you visit 'graph_view.php'",
			"method" => "drop_array",
			"array" => $graph_views,
			"default" => "1"
			),
		"default_timespan" => array(
			"friendly_name" => "Default Graph View Timespan",
			"description" => "The default timespan you wish to be displayed when you display graphs",
			"method" => "drop_array",
			"array" => $graph_timespans,
			"default" => GT_LAST_DAY
			),
		"timespan_sel" => array(
			"friendly_name" => "Display Graph View Timespan Selector",
			"description" => "Choose if you want the time span selection box to be displayed.",
			"method" => "checkbox",
			"default" => "on"
		),
		"default_timeshift" => array(
			"friendly_name" => "Default Graph View Timeshift",
			"description" => "The default timeshift you wish to be displayed when you display graphs",
			"method" => "drop_array",
			"array" => $graph_timeshifts,
			"default" => GTS_1_DAY
			),
		"allow_graph_dates_in_future" => array(
			"friendly_name" => "Allow Graph to extend to Future",
			"description" => "When displaying Graphs, allow Graph Dates to extend 'to future'",
			"method" => "checkbox",
			"default" => "on"
		),
		"first_weekdayid" => array(
			"friendly_name" => "First Day of the Week",
			"description" => "The first Day of the Week for weekly Graph Displays",
			"method" => "drop_array",
			"array" => $graph_weekdays,
			"default" => WD_MONDAY
			),
		"day_shift_start" => array(
			"friendly_name" => "Start of Daily Shift",
			"description" => "Start Time of the Daily Shift.",
			"method" => "textbox",
			"default" => "07:00",
			"max_length" => "5"
			),
		"day_shift_end" => array(
			"friendly_name" => "End of Daily Shift",
			"description" => "End Time of the Daily Shift.",
			"method" => "textbox",
			"default" => "18:00",
			"max_length" => "5"
			),
		"default_date_format" => array(
			"friendly_name" => "Graph Date Display Format",
			"description" => "The date format to use for graphs",
			"method" => "drop_array",
			"array" => $graph_dateformats,
			"default" => GD_Y_MO_D
			),
		"default_datechar" => array(
			"friendly_name" => "Graph Date Separator",
			"description" => "The date separator to be used for graphs",
			"method" => "drop_array",
			"array" => $graph_datechar,
			"default" => GDC_SLASH
			),
		"page_refresh" => array(
			"friendly_name" => "Page Refresh",
			"description" => "The number of seconds between automatic page refreshes.",
			"method" => "textbox",
			"default" => "300",
			"max_length" => "10"
			)
		),
	"thumbnail" => array(
		"default_height" => array(
			"friendly_name" => "Thumbnail Height",
			"description" => "The height of thumbnail graphs in pixels.",
			"method" => "textbox",
			"default" => "100",
			"max_length" => "10"
			),
		"default_width" => array(
			"friendly_name" => "Thumbnail Width",
			"description" => "The width of thumbnail graphs in pixels.",
			"method" => "textbox",
			"default" => "300",
			"max_length" => "10"
			),
		"num_columns" => array(
			"friendly_name" => "Thumbnail Columns",
			"description" => "The number of columns to use when displaying thumbnail graphs.",
			"method" => "textbox",
			"default" => "2",
			"max_length" => "5"
			),
		"thumbnail_sections" => array(
			"friendly_name" => "Thumbnail Sections",
			"description" => "Which sections of Cacti thumbnail graphs should be used for.",
			"method" => "checkbox_group",
			"items" => array(
				"thumbnail_section_preview" => array(
					"friendly_name" => "Preview Mode",
					"default" => "on"
					),
				"thumbnail_section_tree_1" => array(
					"friendly_name" => "Tree View (Single Pane)",
					"default" => "on"
					),
				"thumbnail_section_tree_2" => array(
					"friendly_name" => "Tree View (Dual Pane)",
					"default" => ""
					)
				)
			)
		),
	"tree" => array(
		"default_tree_id" => array(
			"friendly_name" => "Default Graph Tree",
			"description" => "The default graph tree to use when displaying graphs in tree mode.",
			"method" => "drop_sql",
			"sql" => "select id,name from graph_tree order by name",
			"default" => "0"
			),
		"default_tree_view_mode" => array(
			"friendly_name" => "Default Tree View Mode",
			"description" => "The default mode that will be used when viewing tree mode.",
			"method" => "drop_array",
			"array" => $graph_tree_views,
			"default" => "2"
			),
		"treeview_graphs_per_page" => array(
			"friendly_name" => "Graphs Per-Page",
			"description" => "The number of graphs to display on one page in preview mode.",
			"method" => "drop_array",
			"default" => "10",
			"array" => $graphs_per_page
			),
		"default_dual_pane_width" => array(
			"friendly_name" => "Dual Pane Tree Width",
			"description" => "When choosing dual pane Tree View, what width should the tree occupy in pixels.",
			"method" => "textbox",
			"max_length" => "5",
			"default" => "200"
			),
		"expand_hosts" => array(
			"friendly_name" => "Expand Hosts",
			"description" => "Choose whether to expand the graph templates used for a host on the dual pane tree.",
			"method" => "checkbox",
			"default" => ""
			),
		"show_graph_title" => array(
			"friendly_name" => "Show Graph Title",
			"description" => "Display the graph title on the page so that it may be searched using the browser.",
			"method" => "checkbox",
			"default" => ""
			)
		),
	"preview" => array(
		"preview_graphs_per_page" => array(
			"friendly_name" => "Graphs Per-Page",
			"description" => "The number of graphs to display on one page in preview mode.",
			"method" => "drop_array",
			"default" => "10",
			"array" => $graphs_per_page
			)
		),
	"list" => array(
		"list_graphs_per_page" => array(
			"friendly_name" => "Graphs Per-Page",
			"description" => "The number of graphs to display on one page in list view mode.",
			"method" => "drop_array",
			"default" => "30",
			"array" => $graphs_per_page
			)
		),
	"fonts" => array(
		"custom_fonts" => array(
			"friendly_name" => "Use Custom Fonts",
			"description" => "Choose whether to use your own custom fonts and font sizes or utilize the system defaults.",
			"method" => "checkbox",
			"on_change" => "graphSettings()",
			"default" => ""
			),
		"title_size" => array(
			"friendly_name" => "Title Font Size",
			"description" => "The size of the font used for Graph Titles",
			"method" => "textbox",
			"default" => "12",
			"max_length" => "10"
			),
		"title_font" => array(
			"friendly_name" => "Title Font File",
			"description" => "The font file to use for Graph Titles",
			"method" => "font",
			"max_length" => "100"
			),
		"legend_size" => array(
			"friendly_name" => "Legend Font Size",
			"description" => "The size of the font used for Graph Legend items",
			"method" => "textbox",
			"default" => "10",
			"max_length" => "10"
			),
		"legend_font" => array(
			"friendly_name" => "Legend Font File",
			"description" => "The font file to be used for Graph Legend items",
			"method" => "font",
			"max_length" => "100"
			),
		"axis_size" => array(
			"friendly_name" => "Axis Font Size",
			"description" => "The size of the font used for Graph Axis",
			"method" => "textbox",
			"default" => "8",
			"max_length" => "10"
			),
		"axis_font" => array(
			"friendly_name" => "Axis Font File",
			"description" => "The font file to be used for Graph Axis items",
			"method" => "font",
			"max_length" => "100"
			),
		"unit_size" => array(
			"friendly_name" => "Unit Font Size",
			"description" => "The size of the font used for Graph Units",
			"method" => "textbox",
			"default" => "8",
			"max_length" => "10"
			),
		"unit_font" => array(
			"friendly_name" => "Unit Font File",
			"description" => "The font file to be used for Graph Unit items",
			"method" => "font",
			"max_length" => "100"
			)
		)
	);

api_plugin_hook('config_settings');

?>
