<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Ldap::BuildGroupMembershipQueries() turns a raw, admin-entered group name
 * (e.g. "Cacti Admins") into the directory probes used for automatic User
 * Group assignment. The Active Directory handling is the subtle part: AD group
 * objects are objectClass=group, matched by neither posixGroup nor
 * groupOfNames, so AD needs its own cn-scoped nested-membership query. These
 * tests exercise the query construction directly (no live directory).
 */

$repoRoot = dirname(__DIR__, 4);
require_once $repoRoot . '/lib/ldap.php';

/**
 * Invoke the protected query builder without touching the (connection-oriented)
 * constructor.
 */
function gmq_build(string $group, bool $is_ad, string $user_dn = 'CN=bob,CN=Users,DC=example,DC=com'): array {
	$ldap        = (new ReflectionClass(Ldap::class))->newInstanceWithoutConstructor();
	$method      = new ReflectionMethod(Ldap::class, 'BuildGroupMembershipQueries');
	$method->setAccessible(true);
	$user_dn_esc = $user_dn !== '' ? ldap_escape($user_dn, '', LDAP_ESCAPE_FILTER) : '';

	return $method->invoke($ldap, 'DC=example,DC=com', 'bob', $user_dn, $user_dn_esc, $group, $is_ad);
}

test('a bare group name on Active Directory issues a direct and nested member-chain query', function () {
	if (!function_exists('ldap_escape')) {
		$this->markTestSkipped('ldap extension not loaded');
	}

	$filters = implode("\n", array_column(gmq_build('Cacti Admins', true), 'filter'));

	expect($filters)->toContain('objectClass=group');
	expect($filters)->toContain('member:1.2.840.113556.1.4.1941:=');
	// The AD probe is cn-scoped to the raw name, not a DN the admin had to know.
	expect($filters)->toContain('cn=Cacti Admins');
});

test('a bare group name still covers posixGroup and groupOfNames shapes', function () {
	if (!function_exists('ldap_escape')) {
		$this->markTestSkipped('ldap extension not loaded');
	}

	$filters = implode("\n", array_column(gmq_build('Cacti Admins', false), 'filter'));

	expect($filters)->toContain('objectClass=posixGroup');
	expect($filters)->toContain('memberUid=bob');
	expect($filters)->toContain('groupOfNames');
	expect($filters)->toContain('groupOfUniqueNames');
	// No AD-only matching rule is sent to a non-AD directory.
	expect($filters)->not->toContain('1.2.840.113556.1.4.1941');
});

test('a full group DN uses exact base-scope reads including the AD nested memberOf rule', function () {
	if (!function_exists('ldap_escape')) {
		$this->markTestSkipped('ldap extension not loaded');
	}

	$queries = gmq_build('CN=Cacti Admins,OU=Groups,DC=example,DC=com', true);
	$filters = implode("\n", array_column($queries, 'filter'));

	expect($filters)->toContain('memberOf=');
	expect($filters)->toContain('memberOf:1.2.840.113556.1.4.1941:=');
	// DN mode reads exact entries rather than subtree-searching.
	expect(array_column($queries, 'read'))->toContain(true);
});

test('an unresolved user DN still yields a posixGroup probe but no member/uniqueMember probe', function () {
	if (!function_exists('ldap_escape')) {
		$this->markTestSkipped('ldap extension not loaded');
	}

	$filters = implode("\n", array_column(gmq_build('Cacti Admins', false, ''), 'filter'));

	expect($filters)->toContain('objectClass=posixGroup');
	// member/uniqueMember checks require a resolved user DN.
	expect($filters)->not->toContain('uniqueMember=');
});
