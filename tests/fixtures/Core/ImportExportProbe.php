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
 * Runs unchanged functions of the template and Package import and export,
 * and of the device export, in their own process against an SQLite database
 * built from the scenario. Prints what they returned, what they called, the
 * tables named in the scenario, the redirect, the raised messages and the
 * output.
 *
 * The scenario names the functions to load from the checked-in source, the
 * function or CLI script to run, its arguments, the request and the table
 * rows. Every Cacti function the loaded code calls and the scenario does not
 * load is answered here: the database calls by SQLite, the others by a
 * recording fake that returns the value the scenario gives for it.
 */

namespace CactiImportExportFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require $root . '/include/global_constants.php';

if (!defined('CACTI_VERSION')) {
	define('CACTI_VERSION', '1.2.32');
}

$scenario = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);

$GLOBALS['scenario'] = $scenario;
$GLOBALS['calls']    = array();
$GLOBALS['raised']   = array();
$GLOBALS['headers']  = array();
$GLOBALS['log']      = array();
$GLOBALS['status']   = null;

$config = array(
	'base_path'       => $scenario['base_path'] ?? $root,
	'library_path'    => $root . '/lib',
	'include_path'    => $root . '/include',
	'is_web'          => false,
	'cacti_server_os' => 'unix',
	'poller_id'       => 1,
);

$_SERVER  = array_merge($_SERVER, $scenario['server'] ?? array());
$_SESSION = $scenario['session'] ?? array();
$_POST    = $scenario['post'] ?? array();
$_FILES   = $scenario['files'] ?? array();
$_REQUEST = ($scenario['request'] ?? array()) + $_POST;

/* the hash and version code tables, read from the checked-in include/global_arrays.php; both are flat lists of quoted pairs */
$tables = array();

foreach (array('hash_type_codes', 'cacti_version_codes') as $name) {
	if (!preg_match('/^\$' . $name . ' = array\((.*?)\);$/ms', file_get_contents($root . '/include/global_arrays.php'), $match)) {
		throw new \RuntimeException('Cannot locate $' . $name);
	}

	preg_match_all("/'([^']+)'\\s*=>\\s*'([^']*)'/", $match[1], $pairs);

	$tables[$name] = array_combine($pairs[1], $pairs[2]);
}

$hash_type_codes     = $tables['hash_type_codes'];
$cacti_version_codes = $tables['cacti_version_codes'];

$hash_type_names = array_combine(array_keys($hash_type_codes), array_keys($hash_type_codes));

foreach ($scenario['globals'] ?? array() as $name => $value) {
	$GLOBALS[$name] = $value;
}

$allowed = array('lib/import.php', 'lib/export.php', 'lib/functions.php', 'lib/html.php', 'include/csrf.php', 'templates_import.php', 'templates_export.php', 'package_import.php', 'host.php');

foreach (array_merge(array(array('lib/functions.php', 'cacti_sizeof'), array('lib/functions.php', 'cacti_count'), array('lib/functions.php', 'cacti_version_compare'), array('lib/functions.php', 'version_to_decimal'), array('lib/html.php', 'html_escape')), $scenario['slice'] ?? array()) as $slice) {
	if (!in_array($slice[0], $allowed, true)) {
		throw new \RuntimeException('Not a file of the import or export: ' . $slice[0]);
	}

	\cacti_fixture_source_function($root . '/' . $slice[0], $slice[1], __NAMESPACE__);
}

/* any function of the import and export code that was not loaded records its call; the names are the fixed list below */
$fakes = array(
	'xml2array', 'xml_to_graph_template', 'xml_to_data_template', 'xml_to_host_template',
	'xml_to_data_input_method', 'xml_to_data_query', 'xml_to_gprint_preset', 'xml_to_cdef',
	'xml_to_vdef', 'xml_to_data_source_profile', 'xml_detect_ignorable_hash_cache',
	'graph_template_input_xml_preflight', 'repair_system_data_input_methods',
	'db_begin_transaction', 'db_commit_transaction', 'db_rollback_transaction',
	'import_xml_data', 'import_package', 'import_package_get_name', 'import_package_get_details',
	'import_display_results', 'import_validate_signature', 'package_validate_signature',
	'package_import_write_session_file', 'package_prepare_import_array', 'import_display_package_data',
	'prepare_template_display', 'display_template_data', 'validate_request_vars', 'host_validate_vars',
	'get_device_records', 'get_item_xml', 'top_header', 'bottom_footer', 'update_replication_crc',
	'retemplate_graphs', 'get_cacti_cli_version', 'csrf_require_post',
	'resolve_hash_to_id', 'raise_message_javascript', 'clean_up_file_name', 'get_public_key',
	'cacti_csv_safe', 'compare_data', 'read_config_option', 'isempty_request_var', 'graph_template_input_column_is_allowed',
	'get_hash_version', 'get_hash_vdef', 'get_hash_data_query', 'get_hash_data_input', 'get_hash_graph_template', 'get_hash_data_template',
);

