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

/*
 * Probe for I18nFallbackTranslateTest. Run through cacti_test_isolated_probe();
 * prints a JSON verdict on stdout.
 *
 * Loads include/global_languages.php with i18n support switched off, which is
 * the path that returns early to the fallback locale, and reports whether the
 * translation API survived the trip. It needs its own process because the
 * fakes below carry names lib/ declares.
 *
 * argv[1] is the value read_config_option() reports for i18n_language_support.
 */

$root = dirname(__DIR__, 3);

define('CACTI_PATH_INCLUDE', $root . '/include');
define('CACTI_PATH_BASE', $root);
define('CACTI_LANGUAGE_HANDLER_DEFAULT', 0);
define('SESS_USER_LANGUAGE', 'sess_user_language');

$i18n_support = $argv[1] ?? '0';

$config = ['base_path' => $root, 'include_path' => $root . '/include'];

function read_config_option($name, $force = false) {
	return $name === 'i18n_language_support' ? $GLOBALS['i18n_support'] : '';
}

function cacti_sizeof($array) {
	return ($array === false || !is_array($array)) ? 0 : sizeof($array);
}

function cacti_count($array) {
	return cacti_sizeof($array);
}

function db_fetch_assoc($sql, $log = true) {
	return [];
}

function db_fetch_cell($sql, $col = '', $log = true) {
	return false;
}

function isempty_request_var($name) {
	return true;
}

function set_request_var($name, $value) {
}

function get_request_var($name, $default = '') {
	return $default;
}

function grv($name, $default = '') {
	return $default;
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = 0) {
}

function cacti_strtoupper($string) {
	return strtoupper((string) $string);
}

function cacti_strtolower($string) {
	return strtolower((string) $string);
}

require_once CACTI_PATH_INCLUDE . '/global_languages.php';

print json_encode([
	'i18n_support'     => $i18n_support,
	'translate_exists' => function_exists('__'),
	'esc_exists'       => function_exists('__esc'),
	'gettext_exists'   => function_exists('__gettext'),
	'translated'       => function_exists('__') ? __('Version %s', '1.3.0') : null,
]);
