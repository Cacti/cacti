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

/* update_replication_crc - update hash stored in settings table to inform
   remote pollers to replicate tables
   @arg $poller_id - the id of the poller impacted by hash update
   @arg $variable  - the variable name to store in the settings table */
function update_replication_crc($poller_id, $variable) {
	$hash = hash('ripemd160', date('Y-m-d H:i:s') . rand() . $poller_id);

	db_execute_prepared("REPLACE INTO settings SET value = ?, name='$variable" . ($poller_id > 0 ? "_" . "$poller_id'":"'"), array($hash));
}

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

	$poller_ids = array_rekey(db_fetch_assoc('SELECT DISTINCT poller_id FROM poller_item'), 'poller_id', 'poller_id');
	if (sizeof($poller_ids)) {
		foreach($poller_ids as $poller_id) {
			api_data_source_cache_crc_update($poller_id);
		}
	}

	/* update the field mappings for the poller */
	db_execute("TRUNCATE TABLE poller_data_template_field_mappings");
	db_execute("INSERT IGNORE INTO poller_data_template_field_mappings
		SELECT dtr.data_template_id, dif.data_name, GROUP_CONCAT(dtr.data_source_name ORDER BY dtr.data_source_name) AS data_source_names, NOW()
		FROM data_template_rrd AS dtr
		INNER JOIN data_input_fields AS dif
		ON dtr.data_input_field_id = dif.id
		WHERE dtr.local_data_id=0
		GROUP BY dtr.data_template_id, dif.data_name");
}

function update_poller_cache_from_query($host_id, $data_query_id) {
	$poller_data = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' *
		FROM data_local
		WHERE host_id = ?
		AND snmp_query_id = ?',
		array($host_id, $data_query_id));

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

	$poller_ids = array_rekey(
		db_fetch_assoc_prepared('SELECT DISTINCT poller_id
			FROM poller_item
			WHERE host_id = ?',
			array($host_id)),
		'poller_id', 'poller_id'
	);

	if (sizeof($poller_ids)) {
		foreach($poller_ids as $poller_id) {
			api_data_source_cache_crc_update($poller_id);
		}
	}
}

