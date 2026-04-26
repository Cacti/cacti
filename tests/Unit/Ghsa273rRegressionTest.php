<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$authLoginSource    = file_get_contents(__DIR__ . '/../../auth_login.php');
$includeAuthSource  = file_get_contents(__DIR__ . '/../../include/auth.php');
$libAuthSource      = file_get_contents(__DIR__ . '/../../lib/auth.php');

test('GHSA-273r-qr93-wgcp: cacti_auth_transition rotates the session id', function () use ($libAuthSource) {
	// The shared auth-transition helper must call session_regenerate_id(true)
	// so every entry point that goes through it gets a fresh session id.
	$start = strpos($libAuthSource, 'function cacti_auth_transition(');
	expect($start)->not->toBeFalse();

	$body = substr($libAuthSource, $start, 4000);
	expect($body)->toContain('session_regenerate_id(true);');
});

test('GHSA-273r-qr93-wgcp: form login routes through cacti_auth_transition before assigning sess_user_id', function () use ($authLoginSource) {
	$transitionPos = strpos($authLoginSource, "cacti_auth_transition((int)\$user['id'], 'login')");
	$assignPos     = strpos($authLoginSource, "\$_SESSION['sess_user_id'] = \$user['id']");

	expect($transitionPos)->not->toBeFalse();
	expect($assignPos)->not->toBeFalse();
	expect($transitionPos)->toBeLessThan($assignPos);
});

test('GHSA-273r-qr93-wgcp: cookie-restore path routes through cacti_auth_transition', function () use ($includeAuthSource) {
	// Without this guard, an attacker who pre-seeded the session cookie
	// inherits the authenticated session when the remember-me cookie restores.
	$transitionPos = strpos($includeAuthSource, "cacti_auth_transition((int)\$cookie_user, 'cookie_restore')");
	$assignPos     = strpos($includeAuthSource, "\$_SESSION['sess_user_id'] = \$cookie_user");

	expect($transitionPos)->not->toBeFalse();
	expect($assignPos)->not->toBeFalse();
	expect($transitionPos)->toBeLessThan($assignPos);
});

test('GHSA-273r-qr93-wgcp: basic-auth path routes through cacti_auth_transition', function () use ($includeAuthSource) {
	$transitionPos = strpos($includeAuthSource, "cacti_auth_transition((int)\$current_user['id'], 'basic_auth')");
	$assignPos     = strpos($includeAuthSource, "\$_SESSION['sess_user_id'] = \$current_user['id']");

	expect($transitionPos)->not->toBeFalse();
	expect($assignPos)->not->toBeFalse();
	expect($transitionPos)->toBeLessThan($assignPos);
});
