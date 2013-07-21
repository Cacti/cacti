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

if (!defined('VALID_HOST_FIELDS')) {
	$string = api_plugin_hook_function('valid_host_fields', '(hostname|host_id|snmp_community|snmp_username|snmp_password|snmp_auth_protocol|snmp_priv_passphrase|snmp_priv_protocol|snmp_context|snmp_version|snmp_port|snmp_timeout)');
	define('VALID_HOST_FIELDS', $string);
}

/* file: cdef.php, action: edit */
$fields_cdef_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "A useful name for this CDEF.",
		"value" => "|arg1:name|",
		"max_length" => "255",
		"size" => "60"
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"save_component_cdef" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: color.php, action: edit */
$fields_color_edit = array(
	"hex" => array(
		"method" => "textbox",
		"friendly_name" => "Hex Value",
		"description" => "The hex value for this color; valid range: 000000-FFFFFF.",
		"value" => "|arg1:hex|",
		"max_length" => "6",
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"save_component_color" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: data_input.php, action: edit */
$fields_data_input_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "Enter a meaningful name for this data input method.",
		"value" => "|arg1:name|",
		"max_length" => "255",
		),
	"type_id" => array(
		"method" => "drop_array",
		"friendly_name" => "Input Type",
		"description" => "Choose the method you wish to use to collect data for this Data Input method.",
		"value" => "|arg1:type_id|",
		"array" => $input_types,
		),
	"input_string" => array(
		"method" => "textarea",
		"friendly_name" => "Input String",
		"description" => "The data that is sent to the script, which includes the complete path to the script and input sources in &lt;&gt; brackets.",
		"value" => "|arg1:input_string|",
		"textarea_rows" => "4",
		"textarea_cols" => "60",
		"class" => "textAreaNotes",
		"max_length" => "255",
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"save_component_data_input" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: data_input.php, action: field_edit (dropdown) */
$fields_data_input_field_edit_1 = array(
	"data_name" => array(
		"method" => "drop_array",
		"friendly_name" => "Field [|arg1:|]",
		"description" => "Choose the associated field from the |arg1:| field.",
		"value" => "|arg3:data_name|",
		"array" => "|arg2:|",
		)
	);

/* file: data_input.php, action: field_edit (textbox) */
$fields_data_input_field_edit_2 = array(
	"data_name" => array(
		"method" => "textbox",
		"friendly_name" => "Field [|arg1:|]",
		"description" => "Enter a name for this |arg1:| field.",
		"value" => "|arg2:data_name|",
		"max_length" => "50",
		)
	);

/* file: data_input.php, action: field_edit */
$fields_data_input_field_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Friendly Name",
		"description" => "Enter a meaningful name for this data input method.",
		"value" => "|arg1:name|",
		"max_length" => "200",
		),
	"update_rra" => array(
		"method" => "checkbox",
		"friendly_name" => "Update RRD File",
		"description" => "Whether data from this output field is to be entered into the rrd file.",
		"value" => "|arg1:update_rra|",
		"default" => "on",
		"form_id" => "|arg1:id|"
		),
	"regexp_match" => array(
		"method" => "textbox",
		"friendly_name" => "Regular Expression Match",
		"description" => "If you want to require a certain regular expression to be matched againt input data, enter it here (ereg format).",
		"value" => "|arg1:regexp_match|",
		"max_length" => "200",
		"size" => "70"
		),
	"allow_nulls" => array(
		"method" => "checkbox",
		"friendly_name" => "Allow Empty Input",
		"description" => "Check here if you want to allow NULL input in this field from the user.",
		"value" => "|arg1:allow_nulls|",
		"default" => "",
		"form_id" => false
		),
	"type_code" => array(
		"method" => "textbox",
		"friendly_name" => "Special Type Code",
		"description" => "If this field should be treated specially by host templates, indicate so here. Valid keywords for this field are " . (str_replace(")", "'", str_replace("(", "'", str_replace("|", "', '", VALID_HOST_FIELDS)))),
		"value" => "|arg1:type_code|",
		"max_length" => "40"
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"input_output" => array(
		"method" => "hidden",
		"value" => "|arg2:|"
		),
	"sequence" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:sequence|"
		),
	"data_input_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg3:data_input_id|"
		),
	"save_component_field" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: data_templates.php, action: template_edit */
