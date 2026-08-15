<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Consolidated LDAP injection regression tests.
 *
 * Covers GHSA-pmgm-67h9-59hw. Only one advisory currently falls in this
 * category, so this is a straightforward move + rename of the original
 * GhsaPmgmRegressionTest.php rather than a multi-file merge.
 *
 * Each test below keeps its original GHSA identifier in its description
 * so the advisory it guards against remains traceable.
 */

$ldapSource = file_get_contents(__DIR__ . '/../../../../lib/ldap.php');

// GHSA-pmgm: isUserInLDAPGroup() must build its filter through cacti_ldap_filter(),
// which escapes each variable with ldap_escape(..., LDAP_ESCAPE_FILTER), instead of
// interpolating $ldapUser / $groupDN directly into the filter string.
test('GHSA-pmgm: isUserInLDAPGroup routes filter through cacti_ldap_filter', function () use ($ldapSource) {
	$start = strpos($ldapSource, 'function isUserInLDAPGroup(');
	expect($start)->not->toBeFalse();

	$end  = strpos($ldapSource, "\n\t}\n", $start);
	$body = substr($ldapSource, $start, $end - $start);

	// The filter must be assembled by the escaping helper, not by raw
	// string interpolation of $ldapUser / $groupDN.
	expect($body)->toContain('cacti_ldap_filter(');
	expect($body)->toContain('(&(distinguishedName=<user>)(memberOf:1.2.840.113556.1.4.1941:=<group>))');
});

test('GHSA-pmgm: isUserInLDAPGroup passes user and group placeholders', function () use ($ldapSource) {
	$start = strpos($ldapSource, 'function isUserInLDAPGroup(');
	$end   = strpos($ldapSource, "\n\t}\n", $start);
	$body  = substr($ldapSource, $start, $end - $start);

	expect($body)->toContain("'user' => \$ldapUser");
	expect($body)->toContain("'group' => \$groupDN");
});

test('GHSA-pmgm: isUserInLDAPGroup does not interpolate user or group into filter string', function () use ($ldapSource) {
	$start = strpos($ldapSource, 'function isUserInLDAPGroup(');
	$end   = strpos($ldapSource, "\n\t}\n", $start);
	$body  = substr($ldapSource, $start, $end - $start);

	// The vulnerable pattern concatenated $ldapUser and $groupDN directly
	// into the filter. The hardened implementation must not do that.
	expect($body)->not->toContain('"(&(distinguishedName=$ldapUser)');
	expect($body)->not->toContain('"(&(distinguishedName=' . '$ldapUser');
});

test('GHSA-pmgm: cacti_ldap_filter escapes each variable with ldap_escape', function () use ($ldapSource) {
	$start = strpos($ldapSource, 'function cacti_ldap_filter(');
	expect($start)->not->toBeFalse();

	$body = substr($ldapSource, $start, 600);
	expect($body)->toContain("ldap_escape((string) \$value, '', LDAP_ESCAPE_FILTER)");
	expect($body)->toContain("str_replace('<' . \$key . '>', \$escaped, \$result)");
});
