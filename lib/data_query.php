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
 * Executes a data query for a given host and SNMP query ID. This function handles
 * various scenarios such as host status, poller assignment, and data query type.
 * It also manages re-indexing, orphaned data, and updates related caches.
 *
 * @param int  $host_id       The ID of the host for which the data query is being run.
 * @param int  $snmp_query_id The ID of the SNMP query to execute.
 * @param bool $automation    Whether the query is being run as part of an automation process. Default is false.
 * @param bool $force         Whether to force the re-indexing and cache updates. Default is false.
 *
 * @global array $config      Global configuration array.
 * @global array $input_types Global array of input types.
 *
 * @return mixed Returns false if the query fails or the host is down/disabled.
 *               Returns true or the result of the query execution otherwise.
 *
 * @throws Exception If there are issues with database queries or remote calls.
 */
function run_data_query(int $host_id, int $snmp_query_id, bool $automation = false, bool $force = false) : mixed {
	global $config, $input_types;

	if (read_config_option('data_source_trace') == 'on') {
		cacti_log("Running Re-Index for Device[$host_id], DQ[$snmp_query_id]", false, 'DSTRACE');
	}

	// required for upgrading old versions of cacti
	if (!db_column_exists('host', 'poller_id')) {
		return false;
	}

	// don't run/rerun the query if the host is down, or disabled
	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = ',s.disabled AS site_disabled';
	} else {
		$sdisabled = ',"" AS site_disabled';
	}

	$status = db_fetch_row_prepared("SELECT h.status, h.poller_id,
		h.disabled $sdisabled
		FROM host h
		LEFT JOIN sites s
		ON h.site_id = s.id
		WHERE h.id = ?",
		[$host_id]);

	if (!cacti_sizeof($status)) {
		return false;
	}

	if ($status['status'] == HOST_DOWN || $status['disabled'] == 'on' || $status['site_disabled'] == 'on') {
		return true;
	}

	$poller_id = $status['poller_id'];

	if ($poller_id != POLLER_ID) {
		$hostname = db_fetch_cell_prepared('SELECT hostname
			FROM poller
			WHERE id = ?',
			[$poller_id]);

		$url = CACTI_PATH_URL . 'remote_agent.php?action=runquery&host_id=' . $host_id . '&data_query_id=' . $snmp_query_id;

		$response = call_remote_data_collector($poller_id, $url);

		if ($response != '') {
			$response = json_decode($response, true);

			$_SESSION['debug_log']['data_query'] = $response['data_query'];

			if (isset($_SESSION['debug_log']['response'])) {
				$result = $_SESSION['debug_log']['response'];
				unset($_SESSION['debug_log']['response']);

				automation_execute_data_query($host_id, $snmp_query_id);
				query_debug_timer_offset('data_query', __esc('Automation Execution for Data Query complete'));

				api_plugin_hook_function('run_data_query', ['host_id' => $host_id, 'snmp_query_id' => $snmp_query_id]);
				query_debug_timer_offset('data_query', __esc('Plugin Hooks complete'));

				return $result;
			}
		}

		return false;
	}

	include_once(CACTI_PATH_LIBRARY . '/poller.php');
	include_once(CACTI_PATH_LIBRARY . '/api_data_source.php');
	include_once(CACTI_PATH_LIBRARY . '/utility.php');

	query_debug_timer_start();
	$reindex_start = microtime(true);

	// Load the XML structure for custom settings detection
	$query_array = get_data_query_array($snmp_query_id);

	query_debug_timer_offset('data_query', __esc('Running Data Query [%s].', $snmp_query_id));

	$type_id = db_fetch_cell_prepared('SELECT data_input.type_id
		FROM snmp_query
		INNER JOIN data_input
		ON snmp_query.data_input_id = data_input.id
		WHERE snmp_query.id = ?',
		[$snmp_query_id]);

	if (isset($input_types[$type_id])) {
		query_debug_timer_offset('data_query', __esc('Found Type = \'%s\' [%s].', $type_id, $input_types[$type_id]));
	}

	if ($type_id == DATA_INPUT_TYPE_SNMP_QUERY) {
		$result = query_snmp_host($host_id, $snmp_query_id);
	} elseif ($type_id == DATA_INPUT_TYPE_SCRIPT_QUERY) {
		$result = query_script_host($host_id, $snmp_query_id);
	} elseif ($type_id == DATA_INPUT_TYPE_QUERY_SCRIPT_SERVER) {
		$result = query_script_host($host_id, $snmp_query_id);
	} else {
		$result    = false;
		$arguments = [
			'result'        => $result,
			'host_id'       => $host_id,
			'snmp_query_id' => $snmp_query_id
		];

		$arguments = api_plugin_hook_function('run_data_query', $arguments);

		if (isset($arguments['result']) && $arguments['result'] !== false) {
			$result = $arguments['result'];
		} else {
			query_debug_timer_offset('data_query', __esc('Unknown Type = \'%s\'.', $type_id));
		}
	}

	// If re-indexing fails, don't continue
	if (!$result) {
		$cur_time = microtime(true);
		$total    = $cur_time - $reindex_start;

		db_execute_prepared('UPDATE host_snmp_query
			SET reindex_last_runtime = CURRENT_TIMESTAMP(),
			reindex_last_duration = ?
			WHERE host_id = ?
			AND snmp_query_id = ?',
			[$total, $host_id, $snmp_query_id]);

		return false;
	}

	// SNMP Queries can specify the index as a 'source' column
	if (!isset($query_array['index_order'])) {
		$index_is_source = true;
	} else {
		$index_is_source = false;
	}

	$old_sort_field = get_best_data_query_index_type($host_id, $snmp_query_id);

	// update the sort cache if there is a sort field
	if ($index_is_source == false) {
		$new_sort_field = update_data_query_sort_cache($host_id, $snmp_query_id);
	} else {
		$new_sort_field = $old_sort_field;
	}

	if ($new_sort_field === false) {
		$cur_time = microtime(true);
		$total    = $cur_time - $reindex_start;

		db_execute_prepared('UPDATE host_snmp_query
			SET reindex_last_runtime = CURRENT_TIMESTAMP(),
			reindex_last_duration = ?
			WHERE host_id = ?
			AND snmp_query_id = ?',
			[$total, $host_id, $snmp_query_id]);

		cacti_log('ERROR: Re-Indexing failed due to a NULL sort field for Device[' . $host_id . '] and DQ[' . $snmp_query_id . '].  Can not continue with Re-Index.', false, 'REINDEX');

		return false;
	}

	$remap = false;

	if ($index_is_source == false) {
		if (query_check_suitable($new_sort_field, $old_sort_field, $host_id, $snmp_query_id)) {
			if ($old_sort_field != $new_sort_field) {
				if ($old_sort_field != '') {
					query_debug_timer_offset('data_query', __esc('WARNING: Sort Field Association has Changed.  Re-mapping issues may occur!'));

					cacti_log('WARNING: Sort Field has Changed for Device[' . $host_id . '] and DQ[' . $snmp_query_id . '].  Old Sort:' . $old_sort_field . ', New Sort:' . $new_sort_field . '.  Re-mapping issues may occur!', false, 'REINDEX');
				}

				$remap = true;
			}
		} else {
			$new_sort_field = $old_sort_field;
		}
	}

	query_debug_timer_offset('data_query', __esc('Update Data Query Sort Cache complete'));

	// recalculate/change sort order
	$local_data = db_fetch_assoc_prepared('SELECT dl.id AS local_data_id, dl.host_id,
		dl.snmp_query_id, dl.snmp_index, dl.orphan,
		did.data_input_field_id, did.data_template_data_id,
		did.value AS query_index, "' . $old_sort_field . '" AS sort_field
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id = dtd.local_data_id
		INNER JOIN data_input_fields AS dif
		ON dtd.data_input_id = dif.data_input_id
		LEFT JOIN data_input_data AS did
		ON dtd.id = did.data_template_data_id
		AND dif.id = did.data_input_field_id
		WHERE dif.data_name="index_value"
		AND dl.snmp_query_id = ?
		AND dl.host_id = ?',
		[$snmp_query_id, $host_id]);

	$changed_ids  = [];
	$orphaned_ids = [];
	$push_out     = [];

	if (cacti_sizeof($local_data)) {
		query_debug_timer_offset('data_query', __esc('Found %s Local Data ID\'s to Verify', cacti_sizeof($local_data)));

		foreach ($local_data as $data_source) {
			// Just in case there is a forced type from the data source page
			$forced_type = false;

			// Check to see if the user had manually set the index type
			$previous_sort_field = $old_sort_field;

			$previous_did = db_fetch_row_prepared('SELECT did.*
				FROM data_local AS dl
				INNER JOIN data_template_data AS dtd
				ON dl.id = dtd.local_data_id
				INNER JOIN data_input_fields AS dif
				ON dtd.data_input_id = dif.data_input_id
				LEFT JOIN data_input_data AS did
				ON dtd.id = did.data_template_data_id
				AND dif.id = did.data_input_field_id
				WHERE dif.data_name="index_type"
				AND dl.id = ?',
				[$data_source['local_data_id']]);

			if (cacti_sizeof($previous_did)) {
				if ($previous_did['value'] != $previous_sort_field) {
					$forced_type         = true;
					$previous_sort_field = $previous_did['value'];
				}
			}

			if (!$forced_type) {
				// See if the index is in the host cache
				$current_index = db_fetch_cell_prepared('SELECT snmp_index
					FROM host_snmp_cache
					WHERE host_id = ?
					AND snmp_query_id = ?
					AND field_name = ?
					AND field_value = ?',
					[$host_id, $snmp_query_id, $data_source['sort_field'], $data_source['query_index']]);
			} else {
				$current_value = db_fetch_cell_prepared('SELECT value
					FROM data_input_data AS did
					INNER JOIN data_input_fields AS dif
					ON did.data_input_field_id=dif.id
					INNER JOIN data_template_data AS dtd
					ON dtd.id=did.data_template_data_id
					INNER JOIN data_local AS dl
					ON dl.id=dtd.local_data_id
					WHERE dl.id = ?
					AND dl.snmp_query_id = ?
					AND data_name="index_value"',
					[$data_source['local_data_id'], $snmp_query_id]);

				$current_index = db_fetch_cell_prepared('SELECT snmp_index
					FROM host_snmp_cache
					WHERE host_id = ?
					AND snmp_query_id = ?
					AND field_name = ?
					AND field_value = ?',
					[$host_id, $snmp_query_id, $previous_sort_field, $current_value]);
			}

			$new_field_value = '';

			if ($remap) {
				if ($new_sort_field != $previous_sort_field) {
					$new_field_value = db_fetch_cell_prepared('SELECT field_value
						FROM host_snmp_cache
						WHERE host_id = ?
						AND snmp_query_id = ?
						AND field_name = ?
						AND snmp_index = ?',
						[$host_id, $snmp_query_id, $new_sort_field, $current_index]);

					$did_map_data = db_fetch_row_prepared('SELECT value, data_input_field_id, data_template_data_id,
						host_id, snmp_query_id
						FROM data_input_data AS did
						INNER JOIN data_input_fields AS dif
						ON did.data_input_field_id=dif.id
						INNER JOIN data_template_data AS dtd
						ON dtd.id=did.data_template_data_id
						INNER JOIN data_local AS dl
						ON dl.id=dtd.local_data_id
						WHERE value = ?
						AND dl.id = ?
						AND dl.snmp_query_id = ?
						AND data_name="index_type"',
						[$previous_sort_field, $data_source['local_data_id'], $snmp_query_id]);
				} else {
					$remap = false;

					$new_field_value = '';
				}
			}

			if ($current_index != '') {
				// Non blank index found
				// Check to see if the index changed
				if ($current_index != $data_source['snmp_index']) {
					query_debug_timer_offset('data_query', __esc('Index Change Detected! CurrentIndex: %s, PreviousIndex: %s', $current_index, $data_source['query_index']));

					db_execute_prepared('UPDATE data_local
						SET snmp_index = ?,
						orphan = 0
						WHERE id = ?',
						[$current_index, $data_source['local_data_id']]);

					$changed_ids[] = $data_source['local_data_id'];
				} elseif ($data_source['orphan'] == 1) {
					db_execute_prepared('UPDATE data_local
						SET orphan = 0
						WHERE id = ?',
						[$data_source['local_data_id']]);

					$push_out[$data_source['local_data_id']] = $data_source['local_data_id'];
				}
			} elseif ($data_source['snmp_index'] != '' && !$forced_type) {
				if (isset($query_array['index_transient']) && $query_array['index_transient'] == 'true') {
					// Found removed index, but this is expected, so no action taken
					query_debug_timer_offset('data_query', __esc('Transient Index Removal Detected! PreviousIndex: %s.  No action taken.', $data_source['query_index']));
				} else {
					// Found a deleted index, masking off to prevent issues
					query_debug_timer_offset('data_query', __esc('Index Removal Detected! PreviousIndex: %s', $data_source['query_index']));

					// Set the index to Null, note that the Data Source still has the value
					db_execute_prepared('UPDATE data_local
						SET orphan = 1
						WHERE id = ?',
						[$data_source['local_data_id']]);

					$orphaned_ids[] = $data_source['local_data_id'];
				}
			}

			if ($remap && trim($new_field_value) != '' && $new_sort_field != '') {
				db_execute_prepared('UPDATE data_input_data
					SET value = ?
					WHERE data_input_field_id = ?
					AND data_template_data_id = ?',
					[$new_field_value, $data_source['data_input_field_id'], $data_source['data_template_data_id']]);

				db_execute_prepared('UPDATE data_input_data
					SET value = ?
					WHERE data_input_field_id = ?
					AND data_template_data_id = ?',
					[$new_sort_field, $previous_did['data_input_field_id'], $previous_did['data_template_data_id']]);
			}
		}

		// if we've resolved orphan issues, push out the data source
		if (cacti_sizeof($push_out)) {
			foreach ($push_out as $po_local_data_id) {
				push_out_host($host_id, $po_local_data_id);
			}
		}

		query_debug_timer_offset('data_query', __esc('Verification of %s Local Data ID\'s Complete', cacti_sizeof($local_data)));

		if (cacti_sizeof($changed_ids) || cacti_sizeof($orphaned_ids)) {
			query_debug_timer_offset('data_query', __esc('Found Changed %s and %s Orphaned Local Data ID\'s to Re-map.', cacti_sizeof($changed_ids), cacti_sizeof($orphaned_ids)));
			data_query_remap_indexes($changed_ids);
			data_query_remap_indexes($orphaned_ids);
			query_debug_timer_offset('data_query', __esc('Done remapping Graphs to their new Indexes'));
		}

		if ((cacti_sizeof($changed_ids) || cacti_sizeof($orphaned_ids)) && !$force) {
			// update title cache for graph and data source
			update_data_source_title_cache_from_host($host_id, $snmp_query_id, array_merge($changed_ids, $orphaned_ids));
			query_debug_timer_offset('data_query', __esc('Done updating Data Source Title Cache'));

			update_graph_title_cache_from_host($host_id, $snmp_query_id, array_merge($changed_ids, $orphaned_ids));
			query_debug_timer_offset('data_query', __esc('Done updating Graph Title Cache'));
		} elseif ($force) {
			// update title cache for graph and data source
			update_data_source_title_cache_from_host($host_id, $snmp_query_id);
			query_debug_timer_offset('data_query', __esc('Done updating Data Source Title Cache'));

			update_graph_title_cache_from_host($host_id, $snmp_query_id);
			query_debug_timer_offset('data_query', __esc('Done updating Graph Title Cache'));
		}
	}

	query_debug_timer_offset('data_query', __esc('Index Association with Local Data complete'));

	update_reindex_cache($host_id, $snmp_query_id);

	// update the auto reindex cache
	if (cacti_sizeof($changed_ids)) {
		query_debug_timer_offset('data_query', __esc('Update Re-Index Cache complete. There were %s index changes, and %s orphaned indexes.', cacti_sizeof($changed_ids), cacti_sizeof($orphaned_ids)));

		// update the poller cache
		update_poller_cache_from_query($host_id, $snmp_query_id, $changed_ids);
		query_debug_timer_offset('data_query', __esc('Update Poller Cache for Query complete'));
	} else {
		query_debug_timer_offset('data_query', __esc('No Index Changes Detected, Skipping Re-Index and Poller Cache Re-population'));

		$existing_orphans = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM data_local
			WHERE snmp_query_id = ?
			AND host_id = ?
			AND orphan = 1',
			[$snmp_query_id, $host_id]);

		if ($existing_orphans) {
			$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
				FROM data_local
				WHERE snmp_query_id = ?
				LIMIT 1',
				[$snmp_query_id]);

			db_execute_prepared('UPDATE data_local
				SET orphan = 0
				WHERE snmp_query_id = ?
				AND host_id = ?',
				[$snmp_query_id, $host_id]);

			if (isset($query_array['index_orphan_removal']) && $query_array['index_orphan_removal'] == 'true') {
				push_out_host($host_id, 0, $data_template_id);
			}
		}
	}

	if (cacti_sizeof($orphaned_ids) &&
		(isset($query_array['index_orphan_removal']) && $query_array['index_orphan_removal'] == 'true')) {
		$poller_ids = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT poller_id
				FROM poller_item
				WHERE local_data_id IN (' . implode(', ', $orphaned_ids) . ')'),
			'poller_id', 'poller_id'
		);

		if (cacti_sizeof($poller_ids)) {
			foreach ($poller_ids as $poller_id) {
				api_data_source_cache_crc_update($poller_id);
			}
		}
	}

	if (POLLER_ID == 1) {
		// perform any automation on reindex
		automation_execute_data_query($host_id, $snmp_query_id);
		query_debug_timer_offset('data_query', __esc('Automation Executing for Data Query complete'));

		api_plugin_hook_function('run_data_query', ['host_id' => $host_id, 'snmp_query_id' => $snmp_query_id]);
		query_debug_timer_offset('data_query', __esc('Plugin hooks complete'));
	} elseif (CACTI_CONNECTION == 'online') {
		poller_push_reindex_data_to_poller($host_id, $snmp_query_id);

		automation_execute_data_query($host_id, $snmp_query_id);
		query_debug_timer_offset('data_query', __esc('Automation Execution for Data Query complete'));

		api_plugin_hook_function('run_data_query', ['host_id' => $host_id, 'snmp_query_id' => $snmp_query_id]);
		query_debug_timer_offset('data_query', __esc('Plugin Hooks complete'));

		if (!isset($_SESSION)) {
			$config['debug_log']['result'] = $result;
			print json_encode($config['debug_log']);
		} else {
			$_SESSION['debug_log']['result'] = $result;
			print json_encode($_SESSION['debug_log']);

			kill_session_var('debug_log');
		}
	}

	$cur_time = microtime(true);
	$total    = $cur_time - $reindex_start;

	db_execute_prepared('UPDATE host_snmp_query
		SET reindex_last_runtime = CURRENT_TIMESTAMP(),
		reindex_last_duration = ?
		WHERE host_id = ?
		AND snmp_query_id = ?',
		[$total, $host_id, $snmp_query_id]);

	// Remove orphans only if the data query expressly calls for it
	if (cacti_sizeof($orphaned_ids) &&
		(isset($query_array['index_orphan_removal']) && $query_array['index_orphan_removal'] == 'true')) {
		data_query_remove_disabled_items($orphaned_ids);
	}

	return $result;
}

