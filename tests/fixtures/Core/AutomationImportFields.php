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
 * Runs a probe script in a child PHP process and returns its JSON verdict.
 *
 * A test that has to fake a Cacti runtime function must declare a name that
 * lib/ already owns. PHP function declarations are process-global and cannot
 * be undone, so the fake and the real declaration cannot coexist: whichever
 * lands first wins, and the loser fatals with "Cannot redeclare". Because
 * PHPUnit loads every file of a suite into one process, that turns the suite
 * into a load-order lottery -- a new test file that requires lib/database.php
 * is enough to break an unrelated test that fakes db_execute_prepared().
 *
 * Probe scripts sidestep the whole problem by owning their own process. They
 * live under a fixtures/ directory so PHPUnit does not collect them, declare
 * their fakes freely, and print a JSON verdict the parent asserts against.
 */

namespace CactiAutomationImportFixture;
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
$GLOBALS['import_writes'] = [];
$GLOBALS['import_reads']  = 0;
function cacti_log(...$arguments): void {
}
$GLOBALS['export_rows'] = [];
function db_fetch_cell_prepared($sql, $parameters = []) {
	$GLOBALS['import_reads']++;

	if (str_contains($sql, 'SELECT hash')) {
		return str_contains($sql, 'snmp_query_graph') ? 'graph-hash' : 'query-hash';
	}

	if (str_starts_with($sql, 'SELECT CONCAT')) {
		return 'fixture';
	}

	return str_contains($sql, 'snmp_query') ? 1 : 0;
}
function db_fetch_row_prepared($sql, $parameters = []) {
	return $GLOBALS['export_rows']['rule'];
}
function db_fetch_assoc_prepared($sql, $parameters = []) {
	return str_contains($sql, 'automation_match_rule_items') ? $GLOBALS['export_rows']['match'] : [];
}
function db_fetch_assoc($sql) {
	return str_contains($sql, 'host_snmp_cache') ? [['field_name' => 'ifAlias']] : [['Field' => 'id', 'Type' => 'int'], ['Field' => 'name', 'Type' => 'varchar(100)']];
}
function db_execute_prepared(...$arguments): bool {
	$GLOBALS['import_writes'][] = $arguments;

	return true;
}
function sql_save($data, $table): int {
	$GLOBALS['import_writes'][] = [$table, $data];

	return 10;
}

