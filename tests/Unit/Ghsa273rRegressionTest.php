<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$authLoginSource = file_get_contents(__DIR__ . '/../../auth_login.php');

test('GHSA-273r-qr93-wgcp: auth_login regenerates the session on successful login', function () use ($authLoginSource) {
	// The post-auth code path must start a fresh session so the
	// pre-authentication session id cannot be reused.
	expect($authLoginSource)->toContain('cacti_session_start(true);');
});

test('GHSA-273r-qr93-wgcp: session regen is tagged as session-fixation defense', function () use ($authLoginSource) {
	// The inline comment pins the intent of the call so a future refactor
	// cannot silently drop the regen.
	expect($authLoginSource)->toContain('/* avoid session fixation */');
});

test('GHSA-273r-qr93-wgcp: regen happens before sess_user_id is populated', function () use ($authLoginSource) {
	// If the user id were written before the session id was rotated, an
	// attacker-supplied session id would end up authenticated.
	$regenPos  = strpos($authLoginSource, 'cacti_session_start(true);');
	$assignPos = strpos($authLoginSource, "\$_SESSION['sess_user_id'] = \$user['id']");

	expect($regenPos)->not->toBeFalse();
	expect($assignPos)->not->toBeFalse();
	expect($regenPos)->toBeLessThan($assignPos);
});
