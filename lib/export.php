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

function graph_template_to_xml($graph_template_id) {
	global $struct_graph, $fields_graph_template_input_edit, $struct_graph_item, $export_errors;

	$hash["graph_template"] = get_hash_version("graph_template") . get_hash_graph_template($graph_template_id);
	$xml_text = "";

	$graph_template = db_fetch_row("select id,name from graph_templates where id=$graph_template_id");
	$graph_template_graph = db_fetch_row("select * from graph_templates_graph where graph_template_id=$graph_template_id and local_graph_id=0");
	$graph_template_items = db_fetch_assoc("select * from graph_templates_item where graph_template_id=$graph_template_id and local_graph_id=0 order by sequence");
	$graph_template_inputs = db_fetch_assoc("select * from graph_template_input where graph_template_id=$graph_template_id");

	if ((empty($graph_template["id"])) || (empty($graph_template_graph["id"]))) {
		$export_errors++;
		raise_message(30);
		cacti_log("ERROR: Invalid Graph Template found in Database.  Please run database repair script to identify and/or correct.", false, "WEBUI");
		return;
	}

	$xml_text .= "<hash_" . $hash["graph_template"] . ">\n\t<name>" . xml_character_encode($graph_template["name"]) . "</name>\n\t<graph>\n";

	/* XML Branch: <graph> */
	reset($struct_graph);
	while (list($field_name, $field_array) = each($struct_graph)) {
		$xml_text .= "\t\t<t_$field_name>" . xml_character_encode($graph_template_graph{"t_" . $field_name}) . "</t_$field_name>\n";
		$xml_text .= "\t\t<$field_name>" . xml_character_encode($graph_template_graph{$field_name}) . "</$field_name>\n";
	}

	$xml_text .= "\t</graph>\n";

	/* XML Branch: <items> */

	$xml_text .= "\t<items>\n";

	$i = 0;
	if (sizeof($graph_template_items) > 0) {
	foreach ($graph_template_items as $item) {
		$hash["graph_template_item"] = get_hash_version("graph_template_item") . get_hash_graph_template($item["id"], "graph_template_item");

		$xml_text .= "\t\t<hash_" . $hash["graph_template_item"] . ">\n";

		reset($struct_graph_item);
		while (list($field_name, $field_array) = each($struct_graph_item)) {
			if (($field_name == "task_item_id") && (!empty($item{$field_name}))) {
				$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version("data_template_item") . get_hash_data_template($item{$field_name}, "data_template_item") . "</$field_name>\n";
			}elseif (($field_name == "cdef_id") && (!empty($item{$field_name}))) {
				$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version("cdef") . get_hash_cdef($item{$field_name}) . "</$field_name>\n";
			}elseif (($field_name == "gprint_id") && (!empty($item{$field_name}))) {
				$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version("gprint_preset") . get_hash_gprint($item{$field_name}) . "</$field_name>\n";
			}elseif (($field_name == "color_id") && (!empty($item{$field_name}))) {
				$xml_text .= "\t\t\t<$field_name>" . db_fetch_cell("select hex from colors where id=" . $item{$field_name}) . "</$field_name>\n";
			}else{
				$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item{$field_name}) . "</$field_name>\n";
			}
		}

		$xml_text .= "\t\t</hash_" . $hash["graph_template_item"] . ">\n";

		$i++;
	}
	}

	$xml_text .= "\t</items>\n";

	/* XML Branch: <inputs> */

	$xml_text .= "\t<inputs>\n";

	$i = 0;
	if (sizeof($graph_template_inputs) > 0) {
	foreach ($graph_template_inputs as $item) {
		$hash["graph_template_input"] = get_hash_version("graph_template_input") . get_hash_graph_template($item["id"], "graph_template_input");

		$xml_text .= "\t\t<hash_" . $hash["graph_template_input"] . ">\n";

		reset($fields_graph_template_input_edit);
		while (list($field_name, $field_array) = each($fields_graph_template_input_edit)) {
			if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
				$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item{$field_name}) . "</$field_name>\n";
			}
		}

		$graph_template_input_items = db_fetch_assoc("select graph_template_item_id from graph_template_input_defs where graph_template_input_id=" . $item["id"]);

		$xml_text .= "\t\t\t<items>";

		$j = 0;
		if (sizeof($graph_template_input_items) > 0) {
		foreach ($graph_template_input_items as $item2) {
			$xml_text .= "hash_" . get_hash_version("graph_template") . get_hash_graph_template($item2["graph_template_item_id"], "graph_template_item");

			if (($j+1) < sizeof($graph_template_input_items)) {
				$xml_text .= "|";
			}

			$j++;
		}
		}

		$xml_text .= "</items>\n";
		$xml_text .= "\t\t</hash_" . $hash["graph_template_input"] . ">\n";

		$i++;
	}
	}

	$xml_text .= "\t</inputs>\n";
	$xml_text .= "</hash_" . $hash["graph_template"] . ">";

	return $xml_text;
}

