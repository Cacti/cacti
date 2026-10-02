<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * AbstractLoginProvider::applyAutoAssignments() is an authorization boundary:
 * on every login it adds or removes the user from Cacti User Groups based on
 * the groups their Login Provider reports. These tests pin the decisions that
 * grant or revoke group rights - add, remove, no-op (no cache churn), skipping
 * an incapable provider, and preserving memberships when LDAP membership is
 * undetermined.
 *
 * The database and permission-cache helpers are stubbed in the Cacti\Auth
 * namespace: PHP resolves an unqualified call from within that namespace to a
 * same-namespace function before falling back to the real global one, so the
 * provider runs with no database.
 */

namespace Cacti\Auth {
	function db_fetch_assoc($sql) {
		return $GLOBALS['__aa_rows'] ?? [];
	}

	function db_execute_prepared($sql, $params = []) {
		$GLOBALS['__aa_execs'][] = [
			'sql'    => preg_replace('/\s+/', ' ', trim((string) $sql)),
			'params' => $params,
		];

		$queue = &$GLOBALS['__aa_affected_queue'];
		$GLOBALS['__aa_last_affected'] = (is_array($queue) && count($queue)) ? (int) array_shift($queue) : 1;

		return true;
	}

	function db_affected_rows($db_conn = false) {
		return $GLOBALS['__aa_last_affected'] ?? 0;
	}

	function reset_user_perms($id) {
		$GLOBALS['__aa_resets'][] = $id;
	}
}

namespace {
	use Cacti\Auth\AbstractLoginProvider;

	// Claim-based provider (SAML2/OpenID semantics): the default
	// resolveGroupMatches() compares the configured names to $memberships.
	final class AaClaimProvider extends AbstractLoginProvider {
		public static function collectParameters(): array {
			return [];
		}
	}

	// LDAP-like provider that cannot determine membership this login.
	final class AaUndeterminedProvider extends AbstractLoginProvider {
		public static function collectParameters(): array {
			return [];
		}

		protected function resolveGroupMatches(string $username, array $memberships, array $groupNames): ?array {
			return null;
		}
	}

	// Provider whose group source is not currently configured (e.g. SAML with
	// no group attribute): must be skipped entirely.
	final class AaIncapableProvider extends AbstractLoginProvider {
		public static function collectParameters(): array {
			return [];
		}

		public function supportsAutoAssignment(): bool {
			return false;
		}
	}

	function aa_reset(array $rows, array $affected_queue = []): void {
		$GLOBALS['__aa_rows']            = $rows;
		$GLOBALS['__aa_execs']           = [];
		$GLOBALS['__aa_resets']          = [];
		$GLOBALS['__aa_affected_queue']  = $affected_queue;
		$GLOBALS['__aa_last_affected']   = 0;
	}

	/**
	 * Build user_auth_group rows from a [group_id => [providerId => name]] map.
	 */
	function aa_rows(array $map): array {
		$rows = [];

		foreach ($map as $gid => $assignments) {
			$rows[] = ['id' => $gid, 'auto_assignments' => json_encode($assignments)];
		}

		return $rows;
	}

	test('a member of the configured group is added and the perm cache is reset', function () {
		aa_reset(aa_rows([10 => ['5' => 'cacti-admins']]), [1]);

		(new AaClaimProvider(['id' => 5, 'name' => 'Okta']))->applyAutoAssignments(100, 'bob', ['cacti-admins', 'everyone']);

		expect($GLOBALS['__aa_execs'])->toHaveCount(1);
		expect($GLOBALS['__aa_execs'][0]['sql'])->toContain('INSERT IGNORE');
		expect($GLOBALS['__aa_execs'][0]['params'])->toBe([100, 10]);
		expect($GLOBALS['__aa_resets'])->toBe([100]);
	});

	test('a non-member is removed from the group', function () {
		aa_reset(aa_rows([10 => ['5' => 'cacti-admins']]), [1]);

		(new AaClaimProvider(['id' => 5, 'name' => 'Okta']))->applyAutoAssignments(100, 'bob', ['some-other-group']);

		expect($GLOBALS['__aa_execs'])->toHaveCount(1);
		expect($GLOBALS['__aa_execs'][0]['sql'])->toContain('DELETE FROM user_auth_group_members');
		expect($GLOBALS['__aa_execs'][0]['params'])->toBe([100, 10]);
		expect($GLOBALS['__aa_resets'])->toBe([100]);
	});

	test('matching is case-insensitive and whitespace tolerant', function () {
		aa_reset(aa_rows([12 => ['5' => 'Cacti-Admins']]), [1]);

		(new AaClaimProvider(['id' => 5, 'name' => 'Okta']))->applyAutoAssignments(100, 'bob', ['  cacti-admins ']);

		expect($GLOBALS['__aa_execs'][0]['sql'])->toContain('INSERT IGNORE');
	});

	test('a mutation that changes no row does not reset the perm cache', function () {
		// INSERT IGNORE on an already-member (0 rows affected): no cache churn.
		aa_reset(aa_rows([10 => ['5' => 'cacti-admins']]), [0]);

		(new AaClaimProvider(['id' => 5, 'name' => 'Okta']))->applyAutoAssignments(100, 'bob', ['cacti-admins']);

		expect($GLOBALS['__aa_execs'])->toHaveCount(1);
		expect($GLOBALS['__aa_resets'])->toBe([]);
	});

	test('undetermined membership leaves every assignment untouched', function () {
		aa_reset(aa_rows([10 => ['5' => 'cacti-admins']]));

		(new AaUndeterminedProvider(['id' => 5, 'name' => 'LDAP']))->applyAutoAssignments(100, 'bob', []);

		expect($GLOBALS['__aa_execs'])->toBe([]);
		expect($GLOBALS['__aa_resets'])->toBe([]);
	});

	test('a provider that cannot resolve groups is skipped entirely', function () {
		aa_reset(aa_rows([10 => ['5' => 'cacti-admins']]));

		(new AaIncapableProvider(['id' => 5, 'name' => 'SAML without a group claim']))->applyAutoAssignments(100, 'bob', ['cacti-admins']);

		expect($GLOBALS['__aa_execs'])->toBe([]);
		expect($GLOBALS['__aa_resets'])->toBe([]);
	});

	test('rules targeting a different provider are ignored', function () {
		aa_reset(aa_rows([11 => ['7' => 'other-provider-group']]), [1]);

		(new AaClaimProvider(['id' => 5, 'name' => 'Okta']))->applyAutoAssignments(100, 'bob', ['anything']);

		expect($GLOBALS['__aa_execs'])->toBe([]);
		expect($GLOBALS['__aa_resets'])->toBe([]);
	});

	test('a blank configured group name is skipped', function () {
		aa_reset(aa_rows([13 => ['5' => '']]));

		(new AaClaimProvider(['id' => 5, 'name' => 'Okta']))->applyAutoAssignments(100, 'bob', ['anything']);

		expect($GLOBALS['__aa_execs'])->toBe([]);
		expect($GLOBALS['__aa_resets'])->toBe([]);
	});

	test('a non-positive user id does nothing', function () {
		aa_reset(aa_rows([10 => ['5' => 'cacti-admins']]));

		(new AaClaimProvider(['id' => 5, 'name' => 'Okta']))->applyAutoAssignments(0, 'bob', ['cacti-admins']);

		expect($GLOBALS['__aa_execs'])->toBe([]);
	});
}
