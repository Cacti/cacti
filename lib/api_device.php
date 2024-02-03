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

/**
 * api_device_crc_update - update hash stored in settings table to inform
 * remote pollers to update their caches
 *
 * @param  (int)    The id of the poller impacted by hash update
 * @param  (string) The hash variable prefix for the replication setting.
 *
 * @return (void)
 */
function api_device_cache_crc_update($poller_id, $variable = 'poller_replicate_device_cache_crc') {
	$hash = hash('ripemd160', date('Y-m-d H:i:s') . rand() . $poller_id);

	db_execute_prepared("REPLACE INTO settings SET value = ?, name='$variable" . "_" . "$poller_id'", array($hash));
}

/**
 * api_device_remove - removes a device
 *
 * @param  $device_id - the id of the device to remove
 */
function api_device_remove($device_id) {
	global $config;

	$poller_id = db_fetch_cell_prepared('SELECT poller_id
		FROM host WHERE id = ?',
		array($device_id));

	api_plugin_hook_function('device_remove', array($device_id));

	if ($poller_id == 1) {
		db_execute_prepared('DELETE FROM host WHERE id = ?', array($device_id));
	} else {
		db_execute_prepared('UPDATE host SET deleted = "on" WHERE id = ?', array($device_id));
	}

	db_execute_prepared('DELETE FROM host_graph       WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM host_snmp_query  WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM host_snmp_cache  WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM poller_item      WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM poller_reindex   WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM graph_tree_items WHERE host_id = ?', array($device_id));
	db_execute_prepared('DELETE FROM reports_items    WHERE host_id = ?', array($device_id . ':%'));
	db_execute_prepared('DELETE FROM poller_command   WHERE command LIKE ?', array($device_id . ':%'));

	if ($poller_id > 1) {
		api_device_purge_from_remote($device_id, $poller_id);
	}

	$graphs = array_rekey(
		db_fetch_assoc_prepared('SELECT id
			FROM graph_local
			WHERE host_id = ?',
			array($device_id)),
		'id', 'id'
	);

	if (cacti_sizeof($graphs)) {
		api_delete_graphs($graphs, 2);
	}

	api_device_cache_crc_update($poller_id);

	/**
	 * Save the last time a device/site was created/updated
	 * for Caching.
	 */
	set_config_option('time_last_change_device', time());
	set_config_option('time_last_change_site_device', time());
}

/**
 * api_device_purge_from_remote - removes a device from a remote data collectors
 *
 * @param  $device_ids - device id or an array of device_ids of a host or hosts
 * @param  $poller_id  - the previous poller if it changed
 */
