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
