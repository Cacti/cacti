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
 * Behavior of the template importer in lib/import.php against a sqlite copy
 * of the tables it reads and writes.
 *
 * The importer has to reject hashes it cannot place, report dependencies it
 * cannot resolve, accept the unversioned hashes that pre-0.8.7 templates use,
 * and refuse a graph template whose inputs point outside the template before
 * anything is written.  The result renderer is what an operator reads after
 * an import, so its wording for each mode is pinned too.
 */

require_once __DIR__ . '/TemplateExchangeFixture.php';
require_once dirname(__DIR__, 4) . '/lib/import.php';
require_once dirname(__DIR__, 4) . '/lib/xml.php';

// a data query import bumps the replication CRC defined here
require_once dirname(__DIR__, 4) . '/lib/utility.php';

function template_import_seed() : TemplateExchangePDO {
	$conn = new TemplateExchangePDO();

	// every table that import_xml_data() unions into its hash cache
	foreach (['graph_templates', 'graph_templates_item', 'graph_template_input', 'data_template',
		'data_template_rrd', 'data_input', 'data_input_fields', 'snmp_query_graph_sv',
		'cdef_items', 'vdef', 'vdef_items'] as $table) {
		$conn->exec("CREATE TABLE $table (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)");
	}

	$conn->exec('CREATE TABLE data_source_profiles_rra (id INTEGER PRIMARY KEY, data_source_profile_id INTEGER, name TEXT, steps INTEGER)');
	$conn->exec('CREATE TABLE snmp_query_graph_rrd (snmp_query_graph_id INTEGER, data_template_id INTEGER, data_template_rrd_id INTEGER, snmp_field_name TEXT)');
	$conn->exec('CREATE TABLE snmp_query_graph_rrd_sv (id INTEGER PRIMARY KEY, hash TEXT, snmp_query_graph_id INTEGER, data_template_id INTEGER, sequence INTEGER, field_name TEXT, text TEXT)');
	$conn->exec('DROP TABLE snmp_query_graph_sv');
	$conn->exec('CREATE TABLE snmp_query_graph_sv (id INTEGER PRIMARY KEY, hash TEXT, snmp_query_graph_id INTEGER, sequence INTEGER, field_name TEXT, text TEXT)');

	return $conn;
}

function template_import_form_tables(PDO $conn) : void {
	global $fields_cdef_edit, $fields_data_query_edit, $fields_data_query_item_edit;
	global $fields_grprint_presets_edit, $fields_host_template_edit, $fields_profile_edit;

	template_exchange_table($conn, 'cdef', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_cdef_edit);
	template_exchange_table($conn, 'snmp_query', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_data_query_edit);
	template_exchange_table($conn, 'snmp_query_graph', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'snmp_query_id INTEGER'], $fields_data_query_item_edit);
	template_exchange_table($conn, 'graph_templates_gprint', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_grprint_presets_edit);
	template_exchange_table($conn, 'host_template', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_host_template_edit);
	template_exchange_table($conn, 'data_source_profiles', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_profile_edit);
}

/**
 * @return string The hash element name import_xml_data() expects for $type
 */
function template_import_hash(string $type, string $hash, bool $versioned = true) : string {
	global $hash_type_codes;

	return 'hash_' . ($versioned ? get_hash_version($type) : $hash_type_codes[$type]) . $hash;
}

beforeEach(function () {
	$this->conn           = template_import_seed();
	$this->import_globals = template_exchange_wire($this->conn, ['preview_only', 'import_messages', 'import_debug_info', 'ignorable_hashes']);

	$GLOBALS['preview_only']      = false;
	$GLOBALS['import_messages']   = [];
	$GLOBALS['import_debug_info'] = [];

	template_import_form_tables($this->conn);
});

afterEach(function () {
	template_exchange_restore($this->import_globals);
});

