<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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

if (!defined('VALID_HOST_FIELDS')) {
	$string = api_plugin_hook_function('valid_host_fields', '(hostname|host_id|location|snmp_community|snmp_username|snmp_password|snmp_auth_protocol|snmp_priv_passphrase|snmp_priv_protocol|snmp_context|snmp_engine_id|snmp_version|snmp_port|snmp_timeout|external_id)');
	define('VALID_HOST_FIELDS', $string);
}
$valid_host_fields = VALID_HOST_FIELDS;

/* If you update this, check that you have updated the installer */
$fields_snmp_item = array(
	'snmp_version' => array(
		'method' => 'drop_array',
		'friendly_name' => __('SNMP Version'),
		'description' => __('Choose the SNMP version for this host.'),
		'on_change' => 'setSNMP()',
		'value' => '|arg1:snmp_version|',
		'default' => read_config_option('snmp_version'),
		'array' => $snmp_versions
		),
	'snmp_community' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Community String'),
		'description' => __('Fill in the SNMP read community for this device.'),
		'value' => '|arg1:snmp_community|',
		'default' => read_config_option('snmp_community'),
		'max_length' => '100',
		'size' => '20'
		),
	'snmp_security_level' => array(
		'method' => 'drop_array',
		'friendly_name' => __('SNMP Security Level'),
		'description' => __('SNMP v3 Security Level to use when querying the device.'),
		'on_change' => 'setSNMP()',
		'value' => '|arg1:snmp_security_level|',
		'form_id' => '|arg1:id|',
		'default' => read_config_option('snmp_security_level'),
		'array' => $snmp_security_levels
		),
	'snmp_auth_protocol' => array(
		'method' => 'drop_array',
		'friendly_name' => __('SNMP Auth Protocol (v3)'),
		'description' => __('Choose the SNMPv3 Authorization Protocol.'),
		'on_change' => 'setSNMP()',
		'value' => '|arg1:snmp_auth_protocol|',
		'default' => read_config_option('snmp_auth_protocol'),
		'array' => $snmp_auth_protocols,
		),
	'snmp_username' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Username (v3)'),
		'description' => __('SNMP v3 username for this device.'),
		'value' => '|arg1:snmp_username|',
		'default' => read_config_option('snmp_username'),
		'max_length' => '50',
		'size' => '40'
		),
	'snmp_password' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('SNMP Password (v3)'),
		'description' => __('SNMP v3 password for this device.'),
		'value' => '|arg1:snmp_password|',
		'default' => read_config_option('snmp_password'),
		'max_length' => '50',
		'size' => '40'
		),
	'snmp_priv_protocol' => array(
		'method' => 'drop_array',
		'friendly_name' => __('SNMP Privacy Protocol (v3)'),
		'description' => __('Choose the SNMPv3 Privacy Protocol.'),
		'on_change' => 'setSNMP()',
		'value' => '|arg1:snmp_priv_protocol|',
		'default' => read_config_option('snmp_priv_protocol'),
		'array' => $snmp_priv_protocols,
		),
	'snmp_priv_passphrase' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('SNMP Privacy Passphrase (v3)'),
		'description' => __('Choose the SNMPv3 Privacy Passphrase.'),
		'value' => '|arg1:snmp_priv_passphrase|',
		'default' => read_config_option('snmp_priv_passphrase'),
		'max_length' => '200',
		'size' => '80'
		),
	'snmp_context' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Context (v3)'),
		'description' => __('Enter the SNMP Context to use for this device.'),
		'value' => '|arg1:snmp_context|',
		'default' => '',
		'max_length' => '64',
		'size' => '40'
		),
	'snmp_engine_id' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Engine ID (v3)'),
		'description' => __('Enter the SNMP v3 Engine Id to use for this device. Leave this field empty to use the SNMP Engine ID being defined per SNMPv3 Notification receiver.'),
		'value' => '|arg1:snmp_engine_id|',
		'default' => '',
		'max_length' => '64',
		'size' => '40'
		),
	'snmp_port' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Port'),
		'description' => __('Enter the UDP port number to use for SNMP (default is 161).'),
		'value' => '|arg1:snmp_port|',
		'max_length' => '5',
		'default' => read_config_option('snmp_port'),
		'size' => '12'
		),
	'snmp_timeout' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Timeout'),
		'description' => __('The maximum number of milliseconds Cacti will wait for an SNMP response (does not work with php-snmp support).'),
		'value' => '|arg1:snmp_timeout|',
		'max_length' => '8',
		'default' => read_config_option('snmp_timeout'),
		'size' => '12'
		),
	);

$fields_snmp_item_with_oids = $fields_snmp_item + array(
	'max_oids' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Maximum OIDs Per Get Request'),
		'description' => __('The number of SNMP OIDs that can be obtained in a single SNMP Get request.'),
		'value' => '|arg1:max_oids|',
		'default' => read_config_option('max_get_size'),
		'array' => array(
			1  => __('%d OID', 1),
			2  => __('%d OID\'s', 2),
			3  => __('%d OID\'s', 3),
			4  => __('%d OID\'s', 4),
			5  => __('%d OID\'s', 5),
			10 => __('%d OID\'s', 10),
			15 => __('%d OID\'s', 15),
			20 => __('%d OID\'s', 20),
			25 => __('%d OID\'s', 25),
			30 => __('%d OID\'s', 30),
			35 => __('%d OID\'s', 35),
			40 => __('%d OID\'s', 40),
			45 => __('%d OID\'s', 45),
			50 => __('%d OID\'s', 50),
			55 => __('%d OID\'s', 55),
			60 => __('%d OID\'s', 60)
		)
	),
	'bulk_walk_size' => array(
		'method'        => 'drop_array',
		'friendly_name' => __('Bulk Walk Maximum Repetitions'),
		'description'   => __('For SNMPv2 and SNMPv3 Devices, the SNMP Bulk Walk max-repetitions size. The default is to \'Auto Detect on Re-Index\'. For very large switches, high performance servers, Jumbo Frame Networks or for high latency WAN connections, increasing this value may increase poller performance. More data is packed into a single SNMP packet which can reduce data query run time. However, some devices may completely refuse to respond to packets with a max-repetition size which is set too large. This can be especially true for lower-powered IoT type devices or smaller embedded IT appliances. Special attention to the overall network path MTU should also be considered since setting a value which is too high could lead to packet fragmentation.'),
		'value'         => '|arg1:bulk_walk_size|',
		'default'       => '-1',
		'array' => array(
			-1 => __('Auto Detect on Re-Index'),
			0  => __('Auto Detect/Set on first Re-Index'),
			1  => __('%d Repitition', 1),
			2  => __('%d Repetitions', 2),
			3  => __('%d Repetitions', 3),
			4  => __('%d Repetitions', 4),
			5  => __('%d Repetitions', 5),
			10 => __('%d Repetitions', 10),
			15 => __('%d Repetitions', 15),
			20 => __('%d Repetitions', 20),
			25 => __('%d Repetitions', 25),
			30 => __('%d Repetitions', 30),
			35 => __('%d Repetitions', 35),
			40 => __('%d Repetitions', 40),
			45 => __('%d Repetitions', 45),
			50 => __('%d Repetitions', 50),
			55 => __('%d Repetitions', 55),
			60 => __('%d Repetitions', 60)
		)
	)
);

$fields_snmp_item_with_retry = $fields_snmp_item_with_oids + array(
	'snmp_retries' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Retries'),
		'description' => __('The maximum number of attempts to reach a device via an SNMP readstring prior to giving up.'),
		'value' => '|arg1:snmp_retries|',
		'max_length' => '8',
		'default' => read_config_option('snmp_retries'),
		'size' => '12'
		),
	);