function data_template_to_xml($data_template_id) {
	global $struct_data_source, $struct_data_source_item, $export_errors;

	$hash["data_template"] = get_hash_version("data_template") . get_hash_data_template($data_template_id);
	$xml_text = "";

	$data_template = db_fetch_row("select id,name from data_template where id=$data_template_id");
	$data_template_data = db_fetch_row("select * from data_template_data where data_template_id=$data_template_id and local_data_id=0");
	$data_template_rrd = db_fetch_assoc("select * from data_template_rrd where data_template_id=$data_template_id and local_data_id=0");
	$data_template_data_rra = db_fetch_assoc("select * from data_template_data_rra where data_template_data_id=" . $data_template_data["id"]);
	$data_input_data = db_fetch_assoc("select * from data_input_data where data_template_data_id=" . $data_template_data["id"]);

	if ((empty($data_template["id"])) || (empty($data_template_data["id"]))) {
		$export_errors++;
		raise_message(27);
		cacti_log("ERROR: Invalid Data Template found in Database.  Please run database repair script to identify and/or correct.", false, "WEBUI");
		return;
	}

	$xml_text .= "<hash_" . $hash["data_template"] . ">\n\t<name>" . xml_character_encode($data_template["name"]) . "</name>\n\t<ds>\n";

	/* XML Branch: <ds> */
	reset($struct_data_source);
	while (list($field_name, $field_array) = each($struct_data_source)) {
		if (isset($data_template_data{"t_" . $field_name})) {
			$xml_text .= "\t\t<t_$field_name>" . xml_character_encode($data_template_data{"t_" . $field_name}) . "</t_$field_name>\n";
		}

		if (($field_name == "data_input_id") && (!empty($data_template_data{$field_name}))) {
			$xml_text .= "\t\t<$field_name>hash_" . get_hash_version("data_input_method") . get_hash_data_input($data_template_data{$field_name}) . "</$field_name>\n";
		}else{
			if (isset($data_template_data{$field_name})) {
				$xml_text .= "\t\t<$field_name>" . xml_character_encode($data_template_data{$field_name}) . "</$field_name>\n";
			}
		}
	}

	$xml_text .= "\t\t<rra_items>";

	$i = 0;
	if (sizeof($data_template_data_rra) > 0) {
	foreach ($data_template_data_rra as $item) {
		$xml_text .= "hash_" . get_hash_version("round_robin_archive") . get_hash_round_robin_archive($item["rra_id"]);

		if (($i+1) < sizeof($data_template_data_rra)) {
			$xml_text .= "|";
		}

		$i++;
	}
	}

	$xml_text .= "</rra_items>\n";
	$xml_text .= "\t</ds>\n";

	/* XML Branch: <items> */

	$xml_text .= "\t<items>\n";

	$i = 0;
	if (sizeof($data_template_rrd) > 0) {
	foreach ($data_template_rrd as $item) {
		$hash["data_template_item"] = get_hash_version("data_template_item") . get_hash_data_template($item["id"], "data_template_item");

		$xml_text .= "\t\t<hash_" . $hash["data_template_item"] . ">\n";

		reset($struct_data_source_item);
		while (list($field_name, $field_array) = each($struct_data_source_item)) {
			if (isset($item{"t_" . $field_name})) {
				$xml_text .= "\t\t\t<t_$field_name>" . xml_character_encode($item{"t_" . $field_name}) . "</t_$field_name>\n";
			}

			if (($field_name == "data_input_field_id") && (!empty($item{$field_name}))) {
				$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version("data_input_field") . get_hash_data_input($item{$field_name}, "data_input_field") . "</$field_name>\n";
			}else{
				if (isset($item{$field_name})) {
					$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item{$field_name}) . "</$field_name>\n";
				}
			}
		}

		$xml_text .= "\t\t</hash_" . $hash["data_template_item"] . ">\n";

		$i++;
	}
	}

	$xml_text .= "\t</items>\n";

	/* XML Branch: <data> */

	$xml_text .= "\t<data>\n";

	$i = 0;
	if (sizeof($data_input_data) > 0) {
	foreach ($data_input_data as $item) {
		$xml_text .= "\t\t<item_" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";

		$xml_text .= "\t\t\t<data_input_field_id>hash_" . get_hash_version("data_input_field") . get_hash_data_input($item{"data_input_field_id"}, "data_input_field") . "</data_input_field_id>\n";
		$xml_text .= "\t\t\t<t_value>" . xml_character_encode($item{"t_value"}) . "</t_value>\n";
		$xml_text .= "\t\t\t<value>" . xml_character_encode($item{"value"}) . "</value>\n";

		$xml_text .= "\t\t</item_" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";

		$i++;
	}
	}

	$xml_text .= "\t</data>\n";

	$xml_text .= "</hash_" . $hash["data_template"] . ">";

	return $xml_text;
}

