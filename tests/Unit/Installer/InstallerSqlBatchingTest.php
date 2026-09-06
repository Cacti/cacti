<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$source = file_get_contents(CACTI_PATH_LIBRARY . '/installer.php');

if ($source === false) {
	throw new RuntimeException('Unable to read lib/installer.php for SQL batching tests.');
}

test('default automation template hashes are resolved in one prepared query', function () use ($source) {
	expect($source)
		->toContain('private function getDefaultAutomationTemplateIds() : array')
		->toContain('WHERE hash IN ($placeholders)')
		->toContain('$template_ids  = $this->getDefaultAutomationTemplateIds();')
		->not->toContain("db_fetch_cell_prepared('SELECT id\n\t\t\t\t\tFROM host_template\n\t\t\t\t\tWHERE hash = ?'");
});

test('automation mappings are loaded once before the template loop', function () use ($source) {
	expect($source)
		->toContain('WHERE host_template IN ($placeholders)')
		->toContain('if (!isset($mapped_ids[$host_template_id]))')
		->not->toContain("db_fetch_cell_prepared('SELECT host_template\n\t\t\t\t\t\tFROM automation_templates");
});

test('collector names are fetched in one query while preserving log order', function () use ($source) {
	if (preg_match('/private static function fullSyncDataCollectorLog\(.*?^\t}\R/ms', $source, $matches) !== 1) {
		throw new RuntimeException('Unable to extract fullSyncDataCollectorLog() for SQL batching tests.');
	}

	expect($matches[0])
		->toContain('WHERE id IN ($placeholders)')
		->toContain('foreach ($poller_ids as $id)')
		->toContain('$poller = $pollers[$id] ?? false;')
		->not->toContain('SELECT name FROM poller WHERE id = ?');
});
