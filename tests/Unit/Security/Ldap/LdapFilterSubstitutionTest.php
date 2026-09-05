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
 * cacti_ldap_filter() substitutes every placeholder in one pass. Substituting
 * one key at a time into the accumulating result would let the text inserted
 * for one key be rescanned as another key's placeholder, because
 * LDAP_ESCAPE_FILTER leaves '<' and '>' intact.
 *
 * These tests call the function rather than matching against the text of
 * lib/ldap.php, so they fail if the behaviour regresses however it is written.
 */

require_once __DIR__ . '/../../../../lib/ldap.php';

test('a plain value is substituted unchanged', function () {
	expect(cacti_ldap_filter('(uid=<user>)', array('user' => 'alice')))->toBe('(uid=alice)');
});

test('a repeated placeholder is substituted at every position', function () {
	expect(cacti_ldap_filter('(|(uid=<dn>)(cn=<dn>))', array('dn' => 'bob')))
		->toBe('(|(uid=bob)(cn=bob))');
});

test('filter metacharacters are escaped', function ($raw, $escaped) {
	expect(cacti_ldap_filter('(uid=<user>)', array('user' => $raw)))->toBe('(uid=' . $escaped . ')');
})->with(array(
	array('a*b',   'a\\2ab'),
	array('a(b)c', 'a\\28b\\29c'),
	array('x\\y',  'x\\5cy'),
));

test('a value cannot close the filter and append a clause', function () {
	$filter = cacti_ldap_filter('(&(uid=<user>)(objectClass=person))', array('user' => 'a)(uid=*'));

	expect($filter)->toBe('(&(uid=a\\29\\28uid=\\2a)(objectClass=person))');
	expect(substr_count($filter, '(uid='))->toBe(1);
});

test('a value is never rescanned as another placeholder', function () {
	$filter = cacti_ldap_filter('(&(a=<x>)(b=<y>))', array('x' => '<y>', 'y' => 'secret'));

	expect($filter)->toBe('(&(a=<y>)(b=secret))');
	expect($filter)->not->toContain('(a=secret)');
});

test('a value naming a real placeholder survives as a literal', function () {
	$filter = cacti_ldap_filter(
		'(&(distinguishedName=<user>)(memberOf:1.2.840.113556.1.4.1941:=<group>))',
		array('user' => '<group>', 'group' => 'CN=Domain Admins,DC=x')
	);

	expect($filter)->toBe('(&(distinguishedName=<group>)(memberOf:1.2.840.113556.1.4.1941:=CN=Domain Admins,DC=x))');
});

test('an empty variable list returns the template unchanged', function () {
	expect(cacti_ldap_filter('(uid=<user>)', array()))->toBe('(uid=<user>)');
});

test('a value with a __toString is coerced before escaping', function () {
	$value = new class {
		public function __toString() {
			return 'a*b';
		}
	};

	expect(cacti_ldap_filter('(uid=<user>)', array('user' => $value)))->toBe('(uid=a\\2ab)');
});

test('an unknown placeholder is left alone', function () {
	expect(cacti_ldap_filter('(uid=<user>)', array('other' => 'x')))->toBe('(uid=<user>)');
});
