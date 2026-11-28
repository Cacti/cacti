<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\AuthorizedActions;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

if (($argv[1] ?? '') === 'oauth') {
	chdir($root);
	require $root . '/tests/bootstrap-unit.php';
	$_SERVER['PHP_SELF']        = $_SERVER['SCRIPT_NAME'] = '/oauth2.php';
	$config['cacti_db_version'] = CACTI_VERSION;
	check($user_auth_realm_filenames['oauth2.php'] === 15, 'OAuth settings realm was not registered');
	register_shutdown_function(static function () use ($root) : void {
		check(in_array($root . '/include/auth.php', get_included_files(), true), 'OAuth did not enter the authentication boundary');
		check(!in_array($root . '/lib/CactiOAuth.php', get_included_files(), true), 'Anonymous request reached the provider');
	});
	require $root . '/oauth2.php';

	throw new \RuntimeException('Anonymous request unexpectedly completed');
}

require $root . '/include/global_constants.php';
require $root . '/lib/html.php';
require $root . '/lib/html_utility.php';
require $root . '/include/vendor/autoload.php';
define('CACTI_WEB', true);
define('CACTI_PATH_LIBRARY', $root . '/lib');
define('CACTI_PATH_INCLUDE', $root . '/include');
define('CACTI_PATH_URL', '/');
require $root . '/include/csrf.php';
class_alias(\CactiCsrfGuard::class, __NAMESPACE__ . '\\CactiCsrfGuard');

foreach (['include/csrf.php' => ['csrf_check', 'csrf_require_post'], 'lib/auth.php' => ['cacti_authorize_resource'],
	'lib/functions.php'         => ['sanitize_uri', 'is_urlencoded', 'filter_value'], 'lib/reports.php' => ['reports_remove_history'],
	'tree.php'                  => ['tree_require_edit_permission', 'tree_require_item_edit_permission', 'tree_dnd', 'set_host_sort_type', 'set_branch_sort_type'],
	'auth_profile.php'          => ['settings_javascript']] as $file => $functions) {
	foreach ($functions as $function) {
		\cacti_fixture_source_function($root . '/' . $file, $function, __NAMESPACE__);
	}
}

// A controller block runs with the source file's original line numbers and local inputs.
function run_controller(string $file, string $pattern, array $locals = []) : array {
	$file   = dirname(__DIR__, 3) . '/' . $file;
	$source = file_get_contents($file);
	check(preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) === 1, 'Missing controller block in ' . $file);
	[$block, $offset] = $match[1];
	$temporary        = tempnam(sys_get_temp_dir(), 'cacti-action-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", substr_count(substr($source, 0, $offset), "\n")) . $block);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = $file;
	extract($locals, EXTR_SKIP);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}

	return get_defined_vars();
}

function filter_input(int $type, string $name, int $filter) : mixed {
	$GLOBALS['token_checks']++;

	return $_POST[$name] ?? null;
}

function grv(string $name) : mixed {
	return $_REQUEST[$name] ?? '';
}

function gfrv(string $name) : int {
	return (int) grv($name);
}

function gnrv(string $name) : mixed {
	return grv($name);
}

function isrv(string $name) : bool {
	return isset($_REQUEST[$name]);
}

function srv(string $name, mixed $value) : void {
	$_REQUEST[$name] = $value;
}

function __(string $text, mixed ...$args) : string {
	return sprintf($text, ...$args);
}

function __esc(string $text, mixed ...$args) : string {
	return htmlspecialchars(sprintf($text, ...$args), ENT_QUOTES);
}

function read_config_option(string $option) : string {
	return $option === 'font_method' ? '0' : '';
}

function get_selected_theme() : string {
	return 'modern';
}

function raise_message(mixed $id, mixed ...$details) : void {
	$GLOBALS['messages'][] = $id;
}

function header(string $value) : void {
	$GLOBALS['redirect'] = $value;
}

function is_realm_allowed(int $realm, int $user) : bool {
	return $realm === 1 && $user === 9;
}

function db_fetch_cell_prepared(string $sql, array $params) : int|false {
	if (str_contains($sql, 'FROM graph_tree_items')) {
		return $params === [42] ? 10 : false;
	}
	$GLOBALS['trees_checked'][] = (int) $params[0];

	return [10 => 5, 11 => 5, 12 => 7][(int) $params[0]] ?? false;
}

function db_fetch_row_prepared(string $sql, array $params) : array|false {
	if (str_contains($sql, 'FROM reports_log')) {
		return $params === [20] ? ['source' => 'reports', 'source_id' => 2] : false;
	}

	return $params === [2] ? ['user_id' => 5, 'name' => 'weekly'] : false;
}

function db_execute_prepared(string $sql, array $params) : void {
	$GLOBALS['writes'][] = $params;
}

function db_execute(string $sql) : void {
	$GLOBALS['queries'][] = $sql;
}

function cacti_sizeof(mixed $data) : int {
	return is_countable($data) ? count($data) : 0;
}

function get_username(int $user) : string {
	return 'fixture';
}

function is_reports_admin() : bool {
	return false;
}

function sanitize_unserialize_selected_items(mixed $values) : mixed {
	return $values;
}

function sanitize_search_string(string $value) : string {
	return $value;
}

function input_validate_input_number(mixed $number, string $field) : void {
	check(is_numeric($number), 'Unexpected identifier');
}

function set_config_option(string $name, mixed $value) : void {
}

