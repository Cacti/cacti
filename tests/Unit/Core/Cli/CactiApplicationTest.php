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
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\ApplicationTester;

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
