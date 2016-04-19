<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

function repopulate_poller_cache() {
	$poller_data    = db_fetch_assoc('SELECT ' . SQL_NO_CACHE . ' * FROM data_local');
	$poller_items   = array();
	$local_data_ids = array();
	$i = 0;

	if (sizeof($poller_data)) {
		foreach ($poller_data as $data) {
			$poller_items     = array_merge($poller_items, update_poller_cache($data));
			$local_data_ids[] = $data['id'];
			$i++;

			if ($i > 500) {
				$i = 0;
				poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
				$local_data_ids = array();
				$poller_items   = array();
			}
		}

		if ($i > 0) {
			poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
		}
	}
}

function update_poller_cache_from_query($host_id, $data_query_id) {
	$poller_data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' * FROM data_local 
		WHERE host_id = ?  AND snmp_query_id = ?', array($host_id, $data_query_id));

	$i = 0;
	$poller_items = $local_data_ids = array();

	if (sizeof($poller_data)) {
		foreach ($poller_data as $data) {
			$poller_items     = array_merge($poller_items, update_poller_cache($data));
			$local_data_ids[] = $data['id'];
			$i++;

			if ($i > 500) {
				$i = 0;
				poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
				$local_data_ids = array();
				$poller_items   = array();
			}
		}

		if ($i > 0) {
			poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
		}
	}
}

