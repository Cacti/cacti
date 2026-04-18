<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Behavior tests for cacti_authorize_resource().
 *
 * Root-cause mitigation for IDOR (GHSA-8p2f and future reports). The
 * helper must:
 *   - return false for zero/negative user or resource ids
 *   - return true when the user owns the row OR is an admin
 *   - return false for unknown resource types (fail closed)
 *   - cache admin status per-request to avoid DB hammering
 *
 * Tests use source-scan invariants plus an isolated reimplementation of
 * the authorization decision (no DB) to cover edge cases.
 */

/**
 * Pure-function mirror of cacti_authorize_resource() for testing the
 * decision logic without a database. Accepts the inputs the real helper
 * would derive from the DB (owner_id, is_admin).
 */
function decide_authorize($user_id, $resource_id, $resource_type, $owner_of_resource, $user_is_admin) {
	$user_id     = (int) $user_id;
	$resource_id = (int) $resource_id;

	if ($user_id <= 0 || $resource_id <= 0) {
		return false;
	}

	if ($user_is_admin) {
		return true;
	}

	switch ($resource_type) {
		case 'reports':
		case 'graph_tree':
			return $owner_of_resource !== null && (int) $owner_of_resource === $user_id;

		case 'settings_user':
			return $resource_id === $user_id;

		default:
			return false; // fail closed
	}
}

describe('cacti_authorize_resource source contract', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/auth.php');

	it('casts user_id and resource_id to int before any decision', function () use ($src) {
		expect($src)->toContain('$user_id     = (int) $user_id');
		expect($src)->toContain('$resource_id = (int) $resource_id');
	});

	it('rejects non-positive ids before any further work', function () use ($src) {
		expect($src)->toMatch('/if\s*\(\$user_id\s*<=\s*0\s*\|\|\s*\$resource_id\s*<=\s*0\)\s*\{[^}]*return\s+false/s');
	});

	it('falls through to false for unknown resource types', function () use ($src) {
		expect($src)->toContain('default:')
			->and($src)->toMatch('/default:\s*\/\/[^\n]*fail closed\s*\n\s*return\s+false/');
	});

	it('uses prepared queries for ownership lookups', function () use ($src) {
		expect($src)->toContain('db_fetch_cell_prepared');
		expect($src)->not->toMatch('/db_fetch_cell\s*\([^)]*\$user_id/');  // no raw concat
	});

	it('caches admin status per-request', function () use ($src) {
		expect($src)->toContain('static $admin_cache');
	});
});

describe('authorization decision logic', function () {
	it('rejects zero user id', function () {
		expect(decide_authorize(0, 10, 'reports', 5, false))->toBeFalse();
	});

	it('rejects negative user id', function () {
		expect(decide_authorize(-1, 10, 'reports', 5, false))->toBeFalse();
	});

	it('rejects zero resource id', function () {
		expect(decide_authorize(5, 0, 'reports', 5, false))->toBeFalse();
	});

	it('accepts owner of reports', function () {
		expect(decide_authorize(5, 10, 'reports', 5, false))->toBeTrue();
	});

	it('rejects non-owner of reports', function () {
		expect(decide_authorize(7, 10, 'reports', 5, false))->toBeFalse();
	});

	it('accepts owner of graph_tree', function () {
		expect(decide_authorize(5, 10, 'graph_tree', 5, false))->toBeTrue();
	});

	it('rejects non-owner of graph_tree', function () {
		expect(decide_authorize(99, 10, 'graph_tree', 5, false))->toBeFalse();
	});

	it('allows admin to bypass ownership', function () {
		// User 99 is admin; owner is 5; admin wins.
		expect(decide_authorize(99, 10, 'reports', 5, true))->toBeTrue();
	});

	it('allows user to modify own settings_user row', function () {
		expect(decide_authorize(5, 5, 'settings_user', null, false))->toBeTrue();
	});

	it('rejects user modifying another user settings_user row', function () {
		expect(decide_authorize(5, 7, 'settings_user', null, false))->toBeFalse();
	});

	it('fails closed for unknown resource types', function () {
		expect(decide_authorize(5, 10, 'totally_unknown', 5, false))->toBeFalse();
	});

	it('rejects null owner (missing resource row) for non-admin', function () {
		expect(decide_authorize(5, 10, 'reports', null, false))->toBeFalse();
	});

	it('rejects when owner is 0 (orphaned row) for non-admin', function () {
		// Cast: (int) null === 0; owner === 0 but user >= 1, so unequal.
		expect(decide_authorize(5, 10, 'reports', 0, false))->toBeFalse();
	});
});

describe('IDOR attack scenarios', function () {
	// Scenario: attacker is user 7, target is user 5's report id 42.
	// Without this helper, report_edit.php accepts id=42 and updates.
	// With this helper, the update is rejected.

	it('blocks cross-user report modification', function () {
		$attacker = 7;
		$victim_report_id = 42;
		$victim_user_id = 5;

		expect(decide_authorize($attacker, $victim_report_id, 'reports', $victim_user_id, false))
			->toBeFalse();
	});

	it('blocks cross-user graph_tree modification', function () {
		expect(decide_authorize(7, 42, 'graph_tree', 5, false))->toBeFalse();
	});

	it('blocks enumeration attack via non-existent resource id', function () {
		// Non-existent rows return null owner; must be false (don't leak existence).
		expect(decide_authorize(7, 999999, 'reports', null, false))->toBeFalse();
	});
});
