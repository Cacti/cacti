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

require_once dirname(__DIR__, 2) . '/lib/csrf.php';

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;

/*
 * During the upgrade window a browser may still hold a csrf-magic token minted
 * before the swap.  Those are accepted until they expire on the timestamp they
 * carry, so the window closes itself with no configuration flag.  These tests
 * forge legacy tokens the way csrf-magic minted them.
 */

const CSRF_TEST_SECRET = 'a-known-test-secret';

function csrf_test_legacy_guard() : CactiCsrfGuard {
	$_SESSION = [];

	$storage = new NativeSessionTokenStorage();
	$manager = new CsrfTokenManager(new UriSafeTokenGenerator(), $storage);

	return new CactiCsrfGuard($manager, true, CSRF_TEST_SECRET, 7200);
}

/**
 * Rebuild a csrf-magic 'sid:' token exactly as the old library produced it.
 *
 * @param string $sessionId The session id the token was minted against.
 * @param int    $time      The unix time embedded in the token.
 * @param string $secret    The shared secret.
 *
 * @return string The forged legacy token.
 */
function csrf_test_forge_legacy(string $sessionId, int $time, string $secret) : string {
	$inner = hash_hmac('sha1', $time . ':' . $sessionId, $secret);
	$outer = hash_hmac('sha1', $inner, $secret);

	return 'sid:' . $outer . ',' . $time;
}

test('a legacy token inside the window validates', function () {
	$guard = csrf_test_legacy_guard();
	$token = csrf_test_forge_legacy(session_id(), time(), CSRF_TEST_SECRET);

	expect($guard->validate($token))->toBeTrue();
});

test('a legacy token past the window is rejected', function () {
	$guard = csrf_test_legacy_guard();
	$token = csrf_test_forge_legacy(session_id(), time() - 7201, CSRF_TEST_SECRET);

	expect($guard->validate($token))->toBeFalse();
});

test('a legacy token signed with the wrong secret is rejected', function () {
	$guard = csrf_test_legacy_guard();
	$token = csrf_test_forge_legacy(session_id(), time(), 'a-different-secret');

	expect($guard->validate($token))->toBeFalse();
});

test('values with no colon never reach the legacy path', function () {
	$guard = csrf_test_legacy_guard();

	expect($guard->validate('nocolonhere'))->toBeFalse();
	expect($guard->validate(''))->toBeFalse();
});

test('legacy validation is off when no secret is configured', function () {
	$_SESSION = [];

	$storage = new NativeSessionTokenStorage();
	$manager = new CsrfTokenManager(new UriSafeTokenGenerator(), $storage);
	$guard   = new CactiCsrfGuard($manager, true, '', 7200);

	$token = csrf_test_forge_legacy(session_id(), time(), CSRF_TEST_SECRET);

	expect($guard->validate($token))->toBeFalse();
});
