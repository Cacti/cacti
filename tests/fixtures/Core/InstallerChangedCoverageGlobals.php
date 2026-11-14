<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// Global stand-ins for the database and installer APIs that unmodified
// production fragments call from the global namespace. A function that is
// already defined is kept, so loading this file never replaces a real API.

if (!function_exists('db_install_add_column')) {
	function db_install_add_column(...$args) {
	}
}

if (!function_exists('db_install_add_key')) {
	function db_install_add_key(...$args) {
	}
}

if (!function_exists('db_install_execute')) {
	function db_install_execute(...$args) {
	}
}

if (!function_exists('db_install_change_column')) {
	function db_install_change_column(...$args) {
	}
}

if (!function_exists('db_table_exists')) {
	function db_table_exists($table) {
		return false;
	}
}

if (!function_exists('db_column_exists')) {
	function db_column_exists($table, $column) {
		return true;
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql, ...$args) {
		return '';
	}
}

if (!function_exists('unregister_process')) {
	function unregister_process(...$args) {
	}
}

if (!function_exists('install_verify_schema_columns')) {
	function install_verify_schema_columns() {
		return '';
	}
}

if (!function_exists('install_verify_authentication')) {
	function install_verify_authentication() {
		return '';
	}
}

if (!function_exists('db_install_errors')) {
	function db_install_errors($version) {
		return 3;
	}
}

if (!function_exists('cacti_version_compare')) {
	function cacti_version_compare($a, $b, $op) {
		return version_compare((string) $a, (string) $b, $op);
	}
}

if (!function_exists('get_cacti_version_text')) {
	function get_cacti_version_text($long, $version) {
		return (string) $version;
	}
}

if (!function_exists('get_cacti_db_version')) {
	function get_cacti_db_version() {
		return '1.2.22';
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql, ...$args) {
		return ['row_count' => 1, 'cacti' => '1.3.0'];
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($name, $force = false) {
		return $GLOBALS['cacti_db']['settings'][$name] ?? '';
	}
}

if (!function_exists('set_config_option')) {
	function set_config_option($name, $value) {
		$GLOBALS['cacti_db']['settings'][$name] = $value;
	}
}

if (!function_exists('log_install_always')) {
	function log_install_always($section, $text) {
	}
}

if (!function_exists('log_install_high')) {
	function log_install_high($section, $text) {
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($text, ...$args) {
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = []) {
		return true;
	}
}

if (!function_exists('is_realm_allowed')) {
	function is_realm_allowed($realm) {
		return !empty($GLOBALS['cacti_realm_allowed']);
	}
}

if (!function_exists('__')) {
	function __($text, ...$args) {
		return $args !== [] ? vsprintf($text, $args) : $text;
	}
}
