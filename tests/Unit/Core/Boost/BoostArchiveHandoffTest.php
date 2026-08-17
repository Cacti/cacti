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
 * Regression tests for two bugs in boost_process_poller_output() (lib/boost.php):
 *
 * 1. (#7519) The archive-table cleanup deleted every row for $local_data_id
 *    unconditionally, even rows with time >= $timestamp that belong to a
 *    still-open poll round and were therefore excluded from the RRD write a
 *    few lines above. Those rows are now carried forward into the live
 *    poller_output_boost table (INSERT IGNORE, time >= $timestamp) before
 *    the archive delete runs.
 *
 *    The delete itself stays an unconditional-by-local_data_id delete on
 *    purpose, not narrowed to time < $timestamp: the archive-side SELECT
 *    that seeds boost_process_poller_output()'s own $temp_table on a later
 *    call for this local_data_id has no time filter (see the "INSERT INTO
 *    `{$temp_table}` SELECT * FROM `{$table}` WHERE local_data_id = ?" a few
 *    lines above in the same function) and is not INSERT IGNORE. Leaving a
 *    forwarded row behind in the archive table would let that later call
 *    pull the same row in twice -- once from the archive copy, once from
 *    the live table it was forwarded to -- and collide on
 *    poller_output_boost's own primary key (local_data_id, time, rrd_name)
 *    when both land in $temp_table. Deleting the whole slice once it is
 *    known to be safely represented elsewhere (RRD-written or forwarded)
 *    avoids that.
 *
 * 2. (#7523) $last_item was initialized once before the results loop and
 *    never reassigned, so the duplicate-record guard compared every row
 *    against the sentinel {-1, -1, ''} and could never fire. $last_item is
 *    now updated at the end of each processed iteration.
 */

$boostLibPath = __DIR__ . '/../../../../lib/boost.php';

if (!function_exists('boost_handoff_extract_function')) {
	function boost_handoff_extract_function(string $contents, string $signature) : string {
		$func_pos = strpos($contents, $signature);

		if ($func_pos === false) {
			return '';
		}

		$func_end = strpos($contents, "\nfunction ", $func_pos + 1);

		if ($func_end === false) {
			$func_end = strlen($contents);
		}

		return substr($contents, $func_pos, $func_end - $func_pos);
	}
}

test('archive-table cleanup forwards still-open-round rows before deleting', function () use ($boostLibPath) {
	$contents  = file_get_contents($boostLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function boost_process_poller_output(');

	expect($func_body)->not->toBe('');

	// The forwarding INSERT must exist, target the live table, use IGNORE
	// (defensive against a row already re-inserted there by a concurrent
	// path), and copy exactly the still-open-round rows: time >= $timestamp.
	$insert_pos = strpos($func_body, 'INSERT IGNORE INTO poller_output_boost');
	expect($insert_pos)->not->toBeFalse('forwarding INSERT is missing');

	$insert_segment = substr($func_body, $insert_pos, 200);
	expect($insert_segment)->toContain('time >= FROM_UNIXTIME(?)');

	// The archive DELETE (the second db_execute_prepared after the archive
	// foreach) must run after the forwarding INSERT.
	$delete_pos = strpos($func_body, 'DELETE IGNORE', $insert_pos);
	expect($delete_pos)->not->toBeFalse('archive DELETE is missing');
	expect($insert_pos)->toBeLessThan($delete_pos);
});

test('archive-table delete removes the whole local_data_id slice once forwarding has run, not just the closed rounds', function () use ($boostLibPath) {
	$contents  = file_get_contents($boostLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function boost_process_poller_output(');

	$insert_pos = strpos($func_body, 'INSERT IGNORE INTO poller_output_boost');
	$delete_pos = strpos($func_body, 'DELETE IGNORE', $insert_pos);
	expect($delete_pos)->not->toBeFalse();

	$delete_segment = substr($func_body, $delete_pos, 200);

	// A forwarded row must not be left behind in the archive table: the
	// archive-side temp-table seed a few lines above (INSERT INTO
	// `{$temp_table}` SELECT * FROM `{$table}` WHERE local_data_id = ?) has
	// no time filter and is not INSERT IGNORE, so a leftover forwarded row
	// would collide with its own live-table copy on a later call for the
	// same local_data_id. The delete must therefore not be narrowed to
	// "time < FROM_UNIXTIME(?)" -- it must clear everything for this
	// local_data_id now that RRD-written and forwarded rows are both
	// accounted for elsewhere.
	expect($delete_segment)->not->toContain('time <');
	expect($delete_segment)->toContain('WHERE local_data_id = ?');
});

test('the archive-side temp-table seed that forwarded rows must not collide with has no time filter', function () use ($boostLibPath) {
	$contents  = file_get_contents($boostLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function boost_process_poller_output(');

	// This is the query the delete-scope test above is protecting against:
	// confirms the hazard this fix accounts for is still actually present
	// in the source, so the reasoning stays tied to real code rather than a
	// stale assumption.
	$seed_pos = strpos($func_body, 'INSERT INTO `{$temp_table}`');
	expect($seed_pos)->not->toBeFalse();

	$seed_segment = substr($func_body, $seed_pos, 200);
	expect($seed_segment)->toContain('FROM `{$table}`');
	expect($seed_segment)->toContain('WHERE local_data_id = ?"');
	expect($seed_segment)->not->toContain('time');
});

test('duplicate-record guard reassigns $last_item after processing each row', function () use ($boostLibPath) {
	$contents  = file_get_contents($boostLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function boost_process_poller_output(');

	$guard_pos = strpos($func_body, "if (\$last_item['timestamp'] == \$item['timestamp']");
	expect($guard_pos)->not->toBeFalse('duplicate guard not found');

	// $last_item = $item; must appear somewhere after the guard, inside the
	// same foreach loop over $results.
	$reassign_pos = strpos($func_body, '$last_item = $item;', $guard_pos);
	expect($reassign_pos)->not->toBeFalse('$last_item is never reassigned -- guard is still dead');

	// The reassignment must be the last thing that happens to $last_item in
	// the function; there must be no earlier "$last_item = " (other than the
	// initial sentinel array literal) between the guard and this point that
	// would make the placement redundant or misplaced.
	$between = substr($func_body, $guard_pos, $reassign_pos - $guard_pos);
	expect(substr_count($between, '$last_item = ['))->toBe(0);
});

test('$last_item sentinel starts at values no real row can match', function () use ($boostLibPath) {
	$contents  = file_get_contents($boostLibPath);
	$func_body = boost_handoff_extract_function($contents, 'function boost_process_poller_output(');

	expect($func_body)->toContain("'local_data_id' => -1");
	expect($func_body)->toContain("'timestamp'     => -1");
	expect($func_body)->toContain("'rrd_name'      => ''");
});
