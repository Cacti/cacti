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

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($array) {
		return ($array === false || !is_array($array)) ? 0 : sizeof($array);
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $print = false, $tag = 'GENERAL', $level = 1) {
		// Silently ignore
	}
}

if (!function_exists('__')) {
	function __($text, ...$args) {
		return vsprintf($text, $args);
	}
}

if (!function_exists('__esc')) {
	function __esc($text, ...$args) {
		return vsprintf($text, $args);
	}
}

if (!function_exists('set_request_var')) {
	function set_request_var($name, $val) {
		$_REQUEST[$name] = $val;
	}
}

if (!function_exists('srv')) {
	function srv($name, $val) {
		$_REQUEST[$name] = $val;
	}
}

if (!function_exists('cacti_strtolower')) {
	function cacti_strtolower($string) {
		return mb_strtolower($string);
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($name) {
		return false;
	}
}

if (!function_exists('read_user_setting')) {
	function read_user_setting($name) {
		return '08:00';
	}
}

if (!function_exists('cacti_count')) {
	function cacti_count($array) {
		return ($array === false || !is_array($array)) ? 0 : count($array);
	}
}

if (!function_exists('clean_up_lines')) {
	// Production version strips comments; the unit form only trims whitespace.
	function clean_up_lines($s) {
		return trim((string)$s);
	}
}

if (!function_exists('raise_message')) {
	function raise_message($message_id, $message = '', $message_level = 0, $message_title = null) {
		return true;
	}
}

if (!function_exists('cacti_debug_backtrace')) {
	function cacti_debug_backtrace($entry = '', $html = false, $record = true, $limit = 0, $skip = 0) {
		return '';
	}
}

if (!function_exists('get_debug_prefix')) {
	function get_debug_prefix() {
		return '';
	}
}

// Constants required by lib/database.php logging paths and message helpers.
if (!defined('POLLER_VERBOSITY_DEBUG'))  { define('POLLER_VERBOSITY_DEBUG', 5); }
if (!defined('POLLER_VERBOSITY_DEVDBG')) { define('POLLER_VERBOSITY_DEVDBG', 6); }
if (!defined('MESSAGE_LEVEL_NONE'))      { define('MESSAGE_LEVEL_NONE', 0); }
if (!defined('MESSAGE_LEVEL_ERROR'))     { define('MESSAGE_LEVEL_ERROR', 3); }
if (!defined('POLLER_ID'))               { define('POLLER_ID', 1); }
