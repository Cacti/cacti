<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

$messages = array(
	1  => array(
		"message" => 'Save Successful.',
		"type" => "info"),
	2  => array(
		"message" => 'Save Failed',
		"type" => "error"),
	3  => array(
		"message" => 'Save Failed: Field Input Error (Check Red Fields)',
		"type" => "error"),
	4  => array(
		"message" => 'Passwords do not match, please retype.',
		"type" => "error"),
	5  => array(
		"message" => 'You must select at least one field.',
		"type" => "error"),
	6  => array(
		"message" => 'You must have built in user authentication turned on to use this feature.',
		"type" => "error")
		);

$cdef_operators = array(1 =>
	"+",
	"-",
	"*",
	"/",
	"%");

$cdef_functions = array(1 =>
	"SIN",
	"COS",
	"LOG",
	"EXP",
	"FLOOR",
	"CEIL",
	"LT",
	"LE",
	"GT",
	"GE",
	"EQ",
	"IF",
	"MIN",
	"MAX",
	"LIMIT",
	"DUP",
	"EXC",
	"POP",
	"UN",
	"UNKN",
	"PREV",
	"INF",
	"NEGINF",
	"NOW",
	"TIME",
	"LTIME");

$input_types = array(1 =>
	"Script/Command",
	"SNMP",
	"SNMP Query",
	"Script Query");

$snmp_query_field_actions = array(1 =>
	"SNMP Field Name (Dropdown)",
	"SNMP Field Value (From User)",
	"SNMP Output Type (Dropdown)");

$consolidation_functions = array(1 =>
	"AVERAGE",
	"MIN",
	"MAX",
	"LAST");
					
$data_source_types = array(1 =>
	"GAUGE",
	"COUNTER",
	"DERIVE",
	"ABSOLUTE");
				
$graph_item_types = array(1 =>
	"COMMENT",
	"HRULE",
	"VRULE",
	"LINE1",
	"LINE2",
	"LINE3",
	"AREA",
	"STACK",
	"GPRINT",
	"LEGEND");

$image_types = array(1 =>
	"PNG",
	"GIF");

$snmp_versions = array(1 =>
	"Version 1",
	"Version 2",
	"Version 3");

$registered_cacti_names = array(
	"path_cacti");

$graph_views = array(1 =>
	"Tree View",
	"List View",
	"Preview View");

$graph_tree_views = array(1 =>
	"Single Pane (0.6.x - 0.8.2a Default)",
	"Dual Pane");

$auth_realms = array(0 =>
	"Local",
	"LDAP");

$snmp_implimentations = array(
	"ucd-snmp" => "UCD-SNMP 4.x",
	"net-snmp" => "NET-SNMP 5.x");

$menu = array(
	"Graph Setup" => array(
		"graphs.php" => "Graph Management",
		"gprint_presets.php" => "GPRINT Presets",
		"cdef.php" => "CDEFs",
		"tree.php" => "Graph Trees",
		"color.php" => "Colors"
		),
	"Templates" => array(
		"graph_templates.php" => "Graph Templates",
		"host_templates.php" => "Host Templates",
		"data_templates.php" => "Data Templates"
		),
	"Data Gathering" => array(
		"data_sources.php" => "Data Sources",
		"host.php" => 'Polling Hosts',
		"rra.php" => "Available RRA's",
		"data_input.php" => "Data Input Methods",
		"snmp.php" => "Data Queries"
		),
	"Configuration"  => array(
		"utilities.php" => "Utilities",
		"settings.php" => "Cacti Settings"
		),
	"Utilities" => array(
		"user_admin.php" => "User Management",
		"logout.php" => "Logout User"
	));

$struct_graph = array(
	"title" => array(
		"friendly_name" => "Title",
		"method" => "textbox",
		"max_length" => "255",
		"default" => "",
		"description" => "The name that is printed on the graph."
		),
	"image_format_id" => array(
		"friendly_name" => "Image Format",
		"method" => "drop_array",
		"array" => $image_types,
		"default" => "1",
		"description" => "The type of graph that is generated; GIF or PNG."
		),
	"height" => array(
		"friendly_name" => "Height",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "120",
		"description" => "The height (in pixels) that the graph is."
		),
	"width" => array(
		"friendly_name" => "Width",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "500",
		"description" => "The width (in pixels) that the graph is."
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
		"description" => "Use --alt-autoscale-max to scale to the maximum value, or --alt-autoscale to scale to the absolute 
			minimum and maximum.",
		"items" => array(
			0 => array(
				"radio_value" => "1",
				"radio_caption" => "Use --alt-autoscale"
				),
			1 => array(
				"radio_value" => "2",
				"radio_caption" => "Use --alt-autoscale-max"
				)
			)
		),
	"auto_scale_log" => array(
		"friendly_name" => "Logarithmic Auto Scaling (--logarithmic)",
		"method" => "checkbox",
		"default" => "",
		"description" => "Use Logarithmic y-axis scaling"
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
		"friendly_name" => "Upper Limit",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "100",
		"description" => "The maximum vertical value for the rrd graph."
		),
	"lower_limit" => array(
		"friendly_name" => "Lower Limit",
		"method" => "textbox",
		"max_length" => "255",
		"default" => "0",
		"description" => "The minimum vertical value for the rrd graph."
		),
	"base_value" => array(
		"friendly_name" => "Base Value",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "1000",
		"description" => "Should be set to 1024 for memory and 1000 for traffic measurements."
		),
	"unit_value" => array(
		"friendly_name" => "Unit Value",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "",
		"description" => "(--unit) Sets the exponent value on the Y-axis for numbers. Note: This option was 
			recently added in rrdtool 1.0.36."
		),
	"unit_exponent_value" => array(
		"friendly_name" => "Unit Exponent Value",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "0",
		"description" => "What unit cacti should use on the Y-axis. Use 3 to display everything in 'k' or -6 
			to display everything in 'u' (micro)."
		),
	"vertical_label" => array(
		"friendly_name" => "Vertical Label",
		"method" => "textbox",
		"max_length" => "255",
		"default" => "",
		"description" => "The label vertically printed to the left of the graph."
		)
	);