function update_poller_cache($data_source, $commit = false) {
	global $config;

	include_once($config['library_path'] . '/data_query.php');
	include_once($config['library_path'] . '/api_poller.php');

	if (!is_array($data_source)) {
		$data_source = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' *
			FROM data_local
			WHERE id = ?',
			array($data_source));
	}

	$poller_items = array();

	$data_input = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . '
		di.id, di.type_id, dtd.id AS data_template_data_id,
		dtd.data_template_id, dtd.active, dtd.rrd_step
		FROM data_template_data AS dtd
		INNER JOIN data_input AS di
		ON dtd.data_input_id=di.id
		WHERE dtd.local_data_id = ?',
		array($data_source['id']));

	if (sizeof($data_input)) {
		/* we have to perform some additional sql queries if this is a 'query' */
		if (($data_input['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY) ||
			($data_input['type_id'] == DATA_INPUT_TYPE_SCRIPT_QUERY) ||
			($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER)){
			$field = data_query_field_list($data_input['data_template_data_id']);

			$params = array();
			if ($field['output_type'] != '') {
				$output_type_sql = ' AND sqgr.snmp_query_graph_id = ' . $field['output_type'];
			} else {
				$output_type_sql = '';
			}
			$params[] = $data_input['data_template_id'];
			$params[] = $data_source['id'];

			$outputs = db_fetch_assoc_prepared('SELECT DISTINCT ' . SQL_NO_CACHE . "
				sqgr.snmp_field_name, dtr.id as data_template_rrd_id
				FROM snmp_query_graph_rrd AS sqgr
				INNER JOIN data_template_rrd AS dtr FORCE INDEX (local_data_id)
				ON sqgr.data_template_rrd_id = dtr.local_data_template_rrd_id
				WHERE sqgr.data_template_id = ?
				AND dtr.local_data_id = ?
				$output_type_sql
				ORDER BY dtr.id", $params);
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
				} else {
					$action = POLLER_ACTION_SCRIPT;
					$script_path = get_full_script_path($data_source['id']);
				}

				$num_output_fields = sizeof(db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' id
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
			}else if ($data_input['type_id'] == DATA_INPUT_TYPE_SNMP) { /* snmp */
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

				$data_template_rrd_id = db_fetch_cell_prepared('SELECT ' . SQL_NO_CACHE . ' id
					FROM data_template_rrd
					WHERE local_data_id = ?',
					array($data_source['id']));

				$poller_items[] = api_poller_cache_item_add($data_source['host_id'], $host_fields, $data_source['id'], $data_input['rrd_step'], 0, get_data_source_item_name($data_template_rrd_id), 1, (isset($host_fields['snmp_oid']) ? $host_fields['snmp_oid'] : ''));
			}else if ($data_input['type_id'] == DATA_INPUT_TYPE_SNMP_QUERY) { /* snmp query */
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

				if (sizeof($outputs) && sizeof($snmp_queries)) {
					foreach ($outputs as $output) {
						if (isset($snmp_queries['fields'][$output['snmp_field_name']]['oid'])) {
							$oid = $snmp_queries['fields'][$output['snmp_field_name']]['oid'] . '.' . $data_source['snmp_index'];

							if (isset($snmp_queries['fields'][$output['snmp_field_name']]['oid_suffix'])) {
								$oid .= '.' . $snmp_queries['fields'][$output['snmp_field_name']]['oid_suffix'];
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

				if (sizeof($outputs) && sizeof($script_queries)) {
					foreach ($outputs as $output) {
						if (isset($script_queries['fields'][$output['snmp_field_name']]['query_name'])) {
							$identifier = $script_queries['fields'][$output['snmp_field_name']]['query_name'];

							/* fall back to non-script server actions if the user is running a version of php older than 4.3 */
							if (($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER) && (function_exists('proc_open'))) {
								$action = POLLER_ACTION_SCRIPT_PHP;

								$prepend = '';
								if (isset($script_queries['arg_prepend']) && $script_queries['arg_prepend'] != '') {
									$prepend = $script_queries['arg_prepend'];
								}

								$script_path = get_script_query_path(trim($prepend . ' ' . $script_queries['arg_get'] . ' ' . $identifier . ' ' . $data_source['snmp_index']), $script_queries['script_path'] . ' ' . $script_queries['script_function'], $data_source['host_id']);
							}else if (($data_input['type_id'] == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER) && (!function_exists('proc_open'))) {
								$action = POLLER_ACTION_SCRIPT;

								$prepend = '';
								if (isset($script_queries['arg_prepend']) && $script_queries['arg_prepend'] != '') {
									$prepend = $script_queries['arg_prepend'];
								}

								$script_path = read_config_option('path_php_binary') . ' -q ' . get_script_query_path(trim($prepend . ' ' . $script_queries['arg_get'] . ' ' . $identifier . ' ' . $data_source['snmp_index']), $script_queries['script_path'], $data_source['host_id']);
							} else {
								$action = POLLER_ACTION_SCRIPT;
								$script_path = get_script_query_path(trim((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] : '') . ' ' . $script_queries['arg_get'] . ' ' . $identifier . ' ' . $data_source['snmp_index']), $script_queries['script_path'], $data_source['host_id']);
							}
						}

						if (isset($script_path)) {
							$poller_items[] = api_poller_cache_item_add($data_source['host_id'], $host_fields, $data_source['id'], $data_input['rrd_step'], $action, get_data_source_item_name($output['data_template_rrd_id']), sizeof($outputs), $script_path);
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
					(sizeof($poller_items) < sizeof($arguments['poller_items']))) {
					$poller_items = $arguments['poller_items'];
				}
			}
		}
	} else {
		$data_template_data = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' *
			FROM data_template_data
			WHERE local_data_id = ?',
			array($data_source['id']));

		if (sizeof($data_template_data) && $data_template_data['data_input_id'] > 0) {
			cacti_log('WARNING: Repopulate Poller Cache found Data Input Missing for Data Source ' . $data_source['id'] . '.  Database may be corrupted');
		}
	}

	if ($commit && sizeof($poller_items)) {
		poller_update_poller_cache_from_buffer((array)$data_source['id'], $poller_items);
	} elseif (!$commit) {
		return $poller_items;
	}
}

function push_out_data_input_method($data_input_id) {
	$data_sources = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . ' data_local.*
		FROM data_local
		INNER JOIN (
			SELECT DISTINCT local_data_id
			FROM data_template_data
			WHERE data_input_id = ?
			AND local_data_id > 0
		) AS data_template_data ON data_template_data.local_data_id = data_local.id', array($data_input_id));

	$poller_items = array();
	$_my_local_data_ids = array();

	if (sizeof($data_sources)) {
		foreach($data_sources as $data_source) {
			$_my_local_data_ids[] = $data_source['id'];

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

	/* setup somme defaults */
	$overhead     = strlen($sql_prefix) + strlen($sql_suffix);
	$buf_len      = 0;
	$buf_count    = 0;
	$buffer       = '';

	if (sizeof($poller_items)) {
		foreach($poller_items as $record) {
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
	} else {
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
		$hosts[$host_id] = db_fetch_row_prepared('SELECT ' . SQL_NO_CACHE . ' id AS host_id, host.*
			FROM host WHERE id = ?',
			array($host_id));

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
				$hosts[$data_source['host_id']] = db_fetch_row_prepared('SELECT *
					FROM host
					WHERE id = ?',
					array($data_source['host_id']));
			}
			$host = $hosts[$data_source['host_id']];

			/* get field information FROM the data template */
			if (!isset($template_fields[$data_source['local_data_template_data_id']])) {
				$template_fields[$data_source['local_data_template_data_id']] = db_fetch_assoc_prepared('SELECT ' . SQL_NO_CACHE . '
					did.value, did.t_value, dif.id, dif.type_code
					FROM data_input_fields AS dif
					LEFT JOIN data_input_data AS did
					ON dif.id=did.data_input_field_id
					WHERE dif.data_input_id = ?
					AND did.data_template_data_id = ?
					AND (did.t_value="" OR did.t_value is null)
					AND dif.input_output = "in"',
					array($data_source['data_input_id'], $data_source['local_data_template_data_id']));
			}

			/* loop through each field contained in the data template and push out a host value if:
			 - the field is a valid "host field"
			 - the value of the field is empty
			 - the field is set to 'templated' */
			if (sizeof($template_fields[$data_source['local_data_template_data_id']])) {
				foreach ($template_fields[$data_source['local_data_template_data_id']] as $template_field) {
					if (preg_match('/^' . VALID_HOST_FIELDS . '$/i', $template_field['type_code']) && $template_field['value'] == '' && $template_field['t_value'] == '') {
						// handle special case type_code
						if ($template_field['type_code'] == 'host_id') {
							$template_field['type_code'] = 'id';
						}

						db_execute_prepared('REPLACE INTO data_input_data
							(data_input_field_id, data_template_data_id, value)
							VALUES (?, ?, ?)',
							array($template_field['id'], $data_source['id'], $host[$template_field['type_code']]));
					}
				}
			}

			/* flag an update to the poller cache as well */
			$local_data_ids[] = $data_source['local_data_id'];

			/* create a new compatible structure */
			$data = $data_source;
			$data['id'] = $data['local_data_id'];

			$poller_items = array_merge($poller_items, update_poller_cache($data));
		}
	}

	if (sizeof($local_data_ids)) {
		poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
	}

	$poller_id = db_fetch_cell_prepared('SELECT poller_id
		FROM host
		WHERE id = ?',
		array($host_id));

	api_data_source_cache_crc_update($poller_id);
}

function utilities_get_mysql_recommendations() {
	// MySQL/MariaDB Important Variables
	$variables = array_rekey(db_fetch_assoc('SHOW GLOBAL VARIABLES'), 'Variable_name', 'Value');

	$memInfo = utilities_get_system_memory();

	if (strpos($variables['version'], 'MariaDB') !== false) {
		$database = 'MariaDB';
		$version  = str_replace('-MariaDB', '', $variables['version']);

		if (isset($variables['innodb_version'])) {
			$link_ver = substr($variables['innodb_version'], 0, 3);
		} else {
			$link_ver = '5.5';
		}
	} else {
		$database = 'MySQL';
		$version  = $variables['version'];
		$link_ver = substr($variables['version'], 0, 3);
	}

	$recommendations = array(
		'version' => array(
			'value' => '5.6',
			'measure' => 'gt',
			'comment' => __('MySQL 5.6+ and MariaDB 10.0+ are great releases, and are very good versions to choose. Make sure you run the very latest release though which fixes a long standing low level networking issue that was causing spine many issues with reliability.')
			)
	);

	if (isset($variables['innodb_version']) && version_compare($variables['innodb_version'], '5.6', '<')) {
		if (version_compare($link_ver, '5.2', '>=')) {
			if (!isset($variables['innodb_version'])) {
				$recommendations += array(
					'innodb' => array(
						'value' => 'ON',
						'class' => 'warning',
						'measure' => 'equal',
						'comment' => __('It is recommended that you enable InnoDB in any %s version greater than 5.1.', $database)
					)
				);

				$variables['innodb'] = 'UNSET';
			}
		}

		$recommendations += array(
			'collation_server' => array(
				'value' => 'utf8_general_ci',
				'class' => 'warning',
				'measure' => 'equal',
				'comment' => __('When using Cacti with languages other than English, it is important to use the utf8_general_ci collation type as some characters take more than a single byte.  If you are first just now installing Cacti, stop, make the changes and start over again.  If your Cacti has been running and is in production, see the internet for instructions on converting your databases and tables if you plan on supporting other languages.')
				),
			'character_set_client' => array(
				'value' => 'utf8',
				'class' => 'warning',
				'measure' => 'equal',
				'comment' => __('When using Cacti with languages other than English, it is important to use the utf8 character set as some characters take more than a single byte. If you are first just now installing Cacti, stop, make the changes and start over again. If your Cacti has been running and is in production, see the internet for instructions on converting your databases and tables if you plan on supporting other languages.')
				)
		);
	} else {
		if (version_compare($link_ver, '5.2', '>=')) {
			if (!isset($variables['innodb_version'])) {
				$recommendations += array(
					'innodb' => array(
						'value' => 'ON',
						'class' => 'warning',
						'measure' => 'equal',
						'comment' => __('It is recommended that you enable InnoDB in any %s version greater than 5.1.', $database)
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
			'measure' => 'gt',
			'comment' => __('Depending on the number of logins and use of spine data collector, %s will need many connections.  The calculation for spine is: total_connections = total_processes * (total_threads + script_servers + 1), then you must leave headroom for user connections, which will change depending on the number of concurrent login accounts.', $database)
			),
		'max_heap_table_size' => array(
			'value'   => '5',
			'measure' => 'pmem',
			'comment' => __('If using the Cacti Performance Booster and choosing a memory storage engine, you have to be careful to flush your Performance Booster buffer before the system runs out of memory table space.  This is done two ways, first reducing the size of your output column to just the right size.  This column is in the tables poller_output, and poller_output_boost.  The second thing you can do is allocate more memory to memory tables.  We have arbitrarily chosen a recommended value of 10% of system memory, but if you are using SSD disk drives, or have a smaller system, you may ignore this recommendation or choose a different storage engine.  You may see the expected consumption of the Performance Booster tables under Console -> System Utilities -> View Boost Status.')
			),
		'table_cache' => array(
			'value'   => '200',
			'measure' => 'gt',
			'comment' => __('Keeping the table cache larger means less file open/close operations when using innodb_file_per_table.')
			),
		'max_allowed_packet' => array(
			'value'   => 16777216,
			'measure' => 'gt',
			'comment' => __('With Remote polling capabilities, large amounts of data will be synced from the main server to the remote pollers.  Therefore, keep this value at or above 16M.')
			),
		'tmp_table_size' => array(
			'value'   => '64M',
			'measure' => 'gtm',
			'comment' => __('When executing subqueries, having a larger temporary table size, keep those temporary tables in memory.')
			),
		'join_buffer_size' => array(
			'value'   => '64M',
			'measure' => 'gtm',
			'comment' => __('When performing joins, if they are below this size, they will be kept in memory and never written to a temporary file.')
			),
		'innodb_file_per_table' => array(
			'value'   => 'ON',
			'measure' => 'equal',
			'comment' => __('When using InnoDB storage it is important to keep your table spaces separate.  This makes managing the tables simpler for long time users of %s.  If you are running with this currently off, you can migrate to the per file storage by enabling the feature, and then running an alter statement on all InnoDB tables.', $database)
			),
		'innodb_buffer_pool_size' => array(
			'value'   => '25',
			'measure' => 'pmem',
			'comment' => __('InnoDB will hold as much tables and indexes in system memory as is possible.  Therefore, you should make the innodb_buffer_pool large enough to hold as much of the tables and index in memory.  Checking the size of the /var/lib/mysql/cacti directory will help in determining this value.  We are recommending 25% of your systems total memory, but your requirements will vary depending on your systems size.')
			),
		'innodb_doublewrite' => array(
			'value'   => 'OFF',
			'measure' => 'equal',
			'comment' => __('With modern SSD type storage, this operation actually degrades the disk more rapidly and adds a 50% overhead on all write operations.')
			),
		'innodb_additional_mem_pool_size' => array(
			'value'   => '80M',
			'measure' => 'gtm',
			'comment' => __('This is where metadata is stored. If you had a lot of tables, it would be useful to increase this.')
			),
		'innodb_lock_wait_timeout' => array(
			'value'   => '50',
			'measure' => 'gt',
			'comment' => __('Rogue queries should not for the database to go offline to others.  Kill these queries before they kill your system.')
			)
	);

	if (isset($variables['innodb_version']) && version_compare($variables['innodb_version'], '5.6', '<')) {
		$recommendations += array(
			'innodb_flush_log_at_trx_commit' => array(
				'value'   => '2',
				'measure' => 'equal',
				'comment' => __('Setting this value to 2 means that you will flush all transactions every second rather than at commit.  This allows %s to perform writing less often.', $database)
			),
			'innodb_file_io_threads' => array(
				'value'   => '16',
				'measure' => 'gt',
				'comment' => __('With modern SSD type storage, having multiple io threads is advantageous for applications with high io characteristics.')
				)
		);
	} else {
		$recommendations += array(
			'innodb_flush_log_at_timeout' => array(
				'value'   => '3',
				'measure'  => 'gt',
				'comment'  => __('As of %s %s, the you can control how often %s flushes transactions to disk.  The default is 1 second, but in high I/O systems setting to a value greater than 1 can allow disk I/O to be more sequential', $database, $version, $database),
				),
			'innodb_read_io_threads' => array(
				'value'   => '32',
				'measure' => 'gt',
				'comment' => __('With modern SSD type storage, having multiple read io threads is advantageous for applications with high io characteristics.')
				),
			'innodb_write_io_threads' => array(
				'value'   => '16',
				'measure' => 'gt',
				'comment' => __('With modern SSD type storage, having multiple write io threads is advantageous for applications with high io characteristics.')
				),
			'innodb_buffer_pool_instances' => array(
				'value' => '16',
				'measure' => 'present',
				'comment' => __('%s will divide the innodb_buffer_pool into memory regions to improve performance.  The max value is 64.  When your innodb_buffer_pool is less than 1GB, you should use the pool size divided by 128MB.  Continue to use this equation upto the max of 64.', $database)
				)
		);
	}

	html_header(array(__('%s Tuning', $database) . ' (/etc/my.cnf) - [ <a class="linkOverDark" href="https://dev.mysql.com/doc/refman/' . $link_ver . '/en/server-system-variables.html">' . __('Documentation') . '</a> ] ' . __('Note: Many changes below require a database restart')), 2);

	form_alternate_row();
	print "<td colspan='2' style='text-align:left;padding:0px'>";
	print "<table id='mysql' class='cactiTable' style='width:100%'>\n";
	print "<thead>\n";
	print "<tr class='tableHeader'>\n";
	print "  <th class='tableSubHeaderColumn'>" . __('Variable')          . "</th>\n";
	print "  <th class='tableSubHeaderColumn'>" . __('Current Value')     . "</th>\n";
	print "  <th class='tableSubHeaderColumn'>" . __('Recommended Value') . "</th>\n";
	print "  <th class='tableSubHeaderColumn'>" . __('Comments')          . "</th>\n";
	print "</tr>\n";
	print "</thead>\n";

	foreach($recommendations as $name => $r) {
		if (isset($variables[$name])) {
			$class = '';

			form_alternate_row();
			switch($r['measure']) {
			case 'gtm':
				$value = trim($r['value'], 'M') * 1024 * 1024;
				if ($variables[$name] < $value) {
					if (isset($r['class']) && $r['class'] == 'warning') {
						$class = 'textWarning';
					} else {
						$class = 'textError';
					}
				}

				print "<td>" . $name . "</td>\n";
				print "<td class='$class'>" . ($variables[$name]/1024/1024) . "M</td>\n";
				print "<td>>= " . $r['value'] . "</td>\n";
				print "<td class='$class'>" . $r['comment'] . "</td>\n";

				break;
			case 'gt':
				if (version_compare($variables[$name], $r['value'], '<')) {
					if (isset($r['class']) && $r['class'] == 'warning') {
						$class = 'textWarning';
					} else {
						$class = 'textError';
					}
				}

				print "<td>" . $name . "</td>\n";
				print "<td class='$class'>" . $variables[$name] . "</td>\n";
				print "<td>>= " . $r['value'] . "</td>\n";
				print "<td class='$class'>" . $r['comment'] . "</td>\n";

				break;
			case 'equal':
				if (!isset($variables[$name]) || $variables[$name] != $r['value']) {
					if (isset($r['class']) && $r['class'] == 'warning') {
						$class = 'textWarning';
					} else {
						$class = 'textError';
					}
				}

				print "<td>" . $name . "</td>\n";
				print "<td class='$class'>" . (isset($variables[$name]) ? $variables[$name]:'UNSET') . "</td>\n";
				print "<td>" . $r['value'] . "</td>\n";
				print "<td class='$class'>" . $r['comment'] . "</td>\n";

				break;
			case 'pmem':
				if (isset($memInfo['MemTotal'])) {
					$totalMem = $memInfo['MemTotal'];
				} elseif (isset($memInfo['TotalVisibleMemorySize'])) {
					$totalMem = $memInfo['TotalVisibleMemorySize'];
				} else {
					break;
				}

				if ($variables[$name] < ($r['value']*$totalMem/100)) {
					if (isset($r['class']) && $r['class'] == 'warning') {
						$class = 'textWarning';
					} else {
						$class = 'textError';
					}
				}

				print "<td>" . $name . "</td>\n";
				print "<td class='$class'>" . round($variables[$name]/1024/1024,0) . "M</td>\n";
				print "<td>>=" . round($r['value']*$totalMem/100/1024/1024,0) . "M</td>\n";
				print "<td class='$class'>" . $r['comment'] . "</td>\n";

				break;
			}
			form_end_row();
		}
	}
	print "</table>\n";
	print "</td>\n";
	form_end_row();
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
	$php_info = preg_replace('/\<a.*\>/U', '', $php_info);
	$php_info = preg_replace('/\<\/a\>/', '<hr>', $php_info);
	$php_info = preg_replace('/\<img.*\>/U', '', $php_info);
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
		if (sizeof($memInfo)) {
			foreach($memInfo as $key => $values) {
				$memInfo[$key] = $values[1];
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
			foreach($data as $l) {
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
				foreach($output as $line) {
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

