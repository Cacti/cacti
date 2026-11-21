<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\SortLookupChecks;

/*
 * Runs the production sort column, group list, realtime graph and tree node
 * code with its request, session and database boundaries replaced, one request
 * per process so the per-request table counters start at zero as they do in a
 * page view.
 *
 *   sort <scenario>       one of the table requests listed in sort_scenarios()
 *   request <json>        a table request described by the caller; the verdict
 *                         carries the session so the next request can continue
 *   helpers               column checks and the group list query
 *   realtime <case>       allowed | denied
 *   tree <case>           one of the node requests listed in tree_cases()
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
define('CACTI_PATH_BASE', $root);

// plugins.php words one of its header labels by collector
const POLLER_ID = 1;

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new \RuntimeException($message);
	}
}

function verdict(array $data) : void {
	print json_encode($data, JSON_THROW_ON_ERROR) . "\n";
}

function get_request_var(string $name, mixed $default = '') : mixed {
	return $_REQUEST[$name] ?? $default;
}

function get_nfilter_request_var(string $name, mixed $default = '') : mixed {
	return $_REQUEST[$name] ?? $default;
}

function grv(string $name, mixed $default = '') : mixed {
	return $_REQUEST[$name] ?? $default;
}

function gnrv(string $name, mixed $default = '') : mixed {
	return $_REQUEST[$name] ?? $default;
}

function gfrv(string $name) : int {
	return (int) ($_REQUEST[$name] ?? 0);
}

function isset_request_var(string $name) : bool {
	return isset($_REQUEST[$name]);
}

function isrv(string $name) : bool {
	return isset($_REQUEST[$name]);
}

function isempty_request_var(string $name) : bool {
	return ($_REQUEST[$name] ?? '') === '';
}

function set_request_var(string $name, mixed $value) : void {
	$_REQUEST[$name] = $value;
}

function unset_request_var(string $name) : void {
	unset($_REQUEST[$name]);
}

function kill_session_var(string $name) : void {
	unset($_SESSION[$name]);
}

function sanitize_search_string(mixed $value) : string {
	return (string) $value;
}

function cacti_sizeof(mixed $data) : int {
	return is_countable($data) ? count($data) : 0;
}

function cacti_count(mixed $data) : int {
	return is_countable($data) ? count($data) : 0;
}

function cacti_strtolower(string $value) : string {
	return strtolower($value);
}

function cacti_log(string $message) : void {
	throw new \RuntimeException('Unexpected log entry: ' . $message);
}

function cacti_version_compare(string $left, string $right, string $operator) : bool {
	return version_compare($left, $right, $operator);
}

function get_mysql_info() : array {
	return $GLOBALS['fixture_natural_sort'] ? ['database' => 'MariaDB', 'version' => '11.4.2'] : ['database' => 'MySQL', 'version' => '8.0.36'];
}

function htmle(mixed $value) : string {
	return htmlspecialchars((string) $value, ENT_QUOTES);
}

function __(string $text, mixed ...$args) : string {
	return $args === [] ? $text : vsprintf($text, $args);
}

function __esc(string $text, mixed ...$args) : string {
	return htmle(__($text, ...$args));
}

function form_get_table_id() : string {
	return 'fixture:0:';
}

function form_process_visible_display_text(string $table_id, array $display_text) : array {
	return $display_text;
}

function header(string $value) : void {
	$GLOBALS['fixture_headers'][] = $value;
}

foreach (['lib/functions.php' => ['sanitize_sql_column', 'get_current_page', 'get_current_script_name'],
	'lib/html_utility.php'       => ['validate_store_request_vars', 'update_order_string', 'get_order_string', 'validate_sort_column',
		'cacti_validate_sort_column', 'remove_column_from_order_string', 'get_order_string_page'],
	'lib/html.php'      => ['html_header_sort_register', 'html_header_sort_order', 'html_header_sort_column',
		'html_header_sort', 'html_header_sort_checkbox'],
	'lib/html_tree.php' => ['html_tree_get_node']] as $file => $functions) {
	foreach ($functions as $function) {
		\cacti_fixture_source_function($root . '/' . $file, $function, __NAMESPACE__);
	}
}

// The header definitions of real pages, read from their source.
function page_header(string $name) : array {
	$headers = [
		'cdef.php'             => ['cdef.php', "/^(\\t\\\$display_text = \\[\\n\\t\\t'name' => \\[.*?^\\t\\];)/ms"],
		'host.php'             => ['host.php', "/^(\\t\\\$display_text = \\[\\n\\t\\t'description' => \\[.*?^\\t\\];)/ms"],
		'pollers.php'          => ['pollers.php', "/^(\\t\\\$display_text = \\[\\n\\t\\t'name' => \\[.*?^\\t\\];)/ms"],
		'user_group_admin.php' => ['user_group_admin.php', "/^(\\t\\\$display_text = \\[\\n\\t\\t'name' => \\[.*?^\\t\\];)/ms"],
		'plugins.php:archive'  => ['plugins.php', "/^(\\t\\t\\t\\\$display_text = \\[\\n\\t\\t\\t\\t'nosort' => \\[.*?^\\t\\t\\t\\];)/ms"],
		'plugins.php:loaded'   => ['plugins.php', "/^(\\t\\t\\t\\\$display_text = \\[\\n\\t\\t\\t\\t'nosort0' => \\[.*?^\\t\\t\\t\\];)/ms"],
	];
	check(isset($headers[$name]), 'Unknown page header ' . $name);
	[$file, $pattern] = $headers[$name];
	$state            = \cacti_fixture_source_block(dirname(__DIR__, 3) . '/' . $file, __NAMESPACE__, $pattern);
	check(is_array($state['display_text'] ?? null) && count($state['display_text']) > 2, 'Could not read the header of ' . $name);

	return $state['display_text'];
}

function header_columns(array|string $columns) : array {
	if (is_string($columns)) {
		return page_header($columns);
	}
	$header = [];

	foreach ($columns as $column) {
		$header[$column] = ['display' => $column, 'sort' => 'ASC'];
	}

	return $header;
}

function sortable_keys(array $header) : array {
	return array_values(array_filter(array_map('strval', array_keys($header)), static fn (string $key) : bool => $key !== '' && !str_contains($key, 'nosort')));
}

/*
 * One table request in page order: the filter is validated and the sort
 * stored, the query asks for its ORDER BY clause, then the header is drawn.
 */
