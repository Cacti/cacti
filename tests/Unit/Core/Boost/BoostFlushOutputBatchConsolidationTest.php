<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for the poller_output_boost insert consolidation
 * (issue#7536) and the permanent Boost redirection (issue#8470).
 *
 * The insert was hand-written four times: three in cmd.php and once in
 * boost_poller_on_demand() (lib/boost.php), using mismatched duplicate-key
 * behavior (INSERT IGNORE vs ON DUPLICATE KEY UPDATE) -- a real
 * data-integrity inconsistency (silently-drop vs last-write-wins) between
 * two paths writing the same shape of data, not just a style/DRY issue.
 * The cmd.php writes now go through the shared boost_flush_output_batch()
 * helper (lib/boost.php), standardized on INSERT IGNORE. With Boost
 * redirection permanent, cmd.php stages collector output directly, so
 * boost_poller_on_demand() no longer stages inline at all. See
 * BoostFlushOutputBatchTest.php (tests/integration) for the executing proof
 * of the chosen duplicate-key behavior.
 */

$root = dirname(__DIR__, 4);

test('lib/boost.php declares the shared boost_flush_output_batch helper', function () use ($root) {
	$src = file_get_contents($root . '/lib/boost.php');
	expect($src)->not->toBeFalse();

	expect($src)->toContain('function boost_flush_output_batch(array $value_tuples, mixed $conn = false) : bool');
	expect($src)->toContain('$acknowledged = db_execute($sql_prefix . $out_buffer, true, $conn) !== false;');

	// Standardized on IGNORE; the ON DUPLICATE KEY UPDATE variant this
	// replaced must not reappear for poller_output_boost.
	expect($src)->toContain('INSERT IGNORE INTO poller_output_boost');
	expect($src)->not->toMatch('/INSERT\s+INTO\s+poller_output_boost[^;]*ON\s+DUPLICATE\s+KEY\s+UPDATE/is');
});

test('boost_poller_on_demand no longer stages poller output inline', function () use ($root) {
	$src = file_get_contents($root . '/lib/boost.php');

	$func_pos = strpos($src, 'function boost_poller_on_demand(');
	expect($func_pos)->not->toBeFalse();

	$func_end  = strpos($src, "\nfunction ", $func_pos + 1);
	$func_body = substr($src, $func_pos, $func_end - $func_pos);

	// Boost redirection is permanent (issue#8470): cmd.php stages collector
	// output directly, so the on-demand path must never (re-)insert into
	// poller_output_boost itself.
	expect($func_body)->not->toContain('boost_flush_output_batch')
		->and($func_body)->not->toContain('INSERT IGNORE INTO poller_output_boost')
		->and($func_body)->not->toContain('ON DUPLICATE KEY UPDATE')
		->and($func_body)->not->toContain("SHOW VARIABLES LIKE 'max_allowed_packet'");
});

test('all three cmd.php call sites use boost_flush_output_batch, none hand-roll the insert', function () use ($root) {
	$src = file_get_contents($root . '/cmd.php');
	expect($src)->not->toBeFalse();

	// Every remaining "poller_output_boost" reference in cmd.php must be
	// through the shared helper (the only exception is the unrelated
	// SHOW COLUMNS introspection call elsewhere in the file), not a
	// hand-written INSERT.
	expect(substr_count($src, 'boost_flush_output_batch($output_array, $poller_db_cnn_id)'))->toBe(3);
	expect($src)->not->toMatch('/db_execute\(\s*[\'"]INSERT IGNORE INTO poller_output_boost/');
});

test('cmd.php requires lib/boost.php so boost_flush_output_batch is available', function () use ($root) {
	$src = file_get_contents($root . '/cmd.php');
	expect($src)->toContain("require_once(CACTI_PATH_LIBRARY . '/boost.php');");
});
