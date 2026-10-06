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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

/*
 * Regression coverage for the 1.3.0 forced-re-run resilience fix: once the
 * login_providers migration has dropped the legacy user_domains tables, the
 * upgrade must skip the LDAP conversion and the legacy column migrations
 * rather than issue ALTER statements against tables that no longer exist.
 *
 * The installer suite inspects the upgrade source (it cannot run the migration
 * without a live database), so these assertions fail if either table guard is
 * removed - the condition that originally caused the reported hard failure.
 */

$upgrade = dirname(__DIR__, 3) . '/install/upgrades/1_3_0.php';

test('ldap_convert_1_3_0 returns before any SQL when the legacy table is absent', function () use ($upgrade) {
	$source = file_get_contents($upgrade);
	expect($source)->not->toBeFalse();

	$start = strpos($source, 'function ldap_convert_1_3_0() : void {');
	expect($start)->not->toBeFalse();

	$end = strpos($source, "\n}\n", (int) $start);
	expect($end)->not->toBeFalse();

	$body = substr($source, (int) $start, (int) $end - (int) $start);

	$guard    = strpos($body, "!db_table_exists('user_domains_ldap')");
	$return   = strpos($body, 'return;');
	$firstSql = strpos($body, 'db_install_execute(');

	expect($guard)->not->toBeFalse();
	expect($return)->not->toBeFalse();
	expect($firstSql)->not->toBeFalse();

	// The existence guard and its early return must precede every ALTER.
	expect($guard)->toBeLessThan($return);
	expect($return)->toBeLessThan($firstSql);
});

test('the legacy user_domains column migrations are gated on the table existing', function () use ($upgrade) {
	$source = file_get_contents($upgrade);
	expect($source)->not->toBeFalse();

	expect($source)
		->toContain("if (db_table_exists('user_domains') && !db_column_exists('user_domains', 'debug'))")
		->toContain("if (db_table_exists('user_domains_ldap') && !db_column_exists('user_domains_ldap', 'network_timeout'))")
		->toContain("if (db_table_exists('user_domains_ldap') && !db_column_exists('user_domains_ldap', 'bind_timeout'))");

	// The pre-fix unguarded form against the legacy tables must never return.
	expect($source)
		->not->toContain("if (!db_column_exists('user_domains',")
		->not->toContain("if (!db_column_exists('user_domains_ldap',");
});
