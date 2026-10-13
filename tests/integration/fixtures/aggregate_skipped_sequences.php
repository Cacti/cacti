<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.      |
 +-------------------------------------------------------------------------+
 */

// Run only against an explicitly selected disposable database. Every case
// rolls back its fixture data and calls the actual aggregate/database APIs.
if (getenv('CACTI_AGGREGATE_SKIP_TEST') !== '1') {
	fwrite(STDERR, "Disposable aggregate fixture opt-in is required.\n");
	exit(2);
}

require dirname(__DIR__, 3) . '/include/cli_check.php';
require_once CACTI_PATH_LIBRARY . '/aggregate.php';
require_once CACTI_PATH_LIBRARY . '/api_aggregate.php';

if ($database_default !== 'cacti_aggregate_skip_test') {
	fwrite(STDERR, "Refusing to modify a non-fixture database.\n");
	exit(2);
}

$_SESSION[SESS_USER_ID] = 1;
$assertions             = 0;
$failures               = [];
$check                  = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
	$assertions++;

	if (!$condition) {
		$failures[] = $message;
	}
};

// A graph ID equal to a skipped sequence must not hide the whole graph.
foreach ([false, true] as $templated) {
	foreach ([[3 => 3, 4 => 4], [], [1 => 1, 2 => 2, 3 => 3, 4 => 4]] as $skipped) {
		$check(db_begin_transaction(), 'begin percentile fixture transaction');

		try {
			$member   = 3;
			$target   = 900001;
			$template = 900002;

			if ($templated) {
				$check(db_execute_prepared('INSERT INTO aggregate_graphs (local_graph_id, graph_template_id) VALUES (?, ?)', [$target, $template]), 'seed aggregate template relationship');
			}
			$source = $templated ? 0 : $member;

			foreach ([1, 2, 3, 4] as $sequence) {
				$isComment = $sequence % 2 === 1;
				$label     = 'Skip fixture ' . $sequence;
				$check(db_execute_prepared('INSERT INTO graph_templates_item
					(graph_template_id, local_graph_id, sequence, graph_type_id, text_format, value, hard_return)
					VALUES (?, ?, ?, ?, ?, ?, ?)', [
						$template, $source, $sequence,
						$isComment ? GRAPH_ITEM_TYPE_COMMENT : GRAPH_ITEM_TYPE_HRULE,
						$isComment ? $label . '|95:bits:0:current:2|' : $label,
						$isComment ? '' : '|95:bits:0:current:2|', 'on',
					]), 'seed percentile item');
			}
			// Give the destination a starting sequence without contributing a legend.
			$check(db_execute_prepared('INSERT INTO graph_templates_item (local_graph_id, sequence, graph_type_id) VALUES (?, ?, ?)', [$target, 1, GRAPH_ITEM_TYPE_LINE1]), 'seed destination');
			aggregate_handle_ptile_type([$member], $skipped, $target, AGGREGATE_TOTAL_ALL, (string) AGGREGATE_TOTAL_TYPE_SIMILAR);
			$items = db_fetch_assoc_prepared('SELECT graph_type_id, text_format, value FROM graph_templates_item
				WHERE local_graph_id = ? AND graph_type_id IN (?, ?) AND text_format != ? ORDER BY sequence, id', [$target, GRAPH_ITEM_TYPE_COMMENT, GRAPH_ITEM_TYPE_HRULE, '']);
			$expected = 4 - count($skipped);
			$check(count($items) === $expected, 'percentile skip list must filter item sequences in both template paths');
			$separators = db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_templates_item WHERE local_graph_id = ? AND graph_type_id = ? AND text_format = ?', [$target, GRAPH_ITEM_TYPE_COMMENT, '']);
			$check((int) $separators === ($expected > 0 ? 1 : 0), 'a separator exists only when percentile rules are retained');

			foreach ($items as $index => $item) {
				$sequence = $index + 1;
				$check(str_starts_with($item['text_format'], 'Skip fixture ' . $sequence), 'retained percentile label order');
				$check(str_contains($item['text_format'] . $item['value'], ':aggregate_current:'), 'per-source percentile remains intact');
			}
		} finally {
			db_rollback_transaction();
		}
	}
}

// Exercise the complete create/update API, including TOTAL_ALL's separator CF.
$check(db_begin_transaction(), 'begin complete aggregate fixture transaction');

try {
	$member   = 3;
	$template = 900002;
	$check(db_execute_prepared('INSERT INTO graph_local (id, graph_template_id) VALUES (?, ?)', [$member, $template]), 'seed member graph');
	$check(db_execute_prepared('INSERT INTO graph_templates_graph (graph_template_id, local_graph_id, title, title_cache) VALUES (?, 0, ?, ?)', [$template, 'Skip fixture', 'Skip fixture']), 'seed graph template');

	foreach ([1 => 2, 2 => 3, 3 => 4] as $sequence => $cf) {
		$check(db_execute_prepared('INSERT INTO graph_templates_item
			(graph_template_id, local_graph_id, sequence, graph_type_id, consolidation_function_id, color_id, text_format)
			VALUES (?, ?, ?, ?, ?, 1, ?)', [$template, $member, $sequence, GRAPH_ITEM_TYPE_LINE1, $cf, 'Member ' . $sequence]), 'seed colored member item');
	}
	$localGraph = 0;
	$attributes = [
		'graph_title'       => 'Aggregate skipped sequence fixture',
		'graph_template_id' => $template,
		'graph_type'        => AGGREGATE_GRAPH_TYPE_KEEP,
		'total'             => AGGREGATE_TOTAL_ALL,
		'total_type'        => (string) AGGREGATE_TOTAL_TYPE_SIMILAR,
		'item_no'           => 3,
		'color_templates'   => [],
		'graph_item_types'  => [1 => 0, 2 => 0, 3 => 0],
		'cdefs'             => [1 => 0, 2 => 0, 3 => 0],
		'skipped_items'     => [1 => 1, 3 => 3],
		'total_items'       => [2 => 2],
	];
	$check(aggregate_create_update($localGraph, [$member], $attributes, false), 'complete aggregate creation succeeds');
	$separatorCf = db_fetch_cell_prepared('SELECT consolidation_function_id FROM graph_templates_item
		WHERE local_graph_id = ? AND graph_type_id = ? AND text_format = ? AND hard_return = ?', [$localGraph, GRAPH_ITEM_TYPE_COMMENT, '', 'on']);
	$check((int) $separatorCf === 3, 'TOTAL_ALL separator uses first non-skipped colored item CF');
} finally {
	db_rollback_transaction();
}

if ($failures) {
	fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
	exit(1);
}
print json_encode(['assertions' => $assertions, 'result' => 'passed'], JSON_THROW_ON_ERROR) . PHP_EOL;
