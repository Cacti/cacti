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
	preg_match('/\bexit;/', $contents, $exit_match, PREG_OFFSET_CAPTURE, $sigterm_pos);
	$exit_pos = isset($exit_match[0]) ? $exit_match[0][1] : false;

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

test('sig_handler parent-process path uses RELEASE_ALL_LOCKS via db_execute_prepared', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$sigterm_pos = strpos($contents, 'case SIGTERM:');
	expect($sigterm_pos)->not->toBeFalse();

	// The !$child branch must call RELEASE_ALL_LOCKS (not RELEASE_LOCK only)
	$all_locks_pos = strpos($contents, 'RELEASE_ALL_LOCKS()', $sigterm_pos);
	expect($all_locks_pos)->not->toBeFalse();

	// Must be wrapped in db_execute_prepared, not the raw db_execute
	$prepared_pos = strrpos(substr($contents, $sigterm_pos, $all_locks_pos - $sigterm_pos), 'db_execute_prepared');
	expect($prepared_pos)->not->toBeFalse();

	// RELEASE_ALL_LOCKS must appear before exit; in the case block
	preg_match('/\bexit;/', $contents, $exit_match, PREG_OFFSET_CAPTURE, $sigterm_pos);
	$exit_pos = isset($exit_match[0]) ? $exit_match[0][1] : false;
	expect($all_locks_pos)->toBeLessThan($exit_pos);
});

test('sig_handler skips lock release when current_lock is false', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$sigterm_pos = strpos($contents, 'case SIGTERM:');
	expect($sigterm_pos)->not->toBeFalse();

	// The child branch must guard on $current_lock !== false so that a process
	// that never acquired a lock does not attempt to release one.
	$guard_pos = strpos($contents, '$current_lock !== false', $sigterm_pos);
	expect($guard_pos)->not->toBeFalse();

	// The guard must appear before exit; in the case block
	preg_match('/\bexit;/', $contents, $exit_match, PREG_OFFSET_CAPTURE, $sigterm_pos);
	$exit_pos = isset($exit_match[0]) ? $exit_match[0][1] : false;
	expect($guard_pos)->toBeLessThan($exit_pos);
});

test('seconds_offset normal path multiplies read interval by 60', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// When boost_rrd_update_interval is already configured the assignment must
	// multiply the stored minutes value by 60 to produce seconds, not assign a
	// bare integer literal.
	expect($contents)->toMatch(
		'/\$seconds_offset\s*=\s*read_config_option\s*\(\s*[\'"]boost_rrd_update_interval[\'"]\s*\)\s*\*\s*60\s*;/'
	);
});

$boostLibPath = __DIR__ . '/../../lib/boost.php';

test('boost_launch_children passes --archive-table to child processes', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// Children need the archive table name so boost_get_arch_table_names has
	// a concrete fallback when SHOW TABLES returns nothing (e.g. replication lag).
	// --archive-table= is now an element in $child_args passed to exec_background,
	// giving each argument individual shell-escaping via cacti_escapeshellarg().
	expect($contents)->toContain("'--archive-table=' . \$archive_table");
	expect($contents)->toContain('exec_background($php_binary, $child_args, $redirect_args)');
});

test('boost_launch_children declares $archive_table as global', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// The global declaration inside boost_launch_children must include $archive_table
	// so the parent-set value is visible when building the child command line.
	$func_pos   = strpos($contents, 'function boost_launch_children()');
	$global_pos = strpos($contents, 'global', $func_pos);
	$line_end   = strpos($contents, ';', $global_pos);
	$global_line = substr($contents, $global_pos, $line_end - $global_pos);

	expect($global_line)->toContain('$archive_table');
});

test('--archive-table argument is validated with regex before assignment', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// The parsed value must be checked against the expected table name pattern
	// before being assigned to $archive_table to prevent argument injection.
	expect($contents)->toContain("'--archive-table'");
	expect($contents)->toMatch('/poller_output_boost_arch_.*\\\\d\+/');
});

test('boost_get_arch_table_names does not filter by TABLE_ROWS in information_schema fallback', function () use ($boostLibPath) {
	$contents = file_get_contents($boostLibPath);

	// InnoDB TABLE_ROWS in information_schema is an estimate; it can be zero
	// immediately after RENAME TABLE even for non-empty tables. Filtering on
	// TABLE_ROWS > 0 caused spurious "Failed to retrieve archive table name"
	// errors when the estimate had not yet updated.
	$func_pos = strpos($contents, 'function boost_get_arch_table_names');
	$func_end = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	expect($func_body)->not->toContain('TABLE_ROWS > 0');
});

