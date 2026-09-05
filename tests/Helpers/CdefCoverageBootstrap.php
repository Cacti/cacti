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

if (PHP_SAPI !== 'cli') {
	exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$GLOBALS['cdef_test_items']          = [];
$GLOBALS['cdef_test_lists']          = [];
$GLOBALS['cdef_test_names']          = [];
$GLOBALS['cdef_test_queries']        = [];
$GLOBALS['cdef_test_logs']           = [];
$GLOBALS['cdef_test_assoc_callback'] = null;
$GLOBALS['cdef_test_cell_callback']  = null;
$GLOBALS['cdef_functions']           = [];
$GLOBALS['cdef_operators']           = [];

function db_fetch_row_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	$GLOBALS['cdef_test_queries'][] = array($sql, $params, $log, $db_conn);

	return $GLOBALS['cdef_test_items'][(int) ($params[0] ?? 0)] ?? false;
}

function db_fetch_assoc_prepared($sql, $params = array(), $log = true, $db_conn = false) {
	$GLOBALS['cdef_test_queries'][] = array($sql, $params, $log, $db_conn);

	if (is_callable($GLOBALS['cdef_test_assoc_callback'])) {
		$result = ($GLOBALS['cdef_test_assoc_callback'])($sql, $params);

		if ($result !== null) {
			return $result;
		}
	}

	if (str_contains($sql, 'FROM cdef WHERE id')) {
		return array_key_exists((int) ($params[0] ?? 0), $GLOBALS['cdef_test_names']) ? [['id' => $params[0]]] : [];
	}

	return $GLOBALS['cdef_test_lists'][(int) ($params[0] ?? 0)] ?? [];
}

function db_fetch_cell_prepared($sql, $params = array(), $col_name = '', $log = true, $db_conn = false) {
	$GLOBALS['cdef_test_queries'][] = array($sql, $params, $col_name, $log, $db_conn);

	if (is_callable($GLOBALS['cdef_test_cell_callback'])) {
		$result = ($GLOBALS['cdef_test_cell_callback'])($sql, $params);

		if ($result !== null) {
			return $result;
		}
	}

	return $GLOBALS['cdef_test_names'][(int) ($params[0] ?? 0)] ?? false;
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['cdef_test_logs'][] = array($message, $output, $environ, $level);

	return true;
}

require_once dirname(__DIR__, 2) . '/lib/cdef.php';