function table_request(array $spec) : array {
	$script                          = $spec['script'];
	$_SERVER['SCRIPT_NAME']          = '/' . $script;
	$_REQUEST                        = $spec['request'] ?? [];
	$_SESSION                        = $spec['session'] ?? [];
	$GLOBALS['fixture_natural_sort'] = $spec['natural'] ?? false;
	$key                             = '0_' . basename($script, '.php') . (isset($_REQUEST['action']) ? '_' . $_REQUEST['action'] : '') . (isset($_REQUEST['tab']) ? '_' . $_REQUEST['tab'] : '');

	$page                            = '/' . $script;

	if (isset($spec['listed'])) {
		$_SESSION['valid_sort_columns'][$page] = sortable_keys(header_columns($spec['listed']));
	}

	if ($spec['validate'] ?? true) {
		$callback = ['options' => __NAMESPACE__ . '\\sanitize_search_string'];
		$default  = array_key_exists('default', $spec) ? $spec['default'] : 'name';
		validate_store_request_vars([
			'sort_column'    => ['filter' => FILTER_CALLBACK, 'options' => $callback] + ($default === null ? [] : ['default' => $default]),
			'sort_direction' => ['filter' => FILTER_CALLBACK, 'options' => $callback, 'default' => 'ASC'],
		], 'sess_fixture');
	}

	if (isset($spec['remove'])) {
		remove_column_from_order_string($spec['remove']);
	}

	$order = get_order_string();
	$html  = '';

	if (isset($spec['columns'])) {
		ob_start();

		if (($spec['header'] ?? 'checkbox') === 'checkbox') {
			html_header_sort_checkbox(header_columns($spec['columns']), (string) grv('sort_column'), (string) grv('sort_direction'), false);
		} else {
			html_header_sort(header_columns($spec['columns']), (string) grv('sort_column'), (string) grv('sort_direction'));
		}
		$html = (string) ob_get_clean();
	}

	return [
		'order'   => $order,
		'key'     => $key,
		'listed'  => $_SESSION['valid_sort_columns'][$page] ?? null,
		'stored'  => $_SESSION['sort_data'][$key] ?? null,
		'string'  => $_SESSION['sort_string'][$key] ?? null,
		'header'  => $html,
		'session' => $_SESSION,
	];
}

