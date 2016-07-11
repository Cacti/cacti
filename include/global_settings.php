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
	'general' => __('General'),
	'path' => __('Paths'),
	'snmp' => __('Device Defaults'),
	'poller' => __('Poller'),
	'storage' => __('Data Storage'),
	'export' => __('Graph Export'),
	'visual' => __('Visual'),
	'authentication' => __('Authentication'),
	'dsstats' => __('Data Source Statistics'),
	'boost' => __('Performance'),
	'spikes' => __('Spikes'),
	'mail' => __('Mail/Reporting/DNS'));

$tabs_graphs = array(
	'general' => __('General Settings'),
	'timespan' => __('Time Spanning/Shifting'),
	'thumbnail' => __('Graph Thumbnail Settings'),
	'tree' => __('Tree Settings'),
	'fonts' => __('Graph Fonts'));

$spikekill_templates = array_rekey(db_fetch_assoc('SELECT DISTINCT gt.id, gt.name 
	FROM graph_templates AS gt 
	INNER JOIN graph_templates_item AS gti 
	ON gt.id=gti.graph_template_id 
	INNER JOIN data_template_rrd AS dtr 
	ON gti.task_item_id=dtr.id 
	WHERE gti.local_graph_id=0 AND data_source_type_id IN (3,2)
	ORDER BY name'), 'id', 'name');

/* setting information */
$settings = array(
	'path' => array(
		'dependent_header' => array(
			'friendly_name' => __('Required Tool Paths'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'path_snmpwalk' => array(
			'friendly_name' => __('snmpwalk Binary Path'),
			'description' => __('The path to your snmpwalk binary.'),
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_snmpget' => array(
			'friendly_name' => __('snmpget Binary Path'),
			'description' => __('The path to your snmpget binary.'),
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_snmpbulkwalk' => array(
			'friendly_name' => __('snmpbulkwalk Binary Path'),
			'description' => __('The path to your snmpbulkwalk binary.'),
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_snmpgetnext' => array(
			'friendly_name' => __('snmpgetnext Binary Path'),
			'description' => __('The path to your snmpgetnext binary.'),
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_snmptrap' => array(
			'friendly_name' => __('snmptrap Binary Path'),
			'description' => __('The path to your snmptrap binary.'),
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_rrdtool' => array(
			'friendly_name' => __('RRDTool Binary Path'),
			'description' => __('The path to the rrdtool binary.'),
			'method' => 'filepath',
			'max_length' => '255'
			),
		'path_php_binary' => array(
			'friendly_name' => __('PHP Binary Path'),
			'description' => __('The path to your PHP binary file (may require a php recompile to get this file).'),
			'method' => 'filepath',
			'max_length' => '255'
			),
		'logging_header' => array(
			'friendly_name' => __('Logging'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'path_cactilog' => array(
			'friendly_name' => __('Cacti Log File Path'),
			'description' => __('The path to your Cacti log file (if blank, defaults to <path_cacti>/log/cacti.log)'),
			'method' => 'filepath',
			'default' => $config['base_path'] . '/log/cacti.log',
			'max_length' => '255'
			),
		'logrotate_enabled' => array(
			'friendly_name' => __('Rotate the Cacti Log Nightly'),
			'description' => __('This will rotate the Cacti Log every night at midnight.'),
			'method' => 'checkbox',
			'default' => '',
			),
		'logrotate_retain' => array(
			'friendly_name' => __('Log Retention'),
			'description' => __('The number of days to retain old logs.  Use 0 to never remove any logs. (0-365)'),
			'method' => 'textbox',
			'default' => '7',
			'max_length' => 3,
			),
		'pollerpaths_header' => array(
			'friendly_name' => __('Alternate Poller Path'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'path_spine' => array(
			'friendly_name' => __('Spine Poller File Path'),
			'description' => __('The path to Spine binary.'),
			'method' => 'filepath',
			'max_length' => '255'
			),
		'rrdclean_header' => array(
			'friendly_name' => __('RRD Cleaner'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'rrd_autoclean' => array(
			'friendly_name' => __('RRDfile Auto Clean'),
			'description' => __('Automatically Delete, Archive, or Delete RRDfiles when removed from Cacti'),
			'method' => 'checkbox',
			'default' => ''
 			),
		'rrd_autoclean_method' => array(
			'friendly_name' => __('RRDfile Auto Clean Method'),
			'description' => __('The method used to Clean RRDfiles from Cacti after their deletion.'),
			'method' => 'drop_array',
			'array' => array('1' => 'Delete', '3' => 'Archive'),
			'default' => '1'
 			),
		'rrd_archive' => array(
			'friendly_name' => __('Archive directory'),
			'description' => __('This is the directory where rrd files are <strong>moved</strong> for <strong>Archive</strong>'),
			'method' => 'dirpath',
			'default' => $config['base_path'] . '/rra/archive/',
			'max_length' => 255,
			),
		),
	'general' => array(
		'event_logging_header' => array(
			'friendly_name' => __('Event Logging'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'log_destination' => array(
			'friendly_name' => __('Log File Destination'),
			'description' => __('How will Cacti handle event logging.'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => $logfile_options,
			),
		'web_log' => array(
			'friendly_name' => __('Web Events'),
			'description' => __('What Cacti website messages should be placed in the log.'),
			'method' => 'checkbox_group',
			'tab' => 'general',
			'items' => array(
				'log_snmp' => array(
					'friendly_name' => __('SNMP Messages'),
					'default' => ''
					),
				'log_graph' => array(
					'friendly_name' => __('Graph Syntax'),
					'default' => ''
					),
				'log_export' => array(
					'friendly_name' => __('Graph Export'),
					'default' => ''
					),
				'developer_mode' => array(
					'friendly_name' => __('Developer Mode'),
					'default' => ''
					)
				),
			),
		'poller_specific_header' => array(
			'friendly_name' => __('Poller Specific Logging'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'log_verbosity' => array(
			'friendly_name' => __('Poller Logging Level'),
			'description' => __('What level of detail do you want sent to the log file.  WARNING: Leaving in any other status than NONE or LOW can exaust your disk space rapidly.'),
			'method' => 'drop_array',
			'default' => POLLER_VERBOSITY_LOW,
			'array' => $logfile_verbosity,
			),
		'poller_log' => array(
			'friendly_name' => __('Poller Syslog/Eventlog Selection'),
			'description' => __('If you are using the Syslog/Eventlog, What Cacti poller messages should be placed in the Syslog/Eventlog.'),
			'method' => 'checkbox_group',
			'tab' => 'poller',
			'items' => array(
				'log_pstats' => array(
					'friendly_name' => __('Statistics'),
					'default' => ''
					),
				'log_pwarn' => array(
					'friendly_name' => __('Warnings'),
					'default' => ''
					),
				'log_perror' => array(
					'friendly_name' => __('Errors'),
					'default' => 'on'
					)
				),
			),
		'other_header' => array(
			'friendly_name' => __('Other Defaults'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'reindex_method' => array(
			'friendly_name' => __('Default Reindex Method for Data Queries'),
			'description' => __('The default Reindex Method to use for all Data Queries.'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => $reindex_types,
			),
		'default_image_format' => array(
			'friendly_name' => __('Default Graph Template Image Format'),
			'description' => __('The default Image Format to be used for all new Graph Templates.'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => $image_types,
			),
		'default_graph_height' => array(
			'friendly_name' => __('Default Graph Template Height'),
			'description' => __('The default Graph Width to be used for all new Graph Templates.'),
			'method' => 'textbox',
			'default' => '150',
			'size' => '5',
			'max_length' => '5'
			),
		'default_graph_width' => array(
			'friendly_name' => __('Default Graph Template Width'),
			'description' => __('The default Graph Width to be used for all new Graph Templates.'),
			'method' => 'textbox',
			'default' => '500',
			'size' => '5',
			'max_length' => '5'
			),
		'i18n_language_support' => array(
            'friendly_name' => __('Language Support'),
            'description' => __('Choose \'enabled\' to allow the localization of Cacti. The strict mode requires that the requested language will also be supported by all plugins being installed at your system. If that\'s not the fact everything will be displayed in English.'),
            'method' => 'drop_array',
            'default' => '1',
            'array' => $i18n_modes
            ),
        'i18n_default_language' => array(
            'friendly_name' => __('Default Language'),
            'description' => __('Default language for this system.'),
            'method' => 'drop_array',
            'default' => 'us',
            'array' => get_installed_locales()
            ),
        'i18n_auto_detection' => array(
            'friendly_name' => __('Auto Language Detection'),
            'description' => __('Allow to automatically determine the \'default\' language of the user and provide it at login time if that language is supported by Cacti. If disabled, the default language will be in force until the user elects another language.'),
            'method' => 'drop_array',
            'default' => '1',
            'array' => array( '0' => __('Disabled'), '1' => __('Enabled'))
            ),
		'other1_header' => array(
			'friendly_name' => __('Other Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'rrdtool_version' => array(
			'friendly_name' => __('RRDTool Version'),
			'description' => __('The version of RRDTool that you have installed.'),
			'method' => 'drop_array',
			'default' => 'rrd-1.4.x',
			'array' => $rrdtool_versions,
			),
		'graph_auth_method' => array(
			'friendly_name' => __('Graph Permission Method'),
			'description' => __('There are two methods for determining a Users Graph Permissions.  The first is \'Permissive\'.  Under the \'Permissive\' setting, a User only needs access to either the Graph, Device or Graph Template to gain access to the Graphs that apply to them.  Under \'Restrictive\', the User must have access to the Graph, the Device, and the Graph Template to gain access to the Graph.'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => array('1' => 'Permissive', '2' => 'Restrictive')
			),
		'grds_creation_method' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Graph/Data Source Creation Method'),
			'description' => __('If set to Simple, Graphs and Data Sources can only be created from New Graphs.  If Advanced, legacy Graph and Data Source creation is supported.'),
			'default' => '0',
			'array' => array('0' => 'Simple', '1' => 'Advanced')
			),
		'hide_form_description' => array(
			'friendly_name' => __('Show Form/Setting Help Inline'),
			'description' => __('When checked, Form and Setting Help will be show inline.  Otherwise it will be presented when hovering over the help button.'),
			'default' => 'on',
			'method' => 'checkbox',
			),
		'deletion_verification' => array(
			'friendly_name' => __('Deletion Verification'),
			'description' => __('Prompt user before item deletion.'),
			'default' => 'on',
			'method' => 'checkbox',
			),
		'hide_console' => array(
			'friendly_name' => __('Hide Cacti Console'),
			'description' => __('For use with Cacti\'s External Link Support.  Using this setting, you can replace the Cacti Console with your own page.'),
			'method' => 'drop_array',
			'default' => 0,
			'array' => array(0 => __('No'), 1 => __('Yes'))
		),
		'force_https' => array(
			'friendly_name' => __('Force Connections over HTTPS'),
			'description' => __('When checked, any attempts to access Cacti will be redirected to HTTPS to insure high security.'),
			'default' => '',
			'method' => 'checkbox',
			),
		'automation_header' => array(
			'friendly_name' => __('Automation'),
			'method' => 'spacer',
			),
		'automation_graphs_enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Enable Automatic Graph Creation'),
			'description' => __('When disabled, Cacti Automation will not actively create any Graph.' . 
				'This is useful when adjusting Host settings so as to avoid creating new Graphs each time you save an object. ' . 
				'Invoking Automation Rules manually will still be possible.'),
			'default' => '',
			),
		'automation_tree_enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Enable Automatic Tree Item Creation'),
			'description' => __('When disabled, Cacti Automation will not actively create any Tree Item.' . 
				'This is useful when adjusting Host or Graph settings so as to avoid creating new Tree Entries each time you save an object. ' . 
				'Invoking Rules manually will still be possible.'),
			'default' => '',
			),
		),
	'snmp' => array(
		'snmp_header' => array(
			'friendly_name' => __('SNMP Defaults'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'snmp_ver' => array(
			'friendly_name' => __('Version'),
			'description' => __('Default SNMP version for all new hosts.'),
			'method' => 'drop_array',
			'default' => '2',
			'array' => $snmp_versions,
			),
		'snmp_community' => array(
			'friendly_name' => __('Community'),
			'description' => __('Default SNMP read community for all new hosts.'),
			'method' => 'textbox',
			'default' => 'public',
			'max_length' => '100',
			),
		'snmp_username' => array(
			'friendly_name' => __('Username (v3)'),
			'description' => __('The SNMP v3 Username for polling hosts.'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '100',
			),
		'snmp_password' => array(
			'friendly_name' => __('Password (v3)'),
			'description' => __('The SNMP v3 Password for polling hosts.'),
			'method' => 'textbox_password',
			'default' => '',
			'max_length' => '100',
			),
		'snmp_auth_protocol' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Auth Protocol (v3)'),
			'description' => __('Choose the SNMPv3 Authorization Protocol.'),
			'default' => 'MD5',
			'array' => $snmp_auth_protocols,
			),
		'snmp_priv_passphrase' => array(
			'method' => 'textbox',
			'friendly_name' => __('Privacy Passphrase (v3)'),
			'description' => __('Choose the SNMPv3 Privacy Passphrase.'),
			'default' => '',
			'max_length' => '200'
			),
		'snmp_priv_protocol' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Privacy Protocol (v3)'),
			'description' => __('Choose the SNMPv3 Privacy Protocol.'),
			'default' => 'DES',
			'array' => $snmp_priv_protocols,
			),
		'snmp_timeout' => array(
			'friendly_name' => __('Timeout'),
			'description' => __('Default SNMP timeout in milli-seconds.'),
			'method' => 'textbox',
			'default' => '500',
			'max_length' => '10',
			'size' => '5'
			),
		'snmp_port' => array(
			'friendly_name' => __('Port Number'),
			'description' => __('Default UDP port to be used for SNMP Calls.  Typically 161.'),
			'method' => 'textbox',
			'default' => '161',
			'max_length' => '10',
			'size' => '5'
			),
		'snmp_retries' => array(
			'friendly_name' => __('Retries'),
			'description' => __('The number times the SNMP poller will attempt to reach the host before failing.'),
			'method' => 'textbox',
			'default' => '3',
			'max_length' => '10',
			'size' => '5'
			),
		'availability_header' => array(
			'friendly_name' => __('Availability/Reachability'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'availability_method' => array(
			'friendly_name' => __('Downed Device Detection'),
			'description' => __('The method Cacti will use to determine if a host is available for polling.  <br><i>NOTE: It is recommended that, at a minimum, SNMP always be selected.</i>'),
			'method' => 'drop_array',
			'default' => AVAIL_SNMP,
			'array' => $availability_options,
			),
		'ping_method' => array(
			'friendly_name' => __('Ping Type'),
			'description' => __('The type of ping packet to sent.  <br><i>NOTE: ICMP requires that the Cacti Service ID have root privilages in Unix.</i>'),
			'method' => 'drop_array',
			'default' => PING_UDP,
			'array' => $ping_methods,
			),
		'ping_port' => array(
			'friendly_name' => __('Ping Port'),
			'description' => __('When choosing either TCP or UDP Ping, which port should be checked for availability of the host prior to polling.'),
			'method' => 'textbox',
			'default' => '23',
			'max_length' => '10',
			'size' => '5'
			),
		'ping_timeout' => array(
			'friendly_name' => __('Ping Timeout Value'),
			'description' => __('The timeout value to use for host ICMP and UDP pinging.  This host SNMP timeout value applies for SNMP pings.'),
			'method' => 'textbox',
			'default' => '400',
			'max_length' => '10',
			'size' => '5'
			),
		'ping_retries' => array(
			'friendly_name' => __('Ping Retry Count'),
			'description' => __('The number of times Cacti will attempt to ping a host before failing.'),
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
			),
		'updown_header' => array(
			'friendly_name' => __('Up/Down Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'ping_failure_count' => array(
			'friendly_name' => __('Failure Count'),
			'description' => __('The number of polling intervals a host must be down before logging an error and reporting host as down.'),
			'method' => 'textbox',
			'default' => '2',
			'max_length' => '10',
			'size' => '5'
			),
		'ping_recovery_count' => array(
			'friendly_name' => __('Recovery Count'),
			'description' => __('The number of polling intervals a host must remain up before returning host to an up status and issuing a notice.'),
			'method' => 'textbox',
			'default' => '3',
			'max_length' => '10',
			'size' => '5'
			)
		),
	'export' => array(
		'export_hdr_general' => array(
			'friendly_name' => __('General'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'export_type' => array(
			'friendly_name' => __('Export Method'),
			'description' => __('Choose which export method to use.'),
			'method' => 'drop_array',
			'default' => 'disabled',
			'array' => array(
				'disabled' => __('Disabled (no exporting)'),
				'local' => __('Classic (local path)'),
				'ftp_php' => __('FTP (remote) - use php functions'),
				'ftp_ncftpput' => __('FTP (remote) - use ncftpput'),
				'sftp_php' => __('SFTP (remote) - use ssh php functions')
				),
			),
		'export_presentation' => array(
			'friendly_name' => __('Presentation Method'),
			'description' => __('Choose which presentation would you want for the html generated pages. If you choose classical presentation, the graphs will be in a only-one-html page. If you choose tree presentation, the graph tree architecture will be kept in the static html pages'),
			'method' => 'drop_array',
			'default' => 'disabled',
			'array' => array(
				'classical' => __('Classical Presentation'),
				'tree' => __('Tree Presentation'),
				),
			),
		'export_tree_options' => array(
			'friendly_name' => __('Tree Settings'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'export_tree_isolation' => array(
			'friendly_name' => __('Tree Isolation'),
			'description' => __('This setting determines if the entire tree is treated as a single hierarchy or as separate hierarchies.  If they are treated separately, graphs will be isolated from one another.'),
			'method' => 'drop_array',
			'default' => 'off',
			'array' => array(
				'off' => __('Single Tree Representation'),
				'on' => __('Multiple Tree Representation')
				),
			),
		'export_user_id' => array(
			'friendly_name' => __('Effective User Name'),
			'description' => __('The user name to utilize for establishing export permissions.  This user name will be used to determine which graphs/trees are exported.  This setting works in conjunction with the current on/off behavior available within the current templates.'),
			'method' => 'drop_sql',
			'sql' => 'SELECT id, username AS name FROM user_auth ORDER BY name',
			'default' => '1'
			),
		'export_tree_expand_hosts' => array(
			'friendly_name' => __('Expand Tree Devices'),
			'description' => __('This settings determines if the tree hosts will be expanded or not.  If set to expanded, each host will have a sub-folder containing either data templates or data query items.'),
			'method' => 'drop_array',
			'default' => 'off',
			'array' => array(
				'off' => __('Off'),
				'on' => __('On')
				),
			),
		'export_thumb_options' => array(
			'friendly_name' => __('Thumbnail Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'export_default_height' => array(
			'friendly_name' => __('Thumbnail Height'),
			'description' => __('The height of thumbnail graphs in pixels.'),
			'method' => 'textbox',
			'default' => '100',
			'max_length' => '10',
			'size' => '5'
			),
		'export_default_width' => array(
			'friendly_name' => __('Thumbnail Width'),
			'description' => __('The width of thumbnail graphs in pixels.'),
			'method' => 'textbox',
			'default' => '300',
			'max_length' => '10',
			'size' => '5'
			),
		'export_num_columns' => array(
			'friendly_name' => __('Thumbnail Columns'),
			'description' => __('The number of columns to use when displaying thumbnail graphs.'),
			'method' => 'textbox',
			'default' => '2',
			'max_length' => '5',
			'size' => '5'
			),
		'export_hdr_paths' => array(
			'friendly_name' => __('Paths'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'path_html_export' => array(
			'friendly_name' => __('Export Directory (both local and ftp)'),
			'description' => __('This is the directory, either on the local system or on the remote system, that will contain the exported data.'),
			'method' => 'dirpath',
			'max_length' => '255'
			),
		'export_temporary_directory' => array(
			'friendly_name' => __('Local Scratch Directory (ftp only)'),
			'description' => __('This is the a directory that cacti will temporarily store output prior to sending to the remote site via ftp.  The contents of this directory will be deleted after the ftp is completed.'),
			'method' => 'dirpath',
			'max_length' => '255'
			),
		'export_hdr_timing' => array(
			'friendly_name' => __('Timing'),
			'method' => 'spacer',
			),
		'export_timing' => array(
			'friendly_name' => __('Export timing'),
			'description' => __('Choose when to export graphs.'),
			'method' => 'drop_array',
			'default' => 'disabled',
			'array' => array(
						'disabled' => __('Disabled'),
						'classic' => __('Classic (export every x times)'),
						'export_hourly' => __('Hourly at specified minutes'),
						'export_daily' => __('Daily at specified time')
						),
			),
		'path_html_export_skip' => array(
			'friendly_name' => __('Export Every x Times'),
			'description' => __('If you dont want Cacti to export static images every 5 minutes, put another number here. For instance, 3 would equal every 15 minutes.'),
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '5'
			),
		'export_hourly' => array(
			'friendly_name' => __('Hourly at specified minutes'),
			'description' => __('If you want Cacti to export static images on an hourly basis, put the minutes of the hour when to do that. Cacti assumes that you run the data gathering script every 5 minutes, so it will round your value to the one closest to its runtime. For instance, 43 would equal 40 minutes past the hour.'),
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '5'
			),
		'export_daily' => array(
			'friendly_name' => __('Daily at specified time'),
			'description' => __('If you want Cacti to export static images on an daily basis, put here the time when to do that. Cacti assumes that you run the data gathering script every 5 minutes, so it will round your value to the one closest to its runtime. For instance, 21:23 would equal 20 minutes after 9 PM.'),
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '5'
			),
		'export_hdr_ftp' => array(
			'friendly_name' => __('FTP Options'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'export_ftp_sanitize' => array(
			'friendly_name' => __('Sanitize remote directory'),
			'description' => __('Check this if you want to delete any existing files in the FTP remote directory. This option is in use only when using the PHP built-in ftp functions.'),
			'method' => 'checkbox',
			'max_length' => '255'
			),
		'export_ftp_host' => array(
			'friendly_name' => __('FTP Host'),
			'description' => __('Denotes the host to upload your graphs by ftp.'),
			'placeholder' => 'hostname',
			'method' => 'textbox',
			'max_length' => '255'
			),
		'export_ftp_port' => array(
			'friendly_name' => __('FTP Port'),
			'description' => __('Communication port with the ftp server (leave empty for defaults). Default: 21.'),
			'placeholder' => '23',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '5'
			),
		'export_ftp_passive' => array(
			'friendly_name' => __('Use passive mode'),
			'description' => __('Check this if you want to connect in passive mode to the FTP server.'),
			'method' => 'checkbox',
			'max_length' => '255'
			),
		'export_ftp_user' => array(
			'friendly_name' => __('FTP User'),
			'description' => __('Account to logon on the remote server (leave empty for defaults). Default: Anonymous.'),
			'method' => 'textbox',
			'placeholder' => 'anonymous',
			'max_length' => '255'
			),
		'export_ftp_password' => array(
			'friendly_name' => __('FTP Password'),
			'description' => __('Password for the remote ftp account (leave empty for blank).'),
			'method' => 'textbox_password',
			'max_length' => '255'
			)
		),
	'visual' => array(
		'themes_header' => array(
			'friendly_name' => __('Theme Settings'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'selected_theme' => array(
			'friendly_name' => __('Theme'),
			'description' => __('Please select one of the available Themes to skin your Cacti with.'),
			'method' => 'drop_array',
			'default' => 'classic',
			'array' => $themes
			),
		'table_header' => array(
			'friendly_name' => __('Table Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'num_rows_table' => array(
			'friendly_name' => __('Rows Per Page'),
			'description' => __('The default number of rows to display on for a table.'),
			'method' => 'drop_array',
			'default' => '30',
			'array' => $item_rows
			),
		'object_creation_header' => array(
			'friendly_name' => __('Graph/Data Source/Data Query Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'max_title_length' => array(
			'friendly_name' => __('Maximum Title Length'),
			'description' => __('The maximum allowable Graph or Data Source titles.'),
			'method' => 'textbox',
			'default' => '110',
			'max_length' => '10',
			'size' => '5'
			),
		'max_data_query_field_length' => array(
			'friendly_name' => __('Data Source Field Length'),
			'description' => __('The maximum Data Query field length.'),
			'method' => 'textbox',
			'default' => '40',
			'max_length' => '10',
			'size' => '5'
			),
		'graphs_new_header' => array(
			'friendly_name' => __('Graph Creation'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'default_graphs_new_dropdown' => array(
			'friendly_name' => __('Default Graph Type'),
			'description' => __('When creating graphs, what Graph Type would you like pre-selected?'),
			'method' => 'drop_array',
			'default' => '-2',
			'array' => array( '-2' => __('All Types'), '-1' => __('By Template/Data Query') ),
			),
		'logmgmt_header' => array(
			'friendly_name' => __('Log Management'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'num_rows_log' => array(
			'friendly_name' => __('Default Log File Tail Lines'),
			'description' => __('Default number of lines of the Cacti log file to tail.'),
			'method' => 'drop_array',
			'default' => 500,
			'array' => $log_tail_lines,
			),
		'log_refresh_interval' => array(
			'friendly_name' => __('Log File Tail Refresh'),
			'description' => __('How often do you want the Cacti log display to update.'),
			'method' => 'drop_array',
			'default' => 60,
			'array' => $page_refresh_interval,
			),
		'wathermark_header' => array(
			'friendly_name' => __('RRDtool Graph Watermark'),
			'collapsible' => 'true',
			'method' => 'spacer'
			),
		'graph_wathermark' => array(
			'friendly_name' => __('Watermark Text'),
			'description' => __('Test to place at the bottom center of every Graph.'),
			'method' => 'textbox',
			'default' => COPYRIGHT_YEARS,
			'max_length' => '80',
			'size' => '60'
			),
		'clog_header' => array(
			'friendly_name' => __('Log Viewer Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'clog_exclude' => array(
			'friendly_name' => __('Exclusion Regex'),
			'description' => __('Any strings that match this regex will be excluded from the user display.
				<strong>For example, if you want to exclude all log lines that include the words \'Admin\' or \'Login\'
				you would type \'(Admin || Login)\'</strong>'),
			'method' => 'textarea',
			'textarea_rows' => '5',
			'textarea_cols' => '45',
			'max_length' => 512
			),
		'realtime_header' => array(
			'friendly_name' => __('Realtime Graphs'),
			'method' => 'spacer',
			'collapsible' => 'true',
			),
		'realtime_enabled' => array(
			'friendly_name' => __('Enable Realtime Graphing'),
			'description' => __('When an option is checked, users will be able to put Cacti into Realtime mode.'),
			'method' => 'checkbox',
			'default' => 'on'
			),
		'realtime_gwindow' => array(
			'friendly_name' => __('Graph Timespan'),
			'description' => __('This timespan you wish to see on the default graph.'),
			'method' => 'drop_array',
			'default' => 60,
			'array' => $realtime_window,
			),
		'realtime_interval' => array(
			'friendly_name' => __('Refresh Interval'),
			'description' => __('This is the time between graph updates.'),
			'method' => 'drop_array',
			'default' => 10,
			'array' => $realtime_refresh,
			),
		'realtime_cache_path' => array(
			'friendly_name' => __('Cache Directory'),
			'description' => __('This is the location, on the web server where the RRDfiles and PNG files will be cached.
			This cache will be managed by the poller.
			Make sure you have the correct read and write permissions on this folder'),
			'method' => 'dirpath',
			'default' => $config['base_path'] . '/cache/realtime/',
			'max_length' => 255,
			'size' => 40,
			),
		'fonts_header' => array(
			'friendly_name' => __('RRDtool Graph Font Control'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'font_method' => array(
			'friendly_name' => __('Font Selection Method'),
			'description' => __('How do you wish fonts to be handled by default?'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => array(0 => __('System'), 1 => __('Theme'))
			),
		'path_rrdtool_default_font' => array(
			'friendly_name' => __('Default Font'),
			'description' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' 	? __('When not using Theme based fonts, the path to the default True Type Font File.')
																					: __('When not using Theme based font control, the Pangon font-config font name to use for all Graphs. Optionally, you may leave blank and control font settings on a per object basis.') ),
			'method' => 'font',
			'placeholder' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' 	? __('Enter valid True Type Font File Path')
																					: __('Enter Valid Font Config Value') ),
			'max_length' => '255'
			),
		'title_size' => array(
			'friendly_name' => __('Title Font Size'),
			'description' => __('The size of the font used for Graph Titles'),
			'method' => 'textbox',
			'default' => '10',
			'max_length' => '10',
			'size' => '5'
			),
		'title_font' => array(
			'friendly_name' => __('Title Font Setting'),
			'description' => __('The font to use for Graph Titles.  Enter either a valid True Type Font file or valid Pango font-config value.'),
			'method' => 'font',
			'placeholder' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' 	? __('Enter True Type Font Path')
																					: __('Enter Valid Font Config Value') ),
			'max_length' => '100'
			),
		'legend_size' => array(
			'friendly_name' => __('Legend Font Size'),
			'description' => __('The size of the font used for Graph Legend items'),
			'method' => 'textbox',
			'default' => '8',
			'max_length' => '10',
			'size' => '5'
			),
		'legend_font' => array(
			'friendly_name' => __('Legend Font Setting'),
			'description' => __('The font to use for Graph Legends.  Enter either a valid True Type Font file or valid Pango font-config value.'),
			'method' => 'font',
			'placeholder' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' 	? __('Enter True Type Font Path') 
																					: __('Enter Valid Font Config Value') ),
			'max_length' => '100'
			),
		'axis_size' => array(
			'friendly_name' => __('Axis Font Size'),
			'description' => __('The size of the font used for Graph Axis'),
			'method' => 'textbox',
			'default' => '7',
			'max_length' => '10',
			'size' => '5'
			),
		'axis_font' => array(
			'friendly_name' => __('Axis Font Setting'),
			'description' => __('The font to use for Graph Axis items.  Enter either a valid True Type Font file or valid Pango font-config value.'),
			'method' => 'font',
			'placeholder' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' 	? __('Enter True Type Font Path')
																					: __('Enter Valid Font Config Value') ),
			'max_length' => '100'
			),
		'unit_size' => array(
			'friendly_name' => __('Unit Font Size'),
			'description' => __('The size of the font used for Graph Units'),
			'method' => 'textbox',
			'default' => '7',
			'max_length' => '10',
			'size' => '5'
			),
		'unit_font' => array(
			'friendly_name' => __('Unit Font Setting'),
			'description' => __('The font to use for Graph Unit items.  Enter either a valid True Type Font file or valid Pango font-config value.'),
			'method' => 'font',
			'placeholder' => (read_config_option('rrdtool_version') == 'rrd-1.2.x' 	? __('Enter True Type Font Path')
																					: __('Enter Valid Font Config Value') ),
			'max_length' => '100'
			)
		),
	'poller' => array(
		'poller_header' => array(
			'friendly_name' => __('General'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'poller_enabled' => array(
			'friendly_name' => __('Data Collection Enabled'),
			'description' => __('If you wish to stop the polling process completely, uncheck this box.'),
			'method' => 'checkbox',
			'default' => 'on',
			'tab' => 'poller'
			),
		'poller_type' => array(
			'friendly_name' => __('Poller Type'),
			'description' => __('The poller type to use.  This setting will take effect at next polling interval.'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => $poller_options,
			),
		'poller_interval' => array(
			'friendly_name' => __('Poller Interval'),
			'description' => __('The polling interval in use.  This setting will effect how often rrd files are checked and updated.
			<strong><u>NOTE: If you change this value, you must re-populate the poller cache.  Failure to do so, may result in lost data.</u></strong>'),
			'method' => 'drop_array',
			'default' => 300,
			'array' => $poller_intervals,
			),
		'cron_interval' => array(
			'friendly_name' => __('Cron Interval'),
			'description' => __('The cron interval in use.  You need to set this setting to the interval that your cron or scheduled task is currently running.'),
			'method' => 'drop_array',
			'default' => 300,
			'array' => $cron_intervals,
			),
		'concurrent_processes' => array(
			'friendly_name' => __('Maximum Concurrent Poller Processes'),
			'description' => __('The number of concurrent processes to execute.  Using a higher number when using cmd.php will improve performance.  Performance improvements in spine are best resolved with the threads parameter'),
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
			),
		'process_leveling' => array(
			'friendly_name' => __('Balance Process Load'),
			'description' => __('If you choose this option, Cacti will attempt to balance the load of each poller process by equally distributing poller items per process.'),
			'method' => 'checkbox',
			'default' => 'on'
			),
		'oid_increasing_check_disable' => array(
			'friendly_name' => __('Disable increasing OID Check'),
			'description' => __('Controls disabling check for increasing OID while walking OID tree.'),
			'method' => 'checkbox',
			'default' => ''
			),
		'spine_header' => array(
			'friendly_name' => __('Spine Specific Execution Parameters'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'max_threads' => array(
			'friendly_name' => __('Maximum Threads per Process'),
			'description' => __('The maximum threads allowed per process.  Using a higher number when using Spine will improve performance.'),
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
			),
		'php_servers' => array(
			'friendly_name' => __('Number of PHP Script Servers'),
			'description' => __('The number of concurrent script server processes to run per Spine process.  Settings between 1 and 10 are accepted.  This parameter will help if you are running several threads and script server scripts.'),
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
			),
		'script_timeout' => array(
			'friendly_name' => __('Script and Script Server Timeout Value'),
			'description' => __('The maximum time that Cacti will wait on a script to complete.  This timeout value is in seconds'),
			'method' => 'textbox',
			'default' => '25',
			'max_length' => '10',
			'size' => '5'
			),
		'max_get_size' => array(
			'friendly_name' => __('The Maximum SNMP OIDs Per SNMP Get Request'),
			'description' => __('The maximum number of snmp get OIDs to issue per snmpbulkwalk request.  Increasing this value speeds poller performance over slow links.  The maximum value is 100 OIDs.  Decreasing this value to 0 or 1 will disable snmpbulkwalk'),
			'method' => 'textbox',
			'default' => '10',
			'max_length' => '10',
			'size' => '5'
			),
		),
	'authentication' => array(
		'auth_header' => array(
			'friendly_name' => __('General'),
			'method' => 'spacer',
			),
		'auth_method' => array(
			'friendly_name' => __('Authentication Method'),
			'description' => __('<blockquote><i>None</i> - No authentication will be used, all users will have full access.<br><br><i>Builtin Authentication</i> - Cacti handles user authentication, which allows you to create users and give them rights to different areas within Cacti.<br><br><i>Web Basic Authentication</i> - Authentication is handled by the web server. Users can be added or created automatically on first login if the Template User is defined, otherwise the defined guest permissions will be used.<br><br><i>LDAP Authentication</i> - Allows for authentication against a LDAP server. Users will be created automatically on first login if the Template User is defined, otherwise the defined guest permissions will be used.  If PHPs LDAP module is not enabled, LDAP Authentication will not appear as a selectable option.<br><br><i>Multiple LDAP/AD Domain Authentication</i> - Allows administrators to support multiple desparate groups from different LDAP/AD directories to access Cacti resources.  Just as LDAP Authentication, the PHP LDAP module is required to utilize this method.</blockquote>'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => $auth_methods
			),
		'auth_cache_enabled' => array(
			'friendly_name' => __('Support Authentication Cookies'),
			'description' => __('If a user authenticates and selects \'Keep me signed in\', an authentication cookie will be created on the users computer allowing that user to stay logged in.  The authentication cookie expires after 90 days of non-use.'),
			'default' => '',
			'method' => 'checkbox'
			),
		'special_users_header' => array(
			'friendly_name' => __('Special Users'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'guest_user' => array(
			'friendly_name' => __('Guest User'),
			'description' => __('The name of the guest user for viewing graphs; is \'No User\' by default.'),
			'method' => 'drop_sql',
			'none_value' => __('No User'),
			'sql' => 'SELECT username AS id, username AS name FROM user_auth WHERE realm = 0 ORDER BY username',
			'default' => '0'
			),
		'user_template' => array(
			'friendly_name' => __('User Template'),
			'description' => __('The name of the user that cacti will use as a template for new Web Basic and LDAP users; is \'guest\' by default.'),
			'method' => 'drop_sql',
			'none_value' => __('No User'),
			'sql' => 'SELECT username AS id, username AS name FROM user_auth WHERE realm = 0 ORDER BY username',
			'default' => '0'
			),
		'secpass_header' => array(
			'friendly_name' => __('Local Account Complexity Requirements'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'secpass_minlen' => array(
			'friendly_name' => __('Minimum Length'),
			'description' => __('This is minimal length of allowed passwords.'),
			'method' => 'textbox',
			'default' => '8',
			'max_length' => 2,
			'size' => 4
			),
		'secpass_reqmixcase' => array(
			'friendly_name' => __('Require Mix Case'),
			'description' => __('This will require new passwords to contains both lower and upper case characters.'),
			'method' => 'checkbox',
			'default' => 'on',
			),
		'secpass_reqnum' => array(
			'friendly_name' => __('Require Number'),
			'description' => __('This will require new passwords to contain at least 1 numberical character.'),
			'method' => 'checkbox',
			'default' => 'on',
			),
		'secpass_reqspec' => array(
			'friendly_name' => __('Require Special Character'),
			'description' => __('This will require new passwords to contain at least 1 special character.'),
			'method' => 'checkbox',
			'default' => 'on',
			),
		'secpass_forceold' => array(
			'friendly_name' => __('Force Complexity Upon Old Passwords'),
			'description' => __('This will require all old passwords to also meet the new complexity requirements upon login.  If not met, it will force a password change.'),
			'method' => 'checkbox',
			'default' => '',
			),
		'secpass_expireaccount' => array(
			'friendly_name' => __('Expire Inactive Accounts'),
			'description' => __('This is maximum number of days before inactive accounts are disabled.  The Admin account is excluded from this policy.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'  => __('Disabled'), 
				'30'  => __('%d Days', 30), 
				'60'  => __('%d Days', 60), 
				'90'  => __('%d Days', 90), 
				'120'  => __('%d Days', 120), 
				'365'  => __('1 Year'),
				'730'  => __('%d Years', 2) )
			),
		'secpass_expirepass' => array(
			'friendly_name' => __('Expire Password'),
			'description' => __('This is maximum number of days before a password is set to expire.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
			'0'  => __('Disabled'), 
				'30'  => __('%d Days', 30), 
				'60'  => __('%d Days', 60), 
				'90'  => __('%d Days', 90), 
				'120'  => __('%d Days', 120) )
			),
		'secpass_history' => array(
			'friendly_name' => __('Password History'),
			'description' => __('Remember this number of old passwords and disallow re-using them.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'  => __('Disabled'), 
				'1'  => __('1 Change'), 
				'2'  => __('%d Changes', 2),
				'3'  => __('%d Changes', 3),
				'4'  => __('%d Changes', 4),
				'5'  => __('%d Changes', 5),
				'6'  => __('%d Changes', 6),
				'7'  => __('%d Changes', 7),
				'8'  => __('%d Changes', 8),
				'9'  => __('%d Changes', 9),
				'10' => __('%d Changes', 10),
				'11' => __('%d Changes', 11),
				'12' => __('%d Changes', 12) ) 
			),
		'secpass_lock_header' => array(
			'friendly_name' => __('Account Locking'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'secpass_lockfailed' => array(
			'friendly_name' => __('Lock Accounts'),
			'description' => __('Lock an account after this many failed attempts in 1 hour.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'  => __('Disabled'), 
				'1'  => __('1 Attempt'), 
				'2'  => __('%d Attempts', 2), 
				'3'  => __('%d Attempts', 3),
				'4'  => __('%d Attempts', 4), 
				'5'  => __('%d Attempts', 5),
				'6'  => __('%d Attempts', 6) )
			),
		'secpass_unlocktime' => array(
			'friendly_name' => __('Auto Unlock'),
			'description' => __('An account will automatically be unlocked after this many minutes.  Even if the correct password is entered, the account will not unlock until this time limit has been met.  Max of 1440 minutes (1 Day)'),
			'method' => 'drop_array',
			'default' => '60',
			'array' => array(
				'0'  => __('Disabled'), 
				'1'  => __('1 Minute'), 
				'2'  => __('%d Minutes', 2), 
				'5'  => __('%d Minutes', 5),
				'10'  => __('%d Minutes', 10),
				'20'  => __('%d Minutes', 20),
				'30'  => __('%d Minutes', 30),
				'60'  => __('1 Hour'),
				'120'  => __('%d Hours', 2),
				'240'  => __('%d Hours', 4),
				'480'  => __('%d Hours', 8),
				'960'  => __('%d Hours', 16),
				'1440'  => __('1 Day') )
			),
		'ldap_general_header' => array(
			'friendly_name' => __('LDAP General Settings'),
			'method' => 'spacer'
			),
		'ldap_server' => array(
			'friendly_name' => __('Server'),
			'description' => __('The dns hostname or ip address of the server.'),
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_port' => array(
			'friendly_name' => __('Port Standard'),
			'description' => __('TCP/UDP port for Non SSL communications.'),
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '389',
			'size' => '5'
			),
		'ldap_port_ssl' => array(
			'friendly_name' => __('Port SSL'),
			'description' => __('TCP/UDP port for SSL communications.'),
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '636',
			'size' => '5'
			),
		'ldap_version' => array(
			'friendly_name' => __('Protocol Version'),
			'description' => __('Protocol Version that the server supports.'),
			'method' => 'drop_array',
			'default' => '3',
			'array' => $ldap_versions
			),
		'ldap_encryption' => array(
			'friendly_name' => __('Encryption'),
			'description' => __('Encryption that the server supports. TLS is only supported by Protocol Version 3.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => $ldap_encryption
			),
		'ldap_referrals' => array(
			'friendly_name' => __('Referrals'),
			'description' => __('Enable or Disable LDAP referrals.  If disabled, it may increase the speed of searches.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array( '0' => __('Disabled'), '1' => __('Enable'))
			),
		'ldap_mode' => array(
			'friendly_name' => __('Mode'),
		'description' => __('Mode which cacti will attempt to authenicate against the LDAP server.<blockquote><i>No Searching</i> - No Distinguished Name (DN) searching occurs, just attempt to bind with the provided Distinguished Name (DN) format.<br><br><i>Anonymous Searching</i> - Attempts to search for username against LDAP directory via anonymous binding to locate the users Distinguished Name (DN).<br><br><i>Specific Searching</i> - Attempts search for username against LDAP directory via Specific Distinguished Name (DN) and Specific Password for binding to locate the users Distinguished Name (DN).'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => $ldap_modes
			),
		'ldap_dn' => array(
			'friendly_name' => __('Distinguished Name (DN)'),
			'description' => __('Distinguished Name syntax, such as for windows: <i>"&lt;username&gt;@win2kdomain.local"</i> or for OpenLDAP: <i>"uid=&lt;username&gt;,ou=people,dc=domain,dc=local"</i>.   "&lt;username&gt" is replaced with the username that was supplied at the login prompt.  This is only used when in "No Searching" mode.'),
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_group_require' => array(
			'friendly_name' => __('Require Group Membership'),
			'description' => __('Require user to be member of group to authenicate. Group settings must be set for this to work, enabling without proper group settings will cause authenication failure.'),
			'default' => '',
			'method' => 'checkbox'
			),
		'ldap_group_header' => array(
			'friendly_name' => __('LDAP Group Settings'),
			'method' => 'spacer'
			),
		'ldap_group_dn' => array(
			'friendly_name' => __('Group Distingished Name (DN)'),
			'description' => __('Distingished Name of the group that user must have membership.'),
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_group_attrib' => array(
			'friendly_name' => __('Group Member Attribute'),
			'description' => __('Name of the attribute that contains the usernames of the members.'),
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_group_member_type' => array(
			'friendly_name' => __('Group Member Type'),
			'description' => __('Defines if users use full Distingished Name or just Username in the defined Group Member Attribute.'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => array( 1 => __('Distingished Name'), 2 => __('Username') )
			),
		'ldap_search_base_header' => array(
			'friendly_name' => __('LDAP Specific Search Settings'),
			'method' => 'spacer'
			),
		'ldap_search_base' => array(
			'friendly_name' => __('Search Base'),
			'description' => __('Search base for searching the LDAP directory, such as <i>\'dc=win2kdomain,dc=local\'</i> or <i>\'ou=people,dc=domain,dc=local\'</i>.'),
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_search_filter' => array(
			'friendly_name' => __('Search Filter'),
			'description' => __('Search filter to use to locate the user in the LDAP directory, such as for windows: <i>\'(&amp;(objectclass=user)(objectcategory=user)(userPrincipalName=&lt;username&gt;*))\'</i> or for OpenLDAP: <i>\'(&(objectClass=account)(uid=&lt;username&gt))\'</i>.  \'&lt;username&gt\' is replaced with the username that was supplied at the login prompt. '),
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_specific_dn' => array(
			'friendly_name' => __('Search Distingished Name (DN)'),
			'description' => __('Distinguished Name for Specific Searching binding to the LDAP directory.'),
			'method' => 'textbox',
			'max_length' => '255'
			),
		'ldap_specific_password' => array(
			'friendly_name' => __('Search Password'),
			'description' => __('Password for Specific Searching binding to the LDAP directory.'),
			'method' => 'textbox_password',
			'max_length' => '255'
			),
		),
	'mail' => array(
		'settings_web_url' => array(
			'friendly_name' => __('URL Linking'),
			'method' => 'spacer',
			),
		'base_url' => array(
			'friendly_name' => __('Server Base URL'),
			'description' => __('This is a the server location that will be used for links to the Cacti site.'),
			'method' => 'textbox',
			'max_length' => 255,
			'size' => '60',
			'default' => 'http://' . gethostname()
			),
		'settings_email_header' => array(
			'friendly_name' => __('Emailing Options<div id="emailtest" class="emailtest textSubHeaderDark">Send a Test Email</div>'),
			'method' => 'spacer',
			),
		'settings_test_email' => array(
			'friendly_name' => __('Test Email'),
			'description' => __('This is a email account used for sending a test message to ensure everything is working properly.'),
			'method' => 'textbox',
			'max_length' => 255,
			),
		'settings_how' => array(
			'friendly_name' => __('Mail Services'),
			'description' => __('Which mail service to use in order to send mail'),
			'method' => 'drop_array',
			'default' => __('PHP Mail() Function'),
			'array' => array( __('PHP Mail() Function'), __('Sendmail'), __('SMTP') ),
			),
		'settings_from_email' => array(
			'friendly_name' => __('From Email Address'),
			'description' => __('This is the email address that the email will appear from.'),
			'method' => 'textbox',
			'max_length' => 255,
			),
		'settings_from_name' => array(
			'friendly_name' => __('From Name'),
			'description' => __('This is the actual name that the email will appear from.'),
			'method' => 'textbox',
			'max_length' => 255,
			),
		'settings_wordwrap' => array(
			'friendly_name' => __('Word Wrap'),
			'description' => __('This is how many characters will be allowed before a line in the email is automatically word wrapped. (0 = Disabled)'),
			'method' => 'textbox',
			'default' => 120,
			'max_length' => 4,
			'size' => 5
			),
		'settings_sendmail_header' => array(
			'friendly_name' => __('Sendmail Options'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'settings_sendmail_path' => array(
			'friendly_name' => __('Sendmail Path'),
			'description' => __('This is the path to sendmail on your server. (Only used if Sendmail is selected as the Mail Service)'),
			'method' => 'filepath',
			'max_length' => 255,
			'default' => '/usr/sbin/sendmail',
			),
		'settings_smtp_header' => array(
			'friendly_name' => __('SMTP Options'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'settings_smtp_host' => array(
			'friendly_name' => __('SMTP Hostname'),
			'description' => __('This is the hostname/IP of the SMTP Server you will send the email to. For failover, separate your hosts using a semi-colon.'),
			'method' => 'textbox',
			'default' => 'localhost',
			'max_length' => 255,
			),
		'settings_smtp_port' => array(
			'friendly_name' => __('SMTP Port'),
			'description' => __('The port on the SMTP Server to use.'),
			'method' => 'textbox',
			'max_length' => 255,
			'default' => 25,
			'size' => 5
			),
		'settings_smtp_username' => array(
			'friendly_name' => __('SMTP Username'),
			'description' => __('The username to authenticate with when sending via SMTP. (Leave blank if you do not require authentication.)'),
			'method' => 'textbox',
			'max_length' => 255,
			),
		'settings_smtp_password' => array(
			'friendly_name' => __('SMTP Password'),
			'description' => __('The password to authenticate with when sending via SMTP. (Leave blank if you do not require authentication.)'),
			'method' => 'textbox_password',
			'max_length' => 255,
			),
		'settings_smtp_secure' => array(
			'friendly_name' => __('SMTP Security'),
			'description' => __('The encryption method to use for the email.'),
			'method' => 'drop_array',
			'array' => array( 'none' => __('None'), 'ssl' => __('SSL'), 'tls' => __('TLS') ),
			'default' => 'none'
			),
		'settings_smtp_timeout' => array(
			'friendly_name' => __('SMTP Timeout'),
			'description' => __('Please enter the SMTP timeout in seconds.'),
			'method' => 'textbox',
			'default' => '5',
			'max_length' => '10',
			'size' => '5'
			),
		'reports_header' => array(
			'friendly_name' => __('Reporting Presets'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'reports_default_image_format' => array(
			'friendly_name' => __('Default Graph Image Format'),
			'description' => __('When creating a new report, what image type should be used for the inline graphs.'),
			'method' => 'drop_array',
			'default' => REPORTS_TYPE_INLINE_PNG,
			'array' => $attach_types
			),
		'reports_max_attach' => array(
			'friendly_name' => __('Maximum E-Mail Size'),
			'description' => __('The maximum size of the E-Mail message including all attachements.'),
			'method' => 'drop_array',
			'default' => REPORTS_DEFAULT_MAX_SIZE,
			'array' => $attachment_sizes
			),
		'reports_log_verbosity' => array(
			'friendly_name' => __('Poller Logging Level for Cacti Reporting'),
			'description' => __('What level of detail do you want sent to the log file. WARNING: Leaving in any other status than NONE or LOW can exaust your disk space rapidly.'),
			'method' => 'drop_array',
			'default' => POLLER_VERBOSITY_LOW,
			'array' => $logfile_verbosity,
			),
		'reports_allow_ln' => array(
			'friendly_name' => __('Enable Lotus Notus (R) tweak'),
			'description' => __('Enable code tweak for specific handling of Lotus Notes Mail Clients.'),
			'method' => 'checkbox',
			'default' => '',
			),
		'settings_dns_header' => array(
			'friendly_name' => __('DNS Options'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'settings_dns_primary' => array(
			'friendly_name' => __('Primary DNS IP Address'),
			'description' => __('Enter the primary DNS IP Address to utilize for reverse lookups.'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '30'
			),
		'settings_dns_secondary' => array(
			'friendly_name' => __('Secondary DNS IP Address'),
			'description' => __('Enter the secondary DNS IP Address to utilize for reverse lookups.'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '30'
			),
		'settings_dns_timeout' => array(
			'friendly_name' => __('DNS Timeout'),
			'description' => __('Please enter the DNS timeout in milliseconds.  Cacti uses a PHP based DNS resolver.'),
			'method' => 'textbox',
			'default' => '500',
			'max_length' => '10',
			'size' => '5'
			),
		),
	'dsstats' => array(
		'dsstats_hq_header' => array(
			'friendly_name' => __('Data Sources Statistics'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'dsstats_enable' => array(
			'friendly_name' => __('Enable Data Source Statistics Collection'),
			'description' => __('Should Data Source Statistics be collected for this Cacti system?'),
			'method' => 'checkbox',
			'default' => ''
			),
		'dsstats_daily_interval' => array(
			'friendly_name' => __('Daily Update Frequency'),
			'description' => __('How frequent should Daily Stats be updated?'),
			'default' => '60',
			'method' => 'drop_array',
			'array' => $dsstats_refresh_interval
			),
		'dsstats_hourly_duration' => array(
			'friendly_name' => __('Hourly Average Window'),
			'description' => __('The number of consecutive hours that represent the hourly
			average.  Keep in mind that a setting too high can result in very large memory tables'),
			'default' => '60',
			'method' => 'drop_array',
			'array' => $dsstats_hourly_avg
			),
		'dsstats_major_update_time' => array(
			'friendly_name' => __('Maintenance Time'),
			'description' => __('What time of day should Weekly, Monthly, and Yearly Data be updated?  Format is HH:MM [am/pm]'),
			'method' => 'textbox',
			'default' => '12:00am',
			'max_length' => '20',
			'size' => '10'
			),
		'dsstats_poller_mem_limit' => array(
			'friendly_name' => __('Memory Limit for dsstats and Poller'),
			'description' => __('The maximum amount of memory for the Cacti Poller and dsstats Poller'),
			'method' => 'drop_array',
			'default' => '1024',
			'array' => $dsstats_max_memory
			),
		'dsstats_debug_header' => array(
			'friendly_name' => __('Debugging'),
			'method' => 'spacer',
			'collapsible' => 'true',
			),
		'dsstats_rrdtool_pipe' => array(
			'friendly_name' => __('Enable Single RRDtool Pipe'),
			'description' => __('Using a single pipe will speed the RRDtool process by 10x.  However, RRDtool crashes
			problems can occur.  Disable this setting if you need to find a bad RRDfile.'),
			'method' => 'checkbox',
			'default' => 'on'
			),
		'dsstats_partial_retrieve' => array(
			'friendly_name' => __('Enable Partial Reference Data Retrieve'),
			'description' => __('If using a large system, it may be beneficial for you to only gather data as needed
			during Cacti poller passes.  If you check this box, you DSStats will gather data this way.'),
			'method' => 'checkbox',
			'default' => ''
			)
		),
	'boost' => array(
		'boost_hq_header' => array(
			'friendly_name' => __('On Demand RRD Update Settings'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'boost_rrd_update_enable' => array(
			'friendly_name' => __('Enable On Demand RRD Updating'),
			'description' => __('Should Boost enable on demand RRD updating in Cacti?  If you disable, this change will not take affect until after the next polling cycle.'),
			'method' => 'checkbox',
			'default' => ''
			),
		'boost_rrd_update_system_enable' => array(
			'friendly_name' => __('System Level RRD Updater'),
			'description' => __('Before RRD On Demand Update Can be Cleared, a poller run must alway pass'),
			'method' => 'hidden',
			'default' => ''
			),
		'boost_rrd_update_interval' => array(
			'friendly_name' => __('How Often Should Boost Update All RRDs'),
			'description' => __('When you enable boost, your RRD files are only updated when they are requested by a user, or when this time period elapses.'),
			'default' => '60',
			'method' => 'drop_array',
			'default' => '60',
			'array' => $boost_refresh_interval
			),
		'boost_rrd_update_max_records' => array(
			'friendly_name' => __('Maximum Records'),
			'description' => __('If the boost output table exceeds this size, in records, an update will take place.'),
			'method' => 'textbox',
			'default' => '1000000',
			'max_length' => '20',
			'size' => '10'
			),
		'boost_rrd_update_max_records_per_select' => array(
			'friendly_name' => __('Maximum Data Source Items Per Pass'),
			'description' => __('To optimize performance, the boost RRD updater needs to know how many Data Source Items
			should be retrieved in one pass.  Please be careful not to set too high as graphing performance during
			major updates can be compromised.  If you encounter graphing or polling slowness during updates, lower this
			number.  The default value is 50000.'),
			'method' => 'drop_array',
			'default' => '50000',
			'array' => $boost_max_rows_per_select
			),
		'boost_rrd_update_string_length' => array(
			'friendly_name' => __('Maximum Argument Length'),
			'description' => __('When boost sends update commands to RRDtool, it must not exceed the operating systems
			Maximum Argument Length.  This varies by operating system and kernel level.  For example:
			Windows 2000 <= 2048, FreeBSD <= 65535, Linux 2.6.22-- <= 131072, Linux 2.6.23++ unlimited'),
			'method' => 'textbox',
			'default' => '2000',
			'max_length' => '20',
			'size' => '10'
			),
		'boost_poller_mem_limit' => array(
			'friendly_name' => __('Memory Limit for Boost and Poller'),
			'description' => __('The maximum amount of memory for the Cacti Poller and Boosts Poller'),
			'method' => 'drop_array',
			'default' => '1024',
			'array' => $boost_max_memory
			),
		'boost_rrd_update_max_runtime' => array(
			'friendly_name' => __('Maximum RRD Update Script Run Time'),
			'description' => __('The maximum boot poller run time allowed prior to boost issuing warning
			messages relative to possible hardware/software issues preventing proper updates.'),
			'method' => 'drop_array',
			'default' => '1200',
			'array' => $boost_max_runtime
			),
		'boost_redirect' => array(
			'friendly_name' => __('Enable direct population of poller_output_boost table by spine'),
			'description' => __('Enables direct insert of records into poller output boost with results in a 25% time reduction in each poll cycle.'),
			'method' => 'checkbox',
			'default' => ''
			),
		'boost_png_header' => array(
			'friendly_name' => __('Image Caching'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'boost_png_cache_enable' => array(
			'friendly_name' => __('Enable Image Caching'),
			'description' => __('Should image caching be enabled?'),
			'method' => 'checkbox',
			'default' => ''
			),
		'boost_png_cache_directory' => array(
			'friendly_name' => __('Location for Image Files'),
			'description' => __('Specify the location where Boost should place your image files.  These files will be automatically purged by the poller when they expire.'),
			'method' => 'dirpath',
			'max_length' => '255',
			'default' => $config['base_path'] . '/cache/boost/'
			),
		'boost_process_header' => array(
			'friendly_name' => __('Process Interlocking'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'path_boost_log' => array(
			'friendly_name' => __('Boost Debug Log'),
			'description' => __('If this field is non-blank, Boost will log RRDupdate output from the boost	poller process.'),
			'method' => 'filepath',
			'default' => '',
			'max_length' => '255'
			)
		),
	'storage' => array(
		'general_header' => array(
			'friendly_name' => __('General'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'storage_location' => array(
			'friendly_name' => __('Location'),
			'description' => __('Choose if RRDs will be stored locally or being handled by an external RRDtool proxy server.
			<strong><u>NOTE: If you change this value, you must re-populate the poller cache.  Failure to do so, may result in lost data.</u></strong>'),
			'method' => 'drop_array',
			'default' => 0,
			'array' => array ( __('Local'), __('RRDtool Proxy Server') ),
			),
		'extended_paths' => array(
			'friendly_name' => __('Structured RRD Path (/host_id/local_data_id.rrd)'),
			'description' => __('Use a seperate subfolder for each hosts RRD files.'),
			'method' => 'checkbox'
			),
		'rrdp_header' => array(
			'friendly_name' => __('RRDtool Proxy Server'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'rrdp_server' => array(
			'friendly_name' => __('Proxy Server'),
			'description' => __('The dns hostname or ip address of the rrdtool proxy server.'),
			'method' => 'textbox',
			'max_length' => '255'
			),
		'rrdp_port' => array(
			'friendly_name' => __('Proxy Port Number'),
			'description' => __('TCP port for encrypted communication.'),
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '40301',
			'size' => '5'
			),
		'rrdp_fingerprint' => array(
			'friendly_name' => __('RSA Fingerprint'),
			'description' => __('The fingerprint of the current public RSA key the proxy is using. This is required to establish a trusted connection.'),
			'method' => 'textbox',
			'max_length' => '47',
			'default' => '',
			'size' => '47'
			),
		'rrdp_header2' => array(
			'friendly_name' => __('RRDtool Proxy Server - Backup'),
			'method' => 'spacer',
			'collapsible' => 'true'
			),
		'rrdp_server_backup' => array(
			'friendly_name' => __('Proxy Server'),
			'description' => __('The dns hostname or ip address of the rrdtool backup proxy server if proxy is running in MSR mode.'),
			'method' => 'textbox',
			'max_length' => '255'
			),
		'rrdp_port_backup' => array(
			'friendly_name' => __('Proxy Port Number'),
			'description' => __('TCP port for encrypted communication with the backup proxy.'),
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '40301',
			'size' => '5'
			),
		'rrdp_fingerprint_backup' => array(
			'friendly_name' => __('RSA Fingerprint'),
			'description' => __('The fingerprint of the current public RSA key the backup proxy is using. This required to establish a trusted connection.'),
			'method' => 'textbox',
			'max_length' => '47',
			'default' => '',
			'size' => '47'
			)
 		),
	'spikes' => array(
		'spikekill_header' => array(
			'friendly_name' => __('Spike Kill Settings'),
			'method' => 'spacer',
			),
		'spikekill_method' => array(
			'friendly_name' => __('Removal Method'),
			'description' => __('There are two removal methods.  The first, Standard Deviation, will remove any
			sample that is X number of standard deviations away from the average of samples.  The second method,
			Variance, will remove any sample that is X% more than the Variance average.  The Variance method takes
			into account a certain number of \'outliers\'.  Those are exceptinal samples, like the spike, that need
			to be excluded from the Variance Average calculation.'),
			'method' => 'drop_array',
			'default' => '2',
			'array' => array(1 => __('Standard Deviation'), 2 => __('Variance Based w/Outliers Removed'))
			),
		'spikekill_avgnan' => array(
			'friendly_name' => __('Replacement Method'),
			'description' => __('There are two replacement methods.  The first method replaces the spike with the
			the average of the data source in question.  The second method replaces the spike with a \'NaN\'.'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => array(1 => __('Average'), 2 => __('NaN\'s'))
			),
		'spikekill_deviations' => array(
			'friendly_name' => __('Number of Standard Deviations'),
			'description' => __('Any value that is this many standard deviations above the average will be excluded.
			A good number will be dependent on the type of data to be operated on.  We recommend a number no lower
			than 5 Standard Deviations.'),
			'method' => 'drop_array',
			'default' => '5',
			'array' => array(
				3 => __('%d Standard Deviations', 3),
				4 => __('%d Standard Deviations', 4),
				5 => __('%d Standard Deviations', 5),
				6 => __('%d Standard Deviations', 6),
				7 => __('%d Standard Deviations', 7),
				8 => __('%d Standard Deviations', 8),
				9 => __('%d Standard Deviations', 9),
				10 => __('%d Standard Deviations', 10)
				)
			),
		'spikekill_percent' => array(
			'friendly_name' => __('Variance Percentage'),
			'description' => __('This value represents the percentage above the adjusted sample average once outliers
			have been removed from the sample.  For example, a Variance Percentage of 100% on an adjusted average of 50
			would remove any sample above the quantity of 100 from the graph.'),
			'method' => 'drop_array',
			'default' => '500',
			'array' => array(
				100 => '100 %',
				200 => '200 %',
				300 => '300 %',
				400 => '400 %',
				500 => '500 %',
				600 => '600 %',
				700 => '700 %',
				800 => '800 %',
				900 => '900 %',
				1000 => '1000 %'
				)
			),
		'spikekill_outliers' => array(
			'friendly_name' => __('Variance Number of Outliers'),
			'description' => __('This value represents the number of high and low average samples will be removed from the
			sample set prior to calculating the Variance Average.  If you choose an outlier value of 5, then both the top
			and bottom 5 averages are removed.'),
			'method' => 'drop_array',
			'default' => '5',
			'array' => array(
				3  => __('%d High/Low Samples', 3),
				4  => __('%d High/Low Samples', 4),
				5  => __('%d High/Low Samples', 5),
				6  => __('%d High/Low Samples', 6),
				7  => __('%d High/Low Samples', 7),
				8  => __('%d High/Low Samples', 8),
				9  => __('%d High/Low Samples', 9),
				10 => __('%d High/Low Samples', 10),
				)
			),
		'spikekill_number' => array(
			'friendly_name' => __('Max Kills Per RRA'),
			'description' => __('This value represents the maximum number of spikes to remove from a Graph RRA.'),
			'method' => 'drop_array',
			'default' => '5',
			'array' => array(
				3  => __('%d Samples', 3),
				4  => __('%d Samples', 4),
				5  => __('%d Samples', 5),
				6  => __('%d Samples', 6),
				7  => __('%d Samples', 7),
				8  => __('%d Samples', 8),
				9  => __('%d Samples', 9),
				10 => __('%d Samples', 10),
				)
			),
		'spikekill_backupdir' => array(
			'friendly_name' => __('RRDfile Backup Directory'),
			'description' => __('If this directory is not empty, then your original RRDfiles will be backed
			up to this location.'),
			'method' => 'dirpath',
			'default' => $config['base_path'] . '/cache/spikekill/',
			'max_length' => '255',
			'size' => '60'
			),
		'spikekill_batch_header' => array(
			'friendly_name' => __('Batch Spike Kill Settings'),
			'method' => 'spacer',
			),
		'spikekill_batch' => array(
			'friendly_name' => __('Removal Schedule'),
			'description' => __('Do you wish to periodically remove spikes from your graphs?  If so, select the frequency
			below.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				0  => __('Disabled'), 
				6  => __('Every %d Hours', 6), 
				12 => __('Every %d Hours', 12), 
				24 => __('Once a Day'), 
				48 => __('Every Other Day')
				)
			),
		'spikekill_basetime' => array(
			'friendly_name' => __('Base Time'),
			'description' => __('The Base Time for Spike removal to occur.  For example, if you use \'12:00am\' and you choose
			once per day, the batch removal would begin at approximately midnight every day.'),
			'method' => 'textbox',
			'default' => '12:00am',
			'max_length' => '10',
			'size' => '10'
			),
		'spikekill_templates' => array(
			'friendly_name' => __('Graph Templates to SpikeKill'),
			'method' => 'drop_multi',
			'description' => __('When performing batch spike removal, only the templates selected below will be acted on.'),
			'array' => $spikekill_templates,
            )
		)
	);

$settings_user = array(
	'general' => array(
		'selected_theme' => array(
			'friendly_name' => __('Theme'),
			'description' => __('Please select one of the available Themes to skin your Cacti with.'),
			'method' => 'drop_array',
			'default' => 'classic',
			'array' => $themes
			),
		'default_view_mode' => array(
			'friendly_name' => __('Default View Mode'),
			'description' => __('Which Graph mode you want displayed by default when you first visit the Graphs page?'),
			'method' => 'drop_array',
			'array' => $graph_views,
			'default' => '1'
			),
        'user_language' => array(
            'friendly_name' => __('User Language'),
            'description' => __('Defines the preferred GUI language.'),
            'method' => 'drop_array',
            'default' => 'us',
            'array' => get_installed_locales()
            ),
		'show_graph_title' => array(
			'friendly_name' => __('Show Graph Title'),
			'description' => __('Display the graph title on the page so that it may be searched using the browser.'),
			'method' => 'checkbox',
			'default' => ''
			),
		'default_date_format' => array(
			'friendly_name' => __('Graph Date Display Format'),
			'description' => __('The date format to use for graphs'),
			'method' => 'drop_array',
			'array' => $graph_dateformats,
			'default' => GD_Y_MO_D
			),
		'default_datechar' => array(
			'friendly_name' => __('Graph Date Separator'),
			'description' => __('The date separator to be used for graphs'),
			'method' => 'drop_array',
			'array' => $graph_datechar,
			'default' => GDC_SLASH
			),
		'page_refresh' => array(
			'friendly_name' => __('Page Refresh'),
			'description' => __('The number of seconds between automatic page refreshes.'),
			'method' => 'drop_array',
			'default' => '300',
			'array' => array( '15' => __('%d Seconds', 15), '20' => __('%d Seconds', 20), '30' => __('%d Seconds', 30), '60' => __('1 Minute'), '300' => __('%d Minutes', 5) )
			),
		'preview_graphs_per_page' => array(
			'friendly_name' => __('Preview Graphs Per Page'),
			'description' => __('The number of graphs to display on one page in preview mode.'),
			'method' => 'drop_array',
			'default' => '10',
			'array' => $graphs_per_page
			)
		),
	'timespan' => array(
		'default_rra_id' => array(
			'friendly_name' => __('Default Time Range'),
			'description' => __('The default RRA to use in rare occasions.'),
			'method' => 'drop_sql',
			'sql' => 'SELECT id, name FROM data_source_profiles_rra ORDER BY steps',
			'default' => '1'
			),
		'default_timespan' => array(
			'friendly_name' => __('Default Graph View Timespan'),
			'description' => __('The default timespan you wish to be displayed when you display graphs'),
			'method' => 'drop_array',
			'array' => $graph_timespans,
			'default' => GT_LAST_DAY
			),
		'default_timeshift' => array(
			'friendly_name' => __('Default Graph View Timeshift'),
			'description' => __('The default timeshift you wish to be displayed when you display graphs'),
			'method' => 'drop_array',
			'array' => $graph_timeshifts,
			'default' => GTS_1_DAY
			),
		'allow_graph_dates_in_future' => array(
			'friendly_name' => __('Allow Graph to extend to Future'),
			'description' => __('When displaying Graphs, allow Graph Dates to extend "to future"'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'first_weekdayid' => array(
			'friendly_name' => __('First Day of the Week'),
			'description' => __('The first Day of the Week for weekly Graph Displays'),
			'method' => 'drop_array',
			'array' => $graph_weekdays,
			'default' => WD_MONDAY
			),
		'day_shift_start' => array(
			'friendly_name' => __('Start of Daily Shift'),
			'description' => __('Start Time of the Daily Shift.'),
			'method' => 'textbox',
			'default' => '07:00',
			'max_length' => '5',
			'size' => '7'
			),
		'day_shift_end' => array(
			'friendly_name' => __('End of Daily Shift'),
			'description' => __('End Time of the Daily Shift.'),
			'method' => 'textbox',
			'default' => '18:00',
			'max_length' => '5',
			'size' => '7'
			),
		),
	'thumbnail' => array(
		'thumbnail_sections' => array(
			'friendly_name' => __('Thumbnail Sections'),
			'description' => __('Which portions of Cacti display Thumbnails by default.'),
			'method' => 'checkbox_group',
			'items' => array(
				'thumbnail_section_preview' => array(
					'friendly_name' => __('Preview Mode'),
					'default' => 'on'
					),
				'thumbnail_section_tree_2' => array(
					'friendly_name' => __('Tree View'),
					'default' => ''
					)
				)
			),
		'num_columns' => array(
			'friendly_name' => __('Preview Thumbnail Columns'),
			'description' => __('The number of columns to use when displaying Thumbnail graphs in Preview mode.'),
			'method' => 'drop_array',
			'default' => '2',
			'array' => array('1' => __('1 Column'),'2' => __('%d Columns', 2), '3' => __('%d Columns', 3), '4' => __('%d Columns', 4), '5' => __('%d Columns', 5), '6' => __('%d Columns', 6) )
			),
		'num_columns_tree' => array(
			'friendly_name' => __('Treeview Thumbnail Columns'),
			'description' => __('The number of columns to use when displaying Thumbnail graphs in Tree mode.'),
			'method' => 'drop_array',
			'default' => '2',
			'array' => array('1' => __('1 Column'),'2' => __('%d Columns', 2), '3' => __('%d Columns', 3), '4' => __('%d Columns', 4), '5' => __('%d Columns', 5), '6' => __('%d Columns', 6) )
			),
		'default_height' => array(
			'friendly_name' => __('Thumbnail Height'),
			'description' => __('The height of Thumbnail graphs in pixels.'),
			'method' => 'textbox',
			'default' => '100',
			'max_length' => '10',
			'size' => '7'
			),
		'default_width' => array(
			'friendly_name' => __('Thumbnail Width'),
			'description' => __('The width of Thumbnail graphs in pixels.'),
			'method' => 'textbox',
			'default' => '300',
			'max_length' => '10',
			'size' => '7'
			),
		),
	'tree' => array(
		'default_tree_id' => array(
			'friendly_name' => __('Default Tree'),
			'description' => __('The default graph tree to use when displaying graphs in tree mode.'),
			'method' => 'drop_sql',
			'sql' => 'SELECT id,name FROM graph_tree ORDER BY name',
			'default' => '0'
			),
		'treeview_graphs_per_page' => array(
			'friendly_name' => __('Graphs Per Page'),
			'description' => __('The number of graphs to display on one page in preview mode.'),
			'method' => 'drop_array',
			'default' => '10',
			'array' => $graphs_per_page
			),
		'expand_hosts' => array(
			'friendly_name' => __('Expand Devices'),
			'description' => __('Choose whether to expand the Graph Templates and Data Queries used by a Device on Tree.'),
			'method' => 'checkbox',
			'default' => ''
			)
		),
	'fonts' => array(
		'custom_fonts' => array(
			'friendly_name' => __('Use Custom Fonts'),
			'description' => __('Choose whether to use your own custom fonts and font sizes or utilize the system defaults.'),
			'method' => 'checkbox',
			'on_change' => 'graphSettings()',
			'default' => ''
			),
		'title_size' => array(
			'friendly_name' => __('Title Font Size'),
			'description' => __('The size of the font used for Graph Titles'),
			'method' => 'textbox',
			'default' => '12',
			'max_length' => '10'
			),
		'title_font' => array(
			'friendly_name' => __('Title Font File'),
			'description' => __('The font file to use for Graph Titles'),
			'method' => 'font',
			'max_length' => '100'
			),
		'legend_size' => array(
			'friendly_name' => __('Legend Font Size'),
			'description' => __('The size of the font used for Graph Legend items'),
			'method' => 'textbox',
			'default' => '10',
			'max_length' => '10'
			),
		'legend_font' => array(
			'friendly_name' => __('Legend Font File'),
			'description' => __('The font file to be used for Graph Legend items'),
			'method' => 'font',
			'max_length' => '100'
			),
		'axis_size' => array(
			'friendly_name' => __('Axis Font Size'),
			'description' => __('The size of the font used for Graph Axis'),
			'method' => 'textbox',
			'default' => '8',
			'max_length' => '10'
			),
		'axis_font' => array(
			'friendly_name' => __('Axis Font File'),
			'description' => __('The font file to be used for Graph Axis items'),
			'method' => 'font',
			'max_length' => '100'
			),
		'unit_size' => array(
			'friendly_name' => __('Unit Font Size'),
			'description' => __('The size of the font used for Graph Units'),
			'method' => 'textbox',
			'default' => '8',
			'max_length' => '10'
			),
		'unit_font' => array(
			'friendly_name' => __('Unit Font File'),
			'description' => __('The font file to be used for Graph Unit items'),
			'method' => 'font',
			'max_length' => '100'
			)
		)
	);

api_plugin_hook('config_settings');

