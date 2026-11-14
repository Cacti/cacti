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

// The shared template exchange fixture loads the test-mode Cacti and the
// sqlite connection the template importer and exporter tests use.
require_once dirname(__DIR__, 2) . '/Unit/Core/Templates/TemplateExchangeFixture.php';

foreach (['import', 'xml', 'utility', 'template', 'graph_template_input'] as $library) {
	require_once CACTI_PATH_LIBRARY . '/' . $library . '.php';
}

/**
 * The template exchange connection, plus the two MySQL behaviors the
 * importer relies on that sqlite does not share.
 */
class ImportCoveragePDO extends TemplateExchangePDO {
	/**
	 * Rewrites NOW() for sqlite and lets sql_save() of a new row (id 0) take
	 * the next id, as MySQL does, then defers to the template exchange rewrites.
	 *
	 * @param string              $query   The SQL statement
	 * @param array<string,mixed> $options Driver options
	 *
	 * @return PDOStatement|false The prepared statement
	 */
	public function prepare(string $query, array $options = []) : PDOStatement|false {
		$query = str_replace('NOW()', "datetime('now')", $query);
		$query = preg_replace("/^(INSERT INTO \\w+ \\(`id`,.*?\\) VALUES \\()(?:'0'|0),/s", '$1NULL,', $query);

		return parent::prepare($query, $options);
	}
}

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
function import_expect(mixed $actual, mixed $expected, string $message) : void {
	global $count;
	$count++;

	if ($actual !== $expected) {
		throw new RuntimeException($message . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
	}
}

/**
 * Counts one assertion and throws when $haystack does not contain $needle.
 *
 * @param string $haystack The observed text
 * @param string $needle   The required fragment
 * @param string $message  What the assertion checks
 *
 * @return void
 */
function import_expect_contains(string $haystack, string $needle, string $message) : void {
	import_expect(str_contains($haystack, $needle), true, $message . ' (' . $needle . ' in ' . substr($haystack, 0, 2000) . ')');
}

/**
 * Builds the hash element name import_xml_data() expects for $type.
 *
 * @param string $type      The hash type name
 * @param string $hash      The 32 character hash
 * @param bool   $versioned False for the pre-0.8.7 form without a version
 *
 * @return string The hash element name
 */
function import_hash(string $type, string $hash, bool $versioned = true) : string {
	global $hash_type_codes;

	return 'hash_' . ($versioned ? get_hash_version($type) : $hash_type_codes[$type]) . $hash;
}

/**
 * Returns one integer from a query on the fixture connection.
 *
 * @param string $sql The query
 *
 * @return int The first column of the first row
 */
function import_count(string $sql) : int {
	global $connection;

	return (int) $connection->query($sql)->fetchColumn();
}

/**
 * Creates every table the importer reads and writes, with one column per
 * field the importer can save.
 *
 * @param PDO $conn The fixture connection
 *
 * @return void
 */
function import_schema(PDO $conn) : void {
	global $struct_graph, $struct_graph_item, $struct_data_source, $struct_data_source_item;
	global $fields_graph_template_input_edit, $fields_cdef_edit, $fields_data_query_edit, $fields_data_query_item_edit;
	global $fields_grprint_presets_edit, $fields_host_template_edit, $fields_profile_edit, $fields_profile_rra_edit;

	$templated = static function (array $form) : array {
		$fields = [];

		foreach ($form as $name => $field) {
			$fields[$name]        = $field;
			$fields['t_' . $name] = $field;
		}

		return $fields;
	};

	template_exchange_table($conn, 'graph_templates', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'name TEXT', 'class TEXT', 'version TEXT', 'multiple TEXT', 'test_source TEXT']);
	template_exchange_table($conn, 'graph_templates_graph', ['id INTEGER PRIMARY KEY', 'graph_template_id INTEGER', 'local_graph_id INTEGER DEFAULT 0', 'local_graph_template_graph_id INTEGER DEFAULT 0'], $templated($struct_graph));
	template_exchange_table($conn, 'graph_templates_item', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'name TEXT', 'graph_template_id INTEGER', 'local_graph_id INTEGER DEFAULT 0', 'local_graph_template_item_id INTEGER DEFAULT 0'], $struct_graph_item);
	template_exchange_table($conn, 'graph_template_input', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'graph_template_id INTEGER'], $fields_graph_template_input_edit);
	$conn->exec('CREATE TABLE graph_template_input_defs (graph_template_input_id INTEGER, graph_template_item_id INTEGER, PRIMARY KEY (graph_template_input_id, graph_template_item_id))');
	$conn->exec('CREATE TABLE colors (id INTEGER PRIMARY KEY, hex TEXT)');
	$conn->exec('CREATE TABLE graph_local (id INTEGER PRIMARY KEY, graph_template_id INTEGER)');

	template_exchange_table($conn, 'data_template', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'name TEXT']);
	template_exchange_table($conn, 'data_template_data', ['id INTEGER PRIMARY KEY', 'data_template_id INTEGER', 'local_data_id INTEGER DEFAULT 0', 'local_data_template_data_id INTEGER DEFAULT 0', 'data_source_profile_id INTEGER', 'rrd_step TEXT'], $templated($struct_data_source));
	template_exchange_table($conn, 'data_template_rrd', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'name TEXT', 'data_template_id INTEGER', 'local_data_id INTEGER DEFAULT 0', 'local_data_template_rrd_id INTEGER DEFAULT 0', 'rrd_heartbeat TEXT'], $templated($struct_data_source_item));
	$conn->exec('CREATE TABLE data_input_data (data_input_field_id INTEGER, data_template_data_id INTEGER, data_template_id INTEGER, local_data_id INTEGER, host_id INTEGER, t_value TEXT, value TEXT, PRIMARY KEY (data_input_field_id, data_template_data_id))');
	$conn->exec('CREATE TABLE data_input (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE data_input_fields (id INTEGER PRIMARY KEY, hash TEXT, name TEXT, data_input_id INTEGER, data_name TEXT, type_code TEXT, input_output TEXT)');
	$conn->exec('CREATE TABLE poller_data_template_field_mappings (data_template_id INTEGER, data_name TEXT, data_source_names TEXT, last_updated TEXT, PRIMARY KEY (data_template_id, data_name))');

	template_exchange_table($conn, 'cdef', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_cdef_edit);
	$conn->exec('CREATE TABLE cdef_items (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE vdef (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE vdef_items (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	template_exchange_table($conn, 'graph_templates_gprint', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_grprint_presets_edit);

	template_exchange_table($conn, 'snmp_query', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_data_query_edit);
	template_exchange_table($conn, 'snmp_query_graph', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'snmp_query_id INTEGER'], $fields_data_query_item_edit);
	$conn->exec('CREATE TABLE snmp_query_graph_rrd (snmp_query_graph_id INTEGER, data_template_id INTEGER, data_template_rrd_id INTEGER, snmp_field_name TEXT, PRIMARY KEY (snmp_query_graph_id, data_template_id, data_template_rrd_id))');
	$conn->exec('CREATE TABLE snmp_query_graph_sv (id INTEGER PRIMARY KEY, hash TEXT, snmp_query_graph_id INTEGER, sequence INTEGER, field_name TEXT, text TEXT)');
	$conn->exec('CREATE TABLE snmp_query_graph_rrd_sv (id INTEGER PRIMARY KEY, hash TEXT, snmp_query_graph_id INTEGER, data_template_id INTEGER, sequence INTEGER, field_name TEXT, text TEXT)');

	template_exchange_table($conn, 'host_template', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_host_template_edit);
	template_exchange_table($conn, 'data_source_profiles', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_profile_edit);
	template_exchange_table($conn, 'data_source_profiles_rra', ['id INTEGER PRIMARY KEY', 'data_source_profile_id INTEGER', 'name TEXT'], $fields_profile_rra_edit);
	$conn->exec('CREATE TABLE package_public_keys (id INTEGER PRIMARY KEY AUTOINCREMENT, public_key TEXT)');
}

