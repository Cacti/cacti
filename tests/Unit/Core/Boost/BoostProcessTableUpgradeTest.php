<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * #7728 added run_id/child_id to poller_output_boost_processes in cacti.sql
 * and in upgrade_to_1_2_31(). 1.2.31 already shipped, so installs that
 * upgraded to 1.2.31 never run that file again. The installer walks
 * $cacti_version_codes and includes install/upgrades/<version>.php for each
 * version after the database version. 1.2.32 is already in that map.
 */

$root = dirname(__DIR__, 4);

test('the 1.2.32 upgrade file exists so already-upgraded 1.2.31 installs receive the Boost process columns', function () use ($root) {
	expect(file_exists($root . '/install/upgrades/1_2_32.php'))->toBeTrue();
});

test('upgrade_to_1_2_32 adds the Boost process-table columns and unique key', function () use ($root) {
	$src = file_get_contents($root . '/install/upgrades/1_2_32.php');

	expect($src)->toContain('function upgrade_to_1_2_32()')
		->and($src)->toContain("db_install_add_column('poller_output_boost_processes'")
		->and($src)->toContain("'name' => 'run_id'")
		->and($src)->toContain("'name' => 'child_id'")
		->and($src)->toContain("db_install_add_key('poller_output_boost_processes', 'UNIQUE', 'run_child'");
});

test('1.2.32 is in the installer version chain', function () use ($root) {
	$src = file_get_contents($root . '/include/global_arrays.php');

	expect($src)->toContain("'1.2.32'");
});

test('Boost recovers the process-table columns without waiting for an upgrade stamp', function () use ($root) {
	$boost  = file_get_contents($root . '/lib/boost.php');
	$poller = file_get_contents($root . '/poller.php');
	$child  = file_get_contents($root . '/poller_boost.php');

	expect($boost)->toContain('function boost_ensure_process_table($repair_key = false)')
		->and($boost)->toContain("db_column_exists('poller_output_boost_processes', 'run_id')")
		->and($boost)->toContain("db_column_exists('poller_output_boost_processes', 'child_id')")
		->and($boost)->toContain("db_index_exists('poller_output_boost_processes', 'run_child')")
		->and($poller)->toContain('boost_ensure_process_table()')
		->and($poller)->not->toContain('boost_ensure_process_table(true)')
		->and($child)->toContain('boost_ensure_process_table(true)');
});

/** Column and key names from one CREATE TABLE statement. */
function boost_upgrade_table_shape(string $sql, string $table) : array {
	expect(preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`' . $table . '` \((.*?)\)\s*ENGINE/s', $sql, $match))->toBe(1);
	preg_match_all('/^\s*(?:`(\w+)`\s+(\w+)|(PRIMARY KEY|KEY `\w+`)\s*\(([^)]*)\))/m', $match[1], $lines, PREG_SET_ORDER);

	return array_map(fn ($l) => strtolower(preg_replace('/\s+/', '', isset($l[3]) ? $l[3] . $l[4] : $l[1] . ' ' . $l[2])), $lines);
}

test('the installer creates the Boost run-time tables before it requires every cacti.sql table', function () use ($root) {
	$installer = file_get_contents($root . '/lib/installer.php');
	$schema    = file_get_contents($root . '/cacti.sql');

	// in every install mode, not only in the upgrade step for one version
	expect($installer)->toMatch('/\$this->createRuntimeTables\(\);\s*\$failure = \$this->validateCoreSchema\(\);/');

	foreach (['poller_output_boost_local_data_ids', 'poller_output_boost_failures'] as $table) {
		expect($installer)->toContain('CREATE TABLE IF NOT EXISTS `' . $table . '`')
			->and(boost_upgrade_table_shape($installer, $table))->toBe(boost_upgrade_table_shape($schema, $table));
	}
});
