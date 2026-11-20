<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\DisplayAndValidation;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/include/vendor/autoload.php';
require $root . '/lib/CactiValidator.php';
require $root . '/lib/functions.php';
require $root . '/lib/html.php';
require $root . '/include/vendor/parsedown/Parsedown.php';
define('CACTI_PATH_INCLUDE', $root . '/include');
class_alias(\Parsedown::class, __NAMESPACE__ . '\Parsedown');

foreach (['Callback', 'Regex', 'NotBlank'] as $type) {
	class_alias('Symfony\Component\Validator\Constraints\\' . $type, __NAMESPACE__ . '\Assert\\' . $type);
}

function check(bool $condition, string $description) : void {
	if (!$condition) {
		throw new \RuntimeException($description);
	}
}

function __(string $text, mixed ...$args) : string {
	return sprintf($text, ...$args);
}

function __esc(string $text, mixed ...$args) : string {
	return \htmle(__($text, ...$args));
}

function grv(string $name) : mixed {
	return $_REQUEST[$name] ?? '';
}

function gnrv(string $name) : mixed {
	return grv($name);
}

function gfrv(string $name) : int {
	return (int) grv($name);
}

function isrv(string $name) : bool {
	return isset($_REQUEST[$name]);
}

function read_config_option(string $name) : mixed {
	return $GLOBALS['settings'][$name] ?? '';
}

function get_username() : string {
	return 'Dana & Lee';
}

function cacti_log(string $message, mixed ...$parameters) : void {
	$GLOBALS['logs'][] = $message;
}

function raise_message(mixed $id, mixed ...$parameters) : void {
	$GLOBALS['messages'][$id] = $parameters;
}

function is_error_message() : bool {
	return !empty($GLOBALS['messages']);
}

function header(string $line) : void {
	$GLOBALS['location'] = $line;
}

function get_hash_data_input(mixed $id) : string {
	return 'demo';
}

function db_column_exists(string $table, string $column) : bool {
	return $table === 'plugin_available' && $column === 'readme';
}

function db_fetch_cell_prepared(string $sql, array $params) : string|false {
	return $params === ['demo', 'v1'] ? base64_encode("# Manual\n\n<b>Sample</b>") : false;
}

function db_fetch_row_prepared(string $sql, array $params) : array {
	return ['name' => 'Device', 'data_source_path' => '<path_rra>/north campus.rrd'];
}

function generate_data_source_path(int $id) : string {
	return '<path_rra>/device_' . $id . '.rrd';
}

class CactiValidator {
	public static function validateInput(mixed $value, string $field, array $constraints, mixed $message) : mixed {
		$GLOBALS['violations'][$field] = \CactiValidator::validate($value, $constraints);

		return $value;
	}
}

class CactiTableFilter {
	public bool $render_layouts = true;
	public string $rows_label   = '';
	public bool $has_refresh    = false;
	public int $def_refresh     = 0;
	public function render() : void {
	}
}

class QueryReady extends \RuntimeException {
}

function db_qstr(string $value) : string {
	return "'" . $value . "'";
}

function db_fetch_cell(string $sql) : never {
	throw new QueryReady($sql);
}

function get_total_row_data(mixed $user, string $sql, array $params, string $entity) : never {
	throw new QueryReady($sql);
}

foreach (['lib/database.php' => ['db_like_escape'], 'pollers.php' => ['pollers'], 'sites.php' => ['sites'],
	'data_input.php'            => ['form_save'], 'lib/clog_webapi.php' => ['clog_purge_logfile', 'clog_validate_filename'],
	'lib/functions.php'         => ['get_data_source_path'], 'lib/import.php' => ['xml_to_data_input_method', 'import_is_base64_encoded', 'xml_character_decode'],
	'lib/plugins.php'           => ['api_plugin_get_available_file_contents']] as $file => $functions) {
	foreach ($functions as $function) {
		cacti_fixture_source_function($root . '/' . $file, $function, __NAMESPACE__);
	}
}

if (($argv[1] ?? '') === 'save') {
	$_REQUEST = ['save_component_data_input' => '1', 'id' => '5', 'type_id' => '1', 'name' => 'Example', 'input_string' => "reader '"];
	register_shutdown_function(static function () : void {
		check(isset($GLOBALS['messages']['validation_error']), 'Input form must report an incomplete template');
		check($GLOBALS['location'] === 'Location: data_input.php?action=edit&id=5', 'Input form must return to the current method');
	});
	form_save();

	throw new \RuntimeException('Invalid input method was saved');
}