function data_input_method_to_xml($data_input_id) {
	global $fields_data_input_edit, $fields_data_input_field_edit, $fields_data_input_field_edit_1, $export_errors;

	/* aggregate field arrays */
	$fields_data_input_field_edit += $fields_data_input_field_edit_1;

	$hash["data_input_method"] = get_hash_version("data_input_method") . get_hash_data_input($data_input_id);
	$xml_text = "";

	$data_input = db_fetch_row("select * from data_input where id=$data_input_id");
	$data_input_fields = db_fetch_assoc("select * from data_input_fields where data_input_id=$data_input_id");

	if (empty($data_input["id"])) {
		$export_errors++;
		raise_message(26);
		cacti_log("ERROR: Invalid Data Input Method found in Data Template.  Please run database repair script to identify and/or correct.", false, "WEBUI");
		return;
	}

	$xml_text .= "<hash_" . $hash["data_input_method"] . ">\n";

	/* XML Branch: <> */
	reset($fields_data_input_edit);
	while (list($field_name, $field_array) = each($fields_data_input_edit)) {
		if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
			$xml_text .= "\t<$field_name>" . xml_character_encode($data_input{$field_name}) . "</$field_name>\n";
		}
	}

	/* XML Branch: <fields> */

	$xml_text .= "\t<fields>\n";

	if (sizeof($data_input_fields) > 0) {
	foreach ($data_input_fields as $item) {
		$hash["data_input_field"] = get_hash_version("data_input_field") . get_hash_data_input($item["id"], "data_input_field");

		$xml_text .= "\t\t<hash_" . $hash["data_input_field"] . ">\n";

		reset($fields_data_input_field_edit);
		while (list($field_name, $field_array) = each($fields_data_input_field_edit)) {
			if (($field_name == "input_output") && (!empty($item{$field_name}))) {
				$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item{$field_name}) . "</$field_name>\n";
			}else{
				if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
					$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item{$field_name}) . "</$field_name>\n";
				}
			}
		}

		$xml_text .= "\t\t</hash_" . $hash["data_input_field"] . ">\n";
	}
	}

	$xml_text .= "\t</fields>\n";

	$xml_text .= "</hash_" . $hash["data_input_method"] . ">";

	return $xml_text;
}


/** encode a cdef along with all cdef_items as XML text
 * @param int $cdef_id	- the id of the cdef that has to be encoded
 * @return string		- the resulting XML text
 */
