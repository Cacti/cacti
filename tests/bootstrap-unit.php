<?php
/*
 * Minimal bootstrap for source-scan unit tests.
 * These tests only call file_get_contents() on PHP source files and
 * never touch the database, so we skip global.php entirely.
 */
require_once __DIR__ . '/vendor/autoload.php';