$struct_graph_item = array(
	"task_item_id" => array(
		"friendly_name" => "Data Source",
		"method" => "drop_sql",
		"sql" => "select
			CONCAT_WS('',case when host.description is null then 'No Host' when host.description is not null then host.description end,' - ',data_template_data.name,' (',data_template_rrd.data_source_name,')') as name,
			data_template_rrd.id 
			from data_template_data,data_template_rrd,data_local 
			left join host on data_local.host_id=host.id
			where data_template_rrd.local_data_id=data_local.id 
			and data_template_data.local_data_id=data_local.id
			order by name",
		"default" => "0",
		"none_value" => "None",
		"description" => "The task to use for this graph item; not used for COMMENT fields."
		),
	"color_id" => array(
		"friendly_name" => "Color",
		"method" => "drop_color",
		"default" => "0",
		"description" => "The task to use for this graph item; not used for COMMENT fields."
		),
	"graph_type_id" => array(
		"friendly_name" => "Graph Item Type",
		"method" => "drop_array",
		"array" => $graph_item_types,
		"default" => "0",
		"description" => "How data for this item is displayed."
		),
	"consolidation_function_id" => array(
		"friendly_name" => "Consolidation Function",
		"method" => "drop_array",
		"array" => $consolidation_functions,
		"default" => "0",
		"description" => "How data is to be represented on the graph."
		),
	"cdef_id" => array(
		"friendly_name" => "CDEF Function",
		"method" => "drop_sql",
		"sql" => "select id,name from cdef order by name",
		"default" => "0",
		"null_item" => "None",
		"description" => "A CDEF Function to apply to this item on the graph."
		),
	"value" => array(
		"friendly_name" => "Value",
		"method" => "textbox",
		"max_length" => "50",
		"default" => "",
		"description" => "For use with VRULE and HRULE, <em>numbers</em> only."
		),
	"gprint_id" => array(
		"friendly_name" => "GPRINT Type",
		"method" => "drop_sql",
		"sql" => "select id,name from graph_templates_gprint order by name",
		"default" => "2",
		"null_item" => "",
		"description" => "If this graph item is a GPRINT, you can optionally choose another format 
			here. You can define additional types under \"Graph Templates\"."
		),
	"text_format" => array(
		"friendly_name" => "Text Format",
		"method" => "textbox",
		"max_length" => "255",
		"default" => "",
		"description" => "The text of the comment or legend, input and output keywords are allowed."
		),
	"hard_return" => array(
		"friendly_name" => "Insert Hard Return",
		"method" => "checkbox",
		"default" => "",
		"description" => ""
		),
	"sequence" => array(
		"friendly_name" => "Sequence",
		"method" => "view"
		)
	);

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
		"friendly_name" => "Data Input Source",
		"method" => "drop_sql",
		"sql" => "select id,name from data_input order by name",
		"default" => "",
		"null_item" => "None",
		"description" => "The script/source used to gather data for this data source.",
		"flags" => "ALWAYSTEMPLATE"
		),
	"rra_id" => array(
		"method" => "drop_multi",
		"friendly_name" => "Associated RRA's",
		"description" => "Which RRA's to use when entering data. (It is recommended that you select all of these values).",
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

$struct_data_source_item = array(
	"data_source_name" => array(
		"friendly_name" => "Internal Data Source Name",
		"method" => "textbox",
		"max_length" => "19",
		"default" => "",
		"description" => "Choose unique name to represent this piece of data inside of the rrd file."
		),
	"rrd_minimum" => array(
		"friendly_name" => "Minimum Value",
		"method" => "textbox",
		"max_length" => "20",
		"size" => "30",
		"default" => "0",
		"description" => "The minimum value of data that is allowed to be collected."
		),
	"rrd_maximum" => array(
		"friendly_name" => "Maximum Value",
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

?>