test('sig_handler gates boost_poller_status update on parent process only', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$sigterm_pos = strpos($contents, 'case SIGTERM:');
	expect($sigterm_pos)->not->toBeFalse();

	// The terminated status write must be guarded by !$child so child processes
	// don't clobber the parent's status entry in the settings table.
	$status_pos = strpos($contents, "'boost_poller_status', 'terminated", $sigterm_pos);
	expect($status_pos)->not->toBeFalse();

	$segment = substr($contents, $sigterm_pos, $status_pos - $sigterm_pos);
	expect($segment)->toContain('if (!$child)');
});

test('boost_prepare_process_table guards against misconfigured parallel count', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$func_pos = strpos($contents, 'function boost_prepare_process_table()');
	expect($func_pos)->not->toBeFalse();

	$func_end  = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	// The clamp now lives in the shared boost_clamp_parallel() helper so this
	// call site and boost_launch_children() can never disagree on the count
	// before the ceil() division.
	expect($func_body)->toContain("boost_clamp_parallel(read_config_option('boost_parallel'))");
});

test('boost_output_rrd_data returns 0 not false when no rows are assigned', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$func_pos = strpos($contents, 'function boost_output_rrd_data(');
	expect($func_pos)->not->toBeFalse();

	$func_end  = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	// Returning false inserts a non-numeric value into poller_output_boost_processes.status;
	// the parent's SUM() then silently treats it as 0 but the child log emits a warning.
	$zero_check_pos = strpos($func_body, '$total_rows == 0');
	expect($zero_check_pos)->not->toBeFalse();

	$after_check = substr($func_body, $zero_check_pos, 60);
	expect($after_check)->toContain('return 0;');
	expect($after_check)->not->toContain('return false;');
});

test('boost_output_rrd_data guards against zero max_per_select', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$func_pos = strpos($contents, 'function boost_output_rrd_data(');
	expect($func_pos)->not->toBeFalse();

	$func_end  = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	// A zero or unconfigured max_per_select would produce division-by-zero in ceil().
	expect($func_body)->toMatch('/if\s*\(\s*\$max_per_select\s*<=\s*0\s*\)/');
	expect($func_body)->toContain('$max_per_select = 50000;');
});

test('boost_process_local_data_ids guards against zero records per select', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$func_pos = strpos($contents, 'function boost_process_local_data_ids(');
	expect($func_pos)->not->toBeFalse();

	$func_end  = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	// The same setting controls the batched SELECT LIMIT in this worker path.
	// Empty or invalid configuration must fall back before LIMIT construction.
	expect($func_body)->toContain("intval(read_config_option('boost_rrd_update_max_records_per_select'))");
	expect($func_body)->toMatch('/if\s*\(\s*\$data_ids_to_get\s*<=\s*0\s*\)/');
	expect($func_body)->toContain('$data_ids_to_get = 50000;');
});

test('parent waits for all children to register before entering monitoring loop', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// exec_background() is non-blocking; the barrier must wait for every launched
	// child, not just the first. Releasing on the first registration lets a fast
	// child finish, drop the running count to 0, and trip the drain exit while
	// siblings are still booting.
	$launch_pos = strpos($contents, '$expected_children = boost_launch_children();');
	expect($launch_pos)->not->toBeFalse();

	$barrier_pos = strpos($contents, 'boost_all_children_registered($expected_children', $launch_pos);
	expect($barrier_pos)->not->toBeFalse();

	// A time-bounded deadline must accompany the barrier so the parent can't
	// spin forever if children crash before registering.
	$barrier_segment = substr($contents, $launch_pos, $barrier_pos - $launch_pos + 100);
	expect($barrier_segment)->toContain('$startup_deadline');
});

test('boost_process_poller_output does not shadow archive_table with static', function () use ($boostLibPath) {
	$contents = file_get_contents($boostLibPath);

	$func_pos = strpos($contents, 'function boost_process_poller_output(');
	expect($func_pos)->not->toBeFalse();

	$func_end  = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	// A static local always starts false and ignores the global set by the
	// child CLI argument parser, making the archive hint permanently useless.
	expect($func_body)->not->toContain('static $archive_table');

	// The global declaration must include $archive_table so the hint flows in.
	$global_pos  = strpos($func_body, 'global ');
	$global_end  = strpos($func_body, ';', $global_pos);
	$global_line = substr($func_body, $global_pos, $global_end - $global_pos);
	expect($global_line)->toContain('$archive_table');
});

