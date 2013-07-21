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

function &import_xml_data(&$xml_data, $import_custom_rra_settings, $rra_array = array()) {
	global $config, $hash_type_codes, $hash_version_codes;

	include_once($config["library_path"] . "/xml.php");

	$info_array = array();

	$xml_array = xml2array($xml_data);

	if (sizeof($xml_array) == 0) {
		raise_message(7); /* xml parse error */
		return $info_array;
	}

	while (list($hash, $hash_array) = each($xml_array)) {
		/* parse information from the hash */
		$parsed_hash = parse_xml_hash($hash);

		/* invalid/wrong hash */
		if ($parsed_hash == false) { return $info_array; }

		if (isset($dep_hash_cache{$parsed_hash["type"]})) {
			array_push($dep_hash_cache{$parsed_hash["type"]}, $parsed_hash);
		}else{
			$dep_hash_cache{$parsed_hash["type"]} = array($parsed_hash);
		}
	}

	$hash_cache = array();

	/* the order of the $hash_type_codes array is ordered such that the items
	with the most dependencies are last and the items with no dependencies are first.
	this means dependencies will just magically work themselves out :) */
	reset($hash_type_codes);
	while (list($type, $code) = each($hash_type_codes)) {
		/* do we have any matches for this type? */
		if (isset($dep_hash_cache[$type])) {
			/* yes we do. loop through each match for this type */
			for ($i=0; $i<count($dep_hash_cache[$type]); $i++) {
				$hash_array = $xml_array{"hash_" . $hash_type_codes{$dep_hash_cache[$type][$i]["type"]} . $hash_version_codes{$dep_hash_cache[$type][$i]["version"]} . $dep_hash_cache[$type][$i]["hash"]};

				switch($type) {
				case 'graph_template':
					$hash_cache += xml_to_graph_template($dep_hash_cache[$type][$i]["hash"], $hash_array, $hash_cache, $dep_hash_cache[$type][$i]["version"]);
					break;
				case 'data_template':
					$hash_cache += xml_to_data_template($dep_hash_cache[$type][$i]["hash"], $hash_array, $hash_cache, $import_custom_rra_settings, $rra_array);
					break;
				case 'host_template':
					$hash_cache += xml_to_host_template($dep_hash_cache[$type][$i]["hash"], $hash_array, $hash_cache);
					break;
				case 'data_input_method':
					$hash_cache += xml_to_data_input_method($dep_hash_cache[$type][$i]["hash"], $hash_array, $hash_cache);
					break;
				case 'data_query':
					$hash_cache += xml_to_data_query($dep_hash_cache[$type][$i]["hash"], $hash_array, $hash_cache);
					break;
				case 'gprint_preset':
					$hash_cache += xml_to_gprint_preset($dep_hash_cache[$type][$i]["hash"], $hash_array, $hash_cache);
					break;
				case 'cdef':
					$hash_cache += xml_to_cdef($dep_hash_cache[$type][$i]["hash"], $hash_array, $hash_cache);
					break;
				case 'round_robin_archive':
					if ($import_custom_rra_settings === true) {
						$hash_cache += xml_to_round_robin_archive($dep_hash_cache[$type][$i]["hash"], $hash_array, $hash_cache);
					}
					break;
				}

				if (isset($_SESSION["import_debug_info"])) {
					$info_array[$type]{isset($info_array[$type]) ? count($info_array[$type]) : 0} = $_SESSION["import_debug_info"];
				}

				kill_session_var("import_debug_info");
			}
		}
	}

	return $info_array;
}

