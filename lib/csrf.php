<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * CSRF token issuance and validation for Cacti.
 *
 * Replaces the token core of the vendored csrf-magic fork.  Tokens are random
 * values held in $_SESSION by Symfony rather than HMACs over a secret file.
 * Phase 1 mints every token under a single intention, reproducing csrf-magic's
 * "one token, valid anywhere" behaviour so existing forms and third-party
 * plugins keep working unchanged.
 */
final class CactiCsrfGuard {
	/**
	 * The single token scope used in phase 1.  Per-form intentions arrive in
	 * phase 2; until then every token is minted and checked under this id.
	 */
	public const INTENTION_GLOBAL = 'cacti';

	private ?CsrfTokenManagerInterface $manager;

	private bool $enabled;

	/**
	 * @param CsrfTokenManagerInterface|null $manager Null when Symfony is not
	 *   available, which happens during a fresh install before composer has
	 *   populated include/vendor.
	 * @param bool $enabled False on the CLI, where there is no session and no
	 *   request to protect.
	 */
	public function __construct(?CsrfTokenManagerInterface $manager = null, bool $enabled = true) {
		$this->manager = $manager;
		$this->enabled = $enabled && $manager !== null;
	}

	/**
	 * @return bool True when tokens will actually be issued and checked.
	 */
	public function isEnabled() : bool {
		return $this->enabled;
	}

	/**
	 * Issue a token for the current session.
	 *
	 * Symfony returns a freshly randomised encoding of one stored secret on
	 * every call, so successive calls return different strings that all
	 * validate.  That is deliberate BREACH mitigation.
	 *
	 * @return string The token, or an empty string when the guard is disabled.
	 */
	public function token() : string {
		if (!$this->enabled) {
			return '';
		}

		return $this->manager->getToken(self::INTENTION_GLOBAL)->getValue();
	}

	/**
	 * @param string $value The submitted token.
	 *
	 * @return bool True only when the token is valid for this session.
	 */
	public function validate(string $value) : bool {
		if (!$this->enabled || $value === '') {
			return false;
		}

		return $this->manager->isTokenValid(new CsrfToken(self::INTENTION_GLOBAL, $value));
	}
}
