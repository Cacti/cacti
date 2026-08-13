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
