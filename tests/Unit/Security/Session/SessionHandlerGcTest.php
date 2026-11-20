<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

/*
 * The database session handler's garbage collection must report a failed
 * DELETE to PHP as false instead of success. include/session.php returns
 * early under the CLI, so the production function is loaded unchanged into a
 * fixture namespace with database stubs that keep the real signatures.
 */

namespace CactiSessionGcFixture;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$GLOBALS['session_gc_fixture'] = array('fail' => false, 'affected' => 0, 'sql' => array());

function db_execute_prepared($sql, $params = array(), $log = true, $db_conn = false, $execute_name = 'Exec', $default_value = true, $return_func = 'no_return_function', $return_params = array()) {
	$GLOBALS['session_gc_fixture']['sql'][] = array($sql, $params);

	return $GLOBALS['session_gc_fixture']['fail'] ? false : $default_value;
}

function db_affected_rows($db_conn = false) {
	return $GLOBALS['session_gc_fixture']['affected'];
}

if (!function_exists(__NAMESPACE__ . '\\cacti_db_session_clean')) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/include/session.php', 'cacti_db_session_clean', __NAMESPACE__);
}

beforeEach(function () {
	$GLOBALS['session_gc_fixture'] = array('fail' => false, 'affected' => 0, 'sql' => array());
});

test('session cleanup returns the number of expired sessions removed', function () {
	$GLOBALS['session_gc_fixture']['affected'] = 3;

	expect(cacti_db_session_clean(1440))->toBe(3);
	expect($GLOBALS['session_gc_fixture']['sql'][0][0])->toContain('DELETE FROM sessions');
	expect($GLOBALS['session_gc_fixture']['sql'][0][1][0])->toBeLessThanOrEqual(time() - 1440);
});

test('session cleanup returns zero when nothing expired', function () {
	expect(cacti_db_session_clean(1440))->toBe(0);
});

test('session cleanup returns false when the delete fails', function () {
	$GLOBALS['session_gc_fixture']['fail']     = true;
	$GLOBALS['session_gc_fixture']['affected'] = 5;

	expect(cacti_db_session_clean(1440))->toBeFalse();
});

test('the handler gc() passes the cleanup result through to PHP', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/session.php');
	$start  = strpos($source, 'public function gc($max_lifetime): int|false {');
	$body   = substr($source, $start, strpos($source, "\n\t}", $start) - $start);

	expect($start)->not->toBeFalse()
		->and($body)->toContain('return cacti_db_session_clean($max_lifetime);')
		->and($body)->not->toContain('db_affected_rows()');
});
