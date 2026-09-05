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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

function cdef_test_controller_source() : string {
	static $source;

	if (!isset($source)) {
		$source = file_get_contents(dirname(__DIR__, 4) . '/cdef.php');

		if ($source === false) {
			throw new RuntimeException('Unable to read cdef.php for controller contract tests.');
		}
	}

	return $source;
}

test('CDEF controller exposes every action handler', function () : void {
	$cdef_controller_source = cdef_test_controller_source();
	$functions              = [
		'draw_cdef_preview', 'form_save', 'duplicate_cdef', 'form_actions',
		'cdef_item_remove_confirm', 'item_movedown', 'item_moveup',
		'cdef_item_remove', 'item_edit', 'cdef_item_dnd', 'cdef_edit', 'cdef',
	];

	foreach ($functions as $function) {
		expect($cdef_controller_source)->toMatch('/function\s+' . preg_quote($function, '/') . '\s*\(/');
	}

	preg_match_all('/^function\s+([a-z0-9_]+)\s*\(/mi', $cdef_controller_source, $matches);
	expect(array_diff($functions, $matches[1]))->toBe([]);
});

test('CDEF dispatcher covers save edit reorder remove duplicate and list actions', function () : void {
	$cdef_controller_source = cdef_test_controller_source();

	foreach (['save', 'item_remove_confirm', 'item_remove', 'item_movedown', 'item_moveup', 'edit', 'item_edit', 'ajax_dnd', 'actions'] as $action) {
		expect($cdef_controller_source)->toContain("case '$action':");
	}

	expect($cdef_controller_source)->toMatch('/default:\s+top_header\(\);\s+cdef\(\);\s+bottom_footer\(\);/');
});

test('CDEF mutations validate identifiers and use bound database operations', function () : void {
	$cdef_controller_source = cdef_test_controller_source();

	expect($cdef_controller_source)->toContain("gfrv('cdef_id');")
		->toContain("gfrv('id');")
		->toContain("input_validate_input_number(\$cdef_id, 'cdef_id');")
		->toContain("db_fetch_row_prepared('SELECT * FROM cdef WHERE id = ?', [\$_cdef_id])")
		->toContain("db_fetch_assoc_prepared('SELECT * FROM cdef_items WHERE cdef_id = ?', [\$_cdef_id])")
		->toMatch('/DELETE FROM cdef_items\s+WHERE cdef_id = \?\s+AND id = \?/')
		->toMatch('/UPDATE cdef_items\s+SET sequence = \?\s+WHERE id = \?/');
});

test('CDEF save duplicate and delete flows maintain definitions and their items', function () : void {
	$cdef_controller_source = cdef_test_controller_source();
	expect($cdef_controller_source)->toContain("sql_save(\$save, 'cdef')")
		->toContain("sql_save(\$save, 'cdef_items')")
		->toContain("get_hash_cdef(0, 'cdef_item')")
		->toContain('DELETE FROM cdef WHERE ')
		->toContain('DELETE FROM cdef_items WHERE ')
		->toContain("sanitize_unserialize_selected_items(gnrv('selected_items'))")
		->toContain("duplicate_cdef(\$selected_items[\$i], gnrv('title_format'))");
});

test('CDEF edit and list views bind filters pagination and drag ordering', function () : void {
	$cdef_controller_source = cdef_test_controller_source();
	expect($cdef_controller_source)->toContain('ORDER BY sequence')
		->toContain('tableDnD({')
		->toContain('cdef_item_dnd()')
		->toContain("html_start_box(__('CDEF Preview')")
		->toContain('get_cdef($cdef_id)')
		->toContain("grv('rows')")
		->toContain("grv('page')")
		->toContain("\$sql_limit = ' LIMIT ' . (\$rows * (grv('page') - 1)) . ',' . \$rows")
		->toContain('$cdef_list = db_fetch_assoc(');
});
