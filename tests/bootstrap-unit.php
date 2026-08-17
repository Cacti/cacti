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
 * Lightweight source-scan bootstrap for unit tests.
 * Loads only what's necessary to support test execution without
 * database dependencies.
 */

// Set test mode flags before any includes
define('PHP_TESTING', true);
putenv('CACTI_TEST_BOOTSTRAP=1');
$_ENV['CACTI_TEST_BOOTSTRAP'] = '1';

// Load Composer autoloader
require_once __DIR__ . '/../include/vendor/autoload.php';

// Load test stubs BEFORE global.php so stub functions like __() are available
// for use by include/global.php and lib/functions.php
require_once __DIR__ . '/Helpers/CactiStubs.php';
require_once __DIR__ . '/Helpers/UnitStubs.php';

// Load global configuration and settings
// This includes database defaults and test-mode gating via CACTI_TEST_BOOTSTRAP
// Now that stubs are loaded, global.php functions that depend on __() will work
require_once __DIR__ . '/../include/global.php';

// Load core library functions early so test stubs can detect
// and skip re-declaring them via function_exists() checks
require_once __DIR__ . '/../lib/functions.php';