/**
 * Removes disabled items from the database based on the provided orphaned IDs.
 *
 * @param array $orphaned_ids An array of orphaned `local_data_id` values to be removed.
 *
 * @return void
 */
function data_query_remove_disabled_items(array $orphaned_ids) : void {
	if (cacti_sizeof($orphaned_ids)) {
		db_execute_prepared('DELETE FROM poller_item
			WHERE local_data_id IN (' . implode(', ', $orphaned_ids) . ')');

		db_execute_prepared('DELETE FROM poller_output_boost
			WHERE local_data_id IN (' . implode(', ', $orphaned_ids) . ')');

		$archive_tables = array_rekey(
			db_fetch_assoc('SELECT TABLE_NAME
				FROM information_schema.TABLES
				WHERE TABLE_NAME LIKE "poller_output_boost_arch%"'),
			'TABLE_NAME', 'TABLE_NAME'
		);

		if (cacti_sizeof($archive_tables)) {
			foreach ($archive_tables as $table) {
				db_execute("DELETE FROM $table
					WHERE local_data_id IN (" . implode(', ', $orphaned_ids) . ')', false);
			}
		}
	}
}

/**
 * Checks if a new sort field is suitable for updating the sort field of a host's SNMP query.
 *
 * @param string $new_sort_field The new sort field to validate.
 * @param string $old_sort_field The current sort field to compare against.
 * @param int    $host_id        The ID of the host associated with the SNMP query.
 * @param int    $snmp_query_id  The ID of the SNMP query being validated.
 *
 * @return bool Returns true if the new sort field is suitable and the sort field can be updated;
 *              otherwise, returns false if the new sort field is not suitable.
 */
function query_check_suitable(string $new_sort_field, string $old_sort_field, int $host_id, int $snmp_query_id) : bool {
	if ($new_sort_field == $old_sort_field) {
		query_debug_timer_offset('data_query', __esc('Checking for Sort Field change.  No changes detected.'));

		return true;
	}

	query_debug_timer_offset('data_query', __esc('Detected New Sort Field: \'%s\' Old Sort Field \'%s\'', $new_sort_field, $old_sort_field));

	$new_sort_count = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM host_snmp_cache
		WHERE host_id = ?
		AND snmp_query_id = ?
		AND field_name = ?',
		[$host_id, $snmp_query_id, $new_sort_field]);

	$old_sort_count = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM host_snmp_cache
		WHERE host_id = ?
		AND snmp_query_id = ?
		AND field_name = ?',
		[$host_id, $snmp_query_id, $old_sort_field]);

	if ($new_sort_count < $old_sort_count) {
		query_debug_timer_offset('data_query', __esc('ERROR: New Sort Field not suitable.  Sort Field will not change.'));

		// update the cache
		db_execute_prepared('UPDATE host_snmp_query
			SET sort_field = ?
			WHERE host_id = ?
			AND snmp_query_id = ?',
			[$old_sort_field, $host_id, $snmp_query_id]);

		return false;
	}

	query_debug_timer_offset('data_query', __esc('New Sort Field validated.  Sort Field be updated.'));

	return true;
}

/**
 * Remaps SNMP indexes for local data entries and updates associated graph templates.
 *
 * @param array $local_data An array of local data IDs to process.
 *
 * @return void
 */
function data_query_remap_indexes(array $local_data) : void {
	if (cacti_sizeof($local_data)) {
		foreach ($local_data as $id) {
			$index = db_fetch_cell_prepared('SELECT snmp_index
				FROM data_local
				WHERE id = ?',
				[$id]);

			$local_graph_ids = array_rekey(
				db_fetch_assoc_prepared('SELECT DISTINCT gti.local_graph_id
					FROM graph_templates_item AS gti
					INNER JOIN data_template_rrd AS dtr
					ON gti.task_item_id=dtr.id
					WHERE dtr.local_data_id = ?',
					[$id]),
				'local_graph_id', 'local_graph_id'
			);

			if (cacti_sizeof($local_graph_ids)) {
				db_execute_prepared('UPDATE graph_local
					SET snmp_index = ?
					WHERE id IN(' . implode(', ', $local_graph_ids) . ')',
					[$index]);
			}
		}
	}
}

/**
 * Updates the input method for a given SNMP query ID by changing the associated
 * data input ID in the database. This function ensures that the data templates
 * and their associated data inputs are updated correctly when the input method
 * changes.
 *
 * @param int $snmp_query_id     - The ID of the SNMP query to update.
 * @param int $previous_input_id - The previous data input ID associated with the SNMP query.
 * @param int $new_input_id      - The new data input ID to associate with the SNMP query (optional).
 *
 * @return void
 */
function data_query_update_input_method(int $snmp_query_id, int $previous_input_id, int $new_input_id = 0) : void {
	$change_data_input = false;

	if ($previous_input_id != $new_input_id) {
		$change_data_input = true;
	}

	$data_templates = array_rekey(
		db_fetch_assoc_prepared('SELECT DISTINCT data_template_id
			FROM data_local
			WHERE snmp_query_id = ?',
			[$snmp_query_id]),
		'data_template_id', 'data_template_id'
	);

	if (cacti_sizeof($data_templates)) {
		// Look for messed up templates
		$data_inputs = db_fetch_assoc('SELECT DISTINCT data_input_id
			FROM data_template_data
			WHERE data_template_id IN (' . implode(', ', array_keys($data_templates)) . ')');

		if (cacti_sizeof($data_inputs) > 1) {
			$change_data_input = true;
		} elseif (cacti_sizeof($data_inputs) && $new_input_id != '' && $data_inputs[0]['data_input_id'] != $new_input_id) {
			$change_data_input = true;
		}

		if ($change_data_input) {
			if (cacti_sizeof($data_templates)) {
				db_execute_prepared('UPDATE data_template_data
					SET data_input_id = ?
					WHERE data_input_id = ?
					AND data_template_id IN (' . implode(', ', array_keys($data_templates)) . ')',
					[$new_input_id, $previous_input_id]);

				push_out_data_input_method($new_input_id);
			}
		}
	}
}

/**
 * Retrieves the data query array for a given SNMP query ID.
 *
 * @param int $snmp_query_id The ID of the SNMP query to retrieve the data query array for.
 *
 * @return array The parsed data query array. Returns an empty array if the XML file cannot be found.
 *
 * @throws Exception If there is an error during the XML file processing.
 */
function get_data_query_array(int $snmp_query_id) : array {
	global $data_query_xml_arrays;

	include_once(CACTI_PATH_LIBRARY . '/xml.php');

	// load the array into memory if it hasn't been done yet
	if (!isset($data_query_xml_arrays[$snmp_query_id])) {
		$xml_file_path = db_fetch_cell_prepared('SELECT xml_path FROM snmp_query WHERE id = ?', [$snmp_query_id]);

		$search        = ['<path_cacti>', '<path_snmpget>', '<path_php_binary>'];
		$replace       = [CACTI_PATH_BASE, read_config_option('path_snmpget'), read_config_option('path_php_binary')];
		$xml_file_path = str_replace($search, $replace, $xml_file_path);

		// Sanitise for log calls — encodes CR/LF and strips the full ASCII control
		// range (0x00-0x1F plus DEL 0x7F) to prevent log injection via crafted
		// xml_path values. LF/CR are already encoded by str_replace above, so
		// preg_replace never sees raw newlines; the wider range closes the TAB gap.
		$safe_path    = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace(["\r", "\n"], ['\\r', '\\n'], $xml_file_path));
		$allowed_base = realpath(CACTI_PATH_BASE);

		// Reject root or unresolvable base. rtrim('/') returns '' which makes the
		// boundary check trivially true for every absolute path (fails open). Also
		// reject Windows drive-letter roots (C:\, D:\, ...) which are too broad
		// for a safe containment check.
		if ($allowed_base                                      === false
			|| $allowed_base                                      === DIRECTORY_SEPARATOR
			|| preg_match('#^[A-Za-z]:[\\\\/]?$#', $allowed_base) === 1) {
			cacti_log('SECURITY: CACTI_PATH_BASE is invalid or resolves to filesystem root', false, 'SECURITY');

			return [];
		}

		// Reject null bytes before any filesystem call. PHP 8.0+ throws ValueError
		// when passing a string containing \0 to realpath()/file_exists(), so catch
		// it explicitly as a traversal attempt.
		if (str_contains($xml_file_path, "\0")) {
			cacti_log('SECURITY: Null byte in data query XML path rejected', false, 'SECURITY');

			return [];
		}

		// Check existence before resolving — realpath() returns false for missing
		// files, which would otherwise trigger a misleading SECURITY log entry.
		if (!file_exists($xml_file_path)) {
			query_debug_timer_offset('data_query', __esc('Could not find data query XML file at \'%s\'', $safe_path));

			return [];
		}

		$resolved = realpath($xml_file_path);

		if ($resolved === false
			|| !str_starts_with($resolved . DIRECTORY_SEPARATOR, rtrim($allowed_base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
			cacti_log('SECURITY: data query XML path outside Cacti base: ' . $safe_path, false, 'SECURITY');
			query_debug_timer_offset('data_query', __esc('SECURITY: data query XML path outside Cacti base: \'%s\'', $safe_path));

			return [];
		}

		query_debug_timer_offset('data_query', __esc('Found data query XML file at \'%s\'', $safe_path));

		// Reject directories — realpath() resolves both files and dirs, so the
		// boundary check alone does not prevent file() being called on a directory.
		if (!is_file($resolved)) {
			cacti_log('SECURITY: data query XML path is not a file: ' . $safe_path, false, 'SECURITY');

			return [];
		}

		$data = implode('', file($resolved));

		$xml_data = xml2array($data);

		/* snmp queries do not require index_order
		 * get it from the fields if not set
		 */
		calculate_or_set_index_order($xml_data);

		// store the array value to the global array for future reference
		$data_query_xml_arrays[$snmp_query_id] = $xml_data;
	}

	return $data_query_xml_arrays[$snmp_query_id];
}

/**
 * Executes a data query script for a specified host and SNMP query ID.
 *
 * @param int $host_id       The ID of the host for which the data query is executed.
 * @param int $snmp_query_id The ID of the SNMP query associated with the data query.
 *
 * @return bool Returns true on successful execution and data retrieval, or false on failure.
 */
function query_script_host(int $host_id, int $snmp_query_id) : bool {
	$script_queries = get_data_query_array($snmp_query_id);

	// invalid xml check
	if (cacti_sizeof($script_queries) == 0) {
		query_debug_timer_offset('data_query', __esc('Error parsing XML file into an array.'));

		return false;
	}

	query_debug_timer_offset('data_query', __esc('XML file parsed ok.'));

	// are we talking to script server?
	if (isset($script_queries['script_server'])) {
		$script_queries['script_path'] = '"|path_php_binary|" -q ' . $script_queries['script_path'];
	}

	if (!verify_index_order($script_queries)) {
		query_debug_timer_offset('data_query', __('Invalid field &lt;index_order&gt;%s&lt;/index_order&gt;', $script_queries['index_order']));
		query_debug_timer_offset('data_query', __('Must contain &lt;direction&gt;input&lt;/direction&gt; or &lt;direction&gt;input-output&lt;/direction&gt; fields only'));

		return false;
	}

	// We get the current sort field.  If for some reason the query
	// fails for this field, or the snmp indexes we will give up.
	$sort_field = get_best_data_query_index_type($host_id, $snmp_query_id);

	// provide data for arg_num_indexes, if given
	if (isset($script_queries['arg_num_indexes'])) {
		$script_path = get_script_query_path((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] . ' ' : '') . $script_queries['arg_num_indexes'], $script_queries['script_path'], $host_id);

		// fetch specified index at specified OID
		$script_num_index_array = exec_into_array($script_path);

		// if the number of indexes does not exist use emulation
		if (!cacti_sizeof($script_num_index_array)) {
			query_debug_timer_offset('data_query', __esc('Data Query returned no indexes.'));
			query_debug_timer_offset('data_query', __('&lt;arg_num_indexes&gt; exists in XML file but no data returned., \'Index Count Changed\' not supported'));
		}

		query_debug_timer_offset('data_query', __esc('Executing script for num of indexes \'%s\'', $script_path));

		foreach ($script_num_index_array as $element) {
			query_debug_timer_offset('data_query', __esc('Found number of indexes: %s' , $element));
		}
	} else {
		if (isset($script_queries['script_server'])) {
			query_debug_timer_offset('data_query', __('&lt;arg_num_indexes&gt; missing in XML file, \'Index Count Changed\' not supported'));
		} else {
			query_debug_timer_offset('data_query', __('&lt;arg_num_indexes&gt; missing in XML file, \'Index Count Changed\' emulated by counting arg_index entries'));
		}
	}

	// provide data for index, mandatory
	$script_path = get_script_query_path((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] . ' ' : '') . $script_queries['arg_index'], $script_queries['script_path'], $host_id);

	// fetch specified index
	$script_index_array = exec_into_array($script_path);

	if (!cacti_sizeof($script_index_array)) {
		query_debug_timer_offset('data_query', __esc('ERROR: Data Query returned no indexes.'));

		return false;
	}

	query_debug_timer_offset('data_query', __esc('Executing script for list of indexes \'%s\', Index Count: %s', $script_path, cacti_sizeof($script_index_array)));

	debug_log_insert_section_start('data_query', __esc('Click to show Data Query output for \'index\''), true);

	foreach ($script_index_array as $element) {
		debug_log_insert('data_query', __esc('Found index: %s', $element));
	}

	debug_log_insert_section_end('data_query');

	$empty_types = [];

	// set an array to host all updates
	$output_array = [];

	foreach ($script_queries['fields'] as $field_name => $field_array) {
		if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
			$rewrite_value = $field_array['rewrite_value'] ?? null;
			$script_path   = get_script_query_path((isset($script_queries['arg_prepend']) ? $script_queries['arg_prepend'] . ' ' : '') . $script_queries['arg_query'] . ' ' . $field_array['query_name'], $script_queries['script_path'], $host_id);

			debug_log_insert_section_start('data_query', __esc('Click to show Data Query output for field \'%s\'', $field_name), true);

			$script_data_array = exec_into_array($script_path);

			if (!cacti_sizeof($script_data_array) && $field_name == $sort_field) {
				$empty_types[] = $field_name;
				query_debug_timer_offset('data_query', __esc('Sort field returned no data for field name %s, skipping', $field_name));
			} else {
				debug_log_insert('data_query', __esc('Executing script query \'%s\'', $script_path));

				if (cacti_sizeof($script_data_array)) {
					foreach ($script_data_array as $element) {
						// We have two spellings for 'delimiter' so keep both for backward compatibility
						if (isset($script_queries['output_delimeter']) && preg_match('/(.*?)' . preg_quote($script_queries['output_delimeter'], '/') . '(.*)/', $element, $matches)) {
							$script_index = $matches[1];
							$field_value  = $matches[2];

							$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $rewrite_value, $field_value, $script_index, '');

							debug_log_insert('data_query', __esc('Found item [%s=\'%s\'] index: %s', $field_name, $field_value, $script_index));
						} elseif (isset($script_queries['output_delimiter']) && preg_match('/(.*?)' . preg_quote($script_queries['output_delimiter'], '/') . '(.*)/', $element, $matches)) {
							$script_index = $matches[1];
							$field_value  = $matches[2];

							$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $rewrite_value, $field_value, $script_index, '');

							debug_log_insert('data_query', __esc('Found item [%s=\'%s\'] index: %s', $field_name, $field_value, $script_index));
						}
					}
				}
			}

			debug_log_insert_section_end('data_query');
		}
	}

	if (cacti_sizeof($output_array)) {
		data_query_update_host_cache_from_buffer($host_id, $snmp_query_id, $output_array, $empty_types);
	}

	return true;
}

