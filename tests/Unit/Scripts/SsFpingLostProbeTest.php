<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
*/

/*
 * ss_fping() runs against a scripted Net_Ping in the SsFpingLostProbeCoverage
 * fixture, which owns the stand-ins so the same run also feeds the changed-line
 * coverage gate.  With the default settings a failed probe keeps adding its
 * elapsed time to the samples; with ping_rtt_exclude_lost on it only counts
 * toward loss.
 */

function ss_fping_lost_probe_results() : array {
	$pipes   = [];
	$process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/fixtures/SsFpingLostProbeCoverage.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('');

	return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

test('a lost probe still adds a sample by default', function () {
	expect(ss_fping_lost_probe_results()['default'])->toBe('min:10.0000 avg:20.0000 max:30.0000 dev:12.2474 loss:25.0000');
});

test('with the setting on a lost probe only counts toward loss', function () {
	expect(ss_fping_lost_probe_results()['exclude_lost'])->toBe('min:10.0000 avg:20.0000 max:30.0000 dev:8.1650 loss:25.0000');
});

test('every probe lost reports unknown times in both modes', function () {
	$results = ss_fping_lost_probe_results();

	expect($results['all_lost'])->toBe('min:U avg:U max:U dev:U loss:100.00')
		->and($results['all_lost_exclude'])->toBe('min:U avg:U max:U dev:U loss:100.00');
});
