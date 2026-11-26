<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace CactiBulkDevicesAndInstallerHardeningProbe;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';
require_once $root . '/lib/functions.php';
require_once $root . '/lib/ping.php';

if (!defined('SNMP_METHOD_PHP')) {
	define('SNMP_METHOD_PHP', 1);
}
if (!defined('SNMP_METHOD_BINARY')) {
	define('SNMP_METHOD_BINARY', 2);
}
if (!defined('SNMP_STRING_OUTPUT_GUESS')) {
	define('SNMP_STRING_OUTPUT_GUESS', 1);
}
if (!defined('SNMP_STRING_OUTPUT_ASCII')) {
	define('SNMP_STRING_OUTPUT_ASCII', 2);
}
if (!defined('SNMP_STRING_OUTPUT_HEX')) {
	define('SNMP_STRING_OUTPUT_HEX', 3);
}
if (!defined('SNMP_POLLER')) {
	define('SNMP_POLLER', 0);
}
if (!defined('SNMP_WEBUI')) {
	define('SNMP_WEBUI', 1);
}
if (!defined('REGEXP_SNMP_TRIM')) {
	define('REGEXP_SNMP_TRIM', '/^(?:hex|counter(?:32|64)|gauge(?:32|64)?|float|ipaddress|string|integer):\s*/i');
}

