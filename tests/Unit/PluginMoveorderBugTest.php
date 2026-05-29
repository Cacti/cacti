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
 * Regression tests for two bugs in lib/plugins.php that corrupt plugin_config:
 *
 * BUG 1 (moveup/movedown NULL->0 corruption):
 *   api_plugin_moveup() and api_plugin_movedown() swap plugin order by
 *   renaming rows via a three-step id rotation (current->temp, prior->current,
 *   temp->prior). When called at the boundary (first plugin moved up, last
 *   plugin moved down), the subquery MAX/MIN returns NULL. Cacti strips
 *   STRICT_TRANS_TABLES from the session SQL mode on every connection; under
 *   non-strict mode, UPDATE SET id = NULL on a NOT NULL column silently stores
 *   0, leaving the plugin with a corrupted primary key.
 *
 * BUG 2 (plugins_load_temp_table 1062 on id=0 row):
 *   plugin_config.id is AUTO_INCREMENT. Cacti also strips NO_AUTO_VALUE_ON_ZERO
 *   on connect. When an id=0 row exists in plugin_config (caused by bug 1 or a
 *   plugin upgrade script), the bulk INSERT INTO temp SELECT * FROM plugin_config
 *   reassigns the 0 to the next AUTO_INCREMENT sequence value, colliding with
 *   whatever row already holds that id and producing ERROR 1062.
 */

$libPluginsPath = __DIR__ . '/../../lib/plugins.php';
$pluginsPath    = __DIR__ . '/../../plugins.php';

// ---------------------------------------------------------------------------
// api_plugin_moveup
// ---------------------------------------------------------------------------

test('api_plugin_moveup has !empty($prior_id) guard around the three-step swap', function () use ($libPluginsPath) {
	$source = file_get_contents($libPluginsPath);

	$fn_pos    = strpos($source, 'function api_plugin_moveup(');
	$guard_pos = strpos($source, 'if (!empty($prior_id))', $fn_pos);

	expect($fn_pos)->not->toBeFalse('api_plugin_moveup not found');
	expect($guard_pos)->not->toBeFalse('!empty($prior_id) guard missing from api_plugin_moveup');
});

test('api_plugin_moveup swap executes only after the prior_id guard, not before', function () use ($libPluginsPath) {
	$source = file_get_contents($libPluginsPath);

	$fn_pos    = strpos($source, 'function api_plugin_moveup(');
	$guard_pos = strpos($source, 'if (!empty($prior_id))', $fn_pos);
	$swap_pos  = strpos($source, 'UPDATE plugin_config SET id = ? WHERE id = ?', $fn_pos);

	expect($guard_pos)->not->toBeFalse();
	expect($swap_pos)->not->toBeFalse();
	// The first UPDATE must come after the empty() guard.
	expect($swap_pos)->toBeGreaterThan($guard_pos);
});

test('api_plugin_moveup temp_id computation is inside the prior_id guard', function () use ($libPluginsPath) {
	$source = file_get_contents($libPluginsPath);

	$fn_pos      = strpos($source, 'function api_plugin_moveup(');
	$guard_pos   = strpos($source, 'if (!empty($prior_id))', $fn_pos);
	$temp_id_pos = strpos($source, '$temp_id = db_fetch_cell(\'SELECT MAX(id) FROM plugin_config\')', $fn_pos);

	expect($guard_pos)->not->toBeFalse();
	expect($temp_id_pos)->not->toBeFalse('$temp_id not found in moveup');
	// temp_id must be computed after the guard, not before the NULL check.
	expect($temp_id_pos)->toBeGreaterThan($guard_pos);
});

test('api_plugin_moveup does not assign $prior_id to any id column before the empty guard', function () use ($libPluginsPath) {
	$source = file_get_contents($libPluginsPath);

	$fn_pos    = strpos($source, 'function api_plugin_moveup(');
	$guard_pos = strpos($source, 'if (!empty($prior_id))', $fn_pos);

	// Extract the slice between function start and the guard; it must contain
	// no UPDATE statement, because any UPDATE before the guard would set id
	// to a potentially NULL $prior_id value.
	$pre_guard = substr($source, $fn_pos, $guard_pos - $fn_pos);
	expect($pre_guard)->not->toContain('UPDATE plugin_config SET id');
});

// ---------------------------------------------------------------------------
// api_plugin_movedown
// ---------------------------------------------------------------------------

test('api_plugin_movedown has outer !empty($id) guard', function () use ($libPluginsPath) {
	$source = file_get_contents($libPluginsPath);

	$fn_pos   = strpos($source, 'function api_plugin_movedown(');
	$id_guard = strpos($source, 'if (!empty($id))', $fn_pos);

	expect($fn_pos)->not->toBeFalse();
	expect($id_guard)->not->toBeFalse('!empty($id) guard missing from api_plugin_movedown');
});

