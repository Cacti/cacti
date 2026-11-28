<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\CollectorCleanupGates;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

/*
 * Runs the collector, permission and RRD cleanup checks from their checked-in
 * source. Each mode prints one JSON document so a test can assert on what the
 * production code did. Modes that end in exit report from a shutdown handler.
 */
$root = dirname(__DIR__, 3);
$mode = $argv[1] ?? '';
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';

if ($mode === 'escape') {
	define('CACTI_SERVER_OS', 'win32');
}
require $root . '/include/global_constants.php';
require $root . '/include/vendor/autoload.php';
require $root . '/lib/functions.php';

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

function finish(array $result) : void {
	print json_encode($result, JSON_THROW_ON_ERROR) . "\n";
}

if ($mode === 'escape') {
	$slash  = chr(92);
	$result = [
		'words'         => \cacti_escapeshellarg('two words'),
		'quotes'        => \cacti_escapeshellarg('a"&b"c'),
		'trailing'      => \cacti_escapeshellarg('C:' . $slash . 'data' . $slash),
		'trailing-two'  => \cacti_escapeshellarg('share' . $slash . $slash),
		'inner'         => \cacti_escapeshellarg('C:' . $slash . 'data' . $slash . 'file'),
		'percent'       => \cacti_escapeshellarg('%5.2lf %%'),
		'empty'         => \cacti_escapeshellarg(''),
		'unquoted'      => \cacti_escapeshellarg('pla"in' . $slash, false),
		'cmd-quotes'    => \cacti_escapeshellarg_cmd('a"&b|c'),
		'cmd-trailing'  => \cacti_escapeshellarg_cmd('host' . $slash),
		'cmd-unquoted'  => \cacti_escapeshellarg_cmd('host' . $slash, false),
	];
	check($result['quotes'] === '"a&bc"', 'An embedded quote must not end the argument');
	check($result['trailing'] === '"C:' . $slash . 'data' . $slash . $slash . '"', 'A trailing backslash must not escape the closing quote');
	check($result['cmd-trailing'] === '"host' . $slash . $slash . '"', 'The cmd helper must keep the same closing quote');
	finish($result);

	exit;
}

function __(string $text, mixed ...$args) : string {
	return sprintf($text, ...$args);
}

function __esc(string $text, mixed ...$args) : string {
	return htmlspecialchars(sprintf($text, ...$args), ENT_QUOTES);
}

function __x(string $context, string $text, mixed ...$args) : string {
	return sprintf($text, ...$args);
}

function grv(string $name) : mixed {
	return $_REQUEST[$name] ?? '';
}

function gnrv(string $name) : mixed {
	return grv($name);
}

function gfrv(string $name, mixed ...$filter) : mixed {
	return grv($name);
}

function isrv(string $name) : bool {
	return isset($_REQUEST[$name]);
}

function ierv(string $name) : bool {
	return empty($_REQUEST[$name]);
}

function is_realm_allowed(int $realm) : bool {
	check($realm === 15, 'Only the Settings permission is expected');
	$GLOBALS['realm_checks']++;

	return $GLOBALS['settings_allowed'];
}

function read_config_option(string $name, bool $force = false) : mixed {
	return $GLOBALS['settings'][$name] ?? '';
}

function raise_message(mixed $id, mixed ...$details) : void {
	$GLOBALS['messages'][] = $id;
}

function is_error_message() : bool {
	return false;
}

function header(string $value) : void {
	$GLOBALS['redirect'] = $value;
}

function cacti_log(string $message, mixed ...$details) : void {
	$GLOBALS['logs'][] = $message;
}

function db_execute(string $sql) : void {
	$GLOBALS['queries'][] = preg_replace('/\s+/', ' ', $sql);
}

function db_execute_prepared(string $sql, array $params = []) : void {
	$GLOBALS['writes'][] = [strtok(trim($sql), " \n"), $params];
}

$GLOBALS['realm_checks'] = 0;
$GLOBALS['messages']     = $GLOBALS['logs'] = $GLOBALS['queries'] = $GLOBALS['writes'] = $GLOBALS['calls'] = [];
$GLOBALS['settings']     = [];

function recorded(string $name) : void {
	$GLOBALS['calls'][] = substr($name, strlen(__NAMESPACE__) + 1);
}

