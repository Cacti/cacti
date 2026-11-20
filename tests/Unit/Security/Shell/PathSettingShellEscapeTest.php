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

test('configured executable paths and operands survive spaces', function (string $mode) {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/ConfiguredCommands.php';
	$process = proc_open([PHP_BINARY, $fixture, $mode], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
	expect($process)->toBeResource();
	$output = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	expect(proc_close($process))->toBe(0, $output);
})->with(['pollers and upgrades' => '', 'deprecated rebuild command' => 'deprecated']);