/**
 * Writes a gzip Package the way lib/package.php does, signed with $private_key,
 * carrying one file named $file_name.
 *
 * @param string $private_key The PEM private key that signs the Package
 * @param string $public_key  The PEM public key the Package carries
 * @param string $file_name   The Package file path
 *
 * @return string The .xml.gz path
 */
function import_signed_package(string $private_key, string $public_key, string $file_name) : string {
	$payload  = "<interface><name>Fixture</name></interface>\n";
	$file_sig = '';
	$base_sig = '';

	openssl_sign($payload, $file_sig, $private_key, OPENSSL_ALGO_SHA256);

	$xml = "<xml>\n"
		. "   <info>\n"
		. "     <name>Fixture Package</name>\n"
		. "     <author>Fixture</author>\n"
		. "     <homepage>http://example.test</homepage>\n"
		. "     <email>fixture@example.test</email>\n"
		. "   </info>\n"
		. "   <files>\n"
		. "       <file>\n"
		. '           <name>' . $file_name . "</name>\n"
		. '           <data>' . base64_encode($payload) . "</data>\n"
		. '           <filesignature>' . base64_encode($file_sig) . "</filesignature>\n"
		. "       </file>\n"
		. "   </files>\n"
		. "   <publickeyname>Fixture</publickeyname>\n"
		. '   <publickey>' . base64_encode($public_key) . "</publickey>\n";

	openssl_sign($xml . "   <signature></signature>\n</xml>", $base_sig, $private_key, OPENSSL_ALGO_SHA256);

	$xml .= '   <signature>' . base64_encode($base_sig) . "</signature>\n</xml>";

	$path = sys_get_temp_dir() . '/cacti-import-coverage-' . bin2hex(random_bytes(4)) . '.xml.gz';
	$file = fopen('compress.zlib://' . $path, 'wb');
	fwrite($file, $xml);
	fclose($file);

	return $path;
}