function update_poller_cache($data_source, $commit = false) {
	global $config;

	include_once($config['library_path'] . '/data_query.php');
	include_once($config['library_path'] . '/api_poller.php');

	if (!is_array($data_source)) {
		$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' * FROM data_local WHERE id = ?', array($data_source));
	}

	$poller_items = array();

	$data_input = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . '
		di.id, di.type_id, dtd.id AS data_template_data_id,
		dtd.data_template_id, dtd.active, dtd.rrd_step
		FROM data_template_data AS dtd
		INNER JOIN data_input AS di
		ON dtd.data_input_id=di.id
		WHERE dtd.local_data_id = ?', array($data_source['id']));

	/* we have to perform some additional sql queries if this is a 'query' */
	if (($data_input['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY) ||
		($data_input['type_id'] == DATA_INPUT_TYPE_SCRIPT_QUERY) ||
		($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER)){
		$field = data_query_field_list($data_input['data_template_data_id']);

		$params = array();
		if (strlen($field['output_type'])) {
			$output_type_sql = ' AND snmp_query_graph_rrd.snmp_query_graph_id = ?';
			$params[] = $field['output_type'];
		}else{
			$output_type_sql = '';
		}
		$params[] = $data_input['data_template_id'];
		$params[] = $data_source['id'];

		$outputs = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . "
			snmp_query_graph_rrd.snmp_field_name,
			data_template_rrd.id as data_template_rrd_id
			FROM (snmp_query_graph_rrd,data_template_rrd)
			WHERE snmp_query_graph_rrd.data_template_rrd_id = data_template_rrd.local_data_template_rrd_id
			$output_type_sql
			AND snmp_query_graph_rrd.data_template_id = ?
			AND data_template_rrd.local_data_id = ?
			ORDER BY data_template_rrd.id", $params);
	}

	if ($data_input['active'] == 'on') {
		if (($data_input['type_id'] == DATA_INPUT_TYPE_SCRIPT) || ($data_input['type_id'] == DATA_INPUT_TYPE_PHP_SCRIPT_SERVER)) { /* script */
			/* fall back to non-script server actions if the user is running a version of php older than 4.3 */
			if (($data_input['type_id'] == DATA_INPUT_TYPE_PHP_SCRIPT_SERVER) && (function_exists('proc_open'))) {
				$action = POLLER_ACTION_SCRIPT_PHP;
				$script_path = get_full_script_path($data_source['id']);
			}else if (($data_input['type_id'] == DATA_INPUT_TYPE_PHP_SCRIPT_SERVER) && (!function_exists('proc_open'))) {
				$action = POLLER_ACTION_SCRIPT;
				$script_path = read_config_option('path_php_binary') . ' -q ' . get_full_script_path($data_source['id']);
			}else{
				$action = POLLER_ACTION_SCRIPT;
				$script_path = get_full_script_path($data_source['id']);
			}

			$num_output_fields = sizeof(db_fetch_assoc('SELECT ' . SQL_NO_CACHE . ' id FROM data_input_fields WHERE data_input_id=' . $data_input['id'] . " AND input_output='out' AND update_rra='on'"));

			if ($num_output_fields == 1) {
				$data_template_rrd_id = db_fetch_cell('SELECT ' . SQL_NO_CACHE . ' id FROM data_template_rrd WHERE local_data_id=' . $data_source['id']);
				$data_source_item_name = get_data_source_item_name($data_template_rrd_id);
			}else{
				$data_source_item_name = '';
			}

			$poller_items[] = api_poller_cache_item_add($data_source['host_id'], array(), $data_source['id'], $data_input['rrd_step'], $action, $data_source_item_name, 1, $script_path);
		}else if ($data_input['type_id'] == DATA_INPUT_TYPE_SNMP) { /* snmp */
			/* get the host override fields */
			$data_template_id = db_fetch_cell('SELECT ' . SQL_NO_CACHE . ' data_template_id FROM data_template_data WHERE local_data_id=' . $data_source['id']);

			/* get host fields first */
			$host_fields = array_rekey(db_fetch_assoc('SELECT ' . SQL_NO_CACHE . " dif.type_code, did.value
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id=did.data_input_field_id
				WHERE (type_code LIKE 'snmp_%' OR type_code IN('hostname','host_id'))
				AND did.data_template_data_id=" . $data_input['data_template_data_id'] . "
				AND did.value != ''"), 'type_code', 'value');

			$data_template_fields = array_rekey(db_fetch_assoc('SELECT ' . SQL_NO_CACHE . " dif.type_code, did.value
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id=did.data_input_field_id
				WHERE (type_code LIKE 'snmp_%' OR type_code='hostname')
				AND did.data_template_data_id=$data_template_id
				AND data_template_data_id=$data_template_id
				AND did.value != ''"), 'type_code', 'value');

			if (sizeof($host_fields)) {
				if (sizeof($data_template_fields)) {
				foreach($data_template_fields as $key => $value) {
					if (!isset($host_fields[$key])) {
						$host_fields[$key] = $value;
					}
				}
				}
			} elseif (sizeof($data_template_fields)) {
				$host_fields = $data_template_fields;
			}

			$data_template_rrd_id = db_fetch_cell('SELECT ' . SQL_NO_CACHE . ' id FROM data_template_rrd WHERE local_data_id=' . $data_source['id']);

			$poller_items[] = api_poller_cache_item_add($data_source['host_id'], $host_fields, $data_source['id'], $data_input['rrd_step'], 0, get_data_source_item_name($data_template_rrd_id), 1, (isset($host_fields['snmp_oid']) ? $host_fields['snmp_oid'] : ''));
		}else if ($data_input['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY) { /* snmp query */
			$snmp_queries = get_data_query_array($data_source['snmp_query_id']);

			/* get the host override fields */
			$data_template_id = db_fetch_cell('SELECT ' . SQL_NO_CACHE . ' data_template_id FROM data_template_data WHERE local_data_id=' . $data_source['id']);

			/* get host fields first */
			$host_fields = array_rekey(db_fetch_assoc('SELECT ' . SQL_NO_CACHE . " dif.type_code, did.value
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id=did.data_input_field_id
				WHERE (type_code LIKE 'snmp_%' OR type_code='hostname')
				AND did.data_template_data_id=" . $data_input['data_template_data_id'] . "
				AND did.value != ''"), 'type_code', 'value');

			$data_template_fields = array_rekey(db_fetch_assoc('SELECT ' . SQL_NO_CACHE . " dif.type_code, did.value
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id=did.data_input_field_id
				WHERE (type_code LIKE 'snmp_%' OR type_code='hostname')
				AND did.data_template_data_id=$data_template_id
				AND data_template_data_id=$data_template_id
				AND did.value != ''"), 'type_code', 'value');

			if (sizeof($host_fields)) {
				if (sizeof($data_template_fields)) {
				foreach($data_template_fields as $key => $value) {
					if (!isset($host_fields[$key])) {
						$host_fields[$key] = $value;
					}
				}
				}
			} elseif (sizeof($data_template_fields)) {
				$host_fields = $data_template_fields;
			}

			if (sizeof($outputs) > 0) {
			foreach ($outputs as $output) {
				if (isset($snmp_queries['fields']{$output['snmp_field_name']}['oid'])) {
					$oid = $snmp_queries['fields']{$output['snmp_field_name']}['oid'] . '.' . $data_source['snmp_index'];

					if (isset($snmp_queries['fields']{$output['snmp_field_name']}['oid_suffix'])) {
						$oid .= '.' . $snmp_queries['fields']{$output['snmp_field_name']}['oid_suffix'];
					}
				}

				if (!empty($oid)) {
					$poller_items[] = api_poller_cache_item_add($data_source['host_id'], $host_fields, $data_source['id'], $data_input['rrd_step'], 0, get_data_source_item_name($output['data_template_rrd_id']), sizeof($outputs), $oid);
				}
			}
			}
		}else if (($data_input['type_id'] == DATA_INPUT_TYPE_SCRIPT_QUERY) || ($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER)) { /* script query */
			$script_queries = get_data_query_array($data_source['snmp_query_id']);

			/* get the host override fields */
			$data_template_id = db_fetch_cell('SELECT ' . SQL_NO_CACHE . ' data_template_id FROM data_template_data WHERE local_data_id=' . $data_source['id']);

			/* get host fields first */
			$host_fields = array_rekey(db_fetch_assoc('SELECT ' . SQL_NO_CACHE . " dif.type_code, did.value
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id=did.data_input_field_id
				WHERE (type_code LIKE 'snmp_%' OR type_code='hostname')
				AND did.data_template_data_id=" . $data_input['data_template_data_id'] . "
				AND did.value != ''"), 'type_code', 'value');

			$data_template_fields = array_rekey(db_fetch_assoc('SELECT ' . SQL_NO_CACHE . " dif.type_code, did.value
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id=did.data_input_field_id
				WHERE (type_code LIKE 'snmp_%' OR type_code='hostname')
				AND data_template_data_id=$data_template_id
				AND did.data_template_data_id=$data_template_id
				AND did.value != ''"), 'type_code', 'value');

			if (sizeof($host_fields)) {
				if (sizeof($data_template_fields)) {
				foreach($data_template_fields as $key => $value) {
					if (!isset($host_fields[$key])) {
						$host_fields[$key] = $value;
					}
				}
				}
			} elseif (sizeof($data_template_fields)) {
				$host_fields = $data_template_fields;
			}

			if (sizeof($outputs) > 0) {
				foreach ($outputs as $output) {
					if (isset($script_queries['fields']{$output['snmp_field_name']}['query_name'])) {
						$identifier = $script_queries['fields']{$output['snmp_field_name']}['query_name'];

						/* fall back to non-script server actions if the user is running a version of php older than 4.3 */
						if (($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER) && (function_exists('proc_open'))) {
							$action = POLLER_ACTION_SCRIPT_PHP;
							$script_path = get_script_query_path((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] : '') . ' ' . $script_queries['arg_get'] . ' ' . $identifier . ' ' . $data_source['snmp_index'], $script_queries['script_path'] . ' ' . $script_queries['script_function'], $data_source['host_id']);
						}else if (($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER) && (!function_exists('proc_open'))) {
							$action = POLLER_ACTION_SCRIPT;
							$script_path = read_config_option('path_php_binary') . ' -q ' . get_script_query_path((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] : '') . ' ' . $script_queries['arg_get'] . ' ' . $identifier . ' ' . $data_source['snmp_index'], $script_queries['script_path'], $data_source['host_id']);
						}else{
							$action = POLLER_ACTION_SCRIPT;
							$script_path = get_script_query_path((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] : '') . ' ' . $script_queries['arg_get'] . ' ' . $identifier . ' ' . $data_source['snmp_index'], $script_queries['script_path'], $data_source['host_id']);
						}
					}

					if (isset($script_path)) {
						$poller_items[] = api_poller_cache_item_add($data_source['host_id'], $host_fields, $data_source['id'], $data_input['rrd_step'], $action, get_data_source_item_name($output['data_template_rrd_id']), sizeof($outputs), $script_path);
					}
				}
			}
		}
	}

	if ($commit) {
		poller_update_poller_cache_from_buffer((array)$data_source['id'], $poller_items);
	} else {
		return $poller_items;
	}
}

function push_out_data_input_method($data_input_id) {
	$data_sources = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' * FROM data_local
		WHERE id IN (
			SELECT DISTINCT local_data_id
			FROM data_template_data
			WHERE data_input_id = ?
			AND local_data_id>0
		)', array($data_input_id));

	$poller_items = array();
	$_my_local_data_ids = array();

	if (sizeof($data_sources)) {
		foreach($data_sources as $data_source) {
			$_my_local_data_ids[] = $data_source['local_data_id'];

			$poller_items = array_merge($poller_items, update_poller_cache($data_source));
		}

		if (sizeof($_my_local_data_ids)) {
			poller_update_poller_cache_from_buffer($_my_local_data_ids, $poller_items);
		}
	}
}
/** mass update of poller cache - can run in parallel to poller
 * @param array/int $local_data_ids - either a scalar (all ids) or an array of data source to act on
 * @param array $poller_items - the new items for poller cache
 */

function poller_update_poller_cache_from_buffer($local_data_ids, &$poller_items) {
	/* set all fields present value to 0, to mark the outliers when we are all done */
	$ids = array();
	if (sizeof($local_data_ids)) {
		$count = 0;
		foreach($local_data_ids as $id) {
			if ($count == 0) {
				$ids = $id;
			} else {
				$ids .= ', ' . $id;
			}
			$count++;
		}

		if ($ids != '') {
			db_execute("UPDATE poller_item SET present=0 WHERE local_data_id IN ($ids)");
		}
	} else {
		/* don't mark anything in case we have no $local_data_ids => 
		 *this would flush the whole table at bottom of this function */
	}

	/* setup the database call */
	$sql_prefix   = 'INSERT INTO poller_item (local_data_id, poller_id, host_id, action, hostname, ' .
		'snmp_community, snmp_version, snmp_timeout, snmp_username, snmp_password, ' .
		'snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, ' .
		'snmp_port, rrd_name, rrd_path, rrd_num, rrd_step, rrd_next_step, arg1, arg2, arg3, present) ' .
		'VALUES';

	$sql_suffix   = ' ON DUPLICATE KEY UPDATE poller_id=VALUES(poller_id), host_id=VALUES(host_id), action=VALUES(action), hostname=VALUES(hostname), ' .
		'snmp_community=VALUES(snmp_community), snmp_version=VALUES(snmp_version), snmp_timeout=VALUES(snmp_timeout), ' .
		'snmp_username=VALUES(snmp_username), snmp_password=VALUES(snmp_password), snmp_auth_protocol=VALUES(snmp_auth_protocol), ' .
		'snmp_priv_passphrase=VALUES(snmp_priv_passphrase), snmp_priv_protocol=VALUES(snmp_priv_protocol), ' .
		'snmp_context=VALUES(snmp_context), snmp_port=VALUES(snmp_port), rrd_path=VALUES(rrd_path), rrd_num=VALUES(rrd_num), ' .
		'rrd_step=VALUES(rrd_step), rrd_next_step=VALUES(rrd_next_step), arg1=VALUES(arg1), arg2=VALUES(arg2), ' .
		'arg3=VALUES(arg3), present=VALUES(present)';

	/* use a reasonable insert buffer, the default is 1MByte */
	$max_packet   = 256000;

	/* setup somme defaults */
	$overhead     = strlen($sql_prefix) + strlen($sql_suffix);
	$buf_len      = 0;
	$buf_count    = 0;
	$buffer       = '';

	if (sizeof($poller_items)) {
	foreach($poller_items AS $record) {
		/* take care of invalid entries */
		if (strlen($record) == 0) continue;

		if ($buf_count == 0) {
			$delim = ' ';
		} else {
			$delim = ', ';
		}

		$buffer .= $delim . $record;

		$buf_len += strlen($record);

		if (($overhead + $buf_len) > ($max_packet - 1024)) {
			db_execute($sql_prefix . $buffer . $sql_suffix);

			$buffer    = '';
			$buf_len   = 0;
			$buf_count = 0;
		} else {
			$buf_count++;
		}
	}
	}

	if ($buf_count > 0) {
		db_execute($sql_prefix . $buffer . $sql_suffix);
	}

	/* remove stale records FROM the poller cache */
	if (sizeof($ids)) {
		db_execute("DELETE FROM poller_item WHERE present=0 AND local_data_id IN ($ids)");
	}else{
		/* only handle explicitely given local_data_ids */
	}
}

/** for a given data template, update all input data and the poller cache
 * @param int $host_id - id of host, if any
 * @param int $local_data_id - id of a single data source, if any
 * @param int $data_template_id - id of data template
 * works on table data_input_data and poller cache
 */

function push_out_host($host_id, $local_data_id = 0, $data_template_id = 0) {

	/* ok here's the deal: first we need to find every data source that uses this host.
	then we go through each of those data sources, finding each one using a data input method
	with "special fields". if we find one, fill it will the data here FROM this host */
	/* setup the poller items array */
	$poller_items   = array();
	$local_data_ids = array();
	$hosts          = array();
	$sql_where      = '';

	/* setup the sql where, and if using a host, get it's host information */
	if ($host_id != 0) {
		/* get all information about this host so we can write it to the data source */
		$hosts[$host_id] = db_fetch_row('SELECT ' . SQL_NO_CACHE . ' id AS host_id, host.* FROM host WHERE id=' . $host_id);

		$sql_where .= ' AND dl.host_id=' . $host_id;
	}

	/* sql WHERE for local_data_id */
	if ($local_data_id != 0) {
		$sql_where .= ' AND dl.id=' . $local_data_id;
	}

	/* sql WHERE for data_template_id */
	if ($data_template_id != 0) {
		$sql_where .= ' AND dtd.data_template_id=' . $data_template_id;
	}

	$data_sources = db_fetch_assoc('SELECT ' . SQL_NO_CACHE . " dtd.id, dtd.data_input_id, dtd.local_data_id,
		dtd.local_data_template_data_id, dl.host_id, dl.snmp_query_id, dl.snmp_index
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		WHERE dtd.data_input_id>0
		$sql_where");

	/* loop through each matching data source */
	if (sizeof($data_sources)) {
	foreach ($data_sources as $data_source) {
		/* set the host information */
		if (!isset($hosts[$data_source['host_id']])) {
			$hosts[$data_source['host_id']] = db_fetch_row('SELECT * FROM host WHERE id=' . $data_source['host_id']);
		}
		$host = $hosts[$data_source['host_id']];

		/* get field information FROM the data template */
		if (!isset($template_fields{$data_source['local_data_template_data_id']})) {
			$template_fields{$data_source['local_data_template_data_id']} = db_fetch_assoc('SELECT ' . SQL_NO_CACHE . "
				did.value, did.t_value, dif.id, dif.type_code
				FROM data_input_fields AS dif
				LEFT JOIN data_input_data AS did
				ON dif.id=did.data_input_field_id
				WHERE dif.data_input_id=" . $data_source['data_input_id'] . "
				AND did.data_template_data_id=" . $data_source['local_data_template_data_id'] . "
				AND (did.t_value='' OR did.t_value is null)
				AND dif.input_output='in'");
		}

		reset($template_fields{$data_source['local_data_template_data_id']});

		/* loop through each field contained in the data template and push out a host value if:
		 - the field is a valid "host field"
		 - the value of the field is empty
		 - the field is set to 'templated' */
		if (sizeof($template_fields{$data_source['local_data_template_data_id']})) {
		foreach ($template_fields{$data_source['local_data_template_data_id']} as $template_field) {
			if ((preg_match('/^' . VALID_HOST_FIELDS . '$/i', $template_field['type_code'])) && ($template_field['value'] == '') && ($template_field['t_value'] == '')) {
				// handle special case type_code
				if ($template_field['type_code'] == 'host_id') $template_field['type_code'] = 'id';

				db_execute_prepared('REPLACE INTO data_input_data (data_input_field_id, data_template_data_id, value) VALUES (?, ?, ?)', array($template_field['id'], $data_source['id'], $host[$template_field['type_code']]));
			}
		}
		}

		/* flag an update to the poller cache as well */
		$local_data_ids[] = $data_source['local_data_id'];

		/* create a new compatible structure */
		$data = $data_source;
		$data['id'] = $data['local_data_id'];

		$poller_items     = array_merge($poller_items, update_poller_cache($data));
	}
	}

	if (sizeof($local_data_ids)) {
		poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
	}
}

function duplicate_graph($_local_graph_id, $_graph_template_id, $graph_title) {
	global $struct_graph, $struct_graph_item;

	if (!empty($_local_graph_id)) {
		$graph_local          = db_fetch_row("SELECT * FROM graph_local WHERE id=$_local_graph_id");
		$graph_template_graph = db_fetch_row("SELECT * FROM graph_templates_graph WHERE local_graph_id=$_local_graph_id");
		$graph_template_items = db_fetch_assoc("SELECT * FROM graph_templates_item WHERE local_graph_id=$_local_graph_id");

		/* create new entry: graph_local */
		$save['id'] = 0;
		$save['graph_template_id'] = $graph_local['graph_template_id'];
		$save['host_id']           = $graph_local['host_id'];
		$save['snmp_query_id']     = $graph_local['snmp_query_id'];
		$save['snmp_index']        = $graph_local['snmp_index'];

		$local_graph_id = sql_save($save, 'graph_local');

		$graph_template_graph['title'] = str_replace('<graph_title>', $graph_template_graph['title'], $graph_title);
	}elseif (!empty($_graph_template_id)) {
		$graph_template        = db_fetch_row("SELECT * FROM graph_templates WHERE id=$_graph_template_id");
		$graph_template_graph  = db_fetch_row("SELECT * FROM graph_templates_graph WHERE graph_template_id=$_graph_template_id AND local_graph_id=0");
		$graph_template_items  = db_fetch_assoc("SELECT * FROM graph_templates_item WHERE graph_template_id=$_graph_template_id AND local_graph_id=0");
		$graph_template_inputs = db_fetch_assoc("SELECT * FROM graph_template_input WHERE graph_template_id=$_graph_template_id");

		/* create new entry: graph_templates */
		$save['id']   = 0;
		$save['hash'] = get_hash_graph_template(0);
		$save['name'] = str_replace('<template_title>', $graph_template['name'], $graph_title);

		$graph_template_id = sql_save($save, 'graph_templates');
	}

	unset($save);
	unset($struct_graph['general_header']);
	unset($struct_graph['scaling_header']);
	unset($struct_graph['grid_header']);
	unset($struct_graph['axis_header']);
	unset($struct_graph['legend_header']);
	reset($struct_graph);

	/* create new entry: graph_templates_graph */
	$save['id']                            = 0;
	$save['local_graph_id']                = (isset($local_graph_id) ? $local_graph_id : 0);
	$save['local_graph_template_graph_id'] = (isset($graph_template_graph['local_graph_template_graph_id']) ? $graph_template_graph['local_graph_template_graph_id'] : 0);
	$save['graph_template_id']             = (!empty($_local_graph_id) ? $graph_template_graph['graph_template_id'] : $graph_template_id);
	$save['title_cache']                   = $graph_template_graph['title_cache'];

	reset($struct_graph);
	while (list($field, $array) = each($struct_graph)) {
		$save{$field} = $graph_template_graph{$field};
		$save{'t_' . $field} = $graph_template_graph{'t_' . $field};
	}

	$graph_templates_graph_id = sql_save($save, 'graph_templates_graph');

	/* create new entry(s): graph_templates_item */
	if (sizeof($graph_template_items) > 0) {
	foreach ($graph_template_items as $graph_template_item) {
		unset($save);
		reset($struct_graph_item);

		$save['id']                           = 0;
		/* save a hash only for graph_template copy operations */
		$save['hash']                         = (!empty($_graph_template_id) ? get_hash_graph_template(0, 'graph_template_item') : 0);
		$save['local_graph_id']               = (isset($local_graph_id) ? $local_graph_id : 0);
		$save['graph_template_id']            = (!empty($_local_graph_id) ? $graph_template_item['graph_template_id'] : $graph_template_id);
		$save['local_graph_template_item_id'] = (isset($graph_template_item['local_graph_template_item_id']) ? $graph_template_item['local_graph_template_item_id'] : 0);

		while (list($field, $array) = each($struct_graph_item)) {
			$save{$field} = $graph_template_item{$field};
		}

		$graph_item_mappings{$graph_template_item['id']} = sql_save($save, 'graph_templates_item');
	}
	}

	if (!empty($_graph_template_id)) {
		/* create new entry(s): graph_template_input (graph template only) */
		if (sizeof($graph_template_inputs) > 0) {
		foreach ($graph_template_inputs as $graph_template_input) {
			unset($save);

			$save['id']                = 0;
			$save['graph_template_id'] = $graph_template_id;
			$save['name']              = $graph_template_input['name'];
			$save['description']       = $graph_template_input['description'];
			$save['column_name']       = $graph_template_input['column_name'];
			$save['hash']              = get_hash_graph_template(0, 'graph_template_input');

			$graph_template_input_id   = sql_save($save, 'graph_template_input');

			$graph_template_input_defs = db_fetch_assoc('SELECT * FROM graph_template_input_defs WHERE graph_template_input_id=' . $graph_template_input['id']);

			/* create new entry(s): graph_template_input_defs (graph template only) */
			if (sizeof($graph_template_input_defs) > 0) {
			foreach ($graph_template_input_defs as $graph_template_input_def) {
				db_execute("INSERT INTO graph_template_input_defs (graph_template_input_id,graph_template_item_id)
					values ($graph_template_input_id," . $graph_item_mappings{$graph_template_input_def['graph_template_item_id']} . ')');
			}
			}
		}
		}
	}

	if (!empty($_local_graph_id)) {
		update_graph_title_cache($local_graph_id);
	}
}

function duplicate_data_source($_local_data_id, $_data_template_id, $data_source_title) {
	global $struct_data_source, $struct_data_source_item;

	if (!empty($_local_data_id)) {
		$data_local = db_fetch_row("SELECT * FROM data_local WHERE id=$_local_data_id");
		$data_template_data = db_fetch_row("SELECT * FROM data_template_data WHERE local_data_id=$_local_data_id");
		$data_template_rrds = db_fetch_assoc("SELECT * FROM data_template_rrd WHERE local_data_id=$_local_data_id");

		$data_input_datas = db_fetch_assoc("SELECT * FROM data_input_data WHERE data_template_data_id=" . $data_template_data['id']);

		/* create new entry: data_local */
		$save['id']               = 0;
		$save['data_template_id'] = $data_local['data_template_id'];
		$save['host_id']          = $data_local['host_id'];
		$save['snmp_query_id']    = $data_local['snmp_query_id'];
		$save['snmp_index']       = $data_local['snmp_index'];

		$local_data_id = sql_save($save, 'data_local');

		$data_template_data['name'] = str_replace('<ds_title>', $data_template_data['name'], $data_source_title);
	}elseif (!empty($_data_template_id)) {
		$data_template = db_fetch_row("SELECT * FROM data_template WHERE id=$_data_template_id");
		$data_template_data = db_fetch_row("SELECT * FROM data_template_data WHERE data_template_id=$_data_template_id AND local_data_id=0");
		$data_template_rrds = db_fetch_assoc("SELECT * FROM data_template_rrd WHERE data_template_id=$_data_template_id AND local_data_id=0");

		$data_input_datas = db_fetch_assoc("SELECT * FROM data_input_data WHERE data_template_data_id=" . $data_template_data['id']);

		/* create new entry: data_template */
		$save['id']   = 0;
		$save['hash'] = get_hash_data_template(0);
		$save['name'] = str_replace('<template_title>', $data_template['name'], $data_source_title);

		$data_template_id = sql_save($save, 'data_template');
	}

	unset($save);
	unset($struct_data_source['data_source_path']);
	reset($struct_data_source);

	/* create new entry: data_template_data */
	$save['id']                          = 0;
	$save['local_data_id']               = (isset($local_data_id) ? $local_data_id : 0);
	$save['local_data_template_data_id'] = (isset($data_template_data['local_data_template_data_id']) ? $data_template_data['local_data_template_data_id'] : 0);
	$save['data_template_id']            = (!empty($_local_data_id) ? $data_template_data['data_template_id'] : $data_template_id);
	$save['name_cache']                  = $data_template_data['name_cache'];

	while (list($field, $array) = each($struct_data_source)) {
		$save{$field} = $data_template_data{$field};

		if ($array['flags'] != 'ALWAYSTEMPLATE') {
			$save{'t_' . $field} = $data_template_data{'t_' . $field};
		}
	}

	$data_template_data_id = sql_save($save, 'data_template_data');

	/* create new entry(s): data_template_rrd */
	if (sizeof($data_template_rrds) > 0) {
	foreach ($data_template_rrds as $data_template_rrd) {
		unset($save);
		reset($struct_data_source_item);

		$save['id']                         = 0;
		$save['local_data_id']              = (isset($local_data_id) ? $local_data_id : 0);
		$save['local_data_template_rrd_id'] = (isset($data_template_rrd['local_data_template_rrd_id']) ? $data_template_rrd['local_data_template_rrd_id'] : 0);
		$save['data_template_id']           = (!empty($_local_data_id) ? $data_template_rrd['data_template_id'] : $data_template_id);
		if ($save['local_data_id'] == 0) {
			$save['hash']                   = get_hash_data_template($data_template_rrd['local_data_template_rrd_id'], 'data_template_item');
		} else {
			$save['hash'] = '';
		}

		while (list($field, $array) = each($struct_data_source_item)) {
			$save{$field} = $data_template_rrd{$field};

			if (isset($data_template_rrd{'t_' . $field})) {
				$save{'t_' . $field} = $data_template_rrd{'t_' . $field};
			}
		}

		$data_template_rrd_id = sql_save($save, 'data_template_rrd');
	}
	}

	/* create new entry(s): data_input_data */
	if (sizeof($data_input_datas) > 0) {
	foreach ($data_input_datas as $data_input_data) {
		db_execute("INSERT INTO data_input_data (data_input_field_id,data_template_data_id,t_value,value) VALUES
			(" . $data_input_data['data_input_field_id'] . ",$data_template_data_id,'" . $data_input_data['t_value'] .
			"','" . $data_input_data['value'] . "')");
	}
	}

	if (!empty($_local_data_id)) {
		update_data_source_title_cache($local_data_id);
	}
}

function duplicate_host_template($_host_template_id, $host_template_title) {
	global $fields_host_template_edit;

	$host_template = db_fetch_row("SELECT * FROM host_template WHERE id=$_host_template_id");
	$host_template_graphs = db_fetch_assoc("SELECT * FROM host_template_graph WHERE host_template_id=$_host_template_id");
	$host_template_data_queries = db_fetch_assoc("SELECT * FROM host_template_snmp_query WHERE host_template_id=$_host_template_id");

	/* substitute the title variable */
	$host_template['name'] = str_replace('<template_title>', $host_template['name'], $host_template_title);

	/* create new entry: host_template */
	$save['id']   = 0;
	$save['hash'] = get_hash_host_template(0);

	reset($fields_host_template_edit);
	while (list($field, $array) = each($fields_host_template_edit)) {
		if (!preg_match('/^hidden/', $array['method'])) {
			$save[$field] = $host_template[$field];
		}
	}

	$host_template_id = sql_save($save, 'host_template');

	/* create new entry(s): host_template_graph */
	if (sizeof($host_template_graphs) > 0) {
	foreach ($host_template_graphs as $host_template_graph) {
		db_execute("INSERT INTO host_template_graph (host_template_id,graph_template_id) VALUES ($host_template_id," . $host_template_graph['graph_template_id'] . ')');
	}
	}

	/* create new entry(s): host_template_snmp_query */
	if (sizeof($host_template_data_queries) > 0) {
	foreach ($host_template_data_queries as $host_template_data_query) {
		db_execute("INSERT INTO host_template_snmp_query (host_template_id,snmp_query_id) VALUES ($host_template_id," . $host_template_data_query['snmp_query_id'] . ')');
	}
	}
}

function duplicate_cdef($_cdef_id, $cdef_title) {
	global $fields_cdef_edit;

	$cdef = db_fetch_row("SELECT * FROM cdef WHERE id=$_cdef_id");
	$cdef_items = db_fetch_assoc("SELECT * FROM cdef_items WHERE cdef_id=$_cdef_id");

	/* substitute the title variable */
	$cdef['name'] = str_replace('<cdef_title>', $cdef['name'], $cdef_title);

	/* create new entry: host_template */
	$save['id']   = 0;
	$save['hash'] = get_hash_cdef(0);

	reset($fields_cdef_edit);
	while (list($field, $array) = each($fields_cdef_edit)) {
		if (!preg_match('/^hidden/', $array['method'])) {
			$save[$field] = $cdef[$field];
		}
	}

	$cdef_id = sql_save($save, 'cdef');

	/* create new entry(s): cdef_items */
	if (sizeof($cdef_items) > 0) {
		foreach ($cdef_items as $cdef_item) {
			unset($save);

			$save['id']       = 0;
			$save['hash']     = get_hash_cdef(0, 'cdef_item');
			$save['cdef_id']  = $cdef_id;
			$save['sequence'] = $cdef_item['sequence'];
			$save['type']     = $cdef_item['type'];
			$save['value']    = $cdef_item['value'];

			sql_save($save, 'cdef_items');
		}
	}
}

function duplicate_vdef($_vdef_id, $vdef_title) {
	global $fields_vdef_edit;

	$vdef       = db_fetch_row_prepared('SELECT * FROM vdef WHERE id = ?', array($_vdef_id));
	$vdef_items = db_fetch_assoc_prepared('SELECT * FROM vdef_items WHERE vdef_id = ?', array($_vdef_id));

	/* substitute the title variable */
	$vdef['name'] = str_replace('<vdef_title>', $vdef['name'], $vdef_title);

	/* create new entry: device_template */
	$save['id']   = 0;
	$save['hash'] = get_hash_vdef(0);

	$fields_vdef_edit = preset_vdef_form_list();
	reset($fields_vdef_edit);
	while (list($field, $array) = each($fields_vdef_edit)) {
		if (!preg_match('/^hidden/', $array['method'])) {
			$save[$field] = $vdef[$field];
		}
	}

	$vdef_id = sql_save($save, 'vdef');

	/* create new entry(s): vdef_items */
	if (sizeof($vdef_items) > 0) {
		foreach ($vdef_items as $vdef_item) {
			unset($save);

			$save['id']       = 0;
			$save['hash']     = get_hash_vdef(0, 'vdef_item');
			$save['vdef_id']  = $vdef_id;
			$save['sequence'] = $vdef_item['sequence'];
			$save['type']     = $vdef_item['type'];
			$save['value']    = $vdef_item['value'];

			sql_save($save, 'vdef_items');
		}
	}
}

