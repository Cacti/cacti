<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

/* update_replication_crc - update hash stored in settings table to inform
   remote pollers to replicate tables
   @arg $poller_id - the id of the poller impacted by hash update
   @arg $variable  - the variable name to store in the settings table */
function update_replication_crc($poller_id, $variable) {
	$hash = hash('ripemd160', date('Y-m-d H:i:s') . rand() . $poller_id);

	db_execute_prepared("REPLACE INTO settings
		SET value = ?, name='$variable" . ($poller_id > 0 ? "_" . "$poller_id'":"'"),
		array($hash));
}

function repopulate_poller_cache() {
	global $config;

	include_once($config['library_path'] . '/api_data_source.php');

	$poller_data = db_fetch_assoc('SELECT ' . SQL_NO_CACHE . ' dl.*, h.poller_id
		FROM data_local AS dl
		INNER JOIN host AS h
		ON dl.host_id=h.id
		WHERE dl.snmp_query_id = 0 OR (dl.snmp_query_id > 0 AND dl.snmp_index != "")
		ORDER BY h.poller_id ASC, h.id ASC');

	$poller_items   = array();
	$local_data_ids = array();
	$poller_prev    = 1;

	$i = 0;
	$j = 0;

	if (cacti_sizeof($poller_data)) {
		foreach ($poller_data as $data) {
			if ($j == 0) {
				$poller_prev = $data['poller_id'];
			}

			$poller_id = $data['poller_id'];

			if ($i > 500 || $poller_prev != $poller_id) {
				poller_update_poller_cache_from_buffer($local_data_ids, $poller_items, $poller_prev);

				$i = 0;

				$local_data_ids = array();
				$poller_items   = array();
			}

			$poller_prev      = $poller_id;
			$poller_items     = array_merge($poller_items, update_poller_cache($data));
			$local_data_ids[] = $data['id'];

			$i++;
			$j++;
		}

		if ($i > 0) {
			poller_update_poller_cache_from_buffer($local_data_ids, $poller_items, $poller_id);
		}
	}

	$poller_ids = array_rekey(
		db_fetch_assoc('SELECT DISTINCT poller_id
			FROM poller_item'),
		'poller_id', 'poller_id'
	);

	if (cacti_sizeof($poller_ids)) {
		foreach ($poller_ids as $poller_id) {
			api_data_source_cache_crc_update($poller_id);
		}
	}

	/* update the field mappings for the poller */
	db_execute('TRUNCATE TABLE poller_data_template_field_mappings');
	db_execute('INSERT IGNORE INTO poller_data_template_field_mappings
		SELECT dtr.data_template_id, dif.data_name,
		GROUP_CONCAT(DISTINCT dtr.data_source_name ORDER BY dtr.data_source_name) AS data_source_names, NOW()
		FROM graph_templates_item AS gti
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		INNER JOIN data_input_fields AS dif
		ON dtr.data_input_field_id = dif.id
		WHERE dtr.local_data_id = 0
		AND gti.local_graph_id = 0
		GROUP BY dtr.data_template_id, dif.data_name');

	if (isset($_SESSION['sess_user_id'])) {
		cacti_log('NOTE: Poller Cache repopulated by user ' . get_username($_SESSION['sess_user_id']), false, 'WEBUI');
	} else {
		cacti_log('NOTE: Poller Cache repopulated by cli script');
	}
}

function update_poller_cache_from_query($host_id, $data_query_id, $local_data_ids) {
	global $config;

	include_once($config['library_path'] . '/api_data_source.php');

	$poller_data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' *
		FROM data_local
		WHERE host_id = ?
		AND snmp_query_id = ?
		AND id IN(' . implode(', ', $local_data_ids) . ')
		AND snmp_index != ""',
		array($host_id, $data_query_id));

	$poller_id = db_fetch_cell_prepared('SELECT poller_id
		FROM host
		WHERE id = ?',
		array($host_id));

	$i = 0;
	$poller_items = $local_data_ids = array();

	if (cacti_sizeof($poller_data)) {
		foreach ($poller_data as $data) {
			$poller_items     = array_merge($poller_items, update_poller_cache($data));
			$local_data_ids[] = $data['id'];
			$i++;

			if ($i > 500) {
				$i = 0;
				poller_update_poller_cache_from_buffer($local_data_ids, $poller_items, $poller_id);
				$local_data_ids = array();
				$poller_items   = array();
			}
		}

		if ($i > 0) {
			poller_update_poller_cache_from_buffer($local_data_ids, $poller_items, $poller_id);
		}
	}

	if ($poller_id > 1) {
		api_data_source_cache_crc_update($poller_id);
	}
}

function update_poller_cache($data_source, $commit = false) {
	global $config;

	include_once($config['library_path'] . '/data_query.php');
	include_once($config['library_path'] . '/api_poller.php');

	if (!is_array($data_source)) {
		$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' *
			FROM data_local AS dl
			WHERE id = ?
			AND (dl.snmp_query_id = 0 OR (dl.snmp_query_id > 0 AND dl.snmp_index != ""))',
			array($data_source));
	}

	$poller_items = array();

	if (!cacti_sizeof($data_source)) {
		return $poller_items;
	}

	$poller_id = db_fetch_cell_prepared('SELECT poller_id
		FROM host AS h
		INNER JOIN data_local AS dl
		ON h.id=dl.host_id
		WHERE dl.id = ?',
		array($data_source['id']));

	$data_input = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . '
		di.id, di.type_id, dtd.id AS data_template_data_id,
		dtd.data_template_id, dtd.active, dtd.rrd_step
		FROM data_template_data AS dtd
		INNER JOIN data_input AS di
		ON dtd.data_input_id=di.id
		WHERE dtd.local_data_id = ?',
		array($data_source['id']));

	if (cacti_sizeof($data_input)) {
		// Check whitelist for input validation
		if (!data_input_whitelist_check($data_input['id'])) {
			return $poller_items;
		}

		/* we have to perform some additional sql queries if this is a 'query' */
		if (($data_input['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY) ||
			($data_input['type_id'] == DATA_INPUT_TYPE_SCRIPT_QUERY) ||
			($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER)){
			$field = data_query_field_list($data_input['data_template_data_id']);

			$params = array();
			if (cacti_sizeof($field) && $field['output_type'] != '') {
				$output_type_sql = ' AND sqgr.snmp_query_graph_id = ' . $field['output_type'];
			} else {
				$output_type_sql = '';
			}

			$params[] = $data_input['data_template_id'];
			$params[] = $data_source['id'];

			$outputs = db_fetch_assoc_prepared('SELECT DISTINCT ' . SQL_NO_CACHE . "
				sqgr.snmp_field_name, dtr.id AS data_template_rrd_id
				FROM snmp_query_graph_rrd AS sqgr
				INNER JOIN data_template_rrd AS dtr
				ON sqgr.data_template_rrd_id = dtr.local_data_template_rrd_id
				WHERE sqgr.data_template_id = ?
				AND dtr.local_data_id = ?
				$output_type_sql
				ORDER BY dtr.id", $params);
		}

		if ($data_input['active'] == 'on') {
			if (($data_input['type_id'] == DATA_INPUT_TYPE_SCRIPT) ||
				($data_input['type_id'] == DATA_INPUT_TYPE_PHP_SCRIPT_SERVER)) {
				if ($data_input['type_id'] == DATA_INPUT_TYPE_PHP_SCRIPT_SERVER) {
					$action = POLLER_ACTION_SCRIPT_PHP;
					$script_path = get_full_script_path($data_source['id']);
				} else {
					$action = POLLER_ACTION_SCRIPT;
					$script_path = get_full_script_path($data_source['id']);
				}

				$num_output_fields = cacti_sizeof(db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
					FROM data_input_fields
					WHERE data_input_id = ?
					AND input_output = "out"
					AND update_rra="on"',
					array($data_input['id'])));

				if ($num_output_fields == 1) {
					$data_template_rrd_id = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' id
						FROM data_template_rrd
						WHERE local_data_id = ?',
						array($data_source['id']));

					$data_source_item_name = get_data_source_item_name($data_template_rrd_id);
				} else {
					$data_source_item_name = '';
				}

				$poller_items[] = api_poller_cache_item_add($data_source['host_id'], array(), $data_source['id'], $data_input['rrd_step'], $action, $data_source_item_name, 1, $script_path);
			} elseif ($data_input['type_id'] == DATA_INPUT_TYPE_SNMP) {
				/* get the host override fields */
				$data_template_id = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' data_template_id
					FROM data_template_data
					WHERE local_data_id = ?',
					array($data_source['id']));

				/* get host fields first */
				$host_fields = array_rekey(
					db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.type_code, did.value
						FROM data_input_fields AS dif
						LEFT JOIN data_input_data AS did
						ON dif.id=did.data_input_field_id
						WHERE (type_code LIKE "snmp_%" OR type_code IN("hostname","host_id"))
						AND did.data_template_data_id = ?
						AND did.value != ""', array($data_input['data_template_data_id'])),
					'type_code', 'value'
				);

				$data_template_fields = array_rekey(
					db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.type_code, did.value
						FROM data_input_fields AS dif
						LEFT JOIN data_input_data AS did
						ON dif.id=did.data_input_field_id
						WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
						AND did.data_template_data_id = ?
						AND data_template_data_id = ?
						AND did.value != ""', array($data_template_id, $data_template_id)),
					'type_code', 'value'
				);

				if (cacti_sizeof($host_fields)) {
					if (cacti_sizeof($data_template_fields)) {
						foreach($data_template_fields as $key => $value) {
							if (!isset($host_fields[$key])) {
								$host_fields[$key] = $value;
							}
						}
					}
				} elseif (cacti_sizeof($data_template_fields)) {
					$host_fields = $data_template_fields;
				}

				$data_template_rrd_id = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' id
					FROM data_template_rrd
					WHERE local_data_id = ?',
					array($data_source['id']));

				$poller_items[] = api_poller_cache_item_add($data_source['host_id'], $host_fields, $data_source['id'], $data_input['rrd_step'], 0, get_data_source_item_name($data_template_rrd_id), 1, (isset($host_fields['snmp_oid']) ? $host_fields['snmp_oid'] : ''));
			} elseif ($data_input['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY) {
				$snmp_queries = get_data_query_array($data_source['snmp_query_id']);

				/* get the host override fields */
				$data_template_id = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' data_template_id
					FROM data_template_data
					WHERE local_data_id = ?',
					array($data_source['id']));

				/* get host fields first */
				$host_fields = array_rekey(
					db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.type_code, did.value
						FROM data_input_fields AS dif
						LEFT JOIN data_input_data AS did
						ON dif.id=did.data_input_field_id
						WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
						AND did.data_template_data_id = ?
						AND did.value != ""', array($data_input['data_template_data_id'])),
					'type_code', 'value'
				);

				$data_template_fields = array_rekey(
					db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.type_code, did.value
						FROM data_input_fields AS dif
						LEFT JOIN data_input_data AS did
						ON dif.id=did.data_input_field_id
						WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
						AND did.data_template_data_id = ?
						AND data_template_data_id = ?
						AND did.value != ""', array($data_template_id, $data_template_id)),
					'type_code', 'value'
				);

				if (cacti_sizeof($host_fields)) {
					if (cacti_sizeof($data_template_fields)) {
						foreach ($data_template_fields as $key => $value) {
							if (!isset($host_fields[$key])) {
								$host_fields[$key] = $value;
							}
						}
					}
				} elseif (cacti_sizeof($data_template_fields)) {
					$host_fields = $data_template_fields;
				}

				if (cacti_sizeof($outputs) && cacti_sizeof($snmp_queries)) {
					foreach ($outputs as $output) {
						if (isset($snmp_queries['fields'][$output['snmp_field_name']]['oid'])) {
							$oid = $snmp_queries['fields'][$output['snmp_field_name']]['oid'] . '.' . $data_source['snmp_index'];

							if (isset($snmp_queries['fields'][$output['snmp_field_name']]['oid_suffix'])) {
								$oid .= '.' . $snmp_queries['fields'][$output['snmp_field_name']]['oid_suffix'];
							}
						}

						if (!empty($oid)) {
							$poller_items[] = api_poller_cache_item_add($data_source['host_id'], $host_fields, $data_source['id'], $data_input['rrd_step'], 0, get_data_source_item_name($output['data_template_rrd_id']), cacti_sizeof($outputs), $oid);
						}
					}
				}
			} elseif (($data_input['type_id'] == DATA_INPUT_TYPE_SCRIPT_QUERY) ||
				($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER)) {
				$script_queries = get_data_query_array($data_source['snmp_query_id']);

				/* get the host override fields */
				$data_template_id = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' data_template_id
					FROM data_template_data
					WHERE local_data_id = ?',
					array($data_source['id']));

				/* get host fields first */
				$host_fields = array_rekey(
					db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.type_code, did.value
						FROM data_input_fields AS dif
						LEFT JOIN data_input_data AS did
						ON dif.id=did.data_input_field_id
						WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
						AND did.data_template_data_id = ?
						AND did.value != ""', array($data_input['data_template_data_id'])),
					'type_code', 'value'
				);

				$data_template_fields = array_rekey(
					db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dif.type_code, did.value
						FROM data_input_fields AS dif
						LEFT JOIN data_input_data AS did
						ON dif.id=did.data_input_field_id
						WHERE (type_code LIKE "snmp_%" OR type_code="hostname")
						AND data_template_data_id = ?
						AND did.data_template_data_id = ?
						AND did.value != ""', array($data_template_id, $data_template_id)),
					'type_code', 'value'
				);

				if (cacti_sizeof($host_fields)) {
					if (cacti_sizeof($data_template_fields)) {
						foreach ($data_template_fields as $key => $value) {
							if (!isset($host_fields[$key])) {
								$host_fields[$key] = $value;
							}
						}
					}
				} elseif (cacti_sizeof($data_template_fields)) {
					$host_fields = $data_template_fields;
				}

				if (cacti_sizeof($outputs) && cacti_sizeof($script_queries)) {
					foreach ($outputs as $output) {
						if (isset($script_queries['fields'][$output['snmp_field_name']]['query_name'])) {
							$identifier = $script_queries['fields'][$output['snmp_field_name']]['query_name'];

							if ($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER) {
								$action = POLLER_ACTION_SCRIPT_PHP;

								$prepend = '';
								if (isset($script_queries['arg_prepend']) && $script_queries['arg_prepend'] != '') {
									$prepend = $script_queries['arg_prepend'];
								}

								$script_path = get_script_query_path(trim($prepend . ' ' . $script_queries['arg_get'] . ' ' . $identifier . ' ' . $data_source['snmp_index']), $script_queries['script_path'] . ' ' . $script_queries['script_function'], $data_source['host_id']);
							} else {
								$action = POLLER_ACTION_SCRIPT;
								$script_path = get_script_query_path(trim((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] : '') . ' ' . $script_queries['arg_get'] . ' ' . $identifier . ' ' . $data_source['snmp_index']), $script_queries['script_path'], $data_source['host_id']);
							}
						}

						if (isset($script_path)) {
							$poller_items[] = api_poller_cache_item_add($data_source['host_id'], $host_fields, $data_source['id'], $data_input['rrd_step'], $action, get_data_source_item_name($output['data_template_rrd_id']), cacti_sizeof($outputs), $script_path);
						}
					}
				}
			} else {
				$arguments = array(
					'poller_items' => $poller_items,
					'data_source'  => $data_source,
					'data_input'   => $data_input
				);

				$arguments = api_plugin_hook_function('data_source_to_poller_items', $arguments);

				// Process the returned poller items
				if ((isset($arguments['poller_items'])) &&
					(is_array($arguments['poller_items'])) &&
					(cacti_sizeof($poller_items) < cacti_sizeof($arguments['poller_items']))) {
					$poller_items = $arguments['poller_items'];
				}
			}
		}
	} else {
		$data_template_data = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' *
			FROM data_template_data
			WHERE local_data_id = ?',
			array($data_source['id']));

		if (cacti_sizeof($data_template_data) && $data_template_data['data_input_id'] > 0) {
			cacti_log('WARNING: Repopulate Poller Cache found Data Input Missing for Data Source ' . $data_source['id'] . '.  Database may be corrupted');
		}
	}

	if ($commit && cacti_sizeof($poller_items)) {
		poller_update_poller_cache_from_buffer((array)$data_source['id'], $poller_items, $poller_id);
	} elseif (!$commit) {
		return $poller_items;
	}
}

