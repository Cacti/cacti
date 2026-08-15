<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Consolidated session-handling regression tests.
 *
 * Combines the previously separate per-advisory test files for:
 * GHSA-273r-qr93-wgcp, GHSA-6rvg-2vm8-5wrf, and GHSA-rx6j-2pxr-p6gj.
 *
 * Each test below keeps its original GHSA identifier in its description
 * so the advisory it guards against remains traceable.
 */

$authLoginSource   = file_get_contents(__DIR__ . '/../../../../auth_login.php');
$includeAuthSource = file_get_contents(__DIR__ . '/../../../../include/auth.php');
$libAuthSource     = file_get_contents(__DIR__ . '/../../../../lib/auth.php');

// GHSA-273r: session fixation across login / cookie-restore / basic-auth transitions.
test('GHSA-273r: cacti_auth_transition rotates the session id', function () use ($libAuthSource) {
	// The shared auth-transition helper must call session_regenerate_id(true)
	// so every entry point that goes through it gets a fresh session id.
	$start = strpos($libAuthSource, 'function cacti_auth_transition(');
	expect($start)->not->toBeFalse();

	$body = substr($libAuthSource, $start, 4000);
	expect($body)->toContain('session_regenerate_id(true);');
});

test('GHSA-273r: form login routes through cacti_auth_transition before assigning sess_user_id', function () use ($authLoginSource) {
	$transitionPos = strpos($authLoginSource, "cacti_auth_transition((int)\$user['id'], 'login')");
	$assignPos     = strpos($authLoginSource, "\$_SESSION['sess_user_id'] = \$user['id']");

	expect($transitionPos)->not->toBeFalse();
	expect($assignPos)->not->toBeFalse();
	expect($transitionPos)->toBeLessThan($assignPos);
});

test('GHSA-273r: cookie-restore path routes through cacti_auth_transition', function () use ($includeAuthSource) {
	// Without this guard, an attacker who pre-seeded the session cookie
	// inherits the authenticated session when the remember-me cookie restores.
	$transitionPos = strpos($includeAuthSource, "cacti_auth_transition((int)\$cookie_user, 'cookie_restore')");
	$assignPos     = strpos($includeAuthSource, "\$_SESSION['sess_user_id'] = \$cookie_user");

	expect($transitionPos)->not->toBeFalse();
	expect($assignPos)->not->toBeFalse();
	expect($transitionPos)->toBeLessThan($assignPos);
});

test('GHSA-273r: basic-auth path routes through cacti_auth_transition', function () use ($includeAuthSource) {
	$transitionPos = strpos($includeAuthSource, "cacti_auth_transition((int)\$current_user['id'], 'basic_auth')");
	$assignPos     = strpos($includeAuthSource, "\$_SESSION['sess_user_id'] = \$current_user['id']");

	expect($transitionPos)->not->toBeFalse();
	expect($assignPos)->not->toBeFalse();
	expect($transitionPos)->toBeLessThan($assignPos);
});

// GHSA-6rvg: guest session must be fully wiped before a real session reuses it.
test('GHSA-6rvg: guest session wipe is gated by get_guest_account comparison', function () use ($includeAuthSource) {
	// The wipe block only runs when the current session belongs to the
	// guest account; otherwise a regular user session would be killed.
	expect($includeAuthSource)->toContain("if (get_guest_account() === \$_SESSION['sess_user_id']) {");
});

test('GHSA-6rvg: guest wipe chain is kill -> destroy -> start', function () use ($includeAuthSource) {
	// Locate the exact guest-gated block and confirm the three calls
	// appear in the required order inside it.
	$gate = "if (get_guest_account() === \$_SESSION['sess_user_id']) {";
	$pos  = strpos($includeAuthSource, $gate);
	expect($pos)->not->toBeFalse();

	$block = substr($includeAuthSource, $pos, 400);
	expect($block)->toContain("kill_session_var('sess_user_id')");
	expect($block)->toContain('cacti_session_destroy();');
	expect($block)->toContain('cacti_session_start(true);');

	$killPos    = strpos($block, "kill_session_var('sess_user_id')");
	$destroyPos = strpos($block, 'cacti_session_destroy();');
	$startPos   = strpos($block, 'cacti_session_start(true);');

	expect($killPos)->toBeLessThan($destroyPos);
	expect($destroyPos)->toBeLessThan($startPos);
});

test('GHSA-6rvg: guest wipe only fires when no guest_account flag is set', function () use ($includeAuthSource) {
	// The outer guard ensures the wipe does not trigger on pages that
	// legitimately run as the guest account.
	expect($includeAuthSource)->toContain("if (!isset(\$guest_account) && isset(\$_SESSION['sess_user_id'])) {");
});

// GHSA-rx6j: guest-to-auth transition must destroy and restart the session so the
// guest cookie cannot be reused post-login.
test('GHSA-rx6j: guest-to-auth transition destroys then restarts the session', function () use ($includeAuthSource) {
	expect($includeAuthSource)->toContain('cacti_session_destroy();');
	expect($includeAuthSource)->toContain('cacti_session_start(true);');

	$destroyPos = strpos($includeAuthSource, 'cacti_session_destroy();');
	$restartPos = strpos($includeAuthSource, 'cacti_session_start(true);');
	expect($destroyPos)->not->toBeFalse();
	expect($restartPos)->not->toBeFalse();
	expect($destroyPos)->toBeLessThan($restartPos);
});

test('GHSA-rx6j: sess_user_id is killed before destroy', function () use ($includeAuthSource) {
	$killPos    = strpos($includeAuthSource, "kill_session_var('sess_user_id')");
	$destroyPos = strpos($includeAuthSource, 'cacti_session_destroy();');

	expect($killPos)->not->toBeFalse();
	expect($destroyPos)->not->toBeFalse();
	expect($killPos)->toBeLessThan($destroyPos);
});
