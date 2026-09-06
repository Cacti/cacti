<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

function run_cli_options_probe(array $arguments) : array {
	$command = array_merge([PHP_BINARY, dirname(__DIR__, 3) . '/fixtures/CliOptionsProbe.php'], $arguments);
	$pipes   = [];
	$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start CLI options probe.');
	}

	$output = stream_get_contents($pipes[1]);
	$error  = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	return [$status, $output, $error];
}

test('shared CLI parser accepts declared flags and splits values once', function () {
	[$status, $output, $error] = run_cli_options_probe(['--name=a=b', '--force']);

	expect($status)->toBe(0)
		->and($error)->toBe('')
		->and(json_decode($output, true, 512, JSON_THROW_ON_ERROR))->toBe([
			'name'  => 'a=b',
			'force' => true,
		]);
});

test('shared CLI parser accepts a value in the following argument', function () {
	[$status, $output, $error] = run_cli_options_probe(['--name', 'a=b', '--force']);

	expect($status)->toBe(0)
		->and($error)->toBe('')
		->and(json_decode($output, true, 512, JSON_THROW_ON_ERROR))->toBe([
			'name'  => 'a=b',
			'force' => true,
		]);
});

test('shared CLI parser rejects unknown, positional, malformed, and missing arguments', function (array $arguments) {
	[$status, $output] = run_cli_options_probe($arguments);

	expect($status)->toBe(1)
		->and($output)->toContain('ERROR:');
})->with([
	'unknown option'   => [['--other=value']],
	'positional value' => [['name=value']],
	'extra dash'       => [['---name=value']],
	'missing required' => [['--force']],
]);

test('shared CLI help and version requests succeed', function (string $argument, string $expected) {
	[$status, $output, $error] = run_cli_options_probe([$argument]);

	expect($status)->toBe(0)
		->and($error)->toBe('')
		->and($output)->toContain($expected);
})->with([
	'help'    => ['--help', 'probe --name=value'],
	'version' => ['--version', 'Cacti Probe, Version test'],
]);
