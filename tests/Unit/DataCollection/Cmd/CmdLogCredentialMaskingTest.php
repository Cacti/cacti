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

namespace CactiCmdDeviceFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/Helpers/CmdDeviceStubs.php';

$GLOBALS['cmd_mask_log'] = [];

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['cmd_mask_log'][] = $string;
}

function exec_poll(string $command, int $timeout = 5, array $item = []) : string {
	return $GLOBALS['cmd_device_reply'] . "\n";
}

if (!function_exists(__NAMESPACE__ . '\\collect_device_data')) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/poller.php', 'poller_redact_snmp_secrets', __NAMESPACE__);
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/cmd.php', 'collect_device_data', __NAMESPACE__);
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/cmd.php', 'ping_and_reindex_check', __NAMESPACE__);
}

beforeEach(function () {
	$GLOBALS['cmd_mask_log']           = [];
	$GLOBALS['cmd_device_reindex']     = [];
	$GLOBALS['print_data_to_stdout']   = false;
	$GLOBALS['using_proc_function']    = true;
	$GLOBALS['pipes']                  = [];
	$GLOBALS['cactiphp']               = null;
});

foreach ([POLLER_ACTION_SCRIPT => 'script', POLLER_ACTION_SCRIPT_PHP => 'script server'] as $action => $label) {
	foreach (['U', 'not a number', '42'] as $reply) {
		test("cmd.php masks the credentials when it logs a $label reply of '$reply'", function () use ($action, $reply) {
			$GLOBALS['cmd_device_reply'] = $reply;

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
				->and($log)->toContain('ss_x.php ss_x ')
				->and($log)->toContain($action == POLLER_ACTION_SCRIPT ? '] SCRIPT: /opt' : '] SERVER: /opt');

			if ($reply !== '42') {
				expect($log)->toContain('DS[3] ' . ($action == POLLER_ACTION_SCRIPT ? 'SCRIPT' : 'SERVER') . ': /opt');
			}

			// a bad reply is logged twice, as a warning and with the timing
			expect(substr_count($log, '********'))->toBe($reply === '42' ? 2 : 4);
		});
	}
}

test('cmd.php masks the credentials in every recache log line that names the command', function () {
	$GLOBALS['cmd_device_reply'] = '42';
	$GLOBALS['poller_id']        = 1;
	$GLOBALS['set_spike_kill']   = false;

	$args = ' ' . \cacti_escapeshellarg('c0mm') . ' ' . \cacti_escapeshellarg("zq'xw");

	$GLOBALS['cmd_device_reindex'] = [];

	foreach ([
		POLLER_ACTION_SNMP             => 'RECACHE OID: ',
		POLLER_ACTION_SCRIPT           => 'RECACHE SCRIPT: ',
		POLLER_ACTION_SCRIPT_PHP       => 'RECACHE SERVER: ',
		POLLER_ACTION_SNMP_COUNT       => 'RECACHE OID COUNT: ',
		POLLER_ACTION_SCRIPT_COUNT     => 'RECACHE CMD COUNT: ',
		POLLER_ACTION_SCRIPT_PHP_COUNT => 'RECACHE SERVER COUNT: ',
	] as $action => $label) {
		$GLOBALS['cmd_device_reindex'][] = ['data_query_id' => $action, 'action' => $action, 'op' => '=', 'assert_value' => '42', 'arg1' => '/opt/cacti/scripts/ss_q.php ss_q' . $args];
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

/*
 * CACTI_SERVER_OS is a constant, so the win32 escaping forms are built and
 * masked in a separate process by tests/fixtures/PollerRedactSecretsWin32Probe.php.
 */
test('cmd.php log arguments mask a credential in its win32 escaped forms', function () {
	$secrets = [
		'apostrophe'   => "it's'secret",
		'double quote' => 'say"secret"',
		'backslash'    => 'back\\slash\\secret',
		'space'        => 'secret with spaces',
		'cmd metachar' => 'a&b|c^d<e>(f)%g',
		'percent'      => 'pa%ss%word',
	];

	$root    = dirname(__DIR__, 4);
	$process = proc_open(
		[PHP_BINARY, $root . '/tests/fixtures/PollerRedactSecretsWin32Probe.php'],
		[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
		$pipes,
		$root
	);

	expect(is_resource($process))->toBeTrue();

	fwrite($pipes[0], json_encode($secrets));
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	expect(proc_close($process))->toBe(0, $stderr);

	$masked = json_decode($stdout, true);

	expect($masked)->toBeArray()->toHaveCount(18);

	foreach ($secrets as $label => $secret) {
		foreach (['win32', 'win32 cmd', 'win32 cmd strip'] as $form) {
			$command = $masked["$label/$form"];

			// the credential argument and the colon separated SNMPv3 field
			expect(substr_count($command, '********'))->toBe(2, "$label/$form: $command")
				->and($command)->toContain('"host1"')
				->and($command)->toContain(':SHA');

			// no word of the credential survives, whatever escaping wrapped it
			foreach (preg_split('/[^A-Za-z]+/', $secret, -1, PREG_SPLIT_NO_EMPTY) as $word) {
				if (strlen($word) >= 4) {
					expect($command)->not->toContain($word, "$label/$form: $command");
				}
			}
		}
	}
});
