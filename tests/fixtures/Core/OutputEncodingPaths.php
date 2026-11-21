<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\OutputEncodingPaths;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root = dirname(__DIR__, 3);
$mode = $argv[1] ?? '';
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

/*
 * The table filter is a class, so it runs from its own file under the unit
 * bootstrap instead of from a slice.
 */
if ($mode === 'filter') {
	chdir($root);
	require $root . '/tests/bootstrap-unit.php';
	require_once $root . '/lib/html_filter.php';

	$filter = new \CactiTableFilter('Log Filters', 'clog.php?page=1', 'logfile', 'sess_clog', '', false, false);
	$filter->set_filter_array(['rows' => [[
		'filename' => ['method' => 'drop_array', 'array' => [], 'value' => 'cacti.log'],
		'rfilter'  => ['method' => 'textbox', 'filter' => FILTER_UNSAFE_RAW, 'value' => ''],
		'filter'   => ['method' => 'textbox', 'value' => ''],
	], [
		'rfilter' => ['method' => 'textbox', 'value' => ''],
	]]]);
	$script = (new \ReflectionMethod($filter, 'create_javascript'))->invoke($filter);

	check(str_contains($script, "strURL = 'clog.php?page=1&filename='+encodeURIComponent($('#filename').val())+'&rfilter='+encodeURIComponent($('#rfilter').val())+'&filter='+encodeURIComponent($('#filter').val())+'&rfilter='+base64_encode($('#rfilter').val());"), 'Filter values were not encoded as URL components');

	print "PASS: table filter values are encoded as URL components\n";

	exit;
}

require $root . '/include/global_constants.php';
require $root . '/lib/html.php';
define('CACTI_VERSION', '1.3.0');
define('CACTI_SERVER_OS', 'unix');

$GLOBALS['probe']            = ['request' => [], 'status' => 200, 'messages' => [], 'headers' => [], 'purged' => [], 'cells' => [], 'paths' => []];
$GLOBALS['probe']['options'] = ['path_cactilog' => "/var/log/cacti/cacti & core's.log", 'path_stderrlog' => '/var/log/cacti/error.log', 'log_action' => (string) LOG_ACTION_PURGE, 'max_display_rows' => '500'];

function grv(string $name) : mixed {
	return $GLOBALS['probe']['request'][$name] ?? '';
}

function gnrv(string $name) : mixed {
	return grv($name);
}

function isrv(string $name) : bool {
	return isset($GLOBALS['probe']['request'][$name]);
}

function srv(string $name, mixed $value) : void {
	$GLOBALS['probe']['request'][$name] = $value;
}

function __(string $text, mixed ...$args) : string {
	return vsprintf($text, array_filter($args, 'is_scalar'));
}

function __esc(string $text, mixed ...$args) : string {
	return html_escape(__($text, ...$args));
}

function cacti_sizeof(mixed $data) : int {
	return is_countable($data) ? count($data) : 0;
}

function db_qstr(string $value) : string {
	return "'" . addslashes($value) . "'";
}

function db_fetch_row_prepared(string $sql, array $params) : array {
	return ['id' => $params[0], 'name' => 'Lab <b>& Office</b>', 'subnet_range' => '192.0.2.0/24<i>', 'last_started' => '2026-01-01 00:00:00', 'last_runtime' => '4.5'];
}

function raise_message(mixed $id, string $message = '', int $level = 0) : void {
	$GLOBALS['probe']['messages'][] = [$id, $message, $level];
}

function is_valid_pathname(string $path) : bool {
	return !str_contains($path, '*');
}

function form_selectable_cell(string $html, mixed ...$arguments) : void {
	$GLOBALS['probe']['cells'][] = $html;
}

function get_data_source_path(int $local_data_id, bool $expand_paths) : string {
	check($expand_paths, 'The debug check must resolve the expanded path');

	return $GLOBALS['probe']['paths'][$local_data_id];
}

function is_resource_writable(string $path) : bool {
	return is_writable($path);
}

function read_config_option(string $name) : string {
	return $GLOBALS['probe']['options'][$name] ?? '';
}

function header(string $value) : void {
	$GLOBALS['probe']['headers'][] = $value;
}

