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
function graph_template_input_column_is_allowed(mixed $column_name) : bool {
	static $allowed_columns = [
		'graph_type_id'             => true,
		'task_item_id'              => true,
		'color_id'                  => true,
		'alpha'                     => true,
		'color2_id'                 => true,
		'alpha2'                    => true,
		'gradheight'                => true,
		'consolidation_function_id' => true,
		'cdef_id'                   => true,
		'vdef_id'                   => true,
		'shift'                     => true,
		'value'                     => true,
		'gprint_id'                 => true,
		'textalign'                 => true,
		'text_format'               => true,
		'legend'                    => true,
		'hard_return'               => true,
		'line_width'                => true,
		'dashes'                    => true,
		'dash_offset'               => true,
		'sequence'                  => true,
	];

	return is_string($column_name) && isset($allowed_columns[$column_name]);
}

/**
 * Validate the storage shape of a graph template input value.
 */
function graph_template_input_value_is_allowed(mixed $column_name, mixed $value) : bool {
	if (!graph_template_input_column_is_allowed($column_name) || !is_scalar($value)) {
		return false;
	}

	$value = (string) $value;

	if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
		return false;
	}

	if (in_array($column_name, ['graph_type_id', 'task_item_id', 'consolidation_function_id', 'cdef_id', 'vdef_id', 'gprint_id'], true)) {
		return preg_match('/^\d{1,10}$/D', $value) === 1;
	}

	if (in_array($column_name, ['color_id', 'color2_id'], true)) {
		return preg_match('/^(?:0|[A-Fa-f0-9]{6})$/D', $value) === 1;
	}

	if (in_array($column_name, ['alpha', 'alpha2'], true)) {
		return preg_match('/^[A-Fa-f0-9]{2}$/D', $value) === 1;
	}

	if (in_array($column_name, ['shift', 'hard_return'], true)) {
		return in_array($value, ['', '0', '1', 'on'], true);
	}

	if ($column_name === 'line_width') {
		return $value === '' || (strlen($value) <= 5 && preg_match('/^\d+(?:\.\d+)?$/D', $value) === 1);
	}

	if ($column_name === 'dash_offset') {
		return $value === '' || (strlen($value) <= 4 && preg_match('/^-?\d+(?:\.\d+)?$/D', $value) === 1);
	}

	if ($column_name === 'gradheight') {
		return $value === '' || (strlen($value) <= 5 && preg_match('/^-?\d+(?:\.\d+)?$/D', $value) === 1);
	}

	if ($column_name === 'legend') {
		return strlen($value) <= 30;
	}

	if ($column_name === 'sequence') {
		return $value === '' || (strlen($value) <= 4 && preg_match('/^\d+$/D', $value) === 1);
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
 *
 * @param array<int|string> $graph_template_item_ids
 */
function graph_template_input_relationships_are_valid(int $input_id, int $graph_template_id, array $graph_template_item_ids) : bool {
	if ($graph_template_id <= 0) {
		return false;
	}

	if ($input_id > 0 && (int) db_fetch_cell_prepared('SELECT COUNT(*)
		FROM graph_template_input
		WHERE id = ?
		AND graph_template_id = ?', [$input_id, $graph_template_id]) !== 1) {
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
		array_merge([$graph_template_id], $graph_template_item_ids)) === cacti_sizeof($graph_template_item_ids);
}

/**
 * Validate graph-input references in one decoded XML graph template.
 *
 * @param array<string, mixed> $xml_array
 */
function graph_template_input_xml_preflight(array $xml_array) : bool {
	if (isset($xml_array['inputs']) && !is_array($xml_array['inputs'])) {
		return false;
	}

	$available_items = [];

	foreach (isset($xml_array['items']) && is_array($xml_array['items']) ? array_keys($xml_array['items']) : [] as $item_hash) {
		$parsed_hash = parse_xml_hash($item_hash);

		if ($parsed_hash === false) {
			return false;
		}

		$available_items[$parsed_hash['hash']] = true;
	}

	foreach ($xml_array['inputs'] ?? [] as $input) {
		if (!is_array($input) || !isset($input['column_name'], $input['items']) || !is_string($input['items']) ||
			!graph_template_input_column_is_allowed(xml_character_decode($input['column_name']))) {
			return false;
		}

		foreach (array_filter(explode('|', $input['items']), static fn (string $item_hash) : bool => $item_hash !== '') as $item_hash) {
			$parsed_hash = parse_xml_hash($item_hash);

			if ($parsed_hash === false || !isset($available_items[$parsed_hash['hash']])) {
				return false;
			}
		}
	}

	return true;
}
