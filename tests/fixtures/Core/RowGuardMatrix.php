<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiRowGuardFixture;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * Coverage probe for the db_fetch_row_prepared() result guards and the dead
 * comparison clean-ups made for the PHPStan 2.3.6 findings.
 *
 * Each probe loads the exact checked-in statement (or function) into a closure,
 * keeps the original line numbers so Xdebug results map back to the source, and
 * runs it with a missing row (false) and with a populated row. Web entry points
 * and their auth/dispatch side effects are never executed. Statements that end
 * in exit() run in their own mode so the process can assert and finish cleanly.
 *
 *   php RowGuardMatrix.php                      all non-exiting probes
 *   php RowGuardMatrix.php auth-changepassword  exit() path in auth_changepassword.php
 *   php RowGuardMatrix.php add-device           exit(1) path in cli/add_device.php
 *   php RowGuardMatrix.php reports-edit         exit path in lib/html_reports.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';

$GLOBALS['passed']  = 0;
$GLOBALS['events']  = [];
$GLOBALS['request'] = [];

// bcmath is optional in the test image; these cover only the integer sizes the probe uses.
if (!function_exists('bcadd')) {
	function bcadd(string $a, string $b) : string {
		return (string) ((int) $a + (int) $b);
	}

	function bcmul(string $a, string $b) : string {
		return (string) ((int) $a * (int) $b);
	}

	function bcpow(string $a, string $b) : string {
		return (string) ((int) $a ** (int) $b);
	}
}

function __(string $text, mixed ...$args) : string {
	return $args === [] ? $text : vsprintf($text, $args);
}

function __esc(string $text, mixed ...$args) : string {
	return __($text, ...$args);
}

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function cacti_log(string $message, bool $output = false, string $environment = '', int $level = 0) : void {
	$GLOBALS['events'][] = ['log', $message];
}

function raise_message(mixed ...$arguments) : void {
	$GLOBALS['events'][] = ['message', $arguments[0]];
}

function get_graph_title(mixed $id) : string {
	return 'Title ' . $id;
}

function grv(string $name) : mixed {
	return $GLOBALS['request'][$name] ?? '';
}

function form_selectable_cell(string $contents, mixed $id) : void {
	$GLOBALS['events'][] = ['cell', $contents];
}

function form_hidden_box(string $name, mixed $value, mixed $default) : void {
	$GLOBALS['events'][] = ['hidden', $name, $value];
}

function header(string $header) : void {
	$GLOBALS['events'][] = ['header', $header];
}

function validate_redirect_url(string $url, string $default) : string {
	return $default;
}

function get_reports_page() : string {
	return 'reports.php';
}

function get_client_addr() : string {
	return '192.0.2.1';
}

function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['events'][] = ['db', $params];

	return true;
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return $GLOBALS['failed_attempts'] ?? 0;
}

function db_fetch_row_prepared(string $sql, array $params = []) : mixed {
	return $GLOBALS['fetched_row'];
}

function reports_load_format_file(string $file, mixed &$output, bool &$tag = false, mixed &$theme = false) : bool {
	return false;
}

function mailer(mixed ...$arguments) : void {
	$GLOBALS['events'][] = ['mail', $arguments[1]];
}

function events() : array {
	$events            = $GLOBALS['events'];
	$GLOBALS['events'] = [];

	return $events;
}

function check(mixed $actual, mixed $expected, string $label) : void {
	if ($actual !== $expected) {
		throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
	}
	$GLOBALS['passed']++;
}

/**
 * Run the checked-in statement that starts at the line whose trimmed text is
 * $first. With $last, the statement ends at the next line of the same
 * indentation whose trimmed text is $last (the closing brace of a block).
 * Returns the closure result: the variables after the statement, with
 * __fell set when control reached the end (no continue), or the statement's
 * own return value.
 */