function cdef_to_xml($cdef_id) {
	global $fields_cdef_edit, $export_errors;

	$fields_cdef_item_edit = array(
		"sequence" => "sequence",
		"type" => "type",
		"value" => "value"
	);

	$hash["cdef"] = get_hash_version("cdef") . get_hash_cdef($cdef_id);
	$xml_text = "";

	$cdef = db_fetch_row("select * from cdef where id=$cdef_id");
	$cdef_items = db_fetch_assoc("select * from cdef_items where cdef_id=$cdef_id order by sequence");

	if (empty($cdef["id"])) {
		$export_errors++;
		raise_message(25);
		cacti_log("ERROR: Invalid CDEF found in Graph Template.  Please run database repair script to identify and/or correct.", false, "WEBUI");
		return;
	}

	$xml_text .= "<hash_" . $hash["cdef"] . ">\n";

	/* XML Branch: <> */
	reset($fields_cdef_edit);
	while (list($field_name, $field_array) = each($fields_cdef_edit)) {
		if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
			$xml_text .= "\t<$field_name>" . xml_character_encode($cdef{$field_name}) . "</$field_name>\n";
		}
	}

	/* XML Branch: <items> */

	$xml_text .= "\t<items>\n";

	$i = 0;
	if (sizeof($cdef_items) > 0) {
	foreach ($cdef_items as $item) {
		$hash["cdef_item"] = get_hash_version("cdef_item") . get_hash_cdef($item["id"], "cdef_item");

		$xml_text .= "\t\t<hash_" . $hash["cdef_item"] . ">\n";

		/* now do the encoding */
		reset($fields_cdef_item_edit);
		while (list($field_name, $field_array) = each($fields_cdef_item_edit)) {
			if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
				/* check, if an inherited cdef as to be encoded */
				if (($field_name == "value") && ($item["type"] == '5')) {
					$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version("cdef") . get_hash_cdef($item{$field_name}) . "</$field_name>\n";
				} else {
					$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item{$field_name}) . "</$field_name>\n";				
				}
			}
		}

		$xml_text .= "\t\t</hash_" . $hash["cdef_item"] . ">\n";

		$i++;
	}
	}

	$xml_text .= "\t</items>\n";
	$xml_text .= "</hash_" . $hash["cdef"] . ">";

	return $xml_text;
}

function gprint_preset_to_xml($gprint_preset_id) {
	global $fields_grprint_presets_edit, $export_errors;

	$hash = get_hash_version("gprint_preset") . get_hash_gprint($gprint_preset_id);
	$xml_text = "";

	$graph_templates_gprint = db_fetch_row("select * from graph_templates_gprint where id=$gprint_preset_id");

	if (empty($graph_templates_gprint["id"])) {
		$export_errors++;
		raise_message(24);
		cacti_log("ERROR: Invalid GPRINT preset found in Graph Template.  Please run database repair script to identify and/or correct.", false, "WEBUI");
		return;
	}

	$xml_text .= "<hash_$hash>\n";

	/* XML Branch: <> */
	reset($fields_grprint_presets_edit);
	while (list($field_name, $field_array) = each($fields_grprint_presets_edit)) {
		if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
			$xml_text .= "\t<$field_name>" . xml_character_encode($graph_templates_gprint{$field_name}) . "</$field_name>\n";
		}
	}

	$xml_text .= "</hash_$hash>";

	return $xml_text;
}

function round_robin_archive_to_xml($round_robin_archive_id) {
	global $fields_rra_edit, $export_errors;

	$hash = get_hash_version("round_robin_archive") . get_hash_round_robin_archive($round_robin_archive_id);
	$xml_text = "";

	$rra = db_fetch_row("select * from rra where id=$round_robin_archive_id");
	$rra_cf = db_fetch_assoc("select * from rra_cf where rra_id=$round_robin_archive_id");

	if (empty($rra["id"])) {
		$export_errors++;
		raise_message(23);
		cacti_log("ERROR: Invalid Round Robin Archive found during Data Template export.  Please run database repair script to identify and/or correct.", false, "WEBUI");
		return;
	}

	$xml_text .= "<hash_$hash>\n";

	/* XML Branch: <> */
	reset($fields_rra_edit);
	while (list($field_name, $field_array) = each($fields_rra_edit)) {
		if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
			if (isset($rra{$field_name})) {
				$xml_text .= "\t<$field_name>" . xml_character_encode($rra{$field_name}) . "</$field_name>\n";
			}
		}
	}

	$xml_text .= "\t<cf_items>";

	/* XML Branch: <cf_items> */
	$i = 0;
	if (sizeof($rra_cf) > 0) {
	foreach ($rra_cf as $item) {
		$xml_text .= $item["consolidation_function_id"];

		if (($i+1) < sizeof($rra_cf)) {
			$xml_text .= "|";
		}

		$i++;
	}
	}

	$xml_text .= "</cf_items>\n";

	$xml_text .= "</hash_$hash>";

	return $xml_text;
}

