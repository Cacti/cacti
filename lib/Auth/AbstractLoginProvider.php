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

declare(strict_types = 1);

namespace Cacti\Auth;

/**
 * Shared state and helpers for every Login Provider, built from one row of
 * the `login_providers` table (id/name/type/enabled/... plus the type-specific
 * `parameters` JSON blob).
 */
abstract class AbstractLoginProvider implements LoginProviderInterface {
	protected readonly int $id;
	protected readonly string $name;
	protected readonly int $type;
	protected readonly bool $enabled;
	protected readonly bool $allowAuthCookies;
	protected readonly int $templateUserId;
	protected readonly string $buttonLabel;
	protected readonly bool $debugEnabled;

	/** @var array<string, mixed> Decoded `parameters` JSON for this provider row. */
	protected readonly array $parameters;

	/**
	 * @param array $row A row from the `login_providers` table.
	 */
	public function __construct(array $row) {
		$this->id                = (int) ($row['id'] ?? 0);
		$this->name              = (string) ($row['name'] ?? '');
		$this->type              = (int) ($row['type'] ?? 0);
		$this->enabled           = ($row['enabled'] ?? '')              === 'on';
		$this->allowAuthCookies  = ($row['allow_auth_cookies'] ?? 'on') === 'on';
		$this->templateUserId    = (int) ($row['user_id'] ?? 0);
		// button_label/debug are top-level login_providers columns, not part
		// of the type-specific `parameters` JSON - read them from the row.
		$this->buttonLabel       = (string) ($row['button_label'] ?? '');
		$this->debugEnabled      = ($row['debug'] ?? '') === 'on';

		$decoded = json_decode((string) ($row['parameters'] ?? ''), true);

		$this->parameters = is_array($decoded) ? $decoded : [];
	}

