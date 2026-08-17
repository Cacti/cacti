<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for poller_output_boost_local_data_ids missing
 * USING BTREE on its MEMORY-engine indexes (issue#7532).
 *
 * MEMORY-engine indexes default to HASH unless USING BTREE is specified.
 * boost_output_rrd_data()'s "ORDER BY local_data_id ASC LIMIT ?" and
 * boost_process_local_data_ids()'s "DELETE ... WHERE local_data_id <= ?"
 * both need range access on local_data_id, which a HASH index cannot
 * serve -- forcing a filesort on every boost worker pass. This was
 * verified directly against a real MariaDB 11 instance: with the old HASH
 * schema, EXPLAIN on the ORDER BY/LIMIT query showed "Using filesort";
 * with USING BTREE, the same query used the PRIMARY key for ordering and
 * "Using filesort" disappeared. An index algorithm isn't something a unit
 * test can observe directly (EXPLAIN plans aren't portable across engines,
 * and this repo's Pest suite has no live-MySQL fixture), so these tests
 * pin the schema/upgrade text that produces that verified behavior.
 */

$root = dirname(__DIR__, 4);

test('cacti.sql declares poller_output_boost_local_data_ids with USING BTREE on both indexes', function () use ($root) {
	$sql = file_get_contents($root . '/cacti.sql');
	expect($sql)->not->toBeFalse();

	$pos = strpos($sql, 'CREATE TABLE `poller_output_boost_local_data_ids`');
	expect($pos)->not->toBeFalse();

	$end   = strpos($sql, ') ENGINE=', $pos);
	$table = substr($sql, $pos, $end - $pos);

	expect($table)->toContain('PRIMARY KEY USING BTREE (`local_data_id`)');
	expect($table)->toContain('KEY `process_handler` USING BTREE (`process_handler`)');
});

test('poller_boost.php runtime fallback CREATE TABLE matches the corrected cacti.sql schema', function () use ($root) {
	$src = file_get_contents($root . '/poller_boost.php');
	expect($src)->not->toBeFalse();

	$pos = strpos($src, 'CREATE TABLE IF NOT EXISTS poller_output_boost_local_data_ids');
	expect($pos)->not->toBeFalse();

	$end     = strpos($src, "')", $pos);
	$snippet = substr($src, $pos, $end - $pos);

	// This CREATE TABLE IF NOT EXISTS is dead code on any real install (the
	// table already exists from cacti.sql), but it must not silently
	// diverge -- InnoDB/no-BTREE here would document a schema that never
	// actually gets created.
	expect($snippet)->toContain('PRIMARY KEY USING BTREE (local_data_id)');
	expect($snippet)->toContain('INDEX process_handler USING BTREE (process_handler)');
	expect($snippet)->toContain('ENGINE=MEMORY');
});

test('install/upgrades/1_3_0.php rebuilds both indexes as BTREE for existing installs', function () use ($root) {
	$src = file_get_contents($root . '/install/upgrades/1_3_0.php');
	expect($src)->not->toBeFalse();

	$pos = strpos($src, "if (db_table_exists('poller_output_boost_local_data_ids'))");
	expect($pos)->not->toBeFalse('upgrade must guard on the table existing before altering it');

	$end     = strpos($src, "\n\tdb_install_add_column", $pos);
	$snippet = substr($src, $pos, ($end === false ? 2000 : $end - $pos));

	// db_install_add_key() only compares index columns, not algorithm; an
	// existing HASH index with the same columns would be silently skipped.
	// The upgrade must check INDEX_TYPE directly instead.
	expect($snippet)->toContain("INDEX_NAME = 'PRIMARY'");
	expect($snippet)->toContain("INDEX_NAME = 'process_handler'");
	expect($snippet)->toContain('DROP PRIMARY KEY');
	expect($snippet)->toContain('ADD PRIMARY KEY USING BTREE (local_data_id)');
	expect($snippet)->toContain('DROP INDEX process_handler');
	expect($snippet)->toContain('ADD INDEX process_handler USING BTREE (process_handler)');

	// Must not blindly rebuild every time -- only when the stored algorithm
	// genuinely isn't BTREE yet, so re-running the upgrade is a no-op.
	expect($snippet)->toMatch('/\$index_type\s*!=\s*[\'"]BTREE[\'"]/');
});
