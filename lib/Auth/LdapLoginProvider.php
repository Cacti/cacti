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

use Ldap;

/**
 * LDAP Login Provider.
 *
 * A thin adapter around the legacy `Ldap` connection class (lib/ldap.php),
 * which keeps its mature/tested bind, search, TLS and group-membership code
 * untouched. This class only translates the new `login_providers.parameters`
 * JSON into the properties that `Ldap` expects, and drives the
 * search -> authenticate -> claims sequence that used to live in the
 * procedural domains_login_process()/domains_ldap_*() functions.
 */
class LdapLoginProvider extends AbstractLoginProvider implements CredentialLoginProviderInterface {
	public function authenticate(string $username, string $password): LoginResult {
		if ($username === '') {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		$servers = preg_split('/\s+/', trim((string) $this->param('server')));

		if (!is_array($servers) || $servers === ['']) {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		// Resolve the bind DN first (anonymous/specific search modes need it).
		$dn = '';

		foreach ($servers as $server) {
			$search = $this->buildLdap($server);
			$search->username = $username;

			$response = $search->Search();

			if (($response['error_num'] ?? null) === '0') {
				$dn = $response['dn'];

				break;
			}
		}

		if ($dn === '') {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		foreach ($servers as $server) {
			$ldap = $this->buildLdap($server);
			$ldap->username = $username;
			$ldap->password = $password;
			$ldap->dn       = $dn;

			// Presence of a required group, not a separate checkbox, turns the gate on.
			$ldap->group_require = trim((string) $this->param('group_dn')) !== '' ? 1 : 0;

			$response = $ldap->Authenticate();

			if (($response['error_num'] ?? null) === '0') {
				return LoginResult::authenticated($username, $this->resolveClaims($server, $username));
			}
		}

		return LoginResult::failure(__('Access Denied!  Login Failed.'));
	}

	/**
	 * @return array{full_name: string, email: string}
	 */
	protected function resolveClaims(string $server, string $username): array {
		$fullNameAttr = (string) $this->param('claim_full_name');
		$emailAttr    = (string) $this->param('claim_email');

		if ($fullNameAttr === '' && $emailAttr === '') {
			return ['full_name' => '', 'email' => ''];
		}

		$ldap = $this->buildLdap($server);
		$ldap->username = $username;
		$ldap->cn       = [$fullNameAttr, $emailAttr];

		$response = $ldap->Getcn();

		$cn = $response['cn'] ?? [];

		return [
			'full_name' => $cn[$fullNameAttr] ?? '',
			'email'     => $cn[$emailAttr] ?? '',
		];
	}

	protected function buildLdap(string $server): Ldap {
		// domain_id 0 skips Ldap's own DB lookup; every property is set from
		// our already-decoded `parameters` JSON below instead.
		$ldap = new Ldap(0);

		$ldap->host              = $server;
		$ldap->port               = (int) $this->param('port', 389);
		$ldap->port_ssl           = (int) $this->param('port_ssl', 636);
		$ldap->version            = (int) $this->param('proto_version', 3);
		$ldap->network_timeout    = (int) $this->param('network_timeout', 2);
		$ldap->bind_timeout       = (int) $this->param('bind_timeout', 2);
		$ldap->encryption         = (int) $this->param('encryption', 0);
		$ldap->tls_certificate    = (int) $this->param('tls_certificate', LDAP_OPT_X_TLS_DEMAND);
		$ldap->referrals          = (int) $this->param('referrals', 0);
		$ldap->debug              = ($this->parameters['debug'] ?? '') === 'on' ? POLLER_VERBOSITY_LOW : POLLER_VERBOSITY_HIGH;
		$ldap->dn                 = (string) $this->param('dn');

		$ldap->group_dn           = (string) $this->param('group_dn');
		$ldap->group_attrib       = (string) $this->param('group_attrib');
		$ldap->group_member_type  = (int) $this->param('group_member_type', 1);

		$ldap->mode               = (int) $this->param('mode', 0);
		$ldap->search_base        = (string) $this->param('search_base');
		$ldap->search_filter      = (string) $this->param('search_filter');
		$ldap->specific_dn        = (string) $this->param('specific_dn');
		$ldap->specific_password  = (string) $this->param('specific_password');

		$ldap->cn_full_name       = (string) $this->param('claim_full_name');
		$ldap->cn_email           = (string) $this->param('claim_email');

		return $ldap;
	}
}
