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

namespace CactiBulkPluginsHardeningProbe;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';
require_once $root . '/lib/functions.php';

$GLOBALS['bulk_plugin_realms_db'] = array();
$GLOBALS['bulk_plugin_logs']      = array();
$GLOBALS['bulk_plugin_hooks_run'] = array();
$GLOBALS['bulk_plugin_sql']       = array();

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_log($message, $output = false, $facility = '', $level = 0) {
	$GLOBALS['bulk_plugin_logs'][] = $facility . ': ' . $message;
}

function cacti_plugin_path($plugin, $relative = '') {
	return \cacti_plugin_path($plugin, $relative);
}

function cacti_path_is_within($path, $base) {
	return \cacti_path_is_within($path, $base);
}

function cacti_version_compare($v1, $v2, $op) {
	return version_compare($v1, $v2, $op);
}

function db_fetch_assoc($sql) {
	return $GLOBALS['bulk_plugin_realms_db'];
}

function db_table_exists($table) {
	return true;
}

function db_fetch_assoc_prepared($sql, $params = array(), $log = true) {
	$hook = isset($params[0]) ? $params[0] : '';
	return isset($GLOBALS['bulk_plugin_hooks_db'][$hook]) ? $GLOBALS['bulk_plugin_hooks_db'][$hook] : array();
}

function db_fetch_cell_prepared($sql, $params = array()) {
	return 0;
}

function db_execute_prepared($sql, $params = array()) {
	$GLOBALS['bulk_plugin_sql'][] = array($sql, $params);
	return true;
}

function debounce_run_notification($key) {
	return true;
}

function api_plugin_valid_entrypoint($plugin, $fn) {
	return true;
}

function api_plugin_replicate_config() {
}

function api_plugin_run_plugin_hook($name, $plugin_name, $plugin_func, $args) {
	$GLOBALS['bulk_plugin_hooks_run'][] = $plugin_name . ':' . $plugin_func;
}

function api_plugin_run_plugin_hook_function($name, $plugin_name, $function, $ret) {
	$GLOBALS['bulk_plugin_hooks_run'][] = $plugin_name . ':' . $function;
	return $function($ret);
}

function plugin_load_info_defaults($file, $info) {
	return $info;
}

function __($text) {
	$args = func_get_args();
	array_shift($args);
	return count($args) ? vsprintf($text, $args) : $text;
}

\cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_callback_is_allowed', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_hook', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_hook_function', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_get_dependencies', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_remote_capabilities', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_register_realm', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/plugins.php', 'api_plugin_load_realms', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/plugins.php', 'plugin_is_compatible', __NAMESPACE__);
\cacti_fixture_source_function($root . '/lib/plugins.php', 'plugin_load_info_file', __NAMESPACE__);

$tmpBase = sys_get_temp_dir() . '/cacti-plugin-probe-' . bin2hex(random_bytes(6));
mkdir($tmpBase . '/plugins/sample', 0700, true);
$pluginFile = $tmpBase . '/plugins/sample/setup.php';
$infoPath   = $tmpBase . '/plugins/sample/INFO';
$funcName   = 'cacti_sample_plugin_hook_' . bin2hex(random_bytes(4));
file_put_contents($pluginFile, "<?php\nfunction {$funcName}(\$x = null) { return \$x; }\n");
file_put_contents($infoPath, "[info]\nname = sample\nversion = 1.0\ncompat = 1.0.0\nrequires = \${PATH} thold:1.0\ncapabilities = online_view:1\n");
require_once $pluginFile;

$GLOBALS['config'] = array(
	'base_path'    => $tmpBase,
	'poller_id'    => 1,
	'is_web'       => false,
);
if (!defined('CACTI_VERSION')) {
	define('CACTI_VERSION', '1.2.30');
}

