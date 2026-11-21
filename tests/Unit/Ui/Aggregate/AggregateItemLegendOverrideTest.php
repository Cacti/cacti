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

namespace CactiTests\AggregateItemLegend;
require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/api_aggregate.php', 'aggregate_apply_item_legend', __NAMESPACE__);

test('aggregate item legend overrides accept empty text without changing bindings', function () {
	$item = ['text_format' => 'Traffic', 'legend' => 'Bytes', 'task_item_id' => 42, 'sequence' => 4];
	expect(aggregate_apply_item_legend($item, ['t_text_format' => 'on', 'text_format' => '', 'task_item_id' => 99]))
		->toBe(['text_format' => '', 'legend' => 'Bytes', 'task_item_id' => 42, 'sequence' => 4]);
});

test('disabled aggregate legend overrides inherit source text', function () {
	$item = ['text_format' => 'Traffic', 'legend' => 'Bytes'];
	expect(aggregate_apply_item_legend($item, ['t_text_format' => '', 'text_format' => 'Ignored', 't_legend' => '', 'legend' => 'Ignored']))->toBe($item);
});

test('aggregate export legends and text overrides are independent', function () {
	expect(aggregate_apply_item_legend(['text_format' => 'Traffic', 'legend' => 'Bytes'], ['t_legend' => 'on', 'legend' => 'Custom']))
		->toBe(['text_format' => 'Traffic', 'legend' => 'Custom']);
});

test('aggregate legend data stays literal until the output boundary', function () {
	$value = '<img src=x onerror=alert(1)> & "quotes"';
	expect(aggregate_apply_item_legend(['text_format' => 'Traffic'], ['t_text_format' => 'on', 'text_format' => $value]))->toBe(['text_format' => $value]);
});

\cacti_fixture_source_function(dirname(__DIR__, 4) . '/install/upgrades/1_3_0.php', 'upgrade_aggregate_item_overrides', __NAMESPACE__);

/**
 * Reports whether the simulated aggregate item table already has a column.
 *
 * @param string $table  The table name.
 * @param string $column The column name.
 *
 * @return bool True when the simulated upgrade already added the column.
 */
function db_column_exists(string $table, string $column) : bool {
	return in_array($table . '.' . $column, $GLOBALS['aggregate_upgrade_columns'], true);
}

/**
 * Records an upgrade statement instead of running it.
 *
 * @param string $sql The statement.
 *
 * @return int Always 1, meaning the statement succeeded.
 */
function db_install_execute(string $sql) : int {
	$GLOBALS['aggregate_upgrade_sql'][] = $sql;

	return 1;
}

/**
 * Records an added column so a second upgrade pass sees it.
 *
 * @param string $table  The table name.
 * @param array  $column The column definition.
 *
 * @return void
 */
function db_install_add_column(string $table, array $column) : void {
	// Like the real helper, adding an existing column is a no-op.
	$GLOBALS['aggregate_upgrade_columns'][$table . '.' . $column['name']] = $table . '.' . $column['name'];
}

test('upgrade keeps inherited CDEFs on items saved before overrides were applied', function () {
	$GLOBALS['aggregate_upgrade_columns'] = [];
	$GLOBALS['aggregate_upgrade_sql']     = [];

	upgrade_aggregate_item_overrides();
	$first = $GLOBALS['aggregate_upgrade_sql'];

	// A repeated upgrade must not erase a None override saved with the new editor.
	$GLOBALS['aggregate_upgrade_sql'] = [];
	upgrade_aggregate_item_overrides();

	expect($first)->toBe([
		"UPDATE aggregate_graph_templates_item SET t_cdef_id = '' WHERE t_cdef_id = 'on' AND (cdef_id = 0 OR cdef_id IS NULL)",
		"UPDATE aggregate_graphs_graph_item SET t_cdef_id = '' WHERE t_cdef_id = 'on' AND (cdef_id = 0 OR cdef_id IS NULL)",
	])->and($GLOBALS['aggregate_upgrade_sql'])->toBe([])
		->and($GLOBALS['aggregate_upgrade_columns'])->toHaveCount(8);
});