function &xml_to_graph_template($hash, &$xml_array, &$hash_cache, $hash_version) {
	global $struct_graph, $struct_graph_item, $fields_graph_template_input_edit, $hash_version_codes;

	/* import into: graph_templates */
	$_graph_template_id = db_fetch_cell("select id from graph_templates where hash='$hash'");
	$save["id"] = (empty($_graph_template_id) ? "0" : $_graph_template_id);
	$save["hash"] = $hash;
	$save["name"] = $xml_array["name"];
	$graph_template_id = sql_save($save, "graph_templates");

	$hash_cache["graph_template"][$hash] = $graph_template_id;

	/* import into: graph_templates_graph */
	unset($save);
	$save["id"] = (empty($_graph_template_id) ? "0" : db_fetch_cell("select graph_templates_graph.id from (graph_templates,graph_templates_graph) where graph_templates.id=graph_templates_graph.graph_template_id and graph_templates.id=$graph_template_id and graph_templates_graph.local_graph_id=0"));
	$save["graph_template_id"] = $graph_template_id;

	reset($struct_graph);
	while (list($field_name, $field_array) = each($struct_graph)) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array["graph"]{"t_" . $field_name})) {
			$save{"t_" . $field_name} = $xml_array["graph"]{"t_" . $field_name};
		}

		/* make sure this field exists in the xml array first */
		if (isset($xml_array["graph"][$field_name])) {
			/* Cacti pre 0.8.5 did handle a unit_exponent=0 differently
			 * so we need to know the version of the current hash code we're just working on */
			if (($field_name == "unit_exponent_value") && (get_version_index($hash_version) < get_version_index("0.8.5")) && ($xml_array["graph"][$field_name] == "0")) { /* backwards compatability */
				$save[$field_name] = "";
			}else{
				$save[$field_name] = addslashes(xml_character_decode($xml_array["graph"][$field_name]));
			}
		}
	}

	$graph_template_graph_id = sql_save($save, "graph_templates_graph");

	/* import into: graph_templates_item */
	if (is_array($xml_array["items"])) {
		while (list($item_hash, $item_array) = each($xml_array["items"])) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_graph_template_item_id = db_fetch_cell("select id from graph_templates_item where hash='" . $parsed_hash["hash"] . "' and graph_template_id=$graph_template_id and local_graph_id=0");
			$save["id"] = (empty($_graph_template_item_id) ? "0" : $_graph_template_item_id);
			$save["hash"] = $parsed_hash["hash"];
			$save["graph_template_id"] = $graph_template_id;

			reset($struct_graph_item);
			while (list($field_name, $field_array) = each($struct_graph_item)) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					/* is the value of this field a hash or not? */
					if (preg_match("/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/", $item_array[$field_name])) {
						$save[$field_name] = resolve_hash_to_id($item_array[$field_name], $hash_cache);
					}elseif (($field_name == "color_id") && (preg_match("/^[a-fA-F0-9]{6}$/", $item_array[$field_name])) && (get_version_index($parsed_hash["version"]) >= get_version_index("0.8.5"))) { /* treat the 'color' field differently */
						$color_id = db_fetch_cell("select id from colors where hex='" . $item_array[$field_name] . "'");

						if (empty($color_id)) {
							db_execute("insert into colors (hex) values ('" . $item_array[$field_name] . "')");
							$color_id = db_fetch_insert_id();
						}

						$save[$field_name] = $color_id;
					}else{
						$save[$field_name] = addslashes(xml_character_decode($item_array[$field_name]));
					}
				}
			}

			$graph_template_item_id = sql_save($save, "graph_templates_item");

			$hash_cache["graph_template_item"]{$parsed_hash["hash"]} = $graph_template_item_id;
		}
	}

	/* import into: graph_template_input */
	if (is_array($xml_array["inputs"])) {
		while (list($item_hash, $item_array) = each($xml_array["inputs"])) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_graph_template_input_id = db_fetch_cell("select id from graph_template_input where hash='" . $parsed_hash["hash"] . "' and graph_template_id=$graph_template_id");
			$save["id"] = (empty($_graph_template_input_id) ? "0" : $_graph_template_input_id);
			$save["hash"] = $parsed_hash["hash"];
			$save["graph_template_id"] = $graph_template_id;

			reset($fields_graph_template_input_edit);
			while (list($field_name, $field_array) = each($fields_graph_template_input_edit)) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					$save[$field_name] = addslashes(xml_character_decode($item_array[$field_name]));
				}
			}

			$graph_template_input_id = sql_save($save, "graph_template_input");

			$hash_cache["graph_template_input"]{$parsed_hash["hash"]} = $graph_template_input_id;

			/* import into: graph_template_input_defs */
			$hash_items = explode("|", $item_array["items"]);

			if (!empty($hash_items[0])) {
				for ($i=0; $i<count($hash_items); $i++) {
					/* parse information from the hash */
					$parsed_hash = parse_xml_hash($hash_items[$i]);

					/* invalid/wrong hash */
					if ($parsed_hash == false) { return false; }

					if (isset($hash_cache["graph_template_item"]{$parsed_hash["hash"]})) {
						db_execute("replace into graph_template_input_defs (graph_template_input_id,graph_template_item_id) values ($graph_template_input_id," . $hash_cache["graph_template_item"]{$parsed_hash["hash"]} . ")");
					}
				}
			}
		}
	}

	/* status information that will be presented to the user */
	$_SESSION["import_debug_info"]["type"] = (empty($_graph_template_id) ? "new" : "update");
	$_SESSION["import_debug_info"]["title"] = $xml_array["name"];
	$_SESSION["import_debug_info"]["result"] = (empty($graph_template_id) ? "fail" : "success");

	return $hash_cache;
}