function http_response_code(int $code) : void {
	$GLOBALS['probe']['status'] = $code;
}

function csrf_check(bool $fatal) : bool {
	return ($_POST['__csrf_magic'] ?? '') === 'fixture-token';
}

function clog_admin() : bool {
	return true;
}

function clog_validate_filename(string &$file, string &$path, string &$name, bool $rotated = false) : bool {
	$path = '/var/log/cacti';

	return true;
}

function clog_purge_logfile(string $action) : void {
	$GLOBALS['probe']['purged'][] = $action;
}

function clog_get_logfiles() : array {
	return [];
}

function api_plugin_is_enabled(string $plugin) : bool {
	return false;
}

function get_current_page() : string {
	return 'clog.php';
}

function kill_session_var(string $name) : void {
}

function general_header() : void {
}

function form_start(string $action) : void {
}

function html_start_box(mixed ...$arguments) : void {
}

function html_end_box() : void {
}

\cacti_fixture_source_function($root . '/include/csrf.php', 'csrf_require_post', __NAMESPACE__);

foreach (['clog_view_logfile', 'create_clog_filter'] as $function) {
	\cacti_fixture_source_function($root . '/lib/clog_webapi.php', $function, __NAMESPACE__);
}

$GLOBALS['logfile_actions']       = [LOG_ACTION_PURGE => 'Purge', LOG_ACTION_ROTATE => 'Rotate'];
$GLOBALS['log_tail_lines']        = $GLOBALS['page_refresh_interval'] = [];
$encoded_log                      = 'cacti%20%26%20core%27s.log';

/*
 * A confirmed purge or rotate ends the response, so each request runs in its
 * own process and is checked as the process ends.
 */
$confirmations = [
	'purge-get'   => ['GET', 'purge_continue', '', 405, []],
	'purge-token' => ['POST', 'purge_continue', 'stale', 403, []],
	'purge-post'  => ['POST', 'purge_continue', 'fixture-token', 200, ['purge']],
	'rotate-get'  => ['GET', 'rotate_continue', '', 405, []],
	'rotate-post' => ['POST', 'rotate_continue', 'fixture-token', 200, ['rotate']],
];

if (isset($confirmations[$mode])) {
	[$method, $field, $token, $wanted_status, $wanted_change] = $confirmations[$mode];
	$_SERVER['REQUEST_METHOD']                                = $method;
	$_POST                                                    = $token === '' ? [] : ['__csrf_magic' => $token];
	$GLOBALS['probe']['request']                              = [$field => '1', 'filename' => 'cacti.log'];
	register_shutdown_function(static function () use ($mode, $wanted_status, $wanted_change, $encoded_log) : void {
		check($GLOBALS['probe']['status'] === $wanted_status, 'Unexpected response status');
		check($GLOBALS['probe']['purged'] === $wanted_change, 'Unexpected log change');
		check($GLOBALS['probe']['headers'] === match ($wanted_status) {
			405     => ['Allow: POST'],
			403     => [],
			default => ['Location: clog.php?filename=' . $encoded_log],
		}, 'Unexpected response headers');

		print "PASS: log confirmation $mode\n";
	});
	clog_view_logfile();

	throw new \RuntimeException('The confirmation did not end the response');
}

check($mode === '', 'Unknown mode');

// Discovery report: device and network values are text inside the HTML email.
$state = \cacti_fixture_source_block($root . '/poller_automation.php', __NAMESPACE__, '/^\t{3}(\$table_exist = \x27\x27;.*?<\/table><br><br>\x27;)/ms', [
	'network_id'  => 7,
	'ids'         => ['192.0.2.10' => ['old' => ''], '192.0.2.11' => ['old' => '192.0.2.11']],
	'new_devices' => [
		['ip' => '192.0.2.10', 'hostname' => 'edge<script>', 'sysName' => 'core "A" & <b>B</b>', 'snmp' => 1, 'up' => 1],
		['ip' => '192.0.2.11', 'hostname' => 'plain.example', 'sysName' => '', 'snmp' => 0, 'up' => 0],
	],
]);
check(str_contains($state['table_new'], '<tr><td>edge&lt;script&gt;</td><td>192.0.2.10</td><td>core &quot;A&quot; &amp; &lt;b&gt;B&lt;/b&gt;</td>'), 'New device values were not encoded');
check(str_contains($state['table_exist'], '<tr><td>plain.example</td><td>192.0.2.11</td><td><i><u>None</u></i></td>'), 'Known device row changed');
check(str_contains($state['output'], '<h1>Discovery of Lab &lt;b&gt;&amp; Office&lt;/b&gt;</h1>') && str_contains($state['output'], '<td>192.0.2.0/24&lt;i&gt;</td>'), 'Network values were not encoded');
check($state['subject'] === 'Discovery of Lab <b>& Office</b> (192.0.2.0/24<i>) - 2 devices discovered, 1 new!', 'The plain-text subject changed');