$fields_data_template_template_edit = array(
	"template_name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "The name given to this data template.",
		"value" => "|arg1:name|",
		"max_length" => "150",
		),
	"data_template_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg2:data_template_id|"
		),
	"data_template_data_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg2:id|"
		),
	"current_rrd" => array(
		"method" => "hidden_zero",
		"value" => "|arg3:view_rrd|"
		),
	"save_component_template" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: (data_sources.php|data_templates.php), action: (ds|template)_edit */
$struct_data_source = array(
	"name" => array(
		"friendly_name" => "Name",
		"method" => "textbox",
		"max_length" => "250",
		"default" => "",
		"description" => "Choose a name for this data source.",
		"flags" => ""
		),
	"data_source_path" => array(
		"friendly_name" => "Data Source Path",
		"method" => "textbox",
		"max_length" => "255",
		"default" => "",
		"description" => "The full path to the RRD file.",
		"flags" => "NOTEMPLATE"
		),
	"data_input_id" => array(
		"friendly_name" => "Data Input Method",
		"method" => "drop_sql",
		"sql" => "select id,name from data_input order by name",
		"default" => "",
		"none_value" => "None",
		"description" => "The script/source used to gather data for this data source.",
		"flags" => "ALWAYSTEMPLATE"
		),
	"rra_id" => array(
		"method" => "drop_multi_rra",
		"friendly_name" => "Associated RRA's",
		"description" => "Which RRA's to use when entering data. (It is recommended that you select all of these values).",
		"form_id" => "|arg1:id|",
		"sql" => "select rra_id as id,data_template_data_id from data_template_data_rra where data_template_data_id=|arg1:id|",
		"sql_all" => "select rra.id from rra order by id",
		"sql_print" => "select rra.name from (data_template_data_rra,rra) where data_template_data_rra.rra_id=rra.id and data_template_data_rra.data_template_data_id=|arg1:id|",
		"flags" => "ALWAYSTEMPLATE"
		),
	"rrd_step" => array(
		"friendly_name" => "Step",
		"method" => "textbox",
		"max_length" => "10",
		"size" => "20",
		"default" => "300",
		"description" => "The amount of time in seconds between expected updates.",
		"flags" => ""
		),
	"active" => array(
		"friendly_name" => "Data Source Active",
		"method" => "checkbox",
		"default" => "on",
		"description" => "Whether Cacti should gather data for this data source or not.",
		"flags" => ""
		)
	);

/* file: (data_sources.php|data_templates.php), action: (ds|template)_edit */
$struct_data_source_item = array(
	"data_source_name" => array(
		"friendly_name" => "Internal Data Source Name",
		"method" => "textbox",
		"max_length" => "19",
		"default" => "",
		"description" => "Choose unique name to represent this piece of data inside of the rrd file."
		),
	"rrd_minimum" => array(
		"friendly_name" => "Minimum Value ('U' for No Minimum)",
		"method" => "textbox",
		"max_length" => "20",
		"size" => "30",
		"default" => "0",
		"description" => "The minimum value of data that is allowed to be collected."
		),
	"rrd_maximum" => array(
		"friendly_name" => "Maximum Value ('U' for No Maximum)",
		"method" => "textbox",
		"max_length" => "20",
		"size" => "30",
		"default" => "0",
		"description" => "The maximum value of data that is allowed to be collected."
		),
	"data_source_type_id" => array(
		"friendly_name" => "Data Source Type",
		"method" => "drop_array",
		"array" => $data_source_types,
		"default" => "",
		"description" => "How data is represented in the RRA."
		),
	"rrd_heartbeat" => array(
		"friendly_name" => "Heartbeat",
		"method" => "textbox",
		"max_length" => "20",
		"size" => "30",
		"default" => "600",
		"description" => "The maximum amount of time that can pass before data is entered as \"unknown\".
			(Usually 2x300=600)"
		),
	"data_input_field_id" => array(
		"friendly_name" => "Output Field",
		"method" => "drop_sql",
		"default" => "0",
		"description" => "When data is gathered, the data for this field will be put into this data source."
		)
	);

