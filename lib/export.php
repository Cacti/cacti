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
	global $struct_graph, $fields_graph_template_input_edit, $struct_graph_item;
	
	$hash = get_hash_graph_template($graph_template_id);
	$xml_text = "";
	
	$graph_template = db_fetch_row("select id,name from graph_templates where id=$graph_template_id");
	$graph_template_graph = db_fetch_row("select * from graph_templates_graph where graph_template_id=$graph_template_id and local_graph_id=0");
	$graph_template_items = db_fetch_assoc("select * from graph_templates_item where graph_template_id=$graph_template_id and local_graph_id=0 order by sequence");
	$graph_template_inputs = db_fetch_assoc("select * from graph_template_input where graph_template_id=$graph_template_id");
	
	if ((empty($graph_template["id"])) || (empty($graph_template_graph["id"]))) {
		return "Invalid graph template.";
	}
	
	/* XML Branch: <graph> */
	
	$xml_text .= "<hash:$hash>\n\t<name>" . $graph_template["name"] . "</name>\n\t<graph>\n";
	
	while (list($field_name, $field_array) = each($struct_graph)) {
		$xml_text .= "\t\t<t_$field_name>" . $graph_template_graph{"t_" . $field_name} . "</t_$field_name>\n";
		$xml_text .= "\t\t<$field_name>" . $graph_template_graph{$field_name} . "</$field_name>\n";
	}
	
	$xml_text .= "\t</graph>\n";
	
	/* XML Branch: <items> */
	
	$xml_text .= "\t<items>\n";
	
	$i = 0;
	if (sizeof($graph_template_items) > 0) {
	foreach ($graph_template_items as $item) {
		$xml_text .= "\t\t<item:" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		reset($struct_graph_item);
		while (list($field_name, $field_array) = each($struct_graph_item)) {
			$xml_text .= "\t\t\t<$field_name>" . $item{$field_name} . "</$field_name>\n";
		}
		
		$xml_text .= "\t\t</item:" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		$cache_graph_item_id_to_sequence{$item["id"]} = str_pad(strval($i), 3, "0", STR_PAD_LEFT);
		
		$i++;
	}
	}
	
	$xml_text .= "\t</items>\n";
	
	/* XML Branch: <inputs> */
	
	$xml_text .= "\t<inputs>\n";
	
	$i = 0;
	if (sizeof($graph_template_inputs) > 0) {
	foreach ($graph_template_inputs as $item) {
		$xml_text .= "\t\t<item:" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		reset($fields_graph_template_input_edit);
		while (list($field_name, $field_array) = each($fields_graph_template_input_edit)) {
			if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
				$xml_text .= "\t\t\t<$field_name>" . $item{$field_name} . "</$field_name>\n";
			}
		}
		
		$graph_template_input_items = db_fetch_assoc("select graph_template_item_id from graph_template_input_defs where graph_template_input_id=" . $item["id"]);
		
		$xml_text .= "\t\t\t<items>";
		
		$j = 0;
		if (sizeof($graph_template_input_items) > 0) {
		foreach ($graph_template_input_items as $item2) {
			if (isset($cache_graph_item_id_to_sequence{$item2["graph_template_item_id"]})) {
				$xml_text .= $cache_graph_item_id_to_sequence{$item2["graph_template_item_id"]};
			}
			
			if (($j+1) < sizeof($graph_template_input_items)) {
				$xml_text .= "|";
			}
			
			$j++;
		}
		}
		
		$xml_text .= "</items>\n";
		$xml_text .= "\t\t</item:" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		$i++;
	}
	}
	
	$xml_text .= "\t</inputs>\n";
	$xml_text .= "</hash:$hash>";
	
	return $xml_text;
}

