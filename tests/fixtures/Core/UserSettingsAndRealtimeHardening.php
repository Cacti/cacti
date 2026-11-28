<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\UserSettingsAndRealtimeHardening;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$mode = $argv[1] ?? 'core';
$root = realpath(dirname(__DIR__, 3));

require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require_once $root . '/lib/user_setting_validation.php';

if (!defined('CACTI_PATH_LIBRARY')) {
	define('CACTI_PATH_LIBRARY', $root . '/lib');
}

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', $root);
}

if (!defined('CACTI_PATH_URL')) {
	define('CACTI_PATH_URL', '/cacti/');
}

if (!defined('SESS_USER_ID')) {
	define('SESS_USER_ID', 'sess_user_id');
}

if (!defined('OPTIONS_USER')) {
	define('OPTIONS_USER', 'sess_user_config_array');
}

if (!defined('SESS_USER_LANGUAGE')) {
	define('SESS_USER_LANGUAGE', 'sess_user_language');
}

if (!defined('SESS_ERROR_FIELDS')) {
	define('SESS_ERROR_FIELDS', 'sess_error_fields');
}

if (!defined('SESS_FIELD_VALUES')) {
	define('SESS_FIELD_VALUES', 'sess_field_values');
}

if (!defined('RRDTOOL_OUTPUT_GRAPH_DATA')) {
	define('RRDTOOL_OUTPUT_GRAPH_DATA', 2);
}

if (!defined('GT_LAST_DAY')) {
	define('GT_LAST_DAY', 7);
}

if (!defined('GTS_1_DAY')) {
	define('GTS_1_DAY', 86400);
}

if (!defined('WD_MONDAY')) {
	define('WD_MONDAY', 1);
}

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

function __(string $text, mixed ...$args) : string {
	return $args ? sprintf($text, ...$args) : $text;
}

function htmle(mixed $text) : string {
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function grv(string $name) : mixed {
	return $GLOBALS['fixture_request'][$name] ?? '';
}

function gnrv(string $name) : mixed {
	return $GLOBALS['fixture_request'][$name] ?? '';
}

function gfrv(string $name, int $filter = FILTER_VALIDATE_INT, array $options = []) : mixed {
	return $GLOBALS['fixture_request'][$name] ?? 0;
}

function isrv(string $name) : bool {
	return array_key_exists($name, $GLOBALS['fixture_request'] ?? []);
}

function ierv(string $name) : bool {
	return empty($GLOBALS['fixture_request'][$name]);
}

function srv(string $name, mixed $value) : void {
	$GLOBALS['fixture_request'][$name] = $value;
}

function set_default_action() : void {
}

function get_guest_account() : int {
	return $GLOBALS['fixture_guest_id'] ?? 3;
}

function is_view_allowed(string $view) : bool {
	return $GLOBALS['fixture_views'][$view] ?? true;
}

function is_graph_allowed(int $local_graph_id) : bool {
	return $GLOBALS['fixture_graph_allowed'] ?? true;
}

function is_tree_allowed(mixed $tree_id) : bool {
	return true;
}

function grow_right_pane_tree(mixed $tree_id, mixed $leaf_id, string $host_group_data) : void {
}

function read_config_option(string $name, bool $force = false) : mixed {
	return $GLOBALS['fixture_config'][$name] ?? '';
}

function get_new_user_default_language() : string {
	return 'en-US';
}

function get_installed_locales() : array {
	return ['en-US' => 'English'];
}

function read_user_setting(string $name, mixed $default = false) : mixed {
	return $GLOBALS['fixture_user_settings'][$name] ?? $default;
}

function set_user_setting(string $name, mixed $value, mixed $user = null) : void {
	$GLOBALS['fixture_saved_settings'][] = [$name, $value, $user];
}

function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['fixture_queries'][] = [$sql, $params];

	return true;
}

function kill_session_var(string $name) : void {
	unset($_SESSION[$name]);
}

function raise_message(mixed $id) : void {
	$GLOBALS['fixture_messages'][] = $id;
}

