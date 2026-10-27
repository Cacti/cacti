<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiInputCommandFixture;
require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once dirname(__DIR__, 3) . '/lib/CactiValidator.php';
class_alias(\CactiValidator::class, __NAMESPACE__ . '\\CactiValidator');
class_alias(\Symfony\Component\Validator\Constraints\NotBlank::class, __NAMESPACE__ . '\\Assert\\NotBlank');
class_alias(\Symfony\Component\Validator\Constraints\Regex::class, __NAMESPACE__ . '\\Assert\\Regex');
$GLOBALS['command_writes'] = [];
$GLOBALS['command_error']  = false;
function db_fetch_cell_prepared(...$args) {
	return 0;
}
function compare_data(...$args) {
	return 0;
}
function cacti_log(...$args) {
}
function sql_save($data, $table) {
	$GLOBALS['command_writes'][] = $data;

	return 10;
}
function update_replication_crc(...$args) {
}
function generate_data_input_field_sequences(...$args) {
}
function data_input_save_message(...$args) {
}
function push_out_data_input_method(...$args) {
}
function isrv($name) {
	return $name === 'save_component_data_input';
}
function gfrv(...$args) {
	return 0;
}
function gnrv($name) {
	return $_POST[$name] ?? '';
}
function ierv(...$args) {
	return true;
}
function get_hash_data_input(...$args) {
	return 'fixture-hash';
}
function raise_message(...$args) {
	$GLOBALS['command_error'] = true;
}
function is_error_message() {
	return $GLOBALS['command_error'];
}

foreach (['xml_to_data_input_method', 'import_is_base64_encoded', 'xml_character_decode'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/import.php', $function, __NAMESPACE__);
}
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/data_input.php', 'form_save', __NAMESPACE__);
$valid   = ['<path_php_binary> -q <path_cacti>/scripts/query.php <hostname>', '"<path_php_binary>" "<path_cacti>/scripts/query file.php" "<hostname>"', '"C:\\Program Files\\PHP\\php.exe" "<path_cacti>/scripts/query.php" <hostname>', "<path_php_binary> '<path_cacti>/scripts/query file.php' '<hostname>'", ''];
$invalid = ['echo alpha; label', 'echo alpha | label', 'echo $(label)', 'echo `label`', "echo alpha\nlabel", "echo alpha\0label", 'echo > output', 'echo "unclosed', 'echo <broken-field>'];

if (($argv[1] ?? '') === 'form') {
	$command = base64_decode($argv[2], true);
	$_POST   = ['id' => 0, 'name' => 'Fixture', 'input_string' => $command, 'type_id' => 1];
	register_shutdown_function(function () { print json_encode(['writes' => count($GLOBALS['command_writes']), 'blocked' => $GLOBALS['command_error']]); });
	form_save();
	exit;
}
$GLOBALS['fields_data_input_edit']         = ['input_string' => [], 'name' => []];
$GLOBALS['fields_data_input_field_edit']   = [];
$GLOBALS['fields_data_input_field_edit_1'] = [];
$GLOBALS['preview_only']                   = false;
$cases                                     = 0;

foreach ([true => $valid, false => $invalid] as $allowed => $commands) {
	foreach ($commands as $command) {
		if (\cacti_input_string_is_safe($command) !== (bool) $allowed) {
			throw new \RuntimeException('Command syntax validator changed a supported template or accepted shell syntax.');
		}

		foreach ([false, true] as $encoded) {
			$GLOBALS['command_writes']    = [];
			$GLOBALS['import_debug_info'] = [];
			$xml                          = ['name' => 'Fixture', 'input_string' => $encoded ? base64_encode($command) : $command, 'fields' => []];
			$hashes                       = [];
			$result                       = xml_to_data_input_method('fixture-hash', $xml, $hashes);

			if (($allowed && (count($GLOBALS['command_writes']) !== 1 || $GLOBALS['command_writes'][0]['input_string'] !== $command)) || (!$allowed && ($result !== false || $GLOBALS['command_writes'] !== [] || empty($GLOBALS['import_debug_info']['unsafe'])))) {
				throw new \RuntimeException('Import command save boundary failed.');
			}
			$cases++;
		}
	}
}

foreach (['<path_php_binary> <path_cacti>/scripts/query.php <hostname>', 'echo alpha; label'] as $command) {
	$process = proc_open([PHP_BINARY, __FILE__, 'form', base64_encode($command)], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	if (!is_resource($process)) {
		throw new \RuntimeException('Cannot start form request.');
	}
	$result = json_decode(stream_get_contents($pipes[1]), true);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	if (proc_close($process) !== 0 || !is_array($result) || $result['writes'] !== (\cacti_input_string_is_safe($command) ? 1 : 0)) {
		throw new \RuntimeException('Form request boundary failed: ' . $errors);
	}
}
print json_encode(['import_cases' => $cases]);
