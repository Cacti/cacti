<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Runs inside the Cacti web container. Verifies the maintenance-lockout CSRF/POST
 * guard (support.php, #7352) over real HTTP: a GET to action=lockout, and a POST
 * without a valid __csrf_magic token, must both be refused without changing
 * cacti_lockout_status. The probe asserts only these refusal cases; it does not
 * perform a real toggle, so it never leaves the system in a locked-out state.
 *
 * Usage (inside the container):
 *   BASE_URL=http://localhost/cacti php support_lockout_probe.php
 */

chdir(dirname(__DIR__, 2));

require_once dirname(__DIR__, 2) . '/include/global.php';

$base = rtrim(getenv('BASE_URL') ?: 'http://localhost/cacti', '/');
$user = getenv('CACTI_USER') ?: 'admin';
$pass = getenv('CACTI_PASS') ?: 'admin';
$jar  = tempnam(sys_get_temp_dir(), 'cacticookie');

if ($jar === false) {
	fwrite(STDERR, "FAIL: could not create a cookie-jar temp file\n");
	exit(1);
}

// Runs on every exit path, including lockout_probe_fail()'s exit(1), so the
// jar never lingers in the temp directory after a failed run.
register_shutdown_function(static function () use ($jar): void {
	@unlink($jar);
});

function lockout_probe_fail(string $message): void {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function lockout_probe_request(string $url, string $jar, ?array $post = null): array {
	$ch = curl_init($url);

	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_COOKIEJAR      => $jar,
		CURLOPT_COOKIEFILE     => $jar,
		CURLOPT_TIMEOUT        => 15,
	]);

	if ($post !== null) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	}

	$body = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	curl_close($ch);

	return [(int) $code, (string) $body];
}

function lockout_probe_csrf(string $html): string {
	// csrf-magic embeds the token in a hidden field and a JS variable.
	if (preg_match('/name="__csrf_magic"\s+value="([^"]+)"/', $html, $m)) {
		return $m[1];
	}

	if (preg_match("/csrfMagicToken\s*=\s*\"([^\"]+)\"/", $html, $m)) {
		return $m[1];
	}

	return '';
}

// Authenticate.
[, $loginHtml] = lockout_probe_request("$base/index.php", $jar);
$token = lockout_probe_csrf($loginHtml);

if ($token === '') {
	lockout_probe_fail('could not extract a CSRF token from the login page');
}

lockout_probe_request("$base/index.php", $jar, [
	'__csrf_magic' => $token,
	'action'       => 'login',
	'login_username' => $user,
	'login_password' => $pass,
]);

// Without this, a failed login (bad credentials, CSRF rejected) would still
// "pass" below: the lockout requests would just bounce off auth instead of
// the guard under test, leaving cacti_lockout_status untouched either way.
[, $sessionHtml] = lockout_probe_request("$base/index.php", $jar);

if (str_contains($sessionHtml, 'name="login_username"')) {
	lockout_probe_fail('login did not authenticate the session (login form still shown)');
}

$before = read_config_option('cacti_lockout_status', true);

// A GET must be refused: the guard redirects before any state change.
lockout_probe_request("$base/support.php?action=lockout", $jar);
$after_get = read_config_option('cacti_lockout_status', true);
if ($after_get !== $before) {
	lockout_probe_fail('GET to action=lockout changed the lockout state');
}

// A POST without the CSRF token must be refused by csrf_check().
lockout_probe_request("$base/support.php?action=lockout", $jar, ['expected' => 'unlocked']);
$after_post = read_config_option('cacti_lockout_status', true);
if ($after_post !== $before) {
	lockout_probe_fail('POST without a CSRF token changed the lockout state');
}

print "PASS support lockout csrf docker probe\n";
