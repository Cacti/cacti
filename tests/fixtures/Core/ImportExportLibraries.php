<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * Runs the template import and export library functions (lib/import.php,
 * lib/export.php, lib/package.php and lib/api_automation.php) against an
 * in-memory database, as the unit tests do, so a native coverage probe records
 * them.  The Graph Template orphan removal is loaded unchanged into this
 * namespace and runs against a recorded fake database instead.
 */

namespace Cacti\Test\ImportExportLibraries;

use FakeMySQLPDO;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root = dirname(__DIR__, 3);

require $root . '/tests/bootstrap-unit.php';
require_once $root . '/tests/Helpers/FixtureCoverage.php';
require_once $root . '/tests/Helpers/SourceSlice.php';
require_once $root . '/tests/Helpers/FakeMySQLPDO.php';
require_once $root . '/lib/api_automation.php';
require_once $root . '/lib/export.php';
require_once $root . '/lib/import.php';
require_once $root . '/lib/package.php';
require_once $root . '/lib/vdef.php';
require_once $root . '/lib/xml.php';

// The bootstrap loads the hash code tables inside a function scope; import_xml_data() reads them as globals.
if (empty($GLOBALS['hash_type_codes'])) {
	[$GLOBALS['hash_type_codes'], $GLOBALS['cacti_version_codes']] = (static function () : array {
		require CACTI_PATH_INCLUDE . '/global_arrays.php';

		return [$hash_type_codes, $cacti_version_codes];
	})();
}

$GLOBALS['passed'] = 0;

function check(bool $condition, string $label) : void {
	if (!$condition) {
		throw new RuntimeException('Failed: ' . $label);
	}

	$GLOBALS['passed']++;
}

// Give the library functions a fresh in-memory database with the tables named.
function database(array $tables) : FakeMySQLPDO {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$conn = new FakeMySQLPDO();

	// cacti_log() reads its log level from the settings
	foreach ($tables + ['settings' => 'name TEXT PRIMARY KEY, value TEXT'] as $table => $columns) {
		$conn->exec("CREATE TABLE $table ($columns)");
	}

	$database_sessions["$database_hostname:$database_port:$database_default"] = $conn;

	return $conn;
}

function template_document(string $type, string $body) : string {
	$hash = 'hash_' . \get_hash_version($type) . str_repeat('a', 32);

	return "<cacti><$hash>$body</$hash></cacti>";
}

// Device rule export: tree rules and threshold data sources are named by hash, not by id.
$conn = database([
	'automation_templates'        => 'id INTEGER PRIMARY KEY, hash TEXT, host_template INTEGER',
	'host_template'               => 'id INTEGER PRIMARY KEY, hash TEXT, name TEXT',
	'automation_templates_rules'  => 'id INTEGER PRIMARY KEY, template_id INTEGER, rule_type INTEGER, rule_id INTEGER',
	'automation_graph_rules'      => 'id INTEGER PRIMARY KEY, hash TEXT',
	'automation_tree_rules'       => 'id INTEGER PRIMARY KEY, hash TEXT, tree_id INTEGER, tree_item_id INTEGER',
	'automation_tree_rule_items'  => 'id INTEGER PRIMARY KEY, rule_id INTEGER, field TEXT',
	'automation_match_rule_items' => 'id INTEGER PRIMARY KEY, rule_id INTEGER, rule_type INTEGER',
	'graph_tree'                  => 'id INTEGER PRIMARY KEY, name TEXT, sort_type INTEGER',
	'plugin_thold_host_template'  => 'host_template_id INTEGER, thold_template_id INTEGER',
	'thold_template'              => 'id INTEGER PRIMARY KEY, hash TEXT, data_template_id INTEGER, data_source_id INTEGER',
	'data_template_rrd'           => 'id INTEGER PRIMARY KEY, hash TEXT',
]);
$conn->exec("INSERT INTO automation_templates VALUES (1, 'template', 4)");
$conn->exec("INSERT INTO host_template VALUES (4, 'device_template', 'Device')");
$conn->exec('INSERT INTO automation_templates_rules VALUES (1, 1, 2, 3)');
$conn->exec("INSERT INTO automation_tree_rules VALUES (3, 'tree_rule', 1, 0)");
$conn->exec("INSERT INTO automation_tree_rule_items VALUES (9, 3, 'hostname')");
$conn->exec("INSERT INTO graph_tree VALUES (1, 'Default', 1)");
$conn->exec('INSERT INTO plugin_thold_host_template VALUES (4, 6)');
$conn->exec("INSERT INTO thold_template VALUES (6, 'thold_template', 2, 8)");
$conn->exec("INSERT INTO data_template_rrd VALUES (8, 'data_source')");

$device = \automation_device_rule_export([1])['device'][0];
check($device['tree_rules'][0]['tree_rule_items'][0]['rule_id'] === 'tree_rule', 'tree rule item names its rule by hash');
check($device['thold_rules'][0]['thold_template']['data_source_hash'] === 'data_source', 'threshold rule names its data source by hash');
check(!array_key_exists('data_source_id', $device['thold_rules'][0]['thold_template']), 'threshold rule drops the local data source id');

