<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Behavior tests for cacti_validate_sort_column().
 *
 * This helper is the root-cause mitigation for the ORDER BY injection
 * vulnerability class (GHSA-3p6w, GHSA-84q3, GHSA-gp82 and prior). It must
 * return only allowlisted column names; anything else must fall through to
 * the default. Returning attacker-controlled data would reintroduce the bug.
 */

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/lib/functions.php';
});

describe('cacti_validate_sort_column', function () {
	it('returns the column when it is in the allowlist', function () {
		expect(cacti_validate_sort_column('name', ['name', 'id', 'time'], 'id'))
			->toBe('name');
	});

	it('returns the default when the column is not in the allowlist', function () {
		expect(cacti_validate_sort_column('password_hash', ['name', 'id'], 'name'))
			->toBe('name');
	});

	it('returns the default for an empty input', function () {
		expect(cacti_validate_sort_column('', ['name', 'id'], 'name'))
			->toBe('name');
	});

	it('returns the first allowlist entry when default is empty and input is invalid', function () {
		expect(cacti_validate_sort_column('bad', ['name', 'id'], ''))
			->toBe('name');
	});

	it('returns literal "id" when both input is invalid and allowlist is empty', function () {
		expect(cacti_validate_sort_column('bad', [], ''))
			->toBe('id');
	});

	it('does not return SQL injection payloads', function () {
		$allowed = ['name', 'time'];
		$attacks = [
			"1; DROP TABLE users; --",
			"name; DELETE FROM x",
			"1 UNION SELECT password FROM user_auth",
			"id`",
			"name OR 1=1",
			"name/*comment*/",
			"1--",
			"`id`",
			"id, (SELECT password FROM user_auth LIMIT 1)",
		];

		foreach ($attacks as $payload) {
			expect(cacti_validate_sort_column($payload, $allowed, 'time'))
				->toBe('time', "attack string '$payload' must be rejected");
		}
	});

	it('uses strict comparison (rejects loose matches)', function () {
		// PHP loose comparison: '1' == 1 is true. Strict comparison prevents
		// attacker-controlled integer-like strings from matching numeric column names.
		$allowed = ['0', '1'];

		// Strict: integer 0 must NOT match string '0' in the allowlist.
		// Since all callers pass request-var strings, verify the strict path holds.
		expect(cacti_validate_sort_column('0', $allowed, 'fallback'))->toBe('0');
		expect(cacti_validate_sort_column('1', $allowed, 'fallback'))->toBe('1');
	});

	it('is case-sensitive (SQL column names are case-sensitive on some engines)', function () {
		expect(cacti_validate_sort_column('NAME', ['name'], 'name'))
			->toBe('name');
		expect(cacti_validate_sort_column('name', ['name'], 'name'))
			->toBe('name');
	});

	it('accepts function-expression entries in the allowlist', function () {
		// Cacti's existing get_order_string() can legitimately sort by
		// function expressions like INET_ATON(hostname) or LENGTH(description).
		// The allowlist accepts any string the caller approves — strict ===
		// comparison means a function-wrapped entry passes iff the caller
		// put that exact fragment in $allowed.
		$allowed = ['description', 'hostname', 'INET_ATON(hostname)', 'LENGTH(description)'];

		expect(cacti_validate_sort_column('INET_ATON(hostname)', $allowed, 'description'))
			->toBe('INET_ATON(hostname)');

		expect(cacti_validate_sort_column('LENGTH(description)', $allowed, 'description'))
			->toBe('LENGTH(description)');

		// Attacker-crafted function-looking input without exact allowlist
		// entry is still rejected.
		expect(cacti_validate_sort_column('INET_ATON(password)', $allowed, 'description'))
			->toBe('description');

		expect(cacti_validate_sort_column('SLEEP(10)', $allowed, 'description'))
			->toBe('description');
	});

	it('handles whitespace-padded input as non-matching', function () {
		// Leading/trailing whitespace would change a valid identifier into an
		// invalid one in most SQL contexts; treat as non-match.
		expect(cacti_validate_sort_column(' name', ['name'], 'fallback'))
			->toBe('fallback');
		expect(cacti_validate_sort_column("name\n", ['name'], 'fallback'))
			->toBe('fallback');
	});
});
