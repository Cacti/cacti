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

function graph_template_to_xml($graph_template_id) {
	global $struct_graph, $fields_graph_template_input_edit, $struct_graph_item, $export_errors;

	// Remote caching item
	unset($struct_graph_item['data_template_id']);

	$hash['graph_template'] = get_hash_version('graph_template') . get_hash_graph_template($graph_template_id);
	$xml_text = '';

	$graph_template = db_fetch_row_prepared('SELECT *
		FROM graph_templates
		WHERE id = ?',
		array($graph_template_id));

	$graph_template_graph = db_fetch_row_prepared('SELECT *
		FROM graph_templates_graph
		WHERE graph_template_id = ?
		AND local_graph_id = 0
		ORDER BY id',
		array($graph_template_id));

	$graph_template_items = db_fetch_assoc_prepared('SELECT *
		FROM graph_templates_item
		WHERE graph_template_id = ?
		AND local_graph_id = 0
		AND hash != ""
		ORDER BY sequence',
		array($graph_template_id));

	$graph_template_inputs = db_fetch_assoc_prepared('SELECT *
		FROM graph_template_input
		WHERE graph_template_id = ?
		ORDER BY id',
		array($graph_template_id));

	if ((empty($graph_template['id'])) || (empty($graph_template_graph['id']))) {
		$export_errors++;
		raise_message(30);
		cacti_log('ERROR: Invalid Graph Template found in Database for Template ' . $graph_template['name'] . '[' . $graph_template['id'] . '] GTGid: ' . $graph_template_graph['id'] . '.  Please run database repair script to identify and/or correct.', false, 'WEBUI');
		return;
	}

	$xml_text .= '<hash_' . $hash['graph_template'] . ">\n";
	$xml_text .= "\t<name>"        . xml_character_encode($graph_template['name'])        . "</name>\n";
	$xml_text .= "\t<multiple>"    . xml_character_encode($graph_template['multiple'])    . "</multiple>\n";
	$xml_text .= "\t<test_source>" . xml_character_encode($graph_template['test_source']) . "</test_source>\n";

	$xml_text .= "\t<graph>\n";

	/* XML Branch: <graph> */
	foreach ($struct_graph as $field_name => $field_array) {
		if ($field_array['method'] != 'spacer') {
			$xml_text .= "\t\t<t_$field_name>" . xml_character_encode($graph_template_graph['t_' . $field_name]) . "</t_$field_name>\n";
			$xml_text .= "\t\t<$field_name>" . xml_character_encode($graph_template_graph[$field_name]) . "</$field_name>\n";
		}
	}

	$xml_text .= "\t</graph>\n";

	/* XML Branch: <items> */

	$xml_text .= "\t<items>\n";

	$i = 0;
	if (cacti_sizeof($graph_template_items) > 0) {
		foreach ($graph_template_items as $item) {
			$hash['graph_template_item'] = get_hash_version('graph_template_item') . get_hash_graph_template($item['id'], 'graph_template_item');

			$xml_text .= "\t\t<hash_" . $hash['graph_template_item'] . ">\n";

			foreach ($struct_graph_item as $field_name => $field_array) {
				if (!empty($item[$field_name])) {
					switch ($field_name) {
						case 'task_item_id':
							$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version('data_template_item') . get_hash_data_template($item[$field_name], 'data_template_item') . "</$field_name>\n";
							break;
						case 'cdef_id':
							$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version('cdef') . get_hash_cdef($item[$field_name]) . "</$field_name>\n";
							break;
						case 'vdef_id':
							$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version('vdef') . get_hash_vdef($item[$field_name]) . "</$field_name>\n";
							break;
						case 'gprint_id':
							$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version('gprint_preset') . get_hash_gprint($item[$field_name]) . "</$field_name>\n";
							break;
						case 'color_id':
							$xml_text .= "\t\t\t<$field_name>" . db_fetch_cell_prepared('SELECT hex FROM colors WHERE id = ?', array($item[$field_name])) . "</$field_name>\n";
							break;
						default:
							$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item[$field_name]) . "</$field_name>\n";
							break;
					}
				} else {
					$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item[$field_name]) . "</$field_name>\n";
				}
			}

			$xml_text .= "\t\t</hash_" . $hash['graph_template_item'] . ">\n";

			$i++;
		}
	}

	$xml_text .= "\t</items>\n";

	/* XML Branch: <inputs> */

	$xml_text .= "\t<inputs>\n";

	$i = 0;
	if (cacti_sizeof($graph_template_inputs) > 0) {
		foreach ($graph_template_inputs as $item) {
			$hash['graph_template_input'] = get_hash_version('graph_template_input') . get_hash_graph_template($item['id'], 'graph_template_input');

			$xml_text .= "\t\t<hash_" . $hash['graph_template_input'] . ">\n";

			foreach ($fields_graph_template_input_edit as $field_name => $field_array) {
				if (($field_array['method'] != 'hidden_zero') && ($field_array['method'] != 'hidden')) {
					$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item[$field_name]) . "</$field_name>\n";
				}
			}

			$graph_template_input_items = db_fetch_assoc_prepared('SELECT graph_template_item_id
				FROM graph_template_input_defs
				WHERE graph_template_input_id = ?',
				array($item['id']));

			$xml_text .= "\t\t\t<items>";

			$j = 0;
			if (cacti_sizeof($graph_template_input_items) > 0) {
				foreach ($graph_template_input_items as $item2) {
					$xml_text .= 'hash_' . get_hash_version('graph_template') . get_hash_graph_template($item2['graph_template_item_id'], 'graph_template_item');

					if (($j+1) < cacti_sizeof($graph_template_input_items)) {
						$xml_text .= '|';
					}

					$j++;
				}
			}

			$xml_text .= "</items>\n";
			$xml_text .= "\t\t</hash_" . $hash['graph_template_input'] . ">\n";

			$i++;
		}
	}

	$xml_text .= "\t</inputs>\n";
	$xml_text .= '</hash_' . $hash['graph_template'] . '>';

	return $xml_text;
}

