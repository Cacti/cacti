<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/*
 * import_xml_data() merges the hash map that each object importer returns.
 * An importer that rejects its object returns false instead, and the import
 * has to report that as a failed import rather than stop on a type error.
 */

require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/lib/import.php';
require_once dirname(__DIR__, 4) . '/lib/xml.php';
require_once dirname(__DIR__, 4) . '/lib/vdef.php';

// The bootstrap loads the hash code tables inside a function scope, so they
// are not globals here; import_xml_data() reads them as globals.
if (empty($GLOBALS['hash_type_codes'])) {
	[$GLOBALS['hash_type_codes'], $GLOBALS['cacti_version_codes']] = (static function () : array {
		require CACTI_PATH_INCLUDE . '/global_arrays.php';

		return [$hash_type_codes, $cacti_version_codes];
	})();
}

function template_import_failure_document(string $type, string $body) : string {
	return '<cacti><hash_' . get_hash_version($type) . str_repeat('a', 32) . '>' . $body . '</hash_' . get_hash_version($type) . str_repeat('a', 32) . '></cacti>';
}

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->session = "$database_hostname:$database_port:$database_default";
	$this->prior   = $database_sessions[$this->session] ?? null;
	$this->globals = [];

	$this->conn = new FakeMySQLPDO();

	foreach (['graph_templates', 'graph_templates_item', 'graph_template_input', 'data_template', 'data_template_rrd', 'host_template',
		'data_input', 'data_input_fields', 'snmp_query', 'snmp_query_graph', 'snmp_query_graph_sv', 'snmp_query_graph_rrd_sv',
		'graph_templates_gprint', 'cdef', 'cdef_items', 'vdef', 'vdef_items', 'data_source_profiles'] as $table) {
		$this->conn->exec("CREATE TABLE $table (id INTEGER PRIMARY KEY AUTOINCREMENT, hash TEXT DEFAULT '', name TEXT DEFAULT '', input_string TEXT DEFAULT '')");
	}

	$this->conn->exec('CREATE TABLE data_source_profiles_rra (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

	$database_sessions[$this->session] = $this->conn;

	foreach (['fields_host_template_edit', 'fields_data_query_edit', 'fields_data_query_item_edit', 'fields_data_input_edit',
		'fields_data_input_field_edit', 'fields_data_input_field_edit_1', 'preview_only', 'import_messages'] as $name) {
		$this->globals[$name] = $GLOBALS[$name] ?? null;
	}

	$GLOBALS['fields_host_template_edit']      = ['name' => []];
	$GLOBALS['fields_data_query_edit']         = ['name' => []];
	$GLOBALS['fields_data_query_item_edit']    = ['name' => []];
	$GLOBALS['fields_data_input_edit']         = ['name' => [], 'input_string' => []];
	$GLOBALS['fields_data_input_field_edit']   = [];
	$GLOBALS['fields_data_input_field_edit_1'] = [];
	$GLOBALS['import_messages']                = [];
});

afterEach(function () {
	global $database_sessions;

	if ($this->prior === null) {
		unset($database_sessions[$this->session]);
	} else {
		$database_sessions[$this->session] = $this->prior;
	}

	foreach ($this->globals as $name => $value) {
		$GLOBALS[$name] = $value;
	}
});

test('a rejected object fails the template import without a type error', function (string $type, string $body, bool $preview) {
	$GLOBALS['preview_only'] = $preview;

	$xml = template_import_failure_document($type, $body);

	expect(import_xml_data($xml, false, 1))->toBeFalse();
})->with([
	'data input method with an unsafe input string, preview' => ['data_input_method', '<name>Method</name><input_string>a;b</input_string>', true],
	'data input method with an unsafe input string'          => ['data_input_method', '<name>Method</name><input_string>a;b</input_string>', false],
	'device template naming a damaged graph template hash'   => ['host_template', '<name>Device</name><graph_templates>damaged</graph_templates>', false],
	'data query with a damaged graph hash'                   => ['data_query', '<name>Query</name><graphs><damaged><name>Graph</name></damaged></graphs>', true],
	'vdef with a damaged item hash'                          => ['vdef', '<name>Vdef</name><items><damaged><value>1</value></damaged></items>', true],
]);

test('an accepted object still reaches the import result', function () {
	$GLOBALS['preview_only'] = true;

	$xml    = template_import_failure_document('vdef', '<name>Vdef</name>');
	$result = import_xml_data($xml, false, 1);

	expect($result)->toBeArray()
		->and($result['vdef'][0]['title'])->toBe('Vdef');
});
