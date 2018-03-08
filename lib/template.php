<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

/* push_out_data_source_custom_data - pushes out the "custom data" associated with a data
	template to all of its children. this includes all fields inhereted from the host
	and the data template
   @arg $data_template_id - the id of the data template to push out values for */
function push_out_data_source_custom_data($data_template_id) {
	/* valid data template id? */
	if (empty($data_template_id)) {
		return 0;
	}

	/* get data_input_id from template */
	$data_template_data = db_fetch_row_prepared('SELECT id, data_input_id
		FROM data_template_data
		WHERE data_template_id = ?
		AND local_data_id=0', array($data_template_id));

	/* must be a data template */
	if (empty($data_template_data['data_input_id'])) { return 0; }

	/* get a list of data sources using this template */
	$data_sources = db_fetch_assoc_prepared('SELECT data_template_data.id
		FROM data_template_data
		WHERE data_template_id = ?
		AND local_data_id>0', array($data_template_id));

	/* pull out all custom templated 'input' values from the template itself
	 * templated items are selected by querying t_value = '' OR t_value = NULL */
	$template_input_fields = array_rekey(db_fetch_assoc_prepared('SELECT data_input_fields.id,
		data_input_fields.type_code, data_input_data.value, data_input_data.t_value
		FROM data_input_fields
		INNER JOIN data_input_data
		ON data_input_fields.id = data_input_data.data_input_field_id
		INNER JOIN data_template_data
		ON data_template_data.id = data_input_data.data_template_data_id
		WHERE (data_input_fields.input_output = "in")
		AND (data_input_data.t_value="" OR data_input_data.t_value IS NULL)
		AND (data_input_data.data_template_data_id = ?)
		AND (data_template_data.local_data_template_data_id=0)',
		array($data_template_data['id'])), 'id', array('type_code', 'value', 't_value'));

	/* which data_input_fields are templated? */
	$dif_ct = 0;
	$dif_in_str = '';
	if (sizeof($template_input_fields)) {
		$dif_in_str .= 'AND data_input_fields.id IN (';
		foreach ($template_input_fields as $key => $value) {
			$dif_in_str .= ($dif_ct == 0 ? '':',') . $key;
			$dif_ct++;
		}
		$dif_in_str .= ') ';
	}

	/* pull out all templated 'input' values from all related data sources
	 * unfortunately, you can't simply provide the same test as above
	 * all input fields not related to a template ALWAYS are marked with t_value = NULL
	 * so we will verify against the list of data_input_field id's taken from above */
	$input_fields = db_fetch_assoc("SELECT data_template_data.id AS data_template_data_id,
		data_input_fields.id, data_input_fields.type_code, data_input_data.value, data_input_data.t_value
		FROM data_input_fields
		INNER JOIN data_input_data
		ON data_input_fields.id = data_input_data.data_input_field_id
		INNER JOIN data_template_data
		ON data_template_data.id = data_input_data.data_template_data_id
		WHERE (data_input_fields.input_output = 'in')
		$dif_in_str
		AND (data_input_data.t_value='' OR data_input_data.t_value IS NULL)
		AND (data_template_data.local_data_template_data_id=" . $data_template_data['id'] . ')');

	$ds_cnt    = 0;
	$did_cnt   = 0;
	$ds_in_str = '';
	$did_vals  = '';
	if (sizeof($data_sources)) {
		foreach ($data_sources as $data_source) {
			if (sizeof($input_fields)) {
				foreach ($input_fields as $input_field) {
					if ($data_source['id'] == $input_field['data_template_data_id'] &&
						isset($template_input_fields[$input_field['id']])) {
						/* do not push out 'host fields' */
						if (!preg_match('/^' . VALID_HOST_FIELDS . '$/i', $input_field['type_code'])) {
							/* this is not a 'host field', so we should either push out the value if it is templated */
							$did_vals .= ($did_cnt == 0 ? '':',') . '(' . $input_field['id'] . ', ' . $data_source['id'] . ', ' . db_qstr($template_input_fields[$input_field['id']]['value']) . ')';
							$did_cnt++;
						} elseif ($template_input_fields[$input_field['id']]['value'] != $input_field['value']) { # templated input field deviates from currenmt data source, so update required
							$did_vals .= ($did_cnt == 0 ? '':',') . '(' . $input_field['id'] . ', ' . $data_source['id'] . ', ' . db_qstr($template_input_fields[$input_field['id']]['value']) . ')';
							$did_cnt++;
						}
					}
				}
			}

			/* create large inserts to reduce turns */
			$ds_in_str .= ($ds_cnt == 0 ? '(':',') . $data_source['id'];
			$ds_cnt++;

			/* per 1000 data source, update rows */
			if ($ds_cnt % 1000 == 0) {
				$ds_in_str .= ')';
				push_out_data_source_templates($did_vals, $ds_in_str);
				$ds_cnt    = 0;
				$did_cnt   = 0;
				$ds_in_str = '';
				$did_vals  = '';
			}
		}
	}

	if ($ds_cnt > 0) {
		$ds_in_str .= ')';
		push_out_data_source_templates($did_vals, $ds_in_str);
	}
}

/* push out changed data template fields to related data sources
 * @parm string $did_vals	- data input data fields
 * @parm string $ds_in_str	- all data sources, formatted as SQL 'IN' clause
 */
function push_out_data_source_templates($did_vals, $ds_in_str) {
	/* update all templated input fields */
	if ($did_vals != '') {
		db_execute("INSERT INTO data_input_data
			(data_input_field_id,data_template_data_id,value)
			VALUES $did_vals
			ON DUPLICATE KEY UPDATE value=VALUES(value)");
	}
}

/* push_out_data_source_item - pushes out templated data template item fields to all matching
	children
   @arg $data_template_rrd_id - the id of the data template item to push out values for */
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

/* push_out_data_source - pushes out templated data template fields to all matching children
   @arg $data_template_data_id - the id of the data template to push out values for */
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

/* change_data_template - changes the data template for a particular data source to
	$data_template_id
   @arg $local_data_id - the id of the data source to change the data template for
   @arg $data_template_id - id the of the data template to change to. specify '0' for no
   @arg $profile - a structure of data source profile attributes
	data template */
function change_data_template($local_data_id, $data_template_id, $profile = array()) {
	global $struct_data_source, $struct_data_source_item;

	/* always update tables to new data template (or no data template) */
	db_execute_prepared('UPDATE data_local
		SET data_template_id = ? WHERE id = ?',
		array($data_template_id, $local_data_id));

	/* get data about the template and the data source */
	$data = db_fetch_row_prepared('SELECT *
		FROM data_template_data
		WHERE local_data_id = ?' ,
		array($local_data_id));

	$template_data = (($data_template_id == '0') ? $data : db_fetch_row_prepared('SELECT * FROM data_template_data WHERE local_data_id=0 AND data_template_id = ?', array($data_template_id)));

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
	$save['local_data_template_data_id'] = $template_data['id'];
	$save['local_data_id']               = $local_data_id;
	$save['data_template_id']            = $data_template_id;

	/* loop through the 'templated field names' to find to the rest... */
	foreach ($struct_data_source as $field_name => $field_array) {
		/* handle the data source profile */
		if ($field_name == 'rrd_step' && sizeof($profile)) {
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
	$save['data_source_path'] = (isset($data['data_source_path']) ? $data['data_source_path']:'');;

	$data_template_data_id = sql_save($save, 'data_template_data');

	$data_rrds_list = db_fetch_assoc_prepared('SELECT *
		FROM data_template_rrd
		WHERE local_data_id = ?', array($local_data_id));

	$template_rrds_list = (($data_template_id == '0') ? $data_rrds_list : db_fetch_assoc_prepared('SELECT * FROM data_template_rrd WHERE local_data_id=0 AND data_template_id = ?', array($data_template_id)));

	if (sizeof($data_rrds_list)) {
		/* this data source already has 'child' items */
	} else {
		/* this data source does NOT have 'child' items; loop through each item in the template
		and write it exactly to each item */
		if (sizeof($template_rrds_list)) {
			foreach ($template_rrds_list as $template_rrd) {
				unset($save);

				$save['id'] = 0;
				$save['local_data_template_rrd_id'] = $template_rrd['id'];
				$save['local_data_id']              = $local_data_id;
				$save['data_template_id']           = $template_rrd['data_template_id'];

				foreach ($struct_data_source_item as $field_name => $field_array) {
					/* handle the data source profile */
					if ($field_name == 'rrd_heartbeat' && sizeof($profile)) {
						$save[$field_name] = $profile['heartbeat'];
					} else {
						$save[$field_name] = $template_rrd[$field_name];
					}
				}

				sql_save($save, 'data_template_rrd');
			}
		}
	}

	/* make sure to copy down script data (data_input_data) as well */
	$data_input_data = db_fetch_assoc_prepared('SELECT data_input_field_id, t_value, value
		FROM data_input_data
		WHERE data_template_data_id = ?', array($template_data['id']));

	/* this section is before most everthing else so we can determine if this is a new save, by checking
	the status of the 'local_data_template_data_id' column */
	if (sizeof($data_input_data)) {
		foreach ($data_input_data as $item) {
			/* always propagate on a new save, only propagate templated fields thereafter */
			if (($new_save == true) || (empty($item['t_value']))) {
				db_execute_prepared('REPLACE INTO data_input_data
					(data_input_field_id, data_template_data_id, t_value, value)
					VALUES (?, ?, ?, ?)',
					array($item['data_input_field_id'], $data_template_data_id, $item['t_value'], $item['value']));
			}
		}
	}
}

/* push_out_graph - pushes out templated graph template fields to all matching children
   @arg $graph_template_graph_id - the id of the graph template to push out values for */
function push_out_graph($graph_template_graph_id) {
	global $struct_graph;

	/* get information about this graph template */
	$graph_template_graph = db_fetch_row_prepared('SELECT *
		FROM graph_templates_graph
		WHERE id = ?', array($graph_template_graph_id));

	/* must be a graph template */
	if ($graph_template_graph['graph_template_id'] == 0) {
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
			if ($field_name == 'title') {
				update_graph_title_cache_from_template($graph_template_graph['graph_template_id']);
			}
		}
	}
}

/* push_out_graph_input - pushes out the value of a graph input to a single child item. this function
	differs from other push_out_* functions in that it does not push out the value of this element to
	all attached children. instead, it obtains the current value of the graph input based on other
	graph items and pushes out the 'active' value
   @arg $graph_template_input_id - the id of the graph input to push out values for
   @arg $graph_template_item_id - the id the graph template item to push out
   @arg $session_members - when looking for the 'active' value of the graph input, ignore these graph
	template items. typically you want to ignore all items that were just selected and have yet to be
	saved to the database. this is because these items most likely contain incorrect data */
function push_out_graph_input($graph_template_input_id, $graph_template_item_id, $session_members) {
	$graph_input = db_fetch_row_prepared('SELECT graph_template_id, column_name
		FROM graph_template_input
		WHERE id = ?', array($graph_template_input_id));

	$graph_input_items = db_fetch_assoc_prepared('SELECT graph_template_item_id
		FROM graph_template_input_defs
		WHERE graph_template_input_id = ?', array($graph_template_input_id));

	$i = 0;
	if (sizeof($graph_input_items)) {
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

	if (sizeof($session_members) == 0) {
		$values_to_apply = db_fetch_assoc('SELECT local_graph_id,' . $graph_input['column_name'] . '
			FROM graph_templates_item
			WHERE graph_template_id=' . $graph_input['graph_template_id'] . " $sql_include_items
			AND local_graph_id>0
			GROUP BY local_graph_id");
	} else {
		$i = 0;
		foreach ($session_members as $item_id => $item_id) {
			$new_session_members[$i] = $item_id;
			$i++;
		}

		$values_to_apply = db_fetch_assoc('SELECT local_graph_id,' . $graph_input['column_name'] . '
			FROM graph_templates_item
			WHERE graph_template_id=' . $graph_input['graph_template_id'] . '
			AND local_graph_id>0
			AND !(' . array_to_sql_or($new_session_members, 'local_graph_template_item_id') . ") $sql_include_items GROUP BY local_graph_id");
	}

	if (sizeof($values_to_apply)) {
		foreach ($values_to_apply as $value) {
			/* this is just an extra check that i threw in to prevent users' graphs from getting really messed up */
			if (!(($graph_input['column_name'] == 'task_item_id') && (empty($value[$graph_input['column_name']])))) {
				db_execute('UPDATE graph_templates_item
					SET ' . $graph_input['column_name'] . "=" . db_qstr($value[$graph_input['column_name']]) . "
					WHERE local_graph_id=" . $value['local_graph_id'] . "
					AND local_graph_template_item_id=$graph_template_item_id");
			}
		}
	}
}

/* push_out_graph_item - pushes out templated graph template item fields to all matching
	children. if the graph template item is part of a graph input, the field will not be
	pushed out
   @arg $graph_template_item_id - the id of the graph template item to push out values for */
function push_out_graph_item($graph_template_item_id, $local_graph_id = 0) {
	global $struct_graph_item;

	/* get information about this graph template */
	$graph_template_item = db_fetch_row_prepared('SELECT *
		FROM graph_templates_item
		WHERE id = ?', array($graph_template_item_id));

	/* must be a graph template */
	if ($graph_template_item['graph_template_id'] == 0) {
		return 0;
	}

	/* find out if any graphs actual contain this item */
	$exists = db_fetch_assoc_prepared('SELECT id
		FROM graph_templates_item
		WHERE local_graph_template_item_id = ?',
		array($graph_template_item_id));

	if (!sizeof($exists)) {
		/* if not, reapply the template to push out the new item */
		$attached_graphs = db_fetch_assoc_prepared('SELECT local_graph_id
			FROM graph_templates_graph
			WHERE graph_template_id = ?
			AND local_graph_id>0',
			array($graph_template_item['graph_template_id']));

		if (sizeof($attached_graphs)) {
			foreach ($attached_graphs as $item) {
				change_graph_template($item['local_graph_id'], $graph_template_item['graph_template_id'], true);
			}
		}
	}

	/* this is trickier with graph_items than with the actual graph... we have to make sure not to
	overwrite any items covered in the 'graph item inputs'. the same thing applies to graphs, but
	is easier to detect there (t_* columns). */
	$graph_item_inputs = db_fetch_assoc_prepared('SELECT graph_template_input.column_name,
		graph_template_input_defs.graph_template_item_id
		FROM (graph_template_input, graph_template_input_defs)
		WHERE graph_template_input.graph_template_id = ?
		AND graph_template_input.id=graph_template_input_defs.graph_template_input_id
		AND graph_template_input_defs.graph_template_item_id = ?',
		array($graph_template_item['graph_template_id'], $graph_template_item_id));

	$graph_item_inputs = array_rekey($graph_item_inputs, 'column_name', 'graph_template_item_id');

	/* loop through each graph item column name (from the above array) */
	foreach ($struct_graph_item as $field_name => $field_array) {
		/* are we allowed to push out the column? */
		if ($local_graph_id == 0) {
			if (!isset($graph_item_inputs[$field_name])) {
				db_execute_prepared("UPDATE graph_templates_item
					SET $field_name = ?
					WHERE local_graph_template_item_id = ?",
					array($graph_template_item[$field_name], $graph_template_item['id']));
			}
		} else {
			if (!isset($graph_item_inputs[$field_name])) {
				db_execute_prepared("UPDATE graph_templates_item
					SET $field_name = ?
					WHERE local_graph_template_item_id = ?
					AND local_graph_id = ?",
					array($graph_template_item[$field_name], $graph_template_item['id'], $local_graph_id));
			}
		}
	}
}

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

		if ($snmp_query_graph_id != $output_type_id) {
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

function resequence_graphs($graph_template_id, $local_graph_id = 0) {
	$template_items = db_fetch_assoc_prepared('SELECT *
		FROM graph_templates_item
		WHERE graph_template_id = ?
		AND local_graph_id = 0
		ORDER BY sequence',
		array($graph_template_id));

	if (sizeof($template_items)) {
		foreach($template_items as $item) {
			if ($local_graph_id == -1) {
				db_execute_prepared('UPDATE graph_templates_item
					SET sequence = ?
					WHERE graph_template_id = ?
					AND local_graph_template_item_id = ?',
					array($item['sequence'], $graph_template_id, $item['id']));
			} elseif ($local_graph_id == 0) {
				db_execute_prepared('UPDATE graph_templates_item
					SET sequence = ?
					WHERE graph_template_id = ?
					AND local_graph_id > 0
					AND local_graph_template_item_id = ?',
					array($item['sequence'], $graph_template_id, $item['id']));
			} else {
				db_execute_prepared('UPDATE graph_templates_item
					SET sequence = ?
					WHERE graph_template_id = ?
					AND local_graph_id = ?
					AND local_graph_template_item_id = ?',
					array($item['sequence'], $graph_template_id, $local_graph_id, $item['id']));
			}
		}
	}
}

/* retemplate_graphs - reapply the graph template as it currently exists to all
    graphs using that template.  This is important when you have graphs that
    have multiple versions of a template.
   @arg $graph_template_id - the graph template id to retemplate
   @arg $local_graph_id - optional local graph id */
function retemplate_graphs($graph_template_id, $local_graph_id = 0) {
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

	if (sizeof($graphs)) {
		foreach($graphs as $graph) {
			change_graph_template($graph['id'], $graph_template_id, true);
		}
	}
}

/* change_graph_template - changes the graph template for a particular graph to
	$graph_template_id
   @arg $local_graph_id - the id of the graph to change the graph template for
   @arg $graph_template_id - id the of the graph template to change to. specify '0' for no
	graph template
   @arg $intrusive - (true) if the target graph template has more or less graph items than
	the current graph, remove or add the items from the current graph to make them equal.
	(false) leave the graph item count alone */
function change_graph_template($local_graph_id, $graph_template_id, $intrusive = true) {
	global $struct_graph, $struct_graph_item;

	$template_data     = parse_graph_template_id($graph_template_id);
	$graph_template_id = $template_data['graph_template_id'];

	if (isset($template_data['output_type_id'])) {
		$output_type_id = $template_data['output_type_id'];
	} else {
		$output_type_id = 0;
	}

	/* get information about both the graph and the graph template we're using */
	$graph_list = db_fetch_row_prepared('SELECT *
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array($local_graph_id));

	$snmp_query_id = db_fetch_cell_prepared('SELECT snmp_query_id
		FROM graph_local
		WHERE id = ?',
		array($local_graph_id));

	/* always update tables to new graph template (or no graph template) */
	db_execute_prepared('UPDATE graph_local
		SET graph_template_id = ?
		WHERE id = ?',
		array($graph_template_id, $local_graph_id));

	if ($output_type_id > 0) {
		$changed = true;
	}else if (sizeof($graph_list) && $graph_template_id != $graph_list['graph_template_id']) {
		$changed = true;
	} else {
		$changed = false;
	}

	if ($graph_template_id == 0) {
		$template_graph_list = $graph_list;
	} else {
		$template_graph_list = db_fetch_row_prepared('SELECT *
			FROM graph_templates_graph
			WHERE local_graph_id = 0
			AND graph_template_id = ?',
			array($graph_template_id));
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
		ORDER BY sequence',
		array($local_graph_id));

	if ($graph_template_id == 0) {
		$template_items_list = $graph_items_list;
	} else {
		$template_items_list = db_fetch_assoc_prepared('SELECT *
			FROM graph_templates_item
			WHERE local_graph_id=0
			AND graph_template_id = ?
			ORDER BY sequence',
			array($graph_template_id));
	}

	$graph_template_inputs = db_fetch_assoc_prepared('SELECT
		gti.column_name, gtid.graph_template_item_id
		FROM graph_template_input AS gti
		INNER JOIN graph_template_input_defs AS gtid
		ON gti.id=gtid.graph_template_input_id
		AND gti.graph_template_id = ?',
		array($graph_template_id));

	$cols = db_get_table_column_types('graph_templates_item');

	$k=0;
	if (sizeof($template_items_list)) {
		foreach ($template_items_list as $template_item) {
			unset($save);

			$save['local_graph_template_item_id'] = $template_item['id'];
			$save['local_graph_id']               = $local_graph_id;
			$save['graph_template_id']            = $template_item['graph_template_id'];
			$save['sequence']                     = $template_item['sequence'];

			/* go through the existing graph_items and look for the matching local_graph_template_item_id */
			$found = false;
			if (sizeof($graph_items_list) && $new_save == false) {
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

				/* attempt to discover the task_item_id */
				$local_data_ids = db_fetch_cell_prepared('SELECT
					GROUP_CONCAT(DISTINCT local_data_id) AS ids
					FROM data_template_rrd
					WHERE id IN (
						SELECT DISTINCT task_item_id
						FROM graph_templates_item
						WHERE local_graph_id = ?)',
					array($local_graph_id));

				$data_source_name = db_fetch_cell_prepared('SELECT data_source_name
					FROM data_template_rrd WHERE id = ?',
					array($template_item['task_item_id']));

				if ($data_source_name != '' && $local_data_ids != '') {
					$task_item_id = db_fetch_cell_prepared('SELECT DISTINCT id
						FROM data_template_rrd WHERE
						local_data_id IN (' . $local_data_ids . ')
						AND data_source_name = ?
						LIMIT 1', array($data_source_name));

					if (!empty($task_item_id)) {
						$save['task_item_id'] = $task_item_id;
					} else {
						$save['task_item_id'] = 0;
					}
				} else {
					$save['task_item_id'] = 0;
				}

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

	/* if there are more graph items than there are items in the template, delete the difference */
	/* we have probably modified 'graph_templates_item' so we need to recalculate the number of
	   items before checking them */
	$graph_items_list = db_fetch_assoc_prepared('SELECT *
		FROM graph_templates_item
		WHERE local_graph_id = ?
		ORDER BY sequence',
		array($local_graph_id));

	if ($new_save == false && sizeof($graph_items_list) > sizeof($template_items_list)) {
		foreach($template_items_list as $item) {
			$ids[] = $item['id'];
		}

		db_execute('DELETE FROM graph_templates_item
			WHERE local_graph_template_item_id NOT IN (' . implode(',', $ids) . ')
			AND local_graph_id = ' . $local_graph_id);
	}

	if ($new_save == false) {
		resequence_graphs($graph_template_id, $local_graph_id);
	}

	/* handle changes in data template if there are any */
	if ($new_save == false && $changed && $snmp_query_id > 0) {
		update_graph_data_source_output_type($local_graph_id, $output_type_id);
	}

	return true;
}

/* graph_to_graph_template - converts a graph to a graph template
   @arg $local_graph_id - the id of the graph to be converted
   @arg $graph_title - the graph title to use for the new graph template. the variable
	<graph_title> will be substituted for the current graph title */
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

	db_execute_pepared('UPDATE graph_templates_item
		SET local_graph_id=0, local_graph_template_item_id=0, graph_template_id = ?, task_item_id=0
		WHERE local_graph_id = ?',
		array($graph_template_id, $local_graph_id));

	/* create hashes for the graph template items */
	$items = db_fetch_assoc_prepared('SELECT id
		FROM graph_templates_item
		WHERE graph_template_id = ?
		AND local_graph_id=0',
		array($graph_template_id));

	for ($j=0; $j<count($items); $j++) {
		db_execute_prepared('UPDATE graph_templates_item
			SET hash = ? WHERE id= ?',
			array(get_hash_graph_template($items[$j]['id'], 'graph_template_item'), $items[$j]['id']));
	}

	/* delete the old graph local entry */
	db_execute_prepared('DELETE FROM graph_local WHERE id = ?', array($local_graph_id));
	db_execute_prepared('DELETE FROM graph_tree_items WHERE local_graph_id = ?', array($local_graph_id));
}

/* data_source_to_data_template - converts a data source to a data template
   @arg $local_data_id - the id of the data source to be converted
   @arg $data_source_title - the data source title to use for the new data template. the variable
	<ds_title> will be substituted for the current data source title */
function data_source_to_data_template($local_data_id, $data_source_title) {
	/* create a new graph template entry */
	$title_template = db_fetch_cell_prepared('SELECT name
		FROM data_template_data
		WHERE local_data_id = ?',
		array($local_data_id));

	$title = str_replace('<ds_title>', $title_template, $data_source_title);

	db_execute('INSERT INTO data_template
		(id,name,hash)
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

	for ($j=0; $j<count($items); $j++) {
		db_execute_prepared('UPDATE data_template_rrd
			SET hash = ? WHERE id =?',
			array(get_hash_data_template($items[$j]['id'], 'data_template_item'), $items[$j]['id']));
	}

	/* delete the old graph local entry */
	db_execute_prepared('DELETE FROM data_local WHERE id = ?', array($local_data_id));
	db_execute_prepared('DELETE FROM poller_item WHERE local_data_id= ?', array($local_data_id));
}

/* create_complete_graph_from_template - creates a graph and all necessary data sources based on a
	graph template
   @arg $graph_template_id - the id of the graph template that will be used to create the new
	graph
   @arg $host_id - the id of the host to associate the new graph and data sources with
   @arg $snmp_query_array - if the new data sources are to be based on a data query, specify the
	necessary data query information here. it must contain the following information:
	  $snmp_query_array['snmp_query_id']
	  $snmp_query_array['snmp_index_on']
	  $snmp_query_array['snmp_query_graph_id']
	  $snmp_query_array['snmp_index']
   @arg $sugested_vals - any additional information to be included in the new graphs or
	data sources must be included in the array. data is to be included in the following format:
	  $values['cg'][graph_template_id]['graph_template'][field_name] = $value  // graph template
	  $values['cg'][graph_template_id]['graph_template_item'][graph_template_item_id][field_name] = $value  // graph template item
	  $values['cg'][data_template_id]['data_template'][field_name] = $value  // data template
	  $values['cg'][data_template_id]['data_template_item'][data_template_item_id][field_name] = $value  // data template item
	  $values['sg'][data_query_id][graph_template_id]['graph_template'][field_name] = $value  // graph template (w/ data query)
	  $values['sg'][data_query_id][graph_template_id]['graph_template_item'][graph_template_item_id][field_name] = $value  // graph template item (w/ data query)
	  $values['sg'][data_query_id][data_template_id]['data_template'][field_name] = $value  // data template (w/ data query)
	  $values['sg'][data_query_id][data_template_id]['data_template_item'][data_template_item_id][field_name] = $value  // data template item (w/ data query) */
function create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, &$suggested_vals) {
	global $config;

	include_once($config['library_path'] . '/data_query.php');

	/* create the graph */
	$save['id']                  = 0;
	$save['graph_template_id']   = $graph_template_id;
	$save['host_id']             = $host_id;

	/* defaults for non-snmp query based */
	$save['snmp_query_id']       = 0;
	$save['snmp_query_graph_id'] = 0;
	$save['snmp_index']          = '';

	if (sizeof($snmp_query_array)) {
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

	/* apply graph items */
	change_graph_template($cache_array['local_graph_id'], $graph_template_id, true);

	/* perform graph replacement based upon suggested values */
	if (sizeof($snmp_query_array)) {
		/* suggested values for snmp query code */
		$suggested_values = db_fetch_assoc_prepared('SELECT text, field_name
			FROM snmp_query_graph_sv
			WHERE snmp_query_graph_id = ?
			ORDER BY sequence',
			array($snmp_query_array['snmp_query_graph_id']));

		$suggested_values_graph = array();
		if (sizeof($suggested_values)) {
			foreach ($suggested_values as $suggested_value) {
				/* once we find a match; don't try to find more */
				if (!isset($suggested_values_graph[$graph_template_id][$suggested_value['field_name']])) {
					$subs_string = substitute_snmp_query_data($suggested_value['text'], $host_id,
						$snmp_query_array['snmp_query_id'], $snmp_query_array['snmp_index'],
						read_config_option('max_data_query_field_length'));

					/* if there are no '|' characters, all of the substitutions were successful */
					if (!strstr($subs_string, '|query')) {
						if (db_column_exists('graph_templates_graph', $suggested_value['field_name'])) {
							db_execute_prepared('UPDATE graph_templates_graph
								SET ' . $suggested_value['field_name'] . ' = ?
								WHERE local_graph_id = ?',
								array($suggested_value['text'], $cache_array['local_graph_id']));

							/* once we find a working value, stop */
							$suggested_values_graph[$graph_template_id][$suggested_value['field_name']] = true;
						} else {
							cacti_log('ERROR: Suggested value column error.  Column ' . $suggested_value['field_name'] . ' for Data Query ID ' . $snmp_query_array['snmp_query_id'] . ' and Graph Template ID ' . $graph_template_id .  ' is not a compatible field name.  Please correct this suggested value mapping', false);
						}
					}
				}
			}
		}
	}

	/* suggested values: graph */
	if (isset($suggested_vals[$graph_template_id]['graph_template'])) {
		foreach ($suggested_vals[$graph_template_id]['graph_template'] as $field_name => $field_value) {
			db_execute_prepared('UPDATE graph_templates_graph
				SET ' . $field_name . ' = ?
				WHERE local_graph_id= ?',
				array($field_value, $cache_array['local_graph_id']));
		}
	}

	/* suggested values: graph item */
	if (isset($suggested_vals[$graph_template_id]['graph_template_item'])) {
		foreach ($suggested_vals[$graph_template_id]['graph_template_item'] as $graph_template_item_id => $field_array) {
			foreach ($field_array as $field_name => $field_value) {
				$graph_item_id = db_fetch_cell_prepared('SELECT id
					FROM graph_templates_item
					WHERE local_graph_template_item_id = ?
					AND local_graph_id = ?',
					array($graph_template_item_id, $cache_array['local_graph_id']));

				db_execute_prepared('UPDATE graph_templates_item
					SET ' . $field_name . ' = ?
					WHERE id = ?',
					array($field_value, $graph_item_id));
			}
		}
	}

	update_graph_title_cache($cache_array['local_graph_id']);

	/* create each data source, but don't duplicate */
	$data_templates = db_fetch_assoc_prepared('SELECT dt.id, dt.name, dtr.data_source_name
		FROM data_template AS dt
		INNER JOIN data_template_rrd AS dtr
		ON dtr.data_template_id=dt.id
		INNER JOIN graph_templates_item AS gti
		ON gti.task_item_id=dtr.id
		WHERE dtr.local_data_id=0
		AND gti.local_graph_id=0
		AND gti.graph_template_id = ?
		GROUP BY dt.id
		ORDER BY dt.name',
		array($graph_template_id));

	if (sizeof($data_templates)) {
		foreach ($data_templates as $data_template) {
			/* check if the data source already exists */
			$previous_data_source = data_source_exists($graph_template_id, $host_id, $data_template, $snmp_query_array);

			if (sizeof($previous_data_source)) {
				$cache_array['local_data_id'][$data_template['id']] = $previous_data_source['id'];
			} else {
				unset($save);

				$save['id']               = 0;
				$save['data_template_id'] = $data_template['id'];
				$save['host_id']          = $host_id;

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
					if (!sizeof($profile)) {
						$profile = db_fetch_row('SELECT *
							FROM data_source_profiles
							ORDER BY `default`
							DESC LIMIT 1');
					}
				} else {
					$profile_id = 0;
					$profile    = array();
				}

				$cache_array['local_data_id'][$data_template['id']] = sql_save($save, 'data_local');

				change_data_template($cache_array['local_data_id'][$data_template['id']], $data_template['id'], $profile);

				$data_template_data_id = db_fetch_cell_prepared('SELECT id
					FROM data_template_data
					WHERE local_data_id = ?',
					array($cache_array['local_data_id'][$data_template['id']]));

				if (sizeof($snmp_query_array)) {
					/* suggested values for snmp query code */
					$suggested_values = db_fetch_assoc_prepared('SELECT text, field_name
						FROM snmp_query_graph_rrd_sv
						WHERE snmp_query_graph_id = ?
						AND data_template_id = ?
						ORDER BY sequence',
						array($snmp_query_array['snmp_query_graph_id'], $data_template['id']));

					$suggested_values_ds = array();
					if (sizeof($suggested_values)) {
						foreach ($suggested_values as $suggested_value) {
							/* once we find a match; don't try to find more */
							if (!isset($suggested_values_ds[$data_template['id']][$suggested_value['field_name']])) {
								$subs_string = substitute_snmp_query_data($suggested_value['text'], $host_id,
									$snmp_query_array['snmp_query_id'],
									$snmp_query_array['snmp_index'], read_config_option('max_data_query_field_length'));

								/* if there are no '|' characters, all of the substitutions were successful */
								if (!strstr($subs_string, '|query')) {
									$columns = db_fetch_row("SHOW COLUMNS
										FROM data_template_data
										LIKE '" . $suggested_value['field_name'] . "'");

									if (sizeof($columns)) {
										db_execute_prepared('UPDATE data_template_data
											SET ' . $suggested_value['field_name'] . ' = ?
											WHERE local_data_id = ?',
											array($suggested_value['text'], $cache_array['local_data_id'][$data_template['id']]));
									}

									/* once we find a working value, stop */
									$suggested_values_ds[$data_template['id']][$suggested_value['field_name']] = true;

									$columns = db_fetch_row("SHOW COLUMNS
										FROM data_template_rrd
										LIKE '" . $suggested_value['field_name'] . "'");

									if (sizeof($columns) && !substr_count($subs_string, '|')) {
										db_execute_prepared('UPDATE data_template_rrd
											SET ' . $suggested_value['field_name'] . ' = ?
											WHERE local_data_id = ?',
											array($suggested_value['text'], $cache_array['local_data_id'][$data_template['id']]));
									}
								}
							}
						}
					}
				}

				if (sizeof($snmp_query_array)) {
					$data_input_field = array_rekey(db_fetch_assoc_prepared('SELECT dif.id, dif.type_code
						FROM snmp_query AS sq
						INNER JOIN data_input AS di
						ON sq.data_input_id=di.id
						INNER JOIN data_input_fields AS dif
						ON di.id=dif.data_input_id
						WHERE (dif.type_code="index_type" OR dif.type_code="index_value" OR dif.type_code="output_type")
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

					/* save the value to index on (ie. ifindex, ifip, etc) */
					db_execute_prepared('REPLACE INTO data_input_data
						(data_input_field_id, data_template_data_id, t_value, value)
						VALUES (?, ?, "", ?)',
						array($data_input_field['index_type'], $data_template_data_id, $snmp_query_array['snmp_index_on']));

					/* save the actual value (ie. 3, 192.168.1.101, etc) */
					db_execute_prepared('REPLACE INTO data_input_data
						(data_input_field_id,data_template_data_id,t_value,value)
						VALUES (?, ?, "", ?)',
						array($data_input_field['index_value'], $data_template_data_id, $snmp_cache_value));

					/* set the expected output type (ie. bytes, errors, packets) */
					db_execute_prepared('REPLACE INTO data_input_data
						(data_input_field_id,data_template_data_id,t_value,value)
						VALUES (?, ?, "", ?)',
						array($data_input_field['output_type'], $data_template_data_id, $snmp_query_array['snmp_query_graph_id']));

					/* now that we have put data into the 'data_input_data' table, update the snmp cache for ds's */
					update_data_source_data_query_cache($cache_array['local_data_id'][$data_template['id']]);
				}

				/* suggested values: data source */
				if (isset($suggested_vals[$graph_template_id]['data_template'][$data_template['id']])) {
					foreach ($suggested_vals[$graph_template_id]['data_template'][$data_template['id']] as $field_name => $field_value) {
						db_execute_prepared("UPDATE data_template_data
							SET $field_name = ?
							WHERE local_data_id = ?",
							array($field_value, $cache_array['local_data_id'][$data_template['id']]));
					}
				}

				/* suggested values: data source item */
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

				/* suggested values: custom data */
				if (isset($suggested_vals[$graph_template_id]['custom_data'][$data_template['id']])) {
					foreach ($suggested_vals[$graph_template_id]['custom_data'][$data_template['id']] as $data_input_field_id => $field_value) {
						db_execute_prepared('REPLACE INTO data_input_data
							(data_input_field_id, data_template_data_id, t_value, value)
							VALUES (?, ?, "", ?)',
							array($data_input_field_id, $data_template_data_id, $field_value));
					}
				}

				update_data_source_title_cache($cache_array['local_data_id'][$data_template['id']]);
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
	if (sizeof($template_item_list)) {
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

				if (!empty($data_template_rrd_id)) {
					db_execute_prepared('UPDATE graph_templates_item
						SET task_item_id = ?
						WHERE id = ?',
						array($data_template_rrd_id, $graph_template_item_id));
				}
			}
		}
	}

	/* this will not work until the ds->graph dots are connected */
	if (sizeof($snmp_query_array)) {
		if (isset($cache_array['local_graph_id'])) {
			update_graph_data_query_cache($cache_array['local_graph_id']);
		}
	}

	/* now that we have the id of the new host, we may plugin postprocessing code */
	if (isset($cache_array['local_graph_id'])) {
		$save['id']                = $cache_array['local_graph_id'];
		$save['graph_template_id'] = $graph_template_id;	// attention: unset!

		if (sizeof($snmp_query_array)) {
			$save['snmp_query_id']       = $snmp_query_array['snmp_query_id'];
			$save['snmp_index']          = $snmp_query_array['snmp_index'];
			$save['snmp_query_graph_id'] = $snmp_query_array['snmp_query_graph_id'];
		} else {
			$save['snmp_query_id']       = 0;
			$save['snmp_index']          = 0;
			$save['snmp_query_graph_id'] = 0;
		}

		/* provide automation services */
		automation_hook_graph_create_tree($save);

		api_plugin_hook_function('create_complete_graph_from_template', $save);
	}

	return $cache_array;
}

function data_source_exists($graph_template_id, $host_id, &$data_template, &$snmp_query_array) {
	if (sizeof($snmp_query_array)) {
		$input_fields = db_fetch_cell_prepared('SELECT GROUP_CONCAT(DISTINCT snmp_field_name ORDER BY snmp_field_name) AS input_fields
			FROM snmp_query_graph_rrd
			WHERE snmp_query_graph_id = ?',
			array($snmp_query_array['snmp_query_graph_id']));

		return db_fetch_row_prepared('SELECT dl.*,
			GROUP_CONCAT(DISTINCT snmp_field_name ORDER BY snmp_field_name) AS input_fields
			FROM data_local AS dl
			INNER JOIN data_template_data AS dtd
			ON dl.id=dtd.local_data_id
			INNER JOIN data_input_fields AS dif
			ON dif.data_input_id=dtd.data_input_id
			INNER JOIN snmp_query_graph_rrd AS sqgr
			ON sqgr.data_template_id=dtd.data_template_id
			WHERE input_output = "in"
			AND type_code="output_type"
			AND dl.host_id = ?
			AND dl.data_template_id = ?
			AND dl.snmp_query_id = ?
			AND dl.snmp_index = ?
			GROUP BY dtd.local_data_id
			HAVING local_data_id IS NOT NULL AND input_fields = ?',
			array($host_id, $data_template['id'],
				$snmp_query_array['snmp_query_id'], $snmp_query_array['snmp_index'],
				$input_fields));
	} else {
		return array();
	}
}