/**
 * Starts the query debug timer by recording the current time.
 *
 * @return void
 */
function query_debug_timer_start() : void {
	global $query_debug_timer, $query_debug_start;

	// record the start time
	$query_debug_timer = microtime(true);
	$query_debug_start = $query_debug_timer;
}

/**
 * Logs the time offset for debugging purposes.
 *
 * @param string $section The section or context of the debug log entry.
 * @param string $message A descriptive message to include in the debug log.
 *
 * @return float The time delta (in seconds) since the last recorded timestamp.
 */
function query_debug_timer_offset(string $section, string $message) : float {
	global $query_debug_timer, $query_debug_start;

	if (empty($query_debug_timer)) {
		query_debug_timer_start();
	}

	// record the start time
	$cur_time          = microtime(true);
	$delta             = $cur_time - $query_debug_timer;
	$total             = $cur_time - $query_debug_start;
	$query_debug_timer = $cur_time;

	debug_log_insert($section, __('Total: %f, Delta: %f, %s', round($total, 2), round($delta, 2), $message));

	return $delta;
}

/**
 * Stops the query debug timer, calculates the elapsed time, and logs the result.
 *
 * @param string $section The section or context in which the debug timer is being stopped.
 * @param string $message A custom message to include in the debug log entry.
 *
 * @return float The delta time (time elapsed since the last checkpoint) in seconds.
 */
function query_debug_timer_stop(string $section, string $message) : float {
	global $query_debug_timer, $query_debug_start;

	// record the start time
	$cur_time = microtime(true);
	$delta    = $cur_time - $query_debug_timer;
	$total    = $cur_time - $query_debug_start;
	unset($query_debug_timer);
	unset($query_debug_start);

	debug_log_insert($section, __esc('Total: %f, Delta: %f, %s', round($total, 2), round($delta, 2), $message));

	return $delta;
}

/**
 * Queries SNMP data for a specific host and SNMP query ID.
 *
 * @param int $host_id       The ID of the host to query.
 * @param int $snmp_query_id The ID of the SNMP query to execute.
 *
 * @return bool Returns true on success, or false on failure.
 *
 * @throws Exception If there are issues with the SNMP session or query execution.
 */
