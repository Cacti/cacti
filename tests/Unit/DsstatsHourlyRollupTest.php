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

/*
 * The hourly statistics are rolled up from the hourly cache.  A data source
 * whose whole window is unknown must become unknown, not keep the average
 * of an earlier window.
 */

test('a window of only unknown samples replaces the previous hourly average with NULL', function () {
	// the DsstatsHourlyRollupCoverage fixture owns the rollup run so the same
	// run also feeds the changed-line coverage gate
	$pipes   = [];
	$process = proc_open([PHP_BINARY, dirname(__DIR__) . '/fixtures/DsstatsHourlyRollupCoverage.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('');

	expect(json_decode($output, true, 512, JSON_THROW_ON_ERROR))
		->toEqual([
			['local_data_id' => 1, 'average' => null, 'peak' => null],
			['local_data_id' => 2, 'average' => 20.0, 'peak' => 30.0],
		]);
});
