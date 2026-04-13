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
	expect(file_exists($boostPollerPath))->toBeTrue("poller_boost.php not found at {$boostPollerPath}");
	$contents = file_get_contents($boostPollerPath);
	expect($contents)->not->toBeFalse("file_get_contents failed for {$boostPollerPath}");

	// The removed block combined the "Allow mysql to flush" comment with sleep(7).
	// Assert neither the comment nor the 7-second sleep remain.
	expect($contents)->not->toContain('Allow mysql to flush the rename transaction');
	expect($contents)->not->toMatch('/sleep\s*\(\s*7\s*\)/');
});

test('poller_boost.php still calls boost_launch_children', function () use ($boostPollerPath) {
	expect(file_exists($boostPollerPath))->toBeTrue("poller_boost.php not found at {$boostPollerPath}");
	$contents = file_get_contents($boostPollerPath);
	expect($contents)->not->toBeFalse("file_get_contents failed for {$boostPollerPath}");

	// Confirm the removal did not accidentally delete the launch call itself.
	expect($contents)->toContain('boost_launch_children()');
});

test('boost_launch_children call is guarded by $continue', function () use ($boostPollerPath) {
	expect(file_exists($boostPollerPath))->toBeTrue("poller_boost.php not found at {$boostPollerPath}");
	$contents = file_get_contents($boostPollerPath);
	expect($contents)->not->toBeFalse("file_get_contents failed for {$boostPollerPath}");

	// The launch call must remain inside an if ($continue) block so that
	// boost_launch_children() is never invoked when $continue is false.
	expect($contents)->toMatch('/if\s*\(\s*\$continue\s*\).*boost_launch_children\s*\(\s*\)/s');
});

test('no sleep or usleep call immediately precedes boost_launch_children', function () use ($boostPollerPath) {
	expect(file_exists($boostPollerPath))->toBeTrue("poller_boost.php not found at {$boostPollerPath}");
	$contents = file_get_contents($boostPollerPath);
	expect($contents)->not->toBeFalse("file_get_contents failed for {$boostPollerPath}");

	// Walk every line that calls boost_launch_children() and assert the
	// immediately preceding non-blank line is not a sleep/usleep call.
	// This catches re-introduction with any argument, not just sleep(7).
	$lines = explode("\n", $contents);

	foreach ($lines as $i => $line) {
		if (!str_contains($line, 'boost_launch_children()')) {
			continue;
		}

		// Scan backwards past blank lines to find the nearest code line.
		for ($j = $i - 1; $j >= 0; $j--) {
			if (trim($lines[$j]) === '') {
				continue;
			}

			expect($lines[$j])->not->toMatch(
				'/[u]?sleep\s*\(/',
				"sleep/usleep found immediately before boost_launch_children() at line " . ($j + 1)
			);

			break;
		}
	}
});
