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

/*
 * RRD file maintenance only deletes or archives files below the RRA directory.
 * A queue entry it declines is removed from data_source_purge_action, so the
 * purge loop ends instead of fetching the same entry again. The RRDproxy branch
 * only passes on names without control characters or dot segments.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$rrd_cleanup_probe = dirname(__DIR__, 3) . '/fixtures/Core/CollectorCleanupGates.php';

test('local maintenance handles files below the RRA directory only', function () use ($rrd_cleanup_probe) {
	$local = cacti_test_isolated_probe($rrd_cleanup_probe, ['maintenance'])['local'];

	expect($local['deleted'])->toBeTrue()
		->and($local['archived'])->toBeTrue()
		->and($local['outside'])->toBeTrue()
		->and($local['kept'])->toBeTrue()
		->and($local['counts'])->toBe([1, 1])
		->and($local['skipped'])->toBe(4);
});

test('local maintenance drops every queue entry, including the declined ones', function () use ($rrd_cleanup_probe) {
	$local = cacti_test_isolated_probe($rrd_cleanup_probe, ['maintenance'])['local'];

	expect($local['dequeued'])->toHaveCount(6)
		->and($local['dequeued'])->toContain('plain.rrd', '../outside/other.rrd', 'link/other.rrd')
		->and($local['removed'])->toBe([1, 2]);
});

test('proxy maintenance only passes on plain relative names', function () use ($rrd_cleanup_probe) {
	$proxy = cacti_test_isolated_probe($rrd_cleanup_probe, ['maintenance'])['proxy'];

	expect($proxy['commands'])->toBe(['unlink plain.rrd', 'archive sub/with space.rrd'])
		->and($proxy['skipped'])->toBe(5)
		->and($proxy['dequeued'])->toBe(7)
		->and($proxy['removed'])->toBe([1, 2]);

	foreach ($proxy['logs'] as $line) {
		expect(preg_match('/[\x00-\x1f]/', $line))->toBe(0);
	}
});

test('the declined entry is removed from the queue before the loop continues', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/poller_maintenance.php');
	$guard  = strpos($source, 'if (!rrdclean_file_is_removable(');
	$delete = strpos($source, 'DELETE FROM `data_source_purge_action`', $guard);
	$skip   = strpos($source, 'continue;', $guard);
	$unlink = strpos($source, 'unlink($real_file)', $guard);

	expect($guard)->not->toBeFalse()
		->and($delete)->toBeGreaterThan($guard)
		->and($skip)->toBeGreaterThan($delete)
		->and($unlink)->toBeGreaterThan($skip);
});
