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
 * cmd.php logs the script and Script Server commands it runs, and those
 * commands carry the device SNMP credentials, as do the recache commands it
 * logs while checking a device for reindexing.  collect_device_data(),
 * ping_and_reindex_check() and the masking helper are loaded unchanged into a
 * fixture namespace; only logging, configuration, the database, ping, SNMP and
 * the command execution boundary are replaced.
 */

namespace CactiCmdLogMaskingFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$GLOBALS['cmd_mask_log']   = [];
$GLOBALS['cmd_mask_reply'] = '42';

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['cmd_mask_log'][] = $string;
}

function read_config_option($name) {
	return ['spine_log_level' => 2][$name] ?? '';
}

function debug_level(int $host_id, int $level) : int {
	return $level;
}

function exec_poll(string $command, int $timeout = 5) : string {
	return $GLOBALS['cmd_mask_reply'] . "\n";
}

function exec_poll_php(string $command, bool $using_proc_function, array $pipes, mixed $proc_fd) : string {
	return $GLOBALS['cmd_mask_reply'] . "\n";
}

function exec_into_array(string $command) : array {
	return ['1', '2', '3'];
}

function db_fetch_row_prepared($sql, $params = []) {
	return ['hostname' => 'host1', 'ping_port' => 0, 'ping_method' => 0, 'ping_retries' => 1, 'ping_timeout' => 400, 'availability_method' => 0];
}

function db_fetch_assoc_prepared($sql, $params = []) {
	return $GLOBALS['cmd_mask_reindex'];
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

if (!function_exists(__NAMESPACE__ . '\\collect_device_data')) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/poller.php', 'poller_redact_snmp_secrets', __NAMESPACE__);
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/cmd.php', 'collect_device_data', __NAMESPACE__);
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/cmd.php', 'ping_and_reindex_check', __NAMESPACE__);
}

beforeEach(function () {
	$GLOBALS['cmd_mask_log']         = [];
	$GLOBALS['cmd_mask_reindex']     = [];
	$GLOBALS['print_data_to_stdout'] = false;
	$GLOBALS['using_proc_function']  = true;
	$GLOBALS['pipes']                = [];
	$GLOBALS['cactiphp']             = null;
});

foreach ([POLLER_ACTION_SCRIPT => 'script', POLLER_ACTION_SCRIPT_PHP => 'script server'] as $action => $label) {
	foreach (['U', 'not a number', '42'] as $reply) {
		test("cmd.php masks the credentials when it logs a $label reply of '$reply'", function () use ($action, $reply) {
			$GLOBALS['cmd_mask_reply'] = $reply;

			$item = [
				'local_data_id'        => 3,
				'host_id'              => 7,
				'action'               => $action,
				'snmp_community'       => 'c0mm',
				'snmp_password'        => "zq'xw",
				'snmp_priv_passphrase' => '',
				'arg1'                 => '/opt/cacti/scripts/ss_x.php ss_x ' . \cacti_escapeshellarg('c0mm') . ' ' . \cacti_escapeshellarg("zq'xw"),
			];
			$error_ds = [];

			collect_device_data($item, $error_ds, 25);

			$log = implode("\n", $GLOBALS['cmd_mask_log']);

			expect($log)->not->toContain('c0mm')
				->and($log)->not->toContain('zq')
				->and($log)->not->toContain('xw')
				->and($log)->toContain('ss_x.php ss_x ');

			// a bad reply is logged twice, as a warning and with the timing
			expect(substr_count($log, '********'))->toBe($reply === '42' ? 2 : 4);
		});
	}
}