$GLOBALS['bulk_exec_calls']  = array();
$GLOBALS['bulk_bg_calls']    = array();
$GLOBALS['bulk_logs']        = array();
$GLOBALS['bulk_messages']    = array();
$GLOBALS['bulk_sql']         = array();
$GLOBALS['bulk_headers']     = array();
$GLOBALS['bulk_req']         = array();
$GLOBALS['bulk_php_binary']  = PHP_BINARY;
$GLOBALS['bulk_db_exec_ret'] = true;

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_count($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_log($message, $output = false, $facility = '', $level = 0) {
	$GLOBALS['bulk_logs'][] = $facility . ': ' . $message;
}

function cacti_plugin_path($plugin, $relative = '') {
	return \cacti_plugin_path($plugin, $relative);
}

function cacti_redact_sensitive($items) {
	$out = $items;
	if (isset($out['snmp_password'])) {
		$out['snmp_password'] = 'REDACTED';
	}
	return $out;
}

function cacti_exec($binary, $args, &$output = null, $host = '', $timeout = 0) {
	$GLOBALS['bulk_exec_calls'][] = array($binary, $args);
	if (is_array($args) && in_array('--info', $args, true)) {
		$output = array('{"author":"Test","description":"Sample"}');
		return 0;
	}
	if (is_array($args) && isset($args[1]) && strpos($args[1], 'cli_test.php') !== false) {
		$n = (int) $args[2];
		$output = array($GLOBALS['bulk_cli_test_bad'] ? '0' : (string) ($n * $n));
		return 0;
	}
	$output = array('Converting table Successful');
	return 0;
}

function exec_background_process($binary, $args) {
	$GLOBALS['bulk_bg_calls'][] = array($binary, $args);
}

function read_config_option($name, $force = false) {
	if ($name === 'path_php_binary') {
		return $GLOBALS['bulk_php_binary'];
	}
	if ($name === 'path_snmpget' || $name === 'path_snmpgetnext' || $name === 'path_snmpwalk' || $name === 'path_snmpbulkwalk') {
		return PHP_BINARY;
	}
	if ($name === 'path_snmptrap') {
		return '/usr/bin/snmptrap';
	}
	if ($name === 'oid_increasing_check_disable') {
		return '';
	}
	return '';
}

function __($text) {
	$args = func_get_args();
	array_shift($args);
	return count($args) ? vsprintf($text, $args) : $text;
}

function html_escape($text) {
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function raise_message($id, $msg = '', $level = 0) {
	$GLOBALS['bulk_messages'][] = $id;
}

function is_error_message() {
	return !empty($_SESSION['sess_error_fields']);
}

function csrf_require_post() {
}

function isset_request_var($name) {
	return isset($GLOBALS['bulk_req'][$name]);
}

function isempty_request_var($name) {
	return empty($GLOBALS['bulk_req'][$name]);
}

function set_request_var($name, $val) {
	$GLOBALS['bulk_req'][$name] = $val;
}

function save_user_setting($name, $val) {
}

function html_start_box($title = '', $size = '', $div = '', $cols = '', $align = '', $add = '') {
}

function html_end_box() {
}

function form_selectable_cell($v, $id = '') {
}

function form_alternate_row($id = '', $hl = false) {
}

function form_end_row() {
}

function draw_edit_form($a = array()) {
}

function form_save_button($u = '') {
}

function api_device_save_noop() {
}

function cacti_php_version_noop() {
}

function get_request_var($name) {
	return isset($GLOBALS['bulk_req'][$name]) ? $GLOBALS['bulk_req'][$name] : '';
}

function get_filter_request_var($name, $filter = 0, $opts = array()) {
	return isset($GLOBALS['bulk_req'][$name]) ? $GLOBALS['bulk_req'][$name] : '';
}

function get_nfilter_request_var($name) {
	return isset($GLOBALS['bulk_req'][$name]) ? $GLOBALS['bulk_req'][$name] : '';
}

function form_input_validate($val, $name, $regex, $allow_empty, $msg) {
	return $val;
}

function sanitize_unserialize_selected_items($raw) {
	return $raw;
}

function array_to_sql_or($items, $col) {
	return $col . ' IN (' . implode(',', array_map('intval', $items)) . ')';
}

function header($str) {
	$GLOBALS['bulk_headers'][] = $str;
}

function db_execute($sql, $log = true, $db_conn = false) {
	$GLOBALS['bulk_sql'][] = $sql;
	return $GLOBALS['bulk_db_exec_ret'];
}

function db_execute_prepared($sql, $params = array()) {
	$GLOBALS['bulk_sql'][] = $sql;
	return true;
}

function db_fetch_assoc($sql) {
	if (strpos($sql, 'SELECT DISTINCT host_template_id') !== false) {
		return array(array('host_template_id' => 10));
	}
	if (strpos($sql, 'install_table_') !== false) {
		return array(array('name' => 'install_table_1', 'value' => 'host'));
	}
	return array();
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	if (strpos($sql, 'FROM snmpagent_managers_notifications') !== false) {
		return array(
			array('id' => 1, 'hostname' => '192.0.2.1', 'snmp_port' => 162, 'snmp_version' => 1, 'snmp_community' => 'pub', 'snmp_message_type' => 1),
			array('id' => 2, 'hostname' => '192.0.2.2', 'snmp_port' => 162, 'snmp_version' => 2, 'snmp_community' => 'pub', 'snmp_message_type' => 2),
			array('id' => 3, 'hostname' => '192.0.2.3', 'snmp_port' => 162, 'snmp_version' => 3, 'snmp_engine_id' => '0x8000', 'snmp_message_type' => 2, 'snmp_username' => 'u', 'snmp_password' => 'p', 'snmp_auth_protocol' => 'SHA', 'snmp_priv_passphrase' => 'x', 'snmp_priv_protocol' => 'AES'),
		);
	}
	if (strpos($sql, 'FROM snmpagent_cache_notifications') !== false) {
		return array();
	}
	if (strpos($sql, 'FROM host_graph') !== false) {
		return array(array('graph_template_id' => 1));
	}
	return array();
}

function db_fetch_cell($sql) {
	return '';
}

function db_fetch_cell_prepared($sql, $params = array()) {
	if (strpos($sql, 'FROM snmpagent_cache') !== false) {
		return '.1.3.6.1.4.1.12345.1';
	}
	if (strpos($sql, 'FROM host_template') !== false) {
		return 1;
	}
	if (strpos($sql, 'FROM host') !== false) {
		return 1;
	}
	return 1;
}

function db_fetch_row($sql) {
	return array();
}

function db_fetch_row_prepared($sql, $params = array()) {
	return array('id' => 1, 'name' => 'Default', 'step' => 300, 'heartbeat' => 600);
}

function db_get_table_column_types($table, $db_conn = false) {
	return array(
		'id'            => array('type' => 'int'),
		'snmp_password' => array('type' => 'varchar'),
	);
}

function db_qstr($val, $db_conn = false) {
	return "'" . addslashes((string) $val) . "'";
}

function db_fetch_insert_id($db_conn = false) {
	return 1;
}

function db_table_exists($table) {
	return true;
}

function plugin_load_info_file($file) {
	return array('name' => 'sample');
}

function snmp_get_method($version) {
	return SNMP_METHOD_BINARY;
}

function cacti_format_ipv6_colon($host) {
	return $host;
}

function cacti_get_snmp_auth_args($version, $community, $auth_proto, $auth_user, $auth_pass, $priv_proto, $priv_pass, $context, $engineid) {
	return array('-c', $community);
}

function snmp_format_target_arg($hostname, $port) {
	return $hostname . ':' . $port;
}

function cacti_snmp_debug_command($binary, $args) {
}

function cacti_snmp_command_timeout($timeout_s, $retries) {
	return 10;
}

function format_snmp_string($str, $inc = false, $fmt = 0) {
	return $str;
}

function snmp_escape_string($str) {
	return $str;
}

function cacti_snmp_options_sanitize($version, $community, $port, $timeout, $retries, $max_oids, $auth_proto = '', $auth_user = '', $auth_pass = '', $priv_proto = '', $priv_pass = '', $context = '', $engineid = '') {
	return true;
}

function cacti_safe_column_name($col) {
	return $col;
}

function db_fetch_cell_prepared_noop() {
}

function clean_up_lines($str) {
	return $str;
}

function api_plugin_hook_function($hook, $data) {
	return $data;
}

function extension_loaded($ext) {
	return false;
}

\cacti_fixture_source_function($root . '/lib/snmpagent.php', 'snmpagent_get_pluginslist', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/snmpagent.php', 'snmpagent_notification', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/snmp.php', 'cacti_snmp_get', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/snmp.php', 'cacti_snmp_get_raw', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/snmp.php', 'cacti_snmp_getnext', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/snmp.php', 'cacti_snmp_walk', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/database.php', '_db_replace', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/database.php', 'sql_save', __NAMESPACE__);
\cacti_fixture_source_function($root . '/host_templates.php', 'form_actions', __NAMESPACE__);
\cacti_fixture_source_function($root . '/managers.php', 'form_save', __NAMESPACE__);
\cacti_fixture_source_function($root . '/install/functions.php', 'install_setup_get_templates', __NAMESPACE__);

$mode = isset($argv[1]) ? $argv[1] : 'main';

if ($mode === 'host_templates_delete') {
	register_shutdown_function(static function () {
		print json_encode(array(
			'messages' => $GLOBALS['bulk_messages'],
			'sql'      => $GLOBALS['bulk_sql'],
			'headers'  => $GLOBALS['bulk_headers'],
		), JSON_THROW_ON_ERROR);
	});

	$GLOBALS['bulk_req'] = array(
		'drp_action'     => '1',
		'selected_items' => array(10, 20),
	);
	form_actions();
	exit;
}

$tmpDir = sys_get_temp_dir() . '/cacti-dev-probe-' . bin2hex(random_bytes(6));
mkdir($tmpDir . '/plugins/sample', 0700, true);
mkdir($tmpDir . '/install/templates', 0700, true);
file_put_contents($tmpDir . '/poller.php', "<?php\n");
file_put_contents($tmpDir . '/plugins/sample/setup.php', "<?php\n");
file_put_contents($tmpDir . '/plugins/sample/INFO', "[info]\nname=sample\n");
file_put_contents($tmpDir . '/install/templates/test_pkg.xml.gz', 'pkg');

$GLOBALS['config'] = array(
	'base_path'       => $tmpDir,
	'library_path'    => $tmpDir,
	'cacti_server_os' => 'unix',
	'is_web'          => false,
);

try {
	// 1. Net_Ping::is_ipaddress
	$ping = new \Net_Ping();
	$ping_res = array(
		'ipv4'       => $ping->is_ipaddress('192.0.2.1'),
		'ipv6'       => $ping->is_ipaddress('2001:db8::1'),
		'ipv6_zone'  => $ping->is_ipaddress('fe80::1%eth0'),
		'ipv6_dot'   => $ping->is_ipaddress('fe80::1%eth0.10'),
		'ipv4_zone'  => $ping->is_ipaddress('127.0.0.1%eth0'),
		'sqli_zone'  => $ping->is_ipaddress("fe80::1%' OR 1=1--"),
		'cmd_zone'   => $ping->is_ipaddress('fe80::1%eth0;id'),
		'empty_zone' => $ping->is_ipaddress('fe80::1%'),
		'empty'      => $ping->is_ipaddress(''),
	);

	// 2. lib/snmpagent.php
	$GLOBALS['plugins_integrated'] = array();
	$GLOBALS['snmpagent_event_severity'] = array(1 => 'low', 2 => 'medium', 3 => 'high', 4 => 'critical');
	$plugins_list = snmpagent_get_pluginslist();
	snmpagent_notification('cactiNotify', 'CACTI-MIB', array(), SNMPAGENT_EVENT_SEVERITY_MEDIUM);

	// 3. lib/snmp.php (get, get_raw, getnext, walk v1 + bulkwalk v2)
	cacti_snmp_get('192.0.2.1', 'public', '.1.3.6.1.2.1.1.1.0', 2);
	cacti_snmp_get_raw('192.0.2.1', 'public', '.1.3.6.1.2.1.1.1.0', 2);
	cacti_snmp_getnext('192.0.2.1', 'public', '.1.3.6.1.2.1.1.1.0', 2);
	cacti_snmp_walk('192.0.2.1', 'public', '.1.3.6.1.2.1.1', 1);
	cacti_snmp_walk('192.0.2.1', 'public', '.1.3.6.1.2.1.1', 2, '', '', '', '', '', '', 161, 500, 0, 10);

	// 4. lib/database.php (_db_replace failure + sql_save redaction)
	$fakeConn = new \stdClass();
	$GLOBALS['database_hostname'] = 'localhost';
	$GLOBALS['database_port']     = '3306';
	$GLOBALS['database_default']  = 'cacti';
	$GLOBALS['database_sessions'] = array('localhost:3306:cacti' => $fakeConn);
	$GLOBALS['bulk_db_exec_ret'] = false;
	_db_replace($fakeConn, 'host', array('id' => 1, 'snmp_password' => "'secret'"), 'id');
	$GLOBALS['bulk_db_exec_ret'] = true;
	sql_save(array('id' => 1, 'snmp_password' => 'secret'), 'host');

	// 5. managers.php form_save hostname guard
	$_SESSION['sess_error_fields'] = array();
	$GLOBALS['bulk_req'] = array(
		'save_component_manager' => 1,
		'id' => 0, 'description' => 'Mgr', 'hostname' => '-badhost',
		'disabled' => '', 'max_log_size' => 100, 'snmp_version' => 2,
		'snmp_community' => 'pub', 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_engine_id' => '', 'snmp_port' => 162, 'snmp_message_type' => 1,
	);
	form_save();
	$mgr_hostname_err = isset($_SESSION['sess_error_fields']['hostname']);

	// 6. install/functions.php install_setup_get_templates
	$GLOBALS['bulk_php_binary'] = PHP_BINARY;
	$templates = install_setup_get_templates();

	print json_encode(array(
		'ping'             => $ping_res,
		'plugins_list'     => array_keys($plugins_list),
		'bg_calls'         => $GLOBALS['bulk_bg_calls'],
		'exec_calls_count' => count($GLOBALS['bulk_exec_calls']),
		'logs'             => $GLOBALS['bulk_logs'],
		'mgr_hostname_err' => $mgr_hostname_err,
		'templates_count'  => count($templates),
	), JSON_THROW_ON_ERROR);
} finally {
	@unlink($tmpDir . '/install/templates/test_pkg.xml.gz');
	@rmdir($tmpDir . '/install/templates');
	@rmdir($tmpDir . '/install');
	@unlink($tmpDir . '/plugins/sample/INFO');
	@unlink($tmpDir . '/plugins/sample/setup.php');
	@rmdir($tmpDir . '/plugins/sample');
	@rmdir($tmpDir . '/plugins');
	@unlink($tmpDir . '/poller.php');
	@rmdir($tmpDir);
}