// New Graphs: a cached field name is a quoted literal in the pivot query and text in the header.
$field_names = [['field_name' => 'ifDescr'], ['field_name' => "if'Name"]];
$state       = \cacti_fixture_source_block($root . '/graphs_new.php', __NAMESPACE__, '/^\t{6}(if \(cacti_sizeof\(\$field_names\)\) \{\s*foreach \(\$field_names as \$column\).*?^\t{6}\})/ms', [
	'field_names' => $field_names, 'sql_query' => 'SELECT snmp_index', 'i' => 0,
]);
check($state['sql_query'] === "SELECT snmp_index, MAX(CASE WHEN field_name = 'ifDescr' THEN field_value ELSE NULL END) AS 'ifDescr'"
	. ", MAX(CASE WHEN field_name = 'if\\'Name' THEN field_value ELSE NULL END) AS 'if\\'Name'", 'Field names were not quoted in the pivot query');
$state = \cacti_fixture_source_block($root . '/graphs_new.php', __NAMESPACE__, '/^\t{6}(foreach \(\$xml_array\[\x27fields\x27\] as \$field_name => \$field_array\) \{\s*if \(\(\$field_array\[\x27direction\x27\] == \x27input\x27[^\n]*cacti_sizeof\(\$field_names\)\).*?^\t{6}\})/ms', [
	'field_names'    => $field_names, 'html_dq_header' => '',
	'xml_array'      => ['fields' => [
		'ifDescr' => ['direction' => 'input', 'name' => 'Description <b>& Alias</b>'],
		'ifSpeed' => ['direction' => 'output', 'name' => 'Speed'],
	]],
]);
check($state['html_dq_header'] === "<th class='tableSubHeaderColumn'>Description &lt;b&gt;&amp; Alias&lt;/b&gt;</th>", 'The field label was not encoded');

// Import previews: orphan text is encoded once where it is printed; differences arrive encoded.
$orphan     = 'Found Orphaned Graph Items, Type: LINE1, Text Format: <b>In & Out</b>, Value: ';
$difference = 'New Graph Template: Traffic &amp; Load';
$wanted     = 'Differences<br>' . $difference . '<br>Orphans<br>Found Orphaned Graph Items, Type: LINE1, Text Format: &lt;b&gt;In &amp; Out&lt;/b&gt;, Value: ';
$preview    = '/^\t{4}(\$diff_details = \x27\x27;.*?form_selectable_cell\(\$diff_details, \$id, \x27\x27, \x27white-space:pre-wrap\x27\);)/ms';

\cacti_fixture_source_block($root . '/templates_import.php', __NAMESPACE__, $preview, [
	'id' => 3, 'detail' => ['vals' => ['differences' => [$difference], 'orphans' => [$orphan]]],
]);
\cacti_fixture_source_block($root . '/package_import.php', __NAMESPACE__, $preview, [
	'id' => 3, 'detail' => ['vals' => ['package' => ['differences' => [$difference], 'orphans' => [$orphan]]]],
]);
check($GLOBALS['probe']['cells'] === [$wanted, $wanted], 'The import previews do not encode orphan text once');

// Settings: a log path keeps its extension rule and refuses control and markup characters.
$log_path = '/^\t{4}(\$continue = true;\s*if \(\$field_name == \x27path_cactilog\x27.*?\$errors\[36\]\s*= 36;\s*\})/ms';