function data_template_to_xml($data_template_id) {
	global $struct_data_source, $struct_data_source_item, $export_errors;

	$hash['data_template'] = get_hash_version('data_template') . get_hash_data_template($data_template_id);
	$xml_text = '';

	$data_template = db_fetch_row_prepared('SELECT id, name
		FROM data_template
		WHERE id = ?',
		array($data_template_id));

	$data_template_data = db_fetch_row_prepared('SELECT *
		FROM data_template_data
		WHERE data_template_id = ?
		AND local_data_id = 0
		ORDER BY id',
		array($data_template_id));

	$data_template_rrd = db_fetch_assoc_prepared('SELECT *
		FROM data_template_rrd
		WHERE data_template_id = ?
		AND local_data_id = 0
		AND hash != ""
		ORDER BY id',
		array($data_template_id));

	$data_input_data = db_fetch_assoc_prepared('SELECT *
		FROM data_input_data
		WHERE data_template_data_id = ?',
		array($data_template_data['id']));

	if ((empty($data_template['id'])) || (empty($data_template_data['id']))) {
		$export_errors++;
		raise_message(27);
		cacti_log('ERROR: Invalid Data Template found in Database.  Please run database repair script to identify and/or correct.', false, 'WEBUI');
		return;
	}

	$xml_text .= '<hash_' . $hash['data_template'] . ">\n\t<name>" . xml_character_encode($data_template['name']) . "</name>\n\t<ds>\n";

	/* XML Branch: <ds> */
	foreach ($struct_data_source as $field_name => $field_array) {
		if (isset($data_template_data['t_' . $field_name])) {
			$xml_text .= "\t\t<t_$field_name>" . xml_character_encode($data_template_data['t_' . $field_name]) . "</t_$field_name>\n";
		}

		if (($field_name == 'data_input_id') && (!empty($data_template_data[$field_name]))) {
			$xml_text .= "\t\t<$field_name>hash_" . get_hash_version('data_input_method') . get_hash_data_input($data_template_data[$field_name]) . "</$field_name>\n";
		} elseif (($field_name == 'data_source_profile_id') && (!empty($data_template_data[$field_name]))) {
			$xml_text .= "\t\t<$field_name>hash_" . get_hash_version('data_source_profile') . get_hash_data_source_profile($data_template_data[$field_name]) . "</$field_name>\n";
		} else {
			if (isset($data_template_data[$field_name])) {
				$xml_text .= "\t\t<$field_name>" . xml_character_encode($data_template_data[$field_name]) . "</$field_name>\n";
			}
		}
	}

	$xml_text .= "\t</ds>\n";

	/* XML Branch: <items> */

	$xml_text .= "\t<items>\n";

	$i = 0;
	if (cacti_sizeof($data_template_rrd) > 0) {
		foreach ($data_template_rrd as $item) {
			$hash['data_template_item'] = get_hash_version('data_template_item') . get_hash_data_template($item['id'], 'data_template_item');

			$xml_text .= "\t\t<hash_" . $hash['data_template_item'] . ">\n";

			foreach ($struct_data_source_item as $field_name => $field_array) {
				if (isset($item['t_' . $field_name])) {
					$xml_text .= "\t\t\t<t_$field_name>" . xml_character_encode($item['t_' . $field_name]) . "</t_$field_name>\n";
				}

				if (($field_name == 'data_input_field_id') && (!empty($item[$field_name]))) {
					$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version('data_input_field') . get_hash_data_input($item[$field_name], 'data_input_field') . "</$field_name>\n";
				} else {
					if (isset($item[$field_name])) {
						$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item[$field_name]) . "</$field_name>\n";
					}
				}
			}

			$xml_text .= "\t\t</hash_" . $hash['data_template_item'] . ">\n";

			$i++;
		}
	}

	$xml_text .= "\t</items>\n";

	/* XML Branch: <data> */

	$xml_text .= "\t<data>\n";

	$i = 0;
	if (cacti_sizeof($data_input_data) > 0) {
		foreach ($data_input_data as $item) {
			$xml_text .= "\t\t<item_" . str_pad(strval($i), 3, '0', STR_PAD_LEFT) . ">\n";

			$xml_text .= "\t\t\t<data_input_field_id>hash_" . get_hash_version('data_input_field') . get_hash_data_input($item['data_input_field_id'], 'data_input_field') . "</data_input_field_id>\n";
			$xml_text .= "\t\t\t<t_value>" . xml_character_encode($item['t_value']) . "</t_value>\n";
			$xml_text .= "\t\t\t<value>" . xml_character_encode($item['value']) . "</value>\n";

			$xml_text .= "\t\t</item_" . str_pad(strval($i), 3, '0', STR_PAD_LEFT) . ">\n";

			$i++;
		}
	}

	$xml_text .= "\t</data>\n";

	$xml_text .= '</hash_' . $hash['data_template'] . '>';

	return $xml_text;
}

