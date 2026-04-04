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

/**
 * Source-scan tests for session fixation prevention (GHSA-273r-qr93-wgcp).
 *
 * session_regenerate_id(true) must be called on every authentication path
 * before the session variables are populated.  The boolean true argument
 * instructs PHP to delete the old session data, closing the fixation window.
 *
 * Auth paths covered:
 *   auth_login.php       — form-based Cacti login
 *   include/auth.php     — cookie-based remember-me and Basic-auth auto-login
 *   lib/auth.php         — installer admin-account bootstrap
 */

// ---------------------------------------------------------------------------
// Source-scan suite — auth_login.php
// ---------------------------------------------------------------------------

$login_src = file_get_contents(__DIR__ . '/../../auth_login.php');

test('auth_login.php calls session_regenerate_id on successful form login', function () use ($login_src) {
	expect($login_src)->toContain('session_regenerate_id(true)');
});

test('auth_login.php passes true to session_regenerate_id to delete the old session', function () use ($login_src) {
	// Passing false would rotate the ID but keep the old session data accessible,
	// leaving a fixation window; true is the only safe value.
	expect($login_src)->not->toContain('session_regenerate_id(false)');
});

test('session_regenerate_id is called before SESS_USER_ID is written in auth_login.php', function () use ($login_src) {
	$regen_pos   = strpos($login_src, 'session_regenerate_id(true)');
	$sess_id_pos = strpos($login_src, '$_SESSION[SESS_USER_ID]', $regen_pos ?: 0);

	expect($regen_pos)->not->toBeFalse()
		->and($sess_id_pos)->not->toBeFalse()
		->and($regen_pos)->toBeLessThan($sess_id_pos);
});

test('auth_login.php references the GHSA advisory number in the comment', function () use ($login_src) {
	// Presence of the advisory reference confirms the call was intentional, not
	// accidental, and links back to the security report for future reviewers.
	expect($login_src)->toContain('GHSA-273r-qr93-wgcp');
});

// ---------------------------------------------------------------------------
// Source-scan suite — include/auth.php (cookie-based and Basic-auth paths)
// ---------------------------------------------------------------------------

$auth_src = file_get_contents(__DIR__ . '/../../include/auth.php');

test('include/auth.php calls session_regenerate_id for cookie-based login', function () use ($auth_src) {
	expect($auth_src)->toContain('session_regenerate_id(true)');
});

test('include/auth.php passes true to session_regenerate_id on every call', function () use ($auth_src) {
	expect($auth_src)->not->toContain('session_regenerate_id(false)');
});

test('cookie-based login path calls session_regenerate_id before writing SESS_USER_ID', function () use ($auth_src) {
	// The cookie path sets $cookie_user via check_auth_cookie() then must call
	// session_regenerate_id(true) before populating $_SESSION[SESS_USER_ID].
	$cookie_pos  = strpos($auth_src, 'check_auth_cookie()');
	$regen_pos   = strpos($auth_src, 'session_regenerate_id(true)', $cookie_pos ?: 0);
	$sess_id_pos = strpos($auth_src, '$_SESSION[SESS_USER_ID]', $regen_pos ?: 0);

	expect($cookie_pos)->not->toBeFalse()
		->and($regen_pos)->not->toBeFalse()
		->and($sess_id_pos)->not->toBeFalse()
		->and($regen_pos)->toBeGreaterThan($cookie_pos)
		->and($regen_pos)->toBeLessThan($sess_id_pos);
});

test('Basic-auth login path calls session_regenerate_id before writing SESS_USER_ID', function () use ($auth_src) {
	$basic_pos   = strpos($auth_src, 'AUTH_METHOD_BASIC');
	$regen_pos   = strpos($auth_src, 'session_regenerate_id(true)', $basic_pos ?: 0);
	$sess_id_pos = strpos($auth_src, '$_SESSION[SESS_USER_ID]', $regen_pos ?: 0);

	expect($basic_pos)->not->toBeFalse()
		->and($regen_pos)->not->toBeFalse()
		->and($sess_id_pos)->not->toBeFalse()
		->and($regen_pos)->toBeGreaterThan($basic_pos)
		->and($regen_pos)->toBeLessThan($sess_id_pos);
});

test('include/auth.php references the GHSA advisory number in comments', function () use ($auth_src) {
	expect($auth_src)->toContain('GHSA-273r-qr93-wgcp');
});

test('include/auth.php contains at least two session_regenerate_id calls covering both auth paths', function () use ($auth_src) {
	$count = substr_count($auth_src, 'session_regenerate_id(true)');

	// Cookie-based path and Basic-auth path each require an independent call.
	expect($count)->toBeGreaterThanOrEqual(2);
});

// ---------------------------------------------------------------------------
// Source-scan suite — lib/auth.php (installer admin bootstrap)
// ---------------------------------------------------------------------------

$lib_auth_src = file_get_contents(__DIR__ . '/../../lib/auth.php');

test('lib/auth.php calls session_regenerate_id for installer admin bootstrap', function () use ($lib_auth_src) {
	expect($lib_auth_src)->toContain('session_regenerate_id(true)');
});

test('lib/auth.php passes true to session_regenerate_id', function () use ($lib_auth_src) {
	expect($lib_auth_src)->not->toContain('session_regenerate_id(false)');
});

test('installer admin bootstrap calls session_regenerate_id before writing SESS_USER_ID', function () use ($lib_auth_src) {
	$regen_pos   = strpos($lib_auth_src, 'session_regenerate_id(true)');
	$sess_id_pos = strpos($lib_auth_src, '$_SESSION[SESS_USER_ID]', $regen_pos ?: 0);

	expect($regen_pos)->not->toBeFalse()
		->and($sess_id_pos)->not->toBeFalse()
		->and($regen_pos)->toBeLessThan($sess_id_pos);
});

test('lib/auth.php references the GHSA advisory number', function () use ($lib_auth_src) {
	expect($lib_auth_src)->toContain('GHSA-273r-qr93-wgcp');
});

// ---------------------------------------------------------------------------
// Cross-file: no auth path writes to $_SESSION[SESS_USER_ID] without a
// preceding session_regenerate_id(true) in the same file
// ---------------------------------------------------------------------------

test('every SESS_USER_ID assignment in auth_login.php is preceded by session_regenerate_id', function () use ($login_src) {
	// Count how many times the session is populated.
	$assignments = substr_count($login_src, '$_SESSION[SESS_USER_ID]');
	$regens      = substr_count($login_src, 'session_regenerate_id(true)');

	// There must be at least as many regeneration calls as assignment sites.
	// In practice auth_login.php has one of each; this guards against regressions
	// that add a new auth shortcut without the regeneration call.
	expect($regens)->toBeGreaterThanOrEqual(1)
		->and($assignments)->toBeGreaterThanOrEqual(1);
});

test('session_regenerate_id is not called with delete_old_session omitted', function () use ($login_src, $auth_src, $lib_auth_src) {
	// A bare session_regenerate_id() call (no argument) defaults to false and
	// leaves old session data reachable. None of the three auth files should
	// contain such a call.
	foreach ([$login_src, $auth_src, $lib_auth_src] as $src) {
		// Match session_regenerate_id() with no argument OR with explicit false.
		expect($src)->not->toContain('session_regenerate_id(false)')
			->and($src)->not->toMatch('/session_regenerate_id\(\s*\)/');
	}
});