function data_template_to_xml($data_template_id) {
	global $struct_data_source, $struct_data_source_item;
	
	$hash = get_hash_data_template($data_template_id);
	$xml_text = "";
	
	$data_template = db_fetch_row("select id,name from data_template where id=$data_template_id");
	$data_template_data = db_fetch_row("select * from data_template_data where data_template_id=$data_template_id and local_data_id=0");
	$data_template_rrd = db_fetch_assoc("select * from data_template_rrd where data_template_id=$data_template_id and local_data_id=0");
	$data_template_data_rra = db_fetch_assoc("select * from data_template_data_rra where data_template_data_id=" . $data_template_data["id"]);
	
	$data_input_data = db_fetch_assoc("select
		data_input_data.t_value,
		data_input_data.value,
		data_input_fields.data_name
		from data_input_data,data_input_fields
		where data_input_data.data_input_field_id=data_input_fields.id
		and data_input_data.data_template_data_id=" . $data_template_data["id"]);
		
	if ((empty($data_template["id"])) || (empty($data_template_data["id"]))) {
		return "Invalid data template.";
	}
	
	/* XML Branch: <graph> */
	
	$xml_text .= "<hash:$hash>\n\t<name>" . $data_template["name"] . "</name>\n\t<ds>\n";
	
	while (list($field_name, $field_array) = each($struct_data_source)) {
		if (isset($data_template_data{"t_" . $field_name})) {
			$xml_text .= "\t\t<t_$field_name>" . $data_template_data{"t_" . $field_name} . "</t_$field_name>\n";
		}
		
		if (isset($data_template_data{$field_name})) {
			$xml_text .= "\t\t<$field_name>" . $data_template_data{$field_name} . "</$field_name>\n";
		}
	}
	
	$xml_text .= "\t\t<rra_id>";
	
	$i = 0;
	if (sizeof($data_template_data_rra) > 0) {
	foreach ($data_template_data_rra as $item) {
		$xml_text .= $item["rra_id"];
		
		if (($i+1) < sizeof($data_template_data_rra)) {
			$xml_text .= "|";
		}
		
		$i++;
	}
	}
	
	$xml_text .= "</rra_id>\n";
	$xml_text .= "\t</ds>\n";
	
	/* XML Branch: <items> */
	
	$xml_text .= "\t<items>\n";
	
	$i = 0;
	if (sizeof($data_template_rrd) > 0) {
	foreach ($data_template_rrd as $item) {
		$xml_text .= "\t\t<item:" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		reset($struct_data_source_item);
		while (list($field_name, $field_array) = each($struct_data_source_item)) {
			$xml_text .= "\t\t\t<$field_name>" . $item{$field_name} . "</$field_name>\n";
		}
		
		$xml_text .= "\t\t</item:" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		$i++;
	}
	}
	
	$xml_text .= "\t</items>\n";
	
	/* XML Branch: <data> */
	
	$xml_text .= "\t<data>\n";
	
	if (sizeof($data_input_data) > 0) {
	foreach ($data_input_data as $item) {
		$xml_text .= "\t\t<" . $item["data_name"] . ">\n";
		
		$xml_text .= "\t\t\t<t_value>" . $item{"t_value"} . "</t_value>\n";
		$xml_text .= "\t\t\t<value>" . $item{"value"} . "</value>\n";
		
		$xml_text .= "\t\t</" . $item["data_name"] . ">\n";
	}
	}
	
	$xml_text .= "\t</data>\n";
	
	$xml_text .= "</hash:$hash>";
	
	return $xml_text;
}

function data_input_method_to_xml($data_input_id) {
	global $fields_data_input_edit, $fields_data_input_field_edit;
	
	$hash = get_hash_data_input($data_input_id);
	$xml_text = "";
	
	$data_input = db_fetch_row("select * from data_input where id=$data_input_id");
	$data_input_fields = db_fetch_assoc("select * from data_input_fields where data_input_id=$data_input_id");
	
	if (empty($data_input["id"])) {
		return "Invalid data input method.";
	}
	
	$xml_text .= "<hash:$hash>\n";
	
	/* XML Branch: <> */
	
	while (list($field_name, $field_array) = each($fields_data_input_edit)) {
		if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
			$xml_text .= "\t<$field_name>" . $data_input{$field_name} . "</$field_name>\n";
		}
	}
	
	/* XML Branch: <fields> */
	
	$xml_text .= "\t<fields>\n";
	
	if (sizeof($data_input_fields) > 0) {
	foreach ($data_input_fields as $item) {
		$xml_text .= "\t\t<" . $item["data_name"] . ">\n";
		
		reset($fields_data_input_field_edit);
		while (list($field_name, $field_array) = each($fields_data_input_field_edit)) {
			if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
				$xml_text .= "\t\t\t<$field_name>" . $item{$field_name} . "</$field_name>\n";
			}
		}
		
		$xml_text .= "\t\t</" . $item["data_name"] . ">\n";
	}
	}
	
	$xml_text .= "\t</fields>\n";
	
	$xml_text .= "</hash:$hash>";
	
	return $xml_text;
}

