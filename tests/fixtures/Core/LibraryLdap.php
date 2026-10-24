<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiLibraryLdapFixture;

require_once CACTI_PATH_LIBRARY . '/ldap.php';

/** Capture the wrapper's handoff using the real transport's property contract. */
class Ldap extends \Ldap {
	public function Getcn(): array {
		$GLOBALS['library_ldap_handoff'] = get_object_vars($this);

		return $GLOBALS['library_ldap_result'];
	}
}
function run(): void {
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/ldap.php', 'cacti_ldap_search_cn', __NAMESPACE__);
	$parameters = ['username' => 'fixture-user', 'cn' => ['cn', 'mail'], 'dn' => 'uid=fixture-user,dc=example,dc=invalid',
		'host'                   => 'ldap.example.invalid', 'port' => 1389, 'port_ssl' => 1636, 'version' => 3, 'encryption' => 2, 'referrals' => 1,
		'mode'                   => 2, 'search_base' => 'dc=example,dc=invalid', 'search_filter' => '(uid=<username>)',
		'specific_dn'            => 'cn=reader,dc=example,dc=invalid', 'specific_password' => 'disposable fixture credential'];

	try {
		foreach ([
			[\LdapError::ProtocolErrorVersion, 'Unable to set version'],
			[\LdapError::ProtocolErrorReferral, 'Unable to set referrals'],
			[\LdapError::ProtocolErrorTls, 'unable to start TLS'],
			[\LdapError::ProtocolErrorBind, 'Unable to bind'],
			[\LdapError::ConnectionUnavailable, 'Unable to Connect'],
			[\LdapError::ConnectionTimeout, 'Connection Timeout'],
			[\LdapError::InsufficientAccess, 'Insufficient Access'],
			[\LdapError::SearchFoundNoGroup, 'Group DN could not be found'],
			[\LdapError::MissingLdapObject, 'Unable to create LDAP connection'],
			[987654, 'Unexpected error 987654'],
		] as [$error, $message]) {
			$details = \LdapError::GetErrorDetails($error, null, 'directory.example.invalid', 49);
			\library_expect($details['error_num'], $error, 'LDAP diagnostic preserves its domain status');
			\library_expect($details['error_ldap'], 49, 'LDAP diagnostic preserves the directory result code');
			\library_expect(str_contains($details['error_text'], $message), true, 'LDAP diagnostic distinguishes the failed operation');
			\library_expect(str_contains($details['error_text'], 'directory.example.invalid'), true, 'LDAP diagnostic identifies the attempted server');
		}

		foreach ([['cn' => 'Fixture User', 'mail' => 'fixture@example.invalid'], ['error_num' => \LdapError::ConnectionUnavailable, 'error_text' => 'Unavailable fixture directory']] as $result) {
			$GLOBALS['library_ldap_result'] = $result;
			\library_expect(cacti_ldap_search_cn(...$parameters), $result, 'Legacy LDAP wrapper preserves successful and failed transport outcomes');

			foreach ($parameters as $property => $value) {
				\library_expect($GLOBALS['library_ldap_handoff'][$property], $value, 'Legacy LDAP option reaches the real transport property: ' . $property);
			}
		}
		$GLOBALS['library_ldap_result'] = [];
		\library_expect(cacti_ldap_search_cn(''), [], 'Omitted LDAP options preserve an empty transport result');
		\library_expect($GLOBALS['library_ldap_handoff'], get_object_vars(new Ldap()), 'Omitted LDAP options preserve constructor defaults and uninitialized properties');
	} finally {
		unset($GLOBALS['library_ldap_handoff'], $GLOBALS['library_ldap_result']);
	}
}

/** Real LDAP binds, searches, and group checks against the existing E2E directory. */
function native(): void {
	$base       = 'dc=example,dc=org';
	$dn         = 'cn=ldapuser,ou=users,' . $base;
	$group      = 'coverage_' . bin2hex(random_bytes(6));
	$groupDn    = 'cn=' . $group . ',' . $base;
	$connection = ldap_connect('ldap://openldap:389');
	\library_expect($connection !== false, true, 'LDAP fixture obtains a connection');
	ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
	\library_expect(ldap_bind($connection, 'cn=admin,' . $base, 'adminpassword'), true, 'LDAP fixture binds to its disposable directory');
	$owned = false;

	try {
		$owned = ldap_add($connection, $groupDn, ['objectClass' => ['top', 'groupOfNames'], 'cn' => $group, 'member' => $dn]);
		\library_expect($owned, true, 'LDAP fixture owns its test group');
		$factory = static function () use ($base, $dn): \Ldap {
			$ldap = new \Ldap();

			foreach (['username' => 'ldapuser', 'password' => 'ldap-e2e-pass', 'dn' => $dn, 'host' => 'openldap', 'port' => 389, 'port_ssl' => 636, 'version' => 3,
				'encryption'        => 0, 'referrals' => 0, 'network_timeout' => 2, 'bind_timeout' => 2, 'group_require' => false, 'group_dn' => '', 'group_attrib' => 'member', 'group_member_type' => 1,
				'mode'              => 2, 'search_base' => $base, 'search_filter' => '(uid=<username>)', 'specific_dn' => 'cn=admin,' . $base, 'specific_password' => 'adminpassword', 'cn' => ['CN', 'mail']] as $key => $value) {
				$ldap->$key = $value;
			}

			return $ldap;
		};
		$ldap = $factory();
		\library_expect($ldap->Search()['dn'], $dn, 'Directory search returns the actual account identity');
		$ldap = $factory();
		\library_expect($ldap->Getcn()['cn'], ['CN' => 'ldapuser', 'mail' => ''], 'Attribute search preserves requested casing and absent optional mail');

		foreach ([false, true] as $requireGroup) {
			$ldap                = $factory();
			$ldap->group_require = $requireGroup;
			$ldap->group_dn      = $groupDn;
			\library_expect($ldap->Authenticate()['error_num'], $requireGroup ? \LdapError::SearchFoundNoGroup : \LdapError::Success, 'Authentication requires readable group membership when group checking is enabled');
		}
		$ldap = $factory();
		\library_expect($ldap->ResolveGroupMemberships('ldapuser', [$group, 'coverage_nonmember']), [$group], 'Group resolution admits only actual directory membership');
		$ldap           = $factory();
		$ldap->password = 'invalid-disposable-password';
		\library_expect($ldap->Authenticate()['error_num'], \LdapError::Failure, 'Directory rejects an invalid password');
		$ldap           = $factory();
		$ldap->username = 'ldapuser*)(uid=*)';
		\library_expect($ldap->Search()['error_num'], \LdapError::SearchFoundNoUserDN, 'Filter metacharacters cannot broaden the account search');
	} finally {
		if ($owned) {
			\library_expect(ldap_delete($connection, $groupDn), true, 'LDAP fixture removes only its group');
		}
		ldap_close($connection);
	}
}
