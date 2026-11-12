<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Runs the real lib/api_aggregate.php with only its database, graph-template and
// HTML helpers replaced by recording stand-ins. Each scenario feeds those
// stand-ins the rows a stored graph or template would return and asserts the
// aggregate the library writes or draws.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/FixtureStubs.php';

$root   = dirname(__DIR__, 3);
$source = $root . '/lib/api_aggregate.php';

require_once $root . '/include/global_constants.php';
require_once $root . '/include/global_path.php';

/**
 * Stand-in for the library's error handler, which it registers by name.
 *
 * @return bool Always false, so PHP continues with its own handling
 */
function aggregate_error_handler() : bool {
	return false;
}

cacti_fixture_stub_functions(cacti_fixture_called_functions($source, []), '');
require_once $source;

$GLOBALS['fx'] = [];
$passed        = 0;

/**
 * Reset the recorded calls and set the next scenario's database answers.
 *
 * @param array<string, mixed> $answers Rows keyed by a distinctive SQL fragment
 *
 * @return void
 */
function agg_scenario(array $answers = []) : void {
	$GLOBALS['cacti_fixture_calls'] = [];
	$GLOBALS['fx']                  = $answers + ['saves' => [], 'logs' => []];
}

/**
 * Check a fixture expectation.
 *
 * @param bool   $condition The expectation
 * @param string $label     What it checks
 *
 * @return void
 */
function agg_check(bool $condition, string $label) : void {
	global $passed;
	cacti_fixture_assert($condition, $label);
	$passed++;
}

/**
 * Return the configured rows whose SQL fragment appears in the statement.
 *
 * @param string $sql The statement
 *
 * @return mixed The rows, or an empty list
 */
function agg_rows_for(string $sql) : mixed {
	foreach ($GLOBALS['fx']['rows'] ?? [] as $fragment => $rows) {
		if (str_contains($sql, $fragment)) {
			return $rows;
		}
	}

	return [];
}

$GLOBALS['cacti_fixture_returns'] = [
	'cacti_sizeof'            => static fn (mixed $value) : int => is_countable($value) ? count($value) : 0,
	'array_to_sql_or'         => static fn (array $items, string $column) : string => $column . ' IN (' . implode(',', $items) . ')',
	'array_rekey'             => static function (mixed $rows, string $key, mixed $values) : array {
		$out = [];

		foreach (is_array($rows) ? $rows : [] as $row) {
			$out[$row[$key]] = array_intersect_key($row, array_flip((array) $values));
		}

		return $out;
	},
	'auto_hr'                     => static fn (array $skip, array $hr) : array => $hr,
	'aggregate_cdef_make0'        => 99,
	'aggregate_change_graph_type' => static fn (int $index, int $type, int $requested) : int => $type,
	'substitute_host_data'        => static fn (string $text) : string => $text,
	'substitute_snmp_query_data'  => static fn (string $text) : string => $text,
	'read_config_option'          => '30',
	'db_qstr'                     => static fn (mixed $value) : string => "'" . addslashes((string) $value) . "'",
	'db_fetch_assoc_prepared'     => static function (string $sql, array $params = []) : mixed {
		$GLOBALS['fx']['queries'][] = [$sql, $params];

		return agg_rows_for($sql);
	},
	'db_fetch_assoc'          => static fn (string $sql) : mixed => agg_rows_for($sql),
	'db_fetch_row_prepared'   => static fn (string $sql, array $params = []) : mixed => agg_rows_for($sql),
	'db_fetch_cell_prepared'  => 5,
	'db_fetch_cell'           => 0,
	'db_execute'              => 1,
	'db_execute_prepared'     => true,
	'sql_save'                => static function (array $save, string $table) : int {
		$GLOBALS['fx']['saves'][] = [$table, $save];

		return 40 + count($GLOBALS['fx']['saves']);
	},
	'db_begin_transaction'            => true,
	'db_commit_transaction'           => true,
	'aggregate_is_pure_stacked_graph' => false,
	'aggregate_is_stacked_graph'      => false,
	'aggregate_cdef_totalling'        => true,
	'__'                              => static fn (string $text, mixed ...$arguments) : string => $arguments ? vsprintf($text, $arguments) : $text,
	'__esc'                           => static fn (string $text) : string => $text,
	'htmle'                           => static fn (mixed $text) : string => htmlspecialchars((string) $text),
	'html_escape_attr'                => static fn (mixed $text) : string => htmlspecialchars((string) $text),
];

