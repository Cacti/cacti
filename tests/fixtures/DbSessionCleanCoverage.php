<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Request-coverage probe for the session cleanup in include/session.php. The
// real file runs in this process, against stand-ins for the database helpers,
// because loading it registers the database session handler. The results are
// printed for DbSessionCleanTest to assert, and RequestCoverage records the
// hits against the real file.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';

// include/session.php returns early when the command line flag is set
define('CACTI_CLI', false);

$GLOBALS['db_session_clean_fail'] = false;

function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['db_session_clean_sql']      = $sql;
	$GLOBALS['db_session_clean_affected'] = $GLOBALS['db_session_clean_fail'] ? 0 : 3;

	return !$GLOBALS['db_session_clean_fail'];
}

function db_affected_rows() : mixed {
	return $GLOBALS['db_session_clean_affected'];
}

require dirname(__DIR__, 2) . '/include/session.php';

$handler = new Cacti_DB_Session_Handler();
$results = [];

$results['removed']   = $handler->gc(1440);
$results['sql']       = str_contains($GLOBALS['db_session_clean_sql'], 'DELETE FROM sessions');
$results['non_count'] = cacti_db_session_clean('not a number');

$GLOBALS['db_session_clean_fail'] = true;
$results['failed']                = $handler->gc(1440);

print json_encode($results, JSON_THROW_ON_ERROR) . "\n";