function api_plugin_hook(string $name) : void {
}

function api_plugin_hook_function(string $name, mixed $data = null) : mixed {
	return $data;
}

function header(string $header) : void {
	$GLOBALS['fixture_headers'][] = $header;
}

function http_response_code(?int $code = null) : int|bool {
	if ($code !== null) {
		$GLOBALS['fixture_http_code'] = $code;
	}

	return $GLOBALS['fixture_http_code'] ?? 200;
}

function session_id(?string $id = null) : string {
	return 'fixturesessionid123456';
}

function cacti_escapeshellcmd(string $cmd) : string {
	return escapeshellcmd($cmd);
}

function cacti_escapeshellarg(string $arg) : string {
	return escapeshellarg($arg);
}

function shell_exec(string $command) : ?string {
	$GLOBALS['fixture_shell_commands'][] = $command;

	return '';
}

function rrdtool_function_graph(mixed ...$args) : string {
	return 'PNGDATA';
}

function rrdtool_create_error_image(mixed ...$args) : string {
	return 'ERRDATA';
}

function file_exists(string $filename) : bool {
	return false;
}

function draw_tree_filter(bool $render = false) : void {
}

function top_graph_header() : void {
}

function bottom_footer() : void {
}

function sanitize_search_string(string $value) : string {
	return $value;
}

function sanitize_uri(string $value) : string {
	return $value;
}

function get_current_graph_start() : int {
	return 1000;
}

function get_current_graph_end() : int {
	return 2000;
}

function disable_2fa(mixed $user) : string {
	return json_encode(['status' => 'disabled']);
}

function enable_2fa(mixed $user) : string {
	return json_encode(['status' => 'enabled']);
}