foreach ([
	['path_cactilog', '/var/log/cacti/cacti.log', [], true],
	['path_cactilog', '/var/log/cacti/cacti.txt', [9 => 9], false],
	['path_stderrlog', '/var/log/cacti/<b>error</b>.log', [35 => 35], false],
	['path_boost_log', "/var/log/cacti/boost\n.log", [35 => 35], false],
	['path_rrdtool', '/usr/bin/rrd*', [36 => 36], true],
] as [$field_name, $value, $errors, $continue]) {
	$GLOBALS['probe']['request']  = [$field_name => $value];
	$GLOBALS['probe']['messages'] = [];
	$_SESSION                     = [];
	$state                        = \cacti_fixture_source_block($root . '/settings.php', __NAMESPACE__, $log_path, ['field_name' => $field_name, 'errors' => []]);
	check($state['errors'] === $errors && $state['continue'] === $continue, 'Unexpected result for ' . $field_name);
	check(isset($_SESSION['sess_error_fields'][$field_name]) === ($errors !== []), 'Unexpected field state for ' . $field_name);
	check($GLOBALS['probe']['messages'] === (isset($errors[35]) ? [['cacti_settings_' . $field_name, "Setting '$field_name': The log file path must not contain control characters, quotes or angle brackets.", MESSAGE_LEVEL_ERROR]] : []), 'Unexpected message for ' . $field_name);
}

// Data source debug: the contained path is checked and a missing file has no owner.
$present                   = tempnam(sys_get_temp_dir(), 'cacti-rrd-');
$GLOBALS['probe']['paths'] = [5 => $present, 6 => $present . '-missing'];
$owner                     = posix_getpwuid(fileowner($present))['name'] . ':' . posix_getgrgid(filegroup($present))['name'];
$debug                     = '/^\t{4}(\$real_path = get_data_source_path\(.*?\$info\[\x27owner\x27\] =  \$o \. \x27:\x27 \. \$g;\s*\})/ms';

try {
	foreach ([5 => [1, $owner], 6 => [0, '']] as $id => [$exists, $wanted_owner]) {
		$state = \cacti_fixture_source_block($root . '/lib/dsdebug.php', __NAMESPACE__, $debug, [
			'c' => ['datasource' => $id], 'dtd' => ['active' => 'on', 'data_source_path' => '<path_rra>/../outside.rrd'], 'info' => [],
		]);
		check($state['real_path'] === $GLOBALS['probe']['paths'][$id], 'The stored path was used instead of the contained path');
		check($state['info']['rrd_exists'] === $exists && $state['info']['owner'] === $wanted_owner, 'Unexpected file state for data source ' . $id);
	}
} finally {
	unlink($present);
}

// Log viewer: the confirmation posts its token and every link carries an encoded file name.
$_SERVER['REQUEST_METHOD'] = 'GET';

foreach (['purge' => 'purge_continue', 'rotate' => 'rotate_continue'] as $action => $field) {
	$GLOBALS['probe']['request'] = ['action' => $action, 'filename' => "cacti & core's.log"];
	ob_start();
	clog_view_logfile();
	$page = (string) ob_get_clean();
	check(str_contains($page, "postUrl({url: location.pathname+\"?$field=1&filename=$encoded_log\"}, {__csrf_magic: csrfMagicToken});"), 'The confirmation is not a POST with a token and an encoded file name');
	check(str_contains($page, 'cacti &amp; core&apos;s.log'), 'The confirmation text changed');
}
check($GLOBALS['probe']['purged'] === [] && $GLOBALS['probe']['headers'] === [], 'Showing a confirmation changed a log');

$log     = $GLOBALS['probe']['options']['path_cactilog'];
$buttons = create_clog_filter($log, true)['buttons'];
check($buttons['purge']['url'] === 'clog.php?action=purge&filename=%2Fvar%2Flog%2Fcacti%2F' . $encoded_log && !isset($buttons['rotate']), 'The purge link does not encode the file name');
$GLOBALS['probe']['options']['log_action'] = (string) LOG_ACTION_ROTATE;
$buttons                                   = create_clog_filter($log, true)['buttons'];
check($buttons['rotate']['url'] === 'clog.php?action=rotate&filename=%2Fvar%2Flog%2Fcacti%2F' . $encoded_log && !isset($buttons['purge']), 'The rotate link does not encode the file name');

print "PASS: output encoding, query quoting and path checks\n";