function cdef_to_xml($cdef_id) {
	global $fields_cdef_edit;
	
	$fields_cdef_item_edit = array(
		"sequence" => "sequence",
		"type" => "type",
		"value" => "value"
		);
	
	$hash = get_hash_cdef($cdef_id);
	$xml_text = "";
	
	$cdef = db_fetch_row("select * from cdef where id=$cdef_id");
	$cdef_items = db_fetch_assoc("select * from cdef_items where cdef_id=$cdef_id order by sequence");
	
	if (empty($cdef["id"])) {
		return "Invalid CDEF.";
	}
	
	$xml_text .= "<hash:$hash>\n";
	
	/* XML Branch: <> */
	
	while (list($field_name, $field_array) = each($fields_cdef_edit)) {
		if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
			$xml_text .= "\t<$field_name>" . $cdef{$field_name} . "</$field_name>\n";
		}
	}
	
	/* XML Branch: <items> */
	
	$xml_text .= "\t<items>\n";
	
	$i = 0;
	if (sizeof($cdef_items) > 0) {
	foreach ($cdef_items as $item) {
		$xml_text .= "\t\t<item:" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		reset($fields_cdef_item_edit);
		while (list($field_name, $field_array) = each($fields_cdef_item_edit)) {
			if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
				$xml_text .= "\t\t\t<$field_name>" . $item{$field_name} . "</$field_name>\n";
			}
		}
		
		$xml_text .= "\t\t</item:" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		$i++;
	}
	}
	
	$xml_text .= "\t</items>\n";
	
	$xml_text .= "</hash:$hash>";
	
	return $xml_text;
}

function gprint_preset_to_xml($gprint_preset_id) {
	global $fields_grprint_presets_edit;
	
	$hash = get_hash_gprint($gprint_preset_id);
	$xml_text = "";
	
	$graph_templates_gprint = db_fetch_row("select * from graph_templates_gprint where id=$gprint_preset_id");
	
	if (empty($graph_templates_gprint["id"])) {
		return "Invalid GPRINT preset.";
	}
	
	$xml_text .= "<hash:$hash>\n";
	
	/* XML Branch: <> */
	
	while (list($field_name, $field_array) = each($fields_grprint_presets_edit)) {
		if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
			$xml_text .= "\t<$field_name>" . $graph_templates_gprint{$field_name} . "</$field_name>\n";
		}
	}
	
	$xml_text .= "</hash:$hash>";
	
	return $xml_text;
}