test('non-templated data source query joins graph_templates_item before filtering on gti', function () use ($boostLibPath) {
	$contents = file_get_contents($boostLibPath);

	// The broken queries referenced gti.task_item_id without a FROM/JOIN clause,
	// causing MySQL "Unknown column 'gti.task_item_id'" for every non-templated DS.
	expect($contents)->not->toMatch(
		'/FROM data_template_rrd AS dtr\s+WHERE dtr\.local_data_id = \? AND gti\.task_item_id IS NULL/'
	);

	// Both fixed sites must have the LEFT JOIN so gti is defined.
	$join_needle = 'LEFT JOIN graph_templates_item AS gti';
	$count = substr_count($contents, $join_needle);

	// Three occurrences: the pre-existing correct one (templated path) plus the two fixes.
	expect($count)->toBeGreaterThanOrEqual(3);
});

test('boost_process_local_data_ids non-templated reset_template branch joins gti', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$func_pos = strpos($contents, 'function boost_process_local_data_ids(');
	expect($func_pos)->not->toBeFalse();

	$func_end  = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	// The non-templated else branch in boost_process_local_data_ids must not reference
	// gti.task_item_id without a JOIN — MySQL raises "Unknown column" without the JOIN.
	expect($func_body)->not->toMatch(
		'/FROM data_template_rrd AS dtr\s+WHERE dtr\.local_data_id = \? AND gti\.task_item_id IS NULL/'
	);

	// The LEFT JOIN must be present in at least the two corrected sites.
	$join_needle = 'LEFT JOIN graph_templates_item AS gti';
	expect(substr_count($func_body, $join_needle))->toBeGreaterThanOrEqual(2);
});

test('boost_output_rrd_data returns 0 not false when arch tables are not found', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	$func_pos = strpos($contents, 'function boost_output_rrd_data(');
	expect($func_pos)->not->toBeFalse();

	$func_end  = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	// The arch_tables-not-found early return must use 0, not false, so the value
	// inserted into poller_output_boost_processes.status stays numeric throughout.
	$arch_check_pos = strpos($func_body, '!cacti_sizeof($arch_tables)');
	expect($arch_check_pos)->not->toBeFalse();

	$after_arch = substr($func_body, $arch_check_pos, 120);
	expect($after_arch)->toContain('return 0;');
	expect($after_arch)->not->toContain('return false;');
});

test('boost_poller_status is set to complete when $continue is false', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// When boost_prepare_process_table() returns false it already set
	// boost_poller_status to 'running'. Without an explicit reset in the else
	// branch, the next run treats it as an overrun and emits a false warning.
	$launch_pos = strpos($contents, 'boost_launch_children()');
	expect($launch_pos)->not->toBeFalse();

	// Find the else branch that follows the if ($continue) block.
	$else_pos = strpos($contents, "} else {\n\t\t\t// boost_prepare_process_table()", $launch_pos);
	expect($else_pos)->not->toBeFalse();

	$else_segment = substr($contents, $else_pos, 400);
	expect($else_segment)->toContain("'boost_poller_status', 'complete");
});

test('boost_get_total_rows uses information_schema SUM(TABLE_ROWS) for O(1) row estimation', function () use ($boostLibPath) {
	$contents = file_get_contents($boostLibPath);

	$func_pos = strpos($contents, 'function boost_get_total_rows()');
	expect($func_pos)->not->toBeFalse();

	$func_end  = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	// boost_get_total_rows() is called from the monitoring loop for display
	// purposes. Per-table COUNT(*) would add a full-table scan for every arch
	// table on every monitoring tick. TABLE_ROWS from information_schema is an
	// O(1) estimate that is accurate here because ANALYZE TABLE is called in
	// boost_prepare_process_table() before the monitoring loop starts.
	expect($func_body)->toContain('TABLE_ROWS');
	expect($func_body)->toContain('information_schema');

	// The exact per-table COUNT(*) path lives in boost_prepare_process_table(),
	// not here; this function must not do per-table full scans.
	expect($func_body)->not->toContain('foreach');
});
