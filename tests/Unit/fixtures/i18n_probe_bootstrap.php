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

// Shared isolated-process setup for probes that load the language helpers.
$root = dirname(__DIR__, 3);

define('CACTI_PATH_INCLUDE', $root . '/include');
define('CACTI_PATH_BASE', $root);
define('CACTI_LANGUAGE_HANDLER_DEFAULT', 0);
define('SESS_USER_LANGUAGE', 'sess_user_language');

$i18n_support            = $argv[1] ?? '0';
$GLOBALS['i18n_support'] = $i18n_support;

$config = ['base_path' => $root, 'include_path' => $root . '/include'];

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
