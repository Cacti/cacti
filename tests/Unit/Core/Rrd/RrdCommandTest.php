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

use Cacti\Rrd\RrdCommand;
use Cacti\Rrd\RrdCommandSerializer;

require_once dirname(__DIR__, 4) . '/lib/Rrd/RrdCommand.php';
require_once dirname(__DIR__, 4) . '/lib/Rrd/RrdCommandSerializer.php';

test('a command preserves raw arguments for shellless execution', function () {
	$command = new RrdCommand('update', [
		'/var/lib/cacti/a file.rrd',
		'--template',
		'traffic_in:traffic_out',
		'100:1; echo not-a-command',
	]);

	expect($command->toArgv('/usr/bin/rrdtool'))->toBe([
		'/usr/bin/rrdtool',
		'update',
		'/var/lib/cacti/a file.rrd',
		'--template',
		'traffic_in:traffic_out',
		'100:1; echo not-a-command',
	]);
});

test('line protocol serialization escapes arguments but not the operation', function () {
	$command    = new RrdCommand('fetch', ['/var/lib/cacti/a file.rrd', 'AVERAGE']);
	$serializer = new RrdCommandSerializer();

	expect($serializer->forLineProtocol($command, 'escapeshellarg'))
		->toBe("fetch '/var/lib/cacti/a file.rrd' 'AVERAGE'");
});

test('the compatibility list constructor normalizes scalar arguments', function () {
	$command = RrdCommand::fromList(['fetch', '/tmp/test.rrd', 'AVERAGE', -300, null]);

	expect($command->operation)->toBe('fetch')
		->and($command->arguments)->toBe(['/tmp/test.rrd', 'AVERAGE', '-300', '']);
});

test('commands reject line protocol control characters', function (string $token) {
	expect(fn () => new RrdCommand('update', [$token]))
		->toThrow(InvalidArgumentException::class);
})->with([
	'line feed'       => "value\nfetch other.rrd AVERAGE",
	'carriage return' => "value\rquit",
	'null byte'       => "value\0suffix",
]);

test('commands reject operations containing whitespace', function () {
	expect(fn () => new RrdCommand('fetch another-command'))
		->toThrow(InvalidArgumentException::class);
});
