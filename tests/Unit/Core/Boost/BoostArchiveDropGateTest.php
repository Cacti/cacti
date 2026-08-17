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
 * Regression test for #7520: the archive-table DROP in poller_boost.php was
 * gated on `$rrd_updates > 0`, an aggregate SUM(status) over whatever
 * completion rows landed. If 3 of 4 shards succeeded and the 4th child
 * crashed before writing a completion row, the sum from the 3 successful
 * shards was still positive and every archive table for the run -- including
 * the crashed child's entire unprocessed shard -- got dropped.
 *
 * The drain loop a few lines above already computes the correct signal
 * (boost_completed_children() < $expected_children) to detect a crashed
 * child; the fix wires that same completeness check into the drop decision.
 */

$boostPollerPath = __DIR__ . '/../../../../poller_boost.php';

test('archive-table drop is gated on shard completeness, not the status sum', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$rrd_updates_pos = strpos($contents, "SELECT SUM(status) FROM poller_output_boost_processes");
	expect($rrd_updates_pos)->not->toBeFalse();

	$drop_pos = strpos($contents, 'DROP TABLE IF EXISTS `$table`', $rrd_updates_pos);
	expect($drop_pos)->not->toBeFalse();

	$segment = substr($contents, $rrd_updates_pos, $drop_pos - $rrd_updates_pos);

	// The completeness check must appear between the SUM() read and the
	// actual DROP, gating it.
	expect($segment)->toContain('boost_completed_children() >= $expected_children');

	// A bare "if ($rrd_updates > 0) { ... DROP ... }" with nothing else
	// between them is exactly the bug: the drop must not be reachable
	// without the completeness check in between.
	$gate_pos = strpos($segment, 'boost_completed_children() >= $expected_children');
	expect($gate_pos)->toBeGreaterThan(0);
});

test('partial-completion path logs a warning instead of silently keeping the tables', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$drop_pos = strpos($contents, 'DROP TABLE IF EXISTS `$table`');
	expect($drop_pos)->not->toBeFalse();

	// Search a window around the drop for the else branch that must log when
	// completeness is not met.
	$window = substr($contents, max(0, $drop_pos - 200), 1200);

	expect($window)->toContain('leaving archive tables in place for the next run to pick up');
});

test('boost_completed_children and expected_children are still computed the same way the drain loop uses', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// The drain loop's own completeness check must still be present and
	// unchanged; the drop-gate fix reuses these exact symbols rather than
	// inventing a parallel accounting mechanism that could drift from it.
	expect($contents)->toContain('boost_completed_children() < $expected_children');
	expect(substr_count($contents, '$expected_children'))->toBeGreaterThanOrEqual(4);
});
