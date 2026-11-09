<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Whole-file evidence for every included vendor class does not fit the
// runner's memory limit, and only library lines are gated.
if (function_exists('xdebug_set_filter')) {
	xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [dirname(__DIR__, 3) . '/lib/']);
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

// The shared exchange fixture boots a test-mode Cacti and the sqlite connection.
require_once dirname(__DIR__, 2) . '/Unit/Core/Templates/TemplateExchangeFixture.php';
require_once CACTI_PATH_LIBRARY . '/export.php';
require_once CACTI_PATH_LIBRARY . '/vdef.php';

$count = 0;

/**
 * Counts one assertion and throws when the values are not identical.
 *
 * @param mixed  $actual   The observed value
 * @param mixed  $expected The required value
 * @param string $message  What the assertion checks
 *
 * @return void
 */
function export_expect(mixed $actual, mixed $expected, string $message) : void {
	global $count;
	$count++;

	if ($actual !== $expected) {
		throw new RuntimeException($message . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
	}
}

/**
 * Counts one assertion and throws when $haystack lacks $needle.
 *
 * @param string $haystack The exported XML
 * @param string $needle   The fragment that must appear
 * @param string $message  What the assertion checks
 *
 * @return void
 */
function export_contains(string $haystack, string $needle, string $message) : void {
	export_expect(str_contains($haystack, $needle), true, $message . ' (' . $needle . ')');
}

/**
 * Creates $table with the fixed columns plus one empty-default TEXT column per
 * form field, optionally with the matching t_ override column.
 *
 * @param PDO                 $conn   The sqlite connection
 * @param string              $table  The table name
 * @param array<int,string>   $fixed  Column definitions that are always present
 * @param array<string,mixed> $form   A Cacti form array whose keys become columns
 * @param bool                $with_t Whether to add a t_ column per field
 *
 * @return void
 */
function export_table(PDO $conn, string $table, array $fixed, array $form, bool $with_t = false) : void {
	$names = array_map(static fn (string $column) : string => strtok($column, ' '), $fixed);

	foreach (array_keys($form) as $field) {
		foreach ($with_t ? [$field, 't_' . $field] : [$field] as $column) {
			if (!in_array($column, $names, true)) {
				$fixed[] = '"' . $column . "\" TEXT DEFAULT ''";
				$names[] = $column;
			}
		}
	}

	$conn->exec("CREATE TABLE $table (" . implode(', ', $fixed) . ')');
}

/**
 * Inserts one row given as column => value.
 *
 * @param PDO                 $conn  The sqlite connection
 * @param string              $table The table name
 * @param array<string,mixed> $row   The row to insert
 *
 * @return void
 */
function export_insert(PDO $conn, string $table, array $row) : void {
	$columns = implode(', ', array_map(static fn (string $column) : string => '"' . $column . '"', array_keys($row)));
	$marks   = implode(', ', array_fill(0, count($row), '?'));
	$conn->prepare("INSERT INTO $table ($columns) VALUES ($marks)")->execute(array_values($row));
}

/**
 * Runs an exporter against a missing id and returns its XML and log.
 *
 * @param string $exporter The exporter function name
 *
 * @return array{0:string,1:string} The returned XML and the logged text
 */
function export_missing(string $exporter) : array {
	$xml = null;
	$log = template_exchange_capture_log(static function () use ($exporter, &$xml) : void {
		$xml = @$exporter(404);
	});

	return [$xml, $log];
}

$conn   = new TemplateExchangePDO();
$prior  = template_exchange_wire($conn, ['export_errors']);
$hashes = [];

$GLOBALS['export_errors'] = 0;

foreach (range(1, 30) as $index) {
	$hashes[$index] = str_pad(dechex($index), 32, 'a', STR_PAD_LEFT);
}

export_table($conn, 'graph_templates', ['id INTEGER PRIMARY KEY', 'hash TEXT'], array_flip(['name', 'class', 'version', 'multiple', 'test_source']));
export_table($conn, 'graph_templates_graph', ['id INTEGER PRIMARY KEY', 'graph_template_id INTEGER', 'local_graph_id INTEGER'], $struct_graph, true);
export_table($conn, 'graph_templates_item', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'graph_template_id INTEGER', 'local_graph_id INTEGER', 'sequence INTEGER'], $struct_graph_item);
export_table($conn, 'graph_template_input', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'graph_template_id INTEGER'], $fields_graph_template_input_edit);
export_table($conn, 'data_template', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'name TEXT'], []);
export_table($conn, 'data_template_data', ['id INTEGER PRIMARY KEY', 'data_template_id INTEGER', 'local_data_id INTEGER'], $struct_data_source, true);
export_table($conn, 'data_template_rrd', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'data_template_id INTEGER', 'local_data_id INTEGER'], $struct_data_source_item);
export_table($conn, 'data_input', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_data_input_edit);
export_table($conn, 'data_input_fields', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'name TEXT'], $fields_data_input_field_edit + $fields_data_input_field_edit_1);
export_table($conn, 'cdef', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_cdef_edit);
export_table($conn, 'vdef', ['id INTEGER PRIMARY KEY', 'hash TEXT'], preset_vdef_form_list());
export_table($conn, 'graph_templates_gprint', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_grprint_presets_edit);
export_table($conn, 'data_source_profiles', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_profile_edit);
export_table($conn, 'data_source_profiles_rra', ['id INTEGER PRIMARY KEY', 'data_source_profile_id INTEGER'], $fields_profile_rra_edit);
export_table($conn, 'host_template', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_host_template_edit);
export_table($conn, 'snmp_query', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_data_query_edit);
export_table($conn, 'snmp_query_graph', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'snmp_query_id INTEGER'], $fields_data_query_item_edit);
$conn->exec('CREATE TABLE graph_template_input_defs (graph_template_input_id INTEGER, graph_template_item_id INTEGER)');
$conn->exec('CREATE TABLE colors (id INTEGER PRIMARY KEY, hex TEXT)');
$conn->exec('CREATE TABLE data_input_data (data_input_field_id INTEGER, data_template_data_id INTEGER, t_value TEXT, value TEXT)');
$conn->exec('CREATE TABLE cdef_items (id INTEGER PRIMARY KEY, hash TEXT, cdef_id INTEGER, sequence INTEGER, type INTEGER, value TEXT)');
$conn->exec('CREATE TABLE vdef_items (id INTEGER PRIMARY KEY, hash TEXT, vdef_id INTEGER, sequence INTEGER, type INTEGER, value TEXT)');
$conn->exec('CREATE TABLE data_source_profiles_cf (data_source_profile_id INTEGER, consolidation_function_id INTEGER)');
$conn->exec('CREATE TABLE host_template_graph (host_template_id INTEGER, graph_template_id INTEGER)');
$conn->exec('CREATE TABLE host_template_snmp_query (host_template_id INTEGER, snmp_query_id INTEGER)');
$conn->exec('CREATE TABLE snmp_query_graph_rrd (snmp_query_graph_id INTEGER, data_template_id INTEGER, data_template_rrd_id INTEGER, snmp_field_name TEXT)');
$conn->exec('CREATE TABLE snmp_query_graph_sv (id INTEGER PRIMARY KEY, hash TEXT, snmp_query_graph_id INTEGER, sequence INTEGER, field_name TEXT, text TEXT)');
$conn->exec('CREATE TABLE snmp_query_graph_rrd_sv (id INTEGER PRIMARY KEY, hash TEXT, snmp_query_graph_id INTEGER, data_template_id INTEGER, sequence INTEGER, field_name TEXT, text TEXT)');