function api_device_purge_from_remote($device_ids, $poller_id = 0) {
	if (!is_array($device_ids)) {
		$device_ids = array($device_ids);
	}

	if ($poller_id > 1) {
		if (remote_poller_up($poller_id)) {
			if (($rcnn_id = poller_push_to_remote_db_connect($poller_id, true)) !== false) {
				db_execute('DELETE FROM host             WHERE      id IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);
				db_execute('DELETE FROM host_graph       WHERE host_id IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);
				db_execute('DELETE FROM host_snmp_query  WHERE host_id IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);
				db_execute('DELETE FROM host_snmp_cache  WHERE host_id IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);
				db_execute('DELETE FROM poller_item      WHERE host_id IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);
				db_execute('DELETE FROM poller_reindex   WHERE host_id IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);
				db_execute('DELETE FROM graph_tree_items WHERE host_id IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);
				db_execute('DELETE FROM reports_items    WHERE host_id IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);

				db_execute('DELETE FROM poller_command
					WHERE SUBSTRING_INDEX(command, ":", 1) IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);

				db_execute('DELETE FROM data_local       WHERE host_id IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);
				db_execute('DELETE FROM graph_local      WHERE host_id IN (' . implode(', ', $device_ids) . ')', true, $rcnn_id);
			} else {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
			}
		} else {
			raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
		}

		foreach($device_ids as $id) {
			db_execute_prepared('INSERT INTO poller_command
				(poller_id, time, action, command)
				VALUES (?, NOW(), ?, ?)
				ON DUPLICATE KEY UPDATE time=VALUES(time)',
				array($poller_id, POLLER_COMMAND_PURGE, $id));
		}
	}
}

/**
 * api_device_purge_deleted_devices - Remove any devices from the database that are
 *   marked for deletion.
 *
 * @return (void)
 */
function api_device_purge_deleted_devices() {
	$devices = db_fetch_assoc_prepared('SELECT id, poller_id
		FROM host
		WHERE deleted = "on"
		AND UNIX_TIMESTAMP(last_updated) < UNIX_TIMESTAMP()-500');

	if (cacti_sizeof($devices)) {
		foreach($devices as $d) {
			db_execute_prepared('DELETE FROM host             WHERE      id = ?', array($d['id']));
			db_execute_prepared('DELETE FROM host_graph       WHERE host_id = ?', array($d['id']));
			db_execute_prepared('DELETE FROM host_snmp_query  WHERE host_id = ?', array($d['id']));
			db_execute_prepared('DELETE FROM host_snmp_cache  WHERE host_id = ?', array($d['id']));
			db_execute_prepared('DELETE FROM poller_item      WHERE host_id = ?', array($d['id']));
			db_execute_prepared('DELETE FROM poller_reindex   WHERE host_id = ?', array($d['id']));
			db_execute_prepared('DELETE FROM graph_tree_items WHERE host_id = ?', array($d['id']));
			db_execute_prepared('DELETE FROM reports_items    WHERE host_id = ?', array($d['id'] . ':%'));
			db_execute_prepared('DELETE FROM poller_command   WHERE command LIKE ?', array($d['id'] . ':%'));
			db_execute_prepared('DELETE FROM data_local       WHERE host_id = ?', array($d['id']));
			db_execute_prepared('DELETE FROM graph_local      WHERE host_id = ?', array($d['id']));

			api_device_purge_from_remote($d['id'], $d['poller_id']);
		}
	}
}

/**
 * api_device_remove_multi - removes multiple devices in one call
 *
 * @param  (array) An array of device id's to remove
 * @param  (int)   Boolean to keep data source and graphs or remove
 *
 * @return (void)
 */
function api_device_remove_multi($device_ids, $delete_type = 2) {
	global $config;

	$devices_to_delete = '';
	$i = 0;

	if (cacti_sizeof($device_ids)) {
		api_plugin_hook_function('device_remove', $device_ids);

		$data_sources = array();
		$graphs       = array();

		$data_sources = array_rekey(
			db_fetch_assoc('SELECT id
				FROM data_local
				WHERE host_id IN (' . implode(', ', $device_ids) . ')'),
			'id', 'id'
		);

		$graphs = array_rekey(
			db_fetch_assoc('SELECT id
				FROM graph_local
				WHERE host_id IN (' . implode(', ', $device_ids) . ')'),
			'id', 'id'
		);

		/* build the list */
		foreach($device_ids as $device_id) {
			if ($i == 0) {
				$devices_to_delete .= $device_id;
			} else {
				$devices_to_delete .= ', ' . $device_id;
			}

			/* poller commands go one at a time due to trashy logic */
			db_execute_prepared('DELETE FROM poller_item    WHERE host_id = ?', array($device_id));
			db_execute_prepared('DELETE FROM poller_reindex WHERE host_id = ?', array($device_id));
			db_execute_prepared('DELETE FROM poller_command WHERE command LIKE ?', array($device_id . ':%'));

			$poller_id = db_fetch_cell_prepared('SELECT poller_id
				FROM host
				WHERE id = ?',
				array($device_id));

			$i++;
		}

		$poller_ids = get_remote_poller_ids_from_devices($devices_to_delete);

		// handle removal or mark for removal as required
		db_execute("DELETE FROM host WHERE id IN ($devices_to_delete) AND poller_id = 1");
		db_execute("UPDATE host SET deleted = 'on' WHERE id IN ($devices_to_delete) AND poller_id != 1");

		db_execute("DELETE FROM host_graph       WHERE host_id IN ($devices_to_delete)");
		db_execute("DELETE FROM host_snmp_query  WHERE host_id IN ($devices_to_delete)");
		db_execute("DELETE FROM host_snmp_cache  WHERE host_id IN ($devices_to_delete)");
		db_execute("DELETE FROM graph_tree_items WHERE host_id IN ($devices_to_delete)");
		db_execute("DELETE FROM reports_items    WHERE host_id IN ($devices_to_delete)");

		if ($delete_type == 2) {
			api_delete_graphs($graphs, $delete_type);
		} else {
			api_data_source_disable_multi($data_sources);

			db_execute("UPDATE graph_local SET host_id = 0 WHERE host_id IN($devices_to_delete)");
			db_execute("UPDATE data_local  SET host_id = 0 WHERE host_id IN($devices_to_delete)");
		}

		if (cacti_sizeof($poller_ids)) {
			foreach($poller_ids as $poller_id) {
				api_device_cache_crc_update($poller_id);
				api_device_purge_from_remote($device_ids, $poller_id);
			}
		}

		/**
		 * Save the last time a device/site was created/updated
		 * for Caching.
		 */
		set_config_option('time_last_change_device', time());
		set_config_option('time_last_change_site_device', time());
	}
}

/**
 * api_device_disable_devices - Disable an array of device ids
 *
 * @param  (array) An array of device ids
 *
 * @return (void)
 */
function api_device_disable_devices($device_ids) {
	global $config;

	$raised = array();

	foreach ($device_ids as $device_id) {
		db_execute_prepared("UPDATE host
			SET disabled = 'on', status = 0
			WHERE id = ?
			AND (deleted = '' OR (deleted = 'on' AND disabled = ''))",
			array($device_id));

		$poller_id = db_fetch_cell_prepared('SELECT poller_id FROM host WHERE id = ?', array($device_id));

		if ($poller_id > 1) {
			if (remote_poller_up($poller_id)) {
				if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
					db_execute_prepared("UPDATE host
						SET disabled='on'
						WHERE id = ?
						AND (deleted = '' OR (deleted = 'on' AND disabled = ''))",
						array($device_id), true, $rcnn_id);
				} elseif (!isset($raised[$poller_id])) {
					raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					$raised[$poller_id] = true;
				}
			} elseif (!isset($raised[$poller_id])) {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
				$raised[$poller_id] = true;
			}
		}
	}
}

/**
 * api_device_enable_devices - Enable an array of device ids
 *
 * @param  (array) An array of device ids
 *
 * @return (void)
 */
function api_device_enable_devices($device_ids) {
	global $config;

	$raised = array();

	foreach ($device_ids as $device_id) {
		$poller_id = db_fetch_cell_prepared('SELECT poller_id
			FROM host
			WHERE id = ?',
			array($device_id));

		db_execute_prepared("UPDATE host
			SET disabled = ''
			WHERE id = ?
			AND deleted = ''",
			array($device_id));

		if ($poller_id > 1) {
			$poller_cache = 0;

			if (remote_poller_up($poller_id)) {
				if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
					db_execute_prepared("UPDATE host
						SET disabled = ''
						WHERE id = ?",
						array($device_id), true, $rcnn_id);

					$poller_cache = db_fetch_cell_prepared('SELECT COUNT(local_data_id)
						FROM poller_item
						WHERE host_id = ?',
						array($device_id), '', true, $rcnn_id);
				} elseif (!isset($raised[$poller_id])) {
					raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					$raised[$poller_id] = true;
				}

			} elseif (!isset($raised[$poller_id])) {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);

				$raised[$poller_id] = true;
			}
		} else {
			$poller_cache = db_fetch_cell_prepared('SELECT COUNT(local_data_id)
				FROM poller_item
				WHERE host_id = ?',
				array($device_id));
		}

		/**
		 * Only reprime poller cache if empty this allows support for pre 1.2.16
		 * enable behavior.
		 */
		if (!cacti_sizeof($poller_cache)) {
			/* update poller cache */
			$data_sources = db_fetch_assoc_prepared('SELECT id
				FROM data_local
				WHERE host_id = ?',
				array($device_id));

			$poller_items = $local_data_ids = array();

			if (cacti_sizeof($data_sources)) {
				foreach ($data_sources as $data_source) {
					$local_data_ids[] = $data_source['id'];
					$poller_items     = array_merge($poller_items, update_poller_cache($data_source['id']));
				}
			}

			if (cacti_sizeof($local_data_ids)) {
				poller_update_poller_cache_from_buffer($local_data_ids, $poller_items, $poller_id);
			}
		}

		if ($poller_id > 1) {
			if (remote_poller_up($poller_id)) {
				if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
					poller_push_reindex_data_to_poller($device_id, 0, true, $rcnn_id);
				} elseif (!isset($raised[$poller_id])) {
					raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					$raised[$poller_id] = true;
				}
			} elseif (!isset($raised[$poller_id])) {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
				$raised[$poller_id] = true;
			}
		}
	}
}

/**
 * api_device_change_options - Given an array of device ids and the
 *   post variable, update a series of Device settings.
 *
 * @param  (array) An array of device ids
 * @param  (array) An array representing the $_POST variable
 *
 * @return (void)
 */
function api_device_change_options($device_ids, $post) {
	global $config, $fields_host_edit;

	$previous_poller = -1;
	$poller_ids      = array();
	$raised          = array();

	foreach ($device_ids as $device_id) {
		foreach ($fields_host_edit as $field_name => $field_array) {
			if (isset($post["t_$field_name"])) {
				if ($field_name == 'poller_id') {
					$old_poller = db_fetch_cell_prepared('SELECT poller_id
						FROM host
						WHERE id = ?',
						array($device_id));

					if ($old_poller > 1 && $old_poller != get_nfilter_request_var($field_name)) {
						$previous_poller = get_nfilter_request_var($field_name);

						api_device_purge_from_remote($device_id, $old_poller);
					}

					// Update the local device and replicate
					if ($old_poller !=  get_nfilter_request_var($field_name && get_nfilter_request_var($field_name) > 1)) {
						api_device_replicate_out($device_id, get_nfilter_request_var($field_name));
					}
				}

				db_execute_prepared("UPDATE host
					SET $field_name = ?
					WHERE id = ?
					AND deleted = ''",
					array(get_nfilter_request_var($field_name), $device_id));

				if (!isset($poller_ids[$device_id])) {
					$poller_ids[$device_id] = db_fetch_cell_prepared('SELECT poller_id FROM host WHERE id = ?', array($device_id));
				}

				$poller_id = $poller_ids[$device_id];

				if ($poller_id > 1) {
					if (remote_poller_up($poller_id)) {
						if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
							db_execute_prepared("UPDATE host
								SET $field_name = ?
								WHERE id = ?
								AND deleted = ''",
								array(get_nfilter_request_var($field_name), $device_id), true, $rcnn_id);
						} elseif (!isset($raised[$poller_id])) {
							raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
							$raised[$poller_id] = true;
						}
					} elseif (!isset($raised[$poller_id])) {
						raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
						$raised[$poller_id] = true;
					}
				}

				if ($field_name == 'host_template_id') {
					api_device_update_host_template($device_id, get_nfilter_request_var($field_name));
				}
			}
		}

		push_out_host($device_id);
	}
}

/**
 * api_device_clear_statistics - Clear all device level statistics and reset as if the
 *   device was new in Cacti
 *
 * @param  (array) An array of device ids
 *
 * @return (void)
 */
function api_device_clear_statistics($device_ids) {
	global $config;

	$raised = array();

	foreach ($device_ids as $device_id) {
		db_execute_prepared("UPDATE host
			SET min_time = '9.99999', max_time = '0', cur_time = '0', avg_time = '0',
			total_polls = '0', failed_polls = '0',  availability = '100.00'
			WHERE id = ?
			AND deleted = ''",
			array($device_id));

		$poller_id = db_fetch_cell_prepared('SELECT poller_id FROM host WHERE id = ?', array($device_id));

		if ($poller_id > 1) {
			if (remote_poller_up($poller_id)) {
				if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
					db_execute_prepared("UPDATE host
						SET min_time = '9.99999', max_time = '0', cur_time = '0', avg_time = '0',
						total_polls = '0', failed_polls = '0',  availability = '100.00'
						WHERE id = ?
						AND deleted = ''",
						array($device_id), true, $rcnn_id);
				} elseif (!isset($raised[$poller_id])) {
					raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					$raised[$poller_id] = true;
				}
			} elseif (!isset($raised[$poller_id])) {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
				$raised[$poller_id] = true;
			}
		}
	}
}

/**
 * api_device_sync_device_templates - Sync an array of device ids with their
 *   parent Device Template
 *
 * @param (array) An array of device ids
 *
 * @return (void)
 */
function api_device_sync_device_templates($device_ids) {
	global $config;

	foreach ($device_ids as $device_id) {
		$device_template_id = db_fetch_cell_prepared('SELECT host_template_id
			FROM host
 			WHERE id = ?',
			array($device_id));

		if ($device_template_id > 0) {
			api_device_update_host_template($device_id, $device_template_id);
		}
	}
}

/**
 * api_device_dq_add - adds a device->data query mapping
 * @param  (int)  The id of the device which contains the mapping
 * @param  (int)  The id of the data query to remove the mapping for
 * @param  (int)  The reindex method to user when adding the data query
 *
 * @return (void)
 */
function api_device_dq_add($device_id, $data_query_id, $reindex_method) {
	global $config;

	db_execute_prepared('REPLACE INTO host_snmp_query
		(host_id, snmp_query_id, reindex_method)
		VALUES (?, ?, ?)',
		array($device_id, $data_query_id, $reindex_method));

	$poller_id = db_fetch_cell_prepared('SELECT poller_id FROM host WHERE id = ?', array($device_id));

	if ($poller_id > 1) {
		if (remote_poller_up($poller_id)) {
			if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
				db_execute_prepared('REPLACE INTO host_snmp_query
					(host_id, snmp_query_id, reindex_method)
					VALUES (?, ?, ?)',
					array($device_id, $data_query_id, $reindex_method), true, $rcnn_id);
			} else {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
			}
		} else {
			raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
		}
	}

    /* recache snmp data */
	run_data_query($device_id, $data_query_id);
}

/**
 * api_device_dq_remove - removes a device->data query mapping
 *
 * @param  (int) The id of the device which contains the mapping
 * @param  (int) The id of the data query to remove the mapping for
 *
 * @return (void)
 */
function api_device_dq_remove($device_id, $data_query_id) {
	global $config;

	db_execute_prepared('DELETE FROM host_snmp_cache
		WHERE snmp_query_id = ?
		AND host_id = ?',
		array($data_query_id, $device_id));

	db_execute_prepared('DELETE FROM host_snmp_query
		WHERE snmp_query_id = ?
		AND host_id = ?',
		array($data_query_id, $device_id));

	db_execute_prepared('DELETE FROM poller_reindex
		WHERE data_query_id = ?
		AND host_id = ?',
		array($data_query_id, $device_id));

	$poller_id = db_fetch_cell_prepared('SELECT poller_id FROM host WHERE id = ?', array($device_id));

	if ($poller_id > 1) {
		if (remote_poller_up($poller_id)) {
			if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
				db_execute_prepared('DELETE FROM host_snmp_cache
					WHERE snmp_query_id = ?
					AND host_id = ?',
					array($data_query_id, $device_id), true, $rcnn_id);

				db_execute_prepared('DELETE FROM host_snmp_query
					WHERE snmp_query_id = ?
					AND host_id = ?',
					array($data_query_id, $device_id), true, $rcnn_id);

				db_execute_prepared('DELETE FROM poller_reindex
					WHERE data_query_id = ?
					AND host_id = ?',
					array($data_query_id, $device_id), true, $rcnn_id);
			} else {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
			}
		} else {
			raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
		}
	}
}

/**
 * api_device_dq_change - changes a device->data query mapping
 *
 * @param  (int) The id of the device which contains the mapping
 * @param  (int) The id of the data query to remove the mapping for
 * @param  (int) The reindex method to use when changing the data query
 *
 * @return (void)
 */
function api_device_dq_change($device_id, $data_query_id, $reindex_method) {
	global $config;

	db_execute_prepared('INSERT INTO host_snmp_query
		(host_id, snmp_query_id, reindex_method)
		VALUES (?, ?, ?)
		ON DUPLICATE KEY UPDATE reindex_method=VALUES(reindex_method)',
		array($device_id, $data_query_id, $reindex_method));

	db_execute_prepared('DELETE FROM poller_reindex
		WHERE data_query_id = ?
		AND host_id = ?', array($data_query_id, $device_id));

	$poller_id = db_fetch_cell_prepared('SELECT poller_id FROM host WHERE id = ?', array($device_id));

	if ($poller_id > 1) {
		if (remote_poller_up($poller_id)) {
			if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
				db_execute_prepared('INSERT INTO host_snmp_query
					(host_id, snmp_query_id, reindex_method)
					VALUES (?, ?, ?)
					ON DUPLICATE KEY UPDATE reindex_method=VALUES(reindex_method)',
					array($device_id, $data_query_id, $reindex_method), true, $rcnn_id);

				db_execute_prepared('DELETE FROM poller_reindex
					WHERE data_query_id = ?
					AND host_id = ?', array($data_query_id, $device_id), true, $rcnn_id);
			} else {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
			}
		} else {
			raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
		}
	}

	/* finally rerun the data query */
	run_data_query($device_id, $data_query_id);
}

/**
 * api_device_gt_remove - removes a device->graph template mapping
 *
 * @param  (int) The id of the device which contains the mapping
 * @param  (int) The id of the graph template to remove the mapping for
 *
 * @return (void)
 */
function api_device_gt_remove($device_id, $graph_template_id) {
	global $config;

	db_execute_prepared('DELETE FROM host_graph
		WHERE graph_template_id = ?
		AND host_id = ?',
		array($graph_template_id, $device_id));

	$poller_id = db_fetch_cell_prepared('SELECT poller_id FROM host WHERE id = ?', array($device_id));

	if ($poller_id > 1) {
		if (remote_poller_up($poller_id)) {
			if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
				db_execute_prepared('DELETE FROM host_graph
					WHERE graph_template_id = ?
					AND host_id = ?',
					array($graph_template_id, $device_id), true, $rcnn_id);
			} else {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
			}
		} else {
			raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
		}
	}
}

/**
 * api_device_replicate_out - Replace device settings to the remote data collectors
 *
 * @param  (int) The id of the device
 * @param  (int) The poller id of the device.  If null, we determine it
 *
 * @return (void)
 */
function api_device_replicate_out($device_id, $poller_id = 1) {
	global $config;

	$rcnn_id = false;

	if ($poller_id > 1) {
		if (remote_poller_up($poller_id)) {
			$rcnn_id = poller_connect_to_remote($poller_id);
		}
	}

	if ($rcnn_id === false) {
		return false;
	}

	// Update poller id where applicable
	db_execute_prepared('UPDATE host
		SET poller_id = ?
		WHERE id = ?
		AND deleted = ""',
		array($poller_id, $device_id));

	db_execute_prepared('UPDATE poller_item
		SET poller_id = ?
		WHERE host_id = ?',
		array($poller_id, $device_id));

	// Start Push Replication
	$data = db_fetch_assoc_prepared('SELECT hsq.*
		FROM host_snmp_query AS hsq
		INNER JOIN host AS h
		ON h.id=hsq.host_id
		WHERE h.id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'host_snmp_query', $poller_id);
	}

	$data = db_fetch_assoc_prepared('SELECT pi.*
		FROM poller_item AS pi
		WHERE pi.host_id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'poller_item', $poller_id);
	}

	$data = db_fetch_assoc_prepared('SELECT h.*
		FROM host AS h
		WHERE h.id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'host', $poller_id);
	}

	$data = db_fetch_assoc_prepared('SELECT hsc.*
		FROM host_snmp_cache AS hsc
		INNER JOIN host AS h
		ON h.id=hsc.host_id
		WHERE h.id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'host_snmp_cache', $poller_id);
	}

	$data = db_fetch_assoc_prepared('SELECT pri.*
		FROM poller_reindex AS pri
		INNER JOIN host AS h
		ON h.id=pri.host_id
		WHERE h.id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'poller_reindex', $poller_id);
	}

	$data = db_fetch_assoc_prepared('SELECT dl.*
		FROM data_local AS dl
		INNER JOIN host AS h
		ON h.id=dl.host_id
		WHERE h.id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'data_local', $poller_id);
	}

	$data = db_fetch_assoc_prepared('SELECT gl.*
		FROM graph_local AS gl
		INNER JOIN host AS h
		ON h.id=gl.host_id
		WHERE h.id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'graph_local', $poller_id);
	}

	$data = db_fetch_assoc_prepared('SELECT dtd.*
		FROM data_template_data AS dtd
		INNER JOIN data_local AS dl
		ON dtd.local_data_id=dl.id
		INNER JOIN host AS h
		ON h.id=dl.host_id
		WHERE h.id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'data_template_data', $poller_id);
	}

	$data = db_fetch_assoc_prepared('SELECT dtr.*
		FROM data_template_rrd AS dtr
		INNER JOIN data_local AS dl
		ON dtr.local_data_id=dl.id
		INNER JOIN host AS h
		ON h.id=dl.host_id
		WHERE h.id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'data_template_rrd', $poller_id);
	}

	$data = db_fetch_assoc_prepared('SELECT gti.*
		FROM graph_templates_item AS gti
		INNER JOIN graph_local AS gl
		ON gti.local_graph_id=gl.id
		INNER JOIN host AS h
		ON h.id=gl.host_id
		WHERE h.id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'graph_templates_item', $poller_id);
	}

	$data = db_fetch_assoc_prepared('SELECT did.*
		FROM data_input_data AS did
		INNER JOIN data_template_data AS dtd
		ON did.data_template_data_id=dtd.id
		INNER JOIN data_local AS dl
		ON dl.id=dtd.local_data_id
		INNER JOIN host AS h
		ON h.id=dl.host_id
		WHERE h.id = ?',
		array($device_id));

	if ($poller_id > 1) {
		replicate_table_to_poller($rcnn_id, $data, 'data_input_data', $poller_id);
	}

	api_plugin_hook_function('replicate_out', $poller_id);

	$stats = db_fetch_row_prepared('SELECT
		SUM(CASE WHEN action=0 THEN 1 ELSE 0 END) AS snmp,
		SUM(CASE WHEN action=1 THEN 1 ELSE 0 END) AS script,
		SUM(CASE WHEN action=2 THEN 1 ELSE 0 END) AS server
		FROM poller_item
		WHERE poller_id = ?',
		array($poller_id));

	if (cacti_sizeof($stats)) {
		db_execute_prepared('UPDATE poller
			SET snmp = ?, script = ?, server = ?
			WHERE id = ?',
			array($stats['snmp'], $stats['script'], $stats['server'], $poller_id));
	}

	return true;
}