function data_input_method_to_xml($data_input_id) {
	global $fields_data_input_edit, $fields_data_input_field_edit, $fields_data_input_field_edit_1, $export_errors;

	/* aggregate field arrays */
	$fields_data_input_field_edit += $fields_data_input_field_edit_1;

	$hash['data_input_method'] = get_hash_version('data_input_method') . get_hash_data_input($data_input_id);
	$xml_text = '';

	$data_input = db_fetch_row_prepared('SELECT *
		FROM data_input
		WHERE id = ?',
		array($data_input_id));

	$data_input_fields = db_fetch_assoc_prepared('SELECT *
		FROM data_input_fields
		WHERE data_input_id = ?
		ORDER BY id',
		array($data_input_id));

	if (empty($data_input['id'])) {
		$export_errors++;
		raise_message(26);
		cacti_log('ERROR: Invalid Data Input Method found in Data Template.  Please run database repair script to identify and/or correct.', false, 'WEBUI');
		return;
	}

	$xml_text .= '<hash_' . $hash['data_input_method'] . ">\n";

	/* XML Branch: <> */
	foreach ($fields_data_input_edit as $field_name => $field_array) {
		if (($field_array['method'] != 'hidden_zero') &&
			($field_array['method'] != 'hidden') &&
			($field_array['method'] != 'spacer') &&
			($field_array['method'] != 'other')) {
			if ($field_name == 'input_string') {
				$xml_text .= "\t<$field_name>" . base64_encode($data_input[$field_name]) . "</$field_name>\n";
			} else {
				$xml_text .= "\t<$field_name>" . xml_character_encode($data_input[$field_name]) . "</$field_name>\n";
			}
		}
	}

	/* XML Branch: <fields> */

	$xml_text .= "\t<fields>\n";

	if (cacti_sizeof($data_input_fields)) {
		foreach ($data_input_fields as $item) {
			$hash['data_input_field'] = get_hash_version('data_input_field') . get_hash_data_input($item['id'], 'data_input_field');

			$xml_text .= "\t\t<hash_" . $hash['data_input_field'] . ">\n";

			foreach ($fields_data_input_field_edit as $field_name => $field_array) {
				if (($field_name == 'input_output') && (!empty($item[$field_name]))) {
					$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item[$field_name]) . "</$field_name>\n";
				} else {
					$method = $field_array['method'];

					if ($method != 'hidden_zero' && $method != 'hidden' && $method != 'spacer') {
						// Work around renaming of field in form
						// To get around JavaScript form issue
						// In data_input.php
						if ($field_name == 'fname') {
							$field_name = 'name';
						}

						$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item[$field_name]) . "</$field_name>\n";
					}
				}
			}

			$xml_text .= "\t\t</hash_" . $hash['data_input_field'] . ">\n";
		}
	}

	$xml_text .= "\t</fields>\n";

	$xml_text .= '</hash_' . $hash['data_input_method'] . '>';

	return $xml_text;
}


/** encode a cdef along with all cdef_items as XML text
 * @param int $cdef_id	- the id of the cdef that has to be encoded
 * @return string		- the resulting XML text
 */
function cdef_to_xml($cdef_id) {
	global $fields_cdef_edit, $export_errors;

	$fields_cdef_item_edit = array(
		'sequence' => 'sequence',
		'type' => 'type',
		'value' => 'value'
	);

	$hash['cdef'] = get_hash_version('cdef') . get_hash_cdef($cdef_id);
	$xml_text = '';

	$cdef = db_fetch_row_prepared('SELECT *
		FROM cdef
		WHERE id = ?',
		array($cdef_id));

	$cdef_items = db_fetch_assoc_prepared('SELECT *
		FROM cdef_items
		WHERE cdef_id = ?
		ORDER BY sequence',
		array($cdef_id));

	if (empty($cdef['id'])) {
		$export_errors++;
		raise_message(25);
		cacti_log('ERROR: Invalid CDEF found in Graph Template.  Please run database repair script to identify and/or correct.', false, 'WEBUI');
		return;
	}

	$xml_text .= '<hash_' . $hash['cdef'] . ">\n";

	/* XML Branch: <> */
	foreach ($fields_cdef_edit as $field_name => $field_array) {
		if (($field_array['method'] != 'hidden_zero') && ($field_array['method'] != 'hidden')) {
			$xml_text .= "\t<$field_name>" . xml_character_encode($cdef[$field_name]) . "</$field_name>\n";
		}
	}

	/* XML Branch: <items> */

	$xml_text .= "\t<items>\n";

	$i = 0;
	if (cacti_sizeof($cdef_items) > 0) {
		foreach ($cdef_items as $item) {
			$hash['cdef_item'] = get_hash_version('cdef_item') . get_hash_cdef($item['id'], 'cdef_item');

			$xml_text .= "\t\t<hash_" . $hash['cdef_item'] . ">\n";

			/* now do the encoding */
			foreach ($fields_cdef_item_edit as $field_name => $field_array) {
				/* check, if an inherited cdef as to be encoded */
				if (($field_name == 'value') && ($item['type'] == '5')) {
					$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version('cdef') . get_hash_cdef($item[$field_name]) . "</$field_name>\n";
				} else {
					$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item[$field_name]) . "</$field_name>\n";
				}
			}

			$xml_text .= "\t\t</hash_" . $hash['cdef_item'] . ">\n";

			$i++;
		}
	}

	$xml_text .= "\t</items>\n";
	$xml_text .= '</hash_' . $hash['cdef'] . '>';

	return $xml_text;
}

