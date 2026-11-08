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
 * The Script Server test bootstrap, plus output from inside include/global.php,
 * as a plugin print or a stray byte after a closing tag in config.php makes.
 */

require_once __DIR__ . '/script_server_bootstrap.php';

/**
 * Replaces the global.php helper, which is only defined when missing, so that
 * output is printed while include/cli_check.php loads Cacti.
 *
 * @return bool True, as the test bootstrap is active
 */
function cacti_is_test_bootstrap() : bool {
	print 'bootstrap-noise';

	return true;
}
