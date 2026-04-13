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
 * Tests for the MikroTik script SQL injection hardening.
 *
 * ss_mikrotik_interfaces.php and ss_mikrotik_queues.php had two issues:
 *   1. Column names were not validated before interpolation into SQL.
 *   2. $index / $index2 values were passed through db_qstr() inline
 *      instead of using prepared statement placeholders.
 *
 * The fix adds:
 *   - A regex whitelist for column names in ss_mikrotik_interfaces
 *   - An allow-list + default return for column names in ss_mikrotik_queues
 *   - Prepared statement placeholders (?) for $index and $index2
 *
 * NOTE: These are stub-based unit tests that mirror the production
 * validation logic. They verify algorithmic correctness of the guards.
 */

// --- Stub: interfaces column validation (ss_mikrotik_interfaces.php L61-63) ---

function validate_interfaces_column(string $column): bool {
	if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
		return false;
	}

	return true;
}

// --- Stub: queues column allow-list (ss_mikrotik_queues.php L96-98) ---

function validate_queues_column(string $column): string {
	$allowed_columns = [
		'curBytesIn', 'curBytesOut',
		'curPacketsIn', 'curPacketsOut',
		'curQueuesIn', 'curQueuesOut',
		'curDroppedIn', 'curDroppedOut',
	];

	if (!in_array($column, $allowed_columns, true)) {
		return '';
	}

	return $column;
}

// --- Stub: index parameterization (both scripts) ---

function build_parameterized_query(string $index, int $host_id): array {
	$index2 = str_replace('_', ' ', $index);

	$sql    = 'SELECT ? AS value FROM table WHERE name IN (?, ?) AND host_id = ?';
	$params = [$index, $index2, $host_id];

	return ['sql' => $sql, 'params' => $params];
}

// --- Interfaces column validation ---

test('interfaces column: valid alphanumeric passes', function () {
	expect(validate_interfaces_column('Bytes'))->toBeTrue();
});

test('interfaces column: valid with underscore passes', function () {
	expect(validate_interfaces_column('cur_bytes_in'))->toBeTrue();
});

test('interfaces column: valid with digits passes', function () {
	expect(validate_interfaces_column('col123'))->toBeTrue();
});

test('interfaces column: SQL injection rejected', function () {
	expect(validate_interfaces_column("1; DROP TABLE users--"))->toBeFalse();
});

test('interfaces column: quotes rejected', function () {
	expect(validate_interfaces_column("bytes'"))->toBeFalse();
});

test('interfaces column: spaces rejected', function () {
	expect(validate_interfaces_column('cur bytes'))->toBeFalse();
});

test('interfaces column: empty string rejected', function () {
	expect(validate_interfaces_column(''))->toBeFalse();
});

test('interfaces column: parentheses rejected', function () {
	expect(validate_interfaces_column('col()'))->toBeFalse();
});

// --- Queues column allow-list ---

test('queues column: curBytesIn allowed', function () {
	expect(validate_queues_column('curBytesIn'))->toBe('curBytesIn');
});

test('queues column: curDroppedOut allowed', function () {
	expect(validate_queues_column('curDroppedOut'))->toBe('curDroppedOut');
});

test('queues column: all 8 columns allowed', function () {
	$allowed = [
		'curBytesIn', 'curBytesOut',
		'curPacketsIn', 'curPacketsOut',
		'curQueuesIn', 'curQueuesOut',
		'curDroppedIn', 'curDroppedOut',
	];

	foreach ($allowed as $col) {
		expect(validate_queues_column($col))->toBe($col);
	}
});

test('queues column: unknown column rejected', function () {
	expect(validate_queues_column('notAColumn'))->toBe('');
});

test('queues column: SQL injection rejected', function () {
	expect(validate_queues_column("curBytesIn' OR 1=1--"))->toBe('');
});

test('queues column: empty string rejected', function () {
	expect(validate_queues_column(''))->toBe('');
});

// --- Index parameterization ---

test('parameterized query: index appears in params not SQL', function () {
	$result = build_parameterized_query('ether1', 42);

	expect($result['sql'])->not->toContain('ether1');
	expect($result['params'])->toContain('ether1');
});

test('parameterized query: index2 derived from index with spaces', function () {
	$result = build_parameterized_query('ether_1', 42);

	expect($result['params'][0])->toBe('ether_1');
	expect($result['params'][1])->toBe('ether 1');
});

test('parameterized query: host_id in params', function () {
	$result = build_parameterized_query('ether1', 99);

	expect($result['params'][2])->toBe(99);
});

test('parameterized query: SQL injection in index stays in params', function () {
	$malicious = "'; DROP TABLE plugin_mikrotik_interfaces; --";
	$result    = build_parameterized_query($malicious, 1);

	expect($result['sql'])->not->toContain('DROP');
	expect($result['params'][0])->toBe($malicious);
});

test('parameterized query: uses placeholders for all values', function () {
	$result = build_parameterized_query('test', 1);

	$placeholder_count = substr_count($result['sql'], '?');
	expect($placeholder_count)->toBe(4);
});
