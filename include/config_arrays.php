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

$menu = array(
	"Graph Setup" => array(
		"graphs.php" => "Graph Management",
		"gprint_presets.php" => "GPRINT Presets",
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
		"snmp.php" => "Data Queries",
		"cdef.php" => "CDEF's"
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
		"title" => "Title",
		"type" => "text",
		"text_maxlen" => "255",
		"text_size" => "40",
		"default" => "",
		"description" => "The name that is printed on the graph."
		),
	"image_format_id" => array(
		"title" => "Image Format",
		"type" => "drop_array",
		"array_name" => "image_types",
		"default" => "1",
		"description" => "The type of graph that is generated; GIF or PNG."
		),
	"height" => array(
		"title" => "Height",
		"type" => "text",
		"text_maxlen" => "50",
		"text_size" => "40",
		"default" => "120",
		"description" => "The height (in pixels) that the graph is."
		),
	"width" => array(
		"title" => "Width",
		"type" => "text",
		"text_maxlen" => "50",
		"text_size" => "40",
		"default" => "500",
		"description" => "The width (in pixels) that the graph is."
		),
	"auto_scale" => array(
		"title" => "Auto Scale",
		"type" => "check",
		"check_caption" => "Auto Scale",
		"default" => "on",
		"description" => "Auto scale the y-axis instead of defining an upper and lower limit. Note: if this is check both the 
			Upper and Lower limit will be ignored.",
		"check_id" => "id"
		),
	"auto_scale_opts" => array(
		"title" => "Auto Scale Options",
		"type" => "radio",
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
		"title" => "Logarithmic Auto Scaling",
		"type" => "check",
		"check_caption" => "Logarithmic Auto Scaling (--logarithmic)",
		"default" => "",
		"description" => "Use Logarithmic y-axis scaling",
		"check_id" => "id"
		),
	"auto_scale_rigid" => array(
		"title" => "Rigid Boundaries Mode",
		"type" => "check",
		"check_caption" => "Use Rigid Boundaries Mode (--rigid)",
		"default" => "",
		"description" => "Do not expand the lower and upper limit if the graph contains a value outside the valid range.",
		"check_id" => "id"
		),
	"auto_padding" => array(
		"title" => "Auto Padding",
		"type" => "check",
		"check_caption" => "Auto Padding",
		"default" => "on",
		"description" => "Pad text so that legend and graph data always line up. Note: this could cause 
			graphs to take longer to render because of the larger overhead. Also Auto Padding may not 
			be accurate on all types of graphs, consistant labeling usually helps.",
		"check_id" => "id"
		),
	"export" => array(
		"title" => "Allow Graph Export",
		"type" => "check",
		"check_caption" => "Allow Graph Export",
		"default" => "on",
		"description" => "Choose whether this graph will be included in the static html/png export if you use 
			cacti's export feature.",
		"check_id" => "id"
		),
	"upper_limit" => array(
		"title" => "Upper Limit",
		"type" => "text",
		"text_maxlen" => "50",
		"text_size" => "40",
		"default" => "100",
		"description" => "The maximum vertical value for the rrd graph."
		),
	"lower_limit" => array(
		"title" => "Lower Limit",
		"type" => "text",
		"text_maxlen" => "255",
		"text_size" => "40",
		"default" => "0",
		"description" => "The minimum vertical value for the rrd graph."
		),
	"base_value" => array(
		"title" => "Base Value",
		"type" => "text",
		"text_maxlen" => "50",
		"text_size" => "40",
		"default" => "1000",
		"description" => "Should be set to 1024 for memory and 1000 for traffic measurements."
		),
	"unit_value" => array(
		"title" => "Unit Value",
		"type" => "text",
		"text_maxlen" => "50",
		"text_size" => "40",
		"default" => "",
		"description" => "(--unit) Sets the exponent value on the Y-axis for numbers. Note: This option was 
			recently added in rrdtool 1.0.36."
		),
	"unit_exponent_value" => array(
		"title" => "Unit Exponent Value",
		"type" => "text",
		"text_maxlen" => "50",
		"text_size" => "40",
		"default" => "0",
		"description" => "What unit cacti should use on the Y-axis. Use 3 to display everything in 'k' or -6 
			to display everything in 'u' (micro)."
		),
	"vertical_label" => array(
		"title" => "Vertical Label",
		"type" => "text",
		"text_maxlen" => "255",
		"text_size" => "40",
		"default" => "",
		"description" => "The label vertically printed to the left of the graph."
		)
	);

