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

// NOTE: The following functions are NOT stubbed here because they are defined
// in lib/functions.php. By NOT stubbing them, we allow lib/functions.php to be
// loaded without function redeclaration errors.
// - cacti_sizeof (lib/functions.php line 9129)
// - cacti_log (lib/functions.php line 1504)
// - cacti_strtolower (lib/functions.php line 9152)
// - read_config_option (lib/functions.php line 685)
// - read_user_setting (lib/functions.php line 340)
// - cacti_count (lib/functions.php line 9135)
// - raise_message (lib/functions.php line 1129)
// - clean_up_lines (lib/functions.php line 3234)
// - cacti_debug_backtrace (lib/functions.php line 6687)
// - get_debug_prefix (lib/functions.php line 8898)
// 
// NOTE: The POLLER_ID constant is NOT stubbed here because it is defined
// unconditionally by global.php at line 161-165, and defining it here would
// cause a redeclaration warning when global.php loads.

// Only define stubs for translation functions (not in lib/functions.php)
if (!function_exists(__NAMESPACE__ . '\\__') && !function_exists('\\__')) {
	function __($text, ...$args) {
		return vsprintf($text, $args);
	}
}

if (!function_exists(__NAMESPACE__ . '\\__esc') && !function_exists('\\__esc')) {
	function __esc($text, ...$args) {
		return vsprintf($text, $args);
	}
}

// Test-specific helper functions
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

// Constants required by lib/database.php logging paths and message helpers.
// These must be defined before lib/database.php loads, but after POLLER_ID
// (which is defined unconditionally by global.php and doesn't need stubbing).
if (!defined('POLLER_VERBOSITY_DEBUG'))  { define('POLLER_VERBOSITY_DEBUG', 5); }
if (!defined('POLLER_VERBOSITY_DEVDBG')) { define('POLLER_VERBOSITY_DEVDBG', 6); }
if (!defined('MESSAGE_LEVEL_NONE'))      { define('MESSAGE_LEVEL_NONE', 0); }
if (!defined('MESSAGE_LEVEL_ERROR'))     { define('MESSAGE_LEVEL_ERROR', 3); }