function query_snmp_host(int $host_id, int $snmp_query_id) : bool {
	global $data_query_rewrite_indexes_cache;

	include_once(CACTI_PATH_LIBRARY . '/snmp.php');

	$host = db_fetch_row_prepared('SELECT hostname, snmp_community, snmp_version,
		snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase,
		snmp_priv_protocol, snmp_context, snmp_engine_id, snmp_port, snmp_timeout,
		ping_retries, max_oids, bulk_walk_size
		FROM host
		WHERE id = ?',
		[$host_id]);

	$snmp_queries = get_data_query_array($snmp_query_id);

	// We get the current sort field.  If for some reason the query
	// fails for this field, or the snmp indexes we will give up.
	$sort_field = get_best_data_query_index_type($host_id, $snmp_query_id);

	if (!cacti_sizeof($host) || $host['hostname'] == '') {
		query_debug_timer_offset('data_query', __esc('Invalid host_id: %s', $host_id));

		return false;
	}

	// invalid xml check
	if (cacti_sizeof($snmp_queries) == 0) {
		query_debug_timer_offset('data_query', __esc('Error parsing XML file into an array.'));

		return false;
	}

	query_debug_timer_offset('data_query', __esc('XML file parsed ok.'));

	if (!verify_index_order($snmp_queries)) {
		query_debug_timer_offset('data_query', __('Invalid field &lt;index_order&gt;%s&lt;/index_order&gt;', $snmp_queries['index_order']));
		query_debug_timer_offset('data_query', __('Must contain &lt;direction&gt;input&lt;/direction&gt; or &lt;direction&gt;input-output&lt;/direction&gt; fields only'));

		return false;
	}

	$snmp_indexes = [];
	$snmp_index   = '';

	if ($host['bulk_walk_size'] <= 0) {
		$walk_size  = 5;
		$walk_sizes = [1, 5, 10, 15, 20, 25, 30, 40, 50, 60];
		$low_total  = 999;
		$selected   = -1;

		query_debug_timer_offset('data_query', __('Auto Bulk Walk Size Selected.'));

		foreach ($walk_sizes as $size) {
			$session = cacti_snmp_session($host['hostname'], $host['snmp_community'],
				$host['snmp_version'], $host['snmp_username'], $host['snmp_password'],
				$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
				$host['snmp_context'], $host['snmp_engine_id'],  $host['snmp_port'],
				$host['snmp_timeout'], $host['ping_retries'], $host['max_oids'], $size);

			if ($session === false) {
				debug_log_insert('data_query', __esc('Failed to load SNMP session.'));

				return false;
			}

			// fetch specified index at specified OID, measuring time
			$start        = microtime(true);
			$snmp_indexes = cacti_snmp_session_walk($session, $snmp_queries['oid_index']);
			$end          = microtime(true);

			query_debug_timer_offset('data_query', __esc('Tested Bulk Walk Size %d with a response of %2.4f.', $size, $end - $start));

			$total = $end - $start;

			if ($total > $low_total) {
				break;
			} else {
				$walk_size = $size;
				$low_total = $total;
			}
		}

		query_debug_timer_offset('data_query', __esc('Bulk Walk Size selected was %d.', $walk_size));

		if ($host['bulk_walk_size'] == 0) {
			query_debug_timer_offset('data_query', __esc('Saving Bulk Walk Size to Device.'));

			$host['bulk_walk_size'] = $walk_size;

			db_execute_prepared('UPDATE host
				SET bulk_walk_size = ?
				WHERE id = ?',
				[$walk_size, $host_id]);
		}
	} else {
		$walk_size = $host['bulk_walk_size'];

		query_debug_timer_offset('data_query', __esc('Bulk Walk Size is fixed at %d.', $walk_size));

		$session = cacti_snmp_session($host['hostname'], $host['snmp_community'],
			$host['snmp_version'], $host['snmp_username'], $host['snmp_password'],
			$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
			$host['snmp_context'], $host['snmp_engine_id'],  $host['snmp_port'],
			$host['snmp_timeout'], $host['ping_retries'], $host['max_oids'], $walk_size);

		if ($session === false) {
			debug_log_insert('data_query', __esc('Failed to load SNMP session.'));

			return false;
		}

		// fetch specified index at specified OID
		$snmp_indexes = cacti_snmp_session_walk($session, $snmp_queries['oid_index']);
	}

	// provide data for oid_num_indexes, if given
	if (isset($snmp_queries['oid_num_indexes'])) {
		$snmp_num_indexes = cacti_snmp_session_get($session, $snmp_queries['oid_num_indexes']);

		query_debug_timer_offset('data_query', __esc('Executing SNMP get for num of indexes @ \'%s\' Index Count: %s' , $snmp_queries['oid_num_indexes'] , $snmp_num_indexes));
	} else {
		query_debug_timer_offset('data_query', __('&lt;oid_num_indexes&gt; missing in XML file, \'Index Count Changed\' emulated by counting oid_index entries'));
	}

	query_debug_timer_offset('data_query', __esc('Executing SNMP walk for list of indexes @ \'%s\' Index Count: %s', $snmp_queries['oid_index'] , cacti_sizeof($snmp_indexes)));

	// no data found; get out
	if (!cacti_sizeof($snmp_indexes)) {
		query_debug_timer_offset('data_query', __esc('No SNMP data returned'));

		return false;
	} else {
		// show list of indices found
		foreach ($snmp_indexes as $oid => $value) {
			query_debug_timer_offset('data_query', __esc('Index found at OID: \'%s\' value: \'%s\'', $oid , $value));
		}
	}

	// the last octet of the oid is the index by default
	$index_parse_regexp = '/.*\.([0-9]+)$/';

	/* Some devices (e.g. Juniper Netscreen) pad OID indexes with trailing
	   .0 octets. When the default regex extracts the last octet, every
	   index resolves to 0. Detect this and strip the padding before
	   downstream parsing. This intentionally fixes the all-zero collapse
	   without changing the existing last-octet parsing model. See GitHub
	   issue #6108. */
	if (!isset($snmp_queries['oid_index_parse']) && oid_index_should_strip_trailing_zero_padding($snmp_indexes, $index_parse_regexp)) {
		query_debug_timer_offset('data_query', __('All indexes resolved to 0; stripping trailing .0 padding from OIDs'));

		$result = oid_index_strip_trailing_zero_padding($snmp_indexes);

		if ($result !== $snmp_indexes) {
			$snmp_indexes = $result;
		} else {
			query_debug_timer_offset('data_query', __('Skipping trailing-zero stripping: collision detected after stripping'));
		}
	}

	// Filtered index by value
	if (isset($snmp_queries['value_index_parse'])) {
		$value_parse_regexp = '/' . str_replace('VALUE/REGEXP:', '', $snmp_queries['value_index_parse']) . '/';

		foreach ($snmp_indexes as $oid => $value) {
			if (!preg_match($value_parse_regexp, $value)) {
				unset($snmp_indexes[$oid]);
			}
		}

		query_debug_timer_offset('data_query', __esc('List of indexes filtered by value @ \'%s\' Index Count: %s', $snmp_queries['oid_index'] , cacti_sizeof($snmp_indexes)));

		// show list of indices found
		foreach ($snmp_indexes as $oid => $value) {
			query_debug_timer_offset('data_query', __esc('Filtered Index by value found at OID: \'%s\' value: \'%s\'', $oid , $value));
		}
	}

	// parse the index if required
	if (isset($snmp_queries['oid_index_parse'])) {
		$index_parse_regexp = '/' . str_replace('OID/REGEXP:', '', $snmp_queries['oid_index_parse']) . '/';

		$parsed_indexes = [];

		foreach ($snmp_indexes as $oid => $value) {
			if (preg_match($index_parse_regexp, $oid)) {
				$parsed_indexes[$oid] = preg_replace($index_parse_regexp, '\\1', $oid);
			}
		}

		$snmp_indexes = $parsed_indexes;
		query_debug_timer_offset('data_query', __esc('Filtering list of indexes @ \'%s\' Index Count: %s', $snmp_queries['oid_index'] , cacti_sizeof($snmp_indexes)));

		// show list of indices found
		foreach ($snmp_indexes as $oid => $value) {
			query_debug_timer_offset('data_query', __esc('Filtered Index found at OID: \'%s\' value: \'%s\'', $oid , $value));
		}
	}

	// set an array to host all updates
	$output_array = [];

	// invalidate rewrite_index cache
	$data_query_rewrite_indexes_cache = [];

	$fields_processed = [];

	$empty_types = [];

	rewrite_snmp_enum_value(null);

	foreach ($snmp_queries['fields'] as $field_name => $field_array) {
		if ($field_array['source'] != 'index' && ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') && $field_array['method'] != 'get' &&
			(isset($field_array['rewrite_index']) || isset($field_array['oid_suffix']))) {
			$field_array['method'] = 'get';
			debug_log_insert('data_query', __esc('Fixing wrong \'method\' field for \'%s\' since \'rewrite_index\' or \'oid_suffix\' is defined', $field_name));
		}

		$rewrite_value = $field_array['rewrite_value'] ?? null;

		if ((!isset($field_array['oid'])) && ($field_array['source'] == 'index')) {
			foreach ($snmp_indexes as $value) {
				query_debug_timer_offset('data_query', __esc('Inserting index data for field \'%s\' [value=\'%s\']' , $field_name, $value));

				$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $rewrite_value, $value, $value, '');
			}
		} elseif (($field_array['method'] == 'get') && ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output')) {
			query_debug_timer_offset('data_query', __esc('Located input field \'%s\' [get]',$field_name));

			if ($field_array['source'] == 'value' && !isset($field_array['rewrite_index'])) {
				$oid_rewrite_pattern     = null;
				$oid_rewrite_replacement = null;

				if (isset($field_array['oid_rewrite_pattern']) && isset($field_array['oid_rewrite_replacement'])) {
					$oid_rewrite_pattern     = '/' . str_replace('OID/REGEXP:', '', $field_array['oid_rewrite_pattern']) . '/';
					$oid_rewrite_replacement = $field_array['oid_rewrite_replacement'];
					query_debug_timer_offset('data_query', __esc('Found OID rewrite rule: \'s/%s/%s/\'', $oid_rewrite_pattern, $oid_rewrite_replacement));
				}

				foreach ($snmp_indexes as $oid => $index) {
					$oid = $field_array['oid'] . '.' . $index;
					$oid .= isset($field_array['oid_suffix']) ? ('.' . $field_array['oid_suffix']) : '';

					// rewrite the oid if required
					if (isset($oid_rewrite_pattern)) {
						$orig_oid = $oid;
						$oid      = preg_replace($oid_rewrite_pattern, $oid_rewrite_replacement, $oid);
						query_debug_timer_offset('data_query', __esc('oid_rewrite at OID: \'%s\' new OID: \'%s\'', $orig_oid , $oid));
					}

					if (isset($field_array['output_format'])) {
						if ($field_array['output_format'] == 'hex') {
							$value_output_format = SNMP_STRING_OUTPUT_HEX;
						} elseif ($field_array['output_format'] == 'ascii') {
							$value_output_format = SNMP_STRING_OUTPUT_ASCII;
						} else {
							$value_output_format = SNMP_STRING_OUTPUT_GUESS;
						}

						$value = cacti_snmp_get($host['hostname'], $host['snmp_community'], $oid, $host['snmp_version'],
							$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'],
							$host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'],
							$host['snmp_port'], $host['snmp_timeout'], SNMP_POLLER, $host['snmp_engine_id'],
							$value_output_format);
					} else {
						$value = cacti_snmp_session_get($session, $oid);
					}

					query_debug_timer_offset('data_query', __esc('Executing SNMP get for data @ \'%s\' [value=\'%s\']', $oid, $value));

					$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $rewrite_value, $value, $index, $oid);
				}
			} elseif (isset($field_array['rewrite_index'])) {
				$rewritten_indexes = [];

				if (isset($field_array['rewrite_index'])) {
					$errmsg = [];

					$rewritten_indexes = data_query_rewrite_indexes($errmsg, $host_id, $snmp_query_id, $field_array['rewrite_index'], $snmp_indexes, $fields_processed);

					if (cacti_sizeof($errmsg)) {
						foreach ($errmsg as $message) {
							debug_log_insert('data_query', __esc('Field \'%s\' %s', $field_name,$message));
						}
					}
				}

				$values = [];
				$oid    = '';

				foreach ($snmp_indexes as $index) {
					$oid = $field_array['oid'];

					if (isset($field_array['rewrite_index'])) {
						if (isset($rewritten_indexes[$index])) {
							$oid_suffix = $rewritten_indexes[$index];
						} else {
							// we failed to build rewritten index. warnings are sent already, just skip this index silently
							continue;
						}
						$oid .= '.' . $oid_suffix;
					} else {
						$oid .= '.' . $index;
					}

					$oid .= isset($field_array['oid_suffix']) ? ('.' . $field_array['oid_suffix']) : '';
					$value = null;

					if (str_starts_with($field_array['source'], 'OID/REGEXP:')) {
						$value = preg_replace('/' . str_replace('OID/REGEXP:', '', $field_array['source']) . '/', '\\1', $oid);
					} elseif (str_starts_with($field_array['source'], 'OID2HEX/REGEXP:')) {
						$value = preg_replace('/' . str_replace('OID2HEX/REGEXP:', '', $field_array['source']) . '/', '\\1', $oid);

						$parts    = explode('.', $value);
						$ip_value = '';

						foreach ($parts as $idx => $part) {
							if (is_numeric($part)) {
								$parts[$idx] = substr(cacti_strtoupper('00' . dechex(intval($part))), -2);
							} else {
								$parts[$idx] = substr(cacti_strtoupper('00' . $part), -2);
							}

							if ($idx % 2 == 0 && $idx > 0) {
								$ip_value .= ':' . $parts[$idx];
							} else {
								$ip_value .= $parts[$idx];
							}
						}
						$value    = implode(':', $parts);

						// Check for ip address and shorten
						if (is_ipaddress($ip_value)) {
							$value = inet_ntop(inet_pton($ip_value));
						}
					}

					$values[] = ['value' => $value, 'index' => $index, 'oid' => $oid];
				}

				if (cacti_sizeof($values) > 0 && (str_starts_with($field_array['source'], 'VALUE/REGEXP:') || $field_array['source'] == 'value')) {
					$oids = [];

					foreach ($values as $key => $value) {
						$oids[] = $value['oid'];
					}

					debug_log_insert('data_query', __esc('Executing SNMP get for %s oids (%s)' , cacti_count($oids), implode(', ', $oids)));

					$value_output_format = SNMP_STRING_OUTPUT_GUESS;

					if (isset($field_array['output_format'])) {
						if ($field_array['output_format'] == 'hex') {
							$value_output_format = SNMP_STRING_OUTPUT_HEX;
						} elseif ($field_array['output_format'] == 'ascii') {
							$value_output_format = SNMP_STRING_OUTPUT_ASCII;
						} else {
							$value_output_format = SNMP_STRING_OUTPUT_GUESS;
						}
					}

					$results = [];

					foreach ($oids as $oid) {
						$results[$oid] = cacti_snmp_get($host['hostname'], $host['snmp_community'], $oid, $host['snmp_version'],
							$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'],
							$host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'],
							$host['snmp_port'], $host['snmp_timeout'], SNMP_POLLER, $host['snmp_engine_id'],
							$value_output_format);
					}

					if (!cacti_sizeof($results) && $field_name == $sort_field) {
						query_debug_timer_offset('data_query', __esc('Sort field returned no data for Host[%s] DQ[%s], skipping.', $host_id, $snmp_query_id));
					} elseif (cacti_sizeof($results)) {
						foreach ($results as $key => $value) {
							debug_log_insert('data_query',
								__esc('Found result for data @ \'%s\' [value=\'%s\']',
									$key, $value));
						}

						foreach (array_keys($values) as $key) {
							if (isset($results[$values[$key]['oid']])) {
								$values[$key]['value'] = $results[$values[$key]['oid']];
								debug_log_insert('data_query',
									__esc('Setting result for data @ \'%s\' [key=\'%s\', value=\'%s\']',
										$values[$key]['oid'], $key, $values[$key]['value']));
							} else {
								debug_log_insert('data_query',
									__esc('Skipped result for data @ \'%s\' [key=\'%s\', value=\'%s\']',
										$values[$key]['oid'], $key, $values[$key]['value']));
							}
						}

						foreach ($values as $key => $value) {
							if (str_starts_with($field_array['source'], 'VALUE/REGEXP:')) {
								$values[$key]['value'] = preg_replace('/' . str_replace('VALUE/REGEXP:', '', $field_array['source']) . '/', '\\1', $values[$key]['value']);
							}
						}
					}
				}

				foreach ($values as $item) {
					debug_log_insert('data_query', __esc('Got SNMP get result for data @ \'%s\' [value=\'%s\'] (index: %s)', $item['oid'], $item['value'], $item['index']));
					$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $rewrite_value, $item['value'], $item['index'], $item['oid']);
				}

				$values = null;
			} elseif (str_starts_with($field_array['source'], 'VALUE/REGEXP:')) {
				foreach ($snmp_indexes as $oid => $index) {
					$oid = $field_array['oid'] . '.' . $index;
					$oid .= isset($field_array['oid_suffix']) ? ('.' . $field_array['oid_suffix']) : '';

					if (isset($field_array['output_format'])) {
						if ($field_array['output_format'] == 'hex') {
							$value_output_format = SNMP_STRING_OUTPUT_HEX;
						} elseif ($field_array['output_format'] == 'ascii') {
							$value_output_format = SNMP_STRING_OUTPUT_ASCII;
						} else {
							$value_output_format = SNMP_STRING_OUTPUT_GUESS;
						}

						$value = cacti_snmp_get($host['hostname'], $host['snmp_community'], $oid, $host['snmp_version'],
							$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'],
							$host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'],
							$host['snmp_port'], $host['snmp_timeout'], SNMP_POLLER, $host['snmp_engine_id'],
							$value_output_format);
					} else {
						$value = cacti_snmp_session_get($session, $oid);
					}

					$value = preg_replace('/' . str_replace('VALUE/REGEXP:', '', $field_array['source']) . '/', '\\1', $value);

					query_debug_timer_offset('data_query', __esc('Executing SNMP get for data @ \'%s\' [value=\'$value\']', $oid, $value));

					$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $rewrite_value, $value, $index, $oid);
				}
			}
		} elseif ($field_array['method'] == 'walk' && ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output')) {
			debug_log_insert_section_start('data_query', __esc('Click to show Data Query output for field \'%s\'', $field_name), true);

			query_debug_timer_offset('data_query', __esc('Located input field \'%s\' [walk]', $field_name));

			if (isset($field_array['output_format'])) {
				if ($field_array['output_format'] == 'hex') {
					$value_output_format = SNMP_STRING_OUTPUT_HEX;
				} elseif ($field_array['output_format'] == 'ascii') {
					$value_output_format = SNMP_STRING_OUTPUT_ASCII;
				} else {
					$value_output_format = SNMP_STRING_OUTPUT_GUESS;
				}

				$data = cacti_snmp_walk($host['hostname'], $host['snmp_community'], $field_array['oid'], $host['snmp_version'],
					$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'],
					$host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'],
					$host['snmp_port'], $host['snmp_timeout'], $host['ping_retries'], $host['max_oids'], 'SNMP',
					$host['snmp_engine_id'], $value_output_format);

				$snmp_data = [];

				if (cacti_sizeof($data)) {
					foreach ($data as $d) {
						$snmp_data[$d['oid']] = $d['value'];
					}
				} elseif ($field_name == $sort_field) {
					$empty_types[] = $field_name;
					query_debug_timer_offset('data_query', __esc('Sort field returned no data for OID[%s], skipping.', $field_array['oid']));

					continue;
				}
			} else {
				$snmp_data = cacti_snmp_session_walk($session, $field_array['oid']);

				if (!cacti_sizeof($snmp_data) && $field_name == $sort_field) {
					$empty_types[] = $field_name;
					query_debug_timer_offset('data_query', __esc('Sort field returned no data for OID[%s], skipping.', $field_array['oid']));

					continue;
				}
			}

			query_debug_timer_offset('data_query', __esc('Executing SNMP walk for data @ \'%s\'', $field_array['oid']));

			$const_table = [];

			if (preg_match('/^VALUE\/TABLE:(.*)/',$field_array['source'],$matches)) {
				preg_match_all('/([^:]+):([^:]+)/',$matches[1],$match_temp);
				$const_table = array_combine($match_temp[1],$match_temp[2]);
			}

			if (preg_match('/^value/i', $field_array['source'])) {
				if (cacti_sizeof($snmp_data)) {
					foreach ($snmp_data as $oid => $value) {
						$index_regex = ($field_array['oid_index_parse'] ?? $index_parse_regexp) . (isset($field_array['oid_suffix']) ? ('.' . $field_array['oid_suffix']) : '');

						if (!preg_match($index_regex, $oid)) {
							continue;
						}

						$snmp_index = preg_replace($index_regex,'\\1', $oid);

						if (isset($snmp_queries['value_index_parse'])) {
							if (!in_array($snmp_index, $snmp_indexes, true)) {
								debug_log_insert('data_query', __esc('No index[%s] in value_index_parse, skipping.', $snmp_index));
								unset($snmp_data[$oid]);

								continue;
							}
						}

						$oid = $field_array['oid'] . ".$snmp_index" . (isset($field_array['oid_suffix']) ? ('.' . $field_array['oid_suffix']) : '');

						if ($field_name == 'ifOperStatus' || $field_name == 'ifAdminStatus') {
							$value = match (true) {
								preg_match('/^(down|2)/i', $value) > 0 => 'Down',
								preg_match('/^(up|1)/i', $value) > 0   => 'Up',
								preg_match('/^(not|6)/i', $value) > 0  => 'notPresent',
								default                                => 'Testing',
							};

							$mode = 'value';
						} elseif (preg_match('/VALUE\/REGEXP:(.*)/', $field_array['source'], $matches)) {
							$modified_value = preg_replace('/' . $matches[1] . '/', '\\1', $value);
							$mode           = 'regexp value parse';
						} elseif (preg_match('/^VALUE\/TEST:(.*):(.*):(.*)/', $field_array['source'], $matches)) {
							$modified_value = (strcmp($matches[1], $value) !== 0) ? $matches[3] : $matches[2];
							$mode           = 'test value parse';
						} elseif (preg_match('/^VALUE\/TABLE:(.*)/',$field_array['source'])) {
							$modified_value = (array_key_exists($value, $const_table)) ? $const_table[$value] : 'N/A';
							$mode           = 'table value parse';
						} elseif (preg_match('/^VALUE\/HEX2IP:(\d+):(\d+)/', $field_array['source'], $matches)) {
							// $modified_value = substr(str_replace(':','',$value),$matches[1],$matches[2]);
							$modified_value = substr(preg_replace('/(:|HEX-)/','', $value), intval($matches[1]), intval($matches[2]));
							$modified_value = implode('.', unpack('C*', pack('H*', $modified_value)));
							$mode           = 'hex2ip value parse';
						} else {
							$mode = 'value';
						}

						$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $rewrite_value, $modified_value ?? $value, $snmp_index, $oid);
						debug_log_insert('data_query', __esc('Found item [%s=\'%s\'] index: %s [from %s]',$field_name,isset($modified_value) ? "$modified_value ($value)" : $value,$snmp_index,$mode));
						unset($modified_value);
					}
				}
			} elseif (str_starts_with($field_array['source'], 'OID/REGEXP:')) {
				if (cacti_sizeof($snmp_data)) {
					foreach ($snmp_data as $oid => $value) {
						// Re-initialize the index
						$snmp_index  = '';

						$parse_value = preg_replace('/' . str_replace('OID/REGEXP:', '', $field_array['source']) . '/', '\\1', $oid);

						if (isset($snmp_queries['oid_index_parse'])) {
							$snmp_index = preg_replace($index_parse_regexp, '\\1', $oid);
						} elseif ((isset($value)) && ($value != '')) {
							$snmp_index = $value;
						}

						if (isset($snmp_queries['value_index_parse'])) {
							if (!in_array($snmp_index, $snmp_indexes, true)) {
								debug_log_insert('data_query', __esc('No index[%s] in value_index_parse, skipping.', $snmp_index));
								unset($snmp_data[$oid]);

								continue;
							}
						}

						$oid = $field_array['oid'] . '.' . $parse_value;

						// rewrite octet strings
						if (preg_match('/^\d{1,3}(\.\d{1,3}){2,}$/', $parse_value)) {
							$octets = explode('.', $parse_value);
							$size   = array_shift($octets);

							if (cacti_count($octets) == $size) {
								$decoded = '';
								$isascii = true;

								foreach ($octets as $octet) {
									$octet = intval($octet);

									if (($octet <= 31) || ($octet >= 127)) {
										$isascii = false;
									} else {
										$decoded .= chr($octet);
									}
								}

								if ($isascii) {
									query_debug_timer_offset('data_query', __esc('Found OCTET STRING \'%s\' decoded value: \'%s\'', $parse_value, $decoded));
									$parse_value = $decoded;
								}
							}
						}

						debug_log_insert('data_query', __esc('Found item [%s=\'%s\'] index: %s [from regexp oid parse]', $field_name, $parse_value, $snmp_index));

						$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $rewrite_value, $parse_value, $snmp_index, $oid);
					}
				}
			} elseif (str_starts_with($field_array['source'], 'OID2HEX/REGEXP:')) {
				if (cacti_sizeof($snmp_data)) {
					foreach ($snmp_data as $oid => $value) {
						// Re-initialize the index
						$snmp_index  = '';

						$parse_value = preg_replace('/' . str_replace('OID2HEX/REGEXP:', '', $field_array['source']) . '/', '\\1', $oid);

						if (isset($snmp_queries['oid_index_parse'])) {
							$snmp_index = preg_replace($index_parse_regexp, '\\1', $oid);
						} elseif ((isset($value)) && ($value != '')) {
							$snmp_index = $value;
						}

						if (isset($snmp_queries['value_index_parse'])) {
							if (!in_array($snmp_index, $snmp_indexes, true)) {
								debug_log_insert('data_query', __esc('No index[%s] in value_index_parse, skipping.', $snmp_index));
								unset($snmp_data[$oid]);

								continue;
							}
						}

						$oid      = $field_array['oid'] . '.' . $parse_value;
						$ip_value = '';

						// Check for an IPv6 Address or Hex String
						$parts = explode('.', $parse_value);

						foreach ($parts as $idx => $part) {
							if (is_numeric($part)) {
								$parts[$idx] = substr(cacti_strtoupper('00' . dechex(intval($part))), -2);
							} else {
								$parts[$idx] = substr(cacti_strtoupper('00' . $part), -2);
							}

							if ($idx % 2 == 0 && $idx > 0) {
								$ip_value .= ':' . $parts[$idx];
							} else {
								$ip_value .= $parts[$idx];
							}
						}
						$parse_value = implode(':', $parts);

						// Check for ip address and shorten
						if (is_ipaddress($ip_value)) {
							$parse_value = inet_ntop(inet_pton($ip_value));
						}

						debug_log_insert('data_query', __esc('Found item [%s=\'%s\'] index: %s [from regexp oid parse]', $field_name, $parse_value, $snmp_index));

						$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $rewrite_value, $parse_value, $snmp_index, $oid);
					}
				}
			} elseif (str_starts_with($field_array['source'], 'OIDVALUE/REGEXP:')) {
				$regex_array = explode(':', str_replace('OIDVALUE/REGEXP:', '', $field_array['source']));

				if (cacti_sizeof($snmp_data)) {
					foreach ($snmp_data as $oid => $value) {
						// Re-initialize the index
						$snmp_index  = '';

						$parse_value = preg_replace('/' . $regex_array[0] . '/', $regex_array[1], $oid);

						if (isset($snmp_queries['oid_index_parse'])) {
							$snmp_index = preg_replace($index_parse_regexp, '\\1', $oid);
						} elseif ((isset($value)) && ($value != '')) {
							$snmp_index = $value;
						}

						if (isset($snmp_queries['value_index_parse'])) {
							if (!in_array($snmp_index, $snmp_indexes, true)) {
								debug_log_insert('data_query', __esc('No index[%s] in value_index_parse, skipping.', $snmp_index));
								unset($snmp_data[$oid]);

								continue;
							}
						}

						$oid = $field_array['oid'];

						debug_log_insert('data_query', __esc('Found item [%s=\'%s\'] index: %s [from regexp oid value parse]', $field_name, $parse_value, $snmp_index));

						$output_array[] = data_query_format_record($host_id, $snmp_query_id, $field_name, $rewrite_value, $parse_value, $snmp_index, $oid);
					}
				}
			}

			debug_log_insert_section_end('data_query');
		}

		$fields_processed[] = $field_name;
	}

	$session->close();

	if (cacti_sizeof($output_array)) {
		data_query_update_host_cache_from_buffer($host_id, $snmp_query_id, $output_array, $empty_types);
	}

	return true;
}

