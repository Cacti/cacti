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
 * Runs inside the Cacti web container against a real database. Exercises the
 * support_process_tables() hook (support.php, #7353) through the real plugin-hook
 * and db_table_exists() path: every returned definition must be well formed, and
 * poller_time (a core table present after install) must survive the existence
 * gate. This crosses the database boundary the unit test injects around.
 */

chdir(dirname(__DIR__, 2));

require_once CACTI_PATH_INCLUDE . '/global.php';

function process_tables_probe_fail(string $message): void {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

// support.php runs an authenticated dispatch on include, so extract the function
// and evaluate it here. It still calls the real plugin hook and db_table_exists()
// against the container database. eval() runs only Cacti's own extracted source.
$src = file_get_contents(CACTI_PATH_BASE . '/support.php');
$matched = preg_match('/function\s+support_process_tables\s*\(\s*\)\s*:\s*array\s*\{.*?^\}/sm', $src, $m);

if ($matched !== 1) {
	process_tables_probe_fail("could not extract support_process_tables() (preg_match returned $matched)");
}

$body = preg_replace('/^function\s+support_process_tables\s*\(\s*\)/m', 'function support_process_tables_probe()', $m[0], 1, $rename_count);

if ($rename_count !== 1) {
	process_tables_probe_fail("could not rename support_process_tables() (preg_replace count $rename_count)");
}

eval($body);

$tables = support_process_tables_probe();

if (!is_array($tables) || $tables === []) {
	process_tables_probe_fail('support_process_tables() returned no definitions');
}

foreach ($tables as $key => $definition) {
	if (!is_array($definition) ||
		!is_string($definition['label'] ?? null) ||
		!is_string($definition['table'] ?? null) ||
		!is_string($definition['select'] ?? null)) {
		process_tables_probe_fail("definition '$key' is not well formed");
	}
}

// poller_time exists in every installed schema, so it must be registered.
if (!isset($tables['poller_time'])) {
	process_tables_probe_fail('the core poller_time definition is missing');
}

print "PASS support process tables docker probe\n";