// Legend overrides: a stored template's overrides are read once and keyed by item.
agg_scenario(['rows' => ['aggregate_graph_templates_item' => [['graph_templates_item_id' => 7, 'legend' => 'A']]]]);
agg_check(aggregate_item_legend_overrides(0, 0) === [], 'a graph with neither template nor aggregate has no overrides');
agg_check(aggregate_item_legend_overrides(3, 9) === [7 => ['graph_templates_item_id' => 7, 'legend' => 'A']], 'template overrides are keyed by item');
agg_check(str_contains($GLOBALS['fx']['queries'][0][0], 'FROM aggregate_graph_templates_item') && $GLOBALS['fx']['queries'][0][1] === [3], 'a templated aggregate reads its template');
agg_scenario(['rows' => ['aggregate_graphs_graph_item' => [['graph_templates_item_id' => 8, 'legend' => 'B']]]]);
agg_check(array_keys(aggregate_item_legend_overrides(0, 9)) === [8] && $GLOBALS['fx']['queries'][0][1] === [9], 'a standalone aggregate reads its own items');
agg_scenario(['rows' => ['aggregate_graphs_graph_item' => false]]);
agg_check(aggregate_item_legend_overrides(0, 9) === false, 'a failed read is reported');

/**
 * Build one stored graph item.
 *
 * @param array<string, mixed> $override Fields that differ from an AREA item
 *
 * @return array<string, mixed> The graph item row
 */
function agg_graph_item(array $override = []) : array {
	return $override + [
		'id'          => 100, 'local_graph_template_item_id' => 100, 'graph_type_id' => GRAPH_ITEM_TYPE_AREA, 'text_format' => 'Traffic',
		'hard_return' => '', 'cdef_id' => 0, 'color_id' => 5, 'sequence' => 1, 'task_item_id' => 9, 'consolidation_function_id' => 1,
		'value'       => '', 'legend' => '', 'vdef_id' => 0, 'gprint_id' => 0, 'hash' => '', 'graph_template_id' => 2, 'local_graph_id' => 10,
		'cdef_name'   => '', 'vdef_name' => '', 'gprint_name' => '', 'hex' => '', 'textalign' => '', 'data_source_name' => 'in',
	];
}

$items = [agg_graph_item(), agg_graph_item(['id' => 101, 'local_graph_template_item_id' => 101, 'graph_type_id' => GRAPH_ITEM_TYPE_COMMENT, 'text_format' => 'Cur |sum:auto:bits:current:2|', 'sequence' => 2]),
	agg_graph_item(['id' => 102, 'local_graph_template_item_id' => 102, 'graph_type_id' => GRAPH_ITEM_TYPE_HRULE, 'text_format' => '', 'value' => '|sum:auto:bits:max:2|', 'sequence' => 3])];

// Copying a member graph's items: an item's enabled text override replaces its
// text before the prefix is applied, and an item's CDEF override replaces the
// inherited CDEF.
agg_scenario(['rows' => ['FROM graph_local' => ['host_id' => 1, 'graph_template_id' => 2, 'snmp_query_id' => 0, 'snmp_index' => ''], 'FROM graph_templates_item' => [agg_graph_item()]]]);
$next = aggregate_graphs_insert_graph_items(11, 10, 2, [], [], 1, 0, [], [1 => 0], [1 => 7], 0, 'pre', '', AGGREGATE_TOTAL_NONE, '', [10],
	[100 => ['t_text_format' => 'on', 'text_format' => 'Custom']]);