function form_selectable_cell(string $html, int $id) : void {
	$GLOBALS['cell'] = $html;
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST                     = ['__csrf_magic' => \csrf_guard()->token()];
$_SESSION[SESS_USER_ID]    = 5;
$GLOBALS['token_checks']   = 0;
$GLOBALS['writes']         = $GLOBALS['messages'] = [];

if (($argv[1] ?? '') === 'profile') {
	settings_javascript();
	exit;
}

if (($argv[1] ?? '') === 'denied' || ($argv[1] ?? '') === 'reorder') {
	$denied = $argv[1] === 'denied';
	register_shutdown_function(static function () use ($denied) : void {
		check(($GLOBALS['redirect'] ?? null) === 'Location: tree.php', 'Missing tree redirect');
		check($GLOBALS['writes'] === ($denied ? [] : [[1, '10'], [2, '11']]), 'Unexpected tree ordering');
		check($GLOBALS['messages'] === ($denied ? ['tree_idor'] : []), 'Unexpected tree edit status');
	});
	$_REQUEST = ['tree_ids' => $denied ? ['line10', 'line12'] : ['line10', 'line11']];
	tree_dnd();

	throw new \RuntimeException('Reorder did not end the response');
}

foreach (['auth_profile.php' => 'logout_everywhere', 'automation_templates.php' => 'exitonchange', 'graphs.php' => 'ajax_dnd',
	'graph_templates.php'       => 'input_remove', 'data_debug.php' => 'purge', 'utilities.php' => 'purge_snmpagent_events'] as $page => $action) {
	$_REQUEST = ['action' => $action];
	$before   = $GLOBALS['token_checks'];
	$guard    = "/^(if \\(in_array\\(grv\\('action'\\), \\[[^]]*\\], true\\)\\) \\{\\s*csrf_require_post\\(\\);\\s*\\})/m";
	run_controller($page, $guard);
	check($GLOBALS['token_checks'] > $before, 'POST did not validate a token in ' . $page);
	$_REQUEST = ['action' => 'view'];
	$before   = $GLOBALS['token_checks'];
	run_controller($page, $guard);
	check($GLOBALS['token_checks'] === $before, 'Viewing a page required a mutation token');
}
$_REQUEST = ['action' => 'purge'];
run_controller('data_debug.php', "/^(if \\(grv\\('action'\\) === 'purge'\\) \\{[^}]+\\})/m");
run_controller('utilities.php', "/case 'purge_snmpagent_events':\\s*(db_execute\\(.*?srv\\('clear', true\\);)/s");
check($GLOBALS['queries'] === ['TRUNCATE TABLE data_debug', 'TRUNCATE table snmpagent_notifications_log'] && $_REQUEST['clear'] === true, 'Purge did not reset its data and filters');

run_controller('automation_templates.php', '/^(\\s*form_selectable_cell\\(filter_value\\(\\$exit_text, .*?;)/m',
	['exit_on_url' => 'automation_templates.php?action=exitonchange&id=1', 'id' => 1, 'exit_text' => 'Toggle']);
check(str_contains($GLOBALS['cell'], 'data-url="automation_templates.php?action=exitonchange&amp;id=1"') && str_contains($GLOBALS['cell'], '>Toggle</a>'), 'Toggle link lost the action or label');
check(filter_value('Edit', '', 'automation_templates.php?action=edit&id=1') === '<a class="linkEditMain" href="automation_templates.php?action=edit&amp;id=1" title="">Edit</a>', 'A link that only views lost its address');

foreach (['edit', 'tree_up', 'tree_down', 'lock', 'unlock'] as $action) {
	$_REQUEST                 = ['action' => $action, 'id' => 10];
	$GLOBALS['trees_checked'] = [];
	run_controller('tree.php', '/^(\\$action\\s*= grv\\(\\x27action\\x27\\);.*?^\\})/ms');
	check($GLOBALS['trees_checked'] === [10], 'Tree permission missing for ' . $action);
}

foreach (['set_host_sort_type' => 'hsgt', 'set_branch_sort_type' => 'manual'] as $handler => $type) {
	$_REQUEST = ['nodeid' => 'tbranch:42', 'type' => $type];
	$call     = __NAMESPACE__ . '\\' . $handler;
	$call();
}
check(count($GLOBALS['writes']) === 2, 'Expected both branch sorting updates');
$_REQUEST = ['id' => 10];
run_controller('tree.php', "/(if \\(gfrv\\('id'\\) > 0\\) \\{\\s*tree_require_edit_permission.*?\\n\\s*\\})/s");

foreach ([[10, 12], [12]] as $ids) {
	$_REQUEST = ['selected_items' => $ids];
	$state    = run_controller('tree.php', "/isrv\\('selected_items'\\)\\) \\{\\s*(csrf_require_post\\(\\);.*?)(?=\\s*if \\(\\\x24selected_items != false\\))/s");
	check($state['selected_items'] === ($ids === [12] ? false : [10]), 'Unexpected bulk selection');
}
check(!cacti_authorize_resource(0, 10, 'graph_tree') && cacti_authorize_resource(9, 12, 'graph_tree'), 'Tree owner and administrator permissions did not match');
$GLOBALS['writes'] = [];
reports_remove_history(20);
reports_remove_history(99);
$_SESSION[SESS_USER_ID] = 7;
reports_remove_history(20);
check($GLOBALS['writes'] === [[20]] && $GLOBALS['messages'] === ['remove_message', 'remove_error', 'remove_error'], 'History removal failed its owner check');
check(sanitize_uri('/') === '/' && sanitize_uri('///') === '/', 'Local directory paths were not normalized');

print "PASS: authorized actions validate POST, enforce ownership and update the expected rows\n";
