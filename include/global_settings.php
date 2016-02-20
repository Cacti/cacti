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

$dir = dir($config['base_path'] . '/include/themes/');
while (false !== ($entry = $dir->read())) {
	if ($entry != '.' && $entry != '..') {
		if (is_dir($config['base_path'] . '/include/themes/' . $entry)) {
			$themes[$entry] = ucwords($entry);
		}
	}
}
asort($themes);
$dir->close();

/* tab information */
$tabs = array(
	'general' => 'General',
	'path' => 'Paths',
	'poller' => 'Poller',
	'storage' => 'Data Storage',
	'export' => 'Graph Export',
	'visual' => 'Visual',
	'authentication' => 'Authentication',
	'dsstats' => 'Data Source Statistics',
	'boost' => 'Performance',
	'mail' => 'Mail/Reporting/DNS');

$tabs_graphs = array(
	'general' => 'General Settings',
	'timespan' => 'Time Spanning/Shifting',
	'thumbnail' => 'Graph Thumbnail Settings',
	'tree' => 'Tree Settings',
	'fonts' => 'Graph Fonts');

/* setting information */
$settings = array(
	'path' => array(
		'dependent_header' => array(
			'friendly_name' => 'Required Tool Paths',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'path_snmpwalk' => array(
			'friendly_name' => 'snmpwalk Binary Path',
			'description' => 'The path to your snmpwalk binary.',
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_snmpget' => array(
			'friendly_name' => 'snmpget Binary Path',
			'description' => 'The path to your snmpget binary.',
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_snmpbulkwalk' => array(
			'friendly_name' => 'snmpbulkwalk Binary Path',
			'description' => 'The path to your snmpbulkwalk binary.',
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_snmpgetnext' => array(
			'friendly_name' => 'snmpgetnext Binary Path',
			'description' => 'The path to your snmpgetnext binary.',
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_snmptrap' => array(
			'friendly_name' => 'snmptrap Binary Path',
			'description' => 'The path to your snmptrap binary.',
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_rrdtool' => array(
			'friendly_name' => 'RRDTool Binary Path',
			'description' => 'The path to the rrdtool binary.',
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_php_binary' => array(
			'friendly_name' => 'PHP Binary Path',
			'description' => 'The path to your PHP binary file (may require a php recompile to get this file).',
			'method' => 'filepath',
			'max_length' => '255'
			),
		'logging_header' => array(
			'friendly_name' => 'Logging',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'path_cactilog' => array(
			'friendly_name' => 'Cacti Log File Path',
			'description' => 'The path to your Cacti log file (if blank, defaults to <path_cacti>/log/cacti.log)',
			'method' => 'filepath',
			'default' => $config['base_path'] . '/log/cacti.log',
			'max_length' => '255'
			),
		'logrotate_enabled' => array(
			'friendly_name' => 'Rotate the Cacti Log Nightly',
			'description' => 'This will rotate the Cacti Log every night at midnight.',
			'method' => 'checkbox',
			'default' => '',
			),
		'logrotate_retain' => array(
			'friendly_name' => 'Log Retention',
			'description' => 'The number of days to retain old logs.  Use 0 to never remove any logs. (0-365)',
			'method' => 'textbox',
			'default' => '7',
			'max_length' => 3,
			),
		'pollerpaths_header' => array(
			'friendly_name' => 'Alternate Poller Path',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'path_spine' => array(
			'friendly_name' => 'Spine Poller File Path',
			'description' => 'The path to Spine binary.',
			'method' => 'filepath',
			'max_length' => '255'
			),
		'rrdclean_header' => array(
			'friendly_name' => 'RRD Cleaner',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'rrd_autoclean' => array(
			'friendly_name' => 'RRDfile Auto Clean',
			'description' => 'Automatically Delete, Archive, or Delete RRDfiles when removed from Cacti',
			'method' => 'checkbox',
			'default' => ''
 			),
		'rrd_autoclean_method' => array(
			'friendly_name' => 'RRDfile Auto Clean Method',
			'description' => 'The method used to Clean RRDfiles from Cacti after their deletion.',
			'method' => 'drop_array',
			'array' => array('1' => 'Delete', '3' => 'Archive'),
			'default' => '1'
 			),
		'rrd_archive' => array(
			'friendly_name' => 'Archive directory',
			'description' => 'This is the directory where rrd files are <strong>moved</strong> for <strong>Archive</strong>',
			'method' => 'dirpath',
			'default' => $config['base_path'] . '/rra/archive/',
			'max_length' => 255,
			),
		),
	'general' => array(
		'event_logging_header' => array(
			'friendly_name' => 'Event Logging',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'log_destination' => array(
			'friendly_name' => 'Log File Destination',
			'description' => 'How will Cacti handle event logging.',
			'method' => 'drop_array',
			'default' => 1,
			'array' => $logfile_options,
			),
		'web_log' => array(
			'friendly_name' => 'Web Events',
			'description' => 'What Cacti website messages should be placed in the log.',
			'method' => 'checkbox_group',
			'tab' => 'general',
			'items' => array(
				'log_snmp' => array(
					'friendly_name' => 'SNMP Messages',
					'default' => ''
					),
				'log_graph' => array(
					'friendly_name' => 'Graph Syntax',
					'default' => ''
					),
				'log_export' => array(
					'friendly_name' => 'Graph Export',
					'default' => ''
					),
				'developer_mode' => array(
					'friendly_name' => 'Developer Mode',
					'default' => ''
					)
				),
			),
		'poller_specific_header' => array(
			'friendly_name' => 'Poller Specific Logging',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'log_verbosity' => array(
			'friendly_name' => 'Poller Logging Level',
			'description' => 'What level of detail do you want sent to the log file.  WARNING: Leaving in any other status than NONE or LOW can exaust your disk space rapidly.',
			'method' => 'drop_array',
			'default' => POLLER_VERBOSITY_LOW,
			'array' => $logfile_verbosity,
			),
		'poller_log' => array(
			'friendly_name' => 'Poller Syslog/Eventlog Selection',
			'description' => 'If you are using the Syslog/Eventlog, What Cacti poller messages should be placed in the Syslog/Eventlog.',
			'method' => 'checkbox_group',
			'tab' => 'poller',
			'items' => array(
				'log_pstats' => array(
					'friendly_name' => 'Statistics',
					'default' => ''
					),
				'log_pwarn' => array(
					'friendly_name' => 'Warnings',
					'default' => ''
					),
				'log_perror' => array(
					'friendly_name' => 'Errors',
					'default' => 'on'
					)
				),
			),
		'versions_header' => array(
			'friendly_name' => 'Required Tool Versions',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'snmp_version' => array(
			'friendly_name' => 'SNMP Utility Version',
			'description' => 'The type of SNMP you have installed.  Required if you are using SNMP v2c or dont have embedded SNMP support in PHP.',
			'method' => 'drop_array',
			'default' => 'net-snmp',
			'array' => $snmp_implimentations,
			),
		'rrdtool_version' => array(
			'friendly_name' => 'RRDTool Utility Version',
			'description' => 'The version of RRDTool that you have installed.',
			'method' => 'drop_array',
			'default' => 'rrd-1.4.x',
			'array' => $rrdtool_versions,
			),
		'snmp_header' => array(
			'friendly_name' => 'SNMP Defaults',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'snmp_ver' => array(
			'friendly_name' => 'SNMP Version',
			'description' => 'Default SNMP version for all new hosts.',
			'method' => 'drop_array',
			'default' => '1',
			'array' => $snmp_versions,
			),
		'snmp_community' => array(
			'friendly_name' => 'SNMP Community',
			'description' => 'Default SNMP read community for all new hosts.',
			'method' => 'textbox',
			'default' => 'public',
			'max_length' => '100',
			),
		'snmp_username' => array(
			'friendly_name' => 'SNMP Username (v3)',
			'description' => 'The SNMP v3 Username for polling hosts.',
			'method' => 'textbox',
			'default' => '',
			'max_length' => '100',
			),
		'snmp_password' => array(
			'friendly_name' => 'SNMP Password (v3)',
			'description' => 'The SNMP v3 Password for polling hosts.',
			'method' => 'textbox_password',
			'default' => '',
			'max_length' => '100',
			),
		'snmp_auth_protocol' => array(
			'method' => 'drop_array',
			'friendly_name' => 'SNMP Auth Protocol (v3)',
			'description' => 'Choose the SNMPv3 Authorization Protocol.',
			'default' => 'MD5',
			'array' => $snmp_auth_protocols,
			),
		'snmp_priv_passphrase' => array(
			'method' => 'textbox',
			'friendly_name' => 'SNMP Privacy Passphrase (v3)',
			'description' => 'Choose the SNMPv3 Privacy Passphrase.',
			'default' => '',
			'max_length' => '200'
			),
		'snmp_priv_protocol' => array(
			'method' => 'drop_array',
			'friendly_name' => 'SNMP Privacy Protocol (v3)',
			'description' => 'Choose the SNMPv3 Privacy Protocol.',
			'default' => 'DES',
			'array' => $snmp_priv_protocols,
			),
		'snmp_timeout' => array(
			'friendly_name' => 'SNMP Timeout',
			'description' => 'Default SNMP timeout in milli-seconds.',
			'method' => 'textbox',
			'default' => '500',
			'max_length' => '10',
			'size' => '5'
			),
		'snmp_port' => array(
			'friendly_name' => 'SNMP Port Number',
			'description' => 'Default UDP port to be used for SNMP Calls.  Typically 161.',
			'method' => 'textbox',
			'default' => '161',
			'max_length' => '10',
			'size' => '5'
			),
		'snmp_retries' => array(
			'friendly_name' => 'SNMP Retries',
			'description' => 'The number times the SNMP poller will attempt to reach the host before failing.',
			'method' => 'textbox',
			'default' => '3',
			'max_length' => '10',
			'size' => '5'
			),
		'other_header' => array(
			'friendly_name' => 'Other Defaults',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'reindex_method' => array(
			'friendly_name' => 'Default Reindex Method for Data Queries',
			'description' => 'The default Reindex Method to use for all Data Queries.',
			'method' => 'drop_array',
			'default' => '1',
			'array' => $reindex_types,
			),
		'default_image_format' => array(
			'friendly_name' => 'Default Graph Template Image Format',
			'description' => 'The default Image Format to be used for all new Graph Templates.',
			'method' => 'drop_array',
			'default' => '1',
			'array' => $image_types,
			),
		'default_graph_height' => array(
			'friendly_name' => 'Default Graph Template Height',
			'description' => 'The default Graph Width to be used for all new Graph Templates.',
			'method' => 'textbox',
			'default' => '150',
			'size' => '5',
			'max_length' => '5'
			),
		'default_graph_width' => array(
			'friendly_name' => 'Default Graph Template Width',
			'description' => 'The default Graph Width to be used for all new Graph Templates.',
			'method' => 'textbox',
			'default' => '500',
			'size' => '5',
			'max_length' => '5'
			),
		'other1_header' => array(
			'friendly_name' => 'Other Settings',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'graph_auth_method' => array(
			'friendly_name' => 'Graph Permission Method',
			'description' => 'There are two methods for determining a Users Graph Permissions.  The first is "Permissive".  Under the "Permissive" setting, a User only needs access to either the Graph, Device or Graph Template to gain access to the Graphs that apply to them.  Under "Restrictive", the User must have access to the Graph, the Device, and the Graph Template to gain access to the Graph.',
			'method' => 'drop_array',
			'default' => '1',
			'array' => array('1' => 'Permissive', '2' => 'Restrictive')
			),
		'grds_creation_method' => array(
			'method' => 'drop_array',
			'friendly_name' => 'Graph/Data Source Creation Method',
			'description' => 'If set to Simple, Graphs and Data Sources can only be created from New Graphs.  If Advanced, legacy Graph and Data Source creation is supported.',
			'default' => '0',
			'array' => array('0' => 'Simple', '1' => 'Advanced')
			),
		'deletion_verification' => array(
			'friendly_name' => 'Deletion Verification',
			'description' => 'Prompt user before item deletion.',
			'default' => 'on',
			'method' => 'checkbox',
			),
		'force_https' => array(
			'friendly_name' => 'Force Connections over HTTPS',
			'description' => 'When checked, any attempts to access Cacti will be redirected to HTTPS to insure high security.',
			'default' => '',
			'method' => 'checkbox',
			),
		'automation_header' => array(
			'friendly_name' => 'Automation',
			'method' => 'spacer',
			),
		'automation_graphs_enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => 'Enable Automatic Graph Creation',
			'description' => 'When disabled, Cacti Automation will not actively create any Graph.' . 
				'This is useful when adjusting Host settings so as to avoid creating new Graphs each time you save an object. ' . 
				'Invoking Automation Rules manually will still be possible.',
			'default' => '',
			),
		'automation_tree_enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => 'Enable Automatic Tree Item Creation',
			'description' => 'When disabled, Cacti Automation will not actively create any Tree Item.' . 
				'This is useful when adjusting Host or Graph settings so as to avoid creating new Tree Entries each time you save an object. ' . 
				'Invoking Rules manually will still be possible.',
			'default' => '',
			),
		),
	'export' => array(
		'export_hdr_general' => array(
			'friendly_name' => 'General',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'export_type' => array(
			'friendly_name' => 'Export Method',
			'description' => 'Choose which export method to use.',
			'method' => 'drop_array',
			'default' => 'disabled',
			'array' => array(
				'disabled' => 'Disabled (no exporting)',
				'local' => 'Classic (local path)',
				'ftp_php' => 'FTP (remote) - use php functions',
				'ftp_ncftpput' => 'FTP (remote) - use ncftpput',
				'sftp_php' => 'SFTP (remote) - use ssh php functions'
				),
			),
		'export_presentation' => array(
			'friendly_name' => 'Presentation Method',
			'description' => 'Choose which presentation would you want for the html generated pages. If you choose classical presentation, the graphs will be in a only-one-html page. If you choose tree presentation, the graph tree architecture will be kept in the static html pages',
			'method' => 'drop_array',
			'default' => 'disabled',
			'array' => array(
				'classical' => 'Classical Presentation',
				'tree' => 'Tree Presentation',
				),
			),
		'export_tree_options' => array(
			'friendly_name' => 'Tree Settings',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'export_tree_isolation' => array(
			'friendly_name' => 'Tree Isolation',
			'description' => 'This setting determines if the entire tree is treated as a single hierarchy or as separate hierarchies.  If they are treated separately, graphs will be isolated from one another.',
			'method' => 'drop_array',
			'default' => 'off',
			'array' => array(
				'off' => 'Single Tree Representation',
				'on' => 'Multiple Tree Representation'
				),
			),
		'export_user_id' => array(
			'friendly_name' => 'Effective User Name',
			'description' => 'The user name to utilize for establishing export permissions.  This user name will be used to determine which graphs/trees are exported.  This setting works in conjunction with the current on/off behavior available within the current templates.',
			'method' => 'drop_sql',
			'sql' => 'SELECT id, username AS name FROM user_auth ORDER BY name',
			'default' => '1'
			),
		'export_tree_expand_hosts' => array(
			'friendly_name' => 'Expand Tree Devices',
			'description' => 'This settings determines if the tree hosts will be expanded or not.  If set to expanded, each host will have a sub-folder containing either data templates or data query items.',
			'method' => 'drop_array',
			'default' => 'off',
			'array' => array(
				'off' => 'Off',
				'on' => 'On'
				),
			),
		'export_thumb_options' => array(
			'friendly_name' => 'Thumbnail Settings',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'export_default_height' => array(
			'friendly_name' => 'Thumbnail Height',
			'description' => 'The height of thumbnail graphs in pixels.',
			'method' => 'textbox',
			'default' => '100',
			'max_length' => '10',
			'size' => '5'
			),
		'export_default_width' => array(
			'friendly_name' => 'Thumbnail Width',
			'description' => 'The width of thumbnail graphs in pixels.',
			'method' => 'textbox',
			'default' => '300',
			'max_length' => '10',
			'size' => '5'
			),
		'export_num_columns' => array(
			'friendly_name' => 'Thumbnail Columns',
			'description' => 'The number of columns to use when displaying thumbnail graphs.',
			'method' => 'textbox',
			'default' => '2',
			'max_length' => '5',
			'size' => '5'
			),
		'export_hdr_paths' => array(
			'friendly_name' => 'Paths',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'path_html_export' => array(
			'friendly_name' => 'Export Directory (both local and ftp)',
			'description' => 'This is the directory, either on the local system or on the remote system, that will contain the exported data.',
			'method' => 'dirpath',
			'max_length' => '255'
			),
		'export_temporary_directory' => array(
			'friendly_name' => 'Local Scratch Directory (ftp only)',
			'description' => 'This is the a directory that cacti will temporarily store output prior to sending to the remote site via ftp.  The contents of this directory will be deleted after the ftp is completed.',
			'method' => 'dirpath',
			'max_length' => '255'
			),
		'export_hdr_timing' => array(
			'friendly_name' => 'Timing',
			'method' => 'spacer',
			),
		'export_timing' => array(
			'friendly_name' => 'Export timing',
			'description' => 'Choose when to export graphs.',
			'method' => 'drop_array',
			'default' => 'disabled',
			'array' => array(
						'disabled' => 'Disabled',
						'classic' => 'Classic (export every x times)',
						'export_hourly' => 'Hourly at specified minutes',
						'export_daily' => 'Daily at specified time'
						),
			),
		'path_html_export_skip' => array(
			'friendly_name' => 'Export Every x Times',
			'description' => 'If you dont want Cacti to export static images every 5 minutes, put another number here. For instance, 3 would equal every 15 minutes.',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '5'
			),
		'export_hourly' => array(
			'friendly_name' => 'Hourly at specified minutes',
			'description' => 'If you want Cacti to export static images on an hourly basis, put the minutes of the hour when to do that. Cacti assumes that you run the data gathering script every 5 minutes, so it will round your value to the one closest to its runtime. For instance, 43 would equal 40 minutes past the hour.',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '5'
			),
		'export_daily' => array(
			'friendly_name' => 'Daily at specified time',
			'description' => 'If you want Cacti to export static images on an daily basis, put here the time when to do that. Cacti assumes that you run the data gathering script every 5 minutes, so it will round your value to the one closest to its runtime. For instance, 21:23 would equal 20 minutes after 9 PM.',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '5'
			),
		'export_hdr_ftp' => array(
			'friendly_name' => 'FTP Options',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'export_ftp_sanitize' => array(
			'friendly_name' => 'Sanitize remote directory',
			'description' => 'Check this if you want to delete any existing files in the FTP remote directory. This option is in use only when using the PHP built-in ftp functions.',
			'method' => 'checkbox',
			'max_length' => '255'
			),
		'export_ftp_host' => array(
			'friendly_name' => 'FTP Host',
			'description' => 'Denotes the host to upload your graphs by ftp.',
			'placeholder' => 'hostname',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'export_ftp_port' => array(
			'friendly_name' => 'FTP Port',
			'description' => 'Communication port with the ftp server (leave empty for defaults). Default: 21.',
			'placeholder' => '23',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '5'
			),
		'export_ftp_passive' => array(
			'friendly_name' => 'Use passive mode',
			'description' => 'Check this if you want to connect in passive mode to the FTP server.',
			'method' => 'checkbox',
			'max_length' => '255'
			),
		'export_ftp_user' => array(
			'friendly_name' => 'FTP User',
			'description' => 'Account to logon on the remote server (leave empty for defaults). Default: Anonymous.',
			'method' => 'textbox',
			'placeholder' => 'anonymous',
			'max_length' => '255'
			),
		'export_ftp_password' => array(
			'friendly_name' => 'FTP Password',
			'description' => 'Password for the remote ftp account (leave empty for blank).',
			'method' => 'textbox_password',
			'max_length' => '255'
			)
		),
	'visual' => array(
		'themes_header' => array(
			'friendly_name' => 'Theme Settings',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'selected_theme' => array(
			'friendly_name' => 'Theme',
			'description' => 'Please select one of the available Themes to skin your Cacti with.',
			'method' => 'drop_array',
			'default' => 'classic',
			'array' => $themes
			),
		'table_header' => array(
			'friendly_name' => 'Table Settings',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'num_rows_table' => array(
			'friendly_name' => 'Rows Per Page',
			'description' => 'The default number of rows to display on for a table.',
			'method' => 'drop_array',
			'default' => '30',
			'array' => $item_rows
			),
		'object_creation_header' => array(
			'friendly_name' => 'Graph/Data Source/Data Query Settings',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'max_title_length' => array(
			'friendly_name' => 'Maximum Title Length',
			'description' => 'The maximum allowable Graph or Data Source titles.',
			'method' => 'textbox',
			'default' => '80',
			'max_length' => '10',
			'size' => '5'
			),
		'max_data_query_field_length' => array(
			'friendly_name' => 'Data Source Field Length',
			'description' => 'The maximum Data Query field length.',
			'method' => 'textbox',
			'default' => '15',
			'max_length' => '10',
			'size' => '5'
			),
		'graphs_new_header' => array(
			'friendly_name' => 'Graph Creation',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'default_graphs_new_dropdown' => array(
			'friendly_name' => 'Default Graph Type',
			'description' => 'When creating graphs, what Graph Type would you like pre-selected?',
			'method' => 'drop_array',
			'default' => '-2',
			'array' => array('-2' => 'All Types', '-1' => 'By Template/Data Query'),
			),
		'logmgmt_header' => array(
			'friendly_name' => 'Log Management',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'num_rows_log' => array(
			'friendly_name' => 'Default Log File Tail Lines',
			'description' => 'Default number of lines of the Cacti log file to tail.',
			'method' => 'drop_array',
			'default' => 500,
			'array' => $log_tail_lines,
			),
		'log_refresh_interval' => array(
			'friendly_name' => 'Log File Tail Refresh',
			'description' => 'How often do you want the Cacti log display to update.',
			'method' => 'drop_array',
			'default' => 60,
			'array' => $page_refresh_interval,
			),
		'wathermark_header' => array(
			'friendly_name' => 'RRDtool Graph Watermark',
			'collapsible' => 'true',
			'method' => 'spacer'
			),
		'graph_wathermark' => array(
			'friendly_name' => 'Watermark Text',
			'description' => 'Test to place at the bottom center of every Graph.',
			'method' => 'textbox',
			'default' => COPYRIGHT_YEARS,
			'max_length' => '80',
			'size' => '60'
			),
		'clog_header' => array(
			'friendly_name' => 'Log Viewer Settings',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'clog_exclude' => array(
			'friendly_name' => 'Exclusion Regex',
			'description' => 'Any strings that match this regex will be excluded from the user display.
				<strong>For example, if you want to exclude all log lines that include the words "Admin" or "Login"
				you would type "(Admin || Login)"</strong>',
			'method' => 'textarea',
			'textarea_rows' => '5',
			'textarea_cols' => '45',
			'max_length' => 512
			),
		'realtime_header' => array(
			'friendly_name' => 'Realtime Graphs',
			'method' => 'spacer',
			'collapsible' => 'true',
			),
		'realtime_enabled' => array(
			'friendly_name' => 'Enable Realtime Graphing',
			'description' => 'When an option is checked, users will be able to put Cacti into Realtime mode.',
			'method' => 'checkbox',
			'default' => 'on'
			),
		'realtime_gwindow' => array(
			'friendly_name' => 'Graph Timespan',
			'description' => 'This timespan you wish to see on the default graph.',
			'method' => 'drop_array',
			'default' => 60,
			'array' => $realtime_window,
			),
		'realtime_interval' => array(
			'friendly_name' => 'Refresh Interval',
			'description' => 'This is the time between graph updates.',
			'method' => 'drop_array',
			'default' => 10,
			'array' => $realtime_refresh,
			),
		'realtime_cache_path' => array(
			'friendly_name' => 'Cache Directory',
			'description' => 'This is the location, on the web server where the RRDfiles and PNG files will be cached.
			This cache will be managed by the poller.
			Make sure you have the correct read and write permissions on this folder',
			'method' => 'dirpath',
			'default' => $config['base_path'] . '/rra_rt/',
			'max_length' => 255,
			'size' => 40,
			),
		'fonts_header' => array(
			'friendly_name' => 'RRDtool Graph Font Control',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'font_method' => array(
			'friendly_name' => 'Font Selection Method',
			'description' => 'How do you wish fonts to be handled by default?',
			'method' => 'drop_array',
			'default' => 1,
			'array' => array(0 => 'System', 1 => 'Theme')
			),
		'path_rrdtool_default_font' => array(
			'friendly_name' => 'Default Font',
			'description' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' ? 'When not using Theme based fonts, the path to the default True Type Font File.':'When not using Theme based font control, the Pangon font-config font name to use for all Graphs.') . '  Optionally, you may leave blank and control font settings on a per object basis.',
			'method' => 'font',
			'placeholder' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' ? 'Enter valid True Type Font File Path':'Enter Valid Font Config Value'),
			'max_length' => '255'
			),
		'title_size' => array(
			'friendly_name' => 'Title Font Size',
			'description' => 'The size of the font used for Graph Titles',
			'method' => 'textbox',
			'default' => '10',
			'max_length' => '10',
			'size' => '5'
			),
		'title_font' => array(
			'friendly_name' => 'Title Font Setting',
			'description' => 'The font to use for Graph Titles.  Enter either a valid True Type Font file or valid Pango font-config value.',
			'method' => 'font',
			'placeholder' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' ? 'Enter True Type Font Path':'Enter Valid Font Config Value'),
			'max_length' => '100'
			),
		'legend_size' => array(
			'friendly_name' => 'Legend Font Size',
			'description' => 'The size of the font used for Graph Legend items',
			'method' => 'textbox',
			'default' => '8',
			'max_length' => '10',
			'size' => '5'
			),
		'legend_font' => array(
			'friendly_name' => 'Legend Font Setting',
			'description' => 'The font to use for Graph Legends.  Enter either a valid True Type Font file or valid Pango font-config value.',
			'method' => 'font',
			'placeholder' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' ? 'Enter True Type Font Path':'Enter Valid Font Config Value'),
			'max_length' => '100'
			),
		'axis_size' => array(
			'friendly_name' => 'Axis Font Size',
			'description' => 'The size of the font used for Graph Axis',
			'method' => 'textbox',
			'default' => '7',
			'max_length' => '10',
			'size' => '5'
			),
		'axis_font' => array(
			'friendly_name' => 'Axis Font Setting',
			'description' => 'The font to use for Graph Axis items.  Enter either a valid True Type Font file or valid Pango font-config value.',
			'method' => 'font',
			'placeholder' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' ? 'Enter True Type Font Path':'Enter Valid Font Config Value'),
			'max_length' => '100'
			),
		'unit_size' => array(
			'friendly_name' => 'Unit Font Size',
			'description' => 'The size of the font used for Graph Units',
			'method' => 'textbox',
			'default' => '7',
			'max_length' => '10',
			'size' => '5'
			),
		'unit_font' => array(
			'friendly_name' => 'Unit Font Setting',
			'description' => 'The font to use for Graph Unit items.  Enter either a valid True Type Font file or valid Pango font-config value.',
			'method' => 'font',
			'placeholder' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' ? 'Enter True Type Font Path':'Enter Valid Font Config Value'),
			'max_length' => '100'
			)
		),
	'poller' => array(
		'poller_header' => array(
			'friendly_name' => 'General',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'poller_enabled' => array(
			'friendly_name' => 'Data Collection Enabled',
			'description' => 'If you wish to stop the polling process completely, uncheck this box.',
			'method' => 'checkbox',
			'default' => 'on',
			'tab' => 'poller'
			),
		'poller_type' => array(
			'friendly_name' => 'Poller Type',
			'description' => 'The poller type to use.  This setting will take effect at next polling interval.',
			'method' => 'drop_array',
			'default' => 1,
			'array' => $poller_options,
			),
		'poller_interval' => array(
			'friendly_name' => 'Poller Interval',
			'description' => 'The polling interval in use.  This setting will effect how often rrd files are checked and updated.
			<strong><u>NOTE: If you change this value, you must re-populate the poller cache.  Failure to do so, may result in lost data.</u></strong>',
			'method' => 'drop_array',
			'default' => 300,
			'array' => $poller_intervals,
			),
		'cron_interval' => array(
			'friendly_name' => 'Cron Interval',
			'description' => 'The cron interval in use.  You need to set this setting to the interval that your cron or scheduled task is currently running.',
			'method' => 'drop_array',
			'default' => 300,
			'array' => $cron_intervals,
			),
		'concurrent_processes' => array(
			'friendly_name' => 'Maximum Concurrent Poller Processes',
			'description' => 'The number of concurrent processes to execute.  Using a higher number when using cmd.php will improve performance.  Performance improvements in spine are best resolved with the threads parameter',
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
			),
		'process_leveling' => array(
			'friendly_name' => 'Balance Process Load',
			'description' => 'If you choose this option, Cacti will attempt to balance the load of each poller process by equally distributing poller items per process.',
			'method' => 'checkbox',
			'default' => 'on'
			),
		'oid_increasing_check_disable' => array(
			'friendly_name' => 'Disable increasing OID Check',
			'description' => 'Controls disabling check for increasing OID while walking OID tree.',
			'method' => 'checkbox',
			'default' => ''
			),
		'spine_header' => array(
			'friendly_name' => 'Spine Specific Execution Parameters',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'max_threads' => array(
			'friendly_name' => 'Maximum Threads per Process',
			'description' => 'The maximum threads allowed per process.  Using a higher number when using Spine will improve performance.',
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
			),
		'php_servers' => array(
			'friendly_name' => 'Number of PHP Script Servers',
			'description' => 'The number of concurrent script server processes to run per Spine process.  Settings between 1 and 10 are accepted.  This parameter will help if you are running several threads and script server scripts.',
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
			),
		'script_timeout' => array(
			'friendly_name' => 'Script and Script Server Timeout Value',
			'description' => 'The maximum time that Cacti will wait on a script to complete.  This timeout value is in seconds',
			'method' => 'textbox',
			'default' => '25',
			'max_length' => '10',
			'size' => '5'
			),
		'max_get_size' => array(
			'friendly_name' => 'The Maximum SNMP OIDs Per SNMP Get Request',
			'description' => 'The maximum number of snmp get OIDs to issue per snmpbulkwalk request.  Increasing this value speeds poller performance over slow links.  The maximum value is 100 OIDs.  Decreasing this value to 0 or 1 will disable snmpbulkwalk',
			'method' => 'textbox',
			'default' => '10',
			'max_length' => '10',
			'size' => '5'
			),
		'availability_header' => array(
			'friendly_name' => 'Device Availability Settings',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'availability_method' => array(
			'friendly_name' => 'Downed Device Detection',
			'description' => 'The method Cacti will use to determine if a host is available for polling.  <br><i>NOTE: It is recommended that, at a minimum, SNMP always be selected.</i>',
			'method' => 'drop_array',
			'default' => AVAIL_SNMP,
			'array' => $availability_options,
			),
		'ping_method' => array(
			'friendly_name' => 'Ping Type',
			'description' => 'The type of ping packet to sent.  <br><i>NOTE: ICMP requires that the Cacti Service ID have root privilages in Unix.</i>',
			'method' => 'drop_array',
			'default' => PING_UDP,
			'array' => $ping_methods,
			),
		'ping_port' => array(
			'friendly_name' => 'Ping Port',
			'description' => 'When choosing either TCP or UDP Ping, which port should be checked for availability of the host prior to polling.',
			'method' => 'textbox',
			'default' => '23',
			'max_length' => '10',
			'size' => '5'
			),
		'ping_timeout' => array(
			'friendly_name' => 'Ping Timeout Value',
			'description' => 'The timeout value to use for host ICMP and UDP pinging.  This host SNMP timeout value applies for SNMP pings.',
			'method' => 'textbox',
			'default' => '400',
			'max_length' => '10',
			'size' => '5'
			),
		'ping_retries' => array(
			'friendly_name' => 'Ping Retry Count',
			'description' => 'The number of times Cacti will attempt to ping a host before failing.',
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
			),
		'updown_header' => array(
			'friendly_name' => 'Device Up/Down Settings',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'ping_failure_count' => array(
			'friendly_name' => 'Failure Count',
			'description' => 'The number of polling intervals a host must be down before logging an error and reporting host as down.',
			'method' => 'textbox',
			'default' => '2',
			'max_length' => '10',
			'size' => '5'
			),
		'ping_recovery_count' => array(
			'friendly_name' => 'Recovery Count',
			'description' => 'The number of polling intervals a host must remain up before returning host to an up status and issuing a notice.',
			'method' => 'textbox',
			'default' => '3',
			'max_length' => '10',
			'size' => '5'
			)
		),
	'authentication' => array(
		'auth_header' => array(
			'friendly_name' => 'General',
			'method' => 'spacer',
			),
		'auth_method' => array(
			'friendly_name' => 'Authentication Method',
			'description' => '<blockquote><i>None</i> - No authentication will be used, all users will have full access.<br><br><i>Builtin Authentication</i> - Cacti handles user authentication, which allows you to create users and give them rights to different areas within Cacti.<br><br><i>Web Basic Authentication</i> - Authentication is handled by the web server. Users can be added or created automatically on first login if the Template User is defined, otherwise the defined guest permissions will be used.<br><br><i>LDAP Authentication</i> - Allows for authentication against a LDAP server. Users will be created automatically on first login if the Template User is defined, otherwise the defined guest permissions will be used.  If PHPs LDAP module is not enabled, LDAP Authentication will not appear as a selectable option.<br><br><i>Multiple LDAP/AD Domain Authentication</i> - Allows administrators to support multiple desparate groups from different LDAP/AD directories to access Cacti resources.  Just as LDAP Authentication, the PHP LDAP module is required to utilize this method.</blockquote>',
			'method' => 'drop_array',
			'default' => 1,
			'array' => $auth_methods
			),
		'auth_cache_enabled' => array(
			'friendly_name' => 'Support Authentication Cookies',
			'description' => "If a user authenticates and selects 'Keep me signed in', an authentication cookie will be created on the users computer allowing that user to stay logged in.  The authentication cookie expires after 90 days of non-use.",
			'default' => '',
			'method' => 'checkbox'
			),
		'special_users_header' => array(
			'friendly_name' => 'Special Users',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'guest_user' => array(
			'friendly_name' => 'Guest User',
			'description' => 'The name of the guest user for viewing graphs; is "No User" by default.',
			'method' => 'drop_sql',
			'none_value' => 'No User',
			'sql' => 'SELECT username AS id, username AS name FROM user_auth WHERE realm = 0 ORDER BY username',
			'default' => '0'
			),
		'user_template' => array(
			'friendly_name' => 'User Template',
			'description' => 'The name of the user that cacti will use as a template for new Web Basic and LDAP users; is "guest" by default.',
			'method' => 'drop_sql',
			'none_value' => 'No User',
			'sql' => 'SELECT username AS id, username AS name FROM user_auth WHERE realm = 0 ORDER BY username',
			'default' => '0'
			),
		'secpass_header' => array(
			'friendly_name' => 'Local Account Complexity Requirements',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'secpass_minlen' => array(
			'friendly_name' => 'Minimum Length',
			'description' => 'This is minimal length of allowed passwords.',
			'method' => 'textbox',
			'default' => '8',
			'max_length' => 2,
			'size' => 4
			),
		'secpass_reqmixcase' => array(
			'friendly_name' => 'Require Mix Case',
			'description' => 'This will require new passwords to contains both lower and upper case characters.',
			'method' => 'checkbox',
			'default' => 'on',
			),
		'secpass_reqnum' => array(
			'friendly_name' => 'Require Number',
			'description' => 'This will require new passwords to contain at least 1 numberical character.',
			'method' => 'checkbox',
			'default' => 'on',
			),
		'secpass_reqspec' => array(
			'friendly_name' => 'Require Special Character',
			'description' => 'This will require new passwords to contain at least 1 special character.',
			'method' => 'checkbox',
			'default' => 'on',
			),
		'secpass_forceold' => array(
			'friendly_name' => 'Force Complexity Upon Old Passwords',
			'description' => 'This will require all old passwords to also meet the new complexity requirements upon login.  If not met, it will force a password change.',
			'method' => 'checkbox',
			'default' => '',
			),
		'secpass_expireaccount' => array(
			'friendly_name' => 'Expire Inactive Accounts',
			'description' => 'This is maximum number of days before inactive accounts are disabled.  The Admin account is excluded from this policy.',
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'  => 'Disabled', 
				'30'  => '30 Days', 
				'60'  => '60 Days', 
				'90'  => '90 Days', 
				'120'  => '120 Days', 
				'365'  => '1 Year',
				'730'  => '2 Years')
			),
		'secpass_expirepass' => array(
			'friendly_name' => 'Expire Password',
			'description' => 'This is maximum number of days before a password is set to expire.',
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'  => 'Disabled', 
				'30'  => '30 Days', 
				'60'  => '60 Days', 
				'90'  => '90 Days', 
				'120'  => '120 Days')
			),
		'secpass_history' => array(
			'friendly_name' => 'Password History',
			'description' => 'Remember this number of old passwords and disallow re-using them.',
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'  => 'Disabled', 
				'1'  => '1 Change', 
				'2'  => '2 Changes', 
				'3'  => '3 Changes', 
				'4'  => '4 Changes', 
				'5'  => '5 Changes',
				'6'  => '6 Changes',
				'7'  => '7 Changes',
				'8'  => '8 Changes',
				'9'  => '9 Changes',
				'10' => '10 Changes',
				'11' => '11 Changes',
				'12' => '12 Changes')
			),
		'secpass_lock_header' => array(
			'friendly_name' => 'Account Locking',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'secpass_lockfailed' => array(
			'friendly_name' => 'Lock Accounts',
			'description' => 'Lock an account after this many failed attempts in 1 hour.',
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'  => 'Disabled', 
				'1'  => '1 Attempt', 
				'2'  => '2 Attempts', 
				'3'  => '3 Attempts', 
				'4'  => '4 Attempts', 
				'5'  => '5 Attempts',
				'6'  => '6 Attempts')
			),
		'secpass_unlocktime' => array(
			'friendly_name' => 'Auto Unlock',
			'description' => 'An account will automatically be unlocked after this many minutes.  Even if the correct password is entered, the account will not unlock until this time limit has been met.  Max of 1440 minutes (1 Day)',
			'method' => 'drop_array',
			'default' => '60',
			'array' => array(
				'0'  => 'Disabled', 
				'1'  => '1 Minute', 
				'2'  => '2 Minutes', 
				'5'  => '5 Minutes',
				'10'  => '10 Minutes',
				'20'  => '20 Minutes',
				'30'  => '30 Minutes',
				'60'  => '1 Hour',
				'120'  => '2 Hours',
				'240'  => '4 Hours',
				'480'  => '8 Hours',
				'960'  => '16 Hours',
				'1440'  => '1 Day')
			),
		'ldap_general_header' => array(
			'friendly_name' => 'LDAP General Settings',
			'method' => 'spacer'
			),
		'ldap_server' => array(
			'friendly_name' => 'Server',
			'description' => 'The dns hostname or ip address of the server.',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_port' => array(
			'friendly_name' => 'Port Standard',
			'description' => 'TCP/UDP port for Non SSL communications.',
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '389',
			'size' => '5'
			),
		'ldap_port_ssl' => array(
			'friendly_name' => 'Port SSL',
			'description' => 'TCP/UDP port for SSL communications.',
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '636',
			'size' => '5'
			),
		'ldap_version' => array(
			'friendly_name' => 'Protocol Version',
			'description' => 'Protocol Version that the server supports.',
			'method' => 'drop_array',
			'default' => '3',
			'array' => $ldap_versions
			),
		'ldap_encryption' => array(
			'friendly_name' => 'Encryption',
			'description' => 'Encryption that the server supports. TLS is only supported by Protocol Version 3.',
			'method' => 'drop_array',
			'default' => '0',
			'array' => $ldap_encryption
			),
		'ldap_referrals' => array(
			'friendly_name' => 'Referrals',
			'description' => 'Enable or Disable LDAP referrals.  If disabled, it may increase the speed of searches.',
			'method' => 'drop_array',
			'default' => '0',
			'array' => array( '0' => 'Disabled', '1' => 'Enable')
			),
		'ldap_mode' => array(
			'friendly_name' => 'Mode',
		'description' => 'Mode which cacti will attempt to authenicate against the LDAP server.<blockquote><i>No Searching</i> - No Distinguished Name (DN) searching occurs, just attempt to bind with the provided Distinguished Name (DN) format.<br><br><i>Anonymous Searching</i> - Attempts to search for username against LDAP directory via anonymous binding to locate the users Distinguished Name (DN).<br><br><i>Specific Searching</i> - Attempts search for username against LDAP directory via Specific Distinguished Name (DN) and Specific Password for binding to locate the users Distinguished Name (DN).',
			'method' => 'drop_array',
			'default' => '0',
			'array' => $ldap_modes
			),
		'ldap_dn' => array(
			'friendly_name' => 'Distinguished Name (DN)',
			'description' => 'Distinguished Name syntax, such as for windows: <i>"&lt;username&gt;@win2kdomain.local"</i> or for OpenLDAP: <i>"uid=&lt;username&gt;,ou=people,dc=domain,dc=local"</i>.   "&lt;username&gt" is replaced with the username that was supplied at the login prompt.  This is only used when in "No Searching" mode.',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_group_require' => array(
			'friendly_name' => 'Require Group Membership',
			'description' => 'Require user to be member of group to authenicate. Group settings must be set for this to work, enabling without proper group settings will cause authenication failure.',
			'default' => '',
			'method' => 'checkbox'
			),
		'ldap_group_header' => array(
			'friendly_name' => 'LDAP Group Settings',
			'method' => 'spacer'
			),
		'ldap_group_dn' => array(
			'friendly_name' => 'Group Distingished Name (DN)',
			'description' => 'Distingished Name of the group that user must have membership.',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_group_attrib' => array(
			'friendly_name' => 'Group Member Attribute',
			'description' => 'Name of the attribute that contains the usernames of the members.',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_group_member_type' => array(
			'friendly_name' => 'Group Member Type',
			'description' => 'Defines if users use full Distingished Name or just Username in the defined Group Member Attribute.',
			'method' => 'drop_array',
			'default' => 1,
			'array' => array( 1 => 'Distingished Name', 2 => 'Username' )
			),
		'ldap_search_base_header' => array(
			'friendly_name' => 'LDAP Specific Search Settings',
			'method' => 'spacer'
			),
		'ldap_search_base' => array(
			'friendly_name' => 'Search Base',
			'description' => 'Search base for searching the LDAP directory, such as <i>\'dc=win2kdomain,dc=local\'</i> or <i>\'ou=people,dc=domain,dc=local\'</i>.',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_search_filter' => array(
			'friendly_name' => 'Search Filter',
			'description' => 'Search filter to use to locate the user in the LDAP directory, such as for windows: <i>\'(&amp;(objectclass=user)(objectcategory=user)(userPrincipalName=&lt;username&gt;*))\'</i> or for OpenLDAP: <i>\'(&(objectClass=account)(uid=&lt;username&gt))\'</i>.  \'&lt;username&gt\' is replaced with the username that was supplied at the login prompt. ',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_specific_dn' => array(
			'friendly_name' => 'Search Distingished Name (DN)',
			'description' => 'Distinguished Name for Specific Searching binding to the LDAP directory.',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_specific_password' => array(
			'friendly_name' => 'Search Password',
			'description' => 'Password for Specific Searching binding to the LDAP directory.',
			'method' => 'textbox_password',
			'max_length' => '255'
			),
		),
	'mail' => array(
		'settings_web_url' => array(
			'friendly_name' => 'URL Linking',
			'method' => 'spacer',
			),
		'base_url' => array(
			'friendly_name' => 'Server Base URL',
			'description' => 'This is a the server location that will be used for links to the Cacti site.',
			'method' => 'textbox',
			'max_length' => 255,
			'size' => '60',
			'default' => 'http://' . gethostname()
			),
		'settings_email_header' => array(
			'friendly_name' => 'Emailing Options<div id="emailtest" class="emailtest textSubHeaderDark">Send a Test Email</div>',
			'method' => 'spacer',
			),
		'settings_test_email' => array(
			'friendly_name' => 'Test Email',
			'description' => 'This is a email account used for sending a test message to ensure everything is working properly.',
			'method' => 'textbox',
			'max_length' => 255,
			),
		'settings_how' => array(
			'friendly_name' => 'Mail Services',
			'description' => 'Which mail service to use in order to send mail',
			'method' => 'drop_array',
			'default' => 'PHP Mail() Function',
			'array' => array('PHP Mail() Function', 'Sendmail', 'SMTP'),
			),
		'settings_from_email' => array(
			'friendly_name' => 'From Email Address',
			'description' => 'This is the email address that the email will appear from.',
			'method' => 'textbox',
			'max_length' => 255,
			),
		'settings_from_name' => array(
			'friendly_name' => 'From Name',
			'description' => 'This is the actual name that the email will appear from.',
			'method' => 'textbox',
			'max_length' => 255,
			),
		'settings_wordwrap' => array(
			'friendly_name' => 'Word Wrap',
			'description' => 'This is how many characters will be allowed before a line in the email is automatically word wrapped. (0 = Disabled)',
			'method' => 'textbox',
			'default' => 120,
			'max_length' => 4,
			'size' => 5
			),
		'settings_sendmail_header' => array(
			'friendly_name' => 'Sendmail Options',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'settings_sendmail_path' => array(
			'friendly_name' => 'Sendmail Path',
			'description' => 'This is the path to sendmail on your server. (Only used if Sendmail is selected as the Mail Service)',
			'method' => 'filepath',
			'max_length' => 255,
			'default' => '/usr/sbin/sendmail',
			),
		'settings_smtp_header' => array(
			'friendly_name' => 'SMTP Options',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'settings_smtp_host' => array(
			'friendly_name' => 'SMTP Hostname',
			'description' => 'This is the hostname/IP of the SMTP Server you will send the email to. For failover, separate your hosts using a semi-colon.',
			'method' => 'textbox',
			'default' => 'localhost',
			'max_length' => 255,
			),
		'settings_smtp_port' => array(
			'friendly_name' => 'SMTP Port',
			'description' => 'The port on the SMTP Server to use.',
			'method' => 'textbox',
			'max_length' => 255,
			'default' => 25,
			'size' => 5
			),
		'settings_smtp_username' => array(
			'friendly_name' => 'SMTP Username',
			'description' => 'The username to authenticate with when sending via SMTP. (Leave blank if you do not require authentication.)',
			'method' => 'textbox',
			'max_length' => 255,
			),
		'settings_smtp_password' => array(
			'friendly_name' => 'SMTP Password',
			'description' => 'The password to authenticate with when sending via SMTP. (Leave blank if you do not require authentication.)',
			'method' => 'textbox_password',
			'max_length' => 255,
			),
		'settings_smtp_secure' => array(
			'friendly_name' => 'SMTP Security',
			'description' => 'The encryption method to use for the email.',
			'method' => 'drop_array',
			'array' => array('none' => 'None', 'ssl' => 'SSL', 'tls' => 'TLS'),
			'default' => 'none'
			),
		'settings_smtp_timeout' => array(
			'friendly_name' => 'SMTP Timeout',
			'description' => 'Please enter the SMTP timeout in seconds.',
			'method' => 'textbox',
			'default' => '5',
			'max_length' => '10',
			'size' => '5'
			),
		'reports_header' => array(
			'friendly_name' => 'Reporting Presets',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'reports_default_image_format' => array(
			'friendly_name' => 'Default Graph Image Format',
			'description' => 'When creating a new report, what image type should be used for the inline graphs.',
			'method' => 'drop_array',
			'default' => REPORTS_TYPE_INLINE_PNG,
			'array' => $attach_types
			),
		'reports_max_attach' => array(
			'friendly_name' => 'Maximum E-Mail Size',
			'description' => 'The maximum size of the E-Mail message including all attachements.',
			'method' => 'drop_array',
			'default' => REPORTS_DEFAULT_MAX_SIZE,
			'array' => $attachment_sizes
			),
		'reports_log_verbosity' => array(
			'friendly_name' => 'Poller Logging Level for Cacti Reporting',
			'description' => 'What level of detail do you want sent to the log file. WARNING: Leaving in any other status than NONE or LOW can exaust your disk space rapidly.',
			'method' => 'drop_array',
			'default' => POLLER_VERBOSITY_LOW,
			'array' => $logfile_verbosity,
			),
		'reports_allow_ln' => array(
			'friendly_name' => 'Enable Lotus Notus (R) tweak',
			'description' => 'Enable code tweak for specific handling of Lotus Notes Mail Clients.',
			'method' => 'checkbox',
			'default' => '',
			),
		'settings_dns_header' => array(
			'friendly_name' => 'DNS Options',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'settings_dns_primary' => array(
			'friendly_name' => 'Primary DNS IP Address',
			'description' => 'Enter the primary DNS IP Address to utilize for reverse lookups.',
			'method' => 'textbox',
			'default' => '',
			'max_length' => '30'
			),
		'settings_dns_secondary' => array(
			'friendly_name' => 'Secondary DNS IP Address',
			'description' => 'Enter the secondary DNS IP Address to utilize for reverse lookups.',
			'method' => 'textbox',
			'default' => '',
			'max_length' => '30'
			),
		'settings_dns_timeout' => array(
			'friendly_name' => 'DNS Timeout',
			'description' => 'Please enter the DNS timeout in milliseconds.  Cacti uses a PHP based DNS resolver.',
			'method' => 'textbox',
			'default' => '500',
			'max_length' => '10',
			'size' => '5'
			),
		),
	'dsstats' => array(
		'dsstats_hq_header' => array(
			'friendly_name' => 'Data Sources Statistics',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'dsstats_enable' => array(
			'friendly_name' => 'Enable Data Source Statistics Collection',
			'description' => 'Should Data Source Statistics be collected for this Cacti system?',
			'method' => 'checkbox',
			'default' => ''
			),
		'dsstats_daily_interval' => array(
			'friendly_name' => 'Daily Update Frequency',
			'description' => 'How frequent should Daily Stats be updated?',
			'default' => '60',
			'method' => 'drop_array',
			'array' => $dsstats_refresh_interval
			),
		'dsstats_hourly_duration' => array(
			'friendly_name' => 'Hourly Average Window',
			'description' => 'The number of consecutive hours that represent the hourly
			average.  Keep in mind that a setting too high can result in very large memory tables',
			'default' => '60',
			'method' => 'drop_array',
			'array' => $dsstats_hourly_avg
			),
		'dsstats_major_update_time' => array(
			'friendly_name' => 'Maintenance Time',
			'description' => 'What time of day should Weekly, Monthly, and Yearly Data be updated?  Format is HH:MM [am/pm]',
			'method' => 'textbox',
			'default' => '12:00am',
			'max_length' => '20',
			'size' => '10'
			),
		'dsstats_poller_mem_limit' => array(
			'friendly_name' => 'Memory Limit for dsstats and Poller',
			'description' => 'The maximum amount of memory for the Cacti Poller and dsstats Poller',
			'method' => 'drop_array',
			'default' => '1024',
			'array' => $dsstats_max_memory
			),
		'dsstats_debug_header' => array(
			'friendly_name' => 'Debugging',
			'method' => 'spacer',
			'collapsible' => 'true',
			),
		'dsstats_rrdtool_pipe' => array(
			'friendly_name' => 'Enable Single RRDtool Pipe',
			'description' => 'Using a single pipe will speed the RRDtool process by 10x.  However, RRDtool crashes
			problems can occur.  Disable this setting if you need to find a bad RRDfile.',
			'method' => 'checkbox',
			'default' => 'on'
			),
		'dsstats_partial_retrieve' => array(
			'friendly_name' => 'Enable Partial Reference Data Retrieve',
			'description' => 'If using a large system, it may be beneficial for you to only gather data as needed
			during Cacti poller passes.  If you check this box, you DSStats will gather data this way.',
			'method' => 'checkbox',
			'default' => ''
			)
		),
	'boost' => array(
		'boost_hq_header' => array(
			'friendly_name' => 'On Demand RRD Update Settings',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'boost_rrd_update_enable' => array(
			'friendly_name' => 'Enable On Demand RRD Updating',
			'description' => 'Should Boost enable on demand RRD updating in Cacti?  If you disable, this change will not take affect until after the next polling cycle.',
			'method' => 'checkbox',
			'default' => ''
			),
		'boost_rrd_update_system_enable' => array(
			'friendly_name' => 'System Level RRD Updater',
			'description' => 'Before RRD On Demand Update Can be Cleared, a poller run must alway pass',
			'method' => 'hidden',
			'default' => ''
			),
		'boost_rrd_update_interval' => array(
			'friendly_name' => 'How Often Should Boost Update All RRDs',
			'description' => 'When you enable boost, your RRD files are only updated when they are requested by a user, or when this time period elapses.',
			'default' => '60',
			'method' => 'drop_array',
			'default' => '60',
			'array' => $boost_refresh_interval
			),
		'boost_rrd_update_max_records' => array(
			'friendly_name' => 'Maximum Records',
			'description' => 'If the boost output table exceeds this size, in records, an update will take place.',
			'method' => 'textbox',
			'default' => '1000000',
			'max_length' => '20',
			'size' => '10'
			),
		'boost_rrd_update_max_records_per_select' => array(
			'friendly_name' => 'Maximum Data Source Items Per Pass',
			'description' => 'To optimize performance, the boost RRD updater needs to know how many Data Source Items
			should be retrieved in one pass.  Please be careful not to set too high as graphing performance during
			major updates can be compromised.  If you encounter graphing or polling slowness during updates, lower this
			number.  The default value is 50000.',
			'method' => 'drop_array',
			'default' => '50000',
			'array' => $boost_max_rows_per_select
			),
		'boost_rrd_update_string_length' => array(
			'friendly_name' => 'Maximum Argument Length',
			'description' => 'When boost sends update commands to RRDtool, it must not exceed the operating systems
			Maximum Argument Length.  This varies by operating system and kernel level.  For example:
			Windows 2000 <= 2048, FreeBSD <= 65535, Linux 2.6.22-- <= 131072, Linux 2.6.23++ unlimited',
			'method' => 'textbox',
			'default' => '2000',
			'max_length' => '20',
			'size' => '10'
			),
		'boost_poller_mem_limit' => array(
			'friendly_name' => 'Memory Limit for Boost and Poller',
			'description' => 'The maximum amount of memory for the Cacti Poller and Boosts Poller',
			'method' => 'drop_array',
			'default' => '1024',
			'array' => $boost_max_memory
			),
		'boost_rrd_update_max_runtime' => array(
			'friendly_name' => 'Maximum RRD Update Script Run Time',
			'description' => 'The maximum boot poller run time allowed prior to boost issuing warning
			messages relative to possible hardware/software issues preventing proper updates.',
			'method' => 'drop_array',
			'default' => '1200',
			'array' => $boost_max_runtime
			),
		'boost_redirect' => array(
			'friendly_name' => 'Enable direct population of poller_output_boost table by spine',
			'description' => 'Enables direct insert of records into poller output boost with results in a 25% time reduction in each poll cycle.',
			'method' => 'checkbox',
			'default' => ''
			),
		'boost_srv_header' => array(
			'friendly_name' => 'Boost Server Settings',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'boost_server_enable' => array(
			'friendly_name' => 'Enable Boost Server',
			'description' => 'Should Boost server be used to RRDupdates?  If you do not select, then your web server needs R/W to rra directories.',
			'method' => 'checkbox',
			'default' => ''
			),
		'boost_server_effective_user' => array(
			'friendly_name' => 'Effective User ID',
			'description' => 'This UNIX/LINUX only option allows you to use this users UID to create and otherwise operate on RRDs.  In order to use this, you must also be using the POSIX module in PHP.  A Boost Server restart is required to enact any changes in this setting.',
			'method' => 'textbox',
			'default' => 'root',
			'max_length' => '20'
			),
		'boost_server_multiprocess' => array(
			'friendly_name' => 'Multiprocess Server',
			'description' => 'Do you want the boost server to fork a separate update process for each boost request?  A Boost Server restart is required to enact any changes in this setting.',
			'method' => 'drop_array',
			'default' => '1',
			'array' => array('0' => 'No', '1' => 'Yes')
			),
		'boost_path_rrdupdate' => array(
			'friendly_name' => 'RRDUpdate Path',
			'description' => 'If you are using the Multiprocess Boost server, it is best to utilize the rrdupdate binary to update your RRDs.  Specify its path here.  Otherwise, boost will use the rrdtool binary.',
			'method' => 'textbox',
			'default' => '',
			'max_length' => '255'
			),
		'boost_server_hostname' => array(
			'friendly_name' => 'Hostname or IP for Boost Server',
			'description' => 'The Hostname/IP for the boost server.',
			'method' => 'textbox',
			'default' => 'localhost',
			'max_length' => '100'
			),
		'boost_server_listen_port' => array(
			'friendly_name' => 'TCP Port to Communicate On',
			'description' => 'The boost server will listen on this port and the client will talk to this port.  A Boost Server restart is required to enact any changes in this setting.',
			'method' => 'textbox',
			'default' => '9050',
			'max_length' => '10'
			),
		'boost_server_timeout' => array(
			'friendly_name' => 'Boost Server Timeout',
			'description' => 'The timeout, in seconds, that the client should wait on the boost server before giving up.',
			'method' => 'textbox',
			'default' => '2',
			'max_length' => '10'
			),
		'boost_server_clients' => array(
			'friendly_name' => 'Allowed Web Hosts',
			'description' => 'A comma separated list of host IP Addresses allowed to connect to the boost server.',
			'method' => 'textbox',
			'default' => '127.0.0.1',
			'max_length' => '512'
			),
		'boost_png_header' => array(
			'friendly_name' => 'Image Caching',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'boost_png_cache_enable' => array(
			'friendly_name' => 'Enable Image Caching',
			'description' => 'Should image caching be enabled?',
			'method' => 'checkbox',
			'default' => ''
			),
		'boost_png_cache_directory' => array(
			'friendly_name' => 'Location for Image Files',
			'description' => 'Specify the location where Boost should place your image files.  These files will be automatically purged by the poller when they expire.',
			'method' => 'dirpath',
			'max_length' => '255',
			'default' => ''
			),
		'boost_process_header' => array(
			'friendly_name' => 'Process Interlocking',
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'path_boost_log' => array(
			'friendly_name' => 'Boost Debug Log',
			'description' => 'If this field is non-blank, Boost will log RRDupdate output from the boost
			poller process.',
			'method' => 'filepath',
			'default' => '',
			'max_length' => '255'
			)
		),
	'storage' => array(
		'general_header' => array(
			'friendly_name' => 'General',
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'storage_location' => array(
			'friendly_name' => 'Location',
			'description' => 'Choose if RRDs will be stored locally or being handled by an external RRDtool proxy server. Note: Once this setting has been chanced poller cache needs to be rebuild.',
			'method' => 'drop_array',
			'default' => 0,
			'array' => array ('Local', 'RRDtool Proxy Server'),
			),
		'extended_paths' => array(
			'friendly_name' => 'Structured RRD Path (/host_id/local_data_id.rrd)',
			'description' => 'Use a seperate subfolder for each hosts RRD files.',
			'method' => 'checkbox'
			),
		'rrdp_header' => array(
			'friendly_name' => 'RRDtool Proxy Server',
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'rrdp_server' => array(
			'friendly_name' => 'Proxy Server',
			'description' => 'The dns hostname or ip address of the rrdtool proxy server.',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'rrdp_port' => array(
			'friendly_name' => 'Proxy Port Number',
			'description' => 'TCP port for encrypted communication.',
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '40301',
			'size' => '5'
			),
		'rrdp_fingerprint' => array(
			'friendly_name' => 'RSA Fingerprint',
			'description' => 'The fingerprint of the current public RSA key the proxy is using. This is required to establish a trusted connection.',
			'method' => 'textbox',
			'max_length' => '47',
			'default' => '',
			'size' => '47'
			),
		'rrdp_header2' => array(
			'friendly_name' => 'RRDtool Proxy Server - Backup',
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'rrdp_server_backup' => array(
			'friendly_name' => 'Proxy Server',
			'description' => 'The dns hostname or ip address of the rrdtool backup proxy server if proxy is running in MSR mode.',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'rrdp_port_backup' => array(
			'friendly_name' => 'Proxy Port Number',
			'description' => 'TCP port for encrypted communication with the backup proxy.',
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '40301',
			'size' => '5'
			),
		'rrdp_fingerprint_backup' => array(
			'friendly_name' => 'RSA Fingerprint',
			'description' => 'The fingerprint of the current public RSA key the backup proxy is using. This required to establish a trusted connection.',
			'method' => 'textbox',
			'max_length' => '47',
			'default' => '',
			'size' => '47'
			),
 		),
	);

$settings_user = array(
	'general' => array(
		'selected_theme' => array(
			'friendly_name' => 'Theme',
			'description' => 'Please select one of the available Themes to skin your Cacti with.',
			'method' => 'drop_array',
			'default' => 'classic',
			'array' => $themes
			),
		'default_view_mode' => array(
			'friendly_name' => 'Default View Mode',
			'description' => 'Which mode you want displayed when you visit "graph_view.php"',
			'method' => 'drop_array',
			'array' => $graph_views,
			'default' => '1'
			),
		'show_graph_title' => array(
			'friendly_name' => 'Show Graph Title',
			'description' => 'Display the graph title on the page so that it may be searched using the browser.',
			'method' => 'checkbox',
			'default' => ''
			),
		'default_date_format' => array(
			'friendly_name' => 'Graph Date Display Format',
			'description' => 'The date format to use for graphs',
			'method' => 'drop_array',
			'array' => $graph_dateformats,
			'default' => GD_Y_MO_D
			),
		'default_datechar' => array(
			'friendly_name' => 'Graph Date Separator',
			'description' => 'The date separator to be used for graphs',
			'method' => 'drop_array',
			'array' => $graph_datechar,
			'default' => GDC_SLASH
			),
		'page_refresh' => array(
			'friendly_name' => 'Page Refresh',
			'description' => 'The number of seconds between automatic page refreshes.',
			'method' => 'drop_array',
			'default' => '300',
			'array' => array('15' => '15 Seconds', '20' => '20 Seconds', '30' => '30 Seconds', '60' => '1 Minute', '300' => '5 Minutes')
			),
		'preview_graphs_per_page' => array(
			'friendly_name' => 'Preview Graphs Per Page',
			'description' => 'The number of graphs to display on one page in preview mode.',
			'method' => 'drop_array',
			'default' => '10',
			'array' => $graphs_per_page
			)
		),
	'timespan' => array(
		'timespan_sel' => array(
			'friendly_name' => 'Display Graph View Timespan Selector',
			'description' => 'Choose if you want the time span selection box to be displayed.',
			'method' => 'checkbox',
			'default' => 'on'
		),
		'default_rra_id' => array(
			'friendly_name' => 'Default Time Range',
			'description' => 'The default RRA to use when for Graphs the Timespan selector is disabled.',
			'method' => 'drop_sql',
			'sql' => 'SELECT id,name FROM rra ORDER BY timespan',
			'default' => '1'
			),
		'default_timespan' => array(
			'friendly_name' => 'Default Graph View Timespan',
			'description' => 'The default timespan you wish to be displayed when you display graphs',
			'method' => 'drop_array',
			'array' => $graph_timespans,
			'default' => GT_LAST_DAY
			),
		'default_timeshift' => array(
			'friendly_name' => 'Default Graph View Timeshift',
			'description' => 'The default timeshift you wish to be displayed when you display graphs',
			'method' => 'drop_array',
			'array' => $graph_timeshifts,
			'default' => GTS_1_DAY
			),
		'allow_graph_dates_in_future' => array(
			'friendly_name' => 'Allow Graph to extend to Future',
			'description' => 'When displaying Graphs, allow Graph Dates to extend "to future"',
			'method' => 'checkbox',
			'default' => 'on'
		),
		'first_weekdayid' => array(
			'friendly_name' => 'First Day of the Week',
			'description' => 'The first Day of the Week for weekly Graph Displays',
			'method' => 'drop_array',
			'array' => $graph_weekdays,
			'default' => WD_MONDAY
			),
		'day_shift_start' => array(
			'friendly_name' => 'Start of Daily Shift',
			'description' => 'Start Time of the Daily Shift.',
			'method' => 'textbox',
			'default' => '07:00',
			'max_length' => '5',
			'size' => '7'
			),
		'day_shift_end' => array(
			'friendly_name' => 'End of Daily Shift',
			'description' => 'End Time of the Daily Shift.',
			'method' => 'textbox',
			'default' => '18:00',
			'max_length' => '5',
			'size' => '7'
			),
		),
	'thumbnail' => array(
		'thumbnail_sections' => array(
			'friendly_name' => 'Thumbnail Sections',
			'description' => 'Which portions of Cacti display Thumbnails by default.',
			'method' => 'checkbox_group',
			'items' => array(
				'thumbnail_section_preview' => array(
					'friendly_name' => 'Preview Mode',
					'default' => 'on'
					),
				'thumbnail_section_tree_2' => array(
					'friendly_name' => 'Tree View',
					'default' => ''
					)
				)
			),
		'num_columns' => array(
			'friendly_name' => 'Preview Thumbnail Columns',
			'description' => 'The number of columns to use when displaying Thumbnail graphs in Preview mode.',
			'method' => 'drop_array',
			'default' => '2',
			'array' => array('1' => '1 Column','2' => '2 Columns', '3' => '3 Columns', '4' => '4 Columns', '5' => '5 Columns', '6' => '6 Columns'),
			),
		'num_columns_tree' => array(
			'friendly_name' => 'Treeview Thumbnail Columns',
			'description' => 'The number of columns to use when displaying Thumbnail graphs in Tree mode.',
			'method' => 'drop_array',
			'default' => '2',
			'array' => array('1' => '1 Column','2' => '2 Columns', '3' => '3 Columns', '4' => '4 Columns', '5' => '5 Columns', '6' => '6 Columns'),
			),
		'default_height' => array(
			'friendly_name' => 'Thumbnail Height',
			'description' => 'The height of Thumbnail graphs in pixels.',
			'method' => 'textbox',
			'default' => '100',
			'max_length' => '10',
			'size' => '7'
			),
		'default_width' => array(
			'friendly_name' => 'Thumbnail Width',
			'description' => 'The width of Thumbnail graphs in pixels.',
			'method' => 'textbox',
			'default' => '300',
			'max_length' => '10',
			'size' => '7'
			),
		),
	'tree' => array(
		'default_tree_id' => array(
			'friendly_name' => 'Default Graph Tree',
			'description' => 'The default graph tree to use when displaying graphs in tree mode.',
			'method' => 'drop_sql',
			'sql' => 'SELECT id,name FROM graph_tree ORDER BY name',
			'default' => '0'
			),
		'treeview_graphs_per_page' => array(
			'friendly_name' => 'Graphs Per Page',
			'description' => 'The number of graphs to display on one page in preview mode.',
			'method' => 'drop_array',
			'default' => '10',
			'array' => $graphs_per_page
			),
		'expand_hosts' => array(
			'friendly_name' => 'Expand Devices',
			'description' => 'Choose whether to expand the Graph Templates and Data Queries used by a Device on Tree.',
			'method' => 'checkbox',
			'default' => ''
			)
		),
	'fonts' => array(
		'custom_fonts' => array(
			'friendly_name' => 'Use Custom Fonts',
			'description' => 'Choose whether to use your own custom fonts and font sizes or utilize the system defaults.',
			'method' => 'checkbox',
			'on_change' => 'graphSettings()',
			'default' => ''
			),
		'title_size' => array(
			'friendly_name' => 'Title Font Size',
			'description' => 'The size of the font used for Graph Titles',
			'method' => 'textbox',
			'default' => '12',
			'max_length' => '10'
			),
		'title_font' => array(
			'friendly_name' => 'Title Font File',
			'description' => 'The font file to use for Graph Titles',
			'method' => 'font',
			'max_length' => '100'
			),
		'legend_size' => array(
			'friendly_name' => 'Legend Font Size',
			'description' => 'The size of the font used for Graph Legend items',
			'method' => 'textbox',
			'default' => '10',
			'max_length' => '10'
			),
		'legend_font' => array(
			'friendly_name' => 'Legend Font File',
			'description' => 'The font file to be used for Graph Legend items',
			'method' => 'font',
			'max_length' => '100'
			),
		'axis_size' => array(
			'friendly_name' => 'Axis Font Size',
			'description' => 'The size of the font used for Graph Axis',
			'method' => 'textbox',
			'default' => '8',
			'max_length' => '10'
			),
		'axis_font' => array(
			'friendly_name' => 'Axis Font File',
			'description' => 'The font file to be used for Graph Axis items',
			'method' => 'font',
			'max_length' => '100'
			),
		'unit_size' => array(
			'friendly_name' => 'Unit Font Size',
			'description' => 'The size of the font used for Graph Units',
			'method' => 'textbox',
			'default' => '8',
			'max_length' => '10'
			),
		'unit_font' => array(
			'friendly_name' => 'Unit Font File',
			'description' => 'The font file to be used for Graph Unit items',
			'method' => 'font',
			'max_length' => '100'
			)
		)
	);

api_plugin_hook('config_settings');