/* file: profiles.php, action: edit */
$fields_profile_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A useful name for this Data Storage and Polling Profile.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80',
		'default' => __('New Profile')
		),
	'step' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Polling Interval'),
		'description' => __('The frequency that data will be collected from the Data Source?'),
		'array' => $sampling_intervals,
		'value' => '|arg1:step|',
		'default' => read_config_option('poller_interval'),
		),
	'heartbeat' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Heartbeat'),
		'description' => __('How long can data be missing before RRDtool records unknown data.  Increase this value if your Data Source is unstable and you wish to carry forward old data rather than show gaps in your graphs.  This value is multiplied by the X-Files Factor to determine the actual amount of time.'),
		'array' => $heartbeats,
		'value' => '|arg1:heartbeat|',
		'default' => (read_config_option('poller_interval') * 2),
		),
	'x_files_factor' => array(
		'method' => 'textbox',
		'friendly_name' => __('X-Files Factor'),
		'description' => __('The amount of unknown data that can still be regarded as known.'),
		'value' => '|arg1:x_files_factor|',
		'max_length' => '10',
		'size' => '7',
		'default' => '0.5'
		),
	'consolidation_function_id' => array(
		'method' => 'drop_multi',
		'friendly_name' => __('Consolidation Functions'),
		'description' => __('How data is to be entered in RRAs.'),
		'array' => $consolidation_functions,
		'sql' => 'SELECT consolidation_function_id AS id, data_source_profile_id FROM data_source_profiles_cf WHERE data_source_profile_id="|arg1:id|"',
		),
	'default' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Default'),
		'description' => __('Is this the default storage profile?'),
		'value' => '|arg1:default|',
		'default' => '',
		),
	'size' => array(
		'method' => 'other',
		'friendly_name' => __('RRDfile Size (in Bytes)'),
		'description' => __('Based upon the number of Rows in all RRAs and the number of Consolidation Functions selected, the size of this entire in the RRDfile.'),
		'value' => ''
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'save_component_profile' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: rra.php, action: edit */
$fields_profile_rra_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('How data is to be entered in RRAs.'),
		'value' => '|arg1:name|',
		'max_length' => '100',
		'size' => '60',
		'default' => __('New Profile RRA')
		),
	'steps' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Aggregation Level'),
		'description' => __('The number of samples required prior to filling a row in the RRA specification.  The first RRA should always have a value of 1.'),
		'array' => $aggregation_levels,
		'value' => '|arg1:steps|',
		'default' => read_config_option('poller_interval'),
		),
	'rows' => array(
		'method' => 'textbox',
		'friendly_name' => __('Rows'),
		'description' => __('How many generations data is kept in the RRA.'),
		'value' => '|arg1:rows|',
		'max_length' => '12',
		'size' => '10',
		'default' => '600'
		),
	'timespan' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Default Timespan'),
		'description' => __('When viewing a Graph based upon the RRA in question, the default Timespan to show for that Graph.'),
		'value' => '|arg1:timespan|',
		'array' => $timespans
		),
	'retention' => array(
		'method' => 'other',
		'friendly_name' => __('Data Retention'),
		'description' => __('Based upon the Aggregation Level, the Rows, and the Polling Interval the amount of data that will be retained in the RRA'),
		'value' => ''
		),
	'size' => array(
		'method' => 'other',
		'friendly_name' => __('RRA Size (in Bytes)'),
		'description' => __('Based upon the number of Rows and the number of Consolidation Functions selected, the size of this RRA in the RRDfile.'),
		'value' => ''
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'save_component_rra' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: cdef.php, action: edit */
$fields_cdef_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A useful name for this CDEF.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80'
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'save_component_cdef' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: color.php, action: edit */
$fields_color_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('The name of this Color.'),
		'value' => '|arg1:name|',
		'max_length' => '40',
		'size' => '40'
		),
	'hex' => array(
		'method' => 'textbox',
		'friendly_name' => __('Hex Value'),
		'description' => __('The hex value for this color; valid range: 000000-FFFFFF.'),
		'value' => '|arg1:hex|',
		'max_length' => '6',
		'size' => '5'
		),
	'read_only' => array(
		'method' => 'hidden',
		'friendly_name' => __('Read Only'),
		'description' => __('Any named color should be read only.'),
		'value' => '|arg1:read_only|',
		'default' => ''
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'hidden_name' => array(
		'method' => 'hidden',
		'value' => '|arg1:name|',
		'max_length' => '40',
		'size' => '40'
		),
	'save_component_color' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: data_input.php, action: edit */
$fields_data_input_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('Enter a meaningful name for this data input method.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80'
		),
	'type_id' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Input Type'),
		'description' => __('Choose the method you wish to use to collect data for this Data Input method.'),
		'value' => '|arg1:type_id|',
		'array' => $input_types_script,
		),
	'input_string' => array(
		'method' => 'textarea',
		'friendly_name' => __('Input String'),
		'description' => __('The data that is sent to the script, which includes the complete path to the script and input sources in &lt;&gt; brackets.'),
		'value' => '|arg1:input_string|',
		'textarea_rows' => '4',
		'textarea_cols' => '60',
		'class' => 'textAreaNotes',
		'max_length' => '255',
		),
	'whitelist_verification' => array(
		'method' => 'other',
		'value' => '',
		'friendly_name' => __('White List Check'),
		'description' => __('The result of the Whitespace verification check for the specific Input Method.  If the Input String changes, and the Whitelist file is not update, Graphs will not be allowed to be created.')
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'save_component_data_input' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: data_input.php, action: field_edit (dropdown) */
$fields_data_input_field_edit_1 = array(
	'data_name' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Field [%s]', '|arg1:|'),
		'description' => __('Choose the associated field from the %s field.', '|arg1:|'),
		'value' => '|arg3:data_name|',
		'array' => '|arg2:|',
		)
	);

/* file: data_input.php, action: field_edit (textbox) */
$fields_data_input_field_edit_2 = array(
	'data_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Field [%s]', '|arg1:|'),
		'description' => __('Enter a name for this %s field.  Note: If using name value pairs in your script, for example: NAME:VALUE, it is important that the name match your output field name identically to the script output name or names.', '|arg1:|'),
		'value' => '|arg2:data_name|',
		'max_length' => '50',
		'size' => '40'
		)
	);

/* file: data_input.php, action: field_edit */
$fields_data_input_field_edit = array(
	'fname' => array(
		'method' => 'textbox',
		'friendly_name' => __('Friendly Name'),
		'description' => __('Enter a meaningful name for this data input method.'),
		'value' => '|arg1:name|',
		'max_length' => '200',
		'size' => '80'
		),
	'update_rra' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Update RRDfile'),
		'description' => __('Whether data from this output field is to be entered into the RRDfile.'),
		'value' => '|arg1:update_rra|',
		'default' => 'on',
		'form_id' => '|arg1:id|'
		),
	'regexp_match' => array(
		'method' => 'textbox',
		'friendly_name' => __('Regular Expression Match'),
		'description' => __('If you want to require a certain regular expression to be matched against input data, enter it here (preg_match format).'),
		'value' => '|arg1:regexp_match|',
		'max_length' => '200',
		'size' => '80'
		),
	'allow_nulls' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Allow Empty Input'),
		'description' => __('Check here if you want to allow NULL input in this field from the user.'),
		'value' => '|arg1:allow_nulls|',
		'default' => '',
		'form_id' => false
		),
	'type_code' => array(
		'method' => 'textbox',
		'friendly_name' => __('Special Type Code'),
		'description' => __('If this field should be treated specially by host templates, indicate so here. Valid keywords for this field are %s', str_replace(")", "'", str_replace("(", "'", str_replace("|", ", ", $valid_host_fields))) ),
		'value' => '|arg1:type_code|',
		'max_length' => '40',
		'size' => '20'
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'input_output' => array(
		'method' => 'hidden',
		'value' => '|arg2:|'
		),
	'sequence' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:sequence|'
		),
	'data_input_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg3:data_input_id|'
		),
	'save_component_field' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: data_templates.php, action: template_edit */
$fields_data_template_template_edit = array(
	'template_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('The name given to this data template.'),
		'value' => '|arg1:name|',
		'max_length' => '150',
		'size' => '80'
		),
	'data_template_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg2:data_template_id|'
		),
	'data_template_data_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg2:id|'
		),
	'current_rrd' => array(
		'method' => 'hidden_zero',
		'value' => '|arg3:view_rrd|'
		),
	'save_component_template' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: (data_sources.php|data_templates.php), action: (ds|template)_edit */

