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

function graph_template_to_xml($graph_template_id) {
	global $struct_graph, $fields_graph_template_input_edit;
	
	$xml_text = "";
	
	$graph_template = db_fetch_row("select id,name from graph_templates where id=$graph_template_id");
	$graph_template_graph = db_fetch_row("select * from graph_templates_graph where graph_template_id=$graph_template_id and local_graph_id=0");
	$graph_template_items = db_fetch_assoc("select * from graph_templates_item where graph_template_id=$graph_template_id and local_graph_id=0");
	$graph_template_inputs = db_fetch_assoc("select * from graph_template_input where graph_template_id=$graph_template_id");
	
	if ((empty($graph_template["id"])) || (empty($graph_template_graph["id"]))) {
		return "Invalid graph template.";
	}
	
	$xml_text .= "<graph_template|DF34V3476WW4H6WF>\n\t<name>" . $graph_template["name"] . "</name>\n\t<graph>\n";
	
	while (list($field_name, $field_array) = each($struct_graph)) {
		$xml_text .= "\t\t<t_$field_name>" . $graph_template_graph{"t_" . $field_name} . "</t_$field_name>\n";
		$xml_text .= "\t\t<$field_name>" . $graph_template_graph{$field_name} . "</$field_name>\n";
	}
	
	$xml_text .= "\t</graph>\n\t<inputs>\n";
	
	$i = 0;
	if (sizeof($graph_template_inputs) > 0) {
	foreach ($graph_template_inputs as $item) {
		$xml_text .= "\t\t<" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		reset($fields_graph_template_input_edit);
		while (list($field_name, $field_array) = each($fields_graph_template_input_edit)) {
			if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
				$xml_text .= "\t\t\t<$field_name>" . $item{$field_name} . "</$field_name>\n";
			}
		}
		
		$xml_text .= "\t\t</" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		$i++;
	}
	}
	/*
	<inputs>
				<001>
					<name>Free Data Source</name>
					<description></description>
					<column_name>task_item_id</column_name>
					<items>001|002|003|004</items>
				</001>
				<001>
					<name>Swap Data Source</name>
					<description></description>
					<column_name>task_item_id</column_name>
					<items>005|006|007|008</items>
				</001>
			</inputs>
	*/
	
	$xml_text .= "\t<inputs>\n";
	$xml_text .= "</graph_template|DF34V3476WW4H6WF>";
	
	return $xml_text;
}

?>