// Every exporter refuses a missing row, counts the failure and logs a repair hint.
$missing = [
	'graph_template_to_xml'      => 'ERROR: Invalid Graph Template found in Database for Template [] GTGid: .',
	'data_template_to_xml'       => 'ERROR: Invalid Data Template found in Database.  Please run database repair script',
	'data_input_method_to_xml'   => 'ERROR: Invalid Data Input Method found in Data Template.  Please run database repair script',
	'cdef_to_xml'                => 'ERROR: Invalid CDEF found in Graph Template.  Please run database repair script',
	'gprint_preset_to_xml'       => 'ERROR: Invalid GPRINT preset found in Graph Template.  Please run database repair script',
	'data_source_profile_to_xml' => 'ERROR: Invalid Data Source Profile found during Data Template export.  Please run database repair script',
	'host_template_to_xml'       => 'ERROR: Invalid Device Template found during Export.  Please run database repair script',
	'data_query_to_xml'          => 'ERROR: Invalid Data Query found during Export.  Please run database repair script',
];

foreach ($missing as $exporter => $message) {
	$before      = $GLOBALS['export_errors'];
	[$xml, $log] = export_missing($exporter);
	export_expect($xml, '', $exporter . ' returns no XML for a missing row');
	export_expect($GLOBALS['export_errors'], $before + 1, $exporter . ' counts the failure');
	export_contains($log, $message, $exporter . ' logs the repair hint');
}