foreach (['automation_query_field_table_aliases', 'get_query_fields', 'array_minus', 'automation_validate_import_match_fields', 'automation_graph_rule_import', 'automation_tree_rule_import', 'automation_template_import', 'automation_graph_rule_export', 'automation_tree_rule_export'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/api_automation.php', $function, __NAMESPACE__);
}
$cases = 0;

foreach (['h.hostname;label', 'h..hostname', 'h.hostname+1', ['hostname'], null] as $field) {
	$graph = ['graph_rules' => [['graph_match_items' => [['field' => $field]]]]];
	$tree  = ['tree_rules' => [['tree_match_items' => [['field' => $field]]]]];

	foreach ([fn () => automation_graph_rule_import($graph), fn () => automation_tree_rule_import($tree), fn () => automation_template_import(['device' => [['graph_rules' => $graph['graph_rules']]]]), fn () => automation_template_import(['device' => [['tree_rules' => $tree['tree_rules']]]])] as $import) {
		$GLOBALS['import_writes'] = [];
		$GLOBALS['import_reads']  = 0;
		$result                   = $import();

		if (empty($result['errors']) || $GLOBALS['import_writes'] !== [] || $GLOBALS['import_reads'] !== 0) {
			throw new \RuntimeException('Malformed imported field reached database operations.');
		}
		$cases++;
	}
}

foreach (['ifAlias', 'h.hostname', 'ht.name', 'gl.id', 'gt.id', 'gtg.name', '_custom'] as $field) {
	$items = [['hash' => 'match-hash', 'field' => $field, 'operation' => 1]];
	$graph = ['hash' => 'graph-rule-hash', 'name' => 'Fixture', 'snmp_query_id' => 'query-hash', 'graph_type_id' => 'graph-hash', 'graph_rule_items' => [], 'graph_match_items' => $items];
	$tree  = ['hash' => 'tree-rule-hash', 'name' => 'Fixture', 'tree_rule_items' => [], 'tree_match_items' => $items];

	foreach ([fn () => automation_graph_rule_import(['graph_rules' => [$graph]]), fn () => automation_tree_rule_import(['tree_rules' => [$tree]])] as $import) {
		$GLOBALS['import_writes'] = [];
		$result                   = $import();

		if (!empty($result['errors']) || count($GLOBALS['import_writes']) !== 2 || $GLOBALS['import_writes'][1][1]['field'] !== $field) {
			throw new \RuntimeException('Valid imported field changed or was rejected.');
		}
		$cases++;
	}
}

foreach ([AUTOMATION_OPER_LEFT_BRACKET, AUTOMATION_OPER_RIGHT_BRACKET, (string) AUTOMATION_OPER_LEFT_BRACKET, (string) AUTOMATION_OPER_RIGHT_BRACKET] as $operation) {
	$debug = [];

	if (!automation_validate_import_match_fields([['items' => [['field' => '', 'operation' => $operation]]]], 'items', $debug)) {
		throw new \RuntimeException('Exported grouping item was rejected.');
	}
	$cases++;
}

// Every prefix the rule editor offers must survive import, so the two cannot drift.
foreach (['host_template', 'host', 'sites', 'host_snmp_cache', 'graph_templates'] as $table) {
	$debug = [];

	foreach (array_keys(get_query_fields($table, ['id'])) as $field) {
		if (!automation_validate_import_match_fields([['items' => [['field' => $field, 'operation' => '1']]]], 'items', $debug)) {
			throw new \RuntimeException('Rule editor field ' . $field . ' was rejected on import.');
		}
		$cases++;
	}
}

/* Rows as automation_match_rule_items returns them for ht.name = Linux AND
 * (s.name = HQ OR hsc.ifAlias = uplink). The AND before the bracket has no
 * field because the bracket item cannot carry its own conjunction. */
$match_rows = [];

foreach ([['0', 'ht.name', 'Linux'], ['1', '', ''], ['3', 's.name', 'HQ'], ['2', 'hsc.ifAlias', 'uplink'], ['4', '', '']] as $sequence => [$operation, $field, $pattern]) {
	$match_rows[] = ['id' => (string) ($sequence + 20), 'hash' => 'match-hash-' . $sequence, 'rule_id' => '7', 'rule_type' => '1', 'sequence' => (string) ($sequence + 1), 'operation' => $operation, 'field' => $field, 'operator' => '1', 'pattern' => $pattern];
}

$exports = [
	'graph' => [['id' => '7', 'hash' => 'graph-rule-hash', 'name' => 'Fixture', 'snmp_query_id' => '1', 'graph_type_id' => '2', 'enabled' => 'on'], fn () => automation_graph_rule_export(7), fn ($json) => automation_graph_rule_import($json)],
	'tree'  => [['id' => '7', 'hash' => 'tree-rule-hash', 'name' => 'Fixture', 'tree_id' => '1', 'tree_item_id' => '0', 'leaf_type' => '2', 'host_grouping_type' => '1', 'enabled' => 'on'], fn () => automation_tree_rule_export(7), fn ($json) => automation_tree_rule_import($json)],
];

foreach ($exports as [$rule_row, $export, $import]) {
	$GLOBALS['export_rows']   = ['rule' => $rule_row, 'match' => $match_rows];
	$json                     = json_decode(json_encode($export()), true);
	$GLOBALS['import_writes'] = [];
	$result                   = $import($json);
	$fields                   = array_map(fn ($write) => $write[1]['field'], array_slice($GLOBALS['import_writes'], 1));

	if (!empty($result['errors']) || $fields !== array_column($match_rows, 'field')) {
		throw new \RuntimeException('Exported rule with site, SNMP cache and conjunction items did not round trip.');
	}
	$cases++;
}

// A field-less item is only meaningful as a conjunction or bracket; unknown aliases stay rejected.
foreach ([['', '0'], ['', 0], ['', '5'], ['', null], ['', '1 '], ['x.name', '1'], ['hscx.ifAlias', '1'], ['s.name.extra', '1']] as [$field, $operation]) {
	$debug = [];

	if (automation_validate_import_match_fields([['items' => [['field' => $field, 'operation' => $operation]]]], 'items', $debug) || empty($debug['errors'])) {
		throw new \RuntimeException('Invalid match item was accepted.');
	}
	$cases++;
}

// A rule list, rule or match item list of the wrong shape is refused before any field is read.
foreach (['not-a-list', ['not-a-rule'], [['items' => 'not-a-list']]] as $rules) {
	$debug = [];

	if (automation_validate_import_match_fields($rules, 'items', $debug) || empty($debug['errors'])) {
		throw new \RuntimeException('Malformed rule structure was accepted.');
	}
	$cases++;
}

print json_encode(['cases' => $cases]);