/** encode given VDEF as XML string
 * @param int $vdef_id  - id of VDEF
 * @return string       - XML text of encoded VDEF
 */
function vdef_to_xml($vdef_id) {
	global $config;

	include_once($config['library_path'] . '/vdef.php');

	$hash['vdef'] = get_hash_version('vdef') . get_hash_vdef($vdef_id);
	$xml_text = '';

	$vdef = db_fetch_row_prepared('SELECT *
		FROM vdef
		WHERE id = ?',
		array($vdef_id));

	$vdef_items = db_fetch_assoc_prepared('SELECT *
		FROM vdef_items
		WHERE vdef_id = ?
		ORDER BY sequence',
		array($vdef_id));

	if (empty($vdef['id'])) {
		$err_msg = 'Invalid VDEF.';
		return $err_msg;
	}

	$xml_text .= '<hash_' . $hash['vdef'] . ">\n";

	/* XML Branch: <> */
	$fields_vdef_edit = preset_vdef_form_list();
	foreach ($fields_vdef_edit as $field_name => $field_array) {
		if (($field_array['method'] != 'hidden_zero') && ($field_array['method'] != 'hidden') && ($field_array['method'] != 'spacer')) {
			$xml_text .= "\t<$field_name>" . xml_character_encode($vdef[$field_name]) . "</$field_name>\n";
		}
	}

	/* XML Branch: <items> */

	$xml_text .= "\t<items>\n";

	$i = 0;
	if (cacti_sizeof($vdef_items) > 0) {
		foreach ($vdef_items as $item) {
			$hash['vdef_item'] = get_hash_version('vdef_item') . get_hash_vdef($item['id'], 'vdef_item');

			$xml_text .= "\t\t<hash_" . $hash['vdef_item'] . ">\n";
			$fields_vdef_item_edit = preset_vdef_item_form_list();
			foreach ($fields_vdef_item_edit as $field_name) {
				$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item[$field_name]) . "</$field_name>\n";
			}
			$xml_text .= "\t\t</hash_" . $hash['vdef_item'] . ">\n";

			$i++;
		}
	}

	$xml_text .= "\t</items>\n";
	$xml_text .= '</hash_' . $hash['vdef'] . '>';

	return $xml_text;
}

function gprint_preset_to_xml($gprint_preset_id) {
	global $fields_grprint_presets_edit, $export_errors;

	$hash = get_hash_version('gprint_preset') . get_hash_gprint($gprint_preset_id);
	$xml_text = '';

	$graph_templates_gprint = db_fetch_row_prepared('SELECT *
		FROM graph_templates_gprint
		WHERE id = ?',
		array($gprint_preset_id));

	if (empty($graph_templates_gprint['id'])) {
		$export_errors++;
		raise_message(24);
		cacti_log('ERROR: Invalid GPRINT preset found in Graph Template.  Please run database repair script to identify and/or correct.', false, 'WEBUI');
		return;
	}

	$xml_text .= "<hash_$hash>\n";

	/* XML Branch: <> */
	foreach ($fields_grprint_presets_edit as $field_name => $field_array) {
		if (($field_array['method'] != 'hidden_zero') && ($field_array['method'] != 'hidden')) {
			$xml_text .= "\t<$field_name>" . xml_character_encode($graph_templates_gprint[$field_name]) . "</$field_name>\n";
		}
	}

	$xml_text .= "</hash_$hash>";

	return $xml_text;
}