// A graph template exports its graph, hashed item references and inputs.
export_insert($conn, 'graph_templates', ['id' => 7, 'hash' => $hashes[1], 'name' => 'Traffic & Errors', 'class' => 'net', 'version' => '1', 'multiple' => '', 'test_source' => '']);
export_insert($conn, 'graph_templates_graph', ['id' => 70, 'graph_template_id' => 7, 'local_graph_id' => 0, 'title' => '|host_description| <Traffic>', 't_title' => 'on', 'height' => '200']);
export_insert($conn, 'data_template', ['id' => 9, 'hash' => $hashes[2], 'name' => 'Interface <Traffic>']);
export_insert($conn, 'data_template_rrd', ['id' => 91, 'hash' => $hashes[3], 'data_template_id' => 9, 'local_data_id' => 0, 'data_source_name' => 'traffic_in', 'data_input_field_id' => 81]);
export_insert($conn, 'cdef', ['id' => 1, 'hash' => $hashes[4], 'name' => 'Bits']);
export_insert($conn, 'cdef', ['id' => 2, 'hash' => $hashes[5], 'name' => 'Total Bits']);
export_insert($conn, 'vdef', ['id' => 3, 'hash' => $hashes[6], 'name' => 'Max & Min']);
export_insert($conn, 'graph_templates_gprint', ['id' => 5, 'hash' => $hashes[7], 'name' => 'Normal', 'gprint_text' => '%8.2lf %s']);
export_insert($conn, 'colors', ['id' => 11, 'hex' => '00FF00']);
export_insert($conn, 'graph_templates_item', ['id' => 71, 'hash' => $hashes[8], 'graph_template_id' => 7, 'local_graph_id' => 0, 'sequence' => 1, 'graph_type_id' => '7', 'task_item_id' => '91', 'color_id' => '11', 'cdef_id' => '2', 'vdef_id' => '3', 'gprint_id' => '5', 'text_format' => 'In <bits>']);
export_insert($conn, 'graph_template_input', ['id' => 72, 'hash' => $hashes[9], 'graph_template_id' => 7, 'name' => 'Data Source [traffic_in]', 'column_name' => 'task_item_id']);
export_insert($conn, 'graph_template_input_defs', ['graph_template_input_id' => 72, 'graph_template_item_id' => 71]);

