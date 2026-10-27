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
require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
$GLOBALS['import_writes'] = [];
$GLOBALS['import_reads']  = 0;
function cacti_log(...$arguments): void {
}
function db_fetch_cell_prepared($sql, $parameters = []) {
	$GLOBALS['import_reads']++;

	return str_contains($sql, 'snmp_query') ? 1 : 0;
}
function db_execute_prepared(...$arguments): bool {
	$GLOBALS['import_writes'][] = $arguments;

	return true;
}
function sql_save($data, $table): int {
	$GLOBALS['import_writes'][] = [$table, $data];

	return 10;
}

foreach (['automation_validate_import_match_fields', 'automation_graph_rule_import', 'automation_tree_rule_import', 'automation_template_import'] as $function) {
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
print json_encode(['cases' => $cases]);