if (db_table_exists('data_source_profiles')) {
	$def_profile = db_fetch_cell('SELECT id
		FROM data_source_profiles
		ORDER BY `default`
		DESC LIMIT 1');
} else {
	$def_profile = '1';
}

$struct_data_source = array(
	'name' => array(
		'friendly_name' => __('Name'),
		'method' => 'textbox',
		'max_length' => '250',
		'size' => '80',
		'default' => '',
		'description' => __('Choose a name for this data source.  It can include replacement variables such as |host_description| or |query_fieldName|.  For a complete list of supported replacement tags, please see the Cacti documentation.'),
		'flags' => ''
		),
	'data_source_path' => array(
		'friendly_name' => __('Data Source Path'),
		'method' => 'textbox',
		'max_length' => '255',
		'size' => '80',
		'default' => '',
		'description' => __('The full path to the RRDfile.'),
		'flags' => 'NOTEMPLATE'
		),
	'data_input_id' => array(
		'friendly_name' => __('Data Input Method'),
		'method' => 'drop_sql',
		'sql' => 'SELECT id, name FROM data_input ORDER BY name',
		'default' => '',
		'none_value' => __('None'),
		'description' => __('The script/source used to gather data for this data source.'),
		'flags' => 'ALWAYSTEMPLATE'
		),
	'data_source_profile_id' => array(
		'friendly_name' => __('Data Source Profile'),
		'method' => 'drop_sql',
		'description' => __('Select the Data Source Profile.  The Data Source Profile controls polling interval, the data aggregation, and retention policy for the resulting Data Sources.'),
		'sql' => 'SELECT "0" AS id, "' . __('External') . '" AS name UNION SELECT id, name FROM data_source_profiles ORDER BY name',
		'default' => $def_profile,
		'flags' => ''
		),
	'rrd_step' => array(
		'friendly_name' => __('Step'),
		'method' => 'hidden',
		'max_length' => '10',
		'size' => '10',
		'default' => '300',
		'description' => __('The amount of time in seconds between expected updates.'),
		'flags' => ''
		),
	'active' => array(
		'friendly_name' => __('Data Source Active'),
		'method' => 'checkbox',
		'default' => 'on',
		'description' => __('Whether Cacti should gather data for this data source or not.'),
		'flags' => ''
		)
	);

/* file: (data_sources.php|data_templates.php), action: (ds|template)_edit */
$struct_data_source_item = array(
	'data_source_name' => array(
		'friendly_name' => __('Internal Data Source Name'),
		'method' => 'textbox',
		'max_length' => '19',
		'size' => '30',
		'default' => 'ds',
		'description' => __('Choose unique name to represent this piece of data inside of the RRDfile.')
		),
	'rrd_minimum' => array(
		'friendly_name' => __('Minimum Value ("U" for No Minimum)'),
		'method' => 'textbox',
		'max_length' => '30',
		'size' => '20',
		'default' => '0',
		'description' => __('The minimum value of data that is allowed to be collected.')
		),
	'rrd_maximum' => array(
		'friendly_name' => __('Maximum Value ("U" for No Maximum)'),
		'method' => 'textbox',
		'max_length' => '30',
		'size' => '20',
		'default' => 'U',
		'description' => __('The maximum value of data that is allowed to be collected.')
		),
	'data_source_type_id' => array(
		'friendly_name' => __('Data Source Type'),
		'method' => 'drop_array',
		'array' => $data_source_types,
		'default' => '',
		'description' => __('How data is represented in the RRA.')
		),
	'rrd_heartbeat' => array(
		'friendly_name' => __('Heartbeat'),
		'method' => 'hidden',
		'max_length' => '20',
		'size' => '10',
		'default' => '600',
		'description' => __('The maximum amount of time that can pass before data is entered as \'unknown\'. (Usually 2x300=600)')
		),
	'data_input_field_id' => array(
		'friendly_name' => __('Output Field'),
		'method' => 'drop_sql',
		'default' => '0',
		'none_value' => __('Not Selected'),
		'description' => __('When data is gathered, the data for this field will be put into this data source.')
		)
	);

/* file: grprint_presets.php, action: edit */
$fields_grprint_presets_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('Enter a name for this GPRINT preset, make sure it is something you recognize.'),
		'value' => '|arg1:name|',
		'max_length' => '50',
		'size' => '40',
		),
	'gprint_text' => array(
		'method' => 'textbox',
		'friendly_name' => __('GPRINT Text'),
		'description' => __('Enter the custom GPRINT string here.'),
		'value' => '|arg1:gprint_text|',
		'max_length' => '50',
		'size' => '40',
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'save_component_gprint_presets' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: (graphs.php|graph_templates.php), action: (graph|template)_edit */
$struct_graph = array(
	'general_header' => array(
		'friendly_name' => __('Common Options'),
		'collapsible' => 'true',
		'method' => 'spacer',
		),
	'title' => array(
		'friendly_name' => __('Title (--title)'),
		'method' => 'textbox',
		'max_length' => '255',
		'default' => '',
		'description' => __('The name that is printed on the graph.  It can include replacement variables such as |host_description| or |query_fieldName|.  For a complete list of supported replacement tags, please see the Cacti documentation.'),
		'size' => '80'
		),
	'vertical_label' => array(
		'friendly_name' => __('Vertical Label (--vertical-label)'),
		'method' => 'textbox',
		'max_length' => '255',
		'default' => '',
		'description' => __('The label vertically printed to the left of the graph.'),
		'size' => '30'
		),
	'image_format_id' => array(
		'friendly_name' => __('Image Format (--imgformat)'),
		'method' => 'drop_array',
		'array' => $image_types,
		'default' => read_config_option('default_image_format'),
		'description' => __('The type of graph that is generated; PNG, GIF or SVG.  The selection of graph image type is very RRDtool dependent.')
		),
	'height' => array(
		'friendly_name' => __('Height (--height)'),
		'method' => 'textbox',
		'max_length' => '50',
		'default' => read_config_option('default_graph_height'),
		'description' => __('The height (in pixels) of the graph area within the graph. This area does not include the legend, axis legends, or title.'),
		'size' => '7'
		),
	'width' => array(
		'friendly_name' => __('Width (--width)'),
		'method' => 'textbox',
		'max_length' => '50',
		'default' => read_config_option('default_graph_width'),
		'description' => __('The width (in pixels) of the graph area within the graph. This area does not include the legend, axis legends, or title.'),
		'size' => '7'
		),
	'base_value' => array(
		'friendly_name' => __('Base Value (--base)'),
		'method' => 'textbox',
		'max_length' => '50',
		'default' => '1000',
		'description' => __('Should be set to 1024 for memory and 1000 for traffic measurements.'),
		'size' => '12'
		),
	'slope_mode' => array(
		'friendly_name' => __('Slope Mode (--slope-mode)'),
		'method' => 'checkbox',
		'default' => 'on',
		'description' => __('Using Slope Mode evens out the shape of the graphs at the expense of some on screen resolution.')
		),
	'scaling_header' => array(
		'friendly_name' => __('Scaling Options'),
		'collapsible' => 'true',
		'method' => 'spacer',
		),
	'auto_scale' => array(
		'friendly_name' => __('Auto Scale'),
		'method' => 'checkbox',
		'default' => 'on',
		'description' => __('Auto scale the y-axis instead of defining an upper and lower limit. Note: if this is check both the Upper and Lower limit will be ignored.'),
		'size' => '7'
		),
	'auto_scale_opts' => array(
		'friendly_name' => __('Auto Scale Options'),
		'method' => 'radio',
		'default' => '2',
		'description' => __('Use <br> --alt-autoscale to scale to the absolute minimum and maximum <br> --alt-autoscale-max to scale to the maximum value, using a given lower limit <br> --alt-autoscale-min to scale to the minimum value, using a given upper limit <br> --alt-autoscale (with limits) to scale using both lower and upper limits (RRDtool default) <br>'),
		'items' => array(
			0 => array(
				'radio_value' => '1',
				'radio_caption' => __('Use --alt-autoscale (ignoring given limits)')
				),
			1 => array(
				'radio_value' => '2',
				'radio_caption' => __('Use --alt-autoscale-max (accepting a lower limit)')
				),
			2 => array(
				'radio_value' => '3',
				'radio_caption' => __('Use --alt-autoscale-min (accepting an upper limit)')
				),
			3 => array(
				'radio_value' => '4',
				'radio_caption' => __('Use --alt-autoscale (accepting both limits, RRDtool default)')
				)
			)
		),
	'auto_scale_log' => array(
		'friendly_name' => __('Logarithmic Scaling (--logarithmic)'),
		'method' => 'checkbox',
		'default' => '',
		'on_change' => 'changeScaleLog()',
		'description' => __('Use Logarithmic y-axis scaling')
		),
	'scale_log_units' => array(
		'friendly_name' => __('SI Units for Logarithmic Scaling (--units=si)'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Use SI Units for Logarithmic Scaling instead of using exponential notation.<br> Note: Linear graphs use SI notation by default.')
		),
	'auto_scale_rigid' => array(
		'friendly_name' => __('Rigid Boundaries Mode (--rigid)'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Do not expand the lower and upper limit if the graph contains a value outside the valid range.')
		),
	'upper_limit' => array(
		'friendly_name' => __('Upper Limit (--upper-limit)'),
		'method' => 'textbox',
		'max_length' => '50',
		'default' => '100',
		'description' => __('The maximum vertical value for the graph.'),
		'size' => '12'
		),
	'lower_limit' => array(
		'friendly_name' => __('Lower Limit (--lower-limit)'),
		'method' => 'textbox',
		'max_length' => '255',
		'default' => '0',
		'description' => __('The minimum vertical value for the graph.'),
		'size' => '12'
		),
	'grid_header' => array(
		'friendly_name' => __('Grid Options'),
		'collapsible' => 'true',
		'method' => 'spacer',
		),
	'unit_value' => array(
		'friendly_name' => __('Unit Grid Value (--unit/--y-grid)'),
		'method' => 'textbox',
		'max_length' => '50',
		'default' => '',
		'description' => __('Sets the exponent value on the Y-axis for numbers. Note: This option is deprecated and replaced by the --y-grid option.  In this option, Y-axis grid lines appear at each grid step interval.  Labels are placed every label factor lines.'),
		'size' => '12'
		),
	'unit_exponent_value' => array(
		'friendly_name' => __('Unit Exponent Value (--units-exponent)'),
		'method' => 'textbox',
		'max_length' => '50',
		'default' => '',
		'description' => __('What unit Cacti should use on the Y-axis. Use 3 to display everything in "k" or -6 to display everything in "u" (micro).'),
		'size' => '12'
		),
	'unit_length' => array(
		'friendly_name' => __('Unit Length (--units-length &lt;length&gt;)'),
		'method' => 'textbox',
		'max_length' => '50',
		'default' => '',
		'size' => '30',
		'description' => __('How many digits should RRDtool assume the y-axis labels to be? You may have to use this option to make enough space once you start fiddling with the y-axis labeling.'),
        ),
	'no_gridfit' => array(
		'friendly_name' => __('No Gridfit (--no-gridfit)'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('In order to avoid anti-aliasing blurring effects RRDtool snaps points to device resolution pixels, this results in a crisper appearance. If this is not to your liking, you can use this switch to turn this behavior off.<br><strong>Note: </strong>Gridfitting is turned off for PDF, EPS, SVG output by default.'),
		),
	'alt_y_grid' => array(
		'friendly_name' => __('Alternative Y Grid (--alt-y-grid)'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('The algorithm ensures that you always have a grid, that there are enough but not too many grid lines, and that the grid is metric. This parameter will also ensure that you get enough decimals displayed even if your graph goes from 69.998 to 70.001.<br><strong>Note: </strong>This parameter may interfere with --alt-autoscale options.'),
		),
	'axis_header' => array(
		'friendly_name' => __('Axis Options'),
		'collapsible' => 'true',
		'method' => 'spacer',
		),
	'right_axis' => array(
		'friendly_name' => __('Right Axis (--right-axis &lt;scale:shift&gt;)'),
		'method' => 'textbox',
		'max_length' => '20',
		'default' => '',
		'size' => '20',
		'description' => __('A second axis will be drawn to the right of the graph. It is tied to the left axis via the scale and shift parameters.'),
		),
	'right_axis_label' => array(
		'friendly_name' => __('Right Axis Label (--right-axis-label &lt;string&gt;)'),
		'method' => 'textbox',
		'max_length' => '200',
		'default' => '',
		'size' => '30',
		'description' => __('The label for the right axis.'),
        ),
	'right_axis_format' => array(
		'friendly_name' => __('Right Axis Format (--right-axis-format &lt;format&gt;)'),
		'method' => 'drop_sql',
		'sql' => 'SELECT id, name FROM graph_templates_gprint WHERE gprint_text NOT LIKE "%\%s%" ORDER BY name',
		'default' => '',
		'none_value' => __('None'),
		'description' => __('By default, the format of the axis labels gets determined automatically.  If you want to do this yourself, use this option with the same %lf arguments you know from the PRINT and GPRINT commands.'),
		),
	'right_axis_formatter' => array(
		'friendly_name' => __('Right Axis Formatter (--right-axis-formatter &lt;formatname&gt;)'),
		'method' => 'drop_array',
		'array' => $rrd_axis_formatters,
		'default' => '0',
		'none_value' => __('None'),
		'description' => __('When you setup the right axis labeling, apply a rule to the data format.  Supported formats include "numeric" where data is treated as numeric, "timestamp" where values are interpreted as UNIX timestamps (number of seconds since January 1970) and expressed using strftime format (default is "%Y-%m-%d %H:%M:%S").  See also --units-length and --right-axis-format.  Finally "duration" where values are interpreted as duration in milliseconds.  Formatting follows the rules of valstrfduration qualified PRINT/GPRINT.'),
		),
	'left_axis_formatter' => array(
		'friendly_name' => __('Left Axis Formatter (--left-axis-formatter &lt;formatname&gt;)'),
		'method' => 'drop_array',
		'array' => $rrd_axis_formatters,
		'default' => '0',
		'none_value' => __('None'),
		'description' => __('When you setup the left axis labeling, apply a rule to the data format.  Supported formats include "numeric" where data is treated as numeric, "timestamp" where values are interpreted as UNIX timestamps (number of seconds since January 1970) and expressed using strftime format (default is "%Y-%m-%d %H:%M:%S").  See also --units-length.  Finally "duration" where values are interpreted as duration in milliseconds.  Formatting follows the rules of valstrfduration qualified PRINT/GPRINT.'),
		),
	'legend_header' => array(
		'friendly_name' => __('Legend Options'),
		'collapsible' => 'true',
		'method' => 'spacer',
		),
	'auto_padding' => array(
		'friendly_name' => __('Auto Padding'),
		'method' => 'checkbox',
		'default' => 'on',
		'description' => __('Pad text so that legend and graph data always line up. Note: this could cause graphs to take longer to render because of the larger overhead. Also Auto Padding may not be accurate on all types of graphs, consistent labeling usually helps.')
		),
	'dynamic_labels' => array(
		'friendly_name' => __('Dynamic Labels (--dynamic-labels)'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Draw line markers as a line.'),
		),
	'force_rules_legend' => array(
		'friendly_name' => __('Force Rules Legend (--force-rules-legend)'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Force the generation of HRULE and VRULE legends.'),
		),
	'tab_width' => array(
		'friendly_name' => __('Tab Width (--tabwidth &lt;pixels&gt;)'),
		'method' => 'textbox',
		'max_length' => '50',
		'default' => '',
		'size' => '10',
		'description' => __('By default the tab-width is 40 pixels, use this option to change it.')
		),
	'legend_position' => array(
		'friendly_name' => __('Legend Position (--legend-position=&lt;position&gt;)'),
		'method' => 'drop_array',
		'array' => $rrd_legend_position,
		'none_value' => __('None'),
		'description' => __('Place the legend at the given side of the graph.'),
		),
	'legend_direction' => array(
		'friendly_name' => __('Legend Direction (--legend-direction=&lt;direction&gt;)'),
		'method' => 'drop_array',
		'array' => $rrd_legend_direction,
		'none_value' => __('None'),
		'description' => __('Place the legend items in the given vertical order.'),
		),
	);

/* file: (graphs.php|graph_templates.php), action: item_edit */
$struct_graph_item = array(
	'graph_type_id' => array(
		'friendly_name' => __('Graph Item Type'),
		'method' => 'drop_array',
		'array' => $graph_item_types,
		'default' => '4',
		'description' => __('How data for this item is represented visually on the graph.')
		),
	'task_item_id' => array(
		'friendly_name' => __('Data Source'),
		'method' => 'drop_sql',
		'sql' => 'SELECT
			CONCAT_WS("",case when host.description is null then "No Device" when host.description is not null then host.description end," - ",data_template_data.name," (",data_template_rrd.data_source_name,")") AS name,
			data_template_rrd.id
			FROM (data_template_data,data_template_rrd,data_local)
			LEFT JOIN host ON (data_local.host_id=host.id)
			WHERE data_template_rrd.local_data_id=data_local.id
			AND data_template_data.local_data_id=data_local.id
			ORDER BY name',
		'default' => '0',
		'none_value' => __('None'),
		'description' => __('The data source to use for this graph item.')
		),
	'color_id' => array(
		'friendly_name' => __('Color'),
		'method' => 'drop_color',
		'default' => '0',
		'on_change' => 'changeColorId()',
		'description' => __('The color to use for the legend.')
		),
	'alpha' => array(
		'friendly_name' => __('Opacity/Alpha Channel'),
		'method' => 'drop_array',
		'default' => 'FF',
		'array' => $graph_color_alpha,
		'description' => __('The opacity/alpha channel of the color.')
		),
	'consolidation_function_id' => array(
		'friendly_name' => __('Consolidation Function'),
		'method' => 'drop_array',
		'array' => $consolidation_functions,
		'default' => '0',
		'description' => __('How data for this item is represented statistically on the graph.')
		),
	'cdef_id' => array(
		'friendly_name' => __('CDEF Function'),
		'method' => 'drop_sql',
		'sql' => 'SELECT id, name FROM cdef ORDER BY name',
		'default' => '0',
		'none_value' => __('None'),
		'description' => __('A CDEF (math) function to apply to this item on the graph or legend.')
		),
	'vdef_id' => array(
		'friendly_name' => __('VDEF Function'),
		'method' => 'drop_sql',
		'sql' => 'SELECT id, name FROM vdef ORDER BY name',
		'default' => '0',
		'none_value' => __('None'),
		'description' => __('A VDEF (math) function to apply to this item on the graph legend.')
		),
	'shift' => array(
		'friendly_name' => __('Shift Data'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Offset your data on the time axis (x-axis) by the amount specified in the \'value\' field.'),
		),
	'value' => array(
		'friendly_name' => __('Value'),
		'method' => 'textbox',
		'max_length' => '255',
		'size' => '80',
		'default' => '',
		'description' => __('[HRULE|VRULE]: The value of the graph item.<br/> [TICK]: The fraction for the tick line.<br/> [SHIFT]: The time offset in seconds.')
		),
	'gprint_id' => array(
		'friendly_name' => __('GPRINT Type'),
		'method' => 'drop_sql',
		'sql' => 'SELECT id, name FROM graph_templates_gprint ORDER BY name',
		'default' => '2',
		'description' => __('If this graph item is a GPRINT, you can optionally choose another format here. You can define additional types under "GPRINT Presets".')
		),
	'textalign' => array(
		'friendly_name' => __('Text Alignment' . ' (TEXTALIGN)'),
		'method' => 'drop_array',
		'value' => '|arg1:textalign|',
		'array' => $rrd_textalign,
		'default' => '',
		'description' => __('All subsequent legend line(s) will be aligned as given here.  You may use this command multiple times in a single graph.  This command does not produce tabular layout.<br/><strong>Note: </strong>You may want to insert a &lt;HR&gt; on the preceding graph item.<br/> <strong>Note: </strong>A &lt;HR&gt; on this legend line will obsolete this setting!'),
		),
	'text_format' => array(
		'friendly_name' => __('Text Format'),
		'method' => 'textbox',
		'max_length' => '255',
		'size' => '80',
		'default' => '',
		'description' => __('Text that will be displayed on the legend for this graph item.')
		),
	'hard_return' => array(
		'friendly_name' => __('Insert Hard Return'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Forces the legend to the next line after this item.')
		),
	'line_width' => array(
		'friendly_name' => __('Line Width (decimal)'),
		'method' => 'textbox',
		'max_length' => '5',
		'default' => '1.00',
		'size' => '5',
		'description' => __('In case LINE was chosen, specify width of line here.  You must include a decimal precision, for example 2.00'),
		),
	'dashes' => array(
		'friendly_name' => __('Dashes (dashes[=on_s[,off_s[,on_s,off_s]...]])'),
		'method' => 'textbox',
		'max_length' => '40',
		'default' => '',
		'size' => '30',
		'description' => __('The dashes modifier enables dashed line style.'),
		),
	'dash_offset' => array(
		'friendly_name' => __('Dash Offset (dash-offset=offset)'),
		'method' => 'textbox',
		'max_length' => '4',
		'default' => '',
		'size' => '4',
		'description' => __('The dash-offset parameter specifies an offset into the pattern at which the stroke begins.'),
		),
	'sequence' => array(
		'friendly_name' => __('Sequence'),
		'method' => 'textbox',
		'max_length' => '4',
		'default' => '',
		'size' => '4',
		'description' => __('The dash-offset parameter specifies an offset into the pattern at which the stroke begins.'),
		)
	);

/* file: graph_templates.php, action: template_edit */
$fields_graph_template_template_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('The name given to this graph template.'),
		'value' => '|arg1:name|',
		'max_length' => '150',
		'size' => '80'
		),
	'multiple' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Multiple Instances'),
		'description' => __('Check this checkbox if there can be more than one Graph of this type per Device.'),
		'value' => '|arg1:multiple|',
		'default' => '',
		'form_id' => false
		),
	'test_source' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Test Data Sources'),
		'description' => __('Check this checkbox if you wish to test the Data Sources prior to their creation.  With Test Data Sources enabled, if the Data Source does not return valid data, the Graph will not be created.  This setting is important if you wish to have a more generic Device Template that can include more Graph Templates that can be selectively applied depending on the characteristics of the Device itself.  Note: If you have a long running script as a Data Source, the time to create Graphs will be increased.'),
		'value' => '|arg1:test_source|',
		'default' => '',
		'form_id' => false
		),
	'graph_template_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg2:graph_template_id|'
		),
	'graph_template_graph_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg2:id|'
		),
	'save_component_template' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: graph_templates.php, action: input_edit */
$fields_graph_template_input_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('Enter a name for this graph item input, make sure it is something you recognize.'),
		'value' => '|arg1:name|',
		'max_length' => '50',
		'size' => '40',
		),
	'description' => array(
		'method' => 'textarea',
		'friendly_name' => __('Description'),
		'description' => __('Enter a description for this graph item input to describe what this input is used for.'),
		'value' => '|arg1:description|',
		'textarea_rows' => '5',
		'textarea_cols' => '40'
		),
	'column_name' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Field Type'),
		'description' => __('How data is to be represented on the graph.'),
		'value' => '|arg1:column_name|',
		'array' => '|arg2:|',
		),
	'graph_template_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg3:graph_template_id|'
		),
	'graph_template_input_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg3:id|'
		),
	'save_component_input' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: host.php, action: edit */
