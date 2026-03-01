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
 * Tests for the db_execute_prepared() recursion guard pattern.
 *
 * When the database is unreachable, the error handler at line 655 of
 * lib/database.php calls db_column_exists('settings', 'name'), which
 * routes through db_fetch_cell() -> db_fetch_cell_prepared() ->
 * db_execute_prepared(), creating infinite recursion and stack overflow.
 *
 * See: https://github.com/Cacti/cacti/issues/6687
 *
 * These tests verify the recursion guard pattern using inline stubs
 * that mirror the call chain without requiring a live database.
 */

/**
 * Simulates db_column_exists() static cache behavior.
 * On first call with DB down, the cache is empty and the function
 * must query. On second call, cached result is returned.
 */
function stub_db_column_exists(string $table, string $column, bool $db_up = true): bool {
	static $results = [];

	if (isset($results[$table][$column])) {
		return $results[$table][$column];
	}

	if (!$db_up) {
		/* DB is down, query would fail, cache never populates */
		return false;
	}

	$results[$table][$column] = true;

	return $results[$table][$column];
}

/**
 * Simulates the unguarded error handler that causes recursion.
 * Returns the recursion depth reached before the guard (or limit) stops it.
 */
function unguarded_error_handler(int $depth = 0, int $max = 100): int {
	if ($depth >= $max) {
		return $depth;
	}

	/* mirrors line 655: db_column_exists() -> db_fetch_cell() -> db_execute_prepared() */
	$col_exists = stub_db_column_exists('settings', 'name', false);

	if (!$col_exists) {
		/* simulates the re-entrant db_execute_prepared() call */
		return unguarded_error_handler($depth + 1, $max);
	}

	return $depth;
}

/**
 * Simulates the guarded error handler with a static recursion flag.
 * This is the fix pattern proposed in issue #6687.
 */
function guarded_error_handler(int $depth = 0, int $max = 100): int {
	static $in_error = false;

	if ($in_error) {
		return $depth;
	}

	$in_error = true;

	$col_exists = stub_db_column_exists('settings', 'name', false);

	if (!$col_exists) {
		$result = guarded_error_handler($depth + 1, $max);
		$in_error = false;

		return $result;
	}

	$in_error = false;

	return $depth;
}

// --- db_column_exists cache: first call with DB up populates cache ---

test('db_column_exists cache returns true on second call after DB-up population', function () {
	/* reset static by calling with a unique table name */
	$first  = stub_db_column_exists('cache_test_table', 'col1', true);
	$second = stub_db_column_exists('cache_test_table', 'col1', false);

	expect($first)->toBeTrue()
		->and($second)->toBeTrue();
});

// --- db_column_exists cache: DB-down call does not populate cache ---

test('db_column_exists returns false when DB is down and cache is empty', function () {
	$result = stub_db_column_exists('uncached_table', 'col1', false);

	expect($result)->toBeFalse();
});

// --- unguarded handler: recurses to max depth ---

test('unguarded error handler recurses to the maximum depth', function () {
	$depth = unguarded_error_handler(0, 50);

	expect($depth)->toBe(50);
});

// --- guarded handler: stops at depth 1 ---

test('guarded error handler stops recursion at depth 1', function () {
	$depth = guarded_error_handler(0, 50);

	expect($depth)->toBe(1);
});

// --- guarded handler: resets flag after returning ---

test('guarded error handler resets flag and can be called again', function () {
	$first  = guarded_error_handler(0, 50);
	$second = guarded_error_handler(0, 50);

	expect($first)->toBe(1)
		->and($second)->toBe(1);
});

// --- recursion chain: db_fetch_cell -> db_fetch_cell_prepared -> db_execute_prepared ---

test('recursion chain mirrors the real call path through three functions', function () {
	$call_log = [];

	$db_execute_prepared = null;

	$db_fetch_cell_prepared = function (string $sql) use (&$call_log, &$db_execute_prepared): mixed {
		$call_log[] = 'db_fetch_cell_prepared';

		return ($db_execute_prepared)($sql);
	};

	$db_fetch_cell = function (string $sql) use (&$call_log, &$db_fetch_cell_prepared): mixed {
		$call_log[] = 'db_fetch_cell';

		return $db_fetch_cell_prepared($sql);
	};

	$db_column_exists = function () use (&$call_log, &$db_fetch_cell): bool {
		$call_log[] = 'db_column_exists';

		return (bool) $db_fetch_cell("SHOW columns FROM `settings` LIKE 'name'");
	};

	$guard = false;

	$db_execute_prepared = function (string $sql) use (&$call_log, &$guard, &$db_column_exists): bool {
		$call_log[] = 'db_execute_prepared';

		/* simulate query failure */
		$error = 2006;

		if ($error == 2006) {
			if ($guard) {
				$call_log[] = 'guard_triggered';

				return false;
			}

			$guard = true;
			$db_column_exists();
			$guard = false;
		}

		return false;
	};

	$db_execute_prepared("SELECT * FROM poller_output");

	expect($call_log)->toBe([
		'db_execute_prepared',
		'db_column_exists',
		'db_fetch_cell',
		'db_fetch_cell_prepared',
		'db_execute_prepared',
		'guard_triggered',
	]);
});