function data_query_to_xml($data_query_id) {
	global $fields_data_query_edit, $fields_data_query_item_edit;
	
	$hash = get_hash_data_query($data_query_id);
	$xml_text = "";
	
	$snmp_query = db_fetch_row("select * from snmp_query where id=$data_query_id");
	$snmp_query_graph = db_fetch_assoc("select * from snmp_query_graph where snmp_query_id=$data_query_id");
	
	$snmp_query_field = db_fetch_assoc("select
		snmp_query_field.action_id,
		data_input_fields.data_name
		from snmp_query_field,data_input_fields
		where snmp_query_field.data_input_field_id=data_input_fields.id
		and snmp_query_field.snmp_query_id=$data_query_id");
	
	if (empty($snmp_query["id"])) {
		return "Invalid data query.";
	}
	
	$xml_text .= "<hash:$hash>\n";
	
	/* XML Branch: <> */
	
	while (list($field_name, $field_array) = each($fields_data_query_edit)) {
		if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
			$xml_text .= "\t<$field_name>" . htmlspecialchars($snmp_query{$field_name}) . "</$field_name>\n";
		}
	}
	
	/* XML Branch: <fields> */
	
	$xml_text .= "\t<fields>\n";
	
	if (sizeof($snmp_query_field) > 0) {
	foreach ($snmp_query_field as $item) {
		$xml_text .= "\t\t<" . $item["data_name"] . ">\n";
		
		$xml_text .= "\t\t\t<action_id>" . $item{"action_id"} . "</action_id>\n";
		
		$xml_text .= "\t\t</" . $item["data_name"] . ">\n";
	}
	}
	
	$xml_text .= "\t</fields>\n";
	
	/* XML Branch: <graphs> */
	
	$xml_text .= "\t<graphs>\n";
	
	$i = 0;
	if (sizeof($snmp_query_graph) > 0) {
	foreach ($snmp_query_graph as $item) {
		$xml_text .= "\t\t<item:" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		reset($fields_data_query_item_edit);
		while (list($field_name, $field_array) = each($fields_data_query_item_edit)) {
			if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
				$xml_text .= "\t\t\t<$field_name>" . $item{$field_name} . "</$field_name>\n";
			}
		}
		
		$snmp_query_graph_rrd_sv = db_fetch_assoc("select * from snmp_query_graph_rrd_sv where snmp_query_graph_id=" . $item["id"] . " order by sequence");
		$snmp_query_graph_sv = db_fetch_assoc("select * from snmp_query_graph_sv where snmp_query_graph_id=" . $item["id"] . " order by sequence");
		
		$snmp_query_graph_rrd = db_fetch_assoc("select
			snmp_query_graph_rrd.snmp_field_name,
			snmp_query_graph_rrd.data_template_id,
			data_template_rrd.data_source_name
			from snmp_query_graph_rrd,data_template_rrd
			where snmp_query_graph_rrd.data_template_rrd_id=data_template_rrd.id
			and snmp_query_graph_rrd.snmp_query_graph_id=" . $item["id"]);
		
		/* XML Branch: <graphs/rrd> */
		
		$xml_text .= "\t\t\t<rrd>\n";
		
		if (sizeof($snmp_query_graph_rrd) > 0) {
		foreach ($snmp_query_graph_rrd as $item2) {
			$xml_text .= "\t\t\t\t<item:" . $item2["data_source_name"] . ">\n";
			
			$xml_text .= "\t\t\t\t\t<snmp_field_name>" . $item2{"snmp_field_name"} . "</snmp_field_name>\n";
			$xml_text .= "\t\t\t\t\t<data_template_id>" . $item2{"data_template_id"} . "</data_template_id>\n";
			
			$xml_text .= "\t\t\t\t</item:" . $item2["data_source_name"] . ">\n";
		}
		}
		
		$xml_text .= "\t\t\t</rrd>\n";
		
		/* XML Branch: <graphs/sv_graph> */
		
		$xml_text .= "\t\t\t<sv_graph>\n";
		
		$j = 0;
		if (sizeof($snmp_query_graph_sv) > 0) {
		foreach ($snmp_query_graph_sv as $item2) {
			$xml_text .= "\t\t\t\t<item:" . str_pad(strval($j), 3, "0", STR_PAD_LEFT) . ">\n";
			
			$xml_text .= "\t\t\t\t\t<field_name>" . $item2{"field_name"} . "</field_name>\n";
			$xml_text .= "\t\t\t\t\t<text>" . $item2{"text"} . "</text>\n";
			
			$xml_text .= "\t\t\t\t</item:" . str_pad(strval($j), 3, "0", STR_PAD_LEFT) . ">\n";
			
			$j++;
		}
		}
		
		$xml_text .= "\t\t\t</sv_graph>\n";
		
		/* XML Branch: <graphs/sv_data_source> */
		
		$xml_text .= "\t\t\t<sv_data_source>\n";
		
		$j = 0;
		if (sizeof($snmp_query_graph_rrd_sv) > 0) {
		foreach ($snmp_query_graph_rrd_sv as $item2) {
			$xml_text .= "\t\t\t\t<item:" . str_pad(strval($j), 3, "0", STR_PAD_LEFT) . ">\n";
			
			$xml_text .= "\t\t\t\t\t<field_name>" . $item2{"field_name"} . "</field_name>\n";
			$xml_text .= "\t\t\t\t\t<data_template_id>" . $item2{"data_template_id"} . "</data_template_id>\n";
			$xml_text .= "\t\t\t\t\t<text>" . $item2{"text"} . "</text>\n";
			
			$xml_text .= "\t\t\t\t</item:" . str_pad(strval($j), 3, "0", STR_PAD_LEFT) . ">\n";
			
			$j++;
		}
		}
		
		$xml_text .= "\t\t\t</sv_data_source>\n";
		
		$xml_text .= "\t\t</item:" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";
		
		$i++;
	}
	}
	
	$xml_text .= "\t</graphs>\n";
	
	$xml_text .= "</hash:$hash>";
	
	return $xml_text;
}

?>