function host_template_to_xml($host_template_id) {
	global $fields_host_template_edit, $export_errors;

	$hash = get_hash_version("host_template") . get_hash_host_template($host_template_id);
	$xml_text = "";

	$host_template = db_fetch_row("select * from host_template where id=$host_template_id");
	$host_template_graph = db_fetch_assoc("select * from host_template_graph where host_template_id=$host_template_id");
	$host_template_snmp_query = db_fetch_assoc("select * from host_template_snmp_query where host_template_id=$host_template_id");

	if (empty($host_template["id"])) {
		$export_errors++;
		raise_message(28);
		cacti_log("ERROR: Invalid Host Template found during Export.  Please run database repair script to identify and/or correct.", false, "WEBUI");
		return;
	}

	$xml_text .= "<hash_$hash>\n";

	/* XML Branch: <> */
	reset($fields_host_template_edit);
	while (list($field_name, $field_array) = each($fields_host_template_edit)) {
		if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
			$xml_text .= "\t<$field_name>" . xml_character_encode($host_template{$field_name}) . "</$field_name>\n";
		}
	}

	/* XML Branch: <graph_templates> */
	$xml_text .= "\t<graph_templates>";

	$j = 0;
	if (sizeof($host_template_graph) > 0) {
	foreach ($host_template_graph as $item) {
		$xml_text .= "hash_" . get_hash_version("graph_template") . get_hash_graph_template($item["graph_template_id"]);

		if (($j+1) < sizeof($host_template_graph)) {
			$xml_text .= "|";
		}

		$j++;
	}
	}

	$xml_text .= "</graph_templates>\n";

	/* XML Branch: <data_queries> */
	$xml_text .= "\t<data_queries>";

	$j = 0;
	if (sizeof($host_template_snmp_query) > 0) {
	foreach ($host_template_snmp_query as $item) {
		$xml_text .= "hash_" . get_hash_version("data_query") . get_hash_data_query($item["snmp_query_id"]);

		if (($j+1) < sizeof($host_template_snmp_query)) {
			$xml_text .= "|";
		}

		$j++;
	}
	}

	$xml_text .= "</data_queries>\n";

	$xml_text .= "</hash_$hash>";

	return $xml_text;
}