/**
 * api_device_save - Save a device and update the poller cache for the device is required.
 *   The function will determine if the poller cache needs updating by reviewing the changed
 *   settings.  If no settings changed that require an update of the poller cache, the
 *   device level settings will simply be updated, otherwise the poller cache will be refreshed
 *   for the device.
 *
 * @param  (int)    The id of the device
 * @param  (int)    The device template for the device
 * @param  (string) A device description
 * @param  (string) The devices hostname
 * @param  (string) The devices snmp community in the case of v1/v2c
 * @param  (int)    The devices snmp_version 1|2|3
 * @param  (string) The devices snmp username in the case of v3
 * @param  (string) The devices snmp auth password in the case of v3
 * @param  (int)    The devices snmp port if in use.  Default to 161
 * @param  (int)    The devices snmp timeout in milliseconds
 * @param  (bool)   True of 'on' if the device is disabled
 * @param  (int)    The devices availability/reachability type
 * @param  (int)    The devices availability/reachability test ping method
 * @param  (int)    The devices ping port to be used in the case of TCP or UDP
 * @param  (int)    The ping timeout in milliseconds
 * @param  (int)    The number of times to retry the ping of the device
 * @param  (strong) Operator notes for the device.  Can be used by plugins
 * @param  (int)    The snmp authentication protocol
 * @param  (string) The snmp privilege protocol passphrase
 * @param  (int)    The snmp privilege protocol to use
 * @param  (string) The snmp context to use to reach the device
 * @param  (string) The snmp engine id if required to reach the devices
 * @param  (int)    The maximum number of OID's to gather in a single snmpget request
 * @param  (int)    When using spine, the number of threads to use to collect data source information
 * @param  (int)    The id of the data collector.  The default is 1
 * @param  (int)    The id of the site that the device belongs to
 * @param  (string) External ID's to be used by plugins and other cmdb like functions
 * @param  (string) A location attribute such as rack and enclosure, closet location within a site.
 * @param  (int)    A variable that tells cacti to find detect the optimal bulk walk size for the device
 *
 * @return (int)    The id of the device
 */
