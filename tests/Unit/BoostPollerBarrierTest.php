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
 * Tests for the boost poller correctness fixes:
 *
 * 1. Startup barrier waits for every launched child to register before the
 *    parent drains and drops archive tables.
 * 2. The archive-table fallback validates the parent-supplied name and confirms
 *    it with a data-plane read instead of the lagging SHOW TABLES metadata.
 * 3. boost_parallel clamping and the log-path allow-list are shared, single
 *    sources of truth and the allow-list accepts Windows paths.
 *
 * The pure helpers (clamp, name validation, log-path safety, barrier predicate)
 * are exercised behaviourally. lib/boost.php is function definitions only, so it
 * is safe to require once cacti_sizeof() is stubbed. The wiring in poller_boost.php
 * and the concurrency race itself are asserted by reading the source; the full
 * multi-process race needs the integration suite.
 */

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof(mixed $array) : int {
		return is_array($array) ? count($array) : 0;
	}
}

require_once __DIR__ . '/../../lib/boost.php';

$boostPollerPath = __DIR__ . '/../../poller_boost.php';
$boostLibPath    = __DIR__ . '/../../lib/boost.php';

test('boost_clamp_parallel maps misconfigured values to a single child', function () {
	expect(boost_clamp_parallel(''))->toBe(1);
	expect(boost_clamp_parallel(null))->toBe(1);
	expect(boost_clamp_parallel(0))->toBe(1);
	expect(boost_clamp_parallel('0'))->toBe(1);
	expect(boost_clamp_parallel(-4))->toBe(1);
	expect(boost_clamp_parallel('-4'))->toBe(1);
	expect(boost_clamp_parallel('abc'))->toBe(1);
});

test('boost_clamp_parallel preserves valid process counts', function () {
	expect(boost_clamp_parallel('8'))->toBe(8);
	expect(boost_clamp_parallel(8))->toBe(8);
	expect(boost_clamp_parallel(1))->toBe(1);
	expect(boost_clamp_parallel('16'))->toBe(16);
});

test('boost_is_valid_archive_table accepts well-formed names and rejects injection', function () {
	expect(boost_is_valid_archive_table('poller_output_boost_arch_123'))->toBeTrue();
	expect(boost_is_valid_archive_table('poller_output_boost_arch_1746000000'))->toBeTrue();

	expect(boost_is_valid_archive_table(''))->toBeFalse();
	expect(boost_is_valid_archive_table('poller_output_boost_arch_'))->toBeFalse();
	expect(boost_is_valid_archive_table('poller_output_boost_arch_abc'))->toBeFalse();
	expect(boost_is_valid_archive_table('poller_output_boost_arch_123; DROP TABLE users'))->toBeFalse();
	expect(boost_is_valid_archive_table('poller_output_boost_arch_1 --x'))->toBeFalse();
	expect(boost_is_valid_archive_table("poller_output_boost_arch_1\n--x"))->toBeFalse();
	expect(boost_is_valid_archive_table('../poller_output_boost_arch_1'))->toBeFalse();
	expect(boost_is_valid_archive_table('`poller_output_boost_arch_1`'))->toBeFalse();
	expect(boost_is_valid_archive_table(123))->toBeFalse();
	expect(boost_is_valid_archive_table(null))->toBeFalse();
});

test('boost_log_path_is_safe accepts a unix path and rejects shell metacharacters', function () {
	expect(boost_log_path_is_safe('/var/log/cacti/boost.log'))->toBeTrue();
	expect(boost_log_path_is_safe('boost.log'))->toBeTrue();

	expect(boost_log_path_is_safe(''))->toBeFalse();
	expect(boost_log_path_is_safe(null))->toBeFalse();
	expect(boost_log_path_is_safe('/var/log/$(touch pwn)'))->toBeFalse();
	expect(boost_log_path_is_safe('/var/log/boost.log; rm -rf /'))->toBeFalse();
	expect(boost_log_path_is_safe('/var/log/`id`.log'))->toBeFalse();
	expect(boost_log_path_is_safe('/var/log/boost.log | cat'))->toBeFalse();
});

test('boost_log_path_is_safe allow-list permits Windows path characters', function () {
	// On non-win32 the plain class is applied, so assert the win32 class directly
	// against the same metacharacter set the helper uses. This is the regex the
	// helper runs under PHP_OS_FAMILY === 'Windows'.
	$winClass = '/[^A-Za-z0-9_.\/\\\\: -]/';

	expect(preg_match($winClass, 'C:/cacti/log/boost.log'))->toBe(0);
	expect(preg_match($winClass, 'C:\\cacti\\log\\boost.log'))->toBe(0);
	expect(preg_match($winClass, 'C:\\Program Files\\Cacti\\boost.log'))->toBe(0);

	// Even with the relaxed class, shell metacharacters are still rejected.
	expect(preg_match($winClass, 'C:\\cacti\\$(pwn).log'))->toBe(1);
	expect(preg_match($winClass, 'C:\\cacti\\boost.log;del'))->toBe(1);
	expect(preg_match($winClass, 'C:\\cacti\\`id`.log'))->toBe(1);
});

