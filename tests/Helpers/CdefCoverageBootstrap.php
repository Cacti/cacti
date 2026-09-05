<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';

$GLOBALS['cdef_test_items']   = [];
$GLOBALS['cdef_test_lists']   = [];
$GLOBALS['cdef_test_names']   = [];
$GLOBALS['cdef_test_queries'] = [];
$GLOBALS['cdef_test_logs']    = [];
$GLOBALS['cdef_functions']    = [];
$GLOBALS['cdef_operators']    = [];

function db_fetch_row_prepared(string $sql, array $params = [], bool $log = true, mixed $db_conn = false, float $timeout = 0) : bool|array {
	$GLOBALS['cdef_test_queries'][] = [$sql, $params, $log, $db_conn, $timeout];

	return $GLOBALS['cdef_test_items'][(int) ($params[0] ?? 0)] ?? false;
}

function db_fetch_assoc_prepared(string $sql, array $params = [], bool $log = true, mixed $db_conn = false, float $timeout = 0) : mixed {
	$GLOBALS['cdef_test_queries'][] = [$sql, $params, $log, $db_conn, $timeout];

	return $GLOBALS['cdef_test_lists'][(int) ($params[0] ?? 0)] ?? [];
}

function db_fetch_cell_prepared(string $sql, array $params = [], string $col_name = '', bool $log = true, mixed $db_conn = false, float $timeout = 0) : mixed {
	$GLOBALS['cdef_test_queries'][] = [$sql, $params, $col_name, $log, $db_conn, $timeout];

	return $GLOBALS['cdef_test_names'][(int) ($params[0] ?? 0)] ?? false;
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function cacti_log(mixed $message, bool $output = false, string $environ = 'CMDPHP', mixed $level = '') : bool {
	$GLOBALS['cdef_test_logs'][] = [$message, $output, $environ, $level];

	return true;
}

require_once dirname(__DIR__, 2) . '/lib/cdef.php';