function slice(string $file, string $first, ?string $last, array $variables, int $occurrence = 0) : mixed {
	global $root;

	$path  = $root . '/' . $file;
	$lines = file($path, FILE_IGNORE_NEW_LINES);
	$found = [];

	foreach ($lines as $index => $text) {
		if (trim($text) === $first) {
			$found[] = $index;
		}
	}

	if ($occurrence === 0 ? count($found) !== 1 : !isset($found[$occurrence - 1])) {
		throw new RuntimeException('Cannot uniquely locate "' . $first . '" in ' . $file . ' (' . count($found) . ' matches)');
	}
	$start = $found[max($occurrence, 1) - 1];
	$end   = $start;

	if ($last !== null) {
		$indent = strlen($lines[$start]) - strlen(ltrim($lines[$start]));
		$end    = null;

		for ($next = $start + 1; $next < count($lines); $next++) {
			if (trim($lines[$next]) === $last && strlen($lines[$next]) - strlen(ltrim($lines[$next])) === $indent) {
				$end = $next;

				break;
			}
		}

		if ($end === null) {
			throw new RuntimeException('Cannot find the end "' . $last . '" of "' . $first . '" in ' . $file);
		}
	}
	$code      = implode("\n", array_slice($lines, $start, $end - $start + 1));
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-row-guard-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . '; return static function (array $__v) { extract($__v, EXTR_SKIP); foreach ([0] as $__once) {' . str_repeat("\n", $start) . $code . ' $__fell = true; } return get_defined_vars(); };');
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($path);

	try {
		$closure = require $temporary;
	} finally {
		unlink($temporary);
	}

	return $closure($variables);
}

/**
 * Arm a shutdown check for a probe that must end in exit(); exit(0) from the
 * shutdown function reports success to the runner for exit(1) paths.
 */
function expect_exit(callable $verify) : void {
	$GLOBALS['exit_probe'] = 'armed';
	register_shutdown_function(static function () use ($verify) : void {
		if (($GLOBALS['exit_probe'] ?? '') !== 'armed') {
			return;
		}
		$verify();
		print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";

		exit(0);
	});
}

$mode = $argv[1] ?? 'main';

if ($mode === 'auth-changepassword') {
	$guard = 'if (!is_array($user) || !cacti_sizeof($user) || $user[\'realm\'] != 0) {';
	expect_exit(static function () : void {
		check(events(), [['message', 44], ['header', 'Location: index.php']], 'missing user is redirected');
	});
	slice('auth_changepassword.php', $guard, '}', ['user' => false]);
	$GLOBALS['exit_probe'] = 'fell-through';

	throw new RuntimeException('auth_changepassword.php did not exit for a missing user');
}

if ($mode === 'add-device') {
	expect_exit(static function () : void {
		check(events(), [], 'add_device prints instead of logging');
		check(str_contains((string) ob_get_clean(), 'Unable to read the existing device (7)'), true, 'add_device reports the missing device');
	});
	ob_start();
	slice('cli/add_device.php', 'if (!is_array($phost) || !cacti_sizeof($phost)) {', '}', ['phost' => false, 'id' => 7]);
	$GLOBALS['exit_probe'] = 'fell-through';

	throw new RuntimeException('cli/add_device.php did not exit for a missing device');
}

if ($mode === 'reports-edit') {
	expect_exit(static function () : void {
		check(events(), [['header', 'Location: reports.php']], 'missing report redirects to the report list');
	});
	slice('lib/html_reports.php', 'if (!is_array($report)) {', '}', ['report' => false]);
	$GLOBALS['exit_probe'] = 'fell-through';

	throw new RuntimeException('lib/html_reports.php did not exit for a missing report');
}

// ---- guards that skip the iteration or return when the row is missing ----

foreach ([
	['color.php', 'if (!is_array($color)) {', 'color'],
	['lib/plugins.php', 'if (!is_array($realm_info)) {', 'realm_info'],
] as [$file, $guard, $name]) {
	check(isset(slice($file, $guard, '}', [$name => false])['__fell']), false, $file . ' skips a missing row');
	check(isset(slice($file, $guard, '}', [$name => ['id' => 1]])['__fell']), true, $file . ' keeps a populated row');
}

$result = slice('lib/api_tree.php', 'if (!is_array($pdata) || $pdata === []) {', '}', ['pdata' => false]);
check($result, null, 'api_tree stops sorting without a leaf row');
check(events(), [['log', 'Error Sorting Tree']], 'api_tree logs the missing leaf row');
$result = slice('lib/api_tree.php', 'if (!is_array($pdata) || $pdata === []) {', '}', ['pdata' => []]);
check($result, null, 'api_tree stops sorting without a populated leaf row');
check(events(), [['log', 'Error Sorting Tree']], 'api_tree logs the empty leaf row');
check(isset(slice('lib/api_tree.php', 'if (!is_array($pdata) || $pdata === []) {', '}', ['pdata' => ['parent' => 3, 'graph_tree_id' => 4]])['__fell']), true, 'api_tree keeps a populated leaf row');
check(events(), [], 'api_tree logs nothing for a populated leaf row');