$directory = realpath(sys_get_temp_dir()) . '/cacti logs ' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
define('CACTI_PATH_RRA', $directory);
register_shutdown_function(static function () use ($directory) : void {
	foreach (glob($directory . '/*') as $file) {
		unlink($file);
	}
	rmdir($directory);
});
$settings = ['path_cactilog' => $directory . '/campus.log', 'log_action' => LOG_ACTION_PURGE_ROTATE];
$name     = 'campus.log ';
check(!clog_validate_filename($name), 'A trailing space is not part of a log filename');

foreach (['purge' => 'campus.log.1', 'rotate' => 'campus.log'] as $operation => $filename) {
	file_put_contents($directory . '/' . $filename, 'log entry');
	$_REQUEST['filename'] = $filename;
	clog_purge_logfile($operation);
	check(str_contains($messages['clog_removed'][0], 'Dana &amp; Lee'), 'Display name was not encoded');
	check(str_contains(end($logs), 'Dana & Lee'), 'Log entry changed the original name');
}
check(!file_exists($directory . '/campus.log.1') && filesize($directory . '/campus.log') === 0, 'Log maintenance did not complete');

ob_start();
html_create_list([['host_id' => 1, 'name' => 'Campus', 'class' => 'A&B', 'style' => "font-family: 'Noto Sans'"]], 'name', '', '');
$options = ob_get_clean();
check(str_contains($options, "data-class='A&amp;B'") && str_contains($options, "data-style='font-family: &apos;Noto Sans&apos;'"), 'Option attributes lost literal text');

ob_start();
check(api_plugin_get_available_file_contents('demo','v1','readme'), 'Plugin manual missing');
$html = ob_get_clean();
check(str_contains($html, '<h1>Manual</h1>') && str_contains($html, '&lt;b&gt;Sample&lt;/b&gt;'), 'Plugin manual lost Markdown or literal HTML');

$_SESSION[SESS_USER_ID] = 4;
$_REQUEST               = ['filter' => '90%_load', 'rows' => '10'];

foreach (['sites', 'pollers'] as $controller) {
	try {
		(__NAMESPACE__ . '\\' . $controller)();

		throw new \RuntimeException('Controller did not count filtered rows');
	} catch (QueryReady $query) {
		check(str_contains($query->getMessage(), "WHERE name LIKE '%90\\%\\_load%'"), 'Filter treated search characters as wildcards');
	}
}

foreach (['<path_rra>/device.rrd' => 0, '<path_rra>/north campus.rrd' => 1] as $path => $count) {
	$_REQUEST['data_source_path'] = $path;
	cacti_fixture_source_block($root . '/data_sources.php', __NAMESPACE__, '~(\$save2\[\x27data_source_path\x27\]\s*= CactiValidator::validateInput\(.*?\)\], 3\);)~s');
	check(count($violations['data_source_path']) === $count, 'Unexpected data source path constraint result');
}
check(data_source_path_is_clean(CACTI_PATH_RRA . '/device.rrd'), 'A space in the configured RRA root must be accepted');
check(get_data_source_path(42,true) === CACTI_PATH_RRA . '/device_42.rrd', 'Expanded path must fall back to the generated name');
check(str_contains(end($logs), 'data_source_path'), 'No path fallback diagnostic');

check(cacti_input_string_is_safe(null) && cacti_input_string_is_safe(''), 'Empty input method template rejected');
check(!cacti_input_string_is_safe(false), 'Non-string template accepted');
check(cacti_input_string_is_safe('reader "<device>"') && !cacti_input_string_is_safe("reader '"), 'Unbalanced template quoting was accepted');
$fields_data_input_edit       = ['input_string' => []];
$fields_data_input_field_edit = $fields_data_input_field_edit_1 = [];
$xml                          = ['input_string' => base64_encode("reader '")];
$cache                        = [];
check(xml_to_data_input_method('demo', $xml, $cache) === false, 'Import accepted an incomplete template');
check($import_debug_info['type'] === 'fail' && $import_debug_info['unsafe'] === true, 'Import did not identify the rejected template');