$fields_host_edit = array(
	'host_gen_head' => array(
		'method' => 'spacer',
		'collapsible' => 'true',
		'friendly_name' => __('General Device Options')
		),
	'description' => array(
		'method' => 'textbox',
		'friendly_name' => __('Description'),
		'description' => __('Give this host a meaningful description.'),
		'value' => '|arg1:description|',
		'max_length' => '150',
		),
	'hostname' => array(
		'method' => 'textbox',
		'friendly_name' => __('Hostname'),
		'description' => __('Fully qualified hostname or IP address for this device.'),
		'value' => '|arg1:hostname|',
		'max_length' => '100',
		'size' => '60',
		),
	'location' => array(
		'method' => 'drop_callback',
		'friendly_name' => __('Location'),
		'description' => __('The physical location of the Device.  This free form text can be a room, rack location, etc.'),
		'none_value' => __('None'),
		'sql' => 'SELECT DISTINCT location AS id, location AS name FROM host ORDER BY location',
		'action' => 'ajax_locations',
		'id' => '|arg1:location|',
		'value' => '|arg1:location|',
		),
	'poller_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Poller Association'),
		'description' => __('Choose the Cacti Data Collector/Poller to be used to gather data from this Device.'),
		'value' => '|arg1:poller_id|',
		'default' => read_config_option('default_poller'),
		'sql' => 'SELECT id, name FROM poller ORDER BY name',
		),
	'site_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Device Site Association'),
		'description' => __('What Site is this Device associated with.'),
		'value' => '|arg1:site_id|',
		'none_value' => __('None'),
		'default' => read_config_option('default_site'),
		'sql' => 'SELECT id, name FROM sites ORDER BY name',
		),
	'host_template_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Device Template'),
		'description' => __('Choose the Device Template to use to define the default Graph Templates and Data Queries associated with this Device.'),
		'value' => '|arg1:host_template_id|',
		'none_value' => __('None'),
		'default' => read_config_option('default_template'),
		'sql' => 'SELECT id, name FROM host_template ORDER BY name',
		),
	'device_threads' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Number of Collection Threads'),
		'description' => __('The number of concurrent threads to use for polling this device.  This applies to the Spine poller only.'),
		'value' => '|arg1:device_threads|',
		'default' => read_config_option('device_threads'),
		'array' => $device_threads
		),
	'disabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Disable Device'),
		'description' => __('Check this box to disable all checks for this host.'),
		'value' => '|arg1:disabled|',
		'default' => '',
		'form_id' => false
		),
	'host_snmp_head' => array(
		'method' => 'spacer',
		'friendly_name' => __('SNMP Options'),
		),
	) + $fields_snmp_item_with_oids + array(
	'host_avail_head' => array(
		'method' => 'spacer',
		'friendly_name' => __('Availability/Reachability Options'),
		'collapsible' => 'true',
		),
	'availability_method' => array(
		'friendly_name' => __('Downed Device Detection'),
		'description' => __('The method Cacti will use to determine if a host is available for polling.  <br><i>NOTE: It is recommended that, at a minimum, SNMP always be selected.</i>'),
		'on_change' => 'changeHostForm()',
		'value' => '|arg1:availability_method|',
		'method' => 'drop_array',
		'default' => read_config_option('availability_method'),
		'array' => $availability_options
		),
	'ping_method' => array(
		'friendly_name' => __('Ping Method'),
		'description' => __('The type of ping packet to sent.  <br><i>NOTE: ICMP on Linux/UNIX requires root privileges.</i>'),
		'on_change' => 'changeHostForm()',
		'value' => '|arg1:ping_method|',
		'method' => 'drop_array',
		'default' => read_config_option('ping_method'),
		'array' => $ping_methods
		),
	'ping_port' => array(
		'method' => 'textbox',
		'friendly_name' => __('Ping Port'),
		'value' => '|arg1:ping_port|',
		'description' => __('TCP or UDP port to attempt connection.'),
		'default' => read_config_option('ping_port'),
		'max_length' => '50',
		'size' => '7'
		),
	'ping_timeout' => array(
		'friendly_name' => __('Ping Timeout Value'),
		'description' => __('The timeout value to use for host ICMP and UDP pinging.  This host SNMP timeout value applies for SNMP pings.'),
		'method' => 'textbox',
		'value' => '|arg1:ping_timeout|',
		'default' => read_config_option('ping_timeout'),
		'max_length' => '10',
		'size' => '7'
		),
	'ping_retries' => array(
		'friendly_name' => __('Ping Retry Count'),
		'description' => __('After an initial failure, the number of ping retries Cacti will attempt before failing.'),
		'method' => 'textbox',
		'value' => '|arg1:ping_retries|',
		'default' => read_config_option('ping_retries'),
		'max_length' => '10',
		'size' => '7'
		),
	'host_add_head' => array(
		'method' => 'spacer',
		'collapsible' => 'true',
		'friendly_name' => __('Additional Options')
		),
	'notes' => array(
		'method' => 'textarea',
		'friendly_name' => __('Notes'),
		'description' => __('Enter notes to this host.'),
		'class' => 'textAreaNotes',
		'value' => '|arg1:notes|',
		'textarea_rows' => '5',
		'textarea_cols' => '50'
		),
	'external_id' => array(
		'friendly_name' => __('External ID'),
		'description' => __('External ID for linking Cacti data to external monitoring systems.'),
		'method' => 'textbox',
		'value' => '|arg1:external_id|',
		'default' => '',
		'max_length' => '40',
		'size' => '20'
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'save_component_host' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: host_templates.php, action: edit */
$fields_host_template_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A useful name for this host template.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80'
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'save_component_template' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: data_queries.php, action: edit */
$fields_data_query_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A name for this data query.'),
		'value' => '|arg1:name|',
		'max_length' => '100',
		'size' => '60'
		),
	'description' => array(
		'method' => 'textbox',
		'friendly_name' => __('Description'),
		'description' => __('A description for this data query.'),
		'value' => '|arg1:description|',
		'max_length' => '255',
		'size' => '80',
		),
	'xml_path' => array(
		'method' => 'textbox',
		'friendly_name' => __('XML Path'),
		'description' => __('The full path to the XML file containing definitions for this data query.'),
		'value' => '|arg1:xml_path|',
		'default' => '<path_cacti>/resource/',
		'max_length' => '255',
		'size' => '80',
		),
	'data_input_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Data Input Method'),
		'description' => __('Choose the input method for this Data Query.  This input method defines how data is collected for each Device associated with the Data Query.'),
		'value' => '|arg1:data_input_id|',
		'sql' => 'SELECT id, name FROM data_input WHERE type_id IN(3,4,6) ORDER BY name',
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|',
		),
	'save_component_snmp_query' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: data_queries.php, action: item_edit */
