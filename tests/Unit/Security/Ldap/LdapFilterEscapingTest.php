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

/*
 * cacti_ldap_filter() assembles LDAP filters from a template so a username
 * carrying filter metacharacters is matched as a literal rather than changing
 * the filter's structure. These tests call the function; they do not inspect
 * the text of lib/ldap.php, because a source match certifies spelling rather
 * than behaviour.
 */

require_once CACTI_PATH_LIBRARY . '/ldap.php';

test('a plain username passes through unchanged', function () {
	expect(cacti_ldap_filter('(uid=<user>)', ['user' => 'alice']))->toBe('(uid=alice)');
});

test('a repeated placeholder is substituted at every position', function () {
	$filter = cacti_ldap_filter('(|(uid=<dn>)(cn=<dn>)(userPrincipalName=<dn>))', ['dn' => 'bob']);

	expect($filter)->toBe('(|(uid=bob)(cn=bob)(userPrincipalName=bob))');
});

test('filter metacharacters are escaped, not deleted', function (string $raw, string $escaped) {
	expect(cacti_ldap_filter('(uid=<user>)', ['user' => $raw]))->toBe('(uid=' . $escaped . ')');
})->with([
	['a*b',   'a\\2ab'],
	['a(b)c', 'a\\28b\\29c'],
	['x\\y',  'x\\5cy'],
	['a&b',   'a&b'],
]);

test('a value cannot close the filter and append a clause', function () {
	$filter = cacti_ldap_filter('(&(uid=<user>)(objectClass=person))', ['user' => 'a)(uid=*']);

	// The parenthesis and asterisk survive as escaped literals, so the filter
	// still has exactly the two clauses the template declared.
	expect($filter)->toBe('(&(uid=a\\29\\28uid=\\2a)(objectClass=person))');
	expect(substr_count($filter, '(uid='))->toBe(1);
});

test('a value is never rescanned as another placeholder', function () {
	// LDAP_ESCAPE_FILTER leaves '<' and '>' intact, so substituting one key at a
	// time into the accumulating result would let the text inserted for <x> be
	// picked up as the <y> placeholder on the next iteration.
	$filter = cacti_ldap_filter('(&(a=<x>)(b=<y>))', ['x' => '<y>', 'y' => 'secret']);

	expect($filter)->toBe('(&(a=<y>)(b=secret))');
	expect($filter)->not->toContain('(a=secret)');
});

test('a value carrying a real placeholder name survives as a literal', function () {
	$filter = cacti_ldap_filter(
		'(&(distinguishedName=<user>)(memberOf:1.2.840.113556.1.4.1941:=<group>))',
		['user' => '<group>', 'group' => 'CN=Domain Admins,DC=x']
	);

	expect($filter)->toBe('(&(distinguishedName=<group>)(memberOf:1.2.840.113556.1.4.1941:=CN=Domain Admins,DC=x))');
});

test('an unknown placeholder is left alone', function () {
	expect(cacti_ldap_filter('(uid=<user>)', ['other' => 'x']))->toBe('(uid=<user>)');
});

test('non-string values are coerced before escaping', function () {
	expect(cacti_ldap_filter('(uidNumber=<n>)', ['n' => 1234]))->toBe('(uidNumber=1234)');
});

/*
 * DN context has its own reserved set, so it needs LDAP_ESCAPE_DN rather than
 * the filter escaper. A comma terminates an RDN, which is why it has to be
 * escaped rather than passed through.
 */
test('DN reserved characters are escaped for DN context', function (string $raw, string $escaped) {
	expect(ldap_escape($raw, '', LDAP_ESCAPE_DN))->toBe($escaped);
})->with([
	['a,ou=other', 'a\\2cou\\3dother'],
	['a+b',        'a\\2bb'],
	['x\\y',       'x\\5cy'],
]);