function api_device_save($id, $device_template_id, $description, $hostname, $snmp_community, $snmp_version,
	$snmp_username, $snmp_password, $snmp_port, $snmp_timeout, $disabled,
	$availability_method, $ping_method, $ping_port, $ping_timeout, $ping_retries,
	$notes, $snmp_auth_protocol, $snmp_priv_passphrase, $snmp_priv_protocol, $snmp_context, $snmp_engine_id,
	$max_oids = 5, $device_threads = 1, $poller_id = 1, $site_id = 1, $external_id = '', $location = '', $bulk_walk_size = -1) {
	global $config;

	include_once($config['base_path'] . '/lib/utility.php');
	include_once($config['base_path'] . '/lib/variables.php');
	include_once($config['base_path'] . '/lib/data_query.php');

	if ($id > 0) {
		$previous_poller = db_fetch_cell_prepared('SELECT poller_id
			FROM host
			WHERE id = ?',
			array($id));
	} else {
		$previous_poller = 0;
	}

	/* fetch some cache variables */
	if (empty($id)) {
		$_host_template_id = 0;
	} else {
		$_host_template_id = db_fetch_cell_prepared('SELECT host_template_id
			FROM host
			WHERE id = ?',
			array($id));
	}

	$raised = false;

	$save['id']                   = form_input_validate($id, 'id', '^[0-9]+$', false, 3);
	$save['host_template_id']     = form_input_validate($device_template_id, 'host_template_id', '^[0-9]+$', true, 3);

	$save['poller_id']            = form_input_validate($poller_id, 'poller_id', '^[0-9]+$', true, 3);
	$save['site_id']              = form_input_validate($site_id, 'site_id', '^[0-9]+$', true, 3);
	$save['external_id']          = form_input_validate($external_id, 'external_id', '', true, 3);

	$save['description']          = form_input_validate($description, 'description', '', false, 3);
	$save['hostname']             = form_input_validate(trim($hostname), 'hostname', '', false, 3);
	$save['notes']                = form_input_validate($notes, 'notes', '', true, 3);
	$save['location']             = form_input_validate($location, 'location', '', true, 3);

	$save['snmp_version']         = form_input_validate($snmp_version, 'snmp_version', '', true, 3);
	$save['snmp_community']       = form_input_validate($snmp_community, 'snmp_community', '', true, 3);

	if ($save['snmp_version'] == 3) {
		$save['snmp_username']        = form_input_validate($snmp_username, 'snmp_username', '', true, 3);
		$save['snmp_password']        = form_input_validate($snmp_password, 'snmp_password', '', true, 3);
		$save['snmp_auth_protocol']   = form_input_validate($snmp_auth_protocol, 'snmp_auth_protocol', "^\[None\]|MD5|SHA|SHA224|SHA256|SHA392|SHA512$", true, 3);
		$save['snmp_priv_passphrase'] = form_input_validate($snmp_priv_passphrase, 'snmp_priv_passphrase', '', true, 3);
		$save['snmp_priv_protocol']   = form_input_validate($snmp_priv_protocol, 'snmp_priv_protocol', "^\[None\]|DES|AES128|AES192|AES256$", true, 3);
		$save['snmp_context']         = form_input_validate($snmp_context, 'snmp_context', '', true, 3);
		$save['snmp_engine_id']       = form_input_validate($snmp_engine_id, 'snmp_engine_id', '', true, 3);

		if (strlen($save['snmp_password']) < 8 && $snmp_auth_protocol != '[None]') {
			raise_message(32);
			$_SESSION['sess_error_fields']['snmp_password'] = 'snmp_password';
		}
	} else {
		$save['snmp_username']        = '';
		$save['snmp_password']        = '';
		$save['snmp_auth_protocol']   = '';
		$save['snmp_priv_passphrase'] = '';
		$save['snmp_priv_protocol']   = '';
		$save['snmp_context']         = '';
		$save['snmp_engine_id']       = '';
	}

	$save['snmp_port']            = form_input_validate($snmp_port, 'snmp_port', '^[0-9]+$', false, 3);
	$save['snmp_timeout']         = form_input_validate($snmp_timeout, 'snmp_timeout', '^[0-9]+$', false, 3);

	/* disabled = 'on'   => regexp '^on$'
	 * not disabled = '' => no regexp, but allow nulls */
	$save['disabled']             = form_input_validate($disabled, 'disabled', '^on$', true, 3);

	if ($save['disabled'] == 'on') {
		if ($save['id'] > 0) {
			api_device_disable_devices(array($save['id']));
		}
	}

	$quick_save = api_device_quick_save($save);

	$save['availability_method']  = form_input_validate($availability_method, 'availability_method', '^[0-9]+$', false, 3);
	$save['ping_method']          = form_input_validate($ping_method, 'ping_method', '^[0-9]+$', false, 3);
	$save['ping_port']            = form_input_validate($ping_port, 'ping_port', '^[0-9]+$', true, 3);
	$save['ping_timeout']         = form_input_validate($ping_timeout, 'ping_timeout', '^[0-9]+$', true, 3);
	$save['ping_retries']         = form_input_validate($ping_retries, 'ping_retries', '^[0-9]+$', true, 3);
	$save['max_oids']             = form_input_validate($max_oids, 'max_oids', '^[0-9]+$', false, 3);
	$save['bulk_walk_size']       = form_input_validate($bulk_walk_size, 'bulk_walk_size', '^[-0-9]+$', false, 3);
	$save['device_threads']       = form_input_validate($device_threads, 'device_threads', '^[0-9]+$', true, 3);

	$device_id = 0;

	if (!is_error_message()) {
		$save = api_plugin_hook_function('api_device_save', $save);

		$device_id = sql_save($save, 'host');

		if ($device_id) {
			if ($previous_poller > 1 && $poller_id != $previous_poller) {
				if (remote_poller_up($previous_poller)) {
					api_device_purge_from_remote($device_id, $previous_poller);
				} else {
					raise_message('poller_down_' . $save['id'], __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $previous_poller), MESSAGE_LEVEL_WARN);
					$raised = true;
				}
			}

			raise_message(1);

			if ($poller_id > 1) {
				if (remote_poller_up($poller_id)) {
					if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
						$save['id'] = $device_id;
						sql_save($save, 'host', 'id', true, $rcnn_id);
					} elseif (!$raised) {
						raise_message('poller_down_' . $save['id'], __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
						$raised = true;
					}
				} elseif (!$raised) {
					raise_message('poller_down_' . $save['id'], __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					$raised = true;
				}
			}

			/* clear the host session data for poller cache repopulation */
			if (isset($_SESSION['sess_host_cache_array'][$device_id])) {
				unset($_SESSION['sess_host_cache_array'][$device_id]);
			}

			/* change reindex method for 'None' for non-snmp devices */
			if ($save['snmp_version'] == 0) {
				db_execute_prepared('UPDATE host_snmp_query
					SET reindex_method = 0
					WHERE host_id = ?',
					array($device_id));

				db_execute_prepared('DELETE FROM poller_reindex
					WHERE host_id = ?',
					array($device_id));

				if ($poller_id > 1) {
					if (remote_poller_up($poller_id)) {
						if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
							db_execute_prepared('UPDATE host_snmp_query
								SET reindex_method = 0
								WHERE host_id = ?',
								array($device_id), true, $rcnn_id);

							db_execute_prepared('DELETE FROM poller_reindex
								WHERE host_id = ?',
								array($device_id), true, $rcnn_id);
						} elseif (!$raised) {
							raise_message('poller_down_' . $save['id'], __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
						}
					} elseif (!$raised) {
						raise_message('poller_down_' . $save['id'], __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					}
				}
			}

			api_device_cache_crc_update($save['poller_id']);

			/* push out relevant fields to data sources using this host */
			if (!$quick_save) {
				push_out_host($device_id, 0);
			}

			/* the host substitution cache is now stale; purge it */
			kill_session_var('sess_host_cache_array');

			/* update title cache for graph and data source */
			update_data_source_title_cache_from_host($device_id);
			update_graph_title_cache_from_host($device_id);

			if (empty($id)) {
				/**
				 * Save the last time a device/site was created/updated
				 * for Caching.
				 */
				set_config_option('time_last_change_device', time());
				set_config_option('time_last_change_site_device', time());
			}
		} else {
			raise_message(2);
		}

		/* if the user changes the host template, add each snmp query associated with it */
		if ($device_template_id > 0 && $device_template_id != $_host_template_id) {
			api_device_update_host_template($device_id, $device_template_id);
		}
	}

	if ($device_id > 0) {
		if (read_config_option('extended_paths') == 'on'){
			$host_dir = $config['rra_path'] . '/' . $device_id;
			if (!is_dir($host_dir)){
				if (is_writable($config['rra_path'])) {
					if (mkdir($host_dir, 0775)) {
						if ($config['cacti_server_os'] != 'win32') {
							$owner_id      = fileowner($config['rra_path']);
							$group_id      = filegroup($config['rra_path']);

							if ((chown($host_dir, $owner_id)) &&
								(chgrp($host_dir, $group_id))) {
								/* permissions set ok */
							} else {
								cacti_log("ERROR: Unable to set directory permissions for '" . $host_dir . "'", false);
							}
						}
					} else {
						cacti_log("ERROR: Unable to create directory '" . $host_dir . "'", false);
					}
				} else {
					cacti_log("ERROR: Unable to create directory due to missing write permissions '" . $host_dir . "'", false);
				}
			}
		}

		# now that we have the id of the new host, we may plugin postprocessing code
		$save['id'] = $device_id;

		snmpagent_api_device_new($save);

		automation_execute_device_create_tree($device_id);

		api_plugin_hook_function('api_device_new', $save);
	}

	return $device_id;
}

/**
 * api_device_quick_save - checks if the poller cache needs to be
 *   rebuilt as a part of a device save.
 *
 * @param  (array) The devices "save" structure for the device
 *
 * @return (bool)  If the device can be quickly saved, or will the device have to be pushed out
 */
function api_device_quick_save(&$save) {
	if ($save['id'] > 0) {
		$device = db_fetch_row_prepared('SELECT *
			FROM host
			WHERE id = ?',
			array($save['id']));

		$compare = array(
			'poller_id',
			'disabled',
			'hostname',
			'snmp_community',
			'snmp_version',
			'snmp_username',
			'snmp_password',
			'snmp_auth_protocol',
			'snmp_priv_passphrase',
			'snmp_priv_protocol',
			'snmp_context',
			'snmp_engine_id',
			'snmp_port',
			'snmp_timeout'
		);

		foreach($compare as $c) {
			if ($save[$c] != $device[$c]) {
				return false;
			}
		}

		return true;
	} else {
		return false;
	}
}

/**
 * api_device_update_host_template - changes the host template of a host
 *
 * @param  (int)  The id of the device which contains the mapping
 * @param  (int)  The id of the device template alter the device to
 *
 * @return (void)
 */
function api_device_update_host_template($device_id, $device_template_id) {
	global $config;

	static $raised = array();

	db_execute_prepared('UPDATE host
		SET host_template_id = ?
		WHERE id = ?
		AND deleted = ""',
		array($device_template_id, $device_id));

	$poller_id = db_fetch_cell_prepared('SELECT poller_id FROM host WHERE id = ?', array($device_id));

	if ($poller_id > 1) {
		if (remote_poller_up($poller_id)) {
			if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
				db_execute_prepared('UPDATE host
					SET host_template_id = ?
					WHERE id = ?
					AND deleted = ""',
					array($device_template_id, $device_id), true, $rcnn_id);
			} elseif (!isset($raised[$poller_id])) {
				raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
				$raised[$poller_id] = true;
			}
		} elseif (!isset($raised[$poller_id])) {
			raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
			$raised[$poller_id] = true;
		}
	}

	/* add all new snmp queries assigned to the device template */
	$snmp_queries = db_fetch_assoc_prepared('SELECT snmp_query_id
		FROM host_template_snmp_query AS htsq
		WHERE host_template_id = ?
		AND htsq.snmp_query_id NOT IN (SELECT snmp_query_id FROM host_snmp_cache WHERE host_id = ?)',
		array($device_template_id, $device_id));

	if (cacti_sizeof($snmp_queries)) {
		foreach ($snmp_queries as $snmp_query) {
			db_execute_prepared('REPLACE INTO host_snmp_query
				(host_id, snmp_query_id, reindex_method)
				VALUES (?, ?, ?)',
				array($device_id, $snmp_query['snmp_query_id'], read_config_option('reindex_method')));

			if ($poller_id > 1) {
				if (remote_poller_up($poller_id)) {
					if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
						db_execute_prepared('REPLACE INTO host_snmp_query
							(host_id, snmp_query_id, reindex_method)
							VALUES (?, ?, ?)',
							array($device_id, $snmp_query['snmp_query_id'], read_config_option('reindex_method')), true, $rcnn_id);
					} elseif ($raised[$poller_id]) {
						raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
						$raised[$poller_id] = true;
					}
				} elseif (!isset($raised[$poller_id])) {
					raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					$raised[$poller_id] = true;
				}
			}

			/* recache snmp data */
			run_data_query($device_id, $snmp_query['snmp_query_id']);
		}
	}

	/* add all graph templates assigned to the device template */
	$graph_templates = db_fetch_assoc_prepared('SELECT graph_template_id
		FROM host_template_graph AS hg
		WHERE host_template_id = ?
		AND hg.graph_template_id NOT IN (SELECT graph_template_id FROM host_graph WHERE host_id = ?)',
		array($device_template_id, $device_id));

	if (cacti_sizeof($graph_templates)) {
		foreach ($graph_templates as $graph_template) {
			db_execute_prepared('REPLACE INTO host_graph
				(host_id, graph_template_id)
				VALUES (?, ?)',
				array($device_id, $graph_template['graph_template_id']));

			if ($poller_id > 1) {
				if (remote_poller_up($poller_id)) {
					if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
						db_execute_prepared('REPLACE INTO host_graph
							(host_id, graph_template_id)
							VALUES (?, ?)',
							array($device_id, $graph_template['graph_template_id']), true, $rcnn_id);
					} elseif (!isset($raised[$poller_id])) {
						raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
						$raised[$poller_id] = true;
					}
				} elseif (!isset($raised[$poller_id])) {
					raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					$raised[$poller_id] = true;
				}
			}

			automation_hook_graph_template($device_id, $graph_template['graph_template_id']);

			api_plugin_hook_function('add_graph_template_to_host',
				array('host_id' => $device_id, 'graph_template_id' => $graph_template['graph_template_id']));
		}
	}

	/* remove unused graph templates not assigned to the device template */
	$unused_graph_templates = db_fetch_assoc_prepared('SELECT DISTINCT
		hg.graph_template_id AS id, gt.name, result.gtid
		FROM host_graph AS hg
		LEFT JOIN graph_templates AS gt
		ON gt.id=hg.graph_template_id
		LEFT JOIN (
			SELECT DISTINCT graph_template_id AS gtid
			FROM graph_local AS gl
			WHERE gl.host_id = ?
			AND snmp_query_id = 0
			UNION
			SELECT DISTINCT graph_template_id AS gtid
			FROM host_template_graph AS htg
			WHERE htg.host_template_id = ?
		) AS result
		ON hg.graph_template_id=result.gtid
		WHERE gt.id NOT IN (SELECT graph_template_id FROM snmp_query_graph)
	    HAVING gtid IS NULL
	    ORDER BY gt.name',
	    array($device_id, $device_template_id)
	);

	if (cacti_sizeof($unused_graph_templates)) {
		foreach ($unused_graph_templates as $unused_graph_template) {
			db_execute_prepared('DELETE
				FROM host_graph
				WHERE host_id = ?
				AND graph_template_id = ?',
				array($device_id, $unused_graph_template['id']));

			if ($poller_id > 1) {
				if (remote_poller_up($poller_id)) {
					if (($rcnn_id = poller_push_to_remote_db_connect($device_id)) !== false) {
						db_execute_prepared('DELETE
							FROM host_graph
							WHERE host_id = ?
							AND graph_template_id = ?',
							array($device_id, $unused_graph_template['id']), true, $rcnn_id);
					} elseif (!isset($raised[$poller_id])) {
						raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
						$raised[$poller_id] = true;
					}
				} elseif (!$isset($raised[$poller_id])) {
					raise_message('poller_down_' . $poller_id, __('Remote Poller %s is Down, you will need to perform a FullSync once it is up again', $poller_id), MESSAGE_LEVEL_WARN);
					$raised[$poller_id] = true;
				}
			}
		}
	}

	$data = array('device_id' => $device_id, 'device_template_id' => $device_template_id);

	api_plugin_hook_function('device_template_change', $data);
}

/**
 * api_device_change_field_match - Checks the global $device_change_fileds array
 *   against the field name and returns true or false if it matches the rule
 *
 * This function can be used by plugins to allow the modification of additional
 * device fields from the change device rule.
 *
 * @param  string      The field name to check
 *
 * @return bool        True or false if it matches one of the rules
 */
function api_device_change_field_match($field_name) {
	global $device_change_fields;

	$matches = false;

	foreach($device_change_fields as $rule_type => $rules) {
		foreach($rules as $field_rule) {
			if ($rule_type == 'preg_field') {
				if (preg_match($field_rule, $field_name)) {
					$matches = true;
					break 2;
				}
			} elseif ($rule_type == 'match_field') {
				if ($field_rule == $field_name) {
					$matches = true;
					break 2;
				}
			}
		}
	}

	return $matches;
}

/**
 * api_device_template_sync_template - updates the device template mapping for all devices mapped to a template
 *
 * @param  (int)       The device template to synchronize
 * @param  (int|array) An array of device_ids or a string with a single device_id
 * @param  (bool)      Also update mapping of down devices
 *
 * @return (void)
 */
function api_device_template_sync_template($device_template, $device_ids = '', $down_devices = false) {
	if ($down_devices == true) {
		$status_where = '';
	} else {
		$status_where = ' AND status IN(3,2)';
	}

	if (is_array($device_ids)) {
		$device_ids = implode(',', $device_ids);
	}

	if ($device_ids != '') {
		$status_where .= ' AND host.id IN(' . $device_ids . ')';
	}

	$devices = array_rekey(
		db_fetch_assoc_prepared('SELECT id
			FROM host
			WHERE host_template_id = ?' .
			$status_where,
			array($device_template)),
		'id', 'id'
	);

	if (cacti_sizeof($devices)) {
		foreach($devices as $device) {
			api_device_update_host_template($device, $device_template);
		}
	}
}

/**
 * api_device_ping_device - given a device id and optional indicator of where the ping request
 *   came from, ping the device.  The ping results are echoed to standard output for the browser
 *
 * @param (int)  The device id in question
 * @param (bool) Whether the source of the ping request is coming from a remote data collector.
 *
 * @return (void)
 */