function data_source_profile_to_xml($data_source_profile_id) {
	global $fields_profile_edit, $fields_profile_rra_edit, $export_errors;

	$hash        = get_hash_version('data_source_profile') . get_hash_data_source_profile($data_source_profile_id);
	$xml_text    = '';

	$profile = db_fetch_row_prepared('SELECT *
		FROM data_source_profiles
		WHERE id = ?',
		array($data_source_profile_id));

	$profile_cf = db_fetch_assoc_prepared('SELECT *
		FROM data_source_profiles_cf
		WHERE data_source_profile_id = ?
		ORDER BY consolidation_function_id',
		array($data_source_profile_id));

	$profile_rra = db_fetch_assoc_prepared('SELECT *
		FROM data_source_profiles_rra
		WHERE data_source_profile_id = ?
		ORDER BY steps',
		array($data_source_profile_id));

	if (empty($profile['id'])) {
		$export_errors++;
		raise_message(23);
		cacti_log('ERROR: Invalid Data Source Profile found during Data Template export.  Please run database repair script to identify and/or correct.', false, 'WEBUI');
		return;
	}

	$xml_text .= "<hash_$hash>\n";

	/* XML Branch: <> */
	foreach ($fields_profile_edit as $field_name => $field_array) {
		if (($field_array['method'] != 'hidden_zero') && ($field_array['method'] != 'hidden')) {
			if (isset($profile[$field_name])) {
				$xml_text .= "\t<$field_name>" . xml_character_encode($profile[$field_name]) . "</$field_name>\n";
			}
		}
	}

	$xml_text .= "\t<cf_items>";

	/* XML Branch: <cf_items> */
	$i = 0;
	if (cacti_sizeof($profile_cf) > 0) {
		foreach ($profile_cf as $item) {
			$xml_text .= $item['consolidation_function_id'];

			if (($i+1) < cacti_sizeof($profile_cf)) {
				$xml_text .= '|';
			}

			$i++;
		}
	}

	$xml_text .= "</cf_items>\n";

	$xml_text .= "\t<items>\n";

	$i = 0;
	if (cacti_sizeof($profile_rra)) {
		foreach ($profile_rra as $item) {
			$xml_text .= "\t\t<item_" . str_pad(strval($i), 3, '0', STR_PAD_LEFT) . ">\n";
			foreach ($fields_profile_rra_edit as $field_name => $field_array) {
				if (($field_array['method'] != 'hidden_zero') && ($field_array['method'] != 'hidden' && ($field_array['method'] != 'other')) && ($field_array['method'] != 'spacer')) {
					$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item[$field_name]) . "</$field_name>\n";
				}
			}
			$xml_text .= "\t\t</item_" . str_pad(strval($i), 3, '0', STR_PAD_LEFT) . ">\n";

			$i++;
		}
	}

	$xml_text .= "\t</items>\n";


	$xml_text .= "</hash_$hash>";

	return $xml_text;
}

