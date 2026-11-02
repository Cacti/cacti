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
use Symfony\Component\Process\Process;

test('RRD capabilities are reused within calls and refreshed between calls', function () {
	$root    = dirname(__DIR__, 4);
	$process = new Process([PHP_BINARY, $root . '/tests/fixtures/rrd-version-checks.php', $root]);
	$process->mustRun();
	$result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

	foreach ($result['cases'] as $case) {
		expect($case['updates'])->toBe(6)
			->and($case['update_comparisons'])->toBe(1)
			->and($case['graph_comparisons'])->toBe(1);

		foreach ($case['commands'] as $command) {
			expect(str_contains($command, '--skip-past-updates'))->toBe(version_compare($case['version'], '1.5.0', '>='));
		}

		foreach (['legend-position', 'legend-direction', 'left-axis-formatter', 'right-axis-formatter'] as $flag) {
			expect(str_contains($case['graph'], '--' . $flag))->toBe(version_compare($case['version'], '1.4.0', '>='));
		}
	}
	expect($result['empty_update_comparisons'])->toBe(0);
});