try {
	$cb_results = array(
		'internal_config'  => api_plugin_callback_is_allowed('internal', 'plugin_config_arrays'),
		'internal_nav'     => api_plugin_callback_is_allowed('internal', 'plugin_draw_navigation_text'),
		'internal_phpinfo' => api_plugin_callback_is_allowed('internal', 'phpinfo'),
		'internal_system'  => api_plugin_callback_is_allowed('internal', 'system'),
		'empty_fn'         => api_plugin_callback_is_allowed('sample', ''),
		'missing_fn'       => api_plugin_callback_is_allowed('sample', 'nonexistent_fn_xyz'),
		'bad_plugin_path'  => api_plugin_callback_is_allowed('../escape', $funcName),
		'php_internal'     => api_plugin_callback_is_allowed('sample', 'strlen'),
		'outside_user_fn'  => api_plugin_callback_is_allowed('sample', 'cacti_sizeof'),
		'valid_plugin_fn'  => api_plugin_callback_is_allowed('sample', $funcName),
	);

	// Exercise api_plugin_hook and api_plugin_hook_function with bad file, disallowed callback, and valid callback
	$GLOBALS['plugins_integrated'] = array();
	$GLOBALS['bulk_plugin_hooks_db'] = array(
		'test_hook' => array(
			array('name' => 'sample_bad_file', 'file' => '../escape.php', 'function' => $funcName),
			array('name' => 'sample', 'file' => 'setup.php', 'function' => 'strlen'),
		),
		'test_hook_ok' => array(
			array('name' => 'sample', 'file' => 'setup.php', 'function' => $funcName),
		),
	);
	api_plugin_hook('test_hook');
	api_plugin_hook('test_hook_ok');
	$fn_blocked = api_plugin_hook_function('test_hook', 'val');
	$fn_allowed = api_plugin_hook_function('test_hook_ok', 'val');

	// Exercise INFO parsing & compatibility & remote capabilities
	$deps_traversal = api_plugin_get_dependencies('../etc');
	$deps_raw       = api_plugin_get_dependencies('sample');
	$caps           = api_plugin_remote_capabilities('sample');
	$compat         = plugin_is_compatible('sample');
	$compat_bad     = plugin_is_compatible('../etc');

	// Exercise api_plugin_register_realm
	$GLOBALS['user_auth_realm_filenames'] = array(
		'host.php'    => 3,
		'plugins.php' => 101,
		'index.php'   => 8,
	);
	$reg_plugins_php = api_plugin_register_realm('sample', 'plugins.php', 'Bad');
	$reg_core_host   = api_plugin_register_realm('sample', 'host.php', 'Bad');
	$reg_index_php   = api_plugin_register_realm('sample', 'index.php', 'Index OK');
	$reg_own_file    = api_plugin_register_realm('sample', 'sample_page.php', 'Sample Page');

	// Exercise api_plugin_load_realms
	$GLOBALS['user_auth_realms'] = array();
	$GLOBALS['user_auth_realm_filenames'] = array(
		'host.php'    => 3,
		'plugins.php' => 101,
	);
	$GLOBALS['bulk_plugin_realms_db'] = array(
		array('id' => 1, 'plugin' => 'internal', 'file' => 'plugins.php', 'display' => 'Plugin Management', 'plugin_status' => null),
		array('id' => 10, 'plugin' => 'disabled_plug', 'file' => 'disabled_page.php', 'display' => 'Disabled Plugin', 'plugin_status' => 0),
		array('id' => 11, 'plugin' => 'enabled_a', 'file' => 'enabled_a.php, ,host.php', 'display' => 'Enabled A', 'plugin_status' => 1),
		array('id' => 12, 'plugin' => 'enabled_b', 'file' => 'enabled_a.php,enabled_b.php', 'display' => 'Enabled B', 'plugin_status' => 1),
	);
	$GLOBALS['bulk_plugin_logs'] = array();
	api_plugin_load_realms();
	$logs_first = count($GLOBALS['bulk_plugin_logs']);
	api_plugin_load_realms();
	$logs_second = count($GLOBALS['bulk_plugin_logs']);

	print json_encode(array(
		'cb'              => $cb_results,
		'fn_blocked'      => $fn_blocked,
		'fn_allowed'      => $fn_allowed,
		'deps_traversal'  => $deps_traversal,
		'deps_raw'        => $deps_raw,
		'caps'            => $caps,
		'compat'          => $compat,
		'compat_bad'      => $compat_bad,
		'reg_plugins_php' => $reg_plugins_php,
		'reg_core_host'   => $reg_core_host,
		'reg_index_php'   => $reg_index_php,
		'reg_own_file'    => $reg_own_file,
		'realms'          => $GLOBALS['user_auth_realms'],
		'realm_files'     => $GLOBALS['user_auth_realm_filenames'],
		'logs_first'      => $logs_first,
		'logs_second'     => $logs_second,
	), JSON_THROW_ON_ERROR);
} finally {
	@unlink($infoPath);
	@unlink($pluginFile);
	@rmdir($tmpBase . '/plugins/sample');
	@rmdir($tmpBase . '/plugins');
	@rmdir($tmpBase);
}