function &xml_to_data_template($hash, &$xml_array, &$hash_cache, $import_custom_rra_settings, $rra_array) {
	global $struct_data_source, $struct_data_source_item;

	/* import into: data_template */
	$_data_template_id = db_fetch_cell("select id from data_template where hash='$hash'");
	$save["id"] = (empty($_data_template_id) ? "0" : $_data_template_id);
	$save["hash"] = $hash;
	$save["name"] = $xml_array["name"];

	$data_template_id = sql_save($save, "data_template");

	$hash_cache["data_template"][$hash] = $data_template_id;

	/* import into: data_template_data */
	unset($save);
	$save["id"] = (empty($_data_template_id) ? "0" : db_fetch_cell("select data_template_data.id from (data_template,data_template_data) where data_template.id=data_template_data.data_template_id and data_template.id=$data_template_id and data_template_data.local_data_id=0"));
	$save["data_template_id"] = $data_template_id;

	reset($struct_data_source);
	while (list($field_name, $field_array) = each($struct_data_source)) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array["ds"]{"t_" . $field_name})) {
			$save{"t_" . $field_name} = $xml_array["ds"]{"t_" . $field_name};
		}

		/* make sure this field exists in the xml array first */
		if (isset($xml_array["ds"][$field_name])) {
			/* is the value of this field a hash or not? */
			if (preg_match("/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/", $xml_array["ds"][$field_name])) {
				$save[$field_name] = resolve_hash_to_id($xml_array["ds"][$field_name], $hash_cache);
			}else{
				$save[$field_name] = addslashes(xml_character_decode($xml_array["ds"][$field_name]));
			}
		}
	}

	/* use the polling interval as the step if we are to use the default rra settings */
	if ($import_custom_rra_settings === false) {
		$save["rrd_step"] = read_config_option("poller_interval");
	}

	$data_template_data_id = sql_save($save, "data_template_data");

	/* use custom rra settings from the xml */
	if ($import_custom_rra_settings === true) {
		/* import into: data_template_data_rra */
		$hash_items = explode("|", $xml_array["ds"]["rra_items"]);

		if (!empty($hash_items[0])) {
			for ($i=0; $i<count($hash_items); $i++) {
				/* parse information from the hash */
				$parsed_hash = parse_xml_hash($hash_items[$i]);

				/* invalid/wrong hash */
				if ($parsed_hash == false) { return false; }

				if (isset($hash_cache["round_robin_archive"]{$parsed_hash["hash"]})) {
					db_execute("replace into data_template_data_rra (data_template_data_id,rra_id) values ($data_template_data_id," . $hash_cache["round_robin_archive"]{$parsed_hash["hash"]} . ")");
				}
			}
		}
	}else{ /* use all rras selected by the user */
		if (is_array($rra_array)) {
			/* when overriding an existing data template, make sure that specifying fewer (or different) rra's is honoured */
			db_execute("DELETE FROM data_template_data_rra  WHERE data_template_data_id = $data_template_data_id");
			foreach ($rra_array as $rra) {
				/* as it was user supplied input, make sure it's an integer */
				db_execute("replace into data_template_data_rra (data_template_data_id,rra_id) values ($data_template_data_id," . intval($rra) . ")");
			}
		}
	}

	/* import into: data_template_rrd */
	if (is_array($xml_array["items"])) {
		while (list($item_hash, $item_array) = each($xml_array["items"])) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_data_template_rrd_id = db_fetch_cell("select id from data_template_rrd where hash='" . $parsed_hash["hash"] . "' and data_template_id=$data_template_id and local_data_id=0");
			$save["id"] = (empty($_data_template_rrd_id) ? "0" : $_data_template_rrd_id);
			$save["hash"] = $parsed_hash["hash"];
			$save["data_template_id"] = $data_template_id;

			reset($struct_data_source_item);
			while (list($field_name, $field_array) = each($struct_data_source_item)) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array{"t_" . $field_name})) {
					$save{"t_" . $field_name} = $item_array{"t_" . $field_name};
				}

				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					/* is the value of this field a hash or not? */
					if (preg_match("/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/", $item_array[$field_name])) {
						$save[$field_name] = resolve_hash_to_id($item_array[$field_name], $hash_cache);
					}else{
						$save[$field_name] = addslashes(xml_character_decode($item_array[$field_name]));
					}
				}
			}

			/* use the polling interval * 2 as the heartbeat if we are to use the default rra settings */
			if ($import_custom_rra_settings === false) {
				$save["rrd_heartbeat"] = read_config_option("poller_interval") * 2;
			}

			$data_template_rrd_id = sql_save($save, "data_template_rrd");

			$hash_cache["data_template_item"]{$parsed_hash["hash"]} = $data_template_rrd_id;
		}
	}

	/* import into: data_input_data */
	if (is_array($xml_array["data"])) {
		while (list($item_hash, $item_array) = each($xml_array["data"])) {
			unset($save);
			$save["data_template_data_id"] = $data_template_data_id;
			$save["data_input_field_id"] = resolve_hash_to_id($item_array["data_input_field_id"], $hash_cache);
			$save["t_value"] = $item_array["t_value"];
			$save["value"] = addslashes(xml_character_decode($item_array["value"]));

			sql_save($save, "data_input_data", array("data_template_data_id", "data_input_field_id"), false);
		}
	}

	/* status information that will be presented to the user */
	$_SESSION["import_debug_info"]["type"] = (empty($_data_template_id) ? "new" : "update");
	$_SESSION["import_debug_info"]["title"] = $xml_array["name"];
	$_SESSION["import_debug_info"]["result"] = (empty($data_template_id) ? "fail" : "success");

	return $hash_cache;
}

