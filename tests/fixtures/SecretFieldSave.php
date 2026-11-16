<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Run only against a disposable server with CREATE DATABASE permission. The
// complete save handlers and sql_save() use real SQL in a unique test database.
namespace {

if (PHP_SAPI !== 'cli') {

	exit;
}

$root = dirname(__DIR__, 2);
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/lib/database.php';

function get_nfilter_request_var($name, $default = '') {
	return $_REQUEST[$name] ?? $default;
}
function get_request_var($name, $default = '') {
	return get_nfilter_request_var($name, $default);
}
function isset_request_var($name) {
	return isset($_REQUEST[$name]);
}
function get_filter_request_var($name, $filter = FILTER_VALIDATE_INT, $options = []) {
	return filter_var(get_nfilter_request_var($name), $filter, $options);
}
function read_config_option($name, $force = false) {
	return $name === 'poller_interval' ? 300 : '';
}
function cacti_log($message, $output = false, $facility = 'SYSTEM', $level = 2) {
}
function cacti_debug_backtrace($message, $output = false, $log = true, $level = 0, $skip = 0) {
	throw new RuntimeException('Unexpected database or validation failure');
}
function raise_message($id, $message = '', $level = null) {
	$GLOBALS['messages_seen'][] = $id;
}
function __esc($message, ...$args) {
	return htmlspecialchars(vsprintf($message, $args), ENT_QUOTES, 'UTF-8');
}
function poller_check_duplicate_poller_id($id, $host, $column) {
	// DNS and other collectors are outside this credential persistence test.
	return false;
}
function poller_host_duplicate($id, $host) {
	return false;
}
function snmpagent_global_settings_update() {
}
function api_plugin_hook_function($name, $data = null) {
	return $data;
}
function poller_connect_to_remote($id) {
	$GLOBALS['replicated'][] = $id;
	return $GLOBALS['remote'];
}
function kill_session_var($name) {
	unset($_SESSION[$name]);
}
function check($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	$GLOBALS['passed']++;
}

foreach (['cacti_sizeof', 'cacti_count', 'cacti_object_hash', 'clean_up_lines', 'array_rekey', 'form_input_validate', 'is_error_message', 'is_ipaddress'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, '');
}
cacti_fixture_source_function($root . '/lib/html_form.php', 'form_secret_value', '');
cacti_fixture_source_function($root . '/include/csrf.php', 'csrf_require_post', '');
cacti_fixture_source_function($root . '/pollers.php', 'poller_stored_dbpass', '');
cacti_fixture_source_function($root . '/pollers.php', 'form_save', 'SecretCollectorSave');
cacti_fixture_source_function($root . '/user_domains.php', 'form_save', 'SecretDomainSave');

$database_hostname = getenv('SECRET_DB_HOST') ?: '127.0.0.1';
$database_port = getenv('SECRET_DB_PORT') ?: '3306';
$database_default = 'cacti_secret_' . bin2hex(random_bytes(8));
$database_username = getenv('SECRET_DB_USER') ?: 'root';
$database_password = getenv('SECRET_DB_PASSWORD') ?: '';
$dsn = "mysql:host=$database_hostname;port=$database_port;charset=utf8mb4";
$db = new PDO($dsn, $database_username, $database_password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE DATABASE ' . $database_default);
register_shutdown_function(static function () use ($db, $database_default) {
	$db->exec('DROP DATABASE ' . $database_default);
});
$db->exec('USE ' . $database_default);
$remote = new PDO($dsn, $database_username, $database_password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$remote->exec('USE ' . $database_default);
$database_sessions = ["$database_hostname:$database_port:$database_default" => $db];
$database_total_queries = 0;
$config = ['is_web' => false, 'poller_id' => 1];
$passed = 0;
$_SERVER['REQUEST_METHOD'] = 'POST';

// Use checked-in installation DDL instead of maintaining a second schema.
$schema = file_get_contents($root . '/cacti.sql');
foreach (['poller', 'settings', 'user_auth', 'user_domains', 'user_domains_ldap'] as $table) {
	check((bool) preg_match('/CREATE TABLE `?' . $table . '`? \(.*?;\n/s', $schema, $match), 'Missing installation table ' . $table);
	$db->exec($match[0]);
	if ($table === 'settings') {
		$remote->exec(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $match[0]));
	}
}

$collector = ['save_component_poller' => 1, 'id' => 2, 'name' => 'Remote test', 'hostname' => 'collector.example', 'timezone' => 'UTC', 'notes' => '', 'processes' => 1, 'threads' => 1, 'sync_interval' => 1, 'dbdefault' => 'cacti', 'dbhost' => 'db.example', 'dbuser' => 'cacti', 'dbpass' => '', 'dbport' => 3306, 'dbretries' => 2, 'dbsslkey' => '', 'dbsslcert' => '', 'dbsslca' => ''];
$domain = ['save_component_domain_ldap' => 1, 'domain_id' => 7, 'type' => 2, 'user_id' => 9, 'domain_name' => 'Directory test', 'enabled' => 'on', 'server' => 'ldap.example', 'port' => 389, 'port_ssl' => 636, 'proto_version' => 3, 'encryption' => 0, 'referrals' => 0, 'mode' => 2, 'group_member_type' => 1, 'dn' => '', 'group_dn' => '', 'group_attrib' => '', 'search_base' => '', 'search_filter' => '', 'specific_dn' => 'cn=test,dc=example', 'specific_password' => '', 'cn_full_name' => 'cn', 'cn_email' => 'mail'];

$ldap_uri = getenv('SECRET_LDAP_URI');
if ($ldap_uri) {
	$ldap_admin = ldap_connect($ldap_uri);
	ldap_set_option($ldap_admin, LDAP_OPT_PROTOCOL_VERSION, 3);
	if (!ldap_bind($ldap_admin, 'cn=admin,dc=example', getenv('SECRET_LDAP_ADMIN_PASSWORD'))) {
		throw new RuntimeException('Cannot bind to disposable LDAP fixture');
	}
}

foreach (['collector', 'domain'] as $kind) {
	$field = $kind === 'collector' ? 'dbpass' : 'specific_password';
	$table = $kind === 'collector' ? 'poller' : 'user_domains_ldap';
	$key = $kind === 'collector' ? 'id' : 'domain_id';
	$id = $kind === 'collector' ? 2 : 7;
	foreach (['keep', 'replace', 'clear', 'new', 'invalid'] as $mode) {
		$_SESSION = [];
		$_REQUEST = $kind === 'collector' ? $collector : $domain;
		$db->exec('DELETE FROM ' . $table);
		$db->exec('DELETE FROM user_domains');
		if ($mode !== 'new') {
			$seed = array_intersect_key($_REQUEST + ['group_require' => ''], db_get_table_column_types($table));
			$seed[$field] = 'stored-fixture';
			check(sql_save($seed, $table, $key) !== false, 'Seed failed');
		}
		$_REQUEST[$field] = $mode === 'replace' ? "replacement-'fixture" : '';
		if ($mode === 'clear') {
			$_REQUEST[$field . '_clear'] = 'on';
		}
		if ($mode === 'new') {
			$_REQUEST[$key] = 0;
		}
		if ($mode === 'invalid') {
			$_REQUEST[$kind === 'collector' ? 'name' : 'server'] = '';
		}
		if ($kind === 'domain' && $ldap_uri) {
			ldap_mod_replace($ldap_admin, $domain['specific_dn'], ['userPassword' => $mode === 'replace' ? "replacement-'fixture" : 'stored-fixture']);
		}
		try {
			$kind === 'collector' ? \SecretCollectorSave\form_save() : \SecretDomainSave\form_save();
		} catch (\SecretCollectorSave\Redirect $redirect) {
		}
		$actual = $db->query('SELECT ' . $field . ' FROM ' . $table)->fetchColumn();
		$expected = $mode === 'replace' ? "replacement-'fixture" : ($mode === 'clear' ? '' : ($mode === 'new' ? ($kind === 'collector' ? $database_password : '') : 'stored-fixture'));
		check($actual === $expected, $kind . ' ' . $mode . ' save changed the credential incorrectly');
		if ($kind === 'domain' && $ldap_uri) {
			$ldap = ldap_connect($ldap_uri);
			ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
			// The disposable server rejects anonymous binds, including empty secrets.
			check(@ldap_bind($ldap, $domain['specific_dn'], $actual) === ($actual !== ''), 'Persisted LDAP credential could not bind as expected');
			ldap_unbind($ldap);
		}
	}
}

// Execute the complete settings save case, including the remote replication SQL.
check((bool) preg_match('/case \'save\':(.*?)case \'send_test\':/s', file_get_contents($root . '/settings.php'), $match), 'Missing settings save');
$temporary = tempnam(sys_get_temp_dir(), 'cacti-secret-settings-');
file_put_contents($temporary, '<?php namespace SecretCollectorSave; function settings_save() { global $settings, $config; switch (\'save\') { case \'save\':' . $match[1] . '}}');
try {
	require $temporary;
} finally {
	unlink($temporary);
}
$settings = ['mail' => ['settings_smtp_password' => ['method' => 'textbox_password']]];
$db->exec('DELETE FROM poller');
$db->exec("INSERT INTO poller (id, name, last_status) VALUES (2, 'Remote', NOW()), (3, 'Offline', '2000-01-01')");
foreach (['keep', 'replace', 'clear', 'mismatch'] as $mode) {
	$_SESSION = $replicated = [];
	foreach ([$db, $remote] as $connection) {
		$connection->exec("REPLACE INTO settings VALUES ('settings_smtp_password', 'stored-fixture')");
	}
	$value = $mode === 'replace' ? "replacement-'fixture" : '';
	$_REQUEST = ['tab' => 'mail', 'settings_smtp_password' => $value, 'settings_smtp_password_confirm' => $mode === 'mismatch' ? 'different' : $value];
	if ($mode === 'clear') {
		$_REQUEST['settings_smtp_password_clear'] = 'on';
	}
	try {
		\SecretCollectorSave\settings_save();
	} catch (\SecretCollectorSave\Redirect $redirect) {
	}
	$expected = $mode === 'replace' ? $value : ($mode === 'clear' ? '' : 'stored-fixture');
	foreach ([$db, $remote] as $connection) {
		check($connection->query("SELECT value FROM settings WHERE name='settings_smtp_password'")->fetchColumn() === $expected, 'Settings ' . $mode . ' persistence or replication failed');
	}
	check($replicated === ($mode === 'mismatch' ? [] : [2]), 'Settings replicated to an inactive collector or despite validation failure');
}
if ($ldap_uri) {
	ldap_unbind($ldap_admin);
}
print json_encode(['passed' => $passed]) . "\n";
}

namespace SecretCollectorSave {
class Redirect extends \RuntimeException {
}
function header($header, $replace = true, $response_code = 0) {
	throw new Redirect($header);
}
}

namespace SecretDomainSave {
function header($header, $replace = true, $response_code = 0) {
	throw new \SecretCollectorSave\Redirect($header);
}
}