/* file: grprint_presets.php, action: edit */
$fields_grprint_presets_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "Enter a name for this GPRINT preset, make sure it is something you recognize.",
		"value" => "|arg1:name|",
		"max_length" => "50",
		),
	"gprint_text" => array(
		"method" => "textbox",
		"friendly_name" => "GPRINT Text",
		"description" => "Enter the custom GPRINT string here.",
		"value" => "|arg1:gprint_text|",
		"max_length" => "50",
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"save_component_gprint_presets" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: (graphs.php|graph_templates.php), action: (graph|template)_edit */
$struct_graph = array(
	"title" => array(
		"friendly_name" => "Title (--title)",
		"method" => "textbox",
		"max_length" => "255",
		"default" => "",
		"description" => "The name that is printed on the graph."
		),
	"image_format_id" => array(
		"friendly_name" => "Image Format (--imgformat)",
		"method" => "drop_array",
		"array" => $image_types,
		"default" => "1",
		"description" => "The type of graph that is generated; PNG, GIF or SVG.  The selection of graph image type is very RRDtool dependent."
		),
	"height" => array(
		"friendly_name" => "Height (--height)",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "120",
		"description" => "The height (in pixels) that the graph is."
		),
	"width" => array(
		"friendly_name" => "Width (--width)",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "500",
		"description" => "The width (in pixels) that the graph is."
		),
	"slope_mode" => array(
		"friendly_name" => "Slope Mode (--slope-mode)",
		"method" => "checkbox",
		"default" => "on",
		"description" => "Using Slope Mode, in RRDtool 1.2.x and above, evens out the shape of the graphs at the expense of
			some on screen resolution."
		),
	"auto_scale" => array(
		"friendly_name" => "Auto Scale",
		"method" => "checkbox",
		"default" => "on",
		"description" => "Auto scale the y-axis instead of defining an upper and lower limit. Note: if this is check both the
			Upper and Lower limit will be ignored."
		),
	"auto_scale_opts" => array(
		"friendly_name" => "Auto Scale Options",
		"method" => "radio",
		"default" => "2",
		"description" => "Use <br>
			--alt-autoscale to scale to the absolute minimum and maximum <br>
		    --alt-autoscale-max to scale to the maximum value, using a given lower limit <br>
		    --alt-autoscale-min to scale to the minimum value, using a given upper limit <br>
			--alt-autoscale (with limits) to scale using both lower and upper limits (rrdtool default) <br>
		    ",
		"items" => array(
			0 => array(
				"radio_value" => "1",
				"radio_caption" => "Use --alt-autoscale (ignoring given limits)"
				),
			1 => array(
				"radio_value" => "2",
				"radio_caption" => "Use --alt-autoscale-max (accepting a lower limit)"
				),
			2 => array(
				"radio_value" => "3",
				"radio_caption" => "Use --alt-autoscale-min (accepting an upper limit, requires rrdtool 1.2.x)"
				),
			3 => array(
				"radio_value" => "4",
				"radio_caption" => "Use --alt-autoscale (accepting both limits, rrdtool default)"
				)
			)
		),
	"auto_scale_log" => array(
		"friendly_name" => "Logarithmic Scaling (--logarithmic)",
		"method" => "checkbox",
		"default" => "",
		"on_change" => "changeScaleLog()",
		"description" => "Use Logarithmic y-axis scaling"
		),
	"scale_log_units" => array(
		"friendly_name" => "SI Units for Logarithmic Scaling (--units=si)",
		"method" => "checkbox",
		"default" => "",
		"description" => "Use SI Units for Logarithmic Scaling instead of using exponential notation (not available for rrdtool-1.0.x).<br>
			Note: Linear graphs use SI notation by default."
		),
	"auto_scale_rigid" => array(
		"friendly_name" => "Rigid Boundaries Mode (--rigid)",
		"method" => "checkbox",
		"default" => "",
		"description" => "Do not expand the lower and upper limit if the graph contains a value outside the valid range."
		),
	"auto_padding" => array(
		"friendly_name" => "Auto Padding",
		"method" => "checkbox",
		"default" => "on",
		"description" => "Pad text so that legend and graph data always line up. Note: this could cause
			graphs to take longer to render because of the larger overhead. Also Auto Padding may not
			be accurate on all types of graphs, consistant labeling usually helps."
		),
	"export" => array(
		"friendly_name" => "Allow Graph Export",
		"method" => "checkbox",
		"default" => "on",
		"description" => "Choose whether this graph will be included in the static html/png export if you use
			cacti's export feature."
		),
	"upper_limit" => array(
		"friendly_name" => "Upper Limit (--upper-limit)",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "100",
		"description" => "The maximum vertical value for the rrd graph."
		),
	"lower_limit" => array(
		"friendly_name" => "Lower Limit (--lower-limit)",
		"method" => "textbox",
		"max_length" => "255",
		"default" => "0",
		"description" => "The minimum vertical value for the rrd graph."
		),
	"base_value" => array(
		"friendly_name" => "Base Value (--base)",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "1000",
		"description" => "Should be set to 1024 for memory and 1000 for traffic measurements."
		),
	"unit_value" => array(
		"friendly_name" => "Unit Grid Value (--unit/--y-grid)",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "",
		"description" => "Sets the xponent value on the Y-axis for numbers. Note: This option was
			added in rrdtool 1.0.36 and depricated in 1.2.x.  In RRDtool 1.2.x, this value is replaced by the --y-grid option.
			In this option, Y-axis grid lines appear at each grid step interval.  Labels are placed every label factor lines."
		),
	"unit_exponent_value" => array(
		"friendly_name" => "Unit Exponent Value (--units-exponent)",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "",
		"description" => "What unit cacti should use on the Y-axis. Use 3 to display everything in 'k' or -6
			to display everything in 'u' (micro)."
		),
	"vertical_label" => array(
		"friendly_name" => "Vertical Label (--vertical-label)",
		"method" => "textbox",
		"max_length" => "255",
		"default" => "",
		"description" => "The label vertically printed to the left of the graph."
		)
	);

/* file: (graphs.php|graph_templates.php), action: item_edit */
$struct_graph_item = array(
	"task_item_id" => array(
		"friendly_name" => "Data Source",
		"method" => "drop_sql",
		"sql" => "select
			CONCAT_WS('',case when host.description is null then 'No Host' when host.description is not null then host.description end,' - ',data_template_data.name,' (',data_template_rrd.data_source_name,')') as name,
			data_template_rrd.id
			from (data_template_data,data_template_rrd,data_local)
			left join host on (data_local.host_id=host.id)
			where data_template_rrd.local_data_id=data_local.id
			and data_template_data.local_data_id=data_local.id
			order by name",
		"default" => "0",
		"none_value" => "None",
		"description" => "The data source to use for this graph item."
		),
	"color_id" => array(
		"friendly_name" => "Color",
		"method" => "drop_color",
		"default" => "0",
		"on_change" => "changeColorId()",
		"description" => "The color to use for the legend."
		),
	"alpha" => array(
		"friendly_name" => "Opacity/Alpha Channel",
		"method" => "drop_array",
		"default" => "FF",
		"array" => $graph_color_alpha,
		"description" => "The opacity/alpha channel of the color. Not available for rrdtool-1.0.x."
		),
	"graph_type_id" => array(
		"friendly_name" => "Graph Item Type",
		"method" => "drop_array",
		"array" => $graph_item_types,
		"default" => "0",
		"description" => "How data for this item is represented visually on the graph."
		),
	"consolidation_function_id" => array(
		"friendly_name" => "Consolidation Function",
		"method" => "drop_array",
		"array" => $consolidation_functions,
		"default" => "0",
		"description" => "How data for this item is represented statistically on the graph."
		),
	"cdef_id" => array(
		"friendly_name" => "CDEF Function",
		"method" => "drop_sql",
		"sql" => "select id,name from cdef order by name",
		"default" => "0",
		"none_value" => "None",
		"description" => "A CDEF (math) function to apply to this item on the graph."
		),
	"value" => array(
		"friendly_name" => "Value",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "",
		"description" => "The value of an HRULE or VRULE graph item."
		),
	"gprint_id" => array(
		"friendly_name" => "GPRINT Type",
		"method" => "drop_sql",
		"sql" => "select id,name from graph_templates_gprint order by name",
		"default" => "2",
		"description" => "If this graph item is a GPRINT, you can optionally choose another format
			here. You can define additional types under \"GPRINT Presets\"."
		),
	"text_format" => array(
		"friendly_name" => "Text Format",
		"method" => "textbox",
		"max_length" => "255",
		"default" => "",
		"description" => "Text that will be displayed on the legend for this graph item."
		),
	"hard_return" => array(
		"friendly_name" => "Insert Hard Return",
		"method" => "checkbox",
		"default" => "",
		"description" => "Forces the legend to the next line after this item."
		),
	"sequence" => array(
		"friendly_name" => "Sequence",
		"method" => "view"
		)
	);

/* file: graph_templates.php, action: template_edit */
$fields_graph_template_template_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "The name given to this graph template.",
		"value" => "|arg1:name|",
		"max_length" => "150",
		),
	"graph_template_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg2:graph_template_id|"
		),
	"graph_template_graph_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg2:id|"
		),
	"save_component_template" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: graph_templates.php, action: input_edit */
