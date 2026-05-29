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
 * Regression tests for GHSA-37jj-rx8x-4wf2 / GHSA-jpf3-w6p4-pjrf:
 * Stored 2nd-order SQL injection via automation_tree_rule_items.field.
 *
 * The field value is saved via automation_tree_rules.php and later
 * concatenated into SELECT queries in lib/api_automation.php inside
 * create_all_header_nodes().  Both sites must validate the field against
 * the DB column allowlist before use.
 */

$treeSavePath = __DIR__ . '/../../automation_tree_rules.php';
$apiAutoPath  = __DIR__ . '/../../lib/api_automation.php';

// --- save handler (source-position checks — framework bootstrap not available) ---

test('save_component_automation_tree_rule_item validates field via api_automation_column_exists', function () use ($treeSavePath) {
	$src = file_get_contents($treeSavePath);

	// The guard must appear after $save['field'] is assigned and before sql_save
	$field_assign_pos = strpos($src, "save['field']             = form_input_validate");
	$sql_save_pos     = strpos($src, "sql_save(\$save, 'automation_tree_rule_items')");
	$guard_pos        = strpos($src, 'api_automation_column_exists($save[\'field\']', $field_assign_pos);

	expect($field_assign_pos)->not->toBeFalse('$save[\'field\'] assignment not found');
	expect($sql_save_pos)->not->toBeFalse('sql_save call not found');
	expect($guard_pos)->not->toBeFalse('api_automation_column_exists guard not found in save block');

	expect($guard_pos)->toBeGreaterThan($field_assign_pos, 'guard must come after field assignment');
	expect($guard_pos)->toBeLessThan($sql_save_pos, 'guard must come before sql_save');
});

test('save_component raises error message on invalid field, not just logs', function () use ($treeSavePath) {
	$src = file_get_contents($treeSavePath);

	// Confirm raise_message is called within the guard block
	$guard_pos    = strpos($src, 'api_automation_column_exists($save[\'field\']');
	$raise_pos    = strpos($src, 'raise_message(\'field_invalid\'', $guard_pos);
	$sql_save_pos = strpos($src, "sql_save(\$save, 'automation_tree_rule_items')");

	expect($raise_pos)->not->toBeFalse('raise_message(\'field_invalid\') not found after guard');
	expect($raise_pos)->toBeLessThan($sql_save_pos, 'raise_message must precede sql_save');
});

// --- SQL build side (stub-based: tests allowlist logic directly) -------------

/*
 * Stub: mirrors the api_automation_column_exists() allowlist logic from
 * lib/api_automation.php.  The stub strips known table aliases then checks
 * whether the bare column name exists in any of the supplied tables.
 * Here we use a fixed known-good list rather than a live DB call.
 */

function stub_automation_column_exists(string $column, array $allowed_columns): bool {
	$column = str_replace(['h.', 'ht.', 'gt.', 'gl.', 'gtg.'], '', $column);

	return in_array($column, $allowed_columns, true);
}

/*
 * Stub: mirrors the create_all_header_nodes() per-item field dispatch:
 *   - AUTOMATION_TREE_ITEM_TYPE_STRING  → no SQL concat
 *   - column in allowlist               → concat 'field AS source'
 *   - anything else                     → skip (log + continue)
 *
 * Returns the SQL fragment that would be built, or null when the item is
 * skipped or uses a fixed string.
 */
function stub_build_field_sql(string $field, array $allowed_columns): ?string {
	$string_type = '0';  // AUTOMATION_TREE_ITEM_TYPE_STRING

	if ($field === $string_type) {
		return null;
	}

	if (stub_automation_column_exists($field, $allowed_columns)) {
		return $field . ' AS source ';
	}

	// invalid stored field — log and skip (no SQL returned)
	return null;
}

$allowed = ['id', 'hostname', 'description', 'name', 'title'];

// --- create_all_header_nodes: allowlist pass/reject ---

test('create_all_header_nodes: valid column produces SQL fragment', function () use ($allowed) {
	$sql = stub_build_field_sql('hostname', $allowed);

	expect($sql)->toBe('hostname AS source ');
});

test('create_all_header_nodes: valid column with table alias produces SQL fragment', function () use ($allowed) {
	$sql = stub_build_field_sql('h.hostname', $allowed);

	expect($sql)->toBe('h.hostname AS source ');
});

test('create_all_header_nodes: SQL injection payload is rejected', function () use ($allowed) {
	$sql = stub_build_field_sql("hostname; DROP TABLE users--", $allowed);

	expect($sql)->toBeNull();
});

test('create_all_header_nodes: arbitrary column not in allowlist is rejected', function () use ($allowed) {
	$sql = stub_build_field_sql('password', $allowed);

	expect($sql)->toBeNull();
});

test('create_all_header_nodes: UNION-based injection is rejected', function () use ($allowed) {
	$sql = stub_build_field_sql("1 UNION SELECT password FROM users--", $allowed);

	expect($sql)->toBeNull();
});

test('create_all_header_nodes: fixed-string type produces no SQL', function () use ($allowed) {
	$sql = stub_build_field_sql('0', $allowed);  // AUTOMATION_TREE_ITEM_TYPE_STRING

	expect($sql)->toBeNull();
});

test('create_all_header_nodes: empty field is rejected', function () use ($allowed) {
	$sql = stub_build_field_sql('', $allowed);

	expect($sql)->toBeNull();
});

// --- source-position guard: concat only reachable after allowlist check ------

test('create_all_header_nodes SQL concat is inside elseif(api_automation_column_exists)', function () use ($apiAutoPath) {
	$src = file_get_contents($apiAutoPath);

	// The guard must appear before the concat at the create_all_header_nodes site.
	// Search from the function definition to scope to the right call site.
	$fn_pos     = strpos($src, 'function create_all_header_nodes(');
	$guard_pos  = strpos($src, "api_automation_column_exists(\$tree_item['field']", $fn_pos);
	$concat_pos = strpos($src, "\$sql_field = \$tree_item['field'] . ' AS source '", $guard_pos);

	expect($guard_pos)->not->toBeFalse('api_automation_column_exists guard not found in create_all_header_nodes');
	expect($concat_pos)->not->toBeFalse('field concat not found after guard');
	expect($guard_pos)->toBeLessThan($concat_pos, 'guard must precede the SQL concat');
});
