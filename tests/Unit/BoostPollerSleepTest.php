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
 * Verifies that the unconditional sleep(7) before boost child launch
 * has been removed from poller_boost.php.
 *
 * The original comment claimed the sleep was needed to "allow mysql to
 * flush the rename transaction", but RENAME TABLE is DDL and commits
 * immediately — there is nothing to flush. The sleep added 7 seconds
 * of dead time to every boost cycle with no benefit.
 */

$boostPollerPath = __DIR__ . '/../../poller_boost.php';

test('poller_boost.php has no unconditional sleep before boost_launch_children', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// The removed block combined the "Allow mysql to flush" comment with sleep(7).
	// Assert neither the comment nor the 7-second sleep remain.
	expect($contents)->not->toContain('Allow mysql to flush the rename transaction');
	expect($contents)->not->toMatch('/sleep\s*\(\s*7\s*\)/');
});

test('poller_boost.php still calls boost_launch_children', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// Confirm the removal did not accidentally delete the launch call itself.
	expect($contents)->toContain('boost_launch_children()');
});
