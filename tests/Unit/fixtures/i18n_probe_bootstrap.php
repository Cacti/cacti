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
 * Shared isolated-process setup for probes that load the language helpers.
 *
 * argv[1] is the i18n_language_support setting. argv[2], when given, is the
 * language the session holds, and argv[3] the configured theme, which the real
 * get_selected_theme() then resolves.
 *
 * No shipped theme declares right-to-left support, so the theme that does is
 * 'rtl-fixture' under tests/fixtures/Ui/RtlTheme. Selecting it makes that
 * directory stand in for include/ in the theme lookups; the language helpers
 * themselves always load from the real include/.
 */
$root = dirname(__DIR__, 3);

$i18n_support            = $argv[1] ?? '0';
$i18n_theme              = $argv[3] ?? '';
$GLOBALS['i18n_support'] = $i18n_support;

define('CACTI_PATH_INCLUDE', $i18n_theme === 'rtl-fixture' ? dirname(__DIR__, 2) . '/fixtures/Ui/RtlTheme' : $root . '/include');
define('CACTI_PATH_BASE', $root);
define('CACTI_LANGUAGE_HANDLER_DEFAULT', 0);
define('CACTI_LANGUAGE_HANDLER_PHPGETTEXT', 1);
define('CACTI_LANGUAGE_HANDLER_MOTRANSLATOR', 3);
define('SESS_USER_LANGUAGE', 'sess_user_language');
define('SESS_USER_ID', 'sess_user_id');

$config = ['base_path' => $root, 'include_path' => $root . '/include'];

if (isset($argv[2])) {
	$_SESSION[SESS_USER_LANGUAGE] = $argv[2];
}

if ($i18n_theme !== '') {
	$GLOBALS['i18n_probe_options']['selected_theme'] = $i18n_theme;

	require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
	cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_is_theme_name', '');
	cacti_fixture_source_function($root . '/lib/functions.php', 'get_selected_theme', '');
}

function read_config_option($name, $force = false) {
	return $name === 'i18n_language_support' ? $GLOBALS['i18n_support'] : ($GLOBALS['i18n_probe_options'][$name] ?? '');
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

function db_table_exists($table, $log = true) {
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

require_once $root . '/include/global_languages.php';

// What each document scenario must render, keyed by 'language|theme'.
$i18n_document_attributes = [
	'|'                 => " lang='en-US' dir='ltr'",
	'he-IL|rtl-fixture' => " lang='he-IL' dir='rtl'",
	'he-IL|modern'      => " lang='he-IL' dir='ltr'",
	'xx-XX|modern'      => " lang='en-US' dir='ltr'",
][($argv[2] ?? '') . '|' . $i18n_theme] ?? null;
