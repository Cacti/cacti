<?php
/*
 * Minimal bootstrap for source-scan unit tests.
 * These tests only call file_get_contents() on PHP source files and
 * never touch the database, so we skip global.php entirely.
 */
require_once __DIR__ . '/vendor/autoload.php';

/* Library-only tests use cached options without connecting to a database. */
$GLOBALS['config'] = array('is_web' => false, 'config_options_array' => array('storage_location' => 0));
require_once dirname(__DIR__) . '/include/global_constants.php';
require_once dirname(__DIR__) . '/lib/functions.php';