$fake_source = '<?php namespace ' . __NAMESPACE__ . ';' . PHP_EOL;

foreach ($fakes as $fake) {
	if (!function_exists(__NAMESPACE__ . '\\' . $fake)) {
		$fake_source .= 'function ' . $fake . '(...$arguments) { return fake(\'' . $fake . '\', $arguments); }' . PHP_EOL;
	}
}

/* the fake names are the fixed list above; the file is written and removed by this process only */
$fake_file = tempnam(sys_get_temp_dir(), 'ie');
file_put_contents($fake_file, $fake_source);
require $fake_file;
unlink($fake_file);

function fake($name, $arguments) {
	$GLOBALS['calls'][] = array($name, $arguments);

	if ($name == 'compare_data') {
		return 0;
	}

	if (!array_key_exists($name, $GLOBALS['scenario']['returns'] ?? array())) {
		return null;
	}

	$value = $GLOBALS['scenario']['returns'][$name];

	/* a list under 'each' answers successive calls */
	if (is_array($value) && array_key_exists('each', $value)) {
		$GLOBALS['answered'][$name] = ($GLOBALS['answered'][$name] ?? -1) + 1;

		return $value['each'][$GLOBALS['answered'][$name]] ?? null;
	}

	return $value;
}

function __($text, ...$arguments) {
	return (cacti_sizeof($arguments) && strpos($text, '%') !== false ? vsprintf($text, $arguments) : $text);
}

function cacti_log($message, $output = false, $facility = '', $level = 0) {
	$GLOBALS['log'][] = $message;
}

function raise_message($id, $message = '', $level = 0) {
	$GLOBALS['raised'][] = array($id, $message, $level);
}

function header($header) {
	$GLOBALS['headers'][] = $header;
}

function http_response_code($code) {
	$GLOBALS['status'] = $code;
}

function is_cacti_public_key($key) {
	return trim($key) !== '' && trim($key) === trim($GLOBALS['scenario']['public_key'] ?? '');
}

function xml_to_array($xml) {
	return json_decode(json_encode($xml), true);
}

function isset_request_var($name) {
	return isset($_REQUEST[$name]);
}

function get_request_var($name) {
	return $_REQUEST[$name] ?? '';
}

function get_nfilter_request_var($name, $default = '') {
	return $_REQUEST[$name] ?? $default;
}

function get_filter_request_var($name) {
	return $_REQUEST[$name] ?? '';
}

function api_plugin_hook_function($name, $data = '') {
	return $data;
}

function db() {
	static $db = null;

	if ($db === null) {
		$db = new \PDO('sqlite::memory:');
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
		$db->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
	}

	return $db;
}

function db_query($sql, $params = array()) {
	$GLOBALS['sql'][] = preg_replace('/\s+/', ' ', trim($sql));

	$statement = db()->prepare($sql);
	$statement->execute(array_values($params));

	return $statement;
}

function db_fetch_cell_prepared($sql, $params = array()) {
	$value = db_query($sql, $params)->fetchColumn();

	return $value === false ? false : $value;
}

function db_fetch_cell($sql) {
	return db_fetch_cell_prepared($sql);
}

function db_fetch_row_prepared($sql, $params = array()) {
	$row = db_query($sql, $params)->fetch();

	return $row === false ? array() : $row;
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	return db_query($sql, $params)->fetchAll();
}

function db_fetch_assoc($sql) {
	return db_fetch_assoc_prepared($sql);
}

function db_execute_prepared($sql, $params = array()) {
	db_query($sql, $params);

	return true;
}

function db_fetch_insert_id() {
	return db()->lastInsertId();
}