agg_check($next === 2 && count($GLOBALS['fx']['saves']) === 1, 'one member item is copied');
agg_check($GLOBALS['fx']['saves'][0][1]['text_format'] === 'pre Custom' && $GLOBALS['fx']['saves'][0][1]['cdef_id'] === 7 && $GLOBALS['fx']['saves'][0][1]['local_graph_id'] === 11, 'the override text, prefix and CDEF are stored');

// A template item has no source template item, so its own ID selects the override.
agg_scenario(['rows' => ['FROM graph_templates_item' => [agg_graph_item()]]]);
aggregate_graphs_insert_graph_items(11, 0, 2, [], [], 1, 0, [], [1 => 0], [], 0, 'pre', '', AGGREGATE_TOTAL_NONE, '', [], [100 => ['t_text_format' => 'on', 'text_format' => '']]);
agg_check($GLOBALS['fx']['saves'][0][1]['text_format'] === 'pre', 'an empty text override is kept');

// Saving aggregate items stores every text and legend override.
$stored = ['aggregate_template_id' => 3, 'graph_templates_item_id' => 7, 'sequence' => 1, 't_text_format' => 'on', 'text_format' => 'T', 't_legend' => 'on', 'legend' => 'L'];
agg_scenario();
agg_check(aggregate_graph_items_save([$stored], 'aggregate_graph_templates_item') === true, 'items are saved');
$statements = array_column(cacti_fixture_stub_arguments('db_execute'), 0);
agg_check(str_contains($statements[0], 'DELETE FROM aggregate_graph_templates_item WHERE aggregate_template_id=3') && str_contains($statements[1], "'on', 'T', 'on', 'L'"), 'the old items are replaced with the legend overrides');
agg_check(aggregate_graph_items_save([$stored], 'other_table') === false && aggregate_graph_items_save([['graph_templates_item_id' => 7]], 'aggregate_graph_templates_item') === false, 'an unknown table or an item without an owner is refused');
$GLOBALS['cacti_fixture_returns']['db_execute'] = 0;
agg_check(aggregate_graph_items_save([$stored], 'aggregate_graph_templates_item') === false, 'a rejected insert is reported');
$GLOBALS['cacti_fixture_returns']['db_execute'] = 1;
agg_check(aggregate_graph_items_save([['aggregate_graph_id' => 4, 'graph_templates_item_id' => 8]], 'aggregate_graphs_graph_item') === true, 'a standalone aggregate stores its own items');

// An unreadable override set rolls the whole update back.
$attributes = ['aggregate_template_id' => 3, 'graph_template_id' => 2, 'graph_title' => 'Combined', 'color_templates' => [], 'graph_item_types' => [1 => 0, 2 => 0, 3 => 0], 'cdefs' => [], 'skipped_items' => [], 'total_items' => []];
agg_scenario(['rows' => ['FROM aggregate_graph_templates_item' => false]]);
$graph = 0;
agg_check(aggregate_create_update($graph, [10, 12], $attributes) === false && $graph === 0, 'an unreadable override set fails the update');
agg_check(cacti_fixture_stub_arguments('db_rollback_transaction') !== [] && cacti_fixture_stub_arguments('raise_message') === [[2]], 'the update is rolled back with a message');

// A totalled aggregate copies each member, adds the totals and the percentile rows.
agg_scenario(['rows' => ['FROM aggregate_graph_templates_item' => [], 'FROM graph_templates_item' => $items, 'FROM graph_local' => ['host_id' => 1, 'graph_template_id' => 2, 'snmp_query_id' => 0, 'snmp_index' => '']]]);
$graph  = 0;
$totals = ['total' => AGGREGATE_TOTAL_ALL, 'total_type' => AGGREGATE_TOTAL_TYPE_ALL, 'item_no' => 2, 'gprint_prefix' => 'Sum'] + $attributes;
agg_check(aggregate_create_update($graph, [10, 12], $totals) === true && $graph === 41, 'a totalled aggregate is created');
agg_check(count(cacti_fixture_stub_arguments('aggregate_cdef_totalling')) === 1 && cacti_fixture_stub_arguments('db_commit_transaction') !== [], 'the totals are built and committed');