// Device and graph rule imports report unknown hashes as text.
database(array_fill_keys(['snmp_query', 'snmp_query_graph', 'host_template', 'automation_networks'], 'id INTEGER PRIMARY KEY AUTOINCREMENT, hash TEXT, name TEXT'));

$errors = \automation_graph_rule_import(['graph_rules' => [['hash' => 'rule', 'snmp_query_id' => '<i>query</i>', 'graph_type_id' => '"graph"&']]])['errors'];
check(cacti_sizeof($errors) === 2 && str_contains($errors[0], '&lt;i&gt;query&lt;/i&gt;') && str_contains($errors[1], '&quot;graph&quot;&amp;'), 'graph rule import encodes unknown hashes');

$errors = \automation_template_import(['device' => [[
	'hash'          => 'device',
	'host_template' => '<b>template</b>',
	'graph_rules'   => [['hash' => 'rule', 'snmp_query_id' => '<i>query</i>', 'graph_type_id' => '<u>graph</u>']],
]]])['errors'];
$text = implode("\n", $errors);
check(cacti_sizeof($errors) === 3 && str_contains($text, '&lt;b&gt;template') && str_contains($text, '&lt;i&gt;query') && str_contains($text, '&lt;u&gt;graph') && !str_contains($text, '<'), 'device rule import encodes unknown hashes');

$data  = ['name' => 'network', '<s>column</s>' => 1];
$debug = [];
check(\automation_validate_import_columns('automation_networks', $data, $debug) === false && str_contains($debug['errors'][0], '&lt;s&gt;column&lt;/s&gt;'), 'import column check encodes the rejected column');

// Data query export encodes the data source field name of a graph mapping.
$conn = database([
	'snmp_query'               => "id INTEGER PRIMARY KEY, hash TEXT, name TEXT, xml_path TEXT DEFAULT ''",
	'snmp_query_graph'         => 'id INTEGER PRIMARY KEY, snmp_query_id INTEGER, hash TEXT, name TEXT, graph_template_id INTEGER',
	'snmp_query_graph_rrd'     => 'snmp_query_graph_id INTEGER, data_template_id INTEGER, data_template_rrd_id INTEGER, snmp_field_name TEXT',
	'snmp_query_graph_rrd_sv'  => 'id INTEGER PRIMARY KEY, snmp_query_graph_id INTEGER, sequence INTEGER',
	'snmp_query_graph_sv'      => 'id INTEGER PRIMARY KEY, snmp_query_graph_id INTEGER, sequence INTEGER',
	'host_template_snmp_query' => 'host_template_id INTEGER, snmp_query_id INTEGER',
	'data_template'            => 'id INTEGER PRIMARY KEY, hash TEXT',
	'data_template_rrd'        => 'id INTEGER PRIMARY KEY, hash TEXT',
]);
$conn->exec("INSERT INTO snmp_query (id, hash, name) VALUES (1, '" . str_repeat('a', 32) . "', 'Interface Statistics'), (2, '" . str_repeat('b', 32) . "', 'Disk Partitions')");
$conn->exec("INSERT INTO snmp_query_graph VALUES (2, 1, '" . str_repeat('c', 32) . "', 'In/Out Bits', 7), (6, 2, '" . str_repeat('d', 32) . "', 'Disk Space', 8)");
$conn->exec("INSERT INTO snmp_query_graph_rrd VALUES (2, 3, 4, 'ifIn</snmp_field_name><extra>1</extra>')");
$conn->exec("INSERT INTO data_template VALUES (3, '" . str_repeat('e', 32) . "')");
$conn->exec("INSERT INTO data_template_rrd VALUES (4, '" . str_repeat('f', 32) . "')");
// Device Template 1 uses only the second Data Query.
$conn->exec('INSERT INTO host_template_snmp_query VALUES (1, 2)');

$GLOBALS['fields_data_query_edit']      = ['name' => ['method' => 'textbox']];
$GLOBALS['fields_data_query_item_edit'] = ['name' => ['method' => 'textbox']];

$xml = \data_query_to_xml(1);
check(str_contains($xml, '<snmp_field_name>ifIn&lt;/snmp_field_name&gt;&lt;extra&gt;1&lt;/extra&gt;') && !str_contains($xml, '<extra>'), 'data query export encodes the field name');

// A data query package lists that data query's graph templates, not those of a device template with the same id.
$output = \get_package_contents('data_query', 1, false);
check(str_contains($output, 'Data Query Graph Templates') && str_contains($output, 'In/Out Bits') && !str_contains($output, 'Disk Space'), 'data query package lists its own graph templates');

// A dependency that fails to import fails the whole template import; one that imports is reported.
$tables = [];

foreach (['graph_templates', 'graph_templates_item', 'graph_template_input', 'data_template', 'data_template_rrd', 'host_template',
	'data_input', 'data_input_fields', 'snmp_query', 'snmp_query_graph', 'snmp_query_graph_sv', 'snmp_query_graph_rrd_sv',
	'graph_templates_gprint', 'cdef', 'cdef_items', 'vdef', 'vdef_items', 'data_source_profiles'] as $table) {
	$tables[$table] = "id INTEGER PRIMARY KEY AUTOINCREMENT, hash TEXT DEFAULT '', name TEXT DEFAULT '', input_string TEXT DEFAULT ''";
}
$tables['data_source_profiles_rra'] = 'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT';