function &xml_to_data_query($hash, &$xml_array, &$hash_cache) {
	global $fields_data_query_edit, $fields_data_query_item_edit;

	/* import into: snmp_query */
	$_data_query_id = db_fetch_cell("select id from snmp_query where hash='$hash'");
	$save["id"] = (empty($_data_query_id) ? "0" : $_data_query_id);
	$save["hash"] = $hash;

	reset($fields_data_query_edit);
	while (list($field_name, $field_array) = each($fields_data_query_edit)) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			/* is the value of this field a hash or not? */
			if (preg_match("/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/", $xml_array[$field_name])) {
				$save[$field_name] = resolve_hash_to_id($xml_array[$field_name], $hash_cache);
			}else{
				$save[$field_name] = addslashes(xml_character_decode($xml_array[$field_name]));
			}
		}
	}

	$data_query_id = sql_save($save, "snmp_query");

	$hash_cache["data_query"][$hash] = $data_query_id;

	/* import into: snmp_query_graph */
	if (is_array($xml_array["graphs"])) {
		while (list($item_hash, $item_array) = each($xml_array["graphs"])) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_data_query_graph_id = db_fetch_cell("select id from snmp_query_graph where hash='" . $parsed_hash["hash"] . "' and snmp_query_id=$data_query_id");
			$save["id"] = (empty($_data_query_graph_id) ? "0" : $_data_query_graph_id);
			$save["hash"] = $parsed_hash["hash"];
			$save["snmp_query_id"] = $data_query_id;

			reset($fields_data_query_item_edit);
			while (list($field_name, $field_array) = each($fields_data_query_item_edit)) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					/* is the value of this field a hash or not? */
					if (preg_match("/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/", $item_array[$field_name])) {
						$save[$field_name] = resolve_hash_to_id($item_array[$field_name], $hash_cache);
					}else{
						$save[$field_name] = addslashes(xml_character_decode($item_array[$field_name]));
					}
				}
			}

			$data_query_graph_id = sql_save($save, "snmp_query_graph");

			$hash_cache["data_query_graph"]{$parsed_hash["hash"]} = $data_query_graph_id;

			/* import into: snmp_query_graph_rrd */
			if (is_array($item_array["rrd"])) {
				while (list($sub_item_hash, $sub_item_array) = each($item_array["rrd"])) {
					unset($save);
					$save["snmp_query_graph_id"] = $data_query_graph_id;
					$save["data_template_id"] = resolve_hash_to_id($sub_item_array["data_template_id"], $hash_cache);
					$save["data_template_rrd_id"] = resolve_hash_to_id($sub_item_array["data_template_rrd_id"], $hash_cache);
					$save["snmp_field_name"] = $sub_item_array["snmp_field_name"];

					sql_save($save, "snmp_query_graph_rrd", array("snmp_query_graph_id", "data_template_id", "data_template_rrd_id"), false);
				}
			}

			/* import into: snmp_query_graph_sv */
			if (is_array($item_array["sv_graph"])) {
				while (list($sub_item_hash, $sub_item_array) = each($item_array["sv_graph"])) {
					/* parse information from the hash */
					$parsed_hash = parse_xml_hash($sub_item_hash);

					/* invalid/wrong hash */
					if ($parsed_hash == false) { return false; }

					unset($save);
					$_data_query_graph_sv_id = db_fetch_cell("select id from snmp_query_graph_sv where hash='" . $parsed_hash["hash"] . "' and snmp_query_graph_id=$data_query_graph_id");
					$save["id"] = (empty($_data_query_graph_sv_id) ? "0" : $_data_query_graph_sv_id);
					$save["hash"] = $parsed_hash["hash"];
					$save["snmp_query_graph_id"] = $data_query_graph_id;
					$save["sequence"] = $sub_item_array["sequence"];
					$save["field_name"] = $sub_item_array["field_name"];
					$save["text"] = xml_character_decode($sub_item_array["text"]);

					$data_query_graph_sv_id = sql_save($save, "snmp_query_graph_sv");

					$hash_cache["data_query_sv_graph"]{$parsed_hash["hash"]} = $data_query_graph_sv_id;
				}
			}

			/* import into: snmp_query_graph_rrd_sv */
			if (is_array($item_array["sv_data_source"])) {
				while (list($sub_item_hash, $sub_item_array) = each($item_array["sv_data_source"])) {
					/* parse information from the hash */
					$parsed_hash = parse_xml_hash($sub_item_hash);

					/* invalid/wrong hash */
					if ($parsed_hash == false) { return false; }

					unset($save);
					$_data_query_graph_rrd_sv_id = db_fetch_cell("select id from snmp_query_graph_rrd_sv where hash='" . $parsed_hash["hash"] . "' and snmp_query_graph_id=$data_query_graph_id");
					$save["id"] = (empty($_data_query_graph_rrd_sv_id) ? "0" : $_data_query_graph_rrd_sv_id);
					$save["hash"] = $parsed_hash["hash"];
					$save["snmp_query_graph_id"] = $data_query_graph_id;
					$save["data_template_id"] = resolve_hash_to_id($sub_item_array["data_template_id"], $hash_cache);
					$save["sequence"] = $sub_item_array["sequence"];
					$save["field_name"] = $sub_item_array["field_name"];
					$save["text"] = xml_character_decode($sub_item_array["text"]);

					$data_query_graph_rrd_sv_id = sql_save($save, "snmp_query_graph_rrd_sv");

					$hash_cache["data_query_sv_data_source"]{$parsed_hash["hash"]} = $data_query_graph_rrd_sv_id;
				}
			}
		}
	}

	/* status information that will be presented to the user */
	$_SESSION["import_debug_info"]["type"] = (empty($_data_query_id) ? "new" : "update");
	$_SESSION["import_debug_info"]["title"] = $xml_array["name"];
	$_SESSION["import_debug_info"]["result"] = (empty($data_query_id) ? "fail" : "success");

	return $hash_cache;
}