function verify_2fa(mixed $user, string $code) : string {
	return json_encode(['status' => 'verified', 'code' => $code]);
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function cacti_user_is_guest_account(mixed $user_id, mixed $guest_account_id) : bool {
	return \cacti_user_is_guest_account($user_id, $guest_account_id);
}

function cacti_validate_user_setting_value(string $name, mixed $value, array $field_array) : string|false {
	return \cacti_validate_user_setting_value($name, $value, $field_array);
}

function cacti_sanitize_positive_int_setting(mixed $value, int $default, int $min = 1, int $max = 100000) : int {
	return \cacti_sanitize_positive_int_setting($value, $default, $min, $max);
}

function cacti_sanitize_realtime_interval(mixed $value, array $allowed_intervals = [], int $default = 10) : int {
	return \cacti_sanitize_realtime_interval($value, $allowed_intervals, $default);
}

function load_auth_profile_functions(string $root) : void {
	$file = $root . '/auth_profile.php';

	foreach ([
		'auth_profile_require_post',
		'auth_profile_deny_guest_write',
		'api_auth_logout_everywhere',
		'api_auth_clear_user_settings',
		'api_auth_clear_user_setting',
		'api_auth_update_user_setting',
		'form_save',
	] as $fn) {
		\cacti_fixture_source_function($file, $fn, __NAMESPACE__);
	}
}

$authDispatchPattern = "/(require_once\\(CACTI_PATH_LIBRARY \\. '\\/user_setting_validation\\.php'\\);.*?^\\})/ms";

if ($mode === 'auth-guest-exit') {
	load_auth_profile_functions($root);
	$_SESSION[SESS_USER_ID]      = 3;
	$GLOBALS['fixture_guest_id'] = 3;
	ob_start();
	register_shutdown_function(static function () : void {
		$out = ob_get_clean();
		check(($GLOBALS['fixture_http_code'] ?? 200) === 403, 'Expected 403 for guest write');
		check(str_contains((string) $out, 'Permission Denied'), 'Expected Permission Denied JSON');
	});
	auth_profile_deny_guest_write();
	exit;
}

if (in_array($mode, ['auth-disable-2fa', 'auth-enable-2fa', 'auth-verify-2fa'], true)) {
	load_auth_profile_functions($root);
	$_SERVER['REQUEST_METHOD']   = 'POST';
	$_SESSION[SESS_USER_ID]      = 1;
	$GLOBALS['fixture_guest_id'] = 3;
	$actionMap                   = [
		'auth-disable-2fa' => 'disable_2fa',
		'auth-enable-2fa'  => 'enable_2fa',
		'auth-verify-2fa'  => 'verify_2fa',
	];
	$GLOBALS['fixture_request'] = ['action' => $actionMap[$mode], 'code' => '123456'];
	ob_start();
	register_shutdown_function(static function () : void {
		ob_end_clean();
	});
	\cacti_fixture_source_block($root . '/auth_profile.php', __NAMESPACE__, $authDispatchPattern);
	exit;
}

if ($mode === 'realtime-denied') {
	$_SESSION[SESS_USER_ID]           = 1;
	$GLOBALS['fixture_guest_id']      = 3;
	$GLOBALS['fixture_graph_allowed'] = false;
	$GLOBALS['fixture_request']       = ['local_graph_id' => 5];
	$graph_data_array                 = ['ds_step' => 10, 'image_format' => 'png'];
	$realtime_refresh                 = [10 => '10 Seconds'];
	$size                             = 100;
	ob_start();
	register_shutdown_function(static function () : void {
		check(($GLOBALS['fixture_http_code'] ?? 200) === 403, 'Expected 403 for disallowed realtime graph');
	});
	\cacti_fixture_source_block(
		$root . '/graph_realtime.php',
		__NAMESPACE__,
		'/(\\/\\/ call poller.*?\\$return_array = \\[.*?\\];)/ms',
		['graph_data_array' => $graph_data_array, 'realtime_refresh' => $realtime_refresh, 'size' => $size]
	);
	exit;
}

if ($mode === 'realtime-view-denied') {
	$GLOBALS['fixture_graph_allowed'] = false;
	$GLOBALS['fixture_request']       = ['local_graph_id' => 0];
	register_shutdown_function(static function () : void {
		check(($GLOBALS['fixture_http_code'] ?? 200) === 403, 'Expected 403 for disallowed realtime view');
	});
	\cacti_fixture_source_block(
		$root . '/graph_realtime.php',
		__NAMESPACE__,
		"/case 'view':\\n\\t\\t(\\\$local_graph_id = \\(int\\) gfrv\\('local_graph_id'\\);.*?file_get_contents\\(\\\$graph_rrd\\)\\);\\n\\t\\t\\})/ms"
	);
	exit;
}

// 1. Complete coverage of lib/user_setting_validation.php
check(\cacti_user_is_guest_account(3, 3) === true, 'guest match');
check(\cacti_user_is_guest_account(1, 3) === false, 'non-guest');
check(\cacti_user_is_guest_account(0, 3) === false, 'zero user');
check(\cacti_validate_user_setting_value('x', '1', []) === false, 'missing method');
check(\cacti_validate_user_setting_value('x', ['bad'], ['method' => 'textbox']) === false, 'non-scalar');
check(\cacti_validate_user_setting_value('x', true, ['method' => 'checkbox']) === 'on', 'bool true');
check(\cacti_validate_user_setting_value('x', false, ['method' => 'checkbox']) === '', 'bool false');
check(\cacti_validate_user_setting_value('x', "a\nb", ['method' => 'textbox']) === false, 'newline rejected');
check(\cacti_validate_user_setting_value('x', 'on', ['method' => 'checkbox']) === 'on', 'checkbox on');
check(\cacti_validate_user_setting_value('x', 'invalid', ['method' => 'checkbox']) === false, 'checkbox invalid');
check(\cacti_validate_user_setting_value('x', 'a', ['method' => 'drop_array']) === false, 'drop_array missing array');
check(\cacti_validate_user_setting_value('x', 'a', ['method' => 'drop_array', 'array' => ['a' => 'A']]) === 'a', 'drop_array string');
check(\cacti_validate_user_setting_value('x', '10', ['method' => 'drop_array', 'array' => [10 => 'Ten']]) === '10', 'drop_array int');
check(\cacti_validate_user_setting_value('x', '010', ['method' => 'drop_array', 'array' => [10 => 'Ten']]) === '10', 'drop_array leading-zero int');
check(\cacti_validate_user_setting_value('x', '99', ['method' => 'drop_array', 'array' => [10 => 'Ten']]) === false, 'drop_array miss');
check(\cacti_validate_user_setting_value('x', '-1', ['method' => 'drop_sql']) === false, 'drop_sql neg');
check(\cacti_validate_user_setting_value('x', '5', ['method' => 'drop_sql']) === '5', 'drop_sql ok');
check(\cacti_validate_user_setting_value('x', '5', ['method' => 'drop_color']) === '5', 'drop_color ok');
check(\cacti_validate_user_setting_value('x', 'modern', ['method' => 'themes', 'array' => ['modern' => 'Modern']]) === 'modern', 'themes ok');
check(\cacti_validate_user_setting_value('x', 'en-US', ['method' => 'drop_language', 'array' => ['en-US' => 'English']]) === 'en-US', 'drop_language ok');
check(\cacti_validate_user_setting_value('day_shift_start', '08:00', ['method' => 'textbox', 'value_type' => 'time']) === '08:00', 'time ok');
check(\cacti_validate_user_setting_value('day_shift_start', '99:99', ['method' => 'textbox', 'value_type' => 'time']) === false, 'time bad');
check(\cacti_validate_user_setting_value('offset', '-42', ['method' => 'textbox', 'value_type' => 'int', 'min' => -50, 'max' => 50]) === '-42', 'int ok');
check(\cacti_validate_user_setting_value('offset', '51', ['method' => 'textbox', 'value_type' => 'int', 'min' => -50, 'max' => 50]) === false, 'int max');
check(\cacti_validate_user_setting_value('offset', 'bad', ['method' => 'textbox', 'value_type' => 'int']) === false, 'int bad');
check(\cacti_validate_user_setting_value('min_tree_width', '200', ['method' => 'textbox', 'value_type' => 'positive_int', 'min' => 1, 'max' => 10000]) === '200', 'positive_int ok');
check(\cacti_validate_user_setting_value('min_tree_width', '0', ['method' => 'textbox', 'value_type' => 'positive_int', 'min' => 0]) === false, 'positive_int floor');
check(\cacti_validate_user_setting_value('count', '0', ['method' => 'textbox', 'value_type' => 'non_negative_int']) === '0', 'non_negative_int ok');
check(\cacti_validate_user_setting_value('count', '-1', ['method' => 'textbox', 'value_type' => 'non_negative_int', 'min' => -5]) === false, 'non_negative_int floor');
check(\cacti_validate_user_setting_value('ratio', '3.14', ['method' => 'textbox', 'value_type' => 'double', 'min' => 0.0, 'max' => 10.0]) === '3.14', 'double ok');
check(\cacti_validate_user_setting_value('ratio', '10.5', ['method' => 'textbox', 'value_type' => 'float', 'min' => 0.0, 'max' => 10.0]) === false, 'float max');
check(\cacti_validate_user_setting_value('ratio', '1e309', ['method' => 'textbox', 'value_type' => 'double']) === false, 'double inf');
check(\cacti_validate_user_setting_value('ratio', 'bad', ['method' => 'textbox', 'value_type' => 'double']) === false, 'double bad');
check(\cacti_validate_user_setting_value('note', 'ok', ['method' => 'textbox', 'value_type' => 'string', 'max_length' => 5]) === 'ok', 'string ok');
check(\cacti_validate_user_setting_value('note', 'toolong', ['method' => 'textbox', 'value_type' => 'string', 'max_length' => 5]) === false, 'string long');
check(\cacti_validate_user_setting_value('unknown_vt', '1', ['method' => 'textbox', 'value_type' => 'bogus']) === false, 'unknown value_type');
check(\cacti_validate_user_setting_value('plugin_int', '200', ['method' => 'textbox', 'default' => '100']) === '200', 'fallback int ok');
check(\cacti_validate_user_setting_value('plugin_int', 'bad', ['method' => 'textbox', 'default' => '100']) === false, 'fallback int bad');
check(\cacti_validate_user_setting_value('plugin_dbl', '2.5', ['method' => 'textbox', 'default' => '1.5']) === '2.5', 'fallback double ok');
check(\cacti_validate_user_setting_value('plugin_str', 'ok', ['method' => 'textbox', 'max_length' => 5]) === 'ok', 'fallback string ok');
check(\cacti_validate_user_setting_value('title_font', 'DejaVuSans', ['method' => 'font', 'max_length' => 50]) === 'DejaVuSans', 'font ok');
check(\cacti_validate_user_setting_value('title_font', 'DejaVuSans', ['method' => 'font']) === 'DejaVuSans', 'font default max_length');
check(\cacti_validate_user_setting_value('title_font', 'bad;font', ['method' => 'font', 'max_length' => 50]) === false, 'font bad');
check(\cacti_validate_user_setting_value('x', 'v', ['method' => 'unsupported']) === false, 'unknown method');

// 1b. Cover $settings_user typed textbox declarations in include/global_settings.php
$settingsUserBlock = \cacti_fixture_source_block(
	$root . '/include/global_settings.php',
	__NAMESPACE__,
	'/(\$settings_user = \[.*?^\];)/ms',
	[
		'themes'           => ['modern' => 'Modern'],
		'graph_views'      => ['1' => 'Tree View'],
		'dateformats'      => ['0' => 'Default'],
		'datechar'         => ['0' => '-'],
		'graphs_per_page'  => ['10' => '10', '20' => '20'],
		'graph_timespans'  => [GT_LAST_DAY => 'Last Day'],
		'graph_timeshifts' => [GTS_1_DAY => '1 Day'],
		'graph_weekdays'   => [WD_MONDAY => 'Monday'],
	]
);
$typedTextboxes = 0;

foreach ($settingsUserBlock['settings_user'] as $sectionFields) {
	foreach ($sectionFields as $settingKey => $settingField) {
		if (($settingField['method'] ?? '') === 'textbox') {
			check(isset($settingField['value_type']), 'textbox missing value_type: ' . $settingKey);
			$typedTextboxes++;
		}
	}
}
check($typedTextboxes === 10, 'expected 10 typed textbox settings in $settings_user');
check(\cacti_sanitize_positive_int_setting('15', 10, 1, 100) === 15, 'pos int ok');
check(\cacti_sanitize_positive_int_setting('bad', 10, 1, 100) === 10, 'pos int fallback');
check(\cacti_sanitize_realtime_interval('10', [10 => '10s'], 5) === 10, 'interval ok');
check(\cacti_sanitize_realtime_interval('0', [10 => '10s'], 5) === 5, 'interval low');
check(\cacti_sanitize_realtime_interval('20', [10 => '10s'], 5) === 5, 'interval unlisted');

// 2. auth_profile.php functions + dispatch paths
\cacti_fixture_source_function($root . '/lib/functions.php', 'save_user_settings', __NAMESPACE__);
load_auth_profile_functions($root);
$GLOBALS['fixture_guest_id'] = 3;
$GLOBALS['settings_user']    = [
	'general' => [
		'preview_graphs_per_page' => [
			'method'  => 'drop_array',
			'array'   => [10 => '10', 20 => '20'],
			'default' => '20',
		],
	],
];

// Guest denied on all profile functions
$_SESSION[SESS_USER_ID] = 3;
api_auth_logout_everywhere();
check($GLOBALS['fixture_http_code'] === 403, 'guest logout 403');
api_auth_clear_user_settings();
check($GLOBALS['fixture_http_code'] === 403, 'guest clear_user_settings 403');
api_auth_clear_user_setting('preview_graphs_per_page');
check($GLOBALS['fixture_http_code'] === 403, 'guest clear_user_setting 403');
api_auth_update_user_setting('preview_graphs_per_page', '20');
check($GLOBALS['fixture_http_code'] === 403, 'guest update_user_setting 403');

// Authenticated user without graph_settings denied
$_SESSION[SESS_USER_ID]   = 1;
$GLOBALS['fixture_views'] = ['graph_settings' => false];
api_auth_update_user_setting('preview_graphs_per_page', '20');
check($GLOBALS['fixture_http_code'] === 403, 'no graph_settings 403');

// Authenticated user with graph_settings: invalid -> 400, valid -> persisted
$GLOBALS['fixture_views'] = ['graph_settings' => true];
api_auth_update_user_setting('preview_graphs_per_page', 'not-valid');
check($GLOBALS['fixture_http_code'] === 400, 'invalid setting 400');
$GLOBALS['fixture_http_code'] = 200;
api_auth_update_user_setting('preview_graphs_per_page', '20');
check($GLOBALS['fixture_http_code'] === 200, 'valid setting 200');

// Non-guest dispatch for save & update_data
$_SERVER['REQUEST_METHOD']  = 'POST';
$GLOBALS['fixture_request'] = ['action' => 'save', 'tab' => 'general'];
\cacti_fixture_source_block($root . '/auth_profile.php', __NAMESPACE__, $authDispatchPattern);
$GLOBALS['fixture_request'] = ['action' => 'update_data', 'tab' => 'general', 'name' => 'preview_graphs_per_page', 'value' => '10'];
\cacti_fixture_source_block($root . '/auth_profile.php', __NAMESPACE__, $authDispatchPattern);

// 3. graph_realtime.php header, poller/save-prefs, view allowed, and bottom prefs
\cacti_fixture_source_block(
	$root . '/graph_realtime.php',
	__NAMESPACE__,
	"/(require_once\\(CACTI_PATH_LIBRARY \\. '\\/user_setting_validation\\.php'\\);.*?gfrv\\('left'\\);)/ms"
);

$GLOBALS['fixture_graph_allowed'] = true;
$GLOBALS['fixture_config']        = [
	'realtime_cache_path' => '/tmp',
	'path_php_binary'     => '/usr/bin/php',
	'stats_poller'        => 'Time:1',
];
$GLOBALS['fixture_request'] = [
	'local_graph_id' => 5,
	'ds_step'        => 10,
	'graph_start'    => -60,
	'size'           => 100,
	'graph_nolegend' => 'true',
	'top'            => 0,
	'left'           => 0,
];
$_SESSION[SESS_USER_ID] = 1;
unset($_SESSION['sess_realtime_graph_start']);
ob_start();
\cacti_fixture_source_block(
	$root . '/graph_realtime.php',
	__NAMESPACE__,
	'/(\\/\\/ call poller.*?\\$return_array = \\[.*?\\];)/ms',
	['graph_data_array' => ['ds_step' => 10, 'image_format' => 'png'], 'realtime_refresh' => [10 => '10 Seconds'], 'size' => 100]
);
ob_end_clean();

// Guest skips set_user_setting in both realtime save blocks
$_SESSION[SESS_USER_ID] = 3;
ob_start();
\cacti_fixture_source_block(
	$root . '/graph_realtime.php',
	__NAMESPACE__,
	'/(\\/\\/ call poller.*?\\$return_array = \\[.*?\\];)/ms',
	['graph_data_array' => ['ds_step' => 10, 'image_format' => 'png'], 'realtime_refresh' => [10 => '10 Seconds'], 'size' => 100]
);
ob_end_clean();

// case 'view' allowed path
\cacti_fixture_source_block(
	$root . '/graph_realtime.php',
	__NAMESPACE__,
	"/case 'view':\\n\\t\\t(\\\$local_graph_id = \\(int\\) gfrv\\('local_graph_id'\\);.*?file_get_contents\\(\\\$graph_rrd\\)\\);\\n\\t\\t\\})/ms"
);

// Bottom realtime user preference save block (non-guest + guest)
$realtimeBottomPattern  = '/(if \\(!cacti_user_is_guest_account\\(\\$_SESSION\\[SESS_USER_ID\\] \\?\\? 0, get_guest_account\\(\\)\\)\\) \\{.*?\\n\\})/ms';
$_SESSION[SESS_USER_ID] = 1;
\cacti_fixture_source_block($root . '/graph_realtime.php', __NAMESPACE__, $realtimeBottomPattern, ['realtime_refresh' => [10 => '10 Seconds']]);
$_SESSION[SESS_USER_ID] = 3;
\cacti_fixture_source_block($root . '/graph_realtime.php', __NAMESPACE__, $realtimeBottomPattern, ['realtime_refresh' => [10 => '10 Seconds']]);

// 4. lib/html_tree.php modified lines (209-210, 1350-1358, 1946-1947, 2033)
$GLOBALS['fixture_user_settings'] = [
	'tree_history'             => 'on',
	'min_tree_width'           => '120',
	'max_tree_width'           => '350',
	'treeview_graphs_per_page' => '25',
	'page_refresh'             => '300',
];
ob_start();
\cacti_fixture_source_block(
	$root . '/lib/html_tree.php',
	__NAMESPACE__,
	"/(if \\(read_user_setting\\('tree_history'\\) != 'on'\\) \\{.*?max_tree_width', 300\\)\\); \\?>;)/ms"
);
ob_end_clean();

$treeRowsPattern            = "/(if \\(grv\\('graphs'\\) == '-1'\\) \\{.*?\\\$graph_rows = 10;\\n\\t\\})/ms";
$GLOBALS['fixture_request'] = ['graphs' => '-1'];
$res                        = \cacti_fixture_source_block($root . '/lib/html_tree.php', __NAMESPACE__, $treeRowsPattern);
check($res['graph_rows'] === 25, 'tree rows default validated');

$GLOBALS['fixture_request'] = ['graphs' => 'invalid'];
$res                        = \cacti_fixture_source_block($root . '/lib/html_tree.php', __NAMESPACE__, $treeRowsPattern);
check($res['graph_rows'] === 10, 'tree rows invalid fallback');

\cacti_fixture_source_function($root . '/lib/html_tree.php', 'html_tree_init', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/html_tree.php', 'html_tree_get_content', __NAMESPACE__);
$GLOBALS['fixture_request'] = ['tree_id' => 1, 'node' => 'tree_anchor-1', 'hgd' => ''];
$_SERVER['REQUEST_URI']     = '/cacti/graph_view.php?action=tree_content';
$GLOBALS['fixture_views']   = ['show_tree' => true];
ob_start();
html_tree_init();
html_tree_get_content();
ob_end_clean();

// 5. lib/functions.php save_user_settings()
$GLOBALS['settings_user'] = [
	'general' => [
		'grouped_opts' => [
			'method' => 'other',
			'items'  => [
				'sub_num' => ['method' => 'textbox', 'default' => '10'],
			],
		],
		'valid_setting' => [
			'method'  => 'textbox',
			'default' => '100',
		],
		'invalid_setting' => [
			'method'  => 'drop_array',
			'array'   => [10 => '10', 20 => '20'],
			'default' => '20',
		],
	],
];
$GLOBALS['fixture_request'] = [
	'sub_num'         => '15',
	'valid_setting'   => '120',
	'invalid_setting' => 'not-allowed',
];

// Guest skips saving
$GLOBALS['fixture_saved_settings'] = [];
save_user_settings(3);
check($GLOBALS['fixture_saved_settings'] === [], 'guest save_user_settings blocked');

// Authenticated user saves validated values & falls back to default for invalid
save_user_settings(1);
check(count($GLOBALS['fixture_saved_settings']) === 3, 'non-guest save_user_settings persisted 3 fields');

print json_encode(['passed' => $mode], JSON_THROW_ON_ERROR) . "\n";