/**
 * Formats a data query record for insertion into the database.
 *
 * @param int    $host_id       The ID of the host associated with the data query.
 * @param int    $snmp_query_id The ID of the SNMP query being processed.
 * @param string $field_name    The name of the field being queried.
 * @param mixed  $rewrite_value Optional value used to rewrite the SNMP field value.
 * @param string $value         The value retrieved from the SNMP query.
 * @param string $snmp_index    The SNMP index associated with the value.
 * @param string $oid           The Object Identifier (OID) for the SNMP query.
 *
 * @return string A formatted string representing the data query record, ready for database insertion.
 */
function data_query_format_record(int $host_id, int $snmp_query_id, string $field_name, $rewrite_value, string $value, string $snmp_index, string $oid) : string {
	global $data_query_rewrite_indexes_cache;

	if ($rewrite_value !== null) {
		$value = rewrite_snmp_enum_value($field_name, $value, $rewrite_value);
	}

	$hash = "$host_id@$snmp_query_id@$field_name";

	if (!isset($data_query_rewrite_indexes_cache[$hash])) {
		$data_query_rewrite_indexes_cache[$hash] = [];
	}

	$data_query_rewrite_indexes_cache[$hash][$snmp_index] = $value;

	if (mb_detect_encoding($value, mb_detect_order(), true) === false) {
		$value = bin2hex($value);
	}

	return "($host_id, $snmp_query_id, " . db_qstr($field_name) . ', ' . db_qstr($value) . ', ' . db_qstr($snmp_index) . ', ' . db_qstr($oid) . ', 1)';
}

/**
 * Validates if a given string contains only Unicode letters, numbers,
 * whitespace, and a specific set of special characters.
 *
 * @param string $value The input string to validate.
 *
 * @return int Returns 1 if the string matches the pattern, 0 if it does not.
 */
function data_query_ctype_print_unicode(string $value) : int {
	$pattern = "~^[\pL\pN\s\"\~" . preg_quote("!#$%&'()*+,-./:;<=>?@[\]^_`{|}´") . ']+$~u';

	return (int) preg_match($pattern, $value);
}

/**
 * Updates the SNMP host cache for a specific host and SNMP query using the provided data buffer.
 *
 * @param int   $host_id       The ID of the host whose SNMP cache is being updated.
 * @param int   $snmp_query_id The ID of the SNMP query associated with the cache entries.
 * @param array $output_array  An array of records to be inserted or updated in the cache.
 *                             Each record should be a string formatted as:
 *                             "(host_id, snmp_query_id, field_name, field_value, snmp_index, oid, present)".
 * @param array $empty_types   An array of field names to be marked as present in the cache.
 *
 * @return void
 */
