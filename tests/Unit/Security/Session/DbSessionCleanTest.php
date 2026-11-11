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

/*
 * SessionHandlerInterface::gc() reports the number of sessions removed, or
 * false when the cleanup failed. The unchanged cacti_db_session_clean() is
 * loaded into a fixture namespace with a stub database, since including
 * include/session.php would register the session handler.
 */

namespace CactiDbSessionCleanFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$GLOBALS['db_session_clean_fail'] = false;

function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['db_session_clean_sql']      = $sql;
	$GLOBALS['db_session_clean_affected'] = $GLOBALS['db_session_clean_fail'] ? 0 : 3;

	return !$GLOBALS['db_session_clean_fail'];
}

function db_affected_rows() : mixed {
	return $GLOBALS['db_session_clean_affected'];
}

if (!function_exists(__NAMESPACE__ . '\\cacti_db_session_clean')) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/include/session.php', 'cacti_db_session_clean', __NAMESPACE__);
}

test('session cleanup returns the number of sessions removed', function () {
	$GLOBALS['db_session_clean_fail'] = false;

	expect(cacti_db_session_clean(1440))->toBe(3)
		->and($GLOBALS['db_session_clean_sql'])->toContain('DELETE FROM sessions');
});

test('session cleanup returns false when the delete fails', function () {
	$GLOBALS['db_session_clean_fail'] = true;

	expect(cacti_db_session_clean(1440))->toBeFalse();
});

test('the session handler gc() returns the cleanup result', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/session.php');

	expect($source)->toMatch('/function gc\(int \$max_lifetime\): int\|false \{\s*return cacti_db_session_clean\(\$max_lifetime\);\s*\}/');
});
