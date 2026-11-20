<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/FakeMySQLPDO.php';

/**
 * Run $body with the default database session pointing at an in-memory copy
 * of the MikroTik plugin tables, restoring the prior session afterwards.
 *
 * db_table_exists() caches per connection key, and the MikroTik scripts use
 * the default one, so every test touching these scripts must see the same
 * set of tables.  All of them exist here.
 *
 * @param callable $body The test body
 *
 * @return void
 */
function mikrotik_script_database(callable $body): void {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$session = "$database_hostname:$database_port:$database_default";
	$prior   = $database_sessions[$session] ?? null;
	$conn    = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE host (id INTEGER PRIMARY KEY, hostname TEXT, snmp_community TEXT, snmp_version INTEGER,
		snmp_username TEXT, snmp_password TEXT, snmp_auth_protocol TEXT, snmp_priv_passphrase TEXT, snmp_priv_protocol TEXT,
		snmp_context TEXT, snmp_port INTEGER, snmp_timeout INTEGER, max_oids INTEGER, snmp_engine_id TEXT)');
	$conn->exec('CREATE TABLE plugin_mikrotik_processor (host_id INTEGER, `index` INTEGER, `load` INTEGER)');
	$conn->exec('CREATE TABLE plugin_mikrotik_system (host_id INTEGER, uptime INTEGER, users INTEGER, processes INTEGER,
		memSize INTEGER, memUsed REAL, diskSize INTEGER, diskUsed REAL)');
	$conn->exec('CREATE TABLE plugin_mikrotik_system_health (host_id INTEGER, HlVoltage REAL, HlCpuTemperature REAL,
		HlProcessorTemperature REAL, HlTemperature REAL, HlTwelveVoltage REAL)');
	$conn->exec('CREATE TABLE plugin_mikrotik_health_sensors (host_id INTEGER, name TEXT, value REAL, present INTEGER)');
	$conn->exec('CREATE TABLE plugin_mikrotik_interfaces (host_id INTEGER, `index` INTEGER, name TEXT, curRxBytes INTEGER,
		curTxBytes INTEGER, present INTEGER)');
	$conn->exec('CREATE TABLE plugin_mikrotik_queues (host_id INTEGER, name TEXT, iFace TEXT, srcAddr TEXT, srcMask TEXT,
		dstAddr TEXT, dstMask TEXT, curBytesIn INTEGER, curBytesOut INTEGER, curPacketsIn INTEGER, curPacketsOut INTEGER,
		curQueuesIn INTEGER, curQueuesOut INTEGER, curDroppedIn INTEGER, curDroppedOut INTEGER, last_seen TEXT)');
	$conn->exec('CREATE TABLE plugin_mikrotik_trees (host_id INTEGER, name TEXT, flow TEXT, parentIndex TEXT,
		curHCBytes INTEGER, curPackets INTEGER)');
	$conn->exec('CREATE TABLE plugin_mikrotik_users (host_id INTEGER, name TEXT, domain TEXT, ip TEXT, mac TEXT,
		curBytesIn INTEGER, curBytesOut INTEGER, curPacketsIn INTEGER, curPacketsOut INTEGER, connectTime INTEGER,
		bytesIn INTEGER, bytesOut INTEGER, packetsIn INTEGER, packetsOut INTEGER)');
	$conn->exec('CREATE TABLE plugin_mikrotik_wireless_aps (host_id INTEGER, `index` INTEGER, last_seen TEXT)');
	$conn->exec('CREATE TABLE plugin_mikrotik_wireless_registrations (host_id INTEGER, `index` TEXT, TxBytes INTEGER,
		RxStrengthChl INTEGER, Uptime TEXT, last_seen TEXT, present INTEGER)');

	$now = gmdate('Y-m-d H:i:s');

	$conn->exec('INSERT INTO plugin_mikrotik_processor VALUES (1, 1, 10), (1, 2, 30)');
	$conn->exec('INSERT INTO plugin_mikrotik_system VALUES (1, 8640000, 4, 120, 1000, 25, 2000, 50), (2, 0, 0, 0, 0, 0, 0, 0)');
	$conn->exec('INSERT INTO plugin_mikrotik_system_health VALUES (1, 24.1, 61, NULL, 40, NULL)');
	$conn->exec("INSERT INTO plugin_mikrotik_health_sensors VALUES (1, 'psu1-voltage', 23.9, 1), (1, 'cpu-temperature', 48, 1), (1, 'temperature', 37, 0)");
	$conn->exec("INSERT INTO plugin_mikrotik_interfaces VALUES (1, 3, 'ether1', 0, 0, 0), (1, 3, 'wan uplink', 1500, 900, 1), (1, 7, 'ether2', 10, 20, 1), (1, 9, '', 0, 0, 1)");
	$conn->exec("INSERT INTO plugin_mikrotik_queues VALUES (1, 'guest wifi', 'bridge', '10.0.0.0', '24', '0.0.0.0', '0', 100, 200, 3, 4, 0, 0, 1, 2, '$now')");
	$conn->exec("INSERT INTO plugin_mikrotik_trees VALUES (1, 'customer tree', 'up', 'global', 4096, 8), (1, '<down>', 'down', 'global', 2048, 4)");
	$conn->exec("INSERT INTO plugin_mikrotik_users VALUES (1, 'alice', 'example.net', '10.0.0.9', 'AA:BB', 1, 2, 3, 4, 50, 500, 600, 7, 8), (1, '', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0)");
	$conn->exec("INSERT INTO plugin_mikrotik_wireless_aps VALUES (1, 1, '$now')");
	$conn->exec("INSERT INTO plugin_mikrotik_wireless_registrations VALUES (1, 'AA:BB:CC:DD:EE:FF', 5000, -61, '1:2:3:4', '$now', 1), (1, '11:22:33:44:55:66', 10, -70, '12050', '$now', 1), (2, 'DE:AD:BE:EF:00:01', 0, -80, '0', '$now', 0)");

	$database_sessions[$session] = $conn;

	try {
		$body();
	} finally {
		if ($prior === null) {
			unset($database_sessions[$session]);
		} else {
			$database_sessions[$session] = $prior;
		}
	}
}
