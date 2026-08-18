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
 * We set a testing variable such that Cacti will not attempt
 * to connect to a database when the tests are called, or to call
 * any function that will result in the failure due to the lack
 * of a real database connection.
 */

define('PHP_TESTING', true);

// Arm the combined test-bootstrap gate in include/global.php. PHP_TESTING alone
// no longer engages the DB short-circuit; CACTI_TEST_BOOTSTRAP must also be set
// so a stray define in production cannot bypass real connection logic.
putenv('CACTI_TEST_BOOTSTRAP=1');
$_ENV['CACTI_TEST_BOOTSTRAP'] = '1';