function api_device_ping_device($device_id, $from_remote = false) {
	global $config, $snmp_error;

	if (empty($device_id)) {
		print __('ERROR: Device ID is Blank');
		return;
	}

	$host = db_fetch_row_prepared('SELECT *
		FROM host
		WHERE id = ?',
		array($device_id));

	if (!cacti_sizeof($host)) {
		if ($from_remote) {
			print __('ERROR: Device[' . $device_id . '] not found.  Please perform Full Sync!');
		} else {
			print __('ERROR: Device[' . $device_id . '] not found.  Please check database for errors.');
		}
		return;
	}

	$am   = $host['availability_method'];
	$anym = false;

	if ($config['poller_id'] != $host['poller_id'] && $from_remote == false) {
		$hostname = db_fetch_cell_prepared('SELECT hostname
			FROM poller
			WHERE id = ?',
			array($host['poller_id']));

		$fgc_contextoption = get_default_contextoption();
		$fgc_context       = stream_context_create($fgc_contextoption);
		$results           = @file_get_contents(get_url_type() .'://' . $hostname . $config['url_path'] . 'remote_agent.php?action=ping&host_id=' . $host['id'], false, $fgc_context);

		if ($results != '') {
			print $results;
		} else {
			print __('ERROR: Failed to connect to remote collector.');
		}

		return;
	}

	if ($host['disabled'] == 'on') {
		print __('Device is Disabled') . '<br>';
		print __('Device Availability Check Bypassed') . '<br>';
	} elseif ($am == AVAIL_SNMP || $am == AVAIL_SNMP_GET_NEXT ||
		$am == AVAIL_SNMP_GET_SYSDESC || $am == AVAIL_SNMP_AND_PING ||
		$am == AVAIL_SNMP_OR_PING) {

		$anym = true;

		print __('SNMP Information') . '<br>';
		print "<span class='monoSpace'>";

		if (($host['snmp_community'] == '' && $host['snmp_username'] == '') || $host['snmp_version'] == 0) {
			print "<span style='color: #ab3f1e; font-weight: bold;'>" . __('SNMP not in use') . '</span>';
		} else {
			$snmp_error = '';
			$session = cacti_snmp_session($host['hostname'], $host['snmp_community'], $host['snmp_version'],
 				$host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'],
 				$host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_engine_id'], $host['snmp_port'],
				$host['snmp_timeout'], $host['ping_retries'], $host['max_oids']);

			if ($session === false || $snmp_error != '') {
				print "<span class='hostDown'>" . __('Session') . ' ' . __('SNMP error');
				if ($snmp_error != '') {
					print " - $snmp_error";
				}
				print '</span>';
			} else {
				$snmp_system = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.1.0');
				if ($snmp_system === false || $snmp_system == 'U' || $snmp_error != '') {
					print "<span class='hostDown'>" . __('System') . ' ' . __('SNMP error');
					if ($snmp_error != '') {
						print " - $snmp_error";
					}
					print '</span>';
				} else {

					/* modify for some system descriptions */
					/* 0000937: System output in host.php poor for Alcatel */
					if (substr_count($snmp_system, '00:')) {
						$snmp_system = str_replace('00:', '', $snmp_system);
						$snmp_system = str_replace(':', ' ', $snmp_system);
					}

					if ($snmp_system == '') {
						print "<span class='hostDown'>" . __('Host') . ' ' .  __('SNMP error');
						if ($snmp_error != '') {
							print " - $snmp_error";
						}
						'</span>';
					} else {
						$snmp_uptime = cacti_snmp_session_get($session, '.1.3.6.1.6.3.10.2.1.3.0');

						if (!empty($snmp_uptime)) {
							$snmp_uptime *= 100;
						} else {
							$snmp_uptime = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.3.0');
						}

						$snmp_hostname   = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.5.0');
						$snmp_location   = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.6.0');
						$snmp_contact    = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.4.0');

						print '<strong>' . __('System:') . '</strong> ' . html_split_string($snmp_system) . '<br>';
						$snmp_uptime_ticks = intval($snmp_uptime);
						$days      = intval($snmp_uptime_ticks / (60*60*24*100));
						$remainder = $snmp_uptime_ticks % (60*60*24*100);
						$hours     = intval($remainder / (60*60*100));
						$remainder = $remainder % (60*60*100);
						$minutes   = intval($remainder / (60*100));
						print '<strong>' . __('Uptime:') . "</strong> $snmp_uptime";
						print '&nbsp;(' . $days . __('days') . ', ' . $hours . __('hours') . ', ' . $minutes . __('minutes') . ')<br>';
						print '<strong>' . __('Hostname:') . "</strong> $snmp_hostname<br>";
						print '<strong>' . __('Location:') . "</strong> $snmp_location<br>";
						print '<strong>' . __('Contact:') . "</strong> $snmp_contact<br>";
					}

				}

				$session->close();
			}
		}
		print '</span><br>';
	}

	if ($am == AVAIL_PING || $am == AVAIL_SNMP_AND_PING || $am == AVAIL_SNMP_OR_PING) {
		$anym = true;

		/* create new ping socket for host pinging */
		$ping = new Net_Ping;

		$ping->host = $host;
		$ping->port = $host['ping_port'];

		/* perform the appropriate ping check of the host */
		$ping_results = $ping->ping(AVAIL_PING, $host['ping_method'], $host['ping_timeout'], $host['ping_retries']);

		if ($ping_results == true) {
			$host_down = false;
			$class     = 'hostUp';
		} else {
			$host_down = true;
			$class     = 'hostDown';
		}

		print __('Ping Results') . "<br>\n";
		print "<span class='" . $class . "'>" . $ping->ping_response . "</span>\n";
	}

	if ($anym == false && $host['disabled'] != 'on') {
		print __('No Ping or SNMP Availability Check in Use') . "<br><br>\n";
	}
}

/**
 * api_duplicate_device_template - given a device_template_id, and a title, duplicate it.
 *
 * @param (int)    The Device Template id to duplicate
 * @param (string) The name of the new Device Template
 *
 * @return (void)
 */
function api_duplicate_device_template($_host_template_id, $host_template_title) {
	global $fields_host_template_edit;

	$host_template              = db_fetch_row_prepared('SELECT * FROM host_template WHERE id = ?', array($_host_template_id));
	$host_template_graphs       = db_fetch_assoc_prepared('SELECT * FROM host_template_graph WHERE host_template_id = ?', array($_host_template_id));
	$host_template_data_queries = db_fetch_assoc_prepared('SELECT * FROM host_template_snmp_query WHERE host_template_id = ?', array($_host_template_id));

	if (cacti_sizeof($host_template)) {
		/* substitute the title variable */
		$host_template['name'] = str_replace('<template_title>', $host_template['name'], $host_template_title);

		/* create new entry: host_template */
		$save['id']   = 0;
		$save['hash'] = get_hash_host_template(0);

		foreach ($fields_host_template_edit as $field => $array) {
			if (!preg_match('/^hidden/', $array['method'])) {
				$save[$field] = $host_template[$field];
			}
		}

		$host_template_id = sql_save($save, 'host_template');

		/* create new entry(s): host_template_graph */
		if (cacti_sizeof($host_template_graphs)) {
			foreach ($host_template_graphs as $host_template_graph) {
				db_execute_prepared('INSERT INTO host_template_graph
					(host_template_id,graph_template_id)
					VALUES (?, ?)',
					array($host_template_id, $host_template_graph['graph_template_id']));
			}
		}

		/* create new entry(s): host_template_snmp_query */
		if (cacti_sizeof($host_template_data_queries)) {
			foreach ($host_template_data_queries as $host_template_data_query) {
				db_execute_prepared('INSERT INTO host_template_snmp_query
					(host_template_id,snmp_query_id)
					VALUES (?, ?)',
					array($host_template_id, $host_template_data_query['snmp_query_id']));
			}
		}

		return $host_template_id;
	} else {
		return false;
	}
}

/**
 * api_clone_message - Displays a clone specific log
 *   message if there to CLI and the Cacti log
 *
 * @param string - The message to output
 * @param bool - Is the output for CLI or the web only
 *
 * @return null
 */
function api_clone_message($message, $force = false) {
	global $debug, $config;

	if ($debug || $force) {
		if (!$config['is_web']) {
			print trim($message) . PHP_EOL;
		}

		cacti_log($message, false, 'DTCLONE');
	}
}

/**
 * api_clone_get_unique_name - Get a unique name for
 *   a cacti object based upon the table and column
 *   name.
 *
 * @param string - The desired object name
 * @param string - The table to be checked for that name
 * @param string - The column name to check for the name
 *
 * @return string|bool - The correct name for the object, else false
 *    If more than 20 attempts are made to find a good name.
 */
function api_clone_get_unique_name($name, $table, $column = 'name') {
	$i = 0;

	while($i < 20) {
		if ($i > 0) {
			$check_name = $name . " ($i)";
		} else {
			$check_name = $name;
		}

		$exists = db_fetch_cell_prepared("SELECT $column
			FROM $table
			WHERE $column = ?",
			array($check_name));

		if ($exists == '') {
			return $check_name;
		}

		$i++;
	}

	return false;
}

/**
 * api_clone_get_unique_filename - Get a unique file name for
 *   a Cacti object based upon the file name.
 *
 * @param string - The current filename
 *
 * @return string|bool - The correct name for the object, else false
 *    If more than 20 attempts are made to find a good name.
 */
function api_clone_get_unique_filename($file_name) {
	$i = 1;

	$file_data = pathinfo($file_name);
	$file_base = $file_data['dirname'] . '/' . basename($file_data['basename'], $file_data['extension']);
	$file_ext  = $file_data['extension'];

	while($i < 20) {
		$id = substr('00' . $i, -2);
		$check_name = $file_base . "_$id" . '.' . $file_ext;

		if (!file_exists($check_name)) {
			return $check_name;
		}

		$i++;
	}

	return false;
}

/**
 * api_clone_device_template_check_for_errors - This function will validate the
 *   intput and return warnings and errors before allowing users to proceed.  This
 *   option is skipped when using the quiet option.
 *
 * @param int    - The device template id to be cloned
 * @param string - The include Graph Templates list
 * @param string - The clone Graph Templates list
 * @param string - The include Data Queries list
 * @param string - The clone Data Queries list
 * @param string - The include Data Templates list
 * @param string - The clone Data Templates list
 * @param string - The suffix for the clone operation
 * @param bool   - Should Data Query XML be cloned.  Will be updated if incorrect.
 * @param bool   - Should Data Input Method be cloned.  Will be updated if incorrect.
 *
 * @return array - An array of warning and error message to provide to the user.
 */
