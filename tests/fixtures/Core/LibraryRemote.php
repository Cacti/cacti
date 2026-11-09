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
		library_remote_outages($admin, $schema, $remote, $database_hostname);

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
		$pulled = [['id' => 1, 'label' => 'Pulled label', 'retained' => 'Pulled value']];
		// Failed statements are only visible in the log, so it accompanies a mismatch.
		$log    = library_captured_log(static function () use ($remote, &$pulled, $table): void {
			replicate_table_to_poller($remote, $pulled, $table);
		});
		library_expect(db_fetch_row('SELECT * FROM `' . $table . '` WHERE id = 1', true, $remote), $pulled[0], 'Collector replication updates existing rows: ' . $log);
		library_remote_strict_exclusion($remote);
		$empty = [];
		replicate_out_table($remote, $empty, $table, $poller);
		library_expect((int) db_fetch_cell('SELECT COUNT(*) FROM `' . $table . '`', '', true, $remote), 0, 'An empty source empties an existing remote table');
		$data = [];

		// Progress is reported once a table passes 100000 replicated rows.
		for ($i = 1; $i <= 100100; $i++) {
			$data[] = ['id' => $i, 'label' => 'R', 'retained' => 'V'];
		}
		replicate_out_table($remote, $data, $table, $poller);
		library_expect((int) db_fetch_cell('SELECT COUNT(*) FROM `' . $table . '`', '', true, $remote), 100100, 'Large replication stores every row');
		$data = [];
		library_expect(replicate_out($poller, 'all'), true, 'Full replication reaches the collector');
		library_expect(db_fetch_cell("SELECT value FROM settings WHERE name = 'log_verbosity'", '', true, $remote), db_fetch_cell("SELECT value FROM settings WHERE name = 'log_verbosity'"), 'Full replication copies shared settings');
		\CactiLibraryResourceFixture\run();
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

/** Replicate rows with an excluded NOT NULL column that has no default into a strict collector session. */
function library_remote_strict_exclusion(PDO $remote): void {
	$table = 'coverage_strict_' . bin2hex(random_bytes(6));
	$mode  = db_fetch_cell('SELECT @@SESSION.sql_mode', '', true, $remote);
	library_expect(db_execute('CREATE TABLE `' . $table . '` (id int PRIMARY KEY, label varchar(80) NOT NULL, retained varchar(80) NOT NULL)', true, $remote), true, 'Collector owns a table whose retained column has no default');

	try {
		library_expect(db_execute("SET SESSION sql_mode = 'STRICT_ALL_TABLES'", true, $remote), true, 'Collector session refuses implicit column defaults');
		library_expect(db_execute_prepared('INSERT INTO `' . $table . '` (id, label, retained) VALUES (?, ?, ?)', [1, 'Initial', 'Remote-owned'], true, $remote), true, 'Collector already holds a row');
		$rows = [['id' => 1, 'label' => 'Updated', 'retained' => 'Primary value'], ['id' => 2, 'label' => 'Added', 'retained' => 'Primary seed']];
		$log  = library_captured_log(static function () use ($remote, &$rows, $table): void {
			replicate_table_to_poller($remote, $rows, $table, ['retained']);
		});
		library_expect(db_fetch_assoc('SELECT * FROM `' . $table . '` ORDER BY id', true, $remote),
			[['id' => 1, 'label' => 'Updated', 'retained' => 'Remote-owned'], ['id' => 2, 'label' => 'Added', 'retained' => 'Primary seed']],
			'Excluded columns keep the collector value on update and take the source value on insert: ' . $log);
	} finally {
		db_execute_prepared('SET SESSION sql_mode = ?', [$mode], true, $remote);
		db_execute('DROP TABLE IF EXISTS `' . $table . '`', true, $remote);
	}
}

