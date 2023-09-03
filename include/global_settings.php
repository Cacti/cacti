<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

$dir = dir($config['base_path'] . '/include/themes/');
while (false !== ($entry = $dir->read())) {
	if ($entry != '.' && $entry != '..') {
		if (is_dir($config['base_path'] . '/include/themes/' . $entry)) {
			$themes[$entry] = __(ucwords($entry));
		}
	}
}
asort($themes);
$dir->close();

/* tab information */
$tabs = array(
	'general'        => __('General'),
	'path'           => __('Paths'),
	'snmp'           => __('Device Defaults'),
	'poller'         => __('Poller'),
	'data'           => __('Data'),
	'visual'         => __('Visual'),
	'authentication' => __('Authentication'),
	'boost'          => __('Performance'),
	'spikes'         => __('Spikes'),
	'mail'           => __('Mail/Reporting/DNS')
);

$tabs_graphs = array(
	'general'   => __('General Settings'),
	'timespan'  => __('Time Spanning/Shifting'),
	'thumbnail' => __('Graph Thumbnail Settings'),
	'tree'      => __('Tree Settings'),
	'fonts'     => __('Graph Fonts')
);

if (db_table_exists('plugin_config')) {
	$logplugins = array_rekey(db_fetch_assoc('SELECT directory AS id, name
		FROM plugin_config
		WHERE status = 1
		ORDER BY name'), 'id', 'name');
} else {
	$logplugins = array();
}

/* get the files for selective logging */
$realm_files  = array_keys($user_auth_realm_filenames);
$nohead_files = array_values($no_http_header_files);
foreach($realm_files as $file) {
	$logfiles[$file] = $file;
}

foreach($nohead_files as $file) {
	$logfiles[$file] = $file;
}

$logfiles['poller_realtime.php'] = 'poller_realtime.php';
$logfiles['cmd_realtime.php']    = 'cmd_realtime.php';

asort($logfiles);

$mail_methods = array(
	CACTI_MAIL_PHP      => __('PHP Mail() Function'),
	CACTI_MAIL_SENDMAIL => __('Sendmail'),
	CACTI_MAIL_SMTP     => __('SMTP')
);

if ($config['cacti_server_os'] == 'win32') {
	unset($mail_methods[CACTI_MAIL_SENDMAIL]);
}

/* cache the admin account */
$admin_account = '0';

if (isset($_SESSION['admin_account']) && isset($_SESSION['sess_user_id'])) {
	$admin_account = $_SESSION['admin_account'];
} elseif (isset($_SESSION['sess_user_id'])) {
	$admin_account = db_fetch_cell('SELECT value FROM settings WHERE name="admin_user"');

	if (!empty($admin_account)) {
		$_SESSION['admin_account'] = db_qstr($admin_account) . ', ' . $_SESSION['sess_user_id'];
	} else {
		$_SESSION['admin_account'] = $_SESSION['sess_user_id'];
	}
}

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
			'file_type' => 'binary',
			'max_length' => '255'
		),
		'path_snmpget' => array(
			'friendly_name' => __('snmpget Binary Path'),
			'description' => __('The path to your snmpget binary.'),
			'method' => 'filepath',
			'file_type' => 'binary',
			'max_length' => '255'
		),
		'path_snmpbulkwalk' => array(
			'friendly_name' => __('snmpbulkwalk Binary Path'),
			'description' => __('The path to your snmpbulkwalk binary.'),
			'method' => 'filepath',
			'file_type' => 'binary',
			'max_length' => '255'
		),
		'path_snmpgetnext' => array(
			'friendly_name' => __('snmpgetnext Binary Path'),
			'description' => __('The path to your snmpgetnext binary.'),
			'method' => 'filepath',
			'file_type' => 'binary',
			'max_length' => '255'
		),
		'path_snmptrap' => array(
			'friendly_name' => __('snmptrap Binary Path'),
			'description' => __('The path to your snmptrap binary.'),
			'method' => 'filepath',
			'file_type' => 'binary',
			'max_length' => '255'
		),
		'path_rrdtool' => array(
			'friendly_name' => __('RRDtool Binary Path'),
			'description' => __('The path to the rrdtool binary.'),
			'method' => 'filepath',
			'file_type' => 'binary',
			'max_length' => '255'
		),
		'path_php_binary' => array(
			'friendly_name' => __('PHP Binary Path'),
			'description' => __('The path to your PHP binary file (may require a php recompile to get this file).'),
			'method' => 'filepath',
			'file_type' => 'binary',
			'max_length' => '255'
		),
		'logging_header' => array(
			'friendly_name' => __('Logging'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'path_cactilog' => array(
			'friendly_name' => __('Cacti Log Path'),
			'description' => __('The path to your Cacti log file (if blank, defaults to &lt;path_cacti&gt;/log/cacti.log)'),
			'method' => 'filepath',
			'file_type' => 'ascii',
			'default' => $config['base_path'] . '/log/cacti.log',
			'max_length' => '255',
			'install_check' => 'writable',
			'install_blank' => true
		),
		'path_stderrlog' => array(
			'friendly_name' => __('Poller Standard Error Log Path'),
			'description' => __('If you are having issues with Cacti\'s Data Collectors, set this file path and the Data Collectors standard error will be redirected to this file'),
			'method' => 'filepath',
			'file_type' => 'ascii',
			'default' => $config['base_path'] . '/log/cacti_stderr.log',
			'max_length' => '255',
			'install_check' => 'writable',
			'install_optional' => true
		),
		'logrotate_enabled' => array(
			'friendly_name' => __('Rotate the Cacti Log'),
			'description' => __('This option will rotate the Cacti Log periodically.'),
			'method' => 'checkbox',
			'default' => 'on',
		),
		'logrotate_frequency' => array(
			'friendly_name' => __('Rotation Frequency'),
			'description' => __('At what frequency would you like to rotate your logs?'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => $logrotate_frequency
		),
		'logrotate_retain' => array(
			'friendly_name' => __('Log Retention'),
			'description' => __('How many log files do you wish to retain?  Use 0 to never remove any logs. (0-365)'),
			'method' => 'textbox',
			'default' => '7',
			'max_length' => 3,
			'size' => 4
		),
		'pollerpaths_header' => array(
			'friendly_name' => __('Alternate Poller Path'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'path_spine' => array(
			'friendly_name' => __('Spine Binary File Location'),
			'description' => __('The path to Spine binary.'),
			'method' => 'filepath',
			'file_type' => 'binary',
			'max_length' => '255',
			'install_optional' => true,
		),
		'path_spine_config' => array(
			'friendly_name' => __('Spine Config File Path'),
			'description' => __('The path to Spine configuration file.  By default, in the cwd of Spine, or /etc if not specified.'),
			'method' => 'filepath',
			'file_type' => 'ascii',
			'max_length' => '255',
			'install_optional' => true,
		),
		'rrdclean_header' => array(
			'friendly_name' => __('RRD Cleaner'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'rrd_autoclean' => array(
			'friendly_name' => __('RRDfile Auto Clean'),
			'description' => __('Automatically archive or delete RRDfiles when their corresponding Data Sources are removed from Cacti'),
			'method' => 'checkbox',
			'default' => ''
 		),
		'rrd_autoclean_method' => array(
			'friendly_name' => __('RRDfile Auto Clean Method'),
			'description' => __('The method used to Clean RRDfiles from Cacti after their Data Sources are deleted.'),
			'method' => 'drop_array',
			'array' => array(
				'1' => __('Delete'),
				'3' => __('Archive')
			),
			'default' => '1'
 		),
		'rrd_archive' => array(
			'friendly_name' => __('Archive directory'),
			'description' => __('This is the directory where RRDfiles are <strong>moved</strong> for archiving'),
			'method' => 'dirpath',
			'default' => $config['base_path'] . '/rra/archive/',
			'max_length' => 255,
		),
	),
	'general' => array(
		'poller_specific_header' => array(
			'friendly_name' => __('Log Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'log_destination' => array(
			'friendly_name' => __('Log Destination'),
			'description' => __('How will Cacti handle event logging.'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => $logfile_options,
		),
		'log_verbosity' => array(
			'friendly_name' => __('Generic Log Level'),
			'description' => __('What level of detail do you want sent to the log file.  WARNING: Leaving in any other status than NONE or LOW can exhaust your disk space rapidly.'),
			'method' => 'drop_array',
			'default' => POLLER_VERBOSITY_LOW,
			'array' => $logfile_verbosity,
		),
		'log_validation' => array(
			'friendly_name' => __('Log Input Validation Issues'),
			'description' => __('Record when request fields are accessed without going through proper input validation'),
			'method' => 'checkbox',
			'default' => ''
		),
		'data_source_trace' => array(
			'friendly_name' => __('Data Source Tracing'),
			'description' => __('A developer only option to trace the creation of Data Sources mainly around checks for uniqueness'),
			'method' => 'checkbox',
			'default' => ''
		),
		'selective_debug' => array(
			'friendly_name' => __('Selective File Debug'),
			'description' => __('Select which files you wish to place in Debug mode regardless of the Generic Log Level setting.  Any files selected will be treated as they are in Debug mode.'),
			'method' => 'drop_multi',
			'array' => $logfiles,
			'default' => ''
		),
		'selective_plugin_debug' => array(
			'friendly_name' => __('Selective Plugin Debug'),
			'description' => __('Select which Plugins you wish to place in Debug mode regardless of the Generic Log Level setting.  Any files used by this plugin will be treated as they are in Debug mode.'),
			'method' => 'drop_multi',
			'array' => $logplugins,
			'default' => ''
		),
		'selective_device_debug' => array(
			'friendly_name' => __('Selective Device Debug'),
			'description' => __('A comma delimited list of Device ID\'s that you wish to be in Debug mode during data collection.  This Debug level is only in place during the Cacti polling process.'),
			'method' => 'textbox',
			'size' => '30',
			'max_length' => 30,
			'default' => ''
		),
		'poller_log' => array(
			'friendly_name' => __('Syslog/Eventlog Item Selection'),
			'description' => __('When using Syslog/Eventlog for logging, the Cacti log messages that will be forwarded to the Syslog/Eventlog.'),
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
			)
		),
		'i18n_header' => array(
			'friendly_name' => __('Internationalization (i18n)'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'i18n_language_support' => array(
			'friendly_name' => __('Language Support'),
			'description' => __('Choose \'enabled\' to allow the localization of Cacti. The strict mode requires that the requested language will also be supported by all plugins being installed at your system. If that\'s not the fact everything will be displayed in English.'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => $i18n_modes
		),
		'i18n_default_language' => array(
			'friendly_name' => __('Language'),
			'description' => __('Default language for this system.'),
			'method' => 'drop_language',
			'default' => 'en-US',
			'array' => get_installed_locales()
		),
		'i18n_auto_detection' => array(
			'friendly_name' => __('Auto Language Detection'),
			'description' => __('Allow to automatically determine the \'default\' language of the user and provide it at login time if that language is supported by Cacti. If disabled, the default language will be in force until the user elects another language.'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => array(
				'0' => __('Disabled'),
				'1' => __('Enabled')
			)
		),
		'i18n_language_handler' => array(
			'friendly_name' => __('Preferred Language Processor'),
			'description' => __('Cacti includes support for multiple alternate Language Translation Processors.  If none is selected Cacti will attempt to use the first one found.'),
			'method' => 'drop_array',
			'default' => CACTI_LANGUAGE_HANDLER_DEFAULT,
			'array' => $i18n_supported_languages
		),
		'client_timezone_support' => array(
			'friendly_name' => __('Client TimeZone Support'),
			'description' => __('How should Cacti support Client Dates based upon the Client browsers timezone.'),
			'method' => 'drop_array',
			'array' => array(
				'0' => __('Disabled'),
				'1' => __('Enabled')
			),
			'default' => '0'
		),
		'default_date_format' => array(
			'friendly_name' => __('Date Display Format'),
			'description' => __('The System default date format to use in Cacti.'),
			'method' => 'drop_array',
			'array' => $dateformats,
			'default' => GD_Y_MO_D
		),
		'default_datechar' => array(
			'friendly_name' => __('Date Separator'),
			'description' => __('The System default date separator to be used in Cacti.'),
			'method' => 'drop_array',
			'array' => $datechar,
			'default' => GDC_HYPHEN
		),
		'other1_header' => array(
			'friendly_name' => __('Other Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'site_location_filter' => array(
			'friendly_name' => __('Show only site specific locations'),
			'description' => __('Show only locations that are available at a devices selected site.'),
			'method' => 'checkbox',
			'default' => '',
		),
		'default_has' => array(
			'friendly_name' => __('Has Graphs/Data Sources Checked'),
			'description' => __('Should the Has Graphs and Has Data Sources be Checked by Default.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'rrdtool_version' => array(
			'friendly_name' => __('RRDtool Version'),
			'description' => __('The version of RRDtool that you have installed.'),
			'method' => 'drop_array',
			'default' => '1.4.0',
			'array' => $rrdtool_versions,
		),
		'enable_rrdtool_gradient_support' => array(
			'friendly_name' => __('Enable Gradient Support'),
			'description' => __('Enabled Gradient Support for AREA and STACK charts.'),
			'default' => '',
			'method' => 'checkbox',
		),
		'graph_auth_method' => array(
			'friendly_name' => __('Graph Permission Method'),
			'description' => __('There are four methods for determining a User\'s Graph Permissions.  The first is \'Permissive\'.  Under the \'Permissive\' setting, a User only needs access to either the Graph, Device or Graph Template to gain access to the Graphs that apply to them.  Under \'Restrictive\', the User must have access to the Graph, the Device, and the Graph Template to gain access to the Graph.  These first two methods have scalability problems for very large installs.  So, two additional options are available.  They are \'Device Based\', which means if you have access to the Device, you get access to it\'s Graphs.  And lastly \'Graph Template Based\', which means if you have access to the \'Graph Template\' you get access to all Device Graphs of that Template.'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => array(
				'1' => __('Permissive'),
				'2' => __('Restrictive'),
				'3' => __('Device Based'),
				'4' => __('Graph Template Based'),
			)
		),
		'grds_creation_method' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Graph/Data Source Creation Method'),
			'description' => __('If set to Simple, Graphs and Data Sources can only be created from New Graphs.  If Advanced, legacy Graph and Data Source creation is supported.'),
			'default' => '0',
			'array' => array(
				'0' => __('Simple'),
				'1' => __('Advanced')
			)
		),
		'hide_form_description' => array(
			'friendly_name' => __('Show Form/Setting Help Inline'),
			'description' => __('When checked, Form and Setting Help will be show inline.  Otherwise it will be presented when hovering over the help button.'),
			'default' => '',
			'method' => 'checkbox',
		),
		'local_documentation' => array(
			'friendly_name' => __('Local Page Help Only'),
			'description' => __('By default Cacti page help is located at docs.cacti.net.  However, if your system does not have access to the Internet, you may download the documentation locally in HTML format and host it in the \'docs\' location of you Cacti server.  If you choose only to leverage the local location for page help documentation, check this checkbox.'),
			'default' => '',
			'method' => 'checkbox',
		),
		'deletion_verification' => array(
			'friendly_name' => __('Deletion Verification'),
			'description' => __('Prompt user before item deletion.'),
			'default' => 'on',
			'method' => 'checkbox',
		),
		'ds_preselected_delete' => array(
			'friendly_name' => __('Data Source Preservation Preset'),
			'description' => __('When enabled, Cacti will set Radio Button to Delete related Data Sources of a Graph when removing Graphs.  Note: Cacti will not allow the removal of Data Sources if they are used in other Graphs.'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'graphs_auto_unlock' => array(
			'friendly_name' => __('Graphs Auto Unlock'),
			'description' => __('When enabled, Cacti will not lock Graphs.  This allow a faster manual modification of Data Sources related to a Graph.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'hide_console' => array(
			'friendly_name' => __('Hide Cacti Dashboard'),
			'description' => __('For use with Cacti\'s External Link Support.  Using this setting, you can hide the Cacti Dashboard, so you can display just your own page.'),
			'default' => '',
			'method' => 'checkbox'
		),
		'drag_and_drop' => array(
			'friendly_name' => __('Enable Drag-N-Drop'),
			'description' => __('Some of Cacti\'s interfaces support Drag-N-Drop.  If checked this option will be enabled.  Note: For visually impaired user, this option may be disabled.'),
			'method' => 'checkbox',
			'default' => 'on',
		),
		'security_header' => array(
			'friendly_name' => __('Site Security'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'force_https' => array(
			'friendly_name' => __('Force Connections over HTTPS'),
			'description' => __('When checked, any attempts to access Cacti will be redirected to HTTPS to ensure high security.'),
			'default' => '',
			'method' => 'checkbox',
		),
		'content_security_policy_script' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Content-Security Allow Unsafe JavaScript eval() calls'),
			'description' => __('Certain Cacti plugins require the use of unsafe JavaScript eval() calls.  If you select this option, they will be allowed in Cacti.'),
			'default' => '',
			'array' => array(
				'0'           => __('No'),
				'unsafe-eval' => __('Yes')
			)
		),
		'content_security_alternate_sources' => array(
			'friendly_name' => __('Content-Security Alternate Sources'),
			'description' => __('Space delimited domain names that will be permitted to be accessed outside of the Web Server itself.  This is important for users choosing to use a CDN, or hosting site.  Sources can includes wildcards for example: *.mydomain.com, or a protocol, for example: https://*.example.com.  These Alternate Sources include Image, CSS and JavaScript types only.'),
			'method' => 'textbox',
			'default' => '',
			'size' => '100',
			'max_length' => '255',
		),
		'automation_header' => array(
			'friendly_name' => __('Automation'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'automation_graphs_enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Enable Automatic Graph Creation'),
			'description' => __('When disabled, Cacti Automation will not actively create any Graph.  This is useful when adjusting Device settings so as to avoid creating new Graphs each time you save an object.  Invoking Automation Rules manually will still be possible.'),
			'default' => 'on',
		),
		'automation_tree_enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Enable Automatic Tree Item Creation'),
			'description' => __('When disabled, Cacti Automation will not actively create any Tree Item.  This is useful when adjusting Device or Graph settings so as to avoid creating new Tree Entries each time you save an object.  Invoking Rules manually will still be possible.'),
			'default' => 'on',
		),
		'automation_email' => array(
			'friendly_name' => __('Automation Notification To Email'),
			'description' => __('The Email Address to send Automation Notification Emails to if not specified at the Automation Network level.  If either this field, or the Automation Network value are left blank, Cacti will use the Primary Cacti Admins Email account.'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '128',
		),
		'automation_fromname' => array(
			'friendly_name' => __('Automation Notification From Name'),
			'description' => __('The Email Name to use for Automation Notification Emails to if not specified at the Automation Network level.  If either this field, or the Automation Network value are left blank, Cacti will use the system default From Name.'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '32',
			'size' => '30'
		),
		'automation_fromemail' => array(
			'friendly_name' => __('Automation Notification From Email'),
			'description' => __('The Email Address to use for Automation Notification Emails to if not specified at the Automation Network level.  If either this field, or the Automation Network value are left blank, Cacti will use the system default From Email Address.'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '100',
		),
		'other_header' => array(
			'friendly_name' => __('Graph Template Defaults'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'default_test_source' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Graph Template Test Data Source'),
			'description' => __('Check this checkbox if you wish to test the Data Sources prior to their creation for new and newly imported Graph Templates.  With Test Data Sources enabled, if the Data Source does not return valid data, the Graph will not be created.  This setting is important if you wish to have a more generic Device Template that can include more Graph Templates that can be selectively applied depending on the characteristics of the Device itself.  Note: If you have a long running script as a Data Source, the time to create Graphs will be increased.'),
			'default' => '',
			),
		'default_image_format' => array(
			'friendly_name' => __('Graph Template Image Format'),
			'description' => __('The default Image Format to be used for all new Graph Templates.'),
			'method' => 'drop_array',
			'default' => '3',
			'array' => $image_types,
		),
		'default_graph_height' => array(
			'friendly_name' => __('Graph Template Height'),
			'description' => __('The default Graph Height to be used for all new Graph Templates.'),
			'method' => 'textbox',
			'default' => '200',
			'size' => '5',
			'max_length' => '5'
		),
		'default_graph_width' => array(
			'friendly_name' => __('Graph Template Width'),
			'description' => __('The default Graph Width to be used for all new Graph Templates.'),
			'method' => 'textbox',
			'default' => '700',
			'size' => '5',
			'max_length' => '5'
		)
	),
	'snmp' => array(
		'general_header' => array(
			'friendly_name' => __('General Defaults'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'default_template' => array(
			'friendly_name' => __('Template'),
			'description' => __('The default Device Template used on all new Devices.'),
			'method' => 'drop_sql',
			'default' => '1',
			'none_value' => __('None'),
			'sql' => 'SELECT id, name FROM host_template ORDER BY name',
		),
		'default_site' => array(
			'friendly_name' => __('Site'),
			'description' => __('The default Site for all new Devices.'),
			'method' => 'drop_sql',
			'default' => '1',
			'none_value' => __('None'),
			'sql' => 'SELECT id, name FROM sites ORDER BY name',
		),
		'default_poller' => array(
			'friendly_name' => __('Poller'),
			'description' => __('The default Poller for all new Devices.'),
			'method' => 'drop_sql',
			'default' => '1',
			'none_value' => __('None'),
			'sql' => 'SELECT id, name FROM poller ORDER BY name',
		),
		'device_threads' => array(
			'friendly_name' => __('Device Threads'),
			'description' => __('The default number of Device Threads.  This is only applicable when using the Spine Data Collector.'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => array(
				1  => __('%s Thread', 1),
				2  => __('%s Threads', 2),
				3  => __('%s Threads', 3),
				4  => __('%s Threads', 4),
				5  => __('%s Threads', 5),
				6  => __('%s Threads', 6),
				7  => __('%s Threads', 7),
				8  => __('%s Threads', 8),
				9  => __('%s Threads', 9),
				10 => __('%s Threads', 10)
			)
		),
		'reindex_method' => array(
			'friendly_name' => __('Re-index Method for Data Queries'),
			'description' => __('The default Re-index Method to use for all Data Queries.'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => $reindex_types,
		),
		'default_interface_speed' => array(
			'friendly_name' => __('Default Interface Speed'),
			'description' => __('If Cacti can not determine the interface speed due to either ifSpeed or ifHighSpeed not being set or being zero, what maximum value do you wish on the resulting RRDfiles.'),
			'method' => 'drop_array',
			'default' => '1000',
			'array' => array(
				'100'    => __('100 Mbps Ethernet'),
				'1000'   => __('1 Gbps Ethernet'),
				'10000'  => __('10 Gbps Ethernet'),
				'25000'  => __('25 Gbps Ethernet'),
				'40000'  => __('40 Gbps Ethernet'),
				'56000'  => __('56 Gbps Ethernet'),
				'100000' => __('100 Gbps Ethernet')
			)
		),
		'snmp_header' => array(
			'friendly_name' => __('SNMP Defaults'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'snmp_version' => array(
			'friendly_name' => __('Version'),
			'description' => __('Default SNMP version for all new Devices.'),
			'method' => 'drop_array',
			'default' => '2',
			'array' => $snmp_versions,
		),
		'snmp_community' => array(
			'friendly_name' => __('Community'),
			'description' => __('Default SNMP read community for all new Devices.'),
			'method' => 'textbox',
			'default' => 'public',
			'max_length' => '100',
		),
		'snmp_security_level' => array(
			'friendly_name' => __('Security Level'),
			'description' => __('Default SNMP v3 Security Level for all new Devices.'),
			'method' => 'drop_array',
			'default' => 'authPriv',
			'array' => $snmp_security_levels,
		),
		'snmp_auth_protocol' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Auth Protocol (v3)'),
			'description' => __('Default SNMPv3 Authorization Protocol for all new Devices.'),
			'default' => 'MD5',
			'array' => $snmp_auth_protocols,
		),
		'snmp_username' => array(
			'friendly_name' => __('Auth User (v3)'),
			'description' => __('Default SNMP v3 Authorization User for all new Devices.'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '50',
			'size' => '40'
		),
		'snmp_password' => array(
			'friendly_name' => __('Auth Passphrase (v3)'),
			'description' => __('Default SNMP v3 Authorization Passphrase for all new Devices.'),
			'method' => 'textbox_password',
			'default' => '',
			'max_length' => '50',
			'size' => '40'
		),
		'snmp_priv_protocol' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Privacy Protocol (v3)'),
			'description' => __('Default SNMPv3 Privacy Protocol for all new Devices.'),
			'default' => 'DES',
			'array' => $snmp_priv_protocols,
		),
		'snmp_priv_passphrase' => array(
			'method' => 'textbox_password',
			'friendly_name' => __('Privacy Passphrase (v3)'),
			'description' => __('Default SNMPv3 Privacy Passphrase for all new Devices.'),
			'default' => '',
			'max_length' => '200',
			'size' => '80'
		),
		'snmp_context' => array(
			'method' => 'textbox',
			'friendly_name' => __('SNMP Context (v3)'),
			'description' => __('Enter the SNMP v3 Context for all new Devices.'),
			'value' => '|arg1:snmp_context|',
			'default' => '',
			'max_length' => '64',
			'size' => '40'
		),
		'snmp_engine_id' => array(
			'method' => 'textbox',
			'friendly_name' => __('SNMP Engine ID (v3)'),
			'description' => __('Default SNMP v3 Engine Id for all new Devices. Leave this field empty to use the SNMP Engine ID being defined per SNMPv3 Notification receiver.'),
			'value' => '|arg1:snmp_engine_id|',
			'default' => '',
			'max_length' => '64',
			'size' => '40'
		),
		'snmp_port' => array(
			'friendly_name' => __('Port Number'),
			'description' => __('Default UDP Port for all new Devices.  Typically 161.'),
			'method' => 'textbox',
			'default' => '161',
			'max_length' => '10',
			'size' => '5'
		),
		'snmp_timeout' => array(
			'friendly_name' => __('Timeout'),
			'description' => __('Default SNMP timeout in milli-seconds for all new Devices.'),
			'method' => 'textbox',
			'default' => '500',
			'max_length' => '10',
			'size' => '5'
		),
		'snmp_retries' => array(
			'friendly_name' => __('Retries'),
			'description' => __('Default SNMP retries for all new Devices.'),
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
			'description' => __('Default Availability/Reachability for all new Devices.  The method Cacti will use to determine if a Device is available for polling.  <br><i>NOTE: It is recommended that, at a minimum, SNMP always be selected.</i>'),
			'method' => 'drop_array',
			'default' => AVAIL_SNMP,
			'array' => $availability_options,
		),
		'ping_method' => array(
			'friendly_name' => __('Ping Type'),
			'description' => __('Default Ping type for all new Devices.</i>'),
			'method' => 'drop_array',
			'default' => PING_UDP,
			'array' => $ping_methods,
		),
		'ping_port' => array(
			'friendly_name' => __('Ping Port'),
			'description' => __('Default Ping Port for all new Devices.  With TCP, Cacti will attempt to Syn the port.  With UDP, Cacti requires either a successful connection, or a \'port not reachable\' error to determine if the Device is up or not.'),
			'method' => 'textbox',
			'default' => '23',
			'max_length' => '10',
			'size' => '5'
		),
		'ping_timeout' => array(
			'friendly_name' => __('Ping Timeout Value'),
			'description' => __('Default Ping Timeout value in milli-seconds for all new Devices.  The timeout values to use for Device SNMP, ICMP, UDP and TCP pinging.  ICMP Pings will be rounded up to the nearest second.  TCP and UDP connection timeouts on Windows are controlled by the operating system, and are therefore not recommended on Windows.'),
			'method' => 'textbox',
			'default' => '400',
			'max_length' => '10',
			'size' => '5'
		),
		'ping_retries' => array(
			'friendly_name' => __('Ping Retry Count'),
			'description' => __('The number of times Cacti will attempt to ping a Device before marking it as down.'),
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
			'description' => __('The number of polling intervals a Device must be down before logging an error and reporting Device as down.'),
			'method' => 'textbox',
			'default' => '2',
			'max_length' => '10',
			'size' => '5'
		),
		'ping_recovery_count' => array(
			'friendly_name' => __('Recovery Count'),
			'description' => __('The number of polling intervals a Device must remain up before returning Device to an up status and issuing a notice.'),
			'method' => 'textbox',
			'default' => '3',
			'max_length' => '10',
			'size' => '5'
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
			'default' => 'modern',
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
		'autocomplete_enabled' => array(
			'friendly_name' => __('Autocomplete Enabled'),
			'description' => __('In very large systems, select lists can slow the user interface significantly.  If this option is enabled, Cacti will use autocomplete callbacks to populate the select list systematically.  Note: autocomplete is forcibly disabled on the Classic theme.'),
			'method' => 'drop_array',
			'default' => '1',
			'array' => array(
				1 => __('Yes'),
				0 => __('No')
			)
		),
		'autocomplete_rows' => array(
			'friendly_name' => __('Autocomplete Rows'),
			'description' => __('The default number of rows to return from an autocomplete based select pattern match.') . ' ' . __('.  This is limited by the php setting \'max_input_vars\', currently:') .  ' ' . ini_get('max_input_vars'),
			'method' => 'drop_array',
			'default' => '30',
			'array' => $item_rows
		),
		'tree_header' => array(
			'friendly_name' => __('Tree Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'min_tree_width' => array(
			'friendly_name' => __('Minimum Tree Width'),
			'description' => __('The Minimum width of the Tree to contract to.'),
			'method' => 'textbox',
			'default' => '170',
			'max_length' => '5',
			'size' => '7'
		),
		'max_tree_width' => array(
			'friendly_name' => __('Maximum Tree Width'),
			'description' => __('The Maximum width of the Tree to expand to, after which time, Tree branches will scroll on the page.') . ' ' . __( '.  This is limited by the php setting \'max_input_vars\', currently:') .  ' ' . ini_get('max_input_vars'),
			'method' => 'textbox',
			'default' => '300',
			'max_length' => '5',
			'size' => '7'
		),
		'filter_header' => array(
			'friendly_name' => __('Filter Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'strip_domain' => array(
			'friendly_name' => __('Strip Domains from Device Dropdowns'),
			'description' => __('When viewing Device name filter dropdowns, checking this option will strip to domain from the hostname.'),
			'method' => 'checkbox',
			'default' => ''
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
			'array' => array(
				'-2' => __('All Types'),
				'-1' => __('By Template/Data Query')
			)
		),
		'logmgmt_header' => array(
			'friendly_name' => __('Log Management'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'num_rows_log' => array(
			'friendly_name' => __('Default Log Tail Lines'),
			'description' => __('Default number of lines of the Cacti log file to tail.'),
			'method' => 'drop_array',
			'default' => 500,
			'array' => $log_tail_lines,
		),
		'max_display_rows' => array(
			'friendly_name' => __('Maximum number of rows per page'),
			'description' => __('User defined number of lines for the CLOG to tail when selecting \'All lines\'.'),
			'method' => 'textbox',
			'max_length' => 5,
			'size' => 5,
			'default' => 1000
		),
		'log_refresh_interval' => array(
			'friendly_name' => __('Log Tail Refresh'),
			'description' => __('How often do you want the Cacti log display to update.'),
			'method' => 'drop_array',
			'default' => 60,
			'array' => $page_refresh_interval,
		),
		'clog_header' => array(
			'friendly_name' => __('Log Viewer Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'clog_exclude' => array(
			'friendly_name' => __('Exclusion Regex'),
			'description' => __('Any strings that match this regex will be excluded from the user display.  <strong>For example, if you want to exclude all log lines that include the words \'Admin\' or \'Login\' you would type \'(Admin || Login)\'</strong>'),
			'method' => 'textarea',
			'textarea_rows' => '4',
			'textarea_cols' => '80',
			'max_length' => 512
		),
		'realtime_header' => array(
			'friendly_name' => __('Real-time Graphs'),
			'method' => 'spacer',
			'collapsible' => 'true',
		),
		'realtime_enabled' => array(
			'friendly_name' => __('Enable Real-time Graphing'),
			'description' => __('When an option is checked, users will be able to put Cacti into Real-time mode.'),
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
			'friendly_name' => __('Minimum Refresh Interval'),
			'description' => __('This is the minimal supported time between Graph updates.  This value is also used to set certain RRDfile attributes.  If you have a Device that caches data and does not provide realtime updates, you may have to increase the default Minimum Refresh Interval to prevent creating Graphs with gaps.'),
			'method' => 'drop_array',
			'default' => 10,
			'array' => $realtime_refresh,
		),
		'realtime_cache_path' => array(
			'friendly_name' => __('Cache Directory'),
			'description' => __('This is the location, on the web server where the RRDfiles and PNG files will be cached.  This cache will be managed by the poller.  Make sure you have the correct read and write permissions on this folder'),
			'method' => 'dirpath',
			'default' => $config['base_path'] . '/cache/realtime/',
			'max_length' => 255,
			'size' => 40,
		),
		'rrdtool_header' => array(
			'friendly_name' => __('RRDtool Graph Options'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'graph_watermark' => array(
			'friendly_name' => __('Custom Watermark'),
			'description' => __('Text placed at the bottom center of every Graph.'),
			'method' => 'textbox',
			'default' => 'Generated by Cacti®',
			'max_length' => '80',
			'size' => '60'
		),
		'graph_dateformat' => array(
			'friendly_name' => __('Custom Date Format'),
			'description' => __('To be used when formatting |date_time| on a graph'),
			'method' => 'textbox',
			'default' => 'D d M H:i:s T Y',
			'max_length' => '80',
			'size' => '60'
		),
		'rrdtool_watermark' => array(
			'friendly_name' => __('Disable RRDtool Watermark'),
			'description' => __('Provided your RRDtool version supports it, you may disable RRDtool advertisement on your Graphs by checking this option.'),
			'method' => 'checkbox',
			'default' => '',
		),
		'font_method' => array(
			'friendly_name' => __('Font Selection Method'),
			'description' => __('How do you wish fonts to be handled by default?'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => array(
				0 => __('System'),
				1 => __('Theme')
			)
		),
		'path_rrdtool_default_font' => array(
			'friendly_name' => __('Default Font'),
			'description' =>  __('When not using Theme based font control, the Pangon font-config font name to use for all Graphs. Optionally, you may leave blank and control font settings on a per object basis.'),
			'method' => 'font',
			'placeholder' =>  __('Enter Valid Font Config Value'),
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
			'placeholder' => __('Enter Valid Font Config Value'),
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
			'placeholder' => __('Enter Valid Font Config Value'),
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
			'placeholder' => __('Enter Valid Font Config Value'),
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
			'placeholder' => __('Enter Valid Font Config Value'),
			'max_length' => '100'
			),
		'business_hours_header' => array(
			'friendly_name' => __('Business Hours Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
			),
		'business_hours_start' => array(
			'friendly_name' => __('Start of Business Day'),
			'description' => __('The time your business hours start. Format: hh:mm'),
			'method' => 'textbox',
			'default' => '08:00',
			'max_length' => '5',
			'size' => '60'
			),
		'business_hours_end' => array(
			'friendly_name' => __('End of Business Day'),
			'description' => __('The time your business hours end. Format: hh:mm'),
			'method' => 'textbox',
			'default' => '18:00',
			'max_length' => '6',
			'size' => '60'
			),
		'business_hours_hideWeekends' => array(
			'friendly_name' => __('Hide Weekends'),
			'description' => __('Only show business hours during weekdays.'),
			'method' => 'checkbox',
			'default' => '',
			),
		'business_hours_enable' => array(
			'friendly_name' => __('Show business hours'),
			'description' => __('Display business hours on rrd graphs.'),
			'method' => 'checkbox',
			'default' => '',
			),
		'business_hours_color' => array(
			'friendly_name' => __('Color to use for business hours'),
			'description' => __('The color to be shown for business hours'),
			'method' => 'textbox',
			'default' => 'ccccccff',
			'max_length' => '8',
			'size' => '60'
		),
        'business_hours_max_days' => array(
            'friendly_name' => __('Maximum number of days to show business hours'),
            'description' => __('After this number of days, the business hours will not be added to the graph. <br/>Recommended: 62 days'),
            'method' => 'textbox',
            'default' => '62',
            'max_length' => '3',
            'size' => '60'
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
		'enable_snmp_agent' => array(
			'friendly_name' => __('SNMP Agent Support Enabled'),
			'description' => __('If this option is checked, Cacti will populate SNMP Agent tables with Cacti device and system information.  It does not enable the SNMP Agent itself.'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'poller_type' => array(
			'friendly_name' => __('Poller Type'),
			'description' => __('The poller type to use.  This setting will take affect at next polling interval.'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => $poller_options,
		),
		'poller_sync_interval' => array(
			'friendly_name' => __('Poller Sync Interval'),
			'description' => __('The default polling sync interval to use when creating a poller.  This setting will affect how often remote pollers are checked and updated.'),
			'method' => 'drop_array',
			'default' => 7200,
			'array' => $poller_sync_intervals,
		),
		'poller_interval' => array(
			'friendly_name' => __('Poller Interval'),
			'description' => __('The polling interval in use.  This setting will affect how often RRDfiles are checked and updated.  <strong><u>NOTE: If you change this value, you must re-populate the poller cache.  Failure to do so, may result in lost data.</u></strong>'),
			'method' => 'drop_array',
			'default' => 300,
			'array' => $poller_intervals,
		),
		'cron_interval' => array(
			'friendly_name' => __('Cron/Daemon Interval'),
			'description' => __('The frequency that the Cacti data collector will be started.  You can use either crontab, a scheduled task (for windows), or the cactid systemd service to control launching the Cacti data collector.  For instructions on using the cactid daemon, review the README.md file in the service directory.'),
			'method' => 'drop_array',
			'default' => 300,
			'array' => $cron_intervals,
		),
		'process_leveling' => array(
			'friendly_name' => __('Balance Process Load'),
			'description' => __('If you choose this option, Cacti will attempt to balance the load of each poller process by equally distributing poller items per process.'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'poller_debug' => array(
			'friendly_name' => __('Debug Output Width'),
			'description' => __('If you choose this option, Cacti will check for output that exceeds Cacti\'s ability to store it and issue a warning when it finds it.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'oid_increasing_check_disable' => array(
			'friendly_name' => __('Disable increasing OID Check'),
			'description' => __('Controls disabling check for increasing OID while walking OID tree.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'remote_agent_timeout' => array(
			'friendly_name' => __('Remote Agent Timeout'),
			'description' => __('The amount of time, in seconds, that the Central Cacti web server will wait for a response from the Remote Data Collector to obtain various Device information before abandoning the request.  On Devices that are associated with Data Collectors other than the Central Cacti Data Collector, the Remote Agent must be used to gather Device information.'),
			'method' => 'drop_array',
			'default' => '5',
			'array' => array(
				5 => __('%d Seconds', 5),
				10 => __('%d Seconds', 10),
				15 => __('%d Seconds', 15),
				20 => __('%d Seconds', 20)
			)
		),
		'snmp_bulk_walk_size' => array(
			'friendly_name' => __('SNMP Bulkwalk Fetch Size'),
			'description' => __('How many OID\'s should be returned per snmpbulkwalk request?  For Devices with large SNMP trees, increasing this size will increase re-index performance over a WAN.'),
			'method' => 'drop_array',
			'default' => '10',
			'array' => array(
				'10' => '10',
				'20' => '20',
				'30' => '30',
				'40' => '40',
				'50' => '50',
				'60' => '60',
				'70' => '70',
				'80' => '80',
				'90' => '90',
				'100' => '100',
				'150' => '150',
				'200' => '200'
			)
		),
		'max_get_size' => array(
			'friendly_name' => __('SNMP Get OID Limit'),
			'description' => __('The default maximum number of SNMP Get OIDs to issue per snmpget request.  For Devices, this setting is controlled at the Device level.  You should only use this setting when using Cacti\'s SNMP API natively in your scripts or plugins.'),
			'method' => 'textbox',
			'default' => '10',
			'max_length' => '10',
			'size' => '5'
		),
		'poller_refresh_output_table' => array(
			'friendly_name' => __('Refresh Poller Table Per Cycle'),
			'description' => __('This setting is for a single poller systems only to rebuild the poller output table on each polling cycle to prevent the memory table from swapping on very large systems with large databases that could use swap.'),
			'method' => 'checkbox',
			'default' => '',
		),
		'disable_cache_replication' => array(
			'friendly_name' => __('Disable Resource Cache Replication'),
			'description' => __('By default, the main Cacti Data Collector will cache the entire web site and plugins into a Resource Cache.  Then, periodically the Remote Data Collectors will update themselves with any updates from the main Cacti Data Collector.  This Resource Cache essentially allows Remote Data Collectors to self upgrade.  If you do not wish to use this option, you can disable it using this setting.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'data_collector1_header' => array(
			'friendly_name' => __('Additional Data Collector Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'spine_log_level' => array(
			'friendly_name' => __('Invalid Data Logging'),
			'description' => __('How would you like the Data Collector output errors logged?  Options include: \'Detailed\' which details every error, \'Summary\' which provides the number of output errors per Device, and \'None\', which does not provide error counts.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'  => __('None'),
				'1'  => __('Summary'),
				'2'  => __('Detailed')
			)
		),
		'php_servers' => array(
			'friendly_name' => __('Number of PHP Script Servers'),
			'description' => __('The number of concurrent script server processes to run per spine process.  Settings between 1 and 15 are accepted.  This parameter will help if you are running several threads and script server scripts and is only valid for the spine Data Collector.'),
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
		),
		'script_timeout' => array(
			'friendly_name' => __('Script and Script Server Timeout Value'),
			'description' => __('The maximum time that spine will wait on a script to complete.  This timeout value is in seconds and is only valid for the spine Data Collector.'),
			'method' => 'textbox',
			'default' => '25',
			'max_length' => '10',
			'size' => '5'
		),
		'reindex_header' => array(
			'friendly_name' => __('Periodic All Device Re-Index'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'automatic_reindex' => array(
			'friendly_name' => __('Re-Index All Device Schedule'),
			'description' => __('At what frequency do you wish to Re-Index all Devices.  This Re-Index will occur at midnight at the frequency that you select.'),
			'default' => '0',
			'method' => 'drop_array',
			'array' => array(
				'0' => __('Disabled'),
				'1' => __('Daily'),
				'2' => __('Weekly on Sunday'),
				'3' => __('Monthly on Sunady')
			)
		),
		'timeouts_header' => array(
			'friendly_name' => __('Background Timeout and Concurrent Process Settings'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'reports_timeout' => array(
			'friendly_name' => __('Report Generation Timeout'),
			'description' => __('The maximum amount of time Cacti\'s Reports Generation script can run without generating a timeout error and being killed.'),
			'method' => 'drop_array',
			'default' => '300',
			'array' => array(
				'60'   => __('%s Minute', 1),
				'120'  => __('%s Minutes', 2),
				'300'  => __('%s Minutes', 5),
				'600'  => __('%s Minutes', 10),
				'1200' => __('%s Minutes', 20)
			)
		),
		'dsstats_timeout' => array(
			'friendly_name' => __('Data Source Statistics Timeout'),
			'description' => __('The maximum amount of time Cacti\'s Data Source Statistics script can run without generating a timeout error and being killed.'),
			'method' => 'drop_array',
			'default' => '300',
			'array' => array(
				'60'   => __('%s Minute', 1),
				'120'  => __('%s Minutes', 2),
				'300'  => __('%s Minutes', 5),
				'600'  => __('%s Minutes', 10),
				'1200' => __('%s Minutes', 20),
				'1800' => __('%s Minutes', 30),
				'3600' => __('1 Hour')
			)
		),
		'rrdcheck_timeout' => array(
			'friendly_name' => __('RRDfile Check Timeout'),
			'description' => __('The maximum amount of time Cacti\'s RRDfile check script can run without generating a timeout error and being killed.'),
			'method' => 'drop_array',
			'default' => '300',
			'array' => array(
				'60'   => __('%s Minute', 1),
				'120'  => __('%s Minutes', 2),
				'300'  => __('%s Minutes', 5),
				'600'  => __('%s Minutes', 10),
				'1200' => __('%s Minutes', 20),
				'1800' => __('%s Minutes', 30),
				'3600' => __('1 Hour')
			)
		),
		'commands_timeout' => array(
			'friendly_name' => __('Poller Commands Timeout'),
			'description' => __('The maximum amount of time Cacti\'s Background Commands script can run without generating a timeout error and being killed.  This script will perform tasks such as re-indexing Devices and pruning devices from Remote Data Collectors.'),
			'method' => 'drop_array',
			'default' => '300',
			'array' => array(
				'60'   => __('%s Minute', 1),
				'120'  => __('%s Minutes', 2),
				'300'  => __('%s Minutes', 5),
				'600'  => __('%s Minutes', 10),
				'1200' => __('%s Minutes', 20)
			)
		),
		'commands_processes' => array(
			'friendly_name' => __('Poller Command Concurrent Processes'),
			'description' => __('The number of concurrent Poller Command processes.  The will be at most one concurrent command per host within the Poller Command pool.'),
			'default' => '1',
			'method' => 'drop_array',
			'array' => array(
				1  => __('1 Process'),
				2  => __('%d Processes', 2),
				3  => __('%d Processes', 3),
				4  => __('%d Processes', 4),
				5  => __('%d Processes', 5),
				6  => __('%d Processes', 6),
				7  => __('%d Processes', 7),
				8  => __('%d Processes', 8),
				9  => __('%d Processes', 9),
				10 => __('%d Processes', 10),
				11 => __('%d Processes', 11),
				12 => __('%d Processes', 12),
				13 => __('%d Processes', 13),
				14 => __('%d Processes', 14),
				15 => __('%d Processes', 15),
				16 => __('%d Processes', 16),
				17 => __('%d Processes', 17),
				18 => __('%d Processes', 18),
				19 => __('%d Processes', 19),
				20 => __('%d Processes', 20),
			)
		),
		'maintenance_timeout' => array(
			'friendly_name' => __('Maintenance Background Generation Timeout'),
			'description' => __('The maximum amount of time a Cacti\'s Maintenance script can run without generating a timeout error and being killed.'),
			'method' => 'drop_array',
			'default' => '300',
			'array' => array(
				'60'   => __('%s Minute', 1),
				'120'  => __('%s Minutes', 2),
				'300'  => __('%s Minutes', 5),
				'600'  => __('%s Minutes', 10),
				'1200' => __('%s Minutes', 20),
				'3600' => __('%s Hour', 1)
			)
		),
		'spikekill_timeout' => array(
			'friendly_name' => __('Spikekill Background Generation Timeout'),
			'description' => __('The maximum amount of time a Cacti\'s Spikekill script can run without generating a timeout error and being killed.'),
			'method' => 'drop_array',
			'default' => '3600',
			'array' => array(
				'60'    => __('%s Minute', 1),
				'120'   => __('%s Minutes', 2),
				'300'   => __('%s Minutes', 5),
				'600'   => __('%s Minutes', 10),
				'1200'  => __('%s Minutes', 20),
				'3600'  => __('%s Hour', 1),
				'7200'  => __('%s Hours', 2),
				'14400' => __('%s Hours', 4),
				'28800' => __('%s Hours', 8)
			)
		),
		'data_collector_header' => array(
			'friendly_name' => __('Data Collector Defaults'),
			'description'   => __('These settings are maintained at the Data Collector level.  The values here are only defaults used when first creating a Data Collector.'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'concurrent_processes' => array(
			'friendly_name' => __('Data Collector Processes'),
			'description' => __('The default number of concurrent processes to execute per Data Collector.  NOTE: Starting from Cacti 1.2, this setting is maintained in the Data Collector.  Moving forward, this value is only a preset for the Data Collector.  Using a higher number when using cmd.php will improve performance.  Performance improvements in Spine are best resolved with the threads parameter.  When using Spine, we recommend a lower number and leveraging threads instead.  When using cmd.php, use no more than 2x the number of CPU cores.'),
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
		),
		'max_threads' => array(
			'friendly_name' => __('Threads per Process'),
			'description' => __('The Default Threads allowed per process.  NOTE: Starting in Cacti 1.2+, this setting is maintained in the Data Collector, and this is simply the Preset.  Using a higher number when using Spine will improve performance.  However, ensure that you have enough MySQL/MariaDB connections to support the following equation: connections = data collectors * processes * (threads + script servers).  You must also ensure that you have enough spare connections for user login connections as well.'),
			'method' => 'textbox',
			'default' => '1',
			'max_length' => '10',
			'size' => '5'
		),
	),
	'authentication' => array(
		'auth_header' => array(
			'friendly_name' => __('General'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'auth_method' => array(
			'friendly_name' => __('Authentication Method'),
			'description' => __('<blockquote><i>Built-in Authentication</i> - Cacti handles user authentication, which allows you to create users and give them rights to different areas within Cacti.<br><br><i>Web Basic Authentication</i> - Authentication is handled by the web server. Users can be added or created automatically on first login if the Template User is defined, otherwise the defined guest permissions will be used.<br><br><i>LDAP Authentication</i> - Allows for authentication against a LDAP server. Users will be created automatically on first login if the Template User is defined, otherwise the defined guest permissions will be used.  If PHPs LDAP module is not enabled, LDAP Authentication will not appear as a selectable option.<br><br><i>Multiple LDAP/AD Domain Authentication</i> - Allows administrators to support multiple disparate groups from different LDAP/AD directories to access Cacti resources.  Just as LDAP Authentication, the PHP LDAP module is required to utilize this method.</blockquote>'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => $auth_methods
		),
		'auth_cache_enabled' => array(
			'friendly_name' => __('Support Authentication Cookies'),
			'description' => __('If a user authenticates and selects \'Keep me signed in\', an authentication cookie will be created on the user\'s computer allowing that user to stay logged in.  The authentication cookie expires after 90 days of non-use.'),
			'default' => 'on',
			'method' => 'checkbox'
		),
		'special_users_header' => array(
			'friendly_name' => __('Special Users'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'admin_user' => array(
			'friendly_name' => __('Primary Admin'),
			'description' => __('The name of the primary administrative account that will automatically receive Emails when the Cacti system experiences issues.  To receive these Emails, ensure that your mail settings are correct, and the administrative account has an Email address that is set.'),
			'method' => 'drop_sql',
			'none_value' => __('No User'),
			'sql' => 'SELECT id AS id, username AS name FROM user_auth WHERE realm = 0 ORDER BY username',
			'default' => '1'
		),
		'guest_user' => array(
			'friendly_name' => __('Guest User'),
			'description' => __('The name of the guest user for viewing graphs; is \'No User\' by default.'),
			'method' => 'drop_sql',
			'none_value' => __('No User'),
			'sql' => 'SELECT id AS id, username AS name FROM user_auth WHERE realm = 0 AND id NOT IN (' . $admin_account . ') ORDER BY username',
			'default' => '0'
		),
		'user_template' => array(
			'friendly_name' => __('User Template'),
			'description' => __('The name of the user that Cacti will use as a template for new Web Basic and LDAP users; is \'guest\' by default.  This user account will be disabled from logging in upon being selected.'),
			'method' => 'drop_sql',
			'none_value' => __('No User'),
			'sql' => 'SELECT id AS id, username AS name FROM user_auth WHERE realm = 0 AND id NOT IN (' . $admin_account . ') ORDER BY username',
			'default' => '0'
		),
		'basic_header' => array(
			'friendly_name' => __('Basic Authentication Settings'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'basic_auth_fail_message' => array(
			'friendly_name' => __('Basic Auth Login Failure Message'),
			'description' => __('When using basic authentication, if there are issues mapping the username to a valid Cacti account, the message included here will be displayed to users.  It can include both text and HTML.'),
			'method' => 'textarea',
			'textarea_rows' => '4',
			'textarea_cols' => '80',
			'max_length' => 512
		),
		'path_basic_mapfile' => array(
			'friendly_name' => __('Basic Auth Mapfile'),
			'description' => __('When using basic authentication, if the basic account needs to be translated to a different login account, you can specify a CSV file here to map the basic account to the desired login account.'),
			'method' => 'filepath',
			'file_type' => 'ascii',
			'max_length' => 255,
			'default' => '',
			'install_optional' => true
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
			'description' => __('This will require new passwords to contains both lower and upper-case characters.'),
			'method' => 'checkbox',
			'default' => 'on',
		),
		'secpass_reqnum' => array(
			'friendly_name' => __('Require Number'),
			'description' => __('This will require new passwords to contain at least 1 numerical character.'),
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
				'0'   => __('Disabled'),
				'30'  => __('%d Days', 30),
				'60'  => __('%d Days', 60),
				'90'  => __('%d Days', 90),
				'120' => __('%d Days', 120),
				'365' => __('%d Year', 1),
				'730' => __('%d Years', 2) )
		),
		'secpass_expirepass' => array(
			'friendly_name' => __('Expire Password'),
			'description' => __('This is maximum number of days before a password is set to expire.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0'   => __('Disabled'),
				'30'  => __('%d Days', 30),
				'60'  => __('%d Days', 60),
				'90'  => __('%d Days', 90),
				'120' => __('%d Days', 120)
			)
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
				'0' => __('Disabled'),
				'1' => __('1 Attempt'),
				'2' => __('%d Attempts', 2),
				'3' => __('%d Attempts', 3),
				'4' => __('%d Attempts', 4),
				'5' => __('%d Attempts', 5),
				'6' => __('%d Attempts', 6) )
		),
		'secpass_unlocktime' => array(
			'friendly_name' => __('Auto Unlock'),
			'description' => __('An account will automatically be unlocked after this many minutes.  Even if the correct password is entered, the account will not unlock until this time limit has been met.  Max of 1440 minutes (1 Day)'),
			'method' => 'drop_array',
			'default' => '60',
			'array' => array(
				'0'    => __('Disabled'),
				'1'    => __('1 Minute'),
				'2'    => __('%d Minutes', 2),
				'5'    => __('%d Minutes', 5),
				'10'   => __('%d Minutes', 10),
				'20'   => __('%d Minutes', 20),
				'30'   => __('%d Minutes', 30),
				'60'   => __('1 Hour'),
				'120'  => __('%d Hours', 2),
				'240'  => __('%d Hours', 4),
				'480'  => __('%d Hours', 8),
				'960'  => __('%d Hours', 16),
				'1440' => __('1 Day') )
		),
		'ldap_general_header' => array(
			'friendly_name' => __('LDAP General Settings'),
			'method' => 'spacer'
		),
		'ldap_server' => array(
			'friendly_name' => __('Server(s)'),
			'description' => __('A space delimited list of DNS hostnames or IP address of for valid LDAP servers.  Cacti will attempt to use the LDAP servers from left to right to authenticate a user.'),
			'method' => 'textbox',
			'size' => '80',
			'max_length' => '255'
		),
		'ldap_port' => array(
			'friendly_name' => __('Port Standard'),
			'description' => __('TCP port for Non-SSL communications including LDAP + TLS.  Default is 389.'),
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '389',
			'size' => '5'
		),
		'ldap_port_ssl' => array(
			'friendly_name' => __('Port SSL'),
			'description' => __('TCP port for LDAPS for SSL communications.  Default is 636.'),
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '636',
			'size' => '5'
		),
		'ldap_version' => array(
			'friendly_name' => __('Protocol Version'),
			'description' => __('Protocol Version to connect to the server with.'),
			'method' => 'drop_array',
			'default' => '3',
			'array' => $ldap_versions
		),
		'ldap_network_timeout' => array(
			'friendly_name' => __('Connect Timeout'),
			'description' => __('The Network Connect Timeout in seconds.'),
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '2',
			'size' => '5'
		),
		'ldap_bind_timeout' => array(
			'friendly_name' => __('Bind Timeout'),
			'description' => __('The Bind Timeout in seconds.'),
			'method' => 'textbox',
			'max_length' => '5',
			'default' => '5',
			'size' => '5'
		),
		'ldap_debug' => array(
			'friendly_name' => __('LDAP Debug Mode'),
			'description' => __('If Checked Cacti will log extra LDAP information to the Cacti log during Binding and Searching activities.'),
			'default' => '',
			'method' => 'checkbox'
		),
		'ldap_encryption' => array(
			'friendly_name' => __('Encryption'),
			'description' => __('Encryption that the server supports. NOTE: When using LDAP + TLS you must use version 3.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => $ldap_encryption
		),
		'ldap_tls_certificate' => array(
			'friendly_name' => __('TLS Certificate Requirements'),
			'description' => __('Should LDAP verify TLS Certificates when received by the Client.'),
			'method' => 'drop_array',
			'default' => LDAP_OPT_X_TLS_NEVER,
			'array' => $ldap_tls_cert_req
		),
		'ldap_referrals' => array(
			'friendly_name' => __('Referrals'),
			'description' => __('Enable or Disable LDAP referrals.  If disabled, it may increase the speed of searches.'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array(
				'0' => __('Disabled'),
				'1' => __('Enable')
			)
		),
		'ldap_mode' => array(
			'friendly_name' => __('Mode'),
			'description' => __('Mode which cacti will attempt to authenticate against the LDAP server.<blockquote><i>No Searching</i> - No Distinguished Name (DN) searching occurs, just attempt to bind with the provided Distinguished Name (DN) format.<br><br><i>Anonymous Searching</i> - Attempts to search for username against LDAP directory via anonymous binding to locate the user\'s Distinguished Name (DN).<br><br><i>Specific Searching</i> - Attempts search for username against LDAP directory via Specific Distinguished Name (DN) and Specific Password for binding to locate the user\'s Distinguished Name (DN).'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => $ldap_modes
		),
		'ldap_dn' => array(
			'friendly_name' => __('Distinguished Name (DN)'),
			'description' => __('Distinguished Name syntax, such as for windows: <i>"&lt;username&gt;@win2kdomain.local"</i> or for OpenLDAP: <i>"uid=&lt;username&gt;,ou=people,dc=domain,dc=local"</i>.   "&lt;username&gt" is replaced with the username that was supplied at the login prompt.  This is only used when in "No Searching" mode.'),
			'method' => 'textbox',
			'max_length' => '255',
			'size' => '100'
		),
		'ldap_group_require' => array(
			'friendly_name' => __('Require Group Membership'),
			'description' => __('Require user to be member of group to authenticate. Group settings must be set for this to work, enabling without proper group settings will cause authentication failure.'),
			'default' => '',
			'method' => 'checkbox'
		),
		'ldap_group_header' => array(
			'friendly_name' => __('LDAP Group Settings'),
			'method' => 'spacer'
		),
		'ldap_group_dn' => array(
			'friendly_name' => __('Group Distinguished Name (DN)'),
			'description' => __('Distinguished Name of the group that user must have membership.'),
			'method' => 'textbox',
			'max_length' => '255',
			'size' => '100'
		),
		'ldap_group_attrib' => array(
			'friendly_name' => __('Group Member Attribute'),
			'description' => __('Name of the attribute that contains the usernames of the members.'),
			'method' => 'textbox',
			'max_length' => '255',
			'size' => '100'
		),
		'ldap_group_member_type' => array(
			'friendly_name' => __('Group Member Type'),
			'description' => __('Defines if users use full Distinguished Name or just Username in the defined Group Member Attribute.'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => array(
				1 => __('Distinguished Name'),
				2 => __('Username')
			)
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
			'friendly_name' => __('Search Distinguished Name (DN)'),
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
		'cn_header' => array(
			'friendly_name' => __('LDAP CN Settings'),
			'method' => 'spacer'
		),
		'cn_full_name' => array(
			'friendly_name' => __('Full Name'),
			'description' => __('Field that will replace the Full Name when creating a new user, taken from LDAP. (on windows: displayname) '),
			'method' => 'textbox',
			'max_length' => '255'
		),
		'cn_email' => array(
			'friendly_name' => __('Email'),
			'description' => __('Field that will replace the Email taken from LDAP. (on windows: mail)'),
			'method' => 'textbox',
			'max_length' => '255'
		),
	),
	'mail' => array(
		'settings_web_url' => array(
			'friendly_name' => __('URL Linking'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'base_url' => array(
			'friendly_name' => __('Server Base URL'),
			'description' => __('This is a the server location that will be used for links to the Cacti site. This should include the subdirectory if Cacti does not run from root folder.'),
			'method' => 'textbox',
			'max_length' => 255,
			'size' => '60',
			'default' => 'http://' . gethostname() . $config['url_path']
		),
		'settings_email_header' => array(
			'friendly_name' => __('Emailing Options'),
			'method' => 'spacer',
		),
		'notify_admin' => array(
			'friendly_name' => __('Notify Primary Admin of Issues'),
			'description' => __('In cases where the Cacti server is experiencing problems, should the Primary Administrator be notified by Email?  The Primary Administrator\'s Cacti user account is specified under the Authentication tab on Cacti\'s settings page. It defaults to the \'admin\' account.'),
			'default' => '',
			'method' => 'checkbox'
		),
		'settings_test_email' => array(
			'friendly_name' => __('Test Email'),
			'description' => __('This is a Email account used for sending a test message to ensure everything is working properly.'),
			'method' => 'textbox',
			'max_length' => 255,
		),
		'settings_how' => array(
			'friendly_name' => __('Mail Services'),
			'description' => __('Which mail service to use in order to send mail'),
			'method' => 'drop_array',
			'default' => 0,
			'array' => $mail_methods,
		),
		'settings_ping_mail' => array(
			'friendly_name' => __('Ping Mail Server'),
			'description' => __('Ping the Mail Server before sending test Email?'),
			'method' => 'drop_array',
			'default' => 0,
			'array' => array(
				0 => __('Yes'),
				1 => __('No')
			)
		),
		'settings_from_email' => array(
			'friendly_name' => __('From Email Address'),
			'description' => __('This is the Email address that the Email will appear from.'),
			'method' => 'textbox',
			'max_length' => 255,
		),
		'settings_from_name' => array(
			'friendly_name' => __('From Name'),
			'description' => __('This is the actual name that the Email will appear from.'),
			'method' => 'textbox',
			'max_length' => 255,
		),
		'settings_wordwrap' => array(
			'friendly_name' => __('Word Wrap'),
			'description' => __('This is how many characters will be allowed before a line in the Email is automatically word wrapped. (0 = Disabled)'),
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
			'file_type' => 'binary',
			'max_length' => 255,
			'default' => '',
			'install_optional' => true
		),
		'settings_smtp_header' => array(
			'friendly_name' => __('SMTP Options'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'settings_smtp_host' => array(
			'friendly_name' => __('SMTP Hostname'),
			'description' => __('This is the hostname/IP of the SMTP Server you will send the Email to. For failover, separate your hosts using a semi-colon.'),
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
			'description' => __('The encryption method to use for the Email.'),
			'method' => 'drop_array',
			'array' => array(
				'none' => __('None'),
				'ssl'  => __('SSL'),
				'tls'  => __('TLS')
			),
			'default' => 'none'
		),
		'settings_smtp_timeout' => array(
			'friendly_name' => __('SMTP Timeout'),
			'description' => __('Please enter the SMTP timeout in seconds.'),
			'method' => 'textbox',
			'default' => '10',
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
			'description' => __('The maximum size of the E-Mail message including all attachments.'),
			'method' => 'drop_array',
			'default' => REPORTS_DEFAULT_MAX_SIZE,
			'array' => $attachment_sizes
		),
		'reports_log_verbosity' => array(
			'friendly_name' => __('Poller Logging Level for Cacti Reporting'),
			'description' => __('What level of detail do you want sent to the log file. WARNING: Leaving in any other status than NONE or LOW can exhaust your disk space rapidly.'),
			'method' => 'drop_array',
			'default' => POLLER_VERBOSITY_LOW,
			'array' => $logfile_verbosity,
		),
		'reports_allow_ln' => array(
			'friendly_name' => __('Enable Lotus Notes (R) tweak'),
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
	'boost' => array(
		'boost_hq_header' => array(
			'friendly_name' => __('On-demand RRD Update Settings'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'boost_rrd_update_enable' => array(
			'friendly_name' => __('Enable On-demand RRD Updating'),
			'description' => __('Should Boost enable on demand RRD updating in Cacti?  If you disable, this change will not take affect until after the next polling cycle.  When you have Remote Data Collectors, this settings is required to be on.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'boost_rrd_update_system_enable' => array(
			'friendly_name' => __('System Level RRD Updater'),
			'description' => __('Before RRD On-demand Update Can be Cleared, a poller run must always pass'),
			'method' => 'hidden',
			'default' => ''
		),
		'boost_rrd_update_interval' => array(
			'friendly_name' => __('How Often Should Boost Update All RRDs'),
			'description' => __('When you enable boost, your RRD files are only updated when they are requested by a user, or when this time period elapses.'),
			'default' => '60',
			'method' => 'drop_array',
			'array' => $boost_refresh_interval
		),
		'boost_parallel' => array(
			'friendly_name' => __('Number of Boost Processes'),
			'description' => __('The number of concurrent boost processes to use to use to process all of the RRDs in the boost table.'),
			'default' => '1',
			'method' => 'drop_array',
			'array' => array(
				1  => __('1 Process'),
				2  => __('%d Processes', 2),
				3  => __('%d Processes', 3),
				4  => __('%d Processes', 4),
				5  => __('%d Processes', 5),
				6  => __('%d Processes', 6),
				7  => __('%d Processes', 7),
				8  => __('%d Processes', 8),
				9  => __('%d Processes', 9),
				10 => __('%d Processes', 10),
				11 => __('%d Processes', 11),
				12 => __('%d Processes', 12),
				13 => __('%d Processes', 13),
				14 => __('%d Processes', 14),
				15 => __('%d Processes', 15),
				16 => __('%d Processes', 16),
				17 => __('%d Processes', 17),
				18 => __('%d Processes', 18),
				19 => __('%d Processes', 19),
				20 => __('%d Processes', 20),
			)
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
			'description' => __('To optimize performance, the boost RRD updater needs to know how many Data Source Items should be retrieved in one pass.  Please be careful not to set too high as graphing performance during major updates can be compromised.  If you encounter graphing or polling slowness during updates, lower this number.  The default value is 50000.'),
			'method' => 'drop_array',
			'default' => '50000',
			'array' => $boost_max_rows_per_select
		),
		'boost_rrd_update_string_length' => array(
			'friendly_name' => __('Maximum Argument Length'),
			'description' => __('When boost sends update commands to RRDtool, it must not exceed the operating systems Maximum Argument Length.  This varies by operating system and kernel level.  For example: Windows 2000 <= 2048, FreeBSD <= 65535, Linux 2.6.22-- <= 131072, Linux 2.6.23++ unlimited'),
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
			'description' => __('If the boost poller exceeds this runtime, a warning will be placed in the cacti log,'),
			'method' => 'drop_array',
			'default' => '1200',
			'array' => $boost_max_runtime
		),
		'boost_redirect' => array(
			'friendly_name' => __('Enable direct population of poller_output_boost table'),
			'description' => __('Enables direct insert of records into poller output boost with results in a 25% time reduction in each poll cycle.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'path_boost_log' => array(
			'friendly_name' => __('Boost Debug Log'),
			'description' => __('If this field is non-blank, Boost will log RRDUpdate output from the boost	poller process.'),
			'method' => 'filepath',
			'file_type' => 'ascii',
			'default' => '',
			'max_length' => '255'
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
		)
	),
	'data' => array(
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
		'dsstats_parallel' => array(
			'friendly_name' => __('Number of DSStats Processes'),
			'description' => __('The number of concurrent DSStats processes to use to use to process all of the Data Sources.'),
			'default' => '1',
			'method' => 'drop_array',
			'array' => array(
				1  => __('1 Process'),
				2  => __('%d Processes', 2),
				3  => __('%d Processes', 3),
				4  => __('%d Processes', 4),
				5  => __('%d Processes', 5),
				6  => __('%d Processes', 6),
				7  => __('%d Processes', 7),
				8  => __('%d Processes', 8),
				9  => __('%d Processes', 9),
				10 => __('%d Processes', 10),
				11 => __('%d Processes', 11),
				12 => __('%d Processes', 12),
				13 => __('%d Processes', 13),
				14 => __('%d Processes', 14),
				15 => __('%d Processes', 15),
				16 => __('%d Processes', 16),
				17 => __('%d Processes', 17),
				18 => __('%d Processes', 18),
				19 => __('%d Processes', 19),
				20 => __('%d Processes', 20),
			)
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
			'description' => __('The number of consecutive hours that represent the hourly average.  Keep in mind that a setting too high can result in very large memory tables'),
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
			'friendly_name' => __('Memory Limit for Data Source Statistics Data Collector'),
			'description' => __('The maximum amount of memory for the Cacti Poller and Data Source Statistics Poller'),
			'method' => 'drop_array',
			'default' => '1024',
			'array' => $dsstats_max_memory
		),
		'rrdcheck_hq_header' => array(
			'friendly_name' => __('RRDfile Check'),
			'collapsible' => 'true',
			'method' => 'spacer',
		),
		'rrdcheck_enable' => array(
			'friendly_name' => __('Enable RRDfile Check'),
			'description' => __('Should RRDfile check be enabled for this Cacti system?'),
			'method' => 'checkbox',
			'default' => ''
		),
		'rrdcheck_parallel' => array(
			'friendly_name' => __('Number of RRDfile Check Processes'),
			'description' => __('The number of concurrent RRDfile check processes to use to use to process all of rrd files.'),
			'default' => '1',
			'method' => 'drop_array',
			'array' => array(
				1  => __('1 Process'),
				2  => __('%d Processes', 2),
				3  => __('%d Processes', 3),
				4  => __('%d Processes', 4),
				5  => __('%d Processes', 5),
				6  => __('%d Processes', 6),
				7  => __('%d Processes', 7),
				8  => __('%d Processes', 8),
				9  => __('%d Processes', 9),
				10 => __('%d Processes', 10),
				11 => __('%d Processes', 11),
				12 => __('%d Processes', 12),
				13 => __('%d Processes', 13),
				14 => __('%d Processes', 14),
				15 => __('%d Processes', 15),
				16 => __('%d Processes', 16),
				17 => __('%d Processes', 17),
				18 => __('%d Processes', 18),
				19 => __('%d Processes', 19),
				20 => __('%d Processes', 20),
			)
		),
		'rrdcheck_interval' => array(
			'friendly_name' => __('Check Frequency'),
			'description' => __('How frequent should RRD be checked ?'),
			'default' => '240',
			'method' => 'drop_array',
			'array' => $rrdcheck_intervals
		),
		'storage_header' => array(
			'friendly_name' => __('Data Storage Settings'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'storage_location' => array(
			'friendly_name' => __('Location'),
			'description' => __('Choose if RRDs will be stored locally or being handled by an external RRDtool proxy server.'),
			'method' => 'drop_array',
			'default' => 0,
			'array' => array (
				__('Local'),
				__('RRDtool Proxy Server')
			)
		),
		'extended_paths_header' => array(
			'friendly_name' => __('Structure RRDfile Paths'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'extended_paths' => array(
			'friendly_name' => __('Enable Structured Paths'),
			'description' => __('Use a separate subfolder for each hosts RRD files.  The naming of the RRDfiles will be one of the following:<br><ul><li>&lt;path_cacti&gt;/rra/host_id/local_data_id.rrd,</li><li>&lt;path_cacti&gt;/rra/device_id/data_query_id/local_data_id.rrd,</li><li>&lt;path_cacti&gt;/rra/device_hash/device_id/local_data_id.rrd,</li><li>&lt;path_cacti&gt;/rra/device_hash/device_id/data_query_id/local_data_id.rrd.</li></ul><br>You can make this change after install by running the CLI script <b>structure_rra_paths.php</b> after you make the change.  NOTE: If you change Max Directories value to decrease the number of directories, or if you change the Directory Pattern, empty directories will not be pruned after you rerun the <b>structure_rra_paths.php</b> script.'),
			'method' => 'checkbox'
		),
		'extended_paths_type' => array(
			'friendly_name' => __('Directory Pattern'),
			'description' => __('Which Directory Pattern do you wish to use for Structured RRD Paths.  \'Device ID\' is the default.  The setting \'Device ID/Data Query ID\' should be used when you have Devices with thousands of Graphs.  After Changing the Directory Pattern, you must run the Structured Path CLI script again to modify the RRDfile paths to the new Pattern.'),
			'method' => 'drop_array',
			'default' => 'device',
			'array' => array (
				'device' => __('Device ID'),
				'device_dq' => __('Device ID/Data Query ID'),
				'hash_device' => __('Device Hash/Device ID'),
				'hash_device_dq' => __('Device Hash/Device ID/Data Query ID')
			)
		),
		'extended_paths_hashes' => array(
			'friendly_name' => __('Max Device Hash Directories'),
			'description' => __('The maximum number of Device Directories to be created based upon hashed Device ID\'s.'),
			'method' => 'textbox',
			'max_length' => '5',
			'size' => '5',
			'default' => '100'
		),
		'rrdp_header' => array(
			'friendly_name' => __('RRDtool Proxy Server'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'rrdp_server' => array(
			'friendly_name' => __('Proxy Server'),
			'description' => __('The DNS hostname or IP address of the RRDtool proxy server.'),
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
		'rrdp_load_balancing' => array(
			'friendly_name' => __('Load Balancing'),
			'description' => __('If both main and backup proxy are receivable this option allows to spread all requests against RRDtool.'),
			'method' => 'checkbox'
		),
		'rrdp_server_backup' => array(
			'friendly_name' => __('Proxy Server'),
			'description' => __('The DNS hostname or IP address of the RRDtool backup proxy server if proxy is running in MSR mode.'),
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
			'collapsible' => 'true'
		),
		'spikekill_method' => array(
			'friendly_name' => __('Removal Method'),
			'description' => __('There are two removal methods.  The first, Standard Deviation, will remove any sample that is X number of standard deviations away from the average of samples.  The second method, Variance, will remove any sample that is X% more than the Variance average.  The Variance method takes into account a certain number of \'outliers\'.  Those are exceptional samples, like the spike, that need to be excluded from the Variance Average calculation.'),
			'method' => 'drop_array',
			'default' => '2',
			'array' => array(
				1 => __('Standard Deviation'),
				2 => __('Variance Based w/Outliers Removed')
			)
		),
		'spikekill_avgnan' => array(
			'friendly_name' => __('Replacement Method'),
			'description' => __('There are three replacement methods.  The first method replaces the spike with the average of the data source in question.  The second method replaces the spike with a \'NaN\'.  The last replaces the spike with the last known good value found.'),
			'method' => 'drop_array',
			'default' => 'last',
			'array' => array(
				'avg'  => __('Average'),
				'nan'  => __('NaN\'s'),
				'last' => __('Last Known Good'),
			)
		),
		'spikekill_deviations' => array(
			'friendly_name' => __('Number of Standard Deviations'),
			'description' => __('Any value that is this many standard deviations above the average will be excluded.  A good number will be dependent on the type of data to be operated on.  We recommend a number no lower than 5 Standard Deviations.'),
			'method' => 'drop_array',
			'default' => '10',
			'array' => array(
				3 => __('%d Standard Deviations', 3),
				4 => __('%d Standard Deviations', 4),
				5 => __('%d Standard Deviations', 5),
				6 => __('%d Standard Deviations', 6),
				7 => __('%d Standard Deviations', 7),
				8 => __('%d Standard Deviations', 8),
				9 => __('%d Standard Deviations', 9),
				10 => __('%d Standard Deviations', 10),
				15 => __('%d Standard Deviations', 15),
				20 => __('%d Standard Deviations', 20),
				50 => __('%d Standard Deviations', 50),
				100 => __('%d Standard Deviations', 100),
				200 => __('%d Standard Deviations', 200),
			)
		),
		'spikekill_percent' => array(
			'friendly_name' => __('Variance Percentage'),
			'description' => __('This value represents the percentage above the adjusted sample average once outliers have been removed from the sample.  For example, a Variance Percentage of 100%% on an adjusted average of 50 would remove any sample above the quantity of 100 from the graph.'),
			'method' => 'drop_array',
			'default' => '1000',
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
				1000 => '1000 %',
				2000 => '2000 %',
				3000 => '3000 %',
				10000 => '10000 %',
				50000 => '50000 %',
				100000 => '100000 %',
				500000 => '500000 %',
			)
		),
		'spikekill_outliers' => array(
			'friendly_name' => __('Variance Number of Outliers'),
			'description' => __('This value represents the number of high and low average samples will be removed from the sample set prior to calculating the Variance Average.  If you choose an outlier value of 5, then both the top and bottom 5 averages are removed.'),
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
				20 => __('%d High/Low Samples', 20),
				50 => __('%d High/Low Samples', 50),
				100 => __('%d High/Low Samples', 100),
				500 => __('%d High/Low Samples', 500),
				1000 => __('%d High/Low Samples', 1000),
			)
		),
		'spikekill_number' => array(
			'friendly_name' => __('Max Kills Per RRA'),
			'description' => __('This value represents the maximum number of spikes to remove from a Graph RRA.'),
			'method' => 'drop_array',
			'default' => '5',
			'array' => array(
				1  => __('%d Spikes', 1),
				2  => __('%d Spikes', 2),
				3  => __('%d Spikes', 3),
				4  => __('%d Spikes', 4),
				5  => __('%d Spikes', 5),
				6  => __('%d Spikes', 6),
				7  => __('%d Spikes', 7),
				8  => __('%d Spikes', 8),
				9  => __('%d Spikes', 9),
				10 => __('%d Spikes', 10),
				50 => __('%d Spikes', 50),
				100 => __('%d Spikes', 100),
			)
		),
		'spikekill_backupdir' => array(
			'friendly_name' => __('RRDfile Backup Directory'),
			'description' => __('If this directory is not empty, then your original RRDfiles will be backed up to this location.'),
			'method' => 'dirpath',
			'default' => $config['base_path'] . '/cache/spikekill/',
			'max_length' => '255',
			'size' => '60'
		),
		'spikekill_batch_header' => array(
			'friendly_name' => __('Batch Spike Kill Settings'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'spikekill_batch' => array(
			'friendly_name' => __('Removal Schedule'),
			'description' => __('Do you wish to periodically remove spikes from your graphs?  If so, select the frequency below.'),
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
			'description' => __('The Base Time for Spike removal to occur.  For example, if you use \'12:00am\' and you choose once per day, the batch removal would begin at approximately midnight every day.'),
			'method' => 'textbox',
			'default' => '12:00am',
			'max_length' => '10',
			'size' => '10'
		),
		'spikekill_templates' => array(
			'friendly_name' => __('Graph Templates to Spike Kill'),
			'method' => 'drop_multi',
			'description' => __('When performing batch spike removal, only the templates selected below will be acted on.'),
			'array' => array()
		),
		'spikekill_purge' => array(
			'friendly_name' => __('Backup Retention'),
			'description' => __('When SpikeKill kills spikes in graphs, it makes a backup of the RRDfile.  How long should these backup files be retained?'),
			'method' => 'drop_array',
			'default' => '7856352',
			'none_value' => __('Never'),
			'array' => $timespans
		)
	)
);

$settings_user = array(
	'general' => array(
		'selected_theme' => array(
			'friendly_name' => __('Theme'),
			'description' => __('Please select one of the available Themes to skin your Cacti with.'),
			'method' => 'drop_array',
			'default' => read_config_option('selected_theme'),
			'array' => $themes
		),
		'default_view_mode' => array(
			'friendly_name' => __('Default View Mode'),
			'description' => __('Which Graph mode you want displayed by default when you first visit the Graphs page?'),
			'method' => 'drop_array',
			'array' => $graph_views,
			'default' => '1'
		),
		'client_timezone_support' => array(
			'friendly_name' => __('TimeZone Support'),
			'description' => __('How would you like Cacti to present dates?  This setting will also change the way that Cacti Graphs dates are represented.'),
			'method' => 'drop_array',
			'array' => array(
				'0' => __('Cacti Server Timezone'),
				'1' => __('My Browsers Timezone')
			),
			'default' => '0'
		),
		'user_language' => array(
			'friendly_name' => __('User Language'),
			'description' => __('Defines the preferred GUI language.'),
			'method' => 'drop_language',
			'default' => get_new_user_default_language(),
			'array' => get_installed_locales()
		),
		'show_graph_title' => array(
			'friendly_name' => __('Show Graph Title'),
			'description' => __('Display the graph title on the page so that it may be searched using the browser.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'hide_disabled' => array(
			'friendly_name' => __('Hide Disabled'),
			'description' => __('Hides Disabled Devices and Graphs when viewing outside of Console tab.'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'show_aggregates' => array(
			'friendly_name' => __('Show Device Aggregates'),
			'description' => __('If a Device Data Source is included in an Aggregate Graph, show that Graph along with other Device Graphs'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'enable_hscroll' => array(
			'friendly_name' => __('Enable Horizontal Scrolling'),
			'description' => __('Enable Horizontal Scrolling of Tables, Disabling Responsive Column Visibility.'),
			'method' => 'checkbox',
			'default' => ''
		),
		'default_date_format' => array(
			'friendly_name' => __('Date Display Format'),
			'description' => __('The date format to use in Cacti.'),
			'method' => 'drop_array',
			'array' => $dateformats,
			'default' => read_config_option('default_date_format')
		),
		'default_datechar' => array(
			'friendly_name' => __('Date Separator'),
			'description' => __('The date separator to be used in Cacti.'),
			'method' => 'drop_array',
			'array' => $datechar,
			'default' => read_config_option('default_datechar')
		),
		'page_refresh' => array(
			'friendly_name' => __('Page Refresh'),
			'description' => __('The number of seconds between automatic page refreshes.'),
			'method' => 'drop_array',
			'default' => '300',
			'array' => array(
				'15'  => __('%d Seconds', 15),
				'20'  => __('%d Seconds', 20),
				'30'  => __('%d Seconds', 30),
				'60'  => __('1 Minute'),
				'300' => __('%d Minutes', 5)
			)
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
			'friendly_name' => __('Default Timespan'),
			'description' => __('The default Timespan displayed when viewing Graphs and other time specific data.'),
			'method' => 'drop_array',
			'array' => $graph_timespans,
			'default' => GT_LAST_DAY
		),
		'default_timeshift' => array(
			'friendly_name' => __('Default Timeshift'),
			'description' => __('The default Timeshift displayed when viewing Graphs and other time specific data.'),
			'method' => 'drop_array',
			'array' => $graph_timeshifts,
			'default' => GTS_1_DAY
		),
		'allow_graph_dates_in_future' => array(
			'friendly_name' => __('Allow Graph to extend to Future'),
			'description' => __('When displaying Graphs, allow Graph Dates to extend \'to future\''),
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
		)
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
			'array' => array(
				'1' => __('1 Column'),
				'2' => __('%d Columns', 2),
				'3' => __('%d Columns', 3),
				'4' => __('%d Columns', 4),
				'5' => __('%d Columns', 5),
				'6' => __('%d Columns', 6)
			)
		),
		'num_columns_tree' => array(
			'friendly_name' => __('Tree View Thumbnail Columns'),
			'description' => __('The number of columns to use when displaying Thumbnail graphs in Tree mode.'),
			'method' => 'drop_array',
			'default' => '2',
			'array' => array(
				'1' => __('1 Column'),
				'2' => __('%d Columns', 2),
				'3' => __('%d Columns', 3),
				'4' => __('%d Columns', 4),
				'5' => __('%d Columns', 5),
				'6' => __('%d Columns', 6)
			)
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
		)
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
		),
		'tree_history' => array(
			'friendly_name' => __('Tree History'),
			'description' => __('If enabled, Cacti will remember your Tree History between logins and when you return to the Graphs page.'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'min_tree_width' => array(
			'friendly_name' => __('Minimum Tree Width'),
			'description' => __('The Minimum width of the Tree to contract to.'),
			'method' => 'textbox',
			'default' => read_config_option('min_tree_width'),
			'max_length' => '5',
			'size' => '7'
		),
		'max_tree_width' => array(
			'friendly_name' => __('Maximum Tree Width'),
			'description' => __('The Maximum width of the Tree to expand to, after which time, Tree branches will scroll on the page.'),
			'method' => 'textbox',
			'default' => read_config_option('max_tree_width'),
			'max_length' => '5',
			'size' => '7'
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

if ($config['cacti_server_os'] == 'win32') {
	unset($settings['mail']['settings_sendmail_header']);
	unset($settings['mail']['settings_sendmail_path']);
}

if (!$config['is_web'] || is_realm_allowed(25)) {
	$settings_user['general']['realtime_mode'] = array(
		'friendly_name' => __('Realtime View Mode'),
		'description' => __('How do you wish to view Realtime Graphs?'),
		'method' => 'drop_array',
		'array' => array(
			'1' => __('Inline'),
			'2' => __('New Window')
		),
		'default' => '1'
	);
}

if (!$config['is_web'] || is_realm_allowed(8)) {
	if (read_config_option('auth_method') != 2 && !(read_config_option('auth_cache_enabled') == 'on' && isset($_SESSION['cacti_remembers']) && $_SESSION['cacti_remembers'] == true)) {
		if (ini_get('session.gc_maxlifetime') > '2147483') {
			$max_life = '2147483';
		} else {
			$max_life = ini_get('session.gc_maxlifetime');
		}

		$auto_array = array(
			'-1'      => __('System Setting'),
			'900'     => __('%d Minutes', 15),
			'1200'    => __('%d Minutes', 20),
			'1800'    => __('%d Minutes', 30),
			'3600'    => __('1 Hour'),
			'21600'   => __('%d Hours', 6),
			'43200'   => __('%d Hours', 12),
			'86400'   => __('1 Day'),
			'604800'  => __('1 Week'),
			'1209600' => __('%d Weeks', 2),
			'1814400' => __('%d Weeks', 3),
			'2147483' => __('%d Weeks', 4)
		);

		foreach($auto_array as $key => $value) {
			if ($key > $max_life) {
				unset($auto_array[$key]);
			}
		}

		$settings_user['general']['user_auto_logout_time'] = array(
			'friendly_name' => __('Auto Log Out Time'),
			'description' => __('For users with Console access only, how long this user can stay logged in before being automatically logged out.  Note that if you have not been active in more than an hour, you may have to refresh your browser.  Also note that this setting has no affect when Authentication Cookies are enabled.'),
			'method' => 'drop_array',
			'default' => $max_life,
			'array' => $auto_array
		);
	}
}

api_plugin_hook('config_settings');