check(slice('auth_profile.php', 'if (!is_array($current_user) || !cacti_sizeof($current_user)) {', '}', ['current_user' => false]), false, 'profile page refuses a missing user');
check(slice('auth_profile.php', 'if (!is_array($current_user) || !cacti_sizeof($current_user)) {', '}', ['current_user' => []]), false, 'profile page refuses an empty user');
check(isset(slice('auth_profile.php', 'if (!is_array($current_user) || !cacti_sizeof($current_user)) {', '}', ['current_user' => ['id' => 1]])['__fell']), true, 'profile page keeps a user');

$result = slice('auth_changepassword.php', 'if (!is_array($user) || !cacti_sizeof($user) || $user[\'realm\'] != 0) {', '}', ['user' => ['realm' => 0, 'id' => 1]]);
check(isset($result['__fell']) && events() === [], true, 'password change continues for a local user');

$result = slice('cli/add_device.php', 'if (!is_array($phost) || !cacti_sizeof($phost)) {', '}', ['phost' => ['snmp_version' => 2], 'id' => 7]);
check(isset($result['__fell']), true, 'add_device continues for an existing device');

// ---- guards that choose a branch ----

foreach ([[false, false], [['title_format' => ''], 'Title 5'], [['title_format' => 'Custom'], 'Custom']] as [$aginfo, $title]) {
	$result = slice('aggregate_graphs.php', 'if (is_array($aginfo) && $aginfo[\'title_format\'] == \'\') {', '}', ['aginfo' => $aginfo, 'graphs' => ['local_graph_id' => 5]]);
	check($aginfo === false ? $result['aginfo'] : $result['aginfo']['title_format'], $title, 'aggregate title format');
}

foreach ([[false, 'Colors [edit: ]'], [['hex' => 'FF0000'], 'Colors [edit: FF0000]']] as [$color, $expected]) {
	$result = slice('color.php', '$header_label = __esc(\'Colors [edit: %s]\', is_array($color) ? $color[\'hex\'] : \'\');', null, ['color' => $color]);
	check($result['header_label'], $expected, 'color edit header');
}

foreach ([
	[false, false, false],
	[['data_sources' => 2, 'templates' => 0], true, true],
	[['data_sources' => 0, 'templates' => 3], false, true],
	[['data_sources' => 0, 'templates' => 0], false, false],
	[[], false, false],
] as [$counts, $disabled, $alt]) {
	$result = slice('data_input.php', 'if (!is_array($counts) || !cacti_sizeof($counts)) {', '}', ['counts' => $counts, 'output_disabled' => false, 'save_alt_message' => false]);
	check([$result['output_disabled'], $result['save_alt_message']], [$disabled, $alt], 'data input counts');
}

foreach ([[null, '0', '0', '0'], [false, '0', '0', '0'], [['id' => 4, 'graph_type_id' => 5, 'task_item_id' => 6], 4, 5, 6]] as [$item, $id, $type, $task]) {
	$variables = $item === null ? [] : ['template_item' => $item];
	slice('graph_templates.php', '$has_item = isset($template_item) && is_array($template_item);', 'form_hidden_box(\'_task_item_id\', ($has_item ? $template_item[\'task_item_id\'] : \'0\'), \'\');', $variables);
	$hidden = [];

	foreach (events() as $event) {
		$hidden[$event[1]] = $event[2];
	}
	check([$hidden['graph_template_item_id'], $hidden['_graph_type_id'], $hidden['_task_item_id']], [$id, $type, $task], 'graph template hidden item fields');
}

foreach ([['', 'NULL'], [0.5, 0.5]] as [$value, $expected]) {
	$result = slice('lib/dsstats.php', 'if ($currentval == \'\') {', '}', ['currentval' => $value]);
	check($result['currentval'], $expected, 'dsstats empty value becomes NULL');
}

