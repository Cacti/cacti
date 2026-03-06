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

// Shared stubs for Cacti helpers used across test files.
// cacti_sizeof/cacti_count are provided by lib/functions.php (no guards there),
// so they must NOT be defined here. Load lib/functions.php after this file.

if (!function_exists('__')) {
	function __(string $text, ...$args): string {
		return $text;
	}
}

if (!function_exists('__esc')) {
	function __esc(string $text, ...$args): string {
		return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!defined('CACTI_SERVER_OS')) {
	define('CACTI_SERVER_OS', 'unix');
}

if (!defined('FILTER_VALIDATE_MAC')) {
	define('FILTER_VALIDATE_MAC', 'validate_mac');
}
