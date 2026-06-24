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
 * Update hash stored in settings table to inform remote pollers to update their caches
 *
 * @param int    $poller_id The ID of the poller impacted by hash update
 * @param string $variable  The hash variable prefix for the replication setting
 *
 * @return void
 */
function api_data_source_cache_crc_update(int $poller_id, string $variable = 'poller_replicate_data_source_cache_crc') : void {
	$hash = hash('ripemd160', date('Y-m-d H:i:s') . random_int(0, mt_getrandmax()) . "$poller_id");

	db_execute_prepared("REPLACE INTO settings
		SET value = ?, name='$variable" . '_' . "$poller_id'",
		[$hash]);
}

/**
 * Determines if a data source can be deleted.
 *
 * @param  int  $local_data_id The ID of the local data source to check.
 * @return bool Returns true if the data source can be deleted, false otherwise.
 */
function api_data_source_deletable(int $local_data_id) : bool {
	$graphs = db_fetch_cell_prepared('SELECT COUNT(DISTINCT gti.local_graph_id)
		FROM data_local AS dl
		INNER JOIN data_template_rrd AS dtr
		ON dl.id = dtr.local_data_id
		LEFT JOIN graph_templates_item AS gti
		ON gti.task_item_id = dtr.id
		WHERE dl.id = ?
		AND gti.id IS NOT NULL',
		[$local_data_id]);

	if ($graphs > 0) {
		return false;
	} else {
		return true;
	}
}

/**
 * Removes a data source from the system.
 *
 * @param int  $local_data_id The ID of the local data source to be removed.
 * @param bool $update_totals Whether to update object cache totals after deletion. Default is true.
 *
 * @return void
 */