function push_out_data_input_method($data_input_id) {
	$data_sources = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' dl.*, h.poller_id
		FROM data_local AS dl
		INNER JOIN (
			SELECT DISTINCT local_data_id
			FROM data_template_data
			WHERE data_input_id = ?
			AND local_data_id > 0
		) AS dtd
		ON dtd.local_data_id = dl.id
		INNER JOIN host AS h
		ON h.id = dl.host_id
		WHERE dl.snmp_query_id = 0 OR (dl.snmp_query_id > 0 AND dl.snmp_index != "")
		ORDER BY h.poller_id ASC',
		array($data_input_id));

	$poller_items = array();
	$_my_local_data_ids = array();

	if (cacti_sizeof($data_sources)) {
		$prev_poller = -1;
		foreach ($data_sources as $data_source) {
			if ($prev_poller > 0 && $data_source['poller_id'] != $prev_poller) {
				poller_update_poller_cache_from_buffer($_my_local_data_ids, $poller_items, $prev_poller);
				$_my_local_data_ids = array();
				$poller_items = array();
			} else {
				$_my_local_data_ids[] = $data_source['id'];
				$poller_items = array_merge($poller_items, update_poller_cache($data_source));
			}

			$prev_poller = $data_source['poller_id'];
		}

		if (cacti_sizeof($_my_local_data_ids)) {
			poller_update_poller_cache_from_buffer($_my_local_data_ids, $poller_items, $prev_poller);
		}
	}
}

