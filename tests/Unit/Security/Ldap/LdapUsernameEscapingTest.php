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
 * The Ldap class splices the login username into the configured DN template and
 * search filter. It cleans the username with a blocklist of search filter
 * metacharacters, which is neither sufficient for a filter (a backslash escape
 * is decoded by the directory server) nor applicable to a DN (RFC 4514 has its
 * own metacharacters, none of which the blocklist touches). These tests pin the
 * per-context escaping that guards both splices.
 */

$root = dirname(__DIR__, 4);

// the cleaning the Ldap class applies before every splice
$blocklist = static fn (string $username): string => str_replace(['&', '|', '(', ')', '*', '>', '<', '!', '='], '', $username);

$dn_template = 'uid=<username>,ou=people,dc=example,dc=com';

test('the blocklist leaves every RFC 4514 DN metacharacter in place', function () use ($blocklist) {
	foreach ([',', '\\', '+', '"', ';', '#', ' admin', 'admin '] as $payload) {
		expect($blocklist('alice' . $payload))->toBe('alice' . $payload);
	}
});

test('a blocklisted username still reshapes the bind DN', function () use ($blocklist, $dn_template) {
	// a trailing backslash escapes the template's own separator, so the bind
	// lands on uid=alice,ou=people under dc=example,dc=com instead of the
	// intended entry
	$dn = str_replace('<username>', $blocklist('alice\\'), $dn_template);

	expect(ldap_explode_dn($dn, 0)['count'])->toBe(3);

	// a comma or a plus leaves a DN the server cannot parse at all
	foreach ([',ou', '+cn'] as $payload) {
		$dn = str_replace('<username>', $blocklist('alice' . $payload), $dn_template);

		expect(@ldap_explode_dn($dn, 0))->toBeFalse();
	}
});

test('escaping for the DN context confines the username to one RDN value', function () use ($blocklist, $dn_template) {
	foreach (['alice\\', 'alice,ou', 'alice+cn', 'alice"x', 'alice;x', '#alice', ' alice '] as $payload) {
		$dn    = str_replace('<username>', ldap_escape($blocklist($payload), '', LDAP_ESCAPE_DN), $dn_template);
		$parts = ldap_explode_dn($dn, 0);

		expect($parts['count'])->toBe(4)
			->and($parts[1])->toBe('ou=people')
			->and($parts[2])->toBe('dc=example')
			->and($parts[3])->toBe('dc=com');
	}
});

test('a backslash escape survives the blocklist and reaches the search filter', function () use ($blocklist) {
	// the server decodes \29 \28 \3d \2a back to ) ( = *, so this payload closes
	// the (uid=...) assertion and appends one of its own
	$payload = 'alice\\29\\28uid\\3d\\2a\\29';

	expect($blocklist($payload))->toBe($payload)
		->and(ldap_escape($payload, '', LDAP_ESCAPE_FILTER))->toBe('alice\\5c29\\5c28uid\\5c3d\\5c2a\\5c29');
});

test('every username splice in the Ldap class escapes for its own context', function () use ($root) {
	$src = file_get_contents($root . '/lib/ldap.php');

	// Authenticate(), Search() and Getcn() each build a DN; Search() and Getcn()
	// also build a search filter
	expect(substr_count($src, "str_replace('<username>', ldap_escape(\$this->username, '', LDAP_ESCAPE_DN), \$this->dn)"))->toBe(3)
		->and(substr_count($src, "str_replace('<username>', ldap_escape(\$this->username, '', LDAP_ESCAPE_FILTER), \$this->search_filter)"))->toBe(2)
		->and($src)->not->toContain("str_replace('<username>', \$this->username,");

	// the group_member_type 2 lookup uses the built DN as a filter assertion value
	expect($src)->toContain("\$filter_dn      = ldap_escape(\$this->dn, '', LDAP_ESCAPE_FILTER);")
		->and($src)->not->toContain("'(|(uid=' . \$this->dn . ')");
});
