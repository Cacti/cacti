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
 * A CDEF item of type 5 refers to another CDEF by hash. The probe runs the
 * unchanged xml_to_cdef() against SQLite to check how that reference is
 * resolved and what status the imported CDEF reports.
 */

require_once dirname(__DIR__, 3) . '/Helpers/ImportExportProbe.php';

function cdef_import(array $db_cdefs, array $hash_cache, bool $preview = false): array {
	$outer = str_repeat('c', 32);
	$inner = str_repeat('d', 32);

	return cacti_import_export_probe(array(
		'slice'        => array(
			array('lib/import.php', 'xml_to_cdef'),
			array('lib/import.php', 'parse_xml_hash'),
			array('lib/import.php', 'check_hash_type'),
			array('lib/import.php', 'check_hash_version'),
			array('lib/import.php', 'xml_character_decode'),
		),
		'schema'       => array(
			"CREATE TABLE cdef (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', name TEXT DEFAULT '')",
			"CREATE TABLE cdef_items (id INTEGER PRIMARY KEY, hash TEXT DEFAULT '', cdef_id INTEGER DEFAULT 0, sequence INTEGER DEFAULT 0, type INTEGER DEFAULT 0, value TEXT DEFAULT '')",
		),
		'db'           => array('cdef' => $db_cdefs),
		'globals'      => array('preview_only' => $preview, 'fields_cdef_edit' => array('name' => array())),
		'call'         => 'xml_to_cdef',
		'arguments'    => array(
			$outer,
			array('name' => 'Outer', 'items' => array('hash_140103' . str_repeat('e', 32) => array('sequence' => '1', 'type' => '5', 'value' => 'hash_050103' . $inner))),
			$hash_cache,
		),
		'dump'         => array('cdef_items'),
		'read_globals' => array('import_debug_info'),
	));
}

test('a new CDEF that uses an existing CDEF is reported as new', function () {
	$verdict = cdef_import(array(array('id' => 7, 'hash' => str_repeat('d', 32), 'name' => 'Inner')), array());

	expect($verdict['error'])->toBeNull()
		->and($verdict['globals']['import_debug_info']['type'])->toBe('new')
		->and($verdict['tables']['cdef_items'][0]['value'])->toBe('7');
});

test('a CDEF imported earlier from the same file is found through the hash cache', function () {
	$verdict = cdef_import(array(), array('cdef' => array(str_repeat('d', 32) => 9)));

	expect($verdict['error'])->toBeNull()
		->and($verdict['tables']['cdef_items'][0]['value'])->toBe('9');
});
