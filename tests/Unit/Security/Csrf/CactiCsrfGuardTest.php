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

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;

require_once CACTI_PATH_LIBRARY . '/csrf.php';

/*
 * Build a guard over a real Symfony manager backed by the process $_SESSION.
 * NativeSessionTokenStorage reads and writes $_SESSION directly, so no HTTP
 * foundation or session handler is needed; seeding the superglobal is enough.
 */
function csrf_test_guard() : CactiCsrfGuard {
	$_SESSION = [];

	$storage = new NativeSessionTokenStorage();
	$manager = new CsrfTokenManager(new UriSafeTokenGenerator(), $storage);

	return new CactiCsrfGuard($manager, true);
}

test('a freshly issued token validates', function () {
	$guard = csrf_test_guard();

	expect($guard->validate($guard->token()))->toBeTrue();
});

test('a mutated token does not validate', function () {
	$guard = csrf_test_guard();
	$token = $guard->token();

	expect($guard->validate($token . 'x'))->toBeFalse();
	expect($guard->validate(strrev($token)))->toBeFalse();
});

/*
 * Symfony randomises the encoding on every getToken() call to mitigate BREACH.
 * The hidden form field and the JS global therefore hold different strings on
 * the same page render, and both must validate.  This looks like a bug and is
 * not one.
 */
test('two issued tokens differ and both validate', function () {
	$guard = csrf_test_guard();

	$first  = $guard->token();
	$second = $guard->token();

	expect($first)->not->toBe($second);
	expect($guard->validate($first))->toBeTrue();
	expect($guard->validate($second))->toBeTrue();
});

test('empty and malformed values are rejected', function () {
	$guard = csrf_test_guard();
	$guard->token();

	expect($guard->validate(''))->toBeFalse();
	expect($guard->validate('not-a-token'))->toBeFalse();
});

test('a disabled guard issues nothing and validates nothing', function () {
	$guard = new CactiCsrfGuard(null, false);

	expect($guard->isEnabled())->toBeFalse();
	expect($guard->token())->toBe('');
	expect($guard->validate('anything'))->toBeFalse();
});