/* a row with a positive id that exists is updated, any other row is inserted */
function sql_save($row, $table, $key_cols = 'id', $autoinc = true) {
	if (is_array($key_cols)) {
		$statement = db_query('INSERT OR REPLACE INTO ' . $table . ' (' . '`' . implode('`, `', array_keys($row)) . '`' . ') VALUES (' . implode(', ', array_fill(0, cacti_sizeof($row), '?')) . ')', $row);

		return db()->lastInsertId();
	}

	if (!empty($row['id']) && db_fetch_cell_prepared('SELECT id FROM ' . $table . ' WHERE id = ?', array($row['id']))) {
		$id = $row['id'];
		unset($row['id']);

		db_query('UPDATE ' . $table . ' SET ' . implode(' = ?, ', array_keys($row)) . ' = ? WHERE id = ?', array_merge(array_values($row), array($id)));

		return $id;
	}

	if (empty($row['id'])) {
		unset($row['id']);
	}

	db_query('INSERT INTO ' . $table . ' (' . '`' . implode('`, `', array_keys($row)) . '`' . ') VALUES (' . implode(', ', array_fill(0, cacti_sizeof($row), '?')) . ')', $row);

	return db()->lastInsertId();
}

foreach ($scenario['schema'] ?? array() as $statement) {
	db()->exec($statement);
}

foreach ($scenario['db'] ?? array() as $table => $rows) {
	foreach ($rows as $row) {
		db_query('INSERT INTO ' . $table . ' (' . '`' . implode('`, `', array_keys($row)) . '`' . ') VALUES (' . implode(', ', array_fill(0, cacti_sizeof($row), '?')) . ')', $row);
	}
}

$GLOBALS['sql'] = array();

register_shutdown_function(function () {
	$output = '';

	while (ob_get_level() > 0) {
		$output = ob_get_clean() . $output;
	}

	$tables = array();

	foreach ($GLOBALS['scenario']['dump'] ?? array() as $table) {
		$tables[$table] = db()->query('SELECT * FROM ' . $table . ' ORDER BY 1')->fetchAll();
	}

	$globals = array();

	foreach ($GLOBALS['scenario']['read_globals'] ?? array() as $name) {
		$globals[$name] = $GLOBALS[$name] ?? null;
	}

	print json_encode(array(
		'result'    => $GLOBALS['result'] ?? null,
		'arguments' => $GLOBALS['arguments'] ?? null,
		'error'     => $GLOBALS['error'] ?? null,
		'calls'     => $GLOBALS['calls'],
		'raised'    => $GLOBALS['raised'],
		'headers'   => $GLOBALS['headers'],
		'status'    => $GLOBALS['status'],
		'log'       => $GLOBALS['log'],
		'sql'       => $GLOBALS['sql'],
		'tables'    => $tables,
		'globals'   => $globals,
		'output'    => $output,
	), JSON_PARTIAL_OUTPUT_ON_ERROR);

	exit(0);
});

ob_start();

try {
	if (isset($scenario['cli'])) {
		/* a CLI script runs with its bootstrap requires removed, keeping its line numbers */
		if (!in_array($scenario['cli'], array('cli/import_template.php', 'cli/import_package.php'), true)) {
			throw new \RuntimeException('Not an import CLI script: ' . $scenario['cli']);
		}

		$_SERVER['argv'] = array_merge(array($scenario['cli']), $scenario['argv'] ?? array());

		$source    = preg_replace('/^(require|require_once)\(.*$/m', '', file_get_contents($root . '/' . $scenario['cli']));
		$temporary = tempnam(sys_get_temp_dir(), 'cacti-cli-slice-');

		/* the namespace takes the place of the interpreter line */
		file_put_contents($temporary, preg_replace('/^#!.*$/m', '<?php namespace ' . __NAMESPACE__ . '; ?>', $source, 1));
		$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . '/' . $scenario['cli']);

		try {
			require $temporary;
		} finally {
			unlink($temporary);
		}
	} else {
		$arguments = $scenario['arguments'] ?? array();

		$GLOBALS['result']    = (__NAMESPACE__ . '\\' . $scenario['call'])(...$arguments);
		$GLOBALS['arguments'] = $arguments;
	}
} catch (\Throwable $error) {
	$GLOBALS['error'] = get_class($error) . ': ' . $error->getMessage();
}
