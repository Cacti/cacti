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

/*
 * cmd.php logs each reindex command it runs while checking a device for
 * recache, and a data query script or Script Server command carries the
 * device SNMP credentials.  ping_and_reindex_check() and the masking helper
 * are loaded unchanged into a fixture namespace; only logging, configuration,
 * the database, ping, SNMP and the command execution boundary are replaced.
 */

namespace CactiCmdRecacheMaskingFixture;

require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$GLOBALS['cmd_recache_log']     = array();
$GLOBALS['cmd_recache_reindex'] = array();

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['cmd_recache_log'][] = $string;
}

function read_config_option($name) {
	return '';
}

function debug_level($host_id, $level) {
	return $level;
}

function exec_poll($command) {
	return "42\n";
}

function exec_poll_php($command, $using_proc_function, $pipes, $proc_fd) {
	return "42\n";
}

function exec_into_array($command) {
	return array('1', '2', '3');
}

function db_fetch_row_prepared($sql, $params = array()) {
	return array('hostname' => 'host1', 'ping_port' => 0, 'ping_method' => 0, 'ping_retries' => 1, 'ping_timeout' => 400, 'availability_method' => 0);
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	return $GLOBALS['cmd_recache_reindex'];
}

function db_execute_prepared($sql, $params = array(), $log = true, $db_conn = false) {
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
	return array(array('oid' => $oid . '.1', 'value' => '1'));
}

function cacti_snmp_select_uptime(...$arguments) {
	return '42';
}

class Net_Ping {
	public $host = array();

	public $port = 0;

	public function ping($avail_method, $ping_type, $timeout, $retries) {
		return true;
	}
}

if (!function_exists(__NAMESPACE__ . '\\ping_and_reindex_check')) {
	$root = dirname(__DIR__, 3);

	\cacti_fixture_source_function($root . '/lib/poller.php', 'poller_redact_snmp_secrets', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/cmd.php', 'ping_and_reindex_check', __NAMESPACE__);
}

test('cmd.php masks the credentials in every recache log line that names the command', function () {
	$GLOBALS['cmd_recache_log']      = array();
	$GLOBALS['poller_id']            = 1;
	$GLOBALS['print_data_to_stdout'] = false;
	$GLOBALS['set_spike_kill']       = false;
	$GLOBALS['using_proc_function']  = true;
	$GLOBALS['pipes']                = array();
	$GLOBALS['cactiphp']             = null;

	$saved_os = $GLOBALS['config']['cacti_server_os'] ?? null;

	$GLOBALS['config']['cacti_server_os'] = 'unix';

	$args = ' ' . \cacti_escapeshellarg('c0mm') . ' ' . \cacti_escapeshellarg("zq'xw");

	$GLOBALS['config']['cacti_server_os'] = $saved_os;

	$labels = array(
		POLLER_ACTION_SNMP             => 'RECACHE OID: ',
		POLLER_ACTION_SCRIPT           => 'RECACHE SCRIPT: ',
		POLLER_ACTION_SCRIPT_PHP       => 'RECACHE SERVER: ',
		POLLER_ACTION_SNMP_COUNT       => 'RECACHE OID COUNT: ',
		POLLER_ACTION_SCRIPT_COUNT     => 'RECACHE CMD COUNT: ',
		POLLER_ACTION_SCRIPT_PHP_COUNT => 'RECACHE SERVER COUNT: ',
	);

	$GLOBALS['cmd_recache_reindex'] = array();

	foreach ($labels as $action => $label) {
		$GLOBALS['cmd_recache_reindex'][] = array('data_query_id' => $action, 'action' => $action, 'op' => '=', 'assert_value' => '42', 'arg1' => '/opt/cacti/scripts/ss_q.php ss_q' . $args);
	}

	$item = array('host_id' => 7, 'hostname' => 'host1', 'snmp_community' => 'c0mm', 'snmp_password' => "zq'xw", 'snmp_priv_passphrase' => '');

	expect(ping_and_reindex_check($item, false))->toBeFalse();

	$lines = array_values(array_filter($GLOBALS['cmd_recache_log'], function ($line) {
		return strpos($line, 'ss_q.php') !== false;
	}));
	$log = implode("\n", $lines);

	expect($lines)->toHaveCount(6);
	expect($log)->not->toContain('c0mm');
	expect($log)->not->toContain('zq');
	expect($log)->not->toContain('xw');
	expect(substr_count($log, '********'))->toBe(12);

	foreach ($labels as $label) {
		expect($log)->toContain($label . '/opt/cacti/scripts/ss_q.php ss_q ');
	}
});
