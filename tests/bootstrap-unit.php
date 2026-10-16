<?php
/*
 * Shared bootstrap for the unit test suite.
 *
 * Many unit tests only scan source with file_get_contents(), but others load
 * library files directly, so global.php (which needs a database) is skipped
 * while the handful of load-time prerequisites those libraries expect are
 * provided below. This keeps the whole tests/Unit tree runnable in one process
 * without connecting to a database.
 */
require_once __DIR__ . '/vendor/autoload.php';

/* Library-only tests use cached options without connecting to a database. */
$GLOBALS['config'] = array('is_web' => false, 'config_options_array' => array('storage_location' => 0));

/*
 * Some library files (e.g. lib/snmp.php) read runtime capability flags from
 * $config at include time. global.php normally sets these, but it is skipped
 * here, so mirror the flags the libraries need to be loadable. Without this,
 * including lib/snmp.php raises "Undefined array key php_snmp_support", which
 * the strict test error handler turns into a fatal.
 */
$GLOBALS['config']['php_snmp_support'] = (!function_exists('snmpget')) ? false : class_exists('SNMP');

require_once dirname(__DIR__) . '/include/global_constants.php';
require_once dirname(__DIR__) . '/lib/functions.php';

/*
 * Load lib/html.php so its helpers (html_escape(), etc.) are defined before any
 * test runs. Without this, a test that defines a function_exists()-guarded stub
 * for one of these helpers would define it first, and a later test that includes
 * lib/html.php would hit an unguarded redeclaration fatal. Loading it here (like
 * lib/functions.php above) makes those guarded stubs no-ops, matching production.
 */
require_once dirname(__DIR__) . '/lib/html.php';
