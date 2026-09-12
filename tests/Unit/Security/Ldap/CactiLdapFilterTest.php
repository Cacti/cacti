<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 3) . '/Helpers/DomainsLoginProcessHarness.php';

$cactiLdapFilterSource = file_get_contents(dirname(__DIR__, 4) . '/lib/ldap.php');

test('cacti_ldap_filter is defined in lib/ldap.php', function () use ($cactiLdapFilterSource) {
	expect(strpos($cactiLdapFilterSource, 'function cacti_ldap_filter('))->not->toBeFalse();
});

test('cacti_ldap_filter escapes parentheses asterisks and backslashes', function () use ($cactiLdapFilterSource) {
	cacti_test_load_cacti_ldap_filter($cactiLdapFilterSource);

	$filter = cacti_ldap_filter('(uid=<username>)', ['username' => 'a)(|(uid=*']);

	expect($filter)->toBe('(uid=' . ldap_escape('a)(|(uid=*', '', LDAP_ESCAPE_FILTER) . ')');
	expect($filter)->not->toContain('(|(uid=*');
});

test('cacti_ldap_filter substitutes every placeholder independently', function () use ($cactiLdapFilterSource) {
	cacti_test_load_cacti_ldap_filter($cactiLdapFilterSource);

	$filter = cacti_ldap_filter(
		'(&(distinguishedName=<user>)(memberOf:1.2.840.113556.1.4.1941:=<group>))',
		['user' => 'cn=alice,dc=ex', 'group' => 'cn=admins,dc=ex']
	);

	expect($filter)->toContain('distinguishedName=' . ldap_escape('cn=alice,dc=ex', '', LDAP_ESCAPE_FILTER));
	expect($filter)->toContain('1941:=' . ldap_escape('cn=admins,dc=ex', '', LDAP_ESCAPE_FILTER));
});

test('cacti_ldap_filter stringifies non-string values before escaping', function () use ($cactiLdapFilterSource) {
	cacti_test_load_cacti_ldap_filter($cactiLdapFilterSource);

	expect(cacti_ldap_filter('(uid=<id>)', ['id' => 42]))->toBe('(uid=42)');
});

test('a value is never rescanned as another placeholder', function () use ($cactiLdapFilterSource) {
	cacti_test_load_cacti_ldap_filter($cactiLdapFilterSource);

	$filter = cacti_ldap_filter('(&(a=<x>)(b=<y>))', ['x' => '<y>', 'y' => 'secret']);

	expect($filter)->toBe('(&(a=<y>)(b=secret))');
	expect($filter)->not->toContain('(a=secret)');
});