$fields_graph_template_input_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "Enter a name for this graph item input, make sure it is something you recognize.",
		"value" => "|arg1:name|",
		"max_length" => "50"
		),
	"description" => array(
		"method" => "textarea",
		"friendly_name" => "Description",
		"description" => "Enter a description for this graph item input to describe what this input is used for.",
		"value" => "|arg1:description|",
		"textarea_rows" => "5",
		"textarea_cols" => "40"
		),
	"column_name" => array(
		"method" => "drop_array",
		"friendly_name" => "Field Type",
		"description" => "How data is to be represented on the graph.",
		"value" => "|arg1:column_name|",
		"array" => "|arg2:|",
		),
	"graph_template_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg3:graph_template_id|"
		),
	"graph_template_input_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg3:id|"
		),
	"save_component_input" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: host.php, action: edit */
$fields_host_edit = array(
	"host_header" => array(
		"method" => "spacer",
		"friendly_name" => "General Host Options"
		),
	"description" => array(
		"method" => "textbox",
		"friendly_name" => "Description",
		"description" => "Give this host a meaningful description.",
		"value" => "|arg1:description|",
		"max_length" => "250",
		),
	"hostname" => array(
		"method" => "textbox",
		"friendly_name" => "Hostname",
		"description" => "Fully qualified hostname or IP address for this device.",
		"value" => "|arg1:hostname|",
		"max_length" => "250",
		),
	"host_template_id" => array(
		"method" => "drop_sql",
		"friendly_name" => "Host Template",
		"description" => "Choose the Host Template to use to define the default Graph Templates and Data Queries associated with this Host.",
		"value" => "|arg1:host_template_id|",
		"none_value" => "None",
		"sql" => "select id,name from host_template order by name",
		),
	"device_threads" => array(
		"method" => "drop_array",
		"friendly_name" => "Number of Collection Threads",
		"description" => "The number of concurrent threads to use for polling this device.  This applies to the Spine poller only.",
		"value" => "|arg1:device_threads|",
		"default" => "1",
		"array" => $device_threads
		),
	"disabled" => array(
		"method" => "checkbox",
		"friendly_name" => "Disable Host",
		"description" => "Check this box to disable all checks for this host.",
		"value" => "|arg1:disabled|",
		"default" => "",
		"form_id" => false
		),
	"availability_header" => array(
		"method" => "spacer",
		"friendly_name" => "Availability/Reachability Options"
		),
	"availability_method" => array(
		"friendly_name" => "Downed Device Detection",
		"description" => "The method Cacti will use to determine if a host is available for polling.  <br><i>NOTE: It is recommended that, at a minimum, SNMP always be selected.</i>",
		"on_change" => "changeHostForm()",
		"value" => "|arg1:availability_method|",
		"method" => "drop_array",
		"default" => read_config_option("availability_method"),
		"array" => $availability_options
		),
	"ping_method" => array(
		"friendly_name" => "Ping Method",
		"description" => "The type of ping packet to sent.  <br><i>NOTE: ICMP on Linux/UNIX requires root privileges.</i>",
		"on_change" => "changeHostForm()",
		"value" => "|arg1:ping_method|",
		"method" => "drop_array",
		"default" => read_config_option("ping_method"),
		"array" => $ping_methods
		),
	"ping_port" => array(
		"method" => "textbox",
		"friendly_name" => "Ping Port",
		"value" => "|arg1:ping_port|",
		"description" => "TCP or UDP port to attempt connection.",
		"default" => read_config_option("ping_port"),
		"max_length" => "50",
		"size" => "15"
		),
	"ping_timeout" => array(
		"friendly_name" => "Ping Timeout Value",
		"description" => "The timeout value to use for host ICMP and UDP pinging.  This host SNMP timeout value applies for SNMP pings.",
		"method" => "textbox",
		"value" => "|arg1:ping_timeout|",
		"default" => read_config_option("ping_timeout"),
		"max_length" => "10",
		"size" => "15"
		),
	"ping_retries" => array(
		"friendly_name" => "Ping Retry Count",
		"description" => "After an initial failure, the number of ping retries Cacti will attempt before failing.",
		"method" => "textbox",
		"value" => "|arg1:ping_retries|",
		"default" => read_config_option("ping_retries"),
		"max_length" => "10",
		"size" => "15"
		),
	"spacer1" => array(
		"method" => "spacer",
		"friendly_name" => "SNMP Options"
		),
	"snmp_version" => array(
		"method" => "drop_array",
		"friendly_name" => "SNMP Version",
		"description" => "Choose the SNMP version for this device.",
		"on_change" => "changeHostForm()",
		"value" => "|arg1:snmp_version|",
		"default" => read_config_option("snmp_ver"),
		"array" => $snmp_versions,
		),
	"snmp_community" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Community",
		"description" => "SNMP read community for this device.",
		"value" => "|arg1:snmp_community|",
		"form_id" => "|arg1:id|",
		"default" => read_config_option("snmp_community"),
		"max_length" => "100",
		"size" => "15"
		),
	"snmp_username" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Username (v3)",
		"description" => "SNMP v3 username for this device.",
		"value" => "|arg1:snmp_username|",
		"default" => read_config_option("snmp_username"),
		"max_length" => "50",
		"size" => "15"
		),
	"snmp_password" => array(
		"method" => "textbox_password",
		"friendly_name" => "SNMP Password (v3)",
		"description" => "SNMP v3 password for this device.",
		"value" => "|arg1:snmp_password|",
		"default" => read_config_option("snmp_password"),
		"max_length" => "50",
		"size" => "15"
		),
	"snmp_auth_protocol" => array(
		"method" => "drop_array",
		"friendly_name" => "SNMP Auth Protocol (v3)",
		"description" => "Choose the SNMPv3 Authorization Protocol.",
		"value" => "|arg1:snmp_auth_protocol|",
		"default" => read_config_option("snmp_auth_protocol"),
		"array" => $snmp_auth_protocols,
		),
	"snmp_priv_passphrase" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Privacy Passphrase (v3)",
		"description" => "Choose the SNMPv3 Privacy Passphrase.",
		"value" => "|arg1:snmp_priv_passphrase|",
		"default" => read_config_option("snmp_priv_passphrase"),
		"max_length" => "200",
		"size" => "40"
		),
	"snmp_priv_protocol" => array(
		"method" => "drop_array",
		"friendly_name" => "SNMP Privacy Protocol (v3)",
		"description" => "Choose the SNMPv3 Privacy Protocol.",
		"value" => "|arg1:snmp_priv_protocol|",
		"default" => read_config_option("snmp_priv_protocol"),
		"array" => $snmp_priv_protocols,
		),
	"snmp_context" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Context",
		"description" => "Enter the SNMP Context to use for this device.",
		"value" => "|arg1:snmp_context|",
		"default" => "",
		"max_length" => "64",
		"size" => "25"
		),
	"snmp_port" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Port",
		"description" => "Enter the UDP port number to use for SNMP (default is 161).",
		"value" => "|arg1:snmp_port|",
		"max_length" => "5",
		"default" => read_config_option("snmp_port"),
		"size" => "15"
		),
	"snmp_timeout" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Timeout",
		"description" => "The maximum number of milliseconds Cacti will wait for an SNMP response (does not work with php-snmp support).",
		"value" => "|arg1:snmp_timeout|",
		"max_length" => "8",
		"default" => read_config_option("snmp_timeout"),
		"size" => "15"
		),
	"max_oids" => array(
		"method" => "textbox",
		"friendly_name" => "Maximum OID's Per Get Request",
		"description" => "Specified the number of OID's that can be obtained in a single SNMP Get request.",
		"value" => "|arg1:max_oids|",
		"max_length" => "8",
		"default" => read_config_option("max_get_size"),
		"size" => "15"
		),
	"header4" => array(
		"method" => "spacer",
		"friendly_name" => "Additional Options"
		),
	"notes" => array(
		"method" => "textarea",
		"friendly_name" => "Notes",
		"description" => "Enter notes to this host.",
		"class" => "textAreaNotes",
		"value" => "|arg1:notes|",
		"textarea_rows" => "5",
		"textarea_cols" => "50"
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"_host_template_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:host_template_id|"
		),
	"save_component_host" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: host_templates.php, action: edit */