function sort_scenarios() : array {
	$cdef = ['script' => 'cdef.php', 'listed' => 'cdef.php', 'columns' => 'cdef.php'];
	$log  = ['script' => 'user_log.php', 'validate' => false];

	return [
		// A first view has no column list yet; the default sort applies and the header lists the sortable columns.
		'first-view' => [
			['script' => 'cdef.php', 'columns' => 'cdef.php'],
			['order'  => 'ORDER BY `name` ASC', 'listed' => ['name', 'graphs', 'templates'], 'stored' => ['name' => 'ASC']],
		],
		// Before a page has drawn a header, only its default column is sortable.
		'unregistered-page' => [
			['script' => 'pollers.php', 'columns' => 'pollers.php', 'request' => ['sort_column' => 'dbpass']],
			['order'  => 'ORDER BY `name` ASC', 'stored' => ['name' => 'ASC']],
		],
		// A listed column is added to the stored sort.
		'listed-column' => [
			$cdef + ['session' => ['sort_data' => ['0_cdef' => ['name' => 'ASC']]], 'request' => ['sort_column' => 'graphs', 'sort_direction' => 'DESC']],
			['order' => 'ORDER BY `name` ASC, `graphs` DESC', 'stored' => ['name' => 'ASC', 'graphs' => 'DESC']],
		],
		// A column the header marks as not sortable gives way to the default column.
		'unsortable-column' => [
			$cdef + ['session' => ['sort_data' => ['0_cdef' => ['graphs' => 'DESC']]], 'request' => ['sort_column' => 'nosort']],
			['order' => 'ORDER BY `graphs` DESC, `name` ASC', 'stored' => ['graphs' => 'DESC', 'name' => 'ASC']],
		],
		// A table column the header does not list gives way to the default column.
		'unlisted-column' => [
			$cdef + ['request' => ['sort_column' => 'hash']],
			['order' => 'ORDER BY `name` ASC', 'stored' => ['name' => 'ASC']],
		],
		// Without a default to fall back to, a refused column leaves no sort and a stored one is removed.
		'no-default' => [
			$cdef + ['default' => null, 'session' => ['sort_data' => ['0_cdef' => ['hash' => 'DESC']]], 'request' => ['sort_column' => 'hash', 'sort_direction' => 'ASC']],
			['order' => '', 'stored' => [], 'string' => null],
		],
		// The action and tab of a request do not select a different column list.
		'other-action-unlisted' => [
			$cdef + ['request' => ['action' => 'other', 'tab' => 'extra', 'sort_column' => 'hash']],
			['order' => 'ORDER BY `name` ASC', 'key' => '0_cdef_other_extra', 'stored' => ['name' => 'ASC']],
		],
		'other-action-listed' => [
			$cdef + ['request' => ['action' => 'other', 'tab' => 'extra', 'sort_column' => 'graphs']],
			['order' => 'ORDER BY `graphs` ASC', 'key' => '0_cdef_other_extra', 'stored' => ['graphs' => 'ASC']],
		],
		// The list of one script does not apply to another.
		'other-script' => [
			['script' => 'vdef.php', 'session' => ['valid_sort_columns' => ['/cdef.php' => ['name', 'graphs', 'templates']]], 'request' => ['sort_column' => 'graphs']],
			['order'  => 'ORDER BY `name` ASC', 'listed' => ['name'], 'stored' => ['name' => 'ASC']],
		],
		// A single-column sort on a listed column, with natural host name ordering.
		'single-column' => [
			['script'  => 'host.php', 'listed' => 'host.php', 'columns' => 'host.php', 'default' => 'description', 'natural' => true,
				'request' => ['sort_column' => 'hostname', 'sort_direction' => 'DESC', 'add' => 'reset']],
			['order' => 'ORDER BY NATURAL_SORT_KEY(`hostname`) DESC', 'stored' => ['hostname' => 'DESC']],
		],
		// A single-column sort on a column the device list does not show uses the default column.
		'single-column-refused' => [
			['script'  => 'host.php', 'listed' => 'host.php', 'columns' => 'host.php', 'default' => 'description',
				'request' => ['sort_column' => 'snmp_community', 'add' => 'reset']],
			['order' => 'ORDER BY `description` ASC', 'stored' => ['description' => 'ASC']],
		],
		// The page default stays usable when the header drawn for a filter state does not list it.
		'default-outside-header' => [
			['script' => 'plugins.php', 'listed' => 'plugins.php:archive', 'columns' => 'plugins.php:archive', 'header' => 'plain', 'default' => 'pi.plugin'],
			['order'  => 'ORDER BY `pi`.`plugin` ASC', 'stored' => ['pi.plugin' => 'ASC']],
		],
		// A wrapped column the page lists itself is used as the page wrote it.
		'wrapped-column' => [
			['script'  => 'managers.php', 'listed' => ['INET_ATON(hostname)', 'description'], 'columns' => ['INET_ATON(hostname)', 'description'],
				'default' => 'description', 'request' => ['sort_column' => 'INET_ATON(hostname)', 'reset' => '1']],
			['order' => 'ORDER BY INET_ATON(hostname) ASC', 'stored' => ['INET_ATON(hostname)' => 'ASC']],
		],
		// A stored sort holding a column that is not listed is dropped when it is read.
		'stale-stored-sort' => [
			$log + ['listed' => ['username', 'time'],
				'session'       => ['sort_data' => ['0_user_log' => ['password' => 'ASC', 'username' => 'ASC']], 'sort_string' => ['0_user_log' => 'ORDER BY password ASC, username ASC']],
				'request'       => ['sort_column' => 'username', 'sort_direction' => 'DESC']],
			['order' => 'ORDER BY `username` DESC', 'stored' => null, 'string' => null],
		],
		// A stored clause without its columns is not trusted, and without a list nothing else is either.
		'orphan-stored-sort' => [
			$log + ['session' => ['sort_string' => ['0_user_log' => 'ORDER BY tfa_secret ASC']], 'request' => ['sort_column' => 'username']],
			['order' => '', 'stored' => null, 'string' => null],
		],
		// A stored sort whose columns are all listed is used unchanged.
		'stored-sort' => [
			$log + ['listed' => ['username', 'time'],
				'session'       => ['sort_data' => ['0_user_log' => ['time' => 'DESC', 'username' => 'ASC']], 'sort_string' => ['0_user_log' => 'ORDER BY `time` DESC, `username` ASC']]],
			['order' => 'ORDER BY `time` DESC, `username` ASC', 'stored' => ['time' => 'DESC', 'username' => 'ASC']],
		],
		// Removing a column rebuilds the clause from the columns that are still listed.
		'remove-column' => [
			$cdef + ['remove' => 'graphs', 'request' => ['sort_column' => 'name'],
				'session'        => ['sort_data' => ['0_cdef' => ['name' => 'ASC', 'hash' => 'ASC', 'graphs' => 'DESC', 'templates' => 'DESC']]]],
			['order' => 'ORDER BY name ASC, templates DESC', 'stored' => ['name' => 'ASC', 'templates' => 'DESC']],
		],
		// Removing the only stored column leaves the requested column, not an empty clause.
		'remove-last-column' => [
			$cdef + ['remove' => 'name', 'request' => ['sort_column' => 'name']],
			['order' => 'ORDER BY `name` ASC', 'stored' => [], 'string' => null],
		],
	];
}

