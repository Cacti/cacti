<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__) . '/vendor/autoload.php';

$GLOBALS['cdef_test_items']   = [];
$GLOBALS['cdef_test_lists']   = [];
$GLOBALS['cdef_test_names']   = [];
$GLOBALS['cdef_test_queries'] = [];
$GLOBALS['cdef_functions']    = [];
$GLOBALS['cdef_operators']    = [];

function db_fetch_row_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	$GLOBALS['cdef_test_queries'][] = array($sql, $params, $log, $db_conn);

	return $GLOBALS['cdef_test_items'][(int) ($params[0] ?? 0)] ?? false;
}

function db_fetch_assoc_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	$GLOBALS['cdef_test_queries'][] = array($sql, $params, $log, $db_conn);

	return $GLOBALS['cdef_test_lists'][(int) ($params[0] ?? 0)] ?? [];
}

function db_fetch_cell_prepared($sql, $params = array(), $col_name = '', $log = true, $db_conn = false) {
	$GLOBALS['cdef_test_queries'][] = array($sql, $params, $col_name, $log, $db_conn);

	return $GLOBALS['cdef_test_names'][(int) ($params[0] ?? 0)] ?? false;
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

require_once dirname(__DIR__, 2) . '/lib/cdef.php';
