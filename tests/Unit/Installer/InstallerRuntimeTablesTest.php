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

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 3) . '/lib/installer.php';

/** Column and key definitions of one CREATE TABLE statement, split at top-level commas, whitespace and quoting removed. */
function installer_table_shape(string $sql, string $table) : array {
	expect(preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`' . $table . '` \((.*?)\)\s*ENGINE=(\w+)/s', $sql, $match))->toBe(1);

	$parts = [];
	$depth = 0;
	$part  = '';

	foreach (str_split($match[1]) as $char) {
		$depth += $char === '(' ? 1 : ($char === ')' ? -1 : 0);

		if ($char === ',' && $depth === 0) {
			$parts[] = $part;
			$part    = '';
		} else {
			$part .= $char;
		}
	}
	$parts[] = $part;

	return array_map(fn ($definition) => strtolower(preg_replace('/[\s`]+/', '', $definition) . '|' . $match[2]), array_filter(array_map('trim', $parts)));
}

test('the installer creates the Boost run-time table before it requires every cacti.sql table', function () {
	$installer = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');
	$schema    = file_get_contents(CACTI_PATH_BASE . '/cacti.sql');

	// the final check runs in every mode, so the table has to exist before it
	expect($installer)->toMatch('/\$this->createRuntimeTables\(\);\s*\$failure = \$this->validateRequiredSchema\(\);/')
		->and(installer_table_shape($installer, 'poller_output_boost_local_data_ids'))
		->toBe(installer_table_shape($schema, 'poller_output_boost_local_data_ids'));
});

test('every table a run-time creator provides is covered by the installer', function () {
	$installer = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');
	$boost     = file_get_contents(CACTI_PATH_BASE . '/poller_boost.php');

	preg_match_all('/CREATE TABLE IF NOT EXISTS (?:`)?(poller_output_boost_\w+)/', $boost, $runtime);

	foreach (array_unique($runtime[1]) as $table) {
		if (str_contains(file_get_contents(CACTI_PATH_BASE . '/cacti.sql'), 'CREATE TABLE `' . $table . '`')) {
			expect($installer)->toContain('CREATE TABLE IF NOT EXISTS `' . $table . '`');
		}
	}
});