function tree_cases() : array {
	// tree 5 is viewable, tree 6 is not; branch 70 belongs to tree 5 and branch 77 to tree 6
	return [
		'root-allowed'   => [['tree_id' => '0', 'id' => 'tree_anchor-5'], 5, [5, -1]],
		'root-denied'    => [['tree_id' => '0', 'id' => 'tree_anchor-6'], 5, null],
		'branch-allowed' => [['tree_id' => '5', 'id' => 'tbranch-70'], 5, [5, 70]],
		'branch-denied'  => [['tree_id' => '5', 'id' => 'tbranch-77'], 5, null],
		'lookup-denied'  => [['tree_id' => '0', 'id' => 'tbranch-77'], 5, null],
		'default-denied' => [['id' => '#'], 6, null],
		'tree-list'      => [['id' => '#'], 0, [0, -1]],
	];
}

function read_user_setting(string $name) : mixed {
	return $name === 'default_tree_id' ? $GLOBALS['fixture_default_tree'] : '';
}

function input_validate_input_number(mixed $value, string $name) : void {
	check(is_numeric($value), 'Unexpected ' . $name);
}

function db_fetch_cell_prepared(string $sql, array $params) : mixed {
	check(str_contains($sql, 'FROM graph_tree_items'), 'Unexpected lookup');

	return ['70' => 5, '77' => 6][(string) $params[0]] ?? false;
}

