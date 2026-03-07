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
 * Regression tests for three bugs in poller_boost.php:
 *
 * 1. sig_handler(): lock release was unreachable because it appeared after
 *    exit; in the SIGTERM/SIGINT case. Locks are now released before the
 *    process-table unregister and exit.
 *
 * 2. Loop exit condition used SELECT * instead of SELECT COUNT(*) in the
 *    db_fetch_cell_prepared() call that checks for remaining work. SELECT *
 *    returned the first column value, not a row count; accidentally correct
 *    but semantically wrong.
 *
 * 3. seconds_offset fallback assigned 120 (seconds) while storing 120
 *    (minutes) into boost_rrd_update_interval. Subsequent runs would use
 *    7200 seconds; the first run used 120 seconds -- a 60x discrepancy.
 */

$boostPollerPath = __DIR__ . '/../../poller_boost.php';

test('sig_handler releases lock before exit, not after', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// The lock release block must appear before the exit; statement inside
	// the SIGTERM/SIGINT case.  We look for RELEASE_LOCK appearing before exit;
	// within the same case block.
	$sigterm_pos     = strpos($contents, 'case SIGTERM:');
	$release_pos     = strpos($contents, 'RELEASE_LOCK(', $sigterm_pos);
	$exit_pos        = strpos($contents, "\n\t\t\texit;", $sigterm_pos);

	expect($sigterm_pos)->not->toBeFalse();
	expect($release_pos)->not->toBeFalse();
	expect($exit_pos)->not->toBeFalse();

	// release must come before exit in the case block
	expect($release_pos)->toBeLessThan($exit_pos);
});

test('sig_handler has no unreachable lock-release code after closing brace of switch', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// The old pattern was: switch(...) { ... exit; ... } \n\n if (cacti_version_compare...RELEASE_LOCK
	// After the fix the release block must not appear outside/after the switch.
	expect($contents)->not->toMatch(
		'/\}\s*\n\s*if\s*\(\s*cacti_version_compare[^}]+RELEASE_LOCK/s'
	);
});

test('loop exit condition uses SELECT COUNT(*) not SELECT *', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// Must not contain SELECT * FROM poller_output_boost_local_data_ids
	expect($contents)->not->toContain('SELECT *
			FROM poller_output_boost_local_data_ids');

	// Must contain SELECT COUNT(*)
	expect($contents)->toContain('SELECT COUNT(*)
			FROM poller_output_boost_local_data_ids');
});

test('seconds_offset fallback multiplies minutes by 60', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// The old bug: $seconds_offset = 120; (bare, treating minutes as seconds)
	// Must not appear as an isolated assignment
	expect($contents)->not->toMatch('/\$seconds_offset\s*=\s*120\s*;/');

	// The fix stores 120 minutes * 60 = 7200 seconds
	expect($contents)->toContain('$seconds_offset = 120 * 60;');
});