$GLOBALS['fields_host_template_edit']      = ['name' => []];
$GLOBALS['fields_data_query_edit']         = ['name' => []];
$GLOBALS['fields_data_query_item_edit']    = ['name' => []];
$GLOBALS['fields_data_input_edit']         = ['name' => [], 'input_string' => []];
$GLOBALS['fields_data_input_field_edit']   = [];
$GLOBALS['fields_data_input_field_edit_1'] = [];

foreach ([
	'host_template'     => ['<name>Device</name><graph_templates>damaged</graph_templates>', 'Device', '', false],
	'data_input_method' => ['<name>Method</name><input_string>a;b</input_string>', 'Method', '<input_string>abc</input_string><fields></fields>', true],
	'data_query'        => ['<name>Query</name><graphs><damaged><name>Graph</name></damaged></graphs>', 'Query', '<graphs></graphs>', true],
	'vdef'              => ['<name>Vdef</name><items><damaged><value>1</value></damaged></items>', 'Vdef', '<items></items>', true],
] as $type => [$rejected, $name, $fields, $preview]) {
	database($tables);
	$GLOBALS['preview_only']    = $preview;
	$GLOBALS['import_messages'] = [];

	$document = template_document($type, $rejected);
	check(\import_xml_data($document, false, 1) === false, $type . ' rejected by its importer fails the import');

	// the accepted object is only previewed, so nothing outside these tables is written
	$GLOBALS['preview_only'] = true;
	$document                = template_document($type, "<name>$name</name>$fields");
	$result                  = \import_xml_data($document, false, 1);
	check(is_array($result) && ($result[$type][0]['title'] ?? '') === $name, $type . ' accepted by its importer is reported');
}

// Orphan removal deletes the template item and the graph items made from it, not every item with its hash.
$conn   = database(['graph_templates_item' => 'id INTEGER PRIMARY KEY, hash TEXT, graph_template_id INTEGER, local_graph_id INTEGER, local_graph_template_item_id INTEGER']);
$insert = $conn->prepare('INSERT INTO graph_templates_item VALUES (?, ?, ?, ?, ?)');

foreach ([[1, '', 1, 0, 0], [2, 'kept', 1, 0, 0], [10, '', 1, 5, 1], [11, '', 1, 5, 2], [20, '', 2, 0, 0], [21, '', 2, 6, 20]] as $row) {
	$insert->execute($row);
}

\import_remove_orphan_graph_item(1, ['id' => 1, 'hash' => '']);
check(array_map('intval', $conn->query('SELECT id FROM graph_templates_item ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN)) === [2, 11, 20, 21], 'orphan removal limited to the item and its graph items');

/*
 * The Graph Template importer itself, with the database recorded: an imported
 * Template that no longer carries an item removes it through the scoped helper.
 */
function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return str_contains($sql, 'FROM graph_templates') ? 7 : false;
}

function db_fetch_row_prepared(string $sql, array $params = []) : array {
	return [];
}

function db_fetch_assoc_prepared(string $sql, array $params = []) : array {
	return [['id' => 31, 'hash' => 'gone', 'graph_type_id' => 4, 'text_format' => 'Old', 'value' => '']];
}

function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['statements'][] = [preg_replace('/\s+/', ' ', $sql), $params];

	return true;
}

function sql_save(array $save, string $table) : int {
	return 7;
}

function compare_data(array $save, array $previous, string $table) : int {
	return 0;
}

function retemplate_graphs(int $graph_template_id) : void {
	$GLOBALS['retemplated'][] = $graph_template_id;
}

\cacti_fixture_source_function($root . '/lib/import.php', 'xml_to_graph_template', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/import.php', 'import_remove_orphan_graph_item', __NAMESPACE__);

$GLOBALS['preview_only']     = false;
$GLOBALS['struct_graph']     = [];
$GLOBALS['graph_item_types'] = [4 => 'LINE1'];
$GLOBALS['statements']       = [];
$GLOBALS['retemplated']      = [];
$xml_array                   = ['name' => 'Template', 'items' => [], 'inputs' => []];
$hash_cache                  = [];

check(xml_to_graph_template(str_repeat('a', 32), $xml_array, $hash_cache, '0101', true) !== false, 'graph template imported');
check($GLOBALS['statements'] === [
	['DELETE FROM graph_templates_item WHERE id = ? AND graph_template_id = ? AND local_graph_id = 0', [31, 7]],
	['DELETE FROM graph_templates_item WHERE local_graph_template_item_id = ?', [31]],
], 'orphaned item removed by id within its template');
check($GLOBALS['retemplated'] === [7], 'graphs of the template retemplated after the removal');
check($GLOBALS['import_debug_info']['orphans'] === ['Removed Orphaned Graph Items, Type: LINE1, Text Format: Old, Value: '], 'removed orphan reported');

print 'PASS: import-export-libraries (' . $GLOBALS['passed'] . " checks)\n";