function data_query_to_xml($data_query_id) {
	global $fields_data_query_edit, $fields_data_query_item_edit, $export_errors;

	$hash["data_query"] = get_hash_version("data_query") . get_hash_data_query($data_query_id);
	$xml_text = "";

	$snmp_query = db_fetch_row("select * from snmp_query where id=$data_query_id");
	$snmp_query_graph = db_fetch_assoc("select * from snmp_query_graph where snmp_query_id=$data_query_id");

	if (empty($snmp_query["id"])) {
		$export_errors++;
		raise_message(28);
		cacti_log("ERROR: Invalid Data Query found during Export.  Please run database repair script to identify and/or correct.", false, "WEBUI");
		return;
	}

	$xml_text .= "<hash_" . $hash["data_query"] . ">\n";

	/* XML Branch: <> */
	reset($fields_data_query_edit);
	while (list($field_name, $field_array) = each($fields_data_query_edit)) {
		if (($field_name == "data_input_id") && (!empty($snmp_query{$field_name}))) {
			$xml_text .= "\t<$field_name>hash_" . get_hash_version("data_input_method") . get_hash_data_input($snmp_query{$field_name}) . "</$field_name>\n";
		}else{
			if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
				$xml_text .= "\t<$field_name>" . xml_character_encode($snmp_query{$field_name}) . "</$field_name>\n";
			}
		}
	}

	/* XML Branch: <graphs> */

	$xml_text .= "\t<graphs>\n";

	$i = 0;
	if (sizeof($snmp_query_graph) > 0) {
	foreach ($snmp_query_graph as $item) {
		$hash["data_query_graph"] = get_hash_version("data_query_graph") . get_hash_data_query($item["id"], "data_query_graph");

		$xml_text .= "\t\t<hash_" . $hash["data_query_graph"] . ">\n";

		reset($fields_data_query_item_edit);
		while (list($field_name, $field_array) = each($fields_data_query_item_edit)) {
			if (($field_name == "graph_template_id") && (!empty($item{$field_name}))) {
				$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version("graph_template") . get_hash_graph_template($item{$field_name}) . "</$field_name>\n";
			}else{
				if (($field_array["method"] != "hidden_zero") && ($field_array["method"] != "hidden")) {
					$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item{$field_name}) . "</$field_name>\n";
				}
			}
		}

		$snmp_query_graph_rrd_sv = db_fetch_assoc("select * from snmp_query_graph_rrd_sv where snmp_query_graph_id=" . $item["id"] . " order by sequence");
		$snmp_query_graph_sv = db_fetch_assoc("select * from snmp_query_graph_sv where snmp_query_graph_id=" . $item["id"] . " order by sequence");
		$snmp_query_graph_rrd = db_fetch_assoc("select * from snmp_query_graph_rrd where snmp_query_graph_id=" . $item["id"] . " and data_template_id > 0");

		/* XML Branch: <graphs/rrd> */

		$xml_text .= "\t\t\t<rrd>\n";

		$i = 0;
		if (sizeof($snmp_query_graph_rrd) > 0) {
		foreach ($snmp_query_graph_rrd as $item2) {
			$xml_text .= "\t\t\t\t<item_" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";

			$xml_text .= "\t\t\t\t\t<snmp_field_name>" . $item2{"snmp_field_name"} . "</snmp_field_name>\n";
			$xml_text .= "\t\t\t\t\t<data_template_id>hash_" . get_hash_version("data_template") . get_hash_data_template($item2{"data_template_id"}) . "</data_template_id>\n";
			$xml_text .= "\t\t\t\t\t<data_template_rrd_id>hash_" . get_hash_version("data_template_item") . get_hash_data_template($item2{"data_template_rrd_id"}, "data_template_item") . "</data_template_rrd_id>\n";

			$xml_text .= "\t\t\t\t</item_" . str_pad(strval($i), 3, "0", STR_PAD_LEFT) . ">\n";

			$i++;
		}
		}

		$xml_text .= "\t\t\t</rrd>\n";

		/* XML Branch: <graphs/sv_graph> */

		$xml_text .= "\t\t\t<sv_graph>\n";

		$j = 0;
		if (sizeof($snmp_query_graph_sv) > 0) {
		foreach ($snmp_query_graph_sv as $item2) {
			$hash["data_query_sv_graph"] = get_hash_version("data_query_sv_graph") . get_hash_data_query($item2["id"], "data_query_sv_graph");

			$xml_text .= "\t\t\t\t<hash_" . $hash["data_query_sv_graph"] . ">\n";

			$xml_text .= "\t\t\t\t\t<field_name>" . xml_character_encode($item2{"field_name"}) . "</field_name>\n";
			$xml_text .= "\t\t\t\t\t<sequence>" . $item2{"sequence"} . "</sequence>\n";
			$xml_text .= "\t\t\t\t\t<text>" . xml_character_encode($item2{"text"}) . "</text>\n";

			$xml_text .= "\t\t\t\t</hash_" . $hash["data_query_sv_graph"] . ">\n";

			$j++;
		}
		}

		$xml_text .= "\t\t\t</sv_graph>\n";

		/* XML Branch: <graphs/sv_data_source> */

		$xml_text .= "\t\t\t<sv_data_source>\n";

		$j = 0;
		if (sizeof($snmp_query_graph_rrd_sv) > 0) {
		foreach ($snmp_query_graph_rrd_sv as $item2) {
			$hash["data_query_sv_data_source"] = get_hash_version("data_query_sv_data_source") . get_hash_data_query($item2["id"], "data_query_sv_data_source");

			$xml_text .= "\t\t\t\t<hash_" . $hash["data_query_sv_data_source"] . ">\n";

			$xml_text .= "\t\t\t\t\t<field_name>" . xml_character_encode($item2{"field_name"}) . "</field_name>\n";
			$xml_text .= "\t\t\t\t\t<data_template_id>hash_" . get_hash_version("data_template") . get_hash_data_template($item2{"data_template_id"}) . "</data_template_id>\n";
			$xml_text .= "\t\t\t\t\t<sequence>" . $item2{"sequence"} . "</sequence>\n";
			$xml_text .= "\t\t\t\t\t<text>" . xml_character_encode($item2{"text"}) . "</text>\n";

			$xml_text .= "\t\t\t\t</hash_" . $hash["data_query_sv_data_source"] . ">\n";

			$j++;
		}
		}

		$xml_text .= "\t\t\t</sv_data_source>\n";

		$xml_text .= "\t\t</hash_" . $hash["data_query_graph"] . ">\n";

		$i++;
	}
	}

	$xml_text .= "\t</graphs>\n";

	$xml_text .= "</hash_" . $hash["data_query"] . ">";

	return $xml_text;
}

