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
 * Behavior of the template exporters in lib/export.php against a sqlite copy
 * of the tables they read.
 *
 * Every exporter refuses a template whose row is missing: it counts the
 * failure in $export_errors, logs a repair hint and returns no XML, so a
 * Package never carries a dangling hash.  The positive cases pin the nested
 * references that are written as hashes rather than ids: inherited CDEF items,
 * VDEF form fields and the data query suggested values.
 */

require_once __DIR__ . '/TemplateExchangeFixture.php';
require_once dirname(__DIR__, 4) . '/lib/export.php';
require_once dirname(__DIR__, 4) . '/lib/vdef.php';

function template_export_seed() : TemplateExchangePDO {
	$conn = new TemplateExchangePDO();

	$conn->exec('CREATE TABLE graph_templates (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE graph_templates_graph (id INTEGER PRIMARY KEY, graph_template_id INTEGER, local_graph_id INTEGER)');
	$conn->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, hash TEXT, graph_template_id INTEGER, local_graph_id INTEGER, sequence INTEGER)');
	$conn->exec('CREATE TABLE graph_template_input (id INTEGER PRIMARY KEY, hash TEXT, graph_template_id INTEGER)');
	$conn->exec('CREATE TABLE data_template (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE data_template_data (id INTEGER PRIMARY KEY, data_template_id INTEGER, local_data_id INTEGER)');
	$conn->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY, hash TEXT, data_template_id INTEGER, local_data_id INTEGER)');
	$conn->exec('CREATE TABLE data_input_data (data_input_field_id INTEGER, data_template_data_id INTEGER, value TEXT)');
	$conn->exec('CREATE TABLE data_input (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE data_input_fields (id INTEGER PRIMARY KEY, hash TEXT, data_input_id INTEGER)');
	$conn->exec('CREATE TABLE cdef_items (id INTEGER PRIMARY KEY, hash TEXT, cdef_id INTEGER, sequence INTEGER, type INTEGER, value TEXT)');
	$conn->exec('CREATE TABLE vdef_items (id INTEGER PRIMARY KEY, hash TEXT, vdef_id INTEGER, sequence INTEGER, type INTEGER, value TEXT)');
	$conn->exec('CREATE TABLE graph_templates_gprint (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE data_source_profiles (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE data_source_profiles_cf (data_source_profile_id INTEGER, consolidation_function_id INTEGER)');
	$conn->exec('CREATE TABLE data_source_profiles_rra (id INTEGER PRIMARY KEY, data_source_profile_id INTEGER, steps INTEGER)');
	$conn->exec('CREATE TABLE host_template (id INTEGER PRIMARY KEY, hash TEXT, name TEXT)');
	$conn->exec('CREATE TABLE host_template_graph (host_template_id INTEGER, graph_template_id INTEGER)');
	$conn->exec('CREATE TABLE host_template_snmp_query (host_template_id INTEGER, snmp_query_id INTEGER)');
	$conn->exec('CREATE TABLE snmp_query_graph_rrd (snmp_query_graph_id INTEGER, data_template_id INTEGER, data_template_rrd_id INTEGER, snmp_field_name TEXT)');
	$conn->exec('CREATE TABLE snmp_query_graph_sv (id INTEGER PRIMARY KEY, hash TEXT, snmp_query_graph_id INTEGER, sequence INTEGER, field_name TEXT, text TEXT)');
	$conn->exec('CREATE TABLE snmp_query_graph_rrd_sv (id INTEGER PRIMARY KEY, hash TEXT, snmp_query_graph_id INTEGER, data_template_id INTEGER, sequence INTEGER, field_name TEXT, text TEXT)');

	return $conn;
}

function template_export_form_tables(PDO $conn) : void {
	global $fields_cdef_edit, $fields_data_query_edit, $fields_data_query_item_edit;

	template_exchange_table($conn, 'cdef', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_cdef_edit);
	template_exchange_table($conn, 'vdef', ['id INTEGER PRIMARY KEY', 'hash TEXT'], preset_vdef_form_list());
	template_exchange_table($conn, 'snmp_query', ['id INTEGER PRIMARY KEY', 'hash TEXT'], $fields_data_query_edit);
	template_exchange_table($conn, 'snmp_query_graph', ['id INTEGER PRIMARY KEY', 'hash TEXT', 'snmp_query_id INTEGER'], $fields_data_query_item_edit);
}

beforeEach(function () {
	$this->conn           = template_export_seed();
	$this->export_globals = template_exchange_wire($this->conn, ['export_errors']);

	$GLOBALS['export_errors'] = 0;

	template_export_form_tables($this->conn);
});

afterEach(function () {
	template_exchange_restore($this->export_globals);
});

test('every exporter refuses a missing template and logs a repair hint', function (string $exporter, string $message) {
	$xml = null;

	$log = template_exchange_capture_log(function () use ($exporter, &$xml) {
		$xml = @$exporter(404);
	});

	expect($xml)->toBe('')
		->and($GLOBALS['export_errors'])->toBe(1)
		->and($log)->toContain($message)
		->and($log)->toContain('Please run database repair script to identify and/or correct.');
})->with([
	'graph template'      => ['graph_template_to_xml', 'ERROR: Invalid Graph Template found in Database for Template'],
	'data template'       => ['data_template_to_xml', 'ERROR: Invalid Data Template found in Database.'],
	'data input method'   => ['data_input_method_to_xml', 'ERROR: Invalid Data Input Method found in Data Template.'],
	'cdef'                => ['cdef_to_xml', 'ERROR: Invalid CDEF found in Graph Template.'],
	'gprint preset'       => ['gprint_preset_to_xml', 'ERROR: Invalid GPRINT preset found in Graph Template.'],
	'data source profile' => ['data_source_profile_to_xml', 'ERROR: Invalid Data Source Profile found during Data Template export.'],
	'device template'     => ['host_template_to_xml', 'ERROR: Invalid Device Template found during Export.'],
	'data query'          => ['data_query_to_xml', 'ERROR: Invalid Data Query found during Export.'],
]);