function api_data_source_remove(int $local_data_id, bool $update_totals = true) : void {
	if (empty($local_data_id)) {
		return;
	}

	api_plugin_hook_function('data_source_remove', [$local_data_id]);

	$autoclean = read_config_option('rrd_autoclean');
	$acmethod  = read_config_option('rrd_autoclean_method');

	if ($update_totals) {
		object_cache_get_totals('data_source', $local_data_id);
	}

	if ($autoclean == 'on') {
		$dsinfo = db_fetch_row_prepared('SELECT local_data_id, data_source_path
			FROM data_template_data
			WHERE local_data_id = ?', [$local_data_id]);

		if (cacti_sizeof($dsinfo)) {
			$filename = str_replace('<path_rra>/', '', $dsinfo['data_source_path']);
			db_execute_prepared('INSERT INTO data_source_purge_action
				(local_data_id, name, action) VALUES (?, ?, ?)
				ON DUPLICATE KEY UPDATE action=VALUES(action)',
				[$local_data_id, $filename, $acmethod]);
		}
	}

	$data_template_data_id = db_fetch_cell_prepared('SELECT id
		FROM data_template_data
		WHERE local_data_id = ?', [$local_data_id]);

	$poller_id = db_fetch_cell_prepared('SELECT poller_id
		FROM host AS h
		INNER JOIN data_local AS dl
		ON h.id=dl.host_id
		WHERE dl.id = ?',
		[$local_data_id]);

	if (!empty($data_template_data_id)) {
		db_execute_prepared('DELETE
			FROM data_input_data
			WHERE data_template_data_id = ?',
			[$data_template_data_id]);

		if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
			db_execute_prepared('DELETE
				FROM data_input_data
				WHERE data_template_data_id = ?',
				[$data_template_data_id], true, $rcnn_id);
		}
	}

	// base data
	db_execute_prepared('DELETE FROM data_template_data
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM data_template_rrd
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM poller_item
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM data_local
		WHERE id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM data_debug
		WHERE datasource = ?', [$local_data_id]);

	// dsstats
	db_execute_prepared('DELETE FROM data_source_stats_daily
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM data_source_stats_hourly
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM data_source_stats_hourly_cache
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM data_source_stats_hourly_last
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM data_source_stats_monthly
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM data_source_stats_weekly
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM data_source_stats_yearly
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM data_source_stats_command_cache
		WHERE local_data_id = ?', [$local_data_id]);

	// boost
	db_execute_prepared('DELETE FROM poller_output
		WHERE local_data_id = ?', [$local_data_id]);

	db_execute_prepared('DELETE FROM poller_output_boost
		WHERE local_data_id = ?', [$local_data_id]);

	if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
		// base data
		db_execute_prepared('DELETE FROM data_template_data
			WHERE local_data_id = ?', [$local_data_id], true, $rcnn_id);

		db_execute_prepared('DELETE FROM data_template_rrd
			WHERE local_data_id = ?', [$local_data_id], true, $rcnn_id);

		db_execute_prepared('DELETE FROM poller_item
			WHERE local_data_id = ?', [$local_data_id], true, $rcnn_id);

		db_execute_prepared('DELETE FROM data_local
			WHERE id = ?', [$local_data_id], true, $rcnn_id);

		// boost
		db_execute_prepared('DELETE FROM poller_output
			WHERE local_data_id = ?', [$local_data_id], true, $rcnn_id);

		db_execute_prepared('DELETE FROM poller_output_boost
			WHERE local_data_id = ?', [$local_data_id], true, $rcnn_id);
	}

	// update the database to document the cache change
	api_data_source_cache_crc_update($poller_id);

	if ($update_totals) {
		object_cache_update_totals('delete');
	}
}

/**
 * Removes multiple data sources from the system.
 *
 * @param array $local_data_ids An array of local data IDs to be removed.
 * @param bool  $update_totals  Optional. Whether to update totals after removal. Default is true.
 *
 * @return void
 */
function api_data_source_remove_multi(array $local_data_ids, bool $update_totals = true) : void {
	// Shortcut out if no data
	if (!cacti_sizeof($local_data_ids)) {
		return;
	}

	if ($update_totals) {
		object_cache_get_totals('data_source', $local_data_ids);
	}

	api_plugin_hook_function('data_source_remove', $local_data_ids);

	$autoclean = read_config_option('rrd_autoclean');
	$acmethod  = read_config_option('rrd_autoclean_method');

	$local_data_ids_chunks = array_chunk($local_data_ids, 1000);

	foreach ($local_data_ids_chunks as $ids_to_delete) {
		$poller_ids = get_remote_poller_ids_from_data_sources($ids_to_delete);

		$ids_to_delete = implode(', ', array_map('intval', $ids_to_delete));

		$data_template_data_ids = db_fetch_assoc('SELECT id
			FROM data_template_data
			WHERE local_data_id IN (' . $ids_to_delete . ')');

		if (cacti_sizeof($data_template_data_ids)) {
			$dtd_ids_to_delete = [];

			foreach ($data_template_data_ids as $data_template_data_id) {
				$dtd_ids_to_delete[] = $data_template_data_id['id'];

				if (cacti_sizeof($dtd_ids_to_delete) >= 1000) {
					db_execute('DELETE FROM data_input_data
						WHERE data_template_data_id IN (' . implode(',', $dtd_ids_to_delete) . ')');

					if (cacti_sizeof($poller_ids)) {
						foreach ($poller_ids as $poller_id) {
							if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
								db_execute('DELETE FROM data_input_data
									WHERE data_template_data_id IN (' . implode(',', $dtd_ids_to_delete) . ')', true, $rcnn_id);
							}
						}
					}

					$dtd_ids_to_delete = [];
				}
			}

			if (cacti_sizeof($dtd_ids_to_delete)) {
				db_execute('DELETE FROM data_input_data
					WHERE data_template_data_id IN (' . implode(',', $dtd_ids_to_delete) . ')');

				if (cacti_sizeof($poller_ids)) {
					foreach ($poller_ids as $poller_id) {
						if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
							db_execute('DELETE FROM data_input_data
								WHERE data_template_data_id IN (' . implode(',', $dtd_ids_to_delete) . ')', true, $rcnn_id);
						}
					}
				}
			}
		}

		// prepare auto-clean if enabled
		if ($autoclean == 'on') {
			db_execute("INSERT INTO data_source_purge_action (local_data_id, name, action)
				SELECT local_data_id, REPLACE(data_source_path, '<path_rra>/', ''), '" . $acmethod . "'
				FROM data_template_data
				WHERE local_data_id IN (" . $ids_to_delete . ')
				ON DUPLICATE KEY UPDATE action=VALUES(action)');
		}

		// core data
		db_execute('DELETE FROM data_template_data
			WHERE local_data_id IN (' . $ids_to_delete . ')');

		db_execute('DELETE FROM data_template_rrd
			WHERE local_data_id IN (' . $ids_to_delete . ')');

		db_execute('DELETE FROM poller_item
			WHERE local_data_id IN (' . $ids_to_delete . ')');

		db_execute('DELETE FROM data_local
			WHERE id IN (' . $ids_to_delete . ')');

		db_execute('DELETE FROM data_debug
			WHERE datasource IN (' . $ids_to_delete . ')');

		// dsstats
		db_execute('DELETE FROM data_source_stats_daily
			WHERE local_data_id IN(' . $ids_to_delete . ')');

		db_execute('DELETE FROM data_source_stats_hourly
			WHERE local_data_id IN(' . $ids_to_delete . ')');

		db_execute('DELETE FROM data_source_stats_hourly_cache
			WHERE local_data_id IN(' . $ids_to_delete . ')');

		db_execute('DELETE FROM data_source_stats_hourly_last
			WHERE local_data_id IN(' . $ids_to_delete . ')');

		db_execute('DELETE FROM data_source_stats_monthly
			WHERE local_data_id IN(' . $ids_to_delete . ')');

		db_execute('DELETE FROM data_source_stats_weekly
			WHERE local_data_id IN(' . $ids_to_delete . ')');

		db_execute('DELETE FROM data_source_stats_yearly
			WHERE local_data_id IN(' . $ids_to_delete . ')');

		// boost
		db_execute('DELETE FROM poller_output
			WHERE local_data_id IN (' . $ids_to_delete . ')');

		db_execute('DELETE FROM poller_output_boost
			WHERE local_data_id IN (' . $ids_to_delete . ')');

		if (cacti_sizeof($poller_ids)) {
			foreach ($poller_ids as $poller_id) {
				if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
					// core data
					db_execute('DELETE FROM data_template_data
						WHERE local_data_id IN (' . $ids_to_delete . ')', true, $rcnn_id);

					db_execute('DELETE FROM data_template_rrd
						WHERE local_data_id IN (' . $ids_to_delete . ')', true, $rcnn_id);

					db_execute('DELETE FROM poller_item
						WHERE local_data_id IN (' . $ids_to_delete . ')', true, $rcnn_id);

					db_execute('DELETE FROM data_local
						WHERE id IN (' . $ids_to_delete . ')', true, $rcnn_id);

					// boost
					db_execute('DELETE FROM poller_output
						WHERE local_data_id IN (' . $ids_to_delete . ')', true, $rcnn_id);

					db_execute('DELETE FROM poller_output_boost
						WHERE local_data_id IN (' . $ids_to_delete . ')', true, $rcnn_id);
				}

				api_data_source_cache_crc_update($poller_id);
			}
		}
	}

	if ($update_totals) {
		object_cache_update_totals('delete');
	}
}

/**
 * Enables a data source by setting its status to 'on' in the database.
 *
 * @param int $local_data_id The ID of the local data source to enable.
 *
 * @return void
 */
function api_data_source_enable(int $local_data_id) : void {
	db_execute_prepared("UPDATE data_template_data
		SET active = 'on'
		WHERE local_data_id = ?",
		[$local_data_id]);

	$device_id = db_fetch_cell_prepared('SELECT host_id
		FROM data_local
		WHERE id = ?',
		[$local_data_id]);

	if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
		db_execute_prepared("UPDATE data_template_data
			SET active = 'on'
			WHERE local_data_id = ?",
			[$local_data_id], true, $rcnn_id);
	}

	update_poller_cache($local_data_id, true);
}

/**
 * Disables a data source by deleting its poller items and updating its status.
 *
 * @param int $local_data_id The ID of the local data source to be disabled.
 *
 * @return void
 */
function api_data_source_disable(int $local_data_id) : void {
	db_execute_prepared('DELETE FROM poller_item
		WHERE local_data_id = ?',
		[$local_data_id]);

	db_execute_prepared("UPDATE data_template_data
		SET active=''
		WHERE local_data_id = ?",
		[$local_data_id]);

	$device_id = db_fetch_cell_prepared('SELECT host_id
		FROM data_local
		WHERE id = ?',
		[$local_data_id]);

	if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
		db_execute_prepared('DELETE FROM poller_item
			WHERE local_data_id = ?',
			[$local_data_id], true, $rcnn_id);

		db_execute_prepared("UPDATE data_template_data
			SET active=''
			WHERE local_data_id = ?",
			[$local_data_id], true, $rcnn_id);
	}
}

/**
 * Disables multiple data sources by their local data IDs.
 *
 * @param array $local_data_ids An array of local data IDs to be disabled.
 *
 * @return void
 */
function api_data_source_disable_multi(array $local_data_ids) : void {
	if (!cacti_sizeof($local_data_ids)) {
		return;
	}

	$poller_ids = [];

	$local_data_ids_chunks = array_chunk(array_map('intval', $local_data_ids), 1000);

	foreach ($local_data_ids_chunks as $ids_to_disable) {
		$ids_to_disable = implode(', ', $ids_to_disable);

		$poller_ids += array_rekey(
			db_fetch_assoc('SELECT poller_id
				FROM poller_item
				WHERE local_data_id IN(' . $ids_to_disable . ')'),
			'poller_id', 'poller_id'
		);

		db_execute("DELETE FROM poller_item WHERE local_data_id IN ($ids_to_disable)");
		db_execute("UPDATE data_template_data SET active='' WHERE local_data_id IN ($ids_to_disable)");

		if (cacti_sizeof($poller_ids)) {
			foreach ($poller_ids as $poller_id) {
				if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
					db_execute("DELETE FROM poller_item WHERE local_data_id IN ($ids_to_disable)", true, $rcnn_id);
					db_execute("UPDATE data_template_data SET active='' WHERE local_data_id IN ($ids_to_disable)", true, $rcnn_id);
				}
			}
		}
	}

	if (cacti_sizeof($poller_ids)) {
		foreach ($poller_ids as $poller_id) {
			api_data_source_cache_crc_update($poller_id);
		}
	}
}

/**
 * Retrieves the interface speed for a given data source.
 *
 * @param array $data_local An associative array containing the following keys:
 *                          - 'host_id': The ID of the host.
 *                          - 'snmp_query_id': The ID of the SNMP query.
 *                          - 'snmp_index': The SNMP index.
 *
 * @return int The interface speed in bits per second.
 */
function api_data_source_get_interface_speed(array $data_local) : int {
	$ifHighSpeed = db_fetch_cell_prepared('SELECT field_value
		FROM host_snmp_cache
		WHERE host_id = ?
		AND snmp_query_id = ?
		AND snmp_index = ?
		AND field_name="ifHighSpeed"',
		[$data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']]
	);

	$ifSpeed = db_fetch_cell_prepared('SELECT field_value
		FROM host_snmp_cache
		WHERE host_id = ?
		AND snmp_query_id = ?
		AND snmp_index = ?
		AND field_name="ifSpeed"',
		[$data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']]
	);

	if (!empty($ifHighSpeed)) {
		$speed = $ifHighSpeed * 1000000;

		if (read_config_option('data_source_trace') == 'on') {
			cacti_log('Interface Speed Detected by ifHighSpeed: "' . $speed . '"', false, 'DSTRACE');
		}
	} elseif (!empty($ifSpeed)) {
		$speed = $ifSpeed;

		if (read_config_option('data_source_trace') == 'on') {
			cacti_log('Interface Speed Detected by ifSpeed: "' . $speed . '"', false, 'DSTRACE');
		}
	} else {
		$speed = intval(read_config_option('default_interface_speed'));

		if (empty($speed)) {
			$speed = '10000000000000';

			if (read_config_option('data_source_trace') == 'on') {
				cacti_log('Interface Speed Detected by Default: "' . $speed . '"', false, 'DSTRACE');
			}
		} else {
			$speed *= 1000000;

			if (read_config_option('data_source_trace') == 'on') {
				cacti_log('Interface Speed Detected by Settings: "' . $speed . '"', false, 'DSTRACE');
			}
		}
	}

	return (int) $speed;
}

/**
 * Change the host for a list of data sources.
 *
 * @param array $data_sources An array of data source IDs to be updated.
 * @param int   $device_id    The ID of the new host device.
 *
 * @return void
 */
function api_data_source_change_host(array $data_sources, int $device_id) : void {
	if (cacti_sizeof($data_sources)) {
		foreach ($data_sources as $data_source) {
			db_execute_prepared('UPDATE data_local
				SET host_id = ?
				WHERE id = ?',
				[$device_id, $data_source]);

			if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
				db_execute_prepared('UPDATE data_local
					SET host_id = ?
					WHERE id = ?',
					[$device_id, $data_source], true, $rcnn_id);
			}

			push_out_host($device_id, $data_source);

			update_data_source_title_cache($data_source);
		}
	}
}

/**
 * Reapplies suggested data source data for a given local data ID.
 *
 * @param int $local_data_id The local data ID for which to reapply suggested data source data.
 *
 * @return void
 */
function api_reapply_suggested_data_source_data(int $local_data_id) : void {
	$data_template_data_id = db_fetch_cell_prepared('SELECT id
		FROM data_template_data
		WHERE local_data_id = ?',
		[$local_data_id]);

	if (empty($data_template_data_id)) {
		return;
	}

	// require query type data sources only (snmp_query_id > 0)
	$data_local = db_fetch_row_prepared('SELECT id, host_id,
		data_template_id, snmp_query_id, snmp_index
		FROM data_local
		WHERE snmp_query_id > 0
		AND id = ?',
		[$local_data_id]);

	// if this is not a data query graph, simply return
	if (!isset($data_local['host_id'])) {
		return;
	}

	$snmp_query_graph_id = db_fetch_cell_prepared("SELECT did.value
		FROM data_input_data AS did
		INNER JOIN data_input_fields AS dif
		ON did.data_input_field_id = dif.id
		INNER JOIN data_template_data AS dtd
		ON dtd.id = did.data_template_data_id
		WHERE dif.type_code = 'output_type'
		AND dtd.local_data_id = ?",
		[$data_local['id']]);

	// no snmp query graph id found
	if ($snmp_query_graph_id == 0) {
		return;
	}

	$svs = db_fetch_assoc_prepared('SELECT
		text, field_name
		FROM snmp_query_graph_rrd_sv
		WHERE snmp_query_graph_id = ?
		AND data_template_id = ?
		ORDER BY sequence',
		[$snmp_query_graph_id, $data_local['data_template_id']]);

	$matches = [];

	if (cacti_sizeof($svs)) {
		foreach ($svs as $sv) {
			$sv['text'] = trim($sv['text']);

			if (($sv['text'] == '|query_ifSpeed|' || $sv['text'] == '|query_ifHighSpeed|') && $sv['field_name'] == 'rrd_maximum') {
				$subs_string = api_data_source_get_interface_speed($data_local);
				$sv['text']  = $subs_string;
			} else {
				$max_chars = intval(read_config_option('max_data_query_field_length'));

				if (empty($max_chars)) {
					$max_chars = 40;
				}

				$subs_string = substitute_snmp_query_data($sv['text'],$data_local['host_id'],
					$data_local['snmp_query_id'], $data_local['snmp_index'], $max_chars);
			}

			// if there are no '|query' characters, all of the substitutions were successful
			if (!str_contains($subs_string, '|query')) {
				if (in_array($sv['field_name'], $matches, true)) {
					continue;
				}

				if (db_column_exists('data_template_data', $sv['field_name'])) {
					$matches[] = $sv['field_name'];
					db_execute_prepared('UPDATE data_template_data
						SET ' . $sv['field_name'] . ' = ?
						WHERE local_data_id = ?',
						[$sv['text'], $local_data_id]);
				} elseif (db_column_exists('data_template_rrd', $sv['field_name'])) {
					$matches[] = $sv['field_name'];
					db_execute_prepared('UPDATE data_template_rrd
						SET ' . $sv['field_name'] . ' = ?
						WHERE local_data_id = ?',
						[$sv['text'], $local_data_id]);
				} else {
					cacti_log('ERROR: Suggested value column error.  Column ' . $sv['field_name'] . ' for Data Template ID ' . $data_local['data_template_id'] . ' is not a compatible field name for tables data_template_data and data_template_rrd.  Please correct this suggested value mapping', false);
				}
			}
		}
	}
}

/**
 * Duplicates a data source or data template.
 *
 * @param int    $_local_data_id    The ID of the local data to duplicate. If provided, the function will duplicate the data source.
 * @param int    $_data_template_id The ID of the data template to duplicate. If provided, the function will duplicate the data template.
 * @param string $data_source_title The title for the new data source or data template.
 *
 * @return mixed - The ID of the newly created local data or data template, or false on failure.
 */
function api_data_source_duplicate(int $_local_data_id, int $_data_template_id, string $data_source_title) : mixed {
	global $struct_data_source, $struct_data_source_item;

	$data_template_id   = 0;
	$local_data_id      = 0;
	$data_template_data = [];
	$data_input_datas   = [];
	$data_template_rrds = [];

	if (!empty($_local_data_id)) {
		$data_local = db_fetch_row_prepared('SELECT *
			FROM data_local
			WHERE id = ?',
			[$_local_data_id]);

		if (!cacti_sizeof($data_local)) {
			return false;
		}

		$data_template_data = db_fetch_row_prepared('SELECT *
			FROM data_template_data
			WHERE local_data_id = ?',
			[$_local_data_id]);

		$data_template_rrds = db_fetch_assoc_prepared('SELECT *
			FROM data_template_rrd
			WHERE local_data_id = ?',
			[$_local_data_id]);

		$data_input_datas   = db_fetch_assoc_prepared('SELECT *
			FROM data_input_data
			WHERE data_template_data_id = ?',
			[$data_template_data['id']]);

		// create new entry: data_local
		$save['id']               = 0;
		$save['data_template_id'] = $data_local['data_template_id'];
		$save['host_id']          = $data_local['host_id'];
		$save['snmp_query_id']    = $data_local['snmp_query_id'];
		$save['snmp_index']       = $data_local['snmp_index'];

		$local_data_id = sql_save($save, 'data_local');

		$data_template_data['name'] = str_replace('<ds_title>', $data_template_data['name'], $data_source_title);
	} elseif (!empty($_data_template_id)) {
		$data_template = db_fetch_row_prepared('SELECT *
			FROM data_template
			WHERE id = ?',
			[$_data_template_id]);

		if (!cacti_sizeof($data_template)) {
			return false;
		}

		$data_template_data = db_fetch_row_prepared('SELECT *
			FROM data_template_data
			WHERE data_template_id = ?
			AND local_data_id = 0',
			[$_data_template_id]);

		$data_template_rrds = db_fetch_assoc_prepared('SELECT *
			FROM data_template_rrd
			WHERE data_template_id = ?
			AND local_data_id = 0',
			[$_data_template_id]);

		$data_input_datas = db_fetch_assoc_prepared('SELECT *
			FROM data_input_data
			WHERE data_template_data_id = ?',
			[$data_template_data['id']]);

		// create new entry: data_template
		$save['id']   = 0;
		$save['hash'] = get_hash_data_template(0);
		$save['name'] = str_replace('<template_title>', $data_template['name'], $data_source_title);

		$data_template_id = sql_save($save, 'data_template');
	}

	unset($struct_data_source['data_source_path']);

	// create new entry: data_template_data
	$save = [];

	$save['id']                          = 0;
	$save['local_data_id']               = ($local_data_id ?? 0);
	$save['local_data_template_data_id'] = ($data_template_data['local_data_template_data_id'] ?? 0);
	$save['data_template_id']            = (!empty($_local_data_id) ? $data_template_data['data_template_id'] : $data_template_id);
	$save['name_cache']                  = $data_template_data['name_cache'];

	foreach ($struct_data_source as $field => $array) {
		$save[$field] = $data_template_data[$field];

		if ($array['flags'] != 'ALWAYSTEMPLATE') {
			$save['t_' . $field] = $data_template_data['t_' . $field];
		}
	}

	$data_template_data_id = sql_save($save, 'data_template_data');

	// create new entry(s): data_template_rrd
	if (cacti_sizeof($data_template_rrds)) {
		foreach ($data_template_rrds as $data_template_rrd) {
			$save = [];

			$save['id']                         = 0;
			$save['local_data_id']              = ($local_data_id ?? 0);
			$save['local_data_template_rrd_id'] = ($data_template_rrd['local_data_template_rrd_id'] ?? 0);
			$save['data_template_id']           = (!empty($_local_data_id) ? $data_template_rrd['data_template_id'] : $data_template_id);

			if ($save['local_data_id'] == 0) {
				$save['hash'] = get_hash_data_template($data_template_rrd['local_data_template_rrd_id'], 'data_template_item');
			} else {
				$save['hash'] = '';
			}

			foreach ($struct_data_source_item as $field => $array) {
				$save[$field] = $data_template_rrd[$field];

				if (isset($data_template_rrd['t_' . $field])) {
					$save['t_' . $field] = $data_template_rrd['t_' . $field];
				}
			}

			$data_template_rrd_id = sql_save($save, 'data_template_rrd');
		}
	}

	// create new entry(s): data_input_data
	if (cacti_sizeof($data_input_datas)) {
		/* Stamp the duplicated rows with the new entity's identity, not
		 * the source row's. For a duplicated data source the new rows
		 * point at $local_data_id and that source's host_id; for a
		 * duplicated template they point at the new $data_template_id
		 * with local_data_id = 0 and host_id = 0 (templates have no
		 * device of their own). Mirrors the data_template_data save at
		 * line 759 above which branches the same way. */
		if (!empty($_local_data_id)) {
			$bound_data_template_id = $data_template_data['data_template_id'];
			$bound_local_data_id    = $local_data_id;
			$bound_host_id          = $data_local['host_id'];
		} else {
			$bound_data_template_id = $data_template_id;
			$bound_local_data_id    = 0;
			$bound_host_id          = 0;
		}

		foreach ($data_input_datas as $data_input_data) {
			db_execute_prepared('INSERT IGNORE INTO data_input_data
				(data_input_field_id, data_template_data_id, data_template_id, local_data_id, host_id, t_value, value)
				VALUES (?, ?, ?, ?, ?, ?, ?)',
				[
					$data_input_data['data_input_field_id'],
					$data_template_data_id,
					$bound_data_template_id,
					$bound_local_data_id,
					$bound_host_id,
					$data_input_data['t_value'],
					$data_input_data['value']
				]
			);
		}
	}

	if (!empty($_local_data_id)) {
		update_data_source_title_cache($local_data_id);
	}

	if ($_local_data_id > 0) {
		return $local_data_id;
	}

	if ($_data_template_id > 0) {
		return $data_template_id;
	} else {
		return false;
	}
}

/**
 * Duplicates a data input entry and its associated fields.
 *
 * @param int    $_data_input_id The ID of the data input entry to duplicate.
 * @param string $input_title    The title for the new duplicated data input entry.
 *
 * @return mixed - The ID of the newly created data input entry on success, or false on failure.
 */
function api_data_input_duplicate(int $_data_input_id, string $input_title) : mixed {
	$orig_input = db_fetch_row_prepared('SELECT *
		FROM data_input
		WHERE id = ?',
		[$_data_input_id]);

	if (cacti_sizeof($orig_input)) {
		$save = [];

		$save['id']           = 0;
		$save['hash']         = get_hash_data_input(0);
		$save['name']         = str_replace('<input_title>', $orig_input['name'], $input_title);
		$save['input_string'] = $orig_input['input_string'];
		$save['type_id']      = $orig_input['type_id'];

		$data_input_id = sql_save($save, 'data_input');

		if (!empty($data_input_id)) {
			$data_input_fields = db_fetch_assoc_prepared('SELECT *
				FROM data_input_fields
				WHERE data_input_id = ?',
				[$_data_input_id]);

			if (cacti_sizeof($data_input_fields)) {
				foreach ($data_input_fields as $dif) {
					unset($save);
					$save['id']            = 0;
					$save['hash']          = get_hash_data_input(0, 'data_input_field');
					$save['data_input_id'] = $data_input_id;
					$save['name']          = $dif['name'];
					$save['data_name']     = $dif['data_name'];
					$save['input_output']  = $dif['input_output'];
					$save['update_rra']    = $dif['update_rra'];
					$save['sequence']      = $dif['sequence'];
					$save['type_code']     = $dif['type_code'];
					$save['regexp_match']  = $dif['regexp_match'];
					$save['allow_nulls']   = $dif['allow_nulls'];

					$data_input_field_id = sql_save($save, 'data_input_fields');
				}
			}
		}

		return $data_input_id;
	}

	return false;
}

/**
 * Removes a data input and its associated fields and data.
 *
 * @param int $id The ID of the data input to be removed.
 *
 * @return void
 */
function api_data_input_remove(int $id) : void {
	$data_input_fields = db_fetch_assoc_prepared('SELECT id
		FROM data_input_fields
		WHERE data_input_id = ?',
		[$id]);

	if (is_array($data_input_fields)) {
		foreach ($data_input_fields as $data_input_field) {
			db_execute_prepared('DELETE FROM data_input_data
				WHERE data_input_field_id = ?',
				[$data_input_field['id']]);
		}
	}

	db_execute_prepared('DELETE FROM data_input
		WHERE id = ?',
		[$id]);

	db_execute_prepared('DELETE FROM data_input_fields
		WHERE data_input_id = ?',
		[$id]);

	update_replication_crc(0, 'poller_replicate_data_input_fields_crc');
	update_replication_crc(0, 'poller_replicate_data_input_crc');
}

/**
 * Checks if the number of input fields in the input string is greater than the existing input fields in the database.
 *
 * @param  int    $id           The ID of the data input.
 * @param  string $input_string The input string containing the input fields.
 * @return bool   Returns true if the number of input fields in the input string is greater than the existing input fields, otherwise false.
 */
function api_data_input_more_inputs(int $id, string $input_string) : bool {
	$input_string = str_replace('<path_cacti>', '', $input_string);
	$inputs       = substr_count($input_string, '<');

	$existing = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM data_input_fields
		WHERE data_input_id = ?
		AND input_output = "in"',
		[$id]);

	if ($inputs > $existing) {
		return true;
	} else {
		return false;
	}
}
