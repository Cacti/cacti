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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Pushes out the "custom data" associated with a data template to all of its children. this
 * includes all fields inherited from the host and the data template. Used as part of Cacti's lib
 * functionality.
 *
 * @param int $data_template_id The id of the data template to push out values for.
 *
 * @return bool Bool.
 */
function push_out_data_source_custom_data($data_template_id) {
	/* valid data template id? */
	if (empty($data_template_id)) {
		return 0;
	}

	/* get data_input_id from template */
	$data_template_data = db_fetch_row_prepared('SELECT id, data_input_id
		FROM data_template_data
		WHERE data_template_id = ?
		AND local_data_id = 0',
		array($data_template_id));

	/* must be a data template */
	if (empty($data_template_data['data_input_id'])) {
		return 0;
	}

	/* get a list of data sources using this template */
	$data_sources = db_fetch_assoc_prepared('SELECT data_template_data.id
		FROM data_template_data
		WHERE data_template_id = ?
		AND local_data_id > 0', array($data_template_id));

	/* get data query custom index fields to ignore */
	$data_query_field_index_ids = data_input_field_always_checked(0, true);
	if (cacti_sizeof($data_query_field_index_ids)) {
		$exclude_sql_where = ' AND did.data_input_field_id NOT IN (' . implode(',', $data_query_field_index_ids) . ')';
	} else {
		$exclude_sql_where = '';
	}

	/**
	 * pull out all custom templated 'input' values from the template itself
	 * templated items are selected by querying t_value = '' OR t_value = NULL
	 * or that are not Data Query related index values
	 */
	$template_input_fields = array_rekey(
		db_fetch_assoc_prepared("SELECT dif.id,
			dif.type_code, did.value, did.t_value
			FROM data_input_fields AS dif
			INNER JOIN data_input_data AS did
			ON dif.id = did.data_input_field_id
			INNER JOIN data_template_data AS dtd
			ON dtd.id = did.data_template_data_id
			WHERE (dif.input_output = 'in')
			AND (did.t_value = '' OR did.t_value IS NULL)
			$exclude_sql_where
			AND (did.data_template_data_id = ?)
			AND (dtd.local_data_template_data_id = 0)",
			array($data_template_data['id'])),
		'id', array('type_code', 'value', 't_value')
	);

	if (!cacti_sizeof($template_input_fields)) {
		return;
	}

	// The parent's flags own inheritance. A child may be missing a row or still
	// carry the override flag from an earlier version of the template.
	$field_ids = implode(',', array_map('intval', array_keys($template_input_fields)));
	foreach (array_chunk($data_sources, 1000) as $sources) {
		$source_ids = implode(',', array_map('intval', array_column($sources, 'id')));
		db_execute_prepared("INSERT INTO data_input_data
			(data_input_field_id, data_template_data_id, t_value, value)
			SELECT parent.data_input_field_id, child.id, '', parent.value
			FROM data_template_data AS child
			CROSS JOIN data_input_data AS parent
			WHERE child.id IN ($source_ids)
			AND parent.data_template_data_id = ?
			AND parent.data_input_field_id IN ($field_ids)
			ON DUPLICATE KEY UPDATE t_value = VALUES(t_value), value = VALUES(value)",
			array($data_template_data['id']));
	}
}

/**
 * Push out changed data template fields to related data sources. Used as part of Cacti's lib
 * functionality.
 *
 * Core no longer calls this function, because push_out_data_source_custom_data() now propagates
 * the fields itself. It is kept unchanged because released 1.2 versions exported it and plugins
 * may still call it.
 *
 * @param string $did_vals The data input data fields.
 * @param string $ds_in_str All data sources, formatted as SQL 'IN' clause.
 *
 * @return void No value is returned.
 */
function push_out_data_source_templates($did_vals, $ds_in_str) {
	/* update all templated input fields */
	if ($did_vals != '') {
		db_execute("INSERT INTO data_input_data
			(data_input_field_id, data_template_data_id, value)
			VALUES $did_vals
			ON DUPLICATE KEY UPDATE value=VALUES(value)");
	}
}

/**
 * Pushes out templated data template item fields to all matching children. Used as part of
 * Cacti's lib functionality.
 *
 * @param int $data_template_rrd_id The id of the data template item to push out values for.
 *
 * @return bool Bool.
 */
function push_out_data_source_item($data_template_rrd_id) {
	global $struct_data_source_item;

	/* get information about this data template */
	$data_template_rrd = db_fetch_row_prepared('SELECT * FROM data_template_rrd WHERE id = ?', array($data_template_rrd_id));

	/* must be a data template */
	if (empty($data_template_rrd['data_template_id'])) {
		return 0;
	}

	/* loop through each data source column name (from the above array) */
	foreach ($struct_data_source_item as $field_name => $field_array) {
		/* are we allowed to push out the column? */
		if (((empty($data_template_rrd['t_' . $field_name])) || (preg_match('/FORCE:/', $field_name))) && ((isset($data_template_rrd['t_' . $field_name])) && (isset($data_template_rrd[$field_name])))) {
			db_execute_prepared("UPDATE data_template_rrd
				SET $field_name = ?
				WHERE local_data_template_rrd_id = ?",
				array($data_template_rrd[$field_name], $data_template_rrd['id']));
		}
	}
}

/**
 * Pushes out templated data template fields to all matching children. Used as part of Cacti's lib
 * functionality.
 *
 * @param int $data_template_data_id The id of the data template to push out values for.
 *
 * @return bool Bool.
 */
function push_out_data_source($data_template_data_id) {
	global $struct_data_source;

	/* get information about this data template */
	$data_template_data = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE id = ?', array($data_template_data_id));

	/* must be a data template */
	if (empty($data_template_data['data_template_id'])) { return 0; }

	/* loop through each data source column name (from the above array) */
	foreach ($struct_data_source as $field_name => $field_array) {
		/* are we allowed to push out the column? */
		if (((empty($data_template_data['t_' . $field_name])) || (preg_match('/FORCE:/', $field_name))) && ((isset($data_template_data['t_' . $field_name])) && (isset($data_template_data[$field_name])))) {
			db_execute_prepared("UPDATE data_template_data
				SET $field_name = ?
				WHERE local_data_template_data_id=?",
				array($data_template_data[$field_name], $data_template_data['id']));

			/* update the title cache */
			if ($field_name == 'name') {
				update_data_source_title_cache_from_template($data_template_data['data_template_id']);
			}
		}
	}
}

/**
 * Check for data query custom fields This function will tell cacti which data input fields must
 * always carry the data source information since it's key to linking data query information into
 * the data source. Used as part of Cacti's lib functionality.
 *
 * @param int $data_input_field_id Data_input_field_id - The data input field.
 * @param bool $return_ids Return_ids - Return an array of special ids.
 *
 * @return boolean True or false.
 */
function data_input_field_always_checked($data_input_field_id = 0, $return_ids = false) {
	static $always_checked = array();

	$always_checked_hashes = array(
		'6027a919c7c7731fbe095b6f53ab127b', // SNMP Query - Index Type
		'cbbe5c1ddfb264a6e5d509ce1c78c95f', // SNMP Query - Index Value
		'e6deda7be0f391399c5130e7c4a48b28', // SNMP Query - Output Type ID
		'172b4b0eacee4948c6479f587b62e512', // Script Server Data Query - Index Type
		'30fb5d5bcf3d66bb5abe88596f357c26', // Script Server Data Query - Index Value
		'31112c85ae4ff821d3b288336288818c', // Script Server Data Query - Output Type ID
		'd39556ecad6166701bfb0e28c5a11108', // Script Query - Index Type
		'3b7caa46eb809fc238de6ef18b6e10d5', // Script Query - Indev Value
		'74af2e42dc12956c4817c2ef5d9983f9', // Script Query - Output Type ID
	);

	if (!cacti_sizeof($always_checked)) {
		$always_checked = array_rekey(
			db_fetch_assoc_prepared('SELECT id
				FROM data_input_fields
				WHERE hash IN (?, ?, ?, ?, ?, ?, ?, ?, ?)
				OR (type_code IN("index_type", "index_value", "output_type"))',
				$always_checked_hashes),
			'id', 'id'
		);
	}

	if ($return_ids) {
		return $always_checked;
	} elseif (isset($always_checked[$data_input_field_id])) {
		return true;
	} else {
		return false;
	}
}

/**
 * Changes the data template for a particular data source to $data_template_id. Used as part of
 * Cacti's lib functionality.
 *
 * @param int $local_data_id The id of the data source to change the data template for.
 * @param int $data_template_id Id the of the data template to change to. specify '0' for no.
 * @param array $profile A structure of data source profile attributes data template.
 *
 * @return bool True on success, false if the template is incompatible or a write fails.
 */
function change_data_template($local_data_id, $data_template_id, $profile = array()) {
	global $struct_data_source, $struct_data_source_item;
	global $database_sessions, $database_hostname, $database_port, $database_default, $database_last_error;

	$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
	$nested = $db_conn->inTransaction();
	$complete = false;
	$previous_error = isset($database_last_error) ? $database_last_error : '';
	$database_last_error = '';
	if ($nested ? !db_execute('SAVEPOINT change_data_template') : !db_begin_transaction($db_conn)) {
		return false;
	}

	try {
		/* get data about the template and the data source */
		$data = db_fetch_row_prepared('SELECT *
			FROM data_template_data
			WHERE local_data_id = ?' ,
			array($local_data_id));

		if ($data_template_id == 0) {
			$template_data = $data;
		} else {
			$template_data = db_fetch_row_prepared('SELECT *
				FROM data_template_data
				WHERE local_data_id = 0
				AND data_template_id = ?',
				array($data_template_id));
		}

		$data_rrds_list = db_fetch_assoc_prepared('SELECT *
			FROM data_template_rrd
			WHERE local_data_id = ?',
			array($local_data_id));

		if ($data_template_id == 0) {
			$template_rrds_list = $data_rrds_list;
		} else {
			$template_rrds_list = db_fetch_assoc_prepared('SELECT *
				FROM data_template_rrd
				WHERE local_data_id = 0
				AND data_template_id = ?',
				array($data_template_id));
		}

		if (!cacti_sizeof($template_data)) {
			return false;
		}

		$template_rrds = array();
		foreach ($template_rrds_list as $item) {
			if (isset($template_rrds[$item['data_source_name']])) {
				return false;
			}
			$template_rrds[$item['data_source_name']] = $item;
		}
		if (cacti_sizeof($data_rrds_list)) {
			$names = array_column($data_rrds_list, 'data_source_name');
			if (count(array_unique($names)) != count($names) ||
				count($names) != count($template_rrds) || array_diff($names, array_keys($template_rrds))) {
				return false;
			}
		}

		if (!db_execute_prepared('UPDATE data_local SET data_template_id = ? WHERE id = ?',
			array($data_template_id, $local_data_id))) {
			return false;
		}

		/* determine if we are here for the first time, or coming back */
		$exists = db_fetch_cell_prepared('SELECT local_data_template_data_id
			FROM data_template_data
			WHERE local_data_id = ?',
			array($local_data_id));

		if (empty($exists)) {
			$new_save = true;
		} else {
			$new_save = false;
		}

		/* some basic field values that ALL data sources should have */
		$save['id']                          = (isset($data['id']) ? $data['id'] : 0);
		$save['local_data_template_data_id'] = $data_template_id == 0 ? 0 : $template_data['id'];
		$save['local_data_id']               = $local_data_id;
		$save['data_template_id']            = $data_template_id;

		/* loop through the 'templated field names' to find to the rest... */
		foreach ($struct_data_source as $field_name => $field_array) {
			/* handle the data source profile */
			if ($field_name == 'rrd_step' && cacti_sizeof($profile)) {
				$save[$field_name] = $profile['step'];
			} elseif ((isset($data[$field_name])) || (isset($template_data[$field_name]))) {
				if ((!empty($template_data['t_' . $field_name])) && ($new_save == false)) {
					$save[$field_name] = $data[$field_name];
				} else {
					$save[$field_name] = $template_data[$field_name];
				}
			}
		}

		/* these fields should never be overwritten by the template */
		$save['data_source_path'] = (isset($data['data_source_path']) ? $data['data_source_path']:'');

		$data_template_data_id = sql_save($save, 'data_template_data');
		if ($data_template_data_id === false) {
			return false;
		}

		if (cacti_sizeof($data_rrds_list)) {
			foreach ($data_rrds_list as $item) {
				$template_rrd = $template_rrds[$item['data_source_name']];
				$save = array(
					'id' => $item['id'],
					'data_template_id' => $data_template_id,
					'local_data_template_rrd_id' => $data_template_id == 0 ? 0 : $template_rrd['id']
				);
				foreach ($struct_data_source_item as $field_name => $field_array) {
					if ($field_name == 'rrd_heartbeat' && cacti_sizeof($profile)) {
						$save[$field_name] = $profile['heartbeat'];
					} elseif (isset($template_rrd[$field_name]) && empty($template_rrd['t_' . $field_name])) {
						$save[$field_name] = $template_rrd[$field_name];
					}
				}
				if (sql_save($save, 'data_template_rrd') === false) {
					return false;
				}
			}
		} else {
			/* this data source does NOT have 'child' items; loop through each item in the template
			and write it exactly to each item */
			if (cacti_sizeof($template_rrds_list)) {
				foreach ($template_rrds_list as $template_rrd) {
					unset($save);

					$save['id'] = 0;
					$save['local_data_template_rrd_id'] = $template_rrd['id'];
					$save['local_data_id']              = $local_data_id;
					$save['data_template_id']           = $template_rrd['data_template_id'];

					foreach ($struct_data_source_item as $field_name => $field_array) {
						/* handle the data source profile */
						if ($field_name == 'rrd_heartbeat' && cacti_sizeof($profile)) {
							$save[$field_name] = $profile['heartbeat'];
						} else {
							$save[$field_name] = $template_rrd[$field_name];
						}
					}

					if (sql_save($save, 'data_template_rrd') === false) {
						return false;
					}
				}
			}
		}

		/* make sure to copy down script data (data_input_data) as well */
		$data_input_data = db_fetch_assoc_prepared('SELECT data_input_field_id, t_value, value
			FROM data_input_data
			WHERE data_template_data_id = ?',
			array($template_data['id']));

		/* this section is before most everything else so we can determine if this is a new save, by checking
		the status of the 'local_data_template_data_id' column */
		if (cacti_sizeof($data_input_data)) {
			foreach ($data_input_data as $item) {
				/**
				 * always propagate on a new save, only propagate templated fields thereafter
				 * noting that always checked should not be propagated after the initial save.
				 */
				if ($new_save == true || (empty($item['t_value']) && !data_input_field_always_checked($item['data_input_field_id']))) {
					if (!db_execute_prepared('REPLACE INTO data_input_data
						(data_input_field_id, data_template_data_id, t_value, value)
						VALUES (?, ?, ?, ?)',
						array($item['data_input_field_id'], $data_template_data_id, $item['t_value'], $item['value']))) {
						return false;
					}
				}
			}
		}

		// sql_save can return an id after a failed write on this branch.
		if ($database_last_error != '') {
			return false;
		}
		$complete = $nested ? db_execute('RELEASE SAVEPOINT change_data_template') : db_commit_transaction($db_conn);
		return $complete;
	} catch (Throwable $error) {
		cacti_log('ERROR: change_data_template failed: ' . $error->getMessage(), false, 'WEBUI');
		return false;
	} finally {
		if ($database_last_error == '') {
			$database_last_error = $previous_error;
		}
		if (!$complete && $db_conn->inTransaction()) {
			if ($nested) {
				db_execute('ROLLBACK TO SAVEPOINT change_data_template');
				db_execute('RELEASE SAVEPOINT change_data_template');
			} else {
				db_rollback_transaction($db_conn);
			}
		}
	}
}

/**
 * Pushes out templated graph template fields to all matching children. Used as part of Cacti's
 * lib functionality.
 *
 * @param int $graph_template_graph_id The id of the graph template to push out values for.
 * @param bool $push_title (boolean) if the pattern for the title changes, push out those changes
 *   to the various graphs.
 *
 * @return bool Bool.
 */
function push_out_graph($graph_template_graph_id, $push_title = true) {
	global $struct_graph;

	/* get information about this graph template */
	$graph_template_graph = db_fetch_row_prepared('SELECT *
		FROM graph_templates_graph
		WHERE id = ?', array($graph_template_graph_id));

	/* must be a graph template */
	if ($graph_template_graph['graph_template_id'] == 0) {
		return 0;
	}

	/* don't push out if you have no graphs */
	$has_graphs = db_fetch_cell_prepared('SELECT COUNT(id)
		FROM graph_local
		WHERE graph_template_id = ?',
		array($graph_template_graph['graph_template_id']));

	if ($has_graphs == 0) {
		return 0;
	}

	/* loop through each graph column name (from the above array) */
	foreach ($struct_graph as $field_name => $field_array) {
		/* are we allowed to push out the column? */
		if (isset($graph_template_graph['t_' . $field_name]) && empty($graph_template_graph['t_' . $field_name])) {
			if ($field_array['method'] != 'spacer') {
				db_execute_prepared("UPDATE graph_templates_graph
					SET $field_name = ?
					WHERE local_graph_template_graph_id = ?", array($graph_template_graph[$field_name], $graph_template_graph['id']));
			}

			/* update the title cache */
			if ($field_name == 'title' && $push_title) {
				update_graph_title_cache_from_template($graph_template_graph['graph_template_id']);
			}
		}
	}
}

/**
 * Pushes out the value of a graph input to a single child item. this function differs from other
 * push_out_* functions in that it does not push out the value of this element to all attached
 * children. instead, it obtains the current value of the graph input based on other graph items
 * and pushes out the 'active' value. Used as part of Cacti's lib functionality.
 *
 * @param int $graph_template_input_id The id of the graph input to push out values for.
 * @param int $graph_template_item_id The id the graph template item to push out.
 * @param array $session_members When looking for the 'active' value of the graph input, ignore
 *   these graph template items. typically you want to ignore all items that were just selected and
 *   have yet to be saved to the database. this is because these items most likely contain incorrect
 *   data.
 *
 * @return void No value is returned.
 */
function push_out_graph_input($graph_template_input_id, $graph_template_item_id, $session_members) {
	$graph_input = db_fetch_row_prepared('SELECT graph_template_id, column_name
		FROM graph_template_input
		WHERE id = ?', array($graph_template_input_id));

	/* column_name is a schema identifier interpolated into the SELECT and UPDATE
	 * statements below. Confine it to an editable graph-item field before use. */
	if (!cacti_sizeof($graph_input)) {
		return;
	}

	if (!graph_template_input_column_is_allowed($graph_input['column_name'])) {
		cacti_log('ERROR: push_out_graph_input() refused an invalid graph input field', false, 'SECURITY');

		return;
	}

	$column_name       = $graph_input['column_name'];
	$graph_template_id = (int) $graph_input['graph_template_id'];

	$graph_input_items = db_fetch_assoc_prepared('SELECT graph_template_item_id
		FROM graph_template_input_defs
		WHERE graph_template_input_id = ?', array($graph_template_input_id));

	$i = 0;
	if (cacti_sizeof($graph_input_items)) {
		foreach ($graph_input_items as $item) {
			$include_items[$i] = $item['graph_template_item_id'];
			$i++;
		}
	}

	/* we always want to make sure to stay within the same graph item input, so make a list of each
	item included in this input to be included in the sql query */
	if (isset($include_items)) {
		$sql_include_items = 'AND ' . array_to_sql_or($include_items, 'local_graph_template_item_id');
	} else {
		$sql_include_items = 'AND 0=1';
	}

	if (cacti_sizeof($session_members) == 0) {
		$values_to_apply = db_fetch_assoc('SELECT local_graph_id,' . $column_name . '
			FROM graph_templates_item
			WHERE graph_template_id=' . $graph_template_id . " $sql_include_items
			AND local_graph_id>0
			GROUP BY local_graph_id");
	} else {
		$i = 0;
		foreach ($session_members as $item_id => $item_id) {
			$new_session_members[$i] = $item_id;
			$i++;
		}

		$values_to_apply = db_fetch_assoc('SELECT local_graph_id,' . $column_name . '
			FROM graph_templates_item
			WHERE graph_template_id=' . $graph_template_id . '
			AND local_graph_id>0
			AND !(' . array_to_sql_or($new_session_members, 'local_graph_template_item_id') . ") $sql_include_items GROUP BY local_graph_id");
	}

	if (cacti_sizeof($values_to_apply)) {
		foreach ($values_to_apply as $value) {
			if (!graph_template_input_value_is_allowed($column_name, $value[$column_name])) {
				cacti_log('ERROR: push_out_graph_input() refused an invalid graph input value', false, 'SECURITY');

				return;
			}
		}

		foreach ($values_to_apply as $value) {
			/* this is just an extra check that i threw in to prevent users' graphs from getting really messed up */
			if (!(($column_name == 'task_item_id') && (empty($value[$column_name])))) {
				db_execute_prepared('UPDATE graph_templates_item
					SET ' . $column_name . ' = ?
					WHERE local_graph_id = ?
					AND local_graph_template_item_id = ?',
					array($value[$column_name], $value['local_graph_id'], $graph_template_item_id));
			}
		}
	}
}

/**
 * Pushes out templated graph template item fields to all matching children. if the graph template
 * item is part of a graph input, the field will not be pushed out. Used as part of Cacti's lib
 * functionality.
 *
 * @param int $graph_template_item_id (int) the id of the graph template item to push out values
 *   for.
 * @param bool $task_item_changed (boolean) Add additional steps if the task items changed.
 *
 * @return bool Bool.
 */
function push_out_graph_item($graph_template_item_id, $task_item_changed = false) {
	global $struct_graph_item;

	/* get information about this graph template */
	$graph_template_item = db_fetch_row_prepared('SELECT *
		FROM graph_templates_item
		WHERE id = ?',
		array($graph_template_item_id));

	/* must be a graph template */
	if ($graph_template_item['graph_template_id'] == 0) {
		return 0;
	}

	$graph_template_id = $graph_template_item['graph_template_id'];

	/* don't push out if you have no graphs */
	$has_graphs = db_fetch_cell_prepared('SELECT COUNT(id)
		FROM graph_local
		WHERE graph_template_id = ?',
		array($graph_template_item['graph_template_id']));

	if ($has_graphs == 0) {
		return 0;
	}

	update_graph_template_items($graph_template_id, $graph_template_item_id, $task_item_changed);
}

/**
 * Updates the graph data source output type. Used as part of Cacti's lib functionality.
 *
 * @param int $local_graph_id The local graph ID.
 * @param int $output_type_id The output type ID.
 *
 * @return void No value is returned.
 */
function update_graph_data_source_output_type($local_graph_id, $output_type_id) {
	$graph_local = db_fetch_row_prepared('SELECT *
		FROM graph_local
		WHERE id = ?',
		array($local_graph_id));

	$task_items = db_fetch_cell_prepared('SELECT GROUP_CONCAT(DISTINCT task_item_id) AS items
		FROM graph_templates_item
		WHERE local_graph_id = ?',
		array($local_graph_id));

	if ($task_items != '') {
		$local_data_id = db_fetch_cell("SELECT DISTINCT local_data_id
			FROM data_template_rrd
			WHERE id IN($task_items)");
	} else {
		$local_data_id = 0;
	}

	if ($local_data_id > 0) {
		$data = db_fetch_row_prepared('SELECT id, data_input_id, data_template_id, name, local_data_id
			FROM data_template_data
			WHERE local_data_id = ?',
			array($local_data_id));

		/* get each INPUT field for this data input source */
		$output_type_field_id = db_fetch_cell_prepared('SELECT id
			FROM data_input_fields
			WHERE data_input_id = ?
			AND input_output = "in"
			AND type_code="output_type"
			ORDER BY sequence',
			array($data['data_input_id']));

		$snmp_query_graph_id = db_fetch_cell_prepared('SELECT value
			FROM data_input_data
			WHERE data_template_data_id = ?
			AND data_input_field_id = ?',
			array($data['id'], $output_type_field_id));

		if ($snmp_query_graph_id != $output_type_id && $output_type_id > 0) {
			db_execute_prepared('UPDATE data_input_data
				SET value = ?
				WHERE data_template_data_id = ?
				AND data_input_field_id = ?',
				array($output_type_id, $data['id'], $output_type_field_id));

			db_execute_prepared('UPDATE graph_local
				SET snmp_query_graph_id = ?
				WHERE graph_template_id = ?
				AND id = ?',
				array($output_type_id, $graph_local['graph_template_id'], $local_graph_id));

			push_out_host($graph_local['host_id'], $local_data_id);
		}
	}
}

/**
 * Graph_template_has_override This function determines if a Graph Template has fields that allow
 * changing of their values at create time. Data that is considered for override includes data in:
 * Data Input Fields Data Template Data Fields Graph Template Fields. Used as part of Cacti's lib
 * functionality.
 *
 * @param int $graph_template_id The graph template ID.
 *
 * @return boolean Override allowed.
 */
function graph_template_has_override($graph_template_id) {
	$graph_template = db_fetch_row_prepared('SELECT *
		FROM graph_templates_graph
		WHERE graph_template_id = ?
		AND local_graph_id = 0',
		array($graph_template_id));

	// Check the Graph Template first for adherence
	if (cacti_sizeof($graph_template)) {
		foreach($graph_template as $field => $value) {
			if (substr($field, 0, 2) == 't_') {
				if ($value == 'on') {
					return true;
				}
			}
		}
	}

	// Next let's check it's source Data Templates
	$data_templates = db_fetch_assoc_prepared('SELECT DISTINCT dtd.*
		FROM data_template_data AS dtd
		INNER JOIN data_template_rrd AS dtr
		ON dtd.data_template_id = dtr.data_template_id
		INNER JOIN graph_templates_item AS gti
		ON dtr.id = gti.task_item_id
		WHERE gti.graph_template_id = ?
		AND dtd.local_data_id = 0
		AND dtr.local_data_id = 0
		AND gti.hash != ""',
		array($graph_template_id));

	if (cacti_sizeof($data_templates)) {
		foreach($data_templates as $dtd) {
			foreach($dtd as $field => $value) {
				if (substr($field, 0, 2) == 't_') {
					if ($value == 'on') {
						return true;
					}
				}
			}

			// Lastly check the data input fields
			$input_fields = db_fetch_assoc_prepared('SELECT dif.data_input_id, did.t_value, did.value, dtd.name
				FROM data_template_data AS dtd
				INNER JOIN data_template AS dt
				ON dt.id = dtd.data_template_id
			INNER JOIN data_input_data AS did
			ON did.data_template_data_id = dtd.id
				INNER JOIN data_input_fields AS dif
				ON dif.id = did.data_input_field_id
				WHERE dt.hash != ""
				AND dtd.id = ?
				AND dtd.local_data_id = 0
				AND dif.input_output = "in"
			AND did.t_value = "on"',
				array($dtd['id']));

			if (cacti_sizeof($input_fields)) {
				return true;
			}
		}
	}

	$graph_template_inputs = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM graph_template_input
		WHERE graph_template_id = ?
		AND column_name != "task_item_id"',
		array($graph_template_id));

	if ($graph_template_inputs > 0) {
		return true;
	}

	return false;
}

/**
 * Parses the graph template ID. Used as part of Cacti's lib functionality.
 *
 * @param mixed $value The value.
 *
 * @return mixed The result of the operation, or false on failure.
 */
function parse_graph_template_id($value) {
	if (strpos($value, '_') !== false) {
		$template_parts = explode('_', $value);
		if (is_numeric($template_parts[0]) && is_numeric($template_parts[1])) {
			return array('graph_template_id' => $template_parts[0], 'output_type_id' => $template_parts[1]);
		} else {
			cacti_log('ERROR: Unable to parse graph_template_id with value ' . $value, false, 'WEBUI');
			exit;
		}
	} else {
		return array('graph_template_id' => $value);
	}
}

/**
 * Handles the resequence graphs simple. Used as part of Cacti's lib functionality.
 *
 * @param int $graph_template_id The graph template ID.
 *
 * @return void No value is returned.
 */
function resequence_graphs_simple($graph_template_id) {
	$template_items = db_fetch_assoc_prepared('SELECT *
		FROM graph_templates_item
		WHERE graph_template_id = ?
		AND local_graph_id = 0
		ORDER BY id',
		array($graph_template_id));

	if (cacti_sizeof($template_items)) {
		foreach($template_items as $item) {
			db_execute_prepared('UPDATE graph_templates_item
				SET sequence = ?
				WHERE local_graph_template_item_id = ?',
				array($item['sequence'], $item['id']));
		}
	}
}

/**
 * Handles the resequence graphs. Used as part of Cacti's lib functionality.
 *
 * @param int $graph_template_id The graph template ID.
 * @param int $local_graph_id The local graph ID.
 * @param bool $force The force.
 *
 * @return bool True on success, false otherwise.
 */
function resequence_graphs($graph_template_id, $local_graph_id = 0, $force = false) {
	$template_items = array_rekey(db_fetch_assoc_prepared('SELECT id, sequence
		FROM graph_templates_item WHERE graph_template_id = ? AND local_graph_id = 0',
		array($graph_template_id)), 'id', 'sequence');
	$params = array($graph_template_id);
	$where = '';
	if ($local_graph_id > 0) {
		$where = ' AND local_graph_id = ?';
		$params[] = $local_graph_id;
	}
	$items = db_fetch_assoc_prepared('SELECT id, local_graph_id, local_graph_template_item_id, sequence
		FROM graph_templates_item WHERE graph_template_id = ? AND local_graph_id > 0' . $where . '
		ORDER BY local_graph_id, id', $params);
	$seen = array();
	$deletes = array();
	$repairs = 0;
	foreach ($items as $item) {
		$parent = $item['local_graph_template_item_id'];
		$graph = $item['local_graph_id'];
		if ($force && (!isset($template_items[$parent]) || isset($seen[$graph][$parent]))) {
			$deletes[] = $item['id'];
			$repairs++;
		} elseif (isset($template_items[$parent]) && $item['sequence'] != $template_items[$parent]) {
			$repairs++;
		}
		$seen[$graph][$parent] = true;
	}
	foreach (array_chunk($deletes, 1000) as $ids) {
		db_execute('DELETE FROM graph_templates_item WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
	}

	$params = array($graph_template_id);
	$where = '';
	if ($local_graph_id > 0) {
		$where = ' AND child.local_graph_id = ?';
		$params[] = $local_graph_id;
	}
	db_execute_prepared('UPDATE graph_templates_item AS child
		INNER JOIN graph_templates_item AS parent ON parent.id = child.local_graph_template_item_id
		AND parent.graph_template_id = child.graph_template_id AND parent.local_graph_id = 0
		SET child.sequence = parent.sequence
		WHERE child.graph_template_id = ? AND child.local_graph_id > 0
		AND child.sequence <> parent.sequence' . $where, $params);

	$_SESSION['sess_gt_repairs'] = (isset($_SESSION['sess_gt_repairs']) ? $_SESSION['sess_gt_repairs'] : 0) + $repairs;

	return true;
}

/**
 * Reapply the graph template as it currently exists to all graphs using that template. This is
 * important when you have graphs that have multiple versions of a template. Used as part of
 * Cacti's lib functionality.
 *
 * @param int $graph_template_id The graph template id to retemplate.
 * @param int $local_graph_id Optional local graph id.
 * @param bool $sequence_only Optional only perform a resequence on graphs that have a mismatch on
 *   graph items.
 *
 * @return void No value is returned.
 */
function retemplate_graphs($graph_template_id, $local_graph_id = 0, $sequence_only = false) {
	$_SESSION['sess_gt_repairs'] = 0;
	if (!$sequence_only || $local_graph_id > 0) {
		if ($local_graph_id == 0) {
			$graphs = db_fetch_assoc_prepared('SELECT id
				FROM graph_local
				WHERE graph_template_id = ?',
				array($graph_template_id));
		} else {
			$graphs = db_fetch_assoc_prepared('SELECT id
				FROM graph_local
				WHERE graph_template_id = ?
				AND id = ?',
				array($graph_template_id, $local_graph_id));
		}

		if (cacti_sizeof($graphs)) {
			foreach($graphs as $graph) {
				change_graph_template($graph['id'], $graph_template_id, true);
			}
		}
	} else {
		// Correct counts do not imply correct order or unique template ancestry.
		resequence_graphs($graph_template_id, 0, true);
	}
}

/**
 * Maps each template data source item to the data source item that a graph uses for it. Used as
 * part of Cacti's lib functionality.
 *
 * Only data sources that the graph already uses are considered. The template ancestry recorded
 * in local_data_template_rrd_id decides the match. Data sources retemplated on earlier releases
 * can still carry the ancestry of their previous template, so a template item that ancestry does
 * not resolve falls back to the graph's data source item with the same data_source_name, as
 * earlier releases bound it. Both paths map to 0 when several of the graph's data source items
 * match, since multiple local instances of the same template require an explicit graph input.
 *
 * @param int $local_graph_id    The id of the graph whose data sources are matched.
 * @param int $graph_template_id The id of the graph template whose items are resolved, or 0 to
 *   skip the data_source_name fallback.
 *
 * @return array A map of template data_template_rrd id to the graph's data_template_rrd id, or to
 *   0 when the match is ambiguous.
 */
function graph_template_item_sources($local_graph_id, $graph_template_id = 0) {
	$items = db_fetch_assoc_prepared('SELECT DISTINCT child.id, parent.id AS template_item_id
		FROM data_template_rrd AS child
		INNER JOIN data_template_rrd AS parent
		ON parent.id = child.local_data_template_rrd_id
		AND parent.data_template_id = child.data_template_id
		WHERE parent.local_data_id = 0 AND child.local_data_id > 0
		AND child.local_data_id IN (
			SELECT used.local_data_id FROM data_template_rrd AS used
			INNER JOIN graph_templates_item AS gti ON gti.task_item_id = used.id
			WHERE gti.local_graph_id = ?)', array($local_graph_id));
	$sources = array();
	foreach ($items as $item) {
		$parent = $item['template_item_id'];
		$sources[$parent] = isset($sources[$parent]) ? 0 : $item['id'];
	}

	if ($graph_template_id > 0) {
		$named = db_fetch_assoc_prepared('SELECT DISTINCT child.id, parent.id AS template_item_id
			FROM data_template_rrd AS parent
			INNER JOIN data_template_rrd AS child
			ON child.data_source_name = parent.data_source_name
			WHERE parent.local_data_id = 0 AND child.local_data_id > 0
			AND parent.id IN (
				SELECT task_item_id FROM graph_templates_item
				WHERE local_graph_id = 0 AND graph_template_id = ?)
			AND child.local_data_id IN (
				SELECT used.local_data_id FROM data_template_rrd AS used
				INNER JOIN graph_templates_item AS gti ON gti.task_item_id = used.id
				WHERE gti.local_graph_id = ?)', array($graph_template_id, $local_graph_id));

		$fallback = array();
		foreach ($named as $item) {
			$parent = $item['template_item_id'];
			if (!isset($sources[$parent])) {
				$fallback[$parent] = isset($fallback[$parent]) ? 0 : $item['id'];
			}
		}
		$sources += $fallback;
	}

	return $sources;
}

/**
 * Generic routine that changes the graph template for a particular graph to a specific
 * graph_template_id which can be considered a generic re-templating function. Used as part of
 * Cacti's lib functionality.
 *
 * @param int $local_graph_id The id of the graph to change the graph template for.
 * @param int $graph_template_id The id the of the graph template to change to. specify '0' for no
 *   graph template.
 * @param bool $force If true and the target graph template has more or less graph items than the
 *   current graph, perform a deeper re-template activity, or a full repair.
 *
 * @return bool Bool.
 */
function change_graph_template($local_graph_id, $graph_template_id, $force = false) {
	global $struct_graph, $struct_graph_item;

	static $cols = array();

	$template_data     = parse_graph_template_id($graph_template_id);
	$graph_template_id = $template_data['graph_template_id'];

	/* get information about both the graph and the graph template we're using */
	$graph_list = db_fetch_row_prepared('SELECT *
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array($local_graph_id));

	if ($graph_template_id == 0) {
		$template_graph_list = $graph_list;
	} else {
		$template_graph_list = db_fetch_row_prepared('SELECT *
			FROM graph_templates_graph
			WHERE local_graph_id = 0
			AND graph_template_id = ?',
			array($graph_template_id));
	}

	if (!cacti_sizeof($template_graph_list)) {
		cacti_log(sprintf('WARNING: Graph Template with ID %s Does not have any Graph Items', $graph_template_id), false, 'AUTOM8');
		return false;
	}

	if (isset($template_data['output_type_id'])) {
		$output_type_id = $template_data['output_type_id'];
	} else {
		$output_type_id = 0;
	}

	$snmp_query_id = db_fetch_cell_prepared('SELECT snmp_query_id
		FROM graph_local
		WHERE id = ?',
		array($local_graph_id));

	/* always update tables to new graph template (or no graph template) */
	db_execute_prepared('UPDATE graph_local
		SET graph_template_id = ?
		WHERE id = ?',
		array($graph_template_id, $local_graph_id));

	if ($force) {
		$changed = true;
	} elseif ($output_type_id > 0) {
		$changed = true;
	} elseif (cacti_sizeof($graph_list) && $graph_template_id != $graph_list['graph_template_id']) {
		$changed = false;
	} else {
		$changed = true;
	}

	/* determine if we are here for the first time, or coming back */
	$exists = db_fetch_cell_prepared('SELECT local_graph_template_graph_id
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array($local_graph_id));

	if (!$exists) {
		$new_save = true;
	} else {
		$new_save = false;
	}

	/* some basic field values that ALL graphs should have */
	$save['id'] = (isset($graph_list['id']) ? $graph_list['id'] : 0);
	$save['local_graph_template_graph_id'] = $template_graph_list['id'];
	$save['local_graph_id']                = $local_graph_id;
	$save['graph_template_id']             = $graph_template_id;

	/* loop through the 'templated field names' to find the rest... */
	foreach ($struct_graph as $field_name => $field_array) {
		$value_type = "t_$field_name";

		if ($field_array['method'] != 'spacer') {
			if ((!empty($template_graph_list[$value_type])) && ($new_save == false)) {
				$save[$field_name] = $graph_list[$field_name];
			} else {
				$save[$field_name] = $template_graph_list[$field_name];
			}
		}
	}

	sql_save($save, 'graph_templates_graph');

	$graph_items_list = db_fetch_assoc_prepared('SELECT *
		FROM graph_templates_item
		WHERE local_graph_id = ?
		ORDER BY id',
		array($local_graph_id));

	$existing_items = array();
	foreach ($graph_items_list as $item) {
		if (!isset($existing_items[$item['local_graph_template_item_id']])) {
			$existing_items[$item['local_graph_template_item_id']] = $item;
		}
	}
	$source_items = graph_template_item_sources($local_graph_id, $graph_template_id);

	if ($graph_template_id == 0) {
		$template_items_list = $graph_items_list;
	} else {
		$template_items_list = db_fetch_assoc_prepared('SELECT *
			FROM graph_templates_item
			WHERE local_graph_id = 0
			AND graph_template_id = ?
			ORDER BY id',
			array($graph_template_id));
	}

	if (!cacti_sizeof($cols)) {
		$cols = db_get_table_column_types('graph_templates_item');
	}

	if (cacti_sizeof($template_items_list)) {
		foreach ($template_items_list as $template_item) {
			unset($save);

			$save['local_graph_template_item_id'] = $template_item['id'];
			$save['local_graph_id']               = $local_graph_id;
			$save['graph_template_id']            = $template_item['graph_template_id'];
			$save['sequence']                     = $template_item['sequence'];

			/* go through the existing graph_items and look for the matching local_graph_template_item_id */
			$found = !$new_save && isset($existing_items[$template_item['id']]);
			if ($found) {
				$found_item = $existing_items[$template_item['id']];
			}

			if ($found) {
				foreach($found_item as $column => $value) {
					switch($column) {
					case 'local_graph_id':
					case 'hash':
					case 'local_graph_template_item_id':
					case 'graph_template_id':
					case 'sequence':
						break;
					default:
						if (strstr($cols[$column]['type'], 'int') !== false ||
							strstr($cols[$column]['type'], 'float') !== false ||
							strstr($cols[$column]['type'], 'decimal') !== false ||
							strstr($cols[$column]['type'], 'double') !== false) {
							if (!empty($value)) {
								$save[$column] = $value;
							} else {
								$save[$column] = 0;
							}
						} else {
							$save[$column] = $value;
						}

						break;
					}
				}
			} else {
				/* no graph item at this position, tack it on */
				$save['id'] = 0;

				$save['task_item_id'] = isset($source_items[$template_item['task_item_id']]) ? $source_items[$template_item['task_item_id']] : 0;

				foreach($template_item as $column => $value) {
					switch($column) {
					case 'id':
					case 'hash':
					case 'local_graph_id':
					case 'local_graph_template_item_id':
					case 'graph_template_id':
					case 'sequence':
					case 'task_item_id':
						break;
					default:
						if (strstr($cols[$column]['type'], 'int') !== false ||
							strstr($cols[$column]['type'], 'float') !== false ||
							strstr($cols[$column]['type'], 'decimal') !== false ||
							strstr($cols[$column]['type'], 'double') !== false) {
							if (!empty($value)) {
								$save[$column] = $value;
							} else {
								$save[$column] = 0;
							}
						} else {
							$save[$column] = $value;
						}
						break;
					}
				}
			}

			sql_save($save, 'graph_templates_item');
		}
	}

	if ($new_save == false && $force) {
		resequence_graphs($graph_template_id, $local_graph_id, $force);
	}

	/* handle changes in data template if there are any */
	if ($new_save == false && $changed && $snmp_query_id > 0) {
		update_graph_data_source_output_type($local_graph_id, $output_type_id);
	}

	return true;
}

/**
 * Update a graph template item for a graph template this function is used to push out graph item
 * changes to graphs of a specific template. It will be called when you are saving a graph item.
 * In cases where the Graph Item is new, it will update all graphs with a new item. When the graph
 * item exists, it'll just walk through all columns and update the values.
 *
 * @param int $graph_template_id (int) The graph template id to update.
 * @param int $graph_template_item_id (int) The graph template item id from the template.
 * @param bool $task_item_changed Tells us if this is a new graph item or one that needs to be
 *   pushed to all graphs.
 *
 * @return void Null.
 */
function update_graph_template_items($graph_template_id, $graph_template_item_id, $task_item_changed) {
	global $struct_graph_item;

	static $cols = array();

	$template_items_list = db_fetch_assoc_prepared('SELECT *
		FROM graph_templates_item
		WHERE local_graph_id = 0
		AND graph_template_id = ?
		AND id = ?
		ORDER BY id',
		array($graph_template_id, $graph_template_item_id));

	$input_columns = array_rekey(db_fetch_assoc_prepared('SELECT DISTINCT gti.column_name
		FROM graph_template_input AS gti
		INNER JOIN graph_template_input_defs AS defs ON defs.graph_template_input_id = gti.id
		WHERE defs.graph_template_item_id = ?', array($graph_template_item_id)), 'column_name', 'column_name');

	if (cacti_sizeof($template_items_list)) {
		foreach ($template_items_list as $template_item) {
			foreach($template_item as $column => $value) {
				switch($column) {
					case 'color_id':
					case 'alpha':
					case 'graph_type_id':
					case 'line_width':
					case 'dashes':
					case 'dash_offset':
					case 'cdef_id':
					case 'vdef_id':
					case 'shift':
					case 'consolidation_function_id':
					case 'textalign':
					case 'text_format':
					case 'value':
					case 'hard_return':
					case 'gprint_id':
						if (isset($input_columns[$column])) {
							break;
						}
						db_execute_prepared("UPDATE graph_templates_item
							SET $column = ?
							WHERE local_graph_template_item_id = ?",
							array($value, $graph_template_item_id));
						break;
					default:
						break;
				}
			}
		}
	}

	if ($task_item_changed) {
		if (!cacti_sizeof($cols)) {
			$cols = db_get_table_column_types('graph_templates_item');
		}

		$graphs = array_rekey(
			db_fetch_assoc_prepared('SELECT id
				FROM graph_local
				WHERE graph_template_id = ?',
				array($graph_template_id)),
			'id', 'id'
		);

		if (cacti_sizeof($graphs)) {
			foreach($graphs as $local_graph_id) {
				$source_items = graph_template_item_sources($local_graph_id, $graph_template_id);
				$graph_items_list = db_fetch_assoc_prepared('SELECT *
					FROM graph_templates_item
					WHERE local_graph_id = ?
					ORDER BY id',
					array($local_graph_id));

				if (cacti_sizeof($template_items_list)) {
					foreach ($template_items_list as $template_item) {
						unset($save);

						$save['local_graph_template_item_id'] = $template_item['id'];
						$save['local_graph_id']               = $local_graph_id;
						$save['graph_template_id']            = $template_item['graph_template_id'];
						$save['sequence']                     = $template_item['sequence'];

						/* go through the existing graph_items and look for the matching local_graph_template_item_id */
						$found = false;
						if (cacti_sizeof($graph_items_list)) {
							foreach($graph_items_list as $item) {
								if ($item['local_graph_template_item_id'] == $template_item['id']) {
									$found_item = $item;
									$found = true;
									break;
								}
							}
						}

						if ($found) {
							foreach($found_item as $column => $value) {
								switch($column) {
								case 'local_graph_id':
								case 'hash':
								case 'local_graph_template_item_id':
								case 'graph_template_id':
								case 'sequence':
									break;
								default:
									if (strstr($cols[$column]['type'], 'int') !== false ||
										strstr($cols[$column]['type'], 'float') !== false ||
										strstr($cols[$column]['type'], 'decimal') !== false ||
										strstr($cols[$column]['type'], 'double') !== false) {
										if (!empty($value)) {
											$save[$column] = $value;
										} else {
											$save[$column] = 0;
										}
									} else {
										$save[$column] = $value;
									}

									break;
								}
							}
						} else {
							/* no graph item at this position, tack it on */
							$save['id'] = 0;

							$save['task_item_id'] = isset($source_items[$template_item['task_item_id']]) ? $source_items[$template_item['task_item_id']] : 0;

							foreach($template_item as $column => $value) {
								switch($column) {
								case 'id':
								case 'hash':
								case 'local_graph_id':
								case 'local_graph_template_item_id':
								case 'graph_template_id':
								case 'sequence':
								case 'task_item_id':
									break;
								default:
									if (strstr($cols[$column]['type'], 'int') !== false ||
										strstr($cols[$column]['type'], 'float') !== false ||
										strstr($cols[$column]['type'], 'decimal') !== false ||
										strstr($cols[$column]['type'], 'double') !== false) {
										if (!empty($value)) {
											$save[$column] = $value;
										} else {
											$save[$column] = 0;
										}
									} else {
										$save[$column] = $value;
									}
									break;
								}
							}
						}

						sql_save($save, 'graph_templates_item');
					}
				}
			}
		}
	}
}

/**
 * Converts a graph to a graph template. Used as part of Cacti's lib functionality.
 *
 * @param int $local_graph_id The id of the graph to be converted.
 * @param string $graph_title The graph title to use for the new graph template. the variable
 *   <graph_title> will be substituted for the current graph title.
 *
 * @return void No value is returned.
 */
function graph_to_graph_template($local_graph_id, $graph_title) {
	/* create a new graph template entry */
	$title_template = db_fetch_cell_prepared('SELECT title
		FROM graph_templates_graph WHERE local_graph_id = ?',
		array($local_graph_id));

	$title = str_replace('<graph_title>', $title_template, $graph_title);

	db_execute_prepared('INSERT INTO graph_templates
		(id, name, hash)
		VALUES (0, ?, ?)', array($title, get_hash_graph_template(0)));

	$graph_template_id = db_fetch_insert_id();

	/* update graph to point to the new template */
	db_execute_prepared('UPDATE graph_templates_graph
		SET local_graph_id=0, local_graph_template_graph_id=0, graph_template_id = ?
		WHERE local_graph_id = ?',
		array($graph_template_id, $local_graph_id));

	db_execute_prepared('UPDATE graph_templates_item
		SET local_graph_id=0, local_graph_template_item_id=0, graph_template_id = ?, task_item_id=0
		WHERE local_graph_id = ?',
		array($graph_template_id, $local_graph_id));

	/* create hashes for the graph template items */
	$items = db_fetch_assoc_prepared('SELECT id
		FROM graph_templates_item
		WHERE graph_template_id = ?
		AND local_graph_id=0',
		array($graph_template_id));

	for ($j=0; $j<cacti_count($items); $j++) {
		db_execute_prepared('UPDATE graph_templates_item
			SET hash = ? WHERE id= ?',
			array(get_hash_graph_template($items[$j]['id'], 'graph_template_item'), $items[$j]['id']));
	}

	/* delete the old graph local entry */
	db_execute_prepared('DELETE FROM graph_local WHERE id = ?', array($local_graph_id));
	db_execute_prepared('DELETE FROM graph_tree_items WHERE local_graph_id = ?', array($local_graph_id));
}

/**
 * Converts a data source to a data template. Used as part of Cacti's lib functionality.
 *
 * @param int $local_data_id The id of the data source to be converted.
 * @param string $data_source_title The data source title to use for the new data template. the
 *   variable <ds_title> will be substituted for the current data source title.
 *
 * @return void No value is returned.
 */
function data_source_to_data_template($local_data_id, $data_source_title) {
	/* create a new graph template entry */
	$title_template = db_fetch_cell_prepared('SELECT name
		FROM data_template_data
		WHERE local_data_id = ?',
		array($local_data_id));

	$title = str_replace('<ds_title>', $title_template, $data_source_title);

	db_execute_prepared('INSERT INTO data_template
		(id, name, hash)
		VALUES (0, ?, ?)',
		array($title, get_hash_data_template(0)));

	$data_template_id = db_fetch_insert_id();

	/* update graph to point to the new template */
	db_execute_prepared('UPDATE data_template_data
		SET local_data_id=0, local_data_template_data_id=0, data_template_id = ?
		WHERE local_data_id = ?',
		array($data_template_id, $local_data_id));

	db_execute_prepared('UPDATE data_template_rrd
		SET local_data_id=0, local_data_template_rrd_id=0, data_template_id = ?
		WHERE local_data_id = ?',
		array($data_template_id, $local_data_id));

	/* create hashes for the data template items */
	$items = db_fetch_assoc_prepared('SELECT id
		FROM data_template_rrd
		WHERE data_template_id = ?
		AND local_data_id=0',
		array($data_template_id));

	for ($j=0; $j<cacti_count($items); $j++) {
		db_execute_prepared('UPDATE data_template_rrd
			SET hash = ? WHERE id =?',
			array(get_hash_data_template($items[$j]['id'], 'data_template_item'), $items[$j]['id']));
	}

	/* delete the old graph local entry */
	db_execute_prepared('DELETE FROM data_local WHERE id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM poller_item WHERE local_data_id= ?', array($local_data_id));
}

/**
 * Creates a graph and all necessary data sources based on a graph template. Used as part of
 * Cacti's lib functionality.
 *
 * @param int $graph_template_id The id of the graph template that will be used to create the new
 *   graph.
 * @param int $host_id The id of the host to associate the new graph and data sources with.
 * @param array $snmp_query_array If the new data sources are to be based on a data query, specify
 *   the necessary data query information here. it must contain the following information:
 *   $snmp_query_array['snmp_query_id'] $snmp_query_array['snmp_index_on']
 *   $snmp_query_array['snmp_query_graph_id'] $snmp_query_array['snmp_index'].
 * @param mixed &$suggested_vals Any additional information to be included in the new graphs or data
 *   sources must be included in the array. data is to be included in the following format:
 *   $values['cg'][graph_template_id]['graph_template'][field_name] = $value // graph template
 *   $values['cg'][graph_template_id]['graph_template_item'][graph_template_item_id][field_name] =
 *   $value // graph template item $values['cg'][data_template_id]['data_template'][field_name] =
 *   $value // data template
 *   $values['cg'][data_template_id]['data_template_item'][data_template_item_id][field_name] =
 *   $value // data template item
 *   $values['sg'][data_query_id][graph_template_id]['graph_template'][field_name] = $value // graph
 *   template (w/ data query)
 *   $values['sg'][data_query_id][graph_template_id]['graph_template_item'][graph_template_item_id][field_name]
 *   = $value // graph template item (w/ data query)
 *   $values['sg'][data_query_id][data_template_id]['data_template'][field_name] = $value // data
 *   template (w/ data query)
 *   $values['sg'][data_query_id][data_template_id]['data_template_item'][data_template_item_id][field_name]
 *   = $value // data template item (w/ data query).
 *
 * @param int $local_graph_id Existing empty graph shell to resume, or zero for a new graph.
 *
 * @return mixed False if failing, otherwise the local_data_id data in a cache array.
 */
function create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, &$suggested_vals, $local_graph_id = 0) {
	global $database_sessions, $database_hostname, $database_port, $database_default, $database_last_error;

	$db_conn = $database_sessions["$database_hostname:$database_port:$database_default"];
	// Hooks publish tree/cache changes. They must run only after our transaction commits.
	if ($db_conn->inTransaction()) {
		// Name the caller: plugins that wrap graph creation in their own transaction fail here.
		$caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
		$from   = isset($caller[0]['file']) ? basename($caller[0]['file']) . ':' . $caller[0]['line'] : 'unknown';

		cacti_log('ERROR: Graph creation for Device[' . $host_id . '], GT[' . $graph_template_id . '] requires its own transaction, but ' . $from . ' called it inside an open transaction.', false, 'AUTOM8');
		return false;
	}

	$previous_error = isset($database_last_error) ? $database_last_error : '';
	$database_last_error = '';
	$committed = false;
	$created_graph = array();
	$created_data_sources = array();

	try {
		if (!db_begin_transaction($db_conn)) {
			return false;
		}

		$result = create_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $suggested_vals, $local_graph_id, $created_graph, $created_data_sources);
		// Legacy template helpers report failed SQL through database_last_error.
		// Checking it here also catches failed writes in their nested calls.
		if ($result === false || $database_last_error != '' || !$db_conn->inTransaction()) {
			return false;
		}

		$committed = db_commit_transaction($db_conn);
		if (!$committed) {
			return false;
		}
	} catch (Throwable $e) {
		cacti_log('ERROR: Graph creation failed for Device[' . $host_id . '], GT[' . $graph_template_id . ']: ' . $e->getMessage(), false, 'AUTOM8');
		return false;
	} finally {
		if (!$committed && $db_conn->inTransaction()) {
			db_rollback_transaction($db_conn);
		}
		if ($database_last_error == '') {
			$database_last_error = $previous_error;
		}
	}

	foreach ($created_data_sources as $data_source_id) {
		api_plugin_hook_function('update_data_source_title_cache', $data_source_id);
	}

	if (cacti_sizeof($created_graph)) {
		automation_hook_graph_create_tree($created_graph);
		api_plugin_hook_function('create_complete_graph_from_template', $created_graph);
	}

	return $result;
}

/**
 * Write graph records inside create_complete_graph_from_template's transaction.
 */
function create_graph_from_template($graph_template_id, $host_id, $snmp_query_array, &$suggested_vals, $local_graph_id, &$created_graph, &$created_data_sources) {
	global $config;

	include_once($config['library_path'] . '/data_query.php');

	if (!graph_template_whitelist_check($graph_template_id)) {
		raise_message(10);
		return false;
	}

	/* don't create a graph for a bad template */
	if (empty($graph_template_id)) {
		raise_message('bad_template', __('Attempting to Create Graph from Non-Template'), MESSAGE_LEVEL_ERROR);
		cacti_log('Attempting to Create Graph from Non-Template', false, 'AUTOM8');
		return false;
	}

	/* don't create a graph from a remove template */
	$gt = db_fetch_cell_prepared('SELECT id
		FROM graph_templates
		WHERE id = ?',
		array($graph_template_id));

	if (empty($gt)) {
		raise_message('bad_template', __('Attempting to Create Graph from Removed Graph Template'), MESSAGE_LEVEL_ERROR);
		cacti_log('Attempting to Create Graph from Removed Graph Template ' . $graph_template_id, false, 'AUTOM8');
		return false;
	}

	/* create the graph */
	$save['id']                  = $local_graph_id;
	$save['graph_template_id']   = $graph_template_id;
	$save['host_id']             = $host_id;

	/* defaults for non-snmp query based */
	$save['snmp_query_id']       = 0;
	$save['snmp_query_graph_id'] = 0;
	$save['snmp_index']          = '';

	if (cacti_sizeof($snmp_query_array)) {
		if (isset($snmp_query_array['snmp_query_id']) && $snmp_query_array['snmp_query_id'] > 0) {
			$save['snmp_query_id'] = $snmp_query_array['snmp_query_id'];
		}

		if (isset($snmp_query_array['snmp_query_graph_id']) && $snmp_query_array['snmp_query_graph_id'] > 0) {
			$save['snmp_query_graph_id'] = $snmp_query_array['snmp_query_graph_id'];
		}

		if (isset($snmp_query_array['snmp_index']) && $snmp_query_array['snmp_index'] != '') {
			$save['snmp_index'] = $snmp_query_array['snmp_index'];
		}
	}

	$cache_array['local_graph_id'] = sql_save($save, 'graph_local');
	if (empty($cache_array['local_graph_id'])) {
		return false;
	}

	/**
	 * Save the last time a graph was created/deleted
	 * for Caching.
	 */
	set_config_option('time_last_change_graph', time());

	/* apply graph items */
	if (!change_graph_template($cache_array['local_graph_id'], $graph_template_id, true)) {
		return false;
	}

	/* perform graph replacement based upon suggested values */
	if (cacti_sizeof($snmp_query_array)) {
		api_reapply_suggested_graph_title($cache_array['local_graph_id']);
	}

	/* suggested values: graph, passed from parent */
	if (isset($suggested_vals[$graph_template_id]['graph_template'])) {
		foreach ($suggested_vals[$graph_template_id]['graph_template'] as $field_name => $field_value) {
			db_execute_prepared("UPDATE graph_templates_graph
				SET $field_name = ?
				WHERE local_graph_id = ?",
				array($field_value, $cache_array['local_graph_id']));
		}
	}

	/* suggested values: graph item, passed from parent */
	if (isset($suggested_vals[$graph_template_id]['graph_template_item'])) {
		foreach ($suggested_vals[$graph_template_id]['graph_template_item'] as $graph_template_item_id => $field_array) {
			foreach ($field_array as $field_name => $field_value) {
				$graph_item_id = db_fetch_cell_prepared('SELECT id
					FROM graph_templates_item
					WHERE local_graph_template_item_id = ?
					AND local_graph_id = ?',
					array($graph_template_item_id, $cache_array['local_graph_id']));

				db_execute_prepared("UPDATE graph_templates_item
					SET $field_name = ?
					WHERE id = ?",
					array($field_value, $graph_item_id));
			}
		}
	}

	/* create each data source, but don't duplicate */
	$data_templates = db_fetch_assoc_prepared('SELECT dt.id, dt.name, dtr.data_source_name
		FROM data_template AS dt
		INNER JOIN data_template_rrd AS dtr
		ON dtr.data_template_id=dt.id
		INNER JOIN graph_templates_item AS gti
		ON gti.task_item_id=dtr.id
		WHERE dtr.local_data_id = 0
		AND gti.local_graph_id = 0
		AND gti.graph_template_id = ?
		GROUP BY dt.id
		ORDER BY dt.name',
		array($graph_template_id));

	if (cacti_sizeof($data_templates)) {
		foreach ($data_templates as $data_template) {
			/* check if the data source already exists */
			$previous_data_source = data_source_exists($graph_template_id, $host_id, $data_template, $snmp_query_array);

			if (read_config_option('data_source_trace') == 'on') {
				cacti_log('Data Source previous ' . ($previous_data_source ? 'exists':'does not exist'), false, 'DSTRACE');
				cacti_log('Data Source keys "' . implode(', ', array_keys($previous_data_source)) . '"', false, 'DSTRACE');
				cacti_log('Data Source values "' . implode(', ', array_values($previous_data_source)) . '"', false, 'DSTRACE');
			}

			if (cacti_sizeof($previous_data_source)) {
				$use_previous_data = create_graph_custom_data_compatible(isset($suggested_vals[$graph_template_id]) ? array($graph_template_id => $suggested_vals[$graph_template_id]) : array(), $previous_data_source);
			} else {
				$use_previous_data = false;
			}

			if (read_config_option('data_source_trace') == 'on' && $use_previous_data) {
				cacti_log('Existing Data Source compatible', false, 'DSTRACE');
			}

			if (cacti_sizeof($previous_data_source) && $use_previous_data) {
				$cache_array['local_data_id'][$data_template['id']] = $previous_data_source['id'];
			} else {
				// Save the data source
				unset($save);

				$save['id']               = 0;
				$save['data_template_id'] = $data_template['id'];
				$save['host_id']          = $host_id;

				if (cacti_sizeof($snmp_query_array)) {
					if (isset($snmp_query_array['snmp_query_id']) && $snmp_query_array['snmp_query_id'] > 0) {
						$save['snmp_query_id'] = $snmp_query_array['snmp_query_id'];
					}

					if (isset($snmp_query_array['snmp_index']) && $snmp_query_array['snmp_index'] != '') {
						$save['snmp_index'] = $snmp_query_array['snmp_index'];
					}
				}

				$cache_array['local_data_id'][$data_template['id']] = sql_save($save, 'data_local');
				if (empty($cache_array['local_data_id'][$data_template['id']])) {
					return false;
				}

				/**
				 * Save the last time a data_source was created/deleted
				 * for Caching.
				 */
				set_config_option('time_last_change_data_source', time());

				// Determine the data source profile to use
				if (isset($suggested_vals[$graph_template_id]['data_template'][$data_template['id']]['data_source_profile_id'])) {
					$profile_id = $suggested_vals[$graph_template_id]['data_template'][$data_template['id']]['data_source_profile_id'];

					/* validate the data source profile */
					$profile = array();
					if ($profile_id != 0) {
						$profile = db_fetch_row_prepared('SELECT *
							FROM data_source_profiles
							WHERE id = ?',
							array($profile_id));
					}

					/* default to the default profile if the one given is invalid */
					if (!cacti_sizeof($profile)) {
						$profile = db_fetch_row('SELECT *
							FROM data_source_profiles
							ORDER BY `default`
							DESC LIMIT 1');
					}
				} else {
					$profile_id = 0;
					$profile    = array();
				}

				if (!change_data_template($cache_array['local_data_id'][$data_template['id']], $data_template['id'], $profile)) {
					return false;
				}

				$data_template_data_id = db_fetch_cell_prepared('SELECT id
					FROM data_template_data
					WHERE local_data_id = ?',
					array($cache_array['local_data_id'][$data_template['id']]));

				// Set suggested names and custom data
				if (cacti_sizeof($snmp_query_array)) {
					$data_input_fields = array_rekey(db_fetch_assoc_prepared('SELECT dif.id, dif.type_code
						FROM snmp_query AS sq
						INNER JOIN data_input AS di
						ON sq.data_input_id = di.id
						INNER JOIN data_input_fields AS dif
						ON di.id = dif.data_input_id
						WHERE (dif.type_code = "index_type"
						OR dif.type_code = "index_value"
						OR dif.type_code = "output_type")
						AND sq.id = ?',
						array($snmp_query_array['snmp_query_id'])), 'type_code', 'id');

					$snmp_cache_value = db_fetch_cell_prepared('SELECT field_value
						FROM host_snmp_cache
						WHERE host_id = ?
						AND snmp_query_id = ?
						AND field_name = ?
						AND snmp_index = ?',
						array($host_id, $snmp_query_array['snmp_query_id'],
							$snmp_query_array['snmp_index_on'], $snmp_query_array['snmp_index']));

					if (cacti_sizeof($data_input_fields)) {
						foreach($data_input_fields as $type_code => $id) {
							$checked = data_input_field_always_checked($id) ? 'on':'';

							switch($type_code) {
								case 'index_type':
									/* save the value to index on (ie. ifindex, ifip, etc) */
									db_execute_prepared('REPLACE INTO data_input_data
										(data_input_field_id, data_template_data_id, t_value, value)
										VALUES (?, ?, ?, ?)',
										array($id, $data_template_data_id, $checked, $snmp_query_array['snmp_index_on']));

									break;
								case 'index_value':
									/* save the actual value (ie. 3, 192.168.1.101, etc) */
									db_execute_prepared('REPLACE INTO data_input_data
										(data_input_field_id, data_template_data_id, t_value, value)
										VALUES (?, ?, ?, ?)',
										array($id, $data_template_data_id, $checked, $snmp_cache_value));

									break;
								case 'output_type':
									/* set the expected output type (ie. bytes, errors, packets) */
									db_execute_prepared('REPLACE INTO data_input_data
										(data_input_field_id, data_template_data_id, t_value, value)
										VALUES (?, ?, ?, ?)',
										array($id, $data_template_data_id, $checked, $snmp_query_array['snmp_query_graph_id']));
									break;
							}
						}
					}

					api_reapply_suggested_data_source_data($cache_array['local_data_id'][$data_template['id']]);

					/* now that we have put data into the 'data_input_data' table, update the snmp cache for ds's */
					update_data_source_data_query_cache($cache_array['local_data_id'][$data_template['id']], '', '', '', false);
				}

				/* suggested values: data source, passed from parent */
				if (isset($suggested_vals[$graph_template_id]['data_template'][$data_template['id']])) {
					foreach ($suggested_vals[$graph_template_id]['data_template'][$data_template['id']] as $field_name => $field_value) {
						db_execute_prepared("UPDATE data_template_data
							SET $field_name = ?
							WHERE local_data_id = ?",
							array($field_value, $cache_array['local_data_id'][$data_template['id']]));
					}
				}

				/* suggested values: data source item, passed from parent */
				if (isset($suggested_vals[$graph_template_id]['data_template_item'])) {
					foreach ($suggested_vals[$graph_template_id]['data_template_item'] as $data_template_item_id => $field_array) {
						foreach ($field_array as $field_name => $field_value) {
							$data_source_item_id = db_fetch_cell_prepared('SELECT id
								FROM data_template_rrd
								WHERE local_data_template_rrd_id = ?
								AND local_data_id = ?',
								array($data_template_item_id, $cache_array['local_data_id'][$data_template['id']]));

							db_execute_prepared("UPDATE data_template_rrd
								SET $field_name = ?
								WHERE id = ?",
								array($field_value, $data_source_item_id));
						}
					}
				}

				/* suggested values: custom data, passed from parent */
				if (isset($suggested_vals[$graph_template_id]['custom_data'][$data_template['id']])) {
					foreach ($suggested_vals[$graph_template_id]['custom_data'][$data_template['id']] as $data_input_field_id => $field_value) {
						db_execute_prepared('REPLACE INTO data_input_data
							(data_input_field_id, data_template_data_id, t_value, value)
							VALUES (?, ?, "", ?)',
							array($data_input_field_id, $data_template_data_id, $field_value));
					}
				}

				update_data_source_title_cache($cache_array['local_data_id'][$data_template['id']], false);
				$created_data_sources[] = $cache_array['local_data_id'][$data_template['id']];
			}
		}
	}

	/* connect the dots: graph -> data source(s) */
	$template_item_list = db_fetch_assoc_prepared('SELECT
		gti.id, dtr.id AS data_template_rrd_id, dtr.data_template_id
		FROM graph_templates_item AS gti
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id=dtr.id
		WHERE gti.graph_template_id = ?
		AND local_graph_id=0
		AND task_item_id>0',
		array($graph_template_id));

	/* loop through each item affected and update column data */
	if (cacti_sizeof($template_item_list)) {
		foreach ($template_item_list as $template_item) {
			if (isset($cache_array['local_data_id'][$template_item['data_template_id']])) {
				$local_data_id = $cache_array['local_data_id'][$template_item['data_template_id']];

				$graph_template_item_id = db_fetch_cell_prepared('SELECT id
					FROM graph_templates_item
					WHERE local_graph_template_item_id = ?
					AND local_graph_id = ?',
					array( $template_item['id'], $cache_array['local_graph_id']));

				$data_template_rrd_id = db_fetch_cell_prepared('SELECT id
					FROM data_template_rrd
					WHERE local_data_template_rrd_id = ?
					AND local_data_id = ?',
					array($template_item['data_template_rrd_id'], $local_data_id));

				if (empty($graph_template_item_id) || empty($data_template_rrd_id)) {
					return false;
				}

				db_execute_prepared('UPDATE graph_templates_item
					SET task_item_id = ?
					WHERE id = ?',
					array($data_template_rrd_id, $graph_template_item_id));
			}
		}
	}

	/* this will not work until the ds->graph dots are connected */
	if (cacti_sizeof($snmp_query_array)) {
		if (isset($cache_array['local_graph_id'])) {
			update_graph_data_query_cache($cache_array['local_graph_id']);
		}
	}

	update_graph_title_cache($cache_array['local_graph_id']);

	/* now that we have the id of the new host, we may plugin postprocessing code */
	if (isset($cache_array['local_graph_id'])) {
		$save['id']                = $cache_array['local_graph_id'];
		$save['graph_template_id'] = $graph_template_id;	// attention: unset!

		if (cacti_sizeof($snmp_query_array)) {
			$save['snmp_query_id']       = $snmp_query_array['snmp_query_id'];
			$save['snmp_index']          = $snmp_query_array['snmp_index'];
			$save['snmp_query_graph_id'] = $snmp_query_array['snmp_query_graph_id'];
		} else {
			$save['snmp_query_id']       = 0;
			$save['snmp_index']          = 0;
			$save['snmp_query_graph_id'] = 0;
		}

		$created_graph = $save;
	}

	return $cache_array;
}

/**
 * Checks to see if this graphs data sources have custom data source properties. If so, then you
 * must duplicate the Data Source and not use the existing one. Used as part of Cacti's lib
 * functionality.
 *
 * @param array $suggested_vals Any additional information to be included in the new graphs or
 *   data sources must be included in the array. data is to be included in the following format:
 *   $values['cg'][graph_template_id]['graph_template'][field_name] = $value // graph template
 *   $values['cg'][graph_template_id]['graph_template_item'][graph_template_item_id][field_name] =
 *   $value // graph template item $values['cg'][data_template_id]['data_template'][field_name] =
 *   $value // data template
 *   $values['cg'][data_template_id]['data_template_item'][data_template_item_id][field_name] =
 *   $value // data template item
 *   $values['sg'][data_query_id][graph_template_id]['graph_template'][field_name] = $value // graph
 *   template (w/ data query)
 *   $values['sg'][data_query_id][graph_template_id]['graph_template_item'][graph_template_item_id][field_name]
 *   = $value // graph template item (w/ data query)
 *   $values['sg'][data_query_id][data_template_id]['data_template'][field_name] = $value // data
 *   template (w/ data query)
 *   $values['sg'][data_query_id][data_template_id]['data_template_item'][data_template_item_id][field_name]
 *   = $value // data template item (w/ data query).
 * @param array $previous_data_source The previous data source if this data source id duplicate.
 *
 * @return bool Bool.
 */
function create_graph_custom_data_compatible($suggested_vals, $previous_data_source) {
	if (!cacti_sizeof($suggested_vals)) {
		return true;
	}

	$data = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE local_data_id = ?',
		array($previous_data_source['id']));
	if (!cacti_sizeof($data)) {
		return false;
	}
	$rrds = array_column(db_fetch_assoc_prepared('SELECT parent.id AS template_item_id, child.*
		FROM data_template_rrd AS parent
		LEFT JOIN data_template_rrd AS child
		ON child.local_data_template_rrd_id = parent.id AND child.local_data_id = ?
		WHERE parent.data_template_id = ? AND parent.local_data_id = 0',
		array($previous_data_source['id'], $data['data_template_id'])), null, 'template_item_id');
	$custom = array_rekey(db_fetch_assoc_prepared('SELECT data_input_field_id, value
		FROM data_input_data WHERE data_template_data_id = ?', array($data['id'])), 'data_input_field_id', 'value');

	foreach ($suggested_vals as $items) {
		// Older callers group overrides by template/item id before the type.
		$groups = array();
		foreach ($items as $type => $item) {
			if (is_numeric($type)) {
				foreach ($item as $kind => $values) {
					$groups[] = array($kind, $type, $values);
				}
			} else {
				foreach ($item as $id => $values) {
					$groups[] = array($type, $id, $values);
				}
			}
		}
		foreach ($groups as $group) {
			list($type, $id, $values) = $group;
			if ($type == 'data_template' || $type == 'custom_data') {
				if ($id != $data['data_template_id']) {
					continue;
				}
				$previous = $type == 'data_template' ? $data : $custom;
			} elseif ($type == 'data_template_item') {
				if (!isset($rrds[$id])) {
					continue;
				}
				if (empty($rrds[$id]['id'])) {
					return false;
				}
				$previous = $rrds[$id];
			} else {
				continue;
			}
			foreach ($values as $field => $value) {
				// A missing row must not compare equal to a requested empty string or zero.
				if (!array_key_exists($field, $previous) || (string)$previous[$field] !== (string)$value) {
					return false;
				}
			}
		}
	}

	return true;
}

/**
 * Creates the save graph. Used as part of Cacti's lib functionality.
 *
 * @param int $host_id The host ID.
 * @param string $form_type The form type.
 * @param int $form_id1 The form id1.
 * @param array $form_array2 The form array2.
 * @param mixed $values The values.
 *
 * @return void No value is returned.
 */
function create_save_graph($host_id, $form_type, $form_id1, $form_array2, $values) {
	/* ================= input validation ================= */
	input_validate_input_number($form_id1);
	/* ==================================================== */

	if ($form_type == 'cg') {
		$graph_template_id = $form_id1;
	} elseif ($form_type == 'sg') {
		foreach ($form_array2 as $form_id2 => $form_array3) {
			/* ================= input validation ================= */
			input_validate_input_number($form_id2);
			/* ==================================================== */

			$snmp_index_array = $form_array3;

			$snmp_query_array['snmp_query_id'] = $form_id1;
			$snmp_query_array['snmp_index_on'] = get_best_data_query_index_type($host_id, $form_id1);
			$snmp_query_array['snmp_query_graph_id'] = $form_id2;
		}

		$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
			FROM snmp_query_graph
			WHERE id = ?',
			array($snmp_query_array['snmp_query_graph_id']));
	}

	if ($form_type == 'cg') {
		$snmp_query_array = array();
		if (isset($values['cg'][$graph_template_id])) {
			$params1 = $values['cg'][$graph_template_id];
		} else {
			$params1 = array();
		}

		if (isset($values['cg'])) {
			$params2 = $values['cg'];
		} else {
			$params2 = array();
		}

		if (test_data_sources($graph_template_id, $host_id, 0, '', $params1)) {
			$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $params2);

			if ($return_array !== false) {
				debug_log_insert('new_graphs', __esc('Created: %s', get_graph_title($return_array['local_graph_id'])));

				/* lastly push host-specific information to our data sources */
				if (cacti_sizeof($return_array['local_data_id'])) { # we expect at least one data source associated
					foreach($return_array['local_data_id'] as $item) {
						push_out_host($host_id, $item);
					}
				} else {
					debug_log_insert('new_graphs', __esc('ERROR: No Data Source associated. Check Template'));
				}

				db_execute_prepared('INSERT IGNORE INTO host_graph
					(host_id, graph_template_id)
					VALUES(?, ?)',
					array($host_id, $graph_template_id));
			} else {
				debug_log_insert('new_graphs', __esc('ERROR: Whitelist Validation Failed. Check Data Input Method'));
			}
		} else {
			$name = db_fetch_cell_prepared('SELECT name
				FROM graph_templates
				WHERE id = ?',
				array($graph_template_id));

			debug_log_insert('new_graphs', __esc('Graph Not created for %s due to bad data', $name));
		}
	} elseif ($form_type == 'sg') {
		foreach ($snmp_index_array as $snmp_index => $true) {
			$snmp_query_array['snmp_index'] = decode_data_query_index($snmp_index, $snmp_query_array['snmp_query_id'], $host_id);

			if (test_data_sources($graph_template_id, $host_id, $snmp_query_array['snmp_query_id'], $snmp_query_array['snmp_index'])) {
				$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $values['sg'][$snmp_query_array['snmp_query_id']]);

				if ($return_array !== false) {
					debug_log_insert('new_graphs', __esc('Created: %s', get_graph_title($return_array['local_graph_id'])));

					/* lastly push host-specific information to our data sources */
					if (cacti_sizeof($return_array['local_data_id'])) { # we expect at least one data source associated
						foreach($return_array['local_data_id'] as $item) {
							push_out_host($host_id, $item);
						}
					} else {
						debug_log_insert('new_graphs', __esc('ERROR: No Data Source associated. Check Template'));
					}
				} else {
					debug_log_insert('new_graphs', __esc('ERROR: Whitelist Validation Failed. Check Data Input Method'));
				}
			} else {
				$name = db_fetch_cell_prepared('SELECT name
					FROM snmp_query
					WHERE id = ?',
					array($snmp_query_array['snmp_query_id']));

				debug_log_insert('new_graphs', __esc('NOTE: Graph not added for Data Query %s and index %s due to Data Source verification failure', $name, $snmp_query_array['snmp_index']));
			}
		}
	}
}

/**
 * Handles the data source exists. Used as part of Cacti's lib functionality.
 *
 * @param int $graph_template_id The graph template ID.
 * @param int $host_id The host ID.
 * @param mixed &$data_template The data template.
 * @param mixed &$snmp_query_array The SNMP query array.
 *
 * @return mixed The result of the operation, or false on failure.
 */
function data_source_exists($graph_template_id, $host_id, &$data_template, &$snmp_query_array) {
	if (cacti_sizeof($snmp_query_array)) {
		$input_fields = db_fetch_cell_prepared('SELECT
			GROUP_CONCAT(DISTINCT snmp_field_name ORDER BY snmp_field_name) AS input_fields
			FROM snmp_query_graph_rrd
			WHERE snmp_query_graph_id = ? AND data_template_id = ?',
			array($snmp_query_array['snmp_query_graph_id'], $data_template['id']));

		$find_data_source_names = db_fetch_cell_prepared('SELECT
			GROUP_CONCAT(DISTINCT data_source_name ORDER BY data_source_name) AS data_source_names
			FROM data_template_rrd AS dtr
			INNER JOIN snmp_query_graph_rrd AS sqgr
			ON sqgr.data_template_rrd_id = dtr.id
			AND sqgr.data_template_id = dtr.data_template_id
			WHERE sqgr.snmp_query_graph_id = ?
			AND dtr.data_template_id = ?
			AND dtr.local_data_id = 0',
			array($snmp_query_array['snmp_query_graph_id'], $data_template['id']));

		// Interfaces are a special case where we can change from 32 to 64 bits on the fly
		if (strpos($input_fields, 'ifHCInOctets,ifHCOutOctets') !== false ||
			strpos($input_fields, 'ifInOctets,ifOutOctets') !== false) {
			if (read_config_option('data_source_trace') == 'on') {
				cacti_log('Data Source Exists Special Case "' . $input_fields . '"', false, 'DSTRACE');
			}

			// Counter width may change without creating a second traffic data source.
			$case_where = "AND REPLACE(REPLACE(input_fields, 'ifHCInOctets', 'ifInOctets'), 'ifHCOutOctets', 'ifOutOctets') LIKE ?";
			$case_array = array(
				$host_id,
				$data_template['id'],
				$snmp_query_array['snmp_query_id'],
				$snmp_query_array['snmp_index'],
				'%' . str_replace(array('ifHCInOctets', 'ifHCOutOctets'), array('ifInOctets', 'ifOutOctets'), $input_fields) . '%',
				$find_data_source_names
			);
		} else {
			if (read_config_option('data_source_trace') == 'on') {
				cacti_log('Data Source Exists NOT Special Case "' . $input_fields . '"', false, 'DSTRACE');
			}

			$case_where = "AND input_fields = ?";
			$case_array = array(
				$host_id,
				$data_template['id'],
				$snmp_query_array['snmp_query_id'],
				$snmp_query_array['snmp_index'],
				$input_fields,
				$find_data_source_names
			);
		}

		return db_fetch_row_prepared("SELECT dl.*,
			GROUP_CONCAT(DISTINCT snmp_field_name ORDER BY snmp_field_name) AS input_fields,
			GROUP_CONCAT(DISTINCT data_source_name ORDER BY data_source_name) AS data_source_names
			FROM data_local AS dl
			INNER JOIN data_template_data AS dtd
			ON dl.id = dtd.local_data_id
			INNER JOIN data_template_rrd AS dtr
			ON dtd.local_data_id = dtr.local_data_id
			INNER JOIN data_input_fields AS dif
			ON dif.data_input_id = dtd.data_input_id
			INNER JOIN data_input_data AS did
			ON did.data_template_data_id = dtd.id
			AND did.data_input_field_id = dif.id
			INNER JOIN snmp_query_graph_rrd AS sqgr
			ON sqgr.data_template_id = dtd.data_template_id
			AND sqgr.snmp_query_graph_id = did.value
			AND sqgr.data_template_rrd_id = dtr.local_data_template_rrd_id
			WHERE input_output = 'in'
			AND type_code = 'output_type'
			AND dl.host_id = ?
			AND dl.data_template_id = ?
			AND dl.snmp_query_id = ?
			AND dl.snmp_index = ?
			GROUP BY dtd.local_data_id
			HAVING local_data_id IS NOT NULL
			$case_where
			AND data_source_names = ?",
			$case_array
		);
	} else {
		/* create each data source, but don't duplicate */
		$data_source = db_fetch_row_prepared('SELECT dl.*
			FROM data_local AS dl
			INNER JOIN data_template_rrd AS dtr
			ON dtr.data_template_id = dl.data_template_id
			AND dtr.local_data_id = dl.id
			INNER JOIN graph_templates_item AS gti
			ON gti.task_item_id = dtr.id
			WHERE dl.host_id = ?
			AND dl.data_template_id = ?
			AND dtr.data_source_name = ?
			AND gti.graph_template_id = ?
			LIMIT 1',
			array($host_id, $data_template['id'], $data_template['data_source_name'], $graph_template_id));

		return $data_source;
	}
}

/**
 * Handles the verify data input. Used as part of Cacti's lib functionality.
 *
 * @param string $hash The hash.
 * @param string $input_string The input string.
 *
 * @return array An array of results.
 */
function verify_data_input($hash, $input_string) {
	$input = db_fetch_row_prepared('SELECT *
		FROM data_input
		WHERE hash = ?',
		array($hash));

	if ($input['input_string'] == $input_string) {
		$input['status'] = true;
		return $input;
	} else {
		$input['status'] = false;
		return $input;
	}
}

/**
 * Handles the verify data input whitelist. Used as part of Cacti's lib functionality.
 *
 * @param string $hash The hash.
 * @param string $input_string The input string.
 *
 * @return mixed The result of the operation, or false on failure.
 */
function verify_data_input_whitelist($hash, $input_string) {
	global $config;

	if (!isset($config['input_whitelist'])) {
		return true;
	} elseif (isset($config['input_whitelist']) && !file_exists($config['input_whitelist'])) {
		return true;
	}

	$whitelist = json_decode(file_get_contents($config['input_whitelist']), true);

	if (isset($whitelist[$hash])) {
		if ($input_string == $whitelist[$hash]) {
			return true;
		} else {
			return false;
		}
	} else {
		return -1;
	}
}

/**
 * Handles the graph template whitelist check. Used as part of Cacti's lib functionality.
 *
 * @param int $graph_template_id The graph template ID.
 *
 * @return bool True on success, false otherwise.
 */
function graph_template_whitelist_check($graph_template_id) {
	global $config;

	static $data_input_whitelist = null;
	static $notified = array();

	// no whitelist file defined, everything whitelisted
	if (!isset($config['input_whitelist'])) {
		return true;
	}

	// whitelist is configured but does not exist, means nothing whitelisted
	if (!file_exists($config['input_whitelist'])) {
		return false;
	}

	// load whitelist, but only once within process execution
	if ($data_input_whitelist == null) {
		$data_input_whitelist = json_decode(file_get_contents($config['input_whitelist']), true);
		if ($data_input_whitelist === null) {
			cacti_log('ERROR: Failed to parse input whitelist file: ' . $config['input_whitelist']);
			return true;
		}
	}

	$valid = true;

	$data_input_ids = db_fetch_assoc_prepared('SELECT DISTINCT dtd.data_input_id
		FROM data_template_rrd AS dtr
		INNER JOIN data_template_data AS dtd
		ON dtr.data_template_id=dtd.data_template_id
		INNER JOIN graph_templates_item AS gti
		ON gti.task_item_id=dtr.id
		WHERE gti.graph_template_id = ?
		AND gti.local_graph_id = 0',
		array($graph_template_id));

	if (cacti_sizeof($data_input_ids)) {
		foreach ($data_input_ids as $dii) {
			$found = false;
			$data_input = db_fetch_row_prepared('SELECT *
				FROM data_input
				WHERE id = ?',
				array($dii['data_input_id']));

			if (isset($data_input_whitelist[$data_input['hash']])) {
				$found = true;

				if ($data_input_whitelist[$data_input['hash']] != $data_input['input_string']) {
					$valid = false;
				}
			}

			if (!$found && !isset($notified[$dii['data_input_id']])) {
				cacti_log('WARNING: Data input script not found in input whitelist file: ' . $config['input_whitelist']);
				$notified[$dii['data_input_id']] = true;
			}
		}
	}

	return $valid;
}
