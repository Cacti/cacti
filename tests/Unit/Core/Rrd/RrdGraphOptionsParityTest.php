<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

use Symfony\Component\Process\Process;

// Golden commands and by-reference mutations were captured from Cacti's
// pre-extraction implementation at f9f4be0d5 (PR #8012), using these fixtures.
test('extracted graph options preserve complete commands and reference mutations', function (string $version, string $metadata) {
	$tests   = dirname(__DIR__, 3);
	$process = new Process([PHP_BINARY, $tests . '/fixtures/rrd-graph-options.php', $version, $metadata]);
	$process->mustRun();
	$expected = json_decode(file_get_contents($tests . '/fixtures/rrd-graph-options/' . $version . '.json'), true, 512, JSON_THROW_ON_ERROR);
	$actual   = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

	expect($actual)->toBe($expected);
})->with([
	['1.3.0', 'warm'], ['1.4.0', 'warm'], ['1.8.0', 'warm'],
	['1.3.0', 'cold'], ['1.4.0', 'cold'], ['1.8.0', 'cold'],
]);
