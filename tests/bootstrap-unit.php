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

// Load global configuration and settings
// This includes:
// - include/global_path.php - path constants
// - include/global_constants.php - version and logging constants
// - lib/database.php - database functions
// - lib/functions.php - core library functions (called by format_cacti_version on lines 293-294)
// - lib/renderer.php - rendering functions
// - Various other configuration includes
// Uses __() from stubs above, and defines format_cacti_version via lib/functions.php
require_once __DIR__ . '/../include/global.php';