function &xml_to_gprint_preset($hash, &$xml_array, &$hash_cache) {
	global $fields_grprint_presets_edit;

	/* import into: graph_templates_gprint */
	$_gprint_preset_id = db_fetch_cell("select id from graph_templates_gprint where hash='$hash'");
	$save["id"] = (empty($_gprint_preset_id) ? "0" : $_gprint_preset_id);
	$save["hash"] = $hash;

	reset($fields_grprint_presets_edit);
	while (list($field_name, $field_array) = each($fields_grprint_presets_edit)) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = addslashes(xml_character_decode($xml_array[$field_name]));
		}
	}

	$gprint_preset_id = sql_save($save, "graph_templates_gprint");

	$hash_cache["gprint_preset"][$hash] = $gprint_preset_id;

	/* status information that will be presented to the user */
	$_SESSION["import_debug_info"]["type"] = (empty($_gprint_preset_id) ? "new" : "update");
	$_SESSION["import_debug_info"]["title"] = $xml_array["name"];
	$_SESSION["import_debug_info"]["result"] = (empty($gprint_preset_id) ? "fail" : "success");

	return $hash_cache;
}

function &xml_to_round_robin_archive($hash, &$xml_array, &$hash_cache) {
	global $fields_rra_edit;

	/* import into: rra */
	$_rra_id = db_fetch_cell("select id from rra where hash='$hash'");
	$save["id"] = (empty($_rra_id) ? "0" : $_rra_id);
	$save["hash"] = $hash;

	reset($fields_rra_edit);
	while (list($field_name, $field_array) = each($fields_rra_edit)) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = addslashes(xml_character_decode($xml_array[$field_name]));
		}
	}

	$rra_id = sql_save($save, "rra");

	$hash_cache["round_robin_archive"][$hash] = $rra_id;

	/* import into: rra_cf */
	$hash_items = explode("|", $xml_array["cf_items"]);

	if (!empty($hash_items[0])) {
		for ($i=0; $i<count($hash_items); $i++) {
			db_execute("replace into rra_cf (rra_id,consolidation_function_id) values ($rra_id," . $hash_items[$i] . ")");
		}
	}

	/* status information that will be presented to the user */
	$_SESSION["import_debug_info"]["type"] = (empty($_rra_id) ? "new" : "update");
	$_SESSION["import_debug_info"]["title"] = $xml_array["name"];
	$_SESSION["import_debug_info"]["result"] = (empty($rra_id) ? "fail" : "success");

	return $hash_cache;
}

