<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

// Minimal bootstrap for unit tests that don't need a full Cacti environment
define('CACTI_PATH', dirname(__DIR__));

// Autoloader from composer
require_once CACTI_PATH . '/include/vendor/autoload.php';