function api_clone_device_template_check_for_errors($device_template_id, $device_template_name, $include_gt, $clone_gt,
	$include_dq, $clone_dq, $include_dt, $clone_dt, &$suffix, &$clone_xml, &$clone_script) {

	global $config;

	$return = array(
		'warnings' => array(),
		'errors'   => array()
	);

	$device_template = db_fetch_row_prepared('SELECT *
		FROM host_template
		WHERE id = ?',
		array($device_template_id));

	/* first error check */
	if (!cacti_sizeof($device_template)) {
		$return['errors'][] = sprintf('FATAL: Device Template %s does not exist!', $device_template_id);

		return $return;
	} else {
		$objects = api_clone_device_template_get_objects($device_template['id']);
	}

	/* second error check */
	if (!cacti_sizeof($objects)) {
		$return['errors'][] = sprintf('FATAL: Device Template %s has no Objects!', $device_template_id);

		return $return;
	}

	$errors     = 0;
	$warnings   = 0;
	$include_gt = strtolower($include_gt);
	$include_dq = strtolower($include_dq);
	$include_dt = strtolower($include_dt);
	$clone_gt   = strtolower($clone_gt);
	$clone_dq   = strtolower($clone_dq);
	$clone_dt   = strtolower($clone_dt);

	printf('Cloning Criteria for Device Template are:'                                   . PHP_EOL);
	printf('---------------------------------------------------------------------------' . PHP_EOL);
	printf('NOTE: Include Graph Template: ' . ($include_gt != ''   ? $include_gt:'none') . PHP_EOL);
	printf('NOTE: Include Data Query:     ' . ($include_dq != ''   ? $include_dq:'none') . PHP_EOL);
	printf('NOTE: Include Data Template:  ' . ($include_dt != ''   ? $include_dt:'none') . PHP_EOL);
	printf('NOTE: Clone Graph Template:   ' . ($clone_gt != ''     ? $clone_gt:'none')   . PHP_EOL);
	printf('NOTE: Clone Data Query:       ' . ($clone_dq != ''     ? $clone_dq:'none')   . PHP_EOL);
	printf('NOTE: Clone Data Template:    ' . ($clone_dt != ''     ? $clone_dt:'none')   . PHP_EOL);
	printf('NOTE: Clone XML Files:        ' . ($clone_xml != ''    ? 'yes':'no')         . PHP_EOL);
	printf('NOTE: Clone Script Files:     ' . ($clone_script != '' ? 'yes':'no')         . PHP_EOL);
	printf('---------------------------------------------------------------------------' . PHP_EOL);

	if ($include_gt == 'all' && $clone_gt != '') {
		$return['errors'][] = sprintf('FATAL: Include Graph Templates can not be \'all\' when Clone Graph Templates is in use!');
		$errors++;
	}

	if ($include_dq == 'all' && $clone_dq != '') {
		$return['errors'][] = sprintf('FATAL: Include Data Queries can not be \'all\' when Clone Data Queries is in use!');
		$errors++;
	}

	if ($include_dt == 'all' && $clone_dt != '') {
		$return['errors'][] = sprintf('FATAL: Include Data Templates can not be \'all\' when Clone Data Templates is in use!');
		$errors++;
	}

	if ($include_gt != '' && $clone_gt == 'all') {
		$return['errors'][] = sprintf('FATAL: Clone Graph Templates can not be \'all\' when Include Graph Templates is in use!');
		$errors++;
	}

	if ($include_dq != '' && $clone_dq == 'all') {
		$return['errors'][] = sprintf('FATAL: Clone Data Queries can not be \'all\' when Include Data Queries is in use!');
		$errors++;
	}

	if ($include_dt != '' && $clone_dt == 'all') {
		$return['errors'][] = sprintf('FATAL: Clone Data Templates can not be \'all\' when Include Data Templates is in use!');
		$errors++;
	}

	if ($include_gt != '' && $include_gt != 'all') {
		$gts = explode(',', $include_gt);

		foreach($gts as $gt) {
			if (!is_numeric($gt) || $gt <= 0) {
				$errors++;

				$return['errors'][] = sprintf('FATAL: Graph Template to be included %s is not numeric', $gt);
			}
		}
	}

	if ($include_dq != '' && $include_dq != 'all') {
		$dqs = explode(',', $include_dq);

		foreach($dqs as $dq) {
			if (!is_numeric($dq) || $dq <= 0) {
				$errors++;

				$return['errors'][] = sprintf('FATAL: Data Query to be included %s is not numeric', $dq);
			}
		}
	}

	if ($include_dt != '' && $include_dt != 'all') {
		$dts = explode(',', $include_dt);

		foreach($dts as $dt) {
			if (!is_numeric($dt) || $dt <= 0) {
				$errors++;

				$return['errors'][] = sprintf('FATAL: Data Template to be included %s is not numeric', $dt);
			}
		}
	}

	$graph_templates = array_merge(array_keys($objects['graph_templates']), array_keys($objects['data_query_graph_templates']));
	$data_templates  = array_merge(array_keys($objects['data_templates']), array_keys($objects['data_query_data_templates']));
	$data_queries    = array_keys($objects['data_queries']);

	if ($include_gt != '' && $include_gt != 'all') {
		$gti = explode(',', $include_gt);

		foreach($gts as $gt) {
			if (array_search($gt, $graph_templates, true) === false) {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Graph Template to be included %s does not exist in Device Template', $gt);
			}
		}
	}

	if ($clone_gt != '' && $clone_gt != 'all') {
		$gtc = explode(',', $clone_gt);

		foreach($gtc as $gt) {
			if (array_search($gt, $graph_templates, true) === false) {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Graph Template to be cloned %s does not exist in Device Template', $gt);
			}
		}
	}

	if ($include_dq != '' && $include_dq != 'all') {
		$dqi = explode(',', $include_dq);

		foreach($dqi as $dq) {
			if (array_search($dq, $data_queries, true) === false) {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Data Query to be included %s does not exist in Device Template', $dq);
			}
		}
	}

	if ($clone_dq != '' && $clone_dq != 'all') {
		$clone_dq = true;

		$dqc = explode(',', $clone_dq);

		foreach($dqc as $dq) {
			if (!is_numeric($dq) && $dq > 0) {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Data Query to be cloned %s is not numeric', $dq);
			} elseif (array_search($dq, $data_queries, true) === false) {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Data Query to be cloned %s does not exist in Device Template', $dq);
			}
		}
	} elseif ($clone_dq == 'all') {
		$clone_dq = true;
	}

	if ($include_dt != '' && $include_dt != 'all') {
		$dti = explode(',', $include_dt);

		foreach($dti as $dt) {
			if (!is_numeric($dt) && $dt > 0) {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Data Template to be cloned %s is not numeric', $dt);
			} elseif (array_search($dt, $data_templates, true) === false) {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Data Template to be included %s does not exist in Device Template', $dt);
			}
		}
	}

	if ($clone_dt != '' && $clone_dt != 'all') {
		$clone_dt = true;

		$dtc = explode(',', $clone_dt);

		foreach($dtc as $dt) {
			if (!is_numeric($dt) && $dt > 0) {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Data Template to be cloned %s is not numeric', $dt);
			} elseif (array_search($dt, $data_templates, true) === false) {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Data Template to be cloned %s does not exist in Device Template', $dt);
			}
		}
	} elseif ($clone_dt == 'all') {
		$clone_dt = true;
	}

	/* now check for name collision xml files and scripts */
	if ($clone_xml) {
		if ($clone_dq != '') {
			foreach($objects['data_queries'] as $id => $data_query) {
				if (!is_numeric($id) && $id <= 0) {
					$errors++;
					$return['errors'][] = sprintf('FATAL: Data Query to be cloned %s is not numeric', $id);
				} elseif ($data_query['xml_path'] != '') {
					$xml_clone  = str_replace('.xml', '', $data_query['xml_path']);
					$xml_clone .= $suffix . '.xml';
					$name = $data_query['name'];
					$xml_base = trim(str_replace($config['base_path'], '', $xml_clone), '/');

					if (file_exists($xml_clone)) {
						if (!is_writable(dirname($xml_clone))) {
							$errors++;
							$return['errors'][] = sprintf('FATAL: Data Query XML Base path \'%s\' for Data Query \'%s\' already exists, and the directory is not writable!', $xml_base, $name);
						} else {
							$warnings++;
							$return['warnings'][] = sprintf('WARNING: Data Query XML Base path \'%s\' for \'%s\' already exists.', $xml_base, $name);
						}
					} else {
						$errors++;
						$return['errors'][] = sprintf('FATAL: Data Query XML Base path \'%s\' for \'%s\' not found!', $xml_base, $name);
					}
				}
			}
		}
	}

	if ($clone_script) {
		if ($clone_dq != '') {
			foreach($objects['data_queries'] as $id => $data_query) {
				if (!is_numeric($id) && $id <= 0) {
					$errors++;
					$return['errors'][] = sprintf('FATAL: Data Query to be cloned %s is not numeric', $id);
				} elseif (isset($data_query['script_path']) && $data_query['script_path'] != '') {
					$parts = explode('.', $data_query['script_path']);
					$name  = $data_query['name'];

					$xml_script = $parts[0] . $suffix . (isset($parts[1]) ? '.' . $parts[1]:'');
					$xml_base   = trim(str_replace($config['base_path'], '', $xml_script), '/');

					if (file_exists($xml_script)) {
						if (!is_writable(dirname($xml_script))) {
							$errors++;
							$return['errors'][] = sprintf('FATAL: Data Query Script Base path \'%s\' for \'%s\' already exists and the directory is not writable!', $xml_base, $name);
						} else {
							$warnings++;
							$return['warnings'][] = sprintf('WARNING: Data Query Script Base path \'%s\' for \'%s\' already exists.', $xml_base, $name);
						}
					} else {
						$errors++;
						$return['errors'][] = sprintf('FATAL: Data Query Script Base path \'%s\' for \'%s\' not found!', $xml_base, $name);
					}
				}
			}
		}

		if ($clone_dt) {
			foreach($objects['data_templates'] AS $id => $data_template) {
				if (!is_numeric($id) && $id <= 0) {
					$errors++;
					$return['errors'][] = sprintf('FATAL: Data Template to be cloned %s is not numeric', $id);
				} elseif (isset($data_templates['script_path'])) {
					$parts = explode('.', $data_template['script_path']);
					$name  = $data_template['name'];

					$script_path = $parts[0] . $suffix . (isset($parts[1]) ? '.' . $parts[1]:'');
					$script_base = trim(str_replace($config['base_path'], '', $script_path), '/');

					if (file_exists($script_path)) {
						if (!is_writeable($script_path)) {
							$errors++;
							$return['errors'][] = sprintf('FATAL: Data Template Script Base path \'%s\' for \'%s\' already exists and the directory is not writable!', $script_base, $name);
						} else {
							$warnings++;
							$return['warnings'][] = sprintf('WARNING: Data Template Script Base path \'%s\' for \'%s\' already exists.', $script_base, $name);
						}
					} else {
						$errors++;
						$return['errors'][] = sprintf('FATAL: Data Template Script Base path \'%s\' for \'%s\' not found!', $script_base, $name);
					}
				}
			}
		}
	}

	/* not issue some warnings for things to be cloned */
	if ($device_template_name == '') {
		$device_template_name = db_fetch_cell_prepared('SELECT name
			FROM host_template
			WHERE id = ?',
			array($device_template_id));

		$device_template_name .= $suffix;
	}

	$exists = db_fetch_cell_prepared('SELECT id
		FROM host_template
		WHERE name = ?',
		array($device_template_name));

	if ($exists) {
		$warnings++;
		$return['warnings'][] = sprintf('WARNING: Device Template \'%s\' already exists.', $device_template_name);
	}

	if ($clone_gt != '') {
		if ($clone_gt == 'all') {
			$gts = array_keys($objects['graph_templates']);
		} else {
			$gts = explode(',', $clone_gt);
		}

		foreach($gts as $gt_id) {
			$name = $objects['graph_templates'][$gt_id]['name'];

			$exists = db_fetch_cell_prepared('SELECT id
				FROM graph_templates
				WHERE name = ?',
				array($name . $suffix));

			if ($exists > 0) {
				$warnings++;
				$return['warnings'][] = sprintf('WARNING: Graph Template \'%s\' already exists.', $name . $suffix);
			}
		}
	}

	/* not issue some warnings for things to be cloned */
	if ($clone_xml && !$clone_dq) {
		$warnings++;
		$return['warnings'][] = sprintf('WARNING: Ignoring --clone-xml as no Data Queries were selected to be cloned.');
		$clone_xml = false;
	}

	if ($clone_script && (!$clone_dq && !$clone_dt)) {
		$warnings++;
		$return['warnings'][] = sprintf('WARNING: Ignoring --clone-script as no Data Queries or Templates were selected to be cloned.');
		$clone_script = false;
	}

	if ($clone_dq != '') {
		$ndq = array();

		if ($clone_dq == 'all') {
			$dqs = array_keys($objects['data_queries']);
		} else {
			$dqs = explode(',', $clone_dq);
		}

		if (cacti_sizeof($dqs)) {
			foreach($dqs as $dq) {
				$ndq[$dq] = $dq;
			}

			$dqs = $ndq;
		}

		foreach($dqs as $dq_id) {
			if (is_numeric($dq_id) && $dq_id > 0 && isset($objects['data_queries'][$dq_id])) {
				$name = $objects['data_queries'][$dq_id]['name'];

				$exists = db_fetch_cell_prepared('SELECT id
					FROM snmp_query
					WHERE name = ?',
					array($name . $suffix));

				if ($exists > 0) {
					$warnings++;
					$return['warnings'][] = sprintf('WARNING: Data Query \'%s\' already exists.', $name . $suffix);
				}
			} else {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Data Query ID %s to be cloned is not numeric.', $dq_id);
			}
		}

		foreach($objects['data_query_graph_templates'] as $id => $graph_template) {
			$name          = $graph_template['name'];
			$snmp_query_id = $graph_template['snmp_query_id'];

			if (isset($dqs[$snmp_query_id])) {
				$dq_name = $objects['data_queries'][$snmp_query_id]['name'];

				api_clone_message(sprintf('NOTE: Checking GT:\'%s\' and DQ Name:\'%s\'.', $name, $dq_name));

				$exists = db_fetch_cell_prepared('SELECT id
					FROM graph_templates
					WHERE name = ?',
					array($name . $suffix));

				if ($exists > 0) {
					$warnings++;
					$return['warnings'][] = sprintf('WARNING: Graph Template \'%s\' for Data Query \'%s\' already exists.', $name . $suffix, $dq_name);
				}
			}
		}
	}

	if ($clone_dt != '') {
		print 'Clone DT is "' . $clone_dt . '"' . PHP_EOL;
		if ($clone_dt == 'all') {
			$dts = array_keys($objects['data_queries']);
		} else {
			$dts = explode(',', $clone_dt);
		}

		foreach($dts as $dt_id) {
			if (is_numeric($dt_id) && $dt_id > 0 && isset($objects['data_templates'][$dt_id])) {
				$name   = $objects['data_templates'][$dt_id]['name'];
				$dihash = $objects['data_templates'][$dt_id]['dihash'];

				$exists = db_fetch_cell_prepared('SELECT id
					FROM data_template
					WHERE name = ?',
					array($name . $suffix));

				if ($exists > 0) {
					$warnings++;
					$return['warnings'][] = sprintf('WARNING: Data Template \'%s\' already exists.', $name . $suffix);
				}

				$name = db_fetch_assoc_prepared('SELECT name
					FROM data_input
					WHERE hash = ?',
					array($dihash));

				$exists = db_fetch_cell_prepared('SELECT id
					FROM data_input
					WHERE name = ?',
					array($name . $suffix));

				if ($exits > 0) {
					$warnings++;
					$return['warnings'][] = sprintf('WARNING: Data Template Data Input Method \'%s\' already exists.', $name . $suffix);
				}
			} else {
				$errors++;
				$return['errors'][] = sprintf('FATAL: Data Template ID %s to be cloned is not numeric.', $dq_id);
			}
		}
	}

	//print_r($objects);

	return $return;
}