test('a hash with an unknown type code is rejected in both hash formats', function (string $hash) {
	$parsed = true;

	$log = template_exchange_capture_log(function () use ($hash, &$parsed) {
		$parsed = parse_xml_hash($hash);
	});

	expect($parsed)->toBeFalse()
		->and($GLOBALS['import_messages'])->toContain(7)
		->and($log)->toContain('parse_xml_hash ERROR type or version not found for hash: ' . $hash);
})->with([
	'versioned'   => ['hash_ff0104aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
	'unversioned' => ['hash_ffaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
]);

test('an unversioned hash parses as a release of the first hash version', function () {
	global $cacti_version_codes;

	$parsed = parse_xml_hash('hash_05aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

	expect($parsed['type'])->toBe('cdef')
		->and($parsed['hash'])->toBe('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
		->and($cacti_version_codes[$parsed['version']])->toBe('0101');
});

test('an unresolved dependency resolves to zero and is reported as unmet', function () {
	global $import_debug_info;

	$hash  = template_import_hash('cdef', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
	$cache = ['cdef' => ['cccccccccccccccccccccccccccccccc' => 12]];
	$id    = null;

	$log = template_exchange_capture_log(function () use ($hash, &$cache, &$id) {
		$id = resolve_hash_to_id($hash, $cache, 'graph_templates_item');
	});

	expect($id)->toBe(0)
		->and($import_debug_info['dep'][$hash])->toBe('unmet')
		->and($log)->toContain('Import Error: Import found an invalid dependency hash of :bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb for Table graph_templates_item.');

	$known = template_import_hash('cdef', 'cccccccccccccccccccccccccccccccc');

	expect(resolve_hash_to_id($known, $cache, 'graph_templates_item'))->toBe(12)
		->and($import_debug_info['dep'][$known])->toBe('met');
});

test('the import results name the mode and the package files', function (bool $preview, array $files, string $heading) {
	$info = ['cdef' => [['title' => 'Total <Bits>', 'result' => $preview ? 'preview' : 'success', 'type' => 'new']]];

	ob_start();
	import_display_results($info, $files, false, $preview);
	$output = (string) ob_get_clean();

	expect($output)->toContain($heading)
		->and($output)->toContain('Total &lt;Bits&gt; [new]')
		->and($output)->not->toContain('<span');

	if (cacti_sizeof($files)) {
		expect($output)->toContain(($preview ? '[preview] ' : '') . 'scripts/ss_example.php [found]');
	}
})->with([
	'package preview'  => [true, ['scripts/ss_example.php' => 'found'], 'Cacti would make the following changes if the Package was imported:'],
	'package import'   => [false, ['scripts/ss_example.php' => 'found'], 'Cacti has imported the following items for the Package:'],
	'template preview' => [true, [], 'Cacti would make the following changes if the Template was imported:'],
	'template import'  => [false, [], 'Cacti has imported the following items for the Template:'],
]);

test('an unmet dependency is listed with its friendly name after an import', function () {
	$this->conn->exec("INSERT INTO cdef (id, hash, name) VALUES (3, 'dddddddddddddddddddddddddddddddd', 'Turn Bytes into Bits')");

	$met   = template_import_hash('cdef', 'dddddddddddddddddddddddddddddddd');
	$unmet = template_import_hash('cdef', 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee');
	$info  = ['graph_template' => [['title' => 'Traffic', 'result' => 'fail', 'type' => 'updated', 'dep' => [$met => 'met', $unmet => 'unmet']]]];

	ob_start();
	import_display_results($info, [], false, false);
	$output = (string) ob_get_clean();

	expect($output)->toContain('[fail] Traffic [updated]')
		->and($output)->toContain("+ Found Dependency:\n(CDEF) Turn Bytes into Bits")
		->and($output)->toContain("+ Unmet Dependency:\n(CDEF)\n");
});

test('an unversioned gprint preset imports through the legacy hash lookup', function () {
	global $preview_only;

	$preview_only = true;
	$hash         = template_import_hash('gprint_preset', 'ffffffffffffffffffffffffffffffff', false);
	$xml          = "<cacti><$hash><name>Exact Numbers</name><gprint_text>%8.0lf</gprint_text></$hash></cacti>";

	$info = import_xml_data($xml, false, 1);

	expect($info['gprint_preset'][0])->toMatchArray([
		'type'   => 'new',
		'hash'   => 'ffffffffffffffffffffffffffffffff',
		'title'  => 'Exact Numbers',
		'result' => 'preview',
	])
		->and((int) $this->conn->query('SELECT COUNT(*) FROM graph_templates_gprint')->fetchColumn())->toBe(0);
});

test('a graph template input that names an item outside the template is refused before any write', function () {
	$template = template_import_hash('graph_template', '11111111111111111111111111111111');
	$item     = template_import_hash('graph_template_item', '22222222222222222222222222222222');
	$input    = template_import_hash('graph_template_input', '33333333333333333333333333333333');
	$foreign  = template_import_hash('graph_template_item', '44444444444444444444444444444444');
	$xml      = "<cacti><$template><name>Traffic</name><items><$item><sequence>1</sequence></$item></items>"
		. "<inputs><$input><name>Color</name><column_name>color_id</column_name><items>$foreign</items></$input></inputs></$template></cacti>";
	$imported = null;

	$log = template_exchange_capture_log(function () use ($xml, &$imported) {
		$imported = import_xml_data($xml, false, 1);
	});

	expect($imported)->toBeFalse()
		->and($log)->toContain('SECURITY ERROR: Graph template import refused invalid input relationships before the write pass')
		->and((int) $this->conn->query('SELECT COUNT(*) FROM graph_templates')->fetchColumn())->toBe(0);
});

test('a data source profile preview is reported as a new imported copy', function () {
	global $preview_only;

	$preview_only = true;
	$hash         = template_import_hash('data_source_profile', '55555555555555555555555555555555');
	$xml          = "<cacti><$hash><name>High Collection Rate</name><step>30</step><heartbeat>120</heartbeat><cf_items>1|3</cf_items><items></items></$hash></cacti>";

	$info = import_xml_data($xml, true, 1);

	expect($info['data_source_profile'][0]['type'])->toBe('new')
		->and($info['data_source_profile'][0]['title'])->toBe('High Collection Rate (imported)')
		->and($info['data_source_profile'][0]['result'])->toBe('preview')
		->and((int) $this->conn->query('SELECT COUNT(*) FROM data_source_profiles')->fetchColumn())->toBe(0);
});

test('a device template preview lists the data queries the package would add', function () {
	global $preview_only;

	$preview_only = true;
	$template     = template_import_hash('host_template', '66666666666666666666666666666666');
	$query        = template_import_hash('data_query', '77777777777777777777777777777777');
	$xml          = "<cacti><$query><name>SNMP - Interface &amp; Statistics</name></$query>"
		. "<$template><name>Net-SNMP Device</name><graph_templates></graph_templates><data_queries>$query</data_queries></$template></cacti>";

	$info = import_xml_data($xml, false, 1, false, false, ['66666666666666666666666666666666']);

	expect(array_keys($info))->toBe(['host_template'])
		->and($info['host_template'][0]['title'])->toBe('Net-SNMP Device')
		->and($info['host_template'][0]['type'])->toBe('new')
		->and($info['host_template'][0]['differences'])->toBe(['New Data Query: SNMP - Interface &amp; Statistics']);
});

test('a data query import saves its suggested values and skips one without a data template', function () {
	$this->conn->exec("INSERT INTO data_input (id, hash, name) VALUES (2, '88888888888888888888888888888888', 'Get SNMP Data (Indexed)')");

	$query  = template_import_hash('data_query', '99999999999999999999999999999999');
	$graph  = template_import_hash('data_query_graph', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa1');
	$sv     = template_import_hash('data_query_sv_graph', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa2');
	$rrd_sv = template_import_hash('data_query_sv_data_source', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa3');
	$input  = template_import_hash('data_input_method', '88888888888888888888888888888888');
	$absent = template_import_hash('data_template', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa4');
	$xml    = "<cacti><$query><name>Interfaces</name><description></description><xml_path>&lt;path_cacti&gt;/resource/snmp_queries/interface.xml</xml_path>"
		. "<data_input_id>$input</data_input_id><graphs><$graph><name>In/Out Bits</name><graph_template_id></graph_template_id><rrd></rrd>"
		. "<sv_graph><$sv><field_name>title</field_name><sequence>1</sequence><text>|host_description| - Traffic</text></$sv></sv_graph>"
		. "<sv_data_source><$rrd_sv><field_name>name</field_name><data_template_id>$absent</data_template_id><sequence>1</sequence><text>|query_ifName|</text></$rrd_sv></sv_data_source>"
		. "</$graph></graphs></$query></cacti>";
	$info = null;

	$log = template_exchange_capture_log(function () use ($xml, &$info) {
		$info = import_xml_data($xml, false, 1);
	}, POLLER_VERBOSITY_HIGH);

	$saved = $this->conn->query('SELECT id, name, data_input_id FROM snmp_query')->fetch(PDO::FETCH_ASSOC);

	expect($info['data_query'][0]['result'])->toBe('success')
		->and($info['files'])->toBe([CACTI_PATH_BASE . '/resource/snmp_queries/interface.xml' => 'found'])
		->and($saved['name'])->toBe('Interfaces')
		->and((int) $saved['data_input_id'])->toBe(2)
		->and($this->conn->query('SELECT text FROM snmp_query_graph_sv')->fetchColumn())->toBe('|host_description| - Traffic')
		->and((int) $this->conn->query('SELECT COUNT(*) FROM snmp_query_graph_rrd_sv')->fetchColumn())->toBe(0)
		->and($log)->toContain('Import Error: Error Importing into snmp_query_graph_rrd_sv table');
});
