<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 4) . '/lib/html_utility.php';

beforeEach(function () {
	$this->sortState = [$_SESSION ?? array(), $_REQUEST, $GLOBALS['_CACTI_REQUEST'] ?? array(), $_SERVER['SCRIPT_NAME'] ?? ''];
	$_SESSION = $_REQUEST = $GLOBALS['_CACTI_REQUEST'] = array();
	$_SERVER['SCRIPT_NAME'] = '/data_debug.php';
});

afterEach(function () {
	[$_SESSION, $_REQUEST, $GLOBALS['_CACTI_REQUEST'], $_SERVER['SCRIPT_NAME']] = $this->sortState;
});

/*
 * The sortable columns are listed per script. The page argument is kept for
 * older callers and does not select a list.
 */
function sort_columns_listed(array $columns) : void {
	$_SESSION['valid_sort_columns']['/data_debug.php'] = $columns;
}

test('sort validation refuses every column before the script has listed any', function (string $column) {
	expect(validate_sort_column($column, 'page'))->toBe('');
})->with(['description', 'hostname', 'u.name', 'id', 'status']);

test('sort validation refuses credential style names even when they are listed', function (string $column) {
	sort_columns_listed(array('description', $column));

	expect(validate_sort_column($column, 'page'))->toBe('')
		->and(validate_sort_column('description', 'page'))->toBe('description');
})->with([
	'password', 'u.password', 'password_history', 'u.password_history', 'passwd', 'dbpass', 'rsync_pass', 'snmp_community',
	'snmp_priv_passphrase', 'tfa_secret', 'secret_question', 'key_token', 'token', 'token_expiry', 'csrf_token_time', 'dbsslkey',
	'authkey', 'apikey', 'api_key', 'repo_api_key', 'api_key_id', 'private_key', 'privatekey', 'ssh_key', 'hash', 'password_hash',
	'gt.hash', 'INET_ATON(snmp_community)', 'API_KEY',
]);

test('sort validation accepts ordinary listed columns', function (string $column) {
	sort_columns_listed(array($column));

	expect(validate_sort_column($column, 'page'))->toBe($column)
		->and(validate_sort_column($column . '_other', 'page'))->toBe('');
})->with([
	'hostname', 'host_id', 'status', 'id', 'name', 'description', 'snmp_version', 'last_updated', 'graph_template_id', 'h.description',
	'poller.hostname', 'passes', 'keys', 'key_name', 'monkey', 'compass_heading', 'author', 'INET_ATON(hostname)',
]);

test('sort validation compares strictly and ignores the page argument', function () {
	sort_columns_listed(array('description', 'enabled', '0'));
	$_SESSION['valid_sort_columns']['page'] = array('username');

	expect(validate_sort_column('description', 'page'))->toBe('description')
		->and(validate_sort_column('username', 'page'))->toBe('')
		->and(validate_sort_column('Description', 'page'))->toBe('')
		->and(validate_sort_column('description ', 'page'))->toBe('')
		->and(validate_sort_column(0, 'page'))->toBe('0')
		->and(validate_sort_column(array('description'), 'page'))->toBe('')
		->and(validate_sort_column(null, 'page'))->toBe('');
});

test('the list does not depend on the action or tab of the request', function () {
	sort_columns_listed(array('description'));
	$_REQUEST = array('action' => 'edit', 'tab' => 'other', 'sort_column' => 'username', 'sort_direction' => 'DESC');
	expect(get_order_string())->toBe('');

	// a new request: the validated copy of the previous one is gone
	$GLOBALS['_CACTI_REQUEST'] = array();
	$_REQUEST['sort_column']   = 'description';
	expect(get_order_string())->toBe('ORDER BY `description` DESC');
});

test('request sorts use the listed columns and preserve valid ordering', function (string $column, string $expected) {
	sort_columns_listed(array('description', 'u.name', 'u.password', 'password_history'));
	$_REQUEST = array('sort_column' => $column, 'sort_direction' => 'DESC');
	expect(get_order_string())->toBe($expected);
})->with([
	['u.password', ''],
	['password_history', ''],
	['username', ''],
	['description', 'ORDER BY `description` DESC'],
	['u.name', 'ORDER BY `u`.`name` DESC'],
]);

test('persisted multi-column sorts drop the columns that are refused', function () {
	sort_columns_listed(array('description', 'password', 'status'));
	$page = get_order_string_page(false);
	$_SESSION['sort_string'][$page] = 'ORDER BY password ASC, username ASC, description DESC';
	$_SESSION['sort_data'][$page] = array('password' => 'ASC', 'username' => 'ASC', 'description' => 'DESC');

	expect(get_order_string())->toBe('ORDER BY `description` DESC')
		->and($_SESSION['sort_data'][$page])->toBe(array('description' => 'DESC'));

	$page = get_order_string_page(false);
	$_SESSION['sort_data'][$page] = array('password' => 'ASC', 'username' => 'ASC', 'status' => 'DESC');
	update_order_string(true);

	expect($_SESSION['sort_data'][$page])->toBe(array('status' => 'DESC'))
		->and($_SESSION['sort_string'][$page])->toBe('ORDER BY status DESC');
});

test('a stored sort left without a listed column gives way to the requested column', function () {
	sort_columns_listed(array('description'));
	$_REQUEST = array('sort_column' => 'description', 'sort_direction' => 'ASC');

	$page = get_order_string_page(false);
	$_SESSION['sort_string'][$page] = 'ORDER BY username ASC';
	$_SESSION['sort_data'][$page] = array('username' => 'ASC');
	expect(get_order_string())->toBe('ORDER BY `description` ASC')
		->and(isset($_SESSION['sort_string'][$page]))->toBeFalse();

	$page = get_order_string_page(false);
	$_SESSION['sort_string'][$page] = 'ORDER BY password ASC';
	$GLOBALS['_CACTI_REQUEST'] = array();
	$_REQUEST = array('sort_column' => 'password');
	expect(get_order_string())->toBe('');
});
