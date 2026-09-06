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

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

$orb_available = static function (): bool {
	return (new ExecutableFinder())->find('orb') !== null;
};

$run_orb = static function (array $arguments): array {
	$process = new Process(array_merge(['orb'], $arguments));
	$process->setTimeout(30);

	try {
		$process->run();
	} catch (ProcessTimedOutException $e) {
		return [124, $e->getMessage()];
	}

	return [$process->getExitCode(), $process->getOutput() . $process->getErrorOutput()];
};

it('has all required PHP extensions in the Orb machine', function () use ($orb_available, $run_orb) {
	if (!$orb_available()) {
		test()->markTestSkipped('orb CLI not available');
	}

	[$probeStatus] = $run_orb(['true']);

	if ($probeStatus !== 0) {
		test()->markTestSkipped('orb CLI is installed but no usable machine is available');
	}

	$required_exts = [
		'gd', 'gmp', 'intl', 'ldap', 'mbstring', 'mysqli',
		'pdo_mysql', 'snmp', 'pcntl', 'posix', 'sockets',
		'xml', 'dom', 'sqlite3', 'pdo_sqlite'
	];

	foreach ($required_exts as $ext) {
		[$status, $output] = $run_orb(['php', '-r', "exit(extension_loaded('$ext') ? 0 : 1);"]);

		expect($status)->toBe(0, "Missing required Orb PHP extension: $ext\n$output");
	}
});

it('can run a Cacti CLI command in the Orb machine', function () use ($orb_available, $run_orb) {
	if (!$orb_available()) {
		test()->markTestSkipped('orb CLI not available');
	}

	[$probeStatus] = $run_orb(['true']);

	if ($probeStatus !== 0) {
		test()->markTestSkipped('orb CLI is installed but no usable machine is available');
	}

	[$status, $output] = $run_orb(['php', 'cli/check_cli_version.sh']);

	expect($status)->toBe(0, $output)
		->and($output)->toContain('Cacti');
});