$connection = new ImportCoveragePDO();
$prior      = template_exchange_wire($connection, ['preview_only', 'import_messages', 'import_debug_info', 'ignorable_hashes']);
import_schema($connection);

$GLOBALS['preview_only']      = false;
$GLOBALS['import_messages']   = [];
$GLOBALS['import_debug_info'] = [];

// Hashes with an unknown type code are rejected in both hash formats.
foreach (['hash_ff0104aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'hash_ffaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'] as $bad_hash) {
	$parsed = true;
	$log    = template_exchange_capture_log(function () use ($bad_hash, &$parsed) : void {
		$parsed = parse_xml_hash($bad_hash);
	});
	import_expect($parsed, false, 'An unknown hash type is rejected');
	import_expect_contains($log, 'parse_xml_hash ERROR type or version not found for hash: ' . $bad_hash, 'The rejected hash is logged');
}
import_expect(in_array(7, $GLOBALS['import_messages'], true), true, 'A rejected hash queues the XML parse message');

// An unresolved dependency resolves to zero and is reported as unmet.
$unmet_hash = import_hash('cdef', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
$cache      = ['cdef' => ['cccccccccccccccccccccccccccccccc' => 12]];
$resolved   = null;
$log        = template_exchange_capture_log(function () use ($unmet_hash, &$cache, &$resolved) : void {
	$resolved = resolve_hash_to_id($unmet_hash, $cache, 'graph_templates_item');
});
import_expect($resolved, 0, 'An unresolved dependency resolves to zero');
import_expect($GLOBALS['import_debug_info']['dep'][$unmet_hash], 'unmet', 'An unresolved dependency is unmet');
import_expect_contains($log, 'Import found an invalid dependency hash of :bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb for Table graph_templates_item.', 'The unmet dependency is logged');

// The result renderer names the mode and the package files.
foreach ([
	[true, ['scripts/ss_example.php' => 'found'], 'Cacti would make the following changes if the Package was imported:'],
	[false, ['scripts/ss_example.php' => 'found'], 'Cacti has imported the following items for the Package:'],
	[true, [], 'Cacti would make the following changes if the Template was imported:'],
	[false, [], 'Cacti has imported the following items for the Template:'],
] as [$preview, $files, $heading]) {
	$info = ['cdef' => [['title' => 'Total <Bits>', 'result' => $preview ? 'preview' : 'success', 'type' => 'new']]];
	ob_start();
	import_display_results($info, $files, false, $preview);
	$output = (string) ob_get_clean();
	import_expect_contains($output, $heading, 'The results heading names the mode');
	import_expect_contains($output, 'Total &lt;Bits&gt; [new]', 'The results escape the title');

	if (cacti_sizeof($files)) {
		import_expect_contains($output, ($preview ? '[preview] ' : '') . 'scripts/ss_example.php [found]', 'The results list the package files');
	}
}

// Met and unmet dependencies are listed with their friendly names after an import.
$connection->exec("INSERT INTO cdef (id, hash, name) VALUES (3, 'dddddddddddddddddddddddddddddddd', 'Turn Bytes into Bits')");
$met_hash = import_hash('cdef', 'dddddddddddddddddddddddddddddddd');
$info     = ['graph_template' => [['title' => 'Traffic', 'result' => 'fail', 'type' => 'updated', 'dep' => [$met_hash => 'met', import_hash('cdef', 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee') => 'unmet']]]];
ob_start();
import_display_results($info, [], false, false);
$output = (string) ob_get_clean();
import_expect_contains($output, "+ Found Dependency:\n(CDEF) Turn Bytes into Bits", 'A met dependency is named');
import_expect_contains($output, "+ Unmet Dependency:\n(CDEF)\n", 'An unmet dependency is listed');

// An unversioned gprint preset previews through the legacy hash lookup.
$GLOBALS['preview_only'] = true;
$hash                    = import_hash('gprint_preset', 'ffffffffffffffffffffffffffffffff', false);
$xml                     = "<cacti><$hash><name>Exact Numbers</name><gprint_text>%8.0lf</gprint_text></$hash></cacti>";
$info                    = import_xml_data($xml, false, 1);
import_expect($info['gprint_preset'][0]['type'], 'new', 'A legacy gprint preset previews as new');
import_expect($info['gprint_preset'][0]['result'], 'preview', 'A legacy gprint preset is only previewed');
import_expect(import_count('SELECT COUNT(*) FROM graph_templates_gprint'), 0, 'A preview writes no gprint preset');

// A graph template input that names an item outside the template is refused before any write.
$GLOBALS['preview_only'] = false;
$gt_hash                 = '11111111111111111111111111111111';
$gt                      = import_hash('graph_template', $gt_hash);
$gt_item                 = import_hash('graph_template_item', '22222222222222222222222222222222');
$gt_input                = import_hash('graph_template_input', '33333333333333333333333333333333');
$foreign                 = import_hash('graph_template_item', '44444444444444444444444444444444');
$xml                     = "<cacti><$gt><name>Traffic</name><items><$gt_item><sequence>1</sequence></$gt_item></items>"
	. "<inputs><$gt_input><name>Color</name><column_name>color_id</column_name><items>$foreign</items></$gt_input></inputs></$gt></cacti>";
$imported = null;
$log      = template_exchange_capture_log(function () use ($xml, &$imported) : void {
	$imported = import_xml_data($xml, false, 1);
});
import_expect($imported, false, 'A foreign graph item input is refused');
import_expect_contains($log, 'SECURITY ERROR: Graph template import refused invalid input relationships before the write pass', 'The refusal is logged');
import_expect(import_count('SELECT COUNT(*) FROM graph_templates'), 0, 'A refused graph template writes nothing');

// An existing graph template with an orphaned item: preview reports the
// difference, the new item and the orphan; the import removes the orphan.
$orphan_hash = '55555555555555555555555555555555';
$connection->exec("INSERT INTO graph_templates (id, hash, name) VALUES (1, '$gt_hash', 'Old Traffic')");
$connection->exec("INSERT INTO graph_templates_graph (id, graph_template_id, local_graph_id, title) VALUES (1, 1, 0, 'Old Title')");
$connection->exec("INSERT INTO graph_templates_item (id, hash, graph_template_id, local_graph_id, graph_type_id, text_format, value) VALUES (1, '$orphan_hash', 1, 0, 4, 'Gone', '7')");
$xml = "<cacti><$gt><name>Traffic</name><graph><title>|host_description| - Traffic</title></graph><items><$gt_item>"
	. '<graph_type_id>4</graph_type_id><color_id>00FF00</color_id><color2_id>FF0000</color2_id><text_format>Inbound</text_format><value>1</value><sequence>1</sequence>'
	. "</$gt_item></items><inputs><$gt_input><name>Color</name><column_name>color_id</column_name><items>$gt_item</items></$gt_input></inputs></$gt></cacti>";

$GLOBALS['preview_only'] = true;
$info                    = import_xml_data($xml, false, 1);
$debug                   = $info['graph_template'][0];
import_expect($debug['type'], 'updated', 'A renamed graph template previews as updated');
import_expect($debug['result'], 'preview', 'A graph template preview is only previewed');
import_expect(in_array('Table: graph_templates, Column: name, New Value: Traffic, Old Value: Old Traffic', $debug['differences'], true), true, 'The preview lists the name difference');
import_expect($debug['new_items'], ['New Graph Items, Type: LINE1, Text Format: Inbound, Value: 1'], 'The preview lists the new item');
import_expect($debug['orphans'], ['Found Orphaned Graph Items, Type: LINE1, Text Format: Gone, Value: 7'], 'The preview lists the orphan');
import_expect(import_count('SELECT COUNT(*) FROM colors'), 0, 'A preview adds no colors');

$GLOBALS['preview_only'] = false;
$info                    = import_xml_data($xml, false, 1, true);
$debug                   = $info['graph_template'][0];
import_expect($debug['result'], 'success', 'The graph template imports');
import_expect($debug['orphans'], ['Removed Orphaned Graph Items, Type: LINE1, Text Format: Gone, Value: 7'], 'The import removes the orphan');
import_expect(import_count("SELECT COUNT(*) FROM graph_templates_item WHERE hash = '$orphan_hash'"), 0, 'The orphan row is deleted');
import_expect(import_count("SELECT COUNT(*) FROM colors WHERE hex IN ('00FF00', 'FF0000')"), 2, 'Both item colors are added');
import_expect(import_count('SELECT COUNT(*) FROM graph_template_input_defs'), 1, 'The input links its item');
import_expect($connection->query('SELECT name FROM graph_templates WHERE id = 1')->fetchColumn(), 'Traffic', 'The template is renamed');

// A Data Template imports its items and input data; an item with a value
// that would reach the RRDtool command line is rejected.
$connection->exec("INSERT INTO data_source_profiles (id, hash, name, step, heartbeat, \"default\") VALUES (1, 'd0d0d0d0d0d0d0d0d0d0d0d0d0d0d0d0', 'System Default', '300', '600', 'on')");
$connection->exec("INSERT INTO data_input_fields (id, hash, name, data_input_id, data_name, type_code, input_output) VALUES (5, 'f5f5f5f5f5f5f5f5f5f5f5f5f5f5f5f5', 'OID', 1, 'oid', 'output_type', 'in')");
$dt_hash      = '66666666666666666666666666666666';
$dt_item_hash = '77777777777777777777777777777777';
$dt           = import_hash('data_template', $dt_hash);
$dt_item      = import_hash('data_template_item', $dt_item_hash);
$dif          = import_hash('data_input_field', 'f5f5f5f5f5f5f5f5f5f5f5f5f5f5f5f5');
$dt_xml       = static fn (string $maximum) : string => "<cacti><$dt><name>Traffic Data</name><ds><name>|host_description| - Traffic</name></ds><items><$dt_item>"
	. "<data_source_name>traffic_in</data_source_name><rrd_minimum>0</rrd_minimum><rrd_maximum>$maximum</rrd_maximum><data_source_type_id>2</data_source_type_id>"
	. "</$dt_item></items><data><item_000><data_input_field_id>$dif</data_input_field_id><t_value></t_value><value>.1.3.6</value></item_000></data></$dt></cacti>";

$xml  = $dt_xml('U');
$info = import_xml_data($xml, false, 1);
import_expect($info['data_template'][0]['result'], 'success', 'The data template imports');
import_expect($connection->query("SELECT rrd_minimum || '/' || rrd_maximum || '/' || rrd_heartbeat FROM data_template_rrd")->fetchColumn(), '0/U/600', 'The item keeps its limits and takes the profile heartbeat');
import_expect($connection->query('SELECT t_value || value FROM data_input_data')->fetchColumn(), 'on.1.3.6', 'The output type input is forced templated');

$GLOBALS['import_messages'] = [];
$xml                        = $dt_xml('1;rm');
$imported                   = null;
$log                        = template_exchange_capture_log(function () use ($xml, &$imported) : void {
	$imported = import_xml_data($xml, false, 1);
});
import_expect($imported, false, 'An invalid RRD maximum rejects the data template');
import_expect_contains($log, "FATAL: Data Template 'Traffic Data' rejected, the Data Source Item field 'rrd_maximum' holds the invalid value '1;rm'", 'The rejection is logged');
import_expect($GLOBALS['import_messages'], [45], 'The rejection queues its message');

// A data query saves its graph data source map and suggested values, and
// skips a suggested value whose data template is missing.
$connection->exec("INSERT INTO data_input (id, hash, name) VALUES (2, '88888888888888888888888888888888', 'Get SNMP Data (Indexed)')");
$dq     = import_hash('data_query', '99999999999999999999999999999999');
$graph  = import_hash('data_query_graph', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa1');
$sv     = import_hash('data_query_sv_graph', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa2');
$rrd_sv = import_hash('data_query_sv_data_source', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa3');
$rrd_ok = import_hash('data_query_sv_data_source', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa5');
$input  = import_hash('data_input_method', '88888888888888888888888888888888');
$absent = import_hash('data_template', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa4');
$xml    = "<cacti><$dq><name>Interfaces</name><description>Interface statistics</description><xml_path>&lt;path_cacti&gt;/resource/snmp_queries/interface.xml</xml_path>"
	. "<data_input_id>$input</data_input_id><graphs><$graph><name>In/Out Bits</name>"
	. "<rrd><item_000><snmp_field_name>ifInOctets</snmp_field_name><data_template_id>$dt</data_template_id><data_template_rrd_id>$dt_item</data_template_rrd_id></item_000>"
	. "<item_001><snmp_field_name>ifOutOctets</snmp_field_name><data_template_id>$absent</data_template_id><data_template_rrd_id>$dt_item</data_template_rrd_id></item_001></rrd>"
	. "<sv_graph><$sv><field_name>title</field_name><sequence>1</sequence><text>|host_description| - Traffic</text></$sv></sv_graph>"
	. "<sv_data_source><$rrd_sv><field_name>name</field_name><data_template_id>$absent</data_template_id><sequence>1</sequence><text>|query_ifName|</text></$rrd_sv>"
	. "<$rrd_ok><field_name>name</field_name><data_template_id>$dt</data_template_id><sequence>2</sequence><text>|query_ifAlias|</text></$rrd_ok></sv_data_source>"
	. "</$graph></graphs></$dq></cacti>";
$info = null;
$log  = template_exchange_capture_log(function () use ($xml, &$info) : void {
	$info = import_xml_data($xml, false, 1);
}, POLLER_VERBOSITY_HIGH);
import_expect($info['data_query'][0]['result'], 'success', 'The data query imports');
import_expect($info['files'], [CACTI_PATH_BASE . '/resource/snmp_queries/interface.xml' => 'found'], 'The data query resource file is found');
import_expect((int) $connection->query('SELECT data_input_id FROM snmp_query')->fetchColumn(), 2, 'The data input method resolves');
import_expect($connection->query('SELECT snmp_field_name FROM snmp_query_graph_rrd')->fetchColumn(), 'ifInOctets', 'The resolved graph data source map is saved');
import_expect(import_count('SELECT COUNT(*) FROM snmp_query_graph_rrd'), 1, 'The unresolved map is skipped');
import_expect($connection->query('SELECT text FROM snmp_query_graph_rrd_sv')->fetchColumn(), '|query_ifAlias|', 'The resolved suggested value is saved');
import_expect_contains($log, 'Import Error: inserting into snmp_query_graph_rrd', 'The unresolved map is logged');
import_expect_contains($log, 'Import Error: Error Importing into snmp_query_graph_rrd_sv table', 'The unresolved suggested value is logged');
import_expect_contains($log, "\$dep_hash_cache[\$type][\$i]['hash']: 99999999999999999999999999999999", 'The second pass logs each hash at high verbosity');

// Device template and data source profile previews.
$GLOBALS['preview_only'] = true;
$ht                      = import_hash('host_template', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb1');
$new_dq                  = import_hash('data_query', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb2');
$xml                     = "<cacti><$new_dq><name>SNMP - Interface &amp; Statistics</name></$new_dq>"
	. "<$ht><name>Net-SNMP Device</name><graph_templates></graph_templates><data_queries>$new_dq</data_queries></$ht></cacti>";
$info = import_xml_data($xml, false, 1, false, false, ['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb1']);
import_expect(array_keys($info), ['host_template'], 'Only the selected device template is previewed');
import_expect($info['host_template'][0]['differences'], ['New Data Query: SNMP - Interface &amp; Statistics'], 'The preview lists the new data query');

$dsp  = import_hash('data_source_profile', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb3');
$xml  = "<cacti><$dsp><name>High Collection Rate</name><step>30</step><heartbeat>120</heartbeat><cf_items>1|3</cf_items><items></items></$dsp></cacti>";
$info = import_xml_data($xml, true, 1);
import_expect($info['data_source_profile'][0]['title'], 'High Collection Rate (imported)', 'A profile previews as an imported copy');
import_expect(import_count('SELECT COUNT(*) FROM data_source_profiles'), 1, 'A profile preview writes nothing');

// A self-signed Package may be previewed but not imported until its key is
// accepted; a file whose directory does not exist is reported, not written.
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($key, $private_key);
$public_key = openssl_pkey_get_details($key)['key'];
$file_name  = 'resource/snmp_queries/cacti-import-coverage-' . bin2hex(random_bytes(4)) . '/fixture.xml';
$package    = import_signed_package($private_key, $public_key, $file_name);
$target     = CACTI_PATH_BASE . '/' . $file_name;

try {
	$imported = null;
	$printed  = '';
	$log      = template_exchange_capture_log(function () use ($package, &$imported, &$printed) : void {
		// The refusal is printed for the CLI operator as well as logged.
		ob_start();
		$imported = import_package($package, limitex: false);
		$printed  = (string) ob_get_clean();
	});
	import_expect($imported, false, 'An untrusted Package is not imported');
	import_expect_contains($printed, 'FATAL: Package Public Key is not a Trusted Public Key', 'The refusal is printed');
	import_expect_contains($log, 'FATAL: Package Public Key is not a Trusted Public Key for Package compress.zlib://' . $package, 'The untrusted key is logged');

	$preview = import_package($package, preview: true, limitex: false);
	import_expect($preview[1], [$target => 'not writable, new'], 'A preview reports the new file');

	$insert = $connection->prepare('INSERT INTO package_public_keys (public_key) VALUES (?)');
	$insert->execute([$public_key]);

	$imported = null;
	$log      = template_exchange_capture_log(function () use ($package, &$imported) : void {
		$imported = import_package($package, limitex: false);
	}, POLLER_VERBOSITY_MEDIUM);
	import_expect($imported, [[], [$target => 'not writable']], 'An accepted Package reports the unwritable file');
	import_expect_contains($log, 'Write Status file: ' . $target . ', with Status not writable', 'The write status is logged');
	import_expect(file_exists($target), false, 'No file is written outside an existing directory');
} finally {
	unlink($package);
}

template_exchange_restore($prior);

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