/**
 * api_clone_device_template_get_objects - This function returns the core components
 *   from the Device Template for validating cloning actions.  Once these values
 *   are returned, the device template API will be able to clone the Device
 *   Template without errors.
 *
 * @param int - The Device Template ID to return objects for
 *
 * @return array - All the Device Template Objects
 */
function api_clone_device_template_get_objects($device_template_id) {
	global $config;

	$objects = array(
		'graph_templates'               => array(),
		'data_templates'                => array(),
		'data_queries'                  => array(),
		'data_query_graph_templates'    => array(),
		'data_query_data_templates'     => array()
	);

	$objects['graph_templates'] = array_rekey(
		db_fetch_assoc_prepared('SELECT gt.id, gt.name, gt.hash
			FROM host_template_graph AS ht
			INNER JOIN graph_templates AS gt
			ON ht.graph_template_id = gt.id
			WHERE ht.host_template_id = ?',
			array($device_template_id)),
		'id', array('name', 'hash')
	);

	$objects['data_queries'] = array_rekey(
		db_fetch_assoc_prepared('SELECT sq.id, sq.name, sq.hash, sq.data_input_id, di.hash AS dihash,
			REPLACE(sq.xml_path, "<path_cacti>", ?) AS xml_path,
			REPLACE(di.input_string, "<path_cacti>", ?) AS input_string
			FROM host_template_snmp_query AS htsq
			INNER JOIN snmp_query AS sq
			ON sq.id = htsq.snmp_query_id
			INNER JOIN data_input AS di
			ON di.id = sq.data_input_id
			WHERE host_template_id = ?',
			array($config['base_path'], $config['base_path'], $device_template_id)),
		'id', array('name', 'hash', 'dihash', 'data_input_id', 'xml_path', 'input_string')
	);

	if (cacti_sizeof($objects['data_queries'])) {
		foreach($objects['data_queries'] as $id => $data_query) {
			$snmp_query_data = get_data_query_array($id);

			if (isset($snmp_query_data['script_path'])) {
				$objects['data_queries'][$id]['script_path'] = str_replace('|path_cacti|', $config['base_path'], $snmp_query_data['script_path']);
			}
		}
	}

	if (cacti_sizeof($objects['graph_templates'])) {
		$objects['data_templates'] = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT dt.id, dt.name, dt.hash, dtd.data_input_id, di.hash AS dihash,
				REPLACE(di.input_string, "<path_cacti>", "' . $config['base_path'] . '") AS input_string
				FROM data_template AS dt
				INNER JOIN data_template_data AS dtd
				ON dt.id = dtd.data_template_id
				INNER JOIN data_template_rrd AS dtr
				ON dt.id = dtr.data_template_id
				INNER JOIN graph_templates_item AS gti
				ON dtr.id = gti.task_item_id
				INNER JOIN data_input AS di
				ON di.id = dtd.data_input_id
				WHERE dtr.local_data_id = 0
				AND gti.local_graph_id = 0
				AND gti.graph_template_id IN (
					SELECT graph_template_id
					FROM host_template_graph
					WHERE host_template_id = ?
				)',
				array($device_template_id)),
			'id', array('name', 'hash', 'dihash', 'data_input_id', 'input_string')
		);

		if (cacti_sizeof($objects['data_templates'])) {
			foreach($objects['data_templates'] as $id => $data_template) {
				/* peel the script from the input_string */
				if (isset($data_template['input_string'])) {
					$parts = explode(' ', $data_template['input_string']);

					foreach($parts as $p) {
						if (strpos($p, $config['base_path']) !== false) {
							if (file_exists($p)) {
								$objects['data_templates'][$id]['script_path'] = $p;
								break;
							}
						}
					}
				}

				/* let's get the list of graph templates that need updating */
				$graph_templates = array_rekey(
					db_fetch_assoc_prepared('SELECT DISTINCT graph_template_id AS id
						FROM graph_templates_item AS gti
						INNER JOIN data_template_rrd AS dtr
						ON gti.task_item_id = dtr.id
						WHERE local_graph_id = 0
						AND local_data_id = 0
						AND dtr.data_template_id = ?',
						array($id)),
					'id', 'id'
				);

				$objects['data_templates'][$id]['graph_template_ids'] = $graph_templates;
			}
		}
	}

	if (cacti_sizeof($objects['data_queries'])) {
		$objects['data_query_graph_templates'] = array_rekey(
			db_fetch_assoc('SELECT gt.id, gt.name, gt.hash, sqg.snmp_query_id, sqg.name AS sqname
				FROM graph_templates AS gt
				INNER JOIN snmp_query_graph AS sqg
				ON gt.id = sqg.graph_template_id
				WHERE sqg.snmp_query_id IN (' . implode(',', array_keys($objects['data_queries'])) . ')'),
			'id', array('name', 'hash', 'snmp_query_id', 'sqname')
		);

		$objects['data_query_data_templates'] = array_rekey(
			db_fetch_assoc('SELECT DISTINCT dt.id, dt.name, dt.hash, dtd.data_input_id, sq.id AS snmp_query_id
				FROM data_template AS dt
				INNER JOIN data_template_data AS dtd
				ON dt.id = dtd.data_template_id
				INNER JOIN snmp_query_graph_rrd AS sqgr
				ON dt.id = sqgr.data_template_id
				INNER JOIN snmp_query_graph AS sqg
				ON sqgr.snmp_query_graph_id = sqg.id
				INNER JOIN snmp_query AS sq
				ON sq.id = sqg.snmp_query_id
				WHERE dtd.local_data_id = 0
				AND sq.id IN (' . implode(',', array_keys($objects['data_queries'])) . ')'),
			'id', array('name', 'hash', 'data_input_id', 'snmp_query_id')
		);
	}

	return $objects;
}

/**
 * api_clone_device_template - Clones a device template and in some cases
 *   also updates duplicates Graph Templates, Data Templates, Data Input Methods
 *   and making copies of scripts, and XML files as well.
 *
 * @param int    - The Device Template ID
 * @param string - The proposed Device Template Name
 * @param string - A comma delimited list of Graph Template ID's to Include
 * @param string - A comma delimited list of Graph Template ID's to Clone
 * @param string - A comma delimited list of Data Query ID's to Include
 * @param string - A comma delimited list of Data Query ID's to Clone
 * @param string - A comma delimited list of Data Templates to Include
 * @param string - A comma delimited list of Data Templates to Clone
 * @param string - The suffix to use for Cloning objects
 * @param bool   - Boolean to direct Cacti to clone the XML
 * @param bool   - Boolean to direct to Clone scripts
 *
 * @return int|false - Either the new Device Template ID or false on error
 */
function api_clone_device_template($template_id, $template_name, $include_gt, $clone_gt,
	$include_dq, $clone_dq, $include_dt, $clone_dt, $suffix, $clone_xml, $clone_script, $cli = false) {

	global $config;

	/* The list of duplicated Data Templates.  Dont do it more than once */
	$duped_graph_templates[]    = array();
	$duped_data_templates[]     = array();
	$duped_data_input_methods[] = array();
	$duped_xmlfiles[]           = array();
	$duped_scripts[]            = array();
	$duped_data_query_graphs[]  = array();

	$start = microtime(true);

	$device_template = db_fetch_row_prepared('SELECT *
		FROM host_template
		WHERE id = ?',
		array($template_id));

	api_clone_message(sprintf('NOTE: Beginning Cloning Device Template %s.', $device_template['name']));

	$device_template_hash = generate_hash();

	if ($template_name == '') {
		$new_name = $device_template['name'] . $suffix;
	} else {
		$new_name = $template_name;
	}

	$new_name = api_clone_get_unique_name($new_name, 'host_template', 'name');

	api_clone_message(sprintf('NOTE: Cloning Device Template \'%s\'to \'%s\'', $device_template['name'], $new_name), true);

	$save         = $device_template;
	$save['id']   = 0;
	$save['name'] = $new_name;
	$save['hash'] = $device_template_hash;

	$new_template = sql_save($save, 'host_template');

	/**
	 * This process follows the following algorithm
	 *
	 * Template Duplication Process
	 * --------------------------------------------------------------
	 * 1. Get all current Device Template Objects
	 *
	 * 2. Copy the Device Template
	 *
	 * 3. Handle Include Cases
	 *    a. Include Graph Templates
	 *    b. Include Data Queries
	 *
	 * 4. Handle Clone Cases for Graph Template case
	 *    a. Clone Graph Templates
	 *    b. Clone Data Templates
	 *    c. Clone Data Input Method if Called for
	 *
	 * 5. Handle Clone Cases for Data Query Cases
	 *    a. Clone Data Queries
	 *    b. Clone Data Query Graph Templates
	 *    c. Clone Data Query Data Templates
	 *
	 * 6. Handle Clone XML and Clone Script options
	 *    a. Make copies of each XML file
	 *    b. Make copyies of each script file
	 *    c. Update references in Data Query XML files
	 *    d. Make updates in Data Input Methods
	 *
	 * NOTES:
	 * --------------------------------------------------------------
	 * In the Case of the Data Queries, there is no cloning of the
	 * Data Input Method as they are static and will not change
	 *
	 * In the case of Cloning Graph Template Data Templates,
	 * we must also clone the Data Input method as it is assumed
	 * that the clone should include it as well.
	 */

	/* get the list of exist Data Template Objects */
	$objects = api_clone_device_template_get_objects($template_id);

	/* include graph templates */
	if ($include_gt != '' && $include_gt != 'all') {
		$sql_where = 'AND graph_template_id IN (' . $include_gt . ')';
	} elseif ($clone_gt == 'all') {
		$sql_where = 'AND 1 = 0';
	} else {
		$sql_where = '';
	}

	$graph_templates = db_fetch_assoc_prepared("SELECT *
		FROM host_template_graph
		WHERE host_template_id = ?
		$sql_where",
		array($device_template['id']));

	if (cacti_sizeof($graph_templates)) {
		api_clone_message(sprintf('NOTE: Including %s Graph Templates', cacti_sizeof($graph_templates)));

		foreach($graph_templates as $gt) {
			db_execute_prepared('INSERT INTO host_template_graph
				(host_template_id, graph_template_id)
				VALUES (?, ?)',
				array($new_template, $gt['graph_template_id']));
		}
	} else {
		api_clone_message('NOTE: No Graph Templates to be Included');
	}

	/* include data queries */
	if ($include_dq != '' && $include_dq != 'all') {
		$sql_where = 'AND snmp_query_id IN (' . $include_dq . ')';
	} elseif ($clone_dq == 'all') {
		$sql_where = 'AND 1 = 0';
	} else {
		$sql_where = '';
	}

	$data_queries = db_fetch_assoc_prepared("SELECT *
		FROM host_template_snmp_query
		WHERE host_template_id = ?
		$sql_where",
		array($device_template['id']));

	if (cacti_sizeof($data_queries)) {
		api_clone_message(sprintf('NOTE: Including %s Data Queries', cacti_sizeof($data_queries)));

		foreach($data_queries as $dq) {
			db_execute_prepared('INSERT INTO host_template_snmp_query
				(host_template_id, snmp_query_id)
				VALUES (?, ?)',
				array($new_template, $dq['snmp_query_id']));
		}
	} else {
		api_clone_message('NOTE: No Data Queries to be Included');
	}

	/**
	 * Handle the Data Query Clone Case
	 *
	 * 1. Duplicate the Data Query
	 * 2. If --clone-xml - Clone the XML file
	 * 3. If --clone-script - Clone the Script and update the XML file
	 * 4. If --clone-xml - Update the Data Query with the new XML path
	 */
	if ($clone_dq != '') {
		api_clone_message('NOTE: Starting Data Query Clone Process');

		if ($clone_dq == 'all') {
			$ids = array_keys($objects['data_queries']);
		} else {
			$ids = explode(',', $clone_dq);
		}

		foreach($ids as $id) {
			$old_name    = $objects['data_queries'][$id]['name'];
			$new_name    = api_clone_get_unique_name($old_name, 'snmp_query', 'name');
			$new_dq      = data_query_duplicate($id, $new_name);
			$new_xml     = false;
			$new_script  = false;

			api_clone_message(sprintf('NOTE: Cloning Data Query \'%s\' to \'%s\'', $objects['data_queries'][$id]['name'], $new_name), true);

			db_execute_prepared('INSERT INTO host_template_snmp_query
				(host_template_id, snmp_query_id)
				VALUES (?, ?)',
				array($new_template, $new_dq));

			if ($clone_xml) {
				$old_xmlfile = $objects['data_queries'][$id]['xml_path'];
				$old_xmlbase = str_replace($config['base_path'], '', $old_xmlfile);
				$new_xmlfile = api_clone_get_unique_filename($old_xmlfile);
				$new_xmlbase = str_replace($config['base_path'], '', $new_xmlfile);

				if (!isset($duped_xmlfiles[$old_xmlfile])) {
					api_clone_message(sprintf('NOTE: Copying XML Base \'%s\' to \'%s\'', $old_xmlbase, $new_xmlbase));

					$new_xml = copy($old_xmlfile, $new_xmlfile);

					$duped_xmlfiles[$old_xmlfile] = $new_xmlfile;

					if ($clone_script) {
						if (isset($objects['data_queries'][$id]['script_path'])) {
							$old_scriptfile = $objects['data_queries'][$id]['script_path'];
							$old_scriptbase = str_replace($config['base_path'], '', $old_scriptfile);
							$new_scriptfile = api_clone_get_unique_filename($old_scriptfile);
							$new_scriptbase = str_replace($config['base_path'], '', $new_scriptfile);

							if ($new_xmlfile !== false) {
								if (!isset($duped_scripts[$old_scriptfile])) {
									api_clone_message(sprintf('NOTE: Copying Script Base \'%s\' to \'%s\'', $old_scriptbase, $new_scriptbase));

									$new_script     = copy($old_scriptfile, $new_scriptfile);
								} else {
									/* skipping as we've already cloned */
									$new_script     = true;
									$new_scriptfile = $duped_scripts[$old_scriptfile];
								}
							}
						}
					}

					/* update the XML with new values */
					if ($new_script) {
						api_clone_message(sprintf('NOTE: Updating \'%s\' with new values', $new_xmlfile));

						$data = file_get_contents($new_xmlfile);
						$data = str_replace($old_scriptfile, $new_scriptfile, $data);
						file_put_contents($new_xmlfile, $data);
					}
				} else {
					/* skipping as we've already cloned */
					$new_xmlfile = $duped_xmlfiles[$old_xmlfile];
				}

				db_execute_prepared('UPDATE snmp_query
					SET xml_path = ?
					WHERE id = ?',
					array($new_xmlfile, $new_dq));
			}

			/* Clone Data Query Graph Templates now */
			$dqgt = $objects['data_query_graph_templates'];

			foreach($dqgt as $gt_id => $gt_data) {
				if ($gt_data['snmp_query_id'] == $id) {
					$old_name  = $objects['data_query_graph_templates'][$gt_id]['name'];
					$new_name  = api_clone_get_unique_name($old_name, 'graph_templates', 'name');
					$dqgt_name = $objects['data_query_graph_templates'][$gt_id]['sqname'];

					if (!isset($duped_graph_templates[$gt_id])) {
						api_clone_message(sprintf('NOTE: Cloning Data Query Graph Template \'%s\' to \'%s\'', $old_name, $new_name), true);

						$new_gt = api_duplicate_graph(0, $gt_id, $new_name, false);

						$duped_graph_templates[$gt_id] = $new_gt;
					} else {
						/* skipping as we've already cloned */
						$new_gt = $duped_graph_templates[$gt_id];
					}

					/**
					 * update the snmp_query_graph entries
					 * Get the snmp_query_graph_id from the duplicated
					 * data query first to use to update the rest
					 */
					$snmp_query_graph_id = db_fetch_cell_prepared('SELECT id
						FROM snmp_query_graph
						WHERE snmp_query_id = ?
						AND graph_template_id = ?',
						array($new_dq, $gt_id));

					if ($snmp_query_graph_id > 0) {
						db_execute_prepared('UPDATE snmp_query_graph
							SET graph_template_id = ?, name = ?
							WHERE id = ?',
							array($new_gt, $dqgt_name, $snmp_query_graph_id));

						/**
						 * Since we clone the Data Query, we will clone the
						 * Data Template too.  So, get the new SNMP Query Graphs
						 * Data Template ID
						 */
						$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
							FROM snmp_query_graph_rrd
							WHERE snmp_query_graph_id = ?',
							array($snmp_query_graph_id));

						$old_snmp_query_graph_id = db_fetch_cell_prepared('SELECT id
							FROM snmp_query_graph
							WHERE snmp_query_id = ?
							AND graph_template_id = ?',
							array($id, $gt_id));

						$old_snmp_query_graph_rrds = db_fetch_assoc_prepared('SELECT *
							FROM snmp_query_graph_rrd
							WHERE snmp_query_graph_id = ?',
							array($old_snmp_query_graph_id));

						$old_snmp_query_graph_rrd_sv = db_fetch_assoc_prepared('SELECT *
							FROM snmp_query_graph_rrd_sv
							WHERE snmp_query_graph_id = ?',
							array($old_snmp_query_graph_id));

						$old_snmp_query_graph_sv = db_fetch_assoc_prepared('SELECT *
							FROM snmp_query_graph_sv
							WHERE snmp_query_graph_id = ?',
							array($old_snmp_query_graph_id));

						if ($data_template_id > 0) {
							$old_name = db_fetch_cell_prepared('SELECT name
								FROM data_template
								WHERE id = ?',
								array($data_template_id));

							if (!isset($duped_data_templates[$data_template_id])) {
								api_clone_message(sprintf('NOTE: Cloning Data Template \'%s\' to \'%s\'', $old_name, $new_name), true);

								$new_name = api_clone_get_unique_name($old_name, 'data_template', 'name');
								$new_dt   = api_duplicate_data_source(0, $data_template_id, $new_name);

								if (cacti_sizeof($old_snmp_query_graph_rrds)) {
									foreach($old_snmp_query_graph_rrds as $rrd) {
										$data_source_name = db_fetch_cell_prepared('SELECT data_source_name
											FROM data_template_rrd
											WHERE id = ?',
											array($rrd['data_template_rrd_id']));

										$dt_rrd_id = db_fetch_cell_prepared('SELECT id FROM data_template_rrd
											WHERE data_template_id = ?
											AND data_source_name = ?
											AND local_data_id = 0',
											array($new_dt, $data_source_name));

										db_execute_prepared('INSERT INTO snmp_query_graph_rrd
											(snmp_query_graph_id, data_template_id, data_template_rrd_id, snmp_field_name)
											VALUES (?, ?, ?, ?)',
											array(
												$snmp_query_graph_id,
												$new_dt,
												$dt_rrd_id,
												$rrd['snmp_field_name']
											)
										);

										db_execute_prepared('UPDATE graph_templates_item
											SET task_item_id = ?
											WHERE task_item_id = ?',
											array($dt_rrd_id, $rrd['data_template_rrd_id']));
									}
								}

								if (cacti_sizeof($old_snmp_query_graph_rrd_sv)) {
									foreach($old_snmp_query_graph_rrd_sv as $sv) {
										unset($save);

										$save['id']                  = 0;
										$save['hash']                = get_hash_data_query(0, 'data_query_sv_data_source');
										$save['snmp_query_graph_id'] = $snmp_query_graph_id;
										$save['data_template_id']    = $sv['data_template_id'];
										$save['sequence']            = $sv['sequence'];
										$save['field_name']          = $sv['field_name'];
										$save['text']                = $sv['text'];

										sql_save($save, 'snmp_query_graph_rrd_sv');
									}
								}

								if (cacti_sizeof($old_snmp_query_graph_sv)) {
									foreach($old_snmp_query_graph_sv as $sv) {
										unset($save);
										$save['id']                  = 0;
										$save['hash']                = get_hash_data_query(0, 'data_query_sv_graph');
										$save['snmp_query_graph_id'] = $snmp_query_graph_id;
										$save['sequence']            = $sv['sequence'];
										$save['field_name']          = $sv['field_name'];
										$save['text']                = $sv['text'];

										sql_save($save, 'snmp_query_graph_sv');
									}
								}



								$duped_data_templates[$data_template_id] = $new_dt;
							} else {
								/* skipping as we've already cloned */
								db_execute_prepared('UPDATE snmp_query_graph_rrd
									SET data_template_id = ?
									WHERE snmp_query_graph_id = ?',
									array($duped_data_templates[$data_template_id], $snmp_query_graph_id));
							}
						} else {
							api_clone_message(sprintf('WARNING: Data Query Graph Template \'%s\' not mapped to a Data Template', $snmp_query_graph_id));
						}
					}
				}
			}
		}
	}

	if ($clone_gt != '') {
		api_clone_message('NOTE: Cloning Non Data Query Graph Templates');

		if ($clone_gt == 'all') {
			$ids = array_keys($objects['graph_templates']);
		} else {
			$ids = explode(',', $clone_gt);
		}

		foreach($ids as $id) {
			$old_name = $objects['graph_templates'][$id]['name'];
			$new_name = api_clone_get_unique_name($old_name, 'graph_templates', 'name');

			if (!isset($duped_graph_templates[$id])) {
				api_clone_message(sprintf('NOTE: Cloning Graph Template \'%s\' to \'%s\'', $old_name, $new_name), true);

				$new_gt = api_duplicate_graph(0, $id, $new_name, false);

				$duped_graph_templates[$id] = $new_gt;
			} else {
				/* skipping as we've already cloned */
				$new_gt = $duped_graph_templates[$id];
			}

			db_execute_prepared('INSERT INTO host_template_graph
				(host_template_id, graph_template_id)
				VALUES (?, ?)',
				array($new_template, $new_gt));
		}
	}

	exit;

	if ($clone_dt != '') {
		api_clone_message('NOTE: Cloning Non Data Query Draph Templates');

		if ($clone_dt == 'all') {
			$ids = array_keys($objects['data_templates']);
		} else {
			$ids = explode(',', $clone_dt);
		}

		foreach($ids as $id) {
			$graph_templates = $objects['data_templates'][$id]['graph_templates'];

			$old_name = $objects['data_templates'][$id]['name'];
			$new_name = api_clone_get_unique_name($old_name, 'data_template', 'name');

			if (!isset($duped_data_templates[$id])) {
				api_clone_message(sprintf('NOTE: Cloning Data Template \'%s\' to \'%s\'', $old_name, $new_name), true);

				$new_dt = api_duplicate_data_source(0, $id, $new_name);

				if (isset($objects['data_templates'][$id]['script_path'])) {
					$old_scriptfile = $objects['data_queries'][$id]['script_path'];
					$new_scriptfile = api_clone_get_unique_filename($old_scriptfile);

					if (!isset($duped_scripts[$old_scriptfile])) {
						api_clone_message(sprintf('NOTE: Cloning Data Input Script \'%s\' to \'%s\'', $old_scriptfile, $new_scriptfile), true);

						$new_script = copy($old_scriptfile, $new_scriptfile);
					} else {
						/* skipping as we've already cloned */
						$new_script = $duped_scripts[$old_scriptfile];
					}

					// TO-DO Data Input Duplication
					//					db_execute('UPDATE data_input SET input_string=REPLACE(input_string, ?, ?)
					//						WHERE data_input_id = ?',
					//						array($old_scriptfile, $new_scriptfile, $new_di_id));
				}

				$duped_data_templates[$id] = $new_dt;
			} else {
				/* skipping as we've already cloned */
				$new_dt = $duped_data_templates[$id];
			}
		}
	}

	return $new_template;
}