function db_fetch_assoc_prepared(string $sql, array $params) : array {
	$GLOBALS['fixture_queries'][] = preg_replace('/\s+/', ' ', $sql);

	return [];
}

function is_tree_allowed(int $tree_id) : bool {
	$GLOBALS['fixture_checked'][] = $tree_id;

	return $tree_id === 5;
}

function api_tree_get_main(mixed $tree_id, int $parent) : void {
	$GLOBALS['fixture_nodes'] = [(int) $tree_id, $parent];
}

function is_graph_allowed(int $local_graph_id) : bool {
	return $local_graph_id === 12;
}

function read_config_option(string $name) : string {
	return ['realtime_cache_path' => '/var/cache/realtime', 'path_php_binary' => '/usr/bin/php'][$name];
}

function cacti_escapeshellcmd(string $value) : string {
	return $value;
}

function cacti_escapeshellarg(string $value) : string {
	return escapeshellarg($value);
}

function session_id() : string {
	return 'fixture-session';
}

function shell_exec(string $command) : void {
	$GLOBALS['fixture_commands'][] = $command;
}

$GLOBALS['fixture_headers'] = $GLOBALS['fixture_commands'] = $GLOBALS['fixture_checked'] = $GLOBALS['fixture_queries'] = [];
$GLOBALS['fixture_nodes']   = null;
$mode                       = $argv[1] ?? '';
$case                       = $argv[2] ?? '';

if ($mode === 'sort') {
	check(isset(sort_scenarios()[$case]), 'Unknown sort scenario');
	[$spec, $expected] = sort_scenarios()[$case];
	$result            = table_request($spec);

	foreach ($expected as $name => $value) {
		check($result[$name] === $value, $case . ': unexpected ' . $name . ' ' . var_export($result[$name], true));
	}

	if (isset($spec['columns'])) {
		$sortable = sortable_keys(header_columns($spec['columns']));
		check(array_diff($sortable, $result['listed']) === [], $case . ': the header did not list its sortable columns');
		check(preg_grep('/nosort/', $result['listed']) === [], $case . ': an unsortable column was listed');
		check(substr_count($result['header'], "class='sortinfo'") === count($sortable), $case . ': the header was not drawn');
	}
	verdict(['scenario' => $case, 'order' => $result['order'], 'listed' => $result['listed']]);

	exit;
}

if ($mode === 'request') {
	verdict(table_request(json_decode($case, true, 16, JSON_THROW_ON_ERROR)));

	exit;
}

