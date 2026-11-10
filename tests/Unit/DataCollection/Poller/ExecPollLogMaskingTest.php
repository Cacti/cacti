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
 * issue#8676: when a script exits with an error, exec_poll() and
 * exec_with_timeout() log the command, its output and its stderr, and a
 * script data source command carries the device SNMP credentials.  The
 * production functions are loaded unchanged into a fixture namespace; only
 * logging is replaced, and real processes are run.
 */

namespace CactiExecPollLogMaskingFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

if (!defined('CACTI_SERVER_OS')) {
	define('CACTI_SERVER_OS', 'unix');
}

$GLOBALS['exec_mask_log'] = [];

function cacti_log($string, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['exec_mask_log'][] = $string;
}

if (!function_exists(__NAMESPACE__ . '\\exec_poll')) {
	foreach (['exec_poll', 'exec_with_timeout', 'poller_redact_snmp_secrets'] as $function) {
		\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/poller.php', $function, __NAMESPACE__);
	}
}

/**
 * A command that prints the credentials to stdout and stderr and fails.
 *
 * @return string The shell command.
 */
function exec_mask_failing_command() : string {
	$script = 'fwrite(STDOUT, "out " . $argv[1] . "\n"); fwrite(STDERR, "err " . $argv[2] . "\n"); exit(3);';

	return PHP_BINARY . ' -r ' . escapeshellarg($script) . ' ' . escapeshellarg('c0mmunity') . ' ' . escapeshellarg("auth'pass");
}

beforeEach(function () {
	$GLOBALS['exec_mask_log'] = [];
});

test('a failing script logs its command, output and stderr with the device credentials masked', function () {
	$item = ['snmp_community' => 'c0mmunity', 'snmp_password' => "auth'pass", 'snmp_priv_passphrase' => ''];

	expect(exec_poll(exec_mask_failing_command(), 5, $item))->toBe('out c0mmunity');

	$log = implode("\n", $GLOBALS['exec_mask_log']);

	expect($log)->toContain('ErrorCode:3')
		->and($log)->toContain('exited with code 3')
		->and($log)->toContain('out ********')
		->and($log)->toContain('err ********')
		->and($log)->not->toContain('c0mmunity')
		->and($log)->not->toContain('auth');
});

test('exec_with_timeout logs the command as given when the caller passes no mask', function () {
	$output = [];
	$return = 0;

	exec_with_timeout(exec_mask_failing_command(), $output, $return, 5);

	expect($return)->toBe(3)
		->and(implode("\n", $GLOBALS['exec_mask_log']))->toContain('c0mmunity');
});

test('every script data source caller passes the poller item to exec_poll', function () {
	$root = dirname(__DIR__, 4);

	expect(file_get_contents($root . '/cmd.php'))->toContain('exec_poll($item[\'arg1\'], $script_timeout, $item)')
		->and(file_get_contents($root . '/cmd.php'))->toContain('exec_poll($index_item[\'arg1\'], $script_timeout, $item)')
		->and(file_get_contents($root . '/cmd_realtime.php'))->toContain('exec_poll($item[\'arg1\'], 2, $item)')
		->and(file_get_contents($root . '/remote_agent.php'))->toContain('exec_poll($item[\'arg1\'], 5, $item)');
});