$xml = graph_template_to_xml(7);
export_expect(str_starts_with($xml, '<hash_' . get_hash_version('graph_template') . $hashes[1] . ">\n"), true, 'A graph template opens with its own hash');
export_contains($xml, "\t<name>Traffic &amp; Errors</name>", 'The template name is encoded');
export_contains($xml, "\t\t<t_title>on</t_title>\n\t\t<title>|host_description| &lt;Traffic&gt;</title>", 'A graph field carries its override flag');
export_contains($xml, '<hash_' . get_hash_version('graph_template_item') . $hashes[8] . '>', 'An item is keyed by its own hash');
export_contains($xml, '<task_item_id>hash_' . get_hash_version('data_template_item') . $hashes[3] . '</task_item_id>', 'An item names its data source by hash');
export_contains($xml, '<cdef_id>hash_' . get_hash_version('cdef') . $hashes[5] . '</cdef_id>', 'An item names its CDEF by hash');
export_contains($xml, '<vdef_id>hash_' . get_hash_version('vdef') . $hashes[6] . '</vdef_id>', 'An item names its VDEF by hash');
export_contains($xml, '<gprint_id>hash_' . get_hash_version('gprint_preset') . $hashes[7] . '</gprint_id>', 'An item names its GPRINT by hash');
export_contains($xml, '<color_id>00FF00</color_id>', 'An item color is exported as its hex value');
export_contains($xml, '<text_format>In &lt;bits&gt;</text_format>', 'Other item fields are encoded');
export_contains($xml, '<hash_' . get_hash_version('graph_template_input') . $hashes[9] . '>', 'An input is keyed by its own hash');
export_contains($xml, '<items>hash_' . get_hash_version('graph_template') . $hashes[8] . '</items>', 'An input lists its item hashes');
export_expect(simplexml_load_string($xml) !== false, true, 'The graph template XML parses');

// The graph exporter drops the remote caching column from the item structure.
export_expect(isset($struct_graph_item['data_template_id']), false, 'The item structure no longer lists data_template_id');

// A graph template without its template graph row names both ids.
export_insert($conn, 'graph_templates', ['id' => 8, 'hash' => $hashes[10], 'name' => 'Orphan']);
$log = template_exchange_capture_log(static function () use (&$xml) : void {
	$xml = @graph_template_to_xml(8);
});
export_expect($xml, '', 'A template without a graph row returns no XML');
export_contains($log, 'for Template Orphan[8] GTGid: .', 'The log names the template and the missing graph row');

// A data template exports hashed input, profile and field references and its data.
export_insert($conn, 'data_input', ['id' => 6, 'hash' => $hashes[11], 'name' => 'Get SNMP Data', 'type_id' => '2', 'input_string' => '<path_cacti>/x.php']);
export_insert($conn, 'data_input_fields', ['id' => 81, 'hash' => $hashes[12], 'data_input_id' => 6, 'name' => 'Index Value', 'data_name' => 'index_value', 'input_output' => 'in', 'sequence' => '1']);
export_insert($conn, 'data_input_fields', ['id' => 82, 'hash' => $hashes[13], 'data_input_id' => 6, 'name' => 'OID', 'data_name' => 'oid', 'input_output' => '', 'sequence' => '2']);
export_insert($conn, 'data_source_profiles', ['id' => 4, 'hash' => $hashes[14], 'name' => 'System Default', 'step' => '300', 'heartbeat' => '600']);
export_insert($conn, 'data_source_profiles_cf', ['data_source_profile_id' => 4, 'consolidation_function_id' => 1]);
export_insert($conn, 'data_source_profiles_cf', ['data_source_profile_id' => 4, 'consolidation_function_id' => 3]);
export_insert($conn, 'data_source_profiles_rra', ['id' => 41, 'data_source_profile_id' => 4, 'name' => 'Daily', 'steps' => '1', 'rows' => '600']);
export_insert($conn, 'data_template_data', ['id' => 90, 'data_template_id' => 9, 'local_data_id' => 0, 'name' => '|host_description| - Traffic', 't_name' => 'on', 'data_input_id' => '6', 'data_source_profile_id' => '4', 'rrd_step' => '300']);
export_insert($conn, 'data_input_data', ['data_input_field_id' => 82, 'data_template_data_id' => 90, 't_value' => 'on', 'value' => '.1.3 <x>']);

