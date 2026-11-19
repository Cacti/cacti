<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\LibraryUpdates;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/include/vendor/autoload.php';

define('CACTI_PATH_LIBRARY', $root . '/lib');
define('CACTI_PHP_SNMP', false);
define('VALID_HOST_FIELDS', '(hostname)');

foreach (['Mailer' => \Symfony\Component\Mailer\Mailer::class, 'Email' => \Symfony\Component\Mime\Email::class,
	'Address'         => \Symfony\Component\Mime\Address::class, 'Transport' => \Symfony\Component\Mailer\Transport::class] as $short => $class) {
	class_alias($class, __NAMESPACE__ . '\\' . $short);
}

foreach (['lib/functions.php' => ['mailer', 'repair_system_data_input_methods'], 'lib/utility.php' => ['push_out_host'],
	'lib/data_query.php'         => ['query_snmp_host']] as $file => $functions) {
	foreach ($functions as $function) {
		\cacti_fixture_source_function($root . '/' . $file, $function, __NAMESPACE__);
	}
}

function check(bool $test, string $message) : void {
	if (!$test) {
		throw new \RuntimeException($message);
	}
}

function __(string $format, mixed ...$args) : string {
	return sprintf($format, ...$args);
}

function __esc(string $format, mixed ...$args) : string {
	return sprintf($format, ...$args);
}

function cacti_strtoupper(string $value) : string {
	return strtoupper($value);
}

function cacti_sizeof(mixed $values) : int {
	return is_countable($values) ? count($values) : 0;
}

function cacti_log(string $message, mixed ...$options) : void {
	$GLOBALS['logs'][] = $message;
}

function db_fetch_cell_prepared(string $sql, array $args) : int {
	if (str_contains($sql, 'FROM data_input WHERE hash')) {
		return $args === ['3eb92bb845b9660a7445cf9740726522'] ? 1 : 0;
	}

	return str_contains($sql, 'FROM data_input_fields') ? 2 : 1;
}

function db_fetch_assoc_prepared(string $sql, array $args) : array {
	if (str_contains($sql, 'WHERE hash NOT IN')) {
		return [['id' => 11, 'hash' => 'old', 'data_name' => 'label']];
	}

	if (str_contains($sql, 'FROM data_template_rrd')) {
		return [['id' => 15, 'data_input_field_id' => 11]];
	}

	if (str_contains($sql, 'INNER JOIN data_input_data')) {
		return [['id' => 2, 'type_code' => 'hostname', 't_value' => '']];
	}

	return [];
}

function db_fetch_assoc(string $sql) : array {
	return [['id'                  => 15, 'local_data_id' => 3, 'host_id' => 1, 'data_input_id' => 2,
		'local_data_template_data_id' => 16, 'data_template_id' => 4]];
}

function db_fetch_row_prepared(string $sql, array $args) : array {
	if (str_contains($sql, 'FROM data_input_data')) {
		return ['value' => 'old.example'];
	}

	return ['id' => 1, 'hostname' => 'new.example', 'poller_id' => 1, 'bulk_walk_size' => 1] + array_fill_keys([
		'snmp_community', 'snmp_version', 'snmp_username', 'snmp_password', 'snmp_auth_protocol', 'snmp_priv_passphrase',
		'snmp_priv_protocol', 'snmp_context', 'snmp_port', 'snmp_timeout', 'snmp_engine_id'
	], '');
}

function db_execute_prepared(string $sql, array $args = []) : bool {
	$GLOBALS['writes'][strtok(trim($sql), "\n")][] = $args;

	return true;
}

function update_poller_cache(array $data) : array {
	return [];
}

function poller_update_poller_cache_from_buffer(mixed ...$args) : void {
}

function api_data_source_cache_crc_update(int $id) : void {
}

repair_system_data_input_methods();
check($GLOBALS['writes']['UPDATE data_template_rrd'] === [[2, 11, 15]], 'Damaged RRD mapping was not repointed');

push_out_host(1);
check($GLOBALS['writes']['REPLACE INTO data_input_data'] === [[2, 15, 4, 3, 1, 'new.example']], 'Unexpected poller update');
check(in_array('WARNING: Poller Cache updated Device[1], Field[hostname], Old[old.example], New[new.example]', $GLOBALS['logs'], true), 'Changed host value missing from poller log');

function read_config_option(string $name) : mixed {
	return ['settings_how' => 0, 'settings_smtp_timeout' => 5][$name] ?? null;
}

function parse_email_details(mixed $addresses, int $one = 0) : array {
	return ['name' => '', 'email' => 'monitor@example.test'];
}

class SenderChecked extends \RuntimeException {
}

function add_email_details(array $from, bool &$result, callable $add) : string {
	check($from === [['name' => '', 'email' => 'monitor@example.test']], 'Missing sender name did not become an empty string');
	check($add($from[0]['email'], $from[0]['name']), 'Symfony rejected a sender without a display name');

	throw new SenderChecked();
}

try {
	mailer('monitor@example.test', []);

	throw new \RuntimeException('Sender was not checked');
} catch (SenderChecked) {
}

function get_data_query_array(int $query) : array {
	return ['oid_index' => '.1.2', 'oid_index_parse' => 'OID/REGEXP:.*\.([0-9]+)$',
		'fields'           => ['label' => ['method' => 'get', 'direction' => 'input', 'source' => 'value',
			'oid'                                      => '.1.3', 'rewrite_index' => 'fixture']]];
}

function get_best_data_query_index_type(int $host, int $query) : string {
	return 'label';
}

function verify_index_order(array $query) : bool {
	return true;
}

function cacti_snmp_session_from_host(array $host, int $walk) : object {
	return new \stdClass();
}

function cacti_snmp_session_walk(object $session, string $oid) : array {
	return ['.1.2.7' => '7'];
}

function cacti_snmp_get(string $host, string $community, string $oid, mixed ...$args) : string {
	check($host === 'new.example' && $oid === '.1.3.8', 'Incorrect rewritten SNMP index');

	return 'sample';
}

function data_query_rewrite_indexes(array &$errors, mixed ...$args) : array {
	return ['7' => '8'];
}

function debug_log_insert(string $type, string $message) : void {
}

function query_debug_timer_offset(string $type, string $message) : void {
}

function rewrite_snmp_enum_value(mixed $value) : void {
}

function cacti_count(array $rows) : int {
	return count($rows);
}

class QueryRecordChecked extends \RuntimeException {
}

function data_query_format_record(int $host, int $query, string $field, mixed $rewrite, string $value, string $index, string $oid) : string {
	check([$host, $query, $field, $value, $index, $oid] === [1, 2, 'label', 'sample', '7', '.1.3.8'], 'Incorrect SNMP reading');

	throw new QueryRecordChecked();
}

try {
	query_snmp_host(1, 2);

	throw new \RuntimeException('Expected a reading from the rewritten index');
} catch (QueryRecordChecked) {
}

print "PASS: RRD mapping repair, host propagation, mail sender and rewritten SNMP reading\n";
