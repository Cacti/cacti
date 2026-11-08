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

use Symfony\Component\Process\Process;

// Golden commands and by-reference mutations were captured from Cacti's
// pre-extraction implementation at f9f4be0d5 (PR #8012), using these fixtures.
// No graph option is gated below 1.8 any more, so one pre-1.8 golden covers
// every supported release under it.
test('extracted graph options preserve complete commands and reference mutations', function (string $version) {
	$tests   = dirname(__DIR__, 3);
	$process = new Process([PHP_BINARY, $tests . '/fixtures/rrd-graph-options.php', $version]);
	$process->mustRun();
	$expected = json_decode(file_get_contents($tests . '/fixtures/rrd-graph-options/' . $version . '.json'), true, 512, JSON_THROW_ON_ERROR);
	$actual   = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

	expect($actual)->toBe($expected);
})->with(['1.4.0', '1.8.0']);