	public function getId(): int {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function getType(): int {
		return $this->type;
	}

	public function isEnabled(): bool {
		return $this->enabled;
	}

	public function allowsAuthCookies(): bool {
		return $this->allowAuthCookies;
	}

	public function getTemplateUserId(): int {
		return $this->templateUserId;
	}

	protected function param(string $key, mixed $default = ''): mixed {
		return $this->parameters[$key] ?? $default;
	}

	/**
	 * Reads a single still-stored (possibly encrypted) parameter value from
	 * an existing provider row. For secret fields that never redisplay
	 * their value in the form (e.g. a "privkey" field), leaving the field
	 * blank on save means "keep the existing value" - this is how
	 * collectParameters() implementations look that existing value up.
	 *
	 * @param int    $id  The login_providers.id being edited, or <= 0 for a new provider.
	 * @param string $key The parameters[] key to read.
	 *
	 * @return string The existing value, or '' for a new provider or a missing key.
	 */
	protected static function existingParameter(int $id, string $key): string {
		if ($id <= 0) {
			return '';
		}

		$parameters = json_decode((string) db_fetch_cell_prepared('SELECT parameters FROM login_providers WHERE id = ?', [$id]), true);
		$parameters = is_array($parameters) ? $parameters : [];

		return (string) ($parameters[$key] ?? '');
	}

	/**
	 * Encrypts a freshly submitted secret for storage, or - when the "privkey"
	 * field was left blank because it never redisplays its stored value -
	 * keeps the existing value for $key unchanged, EXCEPT that a legacy
	 * plaintext value (one that predates encryption being added, e.g. a
	 * provider saved before this feature existed) is transparently
	 * encrypted at this point too, so a plain "Save" upgrades it instead of
	 * leaving it unencrypted at rest indefinitely.
	 *
	 * @param string $submitted The raw value read from the request, '' if left blank.
	 * @param string $key       The parameters[] key being saved.
	 *
	 * @return string The value to store: freshly encrypted, the existing value re-encrypted
	 *                if it was still legacy plaintext, or ''.
	 */
	protected static function encryptOrKeepExisting(string $submitted, string $key): string {
		if ($submitted !== '') {
			return cacti_encrypt_secret($submitted);
		}

		$existing = self::existingParameter((int) gnrv('id'), $key);

		if ($existing === '') {
			return '';
		}

		return cacti_decrypt_secret($existing) !== false ? $existing : cacti_encrypt_secret($existing);
	}

	/**
	 * Non-secret counterpart to encryptOrKeepExisting(), for fields such as
	 * a "textcert" certificate that also never redisplay their stored value
	 * (so it can be pasted over without first being cleared) but don't need
	 * encryption at rest. A blank submission keeps the existing value.
	 *
	 * @param string $submitted The raw value read from the request, '' if left blank.
	 * @param string $key       The parameters[] key being saved.
	 *
	 * @return string The value to store: the freshly submitted value, or the untouched existing value.
	 */
	protected static function keepExistingIfBlank(string $submitted, string $key): string {
		return $submitted !== '' ? $submitted : self::existingParameter((int) gnrv('id'), $key);
	}

	/**
	 * Reads and validates this provider type's own settings from the current
	 * request (a submitted login_providers.php form), returning the flat
	 * array to be JSON-encoded into the `parameters` column. Only the fields
	 * relevant to this specific type are read, so switching a provider's
	 * type does not leave stale settings from another type behind.
	 */
	abstract public static function collectParameters(): array;

	/**
	 * Persists a login_providers row (insert or update, per sql_save()'s
	 * usual "id" convention) from already-validated general fields plus the
	 * type-specific parameters collected via collectParameters().
	 *
	 * @param array $general    Validated id/name/description/type/... fields.
	 * @param array $parameters The type-specific settings to JSON-encode.
	 *
	 * @return int|false The row id on success, false on failure.
	 */
	public static function persist(array $general, array $parameters): int|false {
		$general['parameters'] = json_encode($parameters);

		$id = sql_save($general, 'login_providers', 'id');

		if ($id) {
			// Every provider type (LDAP/AD/SAML2/OpenID) copies a template
			// account rather than authenticating it directly, so the template
			// itself must never be directly usable as a local login.
			db_execute_prepared('UPDATE user_auth
				SET enabled = ""
				WHERE id = ?',
				[$general['user_id']]);
		}

		return $id;
	}

	public static function deleteById(int $id): void {
		db_execute_prepared('DELETE FROM login_providers WHERE id = ?', [$id]);

		// Drop any automatic User Group assignment rules that referenced this
		// provider so they are not left behind to be evaluated at login.
		self::purgeAutoAssignments($id);
	}

	public static function enableById(int $id): void {
		db_execute_prepared('UPDATE login_providers SET enabled = "on" WHERE id = ?', [$id]);
	}

	public static function disableById(int $id): void {
		db_execute_prepared('UPDATE login_providers SET enabled = "" WHERE id = ?', [$id]);
	}

	public static function makeDefaultById(int $id): void {
		// get_auth_realms() only ever offers LDAP/AD rows as the login-page
		// default realm; making a SAML2/OpenID row "default" would silently
		// leave that dropdown pointing at nothing.
		$type = db_fetch_cell_prepared('SELECT type FROM login_providers WHERE id = ?', [$id]);

		if ((int) $type !== PROVIDER_TYPE_LDAP && (int) $type !== PROVIDER_TYPE_AD) {
			return;
		}

		db_execute('UPDATE login_providers SET is_default = 0');
		db_execute_prepared('UPDATE login_providers SET is_default = 1 WHERE id = ?', [$id]);
	}

	/**
	 * Group membership gate shared by every provider.
	 *
	 * An admin who leaves the "required group" field blank wants every
	 * authenticated user let in, which is the pre-existing default behavior;
	 * only a non-blank requirement narrows that down. Comparison is
	 * case-insensitive and trims incidental whitespace, since directory and
	 * claim values are frequently copy/pasted with surrounding spaces.
	 *
	 * @param array       $memberships  The group names/DNs the user is a member of.
	 * @param string|null $requiredName The configured required group, or blank/null to disable.
	 */
	protected function groupMembershipAllows(array $memberships, ?string $requiredName): bool {
		$requiredName = trim((string) $requiredName);

		if ($requiredName === '') {
			return true;
		}

		foreach ($memberships as $membership) {
			if (strcasecmp(trim((string) $membership), $requiredName) === 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether this provider can resolve a user's group membership well enough
	 * to drive automatic User Group assignment. True for every provider that
	 * reports groups from an IdP claim (SAML2/OpenID); the LDAP family narrows
	 * this to the search modes that can enumerate groups.
	 */
	public function supportsAutoAssignment(): bool {
		return true;
	}

	/**
	 * Apply this provider's automatic User Group assignments for a user who
	 * has just authenticated.
	 *
	 * Every User Group whose `auto_assignments` JSON holds a rule for THIS
	 * provider id is evaluated: when the user is a member of the configured
	 * group they are added to that Cacti User Group, and when they are not they
	 * are removed from it (so a user who loses the directory/IdP group also
	 * loses the Cacti group on their next login). User Groups with no rule for
	 * this provider are never touched, so memberships assigned by hand are
	 * preserved.
	 *
	 * @param int    $userId      The user_auth.id that just authenticated.
	 * @param string $username    The authenticated username (needed for live LDAP lookups).
	 * @param array  $memberships The group names the IdP reported (SAML2/OpenID); unused by LDAP.
	 */
	public function applyAutoAssignments(int $userId, string $username, array $memberships = []): void {
		if ($userId <= 0) {
			return;
		}

		// group_id => configured group name, for rules targeting this provider.
		$rules = $this->loadAutoAssignmentRules();

		if (!cacti_sizeof($rules)) {
			return;
		}

		$matches = $this->resolveGroupMatches($username, $memberships, array_values(array_unique($rules)));

		// A null result means membership could not be determined (e.g. the LDAP
		// directory was unreachable); leave every assignment as-is rather than
		// revoking memberships on a transient failure.
		if ($matches === null) {
			return;
		}

		$matchSet = [];

		foreach ($matches as $match) {
			$matchSet[strtolower(trim((string) $match))] = true;
		}

		$changed = false;

		foreach ($rules as $groupId => $requiredName) {
			if (isset($matchSet[strtolower(trim($requiredName))])) {
				db_execute_prepared('INSERT IGNORE INTO user_auth_group_members
					(user_id, group_id) VALUES (?, ?)',
					[$userId, $groupId]);
			} else {
				db_execute_prepared('DELETE FROM user_auth_group_members
					WHERE user_id = ?
					AND group_id = ?',
					[$userId, $groupId]);
			}

			$changed = true;
		}

		if ($changed) {
			reset_user_perms($userId);
		}
	}

	/**
	 * Load the automatic-assignment rules that target this provider.
	 *
	 * @return array<int, string> Map of user_auth_group.id => configured group name.
	 */
	protected function loadAutoAssignmentRules(): array {
		$groups = db_fetch_assoc("SELECT id, auto_assignments
			FROM user_auth_group
			WHERE auto_assignments IS NOT NULL
			AND auto_assignments != ''
			AND auto_assignments != '[]'
			AND auto_assignments != '{}'");

		$groups = is_array($groups) ? $groups : [];

		$providerKey = (string) $this->id;
		$rules       = [];

		foreach ($groups as $group) {
			$assignments = json_decode((string) $group['auto_assignments'], true);

			if (!is_array($assignments) || !array_key_exists($providerKey, $assignments)) {
				continue;
			}

			$requiredName = trim((string) $assignments[$providerKey]);

			if ($requiredName === '') {
				continue;
			}

			$rules[(int) $group['id']] = $requiredName;
		}

		return $rules;
	}

	/**
	 * Resolve which of the configured group names the user is a member of.
	 *
	 * The default (SAML2/OpenID) matches the names against the IdP-supplied
	 * claim list; LDAP overrides this to query the directory live. Returns null
	 * only when membership could not be determined at all.
	 *
	 * @param string $username    The authenticated username.
	 * @param array  $memberships The IdP-reported group names.
	 * @param array  $groupNames  The configured group names to test.
	 *
	 * @return array|null The subset of $groupNames the user belongs to, or null if undeterminable.
	 */
	protected function resolveGroupMatches(string $username, array $memberships, array $groupNames): ?array {
		$matched = [];

		foreach ($groupNames as $name) {
			if ($this->groupMembershipAllows($memberships, (string) $name)) {
				$matched[] = $name;
			}
		}

		return $matched;
	}

	/**
	 * Remove a deleted provider's rules from every User Group's
	 * `auto_assignments` JSON, so a stale provider id cannot linger and be
	 * re-evaluated should its id later be reused. Called from deleteById().
	 *
	 * @param int $id The login_providers.id being removed.
	 */
	protected static function purgeAutoAssignments(int $id): void {
		$providerKey = (string) $id;

		$groups = db_fetch_assoc("SELECT id, auto_assignments
			FROM user_auth_group
			WHERE auto_assignments IS NOT NULL
			AND auto_assignments != ''
			AND auto_assignments != '[]'
			AND auto_assignments != '{}'");

		$groups = is_array($groups) ? $groups : [];

		foreach ($groups as $group) {
			$assignments = json_decode((string) $group['auto_assignments'], true);

			if (!is_array($assignments) || !array_key_exists($providerKey, $assignments)) {
				continue;
			}

			unset($assignments[$providerKey]);

			db_execute_prepared('UPDATE user_auth_group SET auto_assignments = ? WHERE id = ?',
				[json_encode($assignments), $group['id']]);
		}
	}
}