function &xml_to_host_template($hash, &$xml_array, &$hash_cache) {
	global $fields_host_template_edit;

	/* import into: graph_templates_gprint */
	$_host_template_id = db_fetch_cell("select id from host_template where hash='$hash'");
	$save["id"] = (empty($_host_template_id) ? "0" : $_host_template_id);
	$save["hash"] = $hash;

	reset($fields_host_template_edit);
	while (list($field_name, $field_array) = each($fields_host_template_edit)) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = addslashes(xml_character_decode($xml_array[$field_name]));
		}
	}

	$host_template_id = sql_save($save, "host_template");

	$hash_cache["host_template"][$hash] = $host_template_id;

	/* import into: host_template_graph */
	$hash_items = explode("|", $xml_array["graph_templates"]);

	if (!empty($hash_items[0])) {
		for ($i=0; $i<count($hash_items); $i++) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($hash_items[$i]);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			if (isset($hash_cache["graph_template"]{$parsed_hash["hash"]})) {
				db_execute("replace into host_template_graph (host_template_id,graph_template_id) values ($host_template_id," . $hash_cache["graph_template"]{$parsed_hash["hash"]} . ")");
			}
		}
	}

	/* import into: host_template_snmp_query */
	$hash_items = explode("|", $xml_array["data_queries"]);

	if (!empty($hash_items[0])) {
		for ($i=0; $i<count($hash_items); $i++) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($hash_items[$i]);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			if (isset($hash_cache["data_query"]{$parsed_hash["hash"]})) {
				db_execute("replace into host_template_snmp_query (host_template_id,snmp_query_id) values ($host_template_id," . $hash_cache["data_query"]{$parsed_hash["hash"]} . ")");
			}
		}
	}

	/* status information that will be presented to the user */
	$_SESSION["import_debug_info"]["type"] = (empty($_host_template_id) ? "new" : "update");
	$_SESSION["import_debug_info"]["title"] = $xml_array["name"];
	$_SESSION["import_debug_info"]["result"] = (empty($host_template_id) ? "fail" : "success");

	return $hash_cache;
}

function &xml_to_cdef($hash, &$xml_array, &$hash_cache) {
	global $fields_cdef_edit;

	$fields_cdef_item_edit = array(
		"sequence" => "sequence",
		"type" => "type",
		"value" => "value"
		);

	/* import into: cdef */
	$_cdef_id = db_fetch_cell("select id from cdef where hash='$hash'");
	$save["id"] = (empty($_cdef_id) ? "0" : $_cdef_id);
	$save["hash"] = $hash;

	reset($fields_cdef_edit);
	while (list($field_name, $field_array) = each($fields_cdef_edit)) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			$save[$field_name] = addslashes(xml_character_decode($xml_array[$field_name]));
		}
	}

	$cdef_id = sql_save($save, "cdef");

	$hash_cache["cdef"][$hash] = $cdef_id;

	/* import into: cdef_items */
	if (is_array($xml_array["items"])) {
		while (list($item_hash, $item_array) = each($xml_array["items"])) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_cdef_item_id = db_fetch_cell("select id from cdef_items where hash='" . $parsed_hash["hash"] . "' and cdef_id=$cdef_id");
			$save["id"] = (empty($_cdef_item_id) ? "0" : $_cdef_item_id);
			$save["hash"] = $parsed_hash["hash"];
			$save["cdef_id"] = $cdef_id;

			reset($fields_cdef_item_edit);
			while (list($field_name, $field_array) = each($fields_cdef_item_edit)) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					/* check, if an inherited cdef as to be decoded (value == 5)
					 * this whole procedure relies on the sequence during template export
					 * inherited cdef's must come first, this has to be taken care of during export
					 * so we do not have any specific dependency checks here */
					if (($field_name == "value") && ($item_array["type"] == '5')) {
						/* parse information from the hash, which in this case
						 * is stored as a value of the current item being processed */
						$parsed_item_hash = parse_xml_hash($item_array["value"]);
						/* invalid/wrong hash */
						if ($parsed_item_hash == false) { return false; }
						$_cdef_id = db_fetch_cell("select id from cdef where hash='" . $parsed_item_hash["hash"] . "'");
						$save[$field_name] = $_cdef_id;
					} else {
						$save[$field_name] = addslashes(xml_character_decode($item_array[$field_name]));
					}
				}
			}

			$cdef_item_id = sql_save($save, "cdef_items");

			$hash_cache["cdef_item"]{$parsed_hash["hash"]} = $cdef_item_id;
		}
	}

	/* status information that will be presented to the user */
	$_SESSION["import_debug_info"]["type"] = (empty($_cdef_id) ? "new" : "update");
	$_SESSION["import_debug_info"]["title"] = $xml_array["name"];
	$_SESSION["import_debug_info"]["result"] = (empty($cdef_id) ? "fail" : "success");

	return $hash_cache;
}

