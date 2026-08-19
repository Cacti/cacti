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
// - cacti_sizeof, cacti_log, cacti_strtolower, read_config_option, read_user_setting,
//   cacti_count, raise_message, clean_up_lines, cacti_debug_backtrace, get_debug_prefix
//   (all defined in lib/functions.php)
// - set_request_var, srv (lib/html_utility.php)
// - __esc (include/global_languages.php line 595)
//
// NOTE: The following constants are NOT stubbed here because they are defined
// in include/global_constants.php and include/global.php:
// - POLLER_VERBOSITY_DEBUG, POLLER_VERBOSITY_DEVDBG, MESSAGE_LEVEL_NONE, MESSAGE_LEVEL_ERROR
// - POLLER_ID

// Runtime functions come from Cacti itself. Per-test doubles belong in the
// test file's namespace so the unit bootstrap never replaces production APIs.
