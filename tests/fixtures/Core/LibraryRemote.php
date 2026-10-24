<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Use a separate owned schema to observe primary/collector persistence independently. */
function library_remote(): void {
	global $database_hostname, $database_default, $database_port, $database_sessions;
	$password = getenv('CACTI_DB_ROOT_PASS');
	library_expect(is_string($password) && $password !== '', true, 'Disposable database administration is configured');
	$admin  = new PDO('mysql:host=' . $database_hostname . ';port=' . $database_port . ';charset=utf8mb4', 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	$schema = 'cov_remote_' . bin2hex(random_bytes(4));
	$admin->exec('CREATE DATABASE `' . $schema . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
	$poller  = $device = $template = 0;
	$remote  = null;
	$session = $_SESSION;
	$table   = 'coverage_replication_' . bin2hex(random_bytes(6));

	try {
		foreach (['host', 'host_snmp_query', 'host_graph', 'host_snmp_cache', 'host_value_cache', 'graph_local', 'version', 'poller', 'poller_command', 'graph_tree_items', 'reports_items', 'poller_item', 'poller_reindex', 'data_local', 'data_template_data', 'data_template_rrd', 'settings'] as $copy) {
			$admin->exec('CREATE TABLE `' . $schema . '`.`' . $copy . '` LIKE `' . $database_default . '`.`' . $copy . '`');
		}
		$poller = (int) sql_save(['id' => 0, 'name' => 'Coverage remote database', 'hostname' => $database_hostname, 'dbhost' => $database_hostname,
			'dbdefault'                   => $schema, 'dbuser' => 'root', 'dbpass' => $password, 'dbport' => $database_port, 'dbretries' => 1, 'dbssl' => '', 'dbsslkey' => '', 'dbsslcert' => '', 'dbsslca' => '', 'dbsslcapath' => '',
			'disabled'                    => '', 'last_status' => date('Y-m-d H:i:s')], 'poller');
		library_expect($poller > 1, true, 'Remote fixture owns its collector');
		library_expect(remote_poller_up($poller), true, 'Recent enabled collector is available');
		$remote = poller_push_to_remote_db_connect($poller, true);
		library_expect($remote instanceof PDO, true, 'Production connector reaches the isolated remote schema');
		library_expect(db_fetch_cell('SELECT DATABASE()', '', false, $remote), $schema, 'Collector writes use a distinct database');
		$device = library_save_device(['description' => 'Remote propagation fixture', 'poller_id' => $poller, 'snmp_version' => 0]);
		library_expect($device > 0, true, 'Remote collector receives an owned device');
		library_expect(db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$device], '', false, $remote), 'Remote propagation fixture', 'Device save reaches the remote collector');

		foreach (['enable', 'disable'] as $action) {
			$action === 'enable' ? api_device_enable_devices([$device]) : api_device_disable_devices([$device]);

			foreach ([false, $remote] as $connection) {
				library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device], '', false, $connection), $action === 'enable' ? '' : 'on', 'Device availability propagates to both databases');
			}
		}
		$template = (int) sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'name' => 'Remote template fixture'], 'host_template');
		library_expect($template > 0, true, 'Remote template fixture owns its identity');
		api_device_update_host_template($device, $template);

		foreach ([false, $remote] as $connection) {
			library_expect((int) db_fetch_cell_prepared('SELECT host_template_id FROM host WHERE id = ?', [$device], '', false, $connection), $template, 'Template assignment propagates to both databases');
		}
		// A stale collector must keep its prior state and warn that synchronization is needed.
		library_expect(db_execute_prepared('UPDATE poller SET last_status = ? WHERE id = ?', ['2000-01-01 00:00:00', $poller]), true, 'Fixture marks the collector unavailable');
		library_expect(remote_poller_up($poller), false, 'Stale heartbeat makes the collector unavailable');
		api_device_enable_devices([$device]);
		library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device]), '', 'Primary update remains visible during collector outage');
		library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device], '', false, $remote), 'on', 'Unavailable collector retains its prior state');
		library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Partial propagation supplies a synchronization warning');
		db_execute_prepared('UPDATE poller SET last_status = NOW() WHERE id = ?', [$poller]);
		api_device_enable_devices([$device]);
		library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device], '', false, $remote), '', 'Retry propagates the retained desired state');

		library_remote_failures($poller, $device, $remote, $database_hostname);
		library_remote_cache($poller, $device, $remote, $database_hostname);

		library_expect(db_execute('CREATE TABLE `' . $table . '` (id int PRIMARY KEY, label varchar(80) NOT NULL, retained varchar(80) NOT NULL)'), true, 'Replication owns its source table');
		$data = [];
		replicate_out_table($remote, $data, $table, $poller);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table], '', false, $remote), 1, 'Empty source still creates the missing remote schema');
		$data = [['id' => 1, 'label' => 'Initial café', 'retained' => 'Remote-owned']];
		replicate_out_table($remote, $data, $table, $poller);
		library_expect(db_fetch_assoc('SELECT * FROM `' . $table . '`', true, $remote), $data, 'Initial replication preserves UTF-8 and column values');
		$data[0]['label']    = 'Updated';
		$data[0]['retained'] = 'Primary value';
		replicate_out_table($remote, $data, $table, $poller, false, 'retained');
		library_expect(db_fetch_row('SELECT * FROM `' . $table . '`', true, $remote), ['id' => 1, 'label' => 'Updated', 'retained' => 'Remote-owned'], 'Incremental replication preserves excluded remote-owned fields');
		library_expect(db_execute('ALTER TABLE `' . $table . '` DROP COLUMN retained', true, $remote), true, 'Fixture reproduces an older remote schema');
		replicate_out_table($remote, $data, $table, $poller);
		library_expect(db_fetch_assoc('SELECT * FROM `' . $table . '`', true, $remote), $data, 'Schema repair restores the full source row');
		$data = [];

		for ($i = 1; $i <= 1003; $i++) {
			$data[] = ['id' => $i, 'label' => 'Row ' . $i, 'retained' => 'Value ' . $i];
		}
		replicate_out_table($remote, $data, $table, $poller);
		library_expect(db_fetch_assoc('SELECT * FROM `' . $table . '` ORDER BY id', true, $remote), $data, 'Replication preserves every row across its 1001-row flush and final remainder');
		api_device_remove($device);
		library_expect(db_fetch_cell_prepared('SELECT deleted FROM host WHERE id = ?', [$device]), 'on', 'Primary retains a deletion marker until collector cleanup can complete');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$device], '', false, $remote), 0, 'Device removal deletes the remote record');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_command WHERE poller_id = ? AND action = ? AND command = ?', [$poller, POLLER_COMMAND_PURGE, (string) $device]), 1, 'Deletion queues a collector purge for recovery');
		db_execute_prepared('UPDATE host SET last_updated = ? WHERE id = ?', ['2000-01-01 00:00:00', $device]);
		api_device_purge_deleted_devices();
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$device]), 0, 'Deferred cleanup removes the aged primary deletion marker');
		$device = 0;
	} finally {
		if ($device > 0) {
			db_execute_prepared('UPDATE host SET poller_id = 1 WHERE id = ?', [$device]);
			api_device_remove($device);
		}
		db_execute_prepared('DELETE FROM host_template WHERE id = ?', [$template]);
		db_execute_prepared('DELETE FROM poller_command WHERE poller_id = ?', [$poller]);
		db_execute_prepared('DELETE FROM poller WHERE id = ?', [$poller]);
		db_execute('DROP TABLE IF EXISTS `' . $table . '`');

		foreach ($database_sessions as $key => $connection) {
			if ($connection === $remote) {
				unset($database_sessions[$key]);
			}
		}
		$remote = null;
		$admin->exec('DROP DATABASE `' . $schema . '`');
		$_SESSION = $session;
	}
}