function &xml_to_data_input_method($hash, &$xml_array, &$hash_cache) {
	global $fields_data_input_edit, $fields_data_input_field_edit, $fields_data_input_field_edit_1;

	/* aggregate field arrays */
	$fields_data_input_field_edit += $fields_data_input_field_edit_1;

	/* import into: data_input */
	$_data_input_id = db_fetch_cell("select id from data_input where hash='$hash'");
	$save["id"] = (empty($_data_input_id) ? "0" : $_data_input_id);
	$save["hash"] = $hash;

	reset($fields_data_input_edit);
	while (list($field_name, $field_array) = each($fields_data_input_edit)) {
		/* make sure this field exists in the xml array first */
		if (isset($xml_array[$field_name])) {
			/* fix issue with data input method importing and white spaces */
			if ($field_name == "input_string") {
				$xml_array[$field_name] = str_replace("><", "> <", $xml_array[$field_name]);
			}

			$save[$field_name] = addslashes(xml_character_decode($xml_array[$field_name]));
		}
	}

	$data_input_id = sql_save($save, "data_input");

	$hash_cache["data_input_method"][$hash] = $data_input_id;

	/* import into: data_input_fields */
	if (is_array($xml_array["fields"])) {
		while (list($item_hash, $item_array) = each($xml_array["fields"])) {
			/* parse information from the hash */
			$parsed_hash = parse_xml_hash($item_hash);

			/* invalid/wrong hash */
			if ($parsed_hash == false) { return false; }

			unset($save);
			$_data_input_field_id = db_fetch_cell("select id from data_input_fields where hash='" . $parsed_hash["hash"] . "' and data_input_id=$data_input_id");
			$save["id"] = (empty($_data_input_field_id) ? "0" : $_data_input_field_id);
			$save["hash"] = $parsed_hash["hash"];
			$save["data_input_id"] = $data_input_id;

			reset($fields_data_input_field_edit);
			while (list($field_name, $field_array) = each($fields_data_input_field_edit)) {
				/* make sure this field exists in the xml array first */
				if (isset($item_array[$field_name])) {
					$save[$field_name] = addslashes(xml_character_decode($item_array[$field_name]));
				}
			}

			$data_input_field_id = sql_save($save, "data_input_fields");

			$hash_cache["data_input_field"]{$parsed_hash["hash"]} = $data_input_field_id;
		}
	}

	/* update field use counter cache if possible */
	if ((isset($xml_array["input_string"])) && (!empty($data_input_id))) {
		generate_data_input_field_sequences($xml_array["input_string"], $data_input_id);
	}

	/* status information that will be presented to the user */
	$_SESSION["import_debug_info"]["type"] = (empty($_data_input_id) ? "new" : "update");
	$_SESSION["import_debug_info"]["title"] = $xml_array["name"];
	$_SESSION["import_debug_info"]["result"] = (empty($data_input_id) ? "fail" : "success");

	return $hash_cache;
}