test('a graph template without its template graph row is refused with both ids', function () {
	$this->conn->exec("INSERT INTO graph_templates (id, hash, name) VALUES (7, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Traffic')");

	$xml = null;

	$log = template_exchange_capture_log(function () use (&$xml) {
		$xml = @graph_template_to_xml(7);
	});

	expect($xml)->toBe('')
		->and($log)->toContain('for Template Traffic[7] GTGid: ');
});

test('a missing VDEF returns the inline error instead of XML', function () {
	expect(vdef_to_xml(404))->toBe('Invalid VDEF.')
		->and($GLOBALS['export_errors'])->toBe(0);
});

test('a VDEF exports its visible form fields and items', function () {
	$this->conn->exec("INSERT INTO vdef (id, hash, name) VALUES (3, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'Max & Min')");
	$this->conn->exec("INSERT INTO vdef_items (id, hash, vdef_id, sequence, type, value) VALUES (31, 'cccccccccccccccccccccccccccccccc', 3, 1, 4, 'MAXIMUM')");

	$xml = vdef_to_xml(3);

	expect($xml)->toStartWith('<hash_' . get_hash_version('vdef') . 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb>')
		->and($xml)->toContain("\t<name>Max &amp; Min</name>")
		->and($xml)->toContain('<hash_' . get_hash_version('vdef_item') . 'cccccccccccccccccccccccccccccccc>')
		->and($xml)->toContain('<value>MAXIMUM</value>')
		->and(simplexml_load_string($xml))->not->toBeFalse();
});

test('an inherited CDEF item is exported as the hash of the CDEF it names', function () {
	$this->conn->exec("INSERT INTO cdef (id, hash, name) VALUES (1, 'dddddddddddddddddddddddddddddddd', 'Bits')");
	$this->conn->exec("INSERT INTO cdef (id, hash, name) VALUES (2, 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee', 'Total Bits')");
	$this->conn->exec("INSERT INTO cdef_items (id, hash, cdef_id, sequence, type, value) VALUES
		(21, 'ffffffffffffffffffffffffffffffff', 2, 1, 5, '1'),
		(22, '11111111111111111111111111111111', 2, 2, 2, '3')");

	$xml = cdef_to_xml(2);

	expect($xml)->toContain('<value>hash_' . get_hash_version('cdef') . 'dddddddddddddddddddddddddddddddd</value>')
		->and($xml)->toContain('<value>3</value>')
		->and($xml)->not->toContain('<value>1</value>')
		->and(simplexml_load_string($xml))->not->toBeFalse();
});

test('data query suggested values carry their own and their data template hashes', function () {
	$this->conn->exec("INSERT INTO snmp_query (id, hash, name, xml_path, data_input_id) VALUES (4, '22222222222222222222222222222222', 'Interfaces', '<path_cacti>/resource/snmp_queries/interface.xml', 0)");
	$this->conn->exec("INSERT INTO snmp_query_graph (id, hash, snmp_query_id, name, graph_template_id) VALUES (41, '33333333333333333333333333333333', 4, 'In/Out Bits', 0)");
	$this->conn->exec("INSERT INTO data_template (id, hash, name) VALUES (9, '44444444444444444444444444444444', 'Traffic')");
	$this->conn->exec("INSERT INTO data_template_rrd (id, hash, data_template_id, local_data_id) VALUES (91, '55555555555555555555555555555555', 9, 0)");
	$this->conn->exec("INSERT INTO snmp_query_graph_rrd VALUES (41, 9, 91, 'ifInOctets')");
	$this->conn->exec("INSERT INTO snmp_query_graph_sv (id, hash, snmp_query_graph_id, sequence, field_name, text) VALUES (51, '66666666666666666666666666666666', 41, 1, 'title', '|host_description| - <Traffic>')");
	$this->conn->exec("INSERT INTO snmp_query_graph_rrd_sv (id, hash, snmp_query_graph_id, data_template_id, sequence, field_name, text) VALUES (61, '77777777777777777777777777777777', 41, 9, 1, 'name', '|query_ifName|')");

	$xml = data_query_to_xml(4);

	expect($xml)->toContain('<snmp_field_name>ifInOctets</snmp_field_name>')
		->and($xml)->toContain('<data_template_rrd_id>hash_' . get_hash_version('data_template_item') . '55555555555555555555555555555555</data_template_rrd_id>')
		->and($xml)->toContain('<hash_' . get_hash_version('data_query_sv_graph') . '66666666666666666666666666666666>')
		->and($xml)->toContain('<text>|host_description| - &lt;Traffic&gt;</text>')
		->and($xml)->toContain('<hash_' . get_hash_version('data_query_sv_data_source') . '77777777777777777777777777777777>')
		->and(substr_count($xml, '<data_template_id>hash_' . get_hash_version('data_template') . '44444444444444444444444444444444</data_template_id>'))->toBe(2)
		->and(simplexml_load_string($xml))->not->toBeFalse();
});