$fields_data_query_item_edit = array(
	'graph_template_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Graph Template'),
		'description' => __('Choose the Graph Template to use for this Data Query Graph Template item.'),
		'value' => '|arg1:graph_template_id|',
		'sql' => 'SELECT DISTINCT gt.id, gt.name
			FROM graph_templates AS gt
			WHERE gt.id IN(
				SELECT DISTINCT gti.graph_template_id
				FROM (
					SELECT DISTINCT graph_template_id, task_item_id
					FROM graph_templates_item
					WHERE local_graph_id=0
				) AS gti
				INNER JOIN (
					SELECT id, data_template_id
					FROM data_template_rrd AS dtr
					WHERE dtr.local_data_id = 0
				) AS dtr
				ON gti.task_item_id=dtr.id
				INNER JOIN (
					SELECT DISTINCT data_template_id
					FROM data_template_data AS dtd
					WHERE local_data_id = 0
					AND dtd.data_input_id IN (2,11,12)
				) AS dtd
				ON dtd.data_template_id=dtr.data_template_id
			) ORDER BY gt.name',
		),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A name for this associated graph.'),
		'value' => '|arg1:name|',
		'max_length' => '100',
		'size' => '60',
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'snmp_query_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg2:snmp_query_id|'
		),
	'graph_template_id_prev' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:graph_template_id|'
		),
	'save_component_snmp_query_item' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

/* file: tree.php, action: edit */
$fields_tree_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A useful name for this graph tree.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80',
	),
	'sort_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Sorting Type'),
		'description' => __('Choose how items in this tree will be sorted.'),
		'value' => '|arg1:sort_type|',
		'array' => $tree_sort_types,
	),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Publish'),
		'description' => __('Should this Tree be published for users to access?'),
		'value' => '|arg1:enabled|',
		'default' => 'on'
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
	'sequence' => array(
		'method' => 'hidden',
		'value' => '|arg1:sequence|'
	),
	'save_component_tree' => array(
		'method' => 'hidden',
		'value' => '1'
	)
);