test('api_plugin_movedown has !empty($next_id) guard around the three-step swap', function () use ($libPluginsPath) {
	$source = file_get_contents($libPluginsPath);

	$fn_pos    = strpos($source, 'function api_plugin_movedown(');
	$guard_pos = strpos($source, 'if (!empty($next_id))', $fn_pos);

	expect($fn_pos)->not->toBeFalse();
	expect($guard_pos)->not->toBeFalse('!empty($next_id) guard missing from api_plugin_movedown');
});

test('api_plugin_movedown swap executes only after the next_id guard, not before', function () use ($libPluginsPath) {
	$source = file_get_contents($libPluginsPath);

	$fn_pos    = strpos($source, 'function api_plugin_movedown(');
	$guard_pos = strpos($source, 'if (!empty($next_id))', $fn_pos);
	$swap_pos  = strpos($source, 'UPDATE plugin_config SET id = ? WHERE id = ?', $fn_pos);

	expect($guard_pos)->not->toBeFalse();
	expect($swap_pos)->not->toBeFalse();
	expect($swap_pos)->toBeGreaterThan($guard_pos);
});

test('api_plugin_movedown does not assign $next_id to any id column before the empty guard', function () use ($libPluginsPath) {
	$source = file_get_contents($libPluginsPath);

	$fn_pos    = strpos($source, 'function api_plugin_movedown(');
	$guard_pos = strpos($source, 'if (!empty($next_id))', $fn_pos);

	$pre_guard = substr($source, $fn_pos, $guard_pos - $fn_pos);
	expect($pre_guard)->not->toContain('UPDATE plugin_config SET id');
});

// ---------------------------------------------------------------------------
// plugins_load_temp_table sql_mode save/restore
// ---------------------------------------------------------------------------

test('plugins_load_temp_table saves @@SESSION.sql_mode before adding NO_AUTO_VALUE_ON_ZERO', function () use ($pluginsPath) {
	$source = file_get_contents($pluginsPath);

	$fn_pos   = strpos($source, 'function plugins_load_temp_table()');
	$save_pos = strpos($source, '$orig_sql_mode = db_fetch_cell(\'SELECT @@SESSION.sql_mode\')', $fn_pos);
	// Use the SET SESSION statement (not the comment above it) as the anchor so
	// the order check is not confused by the explanatory comment that also mentions
	// NO_AUTO_VALUE_ON_ZERO and appears before the save line.
	$set_pos  = strpos($source, 'db_execute("SET SESSION sql_mode = CONCAT_WS', $fn_pos);

	expect($fn_pos)->not->toBeFalse();
	expect($save_pos)->not->toBeFalse('$orig_sql_mode save not found in plugins_load_temp_table');
	expect($set_pos)->not->toBeFalse('SET SESSION sql_mode = CONCAT_WS not found in plugins_load_temp_table');
	// Save must come before the SET that adds NO_AUTO_VALUE_ON_ZERO.
	expect($save_pos)->toBeLessThan($set_pos);
});

test('plugins_load_temp_table inserts into temp table while NO_AUTO_VALUE_ON_ZERO is active', function () use ($pluginsPath) {
	$source = file_get_contents($pluginsPath);

	$fn_pos      = strpos($source, 'function plugins_load_temp_table()');
	$nav_pos     = strpos($source, 'NO_AUTO_VALUE_ON_ZERO', $fn_pos);
	$insert_pos  = strpos($source, 'INSERT INTO $table SELECT * FROM plugin_config', $fn_pos);

	expect($nav_pos)->not->toBeFalse();
	expect($insert_pos)->not->toBeFalse('bulk INSERT not found in plugins_load_temp_table');
	// INSERT must come after the mode is set.
	expect($insert_pos)->toBeGreaterThan($nav_pos);
});

test('plugins_load_temp_table restores original sql_mode after the bulk INSERT', function () use ($pluginsPath) {
	$source = file_get_contents($pluginsPath);

	$fn_pos       = strpos($source, 'function plugins_load_temp_table()');
	$insert_pos   = strpos($source, 'INSERT INTO $table SELECT * FROM plugin_config', $fn_pos);
	$restore_pos  = strpos($source, 'SET SESSION sql_mode = ?', $fn_pos);

	expect($insert_pos)->not->toBeFalse();
	expect($restore_pos)->not->toBeFalse('sql_mode restore not found in plugins_load_temp_table');
	// Restore must come after the INSERT.
	expect($restore_pos)->toBeGreaterThan($insert_pos);
});

test('plugins_load_temp_table restore uses db_execute_prepared with $orig_sql_mode', function () use ($pluginsPath) {
	$source = file_get_contents($pluginsPath);

	$fn_pos = strpos($source, 'function plugins_load_temp_table()');

	// The restore call must bind $orig_sql_mode, not a hardcoded string, so
	// the session mode is returned to exactly what it was before the copy.
	$restore_slice_start = strpos($source, 'SET SESSION sql_mode = ?', $fn_pos);
	$restore_slice       = substr($source, $restore_slice_start, 120);

	expect($restore_slice)->toContain('$orig_sql_mode');
});
