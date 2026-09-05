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

function cacti_remove_graphs_regex_error($regex) {
	$validation = validate_is_regex($regex);

	return $validation === true ? false : $validation;
}

function cacti_remove_graphs_quiet_enabled($options) {
	return array_key_exists('quiet', $options);
}

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

	if ($host_id == '0') {
		return array($where . ' AND graph_local.host_id=0', $params);
	}

	if (ctype_digit($host_id) && (int) $host_id > 0) {
		$where   .= ' AND graph_local.host_id=?';
		$params[] = (int) $host_id;

		return array($where, $params);
	}

	return false;
}