$xml = data_template_to_xml(9);
export_expect(str_starts_with($xml, '<hash_' . get_hash_version('data_template') . $hashes[2] . ">\n\t<name>Interface &lt;Traffic&gt;</name>\n\t<ds>\n"), true, 'A data template opens with its hash and name');
export_contains($xml, "\t\t<t_name>on</t_name>\n\t\t<name>|host_description| - Traffic</name>", 'A data source field carries its override flag');
export_contains($xml, '<data_input_id>hash_' . get_hash_version('data_input_method') . $hashes[11] . '</data_input_id>', 'The input method is named by hash');
export_contains($xml, '<data_source_profile_id>hash_' . get_hash_version('data_source_profile') . $hashes[14] . '</data_source_profile_id>', 'The profile is named by hash');
export_contains($xml, '<hash_' . get_hash_version('data_template_item') . $hashes[3] . '>', 'A data source item is keyed by its hash');
export_contains($xml, "\t\t\t<data_input_field_id>hash_" . get_hash_version('data_input_field') . $hashes[12] . '</data_input_field_id>', 'An item names its output field by hash');
export_contains($xml, "\t\t<item_000>\n\t\t\t<data_input_field_id>hash_" . get_hash_version('data_input_field') . $hashes[13] . '</data_input_field_id>', 'Input data names its field by hash');
export_contains($xml, '<value>.1.3 &lt;x&gt;</value>', 'Input data values are encoded');
export_expect(simplexml_load_string($xml) !== false, true, 'The data template XML parses');

// A data input method base64-encodes its input string and lists its fields.
$xml = data_input_method_to_xml(6);
export_contains($xml, "\t<input_string>" . base64_encode('<path_cacti>/x.php') . '</input_string>', 'The input string is base64 encoded');
export_contains($xml, '<hash_' . get_hash_version('data_input_field') . $hashes[12] . ">\n", 'A field is keyed by its hash');
export_contains($xml, "\t\t\t<name>Index Value</name>", 'The renamed fname field exports the name column');
export_contains($xml, "\t\t\t<input_output>in</input_output>", 'A set direction is exported');
export_contains($xml, "\t\t\t<data_name>oid</data_name>", 'The second field exports its data name');
export_expect(simplexml_load_string($xml) !== false, true, 'The data input method XML parses');

// An inherited CDEF item is exported as the hash of the CDEF it names.
export_insert($conn, 'cdef_items', ['id' => 21, 'hash' => $hashes[15], 'cdef_id' => 2, 'sequence' => 1, 'type' => 5, 'value' => '1']);
export_insert($conn, 'cdef_items', ['id' => 22, 'hash' => $hashes[16], 'cdef_id' => 2, 'sequence' => 2, 'type' => 2, 'value' => '3']);
$xml = cdef_to_xml(2);
export_contains($xml, '<value>hash_' . get_hash_version('cdef') . $hashes[4] . '</value>', 'An inherited CDEF is named by hash');
export_contains($xml, '<value>3</value>', 'An operator item keeps its value');
export_expect(str_contains($xml, '<value>1</value>'), false, 'The inherited CDEF id is not exported');

// A VDEF exports its visible form fields and its items.
export_insert($conn, 'vdef_items', ['id' => 31, 'hash' => $hashes[17], 'vdef_id' => 3, 'sequence' => 1, 'type' => 4, 'value' => 'MAXIMUM']);
export_expect(vdef_to_xml(404), 'Invalid VDEF.', 'A missing VDEF returns the inline error');
$xml = vdef_to_xml(3);
export_contains($xml, "\t<name>Max &amp; Min</name>", 'The VDEF name is encoded');
export_contains($xml, '<hash_' . get_hash_version('vdef_item') . $hashes[17] . '>', 'A VDEF item is keyed by its hash');
export_contains($xml, '<value>MAXIMUM</value>', 'A VDEF item exports its value');

