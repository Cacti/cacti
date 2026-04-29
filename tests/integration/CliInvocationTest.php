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
 * End-to-end checks for the CLI shims. Runs each entrypoint under a fresh PHP
 * process via proc_open and asserts on argv parsing, exit codes, and shim
 * wiring (autoload.php -> CactiApplication -> Command). The Cacti bootstrap
 * (cli_check.php) is intentionally skipped by setting CACTI_PHP_TESTING=1 in
 * the child env because these tests run without a database; the source-scan
 * test in tests/Unit/CliInvocationTest.php covers that initialize() still
 * requires cli_check.php on the production code path.
 */

const CLI_INTEGRATION_REPO_ROOT = __DIR__ . '/../..';

/**
 * @return array{stdout:string,stderr:string,exit:int}
 */
function runCli(string $script, array $args = []) : array {
	$php  = PHP_BINARY;
	$path = CLI_INTEGRATION_REPO_ROOT . '/' . ltrim($script, '/');

	$cmd = escapeshellcmd($php) . ' ' . escapeshellarg($path);

	foreach ($args as $arg) {
		$cmd .= ' ' . escapeshellarg($arg);
	}

	$descriptors = [
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w'],
	];

	// Pass CACTI_PHP_TESTING=1 to the child so CactiCommand::initialize()
	// skips the cli_check.php / Cacti bootstrap path. The bootstrap is not
	// what these integration tests are exercising; we want to verify the
	// argv-validation surface and the shim wiring without spinning up a DB.
	$env = $_ENV + getenv() + ['CACTI_PHP_TESTING' => '1'];

	$proc = proc_open($cmd, $descriptors, $pipes, null, $env);

	if (!is_resource($proc)) {
		return ['stdout' => '', 'stderr' => 'failed to spawn', 'exit' => 127];
	}

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$exit = proc_close($proc);

	return [
		'stdout' => (string) $stdout,
		'stderr' => (string) $stderr,
		'exit'   => $exit,
	];
}

beforeEach(function () {
	if (!file_exists(CLI_INTEGRATION_REPO_ROOT . '/include/vendor/autoload.php')
		|| !class_exists(\Symfony\Component\Console\Application::class)) {
		$this->markTestSkipped('symfony/console not installed; run composer install');
	}
});

test('cli/cacti.php list prints both registered commands', function () {
	$result = runCli('cli/cacti.php', ['list']);

	expect($result['stdout'])->toContain('realtime');
	expect($result['stdout'])->toContain('splice-rrd');
});

test('cmd_realtime.php --help exits 0 from the legacy path', function () {
	$result = runCli('cmd_realtime.php', ['--help']);

	expect($result['exit'])->toBe(0);
	expect($result['stdout'])->toContain('poller-id');
});

test('cli/splice_rrd.php --help exits 0 from the legacy path', function () {
	$result = runCli('cli/splice_rrd.php', ['--help']);

	expect($result['exit'])->toBe(0);
	expect($result['stdout'])->toContain('--oldrrd');
});

test('cmd_realtime.php with garbage poller-id exits non-zero without fatal', function () {
	$result = runCli('cmd_realtime.php', ['notanumber', '5', '5']);

	expect($result['exit'])->not->toBe(0);
	// PHP fatals print "Fatal error" or "Stack trace"; Symfony surfaces a clean message.
	$combined = $result['stdout'] . $result['stderr'];
	expect($combined)->not->toContain('Fatal error');
});

test('cli/splice_rrd.php with shell metacharacter exits non-zero without fatal', function () {
	$result = runCli('cli/splice_rrd.php', ['--oldrrd=' . sys_get_temp_dir() . '/foo;rm.rrd', '--newrrd=' . sys_get_temp_dir() . '/n.rrd']);

	expect($result['exit'])->not->toBe(0);
	$combined = $result['stdout'] . $result['stderr'];
	expect($combined)->not->toContain('Fatal error');
});

test('cli/cacti.php with unknown subcommand exits non-zero', function () {
	$result = runCli('cli/cacti.php', ['this-command-does-not-exist']);

	expect($result['exit'])->not->toBe(0);
});
