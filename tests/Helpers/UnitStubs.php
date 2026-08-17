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
// elsewhere in the codebase. By NOT stubbing them, we avoid function redeclaration errors.
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
// - set_request_var (lib/html_utility.php - wrapper around srv)
// - srv (lib/html_utility.php line 754)
// 
// NOTE: The following constants are NOT stubbed here because they are defined
// in include/global_constants.php (loaded via global.php):
// - POLLER_VERBOSITY_DEBUG (include/global_constants.php line 164)
// - POLLER_VERBOSITY_DEVDBG (include/global_constants.php line 165)
// - MESSAGE_LEVEL_NONE (include/global_constants.php line 499)
// - MESSAGE_LEVEL_ERROR (include/global_constants.php line 502)
// - POLLER_ID (include/global.php line 161-165)

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