$fields_host_template_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "A useful name for this host template.",
		"value" => "|arg1:name|",
		"max_length" => "255",
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"save_component_template" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: rra.php, action: edit */
$fields_rra_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "How data is to be entered in RRA's.",
		"value" => "|arg1:name|",
		"max_length" => "100",
		),
	"consolidation_function_id" => array(
		"method" => "drop_multi",
		"friendly_name" => "Consolidation Functions",
		"description" => "How data is to be entered in RRA's.",
		"array" => $consolidation_functions,
		"sql" => "select consolidation_function_id as id,rra_id from rra_cf where rra_id=|arg1:id|",
		),
	"x_files_factor" => array(
		"method" => "textbox",
		"friendly_name" => "X-Files Factor",
		"description" => "The amount of unknown data that can still be regarded as known.",
		"value" => "|arg1:x_files_factor|",
		"max_length" => "10",
		),
	"steps" => array(
		"method" => "textbox",
		"friendly_name" => "Steps",
		"description" => "How many data points are needed to put data into the RRA.",
		"value" => "|arg1:steps|",
		"max_length" => "8",
		),
	"rows" => array(
		"method" => "textbox",
		"friendly_name" => "Rows",
		"description" => "How many generations data is kept in the RRA.",
		"value" => "|arg1:rows|",
		"max_length" => "12",
		),
	"timespan" => array(
		"method" => "textbox",
		"friendly_name" => "Timespan",
		"description" => "How many seconds to display in graph for this RRA.",
		"value" => "|arg1:timespan|",
		"max_length" => "12",
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"save_component_rra" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: data_queries.php, action: edit */
$fields_data_query_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "A name for this data query.",
		"value" => "|arg1:name|",
		"max_length" => "100",
		),
	"description" => array(
		"method" => "textbox",
		"friendly_name" => "Description",
		"description" => "A description for this data query.",
		"value" => "|arg1:description|",
		"size" => "80",
		"max_length" => "255",
		),
	"xml_path" => array(
		"method" => "textbox",
		"friendly_name" => "XML Path",
		"description" => "The full path to the XML file containing definitions for this data query.",
		"value" => "|arg1:xml_path|",
		"default" => "<path_cacti>/resource/",
		"size" => "80",
		"max_length" => "255",
		),
	"data_input_id" => array(
		"method" => "drop_sql",
		"friendly_name" => "Data Input Method",
		"description" => "Choose the input method for this Data Query.  This input method defines how data is collected for each Host associated with the Data Query.",
		"value" => "|arg1:data_input_id|",
		"sql" => "select id,name from data_input where (type_id=3 or type_id=4 or type_id=5 or type_id=6) order by name",
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|",
		),
	"save_component_snmp_query" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: data_queries.php, action: item_edit */
