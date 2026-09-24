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

	/** @var array<string, mixed> Decoded `parameters` JSON for this provider row. */
	protected readonly array $parameters;

	/**
	 * @param array $row A row from the `login_providers` table.
	 */
	public function __construct(array $row) {
		$this->id               = (int) ($row['id'] ?? 0);
		$this->name              = (string) ($row['name'] ?? '');
		$this->type              = (int) ($row['type'] ?? 0);
		$this->enabled           = ($row['enabled'] ?? '') === 'on';
		$this->allowAuthCookies  = ($row['allow_auth_cookies'] ?? 'on') === 'on';
		$this->templateUserId    = (int) ($row['user_id'] ?? 0);

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

		if ($id && (int) $general['type'] !== PROVIDER_TYPE_SAML2 && (int) $general['type'] !== PROVIDER_TYPE_OPENID) {
			// LDAP/AD copy a template account rather than authenticate it directly.
			db_execute_prepared('UPDATE user_auth
				SET enabled = ""
				WHERE id = ?',
				[$general['user_id']]);
		}

		return $id;
	}

	public static function deleteById(int $id): void {
		db_execute_prepared('DELETE FROM login_providers WHERE id = ?', [$id]);
	}

	public static function enableById(int $id): void {
		db_execute_prepared('UPDATE login_providers SET enabled = "on" WHERE id = ?', [$id]);
	}

	public static function disableById(int $id): void {
		db_execute_prepared('UPDATE login_providers SET enabled = "" WHERE id = ?', [$id]);
	}

	public static function makeDefaultById(int $id): void {
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
}
