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

require_once CACTI_PATH_LIBRARY . '/csrf.php';

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

/**
 * Ensure a session exists before a test reads session_id().
 *
 * A legacy token binds to the session id, and NativeSessionTokenStorage only
 * starts a session lazily on first access.  Without this the id a test forges
 * against is empty while the id validate() later sees is real, so this file
 * passed only when some earlier file in the run had already started one.
 */
function csrf_test_start_session() : void {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
}

function csrf_test_legacy_guard() : CactiCsrfGuard {
	csrf_test_start_session();

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

test('malformed legacy token bodies are rejected', function () {
	$guard = csrf_test_legacy_guard();

	expect($guard->validate('sid:missing-comma'))->toBeFalse()
		->and($guard->validate('sid:hash,not-a-timestamp'))->toBeFalse();
});

test('legacy validation is off when no secret is configured', function () {
	csrf_test_start_session();

	$_SESSION = [];

	$storage = new NativeSessionTokenStorage();
	$manager = new CsrfTokenManager(new UriSafeTokenGenerator(), $storage);
	$guard   = new CactiCsrfGuard($manager, true, '', 7200);

	$token = csrf_test_forge_legacy(session_id(), time(), CSRF_TEST_SECRET);

	expect($guard->validate($token))->toBeFalse();
});

/*
 * cli/refresh_csrf.php does not write a bare digest.  It writes PHP source:
 *
 *     fwrite($fh, '<?php $secret = "' . $new_secret . '";' . PHP_EOL);
 *
 * csrf-magic keyed its HMAC on the raw bytes of that file, PHP tags and
 * trailing newline included.  Any site that followed Cacti's own rotation
 * advice has a secret in this shape, so a reader that normalises whitespace
 * derives a different key and rejects every pre-upgrade token it was added to
 * accept -- silently, because a rejected token is indistinguishable from an
 * expired one.
 */

/**
 * Write a secret file the way cli/refresh_csrf.php writes it.
 *
 * @param string $digest The hex digest to embed.
 *
 * @return string Path to the temporary file, which the caller must unlink.
 */
function csrf_test_write_rotated_secret(string $digest) : string {
	$file = tempnam(sys_get_temp_dir(), 'cacti-csrf-secret-');

	file_put_contents($file, '<?php $secret = "' . $digest . '";' . PHP_EOL);

	return $file;
}

test('a secret rotated by refresh_csrf.php still validates a pre-upgrade token', function () {
	$file = csrf_test_write_rotated_secret(str_repeat('ab', 20));

	try {
		$raw = (string) file_get_contents($file);

		expect($raw)->toEndWith(PHP_EOL)
			->and($raw)->not->toBe(trim($raw));

		csrf_test_start_session();

		$_SESSION = [];

		$manager = new CsrfTokenManager(new UriSafeTokenGenerator(), new NativeSessionTokenStorage());
		$token   = csrf_test_forge_legacy(session_id(), time(), $raw);

		$guard = new CactiCsrfGuard($manager, true, $raw, 7200);
		expect($guard->validate($token))->toBeTrue();

		// the same file read through trim() is a different key
		$trimmed = new CactiCsrfGuard($manager, true, trim($raw), 7200);
		expect($trimmed->validate($token))->toBeFalse();
	} finally {
		unlink($file);
	}
});

test('neither secret reader normalises the bytes it reads', function () {
	$src = file_get_contents(CACTI_PATH_INCLUDE . '/csrf.php');

	expect($src)->toContain('file_get_contents(CACTI_CSRF_SECRET)')
		->and($src)->toContain('(string) @file_get_contents($file)')
		->and($src)->not->toContain('trim(');
});
