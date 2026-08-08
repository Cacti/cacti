<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRTool-based Graphing Solution                      |
 +-------------------------------------------------------------------------+
 */

$root = dirname(__DIR__, 2);

test('the 1.3.0 upgrade records every database mutation', function () use ($root) {
	$source = file_get_contents($root . '/install/upgrades/1_3_0.php');

	expect($source)->not->toBeFalse();
	expect($source)->toContain('db_install_execute(');
	expect($source)->toContain('db_install_update_table(');

	$untracked = preg_match_all(
		'/\b(?:db_execute|db_execute_prepared|db_update_table)\s*\(/',
		$source,
		$matches
	);

	expect($untracked)->toBe(0, 'Upgrade database mutations must use installer-aware helpers');
});

test('the plugin timestamp migration handles null values with SQL null semantics', function () use ($root) {
	$source = file_get_contents($root . '/install/upgrades/1_3_0.php');

	expect($source)->not->toBeFalse();
	expect($source)->toContain('last_updated IS NULL');
	expect($source)->not->toContain('last_updated = NULL');
});

test('table definition synchronization records its status', function () use ($root) {
	$source = file_get_contents($root . '/install/functions.php');

	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function db_install_update_table(');
	$end   = strpos($source, "\n}\n", $start);

	expect($start)->not->toBeFalse();
	expect($end)->not->toBeFalse();

	$function = substr($source, $start, $end - $start);

	expect($function)->toContain('db_update_table(');
	expect($function)->toContain('$database_last_error = false;');
	expect($function)->toContain('DB_STATUS_SUCCESS');
	expect($function)->toContain('DB_STATUS_ERROR');
	expect($function)->toContain('db_install_add_cache(');
	expect($function)->toContain('return $status;');
});