/* file: user_admin.php, action: user_edit (host) */
$fields_user_user_edit_host = array(
	'username' => array(
		'method' => 'textbox',
		'friendly_name' => __('User Name'),
		'description' => __('The login name for this user.'),
		'value' => '|arg1:username|',
		'max_length' => '255',
		'size' => '40',
		),
	'full_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Full Name'),
		'description' => __('A more descriptive name for this user, that can include spaces or special characters.'),
		'value' => '|arg1:full_name|',
		'max_length' => '128',
		'size' => 60
		),
	'email_address' => array(
		'method' => 'textbox',
		'friendly_name' => __('Email Address'),
		'description' => __('An Email Address where the User can be reached.'),
		'value' => '|arg1:email_address|',
		'max_length' => '128',
		'size' => 60
		),
	'password' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('Password'),
		'description' => __('Enter the password for this user twice. Remember that passwords are case sensitive!'),
		'value' => '',
		'max_length' => '255',
		'size' => 60
		),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enabled'),
		'description' => __('Determines if user is able to login.'),
		'value' => '|arg1:enabled|',
		'default' => ''
		),
	'locked' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Locked'),
		'description' => __('Determines if the user account is locked.'),
		'value' => '|arg1:locked|',
		'default' => ''
		),
	'grp1' => array(
		'friendly_name' => __('Account Options'),
		'method' => 'checkbox_group',
		'description' => __('Set any user account specific options here.'),
		'items' => array(
			'must_change_password' => array(
				'value' => '|arg1:must_change_password|',
				'friendly_name' => __('Must Change Password at Next Login'),
				'form_id' => '|arg1:id|',
				'default' => ''
				),
			'password_change' => array(
				'value' => '|arg1:password_change|',
				'friendly_name' => __('Change Password'),
				'form_id' => '|arg1:id|',
				'default' => 'on'
				),
			'graph_settings' => array(
				'value' => '|arg1:graph_settings|',
				'friendly_name' => __('Maintain Custom Graph and User Settings'),
				'form_id' => '|arg1:id|',
				'default' => 'on'
				)
			)
		),
	'grp2' => array(
		'friendly_name' => __('Graph Options'),
		'method' => 'checkbox_group',
		'description' => __('Set any graph specific options here.'),
		'items' => array(
			'show_tree' => array(
				'value' => '|arg1:show_tree|',
				'friendly_name' => __('User Has Rights to Tree View'),
				'form_id' => '|arg1:id|',
				'default' => 'on'
				),
			'show_list' => array(
				'value' => '|arg1:show_list|',
				'friendly_name' => __('User Has Rights to List View'),
				'form_id' => '|arg1:id|',
				'default' => 'on'
				),
			'show_preview' => array(
				'value' => '|arg1:show_preview|',
				'friendly_name' => __('User Has Rights to Preview View'),
				'form_id' => '|arg1:id|',
				'default' => 'on'
				)
			)
		),
	'login_opts' => array(
		'friendly_name' => __('Login Options'),
		'method' => 'radio',
		'default' => '1',
		'description' => __('What to do when this user logs in.'),
		'value' => '|arg1:login_opts|',
		'items' => array(
			0 => array(
				'radio_value' => '1',
				'radio_caption' => __('Show the page that user pointed their browser to.')
				),
			1 => array(
				'radio_value' => '2',
				'radio_caption' => __('Show the default console screen.')
				),
			2 => array(
				'radio_value' => '3',
				'radio_caption' => __('Show the default graph screen.')
				)
			)
		),
	'realm' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Authentication Realm'),
		'description' => __('Only used if you have LDAP or Web Basic Authentication enabled.  Changing this to a non-enabled realm will effectively disable the user.'),
		'value' => '|arg1:realm|',
		'default' => 0,
		'array' => $auth_realms,
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'save_component_user' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

$export_types = array(
	'host_template' => array(
		'name' => __('Device Template'),
		'title_sql' => 'SELECT name FROM host_template WHERE id=|id|',
		'dropdown_sql' => 'SELECT id, name FROM host_template ORDER BY name'
		),
	'graph_template' => array(
		'name' => __('Graph Template'),
		'title_sql' => 'SELECT name FROM graph_templates WHERE id=|id|',
		'dropdown_sql' => 'SELECT id, name FROM graph_templates ORDER BY name'
		),
	'data_template' => array(
		'name' => __('Data Template'),
		'title_sql' => 'SELECT name FROM data_template WHERE id=|id|',
		'dropdown_sql' => 'SELECT id, name FROM data_template ORDER BY name'
		),
	'data_query' => array(
		'name' => __('Data Query'),
		'title_sql' => 'SELECT name FROM snmp_query WHERE id=|id|',
		'dropdown_sql' => 'SELECT id, name FROM snmp_query ORDER BY name'
		),
//	'automation_devices' => array(
//		'name' => __('Discovery Rules'),
//		'title_sql' => 'SELECT CONCAT(ht.name, " (", at.sysDescr, ")") AS name FROM automation_templates AS at INNER JOIN host_template AS ht ON ht.id=at.host_template WHERE at.id=|id|',
//		'dropdown_sql' => 'SELECT at.id, CONCAT(ht.name, " (", at.sysDescr, ")") AS name FROM automation_templates AS at INNER JOIN host_template AS ht ON ht.id=at.host_template ORDER BY name'
//		),
//	'automation_graphs' => array(
//		'name' => __('Graph Rules'),
//		'title_sql' => 'SELECT name FROM automation_graph_rules WHERE id=|id|',
//		'dropdown_sql' => 'SELECT id, name FROM automation_graph_rules ORDER BY name'
//		),
//	'automation_trees' => array(
//		'name' => __('Tree Rules'),
//		'title_sql' => 'SELECT name FROM automation_tree_rules WHERE id=|id|',
//		'dropdown_sql' => 'SELECT id, name FROM automation_tree_rules ORDER BY name'
//		)
	);

$fields_template_import = array(
	'import_file' => array(
		'friendly_name' => __('Import Template from Local File'),
		'description' => __('If the XML file containing template data is located on your local machine, select it here.'),
		'accept' => '.xml',
		'method' => 'file'
	),
	'import_text' => array(
		'method' => 'hidden',
		'friendly_name' => __('Import Template from Text'),
		'description' => __('If you have the XML file containing template data as text, you can paste it into this box to import it.'),
		'value' => '',
		'default' => '',
		'textarea_rows' => '10',
		'textarea_cols' => '50',
		'class' => 'textAreaNotes'
	),
	'preview_only' => array(
		'friendly_name' => __('Preview Import Only'),
		'method' => 'checkbox',
		'description' => __('If checked, Cacti will not import the template, but rather compare the imported Template to the existing Template data.  If you are acceptable of the change, you can them import.'),
		'value' => '',
		'default' => 'on'
	),
	'remove_orphans' => array(
		'friendly_name' => __('Remove Orphaned Graph Items'),
		'method' => 'checkbox',
		'description' => __('If checked, Cacti will delete any Graph Items from both the Graph Template and associated Graphs that are not included in the imported Graph Template.'),
		'value' => '',
		'default' => ''
	),
	'replace_svalues' => array(
		'friendly_name' => __('Replace Data Query Suggested Value Patterns'),
		'method' => 'checkbox',
		'description' => __('Replace Data Source and Graph Template Suggested Value Records for Data Queries.  Graphs and Data Sources will take on new names after either a Data Query Reindex or by using the forced Replace Suggested Values process.'),
		'value' => '',
		'default' => ''
	),
	'import_data_source_profile' => array(
		'friendly_name' => __('Data Source Profile'),
		'method' => 'drop_sql',
		'description' => __('Select the Data Source Profile.  The Data Source Profile controls polling interval, the data aggregation, and retention policy for the resulting Data Sources.'),
		'sql' => "SELECT id, name FROM data_source_profiles ORDER BY name",
		'none_value' => __('Create New from Template'),
		'value' => '',
		'default' => '1'
		),
	);

	$fields_manager_edit = array(
		'host_header' => array(
			'method' => 'spacer',
			'friendly_name' => __('General SNMP Entity Options'),
			'collapsible' => 'true'
			),
		'description' => array(
			'method' => 'textbox',
			'friendly_name' => __('Description'),
			'description' => __('Give this SNMP entity a meaningful description.'),
			'value' => '|arg1:description|',
			'max_length' => '250',
			'size' => 80
			),
		'hostname' => array(
			'method' => 'textbox',
			'friendly_name' => __('Hostname'),
			'description' => __('Fully qualified hostname or IP address for this device.'),
			'value' => '|arg1:hostname|',
			'max_length' => '250',
			'size' => 80
			),
		'disabled' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Disable SNMP Notification Receiver'),
			'description' => __('Check this box if you temporary do not want to send SNMP notifications to this host.'),
			'value' => '|arg1:disabled|',
			'default' => '',
			'form_id' => false
			),
		'max_log_size' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Maximum Log Size'),
			'description' => __('Maximum number of day\'s notification log entries for this receiver need to be stored.'),
			'value' => '|arg1:max_log_size|',
			'default' => 31,
			'array' => array_combine( range(1,31), range(1,31) )
			),
		'snmp_options_header' => array(
			'method' => 'spacer',
			'friendly_name' => __('SNMP Options'),
			'collapsible' => 'true'
			),
		) + $fields_snmp_item + array(
		'snmp_message_type' => array(
			'friendly_name' => __('SNMP Message Type'),
			'description' => __('SNMP traps are always unacknowledged. To send out acknowledged SNMP notifications, formally called "INFORMS", SNMPv2 or above will be required.'),
			'method' => 'drop_array',
			'value' => '|arg1:snmp_message_type|',
			'default' => '1',
			'array' => array(1 => 'NOTIFICATIONS', 2 => 'INFORMS')
			),
		'addition_header' => array(
			'method' => 'spacer',
			'friendly_name' => __('Additional Options'),
			'collapsible' => 'true'
			),
		'notes' => array(
			'method' => 'textarea',
			'friendly_name' => __('Notes'),
			'description' => __('Enter notes to this host.'),
			'class' => 'textAreaNotes',
			'value' => '|arg1:notes|',
			'textarea_rows' => '5',
			'textarea_cols' => '50'
			),
		'id' => array(
			'method' => 'hidden_zero',
			'value' => '|arg1:id|'
			)
	);

