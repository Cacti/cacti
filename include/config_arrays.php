<?/* 
+-------------------------------------------------------------------------+
| Copyright (C) 2002 Ian Berry                                            |
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
| cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
+-------------------------------------------------------------------------+
| This code is currently maintained and debugged by Ian Berry, any        |
| questions or comments regarding this code should be directed to:        |
| - iberry@raxnet.net                                                     |
+-------------------------------------------------------------------------+
| - raXnet - http://www.raxnet.net/                                       |
+-------------------------------------------------------------------------+
*/?>
<?

$messages = array(
	1  => array(
		"message" => 'Save Successful.',
		"type" => "info"),
	2  => array(
		"message" => 'Save Failed',
		"type" => "error"),
	3  => array(
		"message" => 'Save Failed: Field Input Error (Check Red Fields)',
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
	"GPRINT");

$image_types = array(1 =>
	"PNG",
	"GIF");

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
		"default" => "",
		"description" => "The height (in pixels) that the graph is."
		),
	"width" => array(
		"title" => "Width",
		"type" => "text",
		"text_maxlen" => "50",
		"text_size" => "40",
		"default" => "",
		"description" => "The width (in pixels) that the graph is."
		),
	"auto_scale" => array(
		"title" => "Auto Scale",
		"type" => "check",
		"check_caption" => "Auto Scale",
		"default" => "on",
		"description" => ""
		),
	"auto_scale_opts" => array(
		"title" => "Auto Scale Options",
		"type" => "radio",
		"default" => "2",
		"description" => "",
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
		"description" => ""
		),
	"auto_scale_rigid" => array(
		"title" => "Rigid Boundaries Mode",
		"type" => "check",
		"check_caption" => "Use Rigid Boundaries Mode (--rigid)",
		"default" => "",
		"description" => ""
		),
	"auto_padding" => array(
		"title" => "Auto Padding",
		"type" => "check",
		"check_caption" => "Auto Padding",
		"default" => "on",
		"description" => "Pad text so that legend and graph data always line up. Note: this could cause 
			graphs to take longer to render because of the larger overhead. Also Auto Padding may not 
			be accurate on all types of graphs, consistant labeling usually helps."
		),
	"export" => array(
		"title" => "Allow Graph Export",
		"type" => "check",
		"check_caption" => "Allow Graph Export",
		"default" => "on",
		"description" => "Choose whether this graph will be included in the static html/png export if you use 
			cacti's export feature."
		),
	"upper_limit" => array(
		"title" => "Upper Limit",
		"type" => "text",
		"text_maxlen" => "50",
		"text_size" => "40",
		"default" => "",
		"description" => "The maximum vertical value for the rrd graph."
		),
	"lower_limit" => array(
		"title" => "Lower Limit",
		"type" => "text",
		"text_maxlen" => "255",
		"text_size" => "40",
		"default" => "",
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
		"default" => "1000",
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
			and data_template_data.local_data_id=data_local.id",
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
		"description" => " How data is to be represented on the graph."
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
		"description" => ""
		),
	"sequence" => array(
		"title" => "Sequence",
		"type" => "view"
		)
	);

$struct_data_source = array(
	"name",
	"active",
	"rrd_step",
	"FORCE:data_input_id");

$struct_data_source_item = array(
	"rrd_maximum",
	"rrd_minimum",
	"rrd_heartbeat",
	"data_source_type_id",
	"data_source_name",
	"FORCE:data_input_field_id");

$snmp_versions = array(1 =>
	"Version 1",
	"Version 2",
	"Version 3");

$registered_cacti_names = array(
	"path_cacti");
?>
