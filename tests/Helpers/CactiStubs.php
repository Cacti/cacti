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
 * Marks the process as a test run.
 *
 * Nothing on 1.2.x reads PHP_TESTING or CACTI_TEST_BOOTSTRAP, so neither one
 * suppresses a database connection here. The unit tests avoid the database by
 * requiring individual lib files instead of include/global.php. Both are set
 * for parity with develop, where include/global.php and lib/database.php gate
 * their connection short-circuit on the pair.
 */

if (!defined('PHP_TESTING')) {
	define('PHP_TESTING', true);
}

putenv('CACTI_TEST_BOOTSTRAP=1');
$_ENV['CACTI_TEST_BOOTSTRAP'] = '1';