# ------------------------------------------------------------
# Main Aggregate Parameters
# ------------------------------------------------------------
/* file: aggregate.php */
$struct_aggregate = array(
	'title_format' => array(
		'friendly_name' => __('Title'),
		'description' => __('The new Title of the aggregated Graph.'),
		'method' => 'textbox',
		'max_length' => '255',
		'value' => '|arg1:title_format|',
		'size' => '80'
	),
	'gprint_prefix' => array(
		'friendly_name' => __('Prefix'),
		'description' => __('A Prefix for all GPRINT lines to distinguish e.g. different hosts.'),
		'method' => 'textbox',
		'max_length' => '255',
		'value' => '|arg1:gprint_prefix|',
		'size' => '40'
	),
	'gprint_format' => array(
		'friendly_name' => __('Include Prefix Text'),
		'description' => __('Include the source Graphs GPRINT Title Text with the Aggregate Graph(s).'),
		'method' => 'checkbox',
		'value' => '|arg1:gprint_format|',
		'default' => ''
	),
	'aggregate_graph_type' => array(
		'friendly_name' => __('Graph Type'),
		'description' => __('Use this Option to create e.g. STACKed graphs.<br>AREA/STACK: 1st graph keeps AREA/STACK items, others convert to STACK<br>LINE1: all items convert to LINE1 items<br>LINE2: all items convert to LINE2 items<br>LINE3: all items convert to LINE3 items'),
		'method' => 'drop_array',
		'value' => '|arg1:aggregate_graph_type|',
		'array' => $agg_graph_types,
		'default' => GRAPH_ITEM_TYPE_STACK,
	),
	'aggregate_total' => array(
		'friendly_name' => __('Totaling'),
		'description' => __('Please check those Items that shall be totaled in the "Total" column, when selecting any totaling option here.'),
		'method' => 'drop_array',
		'value' => '|arg1:aggregate_total|',
		'array' => $agg_totals,
		'default' => AGGREGATE_TOTAL_NONE
	),
	'aggregate_total_type' => array(
		'friendly_name' => __('Total Type'),
		'description' => __('Which type of totaling shall be performed.'),
		'method' => 'drop_array',
		'value' => '|arg1:aggregate_total_type|',
		'array' => $agg_totals_type,
		'default' => AGGREGATE_TOTAL_TYPE_SIMILAR
	),
	'aggregate_total_prefix' => array(
		'friendly_name' => __('Prefix for GPRINT Totals'),
		'description' => __('A Prefix for all <strong>totaling</strong> GPRINT lines.'),
		'method' => 'textbox',
		'max_length' => '255',
		'value' => '|arg1:aggregate_total_prefix|',
		'size' => '40'
	),
	'aggregate_order_type' => array(
		'friendly_name' => __('Reorder Type'),
		'description' => __('Reordering of Graphs.'),
		'method' => 'drop_array',
		'value' => '|arg1:aggregate_order_type|',
		'array' => $agg_order_types,
		'default' => AGGREGATE_ORDER_NONE,
	),
	'graph_template_id' => array(
		'method' => 'hidden',
		'value' => '|arg1:graph_template_id|',
		'default' => 0
	)
);

$struct_aggregate_graph = array(
	'spacer0' => array(
		'friendly_name' => __('General Settings'),
		'method' => 'spacer'
	),
	'title_format' => array(
		'friendly_name' => __('Graph Name'),
		'description' => __('Please name this Aggregate Graph.'),
		'method' => 'textbox',
		'max_length' => '255',
		'value' => '|arg1:title_format|',
		'size' => '80'
	),
	'template_propogation' => array(
		'friendly_name' => __('Propagation Enabled'),
		'description' => __('Is this to carry the template?'),
		'method' => 'checkbox',
		'default' => '',
		'value' => '|arg1:template_propogation|'
	),
	'spacer1' => array(
		'friendly_name' => __('Aggregate Graph Settings'),
		'method' => 'spacer'
	),
	'gprint_prefix' => array(
		'friendly_name' => __('Prefix'),
		'description' => __('A Prefix for all GPRINT lines to distinguish e.g. different hosts.  You may use both Host as well as Data Query replacement variables in this prefix.'),
		'method' => 'textbox',
		'max_length' => '255',
		'value' => '|arg1:gprint_prefix|',
		'size' => '40'
	),
	'gprint_format' => array(
		'friendly_name' => __('Include Prefix Text'),
		'description' => __('Include the source Graphs GPRINT Title Text with the Aggregate Graph(s).'),
		'method' => 'checkbox',
		'value' => '|arg1:gprint_format|',
		'default' => ''
	),
	'graph_type' => array(
		'friendly_name' => __('Graph Type'),
		'description' => __('Use this Option to create e.g. STACKed graphs.<br>AREA/STACK: 1st graph keeps AREA/STACK items, others convert to STACK<br>LINE1: all items convert to LINE1 items<br>LINE2: all items convert to LINE2 items<br>LINE3: all items convert to LINE3 items'),
		'method' => 'drop_array',
		'value' => '|arg1:graph_type|',
		'array' => $agg_graph_types,
		'default' => GRAPH_ITEM_TYPE_STACK,
	),
	'total' => array(
		'friendly_name' => __('Totaling'),
		'description' => __('Please check those Items that shall be totaled in the "Total" column, when selecting any totaling option here.'),
		'method' => 'drop_array',
		'value' => '|arg1:total|',
		'array' => $agg_totals,
		'default' => AGGREGATE_TOTAL_NONE,
		'on_change' => 'changeTotals()',
	),
	'total_type' => array(
		'friendly_name' => __('Total Type'),
		'description' => __('Which type of totaling shall be performed.'),
		'method' => 'drop_array',
		'value' => '|arg1:total_type|',
		'array' => $agg_totals_type,
		'default' => AGGREGATE_TOTAL_TYPE_SIMILAR,
		'on_change' => 'changeTotalsType()',
	),
	'total_prefix' => array(
		'friendly_name' => __('Prefix for GPRINT Totals'),
		'description' => __('A Prefix for all <strong>totaling</strong> GPRINT lines.'),
		'method' => 'textbox',
		'max_length' => '255',
		'value' => '|arg1:total_prefix|',
		'size' => '40'
	),
	'order_type' => array(
		'friendly_name' => __('Reorder Type'),
		'description' => __('Reordering of Graphs.'),
		'method' => 'drop_array',
		'value' => '|arg1:order_type|',
		'array' => $agg_order_types,
		'default' => AGGREGATE_ORDER_NONE,
	),
	'id' => array(
		'method' => 'hidden',
		'value' => '|arg1:id|',
		'default' => 0
	),
	'local_graph_id' => array(
		'method' => 'hidden',
		'value' => '|arg1:local_graph_id|',
		'default' => 0
	),
	'aggregate_template_id' => array(
		'method' => 'hidden',
		'value' => '|arg1:aggregate_template_id|',
		'default' => 0
	),
	'graph_template_id' => array(
		'method' => 'hidden',
		'value' => '|arg1:graph_template_id|',
		'default' => 0
	)
);

$struct_aggregate_template = array(
	'spacer0' => array(
		'friendly_name' => __('General Settings'),
		'method' => 'spacer'
	),
	'name' => array(
		'friendly_name' => __('Aggregate Template Name'),
		'description' => __('Please name this Aggregate Template.'),
		'method' => 'textbox',
		'max_length' => '255',
		'value' => '|arg1:name|',
		'size' => '80'
	),
	'graph_template_id' => array(
		'friendly_name' => __('Source Graph Template'),
		'description' => __('The Graph Template that this Aggregate Template is based upon.'),
		'method' => 'drop_sql',
		'value' => '|arg1:graph_template_id|',
		'sql' => 'SELECT id, name FROM graph_templates ORDER BY name',
		'default' => 0,
		'none_value' => 'None'
	),
	'spacer1' => array(
		'friendly_name' => __('Aggregate Template Settings'),
		'method' => 'spacer'
	),
	'gprint_prefix' => array(
		'friendly_name' => __('Prefix'),
		'description' => __('A Prefix for all GPRINT lines to distinguish e.g. different hosts.  You may use both Host as well as Data Query replacement variables in this prefix.'),
		'method' => 'textbox',
		'max_length' => '255',
		'value' => '|arg1:gprint_prefix|',
		'size' => '40'
	),
	'gprint_format' => array(
		'friendly_name' => __('Include Prefix Text'),
		'description' => __('Include the source Graphs GPRINT Title Text with the Aggregate Graph(s).'),
		'method' => 'checkbox',
		'value' => '|arg1:gprint_format|',
		'default' => ''
	),
	'graph_type' => array(
		'friendly_name' => __('Graph Type'),
		'description' => __('Use this Option to create e.g. STACKed graphs.<br>AREA/STACK: 1st graph keeps AREA/STACK items, others convert to STACK<br>LINE1: all items convert to LINE1 items<br>LINE2: all items convert to LINE2 items<br>LINE3: all items convert to LINE3 items'),
		'method' => 'drop_array',
		'value' => '|arg1:graph_type|',
		'array' => $agg_graph_types,
		'default' => GRAPH_ITEM_TYPE_STACK,
	),
	'total' => array(
		'friendly_name' => __('Totaling'),
		'description' => __('Please check those Items that shall be totaled in the "Total" column, when selecting any totaling option here.'),
		'method' => 'drop_array',
		'value' => '|arg1:total|',
		'array' => $agg_totals,
		'default' => AGGREGATE_TOTAL_NONE,
		'on_change' => 'changeTotals()',
	),
	'total_type' => array(
		'friendly_name' => __('Total Type'),
		'description' => __('Which type of totaling shall be performed.'),
		'method' => 'drop_array',
		'value' => '|arg1:total_type|',
		'array' => $agg_totals_type,
		'default' => AGGREGATE_TOTAL_TYPE_SIMILAR,
		'on_change' => 'changeTotalsType()',
	),
	'total_prefix' => array(
		'friendly_name' => __('Prefix for GPRINT Totals'),
		'description' => __('A Prefix for all <strong>totaling</strong> GPRINT lines.'),
		'method' => 'textbox',
		'max_length' => '255',
		'value' => '|arg1:total_prefix|',
		'size' => '40'
	),
	'order_type' => array(
		'friendly_name' => __('Reorder Type'),
		'description' => __('Reordering of Graphs.'),
		'method' => 'drop_array',
		'value' => '|arg1:order_type|',
		'array' => $agg_order_types,
		'default' => AGGREGATE_ORDER_NONE,
	),
	'graph_template_id_prev' => array(
		'method' => 'hidden',
		'value' => '|arg1:graph_template_id|',
		'default' => 0
	)
);