$result = slice('lib/poller.php', 'for ($i = 1; $i <= $vallen; $i++) {', '}', ['value' => 'ffffffffff', 'vallen' => 10, 'dec' => 0]);
check((string) $result['dec'], '1099511627775', 'wide hexadecimal value converts exactly');

// ---- sliced multi-statement guards ----

foreach ([
	[false, 'enabled', 0, 'Access Denied!  Login Failed.', 'Login failed to find user'],
	[['id' => 9, 'enabled' => 'on', 'locked' => ''], 'on', 1, 'Access Denied!  Login Failed.', 'Login failed for user'],
	[['id' => 9, 'enabled' => 'on', 'locked' => ''], 'on', 3, 'Your account has been locked.  Please contact your Administrator.', 'Account is locked out'],
] as [$user, $enabled, $failed, $message, $log]) {
	$GLOBALS['failed_attempts'] = $failed;
	$result                     = slice('lib/auth.php', 'if (is_array($user) && cacti_sizeof($user)) {', '}', ['user' => $user, 'username' => 'fixture', 'realm' => 0, 'max' => 3, 'error' => false, 'error_msg' => '']);
	check([$result['error'], $result['error_msg']], [true, $message], 'failed login message');
	check(str_contains(implode("\n", array_map(static fn (array $event) : string => $event[0] === 'log' ? $event[1] : '', events())), $log), true, 'failed login log: ' . $log);
}

check(isset(slice('lib/html_reports.php', 'if (!is_array($report) || !cacti_sizeof($report)) {', '}', ['report' => false])['__fell']), true, 'reports_send ignores a missing report');

check(isset(slice('lib/reports.php', 'if (is_array($report) && cacti_sizeof($report)) {', '}', ['report' => false], 1)['__fell']), true, 'reports_log_and_notify ignores a missing queue row');
check(events(), [], 'reports_log_and_notify does nothing for a missing queue row');

$variables = [
	'report'      => ['name' => 'Weekly'], 'id' => 5, 'data' => ['id' => 5], 'from' => ['email' => 'cacti@example.net'],
	'subject'     => 'Subject', 'oput_html' => '<p>body</p>', 'oput_text' => 'body', 'attachments' => [], 'headers' => false,
];
slice('lib/reports.php', 'if (is_array($list) && cacti_sizeof($list)) {', '}', $variables + ['list' => false]);
check(str_contains(events()[0][1], "Unable to locate notification list '5'"), true, 'missing notification list is logged');
slice('lib/reports.php', 'if (is_array($list) && cacti_sizeof($list)) {', '}', $variables + ['list' => ['format_file' => '', 'emails' => 'a@example.net', 'bcc_emails' => '']]);
check(events(), [['mail', 'a@example.net']], 'populated notification list is mailed');

$result = slice('lib/reports.php', 'if (is_array($report) && cacti_sizeof($report)) {', '}', ['report' => false, 'id' => 5], 2);
check($result, false, 'reports_run refuses a missing queue row');
$result = slice('lib/reports.php', 'if (is_array($report) && cacti_sizeof($report)) {', '}', ['report' => ['name' => 'Weekly'], 'id' => 5], 2);
check([isset($result['__fell']), events()[0][0]], [true, 'db'], 'reports_run marks a queued row as running');

// ---- whole functions ----

cacti_fixture_source_function($root . '/data_source_profiles.php', 'get_span', __NAMESPACE__);
check(get_span(2 * 31536000 + 3 * 2592000 + 2 * 604800 + 3 * 86400 + 7200), '2 Years, 3 Months, 2 Weeks, 3 Days, 2 Hours', 'span with every unit');
check(get_span(31536000 + 1), '1 Year', 'span reports one year');
check(get_span(100), '', 'span below an hour is empty');

cacti_fixture_source_function($root . '/lib/template.php', 'verify_data_input', __NAMESPACE__);

foreach ([[false, ['status' => false]], [['input_string' => 'a'], ['input_string' => 'a', 'status' => true]], [['input_string' => 'b'], ['input_string' => 'b', 'status' => false]]] as [$row, $expected]) {
	$GLOBALS['fetched_row'] = $row;
	check(verify_data_input('hash', 'a'), $expected, 'verify_data_input');
}

print json_encode(['passed' => $GLOBALS['passed']], JSON_THROW_ON_ERROR) . "\n";
