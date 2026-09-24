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
	public static function collectParameters(): array {
		// ================= input validation =================
		gfrv('port');
		gfrv('port_ssl');
		gfrv('proto_version');
		gfrv('encryption');
		gfrv('tls_certificate');
		gfrv('referrals');
		gfrv('mode');
		gfrv('group_member_type');
		// ====================================================

		return [
			// no-validation: admin-entered LDAP server hostname(s)/IPs, space delimited
			'server'            => form_input_validate(gnrv('server'), 'server', '', false, 3),
			'port'              => (int) gnrv('port'),
			'port_ssl'          => (int) gnrv('port_ssl'),
			'proto_version'     => (int) gnrv('proto_version'),
			'network_timeout'   => (int) gnrv('network_timeout'),
			'bind_timeout'      => (int) gnrv('bind_timeout'),
			'encryption'        => (int) gnrv('encryption'),
			'tls_certificate'   => (int) gnrv('tls_certificate'),
			'referrals'         => (int) gnrv('referrals'),
			'mode'              => (int) gnrv('mode'),
			// DN template with a <username> placeholder; ldap_escape() downstream
			// (lib/ldap.php) escapes the substituted value before use.
			// no-validation: DN template, escaped downstream before use
			'dn'                => form_input_validate(gnrv('dn'), 'dn', '', true, 3),
			// A blank group_dn is the "no restriction" behavior; there is no
			// separate enable/disable checkbox for this.
			// no-validation: LDAP group DN, admin-entered directory identifier
			'group_dn'          => form_input_validate(gnrv('group_dn'), 'group_dn', '', true, 3),
			// no-validation: LDAP attribute name, admin-entered directory schema value
			'group_attrib'      => form_input_validate(gnrv('group_attrib'), 'group_attrib', '', true, 3),
			'group_member_type' => (int) gnrv('group_member_type'),
			// no-validation: LDAP search base DN, admin-entered directory identifier
			'search_base'       => form_input_validate(gnrv('search_base'), 'search_base', '', true, 3),
			// LDAP search filter template; ldap_escape() downstream (lib/ldap.php)
			// escapes the substituted value before use.
			// no-validation: search filter template, escaped downstream before use
			'search_filter'     => form_input_validate(gnrv('search_filter'), 'search_filter', '', true, 3),
			// no-validation: LDAP bind DN for the specific-search mode, admin-entered
			'specific_dn'       => form_input_validate(gnrv('specific_dn'), 'specific_dn', '', true, 3),
			// no-validation: LDAP bind password, an arbitrary secret with no format to enforce
			'specific_password' => form_input_validate(gnrv('specific_password'), 'specific_password', '', true, 3),
			'claim_full_name'   => gnrv('claim_full_name'),
			'claim_email'       => gnrv('claim_email'),
		];
	}

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
			$search           = $this->buildLdap($server);
			$search->username = $username;

			$response = $search->Search();

			if (($response['error_num'] ?? null) === 0) {
				$dn = $response['dn'];

				break;
			}
		}

		if ($dn === '') {
			return LoginResult::failure(__('Access Denied!  Login Failed.'));
		}

		foreach ($servers as $server) {
			$ldap           = $this->buildLdap($server);
			$ldap->username = $username;
			$ldap->password = $password;
			$ldap->dn       = $dn;

			// Presence of a required group, not a separate checkbox, turns the gate on.
			$ldap->group_require = trim((string) $this->param('group_dn')) !== '';

			$response = $ldap->Authenticate();

			if (($response['error_num'] ?? null) === 0) {
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

		$ldap           = $this->buildLdap($server);
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
		// Ldap has no DB lookup of its own; every property below is set from
		// our already-decoded `parameters` JSON.
		$ldap = new Ldap();

		$ldap->host               = $server;
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