/** mass update of poller cache - can run in parallel to poller
 * @param array/int $local_data_ids - either a scalar (all ids) or an array of data source to act on
 * @param array $poller_items - the new items for poller cache
 * @param int $poller_id - the poller_id of the buffer
 */
function poller_update_poller_cache_from_buffer($local_data_ids, &$poller_items, $poller_id = 1) {
	global $config;

	$ids    = '';
	$raised = false;

	/* set all fields present value to 0, to mark the outliers when we are all done */
	if (cacti_sizeof($local_data_ids)) {
		$ids = implode(', ', $local_data_ids);

		if ($ids != '') {
			db_execute_prepared("UPDATE poller_item
				SET present = 0
				WHERE poller_id = ?
				AND local_data_id IN ($ids)",
				array($poller_id));

			if ($poller_id > 1) {
				if (remote_poller_up($poller_id)) {
					if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
						db_execute_prepared("UPDATE poller_item
							SET present = 0
							WHERE poller_id = ?
							AND local_data_id IN ($ids)",
							array($poller_id), true, $rcnn_id);
					} else {
						raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
						$raised = true;
					}
				} else {
					raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					$raised = true;
				}
			}
		}
	} else {
		/* don't mark anything in case we have no $local_data_ids =>
		 *this would flush the whole table at bottom of this function */
	}

	/* setup the database call */
	$sql_prefix   = 'INSERT INTO poller_item (local_data_id, poller_id, host_id, action, hostname, ' .
		'snmp_community, snmp_version, snmp_timeout, snmp_username, snmp_password, ' .
		'snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, snmp_engine_id, ' .
		'snmp_port, rrd_name, rrd_path, rrd_num, rrd_step, rrd_next_step, arg1, arg2, arg3, present) ' .
		'VALUES';

	$sql_suffix   = ' ON DUPLICATE KEY UPDATE poller_id=VALUES(poller_id), host_id=VALUES(host_id), action=VALUES(action), hostname=VALUES(hostname), ' .
		'snmp_community=VALUES(snmp_community), snmp_version=VALUES(snmp_version), snmp_timeout=VALUES(snmp_timeout), ' .
		'snmp_username=VALUES(snmp_username), snmp_password=VALUES(snmp_password), snmp_auth_protocol=VALUES(snmp_auth_protocol), ' .
		'snmp_priv_passphrase=VALUES(snmp_priv_passphrase), snmp_priv_protocol=VALUES(snmp_priv_protocol), ' .
		'snmp_context=VALUES(snmp_context), snmp_engine_id=VALUES(snmp_engine_id), snmp_port=VALUES(snmp_port), ' .
		'rrd_path=VALUES(rrd_path), rrd_num=VALUES(rrd_num), ' .
		'rrd_step=VALUES(rrd_step), rrd_next_step=VALUES(rrd_next_step), arg1=VALUES(arg1), arg2=VALUES(arg2), ' .
		'arg3=VALUES(arg3), present=VALUES(present)';

	/* use a reasonable insert buffer, the default is 1MByte */
	$max_packet   = 256000;

	/* setup some defaults */
	$overhead     = strlen($sql_prefix) + strlen($sql_suffix);
	$buf_len      = 0;
	$buf_count    = 0;
	$buffer       = '';

	if (cacti_sizeof($poller_items)) {
		foreach ($poller_items as $record) {
			/* take care of invalid entries */
			if ($record == '') {
				continue;
			}

			if ($buf_count == 0) {
				$delim = ' ';
			} else {
				$delim = ', ';
			}

			$buffer .= $delim . $record;

			$buf_len += strlen($record);

			if ($overhead + $buf_len > $max_packet - 1024) {
				db_execute($sql_prefix . $buffer . $sql_suffix);

				if ($poller_id > 1) {
					if (remote_poller_up($poller_id)) {
						if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
							db_execute($sql_prefix . $buffer . $sql_suffix, true, $rcnn_id);
						} elseif (!$raised) {
							raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
							$raised = true;
						}
					} elseif (!$raised) {
						raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
						$raised = true;
					}
				}

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

		if ($poller_id > 1) {
			if (remote_poller_up($poller_id)) {
				if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
					db_execute($sql_prefix . $buffer . $sql_suffix, true, $rcnn_id);
				} else {
					raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					$raised = true;
				}
			} elseif (!$raised) {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
				$raised = true;
			}
		}
	}

	/* remove stale records FROM the poller cache */
	if ($ids != '') {
		db_execute_prepared("DELETE FROM poller_item
			WHERE present = 0
			AND poller_id = ?
			AND local_data_id IN ($ids)",
			array($poller_id));

		if ($poller_id > 1) {
			if (remote_poller_up($poller_id)) {
				if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
					db_execute_prepared("DELETE FROM poller_item
						WHERE present = 0
						AND poller_id = ?
						AND local_data_id IN ($ids)",
						array($poller_id), true, $rcnn_id);
				} elseif (!$raised) {
					raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
				}
			} elseif (!$raised) {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
			}
		}
	}

	/**
	 * Save the last time a device/site was created/updated
	 * for Caching.
	 */
	set_config_option('time_last_change_poller_item', time());
}

/** for a given data template, update all input data and the poller cache
 * @param int $host_id - id of host, if any
 * @param int $local_data_id - id of a single data source, if any
 * @param int $data_template_id - id of data template
 * works on table data_input_data and poller cache
 */
function push_out_host($host_id, $local_data_id = 0, $data_template_id = 0) {
	global $config;

	include_once($config['library_path'] . '/api_data_source.php');

	/* ok here's the deal: first we need to find every data source that uses this host.
	then we go through each of those data sources, finding each one using a data input method
	with "special fields". if we find one, fill it will the data here FROM this host */
	/* setup the poller items array */
	$poller_items    = array();
	$local_data_ids  = array();
	$hosts           = array();
	$template_fields = array();
	$sql_where       = '';

	/* setup the sql where, and if using a host, get it's host information */
	if ($host_id > 0) {
		/* get all information about this host so we can write it to the data source */
		$hosts[$host_id] = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' id AS host_id, host.*
			FROM host WHERE id = ?',
			array($host_id));

		$sql_where .= ' AND dl.host_id=' . $host_id;
	}

	/* sql WHERE for local_data_id */
	if ($local_data_id > 0) {
		$sql_where .= ' AND dl.id = ' . $local_data_id;
	}

	/* sql WHERE for data_template_id */
	if ($data_template_id > 0) {
		$sql_where .= ' AND dtd.data_template_id = ' . $data_template_id;
	}

	$data_sources = db_fetch_assoc('SELECT ' . SQL_NO_CACHE . " dtd.id,
		dtd.data_input_id, dtd.local_data_id,
		dtd.local_data_template_data_id, dl.host_id,
		dl.snmp_query_id, dl.snmp_index
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id = dtd.local_data_id
		WHERE dtd.data_input_id > 0
		AND (dl.snmp_query_id = 0 OR (dl.snmp_query_id > 0 AND dl.snmp_index != ''))
		$sql_where");

	/* loop through each matching data source */
	if (cacti_sizeof($data_sources)) {
		foreach ($data_sources as $data_source) {
			/* set the host information */
			if (!isset($hosts[$data_source['host_id']])) {
				$hosts[$data_source['host_id']] = db_fetch_row_prepared('SELECT *
					FROM host
					WHERE id = ?',
					array($data_source['host_id']));
			}
			$host = $hosts[$data_source['host_id']];

			/**
			 * get field information FROM the data template
			 */
			if (!isset($template_fields[$data_source['local_data_template_data_id']])) {
				$template_fields[$data_source['local_data_template_data_id']] = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . '
					did.value, did.t_value, dif.id, dif.type_code
					FROM data_input_fields AS dif
					LEFT JOIN data_input_data AS did
					ON dif.id = did.data_input_field_id
					WHERE dif.data_input_id = ?
					AND did.data_template_data_id = ?
					AND ((did.t_value = "" OR did.t_value IS NULL) OR (did.t_value = "on" AND did.value = ""))
					AND dif.input_output = "in"',
					array($data_source['data_input_id'], $data_source['local_data_template_data_id']));
			}

			/**
			 * loop through each field contained in the data template and push out a host value if:
			 * - the field is a valid "host field" and it's not set to be over-written
			 * - in other words the t_value is not checked
			 */
			if (cacti_sizeof($template_fields[$data_source['local_data_template_data_id']])) {
				foreach ($template_fields[$data_source['local_data_template_data_id']] as $template_field) {
					$update = false;
					$field  = '';

					// handle special case type_code
					if ($template_field['type_code'] == 'host_id') {
						$field = 'id';
					}

					// Only override if the template value is null as this point
					if (preg_match('/^' . VALID_HOST_FIELDS . '$/i', $template_field['type_code']) &&
						(($template_field['t_value'] != 'on' && $template_field['value'] == '') || $data_source['snmp_query_id'] == 0)) {
						// It's a valid host type-code
						$update = true;

						if ($template_field['type_code'] != 'host_id') {
							$field = $template_field['type_code'];
						}
					}

					if ($update) {
						// Don't mess with a field that does not exist
						// In the host table
						if (isset($host[$field])) {
							db_execute_prepared('REPLACE INTO data_input_data
								(data_input_field_id, data_template_data_id, value)
								VALUES (?, ?, ?)',
								array($template_field['id'], $data_source['id'], $host[$field]));
						}
					}
				}
			}

			/**
			 * flag an update to the poller cache as well
			 */
			$local_data_ids[] = $data_source['local_data_id'];

			/* create a new compatible structure */
			$data = $data_source;
			$data['id'] = $data['local_data_id'];

			$poller_items = array_merge($poller_items, update_poller_cache($data));
		}
	}

	if (cacti_sizeof($hosts)) {
		foreach($hosts as $host) {
			if (isset($host['poller_id'])) {
				$poller_ids[$host['poller_id']] = $host['poller_id'];
			}
		}

		if (cacti_sizeof($poller_ids) > 1) {
			cacti_log('WARNING: function push_out_host() discovered more than a single host', false, 'POLLER');
		}
	}

	$poller_id = db_fetch_cell_prepared('SELECT poller_id
		FROM host
		WHERE id = ?',
		array($host_id));

	if (cacti_sizeof($local_data_ids)) {
		poller_update_poller_cache_from_buffer($local_data_ids, $poller_items, $poller_id);
	}

	api_data_source_cache_crc_update($poller_id);
}

