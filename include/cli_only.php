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

/* Keep this guard independent of global.php so lightweight launchers can
 * reject web requests without opening the database merely to show --help or
 * --version. Full legacy scripts continue through cli_check.php afterwards. */
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	print 'FATAL: This file can only be called from the command line.' . PHP_EOL;

	exit(1);
}

if (!defined('CACTI_CLI_ONLY')) {
	define('CACTI_CLI_ONLY', true);
}
