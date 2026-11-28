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

define('POLLER_VERBOSITY_NONE', 1);
define('POLLER_VERBOSITY_LOW', 2);
define('POLLER_VERBOSITY_MEDIUM', 3);
define('POLLER_VERBOSITY_HIGH', 4);
define('POLLER_VERBOSITY_DEBUG', 5);
define('POLLER_VERBOSITY_DEVDBG', 6);
define('POLLER_LOG_LEVEL', POLLER_VERBOSITY_DEBUG);

function get_selective_log_level() : int {
	return -1;
}

function read_config_option(string $name) : int {
	return POLLER_VERBOSITY_DEBUG;
}

require_once dirname(__DIR__, 2) . '/lib/poller.php';
require_once dirname(__DIR__, 2) . '/lib/script_server.php';

$valid = cacti_effective_log_level() === POLLER_VERBOSITY_DEBUG
	&& script_server_debug_logging_enabled();
$guard = script_server_start_output_guard();
$valid = $valid
	&& $guard > 0
	&& script_server_start_output_guard() === $guard
	&& !script_server_reset_output_guard(0)
	&& !script_server_reset_output_guard($guard + 1);

exit($valid ? 0 : 1);