/** Exercise local persistence and failure reporting when propagation to a collector fails. */
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

			switch ($operation) {
				case 'add':
					api_device_dq_add($device, $query, '1');

					break;
				case 'change':
					api_device_dq_change($device, $query, '2');

					break;
				default:
					api_device_dq_remove($device, $query);
			}
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
		// A source sampled faster than the poller runs warns the operator once.
		$log    = cacti_log_file();
		$offset = is_file($log) ? filesize($log) : 0;
		library_expect(api_poller_get_rrd_next_step($device, 60, $ids[2]), 0, 'A new device starts its step rotation at zero');
		library_expect(str_contains((string) file_get_contents($log, false, null, $offset), 'Change your Poller Interval to 60 seconds'), true, 'Fast sampling interval is reported in the Cacti log');
		api_data_source_disable_multi([$ids[1]]);

		foreach ([false, $remote] as $connection) {
			library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM poller_item WHERE local_data_id = ?', [$ids[1]], '', false, $connection), 0, 'Bulk disable removes the source from both poller caches');
		}
		library_expect(db_fetch_cell_prepared('SELECT active FROM data_template_data WHERE local_data_id = ?', [$ids[1]]), '', 'Bulk disable stops polling the source');
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

/** Register another collector backed by the isolated remote schema. */
function library_remote_collector(string $schema, string $dbhost, string $status): int {
	global $database_port;
	$poller = (int) sql_save(['id' => 0, 'name' => 'Coverage collector ' . bin2hex(random_bytes(4)), 'hostname' => $dbhost, 'dbhost' => $dbhost,
		'dbdefault'                   => $schema, 'dbuser' => 'root', 'dbpass' => getenv('CACTI_DB_ROOT_PASS'), 'dbport' => $database_port, 'dbretries' => 1, 'dbssl' => '', 'dbsslkey' => '', 'dbsslcert' => '', 'dbsslca' => '', 'dbsslcapath' => '',
		'disabled'                    => '', 'last_status' => $status], 'poller');
	library_expect($poller > 1, true, 'Outage fixture owns its collector');

	return $poller;
}

/** Expire the collector heartbeat or address from inside its own remote write, as a collector that drops mid-operation would. */
function library_remote_lapse(PDO $admin, string $schema, string $event, int $poller, string $failure): string {
	global $database_default;
	$trigger    = 'coverage_lapse_' . bin2hex(random_bytes(4));
	$assignment = $failure === 'offline' ? "last_status = '2000-01-01 00:00:00'" : "dbhost = '127.0.0.1'";
	library_expect($admin->exec('CREATE TRIGGER `' . $schema . '`.`' . $trigger . '` BEFORE ' . $event . ' ON `' . $schema . '`.`host` FOR EACH ROW UPDATE `' . $database_default . '`.`poller` SET ' . $assignment . ' WHERE id = ' . $poller), 0, 'Collector lapse is armed');

	return $trigger;
}

