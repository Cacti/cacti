<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Test whether a graph template input may select a graph item field.
 *
 * This is an application allowlist, not a database schema check. Structural
 * columns such as id, hash, local_graph_id, and graph_template_id must never
 * become user-controlled graph input fields even though they exist in the
 * graph_templates_item table.
 */
function graph_template_input_column_is_allowed($column_name) {
	static $allowed_columns = array(
		'graph_type_id'             => true,
		'task_item_id'              => true,
		'color_id'                  => true,
		'alpha'                     => true,
		'consolidation_function_id' => true,
		'cdef_id'                   => true,
		'vdef_id'                   => true,
		'shift'                     => true,
		'value'                     => true,
		'gprint_id'                 => true,
		'textalign'                 => true,
		'text_format'               => true,
		'hard_return'               => true,
		'line_width'                => true,
		'dashes'                    => true,
		'dash_offset'               => true,
		'sequence'                  => true,
	);

	return is_string($column_name) && isset($allowed_columns[$column_name]);
}

/**
 * Validate the storage shape of a graph template input value.
 */
function graph_template_input_value_is_allowed($column_name, $value) {
	if (!graph_template_input_column_is_allowed($column_name) || !is_scalar($value)) {
		return false;
	}

	$value = (string) $value;

	if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
		return false;
	}

	if (in_array($column_name, array('graph_type_id', 'task_item_id', 'consolidation_function_id', 'cdef_id', 'vdef_id', 'gprint_id'), true)) {
		return preg_match('/^\d{1,10}$/D', $value) === 1;
	}

	if ($column_name === 'color_id') {
		return $value === '' || (preg_match('/^\d{1,8}$/D', $value) === 1 && (int) $value <= 16777215);
	}

	if ($column_name === 'alpha') {
		return preg_match('/^[A-Fa-f0-9]{2}$/D', $value) === 1;
	}

	if (in_array($column_name, array('shift', 'hard_return'), true)) {
		return in_array($value, array('', '0', '1', 'on'), true);
	}

	if ($column_name === 'line_width') {
		return $value === '' || (strlen($value) <= 5 && preg_match('/^\d+(?:[.,]\d+)?$/D', $value) === 1);
	}

	if ($column_name === 'dash_offset') {
		return $value === '' || (preg_match('/^-?\d{1,7}$/D', $value) === 1 && (int) $value >= -8388608 && (int) $value <= 8388607);
	}

	if ($column_name === 'sequence') {
		return $value === '' || (preg_match('/^\d{1,8}$/D', $value) === 1 && (int) $value <= 16777215);
	}

	if ($column_name === 'dashes') {
		return strlen($value) <= 40 && ($value === '' || preg_match('/^[0-9.,[:space:]]+$/D', $value) === 1);
	}

	if ($column_name === 'textalign') {
		return strlen($value) <= 20 && ($value === '' || preg_match('/^[A-Za-z]+$/D', $value) === 1);
	}

	return strlen($value) <= 255;
}

/**
 * Verify that an input and all selected template items share one owner.
 */
function graph_template_input_relationships_are_valid($input_id, $graph_template_id, $graph_template_item_ids) {
	$input_id          = (int) $input_id;
	$graph_template_id = (int) $graph_template_id;

	if ($graph_template_id <= 0 || !is_array($graph_template_item_ids)) {
		return false;
	}

	if ($input_id > 0 && (int) db_fetch_cell_prepared('SELECT COUNT(*)
		FROM graph_template_input
		WHERE id = ?
		AND graph_template_id = ?', array($input_id, $graph_template_id)) !== 1) {
		return false;
	}

	$graph_template_item_ids = array_values(array_unique(array_map('intval', $graph_template_item_ids)));

	foreach ($graph_template_item_ids as $item_id) {
		if ($item_id <= 0) {
			return false;
		}
	}

	if (!cacti_sizeof($graph_template_item_ids)) {
		return true;
	}

	$placeholders = implode(',', array_fill(0, cacti_sizeof($graph_template_item_ids), '?'));

	return (int) db_fetch_cell_prepared('SELECT COUNT(*)
		FROM graph_templates_item
		WHERE graph_template_id = ?
		AND local_graph_id = 0
		AND id IN (' . $placeholders . ')',
		array_merge(array($graph_template_id), $graph_template_item_ids)) === cacti_sizeof($graph_template_item_ids);
}

/**
 * Validate graph-input references in one decoded XML graph template.
 */
function graph_template_input_xml_preflight($xml_array) {
	if (!is_array($xml_array) || (isset($xml_array['inputs']) && !is_array($xml_array['inputs']))) {
		return false;
	}

	$available_items = array();

	foreach (isset($xml_array['items']) && is_array($xml_array['items']) ? array_keys($xml_array['items']) : array() as $item_hash) {
		$parsed_hash = parse_xml_hash($item_hash);

		if ($parsed_hash === false) {
			return false;
		}

		$available_items[$parsed_hash['hash']] = true;
	}

	foreach (isset($xml_array['inputs']) ? $xml_array['inputs'] : array() as $input) {
		if (!is_array($input) || !isset($input['column_name'], $input['items']) || !is_string($input['items']) ||
			!graph_template_input_column_is_allowed(xml_character_decode($input['column_name']))) {
			return false;
		}

		foreach (array_filter(explode('|', $input['items']), 'strlen') as $item_hash) {
			$parsed_hash = parse_xml_hash($item_hash);

			if ($parsed_hash === false || !isset($available_items[$parsed_hash['hash']])) {
				return false;
			}
		}
	}

	return true;
}
