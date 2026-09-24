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
 * Active Directory Login Provider.
 *
 * AD speaks the same LDAP protocol as generic directories, so this reuses
 * LdapLoginProvider's bind/search/group code entirely. The only difference is
 * defaulting the Full Name / Email claim attributes to AD's own conventional
 * names when the admin leaves them blank, since `displayName`/`mail` are
 * virtually universal on AD but not guessable for a first-time admin.
 */
class ActiveDirectoryLoginProvider extends LdapLoginProvider {
	protected function resolveClaims(string $server, string $username): array {
		if (trim((string) $this->param('claim_full_name')) === '' && trim((string) $this->param('claim_email')) === '') {
			$ldap           = $this->buildLdap($server);
			$ldap->username = $username;
			$ldap->cn       = ['displayName', 'mail'];

			$response = $ldap->Getcn();
			$cn       = $response['cn'] ?? [];

			return [
				'full_name' => $cn['displayName'] ?? '',
				'email'     => $cn['mail'] ?? '',
			];
		}

		return parent::resolveClaims($server, $username);
	}
}