function data_query_update_host_cache_from_buffer(int $host_id, int $snmp_query_id, array &$output_array, array &$empty_types) : void {
	// set all fields present value to 0, to mark the outliers when we are all done
	db_execute_prepared('UPDATE host_snmp_cache
		SET present = 0
		WHERE host_id = ?
		AND snmp_query_id = ?',
		[$host_id, $snmp_query_id]);

	// setup the database call
	$sql_prefix   = 'INSERT INTO host_snmp_cache (host_id, snmp_query_id, field_name, field_value, snmp_index, oid, present) VALUES';
	$sql_suffix   = ' ON DUPLICATE KEY UPDATE field_value=VALUES(field_value), oid=VALUES(oid), present=VALUES(present)';

	// use a reasonable insert buffer, the default is 1MByte
	$max_packet   = 256000;

	// setup some defaults
	$overhead     = strlen($sql_prefix) + strlen($sql_suffix);
	$buf_len      = 0;
	$buf_count    = 0;
	$buffer       = '';

	foreach ($output_array as $record) {
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

	if ($buf_count > 0) {
		db_execute($sql_prefix . $buffer . $sql_suffix);
	}

	if (cacti_sizeof($empty_types)) {
		foreach ($empty_types as $field) {
			db_execute_prepared('UPDATE host_snmp_cache
				SET present = 1
				WHERE host_id = ?
				AND snmp_query_id = ?
				AND field_name = ?',
				[$host_id, $snmp_query_id, $field]);
		}
	}

	// remove stale records from the host cache
	db_execute_prepared("DELETE FROM host_snmp_cache
		WHERE host_id = ?
		AND snmp_query_id = ?
		AND present='0'",
		[$host_id, $snmp_query_id]);
}

/**
 * Rewrites SNMP indexes based on a specified rewrite index pattern.
 *
 * @param array        &$errmsg          Reference to an array that will store error messages.
 * @param int          $host_id          The ID of the host for which the SNMP query is being processed.
 * @param int          $snmp_query_id    The ID of the SNMP query being processed.
 * @param string       $rewrite_index    The rewrite index pattern, which may include placeholders for dynamic substitution.
 * @param array|string $snmp_indexes     The SNMP indexes to be processed. Can be a single index or an array of indexes.
 * @param array|bool   $fields_processed An array of fields that have already been processed. Defaults to false.
 *
 * @return array|string|null Returns the rewritten indexes as an array if multiple indexes are provided,
 *                           a string if a single index is provided, or null if no valid output is generated.
 *
 * @throws Exception This function does not explicitly throw exceptions, but database or other errors
 *                   may occur during execution.
 */
function data_query_rewrite_indexes(array &$errmsg, int $host_id, int $snmp_query_id, string $rewrite_index,
	array|string $snmp_indexes, array|bool $fields_processed = false) : array|string|null {
	global $data_query_rewrite_indexes_cache;

	$errmsg        = [];
	$oid_items     = explode('.', $rewrite_index);
	$chain_indexes = [];

	if (!is_array($data_query_rewrite_indexes_cache)) {
		$data_query_rewrite_indexes_cache = [];
	}

	if (cacti_sizeof($oid_items)) {
		foreach ($oid_items as $item) {
			$matches = [];

			if (preg_match('/^\|query_([^|]+)\|$/', $item, $matches)) {
				$iv = $matches[1];

				if (is_array($fields_processed) && !in_array($iv, $fields_processed, true)) {
					$errmsg[] = "rewrite_index='$rewrite_index': '$iv' is not processed yet, could not use it as index source";

					continue;
				}

				if (!isset($chain_indexes[$iv])) {
					$hash =  "$host_id@$snmp_query_id@$iv";

					if (!isset($data_query_rewrite_indexes_cache[$hash])) {
						$data_query_rewrite_indexes_cache[$hash] = [];

						$iv = db_qstr($iv);

						$field_values = db_fetch_assoc_prepared('SELECT snmp_index, field_value
							FROM host_snmp_cache
							WHERE host_id = ?
							AND snmp_query_id = ?
							AND field_name = ?',
							[$host_id, $snmp_query_id, $iv]);

						if (cacti_sizeof($field_values)) {
							foreach ($field_values as $item) {
								$data_query_rewrite_indexes_cache[$hash][$item['snmp_index']] = $item['field_value'];
							}
						} else {
							$errmsg[] = " field '$iv' was not found either in cache nor in DB";
						}
					}
					$chain_indexes[$iv] = $data_query_rewrite_indexes_cache[$hash];
				}
			}
		}
	}

	$out            = [];
	$numeric_output = false;

	if (!is_array($snmp_indexes)) {
		$snmp_indexes   = [$snmp_indexes];
		$numeric_output = true;
	}

	foreach ($snmp_indexes as $num_index) {
		$index = $rewrite_index;

		foreach ($chain_indexes as $key => $values) {
			if (isset($values[$num_index]) && preg_match('/^[0-9.]+$/', $values[$num_index])) {
				$index = str_replace("|query_$key|", trim($values[$num_index]), $index);
			} else {
				$errmsg[] = "@'" . $num_index . "': could not load value of '$key'";
			}
		}

		$index = str_replace('|index|', trim($num_index), $index);

		if (!preg_match('/^[0-9.]*$/', $index)) {
			$errmsg[] = "@'" . $num_index . "': some portions of rewrite_index field were not parsed: '$index'";

			continue;
		}

		if ($numeric_output) {
			return $index;
		}
		$out[$num_index] = $index;
	}

	if ($numeric_output) {
		return null;
	}

	return $out;
}

/**
 * Rewrites an SNMP enumeration value based on a translation map.
 *
 * @param string|null $field_name The name of the field to which the translation map applies.
 *                                If null, the internal map cache is cleared.
 * @param string|null $value      The value to be rewritten. Defaults to null.
 * @param mixed       $map        The translation map, either as an array or a serialized string.
 *                                Defaults to null.
 *
 * @return string|null The rewritten value, or the original value if no match is found.
 *                     Returns null if the field name is null.
 *
 * @throws Exception If the translation map cannot be parsed.
 */
function rewrite_snmp_enum_value(string|null $field_name, string|null $value = null, mixed $map = null) : string|null {
	static $mapcache = [];

	if ($field_name === null) {
		$mapcache = [];

		return null;
	}

	if (is_array($map)) { // produced from XML, needs to be reformatted
		$newmap = [];

		foreach ($map as $index => $item) {
			if (!isset($item['match']) || !isset($item['replace'])) {
				debug_log_insert('data_query', __esc('Bogus rewrite_value item found, index=\'%s\'', $index));

				continue;
			}

			$newmap[$item['match']] = $item['replace'];
		}

		$map = $newmap;
	} else {
		$map = cacti_unserialize($map);
	}

	if ($map === false || !is_array($map)) {
		debug_log_insert('data_query', __esc('Could not parse translation map (rewrite_value)'));

		return $value;
	}

	if (!isset($mapcache[$field_name])) {
		$mapcache[$field_name] = [];

		foreach ($map as $src => $dst) {
			if (preg_match('/^REGEXP(NC)?:(.*)$/', $src, $matches)) {
				if ($matches[1] == 'NC') {
					$src = '/' . str_replace('/', '\/', $matches[2]) . '/i';
				} else {
					$src = '/' . str_replace('/', '\/', $matches[1]) . '/';
				}
			} else {
				$src = '/^' . str_replace('/^', '\/', $src) . '$/';
			}

			$mapcache[$field_name][$src] = $dst;
		}
	}

	foreach ($mapcache[$field_name] as $src => $dst) {
		if (preg_match($src, $value)) {
			$nvalue = preg_replace($src, $dst, $value);
			debug_log_insert('data_query', __esc("rewrite_value: '%s' => '%s'", $value, $nvalue));
			$value = $nvalue;

			break;
		}
	}

	return $value;
}

/**
 * Returns an array containing the data query ID and index value given
 * a data query index type/value combination and a host ID
 *
 * @param string $index_type    The type of the SNMP index field (e.g., field name).
 * @param string $index_value   The value of the SNMP index field.
 * @param int    $host_id       The ID of the host to query.
 * @param int    $data_query_id The ID of the SNMP query.
 *
 * @return string|null The SNMP index if found, or null if no matching record exists.
 */
function data_query_index(string $index_type, string $index_value, int $host_id, int $data_query_id) : string|null {
	return db_fetch_cell_prepared('SELECT snmp_index
		FROM host_snmp_cache
		WHERE field_name = ?
		AND field_value = ?
		AND host_id = ?
		AND snmp_query_id = ?',
		[$index_type, $index_value, $host_id, $data_query_id]);
}

/**
 * Returns an array containing data query information for a given data source
 *
 * @param int $data_template_data_id the ID of the data source to retrieve information for
 *
 * @return array an array that looks like:
 *
 * [
 * 	index_type  => ifIndex,
 * 	index_value => 3,
 * 	output_type => 13
 * ]
 */
function data_query_field_list(int $data_template_data_id) : array|int {
	$field = db_fetch_assoc_prepared("SELECT dif.type_code, did.value
		FROM data_input_fields AS dif
		INNER JOIN data_input_data AS did
		ON dif.id=did.data_input_field_id
		WHERE did.data_template_data_id = ?
		AND dif.type_code IN ('index_type','index_value','output_type')",
		[$data_template_data_id]);

	$field = array_rekey($field, 'type_code', 'value');

	if ((!isset($field['index_type'])) || (!isset($field['index_value'])) || (!isset($field['output_type']))) {
		return 0;
	} else {
		return $field;
	}
}

/**
 * Encodes a data query index value so that it can be included inside of a form
 *
 * @param string $index The index name to encode
 *
 * @return string The encoded data query index
 */
function encode_data_query_index(string $index) : string {
	return md5($index);
}

/**
 * Decodes a data query index value so that it can be read from a form
 *
 * @param string $encoded_index The encoded SNMP index to decode.
 * @param int    $data_query_id The ID of the data query associated with the SNMP index.
 * @param int    $host_id       The ID of the host associated with the SNMP index.
 *
 * @return mixed The decoded SNMP index if a match is found, or null if no match is found.
 */
function decode_data_query_index(string $encoded_index, int $data_query_id, int $host_id) : mixed {
	/* yes, i know MySQL has a MD5() function that would make this a bit quicker. however i would like to
	keep things abstracted for now so Cacti works with ADODB fully when i get around to porting my db calls */
	$indexes = db_fetch_assoc_prepared('SELECT snmp_index
		FROM host_snmp_cache
		WHERE host_id = ?
		AND snmp_query_id = ?
		GROUP BY snmp_index', [$host_id, $data_query_id]);

	if (cacti_sizeof($indexes)) {
		foreach ($indexes as $index) {
			if (encode_data_query_index($index['snmp_index']) == $encoded_index) {
				return $index['snmp_index'];
			}
		}
	}

	return null;
}

/**
 * Uupdates the local data query cache for each graph AND data source tied to this host/data query
 *
 * @param int $host_id       The ID of the host for which the data query cache is being updated.
 * @param int $data_query_id The ID of the data query to update the cache for.
 *
 * @return void
 */
function update_data_query_cache(int $host_id, int $data_query_id) : void {
	$graphs = db_fetch_assoc_prepared('SELECT *
		FROM graph_local
		WHERE host_id = ?
		AND snmp_query_id = ?',
		[$host_id, $data_query_id]);

	if (cacti_sizeof($graphs)) {
		foreach ($graphs as $graph) {
			update_graph_data_query_cache($graph['id'], $host_id, $data_query_id, $graph['snmp_index']);
		}
	}

	query_debug_timer_offset('data_query', __esc('Update graph data query cache complete'));

	$data_sources = db_fetch_assoc_prepared('SELECT *
		FROM data_local
		WHERE host_id = ?
		AND snmp_query_id = ?',
		[$host_id, $data_query_id]);

	if (cacti_sizeof($data_sources) > 0) {
		foreach ($data_sources as $data_source) {
			$changed = update_data_source_data_query_cache($data_source['id'], $host_id, $data_query_id, $data_source['snmp_index']);

			if ($changed) {
				update_poller_cache($data_source, true);
			}
		}
	}

	query_debug_timer_offset('data_query', __esc('Re-Indexing Data Query complete'));
}

/**
 * Updates the local data query cache for a particular graph
 *
 * @param int    $local_graph_id The ID of the local graph to update.
 * @param int    $host_id        The ID of the host associated with the graph.
 *                               If not provided, it will be fetched from the database.
 * @param int    $data_query_id  The ID of the data query. If not provided,
 *                               it will be fetched based on the graph's output type.
 * @param string $previous_index The previous index value. If the current
 *                               index differs from this value, the graph's index will
 *                               be updated.
 *
 * @return void
 */
function update_graph_data_query_cache(int $local_graph_id, int $host_id = 0, int $data_query_id = 0, string $previous_index = '') : void {
	global $data_query_id_cache;

	if ((!is_array($data_query_id_cache)) || cacti_sizeof($data_query_id_cache) == 0) {
		$data_query_id_cache = array_rekey(
			db_fetch_assoc('SELECT id, snmp_query_id
				FROM snmp_query_graph'),
			'id', 'snmp_query_id'
		);
	}

	if (empty($host_id)) {
		$host_id = db_fetch_cell_prepared('SELECT host_id
			FROM graph_local
			WHERE id = ?',
			[$local_graph_id]);
	}

	$field = data_query_field_list(db_fetch_cell_prepared('SELECT dtd.id
		FROM graph_templates_item AS gti
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id=dtr.id
		INNER JOIN data_template_data AS dtd
		ON dtr.local_data_id=dtd.local_data_id
		WHERE gti.local_graph_id = ?
		LIMIT 1',
		[$local_graph_id]));

	if (empty($field)) {
		return;
	}

	if (empty($data_query_id)) {
		$data_query_id = db_fetch_cell_prepared('SELECT snmp_query_id
			FROM snmp_query_graph
			WHERE id = ?',
			[$field['output_type']]);
	}

	$current_index = data_query_index($field['index_type'], $field['index_value'], $host_id, $data_query_id);

	if ($data_query_id != 0 && $current_index != '' && $current_index != $previous_index) {
		// set the index to the new index
		db_execute_prepared('UPDATE graph_local
			SET snmp_query_id = ?,
			snmp_index = ?
			WHERE id = ?',
			[$data_query_id, $current_index, $local_graph_id]);

		// update graph title cache
		update_graph_title_cache($local_graph_id);
	}
}

/**
 * Updates the local data query cache for a particular data source
 *
 * This function ensures that the data query cache is updated with the latest
 * SNMP query ID and index for a specific data source. It also updates the
 * data source title cache if changes are made.
 *
 * @param int    $local_data_id  The ID of the local data source to update.
 * @param int    $host_id        The ID of the host associated with the data source.
 *                               If not provided, it will be fetched from the database.
 * @param int    $data_query_id  The ID of the SNMP query. If not provided, it will
 *                               be determined based on the data source's output type.
 * @param string $previous_index The previous index value. If the current index
 *                               matches the previous index, no update will occur.
 *
 * @return bool - Returns true if the data query cache was updated successfully, false otherwise.
 */
function update_data_source_data_query_cache(int $local_data_id, int $host_id = 0, int $data_query_id = 0, string $previous_index = '') : bool {
	global $data_query_id_cache;

	if ((!is_array($data_query_id_cache)) || cacti_sizeof($data_query_id_cache) == 0) {
		$data_query_id_cache = array_rekey(
			db_fetch_assoc('SELECT id, snmp_query_id
				FROM snmp_query_graph'),
			'id', 'snmp_query_id'
		);
	}

	if (empty($host_id)) {
		$host_id = db_fetch_cell_prepared('SELECT host_id
			FROM data_local
			WHERE id = ?',
			[$local_data_id]);
	}

	$field = data_query_field_list(db_fetch_cell_prepared('SELECT
		data_template_data.id
		FROM data_template_data
		WHERE data_template_data.local_data_id = ?',
		[$local_data_id]));

	if (empty($field)) {
		return false;
	}

	if (empty($data_query_id)) {
		$data_query_id = db_fetch_cell_prepared('SELECT snmp_query_id
			FROM snmp_query_graph
			WHERE id = ?',
			[$field['output_type']]);
	}

	$current_index = data_query_index($field['index_type'], $field['index_value'], (int) $host_id, (int) $data_query_id);

	if ($data_query_id != 0 && $current_index != '' && $current_index != $previous_index) {
		// set the index to the new index
		db_execute_prepared('UPDATE data_local
			SET snmp_query_id = ?,
			snmp_index = ?,
			orphan = 0
			WHERE id = ?',
			[$data_query_id, $current_index, $local_data_id]);

		// update data source title cache
		update_data_source_title_cache($local_data_id);

		return true;
	}

	return false;
}

/**
 * Obtains a list of indexes for a host/data query that is sorted by the
 * chosen index field and formatted using the data query index title format
 *
 * @param int $host_id       The ID of the host. If 0, the function will
 *                           fetch data without considering a specific host.
 * @param int $data_query_id The ID of the data query. Must not be empty.
 *
 * @return array an array formatted like the following: $arr[snmp_index] = 'formatted data query index string'
 */
function get_formatted_data_query_indexes(int $host_id, int $data_query_id) : array {
	include_once(CACTI_PATH_LIBRARY . '/sort.php');

	if (empty($data_query_id)) {
		return ['' => __('Unknown Index')];
	}

	// from the xml; cached in 'host_snmp_query'
	if ($host_id > 0) {
		$sort_cache = db_fetch_row_prepared('SELECT sort_field, title_format
			FROM host_snmp_query
			WHERE host_id = ?
			AND snmp_query_id = ?',
			[$host_id, $data_query_id]);
	} else {
		$sort_cache = db_fetch_row_prepared('SELECT sort_field, title_format
			FROM host_snmp_query
			WHERE snmp_query_id = ?
			LIMIT 1',
			[$data_query_id]);
	}

	// in case no unique index is available, fallback to first field in XML
	if (empty($sort_cache['sort_field'])) {
		$snmp_queries = get_data_query_array($data_query_id);
		$sort_cache   = ['sort_field' => '', 'title_format' => ''];

		if (cacti_sizeof($snmp_queries) && isset($snmp_queries['index_order'])) {
			$i = explode(':', $snmp_queries['index_order']);

			if (cacti_sizeof($i) > 0) {
				$sort_cache['sort_field'] = array_shift($i);
			}
		}
	}

	/* get a list of data query indexes AND the field value that we are supposed
	to sort */
	if ($host_id > 0) {
		$sort_field_data = array_rekey(db_fetch_assoc_prepared('SELECT gl.snmp_index, hsc.field_value
			FROM graph_local AS gl
			INNER JOIN host_snmp_cache AS hsc
			ON gl.host_id=hsc.host_id
			AND gl.snmp_query_id=hsc.snmp_query_id
			AND gl.snmp_index=hsc.snmp_index
			WHERE gl.snmp_query_id = ?
			AND gl.host_id = ?
			AND hsc.field_name = ?
			GROUP BY gl.snmp_index',
			[$data_query_id, $host_id, $sort_cache['sort_field']]), 'snmp_index', 'field_value');
	} else {
		$sort_field_data = array_rekey(db_fetch_assoc_prepared('SELECT DISTINCT gl.snmp_index, hsc.field_value
			FROM graph_local AS gl
			INNER JOIN host_snmp_cache AS hsc
			ON gl.host_id=hsc.host_id
			AND gl.snmp_query_id=hsc.snmp_query_id
			AND gl.snmp_index=hsc.snmp_index
			WHERE gl.snmp_query_id = ?
			AND hsc.field_name = ?
			GROUP BY gl.snmp_index',
			[$data_query_id, $sort_cache['sort_field']]), 'snmp_index', 'field_value');
	}

	// sort the data using the 'data query index' sort algorithm
	uasort($sort_field_data, 'usort_data_query_index');

	$sorted_results = [];

	foreach ($sort_field_data as $snmp_index => $sort_field_value) {
		$sorted_results[$snmp_index] = substitute_snmp_query_data($sort_cache['title_format'], $host_id, $data_query_id, $snmp_index);
	}

	return $sorted_results;
}

/**
 * Obtains a single index for a host/data query/data query
 * index that is formatted using the data query index title format
 *
 * @param int    $host_id          The ID of the host for which the data query index is being retrieved.
 * @param int    $data_query_id    The ID of the SNMP query associated with the host.
 * @param string $data_query_index The index of the data query to be formatted.
 *
 * @return string The formatted data query index after substituting the SNMP query data.
 */
function get_formatted_data_query_index(int $host_id, int $data_query_id, string $data_query_index) : string {
	// from the xml; cached in 'host_snmp_query'
	$sort_cache = db_fetch_row_prepared('SELECT sort_field, title_format
		FROM host_snmp_query
		WHERE host_id = ?
		AND snmp_query_id = ?',
		[$host_id, $data_query_id]);

	return substitute_snmp_query_data($sort_cache['title_format'], $host_id, $data_query_id, $data_query_index);
}

/**
 * Calculates or sets the 'index_order' key in the provided raw XML array.
 *
 * @param array $raw_xml The raw XML data array passed by reference. It may
 *                       be modified to include the 'index_order' key if it
 *                       is not already set.
 *
 * @return void
 */
function calculate_or_set_index_order(array &$raw_xml) : void {
	if (!isset($raw_xml['index_order']) && isset($raw_xml['fields']) && is_array($raw_xml['fields'])) {
		foreach ($raw_xml['fields'] as $name => $attribs) {
			if (isset($attribs['source']) && $attribs['source'] == 'index') {
				$raw_xml['index_order'] = $name;

				break;
			}
		}
	}
}

/**
 * Builds an ordered list of data query index types that are valid given a list of data query
 * indexes that will be checked against the data query cache
 *
 * @param int $host_id       The ID of the host for which the index types are being retrieved.
 * @param int $data_query_id The ID of the data query associated with the host.
 *
 * @return array An ordered list of index types suitable for the given host and data query.
 *               Returns an empty array if no valid index types are found.
 *
 * @throws Exception If database queries fail or unexpected conditions occur.
 */
function get_ordered_index_type_list(int $host_id, int $data_query_id) : array {
	$raw_xml = get_data_query_array($data_query_id);

	// invalid xml check
	if (cacti_sizeof($raw_xml) == 0) {
		return [];
	}

	$must_be_numeric = false;
	$must_be_alpha   = false;
	$order_found     = true;
	$order_unknown   = false;
	$nonunique       = false;
	$oid_index       = false;
	$avail_indexes   = [];

	if (isset($raw_xml['oid_index'])) {
		$oid_index = $raw_xml['oid_index'];
	}

	if (isset($raw_xml['index_order_type'])) {
		switch ($raw_xml['index_order_type']) {
			case 'numeric':
				$mustbenumeric = true;

				break;
			case 'alpha':
			case 'alphabetic':
				$mustbealpha = true;

				break;
			case 'alphanumeric':
				break;
			default:
				cacti_log('WARNING: Unknown index_order_type of ' . $raw_xml['index_order_type'] . " found for Device[$host_id], DQ[$data_query_id].  Permitted types are [alpha:numeric:alphanumeric].  Data collection can be impacted.", false, 'REINDEX');
				$order_unknown = true;
		}

		if (isset($raw_xml['index_order'])) {
			$avail_indexes = explode(':', $raw_xml['index_order']);
		}

		if (read_config_option('data_source_trace') == 'on') {
			cacti_log("Available Sort Fields for Re-Index for Device[$host_id], DQ[$data_query_id] are [" . $raw_xml['index_order'] . ']', false, 'DSTRACE');
		}
	} elseif (!isset($raw_xml['arg_index'])) {
		cacti_log("WARNING: Missing index_order_type XML tag for Device[$host_id], DQ[$data_query_id].  Permitted types [alpha:numeric:alphanumeric].  If a monitored object changes its index, those changes will not be detected.", false, 'REINDEX');
		$order_found = false;
	}

	if (cacti_sizeof($avail_indexes) == 1) {
		if (read_config_option('data_source_trace') == 'on') {
			cacti_log("Only One possible Sort Field found during Re-Index for Device[$host_id], DQ[$data_query_id]", false, 'DSTRACE');
		}

		return $avail_indexes;
	}

	$xml_outputs = [];

	// check for nonunique query parameter, set value
	if (isset($raw_xml['index_type']) && $raw_xml['index_type'] == 'nonunique') {
		$nonunique = true;

		if (read_config_option('data_source_trace') == 'on') {
			cacti_log("Non-Uniqueue Specified during Re-Index for Device[$host_id], DQ[$data_query_id]", false, 'DSTRACE');
		}
	}

	$num_indexes = db_fetch_cell_prepared('SELECT COUNT(DISTINCT snmp_index)
		FROM host_snmp_cache
		WHERE host_id = ?
		AND snmp_query_id = ?',
		[$host_id, $data_query_id]);

	// list each of the input fields for this snmp query
	foreach ($raw_xml['fields'] as $field_name => $field_array) {
		if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
			if (isset($field_array['oid']) && $oid_index == $field_array['oid']) {
				// this is a suitable sort_field
				if (read_config_option('data_source_trace') == 'on') {
					cacti_log("Field Name '$field_name' is an SNMP index and suitable Re-Index for Device[$host_id], DQ[$data_query_id]", false, 'DSTRACE');
				}

				array_push($xml_outputs, $field_name);

				continue;
			}

			if ($order_found && array_search($field_name, $avail_indexes, true) === false) {
				// This is not a suitable index field
				if (read_config_option('data_source_trace') == 'on') {
					cacti_log("Field Name '$field_name' found not suitable during Re-Index for Device[$host_id], DQ[$data_query_id]", false, 'DSTRACE');
				}

				continue;
			}

			// Check for a non-unique sort field
			$unique = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM (
					SELECT field_value, COUNT(*) AS totals
					FROM host_snmp_cache
					WHERE host_id = ?
					AND snmp_query_id = ?
					AND field_name = ?
					GROUP BY field_value
					HAVING totals > 1
				) AS totals',
				[$host_id, $data_query_id, $field_name]);

			$total_unique = db_fetch_cell_prepared('SELECT COUNT(DISTINCT field_value)
				FROM host_snmp_cache
				WHERE host_id = ?
				AND snmp_query_id = ?
				AND field_name = ?',
				[$host_id, $data_query_id, $field_name]);

			/* If the entries are not unique and non-unique is
			 * not specified, skip this sort field
			 */
			if ($unique > 0 && !$nonunique) {
				if (read_config_option('data_source_trace') == 'on') {
					cacti_log("Field Name '$field_name' found not suitable.  Non-unique and nonunique not specified during Re-Index for Device[$host_id], DQ[$data_query_id]", false, 'DSTRACE');
				}

				continue;
			}

			if ($total_unique != $num_indexes) {
				if (read_config_option('data_source_trace') == 'on') {
					cacti_log("Field Name '$field_name' found not suitable.  The Sort field does not have sufficient values for Device[$host_id], DQ[$data_query_id]", false, 'DSTRACE');
				}

				continue;
			}

			// TODO: I think there was a plan here, not sure what it was.
			if ($must_be_alpha || $must_be_numeric) { // @phpstan-ignore-line
				// create a list of all values for this index
				$field_values = db_fetch_assoc_prepared('SELECT field_value
					FROM host_snmp_cache
					WHERE host_id = ?
					AND snmp_query_id = ?
					AND field_name = ?',
					[$host_id, $data_query_id, $field_name]);

				if (cacti_sizeof($field_values)) {
					foreach ($field_values as $value) {
						if ($must_be_numeric && !is_numeric($value)) {
							if (read_config_option('data_source_trace') == 'on') {
								cacti_log("Field Name '$field_name' found not suitable.  Must be numeric and non-numeric data found during Re-Index for Device[$host_id], DQ[$data_query_id]", false, 'DSTRACE');
							}

							continue;
						}

						if ($must_be_alpha && is_numeric($value)) { // @phpstan-ignore-line
							if (read_config_option('data_source_trace') == 'on') {
								cacti_log("Field Name '$field_name' found not suitable.  Must be alphabetic and alphabetic data found during Re-Index for Device[$host_id], DQ[$data_query_id]", false, 'DSTRACE');
							}

							continue;
						}
					}
				}
			}

			if (read_config_option('data_source_trace') == 'on') {
				cacti_log("Field Name '$field_name' found suitable during Re-Index for Device[$host_id], DQ[$data_query_id]", false, 'DSTRACE');
			}

			array_push($xml_outputs, $field_name);
		}
	}

	$return_array = [];

	// Reorder the sort fields by desired order
	if (cacti_sizeof($avail_indexes)) {
		foreach ($avail_indexes as $index) {
			if (array_search($index, $xml_outputs, true) !== false) {
				$return_array[] = $index;
			}
		}
	} else {
		$return_array = $xml_outputs;
	}

	return $return_array;
}

/**
 * Updates the sort cache for a particular host/data query  combination. This works
 * by fetching a list of valid data query index types and choosing the first one in the
 * list. The user can optionally override how the cache is updated in the data query xml file.
 *
 * @param int $host_id       The ID of the host for which the data query sort cache is being updated.
 * @param int $data_query_id The ID of the data query to update.
 *
 * @return string|false Returns the sort field if the update is successful, or `false` if no valid index types are found.
 */
function update_data_query_sort_cache(int $host_id, int $data_query_id) : string|false {
	$raw_xml = get_data_query_array($data_query_id);

	// get a list of valid data query types
	$valid_index_types = get_ordered_index_type_list($host_id, $data_query_id);

	// something is probably wrong with the data query
	if (cacti_sizeof($valid_index_types) == 0) {
		$sort_field = '';

		return false;
	} else {
		// grab the first field off the list
		$sort_field = $valid_index_types[0];
	}

	// substitute variables
	if (cacti_sizeof($raw_xml) && isset($raw_xml['index_title_format'])) {
		$title_format = str_replace('|chosen_order_field|', "|query_$sort_field|", $raw_xml['index_title_format']);
	} else {
		$title_format = "|query_$sort_field|";
	}

	// update the cache
	// TODO: if both $sort field and $title_format are empty, this yields funny results
	db_execute_prepared('UPDATE host_snmp_query
		SET sort_field = ?,
		title_format = ?
		WHERE host_id = ?
		AND snmp_query_id = ?',
		[$sort_field, $title_format, $host_id, $data_query_id]);

	return (string) $sort_field;
}

/**
 * Updates the sort cache for all data queries associated with a particular host.
 *  * See update_data_query_sort_cache() for details about updating the cache.
 *
 * @param int $host_id The ID of the host for which the data query sort cache
 *                     needs to be updated.
 *
 * @return void
 */
function update_data_query_sort_cache_by_host(int $host_id) : void {
	$data_queries = db_fetch_assoc_prepared('SELECT snmp_query_id
		FROM host_snmp_query
		WHERE host_id = ?',
		[$host_id]);

	if (cacti_sizeof($data_queries) > 0) {
		foreach ($data_queries as $data_query) {
			update_data_query_sort_cache($host_id, $data_query['snmp_query_id']);
		}
	}
}

/**
 * Returns the best available data query index type using the sort cache
 *
 * @param int $host_id       The ID of the host for which the data query is being evaluated.
 * @param int $data_query_id The ID of the data query to evaluate.
 *
 * @return mixed Returns the determined index type as a string, or `false` if the index type
 *               cannot be determined or if there is an error (e.g., missing XML file).
 *
 * @throws Exception If there is an issue with database operations or XML parsing.
 */
function get_best_data_query_index_type(int $host_id, int $data_query_id) : mixed {
	$index_type = db_fetch_cell_prepared('SELECT sort_field
		FROM host_snmp_query
		WHERE host_id = ?
		AND snmp_query_id = ?',
		[$host_id, $data_query_id]);

	if ($index_type == '') {
		$raw_xml = get_data_query_array($data_query_id);

		if (cacti_sizeof($raw_xml)) {
			if (isset($raw_xml['index_order']) && $raw_xml['index_order'] != '') {
				$order = explode(':', $raw_xml['index_order']);

				db_execute_prepared('UPDATE host_snmp_query
					SET sort_field = ?
					WHERE host_id = ?
					AND snmp_query_id = ?',
					[$order[0], $host_id, $data_query_id]);

				return (string) $order[0];
			}

			if (isset($raw_xml['fields']) && cacti_sizeof($raw_xml['fields'])) {
				$key = '';

				foreach ($raw_xml['fields'] as $key => $attribs) {
					break;
				}

				db_execute_prepared('UPDATE host_snmp_query
					SET sort_field = ?
					WHERE host_id = ?
					AND snmp_query_id = ?',
					[$key, $host_id, $data_query_id]);

				return $key;
			} else {
				return false;
			}
		} else {
			cacti_log('ERROR: Cacti Data Query DQ[' . $data_query_id . '] XML file may be missing or not readable.');

			return false;
		}
	}

	return (string) $index_type;
}

/**
 * Builds the complete script query executable path
 *
 * @param string $args        A string of arguments to be passed to the script.
 *                            Arguments can be enclosed in single or double quotes
 *                            and are split into individual components.
 * @param string $script_path The path to the script that will be executed.
 * @param int    $host_id     The ID of the host for which the script is being executed.
 *
 * @return string The complete script path with substituted arguments, ready for execution.
 */
function get_script_query_path(string $args, string $script_path, int $host_id) : string {
	include_once(CACTI_PATH_LIBRARY . '/variables.php');

	// get any extra arguments that need to be passed to the script
	if ($args != '') {
		$parts = preg_split("/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|" . "[\s,]*'([^']+)'[\s,]*|" . "[\s,]+/", $args, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

		$extra_arguments = '';

		foreach ($parts as $index => $part) {
			$extra_arguments .= ($index > 0 ? ' ' : '') . cacti_escapeshellarg(substitute_host_data($part, '|', '|', $host_id));
		}
	} else {
		$extra_arguments = '';
	}

	// get a complete path for out target script
	return substitute_script_query_path($script_path) . ' ' . $extra_arguments;
}

/**
 * Verify a given index_order against the list of input fields in the XML file
 *
 * @param  array $raw_xml The raw XML data array to be verified.
 * @return bool  Returns true if all fields in 'index_order' are found in the list
 */
function verify_index_order(array $raw_xml) : bool {
	// invalid xml check
	if (cacti_sizeof($raw_xml) == 0) {
		query_debug_timer_offset('data_query', __esc('Error parsing XML file into an array.'));

		return false;
	}

	$xml_inputs = [];

	// list each of the input fields for this snmp query
	foreach ($raw_xml['fields'] as $field_name => $field_array) {
		if (is_array($field_array) && isset($field_array['direction'])) {
			if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
				// create a list of all values for this index
				array_push($xml_inputs, $field_name);
			}
		}
	}

	$all_index_order_fields_found = true;

	// the xml file contains an ordered list of 'indexable' fields
	if (isset($raw_xml['index_order'])) {
		$index_order_array = explode(':', $raw_xml['index_order']);

		foreach ($index_order_array as $element) {
			$all_index_order_fields_found = $all_index_order_fields_found && (in_array($element, $xml_inputs, true));
		}
	} else {
		// the xml file does not contain an index order
	}

	return $all_index_order_fields_found;
}

/**
 * Perform sql updates for all required tables for new index_sort_order.
 * This code stems from lib/template.php, function create_complete_graph_from_template.
 *
 * @param array $local_data An associative array containing the local data information
 *
 * @return void
 */
function update_snmp_index_order(array $local_data) : void {
	if (cacti_sizeof($local_data)) {
		$data_input_field = array_rekey(db_fetch_assoc_prepared("SELECT
			data_input_fields.id,
			data_input_fields.type_code
			FROM snmp_query
			INNER JOIN data_input
			ON snmp_query.data_input_id = data_input.id
			INNER JOIN data_input_fields
			ON data_input.id = data_input_fields.data_input_id
			WHERE data_input_fields.type_code IN('index_type', 'index_value', 'output_type')
			AND snmp_query.id = ?",
			[$local_data['snmp_query_id']]), 'type_code', 'id');

		$snmp_cache_value = db_fetch_cell_prepared('SELECT field_value
			FROM host_snmp_cache
			WHERE host_id = ?
			AND snmp_query_id = ?
			AND field_name = ?
			AND snmp_index = ?',
			[$local_data['host_id'], $local_data['snmp_query_id'], $local_data['snmp_index_on'], $local_data['snmp_index']]);

		// only update data source index if there actually *is* an index returned from host_snmp_cache
		if (!empty($snmp_cache_value)) {
			db_execute_prepared('REPLACE INTO data_input_data
				(data_input_field_id, data_template_data_id, data_template_id, local_data_id, host_id, t_value, value)
				VALUES
				(?, ?, ?, ?, ?, "", ?),
				(?, ?, ?, ?, ?, "", ?),
				(?, ?, ?, ?, ?, "", ?)',
				[
					// save the value to index on (ie. ifindex, ifip, etc)
					$data_input_field['index_type'], $local_data['data_template_data_id'], $local_data['data_template_id'], $local_data['local_data_id'], $local_data['host_id'], $local_data['snmp_index_on'],
					// save the actual value (ie. 3, 192.168.1.101, etc)
					$data_input_field['index_value'], $local_data['data_template_data_id'], $local_data['data_template_id'], $local_data['local_data_id'], $local_data['host_id'], $snmp_cache_value,
					// set the expected output type (ie. bytes, errors, packets)
					$data_input_field['output_type'], $local_data['data_template_data_id'], $local_data['data_template_id'], $local_data['local_data_id'], $local_data['host_id'], $local_data['snmp_query_graph_id']
				]
			);
		}

		// now that we have put data into the 'data_input_data' table, update the snmp cache for ds's
		// update_data_source_data_query_cache($local_data['local_data_id']);
	}
}

/**
 * Verify that a Data Query Graph Template is properly mapped
 *
 * @param int   $snmp_query_graph_id The ID of the SNMP query graph to validate.
 * @param array $post                The POST data containing user input for the data source mappings.
 *
 * @return bool Returns true if there are validation errors, false otherwise.
 */
function api_data_query_errors(int $snmp_query_graph_id, array $post) : bool {
	$graph_template_id = db_fetch_cell_prepared('SELECT gt.id
		FROM graph_templates AS gt
		INNER JOIN snmp_query_graph AS sqg
		ON gt.id = sqg.graph_template_id
		WHERE sqg.id = ?',
		[$snmp_query_graph_id]);

	$data_sources = db_fetch_assoc_prepared('SELECT DISTINCT dtr.id, dtr.data_source_name, dtr.data_template_id
		FROM data_template_rrd AS dtr
		INNER JOIN graph_templates_item AS gti
		ON dtr.id = gti.task_item_id
		WHERE dtr.local_data_id = 0
		AND gti.graph_template_id = ?',
		[$graph_template_id]);

	$errors = false;

	if (cacti_sizeof($data_sources)) {
		foreach ($data_sources as $ds) {
			if (!isset($post['dsdt_' . $ds['data_template_id'] . '_' . $ds['id'] . '_check'])) {
				raise_message('mapping_error', __('You must select an XML output column for Data Source \'%s\' and toggle the checkbox to its right', $ds['data_source_name']), MESSAGE_LEVEL_ERROR);

				$errors = true;
			}
		}
	} else {
		raise_message('assign_error', __('Your Graph Template has not Data Templates in use.  Please correct your Graph Template'), MESSAGE_LEVEL_ERROR);
		$errors = true;
	}

	return $errors;
}

/**
 * Duplicates an SNMP data query and its associated graphs and sub-graphs.
 *
 * @param int    $_data_query_id  The ID of the data query to duplicate.
 * @param string $data_query_name The name for the new duplicated data query.
 *
 * @return int|false The ID of the newly created data query on success, or false on failure.
 */
function data_query_duplicate(int $_data_query_id, string $data_query_name) : int|false {
	$data_query = db_fetch_row_prepared('SELECT *
		FROM snmp_query
		WHERE id = ?',
		[$_data_query_id]);

	if (!cacti_sizeof($data_query)) {
		return false;
	}

	$data_query_name = str_replace('<dataquery_name>', $data_query['name'], $data_query_name);

	$save         = $data_query;
	$save['id']   = 0;
	$save['hash'] = generate_hash();
	$save['name'] = $data_query_name;

	$data_query_id = sql_save($save, 'snmp_query');

	$_snmp_query_graph = db_fetch_assoc_prepared('SELECT *
		FROM snmp_query_graph
		WHERE snmp_query_id = ?',
		[$_data_query_id]);

	if (cacti_sizeof($_snmp_query_graph)) {
		foreach ($_snmp_query_graph as $_sqg) {
			$save                  = $_sqg;
			$save['id']            = 0;
			$save['hash']          = generate_hash();
			$save['snmp_query_id'] = $data_query_id;
			$sqg_id                = sql_save($save, 'snmp_query_graph');

			$_snmp_query_graph_rrd = db_fetch_assoc_prepared('SELECT *
				FROM snmp_query_graph_rrd
				WHERE snmp_query_graph_id = ?',
				[$_sqg['id']]);

			if (cacti_sizeof($_snmp_query_graph_rrd)) {
				foreach ($_snmp_query_graph_rrd as $_sqgr) {
					$save = $_sqgr;

					$save['snmp_query_graph_id'] = $sqg_id;

					sql_save($save, 'snmp_query_graph_rrd');
				}
			}

			$_snmp_query_graph_rrd_sv = db_fetch_assoc_prepared('SELECT *
				FROM snmp_query_graph_rrd_sv
				WHERE snmp_query_graph_id = ?',
				[$_sqg['id']]);

			if (cacti_sizeof($_snmp_query_graph_rrd_sv)) {
				foreach ($_snmp_query_graph_rrd_sv as $_sqgrs) {
					$save         = $_sqgrs;
					$save['id']   = 0;
					$save['hash'] = generate_hash();

					$save['snmp_query_graph_id'] = $sqg_id;

					sql_save($save, 'snmp_query_graph_rrd_sv');
				}
			}

			$_snmp_query_graph_sv = db_fetch_assoc_prepared('SELECT *
				FROM snmp_query_graph_sv
				WHERE snmp_query_graph_id = ?',
				[$_sqg['id']]);

			if (cacti_sizeof($_snmp_query_graph_sv)) {
				foreach ($_snmp_query_graph_sv as $_sqgs) {
					$save         = $_sqgs;
					$save['id']   = 0;
					$save['hash'] = generate_hash();

					$save['snmp_query_graph_id'] = $sqg_id;

					sql_save($save, 'snmp_query_graph_sv');
				}
			}
		}

		return $data_query_id;
	}

	return false;
}

/**
 * Detect when a multi-row OID walk appears padded with trailing .0 octets and
 * can safely be considered for stripping. The final safety check parses the
 * candidate stripped OIDs and requires unique indexes, so single-.0 padding
 * can be repaired without accepting collisions.
 *
 * @param array  $indexes            Associative array of OID => value.
 * @param string $index_parse_regexp Default last-octet index regexp.
 */
function oid_index_should_strip_trailing_zero_padding(array $indexes, string $index_parse_regexp): bool {
	if (cacti_sizeof($indexes) <= 1) {
		return false;
	}

	$test_indexes      = [];
	$all_end_with_zero = true;

	foreach ($indexes as $oid => $value) {
		if (preg_match($index_parse_regexp, $oid, $matches)) {
			$test_indexes[$oid] = $matches[1];
		} else {
			return false;
		}

		if (!preg_match('/(?:\.0)+$/', $oid)) {
			$all_end_with_zero = false;
		}
	}

	$unique = array_unique(array_values($test_indexes));

	if (!$all_end_with_zero || cacti_sizeof($unique) !== 1 || $unique[0] !== '0') {
		return false;
	}

	$stripped = oid_index_strip_trailing_zero_padding($indexes);

	if ($stripped === $indexes) {
		return false;
	}

	$stripped_indexes = [];

	foreach (array_keys($stripped) as $oid) {
		if (!preg_match($index_parse_regexp, $oid, $matches)) {
			return false;
		}

		$stripped_indexes[] = $matches[1];
	}

	return cacti_sizeof(array_unique($stripped_indexes)) === cacti_sizeof($stripped_indexes);
}

/**
 * Strips trailing .0 octets from SNMP OID index keys when all indexes in a
 * multi-row walk resolve to 0 under the default last-octet regex.
 *
 * Only called when cacti_sizeof($indexes) > 1 and oid_index_parse is absent,
 * so scalar OIDs (single-row walks and custom-regex walks) are never touched.
 *
 * Detection is intentionally conservative: stripping is only attempted when all
 * OIDs currently resolve to index 0, every OID ends in .0, and the stripped
 * OIDs parse to a unique index set.
 *
 * Residual risk: a multi-row walk where every legitimate index ends in .0 and
 * stripping still yields unique indexes can match the heuristic. Operators can
 * avoid auto-detection for those walks by configuring oid_index_parse.
 *
 * @param array $indexes Associative array of OID => value from an SNMP walk.
 *
 * @return array Stripped array if safe (no key collisions); original array otherwise.
 */
function oid_index_strip_trailing_zero_padding(array $indexes): array {
	if (cacti_sizeof($indexes) <= 1) {
		return $indexes;
	}

	/* Partial padding would silently reshape a legitimate walk: only strip
	   when every OID in the set ends with a .0 segment. */
	foreach ($indexes as $oid => $value) {
		if (!preg_match('/(?:\.0)+$/', (string) $oid)) {
			return $indexes;
		}
	}

	$stripped = [];

	foreach ($indexes as $oid => $value) {
		$stripped_oid = preg_replace('/(?:\.0)+$/', '', $oid);

		if (isset($stripped[$stripped_oid])) {
			/* Stripping would merge two distinct OIDs into one key,
			   silently dropping a row. Bail out and keep originals. */
			return $indexes;
		}

		$stripped[$stripped_oid] = $value;
	}

	/* Defence-in-depth: if any row was lost between the input and the
	   stripped map (collisions the isset() guard above did not catch,
	   e.g. via type juggling on numeric string keys), return originals
	   rather than silently dropping rows. */
	if (cacti_sizeof($stripped) !== cacti_sizeof($indexes)) {
		return $indexes;
	}

	return $stripped;
}