function hash_to_friendly_name($hash, $display_type_name) {
	global $hash_type_names;

	/* parse information from the hash */
	$parsed_hash = parse_xml_hash($hash);

	/* invalid/wrong hash */
	if ($parsed_hash == false) { return false; }

	if ($display_type_name == true) {
		$prepend = "(<em>" . $hash_type_names{$parsed_hash["type"]} . "</em>) ";
	}else{
		$prepend = "";
	}

	switch ($parsed_hash["type"]) {
	case 'graph_template':
		return $prepend . db_fetch_cell("select name from graph_templates where hash='" . $parsed_hash["hash"] . "'");
	case 'data_template':
		return $prepend . db_fetch_cell("select name from data_template where hash='" . $parsed_hash["hash"] . "'");
	case 'data_template_item':
		return $prepend . db_fetch_cell("select data_source_name from data_template_rrd where hash='" . $parsed_hash["hash"] . "'");
	case 'host_template':
		return $prepend . db_fetch_cell("select name from host_template where hash='" . $parsed_hash["hash"] . "'");
	case 'data_input_method':
		return $prepend . db_fetch_cell("select name from data_input where hash='" . $parsed_hash["hash"] . "'");
	case 'data_input_field':
		return $prepend . db_fetch_cell("select name from data_input_fields where hash='" . $parsed_hash["hash"] . "'");
	case 'data_query':
		return $prepend . db_fetch_cell("select name from snmp_query where hash='" . $parsed_hash["hash"] . "'");
	case 'gprint_preset':
		return $prepend . db_fetch_cell("select name from graph_templates_gprint where hash='" . $parsed_hash["hash"] . "'");
	case 'cdef':
		return $prepend . db_fetch_cell("select name from cdef where hash='" . $parsed_hash["hash"] . "'");
	case 'round_robin_archive':
		return $prepend . db_fetch_cell("select name from rra where hash='" . $parsed_hash["hash"] . "'");
	}
}

function resolve_hash_to_id($hash, &$hash_cache_array) {
	/* parse information from the hash */
	$parsed_hash = parse_xml_hash($hash);

	/* invalid/wrong hash */
	if ($parsed_hash == false) { return false; }

	if (isset($hash_cache_array{$parsed_hash["type"]}{$parsed_hash["hash"]})) {
		$_SESSION["import_debug_info"]["dep"][$hash] = "met";
		return $hash_cache_array{$parsed_hash["type"]}{$parsed_hash["hash"]};
	}else{
		$_SESSION["import_debug_info"]["dep"][$hash] = "unmet";
		return 0;
	}
}

function parse_xml_hash($hash) {
	if (preg_match("/hash_([a-f0-9]{2})([a-f0-9]{4})([a-f0-9]{32})/", $hash, $matches)) {
		$parsed_hash["type"] = check_hash_type($matches[1]);
		$parsed_hash["version"] = strval(check_hash_version($matches[2]));
		$parsed_hash["hash"] = $matches[3];

		/* an error has occured */
		if (($parsed_hash["type"] === false) || ($parsed_hash["version"] === false)) {
			return false;
		}
	}else{
		cacti_log(__FUNCTION__ . " ERROR wrong hash format", false);
		return false;
	}

	return $parsed_hash;
}

function check_hash_type($hash_type) {
	global $hash_type_codes;

	/* lets not mess up the pointer for other people */
	$local_hash_type_codes = $hash_type_codes;

	reset($local_hash_type_codes);
	while (list($type, $code) = each($local_hash_type_codes)) {
		if ($code == $hash_type) {
			$current_type = $type;
		}
	}

	if (!isset($current_type)) {
		raise_message(18); /* error: cannot find type */
		return false;
	}

	return $current_type;
}

function check_hash_version($hash_version) {
	global $hash_version_codes, $config;

	$i = 0;

	reset($hash_version_codes);
	while (list($version, $code) = each($hash_version_codes)) {
		if ($version == $config["cacti_version"]) {
			$current_version_index = $i;
		}

		if ($code == $hash_version) {
			$hash_version_index = $i;
			$current_version = $version;
		}

		$i++;
	}

	if (!isset($current_version_index)) {
		raise_message(15); /* error: current cacti version does not exist! */
		return false;
	}elseif (!isset($hash_version_index)) {
		raise_message(16); /* error: hash version does not exist! */
		return false;
	}elseif ($hash_version_index > $current_version_index) {
		raise_message(17); /* error: hash made with a newer version of cacti */
		return false;
	}

	return $current_version;
}

function get_version_index($string_version) {
	global $hash_version_codes;

	$i = 0;

	reset($hash_version_codes);
	while (list($version, $code) = each($hash_version_codes)) {
		if ($string_version == $version) {
			return $i;
		}

		$i++;
	}

	/* version index not found */
	return -1;
}

function xml_character_decode($text) {
	if (function_exists("html_entity_decode")) {
		return html_entity_decode($text, ENT_QUOTES, "UTF-8");
	} else {
		$trans_tbl = get_html_translation_table(HTML_ENTITIES);
		$trans_tbl = array_flip($trans_tbl);
		return strtr($text, $trans_tbl);
	}
}

?>
