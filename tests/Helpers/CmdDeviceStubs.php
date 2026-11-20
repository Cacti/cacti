<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Stand-ins for the database, ping, SNMP and Script Server boundaries of
 * cmd.php's collect_device_data() and ping_and_reindex_check(), shared by the
 * cmd.php log masking unit test and its request-coverage fixture.  Load the
 * cmd.php functions into this namespace with cacti_fixture_source_function().
 *
 * $GLOBALS['cmd_device_reply']   is the Script Server reply.
 * $GLOBALS['cmd_device_reindex'] holds the poller_reindex rows.
 */

namespace CactiCmdDeviceFixture;

$GLOBALS['cmd_device_reply']   = '42';
$GLOBALS['cmd_device_reindex'] = [];

function read_config_option($name, $force = false) {
	return ['spine_log_level' => 2][$name] ?? '';
}

function debug_level(int $host_id, int $level) : int {
	return $level;
}

function exec_poll_php(string $command, bool $using_proc_function, array $pipes, mixed $proc_fd) : string {
	return $GLOBALS['cmd_device_reply'] . "\n";
}

function exec_into_array(string $command) : array {
	return ['1', '2', '3'];
}

function db_fetch_row_prepared($sql, $params = []) {
	return ['hostname' => 'host1', 'ping_port' => 0, 'ping_method' => 0, 'ping_retries' => 1, 'ping_timeout' => 400, 'availability_method' => 0];
}

function db_fetch_assoc_prepared($sql, $params = []) {
	return $GLOBALS['cmd_device_reindex'];
}

function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) {
	return true;
}

function update_host_status(...$arguments) {
}

function update_system_mibs($host_id) {
}

function open_snmp_session($host_id, &$item) {
	return 'session';
}

function cacti_snmp_session_get($session, $oid, $strip_alpha = false) {
	return '42';
}

function cacti_snmp_session_walk($session, $oid) {
	return [['oid' => $oid . '.1', 'value' => '1']];
}

function cacti_snmp_select_uptime(...$arguments) {
	return '42';
}

class Net_Ping {
	public $host = [];

	public $port = 0;

	public function ping($avail_method, $ping_type, $timeout, $retries) {
		return true;
	}
}