// A GPRINT preset and a data source profile export their fields.
$xml = gprint_preset_to_xml(5);
export_expect($xml, '<hash_' . get_hash_version('gprint_preset') . $hashes[7] . ">\n\t<name>Normal</name>\n\t<gprint_text>%8.2lf %s</gprint_text>\n</hash_" . get_hash_version('gprint_preset') . $hashes[7] . '>', 'A GPRINT preset exports its name and format');
$xml = data_source_profile_to_xml(4);
export_contains($xml, "\t<cf_items>1|3</cf_items>\n", 'A profile lists its consolidation functions');
export_contains($xml, "\t\t<item_000>\n\t\t\t<name>Daily</name>\n\t\t\t<steps>1</steps>\n\t\t\t<rows>600</rows>\n", 'A profile RRA exports its visible fields');
export_expect(str_contains($xml, '<retention>'), false, 'A profile RRA omits the computed retention');

// A data query names its input method and its graph templates by hash.
export_insert($conn, 'snmp_query', ['id' => 4, 'hash' => $hashes[18], 'name' => 'Interfaces', 'xml_path' => '<path_cacti>/resource/snmp_queries/interface.xml', 'data_input_id' => '6']);
export_insert($conn, 'snmp_query_graph', ['id' => 41, 'hash' => $hashes[19], 'snmp_query_id' => 4, 'name' => 'In/Out Bits', 'graph_template_id' => '7']);
export_insert($conn, 'snmp_query_graph_rrd', ['snmp_query_graph_id' => 41, 'data_template_id' => 9, 'data_template_rrd_id' => 91, 'snmp_field_name' => 'ifInOctets']);
export_insert($conn, 'snmp_query_graph_sv', ['id' => 51, 'hash' => $hashes[20], 'snmp_query_graph_id' => 41, 'sequence' => 1, 'field_name' => 'title', 'text' => '|host_description| - <Traffic>']);
export_insert($conn, 'snmp_query_graph_rrd_sv', ['id' => 61, 'hash' => $hashes[21], 'snmp_query_graph_id' => 41, 'data_template_id' => 9, 'sequence' => 1, 'field_name' => 'name', 'text' => '|query_ifName|']);

$xml = data_query_to_xml(4);
export_contains($xml, "\t<data_input_id>hash_" . get_hash_version('data_input_method') . $hashes[11] . '</data_input_id>', 'The query input method is named by hash');
export_contains($xml, '<hash_' . get_hash_version('data_query_graph') . $hashes[19] . '>', 'A query graph is keyed by its hash');
export_contains($xml, "\t\t\t<graph_template_id>hash_" . get_hash_version('graph_template') . $hashes[1] . '</graph_template_id>', 'A query graph names its template by hash');
export_contains($xml, '<data_template_rrd_id>hash_' . get_hash_version('data_template_item') . $hashes[3] . '</data_template_rrd_id>', 'A query RRD names its item by hash');
export_contains($xml, '<hash_' . get_hash_version('data_query_sv_graph') . $hashes[20] . '>', 'A graph suggested value is keyed by its hash');
export_contains($xml, '<text>|host_description| - &lt;Traffic&gt;</text>', 'A suggested value text is encoded');
export_contains($xml, '<hash_' . get_hash_version('data_query_sv_data_source') . $hashes[21] . '>', 'A data source suggested value is keyed by its hash');
export_expect(substr_count($xml, '<data_template_id>hash_' . get_hash_version('data_template') . $hashes[2] . '</data_template_id>'), 2, 'Both RRD and suggested value name the data template');
export_expect(simplexml_load_string($xml) !== false, true, 'The data query XML parses');

template_exchange_restore($prior);

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
