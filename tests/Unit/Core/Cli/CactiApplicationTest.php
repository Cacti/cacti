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

declare(strict_types = 1);

use Cacti\Console\CactiApplication;
use Cacti\Console\Command\LegacyScriptCommand;
use Cacti\Console\Input\RawArgvInput;
use Cacti\Console\LegacyCommandMap;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Process\Process;

it('registers every executable legacy CLI script exactly once', function (): void {
	$root    = dirname(__DIR__, 4);
	$scripts = array_map(
		static fn (string $path): string => basename($path, '.php'),
		glob($root . '/cli/*.php') ?: []
	);
	$scripts = array_values(array_diff($scripts, ['index']));
	sort($scripts);

	$mapped = array_keys(LegacyCommandMap::commands());
	sort($mapped);

	expect($mapped)->toBe($scripts)
		->and(array_unique(array_values(LegacyCommandMap::commands())))
		->toHaveCount(count($mapped));
});

it('lists commands without bootstrapping the database', function (): void {
	$root        = dirname(__DIR__, 4);
	$application = new CactiApplication($root);
	$application->setAutoExit(false);
	$tester = new ApplicationTester($application);

	$status   = $tester->run(['command' => 'list', '--format' => 'json']);
	$commands = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['commands'];
	$names    = array_column($commands, 'name');

	expect($status)->toBe(0)
		->and($names)->toContain('device:add', 'database:audit', 'poller:reindex', 'rrd:resize');
});

it('reports an unknown version when the version file is unavailable', function (): void {
	$application = new CactiApplication('/path/that/does/not/exist');

	expect($application->getVersion())->toBe('unknown');
});

it('forwards raw legacy arguments and preserves the exit status', function (): void {
	$root        = dirname(__DIR__, 3) . '/fixtures/Console';
	$application = new Application('test');
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$application->add(new LegacyScriptCommand('probe:run', 'probe', $root));
	$input  = new RawArgvInput(['bin/cacti', 'probe:run', '--arbitrary=value', '-x', 'plain']);
	$output = new BufferedOutput();

	$status = $application->run($input, $output);

	expect($status)->toBe(23)
		->and($output->fetch())->toBe('["--arbitrary=value","-x","plain"]probe-error');
});

it('routes child stderr separately for console outputs and accepts aliases', function (): void {
	$root        = dirname(__DIR__, 3) . '/fixtures/Console';
	$application = new Application('test');
	$application->setAutoExit(false);
	$application->setCatchExceptions(false);
	$application->add(new LegacyScriptCommand('probe:run', 'probe', $root));
	$output      = new ConsoleOutput(decorated: false);
	$errorOutput = new BufferedOutput();
	$output->setErrorOutput($errorOutput);

	$status = $application->run(
		new RawArgvInput(['bin/cacti', 'probe', 'alias-value']),
		$output
	);

	expect($status)->toBe(23)
		->and($errorOutput->fetch())->toBe('probe-error');
});

it('rejects parsed inputs that cannot preserve the raw argument vector', function (): void {
	$command = new LegacyScriptCommand('probe:run', 'probe', dirname(__DIR__, 3) . '/fixtures/Console');

	expect(fn () => $command->run(new ArrayInput([]), new BufferedOutput()))
		->toThrow(LogicException::class, 'Legacy commands require RawArgvInput.');
});

it('returns no forwarded arguments when no command token is present', function (): void {
	$input = new RawArgvInput(['bin/cacti', '--version']);

	expect($input->argumentsAfterCommand(['probe:run', 'probe']))->toBe([]);
});

it('uses the server argument vector when one is not supplied', function (): void {
	$original        = $_SERVER['argv'] ?? null;
	$_SERVER['argv'] = ['bin/cacti', 'probe:run', 'server-value'];

	try {
		$input = new RawArgvInput();
		expect($input->argumentsAfterCommand(['probe:run']))->toBe(['server-value']);
	} finally {
		if ($original === null) {
			unset($_SERVER['argv']);
		} else {
			$_SERVER['argv'] = $original;
		}
	}
});

it('runs the installed CLI entry point without application bootstrap', function (): void {
	$root    = dirname(__DIR__, 4);
	$process = new Process([PHP_BINARY, $root . '/bin/cacti', 'list', '--raw']);

	expect($process->run())->toBe(0)
		->and($process->getOutput())->toContain('device:add')
		->and($process->getOutput())->toContain('system:version');
});

it('fails clearly when the CLI entry point has no installed dependencies', function (): void {
	$fixture = sys_get_temp_dir() . '/cacti-cli-no-deps-' . bin2hex(random_bytes(8));
	mkdir($fixture . '/bin', 0777, true);
	copy(dirname(__DIR__, 4) . '/bin/cacti', $fixture . '/bin/cacti');
	$process = new Process([PHP_BINARY, $fixture . '/bin/cacti']);

	expect($process->run())->toBe(1)
		->and($process->getErrorOutput())->toContain('Cacti dependencies are not installed');
});