function library_remote_failures(int $poller, int $device, PDO $remote, string $hostname): void {
	$query         = (int) db_fetch_cell('SELECT id FROM snmp_query ORDER BY id LIMIT 1');
	$graphTemplate = (int) db_fetch_cell('SELECT id FROM graph_templates ORDER BY id LIMIT 1');
	library_expect($query > 0 && $graphTemplate > 0, true, 'Propagation cases use installed query and graph template identities');
	api_device_disable_devices([$device]);

	foreach (['offline', 'invalid-address'] as $failure) {
		db_execute_prepared('UPDATE poller SET last_status = ?, dbhost = ? WHERE id = ?', [$failure === 'offline' ? '2000-01-01 00:00:00' : date('Y-m-d H:i:s'), $failure === 'offline' ? $hostname : '127.0.0.1', $poller]);

		foreach (['disable', 'enable'] as $action) {
			$before = db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device], '', false, $remote);
			unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
			$action === 'disable' ? api_device_disable_devices([$device]) : api_device_enable_devices([$device]);
			library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device]), $action === 'disable' ? 'on' : '', 'Availability change persists locally during collector failure');
			library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device], '', false, $remote), $before, 'Unavailable collector retains its previous availability');
			library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Availability change reports failed propagation');
		}
		$requests = [$_REQUEST, $_GET, $_POST, $GLOBALS['_CACTI_REQUEST'] ?? []];

		try {
			$description = db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$device], '', false, $remote);
			srv('description', 'Deferred collector description');
			unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
			api_device_change_options([$device], ['t_description' => 'on']);
			library_expect(db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$device]), 'Deferred collector description', 'Bulk edit persists selected fields locally');
			library_expect(db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$device], '', false, $remote), $description, 'Failed bulk propagation preserves the remote value');
			library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Bulk edit reports incomplete propagation');
		} finally {
			[$_REQUEST, $_GET, $_POST, $GLOBALS['_CACTI_REQUEST']] = $requests;
		}
		unset($_SESSION[SESS_MESSAGES]['poller_down_' . $device]);
		library_expect(library_save_device(['id' => $device, 'poller_id' => $poller, 'description' => 'Remote propagation fixture', 'snmp_version' => 0]), $device, 'Single-device save retains its identity during collector failure');
		library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $device]), true, 'Single-device save reports incomplete propagation');

		foreach (['add', 'change', 'remove'] as $operation) {
			foreach ([false, $remote] as $connection) {
				db_execute_prepared('REPLACE INTO host_snmp_query (host_id, snmp_query_id, reindex_method) VALUES (?, ?, ?)', [$device, $query, 0], true, $connection);
			}
			unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
			match ($operation) {
				'add'    => api_device_dq_add($device, $query, '1'),
				'change' => api_device_dq_change($device, $query, '2'),
				'remove' => api_device_dq_remove($device, $query),
			};
			library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Query mutation warns about ' . $failure . ' propagation');
			library_expect((int) db_fetch_cell_prepared('SELECT reindex_method FROM host_snmp_query WHERE host_id = ? AND snmp_query_id = ?', [$device, $query], '', false, $remote), 0, 'Unreachable collector retains its query settings');
			$local = db_fetch_cell_prepared('SELECT reindex_method FROM host_snmp_query WHERE host_id = ? AND snmp_query_id = ?', [$device, $query]);
			library_expect($operation === 'remove' ? $local === false : (int) $local === ($operation === 'add' ? 1 : 2), true, 'Primary query mutation remains available for later synchronization');
		}

		foreach ([false, $remote] as $connection) {
			db_execute_prepared('REPLACE INTO host_graph (host_id, graph_template_id) VALUES (?, ?)', [$device, $graphTemplate], true, $connection);
		}
		unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
		api_device_gt_remove($device, $graphTemplate);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ? AND graph_template_id = ?', [$device, $graphTemplate]), 0, 'Primary graph-template unlink succeeds during outage');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ? AND graph_template_id = ?', [$device, $graphTemplate], '', false, $remote), 1, 'Failed propagation preserves the collector mapping');
		library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Graph-template unlink warns about failed propagation');
		unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
		api_device_clear_statistics([$device]);
		library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Statistics reset warns about failed propagation');
		library_expect((int) db_fetch_cell_prepared('SELECT total_polls FROM host WHERE id = ?', [$device]), 0, 'Statistics reset still applies locally');
	}
	db_execute_prepared('UPDATE poller SET last_status = NOW(), dbhost = ? WHERE id = ?', [$hostname, $poller]);
	api_device_dq_remove($device, $query);
	api_device_gt_remove($device, $graphTemplate);
	library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_snmp_query WHERE host_id = ? AND snmp_query_id = ?', [$device, $query], '', false, $remote), 0, 'Query removal retry clears the retained collector mapping');
	library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ? AND graph_template_id = ?', [$device, $graphTemplate], '', false, $remote), 0, 'Graph-template removal retry clears the retained collector mapping');
}