$struct_graph_item = array(
	"task_item_id" => array(
		"title" => "Data Source",
		"type" => "drop_sql",
		"sql" => "select
			CONCAT_WS('',case when host.description is null then 'No Host' when host.description is not null then host.description end,' - ',data_template_data.name,' (',data_template_rrd.data_source_name,')') as name,
			data_template_rrd.id 
			from data_template_data,data_template_rrd,data_local 
			left join host on data_local.host_id=host.id
			where data_template_rrd.local_data_id=data_local.id 
			and data_template_data.local_data_id=data_local.id
			order by name",
		"default" => "0",
		"null_item" => "None",
		"description" => "The task to use for this graph item; not used for COMMENT fields."
		),
	"color_id" => array(
		"title" => "Color",
		"type" => "drop_color",
		"default" => "0",
		"description" => "The task to use for this graph item; not used for COMMENT fields."
		),
	"graph_type_id" => array(
		"title" => "Graph Item Type",
		"type" => "drop_array",
		"array_name" => "graph_item_types",
		"default" => "0",
		"null_item" => "",
		"description" => "How data for this item is displayed."
		),
	"consolidation_function_id" => array(
		"title" => "Consolidation Function",
		"type" => "drop_array",
		"array_name" => "consolidation_functions",
		"default" => "0",
		"null_item" => "",
		"description" => "How data is to be represented on the graph."
		),
	"cdef_id" => array(
		"title" => "CDEF Function",
		"type" => "drop_sql",
		"sql" => "select id,name from cdef order by name",
		"default" => "0",
		"null_item" => "None",
		"description" => "A CDEF Function to apply to this item on the graph."
		),
	"value" => array(
		"title" => "Value",
		"type" => "text",
		"text_maxlen" => "50",
		"text_size" => "30",
		"default" => "",
		"description" => "For use with VRULE and HRULE, <em>numbers</em< only."
		),
	"gprint_id" => array(
		"title" => "GPRINT Type",
		"type" => "drop_sql",
		"sql" => "select id,name from graph_templates_gprint order by name",
		"default" => "2",
		"null_item" => "",
		"description" => "If this graph item is a GPRINT, you can optionally choose another format 
			here. You can define additional types under \"Graph Templates\"."
		),
	"text_format" => array(
		"title" => "Text Format",
		"type" => "text",
		"text_maxlen" => "50",
		"text_size" => "40",
		"default" => "",
		"description" => "The text of the comment or legend, input and output keywords are allowed."
		),
	"hard_return" => array(
		"title" => "Hard Return",
		"type" => "check",
		"check_caption" => "Insert Hard Return",
		"default" => "",
		"description" => "",
		"check_id" => "id"
		),
	"sequence" => array(
		"title" => "Sequence",
		"type" => "view"
		)
	);

$struct_data_source = array(
	"name" => array(
		"title" => "Name",
		"type" => "text",
		"text_maxlen" => "250",
		"text_size" => "40",
		"default" => "",
		"description" => "Choose a name for this data source.",
		"flags" => ""
		),
	"data_source_path" => array(
		"title" => "Data Source Path",
		"type" => "text",
		"text_maxlen" => "255",
		"text_size" => "40",
		"default" => "",
		"description" => "The full path to the RRD file.",
		"flags" => "NOTEMPLATE"
		),
	"data_input_id" => array(
		"title" => "Data Input Source",
		"type" => "drop_sql",
		"sql" => "select id,name from data_input order by name",
		"default" => "",
		"null_item" => "None",
		"description" => "The script/source used to gather data for this data source.",
		"flags" => "ALWAYSTEMPLATE"
		),
	"rra_id" => array(
		"type" => "custom",
		"title" => "Associated RRA's",
		"description" => "Which RRA's to use when entering data. (It is recommended that you select all of these values).",
		"flags" => ""
		),
	"rrd_step" => array(
		"title" => "Step",
		"type" => "text",
		"text_maxlen" => "10",
		"text_size" => "20",
		"default" => "300",
		"description" => "The amount of time in seconds between expected updates.",
		"flags" => ""
		),
	"active" => array(
		"title" => "Data Source Active",
		"type" => "check",
		"check_caption" => "Data Source Active",
		"default" => "on",
		"description" => "Whether cacti should gather data for this data source or not.",
		"flags" => "",
		"check_id" => "id"
		)
	);

$struct_data_source_item = array(
	"data_source_name" => array(
		"title" => "Internal Data Source Name",
		"type" => "text",
		"text_maxlen" => "19",
		"text_size" => "40",
		"default" => "",
		"description" => "Choose unique name to represent this piece of data inside of the rrd file."
		),
	"rrd_minimum" => array(
		"title" => "Minimum Value",
		"type" => "text",
		"text_maxlen" => "20",
		"text_size" => "30",
		"default" => "0",
		"description" => "The minimum value of data that is allowed to be collected."
		),
	"rrd_maximum" => array(
		"title" => "Maximum Value",
		"type" => "text",
		"text_maxlen" => "20",
		"text_size" => "30",
		"default" => "0",
		"description" => "The maximum value of data that is allowed to be collected."
		),
	"data_source_type_id" => array(
		"title" => "Data Source Type",
		"type" => "drop_array",
		"array_name" => "data_source_types",
		"default" => "",
		"null_item" => "",
		"description" => "How data is represented in the RRA."
		),
	"rrd_heartbeat" => array(
		"title" => "Heartbeat",
		"type" => "text",
		"text_maxlen" => "20",
		"text_size" => "30",
		"default" => "600",
		"description" => "The maximum amount of time that can pass before data is entered as \"unknown\". 
			(Usually 2x300=600)"
		),
	"data_input_field_id" => array(
		"title" => "Output Field",
		"type" => "drop_sql",
		"default" => "0",
		"null_item" => "",
		"description" => "When data is gathered, the data for this field will be put into this data source."
		)
	);

?>
