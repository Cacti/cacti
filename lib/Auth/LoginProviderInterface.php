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
 * Common contract for every Login Provider (LDAP, Active Directory, SAML2, OpenID).
 *
 * A provider is either a CredentialLoginProviderInterface (username+password is
 * available synchronously) or a RedirectLoginProviderInterface (the browser must
 * round-trip through an external IdP first). This interface only carries what
 * every provider has in common, regardless of flow.
 */
interface LoginProviderInterface {
	/** One of the PROVIDER_TYPE_* constants from include/global_constants.php. */
	public function getType(): int;

	public function getId(): int;

	public function getName(): string;

	public function isEnabled(): bool;

	/**
	 * Whether the admin allows "remember me" auth cookies for this specific provider.
	 * The global auth_cache_enabled setting is still checked first by the caller.
	 */
	public function allowsAuthCookies(): bool;
}
