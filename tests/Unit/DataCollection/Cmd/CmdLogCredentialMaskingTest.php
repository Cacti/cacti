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
 * commands carry the device SNMP credentials.  collect_device_data() and the
 * masking helper are loaded unchanged into a fixture namespace; only logging,
 * configuration and the command execution boundary are replaced.
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

if (!function_exists(__NAMESPACE__ . '\\collect_device_data')) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/poller.php', 'poller_redact_snmp_secrets', __NAMESPACE__);
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/cmd.php', 'collect_device_data', __NAMESPACE__);
}

beforeEach(function () {
	$GLOBALS['cmd_mask_log']         = [];
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
