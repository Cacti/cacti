<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 */


$root = dirname(__DIR__, 3);

/** Column and key names from one CREATE TABLE statement. */
function installer_boost_table_shape(string $sql, string $table) : array {
	expect(preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`' . $table . '` \((.*?)\)\s*ENGINE/s', $sql, $match))->toBe(1);
	preg_match_all('/^\s*(?:`(\w+)`\s+(\w+)|(PRIMARY KEY|KEY `\w+`)\s*\(([^)]*)\))/m', $match[1], $lines, PREG_SET_ORDER);

	return array_map(fn ($l) => strtolower(preg_replace('/\s+/', '', isset($l[3]) ? $l[3] . $l[4] : $l[1] . ' ' . $l[2])), $lines);
}

test('the 1.2.32 migration owns the Boost run-time table definitions', function () use ($root) {
	$upgrade   = file_get_contents($root . '/install/upgrades/1_2_32.php');
	$installer = file_get_contents($root . '/lib/installer.php');
	$schema    = file_get_contents($root . '/cacti.sql');

	expect($upgrade)->toContain('upgrade_1_2_32_boost_tables();')
		->and($installer)->not->toContain('CREATE TABLE IF NOT EXISTS `poller_output_boost_');

	foreach (['poller_output_boost_local_data_ids', 'poller_output_boost_failures'] as $table) {
		expect(installer_boost_table_shape($upgrade, $table))->toBe(installer_boost_table_shape($schema, $table));
	}
});