# ------------------------------------------------------------
# Color Templates
# ------------------------------------------------------------
/* file: color_templates.php, action: template_edit */
$struct_color_template = array(
	'title' => array(
		'friendly_name' => __('Title'),
		'method' => 'textbox',
		'max_length' => '255',
		'default' => '',
		'description' => __('The name of this Color Template.'),
		'size' => '80'
	)
);

/* file: color_templates.php, action: item_edit */
$struct_color_template_item = array(
	'color_id' => array(
		'friendly_name' => __('Color'),
		'method' => 'drop_color',
		'default' => '0',
		'description' => __('A nice Color'),
		'value' => '|arg1:color_id|',
	)
);

/* file: color_templates.php, action: template_edit */
$fields_color_template_template_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A useful name for this Template.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80'
	)
);

# ------------------------------------------------------------
# Automation Rules
# ------------------------------------------------------------
/* file: automation_graph_rules.php, automation_tree_rules.php, action: edit */
$fields_automation_match_rule_item_edit = array(
	'operation' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Operation'),
		'description' => __('Logical operation to combine rules.'),
		'array' => $automation_oper,
		'value' => '|arg1:operation|',
		'on_change' => 'toggle_operation()',
	),
	'field' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Field Name'),
		'description' => __('The Field Name that shall be used for this Rule Item.'),
		'array' => array(),			# to be filled dynamically
		'value' => '|arg1:field|',
		'none_value' => __('None'),
	),
	'operator' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Operator'),
		'description' => __('Operator.'),
		'array' => $automation_op_array['display'],
		'value' => '|arg1:operator|',
		'on_change' => 'toggle_operator()',
	),
	'pattern' => array(
		'method' => 'textbox',
		'friendly_name' => __('Matching Pattern'),
		'description' => __('The Pattern to be matched against.'),
		'value' => '|arg1:pattern|',
		'max_length' => '255',
		'size' => '50',
	),
	'sequence' => array(
		'method' => 'view',
		'friendly_name' => __('Sequence'),
		'description' => __('Sequence.'),
		'value' => '|arg1:sequence|',
	)
);

/* file: automation_graph_rules.php, action: edit */
$fields_automation_graph_rule_item_edit = array(
	'operation' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Operation'),
		'description' => __('Logical operation to combine rules.'),
		'array' => $automation_oper,
		'value' => '|arg1:operation|',
		'on_change' => 'toggle_operation()',
	),
	'field' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Field Name'),
		'description' => __('The Field Name that shall be used for this Rule Item.'),
		'array' => array(),			# later to be filled dynamically
		'value' => '|arg1:field|',
		'none_value' => __('None'),
	),
	'operator' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Operator'),
		'description' => __('Operator.'),
		'array' => $automation_op_array['display'],
		'value' => '|arg1:operator|',
		'on_change' => 'toggle_operator()',
	),
	'pattern' => array(
		'method' => 'textbox',
		'friendly_name' => __('Matching Pattern'),
		'description' => __('The Pattern to be matched against.'),
		'value' => '|arg1:pattern|',
		'max_length' => '255',
		'size' => '50',
	),
	'sequence' => array(
		'method' => 'view',
		'friendly_name' => __('Sequence'),
		'description' => __('Sequence.'),
		'value' => '|arg1:sequence|',
	)
);

$fields_automation_graph_rules_edit1 = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A useful name for this Rule.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80'
	),
	'snmp_query_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Data Query'),
		'description' => __('Choose a Data Query to apply to this rule.'),
		'value' => '|arg1:snmp_query_id|',
		'on_change' => 'applySNMPQueryIdChange()',
		'sql' => 'SELECT id, name FROM snmp_query ORDER BY name'
	)
);

$fields_automation_graph_rules_edit2 = array(
	'graph_type_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Graph Type'),
		'description' => __('Choose any of the available Graph Types to apply to this rule.'),
		'value' => '|arg1:graph_type_id|',
		'on_change' => 'applySNMPQueryTypeChange()',
		'sql' => 'SELECT snmp_query_graph.id, snmp_query_graph.name
			FROM snmp_query_graph
			WHERE snmp_query_graph.snmp_query_id=|arg1:snmp_query_id|
			ORDER BY snmp_query_graph.name'
	)
);

$fields_automation_graph_rules_edit3 = array(
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enable Rule'),
		'description' => __('Check this box to enable this rule.'),
		'value' => '|arg1:enabled|',
		'default' => '',
		'form_id' => false
	)
);

/* file: automation_tree_rules.php, action: edit */
$fields_automation_tree_rules_edit1 = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A useful name for this Rule.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80'
	),
	'tree_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Tree'),
		'description' => __('Choose a Tree for the new Tree Items.'),
		'value' => '|arg1:tree_id|',
		'on_change' => 'applyTreeChange()',
		'sql' => 'SELECT id, name FROM graph_tree ORDER BY name'
	),
	'leaf_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Leaf Item Type'),
		'description' => __('The Item Type that shall be dynamically added to the tree.'),
		'value' => '|arg1:leaf_type|',
		'on_change' => 'applyItemTypeChange()',
		'array' => $automation_tree_item_types
	),
	'host_grouping_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Graph Grouping Style'),
		'description' => __('Choose how graphs are grouped when drawn for this particular host on the tree.'),
		'array' => $host_group_types,
		'value' => '|arg1:host_grouping_type|',
		'default' => HOST_GROUPING_GRAPH_TEMPLATE,
	)
);

$fields_automation_tree_rules_edit2 = array(
	'tree_item_id' => array(
		'method' => 'drop_tree',
		'friendly_name' => __('Optional: Sub-Tree Item'),
		'description' => __('Choose a Sub-Tree Item to hook in.<br>Make sure, that it is still there when this rule is executed!'),
		'tree_id' => '|arg1:tree_id|',
		'value' => '|arg1:tree_item_id|',
	)
);

$fields_automation_tree_rules_edit3 = array(
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enable Rule'),
		'description' => __('Check this box to enable this rule.'),
		'value' => '|arg1:enabled|',
		'default' => '',
		'form_id' => false
	)
);

$fields_automation_tree_rule_item_edit = array(
	'field' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Header Type'),
		'description' => __('Choose an Object to build a new Sub-header.'),
		'array' => array(),			# later to be filled dynamically
		'value' => '|arg1:field|',
		'none_value' => $automation_tree_header_types[AUTOMATION_TREE_ITEM_TYPE_STRING],
		'on_change' => 'applyHeaderChange()',
	),
	'sort_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Sorting Type'),
		'description' => __('Choose how items in this tree will be sorted.'),
		'value' => '|arg1:sort_type|',
		'default' => TREE_ORDERING_NONE,
		'array' => $tree_sort_types,
		),
	'propagate_changes' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Propagate Changes'),
		'description' => __('Propagate all options on this form (except for \'Title\') to all child \'Header\' items.'),
		'value' => '|arg1:propagate_changes|',
		'default' => '',
		'form_id' => false
		),
	'search_pattern' => array(
		'method' => 'textbox',
		'friendly_name' => __('Matching Pattern'),
		'description' => __('The String Pattern (Regular Expression) to match against.<br>Enclosing \'/\' must <strong>NOT</strong> be provided!'),
		'value' => '|arg1:search_pattern|',
		'max_length' => '255',
		'size' => '50',
		),
	'replace_pattern' => array(
		'method' => 'textbox',
		'friendly_name' => __('Replacement Pattern'),
		'description' => __('The Replacement String Pattern for use as a Tree Header.<br>Refer to a Match by e.g. <strong>\${1}</strong> for the first match!'),
		'value' => '|arg1:replace_pattern|',
		'max_length' => '255',
		'size' => '50',
		),
	'sequence' => array(
		'method' => 'view',
		'friendly_name' => __('Sequence'),
		'description' => __('Sequence.'),
		'value' => '|arg1:sequence|',
	)
);

api_plugin_hook('config_form');

