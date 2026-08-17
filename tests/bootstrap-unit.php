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

// Load Composer autoloader first
require_once __DIR__ . '/../include/vendor/autoload.php';

// Load CactiStubs first - it defines PHP_TESTING and CACTI_TEST_BOOTSTRAP
require_once __DIR__ . '/Helpers/CactiStubs.php';

// Load test stubs to provide basic functions needed by global.php
// These stubs include translation functions (__), test helpers, etc.
// Conflicting functions (cacti_strtolower, read_user_setting, etc.) are
// intentionally NOT stubbed here so lib/functions.php can provide them
// without redeclaration errors.
require_once __DIR__ . '/Helpers/UnitStubs.php';

// Load global configuration and settings (uses __() from stubs above)
require_once __DIR__ . '/../include/global.php';

// Load core library functions
// Since UnitStubs no longer defines cacti_strtolower, read_user_setting, etc.,
// lib/functions.php can define them without conflicts.
require_once __DIR__ . '/../lib/functions.php';
