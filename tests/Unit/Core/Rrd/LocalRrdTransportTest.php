<?php
declare(strict_types = 1);
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

use Cacti\Rrd\LocalRrdTransport;
use Cacti\Rrd\RrdCommand;

$rrdRoot = dirname(__DIR__, 4) . '/lib/Rrd';

require_once "$rrdRoot/RrdCommand.php";
require_once "$rrdRoot/RrdProcessResult.php";
require_once "$rrdRoot/RrdTransport.php";
require_once "$rrdRoot/RrdTransportException.php";
require_once "$rrdRoot/LocalRrdTransport.php";

test('local transport captures stdout stderr and exit status', function () {
	$transport = new LocalRrdTransport(PHP_BINARY);
	$command   = new RrdCommand('-r', [
		'fwrite(STDOUT, $argv[1]); fwrite(STDERR, $argv[2]); exit(7);',
		'standard output',
		'standard error',
	]);

	$result = $transport->execute($command);

	expect($result->stdout)->toBe('standard output')
		->and($result->stderr)->toBe('standard error')
		->and($result->exitCode)->toBe(7)
		->and($result->succeeded())->toBeFalse()
		->and($result->outputWithErrors())->toBe('standard outputstandard error');
});

test('local transport passes metacharacters as data without a shell', function () {
	$payload   = 'value; printf injected | $(whoami)';
	$transport = new LocalRrdTransport(PHP_BINARY);
	$command   = new RrdCommand('-r', ['fwrite(STDOUT, $argv[1]);', $payload]);

	$result = $transport->execute($command);

	expect($result->succeeded())->toBeTrue()
		->and($result->stdout)->toBe($payload)
		->and($result->stderr)->toBe('');
});

test('local transport executes a binary whose path contains spaces', function () {
	$binary = tempnam(sys_get_temp_dir(), 'rrd tool ');
	unlink($binary);

	try {
		expect(symlink(PHP_BINARY, $binary))->toBeTrue();
		$result = (new LocalRrdTransport($binary))->execute(new RrdCommand('-r', ['echo "ok";']));
		expect($result->succeeded())->toBeTrue()->and($result->stdout)->toBe('ok');
	} finally {
		if (is_link($binary)) {
			unlink($binary);
		}
	}
})->skip(PHP_OS_FAMILY === 'Windows', 'Symlink creation requires privileges on Windows.');

test('rrd updates and fetches use the structured command path', function (string $name, string $next, string $builder, string $legacy) {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/rrd.php');
	$start  = strpos($source, "function $name(");
	$end    = strpos($source, "function $next(", $start);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);

	expect($function)->toContain($builder)
		->not->toContain($legacy);
})->with([
	'update' => ['rrdtool_function_update', 'rrdtool_function_tune', 'new \\Cacti\\Rrd\\DataSource\\UpdateCommandBuilder()', "rrdtool_execute('update '"],
	'fetch'  => ['rrdtool_function_fetch', 'rrd_function_process_graph_options', 'new \\Cacti\\Rrd\\DataSource\\FetchCommandBuilder()', "\$cmd_line = 'fetch '"],
]);

test('local transport wraps process startup errors', function () {
	$transport = new LocalRrdTransport("rrdtool\n--daemon");

	expect(fn () => $transport->execute(new RrdCommand('info', ['/tmp/test.rrd'])))
		->toThrow(\Cacti\Rrd\RrdTransportException::class, 'Unable to execute RRDtool');
});
