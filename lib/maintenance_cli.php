<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
 | Cacti is designed, written and maintained by the Cacti Group.           |
 |                                                                         |
 | Please read the included docs/CONTRIBUTING.md file for more information.|
 +-------------------------------------------------------------------------+
 */

/**
 * Validate one raw remove_graphs.php option before getopt() can discard it.
 *
 * @param string $parameter The raw command-line argument.
 * @param string $shortopts The getopt() short-option declaration.
 * @param array  $longopts  The getopt() long-option declarations.
 *
 * @return bool True only when the argument matches a declared option and its
 *              required value shape.
 */
function cacti_remove_graphs_parameter_is_valid($parameter, $shortopts, $longopts) {
	if (strpos($parameter, '-') === 0 && strpos($parameter, '--') !== 0) {
		$letters = substr($parameter, 1);
		$allowed = str_replace(':', '', $shortopts);

		return $letters !== '' && strspn($letters, $allowed) === strlen($letters);
	}

	if (strpos($parameter, '--') !== 0) {
		return false;
	}

	$valid_longopts = array();

	foreach($longopts as $option) {
		$valid_longopts[rtrim($option, ':')] = substr($option, -1) === ':';
	}

	$parts      = explode('=', substr($parameter, 2), 2);
	$name       = $parts[0];
	$has_equals = count($parts) === 2;
	$has_value  = $has_equals && $parts[1] !== '';

	if (!array_key_exists($name, $valid_longopts)) {
		return false;
	}

	return ($valid_longopts[$name] && $has_value) || (!$valid_longopts[$name] && !$has_equals);
}

/**
 * Return the validation error for a remove_graphs.php regular expression.
 *
 * @param string $regex The expression supplied by the operator.
 *
 * @return string|false False when valid, otherwise a printable error message.
 */
function cacti_remove_graphs_regex_error($regex) {
	$validation = validate_is_rlike_regex($regex);

	if ($validation === true) {
		return false;
	}

	return is_string($validation) && $validation !== '' ? $validation : 'Invalid regular expression.';
}

/**
 * Determine whether getopt() observed the remove_graphs.php quiet flag.
 *
 * @param array $options Parsed getopt() options.
 *
 * @return bool True when the quiet option key is present.
 */
function cacti_remove_graphs_quiet_enabled($options) {
	return array_key_exists('quiet', $options);
}

/**
 * Build the prepared host/filter predicate for graph-name reapplication.
 *
 * @param string $host_id A single id, comma-delimited ids, zero, or "all".
 * @param string $filter  Optional graph name/title filter.
 *
 * @return array|false A SQL fragment and parameter list, or false for an
 *                     invalid or missing host selector.
 */
function cacti_reapply_names_where($host_id, $filter) {
	$host_id = trim($host_id);
	$params = array();
	$where  = '';

	if ($filter != '') {
		$where  = 'AND (graph_templates_graph.title_cache LIKE ? OR graph_templates.name LIKE ?)';
		$params = array('%' . $filter . '%', '%' . $filter . '%');
	}

	if (strtolower($host_id) == 'all') {
		return array($where, $params);
	}

	if (substr_count($host_id, ',')) {
		$host_ids = array();

		foreach(explode(',', $host_id) as $host) {
			$host = trim($host);

			if (!ctype_digit($host)) {
				return false;
			}

			$host_ids[] = (int) $host;
		}

		$where  .= ' AND graph_local.host_id IN (' . implode(',', array_fill(0, count($host_ids), '?')) . ')';
		$params  = array_merge($params, $host_ids);

		return array($where, $params);
	}

	if (ctype_digit($host_id)) {
		$where   .= ' AND graph_local.host_id=?';
		$params[] = (int) $host_id;

		return array($where, $params);
	}

	return false;
}