test('boost_all_children_registered holds until every launched child is accounted for', function () {
	// Four launched, only one registered so far: barrier must not release.
	expect(boost_all_children_registered(4, 1, 0))->toBeFalse();
	expect(boost_all_children_registered(4, 0, 0))->toBeFalse();

	// The race this closes: a fast child finishes (running drops, completed rises)
	// while siblings are still booting. running + completed still under expected.
	expect(boost_all_children_registered(4, 2, 1))->toBeFalse();

	// All four present, whether still running, finished, or a mix.
	expect(boost_all_children_registered(4, 4, 0))->toBeTrue();
	expect(boost_all_children_registered(4, 0, 4))->toBeTrue();
	expect(boost_all_children_registered(4, 1, 3))->toBeTrue();
	expect(boost_all_children_registered(1, 1, 0))->toBeTrue();
});

test('archive-table fallback no longer relies on the lagging SHOW TABLES probe', function () use ($boostLibPath) {
	$contents = file_get_contents($boostLibPath);

	$func_pos  = strpos($contents, 'function boost_get_arch_table_names');
	$func_end  = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	// db_table_exists() runs SHOW TABLES, the same metadata query that lags under
	// the replication this fix targets; the fallback must not depend on it.
	expect($func_body)->not->toContain('db_table_exists(');

	// The fallback must validate the name and confirm it with a data-plane read.
	expect($func_body)->toContain('boost_is_valid_archive_table($latest_table)');
	expect($func_body)->toContain('boost_archive_table_readable($latest_table)');
});

test('boost_archive_table_readable probes data, not metadata, and rejects bad names', function () use ($boostLibPath) {
	$contents = file_get_contents($boostLibPath);

	$func_pos  = strpos($contents, 'function boost_archive_table_readable');
	$func_end  = strpos($contents, "\nfunction ", $func_pos + 1);
	$func_body = substr($contents, $func_pos, $func_end - $func_pos);

	// Validate the interpolated name before it reaches SQL.
	expect($func_body)->toContain('boost_is_valid_archive_table($table)');

	// Read from the table itself (data plane), not SHOW TABLES / information_schema.
	expect($func_body)->toContain('SELECT COUNT(*) FROM `$table`');
	expect($func_body)->not->toContain('SHOW TABLES');
	expect($func_body)->not->toContain('information_schema');
});

test('parent waits for all launched children before draining', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// boost_launch_children() returns the launched count and the barrier waits on
	// boost_all_children_registered() rather than releasing on the first signup.
	expect($contents)->toContain('$expected_children = boost_launch_children();');
	expect($contents)->toContain('boost_all_children_registered($expected_children');
	expect($contents)->not->toContain('boost_processes_running() < 1');

	// A deadline still bounds the wait, with a log line if it expires early.
	expect($contents)->toContain('$startup_deadline');
	expect($contents)->toContain('Boost startup barrier timed out');
});

test('drain loop exits only when every child has recorded completion', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// The old loop exited as soon as no child was running. The fix also requires
	// every launched child to have a completion row before draining.
	expect($contents)->toContain('boost_completed_children() < $expected_children');
	expect($contents)->not->toMatch('/while\s*\(\s*\$running\s*=\s*boost_processes_running\(\)\s*\)/');
});

test('end-of-run cleanup drops only this run\'s archive tables', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// The unconditional DROP over every poller_output_boost_arch_% table could
	// destroy a newer rotation or an older crashed run still holding rows. The
	// cleanup must iterate the recorded in-scope set instead.
	expect($contents)->toContain('foreach ($boost_run_arch_tables as $table)');

	// Each dropped name is re-validated before interpolation.
	$drop_pos = strpos($contents, 'foreach ($boost_run_arch_tables as $table)');
	$segment  = substr($contents, $drop_pos, 400);
	expect($segment)->toContain('boost_is_valid_archive_table($table)');
	expect($segment)->toContain('DROP TABLE IF EXISTS `$table`');
});

test('both boost_parallel call sites use the shared clamp helper', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	// boost_prepare_process_table() and boost_launch_children() must agree on the
	// process count so the parent spawns exactly what it later waits for.
	expect(substr_count($contents, 'boost_clamp_parallel(read_config_option(\'boost_parallel\'))'))->toBeGreaterThanOrEqual(2);

	// The old divergent guards must be gone.
	expect($contents)->not->toContain('if (empty($processes)) {');
});

test('boost_launch_children uses the shared log-path safety helper', function () use ($boostPollerPath) {
	$contents = file_get_contents($boostPollerPath);

	expect($contents)->toContain('boost_log_path_is_safe($boost_log)');
});
