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
 * Tests for the test-mode DB sentinel guard. The short-circuit that skips a
 * real database connection must engage only when PHP_TESTING is defined AND
 * the CACTI_TEST_BOOTSTRAP env var is set. PHP_TESTING alone must fail closed:
 * it must not swap to config.php.dist nor skip schema validation.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// Pure replica of cacti_is_test_bootstrap() so the false branches can be
// exercised without mutating the process-global PHP_TESTING constant.
function test_predicate(bool $php_testing, ?string $env): bool {
	return $php_testing && $env === '1';
}

test('bootstrap arms the gate: CactiStubs sets CACTI_TEST_BOOTSTRAP=1', function () {
	expect(getenv('CACTI_TEST_BOOTSTRAP', true))->toBe('1');
});

test('cacti_is_test_bootstrap is true under the test bootstrap', function () {
	expect(defined('PHP_TESTING'))->toBeTrue();
	expect(cacti_is_test_bootstrap())->toBeTrue();
});

test('predicate true only when PHP_TESTING and env are both set', function () {
	expect(test_predicate(true, '1'))->toBeTrue();
});

test('predicate false when PHP_TESTING is set but env is missing', function () {
	expect(test_predicate(true, null))->toBeFalse();
	expect(test_predicate(true, '0'))->toBeFalse();
	expect(test_predicate(true, ''))->toBeFalse();
});

test('predicate false when neither PHP_TESTING nor env is set', function () {
	expect(test_predicate(false, null))->toBeFalse();
});

test('predicate false when env is set but PHP_TESTING is not', function () {
	expect(test_predicate(false, '1'))->toBeFalse();
});

test('short-circuit installs the DB sentinel rather than a real handle', function () {
	global $local_db_cnn_id;

	// global.php ran above under the test bootstrap, so no real connection
	// was attempted and the sentinel stands in for the handle.
	expect($local_db_cnn_id)->toBeInstanceOf(Cacti_TestDbSentinel::class);
	expect(_cacti_is_real_db_conn($local_db_cnn_id))->toBeFalse();
});

test('sentinel throws on any method call so it cannot pass as a real handle', function () {
	$sentinel = new Cacti_TestDbSentinel();

	expect(fn () => $sentinel->query('SELECT 1'))
		->toThrow(RuntimeException::class);
});

test('global.php always assigns cacti_db_version with a safe default', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/include/global.php');

	// Bug 4: the key must be seeded so isset()/comparison logic never raises
	// an Undefined array key warning when the local DB handle is the sentinel
	// or the connection is down. Both connect branches must seed the default.
	$matches = preg_match_all(
		"/\\\$config\\['cacti_db_version'\\] = '';/",
		$src
	);

	expect($matches)->toBe(2);
});

// Source-level guards. These assert the fail-closed wiring is present in the
// shipped files, independent of the runtime path the test itself takes.

test('global.php gates config.php.dist swap on the combined predicate', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/include/global.php');

	// The config.php.dist include must be gated by the helper, not PHP_TESTING alone.
	expect($src)->toContain('if (cacti_is_test_bootstrap() && file_exists(__DIR__ . \'/config.php.dist\'))');
});

test('global.php reads CACTI_TEST_BOOTSTRAP with local_only=true', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/include/global.php');

	expect($src)->toContain("getenv('CACTI_TEST_BOOTSTRAP', true)");
	// No call site may read the env var without the local_only flag.
	expect($src)->not->toContain("getenv('CACTI_TEST_BOOTSTRAP')");
});

test('database.php gates schema validation on the combined predicate with local_only', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/database.php');

	expect($src)->toContain("getenv('CACTI_TEST_BOOTSTRAP', true)");
	expect($src)->not->toContain("if (!defined('PHP_TESTING')) {\n\t\t\t\t$table_exists");
	expect($src)->toContain('$skip_schema_check');
});
