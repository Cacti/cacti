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

namespace CactiBulkAuthHardeningProbe;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);

$GLOBALS['bulk_session_rows'] = array();
$GLOBALS['bulk_session_sql']  = array();

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function get_client_addr() {
	return '127.0.0.1';
}

function cacti_db_session_check() {
	return true;
}

function db_fetch_row_prepared($sql, $params = array()) {
	$GLOBALS['bulk_session_sql'][] = array($sql, $params);
	$id = isset($params[0]) ? $params[0] : '';

	return isset($GLOBALS['bulk_session_rows'][$id]) ? $GLOBALS['bulk_session_rows'][$id] : array();
}

function db_execute_prepared($sql, $params = array()) {
	$GLOBALS['bulk_session_sql'][] = array($sql, $params);

	if (stripos($sql, 'DELETE FROM sessions') !== false && isset($params[0])) {
		unset($GLOBALS['bulk_session_rows'][$params[0]]);
	} elseif (stripos($sql, 'UPDATE IGNORE sessions') !== false && isset($params[1]) && isset($GLOBALS['bulk_session_rows'][$params[1]])) {
		$GLOBALS['bulk_session_rows'][$params[1]]['access'] = $params[0];
	} elseif (stripos($sql, 'INSERT INTO sessions') !== false && isset($params[0])) {
		$GLOBALS['bulk_session_rows'][$params[0]] = array(
			'access' => $params[2],
			'data'   => $params[3],
		);
	}

	return true;
}

\cacti_fixture_source_function($root . '/include/session.php', 'cacti_db_session_read', __NAMESPACE__);
\cacti_fixture_source_function($root . '/include/session.php', 'cacti_db_session_write', __NAMESPACE__);

$old_access = time() - 30;
$GLOBALS['bulk_session_rows']['active-sid'] = array(
	'data'   => 'sess_user_id|i:1;',
	'access' => $old_access,
);
$active_read = cacti_db_session_read('active-sid');
$active_refreshed = $GLOBALS['bulk_session_rows']['active-sid']['access'] >= $old_access;

$maxlifetime = max(1, (int) ini_get('session.gc_maxlifetime'));
$GLOBALS['bulk_session_rows']['expired-sid'] = array(
	'data'   => 'sess_user_id|i:1;',
	'access' => time() - $maxlifetime - 120,
);
$expired_read = cacti_db_session_read('expired-sid');
$expired_deleted = !isset($GLOBALS['bulk_session_rows']['expired-sid']);

$missing_read = cacti_db_session_read('missing-sid');

$_SESSION = array();
$_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
cacti_db_session_write('write-sid', 'sess_user_id|i:42;');
$write_saved = isset($GLOBALS['bulk_session_rows']['write-sid']);

print json_encode(array(
	'active_read'      => $active_read,
	'active_refreshed' => $active_refreshed,
	'expired_read'     => $expired_read,
	'expired_deleted'  => $expired_deleted,
	'missing_read'     => $missing_read,
	'write_saved'      => $write_saved,
), JSON_THROW_ON_ERROR);
