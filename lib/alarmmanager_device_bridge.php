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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/
function alarmmanager_device_bridge_core(array $safe, int $previous, callable $write): bool {
	global $config;
	$path = $config['base_path'] . '/plugins/alarmmanager/includes/device.php';

	if (!is_file($path)) {
		return (bool) $write();
	}
	require_once($path);

	return alarmmanager_device_core_commit($safe, $previous, $write);
}
function alarmmanager_device_bridge_decision(array $host, string $kind): array {
	global $config;
	$safe = array_intersect_key($host, array_flip(['id', 'status', 'hostname', 'description', 'status_fail_date', 'status_rec_date']));
	$path = $config['base_path'] . '/plugins/alarmmanager/includes/device.php';

	if (is_file($path)) {
		require_once($path);

		return alarmmanager_device_thold_decision($safe, $kind);
	}

	// Keep committed ownership even if the plugin files are removed; never decrypt a secret here.
	try {
		$rows = db_fetch_assoc_prepared('SELECT * FROM plugin_alarmmanager_device_owners WHERE device_id = ?', [(int) $host['id']], false);

		if (!$rows) {
			return [];
		} $grant = $rows[0];
		$fail    = (int) db_fetch_cell_prepared('SELECT UNIX_TIMESTAMP(?)', [$host['status_fail_date']], '', false);
		$recover = (int) db_fetch_cell_prepared('SELECT UNIX_TIMESTAMP(?)', [$host['status_rec_date']], '', false);
		$rows    = db_fetch_assoc_prepared('SELECT mode, channels FROM plugin_alarmmanager_device_receipts WHERE device_id = ? AND epoch = ? AND transition = ? AND fail_epoch = ? AND rec_epoch = ? ORDER BY sequence DESC LIMIT 1', [(int) $host['id'], (int) $grant['epoch'], $kind, $fail, $kind === 'recovered' ? $recover : 0], false);

		if ($rows) {
			return ['owned' => $rows[0]['mode'] === 'centralized', 'channels' => json_decode($rows[0]['channels'], true)];
		}

		if ($grant['mode'] === 'centralized' && ((int) $grant['poller_id'] !== (int) ($config['poller_id'] ?? 1) || (int) db_fetch_cell_prepared('SELECT UTC_TIMESTAMP() > ?', [$grant['valid_until']], '', false))) {
			return ['owned' => true, 'channels' => ['email', 'script']];
		}
	} catch (Throwable $error) {
	}

	return [];
}
function alarmmanager_device_bridge_owns(array $decision, string $channel): bool {
	return ($decision['owned'] ?? false) && in_array($channel, $decision['channels'] ?? [], true);
}
function alarmmanager_device_bridge_poll_allowed(int $device): bool {
	global $config;
	static $expires = 0;
	static $grants  = [];

	if ($expires <= time()) {
		$grants  = [];
		$expires = time() + 30;

		try {
			$rows = db_fetch_assoc_prepared("SELECT device_id, poller_id, valid_from, valid_until FROM plugin_alarmmanager_device_owners WHERE mode = 'centralized' AND poller_id = ? LIMIT 4097", [(int) ($config['poller_id'] ?? 1)], false);

			if (is_array($rows)) {
				foreach ($rows as $row) {
					$grants[(int) $row['device_id']] = $row;
				}
			}
		} catch (Throwable $error) {
		}
	}
	$grant = $grants[$device] ?? null;

	return !$grant || ((int) $grant['poller_id'] === (int) ($config['poller_id'] ?? 1) && strtotime($grant['valid_from'] . ' UTC') <= time() && strtotime($grant['valid_until'] . ' UTC') > time());
}
/** Enrolled collectors keep their local operational host fields during Cacti configuration sync. */
function alarmmanager_device_bridge_replicate_hosts($connection, array &$data, int $poller): bool {
	try {
		$peers = db_fetch_assoc_prepared('SELECT poller_id FROM plugin_alarmmanager_sync_peers WHERE poller_id = ? AND enabled = 1', [$poller], false);

		if (!is_array($peers) || !$peers) {
			return false;
		}

		if (db_table_exists('host', false, $connection)) {
			$local  = db_fetch_assoc('SHOW COLUMNS FROM host');
			$remote = db_fetch_assoc('SHOW COLUMNS FROM host', false, $connection);

			if (cacti_sizeof($local) !== cacti_sizeof($remote)) {
				cacti_log('ERROR: Alarm Manager enrolled collector host schema differs; upgrade it explicitly before configuration sync.', false, 'ALARMMANAGER');

				return true;
			}
		}
		$fields = ['status', 'status_event_count', 'status_fail_date', 'status_rec_date', 'status_last_error', 'min_time', 'max_time', 'cur_time', 'avg_time', 'total_polls', 'failed_polls', 'availability', 'last_updated', 'polling_time'];
		replicate_out_table($connection, $data, 'host', $poller, false, $fields);

		// Retire devices no longer assigned here without deleting surviving local status.
		if (!db_execute_prepared('CREATE TEMPORARY TABLE IF NOT EXISTS alarmmanager_host_config_ids (id INT UNSIGNED PRIMARY KEY) ENGINE=InnoDB', [], false, $connection) || !db_execute_prepared('DELETE FROM alarmmanager_host_config_ids', [], false, $connection)) {
			throw new RuntimeException('configuration_sync_failed');
		}

		foreach (array_chunk($data, 500) as $chunk) {
			$ids = array_map(static function ($row) { return (int) $row['id']; }, $chunk);

			if (!db_execute_prepared('INSERT IGNORE INTO alarmmanager_host_config_ids (id) VALUES ' . implode(',', array_fill(0, cacti_sizeof($ids), '(?)')), $ids, false, $connection)) {
				throw new RuntimeException('configuration_sync_failed');
			}
		}

		if (!db_execute_prepared("UPDATE host AS h LEFT JOIN alarmmanager_host_config_ids AS live ON live.id = h.id SET h.deleted = 'on' WHERE h.poller_id = ? AND live.id IS NULL", [$poller], false, $connection)) {
			throw new RuntimeException('configuration_sync_failed');
		}

		return true;
	} catch (Throwable $error) {
		cacti_log('ERROR: Alarm Manager collector host configuration sync failed; local operational state is preserved.', false, 'ALARMMANAGER');

		return true;
	}
}
