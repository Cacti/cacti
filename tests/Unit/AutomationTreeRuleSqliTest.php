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
 * concatenated into SELECT queries in lib/api_automation.php.  Both sites
 * must validate the field against the DB column allowlist before use.
 */

$treeSavePath  = __DIR__ . '/../../automation_tree_rules.php';
$apiAutoPath   = __DIR__ . '/../../lib/api_automation.php';

// --- save handler -----------------------------------------------------------

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

// --- SQL build side ---------------------------------------------------------

test('create_host_branch does not concatenate unvalidated field into SQL', function () use ($apiAutoPath) {
	$src = file_get_contents($apiAutoPath);

	// The second-order concat site must be guarded by api_automation_column_exists.
	// The old vulnerable pattern was: $sql_field = $tree_item['field'] . ' AS source ';
	// immediately inside the else branch without any column check.
	// After the fix the concat only appears inside an elseif(api_automation_column_exists(...)).
	$guard_pos  = strpos($src, "api_automation_column_exists(\$tree_item['field']");
	$concat_pos = strpos($src, "\$sql_field = \$tree_item['field'] . ' AS source '", $guard_pos);

	expect($guard_pos)->not->toBeFalse('api_automation_column_exists guard not found for tree_item field');
	expect($concat_pos)->not->toBeFalse('field concat not found after guard');

	// The guard must appear before the concat
	expect($guard_pos)->toBeLessThan($concat_pos, 'guard must precede the SQL concat');
});

test('create_host_branch logs and skips on invalid stored field', function () use ($apiAutoPath) {
	$src = file_get_contents($apiAutoPath);

	// The else branch after the column check must log and continue, not concatenate
	expect($src)->toContain("SECURITY: Skipped automation tree item with invalid field");
	expect($src)->toContain('continue;');
});