function host_template_to_xml($host_template_id) {
	global $fields_host_template_edit, $export_errors;

	$hash = get_hash_version('host_template') . get_hash_host_template($host_template_id);
	$xml_text = '';

	$host_template = db_fetch_row_prepared('SELECT *
		FROM host_template
		WHERE id = ?',
		array($host_template_id));

	$host_template_graph = db_fetch_assoc_prepared('SELECT *
		FROM host_template_graph
		WHERE host_template_id = ?
		ORDER BY graph_template_id',
		array($host_template_id));

	$host_template_snmp_query = db_fetch_assoc_prepared('SELECT *
		FROM host_template_snmp_query
		WHERE host_template_id = ?
		ORDER BY snmp_query_id',
		array($host_template_id));

	if (empty($host_template['id'])) {
		$export_errors++;
		raise_message(28);
		cacti_log('ERROR: Invalid Device Template found during Export.  Please run database repair script to identify and/or correct.', false, 'WEBUI');
		return;
	}

	$xml_text .= "<hash_$hash>\n";

	/* XML Branch: <> */
	foreach ($fields_host_template_edit as $field_name => $field_array) {
		if (($field_array['method'] != 'hidden_zero') && ($field_array['method'] != 'hidden')) {
			$xml_text .= "\t<$field_name>" . xml_character_encode($host_template[$field_name]) . "</$field_name>\n";
		}
	}

	/* XML Branch: <graph_templates> */
	$xml_text .= "\t<graph_templates>";

	$j = 0;
	if (cacti_sizeof($host_template_graph) > 0) {
		foreach ($host_template_graph as $item) {
			$xml_text .= 'hash_' . get_hash_version('graph_template') . get_hash_graph_template($item['graph_template_id']);

			if (($j+1) < cacti_sizeof($host_template_graph)) {
				$xml_text .= '|';
			}

			$j++;
		}
	}

	$xml_text .= "</graph_templates>\n";

	/* XML Branch: <data_queries> */
	$xml_text .= "\t<data_queries>";

	$j = 0;
	if (cacti_sizeof($host_template_snmp_query) > 0) {
		foreach ($host_template_snmp_query as $item) {
			$xml_text .= 'hash_' . get_hash_version('data_query') . get_hash_data_query($item['snmp_query_id']);

			if (($j+1) < cacti_sizeof($host_template_snmp_query)) {
				$xml_text .= '|';
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

	$hash['data_query'] = get_hash_version('data_query') . get_hash_data_query($data_query_id);
	$xml_text = '';

	$snmp_query = db_fetch_row_prepared('SELECT *
		FROM snmp_query
		WHERE id = ?',
		array($data_query_id));

	$snmp_query_graph = db_fetch_assoc_prepared('SELECT *
		FROM snmp_query_graph
		WHERE snmp_query_id = ?
		ORDER BY id',
		array($data_query_id));

	if (empty($snmp_query['id'])) {
		$export_errors++;
		raise_message(28);
		cacti_log('ERROR: Invalid Data Query found during Export.  Please run database repair script to identify and/or correct.', false, 'WEBUI');
		return;
	}

	$xml_text .= '<hash_' . $hash['data_query'] . ">\n";

	/* XML Branch: <> */
	foreach ($fields_data_query_edit as $field_name => $field_array) {
		if (($field_name == 'data_input_id') && (!empty($snmp_query[$field_name]))) {
			$xml_text .= "\t<$field_name>hash_" . get_hash_version('data_input_method') . get_hash_data_input($snmp_query[$field_name]) . "</$field_name>\n";
		} else {
			if (($field_array['method'] != 'hidden_zero') && ($field_array['method'] != 'hidden')) {
				$xml_text .= "\t<$field_name>" . xml_character_encode($snmp_query[$field_name]) . "</$field_name>\n";
			}
		}
	}

	/* XML Branch: <graphs> */

	$xml_text .= "\t<graphs>\n";

	$i = 0;
	if (cacti_sizeof($snmp_query_graph) > 0) {
		foreach ($snmp_query_graph as $item) {
			$hash['data_query_graph'] = get_hash_version('data_query_graph') . get_hash_data_query($item['id'], 'data_query_graph');

			$xml_text .= "\t\t<hash_" . $hash['data_query_graph'] . ">\n";

			foreach ($fields_data_query_item_edit as $field_name => $field_array) {
				if (($field_name == 'graph_template_id') && (!empty($item[$field_name]))) {
					$xml_text .= "\t\t\t<$field_name>hash_" . get_hash_version('graph_template') . get_hash_graph_template($item[$field_name]) . "</$field_name>\n";
				} else {
					if (($field_array['method'] != 'hidden_zero') && ($field_array['method'] != 'hidden')) {
						$xml_text .= "\t\t\t<$field_name>" . xml_character_encode($item[$field_name]) . "</$field_name>\n";
					}
				}
			}

			$snmp_query_graph_rrd_sv = db_fetch_assoc_prepared('SELECT *
				FROM snmp_query_graph_rrd_sv
				WHERE snmp_query_graph_id = ?
				ORDER BY sequence',
				array($item['id']));

			$snmp_query_graph_sv = db_fetch_assoc_prepared('SELECT *
				FROM snmp_query_graph_sv
				WHERE snmp_query_graph_id = ?
				ORDER BY sequence',
				array($item['id']));

			$snmp_query_graph_rrd = db_fetch_assoc_prepared('SELECT *
				FROM snmp_query_graph_rrd
				WHERE snmp_query_graph_id = ?
				AND data_template_id > 0
				ORDER BY data_template_rrd_id',
				array($item['id']));

			/* XML Branch: <graphs/rrd> */

			$xml_text .= "\t\t\t<rrd>\n";

			$i = 0;
			if (cacti_sizeof($snmp_query_graph_rrd) > 0) {
				foreach ($snmp_query_graph_rrd as $item2) {
					$xml_text .= "\t\t\t\t<item_" . str_pad(strval($i), 3, '0', STR_PAD_LEFT) . ">\n";

					$xml_text .= "\t\t\t\t\t<snmp_field_name>" . $item2['snmp_field_name'] . "</snmp_field_name>\n";
					$xml_text .= "\t\t\t\t\t<data_template_id>hash_" . get_hash_version('data_template') . get_hash_data_template($item2['data_template_id']) . "</data_template_id>\n";
					$xml_text .= "\t\t\t\t\t<data_template_rrd_id>hash_" . get_hash_version('data_template_item') . get_hash_data_template($item2['data_template_rrd_id'], 'data_template_item') . "</data_template_rrd_id>\n";

					$xml_text .= "\t\t\t\t</item_" . str_pad(strval($i), 3, '0', STR_PAD_LEFT) . ">\n";

					$i++;
				}
			}

			$xml_text .= "\t\t\t</rrd>\n";

			/* XML Branch: <graphs/sv_graph> */

			$xml_text .= "\t\t\t<sv_graph>\n";

			$j = 0;
			if (cacti_sizeof($snmp_query_graph_sv) > 0) {
				foreach ($snmp_query_graph_sv as $item2) {
					$hash['data_query_sv_graph'] = get_hash_version('data_query_sv_graph') . get_hash_data_query($item2['id'], 'data_query_sv_graph');

					$xml_text .= "\t\t\t\t<hash_" . $hash['data_query_sv_graph'] . ">\n";

					$xml_text .= "\t\t\t\t\t<field_name>" . xml_character_encode($item2['field_name']) . "</field_name>\n";
					$xml_text .= "\t\t\t\t\t<sequence>" . $item2['sequence'] . "</sequence>\n";
					$xml_text .= "\t\t\t\t\t<text>" . xml_character_encode($item2['text']) . "</text>\n";

					$xml_text .= "\t\t\t\t</hash_" . $hash['data_query_sv_graph'] . ">\n";

					$j++;
				}
			}

			$xml_text .= "\t\t\t</sv_graph>\n";

			/* XML Branch: <graphs/sv_data_source> */

			$xml_text .= "\t\t\t<sv_data_source>\n";

			$j = 0;
			if (cacti_sizeof($snmp_query_graph_rrd_sv) > 0) {
				foreach ($snmp_query_graph_rrd_sv as $item2) {
					$hash['data_query_sv_data_source'] = get_hash_version('data_query_sv_data_source') . get_hash_data_query($item2['id'], 'data_query_sv_data_source');

					$xml_text .= "\t\t\t\t<hash_" . $hash['data_query_sv_data_source'] . ">\n";

					$xml_text .= "\t\t\t\t\t<field_name>" . xml_character_encode($item2['field_name']) . "</field_name>\n";
					$xml_text .= "\t\t\t\t\t<data_template_id>hash_" . get_hash_version('data_template') . get_hash_data_template($item2['data_template_id']) . "</data_template_id>\n";
					$xml_text .= "\t\t\t\t\t<sequence>" . $item2['sequence'] . "</sequence>\n";
					$xml_text .= "\t\t\t\t\t<text>" . xml_character_encode($item2['text']) . "</text>\n";

					$xml_text .= "\t\t\t\t</hash_" . $hash['data_query_sv_data_source'] . ">\n";

					$j++;
				}
			}

			$xml_text .= "\t\t\t</sv_data_source>\n";

			$xml_text .= "\t\t</hash_" . $hash['data_query_graph'] . ">\n";

			$i++;
		}
	}

	$xml_text .= "\t</graphs>\n";

	$xml_text .= '</hash_' . $hash['data_query'] . '>';

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
		$graph_template_items = db_fetch_assoc_prepared('SELECT
			data_template_rrd.data_template_id
			FROM (graph_templates_item,data_template_rrd)
			WHERE graph_templates_item.task_item_id = data_template_rrd.id
			AND graph_templates_item.graph_template_id = ?
			AND graph_templates_item.local_graph_id = 0
			AND graph_templates_item.task_item_id > 0
			GROUP BY data_template_rrd.data_template_id', array($id));

		if (cacti_sizeof($graph_template_items) > 0) {
			foreach ($graph_template_items as $item) {
				if (!isset($dep_array['data_template'][$item['data_template_id']])) {
					$dep_array = resolve_dependencies('data_template', $item['data_template_id'], $dep_array);
				}
			}
		}

		/* dep: cdef */
		$cdef_items = db_fetch_assoc_prepared('SELECT cdef_id
			FROM graph_templates_item
			WHERE graph_template_id = ?
			AND local_graph_id = 0
			AND cdef_id > 0
			GROUP BY cdef_id', array($id));

		$recursive = true;
		/* in the first turn, search all inherited cdef items related to all cdef's known on highest recursion level */
		$search_cdef_items = array_rekey($cdef_items, 'cdef_id', 'cdef_id');
		if (cacti_sizeof($cdef_items) > 0) {
			while ($recursive) {
				/* are there any inherited cdef's within those referenced by any graph item?
				 * search for all cdef_items of type = 5 (inherited cdef)
				 * but fetch only those related to already given cdef's */
				$sql = 'SELECT value as cdef_id ' .
					'FROM cdef_items ' .
					'WHERE type = 5 ' .
					'AND ' . array_to_sql_or($search_cdef_items, 'cdef_id');

				$inherited_cdef_items = db_fetch_assoc($sql);

				/* in case we found any */
				if (cacti_sizeof($inherited_cdef_items) > 0) {
					/* join all cdef's found
					 * ATTENTION!
					 * sequence of parameters matters!
					 * we must place the newly found inherited items first
					 * reason is, that during import, the leaves have to be tackled first,
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
				if (!isset($dep_array['cdef'][$item['cdef_id']])) {
					$dep_array = resolve_dependencies('cdef', $item['cdef_id'], $dep_array);
				}
			}
		}

		/* dep: vdef */
		$vdef_items = db_fetch_assoc_prepared('SELECT vdef_id
			FROM graph_templates_item
			WHERE graph_template_id = ?
			AND local_graph_id = 0
			AND vdef_id > 0
			GROUP BY vdef_id',
			array($id));

		if (cacti_sizeof($vdef_items) > 0) {
			foreach ($vdef_items as $item) {
				if (!isset($dep_array['vdef'][$item['vdef_id']])) {
					$dep_array = resolve_dependencies('vdef', $item['vdef_id'], $dep_array);
				}
			}
		}

		/* dep: gprint preset */
		$graph_template_items = db_fetch_assoc_prepared('SELECT gprint_id
			FROM graph_templates_item
			WHERE graph_template_id = ?
			AND local_graph_id = 0
			AND gprint_id > 0
			GROUP BY gprint_id',
			array($id));

		if (cacti_sizeof($graph_template_items) > 0) {
			foreach ($graph_template_items as $item) {
				if (!isset($dep_array['gprint_preset'][$item['gprint_id']])) {
					$dep_array = resolve_dependencies('gprint_preset', $item['gprint_id'], $dep_array);
				}
			}
		}

		break;
	case 'data_template':
		/* dep: data input method */
		$item = db_fetch_row_prepared('SELECT data_input_id
			FROM data_template_data
			WHERE data_template_id = ?
			AND local_data_id = 0
			AND data_input_id > 0',
			array($id));

		if ((!empty($item)) && (!isset($dep_array['data_input_method'][$item['data_input_id']]))) {
			$dep_array = resolve_dependencies('data_input_method', $item['data_input_id'], $dep_array);
		}

		/* dep: data source profiles */
		$profiles = db_fetch_assoc_prepared('SELECT DISTINCT data_source_profile_id
			FROM data_template_data
			WHERE data_template_id = ?
			AND local_data_id = 0',
			array($id));

		if (cacti_sizeof($profiles)) {
			foreach ($profiles as $item) {
				if (!isset($dep_array['data_source_profile'][$item['data_source_profile_id']])) {
					$dep_array = resolve_dependencies('data_source_profile', $item['data_source_profile_id'], $dep_array);
				}
			}
		}

		break;
	case 'data_query':
		/* dep: data input method */
		$item = db_fetch_row_prepared('SELECT data_input_id
			FROM snmp_query
			WHERE id = ?
			AND data_input_id > 0',
			array($id));

		if ((!empty($item)) && (!isset($dep_array['data_input_method'][$item['data_input_id']]))) {
			$dep_array = resolve_dependencies('data_input_method', $item['data_input_id'], $dep_array);
		}

		/* dep: graph template */
		$snmp_query_graph = db_fetch_assoc_prepared('SELECT graph_template_id
			FROM snmp_query_graph
			WHERE snmp_query_id = ?
			AND graph_template_id > 0
			GROUP BY graph_template_id',
			array($id));

		if (cacti_sizeof($snmp_query_graph) > 0) {
			foreach ($snmp_query_graph as $item) {
				if (!isset($dep_array['graph_template'][$item['graph_template_id']])) {
					$dep_array = resolve_dependencies('graph_template', $item['graph_template_id'], $dep_array);
				}
			}
		}

		break;
	case 'host_template':
		/* dep: graph template */
		$host_template_graph = db_fetch_assoc_prepared('SELECT graph_template_id
			FROM host_template_graph
			WHERE host_template_id = ?
			AND graph_template_id > 0
			GROUP BY graph_template_id',
			array($id));

		if (cacti_sizeof($host_template_graph) > 0) {
			foreach ($host_template_graph as $item) {
				if (!isset($dep_array['graph_template'][$item['graph_template_id']])) {
					$dep_array = resolve_dependencies('graph_template', $item['graph_template_id'], $dep_array);
				}
			}
		}

		/* dep: data query */
		$host_template_snmp_query = db_fetch_assoc_prepared('SELECT snmp_query_id
			FROM host_template_snmp_query
			WHERE host_template_id = ?
			AND snmp_query_id > 0
			GROUP BY snmp_query_id',
			array($id));

		if (cacti_sizeof($host_template_snmp_query) > 0) {
			foreach ($host_template_snmp_query as $item) {
				if (!isset($dep_array['data_query'][$item['snmp_query_id']])) {
					$dep_array = resolve_dependencies('data_query', $item['snmp_query_id'], $dep_array);
				}
			}
		}

		break;
	default:
		$param = array();
		$param['type']      = $type;
		$param['id']        = $id;
		$param['dep_array'] = $dep_array;

		$param = api_plugin_hook_function('resolve_dependencies', $param);

		$dep_array = $param['dep_array'];
		break;
	}

	/* update the dependency array */
	$dep_array[$type][$id] = $id;

	return $dep_array;
}

function get_item_xml($type, $id, $follow_deps) {
	$xml_text = '';
	$xml_indent = '';

	if ($follow_deps == true) {
		/* follow all dependencies recursively */
		$dep_array = resolve_dependencies($type, $id, array());
	} else {
		/* we are not supposed to resolve dependencies */
		$dep_array[$type][$id] = $id;
	}

	if (cacti_sizeof($dep_array)) {
		foreach ($dep_array as $dep_type => $dep_arr) {
			foreach ($dep_arr as $dep_id => $dep_id) {
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
				case 'vdef':
					$xml_text .= "\n" . vdef_to_xml($dep_id);
					break;
				case 'data_source_profile':
					$xml_text .= "\n" . data_source_profile_to_xml($dep_id);
					break;

				default:
					$param = array();
					$param['dep_id']   = $dep_id;
					$param['dep_type'] = $dep_type;
					$param['xml_text'] = $xml_text;

					$param = api_plugin_hook_function('export_action', $param);

					$xml_text = $param['xml_text'];
				}
			}
		}
	}

	$xml_array = explode("\n", $xml_text);

	for ($i=0; $i<cacti_count($xml_array); $i++) {
		$xml_indent .= "\t" . $xml_array[$i] . "\n";
	}

	$xml_text = '<cacti>' . $xml_indent . '</cacti>';

	return $xml_text;
}

function xml_character_encode($text) {
	return html_escape($text);
}