// Percentile comments and rules keep their enabled legend override.
$percentile = [agg_graph_item(['id' => 201, 'graph_type_id' => GRAPH_ITEM_TYPE_COMMENT, 'text_format' => 'Cur |sum:auto:bits:current:2|', 'hard_return' => '']),
	agg_graph_item(['id' => 202, 'graph_type_id' => GRAPH_ITEM_TYPE_HRULE, 'text_format' => '', 'value' => '|sum:auto:bits:max:2|', 'task_item_id' => 9])];
agg_scenario(['rows' => ['FROM aggregate_graphs' => ['graph_template_id' => 2], 'FROM graph_templates_item' => $percentile]]);
aggregate_handle_ptile_type([10], [], 40, AGGREGATE_TOTAL_ALL, AGGREGATE_TOTAL_TYPE_ALL, [201 => ['t_legend' => 'on', 'legend' => 'Peak'], 202 => ['t_legend' => 'on', 'legend' => 'Limit']]);
$inserts = cacti_fixture_stub_arguments('db_execute_prepared');
agg_check(count($inserts) === 3 && $inserts[0][1][5] === 'Peak' && $inserts[2][1][6] === 'Limit' && str_contains($inserts[2][1][7], 'aggregate_sum_peak'), 'the percentile rows carry their legends');

// The item list shows an editable link only where the item can be edited.
$GLOBALS['graph_item_types']        = [GRAPH_ITEM_TYPE_AREA => 'AREA', GRAPH_ITEM_TYPE_COMMENT => 'COMMENT', GRAPH_ITEM_TYPE_HRULE => 'HRULE'];
$GLOBALS['consolidation_functions'] = [1 => 'AVERAGE'];
$listed                             = [agg_graph_item(['hex' => 'FF0000']), $items[1], $items[2]];
$draw                               = static function (int $graph, int $template, array $object, array $stored = []) use ($listed) : string {
	agg_scenario(['rows' => ['FROM graph_templates_item AS gti' => $listed, 'FROM color_templates' => [['color_template_id' => 1, 'name' => 'Blues']],
		'FROM aggregate_graph'                                     => $stored]]);
	ob_start();
	draw_aggregate_graph_items_list($graph, $template, $object);

	return (string) ob_get_clean();
};
$override = [['graph_templates_item_id' => 100, 't_text_format' => 'on', 'text_format' => 'Stored', 'item_skip' => 'on', 'item_total' => '']];
agg_check(!str_contains($draw(11, 0, []), '<a href') && str_contains($draw(11, 0, []), 'Item # 1'), 'a new aggregate lists plain item numbers');
agg_check(!str_contains($draw(11, 0, ['id' => 5, 'aggregate_template_id' => 3], $override), '<a href') && str_contains($draw(11, 0, ['id' => 5, 'aggregate_template_id' => 3], $override), 'Stored'), 'a templated aggregate lists its stored text without links');
agg_check(str_contains($draw(0, 2, ['id' => 3], $override), 'aggregate_graphs.php?action=item_edit&amp;aggregate_template_id=3&amp;id=100'), 'a template links each item to its editor');
agg_check(!str_contains($draw(11, 0, ['id' => 5, 'aggregate_template_id' => 0, 'local_graph_id' => 77], $override), '<a href'), 'an untemplated aggregate without a graph template lists plain item numbers');
agg_check($draw(0, 0, []) === '', 'nothing is drawn without a graph or template');

print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
