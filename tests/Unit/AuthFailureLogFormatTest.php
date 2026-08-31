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
 * auth_log_failure() emits a fixed, machine-parseable line for fail2ban on every
 * failed authentication. The grammar and the bundled fail2ban filter have to stay
 * in lock-step, and the line must fire from the universal login-failure choke point
 * and the 2FA failure branch. A crafted username must not be able to forge fields
 * or extra log lines.
 */

$authSrc  = file_get_contents(CACTI_PATH_LIBRARY . '/auth.php');
$loginSrc = file_get_contents(CACTI_PATH_BASE . '/auth_login.php');
$twofaSrc = file_get_contents(CACTI_PATH_BASE . '/auth_2fa.php');
$filter   = file_get_contents(CACTI_PATH_BASE . '/misc/fail2ban/cacti.conf');

// The fail2ban <HOST> token, expanded to a PCRE that matches an IPv4/IPv6 address.
function _f2b_regex(string $filter): string {
	expect($filter)->toContain('failregex =');

	if (!preg_match('/^failregex\s*=\s*(.+)$/m', $filter, $m)) {
		throw new RuntimeException('no failregex in filter');
	}

	$host = '(?P<host>[0-9]{1,3}(?:\.[0-9]{1,3}){3}|[0-9A-Fa-f:]+)';

	return '/' . str_replace('<HOST>', $host, trim($m[1])) . '/';
}

test('the structured grammar is fixed and documented in one place', function () use ($authSrc) {
	// exactly one sprintf template, so the filter can rely on it
	expect($authSrc)->toContain('AUTH FAILURE user="%s" realm="%s" ip="%s" reason="%s"');
	expect(substr_count($authSrc, 'AUTH FAILURE user="%s"'))->toBe(1);

	// the helper reads the validated, proxy-aware address
	$start = strpos($authSrc, 'function auth_log_failure(');
	$body  = substr($authSrc, $start, strpos($authSrc, "\n}", $start) - $start);
	expect($body)->toContain('get_client_addr()');
});

test('the failure line fires from the universal choke point and the 2FA branch', function () use ($loginSrc, $twofaSrc) {
	// one emission per login attempt, at auth_login.php's generic failure exit
	expect($loginSrc)->toContain("auth_log_failure(\$username, auth_realm_token((int) \$frv_realm), !empty(\$id) ? 'bad_password' : 'no_such_user')");
	// a failed second factor is a brute-force signal too
	expect($twofaSrc)->toContain("auth_log_failure(\$user['username'], auth_realm_token((int) (\$user['realm'] ?? 0)), '2fa')");
});

test('auth_process_lockout does not emit its own structured line (no double count)', function () use ($authSrc) {
	$start = strpos($authSrc, 'function auth_process_lockout(');
	$body  = substr($authSrc, $start, strpos($authSrc, "\nfunction ", $start + 1) - $start);

	// local failures reach both auth_process_lockout and the auth_login.php choke
	// point; only the latter emits, so fail2ban counts one failure per attempt
	expect($body)->not->toContain('auth_log_failure(');
});

test('the bundled fail2ban filter matches the emitted line and extracts the IP', function () use ($filter) {
	$regex = _f2b_regex($filter);
	$stamp = '2026-08-13 09:15:42 - ';

	$v4 = $stamp . 'AUTH FAILURE user="alice" realm="local" ip="203.0.113.7" reason="bad_password"';
	$v6 = $stamp . 'AUTH FAILURE user="bob" realm="ldap" ip="2001:db8::42" reason="2fa"';

	expect(preg_match($regex, $v4, $m4))->toBe(1);
	expect($m4['host'])->toBe('203.0.113.7');
	expect(preg_match($regex, $v6, $m6))->toBe(1);
	expect($m6['host'])->toBe('2001:db8::42');
});

test('the filter does not match successful logins or unrelated lines', function () use ($filter) {
	$regex = _f2b_regex($filter);

	expect(preg_match($regex, "2026-08-13 09:15:42 - AUTH LOGIN: User 'alice' authenticated from IP address '203.0.113.7'"))->toBe(0);
	expect(preg_match($regex, '2026-08-13 09:15:42 - SYSTEM STATS: Time:1.0'))->toBe(0);
});

test('a crafted username cannot forge fields or inject a second line', function () use ($authSrc, $filter) {
	// mirror the helper's cleaning: strip quotes and control characters
	$clean = static fn (string $v): string => preg_replace('/[\x00-\x1f\x7f"]/', '', $v);

	$evil = "x\" ip=\"1.2.3.4\" reason=\"bad_password\"\n2026-01-01 00:00:00 - AUTH FAILURE user=\"y";
	$line = '2026-08-13 09:15:42 - ' . sprintf('AUTH FAILURE user="%s" realm="%s" ip="%s" reason="%s"', $clean($evil), 'local', '203.0.113.7', 'bad_password');

	// the cleaned username has no quote, CR/LF or control chars left
	expect($clean($evil))->not->toContain('"');
	expect(preg_match('/[\x00-\x1f\x7f]/', $clean($evil)))->toBe(0);

	// and the resulting single line still parses to the real attacker IP
	$regex = _f2b_regex($filter);
	expect(preg_match($regex, $line, $m))->toBe(1);
	expect($m['host'])->toBe('203.0.113.7');
});