function data_input_whitelist_check($data_input_id) {
	global $config;

	static $data_input_whitelist = null;
	static $validated_input_ids  = null;
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
		$data_input_ids = array_rekey(
			db_fetch_assoc('SELECT * FROM data_input'),
			'hash', array('id', 'name', 'input_string')
		);

		$data_input_whitelist = json_decode(file_get_contents($config['input_whitelist']), true);
		if ($data_input_whitelist === null) {
			cacti_log('ERROR: Failed to parse input whitelist file: ' . $config['input_whitelist']);
			return true;
		}

		if (cacti_sizeof($data_input_ids)) {
			foreach ($data_input_ids as $hash => $id) {
				if ($id['input_string'] != '') {
					if (isset($data_input_whitelist[$hash])) {
						if ($data_input_whitelist[$hash] == $id['input_string']) {
							$validated_input_ids[$id['id']] = true;
						} else {
							cacti_log('ERROR: Whitelist entry failed validation for Data Input: ' . $id['name'] . '[ ' . $id['id'] . ' ].  Data Collection will not run.  Run CLI command input_whitelist.php --audit and --update to remediate.');
							$validated_input_ids[$id['id']] = false;
						}
					} else {
						cacti_log('WARNING: Whitelist entry missing for Data Input: ' . $id['name'] . '[ ' . $id['id'] . ' ].  Run CLI command input_whitelist.php --update to remediate.');
						$validated_input_ids[$id['id']] = true;
					}
				} else {
					$validated_input_ids[$id['id']] = true;
				}
			}
		}
	}

	if (isset($validated_input_ids[$data_input_id])) {
		if ($validated_input_ids[$data_input_id] == true) {
			return true;
		} else {
			cacti_log('WARNING: Data Input ' . $data_input_id . ' failing validation check.');
			$notified[$data_input_id] = true;
			return false;
		}
	} else {
		return true;
	}
}

function utilities_get_mysql_info($poller_id = 1) {
	global $local_db_cnn_id;

	if ($poller_id == 1) {
		$variables = array_rekey(db_fetch_assoc('SHOW GLOBAL VARIABLES'), 'Variable_name', 'Value');
	} else {
		$variables = array_rekey(db_fetch_assoc('SHOW GLOBAL VARIABLES', false, $local_db_cnn_id), 'Variable_name', 'Value');
	}

	if (strpos($variables['version'], 'MariaDB') !== false) {
		$database = 'MariaDB';
		$version  = str_replace('-MariaDB', '', $variables['version']);

		if (isset($variables['innodb_version'])) {
			$link_ver = substr($variables['innodb_version'], 0, 3);
		} else {
			$link_ver = $version;
		}
	} else {
		$database = 'MySQL';
		$version  = $variables['version'];
		$link_ver = substr($variables['version'], 0, 3);
	}

	return array(
		'database'  => $database,
		'version'   => $version,
		'link_ver'  => $link_ver,
		'variables' => $variables
	);
}