/** Report partial propagation when a collector is unavailable before or during each device, template and data-source mutation. */
function library_remote_outages(PDO $admin, string $schema, PDO $remote, string $hostname): void {
	$pollers   = [];
	$devices   = [];
	$sources   = [];
	$templates = [];
	$triggers  = [];
	$converted = [];
	$query     = (int) db_fetch_cell('SELECT id FROM snmp_query ORDER BY id LIMIT 1');
	$graph     = (int) db_fetch_cell('SELECT id FROM graph_templates WHERE id NOT IN (SELECT graph_template_id FROM snmp_query_graph) ORDER BY id LIMIT 1');
	library_expect($query > 0 && $graph > 0, true, 'Outage cases use installed query and graph template identities');
	$failures = ['offline' => ['2000-01-01 00:00:00', $hostname], 'invalid-address' => [date('Y-m-d H:i:s'), '127.0.0.1']];
	$session  = $_SESSION;

	/** Create a replicated device on a fresh available collector. */
	$collector = static function (string $description) use ($schema, $hostname, &$pollers, &$devices): array {
		$poller    = library_remote_collector($schema, $hostname, date('Y-m-d H:i:s'));
		$pollers[] = $poller;
		$device    = library_save_device(['description' => $description, 'poller_id' => $poller, 'snmp_version' => 0]);
		library_expect($device > 0, true, 'Outage device is created on an available collector');
		$devices[] = $device;

		return [$poller, $device];
	};
	/** Make an existing collector stale or unreachable. */
	$fail = static function (int $poller, string $failure) use ($failures): void {
		library_expect(db_execute_prepared('UPDATE poller SET last_status = ?, dbhost = ? WHERE id = ?', [...$failures[$failure], $poller]), true, 'Collector failure is applied');
	};

	$structure = $GLOBALS['struct_data_source'];

	try {
		foreach (['query', 'graph', 'empty'] as $kind) {
			$templates[$kind] = (int) sql_save(['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'name' => 'Outage template ' . $kind], 'host_template');
			library_expect($templates[$kind] > 0, true, 'Outage template exists');
		}
		library_expect(db_execute_prepared('INSERT INTO host_template_snmp_query (host_template_id, snmp_query_id) VALUES (?, ?)', [$templates['query'], $query]), true, 'Query template owns a data query');
		library_expect(db_execute_prepared('INSERT INTO host_template_graph (host_template_id, graph_template_id) VALUES (?, ?)', [$templates['graph'], $graph]), true, 'Graph template owns a graph template');

		// Each stage of a template change re-checks the collector, so fail it before
		// the change or from inside the first remote write.
		foreach ([
			['query', 'invalid-address', null], ['query', 'offline', null], ['query', null, 'offline'],
			['graph', null, 'invalid-address'], ['graph', null, 'offline'],
			['empty', null, 'invalid-address'], ['empty', null, 'offline'],
		] as [$kind, $before, $during]) {
			[$poller, $device] = $collector('Template outage ' . $kind);

			if ($kind === 'empty') {
				foreach ([false, $remote] as $connection) {
					library_expect(db_execute_prepared('REPLACE INTO host_graph (host_id, graph_template_id) VALUES (?, ?)', [$device, $graph], true, $connection), true, 'Stale graph mapping exists');
				}
			}

			if ($before !== null) {
				$fail($poller, $before);
			} else {
				$triggers[] = library_remote_lapse($admin, $schema, 'UPDATE', $poller, $during);
			}
			unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
			api_device_update_host_template($device, $templates[$kind]);
			library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Template change reports the ' . ($before ?? $during) . ' collector');
			library_expect((int) db_fetch_cell_prepared('SELECT host_template_id FROM host WHERE id = ?', [$device]), $templates[$kind], 'Template change persists locally');
			library_expect((int) db_fetch_cell_prepared('SELECT host_template_id FROM host WHERE id = ?', [$device], '', false, $remote), $before === null ? $templates[$kind] : 0, 'Collector keeps only the writes made before it failed');

			if ($kind === 'query') {
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_snmp_query WHERE host_id = ?', [$device]), 1, 'Template query is associated locally');
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_snmp_query WHERE host_id = ?', [$device], '', false, $remote), 0, 'Failed collector does not receive the query');
			} elseif ($kind === 'graph') {
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ?', [$device]), 1, 'Template graph is associated locally');
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ?', [$device], '', false, $remote), 0, 'Failed collector does not receive the graph');
			} else {
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ?', [$device]), 0, 'Unused graph mapping is removed locally');
				library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host_graph WHERE host_id = ?', [$device], '', false, $remote), 1, 'Failed collector retains the unused mapping');
			}
		}

		foreach (array_keys($failures) as $failure) {
			[$poller, $device] = $collector('Enable outage');
			$triggers[]        = library_remote_lapse($admin, $schema, 'UPDATE', $poller, $failure);
			unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
			api_device_enable_devices([$device]);
			library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device]), '', 'Enable persists locally');
			library_expect(db_fetch_cell_prepared('SELECT disabled FROM host WHERE id = ?', [$device], '', false, $remote), '', 'Enable reached the collector before it failed');
			library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Reindex propagation reports the ' . $failure . ' collector');

			[$poller, $device] = $collector('Save outage');
			$triggers[]        = library_remote_lapse($admin, $schema, 'INSERT', $poller, $failure);
			library_expect(library_save_device(['id' => $device, 'poller_id' => $poller, 'description' => 'Saved before ' . $failure, 'snmp_version' => 0, 'disabled' => '']), $device, 'Save retains the device identity');
			library_expect(db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$device], '', false, $remote), 'Saved before ' . $failure, 'Device row reached the collector before it failed');
			library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $device]), true, 'Reindex reset reports the ' . $failure . ' collector');
		}

		[$poller, $device] = $collector('Move outage');
		$fail($poller, 'offline');
		library_expect(library_save_device(['id' => $device, 'poller_id' => 1, 'snmp_version' => 0]), $device, 'Device moves away from an offline collector');
		library_expect((int) db_fetch_cell_prepared('SELECT poller_id FROM host WHERE id = ?', [$device]), 1, 'Move persists locally');
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$device], '', false, $remote), 1, 'Offline collector keeps the moved device until a full sync');
		library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $device]), true, 'Move reports the offline previous collector');

		[$poller, $device] = $collector('Purge outage');
		$fail($poller, 'invalid-address');
		unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);
		api_device_purge_from_remote($device, $poller);
		library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM host WHERE id = ?', [$device], '', false, $remote), 1, 'Unreachable collector keeps the device');
		library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Purge reports the unreachable collector');

		$local = library_save_device(['description' => 'Outage destination', 'snmp_version' => 0]);
		library_expect($local > 0, true, 'Local destination device exists');
		$devices[] = $local;

		foreach (array_keys($failures) as $failure) {
			foreach (['move', 'convert'] as $operation) {
				[$poller, $device] = $collector('Data source outage');
				$source            = (int) sql_save(['id' => 0, 'host_id' => $device, 'data_template_id' => 0], 'data_local');
				library_expect($source > 0, true, 'Outage data source exists');
				$sources[] = $source;
				library_expect((int) sql_save(['id' => 0, 'local_data_id' => $source, 'name' => 'Outage source', 'data_source_path' => '<path_rra>/coverage_outage_' . $source . '.rrd', 'rrd_step' => 300], 'data_template_data') > 0, true, 'Outage data source has metadata');
				$fail($poller, $failure);
				unset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]);

				if ($operation === 'move') {
					api_data_source_change_host([$source], $local);
					library_expect((int) db_fetch_cell_prepared('SELECT host_id FROM data_local WHERE id = ?', [$source]), $local, 'Data source moves locally');
				} else {
					$name = 'Outage conversion ' . bin2hex(random_bytes(4));
					data_source_to_data_template($source, $name);
					$converted[] = (int) db_fetch_cell_prepared('SELECT id FROM data_template WHERE name = ?', [$name]);
					library_expect((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM data_local WHERE id = ?', [$source]), 0, 'Converted data source leaves the device');
				}
				library_expect(isset($_SESSION[SESS_MESSAGES]['poller_down_' . $poller]), true, 'Data source ' . $operation . ' reports the ' . $failure . ' collector');
			}
		}
	} finally {
		// Duplicating a data template drops the path field from the shared form structure.
		$GLOBALS['struct_data_source'] = $structure;

		foreach ($triggers as $trigger) {
			$admin->exec('DROP TRIGGER IF EXISTS `' . $schema . '`.`' . $trigger . '`');
		}

		foreach ($converted as $template) {
			db_execute_prepared('DELETE FROM data_template_data WHERE data_template_id = ?', [$template]);
			db_execute_prepared('DELETE FROM data_template WHERE id = ?', [$template]);
		}

		foreach ($sources as $source) {
			db_execute_prepared('DELETE FROM data_template_data WHERE local_data_id = ?', [$source]);
			db_execute_prepared('DELETE FROM data_local WHERE id = ?', [$source]);
		}

		foreach ($devices as $device) {
			db_execute_prepared('UPDATE host SET poller_id = 1 WHERE id = ?', [$device]);
			api_device_remove($device);
		}

		foreach ($templates as $template) {
			foreach (['host_template_snmp_query', 'host_template_graph'] as $table) {
				db_execute_prepared('DELETE FROM ' . $table . ' WHERE host_template_id = ?', [$template]);
			}
			db_execute_prepared('DELETE FROM host_template WHERE id = ?', [$template]);
		}

		foreach ($pollers as $poller) {
			db_execute_prepared('DELETE FROM poller_command WHERE poller_id = ?', [$poller]);
			db_execute_prepared('DELETE FROM poller WHERE id = ?', [$poller]);
		}
		$_SESSION = $session;
	}
}
