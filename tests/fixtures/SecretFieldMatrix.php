<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Child-process fixture for tests/Unit/Ui/Html/FormSecretFieldTest.php.
 * It loads lib/html_form.php with stubbed globals and runs the unchanged
 * password save code of settings.php, pollers.php and user_domains.php
 * against a fake settings store, printing the number of passed checks.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 2);

function cacti_sizeof($array) {
	return (is_array($array) || $array instanceof Countable) ? count($array) : 0;
}

function html_escape($string) {
	return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

function __($text) {
	return $text;
}

function __esc($text) {
	return html_escape($text);
}

function isset_request_var($name) {
	return isset($_REQUEST[$name]);
}

function get_nfilter_request_var($name, $default = '') {
	return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
}

function get_filter_request_var($name) {
	return isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';
}

function db_qstr($value) {
	return "'" . addslashes((string) $value) . "'";
}

function db_fetch_cell_prepared($sql, $params = array()) {
	return isset($GLOBALS['stored'][$params[0]]) ? $GLOBALS['stored'][$params[0]] : false;
}

function db_execute_prepared($sql, $params = array()) {
	$GLOBALS['writes'][$params[0]] = $params[1];

	return true;
}

function db_connect_real() {
	$GLOBALS['connected'] = func_get_args();

	return false;
}

function db_close($connection) {
}

function db_fetch_row_prepared($sql, $params = array()) {
	return isset($GLOBALS['rows'][$params[0]]) ? $GLOBALS['rows'][$params[0]] : array();
}

function fixture_source($file, $pattern) {
	if (!preg_match($pattern, file_get_contents($file), $match)) {
		throw new RuntimeException('Cannot locate the source under test in ' . $file);
	}

	return $match[0];
}

require $root . '/lib/html_form.php';

// The production functions and branches under test, unchanged. Only checked-in
// source files are evaluated, never request data.
eval(fixture_source($root . '/pollers.php', '/^function poller_stored_dbpass\(.*?^\}/ms'));
eval(fixture_source($root . '/pollers.php', '/^function poller_saved_test_dbpass\(.*?^\}/ms'));
eval(fixture_source($root . '/pollers.php', '/^function test_database_connection\(.*?^\}/ms'));
eval('function settings_password_branch($field_name, $field_array) { global $errors, $inserts; do { if (false) {' .
	fixture_source($root . '/settings.php', "/\} elseif \(\\\$field_array\['method'\] == 'textbox_password'\) \{.*?(?=\} elseif \(\(isset\(\\\$field_array\['items'\]\)\))/s") . '} } while (false); }');
eval('function user_domains_password($domain_id) { $save = array();' .
	fixture_source($root . '/user_domains.php', "/\\\$save\['specific_password'\]\s*=.*?;\n/") . ' return $save[\'specific_password\']; }');

$count = 0;

function check($result, $case) {
	global $count;

	if (!$result) {
		throw new RuntimeException('Secret field mismatch: ' . $case);
	}

	$count++;
}

// Rendering: neither the stored value nor a new record's default reaches the page.
foreach (array(array('stored-smtp-secret', '', 1), array('', 'main-db-secret', 0)) as $case) {
	$_SESSION = array(
		'sess_error_fields' => array('secret_field' => 'secret_field'),
		'sess_field_values' => array('secret_field' => 'typed-secret', 'secret_field_confirm' => 'typed-secret'),
	);

	$field = array('method' => 'textbox_password', 'value' => $case[0], 'default' => $case[1], 'max_length' => 64, 'form_id' => $case[2], 'secret' => true);

	ob_start();
	draw_edit_control('secret_field', $field);
	$html = ob_get_clean();

	check(strpos($html, 'secret-') === false && strpos($html, '-secret') === false && strpos($html, "name='secret_field_clear'") !== false, 'render ' . $case[2]);
}

// A field without the flag keeps its existing rendering.
$_SESSION = array();
$field    = array('method' => 'textbox_password', 'value' => 'legacy', 'max_length' => 64);

ob_start();
draw_edit_control('plugin_password', $field);
$html = ob_get_clean();

check(substr_count($html, "value='legacy'") == 2 && strpos($html, '_clear') === false, 'render without secret');

// settings.php: blank keeps, a typed value replaces, clear blanks, a mismatch saves nothing.
$cases = array(
	'blank keeps'    => array(array('settings_smtp_password' => '', 'settings_smtp_password_confirm' => ''), 'stored-smtp-secret', 'stored-smtp-secret'),
	'change'         => array(array('settings_smtp_password' => 'new-secret', 'settings_smtp_password_confirm' => 'new-secret'), 'stored-smtp-secret', 'new-secret'),
	'clear'          => array(array('settings_smtp_password' => '', 'settings_smtp_password_confirm' => '', 'settings_smtp_password_clear' => 'on'), 'stored-smtp-secret', ''),
	'nothing stored' => array(array('settings_smtp_password' => '', 'settings_smtp_password_confirm' => ''), false, null),
	'mismatch'       => array(array('settings_smtp_password' => 'one', 'settings_smtp_password_confirm' => 'two'), 'stored-smtp-secret', null),
);

foreach ($cases as $case => $data) {
	$_REQUEST          = $data[0];
	$GLOBALS['stored'] = array('settings_smtp_password' => $data[1]);
	$GLOBALS['writes'] = array();
	$_SESSION          = array();

	settings_password_branch('settings_smtp_password', array('method' => 'textbox_password'));

	check((isset($GLOBALS['writes']['settings_smtp_password']) ? $GLOBALS['writes']['settings_smtp_password'] : null) === $data[2], 'settings ' . $case);
}

// pollers.php: the saved password, or the main one for a new data collector, backs a blank save.
$GLOBALS['database_password'] = 'main-db-secret';
$GLOBALS['stored']            = array(2 => 'remote-secret');

check(poller_stored_dbpass(2) === 'remote-secret' && poller_stored_dbpass(0) === 'main-db-secret', 'poller stored');

// pollers.php connection test: a blank password only reuses the saved one for the saved target.
$target = array('dbhost' => 'db.example', 'dbuser' => 'cacti', 'dbdefault' => 'cacti', 'dbport' => '3306', 'dbretries' => '2', 'dbsslkey' => '', 'dbsslcert' => '', 'dbsslca' => '');

$GLOBALS['rows'] = array(2 => array('dbhost' => 'db.example', 'dbport' => '3306', 'dbuser' => 'cacti', 'dbpass' => 'remote-secret', 'dbssl' => '', 'dbsslkey' => '', 'dbsslcert' => '', 'dbsslca' => ''));

$cases = array(
	'blank, saved target'    => array(array('id' => '2', 'dbpass' => ''), 'remote-secret'),
	'blank, changed host'    => array(array('id' => '2', 'dbpass' => '', 'dbhost' => 'other.example'), null),
	'blank, changed port'    => array(array('id' => '2', 'dbpass' => '', 'dbport' => '3307'), null),
	'blank, changed user'    => array(array('id' => '2', 'dbpass' => '', 'dbuser' => 'other'), null),
	'blank, changed SSL'     => array(array('id' => '2', 'dbpass' => '', 'dbssl' => 'on'), null),
	'blank, no saved record' => array(array('id' => '3', 'dbpass' => ''), null),
	'typed, changed host'    => array(array('id' => '2', 'dbpass' => 'typed-secret', 'dbhost' => 'other.example'), 'typed-secret'),
);

foreach ($cases as $case => $data) {
	$_REQUEST             = $data[0] + $target;
	$GLOBALS['connected'] = null;

	ob_start();
	$result = test_database_connection();
	$output = ob_get_clean();

	if ($data[1] === null) {
		check($result === false && $GLOBALS['connected'] === null && strpos($output, 'Enter the database password') !== false, 'poller ping ' . $case);
	} else {
		check($result !== false && $GLOBALS['connected'][2] === $data[1], 'poller ping ' . $case);
	}
}

// user_domains.php: the LDAP bind password keeps, replaces or clears the saved one.
$GLOBALS['stored'] = array(7 => 'stored-bind');

$cases = array(
	'blank keeps' => array(array('specific_password' => ''), 7, 'stored-bind'),
	'change'      => array(array('specific_password' => 'new-bind'), 7, 'new-bind'),
	'clear'       => array(array('specific_password' => '', 'specific_password_clear' => 'on'), 7, ''),
	'new domain'  => array(array('specific_password' => ''), 8, ''),
);

foreach ($cases as $case => $data) {
	$_REQUEST = $data[0];

	check(user_domains_password($data[1]) === $data[2], 'user domain ' . $case);
}

print json_encode(array('passed' => $count)) . "\n";