test('cmd.php masks the credentials in every recache log line that names the command', function () {
	$GLOBALS['cmd_mask_reply'] = '42';
	$GLOBALS['poller_id']      = 1;
	$GLOBALS['set_spike_kill'] = false;

	$args = ' ' . \cacti_escapeshellarg('c0mm') . ' ' . \cacti_escapeshellarg("zq'xw");

	$GLOBALS['cmd_mask_reindex'] = [];

	foreach ([
		POLLER_ACTION_SNMP             => 'RECACHE OID: ',
		POLLER_ACTION_SCRIPT           => 'RECACHE SCRIPT: ',
		POLLER_ACTION_SCRIPT_PHP       => 'RECACHE SERVER: ',
		POLLER_ACTION_SNMP_COUNT       => 'RECACHE OID COUNT: ',
		POLLER_ACTION_SCRIPT_COUNT     => 'RECACHE CMD COUNT: ',
		POLLER_ACTION_SCRIPT_PHP_COUNT => 'RECACHE SERVER COUNT: ',
	] as $action => $label) {
		$GLOBALS['cmd_mask_reindex'][] = ['data_query_id' => $action, 'action' => $action, 'op' => '=', 'assert_value' => '42', 'arg1' => '/opt/cacti/scripts/ss_q.php ss_q' . $args];
	}

	$item = ['host_id' => 7, 'hostname' => 'host1', 'snmp_community' => 'c0mm', 'snmp_password' => "zq'xw", 'snmp_priv_passphrase' => ''];

	expect(ping_and_reindex_check($item, false, 25))->toBeFalse();

	$lines = array_values(array_filter($GLOBALS['cmd_mask_log'], function ($line) {
		return strpos($line, 'ss_q.php') !== false;
	}));
	$log = implode("\n", $lines);

	expect($lines)->toHaveCount(6)
		->and($log)->not->toContain('c0mm')
		->and($log)->not->toContain('zq')
		->and($log)->not->toContain('xw')
		->and(substr_count($log, '********'))->toBe(12);

	foreach (['RECACHE OID: ', 'RECACHE SCRIPT: ', 'RECACHE SERVER: ', 'RECACHE OID COUNT: ', 'RECACHE CMD COUNT: ', 'RECACHE SERVER COUNT: '] as $label) {
		expect($log)->toContain($label . '/opt/cacti/scripts/ss_q.php ss_q ');
	}
});

test('cmd.php log arguments mask the item credentials', function () {
	$item = ['snmp_community' => 'pub', 'snmp_password' => 'pubpass', 'snmp_priv_passphrase' => ''];

	expect(poller_redact_snmp_secrets("/x/ss.php f 'h' 'pubpass' 'pub'", $item))->toBe("/x/ss.php f 'h' '********' '********'")
		->and(poller_redact_snmp_secrets('no secrets here', []))->toBe('no secrets here');
});

test('cmd.php log arguments mask a credential in its escaped forms', function (string $secret) {
	$item    = ['snmp_community' => '', 'snmp_password' => $secret, 'snmp_priv_passphrase' => ''];
	$command = implode(' ', ['f', \cacti_escapeshellarg('host1'), \cacti_escapeshellarg($secret), \cacti_escapeshellarg_cmd($secret, true, true)]);
	$masked  = poller_redact_snmp_secrets($command, $item);

	expect(substr_count($masked, '********'))->toBe(2)
		->and($masked)->toContain('host1');

	// no word of the credential survives, whatever escaping wrapped it
	foreach (preg_split('/[^A-Za-z]+/', $secret, -1, PREG_SPLIT_NO_EMPTY) as $word) {
		if (strlen($word) >= 4) {
			expect($masked)->not->toContain($word);
		}
	}
})->with([
	'apostrophe'   => ["it's'secret"],
	'double quote' => ['say"secret"'],
	'backslash'    => ['back\\slash\\secret'],
	'space'        => ['secret with spaces'],
	'cmd metachar' => ['a&b|c^d<e>(f)%g'],
]);

test('cmd.php log arguments mask a credential that contains another credential whole', function () {
	$item = ['snmp_community' => 'pub', 'snmp_password' => 'pubpubpass', 'snmp_priv_passphrase' => ''];

	expect(poller_redact_snmp_secrets("f 'pubpubpass' 'pub'", $item))->toBe("f '********' '********'");
});

test('cmd.php log arguments ignore empty credentials', function () {
	$item = ['snmp_community' => '', 'snmp_password' => '', 'snmp_priv_passphrase' => '0'];

	expect(poller_redact_snmp_secrets("f '' 'x0y'", $item))->toBe("f '' 'x********y'")
		->and(poller_redact_snmp_secrets("f '' 'xy'", ['snmp_community' => '']))->toBe("f '' 'xy'");
});