if ($mode === 'helpers') {
	$_SESSION = ['valid_sort_columns' => ['/listed.php' => ['name', 'h.description', 'INET_ATON(hostname)', 'api_key']]];
	$checked  = [];

	foreach ([
		['name', 'listed', 'name'], ['h.description', 'listed', 'h.description'], ['INET_ATON(hostname)', 'listed', 'INET_ATON(hostname)'],
		['api_key', 'listed', 'api_key'], ['description', 'listed', ''], ['password', 'listed', ''], ['h.password', 'listed', ''],
		['tfa_secret', 'listed', ''], ['key_token', 'listed', ''], ['hash', 'listed', ''], ['INET_ATON(password)', 'listed', ''],
		['SLEEP(5)', 'listed', ''], ['', 'listed', ''], ['`', 'listed', ''],
		['name', 'unlisted', ''], ['h.description', 'unlisted', ''], ['api_key', 'unlisted', ''], ['repo_api_key', 'unlisted', ''],
		['password', 'unlisted', ''], ['dbsslkey', 'unlisted', ''], ['INET_ATON(hostname)', 'unlisted', ''], ['id', 'unlisted', ''],
	] as [$column, $page, $expected]) {
		$_SERVER['SCRIPT_NAME'] = '/' . $page . '.php';
		check(validate_sort_column($column) === $expected, 'Unexpected result for ' . $column . ' on the ' . $page . ' page');
		$checked[] = $column;
	}

	check(cacti_validate_sort_column('time', ['username', 'time'], 'username') === 'time', 'An allowed column was replaced');
	check(cacti_validate_sort_column('password', ['username', 'time'], 'username') === 'username', 'The default was not used');
	check(cacti_validate_sort_column('password', ['username', 'time']) === 'username', 'The first allowed column was not used');
	check(cacti_validate_sort_column('password', []) === 'id', 'An empty list did not fall back to id');

	// the group list sorts on the columns its header shows and on nothing else
	$listed = sortable_keys(page_header('user_group_admin.php'));
	$block  = '/^\\t(\\$sort_column = cacti_validate_sort_column\\(.*?\\$sql_params\\);)/ms';

	foreach (array_merge($listed, ['id', 'uag.id', 'graph_settings', '(SELECT 1)']) as $column) {
		$_REQUEST = ['sort_column' => $column, 'sort_direction' => 'desc', 'page' => '2'];
		$state    = \cacti_fixture_source_block($root . '/user_group_admin.php', __NAMESPACE__, $block, ['sql_where' => '', 'sql_params' => [], 'rows' => 30]);
		$sorted   = in_array($column, $listed, true) ? $column : 'name';
		check($state['sort_column'] === $sorted, 'Unexpected group list column for ' . $column);
		check(str_ends_with(trim(end($GLOBALS['fixture_queries'])), 'GROUP BY uag.id ORDER BY ' . $sorted . ' DESC LIMIT 30,30'), 'Unexpected group list query for ' . $column);
	}
	check($listed === ['name', 'members', 'description', 'policy_graphs', 'policy_hosts', 'policy_graph_templates', 'enabled'], 'The group list header changed');
	verdict(['columns' => count($checked), 'group_queries' => count($GLOBALS['fixture_queries'])]);

	exit;
}

if ($mode === 'realtime') {
	$allowed  = $case === 'allowed';
	$_REQUEST = ['local_graph_id' => $allowed ? '12' : '13'];
	register_shutdown_function(static function () use ($allowed) : void {
		$commands = $GLOBALS['fixture_commands'];
		check($GLOBALS['fixture_headers'] === ($allowed ? [] : ['Location: permission_denied.php']), 'Unexpected realtime redirect');
		check(count($commands) === ($allowed ? 1 : 0), 'Unexpected realtime poller start');
		check(!$allowed || str_contains($commands[0], '--graph=12 --interval=5 --poller_id='), 'Unexpected realtime poller arguments');
		verdict(['allowed' => $allowed, 'poller_started' => count($commands) === 1]);
	});
	\cacti_fixture_source_block($root . '/graph_realtime.php', __NAMESPACE__,
		"/^\\t\\t(\\\$local_graph_id = \\(int\\) gfrv\\('local_graph_id'\\);.*?shell_exec\\([^\\n]*\\);)/ms", ['graph_data_array' => ['ds_step' => 5]]);
	check($allowed, 'A refused realtime request continued');

	exit;
}

if ($mode === 'tree') {
	check(isset(tree_cases()[$case]), 'Unknown tree case');
	[$_REQUEST, $GLOBALS['fixture_default_tree'], $nodes] = tree_cases()[$case];
	register_shutdown_function(static function () use ($nodes) : void {
		check($GLOBALS['fixture_nodes'] === $nodes, 'Unexpected node lookup ' . var_export($GLOBALS['fixture_nodes'], true));
		check($GLOBALS['fixture_headers'] === ($nodes === null ? ['Location: permission_denied.php'] : []), 'Unexpected tree redirect');
		verdict(['nodes' => $nodes, 'checked' => $GLOBALS['fixture_checked']]);
	});
	html_tree_get_node();
	check($nodes !== null, 'A refused tree request continued');

	exit;
}

throw new \RuntimeException('Usage: SortLookupChecks.php sort|request|helpers|realtime|tree [case]');