if ($mode === 'collector' || $mode === 'sync-denied') {
	define('POLLER_ID', 1);

	foreach (['Regex', 'NotBlank'] as $type) {
		class_alias('Symfony\Component\Validator\Constraints\\' . $type, __NAMESPACE__ . '\Assert\\' . $type);
	}

	class CactiValidator {
		public static function validateInput(mixed $value, string $field, array $constraints) : mixed {
			return $value;
		}
	}

	function test_database_connection() : void {
		recorded(__FUNCTION__);
	}

	function poller_check_duplicate_poller_id(mixed $id, mixed $host, string $column) : bool {
		return false;
	}

	function poller_host_duplicate(mixed $id, mixed $host) : bool {
		recorded(__FUNCTION__);

		return false;
	}

	function sql_save(array $save, string $table) : int {
		$GLOBALS['saved'] = $save;

		return (int) $save['id'];
	}

	function sanitize_unserialize_selected_items(mixed $items) : mixed {
		return $items;
	}

	function db_fetch_row_prepared(string $sql, array $params) : array {
		check($GLOBALS['mode'] !== 'sync-denied', 'A refused synchronization read a collector');

		return ['id' => (int) $params[0], 'name' => 'Remote', 'timezone' => 'UTC'];
	}

	function form_start(mixed ...$arguments) : void {
	}

	function html_start_box(mixed ...$arguments) : void {
	}

	function html_end_box(mixed ...$arguments) : void {
	}

	function display_tooltip(string $text) : string {
		return $text;
	}

	function inject_form_variables(array $fields, array $values) : array {
		return $fields;
	}

	function draw_edit_form(array $form) : void {
		$GLOBALS['drawn'] = array_keys($form['fields']);
	}

	function csrf_get_tokens() : string {
		return 'sample-token';
	}

	function form_save_buttons(array $buttons) : void {
	}

	foreach (['form_save', 'form_actions', 'poller_edit'] as $function) {
		\cacti_fixture_source_function($root . '/pollers.php', $function, __NAMESPACE__);
	}
}
$GLOBALS['mode'] = $mode;

if ($mode === 'sync-denied') {
	$action = (int) ($argv[2] ?? POLLER_RESYNC);
	register_shutdown_function(static function () use ($action) : void {
		check($GLOBALS['messages'] === ['poller_sync_permission'], 'A refused synchronization must be reported');
		check($GLOBALS['redirect'] === 'Location: pollers.php', 'A refused synchronization must return to the list');
		finish(['action' => $action, 'messages' => $GLOBALS['messages'], 'writes' => $GLOBALS['writes'], 'queries' => $GLOBALS['queries']]);
	});
	$GLOBALS['settings_allowed'] = false;
	$_REQUEST                    = ['selected_items' => [2], 'drp_action' => $action];
	form_actions();

	throw new \RuntimeException('The action handler did not end the response');
}

if ($mode === 'collector') {
	$result = [];

	// The list of bulk actions and the connection test are controller blocks.
	foreach ([true, false] as $allowed) {
		$GLOBALS['settings_allowed'] = $allowed;
		$GLOBALS['calls']            = [];
		$state                       = \cacti_fixture_source_block($root . '/pollers.php', __NAMESPACE__,
			'~^(if \(POLLER_ID == 1 && is_realm_allowed\(15\)\) \{.*?^\})~ms', ['actions' => [POLLER_DELETE => 'Delete']]);
		\cacti_fixture_source_block($root . '/pollers.php', __NAMESPACE__,
			'~case \x27ping\x27:\s*(?://[^\n]*\s*)?(if \(is_realm_allowed\(15\)\) \{.*?\})~s');
		$key                     = $allowed ? 'allowed' : 'denied';
		$result[$key]['actions'] = array_keys($state['actions']);
		$result[$key]['ping']    = $GLOBALS['calls'];
	}

	$request = ['save_component_poller' => '1', 'id' => '2', 'name' => 'Remote', 'hostname' => 'remote.example',
		'log_level'                        => '-1', 'timezone' => 'UTC', 'notes' => '', 'processes' => '1', 'threads' => '1',
		'sync_interval'                    => '7200', 'dbdefault' => 'cacti', 'dbhost' => 'db.example', 'dbuser' => 'cacti',
		'dbpass'                           => 'sample', 'dbport' => '3306', 'dbretries' => '2', 'dbssl' => 'on', 'dbsslkey' => '',
		'dbsslcert'                        => '', 'dbsslca' => '', 'dbsslcapath' => '', 'dbsslverifyservercert' => 'on'];

	foreach ([true, false] as $allowed) {
		$GLOBALS['settings_allowed'] = $allowed;
		$GLOBALS['calls']            = [];
		$_REQUEST                    = $request;
		form_save();
		$key                        = $allowed ? 'allowed' : 'denied';
		$result[$key]['saved']      = array_keys($GLOBALS['saved']);
		$result[$key]['save_calls'] = $GLOBALS['calls'];
		check($GLOBALS['redirect'] === 'Location: pollers.php?action=edit&id=2', 'Saving must return to the collector');
	}

	// The edit form starts from the page's own field list.
	$locals = ['logfile_verbosity' => [], 'poller_sync_intervals' => []];

	foreach (['default', 'username', 'password', 'port', 'retries', 'ssl', 'ssl_key', 'ssl_cert', 'ssl_ca', 'ssl_capath', 'ssl_verify_server_cert'] as $name) {
		$locals['database_' . $name] = '';
	}
	$fields = \cacti_fixture_source_block($root . '/pollers.php', __NAMESPACE__, '~^(\$fields_poller_edit = \[.*?^\];)~ms', $locals)['fields_poller_edit'];

	foreach (['remote' => [2, true], 'remote-denied' => [2, false], 'main' => [1, true], 'main-denied' => [1, false]] as $key => [$id, $allowed]) {
		$GLOBALS['settings_allowed']   = $allowed;
		$GLOBALS['fields_poller_edit'] = $fields;
		$_REQUEST                      = ['id' => $id];
		ob_start();
		poller_edit();
		ob_end_clean();
		$result['form'][$key] = $GLOBALS['drawn'];
	}
	check($GLOBALS['realm_checks'] === 10, 'Every gate must consult the Settings permission');
	finish($result);

	exit;
}