function utilities_get_mysql_recommendations() {
	global $config, $local_db_cnn_id;

	// MySQL/MariaDB Important Variables
	// Assume we are successfully, until we aren't!
	$result = DB_STATUS_SUCCESS;

	$memInfo = utilities_get_system_memory();

	$mysql_info = utilities_get_mysql_info($config['poller_id']);

	$database  = $mysql_info['database'];
	$version   = $mysql_info['version'];
	$link_ver  = $mysql_info['link_ver'];
	$variables = $mysql_info['variables'];

	$recommendations = array(
		'version' => array(
			'value' => '5.6',
			'class' => 'warning',
			'measure' => 'ge',
			'comment' => __('MySQL 5.6+ and MariaDB 10.0+ are great releases, and are very good versions to choose. Make sure you run the very latest release though which fixes a long standing low level networking issue that was causing spine many issues with reliability.')
		)
	);

	if (isset($variables['innodb_version']) && version_compare($variables['innodb_version'], '5.6', '<')) {
		if (version_compare($link_ver, '5.5', '>=')) {
			if (!isset($variables['innodb_version'])) {
				$recommendations += array(
					'innodb' => array(
						'value' => 'ON',
						'class' => 'warning',
						'measure' => 'equalint',
						'comment' => __('It is STRONGLY recommended that you enable InnoDB in any %s version greater than 5.5.3.', $database)
					)
				);

				$variables['innodb'] = 'UNSET';
			}
		}

		$recommendations += array(
			'collation_server' => array(
				'value' => 'utf8mb4_unicode_ci',
				'class' => 'warning',
				'measure' => 'equal',
				'comment' => __('When using Cacti with languages other than English, it is important to use the utf8mb4_unicode_ci collation type as some characters take more than a single byte.')
				),
			'character_set_server' => array(
				'value' => 'utf8mb4',
				'class' => 'warning',
				'measure' => 'equal',
				'comment' => __('When using Cacti with languages other than English, it is important to use the utf8mb4 character set as some characters take more than a single byte.')
				),
			'character_set_client' => array(
				'value' => 'utf8mb4',
				'class' => 'warning',
				'measure' => 'equal',
				'comment' => __('When using Cacti with languages other than English, it is important to use the utf8mb4 character set as some characters take more than a single byte.')
				)
		);
	} else {
		if (version_compare($link_ver, '5.2', '>=')) {
			if (!isset($variables['innodb_version']) &&
				($database == 'MySQL' || ($database == 'MariaDB' && version_compare($link_ver, '10.7', '<')))) {

				$recommendations += array(
					'innodb' => array(
						'value' => 'ON',
						'class' => 'warning',
						'measure' => 'equalint',
						'comment' => __('It is recommended that you enable InnoDB in any %s version greater than 5.1.', $database)
					)
				);

				$variables['innodb'] = 'UNSET';
			}
		}

		$recommendations += array(
			'collation_server' => array(
				'value' => 'utf8mb4_unicode_ci',
				'measure' => 'equal',
				'comment' => __('When using Cacti with languages other than English, it is important to use the utf8mb4_unicode_ci collation type as some characters take more than a single byte.')
				),
			'character_set_server' => array(
				'value' => 'utf8mb4',
				'class' => 'warning',
				'measure' => 'equal',
				'comment' => __('When using Cacti with languages other than English, it is important to use the utf8mb4 character set as some characters take more than a single byte.')
				),
			'character_set_client' => array(
				'value' => 'utf8mb4',
				'class' => 'warning',
				'measure' => 'equal',
				'comment' => __('When using Cacti with languages other than English, it is important to use the utf8mb4 character set as some characters take more than a single byte.')
				)
		);
	}

	$recommendations += array(
		'max_connections' => array(
			'value'   => '100',
			'measure' => 'ge',
			'comment' => __('Depending on the number of logins and use of spine data collector, %s will need many connections.  The calculation for spine is: total_connections = total_processes * (total_threads + script_servers + 1), then you must leave headroom for user connections, which will change depending on the number of concurrent login accounts.', $database)
			),
		'table_cache' => array(
			'value'   => '200',
			'measure' => 'ge',
			'comment' => __('Keeping the table cache larger means less file open/close operations when using innodb_file_per_table.')
			),
		'max_allowed_packet' => array(
			'value'   => 16777216,
			'measure' => 'ge',
			'comment' => __('With Remote polling capabilities, large amounts of data will be synced from the main server to the remote pollers.  Therefore, keep this value at or above 16M.')
			),
		'max_heap_table_size' => array(
			'value'   => '1.6',
			'measure' => 'pmem',
			'class'   => 'warning',
			'comment' => __('If using the Cacti Performance Booster and choosing a memory storage engine, you have to be careful to flush your Performance Booster buffer before the system runs out of memory table space.  This is done two ways, first reducing the size of your output column to just the right size.  This column is in the tables poller_output, and poller_output_boost.  The second thing you can do is allocate more memory to memory tables.  We have arbitrarily chosen a recommended value of 10%% of system memory, but if you are using SSD disk drives, or have a smaller system, you may ignore this recommendation or choose a different storage engine.  You may see the expected consumption of the Performance Booster tables under Console -> System Utilities -> View Boost Status.')
			),
		'tmp_table_size' => array(
			'value'   => '1.6',
			'measure' => 'pmem',
			'class'   => 'warning',
			'comment' => __('When executing subqueries, having a larger temporary table size, keep those temporary tables in memory.')
			),
		'join_buffer_size' => array(
			'value'   => '80',
			'measure' => 'cmem',
			'class'   => 'warning',
			'comment' => __('When performing joins, if they are below this size, they will be kept in memory and never written to a temporary file.  As this is a per connection memory allocation, care must be taken not to increase it too high.  The sum of the join_buffer_size + sort_buffer_size + read_buffer_size + read_rnd_buffer_size + thread_stack + binlog_cache_size + Core MySQL/MariaDB memory should be below 80%.  If the recommendation is negative, you must decrease this and or the sort_buffer_size until the recommendation fits within the allowable memory.')
			),
		'sort_buffer_size' => array(
			'value'   => '80',
			'measure' => 'cmem',
			'class'   => 'warning',
			'comment' => __('When performing joins, if they are below this size, they will be kept in memory and never written to a temporary file.  As this is a per connection memory allocation, care must be taken not to increase it too high.  The sum of the join_buffer_size + sort_buffer_size + read_buffer_size + read_rnd_buffer_size + thread_stack + binlog_cache_size + Core MySQL/MariaDB memory should be below 80%.  If the recommendation is negative, you must decrease this and or the sort_buffer_size until the recommendation fits within the allowable memory.')
			),
		'innodb_file_per_table' => array(
			'value'   => 'ON',
			'measure' => 'equalint',
			'class'   => 'error',
			'comment' => __('When using InnoDB storage it is important to keep your table spaces separate.  This makes managing the tables simpler for long time users of %s.  If you are running with this currently off, you can migrate to the per file storage by enabling the feature, and then running an alter statement on all InnoDB tables.', $database)
			),
		'innodb_file_format' => array(
			'value'   => 'Barracuda',
			'measure' => 'equal',
			'class'   => 'error',
			'comment' => __('When using innodb_file_per_table, it is important to set the innodb_file_format to Barracuda.  This setting will allow longer indexes important for certain Cacti tables.')
			),
		'innodb_large_prefix' => array(
			'value'   => '1',
			'measure' => 'equalint',
			'class'   => 'error',
			'comment' => __('If your tables have very large indexes, you must operate with the Barracuda innodb_file_format and the innodb_large_prefix equal to 1.  Failure to do this may result in plugins that can not properly create tables.')
			),
		'innodb_buffer_pool_size' => array(
			'value'   => '25',
			'measure' => 'pmem',
			'class' => 'warning',
			'comment' => __('InnoDB will hold as much tables and indexes in system memory as is possible.  Therefore, you should make the innodb_buffer_pool large enough to hold as much of the tables and index in memory.  Checking the size of the /var/lib/mysql/cacti directory will help in determining this value.  We are recommending 25%% of your systems total memory, but your requirements will vary depending on your systems size.')
			),
		'innodb_doublewrite' => array(
			'value'   => 'ON',
			'measure' => 'equalint',
			'class' => 'error',
			'comment' => __('This settings should remain ON unless your Cacti instances is running on either ZFS or FusionI/O which both have internal journaling to accomodate abrupt system crashes.  However, if you have very good power, and your systems rarely go down and you have backups, turning this setting to OFF can net you almost a 50% increase in database performance.')
			),
		'innodb_additional_mem_pool_size' => array(
			'value'   => '80M',
			'measure' => 'gem',
			'comment' => __('This is where metadata is stored. If you had a lot of tables, it would be useful to increase this.')
			),
		'innodb_lock_wait_timeout' => array(
			'value'   => '50',
			'measure' => 'ge',
			'comment' => __('Rogue queries should not for the database to go offline to others.  Kill these queries before they kill your system.')
			),
		'innodb_flush_method' => array(
			'value'   => 'O_DIRECT',
			'measure' => 'eq',
			'comment' => __('Maximum I/O performance happens when you use the O_DIRECT method to flush pages.')
			)
	);

	if (isset($variables['innodb_version'])) {
		if (version_compare($variables['innodb_version'], '5.6', '<')) {
			$recommendations += array(
				'innodb_flush_log_at_trx_commit' => array(
					'value'   => '2',
					'measure' => 'equal',
					'comment' => __('Setting this value to 2 means that you will flush all transactions every second rather than at commit.  This allows %s to perform writing less often.', $database)
				),
				'innodb_file_io_threads' => array(
					'value'   => '16',
					'measure' => 'ge',
					'comment' => __('With modern SSD type storage, having multiple io threads is advantageous for applications with high io characteristics.')
					)
			);
		} elseif ($database == 'MariaDB' && version_compare($variables['innodb_version'], '10.5', '<') || $database == 'MySQL') {
			$recommendations += array(
				'innodb_flush_log_at_timeout' => array(
					'value'   => '3',
					'measure'  => 'ge',
					'comment'  => __('As of %s %s, the you can control how often %s flushes transactions to disk.  The default is 1 second, but in high I/O systems setting to a value greater than 1 can allow disk I/O to be more sequential', $database, $version, $database),
					),
				'innodb_read_io_threads' => array(
					'value'   => '32',
					'measure' => 'ge',
					'comment' => __('With modern SSD type storage, having multiple read io threads is advantageous for applications with high io characteristics.  Depending on your MariaDB/MySQL versions, this value can go as high as 64.  But try to keep the number less than your total SMT threads on the database server.')
					),
				'innodb_write_io_threads' => array(
					'value'   => '16',
					'measure' => 'ge',
					'comment' => __('With modern SSD type storage, having multiple write io threads is advantageous for applications with high io characteristics.  Depending on your MariaDB/MySQL versions, this value can go as high as 64.  But try to keep the number less than your total SMT threads on the database server.')
					),
				'innodb_buffer_pool_instances' => array(
					'value' => '16',
					'measure' => 'pinst',
					'class' => 'warning',
					'comment' => ($database == 'MySQL' ? __('%s will divide the innodb_buffer_pool into memory regions to improve performance for versions of MySQL upto and including MySQL 8.0.  The max value is 64, but should not exceed more than the number of CPU cores/threads.  When your innodb_buffer_pool is less than 1GB, you should use the pool size divided by 128MB.  Continue to use this equation up to the max of the number of CPU cores or 64.', $database): __('%s will divide the innodb_buffer_pool into memory regions to improve performance for versions of MariaDB less than 10.5.  The max value is 64, but should not exceed more than the number of CPU cores/threads.  When your innodb_buffer_pool is less than 1GB, you should use the pool size divided by 128MB.  Continue to use this equation up to the max the number of CPU cores or 64.', $database))
					),
				'innodb_io_capacity' => array(
					'value' => '5000',
					'measure' => 'ge',
					'class' => 'warning',
					'comment' => __('If you have SSD disks, use this suggestion.  If you have physical hard drives, use 200 * the number of active drives in the array.  If using NVMe or PCIe Flash, much larger numbers as high as 100000 can be used.')
					),
				'innodb_io_capacity_max' => array(
					'value' => '10000',
					'measure' => 'ge',
					'class' => 'warning',
					'comment' => __('If you have SSD disks, use this suggestion.  If you have physical hard drives, use 2000 * the number of active drives in the array.  If using NVMe or PCIe Flash, much larger numbers as high as 200000 can be used.')
					),
				'innodb_flush_neighbor_pages' => array(
					'value' => 'none',
					'measure' => 'eq',
					'class' => 'warning',
					'comment' => __('If you have SSD disks, use this suggestion. Otherwise, do not set this setting.')
					)
			);
		} else {
			$recommendations += array(
				'innodb_flush_log_at_timeout' => array(
					'value'   => '3',
					'measure'  => 'ge',
					'comment'  => __('As of %s %s, the you can control how often %s flushes transactions to disk.  The default is 1 second, but in high I/O systems setting to a value greater than 1 can allow disk I/O to be more sequential', $database, $version, $database),
					),
				'innodb_read_io_threads' => array(
					'value'   => '32',
					'measure' => 'ge',
					'comment' => __('With modern SSD type storage, having multiple read io threads is advantageous for applications with high io characteristics.')
					),
				'innodb_write_io_threads' => array(
					'value'   => '16',
					'measure' => 'ge',
					'comment' => __('With modern SSD type storage, having multiple write io threads is advantageous for applications with high io characteristics.')
					),
				'innodb_io_capacity' => array(
					'value' => '5000',
					'measure' => 'ge',
					'class' => 'warning',
					'comment' => __('If you have SSD disks, use this suggestion.  If you have physical hard drives, use 200 * the number of active drives in the array.  If using NVMe or PCIe Flash, much larger numbers as high as 100000 can be used.')
					),
				'innodb_io_capacity_max' => array(
					'value' => '10000',
					'measure' => 'ge',
					'class' => 'warning',
					'comment' => __('If you have SSD disks, use this suggestion.  If you have physical hard drives, use 2000 * the number of active drives in the array.  If using NVMe or PCIe Flash, much larger numbers as high as 200000 can be used.')
					),
				'innodb_flush_neighbor_pages' => array(
					'value' => 'none',
					'measure' => 'eq',
					'class' => 'warning',
					'comment' => __('If you have SSD disks, use this suggestion. Otherwise, do not set this setting.')
					)
			);

			unset($recommendations['innodb_additional_mem_pool_size']);
		}
	}

	if ($database == 'MariaDB' && version_compare($version, '10.2.4', '>')) {
		$recommendations['innodb_doublewrite'] = array(
			'value'   => 'OFF',
			'measure' => 'equalint',
			'class' => 'error',
			'comment' => __('When using MariaDB 10.2.4 and above, this setting should be off if atomic writes are enabled.  Therefore, please enable atomic writes instead of the double write buffer as it will increase performance.')
		);

		$recommendations['innodb_use_atomic_writes'] = array(
			'value'   => 'ON',
			'measure' => 'equalint',
			'class' => 'error',
			'comment' => __('When using MariaDB 10.2.4 and above, you can use atomic writes over the doublewrite buffer to increase performance.')
		);
	}

	if (file_exists('/etc/my.cnf.d/server.cnf')) {
		$location = '/etc/my.cnf.d/server.cnf';
	} else {
		$location = '/etc/my.cnf';
	}

	if ($database == 'MariaDB') {
		$variables_url = 'https://mariadb.com/kb/en/server-system-variables/';
	} else {
		$variables_url = html_escape('https://dev.mysql.com/doc/refman/' . $link_ver . '/en/server-system-variables.html');
	}

	print '<tr class="tableHeader tableFixed">';
	print '<th colspan="2">' . __('%s Tuning', $database) . ' (' . $location . ') - [ <a class="linkOverDark" target="_blank" href="' . $variables_url . '">' .  __('Documentation') . '</a> ] ' . __('Note: Many changes below require a database restart') . '</th>';
	print '</tr>';

	form_alternate_row();
	print "<td colspan='2' style='text-align:left;padding:0px'>";
	print "<table id='mysql' class='cactiTable' style='width:100%'>";
	print "<thead>";
	print "<tr class='tableHeader'>";
	print "  <th class='tableSubHeaderColumn'>" . __('Variable')          . "</th>";
	print "  <th class='tableSubHeaderColumn right'>" . __('Current Value'). "</th>";
	print "  <th class='tableSubHeaderColumn center'>&nbsp;</th>";
	print "  <th class='tableSubHeaderColumn'>" . __('Recommended Value') . "</th>";
	print "  <th class='tableSubHeaderColumn'>" . __('Comments')          . "</th>";
	print "</tr>";
	print "</thead>";

	$innodb_pool_size = 0;
	foreach ($recommendations as $name => $r) {
		if (isset($variables[$name])) {
			$class = '';

			// Unset $passed so that we only display fields that we checked
			unset($passed);

			$compare = '';
			$value_recommend = isset($r['value']) ? $r['value'] : '<unset>';
			$value_current   = isset($variables[$name]) ? $variables[$name] : '<unset>';
			$value_display   = $value_current;

			switch($r['measure']) {
			case 'gem':
				$compare = '>=';
				$value_display = ($variables[$name]/1024/1024) . ' M';
				$value = trim($r['value'], 'M') * 1024 * 1024;
				if ($variables[$name] < $value) {
					$passed = false;
				}
				break;
			case 'ge':
				$compare = '>=';
				$passed = (version_compare($value_current, $value_recommend, '>='));
				break;
			case 'equalint':
			case 'equal':
				$compare = '=';
				if (!isset($variables[$name])) {
					$passed = false;
				} else {
					$e_var = $variables[$name];
					$e_rec = $value_recommend;
					if ($r['measure'] == 'equalint') {
						$e_var = (!strcasecmp('on', $e_var) ? 1 : (!strcasecmp('off', $e_var) ? 0 : $e_var));
						$e_rec = (!strcasecmp('on', $e_rec) ? 1 : (!strcasecmp('off', $e_rec) ? 0 : $e_rec));
					}
					$passed = $e_var == $e_rec;
				}
				break;
			case 'pmem':
				if (isset($memInfo['MemTotal'])) {
					$totalMem = $memInfo['MemTotal'];
				} elseif (isset($memInfo['TotalVisibleMemorySize'])) {
					$totalMem = $memInfo['TotalVisibleMemorySize'];
				} else {
					break;
				}

				if ($name == 'innodb_buffer_pool_size') {
					$innodb_pool_size = $variables[$name];
				}

				$compare = '>=';
				$passed = ($variables[$name] >= ($r['value']*$totalMem/100));
				$value_display = round($variables[$name]/1024/1024, 2) . ' M';
				$value_recommend = round($r['value']*$totalMem/100/1024/1024, 2) . ' M';
				break;
			case 'cmem':
				if (isset($memInfo['MemTotal'])) {
					$totalMem = $memInfo['MemTotal'];
				} elseif (isset($memInfo['TotalVisibleMemorySize'])) {
					$totalMem = $memInfo['TotalVisibleMemorySize'];
				} else {
					break;
				}

				if ($config['poller_id'] == 1) {
					$maxConnections = db_fetch_cell('SELECT @@GLOBAL.max_connections');
				} else {
					$maxConnections = db_fetch_cell('SELECT @@GLOBAL.max_connections', '', false, $local_db_cnn_id);
				}

				if ($name == 'sort_buffer_size') {
					if ($config['poller_id'] == 1) {
						if (($database == 'MySQL' && version_compare($version, '8.0', '<')) || $database == 'MariaDB') {
							$totalMemorySans = db_fetch_cell('SELECT @@GLOBAL.key_buffer_size +
								@@GLOBAL.query_cache_size +
								@@GLOBAL.tmp_table_size +
								@@GLOBAL.innodb_buffer_pool_size +
								@@GLOBAL.innodb_log_buffer_size
								+ @@GLOBAL.max_connections * (
									@@GLOBAL.join_buffer_size +
									@@GLOBAL.read_buffer_size +
									@@GLOBAL.read_rnd_buffer_size +
									@@GLOBAL.thread_stack +
									@@GLOBAL.binlog_cache_size)');
						} else {
							$totalMemorySans = db_fetch_cell('SELECT @@GLOBAL.key_buffer_size +
								@@GLOBAL.tmp_table_size +
								@@GLOBAL.innodb_buffer_pool_size +
								@@GLOBAL.innodb_log_buffer_size
								+ @@GLOBAL.max_connections * (
									@@GLOBAL.join_buffer_size +
									@@GLOBAL.read_buffer_size +
									@@GLOBAL.read_rnd_buffer_size +
									@@GLOBAL.thread_stack +
									@@GLOBAL.binlog_cache_size)');
						}
					} else {
						if (($database == 'MySQL' && version_compare($version, '8.0', '<')) || $database == 'MariaDB') {
							$totalMemorySans = db_fetch_cell('SELECT @@GLOBAL.key_buffer_size +
								@@GLOBAL.query_cache_size +
								@@GLOBAL.tmp_table_size +
								@@GLOBAL.innodb_buffer_pool_size +
								@@GLOBAL.innodb_log_buffer_size
								+ @@GLOBAL.max_connections * (
									@@GLOBAL.join_buffer_size +
									@@GLOBAL.read_buffer_size +
									@@GLOBAL.read_rnd_buffer_size +
									@@GLOBAL.thread_stack +
									@@GLOBAL.binlog_cache_size)', '', false, $local_db_cnn_id);
						} else {
							$totalMemorySans = db_fetch_cell('SELECT @@GLOBAL.key_buffer_size +
								@@GLOBAL.tmp_table_size +
								@@GLOBAL.innodb_buffer_pool_size +
								@@GLOBAL.innodb_log_buffer_size
								+ @@GLOBAL.max_connections * (
									@@GLOBAL.join_buffer_size +
									@@GLOBAL.read_buffer_size +
									@@GLOBAL.read_rnd_buffer_size +
									@@GLOBAL.thread_stack +
									@@GLOBAL.binlog_cache_size)', '', false, $local_db_cnn_id);
						}
					}
				} else {
					if ($config['poller_id'] == 1) {
						if (($database == 'MySQL' && version_compare($version, '8.0', '<')) || $database == 'MariaDB') {
							$totalMemorySans = db_fetch_cell('SELECT @@GLOBAL.key_buffer_size +
								@@GLOBAL.query_cache_size +
								@@GLOBAL.tmp_table_size +
								@@GLOBAL.innodb_buffer_pool_size +
								@@GLOBAL.innodb_log_buffer_size
								+ @@GLOBAL.max_connections * (
									@@GLOBAL.sort_buffer_size +
									@@GLOBAL.read_buffer_size +
									@@GLOBAL.read_rnd_buffer_size +
									@@GLOBAL.thread_stack +
									@@GLOBAL.binlog_cache_size)');
						} else {
							$totalMemorySans = db_fetch_cell('SELECT @@GLOBAL.key_buffer_size +
								@@GLOBAL.tmp_table_size +
								@@GLOBAL.innodb_buffer_pool_size +
								@@GLOBAL.innodb_log_buffer_size
								+ @@GLOBAL.max_connections * (
									@@GLOBAL.sort_buffer_size +
									@@GLOBAL.read_buffer_size +
									@@GLOBAL.read_rnd_buffer_size +
									@@GLOBAL.thread_stack +
									@@GLOBAL.binlog_cache_size)');
						}
					} else {
						if (($database == 'MySQL' && version_compare($version, '8.0', '<')) || $database == 'MariaDB') {
							$totalMemorySans = db_fetch_cell('SELECT @@GLOBAL.key_buffer_size +
								@@GLOBAL.query_cache_size +
								@@GLOBAL.tmp_table_size +
								@@GLOBAL.innodb_buffer_pool_size +
								@@GLOBAL.innodb_log_buffer_size
								+ @@GLOBAL.max_connections * (
									@@GLOBAL.sort_buffer_size +
									@@GLOBAL.read_buffer_size +
									@@GLOBAL.read_rnd_buffer_size +
									@@GLOBAL.thread_stack +
									@@GLOBAL.binlog_cache_size)', '', false, $local_db_cnn_id);
						} else {
							$totalMemorySans = db_fetch_cell('SELECT @@GLOBAL.key_buffer_size +
								@@GLOBAL.tmp_table_size +
								@@GLOBAL.innodb_buffer_pool_size +
								@@GLOBAL.innodb_log_buffer_size
								+ @@GLOBAL.max_connections * (
									@@GLOBAL.sort_buffer_size +
									@@GLOBAL.read_buffer_size +
									@@GLOBAL.read_rnd_buffer_size +
									@@GLOBAL.thread_stack +
									@@GLOBAL.binlog_cache_size)', '', false, $local_db_cnn_id);
						}
					}
				}

				$remainingMem = ($totalMem * 0.8) - $totalMemorySans;

				$recommendation = $remainingMem / $maxConnections;

				$compare = '<=';
				$passed = ($variables[$name] >= ($recommendation/1024/1024)) && $recommendation > 0;
				$value_display = round($variables[$name]/1024/1024, 2) . ' M';
				$value_recommend = round($recommendation/1024/1024, 2) . ' M';

				break;
			case 'pinst':
				$compare = '>=';

				// Divide the buffer pool size by 128MB, and ensure 1 or more
				$pool_instances = round(($innodb_pool_size / 1024 / 1024 / 128) + 0.5);

				if ($config['cacti_server_os'] == 'win32') {
					$nproc = getenv('NUMBER_OF_PROCESSORS');
				} else {
					$nproc = system('nproc');
				}

				if ($pool_instances > $nproc) {
					$pool_instances = $nproc;
				}

				if ($pool_instances < 1) {
					$pool_instances = 1;
				} elseif ($pool_instances > 64) {
					$pool_instances = 64;
				}

				$passed = ($variables[$name] >= $pool_instances);
				$value_recommend = $pool_instances;
				break;
			default:
				$compare = $r['measure'];
				$passed = true;
			}

			if (isset($passed)) {
				if (!$passed) {
					if (isset($r['class']) && $r['class'] == 'warning') {
						$class = 'textWarning';
						if ($result == DB_STATUS_SUCCESS) {
							$result = DB_STATUS_WARNING;
						}
					} else {
						$class = 'textError';
						if ($result != DB_STATUS_ERROR) {
							$result = DB_STATUS_ERROR;
						}
					}
				}

				form_alternate_row();

				print "<td>" . $name . "</td>";
				print "<td class='right $class'>$value_display</td>";
				print "<td class='center'>$compare</td>";
				print "<td>$value_recommend</td>";
				print "<td class='$class'>" . $r['comment'] . "</td>";

				form_end_row();
			}

		}
	}
	print "</table>";
	print "</td>";
	form_end_row();
	return $result;
}

function utilities_php_modules() {
	/*
	   Gather phpinfo into a string variable - This has to be done before
	   any headers are sent to the browser, as we are going to do some
	   output buffering fun
	*/

	ob_start();
	phpinfo(INFO_MODULES);
	$php_info = ob_get_contents();
	ob_end_clean();

	/* Remove nasty style sheets, links and other junk */
	$php_info = str_replace("\n", '', $php_info);
	$php_info = preg_replace('/^.*\<body\>/', '', $php_info);
	$php_info = preg_replace('/\<\/body\>.*$/', '', $php_info);
	$php_info = preg_replace('/(\<a name.*\>)([^<>]*)(\<\/a\>)/U', '$2', $php_info);
	$php_info = preg_replace('/\<img.*\>/U', '', $php_info);
	$php_info = preg_replace('/\<div[^<>]*\>\<\/div\>/U', '', $php_info);
	$php_info = preg_replace('/\<\/?address\>/', '', $php_info);

	return $php_info;
}

function memory_bytes($val) {
	$val  = trim($val);
	$last = strtolower($val[strlen($val)-1]);
	$val  = trim($val, 'GMKgmk');
	switch($last) {
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}

function memory_readable($val) {
	if ($val < 1024) {
		$val_label = 'bytes';
	} elseif ($val < 1048576) {
		$val_label = 'K';
		$val /= 1024;
	} elseif ($val < 1073741824) {
		$val_label = 'M';
		$val /= 1048576;
	} else {
		$val_label = 'G';
		$val /= 1073741824;
	}

	return $val . $val_label;
}

function utilities_get_system_memory() {
	global $config;

	$memInfo = array();

	if ($config['cacti_server_os'] == 'win32') {
		exec('wmic os get FreePhysicalMemory', $memInfo['FreePhysicalMemory']);
		exec('wmic os get FreeSpaceInPagingFiles', $memInfo['FreeSpaceInPagingFiles']);
		exec('wmic os get FreeVirtualMemory', $memInfo['FreeVirtualMemory']);
		exec('wmic os get SizeStoredInPagingFiles', $memInfo['SizeStoredInPagingFiles']);
		exec('wmic os get TotalVirtualMemorySize', $memInfo['TotalVirtualMemorySize']);
		exec('wmic os get TotalVisibleMemorySize', $memInfo['TotalVisibleMemorySize']);
		if (cacti_sizeof($memInfo)) {
			foreach ($memInfo as $key => $values) {
				$memInfo[$key] = $values[1] * 1000;
			}
		}
	} else {
		$file = '';
		if (file_exists('/proc/meminfo')) {
			$file = '/proc/meminfo';
		} elseif(file_exists('/linux/proc/meminfo')) {
			$file = '/linux/proc/meminfo';
		} elseif(file_exists('/compat/linux/proc/meminfo')) {
			$file = '/compat/linux/proc/meminfo';
		} elseif(file_exists('/usr/compat/linux/proc/meminfo')) {
			$file = '/usr/compat/linux/proc/meminfo';
		}

		if ($file != '') {
			$data = explode("\n", file_get_contents($file));
			foreach ($data as $l) {
				if (trim($l) != '') {
					list($key, $val) = explode(':', $l);
					$val = trim($val, " kBb\r\n");
					$memInfo[$key] = round($val * 1000,0);
				}
			}
		} elseif (file_exists('/usr/bin/free')) {
			$menInfo   = array();
			$output    = array();
			$exit_code = 0;

			exec('/usr/bin/free', $output, $exit_code);
			if ($exit_code == 0) {
				foreach ($output as $line) {
					$parts = preg_split('/\s+/', $line);
					switch ($parts[0]) {
					case 'Mem:':
						$memInfo['MemTotal']  = (isset($parts[1]) ? $parts[1]*1000:0);
						$memInfo['MemUsed']   = (isset($parts[2]) ? $parts[2]*1000:0);
						$memInfo['MemFree']   = (isset($parts[3]) ? $parts[3]*1000:0);
						$memInfo['MemShared'] = (isset($parts[4]) ? $parts[4]*1000:0);
						$memInfo['Buffers']   = (isset($parts[5]) ? $parts[5]*1000:0);
						$memInfo['Cached']    = (isset($parts[6]) ? $parts[6]*1000:0);
						break;
					case '-/+':
						$memInfo['Active']    = (isset($parts[2]) ? $parts[3]*1000:0);
						$memInfo['Inactive']  = (isset($parts[3]) ? $parts[3]*1000:0);
						break;
					case 'Swap:':
						$memInfo['SwapTotal'] = (isset($parts[1]) ? $parts[1]*1000:0);
						$memInfo['SwapUsed']  = (isset($parts[2]) ? $parts[2]*1000:0);
						break;
					}
				}
			}
		}
	}

	return $memInfo;
}

function utility_php_sort_extensions($a, $b) {
	$name_a = isset($a['name']) ? $a['name'] : '';
	$name_b = isset($b['name']) ? $b['name'] : '';
	return strcasecmp($name_a, $name_b);
}


function utility_php_extensions() {
	global $config;

	$php = cacti_escapeshellcmd(read_config_option('path_php_binary', true));
	$php_file = cacti_escapeshellarg($config['base_path'] . '/install/cli_check.php') . ' extensions';
	$json = shell_exec($php . ' -q ' . $php_file);
	$ext = @json_decode($json, true);

	utility_php_verify_extensions($ext, 'web');
	utility_php_set_installed($ext);

	return $ext;
}

function utility_php_verify_extensions(&$extensions, $source) {
	global $config;

	if (empty($extensions)) {
		$extensions = array(
			'ctype'     => array('cli' => false, 'web' => false),
			'date'      => array('cli' => false, 'web' => false),
			'filter'    => array('cli' => false, 'web' => false),
			'gd'        => array('cli' => false, 'web' => false),
			'gmp'       => array('cli' => false, 'web' => false),
			'hash'      => array('cli' => false, 'web' => false),
			'intl'      => array('cli' => false, 'web' => false),
			'json'      => array('cli' => false, 'web' => false),
			'ldap'      => array('cli' => false, 'web' => false),
			'mbstring'  => array('cli' => false, 'web' => false),
			'openssl'   => array('cli' => false, 'web' => false),
			'pcre'      => array('cli' => false, 'web' => false),
			'PDO'       => array('cli' => false, 'web' => false),
			'pdo_mysql' => array('cli' => false, 'web' => false),
			'session'   => array('cli' => false, 'web' => false),
			'simplexml' => array('cli' => false, 'web' => false),
			'sockets'   => array('cli' => false, 'web' => false),
			'spl'       => array('cli' => false, 'web' => false),
			'standard'  => array('cli' => false, 'web' => false),
			'xml'       => array('cli' => false, 'web' => false),
			'zlib'      => array('cli' => false, 'web' => false)
		);

		if ($config['cacti_server_os'] == 'unix') {
			$extensions['posix'] = array('cli' => false, 'web' => false);
			$extensions['pcntl'] = array('cli' => false, 'web' => true);
		} else {
			$extensions['com_dotnet'] = array('cli' => false, 'web' => false);
		}
	}

	uksort($extensions, 'utility_php_sort_extensions');

	foreach ($extensions as $name=>$extension) {
		if (extension_loaded($name)){
			$extensions[$name][$source] = true;
		}
	}
}

function utility_php_recommends() {
	global $config;

	$php = cacti_escapeshellcmd(read_config_option('path_php_binary', true));
	$php_file = cacti_escapeshellarg($config['base_path'] . '/install/cli_check.php') . ' recommends';
	$json = shell_exec($php . ' -q ' . $php_file);
	$ext = array('web' => '', 'cli' => '');
	$ext['cli'] = @json_decode($json, true);

	utility_php_verify_recommends($ext['web'], 'web');
	utility_php_set_recommends_text($ext);

	return $ext;
}

function utility_get_formatted_bytes($input_value, $wanted_type, &$output_value, $default_type = 'B') {

	$default_type = strtoupper($default_type);
	$multiplier = array(
		'B' => 1,
		'K' => 1024,
		'M' => 1024*1024,
		'G' => 1024*1024*1024,
	);

	if ($input_value > 0 && preg_match('/([0-9.]+)([BKMG]){0,1}/i',$input_value,$matches)) {
		$input_value = $matches[1];
		if (isset($matches[2])) {
			$default_type = $matches[2];
		}

		if (isset($multiplier[$default_type])) {
			$input_value = $input_value * $multiplier[$default_type];
		}
	}

	if (intval($input_value) < 0) {
		$output_value = $input_value . $wanted_type;
	} elseif (isset($multiplier[$wanted_type])) {
		$output_value = ($input_value / $multiplier[$wanted_type]) . $wanted_type;
	} elseif (isset($multiplier[$default_type])) {
		$output_value = ($input_value * $multiplier[$default_type]) . $default_type;
	} else {
		$output_value = $input_value . 'B';
	}
	return $input_value;
}

function utility_php_verify_recommends(&$recommends, $source) {
	global $original_memory_limit;

	$rec_version    = '5.4.0';
	$rec_memory_mb  = (version_compare(PHP_VERSION, '7.0.0', '<') ? 800 : 400);
	$rec_execute_m  = 1;
	$memory_ini     = (isset($original_memory_limit) ? $original_memory_limit : ini_get('memory_limit'));

	// adjust above appropriately (used in configs)
	$rec_execute    = $rec_execute_m * 60;
	$rec_memory     = utility_get_formatted_bytes($rec_memory_mb, 'M', $rec_memory_mb, 'M');
	$memory_limit   = utility_get_formatted_bytes($memory_ini, 'M', $memory_ini, 'B');

	$execute_time   = ini_get('max_execution_time');
	$timezone       = ini_get('date.timezone');

	$cfg_values     = parse_ini_file(get_cfg_var('cfg_file_path'));
	$cfg_mem_limit  = empty($cfg_values['memory_limit']) ? '' : $cfg_values['memory_limit'];
	$cfg_timezone   = empty($cfg_values['date.timezone']) ? '' : $cfg_values['date.timezone'];
	$cfg_max_exec   = empty($cfg_values['max_execution_time']) ? '' : $cfg_values['max_execution_time'];

	$recommends = array(
		array(
			'name'        => 'location',
			'value'       => get_cfg_var('cfg_file_path'),
			'current'     => get_cfg_var('cfg_file_path'),
			'status'      => 2,
		),
		array(
			'name'        => 'version',
			'value'       => $rec_version,
			'current'     => PHP_VERSION,
			'status'      => version_compare(PHP_VERSION, $rec_version, '>=') ? DB_STATUS_SUCCESS : DB_STATUS_ERROR,
		),
		array(
			'name'        => 'memory_limit',
			'value'       => $rec_memory_mb,
			'current'     => $memory_ini,
			'status'      => (($memory_limit <= 0 || $memory_limit >= $rec_memory) ? DB_STATUS_SUCCESS :($memory_limit != $cfg_mem_limit ? DB_STATUS_RESTART :  DB_STATUS_WARNING)),
		),
		array(
			'name'        => 'max_execution_time',
			'value'       => $rec_execute,
			'current'     => $execute_time,
			'status'      => (($execute_time <= 0 || $execute_time >= $rec_execute) ? DB_STATUS_SUCCESS : ($execute_time != $cfg_max_exec ? DB_STATUS_RESTART : DB_STATUS_WARNING)),
		),
		array(
			'name'        => 'date.timezone',
			'value'       => '<timezone>',
			'current'     => $timezone,
			'status'      => ($timezone ? DB_STATUS_SUCCESS : ($timezone != $cfg_timezone ? DB_STATUS_RESTART : DB_STATUS_ERROR)),
		),
	);
}

function utility_php_set_recommends_text(&$recs) {
	if (is_array($recs) && cacti_sizeof($recs)) {
		foreach ($recs as $name => $recommends) {
			if (cacti_sizeof($recommends)) {
				foreach ($recommends as $index => $recommend) {
					if ($recommend['name'] == 'version') {
						$recs[$name][$index]['description'] = __('PHP %s is the minimum version', $recommend['value']);
					} elseif ($recommend['name'] == 'memory_limit') {
						$recs[$name][$index]['description'] = __('A minimum of %s memory limit', $recommend['value']);
					} elseif ($recommend['name'] == 'max_execution_time') {
						$recs[$name][$index]['description'] = __('A minimum of %s m execution time', $recommend['value']);
					} elseif ($recommend['name'] == 'date.timezone') {
						$recs[$name][$index]['description'] = __('A valid timezone that matches MySQL and the system');
					}
				}
			}
		}
	}
}

function utility_php_optionals() {
	global $config;

	$php = cacti_escapeshellcmd(read_config_option('path_php_binary', true));
	$php_file = cacti_escapeshellarg($config['base_path'] . '/install/cli_check.php') . ' optionals';
	$json = shell_exec($php . ' -q ' . $php_file);
	$opt = @json_decode($json, true);

	utility_php_verify_optionals($opt, 'web');
	utility_php_set_installed($opt);

	return $opt;
}

function utility_php_verify_optionals(&$optionals, $source) {
	if (empty($optionals)) {
		$optionals = array(
			'snmp'          => array('web' => false, 'cli' => false),
			'gettext'       => array('web' => false, 'cli' => false),
			'TrueType Box'  => array('web' => false, 'cli' => false),
			'TrueType Text' => array('web' => false, 'cli' => false),
		);
	}

	foreach ($optionals as $name => $optional) {
		if (extension_loaded($name)){
			$optionals[$name][$source] = true;
		}
	}

	$optionals['TrueType Box'][$source] = function_exists('imagettfbbox');
	$optionals['TrueType Text'][$source] = function_exists('imagettftext');
}

function utility_php_set_installed(&$extensions) {
	foreach ($extensions as $name=>$extension) {
		$extensions[$name]['installed'] = $extension['web'] && $extension['cli'];
	}
}
