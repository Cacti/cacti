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
 * Nothing regenerated the session id when a session became authenticated.
 * auth_login.php, the remember-me restore and the basic-auth branch each set
 * the session user id straight onto whatever id the request arrived with, so
 * an id planted before the login stayed valid after it.
 *
 * 1.2.x closed this under GHSA-273r-qr93-wgcp with cacti_auth_transition().
 * The helper never reached this branch, so these tests pin both the helper and
 * the three call sites that have to use it.
 */

$authSource      = file_get_contents(dirname(__DIR__, 4) . '/lib/auth.php');
$includeSource   = file_get_contents(dirname(__DIR__, 4) . '/include/auth.php');
$loginSource     = file_get_contents(dirname(__DIR__, 4) . '/auth_login.php');

// --- the helper ---

test('the transition helper issues a new session id', function () use ($authSource) {
	expect($authSource)->toContain('function cacti_auth_transition(int $user_id, string $reason');
	expect($authSource)->toContain('if (!session_regenerate_id(true)) {')
		->and($authSource)->toContain('SECURITY: auth transition blocked because session regeneration failed')
		->and(strpos($authSource, 'return false;', strpos($authSource, 'if (!session_regenerate_id(true)) {')))
		->toBeLessThan(strpos($authSource, 'kill_session_var(SESS_USER_REALMS);'));
});

test('the transition helper fails closed for a missing or locked account', function () use ($authSource) {
	expect($authSource)->toContain("if (\$locked === false || \$locked == 'on') {");
	expect($authSource)->toContain('SECURITY: auth transition blocked for unavailable or locked user');
});

test('the transition helper drops the cached permissions', function () use ($authSource) {
	foreach ([
		'SESS_USER_REALMS',
		'SESS_AUTH_NAMES',
		'SESS_TREE_PERMS',
		'SESS_SIMPLE_PERMS',
		'SESS_SIMPLE_TEMPLATE_PERMS',
		'SESS_USER_PERMS_KEY',
		'SESS_USER_2FA',
		'OPTIONS_USER',
		'OPTIONS_WEB',
	] as $cache) {
		expect($authSource)->toContain("kill_session_var($cache);");
	}
});

// --- the call sites ---

test('the remember-me restore transitions before trusting the cookie', function () use ($includeSource) {
	expect($includeSource)->toContain("cacti_auth_transition((int) \$cookie_user, 'cookie_restore')");
	expect($includeSource)->not->toContain("if (\$cookie_user !== false) {\n\t\t\t\$_SESSION[SESS_USER_ID]");
});

test('remember-me authentication rejects locked users before rotation and clears a raced cookie', function () use ($authSource, $includeSource) {
	expect($authSource)->toContain("(\$user_info['locked'] ?? '') == 'on'")
		->and($authSource)->toContain('if (!auth_cookie_user_currently_allowed($user_info))')
		->and($authSource)->toContain('set_auth_cookie($user_info);')
		->and(strpos($authSource, 'auth_cookie_user_currently_allowed($user_info)'))
		->toBeLessThan(strpos($authSource, 'set_auth_cookie($user_info);'))
		->and($includeSource)->toContain("} else {\n\t\t\t\t// The account may have been locked after cookie validation.\n\t\t\t\tclear_auth_cookie();");
});

test('basic auth transitions before setting the session user', function () use ($includeSource) {
	expect($includeSource)->toContain("cacti_auth_transition((int) \$current_user['id'], 'basic_auth')");
});

test('password login transitions before setting the session user', function () use ($loginSource) {
	expect($loginSource)->toContain("cacti_auth_transition((int) \$user['id'], 'login')")
		->and(strpos($loginSource, "cacti_auth_transition((int) \$user['id'], 'login')"))
		->toBeLessThan(strpos($loginSource, 'set_auth_cookie($user);'));
});

// --- the sweep: no site may set the session user without transitioning ---

test('every authenticated transition is covered', function () use ($includeSource, $loginSource) {
	$uncovered = [];

	foreach (['include/auth.php' => $includeSource, 'auth_login.php' => $loginSource] as $name => $source) {
		$lines = explode("\n", $source);

		foreach ($lines as $i => $line) {
			if (!preg_match('/\$_SESSION\[SESS_USER_ID\]\s*=/', $line)) {
				continue;
			}

			// the guest account is not a privilege gain and is exempt
			$window = implode("\n", array_slice($lines, max(0, $i - 20), 21));

			if (str_contains($window, 'guest_user_id')) {
				continue;
			}

			if (!str_contains($window, 'cacti_auth_transition')) {
				$uncovered[] = $name . ':' . ($i + 1) . ' ' . trim($line);
			}
		}
	}

	expect($uncovered)->toBe([]);
});