// The remaining modes run page controller blocks behind the real POST check.
require $root . '/lib/html.php';
require $root . '/lib/html_utility.php';
define('CACTI_WEB', true);
define('CACTI_PATH_LIBRARY', $root . '/lib');
define('CACTI_PATH_INCLUDE', $root . '/include');
define('CACTI_PATH_URL', '/');
require $root . '/include/csrf.php';
class_alias(\CactiCsrfGuard::class, __NAMESPACE__ . '\\CactiCsrfGuard');

foreach (['csrf_check', 'csrf_require_post'] as $function) {
	\cacti_fixture_source_function($root . '/include/csrf.php', $function, __NAMESPACE__);
}

function filter_input(int $type, string $name, int $filter) : mixed {
	$GLOBALS['token_checks']++;

	return $_POST[$name] ?? null;
}

function perm_remove() : void {
	recorded(__FUNCTION__);
}

function top_header() : void {
	recorded(__FUNCTION__);
}

function bottom_footer() : void {
	recorded(__FUNCTION__);
}

function remove_all_rrds() : void {
	recorded(__FUNCTION__);
}

function list_rrd() : void {
	recorded(__FUNCTION__);
}

$pages = [
	'user_admin.php'       => '~case \x27perm_remove\x27:\s*(csrf_require_post\(\);\s*perm_remove\(\);)~',
	'user_group_admin.php' => '~case \x27perm_remove\x27:\s*(csrf_require_post\(\);\s*perm_remove\(\);)~',
	'rrdcleaner.php'       => '~case \x27arcall\x27:\s*(?://[^\n]*\s*)?(csrf_require_post\(\);.*?bottom_footer\(\);)~s',
];
$GLOBALS['token_checks'] = 0;
$_SESSION[SESS_USER_ID]  = 5;

if ($mode === 'refused') {
	$page   = $argv[2] ?? '';
	$method = $argv[3] ?? 'GET';
	check(isset($pages[$page]), 'Unknown page');
	register_shutdown_function(static function () use ($page, $method) : void {
		check($GLOBALS['calls'] === [], 'A refused request reached the handler in ' . $page);
		finish(['page' => $page, 'method' => $method, 'calls' => $GLOBALS['calls'], 'token_checks' => $GLOBALS['token_checks']]);
	});
	$_SERVER['REQUEST_METHOD'] = $method;
	$_POST                     = $method === 'POST' ? ['__csrf_magic' => 'not-a-token'] : [];
	\cacti_fixture_source_block($root . '/' . $page, __NAMESPACE__, $pages[$page]);

	throw new \RuntimeException('The request was not refused');
}

if ($mode === 'post') {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = ['__csrf_magic' => \csrf_guard()->token()];
	$result                    = [];

	foreach ($pages as $page => $pattern) {
		$GLOBALS['calls'] = [];
		$before           = $GLOBALS['token_checks'];
		\cacti_fixture_source_block($root . '/' . $page, __NAMESPACE__, $pattern);
		check($GLOBALS['token_checks'] === $before + 1, 'The POST did not validate a token in ' . $page);
		$result['calls'][$page] = $GLOBALS['calls'];
	}

	// The cleaner's own buttons must send the request the check accepts.
	\cacti_fixture_source_function($root . '/rrdcleaner.php', 'create_rrdcleaner_filter', __NAMESPACE__);
	$GLOBALS['item_rows'] = [];
	$buttons              = create_rrdcleaner_filter()['buttons'];

	foreach (['remall', 'arcall'] as $button) {
		check(!isset($buttons[$button]['url']), 'A queue-all button still loads its action by GET');
		$result['buttons'][$button] = $buttons[$button]['callback'];
	}
	$result['rescan'] = $buttons['rescan']['url'];

	finish($result);

	exit;
}

throw new \RuntimeException('Unknown mode');