/** Flush serialized production poller records across both databases and an outage. */
function library_remote_cache(int $poller, int $device, PDO $remote, string $hostname): void {
	require_once CACTI_PATH_LIBRARY . '/api_poller.php';
	$ids      = [];
	$items    = [];
	$expected = [];
	$host     = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', [$device]);
	$settings = db_fetch_assoc("SELECT * FROM settings WHERE name IN ('snmp_cred_version', 'time_last_change_poller_item')");

	try {
		api_device_enable_devices([$device]);

		for ($source = 0; $source < 60; $source++) {
			$id = (int) sql_save(['id' => 0, 'host_id' => $device, 'data_template_id' => 0], 'data_local');
			library_expect($id > 0, true, 'Cache fixture owns its data source identity');
			$ids[] = $id;
			library_expect((int) sql_save(['id' => 0, 'local_data_id' => $id, 'data_source_path' => '<path_rra>/coverage_cache_' . $id . '.rrd', 'rrd_step' => 300, 'active' => 'on'], 'data_template_data') > 0, true, 'Cache source uses a real owned metadata record');

			for ($series = 0; $series < 10; $series++) {
				$name     = 'sensor_' . $series;
				$argument = str_repeat('x', 400) . " ' ; literal " . $source . ':' . $series;
				$record   = api_poller_cache_item_add($device, ['snmp_version' => 3, 'snmp_username' => 'fixture-user', 'snmp_password' => 'fixture-password'], $id, 300, POLLER_ACTION_SCRIPT, $name, 10, $argument);
				library_expect(is_string($record), true, 'Production serializer accepts each owned series');
				$items[]    = $record;
				$expected[] = ['local_data_id' => $id, 'rrd_name' => $name, 'arg1' => $argument, 'present' => 1];
			}
		}
		library_expect(strlen(implode(',', $items)) > 256000, true, 'Cache fixture crosses the production flush boundary');
		poller_update_poller_cache_from_buffer($ids, $items, $poller);
		$sql = 'SELECT local_data_id, rrd_name, arg1, present FROM poller_item WHERE host_id = ? ORDER BY local_data_id, rrd_name';

		foreach ([false, $remote] as $connection) {
			library_expect(db_fetch_assoc_prepared($sql, [$device], true, $connection), $expected, 'Both flush and remainder preserve serialized values');
		}
		$version = db_fetch_cell("SELECT value FROM settings WHERE name = 'snmp_cred_version'");
		library_expect(is_string($version) && $version !== '', true, 'SNMPv3 refresh produces an invalidation token');
		library_expect(db_fetch_cell("SELECT value FROM settings WHERE name = 'snmp_cred_version'", '', false, $remote), $version, 'Collector receives the same credential invalidation token');
		$empty = [];

		foreach (['offline', 'invalid-address'] as $failure) {
			db_execute_prepared('UPDATE poller SET last_status = ?, dbhost = ? WHERE id = ?', [$failure === 'offline' ? '2000-01-01 00:00:00' : date('Y-m-d H:i:s'), $failure === 'offline' ? $hostname : '127.0.0.1', $poller]);

			// Empty selected-ID lists are supported when appending freshly built
			// cache records. Exercise each flush independently of stale-row marking.
			foreach ([$items, array_slice($items, 0, 10)] as $batch) {
				unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
				poller_update_poller_cache_from_buffer([], $batch, $poller);
				library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Cache append warns when either flush size cannot propagate');
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_item WHERE host_id = ?', [$device], '', false, $remote), 600, 'Failed append leaves the existing collector cache intact');
			}
			unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
			poller_update_poller_cache_from_buffer([$ids[0]], $empty, $poller);
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_item WHERE local_data_id = ?', [$ids[0]]), 0, 'Cache removal applies locally during collector outage');
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_item WHERE local_data_id = ?', [$ids[0]], '', false, $remote), 10, 'Unavailable collector keeps its prior poller records');
			library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Cache outage produces a synchronization warning');
		}
		db_execute_prepared('UPDATE poller SET last_status = NOW(), dbhost = ? WHERE id = ?', [$hostname, $poller]);
		poller_update_poller_cache_from_buffer([$ids[0]], $empty, $poller);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_item WHERE local_data_id = ?', [$ids[0]], '', false, $remote), 0, 'Retry removes the retained remote cache entries');

		foreach ([false, $remote] as $connection) {
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_item WHERE host_id = ?', [$device], '', false, $connection), 590, 'Selective cleanup preserves unrelated sources');
		}
	} finally {
		db_execute_prepared('UPDATE poller SET last_status = NOW(), dbhost = ? WHERE id = ?', [$hostname, $poller]);

		foreach ($ids as $id) {
			foreach (['poller_item', 'data_template_data'] as $table) {
				db_execute_prepared('DELETE FROM ' . $table . ' WHERE local_data_id = ?', [$id]);
			}
			db_execute_prepared('DELETE FROM data_local WHERE id = ?', [$id]);
			db_execute_prepared('DELETE FROM poller_item WHERE local_data_id = ?', [$id], true, $remote);
		}
		db_execute("DELETE FROM settings WHERE name IN ('snmp_cred_version', 'time_last_change_poller_item')");

		foreach ($settings as $row) {
			set_config_option($row['name'], $row['value']);
		}
		sql_save($host, 'host');
	}
}