$fields_data_query_item_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "A name for this associated graph.",
		"value" => "|arg1:name|",
		"max_length" => "100",
		),
	"graph_template_id" => array(
		"method" => "drop_sql",
		"friendly_name" => "Graph Template",
		"description" => "Choose the Graph Template to use for this Data Query Graph Template item.",
		"value" => "|arg1:graph_template_id|",
		"sql" => "select id,name from graph_templates order by name",
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"snmp_query_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg2:snmp_query_id|"
		),
	"_graph_template_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:graph_template_id|"
		),
	"save_component_snmp_query_item" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: tree.php, action: edit */
$fields_tree_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "A useful name for this graph tree.",
		"value" => "|arg1:name|",
		"max_length" => "255",
		),
	"sort_type" => array(
		"method" => "drop_array",
		"friendly_name" => "Sorting Type",
		"description" => "Choose how items in this tree will be sorted.",
		"value" => "|arg1:sort_type|",
		"array" => $tree_sort_types,
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"save_component_tree" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

/* file: user_admin.php, action: user_edit (host) */
$fields_user_user_edit_host = array(
	"username" => array(
		"method" => "textbox",
		"friendly_name" => "User Name",
		"description" => "The login name for this user.",
		"value" => "|arg1:username|",
		"max_length" => "255"
		),
	"full_name" => array(
		"method" => "textbox",
		"friendly_name" => "Full Name",
		"description" => "A more descriptive name for this user, that can include spaces or special characters.",
		"value" => "|arg1:full_name|",
		"max_length" => "255"
		),
	"password" => array(
		"method" => "textbox_password",
		"friendly_name" => "Password",
		"description" => "Enter the password for this user twice. Remember that passwords are case sensitive!",
		"value" => "",
		"max_length" => "255"
		),
	"enabled" => array(
		"method" => "checkbox",
		"friendly_name" => "Enabled",
		"description" => "Determines if user is able to login.",
		"value" => "|arg1:enabled|",
		"default" => ""
		),
	"grp1" => array(
		"friendly_name" => "Account Options",
		"method" => "checkbox_group",
		"description" => "Set any user account-specific options here.",
		"items" => array(
			"must_change_password" => array(
				"value" => "|arg1:must_change_password|",
				"friendly_name" => "User Must Change Password at Next Login",
				"form_id" => "|arg1:id|",
				"default" => ""
				),
			"graph_settings" => array(
				"value" => "|arg1:graph_settings|",
				"friendly_name" => "Allow this User to Keep Custom Graph Settings",
				"form_id" => "|arg1:id|",
				"default" => "on"
				)
			)
		),
	"grp2" => array(
		"friendly_name" => "Graph Options",
		"method" => "checkbox_group",
		"description" => "Set any graph-specific options here.",
		"items" => array(
			"show_tree" => array(
				"value" => "|arg1:show_tree|",
				"friendly_name" => "User Has Rights to Tree View",
				"form_id" => "|arg1:id|",
				"default" => "on"
				),
			"show_list" => array(
				"value" => "|arg1:show_list|",
				"friendly_name" => "User Has Rights to List View",
				"form_id" => "|arg1:id|",
				"default" => "on"
				),
			"show_preview" => array(
				"value" => "|arg1:show_preview|",
				"friendly_name" => "User Has Rights to Preview View",
				"form_id" => "|arg1:id|",
				"default" => "on"
				)
			)
		),
	"login_opts" => array(
		"friendly_name" => "Login Options",
		"method" => "radio",
		"default" => "1",
		"description" => "What to do when this user logs in.",
		"value" => "|arg1:login_opts|",
		"items" => array(
			0 => array(
				"radio_value" => "1",
				"radio_caption" => "Show the page that user pointed their browser to."
				),
			1 => array(
				"radio_value" => "2",
				"radio_caption" => "Show the default console screen."
				),
			2 => array(
				"radio_value" => "3",
				"radio_caption" => "Show the default graph screen."
				)
			)
		),
	"realm" => array(
		"method" => "drop_array",
		"friendly_name" => "Authentication Realm",
		"description" => "Only used if you have LDAP or Web Basic Authentication enabled.  Changing this to an non-enabled realm will effectively disable the user.",
		"value" => "|arg1:realm|",
		"default" => 0,
		"array" => $auth_realms,
		),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	"_policy_graphs" => array(
		"method" => "hidden",
		"default" => "2",
		"value" => "|arg1:policy_graphs|"
		),
	"_policy_trees" => array(
		"method" => "hidden",
		"default" => "2",
		"value" => "|arg1:policy_trees|"
		),
	"_policy_hosts" => array(
		"method" => "hidden",
		"default" => "2",
		"value" => "|arg1:policy_hosts|"
		),
	"_policy_graph_templates" => array(
		"method" => "hidden",
		"default" => "2",
		"value" => "|arg1:policy_graph_templates|"
		),
	"save_component_user" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

$export_types = array(
	"graph_template" => array(
		"name" => "Graph Template",
		"title_sql" => "select name from graph_templates where id=|id|",
		"dropdown_sql" => "select id,name from graph_templates order by name"
		),
	"data_template" => array(
		"name" => "Data Template",
		"title_sql" => "select name from data_template where id=|id|",
		"dropdown_sql" => "select id,name from data_template order by name"
		),
	"host_template" => array(
		"name" => "Host Template",
		"title_sql" => "select name from host_template where id=|id|",
		"dropdown_sql" => "select id,name from host_template order by name"
		),
	"data_query" => array(
		"name" => "Data Query",
		"title_sql" => "select name from snmp_query where id=|id|",
		"dropdown_sql" => "select id,name from snmp_query order by name"
		)
	);

$fields_template_import = array(
	"import_file" => array(
		"friendly_name" => "Import Template from Local File",
		"description" => "If the XML file containing template data is located on your local machine, select it here.",
		"method" => "file"
		),
	"import_text" => array(
		"method" => "textarea",
		"friendly_name" => "Import Template from Text",
		"description" => "If you have the XML file containing template data as text, you can paste it into this box to import it.",
		"value" => "",
		"default" => "",
		"textarea_rows" => "10",
		"textarea_cols" => "50",
		"class" => "textAreaNotes"
		),
	"import_rra" => array(
		"friendly_name" => "Import RRA Settings",
		"description" => "Choose whether to allow Cacti to import custom RRA settings from imported templates or whether to use the defaults for this installation.",
		"method" => "radio",
		"value" => "",
		"default" => "1",
		"on_change" => "changeRRA()",
		"items" => array(
			0 => array(
				"radio_value" => "1",
				"radio_caption" => "Select your RRA settings below (Recommended)"
				),
			1 => array(
				"radio_value" => "2",
				"radio_caption" => "Use custom RRA settings from the template"
				),
			)
		),
	"rra_id" => array(
		"method" => "drop_multi_rra",
		"friendly_name" => "Associated RRA's",
		"description" => "Which RRA's to use when entering data (It is recommended that you <strong>deselect unwanted values</strong>).",
		"form_id" => "",
		"sql_all" => "select rra.id from rra where id in (1,2,3,4) order by id",
		),
	);

api_plugin_hook('config_form');
?>
