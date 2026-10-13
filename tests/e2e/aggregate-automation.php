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
if (PHP_SAPI !== 'cli' || getenv('CACTI_AGGREGATE_TEST_DISPOSABLE') !== '1' || !is_file('/tmp/cacti-aggregate-automation-disposable')) {
	throw new RuntimeException('Use only the disposable aggregate automation E2E fixture.');
}
require dirname(__DIR__, 2) . '/include/cli_check.php';
require_once CACTI_PATH_LIBRARY . '/api_automation_aggregate.php';
require_once CACTI_PATH_LIBRARY . '/api_aggregate.php';
require_once CACTI_PATH_LIBRARY . '/aggregate.php';
require_once CACTI_PATH_LIBRARY . '/api_graph.php';
require_once CACTI_PATH_LIBRARY . '/data_query.php';
require_once CACTI_PATH_LIBRARY . '/template.php';
unset($_SESSION[SESS_USER_ID]);
$checks = 0;
function aggregate_fixture_check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	$GLOBALS['checks']++;
}
function aggregate_fixture_members(int $host_id) : array {
	return array_map('intval', array_column(automation_aggregate_rows('SELECT agi.local_graph_id
		FROM aggregate_graphs_items AS agi
		INNER JOIN aggregate_graphs AS ag ON ag.id = agi.aggregate_graph_id
		INNER JOIN automation_aggregate_graphs AS aam ON aam.local_graph_id = ag.local_graph_id
		WHERE aam.aggregate_template_id = 800001 AND aam.host_id = ? ORDER BY agi.local_graph_id', [$host_id]), 'local_graph_id'));
}

if (($argv[1] ?? '') === 'worker') {
	$result = automation_execute_device_aggregates(900004);
	aggregate_fixture_check(in_array($result[800001] ?? '', ['created', 'unchanged', 'busy'], true), 'Concurrent worker failed.');
	exit;
}
aggregate_fixture_check((int) db_fetch_cell('SELECT COUNT(*) FROM graph_local') === 0, 'Use a fresh E2E database.');
require_once CACTI_PATH_BASE . '/install/functions.php';
require_once CACTI_PATH_BASE . '/install/upgrades/1_3_0.php';
$cacti_upgrade_version = '1.3.0';
$fresh_columns         = automation_aggregate_rows('SHOW COLUMNS FROM aggregate_graph_templates');
$fresh_ownership       = automation_aggregate_rows('SHOW CREATE TABLE automation_aggregate_graphs');

foreach (['automation_enabled', 'automation_pattern', 'automation_title', 'automation_host_template_id', 'automation_max_members'] as $column) {
	db_execute('ALTER TABLE aggregate_graph_templates DROP COLUMN ' . $column);
}
db_execute('DROP TABLE automation_aggregate_graphs');
upgrade_aggregate_automation_schema();
aggregate_fixture_check(automation_aggregate_rows('SHOW COLUMNS FROM aggregate_graph_templates') === $fresh_columns, 'Upgrade columns differ from fresh-install columns.');
aggregate_fixture_check(automation_aggregate_rows('SHOW CREATE TABLE automation_aggregate_graphs') === $fresh_ownership, 'Upgrade ownership table differs from a fresh install.');
upgrade_aggregate_automation_schema();
aggregate_fixture_check(automation_aggregate_rows('SHOW CREATE TABLE automation_aggregate_graphs') === $fresh_ownership, 'Re-running the upgrade changed ownership schema.');
set_config_option('auth_method', '1');
set_config_option('log_verbosity', '1');
db_execute_prepared("UPDATE user_auth SET password = ?, must_change_password = '', login_opts = 1 WHERE id = 1", [password_hash('aggregate-fixture-pass', PASSWORD_DEFAULT)]);
db_execute("INSERT INTO graph_templates (id, hash, name) VALUES (800001, '', 'Aggregate automation fixture')");
db_execute("INSERT INTO aggregate_graph_templates
	(id, name, graph_template_id, gprint_prefix, graph_type, total, total_type, total_prefix, order_type, user_id,
	 automation_enabled, automation_pattern, automation_title, automation_max_members)
	VALUES (800001, 'QoS classes', 800001, '', 4, 0, 0, '', 0, 1, 'on', '/QoS.*(Gold|Silver)/i', '|host_description| - |aggregate_template_name|', 1000)");
db_execute("INSERT INTO aggregate_graph_templates_item
	(aggregate_template_id, graph_templates_item_id, sequence, color_template, item_skip, item_total)
	VALUES (800001, 800001, 1, 0, '', '')");

for ($id = 900001; $id <= 900004; $id++) {
	sql_save(['id' => $id, 'description' => 'Fixture device ' . $id, 'hostname' => '127.0.0.1', 'disabled' => 'on', 'snmp_version' => 0], 'host');
}

foreach ([10001 => [900001, 'QoS Gold'], 10002 => [900001, 'QoS Silver'], 10003 => [900001, 'Other'],
	10004          => [900002, 'QoS Gold'], 10005 => [900004, 'QoS Gold'], 10006 => [900001, 'QoS Gold']] as $id => [$host, $name]) {
	sql_save(['id' => $id, 'host_id' => $host], 'data_local');
	sql_save(['local_data_id' => $id, 'name' => $name, 'name_cache' => $name, 'active' => 'on'], 'data_template_data');
	sql_save(['id' => $id, 'local_data_id' => $id, 'data_source_name' => 'value', 'data_source_type_id' => 1], 'data_template_rrd');
	sql_save(['id' => $id, 'host_id' => $host, 'graph_template_id' => $id === 10006 ? 1 : 800001], 'graph_local');
	sql_save(['local_graph_id' => $id, 'graph_template_id' => 800001, 'title' => $name, 'title_cache' => $name], 'graph_templates_graph');
	sql_save(['local_graph_id' => $id, 'graph_template_id' => 800001, 'task_item_id' => $id,
		'graph_type_id'           => 4, 'color_id' => 1, 'sequence' => 1], 'graph_templates_item');
}
// Two graph items bound to the same source must not duplicate aggregate members.
db_execute("INSERT INTO graph_templates_graph (local_graph_id, graph_template_id, title, title_cache) VALUES (0, 800001, 'QoS template', 'QoS template')");
db_execute("INSERT INTO graph_templates_item (id, local_graph_id, graph_template_id, task_item_id, graph_type_id, sequence, text_format) VALUES (800001, 0, 800001, 10004, 4, 1, 'QoS value')");
db_execute('INSERT INTO graph_templates_item (local_graph_id, task_item_id, graph_type_id, sequence) VALUES (10001, 10001, 9, 2)');

foreach (['graph_templates_graph', 'graph_templates_item'] as $table) {
	foreach (db_fetch_assoc('SHOW COLUMNS FROM ' . $table) as $column) {
		if ($column['Null'] === 'YES') {
			$value = str_contains($column['Type'], 'int') || str_contains($column['Type'], 'decimal') ? 0 : '';
			db_execute_prepared('UPDATE ' . $table . ' SET `' . $column['Field'] . '` = ? WHERE `' . $column['Field'] . '` IS NULL', [$value]);
		}
	}
}

$start = hrtime(true);
aggregate_fixture_check(automation_execute_device_aggregates(900001)[800001] === 'created', 'Creation failed.');
$first = (int) db_fetch_cell('SELECT local_graph_id FROM automation_aggregate_graphs WHERE host_id = 900001');
aggregate_fixture_check($first > 0 && aggregate_fixture_members(900001) === [10001, 10002], 'Regex/template filtering or deduplication failed.');
aggregate_fixture_check((int) db_fetch_cell_prepared('SELECT user_id FROM aggregate_graphs WHERE local_graph_id = ?', [$first]) === 1, 'CLI aggregate did not retain the template owner.');
aggregate_fixture_check((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_templates_item WHERE local_graph_id = ?', [$first]) > 0, 'Aggregate contains no rendered graph items.');
aggregate_fixture_check(automation_execute_device_aggregates(900001)[800001] === 'unchanged', 'Repeat run rebuilt an unchanged aggregate.');
aggregate_fixture_check(automation_execute_device_aggregates(900002)[800001] === 'created', 'Second device was not isolated.');
aggregate_fixture_check(aggregate_fixture_members(900002) === [10004], 'Members crossed device boundaries.');
aggregate_fixture_check(automation_execute_device_aggregates(900003)[800001] === 'empty', 'Empty device created an aggregate.');

db_execute('UPDATE aggregate_graph_templates SET automation_host_template_id = 1 WHERE id = 800001');
aggregate_fixture_check(automation_execute_device_aggregates(900001, true) === [], 'Device-template restriction was ignored.');
db_execute('UPDATE aggregate_graph_templates SET automation_host_template_id = 0 WHERE id = 800001');
automation_execute_device_aggregates(900001, true);

// More than one candidate batch must be scanned even when early graphs do not match.
for ($id = 11000; $id < 11205; $id++) {
	db_execute_prepared('INSERT INTO graph_local (id, host_id, graph_template_id) VALUES (?, 900002, 800001)', [$id]);
	db_execute_prepared('INSERT INTO graph_templates_item (local_graph_id, task_item_id, graph_type_id, sequence) VALUES (?, 10004, 4, 1)', [$id]);
}
$rule = automation_aggregate_rows('SELECT id, graph_template_id, automation_pattern, automation_max_members FROM aggregate_graph_templates WHERE id = 800001')[0];
aggregate_fixture_check(count(automation_aggregate_members($rule, 900002)) === 206, 'Candidate pagination missed matches.');
db_execute('DELETE FROM graph_templates_item WHERE local_graph_id >= 11000 AND local_graph_id < 11205');
db_execute('DELETE FROM graph_local WHERE id >= 11000 AND id < 11205');

db_execute('INSERT INTO graph_local (id, host_id, graph_template_id) VALUES (12000, 900003, 800001)');
db_execute('INSERT INTO graph_templates_item (local_graph_id, task_item_id, graph_type_id, sequence) VALUES (12000, 10001, 4, 1)');
aggregate_fixture_check(automation_execute_device_aggregates(900003)[800001] === 'empty', 'A source belonging to another device matched.');

db_execute("UPDATE data_template_data SET name_cache = 'Other' WHERE local_data_id = 10002");
db_execute("UPDATE data_template_data SET name_cache = 'QoS Silver' WHERE local_data_id = 10003");
aggregate_fixture_check(automation_execute_device_aggregates(900001)[800001] === 'updated', 'Membership change did not reconcile.');
aggregate_fixture_check(aggregate_fixture_members(900001) === [10001, 10003], 'Old member rows survived reconciliation.');
aggregate_fixture_check((int) db_fetch_cell('SELECT local_graph_id FROM automation_aggregate_graphs WHERE host_id = 900001') === $first, 'Update created a duplicate graph.');

db_execute('UPDATE aggregate_graph_templates SET automation_max_members = 1 WHERE id = 800001');
aggregate_fixture_check(automation_execute_device_aggregates(900001, true)[800001] === 'failed', 'Member limit did not stop the update.');
aggregate_fixture_check(aggregate_fixture_members(900001) === [10001, 10003], 'Member limit changed the aggregate.');
db_execute('UPDATE aggregate_graph_templates SET automation_max_members = 1000 WHERE id = 800001');

// An error during rebuild must roll back both graph items and ownership.
db_execute("UPDATE data_template_data SET name_cache = 'QoS Silver changed' WHERE local_data_id = 10003");
db_execute("UPDATE aggregate_graph_templates SET automation_title = 'Changed |host_description|' WHERE id = 800001");
db_execute("CREATE TRIGGER aggregate_fixture_fail BEFORE INSERT ON graph_templates_item FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture rebuild failure'");
aggregate_fixture_check(automation_execute_device_aggregates(900001, true)[800001] === 'failed', 'Injected persistence failure was accepted.');
db_execute('DROP TRIGGER aggregate_fixture_fail');
aggregate_fixture_check(aggregate_fixture_members(900001) === [10001, 10003], 'Rollback lost aggregate members.');
aggregate_fixture_check((int) db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_templates_item WHERE local_graph_id = ?', [$first]) > 0, 'Rollback lost rendered items.');
aggregate_fixture_check(automation_execute_device_aggregates(900001, true)[800001] === 'updated', 'Failed rebuild did not retry.');

// A failed metadata read must not be cached as a successful empty rule set.
db_execute('RENAME TABLE aggregate_graph_templates_item TO aggregate_graph_templates_item_fixture');

try {
	automation_execute_device_aggregates(900001, true);

	throw new RuntimeException('Metadata failure was ignored.');
} catch (RuntimeException $error) {
	aggregate_fixture_check($error->getMessage() === 'Unable to read aggregate automation metadata.', 'Unexpected metadata failure.');
} finally {
	db_execute('RENAME TABLE aggregate_graph_templates_item_fixture TO aggregate_graph_templates_item');
}
aggregate_fixture_check(automation_execute_device_aggregates(900001)[800001] === 'unchanged', 'Metadata failure poisoned the process cache.');

api_aggregate_remove_multi([$first]);
aggregate_fixture_check(automation_execute_device_aggregates(900001)[800001] === 'created', 'Deleted managed graph was not recreated.');
aggregate_fixture_check((int) db_fetch_cell('SELECT COUNT(*) FROM automation_aggregate_graphs WHERE host_id = 900001') === 1, 'Stale ownership row created duplicates.');

// Independent CLI processes exercise the real connection-scoped advisory lock.
$workers = [];

for ($worker = 0; $worker < 2; $worker++) {
	$workers[] = proc_open([PHP_BINARY, __FILE__, 'worker'], [1 => STDOUT, 2 => STDERR], $pipes);
}

foreach ($workers as $process) {
	aggregate_fixture_check(is_resource($process) && proc_close($process) === 0, 'Concurrent worker crashed.');
}
aggregate_fixture_check((int) db_fetch_cell('SELECT COUNT(*) FROM automation_aggregate_graphs WHERE host_id = 900004') === 1, 'Concurrent execution duplicated the ownership mapping.');
aggregate_fixture_check((int) db_fetch_cell("SELECT COUNT(*) FROM aggregate_graphs WHERE title_format = 'Changed Fixture device 900004'") === 1, 'Concurrent execution duplicated the graph.');

db_execute("UPDATE aggregate_graph_templates SET automation_enabled = '' WHERE id = 800001");
aggregate_fixture_check(automation_execute_device_aggregates(900001, true) === [], 'Disabled rule still ran.');
$before = $GLOBALS['database_total_queries'];
automation_execute_device_aggregates(900002);
aggregate_fixture_check($GLOBALS['database_total_queries'] === $before, 'Empty rule cache queried again.');

print json_encode(['checks' => $checks, 'elapsed_seconds' => (hrtime(true) - $start) / 1e9,
	'php'                      => PHP_VERSION, 'database' => db_fetch_cell('SELECT VERSION()'), 'diagnostic_only' => true], JSON_PRETTY_PRINT) . "\n";

if (getenv('CACTI_AGGREGATE_TEST_SCALE') === '1000') {
	db_execute("UPDATE aggregate_graph_templates SET automation_enabled = 'on' WHERE id = 800001");
	$source = automation_aggregate_rows('SELECT * FROM data_template_data WHERE local_data_id = 10004')[0];
	$rrd    = automation_aggregate_rows('SELECT * FROM data_template_rrd WHERE id = 10004')[0];
	$graph  = automation_aggregate_rows('SELECT * FROM graph_templates_graph WHERE local_graph_id = 10004')[0];
	$item   = automation_aggregate_rows('SELECT * FROM graph_templates_item WHERE local_graph_id = 10004')[0];
	unset($source['id'], $graph['id'], $item['id']);

	for ($index = 0; $index < 1000; $index++) {
		$host_id = 901000 + $index;
		$id      = 13000 + $index;
		sql_save(['id' => $host_id, 'description' => 'Scale fixture ' . $index, 'hostname' => '127.0.0.1', 'disabled' => 'on', 'snmp_version' => 0], 'host');
		sql_save(['id' => $id, 'host_id' => $host_id], 'data_local');
		sql_save(array_replace($source, ['local_data_id' => $id]), 'data_template_data');
		sql_save(array_replace($rrd, ['id' => $id, 'local_data_id' => $id]), 'data_template_rrd');
		sql_save(['id' => $id, 'host_id' => $host_id, 'graph_template_id' => 800001], 'graph_local');
		sql_save(array_replace($graph, ['local_graph_id' => $id]), 'graph_templates_graph');
		sql_save(array_replace($item, ['local_graph_id' => $id, 'task_item_id' => $id]), 'graph_templates_item');
	}
	$measurements = [];

	foreach (['create', 'unchanged'] as $pass) {
		$before = $GLOBALS['database_total_queries'];
		$start  = hrtime(true);

		for ($index = 0; $index < 1000; $index++) {
			$result = automation_execute_device_aggregates(901000 + $index, $pass === 'create' && $index === 0);
			aggregate_fixture_check(($result[800001] ?? '') === ($pass === 'create' ? 'created' : 'unchanged'), 'Scale pass failed.');
		}
		$measurements[] = ['pass' => $pass, 'devices' => 1000, 'members_per_device' => 1,
			'elapsed_seconds'        => (hrtime(true) - $start) / 1e9,
			'queries'                => $GLOBALS['database_total_queries'] - $before, 'peak_memory_bytes' => memory_get_peak_usage(true)];
	}
	print json_encode(['diagnostic_only' => true, 'kind' => 'real SQL and graph metadata; no SNMP or RRD I/O',
		'php'                               => PHP_VERSION, 'database' => db_fetch_cell('SELECT VERSION()'), 'profile' => $measurements], JSON_PRETTY_PRINT) . "\n";
}