function resolve_dependencies($type, $id, $dep_array) {
	/* make sure we define our variables */
	if (!isset($dep_array[$type])) {
		$dep_array[$type] = array();
	}

	switch ($type) {
	case 'graph_template':
		/* dep: data template */
		$graph_template_items = db_fetch_assoc("select
			data_template_rrd.data_template_id
			from (graph_templates_item,data_template_rrd)
			where graph_templates_item.task_item_id=data_template_rrd.id
			and graph_templates_item.graph_template_id=$id
			and graph_templates_item.local_graph_id=0
			and graph_templates_item.task_item_id > 0
			group by data_template_rrd.data_template_id");

		if (sizeof($graph_template_items) > 0) {
		foreach ($graph_template_items as $item) {
			if (!isset($dep_array["data_template"]{$item["data_template_id"]})) {
				$dep_array = resolve_dependencies("data_template", $item["data_template_id"], $dep_array);
			}
		}
		}

		/* dep: cdef */
		$cdef_items = db_fetch_assoc("select cdef_id from graph_templates_item where graph_template_id=$id and local_graph_id=0 and cdef_id > 0 group by cdef_id");
		
		$recursive = true;
		/* in the first turn, search all inherited cdef items related to all cdef's known on highest recursion level */
		$search_cdef_items = $cdef_items;
		if (sizeof($cdef_items) > 0) {
			while ($recursive) {
				/* are there any inherited cdef's within those referenced by any graph item? 
				 * search for all cdef_items of type = 5 (inherited cdef) 
				 * but fetch only those related to already given cdef's */
				$sql = "SELECT value as cdef_id " .
					"FROM cdef_items " .
					"WHERE type = 5 " .
					"AND " . array_to_sql_or($search_cdef_items, "cdef_id");
				$inherited_cdef_items = db_fetch_assoc($sql);
				
				/* in case we found any */
				if (sizeof($inherited_cdef_items) > 0) {
					/* join all cdef's found 
					 * ATTENTION!
					 * sequence of parameters matters! 
					 * we must place the newly found inherited items first
					 * reason is, that during import, the leafes have to be tackled first,
					 * that is, the inherited items must be placed first so that they are "resolved" (decoded)
					 * first during re-import */
					$cdef_items = array_merge_recursive($inherited_cdef_items, $cdef_items);
					/* for the next turn, search only new cdef's */
					$search_cdef_items = $inherited_cdef_items;
				} else {
					/* else stop recursion */
					$recursive = false;
				}
			}

			foreach ($cdef_items as $item) {
				if (!isset($dep_array["cdef"]{$item["cdef_id"]})) {
					$dep_array = resolve_dependencies("cdef", $item["cdef_id"], $dep_array);
				}
			}
		}

		/* dep: gprint preset */
		$graph_template_items = db_fetch_assoc("select gprint_id from graph_templates_item where graph_template_id=$id and local_graph_id=0 and gprint_id > 0 group by gprint_id");

		if (sizeof($graph_template_items) > 0) {
		foreach ($graph_template_items as $item) {
			if (!isset($dep_array["gprint_preset"]{$item["gprint_id"]})) {
				$dep_array = resolve_dependencies("gprint_preset", $item["gprint_id"], $dep_array);
			}
		}
		}

		break;
	case 'data_template':
		/* dep: data input method */
		$item = db_fetch_row("select data_input_id from data_template_data where data_template_id=$id and local_data_id=0 and data_input_id > 0");

		if ((!empty($item)) && (!isset($dep_array["data_input_method"]{$item["data_input_id"]}))) {
			$dep_array = resolve_dependencies("data_input_method", $item["data_input_id"], $dep_array);
		}

		/* dep: round robin archive */
		$rras = db_fetch_assoc("select rra_id from data_template_data_rra where data_template_data_id=" . db_fetch_cell("select id from data_template_data where data_template_id=$id and local_data_id = 0"));

		if (sizeof($rras) > 0) {
		foreach ($rras as $item) {
			if (!isset($dep_array["round_robin_archive"]{$item["rra_id"]})) {
				$dep_array = resolve_dependencies("round_robin_archive", $item["rra_id"], $dep_array);
			}
		}
		}

		break;
	case 'data_query':
		/* dep: data input method */
		$item = db_fetch_row("select data_input_id from snmp_query where id=$id and data_input_id > 0");

		if ((!empty($item)) && (!isset($dep_array["data_input_method"]{$item["data_input_id"]}))) {
			$dep_array = resolve_dependencies("data_input_method", $item["data_input_id"], $dep_array);
		}

		/* dep: graph template */
		$snmp_query_graph = db_fetch_assoc("select graph_template_id from snmp_query_graph where snmp_query_id=$id and graph_template_id > 0 group by graph_template_id");

		if (sizeof($snmp_query_graph) > 0) {
		foreach ($snmp_query_graph as $item) {
			if (!isset($dep_array["graph_template"]{$item["graph_template_id"]})) {
				$dep_array = resolve_dependencies("graph_template", $item["graph_template_id"], $dep_array);
			}
		}
		}

		break;
	case 'host_template':
		/* dep: graph template */
		$host_template_graph = db_fetch_assoc("select graph_template_id from host_template_graph where host_template_id=$id and graph_template_id > 0 group by graph_template_id");

		if (sizeof($host_template_graph) > 0) {
		foreach ($host_template_graph as $item) {
			if (!isset($dep_array["graph_template"]{$item["graph_template_id"]})) {
				$dep_array = resolve_dependencies("graph_template", $item["graph_template_id"], $dep_array);
			}
		}
		}

		/* dep: data query */
		$host_template_snmp_query = db_fetch_assoc("select snmp_query_id from host_template_snmp_query where host_template_id=$id and snmp_query_id > 0 group by snmp_query_id");

		if (sizeof($host_template_snmp_query) > 0) {
		foreach ($host_template_snmp_query as $item) {
			if (!isset($dep_array["data_query"]{$item["snmp_query_id"]})) {
				$dep_array = resolve_dependencies("data_query", $item["snmp_query_id"], $dep_array);
			}
		}
		}

		break;
	}

	/* update the dependency array */
	$dep_array[$type][$id] = $id;

	return $dep_array;
}

function get_item_xml($type, $id, $follow_deps) {
	$xml_text = "";
	$xml_indent = "";

	if ($follow_deps == true) {
		/* follow all dependencies recursively */
		$dep_array = resolve_dependencies($type, $id, array());
	}else{
		/* we are not supposed to resolve dependencies */
		$dep_array[$type][$id] = $id;
	}

	if (sizeof($dep_array) > 0) {
		while (list($dep_type, $dep_arr) = each($dep_array)) {
			while (list($dep_id, $dep_id) = each($dep_arr)) {
				switch($dep_type) {
				case 'graph_template':
					$xml_text .= "\n" . graph_template_to_xml($dep_id);
					break;
				case 'data_template':
					$xml_text .= "\n" . data_template_to_xml($dep_id);
					break;
				case 'host_template':
					$xml_text .= "\n" . host_template_to_xml($dep_id);
					break;
				case 'data_input_method':
					$xml_text .= "\n" . data_input_method_to_xml($dep_id);
					break;
				case 'data_query':
					$xml_text .= "\n" . data_query_to_xml($dep_id);
					break;
				case 'gprint_preset':
					$xml_text .= "\n" . gprint_preset_to_xml($dep_id);
					break;
				case 'cdef':
					$xml_text .= "\n" . cdef_to_xml($dep_id);
					break;
				case 'round_robin_archive':
					$xml_text .= "\n" . round_robin_archive_to_xml($dep_id);
					break;
				}
			}
		}
	}

	$xml_array = explode("\n", $xml_text);

	for ($i=0; $i<count($xml_array); $i++) {
		$xml_indent .= "\t" . $xml_array[$i] . "\n";
	}

	$xml_text = "<cacti>" . $xml_indent . "</cacti>";

	return $xml_text;
}

function xml_character_encode($text) {
	if (function_exists("htmlspecialchars")) {
		return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
	} else {
		$text = str_replace("&", "&amp;", $text);
		$text = str_replace(">", "&gt;", $text);
		$text = str_replace("<", "&lt;", $text);
		$text = str_replace("\"", "&quot;", $text);
		$text = str_replace("\'", "&apos;", $text);

		return $text;
	}
}

?>
